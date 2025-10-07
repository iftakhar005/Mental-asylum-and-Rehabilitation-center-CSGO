<?php
require_once 'db.php';
require_once 'security_manager.php';

$security = new MentalHealthSecurityManager($conn);

// Test the specific attack that was missed
$test_attack = "admin' OR '1'='1";

echo "<h2>Testing Specific Boolean-based Blind Injection</h2>\n";
echo "<strong>Attack Pattern:</strong> " . htmlspecialchars($test_attack) . "<br>\n";

$is_detected = $security->detectSQLInjection($test_attack);

if ($is_detected) {
    echo "<span style='color: green;'>✅ DETECTED: Attack successfully identified!</span><br>\n";
} else {
    echo "<span style='color: red;'>❌ MISSED: Attack not detected!</span><br>\n";
}

echo "<br><h3>Additional Boolean Injection Tests:</h3>\n";

// Test other similar patterns
$test_patterns = [
    "admin' OR '2'='2",
    "user' OR '3'='3",
    "' OR '1'='1'--",
    "admin' OR '0'='0",
    "test' OR '5'='5",
    "OR '1'='1",
    "' OR 1=1--",
    "admin' OR 1=1#"
];

foreach ($test_patterns as $pattern) {
    $detected = $security->detectSQLInjection($pattern);
    $status = $detected ? "<span style='color: green;'>✅ DETECTED</span>" : "<span style='color: red;'>❌ MISSED</span>";
    echo "$status: " . htmlspecialchars($pattern) . "<br>\n";
}
?>