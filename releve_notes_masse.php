<?php
require_once __DIR__ . '/db.php';
// ============================================================
// RELEVÉ DE NOTES - TOUS LES ÉTUDIANTS DU DÉPARTEMENT
// ============================================================
require_once 'auth.php';
requireRole('Doyen');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $bdd = getDBConnection();
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

/* ============================================================
   ✅ DÉPARTEMENT DU DOYEN (récupéré en BDD)
============================================================ */
$stmtDept = $bdd->prepare("SELECT id_dept FROM doyens WHERE id_user = ?");
$stmtDept->execute([$USER_ID]);
$id_dept_doyen = $stmtDept->fetchColumn();

if (!$id_dept_doyen) {
    die("Aucun département assigné à ce doyen.");
}

/* ============================================================
   ✅ FILTRE : impossible de changer de département via GET
============================================================ */
$id_dept_doyen = (int)$id_dept_doyen;

$filtre_classe = isset($_GET['id_classe']) ? intval($_GET['id_classe']) : 0;
$filtre_annee  = isset($_GET['annee']) ? trim($_GET['annee']) : '';

$where = "cl.id_dept = ?";
$params = [$id_dept_doyen];

if ($filtre_classe > 0) {
    $where .= " AND e.id_classe = ?";
    $params[] = $filtre_classe;
}
if (!empty($filtre_annee)) {
    $where .= " AND e.annee_academique_entree = ?";
    $params[] = $filtre_annee;
}

$sql = "SELECT e.id_etudiant, u.nom, u.prenom, e.matricule, 
               cl.niveau, d.nom_dept, d.faculte,
               COALESCE(AVG(n.note), 0) as moyenne,
               COALESCE(SUM(c.credit), 0) as credits_obtenus,
               COUNT(n.id_note) as nb_notes,
               COALESCE((SELECT SUM(p.montant) FROM paiements p WHERE p.id_etudiant = e.id_etudiant AND p.statut = 'valide'), 0) as total_paye
        FROM etudiants e
        JOIN users u ON e.id_user = u.id_user
        JOIN classes cl ON e.id_classe = cl.id_classe
        JOIN departements d ON cl.id_dept = d.id_dept
        LEFT JOIN notes n ON e.id_etudiant = n.id_etudiant AND n.statut = 'valide'
        LEFT JOIN cours c ON n.id_cours = c.id_cours
        WHERE $where
        GROUP BY e.id_etudiant
        ORDER BY cl.niveau, u.nom ASC";

$stmt = $bdd->prepare($sql);
$stmt->execute($params);
$etudiants = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalEtudiants = count($etudiants);
$totalReussite = 0;
$sommeMoyennes = 0;

foreach ($etudiants as $e) {
    if ($e['moyenne'] >= 10) {
        $totalReussite++;
    }
    $sommeMoyennes += $e['moyenne'];
}
$moyenneGlobale = $totalEtudiants > 0 ? round($sommeMoyennes / $totalEtudiants, 2) : 0;
$tauxReussite = $totalEtudiants > 0 ? round(($totalReussite / $totalEtudiants) * 100, 1) : 0;

require_once 'vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$nomDept = isset($etudiants[0]['nom_dept']) ? $etudiants[0]['nom_dept'] : 'INFORMATIQUE';
$niveauClasse = isset($etudiants[0]['niveau']) ? $etudiants[0]['niveau'] : 'Toutes';

$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Relevé de notes - Tous les étudiants</title>
    <style>
        body { font-family: "DejaVu Sans", Arial, sans-serif; margin: 15px; color: #1e293b; font-size: 11px; }
        .header { text-align: center; border-bottom: 3px solid #1e3a8a; padding-bottom: 12px; margin-bottom: 15px; }
        .header h1 { font-size: 20px; font-weight: 800; color: #1e3a8a; margin: 0; }
        .header h2 { font-size: 14px; font-weight: 600; color: #2563eb; margin: 4px 0 0 0; }
        .header .subtitle { font-size: 11px; color: #64748b; margin-top: 2px; }
        .filter-info { background: #f8fafc; padding: 8px 12px; border-radius: 6px; margin-bottom: 12px; border: 1px solid #e2e8f0; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; font-size: 10px; }
        table th { background: #1e3a8a; color: white; padding: 6px 8px; text-align: left; font-size: 10px; text-transform: uppercase; }
        table td { padding: 5px 8px; border-bottom: 1px solid #e2e8f0; }
        table tr:nth-child(even) td { background: #f8fafc; }
        .table-note { text-align: center; font-weight: 700; }
        .table-note.success { color: #10b981; }
        .table-note.fail { color: #ef4444; }
        .badge { display: inline-block; padding: 1px 8px; border-radius: 10px; font-size: 9px; font-weight: 700; }
        .badge.success { background: #d1fae5; color: #065f46; }
        .badge.fail { background: #fee2e2; color: #991b1b; }
        .stats { display: table; width: 100%; background: #f8fafc; padding: 10px 12px; border-radius: 6px; margin-top: 12px; border: 1px solid #e2e8f0; font-size: 11px; }
        .stats .col { display: table-cell; padding: 3px 10px; border-right: 1px solid #e2e8f0; }
        .stats .col:last-child { border-right: none; }
        .stats .number { font-weight: 700; font-size: 14px; color: #1e3a8a; }
        .stats .label { font-size: 10px; color: #64748b; }
        .footer { text-align: center; font-size: 9px; color: #94a3b8; margin-top: 15px; padding-top: 8px; border-top: 1px solid #e2e8f0; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Université Horizon Tanganyika (UHT)</h1>
        <h2>Relevé de notes - Département de ' . htmlspecialchars($nomDept) . '</h2>
        <div class="subtitle">Liste des étudiants et leurs résultats académiques</div>
    </div>

    <div class="filter-info">
        <strong>Département :</strong> ' . htmlspecialchars($nomDept) . ' &nbsp;|&nbsp;
        <strong>Année académique :</strong> ' . (!empty($filtre_annee) ? htmlspecialchars($filtre_annee) : 'Toutes') . ' &nbsp;|&nbsp;
        <strong>Classe :</strong> ' . ($filtre_classe > 0 && !empty($etudiants) ? htmlspecialchars($etudiants[0]['niveau']) : 'Toutes') . ' &nbsp;|&nbsp;
        <strong>Nombre d\'étudiants :</strong> ' . $totalEtudiants . '
    </div>

    <div class="stats">
        <div class="col"><div class="number">' . $totalEtudiants . '</div><div class="label">Étudiants</div></div>
        <div class="col"><div class="number">' . number_format($moyenneGlobale, 2) . '/20</div><div class="label">Moyenne générale</div></div>
        <div class="col"><div class="number">' . $totalReussite . '</div><div class="label">Admis</div></div>
        <div class="col"><div class="number">' . ($totalEtudiants - $totalReussite) . '</div><div class="label">Ajournés</div></div>
        <div class="col"><div class="number">' . $tauxReussite . '%</div><div class="label">Taux réussite</div></div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width:5%;">N°</th>
                <th style="width:15%;">Matricule</th>
                <th style="width:22%;">Nom & Prénom</th>
                <th style="width:12%;">Classe</th>
                <th style="width:10%;">Notes</th>
                <th style="width:10%;">Crédits</th>
                <th style="width:10%;">Moyenne /20</th>
                <th style="width:8%;">Résultat</th>
                <th style="width:8%;">Minerval</th>
            </tr>
        </thead>
        <tbody>';

if ($totalEtudiants > 0) {
    $i = 1;
    foreach ($etudiants as $e) {
        $moyenne = number_format($e['moyenne'], 2);
        $statut = $e['moyenne'] >= 10 ? 'ADMIS' : 'AJOURNÉ';
        $statutClass = $e['moyenne'] >= 10 ? 'success' : 'fail';
        $minerval = $e['total_paye'] >= 500000 ? '✅' : '⚠';

        $html .= '<tr>
            <td style="text-align:center;">' . $i++ . '</td>
            <td>' . htmlspecialchars($e['matricule']) . '</td>
            <td><strong>' . htmlspecialchars($e['nom'] . ' ' . $e['prenom']) . '</strong></td>
            <td>' . htmlspecialchars($e['niveau']) . '</td>
            <td style="text-align:center;">' . $e['nb_notes'] . '</td>
            <td style="text-align:center;">' . $e['credits_obtenus'] . '</td>
            <td class="table-note ' . $statutClass . '">' . $moyenne . '</td>
            <td style="text-align:center;"><span class="badge ' . $statutClass . '">' . $statut . '</span></td>
            <td style="text-align:center;">' . $minerval . '</td>
        </tr>';
    }
} else {
    $html .= '<tr><td colspan="9" style="text-align:center; color:#94a3b8; padding:20px;">Aucun étudiant trouvé dans ce département.</td></tr>';
}

$html .= '
        </tbody>
    </table>

    <div class="footer">
        <p>Document officiel généré par le système UniVerse</p>
        <div>Fait à Bujumbura, le ' . date('d/m/Y') . '</div>
        <div style="margin-top:6px;">Le Doyen/Doyenne du département</div>
        <p style="margin-top:6px; font-size:8px;">Les résultats affichés sont officiels et certifiés.</p>
    </div>
</body>
</html>';

$options = new Options();
$options->set('defaultFont', 'DejaVu Sans');
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

$filename = 'Releve_Notes_Departement_' . date('Y-m-d') . '.pdf';
$dompdf->stream($filename, array('Attachment' => 1));
exit;
?>