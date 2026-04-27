# 🅿️ SmartParking Enterprise - Indigo Night Edition

**SmartParking Enterprise** is a premium, all-in-one unified parking platform designed to manage complex parking operations with a state-of-the-art "Indigo Night" aesthetic. Built for scalability and security, it features real-time monitoring, AI-driven insights, and a seamless user experience.

---

## ✨ Key Features

### 🏢 Operations & Management
- **Smart Gate Simulator**: Real-time entry/exit simulation with ALPR (Automatic License Plate Recognition) support.
- **Reservation System**: High-priority slot allocation with automatic overlap detection and visitor tracking.
- **Active Vehicle Tracking**: Live monitoring of all vehicles currently inside the facility.
- **Real-time Slot Map**: Visual representation of parking occupancy across multiple zones and floors.

### 📊 Intelligence & Analytics
- **Cereza AI Assistant**: Integrated AI assistant powered by OpenRouter (Claude/Gemini) for operational queries and strategic recommendations.
- **Advanced Analytics**: Interactive revenue graphs, occupancy trends, and traffic flow distribution using Chart.js.
- **Historical Scan Logs**: Detailed records of all gate activity with advanced filtering and search capabilities.
- **Export Reports**: One-click data export to Excel for offline processing.

### 🛡️ Security & Administration
- **CSRF Protection**: All sensitive operations are guarded by secure token validation.
- **RBAC (Role-Based Access Control)**: Granular permissions for Superadmins, Admins, and Operators.
- **Transaction Safety**: Atomic database transactions for secure payment processing and slot state management.
- **Admin Control Panel**: Full CRUD management for slots, parking rates, operators, and user accounts.

---

## 🚀 Tech Stack

- **Backend**: PHP 8.2+ (Core PHP with PDO)
- **Database**: MySQL 8.0+
- **Styling**: Tailwind CSS 3.4 (Modern, utility-first UI)
- **Visuals**: Chart.js, FontAwesome 6 Pro, Flatpickr
- **AI Engine**: OpenRouter API Integration
- **Optimization**: Vite (Asset Bundling)

---

## 🛠️ Installation & Setup

### 1. Database Configuration
1. Create a new database named `parking_db_v2` in your MySQL environment.
2. Import the provided schema:
   ```bash
   mysql -u root -p parking_db_v2 < schema.sql
   ```

### 2. Environment Setup
1. Copy the example environment file:
   ```bash
   cp .env.example .env
   ```
2. Edit `.env` and fill in your database credentials and **OpenRouter API Key**.

### 3. Permissions
Ensure the following directories are writable by the web server (for logs and temp assets):
- `assets/`
- `config/`

---

## 🔑 Default Credentials

| Role | Username | Password |
| :--- | :--- | :--- |
| **Superadmin** | `admin` | `admin123` |
| **Administrator** | `operator` | `operator2026` |
| **Staff/Operator** | `rizky` | `rizky123` |

> [!IMPORTANT]
> For security, immediately rotate all default passwords via the **Admin > Users** management panel.

---

## 📁 File Structure

```text
parkir_final/
├── api/                # Core API endpoints (AI, Traffic, AJAX)
├── assets/             # Compiled CSS, JS, and optimized Images
├── config/             # Database connection & system settings
├── includes/           # Shared UI (Header, Sidebar, AI Assistant)
├── modules/
│   ├── admin/          # Admin CRUD (Users, Rates, Slots)
│   ├── operations/     # Gate Simulators, Reservations, Logs
│   └── reports/        # Analytics, Revenue, Slot Maps, Exports
├── index.php           # Main Dashboard Entry Point
├── home.php            # Public Landing Page
├── reserve.php         # Public Booking Portal
└── schema.sql          # Final Production Database Schema
```

---

## 🛡️ Security & Best Practices

1. **SQL Injection**: Prevented globally via PDO prepared statements.
2. **Password Hashing**: Utilizes `password_hash()` with Bcrypt (cost 12).
3. **Session Hardening**: Secure cookie parameters with `httponly` and `samesite=Strict`.
4. **Rate Limiting**: Integrated protection against brute-force login attempts.
5. **CSRF**: Token-based protection for all state-changing POST requests.

---

**Developed for PHP Course - Final Task Submission.**
*Indigo Night Design System © 2026*
