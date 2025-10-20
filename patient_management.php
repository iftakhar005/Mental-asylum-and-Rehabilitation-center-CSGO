<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}
// Suppress error reporting to prevent HTML errors from corrupting JSON output
error_reporting(0);
ini_set('display_errors', 0);

require_once 'db.php';
require_once 'security_manager.php';
require_once 'session_check.php';
require_once 'simple_rsa_crypto.php';
require_once 'security_decrypt.php';

// Check if user is logged in and has appropriate permissions
check_login(['admin', 'chief-staff', 'doctor', 'nurse', 'receptionist']);

// Initialize response array
$response = ['success' => false, 'message' => '', 'data' => null];

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

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    try {
        switch ($action) {
            case 'add_patient':
                // Define validation rules for patient data
                $validation_rules = [
                    'name' => ['type' => 'name', 'max_length' => 100, 'required' => true],
                    'dob' => ['type' => 'date', 'required' => true],
                    'gender' => ['type' => 'string', 'max_length' => 10, 'required' => true],
                    'admission_date' => ['type' => 'date', 'required' => true],
                    'room' => ['type' => 'alphanumeric', 'max_length' => 20, 'required' => true],
                    'emergency_contact' => ['type' => 'phone', 'max_length' => 20, 'required' => true],
                    'type' => ['type' => 'string', 'max_length' => 50, 'required' => true],
                    'mobility_status' => ['type' => 'string', 'max_length' => 50, 'required' => true],
                    'medical_history' => ['type' => 'string', 'max_length' => 1000, 'required' => false],
                    'current_medications' => ['type' => 'string', 'max_length' => 1000, 'required' => false]
                ];
                
                // Validate and sanitize all inputs
                $validated_data = $securityManager->processFormData($_POST, $validation_rules);
                
                // Encrypt sensitive patient data before storing
                if (!empty($validated_data['medical_history'])) {
                    $validated_data['medical_history'] = rsa_encrypt($validated_data['medical_history']);
                }
                if (!empty($validated_data['current_medications'])) {
                    $validated_data['current_medications'] = rsa_encrypt($validated_data['current_medications']);
                }
                
                // Generate patient ID
                $patient_id = 'ARC-' . date('Ymd') . '-' . rand(1000, 9999);
                
                // Insert into users table first
                $username = 'patient_' . strtolower(explode(' ', $validated_data['name'])[0]) . rand(100, 999);
                $password = generateSecurePassword(12); // Temporary password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                $email = $username . '@relative.gmail.com';
                
                $user_result = $securityManager->secureExecute(
                    "INSERT INTO users (username, password_hash, email, role, first_name, last_name, contact_number, emergency_contact, date_of_birth, address, temp_password) 
                     VALUES (?, ?, ?, 'relative', ?, '', '', ?, ?, '', ?)",
                    [
                        $username, 
                        $hashed_password, 
                        $email, 
                        $validated_data['name'], 
                        $validated_data['emergency_contact'], 
                        $validated_data['dob'], 
                        $password
                    ],
                    'sssssss'
                );
                
                if (!$user_result['success']) {
                    throw new Exception('Error creating user account');
                }
                
                $user_id = $user_result['insert_id'];
                
                // Insert into patients table using secure query
                $patient_result = $securityManager->secureExecute(
                    "INSERT INTO patients (user_id, patient_id, full_name, date_of_birth, gender, contact_number, emergency_contact, medical_history, current_medications, admission_date, room_number, status, type, mobility_status) 
                     VALUES (?, ?, ?, ?, ?, '', ?, ?, ?, ?, ?, 'admitted', ?, ?)",
                    [
                        $user_id,
                        $patient_id,
                        $validated_data['name'],
                        $validated_data['dob'],
                        $validated_data['gender'],
                        $validated_data['emergency_contact'],
                        $validated_data['medical_history'],
                        $validated_data['current_medications'],
                        $validated_data['admission_date'],
                        $validated_data['room'],
                        $validated_data['type'],
                        $validated_data['mobility_status']
                    ],
                    'isssssssssss'
                );
                
                if (!$patient_result['success']) {
                    throw new Exception('Error creating patient record');
                }
                
                // Log the action
                $securityManager->logSecurityEvent('PATIENT_ADDED', [
                    'patient_id' => $patient_id,
                    'admin_user_id' => $_SESSION['user_id']
                ]);
                
                $response['success'] = true;
                $response['message'] = 'Patient added successfully';
                $response['data'] = [
                    'patient_id' => $patient_id,
                    'username' => $username,
                    'temp_password' => $password
                ];
                break;
                
        case 'update_patient':
                // Validate required patient ID
                if (empty($_POST['patient_id'])) {
                    throw new Exception('Patient ID is required');
                }
                
                $patient_id = $securityManager->validateInput($_POST['patient_id'], [
                    'type' => 'string',
                    'max_length' => 50,
                    'required' => true
                ]);
                
                // Define validation rules for updateable fields
                $update_rules = [
                    'name' => ['type' => 'name', 'max_length' => 100, 'required' => false],
                    'dob' => ['type' => 'date', 'required' => false],
                    'gender' => ['type' => 'string', 'max_length' => 10, 'required' => false],
                    'room' => ['type' => 'alphanumeric', 'max_length' => 20, 'required' => false],
                    'emergency_contact' => ['type' => 'phone', 'max_length' => 20, 'required' => false],
                    'medical_history' => ['type' => 'string', 'max_length' => 1000, 'required' => false],
                    'current_medications' => ['type' => 'string', 'max_length' => 1000, 'required' => false],
                    'type' => ['type' => 'string', 'max_length' => 50, 'required' => false]
                ];
                
                // Process only the fields that are provided
                $updates = [];
                $params = [];
                $types = '';
                
                $field_mapping = [
                    'name' => 'full_name',
                    'dob' => 'date_of_birth',
                    'gender' => 'gender',
                    'room' => 'room_number',
                    'emergency_contact' => 'emergency_contact',
                    'medical_history' => 'medical_history',
                    'current_medications' => 'current_medications',
                    'type' => 'type'
                ];
                
                foreach ($field_mapping as $input_field => $db_field) {
                    if (isset($_POST[$input_field]) && $_POST[$input_field] !== '') {
                        $validated_value = $securityManager->validateInput($_POST[$input_field], $update_rules[$input_field]);
                        
                        // Encrypt sensitive fields before updating
                        if ($input_field === 'medical_history' || $input_field === 'current_medications') {
                            $validated_value = rsa_encrypt($validated_value);
                        }
                        
                        $updates[] = "$db_field = ?";
                        $params[] = $validated_value;
                        $types .= 's';
                    }
                }
                
                if (empty($updates)) {
                    throw new Exception('No fields to update');
                }
                
                // Add patient_id parameter
                $params[] = $patient_id;
                $types .= 's';
                
                $update_query = "UPDATE patients SET " . implode(', ', $updates) . " WHERE patient_id = ?";
                $result = $securityManager->secureExecute($update_query, $params, $types);
                
                if (!$result['success']) {
                    throw new Exception('Error updating patient');
                }
                
                // Log the action
                $securityManager->logSecurityEvent('PATIENT_UPDATED', [
                    'patient_id' => $patient_id,
                    'admin_user_id' => $_SESSION['user_id'],
                    'updated_fields' => array_keys($field_mapping)
                ]);
                
                $response['success'] = true;
                $response['message'] = 'Patient updated successfully';
                break;
                
            case 'get_patient':
                if (empty($_POST['patient_id'])) {
                    throw new Exception('Patient ID is required');
                }
                
                $patient_id = $securityManager->validateInput($_POST['patient_id'], [
                    'type' => 'string',
                    'max_length' => 50,
                    'required' => true
                ]);
                
                $result = $securityManager->secureSelect(
                    "SELECT p.*, u.username, u.email, u.temp_password, u.first_name, u.last_name, u.contact_number, u.emergency_contact, u.date_of_birth, u.address, (
                        SELECT s.full_name FROM patient_assessments pa
                        LEFT JOIN staff s ON pa.assigned_doctor = s.staff_id
                        WHERE pa.patient_id = p.patient_id AND pa.assigned_doctor IS NOT NULL
                        ORDER BY pa.assessment_date DESC, pa.created_at DESC LIMIT 1
                    ) AS assigned_doctor_name, (
                        SELECT s2.full_name FROM patient_assessments pa2
                        LEFT JOIN staff s2 ON pa2.assigned_therapist = s2.staff_id
                        WHERE pa2.patient_id = p.patient_id AND pa2.assigned_therapist IS NOT NULL
                        ORDER BY pa2.assessment_date DESC, pa2.created_at DESC LIMIT 1
                    ) AS assigned_therapist_name
                    FROM patients p 
                    LEFT JOIN users u ON p.user_id = u.id 
                    WHERE p.patient_id = ?",
                    [$patient_id],
                    's'
                );
                
                if ($result->num_rows === 0) {
                    throw new Exception('Patient not found');
                }
                
                $patient_data = $result->fetch_assoc();
                
                // Decrypt sensitive patient data based on user role
                $current_user = [
                    'role' => $_SESSION['role'] ?? 'guest',
                    'username' => $_SESSION['username'] ?? 'unknown'
                ];
                $patient_data = decrypt_patient_medical_data($patient_data, $current_user);
                
                // Sanitize output data
                foreach ($patient_data as $key => $value) {
                    if ($value !== null) {
                        $patient_data[$key] = $securityManager->preventXSS($value);
                    }
                }
                
                $response['success'] = true;
                $response['data'] = $patient_data;
                break;
            $conn->query($update_query);
            
            if ($conn->error) {
                $response['message'] = 'Error updating patient: ' . $conn->error;
                break;
            }
            
            $response['success'] = true;
            $response['message'] = 'Patient updated successfully';
            break;
            
        case 'get_patient':
            if (empty($_POST['patient_id'])) {
                $response['message'] = 'Patient ID is required';
                break;
            }
            
            $patient_id = $conn->real_escape_string($_POST['patient_id']);
            $result = $conn->query("SELECT p.*, u.username, u.email, u.temp_password, u.first_name, u.last_name, u.contact_number, u.emergency_contact, u.date_of_birth, u.address, (
                SELECT s.full_name FROM patient_assessments pa
                LEFT JOIN staff s ON pa.assigned_doctor = s.staff_id
                WHERE pa.patient_id = p.patient_id AND pa.assigned_doctor IS NOT NULL
                ORDER BY pa.assessment_date DESC, pa.created_at DESC LIMIT 1
            ) AS assigned_doctor_name, (
                SELECT s2.full_name FROM patient_assessments pa2
                LEFT JOIN staff s2 ON pa2.assigned_therapist = s2.staff_id
                WHERE pa2.patient_id = p.patient_id AND pa2.assigned_therapist IS NOT NULL
                ORDER BY pa2.assessment_date DESC, pa2.created_at DESC LIMIT 1
            ) AS assigned_therapist_name
            FROM patients p 
            JOIN users u ON p.user_id = u.id 
            WHERE p.patient_id = '$patient_id'");
            
            if ($result && $result->num_rows > 0) {
                $patient = $result->fetch_assoc();
                
                // Decrypt sensitive patient data based on user role
                $current_user = [
                    'role' => $_SESSION['role'] ?? 'guest',
                    'username' => $_SESSION['username'] ?? 'unknown'
                ];
                $patient = decrypt_patient_medical_data($patient, $current_user);
                
                $response['success'] = true;
                $response['patient'] = $patient;
            } else {
                $response['message'] = 'Patient not found';
            }
            break;
            
        case 'get_patients':
            $filter = '';
            if (!empty($_POST['status'])) {
                $status = $conn->real_escape_string($_POST['status']);
                $filter = "WHERE p.status = '$status'";
            }
            $result = $conn->query("SELECT p.*, u.username, u.email, u.temp_password, u.first_name, u.last_name, u.contact_number, u.emergency_contact, u.date_of_birth, u.address, (
                SELECT s.full_name FROM patient_assessments pa
                LEFT JOIN staff s ON pa.assigned_doctor = s.staff_id
                WHERE pa.patient_id = p.patient_id AND pa.assigned_doctor IS NOT NULL
                ORDER BY pa.assessment_date DESC, pa.created_at DESC LIMIT 1
            ) AS assigned_doctor_name, (
                SELECT s2.full_name FROM patient_assessments pa2
                LEFT JOIN staff s2 ON pa2.assigned_therapist = s2.staff_id
                WHERE pa2.patient_id = p.patient_id AND pa2.assigned_therapist IS NOT NULL
                ORDER BY pa2.assessment_date DESC, pa2.created_at DESC LIMIT 1
            ) AS assigned_therapist_name
            FROM patients p 
            JOIN users u ON p.user_id = u.id 
            $filter 
            ORDER BY p.admission_date DESC");
            if ($result) {
                $patients = $result->fetch_all(MYSQLI_ASSOC);
                
                // Decrypt patient data based on user role
                $current_user = [
                    'role' => $_SESSION['role'] ?? 'guest',
                    'username' => $_SESSION['username'] ?? 'unknown'
                ];
                $patients = batch_decrypt_records($patients, $current_user, 'patient');
                
                // Attach appointments for each patient (progress_notes removed)
                foreach ($patients as &$patient) {
                    $pid = $conn->real_escape_string($patient['patient_id']);
                    // Appointments
                    $apt_result = $conn->query("SELECT * FROM appointments WHERE patient_id = '$pid' ORDER BY date DESC, time DESC");
                    $patient['appointments'] = $apt_result ? $apt_result->fetch_all(MYSQLI_ASSOC) : [];
                }
                $response['success'] = true;
                $response['patients'] = $patients;
            } else {
                $response['message'] = 'Error fetching patients: ' . $conn->error;
            }
            break;
            
        case 'delete_patient':
            if (empty($_POST['patient_id'])) {
                $response['message'] = 'Patient ID is required';
                break;
            }
            
            $patient_id = $conn->real_escape_string($_POST['patient_id']);
            
            // Check for associated records
            $result = $conn->query("SELECT user_id FROM patients WHERE patient_id = '$patient_id'");
            if ($result && $result->num_rows > 0) {
                $patient = $result->fetch_assoc();
                $user_id = $patient['user_id'];
                
                // Delete patient record
                $conn->query("DELETE FROM patients WHERE patient_id = '$patient_id'");
                
                if ($conn->error) {
                    $response['message'] = 'Error deleting patient: ' . $conn->error;
                    break;
                }
                
                // Delete associated user account
                $conn->query("DELETE FROM users WHERE id = $user_id");
                
                $response['success'] = true;
                $response['message'] = 'Patient deleted successfully';
            } else {
                $response['message'] = 'Patient not found';
            }
            break;
            
        case 'get_patient_stats':
            $stats_result = $conn->query("SELECT 
                COUNT(*) as total_patients,
                SUM(CASE WHEN status = 'admitted' THEN 1 ELSE 0 END) as active_patients,
                SUM(CASE WHEN status = 'discharged' THEN 1 ELSE 0 END) as discharged_patients,
                SUM(CASE WHEN status = 'transferred' THEN 1 ELSE 0 END) as transferred_patients
            FROM patients");
            
            if ($stats_result) {
                $stats = $stats_result->fetch_assoc();
                $response['success'] = true;
                $response['stats'] = $stats;
            } else {
                $response['message'] = 'Error fetching patient stats: ' . $conn->error;
            }
            break;
            
        case 'discharge_patient':
            if (empty($_POST['patient_id'])) {
                $response['message'] = 'Patient ID is required';
                break;
            }
            
            $patient_id = $conn->real_escape_string($_POST['patient_id']);
            
            // Get patient's room number before updating status
            $result = $conn->query("SELECT room_number FROM patients WHERE patient_id = '$patient_id'");
            if ($result && $result->num_rows > 0) {
                $patient = $result->fetch_assoc();
                $room_number = $patient['room_number'];
                
                // Update patient status to discharged
                $conn->query("UPDATE patients SET status = 'discharged' WHERE patient_id = '$patient_id'");
                
                if ($conn->error) {
                    $response['message'] = 'Error discharging patient: ' . $conn->error;
                    break;
                }
                
                // Update room status to available
                if ($room_number) {
                    $conn->query("UPDATE rooms SET status = 'available' WHERE room_number = '$room_number'");
                    if ($conn->error) {
                        $response['message'] = 'Error updating room status: ' . $conn->error;
                        break;
                    }
                }
                
                $response['success'] = true;
                $response['message'] = 'Patient discharged successfully';
            } else {
                $response['message'] = 'Patient not found';
            }
            break;
            
        case 'add_appointment':
            // Validate required fields
            $required_fields = ['patient_id', 'date', 'time', 'type', 'status'];
            $missing_fields = [];
            foreach ($required_fields as $field) {
                if (empty($_POST[$field])) {
                    $missing_fields[] = $field;
                }
            }
            // Doctor and/or therapist logic
            $doctor = $_POST['doctor'] ?? '';
            $therapist = $_POST['therapist'] ?? '';
            if (empty($doctor) && empty($therapist)) {
                $missing_fields[] = 'doctor_or_therapist';
            }
            if (!empty($missing_fields)) {
                $response['message'] = 'Missing required fields: ' . implode(', ', $missing_fields);
                break;
            }
            $patient_id = $conn->real_escape_string($_POST['patient_id']);
            $date = $conn->real_escape_string($_POST['date']);
            $time = $conn->real_escape_string($_POST['time']);
            $type = $conn->real_escape_string($_POST['type']);
            $status = $conn->real_escape_string($_POST['status']);
            // If therapist is set, store in therapist column, doctor column is NULL
            if (!empty($therapist)) {
                $therapist = $conn->real_escape_string($therapist);
                $conn->query("INSERT INTO appointments (patient_id, date, time, type, doctor, therapist, status) VALUES ('$patient_id', '$date', '$time', '$type', NULL, '$therapist', '$status')");
            } else {
                $doctor = $conn->real_escape_string($doctor);
                $conn->query("INSERT INTO appointments (patient_id, date, time, type, doctor, therapist, status) VALUES ('$patient_id', '$date', '$time', '$type', '$doctor', NULL, '$status')");
            }
            if ($conn->error) {
                $response['message'] = 'Error adding appointment: ' . $conn->error;
                break;
            }
            $response['success'] = true;
            $response['message'] = 'Appointment added successfully';
            break;
        case 'get_appointments':
            $appointments = [];
            $result = $conn->query("SELECT a.*, p.room_number, p.type,
                (SELECT s.full_name FROM patient_assessments pa LEFT JOIN staff s ON pa.assigned_doctor = s.staff_id WHERE pa.patient_id = p.patient_id AND pa.assigned_doctor IS NOT NULL ORDER BY pa.assessment_date DESC, pa.created_at DESC LIMIT 1) AS assigned_doctor_name,
                (SELECT s2.full_name FROM patient_assessments pa2 LEFT JOIN staff s2 ON pa2.assigned_therapist = s2.staff_id WHERE pa2.patient_id = p.patient_id AND pa2.assigned_therapist IS NOT NULL ORDER BY pa2.assessment_date DESC, pa2.created_at DESC LIMIT 1) AS assigned_therapist_name
                FROM appointments a JOIN patients p ON a.patient_id = p.patient_id ORDER BY a.date DESC, a.time DESC");
            if ($result) {
                $appointments = $result->fetch_all(MYSQLI_ASSOC);
                $response['success'] = true;
                $response['appointments'] = $appointments;
            } else {
                $response['message'] = 'Error fetching appointments: ' . $conn->error;
            }
            break;
        case 'add_progress_note':
        case 'get_progress_notes':
            $response['message'] = 'Progress notes functionality has been removed.';
            break;
        case 'get_available_rooms':
            $rooms_query = "SELECT * FROM rooms WHERE status = 'available' AND for_whom = 'Patients'";
            $rooms_result = $conn->query($rooms_query);
            $available_rooms = $rooms_result ? $rooms_result->fetch_all(MYSQLI_ASSOC) : [];
            $response['success'] = true;
            $response['rooms'] = $available_rooms;
            break;
        case 'add_assessment':
            // Validate required fields
            $required_fields = ['patient_id', 'assessment_date', 'patient_status'];
            $missing_fields = [];
            foreach ($required_fields as $field) {
                if (empty($_POST[$field])) {
                    $missing_fields[] = $field;
                }
            }
            if (!empty($missing_fields)) {
                $response['message'] = 'Missing required fields: ' . implode(', ', $missing_fields);
                break;
            }
            // Sanitize input
            $patient_id = $conn->real_escape_string($_POST['patient_id']);
            $assessment_date = $conn->real_escape_string($_POST['assessment_date']);
            $patient_status = $conn->real_escape_string($_POST['patient_status']);
            $meal_plan_id = !empty($_POST['meal_plan_id']) ? intval($_POST['meal_plan_id']) : null;
            $assigned_doctor = !empty($_POST['assigned_doctor']) ? $conn->real_escape_string($_POST['assigned_doctor']) : null;
            $assigned_therapist = !empty($_POST['assigned_therapist']) ? $conn->real_escape_string($_POST['assigned_therapist']) : null;
            $morning_staff = !empty($_POST['morning_staff']) ? $conn->real_escape_string($_POST['morning_staff']) : null;
            $evening_staff = !empty($_POST['evening_staff']) ? $conn->real_escape_string($_POST['evening_staff']) : null;
            $night_staff = !empty($_POST['night_staff']) ? $conn->real_escape_string($_POST['night_staff']) : null;
            $notes = $conn->real_escape_string($_POST['notes'] ?? '');
            // Determine created_by (must exist in users table)
            $created_by = $_SESSION['user_id'] ?? 1;
            $user_check = $conn->query("SELECT id FROM users WHERE id = $created_by");
            if (!$user_check || $user_check->num_rows === 0) {
                $created_by = 1; // fallback to admin
            }
            // Log all values
            error_log("Assessment Insert: patient_id=$patient_id, assessment_date=$assessment_date, patient_status=$patient_status, meal_plan_id=$meal_plan_id, assigned_doctor=$assigned_doctor, assigned_therapist=$assigned_therapist, morning_staff=$morning_staff, evening_staff=$evening_staff, night_staff=$night_staff, notes=$notes, created_by=$created_by");
            try {
                $meal_plan_sql = $meal_plan_id ? "'$meal_plan_id'" : "NULL";
                $assigned_doctor_sql = $assigned_doctor ? "'$assigned_doctor'" : "NULL";
                $assigned_therapist_sql = $assigned_therapist ? "'$assigned_therapist'" : "NULL";
                $morning_staff_sql = $morning_staff ? "'$morning_staff'" : "NULL";
                $evening_staff_sql = $evening_staff ? "'$evening_staff'" : "NULL";
                $night_staff_sql = $night_staff ? "'$night_staff'" : "NULL";
                $sql = "INSERT INTO patient_assessments (patient_id, assessment_date, patient_status, meal_plan_id, assigned_doctor, assigned_therapist, morning_staff, evening_staff, night_staff, notes, created_by) 
                        VALUES ('$patient_id', '$assessment_date', '$patient_status', $meal_plan_sql, $assigned_doctor_sql, $assigned_therapist_sql, $morning_staff_sql, $evening_staff_sql, $night_staff_sql, '$notes', $created_by)";
                if (!$conn->query($sql)) {
                    throw new Exception('MySQL error: ' . $conn->error);
                }
                $response['success'] = true;
                $response['message'] = 'Assessment added successfully';
                // After inserting into patient_assessments, always update the patients table status:
                $update_patient_status_sql = "UPDATE patients SET status = '$patient_status' WHERE patient_id = '$patient_id'";
                $conn->query($update_patient_status_sql);

                // NEW: Assign doctor in staff_patient_assignments if assigned_doctor is set
                if ($assigned_doctor) {
                    // Get patients.id from patient_id (string)
                    $pat_id_result = $conn->query("SELECT id FROM patients WHERE patient_id = '$patient_id' LIMIT 1");
                    $pat_row = $pat_id_result ? $pat_id_result->fetch_assoc() : null;
                    // Get users.id from staff.staff_id (assigned_doctor)
                    $doc_id_result = $conn->query("SELECT user_id FROM staff WHERE staff_id = '$assigned_doctor' LIMIT 1");
                    $doc_row = $doc_id_result ? $doc_id_result->fetch_assoc() : null;
                    if ($pat_row && $doc_row) {
                        $pat_id = $pat_row['id'];
                        $doc_user_id = $doc_row['user_id'];
                        // Insert or update assignment
                        $check = $conn->query("SELECT id FROM staff_patient_assignments WHERE staff_id = $doc_user_id AND patient_id = $pat_id");
                        if ($check && $check->num_rows == 0) {
                            $conn->query("INSERT INTO staff_patient_assignments (staff_id, patient_id, assignment_date, status) VALUES ($doc_user_id, $pat_id, CURDATE(), 'active')");
                        }
                    }
                }
                // Assign therapist in staff_patient_assignments if assigned_therapist is set
                if ($assigned_therapist) {
                    $pat_id_result = $conn->query("SELECT id FROM patients WHERE patient_id = '$patient_id' LIMIT 1");
                    $pat_row = $pat_id_result ? $pat_id_result->fetch_assoc() : null;
                    $therapist_id_result = $conn->query("SELECT user_id FROM staff WHERE staff_id = '$assigned_therapist' LIMIT 1");
                    $therapist_row = $therapist_id_result ? $therapist_id_result->fetch_assoc() : null;
                    if ($pat_row && $therapist_row) {
                        $pat_id = $pat_row['id'];
                        $therapist_user_id = $therapist_row['user_id'];
                        $check = $conn->query("SELECT id FROM staff_patient_assignments WHERE staff_id = $therapist_user_id AND patient_id = $pat_id");
                        if ($check && $check->num_rows == 0) {
                            $conn->query("INSERT INTO staff_patient_assignments (staff_id, patient_id, assignment_date, status) VALUES ($therapist_user_id, $pat_id, CURDATE(), 'active')");
                        }
                    }
                }
            } catch (Exception $e) {
                $response['success'] = false;
                $response['message'] = 'Error adding assessment: ' . $e->getMessage();
            }
            break;
            
        case 'get_assessments':
            $patient_id = $conn->real_escape_string($_POST['patient_id'] ?? '');
            $where = $patient_id ? "WHERE pa.patient_id = '$patient_id'" : '';
            $sql = "SELECT pa.*, p.full_name as patient_name, wmp.name as meal_plan_name 
                    FROM patient_assessments pa 
                    LEFT JOIN patients p ON pa.patient_id = p.patient_id 
                    LEFT JOIN weekly_meal_plans wmp ON pa.meal_plan_id = wmp.id 
                    $where 
                    ORDER BY pa.assessment_date DESC, pa.created_at DESC";
            
            $result = $conn->query($sql);
            if ($result) {
                $assessments = $result->fetch_all(MYSQLI_ASSOC);
                $response['success'] = true;
                $response['assessments'] = $assessments;
            } else {
                $response['message'] = 'Error fetching assessments: ' . $conn->error;
            }
            break;
            
        case 'get_assessment_data':
            // Get meal plans with type
            $meal_plans_result = $conn->query("SELECT wmp.id, wmp.name, mpt.name AS type_name FROM weekly_meal_plans wmp LEFT JOIN meal_plan_types mpt ON wmp.type_id = mpt.id WHERE wmp.status = 'active' ORDER BY wmp.name");
            $meal_plans = $meal_plans_result ? $meal_plans_result->fetch_all(MYSQLI_ASSOC) : [];
            
            // Get doctors
            $doctors_result = $conn->query("SELECT staff_id, full_name FROM staff WHERE role = 'doctor' AND status = 'active' ORDER BY full_name");
            $doctors = $doctors_result ? $doctors_result->fetch_all(MYSQLI_ASSOC) : [];
            
            // Get therapists
            $therapists_result = $conn->query("SELECT staff_id, full_name FROM staff WHERE role = 'therapist' AND status = 'active' ORDER BY full_name");
            $therapists = $therapists_result ? $therapists_result->fetch_all(MYSQLI_ASSOC) : [];
            
            // Get nurses filtered by shifts
            $morning_staff_result = $conn->query("SELECT staff_id, full_name, role FROM staff WHERE status = 'active' AND shift = 'Morning' AND role = 'nurse' ORDER BY full_name");
            $morning_staff = $morning_staff_result ? $morning_staff_result->fetch_all(MYSQLI_ASSOC) : [];
            
            $evening_staff_result = $conn->query("SELECT staff_id, full_name, role FROM staff WHERE status = 'active' AND shift = 'Afternoon' AND role = 'nurse' ORDER BY full_name");
            $evening_staff = $evening_staff_result ? $evening_staff_result->fetch_all(MYSQLI_ASSOC) : [];
            
            $night_staff_result = $conn->query("SELECT staff_id, full_name, role FROM staff WHERE status = 'active' AND shift = 'Night' AND role = 'nurse' ORDER BY full_name");
            $night_staff = $night_staff_result ? $night_staff_result->fetch_all(MYSQLI_ASSOC) : [];
            
            // Get patients (include status)
            $patients_result = $conn->query("SELECT p.patient_id, p.full_name, p.type, p.status FROM patients p WHERE p.status = 'admitted' ORDER BY p.full_name");
            $patients = $patients_result ? $patients_result->fetch_all(MYSQLI_ASSOC) : [];
            
            $response['success'] = true;
            $response['meal_plans'] = $meal_plans;
            $response['doctors'] = $doctors;
            $response['therapists'] = $therapists;
            $response['morning_staff'] = $morning_staff;
            $response['evening_staff'] = $evening_staff;
            $response['night_staff'] = $night_staff;
            $response['patients'] = $patients;
            break;
            
        case 'reset_patient_password':
            if (empty($_POST['patient_id'])) {
                $response['message'] = 'Patient ID is required';
                break;
            }
            $patient_id = $conn->real_escape_string($_POST['patient_id']);
            // Find user_id for this patient
            $result = $conn->query("SELECT user_id FROM patients WHERE patient_id = '$patient_id'");
            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $user_id = $row['user_id'];
                // Generate new password
                $new_password = generateSecurePassword(12);
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                // Update users table
                $update = $conn->query("UPDATE users SET password_hash = '$hashed_password', temp_password = '$new_password' WHERE id = $user_id");
                if ($update) {
                    $response['success'] = true;
                    $response['message'] = 'Password reset successfully';
                    $response['new_password'] = $new_password;
                } else {
                    $response['message'] = 'Error updating password: ' . $conn->error;
                }
            } else {
                $response['message'] = 'Patient not found';
            }
            break;
            
        default:
            $response['message'] = 'Invalid action';
    }
    
    } catch (Exception $e) {
        // Log the security or validation error
        $securityManager->logSecurityEvent('PATIENT_MANAGEMENT_ERROR', [
            'action' => $action ?? 'unknown',
            'error' => $e->getMessage(),
            'user_id' => $_SESSION['user_id'] ?? null
        ]);
        
        $response['success'] = false;
        $response['message'] = $e->getMessage();
    }
    
    // Send JSON response for AJAX requests
    header('Content-Type: application/json');
    if (!isset($response)) $response = ['success' => false, 'message' => 'No response set'];
    // Clean output buffer before sending JSON
    if (ob_get_level()) ob_end_clean();
    echo json_encode($response);
    exit;
}

// Get initial data for the page
$stats_query = "SELECT 
    COUNT(*) as total_patients,
    SUM(CASE WHEN status = 'admitted' THEN 1 ELSE 0 END) as active_patients,
    SUM(CASE WHEN status = 'discharged' THEN 1 ELSE 0 END) as discharged_patients,
    SUM(CASE WHEN status = 'transferred' THEN 1 ELSE 0 END) as transferred_patients
    FROM patients";

$stats_result = $conn->query($stats_query);
$stats = $stats_result ? $stats_result->fetch_assoc() : ['total_patients' => 0, 'active_patients' => 0, 'discharged_patients' => 0, 'transferred_patients' => 0];

// Get recent patients
$recent_patients_query = "SELECT p.*, u.username, u.first_name, u.last_name, u.contact_number, u.emergency_contact, u.date_of_birth, u.address 
                         FROM patients p 
                         JOIN users u ON p.user_id = u.id 
                         ORDER BY p.admission_date DESC LIMIT 5";
$recent_patients_result = $conn->query($recent_patients_query);
$recent_patients = $recent_patients_result ? $recent_patients_result->fetch_all(MYSQLI_ASSOC) : [];

// Get available rooms
$rooms_query = "SELECT * FROM rooms WHERE status = 'available' AND for_whom = 'Patients'";
$rooms_result = $conn->query($rooms_query);
$available_rooms = $rooms_result ? $rooms_result->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>United Medical Asylum & Rehab Facility</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            animation: slideDown 0.8s ease-out;
        }

        .header h1 {
            color: #2c3e50;
            font-size: 2.5rem;
            margin-bottom: 10px;
            text-align: center;
        }

        .header p {
            text-align: center;
            color: #7f8c8d;
            font-size: 1.1rem;
        }

        .nav-tabs {
            display: flex;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 15px;
            padding: 10px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            animation: slideUp 0.8s ease-out 0.2s both;
        }

        .nav-tab {
            flex: 1;
            padding: 15px 20px;
            text-align: center;
            background: transparent;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 1rem;
            font-weight: 600;
            color: #7f8c8d;
        }

        .nav-tab.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .nav-tab:hover:not(.active) {
            background: rgba(102, 126, 234, 0.1);
            transform: translateY(-1px);
        }

        .tab-content {
            display: none;
            animation: fadeIn 0.5s ease-in;
        }

        .tab-content.active {
            display: block;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 30px;
            text-align: center;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            animation: slideUp 0.8s ease-out;
        }

        .stat-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
        }

        .stat-card i {
            font-size: 3rem;
            margin-bottom: 15px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .stat-card h3 {
            font-size: 2.5rem;
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .stat-card p {
            color: #7f8c8d;
            font-size: 1.1rem;
        }

        .card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            animation: slideUp 0.8s ease-out;
        }

        .card h2 {
            color: #2c3e50;
            margin-bottom: 25px;
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 12px 15px;
            border: 2px solid #e0e6ed;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.8);
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            transform: translateY(-1px);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            justify-content: center;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }

        .btn-secondary {
            background: #95a5a6;
            color: white;
        }

        .btn-secondary:hover {
            background: #7f8c8d;
            transform: translateY(-2px);
        }

        .btn-success {
            background: #27ae60;
            color: white;
        }

        .btn-success:hover {
            background: #229954;
            transform: translateY(-2px);
        }

        .btn-warning {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            color: #155724;
            border: none;
            font-weight: 700;
            box-shadow: 0 4px 16px rgba(67, 233, 123, 0.15);
            transition: all 0.3s ease;
        }
        .btn-warning:hover {
            background: linear-gradient(135deg, #38f9d7 0%, #43e97b 100%);
            color: #fff;
            transform: translateY(-2px) scale(1.04);
            box-shadow: 0 8px 24px rgba(67, 233, 123, 0.25);
        }

        .patients-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
        }

        .patient-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            cursor: pointer;
            animation: slideUp 0.8s ease-out;
        }

        .patient-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }

        .patient-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .patient-name {
            font-size: 1.3rem;
            font-weight: 700;
            color: #2c3e50;
        }

        .patient-type {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .type-asylum {
            background: #e8f5e8;
            color: #27ae60;
        }

        .type-rehab {
            background: #fff3cd;
            color: #856404;
        }

        .patient-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 15px;
        }

        .patient-info div {
            font-size: 0.9rem;
            color: #7f8c8d;
        }

        .patient-info strong {
            color: #2c3e50;
        }

        .patient-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
            animation: fadeIn 0.3s ease;
        }

        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 20px;
            width: 90%;
            max-width: 800px;
            max-height: 80vh;
            overflow-y: auto;
            animation: slideUp 0.3s ease;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e0e6ed;
        }

        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .close:hover {
            color: #000;
        }

        .progress-bar {
            width: 100%;
            height: 10px;
            background: #e0e6ed;
            border-radius: 5px;
            overflow: hidden;
            margin: 10px 0;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 5px;
            transition: width 0.3s ease;
        }

        .timeline {
            position: relative;
            padding-left: 30px;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: linear-gradient(135deg, #667eea, #764ba2);
        }

        .timeline-item {
            position: relative;
            margin-bottom: 25px;
            background: rgba(255, 255, 255, 0.8);
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: -37px;
            top: 20px;
            width: 12px;
            height: 12px;
            background: #667eea;
            border-radius: 50%;
            border: 3px solid white;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            animation: slideDown 0.5s ease;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

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

        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            .header h1 {
                font-size: 2rem;
            }
            
            .nav-tabs {
                flex-direction: column;
                gap: 10px;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .patients-grid {
                grid-template-columns: 1fr;
            }
            
            .modal-content {
                margin: 10% auto;
                width: 95%;
            }
        }

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

        .hidden {
            display: none !important;
        }

        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }

        .slide-in {
            animation: slideUp 0.5s ease-out;
        }

        .stat-discharged {
            background: linear-gradient(135deg, #f8ffae 0%, #43c6ac 100%);
            color: #155724;
            box-shadow: 0 10px 30px rgba(67, 198, 172, 0.15);
            border: 2px solid #43c6ac;
        }
        .stat-discharged i {
            color: #43c6ac;
            background: none;
            font-size: 3.5rem;
        }
        .stat-transferred {
            background: linear-gradient(135deg, #e0c3fc 0%, #8ec5fc 100%);
            color: #2c3e50;
            box-shadow: 0 10px 30px rgba(142, 197, 252, 0.15);
            border: 2px solid #8ec5fc;
        }
        .stat-transferred i {
            color: #8ec5fc;
            background: none;
            font-size: 3.5rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
                <a href="<?php 
                    $user_role = $_SESSION['role'] ?? 'admin';
                    switch($user_role) {
                        case 'admin':
                            echo 'admin_dashboard.php';
                            break;
                        case 'chief-staff':
                            echo 'chief_staff_dashboard.php';
                            break;
                        case 'doctor':
                            echo 'doctor_dashboard.php';
                            break;
                        case 'nurse':
                            echo 'staff_dashboard.php';
                            break;
                        case 'receptionist':
                            echo 'receptionist_dashboard.php';
                            break;
                        case 'therapist':
                            echo 'therapist_dashboard.php';
                            break;
                        default:
                            echo 'admin_dashboard.php';
                    }
                ?>" class="btn btn-secondary" style="text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
            <h1><i class="fas fa-hospital"></i> United Medical Asylum & Rehab Facility</h1>
            <p>Patient Management System</p>
        </div>

        <div class="nav-tabs">
            <button class="nav-tab active" onclick="showTab('dashboard', event)">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </button>
            <button class="nav-tab" onclick="showTab('patients', event)">
                <i class="fas fa-users"></i> Patients
            </button>
            <button class="nav-tab" onclick="showTab('add-patient', event)">
                <i class="fas fa-user-plus"></i> Add Patient
            </button>
            <button class="nav-tab" onclick="showTab('appointments', event)">
                <i class="fas fa-calendar-alt"></i> Appointments
            </button>
            <button class="nav-tab" onclick="showTab('assessment', event)">
                <i class="fas fa-clipboard-check"></i> Assessment
            </button>
        </div>

        <!-- Dashboard Tab -->
        <div id="dashboard" class="tab-content active">
            <div class="dashboard-grid">
                <div class="stat-card">
                    <i class="fas fa-users"></i>
                    <h3 id="total-patients">0</h3>
                    <p>Total Patients</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-home"></i>
                    <h3 id="asylum-patients">0</h3>
                    <p>Asylum Patients</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-heartbeat"></i>
                    <h3 id="rehab-patients">0</h3>
                    <p>Rehab Patients</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-calendar-check"></i>
                    <h3 id="appointments-today">0</h3>
                    <p>Appointments Today</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-user-check"></i>
                    <h3 id="discharged-patients">0</h3>
                    <p>Discharged Patients</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-exchange-alt"></i>
                    <h3 id="transferred-patients">0</h3>
                    <p>Transferred Patients</p>
                </div>
            </div>

            <div class="card">
                <h2><i class="fas fa-chart-line"></i> Recent Activity</h2>
                <div class="timeline" id="recent-activity">
                    <!-- Recent activity will be populated here -->
                </div>
            </div>
        </div>

        <!-- Patients Tab -->
        <div id="patients" class="tab-content">
            <div class="card">
                <h2><i class="fas fa-users"></i> All Patients</h2>
                <div class="patients-grid" id="patients-grid">
                    <!-- Patients will be populated here -->
                </div>
            </div>
        </div>

        <!-- Add Patient Tab -->
        <div id="add-patient" class="tab-content">
            <div class="card">
                <h2><i class="fas fa-user-plus"></i> Add New Patient</h2>
                <form id="add-patient-form">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="patient-name">Full Name *</label>
                            <input type="text" id="patient-name" name="name" required>
                        </div>
                        <div class="form-group">
                            <label for="patient-dob">Date of Birth *</label>
                            <input type="date" id="patient-dob" name="dob" required>
                        </div>
                        <div class="form-group">
                            <label for="patient-gender">Gender *</label>
                            <select id="patient-gender" name="gender" required>
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="admission-date">Admission Date *</label>
                            <input type="date" id="admission-date" name="admission_date" required>
                        </div>
                        <div class="form-group">
                            <label for="room-number">Room Number *</label>
                            <select id="room-number" name="room" required>
                                <option value="">Select Room</option>
                                <?php foreach ($available_rooms as $room): ?>
                                    <option value="<?php echo htmlspecialchars($room['room_number']); ?>">
                                        Room <?php echo htmlspecialchars($room['room_number']); ?> 
                                        (<?php echo htmlspecialchars($room['type']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="emergency-contact">Emergency Contact *</label>
                            <input type="text" id="emergency-contact" name="emergency_contact" required>
                        </div>
                        <div class="form-group">
                            <label for="patient-type">Patient Type *</label>
                            <select id="patient-type" name="type" required>
                                <option value="">Select Type</option>
                                <option value="Asylum">Asylum</option>
                                <option value="Rehabilitation">Rehabilitation</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="mobility-status">Mobility Status *</label>
                            <select id="mobility-status" name="mobility_status" required>
                                <option value="">Select Status</option>
                                <option value="Independent">Independent</option>
                                <option value="Assisted">Assisted</option>
                                <option value="Wheelchair">Wheelchair</option>
                                <option value="Bedridden">Bedridden</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="medical-history">Medical History</label>
                        <textarea id="medical-history" name="medical_history" placeholder="Previous medical conditions, surgeries, etc."></textarea>
                    </div>

                    <div class="form-group">
                        <label for="current-medications">Current Medications</label>
                        <textarea id="current-medications" name="current_medications" placeholder="List current medications and dosages"></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add Patient
                    </button>
                </form>
            </div>
        </div>

        <!-- Appointments Tab -->
        <div id="appointments" class="tab-content">
            <div class="card">
                <h2><i class="fas fa-calendar-alt"></i> Appointments Management</h2>
                <div id="appointments-list">
                    <!-- Appointments will be populated here -->
                </div>
            </div>
        </div>

        <!-- Assessment Tab -->
        <div id="assessment" class="tab-content">
            <div class="card">
                <h2><i class="fas fa-clipboard-check"></i> Patient Assessment</h2>
                
                <!-- Assessment Form -->
                <form id="assessment-form">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="assessment-patient">Select Patient *</label>
                            <select id="assessment-patient" name="patient_id" required>
                                <option value="">Select Patient</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="assessment-date">Assessment Date *</label>
                            <input type="date" id="assessment-date" name="assessment_date" required>
                        </div>
                        <div class="form-group">
                            <label for="patient-status">Patient Status *</label>
                            <select id="patient-status" name="patient_status" required>
                                <option value="">Select Status</option>
                                <option value="admitted">Admitted</option>
                                <option value="active">Active</option>
                                <option value="discharged">Discharged</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="meal-plan">Meal Plan</label>
                            <select id="meal-plan" name="meal_plan_id">
                                <option value="">Select Meal Plan</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group" id="doctor-container" style="display: none;">
                            <label for="assigned-doctor">Assigned Doctor</label>
                            <select id="assigned-doctor" name="assigned_doctor">
                                <option value="">Select Doctor</option>
                            </select>
                        </div>
                        <div class="form-group" id="therapist-container" style="display: none;">
                            <label for="assigned-therapist">Assigned Therapist</label>
                            <select id="assigned-therapist" name="assigned_therapist">
                                <option value="">Select Therapist</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="morning-staff">Morning Shift Staff</label>
                            <select id="morning-staff" name="morning_staff">
                                <option value="">Select Staff</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="evening-staff">Evening Shift Staff</label>
                            <select id="evening-staff" name="evening_staff">
                                <option value="">Select Staff</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="night-staff">Night Shift Staff</label>
                            <select id="night-staff" name="night_staff">
                                <option value="">Select Staff</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="assessment-notes">Assessment Notes</label>
                        <textarea id="assessment-notes" name="notes" placeholder="Enter assessment notes, observations, and recommendations..."></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Assessment
                    </button>
                </form>

                <!-- Assessment History -->
                <div class="assessment-history" style="margin-top: 30px;">
                    <h3><i class="fas fa-history"></i> Assessment History</h3>
                    <div id="assessment-history-list">
                        <!-- Assessment history will be populated here -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Patient Details Modal -->
    <div id="patient-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modal-patient-name">Patient Details</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div id="modal-patient-content">
                <!-- Patient details will be populated here -->
            </div>
            <div style="margin-top: 20px; display: flex; gap: 10px;">
                <button class="btn btn-danger" id="dischargeBtn" style="display:none;">Discharge Patient</button>
                <button class="btn btn-info" id="viewHistoryBtn">View Treatment History</button>
                <button class="btn btn-secondary" id="viewCredentialBtn">View Credential</button>
            </div>
        </div>
    </div>

    <!-- Treatment History Modal -->
    <div id="treatment-history-modal" class="modal" style="display:none;">
        <div class="modal-content" style="max-width:800px;width:90vw;">
            <div class="modal-header">
                <h2>Treatment History</h2>
                <span class="close" onclick="closeTreatmentHistoryModal()">&times;</span>
            </div>
            <div id="treatment-history-content" style="max-height:60vh;overflow-y:auto;"></div>
        </div>
    </div>

    <!-- Add Appointment Modal -->
    <div id="appointment-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add Appointment</h2>
                <span class="close" onclick="closeAppointmentModal()">&times;</span>
            </div>
            <form id="add-appointment-form">
                <input type="hidden" id="appointment-patient-id" name="patient_id">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="appointment-date">Date *</label>
                        <input type="date" id="appointment-date" name="date" required>
                    </div>
                    <div class="form-group">
                        <label for="appointment-time">Time *</label>
                        <input type="time" id="appointment-time" name="time" required>
                    </div>
                    <div class="form-group">
                        <label for="appointment-type">Type *</label>
                        <select id="appointment-type" name="type" required>
                            <option value="">Select Type</option>
                            <option value="Therapy">Therapy</option>
                            <option value="Medication Review">Medication Review</option>
                            <option value="Consultation">Consultation</option>
                            <option value="Group Session">Group Session</option>
                            <option value="Family Meeting">Family Meeting</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="appointment-doctor">Doctor *</label>
                        <select id="appointment-doctor" name="doctor" required style="display:none;"></select>
                        <input type="text" id="appointment-doctor-text" name="doctor_text" style="display:none;" disabled>
                    </div>
                    <div class="form-group" id="appointment-therapist-group" style="display:none;">
                        <label for="appointment-therapist">Therapist *</label>
                        <select id="appointment-therapist" name="therapist">
                            <option value="">Select Therapist</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="appointment-status">Status *</label>
                        <select id="appointment-status" name="status" required>
                            <option value="scheduled">Scheduled</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-calendar-plus"></i> Add Appointment
                </button>
            </form>
        </div>
    </div>

    <!-- Add Progress Note Modal -->
    <div id="progress-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add Progress Note</h2>
                <span class="close" onclick="closeProgressModal()">&times;</span>
            </div>
            <form id="add-progress-form">
                <input type="hidden" id="progress-patient-id" name="patient_id">
                <div class="form-group">
                    <label for="progress-note">Progress Note *</label>
                    <textarea id="progress-note" name="note" required placeholder="Enter progress note..."></textarea>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-notes-medical"></i> Add Note
                </button>
            </form>
        </div>
    </div>

    <!-- Credential Modal -->
    <div id="credential-modal" class="modal" style="display:none;">
        <div class="modal-content" style="max-width:400px;width:90vw;">
            <div class="modal-header">
                <h2>Patient Credential</h2>
                <span class="close" onclick="closeCredentialModal()">&times;</span>
            </div>
            <div id="credential-content" style="padding:20px;"></div>
        </div>
    </div>

    <script>
        function showAlert(message, type = 'info') {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type}`;
            alertDiv.textContent = message;
            document.body.insertBefore(alertDiv, document.body.firstChild);
            setTimeout(() => {
                alertDiv.remove();
            }, 5000);
        }

        let patients = [];
        let currentPatient = null;

        // Initialize the application
        document.addEventListener('DOMContentLoaded', function() {
            loadPatientsAndDashboard();
            // ... other init code ...
            document.getElementById('admission-date').value = new Date().toISOString().split('T')[0];
            document.getElementById('assessment-date').value = new Date().toISOString().split('T')[0];
            document.getElementById('patient-dob').addEventListener('change', function() {
                const dob = new Date(this.value);
                const today = new Date();
                let age = today.getFullYear() - dob.getFullYear();
                const monthDiff = today.getMonth() - dob.getMonth();
                if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
                    age--;
                }
                const ageDisplay = document.getElementById('age-display');
                if (ageDisplay) {
                    ageDisplay.textContent = age + ' years';
                }
            });
            // ... other code ...
        });

        async function loadPatientsAndDashboard() {
            await loadPatients();
            updateDashboard();
        }

        // Tab navigation
        function showTab(tabName, event) {
            // Hide all tab contents
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(tab => {
                tab.classList.remove('active');
            });
            // Remove active class from all nav tabs
            const navTabs = document.querySelectorAll('.nav-tab');
            navTabs.forEach(tab => {
                tab.classList.remove('active');
            });
            // Show selected tab content
            document.getElementById(tabName).classList.add('active');
            // Add active class to clicked nav tab if event is present
            if (event && event.target) {
                event.target.classList.add('active');
            }
            // Load specific tab data
            if (tabName === 'patients') {
                displayPatients();
            } else if (tabName === 'appointments') {
                displayAppointments();
            } else if (tabName === 'assessment') {
                loadAssessmentData();
                displayAssessments();
            }
        }

        // Load patients from server
        async function loadPatients() {
            try {
                const response = await fetch('patient_management.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=get_patients'
                });
                
                const data = await response.json();
                if (data.success) {
                    patients = data.patients;
                    displayPatients();
                } else {
                    showAlert(data.message, 'error');
                }
            } catch (error) {
                showAlert('Error loading patients: ' + error.message, 'error');
            }
        }

        // Display patients in the grid
        function displayPatients() {
            const patientsGrid = document.getElementById('patients-grid');
            patientsGrid.innerHTML = '';
            patients.forEach(patient => {
                // Calculate age from DOB
                let age = '';
                if (patient.date_of_birth) {
                    const dob = new Date(patient.date_of_birth);
                    const today = new Date();
                    age = today.getFullYear() - dob.getFullYear();
                    const monthDiff = today.getMonth() - dob.getMonth();
                    
                    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
                        age--;
                    }
                } else {
                    age = 'N/A';
                }
                patient.age = age; // Add age to patient object
                // Type badge
                let typeClass = '';
                let typeLabel = '';
                if (patient.type && patient.type.toLowerCase() === 'asylum') {
                    typeClass = 'type-asylum';
                    typeLabel = 'ASYLUM';
                } else if (patient.type && patient.type.toLowerCase().startsWith('rehab')) {
                    typeClass = 'type-rehab';
                    typeLabel = 'REHAB';
                } else {
                    typeClass = 'type-asylum';
                    typeLabel = patient.type || 'ASYLUM';
                }
                // Card HTML
                const card = document.createElement('div');
                card.className = 'patient-card';
                card.innerHTML = `
                    <div class="patient-header">
                        <div class="patient-name">${patient.full_name || patient.name || ''}</div>
                        <div class="patient-type ${typeClass}">${typeLabel}</div>
                    </div>
                    <div class="patient-info">
                        <div><strong>Age:</strong> ${age}</div>
                        <div><strong>Gender:</strong> ${patient.gender || 'N/A'}</div>
                        <div><strong>Room:</strong> ${patient.room_number || patient.room || 'N/A'}</div>
                        <div><strong>${patient.type === 'Rehabilitation' ? 'Therapist' : 'Doctor'}:</strong> ${patient.type === 'Rehabilitation' ? (patient.assigned_therapist_name || 'N/A') : (patient.assigned_doctor_name || 'N/A')}</div>
                        <div><strong>Condition:</strong> ${patient.condition || ''}</div>
                        <div><strong>Status:</strong> ${patient.status || 'N/A'}</div>
                    </div>
                    <div class="patient-actions">
                        <button class="btn btn-primary" onclick="event.stopPropagation(); showPatientDetails(${JSON.stringify(patient).replace(/"/g, '&quot;')})">
                            <i class="fas fa-eye"></i> View Details
                        </button>
                        <button class="btn btn-secondary" onclick="event.stopPropagation(); addAppointment('${patient.patient_id || patient.id}')">
                            <i class="fas fa-calendar"></i> Appointment
                        </button>
                    </div>
                `;
                patientsGrid.appendChild(card);
            });
        }

        // Handle form submission
        document.getElementById('add-patient-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'add_patient');
            const selectedRoom = document.getElementById('room-number').value;
            try {
                const response = await fetch('patient_management.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                if (data.success) {
                    // Set room to occupied in the background
                    if (selectedRoom) updateRoomStatus(selectedRoom, 'occupied');
                    showAlert('Patient added successfully!', 'success');
                    
                    // Reload the page and redirect to patients tab
                    setTimeout(() => {
                        window.location.href = 'patient_management.php?tab=patients';
                    }, 1500);
                    
                } else {
                    showAlert(data.message, 'error');
                }
            } catch (error) {
                showAlert('Error adding patient: ' + error.message, 'error');
            }
        });

        // Reload available rooms dropdown
        async function reloadAvailableRooms() {
            try {
                const response = await fetch('patient_management.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=get_available_rooms'
                });
                const data = await response.json();
                if (data.success && data.rooms) {
                    const roomSelect = document.getElementById('room-number');
                    const currentValue = roomSelect.value;
                    roomSelect.innerHTML = '<option value="">Select Room</option>';
                    data.rooms.forEach(room => {
                        const opt = document.createElement('option');
                        opt.value = room.room_number;
                        opt.textContent = `Room ${room.room_number} (${room.type})`;
                        if (room.room_number === currentValue) opt.selected = true;
                        roomSelect.appendChild(opt);
                    });
                }
            } catch (error) {
                // Ignore errors
            }
        }

        // Delete patient logic (set room to available)
        async function deletePatient(patient) {
            if (!confirm('Are you sure you want to delete this patient?')) return;
            try {
                const formData = new FormData();
                formData.append('action', 'delete_patient');
                formData.append('patient_id', patient.patient_id);
                const response = await fetch('patient_management.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                if (data.success) {
                    showAlert('Patient deleted successfully!', 'success');
                    // Set room to available
                    if (patient.room_number) await updateRoomStatus(patient.room_number, 'available');
                    loadPatients();
                    reloadAvailableRooms();
                } else {
                    showAlert(data.message, 'error');
                }
            } catch (error) {
                showAlert('Error deleting patient: ' + error.message, 'error');
            }
        }

        // Update room status
        async function updateRoomStatus(roomNumber, status) {
            try {
                const response = await fetch('room_management.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=update_status&room_number=${roomNumber}&status=${status}`
                });
                
                const data = await response.json();
                if (!data.success) {
                    showAlert('Error updating room status: ' + data.message, 'error');
                }
            } catch (error) {
                showAlert('Error updating room status: ' + error.message, 'error');
            }
        }

        // Update dashboard statistics
        async function updateDashboard() {
            try {
                const response = await fetch('patient_management.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=get_patient_stats'
                });
                
                const data = await response.json();
                if (data.success) {
                    const stats = data.stats;
                    
                    // Update total patients
                    document.getElementById('total-patients').textContent = stats.total_patients || 0;
                    
                    // Count asylum and rehab patients from the patients array
                    const asylumCount = patients.filter(p => p.type && p.type.toLowerCase() === 'asylum').length;
                    const rehabCount = patients.filter(p => p.type && p.type.toLowerCase().startsWith('rehab')).length;
                    
                    // Update asylum and rehab counts
                    document.getElementById('asylum-patients').textContent = asylumCount;
                    document.getElementById('rehab-patients').textContent = rehabCount;
                    
                    // Count today's appointments
                    const today = new Date().toISOString().split('T')[0];
                    const todayAppointments = patients.reduce((count, patient) => {
                        if (patient.appointments) {
                            return count + patient.appointments.filter(apt => apt.date === today).length;
                        }
                        return count;
                    }, 0);
                    document.getElementById('appointments-today').textContent = todayAppointments;
                    
                    // Update discharged and transferred counts
                    document.getElementById('discharged-patients').textContent = stats.discharged_patients || 0;
                    document.getElementById('transferred-patients').textContent = stats.transferred_patients || 0;
                    
                    // Update recent activity
                    updateRecentActivity();
                }
            } catch (error) {
                console.error('Error updating dashboard:', error);
                showAlert('Error updating dashboard: ' + error.message, 'error');
            }
        }

        function updateRecentActivity() {
            const recentActivity = document.getElementById('recent-activity');
            let activities = [];
            
            // Collect recent activities from all patients
            patients.forEach(patient => {
                // Add patient admission as an activity
                if (patient.admission_date) {
                    activities.push({
                        date: patient.admission_date,
                        text: `New patient admitted: ${patient.full_name || patient.name} (${patient.type})`,
                        type: 'admission'
                    });
                }
                
                // Add progress notes
                if (patient.progress_notes && patient.progress_notes.length > 0) {
                    patient.progress_notes.forEach(note => {
                        activities.push({
                            date: note.date,
                            text: `Progress note for ${patient.full_name || patient.name}: ${note.note.substring(0, 50)}...`,
                            type: 'note'
                        });
                    });
                }
                
                // Add appointments
                if (patient.appointments && patient.appointments.length > 0) {
                    patient.appointments.forEach(apt => {
                        if (new Date(apt.date) >= new Date()) {  // Only show future appointments
                            activities.push({
                                date: apt.date,
                                text: `Upcoming appointment: ${patient.full_name || patient.name} - ${apt.type} with Dr. ${apt.doctor}`,
                                type: 'appointment'
                            });
                        }
                    });
                }
            });
            
            // Sort by date (most recent first)
            activities.sort((a, b) => new Date(b.date) - new Date(a.date));
            
            // Get only the 5 most recent activities
            const recentActivities = activities.slice(0, 5);
            
            if (recentActivities.length === 0) {
                recentActivity.innerHTML = '<p style="text-align: center; color: #666;">No recent activity to display.</p>';
                return;
            }
            
            let activityHTML = '';
            recentActivities.forEach(activity => {
                const date = new Date(activity.date).toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric'
                });
                
                let icon = 'fa-calendar';
                if (activity.type === 'note') icon = 'fa-notes-medical';
                if (activity.type === 'admission') icon = 'fa-user-plus';
                
                activityHTML += `
                    <div class="timeline-item">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <i class="fas ${icon}" style="color: #667eea;"></i>
                            <div>
                                <strong>${date}</strong>
                                <p style="margin: 5px 0 0 0;">${activity.text}</p>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            recentActivity.innerHTML = activityHTML;
        }

        // Show patient details modal
        function showPatientDetails(patient) {
            // Always get the latest patient object by id
            let freshPatient = null;
            if (patient && patient.id) {
                freshPatient = patients.find(p => p.id == patient.id);
            } else if (patient && patient.patient_id) {
                freshPatient = patients.find(p => p.patient_id == patient.patient_id);
            }
            if (!freshPatient) {
                showAlert('Patient data not found. Please reload the page.', 'error');
                return;
            }
            console.log('Opening patient details modal for:', freshPatient);
            currentPatient = freshPatient;
            const modal = document.getElementById('patient-modal');
            const modalName = document.getElementById('modal-patient-name');
            const modalContent = document.getElementById('modal-patient-content');
            modalName.textContent = freshPatient.full_name || freshPatient.name || '';
            let assignedLabel = '';
            let assignedValue = '';
            if (freshPatient.type && freshPatient.type.toLowerCase() === 'asylum') {
                assignedLabel = 'Assigned Doctor';
                assignedValue = freshPatient.assigned_doctor_name || 'N/A';
            } else if (freshPatient.type && freshPatient.type.toLowerCase().startsWith('rehab')) {
                assignedLabel = 'Assigned Therapist';
                assignedValue = freshPatient.assigned_therapist_name || 'N/A';
            }
            let detailsHTML = `
                <div class="form-grid">
                    <div><strong>Age:</strong> ${freshPatient.age || ''}</div>
                    <div><strong>Gender:</strong> ${freshPatient.gender || ''}</div>
                    <div><strong>Room:</strong> ${freshPatient.room_number || freshPatient.room || ''}</div>
                    <div><strong>Admission Date:</strong> ${freshPatient.admission_date || ''}</div>
                    <div><strong>Type:</strong> ${freshPatient.type || ''}</div>
                    <div><strong>Status:</strong> ${freshPatient.status || ''}</div>
                    <div><strong>Condition:</strong> ${freshPatient.condition || ''}</div>
                    <div><strong>${assignedLabel}:</strong> ${assignedValue}</div>
                    <div><strong>Emergency Contact:</strong> ${freshPatient.emergency_contact || ''}</div>
                </div>
                <h3><i class="fas fa-history"></i> Medical History</h3>
                <p>${freshPatient.medical_history || 'No medical history recorded.'}</p>
                <h3><i class="fas fa-pills"></i> Current Medications</h3>
                <p>${freshPatient.current_medications || 'No medications recorded.'}</p>
            `;
            if (freshPatient.appointments && freshPatient.appointments.length > 0) {
                detailsHTML += `
                    <h3><i class="fas fa-calendar-alt"></i> Upcoming Appointments</h3>
                    <div class="timeline">
                `;
                freshPatient.appointments.forEach(appointment => {
                    detailsHTML += `
                        <div class="timeline-item">
                            <strong>${appointment.date} at ${appointment.time}</strong>
                            <br>${appointment.type} with ${appointment.doctor}
                        </div>
                    `;
                });
                detailsHTML += `</div>`;
            }
            if (freshPatient.progress_notes && freshPatient.progress_notes.length > 0) {
                detailsHTML += `
                    <h3><i class="fas fa-notes-medical"></i> Progress Notes</h3>
                    <div class="timeline">
                `;
                freshPatient.progress_notes.forEach(note => {
                    detailsHTML += `
                        <div class="timeline-item">
                            <strong>${note.date}</strong>
                            <br>${note.note}
                        </div>
                    `;
                });
                detailsHTML += `</div>`;
            }
            detailsHTML += `
                <div style="margin-top: 30px; display: flex; gap: 15px;">
                    <button class="btn btn-primary" onclick="addAppointment('${freshPatient.patient_id || freshPatient.id}')">
                        <i class="fas fa-calendar-plus"></i> Add Appointment
                    </button>
                </div>
            `;
            modalContent.innerHTML = detailsHTML;
            modal.style.display = 'block';
            // Show or hide the Discharge button
            const dischargeBtn = document.getElementById('dischargeBtn');
            if (freshPatient.status !== 'discharged') {
                dischargeBtn.style.display = '';
                dischargeBtn.onclick = function() {
                    dischargePatient(freshPatient.patient_id);
                };
            } else {
                dischargeBtn.style.display = 'none';
                dischargeBtn.onclick = null;
            }
            document.getElementById('viewHistoryBtn').onclick = function() {
                document.getElementById('treatment-history-modal').style.display = 'block';
                fetchTreatmentHistory(freshPatient.id);
            };
            const viewCredentialBtn = document.getElementById('viewCredentialBtn');
            viewCredentialBtn.onclick = function() {
                // Use patient email and password if available
                const email = freshPatient.email || freshPatient.username + '@patients.local' || 'N/A';
                // Always use temp_password if available
                const password = freshPatient.temp_password || freshPatient.password || 'Not available';
                let html = `<div><strong>Email:</strong> ${email}</div>`;
                html += `<div><strong>Password:</strong> <span id="copyPass">${password}</span> <button class="copy-btn" onclick="copyToClipboard('copyPass')"><i class='fas fa-copy'></i> Copy</button></div>`;
                html += `<div style='margin-top:1rem;color:#b85c00;font-size:0.95rem;'><i class='fas fa-exclamation-triangle'></i> Password is only available for new patients or after a reset. Please save it securely.</div>`;
                document.getElementById('credential-content').innerHTML = html;
                document.getElementById('credential-modal').style.display = 'block';
            };
        }

        function addAppointment(patientId) {
            document.getElementById('appointment-patient-id').value = patientId;
            document.getElementById('appointment-modal').style.display = 'block';
            closeModal();
        }

        function closeModal() {
            document.getElementById('patient-modal').style.display = 'none';
        }

        function closeAppointmentModal() {
            document.getElementById('appointment-modal').style.display = 'none';
        }

        function closeProgressModal() {
            document.getElementById('progress-modal').style.display = 'none';
        }

        // Discharge patient function
        async function dischargePatient(patientId) {
            if (!confirm('Are you sure you want to discharge this patient? This action cannot be undone.')) return;
            try {
                const formData = new FormData();
                formData.append('action', 'discharge_patient');
                formData.append('patient_id', patientId);
                const response = await fetch('patient_management.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                if (data.success) {
                    showAlert('Patient discharged successfully!', 'success');
                    closeModal();
                    loadPatients();
                    reloadAvailableRooms();
                    updateDashboard();
                } else {
                    showAlert(data.message, 'error');
                }
            } catch (error) {
                showAlert('Error discharging patient: ' + error.message, 'error');
            }
        }

        document.getElementById('add-appointment-form').addEventListener('submit', async function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            formData.append('action', 'add_appointment');

            try {
                const response = await fetch('patient_management.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();
                if (data.success) {
                    showAlert('Appointment added successfully!', 'success');
                    this.reset();
                    closeAppointmentModal();
                    displayAppointments();
                    updateDashboard();
                } else {
                    showAlert(data.message, 'error');
                }
            } catch (error) {
                showAlert('Error adding appointment: ' + error.message, 'error');
            }
        });

        async function displayAppointments() {
            try {
                const response = await fetch('patient_management.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=get_appointments'
                });

                const data = await response.json();
                const appointmentsList = document.getElementById('appointments-list');
                if (data.success && data.appointments) {
                    if (data.appointments.length === 0) {
                        appointmentsList.innerHTML = '<p>No appointments found.</p>';
                        return;
                    }
                    let html = '';
                    data.appointments.forEach(apt => {
                        html += `<div class="appointment-card" style="background:#fff;border-radius:16px;box-shadow:0 4px 16px rgba(67,97,238,0.07);padding:24px 24px 20px 24px;margin-bottom:24px;display:flex;justify-content:space-between;align-items:center;">
                            <div>
                                <div style="font-weight:700;font-size:1.1rem;margin-bottom:6px;">${apt.date} at ${apt.time}</div>
                                <div style="margin-bottom:4px;"><strong>Patient:</strong> ${apt.patient_name || apt.patient_id || ''}</div>
                                <div style="margin-bottom:4px;"><strong>Type:</strong> ${apt.type}</div>
                                <div style="margin-bottom:4px;">
                                    <strong>${apt.type === 'Rehabilitation' ? 'Therapist' : 'Doctor'}:</strong> 
                                    ${apt.type === 'Rehabilitation' ? (apt.assigned_therapist_name || apt.therapist || '') : (apt.assigned_doctor_name || apt.doctor || '')}
                                </div>
                                <div style="margin-bottom:4px;"><strong>Room:</strong> ${apt.room_number || ''}</div>
                            </div>
                            <button class="btn btn-primary" style="min-width:120px;" onclick="viewPatientById('${apt.patient_id}')"><i class="fas fa-user"></i> View Patient</button>
                        </div>`;
                    });
                    appointmentsList.innerHTML = html;
                } else {
                    appointmentsList.innerHTML = '<p>Error loading appointments.</p>';
                }
            } catch (error) {
                document.getElementById('appointments-list').innerHTML = '<p>Error loading appointments.</p>';
            }
        }

        // Helper to view patient by id from appointment card
        function viewPatientById(patientId) {
            // Find patient in loaded patients array
            const patient = patients.find(p => p.patient_id === patientId);
            if (patient) {
                showPatientDetails(patient);
            } else {
                showAlert('Patient details not found.', 'error');
            }
        }

        // Assessment functionality
        async function loadAssessmentData() {
            try {
                const response = await fetch('patient_management.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=get_assessment_data'
                });

                const data = await response.json();
                if (data.success) {
                    // Populate patient dropdown
                    const patientSelect = document.getElementById('assessment-patient');
                    patientSelect.innerHTML = '<option value="">Select Patient</option>';
                    data.patients.forEach(patient => {
                        patientSelect.innerHTML += `<option value="${patient.patient_id}" data-type="${patient.type}">${patient.full_name} (${patient.type})</option>`;
                    });

                    // Populate meal plan dropdown
                    const mealPlanSelect = document.getElementById('meal-plan');
                    mealPlanSelect.innerHTML = '<option value="">Select Meal Plan</option>';
                    data.meal_plans.forEach(plan => {
                        const typeLabel = plan.type_name ? ` (${plan.type_name})` : '';
                        mealPlanSelect.innerHTML += `<option value="${plan.id}">${plan.name}${typeLabel}</option>`;
                    });

                    // Populate doctor dropdown
                    const doctorSelect = document.getElementById('assigned-doctor');
                    doctorSelect.innerHTML = '<option value="">Select Doctor</option>';
                    data.doctors.forEach(doctor => {
                        doctorSelect.innerHTML += `<option value="${doctor.staff_id}">${doctor.full_name}</option>`;
                    });

                    // Populate therapist dropdown
                    const therapistSelect = document.getElementById('assigned-therapist');
                    therapistSelect.innerHTML = '<option value="">Select Therapist</option>';
                    data.therapists.forEach(therapist => {
                        therapistSelect.innerHTML += `<option value="${therapist.staff_id}">${therapist.full_name}</option>`;
                    });

                    // Populate staff dropdowns by shift
                    const morningStaffSelect = document.getElementById('morning-staff');
                    morningStaffSelect.innerHTML = '<option value="">Select Staff</option>';
                    data.morning_staff.forEach(staff => {
                        morningStaffSelect.innerHTML += `<option value="${staff.staff_id}">${staff.full_name} (${staff.role})</option>`;
                    });

                    const eveningStaffSelect = document.getElementById('evening-staff');
                    eveningStaffSelect.innerHTML = '<option value="">Select Staff</option>';
                    data.evening_staff.forEach(staff => {
                        eveningStaffSelect.innerHTML += `<option value="${staff.staff_id}">${staff.full_name} (${staff.role})</option>`;
                    });

                    const nightStaffSelect = document.getElementById('night-staff');
                    nightStaffSelect.innerHTML = '<option value="">Select Staff</option>';
                    data.night_staff.forEach(staff => {
                        nightStaffSelect.innerHTML += `<option value="${staff.staff_id}">${staff.full_name} (${staff.role})</option>`;
                    });

                    // Add event listener for patient selection
                    patientSelect.addEventListener('change', function() {
                        const selectedOption = this.options[this.selectedIndex];
                        const patientType = selectedOption.getAttribute('data-type');
                        const patientId = this.value;
                        let patientStatus = '';
                        if (patientId) {
                            const patientObj = data.patients.find(p => p.patient_id === patientId);
                            patientStatus = patientObj ? patientObj.status : '';
                        }
                        
                        const doctorContainer = document.getElementById('doctor-container');
                        const therapistContainer = document.getElementById('therapist-container');
                        const statusSelect = document.getElementById('patient-status');
                        
                        // Reset both dropdowns
                        document.getElementById('assigned-doctor').value = '';
                        document.getElementById('assigned-therapist').value = '';
                        
                        if (patientType === 'Asylum') {
                            doctorContainer.style.display = 'block';
                            therapistContainer.style.display = 'none';
                        } else if (patientType === 'Rehabilitation') {
                            doctorContainer.style.display = 'none';
                            therapistContainer.style.display = 'block';
                        } else {
                            doctorContainer.style.display = 'none';
                            therapistContainer.style.display = 'none';
                        }

                        // Restrict status dropdown
                        if (patientStatus === 'admitted') {
                            if (userRole === 'chief-staff') {
                                statusSelect.disabled = false;
                                statusSelect.innerHTML = '<option value="admitted">Admitted</option><option value="active">Active</option><option value="discharged">Discharged</option>';
                            } else {
                                statusSelect.innerHTML = '<option value="admitted">Admitted</option>';
                                statusSelect.disabled = true;
                            }
                        } else {
                            statusSelect.innerHTML = `<option value="${patientStatus}">${patientStatus.charAt(0).toUpperCase() + patientStatus.slice(1)}</option>`;
                            statusSelect.disabled = true;
                        }
                    });
                } else {
                    showAlert('Error loading assessment data: ' + data.message, 'error');
                }
            } catch (error) {
                showAlert('Error loading assessment data: ' + error.message, 'error');
            }
        }

        async function displayAssessments() {
            try {
                const response = await fetch('patient_management.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=get_assessments'
                });

                const data = await response.json();
                const assessmentList = document.getElementById('assessment-history-list');
                if (data.success && data.assessments) {
                    if (data.assessments.length === 0) {
                        assessmentList.innerHTML = '<p>No assessments found.</p>';
                        return;
                    }
                    let html = '';
                    data.assessments.forEach(assessment => {
                        html += `
                            <div class="assessment-card" style="background:#fff;border-radius:16px;box-shadow:0 4px 16px rgba(67,97,238,0.07);padding:24px;margin-bottom:20px;">
                                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;">
                                    <h4 style="color:#2c3e50;margin:0;">${assessment.patient_name || assessment.patient_id}</h4>
                                    <span class="status-badge" style="padding:6px 12px;border-radius:20px;font-size:0.9rem;font-weight:600;background:${getStatusColor(assessment.patient_status)};color:white;">
                                        ${assessment.patient_status}
                                    </span>
                                </div>
                                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px;margin-bottom:15px;">
                                    <div><strong>Date:</strong> ${assessment.assessment_date}</div>
                                    <div><strong>Meal Plan:</strong> ${assessment.meal_plan_name || 'Not assigned'}</div>
                                    <div><strong>Doctor:</strong> ${assessment.assigned_doctor || 'Not assigned'}</div>
                                    <div><strong>Therapist:</strong> ${assessment.assigned_therapist || 'Not assigned'}</div>
                                </div>
                                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:15px;margin-bottom:15px;">
                                    <div><strong>Morning:</strong> ${assessment.morning_staff || 'Not assigned'}</div>
                                    <div><strong>Evening:</strong> ${assessment.evening_staff || 'Not assigned'}</div>
                                    <div><strong>Night:</strong> ${assessment.night_staff || 'Not assigned'}</div>
                                </div>
                                ${assessment.notes ? `<div><strong>Notes:</strong> ${assessment.notes}</div>` : ''}
                            </div>
                        `;
                    });
                    assessmentList.innerHTML = html;
                } else {
                    assessmentList.innerHTML = '<p>Error loading assessments.</p>';
                }
            } catch (error) {
                document.getElementById('assessment-history-list').innerHTML = '<p>Error loading assessments.</p>';
            }
        }

        function getStatusColor(status) {
            switch(status) {
                case 'admitted': return '#e74c3c';
                case 'active': return '#27ae60';
                case 'discharged': return '#95a5a6';
                default: return '#7f8c8d';
            }
        }

        // Assessment form submission
        document.getElementById('assessment-form').addEventListener('submit', async function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            formData.append('action', 'add_assessment');

            try {
                const response = await fetch('patient_management.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();
                if (data.success) {
                    showAlert('Assessment added successfully!', 'success');
                    this.reset();
                    document.getElementById('assessment-date').value = new Date().toISOString().split('T')[0];
                    displayAssessments();
                } else {
                    showAlert(data.message, 'error');
                }
            } catch (error) {
                showAlert('Error adding assessment: ' + error.message, 'error');
            }
        });

        // Update fetchTreatmentHistory to robustly render all treatments
        async function fetchTreatmentHistory(patientId) {
            const modal = document.getElementById('treatment-history-modal');
            const content = document.getElementById('treatment-history-content');
            content.innerHTML = '<div class="loading">Loading...</div>';
            try {
                const response = await fetch(`get_patient_treatments.php?patient_id=${patientId}`);
                const result = await response.json();
                console.log('Treatment history result:', result);
                if (result.success && Array.isArray(result.treatments)) {
                    if (result.treatments.length === 0) {
                        content.innerHTML = '<div class="no-history">No treatment history found for this patient.</div>';
                    } else {
                        let html = '<div class="treatment-history-list">';
                        result.treatments.forEach((treatment, idx) => {
                            // Determine type and date
                            let type = treatment.treatment_type || 'Unknown';
                            let date = treatment.created_at || treatment.med_start_date || treatment.session_date || treatment.rehab_start_date || treatment.crisis_date || treatment.documentation_date || 'N/A';
                            // Build details string
                            let details = '';
                            if (treatment.medication_type || treatment.dosage || treatment.schedule) {
                                details += `<div><strong>Medication:</strong> ${treatment.medication_type || 'N/A'} | Dosage: ${treatment.dosage || 'N/A'} | Schedule: ${treatment.schedule || 'N/A'}</div>`;
                            }
                            if (treatment.therapy_type || treatment.approach || treatment.session_notes) {
                                details += `<div><strong>Therapy:</strong> ${treatment.therapy_type || 'N/A'} | Approach: ${treatment.approach || 'N/A'} | Notes: ${treatment.session_notes || 'N/A'}</div>`;
                            }
                            if (treatment.rehab_type || treatment.program_details || treatment.goals) {
                                details += `<div><strong>Rehabilitation:</strong> ${treatment.rehab_type || 'N/A'} | Program: ${treatment.program_details || 'N/A'} | Goals: ${treatment.goals || 'N/A'}</div>`;
                            }
                            if (treatment.patient_status || treatment.notes) {
                                details += `<div><strong>Status:</strong> ${treatment.patient_status || 'N/A'} | Notes: ${treatment.notes || 'N/A'}</div>`;
                            }
                            if (treatment.review_schedule || treatment.reintegration) {
                                details += `<div><strong>Follow-up:</strong> Review: ${treatment.review_schedule || 'N/A'} | Reintegration: ${treatment.reintegration || 'N/A'}</div>`;
                            }
                            if (treatment.progress_notes || treatment.treatment_response || treatment.risk_assessment) {
                                details += `<div><strong>Progress:</strong> Notes: ${treatment.progress_notes || 'N/A'} | Response: ${treatment.treatment_response || 'N/A'} | Risk: ${treatment.risk_assessment || 'N/A'}</div>`;
                            }
                            if (!details) details = '<div>No additional details available.</div>';
                            html += `<div class="treatment-entry">
                                <div><strong>Type:</strong> ${type}</div>
                                <div><strong>Date:</strong> ${date}</div>
                                ${details}
                                <hr/>
                            </div>`;
                        });
                        html += '</div>';
                        content.innerHTML = html;
                    }
                } else {
                    content.innerHTML = '<div class="error">Failed to load treatment history.</div>';
                }
            } catch (err) {
                content.innerHTML = '<div class="error">Failed to load treatment history.</div>';
                console.error('Error fetching treatment history:', err);
            }
        }

        function closeTreatmentHistoryModal() {
            document.getElementById('treatment-history-modal').style.display = 'none';
        }

        function closeCredentialModal() {
            document.getElementById('credential-modal').style.display = 'none';
        }

        // Add this helper function if not already present
        function copyToClipboard(elementId) {
            const el = document.getElementById(elementId);
            if (!el) return;
            const text = el.innerText || el.textContent;
            const tempInput = document.createElement('input');
            tempInput.value = text;
            document.body.appendChild(tempInput);
            tempInput.select();
            document.execCommand('copy');
            document.body.removeChild(tempInput);
            // Optionally show a copied alert
            showAlert('Copied to clipboard!', 'success');
        }

        // --- APPOINTMENT MODAL LOGIC ---
        let doctorList = [];
        let therapistList = [];
        // Fetch doctor and therapist lists on page load
        async function fetchDoctorTherapistLists() {
            try {
                const response = await fetch('patient_management.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=get_assessment_data'
                });
                const data = await response.json();
                if (data.success) {
                    doctorList = data.doctors;
                    therapistList = data.therapists;
                }
            } catch (e) {}
        }
        fetchDoctorTherapistLists();

        // When opening appointment modal, set up doctor/therapist dropdowns
        function addAppointment(patientId) {
            document.getElementById('appointment-patient-id').value = patientId;
            // Find patient type
            let patientType = '';
            let patientObj = patients.find(p => p.patient_id == patientId || p.id == patientId);
            if (patientObj && patientObj.type) patientType = patientObj.type;
            // Populate doctor dropdown
            const docSelect = document.getElementById('appointment-doctor');
            docSelect.innerHTML = '<option value="">Select Doctor</option>';
            doctorList.forEach(doc => {
                docSelect.innerHTML += `<option value="${doc.staff_id}">${doc.full_name}</option>`;
            });
            // Populate therapist dropdown
            const therapistSelect = document.getElementById('appointment-therapist');
            therapistSelect.innerHTML = '<option value="">Select Therapist</option>';
            therapistList.forEach(therapist => {
                therapistSelect.innerHTML += `<option value="${therapist.staff_id}">${therapist.full_name}</option>`;
            });
            // Show/hide fields
            if (patientType === 'Asylum') {
                docSelect.style.display = '';
                document.getElementById('appointment-doctor-text').style.display = 'none';
                document.getElementById('appointment-therapist-group').style.display = 'none';
                docSelect.required = true;
                therapistSelect.required = false;
            } else if (patientType === 'Rehabilitation') {
                docSelect.style.display = 'none';
                document.getElementById('appointment-doctor-text').style.display = 'none';
                document.getElementById('appointment-therapist-group').style.display = '';
                docSelect.required = false;
                therapistSelect.required = true;
            } else {
                docSelect.style.display = 'none';
                document.getElementById('appointment-doctor-text').style.display = '';
                document.getElementById('appointment-therapist-group').style.display = 'none';
                docSelect.required = false;
                therapistSelect.required = false;
            }
            document.getElementById('appointment-modal').style.display = 'block';
            closeModal();
        }
        // On appointment form submit, set doctor field appropriately
        document.getElementById('add-appointment-form').addEventListener('submit', function(e) {
            // If therapist is shown, set doctor field to therapist value
            const docSelect = document.getElementById('appointment-doctor');
            const therapistSelect = document.getElementById('appointment-therapist');
            if (docSelect.style.display === 'none' && therapistSelect.style.display !== 'none') {
                // Set doctor field to therapist value
                if (therapistSelect.value) {
                    // Remove any existing hidden input
                    let hidden = document.getElementById('hidden-doctor');
                    if (!hidden) {
                        hidden = document.createElement('input');
                        hidden.type = 'hidden';
                        hidden.id = 'hidden-doctor';
                        hidden.name = 'doctor';
                        this.appendChild(hidden);
                    }
                    hidden.value = therapistSelect.options[therapistSelect.selectedIndex].text;
                }
            } else {
                // Remove hidden if exists
                let hidden = document.getElementById('hidden-doctor');
                if (hidden) hidden.remove();
            }
        });
    </script>
</body>
</html>
