<?php
session_start();

echo "<h1>Session Cleanup</h1>";
echo "<h3>Current Session Data BEFORE Cleanup:</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

// Clear OTP verification data
if (isset($_SESSION['otp_verification_pending'])) {
    unset($_SESSION['otp_verification_pending']);
    echo "<p>✅ Removed: otp_verification_pending</p>";
}
if (isset($_SESSION['otp_email'])) {
    unset($_SESSION['otp_email']);
    echo "<p>✅ Removed: otp_email</p>";
}
if (isset($_SESSION['pending_user_id'])) {
    unset($_SESSION['pending_user_id']);
    echo "<p>✅ Removed: pending_user_id</p>";
}
if (isset($_SESSION['pending_staff_id'])) {
    unset($_SESSION['pending_staff_id']);
    echo "<p>✅ Removed: pending_staff_id</p>";
}
if (isset($_SESSION['pending_username'])) {
    unset($_SESSION['pending_username']);
    echo "<p>✅ Removed: pending_username</p>";
}
if (isset($_SESSION['pending_role'])) {
    unset($_SESSION['pending_role']);
    echo "<p>✅ Removed: pending_role</p>";
}

echo "<hr>";
echo "<h3>Session Data AFTER Cleanup:</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<hr>";
echo "<h2>✅ Session Cleaned!</h2>";
echo '<p><a href="admin_dashboard.php" style="display:inline-block;padding:15px 30px;background:#10b981;color:white;text-decoration:none;border-radius:8px;font-weight:bold;">GO TO ADMIN DASHBOARD</a></p>';
?>
