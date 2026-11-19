<?php
// Vérifier l'authentification et le statut actif du compte
require_once __DIR__ . '/config/auth.php';

// Autoriser uniquement les requêtes POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Méthode non autorisée.');
}

// Vérifier le jeton CSRF
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

// Récupérer l'identifiant du personnage original
$id = filter_var(
    $_POST['personnage_id'] ?? null,
    FILTER_VALIDATE_INT
);

// Récupérer le nouveau nom
$nouveauNom = is_string($_POST['nouveau_nom'] ?? null)
    ? trim($_POST['nouveau_nom'])
    : '';

// Vérifier les données
if (
    !$id ||
    $id < 1 ||
    mb_strlen($nouveauNom) < 3 ||
    mb_strlen($nouveauNom) > 100
) {
    http_response_code(400);
    exit('Identifiant invalide ou nom incorrect (3 à 100 caractères).');
}

try {
    // Démarrer une transaction pour garantir
    // une duplication complète ou aucune duplication
    $pdo->beginTransaction();

    // Vérifier que le personnage original appartient
    // à l'utilisateur et qu'il a été approuvé
    $requete = $pdo->prepare(
        "SELECT *
         FROM personnages
         WHERE id = ?
           AND utilisateur_id = ?
           AND statut = 'approuve'
         FOR UPDATE"
    );

    $requete->execute([
        $id,
        $_SESSION['utilisateur_id']
    ]);

    $original = $requete->fetch(PDO::FETCH_ASSOC);

    if (!$original) {
        $pdo->rollBack();

        http_response_code(404);
        exit('Personnage introuvable ou non autorisé.');
    }

    // Créer le nouveau personnage
    // Il devra être approuvé avant de pouvoir être utilisé
    $requete = $pdo->prepare(
        "INSERT INTO personnages
        (
            utilisateur_id,
            nom,
            genre,
            yeux_forme,
            nez_forme,
            bouche_forme,
            couleur_peau,
            couleur_cheveux,
            couleur_yeux,
            image,
            statut,
            partage
        )
        VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
            'en_attente', 0
        )"
    );

    $requete->execute([
        $_SESSION['utilisateur_id'],
        $nouveauNom,
        $original['genre'],
        $original['yeux_forme'],
        $original['nez_forme'],
        $original['bouche_forme'],
        $original['couleur_peau'],
        $original['couleur_cheveux'],
        $original['couleur_yeux'],
        $original['image']
    ]);

    // Récupérer l'identifiant du nouveau personnage
    $nouvelId = (int)$pdo->lastInsertId();

    // Copier tous les accessoires du personnage original,
    // y compris ceux déjà équipés mais désactivés
    $requete = $pdo->prepare(
        "INSERT INTO personnage_accessoires
         (personnage_id, accessoire_id)
         SELECT ?, accessoire_id
         FROM personnage_accessoires
         WHERE personnage_id = ?"
    );

    $requete->execute([
        $nouvelId,
        $id
    ]);

    // Valider la transaction
    $pdo->commit();

    // Revenir à la liste des personnages
    header('Location: mes-personnages.php');
    exit;

} catch (PDOException $e) {

    // Annuler toutes les modifications en cas d'erreur
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Nom déjà utilisé
    if ($e->getCode() === '23000') {
        http_response_code(409);
        exit('Ce nom de personnage existe déjà. Choisissez un autre nom.');
    }

    // Journaliser l'erreur sans révéler les détails techniques
    error_log($e->getMessage());

    http_response_code(500);
    exit('Une erreur est survenue pendant la duplication.');
}
