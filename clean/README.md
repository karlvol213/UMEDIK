# Project HCI - Medical Appointment System

## Project Structure (Cleaned & Restructured)

```
project_HCI/
│
├── admin/                          # Admin dashboard and management pages
│   ├── admin.php                   # Main admin dashboard
│   ├── info_admin.php              # Biometrics information
│   ├── registered_users.php        # User management
│   ├── patient_history.php         # Patient history records
│   ├── history_log.php             # Activity logs
│   ├── patient_notes.php           # Patient notes
│   ├── reset_user_password.php     # Password reset
│   ├── update_appointment.php      # Appointment management
│   ├── archive_appointment.php     # Archive records
│   └── ... (other admin files)
│
├── assets/                         # Static files (CSS, JS, Images)
│   ├── css/
│   │   ├── style.css               # Main stylesheet
│   │   ├── common.css              # Common styles
│   │   ├── responsive.css          # Mobile responsive styles
│   │   └── admin.css               # Admin panel styles
│   ├── js/                         # JavaScript files
│   ├── images/
│   │   ├── umak3.ico               # UMAK logo
│   │   ├── clinic_umak.ico         # Clinic logo
│   │   ├── umak2.png               # Logo variant
│   │   └── umaklogo.png            # Logo variant
│   └── uploads/                    # User uploaded files
│
├── config/                         # Database and configuration
│   ├── database.php                # Database connection (PDO)
│   ├── functions.php               # Global functions
│   ├── admin_access.php            # Admin access control
│   ├── patient_functions.php       # Patient-related functions
│   ├── history_log_functions.php   # Logging functions
│   └── ... (other config files)
│
├── includes/                       # Reusable components
│   ├── navbar.php                  # NEW: Modern Bootstrap navbar (absolute paths)
│   ├── header.php                  # HTML head component
│   ├── auth_check.php              # NEW: Authentication check include
│   └── nav.php                     # DEPRECATED: Legacy nav (redirects to navbar.php)
│
├── models/                         # Data models
│   ├── Appointment.php
│   ├── Patient.php
│   ├── Biometric.php
│   ├── HistoryLog.php
│   └── ... (other models)
│
├── patient/                        # Patient-facing pages
│   ├── appointments.php            # View appointments
│   ├── schedule.php                # Schedule new appointment
│   └── profile.php                 # (Future) Patient profile
│
├── migrations/                     # Database migration scripts
│   ├── add_login_security.sql
│   ├── create_biometric_tables.sql
│   └── ... (other migrations)
│
├── logs/                          # Application logs
│   ├── biometric_errors.log
│   └── verification_codes.log
│
├── index.php                       # Login page
├── register.php                    # Registration page
├── home.php                        # Landing page (after login)
├── user_profile.php                # User profile page
├── logout.php                      # Logout handler
├── schedule.php                    # Schedule appointment (root level)
├── database_setup.sql              # Initial database setup
├── .htaccess                       # Apache configuration
├── SECURITY_IMPROVEMENTS.md        # Security notes
└── README.md                       # This file
```

## Setup Instructions

### 1. Database Setup

```bash
# Connect to MySQL/PhpMyAdmin and run:
# Option A: Import the SQL file
mysql -u root -p < database_setup.sql

# Option B: Run setup via browser
http://localhost/project_HCI/config/create_appointments_table.php
```

### 2. Configuration

The database connection automatically uses these credentials (can be overridden via environment variables):
- **Host**: 127.0.0.1
- **Database**: medical_appointment_db
- **User**: root
- **Password**: (empty by default)

To use environment variables, create a `.env` file or set:
```
DB_HOST=127.0.0.1
DB_DATABASE=medical_appointment_db
DB_USERNAME=root
DB_PASSWORD=
DB_PORT=3306
```

### 3. Access the Application

- **Login**: http://localhost/project_HCI/
- **Admin Dashboard**: http://localhost/project_HCI/admin/admin.php
- **Registration**: http://localhost/project_HCI/register.php

### 4. Default Credentials

**Admin Account**:
- Email: admin@gmail.com
- Password: 123

**Doctor Accounts** (Pre-configured):
- See config/admin_access.php for allowed doctor emails

**Nurse Accounts** (Pre-configured):
- See config/admin_access.php for allowed nurse emails

## Path Conventions

### All paths now use absolute paths starting from project root:

✅ **Correct Usage**:
```php
// Navigation links
<a href="/admin/admin.php">Dashboard</a>
<a href="/patient/appointments.php">Appointments</a>

// CSS includes
<link rel="stylesheet" href="/assets/css/style.css">

// Images
<img src="/assets/images/umak3.ico" alt="Logo">

// PHP includes (relative within server)
require_once __DIR__ . '/../config/database.php';
```

❌ **Incorrect Usage** (will not work from all directories):
```php
<a href="admin.php">Dashboard</a>
<link rel="stylesheet" href="style.css">
<img src="umak3.ico">
```

## Including the Navbar

### On any page, include the navbar after session_start():

```php
<?php
session_start();
?>
<!DOCTYPE html>
<html>
<head>
    <!-- ... head content ... -->
</head>
<body>
    <?php include __DIR__ . '/../includes/navbar.php'; ?>
    <!-- Page content goes here -->
</body>
</html>
```

The navbar automatically:
- Detects user login status
- Shows appropriate menu for admin vs patient vs guest
- Highlights the active page
- Works from any directory (uses absolute paths)
- Includes responsive Bootstrap styling

## Protecting Admin Pages

### Add this to the top of every admin page:

```php
<?php
session_start();

// Include auth check - redirects to login if not admin
require_once __DIR__ . '/../includes/auth_check.php';

// Your page code here...
?>
```

## File Path Corrections Applied

### Images
- ✅ Changed: `src="umak3.ico"` → `src="/assets/images/umak3.ico"`
- ✅ Changed: `src="clinic_umak.ico"` → `src="/assets/images/clinic_umak.ico"`

### CSS
- ✅ Changed: `href="style.css"` → `href="/assets/css/style.css"`
- ✅ Changed: `href="style/common.css"` → `href="/assets/css/common.css"`
- ✅ Changed: `href="style/responsive.css"` → `href="/assets/css/responsive.css"`

### Admin Links
- ✅ Changed: `href="admin.php"` → `href="/admin/admin.php"`
- ✅ Changed: `href="history_log.php"` → `href="/admin/history_log.php"`
- ✅ Updated all admin navigation to use absolute paths

### Includes (PHP)
- ✅ All relative includes like `require_once 'config/database.php'` remain unchanged (they work correctly with relative paths from the same level)
- ✅ Files in subdirectories now use `require_once '../config/...'` for proper path resolution

## Removed Files

The following files were deleted as duplicates:
- `admin/biometrics.php.bak` - Backup file, removed
- CSS duplicates consolidated to `/assets/css/`

## Important Security Notes

1. **Never commit `.env` files** with real credentials to version control
2. **Database credentials** should use environment variables in production
3. **Admin pages** are protected with session checks (verify in all admin files)
4. **User passwords** are hashed with password_verify()
5. **Failed login attempts** are tracked (3 failed attempts = 3-minute lockout)
6. **Sensitive directories** (.htaccess prevents direct access to config/, migrations/, logs/)

## Troubleshooting

### 404 errors when clicking links?
- Make sure you're including the navbar from `/includes/navbar.php`
- Verify all links start with `/` for absolute paths

### Images not loading?
- Check that images are in `/assets/images/`
- Update image paths to use `/assets/images/filename.ext`

### CSS not applying?
- Check `<head>` includes `/assets/css/style.css` (absolute path)
- Clear browser cache (Ctrl+F5)

### Admin pages redirecting to login?
- Verify session is started with `session_start()`
- Check that credentials are correct (admin@gmail.com / 123)
- Ensure `config/auth_check.php` is included on admin pages

## Additional Resources

- Security improvements: See `SECURITY_IMPROVEMENTS.md`
- Database setup: See `database_setup.sql`
- Migrations: See `migrations/` directory for schema updates

## Contact & Support

For issues or questions, check the logs in `/logs/` directory and refer to the database schema in `database_setup.sql`.
