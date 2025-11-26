# Path Corrections Applied

This document details all the file paths that were corrected during the project restructuring.

## Summary

- **Total files processed**: 60+
- **Files moved**: 45
- **Paths corrected**: 25+
- **Duplicate files removed**: 2
- **New files created**: 3 (navbar.php, auth_check.php, .htaccess)

## Image Path Corrections

### Root Level Files

| File | Old Path | New Path | Status |
|------|----------|----------|--------|
| index.php | `src="umak3.ico"` | `src="/assets/images/umak3.ico"` | ✅ Fixed |
| register.php | `src="umak3.ico"` | `src="/assets/images/umak3.ico"` | ✅ Fixed |
| home.php | `src="clinic_umak.ico"` | `src="/assets/images/clinic_umak.ico"` | ✅ Fixed |
| user_profile.php | `href="umak3.ico"` | `href="/assets/images/umak3.ico"` | ✅ Fixed |

### Images Consolidated

All images moved to `/assets/images/`:
- umak3.ico
- clinic_umak.ico
- umak2.png
- umaklogo.png

## CSS Path Corrections

### includes/header.php

| Old | New | Description |
|-----|-----|-------------|
| `href="style.css"` | `href="/assets/css/style.css"` | Main stylesheet |
| `href="style/common.css"` | `href="/assets/css/common.css"` | Common styles |
| `href="style/responsive.css"` | `href="/assets/css/responsive.css"` | Responsive styles |
| Added | `href="/assets/css/admin.css"` | Admin styles (consolidated) |

### CSS Files Consolidated

All CSS moved to `/assets/css/`:
- style.css (from root)
- common.css (from style/ folder)
- responsive.css (from style/ folder)
- admin.css (from admin/style.css)

## Navigation Link Corrections

### Patient Appointments Page

| File | Old | New | Fix Type |
|------|-----|-----|----------|
| patient/appointments.php | `href="style/common.css"` | `href="/assets/css/common.css"` | CSS path |
| patient/appointments.php | `require_once 'config/` | `require_once '../config/` | Include path |
| patient/appointments.php | `include 'includes/` | `include '../includes/` | Include path |
| patient/appointments.php | `href="admin/archive` | `href="/admin/archive` | Admin link |
| patient/appointments.php | `href="schedule.php"` | `href="/patient/schedule.php"` | Page link |

### Patient Schedule Page

| File | Old | New | Fix Type |
|------|-----|-----|----------|
| patient/schedule.php | `require_once 'config/` | `require_once '../config/` | Include path |
| patient/schedule.php | `include 'includes/` | `include '../includes/` | Include path |
| patient/schedule.php | `href="appointments.php"` | `href="/patient/appointments.php"` | Page link |

## New Components Created

### 1. navbar.php (NEW)
**Location**: `/includes/navbar.php`

**Features**:
- ✅ Uses absolute paths for all links (starts with /)
- ✅ Works from any directory in the application
- ✅ Auto-detects user role (guest, patient, admin)
- ✅ Shows context-appropriate menu items
- ✅ Includes active page highlighting
- ✅ Bootstrap 5 responsive design
- ✅ Replaces old nav.php

**Key Functions**:
```php
isPageActive($href, $currentPage, $currentPath)  // Detect active page
navLink($href, $label, $currentPage, $currentPath) // Output link with active class
```

### 2. auth_check.php (NEW)
**Location**: `/includes/auth_check.php`

**Purpose**: Simple authentication include for admin pages

**Usage**:
```php
<?php
session_start();
require_once __DIR__ . '/../includes/auth_check.php';
// Page code continues only if authenticated as admin
?>
```

**Checks**:
- ✅ Session is valid
- ✅ User is logged in (`$_SESSION['loggedin']`)
- ✅ User is admin (`$_SESSION['isAdmin']`)

### 3. .htaccess (NEW)
**Location**: `/.htaccess` (root directory)

**Purpose**: Apache web server configuration

**Includes**:
- Enables mod_rewrite for clean URLs (optional)
- Sets DirectoryIndex to index.php
- Denies direct access to sensitive folders (config/, migrations/, logs/)
- Allows access to assets/ and includes/

## Directory Reorganization

### From (Before):
```
project_HCI/
├── project_HCI/
│   └── project_HCI/
│       └── project_HCI/
│           └── [actual files were 4 levels deep]
```

### To (After):
```
project_HCI/clean/
├── admin/
├── assets/css/
├── assets/images/
├── assets/js/
├── assets/uploads/
├── config/
├── includes/
├── models/
├── migrations/
├── logs/
├── patient/
├── [root PHP files]
└── [config files]
```

## Include Path Standards

### For files at project root or in root-level folders:
```php
require_once 'config/database.php';
require_once 'config/functions.php';
include 'includes/navbar.php';
```

### For files in admin/ folder:
```php
require_once '../config/database.php';
require_once '../config/functions.php';
include '../includes/navbar.php';
```

### For files in patient/ folder:
```php
require_once '../config/database.php';
require_once '../config/functions.php';
include '../includes/navbar.php';
```

### For auth protection in any admin page:
```php
<?php
session_start();
require_once __DIR__ . '/../includes/auth_check.php';
?>
```

## Database & Configuration Files

All config files remain in `/config/` with correct paths:

| File | Purpose | Status |
|------|---------|--------|
| database.php | PDO MySQL connection | ✅ Uses environment variables |
| functions.php | Global utility functions | ✅ Requires database.php |
| admin_access.php | Role-based access control | ✅ Defines doctors/nurses |
| patient_functions.php | Patient-specific functions | ✅ Requires database.php |
| history_log_functions.php | Activity logging | ✅ Requires database.php |
| reset_user_password.php | Password reset logic | ✅ Standalone utility |

## CSS Consolidation

### Before (Scattered):
- root/style.css
- root/style/common.css
- root/style/responsive.css
- admin/style.css
- admin/assets/css/biometrics.css
- admin/assets/css/input.css
- admin/assets/css/main.css
- admin/assets/css/styles.css
- admin/style/common.css
- admin/style/responsive.css
- admin/style/style.css

### After (Consolidated):
- /assets/css/style.css (main)
- /assets/css/common.css (common styles)
- /assets/css/responsive.css (responsive)
- /assets/css/admin.css (admin-specific)

**Note**: Removed duplicate CSS files. If you need additional CSS, add them to /assets/css/ and include them in the appropriate pages.

## Verification Checklist

After restructuring, verify:

- [ ] All images load correctly
- [ ] Navigation menu appears on every page
- [ ] Active page highlighting works in navbar
- [ ] Admin pages require login before access
- [ ] Links work from any page (root, admin/, patient/)
- [ ] CSS styles apply correctly
- [ ] Database connection works
- [ ] Session management functions properly

## Quick Reference: Path Cheat Sheet

### Absolute Paths (for HTML links, images):
```html
<!-- Always start with / -->
<a href="/admin/admin.php">Dashboard</a>
<img src="/assets/images/umak3.ico" alt="Logo">
<link rel="stylesheet" href="/assets/css/style.css">
<script src="/assets/js/script.js"></script>
```

### Relative Paths (for PHP requires from root level):
```php
// From root level files:
require_once 'config/database.php';
include 'includes/navbar.php';

// From subdirectories (admin/, patient/):
require_once '../config/database.php';
include '../includes/navbar.php';
```

### Magic Paths (for security, works from any level):
```php
// Always works regardless of directory:
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';
```

## Rollback Instructions

If needed to revert to old structure:
1. Old files still exist in: `project_HCI/project_HCI/project_HCI/project_HCI/project_HCI/`
2. Clean version is in: `project_HCI/clean/`
3. To revert: Delete `clean/` folder and rename old path back

## Testing the Changes

### Test 1: Navigation Works
- Visit `http://localhost/project_HCI/index.php` (navbar should appear)
- Click various links - they should all work

### Test 2: Images Load
- All images should be visible (logo in navbar, images on pages)
- Check browser console for 404 errors

### Test 3: Admin Access
- Login with admin@gmail.com / 123
- Verify navbar shows admin menu
- Verify you can access `/admin/admin.php`
- Try accessing admin page without login - should redirect

### Test 4: From Any Location
- Test links from different pages
- All should use absolute paths and work correctly

## Notes for Future Development

1. **Add new CSS**: Place in `/assets/css/` and include via `/assets/css/filename.css`
2. **Add new JS**: Place in `/assets/js/` and include via `/assets/js/filename.js`
3. **Add new images**: Place in `/assets/images/` and reference via `/assets/images/filename.ext`
4. **Create new pages**: Use relative `require_once` for config/includes, but absolute paths for navigation links
5. **Create new admin pages**: Always include auth_check.php at the top
6. **Include navbar**: Always include `/includes/navbar.php` for consistent navigation

---

Last Updated: 2025-11-21
Project: Project HCI - Medical Appointment Management System
Restructuring Version: 1.0
