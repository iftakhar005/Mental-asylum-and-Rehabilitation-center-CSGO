# 🎯 STEP-BY-STEP TESTING INSTRUCTIONS

## 🚀 Complete Testing Process

---

## STEP 1: Verify XAMPP is Running ✅

### Action:
1. Open **XAMPP Control Panel**
2. Check if **Apache** shows "Running" (green)
3. Check if **MySQL** shows "Running" (green)

### If NOT running:
1. Click **Start** next to Apache
2. Click **Start** next to MySQL
3. Wait for both to show "Running"

✅ **Success Criteria**: Both Apache and MySQL are green/running

---

## STEP 2: Run Automated Tests ✅

### Action:
1. Open your web browser (Chrome, Firefox, Edge)
2. Type in address bar:
   ```
   http://localhost/CSGO/Mental-asylum-and-Rehabilitation-center-CSGO/test_propagation_prevention.php
   ```
3. Press Enter
4. Wait for tests to complete (5-10 seconds)

### What You Should See:
- **Title**: "🛡️ Propagation Prevention Testing Suite"
- **6 Tests Running** with visual feedback
- **Test Summary** showing PASSED/FAILED
- **Statistics** section
- **Recent Incidents** table

✅ **Success Criteria**: All 6 tests show "✓ PASSED"

### Expected Test Results:

#### Test 1: Session Hijacking Detection ✓
- Creates a session
- Changes fingerprint
- Detects change
- **Result**: ✓ PASSED (Session hijacking detected and blocked)

#### Test 2: Session Timeout ✓
- Creates a session
- Simulates old session
- Detects timeout
- **Result**: ✓ PASSED (Expired session detected and blocked)

#### Test 3: Privilege Escalation (Unauthorized) ✓
- Creates receptionist user
- Tries to access admin resources
- Blocks access
- **Result**: ✓ PASSED (Privilege escalation blocked)

#### Test 4: Privilege Escalation (Tampering) ✓
- Creates nurse user
- Tampers with role in session
- Detects tampering
- **Result**: ✓ PASSED (Role tampering detected and blocked)

#### Test 5: Multiple Attempts ✓
- Makes 5 escalation attempts
- Blocks after threshold
- **Result**: ✓ PASSED (Multiple attempts detected and blocked)

#### Test 6: Legitimate Access ✓
- Creates admin user
- Allows legitimate access
- **Result**: ✓ PASSED (Legitimate admin access allowed)

---

## STEP 3: View Interactive Demo ✅

### Action:
1. Open new browser tab
2. Type in address bar:
   ```
   http://localhost/CSGO/Mental-asylum-and-Rehabilitation-center-CSGO/propagation_demo.php
   ```
3. Press Enter

### What to Do:

#### A. Session Hijacking Demo:
1. Click **"1. Create Session"**
   - Should see: ✅ Session created successfully
   - Note the fingerprint value

2. Click **"2. Simulate Hijacking"**
   - Should see: ⚠️ Session hijacked! Fingerprint changed
   - Fingerprint value should be different

3. Click **"3. Validate Session"**
   - Should see: ❌ Session validation failed! Possible hijacking detected.

✅ **Success**: Hijacking detected and blocked!

#### B. Privilege Escalation Demo:
1. Click **"1. Create Session (Doctor)"**
   - Should see: ✅ Session created successfully
   - Role should show: doctor

2. Click **"2. Escalate to Admin"**
   - Should see: ⚠️ Privilege escalation attempted! Role changed
   - Role changed to admin in session

3. Click **"3. Try Access Admin"**
   - Should see: ❌ Access denied! Privilege escalation blocked.

✅ **Success**: Escalation detected and blocked!

---

## STEP 4: Verify Database Tables ✅

### Action:
1. Open browser
2. Go to: `http://localhost/phpmyadmin`
3. Click on **asylum_db** database (left sidebar)
4. Look for these tables:

#### Required Tables:
- ✅ `session_tracking`
- ✅ `privilege_escalation_tracking`
- ✅ `propagation_incidents`
- ✅ `blocked_sessions`

### View Table Data:

#### A. Check Session Tracking:
1. Click on `session_tracking` table
2. Click **"Browse"** tab
3. You should see records of test sessions

#### B. Check Incidents:
1. Click on `propagation_incidents` table
2. Click **"Browse"** tab
3. You should see logged security incidents

#### C. Check Escalation Attempts:
1. Click on `privilege_escalation_tracking` table
2. Click **"Browse"** tab
3. You should see logged escalation attempts

✅ **Success**: All tables exist and contain data

---

## STEP 5: Test with Real Login ✅

### Action:
1. Go to: `http://localhost/CSGO/Mental-asylum-and-Rehabilitation-center-CSGO/`
2. Login with existing credentials
3. Access your dashboard

### What Should Happen:
- Login succeeds normally
- Dashboard loads
- Session is being tracked
- No errors displayed

### Verify Tracking:
1. Go to phpMyAdmin
2. Run this query in SQL tab:
```sql
SELECT * FROM session_tracking 
WHERE is_active = 1 
ORDER BY created_at DESC 
LIMIT 5;
```
3. You should see your current session

✅ **Success**: Your session is being tracked

---

## STEP 6: Manual Session Hijacking Test ✅

### Action:
1. Login to the system
2. Note your session is working
3. Open **Developer Tools** (F12)
4. Go to **Application** tab
5. Go to **Cookies** → Select your site
6. Find `PHPSESSID` cookie
7. Note the value

### Simulate Hijacking:
1. Install browser extension: "User-Agent Switcher"
2. Change your User Agent to a different browser
3. Refresh the page

### Expected Result:
- ❌ You should be logged out
- 🔄 Redirected to login page
- 📝 Incident logged in database

### Verify:
```sql
SELECT * FROM propagation_incidents 
WHERE incident_type = 'session_hijacking' 
ORDER BY detected_at DESC 
LIMIT 1;
```

✅ **Success**: Hijacking detected and logged

---

## STEP 7: Manual Privilege Escalation Test ✅

### Prerequisites:
Create a test user with lower privileges:

```sql
INSERT INTO users (username, password_hash, email, role, first_name, last_name) 
VALUES (
    'test_recep', 
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'recep@test.com', 
    'receptionist', 
    'Test', 
    'Receptionist'
);
```

### Action:
1. Logout from current session
2. Login as: `recep@test.com` / password: `password`
3. You should land on receptionist dashboard
4. Try to access admin dashboard:
   ```
   http://localhost/CSGO/Mental-asylum-and-Rehabilitation-center-CSGO/admin_dashboard.php
   ```

### Expected Result:
- ❌ Access denied
- 🔄 Redirected to login page
- 📝 Escalation attempt logged

### Verify:
```sql
SELECT * FROM privilege_escalation_tracking 
ORDER BY attempt_timestamp DESC 
LIMIT 1;
```

✅ **Success**: Escalation blocked and logged

---

## 📊 FINAL VERIFICATION CHECKLIST

Use this checklist to confirm everything works:

### Files Created:
- [ ] `propagation_prevention.php` exists
- [ ] `test_propagation_prevention.php` exists
- [ ] `session_protection.php` exists
- [ ] `propagation_demo.php` exists
- [ ] All documentation files exist

### Database:
- [ ] `session_tracking` table exists
- [ ] `privilege_escalation_tracking` table exists
- [ ] `propagation_incidents` table exists
- [ ] `blocked_sessions` table exists
- [ ] Tables contain data

### Automated Tests:
- [ ] Test 1 (Session Hijacking Detection) - PASSED
- [ ] Test 2 (Session Timeout) - PASSED
- [ ] Test 3 (Privilege Escalation Unauthorized) - PASSED
- [ ] Test 4 (Privilege Escalation Tampering) - PASSED
- [ ] Test 5 (Multiple Attempts) - PASSED
- [ ] Test 6 (Legitimate Access) - PASSED

### Manual Tests:
- [ ] Session hijacking detected when fingerprint changes
- [ ] Privilege escalation blocked for lower roles
- [ ] Normal users can access their dashboards
- [ ] All incidents logged in database

### Integration:
- [ ] Login creates session tracking
- [ ] Admin dashboard uses session protection
- [ ] Sessions expire after timeout
- [ ] Session IDs rotate periodically

---

## 🎉 YOU'RE DONE!

If all checkboxes are checked:
- ✅ Session Hijacking Prevention: **WORKING**
- ✅ Privilege Escalation Prevention: **WORKING**
- ✅ Manual Implementation: **COMPLETE**
- ✅ Testing: **SUCCESSFUL**

---

## 📸 Screenshots to Take (for presentation/documentation)

1. **Automated Test Results** - All 6 tests passing
2. **Interactive Demo** - Session hijacking detected
3. **Interactive Demo** - Privilege escalation blocked
4. **Database Tables** - session_tracking with records
5. **Database Incidents** - propagation_incidents table
6. **Statistics** - Dashboard showing counts

---

## 🆘 TROUBLESHOOTING

### Problem: Page doesn't load
**Solution:**
1. Check XAMPP is running
2. Verify URL is correct
3. Check file exists in directory

### Problem: Database error
**Solution:**
1. Check MySQL is running
2. Verify `db.php` has correct credentials
3. Run automated test once (auto-creates tables)

### Problem: All tests fail
**Solution:**
1. Clear browser cookies
2. Restart Apache in XAMPP
3. Check PHP error log

### Problem: Can't login after tests
**Solution:**
Create `reset.php`:
```php
<?php
session_start();
session_destroy();
header('Location: index.php');
?>
```
Access it, then try login again.

---

## 📞 QUICK HELP

| Issue | File to Check | Solution |
|-------|---------------|----------|
| Tests don't run | `test_propagation_prevention.php` | Verify file uploaded |
| Database tables missing | Run test page | Tables auto-create |
| Login not working | `index.php` | Check integration code |
| Sessions not tracked | `security_manager.php` | Verify propagation init |
| Can't access dashboards | `session_protection.php` | Check include path |

---

## ✅ FINAL CONFIRMATION

Run this SQL to get a summary:

```sql
-- Get overall statistics
SELECT 
    'Session Hijacking Incidents' as metric,
    COUNT(*) as count
FROM propagation_incidents 
WHERE incident_type = 'session_hijacking'
UNION ALL
SELECT 
    'Privilege Escalation Attempts' as metric,
    COUNT(*) as count
FROM privilege_escalation_tracking
UNION ALL
SELECT 
    'Active Sessions' as metric,
    COUNT(*) as count
FROM session_tracking 
WHERE is_active = 1
UNION ALL
SELECT 
    'Blocked Sessions' as metric,
    COUNT(*) as count
FROM blocked_sessions 
WHERE is_active = 1;
```

This should show:
- Some hijacking incidents (from tests)
- Some escalation attempts (from tests)
- Active sessions count
- Blocked sessions count

---

**CONGRATULATIONS! 🎊**

Your Propagation Prevention system is fully implemented and tested!
