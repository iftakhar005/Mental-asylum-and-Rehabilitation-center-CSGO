<?php

class MentalHealthSecurityManager {
    private $conn;
    private $failed_attempts = [];
    private $banned_clients = [];
    private $max_login_attempts = 3;
    private $max_ban_attempts = 10; 
    private $lockout_duration = 600;
    private $ban_duration = 300;  
    
    public function __construct($database_connection) {
        $this->conn = $database_connection;
        $this->initializeSession();
        $this->initializeFailedAttempts();
        $this->initializeBannedClients();
    }
    
    private function initializeSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    private function initializeFailedAttempts() {
        if (!isset($_SESSION['security_failed_attempts'])) {
            $_SESSION['security_failed_attempts'] = [];
        }
        $this->failed_attempts = $_SESSION['security_failed_attempts'];
    }
    
    private function initializeBannedClients() {
        if (!isset($_SESSION['security_banned_clients'])) {
            $_SESSION['security_banned_clients'] = [];
        }
        $this->banned_clients = $_SESSION['security_banned_clients'];
        
  
        $this->cleanExpiredBans();
    }
    

    public function secureQuery($sql, $params = [], $types = '') {
      
        if (!$this->isQuerySafe($sql)) {
            $this->logSecurityEvent('BLOCKED_QUERY', ['query' => $sql]);
            throw new Exception("Potentially dangerous query blocked for security");
        }
        
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Query preparation failed: " . $this->conn->error);
        }
        
        if (!empty($params)) {
            if (empty($types)) {
                $types = $this->autoDetectTypes($params);
            }
            $stmt->bind_param($types, ...$params);
        }
        
        if (!$stmt->execute()) {
            throw new Exception("Query execution failed: " . $stmt->error);
        }
        
        return $stmt;
    }
    
  
    public function secureSelect($sql, $params = [], $types = '') {
        $stmt = $this->secureQuery($sql, $params, $types);
        $result = $stmt->get_result();
        $stmt->close();
        return $result;
    }

    public function secureExecute($sql, $params = [], $types = '') {
        $stmt = $this->secureQuery($sql, $params, $types);
        $affected_rows = $stmt->affected_rows;
        $insert_id = $this->conn->insert_id;
        $stmt->close();
        return [
            'affected_rows' => $affected_rows,
            'insert_id' => $insert_id,
            'success' => $affected_rows > 0
        ];
    }
    
    private function autoDetectTypes($params) {
        $types = '';
        foreach ($params as $param) {
            if (is_int($param)) {
                $types .= 'i';
            } elseif (is_float($param)) {
                $types .= 'd';
            } else {
                $types .= 's';
            }
        }
        return $types;
    }
    
    /**
     * 2. INPUT LENGTH RESTRICTIONS AND SANITIZATION
     */
    public function validateInput($input, $rules = []) {
        $defaults = [
            'max_length' => 255,
            'min_length' => 0,
            'type' => 'string',
            'required' => true,
            'allow_html' => false
        ];
        
        $rules = array_merge($defaults, $rules);
        
        // Handle null/empty input
        if ($input === null || $input === '') {
            if ($rules['required']) {
                throw new Exception("Input is required but was empty");
            }
            return '';
        }
        
        $input = trim($input);
        
        // Length validation
        if (strlen($input) > $rules['max_length']) {
            throw new Exception("Input exceeds maximum length of {$rules['max_length']} characters");
        }
        
        if (strlen($input) < $rules['min_length']) {
            throw new Exception("Input is below minimum length of {$rules['min_length']} characters");
        }
        
        // Type-specific validation
        switch ($rules['type']) {
            case 'email':
                if (!$this->validateEmail($input)) {
                    throw new Exception("Invalid email format");
                }
                break;
            case 'name':
                if (!$this->validateName($input)) {
                    throw new Exception("Invalid name format - only letters, spaces, hyphens and apostrophes allowed");
                }
                break;
            case 'phone':
                if (!$this->validatePhone($input)) {
                    throw new Exception("Invalid phone number format");
                }
                break;
            case 'numeric':
                if (!is_numeric($input)) {
                    throw new Exception("Input must be numeric");
                }
                break;
            case 'date':
                if (!$this->validateDate($input)) {
                    throw new Exception("Invalid date format");
                }
                break;
            case 'alphanumeric':
                if (!ctype_alnum(str_replace([' ', '-', '_'], '', $input))) {
                    throw new Exception("Input must be alphanumeric");
                }
                break;
        }
        
        // Sanitization
        $input = $this->sanitizeInput($input, $rules['allow_html']);
        
        return $input;
    }
    
    private function validateEmail($email) {
        return preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $email);
    }
    
    private function validateName($name) {
        return preg_match('/^[a-zA-Z\s\-\'\.]+$/', $name) && strlen($name) >= 2;
    }
    
    private function validatePhone($phone) {
        $cleaned = preg_replace('/[\s\-\(\)+]/', '', $phone);
        return preg_match('/^[0-9]{10,15}$/', $cleaned);
    }
    
    private function validateDate($date) {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
    
    private function sanitizeInput($input, $allow_html = false) {
        // Remove null bytes
        $input = str_replace("\0", '', $input);
        
        if (!$allow_html) {
            // Remove HTML tags
            $input = strip_tags($input);
        }
        
        // Remove dangerous JavaScript patterns
        $dangerous_patterns = [
            'javascript:',
            'vbscript:',
            'onload=',
            'onerror=',
            'onclick=',
            'onmouseover=',
            'onfocus=',
            'onblur=',
            'onchange=',
            'onsubmit=',
            '<script',
            '</script>'
        ];
        
        foreach ($dangerous_patterns as $pattern) {
            $input = str_ireplace($pattern, '', $input);
        }
        
        return $input;
    }
    
    /**
     * 3. SQL INJECTION PATTERN BLOCKING
     */
    public function detectSQLInjection($input) {
        $input = strtolower($input);
        
        $injection_patterns = [
            // Union attacks
            '/union\s+(all\s+)?select/i',
            '/union\s+(all\s+)?select\s+null/i',
            
            // Boolean-based blind injection
            '/\'\s*(or|and)\s*\'\w*\'\s*=\s*\'\w*\'/i',
            '/\'\s*(or|and)\s*1\s*=\s*1/i',
            '/\'\s*(or|and)\s*1\s*=\s*0/i',
            '/\'\s*(or|and)\s*true/i',
            '/\'\s*(or|and)\s*false/i',
            
            // Time-based blind injection
            '/sleep\s*\(/i',
            '/benchmark\s*\(/i',
            '/waitfor\s+delay/i',
            '/pg_sleep\s*\(/i',
            
            // Error-based injection
            '/extractvalue\s*\(/i',
            '/updatexml\s*\(/i',
            '/xpath\s*\(/i',
            
            // Stacked queries
            '/;\s*(drop|delete|update|insert|create|alter|grant|revoke)/i',
            
            // Comment-based injection
            '/\/\*.*\*\//i',
            '/--\s/',
            '/#/',
            
            // Information schema attacks
            '/information_schema\./i',
            '/sys\./i',
            '/mysql\./i',
            '/performance_schema\./i',
            
            // File operations
            '/load_file\s*\(/i',
            '/into\s+(outfile|dumpfile)/i',
            
            // Database functions
            '/database\s*\(\s*\)/i',
            '/version\s*\(\s*\)/i',
            '/user\s*\(\s*\)/i',
            '/current_user/i',
            '/connection_id\s*\(\s*\)/i',
            
            // Hex encoding
            '/0x[0-9a-f]+/i',
            
            // Concatenation functions
            '/concat\s*\(/i',
            '/group_concat\s*\(/i',
            
            // Conditional statements
            '/if\s*\(/i',
            '/case\s+when/i',
            '/when\s+.*\s+then/i',
            
            // System commands
            '/xp_cmdshell/i',
            '/sp_configure/i',
            '/sp_executesql/i'
        ];
        
        foreach ($injection_patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }
        
        return false;
    }
    
    private function isQuerySafe($query) {
   
        if (preg_match('/;\s*\w+/', $query)) {
            return false;
        }
        
        // Check for SQL injection patterns
        if ($this->detectSQLInjection($query)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * 4. CAPTCHA SYSTEM FOR FAILED LOGIN ATTEMPTS
     */
    public function recordFailedLogin($identifier = null) {
        if ($identifier === null) {
            $identifier = $this->getClientIdentifier();
        }
        
        // Check if client is already banned
        if ($this->isClientBanned($identifier)) {
            $this->logSecurityEvent('FAILED_LOGIN_BANNED', ['identifier' => $identifier]);
            return;
        }
        
        $current_time = time();
        
        if (!isset($this->failed_attempts[$identifier])) {
            $this->failed_attempts[$identifier] = [];
        }
        
        // Clean old attempts
        $this->failed_attempts[$identifier] = array_filter(
            $this->failed_attempts[$identifier],
            function($timestamp) use ($current_time) {
                return ($current_time - $timestamp) < $this->lockout_duration;
            }
        );
        
        // Add new failed attempt
        $this->failed_attempts[$identifier][] = $current_time;
        $_SESSION['security_failed_attempts'] = $this->failed_attempts;
        
        // Check if client should be banned (10+ attempts)
        if (count($this->failed_attempts[$identifier]) >= $this->max_ban_attempts) {
            $this->banClient($identifier);
            $this->logSecurityEvent('CLIENT_BANNED', ['identifier' => $identifier, 'attempts' => count($this->failed_attempts[$identifier])]);
        } else {
            $this->logSecurityEvent('FAILED_LOGIN', ['identifier' => $identifier, 'attempts' => count($this->failed_attempts[$identifier])]);
        }
    }
    
    public function needsCaptcha($identifier = null) {
        if ($identifier === null) {
            $identifier = $this->getClientIdentifier();
        }
        
        if (!isset($this->failed_attempts[$identifier])) {
            return false;
        }
        
        return count($this->failed_attempts[$identifier]) >= $this->max_login_attempts;
    }
    
    /**
     * BAN SYSTEM - 5 minute ban after 10 attempts
     */
    public function isClientBanned($identifier = null) {
        if ($identifier === null) {
            $identifier = $this->getClientIdentifier();
        }
        
        if (!isset($this->banned_clients[$identifier])) {
            return false;
        }
        
        $ban_time = $this->banned_clients[$identifier];
        $current_time = time();
        
        // Check if ban has expired
        if (($current_time - $ban_time) >= $this->ban_duration) {
            $this->unbanClient($identifier);
            return false;
        }
        
        return true;
    }
    
    public function banClient($identifier = null) {
        if ($identifier === null) {
            $identifier = $this->getClientIdentifier();
        }
        
        $this->banned_clients[$identifier] = time();
        $_SESSION['security_banned_clients'] = $this->banned_clients;
        
        // Clear failed attempts after banning
        if (isset($this->failed_attempts[$identifier])) {
            unset($this->failed_attempts[$identifier]);
            $_SESSION['security_failed_attempts'] = $this->failed_attempts;
        }
    }
    
    public function unbanClient($identifier = null) {
        if ($identifier === null) {
            $identifier = $this->getClientIdentifier();
        }
        
        if (isset($this->banned_clients[$identifier])) {
            unset($this->banned_clients[$identifier]);
            $_SESSION['security_banned_clients'] = $this->banned_clients;
        }
    }
    
    public function getBanTimeRemaining($identifier = null) {
        if ($identifier === null) {
            $identifier = $this->getClientIdentifier();
        }
        
        if (!$this->isClientBanned($identifier)) {
            return 0;
        }
        
        $ban_time = $this->banned_clients[$identifier];
        $current_time = time();
        $elapsed = $current_time - $ban_time;
        
        return max(0, $this->ban_duration - $elapsed);
    }
    
    private function cleanExpiredBans() {
        $current_time = time();
        
        foreach ($this->banned_clients as $identifier => $ban_time) {
            if (($current_time - $ban_time) >= $this->ban_duration) {
                unset($this->banned_clients[$identifier]);
            }
        }
        
        $_SESSION['security_banned_clients'] = $this->banned_clients;
    }
    
    public function generateCaptcha() {
        $operations = [
            ['symbol' => '+', 'name' => 'plus'],
            ['symbol' => '-', 'name' => 'minus'],
            ['symbol' => '*', 'name' => 'times']
        ];
        
        $num1 = rand(1, 15);
        $num2 = rand(1, 10);
        $operation = $operations[rand(0, 2)];
        
        switch ($operation['symbol']) {
            case '+':
                $answer = $num1 + $num2;
                break;
            case '-':
                // Ensure positive result
                if ($num1 < $num2) {
                    list($num1, $num2) = [$num2, $num1];
                }
                $answer = $num1 - $num2;
                break;
            case '*':
                $answer = $num1 * $num2;
                break;
        }
        
        $_SESSION['captcha_answer'] = $answer;
        $_SESSION['captcha_time'] = time();
        $_SESSION['captcha_question'] = "What is {$num1} {$operation['symbol']} {$num2}?";
        
        return [
            'question' => "What is {$num1} {$operation['symbol']} {$num2}?",
            'answer' => $answer
        ];
    }
    
    public function validateCaptcha($user_answer) {
        if (!isset($_SESSION['captcha_answer']) || !isset($_SESSION['captcha_time'])) {
            return false;
        }
        
        // Check if captcha has expired (5 minutes)
        if (time() - $_SESSION['captcha_time'] > 300) {
            unset($_SESSION['captcha_answer'], $_SESSION['captcha_time'], $_SESSION['captcha_question']);
            return false;
        }
        
        $is_valid = (int)$user_answer === (int)$_SESSION['captcha_answer'];
        
        if ($is_valid) {
            unset($_SESSION['captcha_answer'], $_SESSION['captcha_time'], $_SESSION['captcha_question']);
        }
        
        return $is_valid;
    }
    
    public function clearFailedAttempts($identifier = null) {
        if ($identifier === null) {
            $identifier = $this->getClientIdentifier();
        }
        
        if (isset($this->failed_attempts[$identifier])) {
            unset($this->failed_attempts[$identifier]);
            $_SESSION['security_failed_attempts'] = $this->failed_attempts;
        }
    }
    
    private function getClientIdentifier() {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        return hash('sha256', $ip . '|' . $user_agent);
    }
    
    /**
     * 5. XSS PREVENTION - Custom implementation
     */
    public function preventXSS($input, $context = 'html') {
        if ($input === null || $input === '') {
            return '';
        }
        
        $input = (string)$input;
        
        switch ($context) {
            case 'html':
                return $this->escapeHTML($input);
            case 'attribute':
                return $this->escapeHTMLAttribute($input);
            case 'javascript':
                return $this->escapeJavaScript($input);
            case 'css':
                return $this->escapeCSS($input);
            case 'url':
                return $this->escapeURL($input);
            default:
                return $this->escapeHTML($input);
        }
    }
    
    private function escapeHTML($input) {
        $translations = [
            '&' => '&amp;',
            '<' => '&lt;',
            '>' => '&gt;',
            '"' => '&quot;',
            "'" => '&#x27;',
            '/' => '&#x2F;'
        ];
        
        return strtr($input, $translations);
    }
    
    private function escapeHTMLAttribute($input) {
        $safe = $this->escapeHTML($input);
        // Additional escaping for attributes
        $safe = str_replace(["\n", "\r", "\t"], ['&#10;', '&#13;', '&#9;'], $safe);
        return $safe;
    }
    
    private function escapeJavaScript($input) {
        $translations = [
            '\\' => '\\\\',
            '"' => '\\"',
            "'" => "\\'",
            "\n" => '\\n',
            "\r" => '\\r',
            "\t" => '\\t',
            "\f" => '\\f',
            "\b" => '\\b',
            '<' => '\\u003C',
            '>' => '\\u003E',
            '&' => '\\u0026',
            '=' => '\\u003D'
        ];
        
        return strtr($input, $translations);
    }
    
    private function escapeCSS($input) {
        // Remove any characters that could break CSS
        return preg_replace('/[^a-zA-Z0-9\-_]/', '', $input);
    }
    
    private function escapeURL($input) {
        return rawurlencode($input);
    }
    
    /**
     * Batch process form data with validation rules
     */
    public function processFormData($data, $rules = []) {
        $processed = [];
        $errors = [];
        
        foreach ($data as $field => $value) {
            try {
                if (isset($rules[$field])) {
                    $processed[$field] = $this->validateInput($value, $rules[$field]);
                } else {
                    // Default validation
                    $processed[$field] = $this->validateInput($value, ['required' => false]);
                }
            } catch (Exception $e) {
                $errors[$field] = $e->getMessage();
            }
        }
        
        if (!empty($errors)) {
            throw new Exception("Validation errors: " . json_encode($errors));
        }
        
        return $processed;
    }
    
    /**
     * Security logging
     */
    public function logSecurityEvent($event_type, $details = []) {
        $log_entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'session_id' => session_id(),
            'user_id' => $_SESSION['user_id'] ?? null,
            'event_type' => $event_type,
            'details' => $details
        ];
        
        $log_file = __DIR__ . '/logs/security.log';
        
        // Create logs directory if it doesn't exist
        $log_dir = dirname($log_file);
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        
        file_put_contents($log_file, json_encode($log_entry) . "\n", FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Generate secure random token
     */
    public function generateSecureToken($length = 32) {
        return bin2hex(random_bytes($length / 2));
    }
    
    /**
     * Validate CSRF token
     */
    public function validateCSRFToken($token) {
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Generate CSRF token
     */
    public function generateCSRFToken() {
        $_SESSION['csrf_token'] = $this->generateSecureToken();
        return $_SESSION['csrf_token'];
    }
    
    /**
     * PUBLIC TESTING METHODS FOR DEMONSTRATION
     */
    public function testQuerySafety($query) {
        return $this->isQuerySafe($query);
    }
    
    public function testSQLInjectionDetection($input) {
        return $this->detectSQLInjection($input);
    }
}

// Initialize global security manager instance
if (isset($conn)) {
    $securityManager = new MentalHealthSecurityManager($conn);
}
?>