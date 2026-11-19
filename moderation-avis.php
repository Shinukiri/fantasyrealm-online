<?php
// Vérifier la connexion et les droits du compte
require_once __DIR__ . '/config/auth.php';

// Charger notre fonction d'envoi d'e-mails
require_once __DIR__ . '/envoyer-email.php';

// Réserver la modération aux administrateurs et employés
if (!in_array($_SESSION['role'], ['admin', 'employe'], true)) {
    http_response_code(403);
    exit('Accès interdit.');
}

// Générer un jeton CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$erreur = '';
$succes = '';

// Traiter les actions de modération
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? null;

    $avisId = filter_var(
        $_POST['avis_id'] ?? null,
        FILTER_VALIDATE_INT
    );

    $action = $_POST['action'] ?? null;

    // Vérifier les données reçues
    if (
        !is_string($token) ||
        !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        $erreur = 'Formulaire invalide.';

    } elseif (
        !$avisId ||
        $avisId < 1 ||
        !is_string($action) ||
        !in_array($action, ['approuver', 'refuser'], true)
    ) {
        $erreur = 'Action invalide.';

    } else {

        try {
            // Démarrer une transaction
            $pdo->beginTransaction();

            // Verrouiller l'avis et récupérer les informations
            // du personnage et de son propriétaire
            $requete = $pdo->prepare(
                "SELECT
                    a.id,
                    a.note,
                    a.commentaire,
                    p.nom AS personnage,
                    u.email AS email_proprietaire
                 FROM avis a
                 JOIN personnages p
                   ON p.id = a.personnage_id
                 JOIN utilisateurs u
                   ON u.id = p.utilisateur_id
                 WHERE a.id = ?
                   AND a.statut = 'en_attente'
                 FOR UPDATE"
            );

            $requete->execute([$avisId]);

            $avisSelectionne = $requete->fetch(PDO::FETCH_ASSOC);

            if (!$avisSelectionne) {

                $pdo->rollBack();

                $erreur = 'Cet avis a déjà été traité ou n’existe plus.';

            } else {

                // Déterminer le nouveau statut
                $statut = $action === 'approuver'
                    ? 'approuve'
                    : 'refuse';

                // Mettre à jour l'avis
                $requete = $pdo->prepare(
                    "UPDATE avis
                     SET statut = ?
                     WHERE id = ?
                       AND statut = 'en_attente'"
                );

                $requete->execute([
                    $statut,
                    $avisId
                ]);

                // Enregistrer la décision avant tout envoi
                $pdo->commit();

                if ($action === 'approuver') {

                    $succes = 'Avis approuvé.';

                    // Informer le propriétaire du personnage
                    $message =
                        "Bonjour,\n\n" .
                        "Un nouvel avis a été approuvé " .
                        "sur votre personnage « " .
                        $avisSelectionne['personnage'] .
                        " ».\n\n" .
                        "Note : " .
                        $avisSelectionne['note'] .
                        "/5\n\n" .
                        "Commentaire :\n" .
                        $avisSelectionne['commentaire'] .
                        "\n\n" .
                        "Vous pouvez consulter cet avis " .
                        "sur FantasyRealm Online.\n\n" .
                        "L'équipe FantasyRealm Online";

                    $emailEnvoye = envoyerEmail(
                        $avisSelectionne['email_proprietaire'],
                        'Un nouvel avis sur votre personnage',
                        $message
                    );

                    if (!$emailEnvoye) {
                        $succes .=
                            ' Attention : notification e-mail non envoyée.';
                    }

                } else {

                    // Aucun e-mail nécessaire pour un avis refusé
                    $succes = 'Avis refusé.';
                }
            }

        } catch (Throwable $exception) {

            // Annuler les opérations encore en transaction
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            // Journaliser l'erreur
            error_log($exception->getMessage());

            $erreur = 'Une erreur est survenue pendant la modération.';
        }
    }
}

// Récupérer les avis en attente
$requete = $pdo->query(
    "SELECT
        a.id,
        a.note,
        a.commentaire,
        a.date_creation,
        u.pseudo,
        p.nom AS personnage
     FROM avis a
     JOIN utilisateurs u
       ON u.id = a.utilisateur_id
     JOIN personnages p
       ON p.id = a.personnage_id
     WHERE a.statut = 'en_attente'
     ORDER BY a.date_creation ASC, a.id ASC"
);

$avis = $requete->fetchAll(PDO::FETCH_ASSOC);

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
    <meta name="viewport"
          content="width=device-width, initial-scale=1">

    <title>
        Modération des avis | FantasyRealm Online
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

        .avis {
            background: #1f2937;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
        }

        button {
            background: #7c3aed;
            color: white;
            border: none;
            border-radius: 6px;
            padding: 10px 18px;
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

        form {
            display: inline-block;
            margin-right: 10px;
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

    <h1>Modération des avis</h1>

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

    <?php if (!$avis): ?>
        <p>Aucun avis en attente de validation.</p>
    <?php endif; ?>

    <?php foreach ($avis as $item): ?>

        <article class="avis">

            <h2><?= e($item['personnage']) ?></h2>

            <p>
                Auteur : <?= e($item['pseudo']) ?>
            </p>

            <p>
                Note : <?= (int)$item['note'] ?>/5
            </p>

            <p>
                <?= nl2br(e($item['commentaire'])) ?>
            </p>

            <p>
                Publié le : <?= e($item['date_creation']) ?>
            </p>

            <!-- Approbation -->
            <form method="POST">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e($_SESSION['csrf_token']) ?>"
                >

                <input
                    type="hidden"
                    name="avis_id"
                    value="<?= (int)$item['id'] ?>"
                >

                <button
                    type="submit"
                    name="action"
                    value="approuver"
                >
                    Approuver
                </button>

            </form>

            <!-- Refus -->
            <form method="POST">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e($_SESSION['csrf_token']) ?>"
                >

                <input
                    type="hidden"
                    name="avis_id"
                    value="<?= (int)$item['id'] ?>"
                >

                <button
                    class="refuser"
                    type="submit"
                    name="action"
                    value="refuser"
                >
                    Refuser
                </button>

            </form>

        </article>

    <?php endforeach; ?>

</main>
</body>
</html>
