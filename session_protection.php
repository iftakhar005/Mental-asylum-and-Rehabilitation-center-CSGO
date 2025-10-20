<?php
/**
 * SESSION PROTECTION
 * Include this file at the top of every protected page
 * 
 * Provides:
 * 1. Session hijacking detection
 * 2. Privilege escalation prevention
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include required files
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security_manager.php';

// Initialize security manager if not already done
if (!isset($securityManager)) {
    $securityManager = new MentalHealthSecurityManager($conn);
}

/**
 * Protect page with session validation
 */
function protectPage($required_role = null) {
    global $securityManager;
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        header('Location: index.php');
        exit();
    }
    
    // Validate session integrity (detect hijacking)
    if (!$securityManager->validateSessionIntegrity()) {
        // Session hijacking detected - destroy session and redirect
        session_unset();
        session_destroy();
        header('Location: index.php?error=session_invalid');
        exit();
    }
    
    // Validate role access (prevent privilege escalation)
    if ($required_role !== null) {
        if (!$securityManager->validateRoleAccess($required_role)) {
            // Privilege escalation detected - destroy session and redirect
            session_unset();
            session_destroy();
            header('Location: index.php?error=unauthorized_access');
            exit();
        }
    }
}

/**
 * Quick protect - just validates session without specific role
 */
function quickProtect() {
    protectPage(null);
}

/**
 * Enforce specific role access
 */
function enforceRole($required_role) {
    protectPage($required_role);
}
?>
