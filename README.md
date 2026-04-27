# 🅿️ SmartParking System v2

Production-ready parking management system based on PHP + MySQL.

---

## 🚀 Installation

### 1. Import Database

```bash
mysql -u root -p < database/parking_db_v2.sql
```


### 3. DB Connection Configuration

Edit `config/connection.php`:

```php
$db_host = 'localhost';
$db_name = 'parking_db_v2';
$db_user = 'root';
$db_pass = 'your_password';
```

---

## 🔑 Default Accounts

| Username | Password     | Role       |
|----------|-------------|------------|
| admin    | admin123    | superadmin |
| operator | operator2026| admin      |
| rizky    | rizky123    | operator   |

> Change all passwords after setup via **Admin > Users > Reset Password**.

---

## 📁 File Structure

```
parking/
├── config/
│   ├── connection.php          # PDO database connection
│   └── configexample.js        # Template config JS
├── database/
│   └── parking_db_v2.sql       # Complete schema with indexes
├── includes/
│   ├── auth_guard.php          # Session & auth check
│   └── functions.php           # CSRF, fee calculator, helpers
│
├── index.php                   # Main dashboard + sidebar
├── login.php                   # Login with bcrypt + rate limiting
├── logout.php                  # Secure session destroy
│
├── gate_simulator.php          # Entry gate + QR scanner
├── gate_exit.php               # Exit gate + fee calculation
├── print_ticket.php            # Ticket printing (auto + manual)
│
├── dashboard.php               # Active vehicles (unpaid)
├── dashboard_revenue.php       # Daily revenue reports
├── slot_map.php                # Floor slot visualization (real-time)
├── reservation.php             # Advance slot booking
├── scan_log.php                # Gate activity history
│
├── admin_slots.php             # Parking slot CRUD
├── admin_rates.php             # Manage parking rates
├── admin_operators.php         # Manage operators
├── admin_users.php             # Manage login users (superadmin only)
│
├── delete_logs.php             # API: delete logs (AJAX)
└── get_log_dates.php           # API: log date list (AJAX)
```

---

## ✅ Improvements v2 vs v1

### Security
- ✅ **SQL Injection** — All queries use PDO prepared statements
- ✅ **Passwords** — `password_hash()` bcrypt cost-12, not plaintext
- ✅ **CSRF Protection** — Tokens in all POST forms
- ✅ **Rate Limiting** — Max 5 failed logins per 5 minutes per IP
- ✅ **Session Security** — `session_regenerate_id()`, httponly, samesite=Strict
- ✅ **Role-Based Access** — superadmin / admin / operator
- ✅ **Atomic Transactions** — Checkout uses `beginTransaction()` + rollback

### Database
- ✅ **INDEX** on critical columns: `payment_status`, `check_out_time`, `plate_number`, `scan_time`
- ✅ **Data integrity** — Removed `vehicle_type=''` and `duration_hours=999.99`
- ✅ **More slots** — 58 slots (3 floors: G, L1, L2)
- ✅ **`admin_users` table** — Replaces hardcoded credentials
- ✅ **`reservation` table** — For booking system

### New Features
- ✅ **Slot Map** — Real-time visualization per floor, 30s auto-refresh
- ✅ **Reservation System** — Slot booking with automatic overlap detection
- ✅ **Admin CRUD** — Manage slots, rates, operators, and users via UI
- ✅ **Dashboard Index** — Sidebar navigation + live stats + full occupancy alerts
- ✅ **Rate Preview** — Fee simulation while editing rates
- ✅ **Multi-floor** — Ground, Level 1, Level 2

---

## 🛡️ Production Notes

1. **HTTPS mandatory** in production — set `'secure' => true` in session cookie params
3. Add `.htaccess` to block direct access to `config/` and `includes/` folders
4. Set `display_errors = Off` and `log_errors = On` in `php.ini`

---

## 📊 System Flow

```
Entry:
  Gate Simulator → Print Ticket → slot = 'occupied', ticket = 'active', trx = 'unpaid'

Exit:
  Gate Exit (scan QR) → Calculate fee → slot = 'available', ticket = 'used', trx = 'paid'

Reservation:
  Reservation → Select time → Slot automatically allocated → reservation_code provided
```
