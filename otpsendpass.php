<?php
session_start();
require __DIR__.'/vendor/autoload.php';
require_once __DIR__ . '/firebase-init.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

global $database;

function generateOTP() {
    return str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    $email = trim($_POST['email']);

    // Check if email exists in Firebase
    $usersRef = $database->getReference('tables/user');
    $snapshot = $usersRef->getSnapshot();
    $userFound = false;
    $userId = null;

    foreach ($snapshot->getValue() as $id => $userData) {
        if ($userData['user_email'] === $email) {
            $userFound = true;
            $userId = $id;
            break;
        }
    }

    if ($userFound) {
        $otp = generateOTP();

        // Store OTP in Firebase
        $otpRef = $database->getReference('password_reset_otp/' . $userId);
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
            $mail->addAddress($email);

            // Content
            $mail->isHTML(true);
            $mail->Subject = "Your OTP for Password Reset";
            $mail->Body    = "Your OTP for password reset is: <b>{$otp}</b>. This OTP will expire in 5 minutes.";
            $mail->AltBody = "Your OTP for password reset is: {$otp}. This OTP will expire in 5 minutes.";

            $mail->send();
            echo json_encode(['success' => true, 'message' => 'OTP sent successfully']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => "Message could not be sent. Mailer Error: {$mail->ErrorInfo}"]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Email not found']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
