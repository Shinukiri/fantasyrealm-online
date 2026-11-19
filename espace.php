<?php
// Vérifier l'authentification et le statut du compte
require_once __DIR__ . '/config/auth.php';

// Sécuriser l'affichage du pseudonyme
$pseudo = htmlspecialchars(
    $_SESSION['pseudo'],
    ENT_QUOTES,
    'UTF-8'
);

// Récupérer le rôle actualisé par auth.php
$role = $_SESSION['role'];

// Déterminer les droits d'accès
$estPersonnel = in_array(
    $role,
    ['employe', 'admin'],
    true
);

$estAdministrateur = $role === 'admin';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Mon espace | FantasyRealm Online</title>

    <style>
        body {
            margin: 0;
            background: #111827;
            color: white;
            font-family: Arial, sans-serif;
        }

        main {
            max-width: 900px;
            margin: 60px auto;
            padding: 20px;
        }

        h1, h2 {
            color: #a78bfa;
        }

        a {
            color: #c4b5fd;
        }

        section {
            margin-top: 35px;
        }

        .bouton {
            display: inline-block;
            background: #7c3aed;
            color: white;
            padding: 12px 20px;
            border-radius: 6px;
            text-decoration: none;
            margin: 10px 10px 10px 0;
        }

        .bouton:hover {
            background: #6d28d9;
        }

        .deconnexion {
            display: inline-block;
            margin-top: 10px;
        }
    </style>
</head>

<body>
<main>

    <a href="index.php">Accueil</a>

    <h1>Bienvenue, <?= $pseudo ?> !</h1>

    <p>
        Vous êtes connecté à votre espace personnel
        FantasyRealm Online.
    </p>

    <!-- Fonctionnalités accessibles aux comptes actifs -->
    <section>
        <h2>Mes personnages</h2>

        <p>
            Créez et personnalisez vos héros.
        </p>

        <a class="bouton" href="creer-personnage.php">
            Créer un personnage
        </a>

        <a class="bouton" href="mes-personnages.php">
            Voir mes personnages
        </a>
    </section>

    <!-- Fonctionnalités réservées au personnel -->
    <?php if ($estPersonnel): ?>

        <section>
            <h2>Administration et modération</h2>

            <p>
                Gérez les contenus et les utilisateurs
                de FantasyRealm Online.
            </p>

            <a class="bouton" href="moderation-personnages.php">
                Modérer les personnages
            </a>

            <a class="bouton" href="moderation-avis.php">
                Modérer les avis
            </a>

            <a class="bouton" href="admin-accessoires.php">
                Gérer les accessoires
            </a>

            <a class="bouton" href="gestion-utilisateurs.php">
                Gérer les utilisateurs
            </a>
        </section>

    <?php endif; ?>

    <!-- Fonctionnalités réservées aux administrateurs -->
    <?php if ($estAdministrateur): ?>

        <section>
            <h2>Gestion administrative</h2>

            <p>
                Gérez les comptes du personnel.
            </p>

            <a class="bouton" href="gestion-employes.php">
                Gérer les employés
            </a>
        </section>

    <?php endif; ?>

    <!-- Gestion du compte -->
    <section>
        <h2>Mon compte</h2>

        <a class="deconnexion" href="deconnexion.php">
            Se déconnecter
        </a>
    </section>

</main>
</body>
</html>
