<?php
/**
 * Check User Ratul - Comprehensive Diagnostic
 */

require_once 'db.php';
require_once 'security_manager.php';

$securityManager = new MentalHealthSecurityManager($conn);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Check User: Ratul</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 8px; max-width: 1000px; margin: 0 auto; }
        h2 { color: #333; border-bottom: 2px solid #4CAF50; padding-bottom: 10px; }
        .box { padding: 15px; margin: 15px 0; border-radius: 5px; border-left: 4px solid; }
        .success { background: #d4edda; border-color: #28a745; color: #155724; }
        .error { background: #f8d7da; border-color: #dc3545; color: #721c24; }
        .warning { background: #fff3cd; border-color: #ffc107; color: #856404; }
        .info { background: #d1ecf1; border-color: #17a2b8; color: #0c5460; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; font-weight: 600; }
        .btn { background: #4CAF50; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; margin: 5px; }
        .btn:hover { background: #45a049; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
    </style>
</head>
<body>
    <div class="container">
        <h2>🔍 Checking User: Ratul</h2>
        
        <?php
        // Check in STAFF table
        echo "<h3>1️⃣ Checking STAFF Table</h3>";
        $staff_check = $conn->query("SELECT * FROM staff WHERE full_name LIKE '%Ratul%' OR email LIKE '%ratul%'");
        
        if ($staff_check && $staff_check->num_rows > 0) {
            while ($user = $staff_check->fetch_assoc()) {
                echo "<div class='box success'>";
                echo "<h4>✅ Found in STAFF table</h4>";
                echo "<table>";
                echo "<tr><th>Field</th><th>Value</th><th>Status</th></tr>";
                echo "<tr><td>Staff ID</td><td>" . htmlspecialchars($user['staff_id']) . "</td><td>✅</td></tr>";
                echo "<tr><td>Full Name</td><td>" . htmlspecialchars($user['full_name']) . "</td><td>✅</td></tr>";
                echo "<tr><td>Email</td><td>" . htmlspecialchars($user['email']) . "</td><td>✅</td></tr>";
                echo "<tr><td>Role</td><td><strong>" . htmlspecialchars($user['role']) . "</strong></td><td>✅</td></tr>";
                echo "<tr><td>Status</td><td>" . htmlspecialchars($user['status']) . "</td><td>" . ($user['status'] == 'Active' ? "✅" : "❌") . "</td></tr>";
                echo "<tr><td>User ID</td><td>" . htmlspecialchars($user['user_id']) . "</td><td>" . (!empty($user['user_id']) ? "✅" : "⚠️") . "</td></tr>";
                
                // Password check
                $has_password = !empty($user['password_hash']);
                echo "<tr><td>Has Password</td><td>" . ($has_password ? "Yes" : "NO") . "</td><td>" . ($has_password ? "✅" : "❌ PROBLEM!") . "</td></tr>";
                
                if ($has_password) {
                    $hash_format = substr($user['password_hash'], 0, 4);
                    $is_valid_hash = ($hash_format === '$2y$');
                    echo "<tr><td>Password Format</td><td>Starts with: $hash_format</td><td>" . ($is_valid_hash ? "✅" : "❌ INVALID!") . "</td></tr>";
                }
                
                // 2FA check
                $twofa_enabled = isset($user['two_factor_enabled']) ? $user['two_factor_enabled'] : 'Column not found';
                echo "<tr><td>2FA Enabled</td><td>" . htmlspecialchars($twofa_enabled) . "</td><td>" . ($twofa_enabled == 1 ? "🔐 YES (requires OTP)" : ($twofa_enabled === 0 ? "✅ NO" : "⚠️ Unknown")) . "</td></tr>";
                
                echo "</table>";
                
                // Show problems
                if (!$has_password) {
                    echo "<div class='box error'>";
                    echo "<strong>❌ CRITICAL: No password set!</strong><br>";
                    echo "Run this SQL:<br>";
                    echo "<code>UPDATE staff SET password_hash = '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' WHERE staff_id = '" . $user['staff_id'] . "';</code><br>";
                    echo "Then login with password: <code>test123</code>";
                    echo "</div>";
                }
                
                if ($twofa_enabled == 1) {
                    echo "<div class='box warning'>";
                    echo "<strong>⚠️ 2FA IS ENABLED!</strong><br>";
                    echo "To disable 2FA, run this SQL:<br>";
                    echo "<code>UPDATE staff SET two_factor_enabled = 0 WHERE staff_id = '" . $user['staff_id'] . "';</code>";
                    echo "</div>";
                }
                
                echo "</div>";
            }
        } else {
            echo "<div class='box error'>";
            echo "<h4>❌ NOT FOUND in staff table</h4>";
            echo "<p>The user 'Ratul' doesn't exist in the staff table. Did the insert fail?</p>";
            echo "</div>";
        }
        
        // Check in USERS table
        echo "<h3>2️⃣ Checking USERS Table</h3>";
        $users_check = $conn->query("SELECT * FROM users WHERE username LIKE '%Ratul%' OR email LIKE '%ratul%'");
        
        if ($users_check && $users_check->num_rows > 0) {
            while ($user = $users_check->fetch_assoc()) {
                echo "<div class='box warning'>";
                echo "<h4>⚠️ Also found in USERS table (duplicate?)</h4>";
                echo "<table>";
                echo "<tr><th>Field</th><th>Value</th></tr>";
                echo "<tr><td>ID</td><td>" . htmlspecialchars($user['id']) . "</td></tr>";
                echo "<tr><td>Username</td><td>" . htmlspecialchars($user['username']) . "</td></tr>";
                echo "<tr><td>Email</td><td>" . htmlspecialchars($user['email']) . "</td></tr>";
                echo "<tr><td>Role</td><td>" . htmlspecialchars($user['role']) . "</td></tr>";
                
                $twofa_enabled = isset($user['two_factor_enabled']) ? $user['two_factor_enabled'] : 'N/A';
                echo "<tr><td>2FA Enabled</td><td>" . htmlspecialchars($twofa_enabled) . "</td></tr>";
                echo "</table>";
                
                echo "<p><strong>Note:</strong> Nurses should only be in STAFF table, not USERS table.</p>";
                echo "<p>Consider deleting: <code>DELETE FROM users WHERE id = " . $user['id'] . ";</code></p>";
                echo "</div>";
            }
        } else {
            echo "<div class='box success'>";
            echo "<p>✅ Not in users table (correct for nurse)</p>";
            echo "</div>";
        }
        
        // Test login simulation
        echo "<h3>3️⃣ Login Test</h3>";
        echo "<form method='POST'>";
        echo "<div class='box info'>";
        echo "<p><strong>Test login with email and password:</strong></p>";
        echo "<label>Email: <input type='email' name='test_email' placeholder='ratul@example.com' style='width: 300px; padding: 8px;'></label><br><br>";
        echo "<label>Password: <input type='password' name='test_password' placeholder='Enter password' style='width: 300px; padding: 8px;'></label><br><br>";
        echo "<button type='submit' name='test_login' class='btn'>🔍 Test Login</button>";
        echo "</div>";
        echo "</form>";
        
        if (isset($_POST['test_login'])) {
            $test_email = $_POST['test_email'];
            $test_password = $_POST['test_password'];
            
            echo "<div class='box info'>";
            echo "<h4>Testing login for: " . htmlspecialchars($test_email) . "</h4>";
            
            $login_check = $securityManager->secureSelect(
                "SELECT staff_id, full_name, email, password_hash, two_factor_enabled FROM staff WHERE email = ?",
                [$test_email],
                's'
            );
            
            if ($login_check && $login_check->num_rows > 0) {
                $user = $login_check->fetch_assoc();
                echo "<p>✅ User found in database</p>";
                
                if (!empty($user['password_hash'])) {
                    $verify = password_verify($test_password, $user['password_hash']);
                    
                    if ($verify) {
                        echo "<p class='box success'>✅ ✅ ✅ PASSWORD IS CORRECT!</p>";
                        echo "<p>Login should work. If it still fails:</p>";
                        echo "<ul>";
                        echo "<li>Check browser console for JavaScript errors</li>";
                        echo "<li>Clear cookies and try again</li>";
                        echo "<li>Check if you're banned (too many failed attempts)</li>";
                        echo "</ul>";
                        
                        if ($user['two_factor_enabled'] == 1) {
                            echo "<p class='box warning'>⚠️ But 2FA is enabled - you'll need OTP!</p>";
                        }
                    } else {
                        echo "<p class='box error'>❌ PASSWORD IS WRONG!</p>";
                        echo "<p>The password you entered doesn't match the hash in database.</p>";
                    }
                } else {
                    echo "<p class='box error'>❌ No password hash in database!</p>";
                }
            } else {
                echo "<p class='box error'>❌ User not found with that email</p>";
            }
            echo "</div>";
        }
        
        // Quick fixes
        echo "<h3>🔧 Quick Fixes</h3>";
        echo "<div class='box info'>";
        echo "<h4>Set password to 'test123' for Ratul:</h4>";
        echo "<code>UPDATE staff SET password_hash = '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' WHERE full_name LIKE '%Ratul%';</code>";
        echo "<br><br>";
        echo "<h4>Disable 2FA for Ratul:</h4>";
        echo "<code>UPDATE staff SET two_factor_enabled = 0 WHERE full_name LIKE '%Ratul%';</code>";
        echo "<br><br>";
        echo "<h4>Delete Ratul and start over:</h4>";
        echo "<code>DELETE FROM staff WHERE full_name LIKE '%Ratul%';<br>";
        echo "DELETE FROM users WHERE username LIKE '%Ratul%';</code>";
        echo "</div>";
        
        ?>
        
        <h3>🚀 Actions</h3>
        <a href="reset_receptionist_password.php" class="btn">Reset Password Tool</a>
        <a href="debug_login.php" class="btn" style="background: #17a2b8;">Login Debug</a>
        <a href="index.php" class="btn" style="background: #6c757d;">Try Login</a>
    </div>
</body>
</html>
```
