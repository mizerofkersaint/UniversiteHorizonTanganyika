<?php
require_once __DIR__ . '/db.php';
require_once 'auth.php';
requireRole(['Administrateur', 'Agent', 'Recteur', 'Doyen']);
try {
    $bdd = getDBConnection();
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Erreur : " . $e->getMessage());
}

$q = trim($_GET['q'] ?? '');
$resultats = [
    'users' => [],
    'etudiants' => [],
    'professeurs' => [],
    'cours' => [],
    'classes' => [],
    'notes' => [],
    'paiements' => []
];
$total_resultats = 0;

if ($q !== '') {
    $like = '%' . $q . '%';
    
    // ===== USERS =====
    $stmt = $bdd->prepare("
        SELECT id_user, nom, prenom, email, role, statut 
        FROM users 
        WHERE nom LIKE ? OR prenom LIKE ? OR email LIKE ? OR email_auth LIKE ? OR username LIKE ?
        LIMIT 20
    ");
    $stmt->execute([$like, $like, $like, $like, $like]);
    $resultats['users'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ===== ÉTUDIANTS =====
    $stmt = $bdd->prepare("
        SELECT e.id_etudiant, e.matricule, u.nom, u.prenom, c.niveau, d.nom_dept
        FROM etudiants e
        JOIN users u ON e.id_user = u.id_user
        LEFT JOIN classes c ON e.id_classe = c.id_classe
        LEFT JOIN departements d ON c.id_dept = d.id_dept
        WHERE e.matricule LIKE ? OR u.nom LIKE ? OR u.prenom LIKE ?
        LIMIT 20
    ");
    $stmt->execute([$like, $like, $like]);
    $resultats['etudiants'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ===== PROFESSEURS =====
    $stmt = $bdd->prepare("
        SELECT p.id_prof, u.nom, u.prenom, u.email, p.specialite
        FROM professeurs p
        JOIN users u ON p.id_user = u.id_user
        WHERE u.nom LIKE ? OR u.prenom LIKE ? OR p.specialite LIKE ?
        LIMIT 20
    ");
    $stmt->execute([$like, $like, $like]);
    $resultats['professeurs'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ===== COURS =====
    $stmt = $bdd->prepare("
        SELECT c.id_cours, c.nom_cours, c.credit, cl.niveau
        FROM cours c
        JOIN classes cl ON c.id_classe = cl.id_classe
        WHERE c.nom_cours LIKE ?
        LIMIT 20
    ");
    $stmt->execute([$like]);
    $resultats['cours'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ===== CLASSES =====
    $stmt = $bdd->prepare("
        SELECT c.id_classe, c.niveau, d.nom_dept
        FROM classes c
        JOIN departements d ON c.id_dept = d.id_dept
        WHERE c.niveau LIKE ? OR d.nom_dept LIKE ?
        LIMIT 20
    ");
    $stmt->execute([$like, $like]);
    $resultats['classes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ===== NOTES =====
    $stmt = $bdd->prepare("
        SELECT n.id_note, n.note, n.session, u.nom, u.prenom, co.nom_cours
        FROM notes n
        JOIN etudiants e ON n.id_etudiant = e.id_etudiant
        JOIN users u ON e.id_user = u.id_user
        JOIN cours co ON n.id_cours = co.id_cours
        WHERE u.nom LIKE ? OR u.prenom LIKE ? OR co.nom_cours LIKE ?
        LIMIT 20
    ");
    $stmt->execute([$like, $like, $like]);
    $resultats['notes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ===== PAIEMENTS =====
    $stmt = $bdd->prepare("
        SELECT p.id_paiement, p.montant, p.bordereau, p.statut, u.nom, u.prenom
        FROM paiements p
        JOIN etudiants e ON p.id_etudiant = e.id_etudiant
        JOIN users u ON e.id_user = u.id_user
        WHERE p.bordereau LIKE ? OR u.nom LIKE ? OR u.prenom LIKE ?
        LIMIT 20
    ");
    $stmt->execute([$like, $like, $like]);
    $resultats['paiements'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($resultats as $r) $total_resultats += count($r);
}

$dashboards = [
    'Administrateur' => 'administrateur.php', 'Recteur' => 'dashboard_recteur.php',
    'Doyen' => 'dashboard_doyen.php', 'Comptable' => 'dashboard_comptable.php',
    'Professeur' => 'dashboard_professeur.php', 'Etudiant' => 'dashboard_etudiant.php'
];
$dashboard_url = $dashboards[$USER_ROLE] ?? 'Accueil.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Recherche globale - UniVerse</title>
    <link rel="stylesheet" href="assets/theme.css">
    <script src="assets/theme.js"></script>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI',Arial,sans-serif; }
        body { background:#eef3f8; padding:30px 20px; }
        .container { max-width:1200px; margin:0 auto; }
        .page-header { background:linear-gradient(135deg,#003366,#0055aa); color:white; text-align:center; padding:30px; border-radius:12px; box-shadow:0 4px 10px rgba(0,0,0,0.2); margin-bottom:20px; position:relative; }
        .page-header h1 { font-size:26px; margin-bottom:15px; }
        .theme-toggle-btn { position:absolute; top:15px; right:15px; background:white; border:1px solid #e2e8f0; width:42px; height:42px; border-radius:50%; font-size:18px; cursor:pointer; }
        .search-box { display:flex; gap:10px; max-width:700px; margin:0 auto; }
        .search-box input { flex:1; padding:16px 20px; border:none; border-radius:50px; font-size:16px; outline:none; box-shadow:0 4px 15px rgba(0,0,0,0.2); }
        .search-box button { padding:16px 32px; background:#f59e0b; color:white; border:none; border-radius:50px; font-weight:700; cursor:pointer; font-size:16px; box-shadow:0 4px 15px rgba(0,0,0,0.2); }
        .search-box button:hover { background:#d97706; }
        .stats-bar { background:white; padding:15px 25px; border-radius:10px; margin-bottom:20px; text-align:center; font-weight:600; color:#334155; box-shadow:0 2px 8px rgba(0,0,0,0.05); }
        .stats-bar strong { color:#003366; font-size:18px; }
        .section-card { background:white; border-radius:14px; margin-bottom:15px; box-shadow:0 4px 12px rgba(0,0,0,0.06); overflow:hidden; }
        .section-header { padding:15px 20px; background:#f8fafc; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; }
        .section-header h2 { font-size:16px; color:#003366; font-weight:700; }
        .section-header .count { background:#003366; color:white; padding:3px 12px; border-radius:20px; font-size:12px; font-weight:700; }
        .result-item { padding:12px 20px; border-bottom:1px solid #f1f5f9; display:flex; justify-content:space-between; align-items:center; }
        .result-item:last-child { border-bottom:none; }
        .result-item:hover { background:#f8fbff; }
        .result-item .main { font-weight:600; color:#1e293b; }
        .result-item .sub { font-size:12px; color:#64748b; margin-top:2px; }
        .result-item .badge { padding:3px 10px; border-radius:12px; font-size:11px; font-weight:600; }
        .badge-active { background:#d1fae5; color:#065f46; }
        .badge-inactive { background:#fee2e2; color:#991b1b; }
        .badge-valide { background:#d1fae5; color:#065f46; }
        .badge-en_attente { background:#fef3c7; color:#92400e; }
        .badge-rejete { background:#fee2e2; color:#991b1b; }
        .result-item a { color:#003366; text-decoration:none; font-weight:600; font-size:13px; }
        .result-item a:hover { text-decoration:underline; }
        .empty-state { text-align:center; padding:80px 20px; color:#94a3b8; }
        .empty-state h2 { font-size:22px; margin-bottom:10px; color:#334155; }
        .empty-state p { font-size:14px; }
        .icon-big { font-size:64px; display:block; margin-bottom:20px; }
    </style>
</head>
<body>
<div class="container">
    <div class="page-header">
        <div style="position:absolute; top:15px; left:15px;">
            <a href="<?= $dashboard_url ?>" style="background:rgba(255,255,255,0.2); color:white; border:1px solid rgba(255,255,255,0.3); text-decoration:none; font-size:13px; padding:8px 15px; border-radius:8px; font-weight:600;">← Retour</a>
        </div>
        <button class="theme-toggle-btn" onclick="toggleTheme()">🌙</button>
        <h1>🔍 Recherche Globale</h1>
        <form method="GET" class="search-box">
            <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Rechercher : nom, matricule, cours, bordereau..." autofocus>
            <button type="submit">Rechercher</button>
        </form>
    </div>

    <?php if ($q !== ''): ?>
        <div class="stats-bar">
            <strong><?= $total_resultats ?></strong> résultat(s) trouvé(s) pour "<strong><?= htmlspecialchars($q) ?></strong>"
        </div>
        
        <?php if ($total_resultats == 0): ?>
            <div class="empty-state">
                <span class="icon-big">📭</span>
                <h2>Aucun résultat</h2>
                <p>Aucune correspondance pour "<?= htmlspecialchars($q) ?>". Essayez un autre terme.</p>
            </div>
        <?php else: ?>
            
            <?php if (count($resultats['users']) > 0): ?>
            <div class="section-card">
                <div class="section-header"><h2>👥 Utilisateurs</h2><span class="count"><?= count($resultats['users']) ?></span></div>
                <?php foreach ($resultats['users'] as $u): ?>
                    <div class="result-item">
                        <div><div class="main"><?= htmlspecialchars($u['prenom'] . ' ' . $u['nom']) ?></div><div class="sub"><?= htmlspecialchars($u['email']) ?> — <?= htmlspecialchars($u['role']) ?></div></div>
                        <div style="display:flex; align-items:center; gap:10px;">
                            <span class="badge <?= $u['statut'] == 'Actif' ? 'badge-active' : 'badge-inactive' ?>"><?= $u['statut'] ?></span>
                            <a href="modifiuser.php?id=<?= $u['id_user'] ?>">Voir →</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <?php if (count($resultats['etudiants']) > 0): ?>
            <div class="section-card">
                <div class="section-header"><h2>🎓 Étudiants</h2><span class="count"><?= count($resultats['etudiants']) ?></span></div>
                <?php foreach ($resultats['etudiants'] as $e): ?>
                    <div class="result-item">
                        <div><div class="main"><?= htmlspecialchars($e['prenom'] . ' ' . $e['nom']) ?></div><div class="sub">Matricule : <?= htmlspecialchars($e['matricule'] ?? 'N/A') ?> — <?= htmlspecialchars($e['niveau'] ?? 'N/A') ?> (<?= htmlspecialchars($e['nom_dept'] ?? 'N/A') ?>)</div></div>
                        <a href="modifietudiant.php?id=<?= $e['id_etudiant'] ?>">Voir →</a>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <?php if (count($resultats['professeurs']) > 0): ?>
            <div class="section-card">
                <div class="section-header"><h2>👨‍🏫 Professeurs</h2><span class="count"><?= count($resultats['professeurs']) ?></span></div>
                <?php foreach ($resultats['professeurs'] as $p): ?>
                    <div class="result-item">
                        <div><div class="main"><?= htmlspecialchars($p['prenom'] . ' ' . $p['nom']) ?></div><div class="sub"><?= htmlspecialchars($p['email']) ?> — <?= htmlspecialchars($p['specialite'] ?? 'N/A') ?></div></div>
                        <a href="modifiprofesseur.php?id=<?= $p['id_prof'] ?>">Voir →</a>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <?php if (count($resultats['cours']) > 0): ?>
            <div class="section-card">
                <div class="section-header"><h2>📚 Cours</h2><span class="count"><?= count($resultats['cours']) ?></span></div>
                <?php foreach ($resultats['cours'] as $c): ?>
                    <div class="result-item">
                        <div><div class="main"><?= htmlspecialchars($c['nom_cours']) ?></div><div class="sub"><?= $c['credit'] ?> crédits — <?= htmlspecialchars($c['niveau']) ?></div></div>
                        <a href="modificours.php?id=<?= $c['id_cours'] ?>">Voir →</a>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <?php if (count($resultats['classes']) > 0): ?>
            <div class="section-card">
                <div class="section-header"><h2>🏫 Classes</h2><span class="count"><?= count($resultats['classes']) ?></span></div>
                <?php foreach ($resultats['classes'] as $c): ?>
                    <div class="result-item">
                        <div><div class="main"><?= htmlspecialchars($c['niveau']) ?></div><div class="sub"><?= htmlspecialchars($c['nom_dept']) ?></div></div>
                        <a href="modificlassedepartement.php?id=<?= $c['id_classe'] ?>">Voir →</a>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <?php if (count($resultats['notes']) > 0): ?>
            <div class="section-card">
                <div class="section-header"><h2>📝 Notes</h2><span class="count"><?= count($resultats['notes']) ?></span></div>
                <?php foreach ($resultats['notes'] as $n): ?>
                    <div class="result-item">
                        <div><div class="main"><?= htmlspecialchars($n['prenom'] . ' ' . $n['nom']) ?> — <?= htmlspecialchars($n['nom_cours']) ?></div><div class="sub"><?= number_format($n['note'], 2) ?>/20 — <?= htmlspecialchars($n['session']) ?></div></div>
                        <a href="modifinote.php?id=<?= $n['id_note'] ?>">Voir →</a>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <?php if (count($resultats['paiements']) > 0): ?>
            <div class="section-card">
                <div class="section-header"><h2>💰 Paiements</h2><span class="count"><?= count($resultats['paiements']) ?></span></div>
                <?php foreach ($resultats['paiements'] as $p): ?>
                    <div class="result-item">
                        <div><div class="main"><?= htmlspecialchars($p['prenom'] . ' ' . $p['nom']) ?> — <?= number_format($p['montant'], 0, ',', ' ') ?> BIF</div><div class="sub">Bordereau : <?= htmlspecialchars($p['bordereau'] ?? 'N/A') ?></div></div>
                        <div style="display:flex; gap:10px; align-items:center;">
                            <span class="badge badge-<?= $p['statut'] ?>"><?= $p['statut'] ?></span>
                            <a href="modifipaiement.php?id=<?= $p['id_paiement'] ?>">Voir →</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
        <?php endif; ?>
    
    <?php else: ?>
        <div class="empty-state">
            <span class="icon-big">🔍</span>
            <h2>Recherche Universelle</h2>
            <p>Recherchez dans <strong>utilisateurs, étudiants, professeurs, cours, classes, notes et paiements</strong>.</p>
            <p style="margin-top:10px; font-size:13px;">Exemples : <strong>Mizero</strong>, <strong>BAC I</strong>, <strong>Informatique</strong>, <strong>FSGE-2026</strong>, <strong>Bordereau</strong></p>
        </div>
    <?php endif; ?>
</div>
</body>
</html>