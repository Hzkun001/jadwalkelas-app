<?php

function getAppDatabaseName(): string {
    return getenv('DB_DATABASE') ?: 'test_jadwalkelas';
}

function getTestDatabaseName(): string {
    return getenv('DB_TEST_DATABASE') ?: 'test_jadwalkelas_test';
}

function getDbConnection(?string $dsn = null, ?string $user = null, ?string $pass = null, bool $isTest = false): PDO {
    // If an explicit DSN is provided, use it directly (e.g. custom DSN or sqlite::memory:)
    if ($dsn !== null && $dsn !== ':memory:') {
        return new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    if ($dsn === ':memory:') {
        // If sqlite extension is available, allow pure in-memory SQLite
        if (in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            $pdo = new PDO('sqlite::memory:', null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $pdo->exec("PRAGMA foreign_keys = ON;");
            return $pdo;
        }
    }

    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('DB_PORT') ?: '3306';
    $user = $user ?? (getenv('DB_USERNAME') ?: 'hzsan');
    $pass = $pass ?? (getenv('DB_PASSWORD') ?: '');

    // Isolate test database from development/production database
    $isTestEnv = $isTest || defined('PHPUNIT_RUNNING');
    $dbname = $isTestEnv ? getTestDatabaseName() : getAppDatabaseName();

    // Ensure database exists if MySQL
    try {
        $serverConn = new PDO("mysql:host={$host};port={$port}", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $serverConn->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}`");
    } catch (\Throwable $e) {
        // Fallback if user doesn't have create database rights
    }

    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";

    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function initDatabase(PDO $db): void {
    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);

    if ($driver === 'sqlite') {
        $schemaFile = __DIR__ . '/../../database/schema_sqlite.sql';
        $seedFile = __DIR__ . '/../../database/seed_sqlite.sql';
    } else {
        $schemaFile = __DIR__ . '/../../database/schema.sql';
        $seedFile = __DIR__ . '/../../database/seed.sql';
    }

    if (file_exists($schemaFile)) {
        $sql = file_get_contents($schemaFile);
        $db->exec($sql);
    }

    if (file_exists($seedFile)) {
        $sql = file_get_contents($seedFile);
        $db->exec($sql);
    }
}
