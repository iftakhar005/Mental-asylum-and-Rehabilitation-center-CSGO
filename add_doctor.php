<?php
require_once 'session_check.php';
check_login(['admin', 'chief-staff']);
require_once 'db.php';
$dashboard_link = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') ? 'admin_dashboard.php' : 'chief_staff_dashboard.php';

// Function to generate staff ID
function generateStaffId() {
    return 'STF-' . date('Ymd') . '-' . rand(1000, 9999);
}

// Function to generate email
function generateEmail($firstName, $lastName, $role) {
    global $conn;
    $role = strtolower($role);
    $firstName = strtolower($firstName);
    $lastName = strtolower($lastName);
    // 1. Try firstname@role.gmail.com
    $email = $firstName . '@' . $role . '.gmail.com';
    $stmt = $conn->prepare("SELECT COUNT(*) FROM staff WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    if ($count == 0) return $email;
    // 2. Try lastname@role.gmail.com
    $email = $lastName . '@' . $role . '.gmail.com';
    $stmt = $conn->prepare("SELECT COUNT(*) FROM staff WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    if ($count == 0) return $email;
    // 3. Try lastname123@role.gmail.com, etc.
    $counter = 123;
    while (true) {
        $email = $lastName . $counter . '@' . $role . '.gmail.com';
        $stmt = $conn->prepare("SELECT COUNT(*) FROM staff WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();
        if ($count == 0) return $email;
        $counter++;
    }
}

// Function to generate a secure password
function generateSecurePassword($length = 12) {
    $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $lowercase = 'abcdefghijklmnopqrstuvwxyz';
    $numbers = '0123456789';
    $special = '!@#$%^&*()_+-=[]{}|;:,.<>?';
    
    $allChars = $uppercase . $lowercase . $numbers . $special;
    $password = '';
    
    // Ensure at least one character from each category
    $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
    $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
    $password .= $numbers[random_int(0, strlen($numbers) - 1)];
    $password .= $special[random_int(0, strlen($special) - 1)];
    
    // Fill the rest with random characters
    for ($i = 4; $i < $length; $i++) {
        $password .= $allChars[random_int(0, strlen($allChars) - 1)];
    }
    
    // Shuffle the password to make it more random
    return str_shuffle($password);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = $_POST['full_name'];
    $dob = $_POST['dob'];
    $gender = $_POST['gender'];
    $address = $_POST['address'];
    $phone = $_POST['phone'];
    $email = $_POST['email']; // Use provided email instead of generating
    $experience = $_POST['experience'];
    $enable_2fa = isset($_POST['enable_2fa']) ? 1 : 0; // Capture 2FA checkbox
    
    // Split full name into first and last name
    $nameParts = explode(' ', $full_name);
    $firstName = $nameParts[0];
    $lastName = end($nameParts);
    
    // Generate credentials
    $staff_id = 'DOC-' . date('Ymd') . '-' . rand(1000, 9999);
    // $email = generateEmail($firstName, $lastName, 'doctor'); // Removed auto-generation
    $temp_password = generateSecurePassword(12);
    $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Check if email already exists in users table
        $check_email = $conn->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $check_email->bind_param("s", $email);
        $check_email->execute();
        $check_email->bind_result($email_count);
        $check_email->fetch();
        $check_email->close();
        
        if ($email_count > 0) {
            throw new Exception("Email already exists in the system. Please try again.");
        }
        
        // Check if staff_id already exists
        $check_staff = $conn->prepare("SELECT COUNT(*) FROM staff WHERE staff_id = ?");
        $check_staff->bind_param("s", $staff_id);
        $check_staff->execute();
        $check_staff->bind_result($staff_count);
        $check_staff->fetch();
        $check_staff->close();
        
        if ($staff_count > 0) {
            throw new Exception("Staff ID already exists. Please try again.");
        }
        
        // Insert into users table first (with email)
        $sql_user = "INSERT INTO users (username, password_hash, email, role, first_name, last_name, contact_number, status) VALUES ('$staff_id', '$hashed_password', '$email', 'doctor', '$full_name', '', '$phone', 'active')";
        
        if (!$conn->query($sql_user)) {
            throw new Exception("Error creating user account: " . $conn->error);
        }
        
        $user_id = $conn->insert_id;
        
        // Insert into staff table
        $sql = "INSERT INTO staff (staff_id, full_name, role, dob, gender, address, experience, phone, password_hash, status, email, user_id, temp_password, two_factor_enabled) VALUES ('$staff_id', '$full_name', 'doctor', '$dob', '$gender', '$address', '$experience', '$phone', '$hashed_password', 'Active', '$email', $user_id, '$temp_password', $enable_2fa)";
        
        if (!$conn->query($sql)) {
            throw new Exception("Error creating staff record: " . $conn->error);
        }
        
        // Commit transaction
        $conn->commit();
        
        $success = true;
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $message = "Error: " . $e->getMessage();
        $success = false;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Staff Member</title>
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2980b9;
            --accent-color: #e74c3c;
            --light-color: #ecf0f1;
            --dark-color: #2c3e50;
            --success-color: #2ecc71;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #f5f7fa; color: var(--dark-color); line-height: 1.6; }
        .container { max-width: 800px; margin: 40px auto; padding: 20px; }
        .card { background: white; border-radius: 10px; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1); overflow: hidden; }
        .card-header { background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); color: white; padding: 20px; text-align: center; }
        .card-header h2 { font-size: 24px; font-weight: 600; }
        .card-body { padding: 30px; }
        .form-group { margin-bottom: 25px; position: relative; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; color: var(--dark-color); }
        .form-control { width: 100%; padding: 12px 15px; border: 1px solid #ddd; border-radius: 6px; font-size: 16px; transition: all 0.3s; }
        .form-control:focus { border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2); outline: none; }
        .btn { display: inline-block; padding: 12px 24px; border: none; border-radius: 6px; font-size: 16px; font-weight: 500; cursor: pointer; transition: all 0.3s; }
        .btn-primary { background-color: var(--primary-color); color: white; }
        .btn-primary:hover { background-color: var(--secondary-color); transform: translateY(-2px); }
        .btn-outline { background-color: transparent; border: 1px solid var(--primary-color); color: var(--primary-color); }
        .btn-outline:hover { background-color: var(--primary-color); color: white; }
        .form-actions { display: flex; justify-content: space-between; margin-top: 30px; }
        .notification-badge { position: absolute; top: -10px; right: -10px; background-color: var(--accent-color); color: white; border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; font-size: 12px; }
        .sidebar { position: fixed; left: 0; top: 0; bottom: 0; width: 250px; background-color: var(--dark-color); color: white; padding: 20px; transform: translateX(-100%); transition: transform 0.3s ease; z-index: 1000; }
        .sidebar.active { transform: translateX(0); }
        .sidebar-menu { list-style: none; margin-top: 30px; }
        .sidebar-menu li { margin-bottom: 15px; }
        .sidebar-menu a { color: white; text-decoration: none; display: flex; align-items: center; padding: 10px; border-radius: 5px; transition: all 0.3s; }
        .sidebar-menu a:hover { background-color: rgba(255, 255, 255, 0.1); }
        .sidebar-menu a i { margin-right: 10px; }
        .menu-toggle { position: fixed; top: 20px; left: 20px; font-size: 24px; cursor: pointer; z-index: 1001; }
        @media (max-width: 768px) { .container { margin: 20px; } .form-actions { flex-direction: column; } .form-actions .btn { width: 100%; margin-bottom: 10px; } }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .fade-in { animation: fadeIn 0.5s ease forwards; }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="container fade-in">
        <?php if (isset($success) && $success): ?>
        <div id="credentialsModal" class="credentials-modal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:1000;">
            <div class="credentials-content" style="background:white; width:90%; max-width:400px; margin:auto; position:relative; top:50%; transform:translateY(-50%); padding:32px 24px; border-radius:16px; box-shadow:0 2px 16px rgba(67,97,238,0.12); text-align:center; border:2px solid #4361ee;">
                <button class="close-modal" onclick="closeCredentialsModal()" style="position:absolute;top:10px;right:16px;font-size:1.5rem;background:none;border:none;cursor:pointer;">&times;</button>
                <h4 style="color: #4361ee; margin-bottom: 18px; font-size: 1.2rem; font-weight: 700;">Generated Credentials</h4>
                <p style="margin-bottom: 12px; font-size: 1.05rem;">Staff ID: <span id="copyId"><?php echo htmlspecialchars($staff_id); ?></span>
                    <button class="copy-btn" onclick="copyToClipboard('copyId')" style="background: #4361ee; color: white; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer; margin-left: 10px;"><i class="fas fa-copy"></i></button>
                </p>
                <p style="margin-bottom: 12px; font-size: 1.05rem;">Email: <span id="copyEmail"><?php echo htmlspecialchars($email); ?></span>
                    <button class="copy-btn" onclick="copyToClipboard('copyEmail')" style="background: #4361ee; color: white; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer; margin-left: 10px;"><i class="fas fa-copy"></i></button>
                </p>
                <p style="margin-bottom: 12px; font-size: 1.05rem;">Password: <span id="copyPass"><?php echo htmlspecialchars($temp_password); ?></span>
                    <button class="copy-btn" onclick="copyToClipboard('copyPass')" style="background: #4361ee; color: white; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer; margin-left: 10px;"><i class="fas fa-copy"></i></button>
                </p>
                <p style="margin-top: 18px; color: #222; font-size: 1rem;"><b>Note:</b> Please save these credentials. The password cannot be recovered.</p>
            </div>
        </div>
        <?php endif; ?>
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-user-plus"></i> Add New Doctor</h2>
            </div>
            <div class="card-body">
                <form id="staffForm" method="POST">
                    <div class="form-group">
                        <label for="full_name">Full Name</label>
                        <input type="text" id="full_name" name="full_name" class="form-control" placeholder="Enter full name" required>
                    </div>
                    <div class="form-group">
                        <label for="dob">Date of Birth</label>
                        <input type="date" id="dob" name="dob" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="gender">Gender</label>
                        <select id="gender" name="gender" class="form-control" required>
                            <option value="">Select gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="address">Address</label>
                        <textarea id="address" name="address" class="form-control" placeholder="Enter address" required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone" class="form-control" placeholder="Enter phone number" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" class="form-control" placeholder="Enter email address" required>
                    </div>
                    <div class="form-group">
                        <label for="experience">Years of Experience</label>
                        <input type="text" id="experience" name="experience" class="form-control" placeholder="Enter years of experience">
                    </div>
                    
                    <!-- 2FA Checkbox -->
                    <div class="form-group" style="border: 2px solid #3498db; border-radius: 8px; padding: 16px; background: #ebf5fb;">
                        <label style="display: flex; align-items: center; gap: 12px; cursor: pointer; margin: 0;">
                            <input type="checkbox" name="enable_2fa" id="enable_2fa" value="1" style="width: 20px; height: 20px; cursor: pointer; accent-color: #3498db;">
                            <div>
                                <div style="font-weight: 600; color: #3498db; font-size: 15px; margin-bottom: 4px;">
                                    <i class="fas fa-shield-alt" style="margin-right: 8px;"></i>
                                    Enable Two-Factor Authentication (2FA)
                                </div>
                                <div style="font-size: 13px; color: #555; line-height: 1.4;">
                                    When enabled, user will receive an OTP code via email during login for enhanced security.
                                </div>
                            </div>
                        </label>
                    </div>
                    
                    <input type="hidden" name="position" value="Doctor">
                    <input type="hidden" name="department" value="Medical">
                    <div class="form-actions">
                        <button type="button" class="btn btn-outline" id="cancelBtn">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Staff</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script>
        // Form validation
        document.getElementById('staffForm').addEventListener('submit', function(e) {
            const email = document.getElementById('email').value;
            const phone = document.getElementById('phone').value;
            
            // Email validation
            if (!email.includes('@') || !email.includes('.')) {
                alert('Please enter a valid email address');
                e.preventDefault();
                return;
            }
            
            // Simple phone validation (at least 10 digits)
            const phoneDigits = phone.replace(/\D/g, '');
            if (phoneDigits.length < 10) {
                alert('Please enter a valid phone number (at least 10 digits)');
                e.preventDefault();
                return;
            }
            
            // If validation passes, show success message
            alert('Staff member added successfully!');
        });
        // Cancel button action
        document.getElementById('cancelBtn').addEventListener('click', function() {
            if (confirm('Are you sure you want to cancel? All unsaved changes will be lost.')) {
                window.location.href = 'dashboard.php';
            }
        });
        // Input masking for phone number
        document.getElementById('phone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 3 && value.length <= 6) {
                value = value.replace(/(\d{3})(\d{1,3})/, '($1) $2');
            } else if (value.length > 6) {
                value = value.replace(/(\d{3})(\d{3})(\d{1,4})/, '($1) $2-$3');
            }
            e.target.value = value;
        });
        function copyToClipboard(id) {
            var text = document.getElementById(id).innerText;
            navigator.clipboard.writeText(text);
        }
        function closeCredentialsModal() {
            document.getElementById('credentialsModal').style.display = 'none';
        }
        document.addEventListener('DOMContentLoaded', function() {
            var modal = document.getElementById('credentialsModal');
            if (modal) {
                modal.style.display = 'block';
            }
        });
    </script>
</body>
</html>