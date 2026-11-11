<?php
require_once __DIR__ . '/db.php';
require_once 'auth.php';                    
requireRole(['Administrateur', 'Doyen', 'Agent']);  
// ============================================================
// DÉMARRAGE DE SESSION — AVANT tout envoi HTML
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $bdd = getDBConnection();
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    error_log("[afficheclasse] Erreur connexion : " . $e->getMessage());
    die("Erreur de connexion à la base de données.");
}

// Inclusion du système d'archivage centralisé
require_once 'functions_archivage.php';

/* ============================================================
   SUPPRESSION + ARCHIVAGE CENTRALISÉ
============================================================ */
if (isset($_GET['supp']) && !empty($_GET['supp'])) {

    $idSupp = (int) $_GET['supp'];

    if ($idSupp > 0) {

        try {
            // 1. Vérifier que la classe existe
            $check = $bdd->prepare("SELECT id_classe FROM classes WHERE id_classe = ? LIMIT 1");
            $check->execute([$idSupp]);
            if (!$check->fetchColumn()) {
                throw new Exception("Classe introuvable.");
            }

            // 2. Récupérer les données AVANT suppression
            $ancienne_valeur = getDonneesEnregistrement($bdd, 'classes', $idSupp);
            if (empty($ancienne_valeur)) {
                throw new Exception("Impossible de récupérer les données de la classe.");
            }

            // 3. Archivage AVANT la suppression
            $archive_id = archiverAction(
                $bdd,
                getUserIdEnSession($bdd),
                'DELETE',
                'classes',
                $idSupp,
                $ancienne_valeur,
                null,
                'Suppression de la classe'
            );

            // Si archiverAction() retourne false, on refuse la suppression
            if ($archive_id === false) {
                throw new Exception("Échec de l'archivage : suppression annulée.");
            }

            // 4. Suppression effective
            $stmt = $bdd->prepare("DELETE FROM classes WHERE id_classe = ?");
            $stmt->execute([$idSupp]);

            // 5. Confirmation
            echo "<script>
                alert('✅ Classe supprimée avec succès.');
                window.location='afficheclasse.php';
            </script>";
            exit();

        } catch (Exception $e) {
            error_log("[afficheclasse] Erreur suppression : " . $e->getMessage());
            $msg = "Impossible de supprimer cette classe. Vérifiez qu'elle n'est pas utilisée ailleurs.";
            echo "<script>alert(" . json_encode($msg) . "); window.location='afficheclasse.php';</script>";
            exit();
        }

    } else {
        header("Location: afficheclasse.php");
        exit();
    }
}

/* ============================================================
   FILTRES
============================================================ */
$filtreDept    = isset($_GET['departement']) ? (int) $_GET['departement'] : 0;
$filtreFaculte = isset($_GET['faculte']) ? trim($_GET['faculte']) : '';

$stmtDepts = $bdd->query("SELECT id_dept, nom_dept, faculte FROM departements ORDER BY nom_dept");
$departements = $stmtDepts->fetchAll(PDO::FETCH_ASSOC);

$facultesFiltrees = [];
if ($filtreDept > 0) {
    $stmt = $bdd->prepare("SELECT faculte FROM departements WHERE id_dept = ? LIMIT 1");
    $stmt->execute([$filtreDept]);
    $faculte = $stmt->fetchColumn();
    if ($faculte) {
        $facultesFiltrees = [$faculte];
    }
}

/* ============================================================
   REQUÊTE PRINCIPALE
============================================================ */
$sql = "SELECT c.*, d.nom_dept, d.faculte
        FROM classes c
        INNER JOIN departements d ON c.id_dept = d.id_dept
        WHERE 1=1";
$params = [];

if ($filtreDept > 0) {
    $sql .= " AND d.id_dept = ?";
    $params[] = $filtreDept;
}
if (!empty($filtreFaculte)) {
    $sql .= " AND d.faculte = ?";
    $params[] = $filtreFaculte;
}
$sql .= " ORDER BY c.id_classe DESC";

$stmt = $bdd->prepare($sql);
$stmt->execute($params);
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion des Classes</title>
    <style>
        *{ margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI', Arial, sans-serif; }
        body{ background:#eef3f8; color:#1f2937; }
        .container{ width:95%; margin:30px auto; }
        header{ background:linear-gradient(135deg,#003366,#0055aa); color:white; text-align:center; padding:25px; border-radius:12px; box-shadow:0 4px 10px rgba(0,0,0,0.2); margin-bottom:20px; }
        .filters{ background:#fff; padding:20px; border-radius:18px; box-shadow:0 10px 25px rgba(0,0,0,.07); display:grid; grid-template-columns:repeat(4,1fr); gap:15px; margin-bottom:20px; align-items:center; }
        .filters select{ padding:12px; border-radius:10px; border:1px solid #d1d5db; outline:none; width:100%; }
        .filters select:disabled{ background:#f1f5f9; cursor:not-allowed; opacity:0.7; }
        .btn{ background:#003366; color:white; border:none; border-radius:10px; cursor:pointer; font-weight:700; padding:12px; height:100%; }
        .btn:hover{ background:#004488; }
        .btn-reset{ background:#e5e7eb; color:#111827; text-align:center; padding:12px; border-radius:10px; text-decoration:none; font-weight:700; }
        .btn-reset:hover{ background:#d1d5db; }
        .btn-excel{ background:#10b981; color:white; border:none; border-radius:10px; cursor:pointer; font-weight:700; padding:12px; height:100%; text-decoration:none; display:inline-flex; align-items:center; justify-content:center; gap:8px; }
        .btn-excel:hover{ background:#059669; }
        .add-container{ display:flex; justify-content:flex-end; margin-bottom:15px; gap:10px; flex-wrap:wrap; }
        .ajouter{ background:linear-gradient(135deg,#0066cc,#004c99); color:white; padding:12px 18px; border-radius:10px; text-decoration:none; font-weight:700; }
        .table-card{ background:#fff; border-radius:18px; box-shadow:0 10px 25px rgba(0,0,0,.07); overflow-x:auto; }
        .table-header{ padding:18px; display:flex; justify-content:space-between; border-bottom:1px solid #edf0f4; }
        .table-header h2{ color:#003366; }
        .table-header .total{ color:#64748b; font-weight:600; font-size:14px; }
        table{ width:100%; border-collapse:collapse; min-width:800px; }
        th{ background:#003366; color:white; padding:14px; font-size:13px; }
        td{ padding:12px; text-align:center; border-bottom:1px solid #eef2f7; }
        tr:hover{ background:#f8fbff; }
        .actions-cell a{ text-decoration:none; color:white; padding:8px 12px; border-radius:8px; font-weight:700; display:inline-block; margin:2px; }
        .modifier{ background:#16a34a; }
        .modifier:hover{ background:#15803d; }
        .supprimer{ background:#dc2626; }
        .supprimer:hover{ background:#b91c1c; }
        .faculte-badge{ display:inline-block; padding:2px 10px; border-radius:12px; background:#eff6ff; color:#1e40af; font-weight:600; font-size:11px; }
        .classe-badge{ display:inline-block; padding:4px 14px; border-radius:20px; background:#dbeafe; color:#1e40af; font-weight:700; font-size:13px; }
        .dept-badge{ display:inline-block; padding:3px 12px; border-radius:12px; background:#f1f5f9; color:#475569; font-weight:600; font-size:12px; }
        @media(max-width:900px){ .filters{ grid-template-columns:repeat(2,1fr); } }
        @media(max-width:600px){ .filters{ grid-template-columns:1fr; } }
    </style>
</head>
<body>
<?php include("menu.php"); ?>
<header><h1>GESTION DES CLASSES</h1></header>
<div class="container">
    <form method="GET" class="filters">
        <select name="departement" onchange="this.form.submit()">
            <option value="0">Département</option>
            <?php foreach($departements as $d): ?>
                <option value="<?= (int) $d['id_dept'] ?>" <?= ($filtreDept == $d['id_dept']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($d['nom_dept']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="faculte" <?= empty($filtreDept) ? 'disabled' : '' ?>>
            <option value="">Faculté</option>
            <?php foreach($facultesFiltrees as $f): ?>
                <option value="<?= htmlspecialchars($f) ?>" <?= ($filtreFaculte == $f) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($f) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <div style="display:flex; gap:10px; grid-column:span 2;">
            <button type="submit" class="btn">Filtrer</button>
            <a class="btn-reset" href="afficheclasse.php">Réinitialiser</a>

            <?php
            $export_url = 'export_excel_universel.php?table=classes&filename=Liste_Classes';
            if (!empty($_GET)) {
                $export_url .= '&' . http_build_query($_GET);
            }
            ?>
            <a href="<?= $export_url ?>" class="btn-excel" onclick="return confirm('Exporter les données en Excel ?')">
                📊 Exporter Excel
            </a>
        </div>
    </form>

    <div class="add-container">
        <a href="ajouteclassedepartement.php" class="ajouter">+ Ajouter Classe</a>
    </div>

    <div class="table-card">
        <div class="table-header">
            <h2>📚 Liste des classes</h2>
            <span class="total"><?= count($classes) ?> classe(s) trouvée(s)</span>
        </div>
        <table>
            <thead>
                <tr>
                    <th>N°</th>
                    <th>Classe</th>
                    <th>Département</th>
                    <th>Faculté</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if(count($classes) > 0): ?>
                <?php $i = 1; foreach($classes as $row): ?>
                <tr>
                    <td><?= $i++; ?></td>
                    <td><span class="classe-badge"><?= htmlspecialchars($row['niveau']) ?></span></td>
                    <td><span class="dept-badge"><?= htmlspecialchars($row['nom_dept']) ?></span></td>
                    <td><span class="faculte-badge"><?= htmlspecialchars($row['faculte'] ?: 'Non assignée') ?></span></td>
                    <td class="actions-cell">
                        <a class="modifier" href="modificlassedepartement.php?id=<?= $row['id_classe'] ?>">✏️ Modifier</a>
                        <a href="afficheclasse.php?supp=<?= $row['id_classe']; ?>" class="supprimer" onclick="return confirm('Voulez-vous vraiment supprimer cette classe ?');">🗑️ Supprimer</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" style="padding:30px; color:#64748b; font-style:italic; text-align:center;">
                        📭 Aucune classe trouvée.
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>