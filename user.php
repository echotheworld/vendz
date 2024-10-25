<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

require __DIR__.'/vendor/autoload.php';
require_once __DIR__ . '/firebase-init.php';
require_once __DIR__ . '/functions.php';

// Prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Use the global $database variable
global $database;

// Initialize $usersRef here
$usersRef = $database->getReference('tables/user');

// Fetch all users
$users = $usersRef->getValue();

// Count the number of existing users
$userCount = count($users);

// Initialize $errors array
$errors = [];

// Handle account creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['createAccount'])) {
    // Check if the user limit has been reached
    if ($userCount >= 5) {
        $errors['limit'] = 'Account creation limit reached. Maximum of 5 accounts allowed.';
    } else {
        $role = $_POST['role'];
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        $confirmPassword = trim($_POST['confirmPassword']);

        // Username validation
        if (empty($username)) {
            $errors['username'] = "Username is required.";
        } elseif (!preg_match('/^[a-zA-Z0-9]{3,15}$/', $username)) {
            $errors['username'] = "Username must be 3-15 characters long, containing only letters and numbers.";
        }

        // Password validation
        if (empty($password)) {
            $errors['password'] = "Password is required.";
        } elseif (strlen($password) < 8 || strlen($password) > 20) {
            $errors['password'] = "Password must be 8-20 characters long.";
        } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?])/', $password)) {
            $errors['password'] = "Password must contain at least one uppercase letter, one lowercase letter, one number, and one symbol.";
        }

        // Password match validation
        if ($password !== $confirmPassword) {
            $errors['confirmPassword'] = "Passwords do not match.";
        }

        if (empty($errors)) {
            // Check if username already exists
            $userExists = false;
            if ($users) {
                foreach ($users as $user) {
                    if ($user['user_id'] === $username) {
                        $userExists = true;
                        break;
                    }
                }
            }

            if ($userExists) {
                $errors['username'] = "Username already exists.";
            } else {
                // Create new user
                $newUser = [
                    'first_login' => true,
                    'u_role' => $role,
                    'user_contact' => '',
                    'user_email' => '',
                    'user_id' => $username,
                    'user_pass' => password_hash($password, PASSWORD_DEFAULT)
                ];
                $userCount++;
                addLogEntry($_SESSION['user_id'], 'Added New Account', "Added **{$username}** as **{$role}**");

                $usersRef->push($newUser);
                $_SESSION['success_message'] = "Account created successfully." . ($userCount == 5 ? "" : "");
                
                // Redirect to the same page to reload
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            }
        }
    }
}

// Handle account deletion
if (isset($_POST['deleteAccount'])) {
    header('Content-Type: application/json');
    
    try {
        $key = $_POST['key'];
        if (!$key) {
            throw new Exception("No key provided for deletion");
        }

        error_log("Attempting to delete user with key: " . $key);

        // Re-fetch the latest user data
        $users = $usersRef->getValue();
        
        error_log("Number of users fetched: " . count($users));

        $userToDelete = $users[$key] ?? null;
        
        if (!$userToDelete) {
            error_log("All user keys: " . implode(", ", array_keys($users)));
            throw new Exception("User not found for key: " . $key);
        }
        
        // Count admins
        $adminCount = 0;
        foreach ($users as $user) {
            if ($user['u_role'] === 'admin') {
                $adminCount++;
            }
        }
        
        error_log("Number of admins: " . $adminCount);

        // Inside the deleteAccount POST handler (around line 127)
addLogEntry($_SESSION['user_id'], 'Removed an Account', "Removed **{$userToDelete['user_id']}** as **{$userToDelete['u_role']}**");

        // Check if this is not the last admin
        if ($userToDelete['u_role'] !== 'admin' || $adminCount > 1) {
            $usersRef->getChild($key)->remove();
            
            // Inside the deleteAccount POST handler, before redirecting (around line 133)
            if ($userToDelete['user_id'] === $_SESSION['user_id']) {
                addLogEntry($_SESSION['user_id'], 'Account Self-Removal', "**{$_SESSION['user_id']}** removed themselves as **{$userToDelete['u_role']}**");
            }

            // Check if the user was actually deleted
            $updatedUsers = $usersRef->getValue();
            if (!isset($updatedUsers[$key])) {
                echo json_encode(['success' => true, 'message' => 'Account deleted successfully.', 'isCurrentUser' => false]);
            } else {
                throw new Exception("Failed to delete user from database");
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Cannot delete this account.']);
        }
    } catch (Exception $e) {
        error_log('Error deleting account: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
    }
    exit;
}

// Handle role update
if (isset($_POST['updateRole'])) {
    $key = $_POST['key'];
    $newRole = $_POST['newRole'];
    
    // Count admins again to ensure we have the latest count
    $adminCount = 0;
    foreach ($users as $user) {
        if ($user['u_role'] === 'admin') {
            $adminCount++;
        }
    }
    
    // Inside the updateRole POST handler (around line 165)
    $oldRole = $usersRef->getChild($key)->getChild('u_role')->getValue();
    $usersRef->getChild($key)->update(['u_role' => $newRole]);
    addLogEntry($_SESSION['user_id'], 'Changed Account Role', "Changed **{$users[$key]['user_id']}'s** role into **{$newRole}**");

    // Check if this change would remove the last admin
    if ($newRole !== 'admin' && $adminCount <= 1) {
        echo json_encode(['success' => false, 'message' => 'Cannot change the role of the last admin.']);
    } else {
        $usersRef->getChild($key)->update(['u_role' => $newRole]);
        echo json_encode(['success' => true, 'message' => 'Role updated successfully.']);
    }
    exit;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HygienexCare</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            background-color: #f4f6f9;
            color: #343a40;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            background-color: #ffffff;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1000;
            box-sizing: border-box;
        }

        .header .logo {
            display: flex;
            align-items: center;
            font-size: 24px;
            font-weight: bold;
            color: #369c43;
        }

        .header .logo-image {
            height: 50px; /* Adjust this value as needed */
            width: auto;
            margin-right: 10px;
        }

        .header .user-menu {
            display: flex;
            align-items: center;
            position: relative;
        }

        .header .user-menu .user-name {
            margin-right: 10px; 
            font-size: 18px; /* Increased from 16px */
            font-weight: 600; /* Make the font semi-bold */
            letter-spacing: 0.5px; /* Add slight letter spacing for emphasis */
            color: #369c43 ; /* Use your preferred color, this is a blue shade */
            text-transform: uppercase; /* Make the text uppercase for more emphasis */
        }

        .header .user-menu img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            cursor: pointer;
        }

        .header .user-menu .dropdown {
            display: none;
            position: absolute;
            top: 40px;
            right: 0;
            background-color: white;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
            z-index: 1;
            min-width: 160px;
            border-radius: 5px;
        }

        .header .user-menu:hover .dropdown {
            display: block;
        }

        .header .user-menu .dropdown a {
            color: #343a40;
            padding: 10px 15px;
            text-decoration: none;
            display: block;
        }

        .header .user-menu .dropdown a:hover {
            background-color: #f1f1f1;
        }

        .sidebar {
            background-color: #e9ecef;
            padding: 15px;
            height: calc(100vh - 60px);
            position: fixed;
            width: 250px;
            top: 79px;
            left: 0;
            z-index: 1001;
        }

        .sidebar a {
            color: #369c43 ;
            text-decoration: none;
            font-size: 16px;
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            padding: 10px;
            border-radius: 5px;
            background-color: #f8f9fa;
            transition: background-color 0.3s ease;
        }

        .sidebar a:hover {
            background-color: #d6d8db;
        }

        .sidebar i {
            margin-right: 10px;
            font-size: 18px;
        }

        .content {
            flex: 1;
            padding: 20px;
            margin-left: 250px;
            margin-top: 80px;
        }

        @media (max-width: 767px) {
            .menu-bar-icon {
                display: block;
                font-size: 24px;
                cursor: pointer;
                margin: 10px;
            }

            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
                width: 250px;
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .content {
                margin-left: 0;
                margin-top: 60px;
            }
        }

        /* kahit wala na to*/

        /* Main Content Styles */
        .content {
            flex: 1;
            padding: 20px;
            margin-left: 250px;
        }

        /* Card Styles */
        .card {
            background-color: #ffffff;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #e3e6f0;
            padding: 15px 20px;
        }

        .card-header h2 {
            margin: 0;
            font-size: 1.8rem;
            color: #333333;
            font-weight: 600;
        }

        .card-body {
            padding: 20px;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 15px;
        }

        .form-control {
            border-radius: 5px;
        }

        .btn-primary {
            background-color: #369c43 ;
            border-color: #369c43 ;
            transition: background-color 0.3s ease;
        }

        .btn-primary:hover {
            background-color: #0056b3;
            /* Darker blue on hover */
        }

        .btn-success {
            background-color: #28a745;
            border-color: #28a745;
            transition: background-color 0.3s ease;
        }

        .btn-success:hover {
            background-color: #218838;
            /* Darker green on hover */
        }

        /* Visible only on small screens */
        .menu-bar-icon {
            display: none;
        }

        /* Responsive Styles */
        @media (max-width: 767px) {
            .menu-bar-icon {
                display: block;
                font-size: 24px;
                cursor: pointer;
                margin: 10px;
            }

            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
                width: 250px;
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .content {
                margin-left: 0;
            }
        }

        .table-responsive {
            margin-top: 15px;
        }

        .table thead th {
            background-color: #369c43 ;
            color: #ffffff;
            font-weight: 600;
            text-align: center;
            vertical-align: middle;
            padding: 12px;
        }

        .table tbody td {
            vertical-align: middle;
            padding: 12px;
        }

        .table tbody td img {
            max-width: 100px;
            height: auto;
            display: block;
            margin: 0 auto;
        }

        .recent-transaction {
            animation: blinkHighlight 3s ease-in-out;
        }

        @keyframes blinkHighlight {
            0%, 100% { background-color: transparent; }
            50% { background-color: #fff998; }
        }

        .pagination {
            margin-top: 20px;
            text-align: right;
        }

        .pagination button {
            margin-left: 5px;
            padding: 6px 12px;
            background-color: #369c43 ;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .pagination button:hover {
            background-color: #0056b3;
        }

        .pagination span {
            margin-right: 10px;
        }

        .card-body {
            padding: 20px;
        }

        .table-responsive {
            margin-bottom: 20px;
        }

        #resetButton {
            margin-right: auto;
        }

        .pagination {
            margin-top: 0;
            margin-left: auto;
        }

        @media (max-width: 767px) {
            .d-flex.justify-content-between {
                flex-direction: column;
                align-items: flex-start;
            }

            #resetButton, .pagination {
                margin-top: 10px;
            }

            .pagination {
                align-self: flex-end;
            }
        }

        .delete-account {
            position: relative;
        }

        .delete-account[data-custom-tooltip]:hover::after {
            content: attr(data-custom-tooltip);
            position: absolute;
            bottom: -30px;
            left: 50%;
            transform: translateX(-50%);
            padding: 5px 10px;
            background-color: #333;
            color: white;
            border-radius: 4px;
            font-size: 14px;
            white-space: nowrap;
            z-index: 1;
        }

        .delete-account[data-custom-tooltip]:hover::before {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            border-width: 5px;
            border-style: solid;
            border-color: transparent transparent #333 transparent;
        }

        .alert {
            padding: 5px 10px;
            margin-bottom: 10px;
        }

        .alert p {
            margin-bottom: 0;
        }

        .alert-danger {
            background-color: #f8d7da;
            border: none;
            color: #721c24;
            padding: 8px 12px;
            margin-bottom: 15px;
            border-radius: 4px;
        }

        .alert-danger p {
            margin-bottom: 0;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <!-- Header Section -->
    <header class="header">
        <div class="logo">
            <img src="logo2.png" alt="HygienexCare Logo" class="logo-image">
            HYGIENEXCARE
        </div>
        <div class="user-menu">
            <span class="user-name">Hello, <?php echo htmlspecialchars($_SESSION['user_id']); ?>!</span>
            <img src="admin.jfif" alt="User Picture">
            <div class="dropdown">
                <a href="#" id="editProfileLink">Edit Profile</a>
                <a href="logout.php">Log-Out</a>
            </div>
        </div>
        <div class="menu-bar-icon" id="menu-bar-icon">
            <i class="fas fa-bars"></i>
        </div>
    </header>
    <div class="main-container">
        <nav class="sidebar" id="sidebar" data-user-role="<?php echo $userRole; ?>">
            <a href="dash.php" class="menu-item"><i class="fas fa-box"></i> System Status</a>
            <a href="prod.php" class="menu-item"><i class="fas fa-shopping-bag"></i> Product Inventory</a>
            <a href="trans.php" class="menu-item"><i class="fas fa-tag"></i> Transaction History</a>
            <a href="act.php" class="menu-item"><i class="fas fa-history"></i> Activity Log</a>
            <a href="user.php" class="menu-item" id="userManagementLink"><i class="fas fa-cog"></i> User Management</a>
        </nav>
        <!-- Main Content -->
        <div class="content">
            <div class="card">
                <div class="card-header">
                    <h2>User Management</h2>
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- Account Creation Card -->
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h3>Create New Account</h3>
                                </div>
                                <div class="card-body">
                                    <!-- Display success message if any -->
                                    <?php if (isset($_SESSION['success_message'])): ?>
                                        <div class="alert alert-success">
                                            <p><?php echo htmlspecialchars($_SESSION['success_message']); ?></p>
                                        </div>
                                        <?php unset($_SESSION['success_message']); // Clear the message after displaying ?>
                                    <?php endif; ?>

                                    <!-- Display errors if any -->
                                    <?php if (!empty($errors)): ?>
                                        <div class="alert alert-danger">
                                            <?php foreach ($errors as $error): ?>
                                                <p><?php echo htmlspecialchars($error); ?></p>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Account creation form -->
                                    <form method="POST" action="" id="createAccountForm">
                                        <div class="form-group">
                                            <label for="role">Role:</label>
                                            <select class="form-control" id="role" name="role">
                                                <option value="admin">Admin</option>
                                                <option value="viewer">Viewer</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="username">Username:</label>
                                            <input type="text" class="form-control" id="username" name="username" minlength="3" maxlength="15" pattern="[a-zA-Z0-9]{3,15}" required>
                                            <small class="form-text text-muted">Username must be 3-15 characters long, containing only letters and numbers.</small>
                                        </div>
                                        <div class="form-group">
                                            <label for="password">Password:</label>
                                            <input type="password" class="form-control" id="password" name="password" maxlength="20" required>
                                            <small class="form-text text-muted">Password must contain at least one uppercase letter, one lowercase letter, one number, and one symbol.</small>
                                        </div>
                                        <div class="form-group">
                                            <label for="confirmPassword">Confirm Password:</label>
                                            <input type="password" class="form-control" id="confirmPassword" name="confirmPassword" maxlength="20" required>
                                        </div>
                                        <button type="submit" class="btn btn-primary" name="createAccount">Create Account</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <!-- User List Card -->
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h3>User List</h3>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-striped text-center">
                                            <thead>
                                                <tr>
                                                    <th>Username</th>
                                                    <th>Role</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                // Calculate admin count
                                                $adminCount = 0;
                                                foreach ($users as $user) {
                                                    if ($user['u_role'] === 'admin') {
                                                        $adminCount++;
                                                    }
                                                }

                                                foreach ($users as $key => $user) {
                                                    $isLastAdmin = ($user['u_role'] === 'admin' && $adminCount === 1);

                                                    echo "<tr>";
                                                    echo "<td>" . htmlspecialchars($user['user_id']) . "</td>";
                                                    echo "<td>";
                                                    echo "<select class='form-control role-select' data-key='" . $key . "' " . ($isLastAdmin ? 'disabled' : '') . ">";
                                                    echo "<option value='admin' " . ($user['u_role'] === 'admin' ? 'selected' : '') . ">Admin</option>";
                                                    echo "<option value='viewer' " . ($user['u_role'] === 'viewer' ? 'selected' : '') . ">Viewer</option>";
                                                    echo "</select>";
                                                    echo "</td>";
                                                    echo "<td>";
                                                    echo "<button class='btn btn-sm btn-danger delete-account' data-key='" . $key . "' " . ($isLastAdmin ? 'disabled' : '') . " " . ($isLastAdmin ? "data-custom-tooltip='Cannot delete this account'" : "") . ">";
                                                    echo "Delete</button>";
                                                    echo "</td>";
                                                    echo "</tr>";
                                                }
                                                ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        $(document).ready(function() {
            // Edit role
            $('.role-select').change(function() {
                var key = $(this).data('key');
                var newRole = $(this).val();
                
                if ($(this).is(':disabled')) {
                    alert('Cannot change the role of the last admin.');
                    return;
                }
                
                if (confirm('Are you sure you want to change the role to ' + newRole + '?')) {
                    $.ajax({
                        url: 'user.php',
                        type: 'POST',
                        data: { updateRole: true, key: key, newRole: newRole },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                alert(response.message);
                                location.reload();
                            } else {
                                alert(response.message);
                            }
                        },
                        error: function() {
                            alert('An error occurred while updating the role.');
                        }
                    });
                } else {
                    // If the user cancels, revert the dropdown to its original value
                    $(this).val($(this).find('option:not(:selected)').val());
                }
            });

            // Delete account
            $('.delete-account').click(function() {
                var key = $(this).data('key');
                var username = $(this).closest('tr').find('td:first').text();
                var currentUser = '<?php echo htmlspecialchars($_SESSION['user_id']); ?>';
                console.log("Attempting to delete user with key:", key);
                
                if ($(this).is(':disabled')) {
                    alert('Cannot delete this account.');
                    return;
                }
                
                var confirmMessage = username === currentUser ?
                    'Are you sure you want to remove yourself as Administrator?' :
                    'Are you sure you want to remove ' + username + '\'s account?';
                
                if (confirm(confirmMessage)) {
                    $.ajax({
                        url: 'user.php',
                        type: 'POST',
                        data: { deleteAccount: true, key: key },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                if (username === currentUser) {
                                    alert('Your account has been deleted.');
                                    window.location.href = 'logout.php';
                                } else {
                                    alert(response.message);
                                    setTimeout(function() {
                                        location.reload(true);
                                    }, 100);
                                }
                            } else {
                                alert('Error: ' + response.message);
                            }
                        },
                        error: function(jqXHR, textStatus, errorThrown) {
                            console.error('AJAX Error:', textStatus, errorThrown);
                            console.log('Response Text:', jqXHR.responseText);
                            alert('An error occurred while deleting the account. Please check the server logs for more details.');
                        }
                    });
                }
            });

            // Username input validation and conversion to lowercase
            $('#username').on('input', function() {
                var input = $(this);
                var value = input.val().toLowerCase(); // Convert to lowercase immediately
                input.val(value); // Set the lowercase value back to the input

                // Then perform sanitization
                var sanitized = value.replace(/[^a-z0-9]/g, '').substr(0, 15);
                if (value !== sanitized) {
                    input.val(sanitized);
                }
            });

            // Password validation
            $('#password, #confirmPassword').on('input', function() {
                var input = $(this);
                var value = input.val();
                if (value.length > 20) {
                    input.val(value.substr(0, 20));
                }
            });

            // Remove form submission JavaScript validation
            $('form').off('submit');

            // Check user count and disable form if limit reached
            var userCount = <?php echo $userCount; ?>;
            if (userCount >= 5) {
                $('#createAccountForm').find('input, select, button').prop('disabled', true);
                $('#createAccountForm').append('<p class="text-danger">Account creation limit reached. Maximum of 5 accounts allowed.</p>');
            }
        });
    </script>
    <?php include 'edit_profile_modal.php'; ?>
    <script src="edit_profile.js"></script>
    <!-- Firebase SDK -->
    <script src="https://www.gstatic.com/firebasejs/8.10.0/firebase-app.js"></script>
    <script src="https://www.gstatic.com/firebasejs/8.10.0/firebase-database.js"></script>
    <!-- Reset Listener -->
    <script src="firebase-init.js"></script>
    <script src="reset_listener.js"></script>
</body>
</html>
