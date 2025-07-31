<?php
require_once 'session_check.php';
check_login(['admin', 'chief-staff']);
require_once 'db.php';

// Add license_number column to staff table if it doesn't exist
$alter_sql = "ALTER TABLE staff ADD COLUMN IF NOT EXISTS license_number VARCHAR(50)";
$conn->query($alter_sql);

// Fetch all staff members, excluding admin, and join with users to get email and user_id
try {
    $query = "SELECT s.staff_id, s.full_name, s.role, u.email, u.id AS user_id, s.phone, s.dob, s.gender, s.address, s.experience, s.shift, s.license_number, s.created_at, s.status FROM staff s LEFT JOIN users u ON s.staff_id = u.username WHERE s.role != 'admin' ORDER BY s.created_at DESC";
    $result = $conn->query($query);
    if (!$result) {
        throw new Exception("Query failed: " . $conn->error);
    }
    $staff_members = $result->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    // Initialize with empty array if query fails
    $staff_members = [];
    // Display error but continue
    echo "<div class='alert alert-danger'>Error loading staff data: " . $e->getMessage() . "</div>";
}

// Get staff statistics
try {
    $stats_sql = "SELECT 
        COUNT(*) as total_staff,
        SUM(CASE WHEN s.status = 'active' THEN 1 ELSE 0 END) as active_staff,
        SUM(CASE WHEN s.status = 'on_leave' THEN 1 ELSE 0 END) as on_leave_staff,
        SUM(CASE WHEN s.status = 'inactive' THEN 1 ELSE 0 END) as inactive_staff,
        COUNT(DISTINCT s.role) as total_roles
        FROM staff s 
        LEFT JOIN users u ON s.staff_id = u.username 
        WHERE s.role != 'admin'";
    $stats = $conn->query($stats_sql)->fetch_assoc();
} catch (Exception $e) {
    // Default stats if query fails
    $stats = [
        'total_staff' => count($staff_members),
        'active_staff' => 0,
        'on_leave_staff' => 0,
        'inactive_staff' => 0,
        'total_roles' => 0
    ];
}

// Get role distribution
try {
    $roles_sql = "SELECT s.role, COUNT(*) as count 
                FROM staff s 
                LEFT JOIN users u ON s.staff_id = u.username 
                WHERE s.role != 'admin'
                GROUP BY s.role";
    $roles = $conn->query($roles_sql)->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    // Default empty roles if query fails
    $roles = [];
}

// Get yesterday's counts for comparison
try {
    $yesterday_sql = "SELECT 
        COUNT(*) as total_staff,
        SUM(CASE WHEN s.status = 'active' THEN 1 ELSE 0 END) as active_staff,
        SUM(CASE WHEN s.status = 'on_leave' THEN 1 ELSE 0 END) as on_leave_staff,
        SUM(CASE WHEN s.status = 'inactive' THEN 1 ELSE 0 END) as inactive_staff
        FROM staff s 
        LEFT JOIN users u ON s.staff_id = u.username 
        WHERE s.role != 'admin' 
        AND DATE(s.created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
    $yesterday_stats = $conn->query($yesterday_sql)->fetch_assoc();
} catch (Exception $e) {
    // Default yesterday stats if query fails
    $yesterday_stats = [
        'total_staff' => 0,
        'active_staff' => 0,
        'on_leave_staff' => 0,
        'inactive_staff' => 0
    ];
}

// Calculate percentage changes
function calculateChange($current, $previous) {
    if ($previous == 0) return 100;
    return round((($current - $previous) / $previous) * 100);
}

$total_change = calculateChange($stats['total_staff'], $yesterday_stats['total_staff'] ?? 0);
$active_change = calculateChange($stats['active_staff'], $yesterday_stats['active_staff'] ?? 0);
$on_leave_change = calculateChange($stats['on_leave_staff'], $yesterday_stats['on_leave_staff'] ?? 0);

// Build accurate performance metrics for each staff member
$performance_metrics = [];
foreach ($staff_members as $staff) {
    $role = $staff['role'];
    $user_id = $staff['user_id'] ?? null;
    $patients_count = '-';
    $appointments_count = '-';
    $cancelled_count = 0;
    if (in_array($role, ['doctor', 'therapist']) && $user_id) {
        // Count assigned patients (user_id is numeric)
        $stmt = $conn->prepare("SELECT COUNT(*) FROM staff_patient_assignments WHERE staff_id = ? AND status = 'active'");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->bind_result($patients_count);
        $stmt->fetch();
        $stmt->close();
        // Count appointments and cancelled appointments (staff_id is string)
        $appt_col = $role === 'doctor' ? 'doctor' : 'therapist';
        $stmt = $conn->prepare("SELECT COUNT(*), SUM(status = 'cancelled') FROM appointments WHERE $appt_col = ?");
        $stmt->bind_param("s", $staff['staff_id']);
        $stmt->execute();
        $stmt->bind_result($appointments_count, $cancelled_count);
        $stmt->fetch();
        $stmt->close();
        if ($appointments_count === null) $appointments_count = 0;
        if ($cancelled_count === null) $cancelled_count = 0;
    } elseif ($role === 'nurse' && $staff['staff_id']) {
        // Count unique patients where this nurse is assigned in the latest assessment
        $nurse_id = $staff['staff_id'];
        $sql = "SELECT COUNT(DISTINCT pa.patient_id) AS patient_count
                FROM patient_assessments pa
                INNER JOIN (
                    SELECT patient_id, MAX(assessment_date) AS max_date
                    FROM patient_assessments
                    GROUP BY patient_id
                ) latest ON pa.patient_id = latest.patient_id AND pa.assessment_date = latest.max_date
                WHERE (pa.morning_staff = ? OR pa.evening_staff = ? OR pa.night_staff = ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $nurse_id, $nurse_id, $nurse_id);
        $stmt->execute();
        $stmt->bind_result($patients_count);
        $stmt->fetch();
        $stmt->close();
        $appointments_count = '-';
        $cancelled_count = '-';
    } else {
        $patients_count = '-';
        $appointments_count = '-';
        $cancelled_count = '-';
    }
    $performance_metrics[] = [
        'staff_id' => $staff['staff_id'],
        'full_name' => $staff['full_name'],
        'role' => $role,
        'total_patients' => $patients_count,
        'total_appointments' => $appointments_count,
        'cancelled_appointments' => $cancelled_count
    ];
}

// Update performance metrics to show '-' for Chief Staff and Receptionist
foreach ($performance_metrics as &$metric) {
    if (in_array($metric['role'], ['chief-staff', 'receptionist'])) {
        $metric['total_patients'] = '-';
        $metric['total_appointments'] = '-';
    }
}
unset($metric);

// Get staff attendance data
try {
    $attendance_query = "SELECT 
        s.staff_id,
        s.full_name,
        s.role,
        COUNT(DISTINCT CASE WHEN a.status = 'present' THEN a.date END) as days_present,
        COUNT(DISTINCT CASE WHEN a.status = 'absent' THEN a.date END) as days_absent,
        COUNT(DISTINCT CASE WHEN a.status = 'late' THEN a.date END) as days_late
        FROM staff s 
        LEFT JOIN attendance a ON s.staff_id = a.staff_id 
        WHERE s.role != 'admin'
        AND a.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY s.staff_id, s.full_name, s.role";
    $attendance_data = $conn->query($attendance_query)->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    // Create default empty attendance data if table doesn't exist
    $attendance_data = [];
    foreach ($staff_members as $staff) {
        $attendance_data[] = [
            'staff_id' => $staff['staff_id'],
            'full_name' => $staff['full_name'],
            'role' => $staff['role'],
            'days_present' => 0,
            'days_absent' => 0,
            'days_late' => 0
        ];
    }
}

// Handle delete staff
if (isset($_POST['delete_staff_id'])) {
    $delete_id = $_POST['delete_staff_id'];
    $stmt = $conn->prepare("DELETE FROM staff WHERE staff_id = ?");
    $stmt->bind_param("s", $delete_id);
    $stmt->execute();
    $stmt->close();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

// Handle search functionality
if (isset($_GET['search'])) {
    $search = trim($_GET['search']);
    $type = $_GET['type'] ?? 'all';
    $status = $_GET['status'] ?? '';
    $shift = $_GET['shift'] ?? '';
    $where_conditions = [];
    $params = [];
    $types = '';

    if ($search) {
        $where_conditions[] = "(s.full_name LIKE ? OR s.staff_id LIKE ? OR s.email LIKE ?)";
        $search_param = "%$search%";
        $params = array_merge($params, [$search_param, $search_param, $search_param]);
        $types .= 'sss';
    }

    if ($type !== '' && $type !== 'all') {
        $where_conditions[] = "s.role = ?";
        $params[] = $type;
        $types .= 's';
    }

    if ($status !== '') {
        $where_conditions[] = "s.status = ?";
        $params[] = $status;
        $types .= 's';
    }

    if ($shift !== '') {
        $where_conditions[] = "s.shift = ?";
        $params[] = $shift;
        $types .= 's';
    }

    $base_condition = "s.role != 'admin'";
    if (!empty($where_conditions)) {
        $where_clause = "WHERE (" . implode(" AND ", $where_conditions) . ") AND " . $base_condition;
    } else {
        $where_clause = "WHERE " . $base_condition;
    }

    $query = "SELECT s.*, u.email 
              FROM staff s 
              LEFT JOIN users u ON s.staff_id = u.username 
              $where_clause 
              ORDER BY s.role, s.created_at DESC";
    
    if (!empty($params)) {
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($query);
    }
    
    if (!$result) {
        die("Query failed: " . $conn->error);
    }
    $staff_members = $result->fetch_all(MYSQLI_ASSOC);
}

// Get notifications for the current user
try {
    $notifications_query = "SELECT * FROM notifications 
                          WHERE user_id = ? 
                          AND (expires_at IS NULL OR expires_at > NOW())
                          ORDER BY created_at DESC 
                          LIMIT 5";
    $stmt = $conn->prepare($notifications_query);
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $unread_count = array_reduce($notifications, function($carry, $notification) {
        return $carry + ($notification['is_read'] ? 0 : 1);
    }, 0);
} catch (Exception $e) {
    // Default empty notifications if query fails
    $notifications = [];
    $unread_count = 0;
}

// Handle marking notifications as read
if (isset($_POST['mark_read'])) {
    try {
        $notification_ids = $_POST['notification_ids'];
        if (!empty($notification_ids)) {
            $placeholders = str_repeat('?,', count($notification_ids) - 1) . '?';
            $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id IN ($placeholders) AND user_id = ?");
            $types = str_repeat('i', count($notification_ids)) . 'i';
            $params = array_merge($notification_ids, [$_SESSION['user_id']]);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
        }
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    } catch (Exception $e) {
        // Just redirect back if there's an error
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Staff - Asylum & Rehab Center</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --accent-color: #f72585;
            --success-color: #4cc9f0;
            --warning-color: #f8961e;
            --danger-color: #ef476f;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --gray-100: #f8f9fa;
            --gray-200: #e9ecef;
            --gray-300: #dee2e6;
            --gray-400: #ced4da;
            --gray-500: #adb5bd;
            --gray-600: #6c757d;
            --gray-700: #495057;
            --gray-800: #343a40;
            --gray-900: #212529;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        body {
            background-color: var(--gray-100);
            color: var(--gray-900);
            line-height: 1.6;
        }

        .navbar {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            padding: 1rem 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .navbar-brand {
            color: white;
            font-weight: 600;
            font-size: 1.5rem;
            text-decoration: none;
        }

        .container-fluid {
            padding: 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding: 1rem;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .page-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--gray-800);
            margin: 0;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
        }

        .btn-dashboard {
            background: var(--primary-color);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            border: none;
        }

        .btn-dashboard:hover {
            background: var(--secondary-color);
            color: white;
            transform: translateY(-2px);
        }

        .btn-add-staff {
            background: var(--primary-color);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .btn-add-staff:hover {
            background: var(--secondary-color);
            color: white;
            transform: translateY(-2px);
        }

        .filters-section {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .search-box {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .search-input {
            flex: 1;
            min-width: 200px;
            padding: 0.75rem;
            border: 1px solid var(--gray-300);
            border-radius: 5px;
            font-size: 0.9rem;
        }

        .filter-select {
            padding: 0.75rem;
            border: 1px solid var(--gray-300);
            border-radius: 5px;
            min-width: 150px;
            font-size: 0.9rem;
        }

        .staff-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }

        .staff-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .staff-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .staff-header {
            padding: 1.5rem;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            position: relative;
        }

        .staff-status {
            position: absolute;
            top: 1rem;
            right: 1rem;
            padding: 0.3rem 1rem;
            border-radius: 999px;
            font-size: 0.85rem;
            font-weight: 600;
            box-shadow: 0 1px 4px rgba(0,0,0,0.08);
            letter-spacing: 0.5px;
        }

        .status-active {
            background: #2ecc71;
            color: white;
        }

        .status-inactive {
            background: #adb5bd;
            color: white;
        }

        .status-on-leave {
            background: #f9c74f;
            color: #333;
        }

        .staff-name {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .staff-role {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .staff-body {
            padding: 1.5rem;
        }

        .staff-info {
            display: grid;
            gap: 1rem;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: var(--gray-700);
            font-size: 0.9rem;
        }

        .info-item i {
            color: var(--primary-color);
            width: 20px;
        }

        .staff-actions {
            padding: 1rem 1.5rem 1.25rem 1.5rem;
            background: var(--gray-100);
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
        }

        .action-btn {
            padding: 0.5rem 0.7rem;
            border: none;
            border-radius: 7px;
            cursor: pointer;
            transition: all 0.2s;
            color: white;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .view-btn {
            background: var(--primary-color);
        }

        .edit-btn {
            background: var(--success-color);
        }

        .delete-btn {
            background: var(--danger-color);
        }

        .action-btn:hover {
            opacity: 0.85;
            transform: translateY(-2px) scale(1.05);
        }

        .modal-content {
            border-radius: 10px;
            border: none;
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border-radius: 10px 10px 0 0;
            padding: 1.5rem;
        }

        .modal-body {
            padding: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            font-weight: 500;
            color: var(--gray-700);
            margin-bottom: 0.5rem;
        }

        .form-control {
            padding: 0.75rem;
            border: 1px solid var(--gray-300);
            border-radius: 5px;
            font-size: 0.9rem;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(67, 97, 238, 0.25);
        }

        @media (max-width: 768px) {
            .staff-grid {
                grid-template-columns: 1fr;
            }

            .search-box {
                flex-direction: column;
            }

            .search-input, .filter-select {
                width: 100%;
            }
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--box-shadow);
        }

        .stat-card-title {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-bottom: 10px;
        }

        .stat-card-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 5px;
        }

        .stat-card-change {
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .stat-card-change.positive {
            color: #2ecc71;
        }

        .stat-card-change.negative {
            color: #e74c3c;
        }

        .chart-container {
            background: white;
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: var(--box-shadow);
        }

        .chart-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 20px;
        }

        .role-distribution {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
        }

        .role-item {
            background: var(--gray-100);
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }

        .role-name {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-bottom: 5px;
        }

        .role-count {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .notification-container,
        .notification-btn,
        .notification-dropdown,
        .notification-header,
        .notification-list,
        .notification-item,
        .notification-icon,
        .notification-content,
        .notification-title,
        .notification-message,
        .notification-time,
        .notification-link,
        .notification-footer,
        .no-notifications,
        .notification-badge,
        .mark-read-btn,
        .close-notifications {
            display: none !important;
        }

        .activity-log-container,
        .reports-container {
            background: white;
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: var(--box-shadow);
        }

        .section-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 20px;
        }

        .activity-filters {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .activity-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .activity-item {
            display: flex;
            gap: 15px;
            padding: 15px;
            background: var(--gray-100);
            border-radius: 8px;
            transition: transform 0.2s;
        }

        .activity-item:hover {
            transform: translateX(5px);
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .activity-icon.success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--accent-green);
        }

        .activity-icon.info {
            background: rgba(67, 97, 238, 0.1);
            color: var(--primary-color);
        }

        .activity-icon.warning {
            background: rgba(249, 115, 22, 0.1);
            color: var(--accent-orange);
        }

        .activity-icon.danger {
            background: rgba(239, 68, 68, 0.1);
            color: var(--accent-red);
        }

        .activity-content {
            flex: 1;
        }

        .activity-title {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 5px;
        }

        .activity-details {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-bottom: 5px;
        }

        .activity-meta {
            display: flex;
            gap: 15px;
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        .activity-user,
        .activity-time {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .reports-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(600px, 1fr));
            gap: 20px;
        }

        .report-card {
            background: var(--gray-100);
            border-radius: 8px;
            padding: 20px;
        }

        .report-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 15px;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--gray-200);
        }

        .table th {
            font-weight: 600;
            color: var(--text-secondary);
            background: var(--gray-100);
        }

        .progress {
            background: var(--gray-200);
            border-radius: 10px;
            overflow: hidden;
        }

        .progress-bar {
            background: var(--accent-green);
            color: white;
            text-align: center;
            line-height: 20px;
            font-size: 0.85rem;
        }

        .progress-bar.bg-success {
            background: var(--accent-green);
        }

        @media (max-width: 768px) {
            .reports-grid {
                grid-template-columns: 1fr;
            }

            .activity-filters {
                flex-direction: column;
                gap: 10px;
            }

            .activity-filters .filter-select {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="index.php" class="navbar-brand">
            <i class="fas fa-hospital"></i> Asylum & Rehab Center
        </a>
    </nav>

    <div class="container-fluid">
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-users"></i> Staff Management
            </h1>
            <div class="action-buttons">
                <a href="<?php echo $_SESSION['role'] === 'admin' ? 'admin_dashboard.php' : 'chief_staff_dashboard.php'; ?>" class="btn-dashboard">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-card-title">Total Staff</div>
                <div class="stat-card-value"><?php echo $stats['total_staff']; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-card-title">Active Staff</div>
                <div class="stat-card-value"><?php echo $stats['active_staff']; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-card-title">On Leave</div>
                <div class="stat-card-value"><?php echo $stats['on_leave_staff']; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-card-title">Total Roles</div>
                <div class="stat-card-value"><?php echo $stats['total_roles']; ?></div>
                <div class="stat-card-change">
                    <span>Across departments</span>
                </div>
            </div>
        </div>

        <!-- Role Distribution Chart -->
        <div class="chart-container">
            <h3 class="chart-title">Role Distribution</h3>
            <div class="role-distribution">
                <?php foreach ($roles as $role): ?>
                <div class="role-item">
                    <div class="role-name"><?php echo ucfirst(str_replace('-', ' ', $role['role'])); ?></div>
                    <div class="role-count"><?php echo $role['count']; ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Staff Performance Reports -->
        <div class="reports-container">
            <h3 class="section-title">Staff Performance Reports</h3>
            <div class="reports-grid">
                <!-- Performance Metrics -->
                <div class="report-card">
                    <h4 class="report-title">Performance Metrics</h4>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Staff</th>
                                    <th>Role</th>
                                    <th>Patients</th>
                                    <th>Appointments</th>
                                    <th>Cancelled</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($performance_metrics as $metric): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($metric['full_name']); ?></td>
                                    <td><?php echo ucfirst(str_replace('-', ' ', $metric['role'])); ?></td>
                                    <td><?php echo $metric['total_patients']; ?></td>
                                    <td><?php echo $metric['total_appointments']; ?></td>
                                    <td><?php echo $metric['cancelled_appointments']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="filters-section">
            <div class="search-box">
                <input type="text" class="search-input" id="searchInput" placeholder="Search staff by name, ID, or role...">
                <select class="filter-select" id="roleFilter">
                    <option value="">All Roles</option>
                    <option value="chief-staff">Chief Staff</option>
                    <option value="doctor">Doctor</option>
                    <option value="therapist">Therapist</option>
                    <option value="nurse">Nurse</option>
                    <option value="receptionist">Receptionist</option>
                </select>
                <select class="filter-select" id="statusFilter">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="on_leave">On Leave</option>
                </select>
                <select class="filter-select" id="shiftFilter">
                    <option value="">All Shifts</option>
                    <option value="Morning">Morning</option>
                    <option value="Afternoon">Afternoon</option>
                    <option value="Night">Night</option>
                </select>
            </div>
        </div>

        <div class="staff-grid">
                    <?php foreach ($staff_members as $staff): ?>
            <div class="staff-card" data-role="<?php echo htmlspecialchars($staff['role']); ?>" 
                 data-status="<?php echo htmlspecialchars($staff['status']); ?>"
                 data-shift="<?php echo htmlspecialchars($staff['shift']); ?>">
                <div class="staff-header">
                    <span class="staff-status status-<?php echo strtolower($staff['status']); ?>">
                        <?php echo ucwords(str_replace('_', ' ', $staff['status'])); ?>
                            </span>
                    <h2 class="staff-name"><?php echo htmlspecialchars($staff['full_name']); ?></h2>
                    <div class="staff-role">
                        <?php echo ucfirst(str_replace('-', ' ', $staff['role'])); ?>
                    </div>
                </div>
                <div class="staff-body">
                    <div class="staff-info">
                        <div class="info-item">
                            <i class="fas fa-id-card"></i>
                            <span><?php echo htmlspecialchars($staff['staff_id']); ?></span>
                        </div>
                        <div class="info-item">
                            <i class="fas fa-envelope"></i>
                            <span><?php echo htmlspecialchars($staff['email']); ?></span>
                        </div>
                        <div class="info-item">
                            <i class="fas fa-phone"></i>
                            <span><?php echo htmlspecialchars($staff['phone']); ?></span>
                        </div>
                        <?php if ($staff['shift']): ?>
                        <div class="info-item">
                            <i class="fas fa-clock"></i>
                            <span><?php echo htmlspecialchars($staff['shift']); ?> Shift</span>
                        </div>
                        <?php endif; ?>
                        <div class="info-item">
                            <i class="fas fa-calendar"></i>
                            <span>Joined: <?php echo date('M d, Y', strtotime($staff['created_at'])); ?></span>
                        </div>
                    </div>
                </div>
                <div class="staff-actions">
                    <button class="action-btn view-btn" onclick="showCredentials('<?php echo $staff['staff_id']; ?>')" title="View Credentials">
                                <i class="fas fa-eye"></i>
                            </button>
                    <button class="action-btn edit-btn" onclick="editStaff('<?php echo $staff['staff_id']; ?>')" title="Edit Staff">
                                <i class="fas fa-edit"></i>
                            </button>
                            <form method="POST" action="" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this staff member?');">
                                <input type="hidden" name="delete_staff_id" value="<?php echo htmlspecialchars($staff['staff_id']); ?>">
                        <button type="submit" class="action-btn delete-btn" title="Delete Staff">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                </div>
            </div>
                    <?php endforeach; ?>
        </div>
    </div>

    <!-- Credentials Modal -->
    <div class="modal fade" id="credentialsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Staff Credentials</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="credentials-box">
                        <div class="form-group">
                            <label>Staff ID</label>
                            <div class="d-flex align-items-center">
                                <input type="text" id="modalStaffId" class="form-control" readonly>
                                <button class="btn btn-outline-primary ms-2" onclick="copyToClipboard('modalStaffId')">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <div class="d-flex align-items-center">
                                <input type="text" id="modalEmail" class="form-control" readonly>
                                <button class="btn btn-outline-primary ms-2" onclick="copyToClipboard('modalEmail')">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Password</label>
                            <div class="d-flex align-items-center">
                                <input type="text" id="modalPassword" class="form-control" readonly>
                                <button class="btn btn-outline-primary ms-2" onclick="copyToClipboard('modalPassword')">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        </div>
                        <div class="alert alert-warning mt-3">
                            <i class="fas fa-exclamation-triangle"></i>
                            Please save these credentials. The password cannot be recovered.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Staff Modal -->
    <div class="modal fade" id="editStaffModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Staff Member</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editStaffForm" method="POST" action="update_staff.php">
                        <input type="hidden" id="edit_staff_id" name="staff_id">
                        <div class="form-group">
                            <label class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="edit_full_name" name="full_name" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Role</label>
                            <select class="form-control" id="edit_role" name="role" required>
                                <option value="chief-staff">Chief Staff</option>
                                <option value="doctor">Doctor</option>
                                <option value="therapist">Therapist</option>
                                <option value="nurse">Nurse</option>
                                <option value="receptionist">Receptionist</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Phone</label>
                            <input type="tel" class="form-control" id="edit_phone" name="phone" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Date of Birth</label>
                            <input type="date" class="form-control" id="edit_dob" name="dob" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Gender</label>
                            <select class="form-control" id="edit_gender" name="gender" required>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" id="edit_address" name="address" required></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Experience (years)</label>
                            <input type="number" class="form-control" id="edit_experience" name="experience" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Shift</label>
                            <select class="form-control" id="edit_shift" name="shift">
                                <option value="">None</option>
                                <option value="Morning">Morning</option>
                                <option value="Afternoon">Afternoon</option>
                                <option value="Night">Night</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select class="form-control" id="edit_status" name="status" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="on_leave">On Leave</option>
                            </select>
                        </div>
                        <div class="text-end mt-4">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize Bootstrap modals
        const credentialsModal = new bootstrap.Modal(document.getElementById('credentialsModal'));
        const editStaffModal = new bootstrap.Modal(document.getElementById('editStaffModal'));

        // Search and filter functionality
        document.getElementById('searchInput').addEventListener('input', filterStaff);
        document.getElementById('roleFilter').addEventListener('change', filterStaff);
        document.getElementById('statusFilter').addEventListener('change', filterStaff);
        document.getElementById('shiftFilter').addEventListener('change', filterStaff);

        function filterStaff() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const roleFilter = document.getElementById('roleFilter').value.toLowerCase();
            const statusFilter = document.getElementById('statusFilter').value.toLowerCase();
            const shiftFilter = document.getElementById('shiftFilter').value.toLowerCase();

            document.querySelectorAll('.staff-card').forEach(card => {
                const name = card.querySelector('.staff-name').textContent.toLowerCase();
                const role = card.dataset.role.toLowerCase();
                const status = card.dataset.status.toLowerCase();
                const shift = card.dataset.shift.toLowerCase();
                const staffId = card.querySelector('.info-item:first-child span').textContent.toLowerCase();

                const matchesSearch = name.includes(searchTerm) || staffId.includes(searchTerm);
                const matchesRole = !roleFilter || role === roleFilter;
                const matchesStatus = !statusFilter || status === statusFilter;
                const matchesShift = !shiftFilter || shift === shiftFilter;

                card.style.display = matchesSearch && matchesRole && matchesStatus && matchesShift ? 'block' : 'none';
            });
        }

        // Show credentials modal
        function showCredentials(staffId) {
            fetch(`get_staff_credentials.php?staff_id=${staffId}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('modalStaffId').value = data.staff_id;
                    document.getElementById('modalEmail').value = data.email;
                    document.getElementById('modalPassword').value = data.password;
                    credentialsModal.show();
                });
        }

        // Copy to clipboard functionality
        function copyToClipboard(elementId) {
            const element = document.getElementById(elementId);
            element.select();
            document.execCommand('copy');
            
            // Show feedback
            const button = element.nextElementSibling;
            const originalIcon = button.innerHTML;
            button.innerHTML = '<i class="fas fa-check"></i>';
            setTimeout(() => {
                button.innerHTML = originalIcon;
            }, 2000);
        }

        // Edit staff functionality
        function editStaff(staffId) {
            // Fetch staff details and populate the form
            fetch(`get_staff_details.php?staff_id=${staffId}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('edit_staff_id').value = data.staff_id;
                    document.getElementById('edit_full_name').value = data.full_name;
                    document.getElementById('edit_role').value = data.role;
                    document.getElementById('edit_phone').value = data.phone;
                    document.getElementById('edit_dob').value = data.dob;
                    document.getElementById('edit_gender').value = data.gender;
                    document.getElementById('edit_address').value = data.address;
                    document.getElementById('edit_experience').value = data.experience;
                    document.getElementById('edit_shift').value = data.shift;
                    document.getElementById('edit_status').value = data.status;
                    editStaffModal.show();
                });
        }

        // Handle form submission
        document.getElementById('editStaffForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            
            fetch('update_staff.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Check if we received new credentials
                    if (data.newCredentials) {
                        // Show credentials in modal
                        const credentialsHtml = `
                            <div class="alert alert-info">
                                <h5>New Credentials Generated</h5>
                                <p>Since the role was changed, new login credentials have been generated:</p>
                                <div class="mb-2">
                                    <strong>Email:</strong> ${data.newCredentials.email}
                                </div>
                                <div class="mb-2">
                                    <strong>Password:</strong> ${data.newCredentials.password}
                                </div>
                                <p class="mt-2 text-danger">Please save these credentials. The password cannot be recovered later.</p>
                            </div>
                        `;
                        
                        // Show alert with new credentials
                        editStaffModal.hide();
                        
                        // Create a new modal for credentials
                        const credentialModal = document.createElement('div');
                        credentialModal.className = 'modal fade';
                        credentialModal.id = 'newCredentialModal';
                        credentialModal.innerHTML = `
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Staff Updated - New Credentials</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        ${credentialsHtml}
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-primary" data-bs-dismiss="modal" onclick="location.reload()">OK</button>
                                    </div>
                                </div>
                            </div>
                        `;
                        
                        document.body.appendChild(credentialModal);
                        const bsModal = new bootstrap.Modal(document.getElementById('newCredentialModal'));
                        bsModal.show();
                        
                        // Add event listener to reload page when modal is closed
                        document.getElementById('newCredentialModal').addEventListener('hidden.bs.modal', function() {
                            location.reload();
                        });
                    } else {
                        // Regular success case
                        editStaffModal.hide();
                        location.reload();
                    }
                } else {
                    alert('Error updating staff: ' + data.error);
                }
            });
        });

        // Update search functionality to use AJAX
        let searchTimeout;
        document.getElementById('searchInput').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                const searchTerm = this.value;
                const roleFilter = document.getElementById('roleFilter').value;
                const statusFilter = document.getElementById('statusFilter').value;
                const shiftFilter = document.getElementById('shiftFilter').value;

                const url = new URL(window.location.href);
                url.searchParams.set('search', searchTerm);
                url.searchParams.set('type', roleFilter);
                url.searchParams.set('status', statusFilter);
                url.searchParams.set('shift', shiftFilter);

                window.location.href = url.toString();
            }, 500);
        });
    </script>
</body>
</html> 