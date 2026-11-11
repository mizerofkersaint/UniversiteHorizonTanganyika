<?php
require_once __DIR__ . '/db.php';
require_once 'auth.php';                    
requireRole(['Administrateur', 'Doyen', 'Agent']); 
try {
    $bdd = getDBConnection();
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// Inclusion du système d'archivage centralisé
require_once 'functions_archivage.php';

/* ============================================================
   SUPPRESSION + ARCHIVAGE CENTRALISÉ
============================================================ */
if(isset($_GET['supp']) && !empty($_GET['supp'])){
    $id = (int)$_GET['supp'];
    try {
        // ✅ Ancienne valeur AVANT la suppression
        $ancienne_valeur = getDonneesEnregistrement($bdd, 'departements', $id);

        $stmt = $bdd->prepare("DELETE FROM departements WHERE id_dept = ?");
        $stmt->execute([$id]);

        // ✅ Archivage centralisé — uniquement si la suppression a réellement eu lieu
        if ($stmt->rowCount() > 0) {
            $archive_id = archiverAction(
                $bdd,
                getUserIdEnSession($bdd),
                'DELETE',
                'departements',
                $id,
                $ancienne_valeur,
                null,
                'Suppression du département'
            );

            error_log("[DEBUG affdep] archiverAction retour = " . var_export($archive_id, true));
        } else {
            error_log("[DEBUG affdep] rowCount=0, suppression non effectuée pour id=" . $id);
        }

        header("Location: affichedepartement.php?success=suppression");
        exit();
    } catch (Exception $e) {
        error_log("[DEBUG affdep] Exception : " . $e->getMessage());
        echo "<script>alert('Impossible de supprimer ce département car il est utilisé ailleurs.');</script>";
    }
}

/* ============================================================
   INSERTION (déjà présent dans le fichier original)
============================================================ */
if(isset($_POST["depart"])){
    $nom = trim($_POST["nomd"]);
    $faculte = trim($_POST["faculte"]);
    $desc = trim($_POST["desc"]);

    if(!empty($nom) && !empty($faculte)){
        try {
            $stmt = $bdd->prepare("INSERT INTO departements(nom_dept, faculte, description) VALUES(?, ?, ?)");
            $stmt->execute([$nom, $faculte, $desc]);
            echo "<script>alert('Département ajouté avec succès !'); window.location='affichedepartement.php';</script>";
            exit();
        } catch (Exception $e) {
            echo "<script>alert('Erreur lors de l\'ajout : " . addslashes($e->getMessage()) . "');</script>";
        }
    } else {
        echo "<script>alert('Veuillez remplir tous les champs obligatoires.');</script>";
    }
}

/* ============================================================
   RECHERCHE
============================================================ */
$rechercheFaculte = isset($_GET['search_faculte']) ? trim($_GET['search_faculte']) : '';

$sql = "SELECT * FROM departements";
$params = [];

if(!empty($rechercheFaculte)){
    $sql .= " WHERE faculte LIKE ? OR nom_dept LIKE ?";
    $search = "%" . $rechercheFaculte . "%";
    $params = [$search, $search];
}

$sql .= " ORDER BY id_dept DESC";

$stmt = $bdd->prepare($sql);
$stmt->execute($params);
$departements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiques
$statsFacultes = $bdd->query("
    SELECT faculte, COUNT(*) as nb_departements 
    FROM departements 
    WHERE faculte IS NOT NULL AND faculte != ''
    GROUP BY faculte 
    ORDER BY nb_departements DESC
")->fetchAll(PDO::FETCH_ASSOC);

$totalDepartements = $bdd->query("SELECT COUNT(*) FROM departements")->fetchColumn();
$totalFacultes = $bdd->query("SELECT COUNT(DISTINCT faculte) FROM departements WHERE faculte IS NOT NULL AND faculte != ''")->fetchColumn();

$success = isset($_GET['success']) ? $_GET['success'] : '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Gestion des Départements</title>
<style>
*{ margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI', Arial, sans-serif; }
body{ background:#eef3f8; min-height:100vh; }
header{ background:linear-gradient(135deg,#003366,#0055aa); color:white; text-align:center; padding:25px; box-shadow:0 4px 10px rgba(0,0,0,0.2); }
h1{ margin:0; font-size:24px; }
.search-container{ width:90%; margin:20px auto 10px; display:flex; gap:15px; align-items:center; flex-wrap:wrap; background:white; padding:15px 20px; border-radius:12px; box-shadow:0 4px 15px rgba(0,0,0,0.06); border:1px solid #e5e7eb; }
.search-container .search-box{ flex:1; display:flex; gap:10px; align-items:center; min-width:250px; }
.search-container .search-box input{ flex:1; padding:12px 18px; border:2px solid #e2e8f0; border-radius:10px; font-size:15px; outline:none; transition:border-color 0.3s; min-width:200px; }
.search-container .search-box input:focus{ border-color:#0055aa; box-shadow:0 0 0 3px rgba(0,85,170,.10); }
.search-container .search-box button{ background:#003366; color:white; border:none; padding:12px 24px; border-radius:10px; font-weight:bold; cursor:pointer; transition:background 0.3s; white-space:nowrap; }
.search-container .search-box button:hover{ background:#00509e; }
.search-container .search-box .btn-reset{ background:#64748b; }
.search-container .search-box .btn-reset:hover{ background:#475569; }
.btn-excel{ background:#10b981; color:white; border:none; padding:12px 24px; border-radius:10px; font-weight:bold; cursor:pointer; transition:background 0.3s; text-decoration:none; display:inline-flex; align-items:center; gap:8px; white-space:nowrap; }
.btn-excel:hover{ background:#059669; }
.facultes-stats{ width:90%; margin:0 auto 15px; display:flex; gap:15px; flex-wrap:wrap; }
.faculte-stat-card{ background:white; padding:12px 20px; border-radius:10px; border-left:4px solid #003366; box-shadow:0 2px 10px rgba(0,0,0,0.04); display:flex; align-items:center; gap:10px; flex:1; min-width:180px; }
.faculte-stat-card .stat-icon{ font-size:24px; }
.faculte-stat-card .stat-info{ display:flex; flex-direction:column; }
.faculte-stat-card .stat-info .stat-name{ font-size:13px; color:#64748b; font-weight:600; }
.faculte-stat-card .stat-info .stat-count{ font-size:18px; font-weight:700; color:#003366; }
.add-container{ width:90%; margin:10px auto 20px; display:flex; justify-content:space-between; flex-wrap:wrap; gap:10px; }
.add-container .left-actions{ display:flex; gap:10px; flex-wrap:wrap; }
.ajouter{ background:linear-gradient(135deg,#0066cc,#004c99); color:white; padding:12px 18px; border-radius:12px; text-decoration:none; font-weight:700; transition:.3s; box-shadow:0 10px 20px rgba(0,102,204,.18); }
.ajouter:hover{ transform:translateY(-2px); }
.table-card{ width:90%; margin:20px auto; background:#fff; border-radius:18px; box-shadow:0 10px 25px rgba(0,0,0,.07); overflow:hidden; }
caption h2{ padding:18px; font-size:20px; color:#003366; font-weight:800; }
table{ width:100%; border-collapse:collapse; }
th{ background:#003366; color:#fff; padding:15px; text-transform:uppercase; font-size:13px; }
td{ padding:14px; text-align:center; border-bottom:1px solid #eef2f7; }
th, td{ border: none !important; }
tr:nth-child(even){ background:#f8fbff; }
tr:hover{ background:#e8f2ff; }
.faculte-badge{ display:inline-block; padding:4px 12px; border-radius:12px; background:#eff6ff; color:#1e40af; font-weight:600; font-size:12px; }
a{ text-decoration:none; color:white; padding:8px 12px; border-radius:8px; font-weight:700; display:inline-block; transition:.25s; }
.modifier{ background:#16a34a; margin-right:5px; }
.modifier:hover{ background:#15803d; transform:translateY(-1px); }
.supprimer{ background:#dc2626; }
.supprimer:hover{ background:#b91c1c; transform:translateY(-1px); }
.alert-success{ width:90%; margin:0 auto 15px; background:#d1fae5; color:#065f46; padding:12px 20px; border-radius:8px; border-left:4px solid #10b981; font-weight:600; }
footer{ text-align:center; padding:15px; background:#003366; color:white; margin-top:30px; }
.result-stats{ width:90%; margin:0 auto 15px; background:white; padding:12px 20px; border-radius:12px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; border:1px solid #e5e7eb; }
.result-stats .count{ font-weight:600; }
.result-stats .count strong{ color:#003366; font-size:18px; }
@media(max-width:768px){ .search-container .search-box{ flex-wrap:wrap; } .search-container .search-box input{ min-width:100%; } .facultes-stats{ flex-direction:column; } .faculte-stat-card{ min-width:unset; } .add-container{ flex-direction:column; } .add-container .left-actions{ flex-direction:column; } .add-container .left-actions a{ width:100%; text-align:center; justify-content:center; } }
</style>
</head>
<body>
<?php include("menu.php"); ?>

<header><h1>GESTION DES DÉPARTEMENTS</h1></header>

<?php if($success == 'suppression'): ?>
    <div class="alert-success">✅ Département supprimé avec succès !</div>
<?php endif; ?>

<!-- BARRE DE RECHERCHE -->
<div class="search-container">
    <form method="GET" action="" style="width:100%; display:flex; gap:15px; align-items:center; flex-wrap:wrap;">
        <div class="search-box">
            <input type="text" name="search_faculte" placeholder="🔍 Rechercher par faculté ou nom de département..." value="<?= htmlspecialchars($rechercheFaculte) ?>">
            <button type="submit">🔍 Rechercher</button>
            <?php if(!empty($rechercheFaculte)): ?>
                <a href="affichedepartement.php" class="btn-reset" style="background:#64748b; color:white; padding:12px 24px; border-radius:10px; font-weight:bold; text-decoration:none;">✖️ Réinitialiser</a>
            <?php endif; ?>

            <?php 
            $export_url = 'export_excel_universel.php?table=departements&filename=Liste_Departements';
            if (!empty($_GET)) {
                $export_url .= '&' . http_build_query($_GET);
            }
            ?>
            <a href="<?= $export_url ?>" class="btn-excel" onclick="return confirm('Exporter les données en Excel ?')">
                📊 Exporter Excel
            </a>
        </div>
        <?php if(!empty($rechercheFaculte)): ?>
            <div style="font-size:14px; color:#64748b; white-space:nowrap;">
                <strong><?= count($departements) ?></strong> département(s) trouvé(s)
            </div>
        <?php endif; ?>
    </form>
</div>

<!-- STATISTIQUES -->
<div class="facultes-stats">
    <div class="faculte-stat-card" style="border-left-color:#8b5cf6;">
        <span class="stat-icon">🏛️</span>
        <div class="stat-info">
            <span class="stat-name">Total Départements</span>
            <span class="stat-count"><?= $totalDepartements ?></span>
        </div>
    </div>
    <div class="faculte-stat-card" style="border-left-color:#3b82f6;">
        <span class="stat-icon">🎓</span>
        <div class="stat-info">
            <span class="stat-name">Total Facultés</span>
            <span class="stat-count"><?= $totalFacultes ?></span>
        </div>
    </div>
    <?php foreach($statsFacultes as $stat): ?>
        <div class="faculte-stat-card" style="border-left-color:#10b981;">
            <span class="stat-icon">📚</span>
            <div class="stat-info">
                <span class="stat-name"><?= htmlspecialchars($stat['faculte']) ?></span>
                <span class="stat-count"><?= $stat['nb_departements'] ?> département(s)</span>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- RÉSULTATS -->
<?php if(!empty($rechercheFaculte)): ?>
<div class="result-stats">
    <div class="count"><strong><?= count($departements) ?></strong> résultat(s) trouvé(s)</div>
    <span style="background:#eff6ff; color:#1e40af; padding:4px 12px; border-radius:12px; font-size:12px; font-weight:600;">🔍 "<?= htmlspecialchars($rechercheFaculte) ?>"</span>
</div>
<?php endif; ?>

<div class="add-container">
    <div class="left-actions">
        <a href="ajoutedepartement.php" class="ajouter">+ Ajouter un département</a>
    </div>
</div>

<div class="table-card">
    <table>
        <caption><h2>📋 Liste des Départements</h2></caption>
        <tr>
            <th>N°</th>
            <th>Nom Département</th>
            <th>Faculté</th>
            <th>Description</th>
            <th>Actions</th>
        </tr>

        <?php if(count($departements) > 0): ?>
            <?php $i = 1; foreach($departements as $d): ?>
            <tr>
                <td><?= $i++; ?></td>
                <td><strong><?= htmlspecialchars($d['nom_dept']) ?></strong></td>
                <td><span class="faculte-badge"><?= htmlspecialchars($d['faculte'] ?: 'Non assignée') ?></span></td>
                <td><?= htmlspecialchars($d['description'] ?: '-') ?></td>
                <td>
                    <a href="modifidepartement.php?modif=<?= $d['id_dept'] ?>" class="modifier">✏️ Modifier</a>
                    <a href="affichedepartement.php?supp=<?= $d['id_dept'] ?>" class="supprimer" onclick="return confirm('Voulez-vous vraiment supprimer ce département ?');">🗑️ Supprimer</a>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="5" style="padding:30px; color:#64748b; font-style:italic; text-align:center;">
                    📭 Aucun département trouvé.
                </td>
            </tr>
        <?php endif; ?>
    </table>
</div>

<footer>© <?= date('Y') ?> Université du Burundi</footer>
</body>
</html>