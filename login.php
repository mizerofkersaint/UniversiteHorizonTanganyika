<?php
require_once __DIR__ . '/db.php';
// login.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/smtp.php';

$erreur = '';

if (!isset($_SESSION['csrf_login'])) {
    $_SESSION['csrf_login'] = bin2hex(random_bytes(32));
}

function redirigerSelonRole(string $role): void
{
    switch ($role) {
        case 'Administrateur':
            header('Location: administrateur.php');
            break;
        case 'Recteur':
            header('Location: dashboard_recteur.php');
            break;
        case 'Doyen':
            header('Location: dashboard_doyen.php');
            break;
        case 'Comptable':
            header('Location: dashboard_comptable.php');
            break;
        case 'Professeur':
            header('Location: dashboard_professeur.php');
            break;
        case 'Etudiant':
            header('Location: dashboard_etudiant.php');
            break;
        case 'Agent':
            header('Location: dashboard_agent.php');
            break;
        default:
            header('Location: Accueil.php');
            break;
    }
    exit;
}

if (isset($_SESSION['id_user'], $_SESSION['role'])) {
    redirigerSelonRole((string) $_SESSION['role']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['connexion'])) {
    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_login'], (string) $_POST['csrf_token'])
    ) {
        $erreur = 'La demande a expiré. Rechargez la page et réessayez.';
    } else {
        $email_auth = trim((string) ($_POST['email_auth'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($email_auth === '' || $password === '') {
            $erreur = 'Veuillez remplir tous les champs.';
        } else {
            try {
                $bdd = getDBConnection();

                $stmt = $bdd->prepare(
                    'SELECT id_user, nom, prenom, username, email, email_auth,
                            mot_de_passe, role, statut, photo, tentative_connexion
                     FROM users
                     WHERE email_auth = ?
                     LIMIT 1'
                );
                $stmt->execute([$email_auth]);
                $user = $stmt->fetch();

                if (!$user || !password_verify($password, $user['mot_de_passe'])) {
                    if ($user) {
                        $upd = $bdd->prepare("UPDATE users SET tentative_connexion = tentative_connexion + 1 WHERE id_user = ?");
                        $upd->execute([$user['id_user']]);
                        $nouvelles_tentatives = (int)$user['tentative_connexion'] + 1;

                        if ($nouvelles_tentatives >= 5) {
                            $upd2 = $bdd->prepare("UPDATE users SET statut = 'Suspendu', date_blocage = NOW() WHERE id_user = ?");
                            $upd2->execute([$user['id_user']]);
                            $erreur = 'Compte bloqué après 5 tentatives. Contactez l\'administration.';
                        } else {
                            $erreur = 'Mot de passe incorrect. Tentative ' . $nouvelles_tentatives . '/5.';
                        }
                    } else {
                        $erreur = 'Email académique ou mot de passe incorrect.';
                    }
                } elseif ($user['statut'] !== 'Actif') {
                    $erreur = 'Votre compte est ' . strtolower((string) $user['statut']) . '. Contactez l’administration.';
                } elseif (empty($user['email']) || !filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
                    $erreur = 'Aucune adresse email de récupération valide n’est enregistrée.';
                } else {
                    $idUser = (int) $user['id_user'];
                    $now = time();

                    $bdd->prepare("UPDATE users SET tentative_connexion = 0 WHERE id_user = ?")->execute([$idUser]);

                    $req = $bdd->prepare(
                        'SELECT id_user, dernier_envoi, debut_periode_envoi, nombre_envois
                         FROM codes_2fa WHERE id_user = ? LIMIT 1'
                    );
                    $req->execute([$idUser]);
                    $ancien = $req->fetch();

                    $periodeActive = $ancien && strtotime($ancien['debut_periode_envoi']) > ($now - 900);

                    if ($periodeActive && (int) $ancien['nombre_envois'] >= 3) {
                        $erreur = 'Trop de demandes de code. Réessayez dans 15 minutes.';
                    } elseif ($ancien && strtotime($ancien['dernier_envoi']) > ($now - 60)) {
                        session_regenerate_id(true);
                        $_SESSION['pending_2fa_user_id'] = $idUser;
                        $_SESSION['csrf_2fa'] = bin2hex(random_bytes(32));
                        header('Location: verifier_2fa.php');
                        exit;
                    } else {
                        $code = (string) random_int(100000, 999999);
                        $hash = password_hash($code, PASSWORD_DEFAULT);
                        $expiration = date('Y-m-d H:i:s', $now + 300);
                        $dateActuelle = date('Y-m-d H:i:s');

                        if ($ancien) {
                            $debutPeriode = $periodeActive ? $ancien['debut_periode_envoi'] : $dateActuelle;
                            $nombreEnvois = $periodeActive ? ((int) $ancien['nombre_envois'] + 1) : 1;

                            $upd = $bdd->prepare(
                                'UPDATE codes_2fa
                                 SET code_hash = ?, expires_at = ?, tentatives = 0, dernier_envoi = ?, debut_periode_envoi = ?, nombre_envois = ?
                                 WHERE id_user = ?'
                            );
                            $upd->execute([$hash, $expiration, $dateActuelle, $debutPeriode, $nombreEnvois, $idUser]);
                        } else {
                            $ins = $bdd->prepare(
                                'INSERT INTO codes_2fa (id_user, code_hash, expires_at, tentatives, dernier_envoi, debut_periode_envoi, nombre_envois)
                                 VALUES (?, ?, ?, 0, ?, ?, 1)'
                            );
                            $ins->execute([$idUser, $hash, $expiration, $dateActuelle, $dateActuelle]);
                        }

                        try {
                            $mailer = creerMailerSMTP();
                            $mailer->addAddress($user['email']);
                            $mailer->isHTML(true);
                            $mailer->Subject = 'Votre code de vérification UniVerse';
                            $mailer->Body = '<p>Bonjour ' . htmlspecialchars((string) $user['prenom']) . ',</p>
                                <p>Votre code de vérification UniVerse est :</p>
                                <p style="font-size:28px;font-weight:bold;letter-spacing:5px;">' . htmlspecialchars($code) . '</p>
                                <p>Ce code expire dans 5 minutes.</p>';
                            $mailer->AltBody = 'Votre code de vérification UniVerse est : ' . $code;
                            $mailer->send();
                        } catch (Throwable $mailError) {
                            error_log('UniVerse 2FA email error: ' . $mailError->getMessage());
                            $erreur = 'Impossible d’envoyer le code pour le moment.';
                        }

                        if ($erreur === '') {
                            session_regenerate_id(true);
                            $_SESSION['pending_2fa_user_id'] = $idUser;
                            $_SESSION['csrf_2fa'] = bin2hex(random_bytes(32));
                            header('Location: verifier_2fa.php');
                            exit;
                        }
                    }
                }
            } catch (Throwable $e) {
                error_log('UniVerse login error: ' . $e->getMessage());
                $erreur = 'Une erreur technique est survenue.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion — UniVerse</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f8fafc; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 20px; box-sizing: border-box; }
        .card { background: white; padding: 35px; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,.08); width: 100%; max-width: 400px; box-sizing: border-box; }
        h1 { color: #0f172a; text-align: center; font-size: 25px; }
        .subtitle, .footer { text-align: center; color: #64748b; font-size: 13px; }
        .subtitle { margin-bottom: 25px; }
        .form-group { margin-bottom: 16px; }
        label { display: block; font-size: 13px; font-weight: bold; color: #475569; margin-bottom: 6px; }
        input { width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; box-sizing: border-box; }
        input:focus { outline: 2px solid #93c5fd; border-color: #3b82f6; }
        button { width: 100%; padding: 14px; background: #003366; color: white; border: 0; border-radius: 8px; font-weight: bold; cursor: pointer; }
        button:hover { background: #0055aa; }
        .alert { background: #fee2e2; color: #991b1b; padding: 12px; border-radius: 8px; margin-bottom: 15px; font-size: 13px; }
        .info { background: #eff6ff; color: #1e40af; padding: 12px; border-radius: 8px; margin-bottom: 18px; font-size: 12px; }
        .footer { margin-top: 20px; }
    </style>
</head>
<body>
    <main class="card">
        <h1>🎓 Université Horizon Tanganyika (UHT)</h1>
        <div class="subtitle">Connectez-vous avec votre email académique</div>

        <?php if ($erreur !== ''): ?>
            <div class="alert"><?= htmlspecialchars($erreur) ?></div>
        <?php endif; ?>

        <div class="info">
            Saisissez votre email académique et votre mot de passe. Un code sera envoyé à l'adresse email de récupération.
        </div>

        <form method="POST" action="login.php">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_login']) ?>">
            <div class="form-group">
                <label for="email_auth">Email académique</label>
                <input type="email" id="email_auth" name="email_auth" placeholder="exemple@univ.local" required autofocus autocomplete="username">
            </div>
            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>
            <button type="submit" name="connexion">Se connecter</button>
        </form>

        <div style="text-align:center; margin-top:15px;">
            <a href="mot_de_passe_oublie.php" style="color:#2563eb; font-size:13px;">Mot de passe oublié ?</a>
        </div>

        <div class="footer">Pas encore de compte ? Contactez l’administration.</div>
    </main>
</body>
</html>