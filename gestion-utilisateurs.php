<?php
// Vérifier l'authentification et le statut actif du compte
require_once __DIR__ . '/config/auth.php';

// Réserver la gestion des utilisateurs aux employés et admins
// auth.php actualise le rôle depuis la base de données
if (!in_array($_SESSION['role'], ['admin', 'employe'], true)) {
    http_response_code(403);
    exit('Accès interdit.');
}

// Générer le jeton CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';
$erreur = '';

// Traiter les actions de gestion des utilisateurs
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

    // Récupérer l'identifiant du compte concerné
    $utilisateurId = filter_var(
        $_POST['utilisateur_id'] ?? null,
        FILTER_VALIDATE_INT
    );

    // Récupérer l'action demandée
    $action = $_POST['action'] ?? '';

    $actionsAutorisees = [
        'suspendre',
        'reactiver',
        'supprimer'
    ];

    // Vérifier les paramètres
    if (
        !$utilisateurId ||
        $utilisateurId < 1 ||
        !in_array($action, $actionsAutorisees, true)
    ) {
        $erreur = 'Action invalide.';

    } else {

        try {

            // IMPORTANT :
            // Toutes les requêtes ciblent uniquement
            // les comptes ayant le rôle "utilisateur".
            //
            // Les comptes employés et administrateurs
            // ne peuvent pas être modifiés depuis cette page.

            if ($action === 'suspendre') {

                $requete = $pdo->prepare(
                    "UPDATE utilisateurs
                     SET statut = 'suspendu'
                     WHERE id = ?
                       AND role = 'utilisateur'
                       AND statut = 'actif'"
                );

            } elseif ($action === 'reactiver') {

                $requete = $pdo->prepare(
                    "UPDATE utilisateurs
                     SET statut = 'actif'
                     WHERE id = ?
                       AND role = 'utilisateur'
                       AND statut = 'suspendu'"
                );

            } else {

                // Suppression définitive du compte utilisateur
                $requete = $pdo->prepare(
                    "DELETE FROM utilisateurs
                     WHERE id = ?
                       AND role = 'utilisateur'"
                );
            }

            $requete->execute([$utilisateurId]);

            // Vérifier si une ligne a été modifiée
            if ($requete->rowCount() > 0) {

                if ($action === 'suspendre') {
                    $message = 'Compte utilisateur suspendu.';
                } elseif ($action === 'reactiver') {
                    $message = 'Compte utilisateur réactivé.';
                } else {
                    $message = 'Compte utilisateur supprimé définitivement.';
                }

            } else {

                $erreur = 'Aucun compte utilisateur modifié.';
            }

        } catch (PDOException $e) {

            // Enregistrer les détails techniques côté serveur
            error_log($e->getMessage());

            // Ne pas exposer les informations SQL aux visiteurs
            $erreur = 'Une erreur est survenue pendant cette opération.';
        }
    }
}

// Récupérer uniquement les comptes utilisateurs classiques
$requete = $pdo->query(
    "SELECT
        id,
        pseudo,
        email,
        statut,
        date_creation
     FROM utilisateurs
     WHERE role = 'utilisateur'
     ORDER BY date_creation DESC, id DESC"
);

$utilisateurs = $requete->fetchAll(PDO::FETCH_ASSOC);

// Échapper les données affichées contre les injections XSS
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
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Gestion des utilisateurs | FantasyRealm Online</title>

    <style>
        body {
            background: #111827;
            color: white;
            font-family: Arial, sans-serif;
        }

        main {
            max-width: 950px;
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
            border-radius: 10px;
            padding: 20px;
            margin: 15px 0;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        button {
            background: #7c3aed;
            color: white;
            border: none;
            padding: 10px 14px;
            border-radius: 6px;
            cursor: pointer;
        }

        button:hover {
            background: #6d28d9;
        }

        .danger {
            background: #991b1b;
        }

        .danger:hover {
            background: #7f1d1d;
        }

        .succes {
            color: #86efac;
        }

        .erreur {
            color: #fca5a5;
        }
    </style>
</head>
<body>
<main>

    <a href="espace.php">
        Retour à mon espace
    </a>

    <h1>Gestion des utilisateurs</h1>

    <?php if ($message): ?>
        <p class="succes" role="status">
            <?= e($message) ?>
        </p>
    <?php endif; ?>

    <?php if ($erreur): ?>
        <p class="erreur" role="alert">
            <?= e($erreur) ?>
        </p>
    <?php endif; ?>

    <?php if (empty($utilisateurs)): ?>
        <p>Aucun compte utilisateur pour le moment.</p>
    <?php endif; ?>

    <?php foreach ($utilisateurs as $utilisateur): ?>

        <article>

            <h2><?= e($utilisateur['pseudo']) ?></h2>

            <p>
                Email :
                <?= e($utilisateur['email']) ?>
            </p>

            <p>
                Statut :
                <?= e($utilisateur['statut']) ?>
            </p>

            <p>
                Inscrit le :
                <?= e($utilisateur['date_creation']) ?>
            </p>

            <div class="actions">

                <!-- Suspension ou réactivation -->
                <form method="POST">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e($_SESSION['csrf_token']) ?>"
                    >

                    <input
                        type="hidden"
                        name="utilisateur_id"
                        value="<?= (int)$utilisateur['id'] ?>"
                    >

                    <?php if ($utilisateur['statut'] === 'actif'): ?>

                        <button
                            type="submit"
                            name="action"
                            value="suspendre"
                        >
                            Suspendre
                        </button>

                    <?php else: ?>

                        <button
                            type="submit"
                            name="action"
                            value="reactiver"
                        >
                            Réactiver
                        </button>

                    <?php endif; ?>

                </form>

                <!-- Suppression définitive -->
                <form
                    method="POST"
                    onsubmit="return confirm('Supprimer définitivement ce compte et ses données associées ?');"
                >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e($_SESSION['csrf_token']) ?>"
                    >

                    <input
                        type="hidden"
                        name="utilisateur_id"
                        value="<?= (int)$utilisateur['id'] ?>"
                    >

                    <button
                        class="danger"
                        type="submit"
                        name="action"
                        value="supprimer"
                    >
                        Supprimer
                    </button>

                </form>

            </div>

        </article>

    <?php endforeach; ?>

</main>
</body>
</html>
