<?php
/**
 * Manual HTTPS Enforcement Test
 * 
 * Tests HTTPS redirection behavior
 */

// Temporarily disable auto-security to show current state
define('DISABLE_AUTO_SECURITY', true);
require_once 'security_network.php';

$is_https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
$server_name = $_SERVER['SERVER_NAME'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost';
$is_localhost = in_array($server_name, ['localhost', '127.0.0.1', '::1']) || 
                strpos($server_name, 'localhost') !== false;

?>
<!DOCTYPE html>
<html>
<head>
    <title>Manual HTTPS Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        h1 {
            color: #333;
        }
        .status {
            padding: 20px;
            border-radius: 5px;
            margin: 15px 0;
            font-size: 18px;
            font-weight: bold;
        }
        .https {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .http {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }
        .info-box {
            background: #d1ecf1;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
            border-left: 4px solid #17a2b8;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        table th, table td {
            padding: 12px;
            border: 1px solid #ddd;
            text-align: left;
        }
        table th {
            background: #f8f9fa;
            font-weight: bold;
        }
        .icon {
            font-size: 48px;
            text-align: center;
            margin: 20px 0;
        }
        button {
            background: #007bff;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin: 5px;
        }
        button:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>🔒 Manual HTTPS Enforcement Test</h1>
        
        <div class="icon">
            <?php echo $is_https ? '🔒' : '🔓'; ?>
        </div>
        
        <div class="status <?php echo $is_https ? 'https' : 'http'; ?>">
            Current Protocol: <strong><?php echo $is_https ? 'HTTPS ✅' : 'HTTP ⚠️'; ?></strong>
        </div>
        
        <table>
            <tr>
                <th>Property</th>
                <th>Value</th>
            </tr>
            <tr>
                <td>Protocol</td>
                <td><strong><?php echo $is_https ? 'HTTPS' : 'HTTP'; ?></strong></td>
            </tr>
            <tr>
                <td>Server Name</td>
                <td><?php echo htmlspecialchars($server_name); ?></td>
            </tr>
            <tr>
                <td>Is Localhost</td>
                <td><?php echo $is_localhost ? 'YES' : 'NO'; ?></td>
            </tr>
            <tr>
                <td>Full URL</td>
                <td><?php echo htmlspecialchars(($is_https ? 'https' : 'http') . '://' . $server_name . $_SERVER['REQUEST_URI']); ?></td>
            </tr>
            <tr>
                <td>HTTPS Enforcement</td>
                <td><?php echo $is_localhost ? 'DISABLED (Development)' : 'ENABLED (Production)'; ?></td>
            </tr>
        </table>
        
        <div class="info-box">
            <strong>How HTTPS Enforcement Works:</strong><br><br>
            
            <strong>Localhost/Development:</strong><br>
            ✅ HTTP is allowed (no redirect)<br>
            ✅ Makes development easier<br><br>
            
            <strong>Production (non-localhost):</strong><br>
            🔒 HTTP automatically redirects to HTTPS<br>
            🔒 Forces secure connections<br>
        </div>
        
        <div class="info-box">
            <strong>Current Behavior:</strong><br>
            <?php if ($is_localhost): ?>
                ✅ You are on <strong>localhost</strong><br>
                ✅ HTTP is allowed for development<br>
                ✅ HTTPS enforcement is bypassed<br>
                ℹ️ In production, this would redirect to HTTPS
            <?php else: ?>
                <?php if ($is_https): ?>
                    ✅ You are on <strong>HTTPS</strong><br>
                    ✅ Connection is secure<br>
                    ✅ HTTPS enforcement working correctly
                <?php else: ?>
                    ⚠️ You are on production HTTP<br>
                    ⚠️ This would normally redirect to HTTPS<br>
                    ℹ️ (Disabled for this test page)
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="card">
        <h2>🧪 Manual Testing Steps</h2>
        
        <div class="info-box">
            <strong>Test 1: Localhost (Current)</strong><br>
            1. You're already here!<br>
            2. Notice HTTP is allowed ✅<br>
            3. This is correct for development
        </div>
        
        <div class="info-box">
            <strong>Test 2: Simulate Production</strong><br>
            To test HTTPS enforcement:<br><br>
            
            <strong>Option A: Use a real domain</strong><br>
            1. Deploy to a server with a domain name<br>
            2. Access via HTTP (http://yourdomain.com)<br>
            3. Should auto-redirect to HTTPS (https://yourdomain.com)<br><br>
            
            <strong>Option B: Modify hosts file</strong><br>
            1. Edit <code>C:\Windows\System32\drivers\etc\hosts</code><br>
            2. Add: <code>127.0.0.1 testdomain.local</code><br>
            3. Access: <code>http://testdomain.local/...</code><br>
            4. Should redirect to HTTPS<br><br>
            
            <strong>Option C: Check in browser DevTools</strong><br>
            1. Open DevTools (F12)<br>
            2. Go to Network tab<br>
            3. Look for 301 redirect if not on localhost
        </div>
        
        <div class="info-box">
            <strong>Test 3: HTTPS Headers</strong><br>
            When on HTTPS, additional security header is sent:<br>
            <code>Strict-Transport-Security: max-age=31536000</code><br><br>
            This tells browsers to ONLY use HTTPS for 1 year
        </div>
        
        <p style="text-align: center; margin-top: 20px;">
            <a href="test_network_security.php"><button>← Back to Tests</button></a>
            <a href="manual_rate_limit_test.php"><button>Test Rate Limiting</button></a>
        </p>
    </div>
</body>
</html>
