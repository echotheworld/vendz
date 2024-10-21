<?php
session_start();
require __DIR__.'/vendor/autoload.php';
require_once __DIR__ . '/firebase-init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

try {
    // Use the global $database variable
    global $database;

    $usersRef = $database->getReference('tables/user');
    $users = $usersRef->getValue();

    $currentUserId = $_SESSION['user_id'];
    $userKey = null;

    foreach ($users as $key => $user) {
        if ($user['user_id'] === $currentUserId) {
            $userKey = $key;
            break;
        }
    }

    if ($userKey) {
        $updates = [];

        // Check and update username
        if ($_POST['user_id'] !== $users[$userKey]['user_id']) {
            // Check if the new username is already taken
            $usernameExists = false;
            foreach ($users as $user) {
                if ($user['user_id'] === $_POST['user_id'] && $user !== $users[$userKey]) {
                    $usernameExists = true;
                    break;
                }
            }
            if ($usernameExists) {
                echo json_encode(['success' => false, 'message' => 'Username already exists']);
                exit;
            }
            $updates['user_id'] = $_POST['user_id'];
            $_SESSION['user_id'] = $_POST['user_id'];  // Update session with new username
        }

        // Check and update email
        if ($_POST['user_email'] !== $users[$userKey]['user_email']) {
            $updates['user_email'] = $_POST['user_email'];
        }

        // Check and update contact number
        if ($_POST['user_contact'] !== $users[$userKey]['user_contact']) {
            $updates['user_contact'] = $_POST['user_contact'];
        }

        // Update password if provided and confirmed
        if (!empty($_POST['user_pass']) && $_POST['user_pass'] === $_POST['user_pass_confirm']) {
            $updates['user_pass'] = password_hash($_POST['user_pass'], PASSWORD_DEFAULT);
        }

        if (!empty($updates)) {
            $usersRef->getChild($userKey)->update($updates);
            echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
        } else {
            echo json_encode(['success' => true, 'message' => 'No changes were made']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'User not found']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
