<?php
/**
 * Simple Aggregation Monitoring Setup
 * Creates all required tables for Audit Trail functionality
 */

require_once 'db.php';

echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>Audit Trail Database Setup</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #667eea; padding-bottom: 10px; }
        h2 { color: #667eea; margin-top: 25px; }
        .success { color: #10b981; padding: 10px; background: #d1fae5; border-left: 4px solid #10b981; margin: 10px 0; }
        .error { color: #ef4444; padding: 10px; background: #fee2e2; border-left: 4px solid #ef4444; margin: 10px 0; }
        .info { color: #3b82f6; padding: 10px; background: #dbeafe; border-left: 4px solid #3b82f6; margin: 10px 0; }
        .btn { display: inline-block; padding: 12px 24px; background: #667eea; color: white; text-decoration: none; border-radius: 6px; margin-top: 20px; }
        .btn:hover { background: #5a6fd8; }
        ul { margin: 10px 0; padding-left: 20px; }
        li { margin: 5px 0; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🛠️ Audit Trail Database Setup</h1>
        <p>Setting up audit trail monitoring tables...</p>
";

/**
 * Execute SQL with error handling
 */
function executeSQL($conn, $sql, $description = "") {
    try {
        if ($conn->query($sql)) {
            if ($description) {
                echo "<div class='success'>✅ $description</div>";
            }
            return true;
        } else {
            echo "<div class='error'>❌ $description - " . htmlspecialchars($conn->error) . "</div>";
            return false;
        }
    } catch (Exception $e) {
        echo "<div class='error'>❌ $description - " . htmlspecialchars($e->getMessage()) . "</div>";
        return false;
    }
}

echo "<h2>Step 1: Creating Core Tables</h2>";

// Table 1: Data Access Logs
$sql1 = "CREATE TABLE IF NOT EXISTS data_access_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    user_role VARCHAR(50) NOT NULL,
    table_accessed VARCHAR(100) NOT NULL,
    operation_type ENUM('SELECT', 'INSERT', 'UPDATE', 'DELETE', 'BULK_SELECT') NOT NULL,
    query_summary TEXT,
    records_affected INT DEFAULT 0,
    ip_address VARCHAR(45),
    user_agent TEXT,
    session_id VARCHAR(255),
    access_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    execution_time_ms DECIMAL(10,3) DEFAULT 0,
    is_bulk_operation BOOLEAN DEFAULT FALSE,
    is_sensitive_data BOOLEAN DEFAULT FALSE,
    INDEX idx_user_timestamp (user_id, access_timestamp),
    INDEX idx_table_operation (table_accessed, operation_type),
    INDEX idx_bulk_operations (is_bulk_operation, access_timestamp),
    INDEX idx_sensitive_access (is_sensitive_data, access_timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
executeSQL($conn, $sql1, "Created: data_access_logs");

// Table 2: Data Modification History
$sql2 = "CREATE TABLE IF NOT EXISTS data_modification_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    table_name VARCHAR(100) NOT NULL,
    record_id VARCHAR(50) NOT NULL,
    operation_type ENUM('INSERT', 'UPDATE', 'DELETE') NOT NULL,
    field_name VARCHAR(100),
    old_value TEXT,
    new_value TEXT,
    change_reason VARCHAR(500),
    modification_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    requires_approval BOOLEAN DEFAULT FALSE,
    approval_status ENUM('pending', 'approved', 'rejected') DEFAULT NULL,
    INDEX idx_table_record (table_name, record_id),
    INDEX idx_user_timestamp (user_id, modification_timestamp),
    INDEX idx_approval_status (approval_status, modification_timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
executeSQL($conn, $sql2, "Created: data_modification_history");

// Table 3: Bulk Operation Alerts
$sql3 = "CREATE TABLE IF NOT EXISTS bulk_operation_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    operation_type VARCHAR(100) NOT NULL,
    table_accessed VARCHAR(100) NOT NULL,
    records_count INT NOT NULL,
    threshold_exceeded VARCHAR(100),
    alert_level ENUM('INFO', 'WARNING', 'CRITICAL') DEFAULT 'WARNING',
    alert_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_investigated BOOLEAN DEFAULT FALSE,
    investigation_notes TEXT,
    INDEX idx_alert_level (alert_level, alert_timestamp),
    INDEX idx_user_alerts (user_id, alert_timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
executeSQL($conn, $sql3, "Created: bulk_operation_alerts");

// Table 4: User Session Monitoring
$sql4 = "CREATE TABLE IF NOT EXISTS user_session_monitoring (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_id VARCHAR(255) NOT NULL,
    login_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    user_agent TEXT,
    total_queries INT DEFAULT 0,
    bulk_operations INT DEFAULT 0,
    sensitive_access_count INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    logout_timestamp TIMESTAMP NULL,
    INDEX idx_session (session_id),
    INDEX idx_user_active (user_id, is_active),
    INDEX idx_activity_time (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
executeSQL($conn, $sql4, "Created: user_session_monitoring");

// Table 5: Role Data Permissions
$sql5 = "CREATE TABLE IF NOT EXISTS role_data_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL,
    table_name VARCHAR(100) NOT NULL,
    can_read BOOLEAN DEFAULT FALSE,
    can_create BOOLEAN DEFAULT FALSE,
    can_update BOOLEAN DEFAULT FALSE,
    can_delete BOOLEAN DEFAULT FALSE,
    can_bulk_export BOOLEAN DEFAULT FALSE,
    max_records_per_query INT DEFAULT 100,
    restricted_fields JSON,
    conditions_required TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_role_table (role_name, table_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
executeSQL($conn, $sql5, "Created: role_data_permissions");

// Table 6: Approval Workflows
$sql6 = "CREATE TABLE IF NOT EXISTS approval_workflows (
    id INT AUTO_INCREMENT PRIMARY KEY,
    workflow_name VARCHAR(100) NOT NULL,
    description TEXT,
    required_role ENUM('admin', 'chief-staff', 'doctor', 'therapist', 'nurse', 'receptionist') NOT NULL,
    approval_levels INT DEFAULT 1,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
executeSQL($conn, $sql6, "Created: approval_workflows");

// Table 7: Approval Requests
$sql7 = "CREATE TABLE IF NOT EXISTS approval_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    workflow_id INT NOT NULL,
    requester_id INT NOT NULL,
    request_type VARCHAR(100) NOT NULL,
    request_data JSON,
    request_reason TEXT,
    current_approval_level INT DEFAULT 1,
    status ENUM('pending', 'approved', 'rejected', 'cancelled') DEFAULT 'pending',
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    INDEX idx_status_timestamp (status, requested_at),
    INDEX idx_requester (requester_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
executeSQL($conn, $sql7, "Created: approval_requests");

// Table 8: Approval Actions
$sql8 = "CREATE TABLE IF NOT EXISTS approval_actions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    approver_id INT NOT NULL,
    action ENUM('approved', 'rejected', 'request_info') NOT NULL,
    approval_level INT NOT NULL,
    comments TEXT,
    action_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_request_level (request_id, approval_level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
executeSQL($conn, $sql8, "Created: approval_actions");

// Table 9: Role Permissions (alias for compatibility)
$sql9 = "CREATE TABLE IF NOT EXISTS role_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL,
    table_name VARCHAR(100) NOT NULL,
    can_read BOOLEAN DEFAULT FALSE,
    can_create BOOLEAN DEFAULT FALSE,
    can_update BOOLEAN DEFAULT FALSE,
    can_delete BOOLEAN DEFAULT FALSE,
    can_bulk_export BOOLEAN DEFAULT FALSE,
    max_records_per_query INT DEFAULT 100,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_role_table (role_name, table_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
executeSQL($conn, $sql9, "Created: role_permissions");

// Table 10: Data Retention Policies
$sql10 = "CREATE TABLE IF NOT EXISTS data_retention_policies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    table_name VARCHAR(100) NOT NULL,
    retention_period_days INT NOT NULL,
    archive_before_delete BOOLEAN DEFAULT TRUE,
    policy_description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_table (table_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
executeSQL($conn, $sql10, "Created: data_retention_policies");

// Table 11: Anonymization Rules
$sql11 = "CREATE TABLE IF NOT EXISTS anonymization_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    table_name VARCHAR(100) NOT NULL,
    field_name VARCHAR(100) NOT NULL,
    anonymization_method ENUM('hash', 'mask', 'replace', 'encrypt', 'delete') NOT NULL,
    applies_to_role VARCHAR(50),
    rule_description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_table_field (table_name, field_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
executeSQL($conn, $sql11, "Created: anonymization_rules");

// Table 12: Field Level Permissions
$sql12 = "CREATE TABLE IF NOT EXISTS field_level_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL,
    table_name VARCHAR(100) NOT NULL,
    field_name VARCHAR(100) NOT NULL,
    can_read BOOLEAN DEFAULT FALSE,
    can_update BOOLEAN DEFAULT FALSE,
    requires_approval BOOLEAN DEFAULT FALSE,
    mask_data BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_role_table_field (role_name, table_name, field_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
executeSQL($conn, $sql12, "Created: field_level_permissions");

echo "<h2>Step 2: Inserting Default Permissions</h2>";

// Insert default role permissions (both tables for compatibility)
$perms_sql = "INSERT IGNORE INTO role_data_permissions (role_name, table_name, can_read, can_create, can_update, can_delete, can_bulk_export, max_records_per_query) VALUES
('admin', 'users', TRUE, TRUE, TRUE, TRUE, TRUE, 1000),
('admin', 'patients', TRUE, TRUE, TRUE, TRUE, TRUE, 1000),
('admin', 'staff', TRUE, TRUE, TRUE, TRUE, TRUE, 1000),
('admin', 'data_access_logs', TRUE, FALSE, FALSE, FALSE, TRUE, 5000),
('chief-staff', 'patients', TRUE, TRUE, TRUE, FALSE, TRUE, 500),
('chief-staff', 'staff', TRUE, TRUE, TRUE, FALSE, FALSE, 200),
('chief-staff', 'appointments', TRUE, TRUE, TRUE, TRUE, FALSE, 300),
('doctor', 'patients', TRUE, FALSE, TRUE, FALSE, FALSE, 50),
('doctor', 'treatments', TRUE, TRUE, TRUE, FALSE, FALSE, 100),
('therapist', 'patients', TRUE, FALSE, TRUE, FALSE, FALSE, 30),
('nurse', 'patients', TRUE, FALSE, TRUE, FALSE, FALSE, 20)";
executeSQL($conn, $perms_sql, "Inserted default role_data_permissions");

// Also insert into role_permissions table
$perms_sql2 = "INSERT IGNORE INTO role_permissions (role_name, table_name, can_read, can_create, can_update, can_delete, can_bulk_export, max_records_per_query) VALUES
('admin', 'users', TRUE, TRUE, TRUE, TRUE, TRUE, 1000),
('admin', 'patients', TRUE, TRUE, TRUE, TRUE, TRUE, 1000),
('admin', 'staff', TRUE, TRUE, TRUE, TRUE, TRUE, 1000),
('admin', 'data_access_logs', TRUE, FALSE, FALSE, FALSE, TRUE, 5000),
('chief-staff', 'patients', TRUE, TRUE, TRUE, FALSE, TRUE, 500),
('chief-staff', 'staff', TRUE, TRUE, TRUE, FALSE, FALSE, 200),
('chief-staff', 'appointments', TRUE, TRUE, TRUE, TRUE, FALSE, 300),
('doctor', 'patients', TRUE, FALSE, TRUE, FALSE, FALSE, 50),
('doctor', 'treatments', TRUE, TRUE, TRUE, FALSE, FALSE, 100),
('therapist', 'patients', TRUE, FALSE, TRUE, FALSE, FALSE, 30),
('nurse', 'patients', TRUE, FALSE, TRUE, FALSE, FALSE, 20)";
executeSQL($conn, $perms_sql2, "Inserted default role_permissions");

echo "<h2>Step 3: Inserting Default Workflows</h2>";

// Insert default workflows
$workflows_sql = "INSERT IGNORE INTO approval_workflows (workflow_name, description, required_role, approval_levels) VALUES
('Patient Data Export', 'Approval required for bulk patient data export', 'chief-staff', 1),
('Sensitive Medical Records', 'Approval required for accessing sensitive medical records', 'doctor', 1),
('Staff Data Modification', 'Approval required for modifying staff information', 'admin', 1),
('Bulk Data Operations', 'Approval required for bulk database operations', 'chief-staff', 2),
('Patient Discharge', 'Approval required for patient discharge', 'doctor', 1),
('Medicine Stock Changes', 'Approval required for major medicine stock changes', 'chief-staff', 1)";
executeSQL($conn, $workflows_sql, "Inserted default approval workflows");

// Verify tables were created
echo "<h2>Step 4: Verification</h2>";
$tables_to_check = [
    'data_access_logs',
    'data_modification_history',
    'bulk_operation_alerts',
    'user_session_monitoring',
    'role_data_permissions',
    'role_permissions',
    'approval_workflows',
    'approval_requests',
    'approval_actions',
    'data_retention_policies',
    'anonymization_rules',
    'field_level_permissions'
];

$all_tables_exist = true;
echo "<ul>";
foreach ($tables_to_check as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result && $result->num_rows > 0) {
        echo "<li class='success'>✅ Table '$table' exists</li>";
    } else {
        echo "<li class='error'>❌ Table '$table' NOT found</li>";
        $all_tables_exist = false;
    }
}
echo "</ul>";

// Summary
if ($all_tables_exist) {
    echo "<div class='info'>
        <h3>✅ Setup Complete!</h3>
        <p><strong>All 12 tables have been created successfully.</strong></p>
        <p>You can now use the Audit Trail feature.</p>
        <a href='audit_trail.php' class='btn'>Go to Audit Trail</a>
        <a href='admin_dashboard.php' class='btn' style='background: #6b7280; margin-left: 10px;'>Back to Dashboard</a>
    </div>";
} else {
    echo "<div class='error'>
        <h3>⚠️ Setup Incomplete</h3>
        <p>Some tables were not created. Please check the errors above and try again.</p>
        <a href='simple_setup_aggregation_monitoring.php' class='btn'>Retry Setup</a>
    </div>";
}

echo "
    </div>
</body>
</html>";
?>
