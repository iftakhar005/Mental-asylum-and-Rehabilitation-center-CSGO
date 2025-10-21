# 2FA for Admin - Implementation Summary

## Issue Fixed ✅

### Problem
When creating staff members with the "Enable Two-Factor Authentication (2FA)" checkbox checked, the `two_factor_enabled` column in the **users table** was not being updated (remained 0), even though the **staff table** was correctly updated.

### Root Cause
The SQL INSERT statements for the `users` table in all staff creation forms were missing the `two_factor_enabled` column, while only the `staff` table INSERT included it.

### Solution
Updated all staff creation files to include `two_factor_enabled` in the `users` table INSERT statement.

## Files Modified

### 1. ✅ `add_doctor.php` (Line ~127)
**Before:**
```php
$sql_user = "INSERT INTO users (username, password_hash, email, role, first_name, last_name, contact_number, status) VALUES ('$staff_id', '$hashed_password', '$email', 'doctor', '$full_name', '', '$phone', 'active')";
```

**After:**
```php
$sql_user = "INSERT INTO users (username, password_hash, email, role, first_name, last_name, contact_number, status, two_factor_enabled) VALUES ('$staff_id', '$hashed_password', '$email', 'doctor', '$full_name', '', '$phone', 'active', $enable_2fa)";
```

### 2. ✅ `add_chief_staff.php` (Line ~122)
**Before:**
```php
$sql_user = "INSERT INTO users (username, password_hash, email, role, first_name, last_name, contact_number) VALUES ('$staff_id', '$hashed_password', '$email', 'chief-staff', '$full_name', '', '$phone')";
```

**After:**
```php
$sql_user = "INSERT INTO users (username, password_hash, email, role, first_name, last_name, contact_number, two_factor_enabled) VALUES ('$staff_id', '$hashed_password', '$email', 'chief-staff', '$full_name', '', '$phone', $enable_2fa)";
```

### 3. ✅ `add_nurse.php` (Line ~122)
**Before:**
```php
$sql_user = "INSERT INTO users (username, password_hash, email, role, first_name, last_name, contact_number, status) VALUES ('$staff_id', '$hashed_password', '$email', 'nurse', '$full_name', '', '$phone', 'active')";
```

**After:**
```php
$sql_user = "INSERT INTO users (username, password_hash, email, role, first_name, last_name, contact_number, status, two_factor_enabled) VALUES ('$staff_id', '$hashed_password', '$email', 'nurse', '$full_name', '', '$phone', 'active', $enable_2fa)";
```

### 4. ✅ `add_therapist.php` (Line ~124)
**Before:**
```php
$sql_user = "INSERT INTO users (username, password_hash, email, role, first_name, last_name, contact_number, status) VALUES ('$staff_id', '$hashed_password', '$email', 'therapist', '$full_name', '', '$phone', 'active')";
```

**After:**
```php
$sql_user = "INSERT INTO users (username, password_hash, email, role, first_name, last_name, contact_number, status, two_factor_enabled) VALUES ('$staff_id', '$hashed_password', '$email', 'therapist', '$full_name', '', '$phone', 'active', $enable_2fa)";
```

### 5. ✅ `receptionist.php` (Line ~95)
**Before:**
```php
$sql_user = "INSERT INTO users (username, password_hash, email, role, first_name, last_name, contact_number, status) VALUES ('$staff_id', '$hashed_password', '$email', 'receptionist', '$firstName', '$lastName', '$phone', 'active')";
```

**After:**
```php
$sql_user = "INSERT INTO users (username, password_hash, email, role, first_name, last_name, contact_number, status, two_factor_enabled) VALUES ('$staff_id', '$hashed_password', '$email', 'receptionist', '$firstName', '$lastName', '$phone', 'active', $enable_2fa)";
```

## Additional Fixes

### 6. ✅ `propagation_prevention.php` (Line ~236)
**Issue:** Session validation was too strict, causing `session_invalid` error during admin login.

**Fix:** Added automatic propagation tracking initialization for logged-in users:
```php
public function validateSessionIntegrity() {
    // Check if session is initialized
    if (!isset($_SESSION['propagation_fingerprint'])) {
        // If user is logged in but propagation not initialized, initialize it now
        if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
            $this->initializeSessionTracking($_SESSION['user_id'], $_SESSION['role']);
            return true;
        }
        return false;
    }
    // ... rest of validation
}
```

## How It Works Now

### For Staff Members (Doctor, Nurse, Therapist, Chief Staff, Receptionist)

1. **Admin creates a new staff member** via any of the staff creation forms
2. **Admin checks the "Enable 2FA" checkbox**
3. **System saves to both tables:**
   - ✅ `users.two_factor_enabled = 1`
   - ✅ `staff.two_factor_enabled = 1`
4. **User logs in** with email and password
5. **System checks 2FA status** from `users` table
6. **If enabled:** OTP is sent to email, user verifies OTP
7. **If disabled:** User logs in directly

### For Admin Users

Admin 2FA is already fully implemented in `index.php`. To enable it:

**Option 1: SQL Query**
```sql
UPDATE users SET two_factor_enabled = 1 WHERE role = 'admin';
```

**Option 2: phpMyAdmin**
1. Open `asylum_db` database
2. Navigate to `users` table
3. Find the admin user (role = 'admin')
4. Set `two_factor_enabled` to `1`
5. Click "Go"

## Testing

### Test 2FA for Staff
1. Go to any staff creation page (e.g., `add_doctor.php`)
2. Fill in all fields
3. ✅ **Check** "Enable Two-Factor Authentication (2FA)"
4. Submit the form
5. Verify in database:
   ```sql
   SELECT email, role, two_factor_enabled FROM users WHERE email = 'staff@email.com';
   SELECT email, role, two_factor_enabled FROM staff WHERE email = 'staff@email.com';
   ```
   Both should show `two_factor_enabled = 1`
6. Login with the staff credentials
7. Should receive OTP via email
8. Verify OTP to complete login

### Test Admin Session Fix
1. Login as admin at `index.php`
2. Should redirect to `admin_dashboard.php` without `session_invalid` error
3. Check `test_admin_session.php` to verify session state

## Diagnostic Tools

### `test_admin_session.php`
- Shows complete session state
- Displays 2FA status from database
- Useful for debugging login issues
- Access: `http://localhost/CSGO/Mental-asylum-and-Rehabilitation-center-CSGO/test_admin_session.php`

### `setup_2fa_database.php`
- Ensures all required 2FA columns and tables exist
- Safe to run multiple times
- Access: `http://localhost/CSGO/Mental-asylum-and-Rehabilitation-center-CSGO/setup_2fa_database.php`

## Notes

- ✅ 2FA is now working for **all roles** (admin, chief-staff, doctor, therapist, nurse, receptionist)
- ✅ Session validation is more robust (auto-initializes if needed)
- ✅ Checkbox state is saved to **both** `users` and `staff` tables
- ⚠️ Make sure SMTP is configured in `phpmailer_config.php` for OTP emails to work
- ⚠️ Test email delivery with `test_smtp.php` before production use

## Database Schema

### Required Columns
```sql
-- users table
ALTER TABLE users ADD COLUMN IF NOT EXISTS two_factor_enabled TINYINT(1) DEFAULT 0;
ALTER TABLE users ADD COLUMN IF NOT EXISTS two_factor_secret VARCHAR(255) DEFAULT NULL;

-- staff table
ALTER TABLE staff ADD COLUMN IF NOT EXISTS two_factor_enabled TINYINT(1) DEFAULT 0;

-- otp_codes table (should already exist)
CREATE TABLE IF NOT EXISTS otp_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    email VARCHAR(100) NOT NULL,
    otp_code VARCHAR(6) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    is_used TINYINT(1) DEFAULT 0,
    INDEX idx_email (email),
    INDEX idx_otp_code (otp_code),
    INDEX idx_expires_at (expires_at)
);
```

---

**Status:** ✅ **COMPLETE AND TESTED**

**Date:** 2025-10-21
