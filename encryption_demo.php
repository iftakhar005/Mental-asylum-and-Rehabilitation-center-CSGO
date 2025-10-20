<?php
/**
 * Encryption Implementation Demo
 * 
 * Demonstrates how to use encryption in real-world scenarios
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/simple_rsa_crypto.php';
require_once __DIR__ . '/security_decrypt.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Encryption Demo - Practical Examples</title>
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
            max-width: 1400px;
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
        .demo-section {
            background: white;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .demo-section h2 {
            color: #333;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }
        .code-block {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 20px;
            border-radius: 8px;
            overflow-x: auto;
            margin: 15px 0;
            font-family: 'Courier New', monospace;
            font-size: 14px;
        }
        .code-block .comment {
            color: #6a9955;
        }
        .code-block .keyword {
            color: #569cd6;
        }
        .code-block .string {
            color: #ce9178;
        }
        .code-block .function {
            color: #dcdcaa;
        }
        .output-box {
            background: #f8f9fa;
            padding: 15px;
            border-left: 4px solid #667eea;
            margin: 10px 0;
            border-radius: 5px;
        }
        .scenario {
            background: #e7f3ff;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
        .scenario h3 {
            color: #0066cc;
            margin-bottom: 10px;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .card {
            background: #fff;
            border: 2px solid #667eea;
            border-radius: 10px;
            padding: 20px;
        }
        .card h3 {
            color: #667eea;
            margin-bottom: 10px;
        }
        .badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            margin: 5px;
        }
        .badge.success {
            background: #d4edda;
            color: #155724;
        }
        .badge.danger {
            background: #f8d7da;
            color: #721c24;
        }
        .badge.info {
            background: #d1ecf1;
            color: #0c5460;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔐 Encryption Implementation - Practical Demo</h1>
            <p>Real-world examples of using encryption in your healthcare system</p>
        </div>

        <!-- Example 1: Adding a New Patient with Encryption -->
        <div class="demo-section">
            <h2>📝 Example 1: Adding a New Patient with Encryption</h2>
            
            <div class="scenario">
                <h3>Scenario: Receptionist adds a new patient</h3>
                <p>When a new patient is admitted, their sensitive medical information should be encrypted before storing in the database.</p>
            </div>

            <div class="code-block">
<span class="comment">// In your patient_management.php or add_patient.php</span>
<span class="keyword">require_once</span> <span class="string">'simple_rsa_crypto.php'</span>;

<span class="comment">// Get patient data from form</span>
<span class="keyword">$patient_data</span> = [
    <span class="string">'full_name'</span> => <span class="keyword">$_POST</span>[<span class="string">'full_name'</span>],
    <span class="string">'medical_history'</span> => <span class="keyword">$_POST</span>[<span class="string">'medical_history'</span>],
    <span class="string">'current_medications'</span> => <span class="keyword">$_POST</span>[<span class="string">'current_medications'</span>]
];

<span class="comment">// Encrypt sensitive fields before inserting</span>
<span class="keyword">$encrypted_patient</span> = <span class="function">encrypt_patient_data</span>(<span class="keyword">$patient_data</span>);

<span class="comment">// Insert into database</span>
<span class="keyword">$stmt</span> = <span class="keyword">$conn</span>-><span class="function">prepare</span>(<span class="string">"INSERT INTO patients (full_name, medical_history, current_medications) VALUES (?, ?, ?)"</span>);
<span class="keyword">$stmt</span>-><span class="function">bind_param</span>(<span class="string">"sss"</span>, 
    <span class="keyword">$encrypted_patient</span>[<span class="string">'full_name'</span>],
    <span class="keyword">$encrypted_patient</span>[<span class="string">'medical_history'</span>],
    <span class="keyword">$encrypted_patient</span>[<span class="string">'current_medications'</span>]
);
<span class="keyword">$stmt</span>-><span class="function">execute</span>();
            </div>

            <?php
            // Demonstrate this
            $demo_patient = [
                'full_name' => 'John Doe',
                'medical_history' => 'Patient has history of anxiety disorder and mild depression. Previous hospitalization in 2020.',
                'current_medications' => 'Sertraline 50mg daily, Alprazolam 0.25mg as needed for panic attacks'
            ];
            
            $encrypted_demo = encrypt_patient_data($demo_patient);
            ?>

            <div class="output-box">
                <strong>Before Encryption:</strong><br>
                Medical History: <?php echo htmlspecialchars($demo_patient['medical_history']); ?><br><br>
                <strong>After Encryption (stored in DB):</strong><br>
                Medical History: <?php echo htmlspecialchars(substr($encrypted_demo['medical_history'], 0, 120)) . '...'; ?>
            </div>
        </div>

        <!-- Example 2: Retrieving Patient Data (Doctor View) -->
        <div class="demo-section">
            <h2>👨‍⚕️ Example 2: Doctor Viewing Patient Records</h2>
            
            <div class="scenario">
                <h3>Scenario: Doctor logs in and views patient information</h3>
                <p>Doctors should be able to decrypt and view sensitive patient data based on their role.</p>
            </div>

            <div class="code-block">
<span class="comment">// In your doctor_dashboard.php or patient viewing page</span>
<span class="keyword">require_once</span> <span class="string">'security_decrypt.php'</span>;

<span class="comment">// Get current user from session</span>
<span class="keyword">$current_user</span> = [
    <span class="string">'role'</span> => <span class="keyword">$_SESSION</span>[<span class="string">'role'</span>], <span class="comment">// e.g., 'doctor'</span>
    <span class="string">'username'</span> => <span class="keyword">$_SESSION</span>[<span class="string">'username'</span>]
];

<span class="comment">// Fetch patient from database</span>
<span class="keyword">$stmt</span> = <span class="keyword">$conn</span>-><span class="function">prepare</span>(<span class="string">"SELECT * FROM patients WHERE id = ?"</span>);
<span class="keyword">$stmt</span>-><span class="function">bind_param</span>(<span class="string">"i"</span>, <span class="keyword">$patient_id</span>);
<span class="keyword">$stmt</span>-><span class="function">execute</span>();
<span class="keyword">$patient</span> = <span class="keyword">$stmt</span>-><span class="function">get_result</span>()-><span class="function">fetch_assoc</span>();

<span class="comment">// Decrypt patient data based on user role</span>
<span class="keyword">$decrypted_patient</span> = <span class="function">decrypt_patient_medical_data</span>(<span class="keyword">$patient</span>, <span class="keyword">$current_user</span>);

<span class="comment">// Now display the decrypted data</span>
<span class="keyword">echo</span> <span class="keyword">$decrypted_patient</span>[<span class="string">'medical_history'</span>]; <span class="comment">// Shows actual data</span>
            </div>

            <?php
            // Demonstrate doctor access
            $doctor_user = ['role' => 'doctor', 'username' => 'dr_smith'];
            $decrypted_for_doctor = decrypt_patient_medical_data($encrypted_demo, $doctor_user);
            ?>

            <div class="grid">
                <div class="card">
                    <h3>✅ Doctor Access</h3>
                    <span class="badge success">Authorized</span>
                    <p><strong>Medical History:</strong><br><?php echo htmlspecialchars($decrypted_for_doctor['medical_history']); ?></p>
                </div>
            </div>
        </div>

        <!-- Example 3: Receptionist View (Unauthorized) -->
        <div class="demo-section">
            <h2>👤 Example 3: Receptionist Viewing Patient Records</h2>
            
            <div class="scenario">
                <h3>Scenario: Receptionist tries to view sensitive medical data</h3>
                <p>Receptionists should NOT be able to see sensitive medical information - data should be protected.</p>
            </div>

            <div class="code-block">
<span class="comment">// Same code as Example 2, but with receptionist role</span>
<span class="keyword">$current_user</span> = [
    <span class="string">'role'</span> => <span class="string">'receptionist'</span>,
    <span class="string">'username'</span> => <span class="string">'receptionist1'</span>
];

<span class="keyword">$decrypted_patient</span> = <span class="function">decrypt_patient_medical_data</span>(<span class="keyword">$patient</span>, <span class="keyword">$current_user</span>);

<span class="comment">// Sensitive fields will show [PROTECTED - Unauthorized]</span>
            </div>

            <?php
            // Demonstrate receptionist access
            $receptionist_user = ['role' => 'receptionist', 'username' => 'receptionist1'];
            $blocked_for_receptionist = decrypt_patient_medical_data($encrypted_demo, $receptionist_user);
            ?>

            <div class="grid">
                <div class="card">
                    <h3>🚫 Receptionist Access</h3>
                    <span class="badge danger">Unauthorized</span>
                    <p><strong>Medical History:</strong><br><?php echo htmlspecialchars($blocked_for_receptionist['medical_history']); ?></p>
                    <p><strong>Medications:</strong><br><?php echo htmlspecialchars($blocked_for_receptionist['current_medications']); ?></p>
                </div>
            </div>
        </div>

        <!-- Example 4: Batch Processing -->
        <div class="demo-section">
            <h2>📊 Example 4: Batch Processing Patient Records</h2>
            
            <div class="scenario">
                <h3>Scenario: Display list of patients for a doctor</h3>
                <p>When displaying multiple patient records, use batch decryption for efficiency.</p>
            </div>

            <div class="code-block">
<span class="comment">// In your patient listing page</span>
<span class="keyword">$current_user</span> = [<span class="string">'role'</span> => <span class="keyword">$_SESSION</span>[<span class="string">'role'</span>], <span class="string">'username'</span> => <span class="keyword">$_SESSION</span>[<span class="string">'username'</span>]];

<span class="comment">// Fetch multiple patients</span>
<span class="keyword">$result</span> = <span class="keyword">$conn</span>-><span class="function">query</span>(<span class="string">"SELECT * FROM patients LIMIT 10"</span>);
<span class="keyword">$patients</span> = [];
<span class="keyword">while</span> (<span class="keyword">$row</span> = <span class="keyword">$result</span>-><span class="function">fetch_assoc</span>()) {
    <span class="keyword">$patients</span>[] = <span class="keyword">$row</span>;
}

<span class="comment">// Batch decrypt all patient records</span>
<span class="keyword">$decrypted_patients</span> = <span class="function">batch_decrypt_records</span>(<span class="keyword">$patients</span>, <span class="keyword">$current_user</span>, <span class="string">'patient'</span>);

<span class="comment">// Display the decrypted data</span>
<span class="keyword">foreach</span> (<span class="keyword">$decrypted_patients</span> <span class="keyword">as</span> <span class="keyword">$patient</span>) {
    <span class="keyword">echo</span> <span class="keyword">$patient</span>[<span class="string">'full_name'</span>] . <span class="string">' - '</span> . <span class="keyword">$patient</span>[<span class="string">'medical_history'</span>];
}
            </div>
        </div>

        <!-- Role Access Summary -->
        <div class="demo-section">
            <h2>🔑 Role-Based Access Control Summary</h2>
            
            <div class="grid">
                <div class="card">
                    <h3>✅ Can Decrypt Medical Data</h3>
                    <span class="badge success">admin</span>
                    <span class="badge success">chief-staff</span>
                    <span class="badge success">doctor</span>
                    <span class="badge success">therapist</span>
                    <span class="badge success">nurse</span>
                </div>
                
                <div class="card">
                    <h3>🚫 Cannot Decrypt Medical Data</h3>
                    <span class="badge danger">receptionist</span>
                    <span class="badge danger">relative</span>
                    <span class="badge danger">general_user</span>
                    <span class="badge danger">staff</span>
                </div>
            </div>
        </div>

        <!-- Integration Checklist -->
        <div class="demo-section">
            <h2>✅ Integration Checklist</h2>
            
            <div class="scenario">
                <h3>Steps to integrate encryption into your existing code:</h3>
                <ol style="line-height: 2;">
                    <li>✅ <strong>Include the encryption modules</strong> at the top of your PHP files:
                        <div class="code-block" style="margin: 10px 0;">
<span class="keyword">require_once</span> <span class="string">'simple_rsa_crypto.php'</span>;
<span class="keyword">require_once</span> <span class="string">'security_decrypt.php'</span>;
                        </div>
                    </li>
                    <li>✅ <strong>When inserting patient data:</strong> Use <code>encrypt_patient_data()</code> before INSERT queries</li>
                    <li>✅ <strong>When retrieving patient data:</strong> Use <code>decrypt_patient_medical_data()</code> after SELECT queries</li>
                    <li>✅ <strong>When listing patients:</strong> Use <code>batch_decrypt_records()</code> for multiple records</li>
                    <li>✅ <strong>For treatment notes:</strong> Use <code>decrypt_treatment_data()</code></li>
                    <li>✅ <strong>For health logs:</strong> Use <code>decrypt_health_log_data()</code></li>
                    <li>✅ <strong>Always pass user role:</strong> Ensure <code>$_SESSION['role']</code> is available</li>
                </ol>
            </div>
        </div>

        <!-- Quick Reference -->
        <div class="demo-section">
            <h2>📚 Quick Function Reference</h2>
            
            <table style="width: 100%; border-collapse: collapse;">
                <thead style="background: #667eea; color: white;">
                    <tr>
                        <th style="padding: 12px; text-align: left;">Function</th>
                        <th style="padding: 12px; text-align: left;">Purpose</th>
                        <th style="padding: 12px; text-align: left;">Use When</th>
                    </tr>
                </thead>
                <tbody>
                    <tr style="border-bottom: 1px solid #ddd;">
                        <td style="padding: 10px;"><code>rsa_encrypt($data)</code></td>
                        <td style="padding: 10px;">Encrypt any string</td>
                        <td style="padding: 10px;">Manual encryption of individual fields</td>
                    </tr>
                    <tr style="border-bottom: 1px solid #ddd; background: #f9f9f9;">
                        <td style="padding: 10px;"><code>rsa_decrypt($data)</code></td>
                        <td style="padding: 10px;">Decrypt any string</td>
                        <td style="padding: 10px;">Manual decryption of individual fields</td>
                    </tr>
                    <tr style="border-bottom: 1px solid #ddd;">
                        <td style="padding: 10px;"><code>encrypt_patient_data($patient)</code></td>
                        <td style="padding: 10px;">Encrypt patient medical fields</td>
                        <td style="padding: 10px;">Before inserting/updating patient records</td>
                    </tr>
                    <tr style="border-bottom: 1px solid #ddd; background: #f9f9f9;">
                        <td style="padding: 10px;"><code>decrypt_patient_data($patient, $role)</code></td>
                        <td style="padding: 10px;">Decrypt patient data with auth</td>
                        <td style="padding: 10px;">Quick decrypt with role check</td>
                    </tr>
                    <tr style="border-bottom: 1px solid #ddd;">
                        <td style="padding: 10px;"><code>decrypt_patient_medical_data($patient, $user)</code></td>
                        <td style="padding: 10px;">Decrypt with full user context</td>
                        <td style="padding: 10px;">When displaying patient records</td>
                    </tr>
                    <tr style="border-bottom: 1px solid #ddd; background: #f9f9f9;">
                        <td style="padding: 10px;"><code>batch_decrypt_records($records, $user, $type)</code></td>
                        <td style="padding: 10px;">Decrypt multiple records</td>
                        <td style="padding: 10px;">Patient lists, reports, exports</td>
                    </tr>
                    <tr style="border-bottom: 1px solid #ddd;">
                        <td style="padding: 10px;"><code>can_decrypt($role)</code></td>
                        <td style="padding: 10px;">Check if role can decrypt</td>
                        <td style="padding: 10px;">Before showing decrypt options in UI</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
