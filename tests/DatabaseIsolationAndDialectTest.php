<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use PDO;

class DatabaseIsolationAndDialectTest extends TestCase {
    public function testGetDbConnectionHonorsExplicitDsn(): void {
        require_once __DIR__ . '/../app/config/config.php';
        
        // When an explicit DSN is provided, it should not be silently swapped
        // Test with custom dbname parameter
        $testDbName = 'test_jadwalkelas_isolation';
        $customDsn = "mysql:host=127.0.0.1;port=3306;dbname={$testDbName};charset=utf8mb4";
        
        // Create test db if not exists
        $adminDb = new PDO("mysql:host=127.0.0.1;port=3306", 'hzsan', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $adminDb->exec("CREATE DATABASE IF NOT EXISTS `{$testDbName}`");

        $conn = getDbConnection($customDsn, 'hzsan', '');
        $stmt = $conn->query("SELECT DATABASE()");
        $this->assertEquals($testDbName, $stmt->fetchColumn());
    }

    public function testAppDbAndTestDbAreSeparatedByDefault(): void {
        require_once __DIR__ . '/../app/config/config.php';
        
        // App environment and Test environment should use different database names
        $appDbName = getAppDatabaseName();
        $testDbName = getTestDatabaseName();

        $this->assertNotEquals($appDbName, $testDbName);
        $this->assertStringContainsString('test', $testDbName);
    }

    public function testSqliteSchemaAndSeedFilesAreValid(): void {
        $sqliteSchema = __DIR__ . '/../database/schema_sqlite.sql';
        $sqliteSeed = __DIR__ . '/../database/seed_sqlite.sql';

        $this->assertFileExists($sqliteSchema);
        $this->assertFileExists($sqliteSeed);

        $schemaContent = file_get_contents($sqliteSchema);
        $this->assertStringNotContainsString('ENGINE=InnoDB', $schemaContent);
        $this->assertStringNotContainsString('AUTO_INCREMENT', $schemaContent);

        $seedContent = file_get_contents($sqliteSeed);
        $this->assertStringNotContainsString('ON DUPLICATE KEY UPDATE', $seedContent);
    }
}
