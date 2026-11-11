<?php
require_once 'auth.php';
requireRole('Professeur');
session_start();
require_once 'dashboard_professeur.php';

// Traitement ajout d'annonce
if (isset($_POST['publier_annonce'])) {
    $titre = trim($_POST['titre']);
    $message = trim($_POST['message']);
    $id_cours = !empty($_POST['id_cours']) ? intval($_POST['id_cours']) : null;
    $id_classe = !empty($_POST['id_classe']) ? intval($_POST['id_classe']) : null;

    if (!empty($titre) && !empty($message)) {
        $stmt = $bdd->prepare("INSERT INTO annonces_professeurs (id_prof, id_cours, id_classe, titre, message) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$id_prof, $id_cours, $id_classe, $titre, $message]);
        $success = "✅ Annonce publiée avec succès !";
    } else {
        $error = "❌ Veuillez remplir tous les champs obligatoires.";
    }
}

// Suppression d'une annonce
if (isset($_GET['supprimer'])) {
    $id = intval($_GET['supprimer']);
    $stmt = $bdd->prepare("DELETE FROM annonces_professeurs WHERE id_annonce = ? AND id_prof = ?");
    $stmt->execute([$id, $id_prof]);
    $success = "🗑️ Annonce supprimée.";
}

// Récupération des annonces
$annonces = $bdd->prepare("
    SELECT a.*, c.nom_cours, cl.niveau
    FROM annonces_professeurs a
    LEFT JOIN cours c ON a.id_cours = c.id_cours
    LEFT JOIN classes cl ON a.id_classe = cl.id_classe
    WHERE a.id_prof = ?
    ORDER BY a.date_publication DESC
");
$annonces->execute([$id_prof]);

// Liste des cours et classes pour le formulaire
$coursList = $bdd->prepare("SELECT id_cours, nom_cours FROM cours WHERE id_prof = ?");
$coursList->execute([$id_prof]);
$classesList = $bdd->query("SELECT id_classe, niveau FROM classes ORDER BY niveau");
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Annonces — UniVerse</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f1f5f9; padding: 30px; }
        .container { max-width: 1000px; margin: 0 auto; }
        .card { background: white; padding: 25px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); border: 1px solid #e2e8f0; }
        .card h2 { margin-bottom: 15px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { font-weight: 600; display: block; margin-bottom: 5px; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #e2e8f0; }
        .btn { padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; }
        .btn-primary { background: #2563eb; color: white; }
        .btn-danger { background: #ef4444; color: white; }
        .annonce-item { border-bottom: 1px solid #e2e8f0; padding: 15px 0; }
        .annonce-item:last-child { border-bottom: none; }
        .annonce-item .titre { font-weight: 700; font-size: 18px; }
        .annonce-item .date { color: #64748b; font-size: 13px; }
        .annonce-item .message { margin: 10px 0; }
        .badge { display: inline-block; padding: 2px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; background: #dbeafe; color: #1e40af; }
        .alert { padding: 12px; border-radius: 8px; margin-bottom: 15px; }
        .alert-success { background: #d1fae5; color: #065f46; }
        .alert-danger { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
<div class="container">
    <h1><i class="fas fa-bullhorn"></i> Mes annonces</h1>

    <?php if (isset($success)): ?><div class="alert alert-success"><?= $success; ?></div><?php endif; ?>
    <?php if (isset($error)): ?><div class="alert alert-danger"><?= $error; ?></div><?php endif; ?>

    <!-- Formulaire de publication -->
    <div class="card">
        <h2>📢 Publier une annonce</h2>
        <form method="POST">
            <div class="form-group">
                <label>Titre *</label>
                <input type="text" name="titre" placeholder="Titre de l'annonce" required>
            </div>
            <div class="form-group">
                <label>Message *</label>
                <textarea name="message" rows="4" placeholder="Contenu de l'annonce..." required></textarea>
            </div>
            <div class="form-group">
                <label>Lier à un cours (optionnel)</label>
                <select name="id_cours">
                    <option value="">— Aucun —</option>
                    <?php while ($c = $coursList->fetch()): ?>
                        <option value="<?= $c['id_cours']; ?>"><?= htmlspecialchars($c['nom_cours']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Lier à une classe (optionnel)</label>
                <select name="id_classe">
                    <option value="">— Aucune —</option>
                    <?php while ($cl = $classesList->fetch()): ?>
                        <option value="<?= $cl['id_classe']; ?>"><?= htmlspecialchars($cl['niveau']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <button type="submit" name="publier_annonce" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Publier</button>
        </form>
    </div>

    <!-- Liste des annonces -->
    <div class="card">
        <h2>📋 Mes annonces publiées</h2>
        <?php if ($annonces->rowCount() > 0): ?>
            <?php while ($a = $annonces->fetch()): ?>
                <div class="annonce-item">
                    <div class="titre"><?= htmlspecialchars($a['titre']); ?></div>
                    <div class="date">
                        <i class="fas fa-calendar-alt"></i> <?= date('d/m/Y H:i', strtotime($a['date_publication'])); ?>
                        <?php if ($a['nom_cours']): ?>
                            <span class="badge">📚 <?= htmlspecialchars($a['nom_cours']); ?></span>
                        <?php endif; ?>
                        <?php if ($a['niveau']): ?>
                            <span class="badge">🏫 <?= htmlspecialchars($a['niveau']); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="message"><?= nl2br(htmlspecialchars($a['message'])); ?></div>
                    <a href="?supprimer=<?= $a['id_annonce']; ?>" class="btn btn-danger" onclick="return confirm('Supprimer cette annonce ?')"><i class="fas fa-trash"></i> Supprimer</a>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p style="color:#94a3b8;">Aucune annonce publiée pour le moment.</p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>