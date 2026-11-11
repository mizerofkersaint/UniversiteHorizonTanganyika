<?php
require_once __DIR__ . '/db.php';
// ============================================================
// dashboardadmin.php — Tableau de bord administrateur
// Redesign cohérent avec la console admin UniVerse
// ============================================================
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

/* ============================================================
   STATISTIQUES GLOBALES
============================================================ */
$stats = $bdd->query("
    SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN statut = 'Actif' THEN 1 ELSE 0 END) AS actifs,
        SUM(CASE WHEN statut = 'Inactif' THEN 1 ELSE 0 END) AS inactifs,
        SUM(CASE WHEN statut = 'Suspendu' THEN 1 ELSE 0 END) AS suspendus
    FROM users
")->fetch(PDO::FETCH_ASSOC);

$nb_notifs = $bdd->query("SELECT COUNT(*) FROM notifications WHERE statut = 'non_lu'")->fetchColumn();

/* ============================================================
   DERNIERS UTILISATEURS
============================================================ */
$derniers_users = $bdd->query("
    SELECT u.nom, u.prenom, u.username, u.role, u.statut, u.date_creation, u.photo
    FROM users u 
    ORDER BY u.date_creation DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

/* ============================================================
   DERNIÈRES NOTIFICATIONS
============================================================ */
$dernieres_notifs = $bdd->query("
    SELECT titre, message, type_evenement, date_creation 
    FROM notifications 
    ORDER BY date_creation DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

/* ============================================================
   RÉPARTITION PAR RÔLE
============================================================ */
$repartition = $bdd->query("
    SELECT role, COUNT(*) AS nb 
    FROM users 
    GROUP BY role 
    ORDER BY nb DESC
")->fetchAll(PDO::FETCH_ASSOC);

$total_users = array_sum(array_column($repartition, 'nb'));

/* ============================================================
   HELPERS : ICÔNES ET COULEURS
============================================================ */
function roleConfig($role) {
    $config = [
        'Administrateur' => ['icon' => 'fa-crown',         'color' => '#ef4444', 'bg' => '#fee2e2'],
        'Recteur'        => ['icon' => 'fa-university',    'color' => '#d97706', 'bg' => '#fef3c7'],
        'Doyen'          => ['icon' => 'fa-user-tie',      'color' => '#8b5cf6', 'bg' => '#ede9fe'],
        'Professeur'     => ['icon' => 'fa-chalkboard-teacher', 'color' => '#2563eb', 'bg' => '#dbeafe'],
        'Comptable'      => ['icon' => 'fa-calculator',    'color' => '#10b981', 'bg' => '#d1fae5'],
        'Agent'          => ['icon' => 'fa-user-check',    'color' => '#f59e0b', 'bg' => '#fef3c7'],
        'Etudiant'       => ['icon' => 'fa-user-graduate', 'color' => '#06b6d4', 'bg' => '#cffafe'],
        'Lecteur'        => ['icon' => 'fa-book-reader',   'color' => '#64748b', 'bg' => '#f1f5f9'],
    ];
    return $config[$role] ?? ['icon' => 'fa-user', 'color' => '#64748b', 'bg' => '#f1f5f9'];
}

function notifConfig($type) {
    $config = [
        'INFO'   => ['icon' => 'fa-info-circle',    'color' => '#2563eb', 'bg' => '#dbeafe'],
        'SUCCES' => ['icon' => 'fa-check-circle',   'color' => '#10b981', 'bg' => '#d1fae5'],
        'ALERT'  => ['icon' => 'fa-exclamation-triangle', 'color' => '#f59e0b', 'bg' => '#fef3c7'],
        'URGENT' => ['icon' => 'fa-exclamation-circle',   'color' => '#ef4444', 'bg' => '#fee2e2'],
    ];
    return $config[$type] ?? $config['INFO'];
}

function timeAgo($datetime) {
    $ts = strtotime($datetime);
    $diff = time() - $ts;
    if ($diff < 60)    return 'à l\'instant';
    if ($diff < 3600)  return 'il y a ' . floor($diff / 60) . ' min';
    if ($diff < 86400) return 'il y a ' . floor($diff / 3600) . ' h';
    if ($diff < 604800) return 'il y a ' . floor($diff / 86400) . ' j';
    return date('d/m/Y', $ts);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord — UniVerse Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/theme.css">
    <script src="assets/theme.js"></script>
    <style>
        /* ============================================================
           VARIABLES (identiques à la console admin)
           ============================================================ */
        :root {
            --bg-body: #f8fafc;
            --bg-card: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --text-light: #94a3b8;
            --text-white: #ffffff;

            --primary: #6366f1;
            --primary-hover: #4f46e5;
            --primary-light: #eef2ff;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --cyan: #06b6d4;

            --border: #e2e8f0;
            --border-light: #f1f5f9;

            --radius-sm: 8px;
            --radius: 12px;
            --radius-lg: 16px;

            --shadow-xs: 0 1px 2px rgba(15, 23, 42, 0.04);
            --shadow-sm: 0 1px 3px rgba(15, 23, 42, 0.06), 0 1px 2px rgba(15, 23, 42, 0.04);
            --shadow-md: 0 4px 6px -1px rgba(15, 23, 42, 0.08), 0 2px 4px -1px rgba(15, 23, 42, 0.04);

            --ease: cubic-bezier(0.4, 0, 0.2, 1);
            --transition: all 0.2s var(--ease);
        }

        /* ============================================================
           RESET / BASE
           ============================================================ */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }

        html {
            height: 100%;
        }

        body {
            min-height: 100vh;
            background: var(--bg-body);
            color: var(--text-main);
            padding: 24px 28px 32px;
            overflow-y: auto;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb {
            background: rgba(148, 163, 184, 0.4);
            border-radius: 10px;
        }
        ::-webkit-scrollbar-thumb:hover { background: rgba(148, 163, 184, 0.7); }

        /* ============================================================
           BANNIÈRE D'ACCUEIL
           ============================================================ */
        .welcome-banner {
            position: relative;
            background: linear-gradient(135deg, #4f46e5 0%, #6366f1 45%, #8b5cf6 100%);
            border-radius: var(--radius-lg);
            padding: 24px 28px;
            color: var(--text-white);
            margin-bottom: 24px;
            overflow: hidden;
            box-shadow: 0 8px 24px rgba(99, 102, 241, 0.25);
        }
        .welcome-banner::before {
            content: '';
            position: absolute;
            top: -40%; right: -10%;
            width: 340px; height: 340px;
            background: radial-gradient(circle, rgba(255,255,255,0.15), transparent 70%);
            border-radius: 50%;
        }
        .welcome-banner::after {
            content: '';
            position: absolute;
            bottom: -60%; left: 20%;
            width: 260px; height: 260px;
            background: radial-gradient(circle, rgba(236, 72, 153, 0.22), transparent 70%);
            border-radius: 50%;
        }

        .welcome-content {
            position: relative;
            z-index: 1;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .welcome-text h1 {
            font-size: 22px;
            font-weight: 700;
            letter-spacing: -0.3px;
            margin-bottom: 4px;
        }
        .welcome-text p {
            font-size: 13px;
            color: rgba(255,255,255,0.85);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .welcome-text p i {
            font-size: 11px;
            opacity: 0.8;
        }

        .welcome-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .welcome-btn {
            padding: 9px 16px;
            border-radius: 8px;
            font-size: 12.5px;
            font-weight: 600;
            text-decoration: none;
            color: var(--text-white);
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.2);
            display: inline-flex;
            align-items: center;
            gap: 7px;
            transition: var(--transition);
            backdrop-filter: blur(4px);
        }
        .welcome-btn:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-1px);
        }

        /* ============================================================
           STAT CARDS
           ============================================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 18px 20px;
            box-shadow: var(--shadow-xs);
            position: relative;
            overflow: hidden;
            transition: var(--transition);
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
            border-color: transparent;
        }
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            border-radius: var(--radius) var(--radius) 0 0;
        }

        .stat-card.primary::before { background: var(--primary); }
        .stat-card.success::before { background: var(--success); }
        .stat-card.warning::before { background: var(--warning); }
        .stat-card.danger::before  { background: var(--danger); }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }
        .stat-label {
            font-size: 11px;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 11px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            color: var(--text-white);
            flex-shrink: 0;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .stat-card.primary .stat-icon { background: linear-gradient(135deg, #6366f1, #8b5cf6); box-shadow: 0 4px 12px rgba(99, 102, 241, 0.35); }
        .stat-card.success .stat-icon { background: linear-gradient(135deg, #10b981, #059669); box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3); }
        .stat-card.warning .stat-icon { background: linear-gradient(135deg, #f59e0b, #d97706); box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3); }
        .stat-card.danger  .stat-icon { background: linear-gradient(135deg, #ef4444, #dc2626); box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3); }

        .stat-value {
            font-size: 28px;
            font-weight: 800;
            color: var(--text-main);
            line-height: 1.1;
            letter-spacing: -0.5px;
        }
        .stat-sub {
            font-size: 11.5px;
            color: var(--text-muted);
            margin-top: 4px;
            font-weight: 500;
        }

        /* ============================================================
           CONTENT GRID
           ============================================================ */
        .content-grid {
            display: grid;
            grid-template-columns: 1.4fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .panel {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 22px 24px;
            box-shadow: var(--shadow-xs);
            display: flex;
            flex-direction: column;
        }

        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            padding-bottom: 14px;
            border-bottom: 1px solid var(--border-light);
        }
        .panel-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 15px;
            font-weight: 700;
            color: var(--text-main);
        }
        .panel-title-icon {
            width: 32px;
            height: 32px;
            border-radius: 9px;
            background: var(--primary-light);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }
        .panel-link {
            font-size: 12px;
            font-weight: 600;
            color: var(--primary);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: var(--transition);
        }
        .panel-link:hover {
            color: var(--primary-hover);
            gap: 8px;
        }

        /* ============================================================
           LISTE UTILISATEURS
           ============================================================ */
        .user-list {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .user-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 8px;
            border-radius: var(--radius-sm);
            transition: var(--transition);
        }
        .user-item:hover {
            background: var(--bg-body);
        }

        .user-avatar {
            position: relative;
            width: 38px;
            height: 38px;
            border-radius: 50%;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 700;
            color: var(--text-white);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .user-avatar .status-dot {
            position: absolute;
            bottom: -1px;
            right: -1px;
            width: 11px;
            height: 11px;
            border-radius: 50%;
            border: 2px solid var(--bg-card);
        }
        .status-dot.actif     { background: var(--success); }
        .status-dot.inactif   { background: var(--text-light); }
        .status-dot.suspendu  { background: var(--danger); }

        .user-info {
            flex: 1;
            min-width: 0;
        }
        .user-name {
            font-size: 13.5px;
            font-weight: 600;
            color: var(--text-main);
            line-height: 1.3;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .user-meta {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: 3px;
            font-size: 11.5px;
            color: var(--text-muted);
        }
        .user-role-badge {
            font-size: 10.5px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 8px;
            display: inline-block;
        }

        .user-time {
            font-size: 11px;
            color: var(--text-light);
            font-weight: 500;
            flex-shrink: 0;
        }

        /* ============================================================
           LISTE NOTIFICATIONS
           ============================================================ */
        .notif-list {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .notif-item {
            display: flex;
            gap: 12px;
            padding: 11px 8px;
            border-radius: var(--radius-sm);
            transition: var(--transition);
        }
        .notif-item:hover {
            background: var(--bg-body);
        }

        .notif-icon {
            width: 34px;
            height: 34px;
            border-radius: 9px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
        }

        .notif-content {
            flex: 1;
            min-width: 0;
        }
        .notif-title {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-main);
            line-height: 1.35;
            display: -webkit-box;
            -webkit-line-clamp: 1;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .notif-message {
            font-size: 11.5px;
            color: var(--text-muted);
            line-height: 1.4;
            margin-top: 2px;
            display: -webkit-box;
            -webkit-line-clamp: 1;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .notif-time {
            font-size: 10.5px;
            color: var(--text-light);
            margin-top: 4px;
            font-weight: 500;
        }

        /* ============================================================
           RÉPARTITION PAR RÔLE
           ============================================================ */
        .role-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .role-item {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .role-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .role-name {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-main);
        }
        .role-icon-sm {
            width: 24px;
            height: 24px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
        }
        .role-count {
            font-size: 12px;
            font-weight: 700;
            color: var(--text-muted);
        }
        .role-count strong {
            color: var(--text-main);
            font-size: 13px;
        }

        .role-bar {
            height: 6px;
            background: var(--border-light);
            border-radius: 4px;
            overflow: hidden;
        }
        .role-bar-fill {
            height: 100%;
            border-radius: 4px;
            transition: width 1s var(--ease);
        }

        /* ============================================================
           EMPTY STATE
           ============================================================ */
        .empty {
            text-align: center;
            padding: 32px 16px;
            color: var(--text-muted);
            font-size: 13px;
        }
        .empty i {
            font-size: 32px;
            opacity: 0.3;
            display: block;
            margin-bottom: 8px;
        }

        /* ============================================================
           RESPONSIVE
           ============================================================ */
        @media (max-width: 900px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 600px) {
            body { padding: 16px; }
            .welcome-banner { padding: 20px; }
            .welcome-text h1 { font-size: 18px; }
            .welcome-content { flex-direction: column; align-items: flex-start; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .stat-value { font-size: 22px; }
            .panel { padding: 18px; }
        }

        @media (max-width: 400px) {
            .stats-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <!-- ============================================================
         BANNIÈRE D'ACCUEIL
    ============================================================ -->
    <div class="welcome-banner">
        <div class="welcome-content">
            <div class="welcome-text">
                <h1>👋 Bonjour, <?= htmlspecialchars($USER_PRENOM ?: 'Administrateur'); ?> !</h1>
                <p>
                    <i class="far fa-calendar-alt"></i> <?= date('l d F Y') ?>
                    <span style="opacity:0.6; margin:0 4px;">•</span>
                    Vue d'ensemble du système UniVerse
                </p>
            </div>
            <div class="welcome-actions">
                <a href="ajouteuser.php?iframe=1" target="admin-frame" class="welcome-btn">
                    <i class="fas fa-user-plus"></i> Nouvel utilisateur
                </a>
                <a href="admin_notifications.php?iframe=1" target="admin-frame" class="welcome-btn">
                    <i class="fas fa-bell"></i> Notifications
                </a>
            </div>
        </div>
    </div>

    <!-- ============================================================
         STATISTIQUES
    ============================================================ -->
    <div class="stats-grid">
        <!-- Total utilisateurs -->
        <div class="stat-card primary">
            <div class="stat-header">
                <span class="stat-label">Total utilisateurs</span>
                <div class="stat-icon"><i class="fas fa-users"></i></div>
            </div>
            <div class="stat-value"><?= number_format($stats['total']); ?></div>
            <div class="stat-sub">Comptes dans le système</div>
        </div>

        <!-- Actifs -->
        <div class="stat-card success">
            <div class="stat-header">
                <span class="stat-label">Comptes actifs</span>
                <div class="stat-icon"><i class="fas fa-user-check"></i></div>
            </div>
            <div class="stat-value"><?= number_format($stats['actifs']); ?></div>
            <div class="stat-sub">
                <?php
                $pct_actifs = $stats['total'] > 0 ? round(($stats['actifs'] / $stats['total']) * 100) : 0;
                ?>
                <?= $pct_actifs; ?>% des utilisateurs
            </div>
        </div>

        <!-- Inactifs -->
        <div class="stat-card warning">
            <div class="stat-header">
                <span class="stat-label">Comptes inactifs</span>
                <div class="stat-icon"><i class="fas fa-user-clock"></i></div>
            </div>
            <div class="stat-value"><?= number_format($stats['inactifs']); ?></div>
            <div class="stat-sub">En attente d'activation</div>
        </div>

        <!-- Notifications -->
        <div class="stat-card danger">
            <div class="stat-header">
                <span class="stat-label">Notifications non lues</span>
                <div class="stat-icon"><i class="fas fa-bell"></i></div>
            </div>
            <div class="stat-value"><?= number_format($nb_notifs); ?></div>
            <div class="stat-sub">À traiter</div>
        </div>
    </div>

    <!-- ============================================================
         ACTIVITÉ RÉCENTE
    ============================================================ -->
    <div class="content-grid">

        <!-- Derniers utilisateurs -->
        <div class="panel">
            <div class="panel-header">
                <div class="panel-title">
                    <span class="panel-title-icon"><i class="fas fa-user-plus"></i></span>
                    Derniers utilisateurs
                </div>
                <a href="afficheuser.php?iframe=1" target="admin-frame" class="panel-link">
                    Voir tout <i class="fas fa-arrow-right"></i>
                </a>
            </div>

            <?php if (!empty($derniers_users)): ?>
                <div class="user-list">
                    <?php foreach ($derniers_users as $u):
                        $rc = roleConfig($u['role']);
                        $initials = strtoupper(substr($u['prenom'], 0, 1) . substr($u['nom'], 0, 1));
                        $statut_class = strtolower($u['statut']); // actif / inactif / suspendu
                    ?>
                        <div class="user-item">
                            <div class="user-avatar" style="background: linear-gradient(135deg, <?= $rc['color'] ?>, <?= $rc['color'] ?>cc);">
                                <?= htmlspecialchars($initials); ?>
                                <span class="status-dot <?= $statut_class; ?>"></span>
                            </div>
                            <div class="user-info">
                                <div class="user-name"><?= htmlspecialchars($u['prenom'] . ' ' . $u['nom']); ?></div>
                                <div class="user-meta">
                                    <span class="user-role-badge" style="background:<?= $rc['bg'] ?>; color:<?= $rc['color'] ?>;">
                                        <i class="fas <?= $rc['icon'] ?>" style="font-size:9px; margin-right:3px;"></i>
                                        <?= htmlspecialchars($u['role']); ?>
                                    </span>
                                    <span>@<?= htmlspecialchars($u['username']); ?></span>
                                </div>
                            </div>
                            <div class="user-time"><?= timeAgo($u['date_creation']); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty">
                    <i class="fas fa-users"></i>
                    Aucun utilisateur enregistré
                </div>
            <?php endif; ?>
        </div>

        <!-- Dernières notifications -->
        <div class="panel">
            <div class="panel-header">
                <div class="panel-title">
                    <span class="panel-title-icon"><i class="fas fa-bell"></i></span>
                    Dernières notifications
                </div>
                <a href="admin_notifications.php?iframe=1" target="admin-frame" class="panel-link">
                    Voir tout <i class="fas fa-arrow-right"></i>
                </a>
            </div>

            <?php if (!empty($dernieres_notifs)): ?>
                <div class="notif-list">
                    <?php foreach ($dernieres_notifs as $n):
                        $nc = notifConfig($n['type_evenement']);
                    ?>
                        <div class="notif-item">
                            <div class="notif-icon" style="background:<?= $nc['bg'] ?>; color:<?= $nc['color'] ?>;">
                                <i class="fas <?= $nc['icon'] ?>"></i>
                            </div>
                            <div class="notif-content">
                                <div class="notif-title"><?= htmlspecialchars($n['titre']); ?></div>
                                <?php if (!empty($n['message'])): ?>
                                    <div class="notif-message"><?= htmlspecialchars($n['message']); ?></div>
                                <?php endif; ?>
                                <div class="notif-time"><?= timeAgo($n['date_creation']); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty">
                    <i class="fas fa-bell-slash"></i>
                    Aucune notification
                </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- ============================================================
         RÉPARTITION PAR RÔLE
    ============================================================ -->
    <div class="panel">
        <div class="panel-header">
            <div class="panel-title">
                <span class="panel-title-icon"><i class="fas fa-chart-pie"></i></span>
                Répartition par rôle
            </div>
            <span style="font-size:12px; font-weight:600; color:var(--text-muted);">
                <?= number_format($total_users); ?> utilisateur<?= $total_users > 1 ? 's' : ''; ?>
            </span>
        </div>

        <?php if (!empty($repartition)): ?>
            <div class="role-list">
                <?php foreach ($repartition as $r):
                    $rc = roleConfig($r['role']);
                    $pct = $total_users > 0 ? round(($r['nb'] / $total_users) * 100, 1) : 0;
                ?>
                    <div class="role-item">
                        <div class="role-header">
                            <div class="role-name">
                                <span class="role-icon-sm" style="background:<?= $rc['bg'] ?>; color:<?= $rc['color'] ?>;">
                                    <i class="fas <?= $rc['icon'] ?>"></i>
                                </span>
                                <?= htmlspecialchars($r['role']); ?>
                            </div>
                            <div class="role-count">
                                <strong><?= (int)$r['nb']; ?></strong>
                                <span style="font-size:11px; color:var(--text-light); font-weight:500;"> · <?= $pct; ?>%</span>
                            </div>
                        </div>
                        <div class="role-bar">
                            <div class="role-bar-fill" style="width:<?= $pct; ?>%; background:linear-gradient(90deg, <?= $rc['color'] ?>, <?= $rc['color'] ?>99);"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty">
                <i class="fas fa-chart-pie"></i>
                Aucune donnée disponible
            </div>
        <?php endif; ?>
    </div>

</body>
</html>