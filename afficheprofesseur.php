<?php
require_once __DIR__ . '/db.php';
require_once 'auth.php';
requireRole(['Administrateur', 'Doyen']);
try {
    $bdd = getDBConnection();
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die('Erreur : ' . $e->getMessage());
}

include("menu.php");

/* ============================================================
   SUPPRESSION D'UN PROFESSEUR
============================================================ */
if(isset($_GET['supp']) && !empty($_GET['supp'])){
    $idSupp = (int)$_GET['supp'];
    try {
        $bdd->beginTransaction();
        $getUserId = $bdd->prepare("SELECT id_user FROM professeurs WHERE id_prof = ?");
        $getUserId->execute([$idSupp]);
        $profData = $getUserId->fetch(PDO::FETCH_ASSOC);

        if ($profData) {
            $idUserLie = $profData['id_user'];
            $suppProf = $bdd->prepare("DELETE FROM professeurs WHERE id_prof = ?");
            $suppProf->execute([$idSupp]);
            $suppUser = $bdd->prepare("DELETE FROM users WHERE id_user = ?");
            $suppUser->execute([$idUserLie]);
        }
        $bdd->commit();
        echo "<script>alert('Professeur supprimé avec succès.'); window.location='afficheprofesseur.php';</script>";
    } catch (Exception $e) {
        $bdd->rollBack();
        echo "<script>alert('Erreur lors de la suppression : " . addslashes($e->getMessage()) . "');</script>";
    }
    exit();
}

/* ============================================================
   FILTRES
============================================================ */
$rechercheDiplome = isset($_GET['diplome']) ? trim($_GET['diplome']) : "";
$filtreDept       = isset($_GET['departement']) ? trim($_GET['departement']) : "";

$limite = 7;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if($page < 1) $page = 1;
$debut = ($page - 1) * $limite;

$where = [];
$params = [];

if(!empty($rechercheDiplome)){
    $where[] = "p.diplome LIKE ?";
    $params[] = "%" . $rechercheDiplome . "%";
}
if(!empty($filtreDept)){
    $where[] = "d.id_dept = ?";
    $params[] = $filtreDept;
}

$sqlWhere = count($where) > 0 ? " WHERE " . implode(" AND ", $where) : "";

$sqlTotal = "
    SELECT COUNT(DISTINCT p.id_prof) AS total 
    FROM professeurs p 
    INNER JOIN users u ON p.id_user = u.id_user
    LEFT JOIN cours c ON p.id_prof = c.id_prof 
    LEFT JOIN classes cl ON c.id_classe = cl.id_classe 
    LEFT JOIN departements d ON cl.id_dept = d.id_dept 
    $sqlWhere
";
$stmtTotal = $bdd->prepare($sqlTotal);
$stmtTotal->execute($params);
$total = $stmtTotal->fetch(PDO::FETCH_ASSOC)['total'];

$nbPages = ceil($total / $limite) < 1 ? 1 : ceil($total / $limite);
if($page > $nbPages) { $page = $nbPages; $debut = ($page - 1) * $limite; }

$sqlProf = "
    SELECT 
        p.id_prof, u.nom, u.prenom, p.specialite, p.diplome, u.telephone, 
        u.email AS email_perso, u.email_auth AS email_academique,
        GROUP_CONCAT(DISTINCT d.nom_dept ORDER BY d.nom_dept SEPARATOR ', ') AS departements,
        GROUP_CONCAT(DISTINCT cl.niveau ORDER BY cl.niveau SEPARATOR ', ') AS classes
    FROM professeurs p
    INNER JOIN users u ON p.id_user = u.id_user
    LEFT JOIN cours c ON p.id_prof = c.id_prof
    LEFT JOIN classes cl ON c.id_classe = cl.id_classe
    LEFT JOIN departements d ON cl.id_dept = d.id_dept
    $sqlWhere
    GROUP BY p.id_prof
    ORDER BY p.id_prof DESC
    LIMIT $debut, $limite
";
$stmtProf = $bdd->prepare($sqlProf);
$stmtProf->execute($params);

function buildQuery($extra = []) {
    $query = $_GET;
    foreach($extra as $key => $value) $query[$key] = $value;
    return http_build_query($query);
}

// Récupérer les départements pour le filtre
$departements = $bdd->query("SELECT id_dept, nom_dept FROM departements ORDER BY nom_dept");
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion des Professeurs</title>
    <style>
        *{ margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI', Arial, sans-serif; }
        body{ background:#eef2f7; color:#1f2937; }
        .container{ width:95%; margin:30px auto; }
        .page-header{ display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px; margin-bottom:22px; }
        .page-header h1{ color:#003366; font-size:30px; font-weight:800; }
        .top-actions{ display:flex; gap:10px; flex-wrap:wrap; }
        .ajouter{ background:linear-gradient(135deg,#0066cc,#004c99); color:white; text-decoration:none; padding:12px 18px; border-radius:10px; font-weight:700; }
        .btn-excel{ background:#10b981; color:white; text-decoration:none; padding:12px 18px; border-radius:10px; font-weight:700; display:inline-flex; align-items:center; gap:8px; transition:0.3s; }
        .btn-excel:hover{ background:#059669; transform:translateY(-2px); }
        .filters{ background:#fff; padding:20px; border-radius:18px; box-shadow:0 10px 25px rgba(0,0,0,.07); display:grid; grid-template-columns:repeat(3,1fr); gap:15px; margin-bottom:20px; align-items:center; }
        .filters select, .filters input{ padding:12px; border-radius:10px; border:1px solid #d1d5db; outline:none; width:100%; font-size:14px; }
        .filters input:focus, .filters select:focus{ border-color:#0055aa; box-shadow:0 0 0 3px rgba(0,85,170,.10); }
        .btn-filter{ background:#003366; color:white; border:none; border-radius:10px; cursor:pointer; font-weight:700; padding:12px; height:100%; }
        .btn-filter:hover{ background:#004488; }
        .btn-reset{ background:#e5e7eb; color:#111827; text-align:center; padding:12px; border-radius:10px; text-decoration:none; font-weight:700; }
        .btn-reset:hover{ background:#d1d5db; }
        .result-stats{ background:white; padding:12px 20px; border-radius:12px; margin-bottom:15px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; border:1px solid #e5e7eb; }
        .result-stats .count{ font-weight:600; }
        .result-stats .count strong{ color:#003366; font-size:18px; }
        .table-card{ background:#fff; border-radius:18px; box-shadow:0 10px 25px rgba(0,0,0,.07); overflow-x:auto; }
        .table-header{ padding:18px; display:flex; justify-content:space-between; border-bottom:1px solid #edf0f4; }
        .table-header h2{ color:#003366; }
        .table-header .total{ color:#64748b; font-weight:600; font-size:14px; }
        table{ width:100%; border-collapse:collapse; min-width:1300px; }
        th{ background:#003366; color:white; padding:14px; font-size:13px; }
        td{ padding:12px; text-align:center; border-bottom:1px solid #eef2f7; }
        tr:hover{ background:#f8fbff; }
        .badge{ display:inline-block; padding:6px 12px; border-radius:999px; font-size:12px; font-weight:700; }
        .badge-diplome{ background:#eff6ff; color:#1d4ed8; }
        .badge-dept{ background:#ecfdf3; color:#15803d; }
        .badge-classe{ background:#fff7ed; color:#c2410c; }
        .actions-cell a{ text-decoration:none; color:white; padding:8px 12px; border-radius:8px; font-weight:700; display:inline-block; margin:2px; }
        .modifier{ background:#16a34a; }
        .modifier:hover{ background:#15803d; }
        .supprimer{ background:#dc2626; }
        .supprimer:hover{ background:#b91c1c; }
        .pagination{ display:flex; justify-content:center; align-items:center; gap:15px; margin-top:20px; padding:15px; flex-wrap:wrap; }
        .pagination a{ background:#003366; color:white; text-decoration:none; padding:10px 18px; border-radius:8px; font-weight:bold; transition:background 0.3s; }
        .pagination a:hover{ background:#00509e; }
        .page-indicator{ padding:10px 18px; background:white; border-radius:8px; border:1px solid #dbe3ec; font-weight:700; }
        .filter-actions{ display:flex; gap:10px; grid-column:span 1; }
        @media(max-width:900px){ .filters{ grid-template-columns:repeat(2,1fr); } .filter-actions{ grid-column:span 2; } }
        @media(max-width:600px){ .filters{ grid-template-columns:1fr; } .filter-actions{ grid-column:span 1; } .page-header{ flex-direction:column; align-items:flex-start; } }
    </style>
</head>
<body>
<div class="container">
    <div class="page-header">
        <h1>👨‍🏫 Gestion des Professeurs</h1>
        <div class="top-actions">
            <a href="ajouteprofesseur.php" class="ajouter">+ Ajouter professeur</a>
            
            <!-- ============================================================
               BOUTON D'EXPORT EXCEL - INCLUS
            ============================================================ -->
            <?php 
            $export_url = 'export_excel_universel.php?table=professeurs&filename=Liste_Professeurs';
            if (!empty($_GET)) {
                $export_url .= '&' . http_build_query($_GET);
            }
            ?>
            <a href="<?= $export_url ?>" class="btn-excel" onclick="return confirm('Exporter les données en Excel ?')">
                📊 Exporter Excel
            </a>
        </div>
    </div>

    <!-- FILTRES -->
    <form method="GET" class="filters">
        <input type="text" name="diplome" placeholder="🔍 Rechercher par diplôme..." value="<?= htmlspecialchars($rechercheDiplome) ?>">
        <select name="departement">
            <option value="">Tous les départements</option>
            <?php while($d = $departements->fetch()): ?>
                <option value="<?= $d['id_dept'] ?>" <?= ($filtreDept == $d['id_dept']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($d['nom_dept']) ?>
                </option>
            <?php endwhile; ?>
        </select>
        <div class="filter-actions">
            <button type="submit" class="btn-filter">🔍 Filtrer</button>
            <a href="afficheprofesseur.php" class="btn-reset">✖️ Réinitialiser</a>
        </div>
    </form>

    <!-- STATISTIQUES -->
    <div class="result-stats">
        <div class="count"><strong><?= $total ?></strong> enseignant(s) trouvé(s)</div>
        <?php if(!empty($rechercheDiplome)): ?>
            <span style="background:#eff6ff; color:#1e40af; padding:4px 12px; border-radius:12px; font-size:12px; font-weight:600;">Diplôme: <?= htmlspecialchars($rechercheDiplome) ?></span>
        <?php endif; ?>
        <?php if(!empty($filtreDept)): ?>
            <span style="background:#eff6ff; color:#1e40af; padding:4px 12px; border-radius:12px; font-size:12px; font-weight:600;">Département filtré</span>
        <?php endif; ?>
    </div>

    <div class="table-card">
        <div class="table-header">
            <h2>📋 Liste des professeurs</h2>
            <span class="total"><?= $total ?> enseignant(s)</span>
        </div>
        <table>
            <thead>
                <tr>
                    <th>N°</th>
                    <th>Nom</th>
                    <th>Prénom</th>
                    <th>Spécialité</th>
                    <th>Diplôme</th>
                    <th>Téléphone</th>
                    <th>Email Perso</th>
                    <th>Email Académique</th>
                    <th>Départements</th>
                    <th>Classes</th>
                    <th colspan="2">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $numero = $debut + 1;
                while($dataProf = $stmtProf->fetch(PDO::FETCH_ASSOC)){
                ?>
                <tr>
                    <td><?php echo $numero++; ?></td>
                    <td><strong><?php echo htmlspecialchars($dataProf["nom"]); ?></strong></td>
                    <td><?php echo htmlspecialchars($dataProf["prenom"]); ?></td>
                    <td><?php echo htmlspecialchars($dataProf["specialite"]); ?></td>
                    <td><span class="badge badge-diplome"><?php echo htmlspecialchars($dataProf["diplome"] ?: '-'); ?></span></td>
                    <td><?php echo htmlspecialchars($dataProf["telephone"] ?: '-'); ?></td>
                    <td><?php echo htmlspecialchars($dataProf["email_perso"] ?: '-'); ?></td>
                    <td style="color:#0066cc; font-weight:bold;"><?php echo htmlspecialchars($dataProf["email_academique"]); ?></td>
                    <td><span class="badge badge-dept"><?php echo htmlspecialchars($dataProf["departements"] ?: '-'); ?></span></td>
                    <td><span class="badge badge-classe"><?php echo htmlspecialchars($dataProf["classes"] ?: '-'); ?></span></td>
                    <td><a href="modifiprofesseur.php?id=<?php echo $dataProf['id_prof']; ?>" class="btn modifier">✏️</a></td>
                    <td><a href="afficheprofesseur.php?supp=<?php echo $dataProf['id_prof']; ?>" class="btn supprimer" onclick="return confirm('Voulez-vous supprimer ce professeur ?')">🗑️</a></td>
                </tr>
                <?php } ?>
                <?php if($total == 0): ?>
                <tr>
                    <td colspan="12" style="padding:30px; color:#64748b; text-align:center;">📭 Aucun professeur trouvé.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- PAGINATION -->
    <?php if($nbPages > 1): ?>
    <div class="pagination">
        <?php if($page > 1): ?>
            <a href="?<?= buildQuery(['page' => $page - 1]) ?>">← Précédent</a>
        <?php endif; ?>
        <div class="page-indicator">Page <?= $page ?> / <?= $nbPages ?></div>
        <?php if($page < $nbPages): ?>
            <a href="?<?= buildQuery(['page' => $page + 1]) ?>">Suivant →</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
</body>
</html>