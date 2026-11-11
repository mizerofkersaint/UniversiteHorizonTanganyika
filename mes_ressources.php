<?php
require_once __DIR__ . '/db.php';
require_once 'auth.php';
requireRole('Etudiant');

try {
    $bdd = getDBConnection();
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Erreur : " . $e->getMessage());
}

// Récupérer l'étudiant
$stmt = $bdd->prepare("
    SELECT e.id_etudiant, e.id_classe, e.matricule, u.nom, u.prenom, c.niveau, d.nom_dept, d.faculte
    FROM etudiants e
    JOIN users u ON e.id_user = u.id_user
    JOIN classes c ON e.id_classe = c.id_classe
    JOIN departements d ON c.id_dept = d.id_dept
    WHERE e.id_user = ?
");
$stmt->execute([$USER_ID]);
$etu = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$etu) die("Profil étudiant introuvable.");

$id_etudiant = $etu['id_etudiant'];
$id_classe = $etu['id_classe'];

// ============================================================
// SUPPORTS DE COURS (sa classe)
// ============================================================
$stmt = $bdd->prepare("
    SELECT s.*, c.nom_cours, CONCAT(u.nom, ' ', u.prenom) AS prof
    FROM supports_cours s
    JOIN cours c ON s.id_cours = c.id_cours
    LEFT JOIN professeurs p ON s.id_prof = p.id_prof
    LEFT JOIN users u ON p.id_user = u.id_user
    WHERE s.id_classe = ?
    ORDER BY s.date_upload DESC LIMIT 10
");
$stmt->execute([$id_classe]);
$supports = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ============================================================
// EXAMENS PROGRAMMÉS
// ============================================================
$stmt = $bdd->prepare("
    SELECT e.*, c.nom_cours
    FROM examens e
    JOIN cours c ON e.id_cours = c.id_cours
    WHERE e.id_classe = ? AND e.statut = 'valide' AND e.date_prevue >= CURDATE()
    ORDER BY e.date_prevue ASC, e.heure_debut ASC LIMIT 10
");
$stmt->execute([$id_classe]);
$examens = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ============================================================
// NOTES RÉCENTES
// ============================================================
$stmt = $bdd->prepare("
    SELECT n.note, n.session, n.date_saisie, c.nom_cours, c.credit
    FROM notes n
    JOIN cours c ON n.id_cours = c.id_cours
    WHERE n.id_etudiant = ? AND n.statut = 'valide'
    ORDER BY n.date_saisie DESC LIMIT 10
");
$stmt->execute([$id_etudiant]);
$notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ============================================================
// PAIEMENTS RÉCENTS
// ============================================================
$stmt = $bdd->prepare("
    SELECT montant, tranche, date_paiement, statut, bordereau
    FROM paiements
    WHERE id_etudiant = ?
    ORDER BY date_paiement DESC LIMIT 10
");
$stmt->execute([$id_etudiant]);
$paiements = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_paye = array_sum(array_map(fn($p) => $p['statut'] == 'valide' ? $p['montant'] : 0, $paiements));

// ============================================================
// ANNONCES (classe + professeurs)
// ============================================================
$stmt = $bdd->prepare("
    SELECT a.*, u.nom, u.prenom
    FROM annonces_professeurs a
    LEFT JOIN professeurs p ON a.id_prof = p.id_prof
    LEFT JOIN users u ON p.id_user = u.id_user
    WHERE a.id_classe = ? OR a.id_classe IS NULL
    ORDER BY a.date_publication DESC LIMIT 5
");
$stmt->execute([$id_classe]);
$annonces = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Stats
$stmt = $bdd->prepare("SELECT AVG(note) FROM notes WHERE id_etudiant = ? AND statut = 'valide'");
$stmt->execute([$id_etudiant]);
$moyenne = round($stmt->fetchColumn() ?: 0, 2);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mes Ressources - UniVerse</title>
    <link rel="stylesheet" href="assets/theme.css">
    <script src="assets/theme.js"></script>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI',Arial,sans-serif; }
        body { background:#eef3f8; padding:30px 20px; }
        .container { max-width:1300px; margin:0 auto; }
        .page-header { background:linear-gradient(135deg,#003366,#0055aa); color:white; text-align:center; padding:30px; border-radius:12px; box-shadow:0 4px 10px rgba(0,0,0,0.2); margin-bottom:20px; position:relative; }
        .page-header h1 { font-size:26px; }
        .page-header p { font-size:14px; opacity:0.9; margin-top:5px; }
        .theme-toggle-btn { position:absolute; top:15px; right:15px; background:white; border:1px solid #e2e8f0; width:42px; height:42px; border-radius:50%; font-size:18px; cursor:pointer; }
        .stats-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:15px; margin-bottom:25px; }
        .stat-card { background:white; padding:20px; border-radius:12px; text-align:center; box-shadow:0 4px 12px rgba(0,0,0,0.06); border-top:4px solid #003366; }
        .stat-card .value { font-size:28px; font-weight:800; color:#003366; }
        .stat-card .label { font-size:11px; color:#64748b; text-transform:uppercase; font-weight:700; margin-top:4px; }
        .section-card { background:white; border-radius:14px; box-shadow:0 4px 12px rgba(0,0,0,0.06); margin-bottom:20px; overflow:hidden; }
        .section-header { padding:18px 22px; background:#f8fafc; border-bottom:2px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; }
        .section-header h2 { font-size:17px; color:#003366; font-weight:700; }
        .section-header .count { background:#003366; color:white; padding:3px 12px; border-radius:20px; font-size:12px; font-weight:700; }
        .section-body { padding:0; }
        .item-row { padding:14px 22px; border-bottom:1px solid #f1f5f9; display:flex; justify-content:space-between; align-items:center; }
        .item-row:last-child { border-bottom:none; }
        .item-row:hover { background:#f8fbff; }
        .item-row .main { font-weight:600; color:#1e293b; font-size:14px; }
        .item-row .sub { font-size:12px; color:#64748b; margin-top:3px; }
        .item-row .badge { padding:3px 10px; border-radius:12px; font-size:11px; font-weight:600; }
        .badge-success { background:#d1fae5; color:#065f46; }
        .badge-warning { background:#fef3c7; color:#92400e; }
        .badge-danger { background:#fee2e2; color:#991b1b; }
        .badge-info { background:#eff6ff; color:#1e40af; }
        .btn-action { padding:6px 14px; border-radius:8px; text-decoration:none; font-weight:600; font-size:12px; color:white; background:#16a34a; }
        .btn-action:hover { background:#15803d; }
        .empty { padding:30px; text-align:center; color:#94a3b8; font-size:13px; }
        .two-col { display:grid; grid-template-columns:1fr 1fr; gap:20px; }
        @media(max-width:900px){ .two-col{ grid-template-columns:1fr; } }
    </style>
</head>
<body>
<div class="container">
    <div class="page-header">
        <div style="position:absolute; top:15px; left:15px;">
            <a href="dashboard_etudiant.php" style="background:rgba(255,255,255,0.2); color:white; border:1px solid rgba(255,255,255,0.3); text-decoration:none; font-size:13px; padding:8px 15px; border-radius:8px; font-weight:600;">← Retour</a>
        </div>
        <button class="theme-toggle-btn" onclick="toggleTheme()">🌙</button>
        <h1>🎓 Mes Ressources</h1>
        <p><?= htmlspecialchars($etu['prenom'] . ' ' . $etu['nom']) ?> — <?= htmlspecialchars($etu['niveau']) ?> — <?= htmlspecialchars($etu['nom_dept']) ?></p>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="value"><?= count($supports) ?></div>
            <div class="label">📚 Supports de cours</div>
        </div>
        <div class="stat-card" style="border-top-color:#f59e0b;">
            <div class="value" style="color:#f59e0b;"><?= count($examens) ?></div>
            <div class="label">📝 Examens à venir</div>
        </div>
        <div class="stat-card" style="border-top-color:#16a34a;">
            <div class="value" style="color:#16a34a;"><?= number_format($moyenne, 2) ?>/20</div>
            <div class="label">📊 Moyenne générale</div>
        </div>
        <div class="stat-card" style="border-top-color:#8b5cf6;">
            <div class="value" style="color:#8b5cf6;"><?= number_format($total_paye, 0, ',', ' ') ?></div>
            <div class="label">💰 Total payé (BIF)</div>
        </div>
    </div>

    <!-- EXAMENS À VENIR (priorité) -->
    <div class="section-card">
        <div class="section-header">
            <h2>📝 Examens à venir</h2>
            <span class="count"><?= count($examens) ?></span>
        </div>
        <div class="section-body">
            <?php if (count($examens) > 0): ?>
                <?php foreach ($examens as $e): ?>
                    <div class="item-row">
                        <div>
                            <div class="main"><?= htmlspecialchars($e['titre']) ?></div>
                            <div class="sub">
                                📚 <?= htmlspecialchars($e['nom_cours']) ?> — 
                                📅 <strong><?= date('d/m/Y', strtotime($e['date_prevue'])) ?></strong>
                                🕐 <strong><?= substr($e['heure_debut'], 0, 5) ?> - <?= substr($e['heure_fin'], 0, 5) ?></strong>
                                — <?= $e['type_examen'] ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty">📭 Aucun examen programmé pour le moment.</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="two-col">
        <!-- SUPPORTS DE COURS -->
        <div class="section-card">
            <div class="section-header">
                <h2>📚 Supports de cours</h2>
                <span class="count"><?= count($supports) ?></span>
            </div>
            <div class="section-body">
                <?php if (count($supports) > 0): ?>
                    <?php foreach ($supports as $s): ?>
                        <div class="item-row">
                            <div>
                                <div class="main"><?= htmlspecialchars($s['titre']) ?></div>
                                <div class="sub"><?= htmlspecialchars($s['nom_cours']) ?> — <?= htmlspecialchars($s['type_fichier']) ?> — v<?= $s['version'] ?? 1 ?></div>
                            </div>
                            <a href="download_support.php?id=<?= $s['id_support'] ?>" class="btn-action">📥</a>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty">📭 Aucun support disponible.</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- NOTES RÉCENTES -->
        <div class="section-card">
            <div class="section-header">
                <h2>📊 Notes récentes</h2>
                <span class="count"><?= count($notes) ?></span>
            </div>
            <div class="section-body">
                <?php if (count($notes) > 0): ?>
                    <?php foreach ($notes as $n): ?>
                        <div class="item-row">
                            <div>
                                <div class="main"><?= htmlspecialchars($n['nom_cours']) ?></div>
                                <div class="sub"><?= date('d/m/Y', strtotime($n['date_saisie'])) ?> — <?= htmlspecialchars($n['session']) ?></div>
                            </div>
                            <span class="badge <?= $n['note'] >= 10 ? 'badge-success' : 'badge-danger' ?>"><?= number_format($n['note'], 2) ?>/20</span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty">📭 Aucune note disponible.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="two-col">
        <!-- PAIEMENTS -->
        <div class="section-card">
            <div class="section-header">
                <h2>💰 Paiements récents</h2>
                <span class="count"><?= count($paiements) ?></span>
            </div>
            <div class="section-body">
                <?php if (count($paiements) > 0): ?>
                    <?php foreach ($paiements as $p): ?>
                        <div class="item-row">
                            <div>
                                <div class="main"><?= number_format($p['montant'], 0, ',', ' ') ?> BIF</div>
                                <div class="sub"><?= htmlspecialchars($p['tranche'] ?? 'N/A') ?> — <?= date('d/m/Y', strtotime($p['date_paiement'])) ?></div>
                            </div>
                            <span class="badge badge-<?= $p['statut'] == 'valide' ? 'success' : ($p['statut'] == 'rejete' ? 'danger' : 'warning') ?>"><?= $p['statut'] ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty">📭 Aucun paiement enregistré.</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ANNONCES -->
        <div class="section-card">
            <div class="section-header">
                <h2>📢 Annonces</h2>
                <span class="count"><?= count($annonces) ?></span>
            </div>
            <div class="section-body">
                <?php if (count($annonces) > 0): ?>
                    <?php foreach ($annonces as $a): ?>
                        <div class="item-row">
                            <div>
                                <div class="main"><?= htmlspecialchars($a['titre']) ?></div>
                                <div class="sub"><?= htmlspecialchars(substr($a['message'], 0, 80)) ?><?= strlen($a['message']) > 80 ? '...' : '' ?></div>
                                <div class="sub" style="font-size:11px; margin-top:3px;">
                                    <?= date('d/m/Y', strtotime($a['date_publication'])) ?>
                                    <?php if (isset($a['prenom'])): ?> — <?= htmlspecialchars($a['prenom'] . ' ' . $a['nom']) ?><?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty">📭 Aucune annonce pour le moment.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</body>
</html>