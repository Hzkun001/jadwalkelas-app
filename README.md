# Jadwal Kelas App (FST)

Sistem Monitoring Ketersediaan Ruang Kelas Lokal & Pencegahan Bentrok Perkuliahan berbasis **FlightPHP 3**, **Twig**, **Tailwind CSS**, dan **SQLite (Pure Embedded DB)**.

Aplikasi ini dibangun khusus untuk kebutuhan Fakultas Sains dan Teknologi (FST) dengan mengedepankan **performa ultra-ringan (ultra-lightweight)**, cepat diakses melalui smartphone mahasiswa, dan memiliki aturan hierarki ketat di mana **perkuliahan reguler diprioritaskan secara mutlak di atas peminjaman acara/kegiatan ormawa**.

Database menggunakan **SQLite murni** (`database/app.sqlite`) yang sangat portabel, hemat memori, dan tidak memerlukan service daemon eksternal (seperti MySQL/MariaDB).

---

## Fitur Utama

1. **Live Room Occupancy (Tanpa Login)**:
   - Menampilkan status instan ruangan pada jam berjalan: 🟢 **Tersedia / Kosong**, 🔴 **Sedang Kuliah**, atau 🟡 **Sedang Acara**.
   - Dilengkapi informasi mata kuliah, kelas, dosen pengampu, atau nama acara dan waktu selesai.
2. **Tampilan Kalender Interaktif (Weekly & Monthly Timetable)**:
   - Kalender mingguan visual (Senin s.d. Sabtu, 07:00 - 18:00) dan kalender bulanan.
   - Pembedaan warna blok: 🟦 **Biru untuk Kuliah Reguler**, 🟧 **Oranye untuk Acara Ormawa**.
   - Filter ruangan instan (`Semua Ruangan`, `fst 1.1`, `fst 1.2`, ..., `LAB-INT-3`).
   - Modal pop-up rincian lengkap saat blok kegiatan diklik.
3. **Conflict Detection Engine & Prioritas Perkuliahan**:
   - Deteksi matematis waktu bertabrakan ($S_1 < E_2 \land E_1 > S_2$).
   - Peminjaman acara pada tanggal dan jam yang beririsan dengan jadwal kuliah **otomatis ditolak/diberi peringatan** dengan pesan bahwa kuliah memiliki prioritas utama.
   - Pengecekan real-time di form permohonan via AJAX (`/api/check-availability`) sebelum tombol kirim diaktifkan.
   - Validasi ganda di sisi server (*server-side double validation*) untuk mencegah *race condition*.
4. **Alur Peminjaman & Verifikasi Admin**:
   - Organisasi mahasiswa dapat mengajukan permohonan peminjaman ruangan.
   - Status pelacakan: `Menunggu Persetujuan`, `Disetujui`, atau `Ditolak (beserta alasan penolakan)`.
   - Panel admin untuk verifikasi cepat (Setujui / Tolak) dan pengelolaan master ruangan.

---

## 8 Ruang Kelas Lokal FST (Pre-seeded Data)

1. `fst 1.1` (Ruang Kelas Teori - Kapasitas 40)
2. `fst 1.2` (Ruang Kelas Teori - Kapasitas 40)
3. `fst 3.4` (Ruang Kelas Teori - Kapasitas 45)
4. `fst 3.5` (Ruang Kelas Teori - Kapasitas 45)
5. `fst 3.6` (Ruang Kelas Teori - Kapasitas 45)
6. `LAB-INT-internet-1` (Laboratorium Komputer/Jaringan - Kapasitas 35)
7. `LAB-INT-internet-2` (Laboratorium Komputer/Jaringan - Kapasitas 35)
8. `LAB-INT-internet-3` (Laboratorium Komputer/Jaringan - Kapasitas 35)

---

## Akun Pengguna Bawaan (Default Seed)

| Username | Password | Role | Hak Akses |
| :--- | :--- | :--- | :--- |
| `admin` | `admin123` | `admin` | Verifikasi/Persetujuan Peminjaman, Kelola Master Ruangan & Jadwal |
| `komti` | `komti123` | `komti` | Perwakilan kelas (Ketua Tingkat TI-3A) |
| `ormawa` | `ormawa123` | `ormawa` | Pengurus Ormawa (Pengajuan Ruang Kegiatan) |

*Masyarakat umum dan mahasiswa dapat melihat jadwal dan status ruangan secara langsung tanpa perlu login.*

---

## Persyaratan Sistem

- PHP >= 8.0
- Composer 2.x
- Ekstensi PHP: `pdo`, `pdo_sqlite` (driver Linux x86_64 sudah dibundel di folder `ext/` untuk kemudahan langsung pakai)

---

## Cara Menjalankan Aplikasi

### 1. Install Dependensi
```bash
composer install
```

### 2. Jalankan Development Server
Cukup jalankan perintah:
```bash
composer start
```
*(Perintah ini otomatis memuat ekstensi lokal `ext/pdo_sqlite.so` dan menjalankan PHP built-in server di port 8000)*.

Buka browser di:
- **Beranda Publik (Live Status)**: `http://localhost:8000/`
- **Kalender Interaktif**: `http://localhost:8000/calendar`
- **Form Peminjaman Ruang**: `http://localhost:8000/booking/create`
- **Halaman Masuk Petugas**: `http://localhost:8000/login`
- **Panel Admin**: `http://localhost:8000/admin`

---

## Pengujian Otomatis (Automated Testing)

Jalankan seluruh test suite menggunakan:
```bash
composer test
```
Seluruh **25 test case (72 assertions, 100% PASSING)** mencakup:
- Inisialisasi skema database SQLite dan verifikasi 8 ruangan FST.
- Uji matematika deteksi bentrok (adjacent slots, partial overlap, superset overlap).
- Uji aturan prioritas kuliah reguler di atas permohonan acara ormawa.
- Uji model data (Room, Schedule, EventBooking, User).
- Uji autentikasi dan kontrol sesi.
- Uji REST API (`/api/rooms`, `/api/check-availability`, `/api/events`).
- Uji isolasi database pengujian (`:memory:`) dan kompatibilitas dialek SQLite murni.
- Uji alur lengkap peminjaman (submit -> pending -> approve/reject -> deteksi bentrok jadwal baru).
