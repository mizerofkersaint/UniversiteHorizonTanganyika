<?php
require_once __DIR__ . '/db.php';
require_once 'auth.php';
requireRole(['Administrateur', 'Doyen', 'Professeur']);
try {
    $bdd = getDBConnection();
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

include("menu.php");

// Récupération des départements avec faculté
$departements = $bdd->query("SELECT id_dept, nom_dept, faculte FROM departements ORDER BY nom_dept ASC");

// Variables pour les filtres
$departementChoisi = isset($_POST['departement']) ? $_POST['departement'] : '';
$niveauChoisi = isset($_POST['niveau']) ? $_POST['niveau'] : '';

// Récupération de la faculté du département choisi
$faculteChoisie = '';
if(!empty($departementChoisi)){
    $faculteReq = $bdd->prepare("SELECT faculte FROM departements WHERE id_dept = ?");
    $faculteReq->execute([$departementChoisi]);
    $faculteChoisie = $faculteReq->fetchColumn();
}

// Récupération de la classe correspondante
$idClasse = null;
$listeEtudiants = [];
$listeCours = [];

if(!empty($departementChoisi) && !empty($niveauChoisi)){
    $sql = $bdd->prepare("
        SELECT id_classe
        FROM classes
        WHERE id_dept = ? AND niveau = ?
    ");
    $sql->execute([$departementChoisi, $niveauChoisi]);
    $classe = $sql->fetch();

    if($classe){
        $idClasse = $classe['id_classe'];

        // Récupération des étudiants avec leurs informations
        $etudiantsReq = $bdd->prepare("
            SELECT e.id_etudiant, u.nom, u.prenom, e.matricule
            FROM etudiants e 
            INNER JOIN users u ON e.id_user = u.id_user 
            WHERE e.id_classe = ? 
            ORDER BY u.nom ASC
        ");
        $etudiantsReq->execute([$idClasse]);
        $listeEtudiants = $etudiantsReq->fetchAll();

        // Récupération des cours
        $coursReq = $bdd->prepare("SELECT id_cours, nom_cours FROM cours WHERE id_classe = ? ORDER BY nom_cours");
        $coursReq->execute([$idClasse]);
        $listeCours = $coursReq->fetchAll();
    }
}

// 2. TRAITEMENT DU FORMULAIRE (INSERTION EN TRANSACTION)
if (isset($_POST["ajouter"])) {
    $idEtudiant = $_POST["etudiant"];
    $idCours    = $_POST["cours"];
    $note       = $_POST["notes"];
    $session    = $_POST["session"];

    if (empty($idEtudiant) || empty($idCours) || $note === '' || empty($session)) {
        echo "<script>alert('Veuillez remplir tous les champs obligatoires');</script>";
    } else {
        try {
            $bdd->beginTransaction();

            $insertNote = $bdd->prepare("
                INSERT INTO notes (id_etudiant, id_cours, note, session, date_saisie)
                VALUES (?, ?, ?, ?, CURDATE())
            ");
            $insertNote->execute([$idEtudiant, $idCours, $note, $session]);

            $bdd->commit();

            echo "<script>
                    alert('Note enregistrée avec succès');
                    window.location='affichenote.php';
                  </script>";
            exit;
        } catch (Exception $e) {
            $bdd->rollBack();
            echo "<script>alert('Erreur lors de l'enregistrement : " . addslashes($e->getMessage()) . "');</script>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Ajouter des Notes</title>
<style>
*{ margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI', Arial, sans-serif; }
body{ background:#f4f6f9; }
.container{ display:flex; justify-content:center; margin:40px 0; }
fieldset{ width:750px; padding:30px; border:none; border-radius:15px; background:white; box-shadow:0 8px 25px rgba(0,0,0,.12); }
legend{ text-align:center; color:#003366; font-size:24px; font-weight:bold; padding:10px; }
.form-grid{ display:grid; grid-template-columns:1fr 1fr; gap:20px; }
.form-group{ display:flex; flex-direction:column; }
.form-group input[name="notes"]{ font-weight:bold; color:#00509e; }
label{ margin-bottom:6px; font-weight:600; color:#333; }
input[type="text"], input[type="number"], select{ width:100%; height:45px; padding:10px; border:1px solid #d0d7de; border-radius:8px; font-size:14px; transition:0.3s; }
input[type="text"]:focus, input[type="number"]:focus, select:focus{ border-color:#00509e; box-shadow:0 0 8px rgba(0,80,158,.25); outline:none; }
.niveau, .session { display:flex; gap:30px; margin-top:10px; }
.niveau div, .session div { display:flex; align-items:center; gap:5px; }
.btns{ display:flex; justify-content:center; gap:20px; margin-top:30px; }
button { width:180px; height:45px; border:none; border-radius:8px; color:white; cursor:pointer; font-size:15px; font-weight:bold; transition:.3s; }
.btn-ajouter{ background:#28a745; }
.btn-ajouter:hover{ background:#218838; transform:translateY(-2px); }
.btn-reset{ background:#dc3545; }
.btn-reset:hover{ background:#c82333; transform:translateY(-2px); }
.faculte-info{ font-size:13px; color:#6b7280; margin-top:4px; padding:6px 12px; background:#f3f4f6; border-radius:6px; }
</style>
</head>
<body>

<div class="container">
<fieldset>
    <legend>AJOUTER NOTES</legend>

    <form action="ajoutenote.php" method="POST">
    <div class="form-grid">

        <div class="form-group">
            <label for="departement">Département</label>
            <select id="departement" name="departement" onchange="this.form.submit()">
                <option value="">Choisir un département</option>
                <?php foreach($departements as $dataClasse){ ?>
                    <option value="<?php echo $dataClasse["id_dept"]; ?>" <?php echo ($departementChoisi == $dataClasse["id_dept"]) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($dataClasse["nom_dept"]); ?>
                    </option>
                <?php } ?>
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
            <label>Classe</label>
            <div class="niveau">
                <div>
                    <input type="radio" id="bac1" name="niveau" value="BAC I" onchange="this.form.submit()" <?php echo ($niveauChoisi == 'BAC I') ? 'checked' : ''; ?>>
                    <label for="bac1">BAC I</label>
                </div>
                <div>
                    <input type="radio" id="bac2" name="niveau" value="BAC II" onchange="this.form.submit()" <?php echo ($niveauChoisi == 'BAC II') ? 'checked' : ''; ?>>
                    <label for="bac2">BAC II</label>
                </div>
                <div>
                    <input type="radio" id="bac3" name="niveau" value="BAC III" onchange="this.form.submit()" <?php echo ($niveauChoisi == 'BAC III') ? 'checked' : ''; ?>>
                    <label for="bac3">BAC III</label>
                </div>
            </div>
        </div>
        
        <div class="form-group">
            <label for="etudiant">Étudiant</label>
            <select id="etudiant" name="etudiant">
                <option value="">Choisir un étudiant</option>
                <?php foreach($listeEtudiants as $etud){ ?>
                    <option value="<?php echo $etud['id_etudiant']; ?>">
                        <?php echo htmlspecialchars($etud['nom'].' '.$etud['prenom']); ?>
                    </option>
                <?php } ?>
            </select>
        </div>

        <div class="form-group">
            <label for="cours">Cours</label>
            <select id="cours" name="cours">
                <option value="">Choisir un cours</option>
                <?php foreach($listeCours as $cour){ ?>
                    <option value="<?php echo $cour['id_cours']; ?>">
                        <?php echo htmlspecialchars($cour['nom_cours']); ?>
                    </option>
                <?php } ?>
            </select>
        </div>

        <div class="form-group">
            <label for="notes">Notes</label>
            <input id="notes" name="notes" type="number" min="0" max="20" step="0.5" placeholder="Note obtenue">
        </div>

        <div class="form-group">
            <label>Session</label>
            <div class="session">
                <div>
                    <input type="radio" id="session1" value="1ère Session" name="session" checked>
                    <label for="session1">1ère Session</label>
                </div>
                <div>
                    <input type="radio" id="session2" value="2ème Session" name="session">
                    <label for="session2">2ème Session</label>
                </div>
            </div>
        </div>

    </div>

    <div class="btns">
        <button name="ajouter" class="btn-ajouter" type="submit">Ajouter</button>
        <button class="btn-reset" type="reset">Annuler</button>
    </div>
    </form>
</fieldset>
</div>

</body>
</html>