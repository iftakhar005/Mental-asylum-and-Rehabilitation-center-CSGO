<?php
/**
 * Automated Data Retention Policy Enforcement
 * This script should be run via cron job to automatically enforce retention policies
 * 
 * Recommended cron schedule: Run daily at 2 AM
 * 0 2 * * * /usr/bin/php /path/to/retention_enforcer.php
 */

// Set script execution time limit
set_time_limit(3600); // 1 hour

// Include required files
require_once 'db.php';
require_once 'dlp_system.php';

// Create log file for retention activities
$log_file = 'logs/retention_enforcement_' . date('Y_m_d') . '.log';
if (!is_dir('logs')) {
    mkdir('logs', 0755, true);
}

function writeLog($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[{$timestamp}] {$message}\n", FILE_APPEND | LOCK_EX);
}

function sendAdminNotification($subject, $message) {
    // Get admin emails from database
    global $conn;
    $stmt = $conn->prepare("SELECT email FROM users WHERE role = 'admin' AND status = 'active'");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $admin_emails = [];
    while ($row = $result->fetch_assoc()) {
        $admin_emails[] = $row['email'];
    }
    
    if (!empty($admin_emails)) {
        $headers = "From: DLP System <noreply@mentalhealthsystem.com>\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        
        $html_message = "
        <html>
        <body>
            <h2>{$subject}</h2>
            <p>{$message}</p>
            <p><small>This is an automated message from the Data Loss Prevention System.</small></p>
        </body>
        </html>
        ";
        
        foreach ($admin_emails as $email) {
            mail($email, $subject, $html_message, $headers);
        }
    }
}

try {
    writeLog("Starting retention policy enforcement");
    
    // Initialize DLP system with system user
    $dlp = new DataLossPreventionSystem('SYSTEM', 'system', 'Automated Retention System');
    
    // Get retention configuration
    $check_frequency = $dlp->getConfig('retention_check_frequency', 24);
    
    writeLog("Checking if retention enforcement is due (frequency: {$check_frequency} hours)");
    
    // Check if we should run retention policies
    $stmt = $conn->prepare("
        SELECT MAX(last_executed) as last_run 
        FROM retention_policies 
        WHERE last_executed IS NOT NULL
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    $last_run = $row['last_run'];
    $should_run = false;
    
    if ($last_run === null) {
        writeLog("No previous retention run found. Running for the first time.");
        $should_run = true;
    } else {
        $hours_since_last_run = (time() - strtotime($last_run)) / 3600;
        writeLog("Hours since last run: " . round($hours_since_last_run, 2));
        
        if ($hours_since_last_run >= $check_frequency) {
            $should_run = true;
        }
    }
    
    if (!$should_run) {
        writeLog("Retention policies not due for execution. Skipping.");
        exit(0);
    }
    
    writeLog("Executing retention policies");
    
    // Execute retention policies
    $results = $dlp->enforceRetentionPolicies();
    
    $total_records_processed = 0;
    $policies_executed = count($results);
    $successful_policies = 0;
    
    foreach ($results as $result) {
        if (isset($result['result']['success']) && $result['result']['success']) {
            $successful_policies++;
            $records_deleted = $result['result']['records_deleted'] ?? 0;
            $total_records_processed += $records_deleted;
            
            writeLog("Policy '{$result['policy']}' on table '{$result['table']}': {$records_deleted} records processed");
        } else {
            $error = $result['result']['error'] ?? 'Unknown error';
            writeLog("Policy '{$result['policy']}' failed: {$error}");
        }
    }
    
    // Archive old audit logs (older than 7 years)
    writeLog("Archiving old audit logs");
    $archive_results = archiveOldAuditLogs();
    writeLog("Audit log archiving: {$archive_results['archived']} records archived, {$archive_results['deleted']} records deleted");
    
    // Clean up old export approval requests
    writeLog("Cleaning up expired export approval requests");
    $cleanup_results = cleanupExpiredRequests();
    writeLog("Expired requests cleanup: {$cleanup_results} requests cleaned up");
    
    // Clean up old download activity logs (keep only last 2 years)
    writeLog("Cleaning up old download activity logs");
    $download_cleanup = cleanupOldDownloadLogs();
    writeLog("Download logs cleanup: {$download_cleanup} records cleaned up");
    
    // Generate summary report
    $summary = "
    Retention Policy Enforcement Summary:
    - Policies executed: {$policies_executed}
    - Successful policies: {$successful_policies}
    - Total records processed: {$total_records_processed}
    - Audit logs archived: {$archive_results['archived']}
    - Expired requests cleaned: {$cleanup_results}
    - Download logs cleaned: {$download_cleanup}
    ";
    
    writeLog($summary);
    
    // Send notification to admins if significant activity occurred
    if ($total_records_processed > 0 || $archive_results['archived'] > 0) {
        $notification_subject = "DLP Retention Policy Execution Report";
        $notification_message = str_replace("\n", "<br>", $summary);
        sendAdminNotification($notification_subject, $notification_message);
        writeLog("Admin notification sent");
    }
    
    writeLog("Retention policy enforcement completed successfully");
    
} catch (Exception $e) {
    $error_message = "Retention policy enforcement failed: " . $e->getMessage();
    writeLog($error_message);
    
    // Send error notification to admins
    sendAdminNotification("DLP Retention Policy Error", $error_message);
    
    exit(1);
}

function archiveOldAuditLogs() {
    global $conn;
    
    // Archive audit logs older than 7 years
    $cutoff_date = date('Y-m-d H:i:s', strtotime('-7 years'));
    
    // First, get the records to archive
    $stmt = $conn->prepare("SELECT * FROM data_access_audit WHERE access_time < ?");
    $stmt->bind_param("s", $cutoff_date);
    $stmt->execute();
    $records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    $archived_count = count($records);
    
    if ($archived_count > 0) {
        // Create archive file
        $archive_file = "archives/audit_logs_archive_" . date('Y_m_d_H_i_s') . ".json";
        if (!is_dir('archives')) {
            mkdir('archives', 0755, true);
        }
        
        $archive_data = [
            'archive_date' => date('Y-m-d H:i:s'),
            'cutoff_date' => $cutoff_date,
            'record_count' => $archived_count,
            'records' => $records
        ];
        
        file_put_contents($archive_file, json_encode($archive_data, JSON_PRETTY_PRINT));
        
        // Delete the archived records
        $delete_stmt = $conn->prepare("DELETE FROM data_access_audit WHERE access_time < ?");
        $delete_stmt->bind_param("s", $cutoff_date);
        $delete_stmt->execute();
        $deleted_count = $delete_stmt->affected_rows;
        
        return ['archived' => $archived_count, 'deleted' => $deleted_count];
    }
    
    return ['archived' => 0, 'deleted' => 0];
}

function cleanupExpiredRequests() {
    global $conn;
    
    // Update expired requests to 'expired' status
    $stmt = $conn->prepare("
        UPDATE export_approval_requests 
        SET status = 'expired' 
        WHERE status = 'pending' AND expires_at < NOW()
    ");
    $stmt->execute();
    
    return $stmt->affected_rows;
}

function cleanupOldDownloadLogs() {
    global $conn;
    
    // Keep only last 2 years of download activity
    $cutoff_date = date('Y-m-d H:i:s', strtotime('-2 years'));
    
    $stmt = $conn->prepare("DELETE FROM download_activity WHERE download_time < ?");
    $stmt->bind_param("s", $cutoff_date);
    $stmt->execute();
    
    return $stmt->affected_rows;
}

// Function to get DLP configuration
function getDLPConfig($key, $default = null) {
    global $conn;
    $stmt = $conn->prepare("SELECT config_value FROM dlp_config WHERE config_key = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row ? $row['config_value'] : $default;
}

?>