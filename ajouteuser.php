<?php
require_once __DIR__ . '/db.php';
// ============================================================
// ajouteuser.php — Création d'un utilisateur + rattachement
// ============================================================

require_once 'auth.php';
requireRole('Administrateur');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $bdd = getDBConnection();
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    error_log("[ajouteuser] Erreur connexion : " . $e->getMessage());
    die("Erreur de connexion à la base de données.");
}

require_once 'functions_archivage.php';

$message_succes = '';
$message_erreur = '';

// Récupération des départements pour le select
$departements = $bdd->query("SELECT id_dept, nom_dept, faculte FROM departements ORDER BY faculte, nom_dept")->fetchAll(PDO::FETCH_ASSOC);

// ============================================================
// TRAITEMENT DU FORMULAIRE
// ============================================================
if (isset($_POST['ajouter_user'])) {

    $nom       = trim($_POST['nom'] ?? '');
    $prenom    = trim($_POST['prenom'] ?? '');
    $sexe      = trim($_POST['sexe'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $username  = trim($_POST['username'] ?? '');
    $password  = $_POST['mot_de_passe'] ?? '';
    $role      = trim($_POST['role'] ?? '');
    $statut    = trim($_POST['statut'] ?? 'Actif');
    $id_dept   = !empty($_POST['id_dept']) ? (int)$_POST['id_dept'] : null; // Pour Doyen/Prof

    // Validations
    if ($nom === '' || $prenom === '' || $username === '' || $password === '' || $role === '') {
        $message_erreur = "Veuillez remplir tous les champs obligatoires.";
    } elseif ($role === 'Doyen' && empty($id_dept)) {
        $message_erreur = "Un Doyen doit obligatoirement être rattaché à un département.";
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message_erreur = "Adresse e-mail invalide.";
    } elseif (strlen($password) < 6) {
        $message_erreur = "Le mot de passe doit contenir au moins 6 caractères.";
    } else {
        try {
            // Vérifier unicité username
            $checkUsername = $bdd->prepare("SELECT id_user FROM users WHERE username = ? LIMIT 1");
            $checkUsername->execute([$username]);
            if ($checkUsername->fetchColumn()) {
                throw new Exception("Ce nom d'utilisateur est déjà utilisé.");
            }

            // Vérifier unicité email
            if ($email !== '') {
                $checkEmail = $bdd->prepare("SELECT id_user FROM users WHERE email = ? LIMIT 1");
                $checkEmail->execute([$email]);
                if ($checkEmail->fetchColumn()) {
                    throw new Exception("Cette adresse e-mail est déjà utilisée.");
                }
            }

            $mot_de_passe_hache = password_hash($password, PASSWORD_BCRYPT);

            // ============================================================
            // TRANSACTION : Insertion dans users ET dans la table spécifique
            // ============================================================
            $bdd->beginTransaction();

            // 1. Insertion dans `users`
            $stmt = $bdd->prepare("
                INSERT INTO users (nom, prenom, sexe, telephone, email, username, mot_de_passe, role, statut)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $nom, $prenom, $sexe ?: null, $telephone ?: null, $email ?: null,
                $username, $mot_de_passe_hache, $role, $statut
            ]);

            $id_nouvel_utilisateur = (int) $bdd->lastInsertId();

            // 2. Insertion dans la table spécifique selon le rôle
            if ($role === 'Doyen') {
                $stmtDoyen = $bdd->prepare("INSERT INTO doyens (id_user, id_dept) VALUES (?, ?)");
                $stmtDoyen->execute([$id_nouvel_utilisateur, $id_dept]);
            } elseif ($role === 'Professeur') {
				$stmtProf = $bdd->prepare("INSERT INTO professeurs (id_user, specialite, diplome) VALUES (?, ?, ?)");
				$stmtProf->execute([$id_nouvel_utilisateur, 'Non renseignée', 'Non renseigné']);
			} elseif ($role === 'Comptable') {
				$stmtComptable = $bdd->prepare("INSERT INTO comptables (id_user) VALUES (?)");
				$stmtComptable->execute([$id_nouvel_utilisateur]);
			} elseif ($role === 'Agent') {
				$stmtAgent = $bdd->prepare("INSERT INTO agents (id_user, date_embauche) VALUES (?, CURDATE())");
				$stmtAgent->execute([$id_nouvel_utilisateur]);
			} elseif ($role === 'Etudiant') {
                // Un étudiant doit être dans une classe, on laisse ça pour la page dédiée
                // Mais on peut créer une ligne vide pour éviter les erreurs
                // Idéalement, la création d'étudiant se fait via la page Étudiants.
            }

            // 3. Archivage (sans le mot de passe)
            $donnees_pour_archive = getDonneesEnregistrement($bdd, 'users', $id_nouvel_utilisateur);
            if (is_array($donnees_pour_archive)) {
                unset($donnees_pour_archive['mot_de_passe']);
            }

            archiverAction(
                $bdd, getUserIdEnSession($bdd), 'INSERT', 'users', $id_nouvel_utilisateur,
                null, $donnees_pour_archive, 'Création d\'un utilisateur : ' . $username
            );

            $bdd->commit();
			// Envoi de l'email de bienvenue avec les identifiants
			require_once 'functions_email.php';
			if (!empty($email)) {
				$contenu = '<p>Bonjour <strong>' . htmlspecialchars($prenom . ' ' . $nom) . '</strong>,</p>
					<p>Votre compte sur <strong>UniVerse</strong> a été créé avec succès.</p>
					<p>Voici vos identifiants de connexion :</p>
					<ul>
						<li><strong>Email académique :</strong> ' . htmlspecialchars($email) . '</li>
						<li><strong>Mot de passe :</strong> ' . htmlspecialchars($password) . '</li>
					</ul>
					<p style="background:#fef3c7;padding:10px;border-left:4px solid #f59e0b;color:#92400e;">
						⚠️ <strong>Important :</strong> Lors de votre première connexion, vous serez obligé de changer votre mot de passe.
					</p>
					<p><a href="http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/login.php" 
						  style="background:#003366;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;display:inline-block;">
						  Se connecter à UniVerse
					   </a></p>';
				envoyerEmail($email, 'Bienvenue sur UniVerse - Vos identifiants', templateEmail('Bienvenue sur UniVerse', $contenu));
			}
            $message_succes = "Utilisateur ajouté avec succès.";

        } catch (Exception $e) {
            if ($bdd->inTransaction()) {
                $bdd->rollBack();
            }
            error_log("[ajouteuser] Erreur insertion : " . $e->getMessage());
            $message_erreur = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un utilisateur — UniVerse</title>
    <style>
        /* Le CSS reste identique à ce que vous aviez */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Arial, sans-serif; }
        body { background: #eef3f8; color: #1f2937; }
        .container { width: 95%; max-width: 900px; margin: 30px auto; }
        .page-header { background: linear-gradient(135deg, #003366, #0055aa); color: white; text-align: center; padding: 25px; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.2); margin-bottom: 20px; }
        .page-header h1 { font-size: 24px; }
        .card { background: #fff; border-radius: 18px; box-shadow: 0 10px 25px rgba(0,0,0,.07); padding: 30px; }
        .card h2 { color: #003366; margin-bottom: 20px; text-align: center; }
        .alert { padding: 14px 18px; border-radius: 10px; margin-bottom: 20px; font-weight: 600; font-size: 14px; }
        .alert-success { background: #d1fae5; color: #065f46; border-left: 4px solid #10b981; }
        .alert-error { background: #fee2e2; color: #991b1b; border-left: 4px solid #dc2626; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-group { display: flex; flex-direction: column; }
        .form-group label { font-weight: 600; font-size: 13px; color: #334155; margin-bottom: 6px; }
        .form-group label .required { color: #dc2626; margin-left: 2px; }
        input, select { padding: 12px 14px; border: 1px solid #d1d5db; border-radius: 10px; font-size: 14px; outline: none; transition: border-color 0.3s; background: #f8fafc; }
        input:focus, select:focus { border-color: #0055aa; box-shadow: 0 0 0 3px rgba(0,85,170,.10); background: #fff; }
        .full { grid-column: 1 / -1; }
        .buttons { display: flex; justify-content: center; gap: 15px; margin-top: 25px; padding-top: 20px; border-top: 1px solid #e2e8f0; }
        .btn { padding: 12px 28px; border: none; border-radius: 10px; font-size: 15px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: 8px; transition: all 0.2s; }
        .btn-success { background: #16a34a; color: white; }
        .btn-success:hover { background: #15803d; transform: translateY(-2px); }
        .btn-secondary { background: #e5e7eb; color: #111827; }
        .btn-secondary:hover { background: #d1d5db; transform: translateY(-2px); }
        .btn-cancel { background: #dc2626; color: white; }
        .btn-cancel:hover { background: #b91c1c; transform: translateY(-2px); }
        @media (max-width: 700px) { .form-grid { grid-template-columns: 1fr; } .buttons { flex-direction: column; } .btn { width: 100%; } }
    </style>
</head>
<body>
<?php include("menu.php"); ?>

<div class="container">

    <div class="page-header">
        <h1>👤 Ajouter un utilisateur</h1>
    </div>

    <?php if ($message_succes !== ''): ?>
        <div class="alert alert-success">✅ <?= htmlspecialchars($message_succes) ?></div>
    <?php endif; ?>
    <?php if ($message_erreur !== ''): ?>
        <div class="alert alert-error">❌ <?= htmlspecialchars($message_erreur) ?></div>
    <?php endif; ?>

    <div class="card">
        <h2>Informations du nouvel utilisateur</h2>

        <form method="POST" action="">
            <div class="form-grid">

                <div class="form-group">
                    <label>Nom <span class="required">*</span></label>
                    <input type="text" name="nom" value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label>Prénom <span class="required">*</span></label>
                    <input type="text" name="prenom" value="<?= htmlspecialchars($_POST['prenom'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label>Sexe</label>
                    <select name="sexe">
                        <option value="">— Non renseigné —</option>
                        <option value="Masculin" <?= (($_POST['sexe'] ?? '') === 'Masculin') ? 'selected' : '' ?>>Masculin</option>
                        <option value="Feminin"  <?= (($_POST['sexe'] ?? '') === 'Feminin')  ? 'selected' : '' ?>>Féminin</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Téléphone</label>
                    <input type="text" name="telephone" value="<?= htmlspecialchars($_POST['telephone'] ?? '') ?>" placeholder="+257 ...">
                </div>

                <div class="form-group">
                    <label>Adresse e-mail</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="exemple@domaine.com">
                </div>

                <div class="form-group">
                    <label>Nom d'utilisateur <span class="required">*</span></label>
                    <input type="text" name="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label>Mot de passe initial <span class="required">*</span></label>
                    <input type="password" name="mot_de_passe" minlength="6" required placeholder="6 caractères minimum">
                </div>

                <div class="form-group">
                    <label>Rôle <span class="required">*</span></label>
                    <select name="role" id="select-role" required onchange="toggleDeptField()">
                        <option value="">— Choisir un rôle —</option>
                        <option value="Administrateur" <?= (($_POST['role'] ?? '') === 'Administrateur') ? 'selected' : '' ?>>Administrateur</option>
                        <option value="Doyen"          <?= (($_POST['role'] ?? '') === 'Doyen')          ? 'selected' : '' ?>>Doyen</option>
                        <option value="Professeur"     <?= (($_POST['role'] ?? '') === 'Professeur')     ? 'selected' : '' ?>>Professeur</option>
                        <option value="Comptable"      <?= (($_POST['role'] ?? '') === 'Comptable')      ? 'selected' : '' ?>>Comptable</option>
						<option value="Agent"          <?= (($_POST['role'] ?? '') === 'Agent')          ? 'selected' : '' ?>>Agent d'inscription</option>
                        <option value="Etudiant"       <?= (($_POST['role'] ?? '') === 'Etudiant')       ? 'selected' : '' ?>>Étudiant</option>
                        <option value="Lecteur"        <?= (($_POST['role'] ?? '') === 'Lecteur')        ? 'selected' : '' ?>>Recteur</option>
                    </select>
                </div>

                <!-- CHAMP CONDITIONNEL : Rattachement au département (pour Doyen / Prof) -->
                <div class="form-group full" id="dept-container" style="display: none;">
                    <label>Département de rattachement <span class="required">*</span></label>
                    <select name="id_dept">
                        <option value="">— Sélectionner un département —</option>
                        <?php foreach ($departements as $d): ?>
                            <option value="<?= $d['id_dept'] ?>" <?= (($_POST['id_dept'] ?? '') == $d['id_dept']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($d['nom_dept']) ?> (<?= htmlspecialchars($d['faculte'] ?? 'Sans faculté') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span style="font-size:12px; color:#64748b; margin-top:4px;">Obligatoire pour les Doyens.</span>
                </div>

                <div class="form-group full">
                    <label>Statut <span class="required">*</span></label>
                    <select name="statut" required>
                        <option value="Actif"    <?= (($_POST['statut'] ?? 'Actif') === 'Actif')    ? 'selected' : '' ?>>Actif</option>
                        <option value="Inactif"  <?= (($_POST['statut'] ?? '') === 'Inactif')       ? 'selected' : '' ?>>Inactif</option>
                        <option value="Suspendu" <?= (($_POST['statut'] ?? '') === 'Suspendu')      ? 'selected' : '' ?>>Suspendu</option>
                    </select>
                </div>

            </div>

            <div class="buttons">
                <button type="submit" name="ajouter_user" class="btn btn-success">💾 Enregistrer</button>
                <button type="reset" class="btn btn-secondary">🔄 Réinitialiser</button>
                <a href="afficheuser.php" class="btn btn-cancel">❌ Annuler</a>
            </div>
        </form>
    </div>

</div>

<script>
// Afficher le champ département uniquement si le rôle est Doyen (ou Professeur)
function toggleDeptField() {
    const role = document.getElementById('select-role').value;
    const container = document.getElementById('dept-container');
    
    if (role === 'Doyen' || role === 'Professeur') {
        container.style.display = 'flex';
        // Si c'est un doyen, on rend le champ obligatoire via l'attribut required
        if(role === 'Doyen') {
            container.querySelector('select').setAttribute('required', 'required');
        } else {
            container.querySelector('select').removeAttribute('required');
        }
    } else {
        container.style.display = 'none';
        container.querySelector('select').removeAttribute('required');
    }
}

// Lancer au chargement pour garder l'état si erreur de formulaire
document.addEventListener('DOMContentLoaded', toggleDeptField);
</script>
</body>
</html>