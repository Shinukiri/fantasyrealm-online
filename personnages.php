<?php
require_once __DIR__ . '/config/database.php';

$genre = $_GET['genre'] ?? '';
$pseudo = trim($_GET['pseudo'] ?? '');
$dateDebut = $_GET['date_debut'] ?? '';
$dateFin = $_GET['date_fin'] ?? '';

$genres = ['masculin', 'feminin', 'autre'];

if ($genre !== '' && !in_array($genre, $genres, true)) {
    $genre = '';
}

function dateValide($date) {
    if (!is_string($date)) {
        return false;
    }

    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $date);

    return $d && $d->format('Y-m-d') === $date;
}

if (!dateValide($dateDebut)) {
    $dateDebut = '';
}

if (!dateValide($dateFin)) {
    $dateFin = '';
}

$sql = "
    SELECT p.id, p.nom, p.genre, p.image,
           p.date_creation, u.pseudo
    FROM personnages p
    JOIN utilisateurs u ON p.utilisateur_id = u.id
    WHERE p.statut = 'approuve'
      AND p.partage = 1
";

$parametres = [];

if ($genre !== '') {
    $sql .= " AND p.genre = :genre";
    $parametres['genre'] = $genre;
}

if ($pseudo !== '') {
    $sql .= " AND u.pseudo LIKE :pseudo";
    $parametres['pseudo'] = '%' . $pseudo . '%';
}

if ($dateDebut !== '') {
    $sql .= " AND p.date_creation >= :date_debut";
    $parametres['date_debut'] = $dateDebut . ' 00:00:00';
}

if ($dateFin !== '') {
    $sql .= " AND p.date_creation < :date_fin";
    $parametres['date_fin'] = (
        new DateTimeImmutable($dateFin)
    )->modify('+1 day')->format('Y-m-d') . ' 00:00:00';
}

$sql .= " ORDER BY p.date_creation DESC, p.id DESC LIMIT 100";

$requete = $pdo->prepare($sql);
$requete->execute($parametres);
$personnages = $requete->fetchAll();


$equipements = [];

if ($personnages) {
    $ids = array_column($personnages, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $requeteEquipements = $pdo->prepare(
        "SELECT pa.personnage_id, a.nom, a.categorie
         FROM personnage_accessoires pa
         JOIN accessoires a ON a.id = pa.accessoire_id
         WHERE pa.personnage_id IN ($placeholders)
         ORDER BY a.categorie, a.nom"
    );

    $requeteEquipements->execute($ids);

    foreach ($requeteEquipements->fetchAll() as $equipement) {
        $equipements[$equipement['personnage_id']][] = $equipement;
    }
}


function e($valeur) {
    return htmlspecialchars((string)$valeur, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Personnages publics | FantasyRealm Online</title>
    <style>
        body {
            margin: 0;
            background: #111827;
            color: white;
            font-family: Arial, sans-serif;
        }

        main {
            max-width: 1000px;
            margin: 40px auto;
            padding: 20px;
        }

        h1, h2 {
            color: #a78bfa;
        }

        a {
            color: #c4b5fd;
        }

        .filtres, .personnage {
            background: #1f2937;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-top: 12px;
        }

        input, select {
            padding: 10px;
            margin-top: 5px;
            max-width: 100%;
            box-sizing: border-box;
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

        .galerie {
            display: grid;
            grid-template-columns: repeat(
                auto-fit, minmax(250px, 1fr)
            );
            gap: 20px;
        }

        .personnage img {
            max-width: 100%;
            height: auto;
        }
    </style>
</head>
<body>
<main>
    <a href="index.php">Retour à l'accueil</a>

    <h1>Galerie des personnages</h1>

    <form class="filtres" method="GET">
        <h2>Rechercher un personnage</h2>

        <label for="genre">Genre</label>
        <select id="genre" name="genre">
            <option value="">Tous</option>
            <?php foreach ([
                'masculin' => 'Masculin',
                'feminin' => 'Féminin',
                'autre' => 'Autre'
            ] as $valeur => $libelle): ?>
                <option value="<?= e($valeur) ?>"
                    <?= $genre === $valeur ? 'selected' : '' ?>>
                    <?= e($libelle) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="pseudo">Pseudo du créateur</label>
        <input type="text" id="pseudo" name="pseudo"
               value="<?= e($pseudo) ?>" maxlength="50">

        <label for="date_debut">Créé à partir du</label>
        <input type="date" id="date_debut" name="date_debut"
               value="<?= e($dateDebut) ?>">

        <label for="date_fin">Créé jusqu'au</label>
        <input type="date" id="date_fin" name="date_fin"
               value="<?= e($dateFin) ?>">

        <p>
            <button type="submit">Filtrer</button>
            <a href="personnages.php">Réinitialiser</a>
        </p>
    </form>

    <?php if (!$personnages): ?>
        <p>Aucun personnage ne correspond à votre recherche.</p>
    <?php else: ?>
        <div class="galerie">
            <?php foreach ($personnages as $personnage): ?>
                <article class="personnage">
                    <h2><?= e($personnage['nom']) ?></h2>

                    <p>
                        <a href="avis-personnage.php?id=<?= (int)$personnage['id'] ?>">
                            Voir les avis / Donner mon avis
                        </a>
                    </p>
                    
                    <p>Créateur : <?= e($personnage['pseudo']) ?></p>
                    <p>Genre : <?= e($personnage['genre']) ?></p>
                    <p>Créé le : <?= e($personnage['date_creation']) ?></p>
                     
                    <h3>Équipements</h3>

                    <?php if (empty($equipements[$personnage['id']])): ?>
                        <p>Aucun équipement.</p>
                    <?php else: ?>
                        <ul>
                            <?php foreach ($equipements[$personnage['id']] as $equipement): ?>
                                <li>
                                    <?= e($equipement['nom']) ?>
                                    (<?= e($equipement['categorie']) ?>)
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <?php if ($personnage['image']): ?>
                        <img src="<?= e($personnage['image']) ?>"
                             alt="Personnage <?= e($personnage['nom']) ?>">
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>
</body>
</html>
