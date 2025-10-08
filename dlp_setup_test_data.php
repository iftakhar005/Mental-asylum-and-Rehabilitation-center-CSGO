<?php
require_once 'db.php';
require_once 'session_check.php';
check_login(['admin']);

// Initialize test data for DLP system
$success_messages = [];
$error_messages = [];

// 1. Add sample DLP configuration
$configs = [
    ['max_export_size', '100MB', 'Maximum file size for data exports'],
    ['approval_timeout_hours', '72', 'Hours before export approval expires'],
    ['suspicious_download_threshold', '5', 'Number of downloads in 1 hour that triggers alert'],
    ['watermark_template', 'CONFIDENTIAL - Downloaded by {user} on {timestamp} - Authorized Export', 'Template for file watermarks'],
    ['retention_check_frequency', 'daily', 'How often to run retention policy checks']
];

foreach ($configs as $config) {
    $stmt = $conn->prepare("INSERT IGNORE INTO dlp_config (config_key, config_value, description) VALUES (?, ?, ?)");
    if ($stmt === false) {
        $error_messages[] = "❌ Failed to prepare config statement: " . $conn->error;
        continue;
    }
    $stmt->bind_param("sss", $config[0], $config[1], $config[2]);
    if ($stmt->execute()) {
        $success_messages[] = "✅ Added DLP config: " . $config[0];
    } else {
        $error_messages[] = "❌ Failed to add config: " . $config[0] . " - " . $stmt->error;
    }
}

// 2. Add sample data classifications
$classifications = [
    ['patients', 'medical_history', 'restricted', 'medical_records', 2555, true, true],
    ['patients', 'personal_info', 'confidential', 'personal_data', 2190, true, true],
    ['appointments', 'appointment_notes', 'confidential', 'medical_records', 2555, true, false],
    ['staff', 'contact_info', 'internal', 'employee_data', 2190, false, false],
    ['treatments', 'treatment_details', 'restricted', 'medical_records', 2555, true, true],
    ['patients', 'emergency_contact', 'internal', 'contact_info', 1825, false, false]
];

foreach ($classifications as $class) {
    $stmt = $conn->prepare("INSERT IGNORE INTO data_classification (table_name, column_name, classification_level, data_category, retention_days, requires_approval, watermark_required) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if ($stmt === false) {
        $error_messages[] = "❌ Failed to prepare classification statement: " . $conn->error;
        continue;
    }
    $stmt->bind_param("ssssiii", $class[0], $class[1], $class[2], $class[3], $class[4], $class[5], $class[6]);
    if ($stmt->execute()) {
        $success_messages[] = "✅ Classified data: {$class[0]}.{$class[1]} as {$class[2]}";
    } else {
        $error_messages[] = "❌ Failed to classify: {$class[0]}.{$class[1]} - " . $stmt->error;
    }
}

// 3. Add sample retention policies
$policies = [
    ['Medical_Records_Retention', 'treatments', 2555, 'restricted', 'Medical records must be kept for 7 years per regulations'],
    ['Patient_Data_Cleanup', 'patients', 2555, 'confidential', 'Patient personal data retention policy'],
    ['Temporary_Files_Cleanup', 'temp_exports', 7, 'internal', 'Clean up temporary export files weekly'],
    ['Audit_Log_Retention', 'data_access_audit', 2190, 'internal', 'Keep audit logs for 6 years']
];

foreach ($policies as $policy) {
    $stmt = $conn->prepare("INSERT IGNORE INTO retention_policies (policy_name, table_name, retention_days, classification_level, policy_description) VALUES (?, ?, ?, ?, ?)");
    if ($stmt === false) {
        $error_messages[] = "❌ Failed to prepare retention policy statement: " . $conn->error;
        continue;
    }
    $stmt->bind_param("ssiss", $policy[0], $policy[1], $policy[2], $policy[3], $policy[4]);
    if ($stmt->execute()) {
        $success_messages[] = "✅ Created retention policy: " . $policy[0];
    } else {
        $error_messages[] = "❌ Failed to create policy: " . $policy[0] . " - " . $stmt->error;
    }
}

// 4. Add sample export requests for testing approval workflow
$sample_requests = [
    [
        'request_id' => 'EXP-' . date('Ymd') . '-001',
        'user_id' => $_SESSION['user_id'] ?? 'admin',
        'requester_name' => $_SESSION['username'] ?? 'Admin User',
        'requester_role' => $_SESSION['role'] ?? 'admin',
        'export_type' => 'monthly_patient_report',
        'data_tables' => 'patients,appointments,treatments',
        'data_filters' => '{"date_range": "2024-01-01 to 2024-12-31", "status": "active"}',
        'justification' => 'Monthly compliance report required by regulatory authority for patient care quality assessment',
        'classification_level' => 'confidential'
    ],
    [
        'request_id' => 'EXP-' . date('Ymd') . '-002',
        'user_id' => $_SESSION['user_id'] ?? 'admin',
        'requester_name' => $_SESSION['username'] ?? 'Admin User',
        'requester_role' => $_SESSION['role'] ?? 'admin',
        'export_type' => 'staff_performance_report',
        'data_tables' => 'staff,appointments',
        'data_filters' => '{"department": "therapy", "period": "Q4-2024"}',
        'justification' => 'Quarterly staff performance evaluation for therapy department',
        'classification_level' => 'internal'
    ]
];

foreach ($sample_requests as $request) {
    $stmt = $conn->prepare("INSERT IGNORE INTO export_approval_requests (request_id, user_id, requester_name, requester_role, export_type, data_tables, data_filters, justification, classification_level) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if ($stmt === false) {
        $error_messages[] = "❌ Failed to prepare export request statement: " . $conn->error;
        continue;
    }
    $stmt->bind_param("sssssssss", $request['request_id'], $request['user_id'], $request['requester_name'], $request['requester_role'], $request['export_type'], $request['data_tables'], $request['data_filters'], $request['justification'], $request['classification_level']);
    if ($stmt->execute()) {
        $success_messages[] = "✅ Created sample export request: " . $request['request_id'];
    } else {
        $error_messages[] = "❌ Failed to create export request: " . $request['request_id'] . " - " . $stmt->error;
    }
}

// 5. Add some sample download activity for monitoring demonstration
$sample_downloads = [
    ['export_patient_data.csv', 'csv', $_SESSION['user_id'] ?? 'admin', $_SESSION['username'] ?? 'Admin User', $_SESSION['role'] ?? 'admin', $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1', 'confidential'],
    ['monthly_report.pdf', 'pdf', $_SESSION['user_id'] ?? 'admin', $_SESSION['username'] ?? 'Admin User', $_SESSION['role'] ?? 'admin', $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1', 'internal'],
    ['staff_summary.xlsx', 'xlsx', $_SESSION['user_id'] ?? 'admin', $_SESSION['username'] ?? 'Admin User', $_SESSION['role'] ?? 'admin', $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1', 'internal']
];

foreach ($sample_downloads as $download) {
    $stmt = $conn->prepare("INSERT INTO download_activity (file_name, file_type, user_id, user_name, user_role, ip_address, data_classification) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if ($stmt === false) {
        $error_messages[] = "❌ Failed to prepare download activity statement: " . $conn->error;
        continue;
    }
    $stmt->bind_param("sssssss", $download[0], $download[1], $download[2], $download[3], $download[4], $download[5], $download[6]);
    if ($stmt->execute()) {
        $success_messages[] = "✅ Added sample download activity: " . $download[0];
    } else {
        $error_messages[] = "❌ Failed to add download activity: " . $download[0] . " - " . $stmt->error;
    }
}

// 6. Add sample audit trail entries
$sample_audits = [
    ['patients', 'view', $_SESSION['user_id'] ?? 'admin', $_SESSION['username'] ?? 'Admin User', $_SESSION['role'] ?? 'admin', $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1', 'confidential'],
    ['treatments', 'view', $_SESSION['user_id'] ?? 'admin', $_SESSION['username'] ?? 'Admin User', $_SESSION['role'] ?? 'admin', $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1', 'restricted'],
    ['staff', 'export', $_SESSION['user_id'] ?? 'admin', $_SESSION['username'] ?? 'Admin User', $_SESSION['role'] ?? 'admin', $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1', 'internal']
];

foreach ($sample_audits as $audit) {
    $stmt = $conn->prepare("INSERT INTO data_access_audit (table_name, action_type, user_id, user_name, user_role, ip_address, data_classification) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if ($stmt === false) {
        $error_messages[] = "❌ Failed to prepare audit statement: " . $conn->error;
        continue;
    }
    $stmt->bind_param("sssssss", $audit[0], $audit[1], $audit[2], $audit[3], $audit[4], $audit[5], $audit[6]);
    if ($stmt->execute()) {
        $success_messages[] = "✅ Added audit trail entry for: " . $audit[0];
    } else {
        $error_messages[] = "❌ Failed to add audit entry for: " . $audit[0] . " - " . $stmt->error;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DLP Test Data Setup</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { text-align: center; margin-bottom: 30px; }
        .header h1 { color: #2c3e50; margin-bottom: 10px; }
        .success { background: #d4edda; color: #155724; padding: 10px 15px; border-radius: 5px; margin: 5px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 10px 15px; border-radius: 5px; margin: 5px 0; }
        .nav-links { text-align: center; margin: 30px 0; }
        .nav-links a { display: inline-block; background: #3498db; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin: 0 10px; }
        .nav-links a:hover { background: #2980b9; }
        .summary { background: #e3f2fd; padding: 20px; border-radius: 8px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🛠️ DLP Test Data Setup</h1>
            <p>Sample data has been created for testing DLP functionality</p>
        </div>

        <div class="summary">
            <h3>📊 Setup Summary:</h3>
            <ul>
                <li><strong><?= count($success_messages) ?></strong> items created successfully</li>
                <li><strong><?= count($error_messages) ?></strong> errors encountered</li>
            </ul>
        </div>

        <?php if (!empty($success_messages)): ?>
        <h3 style="color: #27ae60;">✅ Successfully Created:</h3>
        <?php foreach ($success_messages as $message): ?>
            <div class="success"><?= htmlspecialchars($message) ?></div>
        <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!empty($error_messages)): ?>
        <h3 style="color: #e74c3c;">❌ Errors:</h3>
        <?php foreach ($error_messages as $message): ?>
            <div class="error"><?= htmlspecialchars($message) ?></div>
        <?php endforeach; ?>
        <?php endif; ?>

        <div class="nav-links">
            <a href="dlp_test_guide.php">📋 Testing Guide</a>
            <a href="dlp_management.php">🛡️ DLP Management</a>
            <a href="check_dlp.php">🔍 System Health</a>
            <a href="admin_dashboard.php">🏠 Dashboard</a>
        </div>

        <div style="background: #fff3cd; padding: 15px; border-radius: 8px; margin-top: 20px;">
            <h4 style="color: #856404; margin-top: 0;">🎯 What's Been Created:</h4>
            <ul style="color: #856404;">
                <li><strong>5 DLP Configuration</strong> settings</li>
                <li><strong>6 Data Classifications</strong> for different data types</li>
                <li><strong>4 Retention Policies</strong> for automated cleanup</li>
                <li><strong>2 Sample Export Requests</strong> for testing approval workflow</li>
                <li><strong>3 Sample Download Activities</strong> for monitoring demonstration</li>
                <li><strong>3 Audit Trail Entries</strong> for compliance tracking</li>
            </ul>
            <p style="color: #856404; margin-bottom: 0;"><strong>Ready to test!</strong> Use the Testing Guide to verify all functionality.</p>
        </div>
    </div>
</body>
</html>