<?php
require_once 'db.php';

echo "=== CURRENT DATABASE TABLES ===\n";
$result = $conn->query('SHOW TABLES');
$existing_tables = [];
while($row = $result->fetch_array()) {
    $existing_tables[] = $row[0];
    echo "✅ " . $row[0] . "\n";
}

echo "\n=== DLP TABLES NEEDED ===\n";
$dlp_tables = [
    'dlp_config' => 'Configuration settings',
    'data_classification' => 'Data sensitivity levels', 
    'export_approval_requests' => 'Export approval workflow',
    'download_activity' => 'Download monitoring',
    'retention_policies' => 'Data retention rules',
    'data_access_audit' => 'Security audit trail'
];

$missing_tables = [];
foreach($dlp_tables as $table => $description) {
    if(in_array($table, $existing_tables)) {
        echo "✅ $table - $description (EXISTS)\n";
    } else {
        echo "❌ $table - $description (MISSING)\n";
        $missing_tables[] = $table;
    }
}

echo "\n=== SUMMARY ===\n";
if(empty($missing_tables)) {
    echo "🎉 All DLP tables exist! No installation needed.\n";
} else {
    echo "⚠️  Missing " . count($missing_tables) . " DLP tables.\n";
    echo "📋 Missing tables: " . implode(', ', $missing_tables) . "\n";
    echo "🚀 Run install_dlp.php to create them.\n";
}
?>