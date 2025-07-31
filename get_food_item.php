<?php
require_once 'db.php';
$id = intval($_GET['id']);
$item = $conn->query("SELECT * FROM food_items WHERE id = $id")->fetch_assoc();
$tags = [];
$meal_times = [];
if ($item) {
    $tag_rows = $conn->query("SELECT tag_id FROM food_item_tags WHERE food_item_id = $id");
    while ($row = $tag_rows->fetch_assoc()) $tags[] = (int)$row['tag_id'];
    $meal_rows = $conn->query("SELECT meal_time FROM food_item_meal_times WHERE food_item_id = $id");
    while ($row = $meal_rows->fetch_assoc()) $meal_times[] = $row['meal_time'];
}
echo json_encode([
    'id' => $item['id'],
    'name' => $item['name'],
    'tags' => $tags,
    'meal_times' => $meal_times
]); 