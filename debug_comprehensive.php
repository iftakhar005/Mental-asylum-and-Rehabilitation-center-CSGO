<?php
// Test more comprehensive patterns
$test_string = "admin' OR '1'='1";
$test_cases = [
    "admin' OR '1'='1",
    "user' AND '2'='2", 
    "' OR '1'='1",
    "test' OR 1=1",
    "admin' or '1'='1",  // lowercase
    "name' OR '0'='0"
];

echo "Testing comprehensive patterns:\n\n";

$comprehensive_patterns = [
    // Pattern 1: Any quote followed by OR/AND and quoted comparison
    "/'\s+(or|and)\s+'[^']*'\s*=\s*'[^']*'/i",
    
    // Pattern 2: Catch any word, quote, OR/AND, quoted comparison  
    "/\w*'\s+(or|and)\s+'[^']*'\s*=\s*'[^']*'/i",
    
    // Pattern 3: Just look for OR/AND with quoted equality
    "/(or|and)\s+'[^']*'\s*=\s*'[^']*'/i",
    
    // Pattern 4: Look for the '1'='1' pattern specifically
    "/'[0-9]+'\s*=\s*'[0-9]+'/i"
];

foreach ($test_cases as $test) {
    echo "Testing: " . htmlspecialchars($test) . "\n";
    
    foreach ($comprehensive_patterns as $i => $pattern) {
        $match = preg_match($pattern, $test);
        echo "  Pattern " . ($i + 1) . ": " . ($match ? "✅ MATCH" : "❌ NO MATCH") . "\n";
    }
    echo "\n";
}
?>