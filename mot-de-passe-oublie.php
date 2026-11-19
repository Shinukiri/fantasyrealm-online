<?php
session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;

// Configuration SMTP privée, compatible XAMPP et Alwaysdata
$smtp = require __DIR__ . '/config/smtp.php';

if (
    empty($smtp['hmac_key']) ||
    !is_string($smtp['hmac_key']) ||
    strlen($smtp['hmac_key']) < 32
) {
    error_log('Clé HMAC absente ou invalide.');
    http_response_code(500);
    exit('Une erreur technique est survenue.');
}

// Générer un jeton CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';
$erreur = '';

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
        exit('Requête non autorisée.');
    }

    // Récupérer et vérifier l'adresse email
    $email = is_string($_POST['email'] ?? null)
        ? trim($_POST['email'])
        : '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erreur = 'Veuillez saisir une adresse email valide.';
    } else {

        // Normaliser l'adresse pour la limitation
        $emailNormalise = strtolower($email);

        // Adresse IP du visiteur
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';

        // Empreintes HMAC : ne pas stocker les valeurs en clair
        $emailHash = hash_hmac(
            'sha256',
            $emailNormalise,
            $smtp['hmac_key']
        );

        $ipHash = hash_hmac(
            'sha256',
            $ip,
            $smtp['hmac_key']
        );

        try {
            // Nettoyer les tentatives de plus de 24 heures
            $requete = $pdo->prepare(
                "DELETE FROM tentatives_reinitialisation_mdp
                 WHERE date_tentative < DATE_SUB(
                     NOW(), INTERVAL 24 HOUR
                 )"
            );
            $requete->execute();

            // Vérifier le délai de 60 secondes par email
            $requete = $pdo->prepare(
                "SELECT COUNT(*)
                 FROM tentatives_reinitialisation_mdp
                 WHERE email_hash = ?
                   AND date_tentative > DATE_SUB(
                       NOW(), INTERVAL 60 SECOND
                   )"
            );

            $requete->execute([$emailHash]);

            $tentativesEmail = (int) $requete->fetchColumn();

            // Vérifier le maximum de 5 demandes par IP
            $requete = $pdo->prepare(
                "SELECT COUNT(*)
                 FROM tentatives_reinitialisation_mdp
                 WHERE ip_hash = ?
                   AND date_tentative > DATE_SUB(
                       NOW(), INTERVAL 15 MINUTE
                   )"
            );

            $requete->execute([$ipHash]);

            $tentativesIp = (int) $requete->fetchColumn();

            // Continuer seulement si les limites sont respectées
            if ($tentativesEmail === 0 && $tentativesIp < 5) {

                // Enregistrer la tentative avant l'envoi
                $requete = $pdo->prepare(
                    "INSERT INTO tentatives_reinitialisation_mdp
                     (
                         email_hash,
                         ip_hash
                     )
                     VALUES (?, ?)"
                );

                $requete->execute([
                    $emailHash,
                    $ipHash
                ]);

                // Chercher un compte actif
                $requete = $pdo->prepare(
                    "SELECT id
                     FROM utilisateurs
                     WHERE email = ?
                       AND statut = 'actif'"
                );

                $requete->execute([$email]);
                $utilisateur = $requete->fetch(PDO::FETCH_ASSOC);

                if ($utilisateur) {

                    // Générer un jeton sécurisé
                    $token = bin2hex(random_bytes(32));

                    // Stocker uniquement son empreinte
                    $tokenHash = hash('sha256', $token);

                    $pdo->beginTransaction();

                    try {
                        // Supprimer les anciens liens
                        $requete = $pdo->prepare(
                            "DELETE FROM reinitialisations_mdp
                             WHERE utilisateur_id = ?"
                        );

                        $requete->execute([
                            $utilisateur['id']
                        ]);

                        // Créer le nouveau lien valable 30 minutes
                        $requete = $pdo->prepare(
                            "INSERT INTO reinitialisations_mdp
                             (
                                 utilisateur_id,
                                 token_hash,
                                 expiration
                             )
                             VALUES (
                                 ?,
                                 ?,
                                 DATE_ADD(NOW(), INTERVAL 30 MINUTE)
                             )"
                        );

                        $requete->execute([
                            $utilisateur['id'],
                            $tokenHash
                        ]);

                        $pdo->commit();

                    } catch (Throwable $e) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        throw $e;
                    }

                    // Construire le lien
                    $lien = rtrim($smtp['base_url'], '/')
                        . '/reinitialiser-mot-de-passe.php?token='
                        . urlencode($token);

                    // Préparer PHPMailer
                    $mail = new PHPMailer(true);

                    $mail->isSMTP();
                    $mail->Host = $smtp['host'];
                    $mail->SMTPAuth = true;
                    $mail->Username = $smtp['username'];
                    $mail->Password = $smtp['password'];
                    $mail->SMTPSecure =
                        PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = (int) $smtp['port'];

                    // Vérification du certificat TLS
                    $mail->SMTPOptions = [
                        'ssl' => [
                            'verify_peer' => true,
                            'verify_peer_name' => true,
                            'allow_self_signed' => false
                        ]
                    ];

                    $mail->CharSet = 'UTF-8';

                    // Expéditeur
                    $mail->setFrom(
                        $smtp['from_email'],
                        $smtp['from_name']
                    );

                    // Destinataire
                    $mail->addAddress($email);

                    // Contenu de l'email
                    $mail->isHTML(true);

                    $mail->Subject =
                        'Réinitialisation de votre mot de passe - FantasyRealm Online';

                    $lienHtml = htmlspecialchars(
                        $lien,
                        ENT_QUOTES,
                        'UTF-8'
                    );

                    $mail->Body = '
                        <h2>FantasyRealm Online</h2>

                        <p>
                            Une demande de réinitialisation
                            de mot de passe a été effectuée
                            pour votre compte.
                        </p>

                        <p>
                            <a href="' . $lienHtml . '">
                                Réinitialiser mon mot de passe
                            </a>
                        </p>

                        <p>
                            Ce lien est valable pendant
                            30 minutes et ne peut être
                            utilisé qu’une seule fois.
                        </p>

                        <p>
                            Si vous n’avez pas effectué
                            cette demande, ignorez
                            simplement cet email.
                        </p>
                    ';

                    $mail->AltBody =
                        "FantasyRealm Online\n\n"
                        . "Pour réinitialiser votre mot de passe, "
                        . "ouvrez ce lien :\n"
                        . $lien
                        . "\n\nCe lien est valable pendant "
                        . "30 minutes.\n"
                        . "Si vous n'avez pas demandé cette "
                        . "réinitialisation, ignorez cet email.";

                    // Envoyer l'email
                    $mail->send();
                }
            }

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            // Ne pas exposer les erreurs au visiteur
            error_log(
                'Échec de réinitialisation : '
                . $e->getMessage()
            );
        }

        // Réponse identique dans tous les cas
        $message =
            "Si un compte actif correspond à cette adresse, "
            . "vous recevrez un lien de réinitialisation.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>
        Mot de passe oublié | FantasyRealm Online
    </title>

    <style>
        body {
            background: #111827;
            color: white;
            font-family: Arial, sans-serif;
        }

        main {
            max-width: 450px;
            margin: 80px auto;
            padding: 25px;
            background: #1f2937;
            border-radius: 10px;
        }

        h1 {
            color: #a78bfa;
        }

        label {
            display: block;
            margin: 20px 0 8px;
        }

        input {
            width: 100%;
            box-sizing: border-box;
            padding: 12px;
        }

        button {
            margin-top: 20px;
            padding: 12px 18px;
            background: #7c3aed;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }

        a {
            color: #c4b5fd;
        }

        .succes {
            color: #86efac;
        }

        .erreur {
            color: #fca5a5;
        }
    </style>
</head>

<body>
<main>

    <h1>Mot de passe oublié ?</h1>

    <p>
        Indiquez votre adresse email pour demander
        la réinitialisation de votre mot de passe.
    </p>

    <?php if ($message): ?>
        <p class="succes" role="status">
            <?= htmlspecialchars(
                $message,
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </p>
    <?php endif; ?>

    <?php if ($erreur): ?>
        <p class="erreur" role="alert">
            <?= htmlspecialchars(
                $erreur,
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </p>
    <?php endif; ?>

    <form method="POST">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars(
                $_SESSION['csrf_token'],
                ENT_QUOTES,
                'UTF-8'
            ) ?>"
        >

        <label for="email">
            Adresse email
        </label>

        <input
            type="email"
            id="email"
            name="email"
            autocomplete="email"
            maxlength="254"
            required
        >

        <button type="submit">
            Demander un lien
        </button>

    </form>

    <p>
        <a href="connexion.php">
            Retour à la connexion
        </a>
    </p>

</main>
</body>
</html>
