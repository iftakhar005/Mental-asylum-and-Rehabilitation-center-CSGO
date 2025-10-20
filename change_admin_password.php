<?php
/**
 * Change Admin Password to admin123
 * Run this file once in browser, then delete it for security
 */

require_once 'db.php';

// New password
$new_password = 'admin123';
$password_hash = password_hash($new_password, PASSWORD_DEFAULT);

echo "<h2>Admin Password Reset</h2>";

// Try to update in users table
$stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE role = 'admin'");
if ($stmt) {
    $stmt->bind_param('s', $password_hash);
    if ($stmt->execute()) {
        $affected = $stmt->affected_rows;
        if ($affected > 0) {
            echo "<p style='color: green;'>✅ Admin password updated successfully in users table!</p>";
            echo "<p><strong>New credentials:</strong></p>";
            echo "<p>Username/Email: (check your admin email in database)</p>";
            echo "<p>Password: <strong>admin123</strong></p>";
        } else {
            echo "<p style='color: orange;'>⚠️ No admin user found in users table</p>";
        }
    }
    $stmt->close();
}

// Also try to find admin user by email pattern
$result = $conn->query("SELECT id, username, email, role FROM users WHERE role = 'admin' LIMIT 1");
if ($result && $result->num_rows > 0) {
    $admin = $result->fetch_assoc();
    echo "<hr>";
    echo "<h3>Admin User Details:</h3>";
    echo "<p>User ID: " . $admin['id'] . "</p>";
    echo "<p>Username: " . $admin['username'] . "</p>";
    echo "<p>Email: " . $admin['email'] . "</p>";
    echo "<p>Role: " . $admin['role'] . "</p>";
} else {
    echo "<hr>";
    echo "<p style='color: red;'>❌ No admin user found in database</p>";
    echo "<p>You may need to create an admin user first.</p>";
}

echo "<hr>";
echo "<p style='color: red;'><strong>IMPORTANT: Delete this file (change_admin_password.php) after use for security!</strong></p>";
echo "<p><a href='index.php'>Go to Login Page</a></p>";

$conn->close();
?>
