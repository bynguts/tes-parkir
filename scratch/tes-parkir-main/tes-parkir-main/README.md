# 🅿️ SmartParking System v2

Sistem manajemen parkir berbasis PHP + MySQL yang production-ready.

---

## 🚀 Instalasi

### 1. Import Database

```bash
mysql -u root -p < database/parking_db_v2.sql
```

### 2. Setup Password Default

Jalankan di browser **satu kali saja** setelah import:

```
http://localhost/parking/setup_passwords.php?key=SETUP_2026_PARKIR
```

> **⚠️ HAPUS `setup_passwords.php` setelah dijalankan!**

### 3. Konfigurasi Koneksi DB

Edit `config/connection.php`:

```php
$db_host = 'localhost';
$db_name = 'parking_db';
$db_user = 'root';
$db_pass = 'your_password';
```

---

## 🔑 Akun Default

| Username | Password     | Role       |
|----------|-------------|------------|
| admin    | admin123    | superadmin |
| operator | operator2026| admin      |
| rizky    | rizky123    | operator   |

> Ganti semua password setelah setup via **Admin > Users > Reset Password**.

---

## 📁 Struktur File

```
parking/
├── config/
│   ├── connection.php          # PDO database connection
│   └── configexample.js        # Template config JS
├── database/
│   └── parking_db_v2.sql       # Schema lengkap dengan indexes
├── includes/
│   ├── auth_guard.php          # Session & auth check
│   └── functions.php           # CSRF, fee calculator, helpers
│
├── index.php                   # Dashboard utama + sidebar
├── login.php                   # Login dengan bcrypt + rate limiting
├── logout.php                  # Secure session destroy
├── setup_passwords.php         # Setup awal password (hapus setelah pakai!)
│
├── gate_simulator.php          # Entry gate + QR scanner
├── gate_exit.php               # Exit gate + fee calculation
├── print_ticket.php            # Cetak tiket (auto + manual)
│
├── dashboard.php               # Kendaraan aktif (unpaid)
├── dashboard_revenue.php       # Laporan revenue harian
├── slot_map.php                # Visualisasi slot per lantai (real-time)
├── reservation.php             # Booking slot di muka
├── scan_log.php                # Riwayat aktivitas gate
│
├── admin_slots.php             # CRUD slot parkir
├── admin_rates.php             # Kelola tarif parkir
├── admin_operators.php         # Kelola operator
├── admin_users.php             # Kelola user login (superadmin only)
│
├── delete_logs.php             # API: hapus log (AJAX)
└── get_log_dates.php           # API: daftar tanggal log (AJAX)
```

---

## ✅ Perbaikan v2 vs v1

### Keamanan
- ✅ **SQL Injection** — Semua query menggunakan PDO prepared statements
- ✅ **Password** — `password_hash()` bcrypt cost-12, bukan plaintext
- ✅ **CSRF Protection** — Token di semua form POST
- ✅ **Rate Limiting** — Max 5 login gagal per 5 menit per IP
- ✅ **Session Security** — `session_regenerate_id()`, httponly, samesite=Strict
- ✅ **Role-Based Access** — superadmin / admin / operator
- ✅ **Atomic Transactions** — Checkout pakai `beginTransaction()` + rollback

### Database
- ✅ **INDEX** pada kolom kritis: `payment_status`, `check_out_time`, `plate_number`, `scan_time`
- ✅ **Data integrity** — Hapus `vehicle_type=''` dan `duration_hours=999.99`
- ✅ **Slot lebih banyak** — 58 slot (3 lantai: G, L1, L2)
- ✅ **Tabel `admin_users`** — Menggantikan hardcoded credentials
- ✅ **Tabel `reservation`** — Untuk sistem booking

### Fitur Baru
- ✅ **Slot Map** — Visualisasi real-time per lantai, auto-refresh 30 detik
- ✅ **Reservation System** — Booking slot dengan deteksi overlap otomatis
- ✅ **Admin CRUD** — Kelola slot, tarif, operator, dan user via UI
- ✅ **Dashboard Index** — Sidebar navigasi + statistik live + alert slot penuh
- ✅ **Rate Preview** — Simulasi biaya saat edit tarif
- ✅ **Multi-floor** — Ground, Level 1, Level 2

---

## 🛡️ Catatan Produksi

1. **HTTPS wajib** di production — set `'secure' => true` di session cookie params
2. **Hapus `setup_passwords.php`** setelah setup awal
3. Tambahkan `.htaccess` untuk block akses langsung ke folder `config/` dan `includes/`
4. Set `display_errors = Off` dan `log_errors = On` di `php.ini`

---

## 📊 Alur Sistem

```
Masuk:
  Gate Simulator → Print Ticket → slot = 'occupied', ticket = 'active', trx = 'unpaid'

Keluar:
  Gate Exit (scan QR) → Hitung biaya → slot = 'available', ticket = 'used', trx = 'paid'

Reservasi:
  Reservation → Pilih waktu → slot otomatis dialokasikan → reservation_code diberikan
```
