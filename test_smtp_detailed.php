<?php
/**
 * Detailed SMTP Test - Diagnose Email Issues
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'phpmailer_config.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMTP Diagnostic Test</title>
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
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        h1 {
            color: #667eea;
            margin-bottom: 20px;
            text-align: center;
        }
        .test-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin: 15px 0;
        }
        .test-item {
            display: flex;
            align-items: center;
            padding: 12px;
            margin: 8px 0;
            background: white;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }
        .test-item i {
            margin-right: 15px;
            font-size: 20px;
        }
        .success {
            border-left-color: #28a745;
            color: #28a745;
        }
        .error {
            border-left-color: #dc3545;
            color: #dc3545;
        }
        .warning {
            border-left-color: #ffc107;
            color: #856404;
        }
        .info {
            border-left-color: #17a2b8;
            color: #0c5460;
        }
        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            margin: 10px 0;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        pre {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 15px;
            border-radius: 8px;
            overflow-x: auto;
            font-size: 12px;
        }
        .code-label {
            font-weight: 600;
            color: #555;
            margin-top: 15px;
            margin-bottom: 5px;
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-diagnoses"></i> SMTP Diagnostic Test</h1>

        <div class="test-section">
            <h3 style="margin-bottom: 15px;">📧 Configuration Check</h3>
            
            <div class="test-item <?php echo (SMTP_HOST === 'smtp.gmail.com') ? 'success' : 'error'; ?>">
                <i class="fas fa-<?php echo (SMTP_HOST === 'smtp.gmail.com') ? 'check-circle' : 'times-circle'; ?>"></i>
                <div>
                    <strong>SMTP Host:</strong> <?php echo SMTP_HOST; ?>
                    <?php echo (SMTP_HOST === 'smtp.gmail.com') ? '✓' : '✗ Should be smtp.gmail.com'; ?>
                </div>
            </div>

            <div class="test-item <?php echo (SMTP_PORT == 587) ? 'success' : 'warning'; ?>">
                <i class="fas fa-<?php echo (SMTP_PORT == 587) ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                <div>
                    <strong>SMTP Port:</strong> <?php echo SMTP_PORT; ?>
                    <?php echo (SMTP_PORT == 587) ? '✓ (TLS)' : '(Try 587 for TLS or 465 for SSL)'; ?>
                </div>
            </div>

            <div class="test-item <?php echo (SMTP_USERNAME === 'iftakharmajumder@gmail.com') ? 'success' : 'error'; ?>">
                <i class="fas fa-<?php echo (SMTP_USERNAME === 'iftakharmajumder@gmail.com') ? 'check-circle' : 'times-circle'; ?>"></i>
                <div>
                    <strong>SMTP Username:</strong> <?php echo SMTP_USERNAME; ?>
                </div>
            </div>

            <div class="test-item <?php echo (strlen(SMTP_PASSWORD) == 16) ? 'success' : 'error'; ?>">
                <i class="fas fa-<?php echo (strlen(SMTP_PASSWORD) == 16) ? 'check-circle' : 'times-circle'; ?>"></i>
                <div>
                    <strong>SMTP Password:</strong> <?php echo strlen(SMTP_PASSWORD); ?> characters
                    <?php echo (strlen(SMTP_PASSWORD) == 16) ? '✓' : '✗ Should be 16 characters'; ?>
                </div>
            </div>

            <div class="test-item info">
                <i class="fas fa-info-circle"></i>
                <div>
                    <strong>From Email:</strong> <?php echo SMTP_FROM_EMAIL; ?>
                </div>
            </div>
        </div>

        <form method="POST">
            <button type="submit" name="test_connection" class="btn">
                <i class="fas fa-bolt"></i> Test SMTP Connection
            </button>
        </form>

        <?php
        if (isset($_POST['test_connection'])) {
            echo '<div class="test-section">';
            echo '<h3 style="margin-bottom: 15px;">🔍 Connection Test Results</h3>';
            
            // Test 1: Socket Connection
            echo '<div class="code-label">Test 1: Connecting to SMTP server...</div>';
            $context = stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ]);
            
            $socket = @stream_socket_client(
                "tcp://" . SMTP_HOST . ":" . SMTP_PORT,
                $errno,
                $errstr,
                10,
                STREAM_CLIENT_CONNECT,
                $context
            );
            
            if ($socket) {
                echo '<div class="test-item success">';
                echo '<i class="fas fa-check-circle"></i>';
                echo '<div><strong>✓ Connection Successful!</strong> Connected to ' . SMTP_HOST . ':' . SMTP_PORT . '</div>';
                echo '</div>';
                
                // Read server greeting
                $response = fgets($socket, 515);
                echo '<pre>Server Response: ' . htmlspecialchars($response) . '</pre>';
                
                // Send EHLO
                echo '<div class="code-label">Test 2: EHLO command...</div>';
                fputs($socket, "EHLO " . SMTP_HOST . "\r\n");
                $response = '';
                while ($str = fgets($socket, 515)) {
                    $response .= $str;
                    if (substr($str, 3, 1) === " ") break;
                }
                echo '<pre>EHLO Response:\n' . htmlspecialchars($response) . '</pre>';
                
                // Test STARTTLS
                echo '<div class="code-label">Test 3: STARTTLS command...</div>';
                fputs($socket, "STARTTLS\r\n");
                $response = fgets($socket, 515);
                echo '<pre>STARTTLS Response: ' . htmlspecialchars($response) . '</pre>';
                
                if (substr($response, 0, 3) == '220') {
                    echo '<div class="test-item success">';
                    echo '<i class="fas fa-check-circle"></i>';
                    echo '<div><strong>✓ STARTTLS Ready!</strong></div>';
                    echo '</div>';
                    
                    // Enable encryption
                    echo '<div class="code-label">Test 4: Enabling TLS encryption...</div>';
                    $crypto = @stream_socket_enable_crypto(
                        $socket,
                        true,
                        STREAM_CRYPTO_METHOD_TLS_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT
                    );
                    
                    if ($crypto) {
                        echo '<div class="test-item success">';
                        echo '<i class="fas fa-lock"></i>';
                        echo '<div><strong>✓ TLS Encryption Enabled!</strong></div>';
                        echo '</div>';
                        
                        // EHLO again after TLS
                        fputs($socket, "EHLO " . SMTP_HOST . "\r\n");
                        $response = '';
                        while ($str = fgets($socket, 515)) {
                            $response .= $str;
                            if (substr($str, 3, 1) === " ") break;
                        }
                        
                        // Test AUTH
                        echo '<div class="code-label">Test 5: Authentication...</div>';
                        fputs($socket, "AUTH LOGIN\r\n");
                        $response = fgets($socket, 515);
                        echo '<pre>AUTH Response: ' . htmlspecialchars($response) . '</pre>';
                        
                        if (substr($response, 0, 3) == '334') {
                            fputs($socket, base64_encode(SMTP_USERNAME) . "\r\n");
                            $response = fgets($socket, 515);
                            echo '<pre>Username Response: ' . htmlspecialchars($response) . '</pre>';
                            
                            fputs($socket, base64_encode(SMTP_PASSWORD) . "\r\n");
                            $response = fgets($socket, 515);
                            echo '<pre>Password Response: ' . htmlspecialchars($response) . '</pre>';
                            
                            if (substr($response, 0, 3) == '235') {
                                echo '<div class="test-item success">';
                                echo '<i class="fas fa-check-circle"></i>';
                                echo '<div><strong>✓✓✓ AUTHENTICATION SUCCESSFUL! ✓✓✓</strong><br>';
                                echo 'Your Gmail SMTP is working perfectly!</div>';
                                echo '</div>';
                                
                                echo '<div class="test-item success" style="background: #d4edda; border: 2px solid #28a745; margin-top: 20px;">';
                                echo '<div style="text-align: center; width: 100%;">';
                                echo '<h3 style="color: #155724; margin-bottom: 10px;">🎉 EMAIL SENDING WILL WORK! 🎉</h3>';
                                echo '<p style="color: #155724;">Your 2FA system is now configured correctly.<br>';
                                echo 'OTP emails will be sent to users\' inboxes!</p>';
                                echo '</div>';
                                echo '</div>';
                            } else {
                                echo '<div class="test-item error">';
                                echo '<i class="fas fa-times-circle"></i>';
                                echo '<div><strong>✗ Authentication Failed!</strong><br>';
                                echo 'Please check:<br>';
                                echo '1. App Password is correct (16 characters, no spaces)<br>';
                                echo '2. 2-Step Verification is enabled on Google account<br>';
                                echo '3. Try generating a new App Password</div>';
                                echo '</div>';
                            }
                        }
                    } else {
                        echo '<div class="test-item error">';
                        echo '<i class="fas fa-times-circle"></i>';
                        echo '<div><strong>✗ TLS Encryption Failed!</strong><br>';
                        echo 'OpenSSL might not be enabled in PHP.</div>';
                        echo '</div>';
                    }
                } else {
                    echo '<div class="test-item error">';
                    echo '<i class="fas fa-times-circle"></i>';
                    echo '<div><strong>✗ STARTTLS Not Supported!</strong></div>';
                    echo '</div>';
                }
                
                fclose($socket);
            } else {
                echo '<div class="test-item error">';
                echo '<i class="fas fa-times-circle"></i>';
                echo '<div><strong>✗ Connection Failed!</strong><br>';
                echo 'Error ' . $errno . ': ' . htmlspecialchars($errstr) . '<br>';
                echo 'Check:<br>';
                echo '1. Internet connection<br>';
                echo '2. Firewall settings<br>';
                echo '3. SMTP host and port</div>';
                echo '</div>';
            }
            
            echo '</div>';
        }
        ?>

        <div class="test-section" style="margin-top: 30px;">
            <h3 style="margin-bottom: 15px;"><i class="fas fa-lightbulb"></i> Troubleshooting Tips</h3>
            
            <div class="test-item warning">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <strong>If authentication fails:</strong><br>
                    1. Make sure 2-Step Verification is ON in your Google account<br>
                    2. Generate a NEW App Password at: <a href="https://myaccount.google.com/apppasswords" target="_blank">Google App Passwords</a><br>
                    3. Remove ALL spaces from the App Password<br>
                    4. Make sure the password is exactly 16 characters
                </div>
            </div>

            <div class="test-item info">
                <i class="fas fa-info-circle"></i>
                <div>
                    <strong>Common Issues:</strong><br>
                    • "Authentication failed" = Wrong App Password<br>
                    • "Connection timeout" = Firewall blocking port 587<br>
                    • "TLS failed" = OpenSSL not enabled in PHP<br>
                    • "Invalid credentials" = 2-Step Verification not enabled
                </div>
            </div>
        </div>
    </div>
</body>
</html>
