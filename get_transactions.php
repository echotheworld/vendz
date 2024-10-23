<?php
require __DIR__.'/vendor/autoload.php';
require_once __DIR__ . '/firebase-init.php';

// Use the global $database variable
global $database;

// Fetch transaction data
$transactionsRef = $database->getReference('tables/transactions');
$transactions = $transactionsRef->getValue();

// Get the number of days from the request
$days = isset($_GET['days']) ? intval($_GET['days']) : 7;

// Calculate start and end dates
$endDate = date('Y-m-d');
$startDate = date('Y-m-d', strtotime("-$days days"));

// Function to get transaction counts for a given date range
function getTransactionCounts($transactions, $startDate, $endDate) {
    $dailyTransactions = [];
    $currentDate = new DateTime($startDate);
    $endDateTime = new DateTime($endDate);

    while ($currentDate <= $endDateTime) {
        $dailyTransactions[$currentDate->format('Y-m-d')] = 0;
        $currentDate->modify('+1 day');
    }

    if ($transactions) {
        foreach ($transactions as $transaction) {
            $transactionDate = $transaction['date'];
            if (isset($dailyTransactions[$transactionDate])) {
                $dailyTransactions[$transactionDate]++;
            }
        }
    }

    return $dailyTransactions;
}

$dailyTransactions = getTransactionCounts($transactions, $startDate, $endDate);

// Prepare the response
$response = [
    'labels' => array_keys($dailyTransactions['Product 1']),
    'data' => [
        'Product 1' => array_values($dailyTransactions['Product 1']),
        'Product 2' => array_values($dailyTransactions['Product 2'])
    ]
];

header('Content-Type: application/json');
echo json_encode($response);
