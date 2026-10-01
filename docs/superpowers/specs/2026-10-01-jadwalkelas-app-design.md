# Spesifikasi Desain: Jadwal Kelas App (Sistem Monitoring Ruang & Pencegahan Jadwal Bentrok)

**Tanggal:** 2026-10-01  
**Status:** Approved by User  
**Target:** `jadwalkelas-app` (Fakultas Sains dan Teknologi)

---

## 1. Latar Belakang & Tujuan (Problem & Goals)

### 1.1 Permasalahan
- **Keterbatasan Ruang Kelas Fisik**: Ketersediaan ruang kelas lokal di FST terbatas (`fst 1.1`, `fst 1.2`, `fst 3.4`, `fst 3.5`, `fst 3.6`, `LAB-INT-internet-1`, `LAB-INT-internet-2`, `LAB-INT-internet-3`).
- **Resiko Jadwal Bentrok**: Setiap kelas/tingkat seringkali harus memeriksa secara manual apakah suatu ruangan kosong atau sudah terpakai oleh mata kuliah lain.
- **Peminjaman Ruangan untuk Acara/Ormawa**: Seringkali ada organisasi mahasiswa atau kepanitiaan yang ingin meminjam ruangan kelas lokal untuk acara/rapat/workshop. Namun, **perkuliahan reguler harus memiliki prioritas mutlak** di atas kegiatan non-kuliah. Terjadi bentrok jika acara diadakan di jam yang beririsan dengan perkuliahan.

### 1.2 Tujuan Sistem
1. Memberikan visibilitas publik tanpa login agar seluruh mahasiswa dan dosen dapat memantau status ruangan secara real-time dan transparan.
2. Menyediakan tampilan kalender interaktif (mingguan dan bulanan) untuk mengecek jadwal perkuliahan dan acara per ruangan dengan cepat.
3. Mengotomatisasi deteksi bentrok (*conflict detection engine*) saat jadwal kuliah diinput atau saat permohonan acara diajukan.
4. Menerapkan aturan hierarki prioritas: perkuliahan reguler secara otomatis memblokir pengajuan acara pada jam dan ruangan yang sama.
5. Menjaga aplikasi tetap **sangat ringan (ultra-lightweight)**, cepat dibuka di smartphone mahasiswa dengan bandwidth minim, dan mudah dipelihara.

---

## 2. Arsitektur Teknologi & Lingkungan (Tech Stack)

Berdasarkan evaluasi performa, konsumsi memori, dan kemudahan deployment:
- **Framework**: FlightPHP 3 (`flightphp/core`) - Micro-framework PHP yang sangat cepat dan berbobot ringan (< 15 MB RAM per request).
- **Template Engine**: Twig Template Engine (`twig/twig`) - Server-side rendering bersih, aman (auto-escaping XSS), dan modular.
- **Styling UI**: Tailwind CSS (mobile-first, responsif, modern, dan compact).
- **Frontend Interactivity**: Alpine.js / Vanilla JavaScript ringan untuk:
  - Real-time availability check (Fetch API ke endpoint `/api/check-availability`).
  - Render tampilan kalender mingguan/bulanan (`calendar.js`).
  - Modal detail interaktif.
- **Database**: SQLite 3 (menggunakan PDO Prepared Statements) - file tunggal, performa tinggi untuk pembacaan cepat, zero-configuration.
- **Pengujian**: PHPUnit 9.6+ untuk automated testing backend & conflict engine.

---

## 3. Data Model & Skema Database

### 3.1 Tabel `rooms` (Daftar Ruang Lokal)
Menyimpan data inventaris ruangan fisik:
- `id` (INTEGER, Primary Key, Auto Increment)
- `code` (VARCHAR(50), Unique, Not Null) — Kode unik ruangan (contoh: `fst-1.1`, `lab-int-1`)
- `name` (VARCHAR(100), Not Null) — Nama tampilan ruangan (contoh: `FST 1.1`, `LAB-INT-internet-1`)
- `building` (VARCHAR(100)) — Lokasi gedung / lantai
- `capacity` (INTEGER, Default 40) — Kapasitas kursi
- `room_type` (VARCHAR(50)) — Tipe ruangan (`kelas`, `lab`, `aula`)
- `is_active` (INTEGER, Default 1) — 1 jika aktif, 0 jika dalam perbaikan

**Daftar Ruangan Awal (Seed Data):**
1. `fst 1.1` (Kelas Teori)
2. `fst 1.2` (Kelas Teori)
3. `fst 3.4` (Kelas Teori)
4. `fst 3.5` (Kelas Teori)
5. `fst 3.6` (Kelas Teori)
6. `LAB-INT-internet-1` (Laboratorium Komputer / Jaringan)
7. `LAB-INT-internet-2` (Laboratorium Komputer / Jaringan)
8. `LAB-INT-internet-3` (Laboratorium Komputer / Jaringan)

### 3.2 Tabel `schedules` (Jadwal Kuliah Reguler Mingguan)
Menyimpan jadwal kuliah rutin mingguan:
- `id` (INTEGER, Primary Key, Auto Increment)
- `room_id` (INTEGER, Foreign Key ke `rooms.id`, Not Null)
- `course_name` (VARCHAR(150), Not Null) — Nama mata kuliah
- `class_name` (VARCHAR(50), Not Null) — Kelas/Angkatan (contoh: `TI-3A`, `SI-2B`)
- `lecturer_name` (VARCHAR(150)) — Nama Dosen pengampu
- `day_of_week` (INTEGER, Not Null) — 1 = Senin, 2 = Selasa, 3 = Rabu, 4 = Kamis, 5 = Jumat, 6 = Sabtu
- `start_time` (VARCHAR(5), Not Null) — Format `HH:MM` (contoh: `08:00`)
- `end_time` (VARCHAR(5), Not Null) — Format `HH:MM` (contoh: `10:30`)
- `academic_period` (VARCHAR(50)) — Semester/Tahun Ajaran (contoh: `2026/2027 Ganjil`)
- `created_by` (INTEGER, Relasi ke `users.id`)
- `created_at` (DATETIME, Default CURRENT_TIMESTAMP)

### 3.3 Tabel `event_bookings` (Peminjaman Ruang Acara/Ormawa)
Menyimpan data permohonan dan jadwal kegiatan insidental:
- `id` (INTEGER, Primary Key, Auto Increment)
- `room_id` (INTEGER, Foreign Key ke `rooms.id`, Not Null)
- `event_name` (VARCHAR(150), Not Null) — Nama acara (contoh: `Seminar HMTI`, `Rapat BEM`)
- `organizer` (VARCHAR(100), Not Null) — Penyelenggara kegiatan
- `booking_date` (VARCHAR(10), Not Null) — Format `YYYY-MM-DD` (contoh: `2026-10-15`)
- `start_time` (VARCHAR(5), Not Null) — Format `HH:MM`
- `end_time` (VARCHAR(5), Not Null) — Format `HH:MM`
- `description` (TEXT) — Keterangan / kebutuhan acara
- `status` (VARCHAR(20), Default 'pending') — Status: `pending`, `approved`, `rejected`, `cancelled`
- `rejection_reason` (TEXT, Nullable) — Alasan penolakan jika ditolak
- `user_id` (INTEGER, Foreign Key ke `users.id`)
- `approved_by` (INTEGER, Nullable, Foreign Key ke `users.id`)
- `created_at` (DATETIME, Default CURRENT_TIMESTAMP)

### 3.4 Tabel `users` (Manajemen Akun Pengguna)
- `id` (INTEGER, Primary Key, Auto Increment)
- `username` (VARCHAR(50), Unique, Not Null)
- `password_hash` (VARCHAR(255), Not Null)
- `name` (VARCHAR(100), Not Null)
- `role` (VARCHAR(20), Not Null) — `admin`, `komti`, `ormawa`
- `created_at` (DATETIME, Default CURRENT_TIMESTAMP)

---

## 4. Conflict Detection Engine & Aturan Prioritas

### 4.1 Logika Matematika Deteksi Waktu Bentrok (Time Overlap)
Dua rentang waktu $[S_1, E_1)$ dan $[S_2, E_2)$ pada ruangan yang sama saling bertabrakan jika:
$$S_1 < E_2 \quad \text{DAN} \quad E_1 > S_2$$

### 4.2 Aturan Hierarki Prioritas
1. **Aturan Kuliah Reguler (Prioritas #1)**:
   - Berlaku rutin tiap minggu pada `day_of_week`.
   - Tidak boleh ada 2 jadwal kuliah di ruangan yang sama dengan irisan waktu.
2. **Aturan Acara Ormawa (Prioritas #2)**:
   - Dihitung pada tanggal spesifik `booking_date`.
   - Konversi `booking_date` ke hari dalam minggu (`day_of_week` 1 s.d. 6).
   - **Cek 1**: Apakah pada `day_of_week` tersebut ada jadwal kuliah di `room_id` yang beririsan waktu?
     - **JIKA YA**: Peminjaman **DITOLAK OTOMATIS** dengan pesan error eksplisit:  
       *"Ruangan [Nama Ruang] sedang digunakan untuk perkuliahan [Mata Kuliah] ([Kelas]) pukul [Jam]. Perkuliahan reguler memiliki prioritas utama."*
   - **Cek 2**: Apakah ada acara lain di `booking_date` dan `room_id` yang sudah berstatus `approved` dan beririsan waktu?
     - **JIKA YA**: Peminjaman ditolak karena ruangan sudah terpesan untuk acara lain.
   - **Cek 3**: Jika ruangan kosong dari kuliah dan acara lain:
     - Form lolos validasi, permohonan masuk dengan status `pending` ke antrean verifikasi Admin.

### 4.3 Double Validation (Keamanan Server-Side)
- Validasi instan dilakukan di frontend via Fetch API untuk kenyamanan pengguna (*user experience*).
- **Validasi ulang mutlak dijalankan di backend (`ConflictEngine::validateBooking()`)** saat form disubmit via POST. Jika terjadi percobaan submit paralel (*race condition*), transaksi dibatalkan dan mengembalikan pesan bentrok.

---

## 5. Antarmuka Pengguna & Tampilan Kalender (UI/UX)

### 5.1 Halaman Publik (Tanpa Login)
1. **Status Ruangan Real-time (Live Occupancy Cards)**:
   - Menampilkan status langsung pada jam sistem berjalan untuk setiap ruangan (`fst 1.1`, `fst 1.2`, dst.):
     - 🟢 **TERSEDIA / KOSONG**: Menampilkan rentang waktu kosong sampai jadwal berikutnya.
     - 🔴 **DIGUNAKAN KULIAH**: Nama Mata Kuliah, Kelas, Dosen, Jam Selesai.
     - 🟡 **DIGUNAKAN ACARA**: Nama Acara, Penyelenggara, Jam Selesai.
2. **Tampilan Kalender Interaktif (Calendar View)**:
   - **Filter Ruangan**: Tabs / dropdown untuk memilih ruangan spesifik atau semua ruangan.
   - **Mode Mingguan (Weekly Timetable Grid)**:
     - Kolom: Hari Senin s.d. Sabtu.
     - Baris: Jam 07:00 s.d. 18:00 (slot 30 menit).
     - Blok Berwarna:
       - 🟦 Biru: Kuliah Reguler.
       - 🟧 Oranye: Acara Disetujui (Approved Event).
   - **Mode Bulanan (Monthly Grid)**:
     - Kalender tanggal 1 s.d. 31 dengan indikator titik/badge acara.
   - **Modal Detail**: Klik pada blok jadwal menampilkan popup rincian lengkap mata kuliah / acara.
3. **Pencarian Cepat**:
   - Kolom pencarian untuk mencari kelas (misal "TI-3A") atau nama mata kuliah secara instan.

### 5.2 Pengajuan Peminjaman Ruangan (Ormawa)
- Form dengan pemilih: Ruangan, Tanggal, Jam Mulai, Jam Selesai, Nama Acara, Penyelenggara, dan Keterangan.
- Indikator real-time: AJAX cek ketersediaan otomatis saat user mengubah jam/tanggal/ruangan.
- Status pelacakan peminjaman pribadi (menampilkan apakah masih `pending`, `approved`, atau `rejected`).

### 5.3 Panel Manajemen (Admin & Komti)
- **Komti**:
  - Mengelola jadwal kuliah reguler kelasnya sendiri (input, edit, hapus).
  - Diberikan pencegahan bentrok saat input jadwal kelas lain.
- **Admin**:
  - Verifikasi Peminjaman Acara: Melihat daftar pengajuan masuk, tombol *Setujui* dan *Tolak* (dengan input alasan penolakan).
  - Manajemen Master Ruangan: Tambah/edit kapasitas & fasilitas ruangan.
  - Manajemen Jadwal Keseluruhan.

---

## 6. Struktur REST API Endpoint

| Method | Endpoint | Deskripsi | Akses |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/rooms` | Daftar seluruh ruangan aktif | Publik |
| `GET` | `/api/events` | Data event kuliah & acara untuk kalender (`?room_id=..&start=..&end=..`) | Publik |
| `GET` | `/api/check-availability` | Cek ketersediaan jam & ruangan secara real-time | Publik |
| `POST` | `/api/bookings` | Mengajukan permohonan peminjaman ruangan | Ormawa / Admin |
| `POST` | `/api/bookings/{id}/approve` | Menyetujui permohonan peminjaman acara | Admin |
| `POST` | `/api/bookings/{id}/reject` | Menolak permohonan peminjaman acara | Admin |
| `POST` | `/api/schedules` | Menambah jadwal kuliah baru | Komti / Admin |

---

## 7. Rencana Pengujian Otomatis (Automated Testing Plan)

Pengujian menggunakan PHPUnit untuk memverifikasi logika krusial:
1. `test_lecture_schedule_overlap_same_room_day_fails`:
   - Menolak dua jadwal kuliah yang bertabrakan di `fst 1.1` pada hari Senin pukul 08:00 - 10:00 vs 09:00 - 11:00.
2. `test_lecture_schedule_adjacent_same_room_day_passes`:
   - Mengizinkan jadwal kuliah berurutan (08:00 - 10:00 dan 10:00 - 12:00 di ruangan yang sama).
3. `test_event_booking_blocked_when_regular_lecture_exists`:
   - Memastikan permohonan acara ormawa di hari dan jam yang bertabrakan dengan jadwal kuliah otomatis ditolak dengan pesan prioritas perkuliahan.
4. `test_event_booking_approved_when_room_free`:
   - Memastikan permohonan acara di luar jam perkuliahan diterima dengan status pending.
5. `test_event_booking_overlap_with_approved_event_fails`:
   - Memastikan tidak ada dua acara yang disetujui pada ruangan dan jam yang sama.
6. `test_calendar_api_returns_correct_json_payload`:
   - Menguji bahwa endpoint `/api/events` mengembalikan data dengan format waktu dan warna event yang valid untuk kalender.

---

## 8. Persetujuan & Langkah Lanjutan
Dokumen ini menjadi dasar implementasi teknis. Setelah dikonfirmasi, langkah berikutnya adalah memanggil skill `writing-plans` untuk merinci rencana implementasi langkah demi langkah (*task breakdown*).
