<?php
/**
 * DLP System Installation Script
 * Run this once to set up the Data Loss Prevention system
 */

require_once 'db.php';

function installDLPSystem() {
    global $conn;
    
    echo "<h2>Installing Data Loss Prevention System...</h2>\n";
    
    try {
        // Read and execute the SQL file
        $sql_file = 'dlp_database.sql';
        if (!file_exists($sql_file)) {
            throw new Exception("SQL file not found: {$sql_file}");
        }
        
        $sql_content = file_get_contents($sql_file);
        $queries = explode(';', $sql_content);
        
        $executed = 0;
        foreach ($queries as $query) {
            $query = trim($query);
            if (!empty($query)) {
                if ($conn->query($query)) {
                    $executed++;
                } else {
                    echo "<div class='error'>Error executing query: " . $conn->error . "</div>\n";
                }
            }
        }
        
        echo "<div class='success'>Successfully executed {$executed} SQL queries</div>\n";
        
        // Create necessary directories
        $directories = ['logs', 'archives', 'exports'];
        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
                echo "<div class='success'>Created directory: {$dir}</div>\n";
            }
        }
        
        // Set up initial data classifications for existing tables
        setupInitialClassifications();
        
        echo "<div class='success'><strong>DLP System installed successfully!</strong></div>\n";
        echo "<div class='info'>
            <h3>Next Steps:</h3>
            <ol>
                <li>Access the DLP Management interface at: <a href='dlp_management.php'>dlp_management.php</a></li>
                <li>Configure additional data classifications as needed</li>
                <li>Set up the retention policy cron job: <code>0 2 * * * /usr/bin/php " . __DIR__ . "/retention_enforcer.php</code></li>
                <li>Test the secure export functionality at: <a href='secure_export.php'>secure_export.php</a></li>
            </ol>
        </div>\n";
        
    } catch (Exception $e) {
        echo "<div class='error'>Installation failed: " . $e->getMessage() . "</div>\n";
        return false;
    }
    
    return true;
}

function setupInitialClassifications() {
    global $conn;
    
    echo "<h3>Setting up initial data classifications...</h3>\n";
    
    // Additional classifications beyond what's in the SQL file
    $additional_classifications = [
        ['table_name' => 'appointments', 'column_name' => 'patient_info', 'classification_level' => 'confidential', 'data_category' => 'medical_info', 'retention_days' => 2555],
        ['table_name' => 'treatments', 'column_name' => 'treatment_notes', 'classification_level' => 'restricted', 'data_category' => 'medical_records', 'retention_days' => 3650],
        ['table_name' => 'medications', 'column_name' => 'prescription_data', 'classification_level' => 'restricted', 'data_category' => 'medical_records', 'retention_days' => 3650],
    ];
    
    foreach ($additional_classifications as $class) {
        $requires_approval = in_array($class['classification_level'], ['confidential', 'restricted']) ? 1 : 0;
        $watermark_required = in_array($class['classification_level'], ['confidential', 'restricted']) ? 1 : 0;
        
        $stmt = $conn->prepare("
            INSERT INTO data_classification 
            (table_name, column_name, classification_level, data_category, retention_days, requires_approval, watermark_required) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            classification_level = VALUES(classification_level),
            data_category = VALUES(data_category),
            retention_days = VALUES(retention_days),
            requires_approval = VALUES(requires_approval),
            watermark_required = VALUES(watermark_required)
        ");
        
        $stmt->bind_param("ssssiii", 
            $class['table_name'], 
            $class['column_name'], 
            $class['classification_level'], 
            $class['data_category'], 
            $class['retention_days'], 
            $requires_approval, 
            $watermark_required
        );
        
        if ($stmt->execute()) {
            echo "<div class='success'>Classified {$class['table_name']}.{$class['column_name']} as {$class['classification_level']}</div>\n";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DLP System Installation</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        .success { background: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin: 10px 0; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; margin: 10px 0; border: 1px solid #f5c6cb; }
        .info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 4px; margin: 10px 0; border: 1px solid #bee5eb; }
        .warning { background: #fff3cd; color: #856404; padding: 10px; border-radius: 4px; margin: 10px 0; border: 1px solid #ffeaa7; }
        code { background: #f8f9fa; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
        h1, h2, h3 { color: #2c3e50; }
    </style>
</head>
<body>
    <h1>Data Loss Prevention System Installation</h1>
    
    <div class="warning">
        <strong>Warning:</strong> This will create new database tables and modify your system. 
        Make sure you have a backup of your database before proceeding.
    </div>
    
    <?php
    if (isset($_GET['install']) && $_GET['install'] === 'confirm') {
        installDLPSystem();
    } else {
        ?>
        <div class="info">
            <h3>What will be installed:</h3>
            <ul>
                <li><strong>Database Tables:</strong> DLP configuration, data classification, export approvals, download monitoring, retention policies, and audit trails</li>
                <li><strong>File Directories:</strong> logs/, archives/, exports/ for storing DLP-related files</li>
                <li><strong>Initial Data:</strong> Default configurations and data classifications for existing tables</li>
                <li><strong>Security Features:</strong> Comprehensive data protection and monitoring capabilities</li>
            </ul>
            
            <h3>System Requirements:</h3>
            <ul>
                <li>PHP 7.4 or higher</li>
                <li>MySQL 5.7 or higher</li>
                <li>Write permissions for creating directories</li>
                <li>Cron job capability for automated retention enforcement</li>
            </ul>
            
            <p><strong>Ready to install?</strong></p>
            <p><a href="?install=confirm" style="background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;">Install DLP System</a></p>
        </div>
        <?php
    }
    ?>
    
    <div class="info">
        <h3>Post-Installation Configuration:</h3>
        <ol>
            <li><strong>Admin Access:</strong> Only admin and chief-staff roles can access DLP management</li>
            <li><strong>Data Classification:</strong> Review and adjust data classifications in the DLP management interface</li>
            <li><strong>Export Policies:</strong> Configure bulk export approval thresholds</li>
            <li><strong>Retention Policies:</strong> Set up automated data retention and cleanup</li>
            <li><strong>Monitoring:</strong> Configure suspicious activity detection thresholds</li>
            <li><strong>Cron Job:</strong> Set up automated retention enforcement</li>
        </ol>
    </div>
</body>
</html>