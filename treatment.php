<?php
require_once 'session_check.php';
check_login(['doctor', 'therapist']);
require_once 'db.php';

// Get the numeric user ID from the session
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Find the staff record for this user
$stmt = $conn->prepare("SELECT * FROM staff WHERE user_id = ? AND role = ?");
$stmt->bind_param("is", $user_id, $role);
$stmt->execute();
$staff = $stmt->get_result()->fetch_assoc();

if (!$staff) {
    echo '<div style="color:red; font-weight:bold;">' . ucfirst($role) . ' not found for user_id: ' . htmlspecialchars($user_id) . '.<br>Check staff table.</div>';
    exit();
}

// Get patients assigned to this staff (status: admitted or active)
$staff_user_id = $user_id; // Numeric user_id from session
$patients_query = "SELECT p.*, u.username 
                  FROM patients p 
                  JOIN users u ON p.user_id = u.id 
                  JOIN staff_patient_assignments spa ON p.id = spa.patient_id 
                  WHERE spa.staff_id = ? AND (p.status = 'admitted' OR p.status = 'active') 
                  ORDER BY p.admission_date DESC";
$stmt = $conn->prepare($patients_query);
$stmt->bind_param("i", $staff_user_id);
$stmt->execute();
$patients = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch available medicine types and medicines from stock
$available_medicines = [];
$available_types = [];
$today = date('Y-m-d');
$med_result = $conn->query("SELECT id, type, name, strength, quantity, expire_date FROM medicine_stock WHERE quantity > 0 AND expire_date >= '$today' ORDER BY type ASC, name ASC, strength ASC");
if ($med_result) {
    $available_medicines = $med_result->fetch_all(MYSQLI_ASSOC);
    foreach ($available_medicines as $med) {
        if (!in_array($med['type'], $available_types)) {
            $available_types[] = $med['type'];
        }
    }
}

// Handle AJAX POST requests for treatment forms
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_type']) && $_POST['form_type'] === 'allTreatments') {
    header('Content-Type: application/json');
    require_once 'db.php';
    $user_id = $_SESSION['user_id'];
    $role = $_SESSION['role'];
    $patient_id = $_POST['patient_id'] ?? null;
    $response = ['success' => false, 'messages' => []];
    try {
        error_log('POST data: ' . print_r($_POST, true));
        $stmt = $conn->prepare("SELECT user_id FROM staff WHERE user_id = ? AND role = ?");
        $stmt->bind_param("is", $user_id, $role);
        $stmt->execute();
        $staff = $stmt->get_result()->fetch_assoc();
        if (!$staff) throw new Exception(ucfirst($role) . ' not found');
        $staff_id = $staff['user_id'];
        $conn->begin_transaction();
        $saved_any = false;
        // Medication
        $medication_type = $_POST['medicine_type'] ?? '';
        $dosage = $_POST['dosage'] ?? '';
        $schedule = $_POST['schedule'] ?? '';
        if ($medication_type && $dosage && $schedule) {
            $stmt = $conn->prepare("INSERT INTO treatments (patient_id, doctor_id, treatment_type) VALUES (?, ?, 'medication')");
            if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);
            $stmt->bind_param("ii", $patient_id, $staff_id);
            if (!$stmt->execute()) throw new Exception('Execute failed: ' . $stmt->error);
            $treatment_id = $conn->insert_id;
            $stmt = $conn->prepare("INSERT INTO medication_treatments (treatment_id, medication_type, dosage, schedule, start_date) VALUES (?, ?, ?, ?, CURDATE())");
            if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);
            $stmt->bind_param("isss", $treatment_id, $medication_type, $dosage, $schedule);
            if (!$stmt->execute()) throw new Exception('Execute failed: ' . $stmt->error);
            $response['messages'][] = 'Medication plan saved.';
            $saved_any = true;
        }
        // Therapy
        $therapy_type = $_POST['therapy_type'] ?? '';
        $approach = $_POST['approach'] ?? '';
        if ($therapy_type && $approach) {
            $session_notes = $_POST['session_notes'] ?? '';
            $stmt = $conn->prepare("INSERT INTO treatments (patient_id, doctor_id, treatment_type) VALUES (?, ?, 'therapy')");
            if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);
            $stmt->bind_param("ii", $patient_id, $staff_id);
            if (!$stmt->execute()) throw new Exception('Execute failed: ' . $stmt->error);
            $treatment_id = $conn->insert_id;
            $stmt = $conn->prepare("INSERT INTO therapy_treatments (treatment_id, therapy_type, approach, session_notes, session_date) VALUES (?, ?, ?, ?, CURDATE())");
            if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);
            $stmt->bind_param("isss", $treatment_id, $therapy_type, $approach, $session_notes);
            if (!$stmt->execute()) throw new Exception('Execute failed: ' . $stmt->error);
            $response['messages'][] = 'Therapy plan saved.';
            $saved_any = true;
        }
        // Rehabilitation (only if not doctor role)
        if ($role !== 'doctor') {
            $rehab_type = $_POST['rehab_type'] ?? '';
            $program_details = $_POST['program_details'] ?? '';
            $goals = $_POST['goals'] ?? '';
            if ($rehab_type && $program_details && $goals) {
                $stmt = $conn->prepare("INSERT INTO treatments (patient_id, doctor_id, treatment_type) VALUES (?, ?, 'rehabilitation')");
                if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);
                $stmt->bind_param("ii", $patient_id, $staff_id);
                if (!$stmt->execute()) throw new Exception('Execute failed: ' . $stmt->error);
                $treatment_id = $conn->insert_id;
                $stmt = $conn->prepare("INSERT INTO rehabilitation_treatments (treatment_id, rehab_type, program_details, goals, start_date) VALUES (?, ?, ?, ?, CURDATE())");
                if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);
                $stmt->bind_param("isss", $treatment_id, $rehab_type, $program_details, $goals);
                if (!$stmt->execute()) throw new Exception('Execute failed: ' . $stmt->error);
                $response['messages'][] = 'Rehabilitation plan saved.';
                $saved_any = true;
            }
        }
        // Crisis
        $patient_status = $_POST['patient_status'] ?? '';
        if ($patient_status) {
            $notes = $_POST['notes'] ?? '';
            $stmt = $conn->prepare("INSERT INTO treatments (patient_id, doctor_id, treatment_type) VALUES (?, ?, 'crisis_intervention')");
            if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);
            $stmt->bind_param("ii", $patient_id, $staff_id);
            if (!$stmt->execute()) throw new Exception('Execute failed: ' . $stmt->error);
            $treatment_id = $conn->insert_id;
            $stmt = $conn->prepare("INSERT INTO crisis_interventions (treatment_id, patient_status, notes, crisis_date) VALUES (?, ?, ?, CURDATE())");
            if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);
            $stmt->bind_param("iss", $treatment_id, $patient_status, $notes);
            if (!$stmt->execute()) throw new Exception('Execute failed: ' . $stmt->error);
            $response['messages'][] = 'Crisis intervention plan saved.';
            $saved_any = true;
        }
        // Follow-up
        $review_schedule = $_POST['review_schedule'] ?? '';
        $reintegration = $_POST['reintegration'] ?? '';
        if ($review_schedule && $reintegration) {
            $stmt = $conn->prepare("INSERT INTO treatments (patient_id, doctor_id, treatment_type) VALUES (?, ?, 'follow_up')");
            if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);
            $stmt->bind_param("ii", $patient_id, $staff_id);
            if (!$stmt->execute()) throw new Exception('Execute failed: ' . $stmt->error);
            $treatment_id = $conn->insert_id;
            $stmt = $conn->prepare("INSERT INTO follow_up_plans (treatment_id, review_schedule, reintegration) VALUES (?, ?, ?)");
            if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);
            $stmt->bind_param("iss", $treatment_id, $review_schedule, $reintegration);
            if (!$stmt->execute()) throw new Exception('Execute failed: ' . $stmt->error);
            $response['messages'][] = 'Follow-up plan saved.';
            $saved_any = true;
        }
        // Documentation
        $progress_notes = $_POST['progress_notes'] ?? '';
        $treatment_response = $_POST['treatment_response'] ?? '';
        $risk_assessment = $_POST['risk_assessment'] ?? '';
        if ($progress_notes && $treatment_response && $risk_assessment) {
            $stmt = $conn->prepare("INSERT INTO treatments (patient_id, doctor_id, treatment_type) VALUES (?, ?, 'documentation')");
            if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);
            $stmt->bind_param("ii", $patient_id, $staff_id);
            if (!$stmt->execute()) throw new Exception('Execute failed: ' . $stmt->error);
            $treatment_id = $conn->insert_id;
            $stmt = $conn->prepare("INSERT INTO treatment_documentation (treatment_id, progress_notes, treatment_response, risk_assessment, documentation_date) VALUES (?, ?, ?, ?, CURDATE())");
            if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);
            $stmt->bind_param("isss", $treatment_id, $progress_notes, $treatment_response, $risk_assessment);
            if (!$stmt->execute()) throw new Exception('Execute failed: ' . $stmt->error);
            $response['messages'][] = 'Documentation saved.';
            $saved_any = true;
        }
        if (!$saved_any) throw new Exception('No section was filled. Please fill at least one section.');
        $conn->commit();
        $response['success'] = true;
    } catch (Exception $e) {
        if ($conn->connect_errno === 0) $conn->rollback();
        $response['success'] = false;
        $response['error'] = $e->getMessage();
        error_log('Treatment save error: ' . $e->getMessage());
    }
    echo json_encode($response);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Treatment Management - Doctor Dashboard</title>
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
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .header h1 {
            color: var(--primary);
            font-size: 24px;
        }

        .treatment-section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .section-title {
            color: var(--primary);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--light-gray);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: var(--dark);
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--light-gray);
            border-radius: 5px;
            font-size: 14px;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background-color: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--secondary);
        }

        .patient-select {
            margin-bottom: 30px;
        }

        .treatment-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .treatment-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .treatment-card h3 {
            color: var(--primary);
            margin-bottom: 15px;
        }

        .treatment-list {
            list-style: none;
        }

        .treatment-list li {
            padding: 10px 0;
            border-bottom: 1px solid var(--light-gray);
        }

        .treatment-list li:last-child {
            border-bottom: none;
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-active {
            background-color: #e6f9ed;
            color: #28a745;
        }

        .status-pending {
            background-color: #fff4e6;
            color: #f8961e;
        }

        .status-completed {
            background-color: #e6f9ed;
            color: #28a745;
        }

        .notes-section {
            margin-top: 20px;
        }

        .notes-section textarea {
            width: 100%;
            min-height: 100px;
            padding: 10px;
            border: 1px solid var(--light-gray);
            border-radius: 5px;
            margin-bottom: 10px;
        }

        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .alert-success {
            background-color: #e6f9ed;
            color: #28a745;
            border: 1px solid #c3e6cb;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .treatment-history-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .treatment-history-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid var(--primary);
        }

        .treatment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--light-gray);
        }

        .treatment-header h4 {
            color: var(--primary);
            margin: 0;
            font-size: 16px;
        }

        .treatment-date {
            color: var(--gray);
            font-size: 12px;
            background: var(--light-gray);
            padding: 4px 8px;
            border-radius: 12px;
        }

        .treatment-details {
            margin-bottom: 15px;
        }

        .treatment-details p {
            margin: 8px 0;
            font-size: 14px;
            line-height: 1.4;
        }

        .treatment-details strong {
            color: var(--dark);
        }

        .treatment-status {
            text-align: right;
        }

        .status-completed {
            background-color: #d4edda;
            color: #155724;
        }

        .status-discontinued {
            background-color: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Treatment Management</h1>
            <div class="user-info">
                <div class="user-avatar">
                    <i class="fas fa-user-md"></i>
                </div>
                <div class="user-details">
                    <span class="user-name">Dr. <?php echo htmlspecialchars($staff['full_name'] ?? 'Doctor'); ?></span>
                    <a href="logout.php" class="logout-btn" style="display: inline-block; margin-left: 15px; color: #dc3545; text-decoration: none;">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>

        <!-- Patient Selection -->
        <div class="treatment-section">
            <div class="patient-select">
                <label for="patient">Select Patient:</label>
                <select id="patient" class="form-control">
                    <option value="">Choose a patient...</option>
                    <?php foreach ($patients as $patient): ?>
                        <option value="<?php echo $patient['id']; ?>">
                            <?php echo htmlspecialchars($patient['full_name'] ?? ($patient['first_name'] . ' ' . $patient['last_name'])); ?> (<?php echo $patient['patient_id']; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Treatment Sections -->
        <form id="allTreatmentsForm">
            <div class="treatment-grid">
                <!-- Medication Management -->
                <div class="treatment-card">
                    <h3><i class="fas fa-pills"></i> Medication Management</h3>
                    <div class="form-group">
                        <label>Type of Medicine</label>
                        <select class="form-control" id="medicine_type_select" name="medicine_type">
                            <option value="">Select Type</option>
                            <?php foreach ($available_types as $type): ?>
                                <option value="<?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars($type); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Medicine</label>
                        <select class="form-control" id="medicine_name_select" name="medicine_id">
                            <option value="">Select Medicine</option>
                            <?php foreach ($available_medicines as $med): ?>
                                <option value="<?php echo $med['id']; ?>" data-type="<?php echo htmlspecialchars($med['type']); ?>">
                                    <?php echo htmlspecialchars($med['name']) . ' (' . htmlspecialchars($med['strength']) . ') - Qty: ' . $med['quantity'] . ' (Exp: ' . $med['expire_date'] . ')'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Dosage</label>
                        <input type="text" class="form-control" name="dosage" placeholder="Enter dosage">
                    </div>
                    <div class="form-group">
                        <label>Schedule</label>
                        <input type="text" class="form-control" name="schedule" placeholder="e.g., Twice daily after meals">
                    </div>
                </div>
                <!-- Psychotherapy / Counseling -->
                <div class="treatment-card">
                    <h3><i class="fas fa-comments"></i> Psychotherapy / Counseling</h3>
                    <div class="form-group">
                        <label>Therapy Type</label>
                        <select class="form-control" name="therapy_type">
                            <option value="">Select Type</option>
                            <option value="individual">Individual Therapy</option>
                            <option value="group">Group Therapy</option>
                            <option value="family">Family Counseling</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Approach</label>
                        <select class="form-control" name="approach">
                            <option value="">Select Approach</option>
                            <option value="cbt">CBT</option>
                            <option value="dbt">DBT</option>
                            <option value="psychoanalysis">Psychoanalysis</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Session Notes</label>
                        <textarea class="form-control" name="session_notes" placeholder="Enter session notes..."></textarea>
                    </div>
                </div>
                <!-- Rehabilitation Services -->
                <?php if ($role !== 'doctor'): ?>
                <div class="treatment-card">
                    <h3><i class="fas fa-hands-helping"></i> Rehabilitation Services</h3>
                    <div class="form-group">
                        <label>Service Type</label>
                        <select class="form-control" name="rehab_type">
                            <option value="">Select Service</option>
                            <option value="vocational">Vocational Training</option>
                            <option value="life_skills">Life Skills Development</option>
                            <option value="social">Social Integration</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Program Details</label>
                        <textarea class="form-control" name="program_details" placeholder="Enter program details..."></textarea>
                    </div>
                    <div class="form-group">
                        <label>Goals</label>
                        <textarea class="form-control" name="goals" placeholder="Enter rehabilitation goals..."></textarea>
                    </div>
                </div>
                <?php endif; ?>
                <!-- Crisis Intervention -->
                <div class="treatment-card">
                    <h3><i class="fas fa-exclamation-triangle"></i> Crisis Intervention</h3>
                    <div class="form-group">
                        <label>Patient Status</label>
                        <select class="form-control" name="patient_status">
                            <option value="">Select Status</option>
                            <option value="stable">Stable</option>
                            <option value="unstable">Unstable</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea class="form-control" name="notes" placeholder="Enter notes..."></textarea>
                    </div>
                </div>
                <!-- Follow-Up Plan -->
                <div class="treatment-card">
                    <h3><i class="fas fa-calendar-check"></i> Follow-Up Plan</h3>
                    <div class="form-group">
                        <label>Review Schedule (Next Appointment)</label>
                        <select class="form-control" name="review_schedule">
                            <option value="">Select Next Appointment</option>
                            <option value="1 week">In 1 week</option>
                            <option value="2 weeks">In 2 weeks</option>
                            <option value="1 month">In 1 month</option>
                            <option value="custom">Custom</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Community Reintegration</label>
                        <select class="form-control" name="reintegration">
                            <option value="">Select Option</option>
                            <option value="yes">Yes</option>
                            <option value="no">No</option>
                            <option value="partial">Partial</option>
                        </select>
                    </div>
                </div>
                <!-- Documentation -->
                <div class="treatment-card">
                    <h3><i class="fas fa-file-medical"></i> Documentation</h3>
                    <div class="form-group">
                        <label>Progress Notes</label>
                        <textarea class="form-control" name="progress_notes" placeholder="Enter progress notes..."></textarea>
                    </div>
                    <div class="form-group">
                        <label>Treatment Response</label>
                        <textarea class="form-control" name="treatment_response" placeholder="Enter treatment response..."></textarea>
                    </div>
                    <div class="form-group">
                        <label>Risk Assessment</label>
                        <textarea class="form-control" name="risk_assessment" placeholder="Enter risk assessment..."></textarea>
                    </div>
                </div>
            </div>
            <div style="text-align:center; margin-top:2rem;">
                <button type="submit" class="btn btn-primary" id="saveAllBtn">Save All</button>
            </div>
        </form>

        <!-- Treatment History Section -->
        <div class="treatment-section" id="treatmentHistory" style="display: none;">
            <h2 class="section-title"><i class="fas fa-history"></i> Treatment History</h2>
            <div id="treatmentHistoryContent"></div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add message display area
            const messageArea = document.createElement('div');
            messageArea.id = 'messageArea';
            messageArea.style.display = 'none';
            messageArea.style.marginBottom = '20px';
            messageArea.style.padding = '15px';
            messageArea.style.borderRadius = '5px';
            document.querySelector('.container').insertBefore(messageArea, document.querySelector('.treatment-section'));

            function showMessage(message, type = 'success') {
                const messageArea = document.getElementById('messageArea');
                messageArea.textContent = message;
                messageArea.style.display = 'block';
                messageArea.className = `alert alert-${type}`;
                
                setTimeout(() => {
                    messageArea.style.display = 'none';
                }, 5000);
            }

            // Handle form submissions
            const forms = {
                'allTreatmentsForm': 'allTreatments'
            };

            Object.keys(forms).forEach(formId => {
                const form = document.getElementById(formId);
                if (form) {
                    console.log('Attaching submit handler to form:', formId);
                    form.addEventListener('submit', async function(e) {
                        e.preventDefault();
                        console.log('Save All form submitted');
                        const patientId = document.getElementById('patient').value;
                        if (!patientId) {
                            showMessage('Please select a patient first', 'danger');
                            return;
                        }
                        const formData = new FormData(form);
                        formData.append('patient_id', patientId);
                        formData.append('form_type', forms[formId]);
                        for (let pair of formData.entries()) {
                            console.log('FormData:', pair[0], pair[1]);
                        }
                        try {
                            const response = await fetch('treatment.php', {
                                method: 'POST',
                                body: formData
                            });
                            const responseText = await response.text();
                            console.log('Response text:', responseText);
                            let result;
                            try {
                                result = JSON.parse(responseText);
                            } catch (e) {
                                showMessage('Server returned invalid response', 'danger');
                                return;
                            }
                            if (result.success) {
                                if (result.messages && Array.isArray(result.messages)) {
                                    showMessage(result.messages.join('\n'), 'success');
                                } else if (result.message) {
                                    showMessage(result.message, 'success');
                                } else {
                                    showMessage('Saved successfully!', 'success');
                                }
                                setTimeout(() => { window.location.reload(); }, 1200);
                                form.reset();
                            } else {
                                showMessage(result.error || 'An error occurred', 'danger');
                            }
                        } catch (error) {
                            console.log('Fetch error:', error);
                            showMessage('An error occurred while saving the data', 'danger');
                        }
                    });
                } else {
                    console.log('Form not found:', formId);
                }
            });

            // Handle patient selection
            const patientSelect = document.getElementById('patient');
            patientSelect.addEventListener('change', async function() {
                const patientId = this.value;
                if (patientId) {
                    try {
                        const response = await fetch(`get_patient_treatments.php?patient_id=${patientId}`);
                        const result = await response.json();
                        console.log('Treatment history result:', result);
                        if (result.success && Array.isArray(result.treatments)) {
                            displayTreatmentHistory(result.treatments);
                        } else {
                            showMessage(result.error || 'Failed to load treatment history', 'danger');
                            document.getElementById('treatmentHistory').style.display = 'block';
                            document.getElementById('treatmentHistoryContent').innerHTML = '<div class="error">Failed to load treatment history.</div>';
                        }
                    } catch (error) {
                        console.error('Error loading treatment history:', error);
                        showMessage('Failed to load treatment history', 'danger');
                        document.getElementById('treatmentHistory').style.display = 'block';
                        document.getElementById('treatmentHistoryContent').innerHTML = '<div class="error">Failed to load treatment history.</div>';
                    }
                } else {
                    document.getElementById('treatmentHistory').style.display = 'none';
                }
            });

            function displayTreatmentHistory(treatments) {
                const historySection = document.getElementById('treatmentHistory');
                const historyContent = document.getElementById('treatmentHistoryContent');
                if (!Array.isArray(treatments) || treatments.length === 0) {
                    historyContent.innerHTML = '<p style="color: var(--gray); text-align: center; padding: 20px;">No treatment history found for this patient.</p>';
                    historySection.style.display = 'block';
                    return;
                }
                let html = '<div class="treatment-history-grid">';
                treatments.forEach(treatment => {
                    const treatmentDate = treatment.created_at ? new Date(treatment.created_at).toLocaleDateString() : 'N/A';
                    const treatmentType = treatment.treatment_type ? treatment.treatment_type.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase()) : 'Unknown';
                    html += `
                        <div class="treatment-history-card">
                            <div class="treatment-header">
                                <h4>${treatmentType}</h4>
                                <span class="treatment-date">${treatmentDate}</span>
                            </div>
                            <div class="treatment-details">`;
                    if (treatment.medication_type || treatment.dosage || treatment.schedule) {
                        html += `<div><strong>Medication:</strong> ${treatment.medication_type || 'N/A'} | Dosage: ${treatment.dosage || 'N/A'} | Schedule: ${treatment.schedule || 'N/A'}</div>`;
                    }
                    if (treatment.therapy_type || treatment.approach || treatment.session_notes) {
                        html += `<div><strong>Therapy:</strong> ${treatment.therapy_type || 'N/A'} | Approach: ${treatment.approach || 'N/A'} | Notes: ${treatment.session_notes || 'N/A'}</div>`;
                    }
                    if (treatment.rehab_type || treatment.program_details || treatment.goals) {
                        html += `<div><strong>Rehabilitation:</strong> ${treatment.rehab_type || 'N/A'} | Program: ${treatment.program_details || 'N/A'} | Goals: ${treatment.goals || 'N/A'}</div>`;
                    }
                    if (treatment.patient_status || treatment.notes) {
                        html += `<div><strong>Status:</strong> ${treatment.patient_status || 'N/A'} | Notes: ${treatment.notes || 'N/A'}</div>`;
                    }
                    if (treatment.review_schedule || treatment.reintegration) {
                        html += `<div><strong>Follow-up:</strong> Review: ${treatment.review_schedule || 'N/A'} | Reintegration: ${treatment.reintegration || 'N/A'}</div>`;
                    }
                    if (treatment.progress_notes || treatment.treatment_response || treatment.risk_assessment) {
                        html += `<div><strong>Progress:</strong> Notes: ${treatment.progress_notes || 'N/A'} | Response: ${treatment.treatment_response || 'N/A'} | Risk: ${treatment.risk_assessment || 'N/A'}</div>`;
                    }
                    html += '</div></div>';
                });
                html += '</div>';
                historyContent.innerHTML = html;
                historySection.style.display = 'block';
            }
        });
    </script>
</body>
</html>