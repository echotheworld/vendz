<?php
session_start();

require __DIR__.'/vendor/autoload.php';
require_once __DIR__ . '/firebase-init.php';

// Use the global $database variable
global $database;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$errors = [];
$success = '';

// Fetch current user data
$usersRef = $database->getReference('tables/user');
$snapshot = $usersRef->getSnapshot();
$currentUser = null;
$userKey = null;

foreach ($snapshot->getValue() as $key => $userData) {
    if ($userData['user_id'] === $_SESSION['user_id']) {
        $currentUser = $userData;
        $userKey = $key;
        break;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['btnUpdate'])) {
        $user_id = trim($_POST['user_id']);
        $email = strtolower(trim($_POST['user_email']));
        $contact = trim($_POST['user_contact']);
        $password = trim($_POST['user_pass']);
        $confirm_password = trim($_POST['user_pass_confirm']);

        $updates = [];

        // Username validation
        if (!empty($user_id)) {
            if ($user_id === $currentUser['user_id']) {
                $errors['user_id'] = "Please enter a new username.";
            } else {
                $updates['user_id'] = $user_id;
            }
        }

        // Email validation
        if (!empty($email)) {
            $emailRegex = '/^[a-zA-Z0-9._%+-]+@(gmail\.com|yahoo\.com|cvsu\.edu\.ph|[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})$/';
            if (!preg_match($emailRegex, $email)) {
                $errors['user_email'] = "Please enter a valid email address.";
            } elseif ($email === $currentUser['user_email']) {
                $errors['user_email'] = "Please enter a new email address.";
            } else {
                $updates['user_email'] = $email;
            }
        }

        // Contact validation
        if (!empty($contact)) {
            if (!preg_match('/^\d{11}$/', $contact)) {
                $errors['user_contact'] = "Contact number must be exactly 11 digits.";
            } else {
                $updates['user_contact'] = $contact;
            }
        }

        // Password validation
        if (!empty($password)) {
            if (strlen($password) < 10 || 
                !preg_match('/[A-Z]/', $password) || 
                !preg_match('/[a-z]/', $password) || 
                !preg_match('/\d/', $password) || 
                !preg_match('/[\W_]/', $password)) {
                $errors['user_pass'] = "Password must be at least 10 characters long and include at least one uppercase letter, one lowercase letter, one number, and one special character.";
            } elseif ($password !== $confirm_password) {
                $errors['user_pass_confirm'] = "Passwords do not match!";
            } else {
                $updates['user_pass'] = password_hash($password, PASSWORD_DEFAULT);
            }
        }

        if (empty($errors) && !empty($updates)) {
            try {
                // Generate a unique token
                $token = bin2hex(random_bytes(16));
                
                // Store pending updates in Firebase
                $pendingUpdatesRef = $database->getReference('pending_updates')->push([
                    'user_key' => $userKey,
                    'updates' => $updates,
                    'token' => $token,
                    'timestamp' => time()
                ]);

                // Create verification link
                $verificationLink = "http://localhost:8000/verify_email.php?token=" . $token;

                // Send verification email using Mailtrap
                $mail = new PHPMailer(true);

                try {
                    // Server settings
                    $mail->isSMTP();
                    $mail->Host       = 'live.smtp.mailtrap.io';
                    $mail->SMTPAuth   = true;
                    $mail->Port       = 587;
                    $mail->Username   = 'api';
                    $mail->Password   = '75d727a6ae7f7146372a8774770c4bbe';
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;

                    // Recipients
                    $mail->setFrom('hello@demomailtrap.com', 'Hygienexcare'); // Updated this line
                    $mail->addAddress($updates['user_email'] ?? $currentUser['user_email']);

                    // Content
                    $mail->isHTML(true);
                    $mail->Subject = "Account Email Verification";
// HTML email body
$mail->Body = '
<!DOCTYPE html>
<html>
<head>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .text-logo {
            font-size: 32px;
            font-weight: bold;
            color: #219130;
            letter-spacing: 2px;
        }
        .title {
            font-size: 24px;
            font-weight: bold;
            color: #000;
            margin: 20px 0;
        }
        .content {
            margin-bottom: 30px;
            color: #333;
        }
        .button {
            display: inline-block;
            padding: 12px 24px;
            background-color: #219130;
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            text-align: center;
        }
        .button-container {
            text-align: center;
            margin: 30px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="text-logo">HYGIENEXCARE</div>
        </div>
        <h1 class="title">Please confirm your email address</h1>
        <div class="content">
            Welcome to HygienexCare! You have recently created an account with a new email address. Please confirm this email address by clicking the button below.
        </div>
        <div class="button-container">
            <a href="' . $verificationLink . '" class="button" style="color: #ffffff !important; background-color: #219130;">Confirm your email</a>
        </div>
    </div>
</body>
</html>';
                    $mail->AltBody = "Click this link to verify your email and update your information: {$verificationLink}";

                    if ($mail->send()) {
                        $success = "Please check your email to verify and complete the update process.";
                    } else {
                        $errors['email_send'] = "Failed to send verification email. Please try again.";
                    }
                } catch (Exception $e) {
                    $errors['process'] = "An error occurred while processing your request. Please try again. Error: " . $mail->ErrorInfo;
                }
            } catch (Exception $e) {
                $errors['process'] = "An error occurred while processing your request. Please try again. Error: " . $e->getMessage();
            }
        } elseif (empty($errors) && empty($updates)) {
            $errors['no_changes'] = "No changes were made. Please update at least one field.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Update Information</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" type="text/css" href="vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="fonts/font-awesome-4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" type="text/css" href="fonts/Linearicons-Free-v1.0.0/icon-font.min.css">
    <link rel="stylesheet" type="text/css" href="css/util.css">
    <link rel="stylesheet" type="text/css" href="css/main1.css">
    <style>
        .error-message {
            color: #d9534f;
            font-size: 0.9em;
            margin-top: 0.5rem;
        }
        .success-message {
            color: #5cb85c;
            margin-top: 1rem;
            text-align: center;
        }
        .container-login100 {
            background: url('images/background.jpg') no-repeat center center fixed;
            background-size: cover;
        }
        .wrap-login100 {
            padding: 2rem;
            border-radius: 8px;
            background-color: #fff;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .input100.error {
            border-color: #d9534f;
        }
        .wrap-input100 {
            position: relative;
        }
        .error-tooltip {
            position: absolute;
            right: -220px;
            top: 50%;
            transform: translateY(-50%);
            background-color: #d9534f;
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.9em;
            display: none;
            width: 200px;
            z-index: 1000;
        }
        .error-tooltip::before {
            content: '';
            position: absolute;
            left: -10px;
            top: 50%;
            transform: translateY(-50%);
            border-width: 5px;
            border-style: solid;
            border-color: transparent #d9534f transparent transparent;
        }
        .login100-form-btn {
            background-color: #219130;
        }
        .login100-form-btn:hover {
            background-color: #1a7226;
        }
    </style>
</head>
<body>
    <div class="limiter">
        <div class="container-login100"> 
            <div class="wrap-login100">
                <form method="post" action="" id="updateForm" class="login100-form validate-form">
                    <div class="login100-form-avatar">
                        <h3>Update Information</h3>
                    </div>

                    <?php if (!empty($errors)): ?>
                        <div class="error-message">
                            <?php 
                            foreach ($errors as $error) {
                                echo htmlspecialchars($error) . "<br>";
                            }
                            ?>
                        </div>
                    <?php endif; ?>

                    <div class="wrap-input100 validate-input m-b-10">
                        <input class="input100 <?php echo isset($errors['user_id']) ? 'error' : ''; ?>" 
                               type="text" name="user_id" id="user_id" placeholder="Enter new username" 
                               value="<?php echo isset($_POST['user_id']) ? htmlspecialchars($_POST['user_id']) : ''; ?>">
                        <span class="focus-input100"></span>
                        <span class="symbol-input100">
                            <i class="fa fa-user"></i>
                        </span>
                        <div class="error-tooltip"><?php echo isset($errors['user_id']) ? $errors['user_id'] : ''; ?></div>
                    </div>

                    <div class="wrap-input100 validate-input m-b-10">
                        <input class="input100 <?php echo isset($errors['user_email']) ? 'error' : ''; ?>" 
                               type="text" name="user_email" id="user_email" placeholder="Enter email" 
                               value="<?php echo isset($_POST['user_email']) ? htmlspecialchars($_POST['user_email']) : ''; ?>">
                        <span class="focus-input100"></span>
                        <span class="symbol-input100">
                            <i class="fa fa-envelope"></i>
                        </span>
                        <div class="error-tooltip"><?php echo isset($errors['user_email']) ? $errors['user_email'] : ''; ?></div>
                    </div>

                    <div class="wrap-input100 validate-input m-b-10">
                        <input class="input100 <?php echo isset($errors['user_contact']) ? 'error' : ''; ?>" 
                               type="text" name="user_contact" id="user_contact" placeholder="Enter mobile number" 
                               value="<?php echo isset($_POST['user_contact']) ? htmlspecialchars($_POST['user_contact']) : ''; ?>">
                        <span class="focus-input100"></span>
                        <span class="symbol-input100">
                            <i class="fa fa-phone"></i>
                        </span>
                        <div class="error-tooltip"><?php echo isset($errors['user_contact']) ? $errors['user_contact'] : ''; ?></div>
                    </div>

                    <div class="wrap-input100 validate-input m-b-10">
                        <input class="input100 <?php echo isset($errors['user_pass']) ? 'error' : ''; ?>" 
                               type="password" name="user_pass" id="user_pass" placeholder="Enter new password">
                        <span class="focus-input100"></span>
                        <span class="symbol-input100">
                            <i class="fa fa-lock"></i>
                        </span>
                        <div class="error-tooltip"><?php echo isset($errors['user_pass']) ? $errors['user_pass'] : ''; ?></div>
                    </div>

                    <div class="wrap-input100 validate-input m-b-10">
                        <input class="input100 <?php echo isset($errors['user_pass_confirm']) ? 'error' : ''; ?>" 
                               type="password" name="user_pass_confirm" id="user_pass_confirm" placeholder="Confirm new password">
                        <span class="focus-input100"></span>
                        <span class="symbol-input100">
                            <i class="fa fa-lock"></i>
                        </span>
                        <div class="error-tooltip"><?php echo isset($errors['user_pass_confirm']) ? $errors['user_pass_confirm'] : ''; ?></div>
                    </div>

                    <?php if ($success): ?>
                        <div class="success-message" id="successMessage"><?php echo htmlspecialchars($success); ?></div>
                    <?php endif; ?>

                    <div class="container-login100-form-btn p-t-10">
                        <button type="submit" name="btnUpdate" class="login100-form-btn">
                            Update
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="vendor/jquery/jquery-3.2.1.min.js"></script>
    <script src="vendor/bootstrap/js/popper.js"></script>
    <script src="vendor/bootstrap/js/bootstrap.min.js"></script>
    <script src="vendor/select2/select2.min.js"></script>
    <script src="js/main.js"></script>
    <script>
        // Real-time validation
        document.querySelectorAll('.input100').forEach(input => {
            const errorTooltip = input.parentElement.querySelector('.error-tooltip');
            
            input.addEventListener('input', function() {
                this.classList.remove('error');
                if (errorTooltip) {
                    errorTooltip.style.display = 'none';
                }
            });

            input.addEventListener('focus', function() {
                if (this.classList.contains('error') && errorTooltip) {
                    errorTooltip.style.display = 'block';
                }
            });

            input.addEventListener('blur', function() {
                if (errorTooltip) {
                    errorTooltip.style.display = 'none';
                }
            });
        });

        // Show error tooltips on page load if there are errors
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.input100.error').forEach(input => {
                const errorTooltip = input.parentElement.querySelector('.error-tooltip');
                if (errorTooltip) {
                    errorTooltip.style.display = 'block';
                }
            });
        });

        // Add this new code at the end of your script
        document.addEventListener('DOMContentLoaded', function() {
            const successMessage = document.getElementById('successMessage');
            if (successMessage) {
                setTimeout(function() {
                    window.location.href = 'login.php';
                }, 5000); // 3000 milliseconds = 3 seconds
            }
        });
    </script>
</body>
</html>
