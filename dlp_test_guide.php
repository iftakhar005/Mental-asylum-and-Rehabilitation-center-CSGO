<?php
require_once 'session_check.php';
check_login(['admin', 'chief-staff']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DLP System Testing Guide</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { text-align: center; margin-bottom: 40px; }
        .header h1 { color: #2c3e50; margin-bottom: 10px; }
        .header p { color: #7f8c8d; font-size: 1.1rem; }
        
        .test-section { margin-bottom: 40px; }
        .test-section h2 { color: #3498db; border-bottom: 3px solid #3498db; padding-bottom: 10px; margin-bottom: 20px; }
        .test-section h3 { color: #2c3e50; margin-top: 25px; margin-bottom: 15px; }
        
        .test-steps { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 15px 0; }
        .test-steps ol { margin: 10px 0; padding-left: 25px; }
        .test-steps li { margin: 8px 0; line-height: 1.6; }
        
        .expected-result { background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 6px; margin: 10px 0; }
        .expected-result::before { content: "✅ Expected Result: "; font-weight: bold; color: #155724; }
        
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 6px; margin: 10px 0; }
        .warning::before { content: "⚠️ Important: "; font-weight: bold; color: #856404; }
        
        .quick-link { display: inline-block; background: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 10px 5px; }
        .quick-link:hover { background: #2980b9; }
        
        .test-data { background: #e3f2fd; border: 1px solid #bbdefb; padding: 15px; border-radius: 6px; margin: 15px 0; }
        .test-data h4 { margin-top: 0; color: #1976d2; }
        
        .nav-back { margin-bottom: 20px; }
        .nav-back a { color: #3498db; text-decoration: none; font-weight: 500; }
        .nav-back a:hover { color: #2980b9; }
    </style>
</head>
<body>
    <div class="container">
        <div class="nav-back">
            <a href="admin_dashboard.php"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        </div>
        
        <div class="header">
            <h1>🛡️ DLP System Testing Guide</h1>
            <p>Complete testing protocol for Data Loss Prevention features</p>
        </div>

        <!-- Quick Access Links -->
        <div style="text-align: center; margin-bottom: 40px;">
            <a href="dlp_management.php" class="quick-link">DLP Management</a>
            <a href="secure_export.php" class="quick-link">Secure Export</a>
            <a href="check_dlp.php" class="quick-link">System Health</a>
            <a href="download_test.php" class="quick-link">Download Testing</a>
        </div>

        <!-- Test Section 1: System Health Check -->
        <div class="test-section">
            <h2>1. System Health & Database Verification</h2>
            
            <h3>🔍 Test 1.1: Database Tables Check</h3>
            <div class="test-steps">
                <ol>
                    <li>Click <a href="check_dlp.php" target="_blank">System Health</a></li>
                    <li>Verify all 6 DLP tables are created and accessible</li>
                    <li>Check that database connection is working</li>
                </ol>
            </div>
            <div class="expected-result">All 6 tables (dlp_config, data_classification, export_approval_requests, download_activity, retention_policies, data_access_audit) should show as "✅ Available"</div>
            
            <h3>📊 Test 1.2: Initial Statistics</h3>
            <div class="test-steps">
                <ol>
                    <li>Go to <a href="dlp_management.php" target="_blank">DLP Management</a></li>
                    <li>Check the statistics cards at the top</li>
                    <li>Note initial counts for comparison later</li>
                </ol>
            </div>
            <div class="expected-result">Statistics should display: Pending Export Requests, Suspicious Activities, Data Classifications, Total Export Requests</div>
        </div>

        <!-- Test Section 2: Data Classification -->
        <div class="test-section">
            <h2>2. Data Classification System</h2>
            
            <h3>🏷️ Test 2.1: Classify Patient Data</h3>
            <div class="test-steps">
                <ol>
                    <li>Go to <a href="dlp_management.php" target="_blank">DLP Management</a> → "Data Classification" tab</li>
                    <li>Add new classification with these test values:</li>
                </ol>
            </div>
            
            <div class="test-data">
                <h4>Test Classification Data:</h4>
                <ul>
                    <li><strong>Table Name:</strong> patients</li>
                    <li><strong>Column Name:</strong> medical_history</li>
                    <li><strong>Classification:</strong> confidential</li>
                    <li><strong>Data Category:</strong> medical_records</li>
                    <li><strong>Retention Days:</strong> 2555 (7 years)</li>
                </ul>
            </div>
            
            <div class="test-steps">
                <ol start="3">
                    <li>Click "Classify Data"</li>
                    <li>Verify the classification appears in the table below</li>
                    <li>Add another classification for "restricted" level data</li>
                </ol>
            </div>
            <div class="expected-result">New classifications should appear in the "Current Data Classifications" table with proper color-coded badges</div>
        </div>

        <!-- Test Section 3: Export Approval Workflow -->
        <div class="test-section">
            <h2>3. Export Approval Workflow</h2>
            
            <h3>📝 Test 3.1: Request Data Export</h3>
            <div class="test-steps">
                <ol>
                    <li>Go to <a href="dlp_management.php" target="_blank">DLP Management</a> → "Export Approval" tab</li>
                    <li>Fill out "Request New Export" form:</li>
                </ol>
            </div>
            
            <div class="test-data">
                <h4>Test Export Request:</h4>
                <ul>
                    <li><strong>Export Type:</strong> patient_report</li>
                    <li><strong>Data Tables:</strong> patients,appointments</li>
                    <li><strong>Data Filters:</strong> {"status": "active", "date_range": "2024-01-01 to 2024-12-31"}</li>
                    <li><strong>Justification:</strong> Monthly compliance report required by regulatory authority</li>
                </ul>
            </div>
            
            <div class="test-steps">
                <ol start="3">
                    <li>Submit the request</li>
                    <li>Note the Request ID generated</li>
                    <li>Check that it appears in "Pending Approval Requests" section</li>
                </ol>
            </div>
            <div class="expected-result">Request should be created with unique ID and appear in pending requests table with "pending" status</div>
            
            <h3>✅ Test 3.2: Approve Export Request</h3>
            <div class="test-steps">
                <ol>
                    <li>In the "Pending Approval Requests" table, find your request</li>
                    <li>Add approval notes: "Approved for compliance reporting purposes"</li>
                    <li>Click "Approve" button</li>
                    <li>Refresh page and verify request is no longer in pending section</li>
                </ol>
            </div>
            <div class="expected-result">Request status should change to "approved" and disappear from pending list</div>
        </div>

        <!-- Test Section 4: Secure Export System -->
        <div class="test-section">
            <h2>4. Secure Export & Watermarking</h2>
            
            <h3>📤 Test 4.1: Generate Secure Export</h3>
            <div class="test-steps">
                <ol>
                    <li>Go to <a href="secure_export.php" target="_blank">Secure Export</a></li>
                    <li>Use the Request ID from your approved export</li>
                    <li>Click "Generate Export"</li>
                    <li>Download the generated file</li>
                </ol>
            </div>
            <div class="expected-result">File should download with watermark containing your username, timestamp, and "CONFIDENTIAL" markings</div>
            
            <h3>🔍 Test 4.2: Verify Watermarking</h3>
            <div class="test-steps">
                <ol>
                    <li>Open the downloaded file</li>
                    <li>Look for watermark text at the top</li>
                    <li>Verify it contains: Username, Download time, Classification level</li>
                </ol>
            </div>
            <div class="expected-result">Watermark should be clearly visible with format: "CONFIDENTIAL - Downloaded by [username] on [timestamp] - Authorized Export"</div>
        </div>

        <!-- Test Section 5: Activity Monitoring -->
        <div class="test-section">
            <h2>5. Activity Monitoring & Audit Trail</h2>
            
            <h3>📊 Test 5.1: Download Activity Tracking</h3>
            <div class="test-steps">
                <ol>
                    <li>Go to <a href="dlp_management.php" target="_blank">DLP Management</a> → "Activity Monitoring" tab</li>
                    <li>Check the "Recent Download Activity" section</li>
                    <li>Verify your export download appears in the log</li>
                </ol>
            </div>
            <div class="expected-result">Your download should appear with timestamp, file type, IP address, and user information</div>
            
            <h3>🔍 Test 5.2: Suspicious Activity Detection</h3>
            <div class="test-steps">
                <ol>
                    <li>Create multiple export requests quickly</li>
                    <li>Download several files in rapid succession</li>
                    <li>Check "Suspicious Activity Alerts" section</li>
                </ol>
            </div>
            <div class="expected-result">System should detect unusual patterns and display alerts for rapid downloads or unusual access patterns</div>
        </div>

        <!-- Test Section 6: Retention Policies -->
        <div class="test-section">
            <h2>6. Retention Policy Management</h2>
            
            <h3>⏰ Test 6.1: Set Retention Policy</h3>
            <div class="test-steps">
                <ol>
                    <li>Go to <a href="dlp_management.php" target="_blank">DLP Management</a> → "Retention Policies" tab</li>
                    <li>Create a test retention policy:</li>
                </ol>
            </div>
            
            <div class="test-data">
                <h4>Test Retention Policy:</h4>
                <ul>
                    <li><strong>Policy Name:</strong> Test_Cleanup_Policy</li>
                    <li><strong>Target Table:</strong> test_data</li>
                    <li><strong>Retention Days:</strong> 1 (for testing)</li>
                    <li><strong>Classification Level:</strong> internal</li>
                </ul>
            </div>
            
            <div class="test-steps">
                <ol start="3">
                    <li>Click "Create Policy"</li>
                    <li>Run retention enforcement to test</li>
                </ol>
            </div>
            <div class="expected-result">Policy should be created and show in active policies list</div>
        </div>

        <!-- Test Section 7: Integration Testing -->
        <div class="test-section">
            <h2>7. Dashboard Integration</h2>
            
            <h3>🎯 Test 7.1: Admin Dashboard Card</h3>
            <div class="test-steps">
                <ol>
                    <li>Go to <a href="admin_dashboard.php" target="_blank">Admin Dashboard</a></li>
                    <li>Find the "DLP Security" card</li>
                    <li>Verify it shows current statistics</li>
                    <li>Click the card to navigate to DLP Management</li>
                </ol>
            </div>
            <div class="expected-result">DLP card should show live statistics and clicking should open DLP Management interface</div>
            
            <h3>📱 Test 7.2: Navigation Menu</h3>
            <div class="test-steps">
                <ol>
                    <li>In Admin Dashboard, expand "Security & Compliance" menu</li>
                    <li>Test all DLP submenu links:
                        <ul>
                            <li>DLP Management</li>
                            <li>Secure Export</li>
                            <li>System Health</li>
                        </ul>
                    </li>
                </ol>
            </div>
            <div class="expected-result">All menu links should work and open correct DLP interfaces</div>
        </div>

        <!-- Final Verification -->
        <div class="test-section">
            <h2>8. Final System Verification</h2>
            
            <div class="warning">
                Complete this checklist to ensure full DLP system functionality:
            </div>
            
            <div class="test-steps">
                <h3>✅ Final Checklist:</h3>
                <ul style="list-style: none; padding-left: 0;">
                    <li>☐ All 6 database tables created and accessible</li>
                    <li>☐ Data classification system working</li>
                    <li>☐ Export approval workflow functional</li>
                    <li>☐ Watermarking applied to exported files</li>
                    <li>☐ Download activity being logged</li>
                    <li>☐ Suspicious activity detection active</li>
                    <li>☐ Retention policies can be created and enforced</li>
                    <li>☐ Admin dashboard integration working</li>
                    <li>☐ All navigation menus functional</li>
                    <li>☐ System health monitoring operational</li>
                </ul>
            </div>
        </div>

        <div style="text-align: center; margin-top: 40px; padding: 20px; background: #d4edda; border-radius: 8px;">
            <h3 style="color: #155724; margin: 0;">🎉 Testing Complete!</h3>
            <p style="color: #155724; margin: 10px 0 0 0;">If all tests pass, your DLP system is fully operational and ready for production use.</p>
        </div>
    </div>
</body>
</html>