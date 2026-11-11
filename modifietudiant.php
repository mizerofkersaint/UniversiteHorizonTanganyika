<?php
require_once __DIR__ . '/db.php';
require_once 'auth.php';
requireRole(['Administrateur', 'Doyen', 'Agent']);
include("menu.php");

try {
    $bdd = getDBConnection();
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: AffichageEtudiants.php");
    exit();
}

$id = (int)$_GET['id'];
$erreur = "";

/* Chargement unifié via jointures avec ajout de l'année académique et de la faculté */
$req = $bdd->prepare("
    SELECT e.*, u.nom, u.prenom, u.sexe, u.telephone, u.email AS email_personnel, u.email_auth, 
           c.id_dept, e.annee_academique_entree, d.faculte
    FROM etudiants e 
    INNER JOIN users u ON u.id_user = e.id_user 
    LEFT JOIN classes c ON e.id_classe = c.id_classe
    LEFT JOIN departements d ON c.id_dept = d.id_dept
    WHERE e.id_etudiant = ?
");
$req->execute([$id]);
$etudiant = $req->fetch(PDO::FETCH_ASSOC);

if (!$etudiant) {
    header("Location: AffichageEtudiants.php");
    exit();
}

// Récupération de tous les départements avec leur faculté
$departements = $bdd->query("SELECT id_dept, nom_dept, faculte FROM departements ORDER BY nom_dept");

// Récupération de toutes les classes avec leur ID département associé
$classes_q = $bdd->query("SELECT * FROM classes ORDER BY niveau");
$liste_classes = $classes_q->fetchAll(PDO::FETCH_ASSOC);

/* ==========================================
   MUTATION DES DONNÉES EN BD (TRANSACTION)
========================================== */
if (isset($_POST["modifier"])) {
    try {
        $bdd->beginTransaction();

        // 1. ÉTAPE 1 : Mise à jour de la table mère (users)
        $updateUser = $bdd->prepare("
            UPDATE users
            SET nom = ?, prenom = ?, sexe = ?, telephone = ?, email = ?
            WHERE id_user = ?
        ");
        $updateUser->execute([
            trim($_POST["nom"]),
            trim($_POST["prenom"]),
            $_POST["sexe"],
            trim($_POST["tel"]),
            trim($_POST["em_perso"]),
            $etudiant['id_user']
        ]);

        // 2. ÉTAPE 2 : Mise à jour de la table fille (etudiants)
        $updateEtudiant = $bdd->prepare("
            UPDATE etudiants
            SET date_naissance = ?, matricule = ?, id_classe = ?, titre = ?, annee_academique_entree = ?
            WHERE id_etudiant = ?
        ");
        $updateEtudiant->execute([
            !empty($_POST["dn"]) ? $_POST["dn"] : null,
            trim($_POST["mat"]),
            $_POST["cl"],
            $_POST["titre"],
            trim($_POST["annee_acad"]),
            $id
        ]);

        $bdd->commit();

        echo "<script>
        alert('Étudiant et compte utilisateur mis à jour avec succès.');
        window.location='AffichageEtudiants.php';
        </script>";
        exit();

    } catch (Exception $e) {
        $bdd->rollBack();
        $erreur = "Erreur lors de la modification : " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Modifier Étudiant</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Arial, sans-serif; }
        body { background: #eef3f8; }
        .container { width: 90%; max-width: 850px; margin: 40px auto; }
        .card { background: white; border-radius: 18px; padding: 35px; box-shadow: 0 10px 25px rgba(0,0,0,.08); }
        h2 { color: #003366; text-align: center; margin-bottom: 30px; }
        .alert-error { background: #ffeeec; color: #dc2626; padding: 12px; border-radius: 8px; margin-bottom: 15px; font-weight: bold; font-size: 14px; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .row { display: flex; flex-direction: column; }
        label { margin-bottom: 8px; font-weight: bold; color: #003366; }
        input, select { padding: 12px; border: 1px solid #d1d5db; border-radius: 10px; outline: none; font-size: 15px; width: 100%; }
        input:focus, select:focus { border-color: #0066cc; }
        .buttons { margin-top: 30px; display: flex; justify-content: center; gap: 20px; }
        .btn1 { background: #16a34a; color: white; border: none; padding: 12px 30px; border-radius: 10px; cursor: pointer; font-size: 15px; font-weight: bold; }
        .btn2 { background: #dc2626; color: white; border: none; padding: 12px 30px; border-radius: 10px; cursor: pointer; font-size: 15px; font-weight: bold; text-decoration: none; text-align: center; }
        .info-text { font-size: 12px; color: #6b7280; margin-top: 4px; }
        @media(max-width:700px){ .form-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<div class="container">
    <div class="card">
        <h2>Modifier un Étudiant</h2>

        <?php if(!empty($erreur)): ?>
            <div class="alert-error"><?= htmlspecialchars($erreur) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-grid">
                <div class="row">
                    <label>Nom</label>
                    <input type="text" name="nom" value="<?= htmlspecialchars($etudiant['nom']); ?>" required>
                </div>

                <div class="row">
                    <label>Prénom</label>
                    <input type="text" name="prenom" value="<?= htmlspecialchars($etudiant['prenom']); ?>" required>
                </div>

                <div class="row">
                    <label>Sexe</label>
                    <select name="sexe" required>
                        <option value="Masculin" <?= ($etudiant['sexe'] == "Masculin") ? "selected" : ""; ?>>Masculin</option>
                        <option value="Feminin" <?= ($etudiant['sexe'] == "Feminin") ? "selected" : ""; ?>>Féminin</option>
                    </select>
                </div>

                <div class="row">
                    <label>Date de naissance</label>
                    <input type="date" name="dn" value="<?= htmlspecialchars($etudiant['date_naissance'] ?? ''); ?>">
                </div>

                <div class="row">
                    <label>Téléphone</label>
                    <input type="text" name="tel" value="<?= htmlspecialchars($etudiant['telephone'] ?? ''); ?>">
                </div>

                <div class="row">
                    <label>Email Personnel</label>
                    <input type="email" name="em_perso" value="<?= htmlspecialchars($etudiant['email_personnel'] ?? ''); ?>" required>
                </div>

                <div class="row">
                    <label>Matricule</label>
                    <input type="text" name="mat" value="<?= htmlspecialchars($etudiant['matricule'] ?? ''); ?>">
                </div>

                <div class="row">
                    <label>Faculté (Lecture seule)</label>
                    <input type="text" id="faculte-display" value="<?= htmlspecialchars($etudiant['faculte'] ?? 'Non assignée'); ?>" readonly style="background:#f3f4f6; color:#6b7280; cursor:not-allowed;">
                </div>

                <div class="row">
                    <label>Département</label>
                    <select id="select-dept" required>
                        <option value="">-- Choisir un département --</option>
                        <?php 
                        $departements->execute();
                        while($d = $departements->fetch(PDO::FETCH_ASSOC)){ ?>
                            <option value="<?= $d['id_dept']; ?>" <?= ($etudiant['id_dept'] == $d['id_dept']) ? "selected" : ""; ?> data-faculte="<?= htmlspecialchars($d['faculte']); ?>">
                                <?= htmlspecialchars($d['nom_dept']); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>

                <div class="row">
                    <label>Classe</label>
                    <select id="select-classe" name="cl" required>
                        <option value="">-- Sélectionnez d'abord un département --</option>
                        <?php foreach($liste_classes as $c){ ?>
                            <option value="<?= $c['id_classe']; ?>" data-dept="<?= $c['id_dept']; ?>" <?= ($etudiant['id_classe'] == $c['id_classe']) ? "selected" : ""; ?>>
                                <?= htmlspecialchars($c['niveau']); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>

                <div class="row">
                    <label>Titre</label>
                    <select name="titre" required>
                        <option value="Nouveau" <?= ($etudiant['titre'] == "Nouveau") ? "selected" : ""; ?>>Nouveau</option>
                        <option value="Redoublant" <?= ($etudiant['titre'] == "Redoublant") ? "selected" : ""; ?>>Redoublant</option>
                    </select>
                </div>

                <div class="row">
                    <label>Année Académique</label>
                    <input type="text" name="annee_acad" value="<?= htmlspecialchars($etudiant['annee_academique_entree'] ?? ''); ?>" placeholder="Ex: 2025-2026" required>
                </div>

                <div class="row">
                    <label>Email Académique (Lecture seule)</label>
                    <input type="text" value="<?= htmlspecialchars($etudiant['email_auth'] ?? ''); ?>" readonly style="background:#f3f4f6; color:#6b7280; cursor:not-allowed;">
                </div>
            </div>

            <div class="buttons">
                <input type="submit" name="modifier" value="Modifier" class="btn1">
                <a href="AffichageEtudiants.php" class="btn2">Annuler</a>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const selectDept = document.getElementById("select-dept");
    const selectClasse = document.getElementById("select-classe");
    const faculteDisplay = document.getElementById("faculte-display");
    const ttesOptionsClasses = Array.from(selectClasse.options);

    // Fonction pour mettre à jour l'affichage de la faculté
    function updateFaculte() {
        const selectedOption = selectDept.options[selectDept.selectedIndex];
        if (selectedOption && selectedOption.value) {
            const faculte = selectedOption.getAttribute('data-faculte');
            faculteDisplay.value = faculte || 'Non assignée';
        } else {
            faculteDisplay.value = '';
        }
    }

    function filtrerClasses() {
        const deptId = selectDept.value;
        
        selectClasse.innerHTML = "";
        
        if(!deptId) {
            selectClasse.appendChild(ttesOptionsClasses[0]);
            updateFaculte();
            return;
        }

        let uneClasseSelectionnee = false;
        ttesOptionsClasses.forEach(option => {
            if(option.getAttribute("data-dept") === deptId) {
                selectClasse.appendChild(option);
                if(option.hasAttribute("selected")) {
                    option.selected = true;
                    uneClasseSelectionnee = true;
                }
            }
        });

        if(!uneClasseSelectionnee && selectClasse.options.length > 0) {
            selectClasse.selectedIndex = 0;
        }
        
        updateFaculte();
    }

    // Lancer au chargement initial
    filtrerClasses();

    // Réagir au changement de département
    selectDept.addEventListener("change", function() {
        filtrerClasses();
    });
});
</script>

</body>
</html>