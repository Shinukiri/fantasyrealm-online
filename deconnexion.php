<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Supprimer les données de session
$_SESSION = [];

// Supprimer le cookie de session
if (ini_get('session.use_cookies')) {
    $parametres = session_get_cookie_params();

    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $parametres['path'],
        'domain' => $parametres['domain'],
        'secure' => $parametres['secure'],
        'httponly' => $parametres['httponly'],
        'samesite' => $parametres['samesite'] ?? 'Lax'
    ]);
}

// Détruire la session
session_destroy();

// Retour à la connexion
header('Location: connexion.php');
exit;
