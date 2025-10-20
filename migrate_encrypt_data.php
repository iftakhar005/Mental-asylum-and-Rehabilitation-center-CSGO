<?php
/**
 * Data Encryption Migration Script
 * 
 * This script encrypts existing unencrypted patient data in the database.
 * Run this ONCE to encrypt all existing patient medical records.
 * 
 * WARNING: Make a database backup before running this script!
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/simple_rsa_crypto.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Encrypt Existing Patient Data</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            min-height: 100vh;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
        }
        .card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        h1 {
            color: #667eea;
            margin-bottom: 15px;
        }
        .warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
            color: #856404;
        }
        .success {
            background: #d4edda;
            border-left: 4px solid #28a745;
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            color: #155724;
        }
        .error {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            color: #721c24;
        }
        .info {
            background: #d1ecf1;
            border-left: 4px solid #17a2b8;
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            color: #0c5460;
        }
        button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            margin: 10px 5px;
        }
        button:hover {
            opacity: 0.9;
        }
        button.danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        }
        .result-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
            font-family: 'Courier New', monospace;
            max-height: 400px;
            overflow-y: auto;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .stat-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        .stat-box h3 {
            font-size: 2em;
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h1>🔐 Encrypt Existing Patient Data</h1>
            <p>This script will encrypt all unencrypted patient medical records in your database.</p>
            
            <div class="warning">
                <strong>⚠️ WARNING:</strong> This operation will modify your database!
                <ul style="margin-top: 10px; margin-left: 20px;">
                    <li>Make sure you have a <strong>database backup</strong></li>
                    <li>This should only be run <strong>ONCE</strong></li>
                    <li>Once encrypted, old code without decryption won't work properly</li>
                </ul>
            </div>

            <?php
            $action = $_GET['action'] ?? '';
            
            if ($action === 'check') {
                // Check current encryption status
                echo '<h2>📊 Current Database Status</h2>';
                
                $total_query = "SELECT COUNT(*) as total FROM patients";
                $total_result = $conn->query($total_query);
                $total_count = $total_result->fetch_assoc()['total'];
                
                $check_query = "SELECT 
                    COUNT(*) as total_patients,
                    SUM(CASE WHEN medical_history IS NOT NULL AND medical_history != '' THEN 1 ELSE 0 END) as has_medical_history,
                    SUM(CASE WHEN current_medications IS NOT NULL AND current_medications != '' THEN 1 ELSE 0 END) as has_medications
                FROM patients";
                
                $result = $conn->query($check_query);
                $stats = $result->fetch_assoc();
                
                echo '<div class="stats">';
                echo '<div class="stat-box"><h3>' . $stats['total_patients'] . '</h3><p>Total Patients</p></div>';
                echo '<div class="stat-box"><h3>' . $stats['has_medical_history'] . '</h3><p>With Medical History</p></div>';
                echo '<div class="stat-box"><h3>' . $stats['has_medications'] . '</h3><p>With Medications</p></div>';
                echo '</div>';
                
                // Sample data to detect if already encrypted
                $sample_query = "SELECT id, patient_id, medical_history, current_medications FROM patients WHERE medical_history IS NOT NULL AND medical_history != '' LIMIT 1";
                $sample_result = $conn->query($sample_query);
                
                if ($sample_result && $sample_result->num_rows > 0) {
                    $sample = $sample_result->fetch_assoc();
                    
                    // Try to detect if data is already encrypted (base64 pattern)
                    $is_encrypted = (bool)preg_match('/^[A-Za-z0-9+\/=]+$/', $sample['medical_history']) && 
                                    strlen($sample['medical_history']) > 100;
                    
                    if ($is_encrypted) {
                        echo '<div class="info"><strong>ℹ️ Detection:</strong> Data appears to be already encrypted (base64 format detected)</div>';
                    } else {
                        echo '<div class="warning"><strong>⚠️ Detection:</strong> Data appears to be unencrypted (plain text detected)</div>';
                        echo '<div class="result-box">';
                        echo '<strong>Sample Medical History (Patient ID: ' . htmlspecialchars($sample['patient_id']) . '):</strong><br>';
                        echo htmlspecialchars(substr($sample['medical_history'], 0, 200));
                        if (strlen($sample['medical_history']) > 200) echo '...';
                        echo '</div>';
                    }
                }
                
            } elseif ($action === 'encrypt') {
                // Perform encryption
                echo '<h2>🔒 Encrypting Patient Data...</h2>';
                
                $conn->begin_transaction();
                
                try {
                    // Get all patients with medical data
                    $patients_query = "SELECT id, patient_id, medical_history, current_medications FROM patients";
                    $patients_result = $conn->query($patients_query);
                    
                    $encrypted_count = 0;
                    $skipped_count = 0;
                    $errors = [];
                    
                    while ($patient = $patients_result->fetch_assoc()) {
                        $needs_update = false;
                        $encrypted_medical_history = $patient['medical_history'];
                        $encrypted_medications = $patient['current_medications'];
                        
                        // Encrypt medical_history if not empty and not already encrypted
                        if (!empty($patient['medical_history'])) {
                            // Check if already encrypted (contains only base64 characters and is long)
                            $is_encrypted = (bool)preg_match('/^[A-Za-z0-9+\/=]+$/', $patient['medical_history']) && 
                                          strlen($patient['medical_history']) > 100;
                            
                            if (!$is_encrypted) {
                                $encrypted_medical_history = rsa_encrypt($patient['medical_history']);
                                $needs_update = true;
                            }
                        }
                        
                        // Encrypt current_medications if not empty and not already encrypted
                        if (!empty($patient['current_medications'])) {
                            $is_encrypted = (bool)preg_match('/^[A-Za-z0-9+\/=]+$/', $patient['current_medications']) && 
                                          strlen($patient['current_medications']) > 100;
                            
                            if (!$is_encrypted) {
                                $encrypted_medications = rsa_encrypt($patient['current_medications']);
                                $needs_update = true;
                            }
                        }
                        
                        // Update if needed
                        if ($needs_update) {
                            $stmt = $conn->prepare("UPDATE patients SET medical_history = ?, current_medications = ? WHERE id = ?");
                            $stmt->bind_param("ssi", $encrypted_medical_history, $encrypted_medications, $patient['id']);
                            
                            if ($stmt->execute()) {
                                $encrypted_count++;
                                echo '<div class="success">✓ Encrypted data for Patient ID: ' . htmlspecialchars($patient['patient_id']) . '</div>';
                            } else {
                                $errors[] = "Failed to update Patient ID: " . $patient['patient_id'] . " - " . $stmt->error;
                            }
                            $stmt->close();
                        } else {
                            $skipped_count++;
                            echo '<div class="info">⊘ Skipped Patient ID: ' . htmlspecialchars($patient['patient_id']) . ' (already encrypted or empty)</div>';
                        }
                    }
                    
                    if (empty($errors)) {
                        $conn->commit();
                        echo '<div class="success"><strong>✓ SUCCESS!</strong> Encryption completed successfully!</div>';
                        echo '<div class="stats">';
                        echo '<div class="stat-box" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);"><h3>' . $encrypted_count . '</h3><p>Encrypted</p></div>';
                        echo '<div class="stat-box" style="background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);"><h3>' . $skipped_count . '</h3><p>Skipped</p></div>';
                        echo '</div>';
                    } else {
                        throw new Exception("Errors occurred during encryption:\n" . implode("\n", $errors));
                    }
                    
                } catch (Exception $e) {
                    $conn->rollback();
                    echo '<div class="error"><strong>✗ ERROR:</strong> ' . htmlspecialchars($e->getMessage()) . '</div>';
                    echo '<div class="warning">Database has been rolled back. No changes were made.</div>';
                }
                
            } else {
                // Show options
                echo '<h2>Available Actions</h2>';
                echo '<div style="margin: 20px 0;">';
                echo '<a href="?action=check"><button>📊 Check Current Status</button></a>';
                echo '<a href="?action=encrypt"><button class="danger">🔒 Start Encryption</button></a>';
                echo '</div>';
                
                echo '<div class="info">';
                echo '<h3>Instructions:</h3>';
                echo '<ol style="margin-left: 20px; line-height: 2;">';
                echo '<li>First, click <strong>"Check Current Status"</strong> to see what data needs encryption</li>';
                echo '<li>Make a <strong>database backup</strong> before proceeding</li>';
                echo '<li>Click <strong>"Start Encryption"</strong> to encrypt all unencrypted patient data</li>';
                echo '<li>After encryption, verify with the test scripts</li>';
                echo '</ol>';
                echo '</div>';
            }
            ?>
            
            <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #ddd;">
                <a href="test_encryption.php"><button>🔍 Run Encryption Tests</button></a>
                <a href="encryption_demo.php"><button>📚 View Usage Examples</button></a>
                <a href="?"><button>🏠 Back to Start</button></a>
            </div>
        </div>
    </div>
</body>
</html>
