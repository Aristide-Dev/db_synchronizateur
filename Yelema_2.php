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
        $this->EXTERNAL_PDO = $this->get_external_PDO();
    }

    private function get_local_PDO(): PDO
    {
        return new PDO("mysql:dbname={$this->LOCAL_DB_NAME};host={$this->LOCAL_HOST};charset=utf8mb4;", $this->LOCAL_DB_USER, $this->LOCAL_DB_PASSWORD, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ
        ]);
    }

    private function get_external_PDO(): PDO
    {
        return new PDO("mysql:dbname={$this->EXTERNAL_DB_NAME};host={$this->EXTERNAL_HOST};charset=utf8mb4;", $this->EXTERNAL_DB_USER, $this->EXTERNAL_DB_PASSWORD, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ
        ]);
    }

    // Méthode pour désactiver les contraintes de clé étrangère
    private function disableForeignKeyChecks($pdo) {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    }

    // Méthode pour réactiver les contraintes de clé étrangère
    private function enableForeignKeyChecks($pdo) {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    public function syncToLocal()
    {
        try {
            $this->disableForeignKeyChecks($this->LOCAL_PDO);

            $tables = $this->getAllTablesList($this->LOCAL_PDO);
            foreach ($tables as $table) {
                $localData = $this->getTableData($this->LOCAL_PDO, $table);
                $externalData = $this->getTableData($this->EXTERNAL_PDO, $table);

                $primaryKeyColumns = $this->getPrimaryKeyColumns($this->LOCAL_PDO, $table);
                $primaryKey = $primaryKeyColumns[0] ?? null;

                foreach ($externalData as $row) {
                    $existingRow = array_filter($localData, fn ($r) => $r[$primaryKey] === $row[$primaryKey]);

                    if (empty($existingRow) || $this->isExternalDataNewer($existingRow, $row)) {
                        if (empty($existingRow)) {
                            $this->insertRow($this->LOCAL_PDO, $table, $row);
                        } else {
                            $this->updateLocalData($this->LOCAL_PDO, $table, $primaryKey, $row);
                        }
                    }
                }
            }
        } catch (PDOException $e) {
            echo "Erreur lors de la synchronisation vers la base de données locale : " . $e->getMessage();
        } finally {
            $this->enableForeignKeyChecks($this->LOCAL_PDO);
        }
    }

    public function syncToExternal()
    {
        try {
            $this->disableForeignKeyChecks($this->EXTERNAL_PDO);

            $tables = $this->getAllTablesList($this->EXTERNAL_PDO);
            foreach ($tables as $table) {
                $localData = $this->getTableData($this->LOCAL_PDO, $table);
                $externalData = $this->getTableData($this->EXTERNAL_PDO, $table);

                $primaryKeyColumns = $this->getPrimaryKeyColumns($this->EXTERNAL_PDO, $table);
                $primaryKey = $primaryKeyColumns[0] ?? null;

                foreach ($localData as $row) {
                    $existingRow = array_filter($externalData, fn ($r) => $r[$primaryKey] === $row[$primaryKey]);

                    if (empty($existingRow) || $this->isExternalDataNewer($existingRow, $row)) {
                        if (empty($existingRow)) {
                            $this->insertRow($this->EXTERNAL_PDO, $table, $row);
                        } else {
                            $this->updateLocalData($this->EXTERNAL_PDO, $table, $primaryKey, $row);
                        }
                    }
                }
            }
        } catch (PDOException $e) {
            echo "Erreur lors de la synchronisation vers la base de données externe : " . $e->getMessage();
        } finally {
            $this->enableForeignKeyChecks($this->EXTERNAL_PDO);
        }
    }

    private function getAllTablesList($pdo): array
    {
        try {
            $stmt = $pdo->query("SHOW TABLES");
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            echo "Erreur lors de la récupération de la liste des tables : " . $e->getMessage();
        }
    }

    private function getTableData($pdo, $table): array
    {
        try {
            $stmt = $pdo->prepare("SELECT * FROM $table");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            echo "Erreur lors de la récupération des données de la table $table : " . $e->getMessage();
        }
    }

    private function getPrimaryKeyColumns($pdo, $table)
    {
        try {
            $stmt = $pdo->prepare("SHOW KEYS FROM $table WHERE Key_name = 'PRIMARY'");
            $stmt->execute();
            $primaryKeys = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $columns = [];
            foreach ($primaryKeys as $primaryKey) {
                $columns[] = $primaryKey['Column_name'];
            }

            return $columns;
        } catch (PDOException $e) {
            echo "Erreur lors de la récupération des colonnes de clé primaire pour la table $table : " . $e->getMessage();
        }
    }


    private function isExternalDataNewer($existingRow, $row)
    {
        // Comparaison basique : nous supposons que la donnée externe est plus récente si elle a plus de colonnes non vides
        return count(array_filter($existingRow)) < count(array_filter($row));
    }

    private function insertRow($pdo, $table, $row)
    {
        try {
            $this->disableForeignKeyChecks($pdo);

            $columns = implode(', ', array_keys($row));
            $placeholders = implode(', ', array_fill(0, count($row), '?'));

            $stmt = $pdo->prepare("INSERT INTO $table ($columns) VALUES ($placeholders)");

            $stmt->execute(array_values($row));
        } catch (PDOException $e) {
            echo "Erreur lors de l'insertion dans la table $table : " . $e->getMessage();
        } finally {
            $this->enableForeignKeyChecks($pdo);
        }
    }

    private function updateLocalData($pdo, $table, $primaryKey, $row)
    {
        try {
            $this->disableForeignKeyChecks($pdo);

            $setClause = '';
            foreach ($row as $column => $value) {
                if ($column !== $primaryKey) {
                    $setClause .= "$column = :$column, ";
                }
            }
            $setClause = rtrim($setClause, ', ');

            $sql = "UPDATE $table SET $setClause WHERE $primaryKey = :$primaryKey";
            $stmt = $pdo->prepare($sql);

            // Binding parameters
            foreach ($row as $column => $value) {
                if ($column !== $primaryKey) {
                    $stmt->bindValue(":$column", $value);
                }
            }
            $stmt->bindValue(":$primaryKey", $row[$primaryKey]);

            $stmt->execute();
        } catch (PDOException $e) {
            echo "Erreur lors de la mise à jour de la table $table : " . $e->getMessage();
        } finally {
            $this->enableForeignKeyChecks($pdo);
        }
    }
}
