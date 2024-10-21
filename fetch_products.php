<?php
require __DIR__.'/vendor/autoload.php';
require_once __DIR__ . '/firebase-init.php';

// Use the global $database variable
global $database;

// Fetch products
$productsRef = $database->getReference('tables/products');
$snapshot = $productsRef->getSnapshot();
$products = $snapshot->getValue() ?: [];

// Return products as JSON
header('Content-Type: application/json');
echo json_encode($products);
