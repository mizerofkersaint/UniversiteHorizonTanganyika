<?php
require_once __DIR__ . '/db.php';
require_once 'auth.php';
requireRole('Agent');

try {
    $bdd = getDBConnection();
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Erreur : " . $e->getMessage());
}

/* ============================================================
   UTILISATEUR CONNECTÉ
============================================================ */
$stmtUser = $bdd->prepare("SELECT nom, prenom, photo FROM users WHERE id_user = ?");
$stmtUser->execute([$USER_ID]);
$user = $stmtUser->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    $user = ['nom' => '', 'prenom' => '', 'photo' => 'default.png'];
}

$photo_profil = (!empty($user['photo']) && file_exists("uploads/" . $user['photo']))
    ? $user['photo']
    : 'default.png';

/* ============================================================
   STATISTIQUES
============================================================ */
$totalEtudiants     = $bdd->query("SELECT COUNT(*) FROM etudiants")->fetchColumn() ?: 0;
$totalInscriptions  = $bdd->query("SELECT COUNT(*) FROM inscriptions")->fetchColumn() ?: 0;
$inscriptionsMois   = $bdd->query("SELECT COUNT(*) FROM inscriptions WHERE MONTH(date_inscription) = MONTH(CURDATE()) AND YEAR(date_inscription) = YEAR(CURDATE())")->fetchColumn() ?: 0;
$inscriptionsJour   = $bdd->query("SELECT COUNT(*) FROM inscriptions WHERE DATE(date_inscription) = CURDATE()")->fetchColumn() ?: 0;

/* ============================================================
   DERNIÈRES INSCRIPTIONS
============================================================ */
$stmt = $bdd->query("
    SELECT i.date_inscription, u.nom, u.prenom, e.matricule, c.niveau, d.nom_dept
    FROM inscriptions i
    JOIN etudiants e ON i.id_etudiant = e.id_etudiant
    JOIN users u ON e.id_user = u.id_user
    JOIN classes c ON e.id_classe = c.id_classe
    JOIN departements d ON c.id_dept = d.id_dept
    ORDER BY i.date_inscription DESC
    LIMIT 5
");
$dernieres_inscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ============================================================
   HEURE DU JOUR POUR LE SALUT
============================================================ */
$heure = (int)date('H');
if ($heure < 12)      $salutation = 'Bonjour';
elseif ($heure < 18)  $salutation = 'Bon après-midi';
else                  $salutation = 'Bonsoir';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Agent — UniVerse</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/theme.css">
    <script src="assets/theme.js"></script>
    <style>
        /* ================================================================
           VARIABLES
           ================================================================ */
        :root {
            --bg-primary: #f1f5f9;
            --bg-sidebar: #0f172a;
            --bg-card: #ffffff;
            --text-primary: #1e293b;
            --text-secondary: #475569;
            --text-muted: #64748b;
            --text-light: #94a3b8;
            --text-white: #ffffff;
            --accent-blue: #2563eb;
            --accent-blue-dark: #1d4ed8;
            --accent-success: #10b981;
            --accent-success-dark: #059669;
            --accent-warning: #f59e0b;
            --accent-warning-dark: #d97706;
            --accent-danger: #ef4444;
            --accent-danger-dark: #dc2626;
            --accent-purple: #8b5cf6;
            --accent-pink: #ec4899;
            --border-color: #e2e8f0;
            --border-light: #f1f5f9;
            --shadow-xs: 0 1px 2px rgba(0,0,0,0.04);
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
            --radius-sm: 10px;
            --radius: 14px;
            --radius-lg: 18px;
            --transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; }
        body {
            background: var(--bg-primary);
            color: var(--text-primary);
            display: flex;
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
        }

        /* ================================================================
           SIDEBAR
           ================================================================ */
        .sidebar {
            width: 270px;
            background: linear-gradient(180deg, #0f172a 0%, #1e293b 100%);
            color: var(--text-white);
            padding: 24px 16px;
            position: fixed;
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            z-index: 100;
            border-right: 1px solid rgba(255,255,255,0.04);
        }
        .sidebar::-webkit-scrollbar { width: 4px; }
        .sidebar::-webkit-scrollbar-thumb { background: #334155; border-radius: 2px; }

        .sidebar .logo {
            font-size: 22px;
            font-weight: 800;
            margin-bottom: 22px;
            padding: 0 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .sidebar .logo span {
            background: linear-gradient(135deg, #34d399, #10b981);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .user-profile {
            background: rgba(16,185,129,0.06);
            padding: 16px 14px;
            border-radius: var(--radius-sm);
            margin-bottom: 22px;
            text-align: center;
            border: 1px solid rgba(16,185,129,0.15);
        }
        .profile-img {
            width: 66px; height: 66px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 10px;
            border: 3px solid var(--accent-success);
            box-shadow: 0 4px 14px rgba(16,185,129,0.35);
        }
        .user-profile .name { font-weight: 600; font-size: 14px; color: var(--text-white); }
        .user-profile .title { font-size: 11px; color: var(--text-light); margin-top: 2px; }
        .badge-role {
            display: inline-block;
            padding: 4px 12px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            border-radius: 20px;
            margin-top: 8px;
            background: linear-gradient(135deg, var(--accent-success), var(--accent-success-dark));
            color: white;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 12px rgba(16,185,129,0.3);
        }

        .nav-links { list-style: none; display: flex; flex-direction: column; gap: 2px; flex: 1; }
        .nav-links a {
            color: var(--text-light);
            text-decoration: none;
            padding: 10px 14px;
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
            font-size: 13px;
            transition: var(--transition);
        }
        .nav-links a:hover { background: rgba(255,255,255,0.06); color: var(--text-white); }
        .nav-links a.active {
            background: linear-gradient(135deg, rgba(16,185,129,0.2), rgba(16,185,129,0.08));
            color: var(--text-white);
            box-shadow: inset 3px 0 0 var(--accent-success);
        }
        .nav-links a i { width: 20px; text-align: center; font-size: 13px; }
        .nav-links .logout {
            color: var(--accent-danger);
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid rgba(255,255,255,0.06);
        }
        .nav-links .logout:hover { background: rgba(239,68,68,0.12); }

        /* ================================================================
           MAIN CONTENT
           ================================================================ */
        .main-content {
            margin-left: 270px;
            width: calc(100% - 270px);
            padding: 28px 32px;
            min-height: 100vh;
        }

        header.page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 26px;
            padding-bottom: 18px;
            border-bottom: 1px solid var(--border-color);
            flex-wrap: wrap;
            gap: 12px;
        }
        header.page-header h1 {
            font-size: 26px;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        header.page-header h1 i { color: var(--accent-success); font-size: 22px; }
        .header-actions { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }

        /* ================================================================
           BOUTON THÈME
           ================================================================ */
        .theme-toggle-btn {
            background: white;
            border: 1px solid var(--border-color);
            width: 40px; height: 40px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 16px;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .theme-toggle-btn:hover { background: var(--bg-primary); transform: rotate(15deg); }

        /* ================================================================
           HERO BANNER
           ================================================================ */
        .hero-banner {
            background: linear-gradient(135deg, #065f46 0%, #10b981 60%, #34d399 100%);
            color: white;
            padding: 26px 30px;
            border-radius: var(--radius-lg);
            margin-bottom: 26px;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-md);
        }
        .hero-banner::before {
            content: '';
            position: absolute;
            top: -50%; right: -10%;
            width: 340px; height: 340px;
            background: radial-gradient(circle, rgba(255,255,255,0.12), transparent 70%);
            border-radius: 50%;
        }
        .hero-banner::after {
            content: '';
            position: absolute;
            bottom: -60%; left: 15%;
            width: 280px; height: 280px;
            background: radial-gradient(circle, rgba(37,99,235,0.25), transparent 70%);
            border-radius: 50%;
        }
        .hero-banner .hero-content {
            position: relative;
            z-index: 1;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }
        .hero-banner h2 {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .hero-banner p {
            font-size: 14px;
            opacity: 0.9;
        }
        .hero-banner .hero-actions { display: flex; gap: 8px; flex-wrap: wrap; }

        .hero-banner .hero-actions a {
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(8px);
            color: white;
            text-decoration: none;
            padding: 10px 18px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 1px solid rgba(255,255,255,0.2);
            transition: var(--transition);
        }
        .hero-banner .hero-actions a:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
        }

        /* ================================================================
           STAT CARDS
           ================================================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 26px;
        }
        .stat-card {
            background: var(--bg-card);
            padding: 20px;
            border-radius: var(--radius);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            opacity: 0.85;
        }
        .stat-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); border-color: transparent; }

        .stat-card .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }
        .stat-card .stat-label {
            font-size: 11px;
            color: var(--text-muted);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .stat-card .stat-icon {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: white;
            box-shadow: 0 4px 10px rgba(0,0,0,0.15);
        }
        .stat-card .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.15;
        }
        .stat-card .stat-sub {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 4px;
            font-weight: 500;
        }

        .stat-card.blue::before   { background: var(--accent-blue); }
        .stat-card.blue   .stat-icon { background: linear-gradient(135deg, #3b82f6, #2563eb); }
        .stat-card.green::before  { background: var(--accent-success); }
        .stat-card.green  .stat-icon { background: linear-gradient(135deg, #34d399, #10b981); }
        .stat-card.orange::before { background: var(--accent-warning); }
        .stat-card.orange .stat-icon { background: linear-gradient(135deg, #fbbf24, #f59e0b); }
        .stat-card.purple::before { background: var(--accent-purple); }
        .stat-card.purple .stat-icon { background: linear-gradient(135deg, #a78bfa, #8b5cf6); }

        /* ================================================================
           PANELS
           ================================================================ */
        .panel {
            background: var(--bg-card);
            padding: 22px 26px;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            margin-bottom: 22px;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
        }
        .panel:hover { box-shadow: var(--shadow-md); }
        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
            flex-wrap: wrap;
            gap: 10px;
            padding-bottom: 14px;
            border-bottom: 1px solid var(--border-light);
        }
        .panel-header h2 {
            font-size: 17px;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* ================================================================
           QUICK ACTIONS
           ================================================================ */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 14px;
        }
        .action-btn {
            background: #f8fafc;
            padding: 18px 20px;
            border-radius: var(--radius);
            text-decoration: none;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 14px;
            transition: var(--transition);
            border: 1.5px solid var(--border-color);
            position: relative;
            overflow: hidden;
        }
        .action-btn:hover {
            background: #f0fdf4;
            border-color: var(--accent-success);
            transform: translateY(-3px);
            box-shadow: 0 6px 16px rgba(16,185,129,0.15);
        }
        .action-btn .icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            flex-shrink: 0;
        }
        .action-btn .text strong {
            display: block;
            font-size: 14px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 2px;
        }
        .action-btn .text small {
            color: var(--text-muted);
            font-size: 12px;
        }

        /* ================================================================
           TABLES
           ================================================================ */
        .table-responsive { overflow-x: auto; border-radius: var(--radius-sm); }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th {
            background: #f8fafc;
            padding: 12px 14px;
            color: var(--text-muted);
            font-weight: 600;
            border-bottom: 2px solid var(--border-color);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            text-align: left;
            white-space: nowrap;
        }
        td {
            padding: 12px 14px;
            border-bottom: 1px solid var(--border-light);
            vertical-align: middle;
            font-size: 13px;
        }
        tr { transition: var(--transition); }
        tr:hover td { background: #fafcff; }
        tr:last-child td { border-bottom: none; }

        .faculte-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            background: #eff6ff;
            color: #1e40af;
            font-weight: 600;
            font-size: 11px;
        }

        .matricule-code {
            font-family: monospace;
            font-size: 12px;
            background: #f1f5f9;
            padding: 3px 8px;
            border-radius: 4px;
        }

        /* ================================================================
           EMPTY STATE
           ================================================================ */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-muted);
            font-size: 14px;
        }
        .empty-state i {
            font-size: 42px;
            margin-bottom: 12px;
            display: block;
            opacity: 0.3;
        }
        .empty-state p { margin-top: 6px; font-size: 13px; }

        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 992px) {
            .quick-actions { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 768px) {
            body { flex-direction: column; }
            .sidebar { width: 100%; height: auto; position: relative; padding: 18px; }
            .main-content { margin-left: 0; width: 100%; padding: 16px; }
            header.page-header { flex-direction: column; align-items: flex-start; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .hero-banner .hero-content { flex-direction: column; align-items: flex-start; }
        }
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
            .quick-actions { grid-template-columns: 1fr; }
            .panel { padding: 18px; }
        }
    </style>
</head>
<body>

    <!-- ================================================================
         SIDEBAR
    ================================================================ -->
    <aside class="sidebar">
        <div class="logo">🎓 <span>UniVerse</span></div>

        <div class="user-profile">
            <img src="uploads/<?= htmlspecialchars($photo_profil); ?>" alt="Photo" class="profile-img">
            <p class="name"><?= htmlspecialchars(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? '')); ?></p>
            <p class="title">Agent d'inscription</p>
            <span class="badge-role">📝 AGENT</span>
        </div>

        <ul class="nav-links">
            <li><a href="dashboard_agent.php" class="active"><i class="fas fa-chart-line"></i> Tableau de bord</a></li>
            <li><a href="ajouteetudiant.php"><i class="fas fa-user-plus"></i> Nouvel étudiant</a></li>
            <li><a href="AffichageEtudiants.php"><i class="fas fa-users"></i> Liste des étudiants</a></li>
            <li><a href="ajouteinscription.php"><i class="fas fa-clipboard-list"></i> Nouvelle inscription</a></li>
            <li><a href="afficheinscription.php"><i class="fas fa-list"></i> Liste des inscriptions</a></li>
            <li><a href="recherche_globale.php"><i class="fas fa-search"></i> Recherche</a></li>
            <li><a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
        </ul>
    </aside>

    <!-- ================================================================
         MAIN CONTENT
    ================================================================ -->
    <main class="main-content">

        <header class="page-header">
            <h1><i class="fas fa-user-tie"></i> Espace Agent d'Inscription</h1>
            <div class="header-actions">
                <button class="theme-toggle-btn" onclick="toggleTheme()" title="Mode sombre">🌙</button>
            </div>
        </header>

        <!-- Hero banner -->
        <div class="hero-banner">
            <div class="hero-content">
                <div>
                    <h2>👋 <?= $salutation ?>, <?= htmlspecialchars($user['prenom'] ?? ''); ?> !</h2>
                    <p>📅 <?= date('l d F Y') ?> — Gestion des inscriptions et des étudiants</p>
                </div>
                <div class="hero-actions">
                    <a href="ajouteetudiant.php"><i class="fas fa-user-plus"></i> Nouvel étudiant</a>
                    <a href="ajouteinscription.php"><i class="fas fa-clipboard-list"></i> Nouvelle inscription</a>
                </div>
            </div>
        </div>

        <!-- Statistiques -->
        <div class="stats-grid">
            <div class="stat-card blue">
                <div class="stat-header">
                    <span class="stat-label">Total étudiants</span>
                    <span class="stat-icon"><i class="fas fa-user-graduate"></i></span>
                </div>
                <div class="stat-value"><?= number_format($totalEtudiants); ?></div>
                <div class="stat-sub">Tous départements confondus</div>
            </div>

            <div class="stat-card green">
                <div class="stat-header">
                    <span class="stat-label">Total inscriptions</span>
                    <span class="stat-icon"><i class="fas fa-clipboard-check"></i></span>
                </div>
                <div class="stat-value"><?= number_format($totalInscriptions); ?></div>
                <div class="stat-sub">Depuis le début</div>
            </div>

            <div class="stat-card orange">
                <div class="stat-header">
                    <span class="stat-label">Ce mois-ci</span>
                    <span class="stat-icon"><i class="fas fa-calendar-alt"></i></span>
                </div>
                <div class="stat-value"><?= number_format($inscriptionsMois); ?></div>
                <div class="stat-sub">Inscriptions du mois</div>
            </div>

            <div class="stat-card purple">
                <div class="stat-header">
                    <span class="stat-label">Aujourd'hui</span>
                    <span class="stat-icon"><i class="fas fa-calendar-day"></i></span>
                </div>
                <div class="stat-value"><?= number_format($inscriptionsJour); ?></div>
                <div class="stat-sub">Inscriptions du jour</div>
            </div>
        </div>

        <!-- Actions rapides -->
        <div class="panel">
            <div class="panel-header">
                <h2><i class="fas fa-bolt" style="color:var(--accent-warning);"></i> Actions rapides</h2>
            </div>
            <div class="quick-actions">
                <a href="ajouteetudiant.php" class="action-btn">
                    <span class="icon">🎓</span>
                    <div class="text">
                        <strong>Enregistrer un étudiant</strong>
                        <small>Ajouter un nouveau compte</small>
                    </div>
                </a>
                <a href="ajouteinscription.php" class="action-btn">
                    <span class="icon">📋</span>
                    <div class="text">
                        <strong>Inscrire un étudiant</strong>
                        <small>Créer une inscription</small>
                    </div>
                </a>
                <a href="AffichageEtudiants.php" class="action-btn">
                    <span class="icon">👥</span>
                    <div class="text">
                        <strong>Liste des étudiants</strong>
                        <small>Voir et modifier</small>
                    </div>
                </a>
                <a href="afficheinscription.php" class="action-btn">
                    <span class="icon">📊</span>
                    <div class="text">
                        <strong>Liste des inscriptions</strong>
                        <small>Consulter l'historique</small>
                    </div>
                </a>
            </div>
        </div>

        <!-- Dernières inscriptions -->
        <div class="panel">
            <div class="panel-header">
                <h2><i class="fas fa-history" style="color:var(--accent-success);"></i> Dernières inscriptions</h2>
                <span style="font-size:13px; color:var(--text-muted);"><?= count($dernieres_inscriptions); ?> récente(s)</span>
            </div>

            <?php if (count($dernieres_inscriptions) > 0): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Étudiant</th>
                                <th>Matricule</th>
                                <th>Classe</th>
                                <th>Département</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dernieres_inscriptions as $i): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($i['prenom'] . ' ' . $i['nom']); ?></strong></td>
                                    <td><span class="matricule-code"><?= htmlspecialchars($i['matricule'] ?? 'N/A'); ?></span></td>
                                    <td><span class="faculte-badge"><?= htmlspecialchars($i['niveau']); ?></span></td>
                                    <td><?= htmlspecialchars($i['nom_dept']); ?></td>
                                    <td><?= date('d/m/Y', strtotime($i['date_inscription'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>Aucune inscription enregistrée pour le moment.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Footer -->
        <div style="text-align:center; padding:20px 0 10px; color:var(--text-light); font-size:12px; border-top:1px solid var(--border-color); margin-top:10px;">
            <p>© <?= date('Y'); ?> UniVerse — Espace Agent d'Inscription</p>
        </div>

    </main>

</body>
</html>