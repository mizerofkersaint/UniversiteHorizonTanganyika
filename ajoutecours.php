<?php
require_once __DIR__ . '/db.php';
require_once 'auth.php';
requireRole(['Administrateur', 'Doyen']);
try {
    $bdd = getDBConnection();
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

/* ============================================================
   RÉCUPÉRATION DES DÉPARTEMENTS
============================================================ */
$departements = $bdd->query("SELECT id_dept, nom_dept, faculte FROM departements ORDER BY nom_dept");
$departements->execute();

/* ============================================================
   RÉCUPÉRATION DES VALEURS SAISIES (CONSERVATION)
============================================================ */
$nomCours = isset($_POST['nc']) ? trim($_POST['nc']) : '';
$credit = isset($_POST['credit']) ? (int)$_POST['credit'] : 0;
$idProf = isset($_POST['prof']) ? (int)$_POST['prof'] : 0;
$idClasse = isset($_POST['classe']) ? (int)$_POST['classe'] : 0;
$categorie = isset($_POST['categorie']) ? trim($_POST['categorie']) : 'En attente';
$semestre = isset($_POST['semestre']) ? trim($_POST['semestre']) : 'Semestre 1';
$idDeptSelectionne = isset($_POST['departement']) ? (int)$_POST['departement'] : 0;

/* ============================================================
   INSERTION AVEC REQUÊTE PRÉPARÉE
============================================================ */
if(isset($_POST['Valid'])){
    $nomCours = trim($_POST['nc']);
    $credit = (int)$_POST['credit'];
    $idProf = (int)$_POST['prof'];
    $idClasse = (int)$_POST['classe'];
    $categorie = trim($_POST['categorie'] ?? 'En attente');
    $semestre = trim($_POST['semestre'] ?? 'Semestre 1');

    if(!empty($nomCours) && $credit > 0 && $idProf > 0 && $idClasse > 0){
        try {
            $stmt = $bdd->prepare("INSERT INTO cours (nom_cours, credit, categorie, semestre, id_prof, id_classe) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$nomCours, $credit, $categorie, $semestre, $idProf, $idClasse]);

            echo "<script>
                alert('✅ Cours enregistré avec succès !');
                window.location='affichecours.php';
            </script>";
            exit();
        } catch (Exception $e) {
            echo "<script>alert('❌ Erreur lors de l'enregistrement : " . addslashes($e->getMessage()) . "');</script>";
        }
    } else {
        echo "<script>alert('❌ Veuillez remplir tous les champs obligatoires !');</script>";
    }
}

/* ============================================================
   RÉCUPÉRATION DE LA FACULTÉ ASSOCIÉE
============================================================ */
$faculteChoisie = '';
if($idDeptSelectionne > 0){
    $stmt = $bdd->prepare("SELECT faculte FROM departements WHERE id_dept = ?");
    $stmt->execute([$idDeptSelectionne]);
    $faculteChoisie = $stmt->fetchColumn();
}

/* ============================================================
   RÉCUPÉRATION DES PROFESSEURS
============================================================ */
$professeurs = $bdd->query("
    SELECT p.id_prof, u.nom, u.prenom 
    FROM professeurs p 
    INNER JOIN users u ON p.id_user = u.id_user 
    ORDER BY u.nom ASC
");
$professeurs->execute();

/* ============================================================
   RÉCUPÉRATION DES CLASSES POUR LE DÉPARTEMENT SÉLECTIONNÉ
============================================================ */
$classes = [];
if($idDeptSelectionne > 0){
    $stmt = $bdd->prepare("SELECT id_classe, niveau FROM classes WHERE id_dept = ? ORDER BY niveau ASC");
    $stmt->execute([$idDeptSelectionne]);
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Ajouter un Cours</title>
<style>
body {
    background: #f0f7f4; 
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    margin: 0;
    color: #333;
}
.content {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: calc(100vh - 80px);
    padding: 20px;
}
.form-card {
    background: white;
    width: 100%;
    max-width: 550px;
    padding: 40px;
    border-radius: 12px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.08);
}
h2 {
    text-align: center;
    color: #2e7d32;
    margin-top: 0;
    margin-bottom: 30px;
    font-size: 24px;
    font-weight: 600;
}
.form-group {
    margin-bottom: 20px;
    display: flex;
    flex-direction: column;
}
label {
    font-weight: 600;
    margin-bottom: 8px;
    color: #4a5568;
    font-size: 14px;
}
label .required {
    color: #dc2626;
    font-weight: 700;
}
input, select {
    width: 100%;
    padding: 12px 14px;
    font-size: 15px;
    border: 2px solid #e2e8f0;
    outline: none;
    background: #f8fafc;
    border-radius: 8px;
    box-sizing: border-box;
    transition: all 0.3s ease;
}
input:focus, select:focus {
    background: #fff;
    border-color: #2e7d32;
    box-shadow: 0 0 0 3px rgba(46, 125, 50, 0.15);
}
select:disabled {
    background: #f1f5f9;
    cursor: not-allowed;
    opacity: 0.7;
}
.faculte-info {
    font-size: 13px;
    color: #6b7280;
    margin-top: 6px;
    padding: 8px 14px;
    background: #f3f4f6;
    border-radius: 6px;
    border-left: 3px solid #2e7d32;
}
.btn-container {
    display: flex;
    gap: 15px;
    margin-top: 30px;
}
.btn1 {
    flex: 2;
    background: #2e7d32;
    color: white;
    border: none;
    padding: 12px 20px;
    font-size: 16px;
    font-weight: 600;
    border-radius: 8px;
    cursor: pointer;
    transition: background 0.2s ease;
}
.btn1:hover {
    background: #1b5e20;
}
.btn2 {
    flex: 1;
    background: #e53e3e;
    color: white;
    border: none;
    padding: 12px 20px;
    font-size: 16px;
    font-weight: 600;
    border-radius: 8px;
    cursor: pointer;
    transition: background 0.2s ease;
}
.btn2:hover {
    background: #c53030;
}
</style>
</head>
<body>

<?php include("menu.php"); ?>

<div class="content">
<div class="form-card">
    <h2>📚 Ajouter Un Cours</h2>
    
    <form action="" method="POST" id="formCours">

        <!-- Nom du cours -->
        <div class="form-group">
            <label for="nc">Nom du cours <span class="required">*</span></label>
            <input type="text" id="nc" name="nc" value="<?= htmlspecialchars($nomCours) ?>" required placeholder="Ex: Algorithmique">
        </div>

        <!-- Crédit -->
        <div class="form-group">
            <label for="credit">Crédit <span class="required">*</span></label>
            <input type="number" id="credit" name="credit" value="<?= $credit > 0 ? $credit : '' ?>" required placeholder="Ex: 4" min="1" max="30">
        </div>

        <!-- Catégorie -->
        <div class="form-group">
            <label for="categorie">Catégorie</label>
            <select id="categorie" name="categorie">
                <option value="En attente" <?= ($categorie == 'En attente') ? 'selected' : '' ?>>En attente</option>
                <option value="Terminé" <?= ($categorie == 'Terminé') ? 'selected' : '' ?>>Terminé</option>
            </select>
        </div>

        <!-- Semestre -->
        <div class="form-group">
            <label for="semestre">Semestre</label>
            <select id="semestre" name="semestre">
                <option value="Semestre 1" <?= ($semestre == 'Semestre 1') ? 'selected' : '' ?>>Semestre 1</option>
                <option value="Semestre 2" <?= ($semestre == 'Semestre 2') ? 'selected' : '' ?>>Semestre 2</option>
            </select>
        </div>

        <!-- Professeur -->
        <div class="form-group">
            <label for="prof">Professeur <span class="required">*</span></label>
            <select id="prof" name="prof" required>
                <option value="0">-- Sélectionnez un professeur --</option>
                <?php while($row = $professeurs->fetch()): ?>
                    <option value="<?= $row['id_prof'] ?>" <?= ($idProf == $row['id_prof']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($row['nom'] . " " . $row['prenom']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <!-- Département -->
        <div class="form-group">
            <label for="departement">Département <span class="required">*</span></label>
            <select id="departement" name="departement" required onchange="this.form.submit()">
                <option value="0">-- Sélectionnez un département --</option>
                <?php 
                $departements->execute();
                while($row = $departements->fetch()): ?>
                    <option value="<?= $row['id_dept'] ?>" <?= ($idDeptSelectionne == $row['id_dept']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($row['nom_dept']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
            <div class="faculte-info">
                <?php if(!empty($idDeptSelectionne)): ?>
                    🏛️ <strong>Faculté :</strong> <?= $faculteChoisie ?: 'Non assignée' ?>
                <?php else: ?>
                    💡 Sélectionnez un département pour voir la faculté
                <?php endif; ?>
            </div>
        </div>

        <!-- Classe -->
        <div class="form-group">
            <label for="classe">Classe <span class="required">*</span></label>
            <select id="classe" name="classe" required <?= empty($idDeptSelectionne) ? 'disabled' : '' ?>>
                <option value="0">-- Choisissez d'abord un département --</option>
                <?php foreach($classes as $row): ?>
                    <option value="<?= $row['id_classe'] ?>" <?= ($idClasse == $row['id_classe']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($row['niveau']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Boutons -->
        <div class="btn-container">
            <input type="submit" value="💾 Enregistrer" name="Valid" class="btn1">
            <input type="reset" value="❌ Annuler" class="btn2" onclick="window.location.href='ajoutecours.php'">
        </div>

    </form>
</div>
</div>

</body>
</html>