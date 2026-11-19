<?php
require_once __DIR__ . '/config/auth.php';

$erreur = '';
$utilisateurId = (int) $_SESSION['utilisateur_id'];

// Vérifier si un changement de mot de passe est obligatoire
$requete = $pdo->prepare(
    "SELECT changement_mdp_requis
     FROM utilisateurs
     WHERE id = ?"
);
$requete->execute([$utilisateurId]);

$changementObligatoire = (bool) $requete->fetchColumn();

if (!$changementObligatoire) {
    header('Location: espace.php');
    exit;
}

// Générer le jeton CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

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

    $nouveauMotDePasse = $_POST['mot_de_passe'] ?? '';
    $confirmation = $_POST['confirmation'] ?? '';

    if (
        !is_string($nouveauMotDePasse) ||
        !is_string($confirmation)
    ) {
        $erreur = 'Données invalides.';
    } elseif (
        strlen($nouveauMotDePasse) < 12 ||
        strlen($nouveauMotDePasse) > 72 ||
        !preg_match('/[A-Z]/', $nouveauMotDePasse) ||
        !preg_match('/[a-z]/', $nouveauMotDePasse) ||
        !preg_match('/[0-9]/', $nouveauMotDePasse) ||
        !preg_match('/[^A-Za-z0-9]/', $nouveauMotDePasse)
    ) {
        $erreur = 'Le mot de passe doit contenir entre 12 et 72 caractères, avec une majuscule, une minuscule, un chiffre et un caractère spécial.';
    } elseif ($nouveauMotDePasse !== $confirmation) {
        $erreur = 'Les mots de passe ne correspondent pas.';
    } else {

        $hash = password_hash(
            $nouveauMotDePasse,
            PASSWORD_DEFAULT
        );

        $requete = $pdo->prepare(
            "UPDATE utilisateurs
             SET mot_de_passe = ?,
                 changement_mdp_requis = 0
             WHERE id = ?
               AND changement_mdp_requis = 1"
        );

        $requete->execute([
            $hash,
            $utilisateurId
        ]);

        // Renouveler la session après le changement
        session_regenerate_id(true);

        header('Location: espace.php');
        exit;
    }
}

function e($valeur) {
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
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Changer mon mot de passe</title>

    <style>
        body {
            background: #111827;
            color: white;
            font-family: Arial, sans-serif;
        }

        main {
            max-width: 500px;
            margin: 60px auto;
            padding: 25px;
            background: #1f2937;
            border-radius: 10px;
        }

        h1 {
            color: #a78bfa;
        }

        label {
            display: block;
            margin-top: 18px;
        }

        input {
            width: 100%;
            box-sizing: border-box;
            padding: 12px;
            margin-top: 6px;
        }

        button {
            background: #7c3aed;
            color: white;
            border: none;
            border-radius: 6px;
            padding: 12px 20px;
            margin-top: 20px;
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

    <h1>Changer votre mot de passe</h1>

    <p>
        Pour sécuriser votre compte, vous devez
        remplacer votre mot de passe temporaire
        avant de continuer.
    </p>

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

        <label for="mot_de_passe">
            Nouveau mot de passe
        </label>

        <input
            type="password"
            id="mot_de_passe"
            name="mot_de_passe"
            minlength="12"
            maxlength="72"
            autocomplete="new-password"
            required
        >

        <label for="confirmation">
            Confirmer le nouveau mot de passe
        </label>

        <input
            type="password"
            id="confirmation"
            name="confirmation"
            minlength="12"
            maxlength="72"
            autocomplete="new-password"
            required
        >

        <button type="submit">
            Enregistrer mon nouveau mot de passe
        </button>

    </form>

    <p>
        <a href="deconnexion.php">Se déconnecter</a>
    </p>

</main>
</body>
</html>
