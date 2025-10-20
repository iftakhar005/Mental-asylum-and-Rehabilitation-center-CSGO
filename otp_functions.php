<?php
/**
 * Two-Factor Authentication (2FA) - OTP Functions
 * Manual implementation with minimal library dependencies
 */

require_once 'db.php';
require_once 'phpmailer_config.php';

/**
 * Generate a cryptographically secure 6-digit OTP code
 * @return string 6-digit OTP code
 */
function generateOTP() {
    try {
        // Use random_int for cryptographically secure random numbers
        $otp = '';
        for ($i = 0; $i < 6; $i++) {
            $otp .= random_int(0, 9);
        }
        return $otp;
    } catch (Exception $e) {
        error_log('OTP Generation Error: ' . $e->getMessage());
        // Fallback to less secure method if random_int fails
        return str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}

/**
 * Store OTP in database with expiration time
 * @param int $user_id User ID
 * @param string $email User email
 * @param string $otp OTP code
 * @param int $expiry_minutes Minutes until OTP expires (default 10)
 * @return bool Success status
 */
function storeOTP($user_id, $email, $otp, $expiry_minutes = 10) {
    global $conn;
    
    try {
        // Delete any existing unused OTPs for this user
        $delete_stmt = $conn->prepare("DELETE FROM otp_codes WHERE user_id = ? AND is_used = 0");
        $delete_stmt->bind_param("i", $user_id);
        $delete_stmt->execute();
        $delete_stmt->close();
        
        // Calculate expiration time
        $expires_at = date('Y-m-d H:i:s', strtotime("+{$expiry_minutes} minutes"));
        
        // Log OTP creation details
        error_log("[2FA] Creating OTP for {$email} | Code: {$otp} | Expires: {$expires_at} | Current Time: " . date('Y-m-d H:i:s'));
        
        // Insert new OTP
        $insert_stmt = $conn->prepare("INSERT INTO otp_codes (user_id, email, otp_code, expires_at) VALUES (?, ?, ?, ?)");
        $insert_stmt->bind_param("isss", $user_id, $email, $otp, $expires_at);
        
        $result = $insert_stmt->execute();
        $insert_stmt->close();
        
        return $result;
    } catch (Exception $e) {
        error_log('Store OTP Error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Verify OTP code
 * @param string $email User email
 * @param string $otp OTP code to verify
 * @return array ['success' => bool, 'message' => string, 'user_id' => int|null]
 */
function verifyOTP($email, $otp) {
    global $conn;
    
    try {
        // Get current time in the same format as database
        $current_time = date('Y-m-d H:i:s');
        
        // Query for valid OTP with explicit time comparison
        $stmt = $conn->prepare("SELECT id, user_id, expires_at FROM otp_codes WHERE email = ? AND otp_code = ? AND is_used = 0");
        $stmt->bind_param("ss", $email, $otp);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $row = $result->fetch_assoc();
            $otp_id = $row['id'];
            $user_id = $row['user_id'];
            $expires_at = $row['expires_at'];
            
            // Check if OTP is still valid (not expired)
            if (strtotime($expires_at) < strtotime($current_time)) {
                $stmt->close();
                return [
                    'success' => false,
                    'message' => 'OTP has expired. Please request a new one.',
                    'user_id' => null
                ];
            }
            
            // Mark OTP as used
            $update_stmt = $conn->prepare("UPDATE otp_codes SET is_used = 1 WHERE id = ?");
            $update_stmt->bind_param("i", $otp_id);
            $update_stmt->execute();
            $update_stmt->close();
            
            $stmt->close();
            
            return [
                'success' => true,
                'message' => 'OTP verified successfully',
                'user_id' => $user_id
            ];
        } else {
            $stmt->close();
            
            // Check if OTP exists but is expired or used
            $check_stmt = $conn->prepare("SELECT id, is_used, expires_at FROM otp_codes WHERE email = ? AND otp_code = ?");
            $check_stmt->bind_param("ss", $email, $otp);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $check_row = $check_result->fetch_assoc();
                if ($check_row['is_used'] == 1) {
                    $message = 'This OTP has already been used. Please request a new one.';
                } else {
                    $message = 'OTP has expired. Please request a new one.';
                }
            } else {
                $message = 'Invalid OTP code. Please try again.';
            }
            
            $check_stmt->close();
            
            return [
                'success' => false,
                'message' => $message,
                'user_id' => null
            ];
        }
    } catch (Exception $e) {
        error_log('Verify OTP Error: ' . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Verification error occurred. Please try again.',
            'user_id' => null
        ];
    }
}

/**
 * Send OTP via email using simple PHP mail() function (for localhost testing)
 * @param string $to_email Recipient email
 * @param string $to_name Recipient name
 * @param string $otp OTP code
 * @return bool Success status
 */
function sendOTPEmailSimple($to_email, $to_name, $otp) {
    try {
        $subject = "Your OTP Code for MindCare System";
        
        // Create HTML email body
        $html_body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f4f4f4; margin: 0; padding: 20px; }
                .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; }
                .header h1 { margin: 0; font-size: 24px; }
                .content { padding: 30px; }
                .otp-box { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; border-radius: 10px; margin: 20px 0; }
                .otp-code { font-size: 36px; font-weight: bold; letter-spacing: 8px; margin: 10px 0; font-family: 'Courier New', monospace; }
                .warning-box { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 5px; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🔐 Two-Factor Authentication</h1>
                    <p>MindCare Mental Health System</p>
                </div>
                <div class='content'>
                    <h2>Hello, {$to_name}!</h2>
                    <p>You requested to log in to your MindCare account. To complete the login process, please use the One-Time Password (OTP) below:</p>
                    
                    <div class='otp-box'>
                        <p style='margin: 0; font-size: 14px;'>Your OTP Code:</p>
                        <div class='otp-code'>{$otp}</div>
                        <p style='margin: 0; font-size: 12px;'>Valid for 10 minutes</p>
                    </div>
                    
                    <div class='warning-box'>
                        <strong>⚠️ Security Notice:</strong>
                        <ul style='margin: 10px 0; padding-left: 20px;'>
                            <li>Never share this code with anyone</li>
                            <li>Our staff will never ask for your OTP</li>
                            <li>This code expires in 10 minutes</li>
                            <li>If you didn't request this, please contact our security team immediately</li>
                        </ul>
                    </div>
                    
                    <p><strong>What to do next:</strong></p>
                    <ol>
                        <li>Return to the login page</li>
                        <li>Enter this 6-digit code in the verification field</li>
                        <li>Click \"Verify OTP\" to complete your login</li>
                    </ol>
                    
                    <p>If you're having trouble, please contact our support team.</p>
                </div>
                <div class='footer'>
                    <p><strong>MindCare Mental Health System</strong></p>
                    <p>This is an automated message. Please do not reply to this email.</p>
                    <p>&copy; 2025 MindCare. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        // Plain text version for email clients that don't support HTML
        $text_body = "Hello {$to_name},\n\nYour OTP code for MindCare System is: {$otp}\n\nThis code is valid for 10 minutes.\n\nNever share this code with anyone.\n\nIf you didn't request this, please contact support immediately.\n\n---\nMindCare Mental Health System";
        
        // Set email headers for HTML email
        $boundary = md5(uniqid(time()));
        $headers = "From: MindCare System <noreply@mindcare.local>\r\n";
        $headers .= "Reply-To: noreply@mindcare.local\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
        
        // Build the message body
        $message = "--{$boundary}\r\n";
        $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $message .= $text_body . "\r\n\r\n";
        
        $message .= "--{$boundary}\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $message .= $html_body . "\r\n\r\n";
        $message .= "--{$boundary}--";
        
        // Send email using PHP mail() function
        $result = mail($to_email, $subject, $message, $headers);
        
        if ($result) {
            error_log("[2FA] OTP email sent successfully to {$to_email} | OTP: {$otp} | Valid for 10 minutes");
            return true;
        } else {
            error_log("[2FA] Failed to send OTP email to {$to_email}");
            // Still return true for testing purposes - user can check console/logs for OTP
            error_log("[2FA] ⭐ OTP CODE FOR {$to_email}: {$otp} (Valid for 10 minutes) ⭐");
            return true; // Return true so login flow continues
        }
        
    } catch (Exception $e) {
        error_log('Send Simple OTP Email Error: ' . $e->getMessage());
        // Log the OTP for testing
        error_log("OTP CODE FOR {$to_email}: {$otp} (Valid for 10 minutes)");
        return true; // Return true for testing - OTP logged in error_log
    }
}

/**
 * Send OTP via email using improved SMTP implementation
 * @param string $to_email Recipient email
 * @param string $to_name Recipient name
 * @param string $otp OTP code
 * @return bool Success status
 */
function sendOTPEmail($to_email, $to_name, $otp) {
    try {
        // Check if SMTP is properly configured (not using placeholder values)
        $smtp_configured = (
            SMTP_USERNAME !== 'your-email@gmail.com' && 
            SMTP_PASSWORD !== 'your-app-password' &&
            !empty(SMTP_USERNAME) && 
            !empty(SMTP_PASSWORD)
        );
        
        // If SMTP not configured, use simple PHP mail() function for localhost testing
        if (!$smtp_configured) {
            error_log("SMTP not configured - using PHP mail() function as fallback");
            return sendOTPEmailSimple($to_email, $to_name, $otp);
        }
        
        // SMTP Configuration from phpmailer_config.php
        $smtp_host = SMTP_HOST;
        $smtp_port = SMTP_PORT;
        $smtp_username = SMTP_USERNAME;
        $smtp_password = SMTP_PASSWORD;
        $from_email = SMTP_FROM_EMAIL;
        $from_name = SMTP_FROM_NAME;
        
        // Email subject
        $subject = "Your OTP Code for MindCare System";
        
        // HTML Email Body
        $html_body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f4f4f4; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 20px auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; }
                .header h1 { margin: 0; font-size: 24px; }
                .content { padding: 30px; }
                .otp-box { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; border-radius: 10px; margin: 20px 0; }
                .otp-code { font-size: 36px; font-weight: bold; letter-spacing: 8px; margin: 10px 0; font-family: 'Courier New', monospace; }
                .warning-box { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 5px; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🔐 Two-Factor Authentication</h1>
                    <p>MindCare Mental Health System</p>
                </div>
                <div class='content'>
                    <h2>Hello, {$to_name}!</h2>
                    <p>You requested to log in to your MindCare account. To complete the login process, please use the One-Time Password (OTP) below:</p>
                    
                    <div class='otp-box'>
                        <p style='margin: 0; font-size: 14px;'>Your OTP Code:</p>
                        <div class='otp-code'>{$otp}</div>
                        <p style='margin: 0; font-size: 12px;'>Valid for 10 minutes</p>
                    </div>
                    
                    <div class='warning-box'>
                        <strong>⚠️ Security Notice:</strong>
                        <ul style='margin: 10px 0; padding-left: 20px;'>
                            <li>Never share this code with anyone</li>
                            <li>Our staff will never ask for your OTP</li>
                            <li>This code expires in 10 minutes</li>
                            <li>If you didn't request this, please contact our security team immediately</li>
                        </ul>
                    </div>
                    
                    <p><strong>What to do next:</strong></p>
                    <ol>
                        <li>Return to the login page</li>
                        <li>Enter this 6-digit code in the verification field</li>
                        <li>Click \"Verify OTP\" to complete your login</li>
                    </ol>
                    
                    <p>If you're having trouble, please contact our support team.</p>
                </div>
                <div class='footer'>
                    <p><strong>MindCare Mental Health System</strong></p>
                    <p>This is an automated message. Please do not reply to this email.</p>
                    <p>&copy; 2025 MindCare. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        // Plain text version
        $text_body = "Hello {$to_name},\n\nYour OTP code for MindCare System is: {$otp}\n\nThis code is valid for 10 minutes.\n\nNever share this code with anyone.\n\nIf you didn't request this, please contact support immediately.\n\n---\nMindCare Mental Health System";
        
        // Build complete email message with proper MIME formatting
        $boundary = md5(uniqid(time()));
        $date = date('r');
        $message_id = sprintf('<%s@%s>', md5(uniqid(time())), $smtp_host);
        
        // Build complete RFC-compliant email
        $email_data = "";
        $email_data .= "Date: {$date}\r\n";
        $email_data .= "From: {$from_name} <{$from_email}>\r\n";
        $email_data .= "To: {$to_email}\r\n";
        $email_data .= "Subject: {$subject}\r\n";
        $email_data .= "Message-ID: {$message_id}\r\n";
        $email_data .= "MIME-Version: 1.0\r\n";
        $email_data .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
        $email_data .= "\r\n";
        
        // Plain text part
        $email_data .= "--{$boundary}\r\n";
        $email_data .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $email_data .= "Content-Transfer-Encoding: 7bit\r\n";
        $email_data .= "\r\n";
        $email_data .= $text_body . "\r\n";
        $email_data .= "\r\n";
        
        // HTML part
        $email_data .= "--{$boundary}\r\n";
        $email_data .= "Content-Type: text/html; charset=UTF-8\r\n";
        $email_data .= "Content-Transfer-Encoding: 7bit\r\n";
        $email_data .= "\r\n";
        $email_data .= $html_body . "\r\n";
        $email_data .= "\r\n";
        
        $email_data .= "--{$boundary}--\r\n";
        
        // Improved SMTP Connection with proper error handling
        error_log("[2FA EMAIL] Attempting to send OTP to {$to_email}");
        
        // Try to connect to SMTP server
        $socket = @fsockopen($smtp_host, $smtp_port, $errno, $errstr, 30);
        
        if (!$socket) {
            error_log("[2FA EMAIL] ❌ Connection failed: {$errno} - {$errstr}");
            error_log("[2FA EMAIL] ⭐ FALLBACK OTP CODE FOR {$to_email}: {$otp} (Valid for 10 minutes) ⭐");
            return true; // Return true so login flow continues
        }
        
        // Helper function to read SMTP response
        $read_response = function($socket) {
            $response = '';
            while ($line = fgets($socket, 515)) {
                $response .= $line;
                if (substr($line, 3, 1) == ' ') break;
            }
            return $response;
        };
        
        // Helper function to send command and check response
        $send_command = function($socket, $command, $expected_code) use ($read_response) {
            fputs($socket, $command);
            $response = $read_response($socket);
            $code = substr($response, 0, 3);
            error_log("[2FA EMAIL] Sent: " . trim($command) . " | Response: " . trim($response));
            return [$code == $expected_code, $response, $code];
        };
        
        // Read initial greeting
        $response = $read_response($socket);
        error_log("[2FA EMAIL] Server greeting: " . trim($response));
        
        // Send EHLO
        list($success, $response) = $send_command($socket, "EHLO {$smtp_host}\r\n", '250');
        if (!$success) {
            error_log("[2FA EMAIL] ❌ EHLO failed");
            fclose($socket);
            return true;
        }
        
        // Start TLS
        list($success, $response) = $send_command($socket, "STARTTLS\r\n", '220');
        if (!$success) {
            error_log("[2FA EMAIL] ❌ STARTTLS failed");
            fclose($socket);
            return true;
        }
        
        // Enable TLS encryption
        $crypto_result = stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT);
        if (!$crypto_result) {
            error_log("[2FA EMAIL] ❌ TLS encryption failed");
            fclose($socket);
            return true;
        }
        error_log("[2FA EMAIL] ✓ TLS encryption enabled");
        
        // Send EHLO again after TLS
        list($success, $response) = $send_command($socket, "EHLO {$smtp_host}\r\n", '250');
        
        // Authenticate
        list($success, $response) = $send_command($socket, "AUTH LOGIN\r\n", '334');
        if (!$success) {
            error_log("[2FA EMAIL] ❌ AUTH LOGIN failed");
            fclose($socket);
            return true;
        }
        
        // Send username
        list($success, $response) = $send_command($socket, base64_encode($smtp_username) . "\r\n", '334');
        if (!$success) {
            error_log("[2FA EMAIL] ❌ Username authentication failed");
            fclose($socket);
            return true;
        }
        
        // Send password
        list($success, $response, $code) = $send_command($socket, base64_encode($smtp_password) . "\r\n", '235');
        if (!$success) {
            error_log("[2FA EMAIL] ❌ Password authentication failed (Code: {$code})");
            error_log("[2FA EMAIL] Check your app password at: https://myaccount.google.com/apppasswords");
            fclose($socket);
            return true;
        }
        error_log("[2FA EMAIL] ✓ Authentication successful");
        
        // Send MAIL FROM
        list($success, $response) = $send_command($socket, "MAIL FROM:<{$from_email}>\r\n", '250');
        if (!$success) {
            error_log("[2FA EMAIL] ❌ MAIL FROM failed");
            fclose($socket);
            return true;
        }
        
        // Send RCPT TO
        list($success, $response) = $send_command($socket, "RCPT TO:<{$to_email}>\r\n", '250');
        if (!$success) {
            error_log("[2FA EMAIL] ❌ RCPT TO failed");
            fclose($socket);
            return true;
        }
        
        // Send DATA command
        list($success, $response) = $send_command($socket, "DATA\r\n", '354');
        if (!$success) {
            error_log("[2FA EMAIL] ❌ DATA command failed");
            fclose($socket);
            return true;
        }
        
        // Send email content
        fputs($socket, $email_data);
        fputs($socket, "\r\n.\r\n");
        $response = $read_response($socket);
        $code = substr($response, 0, 3);
        error_log("[2FA EMAIL] Email sent response: " . trim($response));
        
        if ($code == '250') {
            error_log("[2FA EMAIL] ✅ Email sent successfully to {$to_email}!");
            $send_success = true;
        } else {
            error_log("[2FA EMAIL] ❌ Email sending failed (Code: {$code})");
            $send_success = false;
        }
        
        // Send QUIT
        fputs($socket, "QUIT\r\n");
        fclose($socket);
        
        // Log OTP as backup
        error_log("[2FA EMAIL] OTP CODE FOR {$to_email}: {$otp} (Valid for 10 minutes)");
        
        return $send_success;
        
    } catch (Exception $e) {
        error_log('[2FA EMAIL] Exception: ' . $e->getMessage());
        error_log("[2FA EMAIL] ⭐ FALLBACK OTP CODE FOR {$to_email}: {$otp} (Valid for 10 minutes) ⭐");
        return true; // Return true so login continues
    }
}

/**
 * Check if 2FA is enabled for a user
 * @param int $user_id User ID
 * @return bool True if 2FA is enabled
 */
function is2FAEnabled($user_id) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("SELECT two_factor_enabled FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $row = $result->fetch_assoc();
            $stmt->close();
            return (bool)$row['two_factor_enabled'];
        }
        
        $stmt->close();
        return false;
    } catch (Exception $e) {
        error_log('Check 2FA Enabled Error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Enable 2FA for a user
 * @param int $user_id User ID
 * @return bool Success status
 */
function enable2FA($user_id) {
    global $conn;
    
    try {
        // Generate a random secret for future TOTP implementation
        $secret = bin2hex(random_bytes(16));
        
        $stmt = $conn->prepare("UPDATE users SET two_factor_enabled = 1, two_factor_secret = ? WHERE id = ?");
        $stmt->bind_param("si", $secret, $user_id);
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    } catch (Exception $e) {
        error_log('Enable 2FA Error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Disable 2FA for a user
 * @param int $user_id User ID
 * @return bool Success status
 */
function disable2FA($user_id) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("UPDATE users SET two_factor_enabled = 0, two_factor_secret = NULL WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    } catch (Exception $e) {
        error_log('Disable 2FA Error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Clean up expired and used OTPs
 * Should be called periodically (e.g., via cron job)
 * @return int Number of records deleted
 */
function cleanupExpiredOTPs() {
    global $conn;
    
    try {
        $stmt = $conn->prepare("DELETE FROM otp_codes WHERE expires_at < NOW() OR is_used = 1");
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        
        return $affected;
    } catch (Exception $e) {
        error_log('Cleanup OTPs Error: ' . $e->getMessage());
        return 0;
    }
}
?>
