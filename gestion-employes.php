<?php
// Authentification et vérification du compte actif
require_once __DIR__ . '/config/auth.php';

// Seul l'administrateur peut gérer les employés
if ($_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit('Accès interdit.');
}

// Générer un jeton CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Échapper les données affichées
function e($valeur) {
    return htmlspecialchars(
        (string)$valeur,
        ENT_QUOTES,
        'UTF-8'
    );
}

// Vérifier la solidité d'un mot de passe
function motDePasseValide($motDePasse) {
    return strlen($motDePasse) >= 12
        && strlen($motDePasse) <= 72
        && preg_match('/[a-z]/', $motDePasse)
        && preg_match('/[A-Z]/', $motDePasse)
        && preg_match('/[0-9]/', $motDePasse)
        && preg_match('/[^a-zA-Z0-9]/', $motDePasse);
}

$erreur = '';
$message = '';

// Traiter les formulaires
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Vérification CSRF
    if (
        !is_string($_POST['csrf_token'] ?? null) ||
        !hash_equals(
            $_SESSION['csrf_token'],
            $_POST['csrf_token']
        )
    ) {
        http_response_code(403);
        exit('Requête non autorisée.');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'creer') {

        // Création d'un compte employé
        $pseudo = is_string($_POST['pseudo'] ?? null)
            ? trim($_POST['pseudo'])
            : '';

        $email = is_string($_POST['email'] ?? null)
            ? trim($_POST['email'])
            : '';

        $motDePasse = $_POST['mot_de_passe'] ?? '';

        if (
            mb_strlen($pseudo) < 3 ||
            mb_strlen($pseudo) > 100 ||
            !filter_var($email, FILTER_VALIDATE_EMAIL) ||
            strlen($email) > 254 ||
            !is_string($motDePasse) ||
            !motDePasseValide($motDePasse)
        ) {
            $erreur = 'Informations invalides. Le mot de passe doit contenir entre 12 et 72 caractères, avec une minuscule, une majuscule, un chiffre et un caractère spécial.';

        } else {

            try {
                $hash = password_hash(
                    $motDePasse,
                    PASSWORD_DEFAULT
                );

                $requete = $pdo->prepare(
                    "INSERT INTO utilisateurs
                     (
                         email,
                         pseudo,
                         mot_de_passe,
                         role,
                         statut,
                         changement_mdp_requis
                     )
                     VALUES (?, ?, ?, 'employe', 'actif', 1)"
                );

                $requete->execute([
                    $email,
                    $pseudo,
                    $hash
                ]);

                $message = 'Compte employé créé. Le changement de mot de passe sera obligatoire.';

            } catch (PDOException $e) {

                if ($e->getCode() === '23000') {
                    $erreur = 'Cet email ou ce pseudo est déjà utilisé.';
                } else {
                    error_log($e->getMessage());
                    $erreur = 'Impossible de créer cet employé.';
                }
            }
        }

    } elseif (in_array(
        $action,
        ['suspendre', 'reactiver', 'supprimer', 'changer_mdp'],
        true
    )) {

        $employeId = filter_var(
            $_POST['employe_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$employeId || $employeId < 1) {

            $erreur = 'Identifiant employé invalide.';

        } else {

            try {

                if ($action === 'suspendre') {

                    $requete = $pdo->prepare(
                        "UPDATE utilisateurs
                         SET statut = 'suspendu'
                         WHERE id = ?
                           AND role = 'employe'
                           AND statut = 'actif'"
                    );

                    $requete->execute([$employeId]);

                    $message = $requete->rowCount() > 0
                        ? 'Employé suspendu.'
                        : '';

                } elseif ($action === 'reactiver') {

                    $requete = $pdo->prepare(
                        "UPDATE utilisateurs
                         SET statut = 'actif'
                         WHERE id = ?
                           AND role = 'employe'
                           AND statut = 'suspendu'"
                    );

                    $requete->execute([$employeId]);

                    $message = $requete->rowCount() > 0
                        ? 'Employé réactivé.'
                        : '';

                } elseif ($action === 'supprimer') {

                    $requete = $pdo->prepare(
                        "DELETE FROM utilisateurs
                         WHERE id = ?
                           AND role = 'employe'"
                    );

                    $requete->execute([$employeId]);

                    $message = $requete->rowCount() > 0
                        ? 'Employé supprimé définitivement.'
                        : '';

                } elseif ($action === 'changer_mdp') {

                    $motDePasse = $_POST['nouveau_mdp'] ?? '';

                    if (
                        !is_string($motDePasse) ||
                        !motDePasseValide($motDePasse)
                    ) {
                        $erreur = 'Mot de passe invalide : 12 à 72 caractères, avec minuscule, majuscule, chiffre et caractère spécial.';

                    } else {

                        $hash = password_hash(
                            $motDePasse,
                            PASSWORD_DEFAULT
                        );

                        $requete = $pdo->prepare(
                            "UPDATE utilisateurs
                             SET mot_de_passe = ?,
                                 changement_mdp_requis = 1
                             WHERE id = ?
                               AND role = 'employe'"
                        );

                        $requete->execute([
                            $hash,
                            $employeId
                        ]);

                        $message = $requete->rowCount() > 0
                            ? 'Mot de passe employé réinitialisé.'
                            : '';
                    }
                }

                if ($message === '' && $erreur === '') {
                    $erreur = 'Aucun compte employé modifié.';
                }

            } catch (PDOException $e) {
                error_log($e->getMessage());
                $erreur = 'Une erreur est survenue.';
            }
        }

    } else {
        $erreur = 'Action invalide.';
    }
}

// Récupérer les employés
$requete = $pdo->query(
    "SELECT id, pseudo, email, statut,
            changement_mdp_requis, date_creation
     FROM utilisateurs
     WHERE role = 'employe'
     ORDER BY date_creation DESC, id DESC"
);

$employes = $requete->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1">

    <title>Gestion des employés | FantasyRealm Online</title>

    <style>
        body {
            background: #111827;
            color: white;
            font-family: Arial, sans-serif;
            margin: 0;
        }

        main {
            max-width: 950px;
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
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
        }

        label {
            display: block;
            margin-top: 14px;
        }

        input {
            display: block;
            width: 100%;
            max-width: 450px;
            box-sizing: border-box;
            padding: 10px;
            margin-top: 5px;
        }

        button {
            background: #7c3aed;
            color: white;
            border: none;
            border-radius: 6px;
            padding: 10px 16px;
            margin-top: 12px;
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

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: flex-start;
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

    <a href="espace.php">Retour à mon espace</a>

    <h1>Gestion des employés</h1>

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
        <h2>Créer un employé</h2>

        <form method="POST">

            <input type="hidden"
                   name="csrf_token"
                   value="<?= e($_SESSION['csrf_token']) ?>">

            <input type="hidden"
                   name="action"
                   value="creer">

            <label for="pseudo">Pseudo</label>
            <input
                type="text"
                id="pseudo"
                name="pseudo"
                minlength="3"
                maxlength="100"
                required
            >

            <label for="email">Adresse email</label>
            <input
                type="email"
                id="email"
                name="email"
                maxlength="254"
                required
            >

            <label for="mot_de_passe">
                Mot de passe temporaire
            </label>
            <input
                type="password"
                id="mot_de_passe"
                name="mot_de_passe"
                minlength="12"
                maxlength="72"
                autocomplete="new-password"
                required
            >

            <p>
                12 à 72 caractères, avec une minuscule,
                une majuscule, un chiffre et un caractère spécial.
            </p>

            <button type="submit">
                Créer l'employé
            </button>
        </form>
    </section>

    <h2>Employés existants</h2>

    <?php if (!$employes): ?>
        <p>Aucun employé enregistré.</p>
    <?php endif; ?>

    <?php foreach ($employes as $employe): ?>

        <article>
            <h3><?= e($employe['pseudo']) ?></h3>

            <p>Email : <?= e($employe['email']) ?></p>

            <p>
                Statut :
                <?= e($employe['statut']) ?>
            </p>

            <p>
                Changement de mot de passe requis :
                <?= $employe['changement_mdp_requis']
                    ? 'Oui'
                    : 'Non' ?>
            </p>

            <p>
                Créé le :
                <?= e($employe['date_creation']) ?>
            </p>

            <div class="actions">

                <!-- Suspendre ou réactiver -->
                <form method="POST">
                    <input type="hidden"
                           name="csrf_token"
                           value="<?= e($_SESSION['csrf_token']) ?>">

                    <input type="hidden"
                           name="employe_id"
                           value="<?= (int)$employe['id'] ?>">

                    <?php if ($employe['statut'] === 'actif'): ?>

                        <button
                            type="submit"
                            name="action"
                            value="suspendre"
                        >
                            Suspendre
                        </button>

                    <?php else: ?>

                        <button
                            type="submit"
                            name="action"
                            value="reactiver"
                        >
                            Réactiver
                        </button>

                    <?php endif; ?>
                </form>

                <!-- Supprimer -->
                <form
                    method="POST"
                    onsubmit="return confirm('Supprimer définitivement cet employé ?');"
                >
                    <input type="hidden"
                           name="csrf_token"
                           value="<?= e($_SESSION['csrf_token']) ?>">

                    <input type="hidden"
                           name="employe_id"
                           value="<?= (int)$employe['id'] ?>">

                    <button
                        class="danger"
                        type="submit"
                        name="action"
                        value="supprimer"
                    >
                        Supprimer
                    </button>
                </form>
            </div>

            <!-- Réinitialiser le mot de passe -->
            <form method="POST">
                <input type="hidden"
                       name="csrf_token"
                       value="<?= e($_SESSION['csrf_token']) ?>">

                <input type="hidden"
                       name="employe_id"
                       value="<?= (int)$employe['id'] ?>">

                <label for="mdp_<?= (int)$employe['id'] ?>">
                    Nouveau mot de passe temporaire
                </label>

                <input
                    type="password"
                    id="mdp_<?= (int)$employe['id'] ?>"
                    name="nouveau_mdp"
                    minlength="12"
                    maxlength="72"
                    autocomplete="new-password"
                    required
                >

                <button
                    type="submit"
                    name="action"
                    value="changer_mdp"
                >
                    Réinitialiser le mot de passe
                </button>
            </form>

        </article>

    <?php endforeach; ?>

</main>
</body>
</html>
