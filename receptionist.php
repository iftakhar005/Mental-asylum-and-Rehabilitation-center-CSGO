<?php
require_once 'session_check.php';
check_login(['admin']);
require_once 'db.php';

$message = '';

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
    $full_name = $conn->real_escape_string($_POST['full_name']);
    $dob = $conn->real_escape_string($_POST['dob']);
    $gender = $conn->real_escape_string($_POST['gender']);
    $address = $conn->real_escape_string($_POST['address']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $experience = $conn->real_escape_string($_POST['experience']);
    // Split full name into first and last name
    $nameParts = explode(' ', $full_name);
    $firstName = $nameParts[0];
    $lastName = end($nameParts);
    // Generate credentials
    $staff_id = 'REC-' . date('Ymd') . '-' . rand(1000, 9999);
    $email = generateEmail($firstName, $lastName, 'receptionist');
    $temp_password = generateSecurePassword(12);
    $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);
    $success = false;
    // Insert into users table first
    $sql_user = "INSERT INTO users (username, password_hash, email, role, first_name, last_name, contact_number, status) VALUES ('$staff_id', '$hashed_password', '$email', 'receptionist', '$firstName', '$lastName', '$phone', 'active')";
    if ($conn->query($sql_user)) {
        $user_id = $conn->insert_id;
        // Now insert into staff table using $user_id
        $sql = "INSERT INTO staff (staff_id, full_name, role, dob, gender, address, experience, phone, password_hash, status, email, user_id, temp_password) VALUES ('$staff_id', '$full_name', 'receptionist', '$dob', '$gender', '$address', '$experience', '$phone', '$hashed_password', 'Active', '$email', $user_id, '$temp_password')";
        if ($conn->query($sql)) {
            $success = true;
        } else {
            $message = 'Error: ' . $conn->error;
        }
    } else {
        $message = 'Error: ' . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Receptionist - MindCare</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #667eea;
            --secondary: #764ba2;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --bg: #f7f8fa;
            --bg-card: #fff;
            --text: #23263a;
            --text-muted: #7b809a;
            --border: #e0e6ed;
            --input-bg: #f7f8fa;
            --input-border: #e0e6ed;
        }
        body {
            background: var(--bg);
            color: var(--text);
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
        }
        .container {
            max-width: 500px;
            margin: 40px auto;
            padding: 24px;
        }
        .card {
            background: var(--bg-card);
            border-radius: 18px;
            box-shadow: 0 4px 32px rgba(67,97,238,0.07);
            padding: 32px 28px 28px 28px;
        }
        .page-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 0.5rem;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text);
        }
        .form-control {
            width: 100%;
            padding: 0.85rem 1rem;
            border: 1.5px solid var(--input-border);
            border-radius: 10px;
            background: var(--input-bg);
            color: var(--text);
            font-size: 1rem;
            transition: border-color 0.18s;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(102,126,234,0.10);
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 0.85rem 2rem;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: #fff;
            transition: background 0.2s, transform 0.2s;
        }
        .btn:hover {
            background: linear-gradient(135deg, var(--secondary), var(--primary));
            transform: translateY(-2px);
        }
        .alert {
            padding: 1rem 1.2rem;
            border-radius: 10px;
            margin-bottom: 1.2rem;
            font-weight: 500;
        }
        .alert-success {
            background: #eafaf1;
            color: var(--success);
            border: 1.5px solid #10b98144;
        }
        .alert-danger {
            background: #fff0f0;
            color: var(--danger);
            border: 1.5px solid #ef444444;
        }
        /* Modal */
        .credentials-modal {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(24,26,32,0.15);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .credentials-modal.active {
            display: flex;
        }
        .credentials-content {
            background: var(--bg-card);
            border-radius: 18px;
            box-shadow: 0 8px 32px rgba(67,97,238,0.13);
            padding: 36px 32px 28px 32px;
            min-width: 320px;
            max-width: 95vw;
            text-align: center;
            border: 2px solid var(--primary);
        }
        .close-modal {
            position: absolute;
            top: 18px;
            right: 28px;
            font-size: 2rem;
            color: var(--text-muted);
            background: none;
            border: none;
            cursor: pointer;
        }
        .credentials-content h4 {
            color: var(--primary);
            margin-bottom: 18px;
            font-size: 1.2rem;
            font-weight: 700;
        }
        .credentials-content p {
            margin-bottom: 12px;
            font-size: 1.05rem;
        }
        .copy-btn {
            background: var(--primary);
            color: #fff;
            border: none;
            padding: 5px 10px;
            border-radius: 5px;
            cursor: pointer;
            margin-left: 10px;
        }
        .copy-btn:hover {
            background: var(--secondary);
        }
        @media (max-width: 600px) {
            .container { padding: 8px; }
            .card { padding: 18px 6px; }
            .credentials-content { padding: 18px 6px; }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="container">
        <div class="page-header">
            <h1 class="page-title">Add New Receptionist</h1>
        </div>

        <?php if (isset($success) && $success): ?>
        <div id="credentialsModal" class="credentials-modal active">
            <div class="credentials-content" style="position:relative;">
                <button class="close-modal" onclick="closeCredentialsModal()">&times;</button>
                <h4>Generated Credentials</h4>
                <p>Staff ID: <span id="copyId"><?php echo htmlspecialchars($staff_id); ?></span>
                    <button class="copy-btn" onclick="copyToClipboard('copyId')"><i class="fas fa-copy"></i></button>
                </p>
                <p>Email: <span id="copyEmail"><?php echo htmlspecialchars($email); ?></span>
                    <button class="copy-btn" onclick="copyToClipboard('copyEmail')"><i class="fas fa-copy"></i></button>
                </p>
                <p>Password: <span id="copyPass"><?php echo htmlspecialchars($temp_password); ?></span>
                    <button class="copy-btn" onclick="copyToClipboard('copyPass')"><i class="fas fa-copy"></i></button>
                </p>
                <p style="margin-top: 18px; color: var(--text-muted); font-size: 1rem;"><b>Note:</b> Please save these credentials. The password cannot be recovered.</p>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($message): ?>
            <div class="alert <?php echo strpos($message, 'Error') !== false ? 'alert-danger' : 'alert-success'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label" for="full_name">Full Name</label>
                    <input type="text" class="form-control" id="full_name" name="full_name" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="dob">Date of Birth</label>
                    <input type="date" class="form-control" id="dob" name="dob" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="gender">Gender</label>
                    <select class="form-control" id="gender" name="gender" required>
                        <option value="">Select Gender</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="address">Address</label>
                    <textarea class="form-control" id="address" name="address" rows="3" required></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label" for="phone">Phone Number</label>
                    <input type="tel" class="form-control" id="phone" name="phone" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="experience">Years of Experience</label>
                    <input type="number" class="form-control" id="experience" name="experience" min="0" required>
                </div>

                <button type="submit" class="btn btn-primary">Add Receptionist</button>
            </form>
        </div>
    </div>

    <script>
    function copyToClipboard(id) {
      var text = document.getElementById(id).innerText;
      navigator.clipboard.writeText(text);
    }
    function closeCredentialsModal() {
        document.getElementById('credentialsModal').classList.remove('active');
    }
    <?php if (isset($success) && $success): ?>
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('credentialsModal').classList.add('active');
    });
    <?php endif; ?>
    </script>
</body>
</html> 