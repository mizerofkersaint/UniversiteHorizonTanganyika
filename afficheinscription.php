<?php
require_once __DIR__ . '/db.php';
// ============================================================
// afficheinscription.php — Liste des inscriptions
// ✅ Support iframe + filtres + modifier/supprimer
// ============================================================
require_once 'auth.php';
requireRole(['Administrateur', 'Doyen', 'Agent']);

try {
    $bdd = getDBConnection();
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Erreur : " . $e->getMessage());
}

$is_iframe = isset($_GET['iframe']) && $_GET['iframe'] === '1';
$iframe_query = $is_iframe ? 'iframe=1&' : '';

/* ============================================================
   SUPPRESSION
============================================================ */
if (isset($_GET['supp']) && !empty($_GET['supp'])) {
    $idSupp = (int)$_GET['supp'];
    try {
        $stmt = $bdd->prepare("DELETE FROM inscriptions WHERE id_inscription = ?");
        $stmt->execute([$idSupp]);
        $redirect = 'afficheinscription.php?success=suppression' . ($is_iframe ? '&iframe=1' : '');
        header("Location: $redirect");
        exit();
    } catch (Exception $e) {
        $erreur_supp = "Impossible de supprimer cette inscription.";
    }
}

/* ============================================================
   FILTRES
============================================================ */
$recherche       = isset($_GET['recherche'])  ? trim($_GET['recherche'])  : '';
$filtre_dept     = isset($_GET['dept'])       ? (int)$_GET['dept']        : 0;
$filtre_classe   = isset($_GET['classe'])     ? (int)$_GET['classe']      : 0;
$filtre_date_deb = isset($_GET['date_deb'])   ? trim($_GET['date_deb'])   : '';
$filtre_date_fin = isset($_GET['date_fin'])   ? trim($_GET['date_fin'])   : '';
$success         = isset($_GET['success'])    ? $_GET['success']          : '';
$erreur_supp     = $erreur_supp ?? '';

/* Données selects */
$departements = $bdd->query("SELECT id_dept, nom_dept, faculte FROM departements ORDER BY nom_dept")->fetchAll(PDO::FETCH_ASSOC);
$classes = [];
if ($filtre_dept > 0) {
    $stmt = $bdd->prepare("SELECT id_classe, niveau FROM classes WHERE id_dept = ? ORDER BY niveau");
    $stmt->execute([$filtre_dept]);
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/* Requête WHERE */
$where = [];
$params = [];
if (!empty($recherche)) {
    $where[] = "(u.nom LIKE ? OR u.prenom LIKE ? OR e.matricule LIKE ?)";
    $s = "%" . $recherche . "%";
    $params[] = $s; $params[] = $s; $params[] = $s;
}
if ($filtre_dept > 0)     { $where[] = "d.id_dept = ?";   $params[] = $filtre_dept; }
if ($filtre_classe > 0)   { $where[] = "cl.id_classe = ?"; $params[] = $filtre_classe; }
if (!empty($filtre_date_deb)) { $where[] = "i.date_inscription >= ?"; $params[] = $filtre_date_deb; }
if (!empty($filtre_date_fin)) { $where[] = "i.date_inscription <= ?"; $params[] = $filtre_date_fin; }

$sqlWhere = $where ? " WHERE " . implode(" AND ", $where) : "";

/* Pagination */
$limite = 15;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$debut = ($page - 1) * $limite;

$stmtTotal = $bdd->prepare("
    SELECT COUNT(*) FROM inscriptions i
    INNER JOIN etudiants e ON i.id_etudiant = e.id_etudiant
    INNER JOIN users u ON e.id_user = u.id_user
    INNER JOIN classes cl ON e.id_classe = cl.id_classe
    INNER JOIN departements d ON cl.id_dept = d.id_dept
    $sqlWhere
");
$stmtTotal->execute($params);
$total = (int)$stmtTotal->fetchColumn();
$nbPages = max(1, (int)ceil($total / $limite));

/* Données */
$stmt = $bdd->prepare("
    SELECT i.id_inscription, i.date_inscription,
           e.id_etudiant, e.matricule,
           u.nom, u.prenom,
           cl.id_classe, cl.niveau,
           d.id_dept, d.nom_dept, d.faculte
    FROM inscriptions i
    INNER JOIN etudiants e ON i.id_etudiant = e.id_etudiant
    INNER JOIN users u ON e.id_user = u.id_user
    INNER JOIN classes cl ON e.id_classe = cl.id_classe
    INNER JOIN departements d ON cl.id_dept = d.id_dept
    $sqlWhere
    ORDER BY i.date_inscription DESC
    LIMIT $debut, $limite
");
$stmt->execute($params);
$inscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

function buildQuery($extra = []) {
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
    <title>Inscriptions — UniVerse</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Arial, sans-serif; }
        body { background: #eef3f8; min-height: 100vh; color: #1e293b; }
        body.iframe-mode { background: #f8fafc; }

        .main-container { padding: 30px 20px; }
        .main-container.in-iframe { padding: 18px; }
        .page-inner { max-width: 1300px; margin: 0 auto; width: 100%; }

        /* IFRAME TOPBAR */
        .iframe-topbar {
            display: flex; justify-content: space-between; align-items: center;
            padding: 14px 20px; background: white; border-bottom: 1px solid #e2e8f0;
            margin-bottom: 18px; border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05); flex-wrap: wrap; gap: 12px;
        }
        .iframe-topbar-left { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }

        .btn-back {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 9px 16px; background: #f1f5f9; color: #475569;
            text-decoration: none; border-radius: 8px; font-size: 13px;
            font-weight: 600; border: 1px solid #e2e8f0; transition: all 0.2s; white-space: nowrap;
        }
        .btn-back:hover { background: #e2e8f0; color: #1e293b; }
        .btn-nav {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 9px 16px; background: white; color: #475569;
            text-decoration: none; border-radius: 8px; font-size: 13px;
            font-weight: 600; border: 1px solid #e2e8f0; transition: all 0.2s; white-space: nowrap;
        }
        .btn-nav:hover { background: #f0fdf4; color: #065f46; border-color: #10b981; }
        .btn-nav.active { background: #10b981; color: white; border-color: #10b981; }

        /* PAGE HEADER */
        .page-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 22px; flex-wrap: wrap; gap: 12px;
        }
        .page-header h1 { color: #003366; font-size: 24px; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .page-header h1 .badge-count {
            background: #0055aa; color: white; font-size: 13px;
            padding: 3px 12px; border-radius: 12px; font-weight: 700;
        }
        .top-actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .btn-add { background: #16a34a; color: white; padding: 11px 20px; border-radius: 10px; text-decoration: none; font-weight: 700; transition: all 0.2s; display: inline-flex; align-items: center; gap: 6px; font-size: 13px; }
        .btn-add:hover { background: #15803d; transform: translateY(-1px); }
        .btn-excel { background: #10b981; color: white; padding: 11px 20px; border-radius: 10px; text-decoration: none; font-weight: 700; transition: all 0.2s; display: inline-flex; align-items: center; gap: 6px; font-size: 13px; }
        .btn-excel:hover { background: #059669; transform: translateY(-1px); }

        /* ALERTES */
        .alert-success { background: #d1fae5; color: #065f46; padding: 14px 20px; border-radius: 10px; margin-bottom: 18px; border-left: 4px solid #10b981; font-weight: 600; font-size: 14px; }
        .alert-error { background: #fee2e2; color: #991b1b; padding: 14px 20px; border-radius: 10px; margin-bottom: 18px; border-left: 4px solid #dc2626; font-weight: 600; font-size: 14px; }

        /* FILTRES */
        .filters-card { background: white; padding: 20px; border-radius: 14px; box-shadow: 0 2px 8px rgba(0,0,0,.05); margin-bottom: 18px; border: 1px solid #e2e8f0; }
        .filters-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; align-items: end; }
        .filter-group { display: flex; flex-direction: column; min-width: 0; }
        .filter-group label { font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 5px; }
        .filter-group input, .filter-group select {
            width: 100%; max-width: 100%; padding: 10px 12px;
            border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 13px;
            outline: none; background: #f8fafc; transition: all 0.2s; font-family: inherit;
        }
        .filter-group input:focus, .filter-group select:focus {
            border-color: #0055aa; box-shadow: 0 0 0 3px rgba(0,85,170,0.08); background: white;
        }
        .filter-group select:disabled { background: #f1f5f9; cursor: not-allowed; opacity: 0.7; }
        .filter-actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .btn-search { background: #003366; color: white; padding: 10px 18px; border: none; border-radius: 8px; font-weight: 700; font-size: 13px; cursor: pointer; }
        .btn-search:hover { background: #0055aa; }
        .btn-reset { background: #e5e7eb; color: #111827; padding: 10px 18px; border-radius: 8px; font-weight: 700; font-size: 13px; text-decoration: none; display: inline-flex; align-items: center; }
        .btn-reset:hover { background: #d1d5db; }

        /* STATS BAR */
        .result-stats {
            background: white; padding: 12px 20px; border-radius: 12px; margin-bottom: 15px;
            border: 1px solid #e2e8f0; display: flex; justify-content: space-between;
            align-items: center; flex-wrap: wrap; gap: 10px;
        }
        .result-stats .count { font-weight: 600; color: #475569; font-size: 14px; }
        .result-stats .count strong { color: #003366; font-size: 18px; }

        /* TABLE */
        .table-card { background: white; border-radius: 14px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.05); border: 1px solid #e2e8f0; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; min-width: 900px; }
        thead { background: #003366; color: white; }
        th { padding: 14px 12px; text-align: left; font-size: 11.5px; text-transform: uppercase; font-weight: 700; letter-spacing: 0.3px; white-space: nowrap; }
        td { padding: 12px; border-bottom: 1px solid #eef2f7; vertical-align: middle; }
        tbody tr:hover { background: #f8fbff; }

        .badge-dept { display: inline-block; padding: 3px 10px; border-radius: 12px; background: #eff6ff; color: #1e40af; font-weight: 600; font-size: 11px; white-space: nowrap; }
        .badge-classe { display: inline-block; padding: 3px 10px; border-radius: 12px; background: #fef3c7; color: #92400e; font-weight: 600; font-size: 11px; white-space: nowrap; }
        .badge-faculte { display: inline-block; padding: 3px 10px; border-radius: 12px; background: #f0fdf4; color: #166534; font-weight: 600; font-size: 11px; white-space: nowrap; }
        .matricule-code { font-family: monospace; font-size: 12px; background: #f1f5f9; padding: 3px 8px; border-radius: 4px; white-space: nowrap; }

        .btn-action {
            display: inline-flex; align-items: center; justify-content: center;
            width: 32px; height: 32px; border-radius: 6px;
            text-decoration: none; font-size: 14px; transition: all 0.2s;
            border: none; cursor: pointer;
        }
        .btn-edit { background: #dbeafe; color: #1e40af; }
        .btn-edit:hover { background: #2563eb; color: white; }
        .btn-delete { background: #fee2e2; color: #991b1b; }
        .btn-delete:hover { background: #dc2626; color: white; }

        .empty-state { padding: 60px 20px; text-align: center; color: #94a3b8; font-size: 14px; }
        .empty-state i { font-size: 48px; display: block; margin-bottom: 12px; opacity: 0.4; }

        /* PAGINATION */
        .pagination { display: flex; justify-content: center; align-items: center; gap: 10px; margin-top: 20px; flex-wrap: wrap; }
        .pagination a { background: white; color: #003366; border: 1px solid #cbd5e1; padding: 8px 16px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 13px; transition: all 0.2s; }
        .pagination a:hover { background: #003366; color: white; border-color: #003366; }
        .page-indicator { padding: 8px 16px; background: #003366; color: white; border-radius: 8px; font-weight: 700; font-size: 13px; }

        @media (max-width: 768px) {
            .main-container { padding: 16px 12px; }
            .main-container.in-iframe { padding: 12px; }
            .filters-grid { grid-template-columns: 1fr 1fr; }
            .page-header h1 { font-size: 20px; }
        }
        @media (max-width: 480px) {
            .filters-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body class="<?= $is_iframe ? 'iframe-mode' : '' ?>">

<?php if (!$is_iframe): ?>
    <?php include("menu.php"); ?>
<?php endif; ?>

<div class="main-container <?= $is_iframe ? 'in-iframe' : '' ?>">
    <div class="page-inner">

        <?php if ($is_iframe): ?>
            <div class="iframe-topbar">
                <div class="iframe-topbar-left">
                    <a href="dashboard_agent.php" target="_top" class="btn-back">← Retour au dashboard</a>
                    <a href="afficheinscription.php?iframe=1" class="btn-nav active">📋 Liste</a>
                    <a href="ajouteinscription.php?iframe=1" class="btn-nav">➕ Nouvelle</a>
                </div>
            </div>
        <?php endif; ?>

        <div class="page-header">
            <h1>📋 Liste des inscriptions <span class="badge-count"><?= $total ?></span></h1>
            <div class="top-actions">
                <a href="ajouteinscription.php<?= $is_iframe ? '?iframe=1' : '' ?>" class="btn-add">➕ Nouvelle inscription</a>
                <?php
                $export_url = 'export_excel_universel.php?table=inscriptions&filename=Liste_Inscriptions';
                if (!empty($_GET)) $export_url .= '&' . http_build_query($_GET);
                ?>
                <a href="<?= $export_url ?>" class="btn-excel" onclick="return confirm('Exporter en Excel ?')">📊 Excel</a>
                <?php if (!$is_iframe): ?>
                    <a href="dashboard_agent.php" class="btn-add" style="background: #003366;">🏠 Dashboard</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($success === 'suppression'): ?>
            <div class="alert-success">✅ Inscription supprimée avec succès.</div>
        <?php elseif ($success === 'ajout'): ?>
            <div class="alert-success">✅ Inscription ajoutée avec succès.</div>
        <?php elseif ($success === 'modification'): ?>
            <div class="alert-success">✅ Inscription modifiée avec succès.</div>
        <?php endif; ?>
        <?php if (!empty($erreur_supp)): ?>
            <div class="alert-error">❌ <?= htmlspecialchars($erreur_supp) ?></div>
        <?php endif; ?>

        <!-- FILTRES -->
        <div class="filters-card">
            <form method="GET">
                <?php if ($is_iframe): ?>
                    <input type="hidden" name="iframe" value="1">
                <?php endif; ?>
                <div class="filters-grid">
                    <div class="filter-group">
                        <label>🔍 Recherche</label>
                        <input type="text" name="recherche" value="<?= htmlspecialchars($recherche) ?>" placeholder="Nom, prénom, matricule...">
                    </div>
                    <div class="filter-group">
                        <label>🏛️ Département</label>
                        <select name="dept" onchange="this.form.submit()">
                            <option value="0">— Tous —</option>
                            <?php foreach ($departements as $d): ?>
                                <option value="<?= $d['id_dept'] ?>" <?= ($filtre_dept == $d['id_dept']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($d['nom_dept']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>📚 Classe</label>
                        <select name="classe" <?= empty($classes) ? 'disabled' : '' ?>>
                            <option value="0">— Toutes —</option>
                            <?php foreach ($classes as $c): ?>
                                <option value="<?= $c['id_classe'] ?>" <?= ($filtre_classe == $c['id_classe']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['niveau']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>📅 Du</label>
                        <input type="date" name="date_deb" value="<?= htmlspecialchars($filtre_date_deb) ?>">
                    </div>
                    <div class="filter-group">
                        <label>📅 Au</label>
                        <input type="date" name="date_fin" value="<?= htmlspecialchars($filtre_date_fin) ?>">
                    </div>
                    <div class="filter-actions">
                        <button type="submit" class="btn-search">🔍 Filtrer</button>
                        <a href="afficheinscription.php<?= $is_iframe ? '?iframe=1' : '' ?>" class="btn-reset">✖️</a>
                    </div>
                </div>
            </form>
        </div>

        <!-- STATS -->
        <div class="result-stats">
            <div class="count"><strong><?= $total ?></strong> inscription(s) trouvée(s)</div>
            <?php if ($total > $limite): ?>
                <div style="font-size: 13px; color: #64748b;">Page <?= $page ?> / <?= $nbPages ?></div>
            <?php endif; ?>
        </div>

        <!-- TABLE -->
        <div class="table-card">
            <?php if (empty($inscriptions)): ?>
                <div class="empty-state">
                    <i>📭</i>
                    <p>Aucune inscription trouvée.</p>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 50px;">N°</th>
                                <th>Étudiant</th>
                                <th>Matricule</th>
                                <th>Classe</th>
                                <th>Département</th>
                                <th>Faculté</th>
                                <th>Date d'inscription</th>
                                <th style="width: 100px; text-align: center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = $debut + 1; foreach ($inscriptions as $ins): ?>
                                <tr>
                                    <td><?= $i++ ?></td>
                                    <td><strong><?= htmlspecialchars($ins['nom'] . ' ' . $ins['prenom']) ?></strong></td>
                                    <td><span class="matricule-code"><?= htmlspecialchars($ins['matricule']) ?></span></td>
                                    <td><span class="badge-classe"><?= htmlspecialchars($ins['niveau']) ?></span></td>
                                    <td><span class="badge-dept"><?= htmlspecialchars($ins['nom_dept']) ?></span></td>
                                    <td><span class="badge-faculte"><?= htmlspecialchars($ins['faculte'] ?: '—') ?></span></td>
                                    <td><?= date('d/m/Y', strtotime($ins['date_inscription'])) ?></td>
                                    <td style="text-align: center;">
                                        <a href="modifiinscription.php?id=<?= (int)$ins['id_inscription'] ?><?= $is_iframe ? '&iframe=1' : '' ?>" class="btn-action btn-edit" title="Modifier">✏️</a>
                                        <a href="afficheinscription.php?supp=<?= (int)$ins['id_inscription'] ?><?= $is_iframe ? '&iframe=1' : '' ?>" class="btn-action btn-delete" title="Supprimer" onclick="return confirm('Voulez-vous vraiment supprimer cette inscription ?');">🗑️</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($nbPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?<?= buildQuery(['page' => $page - 1]) ?>">← Précédent</a>
                <?php endif; ?>
                <span class="page-indicator">Page <?= $page ?> / <?= $nbPages ?></span>
                <?php if ($page < $nbPages): ?>
                    <a href="?<?= buildQuery(['page' => $page + 1]) ?>">Suivant →</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </div>
</div>

</body>
</html>