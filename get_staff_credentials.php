<?php
require_once 'session_check.php';
check_login(['admin', 'chief-staff']);
require_once 'db.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if staff_id is provided
if (!isset($_GET['staff_id'])) {
    echo json_encode(['error' => 'Staff ID is required']);
    exit;
}

$staff_id = $_GET['staff_id'];

try {
    // Fetch staff credentials including the temporary password
    $query = "SELECT s.staff_id, s.email, s.temp_password, u.email as user_email 
              FROM staff s 
              LEFT JOIN users u ON s.user_id = u.id 
              WHERE s.staff_id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $staff_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['error' => 'Staff member not found']);
        exit;
    }
    
    $staff = $result->fetch_assoc();
    
    // Prepare response
    $response = [
        'staff_id' => $staff['staff_id'],
        'email' => $staff['email'] ?: $staff['user_email'],
        'password' => $staff['temp_password'] ?: 'Password not available'
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}

$stmt->close();
$conn->close();
?> 