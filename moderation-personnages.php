<?php
// Vérifier l'authentification et le statut actif du compte
require_once __DIR__ . '/config/auth.php';

// Charger la fonction commune d'envoi d'e-mails
require_once __DIR__ . '/envoyer-email.php';

// Charger le journal MongoDB
require_once __DIR__ . '/config/journal.php';

// Réserver la modération aux administrateurs et employés
// Le rôle a été actualisé depuis la base par auth.php
if (!in_array($_SESSION['role'], ['admin', 'employe'], true)) {
    http_response_code(403);
    exit('Accès interdit.');
}

// Échapper les données affichées
function e($valeur) {
    return htmlspecialchars(
        (string)$valeur,
        ENT_QUOTES,
        'UTF-8'
    );
}

// Générer un jeton CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$erreur = '';
$succes = '';

// Traiter les actions de modération
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Récupérer les données en vérifiant leur type
    $token = $_POST['csrf_token'] ?? null;

    $id = filter_var(
        $_POST['personnage_id'] ?? null,
        FILTER_VALIDATE_INT
    );

    $action = $_POST['action'] ?? null;

    $motif = is_string($_POST['motif'] ?? null)
        ? trim($_POST['motif'])
        : '';

    // Vérifier le jeton CSRF
    if (
        !is_string($token) ||
        !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        $erreur = 'Formulaire invalide.';

    } elseif (
        !$id ||
        $id < 1 ||
        !is_string($action) ||
        !in_array($action, ['approuver', 'refuser'], true)
    ) {
        $erreur = 'Action invalide.';

    } elseif (
        $action === 'refuser' &&
        (
            $motif === '' ||
            mb_strlen($motif) > 1000
        )
    ) {
        $erreur = 'Le motif de refus est obligatoire (1000 caractères maximum).';

    } else {

        try {
            // Démarrer une transaction
            $pdo->beginTransaction();

            // Verrouiller le personnage pour éviter
            // qu'il soit modéré simultanément
            // Récupérer également l'e-mail de son créateur
            $requete = $pdo->prepare(
                "SELECT
                    p.id,
                    p.nom,
                    p.utilisateur_id,
                    u.email
                 FROM personnages p
                 JOIN utilisateurs u
                   ON u.id = p.utilisateur_id
                 WHERE p.id = ?
                   AND p.statut = 'en_attente'
                 FOR UPDATE"
            );

            $requete->execute([$id]);

            $personnage = $requete->fetch(PDO::FETCH_ASSOC);

            if (!$personnage) {

                $pdo->rollBack();

                $erreur = 'Ce personnage a déjà été traité ou n’existe plus.';

            } elseif ($action === 'approuver') {

                // Approuver le personnage
                $requete = $pdo->prepare(
                    "UPDATE personnages
                     SET statut = 'approuve'
                     WHERE id = ?
                       AND statut = 'en_attente'"
                );

                $requete->execute([$id]);

                // Vérifier que l'approbation a été effectuée
                if ($requete->rowCount() !== 1) {
                    throw new RuntimeException(
                        'Impossible d’approuver ce personnage.'
                    );
                }

                // Valider la transaction avant MongoDB
                // et avant l'envoi de l'e-mail
                $pdo->commit();

                // Enregistrer l'approbation dans MongoDB
                enregistrerEvenement(
                    'personnage_approuve',
                    (int) $_SESSION['utilisateur_id'],
                    (int) $id,
                    [
                        'personnage_nom' => $personnage['nom'],
                        'createur_id' =>
                            (int) $personnage['utilisateur_id'],
                        'moderateur_id' =>
                            (int) $_SESSION['utilisateur_id'],
                        'moderateur_role' => $_SESSION['role'],
                        'ancien_statut' => 'en_attente',
                        'nouveau_statut' => 'approuve'
                    ]
                );

                $succes = 'Personnage approuvé.';

                // Informer le créateur
                $emailEnvoye = envoyerEmail(
                    $personnage['email'],
                    'Votre personnage a été approuvé',
                    "Bonjour,\n\n" .
                    "Votre personnage « {$personnage['nom']} » " .
                    "a été approuvé par notre équipe.\n\n" .
                    "Vous pouvez maintenant le personnaliser " .
                    "et lui ajouter des équipements.\n\n" .
                    "L'équipe FantasyRealm Online"
                );

                if (!$emailEnvoye) {
                    $succes .=
                        ' Attention : notification e-mail non envoyée.';
                }

            } else {

                // Enregistrer le motif de refus
                // avant la suppression du personnage
                $requete = $pdo->prepare(
                    "INSERT INTO refus_personnages
                     (
                         personnage_nom,
                         utilisateur_id,
                         motif,
                         moderateur_id
                     )
                     VALUES (?, ?, ?, ?)"
                );

                $requete->execute([
                    $personnage['nom'],
                    $personnage['utilisateur_id'],
                    $motif,
                    $_SESSION['utilisateur_id']
                ]);

                // Supprimer définitivement le personnage refusé
                $requete = $pdo->prepare(
                    "DELETE FROM personnages
                     WHERE id = ?
                       AND statut = 'en_attente'"
                );

                $requete->execute([$id]);

                // Vérifier que la suppression a réussi
                if ($requete->rowCount() !== 1) {
                    throw new RuntimeException(
                        'Impossible de supprimer le personnage refusé.'
                    );
                }

                // Valider les deux opérations MySQL ensemble
                $pdo->commit();

                // Enregistrer le refus dans MongoDB
                enregistrerEvenement(
                    'personnage_refuse',
                    (int) $_SESSION['utilisateur_id'],
                    (int) $id,
                    [
                        'personnage_nom' => $personnage['nom'],
                        'createur_id' =>
                            (int) $personnage['utilisateur_id'],
                        'moderateur_id' =>
                            (int) $_SESSION['utilisateur_id'],
                        'moderateur_role' => $_SESSION['role'],
                        'ancien_statut' => 'en_attente',
                        'nouveau_statut' => 'refuse',
                        'motif' => $motif,
                        'personnage_supprime' => true
                    ]
                );

                $succes = 'Personnage refusé et supprimé.';

                // Informer le créateur du refus et de son motif
                $emailEnvoye = envoyerEmail(
                    $personnage['email'],
                    'Votre personnage a été refusé',
                    "Bonjour,\n\n" .
                    "Votre personnage « {$personnage['nom']} » " .
                    "n'a pas été accepté par notre équipe.\n\n" .
                    "Motif du refus :\n" .
                    $motif . "\n\n" .
                    "Vous pouvez créer un nouveau personnage.\n\n" .
                    "L'équipe FantasyRealm Online"
                );

                if (!$emailEnvoye) {
                    $succes .=
                        ' Attention : notification e-mail non envoyée.';
                }
            }

        } catch (Throwable $exception) {

            // Annuler les opérations encore en transaction
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            // Journaliser l'erreur côté serveur
            error_log($exception->getMessage());

            $erreur = 'Une erreur est survenue pendant la modération.';
        }
    }
}

// Récupérer les personnages en attente
$requete = $pdo->query(
    "SELECT
        p.id,
        p.nom,
        p.genre,
        p.date_creation,
        u.pseudo
     FROM personnages p
     JOIN utilisateurs u
       ON u.id = p.utilisateur_id
     WHERE p.statut = 'en_attente'
     ORDER BY p.date_creation ASC, p.id ASC"
);

$personnages = $requete->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>
        Modération des personnages | FantasyRealm Online
    </title>

    <style>
        body {
            margin: 0;
            background: #111827;
            color: white;
            font-family: Arial, sans-serif;
        }

        main {
            max-width: 900px;
            margin: 40px auto;
            padding: 20px;
        }

        h1 {
            color: #a78bfa;
        }

        a {
            color: #c4b5fd;
        }

        .carte {
            background: #1f2937;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
        }

        label {
            display: block;
            margin-top: 15px;
        }

        textarea {
            width: 100%;
            box-sizing: border-box;
            padding: 10px;
            margin-top: 6px;
        }

        button {
            background: #7c3aed;
            color: white;
            border: none;
            border-radius: 6px;
            padding: 12px 18px;
            margin-top: 12px;
            cursor: pointer;
        }

        button:hover {
            background: #6d28d9;
        }

        .refuser {
            background: #b91c1c;
        }

        .refuser:hover {
            background: #991b1b;
        }

        .erreur {
            color: #fca5a5;
        }

        .succes {
            color: #86efac;
        }
    </style>
</head>
<body>
<main>

    <a href="espace.php">
        Retour à mon espace
    </a>

    <h1>Modération des personnages</h1>

    <?php if ($erreur): ?>
        <p class="erreur" role="alert">
            <?= e($erreur) ?>
        </p>
    <?php endif; ?>

    <?php if ($succes): ?>
        <p class="succes" role="status">
            <?= e($succes) ?>
        </p>
    <?php endif; ?>

    <?php if (!$personnages): ?>
        <p>Aucun personnage en attente.</p>
    <?php endif; ?>

    <?php foreach ($personnages as $personnage): ?>

        <article class="carte">

            <h2><?= e($personnage['nom']) ?></h2>

            <p>
                Créateur :
                <?= e($personnage['pseudo']) ?>
            </p>

            <p>
                Genre :
                <?= e($personnage['genre']) ?>
            </p>

            <p>
                Créé le :
                <?= e($personnage['date_creation']) ?>
            </p>

            <!-- Formulaire d'approbation -->
            <form method="POST">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e($_SESSION['csrf_token']) ?>"
                >

                <input
                    type="hidden"
                    name="personnage_id"
                    value="<?= (int)$personnage['id'] ?>"
                >

                <button
                    type="submit"
                    name="action"
                    value="approuver"
                >
                    Approuver
                </button>

            </form>

            <!-- Formulaire de refus -->
            <form
                method="POST"
                onsubmit="return confirm('Refuser et supprimer définitivement ce personnage ?');"
            >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e($_SESSION['csrf_token']) ?>"
                >

                <input
                    type="hidden"
                    name="personnage_id"
                    value="<?= (int)$personnage['id'] ?>"
                >

                <label for="motif_<?= (int)$personnage['id'] ?>">
                    Motif du refus
                </label>

                <textarea
                    id="motif_<?= (int)$personnage['id'] ?>"
                    name="motif"
                    rows="3"
                    maxlength="1000"
                    required
                ></textarea>

                <button
                    class="refuser"
                    type="submit"
                    name="action"
                    value="refuser"
                >
                    Refuser et supprimer
                </button>

            </form>

        </article>

    <?php endforeach; ?>

</main>
</body>
</html>
