<?php
$test_string = "admin' or '1'='1";
echo "Test string: '$test_string'\n";
echo "Length: " . strlen($test_string) . "\n\n";

// Let's check character by character
for ($i = 0; $i < strlen($test_string); $i++) {
    echo "Position $i: '" . $test_string[$i] . "' (ASCII: " . ord($test_string[$i]) . ")\n";
}

echo "\n";

// Simple test patterns
$simple_patterns = [
    "/' or /",
    "/'1'/",
    "/='1'/",
    "/' or '1'='1'/",
    "/admin' or '1'='1'/",
    "/or '1'='1'/",
    "/'1'='1'/"
];

foreach ($simple_patterns as $pattern) {
    $match = preg_match($pattern, $test_string);
    echo "Pattern: " . htmlspecialchars($pattern) . " -> " . ($match ? "✅" : "❌") . "\n";
}
?>