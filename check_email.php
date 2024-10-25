<?php
require __DIR__.'/vendor/autoload.php';
require_once __DIR__ . '/firebase-init.php';

// Use the global $database variable
global $database;

$response = ['exists' => false];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    $email = trim($_POST['email']);

    // Fetch user data from Firebase
    $users = $database->getReference('tables/user')->getValue();

    foreach ($users as $userId => $userData) {
        if (isset($userData['user_email']) && $userData['user_email'] === $email) {
            $response['exists'] = true;
            break;
        }
    }
}

header('Content-Type: application/json');
echo json_encode($response);
