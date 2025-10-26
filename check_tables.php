<?php
require_once 'db.php';

// Check all relevant tables
$tables = [
    'privilege_escalation_tracking',
    'session_tracking',
    'propagation_incidents'
];

foreach ($tables as $table) {
    $sql = "SELECT COUNT(*) as count FROM $table";
    $result = $conn->query($sql);
    
    if ($result) {
        $row = $result->fetch_assoc();
        echo "$table: " . $row['count'] . " records\n";
    } else {
        echo "$table: Error - " . $conn->error . "\n";
    }
}

$conn->close();
?>