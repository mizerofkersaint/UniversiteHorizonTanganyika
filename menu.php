<?php
// ============================================================
// menu.php — Menu universitaire adapté au rôle
// ✅ CORRECTION : ob_start() pour permettre header() dans les pages
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ⬇️⬇️ CETTE LIGNE CORRIGE LE BUG "headers already sent" ⬇️⬇️
ob_start();
// ⬆️⬆️ Sans cette ligne, header() échoue après include menu.php ⬆️⬆️

$role_actuel     = $_SESSION['role'] ?? '';
$user_nom_menu   = trim(($_SESSION['prenom'] ?? '') . ' ' . ($_SESSION['nom'] ?? ''));
$user_photo_menu = $_SESSION['photo'] ?? 'default.png';
$page_actuelle   = basename($_SERVER['PHP_SELF']);

// ============================================================
// LIEN VERS LE DASHBOARD SELON LE RÔLE
// ============================================================
$dashboards_menu = [
    'Administrateur' => 'administrateur.php',
    'Recteur'        => 'dashboard_recteur.php',
    'Doyen'          => 'dashboard_doyen.php',
    'Comptable'      => 'dashboard_comptable.php',
    'Professeur'     => 'dashboard_professeur.php',
    'Etudiant'       => 'dashboard_etudiant.php',
    'Agent'          => 'dashboard_agent.php'
];
$lien_dashboard = $dashboards_menu[$role_actuel] ?? 'Accueil.php';

// ============================================================
// COULEURS PAR RÔLE
// ============================================================
$couleurs_roles = [
    'Administrateur' => '#dc2626',
    'Recteur'        => '#d4af37',
    'Doyen'          => '#8b5cf6',
    'Comptable'      => '#10b981',
    'Professeur'     => '#2563eb',
    'Etudiant'       => '#3b82f6',
    'Agent'          => '#f59e0b'
];
$couleur_role = $couleurs_roles[$role_actuel] ?? '#003366';

$icones_roles = [
    'Administrateur' => '👑',
    'Recteur'        => '🏛️',
    'Doyen'          => '🎓',
    'Comptable'      => '💰',
    'Professeur'     => '👨‍🏫',
    'Etudiant'       => '🎓',
    'Agent'          => '📝'
];
$icone_role = $icones_roles[$role_actuel] ?? '👤';

// ============================================================
// CONFIGURATION DU MENU (avec rôles autorisés)
// ============================================================
$menu_items = [
    ['href' => 'Accueil.php',                'icon' => '🏠', 'label' => 'Accueil',        'roles' => ['*']],
    ['href' => $lien_dashboard,              'icon' => '📊', 'label' => 'Tableau de bord', 'roles' => ['Administrateur','Recteur','Doyen','Comptable','Professeur','Etudiant','Agent']],
    ['href' => 'AffichageEtudiants.php',     'icon' => '🎓', 'label' => 'Étudiants',       'roles' => ['Administrateur','Doyen','Agent']],
    ['href' => 'afficheprofesseur.php',      'icon' => '👨‍🏫', 'label' => 'Professeurs',    'roles' => ['Administrateur','Doyen']],
    ['href' => 'affichecours.php',           'icon' => '📚', 'label' => 'Cours',           'roles' => ['Administrateur','Doyen','Professeur']],
    ['href' => 'afficheclasse.php',          'icon' => '🏫', 'label' => 'Classes',         'roles' => ['Administrateur','Doyen','Agent']],
    ['href' => 'affichedepartement.php',     'icon' => '🏛️', 'label' => 'Départements',   'roles' => ['Administrateur','Agent']],
    ['href' => 'affichenote.php',            'icon' => '📝', 'label' => 'Notes',           'roles' => ['Administrateur','Doyen','Professeur']],
    ['href' => 'affichepaiement.php',        'icon' => '💰', 'label' => 'Paiements',       'roles' => ['Administrateur','Comptable']],
    ['href' => 'afficheinscription.php',     'icon' => '📋', 'label' => 'Inscriptions',    'roles' => ['Administrateur','Agent']],
    ['href' => 'afficheuser.php',            'icon' => '👥', 'label' => 'Utilisateurs',    'roles' => ['Administrateur']],
    ['href' => 'admin_notifications.php',    'icon' => '🔔', 'label' => 'Notifications',   'roles' => ['Administrateur']],
    ['href' => 'archives.php',               'icon' => '🗃️', 'label' => 'Archives',        'roles' => ['Administrateur']],
    ['href' => 'sessions.php',               'icon' => '🔍', 'label' => 'Sessions',        'roles' => ['Administrateur']],
    ['href' => 'backup.php',                 'icon' => '💾', 'label' => 'Sauvegarde',      'roles' => ['Administrateur']],
    ['href' => 'configuration.php',          'icon' => '⚙️', 'label' => 'Configuration',  'roles' => ['Administrateur']],
    ['href' => 'recherche_globale.php',      'icon' => '🔎', 'label' => 'Recherche',       'roles' => ['Administrateur','Recteur','Doyen','Agent']],
    ['href' => 'supports_cours.php',         'icon' => '📁', 'label' => 'Supports',        'roles' => ['Administrateur','Professeur','Etudiant']],
    ['href' => 'chat.php',                   'icon' => '💬', 'label' => 'Chat',            'roles' => ['Administrateur','Professeur','Etudiant','Doyen','Comptable']],
    ['href' => 'centre_alertes.php',         'icon' => '🚨', 'label' => 'Alertes',         'roles' => ['Administrateur','Recteur','Doyen']],
    ['href' => 'rapports_personnalises.php', 'icon' => '📈', 'label' => 'Rapports',        'roles' => ['Administrateur','Recteur']],
    ['href' => 'mon_profil.php',             'icon' => '👤', 'label' => 'Mon profil',      'roles' => ['*']],
    ['href' => 'logout.php',                 'icon' => '🚪', 'label' => 'Déconnexion',     'roles' => ['*'], 'class' => 'logout']
];

function menu_visible($item, $role) {
    if (in_array('*', $item['roles'], true)) return true;
    return in_array($role, $item['roles'], true);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Université du Lac Tanganyika</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Arial, sans-serif; }

        /* ===== HEADER ===== */
        .univ-header {
            background: linear-gradient(135deg, #003366, #0055aa);
            color: white;
            padding: 18px 25px;
            box-shadow: 0 4px 12px rgba(0,0,0,.2);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .header-left h1 {
            font-size: 22px;
            letter-spacing: 1px;
        }
        .header-left p {
            font-size: 13px;
            opacity: 0.85;
            margin-top: 3px;
        }
        .header-user {
            display: flex;
            align-items: center;
            gap: 12px;
            background: rgba(255,255,255,0.1);
            padding: 8px 15px;
            border-radius: 30px;
            border: 1px solid rgba(255,255,255,0.15);
        }
        .user-info {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
        }
        .user-name {
            font-size: 14px;
            font-weight: 600;
        }
        .user-role {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 2px 10px;
            border-radius: 10px;
            margin-top: 2px;
            color: white;
        }
        .user-avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid rgba(255,255,255,0.4);
        }

        /* ===== NAVIGATION ===== */
        .univ-nav {
            background: #0055aa;
            padding: 10px 15px;
            box-shadow: 0 4px 10px rgba(0,0,0,.15);
        }
        .univ-nav ul {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            list-style: none;
            gap: 8px;
            margin: 0;
            padding: 0;
        }
        .univ-nav a.nav-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: white;
            color: #003366;
            padding: 8px 14px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
            transition: all .25s ease;
            box-shadow: 0 2px 6px rgba(0,0,0,.1);
            white-space: nowrap;
        }
        .univ-nav a.nav-link:hover {
            background: #003366;
            color: white;
            transform: translateY(-2px);
        }
        .univ-nav a.nav-link.active {
            background: #f59e0b;
            color: white;
            box-shadow: 0 4px 12px rgba(245,158,11,.4);
        }
        .univ-nav a.nav-link.logout {
            background: #fee2e2;
            color: #991b1b;
        }
        .univ-nav a.nav-link.logout:hover {
            background: #dc2626;
            color: white;
        }

        /* ===== RESPONSIVE ===== */
        @media(max-width: 900px) {
            .univ-header { flex-direction: column; text-align: center; }
            .user-info { align-items: center; }
            .univ-nav ul { flex-direction: column; align-items: stretch; }
            .univ-nav a.nav-link { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>
    <header class="univ-header">
        <div class="header-left">
            <h1>🎓 Université Horizon Tanganyika (UHT)</h1>
            <p>Système de Gestion Universitaire</p>
        </div>
        <?php if (!empty($role_actuel)): ?>
            <div class="header-user">
                <div class="user-info">
                    <span class="user-name"><?= htmlspecialchars($user_nom_menu ?: 'Utilisateur') ?></span>
                    <span class="user-role" style="background:<?= $couleur_role ?>">
                        <?= $icone_role ?> <?= htmlspecialchars($role_actuel) ?>
                    </span>
                </div>
                <img src="uploads/<?= htmlspecialchars($user_photo_menu) ?>" class="user-avatar" alt="Avatar">
            </div>
        <?php endif; ?>
    </header>

    <nav class="univ-nav">
        <ul>
            <?php foreach ($menu_items as $item): ?>
                <?php if (menu_visible($item, $role_actuel)): ?>
                    <?php
                    $is_active = ($page_actuelle === $item['href']);
                    $class = 'nav-link';
                    if ($is_active) $class .= ' active';
                    if (isset($item['class'])) $class .= ' ' . $item['class'];
                    ?>
                    <li>
                        <a href="<?= htmlspecialchars($item['href']) ?>" class="<?= $class ?>">
                            <span><?= $item['icon'] ?></span> <?= htmlspecialchars($item['label']) ?>
                        </a>
                    </li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ul>
    </nav>
</body>
</html>