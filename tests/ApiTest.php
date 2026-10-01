<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Controllers\ApiController;
use PDO;

class ApiTest extends TestCase {
    private PDO $db;
    private ApiController $api;

    protected function setUp(): void {
        require_once __DIR__ . '/../app/config/config.php';
        $this->db = getDbConnection();
        initDatabase($this->db);

        $this->db->exec("DELETE FROM event_bookings");
        $this->db->exec("DELETE FROM schedules");

        $this->api = new ApiController($this->db);
    }

    public function testGetRoomsReturnsActiveList(): void {
        $response = $this->api->getRooms();
        $this->assertTrue($response['success']);
        $this->assertCount(8, $response['data']);
    }

    public function testCheckAvailabilityFreeSlot(): void {
        $result = $this->api->checkAvailability([
            'room_id' => 1,
            'date' => '2026-10-06',
            'start' => '13:00',
            'end' => '15:00'
        ]);
        $this->assertTrue($result['available']);
    }

    public function testCheckAvailabilityLectureConflict(): void {
        // Seed lecture on Tuesday (day 2, 2026-10-06 is Tuesday)
        $this->db->exec("INSERT INTO schedules (room_id, course_name, class_name, lecturer_name, day_of_week, start_time, end_time)
                         VALUES (1, 'Jaringan Komputer', 'TI-3B', 'Dosen Net', 2, '08:00', '10:30')");

        $result = $this->api->checkAvailability([
            'room_id' => 1,
            'date' => '2026-10-06',
            'start' => '09:00',
            'end' => '11:00'
        ]);

        $this->assertFalse($result['available']);
        $this->assertEquals('lecture_priority', $result['conflict_type']);
        $this->assertStringContainsString('Perkuliahan reguler memiliki prioritas utama', $result['reason']);
    }

    public function testGetEventsReturnsCalendarFormattedItems(): void {
        // 1. Lecture on Tuesday (2026-10-06)
        $this->db->exec("INSERT INTO schedules (room_id, course_name, class_name, lecturer_name, day_of_week, start_time, end_time)
                         VALUES (1, 'AI & Robotics', 'TI-4A', 'Dr. Robot', 2, '08:00', '10:00')");

        // 2. Approved event on Wednesday (2026-10-07)
        $this->db->exec("INSERT INTO event_bookings (room_id, event_name, organizer, booking_date, start_time, end_time, status)
                         VALUES (1, 'Workshop Web', 'HMTI', '2026-10-07', '13:00', '16:00', 'approved')");

        $response = $this->api->getEvents([
            'start' => '2026-10-05',
            'end' => '2026-10-11',
            'room_id' => 1
        ]);

        $this->assertTrue($response['success']);
        $events = $response['data'];
        $this->assertGreaterThanOrEqual(2, count($events));

        // Check lecture color
        $lectureEvent = array_values(array_filter($events, fn($e) => $e['type'] === 'lecture'))[0] ?? null;
        $this->assertNotNull($lectureEvent);
        $this->assertEquals('#2563EB', $lectureEvent['color']);

        // Check event color
        $approvedEvent = array_values(array_filter($events, fn($e) => $e['type'] === 'event'))[0] ?? null;
        $this->assertNotNull($approvedEvent);
        $this->assertEquals('#EA580C', $approvedEvent['color']);
    }
}
