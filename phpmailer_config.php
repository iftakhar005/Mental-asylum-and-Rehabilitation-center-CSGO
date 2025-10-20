<?php
/**
 * PHPMailer SMTP Configuration
 * 
 * IMPORTANT: Update these values with your actual SMTP credentials
 * 
 * For Gmail:
 * 1. Go to Google Account Settings → Security
 * 2. Enable 2-Step Verification
 * 3. Go to App Passwords
 * 4. Generate a new app password for "Mail"
 * 5. Use the 16-character password below (NOT your regular password)
 */

// SMTP Server Configuration
define('SMTP_HOST', 'smtp.gmail.com');  // Gmail SMTP server
define('SMTP_PORT', 587);                // Port 587 for TLS, 465 for SSL
define('SMTP_USERNAME', 'iftakharmajumder@gmail.com');  // YOUR Gmail address
define('SMTP_PASSWORD', 'jwzdxgpbfflhlnsu');     // YOUR App Password (16 characters)

// Email Sender Information
define('SMTP_FROM_EMAIL', 'iftakharmajumder@gmail.com'); // Sender email
define('SMTP_FROM_NAME', 'Mental Health Assylum System'); // Sender name

/**
 * SETUP INSTRUCTIONS FOR GMAIL:
 * 
 * 1. Update SMTP_USERNAME with your Gmail address
 * 2. Update SMTP_FROM_EMAIL with the same Gmail address
 * 3. Generate App Password:
 *    - Visit: https://myaccount.google.com/apppasswords
 *    - Select "Mail" and "Other (Custom name)"
 *    - Name it "MindCare 2FA"
 *    - Click "Generate"
 *    - Copy the 16-character password (remove spaces)
 *    - Paste in SMTP_PASSWORD above
 * 
 * 4. Make sure 2-Step Verification is enabled on your Google account
 * 
 * 5. Test the configuration by running test_smtp.php
 * 
 * FOR OTHER EMAIL PROVIDERS:
 * - Outlook/Hotmail: smtp.office365.com, port 587
 * - Yahoo: smtp.mail.yahoo.com, port 465 or 587
 * - Custom SMTP: Contact your email provider for settings
 */

// Security: Prevent direct access
if (!defined('SMTP_CONFIG_LOADED')) {
    define('SMTP_CONFIG_LOADED', true);
}
?>
