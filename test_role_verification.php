<?php
session_start();
require_once 'db.php';
require_once 'propagation_prevention.php';

echo "<h1>Role Verification Test</h1>";
echo "<pre>";

// Manually test the role verification logic
$user_id = $_SESSION['user_id'] ?? 1;
$session_role = $_SESSION['role'] ?? 'admin';

echo "Testing for user_id: $user_id, role: $session_role\n\n";

// Check users table
echo "Checking users table:\n";
$stmt = $conn->prepare("SELECT id, username, role FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo "✅ Found in users table:\n";
    echo "   ID: {$row['id']}\n";
    echo "   Username: {$row['username']}\n";
    echo "   Role: {$row['role']}\n";
    echo "   Session Role: $session_role\n";
    echo "   Roles Match: " . ($row['role'] === $session_role ? '✅ YES' : '❌ NO') . "\n\n";
    
    if ($row['role'] === $session_role) {
        echo "✅✅✅ ROLE INTEGRITY VERIFIED - Admin should be able to login!\n\n";
    }
} else {
    echo "❌ Not found in users table\n\n";
}
$stmt->close();

// Check staff table
echo "Checking staff table:\n";
$stmt = $conn->prepare("SELECT user_id, staff_id, full_name, role FROM staff WHERE user_id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo "✅ Found in staff table:\n";
    echo "   User ID: {$row['user_id']}\n";
    echo "   Staff ID: {$row['staff_id']}\n";
    echo "   Name: {$row['full_name']}\n";
    echo "   Role: {$row['role']}\n";
} else {
    echo "ℹ️  Not found in staff table (this is OK for admin users)\n\n";
}
$stmt->close();

echo "\n";
echo "Session Data:\n";
print_r($_SESSION);

echo "</pre>";

echo '<hr>';
echo '<a href="admin_dashboard.php" style="display:inline-block;padding:10px 20px;background:#667eea;color:white;text-decoration:none;border-radius:8px;">Try Admin Dashboard</a>';
echo ' ';
echo '<a href="index.php" style="display:inline-block;padding:10px 20px;background:#999;color:white;text-decoration:none;border-radius:8px;">Back to Login</a>';
?>
