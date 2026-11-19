<?php
session_start();
require_once __DIR__ . '/config/database.php';

if (!isset($_SESSION['utilisateur_id'])) {
    header('Location: connexion.php');
    exit;
}

$requete = $pdo->prepare(
    "SELECT role, statut FROM utilisateurs WHERE id = ?"
);
$requete->execute([$_SESSION['utilisateur_id']]);
$compte = $requete->fetch();

if (
    !$compte ||
    $compte['statut'] !== 'actif' ||
    !in_array($compte['role'], ['admin', 'employe'], true)
) {
    http_response_code(403);
    exit("Accès interdit.");
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals(
        $_SESSION['csrf_token'],
        $_POST['csrf_token'] ?? ''
    )) {
        http_response_code(403);
        exit("Requête non autorisée.");
    }

    $id = filter_var(
        $_POST['personnage_id'] ?? null,
        FILTER_VALIDATE_INT
    );

    $action = $_POST['action'] ?? '';

    if ($id && $action === 'approuver') {
        $requete = $pdo->prepare(
            "UPDATE personnages
             SET statut = 'approuve'
             WHERE id = ? AND statut = 'en_attente'"
        );
        $requete->execute([$id]);
    }

    header('Location: moderation.php');
    exit;
}

$personnages = $pdo->query(
    "SELECT p.id, p.nom, p.genre, u.pseudo
     FROM personnages p
     JOIN utilisateurs u ON p.utilisateur_id = u.id
     WHERE p.statut = 'en_attente'
     ORDER BY p.date_creation ASC"
)->fetchAll();

function echapper($valeur) {
    return htmlspecialchars(
        (string) $valeur,
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
    <title>Modération | FantasyRealm Online</title>
    <style>
        body {
            background: #111827;
            color: white;
            font-family: Arial, sans-serif;
        }
        main {
            max-width: 850px;
            margin: 50px auto;
            padding: 20px;
        }
        h1 { color: #a78bfa; }
        a { color: #c4b5fd; }
        article {
            background: #1f2937;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
        }
        button {
            background: #7c3aed;
            color: white;
            border: 0;
            border-radius: 6px;
            padding: 12px 18px;
            cursor: pointer;
        }
    </style>
</head>
<body>
<main>
    <a href="espace.php">Retour à mon espace</a>

    <h1>Modération des personnages</h1>

    <?php if (!$personnages): ?>
        <p>Aucun personnage en attente de validation.</p>
    <?php endif; ?>

    <?php foreach ($personnages as $personnage): ?>
        <article>
            <h2><?= echapper($personnage['nom']) ?></h2>
            <p>Créateur : <?= echapper($personnage['pseudo']) ?></p>
            <p>Genre : <?= echapper($personnage['genre']) ?></p>

            <form method="POST">
                <input type="hidden" name="csrf_token"
                       value="<?= echapper($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="personnage_id"
                       value="<?= (int) $personnage['id'] ?>">
                <button type="submit" name="action" value="approuver">
                    Approuver le personnage
                </button>
            </form>
        </article>
    <?php endforeach; ?>
</main>
</body>
</html>
