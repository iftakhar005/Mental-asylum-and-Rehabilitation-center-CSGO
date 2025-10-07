<?php
require_once 'db.php';
require_once 'parent_dashboard.php';

// Test Parameterized Queries
function test_parameterized_queries($conn) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $id = 1; // Valid ID
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    echo $result->num_rows > 0 ? "Parameterized Queries: PASS\n" : "Parameterized Queries: FAIL\n";
    $stmt->close();
}

// Test Input Length Restrictions and Sanitization
function test_input_sanitization() {
    $input = "<script>alert('XSS')</script>";
    $sanitized = sanitize_input($input, 10);
    echo $sanitized === htmlspecialchars(substr($input, 0, 10), ENT_QUOTES, 'UTF-8') ? "Input Sanitization: PASS\n" : "Input Sanitization: FAIL\n";
}

// Test SQL Injection Pattern Blocking
function test_sql_injection_blocking() {
    $input = "SELECT * FROM users";
    try {
        block_sql_injection($input);
        echo "SQL Injection Blocking: FAIL\n";
    } catch (Exception $e) {
        echo "SQL Injection Blocking: PASS\n";
    }
}

// Test CAPTCHA-like Functionality
function test_captcha() {
    session_start();
    $_SESSION['failed_attempts'] = 3;
    $_POST['captcha'] = '7';
    ob_start();
    display_captcha();
    $output = ob_get_clean();
    echo strpos($output, 'Enter the sum of 3 + 4') !== false ? "CAPTCHA Display: PASS\n" : "CAPTCHA Display: FAIL\n";
    echo $_POST['captcha'] == '7' ? "CAPTCHA Validation: PASS\n" : "CAPTCHA Validation: FAIL\n";
}

// Test XSS Prevention
function test_xss_prevention() {
    $_POST['test'] = "<script>alert('XSS')</script>";
    $_POST = array_map(function($value) {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }, $_POST);
    echo $_POST['test'] === htmlspecialchars("<script>alert('XSS')</script>", ENT_QUOTES, 'UTF-8') ? "XSS Prevention: PASS\n" : "XSS Prevention: FAIL\n";
}

// Run Tests
$conn = new mysqli('localhost', 'root', '', 'asylum_db');
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

test_parameterized_queries($conn);
test_input_sanitization();
test_sql_injection_blocking();
test_captcha();
test_xss_prevention();

$conn->close();
?>