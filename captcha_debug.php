<?php
// CAPTCHA Debug Tool
session_start();

require_once 'db.php';
require_once 'security_manager.php';

echo "<h1>🐛 CAPTCHA Debug Tool</h1>";

try {
    require_once 'db.php'; // This will give us $conn variable
    
    if (!isset($conn) || !($conn instanceof mysqli)) {
        throw new Exception("Database connection not available");
    }
    
    $securityManager = new MentalHealthSecurityManager($conn);
    
    echo "<h2>Current Session State:</h2>";
    echo "<pre>";
    echo "Session ID: " . session_id() . "\n";
    echo "CAPTCHA Answer in Session: " . ($_SESSION['captcha_answer'] ?? 'NOT SET') . "\n";
    echo "CAPTCHA Time in Session: " . ($_SESSION['captcha_time'] ?? 'NOT SET') . "\n";
    if (isset($_SESSION['captcha_time'])) {
        echo "Time since CAPTCHA generated: " . (time() - $_SESSION['captcha_time']) . " seconds\n";
    }
    echo "</pre>";
    
    // Force failed attempts to trigger CAPTCHA
    for ($i = 0; $i < 3; $i++) {
        $securityManager->recordFailedLogin();
    }
    
    echo "<h2>Generating New CAPTCHA:</h2>";
    $captcha_data = $securityManager->generateCaptcha();
    echo "<strong>Question:</strong> " . $captcha_data['question'] . "<br>";
    echo "<strong>Correct Answer:</strong> " . $captcha_data['answer'] . "<br>";
    
    echo "<h2>Session After Generation:</h2>";
    echo "<pre>";
    echo "CAPTCHA Answer in Session: " . $_SESSION['captcha_answer'] . "\n";
    echo "CAPTCHA Time in Session: " . $_SESSION['captcha_time'] . "\n";
    echo "</pre>";
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $user_answer = $_POST['test_answer'] ?? '';
        
        echo "<h2>Testing Validation:</h2>";
        echo "<strong>User Answer:</strong> '" . $user_answer . "'<br>";
        echo "<strong>Expected Answer:</strong> '" . $_SESSION['captcha_answer'] . "'<br>";
        echo "<strong>User Answer (int):</strong> " . (int)$user_answer . "<br>";
        echo "<strong>Expected Answer (int):</strong> " . (int)$_SESSION['captcha_answer'] . "<br>";
        echo "<strong>Comparison Result:</strong> " . ((int)$user_answer === (int)$_SESSION['captcha_answer'] ? 'MATCH' : 'NO MATCH') . "<br>";
        
        $validation_result = $securityManager->validateCaptcha($user_answer);
        echo "<strong>validateCaptcha() Result:</strong> " . ($validation_result ? 'TRUE' : 'FALSE') . "<br>";
        
        echo "<h2>Session After Validation:</h2>";
        echo "<pre>";
        echo "CAPTCHA Answer in Session: " . ($_SESSION['captcha_answer'] ?? 'CLEARED') . "\n";
        echo "CAPTCHA Time in Session: " . ($_SESSION['captcha_time'] ?? 'CLEARED') . "\n";
        echo "</pre>";
    }
    
} catch (Exception $e) {
    echo "<div style='color: red;'>Error: " . $e->getMessage() . "</div>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>CAPTCHA Debug</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 5px; }
        .form-group { margin: 15px 0; }
        input[type="text"] { padding: 10px; width: 200px; }
        button { padding: 10px 20px; background: #007cba; color: white; border: none; border-radius: 5px; }
    </style>
</head>
<body>
    <h2>Test CAPTCHA Validation:</h2>
    <form method="POST">
        <div class="form-group">
            <label>Enter your answer for: <strong><?php echo $captcha_data['question'] ?? 'Generate CAPTCHA first'; ?></strong></label><br>
            <input type="text" name="test_answer" placeholder="Enter answer" required>
        </div>
        <button type="submit">Test Validation</button>
    </form>
    
    <hr>
    <p><a href="index.php">← Back to Login</a></p>
</body>
</html>