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

    private $LOCAL_PDO = null;
    private $EXTERNAL_PDO = null;

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

        $this->LOCAL_PDO = $this->get_local_PDO();
        
        echo $this->colorize("\nDB locale connectée.\n", 'green');
        $this->EXTERNAL_PDO = $this->get_external_PDO();
        echo $this->colorize("\nDB externe connectée.\n", 'green');
    }

    private function get_local_PDO(): PDO
    {
        echo $this->colorize("\nDebut de la connexion la DB locale.\n", 'red');
        if(!$this->LOCAL_PDO)
        {
            try {
                $this->LOCAL_PDO =  new PDO("mysql:dbname={$this->LOCAL_DB_NAME};host={$this->LOCAL_HOST};charset=utf8mb4;", $this->LOCAL_DB_USER, $this->LOCAL_DB_PASSWORD, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ
                ]);
            } catch (\PDOException $th) {
                //throw $th;
                echo $this->colorize("Erreur de connexion à la db locale : " . $th->getMessage(),"red");
            }
        }
        return $this->LOCAL_PDO;
    }

    private function get_external_PDO(): PDO
    {
        echo $this->colorize("\nDébut de la connexion à la base de données externe.\n", 'red');
        if ($this->EXTERNAL_PDO === null) {
            try {
                $this->EXTERNAL_PDO = new PDO("mysql:dbname={$this->EXTERNAL_DB_NAME};host={$this->EXTERNAL_HOST};charset=utf8mb4;", $this->EXTERNAL_DB_USER, $this->EXTERNAL_DB_PASSWORD, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ
                ]);
            } catch (PDOException $th) {
                // Handle the exception
                echo $this->colorize("Erreur de connexion à la base de données externe : " . $th->getMessage(), "red");
                // Optionally, rethrow the exception for higher-level handling
                // throw $th;
            }
        }
        return $this->EXTERNAL_PDO;
    }
    

    public function synchronizeData($local_table, $remote_table)
    {
        try {
            // Début de la transaction
            if (!$this->EXTERNAL_PDO->inTransaction()) {
                $this->EXTERNAL_PDO->beginTransaction();
            }

            // Sélection des données à partir de la base de données locale
            $select_query = "SELECT * FROM $local_table";
            $stmt = $this->LOCAL_PDO->query($select_query);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Désactiver temporairement les contraintes de clé étrangère
            $this->EXTERNAL_PDO->exec("SET FOREIGN_KEY_CHECKS=0");

            // Insérer les données dans la base de données distante
            foreach ($rows as $row) {
                // Supprimer l'ID de la liste des colonnes à insérer
                unset($row['id']);

                // Construire la liste des colonnes et des valeurs
                $columns = implode(', ', array_keys($row));
                $values = "'" . implode("', '", array_values($row)) . "'"; // Échapper les valeurs avec des guillemets simples

                // Exécuter la requête d'insertion
                $insert_query = "INSERT INTO $remote_table ($columns) VALUES ($values)";
                $this->EXTERNAL_PDO->exec($insert_query);
            }

            echo $this->colorize("\n\t* Synchronisation de la table $local_table terminée.",'green');

        } catch (PDOException $e) {
            // En cas d'erreur, annuler la transaction
            $this->EXTERNAL_PDO->rollBack();
            echo $this->colorize("Erreur lors de la synchronisation de la table $local_table : " . $e->getMessage(),'red');
        } finally {
            // Réactiver les contraintes de clé étrangère
            $this->EXTERNAL_PDO->exec("SET FOREIGN_KEY_CHECKS=1");
        }
    }


    public function synchronize(array $tables_list = [])
    {
        if (empty($tables_list)) {
            return;
        }

        try {
            echo $this->colorize("\nDebut de la synchronisations vers le cloud.\n",'light_blue');
            // Début de la transaction
            if (!$this->EXTERNAL_PDO->inTransaction()) {
                $this->EXTERNAL_PDO->beginTransaction();
            }

            foreach ($tables_list as $table) {
                $this->synchronizeData($table, $table);
            }

            // Valider la transaction
            $this->EXTERNAL_PDO->commit();
            echo $this->colorize("\nToutes les synchronisations ont été terminées avec succès.\n",'green');

        } catch (PDOException $e) {
            // En cas d'erreur, annuler la transaction
            $this->EXTERNAL_PDO->rollBack();
            echo $this->colorize("Erreur lors de la synchronisation des tables : " . $e->getMessage(),"red");
        }
    }

    public function clearsLocal(array $tables_list = [])
    {
        if (empty($tables_list)) {
            return;
        }

        try {
            echo $this->colorize("\nDebut de la suppression des données de la DB locale.\n", 'light_red');
            // Début de la transaction
            if (!$this->LOCAL_PDO->inTransaction()) {
                $this->LOCAL_PDO->beginTransaction();
            }

            foreach ($tables_list as $table) {
                $this->clearTable($table);
            }

            // Valider la transaction
            $this->LOCAL_PDO->commit();
            echo $this->colorize("\nToutes les suppressions ont été effectuées avec succès.\n", 'green');

        } catch (PDOException $e) {
            // En cas d'erreur, annuler la transaction
            $this->LOCAL_PDO->rollBack();
            echo $this->colorize("Erreur lors de la suppression des données : " . $e->getMessage(),'red');
        }
    }

    public function clearTable($table_name)
    {
        try {
            // Début de la transaction
            if (!$this->LOCAL_PDO->inTransaction()) {
                $this->LOCAL_PDO->beginTransaction();
            }

            // Désactiver temporairement les contraintes de clé étrangère
            $this->LOCAL_PDO->exec("SET FOREIGN_KEY_CHECKS=0");

            // Supprimer toutes les données de la table
            $delete_query = "DELETE FROM $table_name";
            $this->LOCAL_PDO->exec($delete_query);

            echo $this->colorize("\n\t- Les données de la table $table_name ont été supprimées.", 'light_green');

            // Valider la transaction
            // $this->LOCAL_PDO->commit();

        } catch (PDOException $e) {
            // En cas d'erreur, annuler la transaction
            $this->LOCAL_PDO->rollBack();
            echo $this->colorize("Erreur lors de la suppression des données de la table $table_name : " . $e->getMessage(),'red');
        } finally {
            // Réactiver les contraintes de clé étrangère
            $this->LOCAL_PDO->exec("SET FOREIGN_KEY_CHECKS=1");
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
?>
