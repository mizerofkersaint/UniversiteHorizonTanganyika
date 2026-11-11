<?php
require_once __DIR__ . '/db.php';
require_once 'auth.php';
requireRole('Etudiant');
session_start();

// 1. CONNEXION À LA BASE DE DONNÉES
try {
    $bdd = getDBConnection();
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// 2. RÉCUPÉRATION DE L'ÉTUDIANT CONNECTÉ
$id_user = isset($_SESSION['id_user']) ? intval($_SESSION['id_user']) : 0;

$reqEtudiant = $bdd->prepare("
    SELECT e.id_etudiant, e.matricule, e.titre, e.annee_academique_entree,
           u.nom, u.prenom, u.sexe, u.telephone, u.email,
           c.niveau, c.id_classe,
           d.nom_dept, d.faculte
    FROM etudiants e
    INNER JOIN users u ON e.id_user = u.id_user
    LEFT JOIN classes c ON e.id_classe = c.id_classe
    LEFT JOIN departements d ON c.id_dept = d.id_dept
    WHERE e.id_user = ?
");
$reqEtudiant->execute([$id_user]);
$etudiant = $reqEtudiant->fetch(PDO::FETCH_ASSOC);

if (!$etudiant) {
    die("Étudiant non trouvé.");
}

// 3. RÉCUPÉRATION DES NOTES DE L'ÉTUDIANT
$reqNotes = $bdd->prepare("
    SELECT n.note, n.session, n.date_saisie, co.nom_cours, co.credit,
           co.semestre
    FROM notes n
    INNER JOIN cours co ON n.id_cours = co.id_cours
    WHERE n.id_etudiant = ?
    ORDER BY co.semestre, co.nom_cours ASC
");
$reqNotes->execute([$etudiant['id_etudiant']]);
$notes = $reqNotes->fetchAll(PDO::FETCH_ASSOC);

// 4. CALCUL DES STATISTIQUES
$totalNotes = count($notes);
$moyenneGlobale = 0;
$creditsTotal = 0;
$notesValides = 0;

foreach ($notes as $n) {
    if ($n['note'] >= 10) {
        $notesValides++;
    }
    if (!empty($n['credit'])) {
        $creditsTotal += $n['credit'];
    }
}

if ($totalNotes > 0) {
    $sommeNotes = array_sum(array_column($notes, 'note'));
    $moyenneGlobale = round($sommeNotes / $totalNotes, 2);
}

$tauxReussite = $totalNotes > 0 ? round(($notesValides / $totalNotes) * 100, 1) : 0;

// 5. GROUPE LES NOTES PAR SEMESTRE
$notesParSemestre = [];
foreach ($notes as $n) {
    $sem = $n['semestre'] ?? 'Semestre 1';
    if (!isset($notesParSemestre[$sem])) {
        $notesParSemestre[$sem] = [];
    }
    $notesParSemestre[$sem][] = $n;
}

// 6. DÉTERMINER SI L'ÉTUDIANT A RÉUSSI (Moyenne >= 10)
$statut = $moyenneGlobale >= 10 ? 'ADMIS' : 'AJOURNÉ';
$statutColor = $moyenneGlobale >= 10 ? '#10b981' : '#ef4444';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulletin de Notes — <?= htmlspecialchars($etudiant['nom'] . ' ' . $etudiant['prenom']); ?></title>
    <style>
        /* ============================================================
           STYLES POUR L'IMPRESSION / PDF
           ============================================================ */
        @page {
            size: A4;
            margin: 15mm;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Arial, sans-serif;
        }

        body {
            background: #f1f5f9;
            display: flex;
            justify-content: center;
            padding: 30px;
            min-height: 100vh;
        }

        .bulletin-container {
            max-width: 900px;
            width: 100%;
            background: white;
            padding: 35px 40px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }

        /* EN-TÊTE */
        .header {
            text-align: center;
            border-bottom: 3px solid #1e3a8a;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .header h1 {
            font-size: 24px;
            color: #1e3a8a;
            letter-spacing: 2px;
        }
        .header .sous-titre {
            font-size: 14px;
            color: #64748b;
            margin-top: 4px;
        }

        /* INFOS ÉTUDIANT */
        .info-etudiant {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px 30px;
            background: #f8fafc;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .info-etudiant .label {
            font-weight: 600;
            color: #475569;
        }
        .info-etudiant .value {
            font-weight: 500;
        }

        /* STATISTIQUES */
        .stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 25px;
        }
        .stat-item {
            background: #f8fafc;
            padding: 12px;
            border-radius: 8px;
            text-align: center;
            border: 1px solid #e2e8f0;
        }
        .stat-item .number {
            font-size: 22px;
            font-weight: 700;
        }
        .stat-item .number.success { color: #10b981; }
        .stat-item .number.warning { color: #f59e0b; }
        .stat-item .number.danger { color: #ef4444; }
        .stat-item .number.blue { color: #2563eb; }
        .stat-item .label {
            font-size: 11px;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 600;
        }

        /* TABLEAU DES NOTES */
        .section-title {
            font-size: 16px;
            font-weight: 700;
            color: #1e293b;
            margin: 18px 0 10px 0;
            padding: 8px 12px;
            background: #1e3a8a;
            color: white;
            border-radius: 6px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
            margin-bottom: 15px;
        }
        th {
            background: #f1f5f9;
            padding: 10px 12px;
            text-align: left;
            font-weight: 600;
            color: #475569;
            border-bottom: 2px solid #e2e8f0;
        }
        td {
            padding: 8px 12px;
            border-bottom: 1px solid #e2e8f0;
        }
        tr:hover td {
            background: #f8fafc;
        }
        .note-badge {
            display: inline-block;
            padding: 2px 12px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 13px;
        }
        .note-badge.success { background: #d1fae5; color: #065f46; }
        .note-badge.fail { background: #fee2e2; color: #991b1b; }

        /* STATUT FINAL */
        .statut-final {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            border-radius: 8px;
            margin-top: 20px;
            border: 2px solid <?= $statutColor; ?>;
            background: <?= $statutColor == '#10b981' ? '#f0fdf4' : '#fef2f2'; ?>;
        }
        .statut-final .label {
            font-weight: 600;
            font-size: 16px;
        }
        .statut-final .statut {
            font-size: 22px;
            font-weight: 800;
            color: <?= $statutColor; ?>;
        }

        /* FOOTER */
        .footer {
            margin-top: 25px;
            text-align: center;
            font-size: 12px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            padding-top: 15px;
        }

        /* BOUTONS */
        .actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-top: 20px;
        }
        .btn {
            padding: 10px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s;
        }
        .btn-pdf {
            background: #dc2626;
            color: white;
        }
        .btn-pdf:hover {
            background: #b91c1c;
            transform: translateY(-2px);
        }
        .btn-retour {
            background: #64748b;
            color: white;
        }
        .btn-retour:hover {
            background: #475569;
            transform: translateY(-2px);
        }

        /* IMPRESSION */
        @media print {
            body { background: white; padding: 0; }
            .bulletin-container { box-shadow: none; border-radius: 0; padding: 20px; }
            .actions { display: none; }
            .stat-item { border: 1px solid #e2e8f0; }
        }

        @media (max-width: 700px) {
            .stats { grid-template-columns: 1fr 1fr; }
            .info-etudiant { grid-template-columns: 1fr; }
            .bulletin-container { padding: 20px; }
        }
    </style>
</head>
<body>

<div class="bulletin-container" id="bulletin-content">

    <!-- EN-TÊTE -->
    <div class="header">
        <h1>🎓 UNIVERSITÉ DU LAC TANGANYIKA</h1>
        <div class="sous-titre">Bulletin de Notes — Année Académique <?= htmlspecialchars($etudiant['annee_academique_entree']); ?></div>
    </div>

    <!-- INFOS ÉTUDIANT -->
    <div class="info-etudiant">
        <div><span class="label">Nom :</span> <span class="value"><?= htmlspecialchars($etudiant['nom'] . ' ' . $etudiant['prenom']); ?></span></div>
        <div><span class="label">Matricule :</span> <span class="value"><?= htmlspecialchars($etudiant['matricule']); ?></span></div>
        <div><span class="label">Faculté :</span> <span class="value"><?= htmlspecialchars($etudiant['faculte']); ?></span></div>
        <div><span class="label">Département :</span> <span class="value"><?= htmlspecialchars($etudiant['nom_dept']); ?></span></div>
        <div><span class="label">Classe :</span> <span class="value"><?= htmlspecialchars($etudiant['niveau']); ?></span></div>
        <div><span class="label">Année Académique :</span> <span class="value"><?= htmlspecialchars($etudiant['annee_academique_entree']); ?></span></div>
    </div>

    <!-- STATISTIQUES -->
    <div class="stats">
        <div class="stat-item">
            <div class="number blue"><?= $totalNotes; ?></div>
            <div class="label">📝 Notes</div>
        </div>
        <div class="stat-item">
            <div class="number <?= $moyenneGlobale >= 10 ? 'success' : 'danger'; ?>">
                <?= number_format($moyenneGlobale, 2); ?>
            </div>
            <div class="label">📊 Moyenne</div>
        </div>
        <div class="stat-item">
            <div class="number success"><?= $creditsTotal; ?></div>
            <div class="label">📚 Crédits</div>
        </div>
        <div class="stat-item">
            <div class="number <?= $tauxReussite >= 70 ? 'success' : 'warning'; ?>">
                <?= $tauxReussite; ?>%
            </div>
            <div class="label">✅ Taux Réussite</div>
        </div>
    </div>

    <!-- TABLEAU DES NOTES PAR SEMESTRE -->
    <?php if(!empty($notesParSemestre)): ?>
        <?php foreach($notesParSemestre as $semestre => $notesSem): ?>
            <div class="section-title">📖 <?= htmlspecialchars($semestre); ?></div>
            <table>
                <thead>
                    <tr>
                        <th>N°</th>
                        <th>Cours</th>
                        <th>Crédits</th>
                        <th style="text-align:center;">Note /20</th>
                        <th style="text-align:center;">Statut</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach($notesSem as $n): ?>
                    <tr>
                        <td><?= $i++; ?></td>
                        <td><?= htmlspecialchars($n['nom_cours']); ?></td>
                        <td><?= htmlspecialchars($n['credit'] ?? '-'); ?></td>
                        <td style="text-align:center; font-weight:700;">
                            <?= number_format($n['note'], 2); ?>
                        </td>
                        <td style="text-align:center;">
                            <span class="note-badge <?= $n['note'] >= 10 ? 'success' : 'fail'; ?>">
                                <?= $n['note'] >= 10 ? '✅ Validé' : '❌ Échec'; ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endforeach; ?>
    <?php else: ?>
        <div style="text-align:center; padding:30px; color:#94a3b8;">
            📭 Aucune note enregistrée pour cette année académique.
        </div>
    <?php endif; ?>

    <!-- STATUT FINAL -->
    <div class="statut-final">
        <span class="label">🎯 Décision du Jury :</span>
        <span class="statut"><?= $statut; ?></span>
    </div>

    <!-- FOOTER -->
    <div class="footer">
        Bulletin généré le <?= date('d/m/Y à H:i'); ?> — Document officiel de l'Université du Lac Tanganyika
    </div>

</div>

<!-- BOUTONS D'ACTION -->
<div class="actions">
    <button class="btn btn-pdf" onclick="telechargerPDF()">📄 Télécharger en PDF</button>
    <a href="dashboard_etudiant.php?page=notes" class="btn btn-retour">⬅️ Retour</a>
</div>

<!-- ============================================================ -->
<!-- JAVASCRIPT POUR LA GÉNÉRATION PDF                            -->
<!-- ============================================================ -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js" integrity="sha512-GsLlZN/3F2ErC5ifS5QtgpiJtWd43JWSuIgh7mbzZ8zBps+dvLusV+eNQATqgA/HdeKFVgA5v3S/cIrLF7QnIg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

<script>
function telechargerPDF() {
    const element = document.getElementById('bulletin-content');
    
    // Configuration de la génération PDF
    const opt = {
        margin: 10,
        filename: 'Bulletin_<?= htmlspecialchars($etudiant['nom'] . '_' . $etudiant['prenom']); ?>.pdf',
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: {
            scale: 2,
            useCORS: true,
            letterRendering: true,
            logging: false
        },
        jsPDF: {
            unit: 'mm',
            format: 'a4',
            orientation: 'portrait'
        },
        pagebreak: { mode: ['avoid-all', 'css', 'legacy'] }
    };
    
    // Générer le PDF
    html2pdf().set(opt).from(element).save();
}
</script>

</body>
</html>