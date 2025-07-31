<?php
require_once 'session_check.php';
check_login(['admin', 'chief-staff']);
require_once 'db.php';

header('Content-Type: application/json');

if (!isset($_GET['staff_id'])) {
    echo json_encode(['error' => 'Staff ID is required']);
    exit;
}

$staff_id = $_GET['staff_id'];

// Fetch staff details
$query = "SELECT s.*, u.email 
          FROM staff s 
          LEFT JOIN users u ON s.staff_id = u.username 
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

// Format the data for the form
$response = [
    'staff_id' => $staff['staff_id'],
    'full_name' => $staff['full_name'],
    'role' => $staff['role'],
    'email' => $staff['email'],
    'phone' => $staff['phone'],
    'dob' => $staff['dob'],
    'gender' => $staff['gender'],
    'address' => $staff['address'],
    'experience' => $staff['experience'],
    'shift' => $staff['shift'],
    'status' => $staff['status']
];

echo json_encode($response);
$stmt->close();
$conn->close();
?> 