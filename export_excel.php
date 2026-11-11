<?php
require_once __DIR__ . '/db.php';
// Active le tampon de sortie pour éviter toute fuite de texte avant les headers
ob_start();

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 1. CONNEXION À LA BASE DE DONNÉES
try {
    $bdd = getDBConnection();
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// 2. RECUPERATION DES FILTRES
$filtre_dept = isset($_GET['filtre_dept']) ? intval($_GET['filtre_dept']) : 0;
$filtre_faculte = isset($_GET['filtre_faculte']) ? trim($_GET['filtre_faculte']) : '';
$filtre_classe = isset($_GET['filtre_classe']) ? intval($_GET['filtre_classe']) : 0;

// 3. REQUÊTE AVEC FACULTÉ
$queryNotes = "
    SELECT n.note, 
           e.annee_academique_entree AS annee_academique,
           n.date_saisie, 
           co.nom_cours AS matiere, 
           u.nom AS nom_etu, 
           u.prenom AS prenom_etu, 
           c.niveau, 
           d.nom_dept,
           d.faculte
    FROM notes n
    JOIN etudiants e ON n.id_etudiant = e.id_etudiant
    JOIN users u ON e.id_user = u.id_user
    JOIN classes c ON e.id_classe = c.id_classe
    JOIN departements d ON c.id_dept = d.id_dept
    JOIN cours co ON n.id_cours = co.id_cours
    WHERE 1=1
";

$params = [];
if ($filtre_dept > 0) {
    $queryNotes .= " AND d.id_dept = ? ";
    $params[] = $filtre_dept;
}
if (!empty($filtre_faculte)) {
    $queryNotes .= " AND d.faculte = ? ";
    $params[] = $filtre_faculte;
}
if ($filtre_classe > 0) {
    $queryNotes .= " AND c.id_classe = ? ";
    $params[] = $filtre_classe;
}
$queryNotes .= " ORDER BY d.nom_dept, c.niveau, u.nom, co.nom_cours ASC ";

try {
    $req = $bdd->prepare($queryNotes);
    $req->execute($params);
    $notes = $req->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    ob_end_clean();
    die("Erreur SQL lors de la récupération des données. Détails : " . $e->getMessage());
}

// Nettoie le tampon pour s'assurer qu'aucun écho ou warning préalable ne pollue le fichier
ob_end_clean();

// 4. CONFIGURATION STRICTE DES ENTÊTES DE TÉLÉCHARGEMENT
$filename = "Notes_Etudiants_" . date('Ymd_His') . ".xls";

header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header("Expires: 0");

// Début du flux de données Excel
?>
<meta charset="utf-8">
<style>
    .title { font-family: 'Segoe UI', sans-serif; font-size: 16pt; font-weight: bold; color: #1e293b; text-align: center; }
    .header { background-color: #0f172a; color: #ffffff; font-weight: bold; font-family: 'Segoe UI', sans-serif; font-size: 11pt; text-align: left; }
    .data { font-family: 'Segoe UI', sans-serif; font-size: 10pt; border-bottom: 1px solid #e2e8f0; }
    .valide { background-color: #d1fae5; color: #065f46; font-weight: bold; }
    .ajour { background-color: #fee2e2; color: #991b1b; font-weight: bold; }
</style>

<table>
    <tr>
        <td colspan="8" class="title">RAPPORT GLOBAL DES NOTES COMPILÉES — UNIVERSITÉ</td>
    </tr>
    <tr>
        <td colspan="8" style="text-align:center; font-style:italic;">Généré le : <?= date('d/m/Y H:i'); ?></td>
    </tr>
    <tr><td colspan="8"></td></tr>
    <thead>
        <tr>
            <th class="header" style="width: 150px;">Département</th>
            <th class="header" style="width: 150px;">Faculté</th>
            <th class="header" style="width: 120px;">Classe / Niveau</th>
            <th class="header" style="width: 140px;">Année Académique</th>
            <th class="header" style="width: 230px;">Nom & Prénom Étudiant</th>
            <th class="header" style="width: 180px;">Matière / Cours</th>
            <th class="header" style="width: 130px;">Date Saisie</th>
            <th class="header" style="width: 90px; text-align:right;">Note / 20</th>
        </tr>
    </thead>
    <tbody>
        <?php if(empty($notes)): ?>
            <tr>
                <td colspan="8" style="text-align: center; font-size:11pt; padding: 20px;">Aucune donnée disponible pour les critères sélectionnés.</td>
            </tr>
        <?php else: ?>
            <?php foreach($notes as $row): 
                $styleNote = ($row['note'] >= 10) ? 'valide' : 'ajour';
                $dateAffichage = (empty($row['date_saisie']) || $row['date_saisie'] === '0000-00-00') 
                    ? '-' 
                    : date('d/m/Y', strtotime($row['date_saisie']));
            ?>
                <tr>
                    <td class="data"><?= htmlspecialchars($row['nom_dept']); ?></td>
                    <td class="data"><?= htmlspecialchars($row['faculte'] ?: 'Non assignée'); ?></td>
                    <td class="data"><?= htmlspecialchars($row['niveau']); ?></td>
                    <td class="data" style="text-align:center; font-weight:600;"><?= htmlspecialchars($row['annee_academique'] ?: '-'); ?></td>
                    <td class="data"><strong><?= htmlspecialchars(strtoupper($row['nom_etu'])) . ' ' . htmlspecialchars($row['prenom_etu']); ?></strong></td>
                    <td class="data"><?= htmlspecialchars($row['matiere']); ?></td>
                    <td class="data" style="text-align:center;"><?= htmlspecialchars($dateAffichage); ?></td>
                    <td class="data <?= $styleNote; ?>" style="text-align:right;"><?= number_format($row['note'], 2, ',', ' '); ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
<?php
exit;
?>