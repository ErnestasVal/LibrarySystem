<?php
require __DIR__ . '/../vendor/autoload.php';

// Load .env from project root (one level above database/)
if (class_exists('Dotenv\\Dotenv')) {
    try {
        $root = dirname(__DIR__);
        Dotenv\Dotenv::createImmutable($root)->safeLoad();
    } catch (Exception $e) {
        // ignore .env loading errors
    }
}

$host = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? 'localhost');
$dbname = getenv('DB_NAME') ?: ($_ENV['DB_NAME'] ?? 'biblioteka');
$user = getenv('DB_USER') ?: ($_ENV['DB_USER'] ?? 'stud');
$pass = getenv('DB_PASS') ?: ($_ENV['DB_PASS'] ?? 'stud');

try {
    // Connect to MySQL server and ensure database is created with utf8mb4 (good for Lithuanian)
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    // Create database with utf8mb4 character set and a Unicode collation
    // If MySQL >=8 and a Lithuanian-specific collation is available you can change the COLLATE value.
    $createSql = "CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    $pdo->exec($createSql);
    $pdo->exec("USE `$dbname`");
    $pdo->exec("SET NAMES 'utf8mb4' COLLATE 'utf8mb4_unicode_ci'");

    // Load and execute schema SQL from the same folder as this script
    $schemaFile = __DIR__ . '/schema.sql';
    if (!file_exists($schemaFile)) {
        throw new RuntimeException("Schema file not found: $schemaFile");
    }
    $sql = file_get_contents($schemaFile);
    if ($sql === false) {
        throw new RuntimeException("Failed to read schema file: $schemaFile");
    }

    $pdo->exec($sql);

    echo "Database setup complete!\n";
} catch (PDOException $e) {
    die("PDO Error: " . $e->getMessage());
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}