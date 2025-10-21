<?php
/**
 * INTERACTIVE DLP SIMULATOR
 * Live demonstration where users can trigger DLP controls
 */

session_start();
require_once 'db.php';

$simulation_result = null;

// Handle simulation requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simulate'])) {
    $data_type = $_POST['data_type'];
    $classification = $_POST['classification'];
    $action = $_POST['action'];
    $user_role = $_POST['user_role'];
    
    // Simulate DLP decision
    $approved = false;
    $reason = '';
    $requires_approval = false;
    
    switch ($classification) {
        case 'PUBLIC':
            $approved = true;
            $reason = 'PUBLIC data is automatically approved for everyone. No restrictions apply.';
            break;
            
        case 'INTERNAL':
            if (in_array($user_role, ['admin', 'chief-staff', 'doctor', 'nurse', 'therapist', 'receptionist', 'staff'])) {
                $approved = true;
                $reason = 'INTERNAL data is automatically approved for authenticated staff. Action logged for audit trail.';
            } else {
                $approved = false;
                $reason = 'INTERNAL data requires authentication. Guest access denied.';
            }
            break;
            
        case 'CONFIDENTIAL':
            $requires_approval = true;
            if (in_array($user_role, ['admin', 'chief-staff'])) {
                $approved = true;
                $reason = 'CONFIDENTIAL data is automatically approved for supervisor/admin role. High-security audit log created.';
            } else {
                $approved = false;
                $reason = 'CONFIDENTIAL data requires supervisor or admin approval. Request submitted to approval queue for manual review.';
            }
            break;
            
        case 'RESTRICTED':
            $requires_approval = true;
            if ($user_role === 'admin') {
                $approved = true;
                $reason = 'RESTRICTED data is automatically approved for administrator. Maximum security audit log created with IP tracking.';
            } else {
                $approved = false;
                $reason = 'RESTRICTED data requires administrator approval only. All other roles must wait for admin review + justification.';
            }
            break;
    }
    
    $simulation_result = [
        'data_type' => $data_type,
        'classification' => $classification,
        'action' => $action,
        'user_role' => $user_role,
        'approved' => $approved,
        'reason' => $reason,
        'requires_approval' => $requires_approval,
        'timestamp' => date('Y-m-d H:i:s')
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DLP Interactive Simulator</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
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

        .demo-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .panel {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.2);
        }

        .panel-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 3px solid #667eea;
        }

        .panel-header h2 {
            color: #2c3e50;
            font-size: 26px;
        }

        .panel-header i {
            font-size: 32px;
            color: #667eea;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 10px;
            color: #2c3e50;
            font-weight: bold;
            font-size: 16px;
        }

        select, input {
            width: 100%;
            padding: 15px;
            border: 2px solid #ddd;
            border-radius: 10px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        select:focus, input:focus {
            outline: none;
            border-color: #667eea;
        }

        .btn {
            width: 100%;
            padding: 18px;
            border: none;
            border-radius: 12px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-simulate {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-simulate:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }

        .result-panel {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.2);
            animation: slideIn 0.5s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .result-approved {
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin: 20px 0;
            text-align: center;
            animation: pulse 0.6s;
        }

        .result-denied {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin: 20px 0;
            text-align: center;
            animation: shake 0.6s;
        }

        .result-pending {
            background: linear-gradient(135deg, #f39c12 0%, #d68910 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin: 20px 0;
            text-align: center;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }

        .result-icon {
            font-size: 80px;
            margin-bottom: 20px;
        }

        .result-title {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 15px;
        }

        .info-box {
            background: rgba(0,0,0,0.2);
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
        }

        .info-label {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .info-value {
            font-size: 16px;
            margin-bottom: 15px;
        }

        .examples-section {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.2);
            margin-bottom: 30px;
        }

        .example-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .example-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            border-left: 4px solid #667eea;
            cursor: pointer;
            transition: all 0.3s;
        }

        .example-card:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .example-title {
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 16px;
        }

        .example-detail {
            font-size: 13px;
            color: #666;
            line-height: 1.6;
        }

        .classification-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
            margin: 5px 0;
        }

        .badge-public { background: #4caf50; color: white; }
        .badge-internal { background: #ff9800; color: white; }
        .badge-confidential { background: #ffc107; color: #000; }
        .badge-restricted { background: #f44336; color: white; }

        @media (max-width: 768px) {
            .demo-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-shield-alt"></i> DLP Interactive Simulator</h1>
            <p>Try different scenarios - See how DLP controls data access in real-time!</p>
        </div>

        <div class="demo-container">
            <!-- Simulation Input Panel -->
            <div class="panel">
                <div class="panel-header">
                    <i class="fas fa-sliders-h"></i>
                    <h2>Scenario Setup</h2>
                </div>

                <form method="POST">
                    <div class="form-group">
                        <label><i class="fas fa-file-alt"></i> Data Type:</label>
                        <select name="data_type" required>
                            <option value="Patient Medical Record">Patient Medical Record</option>
                            <option value="Staff Information">Staff Information</option>
                            <option value="Financial Report">Financial Report</option>
                            <option value="Treatment Plan">Treatment Plan</option>
                            <option value="Appointment Schedule">Appointment Schedule</option>
                            <option value="Medicine Inventory">Medicine Inventory</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-layer-group"></i> Classification Level:</label>
                        <select name="classification" id="classification" required>
                            <option value="PUBLIC">🟢 PUBLIC - Unrestricted</option>
                            <option value="INTERNAL">🟡 INTERNAL - Auth Required</option>
                            <option value="CONFIDENTIAL" selected>🟠 CONFIDENTIAL - Supervisor Approval</option>
                            <option value="RESTRICTED">🔴 RESTRICTED - Admin Only</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-tasks"></i> Action Requested:</label>
                        <select name="action" required>
                            <option value="VIEW">View Data</option>
                            <option value="DOWNLOAD" selected>Download/Export</option>
                            <option value="PRINT">Print Document</option>
                            <option value="SHARE">Share with External Party</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-user"></i> User Role:</label>
                        <select name="user_role" id="userRole" required>
                            <option value="guest">Guest (No Login)</option>
                            <option value="nurse" selected>Nurse</option>
                            <option value="doctor">Doctor</option>
                            <option value="chief-staff">Chief Staff/Supervisor</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>

                    <button type="submit" name="simulate" class="btn btn-simulate">
                        <i class="fas fa-play"></i>
                        RUN SIMULATION
                    </button>
                </form>
            </div>

            <!-- Result Panel -->
            <div class="panel">
                <div class="panel-header">
                    <i class="fas fa-desktop"></i>
                    <h2>DLP Response</h2>
                </div>

                <?php if ($simulation_result): ?>
                    <?php if ($simulation_result['approved']): ?>
                        <div class="result-approved">
                            <div class="result-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="result-title">✅ ACCESS GRANTED</div>
                            <p style="font-size: 16px; margin-bottom: 20px;"><?php echo $simulation_result['reason']; ?></p>
                            
                            <div class="info-box">
                                <div class="info-label">Data Type:</div>
                                <div class="info-value"><?php echo htmlspecialchars($simulation_result['data_type']); ?></div>
                                
                                <div class="info-label">Classification:</div>
                                <div class="info-value">
                                    <span class="classification-badge badge-<?php echo strtolower($simulation_result['classification']); ?>">
                                        <?php echo $simulation_result['classification']; ?>
                                    </span>
                                </div>
                                
                                <div class="info-label">Action:</div>
                                <div class="info-value"><?php echo $simulation_result['action']; ?></div>
                                
                                <div class="info-label">User Role:</div>
                                <div class="info-value"><?php echo strtoupper($simulation_result['user_role']); ?></div>
                                
                                <div class="info-label">Timestamp:</div>
                                <div class="info-value"><?php echo $simulation_result['timestamp']; ?></div>
                            </div>
                            
                            <p style="margin-top: 20px; font-size: 15px;">
                                ✓ Action logged in audit trail<br>
                                ✓ User identity recorded<br>
                                ✓ Download/access tracked
                            </p>
                        </div>
                    <?php else: ?>
                        <div class="<?php echo $simulation_result['requires_approval'] ? 'result-pending' : 'result-denied'; ?>">
                            <div class="result-icon">
                                <i class="fas <?php echo $simulation_result['requires_approval'] ? 'fa-clock' : 'fa-ban'; ?>"></i>
                            </div>
                            <div class="result-title">
                                <?php echo $simulation_result['requires_approval'] ? '⏳ APPROVAL REQUIRED' : '🛑 ACCESS DENIED'; ?>
                            </div>
                            <p style="font-size: 16px; margin-bottom: 20px;"><?php echo $simulation_result['reason']; ?></p>
                            
                            <div class="info-box">
                                <div class="info-label">Data Type:</div>
                                <div class="info-value"><?php echo htmlspecialchars($simulation_result['data_type']); ?></div>
                                
                                <div class="info-label">Classification:</div>
                                <div class="info-value">
                                    <span class="classification-badge badge-<?php echo strtolower($simulation_result['classification']); ?>">
                                        <?php echo $simulation_result['classification']; ?>
                                    </span>
                                </div>
                                
                                <div class="info-label">User Role:</div>
                                <div class="info-value"><?php echo strtoupper($simulation_result['user_role']); ?></div>
                            </div>
                            
                            <?php if ($simulation_result['requires_approval']): ?>
                                <p style="margin-top: 20px; font-size: 15px;">
                                    📋 Request submitted to approval queue<br>
                                    📧 Notification sent to approver<br>
                                    ⏰ Awaiting supervisor/admin approval
                                </p>
                            <?php else: ?>
                                <p style="margin-top: 20px; font-size: 15px;">
                                    🚫 Insufficient permissions<br>
                                    📝 Access attempt logged<br>
                                    🔔 Security team notified
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div style="text-align: center; padding: 60px 20px; color: #999;">
                        <i class="fas fa-arrow-left" style="font-size: 60px; margin-bottom: 20px;"></i>
                        <p style="font-size: 18px;">Configure a scenario and click "RUN SIMULATION" to see how DLP controls work!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Example Scenarios -->
        <div class="examples-section">
            <div class="panel-header">
                <i class="fas fa-lightbulb"></i>
                <h2>Try These Scenarios</h2>
            </div>

            <div class="example-grid">
                <div class="example-card" onclick="setScenario('Patient Medical Record', 'PUBLIC', 'VIEW', 'guest')">
                    <div class="example-title">✅ Scenario 1: Public Access</div>
                    <div class="example-detail">
                        <strong>Guest</strong> viewing <strong>PUBLIC</strong> data<br>
                        <span class="classification-badge badge-public">PUBLIC</span><br>
                        <em>Expected: GRANTED</em>
                    </div>
                </div>

                <div class="example-card" onclick="setScenario('Staff Information', 'INTERNAL', 'VIEW', 'nurse')">
                    <div class="example-title">✅ Scenario 2: Authenticated Access</div>
                    <div class="example-detail">
                        <strong>Nurse</strong> viewing <strong>INTERNAL</strong> data<br>
                        <span class="classification-badge badge-internal">INTERNAL</span><br>
                        <em>Expected: GRANTED</em>
                    </div>
                </div>

                <div class="example-card" onclick="setScenario('Treatment Plan', 'CONFIDENTIAL', 'DOWNLOAD', 'nurse')">
                    <div class="example-title">⏳ Scenario 3: Needs Approval</div>
                    <div class="example-detail">
                        <strong>Nurse</strong> downloading <strong>CONFIDENTIAL</strong> data<br>
                        <span class="classification-badge badge-confidential">CONFIDENTIAL</span><br>
                        <em>Expected: APPROVAL REQUIRED</em>
                    </div>
                </div>

                <div class="example-card" onclick="setScenario('Financial Report', 'CONFIDENTIAL', 'DOWNLOAD', 'chief-staff')">
                    <div class="example-title">✅ Scenario 4: Supervisor Override</div>
                    <div class="example-detail">
                        <strong>Chief Staff</strong> downloading <strong>CONFIDENTIAL</strong> data<br>
                        <span class="classification-badge badge-confidential">CONFIDENTIAL</span><br>
                        <em>Expected: GRANTED (supervisor)</em>
                    </div>
                </div>

                <div class="example-card" onclick="setScenario('Patient Medical Record', 'RESTRICTED', 'SHARE', 'nurse')">
                    <div class="example-title">🛑 Scenario 5: Hard Block</div>
                    <div class="example-detail">
                        <strong>Nurse</strong> sharing <strong>RESTRICTED</strong> data<br>
                        <span class="classification-badge badge-restricted">RESTRICTED</span><br>
                        <em>Expected: DENIED</em>
                    </div>
                </div>

                <div class="example-card" onclick="setScenario('Patient Medical Record', 'RESTRICTED', 'DOWNLOAD', 'admin')">
                    <div class="example-title">✅ Scenario 6: Admin Access</div>
                    <div class="example-detail">
                        <strong>Administrator</strong> downloading <strong>RESTRICTED</strong> data<br>
                        <span class="classification-badge badge-restricted">RESTRICTED</span><br>
                        <em>Expected: GRANTED (admin only)</em>
                    </div>
                </div>
            </div>
        </div>

        <!-- Navigation -->
        <div style="text-align: center; margin: 30px 0;">
            <a href="test_dlp.php" class="btn btn-simulate" style="display: inline-flex; width: auto; text-decoration: none; margin: 5px;">
                <i class="fas fa-chart-bar"></i>
                View Full DLP Report
            </a>
            <a href="test_security.php" class="btn btn-simulate" style="display: inline-flex; width: auto; text-decoration: none; margin: 5px;">
                <i class="fas fa-shield-alt"></i>
                Security Demo
            </a>
            <a href="index.php" class="btn btn-simulate" style="display: inline-flex; width: auto; text-decoration: none; margin: 5px;">
                <i class="fas fa-home"></i>
                Back to Home
            </a>
        </div>
    </div>

    <script>
        function setScenario(dataType, classification, action, userRole) {
            document.querySelector('select[name="data_type"]').value = dataType;
            document.querySelector('select[name="classification"]').value = classification;
            document.querySelector('select[name="action"]').value = action;
            document.querySelector('select[name="user_role"]').value = userRole;
            
            // Optional: Auto-submit
            // document.querySelector('form').submit();
        }
    </script>
</body>
</html>
