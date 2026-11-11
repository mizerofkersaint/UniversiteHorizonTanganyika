<?php
require_once __DIR__ . '/db.php';
require_once 'auth.php';
requireRole('Recteur');
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// -------------------------------------------------------------------------
// 1. CONNEXION À LA BASE DE DONNÉES
// -------------------------------------------------------------------------
try {
    $bdd = getDBConnection();
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// -------------------------------------------------------------------------
// 2. VÉRIFICATION DU RÔLE (SÉCURITÉ)
// -------------------------------------------------------------------------
$id_user = isset($_SESSION['id_user']) ? intval($_SESSION['id_user']) : 1;

$reqUser = $bdd->prepare("SELECT nom, prenom, role, photo FROM users WHERE id_user = ?");
$reqUser->execute([$id_user]);
$user = $reqUser->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    $user = [
        'nom' => 'Kersaint',
        'prenom' => 'Mizero Francis',
        'role' => 'RECTEUR',
        'photo' => 'default.png'
    ];
}

$photo_profil = !empty($user['photo']) && file_exists("uploads/" . $user['photo']) ? $user['photo'] : 'default.png';

// -------------------------------------------------------------------------
// 3. STATISTIQUES D'AUDIT
// -------------------------------------------------------------------------
$totalEtudiants = $bdd->query("SELECT COUNT(*) FROM etudiants")->fetchColumn() ?: 0;
$totalEtablissements = $bdd->query("SELECT COUNT(*) FROM departements")->fetchColumn() ?: 0;
$totalPersonnel = $bdd->query("SELECT (SELECT COUNT(*) FROM professeurs) + (SELECT COUNT(*) FROM users WHERE role = 'Comptable')")->fetchColumn() ?: 0;
$totalRecettes = $bdd->query("SELECT SUM(montant) FROM paiements")->fetchColumn() ?: 0;

// -------------------------------------------------------------------------
// 4. EXPORT EXCEL AVEC FACULTÉ (AJOUTÉ)
// -------------------------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] == 'download_excel') {
    // Données de base
    $effGlobal = $bdd->query("SELECT COUNT(*) FROM etudiants")->fetchColumn() ?: 0;
    $entitesActives = $bdd->query("SELECT COUNT(*) FROM departements")->fetchColumn() ?: 0;
    $recettesTotales = $bdd->query("SELECT SUM(montant) FROM paiements")->fetchColumn() ?: 0;
    
    // Recettes par faculté
    $recettesFacultes = $bdd->query("
        SELECT d.faculte, SUM(p.montant) as total_recettes, COUNT(p.id_paiement) as nb_paiements
        FROM paiements p
        JOIN etudiants e ON p.id_etudiant = e.id_etudiant
        JOIN classes c ON e.id_classe = c.id_classe
        JOIN departements d ON c.id_dept = d.id_dept
        GROUP BY d.faculte
        ORDER BY total_recettes DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Répartition des étudiants par faculté
    $facultesStats = $bdd->query("
        SELECT d.faculte, COUNT(e.id_etudiant) as nb_etudiants 
        FROM etudiants e
        JOIN classes c ON e.id_classe = c.id_classe
        JOIN departements d ON c.id_dept = d.id_dept
        GROUP BY d.faculte
        ORDER BY nb_etudiants DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=Audit_Finances_" . date('Y-m-d') . ".xls");
    echo "\xEF\xBB\xBF"; 

    echo "<table border='1' style='border-collapse:collapse;'>";
    echo "<tr><th colspan='2' style='background-color:#1e3a8a; color:white; font-size:14px; padding:10px;'>AUDIT FINANCIER — RECTORAT</th></tr>";
    
    // Statistiques globales
    echo "<tr><td style='font-weight:bold; width:200px;'>Effectifs Globaux (Étudiants)</td><td style='text-align:right;'>" . number_format($effGlobal, 0, ',', ' ') . "</td></tr>";
    echo "<tr><td style='font-weight:bold;'>Entités (Départements) Actives</td><td style='text-align:right;'>" . $entitesActives . "</td></tr>";
    echo "<tr><td style='font-weight:bold;'>Recettes Totales perçues</td><td style='text-align:right; color:#10b981; font-weight:bold;'>" . number_format($recettesTotales, 0, ',', ' ') . " BIF</td></tr>";
    
    // Répartition par faculté (étudiants)
    echo "<tr><td colspan='2' style='background-color:#f1f5f9; font-weight:bold; padding:10px;'>📊 RÉPARTITION DES ÉTUDIANTS PAR FACULTÉ</td></tr>";
    if(!empty($facultesStats)){
        foreach($facultesStats as $f) {
            $faculteNom = $f['faculte'] ?: 'Non assignée';
            echo "<tr><td style='padding-left:20px;'>" . htmlspecialchars($faculteNom) . "</td><td style='text-align:right;'>" . $f['nb_etudiants'] . " étudiants</td></tr>";
        }
    } else {
        echo "<tr><td colspan='2' style='text-align:center; color:#64748b;'>Aucune donnée disponible</td></tr>";
    }
    
    // Recettes par faculté
    echo "<tr><td colspan='2' style='background-color:#f1f5f9; font-weight:bold; padding:10px;'>💰 RECETTES PAR FACULTÉ</td></tr>";
    if(!empty($recettesFacultes)){
        foreach($recettesFacultes as $rf) {
            $faculteNom = $rf['faculte'] ?: 'Non assignée';
            echo "<tr><td style='padding-left:20px;'>" . htmlspecialchars($faculteNom) . "</td><td style='text-align:right; color:#10b981;'>" . number_format($rf['total_recettes'], 0, ',', ' ') . " BIF (" . $rf['nb_paiements'] . " paiements)</td></tr>";
        }
    } else {
        echo "<tr><td colspan='2' style='text-align:center; color:#64748b;'>Aucune donnée disponible</td></tr>";
    }
    
    echo "</table>";
    exit;
}

// -------------------------------------------------------------------------
// 5. COLLECTE DU DÉTAIL DES RECETTES PAR DÉPARTEMENT AVEC FACULTÉ
// -------------------------------------------------------------------------
$recettesParDept = $bdd->query("
    SELECT d.nom_dept, d.faculte,
           COUNT(p.id_paiement) as transactions, 
           SUM(p.montant) as total_collecte,
           AVG(p.montant) as moyenne_transaction,
           MIN(p.montant) as min_transaction,
           MAX(p.montant) as max_transaction
    FROM paiements p
    JOIN etudiants e ON p.id_etudiant = e.id_etudiant
    LEFT JOIN classes c ON e.id_classe = c.id_classe
    LEFT JOIN departements d ON c.id_dept = d.id_dept
    GROUP BY d.id_dept, d.nom_dept, d.faculte
    ORDER BY total_collecte DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Registre Supérieur de suivi général
$listeInscriptions = $bdd->query("
    SELECT e.matricule, u.nom, u.prenom, c.niveau, d.nom_dept, d.faculte
    FROM etudiants e
    JOIN users u ON e.id_user = u.id_user
    LEFT JOIN classes c ON e.id_classe = c.id_classe
    LEFT JOIN departements d ON c.id_dept = d.id_dept
    ORDER BY u.nom ASC LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);

// Statistiques financières
$statsFinancieres = $bdd->query("
    SELECT 
        COUNT(*) as total_paiements,
        SUM(montant) as total_encaisse,
        AVG(montant) as moyenne_versement,
        MIN(montant) as min_versement,
        MAX(montant) as max_versement,
        COUNT(DISTINCT id_etudiant) as nombre_etudiants_payeurs
    FROM paiements
")->fetch(PDO::FETCH_ASSOC);

// Paiements par mois
$paiementsParMois = $bdd->query("
    SELECT 
        DATE_FORMAT(date_paiement, '%Y-%m') as mois,
        COUNT(*) as nb_paiements,
        SUM(montant) as total_mois
    FROM paiements
    GROUP BY DATE_FORMAT(date_paiement, '%Y-%m')
    ORDER BY mois DESC
    LIMIT 12
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Financier — UniVerse</title>
    <style>
    :root {
        --bg-light: #f8fafc;
        --sidebar-color: #0b1329;
        --text-main: #334155;
        --text-muted: #64748b;
        --text-white: #ffffff;
        --border-color: #e2e8f0;
        --card-bg: #ffffff;
        --success: #10b981;
        --danger: #ef4444;
        --warning: #f59e0b;
        --role-recteur-gold: #d4af37;
        --role-recteur-gold-dark: #aa7c11;
        --shadow-sm: 0 1px 3px rgba(0,0,0,0.06);
        --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
        --radius: 12px;
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
    }

    body {
        background-color: var(--bg-light);
        color: var(--text-main);
        display: flex;
        min-height: 100vh;
        line-height: 1.5;
    }

    /* SIDEBAR */
    .sidebar {
        width: 280px;
        background-color: var(--sidebar-color);
        color: var(--text-white);
        padding: 30px 20px;
        position: fixed;
        height: 100vh;
        display: flex;
        flex-direction: column;
        border-right: 2px solid var(--role-recteur-gold);
        box-shadow: 4px 0 10px rgba(0, 0, 0, 0.05);
        z-index: 100;
    }

    .sidebar .logo {
        font-size: 24px;
        font-weight: 800;
        margin-bottom: 25px;
        text-align: center;
    }

    .user-profile {
        background: rgba(255, 255, 255, 0.04);
        padding: 15px;
        border-radius: 12px;
        margin-bottom: 25px;
        text-align: center;
        border: 1px solid rgba(255, 255, 255, 0.08);
    }

    .user-profile .profile-img {
        width: 70px;
        height: 70px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid var(--role-recteur-gold);
        margin-bottom: 10px;
    }

    .user-profile .name {
        font-weight: 600;
        font-size: 15px;
        color: var(--text-white);
    }

    .user-profile .title {
        font-size: 12px;
        color: #94a3b8;
        margin-top: 2px;
    }

    .badge.recteur { 
        display: inline-block;
        padding: 4px 12px;
        font-size: 11px;
        font-weight: 700;
        border-radius: 6px;
        margin-top: 6px;
        background: linear-gradient(135deg, var(--role-recteur-gold), var(--role-recteur-gold-dark));
        color: white;
    }

    .nav-links {
        list-style: none;
        display: flex;
        flex-direction: column;
        gap: 6px;
        height: 100%;
    }

    .nav-links a {
        color: #94a3b8;
        text-decoration: none;
        padding: 12px 16px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        gap: 12px;
        font-weight: 600;
        font-size: 14px;
        transition: all 0.25s ease;
    }

    .nav-links a:hover,
    .nav-links a.active {
        background: rgba(255, 255, 255, 0.08);
        color: var(--text-white);
    }

    .nav-links .logout-container {
        margin-top: auto;
    }

    .nav-links a.logout {
        color: #ef4444;
    }

    /* MAIN CONTENT */
    .main-content {
        margin-left: 280px;
        width: calc(100% - 280px);
        padding: 40px;
        background-color: var(--bg-light);
    }

    header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        border-bottom: 1px solid var(--border-color);
        padding-bottom: 20px;
        flex-wrap: wrap;
        gap: 10px;
    }

    header h1 {
        font-size: 26px;
        color: #1e293b;
        font-weight: 700;
    }

    .info-badge {
        border: 1px solid var(--role-recteur-gold);
        color: var(--role-recteur-gold-dark);
        background-color: #fffbeb;
        padding: 8px 18px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 14px;
    }

    .audit-badge {
        display: inline-block;
        background: #1e293b;
        color: white;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 10px;
    }

    .faculte-badge {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 12px;
        background: #eff6ff;
        color: #1e40af;
        font-weight: 600;
        font-size: 11px;
    }

    /* STATS GRID */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .card {
        background: var(--card-bg);
        padding: 20px;
        border-radius: var(--radius);
        border: 1px solid var(--border-color);
        box-shadow: var(--shadow-sm);
    }

    .card.gold-border {
        border-top: 4px solid var(--role-recteur-gold);
    }

    .card h3 {
        font-size: 12px;
        color: var(--text-muted);
        text-transform: uppercase;
        margin-bottom: 8px;
        letter-spacing: 0.3px;
    }

    .card .value {
        font-size: 22px;
        font-weight: 700;
        color: #1e293b;
    }

    .card .value.success { color: var(--success); }
    .card .value.danger { color: var(--danger); }
    .card .value.warning { color: var(--warning); }

    /* PANELS */
    .panel {
        background: var(--card-bg);
        padding: 25px 30px;
        border-radius: var(--radius);
        border: 1px solid var(--border-color);
        margin-bottom: 30px;
        box-shadow: var(--shadow-sm);
    }

    .panel.audit-highlight {
        border-left: 4px solid var(--role-recteur-gold);
    }

    .panel-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
        flex-wrap: wrap;
        gap: 10px;
    }

    .panel h2 {
        font-size: 18px;
        color: #1e293b;
    }

    .panel .subtitle {
        color: var(--text-muted);
        font-size: 14px;
    }

    /* TABLEAUX */
    .table-responsive {
        overflow-x: auto;
        margin-top: 10px;
    }

    .data-table {
        width: 100%;
        border-collapse: collapse;
        text-align: left;
        font-size: 14px;
    }

    .data-table th {
        background-color: #f1f5f9;
        color: #475569;
        padding: 12px 16px;
        font-weight: 600;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        border-bottom: 2px solid var(--border-color);
    }

    .data-table td {
        padding: 12px 16px;
        border-bottom: 1px solid var(--border-color);
        font-size: 13px;
    }

    .data-table tr:hover td {
        background-color: #f8fafc;
    }

    /* BADGES */
    .badge-action {
        display: inline-block;
        padding: 2px 12px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
    }
    .badge-action.modification { background: #eff6ff; color: #1e40af; }
    .badge-action.suppression { background: #fee2e2; color: #991b1b; }
    .badge-action.ajout { background: #d1fae5; color: #065f46; }
    .badge-action.urgent { background: #fef2f2; color: #dc2626; animation: pulse 2s infinite; }
    
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.6; }
    }

    .empty-state {
        text-align: center;
        color: var(--text-muted);
        padding: 30px;
        font-style: italic;
    }

    /* GRID 2 COLONNES */
    .two-col-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 25px;
        margin-bottom: 30px;
    }

    /* RESPONSIVE */
    @media (max-width: 1024px) {
        .two-col-grid { grid-template-columns: 1fr; }
    }

    @media (max-width: 768px) {
        body { flex-direction: column; }
        .sidebar { width: 100%; height: auto; position: relative; padding: 20px; border-right: none; border-bottom: 2px solid var(--role-recteur-gold); }
        .nav-links { flex-direction: row; flex-wrap: wrap; }
        .main-content { margin-left: 0; width: 100%; padding: 20px; }
        header { flex-direction: column; align-items: flex-start; }
        .stats-grid { grid-template-columns: 1fr 1fr; }
    }

    @media (max-width: 480px) {
        .stats-grid { grid-template-columns: 1fr; }
        .panel { padding: 15px 18px; }
    }

    /* Scrollbar */
    ::-webkit-scrollbar { width: 6px; height: 6px; }
    ::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 10px; }
    ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="logo">👑 UniVerse</div>
        <div class="user-profile">
            <img src="uploads/<?= htmlspecialchars($photo_profil); ?>" alt="Photo" class="profile-img">
            <p class="name">Prof. <?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']); ?></p>
            <p class="title">Audit & Contrôle Financier</p>
            <span class="badge recteur"><?= htmlspecialchars($user['role']); ?></span>
        </div>
        <ul class="nav-links">
            <li><a href="dashboard_recteur.php">🏛️ Tableau de Bord</a></li>
            <li><a href="#" class="active">📊 Audit Financier</a></li>
            <li><a href="?action=download_excel" style="background: rgba(16, 185, 129, 0.1); color: #10b981;">📊 Rapport Excel</a></li>
            <li><a href="admin_notifications.php">🔔 Notifications</a></li>
            <li class="logout-container"><a href="logout.php" class="logout">🚪 Déconnexion</a></li>
        </ul>
    </div>

    <div class="main-content">
        <header>
            <h1>📊 Audit Financier et Contrôle</h1>
            <div class="info-badge">🛡️ Mode Lecture Seule</div>
        </header>

        <!-- STATISTIQUES GLOBALES -->
        <div class="stats-grid">
            <div class="card gold-border">
                <h3>Effectif Global</h3>
                <p class="value"><?= number_format($totalEtudiants, 0, ',', ' '); ?></p>
            </div>
            <div class="card">
                <h3>Départements</h3>
                <p class="value"><?= $totalEtablissements; ?></p>
            </div>
            <div class="card">
                <h3>Personnel Actif</h3>
                <p class="value"><?= number_format($totalPersonnel, 0, ',', ' '); ?></p>
            </div>
            <div class="card">
                <h3>Recettes Totales</h3>
                <p class="value success"><?= number_format($totalRecettes, 0, ',', ' '); ?> BIF</p>
            </div>
            <div class="card">
                <h3>Total Paiements</h3>
                <p class="value"><?= number_format($statsFinancieres['total_paiements'], 0, ',', ' '); ?></p>
            </div>
            <div class="card">
                <h3>Moyenne Versement</h3>
                <p class="value warning"><?= number_format($statsFinancieres['moyenne_versement'], 0, ',', ' '); ?> BIF</p>
            </div>
        </div>

        <!-- RECETTES PAR DÉPARTEMENT AVEC FACULTÉ -->
        <div class="panel audit-highlight">
            <div class="panel-header">
                <h2>📈 Recettes par Département</h2>
                <span class="audit-badge">🛡️ Audit</span>
            </div>
            <p class="subtitle">Détail des encaissements par département avec statistiques avancées.</p>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Département</th>
                            <th>Faculté</th>
                            <th style="text-align: center;">Transactions</th>
                            <th style="text-align: right;">Total Collecté</th>
                            <th style="text-align: right;">Moyenne</th>
                            <th style="text-align: right;">Min</th>
                            <th style="text-align: right;">Max</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($recettesParDept)): ?>
                            <?php foreach ($recettesParDept as $rd): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($rd['nom_dept'] ?? 'Non assigné'); ?></strong></td>
                                    <td><span class="faculte-badge"><?= htmlspecialchars($rd['faculte'] ?? 'Non assignée'); ?></span></td>
                                    <td style="text-align: center;"><?= $rd['transactions']; ?></td>
                                    <td style="text-align: right; font-weight: 700; color: var(--success);"><?= number_format($rd['total_collecte'], 0, ',', ' '); ?> BIF</td>
                                    <td style="text-align: right;"><?= number_format($rd['moyenne_transaction'], 0, ',', ' '); ?> BIF</td>
                                    <td style="text-align: right; color: var(--danger);"><?= number_format($rd['min_transaction'], 0, ',', ' '); ?> BIF</td>
                                    <td style="text-align: right; color: var(--success);"><?= number_format($rd['max_transaction'], 0, ',', ' '); ?> BIF</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="7" class="empty-state">Aucune donnée disponible</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- STATISTIQUES FINANCIÈRES -->
        <div class="two-col-grid">
            <div class="panel">
                <h2>💰 Synthèse Financière</h2>
                <table class="data-table">
                    <tbody>
                        <tr><td>Nombre total de paiements</td><td style="font-weight:700;"><?= number_format($statsFinancieres['total_paiements'], 0, ',', ' '); ?></td></tr>
                        <tr><td>Total encaissé</td><td style="font-weight:700; color:var(--success);"><?= number_format($statsFinancieres['total_encaisse'], 0, ',', ' '); ?> BIF</td></tr>
                        <tr><td>Moyenne par versement</td><td style="font-weight:700;"><?= number_format($statsFinancieres['moyenne_versement'], 0, ',', ' '); ?> BIF</td></tr>
                        <tr><td>Versement minimum</td><td style="font-weight:700; color:var(--danger);"><?= number_format($statsFinancieres['min_versement'], 0, ',', ' '); ?> BIF</td></tr>
                        <tr><td>Versement maximum</td><td style="font-weight:700; color:var(--success);"><?= number_format($statsFinancieres['max_versement'], 0, ',', ' '); ?> BIF</td></tr>
                        <tr><td>Étudiants payeurs</td><td style="font-weight:700;"><?= number_format($statsFinancieres['nombre_etudiants_payeurs'], 0, ',', ' '); ?></td></tr>
                    </tbody>
                </table>
            </div>

            <!-- Paiements par mois -->
            <div class="panel">
                <h2>📅 Paiements par Mois</h2>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr><th>Mois</th><th style="text-align:center;">Nb</th><th style="text-align:right;">Total</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach($paiementsParMois as $pm): ?>
                                <tr>
                                    <td><?= date('M Y', strtotime($pm['mois'] . '-01')); ?></td>
                                    <td style="text-align:center;"><?= $pm['nb_paiements']; ?></td>
                                    <td style="text-align:right; color:var(--success); font-weight:600;"><?= number_format($pm['total_mois'], 0, ',', ' '); ?> BIF</td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if(empty($paiementsParMois)): ?>
                                <tr><td colspan="3" class="empty-state">Aucune donnée</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- REGISTRE DES INSCRIPTIONS AVEC FACULTÉ -->
        <div class="panel">
            <h2>🎓 Registre Supérieur de Suivi des Inscriptions</h2>
            <p>Consultation brute des derniers étudiants associés aux structures académiques.</p>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>N° Matricule</th>
                            <th>Identité Complète</th>
                            <th>Niveau</th>
                            <th>Département</th>
                            <th>Faculté</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($listeInscriptions)): ?>
                            <?php foreach ($listeInscriptions as $ins): ?>
                                <tr>
                                    <td style="font-family: monospace; font-weight: 600; color: #2563eb;"><?= htmlspecialchars($ins['matricule']); ?></td>
                                    <td><strong><?= htmlspecialchars($ins['nom'] . ' ' . $ins['prenom']); ?></strong></td>
                                    <td><?= htmlspecialchars($ins['niveau'] ?? 'N/A'); ?></td>
                                    <td><?= htmlspecialchars($ins['nom_dept'] ?? 'Non assigné'); ?></td>
                                    <td><span class="faculte-badge"><?= htmlspecialchars($ins['faculte'] ?? 'Non assignée'); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center; color: var(--text-muted);">Aucun étudiant enregistré.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- FOOTER AUDIT -->
        <div style="text-align:center; padding:20px; color:var(--text-muted); font-size:13px; border-top:1px solid var(--border-color); margin-top:10px;">
            <p>🔒 Audit en Lecture Seule — Rapport généré le <?= date('d/m/Y à H:i'); ?></p>
            <p style="font-size:12px; margin-top:4px;">Toutes les données sont en temps réel. Aucune modification n'est possible depuis cette interface.</p>
        </div>
    </div>

</body>
</html>