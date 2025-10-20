<?php
/**
 * Security Decryption Module
 * 
 * Provides centralized decryption functions with role-based access control
 * and audit logging for sensitive data.
 */

// Include RSA crypto functions
require_once __DIR__ . '/simple_rsa_crypto.php';

/**
 * Decrypt field if user is authorized
 * 
 * @param string $value - Potentially encrypted value
 * @param array $aad - Associated authenticated data (kept for compatibility)
 * @param array $user - Current user data (must contain 'role')
 * @param array $allowed_roles - Roles allowed to decrypt
 * @return string - Decrypted value, '[protected]' if unauthorized, or original on failure
 */
function decrypt_field_if_authorized($value, array $aad, array $user, array $allowed_roles = ['admin', 'chief-staff', 'doctor', 'therapist']) {
    // Check if user has required role
    if (!isset($user['role'])) {
        error_log("decrypt_field_if_authorized: User role not set");
        return '[protected]';
    }
    
    $userRole = strtolower($user['role']);
    $normalizedRoles = array_map('strtolower', $allowed_roles);
    
    if (!in_array($userRole, $normalizedRoles)) {
        // Log unauthorized access attempt
        error_log("Unauthorized decryption attempt by role: {$userRole}");
        return '[protected]';
    }
    
    // Attempt decryption
    try {
        $decrypted = rsa_decrypt($value);
        
        // Log successful decryption (without logging actual data)
        error_log("Decryption successful for role: {$userRole}");
        
        return $decrypted;
    } catch (Exception $e) {
        error_log("Decryption failed: " . $e->getMessage());
        return $value; // Return original on failure
    }
}

/**
 * Decrypt patient medical data fields
 * 
 * @param array $patient - Patient record
 * @param array $user - Current user data
 * @return array - Patient record with decrypted medical fields
 */
function decrypt_patient_medical_data($patient, $user) {
    if (!is_array($patient) || !isset($patient['id'])) {
        return $patient;
    }
    
    $patientId = $patient['id'];
    $aad = ['patient_id' => $patientId];
    
    // Decrypt medical_history
    if (isset($patient['medical_history']) && !empty($patient['medical_history'])) {
        $patient['medical_history'] = decrypt_field_if_authorized(
            $patient['medical_history'],
            $aad,
            $user,
            ['admin', 'chief-staff', 'doctor', 'therapist']
        );
    }
    
    // Decrypt current_medications
    if (isset($patient['current_medications']) && !empty($patient['current_medications'])) {
        $patient['current_medications'] = decrypt_field_if_authorized(
            $patient['current_medications'],
            $aad,
            $user,
            ['admin', 'chief-staff', 'doctor', 'therapist', 'nurse']
        );
    }
    
    return $patient;
}

/**
 * Decrypt treatment data fields
 * 
 * @param array $treatment - Treatment record
 * @param array $user - Current user data
 * @return array - Treatment record with decrypted fields
 */
function decrypt_treatment_data($treatment, $user) {
    if (!is_array($treatment) || !isset($treatment['id'])) {
        return $treatment;
    }
    
    $treatmentId = $treatment['id'];
    $aad = ['treatment_id' => $treatmentId];
    
    // Fields to decrypt
    $fields = [
        'therapy_sessions',
        'rehabilitation_plan',
        'crisis_intervention',
        'documentation'
    ];
    
    foreach ($fields as $field) {
        if (isset($treatment[$field]) && !empty($treatment[$field])) {
            $treatment[$field] = decrypt_field_if_authorized(
                $treatment[$field],
                $aad,
                $user,
                ['admin', 'chief-staff', 'doctor', 'therapist']
            );
        }
    }
    
    return $treatment;
}

/**
 * Decrypt health log data
 * 
 * @param array $health_log - Health log record
 * @param array $user - Current user data
 * @return array - Health log with decrypted details
 */
function decrypt_health_log_data($health_log, $user) {
    if (!is_array($health_log) || !isset($health_log['id'])) {
        return $health_log;
    }
    
    $logId = $health_log['id'];
    $aad = ['health_log_id' => $logId];
    
    // Decrypt details field
    if (isset($health_log['details']) && !empty($health_log['details'])) {
        $health_log['details'] = decrypt_field_if_authorized(
            $health_log['details'],
            $aad,
            $user,
            ['admin', 'chief-staff', 'doctor', 'therapist', 'nurse']
        );
    }
    
    return $health_log;
}

/**
 * Decrypt user data fields
 * 
 * @param array $user_data - User record
 * @param array $current_user - Current user data
 * @return array - User record with decrypted address
 */
function decrypt_user_data($user_data, $current_user) {
    if (!is_array($user_data) || !isset($user_data['id'])) {
        return $user_data;
    }
    
    $userId = $user_data['id'];
    $aad = ['user_id' => $userId];
    
    // Decrypt address (admin and chief-staff only)
    if (isset($user_data['address']) && !empty($user_data['address'])) {
        $user_data['address'] = decrypt_field_if_authorized(
            $user_data['address'],
            $aad,
            $current_user,
            ['admin', 'chief-staff']
        );
    }
    
    return $user_data;
}

/**
 * Batch decrypt records by type
 * 
 * @param array $records - Array of records to decrypt
 * @param array $user - Current user data
 * @param string $type - Type of records ('patient', 'treatment', 'health_log', 'user')
 * @return array - Array of decrypted records
 */
function batch_decrypt_records($records, $user, $type) {
    if (!is_array($records)) {
        return $records;
    }
    
    $decrypted = [];
    
    foreach ($records as $record) {
        switch ($type) {
            case 'patient':
                $decrypted[] = decrypt_patient_medical_data($record, $user);
                break;
            
            case 'treatment':
                $decrypted[] = decrypt_treatment_data($record, $user);
                break;
            
            case 'health_log':
                $decrypted[] = decrypt_health_log_data($record, $user);
                break;
            
            case 'user':
                $decrypted[] = decrypt_user_data($record, $user);
                break;
            
            default:
                $decrypted[] = $record;
                error_log("Unknown record type for decryption: {$type}");
        }
    }
    
    return $decrypted;
}

/**
 * Audit log for decryption events
 * 
 * @param string $action - Action performed
 * @param array $context - Context data (do NOT include plaintext)
 */
function crypto_audit($action, array $context = []) {
    $timestamp = date('Y-m-d H:i:s');
    $user = isset($context['user']) ? $context['user'] : 'unknown';
    $role = isset($context['role']) ? $context['role'] : 'unknown';
    
    // Remove any sensitive data from context before logging
    unset($context['plaintext']);
    unset($context['decrypted']);
    
    $logMessage = sprintf(
        "[%s] CRYPTO_AUDIT: %s | User: %s | Role: %s | Context: %s",
        $timestamp,
        $action,
        $user,
        $role,
        json_encode($context)
    );
    
    error_log($logMessage);
}

/**
 * Assert user can decrypt based on role
 * 
 * @param array $user - User data
 * @param array $allowedRoles - Allowed roles
 * @throws Exception if not authorized
 */
function crypto_assert_can_decrypt(array $user, array $allowedRoles) {
    if (!isset($user['role'])) {
        http_response_code(403);
        throw new Exception("Access denied: User role not set");
    }
    
    $userRole = strtolower($user['role']);
    $normalizedRoles = array_map('strtolower', $allowedRoles);
    
    if (!in_array($userRole, $normalizedRoles)) {
        http_response_code(403);
        crypto_audit('UNAUTHORIZED_DECRYPTION_ATTEMPT', [
            'user' => $user['username'] ?? 'unknown',
            'role' => $userRole
        ]);
        throw new Exception("Access denied: Insufficient privileges");
    }
}
?>
