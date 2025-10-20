<?php
/**
 * PHPMailer Implementation - More Reliable Email Sending
 * Download PHPMailer from: https://github.com/PHPMailer/PHPMailer
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once 'phpmailer_config.php';

function sendOTPEmailWithPHPMailer($to_email, $to_name, $otp) {
    // Check if PHPMailer is installed
    if (!file_exists('PHPMailer/src/PHPMailer.php')) {
        error_log('[2FA] PHPMailer not found. Please install it.');
        return false;
    }
    
    require 'PHPMailer/src/Exception.php';
    require 'PHPMailer/src/PHPMailer.php';
    require 'PHPMailer/src/SMTP.php';
    
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        
        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to_email, $to_name);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Your OTP Code for MindCare System';
        
        $mail->Body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
        </head>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f4f4f4; margin: 0; padding: 0;'>
            <div style='max-width: 600px; margin: 20px auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1);'>
                <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center;'>
                    <h1 style='margin: 0; font-size: 24px;'>🔐 Two-Factor Authentication</h1>
                    <p style='margin: 10px 0 0 0;'>MindCare Mental Health System</p>
                </div>
                <div style='padding: 30px;'>
                    <h2>Hello, {$to_name}!</h2>
                    <p>You requested to log in to your MindCare account. To complete the login process, please use the One-Time Password (OTP) below:</p>
                    
                    <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; border-radius: 10px; margin: 20px 0;'>
                        <p style='margin: 0; font-size: 14px;'>Your OTP Code:</p>
                        <div style='font-size: 36px; font-weight: bold; letter-spacing: 8px; margin: 10px 0; font-family: \"Courier New\", monospace;'>{$otp}</div>
                        <p style='margin: 0; font-size: 12px;'>Valid for 10 minutes</p>
                    </div>
                    
                    <div style='background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 5px;'>
                        <strong>⚠️ Security Notice:</strong>
                        <ul style='margin: 10px 0; padding-left: 20px;'>
                            <li>Never share this code with anyone</li>
                            <li>Our staff will never ask for your OTP</li>
                            <li>This code expires in 10 minutes</li>
                        </ul>
                    </div>
                </div>
                <div style='background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #666;'>
                    <p><strong>MindCare Mental Health System</strong></p>
                    <p>&copy; 2025 MindCare. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $mail->AltBody = "Hello {$to_name},\n\nYour OTP code for MindCare System is: {$otp}\n\nThis code is valid for 10 minutes.\n\nNever share this code with anyone.";
        
        $mail->send();
        error_log("[2FA] ✓ Email sent successfully to {$to_email} via PHPMailer");
        return true;
        
    } catch (Exception $e) {
        error_log("[2FA] ✗ Email failed: {$mail->ErrorInfo}");
        error_log("[2FA] ⭐ OTP CODE FOR {$to_email}: {$otp} (Valid for 10 minutes) ⭐");
        return false;
    }
}
?>
