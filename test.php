<?php
require_once('./Yelema_2.php');

function checkInternetConnection()
{
    // return true;
    $url = 'https://www.google.com'; // Changer l'URL par un site Web fiable

    $handle = curl_init($url);
    curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5); // Définir le temps limite de connexion en secondes

    $response = curl_exec($handle);
    $httpCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);
    curl_close($handle);

    if ($httpCode >= 200 && $httpCode < 300) {
        return true; // La connexion Internet est disponible
    } else {
        return false; // La connexion Internet n'est pas disponible
    }
}

 // Utilisation de la fonction pour vérifier la connexion Internet
 if (checkInternetConnection()) {
    echo "La connexion Internet est disponible.\n";

    
// Créer une instance de la classe YelemaSynchronizationDB en fournissant les informations de connexion
// $synchronizer = new YelemaSynchronizationDB(
//     'localhost', // Hôte de la base de données locale
//     'u662616459_QelGZ', // Nom de la base de données locale
//     'root', // Utilisateur de la base de données locale
//     '', // Mot de passe de la base de données locale
    
//     '86.38.202.52', // Hôte de la base de données externe
//     'u662616459_QelGZ', // Nom de la base de données externe
//     'u662616459_uctf9', // Utilisateur de la base de données externe
//     'u662616459_QelGZu662616459_QelGZ' // Mot de passe de la base de données externe
// );

$synchronizer = new YelemaSynchronizationDB(
    'localhost', // Hôte de la base de données locale
    'u662616459_HV8sK', // Nom de la base de données locale
    'root', // Utilisateur de la base de données locale
    '', // Mot de passe de la base de données locale
    
    '86.38.202.52', // Hôte de la base de données externe
    'u662616459_HV8sK', // Nom de la base de données externe
    'u662616459_NvQc4', // Utilisateur de la base de données externe
    'u662616459_HV8sKu662616459_HV8sK' // Mot de passe de la base de données externe
);


// Synchroniser les données de la base de données externe vers la base de données locale
$synchronizer->syncToExternal();

// Synchroniser les données de la base de données locale vers la base de données externe
$synchronizer->syncToLocal();

} else {
    echo "La connexion Internet n'est pas disponible.";
}