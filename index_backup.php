<?php
session_start();

// Initialize variables
$login_error = '';
$show_captcha = false;
$captcha_question = '';
$securityManager = null;

// Include database and security manager with error handling
try {
    include 'db.php';
    include 'security_manager.php';
    
    // Initialize security manager if database connection is successful
    if (isset($conn) && $conn instanceof mysqli) {
        $securityManager = new MentalHealthSecurityManager($conn);
    }
} catch (Exception $e) {
    $login_error = 'System initialization error. Please contact administrator.';
    error_log('System Error: ' . $e->getMessage());
}

// Check if CAPTCHA is needed (only if security manager is available)
if ($securityManager && is_object($securityManager)) {
    $show_captcha = $securityManager->needsCaptcha();
    if ($show_captcha) {
        $captcha_data = $securityManager->generateCaptcha();
        $captcha_question = $captcha_data['question'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Only process login if security manager is available
    if (!$securityManager) {
        $login_error = 'Security system not available. Please contact administrator.';
    } else {
        try {
        // Validate and sanitize inputs using security manager
        $email = $securityManager->validateInput($_POST['email'] ?? '', [
            'type' => 'email',
            'max_length' => 255,
            'required' => true
        ]);
        
        $password = $securityManager->validateInput($_POST['password'] ?? '', [
            'type' => 'string',
            'max_length' => 255,
            'required' => true,
            'allow_html' => false
        ]);
        
        $remember = isset($_POST['remember']);

        // Check for SQL injection attempts
        if ($securityManager->detectSQLInjection($email) || $securityManager->detectSQLInjection($password)) {
            $securityManager->logSecurityEvent('SQL_INJECTION_ATTEMPT', ['email' => $email]);
            $login_error = 'Invalid login attempt detected.';
            $securityManager->recordFailedLogin();
        } else {
            // Validate CAPTCHA if required
            if ($show_captcha) {
                $captcha_answer = $_POST['captcha_answer'] ?? '';
                if (!$securityManager->validateCaptcha($captcha_answer)) {
                    $login_error = 'Invalid CAPTCHA answer. Please try again.';
                    $securityManager->recordFailedLogin();
                } else {
                    // CAPTCHA passed, proceed with login
                    $login_success = processLogin($email, $password, $remember);
                    if (!$login_success) {
                        $securityManager->recordFailedLogin();
                    }
                }
            } else {
                // No CAPTCHA required, proceed with login
                $login_success = processLogin($email, $password, $remember);
                if (!$login_success) {
                    $securityManager->recordFailedLogin();
                }
            }
        }
    } catch (Exception $e) {
        $securityManager->logSecurityEvent('LOGIN_VALIDATION_ERROR', ['error' => $e->getMessage()]);
        $login_error = 'Invalid input provided.';
        $securityManager->recordFailedLogin();
    }
}

// Function to handle login process
function processLogin($email, $password, $remember) {
    global $securityManager, $conn, $login_error;
    
    try {
        // Use secure query for staff table check
        $result = $securityManager->secureSelect(
            "SELECT staff_id, full_name, password_hash, role FROM staff WHERE email = ?",
            [$email],
            's'
        );
        
        if ($result->num_rows === 1) {
            $staff_data = $result->fetch_assoc();
            
            if (password_verify($password, $staff_data['password_hash'])) {
                // Successful login - clear failed attempts
                $securityManager->clearFailedAttempts();
                
                // Look up the numeric user_id from the staff table
                $user_result = $securityManager->secureSelect(
                    "SELECT user_id FROM staff WHERE staff_id = ?",
                    [$staff_data['staff_id']],
                    's'
                );
                
                $user_row = $user_result->fetch_assoc();
                if ($user_row && !empty($user_row['user_id'])) {
                    $_SESSION['user_id'] = $user_row['user_id'];
                } else {
                    $_SESSION['user_id'] = $staff_data['staff_id'];
                }
                
                $_SESSION['staff_id'] = $staff_data['staff_id'];
                $_SESSION['username'] = $staff_data['full_name'];
                $_SESSION['role'] = $staff_data['role'];
                
                // Log successful login
                $securityManager->logSecurityEvent('SUCCESSFUL_LOGIN', [
                    'user_id' => $_SESSION['user_id'],
                    'role' => $staff_data['role']
                ]);
                
                // Redirect based on role
                switch ($staff_data['role']) {
                    case 'chief-staff':
                        header('Location: chief_staff_dashboard.php');
                        break;
                    case 'doctor':
                        header('Location: doctor_dashboard.php');
                        break;
                    case 'therapist':
                        header('Location: therapist_dashboard.php');
                        break;
                    case 'receptionist':
                        header('Location: receptionist_dashboard.php');
                        break;
                    default:
                        header('Location: staff_dashboard.php');
                        break;
                }
                exit();
            } else {
                $login_error = 'Invalid email or password.';
                return false;
            }
        } else {
            // If not found in staff table, check users table (for admin)
            $result = $securityManager->secureSelect(
                "SELECT id, username, password_hash, role FROM users WHERE email = ?",
                [$email],
                's'
            );
            
            if ($result->num_rows === 1) {
                $user_data = $result->fetch_assoc();
                
                if (password_verify($password, $user_data['password_hash'])) {
                    // Successful login - clear failed attempts
                    $securityManager->clearFailedAttempts();
                    
                    $_SESSION['user_id'] = $user_data['id'];
                    $_SESSION['staff_id'] = $user_data['username'];
                    $_SESSION['username'] = $user_data['username'];
                    $_SESSION['role'] = $user_data['role'];
                    
                    // Log successful login
                    $securityManager->logSecurityEvent('SUCCESSFUL_LOGIN', [
                        'user_id' => $_SESSION['user_id'],
                        'role' => $user_data['role']
                    ]);
                    
                    // Redirect based on role
                    switch ($user_data['role']) {
                        case 'admin':
                            header('Location: admin_dashboard.php');
                            break;
                        case 'chief-staff':
                            header('Location: chief_staff_dashboard.php');
                            break;
                        case 'doctor':
                            header('Location: doctor_dashboard.php');
                            break;
                        case 'therapist':
                            header('Location: therapist_dashboard.php');
                            break;
                        case 'receptionist':
                            header('Location: receptionist_dashboard.php');
                            break;
                        case 'relative':
                            header('Location: parent_dashboard.php');
                            break;
                        case 'general_user':
                            header('Location: guser.php');
                            break;
                        default:
                            header('Location: staff_dashboard.php');
                            break;
                    }
                    exit();
                }
            }
            
            $login_error = 'Invalid email or password.';
            return false;
        }
    } catch (Exception $e) {
        $securityManager->logSecurityEvent('LOGIN_ERROR', ['error' => $e->getMessage()]);
        $login_error = 'An error occurred during login. Please try again later.';
        return false;
    }
    
    return false;
}
?>login_error = '';
$show_captcha = false;
$captcha_question = '';

// Check if CAPTCHA is needed
if (isset($securityManager)) {
    $show_captcha = $securityManager->needsCaptcha();
    if ($show_captcha) {
        $captcha_data = $securityManager->generateCaptcha();
        $captcha_question = $captcha_data['question'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate and sanitize inputs using security manager
        $email = $securityManager->validateInput($_POST['email'] ?? '', [
            'type' => 'email',
            'max_length' => 255,
            'required' => true
        ]);
        
        $password = $securityManager->validateInput($_POST['password'] ?? '', [
            'type' => 'string',
            'max_length' => 255,
            'required' => true,
            'allow_html' => false
        ]);
        
        $remember = isset($_POST['remember']);

        // Check for SQL injection attempts
        if ($securityManager->detectSQLInjection($email) || $securityManager->detectSQLInjection($password)) {
            $securityManager->logSecurityEvent('SQL_INJECTION_ATTEMPT', ['email' => $email]);
            $login_error = 'Invalid login attempt detected.';
            $securityManager->recordFailedLogin();
        } else {
            // Validate CAPTCHA if required
            if ($show_captcha) {
                $captcha_answer = $_POST['captcha_answer'] ?? '';
                if (!$securityManager->validateCaptcha($captcha_answer)) {
                    $login_error = 'Invalid CAPTCHA answer. Please try again.';
                    $securityManager->recordFailedLogin();
                } else {
                    // CAPTCHA passed, proceed with login
                    $login_success = processLogin($email, $password, $remember);
                    if (!$login_success) {
                        $securityManager->recordFailedLogin();
                    }
                }
            } else {
                // No CAPTCHA required, proceed with login
                $login_success = processLogin($email, $password, $remember);
                if (!$login_success) {
                    $securityManager->recordFailedLogin();
                }
            }
        }
    } catch (Exception $e) {
        $securityManager->logSecurityEvent('LOGIN_VALIDATION_ERROR', ['error' => $e->getMessage()]);
        $login_error = 'Invalid input provided.';
        $securityManager->recordFailedLogin();
    }
}

// Function to handle login process
function processLogin($email, $password, $remember) {
    global $securityManager, $conn, $login_error;
    
    try {
        // Use secure query for staff table check
        $result = $securityManager->secureSelect(
            "SELECT staff_id, full_name, password_hash, role FROM staff WHERE email = ?",
            [$email],
            's'
        );
        
        if ($result->num_rows === 1) {
            $staff_data = $result->fetch_assoc();
            
            if (password_verify($password, $staff_data['password_hash'])) {
                // Successful login - clear failed attempts
                $securityManager->clearFailedAttempts();
                
                // Look up the numeric user_id from the staff table
                $user_result = $securityManager->secureSelect(
                    "SELECT user_id FROM staff WHERE staff_id = ?",
                    [$staff_data['staff_id']],
                    's'
                );
                
                $user_row = $user_result->fetch_assoc();
                if ($user_row && !empty($user_row['user_id'])) {
                    $_SESSION['user_id'] = $user_row['user_id'];
                } else {
                    $_SESSION['user_id'] = $staff_data['staff_id'];
                }
                
                $_SESSION['staff_id'] = $staff_data['staff_id'];
                $_SESSION['username'] = $staff_data['full_name'];
                $_SESSION['role'] = $staff_data['role'];
                
                // Log successful login
                $securityManager->logSecurityEvent('SUCCESSFUL_LOGIN', [
                    'user_id' => $_SESSION['user_id'],
                    'role' => $staff_data['role']
                ]);
                
                // Redirect based on role
                switch ($staff_data['role']) {
                    case 'chief-staff':
                        header('Location: chief_staff_dashboard.php');
                        break;
                    case 'doctor':
                        header('Location: doctor_dashboard.php');
                        break;
                    case 'therapist':
                        header('Location: therapist_dashboard.php');
                        break;
                    case 'receptionist':
                        header('Location: receptionist_dashboard.php');
                        break;
                    default:
                        header('Location: staff_dashboard.php');
                        break;
                }
                exit();
            } else {
                $login_error = 'Invalid email or password.';
                return false;
            }
        } else {
            // If not found in staff table, check users table (for admin)
            $result = $securityManager->secureSelect(
                "SELECT id, username, password_hash, role FROM users WHERE email = ?",
                [$email],
                's'
            );
            
            if ($result->num_rows === 1) {
                $user_data = $result->fetch_assoc();
                
                if (password_verify($password, $user_data['password_hash'])) {
                    // Successful login - clear failed attempts
                    $securityManager->clearFailedAttempts();
                    
                    $_SESSION['user_id'] = $user_data['id'];
                    $_SESSION['staff_id'] = $user_data['username'];
                    $_SESSION['username'] = $user_data['username'];
                    $_SESSION['role'] = $user_data['role'];
                    
                    // Log successful login
                    $securityManager->logSecurityEvent('SUCCESSFUL_LOGIN', [
                        'user_id' => $_SESSION['user_id'],
                        'role' => $user_data['role']
                    ]);
                    
                    // Redirect based on role
                    switch ($user_data['role']) {
                        case 'admin':
                            header('Location: admin_dashboard.php');
                            break;
                        case 'chief-staff':
                            header('Location: chief_staff_dashboard.php');
                            break;
                        case 'doctor':
                            header('Location: doctor_dashboard.php');
                            break;
                        case 'therapist':
                            header('Location: therapist_dashboard.php');
                            break;
                        case 'receptionist':
                            header('Location: receptionist_dashboard.php');
                            break;
                        case 'relative':
                            header('Location: parent_dashboard.php');
                            break;
                        case 'general_user':
                            header('Location: guser.php');
                            break;
                        default:
                            header('Location: staff_dashboard.php');
                            break;
                    }
                    exit();
                }
            }
            
            $login_error = 'Invalid email or password.';
            return false;
        }
    } catch (Exception $e) {
        $securityManager->logSecurityEvent('LOGIN_ERROR', ['error' => $e->getMessage()]);
        $login_error = 'An error occurred during login. Please try again later.';
        return false;
    }
    
    return false;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - Welcome Back</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            overflow-x: hidden;
        }

        .container {
            width: 100%;
            max-width: 420px;
            position: relative;
        }

        .form-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transform: translateY(20px);
            opacity: 0;
            animation: slideUp 0.8s ease-out forwards;
        }

        @keyframes slideUp {
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .header {
            text-align: center;
            margin-bottom: 32px;
            animation: fadeIn 1s ease-out 0.3s both;
        }

        .header h1 {
            font-size: 32px;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 8px;
        }

        .header p {
            color: #6b7280;
            font-size: 16px;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .form-group {
            margin-bottom: 24px;
            animation: slideInLeft 0.6s ease-out both;
        }

        .form-group:nth-child(2) { animation-delay: 0.1s; }
        .form-group:nth-child(3) { animation-delay: 0.2s; }

        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #374151;
            font-size: 14px;
        }

        .input-wrapper {
            position: relative;
        }

        .form-input {
            width: 100%;
            padding: 16px 16px 16px 48px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: #ffffff;
        }

        .form-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            transform: translateY(-2px);
        }

        .form-input.error {
            border-color: #ef4444;
            animation: shake 0.5s ease-in-out;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 18px;
            transition: color 0.3s ease;
        }

        .form-input:focus + .input-icon {
            color: #667eea;
        }

        .password-toggle {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #9ca3af;
            cursor: pointer;
            font-size: 18px;
            transition: color 0.3s ease;
        }

        .password-toggle:hover {
            color: #667eea;
        }

        .error-message {
            color: #ef4444;
            font-size: 12px;
            margin-top: 6px;
            opacity: 0;
            transform: translateY(-10px);
            transition: all 0.3s ease;
        }

        .error-message.show {
            opacity: 1;
            transform: translateY(0);
        }

        /* Fixed alignment section */
        .form-bottom {
            margin-top: 24px;
        }

        .checkbox-wrapper {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 24px;
            animation: slideInLeft 0.6s ease-out 0.3s both;
        }

        .custom-checkbox {
            position: relative;
            width: 18px;
            height: 18px;
            flex-shrink: 0;
        }

        .custom-checkbox input {
            opacity: 0;
            position: absolute;
        }

        .checkmark {
            position: absolute;
            top: 0;
            left: 0;
            height: 18px;
            width: 18px;
            background: #ffffff;
            border: 2px solid #e5e7eb;
            border-radius: 4px;
            transition: all 0.3s ease;
        }

        .custom-checkbox input:checked ~ .checkmark {
            background: #667eea;
            border-color: #667eea;
        }

        .checkmark:after {
            content: "";
            position: absolute;
            display: none;
            left: 5px;
            top: 1px;
            width: 5px;
            height: 9px;
            border: solid white;
            border-width: 0 2px 2px 0;
            transform: rotate(45deg);
        }

        .custom-checkbox input:checked ~ .checkmark:after {
            display: block;
            animation: checkmark 0.3s ease-in-out;
        }

        @keyframes checkmark {
            0% {
                transform: rotate(45deg) scale(0);
            }
            100% {
                transform: rotate(45deg) scale(1);
            }
        }

        .checkbox-label {
            color: #6b7280;
            cursor: pointer;
            font-size: 14px;
        }

        .submit-btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            margin-bottom: 24px;
            animation: slideInLeft 0.6s ease-out 0.4s both;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }

        .submit-btn:active {
            transform: translateY(0);
        }

        .submit-btn.loading {
            pointer-events: none;
        }

        .btn-text {
            transition: opacity 0.3s ease;
        }

        .btn-spinner {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .spinner {
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top: 2px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .submit-btn.loading .btn-text {
            opacity: 0;
        }

        .submit-btn.loading .btn-spinner {
            opacity: 1;
        }

        .signup-link {
            text-align: center;
            color: #6b7280;
            font-size: 14px;
            animation: fadeIn 1s ease-out 0.5s both;
        }

        .signup-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }

        .signup-link a:hover {
            text-decoration: underline;
        }

        .success-message {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #10b981;
            color: white;
            padding: 16px 24px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(16, 185, 129, 0.3);
            transform: translateX(400px);
            transition: transform 0.5s ease;
            z-index: 1000;
        }

        .success-message.show {
            transform: translateX(0);
        }

        .floating-shapes {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: -1;
        }

        .shape {
            position: absolute;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float 6s ease-in-out infinite;
        }

        .shape:nth-child(1) {
            width: 80px;
            height: 80px;
            top: 20%;
            left: 10%;
            animation-delay: 0s;
        }

        .shape:nth-child(2) {
            width: 120px;
            height: 120px;
            top: 60%;
            right: 10%;
            animation-delay: 2s;
        }

        .shape:nth-child(3) {
            width: 60px;
            height: 60px;
            bottom: 20%;
            left: 20%;
            animation-delay: 4s;
        }

        @keyframes float {
            0%, 100% {
                transform: translateY(0px) rotate(0deg);
            }
            50% {
                transform: translateY(-20px) rotate(180deg);
            }
        }

        @media (max-width: 768px) {
            .form-card {
                padding: 24px;
                margin: 10px;
            }

            .header h1 {
                font-size: 28px;
            }
        }
    </style>
</head>
<body>
    <div class="floating-shapes">
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
    </div>

    <div class="container">
        <div class="form-card">
            <div class="header">
                <h1>Welcome Back</h1>
                <p>Sign in to your account to continue</p>
            </div>

            <form id="signinForm" method="POST">
                <div class="form-group">
                    <label class="form-label" for="email">Email Address</label>
                    <div class="input-wrapper">
                        <input type="email" name="email" id="email" class="form-input" required>
                        <i class="fas fa-envelope input-icon"></i>
                    </div>
                    <div id="email-error" class="error-message">Please enter a valid email address</div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <div class="input-wrapper">
                        <input type="password" name="password" id="password" class="form-input" placeholder="••••••••" required>
                        <i class="fas fa-lock input-icon"></i>
                        <button type="button" class="password-toggle" id="togglePassword">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div id="password-error" class="error-message">Please enter your password</div>
                </div>

                <div class="form-bottom">
                    <div class="checkbox-wrapper">
                        <label class="custom-checkbox">
                            <input type="checkbox" name="remember" id="remember">
                            <span class="checkmark"></span>
                        </label>
                        <label for="remember" class="checkbox-label">Remember me</label>
                    </div>

                    <?php if ($show_captcha): ?>
                    <div class="form-group captcha-group" style="margin-top: 15px;">
                        <label class="form-label" for="captcha_answer">Security Question</label>
                        <div class="captcha-question" style="background: #f8f9fa; padding: 10px; border-radius: 5px; margin-bottom: 10px; font-weight: bold; color: #333;">
                            <?php echo $securityManager->preventXSS($captcha_question); ?>
                        </div>
                        <div class="input-wrapper">
                            <input type="number" name="captcha_answer" id="captcha_answer" class="form-input" placeholder="Enter your answer" required>
                            <i class="fas fa-shield-alt input-icon"></i>
                        </div>
                        <div id="captcha-error" class="error-message">Please solve the security question</div>
                    </div>
                    <?php endif; ?>

                    <button type="submit" class="submit-btn" id="submitBtn">
                        <span class="btn-text">Sign In</span>
                        <div class="btn-spinner">
                            <div class="spinner"></div>
                        </div>
                    </button>

                    <div class="signup-link">
                        Don't have an account? <a href="signup.php">Create one</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php if (!empty($login_error)): ?>
        <div style="color: #ef4444; text-align: center; margin-bottom: 16px; font-size: 15px;">
            <?php echo htmlspecialchars($login_error); ?>
        </div>
    <?php endif; ?>

    <script>
        // Password toggle functionality
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        
        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            const icon = this.querySelector('i');
            icon.classList.toggle('fa-eye');
            icon.classList.toggle('fa-eye-slash');
        });

        // Form validation
        function showError(fieldId, message) {
            const field = document.getElementById(fieldId);
            const errorDiv = document.getElementById(fieldId + '-error');
            
            field.classList.add('error');
            errorDiv.textContent = message;
            errorDiv.classList.add('show');
            
            setTimeout(() => {
                field.classList.remove('error');
            }, 500);
        }

        function hideError(fieldId) {
            const field = document.getElementById(fieldId);
            const errorDiv = document.getElementById(fieldId + '-error');
            
            field.classList.remove('error');
            errorDiv.classList.remove('show');
        }

        function validateForm() {
            let isValid = true;
            
            // Clear all errors first
            ['email', 'password'].forEach(hideError);
            
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            
            if (!email) {
                showError('email', 'Please enter your email address');
                isValid = false;
            } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                showError('email', 'Please enter a valid email address');
                isValid = false;
            }
            
            if (!password) {
                showError('password', 'Please enter your password');
                isValid = false;
            }
            
            return isValid;
        }

        // Form submission
        document.getElementById('signinForm').addEventListener('submit', function(e) {
            if (!validateForm()) {
                e.preventDefault();
                return false;
            }
            // Show loading state
            document.getElementById('submitBtn').classList.add('loading');
            return true;
        });

        // Real-time validation
        document.getElementById('email').addEventListener('blur', function() {
            const email = this.value.trim();
            if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                showError('email', 'Please enter a valid email address');
            } else if (email) {
                hideError('email');
            }
        });
    </script>
</body>
</html> 