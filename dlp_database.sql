-- Data Loss Prevention (DLP) Database Schema
-- Run this to add DLP functionality to your existing database

-- DLP Configuration Table
CREATE TABLE IF NOT EXISTS dlp_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    config_key VARCHAR(100) NOT NULL UNIQUE,
    config_value TEXT NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Data Classification Table
CREATE TABLE IF NOT EXISTS data_classification (
    id INT AUTO_INCREMENT PRIMARY KEY,
    table_name VARCHAR(100) NOT NULL,
    column_name VARCHAR(100) NOT NULL,
    classification_level ENUM('public', 'internal', 'confidential', 'restricted') NOT NULL DEFAULT 'internal',
    data_category VARCHAR(100) NOT NULL,
    retention_days INT DEFAULT 365,
    requires_approval BOOLEAN DEFAULT FALSE,
    watermark_required BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_classification (table_name, column_name)
);

-- Export Approval Requests
CREATE TABLE IF NOT EXISTS export_approval_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id VARCHAR(50) UNIQUE NOT NULL,
    user_id VARCHAR(50) NOT NULL,
    requester_name VARCHAR(200) NOT NULL,
    requester_role VARCHAR(50) NOT NULL,
    export_type VARCHAR(100) NOT NULL,
    data_tables TEXT NOT NULL,
    data_filters JSON,
    justification TEXT NOT NULL,
    classification_level ENUM('public', 'internal', 'confidential', 'restricted') NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'expired') DEFAULT 'pending',
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    approved_by VARCHAR(50),
    approved_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    approval_notes TEXT,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_expires_at (expires_at)
);

-- Download Activity Monitoring
CREATE TABLE IF NOT EXISTS download_activity (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(50) NOT NULL,
    user_name VARCHAR(200) NOT NULL,
    user_role VARCHAR(50) NOT NULL,
    file_type VARCHAR(50) NOT NULL,
    file_name VARCHAR(255),
    file_size INT,
    data_classification ENUM('public', 'internal', 'confidential', 'restricted') NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    download_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    export_request_id VARCHAR(50),
    watermarked BOOLEAN DEFAULT FALSE,
    suspicious_flag BOOLEAN DEFAULT FALSE,
    INDEX idx_user_id (user_id),
    INDEX idx_download_time (download_time),
    INDEX idx_classification (data_classification),
    INDEX idx_suspicious (suspicious_flag)
);

-- Data Retention Policies
CREATE TABLE IF NOT EXISTS retention_policies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    policy_name VARCHAR(100) NOT NULL UNIQUE,
    table_name VARCHAR(100) NOT NULL,
    retention_days INT NOT NULL,
    classification_level ENUM('public', 'internal', 'confidential', 'restricted') NOT NULL,
    auto_delete BOOLEAN DEFAULT FALSE,
    archive_before_delete BOOLEAN DEFAULT TRUE,
    policy_description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_executed TIMESTAMP NULL,
    INDEX idx_table_name (table_name),
    INDEX idx_classification (classification_level)
);

-- Data Access Audit Trail
CREATE TABLE IF NOT EXISTS data_access_audit (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(50) NOT NULL,
    user_name VARCHAR(200) NOT NULL,
    user_role VARCHAR(50) NOT NULL,
    action_type ENUM('view', 'export', 'modify', 'delete', 'bulk_export') NOT NULL,
    table_name VARCHAR(100) NOT NULL,
    record_id VARCHAR(50),
    data_classification ENUM('public', 'internal', 'confidential', 'restricted') NOT NULL,
    access_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    details JSON,
    risk_score INT DEFAULT 0,
    INDEX idx_user_id (user_id),
    INDEX idx_access_time (access_time),
    INDEX idx_action_type (action_type),
    INDEX idx_table_name (table_name),
    INDEX idx_risk_score (risk_score)
);

-- Insert default DLP configurations
INSERT INTO dlp_config (config_key, config_value, description) VALUES
('max_bulk_export_records', '1000', 'Maximum records allowed in bulk export without approval'),
('approval_expiry_hours', '72', 'Hours before export approval expires'),
('download_monitoring_enabled', '1', 'Enable download pattern monitoring'),
('watermark_enabled', '1', 'Enable watermarking for exported files'),
('suspicious_download_threshold', '10', 'Number of downloads per hour that triggers suspicious flag'),
('retention_check_frequency', '24', 'Hours between retention policy checks'),
('high_risk_ip_monitoring', '1', 'Enable monitoring for high-risk IP addresses'),
('data_classification_required', '1', 'Require data classification for all sensitive tables');

-- Insert default data classifications
INSERT INTO data_classification (table_name, column_name, classification_level, data_category, retention_days, requires_approval, watermark_required) VALUES
('staff', 'password_hash', 'restricted', 'authentication', 2555, TRUE, TRUE),
('staff', 'temp_password', 'restricted', 'authentication', 7, TRUE, TRUE),
('staff', 'phone', 'confidential', 'personal_info', 2555, TRUE, TRUE),
('staff', 'email', 'confidential', 'personal_info', 2555, TRUE, TRUE),
('staff', 'address', 'confidential', 'personal_info', 2555, TRUE, TRUE),
('staff', 'dob', 'confidential', 'personal_info', 2555, TRUE, TRUE),
('users', 'password_hash', 'restricted', 'authentication', 2555, TRUE, TRUE),
('users', 'email', 'confidential', 'personal_info', 2555, TRUE, TRUE),
('users', 'contact_number', 'confidential', 'personal_info', 2555, TRUE, TRUE);

-- Insert default retention policies
INSERT INTO retention_policies (policy_name, table_name, retention_days, classification_level, auto_delete, archive_before_delete, policy_description) VALUES
('Staff Authentication Data', 'staff', 2555, 'restricted', FALSE, TRUE, 'Long-term retention for staff authentication data'),
('User Authentication Data', 'users', 2555, 'restricted', FALSE, TRUE, 'Long-term retention for user authentication data'),
('Download Activity Logs', 'download_activity', 365, 'internal', TRUE, TRUE, 'Retain download logs for one year'),
('Access Audit Logs', 'data_access_audit', 2555, 'confidential', FALSE, TRUE, 'Long-term retention for audit compliance'),
('Export Approval Requests', 'export_approval_requests', 1095, 'confidential', FALSE, TRUE, 'Retain export approvals for 3 years');