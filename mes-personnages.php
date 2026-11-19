<?php
// Vérification centralisée de l'authentification
// et du statut actif de l'utilisateur
require_once __DIR__ . '/config/auth.php';

// Récupérer les personnages de l'utilisateur connecté
$requete = $pdo->prepare(
    "SELECT id, nom, genre, statut, partage, date_creation
     FROM personnages
     WHERE utilisateur_id = ?
     ORDER BY date_creation DESC, id DESC"
);

$requete->execute([$_SESSION['utilisateur_id']]);
$personnages = $requete->fetchAll(PDO::FETCH_ASSOC);

// Générer le jeton CSRF pour les formulaires
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Protéger les données affichées contre les injections HTML
function echapper($valeur) {
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
    <title>Mes personnages | FantasyRealm Online</title>
    <style>
        body {
            margin: 0;
            background: #111827;
            color: white;
            font-family: Arial, sans-serif;
        }

        main {
            max-width: 900px;
            margin: 50px auto;
            padding: 20px;
        }

        h1 {
            color: #a78bfa;
        }

        a {
            color: #c4b5fd;
        }

        .personnage {
            background: #1f2937;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
        }

        .personnage h2 {
            margin-top: 0;
        }

        .bouton {
            display: inline-block;
            background: #7c3aed;
            color: white;
            padding: 10px 16px;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            cursor: pointer;
            margin: 5px 0;
        }

        .bouton:hover {
            background: #6d28d9;
        }

        .danger {
            background: #991b1b;
        }

        .danger:hover {
            background: #7f1d1d;
        }

        label {
            display: block;
            margin-top: 10px;
        }

        input[type="text"] {
            padding: 10px;
            margin: 8px 0;
            max-width: 100%;
            box-sizing: border-box;
        }

        form {
            margin: 15px 0;
        }
    </style>
</head>
<body>
<main>
    <a href="espace.php">Retour à mon espace</a>

    <h1>Mes personnages</h1>

    <p>
        <a class="bouton" href="creer-personnage.php">
            Créer un personnage
        </a>
    </p>

    <?php if (!$personnages): ?>
        <p>Vous n'avez pas encore créé de personnage.</p>
    <?php endif; ?>

    <?php foreach ($personnages as $personnage): ?>
        <article class="personnage">

            <h2><?= echapper($personnage['nom']) ?></h2>

            <p>
                Genre :
                <?= echapper($personnage['genre']) ?>
            </p>

            <p>
                Statut :
                <?= $personnage['statut'] === 'approuve'
                    ? 'Approuvé'
                    : 'En attente de validation' ?>
            </p>

            <p>
                Partage :
                <?= $personnage['partage']
                    ? 'Public'
                    : 'Privé' ?>
            </p>

            <p>
                Créé le :
                <?= echapper($personnage['date_creation']) ?>
            </p>

            <?php if ($personnage['statut'] === 'approuve'): ?>

                <p>
                    <a class="bouton"
                       href="modifier-personnage.php?id=<?= (int)$personnage['id'] ?>">
                        Modifier l'apparence
                    </a>
                </p>

                <p>
                    <a class="bouton"
                       href="equipements.php?id=<?= (int)$personnage['id'] ?>">
                        Gérer les équipements
                    </a>
                </p>

                <form method="POST" action="partager-personnage.php">
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= echapper($_SESSION['csrf_token']) ?>"
                    >

                    <input
                        type="hidden"
                        name="personnage_id"
                        value="<?= (int)$personnage['id'] ?>"
                    >

                    <button class="bouton" type="submit">
                        <?= $personnage['partage']
                            ? 'Rendre privé'
                            : 'Partager publiquement' ?>
                    </button>
                </form>

                <form method="POST" action="dupliquer-personnage.php">
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= echapper($_SESSION['csrf_token']) ?>"
                    >

                    <input
                        type="hidden"
                        name="personnage_id"
                        value="<?= (int)$personnage['id'] ?>"
                    >

                    <label for="nouveau_nom_<?= (int)$personnage['id'] ?>">
                        Nom du personnage dupliqué
                    </label>

                    <input
                        type="text"
                        id="nouveau_nom_<?= (int)$personnage['id'] ?>"
                        name="nouveau_nom"
                        minlength="3"
                        maxlength="100"
                        required
                    >

                    <button class="bouton" type="submit">
                        Dupliquer le personnage
                    </button>
                </form>

            <?php endif; ?>

            <form
                method="POST"
                action="supprimer-personnage.php"
                onsubmit="return confirm('Supprimer définitivement ce personnage ?');"
            >
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= echapper($_SESSION['csrf_token']) ?>"
                >

                <input
                    type="hidden"
                    name="personnage_id"
                    value="<?= (int)$personnage['id'] ?>"
                >

                <button class="bouton danger" type="submit">
                    Supprimer le personnage
                </button>
            </form>

        </article>
    <?php endforeach; ?>
</main>
</body>
</html>
