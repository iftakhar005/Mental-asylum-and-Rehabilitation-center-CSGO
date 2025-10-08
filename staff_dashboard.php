<?php

session_start();
// Prevent browser from caching authenticated pages
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
// Enforce session/role check for staff
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['doctor','therapist','nurse','receptionist','chief-staff','staff'])) {
    header('Location: index.php');
    exit();
}
require_once 'db.php';

// Check if user is logged in and has required role
if (!isset($_SESSION['staff_id']) || !isset($_SESSION['user_id']) || !isset($_SESSION['username']) || !isset($_SESSION['role'])) {
    header('Location: index.php');
    exit();
}

// Fetch assigned patients from the database (join with users to get patient name)
$stmt = $conn->prepare("SELECT patients.id, users.username, patients.room_number, patients.status, patients.admission_date FROM patients JOIN users ON patients.user_id = users.id WHERE patients.status = 'active'");
if (!$stmt) {
    error_log("Error preparing statement for assigned patients: " . $conn->error);
    die("Error preparing statement for assigned patients: " . $conn->error);
}
$stmt->execute();
$result = $stmt->get_result();
$assigned_patients = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

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
    <title>Staff Dashboard</title>
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
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        body { background-color: #f5f7fb; color: var(--dark); overflow-x: hidden; }
        .dashboard { display: block; min-height: 100vh; }
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
        .main-content {
            margin-left: 16rem;
            transition: margin-left 0.3s ease-in-out;
            padding-top: 70px;
        }
        .sidebar.collapsed ~ .main-content { margin-left: 4.5rem; }
        .topbar {
            margin-left: 0;
            transition: margin-left 0.3s ease-in-out;
            display: flex;
            align-items: center;
            height: 64px;
            background: #fff;
            box-shadow: 0 2px 8px rgba(67,97,238,0.07);
            border-bottom: 1px solid #e9ecef;
            z-index: 1100;
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            padding: 0 32px;
        }
        .topbar-content {
            display: flex;
            align-items: center;
            gap: 18px;
            width: 100%;
            height: 64px;
        }
        .topbar-content img {
            display: block;
            margin: 0;
            height: 40px;
            width: 40px;
            object-fit: contain;
            vertical-align: middle;
        }
        .topbar-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
            white-space: nowrap;
        }
        .sidebar.collapsed ~ .topbar { margin-left: 4.5rem; }
        .section { background-color: white; border-radius: 10px; padding: 20px; margin-bottom: 30px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .section-title { font-size: 1.2rem; font-weight: 600; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid var(--light-gray); }
        th { font-weight: 600; color: var(--gray); font-size: 0.85rem; text-transform: uppercase; }
        tr:hover { background-color: var(--light-gray); }
        .btn { padding: 8px 15px; border-radius: 5px; border: none; font-weight: 500; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; }
        .btn-primary { background-color: #4361ee; color: #fff; }
        .btn-primary:hover { background-color: #3f37c9; }
        .btn-outline { background-color: transparent; border: 1px solid var(--primary); color: var(--primary); }
        .btn-outline:hover { background-color: var(--primary); color: white; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 500; font-size: 0.9rem; }
        .form-control { width: 100%; padding: 10px 15px; border: 1px solid var(--light-gray); border-radius: 5px; font-size: 0.9rem; transition: border-color 0.3s ease; }
        .form-control:focus { outline: none; border-color: #4361ee; box-shadow: 0 0 0 3px rgba(67,97,238,0.35); }
        @media (max-width: 768px) {
            .sidebar { width: 4.5rem; }
            .sidebar.collapsed { width: 4.5rem; }
            .main-content, .topbar { margin-left: 4.5rem; }
            .main-content { padding-top: 70px; }
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
    <div class="topbar">
        <div class="topbar-content">
            <button id="sidebar-toggle" class="text-gray-100 hover:text-white focus:outline-none" style="background:none;border:none;font-size:1.7rem;cursor:pointer;">
                <i class="fas fa-bars"></i>
            </button>
            <img src="https://img.icons8.com/ios-filled/40/4361ee/hospital-room.png" alt="Logo">
            <span class="topbar-title">Staff Dashboard</span>
            <div class="user-profile">
                <div class="user-avatar">ST</div>
                <span class="user-name">Staff</span>
                <i class="fas fa-chevron-down"></i>
                <div class="user-dropdown">
                    <a href="logout.php" class="dropdown-item">
                        <i class="fas fa-sign-out-alt"></i>
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </div>
    <aside id="sidebar" class="sidebar expanded">
        <button class="close-sidebar" id="closeSidebarBtn"><i class="fas fa-times"></i></button>
        <div style="text-align:center; margin-bottom: 10px;">
            <!-- Logo removed -->
        </div>
        <div class="sidebar-header" style="text-align:center; margin-bottom: 30px; margin-top: 30px;">
            <h2 style="margin-bottom: 6px;">Rehab Center</h2>
            <p style="margin:0;">Staff Dashboard</p>
        </div>
        <div class="sidebar-menu">
            <!-- <div class="menu-item active" id="dashboard-link" style="cursor:pointer;"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></div> -->
            <div class="menu-item" id="assigned-patients-link" style="cursor:pointer;"><i class="fas fa-user-injured"></i><span>Assigned Patients</span></div>
            <div class="menu-item" id="medications-link" style="cursor:pointer;"><i class="fas fa-pills"></i><span>Medications</span></div>
            <div class="menu-item" id="activities-link" style="cursor:pointer;"><i class="fas fa-running"></i><span>Activities</span></div>
            <div class="menu-item"><a href="export_requests.php" style="color:inherit;text-decoration:none;"><i class="fas fa-shield-alt"></i><span>Export Requests</span></a></div>
            <div class="menu-item"><a href="logout.php" style="color:inherit;text-decoration:none;"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a></div>
        </div>
    </aside>
    <div class="main-content" id="main-content">
        <div class="section" id="assigned-patients-section">
            <div class="section-header">
                <h2 class="section-title">Assigned Patients</h2>
            </div>
            <table>
                <thead>
                    <tr><th>Patient ID</th><th>Name</th><th>Room</th><th>Status</th><th>Admission Date</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($assigned_patients as $p): ?>
                    <tr>
                        <td><?php echo $p['id']; ?></td>
                        <td><?php echo $p['username']; ?></td>
                        <td><?php echo $p['room_number']; ?></td>
                        <td><?php echo $p['status']; ?></td>
                        <td><?php echo $p['admission_date']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="section" id="medications-section">
            <div class="section-header">
                <h2 class="section-title">Medications</h2>
            </div>
            <form method="get" class="mb-3">
                <label for="med_patient_id">Select Patient:</label>
                <select class="form-control" id="med_patient_id" name="med_patient_id" style="max-width:300px;display:inline-block;" onchange="this.form.submit()">
                    <option value="">-- Choose Patient --</option>
                    <?php foreach ($assigned_patients as $p): ?>
                        <option value="<?php echo $p['id']; ?>" <?php if(isset($_GET['med_patient_id']) && $_GET['med_patient_id']==$p['id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($p['username']); ?> (ID: <?php echo $p['id']; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <?php
            $selected_med_patient = isset($_GET['med_patient_id']) ? intval($_GET['med_patient_id']) : null;
            if ($selected_med_patient) {
                $sql = "SELECT h.*, u.username FROM health_logs h JOIN patients p ON h.patient_id = p.id JOIN users u ON p.user_id = u.id WHERE h.log_type = 'medication' AND h.patient_id = ? ORDER BY h.log_time DESC LIMIT 20";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $selected_med_patient);
                $stmt->execute();
                $res = $stmt->get_result();
                $rows = [];
                while ($row = $res->fetch_assoc()) {
                    $details = json_decode($row['details'], true);
                    $rows[] = [
                        'log_time' => $row['log_time'],
                        'medication' => $details['medication'] ?? '',
                        'dosage' => $details['dosage'] ?? '',
                        'notes' => $details['notes'] ?? ''
                    ];
                }
                $stmt->close();
                if (count($rows) > 0) {
                    echo '<table><thead><tr><th>Date/Time</th><th>Medication</th><th>Dosage</th><th>Notes</th></tr></thead><tbody>';
                    foreach ($rows as $r) {
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($r['log_time']) . '</td>';
                        echo '<td>' . htmlspecialchars($r['medication']) . '</td>';
                        echo '<td>' . htmlspecialchars($r['dosage']) . '</td>';
                        echo '<td>' . htmlspecialchars($r['notes']) . '</td>';
                        echo '</tr>';
                    }
                    echo '</tbody></table>';
                } else {
                    // Show medication plan from treatments/medication_treatments
                    $sql = "SELECT mt.medication_type, mt.dosage, mt.schedule FROM treatments t JOIN medication_treatments mt ON t.id = mt.treatment_id WHERE t.patient_id = ? AND (mt.status = 'active' OR mt.status IS NULL) ORDER BY mt.start_date DESC, mt.created_at DESC LIMIT 5";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("i", $selected_med_patient);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    echo '<table><thead><tr><th>Medication</th><th>Dosage</th><th>Schedule</th></tr></thead><tbody>';
                    $plan_found = false;
                    while ($row = $res->fetch_assoc()) {
                        $plan_found = true;
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($row['medication_type']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['dosage']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['schedule']) . '</td>';
                        echo '</tr>';
                    }
                    if (!$plan_found) {
                        echo '<tr><td colspan="3">No medication plan found for this patient.</td></tr>';
                    }
                    echo '</tbody></table>';
                    $stmt->close();
                }
            } else {
                echo '<div class="alert alert-info">Please select a patient to view medication records.</div>';
            }
            ?>
        </div>
        <div class="section" id="record-vitals-section">
            <div class="section-header">
                <h2 class="section-title">Record Vitals / Medications / Activities</h2>
            </div>
            <form method="POST">
                <div class="form-group">
                    <label for="patient_id">Patient</label>
                    <select class="form-control" id="patient_id" name="patient_id" required>
                        <option value="">Select Patient</option>
                        <?php foreach ($assigned_patients as $p): ?>
                            <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['username']); ?> (ID: <?php echo $p['id']; ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Temperature (°C)</label>
                    <input type="number" step="0.1" class="form-control" name="temperature" required>
                </div>
                <div class="form-group">
                    <label>Pulse (Heart Rate)</label>
                    <input type="number" class="form-control" name="pulse" required>
                </div>
                <div class="form-group">
                    <label>Respiratory Rate (RR)</label>
                    <input type="number" class="form-control" name="rr" required>
                </div>
                <div class="form-group">
                    <label>Blood Pressure (BP)</label>
                    <input type="text" class="form-control" name="bp" required>
                </div>
                <div class="form-group">
                    <label>Mood</label>
                    <select class="form-control" name="mood" required>
                        <option value="">Select Mood</option>
                        <option value="anxious">Anxious</option>
                        <option value="calm">Calm</option>
                        <option value="agitated">Agitated</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Date</label>
                    <input type="date" class="form-control" name="record_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="form-group">
                    <label>Time</label>
                    <input type="time" class="form-control" name="record_time" value="<?php echo date('H:i'); ?>" required>
                </div>
                <button type="submit" class="btn btn-primary">Submit Vitals</button>
            </form>
        </div>
        <div class="section" id="activities-section">
            <div class="section-header">
                <h2 class="section-title">Activities</h2>
            </div>
            <form method="get" class="mb-3" id="activity-form">
                <label for="activity_patient_id">Select Patient:</label>
                <select class="form-control" id="activity_patient_id" name="activity_patient_id" style="max-width:300px;display:inline-block;">
                    <option value="">-- Choose Patient --</option>
                    <?php foreach ($assigned_patients as $p): ?>
                        <option value="<?php echo $p['id']; ?>" <?php if(isset($_GET['activity_patient_id']) && $_GET['activity_patient_id']==$p['id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($p['username']); ?> (ID: <?php echo $p['id']; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary" style="margin-left:10px;">View Activities</button>
            </form>
            <script>
            // Remove previous JS for auto-reload on change
            </script>
            <?php
            $selected_activity_patient = isset($_GET['activity_patient_id']) ? intval($_GET['activity_patient_id']) : null;
            if ($selected_activity_patient) {
                $sql = "SELECT tt.therapy_type, tt.approach FROM treatments t JOIN therapy_treatments tt ON t.id = tt.treatment_id WHERE t.patient_id = ? ORDER BY tt.session_date DESC, tt.created_at DESC LIMIT 20";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $selected_activity_patient);
                $stmt->execute();
                $res = $stmt->get_result();
                $found = false;
                echo '<table><thead><tr><th>Type</th><th>Approach</th></tr></thead><tbody>';
                while ($row = $res->fetch_assoc()) {
                    $found = true;
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars($row['therapy_type']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['approach']) . '</td>';
                    echo '</tr>';
                }
                if (!$found) {
                    echo '<tr><td colspan="2">No psychotherapy/counseling activities found for this patient.</td></tr>';
                }
                echo '</tbody></table>';
                $stmt->close();
            } else {
                echo '<div class="alert alert-info">Please select a patient to view activities.</div>';
            }
            ?>
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
            // Sidebar navigation scroll
            document.getElementById('assigned-patients-link').onclick = function(e) {
                e.preventDefault();
                document.getElementById('assigned-patients-section').scrollIntoView({ behavior: 'smooth' });
            };
            document.getElementById('medications-link').onclick = function(e) {
                e.preventDefault();
                document.getElementById('medications-section').scrollIntoView({ behavior: 'smooth' });
            };
            document.getElementById('activities-link').onclick = function(e) {
                e.preventDefault();
                document.getElementById('activities-section').scrollIntoView({ behavior: 'smooth' });
            };
        });
    </script>
</body>
</html> 