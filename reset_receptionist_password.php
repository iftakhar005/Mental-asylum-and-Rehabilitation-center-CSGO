<?php
/**
 * Reset Receptionist Password
 * Simple tool to update receptionist password in database
 */

require_once 'db.php';

$message = '';
$message_type = '';

// Process password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    $email = trim($_POST['email']);
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($email) || empty($new_password)) {
        $message = '❌ Email and password are required';
        $message_type = 'error';
    } elseif ($new_password !== $confirm_password) {
        $message = '❌ Passwords do not match';
        $message_type = 'error';
    } elseif (strlen($new_password) < 6) {
        $message = '❌ Password must be at least 6 characters';
        $message_type = 'error';
    } else {
        // Hash the new password
        $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Update in staff table
        $stmt = $conn->prepare("UPDATE staff SET password_hash = ? WHERE email = ? AND role = 'receptionist'");
        $stmt->bind_param('ss', $password_hash, $email);
        
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $message = "✅ Password updated successfully for: $email";
            $message_type = 'success';
            
            // Also update in users table if exists
            $stmt2 = $conn->prepare("UPDATE users SET password_hash = ? WHERE email = ? AND role = 'receptionist'");
            $stmt2->bind_param('ss', $password_hash, $email);
            $stmt2->execute();
            
        } else {
            $message = "❌ No receptionist found with email: $email";
            $message_type = 'error';
        }
    }
}

// Get all receptionists
$receptionists_staff = $conn->query("SELECT staff_id, full_name, email, role FROM staff WHERE role = 'receptionist' ORDER BY full_name");
$receptionists_users = $conn->query("SELECT id, username, email, role FROM users WHERE role = 'receptionist' ORDER BY username");

?>
<!DOCTYPE html>
<html>
<head>
    <title>Reset Receptionist Password</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 600px;
            width: 100%;
        }
        h2 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .message {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-weight: 500;
            animation: slideDown 0.3s ease;
        }
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .form-group {
            margin-bottom: 25px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }
        select, input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s;
            box-sizing: border-box;
        }
        select:focus, input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        .btn {
            width: 100%;
            padding: 14px 24px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }
        .btn:active {
            transform: translateY(0);
        }
        .btn-secondary {
            background: #6c757d;
            margin-top: 15px;
        }
        .info-box {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            border-left: 4px solid #2196F3;
        }
        .info-box h4 {
            margin: 0 0 10px 0;
            color: #1976d2;
        }
        .info-box ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        .info-box li {
            margin: 5px 0;
            color: #555;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-staff {
            background: #d4edda;
            color: #155724;
        }
        .badge-users {
            background: #fff3cd;
            color: #856404;
        }
        .password-strength {
            font-size: 12px;
            margin-top: 5px;
            color: #666;
        }
        .strength-weak { color: #dc3545; }
        .strength-medium { color: #ffc107; }
        .strength-strong { color: #28a745; }
    </style>
</head>
<body>
    <div class="container">
        <h2>🔐 Reset Receptionist Password</h2>
        <p class="subtitle">Update receptionist password in the database</p>
        
        <?php if (!empty($message)): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <div class="info-box">
            <h4>📋 Available Receptionists</h4>
            
            <?php if ($receptionists_staff && $receptionists_staff->num_rows > 0): ?>
                <p><strong>In Staff Table:</strong> <span class="badge badge-staff">CORRECT</span></p>
                <table>
                    <tr><th>Name</th><th>Email</th></tr>
                    <?php while($row = $receptionists_staff->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['email']); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </table>
            <?php endif; ?>
            
            <?php if ($receptionists_users && $receptionists_users->num_rows > 0): ?>
                <p><strong>In Users Table:</strong> <span class="badge badge-users">DUPLICATE</span></p>
                <table>
                    <tr><th>Username</th><th>Email</th></tr>
                    <?php while($row = $receptionists_users->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['username']); ?></td>
                            <td><?php echo htmlspecialchars($row['email']); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </table>
            <?php endif; ?>
        </div>
        
        <form method="POST" id="resetForm">
            <div class="form-group">
                <label for="email">Receptionist Email</label>
                <select name="email" id="email" required>
                    <option value="">-- Select Receptionist --</option>
                    <?php 
                    $receptionists_staff->data_seek(0);
                    while($row = $receptionists_staff->fetch_assoc()): 
                    ?>
                        <option value="<?php echo htmlspecialchars($row['email']); ?>">
                            <?php echo htmlspecialchars($row['full_name']); ?> - <?php echo htmlspecialchars($row['email']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="new_password">New Password</label>
                <input type="password" name="new_password" id="new_password" 
                       placeholder="Enter new password (min 6 characters)" 
                       minlength="6" required>
                <div class="password-strength" id="strength"></div>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" name="confirm_password" id="confirm_password" 
                       placeholder="Re-enter password" 
                       minlength="6" required>
            </div>
            
            <button type="submit" name="update_password" class="btn">
                ✅ Update Password
            </button>
            
            <button type="button" class="btn btn-secondary" onclick="window.location.href='index.php'">
                ← Back to Login
            </button>
        </form>
        
        <div class="info-box" style="margin-top: 30px; background: #fff3cd; border-left-color: #ff9800;">
            <h4>💡 Quick Tips</h4>
            <ul>
                <li>Password will be securely hashed using <code>password_hash()</code></li>
                <li>Minimum password length: 6 characters</li>
                <li>Recommended: Use mix of letters, numbers, and symbols</li>
                <li>After updating, test login at <a href="index.php">index.php</a></li>
            </ul>
        </div>
    </div>
    
    <script>
        // Password strength checker
        document.getElementById('new_password').addEventListener('input', function() {
            const password = this.value;
            const strengthDiv = document.getElementById('strength');
            
            if (password.length === 0) {
                strengthDiv.textContent = '';
                return;
            }
            
            let strength = 0;
            if (password.length >= 6) strength++;
            if (password.length >= 10) strength++;
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
            if (/\d/.test(password)) strength++;
            if (/[^a-zA-Z0-9]/.test(password)) strength++;
            
            if (strength <= 2) {
                strengthDiv.textContent = '⚠️ Weak password';
                strengthDiv.className = 'password-strength strength-weak';
            } else if (strength <= 3) {
                strengthDiv.textContent = '✓ Medium password';
                strengthDiv.className = 'password-strength strength-medium';
            } else {
                strengthDiv.textContent = '✅ Strong password';
                strengthDiv.className = 'password-strength strength-strong';
            }
        });
        
        // Password match validation
        document.getElementById('resetForm').addEventListener('submit', function(e) {
            const password = document.getElementById('new_password').value;
            const confirm = document.getElementById('confirm_password').value;
            
            if (password !== confirm) {
                e.preventDefault();
                alert('❌ Passwords do not match!');
                return false;
            }
        });
    </script>
</body>
</html>
```
