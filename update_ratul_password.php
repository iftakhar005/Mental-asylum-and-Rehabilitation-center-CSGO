<?php
/**
 * Update Ratul's Password to "ratul123"
 * This will update the password in the staff table
 */

require_once 'db.php';

// The new password
$new_password = "ratul123";

// Generate proper bcrypt hash
$password_hash = password_hash($new_password, PASSWORD_BCRYPT);

echo "<!DOCTYPE html>
<html>
<head>
    <title>Update Ratul's Password</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h2 {
            color: #2c3e50;
            border-bottom: 3px solid #3498db;
            padding-bottom: 10px;
        }
        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            margin: 20px 0;
        }
        .success {
            background: #d4edda;
            border-left-color: #28a745;
            color: #155724;
        }
        .error {
            background: #f8d7da;
            border-left-color: #dc3545;
            color: #721c24;
        }
        .warning {
            background: #fff3cd;
            border-left-color: #ffc107;
            color: #856404;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
        .login-info {
            background: #d1ecf1;
            border: 2px solid #0c5460;
            padding: 20px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .login-info h3 {
            margin-top: 0;
            color: #0c5460;
        }
        .credential {
            font-size: 18px;
            font-weight: bold;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class='container'>
        <h2>🔐 Update Ratul's Password</h2>
";

// Step 1: Delete duplicate from users table (if exists)
echo "<h3>Step 1: Remove Duplicate Entry</h3>";
$delete_sql = "DELETE FROM users WHERE id = 17";
if ($conn->query($delete_sql)) {
    if ($conn->affected_rows > 0) {
        echo "<div class='info-box success'>✅ Deleted duplicate entry from users table (ID: 17)</div>";
    } else {
        echo "<div class='info-box warning'>ℹ️ No duplicate found in users table (already cleaned)</div>";
    }
} else {
    echo "<div class='info-box error'>⚠️ Error deleting duplicate: " . $conn->error . "</div>";
}

// Step 2: Update password in staff table
echo "<h3>Step 2: Update Password</h3>";
echo "<div class='info-box'>
    <strong>New Password:</strong> <code>ratul123</code><br>
    <strong>Bcrypt Hash:</strong> <code>" . htmlspecialchars($password_hash) . "</code>
</div>";

$update_sql = "UPDATE staff 
               SET password_hash = ?,
                   status = 'Active',
                   two_factor_enabled = 0
               WHERE staff_id = 'NUR-20251021-3412'";

$stmt = $conn->prepare($update_sql);
$stmt->bind_param("s", $password_hash);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo "<div class='info-box success'>✅ Password updated successfully!</div>";
    } else {
        echo "<div class='info-box error'>❌ No rows updated. Staff ID might not exist.</div>";
    }
} else {
    echo "<div class='info-box error'>❌ Error updating password: " . $conn->error . "</div>";
}

// Step 3: Verify the update
echo "<h3>Step 3: Verify Updated Information</h3>";
$verify_sql = "SELECT staff_id, full_name, email, role, status, two_factor_enabled, password_hash
               FROM staff 
               WHERE staff_id = 'NUR-20251021-3412'";

$result = $conn->query($verify_sql);

if ($result && $result->num_rows > 0) {
    $user = $result->fetch_assoc();
    
    echo "<div class='info-box'>";
    echo "<strong>Staff ID:</strong> " . htmlspecialchars($user['staff_id']) . "<br>";
    echo "<strong>Name:</strong> " . htmlspecialchars($user['full_name']) . "<br>";
    echo "<strong>Email:</strong> " . htmlspecialchars($user['email']) . "<br>";
    echo "<strong>Role:</strong> " . htmlspecialchars($user['role']) . "<br>";
    echo "<strong>Status:</strong> <code>" . htmlspecialchars($user['status']) . "</code> ";
    
    if ($user['status'] === 'Active') {
        echo "✅";
    } else {
        echo "❌ Should be 'Active'";
    }
    echo "<br>";
    
    echo "<strong>2FA Enabled:</strong> " . ($user['two_factor_enabled'] ? '❌ YES (will require OTP)' : '✅ NO') . "<br>";
    echo "</div>";
    
    // Step 4: Test password verification
    echo "<h3>Step 4: Test Password Verification</h3>";
    $test_password = "ratul123";
    $verify_result = password_verify($test_password, $user['password_hash']);
    
    if ($verify_result) {
        echo "<div class='info-box success'>";
        echo "<h3>✅ ✅ ✅ PASSWORD VERIFICATION SUCCESSFUL!</h3>";
        echo "password_verify('ratul123', hash) = <strong>TRUE</strong>";
        echo "</div>";
    } else {
        echo "<div class='info-box error'>";
        echo "❌ Password verification FAILED!<br>";
        echo "password_verify('ratul123', hash) = <strong>FALSE</strong>";
        echo "</div>";
    }
    
} else {
    echo "<div class='info-box error'>❌ Could not find user with Staff ID: NUR-20251021-3412</div>";
}

// Step 5: Check for duplicates
echo "<h3>Step 5: Check for Duplicate Entries</h3>";
$dup_check = "SELECT COUNT(*) as count FROM users WHERE username = 'ratul@gmail.com' OR email = 'ratul@gmail.com'";
$dup_result = $conn->query($dup_check);
$dup_data = $dup_result->fetch_assoc();

if ($dup_data['count'] > 0) {
    echo "<div class='info-box error'>❌ WARNING: User still exists in 'users' table ({$dup_data['count']} records)</div>";
} else {
    echo "<div class='info-box success'>✅ No duplicate in 'users' table</div>";
}

// Final Instructions
echo "<div class='login-info'>
    <h3>🎯 Login Credentials for Ratul</h3>
    <div class='credential'>📧 Email: <code>ratul@gmail.com</code></div>
    <div class='credential'>🔑 Password: <code>ratul123</code></div>
    <div class='credential'>🌐 Login URL: <code>http://localhost/CSGO/Mental-asylum-and-Rehabilitation-center-CSGO/index.php</code></div>
</div>";

echo "<div class='info-box warning'>
    <h3>⚠️ IMPORTANT - Clear Browser Cache!</h3>
    <ol>
        <li>Press <strong>Ctrl + Shift + Delete</strong></li>
        <li>Select 'Cookies and other site data' and 'Cached images and files'</li>
        <li>Click 'Clear data'</li>
        <li>Close browser completely</li>
        <li>Reopen and try logging in</li>
    </ol>
    <p>Or use <strong>Incognito/Private mode</strong> to test immediately.</p>
</div>";

echo "
    </div>
</body>
</html>";

$conn->close();
?>
