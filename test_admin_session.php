<?php
/**
 * Test Admin Session
 * Quick diagnostic tool to check admin session state
 */

session_start();
require_once 'db.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Session Test</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 40px;
        }
        
        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 30px;
        }
        
        .section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .section h2 {
            color: #667eea;
            margin-bottom: 15px;
            font-size: 20px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: #495057;
        }
        
        .info-value {
            color: #212529;
        }
        
        .status-badge {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }
        
        .status-success {
            background: #d4edda;
            color: #155724;
        }
        
        .status-error {
            background: #f8d7da;
            color: #721c24;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 10px;
            margin-top: 20px;
            transition: all 0.3s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        
        code {
            background: #fff3cd;
            padding: 2px 8px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-user-shield"></i> Admin Session Diagnostic</h1>
        
        <!-- Session Status -->
        <div class="section">
            <h2><i class="fas fa-info-circle"></i> Session Status</h2>
            <?php if (isset($_SESSION['user_id']) && isset($_SESSION['role'])): ?>
                <div class="info-row">
                    <span class="info-label">Login Status:</span>
                    <span class="status-badge status-success">✓ LOGGED IN</span>
                </div>
            <?php else: ?>
                <div class="info-row">
                    <span class="info-label">Login Status:</span>
                    <span class="status-badge status-error">✗ NOT LOGGED IN</span>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Session Variables -->
        <div class="section">
            <h2><i class="fas fa-database"></i> Session Variables</h2>
            <div class="info-row">
                <span class="info-label">user_id:</span>
                <span class="info-value"><code><?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'NOT SET'; ?></code></span>
            </div>
            <div class="info-row">
                <span class="info-label">role:</span>
                <span class="info-value"><code><?php echo isset($_SESSION['role']) ? $_SESSION['role'] : 'NOT SET'; ?></code></span>
            </div>
            <div class="info-row">
                <span class="info-label">username:</span>
                <span class="info-value"><code><?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'NOT SET'; ?></code></span>
            </div>
            <div class="info-row">
                <span class="info-label">staff_id:</span>
                <span class="info-value"><code><?php echo isset($_SESSION['staff_id']) ? $_SESSION['staff_id'] : 'NOT SET'; ?></code></span>
            </div>
        </div>
        
        <!-- Propagation Tracking -->
        <div class="section">
            <h2><i class="fas fa-shield-alt"></i> Propagation Tracking</h2>
            <div class="info-row">
                <span class="info-label">propagation_fingerprint:</span>
                <span class="info-value"><code><?php echo isset($_SESSION['propagation_fingerprint']) ? 'SET' : 'NOT SET'; ?></code></span>
            </div>
            <div class="info-row">
                <span class="info-label">propagation_user_id:</span>
                <span class="info-value"><code><?php echo isset($_SESSION['propagation_user_id']) ? $_SESSION['propagation_user_id'] : 'NOT SET'; ?></code></span>
            </div>
            <div class="info-row">
                <span class="info-label">propagation_role:</span>
                <span class="info-value"><code><?php echo isset($_SESSION['propagation_role']) ? $_SESSION['propagation_role'] : 'NOT SET'; ?></code></span>
            </div>
            <div class="info-row">
                <span class="info-label">propagation_created_at:</span>
                <span class="info-value"><code><?php echo isset($_SESSION['propagation_created_at']) ? date('Y-m-d H:i:s', $_SESSION['propagation_created_at']) : 'NOT SET'; ?></code></span>
            </div>
        </div>
        
        <!-- 2FA Status -->
        <div class="section">
            <h2><i class="fas fa-lock"></i> 2FA Status</h2>
            <div class="info-row">
                <span class="info-label">otp_verification_pending:</span>
                <span class="info-value"><code><?php echo isset($_SESSION['otp_verification_pending']) ? 'TRUE' : 'FALSE'; ?></code></span>
            </div>
            <?php if (isset($_SESSION['user_id'])): ?>
                <?php
                    $user_id = $_SESSION['user_id'];
                    $stmt = $conn->prepare("SELECT two_factor_enabled FROM users WHERE id = ?");
                    $stmt->bind_param('i', $user_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result->num_rows > 0) {
                        $row = $result->fetch_assoc();
                        $two_factor_enabled = $row['two_factor_enabled'];
                    } else {
                        $two_factor_enabled = null;
                    }
                    $stmt->close();
                ?>
                <div class="info-row">
                    <span class="info-label">2FA Enabled in DB:</span>
                    <span class="info-value">
                        <?php if ($two_factor_enabled === null): ?>
                            <code>USER NOT FOUND</code>
                        <?php elseif ($two_factor_enabled): ?>
                            <span class="status-badge status-success">✓ ENABLED</span>
                        <?php else: ?>
                            <span class="status-badge status-error">✗ DISABLED</span>
                        <?php endif; ?>
                    </span>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Database Admin Check -->
        <div class="section">
            <h2><i class="fas fa-database"></i> Database Admin User</h2>
            <?php
                $admin_check = $conn->query("SELECT id, username, email, role, two_factor_enabled FROM users WHERE role = 'admin' LIMIT 1");
                if ($admin_check && $admin_check->num_rows > 0):
                    $admin = $admin_check->fetch_assoc();
            ?>
                <div class="info-row">
                    <span class="info-label">Admin ID:</span>
                    <span class="info-value"><code><?php echo $admin['id']; ?></code></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Username:</span>
                    <span class="info-value"><code><?php echo htmlspecialchars($admin['username']); ?></code></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Email:</span>
                    <span class="info-value"><code><?php echo htmlspecialchars($admin['email']); ?></code></span>
                </div>
                <div class="info-row">
                    <span class="info-label">2FA Status:</span>
                    <span class="info-value">
                        <?php if ($admin['two_factor_enabled']): ?>
                            <span class="status-badge status-success">✓ ENABLED</span>
                        <?php else: ?>
                            <span class="status-badge status-error">✗ DISABLED</span>
                        <?php endif; ?>
                    </span>
                </div>
            <?php else: ?>
                <div class="info-row">
                    <span class="info-label">Status:</span>
                    <span class="status-badge status-error">✗ NO ADMIN USER FOUND</span>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Actions -->
        <div style="text-align: center; margin-top: 30px;">
            <a href="index.php" class="btn">
                <i class="fas fa-sign-in-alt"></i> Go to Login
            </a>
            <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'admin'): ?>
                <a href="admin_dashboard.php" class="btn">
                    <i class="fas fa-tachometer-alt"></i> Go to Dashboard
                </a>
            <?php endif; ?>
            <a href="logout.php" class="btn" style="background: linear-gradient(135deg, #dc3545, #c82333);">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>
</body>
</html>
