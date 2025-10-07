<?php
// Test with lowercase like the function does
$test_string = strtolower("admin' OR '1'='1");
echo "Lowercase test string: " . htmlspecialchars($test_string) . "\n\n";

$patterns = [
    "/' or '[^']*'='[^']*'/i",
    "/' (or|and) '[^']*'='[^']*'/i",
    "/'\s+(or|and)\s+'[^']*'\s*=\s*'[^']*'/i",
    "/(or|and)\s+'[^']*'\s*=\s*'[^']*'/i",
    "/'[0-9]+'\s*=\s*'[0-9]+'/i",
    "/' or '[0-9]+'\s*=\s*'[0-9]+'/i"
];

foreach ($patterns as $i => $pattern) {
    $match = preg_match($pattern, $test_string);
    echo "Pattern " . ($i + 1) . ": " . htmlspecialchars($pattern) . "\n";
    echo "Match: " . ($match ? "✅ YES" : "❌ NO") . "\n\n";
}
?>