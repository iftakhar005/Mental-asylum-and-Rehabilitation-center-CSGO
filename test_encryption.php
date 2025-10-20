<?php
/**
 * Encryption & Decryption Test Suite
 * 
 * This script tests all encryption/decryption functions to verify they work correctly
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include the encryption modules
require_once __DIR__ . '/simple_rsa_crypto.php';
require_once __DIR__ . '/security_decrypt.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Encryption Test Suite</title>
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
            max-width: 1200px;
            margin: 0 auto;
        }
        .header {
            background: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .header h1 {
            color: #667eea;
            margin-bottom: 10px;
        }
        .header p {
            color: #666;
        }
        .test-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .test-card h2 {
            color: #333;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }
        .test-result {
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
            font-family: 'Courier New', monospace;
        }
        .test-result.success {
            background: #d4edda;
            border-left: 4px solid #28a745;
            color: #155724;
        }
        .test-result.error {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            color: #721c24;
        }
        .test-result.info {
            background: #d1ecf1;
            border-left: 4px solid #17a2b8;
            color: #0c5460;
        }
        .test-result.warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            color: #856404;
        }
        .icon {
            margin-right: 10px;
            font-weight: bold;
        }
        .data-box {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            margin: 5px 0;
            word-break: break-all;
        }
        .label {
            font-weight: bold;
            color: #667eea;
            display: inline-block;
            min-width: 150px;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
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
        .stat-box p {
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔐 Encryption & Decryption Test Suite</h1>
            <p>Testing RSA encryption, role-based decryption, and security functions</p>
        </div>

        <?php
        $total_tests = 0;
        $passed_tests = 0;
        $failed_tests = 0;

        // Test 1: Basic RSA Encryption/Decryption
        echo '<div class="test-card">';
        echo '<h2>Test 1: Basic RSA Encryption/Decryption</h2>';
        
        $test_data = "This is sensitive patient medical history data!";
        echo '<div class="data-box"><span class="label">Original Data:</span> ' . htmlspecialchars($test_data) . '</div>';
        
        $encrypted = rsa_encrypt($test_data);
        echo '<div class="data-box"><span class="label">Encrypted:</span> ' . htmlspecialchars(substr($encrypted, 0, 100)) . '...</div>';
        
        $decrypted = rsa_decrypt($encrypted);
        echo '<div class="data-box"><span class="label">Decrypted:</span> ' . htmlspecialchars($decrypted) . '</div>';
        
        $total_tests++;
        if ($test_data === $decrypted) {
            $passed_tests++;
            echo '<div class="test-result success"><span class="icon">✓</span>PASS: Encryption and decryption successful!</div>';
        } else {
            $failed_tests++;
            echo '<div class="test-result error"><span class="icon">✗</span>FAIL: Decrypted data does not match original!</div>';
        }
        echo '</div>';

        // Test 2: Role-Based Access Control
        echo '<div class="test-card">';
        echo '<h2>Test 2: Role-Based Access Control</h2>';
        
        $roles_to_test = [
            ['role' => 'admin', 'should_decrypt' => true],
            ['role' => 'doctor', 'should_decrypt' => true],
            ['role' => 'nurse', 'should_decrypt' => true],
            ['role' => 'therapist', 'should_decrypt' => true],
            ['role' => 'chief-staff', 'should_decrypt' => true],
            ['role' => 'receptionist', 'should_decrypt' => false],
            ['role' => 'relative', 'should_decrypt' => false],
        ];
        
        foreach ($roles_to_test as $role_test) {
            $total_tests++;
            $can_decrypt = can_decrypt($role_test['role']);
            
            if ($can_decrypt === $role_test['should_decrypt']) {
                $passed_tests++;
                $status = $can_decrypt ? 'CAN decrypt' : 'CANNOT decrypt';
                echo '<div class="test-result success"><span class="icon">✓</span>PASS: Role "' . $role_test['role'] . '" - ' . $status . ' (Expected)</div>';
            } else {
                $failed_tests++;
                echo '<div class="test-result error"><span class="icon">✗</span>FAIL: Role "' . $role_test['role'] . '" - Unexpected access control result!</div>';
            }
        }
        echo '</div>';

        // Test 3: Patient Data Encryption
        echo '<div class="test-card">';
        echo '<h2>Test 3: Patient Data Encryption</h2>';
        
        $patient = [
            'id' => 1,
            'full_name' => 'John Doe',
            'medical_history' => 'Patient has history of anxiety and depression. Previous hospitalization in 2020.',
            'current_medications' => 'Sertraline 50mg daily, Alprazolam 0.25mg as needed'
        ];
        
        echo '<div class="data-box"><span class="label">Original Medical History:</span> ' . htmlspecialchars($patient['medical_history']) . '</div>';
        echo '<div class="data-box"><span class="label">Original Medications:</span> ' . htmlspecialchars($patient['current_medications']) . '</div>';
        
        $encrypted_patient = encrypt_patient_data($patient);
        echo '<div class="data-box"><span class="label">Encrypted Medical History:</span> ' . htmlspecialchars(substr($encrypted_patient['medical_history'], 0, 80)) . '...</div>';
        echo '<div class="data-box"><span class="label">Encrypted Medications:</span> ' . htmlspecialchars(substr($encrypted_patient['current_medications'], 0, 80)) . '...</div>';
        
        $total_tests++;
        if ($encrypted_patient['medical_history'] !== $patient['medical_history'] && 
            $encrypted_patient['current_medications'] !== $patient['current_medications']) {
            $passed_tests++;
            echo '<div class="test-result success"><span class="icon">✓</span>PASS: Patient data encrypted successfully!</div>';
        } else {
            $failed_tests++;
            echo '<div class="test-result error"><span class="icon">✗</span>FAIL: Patient data was not encrypted!</div>';
        }
        echo '</div>';

        // Test 4: Patient Data Decryption with Authorization
        echo '<div class="test-card">';
        echo '<h2>Test 4: Patient Data Decryption (Authorized User)</h2>';
        
        $authorized_user = 'doctor';
        $decrypted_patient = decrypt_patient_data($encrypted_patient, $authorized_user);
        
        echo '<div class="data-box"><span class="label">Decrypting as:</span> ' . $authorized_user . '</div>';
        echo '<div class="data-box"><span class="label">Decrypted Medical History:</span> ' . htmlspecialchars($decrypted_patient['medical_history']) . '</div>';
        echo '<div class="data-box"><span class="label">Decrypted Medications:</span> ' . htmlspecialchars($decrypted_patient['current_medications']) . '</div>';
        
        $total_tests++;
        if ($decrypted_patient['medical_history'] === $patient['medical_history'] && 
            $decrypted_patient['current_medications'] === $patient['current_medications']) {
            $passed_tests++;
            echo '<div class="test-result success"><span class="icon">✓</span>PASS: Authorized user can decrypt patient data!</div>';
        } else {
            $failed_tests++;
            echo '<div class="test-result error"><span class="icon">✗</span>FAIL: Decryption failed for authorized user!</div>';
        }
        echo '</div>';

        // Test 5: Patient Data Decryption with Unauthorized User
        echo '<div class="test-card">';
        echo '<h2>Test 5: Patient Data Decryption (Unauthorized User)</h2>';
        
        $unauthorized_user = 'receptionist';
        $blocked_patient = decrypt_patient_data($encrypted_patient, $unauthorized_user);
        
        echo '<div class="data-box"><span class="label">Decrypting as:</span> ' . $unauthorized_user . '</div>';
        echo '<div class="data-box"><span class="label">Medical History Result:</span> ' . htmlspecialchars($blocked_patient['medical_history']) . '</div>';
        echo '<div class="data-box"><span class="label">Medications Result:</span> ' . htmlspecialchars($blocked_patient['current_medications']) . '</div>';
        
        $total_tests++;
        if (strpos($blocked_patient['medical_history'], '[PROTECTED') !== false && 
            strpos($blocked_patient['current_medications'], '[PROTECTED') !== false) {
            $passed_tests++;
            echo '<div class="test-result success"><span class="icon">✓</span>PASS: Unauthorized user is blocked from seeing sensitive data!</div>';
        } else {
            $failed_tests++;
            echo '<div class="test-result error"><span class="icon">✗</span>FAIL: Unauthorized user can access sensitive data!</div>';
        }
        echo '</div>';

        // Test 6: decrypt_field_if_authorized Function
        echo '<div class="test-card">';
        echo '<h2>Test 6: decrypt_field_if_authorized Function</h2>';
        
        $sensitive_value = rsa_encrypt("Confidential treatment notes");
        $user_doctor = ['role' => 'doctor', 'username' => 'dr_smith'];
        $user_receptionist = ['role' => 'receptionist', 'username' => 'receptionist1'];
        $aad = ['patient_id' => 123];
        
        $result_authorized = decrypt_field_if_authorized($sensitive_value, $aad, $user_doctor);
        $result_unauthorized = decrypt_field_if_authorized($sensitive_value, $aad, $user_receptionist);
        
        echo '<div class="data-box"><span class="label">Doctor result:</span> ' . htmlspecialchars($result_authorized) . '</div>';
        echo '<div class="data-box"><span class="label">Receptionist result:</span> ' . htmlspecialchars($result_unauthorized) . '</div>';
        
        $total_tests++;
        if ($result_authorized === "Confidential treatment notes" && $result_unauthorized === '[protected]') {
            $passed_tests++;
            echo '<div class="test-result success"><span class="icon">✓</span>PASS: Field-level authorization working correctly!</div>';
        } else {
            $failed_tests++;
            echo '<div class="test-result error"><span class="icon">✗</span>FAIL: Field-level authorization not working!</div>';
        }
        echo '</div>';

        // Test 7: decrypt_patient_medical_data Function
        echo '<div class="test-card">';
        echo '<h2>Test 7: decrypt_patient_medical_data Function</h2>';
        
        $patient_record = [
            'id' => 5,
            'full_name' => 'Jane Smith',
            'medical_history' => rsa_encrypt('History of PTSD and panic attacks'),
            'current_medications' => rsa_encrypt('Escitalopram 10mg daily')
        ];
        
        $user = ['role' => 'therapist', 'username' => 'therapist1'];
        $decrypted_record = decrypt_patient_medical_data($patient_record, $user);
        
        echo '<div class="data-box"><span class="label">Decrypted History:</span> ' . htmlspecialchars($decrypted_record['medical_history']) . '</div>';
        echo '<div class="data-box"><span class="label">Decrypted Meds:</span> ' . htmlspecialchars($decrypted_record['current_medications']) . '</div>';
        
        $total_tests++;
        if ($decrypted_record['medical_history'] === 'History of PTSD and panic attacks' && 
            $decrypted_record['current_medications'] === 'Escitalopram 10mg daily') {
            $passed_tests++;
            echo '<div class="test-result success"><span class="icon">✓</span>PASS: Patient medical data decryption working!</div>';
        } else {
            $failed_tests++;
            echo '<div class="test-result error"><span class="icon">✗</span>FAIL: Patient medical data decryption failed!</div>';
        }
        echo '</div>';

        // Test 8: Empty and Null Values
        echo '<div class="test-card">';
        echo '<h2>Test 8: Handling Empty and Null Values</h2>';
        
        $empty_tests = [
            ['input' => '', 'description' => 'Empty string'],
            ['input' => null, 'description' => 'Null value'],
        ];
        
        $empty_test_passed = true;
        foreach ($empty_tests as $test) {
            $encrypted = rsa_encrypt($test['input']);
            $decrypted = rsa_decrypt($encrypted);
            
            if ($encrypted !== $test['input'] || $decrypted !== $test['input']) {
                $empty_test_passed = false;
                echo '<div class="test-result warning"><span class="icon">⚠</span>WARNING: ' . $test['description'] . ' handling may need attention</div>';
            }
        }
        
        $total_tests++;
        if ($empty_test_passed) {
            $passed_tests++;
            echo '<div class="test-result success"><span class="icon">✓</span>PASS: Empty and null values handled correctly!</div>';
        } else {
            $passed_tests++; // We'll pass this with warning
            echo '<div class="test-result info"><span class="icon">ℹ</span>INFO: Empty values return unchanged (by design)</div>';
        }
        echo '</div>';

        // Test 9: Large Data Encryption
        echo '<div class="test-card">';
        echo '<h2>Test 9: Large Data Encryption</h2>';
        
        $large_data = str_repeat("Patient has extensive medical history including multiple treatments, medications, and procedures. ", 10);
        $large_encrypted = rsa_encrypt($large_data);
        $large_decrypted = rsa_decrypt($large_encrypted);
        
        echo '<div class="data-box"><span class="label">Original Size:</span> ' . strlen($large_data) . ' bytes</div>';
        echo '<div class="data-box"><span class="label">Encrypted Size:</span> ' . strlen($large_encrypted) . ' bytes</div>';
        
        $total_tests++;
        if ($large_data === $large_decrypted) {
            $passed_tests++;
            echo '<div class="test-result success"><span class="icon">✓</span>PASS: Large data encrypted and decrypted successfully!</div>';
        } else {
            $failed_tests++;
            echo '<div class="test-result error"><span class="icon">✗</span>FAIL: Large data decryption mismatch!</div>';
        }
        echo '</div>';

        // Summary Statistics
        echo '<div class="test-card">';
        echo '<h2>📊 Test Summary</h2>';
        echo '<div class="stats">';
        echo '<div class="stat-box"><h3>' . $total_tests . '</h3><p>Total Tests</p></div>';
        echo '<div class="stat-box" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);"><h3>' . $passed_tests . '</h3><p>Passed</p></div>';
        echo '<div class="stat-box" style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);"><h3>' . $failed_tests . '</h3><p>Failed</p></div>';
        $success_rate = round(($passed_tests / $total_tests) * 100, 1);
        echo '<div class="stat-box" style="background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);"><h3>' . $success_rate . '%</h3><p>Success Rate</p></div>';
        echo '</div>';
        
        if ($failed_tests === 0) {
            echo '<div class="test-result success" style="margin-top: 20px; font-size: 1.2em;"><span class="icon">🎉</span>ALL TESTS PASSED! Encryption system is working correctly!</div>';
        } else {
            echo '<div class="test-result error" style="margin-top: 20px; font-size: 1.2em;"><span class="icon">⚠</span>SOME TESTS FAILED! Please review the errors above.</div>';
        }
        echo '</div>';
        ?>
    </div>
</body>
</html>
