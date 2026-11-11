<?php
require_once __DIR__ . '/db.php';
require_once 'auth.php';
requireRole(['Comptable', 'Administrateur']);

try {
    $bdd = getDBConnection();
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

/* ============================================================
   SUPPRESSION
============================================================ */
if(isset($_GET['supp']) && !empty($_GET['supp']))
{
    $idSupp = (int) $_GET['supp'];
    try {
        $bdd->beginTransaction();
        $supprimer = $bdd->prepare("DELETE FROM paiements WHERE id_paiement = ?");
        $supprimer->execute([$idSupp]);
        $bdd->commit();
        echo "<script>alert('Paiement supprimé avec succès'); window.location='affichepaiement.php';</script>";
        exit();
    } catch (PDOException $e) {
        if ($bdd->inTransaction()) $bdd->rollBack();
        echo "<script>alert('Erreur lors de la suppression');</script>";
    }
}

/* ============================================================
   FILTRES
============================================================ */
$filtreDept    = isset($_GET['departement']) ? trim($_GET['departement']) : "";
$filtreFaculte = isset($_GET['faculte']) ? trim($_GET['faculte']) : "";
$filtreClasse  = isset($_GET['classe']) ? trim($_GET['classe']) : "";
$filtreTranche = isset($_GET['tranche']) ? trim($_GET['tranche']) : "";

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
    $classes = $stmtClasses->fetchAll(PDO::FETCH_ASSOC);
}

/* ============================================================
   PAGINATION
============================================================ */
$limite = 7;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if($page < 1) $page = 1;
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
if(!empty($filtreTranche)){
    $where[] = "p.tranche = ?";
    $params[] = $filtreTranche;
}

$sqlWhere = count($where) > 0 ? " WHERE " . implode(" AND ", $where) : "";

$sqlTotal = "SELECT COUNT(*) AS total FROM paiements p
    INNER JOIN etudiants e ON p.id_etudiant = e.id_etudiant
    INNER JOIN users u ON e.id_user = u.id_user
    INNER JOIN classes cl ON e.id_classe = cl.id_classe
    INNER JOIN departements d ON cl.id_dept = d.id_dept
    $sqlWhere";
$stmtTotal = $bdd->prepare($sqlTotal);
$stmtTotal->execute($params);
$total = $stmtTotal->fetch(PDO::FETCH_ASSOC)['total'];
$nbPages = ceil($total / $limite) < 1 ? 1 : ceil($total / $limite);

$sqlPaiements = "SELECT p.id_paiement, p.montant, p.date_paiement, p.banque, p.tranche, p.bordereau, p.motif,
    u.nom, u.prenom, d.id_dept, d.nom_dept, d.faculte, cl.id_classe, cl.niveau
    FROM paiements p
    INNER JOIN etudiants e ON p.id_etudiant = e.id_etudiant
    INNER JOIN users u ON e.id_user = u.id_user
    INNER JOIN classes cl ON e.id_classe = cl.id_classe
    INNER JOIN departements d ON cl.id_dept = d.id_dept
    $sqlWhere
    ORDER BY p.id_paiement DESC
    LIMIT $debut, $limite";
$stmtPaiements = $bdd->prepare($sqlPaiements);
$stmtPaiements->execute($params);

function buildQuery($extra = []){
    $query = $_GET;
    foreach($extra as $key => $value) $query[$key] = $value;
    return http_build_query($query);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Gestion des Paiements</title>
<style>
*{ margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI', Arial, sans-serif; }
body{ background:#eef3f8; color:#1f2937; }
.container{ width:95%; margin:30px auto; }
.page-header{ display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px; margin-bottom:22px; }
.page-header h1{ color:#003366; font-size:30px; font-weight:800; }
.top-actions{ display:flex; gap:10px; flex-wrap:wrap; }
.ajouter{ background:linear-gradient(135deg,#0066cc,#004c99); color:white; text-decoration:none; padding:12px 18px; border-radius:12px; font-weight:700; transition:.3s; box-shadow:0 10px 20px rgba(0,102,204,.18); }
.ajouter:hover{ transform:translateY(-2px); }
.btn-excel{ background:#10b981; color:white; text-decoration:none; padding:12px 18px; border-radius:12px; font-weight:700; display:inline-flex; align-items:center; gap:8px; transition:.3s; }
.btn-excel:hover{ background:#059669; transform:translateY(-2px); }
.filters-card{ background:#fff; border-radius:18px; box-shadow:0 10px 25px rgba(0,0,0,.07); padding:22px; margin-bottom:24px; border:1px solid #e5e7eb; }
.filters-title{ font-size:18px; font-weight:800; color:#003366; margin-bottom:16px; }
.filters-form{ display:grid; grid-template-columns:repeat(4,1fr); gap:16px; }
.filter-group{ display:flex; flex-direction:column; }
.filter-group label{ font-size:13px; font-weight:700; margin-bottom:8px; color:#334155; }
.filter-group select{ height:46px; border:1px solid #d1d5db; border-radius:10px; padding:0 12px; outline:none; font-size:14px; background:#fff; transition:.25s; }
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
.table-header h2{ color:#003366; font-size:20px; font-weight:800; }
.table-info{ color:#64748b; font-size:14px; font-weight:700; }
.table-wrap{ overflow-x:auto; }
table{ width:100%; border-collapse:collapse; min-width:1500px; }
thead{ background:#003366; color:#fff; }
th{ padding:15px 12px; text-align:center; font-size:14px; white-space:nowrap; }
td{ padding:14px 12px; text-align:center; border-bottom:1px solid #eef2f7; vertical-align:middle; }
tbody tr:hover{ background:#f8fbff; }
th, td{ border: none !important; }
.badge{ display:inline-block; padding:6px 12px; border-radius:999px; font-size:12px; font-weight:700; }
.badge-inscription{ background:#eff6ff; color:#1d4ed8; }
.badge-t1{ background:#ecfdf3; color:#15803d; }
.badge-t2{ background:#fefce8; color:#a16207; }
.badge-t3{ background:#fdf2f8; color:#be185d; }
.badge-t4{ background:#eef2ff; color:#4338ca; }
.badge-penalite{ background:#fef2f2; color:#b91c1c; }
.faculte-badge{ display:inline-block; padding:2px 10px; border-radius:12px; background:#eff6ff; color:#1e40af; font-weight:600; font-size:11px; }
.bordereau-box{ display:flex; justify-content:center; align-items:center; gap:8px; flex-wrap:wrap; }
.bordereau-tag{ background:#16a34a; color:white; padding:4px 10px; border-radius:999px; font-size:12px; font-weight:700; }
.btn{ padding:9px 14px; color:white; text-decoration:none; border-radius:8px; font-size:13px; font-weight:700; display:inline-block; transition:.25s; }
.modifier{ background:#16a34a; }
.modifier:hover{ background:#15803d; transform:translateY(-1px); }
.supprimer{ background:#dc2626; }
.supprimer:hover{ background:#b91c1c; transform:translateY(-1px); }
.pagination{ display:flex; justify-content:center; align-items:center; gap:15px; margin-top:24px; flex-wrap:wrap; }
.pagination a{ background:#003366; color:white; text-decoration:none; padding:11px 18px; border-radius:10px; font-weight:bold; transition:.3s; }
.pagination a:hover{ background:#00509e; transform:translateY(-2px); }
.page-indicator{ padding:10px 18px; background:#fff; border-radius:10px; border:1px solid #dbe3ec; color:#003366; font-weight:700; }
@media (max-width: 1000px){ .filters-form{ grid-template-columns:repeat(2,1fr); } }
@media (max-width: 700px){ .filters-form{ grid-template-columns:1fr; } .page-header{ flex-direction:column; align-items:flex-start; } }
</style>
</head>
<body>
<div class="container">
    <div class="page-header">
        <h1>💳 Gestion des Paiements</h1>
        <div class="top-actions">
            <a href="ajoutepaiement.php" class="ajouter">+ Ajouter paiement</a>
            
            <!-- ============================================================
               BOUTON D'EXPORT EXCEL - INCLUS
            ============================================================ -->
            <?php 
            $export_url = 'export_excel_universel.php?table=paiements&filename=Liste_Paiements';
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
                    <option value="">Choisir un département</option>
                    <?php 
                    $departements->execute();
                    while($dept = $departements->fetch(PDO::FETCH_ASSOC)){ ?>
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
                    <option value="">Choisir une classe</option>
                    <?php foreach($classes as $cl){ ?>
                        <option value="<?= $cl['id_classe'] ?>" <?= ($filtreClasse == $cl['id_classe']) ? 'selected' : '' ?>>
                            <?= $cl['niveau'] ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Tranche</label>
                <select name="tranche">
                    <option value="">Toutes les tranches</option>
                    <option value="Inscription" <?= ($filtreTranche == "Inscription") ? 'selected' : '' ?>>Inscription</option>
                    <option value="Tranche 1" <?= ($filtreTranche == "Tranche 1") ? 'selected' : '' ?>>Tranche 1</option>
                    <option value="Tranche 2" <?= ($filtreTranche == "Tranche 2") ? 'selected' : '' ?>>Tranche 2</option>
                    <option value="Tranche 3" <?= ($filtreTranche == "Tranche 3") ? 'selected' : '' ?>>Tranche 3</option>
                    <option value="Tranche 4" <?= ($filtreTranche == "Tranche 4") ? 'selected' : '' ?>>Tranche 4</option>
                    <option value="Penalte" <?= ($filtreTranche == "Penalte") ? 'selected' : '' ?>>Pénalité</option>
                </select>
            </div>
            <div class="filter-actions">
                <a href="affichepaiement.php" class="btn-reset">Réinitialiser</a>
                <button type="submit" class="btn-filter">Filtrer</button>
            </div>
        </form>
    </div>

    <!-- STATISTIQUES -->
    <div class="result-stats">
        <div class="count"><strong><?= $total ?></strong> paiement(s) trouvé(s)</div>
        <?php if(!empty($filtreTranche)): ?>
            <span style="background:#eff6ff; color:#1e40af; padding:4px 12px; border-radius:12px; font-size:12px; font-weight:600;">Tranche: <?= htmlspecialchars($filtreTranche) ?></span>
        <?php endif; ?>
        <?php if(!empty($filtreDept)): ?>
            <span style="background:#eff6ff; color:#1e40af; padding:4px 12px; border-radius:12px; font-size:12px; font-weight:600;">Département filtré</span>
        <?php endif; ?>
    </div>

    <div class="table-card">
        <div class="table-header">
            <h2>Liste des paiements</h2>
            <div class="table-info"><?= $total ?> paiement(s)</div>
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
                        <th>TRANCHE</th>
                        <th>MONTANT</th>
                        <th>BANQUE / BORDEREAU</th>
                        <th>DATE</th>
                        <th colspan="2">ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = $debut + 1; while($dataPaiement = $stmtPaiements->fetch(PDO::FETCH_ASSOC)){ 
                        $trancheClass = "";
                        switch(strtolower(trim($dataPaiement["tranche"]))){
                            case "inscription": $trancheClass = "badge badge-inscription"; break;
                            case "tranche 1": $trancheClass = "badge badge-t1"; break;
                            case "tranche 2": $trancheClass = "badge badge-t2"; break;
                            case "tranche 3": $trancheClass = "badge badge-t3"; break;
                            case "tranche 4": $trancheClass = "badge badge-t4"; break;
                            default: $trancheClass = "badge badge-penalite"; break;
                        }
                    ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td><?= $dataPaiement["nom_dept"] ?></td>
                        <td><span class="faculte-badge"><?= $dataPaiement["faculte"] ?: 'Non assignée' ?></span></td>
                        <td><?= $dataPaiement["niveau"] ?></td>
                        <td><?= $dataPaiement["nom"] . " " . $dataPaiement["prenom"] ?></td>
                        <td><span class="<?= $trancheClass ?>"><?= $dataPaiement["tranche"] ?></span></td>
                        <td><?= number_format($dataPaiement["montant"], 0, ',', ' ') ?> FBu</td>
                        <td>
                            <div class="bordereau-box">
                                <span><?= $dataPaiement["banque"] ?></span>
                                <span class="bordereau-tag"><?= $dataPaiement["bordereau"] ?></span>
                            </div>
                        </td>
                        <td><?= $dataPaiement["date_paiement"] ?></td>
                        <td><a href="modifipaiement.php?id=<?= $dataPaiement['id_paiement'] ?>" class="btn modifier">✏️</a></td>
                        <td><a href="affichepaiement.php?supp=<?= $dataPaiement['id_paiement'] ?>" class="btn supprimer" onclick="return confirm('Voulez-vous vraiment supprimer ce paiement ?');">🗑️</a></td>
                    </tr>
                    <?php } ?>
                    <?php if($total == 0){ ?>
                    <tr><td colspan="11" style="padding:30px; color:#64748b; text-align:center;">📭 Aucun paiement trouvé.</td></tr>
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