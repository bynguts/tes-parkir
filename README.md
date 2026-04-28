# 🅿️ Parkhere System v2

Production-ready parking management system based on PHP + MySQL.

---

## 🚀 Installation

### 1. Import Database

```bash
mysql -u root -p < schema.sql
```

### 2. Environment Configuration

Copy the example environment file and update your credentials:

```bash
cp .env.example .env
```

Edit `.env`:

```env
DB_HOST=localhost
DB_NAME=parking_db_v2
DB_USER=root
DB_PASS=your_password
```

---

## 🔑 Default Accounts

| Username   | Password      | Role       |
|------------|---------------|------------|
| superadmin | superadmin123 | superadmin |
| admin      | admin123      | admin      |
| operator   | operator123   | operator   |

> Change all passwords after setup via **Admin > Users > Reset Password**.

---

## 📁 File Structure

```
parking/
├── api/                        # JSON Endpoints for AJAX
├── assets/                     # CSS, JS, and Images
├── config/
│   └── connection.php          # PDO database connection + .env loader
├── design-system/              # Design specifications
├── includes/
│   ├── auth_guard.php          # Session & auth check
│   └── functions.php           # Shared utilities & helpers
├── modules/
│   ├── admin/                  # Slot, Rate, Operator, User CRUD
│   ├── operations/             # Gates, Simulator, Logs
│   └── reports/                # Analytics & Visualization
│
├── .env.example                # Environment template
├── README.md                   # System documentation
├── schema.sql                  # Complete database schema
├── index.php                   # Unified Dashboard
├── login.php                   # Enterprise Auth
└── logout.php                  # Session Termination
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
