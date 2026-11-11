<?php
require_once __DIR__ . '/db.php';
// ============================================================
// BULLETIN DE NOTES - UniVerse
// Génération PDF officiel
// ============================================================

require_once 'auth.php';
requireRole('Etudiant');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// ============================================================
// 1. CONNEXION À LA BASE DE DONNÉES
// ============================================================
try {
    $bdd = getDBConnection();
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// ============================================================
// 2. RÉCUPÉRATION DES PARAMÈTRES
// ============================================================
$id_etudiant = isset($_GET['id_etudiant']) ? intval($_GET['id_etudiant']) : 0;
$annee_academique = isset($_GET['annee']) ? trim($_GET['annee']) : date('Y') . '-' . (date('Y') + 1);

if ($id_etudiant <= 0) {
    die("ID étudiant invalide.");
}

// ============================================================
// 3. RÉCUPÉRATION DES INFORMATIONS DE L'ÉTUDIANT
// ============================================================
$reqEtudiant = $bdd->prepare("
    SELECT e.id_etudiant, e.matricule, e.annee_academique_entree,
           u.nom, u.prenom, u.email, u.telephone,
           c.niveau, c.id_classe,
           d.nom_dept, d.faculte
    FROM etudiants e
    JOIN users u ON e.id_user = u.id_user
    LEFT JOIN classes c ON e.id_classe = c.id_classe
    LEFT JOIN departements d ON c.id_dept = d.id_dept
    WHERE e.id_etudiant = ?
");
$reqEtudiant->execute([$id_etudiant]);
$etudiant = $reqEtudiant->fetch(PDO::FETCH_ASSOC);

if (!$etudiant) {
    die("Étudiant introuvable.");
}

// ============================================================
// 4. RÉCUPÉRATION DES NOTES OFFICIELLES (validées ET publiées)
// ============================================================
$reqNotes = $bdd->prepare("
    SELECT n.note, n.session, n.date_validation, n.date_publication,
           co.nom_cours, co.credit, co.semestre,
           CONCAT(u.nom, ' ', u.prenom) AS enseignant
    FROM notes n
    JOIN cours co ON n.id_cours = co.id_cours
    LEFT JOIN professeurs p ON co.id_prof = p.id_prof
    LEFT JOIN users u ON p.id_user = u.id_user
    WHERE n.id_etudiant = ? 
      AND n.statut = 'valide' 
      AND n.date_publication IS NOT NULL
      AND co.semestre IS NOT NULL
    ORDER BY co.semestre, co.nom_cours
");
$reqNotes->execute([$id_etudiant]);
$notes = $reqNotes->fetchAll(PDO::FETCH_ASSOC);

// ============================================================
// 5. RÉCUPÉRATION DU RÉSULTAT ANNUEL
// ============================================================
$reqResultat = $bdd->prepare("
    SELECT moyenne, credits_obtenus, credits_requis, decision, statut, date_validation
    FROM resultats_annuels
    WHERE id_etudiant = ? AND annee_academique = ?
    ORDER BY date_validation DESC
    LIMIT 1
");
$reqResultat->execute([$id_etudiant, $annee_academique]);
$resultat = $reqResultat->fetch(PDO::FETCH_ASSOC);

// ============================================================
// 6. CALCUL DES STATISTIQUES
// ============================================================
$totalNotes = count($notes);
$creditsObtenus = 0;
$creditsTotal = 0;
$moyenneGlobale = 0;
$notesValidees = 0;

foreach ($notes as $n) {
    if ($n['note'] >= 10) {
        $notesValidees++;
        $creditsObtenus += intval($n['credit'] ?? 0);
    }
    if (!empty($n['credit'])) {
        $creditsTotal += intval($n['credit']);
    }
}
if ($totalNotes > 0) {
    $sommeNotes = array_sum(array_column($notes, 'note'));
    $moyenneGlobale = round($sommeNotes / $totalNotes, 2);
}
$creditsRestants = $creditsTotal - $creditsObtenus;

// Utiliser les données du résultat annuel si disponibles
if ($resultat) {
    $moyenneGlobale = $resultat['moyenne'];
    $creditsObtenus = $resultat['credits_obtenus'];
    $creditsTotal = $resultat['credits_requis'];
    $decision = $resultat['decision'];
    $statut = $resultat['statut'];
    $dateValidation = $resultat['date_validation'];
} else {
    $decision = $moyenneGlobale >= 10 ? 'ADMIS' : 'AJOURNÉ';
    $statut = 'en_attente';
    $dateValidation = null;
}

// ============================================================
// 7. CHARGEMENT DE DOMPDF
// ============================================================
require_once 'vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// ============================================================
// 8. CONTENU HTML DU BULLETIN
// ============================================================
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Bulletin de notes - ' . htmlspecialchars($etudiant['nom'] . ' ' . $etudiant['prenom']) . '</title>
    <style>
        body {
            font-family: "DejaVu Sans", Arial, sans-serif;
            margin: 20px;
            padding: 0;
            color: #1e293b;
            font-size: 12px;
        }
        .header {
            text-align: center;
            border-bottom: 3px solid #1e3a8a;
            padding-bottom: 12px;
            margin-bottom: 15px;
        }
        .header h1 {
            font-size: 20px;
            font-weight: 800;
            color: #1e3a8a;
            margin: 0;
        }
        .header h2 {
            font-size: 14px;
            font-weight: 600;
            color: #2563eb;
            margin: 4px 0 0 0;
        }
        .header .subtitle {
            font-size: 11px;
            color: #64748b;
            margin-top: 2px;
        }
        .info-etudiant {
            display: table;
            width: 100%;
            background: #f8fafc;
            padding: 10px 12px;
            border-radius: 6px;
            margin-bottom: 15px;
            border: 1px solid #e2e8f0;
        }
        .info-etudiant .row {
            display: table-row;
        }
        .info-etudiant .cell {
            display: table-cell;
            padding: 3px 8px;
            font-size: 11px;
        }
        .info-etudiant .label {
            font-weight: 700;
            color: #475569;
            width: 100px;
        }
        .info-etudiant .value {
            font-weight: 500;
        }
        .info-etudiant .value strong {
            color: #1e293b;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
            font-size: 11px;
        }
        table th {
            background: #1e3a8a;
            color: white;
            padding: 6px 8px;
            text-align: left;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        table td {
            padding: 5px 8px;
            border-bottom: 1px solid #e2e8f0;
        }
        table tr:nth-child(even) td {
            background: #f8fafc;
        }
        .table-note {
            text-align: center;
            font-weight: 700;
        }
        .table-note.success {
            color: #10b981;
        }
        .table-note.fail {
            color: #ef4444;
        }
        .badge {
            display: inline-block;
            padding: 1px 8px;
            border-radius: 10px;
            font-size: 9px;
            font-weight: 700;
        }
        .badge.success {
            background: #d1fae5;
            color: #065f46;
        }
        .badge.fail {
            background: #fee2e2;
            color: #991b1b;
        }
        .stats {
            display: table;
            width: 100%;
            background: #f8fafc;
            padding: 10px 12px;
            border-radius: 6px;
            margin-top: 12px;
            border: 1px solid #e2e8f0;
            font-size: 11px;
        }
        .stats .col {
            display: table-cell;
            padding: 3px 10px;
            border-right: 1px solid #e2e8f0;
        }
        .stats .col:last-child {
            border-right: none;
        }
        .stats .number {
            font-weight: 700;
            font-size: 14px;
            color: #1e3a8a;
        }
        .stats .label {
            font-size: 10px;
            color: #64748b;
        }
        .statut-final {
            display: table;
            width: 100%;
            padding: 10px 12px;
            border-radius: 6px;
            margin-top: 12px;
            border: 2px solid ' . ($decision == 'ADMIS' ? '#10b981' : '#ef4444') . ';
            background: ' . ($decision == 'ADMIS' ? '#f0fdf4' : '#fef2f2') . ';
        }
        .statut-final .label {
            font-weight: 600;
            font-size: 13px;
        }
        .statut-final .statut {
            font-size: 18px;
            font-weight: 800;
            color: ' . ($decision == 'ADMIS' ? '#10b981' : '#ef4444') . ';
            float: right;
        }
        .footer {
            text-align: center;
            font-size: 9px;
            color: #94a3b8;
            margin-top: 15px;
            padding-top: 8px;
            border-top: 1px solid #e2e8f0;
        }
        .footer .signature {
            margin-top: 8px;
        }
        .footer .signature .line {
            display: inline-block;
            width: 150px;
            border-bottom: 1px solid #1e293b;
            margin-top: 4px;
        }
        .reference {
            font-size: 10px;
            color: #64748b;
            text-align: right;
            margin-top: 5px;
        }
    </style>
</head>
<body>

    <!-- ============================================================
    EN-TÊTE
    ============================================================ -->
    <div class="header">
        <h1>Université Horizon Tanganyika (UHT)</h1>
        <h2>BULLETIN DE NOTES OFFICIEL</h2>
        <div class="subtitle">Document certifié par la Doyenne/Doyen du département</div>
    </div>

    <!-- ============================================================
    INFORMATIONS ÉTUDIANT
    ============================================================ -->
    <div class="info-etudiant">
        <div class="row">
            <div class="cell label">Nom complet</div>
            <div class="cell value"><strong>' . htmlspecialchars($etudiant['nom'] . ' ' . $etudiant['prenom']) . '</strong></div>
            <div class="cell label">Matricule</div>
            <div class="cell value">' . htmlspecialchars($etudiant['matricule']) . '</div>
        </div>
        <div class="row">
            <div class="cell label">Faculté</div>
            <div class="cell value">' . htmlspecialchars($etudiant['faculte'] ?? 'Non assignée') . '</div>
            <div class="cell label">Département</div>
            <div class="cell value">' . htmlspecialchars($etudiant['nom_dept'] ?? 'Non assigné') . '</div>
        </div>
        <div class="row">
            <div class="cell label">Classe</div>
            <div class="cell value">' . htmlspecialchars($etudiant['niveau']) . '</div>
            <div class="cell label">Année académique</div>
            <div class="cell value">' . htmlspecialchars($annee_academique) . '</div>
        </div>
    </div>

    <!-- ============================================================
    TABLEAU DES NOTES
    ============================================================ -->
    <table>
        <thead>
            <tr>
                <th style="width:5%;">N°</th>
                <th style="width:32%;">Cours</th>
                <th style="width:10%;">Crédits</th>
                <th style="width:12%;">Semestre</th>
                <th style="width:20%;">Enseignant</th>
                <th style="width:10%;">Note /20</th>
                <th style="width:11%;">Résultat</th>
            </tr>
        </thead>
        <tbody>';

if (count($notes) > 0) {
    $i = 1;
    foreach ($notes as $n) {
        $noteClass = $n['note'] >= 10 ? 'success' : 'fail';
        $resultatText = $n['note'] >= 10 ? '✅ Validé' : '❌ Échec';
        $html .= '
            <tr>
                <td style="text-align:center;">' . $i++ . '</td>
                <td><strong>' . htmlspecialchars($n['nom_cours']) . '</strong></td>
                <td style="text-align:center;">' . htmlspecialchars($n['credit'] ?? '-') . '</td>
                <td style="text-align:center;">' . htmlspecialchars($n['semestre']) . '</td>
                <td>' . htmlspecialchars($n['enseignant'] ?? 'Non assigné') . '</td>
                <td class="table-note ' . $noteClass . '">' . number_format($n['note'], 2) . '</td>
                <td style="text-align:center;">
                    <span class="badge ' . $noteClass . '">' . $resultatText . '</span>
                </td>
            </tr>';
    }
} else {
    $html .= '
            <tr>
                <td colspan="7" style="text-align:center; color:#94a3b8; padding:20px;">
                    Aucune note officielle publiée pour cette année académique.
                </td>
            </tr>';
}

$html .= '
        </tbody>
    </table>

    <!-- ============================================================
    STATISTIQUES
    ============================================================ -->
    <div class="stats">
        <div class="col">
            <div class="number">' . $totalNotes . '</div>
            <div class="label">Notes</div>
        </div>
        <div class="col">
            <div class="number">' . $creditsObtenus . '/' . $creditsTotal . '</div>
            <div class="label">Crédits obtenus</div>
        </div>
        <div class="col">
            <div class="number">' . number_format($moyenneGlobale, 2) . '/20</div>
            <div class="label">Moyenne générale</div>
        </div>
        <div class="col">
            <div class="number">' . $notesValidees . '</div>
            <div class="label">Validés</div>
        </div>
        <div class="col">
            <div class="number">' . ($totalNotes - $notesValidees) . '</div>
            <div class="label">Non validés</div>
        </div>
    </div>

    <!-- ============================================================
    STATUT FINAL
    ============================================================ -->
    <div class="statut-final">
        <span class="label">🎯 Décision du Jury :</span>
        <span class="statut">' . $decision . '</span>
        <div style="clear:both;"></div>
        <div style="font-size:11px; color:#475569; margin-top:4px;">
            Statut : ' . ($statut == 'valide' ? '✅ Résultat officiel validé' : '⏳ En attente de validation finale') . '
        </div>
    </div>

    <!-- ============================================================
    SIGNATURE
    ============================================================ -->
    <div class="footer">
        <p>Ce bulletin est officiel et certifié par l\'Université du Lac Tanganyika.</p>
        <div class="signature">
            <div>Fait à Bujumbura, le ' . date('d/m/Y') . '</div>
            <div style="margin-top:6px;">
                <span style="display:inline-block; width:150px; border-bottom:1px solid #1e293b; padding-top:5px;"></span>
            </div>
            <div>Le Doyen/Doyenne du département</div>
        </div>
        <div class="reference">
            Réf: ULT-' . strtoupper(substr($etudiant['nom_dept'] ?? 'GEN', 0, 3)) . '-' . $annee_academique . '-' . str_pad($id_etudiant, 6, '0', STR_PAD_LEFT) . '
        </div>
        <p style="margin-top:6px; font-size:8px; color:#94a3b8;">Document généré automatiquement par le système UniVerse</p>
    </div>

</body>
</html>';

// ============================================================
// 9. GÉNÉRATION DU PDF
// ============================================================
$options = new Options();
$options->set('defaultFont', 'DejaVu Sans');
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// ============================================================
// 10. TÉLÉCHARGEMENT
// ============================================================
$filename = 'Bulletin_' . $etudiant['nom'] . '_' . $etudiant['prenom'] . '_' . $annee_academique . '.pdf';
$dompdf->stream($filename, array('Attachment' => 1));
exit;