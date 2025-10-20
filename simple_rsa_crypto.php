<?php
/**
 * Simple RSA Encryption/Decryption Implementation
 * 
 * NOTE: This is a TOY implementation for demonstration purposes.
 * For production use, implement proper OpenSSL-based hybrid encryption.
 */

class SimpleRSA {
    private $publicKey;
    private $privateKey;
    private $n;
    
    public function __construct() {
        // Toy RSA keys (small primes for demonstration)
        // In production, use proper key generation
        $p = 61;
        $q = 53;
        $this->n = $p * $q; // 3233
        $phi = ($p - 1) * ($q - 1); // 3120
        $this->publicKey = 17; // e
        $this->privateKey = 2753; // d (calculated)
    }
    
    /**
     * Encrypt data using simple RSA
     * 
     * @param string $data - Plain text to encrypt
     * @return string - Encrypted data (base64 encoded chunks separated by |)
     */
    public function encrypt($data) {
        if (empty($data)) {
            return $data;
        }
        
        $encrypted = [];
        $length = strlen($data);
        
        for ($i = 0; $i < $length; $i++) {
            $char = ord($data[$i]);
            $encryptedChar = $this->modPow($char, $this->publicKey, $this->n);
            $encrypted[] = $encryptedChar;
        }
        
        // Join with | and base64 encode
        return base64_encode(implode('|', $encrypted));
    }
    
    /**
     * Decrypt data encrypted with encrypt()
     * 
     * @param string $encryptedData - Encrypted string from encrypt()
     * @return string - Decrypted plain text
     */
    public function decrypt($encryptedData) {
        if (empty($encryptedData)) {
            return $encryptedData;
        }
        
        try {
            $decoded = base64_decode($encryptedData);
            if ($decoded === false) {
                return $encryptedData;
            }
            
            $chunks = explode('|', $decoded);
            $decrypted = '';
            
            foreach ($chunks as $chunk) {
                if (!is_numeric($chunk)) {
                    continue;
                }
                $decryptedChar = $this->modPow((int)$chunk, $this->privateKey, $this->n);
                $decrypted .= chr($decryptedChar);
            }
            
            return $decrypted;
        } catch (Exception $e) {
            error_log("RSA Decryption error: " . $e->getMessage());
            return $encryptedData;
        }
    }
    
    /**
     * Modular exponentiation: (base^exponent) % modulus
     * 
     * @param int $base
     * @param int $exponent
     * @param int $modulus
     * @return int
     */
    private function modPow($base, $exponent, $modulus) {
        if ($modulus === 1) {
            return 0;
        }
        
        $result = 1;
        $base = $base % $modulus;
        
        while ($exponent > 0) {
            if ($exponent % 2 === 1) {
                $result = ($result * $base) % $modulus;
            }
            $exponent = $exponent >> 1;
            $base = ($base * $base) % $modulus;
        }
        
        return $result;
    }
}

// Global RSA instance
$GLOBALS['rsa_instance'] = new SimpleRSA();

/**
 * Global wrapper to encrypt data
 * 
 * @param string $data - Data to encrypt
 * @return string - Encrypted data
 */
function rsa_encrypt($data) {
    if (empty($data)) {
        return $data;
    }
    
    if (!isset($GLOBALS['rsa_instance'])) {
        throw new Exception("RSA instance not initialized");
    }
    
    return $GLOBALS['rsa_instance']->encrypt($data);
}

/**
 * Global wrapper to decrypt data
 * 
 * @param string $encryptedData - Encrypted data
 * @return string - Decrypted data or original on failure
 */
function rsa_decrypt($encryptedData) {
    if (empty($encryptedData)) {
        return $encryptedData;
    }
    
    try {
        if (!isset($GLOBALS['rsa_instance'])) {
            throw new Exception("RSA instance not initialized");
        }
        
        return $GLOBALS['rsa_instance']->decrypt($encryptedData);
    } catch (Exception $e) {
        error_log("rsa_decrypt error: " . $e->getMessage());
        return $encryptedData;
    }
}

/**
 * Check if user role can decrypt data
 * 
 * @param string $userRole - User's role
 * @return bool - True if authorized to decrypt
 */
function can_decrypt($userRole) {
    $allowedRoles = ['admin', 'chief-staff', 'doctor', 'therapist', 'nurse'];
    return in_array(strtolower($userRole), $allowedRoles);
}

/**
 * Encrypt sensitive patient data fields
 * 
 * @param array $patient - Patient data array
 * @return array - Patient array with encrypted sensitive fields
 */
function encrypt_patient_data($patient) {
    if (!is_array($patient)) {
        return $patient;
    }
    
    // Encrypt medical_history if present
    if (isset($patient['medical_history']) && !empty($patient['medical_history'])) {
        $patient['medical_history'] = rsa_encrypt($patient['medical_history']);
    }
    
    // Encrypt current_medications if present
    if (isset($patient['current_medications']) && !empty($patient['current_medications'])) {
        $patient['current_medications'] = rsa_encrypt($patient['current_medications']);
    }
    
    return $patient;
}

/**
 * Decrypt patient data for authorized roles
 * 
 * @param array $patient - Patient data array
 * @param string $userRole - Current user's role
 * @return array - Patient array with decrypted fields or placeholders
 */
function decrypt_patient_data($patient, $userRole) {
    if (!is_array($patient)) {
        return $patient;
    }
    
    // Check authorization
    if (!can_decrypt($userRole)) {
        if (isset($patient['medical_history'])) {
            $patient['medical_history'] = '[PROTECTED - Unauthorized]';
        }
        if (isset($patient['current_medications'])) {
            $patient['current_medications'] = '[PROTECTED - Unauthorized]';
        }
        return $patient;
    }
    
    // Decrypt medical_history if present
    if (isset($patient['medical_history']) && !empty($patient['medical_history'])) {
        // Detect legacy AES JSON format
        $decoded = @json_decode($patient['medical_history'], true);
        if (is_array($decoded) && isset($decoded['ciphertext_b64'])) {
            $patient['medical_history'] = '[Legacy AES encrypted data - please re-encrypt]';
        } else {
            $patient['medical_history'] = rsa_decrypt($patient['medical_history']);
        }
    }
    
    // Decrypt current_medications if present
    if (isset($patient['current_medications']) && !empty($patient['current_medications'])) {
        // Detect legacy AES JSON format
        $decoded = @json_decode($patient['current_medications'], true);
        if (is_array($decoded) && isset($decoded['ciphertext_b64'])) {
            $patient['current_medications'] = '[Legacy AES encrypted data - please re-encrypt]';
        } else {
            $patient['current_medications'] = rsa_decrypt($patient['current_medications']);
        }
    }
    
    return $patient;
}
?>
