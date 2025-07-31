<?php
require_once 'db.php';

// Test the login system
echo "<h2>Testing Login System</h2>";

// Test 1: Check if staff records are properly linked
echo "<h3>Test 1: Staff-User Linking</h3>";
$result = $conn->query("SELECT s.staff_id, s.full_name, s.email, s.user_id, u.id as user_table_id, u.username, u.email as user_email 
                       FROM staff s 
                       LEFT JOIN users u ON s.user_id = u.id 
                       WHERE s.role IN ('doctor', 'chief-staff')");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "Staff ID: " . $row['staff_id'] . "<br>";
        echo "Staff Name: " . $row['full_name'] . "<br>";
        echo "Staff Email: " . $row['email'] . "<br>";
        echo "Staff user_id: " . ($row['user_id'] ?? 'NULL') . "<br>";
        echo "User table ID: " . ($row['user_table_id'] ?? 'NULL') . "<br>";
        echo "User username: " . ($row['username'] ?? 'NULL') . "<br>";
        echo "User email: " . ($row['user_email'] ?? 'NULL') . "<br>";
        echo "Linked correctly: " . (($row['user_id'] == $row['user_table_id']) ? 'YES' : 'NO') . "<br>";
        echo "<hr>";
    }
}

// Test 2: Check password hashes
echo "<h3>Test 2: Password Hash Verification</h3>";
$result = $conn->query("SELECT s.staff_id, s.email, s.password_hash as staff_password, u.password_hash as user_password 
                       FROM staff s 
                       LEFT JOIN users u ON s.user_id = u.id 
                       WHERE s.role IN ('doctor', 'chief-staff')");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "Staff ID: " . $row['staff_id'] . "<br>";
        echo "Email: " . $row['email'] . "<br>";
        echo "Staff password hash: " . substr($row['staff_password'], 0, 20) . "...<br>";
        echo "User password hash: " . substr($row['user_password'], 0, 20) . "...<br>";
        echo "Passwords match: " . ($row['staff_password'] === $row['user_password'] ? 'YES' : 'NO') . "<br>";
        echo "<hr>";
    }
}

// Test 3: Simulate login process
echo "<h3>Test 3: Login Process Simulation</h3>";
$test_email = "a@chief-staff.gmail.com"; // Use the chief staff email
$test_password = "test123"; // This won't work, but let's see the process

echo "Testing login for email: " . $test_email . "<br>";

// Check in staff table first
$stmt = $conn->prepare("SELECT staff_id, full_name, password_hash, role FROM staff WHERE email=?");
$stmt->bind_param("s", $test_email);
$stmt->execute();
$stmt->store_result();

echo "Found rows in staff table: " . $stmt->num_rows . "<br>";

if ($stmt->num_rows === 1) {
    $stmt->bind_result($staff_id, $full_name, $hashed_password, $role);
    $stmt->fetch();
    
    echo "Staff ID: " . $staff_id . "<br>";
    echo "Full Name: " . $full_name . "<br>";
    echo "Role: " . $role . "<br>";
    echo "Password hash: " . substr($hashed_password, 0, 20) . "...<br>";
    
    // Test password verification
    $password_verify_result = password_verify($test_password, $hashed_password);
    echo "Password verification result: " . ($password_verify_result ? 'TRUE' : 'FALSE') . "<br>";
    
    // Look up user_id
    $user_id_lookup = $conn->prepare("SELECT user_id FROM staff WHERE staff_id = ?");
    $user_id_lookup->bind_param("s", $staff_id);
    $user_id_lookup->execute();
    $user_id_result = $user_id_lookup->get_result();
    $user_row = $user_id_result->fetch_assoc();
    
    if ($user_row && !empty($user_row['user_id'])) {
        echo "User ID found: " . $user_row['user_id'] . "<br>";
    } else {
        echo "User ID NOT found!<br>";
    }
} else {
    echo "No staff found with this email<br>";
}

$stmt->close();
?> 