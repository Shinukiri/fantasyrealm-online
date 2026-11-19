<?php
require_once __DIR__ . '/config/database.php';

$erreur = '';
$succes = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pseudo = trim($_POST['pseudo'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erreur = "Adresse email invalide.";
    } elseif (strlen($pseudo) < 3 || strlen($pseudo) > 50) {
        $erreur = "Le pseudo doit contenir entre 3 et 50 caractères.";
    } elseif (
        strlen($password) < 8 ||
        !preg_match('/[A-Z]/', $password) ||
        !preg_match('/[a-z]/', $password) ||
        !preg_match('/[0-9]/', $password) ||
        !preg_match('/[^a-zA-Z0-9]/', $password)
    ) {
        $erreur = "Le mot de passe doit contenir au moins 8 caractères, une majuscule, une minuscule, un chiffre et un caractère spécial.";
    } else {
        try {
            $requete = $pdo->prepare(
                "INSERT INTO utilisateurs (email, pseudo, mot_de_passe)
                 VALUES (:email, :pseudo, :password)"
            );

            $requete->execute([
                'email' => $email,
                'pseudo' => $pseudo,
                'password' => password_hash($password, PASSWORD_DEFAULT)
            ]);

            $succes = "Compte créé avec succès !";
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $erreur = "Cet email ou ce pseudo est déjà utilisé.";
            } else {
                error_log($e->getMessage());
                $erreur = "Une erreur est survenue.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription | FantasyRealm Online</title>
    <style>
        body {
            background: #111827;
            color: white;
            font-family: Arial, sans-serif;
            line-height: 1.6;
        }
        main {
            max-width: 450px;
            margin: 60px auto;
            padding: 20px;
        }
        h1 { color: #a78bfa; }
        label { display: block; margin-top: 15px; }
        input {
            width: 100%;
            box-sizing: border-box;
            padding: 12px;
            margin-top: 5px;
        }
        button {
            margin-top: 20px;
            padding: 12px 20px;
            background: #7c3aed;
            color: white;
            border: 0;
            cursor: pointer;
        }
        a { color: #c4b5fd; }
        .erreur { color: #fca5a5; }
        .succes { color: #86efac; }
    </style>
</head>
<body>
    <main>
        <a href="index.php">Retour à l'accueil</a>
        <h1>Créer un compte</h1>

        <?php if ($erreur): ?>
            <p class="erreur" role="alert">
                <?= htmlspecialchars($erreur, ENT_QUOTES, 'UTF-8') ?>
            </p>
        <?php endif; ?>

        <?php if ($succes): ?>
            <p class="succes" role="status">
                <?= htmlspecialchars($succes, ENT_QUOTES, 'UTF-8') ?>
            </p>
            <a href="connexion.php">Se connecter</a>
        <?php else: ?>
            <form method="POST">
                <label for="email">Adresse email</label>
                <input type="email" id="email" name="email"
                       required maxlength="255" autocomplete="email">

                <label for="pseudo">Pseudo</label>
                <input type="text" id="pseudo" name="pseudo"
                       required minlength="3" maxlength="50">

                <label for="password">Mot de passe</label>
                <input type="password" id="password" name="password"
                       required minlength="8" autocomplete="new-password">

                <button type="submit">S'inscrire</button>
            </form>
        <?php endif; ?>
    </main>
</body>
</html>
