<?php

namespace App\Controllers;

use App\Models\Room;
use App\Models\Schedule;
use App\Models\EventBooking;
use App\Services\ConflictEngine;
use Flight;
use PDO;

class HomeController {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function index(): void {
        $now = time();
        $date = date('Y-m-d', $now);
        $dayOfWeek = (int) date('N', $now);
        $currentTime = date('H:i', $now);

        $roomStatuses = $this->getRoomStatusesForTime($dayOfWeek, $date, $currentTime);

        echo Flight::view()->render('home/index.html.twig', [
            'active_nav' => 'live',
            'rooms' => $roomStatuses,
            'current_date' => date('d F Y', $now),
            'current_time' => $currentTime,
            'day_name' => $this->getDayNameIndonesian($dayOfWeek),
        ]);
    }

    public function calendar(): void {
        $rooms = Room::getAllActive($this->db);
        echo Flight::view()->render('home/calendar.html.twig', [
            'active_nav' => 'calendar',
            'rooms' => $rooms,
            'today' => date('Y-m-d'),
        ]);
    }

    public function getRoomStatusesForTime(int $dayOfWeek, string $date, string $currentTime): array {
        $rooms = Room::getAllActive($this->db);
        $result = [];

        foreach ($rooms as $room) {
            $roomId = $room['id'];

            // 1. Check current ongoing lecture
            $stmt = $this->db->prepare("SELECT * FROM schedules 
                                        WHERE room_id = :room_id 
                                          AND day_of_week = :day 
                                          AND start_time <= :current_time_a 
                                          AND end_time > :current_time_b
                                        ORDER BY start_time ASC LIMIT 1");
            $stmt->execute([
                ':room_id' => $roomId,
                ':day' => $dayOfWeek,
                ':current_time_a' => $currentTime,
                ':current_time_b' => $currentTime,
            ]);
            $currentLecture = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($currentLecture) {
                $room['status'] = 'lecture';
                $room['current_activity'] = $currentLecture;
                $room['status_label'] = 'Sedang Kuliah';
                $room['status_badge'] = 'bg-blue-100 text-blue-700 border-blue-200';
                $result[] = $room;
                continue;
            }

            // 2. Check current ongoing approved event
            $stmt = $this->db->prepare("SELECT * FROM event_bookings 
                                        WHERE room_id = :room_id 
                                          AND booking_date = :date 
                                          AND status = 'approved'
                                          AND start_time <= :current_time_c 
                                          AND end_time > :current_time_d
                                        ORDER BY start_time ASC LIMIT 1");
            $stmt->execute([
                ':room_id' => $roomId,
                ':date' => $date,
                ':current_time_c' => $currentTime,
                ':current_time_d' => $currentTime,
            ]);
            $currentEvent = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($currentEvent) {
                $room['status'] = 'event';
                $room['current_activity'] = $currentEvent;
                $room['status_label'] = 'Sedang Acara';
                $room['status_badge'] = 'bg-amber-100 text-amber-700 border-amber-200';
                $result[] = $room;
                continue;
            }

            // 3. Room is currently empty - check next upcoming activity today
            $stmt = $this->db->prepare("SELECT * FROM schedules 
                                        WHERE room_id = :room_id 
                                          AND day_of_week = :day 
                                          AND start_time > :current_time 
                                        ORDER BY start_time ASC LIMIT 1");
            $stmt->execute([
                ':room_id' => $roomId,
                ':day' => $dayOfWeek,
                ':current_time' => $currentTime,
            ]);
            $nextLecture = $stmt->fetch(PDO::FETCH_ASSOC);

            $room['status'] = 'empty';
            $room['current_activity'] = null;
            $room['next_activity'] = $nextLecture ?: null;
            $room['status_label'] = 'Tersedia / Kosong';
            $room['status_badge'] = 'bg-emerald-100 text-emerald-700 border-emerald-200';
            $result[] = $room;
        }

        return $result;
    }

    private function getDayNameIndonesian(int $dayOfWeek): string {
        $days = [
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
            7 => 'Minggu',
        ];
        return $days[$dayOfWeek] ?? 'Hari Ini';
    }
}
