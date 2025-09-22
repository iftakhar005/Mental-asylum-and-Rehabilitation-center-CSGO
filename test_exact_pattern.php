<?php
$test_input = "admin' OR '1'='1";
$lowercase_input = strtolower($test_input);

echo "Original: '$test_input'\n";
echo "Lowercase: '$lowercase_input'\n\n";

// Test the exact patterns from our code
$patterns = [
    '/\' (or|and) \'[0-9]+\'=\'[0-9]+\'/i',
    '/or \'[0-9]+\'=\'[0-9]+\'/i'
];

foreach ($patterns as $i => $pattern) {
    $match = preg_match($pattern, $lowercase_input);
    echo "Pattern " . ($i + 1) . ": " . htmlspecialchars($pattern) . "\n";
    echo "Result: " . ($match ? "✅ DETECTED" : "❌ MISSED") . "\n\n";
}

// Let me examine the exact format
echo "Character analysis of: '$lowercase_input'\n";
echo "Looking for: ' or '[digits]'='[digits]'\n\n";

// Try a super simple pattern
$simple_pattern = "/ or '[0-9]+'='[0-9]+/";
$match = preg_match($simple_pattern, $lowercase_input);
echo "Simple pattern: " . htmlspecialchars($simple_pattern) . "\n";
echo "Result: " . ($match ? "✅ DETECTED" : "❌ MISSED") . "\n";
?>