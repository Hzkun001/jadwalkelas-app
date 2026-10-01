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
        $this->calendar();
    }

    public function live(): void {
        $now = time();
        $date = date('Y-m-d', $now);
        $dayOfWeek = (int) date('N', $now);
        $currentTime = date('H:i', $now);

        $roomStatuses = $this->getRoomStatusesForTime($dayOfWeek, $date, $currentTime);

        $totalRooms = count($roomStatuses);
        $availableCount = count(array_filter($roomStatuses, fn($r) => $r['status'] === 'empty'));
        $lectureCount = count(array_filter($roomStatuses, fn($r) => $r['status'] === 'lecture'));
        $eventCount = count(array_filter($roomStatuses, fn($r) => $r['status'] === 'event'));

        $saintekRooms = array_values(array_filter($roomStatuses, fn($r) => $r['building_group'] === 'saintek'));
        $labRooms = array_values(array_filter($roomStatuses, fn($r) => $r['building_group'] === 'lab'));

        echo Flight::view()->render('home/index.html.twig', [
            'active_nav' => 'live',
            'rooms' => $roomStatuses,
            'saintek_rooms' => $saintekRooms,
            'lab_rooms' => $labRooms,
            'current_date' => date('d F Y', $now),
            'current_time' => $currentTime,
            'day_name' => $this->getDayNameIndonesian($dayOfWeek),
            'stats' => [
                'total' => $totalRooms,
                'available' => $availableCount,
                'lecture' => $lectureCount,
                'event' => $eventCount,
            ],
        ]);
    }

    public function calendar(): void {
        $rooms = Room::getAllActive($this->db);
        $now = time();
        $date = date('Y-m-d', $now);
        $dayOfWeek = (int) date('N', $now);
        $currentTime = date('H:i', $now);

        $roomStatuses = $this->getRoomStatusesForTime($dayOfWeek, $date, $currentTime);
        $totalRooms = count($roomStatuses);
        $availableCount = count(array_filter($roomStatuses, fn($r) => $r['status'] === 'empty'));
        $lectureCount = count(array_filter($roomStatuses, fn($r) => $r['status'] === 'lecture'));
        $eventCount = count(array_filter($roomStatuses, fn($r) => $r['status'] === 'event'));

        $saintekRooms = array_values(array_filter($rooms, fn($r) => (stripos($r['name'], 'fst') === 0) || (stripos($r['code'], 'fst') === 0)));
        $labRooms = array_values(array_filter($rooms, fn($r) => !((stripos($r['name'], 'fst') === 0) || (stripos($r['code'], 'fst') === 0))));

        echo Flight::view()->render('home/calendar.html.twig', [
            'active_nav' => 'calendar',
            'rooms' => $rooms,
            'saintek_rooms' => $saintekRooms,
            'lab_rooms' => $labRooms,
            'today' => $date,
            'current_time' => $currentTime,
            'day_name' => $this->getDayNameIndonesian($dayOfWeek),
            'current_date' => date('d F Y', $now),
            'stats' => [
                'total' => $totalRooms,
                'available' => $availableCount,
                'lecture' => $lectureCount,
                'event' => $eventCount,
            ],
        ]);
    }

    public function getRoomStatusesForTime(int $dayOfWeek, string $date, string $currentTime): array {
        $rooms = Room::getAllActive($this->db);
        $result = [];

        foreach ($rooms as $room) {
            $roomId = $room['id'];

            // Tag building group
            $isSaintek = (stripos($room['name'], 'fst') === 0) || (stripos($room['code'], 'fst') === 0);
            $room['building_group'] = $isSaintek ? 'saintek' : 'lab';
            $room['building_name'] = $isSaintek ? 'Gedung Saintek' : 'Gedung Lab Komputer';

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
                $room['status_badge'] = 'bg-rose-100 text-rose-800 border-rose-300 font-bold';
                $room['status_border'] = 'border-l-rose-500';
                $room['status_dot'] = 'bg-rose-500';
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
                $room['status_badge'] = 'bg-amber-100 text-amber-900 border-amber-300 font-bold';
                $room['status_border'] = 'border-l-amber-500';
                $room['status_dot'] = 'bg-amber-500';
                $result[] = $room;
                continue;
            }

            // 3. Room is currently empty - check next upcoming activity today (lecture or event)
            $stmt = $this->db->prepare("SELECT id, course_name as title, start_time, 'lecture' as type FROM schedules 
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

            $stmt = $this->db->prepare("SELECT id, event_name as title, start_time, 'event' as type FROM event_bookings 
                                        WHERE room_id = :room_id 
                                          AND booking_date = :date 
                                          AND status = 'approved' 
                                          AND start_time > :current_time 
                                        ORDER BY start_time ASC LIMIT 1");
            $stmt->execute([
                ':room_id' => $roomId,
                ':date' => $date,
                ':current_time' => $currentTime,
            ]);
            $nextEvent = $stmt->fetch(PDO::FETCH_ASSOC);

            $nextActivity = null;
            if ($nextLecture && $nextEvent) {
                $nextActivity = ($nextLecture['start_time'] <= $nextEvent['start_time']) ? $nextLecture : $nextEvent;
            } elseif ($nextLecture) {
                $nextActivity = $nextLecture;
            } elseif ($nextEvent) {
                $nextActivity = $nextEvent;
            }

            $room['status'] = 'empty';
            $room['current_activity'] = null;
            $room['next_activity'] = $nextActivity;
            $room['status_label'] = 'Ruang Kosong';
            $room['status_badge'] = 'bg-emerald-100 text-emerald-800 border-emerald-300 font-bold';
            $room['status_border'] = 'border-l-emerald-500';
            $room['status_dot'] = 'bg-emerald-500';
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
