<?php
require_once 'session_check.php';
check_login(); // All authenticated users can request exports
require_once 'dlp_system.php';

$dlp = new DataLossPreventionSystem();
$message = '';
$success = false;

// Handle export request submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request'])) {
    $export_type = $_POST['export_type'];
    $data_tables = explode(',', trim($_POST['data_tables']));
    $data_filters = json_decode($_POST['data_filters'] ?: '{}', true);
    $justification = $_POST['justification'];
    
    $result = $dlp->requestBulkExportApproval($export_type, $data_tables, $data_filters, $justification);
    
    if ($result['success']) {
        if ($result['auto_approved']) {
            $message = "✅ " . $result['message'] . " <br><strong>Request ID:</strong> " . $result['request_id'] . "<br><a href='secure_export.php?request_id=" . urlencode($result['request_id']) . "' style='color: #007bff; font-weight: bold;'>Click here to download now →</a>";
        } else {
            $message = "📋 " . $result['message'] . " <br><strong>Request ID:</strong> " . $result['request_id'];
        }
    } else {
        $message = $result['error'];
    }
    $success = $result['success'];
}

// Handle export request rejection (admin/chief-staff only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_request'])) {
    if (in_array($_SESSION['role'], ['admin', 'chief-staff'])) {
        $request_id = $_POST['request_id'];
        $rejection_notes = $_POST['rejection_notes'] ?? 'Rejected by ' . $_SESSION['username'];
        
        $result = $dlp->rejectExportRequest($request_id, $rejection_notes);
        $message = $result['success'] ? '✅ ' . $result['message'] : '❌ ' . $result['error'];
        $success = $result['success'];
    } else {
        $message = '❌ You do not have permission to reject export requests.';
        $success = false;
    }
}

// Get user's export requests
$user_requests = $dlp->getUserExportRequests();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Data Export</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f6fa; }
        
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .header h1 { font-size: 2rem; margin-bottom: 10px; }
        .header p { opacity: 0.9; }
        
        .card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 25px;
            overflow: hidden;
        }
        
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            font-size: 1.3rem;
            font-weight: 600;
        }
        
        .card-body { padding: 30px; }
        
        .form-group { margin-bottom: 20px; }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: #667eea;
            outline: none;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        textarea.form-control { resize: vertical; min-height: 100px; }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        
        .btn-secondary {
            background: #95a5a6;
            color: white;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        
        .request-item {
            background: #f8f9fa;
            border: 2px solid #dee2e6;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
        }
        
        .request-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .request-id {
            font-weight: bold;
            color: #667eea;
            font-size: 1.1rem;
        }
        
        .status-badge {
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-pending { background: #fff3cd; color: #856404; }
        .status-approved { background: #d4edda; color: #155724; }
        .status-denied { background: #f8d7da; color: #721c24; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        .status-expired { background: #e2e3e5; color: #383d41; }
        
        .classification-badge {
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .class-public { background: #d1ecf1; color: #0c5460; }
        .class-internal { background: #fff3cd; color: #856404; }
        .class-confidential { background: #f8d7da; color: #721c24; }
        .class-restricted { background: #721c24; color: white; }
        
        .info-row {
            display: grid;
            grid-template-columns: 150px 1fr;
            padding: 5px 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .info-label { font-weight: 600; color: #6c757d; }
        .info-value { color: #495057; }
        
        .nav-bar {
            background: white;
            padding: 15px 30px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        
        .nav-bar a {
            color: #667eea;
            text-decoration: none;
            margin-right: 20px;
            font-weight: 500;
        }
        
        .nav-bar a:hover { color: #764ba2; }
        
        .help-text {
            background: #e7f3ff;
            border-left: 4px solid #2196f3;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        
        .help-text h4 {
            color: #1976d2;
            margin-bottom: 10px;
        }
        
        .help-text ul {
            margin-left: 20px;
            line-height: 1.8;
        }
    </style>
</head>
<body>
    <div class="nav-bar">
        <a href="<?php echo $_SESSION['role'] === 'admin' ? 'admin_dashboard.php' : ($_SESSION['role'] === 'chief-staff' ? 'chief_staff_dashboard.php' : 'staff_dashboard.php'); ?>">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
        <?php if ($_SESSION['role'] === 'admin'): ?>
            <a href="dlp_management.php">DLP Management</a>
        <?php endif; ?>
        <a href="logout.php" style="float: right; color: #dc3545;">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>

    <div class="container">
        <div class="header">
            <h1><i class="fas fa-file-export"></i> Request Data Export</h1>
            <p>Submit a request to export sensitive or bulk data with proper justification</p>
        </div>

        <?php if ($message): ?>
        <div class="alert <?php echo $success ? 'alert-success' : 'alert-error'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>

        <div class="help-text">
            <h4><i class="fas fa-info-circle"></i> How Data Export Works (Auto-Approval System)</h4>
            <ul>
                <li><strong>PUBLIC</strong> data → <span style="color: #28a745; font-weight: bold;">✅ AUTO-APPROVED instantly</span> for all users</li>
                <li><strong>INTERNAL</strong> data → <span style="color: #28a745; font-weight: bold;">✅ AUTO-APPROVED instantly</span> for authenticated staff (logged automatically)</li>
                <li><strong>CONFIDENTIAL</strong> data → <span style="color: #ffc107;">📋 Requires approval</span> from supervisor or admin (auto-approved for chief-staff/admin)</li>
                <li><strong>RESTRICTED</strong> data → <span style="color: #dc3545;">🔒 Requires admin approval only</span> (auto-approved for admin)</li>
            </ul>
            <p style="margin-top: 10px; padding: 10px; background: #e7f3ff; border-radius: 5px;">
                <strong>Note:</strong> Most data exports for staff will be approved automatically! Only highly sensitive data requires manual approval.
            </p>
        </div>

        <div class="card">
            <div class="card-header">
                <i class="fas fa-paper-plane"></i> New Export Request
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-file-alt"></i> Export Type
                        </label>
                        <select name="export_type" class="form-control" required>
                            <option value="">Select export type...</option>
                            <option value="staff_data">Staff Data Export</option>
                            <option value="patient_records">Patient Records</option>
                            <option value="appointment_data">Appointment Data</option>
                            <option value="treatment_records">Treatment Records</option>
                            <option value="system_logs">System Logs</option>
                            <option value="financial_data">Financial Data</option>
                            <option value="custom">Custom Export</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-database"></i> Data Tables (comma-separated)
                        </label>
                        <input 
                            type="text" 
                            name="data_tables" 
                            class="form-control" 
                            placeholder="e.g., staff, appointments, patients"
                            required
                        >
                        <small style="color: #6c757d;">Enter database table names separated by commas</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-filter"></i> Data Filters (JSON format) - Optional
                        </label>
                        <textarea 
                            name="data_filters" 
                            class="form-control" 
                            placeholder='{"role": "doctor", "status": "active", "date_from": "2025-01-01"}'
                        ></textarea>
                        <small style="color: #6c757d;">Optional: Use JSON format to filter exported data</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-comment-dots"></i> Justification <span style="color: red;">*</span>
                        </label>
                        <textarea 
                            name="justification" 
                            class="form-control" 
                            placeholder="Provide detailed justification for this export request...&#10;&#10;Example:&#10;- Purpose: Monthly staff performance report&#10;- Requestor: HR Department&#10;- Data usage: Internal review and compliance audit&#10;- Retention: Will be deleted after 30 days"
                            required
                        ></textarea>
                        <small style="color: #6c757d;">Provide a clear business justification for requesting this data export</small>
                    </div>

                    <button type="submit" name="submit_request" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Submit Export Request
                    </button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <i class="fas fa-history"></i> My Export Requests
            </div>
            <div class="card-body">
                <?php if (empty($user_requests)): ?>
                    <p style="text-align: center; color: #6c757d; padding: 30px;">
                        <i class="fas fa-inbox" style="font-size: 3rem; display: block; margin-bottom: 15px; opacity: 0.3;"></i>
                        No export requests found. Submit your first request above!
                    </p>
                <?php else: ?>
                    <?php foreach ($user_requests as $req): ?>
                    <div class="request-item">
                        <div class="request-header">
                            <span class="request-id"><?php echo htmlspecialchars($req['request_id']); ?></span>
                            <span class="status-badge status-<?php echo strtolower($req['status']); ?>">
                                <?php echo ucfirst($req['status']); ?>
                            </span>
                        </div>

                        <div class="info-row">
                            <div class="info-label">Export Type:</div>
                            <div class="info-value"><?php echo htmlspecialchars($req['export_type']); ?></div>
                        </div>

                        <div class="info-row">
                            <div class="info-label">Classification:</div>
                            <div class="info-value">
                                <span class="classification-badge class-<?php echo strtolower($req['classification_level']); ?>">
                                    <?php echo strtoupper($req['classification_level']); ?>
                                </span>
                            </div>
                        </div>

                        <div class="info-row">
                            <div class="info-label">Tables:</div>
                            <div class="info-value"><?php echo htmlspecialchars($req['data_tables']); ?></div>
                        </div>

                        <div class="info-row">
                            <div class="info-label">Justification:</div>
                            <div class="info-value"><?php echo htmlspecialchars($req['justification']); ?></div>
                        </div>

                        <div class="info-row">
                            <div class="info-label">Requested:</div>
                            <div class="info-value"><?php echo date('Y-m-d H:i', strtotime($req['requested_at'])); ?></div>
                        </div>

                        <?php if ($req['status'] === 'pending'): ?>
                        <div class="info-row">
                            <div class="info-label">Expires:</div>
                            <div class="info-value"><?php echo date('Y-m-d H:i', strtotime($req['expires_at'])); ?></div>
                        </div>
                        
                        <?php if (in_array($_SESSION['role'], ['admin', 'chief-staff'])): ?>
                        <div style="margin-top: 15px; padding-top: 15px; border-top: 2px solid #ffc107;">
                            <form method="POST" style="display: inline-block; margin-right: 10px;" onsubmit="return confirm('Are you sure you want to reject this export request?');">
                                <input type="hidden" name="reject_request" value="1">
                                <input type="hidden" name="request_id" value="<?php echo htmlspecialchars($req['request_id']); ?>">
                                <input type="text" name="rejection_notes" placeholder="Rejection reason (optional)" style="padding: 8px; border: 1px solid #ddd; border-radius: 5px; margin-right: 10px; width: 300px;">
                                <button type="submit" class="btn btn-secondary" style="background: #dc3545; padding: 10px 20px;">
                                    <i class="fas fa-times"></i> Reject Request
                                </button>
                            </form>
                        </div>
                        <?php endif; ?>
                        <?php endif; ?>

                        <?php if ($req['status'] === 'approved'): ?>
                        <div style="margin-top: 15px; padding-top: 15px; border-top: 2px solid #28a745;">
                            <a href="secure_export.php?request_id=<?php echo urlencode($req['request_id']); ?>" class="btn btn-primary">
                                <i class="fas fa-download"></i> Download Export
                            </a>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($req['status'] === 'rejected'): ?>
                        <div style="margin-top: 15px; padding: 10px; background: #f8d7da; border-radius: 5px; border-left: 4px solid #dc3545;">
                            <strong style="color: #721c24;">❌ Request Rejected</strong>
                            <?php if (!empty($req['approval_notes'])): ?>
                            <p style="margin: 5px 0 0 0; color: #721c24;">Reason: <?php echo htmlspecialchars($req['approval_notes']); ?></p>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($req['approval_notes'])): ?>
                        <div style="margin-top: 15px; padding: 10px; background: #f1f3f5; border-radius: 5px;">
                            <strong>Admin Notes:</strong> <?php echo htmlspecialchars($req['approval_notes']); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
