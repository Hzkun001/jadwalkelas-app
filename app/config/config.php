<?php

function getDbConnection(?string $dsn = null, ?string $user = null, ?string $pass = null, bool $isTest = false): PDO {
    $sqliteDbFile = __DIR__ . '/../../database/app.sqlite';

    // 1. Determine DSN
    if ($dsn === null) {
        $isTestEnv = $isTest || defined('PHPUNIT_RUNNING');
        if ($isTestEnv) {
            $dsn = 'sqlite::memory:';
        } else {
            $dsn = 'sqlite:' . $sqliteDbFile;
        }
    }

    // 2. Check if SQLite driver is available
    if (strpos($dsn, 'sqlite') === 0 && !in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        // Check if bundled local ext/pdo_sqlite.so exists
        $localExt = __DIR__ . '/../../ext/pdo_sqlite.so';
        $helpMsg = "Driver PDO SQLite belum aktif di PHP.\n"
                 . "Silakan jalankan: sudo pacman -S php-sqlite\n"
                 . "Atau jalankan server dengan: php -d extension=" . realpath($localExt) . " -S localhost:8000 -t public";
        throw new RuntimeException($helpMsg);
    }

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    if (strpos($dsn, 'sqlite') === 0) {
        $pdo->exec("PRAGMA foreign_keys = ON;");
    }

    return $pdo;
}

function initDatabase(PDO $db): void {
    $schemaFile = __DIR__ . '/../../database/schema.sql';
    $seedFile = __DIR__ . '/../../database/seed.sql';

    if (file_exists($schemaFile)) {
        $sql = file_get_contents($schemaFile);
        $db->exec($sql);
    }

    if (file_exists($seedFile)) {
        $sql = file_get_contents($seedFile);
        $db->exec($sql);
    }
}
