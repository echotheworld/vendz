<?php
session_start();
require __DIR__.'/vendor/autoload.php';
require_once __DIR__ . '/firebase-init.php';

// Use the global $database variable
global $database;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['otp'])) {
    $enteredOTP = $_POST['otp'];

    // Fetch stored OTP from Firebase
    $otpRef = $database->getReference('otp/' . $_SESSION['user_id']);
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
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}