<?php
require_once 'session_check.php';
check_login(['admin']);
require_once 'db.php';

// Handle status update
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['appointment_id'], $_POST['status'])) {
    $apt_id = intval($_POST['appointment_id']);
    $status = $_POST['status'];
    if (in_array($status, ['scheduled', 'completed', 'cancelled'])) {
        $stmt = $conn->prepare("UPDATE appointments SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $apt_id);
        if ($stmt->execute()) {
            $message = '<div style="color:green;">Status updated successfully.</div>';
        } else {
            $message = '<div style=\"color:red;\">Failed to update status.</div>';
        }
        $stmt->close();
    }
}

// Fetch all appointments with patient, doctor, therapist, and room info
$query = "SELECT a.*, p.full_name as patient_name, p.room_number,
    (SELECT s.full_name FROM staff s WHERE s.staff_id = a.doctor) as doctor_name,
    (SELECT s2.full_name FROM staff s2 WHERE s2.staff_id = a.therapist) as therapist_name
    FROM appointments a
    JOIN patients p ON a.patient_id = p.patient_id
    ORDER BY a.date DESC, a.time DESC";
$result = $conn->query($query);
$appointments = [];
if ($result) {
    while($row = $result->fetch_assoc()) {
        $appointments[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>All Appointments - Admin</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f7f8fa; margin: 0; }
        .container { max-width: 1200px; margin: 40px auto; background: #fff; border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,0.08); padding: 2rem; }
        h2 { color: #4f46e5; margin-bottom: 1.5rem; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { padding: 0.75rem 1rem; text-align: left; }
        th { background: #f3f4f6; color: #374151; font-weight: 600; }
        tr:nth-child(even) { background: #f9fafb; }
        tr:hover { background: #eef2ff; }
        .status-scheduled { color: #2563eb; font-weight: 500; }
        .status-completed { color: #059669; font-weight: 500; }
        .status-cancelled { color: #dc2626; font-weight: 500; }
        .back-link { display: inline-block; margin-bottom: 1rem; color: #4f46e5; text-decoration: none; font-weight: 500; }
        .back-link:hover { text-decoration: underline; }
        .status-form { display: flex; align-items: center; gap: 0.5rem; }
        select { padding: 0.3rem 0.5rem; border-radius: 6px; border: 1px solid #ccc; }
        button { padding: 0.3rem 0.8rem; border-radius: 6px; border: none; background: #4f46e5; color: #fff; cursor: pointer; font-weight: 500; }
        button:hover { background: #3730a3; }
    </style>
</head>
<body>
    <div class="container">
        <a href="admin_dashboard.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        <h2>All Appointments</h2>
        <?php echo $message; ?>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Patient</th>
                    <th>Type</th>
                    <th>Doctor</th>
                    <th>Therapist</th>
                    <th>Room</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($appointments as $apt): ?>
                <tr>
                    <td><?php echo htmlspecialchars($apt['date']); ?></td>
                    <td><?php echo htmlspecialchars(date('H:i', strtotime($apt['time']))); ?></td>
                    <td><?php echo htmlspecialchars($apt['patient_name']); ?></td>
                    <td><?php echo htmlspecialchars($apt['type']); ?></td>
                    <td><?php echo htmlspecialchars($apt['doctor_name'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($apt['therapist_name'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($apt['room_number'] ?? ''); ?></td>
                    <td>
                        <form method="post" class="status-form" style="margin:0;">
                            <input type="hidden" name="appointment_id" value="<?php echo $apt['id']; ?>">
                            <select name="status">
                                <option value="scheduled" <?php if($apt['status']==='scheduled') echo 'selected'; ?>>Scheduled</option>
                                <option value="completed" <?php if($apt['status']==='completed') echo 'selected'; ?>>Completed</option>
                                <option value="cancelled" <?php if($apt['status']==='cancelled') echo 'selected'; ?>>Cancelled</option>
                            </select>
                            <button type="submit">Update</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($appointments)): ?>
                <tr><td colspan="8" style="text-align:center; color:#888;">No appointments found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html> 