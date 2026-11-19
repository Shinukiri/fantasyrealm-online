<?php
// Vérifier la connexion et le statut actif du compte
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/journal.php';

// Vérifier l'identifiant du personnage
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    http_response_code(400);
    exit('Identifiant invalide.');
}

// Vérifier que le personnage appartient à l'utilisateur
// et qu'il a été approuvé
$requete = $pdo->prepare(
    "SELECT * FROM personnages
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

// Générer le jeton CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$erreur = '';

// Traiter les modifications
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

    // Valeurs autorisées
    $formes = ['rond', 'fin', 'large'];
    $genres = ['masculin', 'feminin', 'autre'];

    // Récupérer les valeurs du formulaire
    $genre = $_POST['genre'] ?? '';
    $yeux = $_POST['yeux_forme'] ?? '';
    $nez = $_POST['nez_forme'] ?? '';
    $bouche = $_POST['bouche_forme'] ?? '';
    $peau = $_POST['couleur_peau'] ?? '';
    $cheveux = $_POST['couleur_cheveux'] ?? '';
    $couleurYeux = $_POST['couleur_yeux'] ?? '';

    // Valider les caractéristiques
    if (
        !in_array($genre, $genres, true) ||
        !in_array($yeux, $formes, true) ||
        !in_array($nez, $formes, true) ||
        !in_array($bouche, $formes, true) ||
        !is_string($peau) ||
        !is_string($cheveux) ||
        !is_string($couleurYeux) ||
        !preg_match('/^#[0-9a-fA-F]{6}$/', $peau) ||
        !preg_match('/^#[0-9a-fA-F]{6}$/', $cheveux) ||
        !preg_match('/^#[0-9a-fA-F]{6}$/', $couleurYeux)
    ) {
        $erreur = 'Caractéristiques invalides.';

    } else {

        // Associer les champs aux nouvelles valeurs
        $nouvellesDonnees = [
            'genre' => $genre,
            'yeux_forme' => $yeux,
            'nez_forme' => $nez,
            'bouche_forme' => $bouche,
            'couleur_peau' => $peau,
            'couleur_cheveux' => $cheveux,
            'couleur_yeux' => $couleurYeux
        ];

        try {
            // Démarrer une transaction pour garantir
            // la cohérence entre modification et historique
            $pdo->beginTransaction();

            // Relire et verrouiller le personnage
            // pour récupérer ses valeurs les plus récentes
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

            $personnageActuel = $requete->fetch(PDO::FETCH_ASSOC);

            if (!$personnageActuel) {
                throw new RuntimeException(
                    'Personnage introuvable ou non autorisé.'
                );
            }

            // Comparer les anciennes et nouvelles valeurs
            $anciennesValeurs = [];
            $nouvellesValeurs = [];

            foreach ($nouvellesDonnees as $champ => $valeur) {

                if ($personnageActuel[$champ] !== $valeur) {

                    $anciennesValeurs[$champ] =
                        $personnageActuel[$champ];

                    $nouvellesValeurs[$champ] = $valeur;
                }
            }

            // Ne modifier la base que si un champ a changé
            if (!empty($nouvellesValeurs)) {

                // Mettre à jour le personnage
                $requete = $pdo->prepare(
                    "UPDATE personnages
                     SET genre = ?,
                         yeux_forme = ?,
                         nez_forme = ?,
                         bouche_forme = ?,
                         couleur_peau = ?,
                         couleur_cheveux = ?,
                         couleur_yeux = ?
                     WHERE id = ?
                       AND utilisateur_id = ?
                       AND statut = 'approuve'"
                );

                $requete->execute([
                    $genre,
                    $yeux,
                    $nez,
                    $bouche,
                    $peau,
                    $cheveux,
                    $couleurYeux,
                    $id,
                    $_SESSION['utilisateur_id']
                ]);

                // Encoder les changements au format JSON
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
                    'apparence',
                    $ancienJSON,
                    $nouveauJSON
                ]);
            }

            // Valider les opérations MySQL ensemble
            $pdo->commit();

            // Journaliser dans MongoDB uniquement
            // lorsqu'une modification a réellement eu lieu.
            if (!empty($nouvellesValeurs)) {

                enregistrerEvenement(
                    'modification_personnage',
                    (int) $_SESSION['utilisateur_id'],
                    (int) $id,
                    [
                        'nom' => $personnageActuel['nom'],
                        'type_modification' => 'apparence',
                        'anciennes_valeurs' => $anciennesValeurs,
                        'nouvelles_valeurs' => $nouvellesValeurs
                    ]
                );
            }

            header('Location: mes-personnages.php');
            exit;

        } catch (Throwable $exception) {

            // Annuler les opérations en cas d'erreur
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log($exception->getMessage());

            $erreur =
                'Une erreur est survenue pendant la modification.';
        }
    }
}

// Échapper les valeurs affichées
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

    <title>
        Modifier un personnage | FantasyRealm Online
    </title>

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

        select, input {
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
            border-radius: 6px;
            cursor: pointer;
        }

        a {
            color: #c4b5fd;
        }

        .erreur {
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
        Modifier <?= e($personnage['nom']) ?>
    </h1>

    <?php if ($erreur): ?>
        <p class="erreur" role="alert">
            <?= e($erreur) ?>
        </p>
    <?php endif; ?>

    <form method="POST">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= e($_SESSION['csrf_token']) ?>"
        >

        <label for="genre">Genre</label>

        <select id="genre" name="genre">

            <?php foreach ([
                'masculin' => 'Masculin',
                'feminin' => 'Féminin',
                'autre' => 'Autre'
            ] as $valeur => $libelle): ?>

                <option
                    value="<?= e($valeur) ?>"
                    <?= $personnage['genre'] === $valeur
                        ? 'selected'
                        : '' ?>
                >
                    <?= e($libelle) ?>
                </option>

            <?php endforeach; ?>

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

                <?php foreach (
                    ['rond', 'fin', 'large'] as $forme
                ): ?>

                    <option
                        value="<?= e($forme) ?>"
                        <?= $personnage[$champ] === $forme
                            ? 'selected'
                            : '' ?>
                    >
                        <?= e(ucfirst($forme)) ?>
                    </option>

                <?php endforeach; ?>

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
                value="<?= e($personnage[$champ]) ?>"
            >

        <?php endforeach; ?>

        <button type="submit">
            Enregistrer les modifications
        </button>

    </form>

</main>
</body>
</html>
