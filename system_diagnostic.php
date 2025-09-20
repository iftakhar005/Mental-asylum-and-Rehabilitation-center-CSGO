<?php
/**
 * System Diagnostic and Setup Tool
 * Checks system requirements and helps fix common issues
 */

echo "<!DOCTYPE html>
<html>
<head>
    <title>Mental Health Center - System Diagnostic</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        .warning { color: #ffc107; font-weight: bold; }
        .info { color: #17a2b8; font-weight: bold; }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .fix-instructions { background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 10px 0; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 3px; overflow-x: auto; }
        .button { background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 5px; }
        .button:hover { background: #0056b3; }
    </style>
</head>
<body>";

echo "<div class='container'>";
echo "<h1>🏥 Mental Health Center - System Diagnostic</h1>";

// Check PHP Version
echo "<div class='section'>";
echo "<h2>1. PHP Version Check</h2>";
$php_version = phpversion();
if (version_compare($php_version, '7.0', '>=')) {
    echo "<span class='success'>✅ PHP Version: $php_version (Good)</span>";
} else {
    echo "<span class='error'>❌ PHP Version: $php_version (Too old, need 7.0+)</span>";
}
echo "</div>";

// Check Extensions
echo "<div class='section'>";
echo "<h2>2. Required Extensions</h2>";

$required_extensions = ['mysqli', 'session', 'json'];
$missing_extensions = [];

foreach ($required_extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "<span class='success'>✅ $ext - Loaded</span><br>";
    } else {
        echo "<span class='error'>❌ $ext - Not loaded</span><br>";
        $missing_extensions[] = $ext;
    }
}

if (!empty($missing_extensions)) {
    echo "<div class='fix-instructions'>";
    echo "<h3>🔧 How to Fix Missing Extensions:</h3>";
    echo "<p><strong>For XAMPP Users:</strong></p>";
    echo "<ol>";
    echo "<li>Open your XAMPP installation folder (usually C:\\xampp\\)</li>";
    echo "<li>Edit the file: <code>php\\php.ini</code></li>";
    echo "<li>Find and uncomment (remove the semicolon) from these lines:</li>";
    echo "<pre>";
    foreach ($missing_extensions as $ext) {
        if ($ext === 'mysqli') {
            echo "extension=mysqli\n";
        }
    }
    echo "</pre>";
    echo "<li>Save the file and restart Apache in XAMPP Control Panel</li>";
    echo "<li>Refresh this page to check again</li>";
    echo "</ol>";
    echo "</div>";
}
echo "</div>";

// Check Database Connection
echo "<div class='section'>";
echo "<h2>3. Database Connection Test</h2>";

if (extension_loaded('mysqli')) {
    try {
        $conn = new mysqli('localhost', 'root', '', 'asylum_db');
        if ($conn->connect_error) {
            echo "<span class='warning'>⚠️ Database Connection: Failed - " . $conn->connect_error . "</span><br>";
            echo "<div class='fix-instructions'>";
            echo "<h3>🔧 Database Setup Instructions:</h3>";
            echo "<ol>";
            echo "<li>Start MySQL service in XAMPP Control Panel</li>";
            echo "<li>Open phpMyAdmin (http://localhost/phpmyadmin)</li>";
            echo "<li>Create a database named 'asylum_db'</li>";
            echo "<li>Import your database schema if you have one</li>";
            echo "</ol>";
            echo "</div>";
        } else {
            echo "<span class='success'>✅ Database Connection: Successful</span><br>";
            
            // Test basic table existence
            $tables = ['users', 'staff', 'patients'];
            foreach ($tables as $table) {
                $result = $conn->query("SHOW TABLES LIKE '$table'");
                if ($result && $result->num_rows > 0) {
                    echo "<span class='success'>✅ Table '$table': Exists</span><br>";
                } else {
                    echo "<span class='warning'>⚠️ Table '$table': Not found</span><br>";
                }
            }
        }
        if (isset($conn)) $conn->close();
    } catch (Exception $e) {
        echo "<span class='error'>❌ Database Error: " . $e->getMessage() . "</span>";
    }
} else {
    echo "<span class='error'>❌ Cannot test database - MySQLi extension not loaded</span>";
}
echo "</div>";

// Check File Permissions
echo "<div class='section'>";
echo "<h2>4. File System Check</h2>";

$files_to_check = [
    'db.php' => 'Database configuration',
    'security_manager.php' => 'Security library',
    'index.php' => 'Login page'
];

foreach ($files_to_check as $file => $description) {
    if (file_exists($file)) {
        if (is_readable($file)) {
            echo "<span class='success'>✅ $file ($description): Readable</span><br>";
        } else {
            echo "<span class='error'>❌ $file ($description): Not readable</span><br>";
        }
    } else {
        echo "<span class='error'>❌ $file ($description): File not found</span><br>";
    }
}

// Check if logs directory exists
if (!is_dir('logs')) {
    echo "<span class='warning'>⚠️ logs/ directory: Not found (will be created automatically)</span><br>";
} else {
    echo "<span class='success'>✅ logs/ directory: Exists</span><br>";
}

echo "</div>";

// Security Features Test
echo "<div class='section'>";
echo "<h2>5. Security Features Test</h2>";

if (extension_loaded('mysqli') && file_exists('security_manager.php')) {
    try {
        include_once 'security_manager.php';
        echo "<span class='success'>✅ Security Manager: Loaded successfully</span><br>";
        
        // Test basic functionality
        if (class_exists('MentalHealthSecurityManager')) {
            echo "<span class='success'>✅ Security Class: Available</span><br>";
        } else {
            echo "<span class='error'>❌ Security Class: Not found</span><br>";
        }
        
    } catch (Exception $e) {
        echo "<span class='error'>❌ Security Manager: Error - " . $e->getMessage() . "</span><br>";
    }
} else {
    echo "<span class='warning'>⚠️ Security Features: Cannot test (dependencies missing)</span><br>";
}
echo "</div>";

// System Information
echo "<div class='section'>";
echo "<h2>6. System Information</h2>";
echo "<strong>PHP Version:</strong> " . phpversion() . "<br>";
echo "<strong>Server:</strong> " . $_SERVER['SERVER_SOFTWARE'] . "<br>";
echo "<strong>Document Root:</strong> " . $_SERVER['DOCUMENT_ROOT'] . "<br>";
echo "<strong>Current Directory:</strong> " . __DIR__ . "<br>";
echo "<strong>Session Support:</strong> " . (extension_loaded('session') ? 'Yes' : 'No') . "<br>";
echo "<strong>JSON Support:</strong> " . (extension_loaded('json') ? 'Yes' : 'No') . "<br>";
echo "</div>";

// Quick Actions
echo "<div class='section'>";
echo "<h2>7. Quick Actions</h2>";
echo "<a href='index.php' class='button'>🔐 Go to Login Page</a>";
echo "<a href='security_test.php' class='button'>🧪 Run Security Tests</a>";
echo "<a href='?phpinfo=1' class='button'>📋 View PHP Info</a>";
echo "</div>";

// Show phpinfo if requested
if (isset($_GET['phpinfo'])) {
    echo "<div class='section'>";
    echo "<h2>PHP Information</h2>";
    echo "<div style='max-height: 400px; overflow-y: auto; border: 1px solid #ddd; padding: 10px;'>";
    phpinfo();
    echo "</div>";
    echo "</div>";
}

echo "</div>";
echo "</body></html>";
?>