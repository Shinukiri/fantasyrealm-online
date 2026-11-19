<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/envoyer-email.php';

function e($valeur) {
    return htmlspecialchars((string)$valeur, ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$erreur = '';
$succes = '';
$email = '';
$pseudo = '';
$message = '';
$utilisateurConnecte = false;

// Préremplir les champs si l'utilisateur est connecté
if (isset($_SESSION['utilisateur_id'])) {
    $requete = $pdo->prepare(
        "SELECT pseudo, email, statut
         FROM utilisateurs
         WHERE id = ?"
    );
    $requete->execute([$_SESSION['utilisateur_id']]);
    $utilisateur = $requete->fetch(PDO::FETCH_ASSOC);

    if ($utilisateur && $utilisateur['statut'] === 'actif') {
        $utilisateurConnecte = true;
        $email = $utilisateur['email'];
        $pseudo = $utilisateur['pseudo'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? null;

    $email = is_string($_POST['email'] ?? null)
        ? trim($_POST['email']) : '';

    $pseudo = is_string($_POST['pseudo'] ?? null)
        ? trim($_POST['pseudo']) : '';

    $message = is_string($_POST['message'] ?? null)
        ? trim($_POST['message']) : '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        $erreur = 'Formulaire invalide.';

    } elseif (
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        strlen($email) > 254
    ) {
        $erreur = 'Adresse e-mail invalide.';

    } elseif (
        mb_strlen($pseudo) < 3 ||
        mb_strlen($pseudo) > 100
    ) {
        $erreur = 'Pseudo invalide.';

    } elseif (
        mb_strlen($message) < 10 ||
        mb_strlen($message) > 5000
    ) {
        $erreur = 'Le message doit contenir entre 10 et 5000 caractères.';

    } else {

        // Vérifier que le pseudo appartient à un compte existant
        $requete = $pdo->prepare(
            "SELECT id, email
             FROM utilisateurs
             WHERE pseudo = ?
               AND statut = 'actif'"
        );
        $requete->execute([$pseudo]);
        $compte = $requete->fetch(PDO::FETCH_ASSOC);

        if (!$compte) {
            $erreur = 'Ce pseudo ne correspond à aucun compte actif.';

        } elseif (
            $utilisateurConnecte &&
            (
                $pseudo !== $utilisateur['pseudo'] ||
                strcasecmp($email, $utilisateur['email']) !== 0
            )
        ) {
            $erreur = 'Utilisez les informations de votre compte connecté.';

        } else {

            // Ne pas considérer l'adresse saisie comme vérifiée :
            // elle est simplement incluse dans le corps du message.
            $contenu =
                "Nouveau message de contact\n\n" .
                "Pseudo : " . $pseudo . "\n" .
                "E-mail déclaré : " . $email . "\n\n" .
                "Message :\n" . $message . "\n\n" .
                "FantasyRealm Online";

            $envoye = envoyerEmail(
                'naroki78@gmail.com',
                'Nouveau message - FantasyRealm Online',
                $contenu
            );

            if ($envoye) {
                $succes = 'Votre message a bien été envoyé.';
                $message = '';
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            } else {
                $erreur = 'Envoi impossible. Veuillez réessayer plus tard.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Contact | FantasyRealm Online</title>

    <style>
        body {
            margin: 0;
            background: #111827;
            color: white;
            font-family: Arial, sans-serif;
        }

        main {
            max-width: 650px;
            margin: 40px auto;
            padding: 25px;
            background: #1f2937;
            border-radius: 10px;
        }

        h1 {
            color: #a78bfa;
        }

        a {
            color: #c4b5fd;
        }

        label {
            display: block;
            margin-top: 18px;
        }

        input, textarea {
            width: 100%;
            box-sizing: border-box;
            padding: 12px;
            margin-top: 6px;
            border-radius: 5px;
            border: 1px solid #6b7280;
        }

        button {
            margin-top: 20px;
            background: #7c3aed;
            color: white;
            border: none;
            border-radius: 6px;
            padding: 12px 20px;
            cursor: pointer;
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

    <a href="index.php">Retour à l'accueil</a>

    <h1>Contacter PixelVerse Studios</h1>

    <p>
        Une question concernant FantasyRealm Online ?
        Envoyez-nous votre message.
    </p>

    <?php if ($erreur): ?>
        <p class="erreur" role="alert"><?= e($erreur) ?></p>
    <?php endif; ?>

    <?php if ($succes): ?>
        <p class="succes" role="status"><?= e($succes) ?></p>
    <?php endif; ?>

    <form method="POST">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= e($_SESSION['csrf_token']) ?>"
        >

        <label for="email">Adresse e-mail</label>
        <input
            type="email"
            id="email"
            name="email"
            value="<?= e($email) ?>"
            maxlength="254"
            autocomplete="email"
            required
        >

        <label for="pseudo">Pseudo</label>
        <input
            type="text"
            id="pseudo"
            name="pseudo"
            value="<?= e($pseudo) ?>"
            minlength="3"
            maxlength="100"
            required
        >

        <label for="message">Message</label>
        <textarea
            id="message"
            name="message"
            rows="7"
            minlength="10"
            maxlength="5000"
            required
        ><?= e($message) ?></textarea>

        <button type="submit">Envoyer le message</button>

    </form>

</main>
</body>
</html>
