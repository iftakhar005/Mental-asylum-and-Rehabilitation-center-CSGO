<?php
// Simple database connection test
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'asylum_db';

// Suppress warnings
$conn = @new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

echo "Connected successfully to database: $db\n\n";

// Check tables
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