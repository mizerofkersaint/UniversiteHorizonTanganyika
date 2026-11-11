<?php
require_once __DIR__ . '/db.php';
// ============================================================
// archives.php — Consultation + restauration de l'historique
// ============================================================
require_once 'auth.php';
requireRole('Administrateur');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['id_user']) || (int)$_SESSION['id_user'] <= 0) {
    http_response_code(403);
    die("Accès refusé.");
}

try {
    $bdd = getDBConnection();
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Erreur de connexion.");
}

require_once 'functions_archivage.php';

/* ============================================================
   CSRF
============================================================ */
if (empty($_SESSION['csrf_archives'])) {
    $_SESSION['csrf_archives'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_archives'];

/* ============================================================
   TRAITEMENT : RESTAURATION
============================================================ */
$flash = ['type' => '', 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restaurer_archive'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($csrf_token, $_POST['csrf_token'])) {
        $flash = ['type' => 'error', 'message' => "❌ Jeton CSRF invalide."];
    } else {
        $id_arch = (int)($_POST['id_archivage'] ?? 0);
        $res = restaurerArchive($bdd, $id_arch, getUserIdEnSession($bdd));
        $flash = [
            'type'    => $res['success'] ? 'success' : 'error',
            'message' => $res['message'],
        ];
    }
}

/* ============================================================
   FILTRES (GET)
============================================================ */
$filtre_id_user  = isset($_GET['id_user']) && $_GET['id_user'] !== '' ? (int)$_GET['id_user'] : 0;
$filtre_action   = isset($_GET['action']) ? trim($_GET['action']) : '';
$filtre_table    = isset($_GET['table']) ? trim($_GET['table']) : '';
$filtre_date_deb = isset($_GET['date_debut']) ? trim($_GET['date_debut']) : '';
$filtre_date_fin = isset($_GET['date_fin']) ? trim($_GET['date_fin']) : '';
$filtre_search   = isset($_GET['recherche']) ? trim($_GET['recherche']) : '';
$page_courante   = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limite          = 20;

$actions_valides = ['INSERT', 'UPDATE', 'DELETE', 'SELECT'];
if (!in_array($filtre_action, $actions_valides, true)) {
    $filtre_action = '';
}

/* ============================================================
   RÉCUPÉRATION
============================================================ */
$filtres = [];
if ($filtre_id_user > 0)      { $filtres['id_user']    = $filtre_id_user; }
if ($filtre_action !== '')    { $filtres['action']     = $filtre_action; }
if ($filtre_table !== '')     { $filtres['table']      = $filtre_table; }
if ($filtre_date_deb !== '')  { $filtres['date_debut'] = $filtre_date_deb; }
if ($filtre_date_fin !== '')  { $filtres['date_fin']   = $filtre_date_fin; }
if ($filtre_search !== '')    { $filtres['recherche']  = $filtre_search; }

$historique = getHistoriqueArchivage($bdd, $filtres, $page_courante, $limite);
$total = $historique['total'];
$pages = $historique['pages'];
$data  = $historique['data'];

/* Listes pour les selects */
$liste_utilisateurs = [];
try {
    $stmt = $bdd->query("
        SELECT DISTINCT a.id_user, u.nom, u.prenom, u.role
        FROM archivage a
        LEFT JOIN users u ON a.id_user = u.id_user
        WHERE a.id_user IS NOT NULL
        ORDER BY u.nom, u.prenom
    ");
    $liste_utilisateurs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$liste_tables = [];
try {
    $stmt = $bdd->query("
        SELECT DISTINCT table_concernee
        FROM archivage
        WHERE table_concernee IS NOT NULL AND table_concernee != ''
        ORDER BY table_concernee
    ");
    $liste_tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

function build_query($extra = []) {
    $q = $_GET;
    foreach ($extra as $k => $v) $q[$k] = $v;
    return http_build_query($q);
}

/* Helper : une action peut-elle être restaurée ? */
function action_restaurable($action) {
    return in_array(strtoupper($action), ['INSERT', 'UPDATE', 'DELETE'], true);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archives — UniVerse</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Arial, sans-serif; }
        body { background: #eef3f8; color: #1f2937; }
        .page-top-bar { background: #fff; padding: 12px 25px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
        .page-top-bar a { color: #003366; text-decoration: none; font-weight: 600; font-size: 14px; }
        .page-top-bar a:hover { color: #0055aa; text-decoration: underline; }
        .container { width: 95%; max-width: 1300px; margin: 30px auto; }

        .page-header { background: linear-gradient(135deg, #003366, #0055aa); color: white; text-align: center; padding: 25px; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.2); margin-bottom: 20px; }
        .page-header h1 { font-size: 24px; }
        .page-header p { font-size: 13px; opacity: 0.9; margin-top: 5px; }

        .flash { padding: 14px 20px; border-radius: 10px; margin-bottom: 20px; font-weight: 600; font-size: 14px; animation: slideDown 0.3s ease; }
        .flash.success { background: #d1fae5; color: #065f46; border-left: 4px solid #10b981; }
        .flash.error { background: #fee2e2; color: #991b1b; border-left: 4px solid #dc2626; }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-8px);} to {opacity:1;transform:translateY(0);} }

        .filters-card { background: #fff; padding: 20px 25px; border-radius: 14px; box-shadow: 0 4px 10px rgba(0,0,0,.05); margin-bottom: 20px; }
        .filters-grid { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr 1fr auto; gap: 12px; align-items: end; }
        .filter-group { display: flex; flex-direction: column; }
        .filter-group label { font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 5px; letter-spacing: 0.3px; }
        .filter-group input, .filter-group select { padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 13px; outline: none; background: #f8fafc; transition: border-color 0.2s; }
        .filter-group input:focus, .filter-group select:focus { border-color: #0055aa; box-shadow: 0 0 0 3px rgba(0,85,170,.10); background: #fff; }

        .btn { padding: 10px 18px; border: none; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: 6px; transition: all 0.2s; }
        .btn-primary { background: #003366; color: white; }
        .btn-primary:hover { background: #0055aa; }
        .btn-secondary { background: #e5e7eb; color: #111827; }
        .btn-secondary:hover { background: #d1d5db; }

        .stats-bar { background: #fff; padding: 14px 22px; border-radius: 12px; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .stats-bar .count { font-weight: 600; font-size: 14px; color: #334155; }
        .stats-bar .count strong { color: #003366; font-size: 18px; }
        .filter-tag { display: inline-block; background: #eff6ff; color: #1e40af; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; }

        .table-card { background: #fff; border-radius: 18px; box-shadow: 0 10px 25px rgba(0,0,0,.07); overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 1100px; }
        th { background: #003366; color: white; padding: 12px 14px; font-size: 12px; text-align: left; text-transform: uppercase; letter-spacing: 0.3px; }
        td { padding: 12px 14px; border-bottom: 1px solid #eef2f7; font-size: 13px; vertical-align: middle; }
        tr:hover td { background: #f8fbff; }

        .badge { display: inline-block; padding: 3px 12px; border-radius: 12px; font-weight: 700; font-size: 11px; text-transform: uppercase; }
        .badge-INSERT { background: #d1fae5; color: #065f46; }
        .badge-UPDATE { background: #fef3c7; color: #92400e; }
        .badge-DELETE { background: #fee2e2; color: #991b1b; }
        .badge-SELECT { background: #dbeafe; color: #1e40af; }

        .role-badge { display: inline-block; padding: 2px 8px; border-radius: 10px; background: #f1f5f9; color: #475569; font-weight: 600; font-size: 11px; }
        .table-name { display: inline-block; padding: 2px 10px; border-radius: 10px; background: #f8fafc; color: #334155; font-weight: 600; font-size: 12px; font-family: monospace; }
        .description { color: #475569; max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .date-cell { color: #64748b; font-size: 12px; white-space: nowrap; }
        .user-cell { font-weight: 600; color: #1e293b; }
        .user-cell .empty { color: #94a3b8; font-weight: 400; font-style: italic; }

        .empty { text-align: center; color: #94a3b8; padding: 40px; font-style: italic; }

        .btn-restore {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 12px;
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 11.5px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
        }
        .btn-restore:hover {
            background: linear-gradient(135deg, #4f46e5, #4338ca);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(79,70,229,0.3);
        }
        .btn-restore.disabled {
            background: #f1f5f9;
            color: #94a3b8;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .pagination { display: flex; justify-content: center; align-items: center; gap: 6px; margin-top: 20px; flex-wrap: wrap; }
        .pagination a, .pagination span { padding: 8px 14px; border-radius: 8px; font-weight: 600; font-size: 13px; text-decoration: none; }
        .pagination a { background: white; color: #334155; border: 1px solid #e2e8f0; transition: all 0.2s; }
        .pagination a:hover { background: #003366; color: white; border-color: #003366; }
        .pagination .active { background: #003366; color: white; border-color: #003366; }
        .pagination .page-info { background: #f8fafc; color: #64748b; border: 1px solid #e2e8f0; }

        @media (max-width: 1100px) { .filters-grid { grid-template-columns: 1fr 1fr 1fr; } }
        @media (max-width: 700px)  { .filters-grid { grid-template-columns: 1fr; } .stats-bar { flex-direction: column; align-items: flex-start; } }
    </style>
</head>
<body>

<div class="page-top-bar">
    <a href="administrateur.php">← Retour au tableau de bord</a>
    <span style="font-size:13px; color:#64748b;">Journal d'activité — Restauration possible</span>
</div>

<div class="container">

    <div class="page-header">
        <h1>🗃️ Archives du système</h1>
        <p>Historique complet + restauration des enregistrements (INSERT / UPDATE / DELETE)</p>
    </div>

    <?php if (!empty($flash['message'])): ?>
        <div class="flash <?= htmlspecialchars($flash['type']); ?>"><?= $flash['message']; ?></div>
    <?php endif; ?>

    <!-- FILTRES -->
    <div class="filters-card">
        <form method="GET" action="">
            <div class="filters-grid">
                <div class="filter-group">
                    <label>Recherche libre</label>
                    <input type="text" name="recherche" value="<?= htmlspecialchars($filtre_search) ?>" placeholder="Nom, description...">
                </div>
                <div class="filter-group">
                    <label>Utilisateur</label>
                    <select name="id_user">
                        <option value="">— Tous —</option>
                        <?php foreach ($liste_utilisateurs as $u): ?>
                            <option value="<?= (int)$u['id_user'] ?>" <?= ($filtre_id_user == $u['id_user']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($u['nom'] . ' ' . $u['prenom']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Action</label>
                    <select name="action">
                        <option value="">— Toutes —</option>
                        <option value="INSERT" <?= ($filtre_action === 'INSERT') ? 'selected' : '' ?>>INSERT</option>
                        <option value="UPDATE" <?= ($filtre_action === 'UPDATE') ? 'selected' : '' ?>>UPDATE</option>
                        <option value="DELETE" <?= ($filtre_action === 'DELETE') ? 'selected' : '' ?>>DELETE</option>
                        <option value="SELECT" <?= ($filtre_action === 'SELECT') ? 'selected' : '' ?>>SELECT</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Table</label>
                    <select name="table">
                        <option value="">— Toutes —</option>
                        <?php foreach ($liste_tables as $t): ?>
                            <option value="<?= htmlspecialchars($t) ?>" <?= ($filtre_table === $t) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($t) ?>
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
                <a href="archives.php" class="btn btn-secondary">✖️ Réinitialiser</a>
            </div>
        </form>
    </div>

    <!-- STATS -->
    <div class="stats-bar">
        <div class="count">
            <strong><?= $total ?></strong> archive(s) — page <?= $page_courante ?> / <?= $pages ?>
        </div>
        <div style="display:flex; gap:8px; flex-wrap:wrap;">
            <?php if ($filtre_id_user > 0): ?><span class="filter-tag">👤 Utilisateur</span><?php endif; ?>
            <?php if ($filtre_action !== ''): ?><span class="filter-tag">⚡ <?= htmlspecialchars($filtre_action) ?></span><?php endif; ?>
            <?php if ($filtre_table !== ''): ?><span class="filter-tag">📋 <?= htmlspecialchars($filtre_table) ?></span><?php endif; ?>
            <?php if ($filtre_search !== ''): ?><span class="filter-tag">🔍 « <?= htmlspecialchars($filtre_search) ?> »</span><?php endif; ?>
        </div>
    </div>

    <!-- TABLEAU -->
    <div class="table-card">
        <?php if (empty($data)): ?>
            <div class="empty">📭 Aucune archive ne correspond à ces critères.</div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th style="width:60px;">ID</th>
                        <th>Utilisateur</th>
                        <th style="width:100px;">Rôle</th>
                        <th style="width:100px;">Action</th>
                        <th style="width:130px;">Table</th>
                        <th>Description</th>
                        <th style="width:150px;">Date</th>
                        <th style="width:120px;">IP</th>
                        <th style="width:110px;">Restauration</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data as $ligne):
                        $can_restore = action_restaurable($ligne['action']);
                        $action_label = strtoupper($ligne['action']);
                    ?>
                        <tr>
                            <td>#<?= (int)$ligne['id_archivage'] ?></td>
                            <td class="user-cell">
                                <?php if (!empty($ligne['nom_utilisateur']) || !empty($ligne['prenom_utilisateur'])): ?>
                                    <?= htmlspecialchars(trim(($ligne['prenom_utilisateur'] ?? '') . ' ' . ($ligne['nom_utilisateur'] ?? ''))) ?>
                                <?php else: ?>
                                    <span class="empty">— Non identifié —</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($ligne['role_utilisateur'])): ?>
                                    <span class="role-badge"><?= htmlspecialchars($ligne['role_utilisateur']) ?></span>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-<?= htmlspecialchars($action_label) ?>">
                                    <?= htmlspecialchars($action_label) ?>
                                </span>
                            </td>
                            <td><span class="table-name"><?= htmlspecialchars($ligne['table_concernee']) ?></span></td>
                            <td>
                                <div class="description" title="<?= htmlspecialchars($ligne['description'] ?? '') ?>">
                                    <?= htmlspecialchars($ligne['description'] ?? '—') ?>
                                </div>
                            </td>
                            <td class="date-cell"><?= date('d/m/Y H:i:s', strtotime($ligne['date_action'])) ?></td>
                            <td class="date-cell"><?= htmlspecialchars($ligne['adresse_ip'] ?? '—') ?></td>
                            <td>
                                <?php if ($can_restore): ?>
                                    <form method="POST" action="archives.php" style="display:inline;"
                                          onsubmit="return confirmRestore('<?= htmlspecialchars($action_label) ?>', '<?= htmlspecialchars($ligne['table_concernee']) ?>', <?= (int)$ligne['id_concerned'] ?>);">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                        <input type="hidden" name="id_archivage" value="<?= (int)$ligne['id_archivage'] ?>">
                                        <button type="submit" name="restaurer_archive" class="btn-restore">
                                            ↩️ Restaurer
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="btn-restore disabled" title="Action non restaurable">
                                        —
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- PAGINATION -->
    <?php if ($pages > 1): ?>
        <div class="pagination">
            <?php if ($page_courante > 1): ?>
                <a href="?<?= build_query(['page' => $page_courante - 1]) ?>">← Précédent</a>
            <?php endif; ?>

            <?php
            $debut = max(1, $page_courante - 3);
            $fin = min($pages, $page_courante + 3);
            if ($debut > 1) {
                echo '<a href="?' . build_query(['page' => 1]) . '">1</a>';
                if ($debut > 2) echo '<span class="page-info">…</span>';
            }
            for ($i = $debut; $i <= $fin; $i++) {
                if ($i == $page_courante) {
                    echo '<span class="active">' . $i . '</span>';
                } else {
                    echo '<a href="?' . build_query(['page' => $i]) . '">' . $i . '</a>';
                }
            }
            if ($fin < $pages) {
                if ($fin < $pages - 1) echo '<span class="page-info">…</span>';
                echo '<a href="?' . build_query(['page' => $pages]) . '">' . $pages . '</a>';
            }
            ?>

            <?php if ($page_courante < $pages): ?>
                <a href="?<?= build_query(['page' => $page_courante + 1]) ?>">Suivant →</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</div>

<script>
function confirmRestore(action, table, id) {
    let msg = '';
    if (action === 'INSERT') {
        msg = "⚠️ Cette action va SUPPRIMER l'enregistrement #" + id + " de la table « " + table + " » créé par cette action INSERT.\n\nContinuer ?";
    } else if (action === 'UPDATE') {
        msg = "⚠️ Cette action va RESTAURER l'enregistrement #" + id + " de la table « " + table + " » à sa valeur ANTÉRIEURE (avant la modification).\n\nContinuer ?";
    } else if (action === 'DELETE') {
        msg = "⚠️ Cette action va RÉ-INSÉRER l'enregistrement #" + id + " supprimé de la table « " + table + " » avec ses anciennes données.\n\nContinuer ?";
    } else {
        return false;
    }
    return confirm(msg);
}
</script>

</body>
</html>