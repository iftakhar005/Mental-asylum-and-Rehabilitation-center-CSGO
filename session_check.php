<?php
session_start();

// Include network security (security headers, HTTPS, rate limiting)
require_once __DIR__ . '/security_network.php';

// Prevent browser from caching authenticated pages
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

function check_login($roles = []) {
    $is_api = strpos($_SERVER['REQUEST_URI'], 'get_patient_treatments.php') !== false;
    if (!isset($_SESSION['role']) || (count($roles) > 0 && !in_array($_SESSION['role'], $roles))) {
        // Log unauthorized access attempt
        log_security_event('UNAUTHORIZED_ACCESS_ATTEMPT', [
            'requested_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'required_roles' => $roles,
            'user_role' => $_SESSION['role'] ?? 'none'
        ]);
        
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