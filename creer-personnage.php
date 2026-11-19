<?php
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/journal.php';

// Générer un jeton de sécurité pour le formulaire
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$erreur = '';
$succes = '';

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

    $nom = trim($_POST['nom'] ?? '');
    $genre = $_POST['genre'] ?? '';
    $yeux = $_POST['yeux_forme'] ?? '';
    $nez = $_POST['nez_forme'] ?? '';
    $bouche = $_POST['bouche_forme'] ?? '';
    $peau = $_POST['couleur_peau'] ?? '';
    $cheveux = $_POST['couleur_cheveux'] ?? '';
    $couleurYeux = $_POST['couleur_yeux'] ?? '';

    $genres = ['masculin', 'feminin', 'autre'];
    $formes = ['rond', 'fin', 'large'];

    if (mb_strlen($nom) < 3 || mb_strlen($nom) > 100) {
        $erreur = "Le nom doit contenir entre 3 et 100 caractères.";
    } elseif (
        !in_array($genre, $genres, true) ||
        !in_array($yeux, $formes, true) ||
        !in_array($nez, $formes, true) ||
        !in_array($bouche, $formes, true) ||
        !preg_match('/^#[0-9a-fA-F]{6}$/', $peau) ||
        !preg_match('/^#[0-9a-fA-F]{6}$/', $cheveux) ||
        !preg_match('/^#[0-9a-fA-F]{6}$/', $couleurYeux)
    ) {
        $erreur = "Les caractéristiques sélectionnées sont invalides.";
    } else {
        try {
            $requete = $pdo->prepare(
                "INSERT INTO personnages
                (utilisateur_id, nom, genre, yeux_forme, nez_forme,
                 bouche_forme, couleur_peau, couleur_cheveux, couleur_yeux)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );

            $requete->execute([
                $_SESSION['utilisateur_id'],
                $nom,
                $genre,
                $yeux,
                $nez,
                $bouche,
                $peau,
                $cheveux,
                $couleurYeux
            ]);

            // Récupérer l'identifiant du personnage créé dans MySQL.
            $personnageId = (int) $pdo->lastInsertId();

            // Enregistrer la création du personnage dans MongoDB.
            enregistrerEvenement(
                'creation_personnage',
                (int) $_SESSION['utilisateur_id'],
                $personnageId,
                [
                    'nom' => $nom,
                    'genre' => $genre,
                    'statut' => 'en_attente'
                ]
            );

            $succes = "Personnage créé ! Il attend la validation d'un employé.";

        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $erreur = "Ce nom de personnage est déjà utilisé.";
            } else {
                error_log($e->getMessage());
                $erreur = "Une erreur est survenue.";
            }
        }
    }
}

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
    <title>Créer un personnage | FantasyRealm Online</title>
    <style>
        body {
            background: #111827;
            color: white;
            font-family: Arial, sans-serif;
        }

        main {
            max-width: 550px;
            margin: 40px auto;
            padding: 20px;
        }

        h1 {
            color: #a78bfa;
        }

        label {
            display: block;
            margin-top: 16px;
        }

        input, select {
            width: 100%;
            box-sizing: border-box;
            padding: 10px;
            margin-top: 5px;
        }

        input[type="color"] {
            height: 50px;
        }

        button {
            margin-top: 25px;
            padding: 12px 20px;
            background: #7c3aed;
            color: white;
            border: none;
            cursor: pointer;
        }

        a {
            color: #c4b5fd;
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
    <a href="espace.php">Retour à mon espace</a>

    <h1>Créer un personnage</h1>

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

    <form method="POST">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= e($_SESSION['csrf_token']) ?>"
        >

        <label for="nom">Nom du personnage</label>
        <input
            id="nom"
            name="nom"
            required
            minlength="3"
            maxlength="100"
        >

        <label for="genre">Genre</label>
        <select id="genre" name="genre" required>
            <option value="masculin">Masculin</option>
            <option value="feminin">Féminin</option>
            <option value="autre">Autre</option>
        </select>

        <?php foreach ([
            'yeux_forme' => 'Forme des yeux',
            'nez_forme' => 'Forme du nez',
            'bouche_forme' => 'Forme de la bouche'
        ] as $champ => $libelle): ?>

            <label for="<?= e($champ) ?>">
                <?= e($libelle) ?>
            </label>

            <select
                id="<?= e($champ) ?>"
                name="<?= e($champ) ?>"
            >
                <option value="rond">Rond</option>
                <option value="fin">Fin</option>
                <option value="large">Large</option>
            </select>

        <?php endforeach; ?>

        <?php foreach ([
            'couleur_peau' => 'Couleur de peau',
            'couleur_cheveux' => 'Couleur des cheveux',
            'couleur_yeux' => 'Couleur des yeux'
        ] as $champ => $libelle): ?>

            <label for="<?= e($champ) ?>">
                <?= e($libelle) ?>
            </label>

            <input
                type="color"
                id="<?= e($champ) ?>"
                name="<?= e($champ) ?>"
                value="#a78bfa"
            >

        <?php endforeach; ?>

        <button type="submit">
            Créer mon personnage
        </button>

    </form>
</main>
</body>
</html>
