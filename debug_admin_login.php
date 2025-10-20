<?php
/**
 * DEBUG ADMIN LOGIN
 * This file helps diagnose admin login issues
 */

session_start();

echo "<h1>Admin Login Debug</h1>";
echo "<pre>";

echo "<strong>SESSION DATA:</strong>\n";
print_r($_SESSION);

echo "\n<strong>CHECKING DATABASE FOR ADMIN USER:</strong>\n";
require_once 'db.php';

// Check users table for admin
$admin_check = $conn->query("SELECT id, username, email, role, two_factor_enabled FROM users WHERE role = 'admin'");
if ($admin_check && $admin_check->num_rows > 0) {
    echo "✅ Admin user(s) found in users table:\n";
    while ($row = $admin_check->fetch_assoc()) {
        echo "  - ID: {$row['id']}, Username: {$row['username']}, Email: {$row['email']}, 2FA: " . ($row['two_factor_enabled'] ? 'Yes' : 'No') . "\n";
    }
} else {
    echo "❌ No admin user found in users table\n";
}

// Check staff table for admin
$admin_staff_check = $conn->query("SELECT staff_id, full_name, email, role, two_factor_enabled FROM staff WHERE role = 'admin'");
if ($admin_staff_check && $admin_staff_check->num_rows > 0) {
    echo "\n✅ Admin user(s) found in staff table:\n";
    while ($row = $admin_staff_check->fetch_assoc()) {
        echo "  - Staff ID: {$row['staff_id']}, Name: {$row['full_name']}, Email: {$row['email']}, 2FA: " . ($row['two_factor_enabled'] ? 'Yes' : 'No') . "\n";
    }
} else {
    echo "❌ No admin user found in staff table\n";
}

echo "\n<strong>SESSION STATUS:</strong>\n";
echo "Session ID: " . session_id() . "\n";
echo "Session Status: " . (session_status() === PHP_SESSION_ACTIVE ? 'Active' : 'Inactive') . "\n";

if (isset($_SESSION['role'])) {
    echo "\n✅ User is logged in as: " . $_SESSION['role'] . "\n";
    echo "Username: " . ($_SESSION['username'] ?? 'N/A') . "\n";
    echo "User ID: " . ($_SESSION['user_id'] ?? 'N/A') . "\n";
    
    if ($_SESSION['role'] === 'admin') {
        echo "\n✅ <strong>Admin access should work!</strong>\n";
        echo '<a href="admin_dashboard.php" style="display:inline-block;margin-top:10px;padding:10px 20px;background:#667eea;color:white;text-decoration:none;border-radius:8px;">Go to Admin Dashboard</a>';
    } else {
        echo "\n❌ <strong>Not logged in as admin</strong>\n";
    }
} else {
    echo "\n❌ Not logged in\n";
    echo '<a href="index.php" style="display:inline-block;margin-top:10px;padding:10px 20px;background:#667eea;color:white;text-decoration:none;border-radius:8px;">Go to Login</a>';
}

echo "</pre>";

echo "<hr><h3>Test Login Form</h3>";
echo '<form method="POST" action="index.php" style="max-width:400px;">';
echo '<div style="margin:10px 0;">';
echo '<label>Email:</label><br>';
echo '<input type="email" name="email" required style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;">';
echo '</div>';
echo '<div style="margin:10px 0;">';
echo '<label>Password:</label><br>';
echo '<input type="password" name="password" required style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;">';
echo '</div>';
echo '<button type="submit" style="padding:10px 20px;background:#667eea;color:white;border:none;border-radius:8px;cursor:pointer;">Login</button>';
echo '</form>';
?>
