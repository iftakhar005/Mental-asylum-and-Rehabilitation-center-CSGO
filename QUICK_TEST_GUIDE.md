# 🚀 Quick Testing Guide - Propagation Prevention

## ⚡ Fast Track Testing (5 Minutes)

### Step 1: Start XAMPP ✅
```
1. Open XAMPP Control Panel
2. Start Apache
3. Start MySQL
```

### Step 2: Run Automated Tests ✅
```
Open browser:
http://localhost/CSGO/Mental-asylum-and-Rehabilitation-center-CSGO/test_propagation_prevention.php
```

### Step 3: Verify Results ✅
**All 6 tests should PASS:**
- ✓ Session Hijacking Detection (Fingerprint Mismatch)
- ✓ Session Timeout Detection  
- ✓ Privilege Escalation Prevention (Unauthorized Access)
- ✓ Privilege Escalation Prevention (Role Tampering)
- ✓ Multiple Privilege Escalation Attempts
- ✓ Legitimate Access

---

## 🔍 Manual Testing (15 Minutes)

### Test 1: Session Hijacking
```
1. Login to the system
2. Open Developer Tools (F12)
3. Go to Application → Storage → Session Storage
4. Note session fingerprint
5. Change User Agent in browser
6. Refresh page
✅ EXPECTED: Logged out automatically
```

### Test 2: Privilege Escalation
```
1. Login as receptionist (create one if needed)
2. Try to access: admin_dashboard.php
✅ EXPECTED: Access denied, redirected to login
```

### Test 3: Role Tampering
```
1. Login as any user
2. Developer Tools → Console
3. Type: document.cookie = "role=admin"
4. Try to access admin page
✅ EXPECTED: Blocked (role verified from database)
```

---

## 📊 View Results in Database

### Open phpMyAdmin:
```
http://localhost/phpmyadmin
```

### Run These Queries:

**1. View Session Hijacking Incidents**
```sql
SELECT * FROM propagation_incidents 
WHERE incident_type = 'session_hijacking' 
ORDER BY detected_at DESC LIMIT 10;
```

**2. View Privilege Escalation Attempts**
```sql
SELECT * FROM privilege_escalation_tracking 
ORDER BY attempt_timestamp DESC LIMIT 10;
```

**3. View Active Sessions**
```sql
SELECT * FROM session_tracking 
WHERE is_active = 1 
ORDER BY last_activity DESC;
```

**4. View Blocked Sessions**
```sql
SELECT * FROM blocked_sessions 
WHERE is_active = 1 AND expires_at > NOW();
```

---

## 🎯 Key URLs

| Purpose | URL |
|---------|-----|
| Login Page | `http://localhost/CSGO/Mental-asylum-and-Rehabilitation-center-CSGO/` |
| Automated Tests | `http://localhost/CSGO/Mental-asylum-and-Rehabilitation-center-CSGO/test_propagation_prevention.php` |
| Admin Dashboard | `http://localhost/CSGO/Mental-asylum-and-Rehabilitation-center-CSGO/admin_dashboard.php` |
| phpMyAdmin | `http://localhost/phpmyadmin` |

---

## 🆘 Quick Fixes

### Problem: Can't login after testing
**Solution:**
```
Create file: reset_session.php
Content:
<?php
session_start();
session_destroy();
header('Location: index.php');
?>
```

### Problem: Tables not created
**Solution:**
1. Open test_propagation_prevention.php once
2. Tables auto-create on first run

### Problem: All tests fail
**Solution:**
1. Check database connection in db.php
2. Verify XAMPP is running
3. Clear browser cookies

---

## ✅ Success Indicators

You'll know it's working when:
- ✅ Automated tests show 6/6 PASSED
- ✅ Statistics show incident counts
- ✅ Database tables have data
- ✅ You get logged out when fingerprint changes
- ✅ Lower privilege users can't access admin pages

---

## 📋 Test Checklist

Quick checklist for presentation/demo:

- [ ] XAMPP running
- [ ] Database connected
- [ ] Test page loads without errors
- [ ] All 6 tests PASS
- [ ] Statistics display correctly
- [ ] Database tables populated
- [ ] Session hijacking blocked
- [ ] Privilege escalation blocked
- [ ] Incidents logged properly

---

## 🎓 What This System Does

### Session Hijacking Prevention:
- Creates unique fingerprint for each session
- Monitors IP, User Agent, Language, Encoding
- Detects if fingerprint changes (hijacking)
- Immediately blocks and logs incident
- Rotates session ID every 15 minutes

### Privilege Escalation Prevention:
- Enforces role hierarchy
- Validates roles from database (not just session)
- Detects role tampering attempts
- Blocks unauthorized page access
- Bans users after multiple attempts

### Manual Implementation:
- ❌ No external libraries
- ❌ No built-in hash functions for fingerprints
- ✅ 100% custom code
- ✅ Pure PHP logic

---

## 🚀 Ready to Test?

1. **Start XAMPP**
2. **Open test page**
3. **Watch tests run**
4. **Verify all PASS**
5. **Check database for incidents**

**That's it! You're done!** 🎉

---

## 📞 Need Help?

1. Check `PROPAGATION_PREVENTION_TESTING_GUIDE.md` for detailed steps
2. Review PHP error logs
3. Check Apache error logs
4. Verify database structure
5. Clear cookies and try again
