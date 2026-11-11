<?php
require_once __DIR__ . '/db.php';
require_once 'auth.php';

try {
    $bdd = getDBConnection();
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Erreur : " . $e->getMessage());
}

$id_support = (int)($_GET['id'] ?? 0);
if ($id_support <= 0) {
    die("ID du support invalide. <a href='supports_cours.php'>Retour</a>");
}

// Récupérer le support
$stmt = $bdd->prepare("SELECT titre, description FROM supports_cours WHERE id_support = ?");
$stmt->execute([$id_support]);
$support = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$support) {
    die("Support introuvable. <a href='supports_cours.php'>Retour</a>");
}

// Ajouter un commentaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['contenu'])) {
    $contenu = trim($_POST['contenu']);
    $ins = $bdd->prepare("INSERT INTO commentaires_supports (id_support, id_user, contenu) VALUES (?, ?, ?)");
    $ins->execute([$id_support, $USER_ID, $contenu]);
    header("Location: commentaires_support.php?id=" . $id_support);
    exit;
}

// Récupérer les commentaires
$stmt = $bdd->prepare("
    SELECT c.*, u.nom, u.prenom, u.photo, u.role 
    FROM commentaires_supports c 
    JOIN users u ON c.id_user = u.id_user 
    WHERE c.id_support = ? 
    ORDER BY c.date_creation DESC
");
$stmt->execute([$id_support]);
$commentaires = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Commentaires - UniVerse</title>
    <link rel="stylesheet" href="assets/theme.css">
    <script src="assets/theme.js"></script>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI',Arial,sans-serif; }
        body { background:#eef3f8; padding:30px 20px; }
        .container { max-width:800px; margin:0 auto; }
        .page-header { background:linear-gradient(135deg,#003366,#0055aa); color:white; text-align:center; padding:25px; border-radius:12px; box-shadow:0 4px 10px rgba(0,0,0,0.2); margin-bottom:20px; position:relative; }
        .page-header h1 { font-size:20px; }
        .page-header p { font-size:13px; opacity:0.9; margin-top:5px; }
        .card { background:white; border-radius:18px; box-shadow:0 10px 25px rgba(0,0,0,.07); padding:25px; margin-bottom:20px; }
        .card h2 { color:#003366; margin-bottom:15px; font-size:17px; padding-bottom:12px; border-bottom:2px solid #f1f5f9; }
        .form-control { width:100%; padding:12px; border:1px solid #d1d5db; border-radius:10px; font-size:14px; outline:none; resize:vertical; min-height:80px; }
        .btn { padding:11px 22px; border:none; border-radius:10px; font-weight:600; font-size:14px; cursor:pointer; text-decoration:none; display:inline-block; }
        .btn-primary { background:#003366; color:white; margin-top:10px; }
        .btn-primary:hover { background:#0055aa; }
        .comment { border-bottom:1px solid #eef2f7; padding:15px 0; display:flex; gap:15px; }
        .comment:last-child { border-bottom:none; }
        .avatar { width:40px; height:40px; border-radius:50%; object-fit:cover; }
        .comment-content { flex:1; }
        .comment-header { display:flex; justify-content:space-between; margin-bottom:5px; }
        .comment-author { font-weight:700; font-size:14px; color:#1e293b; }
        .comment-role { font-size:11px; background:#eff6ff; color:#1e40af; padding:2px 8px; border-radius:10px; margin-left:5px; }
        .comment-date { font-size:12px; color:#64748b; }
        .comment-text { font-size:14px; color:#334155; line-height:1.5; }
        .empty { text-align:center; padding:30px; color:#94a3b8; }
    </style>
</head>
<body>
<div class="container">
    <div class="page-header">
        <div style="position:absolute; top:15px; left:15px;">
            <a href="supports_cours.php" style="background:rgba(255,255,255,0.2); color:white; border:1px solid rgba(255,255,255,0.3); text-decoration:none; font-size:13px; padding:8px 15px; border-radius:8px; font-weight:600;">← Retour aux supports</a>
        </div>
        <h1>💬 Commentaires</h1>
        <p><?= htmlspecialchars($support['titre']) ?></p>
        <button id="themeToggleBtn" class="theme-toggle-btn" onclick="toggleTheme()" title="Mode sombre" style="position:absolute; top:15px; right:15px; background:white; border:1px solid #e2e8f0; width:42px; height:42px; border-radius:50%; font-size:18px; cursor:pointer;">🌙</button>
    </div>

    <div class="card">
        <h2>✍️ Ajouter un commentaire</h2>
        <form method="POST">
            <textarea name="contenu" class="form-control" placeholder="Posez une question ou donnez votre avis..." required></textarea>
            <button type="submit" class="btn btn-primary">📩 Publier</button>
        </form>
    </div>

    <div class="card">
        <h2>📋 Discussion (<?= count($commentaires) ?>)</h2>
        <?php if (count($commentaires) > 0): ?>
            <?php foreach ($commentaires as $c): ?>
                <div class="comment">
                    <?php $img = (!empty($c['photo']) && file_exists("uploads/".$c['photo'])) ? $c['photo'] : 'default.png'; ?>
                    <img src="uploads/<?= htmlspecialchars($img) ?>" class="avatar">
                    <div class="comment-content">
                        <div class="comment-header">
                            <div>
                                <span class="comment-author"><?= htmlspecialchars($c['prenom'] . ' ' . $c['nom']) ?></span>
                                <span class="comment-role"><?= htmlspecialchars($c['role']) ?></span>
                            </div>
                            <span class="comment-date"><?= date('d/m/Y H:i', strtotime($c['date_creation'])) ?></span>
                        </div>
                        <div class="comment-text"><?= nl2br(htmlspecialchars($c['contenu'])) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty">📭 Aucun commentaire pour le moment. Soyez le premier à réagir !</div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>