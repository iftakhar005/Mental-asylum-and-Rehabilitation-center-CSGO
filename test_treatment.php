<?php
require_once 'session_check.php';
check_login(['doctor']);
require_once 'db.php';

// Test data
$test_data = [
    'patient_id' => 1,
    'medication_type' => 'antidepressants',
    'dosage' => '20mg',
    'schedule' => 'Once daily',
    'side_effects' => 'Monitor for drowsiness'
];

// Get doctor ID from session
$user_id = $_SESSION['user_id'];
echo "User ID from session: " . $user_id . "<br>";

$stmt = $conn->prepare("SELECT user_id FROM staff WHERE user_id = ? AND role = 'doctor'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$doctor = $stmt->get_result()->fetch_assoc();

if ($doctor) {
    echo "Doctor found: " . $doctor['user_id'] . "<br>";
    
    // Test inserting into treatments table
    $stmt = $conn->prepare("INSERT INTO treatments (patient_id, doctor_id, treatment_type) VALUES (?, ?, 'medication')");
    $stmt->bind_param("ii", $test_data['patient_id'], $doctor['user_id']);
    
    if ($stmt->execute()) {
        $treatment_id = $conn->insert_id;
        echo "Treatment record created with ID: " . $treatment_id . "<br>";
        
        // Test inserting into medication_treatments table
        $stmt = $conn->prepare("INSERT INTO medication_treatments (treatment_id, medication_type, dosage, schedule, side_effects, start_date) VALUES (?, ?, ?, ?, ?, CURDATE())");
        $stmt->bind_param("issss", $treatment_id, $test_data['medication_type'], $test_data['dosage'], $test_data['schedule'], $test_data['side_effects']);
        
        if ($stmt->execute()) {
            echo "Medication treatment record created successfully!<br>";
        } else {
            echo "Error creating medication treatment: " . $stmt->error . "<br>";
        }
    } else {
        echo "Error creating treatment record: " . $stmt->error . "<br>";
    }
} else {
    echo "Doctor not found<br>";
}

// Check if data was inserted
$result = $conn->query("SELECT COUNT(*) as count FROM treatments");
$count = $result->fetch_assoc()['count'];
echo "Total treatments in database: " . $count . "<br>";

$result = $conn->query("SELECT COUNT(*) as count FROM medication_treatments");
$count = $result->fetch_assoc()['count'];
echo "Total medication treatments in database: " . $count . "<br>";
?> 