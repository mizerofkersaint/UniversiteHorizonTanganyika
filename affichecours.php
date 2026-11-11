<?php
require_once __DIR__ . '/db.php';
require_once 'auth.php';
requireRole(['Administrateur', 'Doyen']);
try {
    $bdd = getDBConnection();
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

/* ============================================================
   SUPPRESSION
============================================================ */
if(isset($_GET['supp']) && !empty($_GET['supp'])){
    $idSupp = (int)$_GET['supp'];
    try {
        $stmt = $bdd->prepare("DELETE FROM cours WHERE id_cours = ?");
        $stmt->execute([$idSupp]);
        echo "<script>alert('Cours supprimé avec succès'); window.location='affichecours.php';</script>";
        exit();
    } catch (Exception $e) {
        echo "<script>alert('Impossible de supprimer ce cours car il est utilisé.'); window.location='affichecours.php';</script>";
        exit();
    }
}

/* ============================================================
   FILTRES
============================================================ */
$filtreDept = isset($_GET['departement']) ? (int)$_GET['departement'] : 0;
$filtreFaculte = isset($_GET['faculte']) ? trim($_GET['faculte']) : '';
$filtreNiveau = isset($_GET['niveau']) ? trim($_GET['niveau']) : '';
$filtreCat = isset($_GET['categorie']) ? trim($_GET['categorie']) : '';
$filtreSem = isset($_GET['semestre']) ? trim($_GET['semestre']) : '';

$stmtDepts = $bdd->query("SELECT id_dept, nom_dept, faculte FROM departements ORDER BY nom_dept");
$departements = $stmtDepts->fetchAll(PDO::FETCH_ASSOC);

$facultesFiltrees = [];
if($filtreDept > 0){
    $stmt = $bdd->prepare("SELECT faculte FROM departements WHERE id_dept = ?");
    $stmt->execute([$filtreDept]);
    $faculte = $stmt->fetchColumn();
    if($faculte){
        $facultesFiltrees = [$faculte];
    }
}

/* ============================================================
   REQUÊTE PRINCIPALE
============================================================ */
$sql = "
    SELECT c.*, u.nom, u.prenom, cl.niveau, d.nom_dept, d.faculte
    FROM cours c
    INNER JOIN professeurs p ON c.id_prof = p.id_prof
    INNER JOIN users u ON p.id_user = u.id_user
    INNER JOIN classes cl ON c.id_classe = cl.id_classe
    INNER JOIN departements d ON cl.id_dept = d.id_dept
    WHERE 1=1
";
$params = [];

if($filtreDept > 0){
    $sql .= " AND d.id_dept = ?";
    $params[] = $filtreDept;
}
if(!empty($filtreFaculte)){
    $sql .= " AND d.faculte = ?";
    $params[] = $filtreFaculte;
}
if(!empty($filtreNiveau)){
    $sql .= " AND cl.niveau = ?";
    $params[] = $filtreNiveau;
}
if(!empty($filtreCat)){
    $sql .= " AND c.categorie = ?";
    $params[] = $filtreCat;
}
if(!empty($filtreSem)){
    $sql .= " AND c.semestre = ?";
    $params[] = $filtreSem;
}
$sql .= " ORDER BY c.id_cours DESC";

$stmt = $bdd->prepare($sql);
$stmt->execute($params);
$cours = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Gestion des Cours</title>
<style>
body{ font-family:Arial; background:#f4f6f9; margin:0; }
header{ background:#003366; color:white; text-align:center; padding:20px; }
.top-bar{ width:90%; margin:20px auto 0; display:flex; justify-content:flex-end; gap:10px; flex-wrap:wrap; }
.btn-ajouter{ background:#28a745; color:white; padding:12px 20px; border-radius:8px; text-decoration:none; font-weight:bold; transition:0.3s; }
.btn-ajouter:hover{ background:#218838; transform:scale(1.03); }
.btn-excel{ background:#10b981; color:white; padding:12px 20px; border-radius:8px; text-decoration:none; font-weight:bold; transition:0.3s; display:inline-flex; align-items:center; gap:8px; }
.btn-excel:hover{ background:#059669; transform:scale(1.03); }
.filters-card{ background:white; width:90%; margin:20px auto; padding:20px; border-radius:12px; box-shadow:0 4px 10px rgba(0,0,0,0.1); }
.filters-title{ text-align:center; font-size:20px; font-weight:bold; color:#003366; margin-bottom:15px; }
.filters-form{ display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:15px; align-items:end; }
.filter-group label{ font-weight:bold; }
select{ width:100%; padding:10px; border-radius:6px; border:1px solid #ccc; }
select:disabled{ background:#f1f5f9; cursor:not-allowed; opacity:0.7; }
.filter-actions{ display:flex; gap:10px; flex-wrap:wrap; align-items:center; }
.btn-filter{ background:#28a745; color:white; border:none; padding:10px 15px; border-radius:6px; cursor:pointer; }
.btn-reset{ background:#dc3545; color:white; padding:10px 15px; border-radius:6px; text-decoration:none; }
table{ width:90%; margin:30px auto; border-collapse:collapse; background:white; border-radius:10px; overflow:hidden; box-shadow:0 0 10px rgba(0,0,0,0.1); }
th{ background:#0055aa; color:white; padding:12px; }
td{ padding:10px; text-align:center; border-bottom:1px solid #eef2f7; }
tr:nth-child(even){ background:#f9f9f9; }
tr:hover{ background:#e6f2ff; }
th, td{ border:none !important; }
.faculte-badge{ display:inline-block; padding:2px 10px; border-radius:12px; background:#eff6ff; color:#1e40af; font-weight:600; font-size:11px; }
.modifier{ background:orange; padding:6px 10px; border-radius:5px; color:white; text-decoration:none; }
.supprimer{ background:red; padding:6px 10px; border-radius:5px; color:white; text-decoration:none; }
footer{ text-align:center; padding:15px; background:#003366; color:white; }
td a{ margin:2px; }
@media(max-width:768px){ .top-bar{ flex-direction:column; } .top-bar .btn-ajouter, .top-bar .btn-excel{ width:100%; text-align:center; justify-content:center; } }
</style>
</head>
<body>
<?php include("menu.php"); ?>
<header><h1>GESTION DES COURS</h1></header>
<div class="top-bar">
    <a href="ajoutecours.php" class="btn-ajouter">+ Ajouter un cours</a>
    
    <!-- ============================================================
       BOUTON D'EXPORT EXCEL - INCLUS
    ============================================================ -->
    <?php 
    $export_url = 'export_excel_universel.php?table=cours&filename=Liste_Cours';
    if (!empty($_GET)) {
        $export_url .= '&' . http_build_query($_GET);
    }
    ?>
    <a href="<?= $export_url ?>" class="btn-excel" onclick="return confirm('Exporter les données en Excel ?')">
        📊 Exporter Excel
    </a>
</div>
<div class="filters-card">
    <div class="filters-title">Recherche & filtres</div>
    <form method="GET" class="filters-form">
        <div class="filter-group">
            <label>Département</label>
            <select name="departement" onchange="this.form.submit()">
                <option value="0">Tous</option>
                <?php foreach($departements as $d): ?>
                    <option value="<?= $d['id_dept'] ?>" <?= ($filtreDept == $d['id_dept']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($d['nom_dept']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label>Faculté</label>
            <select name="faculte" <?= empty($filtreDept) ? 'disabled' : '' ?>>
                <option value="">Toutes</option>
                <?php foreach($facultesFiltrees as $f): ?>
                    <option value="<?= $f ?>" <?= ($filtreFaculte == $f) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($f) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label>Classe</label>
            <select name="niveau">
                <option value="">Toutes</option>
                <option value="BAC I" <?= ($filtreNiveau == 'BAC I') ? 'selected' : '' ?>>BAC I</option>
                <option value="BAC II" <?= ($filtreNiveau == 'BAC II') ? 'selected' : '' ?>>BAC II</option>
                <option value="BAC III" <?= ($filtreNiveau == 'BAC III') ? 'selected' : '' ?>>BAC III</option>
            </select>
        </div>
        <div class="filter-group">
            <label>Catégorie</label>
            <select name="categorie">
                <option value="">Toutes</option>
                <option value="En attente" <?= ($filtreCat == 'En attente') ? 'selected' : '' ?>>En attente</option>
                <option value="Terminé" <?= ($filtreCat == 'Terminé') ? 'selected' : '' ?>>Terminé</option>
            </select>
        </div>
        <div class="filter-group">
            <label>Semestre</label>
            <select name="semestre">
                <option value="">Tous</option>
                <option value="Semestre 1" <?= ($filtreSem == 'Semestre 1') ? 'selected' : '' ?>>Semestre 1</option>
                <option value="Semestre 2" <?= ($filtreSem == 'Semestre 2') ? 'selected' : '' ?>>Semestre 2</option>
            </select>
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn-filter">Filtrer</button>
            <a href="affichecours.php" class="btn-reset">Réinitialiser</a>
        </div>
    </form>
</div>
<table>
    <tr>
        <th>N°</th>
        <th>Cours</th>
        <th>Crédit</th>
        <th>Professeur</th>
        <th>Classe</th>
        <th>Département</th>
        <th>Faculté</th>
        <th>Actions</th>
    </tr>
    <?php if(count($cours) > 0): ?>
        <?php $i = 1; foreach($cours as $c): ?>
        <tr>
            <td><?= $i++; ?></td>
            <td><strong><?= htmlspecialchars($c['nom_cours']) ?></strong></td>
            <td><?= htmlspecialchars($c['credit']) ?></td>
            <td><?= htmlspecialchars($c['nom'] . " " . $c['prenom']) ?></td>
            <td><?= htmlspecialchars($c['niveau']) ?></td>
            <td><?= htmlspecialchars($c['nom_dept']) ?></td>
            <td><span class="faculte-badge"><?= htmlspecialchars($c['faculte'] ?: 'Non assignée') ?></span></td>
            <td>
                <a class="modifier" href="modificours.php?id=<?= $c['id_cours'] ?>">✏️ Modifier</a>
                <a href="affichecours.php?supp=<?= $c['id_cours']; ?>" class="supprimer" onclick="return confirm('Voulez-vous vraiment supprimer ce cours ?');">🗑️ Supprimer</a>
            </td>
        </tr>
        <?php endforeach; ?>
    <?php else: ?>
        <tr>
            <td colspan="8" style="padding:30px; color:#64748b; font-style:italic; text-align:center;">
                📭 Aucun cours trouvé.
            </td>
        </tr>
    <?php endif; ?>
</table>
<footer>© <?= date('Y') ?> Université du Burundi</footer>
</body>
</html>