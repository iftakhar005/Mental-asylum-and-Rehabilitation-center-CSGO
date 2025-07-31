<?php
// Test script for updated assessment functionality
require_once 'db.php';

echo "<h2>Testing Updated Assessment Functionality</h2>";

// Test 1: Check staff with shifts
echo "<h3>Test 1: Staff with Preferred Shifts</h3>";
$result = $conn->query("SELECT staff_id, full_name, role, shift FROM staff WHERE status = 'active' ORDER BY shift, full_name");
if ($result && $result->num_rows > 0) {
    echo "<p style='color: green;'>✓ Found {$result->num_rows} active staff members</p>";
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>Name</th><th>Role</th><th>Preferred Shift</th></tr>";
    while ($row = $result->fetch_assoc()) {
        $shift = $row['shift'] ? $row['shift'] : 'Not set';
        echo "<tr>";
        echo "<td>{$row['full_name']}</td>";
        echo "<td>{$row['role']}</td>";
        echo "<td>{$shift}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: orange;'>⚠ No active staff found</p>";
}

// Test 2: Check morning shift staff
echo "<h3>Test 2: Morning Shift Staff</h3>";
$result = $conn->query("SELECT staff_id, full_name, role FROM staff WHERE status = 'active' AND shift = 'Morning' ORDER BY full_name");
if ($result && $result->num_rows > 0) {
    echo "<p style='color: green;'>✓ Found {$result->num_rows} morning shift staff</p>";
    while ($row = $result->fetch_assoc()) {
        echo "<p>- {$row['full_name']} ({$row['role']})</p>";
    }
} else {
    echo "<p style='color: orange;'>⚠ No morning shift staff found</p>";
}

// Test 3: Check afternoon shift staff
echo "<h3>Test 3: Afternoon Shift Staff</h3>";
$result = $conn->query("SELECT staff_id, full_name, role FROM staff WHERE status = 'active' AND shift = 'Afternoon' ORDER BY full_name");
if ($result && $result->num_rows > 0) {
    echo "<p style='color: green;'>✓ Found {$result->num_rows} afternoon shift staff</p>";
    while ($row = $result->fetch_assoc()) {
        echo "<p>- {$row['full_name']} ({$row['role']})</p>";
    }
} else {
    echo "<p style='color: orange;'>⚠ No afternoon shift staff found</p>";
}

// Test 4: Check night shift staff
echo "<h3>Test 4: Night Shift Staff</h3>";
$result = $conn->query("SELECT staff_id, full_name, role FROM staff WHERE status = 'active' AND shift = 'Night' ORDER BY full_name");
if ($result && $result->num_rows > 0) {
    echo "<p style='color: green;'>✓ Found {$result->num_rows} night shift staff</p>";
    while ($row = $result->fetch_assoc()) {
        echo "<p>- {$row['full_name']} ({$row['role']})</p>";
    }
} else {
    echo "<p style='color: orange;'>⚠ No night shift staff found</p>";
}

// Test 5: Check doctors
echo "<h3>Test 5: Available Doctors</h3>";
$result = $conn->query("SELECT staff_id, full_name FROM staff WHERE role = 'doctor' AND status = 'active' ORDER BY full_name");
if ($result && $result->num_rows > 0) {
    echo "<p style='color: green;'>✓ Found {$result->num_rows} active doctors</p>";
    while ($row = $result->fetch_assoc()) {
        echo "<p>- {$row['full_name']}</p>";
    }
} else {
    echo "<p style='color: orange;'>⚠ No active doctors found</p>";
}

// Test 6: Check therapists
echo "<h3>Test 6: Available Therapists</h3>";
$result = $conn->query("SELECT staff_id, full_name FROM staff WHERE role = 'therapist' AND status = 'active' ORDER BY full_name");
if ($result && $result->num_rows > 0) {
    echo "<p style='color: green;'>✓ Found {$result->num_rows} active therapists</p>";
    while ($row = $result->fetch_assoc()) {
        echo "<p>- {$row['full_name']}</p>";
    }
} else {
    echo "<p style='color: orange;'>⚠ No active therapists found</p>";
}

// Test 7: Check patients by type
echo "<h3>Test 7: Patients by Type</h3>";
$result = $conn->query("SELECT patient_id, full_name, type, status FROM patients WHERE status != 'discharged' ORDER BY type, full_name");
if ($result && $result->num_rows > 0) {
    echo "<p style='color: green;'>✓ Found {$result->num_rows} active patients</p>";
    $asylum_count = 0;
    $rehab_count = 0;
    while ($row = $result->fetch_assoc()) {
        echo "<p>- {$row['full_name']} ({$row['type']}) - Status: {$row['status']}</p>";
        if ($row['type'] === 'Asylum') $asylum_count++;
        if ($row['type'] === 'Rehabilitation') $rehab_count++;
    }
    echo "<p><strong>Summary:</strong> {$asylum_count} Asylum patients, {$rehab_count} Rehabilitation patients</p>";
} else {
    echo "<p style='color: orange;'>⚠ No active patients found</p>";
}

echo "<h3>Updated Assessment System Features</h3>";
echo "<p>The assessment system now includes:</p>";
echo "<ul>";
echo "<li><strong>Patient Type-Based Medical Staff:</strong> Only doctors show for Asylum patients, only therapists show for Rehabilitation patients</li>";
echo "<li><strong>Shift-Based Staff Filtering:</strong> Staff are filtered by their preferred shifts (Morning/Afternoon/Night)</li>";
echo "<li><strong>Dynamic Form Display:</strong> Medical staff dropdowns show/hide based on selected patient type</li>";
echo "<li><strong>Proper Staff Assignment:</strong> Each shift dropdown only shows staff with that preferred shift</li>";
echo "</ul>";

echo "<p><a href='patient_management.php' style='background: #667eea; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Patient Management</a></p>";
?> 