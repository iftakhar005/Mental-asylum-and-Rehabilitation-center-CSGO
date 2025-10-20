<?php
/**
 * CONFIGURATION FILE FOR MULTI-DEVICE LOGIN SUPPORT
 * This file contains configuration for database, session, and security settings
 */

// ================================
// DATABASE CONFIGURATION
// ================================

// For LOCALHOST (Same Computer)
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'asylum_db');

// For NETWORK ACCESS (Other Devices on Same Network)
// Uncomment and configure if accessing from other devices:
// define('DB_HOST', '192.168.1.100'); // Replace with your server's IP address
// define('DB_USER', 'root');
// define('DB_PASS', '');
// define('DB_NAME', 'asylum_db');

// For REMOTE/CLOUD ACCESS
// Uncomment and configure if using cloud database:
// define('DB_HOST', 'your-cloud-server.com');
// define('DB_USER', 'your_username');
// define('DB_PASS', 'your_password');
// define('DB_NAME', 'asylum_db');

// ================================
// SESSION CONFIGURATION
// ================================

// Session cookie configuration for multi-device support
ini_set('session.cookie_lifetime', 86400); // 24 hours
ini_set('session.gc_maxlifetime', 86400);  // 24 hours
ini_set('session.cookie_httponly', 1);     // Prevent JavaScript access
ini_set('session.use_only_cookies', 1);    // Only use cookies, not URL parameters

// Security headers for cookies
// For LOCALHOST development (HTTP allowed)
if ($_SERVER['SERVER_NAME'] === 'localhost' || $_SERVER['SERVER_ADDR'] === '127.0.0.1') {
    ini_set('session.cookie_secure', 0);    // Allow HTTP for development
    ini_set('session.cookie_samesite', 'Lax');
} else {
    // For PRODUCTION (HTTPS required)
    ini_set('session.cookie_secure', 1);    // HTTPS only
    ini_set('session.cookie_samesite', 'Strict');
}

// Set cookie domain and path for network access
// For localhost (default)
ini_set('session.cookie_domain', '');
ini_set('session.cookie_path', '/');

// For network access (uncomment and set your domain/IP)
// ini_set('session.cookie_domain', '.yourdomain.com'); // Allow all subdomains
// OR
// ini_set('session.cookie_domain', '192.168.1.100'); // Specific IP

// ================================
// SECURITY CONFIGURATION
// ================================

// Session fingerprint validation mode
// Options:
//   'strict'   - IP + User Agent must match exactly (single device only)
//   'moderate' - User Agent only (allow IP changes for mobile users)
//   'relaxed'  - No fingerprint validation (allow multiple devices)
define('FINGERPRINT_MODE', 'moderate'); // CHANGE THIS TO ALLOW MULTI-DEVICE

// Rate limiting configuration
define('MAX_LOGIN_ATTEMPTS', 5);        // Maximum failed login attempts
define('LOGIN_LOCKOUT_DURATION', 900);  // Lockout duration in seconds (15 minutes)
define('BAN_DURATION', 1800);           // Ban duration in seconds (30 minutes)

// Session timeout configuration
define('SESSION_LIFETIME', 28800);      // 8 hours
define('SESSION_ROTATION_INTERVAL', 1800); // Rotate session ID every 30 minutes

// ================================
// MULTI-DEVICE SETTINGS
// ================================

// Allow concurrent logins from multiple devices
define('ALLOW_CONCURRENT_SESSIONS', true); // Set to false to allow only one active session

// Maximum number of concurrent sessions per user
define('MAX_CONCURRENT_SESSIONS', 3); // Maximum devices that can be logged in simultaneously

// Device trust configuration
define('REMEMBER_DEVICE_DURATION', 2592000); // 30 days

// ================================
// TIMEZONE CONFIGURATION
// ================================
date_default_timezone_set('Asia/Manila'); // Change to your timezone

// ================================
// ERROR REPORTING (DEVELOPMENT)
// ================================
if ($_SERVER['SERVER_NAME'] === 'localhost') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// ================================
// APPLICATION SETTINGS
// ================================
define('APP_NAME', 'Mental Health Asylum Management System');
define('APP_VERSION', '1.0.0');
define('SUPPORT_EMAIL', 'support@asylum-system.com');

?>
