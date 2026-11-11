<?php
require_once __DIR__ . '/db.php';
require_once 'auth.php';
requireRole(['Administrateur', 'Agent']);

include("menu.php");

try {
    $bdd = getDBConnection();
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

$erreur = "";
$nom        = isset($_POST["nom"]) ? trim($_POST["nom"]) : "";
$prenom     = isset($_POST["prenom"]) ? trim($_POST["prenom"]) : "";
$dn         = isset($_POST["dn"]) ? $_POST["dn"] : "";
$sexe       = isset($_POST["sexe"]) ? $_POST["sexe"] : "";
$em_perso   = isset($_POST["em_perso"]) ? trim($_POST["em_perso"]) : "";
$tel        = isset($_POST["tel"]) ? trim($_POST["tel"]) : "";
$titre      = isset($_POST["titre"]) ? $_POST["titre"] : "";
$id_dept    = isset($_POST["dept"]) ? (int)$_POST["dept"] : 0;
$id_classe  = isset($_POST["cl"]) ? (int)$_POST["cl"] : 0;
$annee_acad = isset($_POST["annee_acad"]) ? trim($_POST["annee_acad"]) : "";

$departements = $bdd->query("SELECT id_dept, nom_dept, faculte FROM departements ORDER BY nom_dept");

if (isset($_POST["Valid"])) {
    if (!empty($nom) && !empty($prenom) && !empty($em_perso) && $id_classe > 0 && !empty($annee_acad)) {
        try {
            $bdd->beginTransaction();
            $username = strtolower(substr($prenom, 0, 1) . '.' . str_replace(' ', '', $nom));
            $passwordParDefaut = password_hash('123456', PASSWORD_BCRYPT);

            $insertUser = $bdd->prepare("INSERT INTO users (nom, prenom, sexe, telephone, email, username, mot_de_passe, role, statut) VALUES (?, ?, ?, ?, ?, ?, ?, 'Etudiant', 'Actif')");
            $insertUser->execute([$nom, $prenom, $sexe, $tel, $em_perso, $username, $passwordParDefaut]);
            $id_user_cree = $bdd->lastInsertId();

            $insertEtudiant = $bdd->prepare("INSERT INTO etudiants (id_user, id_classe, titre, date_naissance, annee_academique_entree) VALUES (?, ?, ?, ?, ?)");
            $insertEtudiant->execute([$id_user_cree, $id_classe, $titre, !empty($dn) ? $dn : null, $annee_acad]);

            $recupInfosTrigger = $bdd->prepare("SELECT u.email_auth, e.matricule FROM users u JOIN etudiants e ON u.id_user = e.id_user WHERE u.id_user = ?");
            $recupInfosTrigger->execute([$id_user_cree]);
            $infoGenerees = $recupInfosTrigger->fetch(PDO::FETCH_ASSOC);
            
            $emailAuthGenere = $infoGenerees['email_auth'] ?? 'Généré par le système';
            $matriculeGenere = $infoGenerees['matricule'] ?? 'Généré par le système';

            $bdd->commit();

            echo "<script>
            alert('Étudiant créé avec succès !\\n\\nMatricule Officiel : " . $matriculeGenere . "\\nEmail d\'Authentification : " . $emailAuthGenere . "\\nMot de passe par défaut : 123456');
            window.location='AffichageEtudiants.php';
            </script>";
            exit();

        } catch (Exception $e) {
            if ($bdd->inTransaction()) { $bdd->rollBack(); }
            $erreur = "Erreur lors de l'enregistrement : " . $e->getMessage();
        }
    } else {
        $erreur = "Veuillez remplir tous les champs obligatoires (*) et sélectionner une classe.";
    }
}

$classes = [];
if ($id_dept > 0) {
    $stmt = $bdd->prepare("SELECT id_classe, niveau FROM classes WHERE id_dept = ? ORDER BY niveau");
    $stmt->execute([$id_dept]);
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$faculteChoisie = '';
if ($id_dept > 0) {
    $stmt = $bdd->prepare("SELECT faculte FROM departements WHERE id_dept = ?");
    $stmt->execute([$id_dept]);
    $faculteChoisie = $stmt->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Ajouter Étudiant - UniVerse</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Arial, sans-serif; }
        body { background: linear-gradient(135deg, #eef3f8, #f4f6f9); min-height: 100vh; }
        .container { display: flex; justify-content: center; align-items: center; padding: 40px 20px; }
        .form-card { background: white; width: 100%; max-width: 850px; padding: 35px; border-radius: 15px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
        h2 { text-align: center; color: #0f172a; margin-bottom: 25px; font-size: 24px; border-bottom: 2px solid #e2e8f0; padding-bottom: 15px; }
        .alert-error { background: #ffeeec; color: #dc2626; padding: 12px; border-radius: 8px; margin-bottom: 15px; font-weight: bold; font-size: 14px; }
        form { display: flex; flex-direction: column; gap: 20px; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .row { display: flex; flex-direction: column; }
        label { font-weight: bold; font-size: 14px; color: #334155; margin-bottom: 8px; }
        input, select { padding: 12px; border-radius: 8px; border: 1px solid #cbd5e1; outline: none; font-size: 15px; width: 100%; transition: border-color 0.2s; color: #334155; height: 46px; }
        input:focus, select:focus { border-color: #3b82f6; }
        .btns { display: flex; justify-content: flex-end; gap: 15px; margin-top: 10px; border-top: 1px solid #e2e8f0; padding-top: 20px; }
        .btn1 { background: #10b981; color: white; border: none; padding: 12px 25px; border-radius: 8px; cursor: pointer; font-weight: bold; font-size: 15px; }
        .btn2 { background: #ef4444; color: white; border: none; padding: 12px 25px; border-radius: 8px; cursor: pointer; font-weight: bold; font-size: 15px; text-align: center; text-decoration: none; }
        @media(max-width:650px){ .form-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="container">
    <div class="form-card">
        <h2>➕ Ajouter un Étudiant</h2>
        <?php if(!empty($erreur)): ?><div class="alert-error">❌ <?= htmlspecialchars($erreur) ?></div><?php endif; ?>
        <form method="POST" action="ajouteetudiant.php">
            <div class="form-grid">
                <div class="row"><label>Nom <span style="color:red;">*</span></label><input type="text" name="nom" value="<?= htmlspecialchars($nom) ?>" required></div>
                <div class="row"><label>Prénom <span style="color:red;">*</span></label><input type="text" name="prenom" value="<?= htmlspecialchars($prenom) ?>" required></div>
                <div class="row"><label>Date de naissance</label><input type="date" name="dn" value="<?= htmlspecialchars($dn) ?>"></div>
                <div class="row"><label>Sexe <span style="color:red;">*</span></label>
                    <select name="sexe" required>
                        <option value="">Choisir le sexe</option>
                        <option value="Masculin" <?= $sexe == 'Masculin' ? 'selected' : '' ?>>Masculin</option>
                        <option value="Feminin" <?= $sexe == 'Feminin' ? 'selected' : '' ?>>Féminin</option>
                    </select>
                </div>
                <div class="row"><label>Email Personnel <span style="color:red;">*</span></label><input type="email" name="em_perso" value="<?= htmlspecialchars($em_perso) ?>" required></div>
                <div class="row"><label>Téléphone</label><input type="text" name="tel" value="<?= htmlspecialchars($tel) ?>"></div>
                <div class="row"><label>Titre <span style="color:red;">*</span></label>
                    <select name="titre" required>
                        <option value="">Choisir le statut</option>
                        <option value="Nouveau" <?= $titre == 'Nouveau' ? 'selected' : '' ?>>Nouveau</option>
                        <option value="Redoublant" <?= $titre == 'Redoublant' ? 'selected' : '' ?>>Redoublant</option>
                    </select>
                </div>
                <div class="row"><label>Département <span style="color:red;">*</span></label>
                    <select name="dept" id="select-dept" required onchange="this.form.submit()">
                        <option value="0">Sélectionner un département</option>
                        <?php while($rowD = $departements->fetch(PDO::FETCH_ASSOC)): ?>
                            <option value="<?= $rowD['id_dept'] ?>" <?= ($id_dept == $rowD['id_dept']) ? 'selected' : '' ?>><?= htmlspecialchars($rowD['nom_dept']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="row"><label>Classe <span style="color:red;">*</span></label>
                    <select name="cl" required>
                        <?php if($id_dept === 0): ?>
                            <option value="0">Sélectionnez d'abord un département</option>
                        <?php else: ?>
                            <option value="0">Sélectionner la classe</option>
                            <?php foreach($classes as $rowC): ?>
                                <option value="<?= $rowC['id_classe'] ?>" <?= ($id_classe == $rowC['id_classe']) ? 'selected' : '' ?>><?= htmlspecialchars($rowC['niveau']) ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="row"><label>Année Académique <span style="color:red;">*</span></label><input type="text" name="annee_acad" value="<?= htmlspecialchars($annee_acad) ?>" placeholder="Ex: 2025-2026" required></div>
                <div class="row"><label>Faculté (lecture seule)</label><input type="text" value="<?= htmlspecialchars($faculteChoisie ?: 'Non assignée') ?>" readonly style="background:#f3f4f6;"></div>
            </div>
            <div class="btns">
                <a href="AffichageEtudiants.php" class="btn2">❌ Annuler</a>
                <input type="submit" value="💾 Enregistrer" name="Valid" class="btn1">
            </div>
        </form>
    </div>
</div>
</body>
</html>