<?php
require __DIR__.'/vendor/autoload.php';
require_once __DIR__ . '/firebase-init.php';

global $database;

if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    // Fetch pending updates from Firebase
    $pendingUpdatesRef = $database->getReference('pending_updates');
    $pendingUpdates = $pendingUpdatesRef->getValue();
    
    $updateFound = false;
    
    foreach ($pendingUpdates as $key => $update) {
        if ($update['token'] === $token) {
            $updateFound = true;
            $userKey = $update['user_key'];
            $updates = $update['updates'];
            
            // Update user data in Firebase
            $userRef = $database->getReference('tables/user/' . $userKey);
            $userRef->update($updates);
            
            // Set first_login to false
            $userRef->update(['first_login' => false]);
            
            // Remove the pending update
            $pendingUpdatesRef->getChild($key)->remove();
            
            break;
        }
    }
    
    if ($updateFound) {
        // Display success message and redirect
        echo "
        <html>
        <head>
            <title>Account Verification</title>
            <script>
                alert('Account Created Successfully!');
                window.location.href = 'login.php';
            </script>
        </head>
        <body>
        </body>
        </html>
        ";
    } else {
        echo "Invalid or expired verification link.";
    }
} else {
    echo "No verification token provided.";
}
