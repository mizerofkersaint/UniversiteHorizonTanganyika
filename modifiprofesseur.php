<?php
require_once __DIR__ . '/db.php';
require_once 'auth.php';
requireRole(['Administrateur', 'Doyen']);
try {
    $bdd = getDBConnection();
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die('Erreur : ' . $e->getMessage());
}

include("menu.php");

$idProf = isset($_GET['id']) ? (int)$_GET['id'] : 0;

/* =========================================================
   RÉCUPÉRATION DU PROFESSEUR CONCERNÉ
========================================================= */
$stmt = $bdd->prepare("
    SELECT p.*, u.nom, u.prenom, u.sexe, u.telephone, u.email, u.email_auth 
    FROM professeurs p 
    INNER JOIN users u ON p.id_user = u.id_user 
    WHERE p.id_prof = ?
");
$stmt->execute([$idProf]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) {
    echo "<script>alert('Professeur introuvable.'); window.location='afficheprofesseur.php';</script>";
    exit();
}

/* =========================================================
   TRAITEMENT DE LA MODIFICATION
========================================================= */
if(isset($_POST['modifier'])){
    $nom        = trim($_POST['nom']);
    $prenom     = trim($_POST['prenom']);
    $sexe       = $_POST['sexe'];
    $specialite = trim($_POST['specialite']);
    $diplome    = trim($_POST['diplome']);
    $email_perso= trim($_POST['email']);
    $telephone  = trim($_POST['telephone']);

    try {
        $bdd->beginTransaction();

        // 1. Mise à jour de la table mère (users)
        $updateUser = $bdd->prepare("
            UPDATE users SET
                nom = ?, prenom = ?, sexe = ?, telephone = ?, email = ?
            WHERE id_user = ?
        ");
        $updateUser->execute([$nom, $prenom, $sexe, $telephone, $email_perso, $data['id_user']]);

        // 2. Mise à jour de la table fille (professeurs)
        $updateProf = $bdd->prepare("
            UPDATE professeurs SET
                specialite = ?, diplome = ?
            WHERE id_prof = ?
        ");
        $updateProf->execute([$specialite, $diplome, $idProf]);

        $bdd->commit();
        echo "<script>alert('Professeur modifié avec succès !'); window.location='afficheprofesseur.php';</script>";
        exit();
    } catch (Exception $e) {
        $bdd->rollBack();
        echo "<script>alert('Erreur lors de la modification : " . addslashes($e->getMessage()) . "');</script>";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Modifier un Professeur</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI',Arial,sans-serif;}
        body{background:#eef2f7;}
        .container{width:90%;max-width:700px;margin:40px auto;}
        .card{background:#fff;border-radius:18px;box-shadow:0 10px 25px rgba(0,0,0,.08);padding:35px;}
        h2{color:#003366;text-align:center;margin-bottom:30px;}
        .form-group{display:flex;flex-direction:column;margin-bottom:20px;}
        label{margin-bottom:8px;color:#003366;font-weight:bold;}
        input, select{padding:12px;border:1px solid #d1d5db;border-radius:10px;font-size:15px;}
        .buttons{display:flex;justify-content:center;gap:20px;margin-top:30px;}
        .btn-save{background:#16a34a;color:white;border:none;padding:12px 28px;border-radius:10px;cursor:pointer;font-weight:bold;}
        .btn-cancel{background:#dc2626;color:white;border:none;padding:12px 28px;border-radius:10px;cursor:pointer;font-weight:bold;text-decoration:none;text-align:center;}
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <h2>Modifier le Professeur</h2>
        <form method="POST">
            <div class="form-group">
                <label>Nom</label>
                <input type="text" name="nom" value="<?php echo htmlspecialchars($data['nom']); ?>" required>
            </div>
            <div class="form-group">
                <label>Prénom</label>
                <input type="text" name="prenom" value="<?php echo htmlspecialchars($data['prenom']); ?>" required>
            </div>
            <div class="form-group">
                <label>Sexe</label>
                <select name="sexe">
                    <option value="Masculin" <?php echo ($data['sexe'] == "Masculin") ? "selected" : ""; ?>>Masculin</option>
                    <option value="Feminin" <?php echo ($data['sexe'] == "Feminin") ? "selected" : ""; ?>>Féminin</option>
                </select>
            </div>
            <div class="form-group">
                <label>Spécialité</label>
                <input type="text" name="specialite" value="<?php echo htmlspecialchars($data['specialite']); ?>">
            </div>
            <div class="form-group">
                <label>Diplôme</label>
                <input type="text" name="diplome" value="<?php echo htmlspecialchars($data['diplome']); ?>">
            </div>
            <div class="form-group">
                <label>Email Personnel</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($data['email']); ?>">
            </div>
            <div class="form-group">
                <label>Email Académique (Généré par le système)</label>
                <input type="email" value="<?php echo htmlspecialchars($data['email_auth']); ?>" readonly style="background:#f3f4f6; color:#6b7280; cursor:not-allowed;">
            </div>
            <div class="form-group">
                <label>Téléphone</label>
                <input type="text" name="telephone" value="<?php echo htmlspecialchars($data['telephone']); ?>">
            </div>
            <div class="buttons">
                <input type="submit" name="modifier" value="Sauvegarder" class="btn-save">
                <a href="afficheprofesseur.php" class="btn-cancel">Annuler</a>
            </div>
        </form>
    </div>
</div>
</body>
</html>