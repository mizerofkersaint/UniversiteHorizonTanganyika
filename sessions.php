<?php
require_once __DIR__ . '/db.php';
// ============================================================
// sessions.php — Surveillance des sessions utilisateur
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
    error_log("[sessions] Erreur connexion : " . $e->getMessage());
    die("Erreur de connexion à la base de données.");
}

// ============================================================
// RÉCUPÉRATION DES FILTRES
// ============================================================
$filtre_recherche = isset($_GET['recherche']) ? trim($_GET['recherche']) : '';
$filtre_role      = isset($_GET['role']) ? trim($_GET['role']) : '';
$filtre_statut    = isset($_GET['statut']) ? trim($_GET['statut']) : '';
$filtre_date_deb  = isset($_GET['date_debut']) ? trim($_GET['date_debut']) : '';
$filtre_date_fin  = isset($_GET['date_fin']) ? trim($_GET['date_fin']) : '';

$roles_valides = ['Administrateur', 'Professeur', 'Comptable', 'Etudiant', 'Lecteur', 'Doyen'];
if (!in_array($filtre_role, $roles_valides, true)) {
    $filtre_role = '';
}

$statuts_valides = ['Connecte', 'Deconnecte'];
if (!in_array($filtre_statut, $statuts_valides, true)) {
    $filtre_statut = '';
}

// ============================================================
// CONSTRUCTION DE LA REQUÊTE
// ============================================================
$where = [];
$params = [];

// La jointure LEFT JOIN permet d'afficher aussi les sessions orphelines (id_user NULL)
$sql_base = "
    FROM user_sessions s
    LEFT JOIN users u ON s.id_user = u.id_user
    WHERE 1=1
";

if ($filtre_recherche !== '') {
    $where[] = "(u.nom LIKE ? OR u.prenom LIKE ? OR u.username LIKE ? OR s.adresse_ip LIKE ?)";
    $like = '%' . $filtre_recherche . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
if ($filtre_role !== '') {
    $where[] = "u.role = ?";
    $params[] = $filtre_role;
}
if ($filtre_statut !== '') {
    $where[] = "s.statut = ?";
    $params[] = $filtre_statut;
}
if ($filtre_date_deb !== '') {
    $where[] = "s.date_connexion >= ?";
    $params[] = $filtre_date_deb . ' 00:00:00';
}
if ($filtre_date_fin !== '') {
    $where[] = "s.date_connexion <= ?";
    $params[] = $filtre_date_fin . ' 23:59:59';
}

$sql_where = '';
if (count($where) > 0) {
    $sql_where = " AND " . implode(" AND ", $where);
}

// ============================================================
// PAGINATION
// ============================================================
$limite = 15;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

// Total
try {
    $stmt = $bdd->prepare("SELECT COUNT(*) AS total " . $sql_base . $sql_where);
    $stmt->execute($params);
    $total = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];
} catch (Exception $e) {
    error_log("[sessions] Erreur count : " . $e->getMessage());
    $total = 0;
}

$nb_pages = max(1, (int)ceil($total / $limite));
if ($page > $nb_pages) {
    $page = $nb_pages;
}
$debut = ($page - 1) * $limite;

// Requête principale
$sql = "SELECT 
            s.id_session,
            s.id_user,
            s.statut,
            s.adresse_ip,
            s.user_agent,
            s.date_connexion,
            s.date_deconnexion,
            s.derniere_activite,
            u.nom,
            u.prenom,
            u.username,
            u.role
        " . $sql_base . $sql_where . "
        ORDER BY s.derniere_activite DESC
        LIMIT " . (int)$limite . " OFFSET " . (int)$debut;

try {
    $stmt = $bdd->prepare($sql);
    $stmt->execute($params);
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("[sessions] Erreur requête : " . $e->getMessage());
    $sessions = [];
}

// ============================================================
// STATISTIQUES GLOBALES
// ============================================================
try {
    $stats = $bdd->query("
        SELECT 
            COUNT(*) AS total,
            SUM(CASE WHEN statut = 'Connecte' THEN 1 ELSE 0 END) AS connectes,
            SUM(CASE WHEN statut = 'Deconnecte' THEN 1 ELSE 0 END) AS deconnectes,
            COUNT(DISTINCT id_user) AS utilisateurs_uniques
        FROM user_sessions
    ")->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("[sessions] Erreur stats : " . $e->getMessage());
    $stats = ['total' => 0, 'connectes' => 0, 'deconnectes' => 0, 'utilisateurs_uniques' => 0];
}

// ============================================================
// FONCTION UTILITAIRE : Détection navigateur
// ============================================================
function detecter_navigateur($user_agent) {
    if (empty($user_agent)) return 'Inconnu';
    if (stripos($user_agent, 'Edg') !== false)        return 'Microsoft Edge';
    if (stripos($user_agent, 'OPR') !== false ||
        stripos($user_agent, 'Opera') !== false)      return 'Opera';
    if (stripos($user_agent, 'Chrome') !== false)     return 'Google Chrome';
    if (stripos($user_agent, 'Firefox') !== false)    return 'Mozilla Firefox';
    if (stripos($user_agent, 'Safari') !== false)     return 'Safari';
    if (stripos($user_agent, 'MSIE') !== false ||
        stripos($user_agent, 'Trident') !== false)    return 'Internet Explorer';
    return 'Autre';
}

// ============================================================
// FONCTION UTILITAIRE : URL avec filtres
// ============================================================
function build_query($extra = []) {
    $q = $_GET;
    foreach ($extra as $k => $v) {
        $q[$k] = $v;
    }
    return http_build_query($q);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Surveillance des sessions — UniVerse</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Arial, sans-serif; }
        body { background: #eef3f8; color: #1f2937; }

        .page-top-bar { background: #fff; padding: 12px 25px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
        .page-top-bar a { color: #003366; text-decoration: none; font-weight: 600; font-size: 14px; }
        .page-top-bar a:hover { color: #0055aa; text-decoration: underline; }

        .container { width: 95%; max-width: 1400px; margin: 30px auto; }

        .page-header { background: linear-gradient(135deg, #003366, #0055aa); color: white; text-align: center; padding: 25px; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.2); margin-bottom: 20px; }
        .page-header h1 { font-size: 24px; }
        .page-header p { font-size: 13px; opacity: 0.9; margin-top: 5px; }

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 25px; }
        .stat-card { background: white; padding: 20px; border-radius: 14px; box-shadow: 0 4px 10px rgba(0,0,0,.05); text-align: center; border-top: 4px solid #003366; }
        .stat-card.green  { border-top-color: #10b981; }
        .stat-card.orange { border-top-color: #f59e0b; }
        .stat-card.red    { border-top-color: #dc2626; }
        .stat-card .value { font-size: 28px; font-weight: 800; color: #003366; }
        .stat-card.green  .value { color: #10b981; }
        .stat-card.orange .value { color: #f59e0b; }
        .stat-card.red    .value { color: #dc2626; }
        .stat-card .label { font-size: 12px; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px; margin-top: 4px; }

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
        th { background: #003366; color: white; padding: 12px 14px; font-size: 12px; text-align: left; text-transform: uppercase; letter-spacing: 0.3px; }
        td { padding: 12px 14px; border-bottom: 1px solid #eef2f7; font-size: 13px; vertical-align: middle; }
        tr:hover td { background: #f8fbff; }

        .badge { display: inline-block; padding: 3px 12px; border-radius: 12px; font-weight: 700; font-size: 11px; text-transform: uppercase; }
        .badge-connecte   { background: #d1fae5; color: #065f46; }
        .badge-deconnecte { background: #e5e7eb; color: #4b5563; }

        .role-badge { display: inline-block; padding: 2px 8px; border-radius: 10px; background: #eff6ff; color: #1e40af; font-weight: 600; font-size: 11px; }

        .user-cell { font-weight: 600; color: #1e293b; }
        .user-cell .username { display: block; font-family: monospace; font-size: 12px; color: #64748b; font-weight: 400; margin-top: 2px; }

        .date-cell { color: #64748b; font-size: 12px; white-space: nowrap; }
        .date-cell.empty { color: #cbd5e1; font-style: italic; }

        .browser-badge { display: inline-block; padding: 2px 10px; border-radius: 10px; background: #f1f5f9; color: #334155; font-weight: 600; font-size: 11px; }
        .ip-cell { font-family: monospace; font-size: 12px; color: #475569; }

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

        .info-box { background: #fef3c7; padding: 14px 18px; border-radius: 10px; margin-bottom: 20px; border-left: 4px solid #f59e0b; color: #78350f; font-size: 13px; line-height: 1.6; }

        @media (max-width: 1100px) { .filters-grid { grid-template-columns: 1fr 1fr 1fr; } }
        @media (max-width: 700px)  { .filters-grid { grid-template-columns: 1fr; } .results-bar { flex-direction: column; align-items: flex-start; } }
    </style>
</head>
<body>

<div class="page-top-bar">
    <a href="administrateur.php">← Retour au tableau de bord</a>
    <span style="font-size:13px; color:#64748b;">Surveillance des sessions</span>
</div>

<div class="container">

    <div class="page-header">
        <h1>🔍 Surveillance des sessions</h1>
        <p>Suivi des connexions et des déconnexions</p>
    </div>

    <!-- Message explicatif si table vide -->
    <?php if ($stats['total'] == 0): ?>
        <div class="info-box">
            <strong>💡 Information :</strong> La table <code>user_sessions</code> est actuellement vide.
            Les sessions seront enregistrées dès que le système de connexion (<code>login.php</code>) sera mis en place.
            Cette page est prête à afficher les données.
        </div>
    <?php endif; ?>

    <!-- STATISTIQUES -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="value"><?= (int)$stats['total'] ?></div>
            <div class="label">Sessions totales</div>
        </div>
        <div class="stat-card green">
            <div class="value"><?= (int)$stats['connectes'] ?></div>
            <div class="label">Connectés</div>
        </div>
        <div class="stat-card orange">
            <div class="value"><?= (int)$stats['deconnectes'] ?></div>
            <div class="label">Déconnectés</div>
        </div>
        <div class="stat-card red">
            <div class="value"><?= (int)$stats['utilisateurs_uniques'] ?></div>
            <div class="label">Utilisateurs uniques</div>
        </div>
    </div>

    <!-- FILTRES -->
    <div class="filters-card">
        <form method="GET" action="">
            <div class="filters-grid">
                <div class="filter-group">
                    <label>Recherche (nom, username, IP)</label>
                    <input type="text" name="recherche" value="<?= htmlspecialchars($filtre_recherche) ?>" placeholder="Rechercher...">
                </div>
                <div class="filter-group">
                    <label>Rôle</label>
                    <select name="role">
                        <option value="">— Tous —</option>
                        <?php foreach ($roles_valides as $r): ?>
                            <option value="<?= htmlspecialchars($r) ?>" <?= ($filtre_role === $r) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($r) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Statut</label>
                    <select name="statut">
                        <option value="">— Tous —</option>
                        <option value="Connecte"   <?= ($filtre_statut === 'Connecte')   ? 'selected' : '' ?>>Connecté</option>
                        <option value="Deconnecte" <?= ($filtre_statut === 'Deconnecte') ? 'selected' : '' ?>>Déconnecté</option>
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
                <a href="sessions.php" class="btn btn-secondary">✖️ Réinitialiser</a>
            </div>
        </form>
    </div>

    <!-- RESULTATS -->
    <div class="results-bar">
        <div class="count">
            <strong><?= $total ?></strong> session(s) trouvée(s) — page <?= $page ?> / <?= $nb_pages ?>
            <?php if ($filtre_recherche !== ''): ?><span class="filter-tag">🔍 « <?= htmlspecialchars($filtre_recherche) ?> »</span><?php endif; ?>
            <?php if ($filtre_role !== ''): ?><span class="filter-tag">🎭 <?= htmlspecialchars($filtre_role) ?></span><?php endif; ?>
            <?php if ($filtre_statut !== ''): ?><span class="filter-tag">⚡ <?= htmlspecialchars($filtre_statut) ?></span><?php endif; ?>
        </div>
    </div>

    <!-- TABLEAU -->
    <div class="table-card">
        <?php if (empty($sessions)): ?>
            <div class="empty-state">
                <span class="icon">📭</span>
                <h3>Aucune session enregistrée</h3>
                <p>
                    Aucune session ne correspond aux critères, ou la table <code>user_sessions</code> est encore vide.<br>
                    Les sessions apparaîtront ici dès que le système de connexion sera en place.
                </p>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th style="width:70px;">ID</th>
                        <th>Utilisateur</th>
                        <th style="width:120px;">Rôle</th>
                        <th style="width:110px;">Statut</th>
                        <th style="width:130px;">Adresse IP</th>
                        <th style="width:150px;">Navigateur</th>
                        <th style="width:150px;">Connexion</th>
                        <th style="width:150px;">Déconnexion</th>
                        <th style="width:150px;">Dernière activité</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sessions as $s): ?>
                        <?php
                        $statut_class = ($s['statut'] === 'Connecte') ? 'badge-connecte' : 'badge-deconnecte';
                        $statut_label = ($s['statut'] === 'Connecte') ? '🟢 Connecté' : '⚪ Déconnecté';
                        $navigateur = detecter_navigateur($s['user_agent'] ?? '');
                        $nom_complet = trim(($s['prenom'] ?? '') . ' ' . ($s['nom'] ?? ''));
                        ?>
                        <tr>
                            <td>#<?= (int)$s['id_session'] ?></td>
                            <td class="user-cell">
                                <?= htmlspecialchars($nom_complet ?: '— Utilisateur supprimé —') ?>
                                <?php if (!empty($s['username'])): ?>
                                    <span class="username">@<?= htmlspecialchars($s['username']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($s['role'])): ?>
                                    <span class="role-badge"><?= htmlspecialchars($s['role']) ?></span>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td><span class="badge <?= $statut_class ?>"><?= $statut_label ?></span></td>
                            <td class="ip-cell"><?= htmlspecialchars($s['adresse_ip'] ?? '—') ?></td>
                            <td>
                                <?php if ($navigateur !== 'Inconnu'): ?>
                                    <span class="browser-badge">🌐 <?= htmlspecialchars($navigateur) ?></span>
                                <?php else: ?>
                                    <span class="date-cell empty">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="date-cell <?= empty($s['date_connexion']) ? 'empty' : '' ?>">
                                <?= !empty($s['date_connexion']) ? date('d/m/Y H:i:s', strtotime($s['date_connexion'])) : 'Non renseigné' ?>
                            </td>
                            <td class="date-cell <?= empty($s['date_deconnexion']) ? 'empty' : '' ?>">
                                <?= !empty($s['date_deconnexion']) ? date('d/m/Y H:i:s', strtotime($s['date_deconnexion'])) : 'Non renseigné' ?>
                            </td>
                            <td class="date-cell"><?= !empty($s['derniere_activite']) ? date('d/m/Y H:i:s', strtotime($s['derniere_activite'])) : '—' ?></td>
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