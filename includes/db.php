<?php
/**
 * Mini CRM - Connexion à la base de données
 */

// Charger la configuration
$config_file = __DIR__ . '/../config/config.php';

if (!file_exists($config_file)) {
    header('Location: install/index.php');
    exit;
}

require_once $config_file;

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données. Veuillez vérifier votre configuration.");
}

/**
 * Retourne la connexion PDO
 */
function getDbConnection(): PDO {
    global $pdo;
    return $pdo;
}
