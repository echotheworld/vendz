<?php
session_start();
require __DIR__.'/vendor/autoload.php';
require_once __DIR__ . '/firebase-init.php';

global $database;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email']) && isset($_POST['otp'])) {
    $email = trim($_POST['email']);
    $enteredOTP = $_POST['otp'];

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
        // Fetch stored OTP from Firebase
        $otpRef = $database->getReference('password_reset_otp/' . $userId);
        $storedOTPData = $otpRef->getValue();

        if ($storedOTPData && isset($storedOTPData['otp']) && isset($storedOTPData['timestamp'])) {
            $storedOTP = $storedOTPData['otp'];
            $timestamp = $storedOTPData['timestamp'];

            // Check if OTP is not expired (5 minutes validity)
            if (time() - $timestamp <= 300) {
                if ($enteredOTP === $storedOTP) {
                    // OTP is correct
                    $otpRef->remove(); // Remove the OTP from Firebase
                    echo json_encode(['success' => true, 'message' => 'OTP verified successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Incorrect OTP']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'OTP has expired']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'No valid OTP found']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'User not found']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
