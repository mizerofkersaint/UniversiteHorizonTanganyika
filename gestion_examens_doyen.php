<?php
require_once __DIR__ . '/db.php';
require_once 'auth.php';
requireRole(['Doyen', 'Administrateur']);

try {
    $bdd = getDBConnection();
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Erreur : " . $e->getMessage());
}

$stmtDept = $bdd->prepare("SELECT id_dept FROM doyens WHERE id_user = ?");
$stmtDept->execute([$USER_ID]);
$id_dept_doyen = $stmtDept->fetchColumn();

if (!$id_dept_doyen) die("Aucun département assigné.");

$message_succes = '';
$message_erreur = '';

// ============================================================
// AJOUTER UN EXAMEN
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajouter_examen'])) {
    $id_cours = (int)($_POST['id_cours'] ?? 0);
    $titre = trim($_POST['titre'] ?? '');
    $type_examen = trim($_POST['type_examen'] ?? 'Examen');
    $date_prevue = trim($_POST['date_prevue'] ?? '');
    $heure_debut = trim($_POST['heure_debut'] ?? '');
    $heure_fin = trim($_POST['heure_fin'] ?? '');
    $duree = (int)($_POST['duree'] ?? 120);
    $points_total = (int)($_POST['points_total'] ?? 20);
    $instructions = trim($_POST['instructions'] ?? '');

    if ($id_cours <= 0 || $titre === '' || $date_prevue === '' || $heure_debut === '' || $heure_fin === '') {
        $message_erreur = "Tous les champs marqués * sont obligatoires (date et heures incluses).";
    } elseif ($heure_debut >= $heure_fin) {
        $message_erreur = "L'heure de début doit être inférieure à l'heure de fin.";
    } else {
        try {
            // Vérifier que le cours appartient au département
            $check = $bdd->prepare("SELECT c.id_classe, c.nom_cours FROM cours c JOIN classes cl ON c.id_classe = cl.id_classe WHERE c.id_cours = ? AND cl.id_dept = ?");
            $check->execute([$id_cours, $id_dept_doyen]);
            $cours_data = $check->fetch(PDO::FETCH_ASSOC);

            if (!$cours_data) {
                throw new Exception("Cours introuvable ou hors de votre département.");
            }

            // Insérer l'examen avec le doyen comme créateur
            $ins = $bdd->prepare("
                INSERT INTO examens 
                (id_cours, id_classe, id_prof, id_doyen, titre, type_examen, date_prevue, heure_debut, heure_fin, duree, points_total, instructions, statut, date_envoi)
                VALUES (?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'valide', NOW())
            ");
            $ins->execute([
                $id_cours, $cours_data['id_classe'], $USER_ID, $titre, $type_examen, 
                $date_prevue, $heure_debut, $heure_fin, $duree, $points_total, $instructions
            ]);

            $message_succes = "✅ Examen <strong>\"" . htmlspecialchars($titre) . "\"</strong> ajouté et visible par les étudiants de la classe concernée.";
        } catch (Exception $e) {
            $message_erreur = "Erreur : " . $e->getMessage();
        }
    }
}

// ============================================================
// SUPPRIMER UN EXAMEN
// ============================================================
if (isset($_GET['supprimer']) && (int)$_GET['supprimer'] > 0) {
    $id_supp = (int)$_GET['supprimer'];
    try {
        $check = $bdd->prepare("SELECT e.id_examen FROM examens e JOIN classes cl ON e.id_classe = cl.id_classe WHERE e.id_examen = ? AND cl.id_dept = ?");
        $check->execute([$id_supp, $id_dept_doyen]);
        if ($check->fetch()) {
            $bdd->prepare("DELETE FROM examens WHERE id_examen = ?")->execute([$id_supp]);
            header("Location: gestion_examens_doyen.php?supprime=1");
            exit;
        }
    } catch (Exception $e) { $message_erreur = $e->getMessage(); }
}

// ============================================================
// DONNÉES
// ============================================================
// Liste des cours du département
$stmt = $bdd->prepare("
    SELECT c.id_cours, c.nom_cours, cl.niveau, cl.id_classe 
    FROM cours c 
    JOIN classes cl ON c.id_classe = cl.id_classe 
    WHERE cl.id_dept = ? 
    ORDER BY cl.niveau, c.nom_cours
");
$stmt->execute([$id_dept_doyen]);
$cours_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Liste des examens du département
$stmt = $bdd->prepare("
    SELECT e.*, c.nom_cours, cl.niveau, cl.id_classe,
           CASE WHEN e.id_prof IS NOT NULL THEN CONCAT(u.nom, ' ', u.prenom) ELSE 'Doyen' END AS createur
    FROM examens e 
    JOIN cours c ON e.id_cours = c.id_cours 
    JOIN classes cl ON e.id_classe = cl.id_classe 
    LEFT JOIN professeurs p ON e.id_prof = p.id_prof 
    LEFT JOIN users u ON p.id_user = u.id_user
    WHERE cl.id_dept = ? 
    ORDER BY e.date_prevue DESC, e.heure_debut DESC
");
$stmt->execute([$id_dept_doyen]);
$examens_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion des examens - Doyen</title>
    <link rel="stylesheet" href="assets/theme.css">
    <script src="assets/theme.js"></script>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI',Arial,sans-serif; }
        body { background:#eef3f8; padding:30px 20px; }
        .container { max-width:1200px; margin:0 auto; }
        .page-header { background:linear-gradient(135deg,#003366,#0055aa); color:white; text-align:center; padding:25px; border-radius:12px; box-shadow:0 4px 10px rgba(0,0,0,0.2); margin-bottom:20px; position:relative; }
        .page-header h1 { font-size:24px; }
        .page-header p { font-size:13px; opacity:0.9; margin-top:5px; }
        .theme-toggle-btn { position:absolute; top:15px; right:15px; background:white; border:1px solid #e2e8f0; width:42px; height:42px; border-radius:50%; font-size:18px; cursor:pointer; }
        .card { background:white; border-radius:18px; box-shadow:0 10px 25px rgba(0,0,0,.07); padding:25px; margin-bottom:20px; }
        .card h2 { color:#003366; margin-bottom:15px; font-size:17px; padding-bottom:12px; border-bottom:2px solid #f1f5f9; }
        .form-grid { display:grid; grid-template-columns:1fr 1fr; gap:15px; }
        .form-group { display:flex; flex-direction:column; margin-bottom:15px; }
        .form-group.full { grid-column:1 / -1; }
        .form-group label { font-weight:600; font-size:13px; color:#334155; margin-bottom:6px; }
        .form-control { padding:11px 14px; border:1px solid #d1d5db; border-radius:10px; font-size:14px; background:#f8fafc; outline:none; width:100%; }
        .form-control:focus { border-color:#0055aa; box-shadow:0 0 0 3px rgba(0,85,170,.10); background:white; }
        textarea.form-control { resize:vertical; min-height:60px; }
        .btn { padding:11px 22px; border:none; border-radius:10px; font-weight:600; font-size:14px; cursor:pointer; text-decoration:none; display:inline-block; }
        .btn-primary { background:#003366; color:white; }
        .btn-primary:hover { background:#0055aa; }
        .btn-danger { background:#dc2626; color:white; }
        .btn-danger:hover { background:#b91c1c; }
        .alert { padding:14px 18px; border-radius:10px; margin-bottom:20px; font-weight:600; }
        .alert-success { background:#d1fae5; color:#065f46; border-left:4px solid #10b981; }
        .alert-error { background:#fee2e2; color:#991b1b; border-left:4px solid #dc2626; }
        table { width:100%; border-collapse:collapse; font-size:13px; }
        th { background:#003366; color:white; padding:12px; text-align:left; font-size:12px; text-transform:uppercase; }
        td { padding:12px; border-bottom:1px solid #f1f5f9; }
        tr:hover td { background:#f8fbff; }
        .badge { display:inline-block; padding:3px 10px; border-radius:10px; font-size:11px; font-weight:600; }
        .badge-info { background:#eff6ff; color:#1e40af; }
        .badge-success { background:#d1fae5; color:#065f46; }
        .badge-warning { background:#fef3c7; color:#92400e; }
        .empty { text-align:center; padding:40px; color:#94a3b8; }
        .info-box { background:#fffbeb; padding:12px 16px; border-radius:8px; margin-bottom:15px; font-size:13px; color:#92400e; border-left:3px solid #f59e0b; }
    </style>
</head>
<body>
<div class="container">
    <div class="page-header">
        <div style="position:absolute; top:15px; left:15px;">
            <a href="dashboard_doyen.php" style="background:rgba(255,255,255,0.2); color:white; border:1px solid rgba(255,255,255,0.3); text-decoration:none; font-size:13px; padding:8px 15px; border-radius:8px; font-weight:600;">← Retour</a>
        </div>
        <h1>📝 Gestion des Examens</h1>
        <p>Programmez les examens avec date et heure — visibles par les étudiants</p>
        <button class="theme-toggle-btn" onclick="toggleTheme()">🌙</button>
    </div>

    <?php if ($message_succes): ?><div class="alert alert-success"><?= $message_succes ?></div><?php endif; ?>
    <?php if ($message_erreur): ?><div class="alert alert-error">❌ <?= htmlspecialchars($message_erreur) ?></div><?php endif; ?>
    <?php if (isset($_GET['supprime'])): ?><div class="alert alert-success">✅ Examen supprimé.</div><?php endif; ?>

    <!-- FORMULAIRE AJOUT -->
    <div class="card">
        <h2>➕ Programmer un nouvel examen</h2>
        <div class="info-box">
            💡 En tant que Doyen, l'examen que vous créez est <strong>automatiquement validé</strong> et visible par les étudiants de la classe concernée.
        </div>
        <form method="POST">
            <div class="form-grid">
                <div class="form-group">
                    <label>Cours / Classe *</label>
                    <select name="id_cours" class="form-control" required>
                        <option value="">-- Choisir un cours --</option>
                        <?php foreach ($cours_list as $c): ?>
                            <option value="<?= $c['id_cours'] ?>"><?= htmlspecialchars($c['nom_cours'] . ' — ' . $c['niveau']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Titre de l'examen *</label>
                    <input type="text" name="titre" class="form-control" required placeholder="Ex: Examen final">
                </div>
                <div class="form-group">
                    <label>Type d'examen</label>
                    <select name="type_examen" class="form-control">
                        <option value="Examen">📝 Examen</option>
                        <option value="Interrogation">📋 Interrogation</option>
                        <option value="TP">🔬 TP</option>
                        <option value="TD">📖 TD</option>
                        <option value="Rattrapage">🔄 Rattrapage</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Date de l'examen *</label>
                    <input type="date" name="date_prevue" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Heure de début *</label>
                    <input type="time" name="heure_debut" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Heure de fin *</label>
                    <input type="time" name="heure_fin" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Durée (minutes)</label>
                    <input type="number" name="duree" class="form-control" value="120" min="15" max="300">
                </div>
                <div class="form-group">
                    <label>Points totaux</label>
                    <input type="number" name="points_total" class="form-control" value="20" min="1" max="100">
                </div>
                <div class="form-group full">
                    <label>Instructions (facultatif)</label>
                    <textarea name="instructions" class="form-control" placeholder="Instructions pour les étudiants..."></textarea>
                </div>
            </div>
            <button type="submit" name="ajouter_examen" class="btn btn-primary">✅ Programmer l'examen</button>
        </form>
    </div>

    <!-- LISTE DES EXAMENS -->
    <div class="card">
        <h2>📋 Examens programmés (<?= count($examens_list) ?>)</h2>
        <?php if (count($examens_list) > 0): ?>
            <div style="overflow-x:auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Titre</th>
                            <th>Cours</th>
                            <th>Classe</th>
                            <th>Type</th>
                            <th>Date</th>
                            <th>Horaire</th>
                            <th>Durée</th>
                            <th>Créé par</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($examens_list as $e): 
                            $badge_class = ($e['statut'] == 'valide') ? 'badge-success' : (($e['statut'] == 'rejete') ? 'badge-warning' : 'badge-info');
                        ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($e['titre']) ?></strong></td>
                                <td><?= htmlspecialchars($e['nom_cours']) ?></td>
                                <td><?= htmlspecialchars($e['niveau']) ?></td>
                                <td><span class="badge badge-info"><?= htmlspecialchars($e['type_examen']) ?></span></td>
                                <td><?= date('d/m/Y', strtotime($e['date_prevue'])) ?></td>
                                <td><strong><?= substr($e['heure_debut'], 0, 5) . ' - ' . substr($e['heure_fin'], 0, 5) ?></strong></td>
                                <td><?= $e['duree'] ?> min</td>
                                <td><?= htmlspecialchars($e['createur']) ?></td>
                                <td>
                                    <a href="?supprimer=<?= $e['id_examen'] ?>" class="btn btn-danger" style="padding:6px 12px; font-size:12px;" onclick="return confirm('Supprimer cet examen ?')">🗑️</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty">📭 Aucun examen programmé pour le moment.</div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>