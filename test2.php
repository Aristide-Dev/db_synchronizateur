<?php
require_once('./Yelema_4.php');
$yelemaSync = new YelemaSynchronizationDB(
    'localhost', // Hôte de la base de données locale
    'pharmacie_local', // Nom de la base de données locale
    'root', // Utilisateur de la base de données locale
    '', // Mot de passe de la base de données locale
);
$queries = $yelemaSync->generateInsertQueries(['appro','client','detail','produit','stock','vente']);
// print_r($queries);
$yelemaSync->sendDataToRemoteHost("http://localhost/tontine/traitements/test.php",$queries, true);