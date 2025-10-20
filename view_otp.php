<?php
/**
 * Simple OTP Viewer - Direct Display
 * Shows OTP codes directly without AJAX
 */
require_once 'db.php';

// Auto-refresh every 3 seconds
header("Refresh: 3");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OTP Codes - Live View</title>
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
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 32px;
            margin-bottom: 10px;
        }

        .auto-refresh-notice {
            background: #d4edda;
            border-left: 4px solid #28a745;
            padding: 15px 20px;
            margin: 20px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .auto-refresh-notice i {
            color: #28a745;
            font-size: 24px;
            animation: spin 2s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px 20px;
            margin: 20px;
            border-radius: 8px;
        }

        .content {
            padding: 30px;
        }

        .otp-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: transform 0.3s ease;
        }

        .otp-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }

        .otp-card.expired {
            background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
            opacity: 0.7;
        }

        .otp-card.used {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
            opacity: 0.7;
        }

        .otp-info {
            flex: 1;
        }

        .email {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .otp-code {
            font-size: 48px;
            font-weight: bold;
            letter-spacing: 12px;
            font-family: 'Courier New', monospace;
            margin: 15px 0;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
        }

        .time-info {
            font-size: 14px;
            opacity: 0.9;
            margin-top: 10px;
        }

        .copy-btn {
            background: white;
            color: #667eea;
            border: none;
            padding: 15px 30px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .copy-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }

        .status-badge {
            display: inline-block;
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            margin-top: 10px;
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

        .no-data {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .no-data i {
            font-size: 64px;
            color: #ccc;
            margin-bottom: 20px;
        }

        .countdown {
            font-size: 24px;
            font-weight: bold;
            color: #28a745;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-key"></i> OTP Code Viewer</h1>
            <p>Live View - Auto-refreshes every 3 seconds</p>
        </div>

        <div class="auto-refresh-notice">
            <i class="fas fa-sync-alt"></i>
            <div>
                <strong>Auto-Refresh Active!</strong> This page automatically refreshes every 3 seconds to show new OTP codes.
            </div>
        </div>

        <div class="warning">
            <strong><i class="fas fa-exclamation-triangle"></i> Development Only!</strong> 
            Remove this file before deploying to production. OTP codes are sensitive information.
        </div>

        <div class="content">
            <?php
            try {
                $sql = "SELECT email, otp_code, created_at, expires_at, is_used 
                        FROM otp_codes 
                        ORDER BY created_at DESC 
                        LIMIT 10";
                
                $result = $conn->query($sql);
                
                if ($result && $result->num_rows > 0) {
                    $count = 0;
                    while ($row = $result->fetch_assoc()) {
                        $now = new DateTime();
                        $expires = new DateTime($row['expires_at']);
                        $created = new DateTime($row['created_at']);
                        
                        $is_expired = $now > $expires;
                        $is_used = $row['is_used'] == 1;
                        
                        $card_class = 'otp-card';
                        $status_text = 'VALID';
                        $status_class = 'status-valid';
                        
                        if ($is_used) {
                            $card_class .= ' used';
                            $status_text = 'USED';
                            $status_class = 'status-used';
                        } elseif ($is_expired) {
                            $card_class .= ' expired';
                            $status_text = 'EXPIRED';
                            $status_class = 'status-expired';
                        }
                        
                        // Calculate time left
                        $interval = $now->diff($expires);
                        $time_left = '';
                        if (!$is_expired && !$is_used) {
                            $minutes = $interval->i;
                            $seconds = $interval->s;
                            $time_left = sprintf('%d minutes %d seconds', $minutes, $seconds);
                        }
                        
                        echo "<div class='{$card_class}'>";
                        echo "<div class='otp-info'>";
                        echo "<div class='email'><i class='fas fa-envelope'></i> " . htmlspecialchars($row['email']) . "</div>";
                        echo "<div class='otp-code'>" . htmlspecialchars($row['otp_code']) . "</div>";
                        echo "<div class='status-badge {$status_class}'>{$status_text}</div>";
                        echo "<div class='time-info'>";
                        echo "<i class='fas fa-clock'></i> Created: " . $created->format('M d, Y H:i:s') . "<br>";
                        echo "<i class='fas fa-hourglass-end'></i> Expires: " . $expires->format('M d, Y H:i:s');
                        if ($time_left) {
                            echo "<br><i class='fas fa-timer'></i> <span class='countdown'>Time left: {$time_left}</span>";
                        }
                        echo "</div>";
                        echo "</div>";
                        echo "<div>";
                        echo "<button class='copy-btn' onclick='copyToClipboard(\"" . htmlspecialchars($row['otp_code']) . "\")'>";
                        echo "<i class='fas fa-copy'></i> Copy Code";
                        echo "</button>";
                        echo "</div>";
                        echo "</div>";
                        
                        $count++;
                    }
                } else {
                    echo "<div class='no-data'>";
                    echo "<i class='fas fa-inbox'></i>";
                    echo "<h2>No OTP Codes Found</h2>";
                    echo "<p>Try logging in with a 2FA-enabled user account to generate OTP codes.</p>";
                    echo "<p style='margin-top: 20px;'><strong>Tip:</strong> Make sure you've created a user with the 2FA checkbox enabled.</p>";
                    echo "</div>";
                }
            } catch (Exception $e) {
                echo "<div class='no-data'>";
                echo "<i class='fas fa-exclamation-circle' style='color: #dc3545;'></i>";
                echo "<h2>Error Loading OTP Codes</h2>";
                echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
                echo "</div>";
            }
            ?>
        </div>
    </div>

    <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                alert('✅ OTP Code Copied!\n\nCode: ' + text + '\n\nYou can now paste it into the verification page.');
            }).catch(err => {
                // Fallback for older browsers
                const tempInput = document.createElement('input');
                tempInput.value = text;
                document.body.appendChild(tempInput);
                tempInput.select();
                document.execCommand('copy');
                document.body.removeChild(tempInput);
                alert('✅ OTP Code Copied: ' + text);
            });
        }
    </script>
</body>
</html>
