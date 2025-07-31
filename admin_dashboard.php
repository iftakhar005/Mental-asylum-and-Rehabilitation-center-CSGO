<?php
require_once 'db.php';
session_start();

// Fetch real data from the database
$stats = [
    'total_patients' => 0,
    'total_staff' => 0,
    'total_rooms' => 0,
    'occupied_rooms' => 0,
    'available_rooms' => 0,
    'maintenance_rooms' => 0,
    'total_appointments' => 0,
    'today_appointments' => 0,
    'total_doctors' => 0,
    'total_nurses' => 0,
    'total_therapists' => 0,
    'total_receptionists' => 0,
    'total_chief_staff' => 0
];

// Error handling function
function handle_query_error($conn, $query, $error_message) {
    if (!$conn->query($query)) {
        error_log("Database error: " . $conn->error);
        return false;
    }
    return true;
}

// Patients count and summary
$patients_result = $conn->query("SELECT p.*, u.username, u.first_name, u.last_name, u.contact_number, u.emergency_contact, u.date_of_birth, u.address FROM patients p JOIN users u ON p.user_id = u.id ORDER BY p.admission_date DESC LIMIT 5");
if ($patients_result) {
    $patients = $patients_result->fetch_all(MYSQLI_ASSOC);
    $stats['total_patients'] = count($patients);
} else {
    error_log("Error fetching patients: " . $conn->error);
    $patients = [];
}

// Staff count and summary - Updated to handle both tables
$staff_result = $conn->query("
    SELECT s.*, u.role as user_role 
    FROM staff s 
    LEFT JOIN users u ON s.user_id = u.id 
    WHERE u.role != 'admin' OR u.role IS NULL
");
if ($staff_result) {
    $staff_summary = $staff_result->fetch_all(MYSQLI_ASSOC);
    $stats['total_staff'] = count($staff_summary);
} else {
    error_log("Error fetching staff: " . $conn->error);
    $staff_summary = [];
}

// Appointments count
$appointments_result = $conn->query("SELECT * FROM appointments");
if ($appointments_result) {
    $appointments = $appointments_result->fetch_all(MYSQLI_ASSOC);
    $stats['total_appointments'] = count($appointments);
} else {
    error_log("Error fetching appointments: " . $conn->error);
    $appointments = [];
}

// Available rooms (inventory)
$rooms_result = $conn->query("SELECT * FROM rooms WHERE status = 'available'");
if ($rooms_result) {
    $available_rooms = $rooms_result->fetch_all(MYSQLI_ASSOC);
    $stats['available_rooms'] = count($available_rooms);
} else {
    error_log("Error fetching rooms: " . $conn->error);
    $available_rooms = [];
}

// Fetch yesterday's counts for daily change arrows - Updated to handle missing created_at
function get_yesterday_count($conn, $table, $date_column = 'created_at', $where = '') {
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    
    // Check if the date column exists
    $column_check = $conn->query("SHOW COLUMNS FROM $table LIKE '$date_column'");
    if ($column_check->num_rows === 0) {
        return 0; // Return 0 if the column doesn't exist
    }
    
    $query = "SELECT COUNT(*) as cnt FROM $table WHERE DATE($date_column) = '$yesterday'";
    if ($where) $query .= " AND $where";
    
    $result = $conn->query($query);
    if (!$result) {
        error_log("Error in get_yesterday_count for $table: " . $conn->error);
        return 0;
    }
    
    $row = $result->fetch_assoc();
    return (int)$row['cnt'];
}

// Get yesterday's counts with error handling
$yesterday_patients = get_yesterday_count($conn, 'patients', 'admission_date');
$yesterday_staff = get_yesterday_count($conn, 'staff', 'created_at');
$yesterday_appointments = get_yesterday_count($conn, 'appointments', 'appointment_date');
$yesterday_rooms = get_yesterday_count($conn, 'rooms', 'created_at', "status = 'available'");
function stat_arrow($today, $yesterday) {
    $diff = $today - $yesterday;
    if ($diff > 0) {
        return '<span class="card-delta up">▲ ' . $diff . '</span>';
    } elseif ($diff < 0) {
        return '<span class="card-delta down">▼ ' . abs($diff) . '</span>';
    } else {
        return '<span class="card-delta same">→ 0</span>';
    }
}

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
if (isset($_POST['generate_id'])) {
    $name = $_POST['name'];
    $dob = $_POST['dob'];
    
    $date = new DateTime($dob);
    $patient_id = 'ARC-' . $date->format('Ymd') . '-' . rand(1000, 9999);
    
    $name_parts = explode(' ', strtolower($name));
    $username = 'relative_' . substr($name_parts[0], 0, 3) . substr(end($name_parts), 0, 3) . rand(100, 999);
    $password = generateSecurePassword(12);
    
    $_SESSION['generated_data'] = [
        'patient_id' => $patient_id,
        'username' => $username,
        'password' => $password
    ];
    
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>United Medical Asylum & Rehab Facility - Admin Dashboard</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    :root {
        /* Gray Professional Theme */
        --bg-primary: #e5e7eb;
        --bg-secondary: #d1d5db;
        --bg-tertiary: #9ca3af;
        --bg-card: #f3f4f6;
        --bg-hover: #e5e7eb;
        --bg-accent: #6b7280;
        
        /* Solid Professional Colors */
        --accent-blue: #3b82f6;
        --accent-blue-light: #60a5fa;
        --accent-orange: #f97316;
        --accent-orange-light: #fb923c;
        --accent-purple: #8b5cf6;
        --accent-purple-light: #a78bfa;
        --accent-green: #10b981;
        --accent-green-light: #34d399;
        --accent-red: #ef4444;
        --accent-red-light: #f87171;
        --accent-teal: #14b8a6;
        --accent-teal-light: #2dd4bf;
        
        /* Gray Text Colors */
        --text-primary: #1f2937;
        --text-secondary: #4b5563;
        --text-muted: #6b7280;
        --text-light: #9ca3af;
        
        /* Gray Border Colors */
        --border-primary: #d1d5db;
        --border-secondary: #9ca3af;
        --border-accent: #6b7280;
        
        /* Layout */
        --sidebar-width: 280px;
        --topbar-height: 80px;
        --border-radius: 12px;
        --border-radius-lg: 16px;
        --box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
        --box-shadow-lg: 0 8px 32px rgba(0, 0, 0, 0.15);
        --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Inter', sans-serif;
        background: var(--bg-primary);
        color: var(--text-primary);
        overflow-x: hidden;
        min-height: 100vh;
    }

    /* Custom Icons matching the image */
    .custom-icon {
        display: inline-block;
        width: 1em;
        height: 1em;
        background-size: contain;
        background-repeat: no-repeat;
        background-position: center;
    }

    .icon-user {
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='currentColor'%3E%3Cpath d='M12 12C14.2091 12 16 10.2091 16 8C16 5.79086 14.2091 4 12 4C9.79086 4 8 5.79086 8 8C8 10.2091 9.79086 12 12 12ZM12 14C8.13401 14 5 17.134 5 21H19C19 17.134 15.866 14 12 14Z'/%3E%3C/svg%3E");
    }

    .icon-users {
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='currentColor'%3E%3Cpath d='M16 7C16 9.20914 14.2091 11 12 11C9.79086 11 8 9.20914 8 7C8 4.79086 9.79086 3 12 3C14.2091 3 16 4.79086 16 7ZM12 14C15.866 14 19 17.134 19 21H5C5 17.134 8.13401 14 12 14ZM20.5 7.5C20.5 8.88071 19.3807 10 18 10C16.6193 10 15.5 8.88071 15.5 7.5C15.5 6.11929 16.6193 5 18 5C19.3807 5 20.5 6.11929 20.5 7.5ZM18 12C20.7614 12 23 14.2386 23 17H20.6875C20.25 15.0625 18.75 13.5 16.875 13.125C17.5625 12.625 17.875 12.375 18 12ZM8.5 7.5C8.5 8.88071 7.38071 10 6 10C4.61929 10 3.5 8.88071 3.5 7.5C3.5 6.11929 4.61929 5 6 5C7.38071 5 8.5 6.11929 8.5 7.5ZM6 12C8.76142 12 11 14.2386 11 17H3.3125C3.75 15.0625 5.25 13.5 7.125 13.125C6.4375 12.625 6.125 12.375 6 12Z'/%3E%3C/svg%3E");
    }

    .icon-calendar {
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='currentColor'%3E%3Cpath d='M19 3H18V1H16V3H8V1H6V3H5C3.89 3 3 3.9 3 5V19C3 20.1 3.89 21 5 21H19C20.1 21 21 20.1 21 19V5C21 3.9 20.1 3 19 3ZM19 19H5V8H19V19ZM7 10H9V12H7V10ZM11 10H13V12H11V10ZM15 10H17V12H15V10ZM7 14H9V16H7V14ZM11 14H13V16H11V14ZM15 14H17V16H15V14Z'/%3E%3C/svg%3E");
    }

    .icon-bed {
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='currentColor'%3E%3Cpath d='M22 12V20H20V17H4V20H2V12C2 10.9 2.9 10 4 10H9C9 8.34 10.34 7 12 7S15 8.34 15 7H20C21.1 10 22 10.9 22 12ZM7 13C6.45 13 6 12.55 6 12S6.45 11 7 11 8 11.45 8 12 7.55 13 7 13ZM17 13C16.45 13 16 12.55 16 12S16.45 11 17 11 18 11.45 18 12 17.55 13 17 13Z'/%3E%3C/svg%3E");
    }

    .icon-medicine {
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='currentColor'%3E%3Cpath d='M6 3H18V5H16V7H18C19.1 7 20 7.9 20 9V19C20 20.1 19.1 21 18 21H6C4.9 21 4 20.1 4 19V9C4 7.9 4.9 7 6 7H8V5H6V3M10 5V7H14V5H10M11 10V12H9V14H11V16H13V14H15V12H13V10H11Z'/%3E%3C/svg%3E");
    }

    .icon-shield {
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='currentColor'%3E%3Cpath d='M12 1L3 5V11C3 16.55 6.84 21.74 12 23C17.16 21.74 21 16.55 21 11V5L12 1M10 17L6 13L7.41 11.59L10 14.17L16.59 7.58L18 9L10 17Z'/%3E%3C/svg%3E");
    }

    .icon-bell {
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='currentColor'%3E%3Cpath d='M21 19V20H3V19L5 17V11C5 7.9 7.03 5.17 10 4.29C10 4.19 10 4.1 10 4C10 2.9 10.9 2 12 2S14 2.9 14 4C14 4.1 14 4.19 14 4.29C16.97 5.17 19 7.9 19 11V17L21 19M14 21C14 22.1 13.1 23 12 23S10 22.1 10 21'/%3E%3C/svg%3E");
    }

    /* Topbar */
    .topbar {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        height: var(--topbar-height);
        background: var(--bg-card);
        border-bottom: 1px solid var(--border-primary);
        display: flex;
        align-items: center;
        padding: 0 30px;
        z-index: 1000;
        transition: var(--transition);
        box-shadow: var(--box-shadow);
    }

    .topbar.shifted {
        left: var(--sidebar-width);
    }

    .logo {
        display: flex;
        align-items: center;
        margin-right: 40px;
    }

    .logo-icon {
        width: 50px;
        height: 50px;
        background: var(--accent-blue);
        border-radius: var(--border-radius);
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 15px;
        box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
    }

    .logo-icon .custom-icon {
        color: white;
        font-size: 1.5rem;
    }

    .logo-text h1 {
        font-size: 1.4rem;
        font-weight: 700;
        color: var(--text-primary);
    }

    .logo-text p {
        font-size: 0.8rem;
        color: var(--text-secondary);
        margin-top: -2px;
    }

    .topbar-actions {
        display: flex;
        align-items: center;
        gap: 20px;
        margin-left: auto;
    }

    .action-btn {
        position: relative;
        width: 45px;
        height: 45px;
        border-radius: var(--border-radius);
        background: white;
        border: 1px solid var(--border-primary);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: var(--transition);
        color: var(--text-secondary);
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
    }

    .action-btn:hover {
        background: var(--bg-hover);
        color: var(--accent-blue);
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
        border-color: var(--accent-blue);
    }

    .notification-badge {
        position: absolute;
        top: -5px;
        right: -5px;
        background: var(--accent-red);
        color: white;
        border-radius: 50%;
        width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.7rem;
        font-weight: 600;
    }

    .user-profile {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 8px 16px;
        border-radius: var(--border-radius);
        background: white;
        border: 1px solid var(--border-primary);
        cursor: pointer;
        transition: var(--transition);
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
    }

    .user-profile:hover {
        background: var(--bg-hover);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
        border-color: var(--accent-blue);
    }

    .user-avatar {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        background: var(--accent-purple);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 600;
        font-size: 1.1rem;
    }

    .user-info h4 {
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--text-primary);
    }

    .user-info p {
        font-size: 0.75rem;
        color: var(--text-secondary);
        margin-top: -2px;
    }

    /* Sidebar */
    .sidebar {
        position: fixed;
        top: 0;
        left: -280px;
        width: var(--sidebar-width);
        height: 100vh;
        background: var(--bg-card);
        border-right: 1px solid var(--border-primary);
        transition: var(--transition);
        z-index: 1001;
        overflow-y: auto;
        box-shadow: var(--box-shadow-lg);
    }

    .sidebar.active {
        left: 0;
    }

    .sidebar-header {
        padding: 30px;
        border-bottom: 1px solid var(--border-primary);
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: var(--bg-secondary);
    }

    .sidebar-title {
        font-size: 1.2rem;
        font-weight: 700;
        color: var(--text-primary);
    }

    .close-sidebar {
        background: none;
        border: none;
        font-size: 1.3rem;
        color: var(--text-secondary);
        cursor: pointer;
        padding: 8px;
        border-radius: 8px;
        transition: var(--transition);
    }

    .close-sidebar:hover {
        background: var(--bg-tertiary);
        color: var(--text-primary);
    }

    .sidebar-menu {
        padding: 20px 0;
    }

    .menu-section {
        margin-bottom: 30px;
    }

    .menu-section-title {
        padding: 0 30px 15px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: var(--text-muted);
    }

    .menu-item {
        display: flex;
        align-items: center;
        padding: 15px 30px;
        color: var(--text-secondary);
        text-decoration: none;
        transition: var(--transition);
        position: relative;
        border-left: 4px solid transparent;
    }

    .menu-item:hover {
        background: rgba(59, 130, 246, 0.1);
        color: var(--accent-blue);
        border-left-color: var(--accent-blue);
    }

    .menu-item.active {
        background: rgba(59, 130, 246, 0.15);
        color: var(--accent-blue);
        border-left-color: var(--accent-blue);
        font-weight: 600;
    }

    .menu-item .custom-icon {
        width: 20px;
        margin-right: 15px;
        font-size: 1.1rem;
    }

    .menu-item .arrow {
        margin-left: auto;
        transition: var(--transition);
    }

    .menu-item.expanded .arrow {
        transform: rotate(90deg);
    }

    .submenu {
        max-height: 0;
        overflow: hidden;
        transition: var(--transition);
        background: rgba(0, 0, 0, 0.03);
    }

    .submenu.active {
        max-height: 500px;
    }

    .submenu-item {
        display: block;
        padding: 12px 30px 12px 65px;
        color: var(--text-muted);
        text-decoration: none;
        transition: var(--transition);
        font-size: 0.9rem;
    }

    .submenu-item:hover {
        background: rgba(59, 130, 246, 0.1);
        color: var(--accent-blue);
    }

    /* Main Content */
    .main-content {
        margin-top: var(--topbar-height);
        margin-left: 0;
        padding: 30px;
        transition: var(--transition);
        min-height: calc(100vh - var(--topbar-height));
    }

    .main-content.shifted {
        margin-left: var(--sidebar-width);
    }

    .page-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 30px;
    }

    .page-title {
        font-size: 2rem;
        font-weight: 700;
        color: var(--text-primary);
    }

    .breadcrumb {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.9rem;
        color: var(--text-secondary);
    }

    .breadcrumb a {
        color: var(--accent-blue);
        text-decoration: none;
    }

    .breadcrumb a:hover {
        text-decoration: underline;
    }

    /* Stats Cards */
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
        transition: transform 0.18s cubic-bezier(0.4,0,0.2,1), box-shadow 0.18s cubic-bezier(0.4,0,0.2,1);
    }
    .card:hover, .card:focus-within {
        transform: scale(1.045);
        box-shadow: 0 8px 32px rgba(67,97,238,0.18);
        z-index: 2;
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
    .card.leaves::before { background: linear-gradient(180deg, #f43f5e, #f87171); }
    .card.notifications::before { background: linear-gradient(180deg, #6366f1, #818cf8); }
    .card.patients .card-icon { color: #4361ee; }
    .card.staff .card-icon { color: #4895ef; }
    .card.appointments .card-icon { color: #f8961e; }
    .card.rooms .card-icon { color: #b5179e; }
    .card.leaves .card-icon { color: #f43f5e; }
    .card.notifications .card-icon { color: #6366f1; }

    .card-header {
        background: #fff;
        padding: 28px 32px 18px 32px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-bottom: none;
    }
    .card-title { font-size: 1rem; font-weight: 600; color: #6c757d; margin-bottom: 0.5rem; }
    .card-value { font-size: 2.1rem; font-weight: 700; color: #212529; margin-bottom: 2px; }
    .card-icon-bg { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; }
    .card.patients .card-icon-bg { background: rgba(67, 97, 238, 0.12); }
    .card.staff .card-icon-bg { background: rgba(72, 149, 239, 0.12); }
    .card.appointments .card-icon-bg { background: rgba(248, 150, 30, 0.12); }
    .card.rooms .card-icon-bg { background: rgba(114, 9, 183, 0.12); }
    .card-icon { font-size: 2rem; }

    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    .fade-in { animation: fadeIn 0.5s ease forwards; }
    .delay-1 { animation-delay: 0.1s; }
    .delay-2 { animation-delay: 0.2s; }
    .delay-3 { animation-delay: 0.3s; }

    /* Tables */
    .data-table {
        width: 100%;
        border-collapse: collapse;
    }

    .data-table th {
        text-align: left;
        padding: 15px 20px;
        font-weight: 600;
        color: var(--text-secondary);
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 2px solid var(--border-primary);
        background: var(--bg-card);
    }

    .data-table td {
        padding: 18px 20px;
        border-bottom: 1px solid var(--border-primary);
        font-size: 0.9rem;
        color: var(--text-primary);
    }

    .data-table tr:hover td {
        background: rgba(59, 130, 246, 0.04);
    }

    /* Buttons */
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 12px 24px;
        border: none;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.9rem;
        cursor: pointer;
        transition: var(--transition);
        text-decoration: none;
    }

    .btn-primary {
        background: var(--accent-blue);
        color: white;
        box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
    }

    .btn-primary:hover {
        background: var(--accent-blue-light);
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
    }

    .btn-outline {
        background: transparent;
        color: var(--text-secondary);
        border: 2px solid var(--border-primary);
    }

    .btn-outline:hover {
        background: var(--bg-hover);
        border-color: var(--accent-blue);
        color: var(--accent-blue);
    }

    /* Two Column Layout */
    .two-column {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 30px;
    }

    /* Modal */
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(5px);
        z-index: 2000;
        align-items: center;
        justify-content: center;
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .modal.active {
        display: flex;
        opacity: 1;
    }

    .modal-content {
        background: white;
        border: 1px solid var(--border-primary);
        border-radius: var(--border-radius-lg);
        width: 90%;
        max-width: 500px;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: var(--box-shadow-lg);
        transform: translateY(20px);
        transition: transform 0.3s ease;
    }

    .modal.active .modal-content {
        transform: translateY(0);
    }

    .modal-header {
        padding: 25px 30px;
        border-bottom: 1px solid var(--border-primary);
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: var(--bg-card);
    }

    .modal-title {
        font-size: 1.3rem;
        font-weight: 600;
        color: var(--text-primary);
    }

    .close-modal {
        background: none;
        border: none;
        font-size: 1.5rem;
        color: var(--text-secondary);
        cursor: pointer;
        padding: 5px;
        border-radius: 5px;
        transition: var(--transition);
    }

    .close-modal:hover {
        background: var(--bg-secondary);
        color: var(--text-primary);
    }

    .modal-body {
        padding: 30px;
    }

    .modal-footer {
        padding: 20px 30px;
        border-top: 1px solid var(--border-primary);
        display: flex;
        justify-content: flex-end;
        gap: 15px;
        background: var(--bg-card);
    }

    /* Form Elements */
    .form-group {
        margin-bottom: 25px;
    }

    .form-label {
        display: block;
        margin-bottom: 8px;
        font-weight: 500;
        color: var(--text-primary);
        font-size: 0.9rem;
    }

    .form-control {
        width: 100%;
        padding: 12px 16px;
        border: 2px solid var(--border-primary);
        border-radius: 10px;
        font-size: 0.95rem;
        transition: var(--transition);
        outline: none;
        background: white;
        color: var(--text-primary);
    }

    .form-control::placeholder {
        color: var(--text-muted);
    }

    .form-control:focus {
        border-color: var(--accent-blue);
        box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
    }

    /* Toggle Button */
    .toggle-sidebar {
        background: none;
        border: none;
        font-size: 1.3rem;
        color: var(--text-secondary);
        cursor: pointer;
        padding: 10px;
        border-radius: 8px;
        transition: var(--transition);
        margin-right: 20px;
    }

    .toggle-sidebar:hover {
        background: var(--bg-secondary);
        color: var(--text-primary);
    }

    /* Responsive Design */
    @media (max-width: 1200px) {
        .two-column {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 768px) {
        .topbar {
            padding: 0 20px;
        }

        .logo-text h1 {
            font-size: 1.1rem;
        }

        .logo-text p {
            display: none;
        }

        .search-container {
            max-width: 300px;
        }

        .user-info {
            display: none;
        }

        .main-content {
            padding: 20px;
        }

        .cards-grid {
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .page-title {
            font-size: 1.5rem;
        }
    }

    @media (max-width: 576px) {
        .cards-grid {
            grid-template-columns: 1fr;
        }

        .topbar-actions {
            gap: 10px;
        }

        .search-container {
            display: none;
        }
    }

    /* Custom Scrollbar */
    ::-webkit-scrollbar {
        width: 8px;
    }

    ::-webkit-scrollbar-track {
        background: var(--bg-secondary);
    }

    ::-webkit-scrollbar-thumb {
        background: var(--border-secondary);
        border-radius: 4px;
    }

    ::-webkit-scrollbar-thumb:hover {
        background: var(--border-accent);
    }

    .dashboard-sections-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 30px;
        margin-bottom: 30px;
        grid-template-areas:
            "staff staff"
            "activities notifications"
            "patients patients";
    }
    .dashboard-staff { grid-area: staff; }
    .dashboard-activities { grid-area: activities; }
    .dashboard-notifications { grid-area: notifications; }
    .dashboard-patients { grid-area: patients; }
    @media (max-width: 1000px) {
        .dashboard-sections-grid {
            grid-template-columns: 1fr;
            grid-template-areas:
                "staff"
                "activities"
                "notifications"
                "patients";
        }
    }

    .content-card {
        background: #fff;
        border-radius: 20px;
        box-shadow: 0 4px 24px rgba(67,97,238,0.10);
        padding: 0;
        margin-bottom: 0;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        min-height: 340px;
    }
    .card-header {
        background: #fff;
        padding: 28px 32px 18px 32px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-bottom: none;
    }
    .card-title {
        font-size: 1.45rem;
        font-weight: 700;
        color: #212529;
        margin: 0;
    }
    .card-action {
        color: #3b82f6;
        font-weight: 500;
        font-size: 1rem;
        text-decoration: none;
        transition: color 0.2s;
    }
    .card-action:hover {
        color: #1d4ed8;
        text-decoration: underline;
    }
    .card-body {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 40px 24px 32px 24px;
    }
    .empty-state {
        text-align: center;
        width: 100%;
    }
    .empty-state-icon {
        font-size: 3.2rem !important;
        margin-bottom: 18px !important;
        color: #b0b6be !important;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .empty-state h3 {
        font-size: 1.25rem;
        font-weight: 700;
        color: #374151;
        margin-bottom: 8px;
    }
    .empty-state p {
        color: #6b7280;
        font-size: 1.05rem;
    }
    .card-delta {
        position: absolute;
        right: 22px;
        bottom: 18px;
        font-size: 1.25rem;
        font-weight: 600;
        opacity: 0.92;
        z-index: 2;
    }
    .card-delta.up { color: #10b981; }
    .card-delta.down { color: #ef4444; }
    .card-delta.same { color: #6b7280; }

    /* Search Results Styles */
    .search-results {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: white;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        margin-top: 10px;
        z-index: 1000;
        max-height: 500px;
        overflow-y: auto;
    }

    .search-results-header {
        padding: 15px 20px;
        border-bottom: 1px solid var(--border-primary);
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: var(--bg-card);
        position: sticky;
        top: 0;
    }

    .search-results-header span {
        font-weight: 600;
        color: var(--text-primary);
    }

    .close-search-results {
        background: none;
        border: none;
        color: var(--text-secondary);
        cursor: pointer;
        padding: 5px;
        border-radius: 5px;
        transition: var(--transition);
    }

    .close-search-results:hover {
        background: var(--bg-hover);
        color: var(--text-primary);
    }

    .search-results-body {
        padding: 10px 0;
    }

    .search-result-item {
        padding: 12px 20px;
        display: flex;
        align-items: center;
        gap: 15px;
        cursor: pointer;
        transition: var(--transition);
        border-bottom: 1px solid var(--border-primary);
    }

    .search-result-item:last-child {
        border-bottom: none;
    }

    .search-result-item:hover {
        background: var(--bg-hover);
    }

    .search-result-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        color: white;
    }

    .search-result-icon.patient { background: var(--accent-blue); }
    .search-result-icon.staff { background: var(--accent-purple); }
    .search-result-icon.appointment { background: var(--accent-orange); }

    .search-result-content {
        flex: 1;
    }

    .search-result-title {
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 2px;
    }

    .search-result-subtitle {
        font-size: 0.85rem;
        color: var(--text-secondary);
    }

    .search-result-details {
        font-size: 0.8rem;
        color: var(--text-muted);
        margin-top: 4px;
    }

    .search-result-details span {
        margin-right: 15px;
    }

    .search-result-details strong {
        color: var(--text-secondary);
    }

    .no-results {
        padding: 30px 20px;
        text-align: center;
        color: var(--text-muted);
    }

    .search-loading {
        padding: 20px;
        text-align: center;
        color: var(--text-muted);
    }

    .search-loading i {
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }

    /* Notification Styles */
    .notification-container {
        position: relative;
    }

    .notification-dropdown {
        position: absolute;
        top: 100%;
        right: 0;
        width: 360px;
        background: white;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        margin-top: 10px;
        z-index: 1000;
        max-height: 500px;
        display: flex;
        flex-direction: column;
    }

    .notification-header {
        padding: 15px 20px;
        border-bottom: 1px solid var(--border-primary);
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: var(--bg-card);
    }

    .notification-header h3 {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0;
    }

    .mark-all-read {
        background: none;
        border: none;
        color: var(--accent-blue);
        font-size: 0.85rem;
        cursor: pointer;
        padding: 5px 10px;
        border-radius: 5px;
        transition: var(--transition);
    }

    .mark-all-read:hover {
        background: var(--bg-hover);
    }

    .notification-list {
        flex: 1;
        overflow-y: auto;
        max-height: 400px;
    }

    .notification-item {
        padding: 15px 20px;
        border-bottom: 1px solid var(--border-primary);
        cursor: pointer;
        transition: var(--transition);
        display: flex;
        gap: 12px;
        align-items: flex-start;
    }

    .notification-item:last-child {
        border-bottom: none;
    }

    .notification-item:hover {
        background: var(--bg-hover);
    }

    .notification-item.unread {
        background: rgba(59, 130, 246, 0.05);
    }

    .notification-item.unread:hover {
        background: rgba(59, 130, 246, 0.1);
    }

    .notification-icon {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        color: white;
        flex-shrink: 0;
    }

    .notification-icon.info { background: var(--accent-blue); }
    .notification-icon.warning { background: var(--accent-orange); }
    .notification-icon.success { background: var(--accent-green); }
    .notification-icon.error { background: var(--accent-red); }

    .notification-content {
        flex: 1;
        min-width: 0;
    }

    .notification-title {
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 4px;
        font-size: 0.9rem;
    }

    .notification-message {
        color: var(--text-secondary);
        font-size: 0.85rem;
        margin-bottom: 4px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .notification-time {
        color: var(--text-muted);
        font-size: 0.75rem;
    }

    .notification-footer {
        padding: 12px 20px;
        border-top: 1px solid var(--border-primary);
        text-align: center;
        background: var(--bg-card);
    }

    .view-all {
        color: var(--accent-blue);
        text-decoration: none;
        font-size: 0.9rem;
        font-weight: 500;
        transition: var(--transition);
    }

    .view-all:hover {
        color: var(--accent-blue-light);
        text-decoration: underline;
    }

    .no-notifications {
        padding: 30px 20px;
        text-align: center;
        color: var(--text-muted);
    }

    .notification-loading {
        padding: 20px;
        text-align: center;
        color: var(--text-muted);
    }

    .notification-loading i {
        animation: spin 1s linear infinite;
    }

    .dashboard-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .dashboard-stats {
        grid-area: stats;
    }

    .dashboard-recent {
        grid-area: recent;
    }

    .dashboard-activities {
        grid-area: activities;
    }

    .dashboard-notifications {
        grid-area: notifications;
    }

    .dashboard-charts {
        grid-area: charts;
    }

    .dashboard-summary {
        grid-area: summary;
    }

    .dashboard-grid {
        grid-template-areas: 
            "stats stats"
            "recent activities"
            "charts summary";
    }

    .card-value span {
        word-break: break-all;
        font-size: 2.2rem;
        font-weight: 700;
        color: #222;
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
        display: inline-block;
    }
    .card {
        min-height: 180px;
        max-width: 100%;
        overflow: hidden;
        box-sizing: border-box;
    }
</style>
</head>
<body>
<!-- Topbar -->
<div class="topbar">
    <button class="toggle-sidebar">
        <i class="fas fa-bars"></i>
    </button>
    
    <div class="logo">
        <div class="logo-icon">
            <span class="custom-icon icon-user"></span>
        </div>
        <div class="logo-text">
            <h1>United Medical Asylum & Rehab Facility</h1>
            <p>Rehabilitation Center</p>
        </div>
    </div>

    <div class="topbar-actions">
        <div class="user-profile">
            <div class="user-avatar">AD</div>
            <div class="user-info">
                <h4>Admin User</h4>
                <p>Administrator</p>
            </div>
        </div>
    </div>
</div>

<!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-header">
        <h3 class="sidebar-title">Navigation</h3>
        <button class="close-sidebar">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <div class="sidebar-menu">
        <div class="menu-section">
            <div class="menu-section-title">Main</div>
            <a href="admin_dashboard.php" class="menu-item active">
                <i class="fas fa-chart-pie"></i>
                <span>Dashboard</span>
            </a>
        </div>

        <div class="menu-section">
            <div class="menu-section-title">Patient Management</div>
            <a href="patients.php" class="menu-item">
                <span class="custom-icon icon-user"></span>
                <span>Patients</span>
                <i class="fas fa-chevron-right arrow"></i>
            </a>
            <div class="submenu">
                <a href="patient_management.php#add-patient" class="submenu-item">Add New Patient</a>
                <a href="patient_management.php#patients" class="submenu-item">Patient Records</a>
            </div>
        </div>

        <div class="menu-section">
            <div class="menu-section-title">Staff Management</div>
            <a href="manage_staff.php" class="menu-item">
                <span class="custom-icon icon-users"></span>
                <span>Medical Staff</span>
                <i class="fas fa-chevron-right arrow"></i>
            </a>
            <div class="submenu">
                <a href="add_chief_staff.php" class="submenu-item">Chief Staff</a>
                <a href="add_doctor.php" class="submenu-item">Doctors</a>
                <a href="add_nurse.php" class="submenu-item">Nurses</a>
                <a href="add_therapist.php" class="submenu-item">Therapists</a>
            </div>
            
            <a href="#" class="menu-item">
                <i class="fas fa-users"></i>
                <span>Support Staff</span>
                <i class="fas fa-chevron-right arrow"></i>
            </a>
            <div class="submenu">
                <a href="receptionist.php" class="submenu-item">Receptionist</a>
            </div>
        </div>

        <div class="menu-section">
            <div class="menu-section-title">Operations</div>
            <a href="appointments_admin.php" class="menu-item">
                <span class="custom-icon icon-calendar"></span>
                <span>Appointments</span>
            </a>
            <a href="room_management.php" class="menu-item">
                <i class="fas fa-bed"></i>
                <span>Room Management</span>
            </a>
            <a href="medicine_stock.php" class="menu-item">
                <span class="custom-icon icon-medicine"></span>
                <span>Medicine Stock</span>
            </a>
        </div>

        <div class="menu-section">
            <div class="menu-section-title">System</div>
            <a href="logout.php" class="menu-item">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>
</div>

<!-- Main Content -->
<div class="main-content">
    <div class="page-header">
        <h1 class="page-title">Dashboard Overview</h1>
        <div class="breadcrumb">
            <a href="#">Home</a>
            <i class="fas fa-chevron-right"></i>
            <span>Dashboard</span>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="cards-grid">
        <div class="card patients fade-in">
            <div class="card-header">
                <div>
                    <div class="card-title">Patients</div>
                    <div class="card-value" style="display:flex;align-items:center;gap:10px;">
                        <span><?php echo $stats['total_patients']; ?></span>
                    </div>
                </div>
                <div class="card-icon-bg">
                    <i class="fas fa-user-injured card-icon"></i>
                </div>
            </div>
            <?php echo stat_arrow($stats['total_patients'], $yesterday_patients); ?>
        </div>
        <div class="card staff fade-in delay-1">
            <div class="card-header">
                <div>
                    <div class="card-title">Staff</div>
                    <div class="card-value" style="display:flex;align-items:center;gap:10px;">
                        <span><?php echo $stats['total_staff']; ?></span>
                    </div>
                </div>
                <div class="card-icon-bg">
                    <i class="fas fa-users card-icon"></i>
                </div>
            </div>
            <?php echo stat_arrow($stats['total_staff'], $yesterday_staff); ?>
        </div>
        <div class="card appointments fade-in delay-2">
            <div class="card-header">
                <div>
                    <div class="card-title">Appointments</div>
                    <div class="card-value" style="display:flex;align-items:center;gap:10px;">
                        <span><?php echo $stats['total_appointments'] ?? 0; ?></span>
                    </div>
                </div>
                <div class="card-icon-bg">
                    <i class="fas fa-calendar-check card-icon"></i>
                </div>
            </div>
            <?php echo stat_arrow($stats['total_appointments'] ?? 0, $yesterday_appointments ?? 0); ?>
        </div>
        <div class="card rooms fade-in delay-3">
            <div class="card-header">
                <div>
                    <div class="card-title">Available Rooms</div>
                    <div class="card-value" style="display:flex;align-items:center;gap:10px;">
                        <span><?php echo $stats['available_rooms'] ?? 0; ?></span>
                    </div>
                </div>
                <div class="card-icon-bg">
                    <i class="fas fa-bed card-icon"></i>
                </div>
            </div>
            <?php echo stat_arrow($stats['available_rooms'] ?? 0, $yesterday_rooms ?? 0); ?>
        </div>
    </div>

    <!-- Dashboard Sections Grid -->
    <div class="dashboard-sections-grid">
        <!-- Staff Overview -->
        <div class="content-card dashboard-staff">
            <div class="card-header">
                <h3 class="card-title">Staff Overview</h3>
                <a href="manage_staff.php" class="card-action">View All Staff</a>
            </div>
            <div class="card-body">
                <?php if (empty($staff_summary)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <span class="custom-icon icon-users"></span>
                    </div>
                    <h3>No Staff Data</h3>
                    <p>Staff information will appear here once data is added to the system.</p>
                </div>
                <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Staff ID</th>
                            <th>Name</th>
                            <th>Role</th>
                            <th>Phone</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($staff_summary as $member): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($member['staff_id']); ?></td>
                            <td><?php echo htmlspecialchars($member['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($member['role']); ?></td>
                            <td><?php echo htmlspecialchars($member['phone']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
        <!-- Recent Patients -->
        <div class="content-card dashboard-patients">
            <div class="card-header">
                <h3 class="card-title">Recent Patients</h3>
                <a href="patient_management.php#add-patient" class="btn btn-primary">
                    <i class="fas fa-plus"></i>
                    Add Patient
                </a>
            </div>
            <div class="card-body">
                <?php if (empty($patients)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <span class="custom-icon icon-user"></span>
                    </div>
                    <h3>No Patients Registered</h3>
                    <p>Patient records will be displayed here once they are added to the system. Click "Add Patient" to get started.</p>
                </div>
                <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Patient ID</th>
                            <th>Name</th>
                            <th>Admission Date</th>
                            <th>Status</th>
                            <th>Doctor</th>
                            <th>Therapist</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($patients as $patient): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($patient['patient_id'] ?? $patient['id'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($patient['full_name'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($patient['admission_date'] ?? ''); ?></td>
                            <td>
                                <span class="status-badge <?php echo (isset($patient['status']) && strtolower($patient['status']) === 'admitted') ? 'success' : ((isset($patient['status']) && strtolower($patient['status']) === 'treatment') ? 'warning' : 'info'); ?>">
                                    <?php echo htmlspecialchars($patient['status'] ?? ''); ?>
                                </span>
                            </td>
                            <td>
                                <?php
                                $doctor = 'N/A';
                                $pid = $patient['patient_id'] ?? $patient['id'] ?? '';
                                if ($pid) {
                                    // Try to get assigned doctor from latest assessment
                                    $assess_sql = "SELECT pa.assigned_doctor, s.full_name AS doctor_name FROM patient_assessments pa LEFT JOIN staff s ON pa.assigned_doctor = s.staff_id WHERE pa.patient_id = '" . $conn->real_escape_string($pid) . "' AND pa.assigned_doctor IS NOT NULL ORDER BY pa.assessment_date DESC, pa.created_at DESC LIMIT 1";
                                    $assess_result = $conn->query($assess_sql);
                                    if ($assess_result && $assess_result->num_rows > 0) {
                                        $row = $assess_result->fetch_assoc();
                                        if (!empty($row['doctor_name'])) {
                                            $doctor = $row['doctor_name'];
                                        }
                                    } else {
                                        // Fallback: get latest doctor from appointments
                                        $apt_result = $conn->query("SELECT doctor FROM appointments WHERE patient_id = '" . $conn->real_escape_string($pid) . "' AND doctor IS NOT NULL AND doctor != '' ORDER BY date DESC, time DESC LIMIT 1");
                                        if ($apt_result && $apt_result->num_rows > 0) {
                                            $apt = $apt_result->fetch_assoc();
                                            $doctor = $apt['doctor'];
                                        }
                                    }
                                }
                                echo htmlspecialchars($doctor);
                                ?>
                            </td>
                            <td>
                                <?php
                                $therapist = 'N/A';
                                if ($pid) {
                                    // Try to get assigned therapist from latest assessment
                                    $assess_sql = "SELECT pa.assigned_therapist, s.full_name AS therapist_name FROM patient_assessments pa LEFT JOIN staff s ON pa.assigned_therapist = s.staff_id WHERE pa.patient_id = '" . $conn->real_escape_string($pid) . "' AND pa.assigned_therapist IS NOT NULL ORDER BY pa.assessment_date DESC, pa.created_at DESC LIMIT 1";
                                    $assess_result = $conn->query($assess_sql);
                                    if ($assess_result && $assess_result->num_rows > 0) {
                                        $row = $assess_result->fetch_assoc();
                                        if (!empty($row['therapist_name'])) {
                                            $therapist = $row['therapist_name'];
                                        }
                                    }
                                }
                                echo htmlspecialchars($therapist);
                                ?>
                            </td>
                            <td>
                                <form method="POST" action="delete_patient.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this patient?');">
                                    <input type="hidden" name="patient_id" value="<?php echo htmlspecialchars($patient['patient_id'] ?? $patient['id'] ?? ''); ?>">
                                    <button type="submit" class="btn btn-outline" style="padding: 6px 12px;" title="Delete Patient">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
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
                    <label class="form-label" for="name">Full Name</label>
                    <input type="text" class="form-control" id="name" name="name" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="dob">Date of Birth</label>
                    <input type="date" class="form-control" id="dob" name="dob" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="gender">Gender</label>
                    <select class="form-control" id="gender" name="gender" required>
                        <option value="">Select Gender</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="doctor">Assigned Doctor</label>
                    <select class="form-control" id="doctor" name="doctor" required>
                        <option value="">Select Doctor</option>
                        <option value="Dr. Sarah Johnson">Dr. Sarah Johnson</option>
                        <option value="Dr. Emily Davis">Dr. Emily Davis</option>
                        <option value="Dr. Michael Brown">Dr. Michael Brown</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="guardian">Guardian Name</label>
                    <input type="text" class="form-control" id="guardian" name="guardian">
                </div>
                <div class="form-group">
                    <label class="form-label" for="contact">Guardian Contact</label>
                    <input type="tel" class="form-control" id="contact" name="contact">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline close-modal">Cancel</button>
                <button type="submit" name="generate_id" class="btn btn-primary">Add Patient</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Sidebar Toggle
        const toggleSidebar = document.querySelector('.toggle-sidebar');
        const closeSidebar = document.querySelector('.close-sidebar');
        const sidebar = document.querySelector('.sidebar');
        const mainContent = document.querySelector('.main-content');
        const topbar = document.querySelector('.topbar');

        toggleSidebar.addEventListener('click', () => {
            sidebar.classList.add('active');
            mainContent.classList.add('shifted');
            topbar.classList.add('shifted');
        });

        closeSidebar.addEventListener('click', () => {
            sidebar.classList.remove('active');
            mainContent.classList.remove('shifted');
            topbar.classList.remove('shifted');
        });

        // Menu Items Toggle
        const menuItems = document.querySelectorAll('.menu-item');
        menuItems.forEach(item => {
            if (item.querySelector('.arrow')) {
                item.addEventListener('click', (e) => {
                    e.preventDefault();
                    
                    // Close other open submenus
                    menuItems.forEach(otherItem => {
                        if (otherItem !== item && otherItem.querySelector('.arrow')) {
                            otherItem.classList.remove('expanded');
                            const otherSubmenu = otherItem.nextElementSibling;
                            if (otherSubmenu && otherSubmenu.classList.contains('submenu')) {
                                otherSubmenu.classList.remove('active');
                            }
                        }
                    });
                    
                    // Toggle current item
                    item.classList.toggle('expanded');
                    const submenu = item.nextElementSibling;
                    if (submenu && submenu.classList.contains('submenu')) {
                        submenu.classList.toggle('active');
                    }
                });
            }
        });

        // Modal Functionality
        const addPatientBtn = document.getElementById('addPatientBtn');
        const addPatientModal = document.getElementById('addPatientModal');
        const closeModalBtns = document.querySelectorAll('.close-modal');

        addPatientBtn.addEventListener('click', () => {
            addPatientModal.classList.add('active');
        });

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

        // Search functionality
        const searchInput = document.querySelector('.search-input');
        const searchResults = document.querySelector('.search-results');
        const searchResultsBody = document.querySelector('.search-results-body');
        const closeSearchResults = document.querySelector('.close-search-results');
        let searchTimeout;

        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();
            
            if (query.length < 2) {
                searchResults.style.display = 'none';
                return;
            }
            
            searchResultsBody.innerHTML = '<div class="search-loading"><i class="fas fa-spinner"></i> Searching...</div>';
            searchResults.style.display = 'block';
            
            searchTimeout = setTimeout(() => {
                fetch(`search_handler.php?query=${encodeURIComponent(query)}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.error) {
                            searchResultsBody.innerHTML = `<div class="no-results">${data.error}</div>`;
                            return;
                        }
                        
                        if (data.results.length === 0) {
                            searchResultsBody.innerHTML = '<div class="no-results">No results found</div>';
                            return;
                        }
                        
                        searchResultsBody.innerHTML = data.results.map(result => `
                            <div class="search-result-item" data-type="${result.type}" data-id="${result.id}">
                                <div class="search-result-icon ${result.type}">
                                    <i class="fas ${getIconForType(result.type)}"></i>
                                </div>
                                <div class="search-result-content">
                                    <div class="search-result-title">${result.title}</div>
                                    <div class="search-result-subtitle">${result.subtitle}</div>
                                    <div class="search-result-details">
                                        ${Object.entries(result.details).map(([key, value]) => 
                                            `<span><strong>${key}:</strong> ${value}</span>`
                                        ).join('')}
                                    </div>
                                </div>
                            </div>
                        `).join('');
                        
                        // Add click handlers to search results
                        document.querySelectorAll('.search-result-item').forEach(item => {
                            item.addEventListener('click', () => {
                                const type = item.dataset.type;
                                const id = item.dataset.id;
                                handleSearchResultClick(type, id);
                            });
                        });
                    })
                    .catch(error => {
                        console.error('Search error:', error);
                        searchResultsBody.innerHTML = '<div class="no-results">An error occurred while searching</div>';
                    });
            }, 300);
        });

        function getIconForType(type) {
            switch (type) {
                case 'patient': return 'fa-user';
                case 'staff': return 'fa-user-md';
                case 'appointment': return 'fa-calendar-check';
                default: return 'fa-file';
            }
        }

        function handleSearchResultClick(type, id) {
            switch (type) {
                case 'patient':
                    window.location.href = `patient_details.php?id=${id}`;
                    break;
                case 'staff':
                    window.location.href = `staff_details.php?id=${id}`;
                    break;
                case 'appointment':
                    window.location.href = `appointment_details.php?id=${id}`;
                    break;
            }
        }

        // Close search results when clicking outside
        document.addEventListener('click', (e) => {
            if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
                searchResults.style.display = 'none';
            }
        });

        closeSearchResults.addEventListener('click', () => {
            searchResults.style.display = 'none';
        });

        // Responsive handling
        function handleResize() {
            if (window.innerWidth > 1024) {
                sidebar.classList.remove('active');
                mainContent.classList.remove('shifted');
                topbar.classList.remove('shifted');
            }
        }

        window.addEventListener('resize', handleResize);

        // Notification functionality
        const notificationBtn = document.getElementById('notificationBtn');
        const notificationDropdown = document.querySelector('.notification-dropdown');
        const notificationList = document.querySelector('.notification-list');
        const markAllReadBtn = document.querySelector('.mark-all-read');
        let notificationCheckInterval;

        function loadNotifications() {
            notificationList.innerHTML = '<div class="notification-loading"><i class="fas fa-spinner"></i> Loading notifications...</div>';
            
            fetch('notification_handler.php?action=get&unread_only=true')
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        notificationList.innerHTML = `<div class="no-notifications">${data.error}</div>`;
                        return;
                    }
                    
                    if (data.notifications.length === 0) {
                        notificationList.innerHTML = '<div class="no-notifications">No new notifications</div>';
                        return;
                    }
                    
                    notificationList.innerHTML = data.notifications.map(notification => `
                        <div class="notification-item ${notification.is_read ? '' : 'unread'}" 
                             data-id="${notification.id}" 
                             ${notification.link ? `onclick="window.location.href='${notification.link}'"` : ''}>
                            <div class="notification-icon ${notification.type}">
                                <i class="fas ${getNotificationIcon(notification.type)}"></i>
                            </div>
                            <div class="notification-content">
                                <div class="notification-title">${notification.title}</div>
                                <div class="notification-message">${notification.message}</div>
                                <div class="notification-time">${formatTime(notification.created_at)}</div>
                            </div>
                        </div>
                    `).join('');
                    
                    // Update notification badge
                    const badge = notificationBtn.querySelector('.notification-badge');
                    if (badge) {
                        badge.textContent = data.notifications.length;
                        badge.style.display = data.notifications.length > 0 ? 'flex' : 'none';
                    }
                })
                .catch(error => {
                    console.error('Error loading notifications:', error);
                    notificationList.innerHTML = '<div class="no-notifications">Error loading notifications</div>';
                });
        }

        function getNotificationIcon(type) {
            switch (type) {
                case 'info': return 'fa-info-circle';
                case 'warning': return 'fa-exclamation-triangle';
                case 'success': return 'fa-check-circle';
                case 'error': return 'fa-times-circle';
                default: return 'fa-bell';
            }
        }

        function formatTime(timestamp) {
            const date = new Date(timestamp);
            const now = new Date();
            const diff = now - date;
            
            // Less than 1 minute
            if (diff < 60000) {
                return 'Just now';
            }
            // Less than 1 hour
            if (diff < 3600000) {
                const minutes = Math.floor(diff / 60000);
                return `${minutes} minute${minutes > 1 ? 's' : ''} ago`;
            }
            // Less than 24 hours
            if (diff < 86400000) {
                const hours = Math.floor(diff / 3600000);
                return `${hours} hour${hours > 1 ? 's' : ''} ago`;
            }
            // Less than 7 days
            if (diff < 604800000) {
                const days = Math.floor(diff / 86400000);
                return `${days} day${days > 1 ? 's' : ''} ago`;
            }
            // Otherwise show the date
            return date.toLocaleDateString();
        }

        function markAllAsRead() {
            const unreadItems = notificationList.querySelectorAll('.notification-item.unread');
            if (unreadItems.length === 0) return;
            
            const notificationIds = Array.from(unreadItems).map(item => item.dataset.id);
            
            fetch('notification_handler.php?action=mark_read', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `notification_ids=${JSON.stringify(notificationIds)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    unreadItems.forEach(item => item.classList.remove('unread'));
                    const badge = notificationBtn.querySelector('.notification-badge');
                    if (badge) {
                        badge.style.display = 'none';
                    }
                }
            })
            .catch(error => console.error('Error marking notifications as read:', error));
        }

        // Toggle notification dropdown
        notificationBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            notificationDropdown.style.display = notificationDropdown.style.display === 'none' ? 'flex' : 'none';
            if (notificationDropdown.style.display === 'flex') {
                loadNotifications();
                // Start checking for new notifications every 30 seconds
                notificationCheckInterval = setInterval(loadNotifications, 30000);
            } else {
                clearInterval(notificationCheckInterval);
            }
        });

        // Close notification dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!notificationBtn.contains(e.target) && !notificationDropdown.contains(e.target)) {
                notificationDropdown.style.display = 'none';
                clearInterval(notificationCheckInterval);
            }
        });

        // Mark all as read
        markAllReadBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            markAllAsRead();
        });

        // Initial load of notifications
        loadNotifications();

        // Add smooth scrolling for internal links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth'
                    });
                }
            });
        });
    });
</script>
</body>
</html>

