<?php
require_once 'db.php';
$id = intval($_GET['id']);
$plan = $conn->query("SELECT * FROM weekly_meal_plans WHERE id = $id")->fetch_assoc();
$tags = [];
if ($plan) {
    $tag_rows = $conn->query("SELECT tag_id FROM weekly_meal_plan_tags WHERE plan_id = $id");
    while ($row = $tag_rows->fetch_assoc()) $tags[] = (int)$row['tag_id'];
}
$meals = [];
$meal_rows = $conn->query("SELECT day_of_week, meal_time, food_item_id FROM weekly_meal_plan_entries WHERE plan_id = $id");
while ($row = $meal_rows->fetch_assoc()) {
    $meals[] = [
        'day' => $row['day_of_week'],
        'meal_time' => $row['meal_time'],
        'food_item_id' => $row['food_item_id']
    ];
}
echo json_encode([
    'id' => $plan['id'],
    'name' => $plan['name'],
    'type_id' => $plan['type_id'],
    'tags' => $tags,
    'meals' => $meals
]); 