<?php
require __DIR__.'/vendor/autoload.php';
use Kreait\Firebase\Factory;

// Initialize Firebase
$factory = (new Factory)
    ->withServiceAccount(__DIR__ . '/dbvending-1b336-firebase-adminsdk-m26i6-688c7d0c77.json')
    ->withDatabaseUri('https://dbvending-1b336-default-rtdb.firebaseio.com');

$database = $factory->createDatabase();

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
    'labels' => array_keys($dailyTransactions),
    'data' => array_values($dailyTransactions)
];

header('Content-Type: application/json');
echo json_encode($response);