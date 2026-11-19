<?php
// Cette page est publique, n'utiliser pas auth.php
// qui imposerait une connexion à tous les visiteurs.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/database.php';

// Échapper les données affichées
function e($valeur) {
    return htmlspecialchars(
        (string)$valeur,
        ENT_QUOTES,
        'UTF-8'
    );
}

// Vérifier si une session utilisateur existe
$utilisateurActif = false;

if (isset($_SESSION['utilisateur_id'])) {

    // Vérifier le statut réel du compte en base de données
    $requeteAuth = $pdo->prepare(
        "SELECT id, pseudo, role, statut
         FROM utilisateurs
         WHERE id = ?"
    );

    $requeteAuth->execute([
        $_SESSION['utilisateur_id']
    ]);

    $utilisateur = $requeteAuth->fetch(PDO::FETCH_ASSOC);

    if (
        $utilisateur &&
        $utilisateur['statut'] === 'actif'
    ) {
        $utilisateurActif = true;

        // Actualiser les informations de session
        $_SESSION['pseudo'] = $utilisateur['pseudo'];
        $_SESSION['role'] = $utilisateur['role'];

    } else {

        // Déconnecter un compte supprimé ou suspendu
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $parametres = session_get_cookie_params();

            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $parametres['path'],
                'domain' => $parametres['domain'],
                'secure' => $parametres['secure'],
                'httponly' => $parametres['httponly'],
                'samesite' => $parametres['samesite'] ?? 'Lax'
            ]);
        }

        session_destroy();
    }
}

// Récupérer l'identifiant du personnage
$id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$id || $id < 1) {
    http_response_code(400);
    exit('Identifiant invalide.');
}

// Vérifier que le personnage est approuvé et public
$requete = $pdo->prepare(
    "SELECT p.id, p.nom, u.pseudo
     FROM personnages p
     JOIN utilisateurs u
       ON u.id = p.utilisateur_id
     WHERE p.id = ?
       AND p.statut = 'approuve'
       AND p.partage = 1"
);

$requete->execute([$id]);
$personnage = $requete->fetch(PDO::FETCH_ASSOC);

if (!$personnage) {
    http_response_code(404);
    exit('Personnage introuvable ou privé.');
}

// Générer un jeton CSRF pour les utilisateurs actifs
if ($utilisateurActif && empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$erreur = '';
$succes = '';

// Traiter l'envoi d'un avis
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Vérifier que l'utilisateur est connecté et actif
    if (!$utilisateurActif) {

        http_response_code(403);
        $erreur = 'Connectez-vous avec un compte actif pour publier un avis.';

    } elseif (
        !is_string($_POST['csrf_token'] ?? null) ||
        !hash_equals(
            $_SESSION['csrf_token'],
            $_POST['csrf_token']
        )
    ) {

        $erreur = 'Formulaire invalide.';

    } else {

        // Récupérer la note
        $note = filter_var(
            $_POST['note'] ?? null,
            FILTER_VALIDATE_INT
        );

        // Récupérer le commentaire
        $commentaire = is_string($_POST['commentaire'] ?? null)
            ? trim($_POST['commentaire'])
            : '';

        // Vérifier les données
        if (
            $note === false ||
            $note === null ||
            $note < 1 ||
            $note > 5
        ) {

            $erreur = 'Choisissez une note entre 1 et 5.';

        } elseif (
            mb_strlen($commentaire) < 1 ||
            mb_strlen($commentaire) > 2000
        ) {

            $erreur = 'Le commentaire doit contenir entre 1 et 2000 caractères.';

        } else {

            try {

                // Enregistrer l'avis en attente de validation
                $requete = $pdo->prepare(
                    "INSERT INTO avis
                     (
                         personnage_id,
                         utilisateur_id,
                         note,
                         commentaire,
                         statut
                     )
                     VALUES (?, ?, ?, ?, 'en_attente')"
                );

                $requete->execute([
                    $id,
                    $_SESSION['utilisateur_id'],
                    $note,
                    $commentaire
                ]);

                $succes = 'Avis envoyé ! Il attend une validation.';

            } catch (PDOException $e) {

                error_log($e->getMessage());

                $erreur = 'Une erreur est survenue pendant l’envoi de votre avis.';
            }
        }
    }
}

// Récupérer uniquement les avis approuvés
$requete = $pdo->prepare(
    "SELECT
        a.note,
        a.commentaire,
        a.date_creation,
        u.pseudo
     FROM avis a
     JOIN utilisateurs u
       ON u.id = a.utilisateur_id
     WHERE a.personnage_id = ?
       AND a.statut = 'approuve'
     ORDER BY a.date_creation DESC, a.id DESC"
);

$requete->execute([$id]);
$avis = $requete->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Avis - <?= e($personnage['nom']) ?> | FantasyRealm Online</title>

    <style>
        body {
            background: #111827;
            color: white;
            font-family: Arial, sans-serif;
            margin: 0;
        }

        main {
            max-width: 800px;
            margin: 40px auto;
            padding: 20px;
        }

        h1, h2 {
            color: #a78bfa;
        }

        a {
            color: #c4b5fd;
        }

        .carte {
            background: #1f2937;
            padding: 20px;
            margin: 15px 0;
            border-radius: 10px;
        }

        label {
            display: block;
            margin-top: 15px;
        }

        textarea, select {
            display: block;
            margin-top: 6px;
            padding: 10px;
            max-width: 100%;
            box-sizing: border-box;
        }

        textarea {
            width: 100%;
        }

        button {
            background: #7c3aed;
            color: white;
            border: none;
            border-radius: 6px;
            padding: 12px 20px;
            margin-top: 15px;
            cursor: pointer;
        }

        button:hover {
            background: #6d28d9;
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

    <a href="personnages.php">
        Retour à la galerie
    </a>

    <h1><?= e($personnage['nom']) ?></h1>

    <p>
        Créateur : <?= e($personnage['pseudo']) ?>
    </p>

    <section class="carte">
        <h2>Donner mon avis</h2>

        <?php if ($erreur): ?>
            <p class="erreur" role="alert">
                <?= e($erreur) ?>
            </p>
        <?php endif; ?>

        <?php if ($succes): ?>
            <p class="succes" role="status">
                <?= e($succes) ?>
            </p>
        <?php endif; ?>

        <?php if ($utilisateurActif): ?>

            <form method="POST">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e($_SESSION['csrf_token']) ?>"
                >

                <label for="note">Note</label>

                <select name="note" id="note" required>
                    <option value="5">5 étoiles</option>
                    <option value="4">4 étoiles</option>
                    <option value="3">3 étoiles</option>
                    <option value="2">2 étoiles</option>
                    <option value="1">1 étoile</option>
                </select>

                <label for="commentaire">
                    Commentaire
                </label>

                <textarea
                    id="commentaire"
                    name="commentaire"
                    rows="5"
                    maxlength="2000"
                    required
                ></textarea>

                <button type="submit">
                    Envoyer mon avis
                </button>

            </form>

        <?php else: ?>

            <p>
                <a href="connexion.php">
                    Connectez-vous
                </a>
                pour laisser un avis.
            </p>

        <?php endif; ?>
    </section>

    <h2>Avis de la communauté</h2>

    <?php if (!$avis): ?>
        <p>Aucun avis approuvé pour le moment.</p>
    <?php endif; ?>

    <?php foreach ($avis as $item): ?>

        <article class="carte">

            <h3><?= e($item['pseudo']) ?></h3>

            <p>
                Note : <?= (int)$item['note'] ?>/5
            </p>

            <p>
                <?= nl2br(e($item['commentaire'])) ?>
            </p>

            <small>
                <?= e($item['date_creation']) ?>
            </small>

        </article>

    <?php endforeach; ?>

</main>
</body>
</html>
