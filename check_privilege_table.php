<?php
require_once 'db.php';

// Check privilege escalation table
echo "<h2>Privilege Escalation Tracking Table</h2>\n";

$sql = "SELECT * FROM privilege_escalation_tracking ORDER BY attempt_timestamp DESC";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    echo "<p>Found " . $result->num_rows . " records:</p>\n";
    echo "<table border='1'>\n";
    echo "<tr><th>ID</th><th>User ID</th><th>Session ID</th><th>Attempted Role</th><th>Current Role</th><th>IP</th><th>Timestamp</th></tr>\n";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['user_id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['session_id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['attempted_role']) . "</td>";
        echo "<td>" . htmlspecialchars($row['current_role']) . "</td>";
        echo "<td>" . htmlspecialchars($row['ip_address']) . "</td>";
        echo "<td>" . htmlspecialchars($row['attempt_timestamp']) . "</td>";
        echo "</tr>\n";
    }
    echo "</table>\n";
} else {
    echo "<p>No records found in privilege_escalation_tracking table.</p>\n";
    if ($result === false) {
        echo "<p>Error: " . $conn->error . "</p>\n";
    }
}

// Check session tracking table
echo "<h2>Session Tracking Table (Recent)</h2>\n";

$sql = "SELECT session_id, user_id, role, ip_address, created_at, last_activity FROM session_tracking ORDER BY created_at DESC LIMIT 10";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    echo "<table border='1'>\n";
    echo "<tr><th>Session ID</th><th>User ID</th><th>Role</th><th>IP</th><th>Created</th><th>Last Activity</th></tr>\n";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars(substr($row['session_id'], 0, 20)) . "...</td>";
        echo "<td>" . htmlspecialchars($row['user_id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['role']) . "</td>";
        echo "<td>" . htmlspecialchars($row['ip_address']) . "</td>";
        echo "<td>" . htmlspecialchars($row['created_at']) . "</td>";
        echo "<td>" . htmlspecialchars($row['last_activity']) . "</td>";
        echo "</tr>\n";
    }
    echo "</table>\n";
} else {
    echo "<p>No records found in session_tracking table.</p>\n";
    if ($result === false) {
        echo "<p>Error: " . $conn->error . "</p>\n";
    }
}

$conn->close();
?>