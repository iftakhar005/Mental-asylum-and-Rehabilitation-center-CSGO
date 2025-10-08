<?php
require_once 'session_check.php';
check_login(['doctor', 'therapist', 'nurse', 'receptionist', 'staff']);
require_once 'dlp_system.php';

$dlp = new DataLossPreventionSystem();
$message = '';
$success = false;

// No need for download success message handling since file downloads directly

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'request_export') {
        $export_type = $_POST['export_type'];
        $data_tables = explode(',', trim($_POST['data_tables']));
        $data_filters = json_decode($_POST['data_filters'] ?: '{}', true);
        $justification = $_POST['justification'];
        
        $result = $dlp->requestBulkExportApproval($export_type, $data_tables, $data_filters, $justification);
        $message = $result['success'] ? "Export request submitted successfully. Request ID: " . $result['request_id'] : $result['error'];
        $success = $result['success'];
    }
}

// Get user's own requests
$user_requests = $dlp->getUserExportRequests();
$current_role = $_SESSION['role'];
$username = $_SESSION['username'] ?? 'User';

// Get role-specific table suggestions
$role_tables = [
    'doctor' => ['patients', 'appointments', 'treatments', 'medical_records'],
    'therapist' => ['patients', 'appointments', 'treatments', 'therapy_sessions'],
    'nurse' => ['patients', 'appointments', 'medical_records', 'medications'],
    'receptionist' => ['patients', 'appointments', 'staff'],
    'staff' => ['patients', 'appointments']
];

$suggested_tables = $role_tables[$current_role] ?? ['patients'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export Requests - <?= ucfirst($current_role) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #3498db;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --border-color: #ddd;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            color: #333;
            line-height: 1.6;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .header h1 { font-size: 2.5rem; margin-bottom: 0.5rem; }
        .header p { font-size: 1.1rem; opacity: 0.9; }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .back-nav {
            margin-bottom: 2rem;
        }
        
        .back-nav a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: color 0.3s;
        }
        
        .back-nav a:hover { color: var(--success-color); }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            overflow: hidden;
        }
        
        .card-header {
            background: var(--primary-color);
            color: white;
            padding: 1.5rem;
            font-size: 1.2rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .card-body { padding: 2rem; }
        
        .form-group { margin-bottom: 1.5rem; }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #555;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--border-color);
            border-radius: 6px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
        }
        
        .btn-primary {
            background: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        
        .table th, .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        .table th {
            background: #f8f9fa;
            font-weight: 600;
        }
        
        .table tr:hover { background: #f8f9fa; }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .status-pending { background: #fff3cd; color: #856404; }
        .status-approved { background: #d4edda; color: #155724; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        
        .suggestions {
            background: #e3f2fd;
            border: 1px solid #bbdefb;
            border-radius: 6px;
            padding: 10px;
            margin-top: 8px;
            font-size: 0.9rem;
            color: #1565c0;
        }
        
        .suggestions strong { color: #0d47a1; }
        
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; }
        
        @media (max-width: 768px) {
            .grid-2 { grid-template-columns: 1fr; }
            .header h1 { font-size: 2rem; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <h1><i class="fas fa-shield-alt"></i> Export Requests</h1>
            <p>Submit and track your data export requests - <?= ucfirst($current_role) ?> Dashboard</p>
        </div>
    </div>
    
    <div class="container">
        <div class="back-nav">
            <a href="<?= strtolower($current_role) ?>_dashboard.php">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
        
        <?php if ($message): ?>
        <div class="alert <?= $success ? 'alert-success' : 'alert-error' ?>">
            <i class="fas <?= $success ? 'fa-check-circle' : 'fa-exclamation-triangle' ?>"></i>
            <?= htmlspecialchars($message) ?>
        </div>
        <?php endif; ?>
        
        <div class="grid-2">
            <!-- Submit Request Form -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-paper-plane"></i> Submit New Export Request
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="request_export">
                        
                        <div class="form-group">
                            <label class="form-label">Export Type</label>
                            <select name="export_type" class="form-control" required>
                                <option value="">Select export type</option>
                                <option value="patient_report">Patient Report</option>
                                <option value="appointment_data">Appointment Data</option>
                                <option value="treatment_records">Treatment Records</option>
                                <option value="custom_report">Custom Report</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Data Tables</label>
                            <input type="text" name="data_tables" class="form-control" 
                                   placeholder="e.g., patients, appointments" required>
                            <div class="suggestions">
                                <strong>Suggested for <?= ucfirst($current_role) ?>:</strong> 
                                <?= implode(', ', $suggested_tables) ?>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Data Filters (Optional)</label>
                            <textarea name="data_filters" class="form-control" rows="3" 
                                      placeholder='{"status": "active", "date_range": "2024-01-01 to 2024-12-31"}'></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Justification *</label>
                            <textarea name="justification" class="form-control" rows="4" 
                                      placeholder="Provide detailed justification for this export request. Include the purpose, who will access the data, and how it will be used..." required></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Submit Request
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- My Requests -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-list"></i> My Export Requests
                </div>
                <div class="card-body">
                    <?php if (empty($user_requests)): ?>
                    <p style="text-align: center; color: #666; margin: 2rem 0;">
                        <i class="fas fa-inbox" style="font-size: 3rem; opacity: 0.3; display: block; margin-bottom: 1rem;"></i>
                        No export requests yet.
                    </p>
                    <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Request ID</th>
                                <th>Export Type</th>
                                <th>Status</th>
                                <th>Requested</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($user_requests as $request): ?>
                            <tr>
                                <td><?= htmlspecialchars($request['request_id']) ?></td>
                                <td><?= htmlspecialchars($request['export_type']) ?></td>
                                <td>
                                    <span class="status-badge status-<?= $request['status'] ?>">
                                        <?= ucfirst($request['status']) ?>
                                    </span>
                                </td>
                                <td><?= date('M j, Y', strtotime($request['requested_at'])) ?></td>
                                <td>
                                    <?php if ($request['status'] === 'approved'): ?>
                                    <a href="secure_export.php?request_id=<?= $request['request_id'] ?>" 
                                       class="btn btn-primary" style="padding: 4px 8px; font-size: 0.8rem;">
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                    <?php else: ?>
                                    <span style="color: #666; font-size: 0.9rem;">Pending</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>