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
    $userData = null;

    foreach ($users as $key => $user) {
        if ($user['user_id'] === $currentUserId) {
            $userData = $user;
            break;
        }
    }

    if ($userData) {
        echo json_encode([
            'success' => true,
            'userData' => [
                'user_id' => $userData['user_id'],
                'user_email' => $userData['user_email'] ?? '',
                'user_contact' => $userData['user_contact'] ?? ''
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'User not found']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
