<?php

require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;

function envoyerEmail(
    string $destinataire,
    string $sujet,
    string $message
): bool {
    try {
        $smtp = require 'C:/xampp/private_ecf/smtp.php';

        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host = $smtp['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $smtp['username'];
        $mail->Password = $smtp['password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $smtp['port'];

        $mail->SMTPOptions = [
            'ssl' => [
                'cafile' => 'C:/xampp/php/extras/ssl/cacert.pem',
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
