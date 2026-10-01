<?php

namespace App\Controllers;

use App\Models\Room;
use App\Models\Schedule;
use App\Models\EventBooking;
use App\Services\ConflictEngine;
use Flight;
use PDO;

class ApiController {
    private PDO $db;
    private ConflictEngine $engine;

    public function __construct(PDO $db) {
        $this->db = $db;
        $this->engine = new ConflictEngine($db);
    }

    public function getRooms(): array {
        $rooms = Room::getAllActive($this->db);
        return [
            'success' => true,
            'data' => $rooms,
        ];
    }

    public function checkAvailability(?array $input = null): array {
        if ($input === null) {
            $input = Flight::request()->query->getData();
        }

        $roomId = (int) ($input['room_id'] ?? 0);
        $date = trim($input['date'] ?? '');
        $start = trim($input['start'] ?? '');
        $end = trim($input['end'] ?? '');
        $ignoreId = isset($input['ignore_id']) ? (int) $input['ignore_id'] : null;

        if (!$roomId || empty($date) || empty($start) || empty($end)) {
            return [
                'available' => false,
                'conflict_type' => 'invalid_input',
                'reason' => 'Parameter ruangan, tanggal, jam mulai, dan jam selesai harus diisi lengkap.',
                'details' => null,
            ];
        }

        $result = $this->engine->checkEventConflict($roomId, $date, $start, $end, $ignoreId);

        return [
            'available' => $result['allowed'],
            'conflict_type' => $result['conflict_type'],
            'reason' => $result['reason'],
            'details' => $result['details'],
        ];
    }

    public function getEvents(?array $params = null): array {
        if ($params === null) {
            $params = Flight::request()->query->getData();
        }

        $startDate = $params['start'] ?? date('Y-m-d', strtotime('monday this week'));
        $endDate = $params['end'] ?? date('Y-m-d', strtotime('sunday this week'));
        $roomId = isset($params['room_id']) ? (int) $params['room_id'] : 0;

        $events = [];

        // 1. Fetch approved event bookings
        $bookings = EventBooking::getByDateRange($this->db, $startDate, $endDate, $roomId ?: null);
        foreach ($bookings as $b) {
            $events[] = [
                'id' => 'event-' . $b['id'],
                'event_id' => $b['id'],
                'title' => $b['event_name'],
                'start' => "{$b['booking_date']}T{$b['start_time']}",
                'end' => "{$b['booking_date']}T{$b['end_time']}",
                'type' => 'event',
                'room_id' => $b['room_id'],
                'room_name' => $b['room_name'],
                'organizer' => $b['organizer'],
                'description' => $b['description'],
                'color' => '#EA580C', // Oranye
            ];
        }

        // 2. Fetch recurring weekly schedules and project them onto dates in range
        if ($roomId > 0) {
            $stmt = $this->db->prepare("SELECT s.*, r.name as room_name, r.code as room_code 
                                        FROM schedules s
                                        JOIN rooms r ON r.id = s.room_id
                                        WHERE s.room_id = :room_id");
            $stmt->execute([':room_id' => $roomId]);
            $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $schedules = Schedule::getAll($this->db);
        }

        $current = strtotime($startDate);
        $endTimestamp = strtotime($endDate);

        while ($current <= $endTimestamp) {
            $currentDate = date('Y-m-d', $current);
            $dayOfWeek = (int) date('N', $current); // 1 (Mon) .. 7 (Sun)

            foreach ($schedules as $s) {
                if ((int)$s['day_of_week'] === $dayOfWeek) {
                    $events[] = [
                        'id' => 'lecture-' . $s['id'] . '-' . $currentDate,
                        'schedule_id' => $s['id'],
                        'title' => $s['course_name'],
                        'start' => "{$currentDate}T{$s['start_time']}",
                        'end' => "{$currentDate}T{$s['end_time']}",
                        'type' => 'lecture',
                        'room_id' => $s['room_id'],
                        'room_name' => $s['room_name'],
                        'class_name' => $s['class_name'],
                        'lecturer_name' => $s['lecturer_name'],
                        'academic_period' => $s['academic_period'],
                        'color' => '#2563EB', // Biru
                    ];
                }
            }

            $current = strtotime('+1 day', $current);
        }

        return [
            'success' => true,
            'data' => $events,
        ];
    }

    public function createBooking(): void {
        $data = Flight::request()->data->getData();
        $roomId = (int) ($data['room_id'] ?? 0);
        $eventName = trim($data['event_name'] ?? '');
        $organizer = trim($data['organizer'] ?? '');
        $bookingDate = trim($data['booking_date'] ?? '');
        $startTime = trim($data['start_time'] ?? '');
        $endTime = trim($data['end_time'] ?? '');
        $description = trim($data['description'] ?? '');

        if (!$roomId || empty($eventName) || empty($organizer) || empty($bookingDate) || empty($startTime) || empty($endTime)) {
            Flight::json([
                'success' => false,
                'message' => 'Semua kolom wajib diisi lengkap.',
            ], 400);
            return;
        }

        // Strict server-side validation against conflicts
        $check = $this->engine->checkEventConflict($roomId, $bookingDate, $startTime, $endTime);
        if (!$check['allowed']) {
            Flight::json([
                'success' => false,
                'message' => $check['reason'],
                'conflict_type' => $check['conflict_type'],
            ], 409);
            return;
        }

        $userId = $_SESSION['user']['id'] ?? null;

        $bookingId = EventBooking::create($this->db, [
            'room_id' => $roomId,
            'event_name' => $eventName,
            'organizer' => $organizer,
            'booking_date' => $bookingDate,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'description' => $description,
            'status' => 'pending',
            'user_id' => $userId,
        ]);

        Flight::json([
            'success' => true,
            'booking_id' => $bookingId,
            'message' => 'Permohonan peminjaman berhasil diajukan dan menunggu persetujuan admin.',
        ]);
    }
}
