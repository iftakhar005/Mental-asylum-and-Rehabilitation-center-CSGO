<?php
/**
 * Test SMTP Configuration
 * Use this to verify your email settings before using 2FA
 */

require_once 'phpmailer_config.php';

$test_email = $_POST['test_email'] ?? '';
$test_result = '';
$test_success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($test_email)) {
    // Generate test OTP
    $test_otp = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
    
    try {
        // SMTP Configuration
        $smtp_host = SMTP_HOST;
        $smtp_port = SMTP_PORT;
        $smtp_username = SMTP_USERNAME;
        $smtp_password = SMTP_PASSWORD;
        $from_email = SMTP_FROM_EMAIL;
        $from_name = SMTP_FROM_NAME;
        
        // Email subject and body
        $subject = "Test OTP Code - MindCare System";
        $text_body = "Hello,\n\nThis is a test email from MindCare System.\n\nYour test OTP code is: {$test_otp}\n\nIf you received this email, your SMTP configuration is working correctly!\n\n---\nMindCare Mental Health System";
        
        $html_body = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #667eea; color: white; padding: 20px; text-align: center; }
                .content { padding: 30px; background: #f9f9f9; }
                .otp { font-size: 32px; font-weight: bold; text-align: center; color: #667eea; margin: 20px 0; }
                .success { background: #d4edda; padding: 15px; border-left: 4px solid #28a745; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🔐 Test Email</h1>
                    <p>MindCare System - SMTP Configuration Test</p>
                </div>
                <div class='content'>
                    <div class='success'>
                        <strong>✅ Success!</strong> Your SMTP configuration is working correctly.
                    </div>
                    <p>Your test OTP code is:</p>
                    <div class='otp'>{$test_otp}</div>
                    <p>If you can see this email, your 2FA system is ready to use!</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        // Email headers
        $boundary = md5(uniqid(time()));
        $headers = "From: {$from_name} <{$from_email}>\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
        
        $message = "--{$boundary}\r\n";
        $message .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
        $message .= $text_body . "\r\n\r\n";
        $message .= "--{$boundary}\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
        $message .= $html_body . "\r\n\r\n";
        $message .= "--{$boundary}--";
        
        // Connect to SMTP server
        $socket = @fsockopen($smtp_host, $smtp_port, $errno, $errstr, 30);
        
        if (!$socket) {
            throw new Exception("Failed to connect to SMTP server: {$errstr} ({$errno})");
        }
        
        // Read server response
        fgets($socket, 515);
        
        // EHLO
        fputs($socket, "EHLO {$smtp_host}\r\n");
        fgets($socket, 515);
        
        // STARTTLS
        fputs($socket, "STARTTLS\r\n");
        $response = fgets($socket, 515);
        
        if (substr($response, 0, 3) != '220') {
            throw new Exception("STARTTLS failed: {$response}");
        }
        
        // Enable crypto
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new Exception("Failed to enable TLS encryption");
        }
        
        // EHLO again after TLS
        fputs($socket, "EHLO {$smtp_host}\r\n");
        fgets($socket, 515);
        
        // AUTH LOGIN
        fputs($socket, "AUTH LOGIN\r\n");
        fgets($socket, 515);
        
        fputs($socket, base64_encode($smtp_username) . "\r\n");
        fgets($socket, 515);
        
        fputs($socket, base64_encode($smtp_password) . "\r\n");
        $auth_response = fgets($socket, 515);
        
        if (substr($auth_response, 0, 3) != '235') {
            throw new Exception("Authentication failed. Check your SMTP username and password.");
        }
        
        // MAIL FROM
        fputs($socket, "MAIL FROM: <{$from_email}>\r\n");
        fgets($socket, 515);
        
        // RCPT TO
        fputs($socket, "RCPT TO: <{$test_email}>\r\n");
        fgets($socket, 515);
        
        // DATA
        fputs($socket, "DATA\r\n");
        fgets($socket, 515);
        
        // Send email
        fputs($socket, "Subject: {$subject}\r\n");
        fputs($socket, $headers);
        fputs($socket, "\r\n{$message}\r\n.\r\n");
        fgets($socket, 515);
        
        // QUIT
        fputs($socket, "QUIT\r\n");
        fclose($socket);
        
        $test_result = "✅ Success! Test email sent to {$test_email}. Check your inbox (and spam folder).";
        $test_success = true;
        
    } catch (Exception $e) {
        $test_result = "❌ Error: " . $e->getMessage();
        $test_success = false;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test SMTP Configuration</title>
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
            max-width: 700px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 40px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .header h1 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .header p {
            color: #666;
        }
        
        .config-display {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            font-family: monospace;
        }
        
        .config-display h3 {
            margin-bottom: 15px;
            color: #333;
        }
        
        .config-item {
            padding: 8px 0;
            border-bottom: 1px solid #dee2e6;
        }
        
        .config-item:last-child {
            border-bottom: none;
        }
        
        .config-label {
            font-weight: bold;
            color: #667eea;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
        }
        
        .form-control {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 10px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
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
            transition: all 0.3s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        
        .alert {
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        
        .instructions {
            background: #fff3cd;
            padding: 20px;
            border-radius: 10px;
            border-left: 4px solid #ffc107;
            margin-top: 30px;
        }
        
        .instructions h3 {
            color: #856404;
            margin-bottom: 15px;
        }
        
        .instructions ol {
            margin-left: 20px;
            color: #856404;
        }
        
        .instructions li {
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-envelope-circle-check"></i> Test SMTP Configuration</h1>
            <p>Verify your email settings for 2FA</p>
        </div>
        
        <div class="config-display">
            <h3>Current SMTP Configuration:</h3>
            <div class="config-item">
                <span class="config-label">SMTP Host:</span> <?php echo SMTP_HOST; ?>
            </div>
            <div class="config-item">
                <span class="config-label">SMTP Port:</span> <?php echo SMTP_PORT; ?>
            </div>
            <div class="config-item">
                <span class="config-label">Username:</span> <?php echo SMTP_USERNAME; ?>
            </div>
            <div class="config-item">
                <span class="config-label">From Email:</span> <?php echo SMTP_FROM_EMAIL; ?>
            </div>
            <div class="config-item">
                <span class="config-label">From Name:</span> <?php echo SMTP_FROM_NAME; ?>
            </div>
        </div>
        
        <?php if ($test_result): ?>
            <div class="alert <?php echo $test_success ? 'alert-success' : 'alert-error'; ?>">
                <?php echo htmlspecialchars($test_result); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label class="form-label" for="test_email">
                    <i class="fas fa-envelope"></i> Test Email Address
                </label>
                <input 
                    type="email" 
                    class="form-control" 
                    id="test_email" 
                    name="test_email" 
                    placeholder="your-email@example.com"
                    required
                    value="<?php echo htmlspecialchars($test_email); ?>"
                >
            </div>
            
            <button type="submit" class="btn">
                <i class="fas fa-paper-plane"></i> Send Test Email
            </button>
        </form>
        
        <div class="instructions">
            <h3><i class="fas fa-lightbulb"></i> SMTP Configuration Instructions:</h3>
            <ol>
                <li>Edit <code>phpmailer_config.php</code> with your SMTP credentials</li>
                <li>For Gmail: Use App Password (not your regular password)</li>
                <li>Generate App Password: Google Account → Security → 2-Step Verification → App passwords</li>
                <li>Update SMTP_USERNAME and SMTP_PASSWORD in the config file</li>
                <li>Return here and test with your email address</li>
            </ol>
        </div>
    </div>
</body>
</html>
