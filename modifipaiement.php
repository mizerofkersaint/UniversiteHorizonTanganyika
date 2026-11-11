<?php
require_once 'auth.php';
requireRole(['Comptable', 'Administrateur']);

/* ==========================
   RECUPERER LE PAIEMENT (JOINTURE SUR USERS INCLUS POUR EN SAVOIR PLUS)
========================== */
if(!isset($_GET['id']) || empty($_GET['id'])){
    header("location:affichepaiement.php");
    exit();
}

$id = (int)$_GET['id'];

// Jointure avec les tables users, classes et departements pour récupérer les informations
$req = $bdd->prepare("
    SELECT p.*, u.nom, u.prenom, d.nom_dept, d.faculte, cl.niveau
    FROM paiements p
    INNER JOIN etudiants e ON p.id_etudiant = e.id_etudiant
    INNER JOIN users u ON e.id_user = u.id_user
    INNER JOIN classes cl ON e.id_classe = cl.id_classe
    INNER JOIN departements d ON cl.id_dept = d.id_dept
    WHERE p.id_paiement = ?
");
$req->execute([$id]);
$paiement = $req->fetch(PDO::FETCH_ASSOC);

if(!$paiement){
    header("location:affichepaiement.php");
    exit();
}

/* ==========================
   TRAITEMENT DE LA MISE À JOUR (AVEC TRANSACTION)
========================== */
$message = "";
$messageType = "";

if(isset($_POST['modifier'])) {
    $montant   = trim($_POST['montant']);
    $date_paye = trim($_POST['date_paye']);
    $bordereau = trim($_POST['bordereau']);
    $bank      = trim($_POST['bank']);
    $tranche   = trim($_POST['tranche']);
    $penalte   = trim($_POST['penalte']);

    if(empty($montant) || empty($date_paye) || empty($bordereau) || empty($bank) || empty($tranche)) {
        $message = "Veuillez remplir tous les champs obligatoires.";
        $messageType = "error";
    } else {
        if ($tranche != "Penalte") { $penalte = null; }
        
        try {
            $bdd->beginTransaction();

            $update = $bdd->prepare("
                UPDATE paiements 
                SET montant = ?, date_paiement = ?, bordereau = ?, banque = ?, tranche = ?, motif = ?
                WHERE id_paiement = ?
            ");
            $update->execute([$montant, $date_paye, $bordereau, $bank, $tranche, $penalte, $id]);
            
            $bdd->commit();
            
            echo "<script>
                alert('Paiement mis à jour avec succès !');
                window.location='affichepaiement.php';
            </script>";
            exit();
        } catch(PDOException $e) {
            if ($bdd->inTransaction()) {
                $bdd->rollBack();
            }
            $message = "Erreur lors de la modification : " . $e->getMessage();
            $messageType = "error";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Modifier Paiement</title>
<style>
*{ margin:0; padding:0; box-sizing:border-box; font-family:Arial, sans-serif; }
body{ background:#f4f6f9; }
.container{ width:100%; min-height:100vh; display:flex; justify-content:center; align-items:flex-start; padding:40px 20px; }
.form-card{ width:1000px; background:white; border-radius:14px; box-shadow:0 8px 25px rgba(0,0,0,.12); padding:30px; }
.form-title{ text-align:center; color:#003366; margin-bottom:25px; font-size:28px; font-weight:bold; }
.message{ padding:14px; border-radius:8px; margin-bottom:20px; font-weight:bold; }
.error{ background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; }
form{ display:grid; grid-template-columns:1fr 1fr; gap:20px; }
.form-group{ display:flex; flex-direction:column; }
.full{ grid-column:1 / 3; }
label{ font-weight:bold; color:#333; margin-bottom:7px; }
input[type="text"], input[type="number"], input[type="date"], select{ width:100%; height:46px; padding:10px 12px; border:1px solid #ccc; border-radius:8px; outline:none; font-size:15px; transition:0.2s; background:#fff; }
input:focus, select:focus{ border-color:#00509e; box-shadow:0 0 0 3px rgba(0,80,158,.12); }
input:disabled{ background:#f1f1f1; cursor:not-allowed; }
.info-student{ background:#f8fafc; padding:12px; border-radius:8px; border:1px solid #e2e8f0; }
.info-student strong{ color:#003366; }
.faculte-badge{ display:inline-block; padding:2px 10px; border-radius:12px; background:#eff6ff; color:#1e40af; font-weight:600; font-size:12px; }
.btns{ grid-column:1 / 3; display:flex; justify-content:space-between; gap:15px; margin-top:10px; }
button{ flex:1; height:46px; border:none; border-radius:8px; color:white; cursor:pointer; font-weight:bold; font-size:15px; transition:0.2s; }
.btn-modifier{ background:#28a745; }
.btn-modifier:hover{ background:#218838; }
.btn-annuler{ background:#dc3545; text-decoration:none; display:flex; align-items:center; justify-content:center; color:white; }
.btn-annuler:hover{ background:#c82333; }
@media(max-width:900px){ .form-card{ width:100%; } form{ grid-template-columns:1fr; } .full, .btns{ grid-column:1; } .btns{ flex-direction:column; } }
</style>
</head>
<body>
<div class="container">
    <div class="form-card">
        <h2 class="form-title">MODIFIER PAIEMENT</h2>
        
        <?php if(!empty($message)) { ?>
            <div class="message <?= $messageType ?>"><?= $message ?></div>
        <?php } ?>

        <div class="info-student full">
            <strong>Étudiant :</strong> <?= htmlspecialchars($paiement['nom'] . ' ' . $paiement['prenom']) ?><br>
            <strong>Département :</strong> <?= htmlspecialchars($paiement['nom_dept']) ?><br>
            <strong>Faculté :</strong> <span class="faculte-badge"><?= htmlspecialchars($paiement['faculte'] ?: 'Non assignée') ?></span><br>
            <strong>Classe :</strong> <?= htmlspecialchars($paiement['niveau']) ?>
        </div>

        <form action="" method="POST">
            <div class="form-group full">
                <label for="montant">Montant</label>
                <input name="montant" id="montant" type="number" step="0.01" placeholder="Montant payé" value="<?= htmlspecialchars($paiement['montant']) ?>">
            </div>

            <div class="form-group">
                <label for="date_paye">Date de paiement</label>
                <input name="date_paye" id="date_paye" type="date" value="<?= htmlspecialchars($paiement['date_paiement']) ?>">
            </div>

            <div class="form-group">
                <label for="bordereau">Numéro de bordereau</label>
                <input name="bordereau" id="bordereau" type="text" placeholder="Numéro de bordereau" value="<?= htmlspecialchars($paiement['bordereau']) ?>">
            </div>

            <div class="form-group">
                <label for="bank">Banque utilisée</label>
                <select name="bank" id="bank">
                    <option value="IBBM+" <?= ($paiement['banque'] == 'IBBM+') ? 'selected' : '' ?>>IBBM+</option>
                    <option value="CRDB"  <?= ($paiement['banque'] == 'CRDB') ? 'selected' : '' ?>>CRDB</option>
                    <option value="BCB"   <?= ($paiement['banque'] == 'BCB') ? 'selected' : '' ?>>BCB</option>
                    <option value="KCB"   <?= ($paiement['banque'] == 'KCB') ? 'selected' : '' ?>>KCB</option>
                    <option value="BCEAB" <?= ($paiement['banque'] == 'BCEAB') ? 'selected' : '' ?>>BCEAB</option>
                </select>
            </div>

            <div class="form-group">
                <label for="tranche">Tranche</label>
                <select name="tranche" id="tranche">
                    <option value="Inscription" <?= ($paiement['tranche'] == 'Inscription') ? 'selected' : '' ?>>Inscription</option>
                    <option value="Tranche 1" <?= ($paiement['tranche'] == 'Tranche 1') ? 'selected' : '' ?>>Tranche 1</option>
                    <option value="Tranche 2" <?= ($paiement['tranche'] == 'Tranche 2') ? 'selected' : '' ?>>Tranche 2</option>
                    <option value="Tranche 3" <?= ($paiement['tranche'] == 'Tranche 3') ? 'selected' : '' ?>>Tranche 3</option>
                    <option value="Tranche 4" <?= ($paiement['tranche'] == 'Tranche 4') ? 'selected' : '' ?>>Tranche 4</option>
                    <option value="Penalte" <?= ($paiement['tranche'] == 'Penalte') ? 'selected' : '' ?>>Pénalité</option>
                </select>
            </div>

            <div class="form-group">
                <label for="penalte">Motif de pénalité</label>
                <input name="penalte" id="penalte" type="text" placeholder="Motif de pénalité" value="<?= htmlspecialchars($paiement['motif'] ?? '') ?>">
            </div>

            <div class="btns">
                <button name="modifier" class="btn-modifier" type="submit">💾 Enregistrer les modifications</button>
                <a href="affichepaiement.php" class="btn-annuler">❌ Annuler</a>
            </div>
        </form>
    </div>
</div>
<script>
const tranche = document.getElementById("tranche");
const penalte = document.getElementById("penalte");
function gererPenalite(){
    if(tranche.value === "Penalte"){
        penalte.disabled = false;
    }else{
        penalte.disabled = true;
        penalte.value = "";
    }
}
tranche.addEventListener("change", gererPenalite);
gererPenalite();
</script>
</body>
</html>