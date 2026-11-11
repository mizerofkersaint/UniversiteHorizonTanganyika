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

if (isset($_POST['ajouter_prof'])) {
    $nom        = trim($_POST['nom']);
    $prenom     = trim($_POST['prenom']);
    $sexe       = $_POST['sexe'];
    $specialite = trim($_POST['specialite']);
    $diplome    = trim($_POST['diplome']);
    $email_perso= trim($_POST['email']);
    $telephone  = trim($_POST['telephone']);

    if (!empty($nom) && !empty($prenom)) {
        try {
            $bdd->beginTransaction();

            // 1. Préparation des identifiants par défaut pour la table users
            // On crée un nom d'utilisateur simple automatique, ex: p.dupont
            $username = strtolower(substr($prenom, 0, 1) . '.' . str_replace(' ', '', $nom));
            $password_par_defaut = password_hash("123456", PASSWORD_BCRYPT); 

            // 2. Insertion dans la table mère 'users'
            $insertUser = $bdd->prepare("
                INSERT INTO users (nom, prenom, sexe, telephone, email, username, mot_de_passe, role, statut)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'Professeur', 'Actif')
            ");
            $insertUser->execute([$nom, $prenom, $sexe, $telephone, $email_perso, $username, $password_par_defaut]);
            
            // 3. Récupération de l'id_user qui vient d'être créé
            $id_user_genere = $bdd->lastInsertId();

            // 4. Insertion dans la table fille 'professeurs'
            $insertProf = $bdd->prepare("
                INSERT INTO professeurs (id_user, specialite, diplome)
                VALUES (?, ?, ?)
            ");
            $insertProf->execute([$id_user_genere, $specialite, $diplome]);
            
            $bdd->commit();
            echo "<script>alert('Professeur ajouté avec succès ! L\'email académique a été généré automatiquement.'); window.location='afficheprofesseur.php';</script>";
        } catch (Exception $e) {
            $bdd->rollBack();
            echo "<script>alert('Erreur lors de l\'ajout : " . addslashes($e->getMessage()) . "');</script>";
        }
    } else {
        echo "<script>alert('Veuillez remplir au moins le nom et le prénom.');</script>";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Ajouter un Professeur</title>
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
        .btn-save{background:#003366;color:white;border:none;padding:12px 28px;border-radius:10px;cursor:pointer;font-weight:bold;}
        .btn-cancel{background:#dc2626;color:white;border:none;padding:12px 28px;border-radius:10px;cursor:pointer;font-weight:bold;text-decoration:none;text-align:center;}
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <h2>Ajouter un Nouveau Professeur</h2>
        <form method="POST">
            <div class="form-group">
                <label>Nom *</label>
                <input type="text" name="nom" required placeholder="Ex: KERSAINT">
            </div>
            <div class="form-group">
                <label>Prénom *</label>
                <input type="text" name="prenom" required placeholder="Ex: Mizero Francis">
            </div>
            <div class="form-group">
                <label>Sexe</label>
                <select name="sexe">
                    <option value="Masculin">Masculin</option>
                    <option value="Feminin">Féminin</option>
                </select>
            </div>
            <div class="form-group">
                <label>Spécialité</label>
                <input type="text" name="specialite" placeholder="Ex: Développement Web / Algorithmique">
            </div>
            <div class="form-group">
                <label>Diplôme</label>
                <input type="text" name="diplome" placeholder="Ex: Master / Doctorat">
            </div>
            <div class="form-group">
                <label>Email Personnel</label>
                <input type="email" name="email" placeholder="Ex: perso@gmail.com">
            </div>
            <div class="form-group">
                <label>Téléphone</label>
                <input type="text" name="telephone" placeholder="Ex: +257 ...">
            </div>
            <div class="buttons">
                <input type="submit" name="ajouter_prof" value="Enregistrer" class="btn-save">
                <a href="afficheprofesseur.php" class="btn-cancel">Annuler</a>
            </div>
        </form>
    </div>
</div>
</body>
</html>