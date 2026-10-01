<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Controllers\ApiController;
use PDO;

class CalendarIntegrationTest extends TestCase {
    private PDO $db;

    protected function setUp(): void {
        require_once __DIR__ . '/../app/config/config.php';
        $this->db = getDbConnection();
        initDatabase($this->db);
    }

    public function testCalendarEventsPayloadStructure(): void {
        $api = new ApiController($this->db);
        $res = $api->getEvents(['start' => '2026-10-01', 'end' => '2026-10-07', 'room_id' => 0]);
        $this->assertTrue($res['success']);
        $this->assertIsArray($res['data']);

        // Check view template and javascript file exist
        $this->assertFileExists(__DIR__ . '/../app/views/home/calendar.html.twig');
        $this->assertFileExists(__DIR__ . '/../public/js/calendar.js');
    }
}
