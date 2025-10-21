<?php
/**
 * Data Loss Prevention (DLP) System
 * Comprehensive security system for preventing unauthorized data access and export
 */

require_once 'db.php';

class DataLossPreventionSystem {
    private $conn;
    private $current_user_id;
    private $current_user_role;
    private $current_user_name;
    private $current_ip;
    
    public function __construct($user_id = null, $user_role = null, $user_name = null) {
        global $conn;
        $this->conn = $conn;
        $this->current_user_id = $user_id ?? $_SESSION['user_id'] ?? 'anonymous';
        $this->current_user_role = $user_role ?? $_SESSION['role'] ?? 'guest';
        $this->current_user_name = $user_name ?? $_SESSION['username'] ?? 'Anonymous User';
        $this->current_ip = $this->getRealIpAddress();
    }
    
    // 1. DATA CLASSIFICATION SYSTEM
    public function classifyData($table_name, $column_name, $classification_level, $data_category, $retention_days = 365) {
        $stmt = $this->conn->prepare("
            INSERT INTO data_classification 
            (table_name, column_name, classification_level, data_category, retention_days, requires_approval, watermark_required) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            classification_level = VALUES(classification_level),
            data_category = VALUES(data_category),
            retention_days = VALUES(retention_days),
            requires_approval = VALUES(requires_approval),
            watermark_required = VALUES(watermark_required)
        ");
        
        $requires_approval = in_array($classification_level, ['confidential', 'restricted']);
        $watermark_required = in_array($classification_level, ['confidential', 'restricted']);
        
        $stmt->bind_param("ssssiii", $table_name, $column_name, $classification_level, $data_category, $retention_days, $requires_approval, $watermark_required);
        return $stmt->execute();
    }
    
    public function getDataClassification($table_name, $column_name = null) {
        if ($column_name) {
            $stmt = $this->conn->prepare("SELECT * FROM data_classification WHERE table_name = ? AND column_name = ?");
            $stmt->bind_param("ss", $table_name, $column_name);
        } else {
            $stmt = $this->conn->prepare("SELECT * FROM data_classification WHERE table_name = ?");
            $stmt->bind_param("s", $table_name);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        return $column_name ? $result->fetch_assoc() : $result->fetch_all(MYSQLI_ASSOC);
    }
    
    public function getHighestClassificationForTable($table_name) {
        $stmt = $this->conn->prepare("
            SELECT classification_level FROM data_classification 
            WHERE table_name = ?
            ORDER BY 
                CASE classification_level 
                    WHEN 'restricted' THEN 4 
                    WHEN 'confidential' THEN 3 
                    WHEN 'internal' THEN 2 
                    WHEN 'public' THEN 1 
                END DESC 
            LIMIT 1
        ");
        $stmt->bind_param("s", $table_name);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row ? $row['classification_level'] : 'internal';
    }
    
    // 2. BULK EXPORT APPROVAL SYSTEM
    public function requestBulkExportApproval($export_type, $data_tables, $data_filters, $justification) {
        // Validate user has permission to request exports
        $allowed_roles = ['admin', 'chief-staff', 'doctor', 'therapist', 'nurse', 'receptionist', 'staff'];
        if (!in_array($this->current_user_role, $allowed_roles)) {
            return ['success' => false, 'error' => 'Insufficient permissions to request data exports'];
        }
        
        // Validate justification is provided for sensitive data
        if (empty(trim($justification))) {
            return ['success' => false, 'error' => 'Justification is required for bulk export requests'];
        }
        
        // Validate data tables array
        if (empty($data_tables) || !is_array($data_tables)) {
            return ['success' => false, 'error' => 'At least one data table must be specified'];
        }
        
        // Generate unique request ID
        $request_id = 'EXP-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 8));
        
        // Determine classification level based on tables being exported
        $classification_level = 'public';
        foreach ($data_tables as $table) {
            $table_classification = $this->getHighestClassificationForTable($table);
            if ($this->getClassificationPriority($table_classification) > $this->getClassificationPriority($classification_level)) {
                $classification_level = $table_classification;
            }
        }
        
        // AUTO-APPROVE based on classification level and user role
        $auto_approve = false;
        $status = 'pending';
        $approved_by = null;
        $approved_at = null;
        
        // PUBLIC data - auto-approve for everyone
        if ($classification_level === 'public') {
            $auto_approve = true;
            $status = 'approved';
            $approved_by = 'SYSTEM_AUTO';
            $approved_at = date('Y-m-d H:i:s');
        }
        // INTERNAL data - auto-approve for authenticated staff
        elseif ($classification_level === 'internal' && in_array($this->current_user_role, $allowed_roles)) {
            $auto_approve = true;
            $status = 'approved';
            $approved_by = 'SYSTEM_AUTO';
            $approved_at = date('Y-m-d H:i:s');
        }
        // CONFIDENTIAL data - auto-approve for chief-staff and admin
        elseif ($classification_level === 'confidential' && in_array($this->current_user_role, ['admin', 'chief-staff'])) {
            $auto_approve = true;
            $status = 'approved';
            $approved_by = $this->current_user_id;
            $approved_at = date('Y-m-d H:i:s');
        }
        // RESTRICTED data - auto-approve for admin only
        elseif ($classification_level === 'restricted' && $this->current_user_role === 'admin') {
            $auto_approve = true;
            $status = 'approved';
            $approved_by = $this->current_user_id;
            $approved_at = date('Y-m-d H:i:s');
        }
        
        // Calculate expiry time
        $expiry_hours = $this->getConfig('approval_expiry_hours', 72);
        $expires_at = date('Y-m-d H:i:s', strtotime("+{$expiry_hours} hours"));
        
        $stmt = $this->conn->prepare("
            INSERT INTO export_approval_requests 
            (request_id, user_id, requester_name, requester_role, export_type, data_tables, data_filters, justification, classification_level, status, approved_by, approved_at, expires_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $data_tables_json = json_encode($data_tables);
        $data_filters_json = json_encode($data_filters);
        
        $stmt->bind_param("sssssssssssss", 
            $request_id, $this->current_user_id, $this->current_user_name, $this->current_user_role, 
            $export_type, $data_tables_json, $data_filters_json, $justification, $classification_level, 
            $status, $approved_by, $approved_at, $expires_at
        );
        
        if ($stmt->execute()) {
            $this->logDataAccess('bulk_export', 'export_approval_requests', $request_id, $classification_level, [
                'action' => $auto_approve ? 'request_auto_approved' : 'request_submitted',
                'export_type' => $export_type,
                'tables' => $data_tables,
                'auto_approved' => $auto_approve
            ]);
            
            return [
                'success' => true,
                'request_id' => $request_id,
                'expires_at' => $expires_at,
                'classification_level' => $classification_level,
                'auto_approved' => $auto_approve,
                'status' => $status,
                'message' => $auto_approve ? 
                    'Export approved automatically. You can download immediately!' : 
                    'Export request submitted for approval. You will be notified once approved.'
            ];
        }
        
        return ['success' => false, 'error' => 'Failed to submit approval request'];
    }
    
    public function approveExportRequest($request_id, $approval_notes = '') {
        // Only admin and chief-staff can approve
        if (!in_array($this->current_user_role, ['admin', 'chief-staff'])) {
            return ['success' => false, 'error' => 'Insufficient permissions to approve requests'];
        }
        
        $stmt = $this->conn->prepare("
            UPDATE export_approval_requests 
            SET status = 'approved', approved_by = ?, approved_at = NOW(), approval_notes = ?
            WHERE request_id = ? AND status = 'pending' AND (expires_at IS NULL OR expires_at > NOW())
        ");
        
        $stmt->bind_param("sss", $this->current_user_id, $approval_notes, $request_id);
        
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $this->logDataAccess('export', 'export_approval_requests', $request_id, 'confidential', [
                'action' => 'request_approved',
                'approved_by' => $this->current_user_id
            ]);
            
            return ['success' => true, 'message' => 'Export request approved successfully'];
        }
        
        return ['success' => false, 'error' => 'Request not found, expired, or already processed'];
    }
    
    public function checkExportApproval($request_id) {
        $stmt = $this->conn->prepare("
            SELECT * FROM export_approval_requests 
            WHERE request_id = ? AND status = 'approved' AND (expires_at IS NULL OR expires_at > NOW())
        ");
        $stmt->bind_param("s", $request_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
    
    public function getUserExportRequests($status = null) {
        $sql = "SELECT * FROM export_approval_requests WHERE user_id = ?";
        $params = [$this->current_user_id];
        $types = "s";
        
        if ($status) {
            $sql .= " AND status = ?";
            $params[] = $status;
            $types .= "s";
        }
        
        $sql .= " ORDER BY requested_at DESC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    public function getAllExportRequests($status = null) {
        // Only admin and chief-staff can view all requests
        if (!in_array($this->current_user_role, ['admin', 'chief-staff'])) {
            return ['success' => false, 'error' => 'Insufficient permissions to view all requests'];
        }
        
        $sql = "SELECT * FROM export_approval_requests";
        $params = [];
        $types = "";
        
        if ($status) {
            $sql .= " WHERE status = ?";
            $params[] = $status;
            $types = "s";
        }
        
        $sql .= " ORDER BY requested_at DESC";
        
        $stmt = $this->conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        return ['success' => true, 'requests' => $result->fetch_all(MYSQLI_ASSOC)];
    }
    
    public function rejectExportRequest($request_id, $rejection_notes = '') {
        // Only admin and chief-staff can reject
        if (!in_array($this->current_user_role, ['admin', 'chief-staff'])) {
            return ['success' => false, 'error' => 'Insufficient permissions to reject requests'];
        }
        
        $stmt = $this->conn->prepare("
            UPDATE export_approval_requests 
            SET status = 'rejected', approved_by = ?, approved_at = NOW(), approval_notes = ?
            WHERE request_id = ? AND status = 'pending'
        ");
        
        $stmt->bind_param("sss", $this->current_user_id, $rejection_notes, $request_id);
        
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $this->logDataAccess('export', 'export_approval_requests', $request_id, 'confidential', [
                'action' => 'request_rejected',
                'rejected_by' => $this->current_user_id,
                'reason' => $rejection_notes
            ]);
            
            return ['success' => true, 'message' => 'Export request rejected successfully'];
        }
        
        return ['success' => false, 'error' => 'Request not found or already processed'];
    }
    
    // 3. DOWNLOAD PATTERN MONITORING
    public function logDownloadActivity($file_type, $file_name, $file_size, $data_classification, $export_request_id = null, $watermarked = false) {
        $stmt = $this->conn->prepare("
            INSERT INTO download_activity 
            (user_id, user_name, user_role, file_type, file_name, file_size, data_classification, ip_address, user_agent, export_request_id, watermarked) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        $stmt->bind_param("sssssissssi", 
            $this->current_user_id, $this->current_user_name, $this->current_user_role, 
            $file_type, $file_name, $file_size, $data_classification, 
            $this->current_ip, $user_agent, $export_request_id, $watermarked
        );
        
        if ($stmt->execute()) {
            // Check for suspicious activity
            $this->checkSuspiciousActivity();
            return true;
        }
        
        return false;
    }
    
    private function checkSuspiciousActivity() {
        $threshold = $this->getConfig('suspicious_download_threshold', 10);
        
        // Check downloads in last hour
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as download_count 
            FROM download_activity 
            WHERE user_id = ? AND download_time > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $stmt->bind_param("s", $this->current_user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        if ($row['download_count'] >= $threshold) {
            // Flag as suspicious
            $this->conn->prepare("
                UPDATE download_activity 
                SET suspicious_flag = 1 
                WHERE user_id = ? AND download_time > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ")->execute([$this->current_user_id]);
            
            // Log high-risk activity
            $this->logDataAccess('view', 'download_activity', null, 'restricted', [
                'action' => 'suspicious_download_pattern',
                'downloads_last_hour' => $row['download_count'],
                'threshold' => $threshold
            ], 9); // High risk score
        }
    }
    
    // 4. DATA WATERMARKING
    public function addWatermarkToText($content, $watermark_info = null) {
        if (!$watermark_info) {
            $watermark_info = [
                'user' => $this->current_user_name,
                'time' => date('Y-m-d H:i:s'),
                'id' => $this->current_user_id
            ];
        }
        
        $watermark = "\n\n--- CONFIDENTIAL ---\n";
        $watermark .= "Downloaded by: {$watermark_info['user']} ({$watermark_info['id']})\n";
        $watermark .= "Download Time: {$watermark_info['time']}\n";
        $watermark .= "IP Address: {$this->current_ip}\n";
        $watermark .= "--- UNAUTHORIZED DISTRIBUTION PROHIBITED ---";
        
        return $content . $watermark;
    }
    
    public function addWatermarkToCSV($csv_content, $watermark_info = null) {
        if (!$watermark_info) {
            $watermark_info = [
                'user' => $this->current_user_name,
                'time' => date('Y-m-d H:i:s'),
                'id' => $this->current_user_id
            ];
        }
        
        $watermark_row = "\n\"--- CONFIDENTIAL DATA ---\",\"\",\"\",\"\"\n";
        $watermark_row .= "\"Downloaded by: {$watermark_info['user']}\",\"ID: {$watermark_info['id']}\",\"Time: {$watermark_info['time']}\",\"IP: {$this->current_ip}\"\n";
        $watermark_row .= "\"--- UNAUTHORIZED DISTRIBUTION PROHIBITED ---\",\"\",\"\",\"\"";
        
        return $csv_content . $watermark_row;
    }
    
    // 5. RETENTION POLICY ENFORCEMENT
    public function enforceRetentionPolicies() {
        $stmt = $this->conn->prepare("SELECT * FROM retention_policies WHERE auto_delete = 1");
        $stmt->execute();
        $policies = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        $results = [];
        
        foreach ($policies as $policy) {
            $result = $this->applyRetentionPolicy($policy);
            $results[] = [
                'policy' => $policy['policy_name'],
                'table' => $policy['table_name'],
                'result' => $result
            ];
            
            // Update last executed time
            $this->conn->prepare("UPDATE retention_policies SET last_executed = NOW() WHERE id = ?")
                       ->execute([$policy['id']]);
        }
        
        return $results;
    }
    
    private function applyRetentionPolicy($policy) {
        $table_name = $policy['table_name'];
        $retention_days = $policy['retention_days'];
        $archive_first = $policy['archive_before_delete'];
        
    
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$retention_days} days"));
        

        $date_column = $this->getDateColumnForTable($table_name);
        if (!$date_column) {
            return ['success' => false, 'error' => 'No date column found for retention policy'];
        }
        
        $stmt = $this->conn->prepare("SELECT COUNT(*) as count FROM {$table_name} WHERE {$date_column} < ?");
        $stmt->bind_param("s", $cutoff_date);
        $stmt->execute();
        $count_result = $stmt->get_result()->fetch_assoc();
        $records_to_delete = $count_result['count'];
        
        if ($records_to_delete > 0) {
            if ($archive_first) {
       
                $this->archiveOldRecords($table_name, $date_column, $cutoff_date);
            }
            
            // Delete old records
            $delete_stmt = $this->conn->prepare("DELETE FROM {$table_name} WHERE {$date_column} < ?");
            $delete_stmt->bind_param("s", $cutoff_date);
            
            if ($delete_stmt->execute()) {
                $this->logDataAccess('delete', $table_name, null, 'internal', [
                    'action' => 'retention_policy_cleanup',
                    'records_deleted' => $records_to_delete,
                    'policy' => $policy['policy_name']
                ]);
                
                return ['success' => true, 'records_deleted' => $records_to_delete];
            }
        }
        
        return ['success' => true, 'records_deleted' => 0];
    }
    
    // 6. AUDIT AND COMPLIANCE
    public function logDataAccess($action_type, $table_name, $record_id = null, $data_classification = 'internal', $details = [], $risk_score = 0) {
        $stmt = $this->conn->prepare("
            INSERT INTO data_access_audit 
            (user_id, user_name, user_role, action_type, table_name, record_id, data_classification, ip_address, user_agent, details, risk_score) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $details_json = json_encode($details);
        
        $stmt->bind_param("ssssssssssi", 
            $this->current_user_id, $this->current_user_name, $this->current_user_role, 
            $action_type, $table_name, $record_id, $data_classification, 
            $this->current_ip, $user_agent, $details_json, $risk_score
        );
        
        return $stmt->execute();
    }
    
    // UTILITY METHODS
    private function getConfig($key, $default = null) {
        $stmt = $this->conn->prepare("SELECT config_value FROM dlp_config WHERE config_key = ?");
        $stmt->bind_param("s", $key);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row ? $row['config_value'] : $default;
    }
    
    private function getClassificationPriority($classification) {
        $priorities = ['public' => 1, 'internal' => 2, 'confidential' => 3, 'restricted' => 4];
        return $priorities[$classification] ?? 2;
    }
    
    private function getRealIpAddress() {
        $ip_keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);
                // Convert IPv6 localhost to readable format
                if ($ip === '::1') {
                    return '127.0.0.1 (localhost)';
                }
                return $ip;
            }
        }
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if ($ip === '::1') {
            return '127.0.0.1 (localhost)';
        }
        return $ip;
    }
    
    private function getDateColumnForTable($table_name) {
        // Map tables to their date columns for retention policies
        $date_columns = [
            'download_activity' => 'download_time',
            'data_access_audit' => 'access_time',
            'export_approval_requests' => 'requested_at',
            'staff' => 'created_at',
            'users' => 'created_at'
        ];
        
        return $date_columns[$table_name] ?? 'created_at';
    }
    
    private function archiveOldRecords($table_name, $date_column, $cutoff_date) {
        // Simple archiving - export to JSON file
        $stmt = $this->conn->prepare("SELECT * FROM {$table_name} WHERE {$date_column} < ?");
        $stmt->bind_param("s", $cutoff_date);
        $stmt->execute();
        $records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        if (!empty($records)) {
            $archive_file = "archives/{$table_name}_archive_" . date('Y_m_d_H_i_s') . ".json";
            if (!is_dir('archives')) {
                mkdir('archives', 0755, true);
            }
            file_put_contents($archive_file, json_encode($records, JSON_PRETTY_PRINT));
        }
    }
    
    // PUBLIC API METHODS
    public function canUserExportData($table_name, $record_count = 1) {
        $classification = $this->getHighestClassificationForTable($table_name);
        $max_records = $this->getConfig('max_bulk_export_records', 1000);
        
        // Role-specific export permissions
        $role_permissions = $this->getRoleExportPermissions();
        
        // Check if user role can access this table
        if (!$this->canRoleAccessTable($this->current_user_role, $table_name)) {
            return [
                'requires_approval' => false, 
                'classification' => $classification,
                'allowed' => false,
                'error' => 'Your role does not have permission to export data from this table'
            ];
        }
        
        // Check if approval is required based on classification, count, or role
        $requires_approval = false;
        $reasons = [];
        
        if ($record_count > $max_records) {
            $requires_approval = true;
            $reasons[] = "Record count exceeds limit ({$record_count} > {$max_records})";
        }
        
        if (in_array($classification, ['confidential', 'restricted'])) {
            $requires_approval = true;
            $reasons[] = "Data classification level: {$classification}";
        }
        
        // Some roles always require approval for bulk exports
        if (in_array($this->current_user_role, ['nurse', 'receptionist']) && $record_count > 50) {
            $requires_approval = true;
            $reasons[] = "Role-based restriction for bulk exports";
        }
        
        return [
            'requires_approval' => $requires_approval, 
            'classification' => $classification,
            'allowed' => true,
            'reasons' => $reasons,
            'max_without_approval' => $this->getMaxRecordsWithoutApproval($this->current_user_role)
        ];
    }
    
    private function getRoleExportPermissions() {
        return [
            'admin' => ['all_tables'],
            'chief-staff' => ['all_tables'],
            'doctor' => ['patients', 'appointments', 'treatments', 'medical_records'],
            'therapist' => ['patients', 'appointments', 'treatments', 'therapy_sessions'],
            'nurse' => ['patients', 'appointments', 'medical_records', 'medications'],
            'receptionist' => ['patients', 'appointments', 'staff'],
            'staff' => ['patients', 'appointments']
        ];
    }
    
    private function canRoleAccessTable($role, $table_name) {
        $permissions = $this->getRoleExportPermissions();
        
        if (!isset($permissions[$role])) {
            return false; // Unknown role
        }
        
        $allowed_tables = $permissions[$role];
        
        // Admin and chief-staff have access to all tables
        if (in_array('all_tables', $allowed_tables)) {
            return true;
        }
        
        // Check if specific table is allowed
        return in_array($table_name, $allowed_tables);
    }
    
    private function getMaxRecordsWithoutApproval($role) {
        $limits = [
            'admin' => 10000,
            'chief-staff' => 5000,
            'doctor' => 1000,
            'therapist' => 1000,
            'nurse' => 50,
            'receptionist' => 50,
            'staff' => 25
        ];
        
        return $limits[$role] ?? 10;
    }
    
    public function getDLPStats() {
        $stats = [];
        
        // Export requests
        $stmt = $this->conn->prepare("SELECT status, COUNT(*) as count FROM export_approval_requests GROUP BY status");
        $stmt->execute();
        $export_stats = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stats['export_requests'] = $export_stats;
        
        // Download activity
        $stmt = $this->conn->prepare("SELECT DATE(download_time) as date, COUNT(*) as downloads FROM download_activity WHERE download_time >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY DATE(download_time) ORDER BY date");
        $stmt->execute();
        $download_stats = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stats['daily_downloads'] = $download_stats;
        
        // Suspicious activity
        $stmt = $this->conn->prepare("SELECT COUNT(*) as count FROM download_activity WHERE suspicious_flag = 1 AND download_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $stmt->execute();
        $suspicious_stats = $stmt->get_result()->fetch_assoc();
        $stats['suspicious_activity'] = $suspicious_stats['count'];
        
        return $stats;
    }
    
    public function getUserNotifications($user_id = null) {
        $user_id = $user_id ?? $this->current_user_id;
        
        // Check for newly approved/rejected requests
        $stmt = $this->conn->prepare("
            SELECT request_id, status, export_type, approved_at, approval_notes
            FROM export_approval_requests 
            WHERE user_id = ? AND status IN ('approved', 'rejected') 
            AND approved_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY approved_at DESC
        ");
        $stmt->bind_param("s", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $notifications = [];
        while ($row = $result->fetch_assoc()) {+-
            $notifications[] = [
                'type' => 'export_request_' . $row['status'],
                'title' => 'Export Request ' . ucfirst($row['status']),
                'message' => "Your {$row['export_type']} request ({$row['request_id']}) has been {$row['status']}",
                'time' => $row['approved_at'],
                'request_id' => $row['request_id'],
                'notes' => $row['approval_notes']
            ];
        }
        
        return $notifications;
    }
    
    public function getUnreadNotificationCount($user_id = null) {
        $notifications = $this->getUserNotifications($user_id);
        return count($notifications);
    }
}
?>