<?php
// SQL Injection Detection Test Suite
session_start();

require_once 'db.php';
require_once 'security_manager.php';

echo "<h1>🛡️ SQL Injection Detection Test Suite</h1>";

try {
    require_once 'db.php'; // This will give us $conn variable
    
    if (!isset($conn) || !($conn instanceof mysqli)) {
        throw new Exception("Database connection not available");
    }
    
    $securityManager = new MentalHealthSecurityManager($conn);
    
    echo "<h2>✅ Testing All 25+ SQL Injection Patterns</h2>";
    
    // Test cases grouped by attack type
    $test_cases = [
        'UNION Attacks' => [
            "admin' UNION SELECT * FROM users --",
            "test' UNION ALL SELECT username,password FROM admin --",
            "id=1 UNION SELECT null,username,password FROM users",
            "' UNION SELECT database(),user(),version() --"
        ],
        
        'Boolean-based Blind Injection' => [
            "admin' OR '1'='1",
            "user' AND 1=1 --",
            "test' OR 1=0 --",
            "admin' OR true --",
            "user' AND false --",
            "' OR 'a'='a' --"
        ],
        
        'Time-based Blind Injection' => [
            "admin'; SLEEP(5) --",
            "user' AND BENCHMARK(5000000,MD5(1)) --",
            "test'; WAITFOR DELAY '00:00:05' --",
            "admin' AND pg_sleep(5) --"
        ],
        
        'Error-based Injection' => [
            "admin' AND extractvalue(1,concat(0x7e,version(),0x7e)) --",
            "user' AND updatexml(1,concat(0x7e,database(),0x7e),1) --",
            "test' AND xpath(1,concat(0x7e,user(),0x7e)) --"
        ],
        
        'Stacked Queries' => [
            "admin'; DROP TABLE users; --",
            "user'; DELETE FROM admin; --",
            "test'; INSERT INTO users VALUES('hacker','pass'); --",
            "id=1; CREATE TABLE temp(id INT); --",
            "admin'; ALTER TABLE users ADD column temp VARCHAR(50); --"
        ],
        
        'Comment-based Injection' => [
            "admin'/**/OR/**/1=1/**/--",
            "user'-- comment here",
            "test'# hash comment"
        ],
        
        'Information Schema Attacks' => [
            "admin' UNION SELECT * FROM information_schema.tables --",
            "user' UNION SELECT * FROM sys.databases --",
            "test' UNION SELECT * FROM mysql.user --",
            "id=1 UNION SELECT * FROM performance_schema.tables"
        ],
        
        'File Operations' => [
            "admin' UNION SELECT load_file('/etc/passwd') --",
            "user' INTO OUTFILE '/tmp/result.txt' --",
            "test' INTO DUMPFILE '/tmp/dump.txt' --"
        ],
        
        'Database Functions' => [
            "admin' UNION SELECT database() --",
            "user' UNION SELECT version() --",
            "test' UNION SELECT user() --",
            "id=1 UNION SELECT current_user --",
            "admin' UNION SELECT connection_id() --"
        ],
        
        'Hex Encoding' => [
            "admin' OR 0x41414141 --",
            "user' UNION SELECT 0x48656C6C6F --",
            "test' AND 0x313D31 --"
        ],
        
        'Concatenation Functions' => [
            "admin' UNION SELECT concat(username,password) FROM users --",
            "user' UNION SELECT group_concat(table_name) FROM information_schema.tables --"
        ],
        
        'Conditional Statements' => [
            "admin' AND if(1=1,sleep(5),0) --",
            "user' UNION SELECT case when 1=1 then 'true' else 'false' end --",
            "test' AND case when user()='root' then sleep(5) else 0 end --"
        ],
        
        'System Commands' => [
            "admin'; EXEC xp_cmdshell('dir') --",
            "user'; EXEC sp_configure 'show advanced options',1 --",
            "test'; EXEC sp_executesql 'SELECT @@version' --"
        ],
        
        'Safe Inputs (Should NOT be detected)' => [
            "admin@example.com",
            "John O'Connor",
            "password123",
            "user@test.com",
            "normal text input",
            "1234567890"
        ]
    ];
    
    $total_tests = 0;
    $detected_attacks = 0;
    $false_positives = 0;
    
    foreach ($test_cases as $category => $tests) {
        echo "<h3>🔍 {$category}</h3>";
        echo "<ul>";
        
        foreach ($tests as $test_input) {
            $total_tests++;
            $is_detected = $securityManager->testSQLInjectionDetection($test_input);
            
            if ($category === 'Safe Inputs (Should NOT be detected)') {
                if ($is_detected) {
                    echo "<li style='color: red;'>❌ FALSE POSITIVE: \"" . htmlspecialchars($test_input) . "\" - Incorrectly flagged as attack</li>";
                    $false_positives++;
                } else {
                    echo "<li style='color: green;'>✅ CORRECT: \"" . htmlspecialchars($test_input) . "\" - Correctly identified as safe</li>";
                }
            } else {
                if ($is_detected) {
                    echo "<li style='color: green;'>✅ DETECTED: \"" . htmlspecialchars($test_input) . "\" - Attack blocked</li>";
                    $detected_attacks++;
                } else {
                    echo "<li style='color: red;'>❌ MISSED: \"" . htmlspecialchars($test_input) . "\" - Attack not detected!</li>";
                }
            }
        }
        echo "</ul>";
    }
    
    echo "<h2>📊 Test Results Summary</h2>";
    $safe_inputs = count($test_cases['Safe Inputs (Should NOT be detected)']);
    $attack_inputs = $total_tests - $safe_inputs;
    $missed_attacks = $attack_inputs - $detected_attacks;
    
    echo "<div style='background: #f0f0f0; padding: 15px; border-radius: 5px;'>";
    echo "<strong>Total Tests:</strong> {$total_tests}<br>";
    echo "<strong>Attack Patterns Tested:</strong> {$attack_inputs}<br>";
    echo "<strong>Attacks Detected:</strong> {$detected_attacks}<br>";
    echo "<strong>Attacks Missed:</strong> {$missed_attacks}<br>";
    echo "<strong>False Positives:</strong> {$false_positives}<br>";
    echo "<strong>Safe Inputs Tested:</strong> {$safe_inputs}<br>";
    
    $detection_rate = ($attack_inputs > 0) ? round(($detected_attacks / $attack_inputs) * 100, 2) : 0;
    $accuracy = round((($detected_attacks + ($safe_inputs - $false_positives)) / $total_tests) * 100, 2);
    
    echo "<br>";
    echo "<strong style='color: " . ($detection_rate >= 95 ? 'green' : ($detection_rate >= 80 ? 'orange' : 'red')) . "';'>Detection Rate: {$detection_rate}%</strong><br>";
    echo "<strong style='color: " . ($accuracy >= 95 ? 'green' : ($accuracy >= 80 ? 'orange' : 'red')) . "';'>Overall Accuracy: {$accuracy}%</strong>";
    echo "</div>";
    
    echo "<h2>🎯 Real-World Attack Simulation</h2>";
    
    // Test some real-world attack scenarios
    $real_attacks = [
        "Login Bypass" => "admin'--",
        "Data Extraction" => "1' UNION SELECT username,password FROM users--",
        "Database Enumeration" => "1' AND (SELECT COUNT(*) FROM information_schema.tables)>0--",
        "Blind SQL Injection" => "1' AND ASCII(SUBSTRING(database(),1,1))>64--",
        "Second-Order Injection" => "admin'; UPDATE users SET password='hacked' WHERE username='admin'--"
    ];
    
    echo "<ul>";
    foreach ($real_attacks as $attack_name => $attack_payload) {
        $detected = $securityManager->testSQLInjectionDetection($attack_payload);
        $status = $detected ? "✅ BLOCKED" : "❌ VULNERABLE";
        $color = $detected ? "green" : "red";
        echo "<li style='color: {$color};'><strong>{$attack_name}:</strong> {$status}</li>";
    }
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<div style='color: red;'>Error: " . $e->getMessage() . "</div>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>SQL Injection Detection Test</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 1000px; margin: 0 auto; padding: 20px; }
        h1 { color: #d32f2f; }
        h2 { color: #333; border-bottom: 2px solid #007cba; padding-bottom: 5px; }
        h3 { color: #666; }
        ul { margin-bottom: 20px; }
        li { margin: 5px 0; }
        .summary { background: #f5f5f5; padding: 15px; border-radius: 5px; margin: 20px 0; }
    </style>
</head>
<body>
    <hr>
    <p><a href="index.php">← Back to Login</a> | <a href="ban_system_test.php">Test Ban System</a></p>
</body>
</html>