<?php
session_start();
require_once 'db.php';
require_once 'propagation_prevention.php';

$propagation = new PropagationPrevention($conn);
$message = '';
$action = $_GET['action'] ?? '';

// Handle different demo actions
switch ($action) {
    case 'create_session':
        // Create a demo session
        $demo_user_id = 999;
        $demo_role = 'doctor';
        $result = $propagation->initializeSessionTracking($demo_user_id, $demo_role);
        $message = $result ? 
            "<div class='alert success'>✅ Session created successfully! Fingerprint: " . $_SESSION['propagation_fingerprint'] . "</div>" :
            "<div class='alert error'>❌ Failed to create session</div>";
        break;
        
    case 'hijack_session':
        // Simulate hijacking
        if (isset($_SESSION['propagation_fingerprint'])) {
            $original = $_SESSION['propagation_fingerprint'];
            $_SESSION['propagation_fingerprint'] = 'hijacked_' . time();
            $message = "<div class='alert warning'>⚠️ Session hijacked! Fingerprint changed from $original to " . $_SESSION['propagation_fingerprint'] . "</div>";
        } else {
            $message = "<div class='alert error'>❌ No active session to hijack</div>";
        }
        break;
        
    case 'validate_session':
        // Try to validate
        $result = $propagation->validateSessionIntegrity();
        $message = $result ?
            "<div class='alert success'>✅ Session is valid and secure</div>" :
            "<div class='alert error'>❌ Session validation failed! Possible hijacking detected.</div>";
        break;
        
    case 'escalate_privilege':
        // Simulate privilege escalation
        if (isset($_SESSION['propagation_role'])) {
            $original = $_SESSION['propagation_role'];
            $_SESSION['propagation_role'] = 'admin';
            $_SESSION['role'] = 'admin';
            $message = "<div class='alert warning'>⚠️ Privilege escalation attempted! Role changed from $original to admin</div>";
        } else {
            $message = "<div class='alert error'>❌ No active session</div>";
        }
        break;
        
    case 'check_privilege':
        // Check if can access admin
        $result = $propagation->validateRoleAccess('admin');
        $message = $result ?
            "<div class='alert success'>✅ Access granted to admin resources</div>" :
            "<div class='alert error'>❌ Access denied! Privilege escalation blocked.</div>";
        break;
        
    case 'destroy_session':
        session_destroy();
        session_start();
        $message = "<div class='alert info'>🔄 Session destroyed and reset</div>";
        break;
}

// Get current session info
$current_session = [
    'session_id' => session_id(),
    'fingerprint' => $_SESSION['propagation_fingerprint'] ?? 'Not set',
    'user_id' => $_SESSION['propagation_user_id'] ?? 'Not set',
    'role' => $_SESSION['propagation_role'] ?? 'Not set',
    'created_at' => isset($_SESSION['propagation_created_at']) ? date('Y-m-d H:i:s', $_SESSION['propagation_created_at']) : 'Not set'
];

// Get statistics
$stats = $propagation->getPropagationStats();
$incidents = $propagation->getRecentIncidents(5);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Propagation Prevention Demo</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            color: #333;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 40px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
        }
        
        .header p {
            font-size: 1.1em;
            opacity: 0.9;
        }
        
        .content {
            padding: 40px;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid;
            font-weight: bold;
        }
        
        .alert.success {
            background: #d4edda;
            color: #155724;
            border-color: #28a745;
        }
        
        .alert.error {
            background: #f8d7da;
            color: #721c24;
            border-color: #dc3545;
        }
        
        .alert.warning {
            background: #fff3cd;
            color: #856404;
            border-color: #ffc107;
        }
        
        .alert.info {
            background: #d1ecf1;
            color: #0c5460;
            border-color: #17a2b8;
        }
        
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .card {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 25px;
            border: 2px solid #e9ecef;
        }
        
        .card h3 {
            color: #667eea;
            margin-bottom: 15px;
            font-size: 1.3em;
        }
        
        .info-item {
            margin: 10px 0;
            padding: 10px;
            background: white;
            border-radius: 6px;
        }
        
        .info-label {
            font-weight: bold;
            color: #555;
            display: inline-block;
            width: 120px;
        }
        
        .info-value {
            color: #333;
            font-family: 'Courier New', monospace;
        }
        
        .actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 30px 0;
        }
        
        .btn {
            padding: 15px 25px;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            display: block;
            transition: transform 0.2s, box-shadow 0.2s;
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #28a745, #20c997);
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #ffc107, #fd7e14);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #dc3545, #c82333);
        }
        
        .btn-info {
            background: linear-gradient(135deg, #17a2b8, #138496);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 3em;
            font-weight: bold;
            margin: 10px 0;
        }
        
        .stat-label {
            font-size: 0.9em;
            opacity: 0.9;
        }
        
        .incidents-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background: white;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .incidents-table thead {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        
        .incidents-table th,
        .incidents-table td {
            padding: 12px;
            text-align: left;
        }
        
        .incidents-table tbody tr:nth-child(even) {
            background: #f8f9fa;
        }
        
        .severity-low { color: #28a745; font-weight: bold; }
        .severity-medium { color: #ffc107; font-weight: bold; }
        .severity-high { color: #fd7e14; font-weight: bold; }
        .severity-critical { color: #dc3545; font-weight: bold; }
        
        .section {
            margin: 40px 0;
        }
        
        .section h2 {
            color: #764ba2;
            border-bottom: 3px solid #667eea;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🛡️ Propagation Prevention Demo</h1>
            <p>Interactive demonstration of Session Hijacking and Privilege Escalation Prevention</p>
        </div>
        
        <div class="content">
            <?php echo $message; ?>
            
            <div class="section">
                <h2>📊 Current Session Info</h2>
                <div class="card">
                    <h3>Session Details</h3>
                    <div class="info-item">
                        <span class="info-label">Session ID:</span>
                        <span class="info-value"><?php echo htmlspecialchars($current_session['session_id']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Fingerprint:</span>
                        <span class="info-value"><?php echo htmlspecialchars($current_session['fingerprint']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">User ID:</span>
                        <span class="info-value"><?php echo htmlspecialchars($current_session['user_id']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Role:</span>
                        <span class="info-value"><?php echo htmlspecialchars($current_session['role']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Created At:</span>
                        <span class="info-value"><?php echo htmlspecialchars($current_session['created_at']); ?></span>
                    </div>
                </div>
            </div>
            
            <div class="section">
                <h2>🎮 Session Hijacking Demo</h2>
                <div class="actions">
                    <a href="?action=create_session" class="btn btn-success">1. Create Session</a>
                    <a href="?action=hijack_session" class="btn btn-warning">2. Simulate Hijacking</a>
                    <a href="?action=validate_session" class="btn btn-info">3. Validate Session</a>
                </div>
                <p style="padding: 15px; background: #f8f9fa; border-radius: 8px;">
                    <strong>How it works:</strong> First create a session, then simulate hijacking by changing the fingerprint, 
                    then try to validate. The system will detect the mismatch and block the session.
                </p>
            </div>
            
            <div class="section">
                <h2>⚠️ Privilege Escalation Demo</h2>
                <div class="actions">
                    <a href="?action=create_session" class="btn btn-success">1. Create Session (Doctor)</a>
                    <a href="?action=escalate_privilege" class="btn btn-warning">2. Escalate to Admin</a>
                    <a href="?action=check_privilege" class="btn btn-info">3. Try Access Admin</a>
                </div>
                <p style="padding: 15px; background: #f8f9fa; border-radius: 8px;">
                    <strong>How it works:</strong> Create a session as a doctor, try to escalate to admin role, 
                    then attempt to access admin resources. The system validates against the database and blocks the attempt.
                </p>
            </div>
            
            <div class="section">
                <h2>📈 Statistics</h2>
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-label">Session Hijacking (24h)</div>
                        <div class="stat-value"><?php echo $stats['session_hijacking_24h']; ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Privilege Escalation (24h)</div>
                        <div class="stat-value"><?php echo $stats['privilege_escalation_24h']; ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Blocked Sessions</div>
                        <div class="stat-value"><?php echo $stats['blocked_sessions']; ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Active Sessions</div>
                        <div class="stat-value"><?php echo $stats['active_sessions']; ?></div>
                    </div>
                </div>
            </div>
            
            <div class="section">
                <h2>🔍 Recent Incidents</h2>
                <?php if (empty($incidents)): ?>
                    <p style="padding: 20px; background: #f8f9fa; border-radius: 8px; text-align: center;">
                        No incidents recorded yet. Try the demo actions above to generate some incidents.
                    </p>
                <?php else: ?>
                    <table class="incidents-table">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>User ID</th>
                                <th>IP Address</th>
                                <th>Severity</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($incidents as $incident): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($incident['incident_type']); ?></td>
                                    <td><?php echo htmlspecialchars($incident['user_id'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($incident['ip_address']); ?></td>
                                    <td><span class="severity-<?php echo $incident['severity']; ?>"><?php echo $incident['severity']; ?></span></td>
                                    <td><?php echo htmlspecialchars($incident['detected_at']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <div class="section">
                <h2>🔧 Actions</h2>
                <div class="actions">
                    <a href="?action=destroy_session" class="btn btn-danger">Reset Session</a>
                    <a href="test_propagation_prevention.php" class="btn btn-primary">Run Full Tests</a>
                    <a href="index.php" class="btn btn-info">Back to Login</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
