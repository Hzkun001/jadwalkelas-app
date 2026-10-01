<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\EventBooking;
use App\Models\User;
use PDO;

class ModelsTest extends TestCase {
    private PDO $db;

    protected function setUp(): void {
        require_once __DIR__ . '/../app/config/config.php';
        $this->db = getDbConnection();
        initDatabase($this->db);

        // Reset dynamic data
        $this->db->exec("DELETE FROM event_bookings");
        $this->db->exec("DELETE FROM schedules");
    }

    public function testRoomModelReturnsActiveRooms(): void {
        $rooms = Room::getAllActive($this->db);
        $this->assertCount(8, $rooms);
        $first = Room::getById($this->db, $rooms[0]['id']);
        $this->assertNotNull($first);
        $this->assertEquals($rooms[0]['code'], $first['code']);
    }

    public function testScheduleCreationAndRetrieval(): void {
        $id = Schedule::create($this->db, [
            'room_id' => 1,
            'course_name' => 'Pemrograman Web',
            'class_name' => 'TI-3A',
            'lecturer_name' => 'Budi Santoso',
            'day_of_week' => 2,
            'start_time' => '10:00',
            'end_time' => '12:30',
            'academic_period' => '2026/2027 Ganjil',
            'created_by' => 1
        ]);
        $this->assertGreaterThan(0, $id);

        $schedules = Schedule::getByRoomAndDay($this->db, 1, 2);
        $this->assertCount(1, $schedules);
        $this->assertEquals('Pemrograman Web', $schedules[0]['course_name']);
    }

    public function testBookingStatusUpdate(): void {
        $id = EventBooking::create($this->db, [
            'room_id' => 1,
            'event_name' => 'Lomba Coding',
            'organizer' => 'HMTI',
            'booking_date' => '2026-10-20',
            'start_time' => '13:00',
            'end_time' => '16:00',
            'description' => 'Lomba tahunan',
            'user_id' => 3
        ]);

        $updated = EventBooking::updateStatus($this->db, $id, 'approved', null, 1);
        $this->assertTrue($updated);

        $booking = EventBooking::getById($this->db, $id);
        $this->assertEquals('approved', $booking['status']);
    }

    public function testUserFindByUsername(): void {
        $user = User::findByUsername($this->db, 'admin');
        $this->assertNotNull($user);
        $this->assertEquals('admin', $user['role']);
        $this->assertTrue(password_verify('admin123', $user['password_hash']));

        $nonExistent = User::findByUsername($this->db, 'nobody');
        $this->assertNull($nonExistent);
    }
}
