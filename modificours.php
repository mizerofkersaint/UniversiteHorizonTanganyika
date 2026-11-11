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

// ============================================================
// VÉRIFICATION DE L'IDENTIFIANT
// ============================================================
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: affichecours.php");
    exit();
}

$id = (int)$_GET['id'];

// ============================================================
// CHARGER LE COURS AVEC REQUÊTE PRÉPARÉE
// ============================================================
$stmt = $bdd->prepare("SELECT * FROM cours WHERE id_cours = ?");
$stmt->execute([$id]);
$cours = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cours) {
    header("Location: affichecours.php");
    exit();
}

// ============================================================
// LISTE PROFESSEURS AVEC REQUÊTE PRÉPARÉE
// ============================================================
$professeurs = $bdd->query("
    SELECT p.id_prof, u.nom, u.prenom 
    FROM professeurs p 
    INNER JOIN users u ON p.id_user = u.id_user 
    ORDER BY u.nom ASC
");
$professeurs->execute();

// ============================================================
// LISTE CLASSES AVEC REQUÊTE PRÉPARÉE
// ============================================================
$classes = $bdd->query("SELECT id_classe, niveau, id_dept FROM classes ORDER BY niveau ASC");
$classes->execute();

// ============================================================
// LISTE DÉPARTEMENTS AVEC FACULTÉ
// ============================================================
$departements = $bdd->query("SELECT id_dept, nom_dept, faculte FROM departements ORDER BY nom_dept");
$departements->execute();

// ============================================================
// RÉCUPÉRER LE DÉPARTEMENT DE LA CLASSE ACTUELLE
// ============================================================
$stmtDept = $bdd->prepare("SELECT id_dept FROM classes WHERE id_classe = ?");
$stmtDept->execute([$cours['id_classe']]);
$idDeptActuel = $stmtDept->fetchColumn();

// ============================================================
// RÉCUPÉRER LA FACULTÉ DU DÉPARTEMENT ACTUEL
// ============================================================
$faculteActuelle = '';
if ($idDeptActuel) {
    $stmtFaculte = $bdd->prepare("SELECT faculte FROM departements WHERE id_dept = ?");
    $stmtFaculte->execute([$idDeptActuel]);
    $faculteActuelle = $stmtFaculte->fetchColumn();
}

// ============================================================
// TRAITEMENT DE LA MODIFICATION AVEC REQUÊTE PRÉPARÉE
// ============================================================
$message = "";
$message_type = "";

if (isset($_POST["modifier"])) {
    $nomCours = trim($_POST["nom_cours"]);
    $credit = (int)$_POST["credit"];
    $prof = (int)$_POST["prof"];
    $classe = (int)$_POST["classe"];
    $categorie = trim($_POST["categorie"]);
    $semestre = trim($_POST["semestre"]);

    if (!empty($nomCours) && $credit > 0 && $prof > 0 && $classe > 0) {
        try {
            $bdd->beginTransaction();

            $stmt = $bdd->prepare("
                UPDATE cours SET
                    nom_cours = ?,
                    credit = ?,
                    categorie = ?,
                    semestre = ?,
                    id_prof = ?,
                    id_classe = ?
                WHERE id_cours = ?
            ");
            $stmt->execute([$nomCours, $credit, $categorie, $semestre, $prof, $classe, $id]);

            // Notification
            $notif = $bdd->prepare("
                INSERT INTO notifications (titre, message, type_evenement, statut, date_creation, source_table)
                VALUES (?, ?, 'ALERT', 'non_lu', NOW(), 'cours')
            ");
            $notif->execute([
                '📚 Cours modifié',
                'Le cours "' . $cours['nom_cours'] . '" a été mis à jour. Nouveau nom : "' . $nomCours . '"'
            ]);

            $bdd->commit();

            echo "<script>
                alert('✅ Cours modifié avec succès');
                window.location='affichecours.php';
            </script>";
            exit();
        } catch (Exception $e) {
            $bdd->rollBack();
            $message = "❌ Erreur lors de la modification : " . $e->getMessage();
            $message_type = "error";
        }
    } else {
        $message = "❌ Veuillez remplir tous les champs obligatoires.";
        $message_type = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier un Cours — UniVerse</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-primary: #f0f4f8;
            --bg-card: #ffffff;
            --text-primary: #1e293b;
            --text-secondary: #475569;
            --text-muted: #64748b;
            --accent-blue: #2563eb;
            --accent-success: #10b981;
            --accent-danger: #ef4444;
            --accent-warning: #f59e0b;
            --border-color: #e2e8f0;
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.04);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.06);
            --shadow-lg: 0 8px 24px rgba(0,0,0,0.08);
            --radius: 12px;
            --radius-lg: 16px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }

        body {
            background: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .container {
            width: 100%;
            max-width: 850px;
            margin: 0 auto;
        }

        .card {
            background: var(--bg-card);
            padding: 35px 40px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            transition: var(--transition);
        }

        .card:hover {
            box-shadow: var(--shadow-lg);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 18px;
            border-bottom: 2px solid var(--border-color);
        }

        .card-header h1 {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .card-header h1 i {
            color: var(--accent-warning);
            font-size: 22px;
        }

        .card-header a {
            color: var(--accent-blue);
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: var(--transition);
        }

        .card-header a:hover {
            color: var(--accent-danger);
            transform: translateX(-4px);
        }

        .alert {
            padding: 14px 18px;
            border-radius: var(--radius);
            margin-bottom: 20px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideDown 0.4s ease;
            font-size: 14px;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .alert.success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #6ee7b7;
            border-left: 4px solid var(--accent-success);
        }

        .alert.error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
            border-left: 4px solid var(--accent-danger);
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
            letter-spacing: 0.3px;
        }

        .form-group label .required {
            color: var(--accent-danger);
            margin-left: 2px;
        }

        .form-control {
            padding: 10px 14px;
            border: 1.5px solid var(--border-color);
            border-radius: var(--radius);
            font-size: 14px;
            background: var(--bg-primary);
            transition: var(--transition);
            color: var(--text-primary);
            width: 100%;
            -webkit-appearance: none;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
            background: var(--bg-card);
        }

        .form-control:disabled {
            background: #f1f5f9;
            cursor: not-allowed;
            opacity: 0.7;
        }

        .form-control[readonly] {
            background: #f1f5f9;
            font-weight: 600;
            color: var(--text-primary);
        }

        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23475569' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 14px center;
            cursor: pointer;
        }

        .faculte-info {
            font-size: 13px;
            color: var(--text-secondary);
            margin-top: 6px;
            padding: 8px 14px;
            background: #f1f5f9;
            border-radius: 6px;
            border-left: 3px solid var(--accent-blue);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .faculte-info i {
            color: var(--accent-blue);
            font-size: 14px;
        }

        .faculte-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            background: #eff6ff;
            color: #1e40af;
            font-weight: 600;
            font-size: 12px;
        }

        .btn-group {
            display: flex;
            gap: 15px;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 28px;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 14px;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            flex: 1;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--accent-blue), #3b82f6);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }

        .btn-danger {
            background: var(--accent-danger);
            color: white;
        }

        .btn-danger:hover {
            background: #b91c1c;
            transform: translateY(-2px);
        }

        .btn-success {
            background: var(--accent-success);
            color: white;
        }

        .btn-success:hover {
            background: #059669;
            transform: translateY(-2px);
        }

        .btn-outline {
            background: transparent;
            border: 1.5px solid var(--border-color);
            color: var(--text-secondary);
        }

        .btn-outline:hover {
            background: var(--bg-primary);
            transform: translateY(-2px);
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-group.full-width {
                grid-column: 1;
            }

            .card {
                padding: 25px;
            }

            .card-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }

            .card-header h1 {
                font-size: 20px;
            }

            .btn-group {
                flex-direction: column;
            }

            .btn {
                flex: unset;
                width: 100%;
            }
        }

        @media (max-width: 480px) {
            .card {
                padding: 18px;
            }

            .card-header h1 {
                font-size: 18px;
            }

            .form-control {
                font-size: 13px;
                padding: 8px 12px;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="card">

        <!-- HEADER -->
        <div class="card-header">
            <h1>
                <i class="fas fa-edit"></i> Modifier un Cours
            </h1>
            <a href="affichecours.php">
                <i class="fas fa-arrow-left"></i> Retour à la liste
            </a>
        </div>

        <!-- MESSAGES -->
        <?php if (!empty($message)): ?>
            <div class="alert <?= $message_type; ?>">
                <i class="fas <?= $message_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                <?= htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- FORMULAIRE -->
        <form method="POST" action="modificours.php?id=<?= $id; ?>" id="formCours">
            <div class="form-grid">

                <!-- Nom du cours -->
                <div class="form-group full-width">
                    <label for="nom_cours">Nom du cours <span class="required">*</span></label>
                    <input type="text" id="nom_cours" name="nom_cours" class="form-control" value="<?= htmlspecialchars($cours['nom_cours']); ?>" required>
                </div>

                <!-- Crédit -->
                <div class="form-group">
                    <label for="credit">Crédit <span class="required">*</span></label>
                    <input type="number" id="credit" name="credit" class="form-control" value="<?= $cours['credit']; ?>" required min="1" max="30">
                </div>

                <!-- Semestre -->
                <div class="form-group">
                    <label for="semestre">Semestre <span class="required">*</span></label>
                    <select name="semestre" id="semestre" class="form-control" required>
                        <option value="Semestre 1" <?= ($cours['semestre'] == 'Semestre 1') ? 'selected' : ''; ?>>Semestre 1</option>
                        <option value="Semestre 2" <?= ($cours['semestre'] == 'Semestre 2') ? 'selected' : ''; ?>>Semestre 2</option>
                    </select>
                </div>

                <!-- Catégorie -->
                <div class="form-group">
                    <label for="categorie">Catégorie</label>
                    <select name="categorie" id="categorie" class="form-control">
                        <option value="En attente" <?= ($cours['categorie'] == 'En attente') ? 'selected' : ''; ?>>En attente</option>
                        <option value="Terminé" <?= ($cours['categorie'] == 'Terminé') ? 'selected' : ''; ?>>Terminé</option>
                    </select>
                </div>

                <!-- Professeur -->
                <div class="form-group">
                    <label for="prof">Professeur <span class="required">*</span></label>
                    <select name="prof" id="prof" class="form-control" required>
                        <option value="0">-- Sélectionnez un professeur --</option>
                        <?php while ($p = $professeurs->fetch()): ?>
                            <option value="<?= $p['id_prof']; ?>" <?= ($cours['id_prof'] == $p['id_prof']) ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($p['nom'] . ' ' . $p['prenom']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <!-- Département (avec faculté) -->
                <div class="form-group">
                    <label for="select-dept">Département <span class="required">*</span></label>
                    <select name="departement" id="select-dept" class="form-control" required>
                        <option value="0">-- Choisir un département --</option>
                        <?php
                        $departements->execute();
                        while ($d = $departements->fetch()): ?>
                            <option value="<?= $d['id_dept']; ?>" <?= ($idDeptActuel == $d['id_dept']) ? 'selected' : ''; ?> data-faculte="<?= htmlspecialchars($d['faculte']); ?>">
                                <?= htmlspecialchars($d['nom_dept']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <div class="faculte-info" id="faculte-display">
                        <i class="fas fa-university"></i>
                        <span><strong>Faculté :</strong> <?= $faculteActuelle ?: 'Non assignée'; ?></span>
                    </div>
                </div>

                <!-- Classe (dépend du département) -->
                <div class="form-group">
                    <label for="select-classe">Classe <span class="required">*</span></label>
                    <select name="classe" id="select-classe" class="form-control" required>
                        <option value="0">-- Sélectionnez un département d'abord --</option>
                        <?php
                        $classes->execute();
                        while ($c = $classes->fetch()): ?>
                            <option value="<?= $c['id_classe']; ?>" data-dept="<?= $c['id_dept']; ?>" <?= ($cours['id_classe'] == $c['id_classe']) ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($c['niveau']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

            </div>

            <!-- Boutons d'action -->
            <div class="btn-group">
                <button type="submit" name="modifier" class="btn btn-primary">
                    <i class="fas fa-save"></i> Enregistrer les modifications
                </button>
                <a href="affichecours.php" class="btn btn-danger">
                    <i class="fas fa-times"></i> Annuler
                </a>
            </div>
        </form>

    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const selectDept = document.getElementById('select-dept');
    const selectClasse = document.getElementById('select-classe');
    const faculteDisplay = document.getElementById('faculte-display');
    const ttesOptionsClasses = Array.from(selectClasse.options);

    function updateFaculte() {
        const selectedOption = selectDept.options[selectDept.selectedIndex];
        if (selectedOption && selectedOption.value && selectedOption.value !== '0') {
            const faculte = selectedOption.getAttribute('data-faculte');
            if (faculte) {
                faculteDisplay.innerHTML = '<i class="fas fa-university"></i> <span><strong>Faculté :</strong> ' + faculte + '</span>';
            } else {
                faculteDisplay.innerHTML = '<i class="fas fa-university"></i> <span><strong>Faculté :</strong> Non assignée</span>';
            }
            faculteDisplay.style.borderLeftColor = 'var(--accent-blue)';
        } else {
            faculteDisplay.innerHTML = '<i class="fas fa-info-circle"></i> <span>Sélectionnez un département pour voir la faculté</span>';
            faculteDisplay.style.borderLeftColor = '#94a3b8';
        }
    }

    function filtrerClasses() {
        const deptId = selectDept.value;
        
        // Vider le select des classes
        selectClasse.innerHTML = "";
        const optionParDefaut = document.createElement('option');
        optionParDefaut.value = "0";
        optionParDefaut.textContent = "-- Sélectionnez un département d'abord --";
        selectClasse.appendChild(optionParDefaut);
        
        if (!deptId || deptId === '0') {
            selectClasse.disabled = true;
            updateFaculte();
            return;
        }

        selectClasse.disabled = false;
        let uneClasseTrouvee = false;

        ttesOptionsClasses.forEach(option => {
            if (option.getAttribute('data-dept') === deptId) {
                const clone = option.cloneNode(true);
                selectClasse.appendChild(clone);
                uneClasseTrouvee = true;
            }
        });

        if (!uneClasseTrouvee) {
            selectClasse.value = "0";
        }

        updateFaculte();
    }

    // Lancer au chargement initial
    filtrerClasses();

    // Réagir au changement de département
    selectDept.addEventListener('change', function() {
        filtrerClasses();
    });

    // Mettre à jour la faculté si le département change
    selectDept.addEventListener('change', updateFaculte);
});
</script>

</body>
</html>