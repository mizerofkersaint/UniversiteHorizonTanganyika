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
    error_log("[ajouteclassedepartement] Erreur connexion : " . $e->getMessage());
    die("Erreur de connexion à la base de données.");
}

// Inclusion du système d'archivage centralisé
require_once 'functions_archivage.php';

/* ============================================================
   INSERTION + ARCHIVAGE CENTRALISÉ
============================================================ */
if (isset($_POST['Valid'])) {

    $niveau      = isset($_POST['niveau']) ? trim($_POST['niveau']) : '';
    $departement = isset($_POST['depart']) ? (int) $_POST['depart'] : 0;

    if (!empty($niveau) && $departement > 0) {

        try {
            // Vérification que le département existe réellement
            $checkDept = $bdd->prepare("SELECT id_dept FROM departements WHERE id_dept = ? LIMIT 1");
            $checkDept->execute([$departement]);
            if (!$checkDept->fetchColumn()) {
                throw new Exception("Département invalide.");
            }

            // INSERT
            $stmt = $bdd->prepare("INSERT INTO classes (niveau, id_dept) VALUES (?, ?)");
            $stmt->execute([$niveau, $departement]);

            // ✅ Archivage centralisé — APRÈS la réussite de l'INSERT
            $id_nouvelle_classe = (int) $bdd->lastInsertId();
            $donnees_nouvelle   = getDonneesEnregistrement($bdd, 'classes', $id_nouvelle_classe);

            archiverAction(
                $bdd,
                getUserIdEnSession($bdd),
                'INSERT',
                'classes',
                $id_nouvelle_classe,
                null,
                $donnees_nouvelle,
                'Création de la classe'
            );

            echo "<script>alert('Classe ajoutée avec succès !'); window.location='afficheclasse.php';</script>";
            exit();

        } catch (Exception $e) {
            error_log("[ajouteclassedepartement] Erreur insertion : " . $e->getMessage());
            $msg = "Une erreur est survenue lors de l'ajout. Veuillez réessayer.";
            echo "<script>alert(" . json_encode($msg) . ");</script>";
        }

    } else {
        $msg = "Veuillez sélectionner un département et un niveau.";
        echo "<script>alert(" . json_encode($msg) . ");</script>";
    }
}

/* ============================================================
   DONNÉES POUR LE FORMULAIRE
============================================================ */
$departements = $bdd->query("SELECT id_dept, nom_dept, faculte FROM departements ORDER BY nom_dept");

$departementChoisi = isset($_POST['depart']) ? (int) $_POST['depart'] : 0;
$niveauChoisi      = isset($_POST['niveau']) ? trim($_POST['niveau']) : '';
$faculteChoisie    = '';

if ($departementChoisi > 0) {
    $stmtFac = $bdd->prepare("SELECT faculte FROM departements WHERE id_dept = ? LIMIT 1");
    $stmtFac->execute([$departementChoisi]);
    $faculteChoisie = $stmtFac->fetchColumn() ?: '';
}
?>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter une Classe — UniVerse</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        body { background: linear-gradient(135deg, #eef2f7 0%, #f8fafc 100%); min-height: 100vh; display: flex; flex-direction: column; }
        .page-header { background: linear-gradient(135deg, #0f172a, #1e293b); padding: 30px 0; text-align: center; border-bottom: 4px solid #3b82f6; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15); }
        .page-header h1 { color: #ffffff; font-size: 28px; font-weight: 700; letter-spacing: 0.5px; }
        .page-header h1 span { color: #3b82f6; }
        .page-header p { color: #94a3b8; font-size: 14px; margin-top: 5px; }
        .container { display: flex; justify-content: center; align-items: center; padding: 50px 20px; flex: 1; }
        .form-card { background: #ffffff; border-radius: 20px; padding: 45px 50px 40px; width: 100%; max-width: 580px; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.08), 0 8px 20px rgba(0, 0, 0, 0.03); border: 1px solid rgba(226, 232, 240, 0.6); transition: transform 0.3s ease, box-shadow 0.3s ease; }
        .form-card:hover { transform: translateY(-4px); box-shadow: 0 30px 80px rgba(0, 0, 0, 0.1); }
        .form-title { text-align: center; margin-bottom: 35px; }
        .form-title h2 { color: #0f172a; font-size: 24px; font-weight: 700; letter-spacing: -0.3px; }
        .form-title .subtitle { color: #64748b; font-size: 14px; margin-top: 6px; }
        .form-title .divider { width: 50px; height: 4px; background: linear-gradient(90deg, #3b82f6, #8b5cf6); border-radius: 4px; margin: 12px auto 0; }
        .form-group { margin-bottom: 22px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px; letter-spacing: 0.3px; }
        .form-group label .required { color: #ef4444; margin-left: 2px; }
        .form-group select { width: 100%; padding: 12px 16px; font-size: 14px; color: #1e293b; background: #f8fafc; border: 2px solid #e2e8f0; border-radius: 10px; outline: none; transition: all 0.3s ease; appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2364748b' d='M6 8L1 3h10z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 16px center; cursor: pointer; }
        .form-group select:hover { border-color: #cbd5e1; }
        .form-group select:focus { border-color: #3b82f6; box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1); background-color: #ffffff; }
        .form-group select:disabled { background-color: #f1f5f9; cursor: not-allowed; opacity: 0.7; }
        .faculte-info { font-size: 13px; color: #475569; margin-top: 8px; padding: 10px 14px; background: #f1f5f9; border-radius: 8px; border-left: 4px solid #3b82f6; transition: all 0.3s ease; }
        .faculte-info .label { font-weight: 600; color: #1e293b; }
        .faculte-info .value { color: #2563eb; font-weight: 600; }
        .faculte-info.empty { border-left-color: #94a3b8; color: #94a3b8; }
        .form-actions { display: flex; gap: 15px; margin-top: 30px; padding-top: 25px; border-top: 1px solid #e2e8f0; }
        .btn { flex: 1; padding: 14px 20px; font-size: 15px; font-weight: 600; border: none; border-radius: 10px; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; justify-content: center; gap: 8px; }
        .btn-primary { background: linear-gradient(135deg, #2563eb, #3b82f6); color: #ffffff; box-shadow: 0 4px 14px rgba(37, 99, 235, 0.25); }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(37, 99, 235, 0.35); }
        .btn-danger { background: #f1f5f9; color: #64748b; }
        .btn-danger:hover { background: #e2e8f0; color: #475569; transform: translateY(-2px); }
        @media (max-width: 640px) { .form-card { padding: 30px 25px; } .page-header h1 { font-size: 22px; } .form-actions { flex-direction: column; } }
    </style>
</head>
<body>
    <header class="page-header">
        <h1>🎓 <span>UniVerse</span></h1>
        <p>Gestion des classes et départements</p>
    </header>

    <?php include("menu.php"); ?>

    <div class="container">
        <div class="form-card">
            <div class="form-title">
                <h2>📚 Ajouter une Classe</h2>
                <p class="subtitle">Remplissez les informations ci-dessous</p>
                <div class="divider"></div>
            </div>

            <form action="" method="POST">
                <!-- Département -->
                <div class="form-group">
                    <label for="select-dept">Département <span class="required">*</span></label>
                    <select name="depart" id="select-dept" required onchange="this.form.submit()">
                        <option value="0">— Sélectionner un département —</option>
                        <?php while($row = $departements->fetch()): ?>
                            <option value="<?= (int) $row['id_dept'] ?>"
                                    data-faculte="<?= htmlspecialchars($row['faculte'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                    <?= ($departementChoisi == $row['id_dept']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($row['nom_dept']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <!-- Faculté (affichage informatif, ne bloque pas l'enregistrement) -->
                <div class="form-group">
                    <label>Faculté</label>
                    <input type="text"
                           id="faculte-input"
                           value="<?= htmlspecialchars($faculteChoisie ?: 'Non assignée') ?>"
                           readonly
                           style="width:100%; padding:12px 16px; font-size:14px; color:#1e293b; background:#f1f5f9; border:2px solid #e2e8f0; border-radius:10px; outline:none;">
                    <div class="faculte-info <?= empty($departementChoisi) ? 'empty' : '' ?>" id="faculte-display">
                        <?php if(!empty($departementChoisi)): ?>
                            <span class="label">🏛️ Faculté :</span> <span class="value"><?= htmlspecialchars($faculteChoisie ?: 'Non assignée') ?></span>
                        <?php else: ?>
                            💡 Sélectionnez un département pour voir la faculté
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Niveau -->
                <div class="form-group">
                    <label for="select-niveau">Niveau <span class="required">*</span></label>
                    <select name="niveau" id="select-niveau" required <?= empty($departementChoisi) ? 'disabled' : '' ?>>
                        <option value="">— Choisir le niveau —</option>
                        <option value="BAC I"   <?= ($niveauChoisi === 'BAC I')   ? 'selected' : '' ?>>BAC I</option>
                        <option value="BAC II"  <?= ($niveauChoisi === 'BAC II')  ? 'selected' : '' ?>>BAC II</option>
                        <option value="BAC III" <?= ($niveauChoisi === 'BAC III') ? 'selected' : '' ?>>BAC III</option>
                        <option value="BAC IV"  <?= ($niveauChoisi === 'BAC IV')  ? 'selected' : '' ?>>BAC IV</option>
                        <option value="BAC V"   <?= ($niveauChoisi === 'BAC V')   ? 'selected' : '' ?>>BAC V</option>
                    </select>
                </div>

                <!-- Actions -->
                <div class="form-actions">
                    <button type="submit" name="Valid" class="btn btn-primary">💾 Enregistrer</button>
                    <button type="reset" class="btn btn-danger">❌ Annuler</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const selectDept     = document.getElementById('select-dept');
        const faculteInput   = document.getElementById('faculte-input');
        const faculteDisplay = document.getElementById('faculte-display');

        function setFaculteText(value) {
            faculteDisplay.innerHTML = '';
            const label = document.createElement('span');
            label.className = 'label';
            label.textContent = '🏛️ Faculté :';
            const val = document.createElement('span');
            val.className = 'value';
            val.textContent = value;
            faculteDisplay.appendChild(label);
            faculteDisplay.appendChild(document.createTextNode(' '));
            faculteDisplay.appendChild(val);
        }

        function setEmptyMessage() {
            faculteDisplay.innerHTML = '';
            faculteDisplay.textContent = '💡 Sélectionnez un département pour voir la faculté';
        }

        function updateFaculte() {
            const selectedOption = selectDept.options[selectDept.selectedIndex];
            if (selectedOption && selectedOption.value && selectedOption.value !== '0') {
                const faculte = selectedOption.getAttribute('data-faculte') || '';
                if (faculte) {
                    faculteInput.value = faculte;
                    setFaculteText(faculte);
                    faculteDisplay.className = 'faculte-info';
                } else {
                    faculteInput.value = 'Non assignée';
                    setFaculteText('Non assignée');
                    faculteDisplay.className = 'faculte-info';
                }
            } else {
                faculteInput.value = 'Non assignée';
                setEmptyMessage();
                faculteDisplay.className = 'faculte-info empty';
            }
        }

        selectDept.addEventListener('change', updateFaculte);
        updateFaculte();
    });
    </script>
</body>
</html>