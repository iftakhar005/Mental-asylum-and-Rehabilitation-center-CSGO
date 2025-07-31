<?php
session_start();
function check_login($roles = []) {
    $is_api = strpos($_SERVER['REQUEST_URI'], 'get_patient_treatments.php') !== false;
    if (!isset($_SESSION['role']) || (count($roles) > 0 && !in_array($_SESSION['role'], $roles))) {
        if ($is_api) {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
        } else {
            header('Location: index.php');
        }
        exit();
    }
} 