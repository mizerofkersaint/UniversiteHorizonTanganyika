<?php
require_once __DIR__ . '/db.php';
// ============================================================
// DÉMARRAGE DE SESSION — AVANT tout envoi HTML
// ============================================================
require_once 'auth.php';                    
requireRole(['Administrateur', 'Doyen', 'Agent']);  
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $bdd = getDBConnection();
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    error_log("[modificlassedepartement] Erreur connexion : " . $e->getMessage());
    die("Erreur de connexion à la base de données.");
}

// Inclusion du système d'archivage centralisé
require_once 'functions_archivage.php';

/* ============================================================
   VÉRIFICATION DE L'IDENTIFIANT
============================================================ */
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: afficheclasse.php");
    exit();
}

$id = (int) $_GET['id'];

/* ============================================================
   CHARGER LA CLASSE
============================================================ */
$stmt = $bdd->prepare("SELECT * FROM classes WHERE id_classe = ?");
$stmt->execute([$id]);
$classe = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$classe) {
    header("Location: afficheclasse.php");
    exit();
}

/* ============================================================
   DÉPARTEMENTS POUR LE SELECT
============================================================ */
$departements = $bdd->query("SELECT id_dept, nom_dept, faculte FROM departements ORDER BY nom_dept");

/* ============================================================
   FACULTÉ ACTUELLE (depuis la BDD, jamais depuis POST)
============================================================ */
$faculteActuelle = '';
if (!empty($classe['id_dept'])) {
    $stmtFac = $bdd->prepare("SELECT faculte FROM departements WHERE id_dept = ? LIMIT 1");
    $stmtFac->execute([$classe['id_dept']]);
    $faculteActuelle = $stmtFac->fetchColumn() ?: '';
}

/* ============================================================
   TRAITEMENT DE LA MODIFICATION + ARCHIVAGE
============================================================ */
if (isset($_POST['modifier'])) {

    $niveau      = isset($_POST['niveau'])      ? trim($_POST['niveau'])      : '';
    $departement = isset($_POST['departement']) ? (int) $_POST['departement'] : 0;

    if (!empty($niveau) && $departement > 0) {

        try {
            // Vérifier que le département existe réellement
            $checkDept = $bdd->prepare("SELECT id_dept FROM departements WHERE id_dept = ? LIMIT 1");
            $checkDept->execute([$departement]);
            if (!$checkDept->fetchColumn()) {
                throw new Exception("Département invalide.");
            }

            // 1. Anciennes valeurs AVANT l'UPDATE
            $ancienne_valeur = getDonneesEnregistrement($bdd, 'classes', $id);

            // 2. UPDATE
            $stmt = $bdd->prepare("UPDATE classes SET niveau = ?, id_dept = ? WHERE id_classe = ?");
            $stmt->execute([$niveau, $departement, $id]);

            // 3. Nouvelles valeurs APRÈS l'UPDATE
            $nouvelle_valeur = getDonneesEnregistrement($bdd, 'classes', $id);

            // 4. Archivage centralisé (après succès de l'UPDATE)
            archiverAction(
                $bdd,
                getUserIdEnSession($bdd),
                'UPDATE',
                'classes',
                $id,
                $ancienne_valeur,
                $nouvelle_valeur,
                'Modification de la classe'
            );

            echo "<script>
                alert('✅ Classe modifiée avec succès.');
                window.location='afficheclasse.php';
            </script>";
            exit();

        } catch (Exception $e) {
            error_log("[modificlassedepartement] Erreur modification : " . $e->getMessage());
            $msg = "Une erreur est survenue lors de la modification. Veuillez réessayer.";
            echo "<script>alert(" . json_encode($msg) . ");</script>";
        }

    } else {
        $msg = "Veuillez remplir tous les champs obligatoires.";
        echo "<script>alert(" . json_encode($msg) . ");</script>";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Modifier une Classe</title>
<style>
*{ margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI',Arial,sans-serif; }
body{ background:#eef2f7; }
.container{ width:90%; max-width:750px; margin:40px auto; }
.card{ background:#fff; border-radius:18px; box-shadow:0 10px 25px rgba(0,0,0,.08); padding:35px; }
h2{ color:#003366; text-align:center; margin-bottom:30px; }
.form-group{ display:flex; flex-direction:column; margin-bottom:20px; }
label{ margin-bottom:8px; color:#003366; font-weight:bold; }
label .required{ color:#dc2626; }
input, select{ padding:12px; border:1px solid #d1d5db; border-radius:10px; font-size:15px; outline:none; transition:border-color 0.3s; width:100%; }
input:focus, select:focus{ border-color:#0055aa; box-shadow:0 0 0 3px rgba(0,85,170,.10); }
.faculte-info{ font-size:13px; color:#6b7280; margin-top:4px; padding:6px 12px; background:#f3f4f6; border-radius:6px; border-left:3px solid #0055aa; }
.buttons{ display:flex; justify-content:center; gap:20px; margin-top:30px; }
.btn1{ background:#16a34a; color:white; border:none; padding:12px 28px; border-radius:10px; cursor:pointer; font-size:15px; font-weight:bold; transition:background 0.3s; }
.btn1:hover{ background:#15803d; }
.btn2{ background:#dc2626; color:white; border:none; padding:12px 28px; border-radius:10px; cursor:pointer; font-size:15px; font-weight:bold; transition:background 0.3s; text-decoration:none; text-align:center; display:inline-block; }
.btn2:hover{ background:#b91c1c; }
</style>
</head>
<body>
<?php include("menu.php"); ?>

<div class="container">
    <div class="card">
        <h2>✏️ Modifier une Classe</h2>
        <form method="POST">
            <div class="form-group">
                <label>Niveau <span class="required">*</span></label>
                <input type="text" name="niveau" value="<?= htmlspecialchars($classe['niveau']) ?>" required>
            </div>

            <div class="form-group">
                <label>Département <span class="required">*</span></label>
                <select name="departement" id="select-dept" required>
                    <option value="0">-- Choisir un département --</option>
                    <?php while($d = $departements->fetch()): ?>
                        <option value="<?= (int) $d['id_dept'] ?>"
                                data-faculte="<?= htmlspecialchars($d['faculte'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                <?= ($classe['id_dept'] == $d['id_dept']) ? "selected" : "" ?>>
                            <?= htmlspecialchars($d['nom_dept']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <div class="faculte-info" id="faculte-display">
                    🏛️ <strong>Faculté :</strong> <?= htmlspecialchars($faculteActuelle ?: 'Non assignée') ?>
                </div>
            </div>

            <div class="buttons">
                <input type="submit" name="modifier" value="💾 Modifier" class="btn1">
                <a href="afficheclasse.php" class="btn2">❌ Annuler</a>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectDept     = document.getElementById('select-dept');
    const faculteDisplay = document.getElementById('faculte-display');

    function setFaculteText(value) {
        faculteDisplay.innerHTML = '';
        faculteDisplay.appendChild(document.createTextNode('🏛️ '));
        const strong = document.createElement('strong');
        strong.textContent = 'Faculté :';
        faculteDisplay.appendChild(strong);
        faculteDisplay.appendChild(document.createTextNode(' ' + value));
    }

    function updateFaculte() {
        const selectedOption = selectDept.options[selectDept.selectedIndex];
        if (selectedOption && selectedOption.value && selectedOption.value !== '0') {
            const faculte = selectedOption.getAttribute('data-faculte') || '';
            setFaculteText(faculte || 'Non assignée');
        } else {
            setFaculteText('Non assignée');
        }
    }

    selectDept.addEventListener('change', updateFaculte);
    updateFaculte();
});
</script>
</body>
</html>