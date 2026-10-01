<?php

namespace App\Services;

use App\Models\User;
use PDO;

class AuthService {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function attempt(string $username, string $password): bool {
        $user = User::findByUsername($this->db, $username);
        if (!$user) {
            return false;
        }

        if (password_verify($password, $user['password_hash'])) {
            unset($user['password_hash']);
            $_SESSION['user'] = $user;
            return true;
        }

        return false;
    }

    public function user(): ?array {
        return $_SESSION['user'] ?? null;
    }

    public function check(): bool {
        return isset($_SESSION['user']);
    }

    public function hasRole(string|array $roles): bool {
        $user = $this->user();
        if (!$user) {
            return false;
        }

        if (is_array($roles)) {
            return in_array($user['role'], $roles, true);
        }

        return $user['role'] === $roles;
    }

    public function logout(): void {
        unset($_SESSION['user']);
    }
}
