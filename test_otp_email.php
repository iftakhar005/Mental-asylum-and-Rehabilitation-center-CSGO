<?php
/**
 * Test OTP Email Sending
 * This script tests if OTP emails are being sent successfully
 */

require_once 'otp_functions.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test OTP Email - MindCare System</title>
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
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 600px;
            width: 100%;
            padding: 40px;
        }
        
        h1 {
            color: #333;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: 600;
        }
        
        input[type="email"],
        input[type="text"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.3s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .result {
            margin-top: 20px;
            padding: 15px;
            border-radius: 10px;
            display: none;
        }
        
        .result.success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            display: block;
        }
        
        .result.error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            display: block;
        }
        
        .result h3 {
            margin-bottom: 10px;
        }
        
        .otp-display {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            margin-top: 15px;
        }
        
        .otp-code {
            font-size: 32px;
            font-weight: bold;
            letter-spacing: 8px;
            font-family: 'Courier New', monospace;
            margin: 10px 0;
        }
        
        .info-box {
            background: #f0f4ff;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        
        .log-output {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin-top: 15px;
            max-height: 300px;
            overflow-y: auto;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            white-space: pre-wrap;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 Test OTP Email Sending</h1>
        
        <div class="info-box">
            <strong>📋 Test Instructions:</strong>
            <ol style="margin: 10px 0; padding-left: 20px;">
                <li>Enter the recipient's email address</li>
                <li>Enter the recipient's name</li>
                <li>Click "Send Test OTP"</li>
                <li>Check your email inbox (and spam folder)</li>
                <li>The OTP will also be displayed below for verification</li>
            </ol>
        </div>
        
        <?php
        $test_result = '';
        $test_success = false;
        $test_otp = '';
        $test_logs = '';
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_test'])) {
            $recipient_email = $_POST['recipient_email'] ?? '';
            $recipient_name = $_POST['recipient_name'] ?? '';
            
            if (filter_var($recipient_email, FILTER_VALIDATE_EMAIL)) {
                // Start output buffering to capture error logs
                ob_start();
                
                // Generate test OTP
                $test_otp = generateOTP();
                
                // Try to send email
                $email_sent = sendOTPEmail($recipient_email, $recipient_name, $test_otp);
                
                // Capture output
                $test_logs = ob_get_clean();
                
                if ($email_sent) {
                    $test_result = "✅ Test email sent successfully to {$recipient_email}!";
                    $test_success = true;
                } else {
                    $test_result = "❌ Failed to send test email. Check the logs below.";
                    $test_success = false;
                }
            } else {
                $test_result = "❌ Invalid email address provided.";
                $test_success = false;
            }
        }
        ?>
        
        <?php if ($test_result): ?>
            <div class="result <?php echo $test_success ? 'success' : 'error'; ?>">
                <h3><?php echo $test_result; ?></h3>
                
                <?php if ($test_success && $test_otp): ?>
                    <div class="otp-display">
                        <p style="margin: 0; font-size: 14px;">Test OTP Code:</p>
                        <div class="otp-code"><?php echo $test_otp; ?></div>
                        <p style="margin: 0; font-size: 12px;">Use this code to verify email delivery</p>
                    </div>
                <?php endif; ?>
                
                <?php if ($test_logs): ?>
                    <div class="log-output">
                        <strong>📝 Detailed Logs:</strong>
                        <?php echo htmlspecialchars($test_logs); ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="recipient_email">📧 Recipient Email:</label>
                <input type="email" id="recipient_email" name="recipient_email" 
                       value="<?php echo htmlspecialchars($_POST['recipient_email'] ?? ''); ?>" 
                       placeholder="Enter email address to receive test OTP" required>
            </div>
            
            <div class="form-group">
                <label for="recipient_name">👤 Recipient Name:</label>
                <input type="text" id="recipient_name" name="recipient_name" 
                       value="<?php echo htmlspecialchars($_POST['recipient_name'] ?? 'Test User'); ?>" 
                       placeholder="Enter recipient's name" required>
            </div>
            
            <button type="submit" name="send_test" class="btn">
                📨 Send Test OTP
            </button>
        </form>
        
        <div class="info-box" style="margin-top: 20px;">
            <strong>🔍 What to Check:</strong>
            <ul style="margin: 10px 0; padding-left: 20px;">
                <li>Email should arrive within 1-2 minutes</li>
                <li>Check spam/junk folder if not in inbox</li>
                <li>Verify the OTP code matches what's displayed above</li>
                <li>Review the detailed logs for any errors</li>
            </ul>
        </div>
    </div>
</body>
</html>
