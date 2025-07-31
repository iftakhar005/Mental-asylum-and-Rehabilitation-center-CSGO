<?php
session_start();
require_once 'db.php';

// Check if user is logged in as relative (parent uses relative credentials)
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'relative') {
    header('Location: index.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch patient info
$patient_query = $conn->query("SELECT p.*, u.email, u.username FROM patients p JOIN users u ON p.user_id = u.id WHERE u.id = $user_id LIMIT 1");
$patient = $patient_query && $patient_query->num_rows > 0 ? $patient_query->fetch_assoc() : null;

// Fetch assigned doctor and therapist (from latest assessment)
$doctor_name = 'N/A';
$therapist_name = 'N/A';
$assessment = $conn->query("SELECT pa.assigned_doctor, d.full_name AS doctor_name, pa.assigned_therapist, t.full_name AS therapist_name FROM patient_assessments pa 
    LEFT JOIN staff d ON pa.assigned_doctor = d.staff_id 
    LEFT JOIN staff t ON pa.assigned_therapist = t.staff_id 
    WHERE pa.patient_id = '{$patient['patient_id']}' 
    ORDER BY pa.assessment_date DESC, pa.created_at DESC LIMIT 1");
if ($assessment && $assessment->num_rows > 0) {
    $row = $assessment->fetch_assoc();
    $doctor_name = $row['doctor_name'] ?: 'N/A';
    $therapist_name = $row['therapist_name'] ?: 'N/A';
}

// Fetch all appointments for this patient (not just future ones)
$appointments = [];
$apt_result = $conn->query("SELECT * FROM appointments WHERE patient_id = '{$patient['patient_id']}' ORDER BY date DESC, time DESC");
if ($apt_result) {
    $appointments = $apt_result->fetch_all(MYSQLI_ASSOC);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parent Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f4f6fb; margin: 0; }
        .sidebar {
            position: fixed;
            top: 0; left: 0; bottom: 0;
            width: 220px;
            background: #3f37c9;
            color: #fff;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding-top: 40px;
            z-index: 100;
            box-shadow: 2px 0 10px rgba(67,97,238,0.07);
        }
        .sidebar .sidebar-title {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 32px;
            letter-spacing: 1px;
        }
        .sidebar-menu {
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .sidebar-menu a, .sidebar-menu .sidebar-btn {
            color: #fff;
            text-decoration: none;
            padding: 12px 32px;
            font-size: 1.08rem;
            border-radius: 8px 0 0 8px;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: background 0.18s;
            background: none;
            border: none;
            cursor: pointer;
        }
        .sidebar-menu a.active, .sidebar-menu a:hover, .sidebar-menu .sidebar-btn.active, .sidebar-menu .sidebar-btn:hover {
            background: #4361ee;
        }
        .sidebar-menu .sidebar-btn:focus {
            outline: none;
            background: #4361ee;
        }
        .sidebar-menu a.logout {
            margin-top: 32px;
            background: #fff;
            color: #3f37c9;
            font-weight: 600;
            border-radius: 8px;
            transition: background 0.18s, color 0.18s;
        }
        .sidebar-menu a.logout:hover {
            background: #e0e7ff;
            color: #222;
        }
        @media (max-width: 900px) {
            .sidebar { width: 60px; padding-top: 18px; }
            .sidebar .sidebar-title { display: none; }
            .sidebar-menu a { padding: 12px 10px; font-size: 1.1rem; justify-content: center; gap: 0; }
            .sidebar-menu a span { display: none; }
        }
        .topbar {
            background: #4361ee;
            color: #fff;
            padding: 18px 0 18px 0;
            box-shadow: 0 2px 8px rgba(67,97,238,0.07);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-left: 40px;
            padding-right: 40px;
            margin-left: 220px;
        }
        @media (max-width: 900px) {
            .topbar { margin-left: 60px; padding-left: 16px; padding-right: 16px; }
        }
        .container {
            max-width: 800px;
            margin: 40px auto;
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 4px 24px rgba(67,97,238,0.09);
            padding: 36px 32px 32px 32px;
            margin-left: 240px;
        }
        @media (max-width: 900px) {
            .container { margin-left: 70px; padding: 18px 4vw; }
        }
        .topbar .welcome { font-size: 1.2rem; font-weight: 600; }
        .topbar .logout-btn {
            background: #fff;
            color: #4361ee;
            border: none;
            border-radius: 6px;
            padding: 8px 18px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: background 0.2s;
        }
        .topbar .logout-btn:hover { background: #e9ecef; }
        .profile-section {
            display: flex;
            align-items: center;
            gap: 24px;
            margin-bottom: 32px;
        }
        .avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            color: #4361ee;
            font-weight: 700;
            box-shadow: 0 2px 8px rgba(67,97,238,0.08);
        }
        .profile-info {
            flex: 1;
        }
        .profile-info h2 {
            margin: 0 0 6px 0;
            font-size: 1.6rem;
            color: #222;
        }
        .profile-info .patient-id {
            color: #7b809a;
            font-size: 1rem;
            margin-bottom: 4px;
        }
        .profile-info .status {
            display: inline-block;
            background: #e0e7ff;
            color: #3f37c9;
            border-radius: 5px;
            padding: 2px 10px;
            font-size: 0.95rem;
            font-weight: 600;
            margin-top: 4px;
        }
        .info-cards {
            display: flex;
            gap: 24px;
            flex-wrap: wrap;
            margin-bottom: 32px;
        }
        .info-card {
            flex: 1 1 220px;
            background: #f7f9fc;
            border-radius: 12px;
            padding: 18px 20px;
            box-shadow: 0 2px 8px rgba(67,97,238,0.04);
            margin-bottom: 0;
        }
        .info-card .label {
            color: #7b809a;
            font-size: 0.98rem;
            margin-bottom: 2px;
        }
        .info-card .value {
            font-size: 1.1rem;
            font-weight: 600;
            color: #222;
        }
        .doctor-therapist {
            display: flex;
            gap: 24px;
            margin-bottom: 32px;
        }
        .dt-card {
            flex: 1 1 220px;
            background: #e0e7ff;
            border-radius: 12px;
            padding: 18px 20px;
            box-shadow: 0 2px 8px rgba(67,97,238,0.04);
        }
        .dt-card .label {
            color: #3f37c9;
            font-size: 1rem;
            margin-bottom: 2px;
        }
        .dt-card .value {
            font-size: 1.1rem;
            font-weight: 600;
            color: #222;
        }
        .appointments-section {
            margin-top: 18px;
        }
        .appointments-section h2 {
            color: #4361ee;
            margin-bottom: 16px;
            font-size: 1.25rem;
        }
        .timeline {
            border-left: 3px solid #4361ee;
            margin-left: 18px;
            padding-left: 24px;
        }
        .timeline-event {
            position: relative;
            margin-bottom: 28px;
        }
        .timeline-event:last-child { margin-bottom: 0; }
        .timeline-dot {
            position: absolute;
            left: -32px;
            top: 2px;
            width: 18px;
            height: 18px;
            background: #4361ee;
            border-radius: 50%;
            border: 3px solid #fff;
            box-shadow: 0 2px 8px rgba(67,97,238,0.09);
        }
        .timeline-content {
            background: #f7f9fc;
            border-radius: 10px;
            padding: 14px 18px;
            box-shadow: 0 2px 8px rgba(67,97,238,0.04);
        }
        .timeline-content strong { color: #4361ee; }
        @media (max-width: 700px) {
            .container { padding: 18px 4vw; }
            .profile-section { flex-direction: column; align-items: flex-start; gap: 12px; }
            .info-cards, .doctor-therapist { flex-direction: column; gap: 12px; }
        }
        /* Custom modal box styling */
        .modal-content {
            border-radius: 18px !important;
            box-shadow: 0 8px 32px rgba(67,97,238,0.18) !important;
            padding: 10px 0 18px 0;
        }
        .modal-header {
            background: #4361ee;
            color: #fff;
            border-top-left-radius: 18px;
            border-top-right-radius: 18px;
            padding: 18px 24px;
        }
        .modal-title { font-size: 1.3rem; font-weight: 700; }
        .modal-body {
            padding: 24px 32px 18px 32px;
            font-size: 1.08rem;
        }
        .modal-body h6 { color: #3f37c9; margin-top: 18px; margin-bottom: 8px; font-size: 1.08rem; }
        .modal-body ul { margin-bottom: 0; }
        .modal-body hr { margin: 18px 0; }
        @media (max-width: 700px) {
            .modal-body { padding: 12px 6vw 8px 6vw; }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-title"><i class="fas fa-user-friends"></i> Parent Portal</div>
        <div class="sidebar-menu">
            <a href="#" class="active"><i class="fas fa-home"></i> <span>Dashboard</span></a>
            <button id="appointments-btn" type="button" class="sidebar-btn"><i class="fas fa-calendar-alt"></i> <span>Appointments</span></button>
            <a href="#" id="patient-info-link"><i class="fas fa-user"></i> <span>Patient Info</span></a>
            <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
        </div>
    </div>
    <div class="topbar">
        <div class="welcome">Welcome, Parent</div>
        <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
    <div class="container">
        <h1 style="color:#4361ee;margin-bottom:18px;"><i class="fas fa-user-friends"></i> Parent Dashboard</h1>
        <?php if ($patient): ?>
        <div class="profile-section" id="profile-section">
            <h2 style="color:#3f37c9;margin-bottom:18px;">Patient Information</h2>
            <div class="avatar">
                <i class="fas fa-user"></i>
            </div>
            <div class="profile-info">
                <h2><?php echo htmlspecialchars($patient['full_name']); ?></h2>
                <div class="patient-id">Patient ID: <?php echo htmlspecialchars($patient['patient_id']); ?></div>
                <div class="status">Status: <?php echo htmlspecialchars($patient['status']); ?></div>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#patientDetailsModal" style="margin-top:12px;">View Full Details</button>
            </div>
        </div>
        <div class="info-cards">
            <div class="info-card">
                <div class="label"><i class="fas fa-door-open"></i> Room</div>
                <div class="value"><?php echo htmlspecialchars($patient['room_number']); ?></div>
            </div>
            <div class="info-card">
                <div class="label"><i class="fas fa-envelope"></i> Email</div>
                <div class="value"><?php echo htmlspecialchars($patient['email']); ?></div>
            </div>
            <div class="info-card">
                <div class="label"><i class="fas fa-user-tag"></i> Type</div>
                <div class="value"><?php echo htmlspecialchars($patient['type']); ?></div>
            </div>
        </div>
        <div class="doctor-therapist">
            <div class="dt-card">
                <div class="label"><i class="fas fa-user-md"></i> Assigned Doctor</div>
                <div class="value"><?php echo htmlspecialchars($doctor_name); ?></div>
            </div>
            <div class="dt-card">
                <div class="label"><i class="fas fa-user-nurse"></i> Assigned Therapist</div>
                <div class="value"><?php echo htmlspecialchars($therapist_name); ?></div>
            </div>
        </div>
        <div class="appointments-section" id="appointments-section">
            <h2><i class="fas fa-calendar-alt"></i> Appointments</h2>
            <?php if (count($appointments) > 0): ?>
            <div class="timeline">
                <?php foreach ($appointments as $apt): ?>
                <div class="timeline-event">
                    <div class="timeline-dot"></div>
                    <div class="timeline-content">
                        <div><strong>Date:</strong> <?php echo htmlspecialchars($apt['date']); ?> <strong>Time:</strong> <?php echo htmlspecialchars($apt['time']); ?></div>
                        <div><strong>Type:</strong> <?php echo htmlspecialchars($apt['type']); ?></div>
                        <?php if (!empty($apt['doctor'])): ?><div><strong>Doctor:</strong> <?php echo htmlspecialchars($apt['doctor']); ?></div><?php endif; ?>
                        <?php if (!empty($apt['therapist'])): ?><div><strong>Therapist:</strong> <?php echo htmlspecialchars($apt['therapist']); ?></div><?php endif; ?>
                        <div><strong>Status:</strong> <?php echo htmlspecialchars($apt['status']); ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
                <div style="color:#b85c00;">No appointments found for Patient ID: <?php echo htmlspecialchars($patient['patient_id']); ?></div>
            <?php endif; ?>
        </div>
        <!-- Appointments Modal -->
        <div class="modal fade" id="appointmentsModal" tabindex="-1" aria-labelledby="appointmentsModalLabel" aria-hidden="true">
          <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="appointmentsModalLabel">Appointments</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <?php if (count($appointments) > 0): ?>
                <div class="timeline">
                    <?php foreach ($appointments as $apt): ?>
                    <div class="timeline-event">
                        <div class="timeline-dot"></div>
                        <div class="timeline-content">
                            <div><strong>Date:</strong> <?php echo htmlspecialchars($apt['date']); ?> <strong>Time:</strong> <?php echo htmlspecialchars($apt['time']); ?></div>
                            <div><strong>Type:</strong> <?php echo htmlspecialchars($apt['type']); ?></div>
                            <?php if (!empty($apt['doctor'])): ?><div><strong>Doctor:</strong> <?php echo htmlspecialchars($apt['doctor']); ?></div><?php endif; ?>
                            <?php if (!empty($apt['therapist'])): ?><div><strong>Therapist:</strong> <?php echo htmlspecialchars($apt['therapist']); ?></div><?php endif; ?>
                            <div><strong>Status:</strong> <?php echo htmlspecialchars($apt['status']); ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                    <div style="color:#b85c00;">No appointments found for Patient ID: <?php echo htmlspecialchars($patient['patient_id']); ?></div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
        <!-- Modal -->
        <div class="modal fade" id="patientDetailsModal" tabindex="-1" aria-labelledby="patientDetailsModalLabel" aria-hidden="true">
          <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="patientDetailsModalLabel">Full Patient Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <h6>Basic Information</h6>
                <ul>
                  <li><strong>Name:</strong> <?php echo htmlspecialchars($patient['full_name']); ?></li>
                  <li><strong>Patient ID:</strong> <?php echo htmlspecialchars($patient['patient_id']); ?></li>
                  <li><strong>Status:</strong> <?php echo htmlspecialchars($patient['status']); ?></li>
                  <li><strong>Room:</strong> <?php echo htmlspecialchars($patient['room_number']); ?></li>
                  <li><strong>Email:</strong> <?php echo htmlspecialchars($patient['email']); ?></li>
                  <li><strong>Type:</strong> <?php echo htmlspecialchars($patient['type']); ?></li>
                  <li><strong>Assigned Doctor:</strong> <?php echo htmlspecialchars($doctor_name); ?></li>
                  <li><strong>Assigned Therapist:</strong> <?php echo htmlspecialchars($therapist_name); ?></li>
                </ul>
                <hr>
                <h6>Medication Plan</h6>
                <?php
                $meds = [];
                $sql = "SELECT mt.medication_type, mt.dosage, mt.schedule FROM treatments t JOIN medication_treatments mt ON t.id = mt.treatment_id WHERE t.patient_id = ? AND (mt.status = 'active' OR mt.status IS NULL) ORDER BY mt.start_date DESC, mt.created_at DESC LIMIT 5";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $patient['id']);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) $meds[] = $row;
                $stmt->close();
                if (count($meds) > 0) {
                  echo '<ul>';
                  foreach ($meds as $m) {
                    echo '<li><strong>' . htmlspecialchars($m['medication_type']) . ':</strong> ' . htmlspecialchars($m['dosage']) . ' (' . htmlspecialchars($m['schedule']) . ')</li>';
                  }
                  echo '</ul>';
                } else {
                  echo '<div>No medication plan found.</div>';
                }
                ?>
                <hr>
                <h6>Activities Plan</h6>
                <?php
                $activities = [];
                $sql = "SELECT tt.therapy_type, tt.approach, tt.session_date FROM treatments t JOIN therapy_treatments tt ON t.id = tt.treatment_id WHERE t.patient_id = ? ORDER BY tt.session_date DESC, tt.created_at DESC LIMIT 5";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $patient['id']);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) $activities[] = $row;
                $stmt->close();
                if (count($activities) > 0) {
                  echo '<ul>';
                  foreach ($activities as $a) {
                    echo '<li><strong>' . htmlspecialchars($a['therapy_type']) . ':</strong> ' . htmlspecialchars($a['approach']) . ' (Session: ' . htmlspecialchars($a['session_date']) . ')</li>';
                  }
                  echo '</ul>';
                } else {
                  echo '<div>No activities plan found.</div>';
                }
                ?>
                <hr>
                <h6>Treatment Plan</h6>
                <?php
                $treatments = [];
                $sql = "SELECT treatment_type, status, created_at FROM treatments WHERE patient_id = ? ORDER BY created_at DESC, id DESC LIMIT 5";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $patient['id']);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) $treatments[] = $row;
                $stmt->close();
                if (count($treatments) > 0) {
                  echo '<ul>';
                  foreach ($treatments as $t) {
                    echo '<li><strong>' . htmlspecialchars($t['treatment_type']) . ':</strong> Status: ' . htmlspecialchars($t['status']) . ' (Created: ' . htmlspecialchars($t['created_at']) . ')</li>';
                  }
                  echo '</ul>';
                } else {
                  echo '<div>No treatment plan found.</div>';
                }
                ?>
              </div>
            </div>
          </div>
        </div>
        <!-- Bootstrap 5 JS and CSS -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
        <?php else: ?>
            <div style="color:red;">Patient information not found.</div>
        <?php endif; ?>
    </div>
    <script>
    // Sidebar: open modal for Patient Info
    document.getElementById('patient-info-link').addEventListener('click', function(e) {
        e.preventDefault();
        var modal = new bootstrap.Modal(document.getElementById('patientDetailsModal'));
        modal.show();
    });
    // Sidebar: open modal for Appointments
    document.getElementById('appointments-btn').addEventListener('click', function(e) {
        e.preventDefault();
        var modal = new bootstrap.Modal(document.getElementById('appointmentsModal'));
        modal.show();
    });
    // Smooth scroll for other sidebar anchor links
    document.querySelectorAll('.sidebar-menu a[href^="#"]:not(#patient-info-link)').forEach(link => {
        link.addEventListener('click', function(e) {
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                e.preventDefault();
                target.scrollIntoView({ behavior: 'smooth' });
            }
        });
    });
    </script>
</body>
</html> 