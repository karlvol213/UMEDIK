# Patient Card Persistence Fix - Implementation Summary

## Problem
When you record biometrics for a patient and then accidentally log out, the patient card disappears upon login. The card data was only stored in PHP session memory, which is destroyed on logout.

## Solution
Implement database persistence for pending biometric records so they survive logout/login cycles.

## Changes Made

### 1. **New Database Table** (`pending_biometric_notes`)
   - **Location**: Created via migration
   - **Purpose**: Stores pending biometrics that haven't had clinical notes recorded yet
   - **Fields**:
     - `id`: Primary key
     - `biometric_id`: Foreign key to biometrics table (unique, ensures no duplicates)
     - `user_id`: Foreign key to users table
     - `recorded_at`: Timestamp when biometric was marked pending (auto-set)
     - `created_by_admin_id`: Admin who recorded the biometric
     - `is_processed`: Boolean flag (0 = pending, 1 = clinical notes recorded)
     - `notes_recorded_at`: Timestamp when clinical notes were saved
   - **Indexes**: user_id, recorded_at, is_processed (for fast queries)

### 2. **Updated `info_admin.php`** (Biometrics Recording Page)
   - **Change**: When a biometric is saved, it now stores a record in `pending_biometric_notes` table
   - **Logic**:
     - Session storage still happens (for immediate UI display)
     - Database storage also happens (for persistence)
     - Checks for duplicates to prevent duplicate entries
   - **Code Location**: Lines 163-185 (after biometric save)

### 3. **Updated `patient_notes.php`** (Patient Notes Recording Page)
   - **Change #1**: Fetch pending biometrics from database on page load
     - Queries `pending_biometric_notes` table for all unprocessed biometrics (is_processed = 0)
     - Combines with session data to show all pending records
   
   - **Change #2**: Mark biometrics as processed when clinical notes are saved
     - When user records diagnosis/interview/recommendations, the biometric is marked as processed
     - Updates `is_processed = 1` and sets `notes_recorded_at` timestamp
     - Removes from session

### 4. **Migration Files Created**
   - `migrations/create_pending_notes_table.sql`: Raw SQL for manual creation
   - `migrations/run_pending_notes_migration.php`: CLI migration script
   - `migrations/run_pending_notes_migration_web.php`: **Web-accessible migration runner**

## Setup Instructions

### Option A: Web-Based Setup (Recommended)
1. Log in as admin/super admin
2. Visit: `http://localhost/project_HCI/clean/migrations/run_pending_notes_migration_web.php`
3. The table will be created automatically
4. You'll see a success message

### Option B: Manual SQL
1. Open phpMyAdmin
2. Select your database
3. Go to SQL tab
4. Copy and paste the SQL from `migrations/create_pending_notes_table.sql`
5. Execute

### Option C: CLI (If PHP CLI is configured)
```bash
cd c:\xampp\htdocs\project_HCI\clean
php migrations/run_pending_notes_migration.php
```

## How It Works (User Flow)

### Recording Biometrics
1. Admin records patient biometrics in **Biometrics** page
2. Biometric is saved to `biometrics` table
3. Entry is added to `pending_biometric_notes` table (is_processed = 0)
4. Session stores the record for immediate display

### Logout/Login
1. User logs out → session is destroyed
2. **Previously**: Patient card disappeared (session data lost)
3. **Now**: Patient card persists because it's stored in database

### Recording Clinical Notes
1. Admin navigates to **Patient Notes** page
2. Page loads pending biometrics from database
3. Admin enters diagnosis, interview, and recommendations
4. Notes are saved to `patient_history_records` table
5. Biometric is marked as processed: `is_processed = 1` in `pending_biometric_notes`
6. Patient card is removed from "Recently Recorded Biometrics" section

## Benefits

✅ **Persistent across logout/login** - No more lost patient data  
✅ **Safe deletion** - Marked as processed instead of hard deleted  
✅ **Audit trail** - Timestamps show when recorded and when processed  
✅ **No duplicate records** - UNIQUE constraint on biometric_id  
✅ **Admin tracking** - Records who recorded the biometric  
✅ **Backward compatible** - Session still works as fallback  

## Data Flow Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                    BIOMETRICS PAGE (info_admin.php)             │
│                                                                  │
│  Admin records patient vitals                                   │
│         ↓                                                        │
│  Saved to: biometrics table                                     │
│         ↓                                                        │
│  ├─→ $_SESSION['recorded_biometrics'] (session memory)          │
│  └─→ pending_biometric_notes (is_processed = 0)  ← NEW          │
└─────────────────────────────────────────────────────────────────┘
                         ↓
              [Admin logs out/in cycle]
                         ↓
┌─────────────────────────────────────────────────────────────────┐
│                   PATIENT NOTES PAGE (patient_notes.php)        │
│                                                                  │
│  Page loads pending biometrics from:                            │
│  ├─→ $_SESSION['recorded_biometrics'] (if still in session)     │
│  └─→ pending_biometric_notes table  ← PERSISTS ACROSS LOGOUT   │
│         ↓                                                        │
│  Admin records diagnosis, interview, recommendations            │
│         ↓                                                        │
│  Saved to: patient_history_records table                        │
│         ↓                                                        │
│  Mark as processed: pending_biometric_notes.is_processed = 1    │
└─────────────────────────────────────────────────────────────────┘
```

## Testing the Fix

1. **Record a biometric** in the Biometrics page for a patient
2. **Log out** (the critical step!)
3. **Log back in** as the same admin
4. **Go to Patient Notes page**
   - ✅ The patient card should still be there!
5. **Record the clinical notes** for that patient
6. **Refresh the page** or log out/in again
   - ✅ The patient card should now be gone (marked as processed)

## Rollback Instructions

If you need to revert this change:
1. Drop the table: `DROP TABLE pending_biometric_notes;`
2. Remove the code additions from `info_admin.php` and `patient_notes.php`
3. Revert to session-only storage

## Files Modified

| File | Changes |
|------|---------|
| `admin/info_admin.php` | Added database storage for pending biometrics (lines 163-185) |
| `admin/patient_notes.php` | Added database loading (lines 202-224) and marking as processed (lines 152-160) |
| `migrations/create_pending_notes_table.sql` | NEW - SQL table definition |
| `migrations/run_pending_notes_migration.php` | NEW - CLI migration script |
| `migrations/run_pending_notes_migration_web.php` | NEW - Web migration runner |

## Performance Impact

- **Minimal**: The pending_biometric_notes table has indexes on frequently queried columns (user_id, recorded_at, is_processed)
- **Query optimization**: Queries fetch only unprocessed records, limiting result set size
- **No impact on existing queries**: New table is optional; biometrics table unchanged

---

**Status**: ✅ Ready to use  
**Setup Required**: Create table via migration (see Setup Instructions above)  
**Last Updated**: November 23, 2025
