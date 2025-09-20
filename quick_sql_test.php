<?php
// Quick SQL Injection Pattern Test
session_start();

require_once 'db.php';
require_once 'security_manager.php';

echo "<h1>🔬 Quick SQL Injection Test</h1>";

try {
    require_once 'db.php'; // This will give us $conn variable
    
    if (!isset($conn) || !($conn instanceof mysqli)) {
        throw new Exception("Database connection not available");
    }
    
    $securityManager = new MentalHealthSecurityManager($conn);
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $test_input = $_POST['test_input'] ?? '';
        
        echo "<h2>Test Result for: " . htmlspecialchars($test_input) . "</h2>";
        
        $is_detected = $securityManager->testSQLInjectionDetection($test_input);
        
        if ($is_detected) {
            echo "<div style='background: #ffebee; color: #c62828; padding: 15px; border-left: 4px solid #c62828; margin: 10px 0;'>";
            echo "<strong>🚫 SQL INJECTION DETECTED!</strong><br>";
            echo "This input contains malicious SQL patterns and would be <strong>BLOCKED</strong> by your security system.";
            echo "</div>";
        } else {
            echo "<div style='background: #e8f5e8; color: #2e7d32; padding: 15px; border-left: 4px solid #2e7d32; margin: 10px 0;'>";
            echo "<strong>✅ INPUT SAFE</strong><br>";
            echo "This input passed all security checks and would be <strong>ALLOWED</strong> by your system.";
            echo "</div>";
        }
        
        // Show which specific patterns might have triggered
        if ($is_detected) {
            echo "<h3>🔍 Pattern Analysis</h3>";
            
            $patterns_to_check = [
                'UNION attack' => '/union\s+(all\s+)?select/i',
                'Boolean injection' => '/\'\s*(or|and)\s*1\s*=\s*1/i',
                'Comment injection' => '/--\s/',
                'Stacked queries' => '/;\s*(drop|delete|update|insert)/i',
                'Database functions' => '/database\s*\(\s*\)/i',
                'System commands' => '/xp_cmdshell/i',
                'Time-based injection' => '/sleep\s*\(/i'
            ];
            
            echo "<ul>";
            foreach ($patterns_to_check as $name => $pattern) {
                if (preg_match($pattern, strtolower($test_input))) {
                    echo "<li style='color: red;'>❌ <strong>{$name}</strong> detected</li>";
                }
            }
            echo "</ul>";
        }
    }
    
} catch (Exception $e) {
    echo "<div style='color: red;'>Error: " . $e->getMessage() . "</div>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Quick SQL Injection Test</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        .form-group { margin: 15px 0; }
        textarea { width: 100%; height: 100px; padding: 10px; font-family: monospace; }
        button { padding: 10px 20px; background: #007cba; color: white; border: none; border-radius: 5px; cursor: pointer; }
        button:hover { background: #005a8b; }
        .examples { background: #f5f5f5; padding: 15px; border-radius: 5px; margin: 20px 0; }
    </style>
</head>
<body>
    <h2>Test Your Input</h2>
    <form method="POST">
        <div class="form-group">
            <label for="test_input">Enter input to test for SQL injection:</label><br>
            <textarea name="test_input" placeholder="Type or paste your test input here..." required></textarea>
        </div>
        <button type="submit">🔍 Test Input</button>
    </form>
    
    <div class="examples">
        <h3>🧪 Example Attacks to Test:</h3>
        <p><strong>Try these malicious inputs:</strong></p>
        <ul>
            <li><code>admin' OR 1=1 --</code></li>
            <li><code>'; DROP TABLE users; --</code></li>
            <li><code>admin' UNION SELECT * FROM users --</code></li>
            <li><code>test'; SLEEP(5); --</code></li>
            <li><code>admin' AND database() --</code></li>
        </ul>
        
        <p><strong>Try these safe inputs:</strong></p>
        <ul>
            <li><code>admin@example.com</code></li>
            <li><code>John O'Connor</code></li>
            <li><code>password123</code></li>
        </ul>
    </div>
    
    <hr>
    <p><a href="sql_injection_test.php">🧪 Run Full Test Suite</a> | <a href="index.php">← Back to Login</a></p>
</body>
</html>