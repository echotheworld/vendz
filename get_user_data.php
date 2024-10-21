<?php
session_start();
require __DIR__.'/vendor/autoload.php';
use Kreait\Firebase\Factory;

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

try {
    $factory = (new Factory)
        ->withServiceAccount(__DIR__ . '/dbvending-1b336-firebase-adminsdk-m26i6-688c7d0c77.json')
        ->withDatabaseUri('https://dbvending-1b336-default-rtdb.firebaseio.com');

    $database = $factory->createDatabase();
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