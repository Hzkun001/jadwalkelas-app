<?php

namespace App\Controllers;

use App\Models\Room;
use App\Models\Schedule;
use App\Models\EventBooking;
use App\Services\AuthService;
use Flight;
use PDO;

class AdminController {
    private PDO $db;
    private AuthService $auth;

    public function __construct(PDO $db) {
        $this->db = $db;
        $this->auth = new AuthService($db);
    }

    private function ensureAdmin(): void {
        if (!$this->auth->check() || !$this->auth->hasRole('admin')) {
            Flight::redirect('/login?error=unauthorized');
            exit;
        }
    }

    public function dashboard(): void {
        $this->ensureAdmin();

        $pending = EventBooking::getPending($this->db);
        $allBookings = EventBooking::getAll($this->db);
        $rooms = Room::getAll($this->db);

        echo Flight::view()->render('admin/dashboard.html.twig', [
            'active_nav' => 'admin',
            'pending' => $pending,
            'recent' => array_slice($allBookings, 0, 10),
            'rooms' => $rooms,
            'user' => $this->auth->user(),
            'success' => Flight::request()->query['success'] ?? null,
            'error' => Flight::request()->query['error'] ?? null,
        ]);
    }

    public function processApproval(int $bookingId, string $status, ?string $reason = null, ?int $adminId = null): array {
        $booking = EventBooking::getById($this->db, $bookingId);
        if (!$booking) {
            return ['success' => false, 'message' => 'Permohonan peminjaman tidak ditemukan.'];
        }

        $ok = EventBooking::updateStatus($this->db, $bookingId, $status, $reason, $adminId);
        return [
            'success' => $ok,
            'message' => $ok ? "Permohonan berhasil di-{$status}." : 'Gagal memperbarui status permohonan.',
        ];
    }

    public function approve(int $id): void {
        $this->ensureAdmin();
        $adminId = $this->auth->user()['id'] ?? null;
        $res = $this->processApproval($id, 'approved', null, $adminId);
        Flight::redirect('/admin?success=' . urlencode($res['message']));
    }

    public function reject(int $id): void {
        $this->ensureAdmin();
        $adminId = $this->auth->user()['id'] ?? null;
        $reason = trim(Flight::request()->data['rejection_reason'] ?? 'Ditolak oleh admin.');
        $res = $this->processApproval($id, 'rejected', $reason, $adminId);
        Flight::redirect('/admin?success=' . urlencode($res['message']));
    }

    public function rooms(): void {
        $this->ensureAdmin();
        $rooms = Room::getAll($this->db);
        echo Flight::view()->render('admin/rooms.html.twig', [
            'active_nav' => 'admin_rooms',
            'rooms' => $rooms,
            'user' => $this->auth->user(),
            'success' => Flight::request()->query['success'] ?? null,
        ]);
    }
}
