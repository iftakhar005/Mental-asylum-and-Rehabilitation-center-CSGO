<?php


class PropagationPrevention {
    private $conn;
    private $session_fingerprint;
    private $user_id;
    private $current_role;
    
    // Configuration
    private $max_session_lifetime = 3600; // 1 hour
    private $session_rotation_interval = 900; // 15 minutes
    private $max_privilege_attempts = 3;
    private $propagation_block_duration = 1800; // 30 minutes
    
    public function __construct($database_connection) {
        $this->conn = $database_connection;
        $this->initializePropagationTables();
    }
    
    /**
     * Initialize database tables for propagation tracking
     */
    private function initializePropagationTables() {
        // Session tracking table
        $session_table = "CREATE TABLE IF NOT EXISTS session_tracking (
            id INT AUTO_INCREMENT PRIMARY KEY,
            session_id VARCHAR(255) NOT NULL,
            user_id INT NOT NULL,
            role VARCHAR(50) NOT NULL,
            fingerprint VARCHAR(255) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            user_agent TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            is_active TINYINT(1) DEFAULT 1,
            rotated_from VARCHAR(255) DEFAULT NULL,
            INDEX idx_session (session_id),
            INDEX idx_user (user_id),
            INDEX idx_fingerprint (fingerprint)
        )";
        
        // Privilege escalation attempts tracking
        $privilege_table = "CREATE TABLE IF NOT EXISTS privilege_escalation_tracking (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            session_id VARCHAR(255) NOT NULL,
            attempted_role VARCHAR(50) NOT NULL,
            current_role VARCHAR(50) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            user_agent TEXT NOT NULL,
            attempt_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            blocked TINYINT(1) DEFAULT 0,
            propagation_detected TINYINT(1) DEFAULT 0,
            INDEX idx_user_session (user_id, session_id),
            INDEX idx_timestamp (attempt_timestamp)
        )";
        
        // Propagation incidents table
        $propagation_table = "CREATE TABLE IF NOT EXISTS propagation_incidents (
            id INT AUTO_INCREMENT PRIMARY KEY,
            incident_type ENUM('session_hijacking', 'privilege_escalation') NOT NULL,
            user_id INT DEFAULT NULL,
            session_id VARCHAR(255) NOT NULL,
            original_fingerprint VARCHAR(255) NOT NULL,
            detected_fingerprint VARCHAR(255) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            user_agent TEXT NOT NULL,
            additional_data JSON DEFAULT NULL,
            detected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            blocked TINYINT(1) DEFAULT 1,
            severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'high',
            INDEX idx_type (incident_type),
            INDEX idx_session (session_id),
            INDEX idx_timestamp (detected_at)
        )";
        
        // Blocked sessions table
        $blocked_table = "CREATE TABLE IF NOT EXISTS blocked_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            session_id VARCHAR(255) NOT NULL,
            user_id INT DEFAULT NULL,
            fingerprint VARCHAR(255) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            block_reason VARCHAR(255) NOT NULL,
            blocked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expires_at TIMESTAMP NOT NULL,
            is_active TINYINT(1) DEFAULT 1,
            INDEX idx_session (session_id),
            INDEX idx_fingerprint (fingerprint),
            INDEX idx_expires (expires_at)
        )";
        
        // Execute table creation with error handling
        if (!$this->conn->query($session_table)) {
            error_log("Failed to create session_tracking table: " . $this->conn->error);
        }
        if (!$this->conn->query($privilege_table)) {
            error_log("Failed to create privilege_escalation_tracking table: " . $this->conn->error);
        }
        if (!$this->conn->query($propagation_table)) {
            error_log("Failed to create propagation_incidents table: " . $this->conn->error);
        }
        if (!$this->conn->query($blocked_table)) {
            error_log("Failed to create blocked_sessions table: " . $this->conn->error);
        }
    }
    
    /**
     * ========================================================================
     * SESSION HIJACKING PROPAGATION PREVENTION
     * ========================================================================
     */
    
    /**
     * Generate a unique fingerprint for the session
     * Manual implementation without using hash functions
     * Supports multi-device mode based on config
     */
    private function generateFingerprint() {
        // Load configuration
        if (!defined('FINGERPRINT_MODE')) {
            require_once __DIR__ . '/config.php';
        }
        
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $accept_language = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'unknown';
        $accept_encoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? 'unknown';
        
        // Build fingerprint based on mode
        $fingerprint_mode = defined('FINGERPRINT_MODE') ? FINGERPRINT_MODE : 'moderate';
        
        switch ($fingerprint_mode) {
            case 'strict':
                // Strict mode: IP + User Agent + Language + Encoding (single device only)
                $raw_fingerprint = $ip . '|' . $user_agent . '|' . $accept_language . '|' . $accept_encoding;
                break;
                
            case 'moderate':
                // Moderate mode: User Agent only (allows IP changes for mobile users)
                // This allows same device from different networks (mobile data, WiFi, etc.)
                $raw_fingerprint = $user_agent . '|' . $accept_language;
                break;
                
            case 'relaxed':
                // Relaxed mode: Minimal fingerprint (allows multiple devices)
                // Uses session data instead of browser fingerprint
                if (isset($_SESSION['propagation_user_id'])) {
                    $raw_fingerprint = 'user_' . $_SESSION['propagation_user_id'] . '_' . time();
                } else {
                    $raw_fingerprint = 'relaxed_' . time();
                }
                break;
                
            default:
                // Default to moderate
                $raw_fingerprint = $user_agent . '|' . $accept_language;
        }
        
        // Custom hash-like function (manual implementation)
        return $this->customHash($raw_fingerprint);
    }
    
    /**
     * Custom hash function (manual implementation without built-in hash functions)
     */
    private function customHash($input) {
        $hash = 0;
        $length = strlen($input);
        
        for ($i = 0; $i < $length; $i++) {
            $char_code = ord($input[$i]);
            $hash = (($hash << 5) - $hash) + $char_code;
            $hash = $hash & 0xFFFFFFFF; // Convert to 32-bit integer
        }
        
        // Convert to hexadecimal string
        return sprintf('%08x', $hash);
    }
    
    /**
     * Initialize session tracking
     */
    public function initializeSessionTracking($user_id, $role) {
        $this->user_id = $user_id;
        $this->current_role = $role;
        $this->session_fingerprint = $this->generateFingerprint();
        
        $session_id = session_id();
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        // Check if session is blocked
        if ($this->isSessionBlocked($session_id)) {
            $this->logPropagationIncident('session_hijacking', $user_id, 'Blocked session attempted to continue');
            $this->destroySession();
            return false;
        }
        
        // Store session tracking data
        $stmt = $this->conn->prepare(
            "INSERT INTO session_tracking 
            (session_id, user_id, role, fingerprint, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?)"
        );
        
        if ($stmt === false) {
            error_log("Failed to prepare statement in initializeSessionTracking: " . $this->conn->error);
            return false;
        }
        
        $stmt->bind_param('sissss', $session_id, $user_id, $role, $this->session_fingerprint, $ip, $user_agent);
        $stmt->execute();
        $stmt->close();
        
        // Store fingerprint in session
        $_SESSION['propagation_fingerprint'] = $this->session_fingerprint;
        $_SESSION['propagation_created_at'] = time();
        $_SESSION['propagation_last_rotation'] = time();
        $_SESSION['propagation_user_id'] = $user_id;
        $_SESSION['propagation_role'] = $role;
        
        return true;
    }
    
    /**
     * Validate session integrity - detect hijacking
     */
    public function validateSessionIntegrity() {
        // Check if session is initialized
        if (!isset($_SESSION['propagation_fingerprint'])) {
            // If user is logged in but propagation not initialized, initialize it now
            if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
                $this->initializeSessionTracking($_SESSION['user_id'], $_SESSION['role']);
                return true;
            }
            return false;
        }
        
        $session_id = session_id();
        $current_fingerprint = $this->generateFingerprint();
        $stored_fingerprint = $_SESSION['propagation_fingerprint'];
        
        // Check if session is blocked
        if ($this->isSessionBlocked($session_id)) {
            $this->logPropagationIncident('session_hijacking', $_SESSION['propagation_user_id'] ?? null, 'Blocked session detected');
            $this->destroySession();
            return false;
        }
        
        // Fingerprint mismatch - possible session hijacking
        if ($current_fingerprint !== $stored_fingerprint) {
            $this->detectSessionHijackingPropagation($session_id, $stored_fingerprint, $current_fingerprint);
            return false;
        }
        
        // Check session timeout
        $created_at = $_SESSION['propagation_created_at'] ?? 0;
        if (time() - $created_at > $this->max_session_lifetime) {
            $this->logPropagationIncident('session_hijacking', $_SESSION['propagation_user_id'] ?? null, 'Session expired');
            $this->destroySession();
            return false;
        }
        
        // Rotate session ID periodically
        $last_rotation = $_SESSION['propagation_last_rotation'] ?? 0;
        if (time() - $last_rotation > $this->session_rotation_interval) {
            $this->rotateSessionId();
        }
        
        // Update last activity
        $this->updateSessionActivity($session_id);
        
        return true;
    }
    
    /**
     * Detect session hijacking propagation
     */
    private function detectSessionHijackingPropagation($session_id, $original_fingerprint, $detected_fingerprint) {
        $user_id = $_SESSION['propagation_user_id'] ?? null;
        
        // Log the incident
        $this->logPropagationIncident(
            'session_hijacking',
            $user_id,
            'Fingerprint mismatch detected',
            [
                'original_fingerprint' => $original_fingerprint,
                'detected_fingerprint' => $detected_fingerprint,
                'session_age' => time() - ($_SESSION['propagation_created_at'] ?? time())
            ]
        );
        
        // Block the session
        $this->blockSession($session_id, $detected_fingerprint, 'Session hijacking detected');
        
        // Invalidate all sessions for this user
        if ($user_id) {
            $this->invalidateUserSessions($user_id);
        }
        
        // Destroy current session
        $this->destroySession();
    }
    
    /**
     * Rotate session ID to prevent fixation
     */
    private function rotateSessionId() {
        $old_session_id = session_id();
        
        // Regenerate session ID
        session_regenerate_id(true);
        
        $new_session_id = session_id();
        
        // Update tracking with new session ID
        $stmt = $this->conn->prepare(
            "UPDATE session_tracking SET session_id = ?, rotated_from = ? WHERE session_id = ?"
        );
        
        if ($stmt !== false) {
            $stmt->bind_param('sss', $new_session_id, $old_session_id, $old_session_id);
            $stmt->execute();
            $stmt->close();
        }
        
        // Update session rotation timestamp
        $_SESSION['propagation_last_rotation'] = time();
        
        return $new_session_id;
    }
    
    /**
     * Update session last activity
     */
    private function updateSessionActivity($session_id) {
        $stmt = $this->conn->prepare(
            "UPDATE session_tracking SET last_activity = CURRENT_TIMESTAMP WHERE session_id = ?"
        );
        
        if ($stmt !== false) {
            $stmt->bind_param('s', $session_id);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    /**
     * ========================================================================
     * PRIVILEGE ESCALATION PROPAGATION PREVENTION
     * ========================================================================
     */
    
    /**
     * Validate role access - prevent privilege escalation
     */
    public function validateRoleAccess($required_role) {
        // Check if session is valid
        if (!$this->validateSessionIntegrity()) {
            return false;
        }
        
        $current_role = $_SESSION['propagation_role'] ?? $_SESSION['role'] ?? null;
        $user_id = $_SESSION['propagation_user_id'] ?? $_SESSION['user_id'] ?? null;
        
        if (!$current_role || !$user_id) {
            return false;
        }
        
        // Define role hierarchy (lower number = higher privilege)
        $role_hierarchy = [
            'admin' => 1,
            'chief-staff' => 2,
            'doctor' => 3,
            'therapist' => 4,
            'nurse' => 5,
            'receptionist' => 6,
            'relative' => 7,
            'general_user' => 8
        ];
        
        $current_level = $role_hierarchy[$current_role] ?? 999;
        $required_level = $role_hierarchy[$required_role] ?? 0;
        
        // Check if user is trying to access higher privilege
        if ($current_level > $required_level) {
            $this->detectPrivilegeEscalationPropagation($user_id, $current_role, $required_role);
            return false;
        }
        
        // Verify role hasn't been tampered in session
        if (!$this->verifyRoleIntegrity($user_id, $current_role)) {
            $this->detectPrivilegeEscalationPropagation($user_id, $current_role, $required_role);
            return false;
        }
        
        return true;
    }
    
    /**
     * Verify role integrity from database
     */
    private function verifyRoleIntegrity($user_id, $session_role) {
        // Check users table
        $stmt = $this->conn->prepare("SELECT role FROM users WHERE id = ?");
        
        if ($stmt === false) {
            error_log("Failed to prepare statement in verifyRoleIntegrity (users): " . $this->conn->error);
            return false;
        }
        
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $db_role = $row['role'];
            $stmt->close();
            
            // Role found in users table - verify match
            return ($db_role === $session_role);
        }
        $stmt->close();
        
        // Not found in users table - check staff table
        $stmt = $this->conn->prepare("SELECT role FROM staff WHERE user_id = ?");
        
        if ($stmt === false) {
            error_log("Failed to prepare statement in verifyRoleIntegrity (staff): " . $this->conn->error);
            return false;
        }
        
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $db_role = $row['role'];
            $stmt->close();
            
            // Role found in staff table - verify match
            return ($db_role === $session_role);
        }
        $stmt->close();
        
        // Not found in either table
        return false;
    }
    
    /**
     * Detect privilege escalation propagation attempt
     */
    private function detectPrivilegeEscalationPropagation($user_id, $current_role, $attempted_role) {
        $session_id = session_id();
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        // Record the attempt
        $stmt = $this->conn->prepare(
            "INSERT INTO privilege_escalation_tracking 
            (user_id, session_id, attempted_role, current_role, ip_address, user_agent, blocked, propagation_detected) 
            VALUES (?, ?, ?, ?, ?, ?, 1, 1)"
        );
        
        if ($stmt !== false) {
            $stmt->bind_param('isssss', $user_id, $session_id, $attempted_role, $current_role, $ip, $user_agent);
            $stmt->execute();
            $stmt->close();
        }
        
        // Count recent attempts
        $stmt = $this->conn->prepare(
            "SELECT COUNT(*) as attempt_count FROM privilege_escalation_tracking 
            WHERE user_id = ? AND attempt_timestamp > DATE_SUB(NOW(), INTERVAL 1 HOUR)"
        );
        
        $attempt_count = 1; // Default
        if ($stmt !== false) {
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $attempt_count = $row['attempt_count'];
            $stmt->close();
        }
        
        // Log the incident
        $severity = $attempt_count >= $this->max_privilege_attempts ? 'critical' : 'high';
        $this->logPropagationIncident(
            'privilege_escalation',
            $user_id,
            'Unauthorized privilege escalation attempt',
            [
                'current_role' => $current_role,
                'attempted_role' => $attempted_role,
                'attempt_count' => $attempt_count
            ],
            $severity
        );
        
        // Block if too many attempts
        if ($attempt_count >= $this->max_privilege_attempts) {
            $fingerprint = $this->generateFingerprint();
            $this->blockSession($session_id, $fingerprint, 'Multiple privilege escalation attempts');
            $this->invalidateUserSessions($user_id);
            $this->destroySession();
        }
    }
    
    /**
     * Enforce role-based access control
     */
    public function enforceRBAC($required_role, $redirect_url = 'index.php') {
        if (!$this->validateRoleAccess($required_role)) {
            header('Location: ' . $redirect_url);
            exit();
        }
    }
    
    /**
     * ========================================================================
     * UTILITY METHODS
     * ========================================================================
     */
    
    /**
     * Log propagation incident
     */
    private function logPropagationIncident($incident_type, $user_id, $reason, $additional_data = [], $severity = 'high') {
        $session_id = session_id();
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $original_fingerprint = $_SESSION['propagation_fingerprint'] ?? 'unknown';
        $detected_fingerprint = $this->generateFingerprint();
        
        $additional_json = json_encode(array_merge($additional_data, ['reason' => $reason]));
        
        $stmt = $this->conn->prepare(
            "INSERT INTO propagation_incidents 
            (incident_type, user_id, session_id, original_fingerprint, detected_fingerprint, ip_address, user_agent, additional_data, severity) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        
        if ($stmt !== false) {
            $stmt->bind_param('sisssssss', $incident_type, $user_id, $session_id, $original_fingerprint, $detected_fingerprint, $ip, $user_agent, $additional_json, $severity);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    /**
     * Block a session
     */
    private function blockSession($session_id, $fingerprint, $reason) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_id = $_SESSION['propagation_user_id'] ?? null;
        $expires_at = date('Y-m-d H:i:s', time() + $this->propagation_block_duration);
        
        $stmt = $this->conn->prepare(
            "INSERT INTO blocked_sessions 
            (session_id, user_id, fingerprint, ip_address, block_reason, expires_at) 
            VALUES (?, ?, ?, ?, ?, ?)"
        );
        
        if ($stmt !== false) {
            $stmt->bind_param('sissss', $session_id, $user_id, $fingerprint, $ip, $reason, $expires_at);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    /**
     * Check if session is blocked
     */
    private function isSessionBlocked($session_id) {
        $stmt = $this->conn->prepare(
            "SELECT id FROM blocked_sessions 
            WHERE session_id = ? AND is_active = 1 AND expires_at > NOW()"
        );
        
        // Check if prepare failed
        if ($stmt === false) {
            error_log("Failed to prepare statement in isSessionBlocked: " . $this->conn->error);
            return false;
        }
        
        $stmt->bind_param('s', $session_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $is_blocked = $result->num_rows > 0;
        $stmt->close();
        
        return $is_blocked;
    }
    
    /**
     * Invalidate all sessions for a user
     */
    private function invalidateUserSessions($user_id) {
        // Mark all sessions as inactive
        $stmt = $this->conn->prepare(
            "UPDATE session_tracking SET is_active = 0 WHERE user_id = ?"
        );
        
        if ($stmt === false) {
            error_log("Failed to prepare statement in invalidateUserSessions: " . $this->conn->error);
            return;
        }
        
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stmt->close();
    }
    
    /**
     * Destroy current session
     */
    private function destroySession() {
        $_SESSION = array();
        
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
        
        session_destroy();
    }
    
    /**
     * Get propagation statistics
     */
    public function getPropagationStats() {
        $stats = [];
        
        // Session hijacking incidents
        $result = $this->conn->query(
            "SELECT COUNT(*) as count FROM propagation_incidents 
            WHERE incident_type = 'session_hijacking' AND detected_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );
        $stats['session_hijacking_24h'] = $result->fetch_assoc()['count'];
        
        // Privilege escalation incidents
        $result = $this->conn->query(
            "SELECT COUNT(*) as count FROM propagation_incidents 
            WHERE incident_type = 'privilege_escalation' AND detected_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );
        $stats['privilege_escalation_24h'] = $result->fetch_assoc()['count'];
        
        // Blocked sessions
        $result = $this->conn->query(
            "SELECT COUNT(*) as count FROM blocked_sessions 
            WHERE is_active = 1 AND expires_at > NOW()"
        );
        $stats['blocked_sessions'] = $result->fetch_assoc()['count'];
        
        // Active sessions
        $result = $this->conn->query(
            "SELECT COUNT(*) as count FROM session_tracking 
            WHERE is_active = 1 AND last_activity > DATE_SUB(NOW(), INTERVAL 1 HOUR)"
        );
        $stats['active_sessions'] = $result->fetch_assoc()['count'];
        
        return $stats;
    }
    
    /**
     * Get recent incidents
     */
    public function getRecentIncidents($limit = 10) {
        $stmt = $this->conn->prepare(
            "SELECT * FROM propagation_incidents 
            ORDER BY detected_at DESC LIMIT ?"
        );
        
        if ($stmt === false) {
            error_log("Failed to prepare statement in getRecentIncidents: " . $this->conn->error);
            return [];
        }
        
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $incidents = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        return $incidents;
    }
}
?>
