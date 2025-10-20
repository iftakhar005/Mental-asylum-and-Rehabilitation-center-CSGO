<?php
/**
 * DATABASE CONNECTION FILE
 * Supports multi-device access configuration
 */

// Load configuration
require_once __DIR__ . '/config.php';

// Check if MySQLi extension is available
if (!extension_loaded('mysqli')) {
    die('MySQLi extension is not loaded. Please enable it in your PHP configuration (php.ini).');
}

// Use configuration constants
$host = DB_HOST;
$user = DB_USER;
$pass = DB_PASS;
$db = DB_NAME;

try {
    $conn = new mysqli($host, $user, $pass, $db);
    if ($conn->connect_error) {
        // Log error for debugging
        error_log('Database connection failed: ' . $conn->connect_error);
        
        // User-friendly error message
        if ($_SERVER['SERVER_NAME'] === 'localhost') {
            die('Connection failed: ' . $conn->connect_error . '<br><br>Please check:<br>1. XAMPP is running<br>2. MySQL service is started<br>3. Database "asylum_db" exists');
        } else {
            die('Database connection error. Please contact administrator.');
        }
    }
    
    // Set charset to prevent character set confusion attacks
    $conn->set_charset("utf8mb4");
    
    // Set timezone for database
    $conn->query("SET time_zone = '+08:00'"); // Adjust based on your timezone
    
} catch (Exception $e) {
    error_log('Database exception: ' . $e->getMessage());
    die('Database connection error: ' . ($host === 'localhost' ? $e->getMessage() : 'Please contact administrator'));
}
?> 