<?php
// Debug script to test privilege escalation detection
session_start();
require_once 'db.php';
require_once 'security_manager.php';

echo "<h2>Debugging Privilege Escalation Detection</h2>\n";

// Simulate a logged-in user
if (!isset($_SESSION['user_id'])) {
    echo "<p>You need to be logged in to test this. Please log in first.</p>\n";
    echo "<p><a href='index.php'>Go to login page</a></p>\n";
    exit();
}

echo "<h3>Current Session Information:</h3>\n";
echo "<ul>\n";
echo "<li>User ID: " . ($_SESSION['user_id'] ?? 'Not set') . "</li>\n";
echo "<li>Username: " . ($_SESSION['username'] ?? 'Not set') . "</li>\n";
echo "<li>Role: " . ($_SESSION['role'] ?? 'Not set') . "</li>\n";
echo "<li>Propagation Role: " . ($_SESSION['propagation_role'] ?? 'Not set') . "</li>\n";
echo "</ul>\n";

// Test the role validation
echo "<h3>Testing Role Validation:</h3>\n";

$required_role = 'admin';
$current_role = $_SESSION['role'] ?? 'unknown';

echo "<p>Current role: $current_role</p>\n";
echo "<p>Required role: $required_role</p>\n";

// Check what the security manager says
$result = $securityManager->validateRoleAccess($required_role);

echo "<p>Validation result: " . ($result ? 'ALLOWED' : 'DENIED') . "</p>\n";

if (!$result) {
    echo "<p style='color: red;'>Access denied - this should have been recorded in the privilege escalation table if it was a privilege escalation attempt.</p>\n";
} else {
    echo "<p style='color: green;'>Access allowed</p>\n";
}

// Check if this was a privilege escalation attempt
$role_hierarchy = [
    'admin' => 1,
    'chief-staff' => 2,
    'doctor' => 3,
    'therapist' => 4,
    'nurse' => 5,
    'receptionist' => 6,
    'relative' => 7,
    'general_user' => 8
];

$current_level = $role_hierarchy[$current_role] ?? 999;
$required_level = $role_hierarchy[$required_role] ?? 0;

echo "<h3>Role Hierarchy Analysis:</h3>\n";
echo "<ul>\n";
echo "<li>Current level: $current_level</li>\n";
echo "<li>Required level: $required_level</li>\n";
echo "<li>Is escalation attempt: " . ($current_level > $required_level ? 'YES' : 'NO') . "</li>\n";
echo "</ul>\n";

echo "<p><a href='admin_dashboard.php'>Try accessing admin dashboard</a></p>\n";
echo "<p><a href='debug_privilege_escalation.php'>Run this test again</a></p>\n";
?>