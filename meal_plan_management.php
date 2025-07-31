<?php
session_start();
require_once 'db.php';

// Manual session message test
if (isset($_GET['testmsg'])) {
    $_SESSION['message'] = 'Manual session message test!';
    header('Location: meal_plan_management.php');
    exit();
}

// Check if user is logged in and has required role
if (!isset($_SESSION['role']) || !isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Function to check if staff is assigned to a patient
function isStaffAssignedToPatient($conn, $staff_id, $patient_id) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM staff_patient_assignments WHERE staff_id = ? AND patient_id = ?");
    $stmt->bind_param("si", $staff_id, $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['count'] > 0;
}

// Function to check if user is relative of a patient
function isRelativeOfPatient($conn, $user_id, $patient_id) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM patients WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $patient_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['count'] > 0;
}

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log('DEBUG: POST handler entered. POST data: ' . print_r($_POST, true));
    // Only chief-staff can perform CRUD operations
    if ($_SESSION['role'] !== 'chief-staff') {
        error_log('DEBUG: Role check failed. Role: ' . $_SESSION['role']);
        $_SESSION['error'] = 'You do not have permission to perform this action.';
        header('Location: meal_plan_management.php');
        exit();
    }

    // Add Food Item
    if (isset($_POST['add_food_item'])) {
        $name = $conn->real_escape_string($_POST['name']);
        $tags = $_POST['tags'] ?? [];
        $meal_times = $_POST['meal_times'] ?? [];

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("INSERT INTO food_items (name) VALUES (?)");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("s", $name);
            $stmt->execute();
            $food_item_id = $conn->insert_id;

            if (!empty($tags)) {
                $stmt = $conn->prepare("INSERT INTO food_item_tags (food_item_id, tag_id) VALUES (?, ?)");
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
                foreach ($tags as $tag_id) {
                    $stmt->bind_param("ii", $food_item_id, $tag_id);
                    $stmt->execute();
                }
            }

            if (!empty($meal_times)) {
                $stmt = $conn->prepare("INSERT INTO food_item_meal_times (food_item_id, meal_time) VALUES (?, ?)");
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
                foreach ($meal_times as $meal_time) {
                    $stmt->bind_param("is", $food_item_id, $meal_time);
                    $stmt->execute();
                }
            }

            $conn->commit();
            $_SESSION['message'] = 'Food item added successfully!';
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = 'Error adding food item: ' . $e->getMessage();
        }
    }

    // Edit Food Item
    if (isset($_POST['edit_food_item'])) {
        $id = $conn->real_escape_string($_POST['food_item_id']);
        $name = $conn->real_escape_string($_POST['name']);
        $tags = $_POST['tags'] ?? [];
        $meal_times = $_POST['meal_times'] ?? [];

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("UPDATE food_items SET name = ? WHERE id = ?");
            $stmt->bind_param("si", $name, $id);
            $stmt->execute();

            // Update tags
            $conn->query("DELETE FROM food_item_tags WHERE food_item_id = $id");
            if (!empty($tags)) {
                $stmt = $conn->prepare("INSERT INTO food_item_tags (food_item_id, tag_id) VALUES (?, ?)");
                foreach ($tags as $tag_id) {
                    $stmt->bind_param("ii", $id, $tag_id);
                    $stmt->execute();
                }
            }

            // Update meal times
            $conn->query("DELETE FROM food_item_meal_times WHERE food_item_id = $id");
            if (!empty($meal_times)) {
                $stmt = $conn->prepare("INSERT INTO food_item_meal_times (food_item_id, meal_time) VALUES (?, ?)");
                foreach ($meal_times as $meal_time) {
                    $stmt->bind_param("is", $id, $meal_time);
                    $stmt->execute();
                }
            }

            $conn->commit();
            $_SESSION['message'] = 'Food item updated successfully!';
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = 'Error updating food item: ' . $e->getMessage();
        }
    }

    // Delete Food Item
    if (isset($_POST['delete_food_item'])) {
        $id = $conn->real_escape_string($_POST['food_item_id']);
        try {
            $conn->query("DELETE FROM food_items WHERE id = $id");
            $_SESSION['message'] = 'Food item deleted successfully!';
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error deleting food item: ' . $e->getMessage();
        }
    }

    // Create Weekly Meal Plan
    if (isset($_POST['create_meal_plan'])) {
        $name = $_POST['plan_name'];
        $type_id = $_POST['type_id'];
        $tags = $_POST['tags'] ?? [];
        $meals = json_decode($_POST['meals'] ?? '[]', true);

        if (empty($name) || empty($type_id) || empty($meals)) {
            $_SESSION['error'] = "Missing required fields.";
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit();
        }

        try {
            $conn->begin_transaction();

            // Delete previous plan of the same type
            $stmt = $conn->prepare("SELECT id FROM weekly_meal_plans WHERE type_id = ?");
            $stmt->bind_param("i", $type_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $old_plan_id = $row['id'];
                // Delete all related entries first
                $conn->query("DELETE FROM weekly_meal_plan_entries WHERE plan_id = $old_plan_id");
                $conn->query("DELETE FROM weekly_meal_plan_tags WHERE plan_id = $old_plan_id");
                $conn->query("DELETE FROM patient_meal_plan_assignments WHERE plan_id = $old_plan_id");
                // Finally delete the plan itself
                $conn->query("DELETE FROM weekly_meal_plans WHERE id = $old_plan_id");
            }

            // Insert into weekly_meal_plans
            $stmt = $conn->prepare("INSERT INTO weekly_meal_plans (name, type_id, status) VALUES (?, ?, 'active')");
            $stmt->bind_param("si", $name, $type_id);
            $stmt->execute();
            $plan_id = $stmt->insert_id;
            $stmt->close();

            // Insert tags (if any)
            if (!empty($tags)) {
                $stmt = $conn->prepare("INSERT INTO weekly_meal_plan_tags (plan_id, tag_id) VALUES (?, ?)");
                foreach ($tags as $tag_id) {
                    $stmt->bind_param("ii", $plan_id, $tag_id);
                    $stmt->execute();
                }
                $stmt->close();
            }

            // Insert weekly meals
            $stmt = $conn->prepare("INSERT INTO weekly_meal_plan_entries (plan_id, day_of_week, meal_time, food_item_id) VALUES (?, ?, ?, ?)");
            foreach ($meals as $meal) {
                $day = $meal['day'];
                $time = $meal['meal_time'];
                $food_id = (int)$meal['food_item_id'];
                $stmt->bind_param("issi", $plan_id, $day, $time, $food_id);
                $stmt->execute();
            }
            $stmt->close();

            $conn->commit();
            $_SESSION['message'] = "Meal plan created successfully.";
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = "Error: " . $e->getMessage();
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit();
        }
    }

    // Edit Meal Plan
    if (isset($_POST['edit_meal_plan'])) {
        $id = $conn->real_escape_string($_POST['plan_id']);
        $name = $conn->real_escape_string($_POST['plan_name']);
        $type_id = $conn->real_escape_string($_POST['type_id']);
        $tags = $_POST['tags'] ?? [];
        $meals = json_decode($_POST['meals'], true);

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("UPDATE weekly_meal_plans SET name = ?, type_id = ? WHERE id = ?");
            $stmt->bind_param("sii", $name, $type_id, $id);
            $stmt->execute();

            $conn->query("DELETE FROM weekly_meal_plan_tags WHERE plan_id = $id");
            if (!empty($tags)) {
                $stmt = $conn->prepare("INSERT INTO weekly_meal_plan_tags (plan_id, tag_id) VALUES (?, ?)");
                foreach ($tags as $tag_id) {
                    $stmt->bind_param("ii", $id, $tag_id);
                    $stmt->execute();
                }
            }

            $conn->query("DELETE FROM weekly_meal_plan_entries WHERE plan_id = $id");
            if (!empty($meals)) {
                $stmt = $conn->prepare("INSERT INTO weekly_meal_plan_entries (plan_id, day_of_week, meal_time, food_item_id) VALUES (?, ?, ?, ?)");
                foreach ($meals as $meal) {
                    $stmt->bind_param("issi", $id, $meal['day'], $meal['meal_time'], $meal['food_item_id']);
                    $stmt->execute();
                }
            }

            $conn->commit();
            $_SESSION['message'] = 'Meal plan updated successfully!';
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = 'Error updating meal plan: ' . $e->getMessage();
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit();
        }
    }

    // Delete Meal Plan
    if (isset($_POST['delete_meal_plan'])) {
        $id = $conn->real_escape_string($_POST['plan_id']);
        try {
            $conn->query("DELETE FROM weekly_meal_plan_entries WHERE plan_id = $id");
            $conn->query("DELETE FROM weekly_meal_plan_tags WHERE plan_id = $id");
            $conn->query("DELETE FROM patient_meal_plan_assignments WHERE plan_id = $id");
            $conn->query("DELETE FROM weekly_meal_plans WHERE id = $id");
            $_SESSION['message'] = 'Meal plan deleted successfully!';
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit();
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error deleting meal plan: ' . $e->getMessage();
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit();
        }
    }

    // Delete Meal Slot
    if (isset($_POST['delete_meal_slot'])) {
        $plan_id = $conn->real_escape_string($_POST['plan_id']);
        $day = $conn->real_escape_string($_POST['day']);
        $meal_time = $conn->real_escape_string($_POST['meal_time']);
        try {
            $conn->query("DELETE FROM weekly_meal_plan_entries WHERE plan_id = $plan_id AND day_of_week = '$day' AND meal_time = '$meal_time'");
            $_SESSION['message'] = 'Meal slot deleted successfully!';
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error deleting meal slot: ' . $e->getMessage();
        }
    }

    // Delete Day
    if (isset($_POST['delete_day'])) {
        $plan_id = $conn->real_escape_string($_POST['plan_id']);
        $day = $conn->real_escape_string($_POST['day']);
        try {
            $conn->query("DELETE FROM weekly_meal_plan_entries WHERE plan_id = $plan_id AND day_of_week = '$day'");
            $_SESSION['message'] = 'Day deleted successfully!';
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error deleting day: ' . $e->getMessage();
        }
    }

    // Update type
    if (isset($_POST['update_type'])) {
        $_SESSION['selected_meal_type'] = $_POST['type_id'];
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    }
}

// Fetch food items based on user role
if ($_SESSION['role'] === 'chief-staff') {
    // Chief staff can see all food items
    $food_items_query = "SELECT f.*, 
        GROUP_CONCAT(DISTINCT t.name) as tags, 
        GROUP_CONCAT(DISTINCT mt.meal_time) as meal_times 
        FROM food_items f 
        LEFT JOIN food_item_tags ft ON f.id = ft.food_item_id 
        LEFT JOIN meal_tags t ON ft.tag_id = t.id 
        LEFT JOIN food_item_meal_times mt ON f.id = mt.food_item_id 
        GROUP BY f.id, f.name, f.created_at, f.updated_at";
} else {
    // Staff and relatives can only see food items from their assigned patients' meal plans
    $user_id = $_SESSION['user_id'];
    $staff_id = $_SESSION['staff_id'] ?? null;
    
    if ($_SESSION['role'] === 'staff' && $staff_id) {
        // Get assigned patients for staff
        $food_items_query = "SELECT DISTINCT f.*, 
            GROUP_CONCAT(DISTINCT t.name) as tags, 
            GROUP_CONCAT(DISTINCT mt.meal_time) as meal_times 
            FROM food_items f 
            LEFT JOIN food_item_tags ft ON f.id = ft.food_item_id 
            LEFT JOIN meal_tags t ON ft.tag_id = t.id 
            LEFT JOIN food_item_meal_times mt ON f.id = mt.food_item_id 
            INNER JOIN weekly_meal_plan_entries wmpe ON f.id = wmpe.food_item_id 
            INNER JOIN patient_meal_plan_assignments pmpa ON wmpe.plan_id = pmpa.plan_id 
            INNER JOIN staff_patient_assignments spa ON pmpa.patient_id = spa.patient_id 
            WHERE spa.staff_id = '$staff_id'
            GROUP BY f.id, f.name, f.created_at, f.updated_at";
    } else if ($_SESSION['role'] === 'relative') {
        // Get food items for patients related to the relative
        $food_items_query = "SELECT DISTINCT f.*, 
            GROUP_CONCAT(DISTINCT t.name) as tags, 
            GROUP_CONCAT(DISTINCT mt.meal_time) as meal_times 
            FROM food_items f 
            LEFT JOIN food_item_tags ft ON f.id = ft.food_item_id 
            LEFT JOIN meal_tags t ON ft.tag_id = t.id 
            LEFT JOIN food_item_meal_times mt ON f.id = mt.food_item_id 
            INNER JOIN weekly_meal_plan_entries wmpe ON f.id = wmpe.food_item_id 
            INNER JOIN patient_meal_plan_assignments pmpa ON wmpe.plan_id = pmpa.plan_id 
            INNER JOIN patients p ON pmpa.patient_id = p.id 
            WHERE p.user_id = $user_id
            GROUP BY f.id, f.name, f.created_at";
    }
}

$food_items_result = $conn->query($food_items_query);
if (!$food_items_result) {
    $_SESSION['error'] = 'Error fetching food items: ' . $conn->error;
    $food_items = [];
} else {
    $food_items = $food_items_result->fetch_all(MYSQLI_ASSOC);
}

// Fetch meal plan types
$types_query = "SELECT * FROM meal_plan_types ORDER BY name";
$meal_plan_types = $conn->query($types_query)->fetch_all(MYSQLI_ASSOC);

// Fetch meal tags
$tags_query = "SELECT * FROM meal_tags ORDER BY name";
$tags = $conn->query($tags_query)->fetch_all(MYSQLI_ASSOC);

// Fetch weekly meal plans
if ($_SESSION['role'] === 'chief-staff') {
    $plans_query = "SELECT p.*, t.name as type_name, GROUP_CONCAT(DISTINCT tg.name) as tags 
                    FROM weekly_meal_plans p 
                    LEFT JOIN meal_plan_types t ON p.type_id = t.id 
                    LEFT JOIN weekly_meal_plan_tags pt ON p.id = pt.plan_id 
                    LEFT JOIN meal_tags tg ON pt.tag_id = tg.id 
                    GROUP BY p.id 
                    ORDER BY p.created_at DESC";
} else {
    // Similar role-based filtering for meal plans
    // ... (implement similar logic as food items query)
}

$meal_plans = $conn->query($plans_query)->fetch_all(MYSQLI_ASSOC);

// Fetch patients for assignment
$patients_query = "SELECT p.id, CONCAT(u.first_name, ' ', u.last_name) as name 
                  FROM patients p 
                  JOIN users u ON p.user_id = u.id 
                  WHERE p.status = 'admitted'";
$patients = $conn->query($patients_query)->fetch_all(MYSQLI_ASSOC);

// Add this near the top of the file, after session_start()
if (isset($_POST['update_type'])) {
    $_SESSION['selected_meal_type'] = $_POST['type_id'];
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}
$selected_type_id = isset($_SESSION['selected_meal_type']) ? intval($_SESSION['selected_meal_type']) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meal Planning - United Medical Asylum & Rehab Facility</title>
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

        .icon-utensils {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='currentColor'%3E%3Cpath d='M8.1 13.34L3.91 9.16C2.35 7.59 2.35 5.06 3.91 3.5L10.93 10.5L8.1 13.34ZM14.88 11.53C16.32 12.97 16.32 15.31 14.88 16.75L10.93 12.8L14.88 8.84C16.32 10.28 16.32 12.62 14.88 14.06L14.88 11.53ZM5.71 20.29C5.32 19.9 5.32 19.27 5.71 18.88L7.83 16.76L9.24 18.17L7.12 20.29C6.73 20.68 6.1 20.68 5.71 20.29Z'/%3E%3C/svg%3E");
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
            background: var(--accent-orange);
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            box-shadow: 0 4px 15px rgba(249, 115, 22, 0.3);
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
            color: var(--accent-orange);
            background: rgba(249, 115, 22, 0.1);
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
            color: var(--text-primary);
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

        .breadcrumb a {
            color: var(--accent-orange);
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        /* Content Cards */
        .content-card {
            background: white;
            border: 1px solid var(--border-primary);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--box-shadow);
            overflow: hidden;
            margin-bottom: 30px;
        }

        .card-header {
            padding: 25px 30px;
            border-bottom: 1px solid var(--border-primary);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--bg-card);
        }

        .card-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-body {
            padding: 30px;
        }

        /* Grid Layout */
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        .grid-3 {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
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
        }

        .form-control::placeholder {
            color: var(--text-muted);
        }

        .form-control:focus {
            border-color: var(--accent-orange);
            box-shadow: 0 0 0 4px rgba(249, 115, 22, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
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
            background: var(--accent-orange);
            color: white;
            box-shadow: 0 4px 15px rgba(249, 115, 22, 0.3);
        }

        .btn-primary:hover {
            background: var(--accent-orange-light);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(249, 115, 22, 0.4);
        }

        .btn-outline {
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border-primary);
        }

        .btn-outline:hover {
            background: var(--bg-hover);
            border-color: var(--accent-orange);
            color: var(--accent-orange);
        }

        .btn-sm {
            padding: 8px 16px;
            font-size: 0.8rem;
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

        .btn-warning {
            background: var(--accent-orange);
            color: white;
            box-shadow: 0 4px 15px rgba(249, 115, 22, 0.3);
        }

        .btn-warning:hover {
            background: var(--accent-orange-light);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(249, 115, 22, 0.4);
        }

        .btn-info {
            background: var(--accent-blue);
            color: white;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
        }

        .btn-info:hover {
            background: var(--accent-blue-light);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
        }

        /* Food Items Grid */
        .food-items-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .food-item {
            background: var(--bg-card);
            border: 1px solid var(--border-primary);
            border-radius: var(--border-radius);
            padding: 20px;
            transition: var(--transition);
            cursor: pointer;
            position: relative;
        }

        .food-item:hover {
            transform: translateY(-2px);
            box-shadow: var(--box-shadow);
            border-color: var(--accent-orange);
        }

        .food-item h4 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .food-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 10px;
        }

        .food-category {
            background: rgba(249, 115, 22, 0.1);
            color: var(--accent-orange);
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .food-calories {
            color: var(--text-muted);
            font-size: 0.85rem;
        }

        /* Status Badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-badge.active {
            background: rgba(16, 185, 129, 0.1);
            color: var(--accent-green);
        }

        .status-badge.draft {
            background: rgba(249, 115, 22, 0.1);
            color: var(--accent-orange);
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
            max-width: 800px;
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
            color: var(--text-primary);
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

        /* Alert Styles */
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--accent-green);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            color: var(--accent-red);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        /* Table Styles */
        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            background: var(--bg-card);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            border-radius: 8px;
            overflow: hidden;
        }

        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-primary);
        }

        th {
            background-color: var(--bg-secondary);
            color: var(--text-primary);
            font-weight: 600;
        }

        tr:hover {
            background-color: var(--bg-hover);
        }

        /* Badge Styles */
        .badge {
            display: inline-block;
            padding: 0.35rem 0.65rem;
            border-radius: 50rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: capitalize;
        }

        .badge-primary {
            background-color: rgba(249, 115, 22, 0.1);
            color: var(--accent-orange);
        }

        .badge-success {
            background-color: rgba(16, 185, 129, 0.1);
            color: var(--accent-green);
        }

        .badge-warning {
            background-color: rgba(249, 115, 22, 0.1);
            color: var(--accent-orange);
        }

        .badge-danger {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--accent-red);
        }

        /* Day Grid */
        .day-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin: 1rem 0;
        }

        .day {
            padding: 1rem;
            border: 1px solid var(--border-primary);
            border-radius: 8px;
            background-color: white;
        }

        .day h4 {
            color: var(--accent-orange);
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--border-primary);
        }

        .food-item-select {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid var(--border-primary);
            border-radius: 6px;
            font-size: 0.875rem;
            margin-bottom: 0.75rem;
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .grid-2 {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 20px;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .day-grid {
                grid-template-columns: 1fr;
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

        .content-card:nth-child(1) { animation-delay: 0.1s; }
        .content-card:nth-child(2) { animation-delay: 0.2s; }
        .content-card:nth-child(3) { animation-delay: 0.3s; }
    </style>
</head>
<body>
    <!-- Topbar -->
    <div class="topbar">
        <div class="logo">
            <div class="logo-icon">
                <i class="fas fa-utensils"></i>
            </div>
            <div class="logo-text">
                <h1>United Medical Asylum & Rehab Facility</h1>
                <p>Meal Planning</p>
            </div>
        </div>

        <div class="nav-links">
            <a href="chief_staff_dashboard.php" class="nav-link">Dashboard</a>
            <a href="#" class="nav-link active">Meal Planning</a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">Meal Planning Management</h1>
            <div class="breadcrumb">
                <a href="chief_staff_dashboard.php">Home</a>
                <i class="fas fa-chevron-right"></i>
                <span>Meal Planning</span>
            </div>
        </div>

        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-success" id="notification-box">
                <i class="fas fa-check-circle"></i>
                <?php echo $_SESSION['message']; unset($_SESSION['message']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger" id="notification-box">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <!-- Food Items & Active Meal Plans Section -->
        <div class="grid-2">
            <!-- Food Items Management -->
            <div class="content-card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-apple-alt"></i>
                        Food Items
                    </h3>
                    <?php if ($_SESSION['role'] === 'chief-staff'): ?>
                        <button class="btn btn-primary" onclick="openModal('addFoodItemModal')">
                            <i class="fas fa-plus"></i>
                            Add Food Item
                        </button>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <input type="text" class="form-control" id="searchFood" placeholder="Search food items...">
                    </div>
                    
                    <div class="food-items-grid" id="foodItemsGrid">
                        <?php foreach ($food_items as $item): ?>
                        <div class="food-item" data-id="<?php echo $item['id']; ?>">
                            <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                            <div style="margin-bottom: 0.3rem;">
                                <?php if (!empty($item['tags'])): ?>
                                    <?php foreach (explode(',', $item['tags']) as $tag): ?>
                                        <span style="display:inline-block;background:rgba(249, 115, 22, 0.1);color:var(--accent-orange);border-radius:10px;padding:0.13em 0.5em;font-size:0.75em;margin-right:0.2em;margin-bottom:0.15em;">
                                            <?= htmlspecialchars(trim($tag)) ?>
                                        </span>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <div style="margin-bottom: 0.4rem;">
                                <?php if (!empty($item['meal_times'])): ?>
                                    <?php foreach (explode(',', $item['meal_times']) as $time): ?>
                                        <span style="display:inline-block;background:rgba(16, 185, 129, 0.1);color:var(--accent-green);border-radius:10px;padding:0.13em 0.5em;font-size:0.75em;margin-right:0.2em;">
                                            <?php
                                            $icons = [
                                                'breakfast' => '🍳',
                                                'lunch' => '🍛',
                                                'snack' => '🍎',
                                                'dinner' => '🍽️'
                                            ];
                                            echo ($icons[trim($time)] ?? '') . ' ' . ucfirst(trim($time));
                                            ?>
                                        </span>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <?php if ($_SESSION['role'] === 'chief-staff'): ?>
                                <div style="position:absolute;bottom:0.6rem;right:0.6rem;">
                                    <button class="btn btn-sm btn-warning" style="font-size:0.9em;padding:0.2em 0.4em;" onclick="editFoodItem(<?= $item['id'] ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" style="font-size:0.9em;padding:0.2em 0.4em;" onclick="deleteFoodItem(<?= $item['id'] ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Active Meal Plans -->
            <div class="content-card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-list"></i>
                        Active Meal Plans
                    </h3>
                    <div style="display: flex; gap: 10px;">
                    <?php if ($_SESSION['role'] === 'chief-staff'): ?>
                            <button class="btn btn-primary btn-sm" onclick="openModal('createMealPlanModal')">
                            <i class="fas fa-plus"></i> Create Meal Plan
                        </button>
                            <button class="btn btn-primary btn-sm" onclick="openModal('assignMealPlanModal')">
                            <i class="fas fa-user-plus"></i> Assign Meal Plan
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                    <?php if (empty($meal_plans)): ?>
                        <div style="padding: 1rem; color: var(--text-muted);">No meal plans available</div>
                    <?php else: ?>
                        <div id="mealPlanCards">
                                <?php foreach ($meal_plans as $plan): ?>
                                <div class="meal-plan-card" data-plan-id="<?php echo $plan['id']; ?>" style="background: #f4f5f7; border-radius: 18px; margin-bottom: 24px; box-shadow: 0 2px 8px #eee;">
                                    <div class="meal-plan-header" style="display: flex; align-items: center; justify-content: space-between; padding: 24px 32px; cursor: pointer; border-bottom: 1px solid #e5e7eb;">
                                        <div>
                                            <div style="font-size: 1.2rem; font-weight: 700; color: #222; margin-bottom: 6px;"> <?php echo htmlspecialchars($plan['name']); ?> </div>
                                            <div style="display: flex; gap: 18px; align-items: center; font-size: 1rem; color: #4b5563;">
                                                <span><i class="fas fa-tag"></i> <?php echo htmlspecialchars($plan['type_name']); ?></span>
                                                <!-- Add more meta if needed -->
                                        <span class="badge badge-success">Active</span>
                </div>
            </div>
                                        <div style="display: flex; align-items: center; gap: 12px;">
                                            <button class="btn btn-sm btn-warning" onclick="event.stopPropagation(); editPlan(<?php echo $plan['id']; ?>)"><i class="fas fa-edit"></i></button>
                                            <button class="btn btn-sm btn-danger" onclick="event.stopPropagation(); deletePlan(<?php echo $plan['id']; ?>)"><i class="fas fa-trash"></i></button>
                                            <span class="chevron" style="font-size: 1.6rem; color: #f97316; transition: transform 0.2s;">&#8250;</span>
        </div>
                                    </div>
                                    <div class="meal-plan-details" style="display: none; background: #fff;">
        <?php
            // Fetch all entries for this plan
                                        $entries_query = "SELECT * FROM weekly_meal_plan_entries WHERE plan_id = " . $plan['id'];
            $entries_result = $conn->query($entries_query);
            $entries = [];
            while ($row = $entries_result->fetch_assoc()) {
                $entries[$row['day_of_week']][$row['meal_time']] = $row['food_item_id'];
            }
            // Map food item IDs to names
            $food_map = [];
            foreach ($food_items as $item) {
                $food_map[$item['id']] = $item['name'];
            }
            // Change days order to start from Saturday
            $days = ['saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
            $meal_times = ['breakfast', 'lunch', 'snack', 'dinner'];
            $meal_labels = ['breakfast' => 'BREAKFAST', 'lunch' => 'LUNCH', 'snack' => 'SNACKS', 'dinner' => 'DINNER'];
                                        ?>
                        <?php foreach ($days as $day): ?>
                                            <div style="margin: 24px 0 0 0;">
                                                <div style="font-weight: 700; font-size: 1.1rem; color: #222; margin-bottom: 10px;"> <?php echo ucfirst($day); ?> </div>
                                                <div style="display: flex; gap: 18px; flex-wrap: wrap;">
                                <?php foreach ($meal_times as $meal_time): ?>
                                                        <div style="background: #f9fafb; border-radius: 12px; border: 1.5px solid #f97316; min-width: 220px; padding: 18px 22px; margin-bottom: 10px;">
                                                            <div style="font-weight: 700; color: #f97316; margin-bottom: 8px;"> <?php echo $meal_labels[$meal_time]; ?> </div>
                                                            <ul style="margin: 0; padding-left: 18px; color: #374151;">
                                            <?php
                                                                $food_id = $entries[$day][$meal_time] ?? null;
                                                                if ($food_id && isset($food_map[$food_id])) {
                                                                    echo '<li>' . htmlspecialchars($food_map[$food_id]) . '</li>';
                                                                } else {
                                                                    echo '<li style=\'color:#bbb\'>-</li>';
                                                                }
                                                                ?>
                                                            </ul>
                                    </div>
                                <?php endforeach; ?>
                                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                            <?php endforeach; ?>
            </div>
                    <?php endif; ?>
    </div>
            </div>
                            </div>
                        </div>
                        
    <!-- Add Food Item Modal -->
    <div id="addFoodItemModal" class="modal">
        <div class="modal-content" style="max-width: 700px;">
            <div class="modal-header" style="background: #ffb877;">
                <h3 style="font-size: 1.3rem; font-weight: 600; color: #fff;">Add Food Item</h3>
                <button class="close-modal" onclick="closeModal('addFoodItemModal')" style="font-size: 1.5rem; color: #fff; background: none; border: none;">&times;</button>
                        </div>
            <div class="modal-body">
                <form method="POST" action="">
                            <div class="form-group">
                        <label for="name">Name</label>
                        <input type="text" id="name" name="name" class="form-control" required>
                            </div>
                            <div class="form-group">
                        <label>Tags</label>
                        <div style="display: flex; flex-wrap: wrap; gap: 16px;">
                            <?php foreach ($tags as $tag): ?>
                                <label style="background: #ffb877; color: #fff; border-radius: 20px; padding: 10px 22px; display: flex; align-items: center; gap: 8px; font-weight: 500;">
                                    <input type="checkbox" name="tags[]" value="<?php echo $tag['id']; ?>" style="accent-color: #ffb877;"> <?php echo htmlspecialchars($tag['name']); ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        </div>
                    <div class="form-group">
                        <label>Meal Times</label>
                        <div style="display: flex; gap: 24px;">
                            <label style="background: #fff; border: 1.5px solid #ffb877; border-radius: 12px; padding: 18px 28px; display: flex; align-items: center; gap: 8px; font-weight: 500; color: #ffb877;">
                                <input type="checkbox" name="meal_times[]" value="breakfast" style="accent-color: #ffb877;"> Breakfast
                            </label>
                            <label style="background: #fff; border: 1.5px solid #ffb877; border-radius: 12px; padding: 18px 28px; display: flex; align-items: center; gap: 8px; font-weight: 500; color: #ffb877;">
                                <input type="checkbox" name="meal_times[]" value="lunch" style="accent-color: #ffb877;"> Lunch
                            </label>
                            <label style="background: #fff; border: 1.5px solid #ffb877; border-radius: 12px; padding: 18px 28px; display: flex; align-items: center; gap: 8px; font-weight: 500; color: #ffb877;">
                                <input type="checkbox" name="meal_times[]" value="snack" style="accent-color: #ffb877;"> Snacks
                            </label>
                            <label style="background: #fff; border: 1.5px solid #ffb877; border-radius: 12px; padding: 18px 28px; display: flex; align-items: center; gap: 8px; font-weight: 500; color: #ffb877;">
                                <input type="checkbox" name="meal_times[]" value="dinner" style="accent-color: #ffb877;"> Dinner
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 1rem; background: none; border: none;">
                        <button type="button" class="btn btn-outline" onclick="closeModal('addFoodItemModal')" style="border: 1.5px solid #ffb877; color: #ffb877; background: #fff;">Cancel</button>
                        <button type="submit" name="add_food_item" class="btn btn-primary" style="background: #ffb877; color: #fff;">Add Food Item</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Create Meal Plan Modal -->
    <div id="createMealPlanModal" class="modal">
        <div class="modal-content" style="max-width: 1200px;">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-calendar-plus" style="color: var(--accent-orange);"></i>
                    Create Weekly Meal Plan
                </h3>
                <button class="close-modal" onclick="closeModal('createMealPlanModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" action="" id="createMealPlanForm">
                    <!-- Plan Details Section -->
                    <div style="background: var(--bg-card); padding: 20px; border-radius: 10px; margin-bottom: 25px;">
                        <h4 style="color: var(--text-primary); margin-bottom: 15px; display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-info-circle" style="color: var(--accent-blue);"></i>
                            Plan Details
                        </h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="plan_name" class="form-label">Plan Name *</label>
                                <input type="text" id="plan_name" name="plan_name" class="form-control" required placeholder="Enter meal plan name">
                            </div>
                            <div class="form-group">
                                <label for="type_id" class="form-label">Plan Type *</label>
                                <select id="type_id" name="type_id" class="form-control" required onchange="loadFoodItems()">
                                    <option value="">Select Plan Type</option>
                                    <?php foreach ($meal_plan_types as $type): ?>
                                        <?php if (in_array($type['name'], ['Diabetic', 'Gluten-Free', 'High-Protein', 'Low-Carb', 'Non-Vegetarian', 'Vegetarian'])): ?>
                                            <option value="<?php echo $type['id']; ?>"><?php echo htmlspecialchars($type['name']); ?></option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Weekly Schedule Section -->
                    <div style="background: var(--bg-card); padding: 20px; border-radius: 10px;">
                        <h4 style="color: var(--text-primary); margin-bottom: 15px; display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-calendar-week" style="color: var(--accent-purple);"></i>
                            Weekly Schedule
                        </h4>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
                            <?php
                            $days = ['saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
                            $meal_times = ['breakfast', 'lunch', 'snack', 'dinner'];
                            $day_colors = [
                                'saturday' => 'var(--accent-red)',
                                'sunday' => 'var(--accent-orange)', 
                                'monday' => 'var(--accent-blue)',
                                'tuesday' => 'var(--accent-green)',
                                'wednesday' => 'var(--accent-purple)',
                                'thursday' => 'var(--accent-teal)',
                                'friday' => 'var(--accent-orange)'
                            ];
                            foreach ($days as $day):
                            ?>
                                <div style="background: white; border: 2px solid var(--border-primary); border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
                                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid var(--border-primary);">
                                        <div style="width: 12px; height: 12px; background: <?php echo $day_colors[$day]; ?>; border-radius: 50%;"></div>
                                        <h4 style="color: var(--text-primary); margin: 0; font-size: 1.1rem;"><?php echo ucfirst($day); ?></h4>
                                    </div>
                                    <?php foreach ($meal_times as $meal_time): ?>
                                        <div style="margin-bottom: 15px;">
                                            <label style="display: block; font-weight: 500; color: var(--text-secondary); margin-bottom: 5px; font-size: 0.9rem;">
                                                <?php
                                                $icons = ['breakfast' => '🍳', 'lunch' => '🍛', 'snack' => '🍎', 'dinner' => '🍽️'];
                                                echo $icons[$meal_time] . ' ' . ucfirst($meal_time);
                                                ?>
                                            </label>
                                            <select class="food-item-select" name="meals[<?php echo $day; ?>][<?php echo $meal_time; ?>]" data-day="<?php echo $day; ?>" data-meal-time="<?php echo $meal_time; ?>" style="width: 100%; padding: 8px 12px; border: 1px solid var(--border-primary); border-radius: 6px; font-size: 0.85rem; background: white;">
                                                <option value="">Select Food Item</option>
                                            </select>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <input type="hidden" name="meals" id="mealsJson">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('createMealPlanModal')">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" name="create_meal_plan" class="btn btn-primary" form="createMealPlanForm">
                    <i class="fas fa-save"></i> Create Meal Plan
                </button>
            </div>
        </div>
    </div>

    <!-- Assign Meal Plan Modal -->
    <div id="assignMealPlanModal" class="modal">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-user-plus" style="color: var(--accent-green);"></i>
                    Assign Meal Plan to Patient
                </h3>
                <button class="close-modal" onclick="closeModal('assignMealPlanModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" action="">
                    <!-- Assignment Details Section -->
                    <div style="background: var(--bg-card); padding: 20px; border-radius: 10px; margin-bottom: 20px;">
                        <h4 style="color: var(--text-primary); margin-bottom: 15px; display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-clipboard-list" style="color: var(--accent-blue);"></i>
                            Assignment Details
                        </h4>
                        <div class="form-group">
                            <label for="patient_id" class="form-label">Select Patient *</label>
                            <select id="patient_id" name="patient_id" class="form-control" required>
                                <option value="">Choose a patient...</option>
                                <?php foreach ($patients as $patient): ?>
                                    <option value="<?php echo $patient['id']; ?>"><?php echo htmlspecialchars($patient['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="plan_id" class="form-label">Select Meal Plan *</label>
                            <select id="plan_id" name="plan_id" class="form-control" required>
                                <option value="">Choose a meal plan...</option>
                                <?php foreach ($meal_plans as $plan): ?>
                                    <option value="<?php echo $plan['id']; ?>"><?php echo htmlspecialchars($plan['name']); ?> (<?php echo htmlspecialchars($plan['type_name']); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Schedule Section -->
                    <div style="background: var(--bg-card); padding: 20px; border-radius: 10px;">
                        <h4 style="color: var(--text-primary); margin-bottom: 15px; display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-calendar-alt" style="color: var(--accent-purple);"></i>
                            Assignment Schedule
                        </h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="assign_start_date" class="form-label">Start Date *</label>
                                <input type="date" id="assign_start_date" name="start_date" class="form-control" required style="color-scheme: light;">
                            </div>
                            <div class="form-group">
                                <label for="assign_end_date" class="form-label">End Date *</label>
                                <input type="date" id="assign_end_date" name="end_date" class="form-control" required style="color-scheme: light;">
                            </div>
                        </div>
                        
                        <div style="background: rgba(249, 115, 22, 0.1); border: 1px solid rgba(249, 115, 22, 0.2); border-radius: 8px; padding: 15px; margin-top: 15px;">
                            <div style="display: flex; align-items: center; gap: 8px; color: var(--accent-orange);">
                                <i class="fas fa-info-circle"></i>
                                <span style="font-weight: 500; font-size: 0.9rem;">Assignment Information</span>
                            </div>
                            <p style="margin: 8px 0 0 0; font-size: 0.85rem; color: var(--text-secondary);">
                                The meal plan will be active for the selected patient during the specified date range. 
                                You can modify or end the assignment at any time.
                            </p>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('assignMealPlanModal')">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" name="assign_meal_plan" class="btn btn-primary">
                    <i class="fas fa-check"></i> Assign Meal Plan
                </button>
            </div>
        </div>
    </div>

    <!-- Edit Food Item Modal -->
    <div id="editFoodItemModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Edit Food Item</h3>
                <button class="close-modal" onclick="closeModal('editFoodItemModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" action="">
                    <input type="hidden" name="food_item_id" id="edit_food_item_id">
                    <div class="form-group">
                        <label for="edit_name" class="form-label">Name</label>
                        <input type="text" id="edit_name" name="name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tags</label>
                        <div style="display: flex; flex-wrap: wrap; gap: 10px;" id="edit_tags_container">
                            <?php foreach ($tags as $tag): ?>
                                <label style="display: flex; align-items: center; gap: 5px; padding: 8px 12px; background: #ffb877; color: #fff; border-radius: 8px; cursor: pointer;">
                                    <input type="checkbox" name="tags[]" value="<?php echo $tag['id']; ?>">
                                    <?php echo htmlspecialchars($tag['name']); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Meal Times</label>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px;" id="edit_meal_times_container">
                            <label style="display: flex; align-items: center; gap: 5px; padding: 12px; background: #fff; border: 1.5px solid #ffb877; border-radius: 8px; cursor: pointer; color: #ffb877;">
                                <input type="checkbox" name="meal_times[]" value="breakfast">
                                Breakfast
                            </label>
                            <label style="display: flex; align-items: center; gap: 5px; padding: 12px; background: #fff; border: 1.5px solid #ffb877; border-radius: 8px; cursor: pointer; color: #ffb877;">
                                <input type="checkbox" name="meal_times[]" value="lunch">
                                Lunch
                            </label>
                            <label style="display: flex; align-items: center; gap: 5px; padding: 12px; background: #fff; border: 1.5px solid #ffb877; border-radius: 8px; cursor: pointer; color: #ffb877;">
                                <input type="checkbox" name="meal_times[]" value="snack">
                                Snacks
                            </label>
                            <label style="display: flex; align-items: center; gap: 5px; padding: 12px; background: #fff; border: 1.5px solid #ffb877; border-radius: 8px; cursor: pointer; color: #ffb877;">
                                <input type="checkbox" name="meal_times[]" value="dinner">
                                Dinner
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" onclick="closeModal('editFoodItemModal')" style="border: 1.5px solid #ffb877; color: #ffb877; background: #fff;">Cancel</button>
                        <button type="submit" name="edit_food_item" class="btn btn-primary" style="background: #ffb877; color: #fff;">Update Food Item</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Meal Plan Modal -->
    <div id="editMealPlanModal" class="modal">
        <div class="modal-content" style="max-width: 1200px;">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-calendar-edit" style="color: var(--accent-orange);"></i>
                    Edit Weekly Meal Plan
                </h3>
                <button class="close-modal" onclick="closeModal('editMealPlanModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" action="" id="editMealPlanForm">
                    <input type="hidden" name="plan_id" id="edit_plan_id">
                    <div style="background: var(--bg-card); padding: 20px; border-radius: 10px; margin-bottom: 25px;">
                        <h4 style="color: var(--text-primary); margin-bottom: 15px; display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-info-circle" style="color: var(--accent-blue);"></i>
                            Plan Details
                        </h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="edit_plan_name" class="form-label">Plan Name *</label>
                                <input type="text" id="edit_plan_name" name="plan_name" class="form-control" required placeholder="Enter meal plan name">
                            </div>
                            <div class="form-group">
                                <label for="edit_type_id" class="form-label">Plan Type *</label>
                                <select id="edit_type_id" name="type_id" class="form-control" required>
                                    <option value="">Select Plan Type</option>
                                    <?php foreach ($meal_plan_types as $type): ?>
                                        <?php if (in_array($type['name'], ['Diabetic', 'Gluten-Free', 'High-Protein', 'Low-Carb', 'Non-Vegetarian', 'Vegetarian'])): ?>
                                            <option value="<?php echo $type['id']; ?>"><?php echo htmlspecialchars($type['name']); ?></option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div style="background: var(--bg-card); padding: 20px; border-radius: 10px;">
                        <h4 style="color: var(--text-primary); margin-bottom: 15px; display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-calendar-week" style="color: var(--accent-purple);"></i>
                            Weekly Schedule
                        </h4>
                        <div id="edit_day_grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;"></div>
                    </div>
                    <input type="hidden" name="meals" id="edit_mealsJson">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('editMealPlanModal')">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" name="edit_meal_plan" class="btn btn-primary" form="editMealPlanForm">
                    <i class="fas fa-save"></i> Update Meal Plan
                </button>
            </div>
        </div>
    </div>

    <script>
    // Modal open/close logic
    function openModal(modalId) {
        var modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    }
    function closeModal(modalId) {
        var modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('active');
            document.body.style.overflow = 'auto';
        }
    }

    // Load food items in dropdowns based on plan type and meal time (AJAX)
    function loadFoodItems() {
        var typeId = document.getElementById('type_id') ? document.getElementById('type_id').value : '';
        if (!typeId) return;
        var selects = document.querySelectorAll('.food-item-select');
        selects.forEach(function(select) {
            var mealTime = select.dataset.mealTime;
            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'get_food_items.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function() {
                if (this.status === 200) {
                    try {
                        var foodItems = JSON.parse(this.responseText);
                        select.innerHTML = '<option value="">Select Food Item</option>';
                        if (foodItems && foodItems.length > 0) {
                            foodItems.forEach(function(item) {
                                var option = document.createElement('option');
                                option.value = item.id;
                                option.textContent = item.name;
                                select.appendChild(option);
                            });
                        }
                    } catch (e) {
                        console.error('Error parsing response:', e);
                    }
                }
            };
            xhr.onerror = function() {
                console.error('Network error occurred');
            };
            var data = 'type_id=' + typeId + '&meal_time=' + mealTime;
            xhr.send(data);
        });
    }

    // Auto-generate meal plan name when type is selected
    var typeIdElem = document.getElementById('type_id');
    if (typeIdElem) {
        typeIdElem.addEventListener('change', function() {
            var planNameInput = document.getElementById('plan_name');
            var typeSelect = document.getElementById('type_id');
            var selectedOption = typeSelect.options[typeSelect.selectedIndex];
            if (planNameInput && selectedOption.value) {
                var existingPlans = [];
                <?php 
                $stmt = $conn->prepare("SELECT name FROM weekly_meal_plans");
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    echo "existingPlans.push('" . addslashes($row['name']) . "');\n";
                }
                ?>
                var planNumber = 1;
                while (existingPlans.includes('Plan-' + planNumber)) {
                    planNumber++;
                }
                planNameInput.value = 'Plan-' + planNumber;
            }
        });
    }

    // Edit Food Item: fetch and populate modal
    function editFoodItem(id) {
        fetch(`get_food_item.php?id=${id}`)
            .then(function(response) { return response.json(); })
            .then(function(data) {
                document.getElementById('edit_food_item_id').value = data.id;
                document.getElementById('edit_name').value = data.name;
                // Set tags
                var tagCheckboxes = document.querySelectorAll('#edit_tags_container input[type="checkbox"]');
                tagCheckboxes.forEach(function(checkbox) {
                    checkbox.checked = data.tags.includes(parseInt(checkbox.value));
                });
                // Set meal times
                var mealTimeCheckboxes = document.querySelectorAll('#edit_meal_times_container input[type="checkbox"]');
                mealTimeCheckboxes.forEach(function(checkbox) {
                    checkbox.checked = data.meal_times.includes(checkbox.value);
                });
                openModal('editFoodItemModal');
            });
    }
    // Delete Food Item: confirm and submit
    function deleteFoodItem(id) {
        if (confirm('Are you sure you want to delete this food item?')) {
            var form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="food_item_id" value="${id}">
                <input type="hidden" name="delete_food_item" value="1">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }

    // Expand/collapse logic for meal plan cards
    document.addEventListener('DOMContentLoaded', function() {
        var mealPlanCards = document.querySelectorAll('.meal-plan-card');
        mealPlanCards.forEach(function(card) {
            var header = card.querySelector('.meal-plan-header');
            var details = card.querySelector('.meal-plan-details');
            var chevron = card.querySelector('.chevron');
            header.addEventListener('click', function() {
                var isOpen = details.style.display === 'block';
                document.querySelectorAll('.meal-plan-details').forEach(function(d) { d.style.display = 'none'; });
                document.querySelectorAll('.chevron').forEach(function(c) { c.style.transform = 'rotate(0deg)'; });
                if (!isOpen) {
                    details.style.display = 'block';
                    chevron.style.transform = 'rotate(90deg)';
                }
            });
        });
    });

    // Edit Meal Plan Modal logic
    function editPlan(id) {
        fetch(`get_meal_plan.php?id=${id}`)
            .then(function(response) { return response.json(); })
            .then(function(data) {
                document.getElementById('edit_plan_id').value = data.id;
                document.getElementById('edit_plan_name').value = data.name;
                document.getElementById('edit_type_id').value = data.type_id;
                // Build the day grid
                var dayGrid = document.getElementById('edit_day_grid');
                dayGrid.innerHTML = '';
                var days = ['saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
                var mealTimes = ['breakfast', 'lunch', 'snack', 'dinner'];
                days.forEach(function(day) {
                    var dayDiv = document.createElement('div');
                    dayDiv.style.background = 'white';
                    dayDiv.style.border = '2px solid var(--border-primary)';
                    dayDiv.style.borderRadius = '12px';
                    dayDiv.style.padding = '20px';
                    dayDiv.style.boxShadow = '0 2px 8px rgba(0,0,0,0.05)';
                    var header = document.createElement('div');
                    header.style.display = 'flex';
                    header.style.alignItems = 'center';
                    header.style.gap = '10px';
                    header.style.marginBottom = '15px';
                    header.style.paddingBottom = '10px';
                    header.style.borderBottom = '2px solid var(--border-primary)';
                    var h4 = document.createElement('h4');
                    h4.textContent = day.charAt(0).toUpperCase() + day.slice(1);
                    h4.style.color = 'var(--text-primary)';
                    h4.style.margin = '0';
                    h4.style.fontSize = '1.1rem';
                    header.appendChild(h4);
                    dayDiv.appendChild(header);
                    mealTimes.forEach(function(mealTime) {
                        var label = document.createElement('label');
                        label.style.display = 'block';
                        label.style.fontWeight = '500';
                        label.style.color = 'var(--text-secondary)';
                        label.style.marginBottom = '5px';
                        label.style.fontSize = '0.9rem';
                        label.textContent = mealTime.charAt(0).toUpperCase() + mealTime.slice(1);
                        var select = document.createElement('select');
                        select.className = 'food-item-select';
                        select.name = `meals[${day}][${mealTime}]`;
                        select.dataset.day = day;
                        select.dataset.mealTime = mealTime;
                        select.style.width = '100%';
                        select.style.padding = '8px 12px';
                        select.style.border = '1px solid var(--border-primary)';
                        select.style.borderRadius = '6px';
                        select.style.fontSize = '0.85rem';
                        select.style.background = 'white';
                        var option = document.createElement('option');
                        option.value = '';
                        option.textContent = 'Select Food Item';
                        select.appendChild(option);
                        // Fill options from PHP food_items
                        <?php foreach ($food_items as $item): ?>
                            var opt = document.createElement('option');
                            opt.value = '<?php echo $item['id']; ?>';
                            opt.textContent = '<?php echo htmlspecialchars($item['name']); ?>';
                            select.appendChild(opt);
                        <?php endforeach; ?>
                        // Set value if exists
                        var found = data.meals.find(function(m) { return m.day === day && m.meal_time === mealTime; });
                        if (found) select.value = found.food_item_id;
                        dayDiv.appendChild(label);
                        dayDiv.appendChild(select);
                    });
                    dayGrid.appendChild(dayDiv);
                });
                openModal('editMealPlanModal');
            });
    }
    // Handle create meal plan form submission
    document.getElementById('createMealPlanForm').addEventListener('submit', function(e) {
        var meals = [];
        document.querySelectorAll('.food-item-select').forEach(function(select) {
            var day = select.dataset.day;
            var mealTime = select.dataset.mealTime;
            var foodId = select.value;
            if (foodId) {
                meals.push({
                    day: day,
                    meal_time: mealTime,
                    food_item_id: parseInt(foodId)
                });
            }
        });
        document.getElementById('mealsJson').value = JSON.stringify(meals);
    });
    // Handle edit meal plan form submission
    document.getElementById('editMealPlanForm').addEventListener('submit', function(e) {
        var meals = [];
        document.querySelectorAll('#edit_day_grid .food-item-select').forEach(function(select) {
            var day = select.dataset.day;
            var mealTime = select.dataset.mealTime;
            var foodId = select.value;
            if (foodId) {
                meals.push({
                    day: day,
                    meal_time: mealTime,
                    food_item_id: parseInt(foodId)
                });
            }
        });
        document.getElementById('edit_mealsJson').value = JSON.stringify(meals);
    });
    // Delete Meal Plan
    function deletePlan(id) {
        if (confirm('Are you sure you want to delete this meal plan?')) {
            var form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="plan_id" value="${id}">
                <input type="hidden" name="delete_meal_plan" value="1">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }
    </script>
</body>
</html>
