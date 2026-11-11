<?php
require_once __DIR__ . '/db.php';
// ============================================================
// backup.php — Sauvegarde de la base de données UniVerse
// ============================================================

// 1. Démarrer la session AVANT tout HTML
require_once 'auth.php';
requireRole('Administrateur');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================================
// CONFIGURATION — À AJUSTER SI NÉCESSAIRE
// ============================================================
// Chemin absolu vers mysqldump.exe dans XAMPP (Windows)
// Adaptez si votre XAMPP est installé ailleurs.
define('MYSQLDUMP_PATH', 'C:\\xampp\\mysql\\bin\\mysqldump.exe');

// Informations de connexion (identiques à celles du projet)
define('DB_HOST', 'localhost');
define('DB_NAME', 'universite');
define('DB_USER', 'root');
define('DB_PASS', '');

// Répertoire des sauvegardes (créé automatiquement)
$backups_dir = __DIR__ . DIRECTORY_SEPARATOR . 'backups';

// ============================================================
// CONNEXION PDO
// ============================================================
try {
    $bdd = getDBConnection();
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    error_log("[backup] Erreur connexion : " . $e->getMessage());
    die("Erreur de connexion à la base de données.");
}

require_once 'functions_archivage.php';

// ============================================================
// SÉCURITÉ : CSRF token
// ============================================================
if (empty($_SESSION['csrf_backup'])) {
    $_SESSION['csrf_backup'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_backup'];

// ============================================================
// CRÉATION DU RÉPERTOIRE + PROTECTION .htaccess
// ============================================================
if (!is_dir($backups_dir)) {
    if (!@mkdir($backups_dir, 0755, true)) {
        error_log("[backup] Impossible de créer le répertoire : $backups_dir");
    }
}

// Fichier .htaccess pour bloquer l'accès direct par URL
$htaccess_file = $backups_dir . DIRECTORY_SEPARATOR . '.htaccess';
if (!file_exists($htaccess_file)) {
    $htaccess_content = "Require all denied\nDeny from all\n";
    @file_put_contents($htaccess_file, $htaccess_content);
}

// ============================================================
// MESSAGE UTILISATEUR
// ============================================================
$message_succes = '';
$message_erreur = '';

// ============================================================
// ACTION 1 : CRÉER UNE SAUVEGARDE
// ============================================================
if (isset($_POST['creer_backup'])) {

    // Vérification CSRF
    if (!isset($_POST['csrf_token']) || !hash_equals($csrf_token, $_POST['csrf_token'])) {
        $message_erreur = "Jeton de sécurité invalide. Rechargez la page.";
    }
    // Vérification de l'existence de mysqldump
    elseif (!file_exists(MYSQLDUMP_PATH)) {
        $message_erreur = "mysqldump introuvable. Vérifiez le chemin dans la configuration du fichier backup.php.";
        error_log("[backup] mysqldump introuvable : " . MYSQLDUMP_PATH);
    }
    else {
        // Génération du nom de fichier
        $nom_fichier = 'backup_universite_' . date('Y-m-d_H-i-s') . '.sql';
        $chemin_fichier = $backups_dir . DIRECTORY_SEPARATOR . $nom_fichier;

        // Commande mysqldump sécurisée
        // --single-transaction : cohérence sans verrouiller les tables
        // --routines --triggers : inclut les procédures et triggers
        // --default-character-set=utf8mb4 : encodage
        $cmd = sprintf(
            '"%s" --host=%s --user=%s %s --single-transaction --routines --triggers --default-character-set=utf8mb4 %s > "%s" 2>&1',
            MYSQLDUMP_PATH,
            escapeshellarg(DB_HOST),
            escapeshellarg(DB_USER),
            (DB_PASS !== '' ? '--password=' . escapeshellarg(DB_PASS) : ''),
            escapeshellarg(DB_NAME),
            $chemin_fichier
        );

        // Exécution
        $sortie = [];
        $code_retour = 0;
        @exec($cmd, $sortie, $code_retour);

        if ($code_retour === 0 && file_exists($chemin_fichier) && filesize($chemin_fichier) > 0) {

            // Archivage de l'action
            try {
                $donnees_archive = [
                    'fichier'    => $nom_fichier,
                    'taille'     => filesize($chemin_fichier),
                    'date'       => date('Y-m-d H:i:s'),
                    'base'       => DB_NAME,
                ];
                archiverAction(
                    $bdd,
                    getUserIdEnSession($bdd),
                    'INSERT',
                    'backup',
                    null,
                    null,
                    $donnees_archive,
                    'Création d\'une sauvegarde : ' . $nom_fichier
                );
            } catch (Exception $e) {
                error_log("[backup] Erreur archivage : " . $e->getMessage());
            }

            $message_succes = "Sauvegarde créée avec succès : " . $nom_fichier
                . " (" . number_format(filesize($chemin_fichier) / 1024, 2, ',', ' ') . " Ko)";
        } else {
            error_log("[backup] Échec mysqldump. Code retour = $code_retour. Sortie = " . implode("\n", $sortie));
            @unlink($chemin_fichier);
            $message_erreur = "Échec de la création de la sauvegarde. Consultez les journaux du serveur.";
        }
    }
}

// ============================================================
// ACTION 2 : TÉLÉCHARGER UNE SAUVEGARDE
// ============================================================
if (isset($_GET['download']) && !empty($_GET['download'])) {

    $nom_demande = basename($_GET['download']); // Neutralise les "../
    $chemin_demande = $backups_dir . DIRECTORY_SEPARATOR . $nom_demande;

    // Vérifications de sécurité
    if (
        $nom_demande === '' ||
        !preg_match('/^backup_universite_[0-9]{4}-[0-9]{2}-[0-9]{2}_[0-9]{2}-[0-9]{2}-[0-9]{2}\.sql$/', $nom_demande) ||
        !file_exists($chemin_demande) ||
        !is_file($chemin_demande)
    ) {
        error_log("[backup] Tentative de téléchargement invalide : " . $nom_demande);
        die("Fichier introuvable ou invalide.");
    }

    // Envoi du fichier
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . $nom_demande . '"');
    header('Content-Length: ' . filesize($chemin_demande));
    header('Cache-Control: no-store');
    readfile($chemin_demande);
    exit();
}

// ============================================================
// LISTE DES SAUVEGARDES EXISTANTES
// ============================================================
$sauvegardes = [];
if (is_dir($backups_dir)) {
    $fichiers = glob($backups_dir . DIRECTORY_SEPARATOR . 'backup_universite_*.sql');
    if ($fichiers !== false) {
        foreach ($fichiers as $f) {
            if (is_file($f)) {
                $sauvegardes[] = [
                    'nom'    => basename($f),
                    'taille' => filesize($f),
                    'date'   => filemtime($f),
                ];
            }
        }
        // Tri par date décroissante
        usort($sauvegardes, function ($a, $b) { return $b['date'] - $a['date']; });
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sauvegarde de la base — UniVerse</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Arial, sans-serif; }
        body { background: #eef3f8; color: #1f2937; }
        .container { width: 95%; max-width: 900px; margin: 30px auto; }
        .page-header { background: linear-gradient(135deg, #003366, #0055aa); color: white; text-align: center; padding: 25px; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.2); margin-bottom: 20px; }
        .page-header h1 { font-size: 24px; }
        .page-header p { font-size: 13px; opacity: 0.9; margin-top: 5px; }
        .card { background: #fff; border-radius: 18px; box-shadow: 0 10px 25px rgba(0,0,0,.07); padding: 30px; margin-bottom: 20px; }
        .card h2 { color: #003366; margin-bottom: 20px; font-size: 18px; display: flex; align-items: center; gap: 10px; }
        .alert { padding: 14px 18px; border-radius: 10px; margin-bottom: 20px; font-weight: 600; font-size: 14px; }
        .alert-success { background: #d1fae5; color: #065f46; border-left: 4px solid #10b981; }
        .alert-error { background: #fee2e2; color: #991b1b; border-left: 4px solid #dc2626; }
        .info-box { background: #f1f5f9; padding: 14px 18px; border-radius: 10px; margin-bottom: 20px; font-size: 13px; border-left: 4px solid #3b82f6; color: #475569; }
        .btn { padding: 12px 24px; border: none; border-radius: 10px; font-size: 15px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: 8px; transition: all 0.2s; }
        .btn-success { background: #16a34a; color: white; }
        .btn-success:hover { background: #15803d; transform: translateY(-2px); }
        .btn-download { background: #2563eb; color: white; padding: 8px 16px; font-size: 13px; }
        .btn-download:hover { background: #1d4ed8; transform: translateY(-2px); }
        table { width: 100%; border-collapse: collapse; }
        th { background: #003366; color: white; padding: 12px; text-align: left; font-size: 13px; }
        td { padding: 12px; border-bottom: 1px solid #eef2f7; font-size: 14px; }
        tr:hover td { background: #f8fbff; }
        .empty { text-align: center; color: #94a3b8; padding: 30px; font-style: italic; }
        .badge { display: inline-block; padding: 3px 10px; border-radius: 12px; background: #eff6ff; color: #1e40af; font-weight: 600; font-size: 11px; }
    </style>
</head>
<body>
<?php include("menu.php"); ?>

<div class="container">

    <div class="page-header">
        <h1>💾 Sauvegarde de la base de données</h1>
        <p>Base concernée : <strong><?= htmlspecialchars(DB_NAME) ?></strong></p>
    </div>

    <?php if ($message_succes !== ''): ?>
        <div class="alert alert-success">✅ <?= htmlspecialchars($message_succes) ?></div>
    <?php endif; ?>
    <?php if ($message_erreur !== ''): ?>
        <div class="alert alert-error">❌ <?= htmlspecialchars($message_erreur) ?></div>
    <?php endif; ?>

    <div class="card">
        <h2>⚡ Créer une nouvelle sauvegarde</h2>
        <div class="info-box">
            💡 La sauvegarde inclut les tables, les routines et les triggers de la base <strong><?= htmlspecialchars(DB_NAME) ?></strong>.
            Elle est enregistrée dans le dossier <code>backups/</code> et n'est téléchargeable qu'via cette interface.
        </div>
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <button type="submit" name="creer_backup" class="btn btn-success">💾 Créer une sauvegarde</button>
        </form>
    </div>

    <div class="card">
        <h2>📁 Sauvegardes disponibles (<?= count($sauvegardes) ?>)</h2>
        <?php if (count($sauvegardes) === 0): ?>
            <div class="empty">Aucune sauvegarde disponible pour le moment.</div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Nom du fichier</th>
                        <th>Taille</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sauvegardes as $s): ?>
                        <tr>
                            <td><span class="badge">📄</span> <?= htmlspecialchars($s['nom']) ?></td>
                            <td><?= number_format($s['taille'] / 1024, 2, ',', ' ') ?> Ko</td>
                            <td><?= date('d/m/Y H:i:s', $s['date']) ?></td>
                            <td>
                                <a href="?download=<?= urlencode($s['nom']) ?>" class="btn btn-download">⬇️ Télécharger</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

</div>
</body>
</html>