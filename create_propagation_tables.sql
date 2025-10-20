-- Propagation Prevention Tables
-- Run this in phpMyAdmin to create all required tables

-- 1. Session Tracking Table
CREATE TABLE IF NOT EXISTS session_tracking (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(255) NOT NULL,
    user_id INT NOT NULL,
    `role` VARCHAR(50) NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Privilege Escalation Tracking Table
CREATE TABLE IF NOT EXISTS privilege_escalation_tracking (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_id VARCHAR(255) NOT NULL,
    attempted_role VARCHAR(50) NOT NULL,
    `current_role` VARCHAR(50) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NOT NULL,
    attempt_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    blocked TINYINT(1) DEFAULT 0,
    propagation_detected TINYINT(1) DEFAULT 0,
    INDEX idx_user_session (user_id, session_id),
    INDEX idx_timestamp (attempt_timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Propagation Incidents Table
CREATE TABLE IF NOT EXISTS propagation_incidents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    incident_type ENUM('session_hijacking', 'privilege_escalation') NOT NULL,
    user_id INT DEFAULT NULL,
    session_id VARCHAR(255) NOT NULL,
    original_fingerprint VARCHAR(255) NOT NULL,
    detected_fingerprint VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NOT NULL,
    additional_data TEXT DEFAULT NULL,
    detected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    blocked TINYINT(1) DEFAULT 1,
    severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'high',
    INDEX idx_type (incident_type),
    INDEX idx_session (session_id),
    INDEX idx_timestamp (detected_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Blocked Sessions Table
CREATE TABLE IF NOT EXISTS blocked_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(255) NOT NULL,
    user_id INT DEFAULT NULL,
    fingerprint VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    block_reason VARCHAR(255) NOT NULL,
    blocked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    INDEX idx_session (session_id),
    INDEX idx_fingerprint (fingerprint),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Verify tables were created
SELECT 'Tables created successfully!' as Status;
SHOW TABLES LIKE '%tracking%';
SHOW TABLES LIKE '%propagation%';
SHOW TABLES LIKE '%blocked%';
