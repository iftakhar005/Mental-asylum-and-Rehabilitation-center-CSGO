<?php
require_once 'session_check.php';
require_once 'db.php';

header('Content-Type: application/json');

$role = $_SESSION['role'] ?? null;
$user_id = $_SESSION['user_id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$allowed_roles = ['chief-staff', 'doctor', 'therapist', 'nurse'];
if (!$role || !in_array($role, $allowed_roles)) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

try {
    $patient_id = $_GET['patient_id'] ?? null;
    if (!$patient_id) {
        throw new Exception('Patient ID is required');
    }

    // If nurse, check assignment
    if ($role === 'nurse') {
        // Get nurse staff_id
        $stmt = $conn->prepare('SELECT staff_id FROM staff WHERE user_id = ? AND role = ?');
        $stmt->bind_param('is', $user_id, $role);
        $stmt->execute();
        $nurse = $stmt->get_result()->fetch_assoc();
        if (!$nurse) throw new Exception('Nurse not found');
        $nurse_id = $nurse['staff_id'];
        // Get latest assessment for patient
        $stmt = $conn->prepare('SELECT morning_staff, evening_staff, night_staff FROM patient_assessments WHERE patient_id = (SELECT patient_id FROM patients WHERE id = ?) ORDER BY assessment_date DESC LIMIT 1');
        $stmt->bind_param('i', $patient_id);
        $stmt->execute();
        $assessment = $stmt->get_result()->fetch_assoc();
        if (!$assessment || !in_array($nurse_id, [$assessment['morning_staff'], $assessment['evening_staff'], $assessment['night_staff']])) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized (not assigned nurse)']);
            exit();
        }
    }

    // Get all treatments for the patient
    $treatments_query = "
        SELECT t.*, 
               mt.medication_type, mt.dosage, mt.schedule, mt.start_date as med_start_date,
               tt.therapy_type, tt.approach, tt.session_notes, tt.session_date,
               rt.rehab_type, rt.program_details, rt.goals, rt.start_date as rehab_start_date,
               ci.patient_status, ci.notes, ci.crisis_date,
               fp.review_schedule, fp.reintegration,
               td.progress_notes, td.treatment_response, td.risk_assessment, td.documentation_date
        FROM treatments t
        LEFT JOIN medication_treatments mt ON t.id = mt.treatment_id
        LEFT JOIN therapy_treatments tt ON t.id = tt.treatment_id
        LEFT JOIN rehabilitation_treatments rt ON t.id = rt.treatment_id
        LEFT JOIN crisis_interventions ci ON t.id = ci.treatment_id
        LEFT JOIN follow_up_plans fp ON t.id = fp.treatment_id
        LEFT JOIN treatment_documentation td ON t.id = td.treatment_id
        WHERE t.patient_id = ?
        ORDER BY t.created_at DESC
    ";
    $stmt = $conn->prepare($treatments_query);
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $treatments = [];
    while ($row = $result->fetch_assoc()) {
        $treatments[] = $row;
    }
    echo json_encode([
        'success' => true,
        'treatments' => $treatments
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?> 