<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Prevent browser from caching authenticated pages
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
// Enforce session/role check for chief-staff
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'chief-staff') {
    header('Location: index.php');
    exit();
}
require_once 'session_check.php';
check_login(['chief-staff']);
require_once 'db.php';
require_once 'simple_rsa_crypto.php';
require_once 'security_decrypt.php';

// Get data from database
$patients_result = $conn->query("SELECT * FROM patients WHERE status != 'discharged' ORDER BY admission_date DESC LIMIT 20");
if (!$patients_result) {
    die("SQL Error (patients): " . $conn->error);
}
$patients = $patients_result->fetch_all(MYSQLI_ASSOC);

// Decrypt patient data for chief-staff
$current_user = [
    'role' => $_SESSION['role'],
    'username' => $_SESSION['username'] ?? 'chief-staff'
];
$patients = batch_decrypt_records($patients, $current_user, 'patient');

$appointments_result = $conn->query("
    SELECT 
        a.*, 
        p.full_name AS patient_name, 
        COALESCE(s1.full_name, s2.full_name) AS staff_name
    FROM appointments a
    LEFT JOIN patients p ON a.patient_id = p.patient_id
    LEFT JOIN staff s1 ON a.doctor = s1.staff_id
    LEFT JOIN staff s2 ON a.therapist = s2.staff_id
    WHERE a.date >= CURDATE()
    ORDER BY a.date ASC, a.time ASC
    LIMIT 5
");
if (!$appointments_result) {
    die("SQL Error (appointments): " . $conn->error);
}
$appointments = $appointments_result->fetch_all(MYSQLI_ASSOC);

$staff_result = $conn->query("SELECT * FROM staff ORDER BY created_at DESC LIMIT 5");
if (!$staff_result) {
    die("SQL Error (staff): " . $conn->error);
}
$staff = $staff_result->fetch_all(MYSQLI_ASSOC);

$rooms_result = $conn->query("SELECT room_number, status, type, capacity, for_whom FROM rooms ORDER BY room_number ASC");
if (!$rooms_result) {
    die("SQL Error (rooms): " . $conn->error);
}
$rooms = $rooms_result->fetch_all(MYSQLI_ASSOC);

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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_patient'])) {
        // Process patient addition
        $name = $conn->real_escape_string($_POST['name']);
        $dob = $conn->real_escape_string($_POST['dob']);
        $gender = $conn->real_escape_string($_POST['gender']);
        $room = $conn->real_escape_string($_POST['room']);
        $emergency_contact = $conn->real_escape_string($_POST['emergency_contact']);
        $type = $conn->real_escape_string($_POST['type']);
        $mobility_status = $conn->real_escape_string($_POST['mobility_status']);
        $medical_history = $conn->real_escape_string($_POST['medical_history'] ?? '');
        $current_medications = $conn->real_escape_string($_POST['current_medications'] ?? '');
        
        // Encrypt sensitive patient data before storing
        if (!empty($medical_history)) {
            $medical_history = rsa_encrypt($medical_history);
        }
        if (!empty($current_medications)) {
            $current_medications = rsa_encrypt($current_medications);
        }
        
        // Generate patient ID
        $patient_id = 'ARC-' . date('Ymd') . '-' . rand(1000, 9999);
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Insert into users table first
            $username = 'patient_' . strtolower(explode(' ', $name)[0]) . rand(100, 999);
            $password = generateSecurePassword(12); // Temporary password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $email = $username . '@patients.local';
            
            $sql_user = "INSERT INTO users (username, password_hash, email, role, first_name, last_name, contact_number, emergency_contact, date_of_birth, address) 
                         VALUES ('$username', '$hashed_password', '$email', 'patient', '$name', '', '', '$emergency_contact', '$dob', '')";
            
            if (!$conn->query($sql_user)) {
                throw new Exception('Error creating user account: ' . $conn->error);
            }
            
            $user_id = $conn->insert_id;
            
            // Insert into patients table
            $sql_patient = "INSERT INTO patients (user_id, patient_id, full_name, date_of_birth, gender, contact_number, emergency_contact, medical_history, current_medications, admission_date, room_number, status, type, mobility_status) 
                           VALUES ($user_id, '$patient_id', '$name', '$dob', '$gender', '', '$emergency_contact', '$medical_history', '$current_medications', CURDATE(), '$room', 'admitted', '$type', '$mobility_status')";
            
            if (!$conn->query($sql_patient)) {
                throw new Exception('Error adding patient: ' . $conn->error);
            }
            
            // Update room status to occupied
            $sql_room = "UPDATE rooms SET status = 'occupied' WHERE room_number = '$room'";
            if (!$conn->query($sql_room)) {
                throw new Exception('Error updating room status: ' . $conn->error);
            }
            
            // Commit transaction
            $conn->commit();
            
            // Set success message
            $_SESSION['success_message'] = "Patient added successfully. Patient ID: $patient_id, Username: $username, Password: $password";
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $_SESSION['error_message'] = $e->getMessage();
        }
        
        // Redirect to refresh the page
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    }
    
    if (isset($_POST['add_staff'])) {
        // Process staff addition
        $name = $conn->real_escape_string($_POST['staff_name']);
        $role = $conn->real_escape_string($_POST['role']);
        $email = strtolower(str_replace(' ', '.', $name)) . '@' . ($role === 'doctor' ? 'doctor.com' : ($role === 'therapist' ? 'therapist.com' : 'chief-staff.com'));
        
        // Check for duplicate email
        $counter = 1;
        while (true) {
            $result = $conn->query("SELECT id FROM staff WHERE email = '$email'");
            if ($result && $result->num_rows > 0) {
                $email = strtolower(str_replace(' ', '.', $name)) . $counter . '@' . ($role === 'doctor' ? 'doctor.com' : ($role === 'therapist' ? 'therapist.com' : 'chief-staff.com'));
                $counter++;
            } else {
                break;
            }
        }
        
        // Generate staff ID
        $staff_id = 'STF-' . date('Ymd') . '-' . rand(1000, 9999);
        
        $conn->query("INSERT INTO staff (staff_id, name, role, email, status) VALUES ('$staff_id', '$name', '$role', '$email', 'Active')");
    }
    
    // Handle add room
    if (isset($_POST['add_room'])) {
        $room_number = $conn->real_escape_string($_POST['room_number']);
        $status = $conn->real_escape_string($_POST['status']);
        $type = $conn->real_escape_string($_POST['type']);
        $capacity = intval($_POST['capacity']);
        $for_whom = $conn->real_escape_string($_POST['for']);
        $conn->query("INSERT INTO rooms (room_number, status, type, capacity, for_whom) VALUES ('$room_number', '$status', '$type', $capacity, '$for_whom')");
    }
    
    // Handle edit room
    if (isset($_POST['edit_room'])) {
        $room_number = $conn->real_escape_string($_POST['edit_room_number']);
        $status = $conn->real_escape_string($_POST['edit_status']);
        $type = $conn->real_escape_string($_POST['edit_type']);
        $capacity = intval($_POST['edit_capacity']);
        $for_whom = $conn->real_escape_string($_POST['edit_for']);
        $conn->query("UPDATE rooms SET status='$status', type='$type', capacity=$capacity, for_whom='$for_whom' WHERE room_number='$room_number'");
    }
    
    // Handle delete room
    if (isset($_POST['delete_room'])) {
        $room_number = $conn->real_escape_string($_POST['delete_room_number']);
        $conn->query("DELETE FROM rooms WHERE room_number='$room_number'");
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script>
    if (window.performance && window.performance.navigation.type === 2) {
        window.location.reload(true);
    }
    if (!document.cookie.includes('PHPSESSID')) {
        window.location.href = 'index.php';
    }
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chief Staff Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3f37c9;
            --accent: #4895ef;
            --light: #f8f9fa;
            --dark: #212529;
            --success: #4cc9f0;
            --warning: #f8961e;
            --danger: #f72585;
            --gray: #6c757d;
            --light-gray: #e9ecef;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            background-color: #f5f7fb;
            color: var(--dark);
            overflow-x: hidden;
        }
        
        .dashboard {
            display: block;
            min-height: 100vh;
        }
        
        /* Sidebar collapsed/expanded styles */
        .sidebar {
            position: fixed; top: 0; left: 0; height: 100vh; background: #4361ee; color: #fff; box-shadow: 2px 0 10px rgba(0,0,0,0.1); z-index: 1000; transition: width 0.3s ease-in-out; overflow-y: auto; padding: 20px 0; width: 16rem; /* expanded by default */ }
        .sidebar.collapsed { width: 4.5rem; }
        .sidebar-header, .sidebar-menu span, .sidebar-header h2, .sidebar-header p { transition: opacity 0.2s ease-in-out; white-space: nowrap; }
        .sidebar.collapsed .sidebar-header, .sidebar.collapsed .sidebar-menu span, .sidebar.collapsed .sidebar-header h2, .sidebar.collapsed .sidebar-header p { opacity: 0; visibility: hidden; }
        .sidebar.collapsed .sidebar-header { justify-content: center; }
        .sidebar .sidebar-header { text-align: center; margin-bottom: 10px; }
        .sidebar .sidebar-header img { display: block; margin: 0 auto 10px auto; }
        .sidebar .menu-item { justify-content: flex-start; align-items: center; min-height: 54px; height: 54px; display: flex; font-size: 1.1rem; transition: background 0.2s, color 0.2s; padding-left: 1.25rem; }
        .sidebar.collapsed .menu-item { justify-content: center; padding-left: 0; }
        .sidebar .menu-item i { margin-right: 14px; font-size: 1.45rem; min-width: 32px; text-align: center; }
        .sidebar.collapsed .menu-item i { margin-right: 0; }
        .sidebar .menu-item span { display: inline; }
        .sidebar.collapsed .menu-item span { display: none; }
        .main-content { margin-left: 16rem; transition: margin-left 0.3s ease-in-out; padding-top: 76px; }
        .sidebar.collapsed ~ .main-content { margin-left: 4.5rem; }
        .topbar { margin-left: 0; transition: margin-left 0.3s ease-in-out; display: flex; align-items: center; height: 64px; background: #f5f7fb; box-shadow: 0 2px 10px rgba(67,97,238,0.07); z-index: 1100; position: fixed; top: 0; left: 0; width: 100vw; padding: 0 32px; }
        .sidebar.collapsed ~ .topbar { margin-left: 4.5rem; }
        .topbar-content { display: flex; align-items: center; gap: 18px; width: 100%; height: 64px; }
        .topbar-title { font-size: 2rem; font-weight: 700; color: var(--primary); white-space: nowrap; line-height: 1; }
        @media (max-width: 768px) {
            .sidebar { width: 4.5rem; }
            .sidebar.collapsed { width: 4.5rem; }
            .main-content, .topbar { margin-left: 4.5rem; }
            .main-content { padding-top: 76px; }
        }
        
        /* Main Content Styles */
        .main-content {
            padding: 20px;
            margin-top: 60px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .header h1 {
            font-size: 1.8rem;
            color: var(--primary);
        }
        
        .user-info {
            display: flex;
            align-items: center;
        }
        
        .user-info img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 10px;
            object-fit: cover;
        }
        
        /* Cards Grid */
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 16px rgba(67,97,238,0.07);
            padding: 24px 24px 20px 24px;
            position: relative;
            overflow: hidden;
            min-height: 120px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .card::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            width: 7px;
            height: 100%;
            border-radius: 16px 0 0 16px;
            background: var(--primary);
        }
        .card.patients::before { background: linear-gradient(180deg, #4895ef, #4361ee); }
        .card.staff::before { background: linear-gradient(180deg, #4cc9f0, #4895ef); }
        .card.appointments::before { background: linear-gradient(180deg, #f8961e, #f9c74f); }
        .card.rooms::before { background: linear-gradient(180deg, #b5179e, #7209b7); }
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }
        .card-title {
            font-size: 1rem;
            font-weight: 600;
            color: #6c757d;
            margin-bottom: 0.5rem;
        }
        .card-value {
            font-size: 2.1rem;
            font-weight: 700;
            color: #212529;
            margin-bottom: 2px;
        }
        .card-icon-bg {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .card.patients .card-icon-bg { background: rgba(67, 97, 238, 0.12); }
        .card.staff .card-icon-bg { background: rgba(72, 149, 239, 0.12); }
        .card.appointments .card-icon-bg { background: rgba(248, 150, 30, 0.12); }
        .card.rooms .card-icon-bg { background: rgba(114, 9, 183, 0.12); }
        .card-icon {
            font-size: 2rem;
        }
        .card.patients .card-icon { color: #4361ee; }
        .card.staff .card-icon { color: #4895ef; }
        .card.appointments .card-icon { color: #f8961e; }
        .card.rooms .card-icon { color: #b5179e; }
        
        /* Tables */
        .section {
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .section-title {
            font-size: 1.2rem;
            font-weight: 600;
        }
        
        .btn {
            padding: 8px 15px;
            border-radius: 5px;
            border: none;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
        }
        
        .btn i {
            margin-right: 5px;
        }
        
        .btn-primary {
            background-color: #4361ee;
            color: #fff;
        }
        
        .btn-primary:hover {
            background-color: #3f37c9;
        }
        
        .btn-outline {
            background-color: transparent;
            border: 1px solid var(--primary);
            color: var(--primary);
        }
        
        .btn-outline:hover {
            background-color: var(--primary);
            color: white;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--light-gray);
        }
        
        th {
            font-weight: 600;
            color: var(--gray);
            font-size: 0.85rem;
            text-transform: uppercase;
        }
        
        tr:hover {
            background-color: var(--light-gray);
        }
        
        .status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .status-active {
            background-color: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }
        
        .status-inactive {
            background-color: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }
        
        .status-pending {
            background-color: rgba(255, 193, 7, 0.1);
            color: #ffc107;
        }
        
        /* Forms */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 100;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }
        
        .modal.active {
            opacity: 1;
            visibility: visible;
        }
        
        .modal-content {
            background-color: white;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            transform: translateY(20px);
            transition: all 0.3s ease;
        }
        
        .modal.active .modal-content {
            transform: translateY(0);
        }
        
        .modal-header {
            padding: 15px 20px;
            border-bottom: 1px solid var(--light-gray);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-title {
            font-size: 1.2rem;
            font-weight: 600;
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--gray);
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid var(--light-gray);
            border-radius: 5px;
            font-size: 0.9rem;
            transition: border-color 0.3s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #4361ee;
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.35);
        }
        
        .modal-footer {
            padding: 15px 20px;
            border-top: 1px solid var(--light-gray);
            display: flex;
            justify-content: flex-end;
        }
        
        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .fade-in {
            animation: fadeIn 0.5s ease forwards;
        }
        
        @keyframes slideIn {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .slide-in {
            animation: slideIn 0.5s ease forwards;
        }
        
        .delay-1 { animation-delay: 0.1s; }
        .delay-2 { animation-delay: 0.2s; }
        .delay-3 { animation-delay: 0.3s; }
        .delay-4 { animation-delay: 0.4s; }
        
        /* Responsive */
        @media (max-width: 992px) {
            .dashboard {
                grid-template-columns: 70px 1fr;
            }
            .sidebar-header h2, .menu-item span {
                display: none;
            }
            .menu-item {
                justify-content: center;
            }
            .menu-item i {
                margin-right: 0;
                font-size: 1.3rem;
            }
        }
        
        @media (max-width: 768px) {
            .cards-grid {
                grid-template-columns: 1fr 1fr;
            }
        }
        
        @media (max-width: 576px) {
            .cards-grid {
                grid-template-columns: 1fr;
            }
            .dashboard {
                grid-template-columns: 1fr;
            }
            .main-content {
                margin-bottom: 60px;
            }
        }
        
        .submenu {
            display: none;
            flex-direction: column;
            background:rgb(80, 79, 98);
            padding: 0 0 0 20px;
            border-radius: 0 0 8px 8px;
            margin-bottom: 10px;
        }
        .submenu .submenu-item {
            color: #fff;
            padding: 10px 0;
            text-decoration: none;
            font-size: 1rem;
            transition: background 0.2s;
            display: block;
        }
        .submenu .submenu-item:hover {
            background:rgb(85, 87, 97);
            color: #fff;
            border-radius: 4px;
        }
        .menu-item.active, .menu-item:focus {
            background: rgba(255,255,255,0.08);
            border-radius: 8px 8px 0 0;
        }
        
        .sidebar .menu-item, .sidebar .menu-item * {
            color: inherit !important;
            text-decoration: none !important;
        }
        .sidebar .menu-item.active, .sidebar .menu-item.active * {
            color: inherit !important;
            text-decoration: none !important;
        }
        .btn-primary, .btn-primary *, .btn-primary a {
            text-decoration: none !important;
        }
        
        .user-profile {
            position: relative;
            cursor: pointer;
        }
        
        .user-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            min-width: 150px;
            display: none;
            z-index: 1000;
        }
        
        .user-profile:hover .user-dropdown {
            display: block;
        }
        
        .dropdown-item {
            display: flex;
            align-items: center;
            padding: 10px 15px;
            color: var(--gray-dark);
            text-decoration: none;
            transition: var(--transition);
        }
        
        .dropdown-item:hover {
            background: rgba(67, 97, 238, 0.05);
            color: var(--primary);
        }
        
        .dropdown-item i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary, #4361ee);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1.1rem;
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <!-- Topbar -->
    <div class="topbar">
        <div class="topbar-content">
            <button id="sidebar-toggle" class="text-gray-100 hover:text-white focus:outline-none" style="background:none;border:none;font-size:1.7rem;cursor:pointer;">
                <i class="fas fa-bars"></i>
            </button>
            <img src="https://img.icons8.com/ios-filled/40/4361ee/hospital-room.png" alt="Logo" style="height:40px;width:40px;object-fit:contain;vertical-align:middle;">
            <span class="topbar-title">United Medical Asylum & Rehab Facility</span>
        </div>
    </div>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <div class="dashboard">
        <!-- Sidebar -->
        <aside id="sidebar" class="sidebar expanded">
            <button class="close-sidebar" id="closeSidebarBtn"><i class="fas fa-times"></i></button>
            <div style="text-align:center; margin-bottom: 10px;">
                <img src="https://img.icons8.com/ios-filled/60/ffffff/hospital-room.png" alt="Logo" style="width:56px;height:56px;margin-top:25px;margin-bottom:10px;">
            </div>
            <div class="sidebar-header">
                <!-- Removed hospital and chief staff name -->
            </div>
            
            <div class="sidebar-menu">
                <div class="menu-item active">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </div>
                <a href="patient_management.php" class="menu-item">
                    <i class="fas fa-user-injured"></i>
                    <span>Patients</span>
                </a>
                <div class="menu-item" id="staffMenu" style="cursor: pointer;">
                    <i class="fas fa-users"></i>
                    <span>Staff</span>
                </div>
                <div class="submenu" id="staffSubmenu" style="display:none;">
                    <a href="manage_staff.php" class="submenu-item">Manage Staff</a>
                    <a href="add_doctor.php" class="submenu-item">Add Doctor</a>
                    <a href="add_therapist.php" class="submenu-item">Add Therapist</a>
                    <a href="add_staff_member.php" class="submenu-item">Add Staff Member</a>
                </div>
                <a href="room_management.php" class="menu-item">
                    <i class="fas fa-bed"></i>
                    <span>Rooms</span>
                </a>
                <a href="meal_plan_management.php" class="menu-item">
                    <i class="fas fa-utensils"></i>
                    <span>Meal Plans</span>
                </a>
                <a href="export_requests.php" class="menu-item">
                    <i class="fas fa-shield-alt"></i>
                    <span>Export Requests</span>
                </a>
                <a href="logout.php" class="menu-item">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </aside>
        
        <!-- Main Content -->
        <div class="main-content" id="main-content">
            <div class="header" style="margin-bottom: 10px; padding-bottom: 0; display: flex; align-items: center; justify-content: space-between;">
                <h1 class="slide-in" style="margin-bottom: 0;">Chief Staff Dashboard</h1>
                <div style="display: flex; align-items: center; gap: 16px;">
                    <div class="user-profile">
                        <div class="user-avatar">CS</div>
                        <i class="fas fa-chevron-down"></i>
                        <div class="user-dropdown">
                            <a href="logout.php" class="dropdown-item">
                                <i class="fas fa-sign-out-alt"></i>
                                Logout
                            </a>
                        </div>
                    </div>
                    <span class="user-name" style="font-weight:600; font-size:1rem;"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Chief Staff'); ?></span>
                </div>
            </div>
            
            <!-- Add this right after the header section -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success" style="background-color: #d4edda; color: #155724; padding: 15px; margin-bottom: 20px; border-radius: 5px; border: 1px solid #c3e6cb;">
                    <?php 
                    echo $_SESSION['success_message'];
                    unset($_SESSION['success_message']);
                    ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger" style="background-color: #f8d7da; color: #721c24; padding: 15px; margin-bottom: 20px; border-radius: 5px; border: 1px solid #f5c6cb;">
                    <?php 
                    echo $_SESSION['error_message'];
                    unset($_SESSION['error_message']);
                    ?>
                </div>
            <?php endif; ?>
            
            <!-- Stats Cards -->
            <div class="cards-grid">
                <div class="card patients fade-in">
                    <div class="card-header">
                        <div>
                            <div class="card-title">Patients</div>
                            <div class="card-value"><?php echo count($patients); ?></div>
                        </div>
                        <div class="card-icon-bg">
                            <i class="fas fa-user-injured card-icon"></i>
                        </div>
                    </div>
                </div>
                <div class="card staff fade-in delay-1">
                    <div class="card-header">
                        <div>
                            <div class="card-title">Staff</div>
                            <div class="card-value"><?php echo count($staff); ?></div>
                        </div>
                        <div class="card-icon-bg">
                            <i class="fas fa-users card-icon"></i>
                        </div>
                    </div>
                </div>
                <div class="card appointments fade-in delay-2">
                    <div class="card-header">
                        <div>
                            <div class="card-title">Appointments</div>
                            <div class="card-value"><?php echo count($appointments); ?></div>
                        </div>
                        <div class="card-icon-bg">
                            <i class="fas fa-calendar-check card-icon"></i>
                        </div>
                    </div>
                </div>
                <div class="card rooms fade-in delay-3">
                    <div class="card-header">
                        <div>
                            <div class="card-title">Available Rooms</div>
                            <div class="card-value"><?php echo count(array_filter($rooms, function($room) { return $room['status'] === 'available'; })); ?></div>
                        </div>
                        <div class="card-icon-bg">
                            <i class="fas fa-bed card-icon"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Staff Members Summary Box -->
            <div class="section" style="margin-bottom: 30px;">
                <div class="section-header">
                    <h2 class="section-title">Staff Members</h2>
                </div>
                <table style="width:100%;border-collapse:collapse;">
                    <thead>
                        <tr style="background:#f5f7fb;">
                            <th style="padding:12px 15px;text-align:left;">Staff ID</th>
                            <th style="padding:12px 15px;text-align:left;">Name</th>
                            <th style="padding:12px 15px;text-align:left;">Role</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($staff as $member): ?>
                        <tr>
                            <td style="padding:12px 15px;"> <?php echo htmlspecialchars($member['staff_id'] ?? $member['id'] ?? ''); ?> </td>
                            <td style="padding:12px 15px;"> <?php echo htmlspecialchars($member['name'] ?? $member['full_name'] ?? ''); ?> </td>
                            <td style="padding:12px 15px;"> <?php echo htmlspecialchars($member['role']); ?> </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Room Management Section -->
            <div class="section" style="margin-bottom: 30px;">
                <div class="section-header">
                    <h2 class="section-title">Room Management</h2>
                    <a href="room_management.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add Room</a>
                </div>
                <table style="width:100%;border-collapse:collapse;">
                    <thead>
                        <tr style="background:#f5f7fb;">
                            <th style="padding:8px 8px; font-size:0.98rem;">Room Number</th>
                            <th style="padding:8px 8px; font-size:0.98rem;">Status</th>
                            <th style="padding:8px 8px; font-size:0.98rem;">Type</th>
                            <th style="padding:8px 8px; font-size:0.98rem;">Capacity</th>
                            <th style="padding:8px 8px; font-size:0.98rem;">For</th>
                            <th style="padding:8px 8px; font-size:0.98rem;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rooms as $room): ?>
                        <tr>
                            <td style="padding:8px 8px; font-size:0.98rem;"> <?php echo htmlspecialchars($room['room_number'] ?? '-'); ?> </td>
                            <td style="padding:8px 8px; font-size:0.98rem;">
                                <?php
                                    $status = $room['status'] ?? '';
                                    $badgeColor = '';
                                    if ($status === 'available') $badgeColor = 'background:#e6f9ed;color:#28a745;font-weight:600;padding:3px 12px;border-radius:12px;';
                                    elseif ($status === 'occupied') $badgeColor = 'background:#ffe0ef;color:#d72660;font-weight:600;padding:3px 12px;border-radius:12px;';
                                    elseif ($status === 'maintenance') $badgeColor = 'background:#e6eafc;color:#3f37c9;font-weight:600;padding:3px 12px;border-radius:12px;';
                                ?>
                                <span style="<?php echo $badgeColor; ?>">
                                    <?php echo htmlspecialchars($status ?: '-'); ?>
                                </span>
                            </td>
                            <td style="padding:8px 8px; font-size:0.98rem;">
                                <?php $type = ($room['type'] ?? '') !== '' ? $room['type'] : '-'; ?>
                                <?php if ($type !== '-') {
                                    $typeColor = ($type === 'Normal') ? 'background:#e3f0ff;color:#4361ee;' :
                                        (($type === 'Deluxe') ? 'background:#fff4e6;color:#f8961e;' : 'background:#eee;color:#888;');
                                ?>
                                    <span style="<?php echo $typeColor; ?>font-weight:600;padding:3px 12px;border-radius:12px;">
                                        <?php echo htmlspecialchars($type); ?>
                                    </span>
                                <?php } else { echo '-'; } ?>
                            </td>
                            <td style="padding:8px 8px; font-size:0.98rem;">
                                <?php $capacity = ($room['capacity'] ?? '') !== '' ? $room['capacity'] : '-'; ?>
                                <?php if ($capacity !== '-') {
                                    $capColor = ($capacity == 1) ? 'background:#f1f3f4;color:#6c757d;' :
                                        (($capacity == 2) ? 'background:#e6fcf7;color:#20c997;' :
                                        (($capacity == 3) ? 'background:#e3f0ff;color:#4361ee;' :
                                        (($capacity >= 4) ? 'background:#fff4e6;color:#f8961e;' : 'background:#eee;color:#888;')));
                                ?>
                                    <span style="<?php echo $capColor; ?>font-weight:600;padding:3px 12px;border-radius:12px;">
                                        <?php echo htmlspecialchars($capacity); ?>
                                    </span>
                                <?php } else { echo '-'; } ?>
                            </td>
                            <td style="padding:8px 8px; font-size:0.98rem;">
                                <?php $for = ($room['for_whom'] ?? '') !== '' ? $room['for_whom'] : '-'; ?>
                                <?php if ($for !== '-') {
                                    $forColor = ($for === 'Patients') ? 'background:#f3e9ff;color:#7209b7;' :
                                        (($for === 'Chamber for Doctor') ? 'background:#e6fcf7;color:#20c997;' :
                                        (($for === 'Therapy') ? 'background:#fff4e6;color:#f8961e;' : 'background:#eee;color:#888;'));
                                ?>
                                    <span style="<?php echo $forColor; ?>font-weight:600;padding:3px 12px;border-radius:12px;">
                                        <?php echo htmlspecialchars($for); ?>
                                    </span>
                                <?php } else { echo '-'; } ?>
                            </td>
                            <td style="padding:8px 8px; font-size:0.98rem;">
                                <button class="btn btn-outline btn-edit-room" data-room='<?php echo json_encode($room); ?>' style="padding:4px 8px;font-size:0.8rem;"><i class="fas fa-edit"></i></button>
                                <button class="btn btn-outline btn-delete-room" data-room-number="<?php echo htmlspecialchars($room['room_number'] ?? ''); ?>" style="padding:4px 8px;font-size:0.8rem;color:#dc3545;"><i class="fas fa-trash"></i></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Recent Patients Section -->
            <div class="section slide-in delay-1">
                <div class="section-header">
                    <h2 class="section-title">Recent Patients</h2>
                    <button class="btn btn-primary" id="addPatientBtn">
                        <i class="fas fa-plus"></i> Add Patient
                    </button>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>Patient ID</th>
                            <th>Name</th>
                            <th>Room</th>
                            <th>Status</th>
                            <th>Admission Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($patients as $patient): ?>
                        <tr>
                            <td><?php echo $patient['patient_id']; ?></td>
                            <td><?php echo $patient['full_name'] ?? '-'; ?></td>
                            <td><?php echo $patient['room_number'] ?? '-'; ?></td>
                            <td>
                                <span class="status status-<?php echo strtolower($patient['status']); ?>">
                                    <?php echo ucfirst($patient['status']); ?>
                                </span>
                            </td>
                            <td><?php echo $patient['admission_date']; ?></td>
                            <td>
                                <button class="btn btn-outline" style="padding: 5px 10px; font-size: 0.8rem;">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Upcoming Appointments -->
            <div class="section slide-in delay-2">
                <div class="section-header">
                    <h2 class="section-title">Upcoming Appointments</h2>
                    <button class="btn btn-outline">
                        <i class="fas fa-calendar-plus"></i> Schedule
                    </button>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Type</th>
                            <th>Staff</th>
                            <th>Date & Time</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($appointments as $appt): ?>
                        <tr>
                            <td><?php echo $appt['patient_name']; ?></td>
                            <td><?php echo $appt['type']; ?></td>
                            <td><?php echo $appt['staff_name']; ?></td>
                            <td><?php echo date('M j, Y g:i A', strtotime($appt['date'])); ?></td>
                            <td>
                                <span class="status status-<?php echo strtolower($appt['status']); ?>">
                                    <?php echo $appt['status']; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Current Meal Plans -->
            <div class="section slide-in delay-3">
                <div class="section-header">
                    <h2 class="section-title">Current Meal Plans</h2>
                    <div style="display: flex; gap: 10px;">
                        <a href="meal_plan_management.php" class="btn btn-primary">
                            <i class="fas fa-utensils"></i> Create Plan
                        </a>
                    </div>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>Plan Name</th>
                            <th>Diet Type</th>
                            <th>Patients</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (!isset($meal_plans) || !is_array($meal_plans)) {
                            $meal_plans = [];
                        }
                        foreach ($meal_plans as $plan): ?>
                        <tr>
                            <td><?php echo $plan['name']; ?></td>
                            <td><?php echo $plan['diet_type']; ?></td>
                            <td><?php echo $plan['patient_count']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Add Patient Modal -->
    <div class="modal" id="addPatientModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Add New Patient</h3>
                <button class="close-modal">&times;</button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="name">Full Name</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="dob">Date of Birth</label>
                        <input type="date" class="form-control" id="dob" name="dob" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="gender">Gender</label>
                        <select class="form-control" id="gender" name="gender" required>
                            <option value="">Select Gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="room">Room Assignment</label>
                        <select class="form-control" id="room" name="room" required>
                            <option value="">Select Room</option>
                            <?php foreach ($rooms as $room): ?>
                                <?php if ($room['status'] === 'available'): ?>
                                    <option value="<?php echo $room['room_number']; ?>">Room <?php echo $room['room_number']; ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="emergency_contact">Emergency Contact</label>
                        <input type="tel" class="form-control" id="emergency_contact" name="emergency_contact" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="type">Patient Type</label>
                        <select class="form-control" id="type" name="type" required>
                            <option value="">Select Type</option>
                            <option value="Asylum">Asylum</option>
                            <option value="Rehabilitation">Rehabilitation</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="mobility_status">Mobility Status</label>
                        <select class="form-control" id="mobility_status" name="mobility_status" required>
                            <option value="">Select Status</option>
                            <option value="Independent">Independent</option>
                            <option value="Assisted">Assisted</option>
                            <option value="Wheelchair">Wheelchair</option>
                            <option value="Bedridden">Bedridden</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="medical_history">Medical History</label>
                        <textarea class="form-control" id="medical_history" name="medical_history" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="current_medications">Current Medications</label>
                        <textarea class="form-control" id="current_medications" name="current_medications" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline close-modal">Cancel</button>
                    <button type="submit" name="add_patient" class="btn btn-primary">Add Patient</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Add Room Modal -->
    <div class="modal" id="addRoomModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Add New Room</h3>
                <button class="close-modal">&times;</button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="room_number">Room Number</label>
                        <input type="text" class="form-control" id="room_number" name="room_number" required>
                    </div>
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select class="form-control" id="status" name="status" required>
                            <option value="available">Available</option>
                            <option value="occupied">Occupied</option>
                            <option value="maintenance">Maintenance</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="type">Type</label>
                        <select class="form-control" id="type" name="type" required>
                            <option value="Normal">Normal</option>
                            <option value="Deluxe">Deluxe</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="capacity">Capacity</label>
                        <input type="number" class="form-control" id="capacity" name="capacity" min="1" value="1" required>
                    </div>
                    <div class="form-group">
                        <label for="for">For</label>
                        <select class="form-control" id="for" name="for" required>
                            <option value="Patients">Patients</option>
                            <option value="Chamber for Doctor">Chamber for Doctor</option>
                            <option value="Therapy">Therapy</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline close-modal">Cancel</button>
                    <button type="submit" name="add_room" class="btn btn-primary">Add Room</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit Room Modal -->
    <div class="modal" id="editRoomModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Edit Room</h3>
                <button class="close-modal">&times;</button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="edit_room_number">Room Number</label>
                        <input type="text" class="form-control" id="edit_room_number" name="edit_room_number" readonly required>
                    </div>
                    <div class="form-group">
                        <label for="edit_status">Status</label>
                        <select class="form-control" id="edit_status" name="edit_status" required>
                            <option value="available">Available</option>
                            <option value="occupied">Occupied</option>
                            <option value="maintenance">Maintenance</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_type">Type</label>
                        <input type="text" class="form-control" id="edit_type" name="edit_type" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_capacity">Capacity</label>
                        <input type="number" class="form-control" id="edit_capacity" name="edit_capacity" min="1" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_for">For</label>
                        <select class="form-control" id="edit_for" name="edit_for" required>
                            <option value="Patients">Patients</option>
                            <option value="Chamber for Doctor">Chamber for Doctor</option>
                            <option value="Therapy">Therapy</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline close-modal">Cancel</button>
                    <button type="submit" name="edit_room" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Delete Room Modal -->
    <div class="modal" id="deleteRoomModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Delete Room</h3>
                <button class="close-modal">&times;</button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <p>Are you sure you want to delete room <span id="deleteRoomNumberDisplay"></span>?</p>
                    <input type="hidden" id="delete_room_number" name="delete_room_number">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline close-modal">Cancel</button>
                    <button type="submit" name="delete_room" class="btn btn-primary" style="background:#dc3545;border:none;">Delete</button>
                </div>
            </form>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarToggle = document.getElementById('sidebar-toggle');
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('main-content');
            // Function to toggle sidebar
            function toggleSidebar() {
                if (sidebar.classList.contains('collapsed')) {
                    sidebar.classList.remove('collapsed');
                    sidebar.classList.add('expanded');
                } else {
                    sidebar.classList.remove('expanded');
                    sidebar.classList.add('collapsed');
                }
            }
            // Toggle sidebar on button click
            sidebarToggle.addEventListener('click', toggleSidebar);
            // Handle window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth < 768) {
                    if (!sidebar.classList.contains('collapsed')) {
                        sidebar.classList.remove('expanded');
                        sidebar.classList.add('collapsed');
                    }
                }
            });
            // Start with expanded sidebar on desktop
            if (window.innerWidth < 768) {
                sidebar.classList.remove('expanded');
                sidebar.classList.add('collapsed');
            }
        });
        
        // DOM Elements
        const addPatientBtn = document.getElementById('addPatientBtn');
        const addPatientModal = document.getElementById('addPatientModal');
        const closeModalBtns = document.querySelectorAll('.close-modal');
        
        // Show Add Patient Modal
        addPatientBtn.addEventListener('click', () => {
            addPatientModal.classList.add('active');
        });
        
        // Close Modals
        closeModalBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.modal').forEach(modal => {
                    modal.classList.remove('active');
                });
            });
        });
        
        // Close modal when clicking outside
        window.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal')) {
                e.target.classList.remove('active');
            }
        });
        
        // Menu item active state
        const menuItems = document.querySelectorAll('.menu-item');
        menuItems.forEach(item => {
            item.addEventListener('click', () => {
                menuItems.forEach(i => i.classList.remove('active'));
                item.classList.add('active');
            });
        });
        
        // Animation on scroll
        const animateOnScroll = () => {
            const sections = document.querySelectorAll('.section');
            
            sections.forEach(section => {
                const sectionTop = section.getBoundingClientRect().top;
                const windowHeight = window.innerHeight;
                
                if (sectionTop < windowHeight * 0.75) {
                    section.classList.add('slide-in');
                }
            });
        };
        
        window.addEventListener('scroll', animateOnScroll);
        animateOnScroll(); // Run once on load
        
        // Sidebar submenu toggle for staff
        const staffMenu = document.getElementById('staffMenu');
        const staffSubmenu = document.getElementById('staffSubmenu');
        staffMenu.addEventListener('click', function(e) {
            e.stopPropagation();
            staffSubmenu.style.display = staffSubmenu.style.display === 'block' ? 'none' : 'block';
        });
        document.addEventListener('click', function(e) {
            if (!staffMenu.contains(e.target)) {
                staffSubmenu.style.display = 'none';
            }
        });
        
        // Room Management Modal Logic
        const addRoomBtn = document.getElementById('addRoomBtn');
        const addRoomModal = document.getElementById('addRoomModal');
        if (addRoomBtn && addRoomModal) {
            addRoomBtn.addEventListener('click', () => {
                addRoomModal.classList.add('active');
            });
        }
        document.querySelectorAll('.close-modal').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.modal').forEach(modal => {
                    modal.classList.remove('active');
                });
            });
        });
        window.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal')) {
                e.target.classList.remove('active');
            }
        });
        
        // Edit Room Modal Logic
        document.querySelectorAll('.btn-edit-room').forEach(btn => {
            btn.addEventListener('click', function() {
                const room = JSON.parse(this.getAttribute('data-room'));
                document.getElementById('edit_room_number').value = room.room_number;
                document.getElementById('edit_status').value = room.status;
                document.getElementById('edit_type').value = room.type;
                document.getElementById('edit_capacity').value = room.capacity;
                document.getElementById('edit_for').value = room.for_whom;
                document.getElementById('editRoomModal').classList.add('active');
            });
        });
        
        // Delete Room Modal Logic
        document.querySelectorAll('.btn-delete-room').forEach(btn => {
            btn.addEventListener('click', function() {
                const roomNumber = this.getAttribute('data-room-number');
                document.getElementById('delete_room_number').value = roomNumber;
                document.getElementById('deleteRoomNumberDisplay').textContent = roomNumber;
                document.getElementById('deleteRoomModal').classList.add('active');
            });
        });

    </script>
</body>
</html>
<?php
$conn->close();
?>