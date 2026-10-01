<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use PDO;

class DatabaseIsolationAndDialectTest extends TestCase {
    public function testGetDbConnectionUsesSqliteInMemoryForTests(): void {
        require_once __DIR__ . '/../app/config/config.php';
        
        $conn = getDbConnection();
        $this->assertEquals('sqlite', $conn->getAttribute(PDO::ATTR_DRIVER_NAME));

        initDatabase($conn);
        $stmt = $conn->query("SELECT COUNT(*) FROM rooms");
        $this->assertEquals(8, $stmt->fetchColumn());
    }

    public function testSqliteSchemaAndSeedFilesAreValid(): void {
        $schema = __DIR__ . '/../database/schema.sql';
        $seed = __DIR__ . '/../database/seed.sql';

        $this->assertFileExists($schema);
        $this->assertFileExists($seed);

        $schemaContent = file_get_contents($schema);
        $this->assertStringNotContainsString('ENGINE=InnoDB', $schemaContent);
        $this->assertStringNotContainsString('AUTO_INCREMENT', $schemaContent);

        $seedContent = file_get_contents($seed);
        $this->assertStringNotContainsString('ON DUPLICATE KEY UPDATE', $seedContent);
    }
}
