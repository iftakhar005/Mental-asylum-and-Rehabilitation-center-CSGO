<?php
/**
 * Quick Email Test - Run this to test email sending immediately
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'otp_functions.php';

// Test email sending
$recipient_email = 'iftakharmajumder@gmail.com';
$recipient_name = 'Iftakhar';
$test_otp = generateOTP();

echo "<html><head><style>
    body { font-family: Arial; padding: 20px; background: #f5f5f5; }
    .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
    h1 { color: #333; }
    .status { padding: 15px; border-radius: 5px; margin: 15px 0; }
    .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
    .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
    .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; }
    .otp-box { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 10px; text-align: center; margin: 20px 0; }
    .otp-code { font-size: 36px; font-weight: bold; letter-spacing: 8px; font-family: monospace; }
    pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; }
</style></head><body><div class='container'>";

echo "<h1>🧪 Email Delivery Test</h1>";

echo "<div class='info'>";
echo "<strong>📋 Test Configuration:</strong><br>";
echo "SMTP Host: " . SMTP_HOST . "<br>";
echo "SMTP Port: " . SMTP_PORT . "<br>";
echo "Username: " . SMTP_USERNAME . "<br>";
echo "From Email: " . SMTP_FROM_EMAIL . "<br>";
echo "To Email: {$recipient_email}<br>";
echo "</div>";

echo "<div class='otp-box'>";
echo "<p style='margin:0;'>Generated OTP Code:</p>";
echo "<div class='otp-code'>{$test_otp}</div>";
echo "<p style='margin:0; font-size:12px;'>This code should arrive in the email</p>";
echo "</div>";

echo "<h2>📨 Sending Email...</h2>";

// Capture all output
ob_start();

$start_time = microtime(true);
$result = sendOTPEmail($recipient_email, $recipient_name, $test_otp);
$end_time = microtime(true);

$output = ob_get_clean();

$execution_time = round(($end_time - $start_time) * 1000, 2);

if ($result) {
    echo "<div class='success'>";
    echo "<h3>✅ Email Sent Successfully!</h3>";
    echo "<p>Execution time: {$execution_time}ms</p>";
    echo "<p>Check your inbox at: <strong>{$recipient_email}</strong></p>";
    echo "<p>If not in inbox, check SPAM folder.</p>";
    echo "</div>";
} else {
    echo "<div class='error'>";
    echo "<h3>❌ Email Sending Failed</h3>";
    echo "<p>Execution time: {$execution_time}ms</p>";
    echo "</div>";
}

echo "<h2>📝 Detailed Logs:</h2>";
echo "<pre>";

// Read the Apache error log for recent entries
$error_log_file = 'E:\\XAMPP\\apache\\logs\\error.log';
if (file_exists($error_log_file)) {
    $log_lines = file($error_log_file);
    $recent_logs = array_slice($log_lines, -50); // Last 50 lines
    
    echo "=== Recent Apache Error Log (Last 50 lines) ===\n\n";
    foreach ($recent_logs as $line) {
        if (stripos($line, '2FA') !== false || stripos($line, 'OTP') !== false || stripos($line, 'EMAIL') !== false) {
            echo htmlspecialchars($line);
        }
    }
}

if ($output) {
    echo "\n\n=== PHP Output ===\n\n";
    echo htmlspecialchars($output);
}

echo "</pre>";

echo "<h2>🔍 Next Steps:</h2>";
echo "<div class='info'>";
echo "<ol>";
echo "<li><strong>Check your email inbox</strong> for {$recipient_email}</li>";
echo "<li><strong>Check SPAM/Junk folder</strong> if not in inbox</li>";
echo "<li><strong>Verify OTP code</strong> matches: <code>{$test_otp}</code></li>";
echo "<li><strong>Review logs above</strong> for any error messages</li>";
echo "</ol>";
echo "</div>";

echo "<h2>⚙️ Troubleshooting:</h2>";
echo "<div class='info'>";
echo "<strong>If email didn't arrive:</strong><br>";
echo "1. <a href='https://myaccount.google.com/apppasswords' target='_blank'>Generate new App Password</a><br>";
echo "2. Update phpmailer_config.php with new password<br>";
echo "3. Check <a href='https://myaccount.google.com/notifications' target='_blank'>Google Security Alerts</a><br>";
echo "4. Try <a href='https://accounts.google.com/DisplayUnlockCaptcha' target='_blank'>Unlock Captcha</a><br>";
echo "</div>";

// Also test via view_otp.php
echo "<h2>🔑 Alternative: View OTP from Database</h2>";
echo "<div class='info'>";
echo "If email delivery is unreliable, you can use:<br>";
echo "<a href='view_otp.php' target='_blank'>View OTP Tool</a> - Shows OTPs from database<br>";
echo "<a href='debug_otp.php' target='_blank'>Debug OTP Tool</a> - Shows detailed OTP information";
echo "</div>";

echo "</div></body></html>";
?>
