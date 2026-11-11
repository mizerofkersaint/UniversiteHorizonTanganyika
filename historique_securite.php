<?php
require_once __DIR__ . '/db.php';
// ============================================================
// historique_securite.php — Événements de sécurité d'UniVerse
// ============================================================

// 1. Démarrer la session AVANT tout HTML
require_once 'auth.php';
requireRole('Administrateur');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================================
// GARDE-FOU TEMPORAIRE — ACCÈS ADMINISTRATEUR
// ============================================================
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
    error_log("[historique_securite] Erreur connexion : " . $e->getMessage());
    die("Erreur de connexion à la base de données.");
}

// ============================================================
// TYPES D'ÉVÉNEMENTS DE SÉCURITÉ
// ============================================================
// Chaque type est défini par un ensemble de conditions SQL
// (via LIKE) sur les colonnes `action` et `description`.
$types_evenements = [
    'role_change' => [
        'label'  => 'Changement de rôle',
        'icone'  => '🎭',
        'color'  => '#8b5cf6',
        'sql'    => "description LIKE 'Changement de rôle%'",
    ],
    'mdp_change' => [
        'label'  => 'Changement de mot de passe',
        'icone'  => '🔑',
        'color'  => '#f59e0b',
        'sql'    => "description = 'Changement de mot de passe'",
    ],
    'user_create' => [
        'label'  => 'Création d\'utilisateur',
        'icone'  => '👤',
        'color'  => '#10b981',
        'sql'    => "(table_concernee = 'users' AND action = 'INSERT')",
    ],
    'user_delete' => [
        'label'  => 'Suppression d\'utilisateur',
        'icone'  => '🗑️',
        'color'  => '#dc2626',
        'sql'    => "(table_concernee = 'users' AND action = 'DELETE')",
    ],
    'backup' => [
        'label'  => 'Sauvegarde',
        'icone'  => '💾',
        'color'  => '#10b981',
        'sql'    => "table_concernee = 'backup'",
    ],
    'restauration' => [
        'label'  => 'Restauration',
        'icone'  => '♻️',
        'color'  => '#ef4444',
        'sql'    => "table_concernee = 'restauration'",
    ],
    'delete' => [
        'label'  => 'Suppression (générale)',
        'icone'  => '⚠️',
        'color'  => '#ea580c',
        'sql'    => "action = 'DELETE'",
    ],
];

// ============================================================
// CONSTRUCTION DU FILTRE SQL DES ÉVÉNEMENTS DE SÉCURITÉ
// ============================================================
// Un événement de sécurité = UNION de toutes les conditions ci-dessus
$conditions_securite = [];
foreach ($types_evenements as $type) {
    $conditions_securite[] = "(" . $type['sql'] . ")";
}
$sql_securite = "(" . implode(" OR ", $conditions_securite) . ")";

// ============================================================
// RÉCUPÉRATION DES FILTRES
// ============================================================
$filtre_recherche  = isset($_GET['recherche'])  ? trim($_GET['recherche'])  : '';
$filtre_id_user    = isset($_GET['id_user'])    && $_GET['id_user'] !== '' ? (int)$_GET['id_user'] : 0;
$filtre_type       = isset($_GET['type'])       ? trim($_GET['type'])       : '';
$filtre_date_deb   = isset($_GET['date_debut']) ? trim($_GET['date_debut']) : '';
$filtre_date_fin   = isset($_GET['date_fin'])   ? trim($_GET['date_fin'])   : '';

if (!array_key_exists($filtre_type, $types_evenements)) {
    $filtre_type = '';
}

// ============================================================
// CONSTRUCTION DE LA REQUÊTE
// ============================================================
$where  = [$sql_securite];
$params = [];

if ($filtre_recherche !== '') {
    $where[] = "(nom_utilisateur LIKE ? OR prenom_utilisateur LIKE ? OR description LIKE ? OR adresse_ip LIKE ?)";
    $like = '%' . $filtre_recherche . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($filtre_id_user > 0) {
    $where[] = "id_user = ?";
    $params[] = $filtre_id_user;
}

if ($filtre_type !== '') {
    $where[] = "(" . $types_evenements[$filtre_type]['sql'] . ")";
}

if ($filtre_date_deb !== '') {
    $where[] = "date_action >= ?";
    $params[] = $filtre_date_deb . ' 00:00:00';
}

if ($filtre_date_fin !== '') {
    $where[] = "date_action <= ?";
    $params[] = $filtre_date_fin . ' 23:59:59';
}

$sql_where = " WHERE " . implode(" AND ", $where);

// ============================================================
// PAGINATION
// ============================================================
$limite = 20;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

try {
    $stmt = $bdd->prepare("SELECT COUNT(*) AS total FROM archivage" . $sql_where);
    $stmt->execute($params);
    $total = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];
} catch (Exception $e) {
    error_log("[historique_securite] Erreur count : " . $e->getMessage());
    $total = 0;
}

$nb_pages = max(1, (int)ceil($total / $limite));
if ($page > $nb_pages) {
    $page = $nb_pages;
}
$debut = ($page - 1) * $limite;

// Requête principale
$sql = "SELECT 
            id_archivage, id_user,
            nom_utilisateur, prenom_utilisateur, role_utilisateur,
            action, table_concernee, id_concerned,
            description, adresse_ip, user_agent, date_action
        FROM archivage
        " . $sql_where . "
        ORDER BY date_action DESC
        LIMIT " . (int)$limite . " OFFSET " . (int)$debut;

try {
    $stmt = $bdd->prepare($sql);
    $stmt->execute($params);
    $evenements = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("[historique_securite] Erreur requête : " . $e->getMessage());
    $evenements = [];
}

// ============================================================
// INDICATEURS DE SÉCURITÉ
// ============================================================
try {
    $indicateurs = $bdd->query("
        SELECT 
            (SELECT COUNT(*) FROM archivage WHERE " . $sql_securite . ") AS total_evenements,
            (SELECT COUNT(*) FROM archivage WHERE " . $sql_securite . " AND date_action >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) AS evenements_24h,
            (SELECT COUNT(DISTINCT id_user) FROM archivage WHERE " . $sql_securite . " AND id_user IS NOT NULL) AS utilisateurs_concernes,
            (SELECT COUNT(*) FROM archivage WHERE description LIKE 'Changement de rôle%') AS changements_role,
            (SELECT COUNT(*) FROM archivage WHERE description = 'Changement de mot de passe') AS changements_mdp,
            (SELECT COUNT(*) FROM archivage WHERE action = 'DELETE') AS suppressions
    ")->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("[historique_securite] Erreur indicateurs : " . $e->getMessage());
    $indicateurs = [
        'total_evenements' => 0, 'evenements_24h' => 0, 'utilisateurs_concernes' => 0,
        'changements_role' => 0, 'changements_mdp' => 0, 'suppressions' => 0,
    ];
}

// ============================================================
// LISTE DES UTILISATEURS POUR LE FILTRE
// ============================================================
$liste_users = [];
try {
    $stmt = $bdd->query("
        SELECT DISTINCT a.id_user, u.nom, u.prenom, u.role
        FROM archivage a
        LEFT JOIN users u ON a.id_user = u.id_user
        WHERE a.id_user IS NOT NULL
        ORDER BY u.nom, u.prenom
    ");
    $liste_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("[historique_securite] Erreur liste users : " . $e->getMessage());
}

// ============================================================
// FONCTIONS UTILITAIRES
// ============================================================
function detecter_navigateur($ua) {
    if (empty($ua)) return '—';
    if (stripos($ua, 'Edg') !== false)     return 'Edge';
    if (stripos($ua, 'OPR') !== false || stripos($ua, 'Opera') !== false) return 'Opera';
    if (stripos($ua, 'Chrome') !== false)  return 'Chrome';
    if (stripos($ua, 'Firefox') !== false) return 'Firefox';
    if (stripos($ua, 'Safari') !== false)  return 'Safari';
    return 'Autre';
}

function classifier_evenement($ligne, $types_evenements) {
    foreach ($types_evenements as $key => $type) {
        // Détection basée sur les mêmes règles que la SQL
        if ($key === 'role_change'   && strpos($ligne['description'] ?? '', 'Changement de rôle') === 0) return $key;
        if ($key === 'mdp_change'    && ($ligne['description'] ?? '') === 'Changement de mot de passe') return $key;
        if ($key === 'user_create'   && $ligne['table_concernee'] === 'users' && $ligne['action'] === 'INSERT') return $key;
        if ($key === 'user_delete'   && $ligne['table_concernee'] === 'users' && $ligne['action'] === 'DELETE') return $key;
        if ($key === 'backup'        && $ligne['table_concernee'] === 'backup') return $key;
        if ($key === 'restauration'  && $ligne['table_concernee'] === 'restauration') return $key;
        if ($key === 'delete'        && $ligne['action'] === 'DELETE') return $key;
    }
    return null;
}

function build_query($extra = []) {
    $q = $_GET;
    foreach ($extra as $k => $v) $q[$k] = $v;
    return http_build_query($q);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historique de sécurité — UniVerse</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Arial, sans-serif; }
        body { background: #eef3f8; color: #1f2937; }

        .page-top-bar { background: #fff; padding: 12px 25px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
        .page-top-bar a { color: #003366; text-decoration: none; font-weight: 600; font-size: 14px; }
        .page-top-bar a:hover { color: #0055aa; text-decoration: underline; }

        .container { width: 95%; max-width: 1400px; margin: 30px auto; }

        .page-header { background: linear-gradient(135deg, #7f1d1d, #dc2626); color: white; text-align: center; padding: 25px; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.2); margin-bottom: 20px; }
        .page-header h1 { font-size: 24px; }
        .page-header p { font-size: 13px; opacity: 0.9; margin-top: 5px; }

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 15px; margin-bottom: 25px; }
        .stat-card { background: white; padding: 18px; border-radius: 14px; box-shadow: 0 4px 10px rgba(0,0,0,.05); text-align: center; border-top: 4px solid #003366; }
        .stat-card.purple { border-top-color: #8b5cf6; }
        .stat-card.orange { border-top-color: #f59e0b; }
        .stat-card.red    { border-top-color: #dc2626; }
        .stat-card.green  { border-top-color: #10b981; }
        .stat-card .value { font-size: 24px; font-weight: 800; color: #003366; }
        .stat-card.purple .value { color: #8b5cf6; }
        .stat-card.orange .value { color: #f59e0b; }
        .stat-card.red    .value { color: #dc2626; }
        .stat-card.green  .value { color: #10b981; }
        .stat-card .label { font-size: 11px; color: #64748b; font-weight: 600; text-transform: uppercase; margin-top: 4px; letter-spacing: 0.3px; }

        .filters-card { background: white; padding: 20px 25px; border-radius: 14px; box-shadow: 0 4px 10px rgba(0,0,0,.05); margin-bottom: 20px; }
        .filters-grid { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr 1fr; gap: 12px; align-items: end; }
        .filter-group { display: flex; flex-direction: column; }
        .filter-group label { font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 5px; letter-spacing: 0.3px; }
        .filter-group input, .filter-group select { padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 13px; outline: none; background: #f8fafc; transition: border-color 0.2s; }
        .filter-group input:focus, .filter-group select:focus { border-color: #0055aa; box-shadow: 0 0 0 3px rgba(0,85,170,.10); background: #fff; }

        .btn { padding: 10px 18px; border: none; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: 6px; transition: all 0.2s; }
        .btn-primary { background: #003366; color: white; }
        .btn-primary:hover { background: #0055aa; }
        .btn-secondary { background: #e5e7eb; color: #111827; }
        .btn-secondary:hover { background: #d1d5db; }

        .results-bar { background: #fff; padding: 12px 22px; border-radius: 12px; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; border: 1px solid #e5e7eb; }
        .results-bar .count { font-weight: 600; font-size: 14px; color: #334155; }
        .results-bar .count strong { color: #003366; font-size: 18px; }
        .filter-tag { display: inline-block; background: #eff6ff; color: #1e40af; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; margin-left: 6px; }

        .table-card { background: #fff; border-radius: 18px; box-shadow: 0 10px 25px rgba(0,0,0,.07); overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 1200px; }
        th { background: #1e293b; color: white; padding: 12px 14px; font-size: 12px; text-align: left; text-transform: uppercase; letter-spacing: 0.3px; }
        td { padding: 12px 14px; border-bottom: 1px solid #eef2f7; font-size: 13px; vertical-align: middle; }
        tr:hover td { background: #f8fbff; }

        .type-badge { display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px; border-radius: 10px; font-weight: 700; font-size: 11px; }
        .role-badge { display: inline-block; padding: 2px 8px; border-radius: 10px; background: #eff6ff; color: #1e40af; font-weight: 600; font-size: 11px; }

        .user-cell { font-weight: 600; color: #1e293b; }
        .user-cell .empty { color: #94a3b8; font-weight: 400; font-style: italic; }

        .description { color: #475569; max-width: 340px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .date-cell { color: #64748b; font-size: 12px; white-space: nowrap; }
        .ip-cell { font-family: monospace; font-size: 12px; color: #475569; }
        .browser-badge { display: inline-block; padding: 2px 8px; border-radius: 10px; background: #f1f5f9; color: #334155; font-weight: 600; font-size: 11px; }

        .empty-state { text-align: center; padding: 60px 20px; }
        .empty-state .icon { font-size: 48px; display: block; margin-bottom: 15px; opacity: 0.5; }
        .empty-state h3 { color: #334155; font-size: 18px; margin-bottom: 8px; }
        .empty-state p { color: #64748b; font-size: 14px; max-width: 500px; margin: 0 auto; line-height: 1.6; }

        .pagination { display: flex; justify-content: center; align-items: center; gap: 6px; margin-top: 20px; flex-wrap: wrap; }
        .pagination a, .pagination span { padding: 8px 14px; border-radius: 8px; font-weight: 600; font-size: 13px; text-decoration: none; }
        .pagination a { background: white; color: #334155; border: 1px solid #e2e8f0; transition: all 0.2s; }
        .pagination a:hover { background: #003366; color: white; border-color: #003366; }
        .pagination .active { background: #003366; color: white; border-color: #003366; }
        .pagination .page-info { background: #f8fafc; color: #64748b; border: 1px solid #e2e8f0; }

        .info-box { background: #eff6ff; padding: 14px 18px; border-radius: 10px; margin-bottom: 20px; border-left: 4px solid #3b82f6; color: #1e3a8a; font-size: 13px; line-height: 1.6; }

        @media (max-width: 1100px) { .filters-grid { grid-template-columns: 1fr 1fr 1fr; } }
        @media (max-width: 700px)  { .filters-grid { grid-template-columns: 1fr; } .results-bar { flex-direction: column; align-items: flex-start; } }
    </style>
</head>
<body>

<div class="page-top-bar">
    <a href="administrateur.php">← Retour au tableau de bord</a>
    <span style="font-size:13px; color:#64748b;">Historique des événements de sécurité</span>
</div>

<div class="container">

    <div class="page-header">
        <h1>🛡️ Historique de sécurité</h1>
        <p>Événements sensibles : rôles, mots de passe, sauvegardes, restaurations, suppressions</p>
    </div>

    <!-- INDICATEURS -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="value"><?= (int)$indicateurs['total_evenements'] ?></div>
            <div class="label">Événements totaux</div>
        </div>
        <div class="stat-card green">
            <div class="value"><?= (int)$indicateurs['evenements_24h'] ?></div>
            <div class="label">Dernières 24h</div>
        </div>
        <div class="stat-card">
            <div class="value"><?= (int)$indicateurs['utilisateurs_concernes'] ?></div>
            <div class="label">Utilisateurs concernés</div>
        </div>
        <div class="stat-card purple">
            <div class="value"><?= (int)$indicateurs['changements_role'] ?></div>
            <div class="label">Changements de rôle</div>
        </div>
        <div class="stat-card orange">
            <div class="value"><?= (int)$indicateurs['changements_mdp'] ?></div>
            <div class="label">Mots de passe</div>
        </div>
        <div class="stat-card red">
            <div class="value"><?= (int)$indicateurs['suppressions'] ?></div>
            <div class="label">Suppressions</div>
        </div>
    </div>

    <?php if ($indicateurs['total_evenements'] == 0): ?>
        <div class="info-box">
            <strong>💡 Information :</strong> Aucun événement de sécurité n'a encore été enregistré.
            Les événements apparaîtront ici automatiquement dès que des actions sensibles seront effectuées
            (changement de rôle, changement de mot de passe, suppression, sauvegarde, restauration).
        </div>
    <?php endif; ?>

    <!-- FILTRES -->
    <div class="filters-card">
        <form method="GET" action="">
            <div class="filters-grid">
                <div class="filter-group">
                    <label>Recherche</label>
                    <input type="text" name="recherche" value="<?= htmlspecialchars($filtre_recherche) ?>" placeholder="Nom, description, IP...">
                </div>
                <div class="filter-group">
                    <label>Utilisateur</label>
                    <select name="id_user">
                        <option value="">— Tous —</option>
                        <?php foreach ($liste_users as $u): ?>
                            <option value="<?= (int)$u['id_user'] ?>" <?= ($filtre_id_user == $u['id_user']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars(trim(($u['prenom'] ?? '') . ' ' . ($u['nom'] ?? '')) ?: 'Utilisateur supprimé') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Type d'événement</label>
                    <select name="type">
                        <option value="">— Tous —</option>
                        <?php foreach ($types_evenements as $key => $t): ?>
                            <option value="<?= htmlspecialchars($key) ?>" <?= ($filtre_type === $key) ? 'selected' : '' ?>>
                                <?= $t['icone'] ?> <?= htmlspecialchars($t['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Date début</label>
                    <input type="date" name="date_debut" value="<?= htmlspecialchars($filtre_date_deb) ?>">
                </div>
                <div class="filter-group">
                    <label>Date fin</label>
                    <input type="date" name="date_fin" value="<?= htmlspecialchars($filtre_date_fin) ?>">
                </div>
            </div>
            <div style="display:flex; gap:10px; margin-top:15px; justify-content:flex-end;">
                <button type="submit" class="btn btn-primary">🔍 Filtrer</button>
                <a href="historique_securite.php" class="btn btn-secondary">✖️ Réinitialiser</a>
            </div>
        </form>
    </div>

    <!-- RESULTATS -->
    <div class="results-bar">
        <div class="count">
            <strong><?= $total ?></strong> événement(s) — page <?= $page ?> / <?= $nb_pages ?>
            <?php if ($filtre_recherche !== ''): ?><span class="filter-tag">🔍 « <?= htmlspecialchars($filtre_recherche) ?> »</span><?php endif; ?>
            <?php if ($filtre_type !== ''): ?><span class="filter-tag"><?= $types_evenements[$filtre_type]['icone'] ?> <?= htmlspecialchars($types_evenements[$filtre_type]['label']) ?></span><?php endif; ?>
        </div>
    </div>

    <!-- TABLEAU -->
    <div class="table-card">
        <?php if (empty($evenements)): ?>
            <div class="empty-state">
                <span class="icon">🛡️</span>
                <h3>Aucun événement de sécurité</h3>
                <p>
                    Aucun événement ne correspond aux critères, ou aucun événement sensible n'a encore été enregistré.<br>
                    Effectuez un changement de rôle ou de mot de passe pour voir apparaître des événements.
                </p>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th style="width:70px;">ID</th>
                        <th style="width:150px;">Type</th>
                        <th>Utilisateur</th>
                        <th style="width:110px;">Rôle</th>
                        <th>Description</th>
                        <th style="width:130px;">Adresse IP</th>
                        <th style="width:120px;">Navigateur</th>
                        <th style="width:150px;">Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($evenements as $ev): ?>
                        <?php
                        $type_key = classifier_evenement($ev, $types_evenements);
                        $type_info = $type_key ? $types_evenements[$type_key] : null;
                        $navigateur = detecter_navigateur($ev['user_agent'] ?? '');
                        $nom_complet = trim(($ev['prenom_utilisateur'] ?? '') . ' ' . ($ev['nom_utilisateur'] ?? ''));
                        ?>
                        <tr>
                            <td>#<?= (int)$ev['id_archivage'] ?></td>
                            <td>
                                <?php if ($type_info): ?>
                                    <span class="type-badge" style="background: <?= htmlspecialchars($type_info['color']) ?>20; color: <?= htmlspecialchars($type_info['color']) ?>;">
                                        <?= $type_info['icone'] ?> <?= htmlspecialchars($type_info['label']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="type-badge" style="background: #f1f5f9; color: #475569;">
                                        📌 <?= htmlspecialchars($ev['action']) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="user-cell">
                                <?php if ($nom_complet !== ''): ?>
                                    <?= htmlspecialchars($nom_complet) ?>
                                <?php else: ?>
                                    <span class="empty">— Non identifié —</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($ev['role_utilisateur'])): ?>
                                    <span class="role-badge"><?= htmlspecialchars($ev['role_utilisateur']) ?></span>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td><div class="description" title="<?= htmlspecialchars($ev['description'] ?? '') ?>"><?= htmlspecialchars($ev['description'] ?? '—') ?></div></td>
                            <td class="ip-cell"><?= htmlspecialchars($ev['adresse_ip'] ?? '—') ?></td>
                            <td><span class="browser-badge">🌐 <?= htmlspecialchars($navigateur) ?></span></td>
                            <td class="date-cell"><?= date('d/m/Y H:i:s', strtotime($ev['date_action'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- PAGINATION -->
    <?php if ($nb_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?<?= build_query(['page' => $page - 1]) ?>">← Précédent</a>
            <?php endif; ?>

            <?php
            $debut_p = max(1, $page - 3);
            $fin_p   = min($nb_pages, $page + 3);
            if ($debut_p > 1) {
                echo '<a href="?' . build_query(['page' => 1]) . '">1</a>';
                if ($debut_p > 2) echo '<span class="page-info">…</span>';
            }
            for ($i = $debut_p; $i <= $fin_p; $i++) {
                if ($i == $page) {
                    echo '<span class="active">' . $i . '</span>';
                } else {
                    echo '<a href="?' . build_query(['page' => $i]) . '">' . $i . '</a>';
                }
            }
            if ($fin_p < $nb_pages) {
                if ($fin_p < $nb_pages - 1) echo '<span class="page-info">…</span>';
                echo '<a href="?' . build_query(['page' => $nb_pages]) . '">' . $nb_pages . '</a>';
            }
            ?>

            <?php if ($page < $nb_pages): ?>
                <a href="?<?= build_query(['page' => $page + 1]) ?>">Suivant →</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</div>
</body>
</html>