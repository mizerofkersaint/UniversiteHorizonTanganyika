<?php
require_once __DIR__ . '/db.php';
require_once 'auth.php';
requireRole(['Administrateur', 'Doyen', 'Professeur']);
try {
    $bdd = getDBConnection();
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

/* ============================================================
   SUPPRESSION
============================================================ */
if (isset($_GET['supp']) && !empty($_GET['supp'])) {
    $idSupp = (int) $_GET['supp'];
    try {
        $supprimer = $bdd->prepare("DELETE FROM notes WHERE id_note = ?");
        $supprimer->execute([$idSupp]);
        echo "<script>alert('Note supprimée avec succès'); window.location='affichenote.php';</script>";
        exit();
    } catch (PDOException $e) {
        echo "<script>alert('Erreur lors de la suppression');</script>";
    }
}

/* ============================================================
   FILTRES
============================================================ */
$filtreDept    = isset($_GET['departement']) ? trim($_GET['departement']) : "";
$filtreFaculte = isset($_GET['faculte']) ? trim($_GET['faculte']) : "";
$filtreClasse  = isset($_GET['classe']) ? trim($_GET['classe']) : "";
$filtreNote    = isset($_GET['filtre_note']) ? trim($_GET['filtre_note']) : "";
$filtreSession = isset($_GET['session']) ? trim($_GET['session']) : "";

$departements = $bdd->query("SELECT id_dept, nom_dept, faculte FROM departements ORDER BY nom_dept");

$facultes = [];
if(!empty($filtreDept)){
    $faculteReq = $bdd->prepare("SELECT faculte FROM departements WHERE id_dept = ?");
    $faculteReq->execute([$filtreDept]);
    $faculte = $faculteReq->fetchColumn();
    if($faculte){
        $facultes = [$faculte];
    }
}

$classes = [];
if(!empty($filtreDept)){
    $stmtClasses = $bdd->prepare("SELECT id_classe, niveau FROM classes WHERE id_dept = ? ORDER BY niveau ASC");
    $stmtClasses->execute([$filtreDept]);
    $classes = $stmtClasses->fetchAll();
}

/* ============================================================
   PAGINATION
============================================================ */
$limite = 7;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1) $page = 1;
$debut = ($page - 1) * $limite;

$where = [];
$params = [];

if(!empty($filtreDept)){
    $where[] = "d.id_dept = ?";
    $params[] = $filtreDept;
}
if(!empty($filtreFaculte)){
    $where[] = "d.faculte = ?";
    $params[] = $filtreFaculte;
}
if(!empty($filtreClasse)){
    $where[] = "cl.id_classe = ?";
    $params[] = $filtreClasse;
}
if($filtreNote === "sup10"){
    $where[] = "n.note >= ?";
    $params[] = 10;
}elseif($filtreNote === "inf10"){
    $where[] = "n.note < ?";
    $params[] = 10;
}
if(!empty($filtreSession)){
    $where[] = "n.session = ?";
    $params[] = $filtreSession;
}

$sqlWhere = count($where) > 0 ? " WHERE " . implode(" AND ", $where) : "";

$sqlTotal = "SELECT COUNT(*) AS total FROM notes n
    INNER JOIN etudiants e ON n.id_etudiant = e.id_etudiant
    INNER JOIN users u ON e.id_user = u.id_user
    INNER JOIN cours c ON n.id_cours = c.id_cours
    INNER JOIN classes cl ON c.id_classe = cl.id_classe
    INNER JOIN departements d ON cl.id_dept = d.id_dept
    $sqlWhere";
$stmtTotal = $bdd->prepare($sqlTotal);
$stmtTotal->execute($params);
$total = $stmtTotal->fetch()['total'];
$nbPages = ceil($total / $limite) < 1 ? 1 : ceil($total / $limite);

$sqlNotes = "SELECT n.id_note, n.note, n.session, u.nom, u.prenom, d.nom_dept, d.faculte, d.id_dept, cl.id_classe, cl.niveau, c.nom_cours
    FROM notes n
    INNER JOIN etudiants e ON n.id_etudiant = e.id_etudiant
    INNER JOIN users u ON e.id_user = u.id_user
    INNER JOIN cours c ON n.id_cours = c.id_cours
    INNER JOIN classes cl ON c.id_classe = cl.id_classe
    INNER JOIN departements d ON cl.id_dept = d.id_dept
    $sqlWhere
    ORDER BY n.id_note DESC
    LIMIT $debut, $limite";
$stmtNotes = $bdd->prepare($sqlNotes);
$stmtNotes->execute($params);

function buildQuery($extra = []) {
    $query = $_GET;
    foreach ($extra as $key => $value) $query[$key] = $value;
    return http_build_query($query);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Gestion des notes</title>
<style>
*{ margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI', Arial, sans-serif; }
body{ background:#eef2f7; color:#1f2937; }
.container{ width:95%; margin:30px auto; }
.page-header{ display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px; margin-bottom:22px; }
.page-header h1{ color:#003366; font-size:30px; font-weight:800; }
.top-actions{ display:flex; gap:10px; flex-wrap:wrap; }
.ajouter{ background:linear-gradient(135deg,#0066cc,#004c99); color:white; text-decoration:none; padding:12px 18px; border-radius:10px; font-weight:700; transition:.3s; box-shadow:0 8px 18px rgba(0,102,204,.18); }
.ajouter:hover{ transform:translateY(-2px); }
.btn-excel{ background:#10b981; color:white; text-decoration:none; padding:12px 18px; border-radius:10px; font-weight:700; display:inline-flex; align-items:center; gap:8px; transition:.3s; }
.btn-excel:hover{ background:#059669; transform:translateY(-2px); }
.filters-card{ background:#fff; border-radius:18px; box-shadow:0 10px 25px rgba(0,0,0,.07); padding:20px; margin-bottom:22px; border:1px solid #e5e7eb; }
.filters-title{ font-size:18px; font-weight:700; color:#003366; margin-bottom:16px; }
.filters-form{ display:grid; grid-template-columns:repeat(5,1fr); gap:14px; }
.filter-group{ display:flex; flex-direction:column; }
.filter-group label{ font-size:13px; font-weight:700; margin-bottom:7px; color:#334155; }
.filter-group select{ height:44px; border:1px solid #d1d5db; border-radius:10px; padding:0 12px; outline:none; font-size:14px; background:#fff; transition:.25s; }
.filter-group select:focus{ border-color:#00509e; box-shadow:0 0 0 4px rgba(0,80,158,.10); }
.filter-group select:disabled{ background:#f1f5f9; cursor:not-allowed; opacity:0.7; }
.filter-actions{ grid-column:1 / -1; display:flex; justify-content:flex-end; gap:12px; margin-top:8px; flex-wrap:wrap; }
.btn-filter, .btn-reset{ border:none; border-radius:10px; padding:12px 18px; font-weight:700; cursor:pointer; transition:.25s; text-decoration:none; display:inline-flex; align-items:center; justify-content:center; }
.btn-filter{ background:#003366; color:#fff; }
.btn-filter:hover{ background:#00509e; }
.btn-reset{ background:#e5e7eb; color:#111827; }
.btn-reset:hover{ background:#d1d5db; }
.result-stats{ background:white; padding:12px 20px; border-radius:12px; margin-bottom:15px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; border:1px solid #e5e7eb; }
.result-stats .count{ font-weight:600; }
.result-stats .count strong{ color:#003366; font-size:18px; }
.table-card{ background:#fff; border-radius:18px; box-shadow:0 10px 25px rgba(0,0,0,.07); overflow:hidden; border:1px solid #e5e7eb; }
.table-header{ display:flex; justify-content:space-between; align-items:center; padding:18px 22px; border-bottom:1px solid #edf0f4; flex-wrap:wrap; gap:12px; }
.table-header h2{ color:#003366; font-size:20px; }
.table-info{ color:#64748b; font-size:14px; font-weight:600; }
.table-wrap{ overflow-x:auto; }
table{ width:100%; border-collapse:collapse; min-width:1400px; }
thead{ background:#003366; color:#fff; }
th{ padding:15px 12px; text-align:center; font-size:14px; white-space:nowrap; }
td{ padding:14px 12px; text-align:center; border-bottom:1px solid #eef2f7; vertical-align:middle; }
tbody tr:hover{ background:#f8fbff; }
th, td{ border: none !important; }
.badge{ display:inline-block; padding:6px 12px; border-radius:999px; font-size:12px; font-weight:700; }
.badge-success{ background:#ecfdf3; color:#15803d; }
.badge-danger{ background:#fef2f2; color:#dc2626; }
.badge-session{ background:#eff6ff; color:#1d4ed8; }
.faculte-badge{ display:inline-block; padding:2px 10px; border-radius:12px; background:#eff6ff; color:#1e40af; font-weight:600; font-size:11px; }
.btn{ padding:9px 14px; color:white; text-decoration:none; border-radius:8px; font-size:13px; font-weight:700; display:inline-block; transition:.25s; }
.modifier{ background:#16a34a; color:white; text-decoration:none; padding:9px 14px; border-radius:8px; font-size:13px; font-weight:700; display:inline-block; }
.modifier:hover{ background:#15803d; transform:translateY(-1px); }
.supprimer{ background:#dc2626; border:none; cursor:pointer; padding:9px 14px; border-radius:8px; font-size:13px; font-weight:700; display:inline-block; color:white; text-decoration:none; }
.supprimer:hover{ background:#b91c1c; transform:translateY(-1px); }
.pagination{ display:flex; justify-content:center; align-items:center; gap:15px; margin-top:24px; flex-wrap:wrap; }
.pagination a{ background:#003366; color:white; text-decoration:none; padding:11px 18px; border-radius:10px; font-weight:bold; transition:.3s; }
.pagination a:hover{ background:#00509e; transform:translateY(-2px); }
.page-indicator{ padding:10px 18px; background:#fff; border-radius:10px; border:1px solid #dbe3ec; color:#003366; font-weight:700; }
@media (max-width: 1100px){ .filters-form{ grid-template-columns:repeat(3,1fr); } }
@media (max-width: 700px){ .filters-form{ grid-template-columns:1fr; } .page-header{ flex-direction:column; align-items:flex-start; } }
</style>
</head>
<body>
<?php include("menu.php"); ?>
<div class="container">
    <div class="page-header">
        <h1>📘 Gestion des notes</h1>
        <div class="top-actions">
            <a href="ajoutenote.php" class="ajouter">+ Ajouter les notes</a>
            
            <!-- ============================================================
               BOUTON D'EXPORT EXCEL - INCLUS
            ============================================================ -->
            <?php 
            $export_url = 'export_excel_universel.php?table=notes&filename=Liste_Notes';
            if (!empty($_GET)) {
                $export_url .= '&' . http_build_query($_GET);
            }
            ?>
            <a href="<?= $export_url ?>" class="btn-excel" onclick="return confirm('Exporter les données en Excel ?')">
                📊 Exporter Excel
            </a>
        </div>
    </div>

    <div class="filters-card">
        <div class="filters-title">Recherche & filtres</div>
        <form method="GET" class="filters-form">
            <div class="filter-group">
                <label>Département</label>
                <select name="departement" onchange="this.form.submit()">
                    <option value="">Tous</option>
                    <?php 
                    $departements->execute();
                    while($dept = $departements->fetch()){ ?>
                        <option value="<?= $dept['id_dept'] ?>" <?= ($filtreDept == $dept['id_dept']) ? 'selected' : '' ?>>
                            <?= $dept['nom_dept'] ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Faculté</label>
                <select name="faculte" <?= empty($filtreDept) ? 'disabled' : '' ?>>
                    <option value="">Toutes</option>
                    <?php if(!empty($filtreDept)){ foreach($facultes as $f){ ?>
                        <option value="<?= $f ?>" <?= ($filtreFaculte == $f) ? 'selected' : '' ?>>
                            <?= $f ?>
                        </option>
                    <?php }} ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Classe</label>
                <select name="classe">
                    <option value="">Toutes</option>
                    <?php if(!empty($filtreDept) && !empty($classes)){ foreach($classes as $cl){ ?>
                        <option value="<?= $cl['id_classe'] ?>" <?= ($filtreClasse == $cl['id_classe']) ? 'selected' : '' ?>>
                            <?= $cl['niveau'] ?>
                        </option>
                    <?php }} ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Filtre note</label>
                <select name="filtre_note">
                    <option value="">Toutes</option>
                    <option value="sup10" <?= ($filtreNote == "sup10") ? 'selected' : '' ?>>Note ≥ 10</option>
                    <option value="inf10" <?= ($filtreNote == "inf10") ? 'selected' : '' ?>>Note < 10</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Session</label>
                <select name="session">
                    <option value="">Toutes</option>
                    <option value="1ère Session" <?= ($filtreSession == "1ère Session") ? 'selected' : '' ?>>1ère Session</option>
                    <option value="2ème Session" <?= ($filtreSession == "2ème Session") ? 'selected' : '' ?>>2ème Session</option>
                </select>
            </div>
            <div class="filter-actions">
                <a href="affichenote.php" class="btn-reset">Réinitialiser</a>
                <button type="submit" class="btn-filter">Filtrer</button>
            </div>
        </form>
    </div>

    <!-- STATISTIQUES -->
    <div class="result-stats">
        <div class="count"><strong><?= $total ?></strong> note(s) trouvée(s)</div>
        <?php if($filtreNote == "sup10"): ?>
            <span style="background:#ecfdf3; color:#15803d; padding:4px 12px; border-radius:12px; font-size:12px; font-weight:600;">✅ Notes ≥ 10</span>
        <?php elseif($filtreNote == "inf10"): ?>
            <span style="background:#fef2f2; color:#dc2626; padding:4px 12px; border-radius:12px; font-size:12px; font-weight:600;">❌ Notes < 10</span>
        <?php endif; ?>
        <?php if(!empty($filtreSession)): ?>
            <span style="background:#eff6ff; color:#1e40af; padding:4px 12px; border-radius:12px; font-size:12px; font-weight:600;">Session: <?= htmlspecialchars($filtreSession) ?></span>
        <?php endif; ?>
    </div>

    <div class="table-card">
        <div class="table-header">
            <h2>Liste des notes</h2>
            <div class="table-info"><?= $total ?> note(s)</div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>N°</th>
                        <th>DÉPARTEMENT</th>
                        <th>FACULTÉ</th>
                        <th>CLASSE</th>
                        <th>ÉTUDIANT</th>
                        <th>COURS</th>
                        <th>NOTE</th>
                        <th>SESSION</th>
                        <th colspan="2">ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = $debut + 1; foreach($stmtNotes as $dataNotes){ 
                        $badgeNote = ($dataNotes["note"] >= 10) ? "badge badge-success" : "badge badge-danger";
                    ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td><?= $dataNotes["nom_dept"] ?></td>
                        <td><span class="faculte-badge"><?= $dataNotes["faculte"] ?: 'Non assignée' ?></span></td>
                        <td><?= $dataNotes["niveau"] ?></td>
                        <td><?= $dataNotes["nom"]." ".$dataNotes["prenom"] ?></td>
                        <td><?= $dataNotes["nom_cours"] ?></td>
                        <td><span class="<?= $badgeNote ?>"><?= $dataNotes["note"] ?></span></td>
                        <td><span class="badge badge-session"><?= $dataNotes["session"] ?></span></td>
                        <td><a href="modifinote.php?id=<?= $dataNotes['id_note']; ?>" class="modifier">✏️</a></td>
                        <td><a href="affichenote.php?supp=<?= $dataNotes['id_note']; ?>" class="btn supprimer" onclick="return confirm('Voulez-vous supprimer cette note ?');">🗑️</a></td>
                    </tr>
                    <?php } ?>
                    <?php if($total == 0){ ?>
                    <tr><td colspan="10" style="padding:30px; color:#64748b; text-align:center;">📭 Aucune note trouvée.</td></tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="pagination">
        <?php if($page > 1){ ?><a href="?<?= buildQuery(['page' => $page - 1]) ?>">← Retour</a><?php } ?>
        <div class="page-indicator">Page <?= $page ?> / <?= $nbPages ?></div>
        <?php if($page < $nbPages){ ?><a href="?<?= buildQuery(['page' => $page + 1]) ?>">Suivant →</a><?php } ?>
    </div>
</div>
</body>
</html>