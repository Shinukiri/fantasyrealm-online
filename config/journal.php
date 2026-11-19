<?php

require_once __DIR__ . '/mongodb.php';

function enregistrerEvenement(
    string $type,
    ?int $utilisateurId = null,
    ?int $personnageId = null,
    array $details = []
): void {
    try {
        $collection = connexionMongoDB();

        $collection->insertOne([
            'type' => $type,
            'utilisateur_id' => $utilisateurId,
            'personnage_id' => $personnageId,
            'details' => $details,
            'date' => new MongoDB\BSON\UTCDateTime()
        ]);

    } catch (Throwable $exception) {
        // Un problème MongoDB ne doit pas bloquer le site.
        error_log(
            'Erreur journal MongoDB : ' .
            $exception->getMessage()
        );
    }
}
