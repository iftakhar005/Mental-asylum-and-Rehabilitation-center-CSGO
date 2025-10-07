
<?php
// Prevent browser from caching authenticated pages
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
session_start();
// Enforce session/role check for receptionist
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'receptionist') {
    header('Location: index.php');
    exit();
}
require_once 'session_check.php';
check_login(['receptionist']);
require_once 'db.php';

// Fetch available rooms for patients
$rooms_result = $conn->query("SELECT room_number FROM rooms WHERE status = 'available' AND for_whom = 'Patients' ORDER BY room_number ASC");
$available_rooms = $rooms_result ? $rooms_result->fetch_all(MYSQLI_ASSOC) : [];
$receptionist_name = htmlspecialchars($_SESSION['username'] ?? 'Receptionist');
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
    <title>Receptionist Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #667eea;
            --secondary: #764ba2;
            --success: #10b981;
            --danger: #ef4444;
            --bg: #f7f8fa;
            --bg-card: #fff;
            --text: #23263a;
            --text-muted: #7b809a;
            --border: #e0e6ed;
            --sidebar-width: 220px;
        }
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
        }
        body {
            background: var(--bg);
            color: var(--text);
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
        }
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: #fff;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding-top: 32px;
            position: fixed;
            left: 0;
            top: 0;
            z-index: 100;
        }
        .sidebar-logo {
            margin-bottom: 32px;
        }
        .sidebar-logo img {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: #fff;
            padding: 6px;
        }
        .sidebar-menu {
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 18px;
        }
        .sidebar-menu a {
            color: #fff;
            text-decoration: none;
            font-weight: 500;
            padding: 12px 32px;
            border-radius: 8px 0 0 8px;
            display: flex;
            align-items: center;
            gap: 14px;
            transition: background 0.18s;
        }
        .sidebar-menu a.active, .sidebar-menu a:hover {
            background: rgba(255,255,255,0.13);
        }
        .sidebar-spacer {
            flex: 1;
        }
        .sidebar-logout {
            margin-bottom: 32px;
        }
        .sidebar-logout a {
            color: #fff;
            text-decoration: none;
            font-weight: 500;
            padding: 10px 32px;
            border-radius: 8px 0 0 8px;
            display: flex;
            align-items: center;
            gap: 12px;
            background: rgba(239,68,68,0.13);
            transition: background 0.18s;
        }
        .sidebar-logout a:hover {
            background: rgba(239,68,68,0.23);
        }
        .main {
            margin-left: var(--sidebar-width);
            width: 100%;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .header {
            height: 64px;
            background: #fff;
            box-shadow: 0 2px 8px rgba(67,97,238,0.06);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 36px;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .header-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .header-logo {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            background: #f7f8fa;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .header-logo img {
            width: 32px;
            height: 32px;
        }
        .header-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--primary);
        }
        .header-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.1rem;
        }
        .user-name {
            font-weight: 600;
            color: var(--text);
        }
        .main-content {
            flex: 1;
            padding: 36px 24px 24px 24px;
            max-width: 1100px;
            margin: 0 auto;
            width: 100%;
        }
        .tabs {
            display: flex;
            gap: 16px;
            margin-bottom: 32px;
        }
        .tab-btn {
            padding: 12px 32px;
            border: none;
            border-radius: 10px 10px 0 0;
            background: #f7f8fa;
            color: var(--primary);
            font-weight: 600;
            font-size: 1.1rem;
            cursor: pointer;
            transition: background 0.18s, color 0.18s;
        }
        .tab-btn.active {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: #fff;
        }
        .tab-content {
            background: #fff;
            border-radius: 0 0 18px 18px;
            box-shadow: 0 4px 32px rgba(67,97,238,0.07);
            padding: 32px 28px 28px 28px;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text);
        }
        .form-control {
            width: 100%;
            padding: 0.85rem 1rem;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            background: var(--bg);
            color: var(--text);
            font-size: 1rem;
            transition: border-color 0.18s;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(102,126,234,0.10);
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 0.85rem 2rem;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: #fff;
            transition: background 0.2s, transform 0.2s;
        }
        .btn:hover {
            background: linear-gradient(135deg, var(--secondary), var(--primary));
            transform: translateY(-2px);
        }
        .alert {
            padding: 1rem 1.2rem;
            border-radius: 10px;
            margin-bottom: 1.2rem;
            font-weight: 500;
        }
        .alert-success {
            background: #eafaf1;
            color: var(--success);
            border: 1.5px solid #10b98144;
        }
        .alert-danger {
            background: #fff0f0;
            color: var(--danger);
            border: 1.5px solid #ef444444;
        }
        .credentials {
            background: #f7f8fa;
            border: 1.5px solid #e0e6ed;
            border-radius: 10px;
            padding: 1rem;
            margin-top: 1rem;
        }
        .patients-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        .patients-table th, .patients-table td {
            padding: 10px 14px;
            border-bottom: 1px solid #e0e6ed;
            text-align: left;
        }
        .patients-table th {
            background: #f7f8fa;
            font-weight: 600;
        }
        .patients-table tr:nth-child(even) {
            background: #fafbfc;
        }
        .patients-table tr:hover {
            background: #f0f4ff;
        }
        .modal-bg {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(24,26,32,0.15);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal-bg.active {
            display: flex;
        }
        .modal-content {
            background: var(--bg-card);
            border-radius: 18px;
            box-shadow: 0 8px 32px rgba(67,97,238,0.13);
            padding: 36px 32px 28px 32px;
            min-width: 320px;
            max-width: 95vw;
            text-align: center;
            border: 2px solid var(--primary);
            position: relative;
        }
        .close-modal {
            position: absolute;
            top: 18px;
            right: 28px;
            font-size: 2rem;
            color: var(--text-muted);
            background: none;
            border: none;
            cursor: pointer;
        }
        @media (max-width: 900px) {
            .main-content { padding: 16px 2vw; }
            .tab-content { padding: 18px 6px; }
        }
        @media (max-width: 700px) {
            .sidebar { display: none; }
            .main { margin-left: 0; }
            .header { padding: 0 12px; }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-logo">
            <i class="fas fa-hospital" style="font-size: 2rem; color: #fff;"></i>
        </div>
        <div class="sidebar-menu">
            <a href="#" class="active"><i class="fas fa-home"></i> Dashboard</a>
            <a href="#" id="tabAddBtn"><i class="fas fa-user-plus"></i> Add Patient</a>
            <a href="#" id="tabViewBtn"><i class="fas fa-users"></i> View Patients</a>
        </div>
        <div class="sidebar-spacer"></div>
        <div class="sidebar-logout">
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>
    <div class="main">
        <div class="header">
            <div class="header-left">
                <div class="header-logo">
                    <i class="fas fa-hospital" style="font-size: 1.5rem; color: var(--primary);"></i>
                </div>
                <span class="header-title">United Medical Asylum & Rehab Facility</span>
            </div>
            <div class="header-right">
                <span class="user-name"><?php echo $receptionist_name; ?></span>
                <div class="user-avatar">R</div>
            </div>
        </div>
        <div class="main-content">
            <div class="tabs">
                <button class="tab-btn active" id="tabAdd">Add Patient</button>
                <button class="tab-btn" id="tabView">View Patients</button>
            </div>
            <div class="tab-content" id="tabAddContent">
                <div class="page-header">
                    <h1 class="page-title">Add New Patient</h1>
                </div>
                <div id="alert"></div>
                <form id="addPatientForm" autocomplete="off" method="POST" action="">
                    <div class="form-group">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Date of Birth</label>
                        <input type="date" name="dob" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Gender</label>
                        <select name="gender" class="form-control" required>
                            <option value="">Select</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Admission Date</label>
                        <input type="date" name="admission_date" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Room Number</label>
                        <select name="room" class="form-control" required>
                            <option value="">Select Room</option>
                            <?php foreach ($available_rooms as $room): ?>
                                <option value="<?php echo htmlspecialchars($room['room_number']); ?>">
                                    Room <?php echo htmlspecialchars($room['room_number']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Emergency Contact</label>
                        <input type="text" name="emergency_contact" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Patient Type</label>
                        <select name="type" class="form-control" required>
                            <option value="">Select</option>
                            <option value="Asylum">Asylum</option>
                            <option value="Rehabilitation">Rehabilitation</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Mobility Status</label>
                        <select name="mobility_status" class="form-control" required>
                            <option value="">Select</option>
                            <option value="Independent">Independent</option>
                            <option value="Assisted">Assisted</option>
                            <option value="Wheelchair">Wheelchair</option>
                            <option value="Bedridden">Bedridden</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Medical History</label>
                        <textarea name="medical_history" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Current Medications</label>
                        <textarea name="current_medications" class="form-control" rows="2"></textarea>
                    </div>
                    <input type="hidden" name="status" value="admitted">
                    <button type="submit" class="btn"><i class="fas fa-user-plus"></i> Add Patient</button>
                </form>
                <div id="credentials" class="credentials" style="display:none;"></div>
            </div>
            <div class="tab-content" id="tabViewContent" style="display:none;">
                <div class="page-header">
                    <h1 class="page-title">Search & View Patients</h1>
                </div>
                <input type="text" id="patientSearch" class="form-control" placeholder="Search by name, room, or ID..." style="margin-bottom:1.2rem;max-width:350px;">
                <div style="overflow-x:auto;">
                    <table class="patients-table">
                        <thead>
                            <tr>
                                <th>Patient ID</th>
                                <th>Name</th>
                                <th>Room</th>
                                <th>Gender</th>
                                <th>Status</th>
                                <th>Admission Date</th>
                                <th>View</th>
                            </tr>
                        </thead>
                        <tbody id="patientsTableBody">
                            <tr><td colspan="7" style="text-align:center;">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <!-- Patient Details Modal -->
            <div id="patientModal" class="modal-bg">
                <div class="modal-content">
                    <button class="close-modal" onclick="closePatientModal()">&times;</button>
                    <div id="modalPatientContent"></div>
                </div>
            </div>
        </div>
    </div>
    <script>
    // Tab switching logic
    const tabAdd = document.getElementById('tabAdd');
    const tabView = document.getElementById('tabView');
    const tabAddContent = document.getElementById('tabAddContent');
    const tabViewContent = document.getElementById('tabViewContent');
    document.getElementById('tabAddBtn').onclick = () => { tabAdd.click(); };
    document.getElementById('tabViewBtn').onclick = () => { tabView.click(); };
    tabAdd.onclick = function() {
        tabAdd.classList.add('active');
        tabView.classList.remove('active');
        tabAddContent.style.display = '';
        tabViewContent.style.display = 'none';
    };
    tabView.onclick = function() {
        tabView.classList.add('active');
        tabAdd.classList.remove('active');
        tabViewContent.style.display = '';
        tabAddContent.style.display = 'none';
    };
    // Add Patient Form AJAX
    document.getElementById('addPatientForm').addEventListener('submit', function(e) {
        e.preventDefault();
        var form = e.target;
        var formData = new FormData(form);
        formData.append('action', 'add_patient');
        var alertBox = document.getElementById('alert');
        var credentialsBox = document.getElementById('credentials');
        alertBox.innerHTML = '';
        credentialsBox.style.display = 'none';
        fetch('patient_management.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alertBox.innerHTML = '<div class="alert alert-success">' + data.message + '</div>';
                credentialsBox.innerHTML = '<b>Patient ID:</b> ' + data.patient_id + '<br>' +
                    '<b>Username:</b> ' + data.username + '<br>' +
                    '<b>Temporary Password:</b> ' + data.password;
                credentialsBox.style.display = 'block';
                form.reset();
            } else {
                alertBox.innerHTML = '<div class="alert alert-danger">' + data.message + '</div>';
            }
        })
        .catch(() => {
            alertBox.innerHTML = '<div class="alert alert-danger">An error occurred. Please try again.</div>';
        });
    });
    // Patient search and view logic
    let patientsList = [];
    const patientsTableBody = document.getElementById('patientsTableBody');
    const patientSearch = document.getElementById('patientSearch');
    function fetchPatients() {
        patientsTableBody.innerHTML = '<tr><td colspan="7" style="text-align:center;">Loading...</td></tr>';
        fetch('patient_management.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=get_patients'
        })
        .then(res => res.json())
        .then(data => {
            if (data.success && Array.isArray(data.patients)) {
                patientsList = data.patients;
                renderPatientsTable();
            } else {
                patientsTableBody.innerHTML = '<tr><td colspan="7" style="text-align:center;">No patients found.</td></tr>';
            }
        })
        .catch(() => {
            patientsTableBody.innerHTML = '<tr><td colspan="7" style="text-align:center;">Error loading patients.</td></tr>';
        });
    }
    function renderPatientsTable() {
        const q = (patientSearch.value || '').toLowerCase();
        const filtered = patientsList.filter(p =>
            (p.full_name && p.full_name.toLowerCase().includes(q)) ||
            (p.patient_id && p.patient_id.toLowerCase().includes(q)) ||
            (p.room_number && p.room_number.toLowerCase().includes(q))
        );
        if (filtered.length === 0) {
            patientsTableBody.innerHTML = '<tr><td colspan="7" style="text-align:center;">No matching patients.</td></tr>';
            return;
        }
        patientsTableBody.innerHTML = filtered.map(p => `
            <tr>
                <td>${p.patient_id || ''}</td>
                <td>${p.full_name || ''}</td>
                <td>${p.room_number || ''}</td>
                <td>${p.gender || ''}</td>
                <td>${p.status || ''}</td>
                <td>${p.admission_date || ''}</td>
                <td><button class="btn" style="padding:4px 12px;font-size:0.95rem;" onclick="showPatientModal('${p.patient_id}')"><i class='fas fa-eye'></i> View</button></td>
            </tr>
        `).join('');
    }
    patientSearch.addEventListener('input', renderPatientsTable);
    function showPatientModal(patientId) {
        const p = patientsList.find(x => x.patient_id === patientId);
        if (!p) return;
        let html = `<h2 style='margin-bottom:0.5rem;'>${p.full_name || ''}</h2>`;
        html += `<div style='margin-bottom:0.7rem;color:#7b809a;'>Patient ID: ${p.patient_id || ''}</div>`;
        html += `<div><b>Room:</b> ${p.room_number || ''}</div>`;
        html += `<div><b>Gender:</b> ${p.gender || ''}</div>`;
        html += `<div><b>Status:</b> ${p.status || ''}</div>`;
        html += `<div><b>Admission Date:</b> ${p.admission_date || ''}</div>`;
        html += `<div><b>Emergency Contact:</b> ${p.emergency_contact || ''}</div>`;
        html += `<div style='margin-top:1rem;'><b>Medical History:</b><br>${p.medical_history || 'N/A'}</div>`;
        html += `<div style='margin-top:1rem;'><b>Current Medications:</b><br>${p.current_medications || 'N/A'}</div>`;
        document.getElementById('modalPatientContent').innerHTML = html;
        document.getElementById('patientModal').classList.add('active');
    }
    function closePatientModal() {
        document.getElementById('patientModal').classList.remove('active');
    }
    // Fetch patients on page load
    fetchPatients();
    </script>
</body>
</html>
