# NAVIGATION QUICK REFERENCE CARD

## All URL Paths - Correct Format

```
ABSOLUTE PATHS (All start with /clean/)
├── /clean/index.php                      Login
├── /clean/register.php                   Register
├── /clean/home.php                       Dashboard
├── /clean/user_profile.php               Profile
├── /clean/logout.php                     Logout
├── /clean/schedule.php                   Schedule
│
├── /clean/patient/
│   ├── appointments.php                  View Appointments
│   └── schedule.php                      Schedule Appointment
│
├── /clean/admin/
│   ├── admin.php                         Dashboard
│   ├── registered_users.php              Users
│   ├── info_admin.php                    Biometrics
│   ├── patient_history.php               Patient History
│   ├── history_log.php                   Activity Logs
│   ├── patient_notes.php                 Clinical Notes
│   ├── reset_user_password.php           Reset Password
│   └── ... (more admin pages)
│
└── /clean/assets/
    ├── images/umak3.ico                  Logo
    ├── css/                              Stylesheets
    └── js/                               Scripts
```

---

## Navbar Links

### Admin User Menu
```php
Dashboard → href="/clean/admin/admin.php"
Users → href="/clean/admin/registered_users.php"
Biometrics → href="/clean/admin/info_admin.php"
Patient History → href="/clean/admin/patient_history.php"
Logs → href="/clean/admin/history_log.php"
Notes → href="/clean/admin/patient_notes.php"
Reset Password → href="/clean/admin/reset_user_password.php"
Logout → href="/clean/logout.php"
```

### Patient User Menu
```php
Home → href="/clean/home.php"
Appointments → href="/clean/patient/appointments.php"
Profile → href="/clean/user_profile.php"
Logout → href="/clean/logout.php"
```

### Guest Menu
```php
Login → href="/clean/index.php"
Register → href="/clean/register.php"
```

---

## Logo Behavior

```php
Logo Image: /clean/assets/images/umak3.ico
Logo Link (if logged in): /clean/home.php
Logo Link (if guest): /clean/index.php
```

---

## Common Issues & Fixes

| Issue | Solution |
|-------|----------|
| Logo not showing | Check `/clean/assets/images/umak3.ico` exists |
| Links wrong page | Clear cache: Ctrl+Shift+Delete, then F5 |
| Mobile menu broken | Ensure Bootstrap JS is loaded |
| Path not found | Use `/clean/` prefix, not `../` or `./` |
| 404 Error | Use absolute paths starting with `/clean/` |

---

## Code Pattern (Always Use)

```php
<?php
$baseUrl = '/clean/';

// ✅ CORRECT
href="<?php echo $baseUrl; ?>home.php"

// ❌ WRONG
href="./home.php"
href="../home.php"
href="home.php"
```

---

## Testing from Different Locations

✅ From `/clean/index.php` - Links work
✅ From `/clean/patient/appointments.php` - Links work
✅ From `/clean/admin/admin.php` - Links work

All use same `/clean/` prefix - no path calculation needed!

---

**Remember**: All paths in navbar MUST start with `/clean/`
