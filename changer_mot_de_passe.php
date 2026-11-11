<?php
require_once __DIR__ . '/db.php';
// ============================================================
// DÉMARRAGE DE SESSION — AVANT tout envoi HTML
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $bdd = getDBConnection();
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    error_log("[changer_mot_de_passe] Erreur connexion : " . $e->getMessage());
    die("Erreur de connexion à la base de données.");
}

// Inclusion du système d'archivage centralisé
require_once 'functions_archivage.php';

/* ============================================================
   RÉCUPÉRATION DE L'UTILISATEUR CONNECTÉ
   - getUserIdEnSession($bdd) retourne NULL si pas de session
   - On ne fabrique AUCUN identifiant utilisateur
============================================================ */
$id_utilisateur = getUserIdEnSession($bdd);
$force = isset($_GET['force']) && $_GET['force'] == '1';

$message_succes = '';
$message_erreur = '';

// Si l'utilisateur n'est pas identifié, on refuse l'accès au formulaire.
// (Ceci sera amélioré quand l'authentification sera en place.)
if ($id_utilisateur === null) {
    $message_erreur = "Vous devez être connecté pour modifier votre mot de passe.";
} else {

    /* ============================================================
       TRAITEMENT DU FORMULAIRE
    ============================================================ */
    if (isset($_POST['changer_mdp'])) {

        $mdp_actuel     = isset($_POST['mdp_actuel'])     ? $_POST['mdp_actuel']     : '';
        $nouveau_mdp    = isset($_POST['nouveau_mdp'])    ? $_POST['nouveau_mdp']    : '';
        $confirmation   = isset($_POST['confirmation'])   ? $_POST['confirmation']   : '';

        // 1. Champs obligatoires
        if ($mdp_actuel === '' || $nouveau_mdp === '' || $confirmation === '') {
            $message_erreur = "Tous les champs sont obligatoires.";
        }
        // 2. Nouveau mot de passe et confirmation identiques
        elseif ($nouveau_mdp !== $confirmation) {
            $message_erreur = "Le nouveau mot de passe et sa confirmation ne correspondent pas.";
        }
        // 3. Longueur minimale
        elseif (strlen($nouveau_mdp) < 6) {
            $message_erreur = "Le nouveau mot de passe doit contenir au moins 6 caractères.";
        }
        // 4. Nouveau mot de passe différent de l'ancien
        elseif ($nouveau_mdp === $mdp_actuel) {
            $message_erreur = "Le nouveau mot de passe doit être différent de l'ancien.";
        }
        else {
            try {
                // Récupérer le hash actuel
                $stmt = $bdd->prepare("SELECT mot_de_passe FROM users WHERE id_user = ? LIMIT 1");
                $stmt->execute([$id_utilisateur]);
                $hash_actuel = $stmt->fetchColumn();

                if (!$hash_actuel) {
                    throw new Exception("Utilisateur introuvable.");
                }

                // Vérifier le mot de passe actuel
                if (!password_verify($mdp_actuel, $hash_actuel)) {
                    throw new Exception("Le mot de passe actuel est incorrect.");
                }

                // Hacher le nouveau mot de passe
                $nouveau_hash = password_hash($nouveau_mdp, PASSWORD_BCRYPT);

                // Ancien état (pour archivage) — SANS le mot de passe
                $ancienne_valeur = getDonneesEnregistrement($bdd, 'users', $id_utilisateur);
                if (is_array($ancienne_valeur)) {
                    unset($ancienne_valeur['mot_de_passe']);
                }

                // UPDATE
                $stmt = $bdd->prepare("
                    UPDATE users
                    SET mot_de_passe = ?, date_modification_mdp = NOW()
                    WHERE id_user = ?
                ");
                $stmt->execute([$nouveau_hash, $id_utilisateur]);
				$bdd->prepare("UPDATE users SET premiere_connexion = 'Non' WHERE id_user = ?")->execute([$id_utilisateur]);

                // Nouvel état (pour archivage) — SANS le mot de passe
                $nouvelle_valeur = getDonneesEnregistrement($bdd, 'users', $id_utilisateur);
                if (is_array($nouvelle_valeur)) {
                    unset($nouvelle_valeur['mot_de_passe']);
                }

                // Archivage centralisé
                archiverAction(
                    $bdd,
                    $id_utilisateur,
                    'UPDATE',
                    'users',
                    $id_utilisateur,
                    $ancienne_valeur,
                    $nouvelle_valeur,
                    'Changement de mot de passe'
                );

                // Redirection pour éviter le renvoi du formulaire au refresh
				// Redirection vers le dashboard selon le rôle
				switch ($_SESSION['role']) {
					case 'Administrateur': header("Location: administrateur.php"); break;
					case 'Recteur':        header("Location: dashboard_recteur.php"); break;
					case 'Doyen':          header("Location: dashboard_doyen.php"); break;
					case 'Comptable':      header("Location: dashboard_comptable.php"); break;
					case 'Professeur':     header("Location: dashboard_professeur.php"); break;
					case 'Etudiant':       header("Location: dashboard_etudiant.php"); break;
					default:               header("Location: Accueil.php");
				}
				exit();

            } catch (Exception $e) {
                error_log("[changer_mot_de_passe] Erreur : " . $e->getMessage());
                $message_erreur = $e->getMessage();
            }
        }
    }

    // Message de succès via paramètre d'URL (post-redirection)
    if (isset($_GET['success']) && $_GET['success'] === '1') {
        $message_succes = "Votre mot de passe a été modifié avec succès.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Changer mon mot de passe — UniVerse</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Arial, sans-serif; }
        body { background: #eef3f8; color: #1f2937; }
        .container { width: 95%; max-width: 600px; margin: 40px auto; }
        .page-header { background: linear-gradient(135deg, #003366, #0055aa); color: white; text-align: center; padding: 25px; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.2); margin-bottom: 20px; }
        .page-header h1 { font-size: 24px; }
        .card { background: #fff; border-radius: 18px; box-shadow: 0 10px 25px rgba(0,0,0,.07); padding: 30px; }
        .card h2 { color: #003366; margin-bottom: 20px; text-align: center; font-size: 18px; }
        .alert { padding: 14px 18px; border-radius: 10px; margin-bottom: 20px; font-weight: 600; font-size: 14px; }
        .alert-success { background: #d1fae5; color: #065f46; border-left: 4px solid #10b981; }
        .alert-error { background: #fee2e2; color: #991b1b; border-left: 4px solid #dc2626; }
        .form-group { display: flex; flex-direction: column; margin-bottom: 18px; }
        .form-group label { font-weight: 600; font-size: 13px; color: #334155; margin-bottom: 6px; }
        input[type="password"] { padding: 12px 14px; border: 1px solid #d1d5db; border-radius: 10px; font-size: 14px; outline: none; transition: border-color 0.3s; background: #f8fafc; }
        input[type="password"]:focus { border-color: #0055aa; box-shadow: 0 0 0 3px rgba(0,85,170,.10); background: #fff; }
        .hint { font-size: 12px; color: #6b7280; margin-top: 4px; }
        .buttons { display: flex; justify-content: center; gap: 15px; margin-top: 25px; padding-top: 20px; border-top: 1px solid #e2e8f0; }
        .btn { padding: 12px 24px; border: none; border-radius: 10px; font-size: 15px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: 8px; transition: all 0.2s; }
        .btn-success { background: #16a34a; color: white; }
        .btn-success:hover { background: #15803d; transform: translateY(-2px); }
        .btn-cancel { background: #dc2626; color: white; }
        .btn-cancel:hover { background: #b91c1c; transform: translateY(-2px); }
        @media (max-width: 700px) { .buttons { flex-direction: column; } .btn { width: 100%; } }
    </style>
</head>
<body>
<?php include("menu.php"); ?>

<div class="container">

<div class="page-header">
        <h1>🔐 Changer mon mot de passe</h1>
    </div>

    <?php if ($force): ?>
        <div class="alert alert-warning" style="background:#fef3c7; color:#92400e; border-left:4px solid #f59e0b;">
            ⚠️ <strong>Première connexion :</strong> vous devez changer votre mot de passe avant de continuer à utiliser le système.
        </div>
    <?php endif; ?>

    <?php if ($message_succes !== ''): ?>
        <div class="alert alert-success">✅ <?= htmlspecialchars($message_succes) ?></div>
    <?php endif; ?>
    <?php if ($message_erreur !== ''): ?>
        <div class="alert alert-error">❌ <?= htmlspecialchars($message_erreur) ?></div>
    <?php endif; ?>

    <div class="card">
        <?php if ($id_utilisateur === null): ?>

            <h2>Accès refusé</h2>
            <p style="text-align:center; color:#64748b;">
                Vous devez être connecté pour accéder à cette page.
            </p>

        <?php else: ?>

            <h2>Sécurité du compte</h2>

            <form method="POST" action="">
                <div class="form-group">
                    <label>Mot de passe actuel</label>
                    <input type="password" name="mdp_actuel" required autocomplete="current-password">
                </div>

                <div class="form-group">
                    <label>Nouveau mot de passe</label>
                    <input type="password" name="nouveau_mdp" minlength="6" required autocomplete="new-password">
                    <div class="hint">Minimum 6 caractères.</div>
                </div>

                <div class="form-group">
                    <label>Confirmation du nouveau mot de passe</label>
                    <input type="password" name="confirmation" minlength="6" required autocomplete="new-password">
                </div>

                <div class="buttons">
                    <button type="submit" name="changer_mdp" class="btn btn-success">💾 Changer le mot de passe</button>
                    <?php if ($force): ?>
                        <a href="logout.php" class="btn btn-cancel">🚪 Se déconnecter</a>
                    <?php else: ?>
                        <a href="afficheuser.php" class="btn btn-cancel">❌ Annuler</a>
                    <?php endif; ?>
                </div>
            </form>

        <?php endif; ?>
    </div>

</div>
</body>
</html>