<?php
require_once 'session_check.php';
check_login(['admin', 'chief-staff']);
require_once 'db.php';

// Function to generate a secure password
function generateSecurePassword($length = 12) {
    $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $lowercase = 'abcdefghijklmnopqrstuvwxyz';
    $numbers = '0123456789';
    $special = '!@#$%^&*()_+-=[]{}|;:,.<>?';
    
    $allChars = $uppercase . $lowercase . $numbers . $special;
    $password = '';
    
    // Ensure at least one character from each category
    $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
    $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
    $password .= $numbers[random_int(0, strlen($numbers) - 1)];
    $password .= $special[random_int(0, strlen($special) - 1)];
    
    // Fill the rest with random characters
    for ($i = 4; $i < $length; $i++) {
        $password .= $allChars[random_int(0, strlen($allChars) - 1)];
    }
    
    // Shuffle the password to make it more random
    return str_shuffle($password);
}

header('Content-Type: application/json');

// Validate required fields
$required_fields = ['staff_id', 'full_name', 'role', 'phone', 'dob', 'gender', 'address', 'experience', 'status'];
foreach ($required_fields as $field) {
    if (!isset($_POST[$field]) || empty($_POST[$field])) {
        echo json_encode(['error' => ucfirst(str_replace('_', ' ', $field)) . ' is required']);
        exit;
    }
}

// Sanitize and validate input
$staff_id = trim($_POST['staff_id']);
$full_name = trim($_POST['full_name']);
$role = trim($_POST['role']);
$phone = trim($_POST['phone']);
$dob = trim($_POST['dob']);
$gender = trim($_POST['gender']);
$address = trim($_POST['address']);
$experience = (int)$_POST['experience'];
$shift = isset($_POST['shift']) ? trim($_POST['shift']) : null;
$status = trim($_POST['status']);

// Validate role
$allowed_roles = ['chief-staff', 'doctor', 'therapist', 'nurse', 'receptionist'];
if (!in_array($role, $allowed_roles)) {
    echo json_encode(['error' => 'Invalid role selected']);
    exit;
}

// Validate status
$allowed_statuses = ['active', 'inactive', 'on_leave'];
if (!in_array($status, $allowed_statuses)) {
    echo json_encode(['error' => 'Invalid status selected']);
    exit;
}

// Validate shift if provided
if ($shift !== null && $shift !== '') {
    $allowed_shifts = ['Morning', 'Afternoon', 'Night'];
    if (!in_array($shift, $allowed_shifts)) {
        echo json_encode(['error' => 'Invalid shift selected']);
        exit;
    }
}

try {
    // Start transaction
    $conn->begin_transaction();

    // Get current staff data
    $check_query = "SELECT role, full_name, email FROM staff WHERE staff_id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("s", $staff_id);
    $check_stmt->execute();
    $current_data = $check_stmt->get_result()->fetch_assoc();
    $check_stmt->close();

    if (!$current_data) {
        throw new Exception("Staff member not found");
    }

    // Generate new email and password if role has changed
    $new_email = null;
    $new_password = null;
    if ($current_data['role'] != $role) {
        // Get first name for email generation
        $name_parts = explode(' ', $full_name);
        $firstName = strtolower($name_parts[0]);
        
        // Generate new email with new role
        $new_email = $firstName . '@' . strtolower($role) . '.gmail.com';
        
        // Check if email exists and append number if needed
        $email_check_query = "SELECT COUNT(*) as count FROM staff WHERE email = ? AND staff_id != ?";
        $email_check_stmt = $conn->prepare($email_check_query);
        $counter = 1;
        while (true) {
            $email_check_stmt->bind_param("ss", $new_email, $staff_id);
            $email_check_stmt->execute();
            $count = $email_check_stmt->get_result()->fetch_assoc()['count'];
            if ($count == 0) break;
            $new_email = $firstName . $counter . '@' . strtolower($role) . '.gmail.com';
            $counter++;
        }
        $email_check_stmt->close();

        // Generate new password
        $new_password = generateSecurePassword(12);
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Log the new credentials for debugging
        error_log("New Credentials - Email: $new_email, Password: $new_password");
        
        // Update staff table with new credentials
        $update_staff_query = "UPDATE staff SET 
            full_name = ?,
            role = ?,
            phone = ?,
            dob = ?,
            gender = ?,
            address = ?,
            experience = ?,
            shift = ?,
            status = ?,
            email = ?,
            password_hash = ?,
            temp_password = ?
            WHERE staff_id = ?";
        
        $update_stmt = $conn->prepare($update_staff_query);
        $update_stmt->bind_param("ssssssissssss",
            $full_name,
            $role,
            $phone,
            $dob,
            $gender,
            $address,
            $experience,
            $shift,
            $status,
            $new_email,
            $hashed_password,
            $new_password,
            $staff_id
        );
        
        if (!$update_stmt->execute()) {
            throw new Exception("Failed to update staff: " . $update_stmt->error);
        }
        $update_stmt->close();

        // Update users table with new credentials
        $update_user_query = "UPDATE users SET 
            role = ?,
            email = ?,
            password_hash = ?
            WHERE username = ?";
        
        $update_user_stmt = $conn->prepare($update_user_query);
        $update_user_stmt->bind_param("ssss",
            $role,
            $new_email,
            $hashed_password,
            $staff_id
        );
        
        if (!$update_user_stmt->execute()) {
            throw new Exception("Failed to update user: " . $update_user_stmt->error);
        }
        $update_user_stmt->close();
    } else {
        // If role hasn't changed, just update other fields
        $update_staff_query = "UPDATE staff SET 
            full_name = ?,
            phone = ?,
            dob = ?,
            gender = ?,
            address = ?,
            experience = ?,
            shift = ?,
            status = ?
            WHERE staff_id = ?";
        
        $update_stmt = $conn->prepare($update_staff_query);
        $update_stmt->bind_param("sssssssss",
            $full_name,
            $phone,
            $dob,
            $gender,
            $address,
            $experience,
            $shift,
            $status,
            $staff_id
        );
        
        if (!$update_stmt->execute()) {
            throw new Exception("Failed to update staff: " . $update_stmt->error);
        }
        $update_stmt->close();
    }

    // Commit transaction
    $conn->commit();

    // Return success response with new credentials if generated
    $response = [
        'success' => true,
        'message' => 'Staff updated successfully'
    ];

    if ($new_email && $new_password) {
        $response['newCredentials'] = [
            'email' => $new_email,
            'password' => $new_password,
            'staff_id' => $staff_id
        ];
        $response['message'] .= ". New login credentials have been generated.";
    }

    echo json_encode($response);

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    echo json_encode(['error' => $e->getMessage()]);
} finally {
    if (isset($stmt)) $stmt->close();
    if (isset($log_stmt)) $log_stmt->close();
    $conn->close();
} 