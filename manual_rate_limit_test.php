<?php
/**
 * Manual Rate Limit Test
 * 
 * Click the button multiple times to test rate limiting
 */
require_once 'security_network.php';

$result = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ip = get_client_ip();
    $allowed = rate_limit('manual_test_' . $ip, 5, 60); // 5 requests per minute
    
    if ($allowed) {
        $result = '✅ Request ALLOWED - You have remaining requests';
    } else {
        http_response_code(429);
        $result = '🚫 RATE LIMITED - Too many requests. Wait 1 minute.';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manual Rate Limit Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
        }
        button {
            background: #4CAF50;
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin: 10px 0;
        }
        button:hover {
            background: #45a049;
        }
        .result {
            margin-top: 20px;
            padding: 15px;
            border-radius: 5px;
            font-size: 18px;
            font-weight: bold;
        }
        .allowed {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .limited {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .info {
            background: #d1ecf1;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
            border-left: 4px solid #17a2b8;
        }
        .counter {
            font-size: 48px;
            text-align: center;
            margin: 20px 0;
            color: #4CAF50;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>🔒 Manual Rate Limit Test</h1>
        
        <div class="info">
            <strong>Instructions:</strong><br>
            Click the button below multiple times (more than 5 times).<br>
            After 5 clicks, you should be rate limited.
        </div>
        
        <div class="info">
            <strong>Rate Limit:</strong> 5 requests per minute<br>
            <strong>Your IP:</strong> <?php echo get_client_ip(); ?>
        </div>
        
        <form method="POST">
            <button type="submit">🔘 Make Request (Click Me!)</button>
        </form>
        
        <div class="counter" id="counter">
            Click count: <span id="count">0</span>
        </div>
        
        <?php if ($result): ?>
            <div class="result <?php echo $allowed ? 'allowed' : 'limited'; ?>">
                <?php echo $result; ?>
            </div>
        <?php endif; ?>
        
        <div class="info" style="margin-top: 20px;">
            <strong>Expected Behavior:</strong><br>
            • First 5 clicks: ✅ ALLOWED<br>
            • 6th+ clicks: 🚫 RATE LIMITED<br>
            • After 1 minute: Tokens refill, allowed again
        </div>
        
        <p style="text-align: center; margin-top: 20px;">
            <a href="test_network_security.php" style="color: #007bff;">← Back to Automated Tests</a>
        </p>
    </div>
    
    <script>
        // Count button clicks
        let count = parseInt(localStorage.getItem('clickCount') || '0');
        document.getElementById('count').textContent = count;
        
        document.querySelector('form').addEventListener('submit', function() {
            count++;
            localStorage.setItem('clickCount', count);
            document.getElementById('count').textContent = count;
        });
        
        // Reset counter button
        if (count > 0) {
            const resetBtn = document.createElement('button');
            resetBtn.textContent = '🔄 Reset Counter';
            resetBtn.style.background = '#6c757d';
            resetBtn.type = 'button';
            resetBtn.onclick = function() {
                localStorage.setItem('clickCount', '0');
                location.reload();
            };
            document.querySelector('form').appendChild(resetBtn);
        }
    </script>
</body>
</html>
