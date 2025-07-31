<?php
require_once 'session_check.php';
check_login(['admin', 'chief-staff']);
require_once 'db.php';

if (!isset($_GET['staff_id'])) {
    header('Location: manage_staff.php');
    exit();
}
$staff_id = $_GET['staff_id'];

// Fetch staff details
$stmt = $conn->prepare("SELECT staff_id, full_name, role, email, phone, emergency_contact FROM staff WHERE staff_id = ?");
$stmt->bind_param("s", $staff_id);
$stmt->execute();
$result = $stmt->get_result();
$staff = $result->fetch_assoc();
$stmt->close();

if (!$staff) {
    echo "<p>Staff member not found.</p>";
    exit();
}

$role_from_db = $staff['role'];

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = $_POST['full_name'];
    $phone = $_POST['phone'];
    $emergency_contact = $_POST['emergency_contact'];
    $email = $_POST['email'];
    $role = $_POST['role'];
    $stmt = $conn->prepare("UPDATE staff SET full_name=?, phone=?, emergency_contact=?, role=?, email=? WHERE staff_id=?");
    $stmt->bind_param("ssssss", $full_name, $phone, $emergency_contact, $role, $email, $staff_id);
    if ($stmt->execute()) {
        header('Location: manage_staff.php');
        exit();
    } else {
        $error = "Failed to update staff: " . $conn->error;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Staff Member</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { background: #f5f7fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .container { max-width: 500px; margin: 40px auto; background: #fff; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); padding: 30px; }
        h2 { text-align: center; color: #3498db; margin-bottom: 30px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 500; }
        input, select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 16px; }
        .btn { background: #3498db; color: #fff; border: none; padding: 12px 24px; border-radius: 6px; font-size: 16px; cursor: pointer; width: 100%; }
        .btn:hover { background: #2980b9; }
        .form-actions { display: flex; gap: 10px; }
        .error { color: #e74c3c; text-align: center; margin-bottom: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <h2><i class="fas fa-user-edit"></i> Edit Staff Member</h2>
        <?php if (!empty($error)) echo '<div class="error">' . htmlspecialchars($error) . '</div>'; ?>
        <form method="POST">
            <div class="form-group">
                <label for="full_name">Full Name</label>
                <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($staff['full_name']); ?>" required>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($staff['email']); ?>" required>
            </div>
            <div class="form-group">
                <label for="role">Role</label>
                <select id="role" name="role" required>
                    <option value="chief-staff" <?php if($role_from_db==='chief-staff') echo 'selected'; ?>>Chief Staff</option>
                    <option value="doctor" <?php if($role_from_db==='doctor') echo 'selected'; ?>>Doctor</option>
                    <option value="therapist" <?php if($role_from_db==='therapist') echo 'selected'; ?>>Therapist</option>
                    <option value="nurse" <?php if($role_from_db==='nurse') echo 'selected'; ?>>Nurse</option>
                    <option value="receptionist" <?php if($role_from_db==='receptionist') echo 'selected'; ?>>Receptionist</option>
                </select>
            </div>
            <div class="form-group">
                <label for="phone">Phone</label>
                <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($staff['phone']); ?>" required>
            </div>
            <div class="form-group">
                <label for="emergency_contact">Emergency Contact</label>
                <input type="text" id="emergency_contact" name="emergency_contact" value="<?php echo htmlspecialchars($staff['emergency_contact']); ?>" required>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn">Save Changes</button>
                <a href="manage_staff.php" class="btn" style="background:#e74c3c;">Cancel</a>
            </div>
        </form>
    </div>
</body>
</html> 