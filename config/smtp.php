<?php

// Configuration privée Alwaysdata
$production = dirname(__DIR__, 3) . '/private_ecf/smtp.php';

// Configuration privée XAMPP
$local = 'C:/xampp/private_ecf/smtp.php';

if (is_file($production)) {
    return require $production;
}

if (is_file($local)) {
    return require $local;
}

throw new RuntimeException(
    'Configuration SMTP introuvable.'
);
