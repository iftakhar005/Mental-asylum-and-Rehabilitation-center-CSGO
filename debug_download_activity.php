<?php
require_once 'session_check.php';
check_login(['admin', 'chief-staff']);
require_once 'db.php';

echo "<h2>🔍 Download Activity Debug</h2>";

// Check if download_activity table exists and has data
echo "<h3>1. Table Structure Check</h3>";
$result = $conn->query("DESCRIBE download_activity");
if ($result) {
    echo "<p style='color: green;'>✅ download_activity table exists</p>";
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr style='background: #f0f0f0;'><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Key'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>❌ download_activity table NOT found: " . $conn->error . "</p>";
}

// Check current data in download_activity table
echo "<h3>2. Current Download Records</h3>";
$result = $conn->query("SELECT COUNT(*) as total FROM download_activity");
if ($result) {
    $count = $result->fetch_assoc()['total'];
    echo "<p><strong>Total records in download_activity:</strong> $count</p>";
    
    if ($count > 0) {
        echo "<h4>Recent Records:</h4>";
        $result = $conn->query("SELECT * FROM download_activity ORDER BY download_time DESC LIMIT 10");
        if ($result && $result->num_rows > 0) {
            echo "<table border='1' style='border-collapse: collapse; width: 100%; font-size: 12px;'>";
            echo "<tr style='background: #f0f0f0;'>";
            echo "<th>ID</th><th>User</th><th>File Name</th><th>Type</th><th>Classification</th><th>IP</th><th>Time</th><th>Watermarked</th>";
            echo "</tr>";
            while ($row = $result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($row['id']) . "</td>";
                echo "<td>" . htmlspecialchars($row['user_name'] ?? $row['user_id']) . "</td>";
                echo "<td>" . htmlspecialchars($row['file_name'] ?? 'N/A') . "</td>";
                echo "<td>" . htmlspecialchars($row['file_type']) . "</td>";
                echo "<td>" . htmlspecialchars($row['data_classification']) . "</td>";
                echo "<td>" . htmlspecialchars($row['ip_address']) . "</td>";
                echo "<td>" . htmlspecialchars($row['download_time']) . "</td>";
                echo "<td>" . ($row['watermarked'] ? 'Yes' : 'No') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    } else {
        echo "<p style='color: orange;'>⚠️ No download records found</p>";
    }
} else {
    echo "<p style='color: red;'>❌ Error checking records: " . $conn->error . "</p>";
}

// Check session information
echo "<h3>3. Current Session Info</h3>";
echo "<p><strong>User ID:</strong> " . ($_SESSION['user_id'] ?? 'Not set') . "</p>";
echo "<p><strong>Username:</strong> " . ($_SESSION['username'] ?? 'Not set') . "</p>";
echo "<p><strong>Role:</strong> " . ($_SESSION['role'] ?? 'Not set') . "</p>";
echo "<p><strong>IP Address:</strong> " . ($_SERVER['REMOTE_ADDR'] ?? 'Not detected') . "</p>";

// Test DLP system directly
echo "<h3>4. DLP System Test</h3>";
try {
    require_once 'dlp_system.php';
    $dlp = new DataLossPreventionSystem();
    
    // Try to log a test download
    $test_result = $dlp->logDownloadActivity(
        'txt',                    // file_type
        'debug_test_file.txt',   // file_name
        100,                     // file_size
        'internal',              // data_classification
        null,                    // export_request_id
        false                    // watermarked
    );
    
    if ($test_result) {
        echo "<p style='color: green;'>✅ DLP system test successful - logged test download</p>";
    } else {
        echo "<p style='color: red;'>❌ DLP system test failed</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ DLP system error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Check latest records after test
echo "<h3>5. Records After Test</h3>";
$result = $conn->query("SELECT * FROM download_activity ORDER BY download_time DESC LIMIT 5");
if ($result && $result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%; font-size: 12px;'>";
    echo "<tr style='background: #f0f0f0;'>";
    echo "<th>User</th><th>File Name</th><th>Type</th><th>Classification</th><th>Time</th>";
    echo "</tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['user_name'] ?? $row['user_id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['file_name'] ?? 'N/A') . "</td>";
        echo "<td>" . htmlspecialchars($row['file_type']) . "</td>";
        echo "<td>" . htmlspecialchars($row['data_classification']) . "</td>";
        echo "<td>" . htmlspecialchars($row['download_time']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>❌ Still no records found</p>";
}

// Check connection details
echo "<h3>6. Database Connection</h3>";
echo "<p><strong>Connection Status:</strong> " . ($conn ? '✅ Connected' : '❌ Failed') . "</p>";
if ($conn) {
    $db_info = $conn->get_server_info();
    echo "<p><strong>MySQL Version:</strong> $db_info</p>";
    
    // Get current database name
    $result = $conn->query("SELECT DATABASE() as db_name");
    if ($result) {
        $db_name = $result->fetch_assoc()['db_name'];
        echo "<p><strong>Current Database:</strong> $db_name</p>";
    }
}

?>

<div style="background: #e3f2fd; padding: 20px; border-radius: 8px; margin: 20px 0;">
    <h3 style="color: #1976d2; margin-top: 0;">🛠️ Troubleshooting Steps:</h3>
    <ol>
        <li><strong>If table doesn't exist:</strong> Run the dlp_database.sql script</li>
        <li><strong>If no records:</strong> Downloads aren't being logged properly</li>
        <li><strong>If session issues:</strong> Make sure you're logged in properly</li>
        <li><strong>If DLP test fails:</strong> There's an issue with the DLP system class</li>
    </ol>
</div>

<p><a href="download_test.php">🔄 Try Download Test Again</a> | <a href="dlp_management.php">📊 DLP Management</a></p>