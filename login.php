<?php
require __DIR__.'/vendor/autoload.php';
use Kreait\Firebase\Factory;

session_start();

// Check if the file exists and is readable
$credentialsFile = __DIR__ . '/dbvending-1b336-firebase-adminsdk-m26i6-688c7d0c77.json';
if (!file_exists($credentialsFile)) {
    die("Firebase credentials file not found: $credentialsFile");
}
if (!is_readable($credentialsFile)) {
    die("Firebase credentials file is not readable: $credentialsFile");
}

// Initialize Firebase
$factory = (new Factory)
    ->withServiceAccount($credentialsFile)
    ->withDatabaseUri('https://dbvending-1b336-default-rtdb.firebaseio.com');

$database = $factory->createDatabase();

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
    </style>
</head>
<body>
    <div class="limiter">
        <div class="container-login100"> 
            <div class="wrap-login100">
                <form method="post" action="" class="login100-form validate-form">
                    <div class="login100-form-avatar">
                        <h3>LOG-IN</h3>
                    </div>

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
</body>
</html>
