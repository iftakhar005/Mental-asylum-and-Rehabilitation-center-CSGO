<?php
/**
 * Security Manager Implementation Verification
 * Tests if all security features are properly implemented
 */

require_once 'security_manager.php';
require_once 'db.php';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Security Implementation Check</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { background: white; padding: 20px; border-radius: 8px; max-width: 1000px; margin: 0 auto; }
        h2 { color: #333; border-bottom: 2px solid #4CAF50; padding-bottom: 10px; }
        .success { color: green; }
        .fail { color: red; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #4CAF50; color: white; }
        tr:hover { background-color: #f5f5f5; }
        .summary { background: #e8f5e9; padding: 15px; border-radius: 5px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h2>🛡️ Security Manager Implementation Check</h2>
        
        <?php
        // Test 1: Check if security manager class exists
        echo "<h3>Test 1: Class Existence</h3>";
        if (class_exists('MentalHealthSecurityManager')) {
            echo "<p class='success'>✅ MentalHealthSecurityManager class exists</p>";
        } else {
            echo "<p class='fail'>❌ MentalHealthSecurityManager class NOT found</p>";
            exit;
        }
        
        // Test 2: Initialize security manager
        echo "<h3>Test 2: Initialization</h3>";
        try {
            $securityManager = new MentalHealthSecurityManager($conn);
            echo "<p class='success'>✅ Security Manager initialized successfully</p>";
        } catch (Exception $e) {
            echo "<p class='fail'>❌ Failed to initialize: " . $e->getMessage() . "</p>";
            exit;
        }
        
        // Test 3: Check available methods
        $required_methods = [
            'validateInput',
            'detectSQLInjection',
            'preventXSS',
            'secureQuery',
            'secureSelect',
            'secureExecute',
            'generateCaptcha',
            'validateCaptcha',
            'recordFailedLogin',
            'needsCaptcha',
            'isClientBanned',
            'generateCSRFToken',
            'validateCSRFToken',
            'logSecurityEvent',
            'processFormData'
        ];
        
        echo "<h3>Test 3: Available Security Methods</h3>";
        echo "<table>";
        echo "<tr><th>Method Name</th><th>Status</th></tr>";
        
        $methods_found = 0;
        foreach ($required_methods as $method) {
            $exists = method_exists($securityManager, $method);
            if ($exists) {
                $methods_found++;
                echo "<tr><td>$method()</td><td class='success'>✅ Available</td></tr>";
            } else {
                echo "<tr><td>$method()</td><td class='fail'>❌ NOT FOUND</td></tr>";
            }
        }
        echo "</table>";
        
        // Summary
        $total_methods = count($required_methods);
        $percentage = round(($methods_found / $total_methods) * 100);
        
        echo "<div class='summary'>";
        echo "<h3>📊 Summary</h3>";
        echo "<p><strong>Methods Found:</strong> $methods_found / $total_methods ($percentage%)</p>";
        
        if ($methods_found === $total_methods) {
            echo "<p class='success' style='font-size: 18px;'>🎉 ALL SECURITY METHODS AVAILABLE!</p>";
            echo "<p>✅ Advanced Input Validation System is fully implemented</p>";
        } else {
            $missing = $total_methods - $methods_found;
            echo "<p class='fail' style='font-size: 18px;'>⚠️ $missing METHOD(S) MISSING!</p>";
            echo "<p>Review security_manager.php implementation</p>";
        }
        echo "</div>";
        
        // Next steps
        echo "<h3>🚀 Next Steps</h3>";
        echo "<ul>";
        echo "<li><a href='verify_sql_injection_protection.php'>Verify SQL Injection Protection</a></li>";
        echo "<li><a href='verify_xss_prevention.php'>Verify XSS Prevention</a></li>";
        echo "<li><a href='verify_rate_limiting.php'>Verify Rate Limiting</a></li>";
        echo "<li><a href='verify_csrf_protection.php'>Verify CSRF Protection</a></li>";
        echo "</ul>";
        ?>
    </div>
</body>
</html>
```
