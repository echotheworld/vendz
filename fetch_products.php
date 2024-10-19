<?php
require __DIR__.'/vendor/autoload.php';
use Kreait\Firebase\Factory;

// Initialize Firebase
$factory = (new Factory)->withServiceAccount(__DIR__ . '/dbvending-1b336-firebase-adminsdk-m26i6-688c7d0c77.json')
                        ->withDatabaseUri('https://dbvending-1b336-default-rtdb.firebaseio.com');
$database = $factory->createDatabase();

// Fetch products
$productsRef = $database->getReference('tables/products');
$snapshot = $productsRef->getSnapshot();
$products = $snapshot->getValue() ?: [];

// Return products as JSON
header('Content-Type: application/json');
echo json_encode($products);