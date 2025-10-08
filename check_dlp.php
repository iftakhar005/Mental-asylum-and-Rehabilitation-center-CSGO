<?php
/**
 * DLP System Health Check
 * Run this to verify your DLP system installation and status
 */

// Start output buffering for cleaner display
ob_start();

// Include database connection
try {
    require_once 'db.php';
    $db_connected = true;
} catch (Exception $e) {
    $db_connected = false;
    $db_error = $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DLP System Health Check</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 1000px; margin: 0 auto; padding: 20px; background: #f8f9fa; }
        .header { background: linear-gradient(135deg, #3498db, #2980b9); color: white; padding: 30px; text-align: center; border-radius: 10px; margin-bottom: 30px; }
        .check-item { background: white; margin: 15px 0; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .check-title { font-size: 1.2em; font-weight: bold; margin-bottom: 10px; }
        .status { padding: 5px 15px; border-radius: 20px; font-weight: bold; font-size: 0.9em; }
        .status-ok { background: #d4edda; color: #155724; }
        .status-warning { background: #fff3cd; color: #856404; }
        .status-error { background: #f8d7da; color: #721c24; }
        .details { margin-top: 10px; font-size: 0.9em; color: #666; }
        .code { background: #f8f9fa; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
        .action-buttons { margin-top: 20px; text-align: center; }
        .btn { display: inline-block; padding: 12px 24px; margin: 5px; background: #3498db; color: white; text-decoration: none; border-radius: 6px; font-weight: bold; }
        .btn:hover { background: #2980b9; }
        .btn-success { background: #27ae60; }
        .btn-warning { background: #f39c12; }
        .btn-danger { background: #e74c3c; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <h1>🛡️ DLP System Health Check</h1>
        <p>Verifying Data Loss Prevention system installation and status</p>
    </div>

    <!-- Database Connection Check -->
    <div class="check-item">
        <div class="check-title">📊 Database Connection</div>
        <?php if ($db_connected): ?>
            <span class="status status-ok">✅ CONNECTED</span>
            <div class="details">Successfully connected to the database</div>
        <?php else: ?>
            <span class="status status-error">❌ CONNECTION FAILED</span>
            <div class="details">Error: <?= htmlspecialchars($db_error) ?></div>
        <?php endif; ?>
    </div>

    <!-- File System Check -->
    <div class="check-item">
        <div class="check-title">📁 Required Files</div>
        <?php
        $required_files = [
            'dlp_system.php' => 'Core DLP system class',
            'dlp_management.php' => 'DLP management interface',
            'secure_export.php' => 'Secure data export handler',
            'retention_enforcer.php' => 'Automated retention enforcement',
            'dlp_database.sql' => 'Database schema file'
        ];
        
        $missing_files = [];
        foreach ($required_files as $file => $description) {
            if (!file_exists($file)) {
                $missing_files[] = $file;
            }
        }
        ?>
        
        <?php if (empty($missing_files)): ?>
            <span class="status status-ok">✅ ALL FILES PRESENT</span>
            <table>
                <tr><th>File</th><th>Description</th><th>Status</th></tr>
                <?php foreach ($required_files as $file => $description): ?>
                <tr>
                    <td><code><?= $file ?></code></td>
                    <td><?= $description ?></td>
                    <td><span class="status status-ok">✅ Found</span></td>
                </tr>
                <?php endforeach; ?>
            </table>
        <?php else: ?>
            <span class="status status-error">❌ MISSING FILES</span>
            <div class="details">Missing files: <?= implode(', ', $missing_files) ?></div>
        <?php endif; ?>
    </div>

    <!-- Database Tables Check -->
    <div class="check-item">
        <div class="check-title">🗄️ Database Tables</div>
        <?php if ($db_connected): ?>
            <?php
            $required_tables = [
                'dlp_config' => 'DLP configuration settings',
                'data_classification' => 'Data sensitivity classifications',
                'export_approval_requests' => 'Export approval workflow',
                'download_activity' => 'Download monitoring logs',
                'retention_policies' => 'Data retention rules',
                'data_access_audit' => 'Access audit trail'
            ];
            
            $existing_tables = [];
            $missing_tables = [];
            
            $result = $conn->query("SHOW TABLES");
            $all_tables = [];
            while ($row = $result->fetch_array()) {
                $all_tables[] = $row[0];
            }
            
            foreach ($required_tables as $table => $description) {
                if (in_array($table, $all_tables)) {
                    $existing_tables[$table] = $description;
                } else {
                    $missing_tables[$table] = $description;
                }
            }
            ?>
            
            <?php if (empty($missing_tables)): ?>
                <span class="status status-ok">✅ ALL TABLES EXIST</span>
                <table>
                    <tr><th>Table</th><th>Description</th><th>Records</th></tr>
                    <?php foreach ($existing_tables as $table => $description): ?>
                    <?php
                    $count_result = $conn->query("SELECT COUNT(*) as count FROM `{$table}`");
                    $count = $count_result->fetch_assoc()['count'];
                    ?>
                    <tr>
                        <td><code><?= $table ?></code></td>
                        <td><?= $description ?></td>
                        <td><?= $count ?> records</td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            <?php else: ?>
                <span class="status status-warning">⚠️ TABLES MISSING</span>
                <div class="details">
                    <strong>Missing tables:</strong>
                    <ul>
                        <?php foreach ($missing_tables as $table => $description): ?>
                        <li><code><?= $table ?></code> - <?= $description ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <strong>Solution:</strong> Run the installation script to create missing tables.
                </div>
            <?php endif; ?>
        <?php else: ?>
            <span class="status status-error">❌ CANNOT CHECK</span>
            <div class="details">Database connection required to check tables</div>
        <?php endif; ?>
    </div>

    <!-- Directory Permissions Check -->
    <div class="check-item">
        <div class="check-title">📂 Directory Permissions</div>
        <?php
        $required_dirs = ['logs', 'archives', 'exports'];
        $dir_issues = [];
        
        foreach ($required_dirs as $dir) {
            if (!is_dir($dir)) {
                if (mkdir($dir, 0755, true)) {
                    $dir_issues[] = "Created directory: {$dir}";
                } else {
                    $dir_issues[] = "Failed to create directory: {$dir}";
                }
            } elseif (!is_writable($dir)) {
                $dir_issues[] = "Directory not writable: {$dir}";
            }
        }
        ?>
        
        <?php if (empty($dir_issues)): ?>
            <span class="status status-ok">✅ ALL DIRECTORIES OK</span>
            <div class="details">
                Required directories exist and are writable:
                <?php foreach ($required_dirs as $dir): ?>
                <br>📁 <code><?= $dir ?>/</code> - <?= is_dir($dir) ? 'Exists' : 'Missing' ?>, <?= is_writable($dir) ? 'Writable' : 'Not writable' ?>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <span class="status status-warning">⚠️ DIRECTORY ISSUES</span>
            <div class="details">
                <?php foreach ($dir_issues as $issue): ?>
                <div><?= $issue ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- PHP Requirements Check -->
    <div class="check-item">
        <div class="check-title">🐘 PHP Requirements</div>
        <?php
        $php_version = phpversion();
        $required_version = '7.4.0';
        $version_ok = version_compare($php_version, $required_version, '>=');
        
        $required_extensions = ['mysqli', 'json', 'openssl'];
        $missing_extensions = [];
        
        foreach ($required_extensions as $ext) {
            if (!extension_loaded($ext)) {
                $missing_extensions[] = $ext;
            }
        }
        ?>
        
        <?php if ($version_ok && empty($missing_extensions)): ?>
            <span class="status status-ok">✅ REQUIREMENTS MET</span>
            <div class="details">
                <strong>PHP Version:</strong> <?= $php_version ?> (Required: <?= $required_version ?>+)<br>
                <strong>Extensions:</strong> All required extensions are loaded
            </div>
        <?php else: ?>
            <span class="status status-error">❌ REQUIREMENTS NOT MET</span>
            <div class="details">
                <?php if (!$version_ok): ?>
                <div><strong>PHP Version:</strong> <?= $php_version ?> (Required: <?= $required_version ?>+)</div>
                <?php endif; ?>
                <?php if (!empty($missing_extensions)): ?>
                <div><strong>Missing Extensions:</strong> <?= implode(', ', $missing_extensions) ?></div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- System Status Summary -->
    <div class="check-item">
        <div class="check-title">📋 System Status Summary</div>
        <?php
        $issues = 0;
        if (!$db_connected) $issues++;
        if (!empty($missing_files)) $issues++;
        if (!empty($missing_tables)) $issues++;
        if (!empty($dir_issues)) $issues++;
        if (!$version_ok || !empty($missing_extensions)) $issues++;
        ?>
        
        <?php if ($issues === 0): ?>
            <span class="status status-ok">✅ SYSTEM READY</span>
            <div class="details">All checks passed! Your DLP system is ready to use.</div>
        <?php elseif ($issues <= 2): ?>
            <span class="status status-warning">⚠️ MINOR ISSUES</span>
            <div class="details">System has minor issues that should be resolved for optimal operation.</div>
        <?php else: ?>
            <span class="status status-error">❌ MAJOR ISSUES</span>
            <div class="details">System has significant issues that must be resolved before use.</div>
        <?php endif; ?>
    </div>

    <!-- Action Buttons -->
    <div class="action-buttons">
        <?php if (!empty($missing_tables) || $issues > 0): ?>
            <a href="install_dlp.php" class="btn btn-success">🚀 Install/Fix DLP System</a>
        <?php endif; ?>
        
        <?php if ($db_connected && empty($missing_tables)): ?>
            <a href="dlp_management.php" class="btn">🛡️ DLP Management</a>
            <a href="secure_export.php" class="btn">📥 Secure Export</a>
        <?php endif; ?>
        
        <a href="?refresh=1" class="btn btn-warning">🔄 Refresh Check</a>
    </div>

    <div style="margin-top: 30px; text-align: center; color: #666; font-size: 0.9em;">
        Last checked: <?= date('Y-m-d H:i:s') ?> | 
        <a href="https://github.com/iftakhar005/Mental-asylum-and-Rehabilitation-center-CSGO" target="_blank" style="color: #3498db;">View Project</a>
    </div>
</body>
</html>