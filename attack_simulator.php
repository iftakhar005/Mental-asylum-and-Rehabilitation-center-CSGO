<?php
/**
 * LIVE SECURITY ATTACK SIMULATOR
 * Interactive demonstration where teacher can try their own attacks
 */

session_start();
require_once 'db.php';
require_once 'security_manager.php';

$securityManager = new MentalHealthSecurityManager($conn);

$test_result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_input'])) {
    $input = $_POST['test_input'];
    $test_type = $_POST['test_type'];
    
    if ($test_type === 'sql') {
        $is_malicious = $securityManager->detectSQLInjection($input);
        $test_result = [
            'type' => 'SQL Injection Test',
            'input' => $input,
            'detected' => $is_malicious,
            'message' => $is_malicious ? 'SQL Injection Attack Detected & Blocked!' : 'Input is safe - No SQL injection detected'
        ];
    } else {
        $sanitized = $securityManager->escapeHTML($input);
        $test_result = [
            'type' => 'XSS Protection Test',
            'input' => $input,
            'sanitized' => $sanitized,
            'changed' => ($input !== $sanitized),
            'message' => ($input !== $sanitized) ? 'Malicious code sanitized!' : 'Input is already safe'
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Security Attack Simulator</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            text-align: center;
            margin-bottom: 30px;
            animation: slideDown 0.6s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .header h1 {
            font-size: 42px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 15px;
        }

        .header p {
            color: #666;
            font-size: 18px;
        }

        .demo-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .attack-panel {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.2);
            animation: fadeIn 0.8s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .panel-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 3px solid #667eea;
        }

        .panel-header h2 {
            color: #2c3e50;
            font-size: 26px;
        }

        .panel-header i {
            font-size: 32px;
            color: #667eea;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 10px;
            color: #2c3e50;
            font-weight: bold;
            font-size: 16px;
        }

        select, textarea {
            width: 100%;
            padding: 15px;
            border: 2px solid #ddd;
            border-radius: 10px;
            font-size: 14px;
            font-family: 'Courier New', monospace;
            transition: border-color 0.3s;
        }

        select:focus, textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        textarea {
            min-height: 120px;
            resize: vertical;
        }

        .preset-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 20px;
        }

        .preset-btn {
            padding: 10px;
            border: 2px solid #667eea;
            background: white;
            color: #667eea;
            border-radius: 8px;
            cursor: pointer;
            font-size: 13px;
            transition: all 0.3s;
        }

        .preset-btn:hover {
            background: #667eea;
            color: white;
        }

        .btn {
            width: 100%;
            padding: 18px;
            border: none;
            border-radius: 12px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-test {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-test:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }

        .result-panel {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.2);
            animation: slideIn 0.5s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .result-blocked {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin: 20px 0;
            text-align: center;
            animation: shake 0.6s;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }

        .result-safe {
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin: 20px 0;
            text-align: center;
            animation: pulse 0.6s;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .result-sanitized {
            background: linear-gradient(135deg, #f39c12 0%, #d68910 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin: 20px 0;
            text-align: center;
        }

        .result-icon {
            font-size: 80px;
            margin-bottom: 20px;
        }

        .result-title {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 15px;
        }

        .code-box {
            background: rgba(0,0,0,0.2);
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            font-family: 'Courier New', monospace;
            word-break: break-all;
            text-align: left;
        }

        .code-label {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 10px;
            font-weight: bold;
        }

        .examples-section {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.2);
        }

        .example-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .example-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            border-left: 4px solid #667eea;
        }

        .example-title {
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 8px;
        }

        .example-code {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            color: #c0392b;
            background: white;
            padding: 10px;
            border-radius: 5px;
            margin-top: 5px;
        }

        @media (max-width: 768px) {
            .demo-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-shield-virus"></i> Live Security Attack Simulator</h1>
            <p>Try to hack the system - See how protection works in real-time!</p>
        </div>

        <div class="demo-container">
            <!-- Attack Input Panel -->
            <div class="attack-panel">
                <div class="panel-header">
                    <i class="fas fa-user-secret"></i>
                    <h2>Launch Attack</h2>
                </div>

                <form method="POST">
                    <div class="form-group">
                        <label><i class="fas fa-crosshairs"></i> Attack Type:</label>
                        <select name="test_type" id="attackType" onchange="updatePresets()">
                            <option value="sql">SQL Injection Attack</option>
                            <option value="xss">XSS (Cross-Site Scripting) Attack</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-bolt"></i> Quick Attack Presets:</label>
                        <div class="preset-buttons" id="presetButtons"></div>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-terminal"></i> Your Attack Code:</label>
                        <textarea name="test_input" id="attackInput" placeholder="Enter your malicious code here..."><?php echo isset($_POST['test_input']) ? htmlspecialchars($_POST['test_input']) : ''; ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-test">
                        <i class="fas fa-rocket"></i>
                        LAUNCH ATTACK!
                    </button>
                </form>
            </div>

            <!-- Result Panel -->
            <div class="attack-panel">
                <div class="panel-header">
                    <i class="fas fa-shield-alt"></i>
                    <h2>System Response</h2>
                </div>

                <?php if ($test_result): ?>
                    <?php if ($test_result['type'] === 'SQL Injection Test'): ?>
                        <?php if ($test_result['detected']): ?>
                            <div class="result-blocked">
                                <div class="result-icon">
                                    <i class="fas fa-ban"></i>
                                </div>
                                <div class="result-title">🛑 ATTACK BLOCKED!</div>
                                <p style="font-size: 16px;">SQL Injection detected and prevented</p>
                                
                                <div class="code-box">
                                    <div class="code-label">Malicious Input Attempted:</div>
                                    <?php echo htmlspecialchars($test_result['input']); ?>
                                </div>
                                
                                <p style="margin-top: 20px; font-size: 15px;">
                                    ✅ Database is safe<br>
                                    ✅ Attack logged for security audit<br>
                                    ✅ User would be rate-limited
                                </p>
                            </div>
                        <?php else: ?>
                            <div class="result-safe">
                                <div class="result-icon">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <div class="result-title">✅ SAFE INPUT</div>
                                <p style="font-size: 16px;">No SQL injection detected</p>
                                
                                <div class="code-box">
                                    <div class="code-label">Input Provided:</div>
                                    <?php echo htmlspecialchars($test_result['input']); ?>
                                </div>
                                
                                <p style="margin-top: 20px;">This input is legitimate and can be processed safely!</p>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php if ($test_result['changed']): ?>
                            <div class="result-sanitized">
                                <div class="result-icon">
                                    <i class="fas fa-broom"></i>
                                </div>
                                <div class="result-title">🧹 XSS SANITIZED!</div>
                                <p style="font-size: 16px;">Malicious script neutralized</p>
                                
                                <div class="code-box">
                                    <div class="code-label">Original (Dangerous):</div>
                                    <?php echo htmlspecialchars($test_result['input']); ?>
                                </div>
                                
                                <div class="code-box">
                                    <div class="code-label">After Sanitization (Safe):</div>
                                    <?php echo htmlspecialchars($test_result['sanitized']); ?>
                                </div>
                                
                                <p style="margin-top: 20px; font-size: 15px;">
                                    ✅ JavaScript execution prevented<br>
                                    ✅ Content can be displayed safely<br>
                                    ✅ No risk to users
                                </p>
                            </div>
                        <?php else: ?>
                            <div class="result-safe">
                                <div class="result-icon">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <div class="result-title">✅ ALREADY SAFE</div>
                                <p style="font-size: 16px;">No dangerous code detected</p>
                                
                                <div class="code-box">
                                    <div class="code-label">Input:</div>
                                    <?php echo htmlspecialchars($test_result['input']); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php else: ?>
                    <div style="text-align: center; padding: 60px 20px; color: #999;">
                        <i class="fas fa-arrow-left" style="font-size: 60px; margin-bottom: 20px;"></i>
                        <p style="font-size: 18px;">Select an attack type and launch your attack to see how the system responds!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Examples Section -->
        <div class="examples-section">
            <div class="panel-header">
                <i class="fas fa-book"></i>
                <h2>Attack Examples to Try</h2>
            </div>

            <h3 style="color: #e74c3c; margin: 20px 0;">SQL Injection Attacks:</h3>
            <div class="example-grid">
                <div class="example-card">
                    <div class="example-title">Authentication Bypass</div>
                    <div class="example-code">admin' OR '1'='1</div>
                </div>
                <div class="example-card">
                    <div class="example-title">Data Extraction</div>
                    <div class="example-code">1' UNION SELECT password FROM users--</div>
                </div>
                <div class="example-card">
                    <div class="example-title">Table Deletion</div>
                    <div class="example-code">admin'; DROP TABLE users;--</div>
                </div>
                <div class="example-card">
                    <div class="example-title">Time Delay Attack</div>
                    <div class="example-code">1' AND SLEEP(5)--</div>
                </div>
            </div>

            <h3 style="color: #f39c12; margin: 30px 0 20px 0;">XSS Attacks:</h3>
            <div class="example-grid">
                <div class="example-card">
                    <div class="example-title">Script Injection</div>
                    <div class="example-code">&lt;script&gt;alert('Hacked!')&lt;/script&gt;</div>
                </div>
                <div class="example-card">
                    <div class="example-title">Image Event Handler</div>
                    <div class="example-code">&lt;img src=x onerror="alert('XSS')"&gt;</div>
                </div>
                <div class="example-card">
                    <div class="example-title">JavaScript Protocol</div>
                    <div class="example-code">&lt;a href="javascript:alert('XSS')"&gt;Click&lt;/a&gt;</div>
                </div>
                <div class="example-card">
                    <div class="example-title">SVG Attack</div>
                    <div class="example-code">&lt;svg onload="alert('XSS')"&gt;&lt;/svg&gt;</div>
                </div>
            </div>
        </div>

        <div style="text-align: center; margin: 30px 0;">
            <a href="security_demo.php" class="btn btn-test" style="display: inline-flex; width: auto; text-decoration: none;">
                <i class="fas fa-chart-bar"></i>
                View Full Automated Test Results
            </a>
        </div>
    </div>

    <script>
        const sqlPresets = [
            ["OR Injection", "admin' OR '1'='1"],
            ["UNION Attack", "1' UNION SELECT password FROM users--"],
            ["DROP TABLE", "admin'; DROP TABLE users;--"],
            ["Time Delay", "1' AND SLEEP(5)--"],
            ["Comment Bypass", "admin'--"],
            ["Stacked Query", "admin'; DELETE FROM sessions;--"]
        ];

        const xssPresets = [
            ["Script Tag", "<script>alert('XSS')</script>"],
            ["Image Event", "<img src=x onerror=\"alert('XSS')\">"],
            ["JavaScript URL", "<a href=\"javascript:alert('XSS')\">Click</a>"],
            ["SVG Attack", "<svg onload=\"alert('XSS')\"></svg>"],
            ["Iframe Injection", "<iframe src=\"http://evil.com\"></iframe>"],
            ["Style XSS", "<div style=\"background:url(javascript:alert(1))\">"]
        ];

        function updatePresets() {
            const attackType = document.getElementById('attackType').value;
            const presetsContainer = document.getElementById('presetButtons');
            const presets = attackType === 'sql' ? sqlPresets : xssPresets;
            
            presetsContainer.innerHTML = '';
            presets.forEach(([name, code]) => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'preset-btn';
                btn.textContent = name;
                btn.onclick = () => {
                    document.getElementById('attackInput').value = code;
                };
                presetsContainer.appendChild(btn);
            });
        }

        // Initialize presets on page load
        updatePresets();
    </script>
</body>
</html>
