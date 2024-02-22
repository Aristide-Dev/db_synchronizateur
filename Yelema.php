<?php

class YelemaSynchronizationDB
{
    private $LOCAL_HOST;
    private $LOCAL_DB_NAME;
    private $LOCAL_DB_USER;
    private $LOCAL_DB_PASSWORD;


    private $EXTERNAL_HOST;
    private $EXTERNAL_DB_NAME;
    private $EXTERNAL_DB_USER;
    private $EXTERNAL_DB_PASSWORD;

    /**
     * Undocumented variable
     *
     * @var PDO|null
     */
    private $LOCAL_PDO = null;

    /**
     * Undocumented variable
     *
     * @var PDO|null
     */
    private $EXTERNAL_PDO = null;

    /**
     * Undocumented function
     *
     * @param string $local_host
     * @param string $local_db_name
     * @param string $local_db_user
     * @param string $local_db_password
     * @param string $external_host
     * @param string $external_db_name
     * @param string $external_db_user
     * @param string $external_db_password
     */
    public function __construct(
        $local_host,
        $local_db_name,
        $local_db_user,
        $local_db_password,
        $external_host,
        $external_db_name,
        $external_db_user,
        $external_db_password
    ) {
        $this->LOCAL_HOST = $local_host;
        $this->LOCAL_DB_NAME = $local_db_name;
        $this->LOCAL_DB_USER = $local_db_user;
        $this->LOCAL_DB_PASSWORD = $local_db_password;

        $this->EXTERNAL_HOST = $external_host;
        $this->EXTERNAL_DB_NAME = $external_db_name;
        $this->EXTERNAL_DB_USER = $external_db_user;
        $this->EXTERNAL_DB_PASSWORD = $external_db_password;

        echo "initialisation des données";

        $this->LOCAL_PDO = $this->get_local_PDO();
        $this->EXTERNAL_PDO = $this->get_external_PDO();
    }

    private function get_local_PDO(): PDO
    {
        echo "\nconnexion à la database locale";
        echo "\nHOST: " . $this->LOCAL_HOST;
        echo "\nDB_NAME: " . $this->LOCAL_DB_NAME;
        echo "\nDB_USER: " . $this->LOCAL_DB_USER;
        echo "\nDB_PASSWORD: " . $this->LOCAL_DB_PASSWORD;
        // echo "\nPDO: " . $this->LOCAL_PDO;
        echo "\n...";
        echo "\n...";
        return $this->LOCAL_PDO ?? $this->LOCAL_PDO = new PDO("mysql:dbname={$this->LOCAL_DB_NAME};host={$this->LOCAL_HOST};charset=utf8mb4;", $this->LOCAL_DB_USER, $this->LOCAL_DB_PASSWORD, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ
        ]);
    }

    private function get_external_PDO(): PDO
    {
        echo "\nconnexion à la database externe";
        echo "\nHOST: " . $this->EXTERNAL_HOST;
        echo "\nDB_NAME: " . $this->EXTERNAL_DB_NAME;
        echo "\nDB_USER: " . $this->EXTERNAL_DB_USER;
        echo "\nDB_PASSWORD: " . $this->EXTERNAL_DB_PASSWORD;
        echo "\n...";
        echo "\n...";
        return $this->EXTERNAL_PDO ?? $this->EXTERNAL_PDO = new PDO("mysql:dbname={$this->EXTERNAL_DB_NAME};host={$this->EXTERNAL_HOST};charset=utf8mb4;", $this->EXTERNAL_DB_USER, $this->EXTERNAL_DB_PASSWORD, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ
        ]);
    }

    // Méthode pour synchroniser toutes les données de la base de données locale vers la base de données externe
    public function syncToLocal()
    {
        echo "\nSynchronisation de la base de données locale...\n";
        try {
            if ($this->LOCAL_PDO->inTransaction()) {
                $this->LOCAL_PDO->rollBack(); // Rollback si une transaction est déjà active
            }
            $this->LOCAL_PDO->beginTransaction(); // Début de la nouvelle transaction
            $tables = $this->getAllTables($this->LOCAL_PDO); // Récupérer toutes les tables de la base de données locale
            foreach ($tables as $table) {
                echo "\nSynchronisation de la table $table...\n";
                $localData = $this->getTableData($this->LOCAL_PDO, $table); // Récupérer les données de chaque table
                if (!empty($localData)) { // Vérifier que les données ne sont pas vides
                    $this->insertExternalData($this->EXTERNAL_PDO, $table, $localData); // Insérer les données dans la base de données externe
                } else {
                    echo "\nAucune donnée à synchroniser pour la table $table.\n";
                }
            }
            $this->LOCAL_PDO->commit(); // Valider la transaction
            echo "\nSynchronisation vers la base de données externe effectuée avec succès.\n";
        } catch (PDOException $e) {
            $this->LOCAL_PDO->rollBack(); // Rollback en cas d'erreur
            echo "\nErreur lors de la synchronisation vers la base de données externe : " . $e->getMessage() . "\n";
        }
    }

    // Méthode pour synchroniser toutes les données de la base de données externe vers la base de données locale
    public function syncToExternal()
    {
        echo "\nSynchronisation de la base de données externe...\n";
        try {
            if ($this->EXTERNAL_PDO->inTransaction()) {
                $this->EXTERNAL_PDO->rollBack(); // Rollback si une transaction est déjà active
            }
            $this->EXTERNAL_PDO->beginTransaction(); // Début de la nouvelle transaction
            $tables = $this->getAllTables($this->EXTERNAL_PDO); // Récupérer toutes les tables de la base de données externe
            foreach ($tables as $table) {
                echo "\nSynchronisation de la table $table...\n";
                $externalData = $this->getTableData($this->EXTERNAL_PDO, $table); // Récupérer les données de chaque table
                if (!empty($externalData)) { // Vérifier que les données ne sont pas vides
                    $this->insertLocalData($this->LOCAL_PDO, $table, $externalData); // Insérer les données dans la base de données locale
                } else {
                    echo "\nAucune donnée à synchroniser pour la table $table.\n";
                }
            }
            $this->EXTERNAL_PDO->commit(); // Valider la transaction
            echo "\nSynchronisation vers la base de données locale effectuée avec succès.\n";
        } catch (PDOException $e) {
            $this->EXTERNAL_PDO->rollBack(); // Rollback en cas d'erreur
            echo "\nErreur lors de la synchronisation vers la base de données locale : " . $e->getMessage() . "\n";
        }
    }


    // Méthode pour récupérer toutes les tables d'une base de données
    private function getAllTables($pdo): array
    {
        $stmt = $pdo->query("SHOW TABLES");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    // Méthode pour récupérer les données d'une table spécifique
    private function getTableData($pdo, $table): array
    {
        $stmt = $pdo->prepare("SELECT * FROM $table");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Méthode pour insérer les données dans une table spécifique de la base de données externe
    private function insertExternalData($pdo, $table, $data)
    {
        $columns = array_keys($data[0]);
        $placeholders = array_fill(0, count($columns), '?');
        $sql = "INSERT INTO $table (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";

        $pdo->beginTransaction();
        $stmt = $pdo->prepare($sql);
        foreach ($data as $row) {
            $stmt->execute(array_values($row));
        }
        $pdo->commit();
    }

    // Méthode pour insérer les données dans une table spécifique de la base de données locale
    private function insertLocalData($pdo, $table, $data)
    {
        $columns = array_keys($data[0]);
        $placeholders = array_fill(0, count($columns), '?');
        $sql = "INSERT INTO $table (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";

        $pdo->beginTransaction();
        $stmt = $pdo->prepare($sql);
        foreach ($data as $row) {
            $stmt->execute(array_values($row));
        }
        $pdo->commit();
    }







    // Connexion à la base de données locale
    // $bdd_locale = new PDO('mysql:host=localhost;dbname=nom_base_de_données_locale', 'utilisateur', 'mdp');
}

