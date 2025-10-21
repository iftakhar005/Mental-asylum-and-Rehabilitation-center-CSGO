<?php
/**
 * DLP System Verification
 * Tests if all DLP components are properly implemented
 */

require_once 'db.php';
require_once 'dlp_system.php';

?>
<!DOCTYPE html>
<html>
<head>
    <title>DLP Implementation Check</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { background: white; padding: 20px; border-radius: 8px; max-width: 1000px; margin: 0 auto; }
        h2 { color: #333; border-bottom: 2px solid #2196F3; padding-bottom: 10px; }
        .success { color: green; }
        .fail { color: red; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #2196F3; color: white; }
        tr:hover { background-color: #f5f5f5; }
        .summary { background: #e3f2fd; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .green-bg { background-color: #c8e6c9; }
        .red-bg { background-color: #ffcdd2; }
    </style>
</head>
<body>
    <div class="container">
        <h2>🔒 DLP System Implementation Check</h2>
        
        <?php
        // Test 1: Check required tables
        echo "<h3>Test 1: Database Tables</h3>";
        
        $required_tables = [
            'data_classification',
            'export_approval_requests',
            'download_activity',
            'data_access_audit',
            'retention_policies',
            'dlp_config'
        ];
        
        echo "<table>";
        echo "<tr><th>Table Name</th><th>Status</th><th>Record Count</th></tr>";
        
        $all_tables_exist = true;
        foreach ($required_tables as $table) {
            $check_query = "SHOW TABLES LIKE '$table'";
            $result = $conn->query($check_query);
            
            if ($result && $result->num_rows > 0) {
                // Table exists, count records
                $count_query = "SELECT COUNT(*) as count FROM $table";
                $count_result = $conn->query($count_query);
                $count = $count_result ? $count_result->fetch_assoc()['count'] : 0;
                
                echo "<tr class='green-bg'>";
                echo "<td>$table</td>";
                echo "<td class='success'>✅ EXISTS</td>";
                echo "<td>$count records</td>";
                echo "</tr>";
            } else {
                echo "<tr class='red-bg'>";
                echo "<td>$table</td>";
                echo "<td class='fail'>❌ MISSING</td>";
                echo "<td>-</td>";
                echo "</tr>";
                $all_tables_exist = false;
            }
        }
        echo "</table>";
        
        if (!$all_tables_exist) {
            echo "<p class='fail'>⚠️ Some tables are missing. Run <a href='install_dlp.php'>install_dlp.php</a></p>";
        }
        
        // Test 2: Check if DLP class exists
        echo "<h3>Test 2: DLP Class</h3>";
        if (class_exists('DataLossPreventionSystem')) {
            echo "<p class='success'>✅ DataLossPreventionSystem class exists</p>";
        } else {
            echo "<p class='fail'>❌ DataLossPreventionSystem class NOT found</p>";
            exit;
        }
        
        // Test 3: Initialize DLP system
        echo "<h3>Test 3: Initialization</h3>";
        try {
            session_start();
            $_SESSION['user_id'] = 1;
            $_SESSION['role'] = 'admin';
            $_SESSION['username'] = 'Test Admin';
            
            $dlp = new DataLossPreventionSystem();
            echo "<p class='success'>✅ DLP System initialized successfully</p>";
        } catch (Exception $e) {
            echo "<p class='fail'>❌ DLP initialization failed: " . $e->getMessage() . "</p>";
            exit;
        }
        
        // Test 4: Check available methods
        $required_methods = [
            'classifyData',
            'getDataClassification',
            'requestBulkExportApproval',
            'approveExportRequest',
            'rejectExportRequest',
            'checkExportApproval',
            'getUserExportRequests',
            'getAllExportRequests',
            'logDownloadActivity',
            'addWatermarkToText',
            'addWatermarkToCSV',
            'canUserExportData',
            'getDLPStats',
            'getUserNotifications'
        ];
        
        echo "<h3>Test 4: Available DLP Methods</h3>";
        echo "<table>";
        echo "<tr><th>Method Name</th><th>Status</th></tr>";
        
        $methods_found = 0;
        foreach ($required_methods as $method) {
            $exists = method_exists($dlp, $method);
            if ($exists) {
                $methods_found++;
                echo "<tr class='green-bg'><td>$method()</td><td class='success'>✅ Available</td></tr>";
            } else {
                echo "<tr class='red-bg'><td>$method()</td><td class='fail'>❌ NOT FOUND</td></tr>";
            }
        }
        echo "</table>";
        
        // Summary
        $total_methods = count($required_methods);
        $percentage = round(($methods_found / $total_methods) * 100);
        
        echo "<div class='summary'>";
        echo "<h3>📊 Summary</h3>";
        echo "<ul>";
        echo "<li><strong>Tables:</strong> " . ($all_tables_exist ? "✅ All present" : "❌ Some missing") . "</li>";
        echo "<li><strong>Class:</strong> ✅ Loaded</li>";
        echo "<li><strong>Methods:</strong> $methods_found / $total_methods ($percentage%)</li>";
        echo "</ul>";
        
        if ($all_tables_exist && $methods_found === $total_methods) {
            echo "<p class='success' style='font-size: 18px;'>🎉 DLP SYSTEM FULLY IMPLEMENTED!</p>";
            echo "<p>✅ All components are ready for use</p>";
        } else {
            echo "<p class='fail' style='font-size: 18px;'>⚠️ IMPLEMENTATION INCOMPLETE!</p>";
            if (!$all_tables_exist) {
                echo "<p>❌ Run install_dlp.php to create missing tables</p>";
            }
            if ($methods_found < $total_methods) {
                echo "<p>❌ Review dlp_system.php for missing methods</p>";
            }
        }
        echo "</div>";
        
        // Next steps
        echo "<h3>🚀 Next Steps</h3>";
        echo "<ul>";
        echo "<li><a href='export_requests.php'>View Export Requests Interface</a> (User)</li>";
        echo "<li><a href='dlp_management.php'>View DLP Management</a> (Admin)</li>";
        echo "<li><a href='ADVANCED_INPUT_VALIDATION_DOCUMENTATION.md'>Read Input Validation Docs</a></li>";
        echo "<li><a href='DLP_QUICK_START_GUIDE.md'>Read DLP Quick Start Guide</a></li>";
        echo "</ul>";
        ?>
    </div>
</body>
</html>
```
