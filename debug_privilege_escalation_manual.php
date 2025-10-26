<?php
// Test script that will deliberately trigger privilege escalation detection
session_start();
require_once 'db.php';
require_once 'security_manager.php';
require_once 'propagation_prevention.php';

echo "<h2>Privilege Escalation Test</h2>\n";

// Initialize propagation prevention
$propagation = new PropagationPrevention($conn);

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    echo "<p>You need to be logged in to test this.</p>\n";
    echo "<p><a href='index.php'>Login</a></p>\n";
    exit();
}

$user_id = $_SESSION['user_id'];
$current_role = $_SESSION['role'];

echo "<p>Logged in as: $current_role (User ID: $user_id)</p>\n";

// Only proceed if user is not admin
if ($current_role === 'admin') {
    echo "<p>You're already an admin, so there's no escalation to detect.</p>\n";
    exit();
}

echo "<h3>Testing Privilege Escalation Detection</h3>\n";

// Initialize session tracking if not already done
if (!isset($_SESSION['propagation_role'])) {
    echo "<p>Initializing session tracking...</p>\n";
    $propagation->initializeSessionTracking($user_id, $current_role);
}

// Try to validate admin access - this should trigger escalation detection
echo "<p>Attempting to validate admin access...</p>\n";
$result = $propagation->validateRoleAccess('admin');

if (!$result) {
    echo "<p style='color: red;'>Access denied - privilege escalation attempt should be recorded!</p>\n";
} else {
    echo "<p style='color: green;'>Access granted</p>\n";
}

// Check what was recorded in the database
echo "<h3>Checking Database Records:</h3>\n";

// Check privilege escalation tracking table
$sql = "SELECT * FROM privilege_escalation_tracking ORDER BY attempt_timestamp DESC LIMIT 5";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    echo "<p>Found " . $result->num_rows . " privilege escalation records:</p>\n";
    echo "<table border='1'>\n";
    echo "<tr><th>ID</th><th>User ID</th><th>Attempted Role</th><th>Current Role</th><th>Timestamp</th></tr>\n";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['user_id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['attempted_role']) . "</td>";
        echo "<td>" . htmlspecialchars($row['current_role']) . "</td>";
        echo "<td>" . htmlspecialchars($row['attempt_timestamp']) . "</td>";
        echo "</tr>\n";
    }
    echo "</table>\n";
} else {
    echo "<p>No privilege escalation records found.</p>\n";
    if ($result === false) {
        echo "<p>Database error: " . $conn->error . "</p>\n";
    }
}

echo "<p><a href='debug_privilege_escalation_manual.php'>Run Test Again</a></p>\n";

$conn->close();
?>