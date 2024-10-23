<?php
error_reporting(E_ALL);
ini_set('display_errors', 0); // Change this to 0 to prevent warnings from being output

session_start();
require __DIR__.'/vendor/autoload.php';
require_once __DIR__ . '/firebase-init.php';

header('Content-Type: application/json');

// Set timezone to GMT+8 (Philippines)
date_default_timezone_set('Asia/Manila');

// Use the global $database variable
global $database;

// Reference to the activity_logs node in Firebase
$logsRef = $database->getReference('activity_logs');

// Get the category from the GET request
$category = isset($_GET['category']) ? $_GET['category'] : 'all';

// Fetch all logs
$logs = $logsRef->getValue();

// Format and filter the logs
$formattedLogs = [];
if ($logs) {
    foreach ($logs as $key => $log) {
        $timestamp = $log['timestamp'];
        // Ensure timestamp is treated as an integer
        if (is_float($timestamp)) {
            $timestamp = (int)$timestamp;
        }
        
        $action = strtolower($log['action']);
        
        // Apply category filter
        if ($category === 'all' ||
            ($category === 'user' && strpos($action, 'account') !== false) ||
            ($category === 'product' && strpos($action, 'update') !== false) ||
            ($category === 'transaction' && strpos($action, 'reset') !== false)) {
            
            $formattedLogs[] = [
                'username' => $log['username'],
                'action' => $log['action'],
                'details' => $log['details'],
                'formattedTime' => [
                    'date' => date('M d, Y', $timestamp),
                    'time' => date('h:i:s A', $timestamp)
                ]
            ];
        }
    }

    // Sort logs by timestamp in descending order
    usort($formattedLogs, function($a, $b) {
        return strtotime($b['formattedTime']['date'] . ' ' . $b['formattedTime']['time']) 
             - strtotime($a['formattedTime']['date'] . ' ' . $a['formattedTime']['time']);
    });
}

echo json_encode($formattedLogs);
