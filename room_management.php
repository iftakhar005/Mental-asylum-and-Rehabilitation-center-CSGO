<?php
require_once 'session_check.php';
check_login(['admin', 'chief-staff']);
require_once 'db.php';

// Add after require_once 'db.php';
$dashboard_link = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') ? 'admin_dashboard.php' : 'chief_staff_dashboard.php';

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add Room
    if (isset($_POST['add_room'])) {
        $room_number = $conn->real_escape_string($_POST['room_number']);
        $status = $conn->real_escape_string($_POST['status']);
        $type = $conn->real_escape_string($_POST['type']);
        $capacity = intval($_POST['capacity']);
        $for_whom = $conn->real_escape_string($_POST['for']);
        // Check for duplicate room number
        $check = $conn->query("SELECT room_number FROM rooms WHERE room_number = '$room_number'");
        if ($check && $check->num_rows > 0) {
            echo "<script>alert('Room number already exists!'); window.history.back();</script>";
            exit;
        }
        $conn->query("INSERT INTO rooms (room_number, status, type, capacity, for_whom) VALUES ('$room_number', '$status', '$type', $capacity, '$for_whom')");
    }
    // Edit Room
    if (isset($_POST['edit_room'])) {
        $room_number = $conn->real_escape_string($_POST['edit_room_number']);
        $status = $conn->real_escape_string($_POST['edit_status']);
        $type = $conn->real_escape_string($_POST['edit_type']);
        $capacity = intval($_POST['edit_capacity']);
        $for_whom = $conn->real_escape_string($_POST['edit_for']);
        $conn->query("UPDATE rooms SET status='$status', type='$type', capacity=$capacity, for_whom='$for_whom' WHERE room_number='$room_number'");
    }
    // Delete Room
    if (isset($_POST['delete_room'])) {
        $room_number = $conn->real_escape_string($_POST['delete_room_number']);
        $conn->query("DELETE FROM rooms WHERE room_number='$room_number'");
    }
    
    // Handle room status update from patient management
    if (isset($_POST['action']) && $_POST['action'] === 'update_status') {
        $response = ['success' => false, 'message' => ''];
        
        if (empty($_POST['room_number']) || empty($_POST['status'])) {
            $response['message'] = 'Room number and status are required';
        } else {
            $room_number = $conn->real_escape_string($_POST['room_number']);
            $status = $conn->real_escape_string($_POST['status']);
            
            // Validate status
            $valid_statuses = ['available', 'occupied', 'maintenance'];
            if (!in_array($status, $valid_statuses)) {
                $response['message'] = 'Invalid status';
            } else {
                // Check if room exists
                $result = $conn->query("SELECT * FROM rooms WHERE room_number = '$room_number'");
                if ($result && $result->num_rows > 0) {
                    // Update room status
                    if ($conn->query("UPDATE rooms SET status = '$status' WHERE room_number = '$room_number'")) {
                        $response['success'] = true;
                        $response['message'] = 'Room status updated successfully';
                    } else {
                        $response['message'] = 'Error updating room status: ' . $conn->error;
                    }
                } else {
                    $response['message'] = 'Room not found';
                }
            }
        }
        
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
}

// Fetch all rooms
$rooms_result = $conn->query("SELECT * FROM rooms ORDER BY room_number ASC");
$rooms = $rooms_result ? $rooms_result->fetch_all(MYSQLI_ASSOC) : [];

// Generate next available room number (e.g., 1a, 1b, 1c, 2a, ...)
$existing_numbers = array_map(function($room) { return strtolower($room['room_number']); }, $rooms);
$letters = range('A', 'H');
$next_room_number = '';
for ($floor = 1; $floor <= 99; $floor++) {
    foreach ($letters as $letter) {
        $candidate = $floor . $letter;
        if (!in_array(strtolower($candidate), $existing_numbers)) {
            $next_room_number = $candidate;
            break 2;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Room Management - United Medical Asylum & Rehab Facility</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            /* Gray Professional Theme */
            --bg-primary: #e5e7eb;
            --bg-secondary: #d1d5db;
            --bg-tertiary: #9ca3af;
            --bg-card: #f3f4f6;
            --bg-hover: #e5e7eb;
            --bg-accent: #6b7280;
            
            /* Solid Professional Colors */
            --accent-blue: #3b82f6;
            --accent-blue-light: #60a5fa;
            --accent-orange: #f97316;
            --accent-orange-light: #fb923c;
            --accent-purple: #8b5cf6;
            --accent-purple-light: #a78bfa;
            --accent-green: #10b981;
            --accent-green-light: #34d399;
            --accent-red: #ef4444;
            --accent-red-light: #f87171;
            --accent-teal: #14b8a6;
            --accent-teal-light: #2dd4bf;
            
            /* Gray Text Colors */
            --text-primary: #1f2937;
            --text-secondary: #4b5563;
            --text-muted: #6b7280;
            --text-light: #9ca3af;
            
            /* Gray Border Colors */
            --border-primary: #d1d5db;
            --border-secondary: #9ca3af;
            --border-accent: #6b7280;
            
            /* Layout */
            --sidebar-width: 280px;
            --topbar-height: 80px;
            --border-radius: 12px;
            --border-radius-lg: 16px;
            --box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
            --box-shadow-lg: 0 8px 32px rgba(0, 0, 0, 0.15);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            overflow-x: hidden;
            min-height: 100vh;
            padding-top: var(--topbar-height);
        }

        /* Custom Icons */
        .custom-icon {
            display: inline-block;
            width: 1em;
            height: 1em;
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
        }

        .icon-bed {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='currentColor'%3E%3Cpath d='M22 12V20H20V17H4V20H2V12C2 10.9 2.9 10 4 10H9C9 8.34 10.34 7 12 7S15 8.34 15 7H20C21.1 10 22 10.9 22 12ZM7 13C6.45 13 6 12.55 6 12S6.45 11 7 11 8 11.45 8 12 7.55 13 7 13ZM17 13C16.45 13 16 12.55 16 12S16.45 11 17 11 18 11.45 18 12 17.55 13 17 13Z'/%3E%3C/svg%3E");
        }

        /* Topbar */
        .topbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: var(--topbar-height);
            background: var(--bg-card);
            border-bottom: 1px solid var(--border-primary);
            display: flex;
            align-items: center;
            padding: 0 30px;
            z-index: 1000;
            box-shadow: var(--box-shadow);
        }

        .logo {
            display: flex;
            align-items: center;
            margin-right: 40px;
        }

        .logo-icon {
            width: 50px;
            height: 50px;
            background: var(--accent-blue);
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
        }

        .logo-icon .custom-icon {
            color: white;
            font-size: 1.5rem;
        }

        .logo-text h1 {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .logo-text p {
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin-top: -2px;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 30px;
            margin-left: auto;
        }

        .nav-link {
            color: var(--text-secondary);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
            padding: 8px 16px;
            border-radius: 8px;
        }

        .nav-link:hover, .nav-link.active {
            color: var(--accent-blue);
            background: rgba(59, 130, 246, 0.1);
        }

        /* Main Content */
        .main-content {
            padding: 30px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 30px;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--accent-blue);
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: white;
            color: var(--accent-blue);
            border: 2px solid var(--accent-blue);
            border-radius: 10px;
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }

        .back-btn:hover {
            background: var(--accent-blue);
            color: white;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
        }

        .btn-primary {
            background: var(--accent-blue);
            color: white;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
        }

        .btn-primary:hover {
            background: var(--accent-blue-light);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
        }

        .btn-outline {
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border-primary);
        }

        .btn-outline:hover {
            background: var(--bg-hover);
            border-color: var(--accent-blue);
            color: var(--accent-blue);
        }

        .btn-danger {
            background: var(--accent-red);
            color: white;
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
        }

        .btn-danger:hover {
            background: var(--accent-red-light);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(239, 68, 68, 0.4);
        }

        .btn-sm {
            padding: 8px 12px;
            font-size: 0.8rem;
        }

        .btn-icon {
            padding: 8px;
            width: 36px;
            height: 36px;
            justify-content: center;
        }

        /* Content Card */
        .content-card {
            background: white;
            border: 1px solid var(--border-primary);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--box-shadow);
            overflow: hidden;
        }

        /* Table Styles */
        .table-container {
            overflow-x: auto;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }

        .data-table th {
            text-align: left;
            padding: 20px 24px;
            font-weight: 600;
            color: var(--accent-blue);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid var(--border-primary);
            background: var(--bg-card);
        }

        .data-table td {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border-primary);
            color: var(--text-primary);
            vertical-align: middle;
        }

        .data-table tr:hover td {
            background: rgba(59, 130, 246, 0.04);
        }

        .data-table tr:last-child td {
            border-bottom: none;
        }

        /* Status Badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: lowercase;
            letter-spacing: 0.5px;
            min-width: 80px;
            justify-content: center;
        }

        .status-badge.available {
            background: rgba(16, 185, 129, 0.1);
            color: var(--accent-green);
        }

        .status-badge.occupied {
            background: rgba(239, 68, 68, 0.1);
            color: var(--accent-red);
        }

        .status-badge.maintenance {
            background: rgba(59, 130, 246, 0.1);
            color: var(--accent-blue);
        }

        /* Type Badges */
        .type-badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: capitalize;
            letter-spacing: 0.5px;
            min-width: 70px;
            justify-content: center;
        }

        .type-badge.normal {
            background: rgba(59, 130, 246, 0.1);
            color: var(--accent-blue);
        }

        .type-badge.deluxe {
            background: rgba(249, 115, 22, 0.1);
            color: var(--accent-orange);
        }

        /* For Badges */
        .for-badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            min-width: 80px;
            justify-content: center;
        }

        .for-badge.patients {
            background: rgba(139, 92, 246, 0.1);
            color: var(--accent-purple);
        }

        .for-badge.doctor {
            background: rgba(20, 184, 166, 0.1);
            color: var(--accent-teal);
        }

        .for-badge.therapy {
            background: rgba(249, 115, 22, 0.1);
            color: var(--accent-orange);
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: 1px solid var(--border-primary);
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
            color: var(--text-muted);
        }

        .action-btn:hover {
            border-color: var(--accent-blue);
            color: var(--accent-blue);
            transform: translateY(-1px);
        }

        .action-btn.delete:hover {
            border-color: var(--accent-red);
            color: var(--accent-red);
            background: rgba(239, 68, 68, 0.05);
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .modal.active {
            display: flex;
            opacity: 1;
        }

        .modal-content {
            background: white;
            border: 1px solid var(--border-primary);
            border-radius: var(--border-radius-lg);
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--box-shadow-lg);
            transform: translateY(20px);
            transition: transform 0.3s ease;
        }

        .modal.active .modal-content {
            transform: translateY(0);
        }

        .modal-header {
            padding: 25px 30px;
            border-bottom: 1px solid var(--border-primary);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--bg-card);
        }

        .modal-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--accent-blue);
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--text-secondary);
            cursor: pointer;
            padding: 5px;
            border-radius: 5px;
            transition: var(--transition);
        }

        .close-modal:hover {
            background: var(--bg-secondary);
            color: var(--text-primary);
        }

        .modal-body {
            padding: 30px;
        }

        .modal-footer {
            padding: 20px 30px;
            border-top: 1px solid var(--border-primary);
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            background: var(--bg-card);
        }

        /* Form Elements */
        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-primary);
            font-size: 0.9rem;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--border-primary);
            border-radius: 10px;
            font-size: 0.95rem;
            transition: var(--transition);
            outline: none;
            background: white;
            color: var(--text-primary);
            box-sizing: border-box;
        }

        .form-control::placeholder {
            color: var(--text-muted);
        }

        .form-control:focus {
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .main-content {
                padding: 20px;
            }

            .page-header {
                flex-direction: column;
                gap: 20px;
                align-items: flex-start;
            }

            .header-actions {
                width: 100%;
                justify-content: space-between;
            }

            .data-table th,
            .data-table td {
                padding: 12px 16px;
            }

            .action-buttons {
                flex-direction: column;
            }
        }

        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .content-card {
            animation: fadeInUp 0.6s ease forwards;
        }
    </style>
</head>
<body>
    <!-- Topbar -->
    <div class="topbar">
        <div class="logo">
            <div class="logo-icon">
                <span class="custom-icon icon-bed"></span>
            </div>
            <div class="logo-text">
                <h1>United Medical Asylum & Rehab Facility</h1>
                <p>Room Management</p>
            </div>
        </div>

        <div class="nav-links">
            <a href="<?php echo $dashboard_link; ?>" class="nav-link">Dashboard</a>
            <a href="#" class="nav-link active">Room Management</a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">
                <span class="custom-icon icon-bed"></span>
                Room Management
            </h1>
            <div class="header-actions">
                <button class="btn btn-primary" id="addRoomBtn">
                    <i class="fas fa-plus"></i>
                    Add Room
                </button>
            </div>
        </div>

        <!-- Rooms Table -->
        <div class="content-card">
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Room Number</th>
                            <th>Status</th>
                            <th>Type</th>
                            <th>Capacity</th>
                            <th>For</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rooms as $room): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($room['room_number']); ?></strong>
                            </td>
                            <td>
                                <span class="status-badge <?php echo strtolower($room['status']); ?>">
                                    <?php echo htmlspecialchars($room['status']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="type-badge <?php echo strtolower($room['type']); ?>">
                                    <?php echo htmlspecialchars($room['type']); ?>
                                </span>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($room['capacity']); ?></strong>
                            </td>
                            <td>
                                <span class="for-badge <?php echo strtolower(str_replace(' ', '-', str_replace('Chamber for ', '', $room['for_whom']))); ?>">
                                    <?php echo htmlspecialchars($room['for_whom']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="action-btn btn-edit-room" data-room='<?php echo json_encode($room); ?>' title="Edit Room">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="action-btn delete btn-delete-room" data-room-number="<?php echo htmlspecialchars($room['room_number']); ?>" title="Delete Room">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add Room Modal -->
    <div class="modal" id="addRoomModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Add New Room</h3>
                <button class="close-modal">&times;</button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label" for="room_number">Room Number</label>
                        <input type="text" class="form-control" id="room_number" name="room_number" value="<?php echo htmlspecialchars($next_room_number); ?>" readonly required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="status">Status</label>
                        <select class="form-control" id="status" name="status" required>
                            <option value="available">Available</option>
                            <option value="occupied">Occupied</option>
                            <option value="maintenance">Maintenance</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="type">Type</label>
                        <select class="form-control" id="type" name="type" required>
                            <option value="Normal">Normal</option>
                            <option value="Deluxe">Deluxe</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="capacity">Capacity</label>
                        <input type="number" class="form-control" id="capacity" name="capacity" min="1" value="1" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="for">For</label>
                        <select class="form-control" id="for" name="for" required>
                            <option value="Patients">Patients</option>
                            <option value="Chamber for Doctor">Chamber for Doctor</option>
                            <option value="Therapy">Therapy</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline close-modal">Cancel</button>
                    <button type="submit" name="add_room" class="btn btn-primary">Add Room</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Room Modal -->
    <div class="modal" id="editRoomModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Edit Room</h3>
                <button class="close-modal">&times;</button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label" for="edit_room_number">Room Number</label>
                        <input type="text" class="form-control" id="edit_room_number" name="edit_room_number" readonly required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit_status">Status</label>
                        <select class="form-control" id="edit_status" name="edit_status" required>
                            <option value="available">Available</option>
                            <option value="occupied">Occupied</option>
                            <option value="maintenance">Maintenance</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit_type">Type</label>
                        <select class="form-control" id="edit_type" name="edit_type" required>
                            <option value="Normal">Normal</option>
                            <option value="Deluxe">Deluxe</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit_capacity">Capacity</label>
                        <input type="number" class="form-control" id="edit_capacity" name="edit_capacity" min="1" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit_for">For</label>
                        <select class="form-control" id="edit_for" name="edit_for" required>
                            <option value="Patients">Patients</option>
                            <option value="Chamber for Doctor">Chamber for Doctor</option>
                            <option value="Therapy">Therapy</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline close-modal">Cancel</button>
                    <button type="submit" name="edit_room" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Room Modal -->
    <div class="modal" id="deleteRoomModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Delete Room</h3>
                <button class="close-modal">&times;</button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <p>Are you sure you want to delete room <strong><span id="deleteRoomNumberDisplay"></span></strong>?</p>
                    <input type="hidden" id="delete_room_number" name="delete_room_number">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline close-modal">Cancel</button>
                    <button type="submit" name="delete_room" class="btn btn-danger">Delete</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Add Room Modal
        document.getElementById('addRoomBtn').onclick = function() {
            document.getElementById('addRoomModal').classList.add('active');
        };
        
        // Edit Room Modal
        document.querySelectorAll('.btn-edit-room').forEach(btn => {
            btn.addEventListener('click', function() {
                const room = JSON.parse(this.getAttribute('data-room'));
                document.getElementById('edit_room_number').value = room.room_number;
                document.getElementById('edit_status').value = room.status;
                document.getElementById('edit_type').value = room.type;
                document.getElementById('edit_capacity').value = room.capacity;
                document.getElementById('edit_for').value = room.for_whom;
                document.getElementById('editRoomModal').classList.add('active');
            });
        });
        
        // Delete Room Modal
        document.querySelectorAll('.btn-delete-room').forEach(btn => {
            btn.addEventListener('click', function() {
                const roomNumber = this.getAttribute('data-room-number');
                document.getElementById('delete_room_number').value = roomNumber;
                document.getElementById('deleteRoomNumberDisplay').textContent = roomNumber;
                document.getElementById('deleteRoomModal').classList.add('active');
            });
        });
        
        // Close Modals
        document.querySelectorAll('.close-modal').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.modal').forEach(modal => {
                    modal.classList.remove('active');
                });
            });
        });
        
        window.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal')) {
                e.target.classList.remove('active');
            }
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>
