<?php

class YelemaSynchronizationDB
{
    private $HOST;
    private $DB_NAME;
    private $DB_USER;
    private $DB_PASSWORD;

    private $TABLES_LIST;

    private $PDO = null;

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
                $columns = implode(', ', array_keys($row));
                $values = implode(', ', array_map(function ($value) {
                    return "'" . addslashes($value) . "'";
                }, $row));
                $queries[] = "INSERT INTO $table ($columns) VALUES ($values)";
            }
        }

        return $queries;
    }

    public function clearTables()
    {
        foreach ($this->TABLES_LIST as $table) {
            $this->PDO->exec("TRUNCATE TABLE $table");
        }
    }


    public function sendDataToRemoteHost(String $remote_uri, array $data, $removeLocalData=False): bool
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
            if ($responseArray['status'] === 'success') {
                if($removeLocalData === true)
                {
                    $this->clearTables();
                }
                return true;
            } else {
                echo "Erreur : " . $responseArray['message'];
                return false;
            }
        }
    }
}
