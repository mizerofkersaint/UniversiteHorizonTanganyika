<?php
require_once __DIR__ . '/db.php';
// profile_admin.php
require_once 'auth.php';
requireRole('Administrateur');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Simulation d'un ID si pas de session pour tester le design
//$id_user = isset($_SESSION['id_user']) ? $_SESSION['id_user'] : 1;

try {
    $bdd = getDBConnection();
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Erreur de connexion.");
}

$req = $bdd->prepare("SELECT * FROM users WHERE id_user = ?");
$req->execute([$id_user]);
$user = $req->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("Utilisateur introuvable.");
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mon Profil</title>
    <style>
        body { font-family: 'Inter', sans-serif; background: #f8fafc; padding: 30px; margin: 0; color: #1e293b; }
        .profile-card { background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; padding: 40px; max-width: 600px; margin: 0 auto; text-align: center; }
        .avatar { width: 120px; height: 120px; border-radius: 50%; background: #3b82f6; color: white; font-size: 40px; font-weight: bold; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px auto; }
        h1 { font-size: 24px; margin: 0 0 5px 0; color: #0f172a; }
        .role { color: #3b82f6; font-weight: 600; font-size: 14px; text-transform: uppercase; margin-bottom: 30px; display: block; }
        
        .info-grid { text-align: left; background: #f8fafc; padding: 20px; border-radius: 8px; margin-bottom: 30px; }
        .info-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #e2e8f0; }
        .info-row:last-child { border-bottom: none; }
        .info-label { font-weight: 600; color: #64748b; }
        .info-value { font-weight: 500; }
        
        .btn { display: inline-block; padding: 12px 24px; background: #3b82f6; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; transition: 0.2s; }
        .btn:hover { background: #2563eb; }
    </style>
</head>
<body>
    <div class="profile-card">
        <div class="avatar">
            <?= strtoupper(substr($user['prenom'], 0, 1) . substr($user['nom'], 0, 1)) ?>
        </div>
        <h1><?= htmlspecialchars($user['prenom'].' '.$user['nom']) ?></h1>
        <span class="role"><?= htmlspecialchars($user['role']) ?></span>

        <div class="info-grid">
            <div class="info-row">
                <span class="info-label">Nom d'utilisateur</span>
                <span class="info-value">@<?= htmlspecialchars($user['username']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Email Personnel</span>
                <span class="info-value"><?= htmlspecialchars($user['email']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Email Académique</span>
                <span class="info-value"><?= htmlspecialchars($user['email_auth']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Téléphone</span>
                <span class="info-value"><?= htmlspecialchars($user['telephone'] ?: 'Non renseigné') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Dernière connexion</span>
                <span class="info-value"><?= $user['derniere_connexion'] ? date('d/m/Y H:i', strtotime($user['derniere_connexion'])) : 'Jamais' ?></span>
            </div>
        </div>

        <a href="changer_mot_de_passe.php?iframe=1" class="btn">🔑 Changer mon mot de passe</a>
    </div>
</body>
</html>