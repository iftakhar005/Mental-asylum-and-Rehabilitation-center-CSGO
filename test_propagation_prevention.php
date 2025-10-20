<?php
/**
 * PROPAGATION PREVENTION TESTING SCRIPT
 * 
 * Tests:
 * 1. Session Hijacking Detection
 * 2. Privilege Escalation Prevention
 */

session_start();
require_once 'db.php';
require_once 'propagation_prevention.php';

// Initialize propagation prevention
$propagation = new PropagationPrevention($conn);

// Test results array
$test_results = [];

/**
 * Test 1: Session Hijacking Detection - Fingerprint Mismatch
 */
function test_session_hijacking_fingerprint() {
    global $propagation, $test_results;
    
    echo "<h3>Test 1: Session Hijacking Detection (Fingerprint Mismatch)</h3>";
    
    // Initialize a legitimate session
    $user_id = 1;
    $role = 'admin';
    
    $init_result = $propagation->initializeSessionTracking($user_id, $role);
    
    if ($init_result) {
        echo "<p class='success'>✓ Session initialized successfully</p>";
        echo "<p>Original Fingerprint: " . $_SESSION['propagation_fingerprint'] . "</p>";
        
        // Simulate fingerprint change (session hijacking attempt)
        $original_fingerprint = $_SESSION['propagation_fingerprint'];
        $_SESSION['propagation_fingerprint'] = 'fake_hijacked_fingerprint_12345';
        
        echo "<p class='warning'>⚠ Simulating fingerprint change (hijacking attempt)...</p>";
        echo "<p>Modified Fingerprint: " . $_SESSION['propagation_fingerprint'] . "</p>";
        
        // Try to validate - should fail
        $validation_result = $propagation->validateSessionIntegrity();
        
        if (!$validation_result) {
            echo "<p class='success'>✓ Session hijacking detected and blocked!</p>";
            $test_results['session_hijacking_detection'] = 'PASSED';
        } else {
            echo "<p class='error'>✗ Session hijacking NOT detected (FAILED)</p>";
            $test_results['session_hijacking_detection'] = 'FAILED';
        }
    } else {
        echo "<p class='error'>✗ Failed to initialize session</p>";
        $test_results['session_hijacking_detection'] = 'FAILED';
    }
}

/**
 * Test 2: Session Timeout
 */
function test_session_timeout() {
    global $propagation, $test_results;
    
    echo "<h3>Test 2: Session Timeout Detection</h3>";
    
    // Start fresh session
    session_destroy();
    session_start();
    
    $user_id = 2;
    $role = 'doctor';
    
    $propagation->initializeSessionTracking($user_id, $role);
    
    echo "<p class='success'>✓ New session created</p>";
    echo "<p>Session created at: " . date('Y-m-d H:i:s', $_SESSION['propagation_created_at']) . "</p>";
    
    // Simulate old session (expired)
    $_SESSION['propagation_created_at'] = time() - 7200; // 2 hours ago
    
    echo "<p class='warning'>⚠ Simulating expired session (2 hours old)...</p>";
    
    $validation_result = $propagation->validateSessionIntegrity();
    
    if (!$validation_result) {
        echo "<p class='success'>✓ Expired session detected and blocked!</p>";
        $test_results['session_timeout'] = 'PASSED';
    } else {
        echo "<p class='error'>✗ Expired session NOT detected (FAILED)</p>";
        $test_results['session_timeout'] = 'FAILED';
    }
}

/**
 * Test 3: Privilege Escalation - Unauthorized Role Access
 */
function test_privilege_escalation_unauthorized() {
    global $propagation, $test_results, $conn;
    
    echo "<h3>Test 3: Privilege Escalation Prevention (Unauthorized Access)</h3>";
    
    // Start fresh session
    session_destroy();
    session_start();
    
    // Create test user with lower privilege
    $email = 'test_receptionist_' . time() . '@test.com';
    $password = password_hash('TestPass123!', PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare(
        "INSERT INTO users (username, password_hash, email, role, first_name, last_name) 
        VALUES (?, ?, ?, 'receptionist', 'Test', 'User')"
    );
    $username = 'test_recep_' . time();
    $stmt->bind_param('sss', $username, $password, $email);
    $stmt->execute();
    $user_id = $conn->insert_id;
    $stmt->close();
    
    echo "<p>Created test user (ID: $user_id) with role: receptionist</p>";
    
    // Initialize session as receptionist
    $propagation->initializeSessionTracking($user_id, 'receptionist');
    
    echo "<p class='success'>✓ Session initialized as receptionist</p>";
    
    // Try to access admin-level resource
    echo "<p class='warning'>⚠ Attempting to access admin-level resource...</p>";
    
    $access_result = $propagation->validateRoleAccess('admin');
    
    if (!$access_result) {
        echo "<p class='success'>✓ Privilege escalation blocked! Receptionist cannot access admin resources.</p>";
        $test_results['privilege_escalation_block'] = 'PASSED';
    } else {
        echo "<p class='error'>✗ Privilege escalation NOT blocked (FAILED)</p>";
        $test_results['privilege_escalation_block'] = 'FAILED';
    }
    
    // Clean up
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->close();
}

/**
 * Test 4: Privilege Escalation - Role Tampering
 */
function test_privilege_escalation_tampering() {
    global $propagation, $test_results, $conn;
    
    echo "<h3>Test 4: Privilege Escalation Prevention (Role Tampering)</h3>";
    
    // Start fresh session
    session_destroy();
    session_start();
    
    // Create test user
    $email = 'test_nurse_' . time() . '@test.com';
    $password = password_hash('TestPass123!', PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare(
        "INSERT INTO users (username, password_hash, email, role, first_name, last_name) 
        VALUES (?, ?, ?, 'nurse', 'Test', 'Nurse')"
    );
    $username = 'test_nurse_' . time();
    $stmt->bind_param('sss', $username, $password, $email);
    $stmt->execute();
    $user_id = $conn->insert_id;
    $stmt->close();
    
    echo "<p>Created test user (ID: $user_id) with role: nurse</p>";
    
    // Initialize session as nurse
    $propagation->initializeSessionTracking($user_id, 'nurse');
    
    echo "<p class='success'>✓ Session initialized as nurse</p>";
    echo "<p>Session role: " . $_SESSION['propagation_role'] . "</p>";
    
    // Tamper with session role (simulate attacker changing role in session)
    $_SESSION['propagation_role'] = 'admin';
    $_SESSION['role'] = 'admin';
    
    echo "<p class='warning'>⚠ Session role tampered to 'admin'...</p>";
    echo "<p>Tampered session role: " . $_SESSION['propagation_role'] . "</p>";
    
    // Try to validate - should detect tampering
    $access_result = $propagation->validateRoleAccess('admin');
    
    if (!$access_result) {
        echo "<p class='success'>✓ Role tampering detected and blocked!</p>";
        $test_results['role_tampering_detection'] = 'PASSED';
    } else {
        echo "<p class='error'>✗ Role tampering NOT detected (FAILED)</p>";
        $test_results['role_tampering_detection'] = 'FAILED';
    }
    
    // Clean up
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->close();
}

/**
 * Test 5: Multiple Privilege Escalation Attempts
 */
function test_multiple_escalation_attempts() {
    global $propagation, $test_results, $conn;
    
    echo "<h3>Test 5: Multiple Privilege Escalation Attempts (Session Blocking)</h3>";
    
    // Start fresh session
    session_destroy();
    session_start();
    
    // Create test user
    $email = 'test_therapist_' . time() . '@test.com';
    $password = password_hash('TestPass123!', PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare(
        "INSERT INTO users (username, password_hash, email, role, first_name, last_name) 
        VALUES (?, ?, ?, 'therapist', 'Test', 'Therapist')"
    );
    $username = 'test_therapist_' . time();
    $stmt->bind_param('sss', $username, $password, $email);
    $stmt->execute();
    $user_id = $conn->insert_id;
    $stmt->close();
    
    echo "<p>Created test user (ID: $user_id) with role: therapist</p>";
    
    // Initialize session
    $propagation->initializeSessionTracking($user_id, 'therapist');
    
    echo "<p class='success'>✓ Session initialized as therapist</p>";
    
    // Make multiple unauthorized access attempts
    echo "<p class='warning'>⚠ Making multiple privilege escalation attempts...</p>";
    
    $attempt_count = 0;
    for ($i = 0; $i < 5; $i++) {
        $result = $propagation->validateRoleAccess('admin');
        if (!$result) {
            $attempt_count++;
        }
        echo "<p>Attempt " . ($i + 1) . ": " . ($result ? 'Allowed' : 'Blocked') . "</p>";
    }
    
    if ($attempt_count >= 3) {
        echo "<p class='success'>✓ Multiple attempts detected and blocked ($attempt_count/5)</p>";
        $test_results['multiple_attempts_blocking'] = 'PASSED';
    } else {
        echo "<p class='error'>✗ Multiple attempts NOT properly blocked (FAILED)</p>";
        $test_results['multiple_attempts_blocking'] = 'FAILED';
    }
    
    // Clean up
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->close();
}

/**
 * Test 6: Legitimate Access (Should Pass)
 */
function test_legitimate_access() {
    global $propagation, $test_results, $conn;
    
    echo "<h3>Test 6: Legitimate Access (Should Be Allowed)</h3>";
    
    // Start fresh session
    session_destroy();
    session_start();
    
    // Create test admin user
    $email = 'test_admin_' . time() . '@test.com';
    $password = password_hash('TestPass123!', PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare(
        "INSERT INTO users (username, password_hash, email, role, first_name, last_name) 
        VALUES (?, ?, ?, 'admin', 'Test', 'Admin')"
    );
    $username = 'test_admin_' . time();
    $stmt->bind_param('sss', $username, $password, $email);
    $stmt->execute();
    $user_id = $conn->insert_id;
    $stmt->close();
    
    echo "<p>Created test user (ID: $user_id) with role: admin</p>";
    
    // Initialize session as admin
    $propagation->initializeSessionTracking($user_id, 'admin');
    
    echo "<p class='success'>✓ Session initialized as admin</p>";
    
    // Validate session
    $validation_result = $propagation->validateSessionIntegrity();
    
    if ($validation_result) {
        echo "<p class='success'>✓ Session validation passed</p>";
    } else {
        echo "<p class='error'>✗ Session validation failed</p>";
    }
    
    // Try to access admin resource (should be allowed)
    $access_result = $propagation->validateRoleAccess('admin');
    
    if ($access_result) {
        echo "<p class='success'>✓ Legitimate admin access allowed!</p>";
        $test_results['legitimate_access'] = 'PASSED';
    } else {
        echo "<p class='error'>✗ Legitimate access denied (FAILED)</p>";
        $test_results['legitimate_access'] = 'FAILED';
    }
    
    // Clean up
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->close();
}

/**
 * Display statistics
 */
function display_statistics() {
    global $propagation;
    
    echo "<h3>Propagation Prevention Statistics</h3>";
    
    $stats = $propagation->getPropagationStats();
    
    echo "<div class='stats-grid'>";
    echo "<div class='stat-card'>";
    echo "<h4>Session Hijacking (24h)</h4>";
    echo "<p class='stat-value'>" . $stats['session_hijacking_24h'] . "</p>";
    echo "</div>";
    
    echo "<div class='stat-card'>";
    echo "<h4>Privilege Escalation (24h)</h4>";
    echo "<p class='stat-value'>" . $stats['privilege_escalation_24h'] . "</p>";
    echo "</div>";
    
    echo "<div class='stat-card'>";
    echo "<h4>Blocked Sessions</h4>";
    echo "<p class='stat-value'>" . $stats['blocked_sessions'] . "</p>";
    echo "</div>";
    
    echo "<div class='stat-card'>";
    echo "<h4>Active Sessions</h4>";
    echo "<p class='stat-value'>" . $stats['active_sessions'] . "</p>";
    echo "</div>";
    echo "</div>";
}

/**
 * Display recent incidents
 */
function display_recent_incidents() {
    global $propagation;
    
    echo "<h3>Recent Security Incidents</h3>";
    
    $incidents = $propagation->getRecentIncidents(10);
    
    if (empty($incidents)) {
        echo "<p>No recent incidents</p>";
        return;
    }
    
    echo "<table class='incidents-table'>";
    echo "<thead>";
    echo "<tr>";
    echo "<th>Type</th>";
    echo "<th>User ID</th>";
    echo "<th>IP Address</th>";
    echo "<th>Severity</th>";
    echo "<th>Detected At</th>";
    echo "</tr>";
    echo "</thead>";
    echo "<tbody>";
    
    foreach ($incidents as $incident) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($incident['incident_type']) . "</td>";
        echo "<td>" . htmlspecialchars($incident['user_id'] ?? 'N/A') . "</td>";
        echo "<td>" . htmlspecialchars($incident['ip_address']) . "</td>";
        echo "<td><span class='severity-" . $incident['severity'] . "'>" . $incident['severity'] . "</span></td>";
        echo "<td>" . htmlspecialchars($incident['detected_at']) . "</td>";
        echo "</tr>";
    }
    
    echo "</tbody>";
    echo "</table>";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Propagation Prevention Testing</title>
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
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 40px;
        }
        
        h1 {
            color: #667eea;
            margin-bottom: 30px;
            text-align: center;
            font-size: 2.5em;
        }
        
        h2 {
            color: #764ba2;
            margin: 30px 0 20px 0;
            border-bottom: 3px solid #667eea;
            padding-bottom: 10px;
        }
        
        h3 {
            color: #555;
            margin: 25px 0 15px 0;
            padding: 10px;
            background: #f5f5f5;
            border-left: 4px solid #667eea;
        }
        
        .success {
            color: #28a745;
            font-weight: bold;
            padding: 10px;
            background: #d4edda;
            border-left: 4px solid #28a745;
            margin: 10px 0;
        }
        
        .error {
            color: #dc3545;
            font-weight: bold;
            padding: 10px;
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            margin: 10px 0;
        }
        
        .warning {
            color: #ffc107;
            font-weight: bold;
            padding: 10px;
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            margin: 10px 0;
        }
        
        p {
            margin: 8px 0;
            padding: 5px 10px;
            line-height: 1.6;
        }
        
        .test-summary {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 30px 0;
        }
        
        .test-summary h3 {
            background: transparent;
            border: none;
            padding: 0;
            margin-bottom: 15px;
        }
        
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .summary-item {
            padding: 15px;
            background: white;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }
        
        .summary-item.passed {
            border-left-color: #28a745;
        }
        
        .summary-item.failed {
            border-left-color: #dc3545;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .stat-card h4 {
            margin-bottom: 10px;
            font-size: 0.9em;
            opacity: 0.9;
        }
        
        .stat-value {
            font-size: 2.5em;
            font-weight: bold;
        }
        
        .incidents-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .incidents-table thead {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        
        .incidents-table th,
        .incidents-table td {
            padding: 12px 15px;
            text-align: left;
        }
        
        .incidents-table tbody tr:nth-child(even) {
            background: #f8f9fa;
        }
        
        .incidents-table tbody tr:hover {
            background: #e9ecef;
        }
        
        .severity-low { color: #28a745; font-weight: bold; }
        .severity-medium { color: #ffc107; font-weight: bold; }
        .severity-high { color: #fd7e14; font-weight: bold; }
        .severity-critical { color: #dc3545; font-weight: bold; }
        
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            margin: 10px 5px;
            border: none;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🛡️ Propagation Prevention Testing Suite</h1>
        
        <p style="text-align: center; font-size: 1.1em; color: #666; margin-bottom: 30px;">
            Testing Session Hijacking and Privilege Escalation Prevention
        </p>
        
        <h2>Running Security Tests</h2>
        
        <?php
        // Run all tests
        test_session_hijacking_fingerprint();
        test_session_timeout();
        test_privilege_escalation_unauthorized();
        test_privilege_escalation_tampering();
        test_multiple_escalation_attempts();
        test_legitimate_access();
        ?>
        
        <div class="test-summary">
            <h3>📊 Test Summary</h3>
            <div class="summary-grid">
                <?php foreach ($test_results as $test_name => $result): ?>
                    <div class="summary-item <?php echo strtolower($result); ?>">
                        <strong><?php echo str_replace('_', ' ', ucwords($test_name, '_')); ?></strong><br>
                        <span style="font-size: 1.2em;">
                            <?php echo $result === 'PASSED' ? '✓' : '✗'; ?> <?php echo $result; ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <h2>📈 System Statistics</h2>
        <?php display_statistics(); ?>
        
        <h2>🔍 Recent Security Incidents</h2>
        <?php display_recent_incidents(); ?>
        
        <div style="text-align: center; margin-top: 40px;">
            <a href="test_propagation_prevention.php" class="btn">🔄 Run Tests Again</a>
            <a href="index.php" class="btn">🏠 Back to Login</a>
        </div>
    </div>
</body>
</html>
