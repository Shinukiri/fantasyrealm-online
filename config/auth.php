<?php
// Démarrer la session si nécessaire
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Connexion à la base de données
require_once __DIR__ . '/database.php';

// Vérifier que l'utilisateur est connecté
if (!isset($_SESSION['utilisateur_id'])) {
    header('Location: /ecf_studi/connexion.php');
    exit;
}

// Récupérer les informations actuelles du compte
$requeteAuth = $pdo->prepare(
    "SELECT id, pseudo, role, statut, changement_mdp_requis
     FROM utilisateurs
     WHERE id = ?"
);

$requeteAuth->execute([
    $_SESSION['utilisateur_id']
]);

$utilisateurConnecte = $requeteAuth->fetch(PDO::FETCH_ASSOC);

// Vérifier que le compte existe et qu'il est actif
if (
    !$utilisateurConnecte ||
    $utilisateurConnecte['statut'] !== 'actif'
) {
    // Vider la session
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

    header('Location: /ecf_studi/connexion.php?compte=suspendu');
    exit;
}

// Actualiser les informations de session
$_SESSION['role'] = $utilisateurConnecte['role'];
$_SESSION['pseudo'] = $utilisateurConnecte['pseudo'];

// Vérifier si le mot de passe doit être changé
if ((int)$utilisateurConnecte['changement_mdp_requis'] === 1) {

    // Identifier la page actuellement demandée
    $pageActuelle = basename(
        parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)
    );

    // Autoriser uniquement la page de changement
    if ($pageActuelle !== 'changer-mot-de-passe.php') {
        header('Location: /ecf_studi/changer-mot-de-passe.php');
        exit;
    }
}
