<?php
require_once __DIR__ . '/db.php';
session_start();

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$message = '';
$erreur = '';

if (empty($token)) {
    die("Token manquant. <a href='mot_de_passe_oublie.php'>Refaire une demande</a>");
}

try {
    $bdd = getDBConnection();
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $bdd->prepare("SELECT id_user FROM password_resets WHERE token = ? AND used = 0 AND expires_at > NOW() LIMIT 1");
    $stmt->execute([$token]);
    $reset = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reset) {
        die("Lien invalide ou expiré. <a href='mot_de_passe_oublie.php'>Refaire une demande</a>");
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $mdp = $_POST['mdp'] ?? '';
        $confirm = $_POST['confirm'] ?? '';

        if (strlen($mdp) < 6) {
            $erreur = "Le mot de passe doit contenir au moins 6 caractères.";
        } elseif ($mdp !== $confirm) {
            $erreur = "Les deux mots de passe ne correspondent pas.";
        } else {
            $hash = password_hash($mdp, PASSWORD_BCRYPT);

            $bdd->beginTransaction();
            $upd = $bdd->prepare("UPDATE users SET mot_de_passe = ?, tentative_connexion = 0, date_modification_mdp = NOW() WHERE id_user = ?");
            $upd->execute([$hash, $reset['id_user']]);

            $updToken = $bdd->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
            $updToken->execute([$token]);

            $bdd->commit();

            $message = "✅ Mot de passe modifié avec succès. <a href='login.php' style='color:#065f46;font-weight:bold;'>Se connecter</a>";
        }
    }
} catch (Exception $e) {
    error_log("Erreur reset mdp : " . $e->getMessage());
    $erreur = "Une erreur technique est survenue.";
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouveau mot de passe - UniVerse</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f8fafc; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 20px; box-sizing: border-box; }
        .card { background: white; padding: 35px; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,.08); width: 100%; max-width: 420px; }
        h1 { color: #0f172a; text-align: center; font-size: 24px; margin-bottom: 25px; }
        .form-group { margin-bottom: 16px; }
        label { display: block; font-size: 13px; font-weight: bold; color: #475569; margin-bottom: 6px; }
        input { width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; box-sizing: border-box; font-size: 14px; }
        input:focus { outline: 2px solid #93c5fd; border-color: #3b82f6; }
        button { width: 100%; padding: 14px; background: #003366; color: white; border: 0; border-radius: 8px; font-weight: bold; font-size: 15px; cursor: pointer; }
        button:hover { background: #0055aa; }
        .alert-success { background: #d1fae5; color: #065f46; padding: 12px; border-radius: 8px; margin-bottom: 15px; font-size: 13px; }
        .alert-error { background: #fee2e2; color: #991b1b; padding: 12px; border-radius: 8px; margin-bottom: 15px; font-size: 13px; }
    </style>
</head>
<body>
    <main class="card">
        <h1>🔐 Nouveau mot de passe</h1>

        <?php if ($message): ?>
            <div class="alert-success"><?= $message ?></div>
        <?php else: ?>
            <?php if ($erreur): ?>
                <div class="alert-error"><?= htmlspecialchars($erreur) ?></div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                <div class="form-group">
                    <label for="mdp">Nouveau mot de passe</label>
                    <input type="password" id="mdp" name="mdp" required minlength="6" placeholder="Minimum 6 caractères" autocomplete="new-password">
                </div>
                <div class="form-group">
                    <label for="confirm">Confirmer le mot de passe</label>
                    <input type="password" id="confirm" name="confirm" required minlength="6" placeholder="Retapez le mot de passe" autocomplete="new-password">
                </div>
                <button type="submit">Enregistrer le nouveau mot de passe</button>
            </form>
        <?php endif; ?>
    </main>
</body>
</html>