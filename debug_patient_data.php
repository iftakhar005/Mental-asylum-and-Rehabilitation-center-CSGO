<?php
require_once 'session_check.php';
check_login(['admin', 'chief-staff', 'doctor', 'nurse', 'therapist', 'receptionist', 'staff']);
require_once 'db.php';

echo "<h2>Raw Patient Data Debug</h2>";
echo "<p>Let's check what's actually in the database:</p>";

$stmt = $conn->prepare("SELECT user_id, patient_id, full_name, date_of_birth, gender, contact_number, emergency_contact, address FROM patients LIMIT 3");
$stmt->execute();
$result = $stmt->get_result();

echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>user_id</th><th>patient_id</th><th>full_name</th><th>date_of_birth</th><th>gender</th><th>contact_number</th><th>emergency_contact</th><th>address</th></tr>";

while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    foreach ($row as $value) {
        echo "<td>" . htmlspecialchars($value) . "</td>";
    }
    echo "</tr>";
}
echo "</table>";

echo "<br><br><h3>Data Classification Status:</h3>";
$stmt2 = $conn->prepare("SELECT * FROM data_classification WHERE table_name = 'patients'");
$stmt2->execute();
$classifications = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);

if (empty($classifications)) {
    echo "<p style='color: orange;'>⚠️ No data classifications found for 'patients' table. This means no masking rules are applied.</p>";
} else {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Column</th><th>Classification</th><th>Requires Approval</th><th>Watermark Required</th></tr>";
    foreach ($classifications as $class) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($class['column_name']) . "</td>";
        echo "<td>" . htmlspecialchars($class['classification_level']) . "</td>";
        echo "<td>" . ($class['requires_approval'] ? 'Yes' : 'No') . "</td>";
        echo "<td>" . ($class['watermark_required'] ? 'Yes' : 'No') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}
?>