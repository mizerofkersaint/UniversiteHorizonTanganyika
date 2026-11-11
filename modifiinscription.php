<?php
require_once __DIR__ . '/db.php';
// ============================================================
// modifiinscription.php — Modification d'une inscription
// ✅ Support iframe + édition étudiant + date
// ============================================================
require_once 'auth.php';
requireRole(['Administrateur', 'Doyen', 'Agent']);

try {
    $bdd = getDBConnection();
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Erreur : " . $e->getMessage());
}

$is_iframe = isset($_GET['iframe']) && $_GET['iframe'] === '1';
$iframe_query = $is_iframe ? 'iframe=1&' : '';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header("Location: afficheinscription.php" . ($is_iframe ? "?iframe=1" : ""));
    exit();
}

/* Récupération de l'inscription */
$stmt = $bdd->prepare("
    SELECT i.*, e.id_etudiant, e.matricule, e.id_classe,
           u.nom, u.prenom,
           cl.id_dept, cl.niveau,
           d.nom_dept, d.faculte
    FROM inscriptions i
    INNER JOIN etudiants e ON i.id_etudiant = e.id_etudiant
    INNER JOIN users u ON e.id_user = u.id_user
    INNER JOIN classes cl ON e.id_classe = cl.id_classe
    INNER JOIN departements d ON cl.id_dept = d.id_dept
    WHERE i.id_inscription = ?
");
$stmt->execute([$id]);
$inscription = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$inscription) {
    header("Location: afficheinscription.php" . ($is_iframe ? "?iframe=1" : ""));
    exit();
}

$departements = $bdd->query("SELECT id_dept, nom_dept, faculte FROM departements ORDER BY nom_dept")->fetchAll(PDO::FETCH_ASSOC);

/* Valeurs actuelles (POST > valeur BDD) */
$current_dept     = isset($_POST['dept'])     ? (int)$_POST['dept']     : (int)$inscription['id_dept'];
$current_classe   = isset($_POST['classe'])   ? (int)$_POST['classe']   : (int)$inscription['id_classe'];
$current_etudiant = isset($_POST['etudiant']) ? (int)$_POST['etudiant'] : (int)$inscription['id_etudiant'];
$current_date     = isset($_POST['date_inscription']) ? $_POST['date_inscription'] : $inscription['date_inscription'];

/* Classes */
$classes = [];
if ($current_dept > 0) {
    $stmtC = $bdd->prepare("SELECT id_classe, niveau FROM classes WHERE id_dept = ? ORDER BY niveau");
    $stmtC->execute([$current_dept]);
    $classes = $stmtC->fetchAll(PDO::FETCH_ASSOC);
}

/* Étudiants (inclure l'étudiant actuel) */
$etudiants = [];
if ($current_classe > 0) {
    $stmtE = $bdd->prepare("
        SELECT e.id_etudiant, e.matricule, u.nom, u.prenom
        FROM etudiants e
        INNER JOIN users u ON e.id_user = u.id_user
        WHERE e.id_classe = ?
        ORDER BY u.nom, u.prenom
    ");
    $stmtE->execute([$current_classe]);
    $etudiants = $stmtE->fetchAll(PDO::FETCH_ASSOC);
}

/* Traitement */
$message = "";
$message_type = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['modifier'])) {
    $new_etudiant = (int)($_POST['etudiant'] ?? 0);
    $new_date     = trim($_POST['date_inscription'] ?? '');

    if ($new_etudiant <= 0 || $new_date === '') {
        $message = "❌ Sélectionnez un étudiant et une date.";
        $message_type = "error";
    } else {
        try {
            if ($new_etudiant !== (int)$inscription['id_etudiant']) {
                $check = $bdd->prepare("SELECT COUNT(*) FROM inscriptions WHERE id_etudiant = ? AND id_inscription != ?");
                $check->execute([$new_etudiant, $id]);
                if ($check->fetchColumn() > 0) {
                    $message = "❌ Cet étudiant est déjà inscrit.";
                    $message_type = "error";
                    goto skip_update;
                }
            }

            $upd = $bdd->prepare("UPDATE inscriptions SET id_etudiant = ?, date_inscription = ? WHERE id_inscription = ?");
            $upd->execute([$new_etudiant, $new_date, $id]);

            $redirect = "afficheinscription.php?success=modification" . ($is_iframe ? "&iframe=1" : "");
            header("Location: $redirect");
            exit();
        } catch (Exception $e) {
            $message = "❌ Erreur : " . htmlspecialchars($e->getMessage());
            $message_type = "error";
        }
    }
}
skip_update:
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier inscription — UniVerse</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Arial, sans-serif; }
        body { background: #eef3f8; min-height: 100vh; color: #1e293b; }
        body.iframe-mode { background: #f8fafc; }

        .main-container { padding: 30px 20px; }
        .main-container.in-iframe { padding: 18px; }
        .page-inner { max-width: 700px; margin: 0 auto; width: 100%; }

        /* IFRAME TOPBAR */
        .iframe-topbar {
            display: flex; justify-content: space-between; align-items: center;
            padding: 14px 20px; background: white; border-bottom: 1px solid #e2e8f0;
            margin-bottom: 18px; border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05); flex-wrap: wrap; gap: 12px;
        }
        .iframe-topbar-left { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        .btn-back {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 9px 16px; background: #f1f5f9; color: #475569;
            text-decoration: none; border-radius: 8px; font-size: 13px;
            font-weight: 600; border: 1px solid #e2e8f0; transition: all 0.2s; white-space: nowrap;
        }
        .btn-back:hover { background: #e2e8f0; color: #1e293b; }
        .btn-nav {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 9px 16px; background: white; color: #475569;
            text-decoration: none; border-radius: 8px; font-size: 13px;
            font-weight: 600; border: 1px solid #e2e8f0; transition: all 0.2s; white-space: nowrap;
        }
        .btn-nav:hover { background: #f0fdf4; color: #065f46; border-color: #10b981; }

        /* PAGE HEADER */
        .page-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 22px; flex-wrap: wrap; gap: 12px;
        }
        .page-header h1 { color: #003366; font-size: 22px; }

        /* ALERTES */
        .alert { padding: 14px 18px; border-radius: 10px; margin-bottom: 18px; font-size: 14px; animation: slideDown 0.3s ease; }
        .alert.success { background: #d1fae5; color: #065f46; border-left: 4px solid #10b981; }
        .alert.error { background: #fee2e2; color: #991b1b; border-left: 4px solid #dc2626; }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }

        /* CARTE */
        .card {
            background: white; padding: 28px; border-radius: 16px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05); border: 1px solid #e2e8f0;
            width: 100%; overflow: hidden;
        }

        .info-box {
            background: #eff6ff; padding: 14px 18px; border-radius: 10px;
            margin-bottom: 20px; border-left: 4px solid #3b82f6;
            color: #1e40af; font-size: 13px; line-height: 1.7;
        }
        .info-box strong { color: #1e3a8a; }
        .info-box .info-row { margin-top: 4px; }

        /* FORMULAIRE */
        .form-group { display: flex; flex-direction: column; margin-bottom: 16px; min-width: 0; }
        .form-group label { font-weight: 600; font-size: 13px; color: #334155; margin-bottom: 6px; }
        .form-group label .req { color: #dc2626; margin-left: 2px; }
        .form-group input, .form-group select {
            width: 100%; max-width: 100%;
            padding: 11px 14px; border-radius: 10px;
            border: 1.5px solid #cbd5e1; outline: none;
            font-size: 14px; background: #f8fafc;
            transition: all 0.2s; font-family: inherit;
        }
        .form-group input:focus, .form-group select:focus {
            border-color: #0055aa; box-shadow: 0 0 0 3px rgba(0,85,170,0.1);
            background: white;
        }
        .form-group select:disabled { background: #f1f5f9; cursor: not-allowed; opacity: 0.7; }

        /* BOUTONS */
        .btn-row { display: flex; gap: 12px; margin-top: 22px; padding-top: 20px; border-top: 1px solid #e2e8f0; flex-wrap: wrap; }
        .btn {
            padding: 12px 24px; border: none; border-radius: 10px;
            font-weight: 700; font-size: 14px; cursor: pointer;
            transition: all 0.2s; display: inline-flex;
            align-items: center; justify-content: center; gap: 8px;
            text-decoration: none; white-space: nowrap;
        }
        .btn-success { background: #16a34a; color: white; flex: 1; min-width: 140px; }
        .btn-success:hover { background: #15803d; }
        .btn-cancel { background: #e5e7eb; color: #111827; flex: 1; min-width: 140px; }
        .btn-cancel:hover { background: #d1d5db; }

        @media (max-width: 700px) {
            .main-container { padding: 16px 12px; }
            .main-container.in-iframe { padding: 12px; }
            .card { padding: 20px; }
            .btn-row .btn { min-width: 0; }
        }
    </style>
</head>
<body class="<?= $is_iframe ? 'iframe-mode' : '' ?>">

<?php if (!$is_iframe): ?>
    <?php include("menu.php"); ?>
<?php endif; ?>

<div class="main-container <?= $is_iframe ? 'in-iframe' : '' ?>">
    <div class="page-inner">

        <?php if ($is_iframe): ?>
            <div class="iframe-topbar">
                <div class="iframe-topbar-left">
                    <a href="dashboard_agent.php" target="_top" class="btn-back">← Retour au dashboard</a>
                    <a href="afficheinscription.php?iframe=1" class="btn-nav">📋 Liste</a>
                </div>
            </div>
        <?php endif; ?>

        <div class="page-header">
            <h1>✏️ Modifier l'inscription</h1>
            <?php if (!$is_iframe): ?>
                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <a href="afficheinscription.php" class="btn-cancel" style="flex: none; padding: 10px 18px;">← Retour à la liste</a>
                    <a href="dashboard_agent.php" class="btn-success" style="flex: none; padding: 10px 18px; background: #003366;">🏠 Dashboard</a>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($message): ?>
            <div class="alert <?= $message_type ?>"><?= $message ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="info-box">
                <strong>📋 Inscription actuelle</strong>
                <div class="info-row">👤 Étudiant : <?= htmlspecialchars($inscription['nom'] . ' ' . $inscription['prenom']) ?></div>
                <div class="info-row">🎯 Matricule : <?= htmlspecialchars($inscription['matricule']) ?></div>
                <div class="info-row">🏫 Classe : <?= htmlspecialchars($inscription['niveau']) ?> (<?= htmlspecialchars($inscription['nom_dept']) ?>)</div>
                <div class="info-row">📅 Date : <?= date('d/m/Y', strtotime($inscription['date_inscription'])) ?></div>
            </div>

            <form method="POST">
                <?php if ($is_iframe): ?>
                    <input type="hidden" name="iframe" value="1">
                <?php endif; ?>

                <div class="form-group">
                    <label>Département <span class="req">*</span></label>
                    <select name="dept" onchange="this.form.submit()" required>
                        <option value="0">— Sélectionner —</option>
                        <?php foreach ($departements as $d): ?>
                            <option value="<?= $d['id_dept'] ?>" <?= ($current_dept == $d['id_dept']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($d['nom_dept']) ?> (<?= htmlspecialchars($d['faculte'] ?: 'Sans fac') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Classe <span class="req">*</span></label>
                    <select name="classe" onchange="this.form.submit()" required <?= empty($classes) ? 'disabled' : '' ?>>
                        <?php if (empty($classes)): ?>
                            <option value="0">— Choisir un département d'abord —</option>
                        <?php else: ?>
                            <option value="0">— Sélectionner —</option>
                            <?php foreach ($classes as $c): ?>
                                <option value="<?= $c['id_classe'] ?>" <?= ($current_classe == $c['id_classe']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['niveau']) ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Étudiant <span class="req">*</span></label>
                    <select name="etudiant" required <?= empty($etudiants) ? 'disabled' : '' ?>>
                        <?php if (empty($etudiants)): ?>
                            <option value="0">— Aucun étudiant dans cette classe —</option>
                        <?php else: ?>
                            <option value="0">— Sélectionner un étudiant —</option>
                            <?php foreach ($etudiants as $e): ?>
                                <option value="<?= $e['id_etudiant'] ?>" <?= ($current_etudiant == $e['id_etudiant']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($e['nom'] . ' ' . $e['prenom']) ?> — <?= htmlspecialchars($e['matricule']) ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Date d'inscription <span class="req">*</span></label>
                    <input type="date" name="date_inscription" value="<?= htmlspecialchars($current_date) ?>" required>
                </div>

                <div class="btn-row">
                    <button type="submit" name="modifier" class="btn btn-success">💾 Enregistrer</button>
                    <a href="afficheinscription.php<?= $is_iframe ? '?iframe=1' : '' ?>" class="btn btn-cancel">❌ Annuler</a>
                </div>
            </form>
        </div>

    </div>
</div>

</body>
</html>