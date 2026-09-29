<?php
// config/database.php - PDO Database Connection Singleton / Helper

function getDbConnection() {
    static $pdo = null;

    if ($pdo === null) {
        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $port = getenv('DB_PORT') ?: '5432';
        $dbname = getenv('DB_NAME') ?: 'aptitude_db';
        $user = getenv('DB_USER') ?: 'aptitude';
        $pass = getenv('DB_PASS') ?: 'aptitude_pass';

        $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";

        try {
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            error_log("Database connection error: " . $e->getMessage());
            throw new Exception("Database Connection Error. Please verify configuration.");
        }
    }

    return $pdo;
}
