<?php
require_once 'db.php';

echo "<h2>🔍 DLP Database Table Check</h2>";

// Check if all required DLP tables exist
$required_tables = [
    'dlp_config',
    'data_classification', 
    'export_approval_requests',
    'download_activity',
    'retention_policies',
    'data_access_audit'
];

$existing_tables = [];
$missing_tables = [];

foreach ($required_tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result && $result->num_rows > 0) {
        $existing_tables[] = $table;
        echo "<p style='color: green;'>✅ Table '$table' exists</p>";
    } else {
        $missing_tables[] = $table;
        echo "<p style='color: red;'>❌ Table '$table' is MISSING</p>";
    }
}

if (!empty($missing_tables)) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h3>⚠️ Missing Tables Detected</h3>";
    echo "<p>You need to run the DLP database creation script first!</p>";
    echo "<p><strong>Missing tables:</strong> " . implode(', ', $missing_tables) . "</p>";
    echo "<p><strong>Action needed:</strong> Import the <code>dlp_database.sql</code> file into your database</p>";
    echo "</div>";
    
    echo "<div style='background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h4>📋 How to fix:</h4>";
    echo "<ol>";
    echo "<li>Go to phpMyAdmin</li>";
    echo "<li>Select your database</li>";
    echo "<li>Click 'Import' tab</li>";
    echo "<li>Choose the <code>dlp_database.sql</code> file</li>";
    echo "<li>Click 'Go' to create the tables</li>";
    echo "</ol>";
    echo "</div>";
} else {
    echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h3>🎉 All DLP Tables Ready!</h3>";
    echo "<p>All " . count($existing_tables) . " required DLP tables are present in your database.</p>";
    echo "<p><a href='dlp_setup_test_data.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Continue with Test Data Setup</a></p>";
    echo "</div>";
}

// Show database connection info
echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
echo "<h4>📊 Database Info:</h4>";
echo "<p><strong>Host:</strong> " . (defined('DB_HOST') ? DB_HOST : 'localhost') . "</p>";
echo "<p><strong>Database:</strong> " . (defined('DB_NAME') ? DB_NAME : 'Unknown') . "</p>";
echo "<p><strong>Connection:</strong> " . ($conn ? '✅ Connected' : '❌ Failed') . "</p>";
echo "</div>";
?>

<p><a href="admin_dashboard.php">← Back to Dashboard</a></p>