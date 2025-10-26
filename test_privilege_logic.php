<?php
// Test script to demonstrate when privilege escalation is recorded
require_once 'db.php';
require_once 'security_manager.php';

echo "<h2>Testing Privilege Escalation Detection</h2>\n";

// Simulate a user with chief-staff role trying to access admin resources
$current_role = 'chief-staff';
$required_role = 'admin';

echo "<p>Current role: $current_role</p>\n";
echo "<p>Required role: $required_role</p>\n";

// Role hierarchy (lower number = higher privilege)
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

echo "<p>Current level: $current_level</p>\n";
echo "<p>Required level: $required_level</p>\n";

// Check if this would trigger privilege escalation detection
if ($current_level > $required_level) {
    echo "<p style='color: red;'><strong>This WOULD trigger privilege escalation detection!</strong></p>\n";
    echo "<p>Because $current_level > $required_level</p>\n";
    
    // Show what would be recorded
    echo "<h3>What would be recorded:</h3>\n";
    echo "<ul>\n";
    echo "<li>User trying to access: $required_role (level $required_level)</li>\n";
    echo "<li>User's actual role: $current_role (level $current_level)</li>\n";
    echo "<li>Result: Privilege escalation attempt would be logged</li>\n";
    echo "</ul>\n";
} else {
    echo "<p style='color: green;'>This would NOT trigger privilege escalation detection.</p>\n";
}

echo "<hr>\n";
echo "<h3>Role Hierarchy:</h3>\n";
echo "<ol>\n";
foreach ($role_hierarchy as $role => $level) {
    echo "<li>$role (level $level)</li>\n";
}
echo "</ol>\n";

echo "<p><strong>Note:</strong> The privilege escalation table only gets populated when the validateRoleAccess method is actually called and detects an escalation attempt. Simply trying to access a page through a browser redirect doesn't necessarily trigger this.</p>\n";
?>