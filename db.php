<?php
// Check if MySQLi extension is available
if (!extension_loaded('mysqli')) {
    die('MySQLi extension is not loaded. Please enable it in your PHP configuration (php.ini).');
}

$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'asylum_db';

try {
    $conn = new mysqli($host, $user, $pass, $db);
    if ($conn->connect_error) {
        die('Connection failed: ' . $conn->connect_error);
    }
    
    // Set charset to prevent character set confusion attacks
    $conn->set_charset("utf8");
    
} catch (Exception $e) {
    die('Database connection error: ' . $e->getMessage());
}
?> 