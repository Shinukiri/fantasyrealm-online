<?php

// Configuration Alwaysdata via les variables d'environnement.
// Configuration locale XAMPP utilisée en l'absence de ces variables.

$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbName = getenv('DB_NAME') ?: 'ecf_studi';
$dbUser = getenv('DB_USER') ?: 'root';

$dbPassword = getenv('DB_PASS');
if ($dbPassword === false) {
    $dbPassword = '';
}

try {
    $pdo = new PDO(
        "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPassword,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    exit("Erreur de connexion à la base de données.");
}
