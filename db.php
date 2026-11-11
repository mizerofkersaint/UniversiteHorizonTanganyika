<?php
/**
 * Connexion centrale UniVerse — InfinityFree / MySQL
 * Remplace les 4 valeurs ci-dessous par celles affichées dans le panneau InfinityFree.
 * Ne mets PAS localhost, root ou un mot de passe vide.
 */
if (!defined('DB_HOST')) define('DB_HOST', 'REMPLACER_PAR_MYSQL_HOST');
if (!defined('DB_NAME')) define('DB_NAME', 'REMPLACER_PAR_NOM_BASE');
if (!defined('DB_USER')) define('DB_USER', 'REMPLACER_PAR_UTILISATEUR_MYSQL');
if (!defined('DB_PASS')) define('DB_PASS', 'REMPLACER_PAR_MOT_DE_PASSE_MYSQL');

function getDBConnection(): PDO {
    static $connection = null;
    if ($connection instanceof PDO) return $connection;
    if (strpos(DB_HOST, 'REMPLACER_') === 0 || strpos(DB_NAME, 'REMPLACER_') === 0 || strpos(DB_USER, 'REMPLACER_') === 0) {
        throw new RuntimeException('Configure les identifiants MySQL InfinityFree dans db.php.');
    }
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $connection = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $connection;
}
