<?php
session_start();
require_once __DIR__ . '/config/database.php';

function e($valeur) {
    return htmlspecialchars((string)$valeur, ENT_QUOTES, 'UTF-8');
}

function motDePasseValide($motDePasse) {
    return strlen($motDePasse) >= 12
        && strlen($motDePasse) <= 72
        && preg_match('/[a-z]/', $motDePasse)
        && preg_match('/[A-Z]/', $motDePasse)
        && preg_match('/[0-9]/', $motDePasse)
        && preg_match('/[^a-zA-Z0-9]/', $motDePasse);
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$erreur = '';
$message = '';
$valide = false;
$termine = false;

$token = $_GET['token'] ?? '';

if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/i', $token)) {
    $erreur = 'Lien de réinitialisation invalide.';
} else {
    $tokenHash = hash('sha256', $token);

    $requete = $pdo->prepare(
        "SELECT id, utilisateur_id
         FROM reinitialisations_mdp
         WHERE token_hash = ?
           AND expiration > NOW()"
    );

    $requete->execute([$tokenHash]);
    $reinitialisation = $requete->fetch(PDO::FETCH_ASSOC);

    if (!$reinitialisation) {
        $erreur = 'Ce lien est invalide ou a expiré.';
    } else {
        $valide = true;
    }
}

if ($valide && $_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !is_string($_POST['csrf_token'] ?? null) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        http_response_code(403);
        exit('Requête non autorisée.');
    }

    $motDePasse = $_POST['mot_de_passe'] ?? '';
    $confirmation = $_POST['confirmation'] ?? '';

    if (
        !is_string($motDePasse) ||
        !is_string($confirmation) ||
        !motDePasseValide($motDePasse)
    ) {
        $erreur = 'Le mot de passe doit contenir entre 12 et 72 caractères, avec une minuscule, une majuscule, un chiffre et un caractère spécial.';

    } elseif ($motDePasse !== $confirmation) {
        $erreur = 'Les mots de passe ne correspondent pas.';

    } else {

        try {
            $pdo->beginTransaction();

            // Verrouiller le jeton pour empêcher sa réutilisation simultanée.
            $requete = $pdo->prepare(
                "SELECT id, utilisateur_id
                 FROM reinitialisations_mdp
                 WHERE token_hash = ?
                   AND expiration > NOW()
                 FOR UPDATE"
            );

            $requete->execute([$tokenHash]);
            $jeton = $requete->fetch(PDO::FETCH_ASSOC);
            $requete->closeCursor();

            if (!$jeton) {
                $pdo->rollBack();
                $valide = false;
                $erreur = 'Ce lien est invalide ou a expiré.';

            } else {
                $hash = password_hash(
                    $motDePasse,
                    PASSWORD_DEFAULT
                );

                $requete = $pdo->prepare(
                    "UPDATE utilisateurs
                     SET mot_de_passe = ?,
                         changement_mdp_requis = 0
                     WHERE id = ?"
                );

                $requete->execute([
                    $hash,
                    $jeton['utilisateur_id']
                ]);

                // Invalider tous les liens de réinitialisation du compte.
                $requete = $pdo->prepare(
                    "DELETE FROM reinitialisations_mdp
                     WHERE utilisateur_id = ?"
                );

                $requete->execute([
                    $jeton['utilisateur_id']
                ]);

                $pdo->commit();

                $termine = true;
                $valide = false;
                $message = 'Votre mot de passe a été réinitialisé avec succès.';
            }

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log($e->getMessage());
            $erreur = 'Une erreur est survenue. Veuillez réessayer.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Réinitialiser le mot de passe | FantasyRealm Online</title>

    <style>
        body {
            background: #111827;
            color: white;
            font-family: Arial, sans-serif;
            margin: 0;
        }

        main {
            max-width: 450px;
            margin: 80px auto;
            padding: 25px;
            background: #1f2937;
            border-radius: 10px;
        }

        h1 {
            color: #a78bfa;
        }

        label {
            display: block;
            margin-top: 15px;
        }

        input {
            width: 100%;
            box-sizing: border-box;
            padding: 10px;
            margin-top: 6px;
        }

        button {
            margin-top: 20px;
            padding: 12px 16px;
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

        .succes {
            color: #86efac;
        }
    </style>
</head>

<body>
<main>

    <h1>Réinitialiser votre mot de passe</h1>

    <?php if ($erreur): ?>
        <p class="erreur" role="alert"><?= e($erreur) ?></p>
    <?php endif; ?>

    <?php if ($message): ?>
        <p class="succes" role="status"><?= e($message) ?></p>
    <?php endif; ?>

    <?php if ($valide && !$termine): ?>

        <form method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($_SESSION['csrf_token']) ?>"
            >

            <label for="mot_de_passe">Nouveau mot de passe</label>
            <input
                type="password"
                id="mot_de_passe"
                name="mot_de_passe"
                minlength="12"
                maxlength="72"
                autocomplete="new-password"
                required
            >

            <label for="confirmation">Confirmer le mot de passe</label>
            <input
                type="password"
                id="confirmation"
                name="confirmation"
                minlength="12"
                maxlength="72"
                autocomplete="new-password"
                required
            >

            <p>
                12 à 72 caractères, avec une minuscule,
                une majuscule, un chiffre et un caractère spécial.
            </p>

            <button type="submit">
                Enregistrer le nouveau mot de passe
            </button>

        </form>

    <?php endif; ?>

    <p>
        <a href="connexion.php">Retour à la connexion</a>
    </p>

</main>
</body>
</html>
