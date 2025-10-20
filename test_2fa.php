<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>2FA Quick Test - MindCare System</title>
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
            padding: 20px;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
        }

        .header {
            background: white;
            padding: 30px;
            border-radius: 15px;
            text-align: center;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .header h1 {
            color: #667eea;
            font-size: 32px;
            margin-bottom: 10px;
        }

        .header p {
            color: #666;
            font-size: 16px;
        }

        .status-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 20px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        .status-card h2 {
            color: #333;
            font-size: 22px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .check-item {
            display: flex;
            align-items: center;
            padding: 15px;
            margin: 10px 0;
            background: #f8f9ff;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }

        .check-item i {
            font-size: 24px;
            margin-right: 15px;
            min-width: 30px;
        }

        .check-item.success i {
            color: #28a745;
        }

        .check-item.error i {
            color: #dc3545;
        }

        .check-item.info i {
            color: #667eea;
        }

        .btn {
            display: inline-block;
            padding: 15px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            margin: 5px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #6c757d;
        }

        .btn-success {
            background: #28a745;
        }

        .action-buttons {
            text-align: center;
            margin-top: 20px;
        }

        .code-block {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 20px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            overflow-x: auto;
            margin: 15px 0;
        }

        .step-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 35px;
            height: 35px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 50%;
            font-weight: bold;
            margin-right: 15px;
        }

        .steps {
            list-style: none;
        }

        .steps li {
            display: flex;
            align-items: flex-start;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }

        .steps li:last-child {
            border-bottom: none;
        }

        .highlight {
            background: #fff3cd;
            padding: 3px 8px;
            border-radius: 4px;
            font-weight: 600;
        }

        .success-banner {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            margin-bottom: 20px;
            font-size: 18px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-shield-check"></i> 2FA System Status</h1>
            <p>Two-Factor Authentication - Quick Test Dashboard</p>
        </div>

        <?php
        require_once 'db.php';
        require_once 'otp_functions.php';

        $all_checks_passed = true;
        $errors = [];
        $success_messages = [];

        // Check 1: Database Tables
        $tables_ok = true;
        $required_tables = ['otp_codes', 'staff', 'users'];
        
        foreach ($required_tables as $table) {
            $result = $conn->query("SHOW TABLES LIKE '$table'");
            if ($result->num_rows == 0) {
                $tables_ok = false;
                $errors[] = "Table '$table' not found";
            }
        }

        // Check 2: Database Columns
        $columns_ok = true;
        
        // Check staff table
        $result = $conn->query("SHOW COLUMNS FROM staff LIKE 'two_factor_enabled'");
        if ($result->num_rows == 0) {
            $columns_ok = false;
            $errors[] = "Column 'two_factor_enabled' not found in staff table";
        }

        // Check users table
        $result = $conn->query("SHOW COLUMNS FROM users LIKE 'two_factor_enabled'");
        if ($result->num_rows == 0) {
            $columns_ok = false;
            $errors[] = "Column 'two_factor_enabled' not found in users table";
        }

        // Check 3: 2FA Functions
        $functions_ok = true;
        $required_functions = ['generateOTP', 'storeOTP', 'verifyOTP', 'sendOTPEmail', 'sendOTPEmailSimple'];
        
        foreach ($required_functions as $func) {
            if (!function_exists($func)) {
                $functions_ok = false;
                $errors[] = "Function '$func' not found";
            }
        }

        // Check 4: Test OTP Generation
        $otp_generation_ok = false;
        try {
            $test_otp = generateOTP();
            if (strlen($test_otp) == 6 && is_numeric($test_otp)) {
                $otp_generation_ok = true;
                $success_messages[] = "Generated test OTP: $test_otp";
            }
        } catch (Exception $e) {
            $errors[] = "OTP generation failed: " . $e->getMessage();
        }

        // Check 5: Count 2FA Enabled Users
        $result = $conn->query("SELECT COUNT(*) as count FROM staff WHERE two_factor_enabled = 1");
        $row = $result->fetch_assoc();
        $twofa_users_count = $row['count'];

        // Check 6: Count OTP Codes
        $result = $conn->query("SELECT COUNT(*) as count FROM otp_codes");
        $row = $result->fetch_assoc();
        $otp_codes_count = $row['count'];

        $all_checks_passed = $tables_ok && $columns_ok && $functions_ok && $otp_generation_ok;
        ?>

        <?php if ($all_checks_passed): ?>
            <div class="success-banner">
                <i class="fas fa-check-circle" style="font-size: 30px; margin-bottom: 10px; display: block;"></i>
                ✨ 2FA System is 100% Ready! All Checks Passed! ✨
            </div>
        <?php endif; ?>

        <div class="status-card">
            <h2><i class="fas fa-clipboard-check"></i> System Status</h2>
            
            <div class="check-item <?php echo $tables_ok ? 'success' : 'error'; ?>">
                <i class="fas <?php echo $tables_ok ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                <div>
                    <strong>Database Tables:</strong> 
                    <?php echo $tables_ok ? 'All required tables exist' : 'Missing tables'; ?>
                </div>
            </div>

            <div class="check-item <?php echo $columns_ok ? 'success' : 'error'; ?>">
                <i class="fas <?php echo $columns_ok ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                <div>
                    <strong>Database Columns:</strong> 
                    <?php echo $columns_ok ? 'All required columns exist' : 'Missing columns'; ?>
                </div>
            </div>

            <div class="check-item <?php echo $functions_ok ? 'success' : 'error'; ?>">
                <i class="fas <?php echo $functions_ok ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                <div>
                    <strong>2FA Functions:</strong> 
                    <?php echo $functions_ok ? 'All functions loaded' : 'Missing functions'; ?>
                </div>
            </div>

            <div class="check-item <?php echo $otp_generation_ok ? 'success' : 'error'; ?>">
                <i class="fas <?php echo $otp_generation_ok ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                <div>
                    <strong>OTP Generation:</strong> 
                    <?php echo $otp_generation_ok ? 'Working correctly' : 'Failed'; ?>
                </div>
            </div>

            <div class="check-item info">
                <i class="fas fa-users"></i>
                <div>
                    <strong>2FA Enabled Users:</strong> <?php echo $twofa_users_count; ?> user(s)
                </div>
            </div>

            <div class="check-item info">
                <i class="fas fa-key"></i>
                <div>
                    <strong>Total OTP Codes:</strong> <?php echo $otp_codes_count; ?> code(s) in database
                </div>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="status-card" style="border-left: 4px solid #dc3545;">
                <h2 style="color: #dc3545;"><i class="fas fa-exclamation-triangle"></i> Issues Found</h2>
                <?php foreach ($errors as $error): ?>
                    <div class="check-item error">
                        <i class="fas fa-times-circle"></i>
                        <div><?php echo htmlspecialchars($error); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="status-card">
            <h2><i class="fas fa-rocket"></i> Quick Test Steps</h2>
            <ol class="steps">
                <li>
                    <span class="step-number">1</span>
                    <div>
                        <strong>Open OTP Checker Tool</strong><br>
                        Click the button below to open the real-time OTP code viewer
                    </div>
                </li>
                <li>
                    <span class="step-number">2</span>
                    <div>
                        <strong>Create Test User</strong><br>
                        Create a new staff member and <span class="highlight">check the 2FA checkbox</span>
                    </div>
                </li>
                <li>
                    <span class="step-number">3</span>
                    <div>
                        <strong>Test Login</strong><br>
                        Logout and login with the new user credentials
                    </div>
                </li>
                <li>
                    <span class="step-number">4</span>
                    <div>
                        <strong>Get OTP Code</strong><br>
                        Check the OTP Checker tool to see the 6-digit code
                    </div>
                </li>
                <li>
                    <span class="step-number">5</span>
                    <div>
                        <strong>Verify OTP</strong><br>
                        Enter the code on the verification page and complete login
                    </div>
                </li>
            </ol>
        </div>

        <div class="status-card">
            <h2><i class="fas fa-info-circle"></i> How Email Works</h2>
            <div style="padding: 15px; background: #e7f3ff; border-radius: 8px; margin-top: 15px;">
                <p style="margin-bottom: 10px;">
                    <strong>Current Mode: Development (Localhost)</strong>
                </p>
                <p style="color: #666; line-height: 1.8;">
                    Since SMTP is not configured, the system automatically uses a <strong>fallback method</strong>. 
                    OTP codes are stored in the database and logged to PHP error log. You can view them in 
                    real-time using the <strong>OTP Checker Tool</strong>. This is perfect for testing and development!
                </p>
                <p style="margin-top: 15px; color: #666;">
                    <i class="fas fa-lightbulb" style="color: #ffc107;"></i> 
                    <strong>For Production:</strong> Configure SMTP in phpmailer_config.php to send real emails.
                </p>
            </div>
        </div>

        <div class="action-buttons">
            <a href="check_otp_logs.php" class="btn btn-success" target="_blank">
                <i class="fas fa-eye"></i> Open OTP Checker Tool
            </a>
            <a href="index.php" class="btn">
                <i class="fas fa-sign-in-alt"></i> Go to Login Page
            </a>
            <a href="2FA_EMAIL_FIX_GUIDE.md" class="btn btn-secondary" target="_blank">
                <i class="fas fa-book"></i> Read Full Guide
            </a>
        </div>

        <?php if ($all_checks_passed && $twofa_users_count > 0): ?>
            <div class="status-card" style="background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%); border: none;">
                <h2 style="color: #155724;"><i class="fas fa-trophy"></i> Ready to Test!</h2>
                <p style="color: #155724; margin-top: 10px; font-size: 16px; line-height: 1.8;">
                    You have <?php echo $twofa_users_count; ?> user(s) with 2FA enabled. 
                    Click "Open OTP Checker Tool" above, then try logging in with one of those users.
                    The OTP code will appear in the checker tool automatically!
                </p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
