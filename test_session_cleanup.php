<?php
session_start();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Session Cleanup Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 900px;
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
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #3498db;
            color: white;
        }
        tr:hover {
            background: #f5f5f5;
        }
        .set {
            color: #27ae60;
            font-weight: bold;
        }
        .unset {
            color: #e74c3c;
        }
        .info {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            margin: 20px 0;
        }
        .warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
        }
        .success {
            background: #d4edda;
            border-left: 4px solid #28a745;
            padding: 15px;
            margin: 20px 0;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
        }
        .btn {
            padding: 10px 20px;
            margin: 5px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }
        .btn-primary {
            background: #3498db;
            color: white;
        }
        .btn-danger {
            background: #e74c3c;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>🔍 Session Variables Status</h2>
        
        <?php
        $otp_vars = [
            'otp_verification_pending',
            'otp_email',
            'pending_user_id',
            'pending_staff_id',
            'pending_username',
            'pending_role'
        ];
        
        $auth_vars = [
            'user_id',
            'staff_id',
            'username',
            'role'
        ];
        
        $all_set = count($otp_vars);
        $set_count = 0;
        
        foreach ($otp_vars as $var) {
            if (isset($_SESSION[$var])) {
                $set_count++;
            }
        }
        
        if ($set_count == 0) {
            echo '<div class="success">✅ <strong>GOOD!</strong> All OTP session variables are cleared.</div>';
        } else if ($set_count == $all_set) {
            echo '<div class="warning">⚠️ <strong>OTP PENDING!</strong> User is in 2FA verification stage.</div>';
        } else {
            echo '<div class="warning">⚠️ <strong>INCOMPLETE CLEANUP!</strong> ' . $set_count . ' out of ' . $all_set . ' OTP variables still set. This causes the "first OTP fails, second works" bug!</div>';
        }
        ?>
        
        <h3>📋 OTP Verification Session Variables</h3>
        <table>
            <thead>
                <tr>
                    <th>Variable Name</th>
                    <th>Status</th>
                    <th>Value</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($otp_vars as $var): ?>
                <tr>
                    <td><code>$_SESSION['<?php echo $var; ?>']</code></td>
                    <td class="<?php echo isset($_SESSION[$var]) ? 'set' : 'unset'; ?>">
                        <?php echo isset($_SESSION[$var]) ? '✅ SET' : '❌ UNSET'; ?>
                    </td>
                    <td>
                        <?php 
                        if (isset($_SESSION[$var])) {
                            if ($var === 'otp_verification_pending') {
                                echo $_SESSION[$var] ? 'TRUE' : 'FALSE';
                            } else {
                                echo htmlspecialchars($_SESSION[$var]);
                            }
                        } else {
                            echo '<em style="color:#999;">not set</em>';
                        }
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <h3>🔐 Authenticated Session Variables</h3>
        <table>
            <thead>
                <tr>
                    <th>Variable Name</th>
                    <th>Status</th>
                    <th>Value</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($auth_vars as $var): ?>
                <tr>
                    <td><code>$_SESSION['<?php echo $var; ?>']</code></td>
                    <td class="<?php echo isset($_SESSION[$var]) ? 'set' : 'unset'; ?>">
                        <?php echo isset($_SESSION[$var]) ? '✅ SET' : '❌ UNSET'; ?>
                    </td>
                    <td>
                        <?php 
                        if (isset($_SESSION[$var])) {
                            echo htmlspecialchars($_SESSION[$var]);
                        } else {
                            echo '<em style="color:#999;">not set</em>';
                        }
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="info">
            <h4>ℹ️ How to Test the Fix</h4>
            <ol>
                <li><strong>Login with 2FA enabled user</strong> (e.g., Ratul)</li>
                <li><strong>First OTP attempt:</strong> Enter wrong OTP → should show error, check this page</li>
                <li><strong>If bug exists:</strong> You'll see incomplete session cleanup</li>
                <li><strong>Second OTP attempt:</strong> Enter correct OTP → should redirect to dashboard</li>
                <li><strong>After fix:</strong> First OTP with correct code should work immediately!</li>
            </ol>
        </div>
        
        <div style="margin-top: 30px;">
            <a href="index.php" class="btn btn-primary">← Back to Login</a>
            <a href="verify_otp.php" class="btn btn-primary">Go to OTP Page</a>
            <form method="POST" style="display:inline;">
                <button type="submit" name="clear_all" class="btn btn-danger">Clear All Sessions</button>
            </form>
        </div>
        
        <?php
        if (isset($_POST['clear_all'])) {
            session_unset();
            session_destroy();
            echo '<div class="success" style="margin-top:20px;">✅ All session variables cleared! <a href="">Refresh page</a></div>';
        }
        ?>
        
        <h3>📝 All Session Variables</h3>
        <pre style="background:#f4f4f4; padding:15px; border-radius:5px; overflow-x:auto;">
<?php print_r($_SESSION); ?>
        </pre>
    </div>
</body>
</html>
