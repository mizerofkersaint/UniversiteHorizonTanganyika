<?php
require_once __DIR__ . '/db.php';
require_once 'config/smtp.php';
session_start();

$message = '';
$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email_auth'] ?? '');

    if (empty($email)) {
        $erreur = "Veuillez saisir votre email académique.";
    } else {
        try {
            $bdd = getDBConnection();
            $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $stmt = $bdd->prepare("SELECT id_user, nom, prenom, email FROM users WHERE email_auth = ? AND statut = 'Actif' LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', time() + 3600);

                $ins = $bdd->prepare("INSERT INTO password_resets (id_user, token, expires_at) VALUES (?, ?, ?)");
                $ins->execute([$user['id_user'], $token, $expires]);

                $lien = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reinitialiser_mdp.php?token=" . $token;

                try {
                    $mailer = creerMailerSMTP();
                    $mailer->addAddress($user['email']);
                    $mailer->isHTML(true);
                    $mailer->Subject = 'Réinitialisation mot de passe - UniVerse';
                    $mailer->Body = "
                        <p>Bonjour " . htmlspecialchars($user['prenom']) . ",</p>
                        <p>Vous avez demandé la réinitialisation de votre mot de passe.</p>
                        <p><a href='$lien' style='background:#003366;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;display:inline-block;'>Réinitialiser mon mot de passe</a></p>
                        <p>Ou copiez ce lien dans votre navigateur :<br>$lien</p>
                        <p>Ce lien expire dans 1 heure.</p>
                    ";
                    $mailer->send();
                } catch (Exception $e) {
                    error_log("Erreur reset email : " . $e->getMessage());
                }
            }

            $message = "Si votre email existe dans notre système, un lien de réinitialisation vous a été envoyé.";
        } catch (Exception $e) {
            error_log("Erreur reset mdp : " . $e->getMessage());
            $erreur = "Une erreur technique est survenue. Réessayez plus tard.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mot de passe oublié - Université Horizon Tanganyika (UHT)</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f8fafc; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 20px; box-sizing: border-box; }
        .card { background: white; padding: 35px; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,.08); width: 100%; max-width: 420px; }
        h1 { color: #0f172a; text-align: center; font-size: 24px; margin-bottom: 5px; }
        .subtitle { text-align: center; color: #64748b; font-size: 13px; margin-bottom: 25px; }
        .form-group { margin-bottom: 16px; }
        label { display: block; font-size: 13px; font-weight: bold; color: #475569; margin-bottom: 6px; }
        input { width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; box-sizing: border-box; font-size: 14px; }
        input:focus { outline: 2px solid #93c5fd; border-color: #3b82f6; }
        button { width: 100%; padding: 14px; background: #003366; color: white; border: 0; border-radius: 8px; font-weight: bold; font-size: 15px; cursor: pointer; }
        button:hover { background: #0055aa; }
        .alert-success { background: #d1fae5; color: #065f46; padding: 12px; border-radius: 8px; margin-bottom: 15px; font-size: 13px; }
        .alert-error { background: #fee2e2; color: #991b1b; padding: 12px; border-radius: 8px; margin-bottom: 15px; font-size: 13px; }
        .footer { text-align: center; margin-top: 20px; font-size: 13px; }
        .footer a { color: #003366; text-decoration: none; }
    </style>
</head>
<body>
    <main class="card">
        <h1>🔐 Mot de passe oublié</h1>
        <div class="subtitle">Saisissez votre email académique pour recevoir un lien de réinitialisation</div>

        <?php if ($message): ?>
            <div class="alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php if ($erreur): ?>
            <div class="alert-error"><?= htmlspecialchars($erreur) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="email_auth">Email académique</label>
                <input type="email" id="email_auth" name="email_auth" required placeholder="exemple@univ.local" autocomplete="email">
            </div>
            <button type="submit">Envoyer le lien de réinitialisation</button>
        </form>

        <div class="footer">
            <a href="login.php">← Retour à la connexion</a>
        </div>
    </main>
</body>
</html>