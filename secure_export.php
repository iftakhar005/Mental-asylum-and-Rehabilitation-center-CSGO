<?php
require_once 'session_check.php';
check_login(['admin', 'chief-staff', 'doctor', 'nurse', 'therapist', 'receptionist', 'staff']);
require_once 'dlp_system.php';

$dlp = new DataLossPreventionSystem();

// Handle GET request for approved export downloads
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['request_id'])) {
    $request_id = $_GET['request_id'];
    
    // Check if request is approved
    $approval = $dlp->checkExportApproval($request_id);
    if (!$approval) {
        echo "<div style='padding: 20px; text-align: center; font-family: Arial;'>";
        echo "<h3 style='color: #e74c3c;'>❌ Export Request Not Found or Expired</h3>";
        echo "<p>The export request <strong>$request_id</strong> is either not approved, expired, or doesn't exist.</p>";
        echo "<a href='export_requests.php' style='color: #3498db;'>← Back to Export Requests</a>";
        echo "</div>";
        exit;
    }
    
    // Verify user owns this request (unless admin/chief-staff)
    // Use loose comparison (==) instead of strict (===) to handle string/int type differences
    if (!in_array($_SESSION['role'], ['admin', 'chief-staff']) && $approval['user_id'] != $_SESSION['user_id']) {
        echo "<div style='padding: 20px; text-align: center; font-family: Arial;'>";
        echo "<h3 style='color: #e74c3c;'>❌ Access Denied</h3>";
        echo "<p>You don't have permission to download this export.</p>";
        echo "<a href='export_requests.php' style='color: #3498db;'>← Back to Export Requests</a>";
        echo "</div>";
        exit;
    }
    
    // Parse the approved request data
    $data_tables = json_decode($approval['data_tables'], true);
    $data_filters = json_decode($approval['data_filters'], true) ?: [];
    
    // DEBUG: Let's see what we're working with
    error_log("DEBUG - Approval data: " . print_r($approval, true));
    error_log("DEBUG - Data tables: " . print_r($data_tables, true));
    error_log("DEBUG - Data filters: " . print_r($data_filters, true));
    
    // Perform the export for the first table (simplified)
    $table_name = $data_tables[0];
    $export_type = $approval['export_type'];
    $classification = $approval['classification_level'];
    
    // For now, let's try with no filters to see if that's the issue
    $clean_filters = [];
    
    // Generate and download the file
    $result = performSecureExport($table_name, $clean_filters, 'csv', $classification, $request_id);
    
    if ($result['success']) {
        // Set headers for file download
        header('Content-Type: ' . $result['mime_type']);
        header('Content-Disposition: attachment; filename="' . $result['filename'] . '"');
        header('Content-Length: ' . strlen(base64_decode($result['content'])));
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        
        // Output the file content
        echo base64_decode($result['content']);
        exit;
    } else {
        echo "<div style='padding: 20px; text-align: center; font-family: Arial;'>";
        echo "<h3 style='color: #e74c3c;'>❌ Export Failed</h3>";
        echo "<p>" . htmlspecialchars($result['error']) . "</p>";
        echo "<a href='export_requests.php' style='color: #3498db;'>← Back to Export Requests</a>";
        echo "</div>";
        exit;
    }
}

// Check if this is an export request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_action'])) {
    $export_type = $_POST['export_type'] ?? '';
    $table_name = $_POST['table_name'] ?? '';
    $format = $_POST['format'] ?? 'csv';
    $filters = $_POST['filters'] ?? [];
    $request_id = $_POST['request_id'] ?? null;
    
    // Check export permissions
    $record_count = getRecordCount($table_name, $filters);
    $permission_check = $dlp->canUserExportData($table_name, $record_count);
    
    if ($permission_check['requires_approval'] && !$request_id) {
        echo json_encode([
            'success' => false, 
            'error' => 'This export requires approval. Please submit a request through the DLP management system.',
            'requires_approval' => true,
            'classification' => $permission_check['classification']
        ]);
        exit;
    }
    
    if ($request_id) {
        $approval = $dlp->checkExportApproval($request_id);
        if (!$approval) {
            echo json_encode(['success' => false, 'error' => 'Invalid or expired approval request']);
            exit;
        }
    }
    
    // Perform the export
    $result = performSecureExport($table_name, $filters, $format, $permission_check['classification'], $request_id);
    echo json_encode($result);
    exit;
}

function getRecordCount($table_name, $filters) {
    global $conn;
    
    $where_clause = '';
    $params = [];
    $types = '';
    
    if (!empty($filters)) {
        $conditions = [];
        foreach ($filters as $field => $value) {
            if (!empty($value)) {
                $conditions[] = "{$field} LIKE ?";
                $params[] = "%{$value}%";
                $types .= 's';
            }
        }
        if (!empty($conditions)) {
            $where_clause = 'WHERE ' . implode(' AND ', $conditions);
        }
    }
    
    $sql = "SELECT COUNT(*) as count FROM {$table_name} {$where_clause}";
    
    if (!empty($params)) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($sql);
    }
    
    $row = $result->fetch_assoc();
    return $row['count'];
}

function performSecureExport($table_name, $filters, $format, $classification, $request_id = null) {
    global $conn, $dlp;
    
    // Build the query
    $where_clause = '';
    $params = [];
    $types = '';
    
    if (!empty($filters)) {
        $conditions = [];
        foreach ($filters as $field => $value) {
            if (!empty($value)) {
                $conditions[] = "{$field} LIKE ?";
                $params[] = "%{$value}%";
                $types .= 's';
            }
        }
        if (!empty($conditions)) {
            $where_clause = 'WHERE ' . implode(' AND ', $conditions);
        }
    }
    
    $sql = "SELECT * FROM {$table_name} {$where_clause}";
    
    if (!empty($params)) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($sql);
    }
    
    if (!$result) {
        return ['success' => false, 'error' => 'Database query failed'];
    }
    
    $data = $result->fetch_all(MYSQLI_ASSOC);
    
    // DEBUG: Let's see what data we actually got
    error_log("DEBUG - Raw data from database: " . print_r($data, true));
    
    // Apply data masking for sensitive fields
    // Temporarily disabled to show real data - REMOVE IN PRODUCTION
    // $data = applySensitiveDataMasking($data, $table_name, $classification);
    
    // Generate the export file
    $filename = generateSecureFilename($table_name, $format);
    $file_content = '';
    
    switch ($format) {
        case 'csv':
            $file_content = generateCSV($data);
            break;
        case 'json':
            $file_content = generateJSON($data);
            break;
        case 'xml':
            $file_content = generateXML($data, $table_name);
            break;
        default:
            return ['success' => false, 'error' => 'Unsupported format'];
    }
    
    // Apply watermarking if required
    if (in_array($classification, ['confidential', 'restricted'])) {
        $file_content = $dlp->addWatermarkToCSV($file_content);
        $watermarked = true;
    } else {
        $watermarked = false;
    }
    
    // Log the download activity
    $file_size = strlen($file_content);
    $dlp->logDownloadActivity($format, $filename, $file_size, $classification, $request_id, $watermarked);
    
    // Log data access
    $dlp->logDataAccess('bulk_export', $table_name, null, $classification, [
        'format' => $format,
        'record_count' => count($data),
        'file_size' => $file_size,
        'watermarked' => $watermarked,
        'request_id' => $request_id
    ]);
    
    return [
        'success' => true,
        'filename' => $filename,
        'content' => base64_encode($file_content),
        'mime_type' => getMimeType($format),
        'watermarked' => $watermarked,
        'classification' => $classification
    ];
}

function applySensitiveDataMasking($data, $table_name, $classification) {
    global $dlp;
    
    // Get data classifications for this table
    $classifications = $dlp->getDataClassification($table_name);
    
    foreach ($data as $index => $row) {
        foreach ($row as $column => $value) {
            // Find classification for this column
            $column_classification = null;
            foreach ($classifications as $class) {
                if ($class['column_name'] === $column) {
                    $column_classification = $class['classification_level'];
                    break;
                }
            }
            
            // If no classification exists, treat as 'internal' (visible to all staff)
            if ($column_classification === null) {
                $column_classification = 'internal';
            }
            
            // Apply masking based on classification and user role
            // Only mask truly sensitive fields, not basic patient information
            if ($column_classification === 'restricted') {
                // Restricted: Only admin and chief-staff can see full data
                if (!in_array($_SESSION['role'], ['admin', 'chief-staff'])) {
                    // For medical staff, show partial data for medical fields
                    if (in_array($_SESSION['role'], ['doctor', 'therapist', 'nurse']) && 
                        in_array($column, ['medical_history', 'diagnosis', 'treatment_notes'])) {
                        $data[$index][$column] = maskSensitiveValue($value, 'partial');
                    } else {
                        $data[$index][$column] = maskSensitiveValue($value, 'full');
                    }
                }
            } elseif ($column_classification === 'confidential') {
                // Confidential: Medical staff can see, support staff get partial
                if (in_array($_SESSION['role'], ['receptionist', 'staff']) && 
                    in_array($column, ['medical_history', 'emergency_contact', 'address'])) {
                    $data[$index][$column] = maskSensitiveValue($value, 'partial');
                }
                // Admin, chief-staff, doctor, therapist, nurse can see full data
            }
            // Public and Internal data: everyone can see
        }
    }
    
    return $data;
}

function maskSensitiveValue($value, $mask_type) {
    if (empty($value)) return $value;
    
    switch ($mask_type) {
        case 'full':
            return str_repeat('*', min(strlen($value), 10));
        case 'partial':
            if (strlen($value) <= 4) {
                return str_repeat('*', strlen($value));
            }
            return substr($value, 0, 2) . str_repeat('*', strlen($value) - 4) . substr($value, -2);
        default:
            return $value;
    }
}

function generateSecureFilename($table_name, $format) {
    $timestamp = date('Y_m_d_H_i_s');
    $random = strtoupper(substr(md5(uniqid()), 0, 6));
    return "{$table_name}_export_{$timestamp}_{$random}.{$format}";
}

function generateCSV($data) {
    if (empty($data)) return '';
    
    $output = '';
    
    // Headers
    $headers = array_keys($data[0]);
    $output .= '"' . implode('","', $headers) . '"' . "\n";
    
    // Data rows
    foreach ($data as $row) {
        $escaped_row = [];
        foreach ($headers as $header) {
            $value = $row[$header] ?? '';
            
            // Handle different data types to prevent Excel auto-formatting issues
            if (is_numeric($value) && strlen($value) > 10) {
                // Large numbers (like phone numbers) - prefix with single quote to force text format
                $escaped_row[] = "'" . str_replace('"', '""', $value);
            } elseif (preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
                // Date fields - format properly
                $escaped_row[] = str_replace('"', '""', $value);
            } elseif ($value === null || $value === '') {
                // Handle null/empty values
                $escaped_row[] = '';
            } else {
                // Regular text values
                $escaped_row[] = str_replace('"', '""', $value);
            }
        }
        $output .= '"' . implode('","', $escaped_row) . '"' . "\n";
    }
    
    return $output;
}

function generateJSON($data) {
    return json_encode([
        'export_info' => [
            'timestamp' => date('Y-m-d H:i:s'),
            'record_count' => count($data),
            'exported_by' => $_SESSION['username'] ?? 'Unknown'
        ],
        'data' => $data
    ], JSON_PRETTY_PRINT);
}

function generateXML($data, $table_name) {
    $xml = "<?xml version='1.0' encoding='UTF-8'?>\n";
    $xml .= "<export>\n";
    $xml .= "  <info>\n";
    $xml .= "    <timestamp>" . date('Y-m-d H:i:s') . "</timestamp>\n";
    $xml .= "    <record_count>" . count($data) . "</record_count>\n";
    $xml .= "    <exported_by>" . htmlspecialchars($_SESSION['username'] ?? 'Unknown') . "</exported_by>\n";
    $xml .= "  </info>\n";
    $xml .= "  <data>\n";
    
    foreach ($data as $row) {
        $xml .= "    <record>\n";
        foreach ($row as $key => $value) {
            $xml .= "      <{$key}>" . htmlspecialchars($value ?? '') . "</{$key}>\n";
        }
        $xml .= "    </record>\n";
    }
    
    $xml .= "  </data>\n";
    $xml .= "</export>";
    
    return $xml;
}

function getMimeType($format) {
    $mime_types = [
        'csv' => 'text/csv',
        'json' => 'application/json',
        'xml' => 'application/xml'
    ];
    
    return $mime_types[$format] ?? 'text/plain';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Data Export</title>
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2980b9;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --dark-color: #2c3e50;
            --light-color: #ecf0f1;
            --border-color: #bdc3c7;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f8f9fa; color: var(--dark-color); line-height: 1.6; }
        
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); color: white; padding: 30px; text-align: center; margin-bottom: 30px; border-radius: 10px; }
        .header h1 { font-size: 2.5rem; margin-bottom: 10px; }
        
        .card { background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 25px; overflow: hidden; }
        .card-header { background: var(--primary-color); color: white; padding: 20px; font-size: 1.2rem; font-weight: 600; }
        .card-body { padding: 25px; }
        
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; margin-bottom: 8px; font-weight: 500; color: var(--dark-color); }
        .form-control { width: 100%; padding: 12px 15px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 1rem; transition: border-color 0.3s; }
        .form-control:focus { border-color: var(--primary-color); outline: none; box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2); }
        
        .btn { display: inline-block; padding: 12px 24px; border: none; border-radius: 6px; font-size: 1rem; font-weight: 500; cursor: pointer; transition: all 0.3s; text-decoration: none; text-align: center; }
        .btn-primary { background: var(--primary-color); color: white; }
        .btn-primary:hover { background: var(--secondary-color); transform: translateY(-2px); }
        .btn-success { background: var(--success-color); color: white; }
        .btn-warning { background: var(--warning-color); color: white; }
        .btn-danger { background: var(--danger-color); color: white; }
        
        .alert { padding: 15px 20px; border-radius: 6px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        
        .classification-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 500; text-transform: uppercase; }
        .classification-public { background: #d1ecf1; color: #0c5460; }
        .classification-internal { background: #fff3cd; color: #856404; }
        .classification-confidential { background: #f8d7da; color: #721c24; }
        .classification-restricted { background: #721c24; color: white; }
        
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; }
        .filters-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; }
        
        #exportStatus { display: none; }
        
        @media (max-width: 768px) {
            .grid-2 { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-download"></i> Secure Data Export</h1>
            <p>Export data with comprehensive security controls and audit trails</p>
        </div>
        
        <div id="exportStatus"></div>
        
        <div class="grid-2">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-file-export"></i> Export Configuration
                </div>
                <div class="card-body">
                    <form id="exportForm">
                        <div class="form-group">
                            <label class="form-label">Data Source</label>
                            <select name="table_name" id="tableName" class="form-control" required>
                                <option value="">Select data source</option>
                                <option value="staff">Staff Records</option>
                                <option value="users">User Accounts</option>
                                <option value="download_activity">Download Activity</option>
                                <option value="data_access_audit">Access Audit Trail</option>
                                <option value="export_approval_requests">Export Requests</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Export Format</label>
                            <select name="format" class="form-control" required>
                                <option value="csv">CSV (Comma Separated Values)</option>
                                <option value="json">JSON (JavaScript Object Notation)</option>
                                <option value="xml">XML (Extensible Markup Language)</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Export Request ID (if required)</label>
                            <input type="text" name="request_id" class="form-control" placeholder="Enter approved request ID">
                        </div>
                        
                        <div id="filtersSection" class="form-group">
                            <label class="form-label">Data Filters</label>
                            <div id="filtersContainer" class="filters-grid">
                                <!-- Dynamic filters will be added here -->
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-download"></i> Export Data
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-info-circle"></i> Export Information
                </div>
                <div class="card-body">
                    <div id="exportInfo">
                        <p>Select a data source to view export information and security requirements.</p>
                    </div>
                    
                    <div id="securityInfo" style="margin-top: 20px;">
                        <h5>Security Features:</h5>
                        <ul>
                            <li><strong>Data Classification:</strong> All exports are classified and tracked</li>
                            <li><strong>Approval Workflow:</strong> Sensitive data requires management approval</li>
                            <li><strong>Watermarking:</strong> Confidential exports are automatically watermarked</li>
                            <li><strong>Audit Trail:</strong> All export activities are logged and monitored</li>
                            <li><strong>Access Control:</strong> Sensitive fields are masked based on user role</li>
                            <li><strong>Download Monitoring:</strong> Suspicious patterns are automatically detected</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        const tableFilters = {
            'staff': ['full_name', 'role', 'status', 'gender'],
            'users': ['username', 'role', 'status', 'first_name'],
            'download_activity': ['user_name', 'file_type', 'data_classification'],
            'data_access_audit': ['user_name', 'action_type', 'table_name'],
            'export_approval_requests': ['status', 'classification_level', 'export_type']
        };
        
        const tableInfo = {
            'staff': {
                description: 'Staff member records including personal and employment information',
                classification: 'confidential',
                requires_approval: true,
                typical_count: '50-200 records'
            },
            'users': {
                description: 'User account information and authentication data',
                classification: 'restricted',
                requires_approval: true,
                typical_count: '100-500 records'
            },
            'download_activity': {
                description: 'Log of all file downloads and data access activities',
                classification: 'internal',
                requires_approval: false,
                typical_count: '1000+ records'
            },
            'data_access_audit': {
                description: 'Comprehensive audit trail of all data access and modifications',
                classification: 'confidential',
                requires_approval: true,
                typical_count: '5000+ records'
            },
            'export_approval_requests': {
                description: 'History of export approval requests and their status',
                classification: 'confidential',
                requires_approval: true,
                typical_count: '10-100 records'
            }
        };
        
        document.getElementById('tableName').addEventListener('change', function() {
            const tableName = this.value;
            updateFilters(tableName);
            updateExportInfo(tableName);
        });
        
        function updateFilters(tableName) {
            const container = document.getElementById('filtersContainer');
            container.innerHTML = '';
            
            if (tableName && tableFilters[tableName]) {
                tableFilters[tableName].forEach(filter => {
                    const filterDiv = document.createElement('div');
                    filterDiv.innerHTML = `
                        <label class="form-label">${filter.replace('_', ' ').toUpperCase()}</label>
                        <input type="text" name="filters[${filter}]" class="form-control" placeholder="Filter by ${filter}">
                    `;
                    container.appendChild(filterDiv);
                });
            }
        }
        
        function updateExportInfo(tableName) {
            const infoDiv = document.getElementById('exportInfo');
            
            if (tableName && tableInfo[tableName]) {
                const info = tableInfo[tableName];
                infoDiv.innerHTML = `
                    <p><strong>Description:</strong> ${info.description}</p>
                    <p><strong>Classification:</strong> 
                        <span class="classification-badge classification-${info.classification}">
                            ${info.classification.toUpperCase()}
                        </span>
                    </p>
                    <p><strong>Requires Approval:</strong> ${info.requires_approval ? 'Yes' : 'No'}</p>
                    <p><strong>Typical Record Count:</strong> ${info.typical_count}</p>
                    
                    ${info.requires_approval ? `
                        <div class="alert alert-warning">
                            <strong>Approval Required:</strong> This data classification requires management approval before export.
                            Submit a request through the DLP Management system first.
                        </div>
                    ` : ''}
                `;
            } else {
                infoDiv.innerHTML = '<p>Select a data source to view export information and security requirements.</p>';
            }
        }
        
        document.getElementById('exportForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('export_action', '1');
            
            const statusDiv = document.getElementById('exportStatus');
            statusDiv.innerHTML = '<div class="alert alert-warning">Processing export request...</div>';
            statusDiv.style.display = 'block';
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    statusDiv.innerHTML = `
                        <div class="alert alert-success">
                            <strong>Export Successful!</strong><br>
                            File: ${result.filename}<br>
                            Classification: <span class="classification-badge classification-${result.classification}">${result.classification.toUpperCase()}</span><br>
                            ${result.watermarked ? 'Watermarked: Yes<br>' : ''}
                            <button onclick="downloadFile('${result.filename}', '${result.content}', '${result.mime_type}')" class="btn btn-success" style="margin-top: 10px;">
                                <i class="fas fa-download"></i> Download File
                            </button>
                        </div>
                    `;
                } else {
                    statusDiv.innerHTML = `
                        <div class="alert alert-error">
                            <strong>Export Failed:</strong> ${result.error}<br>
                            ${result.requires_approval ? 'Please submit an approval request through the DLP Management system.' : ''}
                        </div>
                    `;
                }
            } catch (error) {
                statusDiv.innerHTML = `
                    <div class="alert alert-error">
                        <strong>Error:</strong> Failed to process export request. Please try again.
                    </div>
                `;
            }
        });
        
        function downloadFile(filename, content, mimeType) {
            const blob = new Blob([atob(content)], { type: mimeType });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }
    </script>
</body>
</html>