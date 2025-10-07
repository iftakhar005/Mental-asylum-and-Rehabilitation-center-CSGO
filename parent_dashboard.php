<?php


session_start();
// Prevent browser from caching authenticated pages
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
require_once 'db.php';

// --- Raw input validation and escaping functions ---
function sanitize_input($input, $maxlen = 255) {
    $input = preg_replace('/<[^>]*>/', '', $input); // Remove HTML tags
    $input = preg_replace('/[\\\'\";`]/', '', $input); // Remove dangerous chars
    $input = substr(trim($input), 0, $maxlen);
    return $input;
}

// Add SQL injection pattern blocking function
function block_sql_injection($input) {
    $patterns = [
        '/\b(SELECT|UNION|INSERT|UPDATE|DELETE|DROP|ALTER|TRUNCATE|RENAME|REPLACE|OR|AND)\b/i',
        '/--/',
        '/;/',
        '/\*/',
        '/\b(WHERE|LIKE|HAVING|GROUP BY|ORDER BY)\b/i'
    ];
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $input)) {
            die("Potential SQL injection detected.");
        }
    }
    return $input;
}
function escape_html($string) {
    $replacements = [
        '&' => '&amp;',
        '<' => '&lt;',
        '>' => '&gt;',
        '"' => '&quot;',
        "'" => '&#039;',
    ];
    return strtr($string, $replacements);
}

// Add input length restrictions and sanitization function
function sanitize_input_full($input, $max_length) {
    $input = trim($input); // Remove unnecessary whitespace
    $input = substr($input, 0, $max_length); // Restrict length
    $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8'); // Prevent XSS
    return $input;
}

// Check if user is logged in as relative (parent uses relative credentials)
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'relative') {
    header('Location: index.php');
    exit();
}


$user_id = isset($_SESSION['user_id']) ? sanitize_input($_SESSION['user_id'], 20) : '';
block_sql_injection($user_id);

// Fetch patient info
$stmt = $conn->prepare("SELECT p.*, u.email, u.username FROM patients p JOIN users u ON p.user_id = u.id WHERE u.id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$patient_query = $stmt->get_result();
$patient = $patient_query && $patient_query->num_rows > 0 ? $patient_query->fetch_assoc() : null;
$stmt->close();

if ($patient) {
    // Sanitize all patient fields
    foreach ($patient as $k => $v) {
        $patient[$k] = sanitize_input($v, 255);
        block_sql_injection($patient[$k]);
    }
}

// Fetch assigned doctor and therapist (from latest assessment)
$stmt = $conn->prepare("SELECT pa.assigned_doctor, d.full_name AS doctor_name, pa.assigned_therapist, t.full_name AS therapist_name FROM patient_assessments pa 
    LEFT JOIN staff d ON pa.assigned_doctor = d.staff_id 
    LEFT JOIN staff t ON pa.assigned_therapist = t.staff_id 
    WHERE pa.patient_id = ? 
    ORDER BY pa.assessment_date DESC, pa.created_at DESC LIMIT 1");
$stmt->bind_param("i", $patient['patient_id']);
$stmt->execute();
$assessment = $stmt->get_result();
if ($assessment && $assessment->num_rows > 0) {
    $row = $assessment->fetch_assoc();
    $doctor_name = $row['doctor_name'] ?: 'N/A';
    $therapist_name = $row['therapist_name'] ?: 'N/A';
}
$stmt->close();

// Fetch all appointments for this patient (not just future ones)
$stmt = $conn->prepare("SELECT * FROM appointments WHERE patient_id = ? ORDER BY date DESC, time DESC");
$stmt->bind_param("i", $patient['patient_id']);
$stmt->execute();
$apt_result = $stmt->get_result();
$appointments = $apt_result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
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
                <h2><?php echo escape_html($patient['full_name']); ?></h2>
                <div class="patient-id">Patient ID: <?php echo escape_html($patient['patient_id']); ?></div>
                <div class="status">Status: <?php echo escape_html($patient['status']); ?></div>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#patientDetailsModal" style="margin-top:12px;">View Full Details</button>
            </div>
        </div>
        <div class="info-cards">
            <div class="info-card">
                <div class="label"><i class="fas fa-door-open"></i> Room</div>
                <div class="value"><?php echo escape_html($patient['room_number']); ?></div>
            </div>
            <div class="info-card">
                <div class="label"><i class="fas fa-envelope"></i> Email</div>
                <div class="value"><?php echo escape_html($patient['email']); ?></div>
            </div>
            <div class="info-card">
                <div class="label"><i class="fas fa-user-tag"></i> Type</div>
                <div class="value"><?php echo escape_html($patient['type']); ?></div>
            </div>
        </div>
        <div class="doctor-therapist">
            <div class="dt-card">
                <div class="label"><i class="fas fa-user-md"></i> Assigned Doctor</div>
                <div class="value"><?php echo escape_html($doctor_name); ?></div>
            </div>
            <div class="dt-card">
                <div class="label"><i class="fas fa-user-nurse"></i> Assigned Therapist</div>
                <div class="value"><?php echo escape_html($therapist_name); ?></div>
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
                        <div><strong>Date:</strong> <?php echo escape_html($apt['date']); ?> <strong>Time:</strong> <?php echo escape_html($apt['time']); ?></div>
                        <div><strong>Type:</strong> <?php echo escape_html($apt['type']); ?></div>
                        <?php if (!empty($apt['doctor'])): ?><div><strong>Doctor:</strong> <?php echo escape_html($apt['doctor']); ?></div><?php endif; ?>
                        <?php if (!empty($apt['therapist'])): ?><div><strong>Therapist:</strong> <?php echo escape_html($apt['therapist']); ?></div><?php endif; ?>
                        <div><strong>Status:</strong> <?php echo escape_html($apt['status']); ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
                <div style="color:#b85c00;">No appointments found for Patient ID: <?php echo escape_html($patient['patient_id']); ?></div>
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
        <!-- Patient Details Modal -->
        <div class="modal fade" id="patientDetailsModal" tabindex="-1" aria-labelledby="patientDetailsModalLabel" aria-hidden="true">
          <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="patientDetailsModalLabel">Patient Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <h6>Basic Information</h6>
                <ul>
                  <li><strong>Full Name:</strong> <?php echo htmlspecialchars($patient['full_name']); ?></li>
                  <li><strong>Patient ID:</strong> <?php echo htmlspecialchars($patient['patient_id']); ?></li>
                  <li><strong>Email:</strong> <?php echo htmlspecialchars($patient['email']); ?></li>
                  <li><strong>Phone:</strong> <?php echo htmlspecialchars($patient['phone']); ?></li>
                  <li><strong>Room Number:</strong> <?php echo htmlspecialchars($patient['room_number']); ?></li>
                  <li><strong>Status:</strong> <?php echo htmlspecialchars($patient['status']); ?></li>
                </ul>
                <hr>
                <h6>Medical Information</h6>
                <ul>
                  <li><strong>Allergies:</strong> <?php echo htmlspecialchars($patient['allergies']); ?></li>
                  <li><strong>Medications:</strong> <?php echo htmlspecialchars($patient['medications']); ?></li>
                  <li><strong>Conditions:</strong> <?php echo htmlspecialchars($patient['conditions']); ?></li>
                </ul>
                <hr>
                <h6>Assigned Staff</h6>
                <ul>
                  <li><strong>Doctor:</strong> <?php echo htmlspecialchars($doctor_name); ?></li>
                  <li><strong>Therapist:</strong> <?php echo htmlspecialchars($therapist_name); ?></li>
                </ul>
              </div>
            </div>
          </div>
        </div>
        <?php else: ?>
        <div style="color:#b85c00;">No patient information found.</div>
        <?php endif; ?>
    </div>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/2.9.2/umd/popper.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.min.js"></script>
    <script>
        // Smooth scrolling for internal links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();

                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });

        // Toggle active class on sidebar menu items
        const sidebarLinks = document.querySelectorAll('.sidebar-menu a, .sidebar-menu .sidebar-btn');
        sidebarLinks.forEach(link => {
            link.addEventListener('click', () => {
                sidebarLinks.forEach(i => i.classList.remove('active'));
                link.classList.add('active');
            });
        });

        // Show appointments in modal
        document.getElementById('appointments-btn').addEventListener('click', () => {
            const modalBody = document.querySelector('#appointmentsModal .modal-body');
            modalBody.innerHTML = '<div class="text-center" style="padding: 40px 0;">Loading appointments...</div>';
            $('#appointmentsModal').modal('show');
            fetch('fetch_appointments.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ patient_id: <?php echo $patient['patient_id']; ?> })
            })
            .then(response => response.json())
            .then(data => {
                modalBody.innerHTML = '';
                if (data.success && data.appointments.length > 0) {
                    const timeline = document.createElement('div');
                    timeline.classList.add('timeline');
                    data.appointments.forEach(apt => {
                        const eventDiv = document.createElement('div');
                        eventDiv.classList.add('timeline-event');
                        eventDiv.innerHTML = `
                            <div class="timeline-dot"></div>
                            <div class="timeline-content">
                                <div><strong>Date:</strong> ${apt.date} <strong>Time:</strong> ${apt.time}</div>
                                <div><strong>Type:</strong> ${apt.type}</div>
                                ${apt.doctor ? `<div><strong>Doctor:</strong> ${apt.doctor}</div>` : ''}
                                ${apt.therapist ? `<div><strong>Therapist:</strong> ${apt.therapist}</div>` : ''}
                                <div><strong>Status:</strong> ${apt.status}</div>
                            </div>
                        `;
                        timeline.appendChild(eventDiv);
                    });
                    modalBody.appendChild(timeline);
                } else {
                    modalBody.innerHTML = '<div style="color:#b85c00;">No appointments found for this patient.</div>';
                }
            })
            .catch(error => {
                modalBody.innerHTML = '<div style="color:#b85c00;">Error loading appointments. Please try again later.</div>';
                console.error('Error fetching appointments:', error);
            });
        });

        // Patient info link click
        document.getElementById('patient-info-link').addEventListener('click', (e) => {
            e.preventDefault();
            const profileSection = document.getElementById('profile-section');
            const appointmentsSection = document.getElementById('appointments-section');
            profileSection.scrollIntoView({ behavior: 'smooth' });
        });
    </script>
</body>
</html>