<?php
/**
 * Connexion à la base de données - Immobilier CI
 *
 * En local (WampServer) : les valeurs par défaut ci-dessous suffisent.
 * Sur Render (ou tout hébergeur basé sur des variables d'environnement) :
 * définissez DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS, SITE_URL dans les
 * variables d'environnement du service — elles remplacent automatiquement
 * les valeurs par défaut, sans toucher au code.
 */

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'immobilier_ci');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

// Nom du site / infos globales par défaut (surchargées par la table settings)
define('SITE_NAME', getenv('SITE_NAME') ?: 'Immobilier CI');
define('SITE_URL', getenv('SITE_URL') ?: 'http://localhost/immobilier-ci'); // sans slash final

// Dossier d'upload des images des annonces (chemin serveur + chemin web)
define('UPLOAD_DIR', __DIR__ . '/../uploads/properties/');
define('UPLOAD_URL', 'uploads/properties/');

function getPDO(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            if (function_exists('ensureSchemaUpToDate')) {
                ensureSchemaUpToDate($pdo);
            }
        } catch (PDOException $e) {
            http_response_code(500);
            die('Erreur de connexion à la base de données. Vérifiez la configuration (variables d\'environnement ou config/db.php). (' . htmlspecialchars($e->getMessage()) . ')');
        }
    }
    return $pdo;
}
