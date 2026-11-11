<?php
require_once __DIR__ . '/db.php';
// ============================================================
// RELEVÉ DE NOTES INDIVIDUEL - UniVerse
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
   ✅ DÉPARTEMENT DU DOYEN (récupéré en BDD, pas en dur)
============================================================ */
$stmtDept = $bdd->prepare("SELECT id_dept FROM doyens WHERE id_user = ?");
$stmtDept->execute([$USER_ID]);
$id_dept_doyen = $stmtDept->fetchColumn();

if (!$id_dept_doyen) {
    die("Aucun département assigné à ce doyen.");
}
$id_dept_doyen = (int)$id_dept_doyen;

$id_etudiant = isset($_GET['id_etudiant']) ? intval($_GET['id_etudiant']) : 0;
if ($id_etudiant <= 0) {
    die("ID étudiant invalide.");
}

/* ============================================================
   ✅ VÉRIFICATION : l'étudiant appartient au département du doyen
============================================================ */
$checkDept = $bdd->prepare("
    SELECT e.id_etudiant 
    FROM etudiants e 
    JOIN classes cl ON e.id_classe = cl.id_classe 
    WHERE e.id_etudiant = ? AND cl.id_dept = ?
");
$checkDept->execute([$id_etudiant, $id_dept_doyen]);

if (!$checkDept->fetch()) {
    http_response_code(403);
    die("⛔ Accès non autorisé à cet étudiant (hors de votre département).");
}

/* ============================================================
   DONNÉES ÉTUDIANT
============================================================ */
$reqEtudiant = $bdd->prepare("
    SELECT e.id_etudiant, e.matricule, 
           u.nom, u.prenom, u.email, u.telephone,
           c.niveau, d.nom_dept, d.faculte,
           e.annee_academique_entree
    FROM etudiants e
    JOIN users u ON e.id_user = u.id_user
    JOIN classes c ON e.id_classe = c.id_classe
    JOIN departements d ON c.id_dept = d.id_dept
    WHERE e.id_etudiant = ?
");
$reqEtudiant->execute([$id_etudiant]);
$etudiant = $reqEtudiant->fetch(PDO::FETCH_ASSOC);

if (!$etudiant) {
    die("Étudiant introuvable.");
}

$reqNotes = $bdd->prepare("
    SELECT n.note, n.session, n.date_saisie, n.date_validation, n.date_publication,
           co.nom_cours, co.credit, co.semestre,
           CONCAT(u.nom, ' ', u.prenom) AS enseignant
    FROM notes n
    JOIN cours co ON n.id_cours = co.id_cours
    LEFT JOIN professeurs p ON co.id_prof = p.id_prof
    LEFT JOIN users u ON p.id_user = u.id_user
    WHERE n.id_etudiant = ? 
      AND n.statut = 'valide' 
      AND n.date_publication IS NOT NULL
    ORDER BY co.semestre, co.nom_cours
");
$reqNotes->execute([$id_etudiant]);
$notes = $reqNotes->fetchAll(PDO::FETCH_ASSOC);

$totalNotes = count($notes);
$moyenneGlobale = 0;
$creditsTotal = 0;
$notesValidees = 0;

foreach ($notes as $n) {
    if ($n['note'] >= 10) {
        $notesValidees++;
    }
    if (!empty($n['credit'])) {
        $creditsTotal += $n['credit'];
    }
}

if ($totalNotes > 0) {
    $sommeNotes = array_sum(array_column($notes, 'note'));
    $moyenneGlobale = round($sommeNotes / $totalNotes, 2);
}

$tauxReussite = $totalNotes > 0 ? round(($notesValidees / $totalNotes) * 100, 1) : 0;
$statut = $moyenneGlobale >= 10 ? 'VALIDÉ' : 'AJOURNÉ';
$statutColor = $moyenneGlobale >= 10 ? '#10b981' : '#ef4444';

require_once 'vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$annee_academique = $etudiant['annee_academique_entree'] ?? date('Y') . '-' . (date('Y') + 1);

$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Relevé de notes - ' . htmlspecialchars($etudiant['nom'] . ' ' . $etudiant['prenom']) . '</title>
    <style>
        body { font-family: "DejaVu Sans", Arial, sans-serif; margin: 20px; color: #1e293b; font-size: 12px; }
        .header { text-align: center; border-bottom: 3px solid #1e3a8a; padding-bottom: 15px; margin-bottom: 20px; }
        .header h1 { font-size: 22px; font-weight: 800; color: #1e3a8a; margin: 0; letter-spacing: 1px; }
        .header h2 { font-size: 16px; font-weight: 600; color: #2563eb; margin: 5px 0 0 0; }
        .header .subtitle { font-size: 12px; color: #64748b; margin-top: 2px; }
        .info-etudiant { display: table; width: 100%; background: #f8fafc; padding: 12px 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #e2e8f0; }
        .info-etudiant .row { display: table-row; }
        .info-etudiant .cell { display: table-cell; padding: 4px 10px; font-size: 12px; }
        .info-etudiant .label { font-weight: 700; color: #475569; width: 120px; }
        .info-etudiant .value { font-weight: 500; color: #1e293b; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        table th { background: #1e3a8a; color: white; padding: 8px 10px; text-align: left; font-size: 11px; text-transform: uppercase; }
        table td { padding: 6px 10px; border-bottom: 1px solid #e2e8f0; font-size: 12px; }
        table tr:nth-child(even) td { background: #f8fafc; }
        .table-note { font-weight: 700; text-align: center; }
        .table-note.success { color: #10b981; }
        .table-note.fail { color: #ef4444; }
        .statut-final { display: table; width: 100%; padding: 12px 15px; border-radius: 8px; margin-top: 20px; border: 2px solid ' . $statutColor . '; background: ' . ($statutColor == '#10b981' ? '#f0fdf4' : '#fef2f2') . '; }
        .statut-final .label { font-weight: 600; font-size: 14px; }
        .statut-final .statut { font-size: 20px; font-weight: 800; color: ' . $statutColor . '; float: right; }
        .footer { text-align: center; font-size: 10px; color: #94a3b8; margin-top: 25px; padding-top: 10px; border-top: 1px solid #e2e8f0; }
        .badge { display: inline-block; padding: 2px 10px; border-radius: 12px; font-size: 10px; font-weight: 700; }
        .badge.success { background: #d1fae5; color: #065f46; }
        .badge.fail { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>

    <div class="header">
        <h1>Université Horizon Tanganyika (UHT)</h1>
        <h2>Relevé de notes officiel</h2>
        <div class="subtitle">Document validé par la Doyenne/Doyen du département</div>
    </div>

    <div class="info-etudiant">
        <div class="row">
            <div class="cell label">Nom complet</div>
            <div class="cell value"><strong>' . htmlspecialchars($etudiant['nom'] . ' ' . $etudiant['prenom']) . '</strong></div>
            <div class="cell label">Matricule</div>
            <div class="cell value">' . htmlspecialchars($etudiant['matricule']) . '</div>
        </div>
        <div class="row">
            <div class="cell label">Faculté</div>
            <div class="cell value">' . htmlspecialchars($etudiant['faculte']) . '</div>
            <div class="cell label">Département</div>
            <div class="cell value">' . htmlspecialchars($etudiant['nom_dept']) . '</div>
        </div>
        <div class="row">
            <div class="cell label">Classe</div>
            <div class="cell value">' . htmlspecialchars($etudiant['niveau']) . '</div>
            <div class="cell label">Année académique</div>
            <div class="cell value">' . htmlspecialchars($annee_academique) . '</div>
        </div>
        <div class="row">
            <div class="cell label">Email</div>
            <div class="cell value">' . htmlspecialchars($etudiant['email']) . '</div>
            <div class="cell label">Téléphone</div>
            <div class="cell value">' . htmlspecialchars($etudiant['telephone'] ?? 'Non renseigné') . '</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width:5%;">N°</th>
                <th style="width:28%;">Cours</th>
                <th style="width:12%;">Crédits</th>
                <th style="width:12%;">Semestre</th>
                <th style="width:18%;">Professeur</th>
                <th style="width:10%;">Note /20</th>
                <th style="width:15%;">Résultat</th>
            </tr>
        </thead>
        <tbody>';

if (count($notes) > 0) {
    $i = 1;
    foreach ($notes as $n) {
        $noteClass = $n['note'] >= 10 ? 'success' : 'fail';
        $resultatText = $n['note'] >= 10 ? '✅ Validé' : '❌ Échec';
        $html .= '<tr>
            <td style="text-align:center;">' . $i++ . '</td>
            <td><strong>' . htmlspecialchars($n['nom_cours']) . '</strong></td>
            <td style="text-align:center;">' . htmlspecialchars($n['credit'] ?? '-') . '</td>
            <td style="text-align:center;">' . htmlspecialchars($n['semestre']) . '</td>
            <td>' . htmlspecialchars($n['enseignant'] ?? 'Non assigné') . '</td>
            <td class="table-note ' . $noteClass . '">' . number_format($n['note'], 2) . '</td>
            <td style="text-align:center;"><span class="badge ' . $noteClass . '">' . $resultatText . '</span></td>
        </tr>';
    }
} else {
    $html .= '<tr><td colspan="7" style="text-align:center; color:#94a3b8; padding:20px;">Aucune note officielle publiée pour cet étudiant.</td></tr>';
}

$html .= '
        </tbody>
    </table>

    <div style="display:table; width:100%; margin-top:15px;">
        <div style="display:table-cell; width:50%; padding-right:15px;">
            <div style="background:#f8fafc; padding:12px 15px; border-radius:8px; border:1px solid #e2e8f0;">
                <strong>📊 Résumé académique</strong>
                <div style="display:table; width:100%; margin-top:8px;">
                    <div style="display:table-row;">
                        <div style="display:table-cell; padding:3px 0;"><span style="font-weight:600;">Notes totales :</span></div>
                        <div style="display:table-cell; padding:3px 0; text-align:right;">' . $totalNotes . '</div>
                    </div>
                    <div style="display:table-row;">
                        <div style="display:table-cell; padding:3px 0;"><span style="font-weight:600;">Crédits obtenus :</span></div>
                        <div style="display:table-cell; padding:3px 0; text-align:right;">' . $creditsTotal . '</div>
                    </div>
                    <div style="display:table-row;">
                        <div style="display:table-cell; padding:3px 0;"><span style="font-weight:600;">Moyenne générale :</span></div>
                        <div style="display:table-cell; padding:3px 0; text-align:right; font-weight:700; color:' . ($moyenneGlobale >= 10 ? '#10b981' : '#ef4444') . ';">' . number_format($moyenneGlobale, 2) . '/20</div>
                    </div>
                    <div style="display:table-row;">
                        <div style="display:table-cell; padding:3px 0;"><span style="font-weight:600;">Taux de réussite :</span></div>
                        <div style="display:table-cell; padding:3px 0; text-align:right;">' . $tauxReussite . '%</div>
                    </div>
                </div>
            </div>
        </div>
        <div style="display:table-cell; width:50%; padding-left:15px;">
            <div style="background:#f8fafc; padding:12px 15px; border-radius:8px; border:1px solid #e2e8f0;">
                <strong>📅 Validation</strong>
                <div style="display:table; width:100%; margin-top:8px;">
                    <div style="display:table-row;">
                        <div style="display:table-cell; padding:3px 0;"><span style="font-weight:600;">Validé par :</span></div>
                        <div style="display:table-cell; padding:3px 0; text-align:right;">Doyen/Doyenne</div>
                    </div>
                    <div style="display:table-row;">
                        <div style="display:table-cell; padding:3px 0;"><span style="font-weight:600;">Date de validation :</span></div>
                        <div style="display:table-cell; padding:3px 0; text-align:right;">' . date('d/m/Y') . '</div>
                    </div>
                    <div style="display:table-row;">
                        <div style="display:table-cell; padding:3px 0;"><span style="font-weight:600;">Décision :</span></div>
                        <div style="display:table-cell; padding:3px 0; text-align:right; font-weight:700; color:' . $statutColor . ';">' . $statut . '</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="statut-final">
        <span class="label">🎯 Décision du Jury :</span>
        <span class="statut">' . $statut . '</span>
        <div style="clear:both;"></div>
    </div>

    <div class="footer">
        <p>Ce relevé est officiel et certifié par l\'Université du Lac Tanganyika.</p>
        <div>Fait à Bujumbura, le ' . date('d/m/Y') . '</div>
        <div style="margin-top:10px;">Le Doyen/Doyenne du département</div>
        <p style="margin-top:8px; font-size:9px;">Document généré automatiquement par le système UniVerse</p>
    </div>
</body>
</html>';

$options = new Options();
$options->set('defaultFont', 'DejaVu Sans');
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = 'Releve_Notes_' . $etudiant['nom'] . '_' . $etudiant['prenom'] . '_' . date('Y-m-d') . '.pdf';
$dompdf->stream($filename, array('Attachment' => 1));
exit;
?>