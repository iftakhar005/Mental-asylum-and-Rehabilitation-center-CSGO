<?php
// Direct test of privilege escalation detection
session_start();
require_once 'db.php';
require_once 'propagation_prevention.php';

echo "<h2>Direct Privilege Escalation Test</h2>\n";

// Initialize propagation prevention
$propagation = new PropagationPrevention($conn);

// Simulate a chief-staff user trying to access admin
$user_id = 1; // Use a test user ID
$current_role = 'chief-staff';
$attempted_role = 'admin';

echo "<p>Simulating $current_role trying to access $attempted_role...</p>\n";

// This should trigger the privilege escalation detection
$reflection = new ReflectionClass($propagation);
$method = $reflection->getMethod('detectPrivilegeEscalationPropagation');
$method->setAccessible(true);

// Call the private method directly
$method->invokeArgs($propagation, [$user_id, $current_role, $attempted_role]);

echo "<p>Privilege escalation attempt recorded!</p>\n";

// Check what was recorded
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

$conn->close();
?>