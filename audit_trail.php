<?php
/**
 * AUDIT TRAIL DASHBOARD
 * Comprehensive view of all system activities and data modifications
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db.php';
require_once 'security_manager.php';
require_once 'session_protection.php';

protectPage();

// Only admin can access audit trail
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php?error=unauthorized');
    exit();
}

$securityManager = new MentalHealthSecurityManager($conn);
$current_user = $_SESSION['username'] ?? 'Unknown';
$current_role = $_SESSION['role'] ?? 'unknown';

// Get audit data (last 100 records)
$audit_logs_sql = "SELECT 
    dal.*, u.username, u.first_name, u.last_name
FROM data_access_logs dal
LEFT JOIN users u ON dal.user_id = u.id
ORDER BY dal.access_timestamp DESC
LIMIT 100";

$audit_logs_result = $conn->query($audit_logs_sql);
$audit_logs = $audit_logs_result ? $audit_logs_result->fetch_all(MYSQLI_ASSOC) : [];

// Get modifications (last 50)
$mods_sql = "SELECT 
    dmh.*, u.username
FROM data_modification_history dmh
LEFT JOIN users u ON dmh.user_id = u.id
ORDER BY dmh.modification_timestamp DESC
LIMIT 50";

$mods_result = $conn->query($mods_sql);
$modifications = $mods_result ? $mods_result->fetch_all(MYSQLI_ASSOC) : [];

// Get bulk alerts
$alerts_sql = "SELECT 
    boa.*, u.username
FROM bulk_operation_alerts boa
LEFT JOIN users u ON boa.user_id = u.id
ORDER BY boa.alert_timestamp DESC
LIMIT 20";

$alerts_result = $conn->query($alerts_sql);
$bulk_alerts = $alerts_result ? $alerts_result->fetch_all(MYSQLI_ASSOC) : [];

// Check if tables exist
$tables_exist = true;
$missing_tables = [];
$required_tables = ['data_access_logs', 'data_modification_history', 'bulk_operation_alerts'];

foreach ($required_tables as $table) {
    $check = $conn->query("SHOW TABLES LIKE '$table'");
    if (!$check || $check->num_rows === 0) {
        $tables_exist = false;
        $missing_tables[] = $table;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Trail</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1600px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
        }
        .header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e5e7eb;
        }
        h1 { color: #1f2937; font-size: 2rem; display: flex; align-items: center; gap: 12px; }
        h1 i { color: #667eea; }
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #e5e7eb;
        }
        .tab {
            padding: 12px 24px;
            border: none;
            background: none;
            color: #6b7280;
            font-weight: 600;
            cursor: pointer;
            border-bottom: 3px solid transparent;
        }
        .tab.active { color: #667eea; border-bottom-color: #667eea; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 12px;
            overflow: hidden;
        }
        th {
            background: #f3f4f6;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #374151;
            border-bottom: 2px solid #e5e7eb;
        }
        td {
            padding: 12px;
            border-bottom: 1px solid #f3f4f6;
            color: #6b7280;
            font-size: 0.85rem;
        }
        tr:hover { background: #f9fafb; }
        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .badge-select { background: #dbeafe; color: #1e40af; }
        .badge-insert { background: #d1fae5; color: #065f46; }
        .badge-update { background: #fef3c7; color: #92400e; }
        .badge-delete { background: #fee2e2; color: #991b1b; }
        .badge-bulk { background: #fecaca; color: #991b1b; }
        .badge-sensitive { background: #fae8ff; color: #86198f; }
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin: 10px 0;
            display: flex;
            gap: 10px;
        }
        .alert-warning { background: #fef3c7; color: #92400e; border-left: 4px solid #f59e0b; }
        .alert-critical { background: #fee2e2; color: #991b1b; border-left: 4px solid #ef4444; }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            background: #667eea;
            color: white;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
        }
        .btn:hover { background: #5a6fd8; }
        .setup-warning {
            background: #fef3c7;
            border: 2px solid #f59e0b;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .setup-warning h3 {
            color: #92400e;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .setup-warning p {
            color: #78350f;
            margin: 5px 0;
        }
        .setup-warning .btn {
            margin-top: 15px;
            background: #f59e0b;
        }
        .setup-warning .btn:hover {
            background: #d97706;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #9ca3af;
        }
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 15px;
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1><i class="fas fa-clipboard-list"></i> Audit Trail</h1>
                <p style="color: #6b7280;">System activity monitoring and data modification tracking</p>
            </div>
            <div style="text-align: right; color: #6b7280;">
                <p><strong><?php echo htmlspecialchars($current_user); ?></strong></p>
                <p>Role: <?php echo htmlspecialchars($current_role); ?></p>
            </div>
        </div>

        <?php if (!$tables_exist): ?>
        <div class="setup-warning">
            <h3>
                <i class="fas fa-exclamation-triangle"></i>
                Audit Trail Database Not Setup
            </h3>
            <p><strong>Missing tables:</strong> <?php echo implode(', ', $missing_tables); ?></p>
            <p>The audit trail requires database tables to be created. Click the button below to run the setup:</p>
            <a href="simple_setup_aggregation_monitoring.php" class="btn">
                <i class="fas fa-tools"></i> Run Database Setup
            </a>
        </div>
        <?php endif; ?>

        <div class="tabs">
            <button class="tab active" onclick="switchTab('access')">
                <i class="fas fa-database"></i> Data Access Logs
            </button>
            <button class="tab" onclick="switchTab('mods')">
                <i class="fas fa-edit"></i> Modifications
            </button>
            <button class="tab" onclick="switchTab('alerts')">
                <i class="fas fa-exclamation-triangle"></i> Bulk Alerts
            </button>
        </div>

        <div id="access" class="tab-content active">
            <?php if (empty($audit_logs) && $tables_exist): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>No audit logs available yet. Logs will appear here as users interact with the system.</p>
                </div>
            <?php elseif (!empty($audit_logs)): ?>
            <table>
                <tr>
                    <th>Time</th>
                    <th>User</th>
                    <th>Role</th>
                    <th>Table</th>
                    <th>Operation</th>
                    <th>Records</th>
                    <th>IP</th>
                    <th>Flags</th>
                </tr>
                <?php foreach ($audit_logs as $log): ?>
                <tr>
                    <td><?php echo date('M j, g:i A', strtotime($log['access_timestamp'])); ?></td>
                    <td><?php echo htmlspecialchars($log['username'] ?? 'Unknown'); ?></td>
                    <td><?php echo htmlspecialchars($log['user_role']); ?></td>
                    <td><strong><?php echo htmlspecialchars($log['table_accessed']); ?></strong></td>
                    <td>
                        <span class="badge badge-<?php echo strtolower($log['operation_type']); ?>">
                            <?php echo $log['operation_type']; ?>
                        </span>
                    </td>
                    <td><?php echo $log['records_affected']; ?></td>
                    <td><small><?php echo htmlspecialchars($log['ip_address']); ?></small></td>
                    <td>
                        <?php if ($log['is_bulk_operation']): ?>
                            <span class="badge badge-bulk">BULK</span>
                        <?php endif; ?>
                        <?php if ($log['is_sensitive_data']): ?>
                            <span class="badge badge-sensitive">SENSITIVE</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
            <?php endif; ?>
        </div>

        <div id="mods" class="tab-content">
            <table>
                <tr>
                    <th>Time</th>
                    <th>User</th>
                    <th>Table</th>
                    <th>Record ID</th>
                    <th>Operation</th>
                    <th>Field</th>
                    <th>Old → New</th>
                </tr>
                <?php foreach ($modifications as $mod): ?>
                <tr>
                    <td><?php echo date('M j, g:i A', strtotime($mod['modification_timestamp'])); ?></td>
                    <td><?php echo htmlspecialchars($mod['username'] ?? 'Unknown'); ?></td>
                    <td><?php echo htmlspecialchars($mod['table_name']); ?></td>
                    <td><?php echo htmlspecialchars($mod['record_id']); ?></td>
                    <td>
                        <span class="badge badge-<?php echo strtolower($mod['operation_type']); ?>">
                            <?php echo $mod['operation_type']; ?>
                        </span>
                    </td>
                    <td><em><?php echo htmlspecialchars($mod['field_name']); ?></em></td>
                    <td>
                        <small>
                            <?php echo htmlspecialchars(substr($mod['old_value'] ?? '', 0, 30)); ?> →
                            <?php echo htmlspecialchars(substr($mod['new_value'] ?? '', 0, 30)); ?>
                        </small>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <div id="alerts" class="tab-content">
            <?php foreach ($bulk_alerts as $alert): ?>
                <div class="alert alert-<?php echo strtolower($alert['alert_level']); ?>">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div>
                        <strong><?php echo $alert['alert_level']; ?>:</strong>
                        <?php echo $alert['operation_type']; ?> on <?php echo $alert['table_accessed']; ?>
                        (<?php echo number_format($alert['records_count']); ?> records)
                        <br>
                        <small><?php echo date('M j, g:i A', strtotime($alert['alert_timestamp'])); ?> |
                        User: <?php echo htmlspecialchars($alert['username'] ?? 'Unknown'); ?></small>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div style="margin-top: 30px; text-align: center;">
            <a href="admin_dashboard.php" class="btn">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <script>
        function switchTab(tabName) {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            
            event.target.closest('.tab').classList.add('active');
            document.getElementById(tabName).classList.add('active');
        }
    </script>
</body>
</html>
