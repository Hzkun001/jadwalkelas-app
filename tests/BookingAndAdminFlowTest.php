<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Controllers\BookingController;
use App\Controllers\AdminController;
use PDO;

class BookingAndAdminFlowTest extends TestCase {
    private PDO $db;

    protected function setUp(): void {
        require_once __DIR__ . '/../app/config/config.php';
        $this->db = getDbConnection();
        initDatabase($this->db);

        $this->db->exec("DELETE FROM event_bookings");
        $this->db->exec("DELETE FROM schedules");
    }

    public function testBookingCreationAndApprovalFlow(): void {
        $bookingCtrl = new BookingController($this->db);
        $adminCtrl = new AdminController($this->db);

        // 1. Submit booking on empty slot
        $bookingResult = $bookingCtrl->processBooking([
            'room_id' => 1,
            'event_name' => 'Seminar Robotika',
            'organizer' => 'HMTI',
            'booking_date' => '2026-10-14', // Rabu
            'start_time' => '13:00',
            'end_time' => '16:00',
            'description' => 'Seminar terbuka',
            'user_id' => 3
        ]);
        $this->assertTrue($bookingResult['success']);
        $bookingId = $bookingResult['booking_id'];

        // 2. Admin approves booking
        $approveResult = $adminCtrl->processApproval($bookingId, 'approved', null, 1);
        $this->assertTrue($approveResult['success']);

        // 3. New booking at same time should now conflict
        $secondResult = $bookingCtrl->processBooking([
            'room_id' => 1,
            'event_name' => 'Rapat Lain',
            'organizer' => 'BEM',
            'booking_date' => '2026-10-14',
            'start_time' => '14:00',
            'end_time' => '17:00',
            'description' => 'Rapat',
            'user_id' => 2
        ]);
        $this->assertFalse($secondResult['success']);
        $this->assertStringContainsString('terpesan', strtolower($secondResult['message']));
    }

    public function testBookingBlockedWhenLectureExists(): void {
        // Seed lecture on Wednesday (day 3, 2026-10-14 is day 3)
        $this->db->exec("INSERT INTO schedules (room_id, course_name, class_name, lecturer_name, day_of_week, start_time, end_time)
                         VALUES (1, 'Struktur Data', 'TI-2A', 'Dosen ASD', 3, '08:00', '11:00')");

        $bookingCtrl = new BookingController($this->db);
        $result = $bookingCtrl->processBooking([
            'room_id' => 1,
            'event_name' => 'Latihan Ormawa',
            'organizer' => 'Ormawa',
            'booking_date' => '2026-10-14',
            'start_time' => '09:00',
            'end_time' => '12:00',
            'description' => 'Latihan',
            'user_id' => 3
        ]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('prioritas utama', strtolower($result['message']));
    }
}
