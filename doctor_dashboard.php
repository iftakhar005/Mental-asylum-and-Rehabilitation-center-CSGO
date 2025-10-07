<?php

require_once 'session_check.php';
// Prevent browser from caching authenticated pages
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
check_login(['doctor']);
require_once 'db.php';

// Debug information
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get doctor's information using numeric user_id
$user_id = $_SESSION['user_id']; // This is now the numeric user ID
// Debug
// echo "Debug - User ID from session: " . $user_id . "<br>";

// First check if the user exists in staff table
$check_staff = "SELECT * FROM staff WHERE user_id = ?";
$stmt = $conn->prepare($check_staff);
if (!$stmt) {
    die("Error preparing staff check statement: " . $conn->error);
}
$stmt->bind_param("i", $user_id);
if (!$stmt->execute()) {
    die("Error executing staff check statement: " . $stmt->error);
}
$staff_result = $stmt->get_result();
$staff = $staff_result->fetch_assoc();

if ($staff) {
    // echo "Debug - Staff found in database. Role: " . $staff['role'] . "<br>";
    // Now get the corresponding user record
    $user_query = "SELECT * FROM users WHERE id = ? AND role = 'doctor'";
    $stmt = $conn->prepare($user_query);
    if (!$stmt) {
        die("Error preparing user statement: " . $conn->error);
    }
    $stmt->bind_param("i", $user_id);
    if (!$stmt->execute()) {
        die("Error executing user statement: " . $stmt->error);
    }
    $user_result = $stmt->get_result();
    $doctor = $user_result->fetch_assoc();
    if (!$doctor) {
        die("Doctor user account not found. Please contact administrator.");
    }
} else {
    die("Staff not found in database. Please log in again.");
}

// Get the current doctor's staff_id and user_id
$doctor_staff_id = $staff['staff_id']; // String staff_id (not used for assignments)
$doctor_user_id = $staff['user_id'];   // Numeric user_id (used for assignments)

// Fix total patient count to only count assigned patients
$total_patients_query = "SELECT COUNT(*) as total FROM staff_patient_assignments WHERE staff_id = ? AND status = 'active'";
$stmt = $conn->prepare($total_patients_query);
$stmt->bind_param("i", $doctor_user_id);
$stmt->execute();
$total_patients_result = $stmt->get_result();
$total_patients = $total_patients_result->fetch_assoc()['total'] ?? 0;

// Get today's appointments count (for this doctor only)
$today = date('Y-m-d');
$appointments_query = "SELECT COUNT(*) as total FROM appointments WHERE date = ? AND doctor = ?";
$stmt = $conn->prepare($appointments_query);
$stmt->bind_param("ss", $today, $doctor_staff_id);
$stmt->execute();
$today_appointments = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

// Get today's appointments list for this doctor only
$appointments_query = "SELECT a.*, p.full_name as patient_name FROM appointments a JOIN patients p ON a.patient_id = p.patient_id WHERE a.date = ? AND a.doctor = ? ORDER BY a.time ASC";
$stmt = $conn->prepare($appointments_query);
$stmt->bind_param("ss", $today, $doctor_staff_id);
$stmt->execute();
$appointments_result = $stmt->get_result();
$appointments = [];
while($row = $appointments_result->fetch_assoc()) {
    $appointments[] = [
        'time' => date('H:i', strtotime($row['time'])),
        'patient' => $row['patient_name'],
        'type' => $row['type']
    ];
}

// Get critical cases count (admitted asylum patients assigned to this doctor)
$critical_cases_query = "SELECT COUNT(*) as total FROM patients p JOIN staff_patient_assignments spa ON p.id = spa.patient_id WHERE spa.staff_id = ? AND p.status = 'admitted' AND p.type = 'Asylum'";
$stmt = $conn->prepare($critical_cases_query);
$stmt->bind_param("s", $doctor_staff_id);
$stmt->execute();
$critical_cases = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

// Get recent patients assigned to this doctor (any status)
$patients_query = "SELECT p.*, u.first_name, u.last_name, u.date_of_birth, p.status 
                  FROM patients p 
                  JOIN users u ON p.user_id = u.id 
                  JOIN staff_patient_assignments spa ON p.id = spa.patient_id 
                  WHERE spa.staff_id = ? 
                  ORDER BY p.admission_date DESC 
                  LIMIT 4";
$stmt = $conn->prepare($patients_query);
if (!$stmt) {
    die("Error preparing patients statement: " . $conn->error);
}
$stmt->bind_param("i", $user_id);
if (!$stmt->execute()) {
    die("Error executing patients statement: " . $stmt->error);
}
$patients_result = $stmt->get_result();
$patients = [];
while($row = $patients_result->fetch_assoc()) {
    $patients[] = [
        'id' => $row['id'],
        'name' => $row['first_name'] . ' ' . $row['last_name'],
        'age' => date_diff(date_create($row['date_of_birth']), date_create('today'))->y,
        'condition' => $row['medical_history'] ?? 'Not specified',
        'status' => $row['status'],
        'admission' => $row['admission_date']
    ];
}

// Calculate recovery rate (this is a placeholder - you should implement your own logic)
$recovery_rate = 94; // This should be calculated based on your actual data
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Dashboard - United Medical Asylum & Rehab Facility</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            overflow-x: hidden;
        }

        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styles */
        .sidebar {
            width: 280px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-right: 1px solid rgba(255, 255, 255, 0.2);
            padding: 2rem 0;
            position: fixed;
            height: 100vh;
            left: 0;
            top: 0;
            transform: translateX(0);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 1000;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }

        .sidebar.collapsed {
            transform: translateX(-100%);
        }

        .logo {
            text-align: center;
            margin-bottom: 3rem;
            padding: 0 2rem;
        }

        .logo h1 {
            color: #4f46e5;
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .logo p {
            color: #6b7280;
            font-size: 0.875rem;
        }

        .nav-menu {
            list-style: none;
            padding: 0 1rem;
        }

        .nav-item {
            margin-bottom: 0.5rem;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 1rem 1.5rem;
            color: #374151;
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .nav-link:hover, .nav-link.active {
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: white;
            transform: translateX(5px);
            box-shadow: 0 5px 20px rgba(79, 70, 229, 0.3);
        }

        .nav-link i {
            margin-right: 1rem;
            font-size: 1.1rem;
            width: 20px;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 2rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .main-content.expanded {
            margin-left: 0;
        }

        /* Header */
        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 1.5rem 2rem;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            animation: slideDown 0.6s ease-out;
        }

        .header-left h2 {
            color: #1f2937;
            font-size: 1.75rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .header-left p {
            color: #6b7280;
            font-size: 0.875rem;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .menu-toggle {
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: white;
            border: none;
            padding: 0.75rem;
            border-radius: 12px;
            cursor: pointer;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            display: none;
        }

        .menu-toggle:hover {
            transform: scale(1.05);
            box-shadow: 0 5px 20px rgba(79, 70, 229, 0.3);
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem 1rem;
            background: rgba(79, 70, 229, 0.1);
            border-radius: 50px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .user-profile:hover {
            background: rgba(79, 70, 229, 0.2);
            transform: translateY(-2px);
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            animation: fadeInUp 0.6s ease-out;
            animation-fill-mode: both;
        }

        .stat-card:nth-child(1) { animation-delay: 0.1s; }
        .stat-card:nth-child(2) { animation-delay: 0.2s; }
        .stat-card:nth-child(3) { animation-delay: 0.3s; }
        .stat-card:nth-child(4) { animation-delay: 0.4s; }

        .stat-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            border-radius: 20px 20px 0 0;
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            margin: 0 auto 1rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #6b7280;
            font-size: 0.875rem;
            font-weight: 500;
        }

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        /* Patients Section */
        .patients-section, .appointments-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2rem;
            animation: fadeInUp 0.6s ease-out 0.5s both;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1f2937;
        }

        .add-btn {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .add-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(16, 185, 129, 0.3);
        }

        /* Patient Cards */
        .patient-card {
            background: rgba(249, 250, 251, 0.8);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            cursor: pointer;
            border: 1px solid rgba(229, 231, 235, 0.5);
        }

        .patient-card:hover {
            transform: translateX(5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            background: rgba(255, 255, 255, 0.9);
        }

        .patient-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .patient-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.25rem;
        }

        .patient-age {
            color: #6b7280;
            font-size: 0.875rem;
        }

        .patient-status {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-stable {
            background: rgba(16, 185, 129, 0.1);
            color: #059669;
        }

        .status-improving {
            background: rgba(59, 130, 246, 0.1);
            color: #2563eb;
        }

        .status-critical {
            background: rgba(239, 68, 68, 0.1);
            color: #dc2626;
        }

        .patient-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-top: 1rem;
        }

        .patient-detail {
            display: flex;
            flex-direction: column;
        }

        .detail-label {
            color: #6b7280;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.25rem;
        }

        .detail-value {
            color: #1f2937;
            font-size: 0.875rem;
            font-weight: 500;
        }

        /* Appointments */
        .appointment-item {
            display: flex;
            align-items: center;
            padding: 1rem;
            background: rgba(249, 250, 251, 0.8);
            border-radius: 12px;
            margin-bottom: 0.75rem;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .appointment-item:hover {
            background: rgba(79, 70, 229, 0.05);
            transform: translateX(5px);
        }

        .appointment-time {
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.875rem;
            margin-right: 1rem;
            min-width: 70px;
            text-align: center;
        }

        .appointment-details h4 {
            color: #1f2937;
            font-size: 0.95rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .appointment-details p {
            color: #6b7280;
            font-size: 0.8rem;
        }

        /* Animations */
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
        }

        .pulse {
            animation: pulse 2s infinite;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .menu-toggle {
                display: block;
            }

            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }

            .header {
                padding: 1rem 1.5rem;
            }

            .main-content {
                padding: 1rem;
            }
        }

        /* Loading Animation */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 2rem;
            border-radius: 20px;
            width: 90%;
            max-width: 500px;
            animation: fadeInUp 0.3s ease-out;
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
        }

        .close:hover {
            color: #000;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            background-color: #4361ee;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }
        
        .user-details {
            display: flex;
            align-items: center;
        }
        
        .user-name {
            font-weight: 600;
            color: #2d3748;
        }
        
        .logout-btn {
            padding: 5px 10px;
            border-radius: 5px;
            transition: all 0.3s ease;
        }
        
        .logout-btn:hover {
            background-color: #fee2e2;
        }
        
        .logout-btn i {
            margin-right: 5px;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <nav class="sidebar" id="sidebar">
            <div class="logo">
                <h1><i class="fas fa-brain"></i> United Medical Asylum & Rehab Facility</h1>
                <p>United Medical Asylum & Rehab Facility</p>
            </div>
            
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="#" class="nav-link active" data-section="dashboard">
                        <i class="fas fa-tachometer-alt"></i>
                        Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a href="patient_vitals.php" class="nav-link" data-section="patients">
                        <i class="fas fa-users"></i>
                        Patients
                    </a>
                </li>
                <li class="nav-item">
                    <a href="appointments_doctor.php" class="nav-link" data-section="appointments">
                        <i class="fas fa-calendar-alt"></i>
                        Appointments
                    </a>
                </li>
                <li class="nav-item">
                    <a href="treatment.php" class="nav-link">
                        <i class="fas fa-pills"></i>
                        Treatment
                    </a>
                </li>
            </ul>
        </nav>

        <!-- Main Content -->
        <main class="main-content" id="mainContent">
            <!-- Header -->
            <header class="header">
                <div class="header-left">
                    <h2>Good <?php echo date('H') < 12 ? 'Morning' : (date('H') < 17 ? 'Afternoon' : 'Evening'); ?>, Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?></h2>
                    <p><?php echo date('l, F j, Y'); ?></p>
                </div>
                <div class="header-right">
                    <button class="menu-toggle" id="menuToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="user-info">
                        <div class="user-avatar">
                            <i class="fas fa-user-md"></i>
                        </div>
                        <div class="user-details">
                            <a href="logout.php" class="logout-btn" style="display: inline-block; margin-left: 15px; color: #dc3545; text-decoration: none;">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-number"><?php echo $total_patients; ?></div>
                    <div class="stat-label">Total Patients</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-number"><?php echo $today_appointments; ?></div>
                    <div class="stat-label">Today's Appointments</div>
                </div>
            </div>

            <!-- Content Grid -->
            <div class="content-grid">
                <!-- Patients Section -->
                <section class="patients-section">
                    <div class="section-header">
                        <h3 class="section-title">Recent Patients</h3>
                    </div>
                    
                    <div class="patients-list">
                        <?php foreach($patients as $patient): ?>
                        <div class="patient-card" onclick="showPatientDetails(<?php echo $patient['id']; ?>)">
                            <div class="patient-header">
                                <div>
                                    <div class="patient-name"><?php echo $patient['name']; ?></div>
                                    <div class="patient-age">Age: <?php echo $patient['age']; ?></div>
                                </div>
                                <div class="patient-status status-<?php echo strtolower(str_replace(' ', '-', $patient['status'])); ?>">
                                    <?php echo $patient['status']; ?>
                                </div>
                            </div>
                            <div class="patient-details">
                                <div class="patient-detail">
                                    <span class="detail-label">Condition</span>
                                    <span class="detail-value"><?php echo $patient['condition']; ?></span>
                                </div>
                                <div class="patient-detail">
                                    <span class="detail-label">Admission</span>
                                    <span class="detail-value"><?php echo date('M j, Y', strtotime($patient['admission'])); ?></span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <!-- Appointments Section -->
                <section class="appointments-section">
                    <div class="section-header">
                        <h3 class="section-title">Today's Schedule</h3>
                    </div>
                    
                    <div class="appointments-list">
                        <?php foreach($appointments as $appointment): ?>
                        <div class="appointment-item">
                            <div class="appointment-time"><?php echo $appointment['time']; ?></div>
                            <div class="appointment-details">
                                <h4><?php echo $appointment['patient']; ?></h4>
                                <p><?php echo $appointment['type']; ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            </div>
        </main>
    </div>

    <script>
        // Mobile menu toggle
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');

        menuToggle.addEventListener('click', () => {
            sidebar.classList.toggle('active');
            mainContent.classList.toggle('expanded');
        });

        // Navigation
        const navLinks = document.querySelectorAll('.nav-link');
        navLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                // Only prevent default for links with href="#"
                if (link.getAttribute('href') === '#') {
                    e.preventDefault();
                    navLinks.forEach(l => l.classList.remove('active'));
                    link.classList.add('active');
                    const section = link.getAttribute('data-section');
                    const icon = link.querySelector('i');
                    const originalClass = icon.className;
                    icon.className = 'fas fa-spinner fa-spin';
                    setTimeout(() => {
                        icon.className = originalClass;
                        showNotification(`Switched to ${section.charAt(0).toUpperCase() + section.slice(1)} section`);
                    }, 500);
                }
                // Otherwise, let the browser follow the link
            });
        });

        // Patient card interactions
        function showPatientDetails(patientId) {
            const card = event.currentTarget;
            card.style.transform = 'scale(0.98)';
            
            setTimeout(() => {
                card.style.transform = 'scale(1)';
                showNotification(`Viewing details for Patient ID: ${patientId}`);
            }, 150);
        }

        // Notification system
        function showNotification(message) {
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: linear-gradient(135deg, #10b981, #059669);
                color: white;
                padding: 1rem 1.5rem;
                border-radius: 12px;
                box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
                z-index: 3000;
                animation: slideInRight 0.3s ease-out;
                font-weight: 500;
            `;
            
            notification.textContent = message;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.animation = 'slideOutRight 0.3s ease-out';
                setTimeout(() => {
                    document.body.removeChild(notification);
                }, 300);
            }, 3000);
        }

        // Add slide animations for notifications
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideInRight {
                from {
                    opacity: 0;
                    transform: translateX(100px);
                }
                to {
                    opacity: 1;
                    transform: translateX(0);
                }
            }
            
            @keyframes slideOutRight {
                from {
                    opacity: 1;
                    transform: translateX(0);
                }
                to {
                    opacity: 0;
                    transform: translateX(100px);
                }
            }
        `;
        document.head.appendChild(style);

        // Initialize dashboard
        document.addEventListener('DOMContentLoaded', () => {
            showNotification('Welcome to United Medical Asylum & Rehab Facility Dashboard!');
            
            // Add hover effects to appointment items
            const appointmentItems = document.querySelectorAll('.appointment-item');
            appointmentItems.forEach(item => {
                item.addEventListener('mouseenter', () => {
                    item.style.background = 'rgba(79, 70, 229, 0.1)';
                });
                
                item.addEventListener('mouseleave', () => {
                    item.style.background = 'rgba(249, 250, 251, 0.8)';
                });
            });
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if (e.ctrlKey || e.metaKey) {
                switch(e.key) {
                    case 'n':
                        e.preventDefault();
                        showNotification('Patient addition functionality removed for doctors');
                        break;
                    case 'm':
                        e.preventDefault();
                        menuToggle.click();
                        break;
                }
            }
            
            if (e.key === 'Escape') {
                showNotification('Patient addition functionality removed for doctors');
            }
        });

        // Add click handler for treatment menu item
        const treatmentMenuItem = document.querySelector('.menu-item:has(i.fa-pills)');
        if (treatmentMenuItem) {
            treatmentMenuItem.addEventListener('click', function() {
                window.location.href = 'treatment.php';
            });
        }
    </script>
</body>
</html>