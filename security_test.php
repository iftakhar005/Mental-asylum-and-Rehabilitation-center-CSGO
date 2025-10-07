<?php
/**
 * Security Features Test Suite for Mental Health Center
 * Tests all implemented security features to ensure they work correctly
 */

require_once 'db.php';
require_once 'security_manager.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Security Test Suite - Mental Health Center</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .test-section { border: 1px solid #ddd; margin: 10px 0; padding: 15px; }
        .pass { color: green; font-weight: bold; }
        .fail { color: red; font-weight: bold; }
        .info { color: blue; }
        .test-item { margin: 5px 0; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 3px; }
    </style>
</head>
<body>";

echo "<h1>Security Features Test Suite</h1>";
echo "<p>Testing all implemented security features...</p>";

// Initialize test results
$total_tests = 0;
$passed_tests = 0;

function runTest($test_name, $test_function) {
    global $total_tests, $passed_tests;
    $total_tests++;
    
    echo "<div class='test-item'>";
    echo "<strong>Test: $test_name</strong><br>";
    
    try {
        $result = $test_function();
        if ($result) {
            echo "<span class='pass'>✓ PASSED</span>";
            $passed_tests++;
        } else {
            echo "<span class='fail'>✗ FAILED</span>";
        }
    } catch (Exception $e) {
        echo "<span class='fail'>✗ ERROR: " . htmlspecialchars($e->getMessage()) . "</span>";
    }
    
    echo "</div>";
}

// Test 1: Parameterized Queries
echo "<div class='test-section'>";
echo "<h2>1. Parameterized Queries Testing</h2>";

runTest("Basic SELECT with parameters", function() {
    global $securityManager;
    $result = $securityManager->secureSelect(
        "SELECT COUNT(*) as count FROM users WHERE role = ?",
        ['admin'],
        's'
    );
    return $result !== false;
});

runTest("INSERT with parameters", function() {
    global $securityManager;
    $test_data = [
        'test_user_' . rand(1000, 9999),
        password_hash('testpass', PASSWORD_DEFAULT),
        'test@example.com',
        'test_role'
    ];
    
    $result = $securityManager->secureExecute(
        "INSERT INTO users (username, password_hash, email, role) VALUES (?, ?, ?, ?)",
        $test_data,
        'ssss'
    );
    
    // Clean up
    if ($result['success']) {
        $securityManager->secureExecute(
            "DELETE FROM users WHERE username = ?",
            [$test_data[0]],
            's'
        );
    }
    
    return $result['success'];
});

runTest("SQL Injection Prevention", function() {
    global $securityManager;
    try {
        // This should fail due to SQL injection detection
        $result = $securityManager->secureSelect(
            "SELECT * FROM users WHERE username = 'admin' OR 1=1--",
            []
        );
        return false; // Should not reach here
    } catch (Exception $e) {
        // Expected to throw exception
        return strpos($e->getMessage(), 'dangerous query') !== false;
    }
});

echo "</div>";

// Test 2: Input Validation and Sanitization
echo "<div class='test-section'>";
echo "<h2>2. Input Validation and Sanitization Testing</h2>";

runTest("Email validation - valid email", function() {
    global $securityManager;
    $result = $securityManager->validateInput('test@example.com', ['type' => 'email']);
    return $result === 'test@example.com';
});

runTest("Email validation - invalid email", function() {
    global $securityManager;
    try {
        $securityManager->validateInput('invalid-email', ['type' => 'email']);
        return false; // Should throw exception
    } catch (Exception $e) {
        return strpos($e->getMessage(), 'Invalid email') !== false;
    }
});

runTest("Name validation - valid name", function() {
    global $securityManager;
    $result = $securityManager->validateInput('John Doe', ['type' => 'name']);
    return $result === 'John Doe';
});

runTest("Name validation - invalid characters", function() {
    global $securityManager;
    try {
        $securityManager->validateInput('John<script>alert(1)</script>', ['type' => 'name']);
        return false; // Should throw exception
    } catch (Exception $e) {
        return strpos($e->getMessage(), 'Invalid name') !== false;
    }
});

runTest("Length restriction", function() {
    global $securityManager;
    try {
        $securityManager->validateInput(str_repeat('a', 300), ['max_length' => 100]);
        return false; // Should throw exception
    } catch (Exception $e) {
        return strpos($e->getMessage(), 'maximum length') !== false;
    }
});

runTest("Phone validation", function() {
    global $securityManager;
    $result = $securityManager->validateInput('123-456-7890', ['type' => 'phone']);
    return !empty($result);
});

echo "</div>";

// Test 3: SQL Injection Pattern Detection
echo "<div class='test-section'>";
echo "<h2>3. SQL Injection Pattern Detection</h2>";

$injection_tests = [
    "' OR 1=1--" => "Boolean-based injection",
    "'; DROP TABLE users;--" => "Stacked queries",
    "' UNION SELECT * FROM users--" => "Union-based injection",
    "' AND SLEEP(5)--" => "Time-based injection",
    "' OR 'x'='x" => "Boolean bypass",
    "admin'/**/OR/**/1=1--" => "Comment-based evasion",
    "1' AND SUBSTRING(@@version,1,1)='5'--" => "Version fingerprinting",
    "' OR '1'='1" => "Simple boolean injection"
];

foreach ($injection_tests as $injection => $test_name) {
    runTest("Detect $test_name", function() use ($injection) {
        global $securityManager;
        return $securityManager->detectSQLInjection($injection);
    });
}

echo "</div>";

// Test 4: CAPTCHA System
echo "<div class='test-section'>";
echo "<h2>4. CAPTCHA System Testing</h2>";

runTest("Generate CAPTCHA", function() {
    global $securityManager;
    $captcha = $securityManager->generateCaptcha();
    return isset($captcha['question']) && isset($captcha['answer']);
});

runTest("Validate correct CAPTCHA", function() {
    global $securityManager;
    $captcha = $securityManager->generateCaptcha();
    return $securityManager->validateCaptcha($captcha['answer']);
});

runTest("Reject incorrect CAPTCHA", function() {
    global $securityManager;
    $securityManager->generateCaptcha();
    return !$securityManager->validateCaptcha('999999'); // Wrong answer
});

runTest("Failed login tracking", function() {
    global $securityManager;
    
    // Record multiple failed attempts
    for ($i = 0; $i < 4; $i++) {
        $securityManager->recordFailedLogin('test_user');
    }
    
    $needs_captcha = $securityManager->needsCaptcha('test_user');
    
    // Clean up
    $securityManager->clearFailedAttempts('test_user');
    
    return $needs_captcha;
});

echo "</div>";

// Test 5: XSS Prevention
echo "<div class='test-section'>";
echo "<h2>5. XSS Prevention Testing</h2>";

$xss_tests = [
    '<script>alert("XSS")</script>' => '&lt;script&gt;alert("XSS")&lt;/script&gt;',
    '<img src="x" onerror="alert(1)">' => '&lt;img src="x" onerror="alert(1)"&gt;',
    'javascript:alert(1)' => 'alert(1)',
    '<a href="javascript:alert(1)">Click</a>' => '&lt;a href="alert(1)"&gt;Click&lt;/a&gt;',
    '"onmouseover="alert(1)"' => '"onmouseover&#61;"alert(1)"'
];

foreach ($xss_tests as $input => $expected_pattern) {
    runTest("XSS Prevention: " . substr($input, 0, 30) . "...", function() use ($input, $expected_pattern) {
        global $securityManager;
        $result = $securityManager->preventXSS($input);
        return strpos($result, '<script') === false && strpos($result, 'javascript:') === false;
    });
}

runTest("JavaScript string escaping", function() {
    global $securityManager;
    $input = 'Hello "World" with \\ backslash';
    $result = $securityManager->preventXSS($input, 'javascript');
    return strpos($result, '\\"') !== false && strpos($result, '\\\\') !== false;
});

runTest("HTML attribute escaping", function() {
    global $securityManager;
    $input = 'value with "quotes" and newlines\n';
    $result = $securityManager->preventXSS($input, 'attribute');
    return strpos($result, '&quot;') !== false && strpos($result, '&#10;') !== false;
});

echo "</div>";

// Test 6: Form Data Processing
echo "<div class='test-section'>";
echo "<h2>6. Form Data Processing Testing</h2>";

runTest("Valid form data processing", function() {
    global $securityManager;
    
    $form_data = [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'phone' => '123-456-7890'
    ];
    
    $rules = [
        'name' => ['type' => 'name', 'max_length' => 100],
        'email' => ['type' => 'email', 'max_length' => 255],
        'phone' => ['type' => 'phone', 'max_length' => 20]
    ];
    
    $result = $securityManager->processFormData($form_data, $rules);
    return count($result) === 3;
});

runTest("Invalid form data rejection", function() {
    global $securityManager;
    
    $form_data = [
        'email' => 'invalid-email',
        'name' => str_repeat('a', 200) // Too long
    ];
    
    $rules = [
        'email' => ['type' => 'email'],
        'name' => ['type' => 'name', 'max_length' => 100]
    ];
    
    try {
        $securityManager->processFormData($form_data, $rules);
        return false; // Should throw exception
    } catch (Exception $e) {
        return strpos($e->getMessage(), 'Validation errors') !== false;
    }
});

echo "</div>";

// Test 7: Security Logging
echo "<div class='test-section'>";
echo "<h2>7. Security Logging Testing</h2>";

runTest("Security event logging", function() {
    global $securityManager;
    
    $log_file = __DIR__ . '/logs/security.log';
    $initial_size = file_exists($log_file) ? filesize($log_file) : 0;
    
    $securityManager->logSecurityEvent('TEST_EVENT', ['test' => 'data']);
    
    $new_size = file_exists($log_file) ? filesize($log_file) : 0;
    return $new_size > $initial_size;
});

echo "</div>";

// Test 8: CSRF Protection
echo "<div class='test-section'>";
echo "<h2>8. CSRF Protection Testing</h2>";

runTest("CSRF token generation", function() {
    global $securityManager;
    $token = $securityManager->generateCSRFToken();
    return !empty($token) && strlen($token) === 64;
});

runTest("CSRF token validation", function() {
    global $securityManager;
    $token = $securityManager->generateCSRFToken();
    return $securityManager->validateCSRFToken($token);
});

runTest("Invalid CSRF token rejection", function() {
    global $securityManager;
    return !$securityManager->validateCSRFToken('invalid_token');
});

echo "</div>";

// Test Summary
echo "<div class='test-section'>";
echo "<h2>Test Summary</h2>";
echo "<p class='info'>Total Tests: $total_tests</p>";
echo "<p class='pass'>Passed: $passed_tests</p>";
echo "<p class='fail'>Failed: " . ($total_tests - $passed_tests) . "</p>";

$success_rate = ($passed_tests / $total_tests) * 100;
echo "<p><strong>Success Rate: " . number_format($success_rate, 1) . "%</strong></p>";

if ($success_rate >= 90) {
    echo "<p class='pass'><strong>✓ EXCELLENT: Security implementation is working correctly!</strong></p>";
} elseif ($success_rate >= 75) {
    echo "<p class='info'><strong>⚠ GOOD: Most security features working, minor issues detected.</strong></p>";
} else {
    echo "<p class='fail'><strong>✗ ATTENTION: Multiple security issues detected. Review implementation.</strong></p>";
}

echo "</div>";

// Usage Examples
echo "<div class='test-section'>";
echo "<h2>Usage Examples</h2>";
echo "<p>Here are examples of how to use the security features in your application:</p>";

echo "<h3>1. Secure Database Queries</h3>";
echo "<pre>";
echo htmlspecialchars('
// SELECT query
$result = $securityManager->secureSelect(
    "SELECT * FROM users WHERE email = ? AND role = ?",
    [$email, $role],
    "ss"
);

// INSERT query  
$result = $securityManager->secureExecute(
    "INSERT INTO patients (name, email, dob) VALUES (?, ?, ?)",
    [$name, $email, $dob],
    "sss"
);
');
echo "</pre>";

echo "<h3>2. Input Validation</h3>";
echo "<pre>";
echo htmlspecialchars('
// Validate email
$clean_email = $securityManager->validateInput($email, [
    "type" => "email",
    "max_length" => 255,
    "required" => true
]);

// Validate name
$clean_name = $securityManager->validateInput($name, [
    "type" => "name", 
    "max_length" => 100,
    "required" => true
]);
');
echo "</pre>";

echo "<h3>3. XSS Prevention</h3>";
echo "<pre>";
echo htmlspecialchars('
// Safe HTML output
echo $securityManager->preventXSS($user_input);

// Safe attribute output
echo "<input value=\"" . $securityManager->preventXSS($value, "attribute") . "\">";

// Safe JavaScript output
echo "var data = \"" . $securityManager->preventXSS($data, "javascript") . "\";";
');
echo "</pre>";

echo "<h3>4. CAPTCHA Implementation</h3>";
echo "<pre>";
echo htmlspecialchars('
// Check if CAPTCHA is needed
if ($securityManager->needsCaptcha()) {
    $captcha = $securityManager->generateCaptcha();
    echo $captcha["question"];
}

// Validate CAPTCHA answer
if ($securityManager->validateCaptcha($_POST["captcha_answer"])) {
    // CAPTCHA correct, proceed
} else {
    // CAPTCHA incorrect
    $securityManager->recordFailedLogin();
}
');
echo "</pre>";

echo "</div>";

echo "</body></html>";
?>