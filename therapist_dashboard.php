<?php

session_start();
// Prevent browser from caching authenticated pages
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
// Enforce session/role check for therapist
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'therapist') {
    header('Location: index.php');
    exit();
}
require_once 'session_check.php';
check_login(['therapist']);
require_once 'db.php';

// Therapist info
$user_id = $_SESSION['user_id'];

// Get therapist's staff record
$stmt = $conn->prepare("SELECT * FROM staff WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$staff_result = $stmt->get_result();
$staff = $staff_result->fetch_assoc();
if (!$staff) {
    die("Staff not found in database. Please log in again.");
}

// Get therapist's user record
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND role = 'therapist'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_result = $stmt->get_result();
$therapist = $user_result->fetch_assoc();
if (!$therapist) {
    die("Therapist user account not found. Please contact administrator.");
}

// Get therapist's staff_id and user_id
$therapist_staff_id = $staff['staff_id']; // string staff_id
$therapist_user_id = $staff['user_id'];   // numeric user_id

// Get assigned rehabilitation patients (use user_id for staff_patient_assignments)
$patients_query = "SELECT p.*, u.first_name, u.last_name, u.date_of_birth, p.status 
                  FROM patients p 
                  JOIN users u ON p.user_id = u.id 
                  JOIN staff_patient_assignments spa ON p.id = spa.patient_id 
                  WHERE spa.staff_id = ? AND p.type = 'Rehabilitation' 
                  ORDER BY p.admission_date DESC";
$stmt = $conn->prepare($patients_query);
$stmt->bind_param("i", $therapist_user_id);
$stmt->execute();
$patients_result = $stmt->get_result();
$patients = [];
while($row = $patients_result->fetch_assoc()) {
    $patients[] = [
        'id' => $row['id'],
        'name' => $row['first_name'] . ' ' . $row['last_name'],
        'age' => date_diff(date_create($row['date_of_birth']), date_create('today'))->y,
        'condition' => $row['medical_history'] ?? 'Not specified',
        'status' => $row['status'],
        'admission' => $row['admission_date'],
        'patient_id' => $row['patient_id']
    ];
}

// Therapist stats
$total_patients = count($patients);
$today = date('Y-m-d');
$appointments_query = "SELECT COUNT(*) as total FROM appointments WHERE therapist = ? AND date = ?";
$stmt = $conn->prepare($appointments_query);
$stmt->bind_param("ss", $therapist_staff_id, $today);
$stmt->execute();
$today_appointments = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$recovery_rate = 92; // Placeholder, customize as needed
$active_cases = $total_patients;

// Fetch today's appointments for this therapist
$appointments_list_query = "SELECT a.*, p.full_name as patient_name FROM appointments a JOIN patients p ON a.patient_id = p.patient_id WHERE a.date = ? AND a.therapist = ? ORDER BY a.time ASC";
$stmt = $conn->prepare($appointments_list_query);
$stmt->bind_param("ss", $today, $therapist_staff_id);
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
    <title>Therapist Dashboard - United Medical Asylum & Rehab Facility</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; margin: 0; }
        .dashboard-container { display: flex; min-height: 100vh; }
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
        .sidebar.active { transform: translateX(0); }
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
        .main-content { flex: 1; margin-left: 280px; padding: 2rem; background: #f7f8fa; min-height: 100vh; }
        .header { background: rgba(255,255,255,0.95); border-radius: 20px; padding: 1.5rem 2rem; margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 10px 40px rgba(0,0,0,0.1); }
        .header-left h2 { color: #1f2937; font-size: 1.75rem; font-weight: 600; margin-bottom: 0.25rem; }
        .header-left p { color: #6b7280; font-size: 0.875rem; }
        .header-right { display: flex; align-items: center; gap: 1rem; }
        .user-info { display: flex; align-items: center; gap: 10px; }
        .user-avatar { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #667eea, #764ba2); display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; }
        .user-details { display: flex; align-items: center; }
        .user-name { font-weight: 600; color: #2d3748; }
        .logout-btn { padding: 5px 10px; border-radius: 5px; transition: all 0.3s ease; color: #dc3545; text-decoration: none; margin-left: 15px; }
        .logout-btn:hover { background-color: #fee2e2; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .stat-card { background: rgba(255,255,255,0.95); border-radius: 20px; padding: 2rem; text-align: center; transition: all 0.3s ease; cursor: pointer; position: relative; overflow: hidden; }
        .stat-card:hover { transform: translateY(-10px); box-shadow: 0 20px 60px rgba(0,0,0,0.15); }
        .stat-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(135deg, #667eea, #764ba2); border-radius: 20px 20px 0 0; }
        .stat-icon { width: 60px; height: 60px; margin: 0 auto 1rem; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: white; background: linear-gradient(135deg, #667eea, #764ba2); }
        .stat-number { font-size: 2.5rem; font-weight: 700; color: #1f2937; margin-bottom: 0.5rem; }
        .stat-label { color: #6b7280; font-size: 0.875rem; font-weight: 500; }
        .patients-section { background: rgba(255,255,255,0.95); border-radius: 20px; padding: 2rem; margin-bottom: 2rem; }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        .section-title { font-size: 1.5rem; font-weight: 600; color: #1f2937; }
        .patients-list { display: flex; flex-wrap: wrap; gap: 1.5rem; }
        .patient-card { background: rgba(249,250,251,0.8); border-radius: 16px; padding: 1.5rem; min-width: 260px; flex: 1 1 260px; max-width: 320px; margin-bottom: 1rem; transition: all 0.3s ease; cursor: pointer; border: 1px solid rgba(229,231,235,0.5); display: flex; flex-direction: column; justify-content: space-between; }
        .patient-card:hover { transform: translateX(5px); box-shadow: 0 10px 30px rgba(0,0,0,0.1); background: rgba(255,255,255,0.9); }
        .patient-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
        .patient-name { font-size: 1.1rem; font-weight: 600; color: #1f2937; margin-bottom: 0.25rem; }
        .patient-age { color: #6b7280; font-size: 0.875rem; }
        .patient-status { padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.75rem; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; }
        .status-admitted { background: rgba(16,185,129,0.1); color: #059669; }
        .status-active { background: rgba(59,130,246,0.1); color: #2563eb; }
        .status-discharged { background: rgba(239,68,68,0.1); color: #dc2626; }
        .patient-details { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 1rem; }
        .patient-detail { display: flex; flex-direction: column; }
        .detail-label { color: #6b7280; font-size: 0.75rem; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.25rem; }
        .detail-value { color: #1f2937; font-size: 0.875rem; font-weight: 500; }
        .treatment-btn { margin-top: 1rem; background: linear-gradient(135deg, #10b981, #059669); color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 12px; cursor: pointer; font-size: 0.95rem; font-weight: 500; transition: all 0.3s ease; text-align: center; text-decoration: none; display: inline-block; }
        .treatment-btn:hover { background: linear-gradient(135deg, #059669, #10b981); transform: translateY(-2px); box-shadow: 0 5px 20px rgba(16,185,129,0.3); }
        @media (max-width: 1024px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.active { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .main-content.expanded { margin-left: 0; }
        }
        @media (max-width: 768px) { .sidebar { width: 100vw; position: static; height: auto; } .main-content { margin-left: 0; } .header { flex-direction: column; align-items: flex-start; padding: 1rem 1.5rem; } }
    </style>
</head>
<body>
    <div class="dashboard-container">
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
                    <a href="appointments_therapist.php" class="nav-link" data-section="appointments">
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
                <li class="nav-item">
                    <a href="export_requests.php" class="nav-link">
                        <i class="fas fa-shield-alt"></i>
                        Export Requests
                    </a>
                </li>
            </ul>
        </nav>
        <main class="main-content" id="mainContent">
            <header class="header">
                <div class="header-left">
                    <h2>Good <?php echo date('H') < 12 ? 'Morning' : (date('H') < 17 ? 'Afternoon' : 'Evening'); ?>, <?php echo htmlspecialchars($therapist['first_name'] . ' ' . $therapist['last_name']); ?></h2>
                    <p><?php echo date('l, F j, Y'); ?></p>
                </div>
                <div class="header-right">
                    <div class="user-info">
                        <div class="user-avatar">
                            <i class="fas fa-user-nurse"></i>
                        </div>
                        <div class="user-details">
                            <span class="user-name"><?php echo htmlspecialchars($therapist['first_name'] . ' ' . $therapist['last_name']); ?></span>
                            <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
                        </div>
                    </div>
                </div>
            </header>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-number"><?php echo $total_patients; ?></div>
                    <div class="stat-label">Assigned Patients</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
                    <div class="stat-number"><?php echo $today_appointments; ?></div>
                    <div class="stat-label">Today's Appointments</div>
                </div>
            </div>
            <div class="content-grid">
                <section class="patients-section">
                    <div class="section-header">
                        <h3 class="section-title">Assigned Rehabilitation Patients</h3>
                    </div>
                    <div class="patients-list">
                        <?php foreach($patients as $patient): ?>
                        <div class="patient-card">
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
                            <a href="treatment.php?patient_id=<?php echo urlencode($patient['id']); ?>" class="treatment-btn"><i class="fas fa-notes-medical"></i> Give Treatment</a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </section>
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
    // Sidebar toggle for mobile
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');
    if (menuToggle && sidebar && mainContent) {
        menuToggle.addEventListener('click', () => {
            sidebar.classList.toggle('active');
            mainContent.classList.toggle('expanded');
        });
        // Optional: close sidebar when clicking outside on mobile
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 1024 && sidebar.classList.contains('active')) {
                if (!sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
                    sidebar.classList.remove('active');
                    mainContent.classList.remove('expanded');
                }
            }
        });
    }
    </script>
</body>
</html> 