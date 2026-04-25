# SmartParking — React + PHP Monorepo

Rebuilt dari `tes-parkir-main` dengan arsitektur **frontend/backend terpisah**:

```
smartparking/
├── frontend/          ← React 18 + Vite + Motion (motiondivision/motion)
│   ├── src/
│   │   ├── App.tsx              ← Router utama (React Router v6)
│   │   ├── pages/               ← Login, Home, Dashboard
│   │   ├── components/
│   │   │   ├── layout/          ← AppShell, Sidebar (React)
│   │   │   └── ui/              ← GateHero, PhpPage (iframe wrapper)
│   │   ├── hooks/               ← useAuth
│   │   └── lib/api.ts           ← Centralized API client
│   ├── index.html
│   ├── package.json
│   └── vite.config.ts           ← Dev proxy → PHP backend :8000
│
└── backend/           ← PHP 8+ (Apache / php -S)
    ├── auth/                    ← NEW: JSON endpoints (me, login, logout, csrf)
    ├── api/                     ← NEW: dashboard.php JSON endpoint
    ├── config/connection.php
    ├── includes/                ← functions, auth_guard, header (embed-aware)
    ├── modules/                 ← Semua module PHP lama (gate, reports, admin…)
    ├── database/                ← SQL schema
    ├── index.php                ← Dashboard (standalone fallback)
    ├── login.php                ← Login (standalone fallback)
    └── .htaccess
```

---

## 🚀 Setup Cepat

### 1. Database
```bash
mysql -u root -p < backend/database/parking_db_v2_3nf.sql
mysql -u root -p < backend/database/attendance_migration.sql
```

### 2. Backend Config
Edit `backend/config/connection.php`:
```php
$db_host = 'localhost';
$db_name = 'parking_db';
$db_user = 'root';
$db_pass = 'your_password';
```

### 3. Jalankan Backend (PHP dev server)
```bash
cd backend
php -S localhost:8000
```

> Atau taruh di Apache/Nginx `htdocs/smartparking/backend/`

### 4. Jalankan Frontend (React + Vite)
```bash
cd frontend
npm install
npm run dev
# → http://localhost:3000
```

Vite otomatis proxy `/api/*` dan `/modules/*` ke `http://localhost:8000`.

---

## 🏗️ Arsitektur

### Strategi: Hybrid React + PHP Iframe

| Halaman | Teknologi | Alasan |
|---------|-----------|--------|
| Landing (`/`) | React + Motion | Animated GateHero |
| Login (`/login`) | React + Motion | Form modern |
| Dashboard (`/dashboard`) | React | Stat cards + animasi motion |
| Gate, Vehicles, dll | PHP via `<iframe>` | Preserve logic bisnis lama |
| Reports, Admin | PHP via `<iframe>` | Tidak perlu rebuild ulang |

PHP module pages di-render dalam `<PhpPage>` (iframe) dengan flag `?embed=1` yang menyembunyikan sidebar/header PHP asli — sehingga sidebar React yang aktif.

### Motion (motiondivision/motion)
Menggantikan `framer-motion`. Import dari `motion/react`:
```tsx
import { motion, useReducedMotion } from 'motion/react'
```

### API Flow
```
React → fetch('/api/dashboard.php')
      ← JSON { car_avail, today_rev, … }

React → fetch('/auth/login.php', { method:'POST', body: JSON })
      ← JSON { success: true, role: 'operator' }
```

---

## 🔑 Akun Default

| Username | Password      | Role       |
|----------|---------------|------------|
| admin    | admin123      | superadmin |
| operator | operator2026  | admin      |
| rizky    | rizky123      | operator   |

---

## 📦 Build Production

```bash
# Build frontend
cd frontend
npm run build
# → dist/ siap di-serve oleh nginx/apache

# Deploy:
# - Taruh dist/ di root domain (mis. parking.example.com/)
# - Taruh backend/ di parking.example.com/backend/
# - Update VITE_API_BASE di .env.production → '/backend'
```

### Nginx contoh config
```nginx
server {
    root /var/www/smartparking/frontend/dist;
    index index.html;

    location / {
        try_files $uri $uri/ /index.html;
    }

    location /api/ {
        proxy_pass http://localhost:8000/api/;
    }
    location /auth/ {
        proxy_pass http://localhost:8000/auth/;
    }
    location /modules/ {
        proxy_pass http://localhost:8000/modules/;
    }
}
```

---

## ✅ Apa yang Berubah dari Versi Lama

| Aspek | Sebelum | Sesudah |
|-------|---------|---------|
| UI Framework | PHP + HTML inline | React 18 + React Router |
| Animasi | framer-motion | **motion** (motiondivision) |
| Routing | File-based PHP | React Router v6 |
| Auth check | PHP session per-page | React `useAuth` hook + `/auth/me.php` |
| Dashboard data | PHP `echo` langsung | Fetch ke `/api/dashboard.php` (JSON) |
| Login | PHP form POST | React form → `/auth/login.php` (JSON) |
| Module pages | PHP full-page | PHP via iframe (embed mode) |
| Sidebar | PHP include | React component dengan NavLink |
| Struktur | Flat PHP | `frontend/` + `backend/` terpisah |

---

## 🔒 Keamanan

Semua fitur keamanan original dipertahankan:
- ✅ PDO prepared statements
- ✅ bcrypt password hashing
- ✅ CSRF token (diterbitkan via `/auth/csrf.php`)
- ✅ Rate limiting login (5x / 5 menit per IP)
- ✅ Session httponly + samesite=Strict
- ✅ Role-based access (superadmin / admin / operator)
