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

## 💡 Common Development Tasks

### Adding New Features
1. Create new PHP file in root or appropriate module directory
2. Include `includes/header.php` and `includes/sidebar.php` as needed
3. Use existing auth_guard.php for authentication checks
4. Follow existing patterns for database queries (PDO prepared statements)
5. Add CSRF protection to forms: `<?= csrf_token() ?>` from functions.php
6. Style with Tailwind utilities (already configured in header)

### Database Changes
1. Update `database/parking_db_v2.sql` with schema changes
2. Test migrations on development database
3. Ensure all queries use prepared statements
4. Add indexes on frequently queried columns for performance

### UI/Modifications
1. Tailwind configuration is in header.php script tag
2. Use existing color variables: surface, surface-bright, on-surface, primary-fixed, secondary-fixed
3. Font families: Inter (default), Manrope (headings), Courier Prime (code)
4. Maintain scroll optimization: body/html overflow hidden, main overflow-y:auto

## 🔧 Maintenance Commands

### Cache Clearing (if needed)
```bash
# OPcache reset (if enabled)
# Usually handled by web server restart
```

### Log Monitoring
- Check web server error logs for PHP errors
- Database connection errors logged via error_log() in connection.php
- Custom application logs can be added to modules/operations/

### Testing
Manual testing workflow:
1. Login with appropriate role
2. Test feature end-to-end
3. Verify database changes
4. Check for console errors
5. Test responsive behavior

## 📝 Coding Standards

### PHP
- Use PDO prepared statements for all database queries
- Sanitize output with `htmlspecialchars()` when echoing user data
- Follow existing naming conventions (snake_case for files/variables)
- Keep PHP opening tag `<?php` on first line
- Use alternative syntax for control structures in HTML contexts

### Security
- Never trust `$_GET`, `$_POST`, `$_REQUEST` without validation
- Always use CSRF tokens for form submissions
- Hash passwords with `password_hash()` and verify with `password_verify()`
- Implement proper error handling without exposing sensitive information

### HTML/Tailwind
- Use existing Tailwind configuration from header.php
- Maintain responsive design principles
- Follow existing component patterns (buttons, forms, cards)
- Keep accessibility in mind (proper labeling, contrast)

## 🚨 Important Notes

1. **Security**: Never commit real database credentials
2. **Setup Files**: Delete `setup_passwords.php` after initial setup
3. **Production**: 
   - Set `display_errors = Off` and `log_errors = On` in php.ini
   - Use HTTPS with secure session cookies
   - Add `.htaccess` to block direct access to config/ and includes/ directories
4. **Updates**: When pulling changes, always check database/parking_db_v2.sql for schema updates

This CLAUDE.md file should help future instances of Claude Code quickly understand and work effectively with this Smart Parking System codebase.