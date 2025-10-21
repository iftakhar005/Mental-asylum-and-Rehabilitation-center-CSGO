<?php
// Load configuration for multi-device support
require_once __DIR__ . '/config.php';

// Start session with proper configuration
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include 2FA functions
require_once 'otp_functions.php';

// Handle OTP cancellation - clear all pending session variables
if (isset($_GET['cancel_otp']) && $_GET['cancel_otp'] == '1') {
    unset($_SESSION['otp_verification_pending']);
    unset($_SESSION['otp_email']);
    unset($_SESSION['pending_user_id']);
    unset($_SESSION['pending_staff_id']);
    unset($_SESSION['pending_username']);
    unset($_SESSION['pending_role']);
    // Redirect to clean URL
    header('Location: index.php');
    exit();
}

$login_error = '';
$show_captcha = false;
$captcha_question = '';
$securityManager = null;


try {
    include 'db.php';
    include 'security_manager.php';
    
   
    if (isset($conn) && $conn instanceof mysqli) {
        $securityManager = new MentalHealthSecurityManager($conn);
    }
} catch (Exception $e) {
    $login_error = 'System initialization error. Please contact administrator.';
    error_log('System Error: ' . $e->getMessage());
}


if ($securityManager && is_object($securityManager)) {
    $show_captcha = $securityManager->needsCaptcha();
    if ($show_captcha) {

        if (!isset($_SESSION['captcha_answer']) || !isset($_SESSION['captcha_time'])) {
            $captcha_data = $securityManager->generateCaptcha();
            $captcha_question = $captcha_data['question'];
        } else {
       
            if (!isset($_SESSION['captcha_question'])) {
             
                $captcha_data = $securityManager->generateCaptcha();
                $captcha_question = $captcha_data['question'];
            } else {
                $captcha_question = $_SESSION['captcha_question'];
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
   
    if (!$securityManager) {
        $login_error = 'Security system not available. Please contact administrator.';
    } else {
        try {

            if ($securityManager->isClientBanned()) {
                $ban_time_remaining = $securityManager->getBanTimeRemaining();
                $minutes = ceil($ban_time_remaining / 60);
                $login_error = "Account temporarily banned due to too many failed attempts. Please try again in {$minutes} minute(s).";
                $securityManager->logSecurityEvent('LOGIN_ATTEMPT_WHILE_BANNED');
            } else {
              
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

                
                if ($securityManager->detectSQLInjection($email) || $securityManager->detectSQLInjection($password)) {
                    $securityManager->logSecurityEvent('SQL_INJECTION_ATTEMPT', ['email' => $email]);
                    $login_error = 'Invalid login attempt detected.';
                    $securityManager->recordFailedLogin();
                } else {
                    
                    if ($show_captcha) {
                        $captcha_answer = $_POST['captcha_answer'] ?? '';
                        if (!$securityManager->validateCaptcha($captcha_answer)) {
                            $login_error = 'Invalid CAPTCHA answer. Please try again.';
                            $securityManager->recordFailedLogin();
                        } else {
                           
                            $login_success = processLogin($email, $password, $remember);
                            if (!$login_success) {
                                $securityManager->recordFailedLogin();
                            }
                        }
                    } else {
                       
                        $login_success = processLogin($email, $password, $remember);
                        if (!$login_success) {
                            $securityManager->recordFailedLogin();
                        }
                    }
                }
            }
        } catch (Exception $e) {
            if ($securityManager) {
                $securityManager->logSecurityEvent('LOGIN_VALIDATION_ERROR', ['error' => $e->getMessage()]);
                $securityManager->recordFailedLogin();
            }
            $login_error = 'Invalid input provided.';
        }
    }
}


function processLogin($email, $password, $remember) {
    global $securityManager, $conn, $login_error;
    
    try {
    
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
                    "SELECT user_id, two_factor_enabled FROM staff WHERE staff_id = ?",
                    [$staff_data['staff_id']],
                    's'
                );
                
                $user_row = $user_result->fetch_assoc();
                $user_id = ($user_row && !empty($user_row['user_id'])) ? $user_row['user_id'] : $staff_data['staff_id'];
                $two_factor_enabled = isset($user_row['two_factor_enabled']) ? (bool)$user_row['two_factor_enabled'] : false;
                
                // Check if 2FA is enabled for this user
                if ($two_factor_enabled) {
                    // Generate and send OTP
                    $otp = generateOTP();
                    
                    if (storeOTP($user_id, $email, $otp, 10)) {
                        if (sendOTPEmail($email, $staff_data['full_name'], $otp)) {
                            // Store pending user data in session
                            $_SESSION['otp_verification_pending'] = true;
                            $_SESSION['otp_email'] = $email;
                            $_SESSION['pending_user_id'] = $user_id;
                            $_SESSION['pending_staff_id'] = $staff_data['staff_id'];
                            $_SESSION['pending_username'] = $staff_data['full_name'];
                            $_SESSION['pending_role'] = $staff_data['role'];
                            
                            // Redirect to OTP verification page
                            header('Location: verify_otp.php');
                            exit();
                        } else {
                            $login_error = 'Failed to send OTP email. Please contact support.';
                            return false;
                        }
                    } else {
                        $login_error = 'Failed to generate OTP. Please try again.';
                        return false;
                    }
                }
                
                // No 2FA - proceed with normal login
                $_SESSION['user_id'] = $user_id;
                $_SESSION['staff_id'] = $staff_data['staff_id'];
                $_SESSION['username'] = $staff_data['full_name'];
                $_SESSION['role'] = $staff_data['role'];
                
                // Initialize propagation prevention tracking
                $securityManager->initializePropagationTracking($_SESSION['user_id'], $staff_data['role']);
                
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
                "SELECT id, username, password_hash, role, two_factor_enabled FROM users WHERE email = ?",
                [$email],
                's'
            );
            
            if ($result->num_rows === 1) {
                $user_data = $result->fetch_assoc();
                
                if (password_verify($password, $user_data['password_hash'])) {
                    // Successful login - clear failed attempts
                    $securityManager->clearFailedAttempts();
                    
                    // Check if 2FA is enabled
                    $two_factor_enabled = isset($user_data['two_factor_enabled']) ? (bool)$user_data['two_factor_enabled'] : false;
                    
                    // Check database for 2FA status if not in query
                    if (!isset($user_data['two_factor_enabled'])) {
                        $check_2fa = $securityManager->secureSelect(
                            "SELECT two_factor_enabled FROM users WHERE id = ?",
                            [$user_data['id']],
                            'i'
                        );
                        if ($check_2fa && $check_2fa->num_rows > 0) {
                            $check_row = $check_2fa->fetch_assoc();
                            $two_factor_enabled = (bool)$check_row['two_factor_enabled'];
                        }
                    }
                    
                    if ($two_factor_enabled) {
                        // Generate and send OTP
                        $otp = generateOTP();
                        
                        if (storeOTP($user_data['id'], $email, $otp, 10)) {
                            if (sendOTPEmail($email, $user_data['username'], $otp)) {
                                // Store pending user data in session
                                $_SESSION['otp_verification_pending'] = true;
                                $_SESSION['otp_email'] = $email;
                                $_SESSION['pending_user_id'] = $user_data['id'];
                                $_SESSION['pending_staff_id'] = $user_data['username'];
                                $_SESSION['pending_username'] = $user_data['username'];
                                $_SESSION['pending_role'] = $user_data['role'];
                                
                                // Redirect to OTP verification page
                                header('Location: verify_otp.php');
                                exit();
                            } else {
                                $login_error = 'Failed to send OTP email. Please contact support.';
                                return false;
                            }
                        } else {
                            $login_error = 'Failed to generate OTP. Please try again.';
                            return false;
                        }
                    }
                    
                    // No 2FA - proceed with normal login
                    $_SESSION['user_id'] = $user_data['id'];
                    $_SESSION['staff_id'] = $user_data['username'];
                    $_SESSION['username'] = $user_data['username'];
                    $_SESSION['role'] = $user_data['role'];
                    
                    // Initialize propagation prevention tracking
                    $securityManager->initializePropagationTracking($user_data['id'], $user_data['role']);
                    
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
                        case 'nurse':
                        case 'staff':
                            header('Location: staff_dashboard.php');
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

        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 420px;
            padding: 0;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.2);
            animation: slideUp 0.6s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-header {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white;
            text-align: center;
            padding: 40px 30px;
            position: relative;
            overflow: hidden;
        }

        .login-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .login-header h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 8px;
            position: relative;
            z-index: 1;
        }

        .login-header p {
            opacity: 0.9;
            font-size: 1rem;
            position: relative;
            z-index: 1;
        }

        .login-body {
            padding: 40px 30px;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-size: 14px;
            font-weight: 500;
            border: 1px solid;
            animation: fadeIn 0.3s ease-out;
        }

        .alert-danger {
            background: #fef2f2;
            color: #dc2626;
            border-color: #fecaca;
        }

        .alert-success {
            background: #f0fdf4;
            color: #16a34a;
            border-color: #bbf7d0;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .form-group {
            margin-bottom: 24px;
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
            padding: 16px 20px 16px 50px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: #ffffff;
        }

        .form-input:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
            transform: translateY(-1px);
        }

        .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            transition: color 0.3s ease;
        }

        .form-input:focus + .input-icon {
            color: #6366f1;
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
            padding: 4px;
            border-radius: 4px;
            transition: color 0.3s ease;
        }

        .password-toggle:hover {
            color: #6366f1;
        }

        .form-bottom {
            margin-top: 32px;
        }

        .checkbox-wrapper {
            display: flex;
            align-items: center;
            margin-bottom: 24px;
        }

        .custom-checkbox {
            position: relative;
            margin-right: 12px;
        }

        .custom-checkbox input {
            opacity: 0;
            position: absolute;
        }

        .checkmark {
            display: block;
            width: 20px;
            height: 20px;
            border: 2px solid #d1d5db;
            border-radius: 6px;
            transition: all 0.3s ease;
            position: relative;
        }

        .custom-checkbox input:checked + .checkmark {
            background: #6366f1;
            border-color: #6366f1;
        }

        .custom-checkbox input:checked + .checkmark::after {
            content: '';
            position: absolute;
            left: 6px;
            top: 2px;
            width: 6px;
            height: 10px;
            border: solid white;
            border-width: 0 2px 2px 0;
            transform: rotate(45deg);
        }

        .checkbox-label {
            font-size: 14px;
            color: #6b7280;
            cursor: pointer;
        }

        .captcha-group {
            margin-bottom: 24px;
        }

        .captcha-question {
            background: #f8f9fa;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 12px;
            font-weight: 600;
            color: #333;
            text-align: center;
            border: 1px solid #e9ecef;
        }

        .submit-btn {
            width: 100%;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white;
            border: none;
            padding: 16px 24px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            margin-bottom: 24px;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(99, 102, 241, 0.3);
        }

        .submit-btn:active {
            transform: translateY(0);
        }

        .btn-spinner {
            display: none;
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
        }

        .spinner {
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .signup-link {
            text-align: center;
            font-size: 14px;
            color: #6b7280;
        }

        .signup-link a {
            color: #6366f1;
            text-decoration: none;
            font-weight: 600;
        }

        .signup-link a:hover {
            text-decoration: underline;
        }

        .error-message {
            color: #dc2626;
            font-size: 12px;
            margin-top: 6px;
            display: none;
        }

        @media (max-width: 480px) {
            .login-container {
                margin: 10px;
                border-radius: 16px;
            }
            
            .login-header, .login-body {
                padding: 30px 24px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>Welcome Back</h1>
            <p>Sign in to your account to continue</p>
        </div>
        
        <div class="login-body">
            <?php if (!empty($login_error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($login_error); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['registered']) && $_GET['registered'] == '1'): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    Registration successful! Please sign in with your credentials.
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="loginForm">
                <div class="form-group">
                    <label class="form-label" for="email">Email Address</label>
                    <div class="input-wrapper">
                        <input type="email" name="email" id="email" class="form-input" placeholder="Enter your email" required>
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
                    <div class="form-group captcha-group">
                        <label class="form-label" for="captcha_answer">Security Question</label>
                        <div class="captcha-question">
                            <?php echo htmlspecialchars($captcha_question); ?>
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

    <script>
        // Password toggle functionality
        document.getElementById('togglePassword').addEventListener('click', function() {
            const password = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (password.type === 'password') {
                password.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                password.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });

        // Form submission with loading state
        document.getElementById('loginForm').addEventListener('submit', function() {
            const submitBtn = document.getElementById('submitBtn');
            const btnText = submitBtn.querySelector('.btn-text');
            const btnSpinner = submitBtn.querySelector('.btn-spinner');
            
            btnText.style.display = 'none';
            btnSpinner.style.display = 'block';
            submitBtn.disabled = true;
        });

        // Input validation
        document.getElementById('email').addEventListener('blur', function() {
            const email = this.value;
            const emailError = document.getElementById('email-error');
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (email && !emailRegex.test(email)) {
                emailError.style.display = 'block';
            } else {
                emailError.style.display = 'none';
            }
        });

        document.getElementById('password').addEventListener('blur', function() {
            const password = this.value;
            const passwordError = document.getElementById('password-error');
            
            if (password.length > 0 && password.length < 6) {
                passwordError.textContent = 'Password must be at least 6 characters';
                passwordError.style.display = 'block';
            } else {
                passwordError.style.display = 'none';
            }
        });
    </script>
</body>
</html>