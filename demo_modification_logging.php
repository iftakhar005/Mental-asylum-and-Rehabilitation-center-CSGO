<?php
/**
 * DEMO: How Modification Logging Works
 * This shows exactly how to track INSERT/UPDATE/DELETE operations
 */

session_start();
require_once 'db.php';

// Mock user session for demo
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'admin';
    $_SESSION['role'] = 'admin';
}

echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>Modification Logging Demo</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; }
        h1 { color: #333; border-bottom: 3px solid #667eea; padding-bottom: 10px; }
        .example { background: #f0f9ff; border-left: 4px solid #3b82f6; padding: 15px; margin: 15px 0; }
        .code { background: #1f2937; color: #10b981; padding: 15px; border-radius: 5px; font-family: monospace; white-space: pre-wrap; }
        .log-entry { background: #fef3c7; border-left: 4px solid #f59e0b; padding: 10px; margin: 10px 0; }
        .success { background: #d1fae5; border-left: 4px solid #10b981; padding: 10px; margin: 10px 0; }
        .btn { padding: 10px 20px; background: #667eea; color: white; border: none; border-radius: 5px; cursor: pointer; margin: 5px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        th { background: #f3f4f6; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>📝 Modification Logging Demo</h1>
";

// Helper function for logging
function logDataModification($conn, $table_name, $record_id, $operation, $field_name = null, $old_value = null, $new_value = null, $reason = '') {
    $user_id = $_SESSION['user_id'] ?? 0;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    
    $sql = "INSERT INTO data_modification_history 
            (user_id, table_name, record_id, operation_type, field_name, old_value, new_value, change_reason, ip_address) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('issssssss', $user_id, $table_name, $record_id, $operation, $field_name, $old_value, $new_value, $reason, $ip_address);
        $stmt->execute();
        $stmt->close();
        return true;
    }
    return false;
}

// DEMO ACTIONS
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    
    // EXAMPLE 1: INSERT (Create new record)
    if ($action == 'insert') {
        echo "<h2>Example 1: INSERT Operation</h2>";
        echo "<div class='example'><strong>Scenario:</strong> Admin creates a new test patient</div>";
        
        // Create a test patient with user_id
        $patient_id = 'TEST-' . time();
        $patient_name = 'Test Patient';
        
        // First, create a user account for the patient
        $username = 'test_patient_' . time();
        $password_hash = password_hash('test123', PASSWORD_DEFAULT);
        $email = $username . '@test.local';
        
        $user_sql = "INSERT INTO users (username, password_hash, email, role, first_name, last_name) 
                     VALUES (?, ?, ?, 'patient', 'Test', 'Patient')";
        $user_stmt = $conn->prepare($user_sql);
        if ($user_stmt) {
            $user_stmt->bind_param('sss', $username, $password_hash, $email);
            if ($user_stmt->execute()) {
                $user_id = $conn->insert_id;
                $user_stmt->close();
                
                // Now create the patient record
                $sql = "INSERT INTO patients (user_id, patient_id, full_name, date_of_birth, gender, status) 
                        VALUES (?, ?, ?, '1990-01-01', 'Male', 'admitted')";
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param('iss', $user_id, $patient_id, $patient_name);
                    
                    if ($stmt->execute()) {
                        echo "<div class='success'>✅ Patient created: $patient_id - $patient_name (User ID: $user_id)</div>";
                        
                        // LOG THE INSERTION
                        logDataModification($conn, 'patients', $patient_id, 'INSERT', 'full_name', null, $patient_name, 'New patient admission');
                        
                        echo "<div class='log-entry'>📝 Logged to audit trail!</div>";
                    } else {
                        echo "<div style='color:red;'>Failed to create patient: " . $stmt->error . "</div>";
                    }
                    $stmt->close();
                } else {
                    echo "<div style='color:red;'>Failed to prepare patient statement: " . $conn->error . "</div>";
                }
            } else {
                echo "<div style='color:red;'>Failed to create user: " . $user_stmt->error . "</div>";
                $user_stmt->close();
            }
        } else {
            echo "<div style='color:red;'>Failed to prepare user statement: " . $conn->error . "</div>";
        }
    }
    
    // EXAMPLE 2: UPDATE (Modify existing record)
    if ($action == 'update') {
        echo "<h2>Example 2: UPDATE Operation</h2>";
        echo "<div class='example'><strong>Scenario:</strong> Admin updates a patient's name</div>";
        
        // Find a test patient
        $result = $conn->query("SELECT patient_id, full_name FROM patients WHERE patient_id LIKE 'TEST-%' LIMIT 1");
        if ($result && $result->num_rows > 0) {
            $patient = $result->fetch_assoc();
            $patient_id = $patient['patient_id'];
            $old_name = $patient['full_name'];
            $new_name = $old_name . ' (Updated)';
            
            echo "<div class='code'>BEFORE UPDATE:
Patient ID: $patient_id
Old Name: $old_name</div>";
            
            // UPDATE the patient
            $sql = "UPDATE patients SET full_name = ? WHERE patient_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ss', $new_name, $patient_id);
            
            if ($stmt->execute()) {
                echo "<div class='success'>✅ Patient updated!</div>";
                
                echo "<div class='code'>AFTER UPDATE:
Patient ID: $patient_id
New Name: $new_name</div>";
                
                // LOG THE UPDATE
                logDataModification($conn, 'patients', $patient_id, 'UPDATE', 'full_name', $old_name, $new_name, 'Name correction');
                
                echo "<div class='log-entry'>📝 Logged to audit trail with old and new values!</div>";
            }
            $stmt->close();
        } else {
            echo "<div style='color:red;'>No test patient found. Run INSERT first.</div>";
        }
    }
    
    // EXAMPLE 3: DELETE (Remove record)
    if ($action == 'delete') {
        echo "<h2>Example 3: DELETE Operation</h2>";
        echo "<div class='example'><strong>Scenario:</strong> Admin deletes a test patient</div>";
        
        // Find a test patient
        $result = $conn->query("SELECT patient_id, full_name FROM patients WHERE patient_id LIKE 'TEST-%' LIMIT 1");
        if ($result && $result->num_rows > 0) {
            $patient = $result->fetch_assoc();
            $patient_id = $patient['patient_id'];
            $patient_name = $patient['full_name'];
            
            echo "<div class='code'>DELETING:
Patient ID: $patient_id
Name: $patient_name</div>";
            
            // DELETE the patient
            $sql = "DELETE FROM patients WHERE patient_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('s', $patient_id);
            
            if ($stmt->execute()) {
                echo "<div class='success'>✅ Patient deleted!</div>";
                
                // LOG THE DELETION
                logDataModification($conn, 'patients', $patient_id, 'DELETE', 'full_name', $patient_name, null, 'Test data cleanup');
                
                echo "<div class='log-entry'>📝 Logged to audit trail (old value preserved)!</div>";
            }
            $stmt->close();
        } else {
            echo "<div style='color:red;'>No test patient found. Run INSERT first.</div>";
        }
    }
}

// Show recent modification logs
echo "<hr><h2>📊 Recent Modification Logs</h2>";
$logs = $conn->query("SELECT * FROM data_modification_history ORDER BY modification_timestamp DESC LIMIT 10");

if ($logs && $logs->num_rows > 0) {
    echo "<table>";
    echo "<tr><th>User ID</th><th>Table</th><th>Record ID</th><th>Operation</th><th>Field</th><th>Old Value</th><th>New Value</th><th>Reason</th><th>Time</th></tr>";
    while ($log = $logs->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$log['user_id']}</td>";
        echo "<td>{$log['table_name']}</td>";
        echo "<td>{$log['record_id']}</td>";
        echo "<td><strong>{$log['operation_type']}</strong></td>";
        echo "<td>{$log['field_name']}</td>";
        echo "<td>" . htmlspecialchars($log['old_value'] ?? 'N/A') . "</td>";
        echo "<td>" . htmlspecialchars($log['new_value'] ?? 'N/A') . "</td>";
        echo "<td>{$log['change_reason']}</td>";
        echo "<td>{$log['modification_timestamp']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No modification logs yet. Try the examples below!</p>";
}

echo "
        <hr>
        <h2>🧪 Try It Yourself:</h2>
        <p>Click the buttons to see modification logging in action:</p>
        <a href='?action=insert' class='btn'>1️⃣ INSERT - Create Patient</a>
        <a href='?action=update' class='btn'>2️⃣ UPDATE - Modify Patient</a>
        <a href='?action=delete' class='btn'>3️⃣ DELETE - Remove Patient</a>
        
        <hr>
        <h3>📋 Key Points:</h3>
        <ul>
            <li><strong>INSERT:</strong> Log the NEW value (old value is NULL)</li>
            <li><strong>UPDATE:</strong> Get old value FIRST, then update, then log BOTH values</li>
            <li><strong>DELETE:</strong> Get old value FIRST, then delete, then log old value (new value is NULL)</li>
            <li><strong>Always include:</strong> Who did it, what changed, when, and why</li>
        </ul>
        
        <p><a href='audit_trail.php' class='btn' style='background:#10b981;'>View Full Audit Trail</a></p>
    </div>
</body>
</html>";
?>
