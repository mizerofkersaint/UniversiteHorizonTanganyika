<?php
require_once 'auth.php';
requireRole('Professeur');
session_start();
require_once 'dashboard_professeur.php';

// Envoyer un message
if (isset($_POST['envoyer_message'])) {
    $sujet = trim($_POST['sujet']);
    $contenu = trim($_POST['contenu']);
    $id_etudiant = !empty($_POST['id_etudiant']) ? intval($_POST['id_etudiant']) : null;
    $id_classe = !empty($_POST['id_classe']) ? intval($_POST['id_classe']) : null;
    $id_cours = !empty($_POST['id_cours']) ? intval($_POST['id_cours']) : null;

    if (!empty($sujet) && !empty($contenu) && ($id_etudiant || $id_classe || $id_cours)) {
        $stmt = $bdd->prepare("INSERT INTO messages_professeurs (id_prof, id_etudiant, id_classe, id_cours, sujet, contenu) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$id_prof, $id_etudiant, $id_classe, $id_cours, $sujet, $contenu]);
        $success = "✅ Message envoyé avec succès !";
    } else {
        $error = "❌ Veuillez remplir tous les champs obligatoires.";
    }
}

// Récupération des messages envoyés
$messages = $bdd->prepare("
    SELECT m.*, u.nom, u.prenom, e.matricule, c.nom_cours, cl.niveau
    FROM messages_professeurs m
    LEFT JOIN etudiants e ON m.id_etudiant = e.id_etudiant
    LEFT JOIN users u ON e.id_user = u.id_user
    LEFT JOIN cours c ON m.id_cours = c.id_cours
    LEFT JOIN classes cl ON m.id_classe = cl.id_classe
    WHERE m.id_prof = ?
    ORDER BY m.date_envoi DESC
");
$messages->execute([$id_prof]);

// Récupération des étudiants, cours et classes pour le formulaire
$etudiants = $bdd->prepare("
    SELECT DISTINCT e.id_etudiant, u.nom, u.prenom
    FROM etudiants e
    JOIN users u ON e.id_user = u.id_user
    JOIN classes c ON e.id_classe = c.id_classe
    JOIN cours co ON co.id_classe = c.id_classe
    WHERE co.id_prof = ?
    ORDER BY u.nom
");
$etudiants->execute([$id_prof]);

$coursList = $bdd->prepare("SELECT id_cours, nom_cours FROM cours WHERE id_prof = ?");
$coursList->execute([$id_prof]);

$classesList = $bdd->query("SELECT id_classe, niveau FROM classes ORDER BY niveau");
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messagerie — UniVerse</title>
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
        .btn-success { background: #10b981; color: white; }
        .message-item { border-bottom: 1px solid #e2e8f0; padding: 15px 0; }
        .message-item:last-child { border-bottom: none; }
        .message-item .sujet { font-weight: 700; }
        .message-item .date { color: #64748b; font-size: 13px; }
        .message-item .destinataire { font-size: 13px; color: #475569; }
        .badge { display: inline-block; padding: 2px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; background: #dbeafe; color: #1e40af; }
        .alert { padding: 12px; border-radius: 8px; margin-bottom: 15px; }
        .alert-success { background: #d1fae5; color: #065f46; }
        .alert-danger { background: #fee2e2; color: #991b1b; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
    </style>
</head>
<body>
<div class="container">
    <h1><i class="fas fa-envelope"></i> Messagerie</h1>

    <?php if (isset($success)): ?><div class="alert alert-success"><?= $success; ?></div><?php endif; ?>
    <?php if (isset($error)): ?><div class="alert alert-danger"><?= $error; ?></div><?php endif; ?>

    <!-- Formulaire d'envoi -->
    <div class="card">
        <h2>✉️ Nouveau message</h2>
        <form method="POST">
            <div class="form-group">
                <label>Sujet *</label>
                <input type="text" name="sujet" placeholder="Sujet du message" required>
            </div>
            <div class="form-group">
                <label>Message *</label>
                <textarea name="contenu" rows="4" placeholder="Votre message..." required></textarea>
            </div>
            <div class="grid-2">
                <div class="form-group">
                    <label>Envoyer à un étudiant</label>
                    <select name="id_etudiant">
                        <option value="">— Sélectionner —</option>
                        <?php while ($e = $etudiants->fetch()): ?>
                            <option value="<?= $e['id_etudiant']; ?>"><?= htmlspecialchars($e['nom'].' '.$e['prenom']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Envoyer à une classe</label>
                    <select name="id_classe">
                        <option value="">— Sélectionner —</option>
                        <?php while ($cl = $classesList->fetch()): ?>
                            <option value="<?= $cl['id_classe']; ?>"><?= htmlspecialchars($cl['niveau']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
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
            <button type="submit" name="envoyer_message" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Envoyer</button>
        </form>
    </div>

    <!-- Messages envoyés -->
    <div class="card">
        <h2>📋 Historique des messages</h2>
        <?php if ($messages->rowCount() > 0): ?>
            <?php while ($m = $messages->fetch()): ?>
                <div class="message-item">
                    <div class="sujet"><?= htmlspecialchars($m['sujet']); ?></div>
                    <div class="destinataire">
                        <?php if ($m['nom']): ?>
                            👤 <?= htmlspecialchars($m['nom'].' '.$m['prenom']); ?> (<?= htmlspecialchars($m['matricule']); ?>)
                        <?php elseif ($m['niveau']): ?>
                            🏫 Classe <?= htmlspecialchars($m['niveau']); ?>
                        <?php else: ?>
                            📚 <?= htmlspecialchars($m['nom_cours'] ?? 'Tous'); ?>
                        <?php endif; ?>
                        <span class="badge"><?= $m['lu'] ? '✅ Lu' : '⏳ Non lu'; ?></span>
                    </div>
                    <div class="date"><i class="fas fa-calendar-alt"></i> <?= date('d/m/Y H:i', strtotime($m['date_envoi'])); ?></div>
                    <div><?= nl2br(htmlspecialchars($m['contenu'])); ?></div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p style="color:#94a3b8;">Aucun message envoyé.</p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>