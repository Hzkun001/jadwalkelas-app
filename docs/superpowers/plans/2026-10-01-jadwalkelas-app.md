# Jadwal Kelas App Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Membangun aplikasi web pemantau ruangan kelas lokal FST (`jadwalkelas-app`) berbasis FlightPHP 3, Twig, SQLite, dan Tailwind CSS dengan tampilan kalender interaktif, deteksi jadwal bentrok otomatis, dan aturan hierarki prioritas perkuliahan di atas kegiatan acara.

**Architecture:** Arsitektur fullstack monolith berbobot ultra-ringan menggunakan FlightPHP 3 (MVC pattern: Controllers, Models, Twig Views). Conflict Engine terpusat memproses validasi bentrok waktu ($S_1 < E_2 \land E_1 > S_2$) serta aturan prioritas (Kuliah Reguler > Acara Ormawa). Data disajikan via server-side rendering untuk performa instan dan endpoint JSON untuk menyuplai kalender interaktif dan pengecekan AJAX real-time.

**Tech Stack:** PHP 8.5+, FlightPHP 3 (`flightphp/core`), Twig 3 (`twig/twig`), SQLite 3 (PDO), Tailwind CSS, Vanilla JS / Fetch API, PHPUnit 9.6+.

**Spec:** `docs/superpowers/specs/2026-10-01-jadwalkelas-app-design.md`

## Global Constraints

- PHP runtime requirement: `php >= 8.0`
- Zero background daemons (Node.js/Bun tidak dijalankan di background production; server dijalankan murni PHP built-in / Apache / Nginx)
- Database: SQLite file tunggal di `database/app.sqlite` dengan foreign keys diaktifkan (`PRAGMA foreign_keys = ON;`)
- Pre-seeded rooms wajib mencakup 8 ruangan FST: `fst 1.1`, `fst 1.2`, `fst 3.4`, `fst 3.5`, `fst 3.6`, `LAB-INT-internet-1`, `LAB-INT-internet-2`, `LAB-INT-internet-3`
- Aturan prioritas: Perkuliahan reguler selalu memblokir peminjaman acara jika beririsan jam dan ruangan yang sama
- Server-side double validation mutlak diterapkan pada seluruh submission POST

## Review Focus

1. **Jadwal berurutan tepat di batas waktu (Adjacent Time Slots)**: Misal slot 1 berakhir pukul `10:00` dan slot 2 mulai tepat pukul `10:00`. Sistem harus menganggap ini **TIDAK BENTROK** (karena $10:00 \nless 10:00$).
2. **Peminjaman acara yang melewati jam kuliah sebagian (Partial Overlap)**: Misal kuliah `08:00 - 10:00`, permohonan acara `09:30 - 11:30`. Sistem harus mendeteksi bentrok dan menolak permohonan.
3. **Peminjaman acara yang melingkupi seluruh jam kuliah (Superset Overlap)**: Misal kuliah `09:00 - 11:00`, permohonan acara `07:00 - 13:00`. Sistem harus mendeteksi bentrok.
4. **Acara yang berstatus `rejected` atau `cancelled`**: Acara yang ditolak/dibatalkan tidak boleh menghalangi permohonan baru pada slot yang sama.
5. **Konversi hari kalender ke `day_of_week`**: Memastikan pemetaan tanggal (misal `2026-10-15`) ke hari dalam sistem (`1=Senin` s.d. `6=Sabtu`) konsisten di server dan client.

---

### Task 1: Scaffolding, Composer & Database SQLite

**Files:**
- Create: `composer.json`
- Create: `database/schema.sql`
- Create: `database/seed.sql`
- Create: `app/config/config.php`
- Create: `app/config/services.php`
- Create: `public/index.php`
- Test: `tests/DatabaseSetupTest.php`

**Interfaces:**
- Produces: `Flight::db(): PDO` mengembalikan koneksi SQLite aktif dengan schema dan seed data yang valid.

- [ ] **Step 1: Write failing test for database setup and tables**

```php
// tests/DatabaseSetupTest.php
namespace Tests;

use PHPUnit\Framework\TestCase;
use PDO;

class DatabaseSetupTest extends TestCase {
    public function testDatabaseTablesAndSeedDataExist(): void {
        require_once __DIR__ . '/../app/config/config.php';
        $db = getDbConnection(':memory:');
        initDatabase($db);

        $stmt = $db->query("SELECT COUNT(*) FROM rooms");
        $roomCount = $stmt->fetchColumn();
        $this->assertEquals(8, $roomCount);

        $stmt = $db->query("SELECT name FROM rooms WHERE code = 'fst-1.1'");
        $this->assertEquals('fst 1.1', $stmt->fetchColumn());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/DatabaseSetupTest.php`
Expected: FAIL (file not found or class not loaded)

- [ ] **Step 3: Implement composer.json, schema.sql, seed.sql, and config.php**

1. Buat `composer.json` dengan dependensi: `flightphp/core: ^3.18`, `twig/twig: ^3.0`, dev `phpunit/phpunit: ^9.6`. Jalankan `composer install`.
2. Buat `database/schema.sql` mendefinisikan tabel `rooms`, `schedules`, `event_bookings`, `users`.
3. Buat `database/seed.sql` mengisi 8 ruangan FST dan akun default (`admin`, `komti`, `ormawa`).
4. Buat `app/config/config.php` dengan fungsi `getDbConnection($path)` dan `initDatabase(PDO $db)`.
5. Buat `app/config/services.php` mendaftarkan PDO dan Twig ke Flight Engine.
6. Buat `public/index.php` sebagai entry point dasar.

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/DatabaseSetupTest.php`
Expected: PASS (8 rooms seeded)

- [ ] **Step 5: Commit**

```bash
git add composer.json database/ app/ public/ tests/DatabaseSetupTest.php
git commit -m "feat: scaffold FlightPHP skeleton and SQLite database with 8 FST rooms"
```

---

### Task 2: Conflict Detection Engine (Core Logic & Priority Rules)

**Files:**
- Create: `app/services/ConflictEngine.php`
- Test: `tests/ConflictEngineTest.php`

**Interfaces:**
- Consumes: `PDO`
- Produces: 
  - `ConflictEngine::hasTimeOverlap(string $start1, string $end1, string $start2, string $end2): bool`
  - `ConflictEngine::checkLectureConflict(int $roomId, int $dayOfWeek, string $start, string $end, ?int $ignoreId = null): ?array`
  - `ConflictEngine::checkEventConflict(int $roomId, string $date, string $start, string $end, ?int $ignoreId = null): array` (returns `['allowed' => bool, 'reason' => ?string, 'conflict_type' => ?string, 'details' => ?array]`)

- [ ] **Step 1: Write failing tests covering all conflict and priority scenarios**

```php
// tests/ConflictEngineTest.php
namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Services\ConflictEngine;
use PDO;

class ConflictEngineTest extends TestCase {
    private PDO $db;
    private ConflictEngine $engine;

    protected function setUp(): void {
        require_once __DIR__ . '/../app/config/config.php';
        $this->db = getDbConnection(':memory:');
        initDatabase($this->db);
        $this->engine = new ConflictEngine($this->db);
    }

    public function testAdjacentSlotsDoNotOverlap(): void {
        $this->assertFalse(ConflictEngine::hasTimeOverlap('08:00', '10:00', '10:00', '12:00'));
    }

    public function testOverlappingSlotsDetected(): void {
        $this->assertTrue(ConflictEngine::hasTimeOverlap('08:00', '10:00', '09:00', '11:00'));
        $this->assertTrue(ConflictEngine::hasTimeOverlap('08:00', '12:00', '09:00', '10:00'));
    }

    public function testEventBlockedWhenLectureExistsOnSameDayAndRoom(): void {
        // Seed a lecture on Monday (day 1) in room 1: 08:00 - 10:00
        $this->db->exec("INSERT INTO schedules (room_id, course_name, class_name, lecturer_name, day_of_week, start_time, end_time) 
                         VALUES (1, 'Basis Data', 'TI-3A', 'Dr. Dosen', 1, '08:00', '10:00')");

        // 2026-10-05 is a Monday (day 1)
        $result = $this->engine->checkEventConflict(1, '2026-10-05', '09:00', '11:00');
        $this->assertFalse($result['allowed']);
        $this->assertEquals('lecture_priority', $result['conflict_type']);
        $this->assertStringContainsString('Perkuliahan reguler memiliki prioritas utama', $result['reason']);
    }

    public function testEventAllowedWhenNoLectureAndNoEvent(): void {
        $result = $this->engine->checkEventConflict(1, '2026-10-05', '13:00', '15:00');
        $this->assertTrue($result['allowed']);
    }

    public function testEventBlockedWhenApprovedEventExists(): void {
        $this->db->exec("INSERT INTO event_bookings (room_id, event_name, organizer, booking_date, start_time, end_time, status)
                         VALUES (1, 'Seminar AI', 'HMTI', '2026-10-05', '13:00', '15:00', 'approved')");

        $result = $this->engine->checkEventConflict(1, '2026-10-05', '14:00', '16:00');
        $this->assertFalse($result['allowed']);
        $this->assertEquals('event_conflict', $result['conflict_type']);
    }

    public function testRejectedOrCancelledEventDoesNotBlockBooking(): void {
        $this->db->exec("INSERT INTO event_bookings (room_id, event_name, organizer, booking_date, start_time, end_time, status)
                         VALUES (1, 'Rapat Batal', 'Ormawa', '2026-10-05', '15:00', '17:00', 'rejected')");

        $result = $this->engine->checkEventConflict(1, '2026-10-05', '15:00', '17:00');
        $this->assertTrue($result['allowed']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/ConflictEngineTest.php`
Expected: FAIL (Class `App\Services\ConflictEngine` not found)

- [ ] **Step 3: Implement `ConflictEngine` in `app/services/ConflictEngine.php`**

Implementasikan logika:
1. `hasTimeOverlap(s1, e1, s2, e2)`: `$s1 < $e2 && $e1 > $s2`.
2. `checkLectureConflict($roomId, $dayOfWeek, $start, $end, $ignoreId)`: query `schedules` untuk ruangan dan hari yang sama.
3. `checkEventConflict($roomId, $date, $start, $end, $ignoreId)`:
   - Hitung `day_of_week` dari `$date` (`date('N', strtotime($date))`).
   - Cek kuliah rutin terlebih dahulu. Jika bentrok, kembalikan `['allowed' => false, 'conflict_type' => 'lecture_priority', 'reason' => ...]`.
   - Cek `event_bookings` dengan status `'approved'`. Jika bentrok, kembalikan `['allowed' => false, 'conflict_type' => 'event_conflict', 'reason' => ...]`.
   - Jika aman, kembalikan `['allowed' => true]`.

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/ConflictEngineTest.php`
Expected: PASS (6 tests, all green)

- [ ] **Step 5: Commit**

```bash
git add app/services/ConflictEngine.php tests/ConflictEngineTest.php
git commit -m "feat: implement ConflictEngine with time overlap and lecture priority rules"
```

---

### Task 3: Models & Data Access (Room, Schedule, EventBooking, User)

**Files:**
- Create: `app/models/Room.php`
- Create: `app/models/Schedule.php`
- Create: `app/models/EventBooking.php`
- Create: `app/models/User.php`
- Test: `tests/ModelsTest.php`

**Interfaces:**
- Produces:
  - `Room::getAllActive(PDO $db): array`
  - `Room::getById(PDO $db, int $id): ?array`
  - `Schedule::getByRoomAndDay(PDO $db, int $roomId, int $day): array`
  - `Schedule::create(PDO $db, array $data): int`
  - `EventBooking::create(PDO $db, array $data): int`
  - `EventBooking::updateStatus(PDO $db, int $id, string $status, ?string $reason, ?int $approvedBy): bool`
  - `User::findByUsername(PDO $db, string $username): ?array`

- [ ] **Step 1: Write failing test for Models**

```php
// tests/ModelsTest.php
namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\EventBooking;
use App\Models\User;
use PDO;

class ModelsTest extends TestCase {
    private PDO $db;

    protected function setUp(): void {
        require_once __DIR__ . '/../app/config/config.php';
        $this->db = getDbConnection(':memory:');
        initDatabase($this->db);
    }

    public function testRoomModelReturnsActiveRooms(): void {
        $rooms = Room::getAllActive($this->db);
        $this->assertCount(8, $rooms);
    }

    public function testScheduleCreationAndRetrieval(): void {
        $id = Schedule::create($this->db, [
            'room_id' => 1,
            'course_name' => 'Pemrograman Web',
            'class_name' => 'TI-3A',
            'lecturer_name' => 'Budi Santoso',
            'day_of_week' => 2,
            'start_time' => '10:00',
            'end_time' => '12:30',
            'academic_period' => '2026/2027 Ganjil',
            'created_by' => 1
        ]);
        $this->assertGreaterThan(0, $id);

        $schedules = Schedule::getByRoomAndDay($this->db, 1, 2);
        $this->assertCount(1, $schedules);
        $this->assertEquals('Pemrograman Web', $schedules[0]['course_name']);
    }

    public function testBookingStatusUpdate(): void {
        $id = EventBooking::create($this->db, [
            'room_id' => 1,
            'event_name' => 'Lomba Coding',
            'organizer' => 'HMTI',
            'booking_date' => '2026-10-20',
            'start_time' => '13:00',
            'end_time' => '16:00',
            'description' => 'Lomba tahunan',
            'user_id' => 3
        ]);

        $updated = EventBooking::updateStatus($this->db, $id, 'approved', null, 1);
        $this->assertTrue($updated);

        $booking = EventBooking::getById($this->db, $id);
        $this->assertEquals('approved', $booking['status']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/ModelsTest.php`
Expected: FAIL

- [ ] **Step 3: Implement Room, Schedule, EventBooking, User models**

Tulis implementasi query PDO prepared statements di `app/models/Room.php`, `Schedule.php`, `EventBooking.php`, dan `User.php`.

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/ModelsTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/models/ tests/ModelsTest.php
git commit -m "feat: implement data models for Room, Schedule, EventBooking, User"
```

---

### Task 4: Authentication Service & Session Control

**Files:**
- Create: `app/services/AuthService.php`
- Create: `app/controllers/AuthController.php`
- Create: `app/views/auth/login.html.twig`
- Test: `tests/AuthServiceTest.php`

**Interfaces:**
- Produces:
  - `AuthService::attempt(string $username, string $password): bool`
  - `AuthService::user(): ?array`
  - `AuthService::hasRole(string|array $roles): bool`
  - `AuthService::logout(): void`

- [ ] **Step 1: Write failing test for AuthService**

```php
// tests/AuthServiceTest.php
namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Services\AuthService;
use PDO;

class AuthServiceTest extends TestCase {
    private PDO $db;
    private AuthService $auth;

    protected function setUp(): void {
        require_once __DIR__ . '/../app/config/config.php';
        $this->db = getDbConnection(':memory:');
        initDatabase($this->db);
        $this->auth = new AuthService($this->db);
    }

    public function testLoginWithValidCredentials(): void {
        $this->assertTrue($this->auth->attempt('admin', 'admin123'));
        $this->assertEquals('admin', $this->auth->user()['role']);
    }

    public function testLoginWithInvalidPasswordFails(): void {
        $this->assertFalse($this->auth->attempt('admin', 'wrongpass'));
        $this->assertNull($this->auth->user());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/AuthServiceTest.php`
Expected: FAIL

- [ ] **Step 3: Implement AuthService and AuthController**

1. Buat `AuthService.php` mengelola verifikasi password hash (`password_verify`) dan `$_SESSION['user']`.
2. Buat `AuthController.php` menangani `showLogin()`, `login()`, `logout()`.
3. Buat template login minimalis nan elegan di `app/views/auth/login.html.twig`.

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/AuthServiceTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/services/AuthService.php app/controllers/AuthController.php app/views/auth/ tests/AuthServiceTest.php
git commit -m "feat: implement authentication service and login controller"
```

---

### Task 5: JSON API Endpoints (Availability, Calendar Events, Booking)

**Files:**
- Create: `app/controllers/ApiController.php`
- Modify: `app/config/routes.php`
- Test: `tests/ApiTest.php`

**Interfaces:**
- Produces Endpoints:
  - `GET /api/rooms` -> `[ { id, code, name, capacity, building }, ... ]`
  - `GET /api/check-availability?room_id=..&date=..&start=..&end=..` -> `{ available: bool, reason: ?string, conflict_type: ?string, details: ?array }`
  - `GET /api/events?room_id=..&start=..&end=..` -> `[ { id, title, start, end, type: 'lecture'|'event', room_name, class_name, lecturer_name, organizer, color }, ... ]`
  - `POST /api/bookings` -> JSON response hasil validasi dan booking ID

- [ ] **Step 1: Write failing test for API endpoints**

```php
// tests/ApiTest.php
namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Controllers\ApiController;
use PDO;

class ApiTest extends TestCase {
    private PDO $db;
    private ApiController $api;

    protected function setUp(): void {
        require_once __DIR__ . '/../app/config/config.php';
        $this->db = getDbConnection(':memory:');
        initDatabase($this->db);
        $this->api = new ApiController($this->db);
    }

    public function testGetRoomsReturnsActiveList(): void {
        $response = $this->api->getRooms();
        $this->assertTrue($response['success']);
        $this->assertCount(8, $response['data']);
    }

    public function testCheckAvailabilityFreeSlot(): void {
        $result = $this->api->checkAvailability([
            'room_id' => 1,
            'date' => '2026-10-06',
            'start' => '13:00',
            'end' => '15:00'
        ]);
        $this->assertTrue($result['available']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/ApiTest.php`
Expected: FAIL

- [ ] **Step 3: Implement ApiController and map routes in routes.php**

1. Tulis `ApiController.php` dengan method `getRooms()`, `checkAvailability()`, `getEvents()`, `createBooking()`.
2. Format output `getEvents()` mengonversi jadwal kuliah mingguan ke rentang tanggal kalender yang diminta dengan warna khusus (Kuliah: `#2563EB` / Biru, Acara: `#EA580C` / Oranye).
3. Daftarkan routes di `app/config/routes.php`.

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/ApiTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/controllers/ApiController.php app/config/routes.php tests/ApiTest.php
git commit -m "feat: implement JSON API for rooms, availability check, and calendar events"
```

---

### Task 6: Public Views (Live Room Occupancy & Layout)

**Files:**
- Create: `app/views/layouts/app.html.twig`
- Create: `app/controllers/HomeController.php`
- Create: `app/views/home/index.html.twig`
- Test: `tests/HomeControllerTest.php`

**Interfaces:**
- Produces:
  - Route `/` render halaman beranda dengan kartu status live 8 ruangan FST (status saat ini: KOSONG, KULIAH, atau ACARA).

- [ ] **Step 1: Write failing test for HomeController status calculation**

```php
// tests/HomeControllerTest.php
namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Controllers\HomeController;
use PDO;

class HomeControllerTest extends TestCase {
    public function testGetRoomCurrentStatusesCalculatesCorrectly(): void {
        require_once __DIR__ . '/../app/config/config.php';
        $db = getDbConnection(':memory:');
        initDatabase($db);

        $controller = new HomeController($db);
        $statuses = $controller->getRoomStatusesForTime(1, '09:00:00'); // Senin, 09:00
        $this->assertArrayHasKey('status', $statuses[0]); // 'empty' or 'lecture' or 'event'
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/HomeControllerTest.php`
Expected: FAIL

- [ ] **Step 3: Implement layout, HomeController, and index view**

1. Buat `app/views/layouts/app.html.twig` dengan Tailwind CSS via CDN (atau standalone css), navigasi responsif, mobile menu, flash messages.
2. Buat `HomeController.php` yang menghitung status ruangan saat ini berdasarkan hari dan jam lokal.
3. Buat `app/views/home/index.html.twig` menampilkan kartu ringkas untuk masing-masing dari 8 ruangan FST dengan badge warna indikator ketersediaan.

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/HomeControllerTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/views/layouts/ app/controllers/HomeController.php app/views/home/index.html.twig tests/HomeControllerTest.php
git commit -m "feat: implement public live room occupancy dashboard"
```

---

### Task 7: Interactive Calendar View (Weekly & Monthly Timetable)

**Files:**
- Create: `app/views/home/calendar.html.twig`
- Create: `public/js/calendar.js`
- Test: `tests/CalendarIntegrationTest.php`

**Interfaces:**
- Produces:
  - Route `/calendar` menyajikan antarmuka kalender.
  - Script `calendar.js` merender jadwal mingguan & bulanan, filter tab ruangan, dan modal pop-up detail kegiatan saat blok diklik.

- [ ] **Step 1: Write failing test for Calendar View route and event feed contract**

```php
// tests/CalendarIntegrationTest.php
namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Controllers\HomeController;
use App\Controllers\ApiController;
use PDO;

class CalendarIntegrationTest extends TestCase {
    private PDO $db;

    protected function setUp(): void {
        require_once __DIR__ . '/../app/config/config.php';
        $this->db = getDbConnection(':memory:');
        initDatabase($this->db);
    }

    public function testCalendarEventsPayloadStructure(): void {
        $api = new ApiController($this->db);
        $events = $api->getEventsData(['start' => '2026-10-01', 'end' => '2026-10-07', 'room_id' => 0]);
        $this->assertIsArray($events);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/CalendarIntegrationTest.php`
Expected: FAIL

- [ ] **Step 3: Implement calendar view and calendar.js**

1. Buat `app/views/home/calendar.html.twig` dengan tab filter ruangan (`Semua Ruangan`, `fst 1.1`, ..., `LAB-INT-3`), toggle mode (Mingguan / Bulanan), navigasi minggu/bulan sebelumnya & selanjutnya.
2. Buat `public/js/calendar.js`:
   - Fetch event dari `/api/events?room_id=...&start=...&end=...`.
   - Render grid waktu (Senin s.d. Sabtu, 07:00 - 18:00).
   - Render blok warna dengan tooltip/modal detail jika diklik.

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/CalendarIntegrationTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/views/home/calendar.html.twig public/js/calendar.js tests/CalendarIntegrationTest.php
git commit -m "feat: implement interactive calendar view with weekly and monthly timetable"
```

---

### Task 8: Booking Flow, Conflict Form Checker & Admin Approval Panel

**Files:**
- Create: `app/controllers/BookingController.php`
- Create: `app/controllers/AdminController.php`
- Create: `app/views/booking/create.html.twig`
- Create: `app/views/booking/index.html.twig`
- Create: `app/views/admin/dashboard.html.twig`
- Create: `app/views/admin/rooms.html.twig`
- Create: `public/js/conflict-check.js`
- Test: `tests/BookingAndAdminFlowTest.php`

**Interfaces:**
- Produces:
  - Form `/booking/create` dengan real-time conflict checking via `public/js/conflict-check.js`.
  - Endpoint `/booking/store` dengan server-side validation ketat.
  - Dashboard Admin di `/admin` untuk verifikasi persetujuan (Approve/Reject) peminjaman acara dan kelola master ruangan.

- [ ] **Step 1: Write failing test for complete booking submission and admin approval flow**

```php
// tests/BookingAndAdminFlowTest.php
namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Controllers\BookingController;
use App\Controllers\AdminController;
use PDO;

class BookingAndAdminFlowTest extends TestCase {
    private PDO $db;

    protected function setUp(): void {
        require_once __DIR__ . '/../app/config/config.php';
        $this->db = getDbConnection(':memory:');
        initDatabase($this->db);
    }

    public function testBookingCreationAndApprovalFlow(): void {
        $bookingCtrl = new BookingController($this->db);
        $adminCtrl = new AdminController($this->db);

        // 1. Submit booking on empty slot
        $bookingResult = $bookingCtrl->processBooking([
            'room_id' => 1,
            'event_name' => 'Seminar Robotika',
            'organizer' => 'HMTI',
            'booking_date' => '2026-10-14', // Rabu
            'start_time' => '13:00',
            'end_time' => '16:00',
            'description' => 'Seminar terbuka',
            'user_id' => 3
        ]);
        $this->assertTrue($bookingResult['success']);
        $bookingId = $bookingResult['booking_id'];

        // 2. Admin approves booking
        $approveResult = $adminCtrl->processApproval($bookingId, 'approved', null, 1);
        $this->assertTrue($approveResult['success']);

        // 3. New booking at same time should now conflict
        $secondResult = $bookingCtrl->processBooking([
            'room_id' => 1,
            'event_name' => 'Rapat Lain',
            'organizer' => 'BEM',
            'booking_date' => '2026-10-14',
            'start_time' => '14:00',
            'end_time' => '17:00',
            'description' => 'Rapat',
            'user_id' => 2
        ]);
        $this->assertFalse($secondResult['success']);
        $this->assertStringContainsString('bentrok', strtolower($secondResult['message']));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/BookingAndAdminFlowTest.php`
Expected: FAIL

- [ ] **Step 3: Implement BookingController, AdminController, Views, and JS Checker**

1. Buat `BookingController.php`: validasi input, panggil `ConflictEngine`, simpan booking dengan status `pending`.
2. Buat `AdminController.php`: list pending bookings, approve, reject dengan pesan alasan, manajemen ruangan.
3. Buat `app/views/booking/create.html.twig` & `public/js/conflict-check.js`: mendengarkan event `change` pada input tanggal, jam, dan ruangan; memanggil `/api/check-availability`; menampilkan badge status hijau/merah.
4. Buat `app/views/admin/dashboard.html.twig`: tabel permohonan dengan tombol Setujui dan Tolak.

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/BookingAndAdminFlowTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/controllers/BookingController.php app/controllers/AdminController.php app/views/booking/ app/views/admin/ public/js/conflict-check.js tests/BookingAndAdminFlowTest.php
git commit -m "feat: implement booking flow with real-time conflict checking and admin approval panel"
```

---

### Task 9: Full End-to-End Verification & Documentation

**Files:**
- Create: `README.md`
- Test: Semua unit test di `tests/`

- [ ] **Step 1: Run all test suites**

Run: `vendor/bin/phpunit`
Expected: All tests pass with 0 errors and 0 failures.

- [ ] **Step 2: Write README.md with clear instructions**

Dokumentasikan:
- Cara clone & install dependensi (`composer install`).
- Cara migrasi & seed database SQLite (`php runway init:db` atau otomatis via config).
- Cara menjalankan server lokal (`php -S localhost:8000 -t public`).
- Akun default untuk pengujian (`admin / admin123`, `komti / komti123`, `ormawa / ormawa123`).
- Dokumentasi fitur (Kalender, Deteksi Bentrok, Prioritas Kuliah, Peminjaman Acara).

- [ ] **Step 3: Final Commit**

```bash
git add README.md
git commit -m "docs: complete setup instructions and project overview"
```
