# 🚀 2FA Quick Start Guide

## Get 2FA Working in 5 Minutes!

---

## Step 1: Run Database Setup (1 minute)

Navigate to:
```
http://localhost/CSGO/Mental-asylum-and-Rehabilitation-center-CSGO/setup_2fa_database.php
```

✅ All checks should be green

---

## Step 2: Configure Email (2 minutes)

### For Gmail Users:

1. **Open:** `phpmailer_config.php`

2. **Update these lines:**
```php
define('SMTP_USERNAME', 'your-email@gmail.com');  // Line 14
define('SMTP_PASSWORD', 'xxxx xxxx xxxx xxxx');   // Line 15 - App Password
define('SMTP_FROM_EMAIL', 'your-email@gmail.com');// Line 18
```

3. **Get Gmail App Password:**
   - Go to: https://myaccount.google.com/apppasswords
   - Click "Select app" → Choose "Mail"
   - Click "Select device" → Choose "Other" → Type "MindCare"
   - Click "Generate"
   - Copy the 16-character password
   - Paste in `SMTP_PASSWORD` (remove spaces)

---

## Step 3: Test Email (1 minute)

Navigate to:
```
http://localhost/CSGO/Mental-asylum-and-Rehabilitation-center-CSGO/test_smtp.php
```

1. Enter your email address
2. Click "Send Test Email"
3. Check your inbox (and spam folder!)

✅ You should receive a test email with OTP code

---

## Step 4: Enable 2FA for a User (30 seconds)

**Option A: For New Users (When Creating)**
- When adding doctor/nurse/therapist, check "Enable 2FA" checkbox
- (You may need to add this checkbox - see full guide)

**Option B: For Existing Users (Database)**
```sql
-- Run this in phpMyAdmin → SQL tab
UPDATE users SET two_factor_enabled = 1 WHERE email = 'your-test-user@example.com';
-- OR for staff:
UPDATE staff SET two_factor_enabled = 1 WHERE email = 'your-test-staff@example.com';
```

---

## Step 5: Test Login! (30 seconds)

1. **Log out** from current session
2. Go to login page
3. Enter credentials of 2FA-enabled user
4. **You'll be redirected to OTP page!** 🎉
5. Check your email for 6-digit code
6. Enter the code
7. **You're logged in!**

---

## 🎊 Success!

You now have working 2FA!

### What You Just Built:
- ✅ Secure email-based 2FA
- ✅ OTP with 10-minute expiration
- ✅ Beautiful verification UI
- ✅ Resend OTP functionality
- ✅ Production-ready security

---

## ❓ Quick Troubleshooting

### Not Receiving Emails?
1. Check spam folder
2. Verify SMTP_PASSWORD is App Password (not regular password)
3. Run test_smtp.php again
4. Check if 2-Step Verification is enabled on your Google account

### Can't Connect to SMTP?
1. Try port 465 instead of 587 in `phpmailer_config.php`
2. Check firewall settings
3. Make sure you're using App Password

### OTP Invalid?
1. Make sure you entered all 6 digits
2. Check if OTP expired (10 minutes)
3. Click "Resend OTP" to get new code

---

## 📚 For More Details:

See `2FA_IMPLEMENTATION_GUIDE.md` for:
- Complete documentation
- Security features
- Admin management
- Faculty presentation tips
- Production deployment guide

---

**That's it! You're done! 🎉**
