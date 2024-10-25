<?php
require __DIR__.'/vendor/autoload.php';
require_once __DIR__ . '/firebase-init.php';

global $database;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email']) && isset($_POST['new_password'])) {
    $email = trim($_POST['email']);
    $newPassword = $_POST['new_password'];

    // Server-side password validation
    if (strlen($newPassword) < 10 || 
        !preg_match('/[A-Z]/', $newPassword) || 
        !preg_match('/[a-z]/', $newPassword) || 
        !preg_match('/\d/', $newPassword) || 
        !preg_match('/[\W_]/', $newPassword)) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 10 characters long and include at least one uppercase letter, one lowercase letter, one number, and one special character.']);
        exit;
    }

    // Find user by email
    $usersRef = $database->getReference('tables/user');
    $snapshot = $usersRef->getSnapshot();
    $userId = null;

    foreach ($snapshot->getValue() as $id => $userData) {
        if ($userData['user_email'] === $email) {
            $userId = $id;
            break;
        }
    }

    if ($userId) {
        // Hash the new password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        // Update the password in Firebase
        $userRef = $database->getReference('tables/user/' . $userId);
        $userRef->update([
            'user_pass' => $hashedPassword
        ]);

        echo json_encode(['success' => true, 'message' => 'Password updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'User not found']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
