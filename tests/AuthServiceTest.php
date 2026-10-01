<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Services\AuthService;
use PDO;

class AuthServiceTest extends TestCase {
    private PDO $db;
    private AuthService $auth;

    protected function setUp(): void {
        require_once __DIR__ . '/../app/config/config.php';
        $this->db = getDbConnection();
        initDatabase($this->db);

        $_SESSION = [];
        $this->auth = new AuthService($this->db);
    }

    public function testLoginWithValidCredentials(): void {
        $this->assertTrue($this->auth->attempt('admin', 'admin123'));
        $this->assertNotNull($this->auth->user());
        $this->assertEquals('admin', $this->auth->user()['role']);
        $this->assertTrue($this->auth->hasRole('admin'));
        $this->assertFalse($this->auth->hasRole('komti'));
        $this->assertTrue($this->auth->hasRole(['admin', 'komti']));
    }

    public function testLoginWithInvalidPasswordFails(): void {
        $this->assertFalse($this->auth->attempt('admin', 'wrongpass'));
        $this->assertNull($this->auth->user());
    }

    public function testLogoutClearsSession(): void {
        $this->auth->attempt('admin', 'admin123');
        $this->assertNotNull($this->auth->user());

        $this->auth->logout();
        $this->assertNull($this->auth->user());
    }
}
