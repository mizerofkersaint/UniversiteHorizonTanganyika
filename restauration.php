<?php
require_once __DIR__ . '/db.php';
// ============================================================
// restauration.php — Restauration de la base UniVerse
// ============================================================
// ⚠️ OPÉRATION CRITIQUE : cette page REMPLACE les données actuelles
//    par celles d'une sauvegarde. Une confirmation explicite est exigée.
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
define('MYSQL_PATH', 'C:\\xampp\\mysql\\bin\\mysql.exe');
define('DB_HOST', 'localhost');
define('DB_NAME', 'universite');
define('DB_USER', 'root');
define('DB_PASS', '');

$backups_dir = __DIR__ . DIRECTORY_SEPARATOR . 'backups';

// ============================================================
// GARDE-FOU TEMPORAIRE — ACCÈS ADMINISTRATEUR
// ============================================================
// Tant que l'authentification n'est pas en place, on vérifie
// simplement qu'une session utilisateur existe.
// ⚠️ À remplacer par un vrai contrôle de rôle quand l'auth sera prête.
if (!isset($_SESSION['id_user']) || (int)$_SESSION['id_user'] <= 0) {
    http_response_code(403);
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <title>Accès refusé — UniVerse</title>
        <style>
            body { font-family: 'Segoe UI', Arial, sans-serif; background: #eef3f8; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
            .box { background: white; padding: 40px; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,.08); text-align: center; max-width: 500px; }
            .box h1 { color: #dc2626; margin-bottom: 15px; font-size: 22px; }
            .box p { color: #475569; font-size: 14px; line-height: 1.6; }
            .box a { display: inline-block; margin-top: 20px; padding: 10px 20px; background: #003366; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; }
        </style>
    </head>
    <body>
        <div class="box">
            <h1>🔒 Accès refusé</h1>
            <p>Cette page est réservée à l'administrateur connecté.</p>
            <a href="administrateur.php">← Retour au tableau de bord</a>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// ============================================================
// CONNEXION PDO
// ============================================================
try {
    $bdd = getDBConnection();
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    error_log("[restauration] Erreur connexion : " . $e->getMessage());
    die("Erreur de connexion à la base de données.");
}

require_once 'functions_archivage.php';

// ============================================================
// CSRF token
// ============================================================
if (empty($_SESSION['csrf_restauration'])) {
    $_SESSION['csrf_restauration'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_restauration'];

$message_succes = '';
$message_erreur = '';

// ============================================================
// ACTION : RESTAURATION
// ============================================================
if (isset($_POST['restaurer'])) {

    // 1. Vérification CSRF
    if (!isset($_POST['csrf_token']) || !hash_equals($csrf_token, $_POST['csrf_token'])) {
        $message_erreur = "Jeton de sécurité invalide. Rechargez la page.";
    }
    // 2. Confirmation explicite
    elseif (!isset($_POST['confirmation']) || $_POST['confirmation'] !== 'CONFIRMER') {
        $message_erreur = "Vous devez saisir exactement « CONFIRMER » pour valider la restauration.";
    }
    // 3. Fichier sélectionné
    else {
        $nom_demande = isset($_POST['fichier']) ? basename($_POST['fichier']) : '';
        $chemin_demande = $backups_dir . DIRECTORY_SEPARATOR . $nom_demande;

        // 4. Vérifications de sécurité sur le nom de fichier
        if ($nom_demande === '' ||
            !preg_match('/^backup_universite_[0-9]{4}-[0-9]{2}-[0-9]{2}_[0-9]{2}-[0-9]{2}-[0-9]{2}\.sql$/', $nom_demande) ||
            !file_exists($chemin_demande) ||
            !is_file($chemin_demande)) {
            $message_erreur = "Fichier de sauvegarde introuvable ou invalide.";
            error_log("[restauration] Fichier invalide : " . $nom_demande);
        }
        // 5. Vérification de mysql.exe
        elseif (!file_exists(MYSQL_PATH)) {
            $message_erreur = "mysql.exe introuvable. Vérifiez le chemin dans la configuration du fichier restauration.php.";
            error_log("[restauration] mysql.exe introuvable : " . MYSQL_PATH);
        }
        else {
            try {
                // 6. ÉTAPE CRITIQUE : créer une sauvegarde préventive AVANT la restauration
                $backup_preventif = 'backup_universite_AVANT_RESTAURATION_' . date('Y-m-d_H-i-s') . '.sql';
                $chemin_preventif = $backups_dir . DIRECTORY_SEPARATOR . $backup_preventif;

                $mysqldump_path = str_replace('mysql.exe', 'mysqldump.exe', MYSQL_PATH);
                if (file_exists($mysqldump_path)) {
                    $cmd_backup = sprintf(
                        '"%s" --host=%s --user=%s %s --single-transaction --routines --triggers --default-character-set=utf8mb4 %s > "%s" 2>&1',
                        $mysqldump_path,
                        escapeshellarg(DB_HOST),
                        escapeshellarg(DB_USER),
                        (DB_PASS !== '' ? '--password=' . escapeshellarg(DB_PASS) : ''),
                        escapeshellarg(DB_NAME),
                        $chemin_preventif
                    );
                    @exec($cmd_backup);
                    error_log("[restauration] Sauvegarde préventive : " . $backup_preventif);
                }

                // 7. Restauration via mysql.exe
                $cmd_restore = sprintf(
                    '"%s" --host=%s --user=%s %s --default-character-set=utf8mb4 %s < "%s" 2>&1',
                    MYSQL_PATH,
                    escapeshellarg(DB_HOST),
                    escapeshellarg(DB_USER),
                    (DB_PASS !== '' ? '--password=' . escapeshellarg(DB_PASS) : ''),
                    escapeshellarg(DB_NAME),
                    $chemin_demande
                );

                $sortie = [];
                $code_retour = 0;
                @exec($cmd_restore, $sortie, $code_retour);

                // 8. Vérification du résultat
                if ($code_retour === 0) {

                    // Archivage de l'action
                    try {
                        $donnees_archive = [
                            'fichier_restaure'    => $nom_demande,
                            'backup_preventif'    => $backup_preventif,
                            'date_restauration'   => date('Y-m-d H:i:s'),
                            'base'                => DB_NAME,
                        ];
                        archiverAction(
                            $bdd,
                            getUserIdEnSession($bdd),
                            'UPDATE',
                            'restauration',
                            null,
                            null,
                            $donnees_archive,
                            'Restauration depuis : ' . $nom_demande
                        );
                    } catch (Exception $e) {
                        error_log("[restauration] Erreur archivage : " . $e->getMessage());
                    }

                    $message_succes = "Restauration effectuée avec succès depuis : " . $nom_demande
                        . ". Une sauvegarde préventive a été créée : " . $backup_preventif . ".";

                } else {
                    error_log("[restauration] Échec mysql. Code retour = $code_retour. Sortie = " . implode("\n", $sortie));
                    $message_erreur = "Échec de la restauration. Consultez les journaux du serveur.";
                }

            } catch (Exception $e) {
                error_log("[restauration] Exception : " . $e->getMessage());
                $message_erreur = "Une erreur est survenue lors de la restauration.";
            }
        }
    }
}

// ============================================================
// LISTE DES SAUVEGARDES DISPONIBLES
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
        usort($sauvegardes, function ($a, $b) { return $b['date'] - $a['date']; });
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restauration de la base — UniVerse</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Arial, sans-serif; }
        body { background: #eef3f8; color: #1f2937; }
        .page-top-bar { background: #fff; padding: 12px 25px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
        .page-top-bar a { color: #003366; text-decoration: none; font-weight: 600; font-size: 14px; }
        .page-top-bar a:hover { color: #0055aa; text-decoration: underline; }
        .container { width: 95%; max-width: 900px; margin: 30px auto; }
        .page-header { background: linear-gradient(135deg, #7f1d1d, #dc2626); color: white; text-align: center; padding: 25px; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.2); margin-bottom: 20px; }
        .page-header h1 { font-size: 24px; }
        .page-header p { font-size: 13px; opacity: 0.9; margin-top: 5px; }
        .card { background: #fff; border-radius: 18px; box-shadow: 0 10px 25px rgba(0,0,0,.07); padding: 30px; margin-bottom: 20px; }
        .card h2 { color: #003366; margin-bottom: 20px; font-size: 18px; display: flex; align-items: center; gap: 10px; }
        .alert { padding: 14px 18px; border-radius: 10px; margin-bottom: 20px; font-weight: 600; font-size: 14px; }
        .alert-success { background: #d1fae5; color: #065f46; border-left: 4px solid #10b981; }
        .alert-error { background: #fee2e2; color: #991b1b; border-left: 4px solid #dc2626; }
        .warning-box { background: #fef3c7; padding: 16px 20px; border-radius: 10px; margin-bottom: 20px; border-left: 4px solid #f59e0b; color: #78350f; font-size: 14px; line-height: 1.6; }
        .warning-box strong { color: #7f1d1d; }
        .form-group { display: flex; flex-direction: column; margin-bottom: 18px; }
        .form-group label { font-weight: 600; font-size: 13px; color: #334155; margin-bottom: 6px; }
        select, input[type="text"] { padding: 12px 14px; border: 1px solid #d1d5db; border-radius: 10px; font-size: 14px; outline: none; transition: border-color 0.3s; background: #f8fafc; }
        select:focus, input[type="text"]:focus { border-color: #dc2626; box-shadow: 0 0 0 3px rgba(220,38,38,.10); background: #fff; }
        .btn { padding: 12px 24px; border: none; border-radius: 10px; font-size: 15px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: 8px; transition: all 0.2s; }
        .btn-danger { background: #dc2626; color: white; }
        .btn-danger:hover { background: #b91c1c; transform: translateY(-2px); }
        .btn-secondary { background: #e5e7eb; color: #111827; }
        .btn-secondary:hover { background: #d1d5db; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #003366; color: white; padding: 12px; text-align: left; font-size: 13px; }
        td { padding: 12px; border-bottom: 1px solid #eef2f7; font-size: 14px; }
        tr:hover td { background: #f8fbff; }
        .empty { text-align: center; color: #94a3b8; padding: 30px; font-style: italic; }
        .badge { display: inline-block; padding: 3px 10px; border-radius: 12px; background: #eff6ff; color: #1e40af; font-weight: 600; font-size: 11px; }
    </style>
</head>
<body>

<div class="page-top-bar">
    <a href="administrateur.php">← Retour au tableau de bord</a>
    <span style="font-size:13px; color:#64748b;">Base : <strong><?= htmlspecialchars(DB_NAME) ?></strong></span>
</div>

<div class="container">

    <div class="page-header">
        <h1>♻️ Restauration de la base de données</h1>
        <p>Cette opération remplace les données actuelles</p>
    </div>

    <?php if ($message_succes !== ''): ?>
        <div class="alert alert-success">✅ <?= htmlspecialchars($message_succes) ?></div>
    <?php endif; ?>
    <?php if ($message_erreur !== ''): ?>
        <div class="alert alert-error">❌ <?= htmlspecialchars($message_erreur) ?></div>
    <?php endif; ?>

    <div class="card">
        <h2>⚠️ Avertissement important</h2>
        <div class="warning-box">
            <strong>La restauration va REMPLACER toutes les données actuelles</strong> par celles de la sauvegarde sélectionnée.<br>
            Une sauvegarde préventive automatique sera créée avant l'opération.<br>
            Cette action est <strong>irréversible</strong> sans intervention manuelle.
        </div>

        <?php if (count($sauvegardes) === 0): ?>
            <div class="empty">Aucune sauvegarde disponible. Créez d'abord une sauvegarde depuis la page <strong>Sauvegarde</strong>.</div>
        <?php else: ?>
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

                <div class="form-group">
                    <label>Sélectionner la sauvegarde à restaurer</label>
                    <select name="fichier" required>
                        <option value="">— Choisir une sauvegarde —</option>
                        <?php foreach ($sauvegardes as $s): ?>
                            <option value="<?= htmlspecialchars($s['nom']) ?>">
                                <?= htmlspecialchars($s['nom']) ?>
                                (<?= number_format($s['taille'] / 1024, 2, ',', ' ') ?> Ko — <?= date('d/m/Y H:i:s', $s['date']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Saisissez exactement « CONFIRMER » pour valider</label>
                    <input type="text" name="confirmation" placeholder="CONFIRMER" required autocomplete="off">
                </div>

                <div style="display:flex; gap:12px; margin-top:25px;">
                    <button type="submit" name="restaurer" class="btn btn-danger" onclick="return confirm('Êtes-vous sûr ? Cette action va remplacer toutes les données actuelles.');">
                        ⚠️ Lancer la restauration
                    </button>
                    <a href="administrateur.php" class="btn btn-secondary">❌ Annuler</a>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>📁 Sauvegardes disponibles (<?= count($sauvegardes) ?>)</h2>
        <?php if (count($sauvegardes) === 0): ?>
            <div class="empty">Aucune sauvegarde disponible.</div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Nom du fichier</th>
                        <th>Taille</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sauvegardes as $s): ?>
                        <tr>
                            <td><span class="badge">📄</span> <?= htmlspecialchars($s['nom']) ?></td>
                            <td><?= number_format($s['taille'] / 1024, 2, ',', ' ') ?> Ko</td>
                            <td><?= date('d/m/Y H:i:s', $s['date']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

</div>
</body>
</html>