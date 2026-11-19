<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$estConnecte = isset($_SESSION['utilisateur_id']);

$pseudo = $estConnecte
    ? (string) ($_SESSION['pseudo'] ?? '')
    : '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>FantasyRealm Online | PixelVerse Studios</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #111827;
            color: #f9fafb;
            line-height: 1.6;
        }

        header,
        footer {
            background: #1f2937;
            padding: 20px;
        }

        nav {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            align-items: center;
        }

        a {
            color: #c4b5fd;
        }

        a:hover {
            color: #ddd6fe;
        }

        a:focus-visible,
        .bouton:focus-visible {
            outline: 3px solid #fbbf24;
            outline-offset: 4px;
        }

        main {
            max-width: 1000px;
            margin: auto;
            padding: 50px 20px;
        }

        section {
            margin-bottom: 40px;
        }

        h1,
        h2 {
            color: #a78bfa;
        }

        .bouton {
            display: inline-block;
            padding: 12px 20px;
            background: #7c3aed;
            color: #ffffff;
            border-radius: 6px;
            text-decoration: none;
        }

        .bouton:hover {
            background: #6d28d9;
            color: #ffffff;
        }

        .utilisateur {
            color: #f9fafb;
            font-weight: bold;
        }

        footer nav {
            margin-bottom: 20px;
        }

        footer p {
            margin: 0;
            color: #d1d5db;
        }

        @media (max-width: 600px) {
            main {
                padding: 30px 16px;
            }
        }
    </style>
</head>

<body>

<header>
    <nav aria-label="Navigation principale">

        <a href="index.php" aria-current="page">
            Accueil
        </a>

        <a href="personnages.php">
            Personnages
        </a>

        <a href="contact.php">
            Contact
        </a>

        <?php if ($estConnecte): ?>

            <a href="espace.php">
                Mon espace
            </a>

            <a href="deconnexion.php">
                Déconnexion
            </a>

            <?php if ($pseudo !== ''): ?>
                <span class="utilisateur">
                    Connecté :
                    <?= htmlspecialchars(
                        $pseudo,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </span>
            <?php endif; ?>

        <?php else: ?>

            <a href="connexion.php">
                Connexion
            </a>

            <a href="inscription.php">
                Inscription
            </a>

        <?php endif; ?>

    </nav>
</header>

<main>

    <section>
        <h1>FantasyRealm Online</h1>

        <p>
            Entrez dans un univers fantastique où
            votre imagination donne naissance
            à des héros uniques.
        </p>

        <p>
            Créez votre personnage, personnalisez
            son apparence et partagez vos créations
            avec la communauté.
        </p>

        <?php if ($estConnecte): ?>

            <a class="bouton" href="espace.php">
                Accéder à mon espace
            </a>

        <?php else: ?>

            <a class="bouton" href="inscription.php">
                Créer mon personnage
            </a>

        <?php endif; ?>
    </section>

    <section>
        <h2>Découvrez PixelVerse Studios</h2>

        <p>
            PixelVerse Studios est un studio
            spécialisé dans la création d'univers
            vidéoludiques et d'expériences
            interactives.
        </p>

        <p>
            Notre ambition est de placer la créativité
            et la communauté au cœur de chaque
            expérience.
        </p>
    </section>

    <section>
        <h2>Un univers MMORPG fantastique</h2>

        <p>
            FantasyRealm Online vous plonge
            dans un monde de fantasy peuplé
            de personnages extraordinaires.
        </p>

        <p>
            Imaginez votre héros, choisissez
            son apparence et équipez-le pour
            lui donner une identité unique.
        </p>
    </section>

    <section>
        <h2>Donnez vie à vos personnages</h2>

        <p>
            Personnalisez vos créations avec
            différents équipements et accessoires.
            Dupliquez vos personnages pour explorer
            de nouvelles possibilités.
        </p>
    </section>

    <section>
        <h2>Partagez avec la communauté</h2>

        <p>
            Découvrez les personnages partagés
            par les autres joueurs, consultez
            leurs créations et participez aux
            échanges grâce aux commentaires.
        </p>

        <a class="bouton" href="personnages.php">
            Découvrir les personnages
        </a>
    </section>

</main>

<footer>

    <nav aria-label="Informations légales">

        <a href="mentions-legales.php">
            Mentions légales
        </a>

        <a href="cgv.php">
            Conditions générales de vente
        </a>

        <a href="confidentialite.php">
            Politique de confidentialité
        </a>

        <a href="accessibilite.php">
            Accessibilité
        </a>

    </nav>

    <p>
        &copy; <?= date('Y') ?> PixelVerse Studios.
        Tous droits réservés sur les contenus
        dont le studio détient les droits.
    </p>

</footer>

</body>
</html>
