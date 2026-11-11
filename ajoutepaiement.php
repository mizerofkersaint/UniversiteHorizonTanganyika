<?php
require_once 'auth.php';
requireRole(['Comptable', 'Administrateur']);

$message = "";
$messageType = "";

$departementChoisi = isset($_POST['departement']) ? $_POST['departement'] : '';
$classeChoisie      = isset($_POST['classe']) ? $_POST['classe'] : '';

// Récupération de la faculté du département choisi
$faculteChoisie = '';
if(!empty($departementChoisi)){
    $faculteReq = $bdd->prepare("SELECT faculte FROM departements WHERE id_dept = ?");
    $faculteReq->execute([$departementChoisi]);
    $faculteChoisie = $faculteReq->fetchColumn();
}

/* =========================
   1) CHARGER LES DEPARTEMENTS AVEC FACULTE
========================= */
$reqDepartements = $bdd->query("SELECT id_dept, nom_dept, faculte FROM departements ORDER BY nom_dept ASC");
$departements = $reqDepartements->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   2) CHARGER LES CLASSES
========================= */
$classes = [];
if (!empty($departementChoisi)) {
    $stmtClasse = $bdd->prepare("SELECT id_classe, niveau FROM classes WHERE id_dept = ? ORDER BY niveau ASC");
    $stmtClasse->execute([$departementChoisi]);
    $classes = $stmtClasse->fetchAll(PDO::FETCH_ASSOC);
}

/* =========================
   3) CHARGER LES ETUDIANTS (JOINTURE SUR USERS POUR NOM/PRENOM)
========================= */
$etudiants = [];
if (!empty($classeChoisie)) {
    $stmtEtud = $bdd->prepare("
        SELECT e.id_etudiant, u.nom, u.prenom 
        FROM etudiants e
        INNER JOIN users u ON e.id_user = u.id_user
        WHERE e.id_classe = ? 
        ORDER BY u.nom ASC, u.prenom ASC
    ");
    $stmtEtud->execute([$classeChoisie]);
    $etudiants = $stmtEtud->fetchAll(PDO::FETCH_ASSOC);
}

/* =========================
   4) INSERTION DU PAIEMENT (AVEC TRANSACTION)
========================= */
if (isset($_POST["ajouter"])) {
    $departement = trim($_POST["departement"] ?? "");
    $classe      = trim($_POST["classe"] ?? "");
    $etudiant    = trim($_POST["etudiant"] ?? "");
    $montant     = trim($_POST["montant"] ?? "");
    $date_paye   = trim($_POST["date_paye"] ?? "");
    $bordereau   = trim($_POST["bordereau"] ?? "");
    $bank        = trim($_POST["bank"] ?? "");
    $tranche     = trim($_POST["tranche"] ?? "");
    $penalte     = trim($_POST["penalte"] ?? "");

    if (empty($departement) || empty($classe) || empty($etudiant) || empty($montant) || empty($date_paye) || empty($bordereau) || empty($bank) || empty($tranche)) {
        $message = "Veuillez remplir tous les champs obligatoires.";
        $messageType = "error";
    } elseif (!is_numeric($montant) || $montant <= 0) {
        $message = "Le montant doit être un nombre supérieur à 0.";
        $messageType = "error";
    } elseif ($tranche == "Penalte" && empty($penalte)) {
        $message = "Veuillez saisir le motif de pénalité.";
        $messageType = "error";
    } else {
        if ($tranche != "Penalte") {
            $penalte = null;
        }

        try {
            $bdd->beginTransaction();

            $insertPaiement = $bdd->prepare("
                INSERT INTO paiements(id_etudiant, tranche, montant, date_paiement, bordereau, banque, motif)
                VALUES(?, ?, ?, ?, ?, ?, ?)
            ");
            $insertPaiement->execute([$etudiant, $tranche, $montant, $date_paye, $bordereau, $bank, $penalte]);

            $bdd->commit();

            $message = "Paiement ajouté avec succès.";
            $messageType = "success";

            $departementChoisi = '';
            $classeChoisie = '';
            $classes = [];
            $etudiants = [];
            $_POST = [];
        } catch (PDOException $e) {
            if ($bdd->inTransaction()) {
                $bdd->rollBack();
            }
            $message = "Erreur lors de l'enregistrement : " . $e->getMessage();
            $messageType = "error";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Ajouter Paiement</title>
<style>
*{ margin:0; padding:0; box-sizing:border-box; font-family:Arial, sans-serif; }
body{ background:#f4f6f9; }
.container{ width:100%; min-height:100vh; display:flex; justify-content:center; align-items:flex-start; padding:40px 20px; }
.form-card{ width:1000px; background:white; border-radius:14px; box-shadow:0 8px 25px rgba(0,0,0,.12); padding:30px; }
.form-title{ text-align:center; color:#003366; margin-bottom:25px; font-size:28px; font-weight:bold; }
.message{ padding:14px; border-radius:8px; margin-bottom:20px; font-weight:bold; }
.success{ background:#d4edda; color:#155724; border:1px solid #c3e6cb; }
.error{ background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; }
form{ display:grid; grid-template-columns:1fr 1fr; gap:20px; }
.form-group{ display:flex; flex-direction:column; }
.full{ grid-column:1 / 3; }
label{ font-weight:bold; color:#333; margin-bottom:7px; }
input[type="text"], input[type="number"], input[type="date"], select{ width:100%; height:46px; padding:10px 12px; border:1px solid #ccc; border-radius:8px; outline:none; font-size:15px; transition:0.2s; background:#fff; }
input:focus, select:focus{ border-color:#00509e; box-shadow:0 0 0 3px rgba(0,80,158,.12); }
input:disabled{ background:#f1f1f1; cursor:not-allowed; }
.faculte-info{ font-size:13px; color:#6b7280; margin-top:4px; padding:6px 12px; background:#f3f4f6; border-radius:6px; }
.info{ grid-column:1 / 3; background:#eef5ff; border-left:4px solid #00509e; padding:12px; border-radius:8px; color:#333; font-size:14px; line-height:1.5; }
.btns{ grid-column:1 / 3; display:flex; justify-content:space-between; gap:15px; margin-top:10px; }
button{ flex:1; height:46px; border:none; border-radius:8px; color:white; cursor:pointer; font-weight:bold; font-size:15px; transition:0.2s; }
.btn-ajouter{ background:#28a745; }
.btn-ajouter:hover{ background:#218838; }
.btn-reset{ background:#dc3545; }
.btn-reset:hover{ background:#c82333; }
@media(max-width:900px){ .form-card{ width:100%; } form{ grid-template-columns:1fr; } .full, .info, .btns{ grid-column:1; } .btns{ flex-direction:column; } }
</style>
</head>
<body>
<div class="container">
    <div class="form-card">
        <h2 class="form-title">AJOUTER PAIEMENT</h2>
        <?php if(!empty($message)) { ?>
            <div class="message <?= $messageType ?>"><?= $message ?></div>
        <?php } ?>

        <form action="" method="POST">
            <div class="form-group">
                <label for="departement">Département</label>
                <select name="departement" id="departement" onchange="this.form.submit()">
                    <option value="">Choisir le département</option>
                    <?php foreach($departements as $dep): ?>
                        <option value="<?= $dep['id_dept'] ?>" <?= ($departementChoisi == $dep['id_dept']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($dep['nom_dept']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="faculte-info">
                    <?php 
                    if(!empty($departementChoisi)){
                        echo "Faculté : " . ($faculteChoisie ?: 'Non assignée');
                    } else {
                        echo "Sélectionnez un département pour voir la faculté";
                    }
                    ?>
                </div>
            </div>

            <div class="form-group">
                <label for="classe">Classe</label>
                <select name="classe" id="classe" onchange="this.form.submit()">
                    <option value="">Choisir la classe</option>
                    <?php if(!empty($classes)): ?>
                        <?php foreach($classes as $classe): ?>
                            <option value="<?= $classe['id_classe'] ?>" <?= ($classeChoisie == $classe['id_classe']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($classe['niveau']) ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>

            <div class="form-group full">
                <label for="etudiant">Étudiant</label>
                <select name="etudiant" id="etudiant">
                    <option value="">Choisir l'étudiant</option>
                    <?php if(!empty($etudiants)): ?>
                        <?php foreach($etudiants as $etud): ?>
                            <option value="<?= $etud['id_etudiant'] ?>" <?= (isset($_POST['etudiant']) && $_POST['etudiant'] == $etud['id_etudiant']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($etud['nom'] . ' ' . $etud['prenom']) ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="montant">Montant</label>
                <input name="montant" id="montant" type="number" step="0.01" placeholder="Montant payé" value="<?= htmlspecialchars($_POST['montant'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="date_paye">Date de paiement</label>
                <input name="date_paye" id="date_paye" type="date" value="<?= htmlspecialchars($_POST['date_paye'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="bordereau">Numéro de bordereau</label>
                <input name="bordereau" id="bordereau" type="text" placeholder="Numéro de bordereau" value="<?= htmlspecialchars($_POST['bordereau'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="bank">Banque utilisée</label>
                <select name="bank" id="bank">
                    <option value="">Choisir la banque utilisée</option>
                    <option value="IBBM+" <?= (($_POST['bank'] ?? '') == 'IBBM+') ? 'selected' : '' ?>>IBBM+</option>
                    <option value="CRDB"  <?= (($_POST['bank'] ?? '') == 'CRDB') ? 'selected' : '' ?>>CRDB</option>
                    <option value="BCB"   <?= (($_POST['bank'] ?? '') == 'BCB') ? 'selected' : '' ?>>BCB</option>
                    <option value="KCB"   <?= (($_POST['bank'] ?? '') == 'KCB') ? 'selected' : '' ?>>KCB</option>
                    <option value="BCEAB" <?= (($_POST['bank'] ?? '') == 'BCEAB') ? 'selected' : '' ?>>BCEAB</option>
                </select>
            </div>

            <div class="form-group">
                <label for="tranche">Tranche</label>
                <select name="tranche" id="tranche">
                    <option value="">Choisir la tranche</option>
                    <option value="Inscription" <?= (($_POST['tranche'] ?? '') == 'Inscription') ? 'selected' : '' ?>>Inscription</option>
                    <option value="Tranche 1" <?= (($_POST['tranche'] ?? '') == 'Tranche 1') ? 'selected' : '' ?>>Tranche 1</option>
                    <option value="Tranche 2" <?= (($_POST['tranche'] ?? '') == 'Tranche 2') ? 'selected' : '' ?>>Tranche 2</option>
                    <option value="Tranche 3" <?= (($_POST['tranche'] ?? '') == 'Tranche 3') ? 'selected' : '' ?>>Tranche 3</option>
                    <option value="Tranche 4" <?= (($_POST['tranche'] ?? '') == 'Tranche 4') ? 'selected' : '' ?>>Tranche 4</option>
                    <option value="Penalte" <?= (($_POST['tranche'] ?? '') == 'Penalte') ? 'selected' : '' ?>>Pénalité</option>
                </select>
            </div>

            <div class="form-group">
                <label for="penalte">Motif de pénalité</label>
                <input name="penalte" id="penalte" type="text" placeholder="Motif de pénalité" value="<?= htmlspecialchars($_POST['penalte'] ?? '') ?>">
            </div>

            <div class="info">
                - Tant qu'aucun <strong>département</strong> n'est choisi, aucune <strong>classe</strong> ne s'affiche.<br>
                - Tant qu'aucune <strong>classe</strong> n'est choisie, aucun <strong>étudiant</strong> ne s'affiche.<br>
                - Le champ <strong>Motif de pénalité</strong> est obligatoire uniquement si la tranche choisie est <strong>Pénalité</strong>.
            </div>

            <div class="btns">
                <button name="ajouter" class="btn-ajouter" type="submit">Ajouter</button>
                <button class="btn-reset" type="reset">Annuler</button>
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