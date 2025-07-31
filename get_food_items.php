<?php
session_start();
require_once 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) && !isset($_SESSION['staff_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Get type_id and meal_time from POST data
$type_id = isset($_POST['type_id']) ? intval($_POST['type_id']) : 0;
$meal_time = isset($_POST['meal_time']) ? $_POST['meal_time'] : '';

// Debug log
error_log("Type ID: " . $type_id . ", Meal Time: " . $meal_time);

if ($type_id && $meal_time) {
    // Get the type name for the given type_id
    $type_stmt = $conn->prepare("SELECT name FROM meal_plan_types WHERE id = ?");
    $type_stmt->bind_param("i", $type_id);
    $type_stmt->execute();
    $type_result = $type_stmt->get_result();
    $type_row = $type_result->fetch_assoc();
    $type_name = $type_row ? $type_row['name'] : '';
    $type_stmt->close();

    error_log("Type Name: " . $type_name);

    if ($type_name) {
        // Find tag id for this type name
        $tag_stmt = $conn->prepare("SELECT id FROM meal_tags WHERE name = ?");
        $tag_stmt->bind_param("s", $type_name);
        $tag_stmt->execute();
        $tag_result = $tag_stmt->get_result();
        $tag_row = $tag_result->fetch_assoc();
        $tag_id = $tag_row ? $tag_row['id'] : 0;
        $tag_stmt->close();

        error_log("Tag ID: " . $tag_id);

        if ($tag_id) {
            // Get food items that match both the tag and meal time
            $query = "SELECT f.id, f.name, t.name as tag_name, mt.meal_time
                     FROM food_items f
                     JOIN food_item_tags ft ON f.id = ft.food_item_id
                     JOIN meal_tags t ON ft.tag_id = t.id
                     JOIN food_item_meal_times mt ON f.id = mt.food_item_id
                     WHERE t.id = ? AND mt.meal_time = ?
                     ORDER BY f.name";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param("is", $tag_id, $meal_time);
            $stmt->execute();
            $result = $stmt->get_result();
            $food_items = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            error_log("Found " . count($food_items) . " food items");
            error_log("Food Items: " . json_encode($food_items));

            echo json_encode($food_items);
            exit();
        }
    }
}

// If no results found or invalid input, return empty array
echo json_encode([]); 