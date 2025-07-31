<?php
// Suppress error reporting to prevent HTML errors
error_reporting(0);
ini_set('display_errors', 0);

require_once 'db.php';

$results = [];

// Test database connection
if ($conn->connect_error) {
    $results['connection'] = 'Failed: ' . $conn->connect_error;
} else {
    $results['connection'] = 'Success';
}

// Check if required tables exist
$tables = ['users', 'patients', 'rooms', 'appointments', 'progress_notes'];
foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result && $result->num_rows > 0) {
        $results[$table] = 'Exists';
        
        // Check table structure
        $columns = $conn->query("DESCRIBE $table");
        if ($columns) {
            $column_names = [];
            while ($row = $columns->fetch_assoc()) {
                $column_names[] = $row['Field'];
            }
            $results[$table . '_columns'] = $column_names;
        }
    } else {
        $results[$table] = 'Missing';
    }
}

// Test a simple query
$test_query = $conn->query("SELECT COUNT(*) as count FROM patients");
if ($test_query) {
    $count = $test_query->fetch_assoc()['count'];
    $results['patient_count'] = $count;
} else {
    $results['patient_count'] = 'Error: ' . $conn->error;
}

// Output results
header('Content-Type: application/json');
echo json_encode($results, JSON_PRETTY_PRINT);
?> 