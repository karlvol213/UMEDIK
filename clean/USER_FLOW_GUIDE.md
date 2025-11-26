# 🏥 UMak Medical Clinic - User Flow Guide

## **Complete Patient User Flow**

### **Step 1: Visit Home Page (Entry Point)**
- **URL:** `https://rachel-kempt-florencio.ngrok-free.dev/project_HCI/clean/` or `https://rachel-kempt-florencio.ngrok-free.dev/`
- **What happens:** Welcome page loads with clinic information
- **No login required:** Everyone can view the home page
- **Actions available:**
  - Register for new account (if not registered)
  - Login (if already have account)

---

### **Step 2: Login with Account**
- **Click:** "Login" button in navbar or "Sign in" button on home page
- **URL:** `https://rachel-kempt-florencio.ngrok-free.dev/project_HCI/clean/index.php`
- **Enter credentials:**
  - Email: (your registered email)
  - Password: (your password)
- **What happens:** 
  - System validates credentials
  - Session is created (`$_SESSION['user_id']`, `$_SESSION['loggedin']`)
  - Redirects to home page

---

### **Step 3: Home Page (Logged In)**
- **URL:** `https://rachel-kempt-florencio.ngrok-free.dev/project_HCI/clean/home.php` or just `/`
- **What happens:** Home page now shows:
  - Your name in navbar (e.g., "michael C Olivo")
  - Logout button in navbar
  - Statistics: Total Appointments, Upcoming Appointments, Available Services
- **Actions available from navbar:**
  - Click "Appointments" → View all your appointments
  - Click "Schedule" → Schedule new appointment
  - Click "Profile" → View your profile
  - Click "Logout" → Logout and return to home page

---

### **Step 4: View Appointments**
- **Click:** "Appointments" in navbar
- **URL:** `https://rachel-kempt-florencio.ngrok-free.dev/project_HCI/clean/patient/appointments.php`
- **What happens:**
  - System checks if logged in (`$_SESSION['user_id']`)
  - Loads all your appointments grouped by status:
    - Requested (pending approval)
    - Approved (confirmed)
    - Completed (past appointments)
    - Cancelled (canceled appointments)

---

### **Step 5: Schedule New Appointment**
- **Click:** "Schedule Now" button (on appointments page or navbar)
- **URL:** `https://rachel-kempt-florencio.ngrok-free.dev/project_HCI/clean/patient/schedule.php`
- **What happens:**
  - Form loads with:
    - Date picker (select appointment date)
    - Time slot selector (available times for that date)
    - Service/Symptom dropdown (select what you need)
    - Comments (optional notes)
  - Click "Schedule" to submit
  - Appointment is created and added to database

---

### **Step 6: Logout**
- **Click:** "Logout" button (red button in navbar)
- **What happens:**
  - Session is destroyed
  - All session variables are cleared
  - Redirects to home page
  - Next time visiting requires login again

---

## **What Was Fixed**

### **Issue 1: Stuck on Login Page After Restart**
- **Problem:** If you refreshed the page while on login page, it would stay on login
- **Solution:** Added automatic redirect in index.php that checks if user is already logged in
- **Code:** If `$_SESSION['loggedin'] === true`, redirects to home.php immediately

### **Issue 2: Logout Not Working**
- **Problem:** Logout button redirected to index.php, but session wasn't cleared properly
- **Solution:** 
  - Improved logout.php to handle both `$_SESSION['id']` and `$_SESSION['user_id']`
  - Now properly destroys session cookies
  - Redirects to home.php instead of login page (cleaner UX)

### **Issue 3: Incorrect Redirect Paths**
- **Problem:** Patient pages (`/patient/appointments.php`) tried to redirect to `index.php` instead of `../index.php`
- **Solution:** Fixed redirect paths to use correct relative paths

---

## **Session Variables Explanation**

When you login, these session variables are created:

```php
$_SESSION['loggedin']    = true                    // Login status
$_SESSION['user_id']     = 123                     // Your user ID
$_SESSION['id']          = 123                     // Same as user_id (for compatibility)
$_SESSION['email']       = 'yourname@gmail.com'    // Your email
$_SESSION['full_name']   = 'John Doe'              // Your name
$_SESSION['role']        = 'user'                  // Your role
$_SESSION['isAdmin']     = false                   // Admin status
```

These variables are checked by:
- **home.php** - Shows your name and stats
- **patient/appointments.php** - Shows your appointments
- **patient/schedule.php** - Schedules new appointment
- **navbar (tailwind_nav.php)** - Shows logout button and your name

---

## **Complete User Flow (Visual)**

```
🏠 HOME PAGE
    ↓
    ├→ [Login Button] → LOGIN PAGE → [Enter credentials] → ✅ Login Success
    └→ [Register Button] → REGISTER PAGE → [Create account] → ✅ Registration Success
                                                                    ↓
                                        HOME PAGE (Logged In) ← ✅
                                              ↓
                                    [Navbar Options]
                                    ├→ Appointments → View all appointments
                                    ├→ Schedule → Schedule new appointment
                                    ├→ Profile → View your profile
                                    └→ Logout → [Clear session] → HOME PAGE
```

---

## **Testing the Flow**

1. **Fresh Start:** Visit `https://rachel-kempt-florencio.ngrok-free.dev/`
2. **Go to Home:** You should see home page (no login required)
3. **Click Register:** Create test account
4. **Login:** Enter your credentials
5. **After Login:** You go to home page (see your name in navbar)
6. **Click Appointments:** View your appointments
7. **Click Schedule:** Schedule new appointment
8. **Click Logout:** Return to home page (session cleared)

✅ **This is the correct flow!**

---

## **Troubleshooting**

### "Stuck on login page"
- Solution: Hard refresh browser (Ctrl+F5 or Cmd+Shift+R)
- If still stuck: Clear browser cookies for the domain

### "Logout button doesn't work"
- Solution: Check if you're seeing the logout button (means you're logged in)
- If not visible: You're not logged in, go to home and login first

### "Can't access appointments page"
- Solution: Make sure you're logged in first
- Check navbar - you should see your name, not login/register buttons

---

Generated: November 22, 2025
