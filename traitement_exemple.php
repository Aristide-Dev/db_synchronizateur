<?php
# http://localhost/tontine/traitements/test.php

$response = [
    "success" => 0,
    "error" => 1,
    "message" => "",
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupère les données JSON envoyées en POST
    $jsonData = file_get_contents('php://input');

    // Convertit les données JSON en tableau PHP
    $data = json_decode($jsonData, true);

    try {
        // Établir la connexion PDO avec la base de données
        $pdo = new PDO("mysql:dbname=pharmacie;host=127.0.0.1;charset=utf8mb4", 'root', '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ
        ]);

        // Début de la transaction
        $pdo->beginTransaction();

        // Traiter les données ici
        foreach ($data as $query) {
            $pdo->exec($query);
        }

        // Valider la transaction
        $pdo->commit();

        // Envoyer une réponse indiquant que le traitement a été effectué avec succès
        die (json_encode(['success' => 1, 'message' => 'Données reçues et traitées avec succès'], JSON_UNESCAPED_UNICODE));
    } catch (PDOException $e) {
        // En cas d'erreur, annuler la transaction
        $pdo->rollBack();

        // Envoyer une réponse avec l'erreur
        die(json_encode(['error' => 1, 'success' => 0, 'message' => 'Erreur lors du traitement des données: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE));
    }
} else {
    // Aucune donnée envoyée en POST
    die(json_encode(['error' => 1, 'success' => 0, 'message' => 'Aucune donnée envoyée en POST'], JSON_UNESCAPED_UNICODE));
}