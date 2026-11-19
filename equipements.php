<?php
// Vérifier la connexion et le statut actif du compte
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/journal.php';

// Récupérer et vérifier l'identifiant du personnage
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    http_response_code(400);
    exit('Identifiant invalide.');
}

// Vérifier que le personnage appartient à l'utilisateur
// et qu'il a été approuvé
$requete = $pdo->prepare(
    "SELECT id, nom
     FROM personnages
     WHERE id = ?
       AND utilisateur_id = ?
       AND statut = 'approuve'"
);

$requete->execute([
    $id,
    $_SESSION['utilisateur_id']
]);

$personnage = $requete->fetch(PDO::FETCH_ASSOC);

if (!$personnage) {
    http_response_code(404);
    exit('Personnage introuvable ou non autorisé.');
}

// Générer un jeton CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$erreur = '';

// Traiter l'ajout ou le retrait d'un équipement
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Vérifier le jeton CSRF
    if (
        !is_string($_POST['csrf_token'] ?? null) ||
        !hash_equals(
            $_SESSION['csrf_token'],
            $_POST['csrf_token']
        )
    ) {
        http_response_code(403);
        exit('Requête non autorisée.');
    }

    $accessoireId = filter_var(
        $_POST['accessoire_id'] ?? null,
        FILTER_VALIDATE_INT
    );

    $action = $_POST['action'] ?? null;

    if (
        !$accessoireId ||
        $accessoireId < 1 ||
        !is_string($action) ||
        !in_array($action, ['ajouter', 'retirer'], true)
    ) {
        $erreur = 'Action invalide.';

    } else {

        try {
            // Démarrer une transaction
            $pdo->beginTransaction();

            // Vérifier et verrouiller le personnage
            $requete = $pdo->prepare(
                "SELECT id, nom
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

            $personnageActuel = $requete->fetch(PDO::FETCH_ASSOC);

            if (!$personnageActuel) {
                throw new RuntimeException(
                    'Personnage introuvable ou non autorisé.'
                );
            }

            // Récupérer l'accessoire
            $requete = $pdo->prepare(
                "SELECT id, nom, actif
                 FROM accessoires
                 WHERE id = ?"
            );

            $requete->execute([$accessoireId]);

            $accessoire = $requete->fetch(PDO::FETCH_ASSOC);

            if (!$accessoire) {
                throw new RuntimeException(
                    'Accessoire introuvable.'
                );
            }

            // Vérifier si l'accessoire est déjà équipé
            $requete = $pdo->prepare(
                "SELECT accessoire_id
                 FROM personnage_accessoires
                 WHERE personnage_id = ?
                   AND accessoire_id = ?"
            );

            $requete->execute([
                $id,
                $accessoireId
            ]);

            $dejaEquipe = (bool)$requete->fetchColumn();

            $modificationEffectuee = false;

            if ($action === 'ajouter') {

                // Ne pas équiper un accessoire désactivé
                if (!(int)$accessoire['actif']) {
                    throw new RuntimeException(
                        'Cet accessoire est désactivé.'
                    );
                }

                if (!$dejaEquipe) {

                    $requete = $pdo->prepare(
                        "INSERT INTO personnage_accessoires
                         (personnage_id, accessoire_id)
                         VALUES (?, ?)"
                    );

                    $requete->execute([
                        $id,
                        $accessoireId
                    ]);

                    $modificationEffectuee = true;
                }

            } else {

                // Autoriser le retrait même si l'accessoire
                // a été désactivé
                if ($dejaEquipe) {

                    $requete = $pdo->prepare(
                        "DELETE FROM personnage_accessoires
                         WHERE personnage_id = ?
                           AND accessoire_id = ?"
                    );

                    $requete->execute([
                        $id,
                        $accessoireId
                    ]);

                    $modificationEffectuee = true;
                }
            }

            // Enregistrer uniquement les modifications réelles
            if ($modificationEffectuee) {

                $informationsAccessoire = [
                    'accessoire_id' => (int)$accessoire['id'],
                    'accessoire_nom' => $accessoire['nom']
                ];

                // Historique avant / après
                $anciennesValeurs = [
                    'equipe' => $action === 'retirer'
                ] + $informationsAccessoire;

                $nouvellesValeurs = [
                    'equipe' => $action === 'ajouter'
                ] + $informationsAccessoire;

                $ancienJSON = json_encode(
                    $anciennesValeurs,
                    JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
                );

                $nouveauJSON = json_encode(
                    $nouvellesValeurs,
                    JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
                );

                // Enregistrer l'historique MySQL
                $requete = $pdo->prepare(
                    "INSERT INTO historique_personnages
                     (
                         personnage_id,
                         personnage_nom,
                         utilisateur_id,
                         type_modification,
                         anciennes_valeurs,
                         nouvelles_valeurs
                     )
                     VALUES (?, ?, ?, ?, ?, ?)"
                );

                $requete->execute([
                    $id,
                    $personnageActuel['nom'],
                    $_SESSION['utilisateur_id'],
                    $action === 'ajouter'
                        ? 'equipement_ajoute'
                        : 'equipement_retire',
                    $ancienJSON,
                    $nouveauJSON
                ]);
            }

            // Valider les modifications MySQL
            $pdo->commit();

            // Journaliser uniquement une modification réelle
            // et validée dans MySQL
            if ($modificationEffectuee) {

                enregistrerEvenement(
                    $action === 'ajouter'
                        ? 'equipement_ajoute'
                        : 'equipement_retire',
                    (int) $_SESSION['utilisateur_id'],
                    (int) $id,
                    [
                        'personnage_nom' =>
                            $personnageActuel['nom'],
                        'accessoire_id' =>
                            (int) $accessoire['id'],
                        'accessoire_nom' =>
                            $accessoire['nom'],
                        'anciennes_valeurs' =>
                            $anciennesValeurs,
                        'nouvelles_valeurs' =>
                            $nouvellesValeurs
                    ]
                );
            }

            header('Location: equipements.php?id=' . $id);
            exit;

        } catch (Throwable $exception) {

            // Annuler les opérations en cas d'erreur
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log($exception->getMessage());

            $erreur =
                'Impossible de modifier cet équipement.';
        }
    }
}

// Récupérer les accessoires actifs et ceux déjà équipés
$requete = $pdo->prepare(
    "SELECT
        a.id,
        a.nom,
        a.categorie,
        a.actif,
        CASE
            WHEN pa.accessoire_id IS NULL THEN 0
            ELSE 1
        END AS equipe
     FROM accessoires a
     LEFT JOIN personnage_accessoires pa
       ON pa.accessoire_id = a.id
      AND pa.personnage_id = ?
     WHERE a.actif = 1
        OR pa.accessoire_id IS NOT NULL
     ORDER BY a.categorie, a.nom"
);

$requete->execute([$id]);

$accessoires = $requete->fetchAll(PDO::FETCH_ASSOC);

// Échapper les données affichées
function e($valeur) {
    return htmlspecialchars(
        (string)$valeur,
        ENT_QUOTES,
        'UTF-8'
    );
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Équipements | FantasyRealm Online</title>

    <style>
        body {
            background: #111827;
            color: white;
            font-family: Arial, sans-serif;
        }

        main {
            max-width: 800px;
            margin: 40px auto;
            padding: 20px;
        }

        h1 {
            color: #a78bfa;
        }

        a {
            color: #c4b5fd;
        }

        article {
            background: #1f2937;
            padding: 16px;
            margin: 12px 0;
            border-radius: 8px;
        }

        button {
            padding: 10px 16px;
            background: #7c3aed;
            color: white;
            border: 0;
            border-radius: 6px;
            cursor: pointer;
        }

        button:hover {
            background: #6d28d9;
        }

        .erreur {
            color: #fca5a5;
        }

        .desactive {
            color: #fca5a5;
        }
    </style>
</head>
<body>
<main>

    <a href="mes-personnages.php">
        Retour à mes personnages
    </a>

    <h1>
        Équipements de <?= e($personnage['nom']) ?>
    </h1>

    <?php if ($erreur): ?>
        <p class="erreur" role="alert">
            <?= e($erreur) ?>
        </p>
    <?php endif; ?>

    <?php if (!$accessoires): ?>
        <p>Aucun accessoire disponible pour le moment.</p>
    <?php endif; ?>

    <?php foreach ($accessoires as $accessoire): ?>

        <article>

            <h2><?= e($accessoire['nom']) ?></h2>

            <p>
                Catégorie :
                <?= e($accessoire['categorie']) ?>
            </p>

            <p>
                Statut :
                <?= $accessoire['equipe']
                    ? 'Équipé'
                    : 'Non équipé' ?>
            </p>

            <?php if (!$accessoire['actif']): ?>
                <p class="desactive">
                    Cet accessoire a été désactivé
                    par l'administration.
                </p>
            <?php endif; ?>

            <form method="POST">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e($_SESSION['csrf_token']) ?>"
                >

                <input
                    type="hidden"
                    name="accessoire_id"
                    value="<?= (int)$accessoire['id'] ?>"
                >

                <button
                    type="submit"
                    name="action"
                    value="<?= $accessoire['equipe']
                        ? 'retirer'
                        : 'ajouter' ?>"
                >
                    <?= $accessoire['equipe']
                        ? 'Retirer'
                        : 'Équiper' ?>
                </button>

            </form>

        </article>

    <?php endforeach; ?>

</main>
</body>
</html>
