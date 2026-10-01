<?php

namespace App\Controllers;

use App\Services\AuthService;
use Flight;
use PDO;

class AuthController {
    private PDO $db;
    private AuthService $auth;

    public function __construct(PDO $db) {
        $this->db = $db;
        $this->auth = new AuthService($db);
    }

    public function showLogin(): void {
        if ($this->auth->check()) {
            $user = $this->auth->user();
            if ($user['role'] === 'admin') {
                Flight::redirect('/admin');
            } else {
                Flight::redirect('/');
            }
            return;
        }

        echo Flight::view()->render('auth/login.html.twig', [
            'error' => Flight::request()->query['error'] ?? null,
        ]);
    }

    public function login(): void {
        $data = Flight::request()->data;
        $username = trim($data['username'] ?? '');
        $password = $data['password'] ?? '';

        if ($this->auth->attempt($username, $password)) {
            $user = $this->auth->user();
            if ($user['role'] === 'admin') {
                Flight::redirect('/admin');
            } else {
                Flight::redirect('/');
            }
        } else {
            Flight::redirect('/login?error=invalid_credentials');
        }
    }

    public function logout(): void {
        $this->auth->logout();
        Flight::redirect('/');
    }
}
