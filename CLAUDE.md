# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## 🚀 Development Setup

### Database
```bash
# Import database schema
mysql -u root -p < database/parking_db_v2.sql

# Setup default passwords (run once after import)
# Visit: http://localhost/parking/setup_passwords.php?key=SETUP_2026_PARKIR
# ⚠️ DELETE setup_passwords.php after running!
```

### Configuration
Edit `config/connection.php` to match your database credentials:
```php
$db_host = 'localhost';
$db_name = 'parking_db'; // or 'parking_db_v2'
$db_user = 'root';
$db_pass = 'your_password';
```

### Default Accounts
| Username | Password     | Role       |
|----------|-------------|------------|
| admin    | admin123    | superadmin |
| operator | operator2026| admin      |
| rizky    | rizky123    | operator   |

> Change all passwords after setup via **Admin > Users > Reset Password**

## 🏗️ Code Architecture

### Directory Structure
- `config/` - Database connection and BASE_URL detection
- `database/` - SQL schema file
- `includes/` - Shared components: header, footer, sidebar, auth_guard, functions
- `modules/` - Feature modules: admin (CRUD), operations (gate logic), reports
- `api/` - AJAX endpoints for dynamic functionality
- Root level - Main PHP pages (dashboard, login, gate simulators, etc.)

### Key Components
1. **Authentication System** (`includes/auth_guard.php`)
   - Session management with role-based access control
   - Password hashing using bcrypt (cost-12)
   - CSRF protection tokens in forms
   - Rate limiting (5 failed logins per 5 minutes per IP)

2. **Database Connection** (`config/connection.php`)
   - PDO with prepared statements (SQL injection prevention)
   - Automatic BASE_URL detection
   - Error handling with logging

3. **UI Layout** (`includes/header.php` + `includes/sidebar.php`)
   - Tailwind CSS via CDN with custom configuration
   - Responsive design with sticky header
   - Role-based UI elements
   - Material Symbols icons
   - Scroll container optimization (body overflow hidden, main scrolls)

4. **Core Features**
   - **Gate Simulation**: Entry/exit logic with QR scanning and fee calculation
   - **Slot Management**: Real-time visualization with 3-floor layout (58 slots)
   - **Reservation System**: Booking with overlap detection
   - **Reporting**: Revenue tracking, Excel export
   - **Admin CRUD**: Manage slots, rates, operators, users

### Security Features (v2 Improvements)
- ✅ PDO prepared statements for all queries
- ✅ bcrypt password hashing (cost-12)
- ✅ CSRF tokens on all POST forms
- ✅ Rate limiting on login attempts
- ✅ Secure session settings (httponly, samesite=Strict)
- ✅ Role-based access control (superadmin/admin/operator)
- ✅ Atomic transactions for checkout operations
- ✅ Input validation and sanitization

## 💻 Development Commands

### Local Development
- **Start PHP built-in server** (if not using XAMPP):
  ```bash
  php -S localhost:8000 -t .
  ```
  Then visit `http://localhost:8000` in your browser.

- **Syntax checking** (PHP lint):
  ```bash
  # Check a single file
  php -l path/to/file.php
  # Check all PHP files recursively
  find . -name "*.php" -exec php -l {} \;
  ```

- **Code style** (if PHP_CodeSniffer is installed):
  ```bash
  phpcs --standard=PSR12 .
  ```

### Database
- **Import schema**: `mysql -u root -p < database/parking_db_v2.sql`
- **Update schema**: Edit `database/parking_db_v2.sql` and re-import on dev DB.
- **Reset passwords**: Visit `http://localhost/parking/setup_passwords.php?key=SETUP_2026_PARKIR` then **delete** `setup_passwords.php`.

### Caching
- **Clear OPcache** (if enabled): Restart Apache/php-fpm or call `opcache_reset()` via a temporary script.
- **Clear browser cache** when testing UI changes (Tailwind via CDN).

### Testing
- **Automated tests**: None configured; testing is manual.
- **Manual testing workflow**:
  1. Login with appropriate role (see Default Accounts).
  2. Test feature end-to-end.
  3. Verify database changes via phpMyAdmin or MySQL CLI.
  4. Check browser console for errors.
  5. Test responsive behavior across devices.

## 🔧 Maintenance Commands

### Log Monitoring
- Check web server error logs for PHP errors.
- Database connection errors logged via `error_log()` in `connection.php`.
- Custom application logs can be added to `modules/operations/`.

### Cache Clearing (if needed)
```bash
# OPcache reset (if enabled)
# Usually handled by web server restart
```

## 📝 Coding Standards

### PHP
- Use PDO prepared statements for all database queries.
- Sanitize output with `htmlspecialchars()` when echoing user data.
- Follow existing naming conventions (snake_case for files/variables).
- Keep PHP opening tag `<?php` on first line.
- Use alternative syntax for control structures in HTML contexts.

### Security
- Never trust `$_GET`, `$_POST`, `$_REQUEST` without validation.
- Always use CSRF tokens for form submissions.
- Hash passwords with `password_hash()` and verify with `password_verify()`.
- Implement proper error handling without exposing sensitive information.

### HTML/Tailwind
- Use existing Tailwind configuration from `header.php`.
- Maintain responsive design principles.
- Follow existing component patterns (buttons, forms, cards).
- Keep accessibility in mind (proper labeling, contrast).

## 🚨 Important Notes

1. **Security**: Never commit real database credentials.
2. **Setup Files**: Delete `setup_passwords.php` after initial setup.
3. **Production**: 
   - Set `display_errors = Off` and `log_errors = On` in `php.ini`.
   - Use HTTPS with secure session cookies.
   - Add `.htaccess` to block direct access to `config/` and `includes/` directories.
4. **Updates**: When pulling changes, always check `database/parking_db_v2.sql` for schema updates.

This CLAUDE.md file should help future instances of Claude Code quickly understand and work effectively with this Smart Parking System codebase.