<?php
require_once 'session_check.php';
check_login(['admin', 'chief-staff', 'doctor', 'therapist']);
require_once 'dlp_system.php';

$dlp = new DataLossPreventionSystem();

// Handle file download request
if (isset($_GET['download'])) {
    $file_type = $_GET['download'];
    $classification = $_GET['classification'] ?? 'internal';
    
    // Generate sample file content based on type
    $content = '';
    $filename = '';
    $mime_type = 'text/plain';
    
    switch ($file_type) {
        case 'patient_report':
            $filename = 'patient_report_' . date('Y-m-d') . '.csv';
            $mime_type = 'text/csv';
            $classification = 'confidential';
            $content = "Patient ID,Name,Diagnosis,Treatment Date,Status\n";
            $content .= "P001,John Doe,Anxiety Disorder,2024-10-01,Active\n";
            $content .= "P002,Jane Smith,Depression,2024-10-02,Recovering\n";
            $content .= "P003,Bob Johnson,PTSD,2024-10-03,Treatment\n";
            break;
            
        case 'staff_report':
            $filename = 'staff_performance_' . date('Y-m-d') . '.xlsx';
            $mime_type = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
            $classification = 'internal';
            $content = "Staff ID,Name,Department,Performance Score,Review Date\n";
            $content .= "S001,Dr. Alice Brown,Therapy,95,2024-10-01\n";
            $content .= "S002,Nurse Carol White,General Care,88,2024-10-02\n";
            break;
            
        case 'medical_records':
            $filename = 'medical_records_' . date('Y-m-d') . '.pdf';
            $mime_type = 'application/pdf';
            $classification = 'restricted';
            $content = "CONFIDENTIAL MEDICAL RECORDS\n\n";
            $content .= "Patient: John Doe\n";
            $content .= "Medical History: Chronic anxiety, family history of depression\n";
            $content .= "Current Medications: Sertraline 50mg daily\n";
            $content .= "Treatment Notes: Patient showing improvement with therapy\n";
            $content .= "Next Appointment: 2024-10-15\n";
            break;
            
        case 'public_info':
            $filename = 'facility_info_' . date('Y-m-d') . '.txt';
            $mime_type = 'text/plain';
            $classification = 'public';
            $content = "Mental Health Rehabilitation Center\n\n";
            $content .= "General Information:\n";
            $content .= "- Operating Hours: 8:00 AM - 6:00 PM\n";
            $content .= "- Emergency Contact: 911\n";
            $content .= "- Main Phone: (555) 123-4567\n";
            $content .= "- Address: 123 Healthcare Ave, City, State\n";
            break;
    }
    
    // Log the download activity using DLP system
    $watermarked = in_array($classification, ['confidential', 'restricted']);
    $dlp->logDownloadActivity(
        pathinfo($filename, PATHINFO_EXTENSION), // file_type
        $filename,                               // file_name
        strlen($content),                        // file_size
        $classification,                         // data_classification
        null,                                   // export_request_id
        $watermarked                            // watermarked
    );
    
    // Apply watermark if required for this classification
    if (in_array($classification, ['confidential', 'restricted'])) {
        $watermark = "\n\n=== CONFIDENTIAL ===\n";
        $watermark .= "Downloaded by: " . ($_SESSION['username'] ?? 'Unknown User') . "\n";
        $watermark .= "Download Time: " . date('Y-m-d H:i:s') . "\n";
        $watermark .= "Classification: " . strtoupper($classification) . "\n";
        $watermark .= "Authorized Export - Do Not Redistribute\n";
        $watermark .= "=== END WATERMARK ===\n";
        $content .= $watermark;
    }
    
    // Force download
    header('Content-Type: ' . $mime_type);
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($content));
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
    
    echo $content;
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Download Testing - DLP System</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { text-align: center; margin-bottom: 30px; }
        .header h1 { color: #2c3e50; margin-bottom: 10px; }
        
        .download-section { margin: 30px 0; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
        .download-section h3 { color: #3498db; margin-top: 0; }
        
        .download-btn { display: inline-block; background: #3498db; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin: 10px 5px; font-weight: 500; }
        .download-btn:hover { background: #2980b9; }
        .download-btn.restricted { background: #e74c3c; }
        .download-btn.confidential { background: #f39c12; }
        .download-btn.internal { background: #27ae60; }
        .download-btn.public { background: #95a5a6; }
        
        .classification-info { background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .classification-info h4 { margin-top: 0; color: #2c3e50; }
        
        .nav-back { margin-bottom: 20px; }
        .nav-back a { color: #3498db; text-decoration: none; font-weight: 500; }
        
        .verification-section { background: #e3f2fd; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .verification-section h3 { color: #1976d2; margin-top: 0; }
        
        .step { background: white; padding: 15px; margin: 10px 0; border-left: 4px solid #3498db; }
        .step h4 { margin-top: 0; color: #2c3e50; }
    </style>
</head>
<body>
    <div class="container">
        <div class="nav-back">
            <a href="dlp_management.php">← Back to DLP Management</a> | 
            <a href="admin_dashboard.php">Dashboard</a>
        </div>
        
        <div class="header">
            <h1>📥 File Download Testing</h1>
            <p>Test DLP monitoring by downloading sample files with different security classifications</p>
        </div>
        
        <div class="verification-section">
            <h3>🔍 How to Verify Downloads Are Logged:</h3>
            <div class="step">
                <h4>Method 1: DLP Dashboard</h4>
                <p>1. Go to <a href="dlp_management.php" target="_blank">DLP Management</a></p>
                <p>2. Click "Activity Monitoring" tab</p>
                <p>3. Check "Recent Download Activity" section</p>
            </div>
            <div class="step">
                <h4>Method 2: Database Check</h4>
                <p>Run this SQL query in phpMyAdmin:</p>
                <code style="background: #f4f4f4; padding: 5px;">SELECT * FROM download_activity ORDER BY download_time DESC LIMIT 10;</code>
            </div>
        </div>

        <!-- Public Files -->
        <div class="download-section">
            <h3>🌐 Public Files (No Restrictions)</h3>
            <p>These files can be downloaded by anyone and don't require approval.</p>
            <a href="?download=public_info&classification=public" class="download-btn public">
                📄 Download Facility Information
            </a>
        </div>

        <!-- Internal Files -->
        <div class="download-section">
            <h3>🏢 Internal Files (Staff Only)</h3>
            <p>Internal business documents with basic logging.</p>
            <a href="?download=staff_report&classification=internal" class="download-btn internal">
                📊 Download Staff Performance Report
            </a>
        </div>

        <!-- Confidential Files -->
        <div class="download-section">
            <h3>🔒 Confidential Files (Restricted Access)</h3>
            <p>Sensitive data with watermarking and enhanced monitoring.</p>
            <a href="?download=patient_report&classification=confidential" class="download-btn confidential">
                📋 Download Patient Report (CSV)
            </a>
        </div>

        <!-- Restricted Files -->
        <div class="download-section">
            <h3>🚫 Restricted Files (Highest Security)</h3>
            <p>Most sensitive data requiring approval and full audit trail.</p>
            <a href="?download=medical_records&classification=restricted" class="download-btn restricted">
                🏥 Download Medical Records (PDF)
            </a>
        </div>

        <div class="classification-info">
            <h4>📋 What Happens When You Download:</h4>
            <ul>
                <li><strong>📝 Activity Logged:</strong> User, IP, timestamp, file details recorded</li>
                <li><strong>🏷️ Watermarking:</strong> Confidential/Restricted files get user watermarks</li>
                <li><strong>⚠️ Suspicious Detection:</strong> Rapid downloads trigger security alerts</li>
                <li><strong>📊 Statistics Updated:</strong> DLP dashboard metrics refresh</li>
                <li><strong>🔍 Audit Trail:</strong> Complete forensic record created</li>
            </ul>
        </div>

        <div style="background: #fff3cd; padding: 15px; border-radius: 8px; margin: 20px 0;">
            <h4 style="color: #856404; margin-top: 0;">🧪 Testing Instructions:</h4>
            <ol style="color: #856404;">
                <li><strong>Download multiple files</strong> of different classifications</li>
                <li><strong>Check DLP Management</strong> → Activity Monitoring tab</li>
                <li><strong>Try rapid downloads</strong> to trigger suspicious activity alerts</li>
                <li><strong>Verify watermarks</strong> appear in Confidential/Restricted files</li>
                <li><strong>Check dashboard stats</strong> update in real-time</li>
            </ol>
        </div>

        <div style="text-align: center; margin-top: 30px;">
            <a href="dlp_management.php" class="download-btn">🛡️ View Download Activity</a>
            <a href="dlp_test_guide.php" class="download-btn">📋 Full Testing Guide</a>
        </div>
    </div>
</body>
</html>