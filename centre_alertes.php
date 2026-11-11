<?php
require_once __DIR__ . '/db.php';
require_once 'auth.php';
requireRole(['Administrateur', 'Recteur', 'Doyen']);

try {
    $bdd = getDBConnection();
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Erreur : " . $e->getMessage());
}

// Filtrage selon le rôle
$id_dept_doyen = 0;
if ($USER_ROLE === 'Doyen') {
    $stmt = $bdd->prepare("SELECT id_dept FROM doyens WHERE id_user = ?");
    $stmt->execute([$USER_ID]);
    $id_dept_doyen = $stmt->fetchColumn();
    if (!$id_dept_doyen) die("Aucun département assigné.");
}

// ============================================================
// DÉTECTION DES ALERTES
// ============================================================
$alertes = [];

// 1. Étudiants avec minerval insuffisant
$sql = "
    SELECT u.nom, u.prenom, e.matricule, c.niveau, d.nom_dept,
           COALESCE(SUM(p.montant), 0) AS total_paye
    FROM etudiants e
    JOIN users u ON e.id_user = u.id_user
    JOIN classes c ON e.id_classe = c.id_classe
    JOIN departements d ON c.id_dept = d.id_dept
    LEFT JOIN paiements p ON e.id_etudiant = p.id_etudiant AND p.statut = 'valide'
";
$params = [];
if ($id_dept_doyen > 0) { $sql .= " WHERE d.id_dept = ?"; $params[] = $id_dept_doyen; }
$sql .= " GROUP BY e.id_etudiant HAVING total_paye < 500000 ORDER BY total_paye ASC LIMIT 30";
$stmt = $bdd->prepare($sql);
$stmt->execute($params);
$minerval_insuffisant = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (count($minerval_insuffisant) > 0) {
    $alertes[] = [
        'niveau' => 'danger',
        'icone' => '💰',
        'titre' => 'Étudiants en retard de paiement',
        'description' => count($minerval_insuffisant) . ' étudiant(s) avec minerval insuffisant (< 500 000 BIF)',
        'data' => $minerval_insuffisant,
        'lien' => 'affichepaiement.php'
    ];
}

// 2. Notes en attente > 7 jours
$sql = "
    SELECT n.id_note, u.nom, u.prenom, co.nom_cours, n.date_soumission,
           DATEDIFF(NOW(), n.date_soumission) AS jours_attente
    FROM notes n
    JOIN etudiants e ON n.id_etudiant = e.id_etudiant
    JOIN users u ON e.id_user = u.id_user
    JOIN cours co ON n.id_cours = co.id_cours
    JOIN classes cl ON co.id_classe = cl.id_classe
    WHERE n.statut = 'soumis' AND DATEDIFF(NOW(), n.date_soumission) > 7
";
$params = [];
if ($id_dept_doyen > 0) { $sql .= " AND cl.id_dept = ?"; $params[] = $id_dept_doyen; }
$sql .= " ORDER BY jours_attente DESC LIMIT 30";
$stmt = $bdd->prepare($sql);
$stmt->execute($params);
$notes_en_retard = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (count($notes_en_retard) > 0) {
    $alertes[] = [
        'niveau' => 'warning',
        'icone' => '📝',
        'titre' => 'Notes en attente de validation',
        'description' => count($notes_en_retard) . ' note(s) non validée(s) depuis plus de 7 jours',
        'data' => $notes_en_retard,
        'lien' => 'affichenote.php'
    ];
}

// 3. Paiements en attente > 3 jours
$sql = "
    SELECT p.id_paiement, p.montant, p.bordereau, u.nom, u.prenom, p.date_paiement,
           DATEDIFF(NOW(), p.date_paiement) AS jours_attente
    FROM paiements p
    JOIN etudiants e ON p.id_etudiant = e.id_etudiant
    JOIN users u ON e.id_user = u.id_user
    JOIN classes c ON e.id_classe = c.id_classe
    WHERE p.statut = 'en_attente' AND DATEDIFF(NOW(), p.date_paiement) > 3
";
$params = [];
if ($id_dept_doyen > 0) { $sql .= " AND c.id_dept = ?"; $params[] = $id_dept_doyen; }
$sql .= " ORDER BY jours_attente DESC LIMIT 30";
$stmt = $bdd->prepare($sql);
$stmt->execute($params);
$paiements_en_retard = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (count($paiements_en_retard) > 0) {
    $alertes[] = [
        'niveau' => 'warning',
        'icone' => '💳',
        'titre' => 'Paiements non validés',
        'description' => count($paiements_en_retard) . ' paiement(s) en attente depuis plus de 3 jours',
        'data' => $paiements_en_retard,
        'lien' => 'affichepaiement.php'
    ];
}

// 4. Cours sans professeur
$sql = "
    SELECT c.nom_cours, cl.niveau, d.nom_dept
    FROM cours c
    JOIN classes cl ON c.id_classe = cl.id_classe
    JOIN departements d ON cl.id_dept = d.id_dept
    WHERE c.id_prof IS NULL
";
$params = [];
if ($id_dept_doyen > 0) { $sql .= " AND d.id_dept = ?"; $params[] = $id_dept_doyen; }
$sql .= " LIMIT 30";
$stmt = $bdd->prepare($sql);
$stmt->execute($params);
$cours_sans_prof = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (count($cours_sans_prof) > 0) {
    $alertes[] = [
        'niveau' => 'info',
        'icone' => '📚',
        'titre' => 'Cours sans professeur',
        'description' => count($cours_sans_prof) . ' cours n\'ont pas de professeur assigné',
        'data' => $cours_sans_prof,
        'lien' => 'affichecours.php'
    ];
}

// 5. Examens non corrigés > 15 jours
$sql = "
    SELECT e.id_examen, e.titre, e.date_prevue, co.nom_cours, cl.niveau,
           DATEDIFF(NOW(), e.date_prevue) AS jours_attente
    FROM examens e
    JOIN cours co ON e.id_cours = co.id_cours
    JOIN classes cl ON e.id_classe = cl.id_classe
    WHERE e.statut = 'valide' AND DATEDIFF(NOW(), e.date_prevue) > 15
";
$params = [];
if ($id_dept_doyen > 0) { $sql .= " AND cl.id_dept = ?"; $params[] = $id_dept_doyen; }
$sql .= " ORDER BY jours_attente DESC LIMIT 30";
$stmt = $bdd->prepare($sql);
$stmt->execute($params);
$examens_non_corriges = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (count($examens_non_corriges) > 0) {
    $alertes[] = [
        'niveau' => 'danger',
        'icone' => '📄',
        'titre' => 'Examens non corrigés',
        'description' => count($examens_non_corriges) . ' examen(s) passé(s) depuis plus de 15 jours sans note saisie',
        'data' => $examens_non_corriges,
        'lien' => 'affichenote.php'
    ];
}

// 6. Comptes suspendus
$sql = "SELECT nom, prenom, email, role, date_blocage FROM users WHERE statut = 'Suspendu' ORDER BY date_blocage DESC LIMIT 20";
$stmt = $bdd->query($sql);
$comptes_suspendus = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (count($comptes_suspendus) > 0) {
    $alertes[] = [
        'niveau' => 'danger',
        'icone' => '🔒',
        'titre' => 'Comptes suspendus',
        'description' => count($comptes_suspendus) . ' compte(s) suspendu(s) (probablement après 5 tentatives)',
        'data' => $comptes_suspendus,
        'lien' => 'afficheuser.php'
    ];
}

$total_alertes = array_sum(array_map(fn($a) => count($a['data']), $alertes));

$dashboards = [
    'Administrateur' => 'administrateur.php', 'Recteur' => 'dashboard_recteur.php',
    'Doyen' => 'dashboard_doyen.php'
];
$dashboard_url = $dashboards[$USER_ROLE] ?? 'Accueil.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Centre d'alertes - UniVerse</title>
    <link rel="stylesheet" href="assets/theme.css">
    <script src="assets/theme.js"></script>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI',Arial,sans-serif; }
        body { background:#eef3f8; padding:30px 20px; }
        .container { max-width:1200px; margin:0 auto; }
        .page-header { background:linear-gradient(135deg,#7f1d1d,#dc2626); color:white; text-align:center; padding:30px; border-radius:12px; box-shadow:0 4px 10px rgba(0,0,0,0.2); margin-bottom:20px; position:relative; }
        .page-header h1 { font-size:26px; }
        .page-header p { font-size:13px; opacity:0.9; margin-top:5px; }
        .theme-toggle-btn { position:absolute; top:15px; right:15px; background:white; border:1px solid #e2e8f0; width:42px; height:42px; border-radius:50%; font-size:18px; cursor:pointer; }
        .stats-bar { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:15px; margin-bottom:25px; }
        .stat-card { background:white; padding:20px; border-radius:12px; text-align:center; box-shadow:0 4px 12px rgba(0,0,0,0.06); border-top:4px solid #dc2626; }
        .stat-card .value { font-size:36px; font-weight:800; color:#dc2626; }
        .stat-card .label { font-size:12px; color:#64748b; text-transform:uppercase; font-weight:600; }
        .alert-section { background:white; border-radius:14px; margin-bottom:15px; box-shadow:0 4px 12px rgba(0,0,0,0.06); overflow:hidden; }
        .alert-header { padding:18px 22px; display:flex; justify-content:space-between; align-items:center; cursor:pointer; transition:0.2s; }
        .alert-header:hover { background:#f8fafc; }
        .alert-header.danger { border-left:5px solid #dc2626; }
        .alert-header.warning { border-left:5px solid #f59e0b; }
        .alert-header.info { border-left:5px solid #3b82f6; }
        .alert-header h2 { font-size:17px; color:#1e293b; display:flex; align-items:center; gap:10px; }
        .alert-header .count-badge { background:#dc2626; color:white; padding:4px 12px; border-radius:20px; font-size:13px; font-weight:700; }
        .alert-header .chevron { font-size:14px; color:#64748b; transition:0.3s; }
        .alert-header.open .chevron { transform:rotate(180deg); }
        .alert-content { max-height:0; overflow:hidden; transition:max-height 0.3s ease; background:#f8fafc; }
        .alert-content.open { max-height:2000px; }
        .alert-item { padding:12px 22px; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; }
        .alert-item:last-child { border-bottom:none; }
        .alert-item .main { font-weight:600; color:#1e293b; font-size:14px; }
        .alert-item .sub { font-size:12px; color:#64748b; margin-top:3px; }
        .alert-item .action-btn { background:#003366; color:white; padding:6px 14px; border-radius:6px; text-decoration:none; font-size:12px; font-weight:600; }
        .alert-item .action-btn:hover { background:#0055aa; }
        .alert-footer { padding:12px 22px; background:#f1f5f9; text-align:right; }
        .alert-footer a { color:#003366; font-weight:600; text-decoration:none; font-size:13px; }
        .alert-footer a:hover { text-decoration:underline; }
        .empty-state { text-align:center; padding:80px 20px; color:#10b981; background:white; border-radius:14px; box-shadow:0 4px 12px rgba(0,0,0,0.06); }
        .empty-state h2 { font-size:24px; margin-bottom:10px; }
        .empty-state p { color:#64748b; font-size:14px; }
        .icon-big { font-size:72px; display:block; margin-bottom:20px; }
        .badge-danger { background:#fee2e2; color:#991b1b; padding:3px 10px; border-radius:10px; font-size:11px; font-weight:600; }
        .badge-warning { background:#fef3c7; color:#92400e; padding:3px 10px; border-radius:10px; font-size:11px; font-weight:600; }
    </style>
</head>
<body>
<div class="container">
    <div class="page-header">
        <div style="position:absolute; top:15px; left:15px;">
            <a href="<?= $dashboard_url ?>" style="background:rgba(255,255,255,0.2); color:white; border:1px solid rgba(255,255,255,0.3); text-decoration:none; font-size:13px; padding:8px 15px; border-radius:8px; font-weight:600;">← Retour</a>
        </div>
        <button class="theme-toggle-btn" onclick="toggleTheme()">🌙</button>
        <h1>🚨 Centre d'Alertes</h1>
        <p>Détection automatique des anomalies du système</p>
    </div>

    <div class="stats-bar">
        <div class="stat-card">
            <div class="value"><?= count($alertes) ?></div>
            <div class="label">Types d'alertes</div>
        </div>
        <div class="stat-card">
            <div class="value"><?= $total_alertes ?></div>
            <div class="label">Alertes totales</div>
        </div>
    </div>

    <?php if (empty($alertes)): ?>
        <div class="empty-state">
            <span class="icon-big">✅</span>
            <h2>Aucune alerte</h2>
            <p>Le système est en parfait état de fonctionnement. Toutes les données sont à jour.</p>
        </div>
    <?php else: ?>
        <?php foreach ($alertes as $i => $alerte): ?>
            <div class="alert-section">
                <div class="alert-header <?= $alerte['niveau'] ?>" onclick="toggleAlert(<?= $i ?>)">
                    <h2>
                        <span><?= $alerte['icone'] ?></span>
                        <?= htmlspecialchars($alerte['titre']) ?>
                    </h2>
                    <div style="display:flex; align-items:center; gap:15px;">
                        <span class="count-badge" style="background:<?= $alerte['niveau'] === 'danger' ? '#dc2626' : ($alerte['niveau'] === 'warning' ? '#f59e0b' : '#3b82f6') ?>;">
                            <?= count($alerte['data']) ?>
                        </span>
                        <span class="chevron" id="chevron-<?= $i ?>">▼</span>
                    </div>
                </div>
                <div class="alert-content" id="content-<?= $i ?>">
                    <div style="padding:10px 22px 0; font-size:13px; color:#64748b;"><?= htmlspecialchars($alerte['description']) ?></div>
                    <?php foreach (array_slice($alerte['data'], 0, 10) as $item): ?>
                        <div class="alert-item">
                            <div>
                                <?php if (isset($item['nom']) && isset($item['prenom'])): ?>
                                    <div class="main"><?= htmlspecialchars($item['prenom'] . ' ' . $item['nom']) ?></div>
                                <?php elseif (isset($item['nom_cours'])): ?>
                                    <div class="main"><?= htmlspecialchars($item['nom_cours']) ?></div>
                                <?php elseif (isset($item['titre'])): ?>
                                    <div class="main"><?= htmlspecialchars($item['titre']) ?></div>
                                <?php endif; ?>
                                <div class="sub">
                                    <?php if (isset($item['matricule'])): ?>Matricule : <?= htmlspecialchars($item['matricule']) ?><?php endif; ?>
                                    <?php if (isset($item['niveau'])): ?> — <?= htmlspecialchars($item['niveau']) ?><?php endif; ?>
                                    <?php if (isset($item['nom_dept'])): ?> (<?= htmlspecialchars($item['nom_dept']) ?>)<?php endif; ?>
                                    <?php if (isset($item['nom_cours']) && isset($item['date_soumission'])): ?> — <?= htmlspecialchars($item['nom_cours']) ?> — <?= $item['jours_attente'] ?> jours<?php endif; ?>
                                    <?php if (isset($item['bordereau'])): ?> — Bordereau <?= htmlspecialchars($item['bordereau']) ?> — <?= $item['jours_attente'] ?> jours<?php endif; ?>
                                    <?php if (isset($item['total_paye'])): ?> — Payé : <?= number_format($item['total_paye'], 0, ',', ' ') ?> BIF<?php endif; ?>
                                    <?php if (isset($item['date_blocage'])): ?> — Bloqué le <?= date('d/m/Y', strtotime($item['date_blocage'])) ?><?php endif; ?>
                                    <?php if (isset($item['jours_attente']) && isset($item['titre'])): ?> — Passé depuis <?= $item['jours_attente'] ?> jours<?php endif; ?>
                                </div>
                            </div>
                            <a href="<?= $alerte['lien'] ?>" class="action-btn">Voir →</a>
                        </div>
                    <?php endforeach; ?>
                    <div class="alert-footer">
                        <a href="<?= $alerte['lien'] ?>">Voir tous les <?= count($alerte['data']) ?> éléments →</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
function toggleAlert(i) {
    const content = document.getElementById('content-' + i);
    const header = content.previousElementSibling;
    const chevron = document.getElementById('chevron-' + i);
    content.classList.toggle('open');
    header.classList.toggle('open');
}
// Ouvrir la première alerte par défaut
document.addEventListener('DOMContentLoaded', function() {
    const firstContent = document.querySelector('.alert-content');
    if (firstContent) {
        firstContent.classList.add('open');
        firstContent.previousElementSibling.classList.add('open');
    }
});
</script>
</body>
</html>