<?php
session_start();
require_once 'db.php';
require_once 'otp_functions.php';

// Check if user is in OTP verification stage
if (!isset($_SESSION['otp_verification_pending']) || !$_SESSION['otp_verification_pending']) {
    header('Location: index.php');
    exit();
}

$error_message = '';
$success_message = '';
$otp_email = $_SESSION['otp_email'] ?? '';

// Handle OTP verification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['verify_otp'])) {
        $otp = $_POST['otp'] ?? '';
        
        // Validate OTP format
        if (strlen($otp) === 6 && ctype_digit($otp)) {
            $result = verifyOTP($otp_email, $otp);
            
            if ($result['success']) {
                // OTP verified successfully - restore session
                $_SESSION['user_id'] = $_SESSION['pending_user_id'];
                $_SESSION['staff_id'] = $_SESSION['pending_staff_id'];
                $_SESSION['username'] = $_SESSION['pending_username'];
                $_SESSION['role'] = $_SESSION['pending_role'];
                
                // Clear OTP verification session variables
                unset($_SESSION['otp_verification_pending']);
                unset($_SESSION['otp_email']);
                unset($_SESSION['pending_user_id']);
                unset($_SESSION['pending_staff_id']);
                unset($_SESSION['pending_username']);
                unset($_SESSION['pending_role']);
                
                // Session is now fully authenticated - force session regeneration for security
                session_regenerate_id(true);
                
                // Redirect to appropriate dashboard
                $role = $_SESSION['role'];
                switch ($role) {
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
                    case 'nurse':
                        header('Location: nurse_dashboard.php');
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
                $error_message = $result['message'];
            }
        } else {
            $error_message = 'Please enter a valid 6-digit OTP code.';
        }
    } elseif (isset($_POST['resend_otp'])) {
        // Resend OTP
        $user_id = $_SESSION['pending_user_id'];
        $username = $_SESSION['pending_username'];
        
        $new_otp = generateOTP();
        if (storeOTP($user_id, $otp_email, $new_otp, 10)) {
            if (sendOTPEmail($otp_email, $username, $new_otp)) {
                $success_message = 'A new OTP has been sent to your email.';
            } else {
                $error_message = 'Failed to send OTP email. Please try again.';
            }
        } else {
            $error_message = 'Failed to generate new OTP. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP - MindCare System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 500px;
            width: 100%;
            padding: 40px;
            animation: slideIn 0.5s ease-out;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .header .icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
        }
        
        .header .icon i {
            font-size: 40px;
            color: white;
        }
        
        .header h1 {
            color: #333;
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .header p {
            color: #666;
            font-size: 14px;
        }
        
        .info-box {
            background: #f0f4ff;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin-bottom: 25px;
            border-radius: 5px;
        }
        
        .info-box i {
            color: #667eea;
            margin-right: 10px;
        }
        
        .info-box strong {
            display: block;
            margin-bottom: 5px;
            color: #333;
        }
        
        .otp-input-container {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-bottom: 25px;
        }
        
        .otp-input {
            width: 50px;
            height: 60px;
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            border: 2px solid #ddd;
            border-radius: 10px;
            transition: all 0.3s;
        }
        
        .otp-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .timer {
            text-align: center;
            margin-bottom: 20px;
            color: #666;
            font-size: 14px;
        }
        
        .timer i {
            color: #667eea;
            margin-right: 5px;
        }
        
        .btn {
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 15px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
        }
        
        .btn-secondary:hover {
            background: #f0f4ff;
        }
        
        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: shake 0.5s;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }
        
        .alert-error {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }
        
        .alert-success {
            background: #efe;
            color: #3c3;
            border: 1px solid #cfc;
        }
        
        .alert i {
            font-size: 20px;
        }
        
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        
        .back-link a {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
        }
        
        .back-link a:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 600px) {
            .container {
                padding: 30px 20px;
            }
            
            .otp-input {
                width: 40px;
                height: 50px;
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="icon">
                <i class="fas fa-shield-alt"></i>
            </div>
            <h1>Two-Factor Authentication</h1>
            <p>Enter the 6-digit code sent to your email</p>
        </div>
        
        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($error_message); ?></span>
            </div>
        <?php endif; ?>
        
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span><?php echo htmlspecialchars($success_message); ?></span>
            </div>
        <?php endif; ?>
        
        <div class="info-box">
            <i class="fas fa-envelope"></i>
            <strong>OTP sent to:</strong>
            <?php echo htmlspecialchars($otp_email); ?>
        </div>
        
        <form method="POST" id="otpForm">
            <div class="otp-input-container">
                <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" autocomplete="off" data-index="0">
                <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" autocomplete="off" data-index="1">
                <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" autocomplete="off" data-index="2">
                <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" autocomplete="off" data-index="3">
                <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" autocomplete="off" data-index="4">
                <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" autocomplete="off" data-index="5">
            </div>
            
            <input type="hidden" name="otp" id="otpValue">
            
            <div class="timer">
                <i class="fas fa-clock"></i>
                <span id="countdown">Code expires in: <strong id="timer">10:00</strong></span>
            </div>
            
            <button type="submit" name="verify_otp" class="btn btn-primary">
                <i class="fas fa-check"></i> Verify OTP
            </button>
        </form>
        
        <form method="POST" style="margin-top: 10px;">
            <button type="submit" name="resend_otp" class="btn btn-secondary">
                <i class="fas fa-redo"></i> Resend OTP
            </button>
        </form>
        
        <div class="back-link">
            <a href="index.php?cancel_otp=1">
                <i class="fas fa-arrow-left"></i> Back to Login
            </a>
        </div>
    </div>
    
    <script>
        // OTP Input handling
        const otpInputs = document.querySelectorAll('.otp-input');
        const otpValue = document.getElementById('otpValue');
        const otpForm = document.getElementById('otpForm');
        
        otpInputs.forEach((input, index) => {
            // Auto-focus next input
            input.addEventListener('input', (e) => {
                if (e.target.value.length === 1) {
                    if (index < otpInputs.length - 1) {
                        otpInputs[index + 1].focus();
                    }
                }
                updateOTPValue();
            });
            
            // Handle backspace
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && e.target.value === '') {
                    if (index > 0) {
                        otpInputs[index - 1].focus();
                    }
                }
            });
            
            // Only allow numbers
            input.addEventListener('keypress', (e) => {
                if (!/[0-9]/.test(e.key)) {
                    e.preventDefault();
                }
            });
            
            // Handle paste
            input.addEventListener('paste', (e) => {
                e.preventDefault();
                const pastedData = e.clipboardData.getData('text');
                if (/^\d{6}$/.test(pastedData)) {
                    pastedData.split('').forEach((char, i) => {
                        if (otpInputs[i]) {
                            otpInputs[i].value = char;
                        }
                    });
                    updateOTPValue();
                    otpInputs[5].focus();
                }
            });
        });
        
        function updateOTPValue() {
            const otp = Array.from(otpInputs).map(input => input.value).join('');
            otpValue.value = otp;
        }
        
        // Countdown timer (10 minutes)
        let timeLeft = 600; // 10 minutes in seconds
        const timerElement = document.getElementById('timer');
        
        function updateTimer() {
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            timerElement.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
            
            if (timeLeft > 0) {
                timeLeft--;
            } else {
                timerElement.textContent = 'EXPIRED';
                timerElement.style.color = '#c33';
            }
        }
        
        setInterval(updateTimer, 1000);
        
        // Auto-focus first input
        otpInputs[0].focus();
        
        // Form submission
        otpForm.addEventListener('submit', (e) => {
            updateOTPValue();
            if (otpValue.value.length !== 6) {
                e.preventDefault();
                alert('Please enter all 6 digits of the OTP code.');
            }
        });
    </script>
</body>
</html>
