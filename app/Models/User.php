<?php

namespace App\Models;

use PDO;

class User {
    public static function findByUsername(PDO $db, string $username): ?array {
        $stmt = $db->prepare("SELECT * FROM users WHERE username = :username");
        $stmt->execute([':username' => $username]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function getById(PDO $db, int $id): ?array {
        $stmt = $db->prepare("SELECT id, username, name, role, created_at FROM users WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function getAll(PDO $db): array {
        $stmt = $db->query("SELECT id, username, name, role, created_at FROM users ORDER BY name ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function create(PDO $db, array $data): int {
        $stmt = $db->prepare("INSERT INTO users (username, password_hash, name, role) 
                              VALUES (:username, :password_hash, :name, :role)");
        $stmt->execute([
            ':username' => $data['username'],
            ':password_hash' => password_hash($data['password'], PASSWORD_BCRYPT),
            ':name' => $data['name'],
            ':role' => $data['role'],
        ]);
        return (int) $db->lastInsertId();
    }
}
