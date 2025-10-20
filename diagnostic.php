<?php
/**
 * SYSTEM DIAGNOSTIC TOOL
 * Use this to troubleshoot multi-device login issues
 */

require_once 'config.php';
session_start();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Diagnostic</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            min-height: 100vh;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        h1 {
            color: #667eea;
            margin-bottom: 10px;
            font-size: 2rem;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 1.1rem;
        }
        .section {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
        }
        .section h2 {
            color: #333;
            margin-bottom: 15px;
            font-size: 1.3rem;
        }
        .status-item {
            display: flex;
            align-items: center;
            padding: 12px;
            background: white;
            border-radius: 8px;
            margin-bottom: 10px;
            border: 1px solid #e0e0e0;
        }
        .status-icon {
            font-size: 24px;
            margin-right: 15px;
            min-width: 30px;
        }
        .status-label {
            font-weight: 600;
            color: #333;
            min-width: 180px;
        }
        .status-value {
            color: #666;
            flex: 1;
            word-break: break-all;
        }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .warning { color: #ffc107; }
        .info { color: #17a2b8; }
        .code-block {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 15px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            overflow-x: auto;
            margin-top: 10px;
        }
        .recommendation {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
        }
        .recommendation h3 {
            color: #856404;
            margin-bottom: 10px;
        }
        .recommendation ul {
            margin-left: 20px;
            color: #856404;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary {
            background: #667eea;
            color: white;
        }
        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-2px);
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background: #5a6268;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 System Diagnostic</h1>
        <p class="subtitle">Multi-Device Login Troubleshooting Tool</p>

        <!-- Database Connection Test -->
        <div class="section">
            <h2>1. Database Connection</h2>
            <?php
            $db_status = false;
            $db_message = '';
            try {
                $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
                if ($conn->connect_error) {
                    $db_message = "Failed: " . $conn->connect_error;
                } else {
                    $db_status = true;
                    $db_message = "Connected successfully!";
                    
                    // Test database tables
                    $tables = ['users', 'staff', 'session_tracking', 'otp_codes'];
                    $missing_tables = [];
                    foreach ($tables as $table) {
                        $result = $conn->query("SHOW TABLES LIKE '$table'");
                        if ($result->num_rows === 0) {
                            $missing_tables[] = $table;
                        }
                    }
                }
            } catch (Exception $e) {
                $db_message = "Exception: " . $e->getMessage();
            }
            ?>
            <div class="status-item">
                <span class="status-icon <?php echo $db_status ? 'success' : 'error'; ?>">
                    <?php echo $db_status ? '✅' : '❌'; ?>
                </span>
                <span class="status-label">Connection Status:</span>
                <span class="status-value"><?php echo htmlspecialchars($db_message); ?></span>
            </div>
            
            <?php if ($db_status): ?>
            <div class="status-item">
                <span class="status-icon info">ℹ️</span>
                <span class="status-label">Database Host:</span>
                <span class="status-value"><?php echo htmlspecialchars(DB_HOST); ?></span>
            </div>
            <div class="status-item">
                <span class="status-icon info">ℹ️</span>
                <span class="status-label">Database Name:</span>
                <span class="status-value"><?php echo htmlspecialchars(DB_NAME); ?></span>
            </div>
            <div class="status-item">
                <span class="status-icon info">ℹ️</span>
                <span class="status-label">Database User:</span>
                <span class="status-value"><?php echo htmlspecialchars(DB_USER); ?></span>
            </div>
            
            <?php if (!empty($missing_tables)): ?>
            <div class="recommendation">
                <h3>⚠️ Missing Tables Detected</h3>
                <p>The following tables are missing: <strong><?php echo implode(', ', $missing_tables); ?></strong></p>
                <p>Please import the database SQL file.</p>
            </div>
            <?php endif; ?>
            
            <?php else: ?>
            <div class="recommendation">
                <h3>❌ Database Connection Failed</h3>
                <ul>
                    <li>Ensure XAMPP/MySQL is running</li>
                    <li>Check database credentials in config.php</li>
                    <li>Verify database "<?php echo DB_NAME; ?>" exists</li>
                    <li>Check if port 3306 is available</li>
                </ul>
            </div>
            <?php endif; ?>
        </div>

        <!-- Session Configuration -->
        <div class="section">
            <h2>2. Session Configuration</h2>
            <div class="status-item">
                <span class="status-icon success">✅</span>
                <span class="status-label">Session ID:</span>
                <span class="status-value"><?php echo session_id(); ?></span>
            </div>
            <div class="status-item">
                <span class="status-icon info">ℹ️</span>
                <span class="status-label">Cookie Lifetime:</span>
                <span class="status-value"><?php echo ini_get('session.cookie_lifetime'); ?> seconds (<?php echo round(ini_get('session.cookie_lifetime')/3600, 1); ?> hours)</span>
            </div>
            <div class="status-item">
                <span class="status-icon info">ℹ️</span>
                <span class="status-label">Cookie Secure (HTTPS):</span>
                <span class="status-value"><?php echo ini_get('session.cookie_secure') ? 'Yes (HTTPS required)' : 'No (HTTP allowed)'; ?></span>
            </div>
            <div class="status-item">
                <span class="status-icon info">ℹ️</span>
                <span class="status-label">Cookie HTTPOnly:</span>
                <span class="status-value"><?php echo ini_get('session.cookie_httponly') ? 'Yes (Protected from JavaScript)' : 'No'; ?></span>
            </div>
            <div class="status-item">
                <span class="status-icon info">ℹ️</span>
                <span class="status-label">Cookie SameSite:</span>
                <span class="status-value"><?php echo ini_get('session.cookie_samesite') ?: 'Not Set'; ?></span>
            </div>
        </div>

        <!-- Security Configuration -->
        <div class="section">
            <h2>3. Security & Fingerprint Configuration</h2>
            <?php
            $fingerprint_mode = defined('FINGERPRINT_MODE') ? FINGERPRINT_MODE : 'Not Set';
            $concurrent_sessions = defined('ALLOW_CONCURRENT_SESSIONS') ? (ALLOW_CONCURRENT_SESSIONS ? 'Enabled' : 'Disabled') : 'Not Set';
            
            $mode_status = 'info';
            $mode_recommendation = '';
            
            if ($fingerprint_mode === 'strict') {
                $mode_status = 'warning';
                $mode_recommendation = 'Strict mode only allows single device. Change to "moderate" or "relaxed" for multi-device support.';
            } elseif ($fingerprint_mode === 'moderate') {
                $mode_status = 'success';
                $mode_recommendation = 'Moderate mode allows same device from different networks (recommended for mobile users).';
            } elseif ($fingerprint_mode === 'relaxed') {
                $mode_status = 'success';
                $mode_recommendation = 'Relaxed mode allows multiple devices per user.';
            }
            ?>
            <div class="status-item">
                <span class="status-icon <?php echo $mode_status === 'success' ? 'success' : 'warning'; ?>">
                    <?php echo $mode_status === 'success' ? '✅' : '⚠️'; ?>
                </span>
                <span class="status-label">Fingerprint Mode:</span>
                <span class="status-value"><?php echo htmlspecialchars($fingerprint_mode); ?></span>
            </div>
            <div class="status-item">
                <span class="status-icon info">ℹ️</span>
                <span class="status-label">Concurrent Sessions:</span>
                <span class="status-value"><?php echo htmlspecialchars($concurrent_sessions); ?></span>
            </div>
            <div class="status-item">
                <span class="status-icon info">ℹ️</span>
                <span class="status-label">Max Login Attempts:</span>
                <span class="status-value"><?php echo defined('MAX_LOGIN_ATTEMPTS') ? MAX_LOGIN_ATTEMPTS : 'Not Set'; ?></span>
            </div>
            
            <?php if ($mode_recommendation): ?>
            <div class="recommendation">
                <h3>ℹ️ Configuration Info</h3>
                <p><?php echo $mode_recommendation; ?></p>
                
                <?php if ($fingerprint_mode === 'strict'): ?>
                <p><strong>To fix multi-device login issues:</strong></p>
                <div class="code-block">
// Open config.php and change:<br>
define('FINGERPRINT_MODE', 'moderate'); // For same device, different networks<br>
// OR<br>
define('FINGERPRINT_MODE', 'relaxed');  // For multiple devices
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Network Information -->
        <div class="section">
            <h2>4. Network Information</h2>
            <div class="status-item">
                <span class="status-icon info">ℹ️</span>
                <span class="status-label">Server IP Address:</span>
                <span class="status-value"><?php echo $_SERVER['SERVER_ADDR'] ?? 'Unknown'; ?></span>
            </div>
            <div class="status-item">
                <span class="status-icon info">ℹ️</span>
                <span class="status-label">Client IP Address:</span>
                <span class="status-value"><?php echo $_SERVER['REMOTE_ADDR']; ?></span>
            </div>
            <div class="status-item">
                <span class="status-icon info">ℹ️</span>
                <span class="status-label">User Agent:</span>
                <span class="status-value"><?php echo htmlspecialchars($_SERVER['HTTP_USER_AGENT']); ?></span>
            </div>
            <div class="status-item">
                <span class="status-icon info">ℹ️</span>
                <span class="status-label">Protocol:</span>
                <span class="status-value"><?php echo isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'HTTPS (Secure)' : 'HTTP'; ?></span>
            </div>
        </div>

        <!-- Access Information -->
        <div class="section">
            <h2>5. Access URLs</h2>
            <?php
            $current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . "/";
            $network_url = "http://" . ($_SERVER['SERVER_ADDR'] ?? 'localhost') . dirname($_SERVER['REQUEST_URI']) . "/";
            $is_localhost = ($_SERVER['SERVER_NAME'] === 'localhost' || $_SERVER['SERVER_ADDR'] === '127.0.0.1');
            ?>
            <div class="status-item">
                <span class="status-icon info">ℹ️</span>
                <span class="status-label">Current Access URL:</span>
                <span class="status-value"><?php echo htmlspecialchars($current_url); ?></span>
            </div>
            <div class="status-item">
                <span class="status-icon <?php echo $is_localhost ? 'warning' : 'success'; ?>">
                    <?php echo $is_localhost ? '⚠️' : '✅'; ?>
                </span>
                <span class="status-label">Network Access URL:</span>
                <span class="status-value"><?php echo htmlspecialchars($network_url); ?></span>
            </div>
            
            <?php if ($is_localhost): ?>
            <div class="recommendation">
                <h3>📱 For Multi-Device Access</h3>
                <p><strong>You are currently accessing via localhost.</strong> To access from other devices:</p>
                <ol style="margin-left: 20px; margin-top: 10px;">
                    <li>Find your computer's IP address (run <code>ipconfig</code> in CMD)</li>
                    <li>Use this URL on other devices: <strong><?php echo $network_url; ?></strong></li>
                    <li>Ensure devices are on the same network (WiFi)</li>
                    <li>Configure MySQL to allow network access (see MULTI_DEVICE_LOGIN_FIX.md)</li>
                </ol>
            </div>
            <?php endif; ?>
        </div>

        <!-- Current Session Data -->
        <?php if (!empty($_SESSION)): ?>
        <div class="section">
            <h2>6. Current Session Data</h2>
            <div class="code-block">
<?php print_r($_SESSION); ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Action Buttons -->
        <div class="action-buttons">
            <a href="index.php" class="btn btn-primary">Go to Login</a>
            <a href="diagnostic.php" class="btn btn-secondary">Refresh Diagnostic</a>
            <a href="MULTI_DEVICE_LOGIN_FIX.md" class="btn btn-secondary" target="_blank">View Fix Guide</a>
        </div>
    </div>
</body>
</html>
