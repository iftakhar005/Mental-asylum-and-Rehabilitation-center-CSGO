<?php
/**
 * Login Debug Tool
 * Shows exactly what happens during login attempt
 */

require_once 'db.php';
require_once 'security_manager.php';

$securityManager = new MentalHealthSecurityManager($conn);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Login Debug Tool</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 8px; max-width: 800px; margin: 0 auto; }
        h2 { color: #333; border-bottom: 2px solid #4CAF50; padding-bottom: 10px; }
        .test-result { padding: 15px; margin: 10px 0; border-radius: 5px; }
        .pass { background: #d4edda; border-left: 4px solid #28a745; }
        .fail { background: #f8d7da; border-left: 4px solid #dc3545; }
        .info { background: #d1ecf1; border-left: 4px solid #0c5460; }
        pre { background: #f4f4f4; padding: 10px; border-radius: 4px; overflow-x: auto; }
        .btn { background: #4CAF50; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; margin: 5px; }
        .btn:hover { background: #45a049; }
        input { padding: 8px; margin: 5px; border: 1px solid #ddd; border-radius: 4px; width: 300px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>🔍 Login Debug Tool</h2>
        
        <div class="info test-result">
            <strong>📋 Instructions:</strong><br>
            1. Enter the email and password you're trying to use<br>
            2. Click "Test Login"<br>
            3. See exactly what's happening
        </div>
        
        <form method="POST">
            <div>
                <label><strong>Email:</strong></label><br>
                <input type="email" name="test_email" value="iftakhar@receptionist.gmail.com" required>
            </div>
            <div>
                <label><strong>Password:</strong></label><br>
                <input type="password" name="test_password" placeholder="Enter password" required>
            </div>
            <button type="submit" name="test_login" class="btn">🔍 Test Login</button>
        </form>
        
        <?php
        if (isset($_POST['test_login'])) {
            $test_email = $_POST['test_email'];
            $test_password = $_POST['test_password'];
            
            echo "<hr>";
            echo "<h3>📊 Test Results</h3>";
            
            // Test 1: Check in staff table
            echo "<div class='test-result'>";
            echo "<h4>Test 1: Looking in STAFF table</h4>";
            $staff_query = $securityManager->secureSelect(
                "SELECT staff_id, full_name, email, role, password_hash, user_id FROM staff WHERE email = ?",
                [$test_email],
                's'
            );
            
            if ($staff_query && $staff_query->num_rows > 0) {
                $staff_user = $staff_query->fetch_assoc();
                echo "<p class='pass'>✅ User FOUND in staff table</p>";
                echo "<strong>Details:</strong><br>";
                echo "• Staff ID: " . htmlspecialchars($staff_user['staff_id']) . "<br>";
                echo "• Name: " . htmlspecialchars($staff_user['full_name']) . "<br>";
                echo "• Email: " . htmlspecialchars($staff_user['email']) . "<br>";
                echo "• Role: " . htmlspecialchars($staff_user['role']) . "<br>";
                echo "• User ID: " . htmlspecialchars($staff_user['user_id']) . "<br>";
                echo "• Has Password Hash: " . (!empty($staff_user['password_hash']) ? "✅ Yes" : "❌ No") . "<br>";
                
                if (!empty($staff_user['password_hash'])) {
                    echo "<br><strong>Password Hash (first 50 chars):</strong><br>";
                    echo "<code>" . htmlspecialchars(substr($staff_user['password_hash'], 0, 50)) . "...</code><br>";
                    
                    // Test password verification
                    echo "<br><strong>Testing Password Verification:</strong><br>";
                    $verify_result = password_verify($test_password, $staff_user['password_hash']);
                    
                    if ($verify_result) {
                        echo "<p class='pass'>✅ PASSWORD CORRECT! password_verify() returned TRUE</p>";
                        echo "<p>Login should work. If it doesn't, check:</p>";
                        echo "<ul>";
                        echo "<li>Is client banned? Run check below</li>";
                        echo "<li>Is CAPTCHA required? Check failed attempts</li>";
                        echo "<li>Any JavaScript errors in browser console?</li>";
                        echo "</ul>";
                    } else {
                        echo "<p class='fail'>❌ PASSWORD INCORRECT! password_verify() returned FALSE</p>";
                        echo "<p><strong>This means:</strong></p>";
                        echo "<ul>";
                        echo "<li>The password you entered doesn't match the hash in database</li>";
                        echo "<li>You need to reset the password again</li>";
                        echo "<li>Make sure you're using the correct password</li>";
                        echo "</ul>";
                        
                        // Show what the hash looks like
                        echo "<br><strong>Current hash format:</strong><br>";
                        if (substr($staff_user['password_hash'], 0, 4) === '$2y$') {
                            echo "<p class='pass'>✅ Hash starts with \$2y\$ (bcrypt format - CORRECT)</p>";
                        } else {
                            echo "<p class='fail'>❌ Hash doesn't start with \$2y\$ - this is NOT a valid password_hash() output!</p>";
                            echo "<p>Current hash: <code>" . htmlspecialchars($staff_user['password_hash']) . "</code></p>";
                        }
                    }
                }
                
                // Show expected redirect
                echo "<br><strong>Expected Redirect:</strong> ";
                switch($staff_user['role']) {
                    case 'receptionist':
                        echo "receptionist_dashboard.php";
                        break;
                    case 'doctor':
                        echo "doctor_dashboard.php";
                        break;
                    default:
                        echo "staff_dashboard.php";
                }
            } else {
                echo "<p class='fail'>❌ User NOT FOUND in staff table</p>";
            }
            echo "</div>";
            
            // Test 2: Check in users table
            echo "<div class='test-result'>";
            echo "<h4>Test 2: Looking in USERS table</h4>";
            $users_query = $securityManager->secureSelect(
                "SELECT id, username, email, role, password_hash FROM users WHERE email = ?",
                [$test_email],
                's'
            );
            
            if ($users_query && $users_query->num_rows > 0) {
                $users_user = $users_query->fetch_assoc();
                echo "<p class='fail'>⚠️ User ALSO FOUND in users table (DUPLICATE!)</p>";
                echo "<strong>Details:</strong><br>";
                echo "• ID: " . htmlspecialchars($users_user['id']) . "<br>";
                echo "• Username: " . htmlspecialchars($users_user['username']) . "<br>";
                echo "• Email: " . htmlspecialchars($users_user['email']) . "<br>";
                echo "• Role: " . htmlspecialchars($users_user['role']) . "<br>";
                
                echo "<br><strong>⚠️ PROBLEM:</strong> Receptionists should ONLY be in staff table, not users table!<br>";
                echo "<strong>Solution:</strong> Delete from users table:<br>";
                echo "<code>DELETE FROM users WHERE email = '" . htmlspecialchars($test_email) . "' AND role = 'receptionist';</code>";
            } else {
                echo "<p class='pass'>✅ User NOT in users table (correct for receptionist)</p>";
            }
            echo "</div>";
            
            // Test 3: Check for bans/captcha
            echo "<div class='test-result'>";
            echo "<h4>Test 3: Security Checks</h4>";
            
            $is_banned = $securityManager->isClientBanned();
            if ($is_banned) {
                $ban_time = $securityManager->getBanTimeRemaining();
                echo "<p class='fail'>❌ CLIENT IS BANNED! Remaining: {$ban_time} seconds</p>";
                echo "<strong>Solution:</strong> Wait for ban to expire or clear it manually<br>";
            } else {
                echo "<p class='pass'>✅ Client not banned</p>";
            }
            
            $needs_captcha = $securityManager->needsCaptcha();
            if ($needs_captcha) {
                echo "<p class='fail'>⚠️ CAPTCHA IS REQUIRED due to failed login attempts</p>";
                echo "<strong>Solution:</strong> Clear failed attempts or solve CAPTCHA<br>";
            } else {
                echo "<p class='pass'>✅ No CAPTCHA required</p>";
            }
            echo "</div>";
            
            // Test 4: Check dashboard file exists
            echo "<div class='test-result'>";
            echo "<h4>Test 4: Dashboard File Check</h4>";
            if (file_exists('receptionist_dashboard.php')) {
                echo "<p class='pass'>✅ receptionist_dashboard.php EXISTS</p>";
            } else {
                echo "<p class='fail'>❌ receptionist_dashboard.php DOES NOT EXIST!</p>";
            }
            echo "</div>";
            
            // Summary
            echo "<div class='test-result info'>";
            echo "<h4>📋 Summary & Next Steps</h4>";
            if (isset($verify_result) && $verify_result) {
                echo "<p><strong>✅ Password is correct!</strong> Login should work.</p>";
                echo "<p>If login still fails, check:</p>";
                echo "<ol>";
                echo "<li>Browser console for JavaScript errors</li>";
                echo "<li>Network tab to see where redirect goes</li>";
                echo "<li>Session errors in PHP error log</li>";
                echo "</ol>";
            } else {
                echo "<p><strong>❌ Password verification failed!</strong></p>";
                echo "<p>Go to: <a href='reset_receptionist_password.php'>Reset Password Tool</a></p>";
                echo "<p>Or use this SQL to set password to 'test123':</p>";
                echo "<pre>UPDATE staff SET password_hash = '$2y$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' WHERE email = '{$test_email}';</pre>";
            }
            echo "</div>";
        }
        ?>
        
        <hr>
        <h3>🛠️ Quick Actions</h3>
        <a href="reset_receptionist_password.php" class="btn">Reset Password</a>
        <a href="index.php" class="btn" style="background: #2196F3;">Try Login</a>
        <a href="check_receptionist_login.php" class="btn" style="background: #FF9800;">Full Diagnostic</a>
    </div>
</body>
</html>
```
