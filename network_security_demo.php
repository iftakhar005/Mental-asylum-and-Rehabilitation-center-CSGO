<?php
/**
 * Network Security Demo
 * 
 * Demonstrates practical usage of network security features
 */

// For demo purposes, we'll control when security is applied
define('DISABLE_AUTO_SECURITY', true);

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/security_network.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Network Security Demo</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            padding: 20px;
            min-height: 100vh;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        .header {
            background: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .header h1 {
            color: #11998e;
            margin-bottom: 10px;
        }
        .demo-section {
            background: white;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .demo-section h2 {
            color: #333;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #11998e;
        }
        .code-block {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 20px;
            border-radius: 8px;
            overflow-x: auto;
            margin: 15px 0;
            font-family: 'Courier New', monospace;
            font-size: 14px;
        }
        .code-block .comment {
            color: #6a9955;
        }
        .code-block .keyword {
            color: #569cd6;
        }
        .code-block .string {
            color: #ce9178;
        }
        .code-block .function {
            color: #dcdcaa;
        }
        .output-box {
            background: #f8f9fa;
            padding: 15px;
            border-left: 4px solid #11998e;
            margin: 10px 0;
            border-radius: 5px;
        }
        .scenario {
            background: #e0f7f4;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
        .scenario h3 {
            color: #11998e;
            margin-bottom: 10px;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .card {
            background: #fff;
            border: 2px solid #11998e;
            border-radius: 10px;
            padding: 20px;
        }
        .card h3 {
            color: #11998e;
            margin-bottom: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        table th {
            background: #11998e;
            color: white;
            padding: 12px;
            text-align: left;
        }
        table td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }
        table tr:nth-child(even) {
            background: #f9f9f9;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🌐 Network Security - Practical Demo</h1>
            <p>Real-world examples of using network security features in your application</p>
        </div>

        <!-- Example 1: Basic Security Setup -->
        <div class="demo-section">
            <h2>🔒 Example 1: Basic Security Setup</h2>
            
            <div class="scenario">
                <h3>Scenario: Securing a PHP page</h3>
                <p>Add comprehensive network security to any PHP page with a single include</p>
            </div>

            <div class="code-block">
<span class="comment">// At the top of your PHP file (e.g., patient_dashboard.php)</span>
<span class="keyword">require_once</span> <span class="string">'security_network.php'</span>;

<span class="comment">// That's it! The following are automatically applied:</span>
<span class="comment">// ✅ Security headers (CSP, X-Frame-Options, etc.)</span>
<span class="comment">// ✅ HTTPS enforcement (production only)</span>
<span class="comment">// ✅ Rate limiting (POST/PUT/DELETE requests)</span>
            </div>

            <div class="output-box">
                <strong>Auto-Applied Security:</strong><br>
                ✅ Content Security Policy<br>
                ✅ Clickjacking protection<br>
                ✅ MIME sniffing prevention<br>
                ✅ XSS protection<br>
                ✅ Rate limiting (30 req/min)
            </div>
        </div>

        <!-- Example 2: Custom Rate Limiting -->
        <div class="demo-section">
            <h2>⏱️ Example 2: Custom Rate Limiting</h2>
            
            <div class="scenario">
                <h3>Scenario: Protect login endpoint from brute force</h3>
                <p>Limit login attempts to 5 per IP address every 5 minutes</p>
            </div>

            <div class="code-block">
<span class="comment">// In your login.php or index.php</span>
<span class="keyword">require_once</span> <span class="string">'security_network.php'</span>;

<span class="keyword">if</span> (<span class="keyword">$_SERVER</span>[<span class="string">'REQUEST_METHOD'</span>] === <span class="string">'POST'</span>) {
    <span class="comment">// Get user's IP</span>
    <span class="keyword">$ip</span> = <span class="function">get_client_ip</span>();
    
    <span class="comment">// Check rate limit: 5 attempts per 5 minutes (300 seconds)</span>
    <span class="keyword">if</span> (!<span class="function">rate_limit</span>(<span class="string">'login_'</span> . <span class="keyword">$ip</span>, 5, 300)) {
        <span class="function">http_response_code</span>(429);
        <span class="keyword">echo</span> <span class="function">json_encode</span>([
            <span class="string">'error'</span> => <span class="string">'Too many login attempts. Please try again in 5 minutes.'</span>
        ]);
        <span class="keyword">exit</span>();
    }
    
    <span class="comment">// Process login...</span>
}
            </div>

            <?php
            // Demonstrate rate limiting
            $demo_ip = '192.168.1.100';
            $attempts = [];
            for ($i = 1; $i <= 7; $i++) {
                $allowed = rate_limit('demo_login_' . $demo_ip, 5, 300);
                $attempts[] = ['attempt' => $i, 'allowed' => $allowed];
            }
            ?>

            <div class="output-box">
                <strong>Rate Limiting Demo (5 attempts allowed per 5 minutes):</strong>
                <table>
                    <tr>
                        <th>Attempt</th>
                        <th>Status</th>
                    </tr>
                    <?php foreach ($attempts as $attempt): ?>
                    <tr>
                        <td>Login Attempt #<?php echo $attempt['attempt']; ?></td>
                        <td style="color: <?php echo $attempt['allowed'] ? 'green' : 'red'; ?>">
                            <?php echo $attempt['allowed'] ? '✓ ALLOWED' : '⊘ BLOCKED (Rate Limited)'; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>

        <!-- Example 3: File Upload Validation -->
        <div class="demo-section">
            <h2>📤 Example 3: Secure File Upload</h2>
            
            <div class="scenario">
                <h3>Scenario: Validate uploaded patient documents</h3>
                <p>Ensure only safe file types are accepted with proper validation</p>
            </div>

            <div class="code-block">
<span class="comment">// In your file upload handler</span>
<span class="keyword">require_once</span> <span class="string">'security_network.php'</span>;

<span class="keyword">if</span> (<span class="keyword">isset</span>(<span class="keyword">$_FILES</span>[<span class="string">'document'</span>])) {
    <span class="comment">// Define allowed file types</span>
    <span class="keyword">$allowed_types</span> = [
        <span class="string">'image/jpeg'</span>,
        <span class="string">'image/png'</span>,
        <span class="string">'application/pdf'</span>,
        <span class="string">'application/msword'</span>,
        <span class="string">'application/vnd.openxmlformats-officedocument.wordprocessingml.document'</span>
    ];
    
    <span class="comment">// Max size: 5MB</span>
    <span class="keyword">$max_size</span> = 5 * 1024 * 1024;
    
    <span class="comment">// Validate file</span>
    <span class="keyword">$validation</span> = <span class="function">validate_file_upload</span>(<span class="keyword">$_FILES</span>[<span class="string">'document'</span>], <span class="keyword">$allowed_types</span>, <span class="keyword">$max_size</span>);
    
    <span class="keyword">if</span> (<span class="keyword">$validation</span>[<span class="string">'success'</span>]) {
        <span class="comment">// Optional: Scan for viruses</span>
        <span class="keyword">$scan</span> = <span class="function">scan_file_with_clamscan</span>(<span class="keyword">$_FILES</span>[<span class="string">'document'</span>][<span class="string">'tmp_name'</span>]);
        
        <span class="keyword">if</span> (<span class="keyword">$scan</span>[<span class="string">'available'</span>] && <span class="keyword">$scan</span>[<span class="string">'infected'</span>]) {
            <span class="keyword">echo</span> <span class="string">'ERROR: File contains malware!'</span>;
        } <span class="keyword">else</span> {
            <span class="comment">// Safe to process</span>
            <span class="function">move_uploaded_file</span>(<span class="keyword">$_FILES</span>[<span class="string">'document'</span>][<span class="string">'tmp_name'</span>], <span class="keyword">$destination</span>);
        }
    } <span class="keyword">else</span> {
        <span class="keyword">echo</span> <span class="keyword">$validation</span>[<span class="string">'message'</span>]; <span class="comment">// Show error</span>
    }
}
            </div>

            <div class="output-box">
                <strong>Validation Checks Performed:</strong><br>
                ✅ File upload errors<br>
                ✅ File size limits<br>
                ✅ MIME type verification<br>
                ✅ Extension/MIME mismatch detection<br>
                ✅ Optional: Virus scanning with ClamAV
            </div>
        </div>

        <!-- Example 4: API Rate Limiting -->
        <div class="demo-section">
            <h2>🔌 Example 4: API Endpoint Protection</h2>
            
            <div class="scenario">
                <h3>Scenario: Protect REST API endpoints</h3>
                <p>Different rate limits for different users and endpoints</p>
            </div>

            <div class="code-block">
<span class="comment">// In your API endpoint (e.g., api/patients.php)</span>
<span class="keyword">require_once</span> <span class="string">'security_network.php'</span>;

<span class="comment">// Disable auto rate limiting</span>
<span class="keyword">define</span>(<span class="string">'DISABLE_AUTO_SECURITY'</span>, <span class="keyword">true</span>);

<span class="comment">// Get user from session/token</span>
<span class="keyword">$user_id</span> = <span class="keyword">$_SESSION</span>[<span class="string">'user_id'</span>] ?? <span class="keyword">null</span>;
<span class="keyword">$user_role</span> = <span class="keyword">$_SESSION</span>[<span class="string">'role'</span>] ?? <span class="string">'guest'</span>;

<span class="comment">// Different limits based on role</span>
<span class="keyword">switch</span> (<span class="keyword">$user_role</span>) {
    <span class="keyword">case</span> <span class="string">'admin'</span>:
        <span class="keyword">$limit</span> = 100;  <span class="comment">// 100 requests per minute</span>
        <span class="keyword">break</span>;
    <span class="keyword">case</span> <span class="string">'doctor'</span>:
    <span class="keyword">case</span> <span class="string">'therapist'</span>:
        <span class="keyword">$limit</span> = 60;   <span class="comment">// 60 requests per minute</span>
        <span class="keyword">break</span>;
    <span class="keyword">default</span>:
        <span class="keyword">$limit</span> = 30;   <span class="comment">// 30 requests per minute</span>
}

<span class="comment">// Apply rate limit</span>
<span class="function">apply_rate_limit</span>(<span class="string">'api_'</span> . <span class="keyword">$user_id</span>, <span class="keyword">$limit</span>, 60);

<span class="comment">// Process API request...</span>
            </div>

            <div class="grid">
                <div class="card">
                    <h3>Admin Users</h3>
                    <p><strong>100</strong> requests/min</p>
                    <p>Highest access for system management</p>
                </div>
                <div class="card">
                    <h3>Medical Staff</h3>
                    <p><strong>60</strong> requests/min</p>
                    <p>Moderate access for patient care</p>
                </div>
                <div class="card">
                    <h3>Other Users</h3>
                    <p><strong>30</strong> requests/min</p>
                    <p>Standard access limit</p>
                </div>
            </div>
        </div>

        <!-- Example 5: Security Event Logging -->
        <div class="demo-section">
            <h2>📝 Example 5: Security Event Logging</h2>
            
            <div class="scenario">
                <h3>Scenario: Track suspicious activities</h3>
                <p>Log security events for audit trail and threat detection</p>
            </div>

            <div class="code-block">
<span class="comment">// Track failed login attempts</span>
<span class="keyword">if</span> (<span class="keyword">$login_failed</span>) {
    <span class="function">log_security_event</span>(<span class="string">'FAILED_LOGIN'</span>, [
        <span class="string">'username'</span> => <span class="keyword">$username</span>,
        <span class="string">'reason'</span> => <span class="string">'Invalid password'</span>
    ]);
}

<span class="comment">// Track unauthorized access attempts</span>
<span class="keyword">if</span> (!<span class="keyword">$user_has_permission</span>) {
    <span class="function">log_security_event</span>(<span class="string">'UNAUTHORIZED_ACCESS'</span>, [
        <span class="string">'user_id'</span> => <span class="keyword">$user_id</span>,
        <span class="string">'attempted_resource'</span> => <span class="keyword">$_SERVER</span>[<span class="string">'REQUEST_URI'</span>]
    ]);
}

<span class="comment">// Track data export attempts</span>
<span class="function">log_security_event</span>(<span class="string">'DATA_EXPORT'</span>, [
    <span class="string">'user_id'</span> => <span class="keyword">$user_id</span>,
    <span class="string">'export_type'</span> => <span class="string">'patient_records'</span>,
    <span class="string">'record_count'</span> => <span class="function">count</span>(<span class="keyword">$records</span>)
]);
            </div>

            <?php
            // Demo logging
            $demo_events = [
                ['type' => 'FAILED_LOGIN', 'ip' => '192.168.1.100', 'username' => 'hacker123'],
                ['type' => 'UNAUTHORIZED_ACCESS', 'ip' => '10.0.0.50', 'resource' => '/admin/settings'],
                ['type' => 'DATA_EXPORT', 'ip' => '192.168.1.10', 'records' => 150]
            ];
            ?>

            <div class="output-box">
                <strong>Sample Security Log Entries:</strong>
                <table>
                    <tr>
                        <th>Timestamp</th>
                        <th>Event Type</th>
                        <th>IP Address</th>
                        <th>Details</th>
                    </tr>
                    <?php foreach ($demo_events as $event): ?>
                    <tr>
                        <td><?php echo date('Y-m-d H:i:s'); ?></td>
                        <td><?php echo $event['type']; ?></td>
                        <td><?php echo $event['ip']; ?></td>
                        <td><?php echo isset($event['username']) ? 'User: ' . $event['username'] : 
                                          (isset($event['resource']) ? 'Resource: ' . $event['resource'] : 
                                          'Records: ' . $event['records']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>

        <!-- Integration Checklist -->
        <div class="demo-section">
            <h2>✅ Integration Checklist</h2>
            
            <table>
                <tr>
                    <th>Step</th>
                    <th>Action</th>
                    <th>File</th>
                </tr>
                <tr>
                    <td>1</td>
                    <td>Include security_network.php at the top of sensitive pages</td>
                    <td>All dashboard files, API endpoints</td>
                </tr>
                <tr>
                    <td>2</td>
                    <td>Add custom rate limiting to login endpoints</td>
                    <td>index.php, login.php</td>
                </tr>
                <tr>
                    <td>3</td>
                    <td>Validate file uploads before processing</td>
                    <td>patient_management.php, any upload handlers</td>
                </tr>
                <tr>
                    <td>4</td>
                    <td>Log security events for audit trail</td>
                    <td>All authentication and authorization code</td>
                </tr>
                <tr>
                    <td>5</td>
                    <td>Test with test_network_security.php</td>
                    <td>Verify all features work correctly</td>
                </tr>
            </table>
        </div>

        <!-- Function Reference -->
        <div class="demo-section">
            <h2>📚 Quick Function Reference</h2>
            
            <table>
                <tr>
                    <th>Function</th>
                    <th>Purpose</th>
                    <th>Parameters</th>
                </tr>
                <tr>
                    <td><code>send_security_headers()</code></td>
                    <td>Send HTTP security headers</td>
                    <td>None</td>
                </tr>
                <tr>
                    <td><code>enforce_https()</code></td>
                    <td>Redirect to HTTPS (production only)</td>
                    <td>None</td>
                </tr>
                <tr>
                    <td><code>rate_limit($key, $limit, $seconds)</code></td>
                    <td>Token bucket rate limiter</td>
                    <td>$key, $limit=30, $seconds=60</td>
                </tr>
                <tr>
                    <td><code>apply_rate_limit($id, $limit, $window)</code></td>
                    <td>Rate limit with auto HTTP 429 response</td>
                    <td>$id, $limit=30, $window=60</td>
                </tr>
                <tr>
                    <td><code>validate_file_upload($file, $types, $size)</code></td>
                    <td>Validate uploaded file</td>
                    <td>$file, $types=[], $size=5MB</td>
                </tr>
                <tr>
                    <td><code>scan_file_with_clamscan($path)</code></td>
                    <td>Scan file for viruses</td>
                    <td>$path</td>
                </tr>
                <tr>
                    <td><code>get_client_ip()</code></td>
                    <td>Get client IP (proxy-aware)</td>
                    <td>None</td>
                </tr>
                <tr>
                    <td><code>log_security_event($type, $context)</code></td>
                    <td>Log security event</td>
                    <td>$type, $context=[]</td>
                </tr>
            </table>
        </div>
    </div>
</body>
</html>
