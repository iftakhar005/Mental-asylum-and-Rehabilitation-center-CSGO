<?php
/**
 * OTP Code Checker - Development Tool
 * This tool helps you retrieve OTP codes from the database for testing
 * 
 * SECURITY WARNING: Remove this file in production!
 */

session_start();
require_once 'db.php';

// Only allow access in development environment
$is_development = ($_SERVER['SERVER_NAME'] === 'localhost' || $_SERVER['SERVER_NAME'] === '127.0.0.1');

if (!$is_development) {
    die('This tool is only available in development environment');
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OTP Code Checker - Development Tool</title>
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
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 1000px;
            width: 100%;
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }

        .header p {
            font-size: 14px;
            opacity: 0.9;
        }

        .warning-banner {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px 20px;
            margin: 20px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .warning-banner i {
            color: #ffc107;
            font-size: 24px;
        }

        .content {
            padding: 30px;
        }

        .refresh-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }

        .refresh-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .otp-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .otp-table thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .otp-table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
        }

        .otp-table td {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
        }

        .otp-table tbody tr:hover {
            background: #f8f9ff;
        }

        .otp-code {
            font-family: 'Courier New', monospace;
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
            letter-spacing: 4px;
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-valid {
            background: #d4edda;
            color: #155724;
        }

        .status-expired {
            background: #f8d7da;
            color: #721c24;
        }

        .status-used {
            background: #d1ecf1;
            color: #0c5460;
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
            font-size: 16px;
        }

        .copy-btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 5px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.3s ease;
        }

        .copy-btn:hover {
            background: #764ba2;
        }

        .time-info {
            font-size: 12px;
            color: #666;
        }

        .auto-refresh {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9ff;
            border-radius: 8px;
        }

        .auto-refresh input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .countdown {
            font-weight: 600;
            color: #667eea;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-shield-alt"></i> OTP Code Checker</h1>
            <p>Development Tool - View OTP Codes for Testing</p>
        </div>

        <div class="warning-banner">
            <i class="fas fa-exclamation-triangle"></i>
            <div>
                <strong>Development Only!</strong> This tool displays sensitive OTP codes. 
                Remove this file before deploying to production!
            </div>
        </div>

        <div class="content">
            <div class="auto-refresh">
                <input type="checkbox" id="autoRefresh" checked>
                <label for="autoRefresh">Auto-refresh every <span class="countdown" id="countdown">5</span> seconds</label>
            </div>

            <button class="refresh-btn" onclick="loadOTPCodes()">
                <i class="fas fa-sync-alt"></i>
                Refresh Now
            </button>

            <div id="otpCodesContainer">
                <div class="no-data">
                    <i class="fas fa-spinner fa-spin" style="font-size: 30px; color: #667eea;"></i>
                    <p style="margin-top: 15px;">Loading OTP codes...</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        let refreshInterval;
        let countdownInterval;
        let countdownValue = 5;

        function loadOTPCodes() {
            fetch('check_otp_logs.php?action=get_otp_codes')
                .then(response => response.json())
                .then(data => {
                    displayOTPCodes(data);
                    resetCountdown();
                })
                .catch(error => {
                    console.error('Error loading OTP codes:', error);
                    document.getElementById('otpCodesContainer').innerHTML = 
                        '<div class="no-data"><i class="fas fa-exclamation-circle" style="font-size: 30px; color: #dc3545;"></i><p style="margin-top: 15px;">Error loading OTP codes</p></div>';
                });
        }

        function displayOTPCodes(codes) {
            const container = document.getElementById('otpCodesContainer');
            
            if (codes.length === 0) {
                container.innerHTML = '<div class="no-data"><i class="fas fa-inbox" style="font-size: 30px; color: #999;"></i><p style="margin-top: 15px;">No OTP codes found. Try logging in with a 2FA-enabled account.</p></div>';
                return;
            }

            let html = `
                <table class="otp-table">
                    <thead>
                        <tr>
                            <th>Email</th>
                            <th>OTP Code</th>
                            <th>Created</th>
                            <th>Expires</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            codes.forEach(code => {
                const now = new Date().getTime();
                const expiryTime = new Date(code.expires_at).getTime();
                const isExpired = now > expiryTime;
                const isUsed = code.is_used == 1;
                
                let status = 'Valid';
                let statusClass = 'status-valid';
                
                if (isUsed) {
                    status = 'Used';
                    statusClass = 'status-used';
                } else if (isExpired) {
                    status = 'Expired';
                    statusClass = 'status-expired';
                }

                const timeLeft = Math.max(0, Math.floor((expiryTime - now) / 1000));
                const minutes = Math.floor(timeLeft / 60);
                const seconds = timeLeft % 60;

                html += `
                    <tr>
                        <td><strong>${code.email}</strong></td>
                        <td><span class="otp-code">${code.otp_code}</span></td>
                        <td class="time-info">${formatDateTime(code.created_at)}</td>
                        <td class="time-info">
                            ${formatDateTime(code.expires_at)}<br>
                            ${!isUsed && !isExpired ? `<small>(${minutes}m ${seconds}s left)</small>` : ''}
                        </td>
                        <td><span class="status-badge ${statusClass}">${status}</span></td>
                        <td>
                            <button class="copy-btn" onclick="copyOTP('${code.otp_code}')">
                                <i class="fas fa-copy"></i> Copy
                            </button>
                        </td>
                    </tr>
                `;
            });

            html += '</tbody></table>';
            container.innerHTML = html;
        }

        function formatDateTime(datetime) {
            const date = new Date(datetime);
            return date.toLocaleString('en-US', { 
                month: 'short', 
                day: 'numeric', 
                hour: '2-digit', 
                minute: '2-digit',
                second: '2-digit'
            });
        }

        function copyOTP(otp) {
            navigator.clipboard.writeText(otp).then(() => {
                alert('OTP code copied to clipboard: ' + otp);
            });
        }

        function resetCountdown() {
            countdownValue = 5;
            document.getElementById('countdown').textContent = countdownValue;
        }

        function startCountdown() {
            countdownInterval = setInterval(() => {
                countdownValue--;
                if (countdownValue < 0) countdownValue = 5;
                document.getElementById('countdown').textContent = countdownValue;
            }, 1000);
        }

        document.getElementById('autoRefresh').addEventListener('change', function() {
            if (this.checked) {
                refreshInterval = setInterval(loadOTPCodes, 5000);
                startCountdown();
            } else {
                clearInterval(refreshInterval);
                clearInterval(countdownInterval);
            }
        });

        // Initial load
        loadOTPCodes();
        refreshInterval = setInterval(loadOTPCodes, 5000);
        startCountdown();
    </script>
</body>
</html>

<?php
// Handle AJAX request for OTP codes
if (isset($_GET['action']) && $_GET['action'] === 'get_otp_codes') {
    header('Content-Type: application/json');
    
    try {
        $sql = "SELECT id, email, otp_code, created_at, expires_at, is_used 
                FROM otp_codes 
                ORDER BY created_at DESC 
                LIMIT 20";
        
        $result = $conn->query($sql);
        $codes = [];
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $codes[] = $row;
            }
        }
        
        echo json_encode($codes);
        
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    
    exit();
}
?>
