<?php
require_once 'session_check.php';
check_login(['admin']); // Only admin can access full DLP management
require_once 'dlp_system.php';

$dlp = new DataLossPreventionSystem();
$message = '';
$success = false;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'request_export':
            $export_type = $_POST['export_type'];
            $data_tables = explode(',', $_POST['data_tables']);
            $data_filters = json_decode($_POST['data_filters'] ?: '{}', true);
            $justification = $_POST['justification'];
            
            $result = $dlp->requestBulkExportApproval($export_type, $data_tables, $data_filters, $justification);
            $message = $result['success'] ? "Export request submitted successfully. Request ID: " . $result['request_id'] : $result['error'];
            $success = $result['success'];
            break;
            
        case 'approve_request':
            $request_id = $_POST['request_id'];
            $notes = $_POST['approval_notes'] ?? '';
            
            $result = $dlp->approveExportRequest($request_id, $notes);
            $message = $result['success'] ? $result['message'] : $result['error'];
            $success = $result['success'];
            break;
            
        case 'reject_request':
            $request_id = $_POST['request_id'];
            $rejection_notes = $_POST['rejection_notes'] ?? '';
            
            $result = $dlp->rejectExportRequest($request_id, $rejection_notes);
            $message = $result['success'] ? $result['message'] : $result['error'];
            $success = $result['success'];
            break;
            
        case 'classify_data':
            $table_name = $_POST['table_name'];
            $column_name = $_POST['column_name'];
            $classification_level = $_POST['classification_level'];
            $data_category = $_POST['data_category'];
            $retention_days = (int)$_POST['retention_days'];
            
            $result = $dlp->classifyData($table_name, $column_name, $classification_level, $data_category, $retention_days);
            $message = $result ? "Data classification updated successfully" : "Failed to update data classification";
            $success = $result;
            break;
            
        case 'run_retention':
            $results = $dlp->enforceRetentionPolicies();
            $total_deleted = array_sum(array_column($results, 'records_deleted'));
            $message = "Retention policies executed. Total records processed: " . $total_deleted;
            $success = true;
            break;
    }
}

// Get pending approval requests (admin/chief-staff see all, others see their own)
if (in_array($_SESSION['role'], ['admin', 'chief-staff'])) {
    $stmt = $conn->prepare("SELECT * FROM export_approval_requests WHERE status = 'pending' ORDER BY requested_at DESC");
    $stmt->execute();
    $pending_requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    // Get user's own requests
    $user_requests = $dlp->getUserExportRequests();
    $pending_requests = array_filter($user_requests, function($req) { return $req['status'] === 'pending'; });
}

// Get DLP statistics
$dlp_stats = $dlp->getDLPStats();

// Get current data classifications
$stmt = $conn->prepare("SELECT * FROM data_classification ORDER BY table_name, column_name");
$stmt->execute();
$classifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Loss Prevention Management</title>
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2980b9;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --dark-color: #2c3e50;
            --light-color: #ecf0f1;
            --border-color: #bdc3c7;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f8f9fa; color: var(--dark-color); line-height: 1.6; }
        
        .container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); color: white; padding: 30px; text-align: center; margin-bottom: 30px; border-radius: 10px; }
        .header h1 { font-size: 2.5rem; margin-bottom: 10px; }
        .header p { font-size: 1.1rem; opacity: 0.9; }
        
        .tabs { display: flex; background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 30px; overflow: hidden; }
        .tab-button { flex: 1; padding: 15px 20px; background: white; border: none; cursor: pointer; font-size: 1rem; transition: all 0.3s; border-bottom: 3px solid transparent; }
        .tab-button:hover { background: #f8f9fa; }
        .tab-button.active { background: var(--primary-color); color: white; border-bottom-color: var(--secondary-color); }
        
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        .card { background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 25px; overflow: hidden; }
        .card-header { background: var(--primary-color); color: white; padding: 20px; font-size: 1.2rem; font-weight: 600; }
        .card-body { padding: 25px; }
        
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; margin-bottom: 8px; font-weight: 500; color: var(--dark-color); }
        .form-control { width: 100%; padding: 12px 15px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 1rem; transition: border-color 0.3s; }
        .form-control:focus { border-color: var(--primary-color); outline: none; box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2); }
        
        .btn { display: inline-block; padding: 12px 24px; border: none; border-radius: 6px; font-size: 1rem; font-weight: 500; cursor: pointer; transition: all 0.3s; text-decoration: none; text-align: center; }
        .btn-primary { background: var(--primary-color); color: white; }
        .btn-primary:hover { background: var(--secondary-color); transform: translateY(-2px); }
        .btn-success { background: var(--success-color); color: white; }
        .btn-success:hover { background: #229954; }
        .btn-warning { background: var(--warning-color); color: white; }
        .btn-danger { background: var(--danger-color); color: white; }
        .btn-sm { padding: 8px 16px; font-size: 0.9rem; }
        
        .alert { padding: 15px 20px; border-radius: 6px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        .table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .table th, .table td { padding: 12px; text-align: left; border-bottom: 1px solid var(--border-color); }
        .table th { background: #f8f9fa; font-weight: 600; }
        .table tr:hover { background: #f8f9fa; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center; }
        .stat-card h3 { font-size: 2.2rem; margin-bottom: 10px; }
        .stat-card p { color: #666; font-size: 1rem; }
        .stat-card.primary h3 { color: var(--primary-color); }
        .stat-card.success h3 { color: var(--success-color); }
        .stat-card.warning h3 { color: var(--warning-color); }
        .stat-card.danger h3 { color: var(--danger-color); }
        
        .classification-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 500; text-transform: uppercase; }
        .classification-public { background: #d1ecf1; color: #0c5460; }
        .classification-internal { background: #fff3cd; color: #856404; }
        .classification-confidential { background: #f8d7da; color: #721c24; }
        .classification-restricted { background: #d1ecf1; color: #0c5460; background: #721c24; color: white; }
        
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; }
        
        @media (max-width: 768px) {
            .tabs { flex-direction: column; }
            .grid-2 { grid-template-columns: 1fr; }
            .stats-grid { grid-template-columns: 1fr; }
        }
        
        /* DLP Navigation */
        .dlp-nav { background: white; padding: 15px 30px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
        .dlp-nav a { color: var(--primary-color); text-decoration: none; font-weight: 500; margin-right: 20px; }
        .dlp-nav a:hover { color: var(--secondary-color); }
    </style>
</head>
<body>
    <div class="dlp-nav">
        <a href="admin_dashboard.php"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        <a href="secure_export.php">Secure Export</a>
        <a href="check_dlp.php">System Health</a>
        <a href="logout.php" style="float: right; color: var(--danger-color);"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
    
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-shield-alt"></i> Data Loss Prevention System</h1>
            <p>Comprehensive data security and compliance management</p>
        </div>
        
        <?php if ($message): ?>
        <div class="alert <?= $success ? 'alert-success' : 'alert-error' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
        <?php endif; ?>
        
        <!-- Statistics Dashboard -->
        <div class="stats-grid">
            <div class="stat-card primary">
                <h3><?= count($pending_requests) ?></h3>
                <p>Pending Export Requests</p>
            </div>
            <div class="stat-card warning">
                <h3><?= $dlp_stats['suspicious_activity'] ?></h3>
                <p>Suspicious Activities (7 days)</p>
            </div>
            <div class="stat-card success">
                <h3><?= count($classifications) ?></h3>
                <p>Data Classifications</p>
            </div>
            <div class="stat-card danger">
                <h3><?= array_sum(array_column($dlp_stats['export_requests'], 'count')) ?></h3>
                <p>Total Export Requests</p>
            </div>
        </div>
        
        <!-- Tabs Navigation -->
        <div class="tabs">
            <button class="tab-button active" onclick="showTab('approval')">
                <?= in_array($_SESSION['role'], ['admin', 'chief-staff']) ? 'Export Management' : 'My Export Requests' ?>
            </button>
            <?php if (in_array($_SESSION['role'], ['admin', 'chief-staff'])): ?>
            <button class="tab-button" onclick="showTab('classification')">Data Classification</button>
            <button class="tab-button" onclick="showTab('monitoring')">Activity Monitoring</button>
            <button class="tab-button" onclick="showTab('retention')">Retention Policies</button>
            <?php endif; ?>
        </div>
        
        <!-- Export Approval Tab -->
        <div id="approval" class="tab-content active">
            <div class="grid-2">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-file-export"></i> 
                        <?= in_array($_SESSION['role'], ['admin', 'chief-staff']) ? 'Request Export Approval' : 'Submit Export Request' ?>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="request_export">
                            
                            <div class="form-group">
                                <label class="form-label">Export Type</label>
                                <select name="export_type" class="form-control" required>
                                    <option value="">Select export type</option>
                                    <option value="staff_data">Staff Data Export</option>
                                    <option value="patient_records">Patient Records</option>
                                    <option value="system_logs">System Logs</option>
                                    <option value="financial_data">Financial Data</option>
                                    <option value="custom">Custom Export</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Data Tables (comma-separated)</label>
                                <input type="text" name="data_tables" class="form-control" placeholder="e.g., staff,users,appointments" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Data Filters (JSON format)</label>
                                <textarea name="data_filters" class="form-control" rows="3" placeholder='{"role": "doctor", "status": "active"}'></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Justification</label>
                                <textarea name="justification" class="form-control" rows="4" placeholder="Provide detailed justification for this export request..." required></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i> Submit Request
                            </button>
                        </form>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-clock"></i> Pending Approval Requests
                    </div>
                    <div class="card-body">
                        <?php if (empty($pending_requests)): ?>
                            <p>No pending requests.</p>
                        <?php else: ?>
                            <?php foreach ($pending_requests as $request): ?>
                            <div style="border: 1px solid var(--border-color); border-radius: 6px; padding: 15px; margin-bottom: 15px;">
                                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px;">
                                    <strong><?= htmlspecialchars($request['request_id']) ?></strong>
                                    <span class="classification-badge classification-<?= $request['classification_level'] ?>">
                                        <?= ucfirst($request['classification_level']) ?>
                                    </span>
                                </div>
                                <p><strong>Type:</strong> <?= htmlspecialchars($request['export_type']) ?></p>
                                <p><strong>Requester:</strong> <?= htmlspecialchars($request['requester_name']) ?> (<?= htmlspecialchars($request['requester_role']) ?>)</p>
                                <p><strong>Tables:</strong> <?= htmlspecialchars($request['data_tables']) ?></p>
                                <p><strong>Justification:</strong> <?= htmlspecialchars($request['justification']) ?></p>
                                <p><strong>Expires:</strong> <?= date('Y-m-d H:i', strtotime($request['expires_at'])) ?></p>
                                
                                <?php if (in_array($_SESSION['role'], ['admin', 'chief-staff'])): ?>
                                <div style="margin-top: 15px; display: flex; gap: 10px;">
                                    <form method="POST" style="flex: 1;">
                                        <input type="hidden" name="action" value="approve_request">
                                        <input type="hidden" name="request_id" value="<?= $request['request_id'] ?>">
                                        <div class="form-group">
                                            <input type="text" name="approval_notes" class="form-control" placeholder="Approval notes (optional)">
                                        </div>
                                        <button type="submit" class="btn btn-success btn-sm">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                    </form>
                                    
                                    <form method="POST" style="flex: 1;">
                                        <input type="hidden" name="action" value="reject_request">
                                        <input type="hidden" name="request_id" value="<?= $request['request_id'] ?>">
                                        <div class="form-group">
                                            <input type="text" name="rejection_notes" class="form-control" placeholder="Rejection reason (optional)">
                                        </div>
                                        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to reject this export request?');">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    </form>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Data Classification Tab -->
        <div id="classification" class="tab-content">
            <div class="grid-2">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-tags"></i> Add Data Classification
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="classify_data">
                            
                            <div class="form-group">
                                <label class="form-label">Table Name</label>
                                <input type="text" name="table_name" class="form-control" placeholder="e.g., staff" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Column Name</label>
                                <input type="text" name="column_name" class="form-control" placeholder="e.g., email" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Classification Level</label>
                                <select name="classification_level" class="form-control" required>
                                    <option value="public">Public</option>
                                    <option value="internal">Internal</option>
                                    <option value="confidential">Confidential</option>
                                    <option value="restricted">Restricted</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Data Category</label>
                                <input type="text" name="data_category" class="form-control" placeholder="e.g., personal_info" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Retention Days</label>
                                <input type="number" name="retention_days" class="form-control" value="365" required>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Add Classification
                            </button>
                        </form>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-list"></i> Current Classifications
                    </div>
                    <div class="card-body">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Table</th>
                                    <th>Column</th>
                                    <th>Level</th>
                                    <th>Category</th>
                                    <th>Retention</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($classifications as $class): ?>
                                <tr>
                                    <td><?= htmlspecialchars($class['table_name']) ?></td>
                                    <td><?= htmlspecialchars($class['column_name']) ?></td>
                                    <td>
                                        <span class="classification-badge classification-<?= $class['classification_level'] ?>">
                                            <?= ucfirst($class['classification_level']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($class['data_category']) ?></td>
                                    <td><?= $class['retention_days'] ?> days</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Activity Monitoring Tab -->
        <div id="monitoring" class="tab-content">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-chart-line"></i> Download Activity (Last 30 Days)
                </div>
                <div class="card-body">
                    <canvas id="downloadChart" style="max-height: 400px;"></canvas>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-exclamation-triangle"></i> Recent Suspicious Activities
                </div>
                <div class="card-body">
                    <?php
                    $stmt = $conn->prepare("SELECT * FROM download_activity WHERE suspicious_flag = 1 ORDER BY download_time DESC LIMIT 10");
                    $stmt->execute();
                    $suspicious = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    ?>
                    
                    <?php if (empty($suspicious)): ?>
                        <p>No suspicious activities detected.</p>
                    <?php else: ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>File Type</th>
                                    <th>Classification</th>
                                    <th>Time</th>
                                    <th>IP Address</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($suspicious as $activity): ?>
                                <tr>
                                    <td><?= htmlspecialchars($activity['user_name']) ?></td>
                                    <td><?= htmlspecialchars($activity['file_type']) ?></td>
                                    <td>
                                        <span class="classification-badge classification-<?= $activity['data_classification'] ?>">
                                            <?= ucfirst($activity['data_classification']) ?>
                                        </span>
                                    </td>
                                    <td><?= date('Y-m-d H:i:s', strtotime($activity['download_time'])) ?></td>
                                    <td><?= htmlspecialchars($activity['ip_address']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-download"></i> Recent Download Activity
                </div>
                <div class="card-body">
                    <?php
                    $stmt = $conn->prepare("SELECT * FROM download_activity ORDER BY download_time DESC LIMIT 20");
                    $stmt->execute();
                    $recent_downloads = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    ?>
                    
                    <?php if (empty($recent_downloads)): ?>
                        <div style="background: #fff3cd; padding: 15px; border-radius: 5px; color: #856404;">
                            <p><strong>⚠️ No download activity found</strong></p>
                            <p>This could mean:</p>
                            <ul>
                                <li>No files have been downloaded yet</li>
                                <li>The download_activity table needs to be created</li>
                                <li>Downloads aren't being logged properly</li>
                            </ul>
                            <p><a href="debug_download_activity.php" style="color: #856404; text-decoration: underline;">🔍 Run Debug Check</a></p>
                        </div>
                    <?php else: ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>File Name</th>
                                    <th>Type</th>
                                    <th>Classification</th>
                                    <th>Size</th>
                                    <th>Time</th>
                                    <th>IP Address</th>
                                    <th>Watermarked</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_downloads as $download): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($download['user_name']) ?></strong><br>
                                        <small style="color: #666;"><?= htmlspecialchars($download['user_role']) ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($download['file_name'] ?? 'Unknown') ?></td>
                                    <td>
                                        <span style="background: #e3f2fd; color: #1976d2; padding: 2px 8px; border-radius: 12px; font-size: 0.8rem;">
                                            <?= strtoupper(htmlspecialchars($download['file_type'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="classification-badge classification-<?= $download['data_classification'] ?>">
                                            <?= ucfirst($download['data_classification']) ?>
                                        </span>
                                    </td>
                                    <td><?= $download['file_size'] ? number_format($download['file_size']) . ' bytes' : 'N/A' ?></td>
                                    <td>
                                        <?= date('M j, Y', strtotime($download['download_time'])) ?><br>
                                        <small style="color: #666;"><?= date('H:i:s', strtotime($download['download_time'])) ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($download['ip_address']) ?></td>
                                    <td>
                                        <?php if ($download['watermarked']): ?>
                                            <span style="color: #28a745;">✓ Yes</span>
                                        <?php else: ?>
                                            <span style="color: #6c757d;">- No</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <div style="margin-top: 15px; padding: 10px; background: #f8f9fa; border-radius: 5px;">
                            <small style="color: #666;">
                                Showing last <?= count($recent_downloads) ?> download activities. 
                                <a href="debug_download_activity.php">🔍 View detailed debug info</a>
                            </small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Retention Policies Tab -->
        <div id="retention" class="tab-content">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-calendar-alt"></i> Retention Policy Management
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="run_retention">
                        <p>Execute retention policies to automatically clean up old data according to defined rules.</p>
                        <p><strong>Warning:</strong> This action will permanently delete data that exceeds retention periods.</p>
                        <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to execute retention policies? This will permanently delete old data.')">
                            <i class="fas fa-trash-alt"></i> Execute Retention Policies
                        </button>
                    </form>
                    
                    <hr style="margin: 30px 0;">
                    
                    <h4>Current Retention Policies</h4>
                    <?php
                    $stmt = $conn->prepare("SELECT * FROM retention_policies ORDER BY table_name");
                    $stmt->execute();
                    $policies = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    ?>
                    
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Policy Name</th>
                                <th>Table</th>
                                <th>Retention Days</th>
                                <th>Classification</th>
                                <th>Auto Delete</th>
                                <th>Last Executed</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($policies as $policy): ?>
                            <tr>
                                <td><?= htmlspecialchars($policy['policy_name']) ?></td>
                                <td><?= htmlspecialchars($policy['table_name']) ?></td>
                                <td><?= $policy['retention_days'] ?> days</td>
                                <td>
                                    <span class="classification-badge classification-<?= $policy['classification_level'] ?>">
                                        <?= ucfirst($policy['classification_level']) ?>
                                    </span>
                                </td>
                                <td><?= $policy['auto_delete'] ? 'Yes' : 'No' ?></td>
                                <td><?= $policy['last_executed'] ? date('Y-m-d H:i', strtotime($policy['last_executed'])) : 'Never' ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function showTab(tabName) {
            // Hide all tab contents
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(content => content.classList.remove('active'));
            
            // Remove active class from all tab buttons
            const tabButtons = document.querySelectorAll('.tab-button');
            tabButtons.forEach(button => button.classList.remove('active'));
            
            // Show selected tab content
            document.getElementById(tabName).classList.add('active');
            
            // Add active class to clicked button
            event.target.classList.add('active');
        }
    </script>
</body>
</html>