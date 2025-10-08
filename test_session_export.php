<?php
session_start();

// Simulate being logged in as the user who made the request
$_SESSION['user_id'] = 6; // The user_id from the export request
$_SESSION['role'] = 'staff';
$_SESSION['username'] = 'test_user';

echo "<h3>Testing Export Download with Session</h3>";
echo "<p>Session user_id: " . $_SESSION['user_id'] . "</p>";
echo "<p>Session role: " . $_SESSION['role'] . "</p>";

// Now include the secure export directly
$_GET['request_id'] = 3; // Set the request_id

echo "<hr>";
echo "<h4>Export Result:</h4>";

// Capture the output
ob_start();
include 'secure_export.php';
$output = ob_get_clean();

echo "<pre>" . htmlspecialchars($output) . "</pre>";
?>