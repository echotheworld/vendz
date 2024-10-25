<?php
require __DIR__.'/vendor/autoload.php';
require_once __DIR__ . '/firebase-init.php';

session_start();

// Use the global $database variable
global $database;

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['btnLogin'])) {
        $user_id = trim($_POST['user_id']);
        $password = trim($_POST['user_pass']);

        // Fetch user data from Firebase
        $users = $database->getReference('tables/user')->getValue();
        $user = null;

        foreach ($users as $userId => $userData) {
            if ($userData['user_id'] === $user_id) {
                $user = $userData;
                $user['user_id'] = $userId;
                break;
            }
        }

        if ($user) {
            // User ID exists, check password
            if ($user['user_pass'] === '@4dmin_HC!') {
                // This is the initial password, let's hash it and update Firebase
                $hashedPassword = password_hash('@4dmin_HC!', PASSWORD_DEFAULT);
                $database->getReference('tables/user/' . $user['user_id'] . '/user_pass')->set($hashedPassword);
                $user['user_pass'] = $hashedPassword;
            }

            if (password_verify($password, $user['user_pass'])) {
                // Password is correct
                $_SESSION['user_id'] = $user_id;
                
                // Check if it's the first login
                if (isset($user['first_login']) && $user['first_login'] === true) {
                    // Redirect to update info page
                    header("Location: updateinfo.php");
                    exit();
                } else {
                    // Regular login, go to dashboard
                    header("Location: dash.php");
                    exit();
                }
            } else {
                // Password is incorrect
                $error = "Login failed. Please check the credentials provided with your system.";
            }
        } else {
            // User ID does not exist
            $error = "Login failed. Please check the credentials provided with your system.";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Login</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" type="text/css" href="vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="fonts/font-awesome-4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" type="text/css" href="fonts/Linearicons-Free-v1.0.0/icon-font.min.css">
    <link rel="stylesheet" type="text/css" href="css/main1.css">
    <style>
        .error-message {
            color: #d9534f;
            font-size: 0.9em;
            margin-bottom: 10px;
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
        .logo-container {
            text-align: center;
            margin-bottom: 10px;
        }
        .logo-container img {
            max-width: 150px;
            height: auto;
        }
        .login-title {
            text-align: center;
            margin-bottom: 50px;
            font-weight: bold;
        }
        .login100-form-btn {
            font-size: 15px;
            line-height: 1.5;
            color: #fff;
            text-transform: uppercase;
            width: 100%;
            height: 50px;
            border-radius: 25px;
            background: #219130;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 0 25px;
            transition: all 0.4s;
            border: none;
        }
        .login100-form-btn:hover {
            background: #f6a400; /* Changed hover color to warm orange */
        }
        .forget-password {
            font-size: 14px;
            color: #6c757d;
            transition: color 0.3s;
            text-decoration: none;
        }

        .forget-password:hover {
            color: #219130;
            text-decoration: none;
        }

        .text-right {
            text-align: right;
        }

        .p-t-12 {
            padding-top: 12px;
        }

        .p-b-20 {
            padding-bottom: 20px;
        }

        .modal-content.wrap-login100 {
            width: 100%;
            max-width: 400px; /* Increased width */
            margin: 0 auto;
            padding: 1.5rem 2rem; /* Adjusted padding */
            border-radius: 10px;
            background-color: #fff;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .modal-header {
            border-bottom: none;
            padding-bottom: 0;
        }

        .modal-title.login-title {
            font-weight: bold;
            margin-bottom: 15px; /* Reduced margin */
            font-size: 1.25rem; /* Slightly smaller font size */
        }

        #resetPasswordModal .input100 {
            font-size: 15px;
            line-height: 1.5;
            color: #666666;
            display: block;
            width: 100%;
            background: #e6e6e6;
            height: 45px; /* Slightly reduced height */
            border-radius: 22px; /* Adjusted for new height */
            padding: 0 20px 0 45px; /* Adjusted padding */
        }

        #resetPasswordModal .symbol-input100 {
            font-size: 15px;
            display: flex;
            align-items: center;
            position: absolute;
            border-radius: 22px; /* Adjusted for new height */
            bottom: 0;
            left: 0;
            width: 100%;
            height: 100%;
            padding-left: 15px;
            pointer-events: none;
            color: #666666;
            transition: all 0.4s;
        }

        #resetPasswordModal .login100-form-btn {
            font-size: 15px;
            line-height: 1.5;
            color: #fff;
            text-transform: uppercase;
            width: 100%;
            height: 45px; /* Slightly reduced height */
            border-radius: 22px; /* Adjusted for new height */
            background: #219130;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 0 25px;
            transition: all 0.4s;
            border: none;
            margin-top: 15px; /* Reduced margin */
        }

        #resetPasswordModal .login100-form-btn:hover {
            background: #1e7e34;
        }

        .modal-header .close {
            padding: 0.5rem;
            margin: -0.5rem -0.5rem -0.5rem auto;
        }

        #otpSection p {
            font-size: 14px;
            color: #666;
            line-height: 1.5;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="limiter">
        <div class="container-login100"> 
            <div class="wrap-login100">
                <div class="logo-container">
                    <img src="logo1.png" alt="Logo">
                </div>
                <h3 class="login-title">LOG-IN</h3>
                <form method="post" action="" class="login100-form validate-form">
                    <div class="wrap-input100 validate-input m-b-10" data-validate="Username is required">
                        <input class="input100" type="text" name="user_id" placeholder="Username" required>
                        <span class="focus-input100"></span>
                        <span class="symbol-input100">
                            <i class="fa fa-user"></i>
                        </span>
                    </div>

                    <div class="wrap-input100 validate-input m-b-10" data-validate="Password is required">
                        <input class="input100" type="password" name="user_pass" placeholder="Password" required>
                        <span class="focus-input100"></span>
                        <span class="symbol-input100">
                            <i class="fa fa-lock"></i>
                        </span>
                    </div>

                    <?php if ($error): ?>
                        <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>

                    <div class="container-login100-form-btn p-t-10">
                        <button type="submit" name="btnLogin" class="login100-form-btn">
                            Login
                        </button>
                    </div>

                    <!-- Add the "Forget your password?" link here -->
                    <div class="text-right p-t-12 p-b-20">
                        <a class="txt2 forget-password" href="#">
                            Forget your password?
                        </a>
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
    <script src="log.js"></script>
    <!-- Add the following line to include the new reset_password.js file -->
    <script src="reset_password.js"></script>

    <!-- Add the following modal structure for the password reset process -->
    <div class="modal fade" id="resetPasswordModal" tabindex="-1" role="dialog" aria-labelledby="resetPasswordModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content wrap-login100">
                <div class="modal-header border-0">
                    <h5 class="modal-title login-title w-100 text-center" id="resetPasswordModalLabel">Reset Password</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div id="emailInputSection">
                        <div class="wrap-input100 validate-input m-b-10" data-validate="Valid email is required">
                            <input class="input100" type="email" id="resetEmail" placeholder="Enter your email" required maxlength="30">
                            <span class="focus-input100"></span>
                            <span class="symbol-input100">
                                <i class="fa fa-envelope"></i>
                            </span>
                        </div>
                        <div class="container-login100-form-btn">
                            <button id="proceedBtn" class="login100-form-btn">PROCEED</button>
                        </div>
                    </div>
                    <div id="otpSection" style="display: none;">
                        <div class="container-login100-form-btn">
                            <button id="sendOtpBtn" class="login100-form-btn">Send OTP</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</body>
</html>
