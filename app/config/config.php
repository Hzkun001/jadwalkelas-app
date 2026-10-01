<?php

function getDbConnection(?string $dsn = null, ?string $user = null, ?string $pass = null): PDO {
    if ($dsn === null || $dsn === ':memory:') {
        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $port = getenv('DB_PORT') ?: '3306';
        $dbname = getenv('DB_DATABASE') ?: 'test_jadwalkelas';
        $user = $user ?? (getenv('DB_USERNAME') ?: 'hzsan');
        $pass = $pass ?? (getenv('DB_PASSWORD') ?: '');
        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
    }

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

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
