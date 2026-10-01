<?php

namespace App\Models;

use PDO;

class Room {
    public static function getAllActive(PDO $db): array {
        $stmt = $db->query("SELECT * FROM rooms WHERE is_active = 1 ORDER BY code ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getAll(PDO $db): array {
        $stmt = $db->query("SELECT * FROM rooms ORDER BY code ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getById(PDO $db, int $id): ?array {
        $stmt = $db->prepare("SELECT * FROM rooms WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function create(PDO $db, array $data): int {
        $stmt = $db->prepare("INSERT INTO rooms (code, name, building, capacity, room_type, is_active)
                              VALUES (:code, :name, :building, :capacity, :room_type, :is_active)");
        $stmt->execute([
            ':code' => $data['code'],
            ':name' => $data['name'],
            ':building' => $data['building'] ?? null,
            ':capacity' => $data['capacity'] ?? 40,
            ':room_type' => $data['room_type'] ?? 'kelas',
            ':is_active' => $data['is_active'] ?? 1,
        ]);
        return (int) $db->lastInsertId();
    }

    public static function update(PDO $db, int $id, array $data): bool {
        $stmt = $db->prepare("UPDATE rooms SET name = :name, building = :building, 
                              capacity = :capacity, room_type = :room_type, is_active = :is_active 
                              WHERE id = :id");
        return $stmt->execute([
            ':id' => $id,
            ':name' => $data['name'],
            ':building' => $data['building'] ?? null,
            ':capacity' => $data['capacity'] ?? 40,
            ':room_type' => $data['room_type'] ?? 'kelas',
            ':is_active' => $data['is_active'] ?? 1,
        ]);
    }
}
