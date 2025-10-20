<?php
/**
 * OTP Debug Tool - Check OTP Status and Timing Issues
 */
require_once 'db.php';

// Get email from URL parameter
$email = isset($_GET['email']) ? trim($_GET['email']) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OTP Debug Tool</title>
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

        .search-form {
            background: #f8f9ff;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }

        .search-form input {
            width: 70%;
            padding: 12px;
            border: 2px solid #667eea;
            border-radius: 8px;
            font-size: 16px;
        }

        .search-form button {
            width: 28%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            margin-left: 2%;
        }

        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }

        .debug-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .debug-section h3 {
            color: #333;
            margin-bottom: 15px;
        }

        .debug-item {
            display: flex;
            justify-content: space-between;
            padding: 10px;
            margin: 5px 0;
            background: white;
            border-radius: 5px;
            border-left: 3px solid #667eea;
        }

        .debug-label {
            font-weight: 600;
            color: #555;
        }

        .debug-value {
            font-family: 'Courier New', monospace;
            color: #667eea;
        }

        .otp-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 15px;
            margin: 15px 0;
        }

        .otp-code-display {
            font-size: 48px;
            font-weight: bold;
            letter-spacing: 10px;
            text-align: center;
            margin: 20px 0;
            font-family: 'Courier New', monospace;
        }

        .status-badge {
            display: inline-block;
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }

        .status-valid {
            background: #28a745;
        }

        .status-expired {
            background: #dc3545;
        }

        .status-used {
            background: #6c757d;
        }

        .copy-btn {
            background: white;
            color: #667eea;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 15px;
        }

        .alert {
            padding: 15px;
            margin: 15px 0;
            border-radius: 8px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border-left: 4px solid #ffc107;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-bug"></i> OTP Debug Tool</h1>

        <div class="search-form">
            <form method="GET">
                <input type="email" name="email" placeholder="Enter email to check OTP status" 
                       value="<?php echo htmlspecialchars($email); ?>" required>
                <button type="submit"><i class="fas fa-search"></i> Check OTP</button>
            </form>
        </div>

        <div class="debug-section">
            <h3><i class="fas fa-clock"></i> Server Time Information</h3>
            <div class="debug-item">
                <span class="debug-label">PHP Current Time:</span>
                <span class="debug-value"><?php echo date('Y-m-d H:i:s'); ?></span>
            </div>
            <div class="debug-item">
                <span class="debug-label">PHP Timezone:</span>
                <span class="debug-value"><?php echo date_default_timezone_get(); ?></span>
            </div>
            <div class="debug-item">
                <span class="debug-label">MySQL NOW():</span>
                <span class="debug-value">
                    <?php
                    $now_result = $conn->query("SELECT NOW() as current_time");
                    if ($now_result) {
                        $now_row = $now_result->fetch_assoc();
                        echo $now_row['current_time'];
                    }
                    ?>
                </span>
            </div>
        </div>

        <?php if (!empty($email)): ?>
            <div class="debug-section">
                <h3><i class="fas fa-key"></i> OTP Status for: <?php echo htmlspecialchars($email); ?></h3>
                
                <?php
                $stmt = $conn->prepare("SELECT id, otp_code, created_at, expires_at, is_used FROM otp_codes WHERE email = ? ORDER BY created_at DESC LIMIT 5");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0):
                    while ($row = $result->fetch_assoc()):
                        $current_time = time();
                        $expires_time = strtotime($row['expires_at']);
                        $created_time = strtotime($row['created_at']);
                        
                        $is_expired = $current_time > $expires_time;
                        $is_used = $row['is_used'] == 1;
                        
                        $status = 'Valid';
                        $status_class = 'status-valid';
                        $alert_class = 'alert-success';
                        
                        if ($is_used) {
                            $status = 'Used';
                            $status_class = 'status-used';
                            $alert_class = 'alert-warning';
                        } elseif ($is_expired) {
                            $status = 'Expired';
                            $status_class = 'status-expired';
                            $alert_class = 'alert-danger';
                        }
                        
                        $time_diff = $expires_time - $current_time;
                        $minutes_left = floor($time_diff / 60);
                        $seconds_left = $time_diff % 60;
                ?>
                        <div class="otp-card">
                            <div class="otp-code-display"><?php echo htmlspecialchars($row['otp_code']); ?></div>
                            <div style="text-align: center;">
                                <span class="status-badge <?php echo $status_class; ?>"><?php echo $status; ?></span>
                            </div>
                            
                            <div class="alert <?php echo $alert_class; ?>" style="margin-top: 20px;">
                                <div class="debug-item" style="border: none; background: transparent; color: inherit;">
                                    <span class="debug-label">Created At:</span>
                                    <span class="debug-value" style="color: inherit;"><?php echo $row['created_at']; ?></span>
                                </div>
                                <div class="debug-item" style="border: none; background: transparent; color: inherit;">
                                    <span class="debug-label">Expires At:</span>
                                    <span class="debug-value" style="color: inherit;"><?php echo $row['expires_at']; ?></span>
                                </div>
                                <div class="debug-item" style="border: none; background: transparent; color: inherit;">
                                    <span class="debug-label">Current Time:</span>
                                    <span class="debug-value" style="color: inherit;"><?php echo date('Y-m-d H:i:s'); ?></span>
                                </div>
                                <div class="debug-item" style="border: none; background: transparent; color: inherit;">
                                    <span class="debug-label">Time Difference:</span>
                                    <span class="debug-value" style="color: inherit;">
                                        <?php 
                                        if ($is_expired) {
                                            echo "Expired " . abs($minutes_left) . " minutes ago";
                                        } else {
                                            echo "{$minutes_left} min {$seconds_left} sec remaining";
                                        }
                                        ?>
                                    </span>
                                </div>
                                <div class="debug-item" style="border: none; background: transparent; color: inherit;">
                                    <span class="debug-label">Unix Timestamps:</span>
                                    <span class="debug-value" style="color: inherit;">
                                        Current: <?php echo $current_time; ?> | Expires: <?php echo $expires_time; ?>
                                    </span>
                                </div>
                            </div>
                            
                            <?php if (!$is_expired && !$is_used): ?>
                                <button class="copy-btn" onclick="copyOTP('<?php echo htmlspecialchars($row['otp_code']); ?>')">
                                    <i class="fas fa-copy"></i> Copy OTP Code
                                </button>
                            <?php endif; ?>
                        </div>
                <?php
                    endwhile;
                else:
                ?>
                    <div class="alert alert-warning">
                        <strong>No OTP codes found for this email.</strong><br>
                        Try logging in with 2FA enabled to generate an OTP code.
                    </div>
                <?php
                endif;
                $stmt->close();
                ?>
            </div>
        <?php else: ?>
            <div class="info-box">
                <strong><i class="fas fa-info-circle"></i> How to use this tool:</strong>
                <ol style="margin: 10px 0 0 20px; line-height: 1.8;">
                    <li>Enter the email address you used to login</li>
                    <li>Click "Check OTP" to see the status</li>
                    <li>The tool will show if the OTP is Valid, Expired, or Used</li>
                    <li>You can see exact timing information to debug expiration issues</li>
                </ol>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function copyOTP(otp) {
            navigator.clipboard.writeText(otp).then(() => {
                alert('✅ OTP Code Copied!\n\nCode: ' + otp + '\n\nPaste it into the verification page.');
            });
        }
    </script>
</body>
</html>
