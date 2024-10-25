<?php
session_start();
require __DIR__.'/vendor/autoload.php';
require_once __DIR__ . '/firebase-init.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Use the global $database variable
global $database;

// Function to generate OTP
function generateOTP() {
    return str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
}

// Get current user's email
$usersRef = $database->getReference('tables/user');
$snapshot = $usersRef->getSnapshot();
$userEmail = null;

foreach ($snapshot->getValue() as $userData) {
    if ($userData['user_id'] === $_SESSION['user_id']) {
        $userEmail = $userData['user_email'];
        break;
    }
}

if (!$userEmail) {
    echo json_encode(['success' => false, 'message' => 'User email not found']);
    exit;
}

// Generate OTP
$otp = generateOTP();

// Store OTP in Firebase
$otpRef = $database->getReference('otp/' . $_SESSION['user_id']);
$otpRef->set([
    'otp' => $otp,
    'timestamp' => time()
]);

// Send OTP via email
$mail = new PHPMailer(true);

try {
    // Server settings
    $mail->isSMTP();
    $mail->Host       = 'live.smtp.mailtrap.io';
    $mail->SMTPAuth   = true;
    $mail->Port       = 587;
    $mail->Username   = 'api';
    $mail->Password   = '75d727a6ae7f7146372a8774770c4bbe';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;

    // Recipients
    $mail->setFrom('hello@demomailtrap.com', 'Hygienexcare');
    $mail->addAddress($userEmail);

    // Content
    $mail->isHTML(true);
    $mail->Subject = "Your OTP for Profile Edit";
    $mail->Body    = "Your OTP is: <b>{$otp}</b>. This OTP will expire in 5 minutes.";
    $mail->AltBody = "Your OTP is: {$otp}. This OTP will expire in 5 minutes.";

    $mail->send();
    echo json_encode(['success' => true, 'message' => 'OTP sent successfully']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => "Message could not be sent. Mailer Error: {$mail->ErrorInfo}"]);
}