<?php
require_once __DIR__ . '/db.php';
// rapports_admin.php
require_once 'auth.php';
requireRole('Administrateur');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $bdd = getDBConnection();
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Erreur de connexion.");
}

// Export CSV
if (isset($_GET['export']) && $_GET['export'] === 'users') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=utilisateurs_'.date('Ymd').'.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Nom', 'Prénom', 'Username', 'Email', 'Rôle', 'Statut', 'Date création']);
    
    $stmt = $bdd->query("SELECT id_user, nom, prenom, username, email, role, statut, date_creation FROM users ORDER BY id_user");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

$stats = $bdd->query("
    SELECT 
        (SELECT COUNT(*) FROM users) as users,
        (SELECT COUNT(*) FROM etudiants) as etudiants,
        (SELECT COUNT(*) FROM professeurs) as professeurs,
        (SELECT COUNT(*) FROM paiements WHERE statut = 'valide') as paiements_valides
")->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Rapports & Exports</title>
    <style>
        body { font-family: 'Inter', sans-serif; background: #f8fafc; padding: 30px; margin: 0; color: #1e293b; }
        h1 { font-size: 24px; margin-bottom: 20px; color: #0f172a; }
        
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .card { background: #fff; padding: 25px; border-radius: 12px; border: 1px solid #e2e8f0; }
        .card h2 { font-size: 16px; margin: 0 0 15px 0; color: #0f172a; }
        
        .stat-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f1f5f9; }
        .stat-row:last-child { border-bottom: none; }
        .stat-value { font-weight: 700; color: #3b82f6; }
        
        .btn-export { display: inline-flex; align-items: center; gap: 8px; padding: 12px 20px; background: #10b981; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; margin-top: 15px; transition: 0.2s; }
        .btn-export:hover { background: #059669; }
    </style>
</head>
<body>
    <h1>Rapports & Exports</h1>

    <div class="grid">
        <div class="card">
            <h2>Résumé Global du Système</h2>
            <div class="stat-row">
                <span>Total Utilisateurs</span>
                <span class="stat-value"><?= $stats['users'] ?></span>
            </div>
            <div class="stat-row">
                <span>Étudiants inscrits</span>
                <span class="stat-value"><?= $stats['etudiants'] ?></span>
            </div>
            <div class="stat-row">
                <span>Professeurs</span>
                <span class="stat-value"><?= $stats['professeurs'] ?></span>
            </div>
            <div class="stat-row">
                <span>Paiements validés</span>
                <span class="stat-value"><?= $stats['paiements_valides'] ?></span>
            </div>
        </div>

        <div class="card">
            <h2>Exporter les données</h2>
            <p style="font-size: 14px; color: #64748b; margin-bottom: 20px;">
                Téléchargez la liste complète des utilisateurs au format CSV (compatible avec Excel).
            </p>
            <a href="?export=users" class="btn-export">
                📊 Exporter les Utilisateurs (CSV)
            </a>
        </div>
    </div>
</body>
</html>