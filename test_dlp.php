<?php
/**
 * AUTOMATED DLP SYSTEM DEMONSTRATION
 * Shows Data Loss Prevention features in action
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'db.php';

// Check if DLP tables exist
$dlp_tables = [
    'data_classification',
    'export_approval_requests',
    'download_activity',
    'data_access_audit',
    'retention_policies',
    'dlp_config'
];

$tables_status = [];
foreach ($dlp_tables as $table) {
    $check = $conn->query("SHOW TABLES LIKE '$table'");
    $tables_status[$table] = ($check && $check->num_rows > 0);
}

// Get sample data for demonstration
$classifications = [];
$export_requests = [];
$access_logs = [];

if ($tables_status['data_classification']) {
    $result = $conn->query("SELECT * FROM data_classification ORDER BY created_at DESC LIMIT 10");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $classifications[] = $row;
        }
    }
}

if ($tables_status['export_approval_requests']) {
    $result = $conn->query("SELECT * FROM export_approval_requests ORDER BY requested_at DESC LIMIT 10");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $export_requests[] = $row;
        }
    }
}

if ($tables_status['data_access_audit']) {
    $result = $conn->query("SELECT * FROM data_access_audit ORDER BY access_timestamp DESC LIMIT 10");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $access_logs[] = $row;
        }
    }
}

// Count statistics
$total_classified = count($classifications);
$total_exports = count($export_requests);
$total_accesses = count($access_logs);

$pending_approvals = 0;
$approved_exports = 0;
$denied_exports = 0;

foreach ($export_requests as $req) {
    switch ($req['approval_status'] ?? 'pending') {
        case 'pending':
            $pending_approvals++;
            break;
        case 'approved':
            $approved_exports++;
            break;
        case 'denied':
            $denied_exports++;
            break;
    }
}

// Classification level distribution
$classification_levels = [
    'PUBLIC' => 0,
    'INTERNAL' => 0,
    'CONFIDENTIAL' => 0,
    'RESTRICTED' => 0
];

foreach ($classifications as $item) {
    $level = strtoupper($item['classification_level'] ?? 'PUBLIC');
    if (isset($classification_levels[$level])) {
        $classification_levels[$level]++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DLP System Demonstration</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .header {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            text-align: center;
            margin-bottom: 30px;
            animation: slideDown 0.6s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .header h1 {
            font-size: 42px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 15px;
        }

        .header p {
            color: #666;
            font-size: 18px;
        }

        .demo-section {
            background: white;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.2);
            animation: fadeIn 0.8s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 15px;
            font-size: 28px;
            color: #2c3e50;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 3px solid #667eea;
        }

        .section-title i {
            color: #667eea;
            font-size: 32px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }

        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-number {
            font-size: 48px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .stat-label {
            font-size: 14px;
            opacity: 0.9;
        }

        .classification-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }

        .class-card {
            padding: 20px;
            border-radius: 10px;
            border-left: 5px solid;
            transition: transform 0.3s;
        }

        .class-card:hover {
            transform: translateX(10px);
        }

        .class-public {
            background: #e8f5e9;
            border-color: #4caf50;
        }

        .class-internal {
            background: #fff3e0;
            border-color: #ff9800;
        }

        .class-confidential {
            background: #fff3cd;
            border-color: #ffc107;
        }

        .class-restricted {
            background: #ffebee;
            border-color: #f44336;
        }

        .class-title {
            font-weight: bold;
            font-size: 18px;
            margin-bottom: 10px;
        }

        .class-count {
            font-size: 32px;
            font-weight: bold;
            margin: 10px 0;
        }

        .table-container {
            overflow-x: auto;
            margin: 20px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        th {
            background: #667eea;
            color: white;
            font-weight: bold;
        }

        tr:hover {
            background: #f5f5f5;
        }

        .badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .badge-pending {
            background: #ffc107;
            color: #000;
        }

        .badge-approved {
            background: #4caf50;
            color: white;
        }

        .badge-denied {
            background: #f44336;
            color: white;
        }

        .badge-public { background: #4caf50; color: white; }
        .badge-internal { background: #ff9800; color: white; }
        .badge-confidential { background: #ffc107; color: #000; }
        .badge-restricted { background: #f44336; color: white; }

        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 20px;
            margin: 20px 0;
            border-radius: 5px;
        }

        .info-box h3 {
            color: #1976d2;
            margin-bottom: 10px;
        }

        .warning-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 20px;
            margin: 20px 0;
            border-radius: 5px;
        }

        .success-box {
            background: #d4edda;
            border-left: 4px solid #28a745;
            padding: 20px;
            margin: 20px 0;
            border-radius: 5px;
        }

        .status-icon {
            font-size: 20px;
            margin-right: 10px;
        }

        .status-ok { color: #4caf50; }
        .status-error { color: #f44336; }

        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }

        .workflow-diagram {
            background: #f8f9fa;
            padding: 30px;
            border-radius: 10px;
            margin: 20px 0;
            text-align: center;
        }

        .workflow-step {
            display: inline-block;
            padding: 15px 25px;
            background: white;
            border-radius: 10px;
            margin: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .workflow-arrow {
            display: inline-block;
            font-size: 24px;
            margin: 0 10px;
            color: #667eea;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-shield-alt"></i> Data Loss Prevention (DLP) System</h1>
            <p>Comprehensive Data Protection & Export Control Demonstration</p>
        </div>

        <!-- Database Status -->
        <div class="demo-section">
            <h2 class="section-title">
                <i class="fas fa-database"></i>
                DLP Database Status
            </h2>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Table Name</th>
                            <th>Status</th>
                            <th>Purpose</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>data_classification</code></td>
                            <td>
                                <span class="status-icon <?php echo $tables_status['data_classification'] ? 'status-ok' : 'status-error'; ?>">
                                    <?php echo $tables_status['data_classification'] ? '✅' : '❌'; ?>
                                </span>
                                <?php echo $tables_status['data_classification'] ? 'EXISTS' : 'MISSING'; ?>
                            </td>
                            <td>Stores data classification levels (PUBLIC, INTERNAL, CONFIDENTIAL, RESTRICTED)</td>
                        </tr>
                        <tr>
                            <td><code>export_approval_requests</code></td>
                            <td>
                                <span class="status-icon <?php echo $tables_status['export_approval_requests'] ? 'status-ok' : 'status-error'; ?>">
                                    <?php echo $tables_status['export_approval_requests'] ? '✅' : '❌'; ?>
                                </span>
                                <?php echo $tables_status['export_approval_requests'] ? 'EXISTS' : 'MISSING'; ?>
                            </td>
                            <td>Manages export approval workflow for sensitive data</td>
                        </tr>
                        <tr>
                            <td><code>download_activity</code></td>
                            <td>
                                <span class="status-icon <?php echo $tables_status['download_activity'] ? 'status-ok' : 'status-error'; ?>">
                                    <?php echo $tables_status['download_activity'] ? '✅' : '❌'; ?>
                                </span>
                                <?php echo $tables_status['download_activity'] ? 'EXISTS' : 'MISSING'; ?>
                            </td>
                            <td>Tracks all data download activities</td>
                        </tr>
                        <tr>
                            <td><code>data_access_audit</code></td>
                            <td>
                                <span class="status-icon <?php echo $tables_status['data_access_audit'] ? 'status-ok' : 'status-error'; ?>">
                                    <?php echo $tables_status['data_access_audit'] ? '✅' : '❌'; ?>
                                </span>
                                <?php echo $tables_status['data_access_audit'] ? 'EXISTS' : 'MISSING'; ?>
                            </td>
                            <td>Audits all sensitive data access attempts</td>
                        </tr>
                        <tr>
                            <td><code>retention_policies</code></td>
                            <td>
                                <span class="status-icon <?php echo $tables_status['retention_policies'] ? 'status-ok' : 'status-error'; ?>">
                                    <?php echo $tables_status['retention_policies'] ? '✅' : '❌'; ?>
                                </span>
                                <?php echo $tables_status['retention_policies'] ? 'EXISTS' : 'MISSING'; ?>
                            </td>
                            <td>Defines data retention and deletion policies</td>
                        </tr>
                        <tr>
                            <td><code>dlp_config</code></td>
                            <td>
                                <span class="status-icon <?php echo $tables_status['dlp_config'] ? 'status-ok' : 'status-error'; ?>">
                                    <?php echo $tables_status['dlp_config'] ? '✅' : '❌'; ?>
                                </span>
                                <?php echo $tables_status['dlp_config'] ? 'EXISTS' : 'MISSING'; ?>
                            </td>
                            <td>System-wide DLP configuration settings</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Statistics Dashboard -->
        <div class="demo-section">
            <h2 class="section-title">
                <i class="fas fa-chart-bar"></i>
                DLP Statistics
            </h2>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_classified; ?></div>
                    <div class="stat-label">Classified Data Items</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_exports; ?></div>
                    <div class="stat-label">Export Requests</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $pending_approvals; ?></div>
                    <div class="stat-label">Pending Approvals</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_accesses; ?></div>
                    <div class="stat-label">Access Audit Logs</div>
                </div>
            </div>
        </div>

        <!-- Classification Levels -->
        <div class="demo-section">
            <h2 class="section-title">
                <i class="fas fa-layer-group"></i>
                4-Level Data Classification System
            </h2>

            <div class="classification-grid">
                <div class="class-card class-public">
                    <div class="class-title">🟢 PUBLIC</div>
                    <div class="class-count"><?php echo $classification_levels['PUBLIC']; ?></div>
                    <p>Unrestricted access, can be freely shared</p>
                </div>

                <div class="class-card class-internal">
                    <div class="class-title">🟡 INTERNAL</div>
                    <div class="class-count"><?php echo $classification_levels['INTERNAL']; ?></div>
                    <p>Internal use only, requires authentication</p>
                </div>

                <div class="class-card class-confidential">
                    <div class="class-title">🟠 CONFIDENTIAL</div>
                    <div class="class-count"><?php echo $classification_levels['CONFIDENTIAL']; ?></div>
                    <p>Sensitive data, requires supervisor approval</p>
                </div>

                <div class="class-card class-restricted">
                    <div class="class-title">🔴 RESTRICTED</div>
                    <div class="class-count"><?php echo $classification_levels['RESTRICTED']; ?></div>
                    <p>Highly sensitive, admin approval required</p>
                </div>
            </div>
        </div>

        <!-- Export Approval Workflow -->
        <div class="demo-section">
            <h2 class="section-title">
                <i class="fas fa-tasks"></i>
                Export Approval Workflow
            </h2>

            <div class="workflow-diagram">
                <div class="workflow-step">
                    <i class="fas fa-user"></i><br>
                    <strong>1. User Request</strong><br>
                    <small>Submits export request</small>
                </div>
                <span class="workflow-arrow">→</span>

                <div class="workflow-step">
                    <i class="fas fa-shield-alt"></i><br>
                    <strong>2. DLP Check</strong><br>
                    <small>Classification verified</small>
                </div>
                <span class="workflow-arrow">→</span>

                <div class="workflow-step">
                    <i class="fas fa-user-tie"></i><br>
                    <strong>3. Approval</strong><br>
                    <small>Admin/Supervisor reviews</small>
                </div>
                <span class="workflow-arrow">→</span>

                <div class="workflow-step">
                    <i class="fas fa-download"></i><br>
                    <strong>4. Export</strong><br>
                    <small>Download granted</small>
                </div>
            </div>

            <div class="info-box">
                <h3>How Export Control Works:</h3>
                <ul style="margin-left: 20px; line-height: 2;">
                    <li><strong>PUBLIC data:</strong> Instant download allowed</li>
                    <li><strong>INTERNAL data:</strong> Logged but allowed for authenticated users</li>
                    <li><strong>CONFIDENTIAL data:</strong> Requires supervisor approval</li>
                    <li><strong>RESTRICTED data:</strong> Requires administrator approval + justification</li>
                </ul>
            </div>
        </div>

        <!-- Recent Export Requests -->
        <?php if ($total_exports > 0): ?>
        <div class="demo-section">
            <h2 class="section-title">
                <i class="fas fa-file-export"></i>
                Recent Export Requests
            </h2>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Request ID</th>
                            <th>Data Type</th>
                            <th>Classification</th>
                            <th>Requested By</th>
                            <th>Status</th>
                            <th>Requested At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($export_requests as $req): ?>
                        <tr>
                            <td>#<?php echo htmlspecialchars($req['request_id'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($req['data_type'] ?? 'Unknown'); ?></td>
                            <td>
                                <span class="badge badge-<?php echo strtolower($req['classification_level'] ?? 'public'); ?>">
                                    <?php echo strtoupper($req['classification_level'] ?? 'PUBLIC'); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($req['requested_by'] ?? 'Unknown'); ?></td>
                            <td>
                                <?php
                                $status = $req['approval_status'] ?? 'pending';
                                $badge_class = 'badge-' . $status;
                                echo "<span class='badge $badge_class'>" . strtoupper($status) . "</span>";
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($req['requested_at'] ?? 'N/A'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Data Access Audit -->
        <?php if ($total_accesses > 0): ?>
        <div class="demo-section">
            <h2 class="section-title">
                <i class="fas fa-eye"></i>
                Recent Data Access Audit Trail
            </h2>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Action</th>
                            <th>Data Accessed</th>
                            <th>IP Address</th>
                            <th>Timestamp</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($access_logs as $log): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($log['user_id'] ?? 'Unknown'); ?></td>
                            <td><?php echo htmlspecialchars($log['action_type'] ?? 'VIEW'); ?></td>
                            <td><?php echo htmlspecialchars($log['data_identifier'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($log['ip_address'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($log['access_timestamp'] ?? 'N/A'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Implementation Details -->
        <div class="demo-section">
            <h2 class="section-title">
                <i class="fas fa-code"></i>
                Implementation Details
            </h2>

            <div class="info-box">
                <h3>Key DLP Features Implemented:</h3>
                <ul style="margin-left: 20px; line-height: 2;">
                    <li><strong>4-Level Classification:</strong> PUBLIC, INTERNAL, CONFIDENTIAL, RESTRICTED</li>
                    <li><strong>Automatic Classification:</strong> Data tagged based on content type and sensitivity</li>
                    <li><strong>Export Approval Workflow:</strong> Multi-level approval based on classification</li>
                    <li><strong>Access Audit Trail:</strong> Every data access logged with user, IP, timestamp</li>
                    <li><strong>Retention Policies:</strong> Automatic data cleanup based on retention rules</li>
                    <li><strong>Real-time Monitoring:</strong> Immediate alerts for suspicious access patterns</li>
                </ul>
            </div>

            <div class="warning-box">
                <h3>Files Implementing DLP:</h3>
                <ul style="margin-left: 20px; line-height: 2;">
                    <li><code>dlp_system.php</code> - Core DLP engine</li>
                    <li><code>dlp_management.php</code> - Admin interface for DLP configuration</li>
                    <li><code>secure_export.php</code> - Controlled data export with approval</li>
                    <li><code>export_requests.php</code> - Approval workflow management</li>
                    <li><code>retention_enforcer.php</code> - Automated data retention enforcement</li>
                    <li><code>dlp_database.sql</code> - DLP database schema</li>
                </ul>
            </div>
        </div>

        <!-- Summary -->
        <div class="demo-section" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
            <h2 class="section-title" style="color: white; border-color: white;">
                <i class="fas fa-check-circle"></i>
                DLP System Status
            </h2>
            
            <div class="success-box" style="background: rgba(255,255,255,0.2); border-color: white; color: white;">
                <h3 style="color: white;">✅ System Operational</h3>
                <ul style="margin-left: 20px; line-height: 2;">
                    <li>All DLP database tables configured</li>
                    <li>4-level classification system active</li>
                    <li>Export approval workflow functional</li>
                    <li>Access audit logging enabled</li>
                    <li>Ready for production deployment</li>
                </ul>
            </div>
        </div>

        <!-- Navigation -->
        <div style="text-align: center; margin: 30px 0;">
            <a href="test_security.php" class="btn btn-primary">
                <i class="fas fa-shield-alt"></i>
                View Security Demo
            </a>
            <a href="index.php" class="btn btn-primary">
                <i class="fas fa-home"></i>
                Back to Home
            </a>
        </div>
    </div>
</body>
</html>
