<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use PDO;

class DatabaseSetupTest extends TestCase {
    public function testDatabaseTablesAndSeedDataExist(): void {
        require_once __DIR__ . '/../app/config/config.php';
        $db = getDbConnection(':memory:');
        initDatabase($db);

        $stmt = $db->query("SELECT COUNT(*) FROM rooms");
        $roomCount = $stmt->fetchColumn();
        $this->assertEquals(8, $roomCount);

        $stmt = $db->query("SELECT name FROM rooms WHERE code = 'fst-1.1'");
        $this->assertEquals('fst 1.1', $stmt->fetchColumn());
    }
}
