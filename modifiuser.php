<?php
require_once __DIR__ . '/db.php';
// ============================================================
// modifiuser.php — Modification d'un utilisateur
// ✅ Corrections :
//    1. session_start() redondant supprimé
//    2. Rôles "Agent" et "Recteur" ajoutés au menu déroulant
//    3. Gestion complète des tables spécifiques (doyens, agents, comptables, lecteurs)
// ============================================================
require_once 'auth.php';
requireRole('Administrateur');

// ⚠️ PAS de session_start() ici : auth.php le fait déjà

try {
    $bdd = getDBConnection();
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header("Location: afficheuser.php");
    exit();
}

/* ============================================================
   RÉCUPÉRATION DE L'UTILISATEUR
============================================================ */
$stmt = $bdd->prepare("SELECT * FROM users WHERE id_user = ?");
$stmt->execute([$id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo "<script>alert('Utilisateur introuvable'); window.location='afficheuser.php';</script>";
    exit();
}

/* ============================================================
   DÉPARTEMENT ACTUEL (si Doyen)
============================================================ */
$stmtDoyen = $bdd->prepare("SELECT id_dept FROM doyens WHERE id_user = ?");
$stmtDoyen->execute([$id]);
$current_dept = $stmtDoyen->fetchColumn();

$departements = $bdd->query("SELECT id_dept, nom_dept, faculte FROM departements ORDER BY nom_dept")->fetchAll(PDO::FETCH_ASSOC);

/* ============================================================
   TRAITEMENT DU FORMULAIRE
============================================================ */
if (isset($_POST['modifier'])) {
    $nom       = trim($_POST['nom'] ?? '');
    $prenom    = trim($_POST['prenom'] ?? '');
    $username  = trim($_POST['username'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $sexe      = $_POST['sexe'] ?? null;
    $role      = trim($_POST['role'] ?? '');
    $statut    = trim($_POST['statut'] ?? 'Actif');
    $id_dept   = !empty($_POST['id_dept']) ? (int)$_POST['id_dept'] : null;

    $roles_valides = ['Administrateur','Recteur','Doyen','Professeur','Comptable','Agent','Etudiant','Lecteur'];

    if (empty($nom) || empty($prenom) || empty($username) || !in_array($role, $roles_valides, true)) {
        echo "<script>alert('Veuillez remplir correctement tous les champs obligatoires.');</script>";
    } else {
        try {
            $bdd->beginTransaction();

            /* ---------- 1. Mise à jour de la table users ---------- */
            $update = $bdd->prepare("
                UPDATE users 
                SET nom = ?, prenom = ?, username = ?, email = ?, telephone = ?, sexe = ?, role = ?, statut = ? 
                WHERE id_user = ?
            ");
            $update->execute([$nom, $prenom, $username, $email, $telephone, $sexe, $role, $statut, $id]);

            /* ---------- 2. Nettoyage des tables qui ne correspondent plus au rôle ---------- */
            if ($role !== 'Doyen') {
                $bdd->prepare("DELETE FROM doyens WHERE id_user = ?")->execute([$id]);
            }
            if ($role !== 'Agent') {
                $bdd->prepare("DELETE FROM agents WHERE id_user = ?")->execute([$id]);
            }
            if ($role !== 'Comptable') {
                $bdd->prepare("DELETE FROM comptables WHERE id_user = ?")->execute([$id]);
            }
            if ($role !== 'Lecteur') {
                $bdd->prepare("DELETE FROM lecteurs WHERE id_user = ?")->execute([$id]);
            }

            /* ---------- 3. Insertion / mise à jour selon le nouveau rôle ---------- */
            if ($role === 'Doyen' && $id_dept > 0) {
                // Doyen → doyens (id_dept obligatoire)
                $check = $bdd->prepare("SELECT id_doyen FROM doyens WHERE id_user = ?");
                $check->execute([$id]);
                if ($check->fetchColumn()) {
                    $bdd->prepare("UPDATE doyens SET id_dept = ? WHERE id_user = ?")->execute([$id_dept, $id]);
                } else {
                    $bdd->prepare("INSERT INTO doyens (id_user, id_dept) VALUES (?, ?)")->execute([$id, $id_dept]);
                }
            } elseif ($role === 'Agent') {
                // Agent → agents (date_embauche automatique)
                $check = $bdd->prepare("SELECT id_agent FROM agents WHERE id_user = ?");
                $check->execute([$id]);
                if (!$check->fetchColumn()) {
                    $bdd->prepare("INSERT INTO agents (id_user, date_embauche) VALUES (?, CURDATE())")->execute([$id]);
                }
            } elseif ($role === 'Comptable') {
                // Comptable → comptables
                $check = $bdd->prepare("SELECT id_comptable FROM comptables WHERE id_user = ?");
                $check->execute([$id]);
                if (!$check->fetchColumn()) {
                    $bdd->prepare("INSERT INTO comptables (id_user) VALUES (?)")->execute([$id]);
                }
            } elseif ($role === 'Lecteur') {
                // Lecteur → lecteurs
                $check = $bdd->prepare("SELECT id_lecteur FROM lecteurs WHERE id_user = ?");
                $check->execute([$id]);
                if (!$check->fetchColumn()) {
                    $bdd->prepare("INSERT INTO lecteurs (id_user) VALUES (?)")->execute([$id]);
                }
            }
            // Note : Professeur et Etudiant gérés par leurs pages dédiées
            // (ajouteprofesseur.php, ajouteetudiant.php) car ils requièrent des données supplémentaires.

            $bdd->commit();
            echo "<script>alert('✅ Utilisateur modifié avec succès.'); window.location='afficheuser.php';</script>";
            exit();

        } catch (Exception $e) {
            $bdd->rollBack();
            echo "<script>alert('❌ Erreur : " . addslashes($e->getMessage()) . "');</script>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Modifier utilisateur — UniVerse</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Arial, sans-serif; }
        body { background: #eef3f8; padding: 40px 20px; min-height: 100vh; }
        .container { width: 100%; max-width: 620px; margin: 0 auto; }
        .card { background: white; padding: 35px; border-radius: 18px; box-shadow: 0 10px 25px rgba(0,0,0,.08); border: 1px solid #e2e8f0; }
        h2 { text-align: center; color: #003366; margin-bottom: 8px; font-size: 22px; }
        .subtitle { text-align: center; color: #64748b; font-size: 13px; margin-bottom: 25px; padding-bottom: 20px; border-bottom: 1px solid #e2e8f0; }

        .form-group { display: flex; flex-direction: column; margin-bottom: 16px; }
        .form-group label { font-weight: 600; font-size: 13px; color: #334155; margin-bottom: 6px; }
        .form-group label .req { color: #dc2626; margin-left: 2px; }

        input, select {
            width: 100%;
            padding: 12px 14px;
            border-radius: 10px;
            border: 1px solid #cbd5e1;
            outline: none;
            font-size: 14px;
            background: #f8fafc;
            transition: all 0.2s;
            font-family: inherit;
        }
        input:focus, select:focus {
            border-color: #0055aa;
            box-shadow: 0 0 0 3px rgba(0,85,170,0.1);
            background: white;
        }

        .btn-row { display: flex; gap: 10px; margin-top: 24px; padding-top: 20px; border-top: 1px solid #e2e8f0; }
        .btn-save {
            flex: 1;
            background: #16a34a;
            color: white;
            padding: 13px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 700;
            font-size: 14px;
            transition: background 0.2s;
        }
        .btn-save:hover { background: #15803d; }
        .btn-cancel {
            flex: 1;
            background: #dc2626;
            color: white;
            padding: 13px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 700;
            font-size: 14px;
            text-align: center;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
        }
        .btn-cancel:hover { background: #b91c1c; }

        .dept-container { display: none; }

        @media (max-width: 600px) {
            .btn-row { flex-direction: column; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="card">
        <h2>✏️ Modifier l'utilisateur</h2>
        <p class="subtitle">Utilisateur #<?= htmlspecialchars($user['id_user']) ?> — <?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?></p>

        <form method="POST">
            <div class="form-group">
                <label>Nom <span class="req">*</span></label>
                <input type="text" name="nom" value="<?= htmlspecialchars($user['nom']) ?>" required>
            </div>

            <div class="form-group">
                <label>Prénom <span class="req">*</span></label>
                <input type="text" name="prenom" value="<?= htmlspecialchars($user['prenom']) ?>" required>
            </div>

            <div class="form-group">
                <label>Username <span class="req">*</span></label>
                <input type="text" name="username" value="<?= htmlspecialchars($user['username']) ?>" required>
            </div>

            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>">
            </div>

            <div class="form-group">
                <label>Téléphone</label>
                <input type="text" name="telephone" value="<?= htmlspecialchars($user['telephone']) ?>">
            </div>

            <div class="form-group">
                <label>Sexe</label>
                <select name="sexe">
                    <option value="">— Non renseigné —</option>
                    <option value="Masculin" <?= $user['sexe'] == 'Masculin' ? 'selected' : '' ?>>Masculin</option>
                    <option value="Feminin" <?= $user['sexe'] == 'Feminin' ? 'selected' : '' ?>>Féminin</option>
                </select>
            </div>

            <div class="form-group">
                <label>Rôle <span class="req">*</span></label>
                <select name="role" id="select-role" onchange="toggleDept()" required>
                    <option value="">— Choisir un rôle —</option>
                    <option value="Administrateur" <?= $user['role'] == 'Administrateur' ? 'selected' : '' ?>>👑 Administrateur</option>
                    <option value="Recteur"        <?= $user['role'] == 'Recteur'        ? 'selected' : '' ?>>🏛️ Recteur</option>
                    <option value="Doyen"          <?= $user['role'] == 'Doyen'          ? 'selected' : '' ?>>🎓 Doyen</option>
                    <option value="Professeur"     <?= $user['role'] == 'Professeur'     ? 'selected' : '' ?>>👨‍🏫 Professeur</option>
                    <option value="Comptable"      <?= $user['role'] == 'Comptable'      ? 'selected' : '' ?>>💰 Comptable</option>
                    <option value="Agent"          <?= $user['role'] == 'Agent'          ? 'selected' : '' ?>>📝 Agent d'inscription</option>
                    <option value="Etudiant"       <?= $user['role'] == 'Etudiant'       ? 'selected' : '' ?>>🎓 Étudiant</option>
                    <option value="Lecteur"        <?= $user['role'] == 'Lecteur'        ? 'selected' : '' ?>>📖 Lecteur</option>
                </select>
            </div>

            <!-- Champ Département (visible uniquement pour le rôle Doyen) -->
            <div class="form-group dept-container" id="dept-container">
                <label>Département de rattachement</label>
                <select name="id_dept">
                    <option value="">— Sélectionner un département —</option>
                    <?php foreach ($departements as $d): ?>
                        <option value="<?= (int)$d['id_dept'] ?>" <?= ($current_dept == $d['id_dept']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($d['nom_dept']) ?> (<?= htmlspecialchars($d['faculte'] ?: 'Sans faculté') ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Statut <span class="req">*</span></label>
                <select name="statut" required>
                    <option value="Actif"    <?= $user['statut'] == 'Actif'    ? 'selected' : '' ?>>✅ Actif</option>
                    <option value="Inactif"  <?= $user['statut'] == 'Inactif'  ? 'selected' : '' ?>>⏸️ Inactif</option>
                    <option value="Suspendu" <?= $user['statut'] == 'Suspendu' ? 'selected' : '' ?>>🚫 Suspendu</option>
                </select>
            </div>

            <div class="btn-row">
                <button type="submit" name="modifier" class="btn-save">💾 Enregistrer</button>
                <a href="afficheuser.php" class="btn-cancel">❌ Annuler</a>
            </div>
        </form>
    </div>
</div>

<script>
function toggleDept() {
    const role = document.getElementById('select-role').value;
    const container = document.getElementById('dept-container');
    if (role === 'Doyen') {
        container.style.display = 'flex';
        container.querySelector('select').setAttribute('required', 'required');
    } else {
        container.style.display = 'none';
        container.querySelector('select').removeAttribute('required');
    }
}
document.addEventListener('DOMContentLoaded', toggleDept);
</script>

</body>
</html>