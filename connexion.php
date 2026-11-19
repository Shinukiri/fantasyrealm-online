<?php
session_start();
require_once __DIR__ . '/config/database.php';

$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $requete = $pdo->prepare(
        "SELECT id, pseudo, mot_de_passe, role, statut,
                changement_mdp_requis
         FROM utilisateurs
         WHERE email = :email"
    );

    $requete->execute(['email' => $email]);
    $utilisateur = $requete->fetch();

    if (
        !$utilisateur ||
        !password_verify($password, $utilisateur['mot_de_passe'])
    ) {
        $erreur = "Email ou mot de passe incorrect.";
    } elseif ($utilisateur['statut'] !== 'actif') {
        $erreur = "Ce compte est suspendu.";
    } else {
        session_regenerate_id(true);

        $_SESSION['utilisateur_id'] = $utilisateur['id'];
        $_SESSION['pseudo'] = $utilisateur['pseudo'];
        $_SESSION['role'] = $utilisateur['role'];

        if ($utilisateur['changement_mdp_requis']) {
            header('Location: changer-mot-de-passe.php');
        } else {
            header('Location: espace.php');
        }
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion | FantasyRealm Online</title>
    <style>
        body {
            background: #111827;
            color: white;
            font-family: Arial, sans-serif;
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
            border: none;
            cursor: pointer;
        }
        a { color: #c4b5fd; }
        .erreur { color: #fca5a5; }
    </style>
</head>
<body>
    <main>
        <a href="index.php">Retour à l'accueil</a>
        <h1>Connexion</h1>

        <?php if ($erreur): ?>
            <p class="erreur" role="alert">
                <?= htmlspecialchars($erreur, ENT_QUOTES, 'UTF-8') ?>
            </p>
        <?php endif; ?>

        <form method="POST">
            <label for="email">Adresse email</label>
            <input type="email" id="email" name="email"
                   required autocomplete="email">

            <label for="password">Mot de passe</label>
            <input type="password" id="password" name="password"
                   required autocomplete="current-password">

            <button type="submit">Se connecter</button>
        </form>

        <p>
            <a href="inscription.php">Créer un compte</a>
        </p>
    </main>
</body>
</html>
