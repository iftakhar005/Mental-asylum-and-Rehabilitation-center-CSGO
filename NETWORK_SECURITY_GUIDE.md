# 🌐 Network Security Implementation - Complete Guide

## ✅ What Has Been Implemented

### 1. **Core Network Security File**
- ✅ [`security_network.php`](security_network.php) - Complete network security module

### 2. **Integrated Into System**
- ✅ [`session_check.php`](session_check.php) - Auto-applies security to all authenticated pages

### 3. **Testing & Demo Tools**
- ✅ [`test_network_security.php`](test_network_security.php) - Automated test suite (8 tests)
- ✅ [`network_security_demo.php`](network_security_demo.php) - Practical usage examples

---

## 🔒 Security Features Implemented

### 1. **HTTPS Enforcement** 
✅ Automatically redirects HTTP → HTTPS (production only)
- Skips localhost/127.0.0.1 for development
- Returns 301 redirect for production environments

### 2. **Security Headers**
✅ Comprehensive HTTP security headers sent automatically:
- `X-Content-Type-Options: nosniff` - Prevents MIME sniffing
- `X-Frame-Options: SAMEORIGIN` - Prevents clickjacking
- `X-XSS-Protection: 1; mode=block` - XSS protection
- `Content-Security-Policy` - Restricts resource loading
- `Strict-Transport-Security` - Forces HTTPS (when on HTTPS)
- `Referrer-Policy` - Controls referrer information
- `Permissions-Policy` - Limits browser features

### 3. **Rate Limiting (Token Bucket Algorithm)**
✅ Prevents brute force and DoS attacks:
- Configurable limits per user/IP/endpoint
- Automatic token refill over time
- File-based persistence (survives restarts)
- Default: 30 requests per minute for POST/PUT/DELETE

### 4. **File Upload Validation**
✅ Secure file upload handling:
- MIME type verification
- File size limits
- Extension validation
- MIME/extension mismatch detection
- Security event logging

### 5. **ClamAV Antivirus Integration**
✅ Optional virus scanning:
- Scans uploaded files for malware
- Gracefully handles when ClamAV not installed
- Returns detailed scan results

### 6. **Security Event Logging**
✅ Audit trail for security events:
- Logs to PHP error log
- Includes IP address, user agent, timestamp
- Contextual information for investigations

### 7. **IP Address Detection**
✅ Proxy-aware IP detection:
- Handles X-Forwarded-For headers
- Validates IP addresses
- Filters private/reserved ranges

---

## 🚀 How to Use

### Automatic Security (Recommended)

Simply include `security_network.php` at the top of your file:

```php
<?php
require_once 'security_network.php';

// All security features are now AUTO-APPLIED:
// ✅ Security headers
// ✅ HTTPS enforcement (production)
// ✅ Rate limiting (POST/PUT/DELETE)
```

**Already integrated in:**
- ✅ All pages that use `session_check.php` (dashboards, management pages)
- ✅ Automatic for all authenticated pages

---

## 📝 Usage Examples

### Example 1: Protect Login Endpoint

```php
<?php
require_once 'security_network.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ip = get_client_ip();
    
    // Limit to 5 login attempts per 5 minutes
    if (!rate_limit('login_' . $ip, 5, 300)) {
        http_response_code(429);
        echo json_encode(['error' => 'Too many login attempts']);
        exit();
    }
    
    // Process login...
}
```

### Example 2: Validate File Upload

```php
<?php
require_once 'security_network.php';

if (isset($_FILES['document'])) {
    $allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    $validation = validate_file_upload($_FILES['document'], $allowed_types, $max_size);
    
    if ($validation['success']) {
        // Optional: Scan for viruses
        $scan = scan_file_with_clamscan($_FILES['document']['tmp_name']);
        
        if ($scan['available'] && $scan['infected']) {
            die('File contains malware!');
        }
        
        // Safe to process
        move_uploaded_file($_FILES['document']['tmp_name'], $destination);
    } else {
        echo $validation['message'];
    }
}
```

### Example 3: Custom Rate Limiting for API

```php
<?php
define('DISABLE_AUTO_SECURITY', true); // Disable auto rate limiting
require_once 'security_network.php';

$user_role = $_SESSION['role'] ?? 'guest';

// Different limits based on role
switch ($user_role) {
    case 'admin':
        $limit = 100; // 100 req/min
        break;
    case 'doctor':
    case 'therapist':
        $limit = 60; // 60 req/min
        break;
    default:
        $limit = 30; // 30 req/min
}

apply_rate_limit('api_' . $_SESSION['user_id'], $limit, 60);

// Process API request...
```

### Example 4: Log Security Events

```php
<?php
require_once 'security_network.php';

// Failed login
if ($login_failed) {
    log_security_event('FAILED_LOGIN', [
        'username' => $username,
        'reason' => 'Invalid password'
    ]);
}

// Unauthorized access
if (!$has_permission) {
    log_security_event('UNAUTHORIZED_ACCESS', [
        'user_id' => $user_id,
        'resource' => $_SERVER['REQUEST_URI']
    ]);
}

// Data export
log_security_event('DATA_EXPORT', [
    'user_id' => $user_id,
    'export_type' => 'patient_records',
    'count' => count($records)
]);
```

---

## 🔍 Testing the Implementation

### Run the Test Suite:
```
http://localhost/CSGO/Mental-asylum-and-Rehabilitation-center-CSGO/test_network_security.php
```

**Tests Performed:**
1. ✅ Security headers verification
2. ✅ Client IP detection
3. ✅ Rate limiting (token bucket)
4. ✅ File upload validation
5. ✅ ClamAV antivirus scanner
6. ✅ HTTPS enforcement
7. ✅ Token refill recovery
8. ✅ Security event logging

**Expected Result:** All 8 tests should PASS ✅

### View Usage Examples:
```
http://localhost/CSGO/Mental-asylum-and-Rehabilitation-center-CSGO/network_security_demo.php
```

---

## 📊 Function Reference

### Core Functions

| Function | Purpose | Parameters |
|----------|---------|------------|
| `enforce_https()` | Redirect to HTTPS (production) | None |
| `send_security_headers()` | Send HTTP security headers | None |
| `rate_limit($key, $limit, $seconds)` | Token bucket rate limiter | $key, $limit=30, $seconds=60 |
| `apply_rate_limit($id, $limit, $window)` | Rate limit with auto HTTP 429 | $id, $limit=30, $window=60 |
| `get_client_ip()` | Get client IP (proxy-aware) | None |
| `log_security_event($type, $context)` | Log security event | $type, $context=[] |
| `validate_file_upload($file, $types, $size)` | Validate uploaded file | $file, $types=[], $size=5MB |
| `scan_file_with_clamscan($path)` | Scan file for viruses | $path |

---

## ⚙️ Configuration Options

### Disable Auto-Security

If you need manual control, disable auto-apply:

```php
<?php
define('DISABLE_AUTO_SECURITY', true);
require_once 'security_network.php';

// Now manually call functions as needed
send_security_headers();
// Don't enforce HTTPS
// Don't auto rate limit
```

### Custom CSP (Content Security Policy)

Edit `security_network.php` line ~55 to customize CSP:

```php
$csp = "default-src 'self'; " .
       "script-src 'self' 'unsafe-inline' https://trusted-cdn.com; " .
       "style-src 'self' 'unsafe-inline';";
```

### Adjust Rate Limits

In `session_check.php` or individual files:

```php
// Change from default 30 to 60 requests per minute
apply_rate_limit(get_client_ip(), 60, 60);
```

---

## 🎯 Current Integration Status

### ✅ Auto-Protected Pages
All pages that use `session_check.php`:
- ✅ All dashboard files (*_dashboard.php)
- ✅ Patient management pages
- ✅ Staff management pages
- ✅ Appointment pages
- ✅ Treatment pages

### 🔄 Optional Integration
Pages you may want to protect manually:
- [ ] `index.php` (login page) - Add rate limiting
- [ ] File upload handlers - Add validation
- [ ] API endpoints - Add custom rate limits

---

## 🔐 Security Best Practices

### 1. **Production HTTPS**
- Obtain SSL/TLS certificate (Let's Encrypt, commercial CA)
- Configure web server (Apache/Nginx) for HTTPS
- Network security will auto-redirect HTTP → HTTPS

### 2. **Install ClamAV (Optional)**
```bash
# Linux
sudo apt-get install clamav

# macOS
brew install clamav

# Update virus database
sudo freshclam
```

### 3. **Monitor Logs**
- Check PHP error log for security events
- Look for patterns: `SECURITY:`, `RATE_LIMIT_EXCEEDED`, `FAILED_LOGIN`

### 4. **Tighten CSP**
- Remove `'unsafe-inline'` for scripts/styles in production
- Use nonces or hashes for inline code
- Restrict to specific CDN domains

### 5. **Regular Updates**
- Keep ClamAV virus definitions updated
- Update PHP and dependencies
- Review security logs regularly

---

## 📈 Rate Limiting Scenarios

| Scenario | Rate Limit | Time Window |
|----------|-----------|-------------|
| Login attempts | 5 attempts | 5 minutes |
| API calls (admin) | 100 requests | 1 minute |
| API calls (medical staff) | 60 requests | 1 minute |
| API calls (other users) | 30 requests | 1 minute |
| File uploads | 10 uploads | 1 hour |
| Password reset | 3 requests | 1 hour |

---

## 🆘 Troubleshooting

### Issue: Rate limiting too strict
**Solution:** Increase limits in `security_network.php` auto-apply section (line ~348)

### Issue: Localhost redirecting to HTTPS
**Solution:** Ensure your server name contains "localhost" or use 127.0.0.1

### Issue: CSP blocking resources
**Solution:** Check browser console, add allowed domains to CSP in `send_security_headers()`

### Issue: ClamAV not working
**Solution:** Install ClamAV or disable virus scanning (it's optional)

---

## 📞 Support Files

- **Network Security Module:** [security_network.php](security_network.php)
- **Test Suite:** [test_network_security.php](test_network_security.php)
- **Usage Examples:** [network_security_demo.php](network_security_demo.php)
- **Session Protection:** [session_check.php](session_check.php) (auto-integrated)

---

## 🎉 Implementation Complete!

### What's Protected Now:
✅ All authenticated pages have security headers  
✅ HTTPS enforcement on production  
✅ Rate limiting on all POST/PUT/DELETE requests  
✅ Unauthorized access logging  
✅ File upload validation available  
✅ Antivirus scanning available (optional)  

### Next Steps:
1. ✅ Run `test_network_security.php` to verify
2. ✅ Review `network_security_demo.php` for examples
3. ✅ Add rate limiting to `index.php` login
4. ✅ Add file validation to upload handlers
5. ✅ Install ClamAV for virus scanning (optional)
6. ✅ Tighten CSP for production

---

**Your application now has enterprise-grade network security! 🔒**
