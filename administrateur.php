<?php
require_once __DIR__ . '/db.php';
// ============================================================
// administrateur.php — Console d'administration UniVerse
// Refonte professionnelle (design uniquement, fonctionnalités intactes)
// ============================================================


require_once 'auth.php';
requireRole('Administrateur');

$id_user = $USER_ID;

try {
    $bddMenu = getDBConnection();
    $bddMenu->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $countUsers = $bddMenu->query("SELECT COUNT(*) FROM users")->fetchColumn();
} catch (Exception $e) {
    $countUsers = 0;
}

// 2. LOGIQUE AJAX POUR LES NOTIFICATIONS
if (isset($_GET['action']) && $_GET['action'] === 'get_notifs') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $query_count = $bddMenu->query("SELECT COUNT(*) FROM notifications WHERE statut = 'non_lu'");
        $notif_count = $query_count->fetchColumn();

        $query_list = $bddMenu->query("SELECT titre, message FROM notifications WHERE statut = 'non_lu' ORDER BY date_creation DESC LIMIT 3");
        $list_notifications = $query_list->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'count' => (int)$notif_count,
            'notifications' => $list_notifications
        ]);
    } catch(Exception $e) {
        echo json_encode(['count' => 0, 'notifications' => [], 'error' => $e->getMessage()]);
    }
    exit(); 
}

// 3. CHARGEMENT INITIAL DES DONNÉES
try {
    $notif_count = $bddMenu->query("SELECT COUNT(*) FROM notifications WHERE statut = 'non_lu'")->fetchColumn();
    $list_notifications = $bddMenu->query("SELECT titre, message FROM notifications WHERE statut = 'non_lu' ORDER BY date_creation DESC LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {
    $notif_count = 0;
    $list_notifications = [];
}

$reqAdmin = $bddMenu->prepare("SELECT nom, prenom, photo FROM users WHERE id_user = ?");
$reqAdmin->execute([$id_user]);
$admin = $reqAdmin->fetch(PDO::FETCH_ASSOC);

$admin_name = ($admin['prenom'] ?? '') . ' ' . ($admin['nom'] ?? '');
$admin_initials = '';
if (!empty($admin['nom']) && !empty($admin['prenom'])) {
    $admin_initials = strtoupper(substr($admin['prenom'], 0, 1) . substr($admin['nom'], 0, 1));
} else {
    $admin_initials = 'AD';
}
// Photo de profil (fallback ui-avatars avec la couleur d'accent indigo)
$admin_photo = (!empty($admin['photo']) && file_exists("uploads/" . $admin['photo'])) ? "uploads/" . $admin['photo'] : 'https://ui-avatars.com/api/?name='.urlencode($admin_name ?: 'Admin').'&background=6366f1&color=fff';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Console d'Administration — UniVerse</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/theme.css">
    <script src="assets/theme.js"></script>
    <style>
        /* ============================================================
           VARIABLES
           ============================================================ */
        :root {
            /* Couleurs base */
            --bg-body: #f8fafc;
            --bg-sidebar: #0f172a;
            --bg-sidebar-light: #1e293b;
            --bg-card: #ffffff;

            /* Texte */
            --text-main: #0f172a;
            --text-muted: #64748b;
            --text-light: #94a3b8;
            --text-white: #ffffff;
            --text-sidebar: #cbd5e1;
            --text-sidebar-muted: #64748b;

            /* Accents */
            --primary: #6366f1;
            --primary-hover: #4f46e5;
            --primary-light: #eef2ff;
            --primary-glow: rgba(99, 102, 241, 0.35);
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;

            /* Bordures */
            --border: #e2e8f0;
            --border-light: #f1f5f9;

            /* Radius */
            --radius-sm: 8px;
            --radius: 12px;
            --radius-lg: 16px;
            --radius-xl: 20px;

            /* Shadows */
            --shadow-xs: 0 1px 2px rgba(15, 23, 42, 0.04);
            --shadow-sm: 0 1px 3px rgba(15, 23, 42, 0.06), 0 1px 2px rgba(15, 23, 42, 0.04);
            --shadow-md: 0 4px 6px -1px rgba(15, 23, 42, 0.08), 0 2px 4px -1px rgba(15, 23, 42, 0.04);
            --shadow-lg: 0 10px 15px -3px rgba(15, 23, 42, 0.1), 0 4px 6px -2px rgba(15, 23, 42, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(15, 23, 42, 0.1), 0 10px 10px -5px rgba(15, 23, 42, 0.04);

            /* Transitions */
            --ease: cubic-bezier(0.4, 0, 0.2, 1);
            --transition: all 0.2s var(--ease);
            --transition-slow: all 0.35s var(--ease);
        }

        /* ============================================================
           RESET & BASE
           ============================================================ */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }

        html, body { height: 100%; }

        body {
            background-color: var(--bg-body);
            color: var(--text-main);
            overflow: hidden;
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

        /* Sidebar scrollbar (plus clair) */
        .sidebar-scroll::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.08); }
        .sidebar-scroll::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.18); }

        /* ============================================================
           LAYOUT PRINCIPAL
           ============================================================ */
        .admin-layout {
            display: flex;
            height: 100vh;
            width: 100vw;
        }

        /* ============================================================
           SIDEBAR
           ============================================================ */
        .admin-sidebar {
            width: 280px;
            background: linear-gradient(180deg, #0f172a 0%, #1e293b 100%);
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
            z-index: 100;
            position: relative;
            border-right: 1px solid rgba(255,255,255,0.04);
        }
        .admin-sidebar::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background:
                radial-gradient(circle at 10% 0%, rgba(99, 102, 241, 0.15), transparent 40%),
                radial-gradient(circle at 90% 100%, rgba(139, 92, 246, 0.08), transparent 40%);
            pointer-events: none;
        }

        /* ---------- Brand ---------- */
        .sidebar-brand {
            padding: 26px 24px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            position: relative;
            z-index: 1;
        }

        .brand-icon-wrap {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.35);
            flex-shrink: 0;
        }

        .brand-text {
            display: flex;
            flex-direction: column;
            line-height: 1.15;
        }
        .brand-main {
            font-size: 20px;
            font-weight: 800;
            letter-spacing: -0.5px;
            color: var(--text-white);
        }
        .brand-sub {
            font-size: 10px;
            font-weight: 500;
            color: var(--text-sidebar-muted);
            letter-spacing: 0.8px;
            text-transform: uppercase;
            margin-top: 2px;
        }

        /* ---------- Profil Admin ---------- */
        .admin-profile-header {
            margin: 0 16px 22px;
            padding: 14px;
            display: flex;
            align-items: center;
            gap: 12px;
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.14), rgba(139, 92, 246, 0.06));
            border: 1px solid rgba(99, 102, 241, 0.2);
            border-radius: var(--radius);
            position: relative;
            z-index: 1;
            overflow: hidden;
            transition: var(--transition);
        }
        .admin-profile-header::before {
            content: '';
            position: absolute;
            top: -50%; right: -20%;
            width: 100px; height: 100px;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.35), transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }
        .admin-profile-header:hover {
            border-color: rgba(99, 102, 241, 0.35);
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.2), rgba(139, 92, 246, 0.1));
        }

        .admin-avatar {
            position: relative;
            width: 42px;
            height: 42px;
            flex-shrink: 0;
            z-index: 1;
        }
        .admin-avatar img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid rgba(99, 102, 241, 0.6);
            box-shadow: 0 2px 8px rgba(99, 102, 241, 0.35);
        }
        .status-dot {
            position: absolute;
            bottom: 0; right: 0;
            width: 11px; height: 11px;
            background: var(--success);
            border: 2px solid var(--bg-sidebar);
            border-radius: 50%;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.25);
        }

        .profile-info {
            flex: 1;
            min-width: 0;
            position: relative;
            z-index: 1;
        }
        .profile-info .name {
            font-size: 13.5px;
            font-weight: 600;
            color: var(--text-white);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .profile-info .role {
            font-size: 11px;
            color: #a5b4fc;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 5px;
            margin-top: 3px;
        }
        .role-dot {
            width: 5px; height: 5px;
            background: var(--success);
            border-radius: 50%;
            animation: pulseDot 2s infinite;
        }
        @keyframes pulseDot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.6; transform: scale(0.85); }
        }

        /* ---------- Scroll Area ---------- */
        .sidebar-scroll {
            flex: 1;
            overflow-y: auto;
            padding: 0 12px;
            position: relative;
            z-index: 1;
        }

        /* ---------- Sections ---------- */
        .menu-section {
            margin-bottom: 8px;
        }
        .menu-section-title {
            padding: 14px 12px 8px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #475569;
            position: relative;
        }
        .menu-section-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 12px;
            right: 12px;
            height: 1px;
            background: linear-gradient(90deg, rgba(148, 163, 184, 0.15), transparent);
        }

        /* ---------- Liste menu ---------- */
        .admin-menu-list {
            list-style: none;
            padding-top: 6px;
        }
        .admin-menu-list li {
            margin-bottom: 2px;
        }

        .admin-menu-list li a {
            display: flex;
            align-items: center;
            padding: 10px 12px;
            color: var(--text-sidebar);
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            border-radius: var(--radius-sm);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .admin-menu-list li a::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 3px;
            height: 0;
            background: linear-gradient(180deg, #6366f1, #8b5cf6);
            border-radius: 0 3px 3px 0;
            transition: var(--transition);
        }

        .admin-menu-list li a:hover {
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-white);
            transform: translateX(2px);
        }
        .admin-menu-list li a:hover::before {
            height: 20px;
        }

        .admin-menu-list li a.active {
            background: linear-gradient(90deg, rgba(99, 102, 241, 0.22), rgba(99, 102, 241, 0.04));
            color: var(--text-white);
            font-weight: 600;
        }
        .admin-menu-list li a.active::before {
            height: 60%;
        }

        .admin-menu-list li a .menu-icon {
            font-size: 15px;
            width: 22px;
            text-align: center;
            margin-right: 10px;
            color: #94a3b8;
            transition: var(--transition);
            flex-shrink: 0;
        }
        .admin-menu-list li a:hover .menu-icon,
        .admin-menu-list li a.active .menu-icon {
            color: #a5b4fc;
        }

        .admin-menu-list li a .menu-text {
            flex: 1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Badges menu */
        .menu-badge {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 10px;
            margin-left: auto;
            flex-shrink: 0;
            box-shadow: 0 2px 6px rgba(99, 102, 241, 0.35);
            min-width: 20px;
            text-align: center;
        }

        /* ---------- Footer ---------- */
        .sidebar-footer {
            padding: 16px;
            border-top: 1px solid rgba(255,255,255,0.06);
            position: relative;
            z-index: 1;
        }
        .sidebar-footer a.logout {
            color: #fca5a5;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border-radius: var(--radius-sm);
            transition: var(--transition);
            border: 1px solid transparent;
        }
        .sidebar-footer a.logout:hover {
            background: rgba(239, 68, 68, 0.12);
            color: #fecaca;
            border-color: rgba(239, 68, 68, 0.15);
        }

        /* ============================================================
           MAIN WRAPPER
           ============================================================ */
        .admin-main-wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
            height: 100vh;
            background-color: var(--bg-body);
        }

        /* ============================================================
           TOP NAVBAR
           ============================================================ */
        .admin-top-navbar {
            height: 76px;
            background: rgba(248, 250, 252, 0.85);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 36px;
            flex-shrink: 0;
            border-bottom: 1px solid var(--border);
            position: sticky;
            top: 0;
            z-index: 50;
        }

        .navbar-left .page-title {
            font-size: 22px;
            font-weight: 700;
            color: var(--text-main);
            letter-spacing: -0.4px;
        }
        .navbar-left .page-subtitle {
            font-size: 12.5px;
            color: var(--text-muted);
            margin-top: 2px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .navbar-left .page-subtitle i {
            font-size: 11px;
            opacity: 0.8;
        }

        .navbar-right {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        /* ---------- Bouton thème ---------- */
        .theme-toggle-btn {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: var(--bg-card);
            border: 1px solid var(--border);
            color: var(--text-muted);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            transition: var(--transition);
            box-shadow: var(--shadow-xs);
        }
        .theme-toggle-btn:hover {
            background: var(--primary-light);
            color: var(--primary);
            border-color: var(--primary-light);
            transform: translateY(-1px);
            box-shadow: 0 4px 10px rgba(99, 102, 241, 0.2);
        }

        /* ---------- Notification ---------- */
        .notification-container { position: relative; }

        .notification-bell-btn {
            position: relative;
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: var(--bg-card);
            border: 1px solid var(--border);
            color: var(--text-muted);
            font-size: 16px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
            box-shadow: var(--shadow-xs);
        }
        .notification-bell-btn:hover {
            background: var(--primary-light);
            color: var(--primary);
            border-color: var(--primary-light);
            transform: translateY(-1px);
            box-shadow: 0 4px 10px rgba(99, 102, 241, 0.2);
        }

        .bell-badge {
            position: absolute;
            top: -2px;
            right: -2px;
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 10px;
            font-weight: 700;
            border: 2px solid var(--bg-body);
            box-shadow: 0 2px 6px rgba(239, 68, 68, 0.4);
            animation: badgePulse 2.2s infinite;
            min-width: 18px;
            text-align: center;
        }
        @keyframes badgePulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.12); }
        }

        /* ---------- Dropdown notifications ---------- */
        .dropdown-content {
            position: absolute;
            right: 0;
            top: calc(100% + 12px);
            width: 380px;
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-xl);
            border: 1px solid var(--border);
            overflow: hidden;
            z-index: 1000;

            /* Animation d'entrée */
            opacity: 0;
            visibility: hidden;
            transform: translateY(-8px) scale(0.98);
            transform-origin: top right;
            transition: all 0.22s var(--ease);
        }
        .dropdown-content.active {
            opacity: 1;
            visibility: visible;
            transform: translateY(0) scale(1);
        }

        .dropdown-header {
            padding: 16px 20px;
            font-weight: 700;
            font-size: 14px;
            color: var(--text-main);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            border-bottom: 1px solid var(--border);
        }
        .dropdown-header .count {
            font-size: 11px;
            font-weight: 600;
            color: var(--primary);
            background: var(--primary-light);
            padding: 3px 10px;
            border-radius: 10px;
        }

        .notif-items-container {
            max-height: 340px;
            overflow-y: auto;
        }

        .notif-link {
            display: block;
            padding: 14px 20px;
            border-bottom: 1px solid var(--border-light);
            text-decoration: none;
            color: var(--text-main);
            transition: var(--transition);
            position: relative;
            padding-left: 34px;
        }
        .notif-link::before {
            content: '';
            position: absolute;
            left: 16px;
            top: 20px;
            width: 8px;
            height: 8px;
            background: var(--primary);
            border-radius: 50%;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
        }
        .notif-link:hover {
            background: var(--bg-body);
        }
        .notif-link:last-child {
            border-bottom: none;
        }

        .notif-title {
            font-weight: 600;
            font-size: 13px;
            color: var(--text-main);
            display: block;
            margin-bottom: 3px;
            line-height: 1.35;
        }
        .notif-message {
            font-size: 12px;
            color: var(--text-muted);
            line-height: 1.45;
            display: block;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .dropdown-footer {
            padding: 12px;
            text-align: center;
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            border-top: 1px solid var(--border);
        }
        .dropdown-footer a {
            color: var(--primary);
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: var(--transition);
        }
        .dropdown-footer a:hover {
            color: var(--primary-hover);
            gap: 8px;
        }

        /* ---------- Profil topbar ---------- */
        .nav-btn-profile {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 6px 12px 6px 6px;
            color: var(--text-main);
            text-decoration: none;
            font-weight: 600;
            font-size: 13.5px;
            border-radius: 30px;
            border: 1px solid transparent;
            transition: var(--transition);
        }
        .nav-btn-profile:hover {
            background: var(--primary-light);
            border-color: rgba(99, 102, 241, 0.15);
        }

        .profile-avatar-sm {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            overflow: hidden;
            border: 2px solid var(--primary-light);
            flex-shrink: 0;
            box-shadow: 0 2px 6px rgba(99, 102, 241, 0.15);
        }
        .profile-avatar-sm img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* ============================================================
           IFRAME CONTENT
           ============================================================ */
        .admin-page-content {
            flex: 1;
            padding: 24px 32px 32px;
            overflow: hidden;
            background-color: var(--bg-body);
        }

        .content-frame {
            width: 100%;
            height: 100%;
            border: none;
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border);
        }

        /* ============================================================
           RESPONSIVE
           ============================================================ */
        @media (max-width: 1024px) {
            .admin-sidebar { width: 240px; }
        }

        @media (max-width: 768px) {
            body { overflow: auto; }
            .admin-layout { flex-direction: column; height: auto; min-height: 100vh; }
            .admin-sidebar {
                width: 100%;
                height: auto;
                max-height: 420px;
                overflow-y: auto;
                border-right: none;
                border-bottom: 1px solid rgba(255,255,255,0.06);
            }
            .admin-main-wrapper { height: auto; min-height: calc(100vh - 420px); }
            .admin-top-navbar {
                padding: 16px 20px;
                height: auto;
                min-height: 70px;
                flex-wrap: wrap;
                gap: 12px;
            }
            .navbar-left { flex: 1 1 100%; }
            .navbar-right { flex: 1 1 100%; justify-content: flex-end; }
            .admin-page-content {
                height: 70vh;
                padding: 16px;
            }
            .dropdown-content {
                width: calc(100vw - 40px);
                max-width: 380px;
                right: -60px;
            }
        }

        @media (max-width: 480px) {
            .brand-sub { display: none; }
            .navbar-left .page-title { font-size: 18px; }
            .navbar-left .page-subtitle { font-size: 11px; }
            .nav-btn-profile span:not(.profile-avatar-sm) { display: none; }
        }
    </style>
</head>
<body>

<div class="admin-layout">
    
    <!-- ============================================================
    SIDEBAR
    ============================================================ -->
    <aside class="admin-sidebar">
        <!-- Brand -->
        <div class="sidebar-brand">
            <div class="brand-icon-wrap">🎓</div>
            <div class="brand-text">
                <span class="brand-main">UniVerse</span>
                <span class="brand-sub">Console Admin</span>
            </div>
        </div>

        <!-- Profil Admin -->
        <div class="admin-profile-header">
            <div class="admin-avatar">
                <img src="<?= htmlspecialchars($admin_photo); ?>" alt="Admin">
                <span class="status-dot"></span>
            </div>
            <div class="profile-info">
                <div class="name"><?= htmlspecialchars($admin_name ?: 'Administrateur'); ?></div>
                <div class="role">
                    <span class="role-dot"></span> Administrateur
                </div>
            </div>
        </div>

        <!-- Navigation -->
        <div class="sidebar-scroll">
            
            <!-- Section Principal -->
            <div class="menu-section">
                <div class="menu-section-title">Principal</div>
                <ul class="admin-menu-list">
                    <li>
                        <a href="dashboardadmin.php?iframe=1" target="admin-frame" class="active">
                            <i class="fas fa-chart-line menu-icon"></i>
                            <span class="menu-text">Tableau de bord</span>
                        </a>
                    </li>
                    <li>
                        <a href="recherche_globale.php" target="_blank">
                            <i class="fas fa-search menu-icon"></i>
                            <span class="menu-text">Recherche globale</span>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Section Gestion Utilisateurs -->
            <div class="menu-section">
                <div class="menu-section-title">Gestion Utilisateurs</div>
                <ul class="admin-menu-list">
				    <li>
						<a href="facultes.php" target="_blank">
							<i class="fas fa-university menu-icon"></i>
							<span class="menu-text">Nos Facultés</span>
						</a>
					</li>
                    <li>
                        <a href="afficheuser.php?iframe=1" target="admin-frame">
                            <i class="fas fa-users menu-icon"></i>
                            <span class="menu-text">Tous les utilisateurs</span>
                            <span class="menu-badge"><?= $countUsers ?></span>
                        </a>
                    </li>
                    <li>
                        <a href="afficheuser.php?statut=Actif&iframe=1" target="admin-frame">
                            <i class="fas fa-user-check menu-icon"></i>
                            <span class="menu-text">Utilisateurs actifs</span>
                        </a>
                    </li>
                    <li>
                        <a href="ajouteuser.php?iframe=1" target="admin-frame">
                            <i class="fas fa-user-plus menu-icon"></i>
                            <span class="menu-text">Ajouter un utilisateur</span>
                        </a>
                    </li>
                    <li>
                        <a href="dashboard_agent.php" target="_blank">
                            <i class="fas fa-user-tie menu-icon"></i>
                            <span class="menu-text">Dashboard Agent</span>
                        </a>
                    </li>
                    <li>
                        <a href="gestion_roles.php?iframe=1" target="admin-frame">
                            <i class="fas fa-user-shield menu-icon"></i>
                            <span class="menu-text">Rôles & Permissions</span>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Section Système & Sécurité -->
            <div class="menu-section">
                <div class="menu-section-title">Système & Sécurité</div>
                <ul class="admin-menu-list">
                    <li>
                        <a href="centre_alertes.php" target="_blank">
                            <i class="fas fa-exclamation-triangle menu-icon"></i>
                            <span class="menu-text">Centre d'alertes</span>
                        </a>
                    </li>
                    <li>
                        <a href="admin_notifications.php?iframe=1" target="admin-frame">
                            <i class="fas fa-bell menu-icon"></i>
                            <span class="menu-text">Notifications</span>
                            <?php if ($notif_count > 0): ?>
                                <span class="menu-badge"><?= $notif_count ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li>
                        <a href="sessions.php?iframe=1" target="admin-frame">
                            <i class="fas fa-desktop menu-icon"></i>
                            <span class="menu-text">Sessions actives</span>
                        </a>
                    </li>
                    <li>
                        <a href="historique_securite.php?iframe=1" target="admin-frame">
                            <i class="fas fa-shield-alt menu-icon"></i>
                            <span class="menu-text">Historique sécurité</span>
                        </a>
                    </li>
                    <li>
                        <a href="archives.php?iframe=1" target="admin-frame">
                            <i class="fas fa-archive menu-icon"></i>
                            <span class="menu-text">Archives globales</span>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Section Maintenance -->
            <div class="menu-section">
                <div class="menu-section-title">Maintenance</div>
                <ul class="admin-menu-list">
                    <li>
                        <a href="rapports_admin.php?iframe=1" target="admin-frame">
                            <i class="fas fa-chart-bar menu-icon"></i>
                            <span class="menu-text">Rapports & Exports</span>
                        </a>
                    </li>
                    <li>
                        <a href="rapports_personnalises.php" target="admin-frame">
                            <i class="fas fa-chart-pie menu-icon"></i>
                            <span class="menu-text">Rapports personnalisés</span>
                        </a>
                    </li>
                    <li>
                        <a href="configuration.php?iframe=1" target="admin-frame">
                            <i class="fas fa-cog menu-icon"></i>
                            <span class="menu-text">Configuration</span>
                        </a>
                    </li>
                    <li>
                        <a href="backup.php?iframe=1" target="admin-frame">
                            <i class="fas fa-save menu-icon"></i>
                            <span class="menu-text">Sauvegarde</span>
                        </a>
                    </li>
                    <li>
                        <a href="restauration.php?iframe=1" target="admin-frame">
                            <i class="fas fa-history menu-icon"></i>
                            <span class="menu-text">Restauration</span>
                        </a>
                    </li>
                    <li>
                        <a href="verifier_2fa.php" target="admin-frame">
                            <i class="fas fa-key menu-icon"></i>
                            <span class="menu-text">2FA</span>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Section Mon Compte -->
            <div class="menu-section">
                <div class="menu-section-title">Mon Compte</div>
                <ul class="admin-menu-list">
                    <li>
                        <a href="profile_admin.php?iframe=1" target="admin-frame">
                            <i class="fas fa-user menu-icon"></i>
                            <span class="menu-text">Mon profil</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Logout -->
        <div class="sidebar-footer">
            <a href="logout.php" class="logout" onclick="return confirm('Voulez-vous vraiment vous déconnecter ?');">
                <i class="fas fa-sign-out-alt"></i> Déconnexion
            </a>
        </div>
    </aside>

    <!-- ============================================================
    MAIN CONTENT
    ============================================================ -->
    <div class="admin-main-wrapper">
        
        <!-- Top Navbar -->
        <header class="admin-top-navbar">
            <div class="navbar-left">
                <div class="page-title">Tableau de bord</div>
                <div class="page-subtitle">
                    <i class="far fa-calendar-alt"></i>
                    <span><?= date('d/m/Y') ?> — Bienvenue sur UniVerse Admin</span>
                </div>
            </div>
            
            <div class="navbar-right">
                <!-- Toggle thème -->
                <button id="themeToggleBtn" class="theme-toggle-btn" onclick="toggleTheme()" title="Mode sombre">
                    🌙
                </button>

                <!-- Notifications -->
                <div class="notification-container">
                    <button class="notification-bell-btn" id="notifBtn">
                        <i class="fas fa-bell"></i>
                        <span id="notifBadge">
                            <?php if ($notif_count > 0): ?>
                                <span class="bell-badge"><?= $notif_count ?></span>
                            <?php endif; ?>
                        </span>
                    </button>
                    
                    <div class="dropdown-content" id="notifDropdown">
                        <div class="dropdown-header">
                            <span><i class="fas fa-bell" style="color:var(--primary); margin-right:6px;"></i> Notifications</span>
                            <span class="count"><?= $notif_count ?> nouvelle(s)</span>
                        </div>
                        
                        <div class="notif-items-container" id="notifContainer">
                            <?php if(!empty($list_notifications)): ?>
                                <?php foreach($list_notifications as $notif): ?>
                                    <a href="admin_notifications.php?iframe=1" class="notif-link" target="admin-frame">
                                        <span class="notif-title"><?= htmlspecialchars($notif['titre']) ?></span>
                                        <span class="notif-message"><?= htmlspecialchars($notif['message']) ?></span>
                                    </a>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div style="padding:30px; text-align:center; color:var(--text-muted); font-size:13px;">
                                    <i class="fas fa-inbox" style="font-size:28px; opacity:0.3; display:block; margin-bottom:8px;"></i>
                                    Aucune notification
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="dropdown-footer">
                            <a href="admin_notifications.php?iframe=1" target="admin-frame">
                                Voir toutes les notifications <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Profil -->
                <a href="profile_admin.php?iframe=1" target="admin-frame" class="nav-btn-profile">
                    <span class="profile-avatar-sm">
                        <img src="<?= htmlspecialchars($admin_photo); ?>" alt="Photo">
                    </span>
                    <span><?= htmlspecialchars($admin_name ?: 'Mon Profil'); ?></span>
                </a>
            </div>
        </header>

        <!-- Iframe -->
        <main class="admin-page-content">
            <iframe name="admin-frame" src="dashboardadmin.php?iframe=1" class="content-frame"></iframe>
        </main>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const notifBtn = document.getElementById('notifBtn');
    const notifDropdown = document.getElementById('notifDropdown');

    notifBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        notifDropdown.classList.toggle('active');
    });

    document.addEventListener('click', function(e) {
        if (!notifDropdown.contains(e.target) && e.target !== notifBtn) {
            notifDropdown.classList.remove('active');
        }
    });

    notifDropdown.addEventListener('click', function(e) {
        if (e.target.tagName === 'A' || e.target.closest('a')) {
            notifDropdown.classList.remove('active');
        }
    });

    // AJAX Refresh toutes les 15 sec
    function chargerNotificationsAjax() {
        fetch('?action=get_notifs')
            .then(response => response.json())
            .then(data => {
                const badgeBox = document.getElementById('notifBadge');
                badgeBox.innerHTML = data.count > 0 ? `<span class="bell-badge">${data.count}</span>` : '';
                document.querySelector('.dropdown-header .count').textContent = data.count + ' nouvelle(s)';
                
                const container = document.getElementById('notifContainer');
                container.innerHTML = '';
                if (data.notifications.length > 0) {
                    data.notifications.forEach(notif => {
                        container.innerHTML += `
                            <a href="admin_notifications.php?iframe=1" class="notif-link" target="admin-frame">
                                <span class="notif-title">${escapeHtml(notif.titre)}</span>
                                <span class="notif-message">${escapeHtml(notif.message)}</span>
                            </a>
                        `;
                    });
                } else {
                    container.innerHTML = `<div style="padding:30px; text-align:center; color:var(--text-muted); font-size:13px;"><i class="fas fa-inbox" style="font-size:28px; opacity:0.3; display:block; margin-bottom:8px;"></i>Aucune notification</div>`;
                }
            })
            .catch(error => console.error(error));
    }

    function escapeHtml(text) {
        if (!text) return '';
        return text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }

    setInterval(chargerNotificationsAjax, 15000);
});
</script>

</body>
</html>