<?php
/**
 * Network Security Module
 * 
 * Provides HTTPS enforcement, security headers, rate limiting, and file scanning
 */

/**
 * Enforce HTTPS for non-localhost environments
 * 
 * Redirects HTTP requests to HTTPS for production/staging environments.
 * Skips localhost and 127.0.0.1 for development.
 */
function enforce_https() {
    // Check if already on HTTPS
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        return;
    }
    
    // Get the server name
    $server_name = $_SERVER['SERVER_NAME'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Skip HTTPS enforcement for localhost/development
    $is_localhost = in_array($server_name, ['localhost', '127.0.0.1', '::1']) ||
                    strpos($server_name, 'localhost') !== false;
    
    if ($is_localhost) {
        // Development environment - allow HTTP
        return;
    }
    
    // Production/staging - enforce HTTPS
    $redirect_url = "https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    header("Location: $redirect_url", true, 301);
    exit();
}

/**
 * Send security headers to protect against common web vulnerabilities
 */
function send_security_headers() {
    // Prevent MIME type sniffing
    header('X-Content-Type-Options: nosniff');
    
    // Control referrer information
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    // Prevent clickjacking attacks
    header('X-Frame-Options: SAMEORIGIN');
    
    // Enable XSS protection (legacy browsers)
    header('X-XSS-Protection: 1; mode=block');
    
    // Content Security Policy (permissive for now - tighten in production)
    // Allows inline scripts/styles for compatibility
    $csp = "default-src 'self'; " .
           "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net; " .
           "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com; " .
           "font-src 'self' https://cdnjs.cloudflare.com https://fonts.gstatic.com data:; " .
           "img-src 'self' data: https:; " .
           "connect-src 'self'; " .
           "frame-ancestors 'self';";
    
    header("Content-Security-Policy: $csp");
    
    // Strict Transport Security (only for HTTPS)
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        // Tell browsers to only use HTTPS for 1 year
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
    
    // Permissions Policy (formerly Feature Policy)
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
}

/**
 * Token bucket rate limiter
 * 
 * @param string $key - Unique identifier (e.g., user ID, IP address)
 * @param int $limit - Maximum number of requests allowed in the time window
 * @param int $refill_seconds - Time window in seconds
 * @return bool - True if request is allowed, false if rate limited
 */
function rate_limit($key, $limit = 30, $refill_seconds = 60) {
    $temp_dir = sys_get_temp_dir();
    $file_path = $temp_dir . DIRECTORY_SEPARATOR . 'rate_limit_' . md5($key) . '.json';
    
    $now = time();
    
    // Load existing bucket or create new
    if (file_exists($file_path)) {
        $data = json_decode(file_get_contents($file_path), true);
        if (!$data) {
            // Corrupted file, reset
            $data = ['tokens' => $limit, 'last_refill' => $now];
        }
    } else {
        $data = ['tokens' => $limit, 'last_refill' => $now];
    }
    
    // Calculate tokens to add based on time elapsed
    $time_elapsed = $now - $data['last_refill'];
    $tokens_to_add = floor($time_elapsed / $refill_seconds * $limit);
    
    if ($tokens_to_add > 0) {
        $data['tokens'] = min($limit, $data['tokens'] + $tokens_to_add);
        $data['last_refill'] = $now;
    }
    
    // Check if request can be allowed
    if ($data['tokens'] >= 1) {
        $data['tokens'] -= 1;
        file_put_contents($file_path, json_encode($data));
        return true; // Request allowed
    }
    
    // Rate limit exceeded
    file_put_contents($file_path, json_encode($data));
    return false;
}

/**
 * Scan file with ClamAV antivirus
 * 
 * @param string $path - Full path to file to scan
 * @return array - ['available' => bool, 'infected' => bool|null, 'output' => string]
 */
function scan_file_with_clamscan($path) {
    $result = [
        'available' => false,
        'infected' => null,
        'output' => ''
    ];
    
    // Check if file exists
    if (!file_exists($path)) {
        $result['output'] = 'File not found';
        return $result;
    }
    
    // Check if clamscan is available
    $clamscan_path = 'clamscan'; // Adjust path if needed
    
    // Try to execute clamscan
    $command = escapeshellcmd($clamscan_path) . ' ' . escapeshellarg($path) . ' 2>&1';
    $output = [];
    $return_code = 0;
    
    exec($command, $output, $return_code);
    
    $result['output'] = implode("\n", $output);
    
    // Check if clamscan is installed
    if (strpos($result['output'], 'not found') !== false || 
        strpos($result['output'], 'command not found') !== false) {
        $result['available'] = false;
        $result['output'] = 'ClamAV not installed';
        return $result;
    }
    
    $result['available'] = true;
    
    // Parse clamscan output
    // Return code: 0 = clean, 1 = infected, 2 = error
    if ($return_code === 0) {
        $result['infected'] = false;
    } elseif ($return_code === 1) {
        $result['infected'] = true;
    } else {
        $result['infected'] = null;
        $result['output'] = 'Scan error: ' . $result['output'];
    }
    
    return $result;
}

/**
 * Get client IP address (handles proxies)
 * 
 * @return string - Client IP address
 */
function get_client_ip() {
    $ip_keys = [
        'HTTP_CLIENT_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_FORWARDED',
        'HTTP_X_CLUSTER_CLIENT_IP',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
        'REMOTE_ADDR'
    ];
    
    foreach ($ip_keys as $key) {
        if (array_key_exists($key, $_SERVER)) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                
                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP, 
                    FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Log security event with IP and timestamp
 * 
 * @param string $event_type - Type of security event
 * @param array $context - Additional context data
 */
function log_security_event($event_type, array $context = []) {
    $timestamp = date('Y-m-d H:i:s');
    $ip = get_client_ip();
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    
    $log_message = sprintf(
        "[%s] SECURITY: %s | IP: %s | User-Agent: %s | Context: %s",
        $timestamp,
        $event_type,
        $ip,
        $user_agent,
        json_encode($context)
    );
    
    error_log($log_message);
}

/**
 * Validate file upload for security
 * 
 * @param array $file - $_FILES array element
 * @param array $allowed_types - Array of allowed MIME types
 * @param int $max_size - Maximum file size in bytes
 * @return array - ['success' => bool, 'message' => string]
 */
function validate_file_upload($file, $allowed_types = [], $max_size = 5242880) {
    // Default allowed types (images and documents)
    if (empty($allowed_types)) {
        $allowed_types = [
            'image/jpeg', 'image/png', 'image/gif',
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];
    }
    
    // Check if file was uploaded
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['success' => false, 'message' => 'Invalid file upload'];
    }
    
    // Check for upload errors
    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            return ['success' => false, 'message' => 'No file uploaded'];
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return ['success' => false, 'message' => 'File exceeds size limit'];
        default:
            return ['success' => false, 'message' => 'Unknown upload error'];
    }
    
    // Check file size
    if ($file['size'] > $max_size) {
        return ['success' => false, 'message' => 'File too large (max ' . ($max_size / 1024 / 1024) . 'MB)'];
    }
    
    // Verify MIME type
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime_type = $finfo->file($file['tmp_name']);
    
    if (!in_array($mime_type, $allowed_types)) {
        log_security_event('INVALID_FILE_TYPE', [
            'mime_type' => $mime_type,
            'filename' => $file['name']
        ]);
        return ['success' => false, 'message' => 'File type not allowed'];
    }
    
    // Check file extension matches MIME type
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $mime_to_ext = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/gif' => ['gif'],
        'application/pdf' => ['pdf'],
        'application/msword' => ['doc'],
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx']
    ];
    
    $valid_extension = false;
    if (isset($mime_to_ext[$mime_type])) {
        $valid_extension = in_array($extension, $mime_to_ext[$mime_type]);
    }
    
    if (!$valid_extension) {
        log_security_event('EXTENSION_MISMATCH', [
            'mime_type' => $mime_type,
            'extension' => $extension,
            'filename' => $file['name']
        ]);
        return ['success' => false, 'message' => 'File extension does not match file type'];
    }
    
    return ['success' => true, 'message' => 'File validation passed'];
}

/**
 * Apply rate limiting to current request
 * Returns HTTP 429 and exits if rate limit exceeded
 * 
 * @param string $identifier - Unique identifier for rate limiting
 * @param int $limit - Maximum requests allowed
 * @param int $window - Time window in seconds
 */
function apply_rate_limit($identifier = null, $limit = 30, $window = 60) {
    // Use IP address if no identifier provided
    if ($identifier === null) {
        $identifier = get_client_ip();
    }
    
    if (!rate_limit($identifier, $limit, $window)) {
        log_security_event('RATE_LIMIT_EXCEEDED', [
            'identifier' => $identifier,
            'limit' => $limit,
            'window' => $window
        ]);
        
        http_response_code(429); // Too Many Requests
        header('Retry-After: ' . $window);
        
        echo json_encode([
            'error' => 'Rate limit exceeded',
            'message' => 'Too many requests. Please try again later.',
            'retry_after' => $window
        ]);
        exit();
    }
}

// Auto-apply security measures when file is included
// Can be disabled by defining DISABLE_AUTO_SECURITY before including this file

if (!defined('DISABLE_AUTO_SECURITY')) {
    // Send security headers
    send_security_headers();
    
    // Enforce HTTPS (skips localhost)
    enforce_https();
    
    // Apply rate limiting to POST/PUT/DELETE requests (not GET)
    if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'HEAD') {
        // Rate limit: 30 requests per minute per IP
        apply_rate_limit(get_client_ip(), 30, 60);
    }
}
?>
