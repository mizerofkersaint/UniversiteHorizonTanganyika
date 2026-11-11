<?php
require_once __DIR__ . '/db.php';
require_once 'auth.php';
requireRole(['Professeur', 'Administrateur', 'Etudiant']);

try {
    $bdd = getDBConnection();
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Erreur : " . $e->getMessage());
}

// Récupérer l'id_prof
$id_prof = 0;
if ($USER_ROLE === 'Professeur') {
    $stmt = $bdd->prepare("SELECT id_prof FROM professeurs WHERE id_user = ?");
    $stmt->execute([$USER_ID]);
    $id_prof = $stmt->fetchColumn();
    if (!$id_prof) die("Profil professeur introuvable.");
}

// Pour les étudiants : récupérer leur classe
$id_classe_etudiant = 0;
if ($USER_ROLE === 'Etudiant') {
    $stmt = $bdd->prepare("SELECT id_classe FROM etudiants WHERE id_user = ?");
    $stmt->execute([$USER_ID]);
    $id_classe_etudiant = $stmt->fetchColumn();
    if (!$id_classe_etudiant) die("Profil étudiant introuvable.");
}

$is_etudiant = ($USER_ROLE === 'Etudiant');
$is_prof_or_admin = in_array($USER_ROLE, ['Professeur', 'Administrateur']);

// Dictionnaire des dashboards
$dashboards = [
    'Administrateur' => 'administrateur.php', 'Recteur' => 'dashboard_recteur.php',
    'Doyen' => 'dashboard_doyen.php', 'Comptable' => 'dashboard_comptable.php',
    'Professeur' => 'dashboard_professeur.php', 'Etudiant' => 'dashboard_etudiant.php'
];
$dashboard_url = $dashboards[$USER_ROLE] ?? 'Accueil.php';

// Dossier upload
$upload_dir = __DIR__ . '/uploads/supports/';
if (!is_dir($upload_dir)) @mkdir($upload_dir, 0755, true);

$search = trim($_GET['search'] ?? '');
$message = '';
$erreur = '';

// ============================================================
// TRAITEMENT UPLOAD & VERSIONS
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload']) && $is_prof_or_admin) {
    $titre = trim($_POST['titre'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $id_cours = (int)($_POST['id_cours'] ?? 0);
    $semestre = trim($_POST['semestre'] ?? '');
    $annee_acad = trim($_POST['annee_academique'] ?? date('Y').'-'.(date('Y')+1));

    if ($titre === '' || $id_cours <= 0) {
        $erreur = "Le titre et le cours sont obligatoires.";
    } elseif (!isset($_FILES['fichier']) || $_FILES['fichier']['error'] !== UPLOAD_ERR_OK) {
        $erreur = "Aucun fichier sélectionné ou erreur d'upload.";
    } else {
        $file = $_FILES['fichier'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed_ext = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt', 'zip'];
        $max_size = 20 * 1024 * 1024;

        if (!in_array($ext, $allowed_ext)) {
            $erreur = "Format non autorisé.";
        } elseif ($file['size'] > $max_size) {
            $erreur = "Fichier trop volumineux (max 20 Mo).";
        } else {
            $checkCours = $bdd->prepare("SELECT c.id_classe, c.nom_cours FROM cours c WHERE c.id_cours = ? AND (c.id_prof = ? OR ? = 'Administrateur')");
            $checkCours->execute([$id_cours, $id_prof, $USER_ROLE]);
            $cours_data = $checkCours->fetch(PDO::FETCH_ASSOC);

            if (!$cours_data) {
                $erreur = "Cours introuvable ou non autorisé.";
            } else {
                $nom_fichier = 'support_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $chemin = $upload_dir . $nom_fichier;

                if (move_uploaded_file($file['tmp_name'], $chemin)) {
                    try {
                        // GESTION DES VERSIONS
                        $checkVersion = $bdd->prepare("SELECT MAX(version) FROM supports_cours WHERE id_cours = ? AND titre = ?");
                        $checkVersion->execute([$id_cours, $titre]);
                        $maxVersion = $checkVersion->fetchColumn();
                        $newVersion = $maxVersion ? $maxVersion + 1 : 1;

                        $ins = $bdd->prepare("INSERT INTO supports_cours (id_cours, id_prof, id_classe, titre, description, fichier, type_fichier, semestre, annee_academique, version) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $ins->execute([$id_cours, $id_prof ?: 0, $cours_data['id_classe'], $titre, $description, $nom_fichier, $ext, $semestre, $annee_acad, $newVersion]);
                        $message = "✅ Fichier uploadé avec succès (Version v$newVersion).";
                    } catch (Exception $e) {
                        @unlink($chemin);
                        $erreur = "Erreur BDD : " . $e->getMessage();
                    }
                } else {
                    $erreur = "Impossible d'enregistrer le fichier.";
                }
            }
        }
    }
}

// ============================================================
// PARTAGE (GÉNÉRATION DE TOKEN)
// ============================================================
if (isset($_GET['share']) && (int)$_GET['share'] > 0) {
    $id_to_share = (int)$_GET['share'];
    $token = bin2hex(random_bytes(16));
    $upd = $bdd->prepare("UPDATE supports_cours SET share_token = ? WHERE id_support = ?");
    if ($upd->execute([$token, $id_to_share])) {
        $lien = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/download_support.php?token=" . $token;
        $message = "✅ Lien de partage généré : <br><input type='text' value='$lien' style='width:100%; padding:8px; margin-top:5px; border:1px solid #10b981; border-radius:5px;' onclick='this.select()' readonly><small style='color:#065f46;'>Copiez ce lien pour le partager.</small>";
    }
}

// ============================================================
// SUPPRESSION
// ============================================================
if (isset($_GET['delete']) && (int)$_GET['delete'] > 0) {
    $id_del = (int)$_GET['delete'];
    try {
        $check = $bdd->prepare("SELECT fichier, id_prof FROM supports_cours WHERE id_support = ?");
        $check->execute([$id_del]);
        $sup = $check->fetch(PDO::FETCH_ASSOC);
        if ($sup && ($sup['id_prof'] == $id_prof || $USER_ROLE === 'Administrateur')) {
            @unlink($upload_dir . $sup['fichier']);
            $bdd->prepare("DELETE FROM supports_cours WHERE id_support = ?")->execute([$id_del]);
            header("Location: supports_cours.php?deleted=1");
            exit;
        }
    } catch (Exception $e) {
        $erreur = "Erreur suppression : " . $e->getMessage();
    }
}

// ============================================================
// DONNÉES
// ============================================================
$cours_list = [];
$supports = [];

if ($USER_ROLE === 'Administrateur') {
    $cours_list = $bdd->query("SELECT c.id_cours, c.nom_cours, cl.niveau FROM cours c JOIN classes cl ON c.id_classe = cl.id_classe ORDER BY c.nom_cours")->fetchAll();
    $supports = $bdd->query("SELECT s.*, c.nom_cours, cl.niveau, CONCAT(u.nom,' ',u.prenom) AS prof FROM supports_cours s JOIN cours c ON s.id_cours = c.id_cours JOIN classes cl ON s.id_classe = cl.id_classe LEFT JOIN professeurs p ON s.id_prof = p.id_prof LEFT JOIN users u ON p.id_user = u.id_user ORDER BY s.date_upload DESC")->fetchAll();
} elseif ($USER_ROLE === 'Professeur') {
    $stmt = $bdd->prepare("SELECT c.id_cours, c.nom_cours, cl.niveau FROM cours c JOIN classes cl ON c.id_classe = cl.id_classe WHERE c.id_prof = ? ORDER BY c.nom_cours");
    $stmt->execute([$id_prof]);
    $cours_list = $stmt->fetchAll();
    
    $stmt = $bdd->prepare("SELECT s.*, c.nom_cours, cl.niveau FROM supports_cours s JOIN cours c ON s.id_cours = c.id_cours JOIN classes cl ON s.id_classe = cl.id_classe WHERE s.id_prof = ? ORDER BY s.date_upload DESC");
    $stmt->execute([$id_prof]);
    $supports = $stmt->fetchAll();
} elseif ($USER_ROLE === 'Etudiant') {
    $stmt = $bdd->prepare("SELECT s.*, c.nom_cours, cl.niveau, CONCAT(u.nom, ' ', u.prenom) AS prof FROM supports_cours s JOIN cours c ON s.id_cours = c.id_cours JOIN classes cl ON s.id_classe = cl.id_classe LEFT JOIN professeurs p ON s.id_prof = p.id_prof LEFT JOIN users u ON p.id_user = u.id_user WHERE s.id_classe = ? ORDER BY s.date_upload DESC");
    $stmt->execute([$id_classe_etudiant]);
    $supports = $stmt->fetchAll();
}

// Filtre de recherche
if (!empty($search)) {
    $supports = array_values(array_filter($supports, function($s) use ($search) {
        return stripos($s['titre'] ?? '', $search) !== false || stripos($s['description'] ?? '', $search) !== false || stripos($s['nom_cours'] ?? '', $search) !== false || stripos($s['niveau'] ?? '', $search) !== false;
    }));
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Supports de cours - UniVerse</title>
    <link rel="stylesheet" href="assets/theme.css">
    <script src="assets/theme.js"></script>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI',Arial,sans-serif; }
        body { background:#eef3f8; padding:30px 20px; }
        .container { max-width:1200px; margin:0 auto; }
        .page-header { background:linear-gradient(135deg,#003366,#0055aa); color:white; text-align:center; padding:25px; border-radius:12px; box-shadow:0 4px 10px rgba(0,0,0,0.2); margin-bottom:20px; position:relative; }
        .page-header h1 { font-size:24px; }
        .page-header p { font-size:13px; opacity:0.9; margin-top:5px; }
        .theme-toggle-btn { position:absolute; top:15px; right:15px; background:white; border:1px solid #e2e8f0; width:42px; height:42px; border-radius:50%; font-size:18px; cursor:pointer; display:flex; align-items:center; justify-content:center; }
        .card { background:white; border-radius:18px; box-shadow:0 10px 25px rgba(0,0,0,.07); padding:25px; margin-bottom:20px; }
        .card h2 { color:#003366; margin-bottom:15px; font-size:17px; padding-bottom:12px; border-bottom:2px solid #f1f5f9; }
        .form-grid { display:grid; grid-template-columns:1fr 1fr; gap:15px; }
        .form-group { display:flex; flex-direction:column; margin-bottom:15px; }
        .form-group.full { grid-column:1 / -1; }
        .form-group label { font-weight:600; font-size:13px; color:#334155; margin-bottom:6px; }
        .form-control { padding:11px 14px; border:1px solid #d1d5db; border-radius:10px; font-size:14px; background:#f8fafc; outline:none; }
        .form-control:focus { border-color:#0055aa; box-shadow:0 0 0 3px rgba(0,85,170,.10); background:white; }
        textarea.form-control { resize:vertical; min-height:70px; }
        .btn { padding:11px 22px; border:none; border-radius:10px; font-weight:600; font-size:14px; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; gap:8px; transition:all 0.2s; }
        .btn-primary { background:#003366; color:white; }
        .btn-primary:hover { background:#0055aa; transform:translateY(-2px); }
        .btn-danger { background:#dc2626; color:white; }
        .btn-danger:hover { background:#b91c1c; }
        .btn-success { background:#16a34a; color:white; }
        .btn-success:hover { background:#15803d; }
        .alert { padding:14px 18px; border-radius:10px; margin-bottom:20px; font-weight:600; font-size:14px; }
        .alert-success { background:#d1fae5; color:#065f46; border-left:4px solid #10b981; }
        .alert-error { background:#fee2e2; color:#991b1b; border-left:4px solid #dc2626; }
        table { width:100%; border-collapse:collapse; background:white; border-radius:10px; overflow:hidden; font-size:13px; }
        th { background:#003366; color:white; padding:12px; text-align:left; font-size:12px; text-transform:uppercase; }
        td { padding:12px; border-bottom:1px solid #eef2f7; }
        tr:hover td { background:#f8fbff; }
        .type-badge { display:inline-block; padding:3px 10px; border-radius:12px; background:#eff6ff; color:#1e40af; font-weight:600; font-size:11px; text-transform:uppercase; }
        .empty { padding:40px; text-align:center; color:#94a3b8; }
        @media(max-width:700px){ .form-grid { grid-template-columns:1fr; } }
    </style>
</head>
<body>
<div class="container">
    <div class="page-header">
        <div style="position:absolute; top:15px; left:15px;">
            <a href="<?= $dashboard_url ?>" style="background:rgba(255,255,255,0.2); color:white; border:1px solid rgba(255,255,255,0.3); text-decoration:none; font-size:13px; padding:8px 15px; border-radius:8px; font-weight:600;">← Retour</a>
        </div>
        <h1>📚 Supports de cours</h1>
        <p>Uploadez et gérez les documents pédagogiques</p>
        <button id="themeToggleBtn" class="theme-toggle-btn" onclick="toggleTheme()" title="Mode sombre">🌙</button>
    </div>

    <?php if (isset($_GET['deleted'])): ?><div class="alert alert-success">✅ Fichier supprimé.</div><?php endif; ?>
    <?php if ($message): ?><div class="alert alert-success"><?= $message ?></div><?php endif; ?>
    <?php if ($erreur): ?><div class="alert alert-error">❌ <?= htmlspecialchars($erreur) ?></div><?php endif; ?>

    <?php if ($is_prof_or_admin): ?>
    <div class="card">
        <h2>📤 Uploader un nouveau support</h2>
        <form method="POST" enctype="multipart/form-data">
            <div class="form-grid">
                <div class="form-group full">
                    <label>Titre *</label>
                    <input type="text" name="titre" class="form-control" required placeholder="Ex: Cours 1 - Introduction">
                </div>
                <div class="form-group">
                    <label>Cours *</label>
                    <select name="id_cours" class="form-control" required>
                        <option value="">-- Choisir --</option>
                        <?php foreach ($cours_list as $c): ?>
                            <option value="<?= $c['id_cours'] ?>"><?= htmlspecialchars($c['nom_cours'] . ' (' . $c['niveau'] . ')') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Semestre</label>
                    <select name="semestre" class="form-control">
                        <option value="">-- Aucun --</option>
                        <option value="Semestre 1">Semestre 1</option>
                        <option value="Semestre 2">Semestre 2</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Année académique</label>
                    <input type="text" name="annee_academique" class="form-control" value="<?= date('Y') ?>-<?= date('Y')+1 ?>">
                </div>
                <div class="form-group">
                    <label>Fichier * (max 20 Mo)</label>
                    <input type="file" name="fichier" class="form-control" required accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt,.zip">
                </div>
                <div class="form-group full">
                    <label>Description</label>
                    <textarea name="description" class="form-control" placeholder="Description du document..."></textarea>
                </div>
            </div>
            <button type="submit" name="upload" class="btn btn-primary">📤 Uploader le fichier</button>
        </form>
    </div>
    <?php endif; ?>

    <div class="card">
        <h2>📋 <?= $is_etudiant ? 'Supports de ma classe' : 'Mes supports' ?> (<?= count($supports) ?>)</h2>
        
        <form method="GET" style="margin-bottom:15px; display:flex; gap:10px;">
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="🔍 Rechercher..." class="form-control" style="flex:1;">
            <button type="submit" class="btn btn-primary" style="padding:11px 20px;">🔍 Chercher</button>
            <?php if (!empty($search)): ?><a href="supports_cours.php" class="btn btn-danger" style="padding:11px 20px;">✖️ Reset</a><?php endif; ?>
        </form>
        
        <?php if (!empty($search)): ?>
            <div style="background:#eff6ff; padding:10px; border-radius:8px; margin-bottom:15px; font-size:13px; color:#1e40af;">
                🔍 <strong><?= count($supports) ?></strong> résultat(s) pour "<strong><?= htmlspecialchars($search) ?></strong>"
            </div>
        <?php endif; ?>

        <?php if (count($supports) > 0): ?>
            <div style="overflow-x:auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Titre</th>
                            <th>Cours</th>
                            <th>Classe</th>
                            <th>Type</th>
                            <th>Version</th>
                            <th>Date</th>
                            <?php if ($USER_ROLE === 'Administrateur'): ?><th>Professeur</th><?php endif; ?>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($supports as $s): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($s['titre']) ?></strong></td>
                                <td><?= htmlspecialchars($s['nom_cours']) ?></td>
                                <td><?= htmlspecialchars($s['niveau']) ?></td>
                                <td><span class="type-badge"><?= htmlspecialchars($s['type_fichier']) ?></span></td>
                                <td><strong>v<?= (int)($s['version'] ?? 1) ?></strong></td>
                                <td><?= date('d/m/Y H:i', strtotime($s['date_upload'])) ?></td>
                                <?php if ($USER_ROLE === 'Administrateur'): ?><td><?= htmlspecialchars($s['prof'] ?? 'N/A') ?></td><?php endif; ?>
                                <td>
                                    <a href="download_support.php?id=<?= $s['id_support'] ?>" class="btn btn-success" style="padding:6px 12px; font-size:12px;">📥 Télécharger</a>
                                    <a href="?share=<?= $s['id_support'] ?>" class="btn" style="padding:6px 12px; font-size:12px; background:#0ea5e9; color:white;">🔗 Partager</a>
                                    <a href="commentaires_support.php?id=<?= $s['id_support'] ?>" class="btn" style="padding:6px 12px; font-size:12px; background:#f59e0b; color:white;">💬 Commentaires</a>
                                    <?php if ($is_prof_or_admin): ?>
                                        <a href="?delete=<?= $s['id_support'] ?>" class="btn btn-danger" style="padding:6px 12px; font-size:12px;" onclick="return confirm('Supprimer ce support ?')">🗑️</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty">📭 Aucun support disponible pour le moment.</div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>