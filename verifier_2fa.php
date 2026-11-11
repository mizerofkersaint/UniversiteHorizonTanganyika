<?php
require_once __DIR__ . '/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['pending_2fa_user_id'])) {
    header('Location: login.php');
    exit;
}

if (empty($_SESSION['csrf_2fa'])) {
    $_SESSION['csrf_2fa'] = bin2hex(random_bytes(32));
}

$erreur = '';

try {
    $bdd = getDBConnection();

    $idUser = (int) $_SESSION['pending_2fa_user_id'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_2fa'], (string)$_POST['csrf_token'])) {
            $erreur = 'La demande a expiré.';
        } else {
            $code = trim((string) ($_POST['code'] ?? ''));

            if (!preg_match('/^\d{6}$/', $code)) {
                $erreur = 'Saisissez le code à 6 chiffres.';
            } else {
                $stmt = $bdd->prepare('SELECT id_user, code_hash, expires_at, tentatives FROM codes_2fa WHERE id_user = ? LIMIT 1');
                $stmt->execute([$idUser]);
                $verification = $stmt->fetch();

                if (!$verification) {
                    $erreur = 'Aucun code actif.';
                } elseif (strtotime($verification['expires_at']) < time()) {
                    $bdd->prepare('DELETE FROM codes_2fa WHERE id_user = ?')->execute([$idUser]);
                    $erreur = 'Le code a expiré.';
                } elseif ((int) $verification['tentatives'] >= 5) {
                    $bdd->prepare('DELETE FROM codes_2fa WHERE id_user = ?')->execute([$idUser]);
                    unset($_SESSION['pending_2fa_user_id'], $_SESSION['csrf_2fa']);
                    $erreur = 'Nombre maximal de tentatives atteint.';
                } elseif (!password_verify($code, $verification['code_hash'])) {
                    $bdd->prepare('UPDATE codes_2fa SET tentatives = tentatives + 1 WHERE id_user = ?')->execute([$idUser]);
                    $erreur = 'Code incorrect.';
                } else {
                    $stmtUser = $bdd->prepare('SELECT id_user, nom, prenom, username, email, email_auth, role, statut, photo FROM users WHERE id_user = ? LIMIT 1');
                    $stmtUser->execute([$idUser]);
                    $user = $stmtUser->fetch();

                    if (!$user || $user['statut'] !== 'Actif') {
                        $bdd->prepare('DELETE FROM codes_2fa WHERE id_user = ?')->execute([$idUser]);
                        unset($_SESSION['pending_2fa_user_id'], $_SESSION['csrf_2fa']);
                        $erreur = 'Votre compte ne peut pas être connecté.';
                    } else {
                        $bdd->prepare('DELETE FROM codes_2fa WHERE id_user = ?')->execute([$idUser]);
                        session_regenerate_id(true);

                        $_SESSION['id_user'] = $user['id_user'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['nom'] = $user['nom'];
                        $_SESSION['prenom'] = $user['prenom'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['email_auth'] = $user['email_auth'];
                        $_SESSION['photo'] = $user['photo'];

                        unset($_SESSION['pending_2fa_user_id'], $_SESSION['csrf_2fa'], $_SESSION['csrf_login']);

                        $bdd->prepare('UPDATE users SET derniere_connexion = NOW() WHERE id_user = ?')->execute([$idUser]);

                        switch ($user['role']) {
                            case 'Administrateur': header('Location: administrateur.php'); break;
                            case 'Recteur':        header('Location: dashboard_recteur.php'); break;
                            case 'Doyen':          header('Location: dashboard_doyen.php'); break;
                            case 'Comptable':      header('Location: dashboard_comptable.php'); break;
                            case 'Professeur':     header('Location: dashboard_professeur.php'); break;
                            case 'Etudiant':       header('Location: dashboard_etudiant.php'); break;
                            case 'Agent':          header('Location: dashboard_agent.php'); break;
                            default:               header('Location: Accueil.php');
                        }
                        exit;
                    }
                }
            }
        }
    }
} catch (Throwable $e) {
    error_log('UniVerse 2FA error: ' . $e->getMessage());
    $erreur = 'Une erreur technique est survenue.';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérification 2FA — UniVerse</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f8fafc; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 20px; box-sizing: border-box; }
        .card { background: #fff; width: 100%; max-width: 420px; padding: 32px; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,.08); box-sizing: border-box; }
        h1 { text-align: center; color: #0f172a; }
        p { color: #64748b; line-height: 1.5; text-align: center; }
        label { display: block; margin: 20px 0 7px; font-weight: bold; font-size: 14px; color: #334155; }
        input { width: 100%; box-sizing: border-box; padding: 13px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 22px; text-align: center; letter-spacing: 7px; }
        button { width: 100%; margin-top: 18px; padding: 14px; background: #003366; color: white; border: 0; border-radius: 8px; font-weight: bold; cursor: pointer; }
        button:hover { background: #0055aa; }
        .alert { background: #fee2e2; color: #991b1b; padding: 12px; border-radius: 8px; font-size: 13px; }
        .help { font-size: 12px; margin-top: 18px; }
        a { color: #2563eb; }
    </style>
</head>
<body>
    <main class="card">
        <h1>🔐 Vérification Université Horizon Tanganyika (UHT)</h1>
        <p>Un code de vérification a été envoyé à votre adresse email de récupération.</p>

        <?php if ($erreur !== ''): ?>
            <div class="alert"><?= htmlspecialchars($erreur) ?></div>
        <?php endif; ?>

        <form method="POST" action="verifier_2fa.php">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_2fa']) ?>">
            <label for="code">Code de vérification</label>
            <input type="text" id="code" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" placeholder="000000" required autofocus>
            <button type="submit">Vérifier et se connecter</button>
        </form>

        <p class="help">Le code est valable 5 minutes. <a href="login.php">Retour à la connexion</a></p>
    </main>
</body>
</html>