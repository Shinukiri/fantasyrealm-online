<?php
// Vérifie l'authentification et le statut actif du compte
require_once __DIR__ . '/config/auth.php';

// Réserver la gestion des accessoires aux employés et admins
// Le rôle est actualisé depuis la base par auth.php
if (!in_array($_SESSION['role'], ['employe', 'admin'], true)) {
    http_response_code(403);
    exit('Accès interdit.');
}

// Générer le jeton CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$erreur = '';
$message = '';

// Traiter les actions administratives
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Vérifier le jeton CSRF
    if (
        !is_string($_POST['csrf_token'] ?? null) ||
        !hash_equals(
            $_SESSION['csrf_token'],
            $_POST['csrf_token']
        )
    ) {
        http_response_code(403);
        exit('Jeton de sécurité invalide.');
    }

    $action = $_POST['action'] ?? '';

    $id = filter_var(
        $_POST['accessoire_id'] ?? null,
        FILTER_VALIDATE_INT
    );

    if ($action === 'ajouter') {

        // Récupérer les informations de l'accessoire
        $nom = is_string($_POST['nom'] ?? null)
            ? trim($_POST['nom'])
            : '';

        $categorie = $_POST['categorie'] ?? '';

        $categoriesAutorisees = [
            'vetement',
            'armure',
            'arme',
            'autre'
        ];

        // Vérifier les informations
        if (
            mb_strlen($nom) < 1 ||
            mb_strlen($nom) > 100 ||
            !in_array($categorie, $categoriesAutorisees, true)
        ) {
            $erreur = 'Nom ou catégorie invalide.';

        } else {

            try {
                // Ajouter un accessoire actif
                $requete = $pdo->prepare(
                    "INSERT INTO accessoires
                     (nom, categorie, actif)
                     VALUES (?, ?, 1)"
                );

                $requete->execute([
                    $nom,
                    $categorie
                ]);

                $message = 'Accessoire ajouté.';

            } catch (PDOException $e) {
                error_log($e->getMessage());
                $erreur = 'Impossible d’ajouter cet accessoire.';
            }
        }

    } elseif ($id && $id > 0 && $action === 'basculer') {

        try {
            // Activer ou désactiver un accessoire
            $requete = $pdo->prepare(
                "UPDATE accessoires
                 SET actif = NOT actif
                 WHERE id = ?"
            );

            $requete->execute([$id]);

            if ($requete->rowCount() > 0) {
                $message = 'Statut modifié.';
            } else {
                $erreur = 'Accessoire introuvable.';
            }

        } catch (PDOException $e) {
            error_log($e->getMessage());
            $erreur = 'Impossible de modifier cet accessoire.';
        }

    } elseif ($id && $id > 0 && $action === 'supprimer') {

        try {
            // Vérifier si l'accessoire est équipé
            $requete = $pdo->prepare(
                "SELECT COUNT(*)
                 FROM personnage_accessoires
                 WHERE accessoire_id = ?"
            );

            $requete->execute([$id]);

            $nombreUtilisations = (int)$requete->fetchColumn();

            if ($nombreUtilisations > 0) {

                $erreur = "Cet accessoire est équipé. Désactive-le plutôt.";

            } else {

                // Supprimer l'accessoire non utilisé
                $requete = $pdo->prepare(
                    "DELETE FROM accessoires
                     WHERE id = ?"
                );

                $requete->execute([$id]);

                if ($requete->rowCount() > 0) {
                    $message = 'Accessoire supprimé.';
                } else {
                    $erreur = 'Accessoire introuvable.';
                }
            }

        } catch (PDOException $e) {

            // Une contrainte SQL peut empêcher la suppression
            error_log($e->getMessage());

            $erreur = 'Suppression impossible : accessoire utilisé.';
        }

    } else {

        $erreur = 'Action invalide.';
    }
}

// Récupérer la liste des accessoires
$requete = $pdo->query(
    "SELECT id, nom, categorie, actif
     FROM accessoires
     ORDER BY categorie, nom"
);

$accessoires = $requete->fetchAll(PDO::FETCH_ASSOC);

// Échapper les données affichées
function e($valeur) {
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
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>
        Administration des accessoires | FantasyRealm Online
    </title>

    <style>
        body {
            background: #111827;
            color: white;
            font-family: Arial, sans-serif;
        }

        main {
            max-width: 850px;
            margin: 40px auto;
            padding: 20px;
        }

        h1, h2 {
            color: #a78bfa;
        }

        a {
            color: #c4b5fd;
        }

        section, article {
            background: #1f2937;
            padding: 20px;
            border-radius: 10px;
            margin: 15px 0;
        }

        label {
            display: block;
            margin-top: 12px;
        }

        input, select {
            padding: 10px;
        }

        button {
            background: #7c3aed;
            color: white;
            border: none;
            padding: 10px 14px;
            border-radius: 6px;
            margin: 5px;
            cursor: pointer;
        }

        button:hover {
            background: #6d28d9;
        }

        .danger {
            background: #991b1b;
        }

        .danger:hover {
            background: #7f1d1d;
        }

        .erreur {
            color: #fca5a5;
        }

        .succes {
            color: #86efac;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
        }
    </style>
</head>
<body>
<main>

    <a href="espace.php">
        Retour à mon espace
    </a>

    <h1>Gestion des accessoires</h1>

    <?php if ($erreur): ?>
        <p class="erreur" role="alert">
            <?= e($erreur) ?>
        </p>
    <?php endif; ?>

    <?php if ($message): ?>
        <p class="succes" role="status">
            <?= e($message) ?>
        </p>
    <?php endif; ?>

    <section>
        <h2>Ajouter un accessoire</h2>

        <form method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($_SESSION['csrf_token']) ?>"
            >

            <input
                type="hidden"
                name="action"
                value="ajouter"
            >

            <label for="nom">Nom</label>

            <input
                id="nom"
                name="nom"
                maxlength="100"
                required
            >

            <label for="categorie">Catégorie</label>

            <select
                id="categorie"
                name="categorie"
                required
            >
                <option value="arme">Arme</option>
                <option value="armure">Armure</option>
                <option value="vetement">Vêtement</option>
                <option value="autre">Autre</option>
            </select>

            <p>
                <button type="submit">
                    Ajouter
                </button>
            </p>

        </form>
    </section>

    <h2>Accessoires existants</h2>

    <?php if (!$accessoires): ?>
        <p>Aucun accessoire enregistré.</p>
    <?php endif; ?>

    <?php foreach ($accessoires as $accessoire): ?>

        <article>

            <h3><?= e($accessoire['nom']) ?></h3>

            <p>
                Catégorie :
                <?= e($accessoire['categorie']) ?>
            </p>

            <p>
                Statut :
                <?= $accessoire['actif']
                    ? 'Actif'
                    : 'Désactivé' ?>
            </p>

            <div class="actions">

                <!-- Activation / désactivation -->
                <form method="POST">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e($_SESSION['csrf_token']) ?>"
                    >

                    <input
                        type="hidden"
                        name="action"
                        value="basculer"
                    >

                    <input
                        type="hidden"
                        name="accessoire_id"
                        value="<?= (int)$accessoire['id'] ?>"
                    >

                    <button type="submit">
                        <?= $accessoire['actif']
                            ? 'Désactiver'
                            : 'Activer' ?>
                    </button>

                </form>

                <!-- Suppression -->
                <form
                    method="POST"
                    onsubmit="return confirm('Supprimer cet accessoire ?');"
                >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e($_SESSION['csrf_token']) ?>"
                    >

                    <input
                        type="hidden"
                        name="action"
                        value="supprimer"
                    >

                    <input
                        type="hidden"
                        name="accessoire_id"
                        value="<?= (int)$accessoire['id'] ?>"
                    >

                    <button
                        class="danger"
                        type="submit"
                    >
                        Supprimer
                    </button>

                </form>

            </div>

        </article>

    <?php endforeach; ?>

</main>
</body>
</html>
