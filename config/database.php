<?php
// config/database.php - PDO Database Connection with SQLite / PostgreSQL support

function getDbConnection() {
    static $pdo = null;

    if ($pdo === null) {
        $dbDriver = getenv('DB_DRIVER') ?: 'sqlite';

        if ($dbDriver === 'pgsql') {
            $host = getenv('DB_HOST') ?: '127.0.0.1';
            $port = getenv('DB_PORT') ?: '5432';
            $dbname = getenv('DB_NAME') ?: 'disc_db';
            $user = getenv('DB_USER') ?: 'disc_user';
            $pass = getenv('DB_PASS') ?: 'disc_pass';
            $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";
            try {
                $pdo = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            } catch (PDOException $e) {
                // Fallback to SQLite if PgSQL isn't running or configured
                $sqlitePath = __DIR__ . '/../disc_assessment.sqlite';
                $pdo = new PDO("sqlite:{$sqlitePath}", null, null, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
            }
        } else {
            $sqlitePath = __DIR__ . '/../disc_assessment.sqlite';
            $pdo = new PDO("sqlite:{$sqlitePath}", null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $pdo->exec("PRAGMA foreign_keys = ON;");
        }
    }

    return $pdo;
}
