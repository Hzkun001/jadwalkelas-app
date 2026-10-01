<?php

namespace App\Controllers;

use App\Models\Room;
use App\Models\EventBooking;
use App\Services\ConflictEngine;
use App\Services\AuthService;
use Flight;
use PDO;

class BookingController {
    private PDO $db;
    private ConflictEngine $engine;
    private AuthService $auth;

    public function __construct(PDO $db) {
        $this->db = $db;
        $this->engine = new ConflictEngine($db);
        $this->auth = new AuthService($db);
    }

    public function showCreate(): void {
        $rooms = Room::getAllActive($this->db);
        $selectedRoom = (int) (Flight::request()->query['room_id'] ?? 0);

        echo Flight::view()->render('booking/create.html.twig', [
            'active_nav' => 'booking',
            'rooms' => $rooms,
            'selected_room' => $selectedRoom,
            'user' => $this->auth->user(),
            'error' => Flight::request()->query['error'] ?? null,
            'success' => Flight::request()->query['success'] ?? null,
        ]);
    }

    public function processBooking(array $data): array {
        $roomId = (int) ($data['room_id'] ?? 0);
        $eventName = trim($data['event_name'] ?? '');
        $organizer = trim($data['organizer'] ?? '');
        $bookingDate = trim($data['booking_date'] ?? '');
        $startTime = trim($data['start_time'] ?? '');
        $endTime = trim($data['end_time'] ?? '');
        $description = trim($data['description'] ?? '');
        $userId = isset($data['user_id']) ? (int) $data['user_id'] : null;

        if (!$roomId || empty($eventName) || empty($organizer) || empty($bookingDate) || empty($startTime) || empty($endTime)) {
            return [
                'success' => false,
                'message' => 'Semua kolom yang ditandai bintang wajib diisi lengkap.',
            ];
        }

        // Strict server-side conflict & priority check
        $check = $this->engine->checkEventConflict($roomId, $bookingDate, $startTime, $endTime);
        if (!$check['allowed']) {
            return [
                'success' => false,
                'message' => $check['reason'],
                'conflict_type' => $check['conflict_type'],
            ];
        }

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

        return [
            'success' => true,
            'booking_id' => $bookingId,
            'message' => 'Permohonan peminjaman berhasil dikirim. Menunggu verifikasi persetujuan admin.',
        ];
    }

    public function store(): void {
        $data = Flight::request()->data->getData();
        if ($this->auth->check()) {
            $data['user_id'] = $this->auth->user()['id'];
        }

        $result = $this->processBooking($data);
        if ($result['success']) {
            Flight::redirect('/booking/list?success=' . urlencode($result['message']));
        } else {
            Flight::redirect('/booking/create?error=' . urlencode($result['message']));
        }
    }

    public function listBookings(): void {
        $bookings = EventBooking::getAll($this->db);
        echo Flight::view()->render('booking/index.html.twig', [
            'active_nav' => 'booking',
            'bookings' => $bookings,
            'user' => $this->auth->user(),
            'success' => Flight::request()->query['success'] ?? null,
        ]);
    }
}
