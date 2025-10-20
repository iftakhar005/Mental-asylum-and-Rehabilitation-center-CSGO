<?php
/**
 * TRACE ADMIN ACCESS - Step by step debugging
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Admin Access Trace</h1>";
echo "<pre>";

// Step 1: Check session
echo "=== STEP 1: SESSION CHECK ===\n";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    echo "✅ Session started\n";
} else {
    echo "✅ Session already active\n";
}

echo "Session ID: " . session_id() . "\n";
echo "Session Data:\n";
print_r($_SESSION);

// Step 2: Check database connection
echo "\n=== STEP 2: DATABASE CONNECTION ===\n";
require_once 'db.php';
if ($conn) {
    echo "✅ Database connected\n";
} else {
    echo "❌ Database connection failed\n";
    die();
}

// Step 3: Check if user is logged in
echo "\n=== STEP 3: LOGIN STATUS ===\n";
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    echo "✅ User is logged in\n";
    echo "   User ID: " . $_SESSION['user_id'] . "\n";
    echo "   Role: " . $_SESSION['role'] . "\n";
    echo "   Username: " . ($_SESSION['username'] ?? 'N/A') . "\n";
} else {
    echo "❌ User is NOT logged in\n";
    echo '<a href="index.php">Go to Login</a>';
    die();
}

// Step 4: Check propagation prevention
echo "\n=== STEP 4: PROPAGATION PREVENTION ===\n";
require_once 'propagation_prevention.php';
$propagation = new PropagationPrevention($conn);

// Step 5: Test validateSessionIntegrity
echo "\n=== STEP 5: SESSION INTEGRITY ===\n";
try {
    $session_valid = $propagation->validateSessionIntegrity();
    if ($session_valid) {
        echo "✅ Session integrity valid\n";
    } else {
        echo "❌ Session integrity FAILED\n";
        echo "   Reason: Session fingerprint mismatch or timeout\n";
    }
} catch (Exception $e) {
    echo "❌ Error checking session integrity: " . $e->getMessage() . "\n";
    $session_valid = false;
}

// Step 6: Test verifyRoleIntegrity manually
echo "\n=== STEP 6: ROLE INTEGRITY CHECK ===\n";
$user_id = $_SESSION['user_id'];
$session_role = $_SESSION['role'];

// Check users table
$stmt = $conn->prepare("SELECT id, username, role FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo "✅ Found in users table:\n";
    echo "   ID: {$row['id']}\n";
    echo "   Username: {$row['username']}\n";
    echo "   DB Role: {$row['role']}\n";
    echo "   Session Role: $session_role\n";
    
    if ($row['role'] === $session_role) {
        echo "✅ Roles MATCH - Role integrity OK!\n";
    } else {
        echo "❌ Roles MISMATCH - Role integrity FAILED!\n";
    }
} else {
    echo "❌ User not found in users table\n";
}
$stmt->close();

// Step 7: Test validateRoleAccess
echo "\n=== STEP 7: ROLE ACCESS VALIDATION ===\n";
try {
    $role_access = $propagation->validateRoleAccess('admin');
    if ($role_access) {
        echo "✅ Role access validation PASSED for 'admin'\n";
    } else {
        echo "❌ Role access validation FAILED for 'admin'\n";
        echo "   This is why admin_dashboard.php redirects!\n";
    }
} catch (Exception $e) {
    echo "❌ Error validating role access: " . $e->getMessage() . "\n";
    $role_access = false;
}

// Step 8: Final verdict
echo "\n=== STEP 8: FINAL VERDICT ===\n";
if ($session_valid && $role_access) {
    echo "✅✅✅ ALL CHECKS PASSED - Admin dashboard should work!\n";
    echo '<br><br><a href="admin_dashboard.php" style="display:inline-block;padding:15px 30px;background:#10b981;color:white;text-decoration:none;border-radius:8px;font-weight:bold;">✅ GO TO ADMIN DASHBOARD</a>';
} else {
    echo "❌ SOME CHECKS FAILED:\n";
    if (!$session_valid) {
        echo "   ❌ Session integrity failed\n";
    }
    if (!$role_access) {
        echo "   ❌ Role access validation failed\n";
    }
    echo "\n<strong>This is why admin can't access the dashboard.</strong>\n";
}

echo "\n=== STEP 9: CHECK PROPAGATION SESSION DATA ===\n";
if (isset($_SESSION['propagation_fingerprint'])) {
    echo "✅ Propagation data exists:\n";
    echo "   Fingerprint: " . $_SESSION['propagation_fingerprint'] . "\n";
    echo "   Created: " . ($_SESSION['propagation_created_at'] ?? 'N/A') . "\n";
    echo "   Last Rotation: " . ($_SESSION['propagation_last_rotation'] ?? 'N/A') . "\n";
    echo "   User ID: " . ($_SESSION['propagation_user_id'] ?? 'N/A') . "\n";
    echo "   Role: " . ($_SESSION['propagation_role'] ?? 'N/A') . "\n";
} else {
    echo "❌ Propagation fingerprint NOT SET\n";
    echo "   This might be the issue - session tracking not initialized\n";
}

echo "</pre>";
?>
