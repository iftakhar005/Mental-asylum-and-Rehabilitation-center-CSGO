<?php
session_start();
require_once 'db.php';

// Check login and role
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || !in_array($_SESSION['role'], ['doctor', 'therapist'])) {
    header('Location: index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Get staff_id for this user
$staff_id = null;
$stmt = $conn->prepare("SELECT staff_id FROM staff WHERE user_id = ? AND role = ?");
$stmt->bind_param("is", $user_id, $role);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    $staff_id = $row['staff_id'];
}
$stmt->close();
if (!$staff_id) {
    die('Staff record not found.');
}

// Get assigned patients (active only)
$patients = [];
if ($role === 'doctor' || $role === 'therapist') {
    $sql = "SELECT p.id, p.patient_id, u.username, u.first_name, u.last_name
            FROM patients p
            JOIN users u ON p.user_id = u.id
            JOIN staff_patient_assignments spa ON p.id = spa.patient_id
            WHERE spa.staff_id = ? AND p.status = 'active' ORDER BY u.first_name, u.last_name";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $patients[] = $row;
    }
    $stmt->close();
}

// Handle patient selection
$selected_patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : null;
$vitals = [];
if ($selected_patient_id) {
    $sql = "SELECT h.*, u.username FROM health_logs h JOIN patients p ON h.patient_id = p.id JOIN users u ON p.user_id = u.id WHERE h.log_type = 'vitals' AND h.patient_id = ? ORDER BY h.log_time DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $selected_patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $details = json_decode($row['details'], true);
        $vitals[] = [
            'username' => $row['username'],
            'log_time' => $row['log_time'],
            'temperature' => $details['temperature'] ?? '',
            'pulse' => $details['pulse'] ?? '',
            'rr' => $details['rr'] ?? '',
            'bp' => $details['bp'] ?? '',
            'mood' => $details['mood'] ?? ''
        ];
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Vitals</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f7f8fa; }
        .container { max-width: 900px; margin-top: 40px; }
        .vitals-table th, .vitals-table td { vertical-align: middle; }
        .search-select { min-width: 300px; }
    </style>
</head>
<body>
<div class="container">
    <h2 class="mb-4"><i class="fas fa-heartbeat"></i> Patient Vitals</h2>
    <form method="get" class="mb-4">
        <div class="row g-2 align-items-center">
            <div class="col-auto">
                <label for="patient_id" class="col-form-label">Select Patient:</label>
            </div>
            <div class="col-auto flex-grow-1">
                <select class="form-select search-select" id="patient_id" name="patient_id" required onchange="this.form.submit()">
                    <option value="">-- Choose Patient --</option>
                    <?php foreach ($patients as $p): ?>
                        <option value="<?php echo $p['id']; ?>" <?php if ($selected_patient_id == $p['id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars(trim(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? ''))); ?> (<?php echo htmlspecialchars($p['username']); ?> | ID: <?php echo $p['patient_id']; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </form>
    <?php if ($selected_patient_id && !empty($vitals)): ?>
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <i class="fas fa-notes-medical"></i> Vitals Records
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover vitals-table mb-0">
                        <thead>
                            <tr>
                                <th>Date/Time</th>
                                <th>Temperature (°C)</th>
                                <th>Pulse</th>
                                <th>Resp. Rate</th>
                                <th>Blood Pressure</th>
                                <th>Mood</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($vitals as $v): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($v['log_time']); ?></td>
                                <td><?php echo htmlspecialchars($v['temperature']); ?></td>
                                <td><?php echo htmlspecialchars($v['pulse']); ?></td>
                                <td><?php echo htmlspecialchars($v['rr']); ?></td>
                                <td><?php echo htmlspecialchars($v['bp']); ?></td>
                                <td><?php echo htmlspecialchars(ucfirst($v['mood'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php elseif ($selected_patient_id): ?>
        <div class="alert alert-warning">No vitals records found for this patient.</div>
    <?php else: ?>
        <div class="alert alert-info">Please select a patient to view vitals information.</div>
    <?php endif; ?>
    <a href="<?php echo ($role === 'doctor') ? 'doctor_dashboard.php' : 'therapist_dashboard.php'; ?>" class="btn btn-secondary mt-3"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 