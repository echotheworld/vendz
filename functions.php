<?php

require_once __DIR__ . '/firebase-init.php';

/**
 * Adds a new entry to the activity log.
 *
 * @param string $username The username of the user performing the action
 * @param string $action A short description of the action performed
 * @param string $details Additional details about the action
 */
function addLogEntry($username, $action, $details) {
    global $database;
    
    $logsRef = $database->getReference('activity_logs');
    
    // Set timezone to GMT+8
    date_default_timezone_set('Asia/Manila');
    
    $logEntry = [
        'username' => $username,
        'timestamp' => time(), // Store as Unix timestamp
        'action' => $action,
        'details' => $details
    ];
    
    $logsRef->push($logEntry);
}

/**
 * Formats a timestamp into a readable date and time string.
 *
 * @param int $timestamp The Unix timestamp to format
 * @return array Formatted date and time strings
 */
function formatTimestamp($timestamp) {
    // Set timezone to GMT+8
    date_default_timezone_set('Asia/Manila');
    
    // Format date as "MMM-DD-YYYY"
    $date = date('M-d-Y', $timestamp);
    
    // Format time as 12-hour format
    $time = date('h:i:s A', $timestamp);
    
    return ['date' => $date, 'time' => $time];
}

// You can add more utility functions here as needed

?>
