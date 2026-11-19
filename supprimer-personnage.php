<?php
// Vérifier l'authentification et le statut actif du compte
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/journal.php';

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

// Récupérer et vérifier l'identifiant du personnage
$id = filter_var(
    $_POST['personnage_id'] ?? null,
    FILTER_VALIDATE_INT
);

if (!$id || $id < 1) {
    http_response_code(400);
    exit('Identifiant invalide.');
}

try {
    // Démarrer une transaction MySQL
    $pdo->beginTransaction();

    // Récupérer le personnage et verrouiller sa ligne
    // pour éviter une modification concurrente
    $requete = $pdo->prepare(
        "SELECT id, nom
         FROM personnages
         WHERE id = ?
           AND utilisateur_id = ?
         FOR UPDATE"
    );

    $requete->execute([
        $id,
        $_SESSION['utilisateur_id']
    ]);

    $personnage = $requete->fetch(PDO::FETCH_ASSOC);

    // Vérifier que le personnage existe
    // et appartient à l'utilisateur connecté
    if (!$personnage) {
        $pdo->rollBack();

        http_response_code(404);
        exit('Personnage introuvable ou non autorisé.');
    }

    // Conserver le nom avant la suppression
    $nomPersonnage = $personnage['nom'];

    // Supprimer le personnage dans MySQL
    $requete = $pdo->prepare(
        "DELETE FROM personnages
         WHERE id = ?
           AND utilisateur_id = ?"
    );

    $requete->execute([
        $id,
        $_SESSION['utilisateur_id']
    ]);

    // Vérifier si la suppression a réussi
    if ($requete->rowCount() !== 1) {
        throw new RuntimeException(
            'La suppression du personnage a échoué.'
        );
    }

    // Valider définitivement la suppression MySQL
    $pdo->commit();

    // Enregistrer la suppression dans MongoDB
    // uniquement après la réussite de MySQL
    enregistrerEvenement(
        'suppression_personnage',
        (int) $_SESSION['utilisateur_id'],
        (int) $id,
        [
            'nom' => $nomPersonnage,
            'action' => 'suppression'
        ]
    );

    // Revenir à la liste des personnages
    header('Location: mes-personnages.php');
    exit;

} catch (Throwable $exception) {

    // Annuler la transaction si elle est encore active
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Enregistrer l'erreur dans les journaux du serveur
    error_log($exception->getMessage());

    http_response_code(500);
    exit('Une erreur est survenue pendant la suppression.');
}
