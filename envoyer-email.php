<?php

require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;

function envoyerEmail(
    string $destinataire,
    string $sujet,
    string $message
): bool {
    try {
        // Configuration SMTP compatible XAMPP et Alwaysdata
        $smtp = require __DIR__ . '/config/smtp.php';

        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host = $smtp['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $smtp['username'];
        $mail->Password = $smtp['password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = (int) $smtp['port'];

        // Vérification TLS via les certificats du système
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false
            ]
        ];

        $mail->CharSet = 'UTF-8';

        $mail->setFrom(
            $smtp['from_email'],
            $smtp['from_name']
        );

        $mail->addAddress($destinataire);

        $mail->isHTML(false);
        $mail->Subject = $sujet;
        $mail->Body = $message;

        return $mail->send();

    } catch (Throwable $exception) {
        error_log(
            'Erreur envoi email : ' . $exception->getMessage()
        );

        return false;
    }
}
