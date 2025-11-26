# Installation & Setup Guide

## Quick Start

### Step 1: Database Setup

Open phpMyAdmin and create a new database:
```sql
CREATE DATABASE medical_appointment_db;
```

Then import the SQL file:
```bash
# Option A: Via command line (if available)
mysql -u root -p medical_appointment_db < database_setup.sql

# Option B: Via phpMyAdmin
1. Select database: medical_appointment_db
2. Click "Import" tab
3. Choose: database_setup.sql
4. Click "Go"
```

### Step 2: Test the Application

1. **Login Page**
   ```
   http://localhost/project_HCI/index.php
   ```

2. **Admin Login** (default credentials)
   ```
   Email: admin@gmail.com
   Password: 123
   ```

3. **Register New Patient**
   ```
   http://localhost/project_HCI/register.php
   ```

## Detailed Setup

### Prerequisites

- **Apache Server** (with mod_rewrite enabled)
- **PHP 7.4+** (with PDO and MySQL extensions)
- **MySQL 5.7+** or **MariaDB 10.3+**
- **XAMPP**, **WAMP**, or equivalent local server

### Installation Steps

#### 1. Extract Project to Web Root

```bash
# XAMPP location
C:\xampp\htdocs\project_HCI\

# WAMP location
C:\wamp\www\project_HCI\

# Linux location
/var/www/html/project_HCI/
```

The clean project should be at:
```
C:\xampp\htdocs\project_HCI\clean\
```

Or rename `clean` to `project` for cleaner URLs:
```
C:\xampp\htdocs\project_HCI\
```

#### 2. Create MySQL Database

**Using Command Line:**
```bash
mysql -u root -p -e "CREATE DATABASE medical_appointment_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

**Using phpMyAdmin:**
1. Open http://localhost/phpmyadmin
2. Click "New"
3. Database name: `medical_appointment_db`
4. Collation: `utf8mb4_unicode_ci`
5. Click "Create"

#### 3. Import Database Schema

**Using Command Line:**
```bash
cd C:\xampp\htdocs\project_HCI\clean
mysql -u root -p medical_appointment_db < database_setup.sql
```

**Using phpMyAdmin:**
1. Select database: `medical_appointment_db`
2. Click "Import" tab
3. Browse and select: `database_setup.sql`
4. Click "Go"

**Via Browser (Recommended for Windows/XAMPP):**
1. Navigate to: http://localhost/project_HCI/config/create_appointments_table.php
2. Follow the on-screen prompts
3. Or visit migrations folder for additional setup scripts

#### 4. Verify Database Setup

Check that all tables were created:
```sql
USE medical_appointment_db;
SHOW TABLES;
```

You should see tables like:
- users
- appointments
- patient_history
- biometrics
- history_logs
- etc.

#### 5. Test Application

**Access Points:**

| Page | URL | Purpose |
|------|-----|---------|
| Login | `http://localhost/project_HCI/index.php` | Patient/Admin login |
| Register | `http://localhost/project_HCI/register.php` | New patient registration |
| Home | `http://localhost/project_HCI/home.php` | Landing page (after login) |
| Admin Dashboard | `http://localhost/project_HCI/admin/admin.php` | Admin panel |
| Appointments | `http://localhost/project_HCI/patient/appointments.php` | View appointments |
| Schedule | `http://localhost/project_HCI/patient/schedule.php` | Schedule new appointment |

## Configuration

### Environment Variables (Optional but Recommended)

Create a `.env` file in the project root:

```env
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=medical_appointment_db
DB_USERNAME=root
DB_PASSWORD=
```

The application will use these instead of hardcoded values in `config/database.php`.

### Apache Configuration

If you get 404 errors on internal links, enable `mod_rewrite`:

**Windows (XAMPP):**
1. Edit: `C:\xampp\apache\conf\httpd.conf`
2. Find: `#LoadModule rewrite_module modules/mod_rewrite.so`
3. Remove the `#` to uncomment
4. Save and restart Apache

**Linux:**
```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

### PHP Configuration

Ensure these extensions are enabled in `php.ini`:

```ini
extension=pdo_mysql
extension=mysqli
extension=curl
extension=gd
extension=json
```

## User Roles & Access

### Admin Users

Default admin credentials:
- **Email**: admin@gmail.com
- **Password**: 123

Admin users access: `/admin/admin.php`

### Doctor Users

Pre-configured doctors (from `config/admin_access.php`):
- Can access doctor-specific pages
- Can view patient history
- Email format: configure in `get_allowed_doctors()`

### Nurse Users

Pre-configured nurses (from `config/admin_access.php`):
- Can manage appointments
- Can view patient information
- Email format: configure in `get_allowed_nurses()`

### Regular Patients

Regular registered users who:
- Can schedule appointments
- Can view their own appointments
- Can update their profile
- Cannot access admin functions

## Troubleshooting

### Problem: "Connection Refused" Error

**Solution:**
1. Verify MySQL is running
2. Check database credentials in `config/database.php`
3. Verify database name: `medical_appointment_db`
4. Test connection:
   ```bash
   mysql -u root -p -e "USE medical_appointment_db; SHOW TABLES;"
   ```

### Problem: 404 Errors on Links

**Solution:**
1. Verify `.htaccess` is in project root
2. Enable Apache `mod_rewrite`
3. Check that all links start with `/` (absolute paths)
4. Example: ✅ `/admin/admin.php` NOT ❌ `admin.php`

### Problem: Images Not Loading

**Solution:**
1. Verify images are in `/assets/images/`
2. Check image paths in HTML: `src="/assets/images/umak3.ico"`
3. Check browser console for 404 errors
4. Clear browser cache (Ctrl+F5)

### Problem: CSS Not Applying

**Solution:**
1. Verify CSS files in `/assets/css/`
2. Check includes in `<head>`: `href="/assets/css/style.css"`
3. Clear browser cache (Ctrl+F5)
4. Check browser console for errors

### Problem: Login Redirects to Register

**Solution:**
1. Verify database tables were created successfully
2. Check `users` table exists with proper schema
3. Verify `config/database.php` connection works
4. Check `config/functions.php` `get_user_by_email()` function

### Problem: Admin Pages Show 404

**Solution:**
1. Verify you're logged in as admin (check session)
2. Verify `config/admin_access.php` is properly configured
3. Check that admin files exist in `/admin/` folder
4. Verify paths start with `/` in navigation

### Problem: Navbar Not Showing

**Solution:**
1. Verify session is started: `session_start();` (before any output)
2. Verify navbar is included: `<?php include '/includes/navbar.php'; ?>`
3. Check that Bootstrap CSS is loading (check browser console)
4. Verify `/includes/navbar.php` file exists

## Database Migrations

Additional table creation scripts are in `/migrations/`:

```bash
# If you need to add more tables after initial setup:
mysql -u root -p medical_appointment_db < migrations/add_login_security.sql
mysql -u root -p medical_appointment_db < migrations/add_special_status.sql
mysql -u root -p medical_appointment_db < migrations/create_appointment_archives.sql
```

## Security Checklist

Before going to production:

- [ ] Change default admin password
- [ ] Use strong passwords for all accounts
- [ ] Enable HTTPS/SSL
- [ ] Move `.env` outside web root
- [ ] Set proper file permissions (755 for directories, 644 for files)
- [ ] Disable error_reporting in production
- [ ] Set up regular database backups
- [ ] Enable WAF (Web Application Firewall)
- [ ] Review `SECURITY_IMPROVEMENTS.md` file
- [ ] Test all input validation (try SQL injection, XSS)

## Performance Optimization

### Enable Caching

Add to `.htaccess`:
```apache
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType image/jpeg "access 1 month"
    ExpiresByType image/gif "access 1 month"
    ExpiresByType image/png "access 1 month"
    ExpiresByType image/icon "access 1 month"
    ExpiresByType text/css "access 1 week"
    ExpiresByType text/javascript "access 1 week"
</IfModule>
```

### Database Indexing

Important indexes (already in schema):
```sql
CREATE INDEX idx_user_email ON users(email);
CREATE INDEX idx_appointment_user ON appointments(user_id);
CREATE INDEX idx_patient_user ON patient_history(user_id);
```

## Backup & Maintenance

### Regular Database Backups

```bash
# Daily backup
mysqldump -u root -p medical_appointment_db > backup_$(date +%Y%m%d).sql

# With compression
mysqldump -u root -p medical_appointment_db | gzip > backup_$(date +%Y%m%d).sql.gz
```

### Log Rotation

Check `/logs/` directory for:
- biometric_errors.log
- verification_codes.log

Archive old logs and delete if they exceed 10MB.

## Support & Documentation

- **README.md** - Project overview and usage guide
- **PATH_CORRECTIONS.md** - Detailed path fixes applied
- **SECURITY_IMPROVEMENTS.md** - Security features and improvements
- **database_setup.sql** - Full database schema

## Next Steps

1. ✅ Extract project to web root
2. ✅ Create database and import schema
3. ✅ Access http://localhost/project_HCI/index.php
4. ✅ Login with default credentials
5. ✅ Create test user account
6. ✅ Test appointment scheduling
7. ✅ Access admin panel to review data
8. ✅ Configure admin/doctor/nurse accounts
9. ✅ Set up SSL/HTTPS (for production)
10. ✅ Enable backups and monitoring

---

**Version**: 1.0
**Last Updated**: 2025-11-21
**Status**: Ready for Installation
