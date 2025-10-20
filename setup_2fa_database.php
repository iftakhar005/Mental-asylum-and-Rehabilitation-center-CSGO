<?php
/**
 * 2FA Database Setup Script
 * Run this ONCE to add 2FA columns and tables to your database
 */

require_once 'db.php';

$success_messages = [];
$error_messages = [];

// Add two_factor_enabled column to users table
try {
    $check = $conn->query("SHOW COLUMNS FROM users LIKE 'two_factor_enabled'");
    if ($check->num_rows == 0) {
        $conn->query("ALTER TABLE users ADD COLUMN two_factor_enabled TINYINT(1) DEFAULT 0 AFTER status");
        $success_messages[] = "Added 'two_factor_enabled' column to users table";
    } else {
        $success_messages[] = "'two_factor_enabled' column already exists in users table";
    }
} catch (Exception $e) {
    $error_messages[] = "Error adding two_factor_enabled to users: " . $e->getMessage();
}

// Add two_factor_secret column to users table
try {
    $check = $conn->query("SHOW COLUMNS FROM users LIKE 'two_factor_secret'");
    if ($check->num_rows == 0) {
        $conn->query("ALTER TABLE users ADD COLUMN two_factor_secret VARCHAR(255) DEFAULT NULL AFTER two_factor_enabled");
        $success_messages[] = "Added 'two_factor_secret' column to users table";
    } else {
        $success_messages[] = "'two_factor_secret' column already exists in users table";
    }
} catch (Exception $e) {
    $error_messages[] = "Error adding two_factor_secret to users: " . $e->getMessage();
}

// Create otp_codes table
try {
    $check = $conn->query("SHOW TABLES LIKE 'otp_codes'");
    if ($check->num_rows == 0) {
        $sql = "CREATE TABLE otp_codes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            email VARCHAR(100) NOT NULL,
            otp_code VARCHAR(6) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NOT NULL,
            is_used TINYINT(1) DEFAULT 0,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_email (email),
            INDEX idx_otp_code (otp_code),
            INDEX idx_expires_at (expires_at)
        )";
        $conn->query($sql);
        $success_messages[] = "Created 'otp_codes' table";
    } else {
        $success_messages[] = "'otp_codes' table already exists";
    }
} catch (Exception $e) {
    $error_messages[] = "Error creating otp_codes table: " . $e->getMessage();
}

// Add two_factor_enabled column to staff table
try {
    $check = $conn->query("SHOW COLUMNS FROM staff LIKE 'two_factor_enabled'");
    if ($check->num_rows == 0) {
        $conn->query("ALTER TABLE staff ADD COLUMN two_factor_enabled TINYINT(1) DEFAULT 0");
        $success_messages[] = "Added 'two_factor_enabled' column to staff table";
    } else {
        $success_messages[] = "'two_factor_enabled' column already exists in staff table";
    }
} catch (Exception $e) {
    $error_messages[] = "Error adding two_factor_enabled to staff: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>2FA Database Setup</title>
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
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 40px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .header h1 {
            color: #333;
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .header p {
            color: #666;
            font-size: 16px;
        }
        
        .status-box {
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 15px;
            display: flex;
            align-items: flex-start;
            gap: 15px;
        }
        
        .success-box {
            background: #d4edda;
            border-left: 4px solid #28a745;
        }
        
        .error-box {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
        }
        
        .status-box i {
            font-size: 24px;
            margin-top: 2px;
        }
        
        .success-box i {
            color: #28a745;
        }
        
        .error-box i {
            color: #dc3545;
        }
        
        .status-box .content {
            flex: 1;
        }
        
        .status-box h3 {
            margin-bottom: 10px;
            color: #333;
        }
        
        .status-box p {
            color: #666;
            line-height: 1.6;
        }
        
        .summary {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-top: 30px;
            text-align: center;
        }
        
        .summary h2 {
            color: #333;
            margin-bottom: 15px;
        }
        
        .summary-stats {
            display: flex;
            justify-content: center;
            gap: 40px;
            margin-top: 20px;
        }
        
        .stat {
            text-align: center;
        }
        
        .stat-value {
            font-size: 36px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
        }
        
        .success-value {
            color: #28a745;
        }
        
        .error-value {
            color: #dc3545;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 30px;
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
        
        .next-steps {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 20px;
            border-radius: 10px;
            margin-top: 30px;
        }
        
        .next-steps h3 {
            color: #856404;
            margin-bottom: 15px;
        }
        
        .next-steps ol {
            margin-left: 20px;
            color: #856404;
            line-height: 1.8;
        }
        
        .next-steps ol li {
            margin-bottom: 10px;
        }
        
        .next-steps code {
            background: #ffeaa7;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-database"></i> 2FA Database Setup</h1>
            <p>Setting up Two-Factor Authentication database structure</p>
        </div>
        
        <?php foreach ($success_messages as $msg): ?>
            <div class="status-box success-box">
                <i class="fas fa-check-circle"></i>
                <div class="content">
                    <h3>Success</h3>
                    <p><?php echo htmlspecialchars($msg); ?></p>
                </div>
            </div>
        <?php endforeach; ?>
        
        <?php foreach ($error_messages as $msg): ?>
            <div class="status-box error-box">
                <i class="fas fa-exclamation-circle"></i>
                <div class="content">
                    <h3>Error</h3>
                    <p><?php echo htmlspecialchars($msg); ?></p>
                </div>
            </div>
        <?php endforeach; ?>
        
        <div class="summary">
            <h2>Setup Summary</h2>
            <div class="summary-stats">
                <div class="stat">
                    <div class="stat-value success-value"><?php echo count($success_messages); ?></div>
                    <div class="stat-label">Successful Operations</div>
                </div>
                <div class="stat">
                    <div class="stat-value error-value"><?php echo count($error_messages); ?></div>
                    <div class="stat-label">Errors</div>
                </div>
            </div>
            
            <?php if (count($error_messages) == 0): ?>
                <p style="color: #28a745; margin-top: 20px; font-weight: bold;">
                    <i class="fas fa-check-circle"></i> Database setup completed successfully!
                </p>
            <?php else: ?>
                <p style="color: #dc3545; margin-top: 20px; font-weight: bold;">
                    <i class="fas fa-exclamation-triangle"></i> Some errors occurred. Please check the messages above.
                </p>
            <?php endif; ?>
        </div>
        
        <?php if (count($error_messages) == 0): ?>
            <div class="next-steps">
                <h3><i class="fas fa-list-check"></i> Next Steps</h3>
                <ol>
                    <li>Update <code>phpmailer_config.php</code> with your SMTP credentials (Gmail, Outlook, etc.)</li>
                    <li>Test SMTP configuration by visiting <code>test_smtp.php</code></li>
                    <li>Enable 2FA for users when creating them (check the "Enable 2FA" checkbox)</li>
                    <li>Test the login flow with a 2FA-enabled user</li>
                    <li>Check your email for the OTP code</li>
                    <li>Enter the OTP on the verification page</li>
                </ol>
            </div>
            
            <div style="text-align: center;">
                <a href="index.php" class="btn">
                    <i class="fas fa-arrow-right"></i> Go to Login Page
                </a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
