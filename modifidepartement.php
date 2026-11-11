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

// Inclusion du système d'archivage centralisé
require_once 'functions_archivage.php';

/* ============================================================
   VÉRIFICATION DE L'IDENTIFIANT
============================================================ */
if(!isset($_GET['modif']) || empty($_GET['modif'])){
    header("Location: affichedepartement.php");
    exit();
}

$id = (int)$_GET['modif'];

/* ============================================================
   CHARGER LE DÉPARTEMENT
============================================================ */
$stmt = $bdd->prepare("SELECT * FROM departements WHERE id_dept = ?");
$stmt->execute([$id]);
$datadep = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$datadep){
    header("Location: affichedepartement.php");
    exit();
}

/* ============================================================
   VALEURS PAR DÉFAUT POUR LE FORMULAIRE
============================================================ */
$nomdep  = $datadep['nom_dept'];
$descrip = $datadep['description'];

/* ============================================================
   TRAITEMENT DU POST : UPDATE + ARCHIVAGE
============================================================ */
if(isset($_POST['modifie'])){
    $nomdep  = trim($_POST['nomdep']);
    $descrip = trim($_POST['descrip']);

    if(!empty($nomdep)){
        try {
            // ✅ Ancienne valeur AVANT le UPDATE
            $ancienne_valeur = getDonneesEnregistrement($bdd, 'departements', $id);

            $stmt = $bdd->prepare("UPDATE departements SET nom_dept = ?, description = ? WHERE id_dept = ?");
            $stmt->execute([$nomdep, $descrip, $id]);

            // ✅ Nouvelle valeur APRÈS le UPDATE
            $nouvelle_valeur = getDonneesEnregistrement($bdd, 'departements', $id);

            // ✅ Archivage centralisé
            $archive_id = archiverAction(
                $bdd,
                getUserIdEnSession($bdd),
                'UPDATE',
                'departements',
                $id,
                $ancienne_valeur,
                $nouvelle_valeur,
                'Modification du département'
            );

            // Debug temporaire (à retirer plus tard)
            error_log("[DEBUG modifidep] archiverAction retour = " . var_export($archive_id, true));

            echo "<script>
                alert('✅ Département modifié avec succès');
                window.location='affichedepartement.php';
            </script>";
            exit();
        } catch (Exception $e) {
            echo "<script>alert('❌ Erreur lors de la modification : " . addslashes($e->getMessage()) . "');</script>";
        }
    } else {
        echo "<script>alert('❌ Veuillez remplir tous les champs obligatoires.');</script>";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Modifier un Département</title>
<style>
*{ margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI',Arial,sans-serif; }
body{ background:#eef2f7; }
.container{ width:90%; max-width:750px; margin:40px auto; }
.card{ background:#fff; border-radius:18px; box-shadow:0 10px 25px rgba(0,0,0,.08); padding:35px; }
h2{ color:#003366; text-align:center; margin-bottom:30px; }
.form-group{ display:flex; flex-direction:column; margin-bottom:20px; }
label{ margin-bottom:8px; color:#003366; font-weight:bold; }
label .required{ color:#dc2626; }
input{ width:100%; padding:12px; border:1px solid #d1d5db; border-radius:10px; font-size:15px; outline:none; transition:border-color 0.3s; }
input:focus{ border-color:#0055aa; box-shadow:0 0 0 4px rgba(0,85,170,.10); }
.buttons{ display:flex; justify-content:center; gap:20px; margin-top:30px; }
.btn1{ background:#16a34a; color:white; border:none; padding:12px 30px; border-radius:10px; font-size:15px; font-weight:bold; cursor:pointer; transition:background 0.3s; }
.btn1:hover{ background:#15803d; }
.btn2{ background:#dc2626; color:white; border:none; padding:12px 30px; border-radius:10px; font-size:15px; font-weight:bold; cursor:pointer; transition:background 0.3s; text-decoration:none; text-align:center; display:inline-block; }
.btn2:hover{ background:#b91c1c; }
</style>
</head>
<body>
<?php include("menu.php"); ?>

<div class="container">
    <div class="card">
        <h2>✏️ Modifier un Département</h2>
        <form method="POST">
            <div class="form-group">
                <label>Nom du département <span class="required">*</span></label>
                <input type="text" name="nomdep" value="<?= htmlspecialchars($nomdep) ?>" required>
            </div>

            <div class="form-group">
                <label>Description</label>
                <input type="text" name="descrip" value="<?= htmlspecialchars($descrip) ?>">
            </div>

            <div class="buttons">
                <input type="submit" name="modifie" value="💾 Modifier" class="btn1">
                <a href="affichedepartement.php" class="btn2">❌ Annuler</a>
            </div>
        </form>
    </div>
</div>
</body>
</html>