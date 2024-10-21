<?php

require __DIR__.'/vendor/autoload.php';

use Kreait\Firebase\Factory;

$factory = (new Factory)
    ->withServiceAccount(__DIR__ . '/firebase-config.json')
    ->withDatabaseUri('https://dbvending-1b336-default-rtdb.firebaseio.com');

$database = $factory->createDatabase();

// You can add other Firebase services here if needed
// $auth = $factory->createAuth();
// $storage = $factory->createStorage();

// Make these variables available globally
global $database;