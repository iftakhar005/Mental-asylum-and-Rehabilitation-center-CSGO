<?php
/**
 * AUTO-SETUP DATA CLASSIFICATIONS
 * Sets up common data classifications for the healthcare system
 */

require_once 'db.php';
require_once 'dlp_system.php';

$dlp = new DataLossPreventionSystem();

echo "<!DOCTYPE html>
<html>
<head>
    <title>Auto-Setup DLP Classifications</title>
    <style>
        body { font-family: Arial; max-width: 1000px; margin: 20px auto; padding: 20px; }
        h2 { color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 10px; }
        .success { background: #d4edda; color: #155724; padding: 10px; margin: 5px 0; border-radius: 5px; }
        .info { background: #d1ecf1; color: #0c5460; padding: 15px; margin: 20px 0; border-radius: 5px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #3498db; color: white; }
        .badge { padding: 3px 10px; border-radius: 12px; font-size: 0.9em; font-weight: bold; }
        .badge-public { background: #d4edda; color: #155724; }
        .badge-internal { background: #fff3cd; color: #856404; }
        .badge-confidential { background: #f8d7da; color: #721c24; }
        .badge-restricted { background: #721c24; color: white; }
    </style>
</head>
<body>
    <h1>🔐 DLP Data Classification Setup</h1>
    <div class='info'>
        <strong>Note:</strong> This will set up common data classifications for your healthcare system.
        Existing classifications will be updated.
    </div>
";

$classifications = [
    // PUBLIC DATA - Anyone can access
    ['rooms', 'room_number', 'public', 'facility_info', 365],
    ['rooms', 'type', 'public', 'facility_info', 365],
    ['rooms', 'capacity', 'public', 'facility_info', 365],
    ['rooms', 'status', 'public', 'facility_info', 365],
    
    // INTERNAL DATA - Authenticated staff only
    ['staff', 'staff_id', 'internal', 'staff_info', 730],
    ['staff', 'full_name', 'internal', 'staff_info', 730],
    ['staff', 'role', 'internal', 'staff_info', 730],
    ['staff', 'department', 'internal', 'staff_info', 730],
    ['appointments', 'appointment_id', 'internal', 'scheduling', 365],
    ['appointments', 'appointment_date', 'internal', 'scheduling', 365],
    ['appointments', 'time_slot', 'internal', 'scheduling', 365],
    ['appointments', 'status', 'internal', 'scheduling', 365],
    ['medicine_stock', 'medicine_name', 'internal', 'inventory', 365],
    ['medicine_stock', 'category', 'internal', 'inventory', 365],
    ['medicine_stock', 'quantity', 'internal', 'inventory', 365],
    
    // CONFIDENTIAL DATA - Supervisor/Admin approval required
    ['staff', 'email', 'confidential', 'contact_info', 730],
    ['staff', 'phone', 'confidential', 'contact_info', 730],
    ['staff', 'salary', 'confidential', 'financial', 2555],
    ['patients', 'full_name', 'confidential', 'patient_info', 2555],
    ['patients', 'email', 'confidential', 'contact_info', 2555],
    ['patients', 'phone', 'confidential', 'contact_info', 2555],
    ['patients', 'address', 'confidential', 'contact_info', 2555],
    ['treatment', 'treatment_plan', 'confidential', 'medical', 2555],
    ['appointments', 'patient_id', 'confidential', 'patient_link', 2555],
    
    // RESTRICTED DATA - Admin only
    ['staff', 'password_hash', 'restricted', 'security', 730],
    ['users', 'password_hash', 'restricted', 'security', 730],
    ['patients', 'diagnosis', 'restricted', 'medical', 2555],
    ['patients', 'medical_history', 'restricted', 'medical', 2555],
    ['treatment', 'medications', 'restricted', 'medical', 2555],
    ['treatment', 'diagnosis', 'restricted', 'medical', 2555],
    ['data_access_logs', '*', 'restricted', 'audit', 1825],
    ['export_approval_requests', '*', 'restricted', 'security', 730],
];

echo "<h2>Setting Up Classifications</h2>";
echo "<table>
    <thead>
        <tr>
            <th>Table</th>
            <th>Column</th>
            <th>Classification</th>
            <th>Category</th>
            <th>Retention</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>";

$success_count = 0;
$failed_count = 0;

foreach ($classifications as $class) {
    list($table, $column, $level, $category, $retention) = $class;
    
    $result = $dlp->classifyData($table, $column, $level, $category, $retention);
    
    $badge_class = "badge-" . $level;
    $status = $result ? "✅ Success" : "❌ Failed";
    
    echo "<tr>
        <td><code>$table</code></td>
        <td><code>$column</code></td>
        <td><span class='badge $badge_class'>" . strtoupper($level) . "</span></td>
        <td>$category</td>
        <td>$retention days</td>
        <td>$status</td>
    </tr>";
    
    if ($result) $success_count++; else $failed_count++;
}

echo "</tbody></table>";

echo "<div class='success'>
    <strong>Setup Complete!</strong><br>
    ✅ Successfully classified: $success_count items<br>
    ❌ Failed: $failed_count items<br><br>
    <a href='check_classification.php'>View All Classifications</a> | 
    <a href='dlp_management.php'>DLP Management</a>
</div>";

echo "</body></html>";
?>
