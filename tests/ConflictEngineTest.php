<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Services\ConflictEngine;
use PDO;

class ConflictEngineTest extends TestCase {
    private PDO $db;
    private ConflictEngine $engine;

    protected function setUp(): void {
        require_once __DIR__ . '/../app/config/config.php';
        $this->db = getDbConnection();
        initDatabase($this->db);

        // Clean schedules and bookings before each test
        $this->db->exec("DELETE FROM event_bookings");
        $this->db->exec("DELETE FROM schedules");

        $this->engine = new ConflictEngine($this->db);
    }

    public function testAdjacentSlotsDoNotOverlap(): void {
        $this->assertFalse(ConflictEngine::hasTimeOverlap('08:00', '10:00', '10:00', '12:00'));
    }

    public function testOverlappingSlotsDetected(): void {
        $this->assertTrue(ConflictEngine::hasTimeOverlap('08:00', '10:00', '09:00', '11:00'));
        $this->assertTrue(ConflictEngine::hasTimeOverlap('08:00', '12:00', '09:00', '10:00'));
        $this->assertTrue(ConflictEngine::hasTimeOverlap('09:00', '10:00', '08:00', '12:00'));
    }

    public function testEventBlockedWhenLectureExistsOnSameDayAndRoom(): void {
        // Seed a lecture on Monday (day 1) in room 1: 08:00 - 10:00
        $this->db->exec("INSERT INTO schedules (room_id, course_name, class_name, lecturer_name, day_of_week, start_time, end_time) 
                         VALUES (1, 'Basis Data', 'TI-3A', 'Dr. Dosen', 1, '08:00', '10:00')");

        // 2026-10-05 is a Monday (day 1)
        $result = $this->engine->checkEventConflict(1, '2026-10-05', '09:00', '11:00');
        $this->assertFalse($result['allowed']);
        $this->assertEquals('lecture_priority', $result['conflict_type']);
        $this->assertStringContainsString('Perkuliahan reguler memiliki prioritas utama', $result['reason']);
    }

    public function testEventAllowedWhenNoLectureAndNoEvent(): void {
        $result = $this->engine->checkEventConflict(1, '2026-10-05', '13:00', '15:00');
        $this->assertTrue($result['allowed']);
    }

    public function testEventBlockedWhenApprovedEventExists(): void {
        $this->db->exec("INSERT INTO event_bookings (room_id, event_name, organizer, booking_date, start_time, end_time, status)
                         VALUES (1, 'Seminar AI', 'HMTI', '2026-10-05', '13:00', '15:00', 'approved')");

        $result = $this->engine->checkEventConflict(1, '2026-10-05', '14:00', '16:00');
        $this->assertFalse($result['allowed']);
        $this->assertEquals('event_conflict', $result['conflict_type']);
    }

    public function testRejectedOrCancelledEventDoesNotBlockBooking(): void {
        $this->db->exec("INSERT INTO event_bookings (room_id, event_name, organizer, booking_date, start_time, end_time, status)
                         VALUES (1, 'Rapat Batal', 'Ormawa', '2026-10-05', '15:00', '17:00', 'rejected')");

        $result = $this->engine->checkEventConflict(1, '2026-10-05', '15:00', '17:00');
        $this->assertTrue($result['allowed']);
    }

    public function testLectureConflictDetection(): void {
        $this->db->exec("INSERT INTO schedules (room_id, course_name, class_name, lecturer_name, day_of_week, start_time, end_time) 
                         VALUES (1, 'Kalkulus', 'TI-1A', 'Dosen A', 1, '08:00', '10:00')");

        $conflict = $this->engine->checkLectureConflict(1, 1, '09:00', '11:00');
        $this->assertNotNull($conflict);
        $this->assertEquals('Kalkulus', $conflict['course_name']);

        $noConflict = $this->engine->checkLectureConflict(1, 1, '10:00', '12:00');
        $this->assertNull($noConflict);
    }
}
