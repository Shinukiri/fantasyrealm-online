<?php
// Vérifier l'authentification et le statut actif du compte
require_once __DIR__ . '/config/auth.php';

// Autoriser uniquement les requêtes POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Méthode non autorisée.');
}

// Vérifier le jeton de sécurité CSRF
if (
    empty($_SESSION['csrf_token']) ||
    !is_string($_POST['csrf_token'] ?? null) ||
    !hash_equals(
        $_SESSION['csrf_token'],
        $_POST['csrf_token']
    )
) {
    http_response_code(403);
    exit('Requête non autorisée.');
}

// Récupérer et vérifier l'identifiant du personnage
$id = filter_var(
    $_POST['personnage_id'] ?? null,
    FILTER_VALIDATE_INT
);

if (!$id || $id < 1) {
    http_response_code(400);
    exit('Identifiant invalide.');
}

// Inverser le statut de partage uniquement si :
// - le personnage appartient à l'utilisateur connecté
// - le personnage a été approuvé
$requete = $pdo->prepare(
    "UPDATE personnages
     SET partage = CASE
         WHEN partage = 1 THEN 0
         ELSE 1
     END
     WHERE id = ?
       AND utilisateur_id = ?
       AND statut = 'approuve'"
);

$requete->execute([
    $id,
    $_SESSION['utilisateur_id']
]);

// Retourner à la liste des personnages
header('Location: mes-personnages.php');
exit;
