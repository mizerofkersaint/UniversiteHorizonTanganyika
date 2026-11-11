<?php
require_once __DIR__ . '/db.php';
require_once 'auth.php';                    
requireRole(['Administrateur', 'Doyen', 'Agent']); 
try {
    $bdd = getDBConnection();
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// 🔗 MORCEAU 1 : connexion au système d'archivage centralisé
require_once 'functions_archivage.php';

/* ============================================================
   INSERTION AVEC REQUÊTE PRÉPARÉE + ARCHIVAGE CENTRALISÉ
============================================================ */
if(isset($_POST["depart"])){
    $nom     = trim($_POST["nomd"]);
    $faculte = trim($_POST["faculte"]);
    $desc    = trim($_POST["desc"]);

    if(!empty($nom) && !empty($faculte)){
        try {
            // Requête préparée sécurisée
            $stmt = $bdd->prepare("INSERT INTO departements(nom_dept, faculte, description) VALUES(?, ?, ?)");
            $stmt->execute([$nom, $faculte, $desc]);

            // 🔗 MORCEAU 2 : archivage centralisé APRÈS la réussite de l'INSERT
            $id_nouveau_departement = (int) $bdd->lastInsertId();
            $donnees_nouveau = getDonneesEnregistrement($bdd, 'departements', $id_nouveau_departement);
            archiverAction(
                $bdd,
                getUserIdEnSession($bdd),
                'INSERT',
                'departements',
                $id_nouveau_departement,
                null,
                $donnees_nouveau,
                'Création du département'
            );

            echo "<script>
                alert('Département ajouté avec succès !');
                window.location='affichedepartement.php';
            </script>";
            exit();
        } catch (Exception $e) {
            echo "<script>alert('Erreur lors de l'ajout : " . addslashes($e->getMessage()) . "');</script>";
        }
    } else {
        echo "<script>alert('Veuillez remplir tous les champs obligatoires.');</script>";
    }
}
?>

<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Ajouter un Département</title>
<style>
body{
    background:#eef3f8;
    font-family:Arial, sans-serif;
}
.content{
    display:flex;
    justify-content:center;
    margin-top:40px;
}
table{
    background:white;
    padding:30px;
    border-radius:15px;
    box-shadow:0 10px 25px rgba(0,0,0,.08);
    width:500px;
}
td{
    padding:10px;
}
input, select{
    width:100%;
    padding:10px;
    border:1px solid #d1d5db;
    border-radius:8px;
    outline:none;
    transition:border-color 0.3s;
    font-size:14px;
}
input:focus, select:focus{
    border-color:#0055aa;
    box-shadow:0 0 0 3px rgba(0,85,170,.10);
}
h2{
    text-align:center;
    color:#003366;
    margin-bottom:10px;
}
.btn1{
    background:#16a34a;
    color:white;
    border:none;
    padding:12px 25px;
    border-radius:8px;
    cursor:pointer;
    font-weight:bold;
    transition:background 0.3s;
}
.btn1:hover{
    background:#15803d;
}
.btn2{
    background:#dc2626;
    color:white;
    border:none;
    padding:12px 25px;
    border-radius:8px;
    cursor:pointer;
    font-weight:bold;
    transition:background 0.3s;
}
.btn2:hover{
    background:#b91c1c;
}
</style>
</head>

<body>
<?php include("menu.php"); ?>

<div class="content">
<form action="ajoutedepartement.php" method="POST">
<table>
    <tr>
        <td colspan="2">
            <h2>📚 Ajouter un Département</h2>
        </td>
    </tr>
    <tr>
        <td><label>Nom Département <span style="color:red;">*</span></label></td>
        <td><input type="text" name="nomd" required placeholder="Ex: INFORMATIQUE"></td>
    </tr>
    <tr>
        <td><label>Faculté <span style="color:red;">*</span></label></td>
        <td>
            <select name="faculte" required>
                <option value="">-- Sélectionner une faculté --</option>
                <option value="Faculté des Sciences et Technologies">Faculté des Sciences et Technologies</option>
                <option value="Faculté des Sciences de Gestion">Faculté des Sciences de Gestion</option>
                <option value="Faculté de Droit">Faculté de Droit</option>
                <option value="Faculté de Médecine">Faculté de Médecine</option>
                <option value="Faculté des Sciences et Ingénierie">Faculté des Sciences et Ingénierie</option>
                <option value="Faculté des Sciences Politiques">Faculté des Sciences Politiques</option>
                <option value="Faculté des Lettres et Sciences Humaines">Faculté des Lettres et Sciences Humaines</option>
            </select>
        </td>
    </tr>
    <tr>
        <td><label>Description</label></td>
        <td><input type="text" name="desc" placeholder="Description du département"></td>
    </tr>
    <tr>
        <td colspan="2" align="center" style="padding-top:20px;">
            <input type="submit" value="💾 Enregistrer" name="depart" class="btn1">
            <input type="reset" value="❌ Annuler" class="btn2">
        </td>
    </tr>
</table>
</form>
</div>
</body>
</html>