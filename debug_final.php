<?php
$test_string = "admin' or '1'='1'";  // Added closing quote
echo "Corrected test string: '$test_string'\n\n";

$patterns = [
    "/' or '1'='1'/",
    "/'1'='1'/",
    "/or '1'='1'/",
    "/' or '[0-9]+'='[0-9]+'/",
    "/' or '.*'='.*'/",
    "/[a-z]*' or '[0-9]+'='[0-9]+'/",
    "/' or '\d+'='\d+'/"
];

foreach ($patterns as $pattern) {
    $match = preg_match($pattern, $test_string);
    echo "Pattern: " . htmlspecialchars($pattern) . " -> " . ($match ? "✅" : "❌") . "\n";
}

// Let's also test what our actual input should be
echo "\n--- Testing actual attack pattern ---\n";
$actual_attack = "admin' OR '1'='1";
$actual_lower = strtolower($actual_attack);
echo "Original: '$actual_attack'\n";
echo "Lowercase: '$actual_lower'\n";

$working_pattern = "/or '[0-9]+'='[0-9]+'/i";
echo "Working pattern test: " . (preg_match($working_pattern, $actual_lower) ? "✅" : "❌") . "\n";
?>