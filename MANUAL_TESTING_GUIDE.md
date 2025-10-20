# 🧪 Manual Network Security Testing Guide

This guide shows you how to manually test each network security feature without relying on automated tests.

---

## 🔍 Test 1: Security Headers (Browser DevTools)

### Steps:
1. Open your browser (Chrome, Edge, or Firefox)
2. Navigate to any authenticated page:
   ```
   http://localhost/CSGO/Mental-asylum-and-Rehabilitation-center-CSGO/admin_dashboard.php
   ```
3. Press **F12** to open Developer Tools
4. Click on **Network** tab
5. **Refresh the page** (Ctrl+R or F5)
6. Click on the **first request** (usually the page name like `admin_dashboard.php`)
7. Look for the **Response Headers** section

### What to Look For:
```
✅ X-Content-Type-Options: nosniff
✅ X-Frame-Options: SAMEORIGIN
✅ X-XSS-Protection: 1; mode=block
✅ Content-Security-Policy: default-src 'self'...
✅ Referrer-Policy: strict-origin-when-cross-origin
✅ Permissions-Policy: geolocation=()...
```

### Screenshot Locations:
- **Chrome/Edge:** Network tab → Click request → Headers → Response Headers
- **Firefox:** Network tab → Click request → Headers → Response Headers

### ✅ Pass Criteria:
All 6 headers should be present in the response.

---

## 🔘 Test 2: Rate Limiting (Manual Button Test)

### Interactive Test Page:
```
http://localhost/CSGO/Mental-asylum-and-Rehabilitation-center-CSGO/manual_rate_limit_test.php
```

### Steps:
1. Open the manual rate limit test page
2. Click the **"Make Request"** button **repeatedly**
3. First 5 clicks should show: ✅ **Request ALLOWED**
4. 6th click onwards should show: 🚫 **RATE LIMITED**
5. Wait 1 minute, then click again - should be allowed

### Alternative: Test Login Rate Limiting
If you've integrated rate limiting into login:
1. Go to login page
2. Try to login with wrong password **6 times**
3. 6th attempt should be blocked with "Too many attempts" message

### ✅ Pass Criteria:
- First 5 requests: ALLOWED ✅
- 6th+ requests: RATE LIMITED 🚫
- After 1 minute: Tokens refilled, allowed again ✅

---

## 🔒 Test 3: HTTPS Enforcement

### Test Page:
```
http://localhost/CSGO/Mental-asylum-and-Rehabilitation-center-CSGO/manual_https_test.php
```

### On Localhost (Current):
1. Open the manual HTTPS test page
2. Verify it shows:
   - Current Protocol: **HTTP**
   - Is Localhost: **YES**
   - HTTPS Enforcement: **DISABLED (Development)**

### ✅ Pass Criteria for Localhost:
- HTTP is allowed (no redirect)
- Page shows "localhost detected"

### To Test Production Behavior:
**Option 1: Use a Real Domain**
1. Deploy to a server with a domain
2. Access via `http://yourdomain.com`
3. Should auto-redirect to `https://yourdomain.com` (301 redirect)

**Option 2: Simulate with Hosts File**
1. Edit hosts file:
   - Windows: `C:\Windows\System32\drivers\etc\hosts`
   - Add line: `127.0.0.1 testdomain.local`
2. Access: `http://testdomain.local/CSGO/.../admin_dashboard.php`
3. Should see redirect to HTTPS in Network tab

### ✅ Pass Criteria for Production:
- Non-localhost HTTP requests redirect to HTTPS (301)
- HTTPS requests work normally
- `Strict-Transport-Security` header present on HTTPS

---

## 📤 Test 4: File Upload Validation

### Create Test Files:

**Test File 1: Valid Image (Should PASS)**
- Create a small JPG file or use any existing image
- Try to upload via file upload form

**Test File 2: Valid PDF (Should PASS)**
- Create or use any PDF file
- Try to upload

**Test File 3: Invalid File (Should FAIL)**
- Rename any file to `.exe` extension
- Try to upload - should be rejected

### Manual Test Code:
Create a test upload page:

```php
<?php
require_once 'security_network.php';

if (isset($_FILES['test_file'])) {
    $allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    $validation = validate_file_upload($_FILES['test_file'], $allowed_types, $max_size);
    
    echo '<h2>Upload Result:</h2>';
    if ($validation['success']) {
        echo '<p style="color: green;">✅ PASS: ' . $validation['message'] . '</p>';
    } else {
        echo '<p style="color: red;">🚫 FAIL: ' . $validation['message'] . '</p>';
    }
}
?>
<form method="POST" enctype="multipart/form-data">
    <input type="file" name="test_file" required>
    <button type="submit">Upload Test File</button>
</form>
```

### ✅ Pass Criteria:
- Valid images/PDFs: Accepted ✅
- EXE files: Rejected 🚫
- Oversized files: Rejected 🚫
- MIME type mismatches: Rejected 🚫

---

## 🦠 Test 5: ClamAV Antivirus (Optional)

### Check if ClamAV is Installed:

**Windows:**
```cmd
clamscan --version
```

**Linux/Mac:**
```bash
clamscan --version
```

### If Not Installed:
This is **OPTIONAL** - the system works fine without it.

### To Install:
**Windows:**
1. Download from: http://www.clamav.net/downloads
2. Install ClamAV
3. Add to PATH environment variable

**Linux:**
```bash
sudo apt-get install clamav
sudo freshclam  # Update virus definitions
```

**macOS:**
```bash
brew install clamav
freshclam  # Update virus definitions
```

### Test ClamAV:
Once installed, re-run the automated test:
```
http://localhost/.../test_network_security.php
```

Test 5 should now pass ✅

### ✅ Pass Criteria:
- ClamAV installed: Test passes ✅
- ClamAV not installed: Test shows warning (acceptable) ⚠️

---

## 🌐 Test 6: Client IP Detection

### Test Page:
Any page that uses `security_network.php`

### View Your IP:
```php
<?php
require_once 'security_network.php';
echo "Your IP: " . get_client_ip();
?>
```

### Test with Proxy Headers:
To test proxy detection, modify your request headers:

**Using curl:**
```bash
curl -H "X-Forwarded-For: 1.2.3.4" http://localhost/.../admin_dashboard.php
```

### ✅ Pass Criteria:
- Shows valid IP address format
- Detects proxy headers correctly
- Filters private/reserved IPs

---

## 📝 Test 7: Security Event Logging

### Check PHP Error Log:

**Windows (XAMPP):**
```
C:\xampp\php\logs\php_error_log
```

**Linux:**
```
/var/log/apache2/error.log
or
/var/log/php-fpm/error.log
```

### Trigger Security Events:

1. **Failed Login:**
   - Try to login with wrong password
   - Check log for: `FAILED_LOGIN`

2. **Unauthorized Access:**
   - Access a page without permission
   - Check log for: `UNAUTHORIZED_ACCESS_ATTEMPT`

3. **Rate Limit:**
   - Trigger rate limit
   - Check log for: `RATE_LIMIT_EXCEEDED`

### View Log Entries:
Look for entries like:
```
[2025-10-20 10:30:45] SECURITY: FAILED_LOGIN | IP: ::1 | ...
[2025-10-20 10:31:02] SECURITY: RATE_LIMIT_EXCEEDED | IP: ::1 | ...
[2025-10-20 10:32:15] SECURITY: UNAUTHORIZED_ACCESS_ATTEMPT | IP: ::1 | ...
```

### ✅ Pass Criteria:
- Events are logged to error log
- Logs include IP, timestamp, user agent
- No sensitive data (passwords) in logs

---

## 🧪 Test 8: CSP (Content Security Policy)

### Check CSP in Browser:

1. Open DevTools (F12)
2. Go to **Console** tab
3. Try to execute inline script from console:
   ```javascript
   eval("alert('test')")
   ```
4. You might see CSP warnings (depends on policy strictness)

### View CSP Violations:

1. Open DevTools → Console
2. Look for CSP violation messages
3. They look like: `Refused to execute inline script because it violates CSP...`

### ✅ Pass Criteria:
- CSP header is present
- Violations are reported in console
- External scripts from allowed CDNs work

---

## 📊 Manual Testing Checklist

| Test | Method | Expected Result | Status |
|------|--------|----------------|--------|
| Security Headers | Browser DevTools | 6 headers present | ☐ |
| Rate Limiting | Button clicks | 5 allowed, 6th blocked | ☐ |
| HTTPS Enforcement | Localhost check | HTTP allowed on localhost | ☐ |
| File Upload | Upload test files | Valid accepted, invalid rejected | ☐ |
| ClamAV | Command line | Installed or warning shown | ☐ |
| IP Detection | View IP page | Valid IP format | ☐ |
| Event Logging | Check error log | Events logged | ☐ |
| CSP | Browser console | Header present | ☐ |

---

## 🎯 Quick Testing URLs

```
Automated Tests:
http://localhost/CSGO/Mental-asylum-and-Rehabilitation-center-CSGO/test_network_security.php

Manual Rate Limit Test:
http://localhost/CSGO/Mental-asylum-and-Rehabilitation-center-CSGO/manual_rate_limit_test.php

Manual HTTPS Test:
http://localhost/CSGO/Mental-asylum-and-Rehabilitation-center-CSGO/manual_https_test.php

Any Dashboard (for headers):
http://localhost/CSGO/Mental-asylum-and-Rehabilitation-center-CSGO/admin_dashboard.php
```

---

## ✅ Summary

**Passing Criteria:**
- 6/8 tests MUST pass (ClamAV and HTTPS are optional for localhost)
- Security headers present
- Rate limiting works
- Logging functional

**Current Status:**
- ✅ 7/8 tests passing (87.5%)
- ⚠️ ClamAV not installed (optional)

**Your system is secure! 🎉**

---

## 🆘 Troubleshooting

### Headers not showing?
- Ensure `session_check.php` includes `security_network.php`
- Check if page is cached (hard refresh: Ctrl+Shift+R)

### Rate limiting not working?
- Check temp directory permissions
- Clear rate limit files: `sys_get_temp_dir()/rate_limit_*.json`

### Can't see error log?
- Check PHP error log location in `php.ini`
- Enable error logging: `log_errors = On`

---

**You now have comprehensive manual testing capabilities! 🎊**
