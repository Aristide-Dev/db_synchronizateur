
<?php
require_once('./Yelema_4.php');

$yelemaSync = new YelemaSynchronizationDB(
    'localhost', // Hôte de la base de données locale
    'questionnaire', // Nom de la base de données locale
    'root', // Utilisateur de la base de données locale
    '', // Mot de passe de la base de données locale
);

$queries = $yelemaSync->generateInsertQueries(['identification']);
print_r($queries);