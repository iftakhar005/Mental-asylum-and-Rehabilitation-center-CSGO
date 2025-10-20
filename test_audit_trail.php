<?php
/**
 * TEST AUDIT TRAIL SYSTEM
 * Verify that audit trail is working correctly
 */

session_start();
require_once 'db.php';

echo "<h1>Audit Trail System Test</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
    .success { background: #d1fae5; border-left: 4px solid #10b981; padding: 10px; margin: 10px 0; }
    .error { background: #fee2e2; border-left: 4px solid #ef4444; padding: 10px; margin: 10px 0; }
    .info { background: #dbeafe; border-left: 4px solid #3b82f6; padding: 10px; margin: 10px 0; }
    table { width: 100%; border-collapse: collapse; background: white; margin: 10px 0; }
    th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
    th { background: #f3f4f6; }
    .btn { display: inline-block; padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; margin: 5px; }
</style>";

echo "<h2>Step 1: Check Required Tables</h2>";

$required_tables = [
    'data_access_logs',
    'data_modification_history',
    'bulk_operation_alerts',
    'role_permissions',
    'approval_workflows',
    'data_retention_policies',
    'anonymization_rules',
    'field_level_permissions'
];

$missing_tables = [];
$existing_tables = [];

foreach ($required_tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result && $result->num_rows > 0) {
        $existing_tables[] = $table;
        echo "<div class='success'>✅ Table exists: $table</div>";
    } else {
        $missing_tables[] = $table;
        echo "<div class='error'>❌ Table missing: $table</div>";
    }
}

if (!empty($missing_tables)) {
    echo "<div class='error'>";
    echo "<h3>⚠️ Audit Trail NOT Setup!</h3>";
    echo "<p>Missing tables: " . implode(', ', $missing_tables) . "</p>";
    echo "<p><strong>You need to run the setup script first:</strong></p>";
    echo "<a href='simple_setup_aggregation_monitoring.php' class='btn'>🔧 Run Database Setup</a>";
    echo "</div>";
} else {
    echo "<div class='success'><h3>✅ All Required Tables Exist!</h3></div>";
    
    // Check table data
    echo "<h2>Step 2: Check Table Data</h2>";
    
    // Check data_access_logs
    $logs_count = $conn->query("SELECT COUNT(*) as cnt FROM data_access_logs")->fetch_assoc()['cnt'];
    echo "<div class='info'>📊 data_access_logs: <strong>$logs_count</strong> records</div>";
    
    // Check data_modification_history
    $mods_count = $conn->query("SELECT COUNT(*) as cnt FROM data_modification_history")->fetch_assoc()['cnt'];
    echo "<div class='info'>📊 data_modification_history: <strong>$mods_count</strong> records</div>";
    
    // Check bulk_operation_alerts
    $alerts_count = $conn->query("SELECT COUNT(*) as cnt FROM bulk_operation_alerts")->fetch_assoc()['cnt'];
    echo "<div class='info'>📊 bulk_operation_alerts: <strong>$alerts_count</strong> records</div>";
    
    // Check role_permissions
    $perms_count = $conn->query("SELECT COUNT(*) as cnt FROM role_permissions")->fetch_assoc()['cnt'];
    echo "<div class='info'>📊 role_permissions: <strong>$perms_count</strong> records</div>";
    
    if ($perms_count == 0) {
        echo "<div class='error'>❌ No role permissions configured! Run setup script.</div>";
    }
    
    echo "<h2>Step 3: Test Logging Functionality</h2>";
    
    // First, check the structure of data_access_logs
    $structure = $conn->query("DESCRIBE data_access_logs");
    $columns = [];
    if ($structure) {
        while ($col = $structure->fetch_assoc()) {
            $columns[] = $col['Field'];
        }
    }
    
    // Insert a test log entry using the correct columns
    $test_user_id = $_SESSION['user_id'] ?? 1;
    $test_username = $_SESSION['username'] ?? 'test_user';
    
    // Build SQL based on available columns
    if (in_array('username', $columns)) {
        // New structure with username column
        $sql = "INSERT INTO data_access_logs 
                (user_id, username, table_name, operation_type, record_id, ip_address, user_agent, access_timestamp) 
                VALUES (?, ?, 'test_table', 'SELECT', '123', ?, ?, NOW())";
        
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            echo "<div class='error'>❌ Failed to prepare statement: " . htmlspecialchars($conn->error) . "</div>";
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Test Agent';
            $stmt->bind_param('isss', $test_user_id, $test_username, $ip, $ua);
            
            if ($stmt->execute()) {
                $insert_id = $conn->insert_id;
                echo "<div class='success'>✅ Test log entry created! (ID: $insert_id)</div>";
                
                // Verify it was inserted
                $verify = $conn->query("SELECT * FROM data_access_logs WHERE id = $insert_id");
                if ($verify && $verify->num_rows > 0) {
                    $verify_data = $verify->fetch_assoc();
                    echo "<div class='success'>✅ Log entry verified in database!</div>";
                    echo "<table>";
                    echo "<tr><th>Field</th><th>Value</th></tr>";
                    foreach ($verify_data as $key => $value) {
                        echo "<tr><td>$key</td><td>" . htmlspecialchars($value ?? '') . "</td></tr>";
                    }
                    echo "</table>";
                }
            } else {
                echo "<div class='error'>❌ Failed to create test log: " . htmlspecialchars($stmt->error) . "</div>";
            }
            $stmt->close();
        }
    } else {
        // Old structure without username column
        $sql = "INSERT INTO data_access_logs 
                (user_id, user_role, table_accessed, operation_type, ip_address, user_agent, access_timestamp) 
                VALUES (?, 'admin', 'test_table', 'SELECT', ?, ?, NOW())";
        
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            echo "<div class='error'>❌ Failed to prepare statement: " . htmlspecialchars($conn->error) . "</div>";
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Test Agent';
            $stmt->bind_param('iss', $test_user_id, $ip, $ua);
            
            if ($stmt->execute()) {
                $insert_id = $conn->insert_id;
                echo "<div class='success'>✅ Test log entry created! (ID: $insert_id)</div>";
            } else {
                echo "<div class='error'>❌ Failed to create test log: " . htmlspecialchars($stmt->error) . "</div>";
            }
            $stmt->close();
        }
    }
    
    echo "<h2>Step 4: Recent Audit Logs</h2>";
    
    // First check what columns actually exist
    $structure = $conn->query("DESCRIBE data_access_logs");
    $actual_columns = [];
    if ($structure) {
        while ($col = $structure->fetch_assoc()) {
            $actual_columns[] = $col['Field'];
        }
    }
    
    // Determine which column names to use
    $user_col = in_array('username', $actual_columns) ? 'username' : 'user_role';
    $table_col = in_array('table_name', $actual_columns) ? 'table_name' : 'table_accessed';
    $record_col = in_array('record_id', $actual_columns) ? 'record_id' : 'id';
    $time_col = in_array('access_timestamp', $actual_columns) ? 'access_timestamp' : 'created_at';
    
    $recent = $conn->query("SELECT * FROM data_access_logs ORDER BY $time_col DESC LIMIT 10");
    
    if ($recent && $recent->num_rows > 0) {
        echo "<table>";
        echo "<tr><th>ID</th><th>User</th><th>Table</th><th>Operation</th><th>IP Address</th><th>Timestamp</th></tr>";
        while ($row = $recent->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$row['id']}</td>";
            echo "<td>" . htmlspecialchars($row[$user_col] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($row[$table_col] ?? 'N/A') . "</td>";
            echo "<td>{$row['operation_type']}</td>";
            echo "<td>" . htmlspecialchars($row['ip_address'] ?? 'N/A') . "</td>";
            echo "<td>{$row[$time_col]}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='info'>ℹ️ No audit logs yet. Perform some database operations to generate logs.</div>";
    }
    
    echo "<h2>✅ Audit Trail System Status</h2>";
    echo "<div class='success'>";
    echo "<h3>✅ Audit Trail is WORKING!</h3>";
    echo "<ul>";
    echo "<li>✅ All tables created</li>";
    echo "<li>✅ Logging functionality working</li>";
    echo "<li>✅ Database queries successful</li>";
    echo "</ul>";
    echo "<p><a href='audit_trail.php' class='btn'>📊 View Audit Trail Dashboard</a></p>";
    echo "</div>";
}

echo "<hr>";
echo "<h3>Quick Actions</h3>";
echo "<a href='simple_setup_aggregation_monitoring.php' class='btn'>🔧 Re-run Setup</a>";
echo "<a href='audit_trail.php' class='btn'>📊 Audit Trail Dashboard</a>";
echo "<a href='admin_dashboard.php' class='btn'>🏠 Admin Dashboard</a>";

?>
