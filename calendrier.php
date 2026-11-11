<?php
require_once 'auth.php';
requireRole('Professeur');
session_start();
require_once 'dashboard_professeur.php';

// Ajouter un événement
if (isset($_POST['ajouter_evenement'])) {
    $titre = trim($_POST['titre']);
    $description = trim($_POST['description']);
    $date_debut = $_POST['date_debut'];
    $date_fin = $_POST['date_fin'] ?: null;
    $type = $_POST['type_evenement'];
    $couleur = $_POST['couleur'] ?: '#2563eb';

    if (!empty($titre) && !empty($date_debut)) {
        $stmt = $bdd->prepare("INSERT INTO evenements_calendrier (id_prof, titre, description, date_debut, date_fin, type_evenement, couleur) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$id_prof, $titre, $description, $date_debut, $date_fin, $type, $couleur]);
        $success = "✅ Événement ajouté !";
    } else {
        $error = "❌ Veuillez remplir tous les champs obligatoires.";
    }
}

// Supprimer un événement
if (isset($_GET['supprimer'])) {
    $id = intval($_GET['supprimer']);
    $stmt = $bdd->prepare("DELETE FROM evenements_calendrier WHERE id_evenement = ? AND id_prof = ?");
    $stmt->execute([$id, $id_prof]);
    $success = "🗑️ Événement supprimé.";
}

// Récupérer les événements
$evenements = $bdd->prepare("SELECT * FROM evenements_calendrier WHERE id_prof = ? ORDER BY date_debut ASC");
$evenements->execute([$id_prof]);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendrier — UniVerse</title>
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
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .event-item { display: flex; align-items: center; gap: 15px; padding: 12px 0; border-bottom: 1px solid #f1f5f9; }
        .event-item .couleur { width: 12px; height: 12px; border-radius: 50%; flex-shrink: 0; }
        .event-item .infos { flex: 1; }
        .event-item .titre { font-weight: 600; }
        .event-item .date { font-size: 13px; color: #64748b; }
        .badge { display: inline-block; padding: 2px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; background: #dbeafe; color: #1e40af; }
        .alert { padding: 12px; border-radius: 8px; margin-bottom: 15px; }
        .alert-success { background: #d1fae5; color: #065f46; }
        .alert-danger { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
<div class="container">
    <h1><i class="fas fa-calendar-check"></i> Mon calendrier</h1>

    <?php if (isset($success)): ?><div class="alert alert-success"><?= $success; ?></div><?php endif; ?>
    <?php if (isset($error)): ?><div class="alert alert-danger"><?= $error; ?></div><?php endif; ?>

    <!-- Formulaire d'ajout -->
    <div class="card">
        <h2>📅 Ajouter un événement</h2>
        <form method="POST">
            <div class="form-group">
                <label>Titre *</label>
                <input type="text" name="titre" placeholder="Titre de l'événement" required>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="2" placeholder="Description..."></textarea>
            </div>
            <div class="grid-2">
                <div class="form-group">
                    <label>Date début *</label>
                    <input type="datetime-local" name="date_debut" required>
                </div>
                <div class="form-group">
                    <label>Date fin</label>
                    <input type="datetime-local" name="date_fin">
                </div>
            </div>
            <div class="grid-2">
                <div class="form-group">
                    <label>Type</label>
                    <select name="type_evenement">
                        <option value="cours">📚 Cours</option>
                        <option value="examen">📝 Examen</option>
                        <option value="reunion">🤝 Réunion</option>
                        <option value="autre">📌 Autre</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Couleur</label>
                    <input type="color" name="couleur" value="#2563eb">
                </div>
            </div>
            <button type="submit" name="ajouter_evenement" class="btn btn-primary"><i class="fas fa-plus"></i> Ajouter</button>
        </form>
    </div>

    <!-- Liste des événements -->
    <div class="card">
        <h2>📋 Mes événements</h2>
        <?php if ($evenements->rowCount() > 0): ?>
            <?php while ($e = $evenements->fetch()): ?>
                <div class="event-item">
                    <span class="couleur" style="background:<?= htmlspecialchars($e['couleur']); ?>;"></span>
                    <div class="infos">
                        <div class="titre"><?= htmlspecialchars($e['titre']); ?></div>
                        <div class="date">
                            <i class="fas fa-calendar-alt"></i> <?= date('d/m/Y H:i', strtotime($e['date_debut'])); ?>
                            <?php if ($e['date_fin']): ?>
                                → <?= date('d/m/Y H:i', strtotime($e['date_fin'])); ?>
                            <?php endif; ?>
                            <span class="badge"><?= htmlspecialchars($e['type_evenement']); ?></span>
                        </div>
                        <?php if ($e['description']): ?>
                            <div style="font-size:13px; color:#475569;"><?= nl2br(htmlspecialchars($e['description'])); ?></div>
                        <?php endif; ?>
                    </div>
                    <a href="?supprimer=<?= $e['id_evenement']; ?>" class="btn btn-danger" onclick="return confirm('Supprimer cet événement ?')"><i class="fas fa-trash"></i></a>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p style="color:#94a3b8;">Aucun événement planifié.</p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>