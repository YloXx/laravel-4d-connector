<?php
//

namespace Jouwgebruikersnaam\Laravel4DConnector;

use GuzzleHttp\Client;
use PDO;

class Laravel4DConnector
{
    public static function connect($dsn, $username, $password, $laravelUrl)
    {
        $pdo = new PDO($dsn, $username, $password);

        $stmt = $pdo->query('SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE="BASE TABLE"');
        $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalTables = count($tables);
        $tableCount = 0;

        foreach ($tables as $table) {
            $tableCount++;
            $tableName = $table['TABLE_NAME'];

            $stmt = $pdo->query("SELECT * FROM $tableName");
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $client = new Client(['base_uri' => $laravelUrl]);
            $response = $client->request('POST', '/api/' . $tableName, [
                'json' => $results
            ]);

            $status = $response->getStatusCode();

            if ($status == 200) {
                $message = "Tabel '$tableName' succesvol gesynchroniseerd met Laravel";
            } else {
                $message = "Er is een fout opgetreden bij het synchroniseren van tabel '$tableName' met Laravel";
            }

            echo "$message\n";

            $stmt = $pdo->query("SELECT FKTABLE_NAME, FKCOLUMN_NAME, PKTABLE_NAME, PKCOLUMN_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME='$tableName'");
            $relations = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($relations as $relation) {
                $fkTableName = $relation['FKTABLE_NAME'];
                $fkColumnName = $relation['FKCOLUMN_NAME'];
                $pkTableName = $relation['PKTABLE_NAME'];
                $pkColumnName = $relation['PKCOLUMN_NAME'];

                $stmt = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='$pkTableName'");
                $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $relatedColumns = array();
                foreach ($columns as $column) {
                    $relatedColumns[] = $column['COLUMN_NAME'];
                }

                $relatedQuery = "SELECT ";

                foreach ($relatedColumns as $relatedColumn) {
                    $relatedQuery .= "$pkTableName.$relatedColumn, ";
                }

                $relatedQuery = substr($relatedQuery, 0, -2);
                $relatedQuery .= " FROM $pkTableName, $fkTableName WHERE $fkTableName.$fkColumnName = $pkTableName.$pkColumnName";

                $stmt = $pdo->query($relatedQuery);
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $client = new Client(['base_uri' => $laravelUrl]);
                $response = $client->request('POST', '/api/' . $fkTableName . '/' . $pkColumnName, [
                    'json' => $results
                ]);

                $status = $response->getStatusCode();

                if ($status == 200) {
                    $message = "Relatie tussen '$tableName' en '$fkTableName' succesvol gesynchroniseerd met Laravel";
                } else {
                    $message = "Er is een fout opgetreden bij het synchroniseren van de relatie tussen '$tableName' en '$fkTableName' met Laravel";
                }

                echo "$message\n";
            }

            if ($tableCount < $totalTables) {
                echo "\n-----------------------\n\n";
            }
        }
    }
}
