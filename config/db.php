<?php
/**
 * Database Connection File
 * 
 * This file establishes the connection to MySQL database using PDO
 * and provides a singleton pattern for database access.
 */

class Database {
    private static $pdo = null;

    public static function getInstance() {
        if (self::$pdo === null) {
            try {
                // Log attempt to connect
                $logMsg = "Attempting database connection to: localhost, dbname=county_gov_tracking";
                error_log($logMsg);
                
                // Check if mysqli extension is loaded
                if (!extension_loaded('pdo_mysql')) {
                    throw new Exception("PDO MySQL extension is not loaded");
                }
                
                // First create a connection without specifying database to check if database exists
                $tempPdo = new PDO(
                    "mysql:host=localhost",
                    'root',
                    '',
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                    ]
                );
                
                // Check if the database exists
                $dbname = 'county_gov_tracking';
                $checkDb = $tempPdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$dbname'");
                if ($checkDb->rowCount() === 0) {
                    // Database doesn't exist, attempt to create it
                    error_log("Database '$dbname' does not exist. Attempting to create it.");
                    $tempPdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
                    error_log("Database created successfully.");
                }
                
                // Now connect with the database specified
                self::$pdo = new PDO(
                    "mysql:host=localhost;dbname=$dbname",
                    'root',
                    '',
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                    ]
                );
                
                // Test if connection works
                $testQuery = self::$pdo->query("SELECT 1");
                if ($testQuery === false) {
                    throw new PDOException("Connection established but unable to execute basic query");
                }
                
                error_log("Database connection successful");
            } catch (PDOException $e) {
                $errorMsg = "Database connection error: " . $e->getMessage();
                error_log($errorMsg);
                
                // Add more detail in error message
                $errorDetails = "PDO Error Code: " . (isset($e->errorInfo[1]) ? $e->errorInfo[1] : 'N/A');
                error_log($errorDetails);
                
                // Die with useful message
                die("Database connection failed: " . $e->getMessage());
            } catch (Exception $e) {
                error_log("General error: " . $e->getMessage());
                die("Error: " . $e->getMessage());
            }
        }
        return self::$pdo;
    }
}

function db() {
    return Database::getInstance();
}