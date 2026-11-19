<?php
require_once __DIR__ . '/config/auth.php';

// Accès réservé aux administrateurs
if (($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Accès interdit.');
}

function e($valeur) {
    return htmlspecialchars(
        (string)$valeur,
        ENT_QUOTES,
        'UTF-8'
    );
}

// Récupérer les 100 dernières modifications
$requete = $pdo->query(
    "SELECT
        h.id,
        h.personnage_id,
        h.personnage_nom,
        h.type_modification,
        h.anciennes_valeurs,
        h.nouvelles_valeurs,
        h.date_modification,
        u.pseudo
     FROM historique_personnages h
     LEFT JOIN utilisateurs u
       ON u.id = h.utilisateur_id
     ORDER BY h.date_modification DESC, h.id DESC
     LIMIT 100"
);

$historique = $requete->fetchAll(PDO::FETCH_ASSOC);

// Afficher les données JSON de manière lisible
function afficherValeurs($json) {
    if ($json === null || $json === '') {
        return 'Aucune';
    }

    $donnees = json_decode($json, true);

    if (!is_array($donnees)) {
        return 'Données indisponibles';
    }

    return json_encode(
        $donnees,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
    );
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1">

    <title>
        Historique des personnages | FantasyRealm Online
    </title>

    <style>
        body {
            margin: 0;
            background: #111827;
            color: white;
            font-family: Arial, sans-serif;
        }

        main {
            max-width: 1100px;
            margin: 40px auto;
            padding: 20px;
        }

        h1 {
            color: #a78bfa;
        }

        a {
            color: #c4b5fd;
        }

        article {
            background: #1f2937;
            border-radius: 10px;
            padding: 20px;
            margin: 18px 0;
        }

        h2 {
            margin-top: 0;
        }

        .valeurs {
            display: grid;
            grid-template-columns:
                repeat(auto-fit, minmax(250px, 1fr));
            gap: 16px;
        }

        pre {
            background: #111827;
            padding: 15px;
            border-radius: 6px;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
        }

        .date {
            color: #d1d5db;
        }
    </style>
</head>
<body>
<main>

    <a href="espace.php">
        Retour à mon espace
    </a>

    <h1>Historique des modifications</h1>

    <p>
        Consultation réservée aux administrateurs.
        Les 100 dernières modifications sont affichées.
    </p>

    <?php if (!$historique): ?>
        <p>Aucune modification enregistrée.</p>
    <?php endif; ?>

    <?php foreach ($historique as $ligne): ?>

        <article>

            <h2>
                <?= e($ligne['personnage_nom']) ?>
            </h2>

            <p>
                Propriétaire :
                <?= e($ligne['pseudo'] ?? 'Compte supprimé') ?>
            </p>

            <p>
                Type :
                <?= e($ligne['type_modification']) ?>
            </p>

            <p class="date">
                Date :
                <?= e($ligne['date_modification']) ?>
            </p>

            <div class="valeurs">

                <div>
                    <h3>Anciennes valeurs</h3>
                    <pre><?= e(
                        afficherValeurs(
                            $ligne['anciennes_valeurs']
                        )
                    ) ?></pre>
                </div>

                <div>
                    <h3>Nouvelles valeurs</h3>
                    <pre><?= e(
                        afficherValeurs(
                            $ligne['nouvelles_valeurs']
                        )
                    ) ?></pre>
                </div>

            </div>

        </article>

    <?php endforeach; ?>

</main>
</body>
</html>
