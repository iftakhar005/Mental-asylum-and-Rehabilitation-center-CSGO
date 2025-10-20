<?php
/**
 * DEBUG SESSION FLOW - Track exactly what happens
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/session_debug.log');

// Custom error handler to catch everything
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    echo "<div style='color:red;'><strong>ERROR:</strong> $errstr in $errfile on line $errline</div>";
    return false;
});

echo "<h1>Session Flow Debug</h1>";
echo "<pre style='background:#f0f0f0;padding:10px;'>";

// Step 1: Session start
echo "=== STEP 1: Starting Session ===\n";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    echo "✅ Session started\n";
} else {
    echo "✅ Session already active\n";
}
echo "Session ID: " . session_id() . "\n\n";

// Step 2: Load files
echo "=== STEP 2: Loading Required Files ===\n";

try {
    echo "Loading db.php...\n";
    require_once 'db.php';
    echo "✅ db.php loaded\n";
} catch (Exception $e) {
    echo "❌ db.php failed: " . $e->getMessage() . "\n";
}

try {
    echo "Loading security_manager.php...\n";
    require_once 'security_manager.php';
    echo "✅ security_manager.php loaded\n";
} catch (Exception $e) {
    echo "❌ security_manager.php failed: " . $e->getMessage() . "\n";
}

try {
    echo "Loading security_network.php...\n";
    require_once 'security_network.php';
    echo "✅ security_network.php loaded\n";
} catch (Exception $e) {
    echo "❌ security_network.php failed: " . $e->getMessage() . "\n";
}

try {
    echo "Loading session_protection.php...\n";
    require_once 'session_protection.php';
    echo "✅ session_protection.php loaded\n";
} catch (Exception $e) {
    echo "❌ session_protection.php failed: " . $e->getMessage() . "\n";
}

echo "\n";

// Step 3: Check session data
echo "=== STEP 3: Current Session Data ===\n";
print_r($_SESSION);
echo "\n";

// Step 4: Check login status
echo "=== STEP 4: Login Status ===\n";
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    echo "✅ Logged in as: " . $_SESSION['role'] . " (ID: " . $_SESSION['user_id'] . ")\n";
} else {
    echo "❌ NOT logged in\n";
}
echo "\n";

// Step 5: Test enforceRole function
echo "=== STEP 5: Testing enforceRole('admin') ===\n";
echo "About to call enforceRole('admin')...\n";

// Capture any headers being set
ob_start();
try {
    enforceRole('admin');
    echo "✅ enforceRole('admin') PASSED - No redirect!\n";
    $headers = headers_list();
    if (!empty($headers)) {
        echo "Headers set:\n";
        print_r($headers);
    }
} catch (Exception $e) {
    echo "❌ enforceRole('admin') threw exception: " . $e->getMessage() . "\n";
}
$output = ob_get_clean();
echo $output;

echo "\n";

// Step 6: Check for any Location headers
echo "=== STEP 6: Headers Check ===\n";
$headers = headers_list();
$has_redirect = false;
foreach ($headers as $header) {
    if (stripos($header, 'Location:') !== false) {
        echo "❌ REDIRECT DETECTED: $header\n";
        $has_redirect = true;
    }
}
if (!$has_redirect) {
    echo "✅ No redirect headers found\n";
}

echo "\n=== FINAL RESULT ===\n";
if (!$has_redirect) {
    echo "✅✅✅ NO REDIRECT - Admin dashboard should work!\n";
    echo '</pre>';
    echo '<br><a href="admin_dashboard.php" style="display:inline-block;padding:15px 30px;background:#10b981;color:white;text-decoration:none;border-radius:8px;font-weight:bold;">TEST ADMIN DASHBOARD</a>';
} else {
    echo "❌ REDIRECT WILL OCCUR - This is the problem!\n";
    echo "Check the session_protection.php or propagation_prevention.php code.\n";
}

echo "</pre>";
?>
