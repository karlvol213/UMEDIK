# Quick Setup: Patient Card Persistence

## ⚡ TL;DR - Do This First

### Step 1: Run the Migration (Choose ONE method)

#### **Method 1: Web-Based (EASIEST)** ✅ Recommended
1. Make sure you're logged in as admin
2. Go to: `http://localhost/project_HCI/clean/migrations/run_pending_notes_migration_web.php`
3. You should see a success message
4. Done! ✅

#### **Method 2: phpMyAdmin**
1. Open phpMyAdmin → Select your database
2. Click "SQL" tab
3. Paste this:
```sql
CREATE TABLE IF NOT EXISTS pending_biometric_notes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    biometric_id INT NOT NULL UNIQUE,
    user_id INT NOT NULL,
    recorded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_by_admin_id INT,
    is_processed BOOLEAN DEFAULT FALSE,
    notes_recorded_at DATETIME,
    FOREIGN KEY (biometric_id) REFERENCES biometrics(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by_admin_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX (user_id),
    INDEX (recorded_at),
    INDEX (is_processed)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```
4. Click "Go"
5. Done! ✅

---

## ✨ What Changed?

Your patient cards now **persist across logout/login**! 

| Before | After |
|--------|-------|
| Record biometric → Log out → Log in → Card is gone ❌ | Record biometric → Log out → Log in → Card is still there ✅ |

---

## 🧪 Test It

1. Go to **Biometrics** page → Record a patient's vitals
2. **Log out**
3. **Log back in**
4. Go to **Patient Notes** → Patient card should still be visible!
5. Record clinical notes → Card disappears (marked as complete)

---

## 📋 What's New?

- New database table: `pending_biometric_notes`
- Tracks which patients need clinical notes recorded
- Automatically cleaned up when notes are saved
- Persists across all logout/login cycles

---

## Need Help?

- See full docs: `PATIENT_CARD_PERSISTENCE_FIX.md`
- Check logs if something breaks: `admin/logs/biometric_errors.log`
- Rollback: Drop the table and revert code changes

---

**That's it! Your system is now ready.** 🎉
