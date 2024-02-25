<?php

/**
 * Cette classe permet de synchroniser des données entre une base de données locale et un hôte distant.
 */
class YelemaSynchronizationDB
{
    /** @var string Adresse de l'hôte de la base de données */
    private $HOST;
    
    /** @var string Nom de la base de données */
    private $DB_NAME;
    
    /** @var string Nom d'utilisateur de la base de données */
    private $DB_USER;
    
    /** @var string Mot de passe de la base de données */
    private $DB_PASSWORD;

    /** @var array Liste des tables à traiter */
    private $TABLES_LIST;

    /** @var PDO|null Instance de l'objet PDO pour la connexion à la base de données */
    private $PDO = null;

    /**
     * Constructeur de la classe YelemaSynchronizationDB.
     *
     * @param string $host Adresse de l'hôte de la base de données
     * @param string $db_name Nom de la base de données
     * @param string $db_user Nom d'utilisateur de la base de données
     * @param string $db_password Mot de passe de la base de données
     */
    public function __construct(
        $host,
        $db_name,
        $db_user,
        $db_password
    ) {
        $this->HOST = $host;
        $this->DB_NAME = $db_name;
        $this->DB_USER = $db_user;
        $this->DB_PASSWORD = $db_password;

        $this->PDO = $this->get_PDO();
    }

    /**
     * Établit une connexion à la base de données et retourne une instance de PDO.
     *
     * @return PDO Objet PDO représentant la connexion à la base de données
     * @throws PDOException En cas d'échec de connexion à la base de données
     */
    private function get_PDO(): PDO
    {
        if (!$this->PDO) {
            try {
                $this->PDO =  new PDO("mysql:dbname={$this->DB_NAME};host={$this->HOST};charset=utf8mb4;", $this->DB_USER, $this->DB_PASSWORD, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ
                ]);
            } catch (\PDOException $th) {
                throw $th;
            }
        }
        return $this->PDO;
    }

    /**
     * Génère des requêtes d'insertion à partir des données des tables spécifiées.
     *
     * @param array $tables Liste des tables à traiter
     * @return array Tableau de requêtes d'insertion
     */
    public function generateInsertQueries(array $tables): array
    {
        $this->TABLES_LIST = $tables;
        $queries = [];

        foreach ($this->TABLES_LIST as $table) {
            // Récupérer les données de la table
            $stmt = $this->PDO->query("SELECT * FROM $table");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Générer les requêtes d'insertion
            foreach ($rows as $row) {
                // Exclure la colonne ID
                $dataWithoutId = array_filter($row, function ($key) {
                    return strtolower($key) !== 'id';
                }, ARRAY_FILTER_USE_KEY);

                $columns = implode(', ', array_keys($dataWithoutId));
                $values = implode(', ', array_map(function ($value) {
                    return "'" . addslashes($value) . "'";
                }, $dataWithoutId));
                $queries[] = "INSERT INTO $table ($columns) VALUES ($values)";

                // echo "\nINSERT INTO $table ($columns) VALUES ($values)";
            }
        }

        return $queries;
    }

    /**
     * Supprime toutes les données des tables spécifiées.
     */
    public function clearTables()
    {
        foreach ($this->TABLES_LIST as $table) {
            $this->PDO->exec("TRUNCATE TABLE $table");
        }
    }

    /**
     * Envoie des données à un hôte distant via une requête POST.
     *
     * @param string $remote_uri URI de l'hôte distant
     * @param array $data Données à envoyer
     * @param bool $removeLocalData Indique si les données locales doivent être supprimées après l'envoi réussi
     * @return bool true si l'envoi est réussi, sinon false
     */
    public function sendDataToRemoteHost(string $remote_uri, array $data, bool $removeLocalData = false): bool
    {
        // Convertir le tableau de requêtes en JSON
        $jsonData = json_encode($data);

        // Configuration de la requête Curl
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $remote_uri,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $jsonData,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json'
            ],
        ]);

        // Exécution de la requête Curl
        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        // Vérification des erreurs Curl
        if ($err) {
            echo "Erreur Curl : " . $err;
            return false;
        } else {
            // Vérification de la réponse de l'hôte distant
            $responseArray = json_decode($response, true);
            var_dump($responseArray);
            // var_dump($responseArray['message']);
            if ($responseArray && $responseArray['success'] === 1) {
                if ($removeLocalData === true) {
                    $this->clearTables();
                }
                return true;
            } else {
                print_r ("Erreur : " . $responseArray);
                return false;
            }
        }
    }

    private function colorize($text, $color="white") {
        $colors = [
            'black' => '0;30',
            'dark_gray' => '1;30',
            'blue' => '0;34',
            'light_blue' => '1;34',
            'green' => '0;32',
            'light_green' => '1;32',
            'cyan' => '0;36',
            'light_cyan' => '1;36',
            'red' => '0;31',
            'light_red' => '1;31',
            'purple' => '0;35',
            'light_purple' => '1;35',
            'brown' => '0;33',
            'yellow' => '1;33',
            'light_gray' => '0;37',
            'white' => '1;37',
        ];
    
        // Vérifie si la couleur est définie
        if (array_key_exists($color, $colors)) {
            return "\033[" . $colors[$color] . "m" . $text . "\033[0m";
        } else {
            return $text; // Retourne le texte sans couleur si la couleur n'est pas trouvée
        }
    }
}

