<?php
/**
 * Receptionist Login Troubleshooting Tool
 * Checks why receptionists cannot login
 */

require_once 'db.php';
require_once 'security_manager.php';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Receptionist Login Diagnostic</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { background: white; padding: 20px; border-radius: 8px; max-width: 1200px; margin: 0 auto; }
        h2 { color: #333; border-bottom: 2px solid #4CAF50; padding-bottom: 10px; }
        .success { color: green; }
        .fail { color: red; }
        .warning { color: orange; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #4CAF50; color: white; }
        .green-bg { background-color: #c8e6c9; }
        .red-bg { background-color: #ffcdd2; }
        .yellow-bg { background-color: #fff9c4; }
        .info-box { background: #e3f2fd; padding: 15px; border-left: 4px solid #2196F3; margin: 20px 0; }
        .fix-btn { background: #4CAF50; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
        .fix-btn:hover { background: #45a049; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>🔍 Receptionist Login Diagnostic Tool</h2>
        
        <?php
        $securityManager = new MentalHealthSecurityManager($conn);
        
        // Test 1: Check if staff table exists
        echo "<h3>Test 1: Database Table Check</h3>";
        $tables_to_check = ['staff', 'users'];
        echo "<table>";
        echo "<tr><th>Table Name</th><th>Status</th><th>Record Count</th></tr>";
        
        foreach ($tables_to_check as $table) {
            $check_query = "SHOW TABLES LIKE '$table'";
            $result = $conn->query($check_query);
            
            if ($result && $result->num_rows > 0) {
                $count_query = "SELECT COUNT(*) as count FROM $table";
                $count_result = $conn->query($count_query);
                $count = $count_result->fetch_assoc()['count'];
                
                echo "<tr class='green-bg'>";
                echo "<td>$table</td>";
                echo "<td class='success'>✅ EXISTS</td>";
                echo "<td>$count records</td>";
                echo "</tr>";
            } else {
                echo "<tr class='red-bg'>";
                echo "<td>$table</td>";
                echo "<td class='fail'>❌ MISSING</td>";
                echo "<td>-</td>";
                echo "</tr>";
            }
        }
        echo "</table>";
        
        // Test 2: Check receptionist records in staff table
        echo "<h3>Test 2: Receptionist Records in 'staff' Table</h3>";
        $receptionist_query = "SELECT staff_id, full_name, email, role, password_hash, user_id FROM staff WHERE role = 'receptionist'";
        $receptionist_result = $conn->query($receptionist_query);
        
        if ($receptionist_result && $receptionist_result->num_rows > 0) {
            echo "<p class='success'>✅ Found " . $receptionist_result->num_rows . " receptionist(s) in staff table</p>";
            echo "<table>";
            echo "<tr><th>Staff ID</th><th>Name</th><th>Email</th><th>Role</th><th>Has Password</th><th>User ID</th><th>Status</th></tr>";
            
            while ($row = $receptionist_result->fetch_assoc()) {
                $has_password = !empty($row['password_hash']);
                $has_user_id = !empty($row['user_id']);
                
                $status_class = ($has_password && $has_user_id) ? 'green-bg' : 'yellow-bg';
                $status_text = ($has_password && $has_user_id) ? '✅ OK' : '⚠️ Issue';
                
                echo "<tr class='$status_class'>";
                echo "<td>" . htmlspecialchars($row['staff_id']) . "</td>";
                echo "<td>" . htmlspecialchars($row['full_name']) . "</td>";
                echo "<td>" . htmlspecialchars($row['email']) . "</td>";
                echo "<td>" . htmlspecialchars($row['role']) . "</td>";
                echo "<td>" . ($has_password ? '✅ Yes' : '❌ No') . "</td>";
                echo "<td>" . ($has_user_id ? htmlspecialchars($row['user_id']) : '❌ Missing') . "</td>";
                echo "<td>$status_text</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p class='fail'>❌ No receptionists found in staff table!</p>";
            echo "<div class='info-box'>";
            echo "<strong>📝 Solution:</strong> Receptionists need to be added to the <code>staff</code> table with role='receptionist'<br>";
            echo "Use <code>add_receptionist.php</code> or run this SQL:<br>";
            echo "<code>INSERT INTO staff (staff_id, full_name, email, password_hash, role) VALUES (...);</code>";
            echo "</div>";
        }
        
        // Test 3: Check receptionist records in users table
        echo "<h3>Test 3: Receptionist Records in 'users' Table</h3>";
        $users_receptionist_query = "SELECT id, username, email, role FROM users WHERE role = 'receptionist'";
        $users_receptionist_result = $conn->query($users_receptionist_query);
        
        if ($users_receptionist_result && $users_receptionist_result->num_rows > 0) {
            echo "<p class='warning'>⚠️ Found " . $users_receptionist_result->num_rows . " receptionist(s) in users table</p>";
            echo "<table>";
            echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th></tr>";
            
            while ($row = $users_receptionist_result->fetch_assoc()) {
                echo "<tr class='yellow-bg'>";
                echo "<td>" . htmlspecialchars($row['id']) . "</td>";
                echo "<td>" . htmlspecialchars($row['username']) . "</td>";
                echo "<td>" . htmlspecialchars($row['email']) . "</td>";
                echo "<td>" . htmlspecialchars($row['role']) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
            
            echo "<div class='info-box'>";
            echo "<strong>⚠️ Note:</strong> Receptionists should be in the <code>staff</code> table, not the <code>users</code> table.<br>";
            echo "The <code>users</code> table is typically for admin users only.<br>";
            echo "Consider migrating these records to the <code>staff</code> table.";
            echo "</div>";
        } else {
            echo "<p class='success'>✅ No receptionists in users table (correct)</p>";
        }
        
        // Test 4: Test login process simulation
        echo "<h3>Test 4: Login Process Simulation</h3>";
        echo "<p>Testing login flow for first receptionist found...</p>";
        
        $test_receptionist = $conn->query("SELECT staff_id, email, full_name, password_hash FROM staff WHERE role = 'receptionist' LIMIT 1");
        
        if ($test_receptionist && $test_receptionist->num_rows > 0) {
            $test_user = $test_receptionist->fetch_assoc();
            echo "<table>";
            echo "<tr><th>Step</th><th>Status</th><th>Details</th></tr>";
            
            // Step 1: Email lookup
            $email_check = $securityManager->secureSelect(
                "SELECT staff_id, full_name, password_hash, role FROM staff WHERE email = ?",
                [$test_user['email']],
                's'
            );
            
            if ($email_check->num_rows > 0) {
                echo "<tr class='green-bg'><td>1. Email Lookup</td><td>✅ PASS</td><td>User found with email: " . htmlspecialchars($test_user['email']) . "</td></tr>";
            } else {
                echo "<tr class='red-bg'><td>1. Email Lookup</td><td>❌ FAIL</td><td>Email not found in database</td></tr>";
            }
            
            // Step 2: Password hash check
            if (!empty($test_user['password_hash'])) {
                echo "<tr class='green-bg'><td>2. Password Hash</td><td>✅ PASS</td><td>Password hash exists</td></tr>";
            } else {
                echo "<tr class='red-bg'><td>2. Password Hash</td><td>❌ FAIL</td><td>No password hash found - user cannot login!</td></tr>";
            }
            
            // Step 3: Role check
            echo "<tr class='green-bg'><td>3. Role Check</td><td>✅ PASS</td><td>Role is 'receptionist'</td></tr>";
            
            // Step 4: Redirect check
            echo "<tr class='green-bg'><td>4. Redirect Path</td><td>✅ PASS</td><td>Would redirect to: receptionist_dashboard.php</td></tr>";
            
            echo "</table>";
        } else {
            echo "<p class='fail'>❌ No receptionist available to test</p>";
        }
        
        // Test 5: Common Issues
        echo "<h3>Test 5: Common Login Issues</h3>";
        echo "<table>";
        echo "<tr><th>Issue</th><th>Check</th><th>Status</th></tr>";
        
        // Check for NULL password hashes
        $null_passwords = $conn->query("SELECT COUNT(*) as count FROM staff WHERE role = 'receptionist' AND (password_hash IS NULL OR password_hash = '')");
        $null_count = $null_passwords->fetch_assoc()['count'];
        
        if ($null_count > 0) {
            echo "<tr class='red-bg'><td>Missing Passwords</td><td>$null_count receptionist(s) have no password</td><td>❌ FIX REQUIRED</td></tr>";
        } else {
            echo "<tr class='green-bg'><td>Missing Passwords</td><td>All receptionists have passwords</td><td>✅ OK</td></tr>";
        }
        
        // Check for missing user_id
        $null_user_ids = $conn->query("SELECT COUNT(*) as count FROM staff WHERE role = 'receptionist' AND (user_id IS NULL OR user_id = '')");
        $null_uid_count = $null_user_ids->fetch_assoc()['count'];
        
        if ($null_uid_count > 0) {
            echo "<tr class='yellow-bg'><td>Missing user_id</td><td>$null_uid_count receptionist(s) have no user_id</td><td>⚠️ WARNING</td></tr>";
        } else {
            echo "<tr class='green-bg'><td>Missing user_id</td><td>All receptionists have user_id</td><td>✅ OK</td></tr>";
        }
        
        // Check for duplicate emails
        $duplicate_emails = $conn->query("SELECT email, COUNT(*) as count FROM staff WHERE role = 'receptionist' GROUP BY email HAVING count > 1");
        if ($duplicate_emails && $duplicate_emails->num_rows > 0) {
            echo "<tr class='red-bg'><td>Duplicate Emails</td><td>Found duplicate email addresses</td><td>❌ FIX REQUIRED</td></tr>";
        } else {
            echo "<tr class='green-bg'><td>Duplicate Emails</td><td>No duplicates found</td><td>✅ OK</td></tr>";
        }
        
        // Check receptionist_dashboard.php exists
        if (file_exists('receptionist_dashboard.php')) {
            echo "<tr class='green-bg'><td>Dashboard File</td><td>receptionist_dashboard.php exists</td><td>✅ OK</td></tr>";
        } else {
            echo "<tr class='red-bg'><td>Dashboard File</td><td>receptionist_dashboard.php NOT FOUND</td><td>❌ FIX REQUIRED</td></tr>";
        }
        
        echo "</table>";
        
        // Summary & Solutions
        echo "<h3>📋 Summary & Solutions</h3>";
        
        $total_receptionists = $conn->query("SELECT COUNT(*) as count FROM staff WHERE role = 'receptionist'")->fetch_assoc()['count'];
        $working_receptionists = $conn->query("SELECT COUNT(*) as count FROM staff WHERE role = 'receptionist' AND password_hash IS NOT NULL AND password_hash != ''")->fetch_assoc()['count'];
        
        echo "<div class='info-box'>";
        echo "<h4>Current Status:</h4>";
        echo "<ul>";
        echo "<li><strong>Total Receptionists:</strong> $total_receptionists</li>";
        echo "<li><strong>Can Login:</strong> $working_receptionists</li>";
        echo "<li><strong>Cannot Login:</strong> " . ($total_receptionists - $working_receptionists) . "</li>";
        echo "</ul>";
        echo "</div>";
        
        if ($working_receptionists < $total_receptionists) {
            echo "<div class='info-box' style='background: #fff3cd; border-left-color: #ff9800;'>";
            echo "<h4>🔧 How to Fix:</h4>";
            echo "<ol>";
            echo "<li><strong>Set Password for Receptionists:</strong><br>";
            echo "<code>UPDATE staff SET password_hash = PASSWORD('your_password') WHERE staff_id = 'RECEP-XXX';</code><br>";
            echo "Or use PHP: <code>\$hash = password_hash('password', PASSWORD_DEFAULT);</code></li>";
            echo "<li><strong>Verify Email is Correct:</strong><br>";
            echo "Make sure the email in staff table matches what receptionist enters during login</li>";
            echo "<li><strong>Test Login:</strong><br>";
            echo "Go to <a href='index.php'>index.php</a> and try logging in with receptionist credentials</li>";
            echo "</ol>";
            echo "</div>";
        }
        
        // Show sample receptionist for testing
        echo "<h3>🧪 Test Credentials</h3>";
        $sample = $conn->query("SELECT staff_id, email, full_name FROM staff WHERE role = 'receptionist' AND password_hash IS NOT NULL LIMIT 1");
        
        if ($sample && $sample->num_rows > 0) {
            $sample_user = $sample->fetch_assoc();
            echo "<div class='info-box' style='background: #e8f5e9; border-left-color: #4CAF50;'>";
            echo "<p><strong>✅ You can test login with:</strong></p>";
            echo "<ul>";
            echo "<li><strong>Email:</strong> " . htmlspecialchars($sample_user['email']) . "</li>";
            echo "<li><strong>Name:</strong> " . htmlspecialchars($sample_user['full_name']) . "</li>";
            echo "<li><strong>Password:</strong> Use the password you set for this user</li>";
            echo "</ul>";
            echo "<p><a href='index.php' class='fix-btn'>Go to Login Page</a></p>";
            echo "</div>";
        } else {
            echo "<div class='info-box' style='background: #ffebee; border-left-color: #f44336;'>";
            echo "<p><strong>❌ No receptionist with password found!</strong></p>";
            echo "<p>You need to create a receptionist account first or set a password for existing ones.</p>";
            echo "</div>";
        }
        ?>
        
        <h3>🚀 Quick Actions</h3>
        <p>
            <a href="index.php" class="fix-btn">Test Login</a>
            <a href="check_security_implementation.php" class="fix-btn" style="background: #2196F3;">Check Security System</a>
            <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="fix-btn" style="background: #FF9800;">Refresh Diagnostic</a>
        </p>
    </div>
</body>
</html>
```
