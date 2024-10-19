<?php
   require __DIR__.'/vendor/autoload.php';

   use Kreait\Firebase\Factory;

   $factory = (new Factory)
       ->withServiceAccount('/path/to/your/firebase_credentials.json')
       ->withDatabaseUri('https://dbvending-1b336-default-rtdb.firebaseio.com');

   $database = $factory->createDatabase();