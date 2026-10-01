<?php

namespace App\Services;

use PDO;

class ConflictEngine {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    /**
     * Checks if two time windows [start1, end1) and [start2, end2) overlap.
     * Uses strict time overlap formula: start1 < end2 && end1 > start2.
     * Adjacent slots (e.g. 08:00-10:00 and 10:00-12:00) DO NOT overlap.
     */
    public static function hasTimeOverlap(string $start1, string $end1, string $start2, string $end2): bool {
        $s1 = strtotime("1970-01-01 " . $start1);
        $e1 = strtotime("1970-01-01 " . $end1);
        $s2 = strtotime("1970-01-01 " . $start2);
        $e2 = strtotime("1970-01-01 " . $end2);

        return ($s1 < $e2) && ($e1 > $s2);
    }

    /**
     * Checks if a new lecture schedule conflicts with existing lectures on the same day and room.
     */
    public function checkLectureConflict(int $roomId, int $dayOfWeek, string $start, string $end, ?int $ignoreId = null): ?array {
        $sql = "SELECT s.*, r.name as room_name 
                FROM schedules s
                JOIN rooms r ON r.id = s.room_id
                WHERE s.room_id = :room_id AND s.day_of_week = :day_of_week";
        $params = [
            ':room_id' => $roomId,
            ':day_of_week' => $dayOfWeek,
        ];

        if ($ignoreId !== null) {
            $sql .= " AND s.id != :ignore_id";
            $params[':ignore_id'] = $ignoreId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $existing = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($existing as $schedule) {
            if (self::hasTimeOverlap($start, $end, $schedule['start_time'], $schedule['end_time'])) {
                return $schedule;
            }
        }

        return null;
    }

    /**
     * Checks if an event booking conflicts with regular lectures (strict priority)
     * or approved events in the same room on the specified date.
     */
    public function checkEventConflict(int $roomId, string $date, string $start, string $end, ?int $ignoreId = null): array {
        // 1. Calculate day of week (1=Monday .. 7=Sunday)
        $dayOfWeek = (int) date('N', strtotime($date));

        // 2. CHECK REGULAR LECTURES (PRIORITY #1)
        $lectureConflict = $this->checkLectureConflict($roomId, $dayOfWeek, $start, $end);
        if ($lectureConflict !== null) {
            $courseName = $lectureConflict['course_name'];
            $className = $lectureConflict['class_name'];
            $timeSlot = "{$lectureConflict['start_time']} - {$lectureConflict['end_time']}";
            $roomName = $lectureConflict['room_name'] ?? "Ruangan #{$roomId}";

            return [
                'allowed' => false,
                'conflict_type' => 'lecture_priority',
                'reason' => "Ruangan {$roomName} sedang digunakan untuk perkuliahan {$courseName} ({$className}) pukul {$timeSlot}. Perkuliahan reguler memiliki prioritas utama.",
                'details' => $lectureConflict,
            ];
        }

        // 3. CHECK APPROVED EVENTS (PRIORITY #2)
        $sql = "SELECT b.*, r.name as room_name 
                FROM event_bookings b
                JOIN rooms r ON r.id = b.room_id
                WHERE b.room_id = :room_id 
                  AND b.booking_date = :booking_date 
                  AND b.status = 'approved'";
        $params = [
            ':room_id' => $roomId,
            ':booking_date' => $date,
        ];

        if ($ignoreId !== null) {
            $sql .= " AND b.id != :ignore_id";
            $params[':ignore_id'] = $ignoreId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $existingEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($existingEvents as $event) {
            if (self::hasTimeOverlap($start, $end, $event['start_time'], $event['end_time'])) {
                $eventName = $event['event_name'];
                $organizer = $event['organizer'];
                $timeSlot = "{$event['start_time']} - {$event['end_time']}";
                $roomName = $event['room_name'] ?? "Ruangan #{$roomId}";

                return [
                    'allowed' => false,
                    'conflict_type' => 'event_conflict',
                    'reason' => "Ruangan {$roomName} sudah terpesan untuk acara '{$eventName}' oleh {$organizer} pukul {$timeSlot}.",
                    'details' => $event,
                ];
            }
        }

        // 4. ROOM IS FREE
        return [
            'allowed' => true,
            'conflict_type' => null,
            'reason' => null,
            'details' => null,
        ];
    }
}
