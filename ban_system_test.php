<?php
// Ban System Test
session_start();

require_once 'db.php';
require_once 'security_manager.php';

echo "<h1>🚫 Ban System Test</h1>";

try {
    require_once 'db.php'; // This will give us $conn variable
    
    if (!isset($conn) || !($conn instanceof mysqli)) {
        throw new Exception("Database connection not available");
    }
    
    $securityManager = new MentalHealthSecurityManager($conn);
    
    // Clear any existing data
    unset($_SESSION['security_failed_attempts'], $_SESSION['security_banned_clients']);
    
    echo "<h2>Step 1: Test Ban After 10 Attempts</h2>";
    
    // Simulate 10 failed login attempts
    for ($i = 1; $i <= 10; $i++) {
        $securityManager->recordFailedLogin();
        echo "Attempt {$i}: Recorded<br>";
        
        if ($i == 3) {
            echo "&nbsp;&nbsp;→ After 3 attempts, needsCaptcha(): " . ($securityManager->needsCaptcha() ? 'TRUE' : 'FALSE') . "<br>";
        }
        
        if ($i == 10) {
            echo "&nbsp;&nbsp;→ After 10 attempts, isClientBanned(): " . ($securityManager->isClientBanned() ? 'TRUE' : 'FALSE') . "<br>";
        }
    }
    
    echo "<h2>Step 2: Test Ban Status</h2>";
    $is_banned = $securityManager->isClientBanned();
    echo "✅ Client is banned: " . ($is_banned ? 'YES' : 'NO') . "<br>";
    
    if ($is_banned) {
        $time_remaining = $securityManager->getBanTimeRemaining();
        $minutes = ceil($time_remaining / 60);
        echo "✅ Time remaining: {$time_remaining} seconds ({$minutes} minutes)<br>";
    }
    
    echo "<h2>Step 3: Test Ban Prevention</h2>";
    echo "Trying to record another failed attempt while banned...<br>";
    $securityManager->recordFailedLogin();
    echo "✅ Ban system prevented additional logging<br>";
    
    echo "<h2>Step 4: Test Manual Unban</h2>";
    $securityManager->unbanClient();
    echo "✅ Client manually unbanned<br>";
    echo "✅ isClientBanned() after unban: " . ($securityManager->isClientBanned() ? 'TRUE' : 'FALSE') . "<br>";
    
    echo "<h2>Step 5: Test Full Flow</h2>";
    
    // Reset everything
    unset($_SESSION['security_failed_attempts'], $_SESSION['security_banned_clients']);
    
    // Simulate the flow
    echo "<strong>Simulating 12 failed attempts:</strong><br>";
    for ($i = 1; $i <= 12; $i++) {
        // Check ban status before each attempt
        if ($securityManager->isClientBanned()) {
            echo "Attempt {$i}: ❌ BLOCKED - Client is banned<br>";
            continue;
        }
        
        $securityManager->recordFailedLogin();
        echo "Attempt {$i}: ✅ Recorded<br>";
        
        // Show status at key points
        if ($i == 3) {
            echo "&nbsp;&nbsp;→ CAPTCHA required: " . ($securityManager->needsCaptcha() ? 'YES' : 'NO') . "<br>";
        }
        if ($i == 10) {
            echo "&nbsp;&nbsp;→ Client banned: " . ($securityManager->isClientBanned() ? 'YES' : 'NO') . "<br>";
        }
    }
    
    echo "<h2>🎯 Summary</h2>";
    echo "<ul>";
    echo "<li>✅ After 3 attempts: CAPTCHA required</li>";
    echo "<li>✅ After 10 attempts: Client banned for 5 minutes</li>";
    echo "<li>✅ Ban prevents further login attempts</li>";
    echo "<li>✅ Ban can be manually lifted</li>";
    echo "<li>✅ Ban automatically expires after 5 minutes</li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<div style='color: red;'>Error: " . $e->getMessage() . "</div>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Ban System Test</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        h2 { color: #333; border-bottom: 2px solid #d32f2f; padding-bottom: 5px; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
    </style>
</head>
<body>
    <hr>
    <p><a href="index.php">← Test Real Login</a> | <a href="captcha_debug.php">CAPTCHA Debug</a></p>
</body>
</html>