<?php
// CAPTCHA Test Fix
session_start();

require_once 'db.php';
require_once 'security_manager.php';

echo "<h1>🔧 CAPTCHA Fix Test</h1>";

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        throw new Exception("Database connection failed");
    }
    
    $securityManager = new MentalHealthSecurityManager($conn);
    
    // Clear any existing session data
    unset($_SESSION['captcha_answer'], $_SESSION['captcha_time'], $_SESSION['captcha_question']);
    
    echo "<h2>Step 1: Force CAPTCHA Requirement</h2>";
    // Force failed attempts to trigger CAPTCHA
    for ($i = 0; $i < 3; $i++) {
        $securityManager->recordFailedLogin();
    }
    echo "✅ Recorded 3 failed attempts<br>";
    echo "✅ needsCaptcha(): " . ($securityManager->needsCaptcha() ? 'TRUE' : 'FALSE') . "<br>";
    
    echo "<h2>Step 2: Generate CAPTCHA (First Time)</h2>";
    $captcha_data = $securityManager->generateCaptcha();
    echo "✅ Question: " . $captcha_data['question'] . "<br>";
    echo "✅ Answer: " . $captcha_data['answer'] . "<br>";
    echo "✅ Session captcha_question: " . $_SESSION['captcha_question'] . "<br>";
    echo "✅ Session captcha_answer: " . $_SESSION['captcha_answer'] . "<br>";
    
    echo "<h2>Step 3: Simulate Page Reload (Don't Regenerate)</h2>";
    // Simulate what happens on page reload
    $show_captcha = $securityManager->needsCaptcha();
    if ($show_captcha) {
        if (!isset($_SESSION['captcha_answer']) || !isset($_SESSION['captcha_time'])) {
            echo "❌ Would regenerate CAPTCHA (BAD)<br>";
        } else {
            echo "✅ CAPTCHA exists, using stored question<br>";
            $captcha_question = $_SESSION['captcha_question'];
            echo "✅ Stored question: " . $captcha_question . "<br>";
        }
    }
    
    echo "<h2>Step 4: Test Validation with Correct Answer</h2>";
    $correct_answer = $_SESSION['captcha_answer'];
    echo "Testing with answer: " . $correct_answer . "<br>";
    
    $validation_result = $securityManager->validateCaptcha($correct_answer);
    echo "✅ Validation result: " . ($validation_result ? 'SUCCESS' : 'FAILED') . "<br>";
    
    if ($validation_result) {
        echo "✅ Session cleared after validation: " . (isset($_SESSION['captcha_answer']) ? 'NO' : 'YES') . "<br>";
    }
    
    echo "<h2>Step 5: Test Complete Flow</h2>";
    
    // Reset for complete flow test
    unset($_SESSION['captcha_answer'], $_SESSION['captcha_time'], $_SESSION['captcha_question']);
    for ($i = 0; $i < 3; $i++) {
        $securityManager->recordFailedLogin();
    }
    
    // Simulate index.php logic
    $show_captcha = $securityManager->needsCaptcha();
    if ($show_captcha) {
        // Only generate CAPTCHA if not already in session
        if (!isset($_SESSION['captcha_answer']) || !isset($_SESSION['captcha_time'])) {
            $captcha_data = $securityManager->generateCaptcha();
            $captcha_question = $captcha_data['question'];
            echo "✅ Generated new CAPTCHA: " . $captcha_question . "<br>";
        } else {
            if (!isset($_SESSION['captcha_question'])) {
                $captcha_data = $securityManager->generateCaptcha();
                $captcha_question = $captcha_data['question'];
                echo "❌ Fallback regeneration<br>";
            } else {
                $captcha_question = $_SESSION['captcha_question'];
                echo "✅ Used stored question: " . $captcha_question . "<br>";
            }
        }
    }
    
    // Now simulate form submission
    $test_answer = $_SESSION['captcha_answer'];
    echo "✅ Submitting answer: " . $test_answer . "<br>";
    
    if (!$securityManager->validateCaptcha($test_answer)) {
        echo "❌ CAPTCHA validation failed!<br>";
    } else {
        echo "✅ CAPTCHA validation succeeded!<br>";
    }
    
} catch (Exception $e) {
    echo "<div style='color: red;'>Error: " . $e->getMessage() . "</div>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>CAPTCHA Fix Test</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        h2 { color: #333; border-bottom: 2px solid #007cba; padding-bottom: 5px; }
        .success { color: green; }
        .error { color: red; }
    </style>
</head>
<body>
    <hr>
    <p><a href="index.php">← Test Real Login Now</a> | <a href="captcha_debug.php">Debug Tool</a></p>
</body>
</html>