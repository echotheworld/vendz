<?php
session_start();
require __DIR__.'/vendor/autoload.php';
require_once __DIR__ . '/firebase-init.php';

// Use the global $database variable
global $database;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['password'])) {
    $password = $_POST['password'];
    $userId = $_SESSION['user_id'];

    // Fetch user data from Firebase
    $usersRef = $database->getReference('tables/user');
    $snapshot = $usersRef->getSnapshot();
    $users = $snapshot->getValue();

    $user = null;
    foreach ($users as $key => $userData) {
        if ($userData['user_id'] === $userId) {
            $user = $userData;
            break;
        }
    }

    if ($user) {
        if (password_verify($password, $user['user_pass'])) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false]);
        }
    } else {
        echo json_encode(['success' => false]);
    }
} else {
    echo json_encode(['success' => false]);
}