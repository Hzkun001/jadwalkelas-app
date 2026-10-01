<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Controllers\HomeController;
use PDO;

class HomeControllerTest extends TestCase {
    private PDO $db;

    protected function setUp(): void {
        require_once __DIR__ . '/../app/config/config.php';
        $this->db = getDbConnection();
        initDatabase($this->db);

        $this->db->exec("DELETE FROM event_bookings");
        $this->db->exec("DELETE FROM schedules");
    }

    public function testGetRoomCurrentStatusesCalculatesCorrectly(): void {
        // Seed lecture in room 1 on Monday (day 1) 08:00 - 10:00
        $this->db->exec("INSERT INTO schedules (room_id, course_name, class_name, lecturer_name, day_of_week, start_time, end_time)
                         VALUES (1, 'Fisika Dasar', 'TI-1A', 'Dosen Fisika', 1, '08:00', '10:00')");

        $controller = new HomeController($this->db);
        $statuses = $controller->getRoomStatusesForTime(1, '2026-10-05', '09:00'); // Senin, 09:00

        $this->assertCount(8, $statuses);
        // Room 1 should be occupied by lecture
        $room1 = array_values(array_filter($statuses, fn($r) => $r['id'] == 1))[0];
        $this->assertEquals('lecture', $room1['status']);
        $this->assertEquals('Fisika Dasar', $room1['current_activity']['course_name']);

        // Room 2 should be empty
        $room2 = array_values(array_filter($statuses, fn($r) => $r['id'] == 2))[0];
        $this->assertEquals('empty', $room2['status']);
    }
}
