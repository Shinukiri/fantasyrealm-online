<?php
// Charger les bibliothèques Composer
require_once __DIR__ . '/../vendor/autoload.php';

use MongoDB\Client;

function connexionMongoDB(): \MongoDB\Collection
{
    // Connexion au serveur MongoDB local
    $client = new Client(
        'mongodb://127.0.0.1:27017',
        [],
        ['serverSelectionTimeoutMS' => 3000]
    );

    // Base : fantasyrealm_logs
    // Collection : evenements
    return $client
        ->selectDatabase('fantasyrealm_logs')
        ->selectCollection('evenements');
}
