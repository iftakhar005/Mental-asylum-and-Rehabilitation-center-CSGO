<?php
// Test the regex pattern directly
$test_string = "admin' OR '1'='1";
$patterns = [
    '/\'\s*(or|and)\s*\'\w*\'\s*=\s*\'\w*\'/i',
    '/\'\s*(or|and)\s*1\s*=\s*1/i',
    '/\'\s*(or|and)\s*1\s*=\s*0/i',
    '/\'\s*(or|and)\s*true/i',
    '/\'\s*(or|and)\s*false/i',
    '/\'\s*(or|and)\s*\'\d+\'\s*=\s*\'\d+\'/i',
    '/(or|and)\s*\'\d+\'\s*=\s*\'\d+\'/i'
];

echo "Testing string: " . htmlspecialchars($test_string) . "\n\n";

foreach ($patterns as $i => $pattern) {
    $match = preg_match($pattern, $test_string);
    echo "Pattern " . ($i + 1) . ": " . htmlspecialchars($pattern) . "\n";
    echo "Match: " . ($match ? "YES" : "NO") . "\n\n";
}

// Let me try a more specific pattern
$new_pattern = "/'\s*(or|and)\s*'[^']*'\s*=\s*'[^']*'/i";
echo "New pattern: " . htmlspecialchars($new_pattern) . "\n";
echo "Match: " . (preg_match($new_pattern, $test_string) ? "YES" : "NO") . "\n\n";

// Even more specific
$specific_pattern = "/' or '[^']*'='[^']*'/i";
echo "Specific pattern: " . htmlspecialchars($specific_pattern) . "\n";
echo "Match: " . (preg_match($specific_pattern, $test_string) ? "YES" : "NO") . "\n";
?>