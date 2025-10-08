<?php
require_once 'session_check.php';
check_login(['admin', 'chief-staff', 'doctor', 'nurse', 'therapist', 'receptionist', 'staff']);
require_once 'db.php';

echo "Content-Type: text/csv\n";
echo "Content-Disposition: attachment; filename=\"test_export.csv\"\n\n";

// Simple direct export without any processing
$stmt = $conn->prepare("SELECT * FROM patients LIMIT 5");
$stmt->execute();
$result = $stmt->get_result();

// Get column headers
$fields = $result->fetch_fields();
$headers = [];
foreach ($fields as $field) {
    $headers[] = $field->name;
}
echo '"' . implode('","', $headers) . '"' . "\n";

// Get data rows
while ($row = $result->fetch_assoc()) {
    $escaped_row = [];
    foreach ($row as $value) {
        $escaped_row[] = str_replace('"', '""', $value ?? '');
    }
    echo '"' . implode('","', $escaped_row) . '"' . "\n";
}

// Add watermark
echo "\n\"--- CONFIDENTIAL DATA ---\",\"\",\"\",\"\"\n";
echo "\"Downloaded by: " . ($_SESSION['username'] ?? 'Unknown') . "\",\"ID: " . ($_SESSION['user_id'] ?? 'Unknown') . "\",\"Time: " . date('Y-m-d H:i:s') . "\",\"IP: 127.0.0.1 (localhost)\"\n";
echo "\"--- UNAUTHORIZED DISTRIBUTION PROHIBITED ---\",\"\",\"\",\"\"\n";
?>