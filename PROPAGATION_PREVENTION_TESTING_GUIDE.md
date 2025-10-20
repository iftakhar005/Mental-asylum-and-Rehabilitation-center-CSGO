# 🛡️ Propagation Prevention Testing Guide

## Overview
This guide provides step-by-step instructions for testing the **Propagation Prevention** system that includes:
1. **Session Hijacking Propagation Prevention**
2. **Privilege Escalation Propagation Prevention**

---

## ⚙️ Prerequisites

### 1. Setup Requirements
- **XAMPP** running (Apache + MySQL)
- **PHP 7.4+** installed
- Database `asylum_db` created and populated

### 2. File Verification
Ensure these files exist in your project:
- ✅ `propagation_prevention.php` - Main propagation prevention system
- ✅ `test_propagation_prevention.php` - Automated testing script
- ✅ `session_protection.php` - Session protection helper
- ✅ `security_manager.php` - Updated with propagation integration
- ✅ `index.php` - Updated login with propagation tracking

---

## 📋 Testing Methods

There are **TWO** ways to test the system:

### Method 1: Automated Testing (Recommended for Quick Verification)
### Method 2: Manual Testing (Recommended for Understanding How It Works)

---

## 🚀 Method 1: Automated Testing

### Step 1: Start XAMPP
1. Open **XAMPP Control Panel**
2. Start **Apache** server
3. Start **MySQL** server
4. Ensure both show "Running" status

### Step 2: Access Testing Script
1. Open your web browser
2. Navigate to: `http://localhost/CSGO/Mental-asylum-and-Rehabilitation-center-CSGO/test_propagation_prevention.php`

### Step 3: Review Test Results
The automated test will run **6 comprehensive tests**:

#### ✅ Test 1: Session Hijacking Detection (Fingerprint Mismatch)
- **What it tests**: Detects when session fingerprint is changed (indicating hijacking)
- **Expected result**: ✓ Session hijacking detected and blocked

#### ✅ Test 2: Session Timeout Detection
- **What it tests**: Detects and blocks expired sessions
- **Expected result**: ✓ Expired session detected and blocked

#### ✅ Test 3: Privilege Escalation Prevention (Unauthorized Access)
- **What it tests**: Prevents lower-privilege users from accessing admin resources
- **Expected result**: ✓ Privilege escalation blocked

#### ✅ Test 4: Privilege Escalation Prevention (Role Tampering)
- **What it tests**: Detects when user manually changes their role in session
- **Expected result**: ✓ Role tampering detected and blocked

#### ✅ Test 5: Multiple Privilege Escalation Attempts
- **What it tests**: Blocks users after multiple escalation attempts
- **Expected result**: ✓ Multiple attempts detected and blocked

#### ✅ Test 6: Legitimate Access
- **What it tests**: Ensures legitimate users can access their resources
- **Expected result**: ✓ Legitimate admin access allowed

### Step 4: Check Statistics
The test page displays:
- **Session Hijacking incidents (24h)**: Count of detected hijacking attempts
- **Privilege Escalation incidents (24h)**: Count of escalation attempts
- **Blocked Sessions**: Currently blocked sessions
- **Active Sessions**: Currently active sessions

### Step 5: Review Recent Incidents
Check the incidents table showing:
- Incident type (session_hijacking or privilege_escalation)
- User ID involved
- IP address
- Severity level
- Detection timestamp

---

## 🔧 Method 2: Manual Testing

### Test A: Session Hijacking Prevention

#### Step A1: Create Test User
1. Login to phpMyAdmin: `http://localhost/phpmyadmin`
2. Select database: `asylum_db`
3. Run this SQL:
```sql
INSERT INTO users (username, password_hash, email, role, first_name, last_name) 
VALUES (
    'test_admin', 
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: password
    'test_admin@test.com', 
    'admin', 
    'Test', 
    'Admin'
);
```

#### Step A2: Normal Login
1. Open browser: `http://localhost/CSGO/Mental-asylum-and-Rehabilitation-center-CSGO/`
2. Login with:
   - Email: `test_admin@test.com`
   - Password: `password`
3. You should be redirected to admin dashboard
4. ✅ **Expected**: Login successful, dashboard loads

#### Step A3: Check Session Tracking
1. Go to phpMyAdmin
2. Select `asylum_db` database
3. Run query:
```sql
SELECT * FROM session_tracking WHERE user_id = (SELECT id FROM users WHERE email = 'test_admin@test.com') ORDER BY created_at DESC LIMIT 1;
```
4. ✅ **Expected**: You should see a record with your session fingerprint

#### Step A4: Simulate Session Hijacking
1. Open browser developer tools (F12)
2. Go to **Application** tab → **Cookies**
3. Note the current `PHPSESSID` value
4. **Option 1**: Change User Agent
   - Install a User Agent Switcher extension
   - Change your browser's user agent
   - Refresh the page
5. **Option 2**: Manually tamper with session
   - Add this PHP code to test page:
   ```php
   <?php
   session_start();
   $_SESSION['propagation_fingerprint'] = 'fake_hijacked_fingerprint';
   header('Location: admin_dashboard.php');
   ?>
   ```
6. ✅ **Expected**: You should be logged out and redirected to login page

#### Step A5: Verify Incident Logged
1. Go to phpMyAdmin
2. Run query:
```sql
SELECT * FROM propagation_incidents WHERE incident_type = 'session_hijacking' ORDER BY detected_at DESC LIMIT 5;
```
3. ✅ **Expected**: You should see incident record with details

---

### Test B: Privilege Escalation Prevention

#### Step B1: Create Lower-Privilege User
1. In phpMyAdmin, run:
```sql
INSERT INTO users (username, password_hash, email, role, first_name, last_name) 
VALUES (
    'test_receptionist', 
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: password
    'test_recep@test.com', 
    'receptionist', 
    'Test', 
    'Receptionist'
);
```

#### Step B2: Login as Receptionist
1. Logout from current session
2. Login with:
   - Email: `test_recep@test.com`
   - Password: `password`
3. You should be redirected to receptionist dashboard
4. ✅ **Expected**: Login successful as receptionist

#### Step B3: Attempt to Access Admin Page
1. Try to navigate to: `http://localhost/CSGO/Mental-asylum-and-Rehabilitation-center-CSGO/admin_dashboard.php`
2. ✅ **Expected**: You should be blocked and redirected to login page

#### Step B4: Verify Privilege Escalation Logged
1. Go to phpMyAdmin
2. Run query:
```sql
SELECT * FROM privilege_escalation_tracking ORDER BY attempt_timestamp DESC LIMIT 5;
```
3. ✅ **Expected**: You should see the escalation attempt recorded

#### Step B5: Test Role Tampering
1. Login as receptionist again
2. Open browser developer tools
3. In console, run:
```javascript
// This simulates tampering (won't work in production as session is server-side)
document.cookie = "role=admin";
```
4. Try to access admin page
5. ✅ **Expected**: Access denied (system validates role from database, not session only)

---

### Test C: Session Timeout

#### Step C1: Login
1. Login with any valid account
2. Note the time

#### Step C2: Wait for Timeout
1. Wait for more than 1 hour (default timeout)
2. OR manually modify session in database:
```sql
UPDATE session_tracking 
SET created_at = DATE_SUB(NOW(), INTERVAL 2 HOUR) 
WHERE session_id = 'YOUR_SESSION_ID';
```

#### Step C3: Try to Access Page
1. Try to access any protected page
2. ✅ **Expected**: Session expired, redirected to login

---

## 📊 Database Verification

### Check Created Tables
Run in phpMyAdmin:
```sql
SHOW TABLES LIKE '%tracking%';
SHOW TABLES LIKE '%propagation%';
SHOW TABLES LIKE '%blocked%';
```

✅ **Expected tables**:
- `session_tracking`
- `privilege_escalation_tracking`
- `propagation_incidents`
- `blocked_sessions`

### View Table Structure
```sql
DESCRIBE session_tracking;
DESCRIBE privilege_escalation_tracking;
DESCRIBE propagation_incidents;
DESCRIBE blocked_sessions;
```

### Check for Data
```sql
-- Active sessions
SELECT COUNT(*) as active_sessions FROM session_tracking WHERE is_active = 1;

-- Recent incidents
SELECT COUNT(*) as incidents_24h FROM propagation_incidents WHERE detected_at > DATE_SUB(NOW(), INTERVAL 24 HOUR);

-- Blocked sessions
SELECT COUNT(*) as blocked FROM blocked_sessions WHERE is_active = 1 AND expires_at > NOW();

-- Privilege attempts
SELECT COUNT(*) as attempts FROM privilege_escalation_tracking WHERE attempt_timestamp > DATE_SUB(NOW(), INTERVAL 24 HOUR);
```

---

## 🎯 Key Features Demonstrated

### 1. Session Hijacking Prevention ✅
- **Fingerprint-based detection**: Uses IP, User Agent, Accept-Language, Accept-Encoding
- **Custom hash function**: Manual implementation without built-in hash functions
- **Session rotation**: Periodic session ID regeneration
- **Incident logging**: All attempts logged with details
- **Automatic blocking**: Hijacked sessions immediately terminated

### 2. Privilege Escalation Prevention ✅
- **Role hierarchy enforcement**: Admin > Chief Staff > Doctor > Therapist > Nurse > Receptionist
- **Database verification**: Always validates role from database, not just session
- **Tampering detection**: Detects if user modifies their role in session
- **Attempt tracking**: Logs all unauthorized access attempts
- **Progressive blocking**: Blocks user after multiple attempts

---

## 🚨 Common Issues & Solutions

### Issue 1: "Table doesn't exist" error
**Solution**: 
1. Check if tables were created
2. Run the test script once - it auto-creates tables
3. Verify database connection in `db.php`

### Issue 2: "Session already started" warning
**Solution**: 
1. Clear browser cookies
2. Restart Apache server
3. Clear PHP session files

### Issue 3: All tests fail
**Solution**:
1. Verify database connection
2. Check PHP version (needs 7.4+)
3. Ensure `session_start()` is working
4. Check file permissions

### Issue 4: Can't access any page after testing
**Solution**:
```php
// Create reset_session.php
<?php
session_start();
session_unset();
session_destroy();
echo "Session cleared. <a href='index.php'>Go to login</a>";
?>
```

---

## 📈 Performance Metrics

Monitor these metrics:
- **Response Time**: Should add < 50ms overhead
- **Database Queries**: +2 queries per page load (acceptable)
- **False Positives**: Should be 0% for legitimate users
- **Detection Rate**: Should be 100% for attack simulations

---

## 🔐 Security Best Practices

1. **Regular Monitoring**: Check incidents daily
2. **Clean Old Data**: Archive old tracking data monthly
3. **Update Fingerprints**: Add more entropy points if needed
4. **Review Blocked Sessions**: Investigate patterns
5. **User Education**: Notify users of security features

---

## 📝 Test Checklist

Use this checklist during testing:

### Automated Tests
- [ ] All 6 tests pass
- [ ] Statistics display correctly
- [ ] Incidents are logged
- [ ] No PHP errors in console

### Manual Tests - Session Hijacking
- [ ] Normal login works
- [ ] Session tracking recorded
- [ ] Fingerprint mismatch detected
- [ ] Session blocked after hijacking
- [ ] Incident logged in database

### Manual Tests - Privilege Escalation
- [ ] Lower privilege user created
- [ ] Admin page access blocked
- [ ] Role tampering detected
- [ ] Attempts logged
- [ ] Multiple attempts trigger ban

### Database Verification
- [ ] All 4 tables created
- [ ] Data being inserted correctly
- [ ] Indexes working properly
- [ ] Old data cleanup working

---

## 🎓 Understanding the Implementation

### No External Libraries
This implementation is **100% manual**:
- ❌ No `hash()` function
- ❌ No `password_hash()` for fingerprints
- ❌ No security libraries
- ✅ Custom hash algorithm
- ✅ Manual fingerprint generation
- ✅ Pure PHP logic

### How Session Fingerprint Works
```php
// Components used:
1. IP Address ($_SERVER['REMOTE_ADDR'])
2. User Agent ($_SERVER['HTTP_USER_AGENT'])
3. Accept Language ($_SERVER['HTTP_ACCEPT_LANGUAGE'])
4. Accept Encoding ($_SERVER['HTTP_ACCEPT_ENCODING'])

// Custom hash algorithm:
- Concatenates all components
- Creates 32-bit integer hash
- Converts to hexadecimal
- Stores in session for comparison
```

### How Privilege Escalation is Prevented
```php
// Role Hierarchy (lower number = higher privilege)
admin = 1
chief-staff = 2
doctor = 3
therapist = 4
nurse = 5
receptionist = 6

// Validation Process:
1. Check session role vs required role
2. Verify against database (prevents tampering)
3. Log any mismatch
4. Block after multiple attempts
```

---

## 📞 Support

If you encounter issues:
1. Check PHP error logs
2. Check Apache error logs
3. Review database for errors
4. Verify all files are uploaded
5. Clear browser cache and cookies

---

## ✅ Success Criteria

Your implementation is successful if:
1. ✅ All automated tests PASS
2. ✅ Session hijacking is detected and blocked
3. ✅ Privilege escalation is prevented
4. ✅ Legitimate users can access their resources
5. ✅ All incidents are logged
6. ✅ No external libraries used

---

## 🎉 Congratulations!

If all tests pass, you have successfully implemented:
- ✅ Session Hijacking Propagation Prevention
- ✅ Privilege Escalation Propagation Prevention
- ✅ Manual implementation without external libraries
- ✅ Comprehensive logging and monitoring

**Your system is now protected against propagation attacks!** 🛡️
