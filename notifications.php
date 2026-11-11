<?php
require_once 'auth.php';
requireRole('Professeur');
session_start();
require_once 'dashboard_professeur.php';

// Marquer une notification comme lue
if (isset($_GET['marquer_lu'])) {
    $id = intval($_GET['marquer_lu']);
    $stmt = $bdd->prepare("UPDATE notifications SET statut = 'lu' WHERE id_notification = ?");
    $stmt->execute([$id]);
    header("Location: notifications.php");
    exit();
}

// Récupérer les notifications
$notifications = $bdd->prepare("
    SELECT * FROM notifications
    WHERE source_table = 'professeur' OR source_table = '' OR source_table IS NULL
    ORDER BY date_creation DESC
");
$notifications->execute();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications — UniVerse</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f1f5f9; padding: 30px; }
        .container { max-width: 800px; margin: 0 auto; }
        .card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); border: 1px solid #e2e8f0; }
        .card h2 { margin-bottom: 15px; }
        .notif-item { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #f1f5f9; }
        .notif-item:last-child { border-bottom: none; }
        .notif-item .contenu { flex: 1; }
        .notif-item .titre { font-weight: 600; }
        .notif-item .date { font-size: 12px; color: #64748b; }
        .notif-item .statut { font-size: 11px; font-weight: 600; padding: 2px 10px; border-radius: 12px; }
        .statut-non_lu { background: #dbeafe; color: #1e40af; }
        .statut-lu { background: #e2e8f0; color: #475569; }
        .btn { padding: 5px 12px; border: none; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 600; }
        .btn-primary { background: #2563eb; color: white; }
    </style>
</head>
<body>
<div class="container">
    <h1><i class="fas fa-bell"></i> Mes notifications</h1>

    <div class="card">
        <h2>📋 Liste des notifications</h2>
        <?php if ($notifications->rowCount() > 0): ?>
            <?php while ($n = $notifications->fetch()): ?>
                <div class="notif-item">
                    <div class="contenu">
                        <div class="titre"><?= htmlspecialchars($n['titre']); ?></div>
                        <div><?= htmlspecialchars($n['message']); ?></div>
                        <div class="date"><i class="fas fa-calendar-alt"></i> <?= date('d/m/Y H:i', strtotime($n['date_creation'])); ?></div>
                    </div>
                    <div>
                        <span class="statut statut-<?= $n['statut']; ?>"><?= $n['statut'] == 'non_lu' ? '⏳ Non lu' : '✅ Lu'; ?></span>
                        <?php if ($n['statut'] == 'non_lu'): ?>
                            <a href="?marquer_lu=<?= $n['id_notification']; ?>" class="btn btn-primary">Marquer lu</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p style="color:#94a3b8;">Aucune notification.</p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>