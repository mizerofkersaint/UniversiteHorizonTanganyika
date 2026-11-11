<?php
require_once __DIR__ . '/db.php';
require_once 'auth.php';
requireRole(['Doyen', 'Administrateur']);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $bdd = getDBConnection();
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: dashboard_doyen.php');
    exit;
}

$id_horaire = intval($_GET['id']);

/* ============================================================
   RÉCUPÉRATION DE L'HORAIRE
============================================================ */
$reqHoraire = $bdd->prepare("
    SELECT h.*, c.nom_cours, c.id_prof, cl.id_classe, cl.niveau, d.nom_dept, d.faculte, d.id_dept
    FROM horaires h
    LEFT JOIN cours c ON h.id_cours = c.id_cours
    LEFT JOIN classes cl ON h.id_classe = cl.id_classe
    LEFT JOIN departements d ON cl.id_dept = d.id_dept
    WHERE h.id_horaire = ?
");
$reqHoraire->execute([$id_horaire]);
$horaire = $reqHoraire->fetch(PDO::FETCH_ASSOC);

if (!$horaire) {
    header('Location: dashboard_doyen.php');
    exit;
}

/* ============================================================
   VÉRIFICATION : le doyen ne peut modifier que son département
============================================================ */
if ($USER_ROLE === 'Doyen') {
    $stmtDept = $bdd->prepare("SELECT id_dept FROM doyens WHERE id_user = ?");
    $stmtDept->execute([$USER_ID]);
    $id_dept_doyen = $stmtDept->fetchColumn();

    if (!$id_dept_doyen || (int)$id_dept_doyen !== (int)$horaire['id_dept']) {
        http_response_code(403);
        die("⛔ Vous n'êtes pas autorisé à modifier cet horaire (hors de votre département).");
    }
}

/* ============================================================
   DONNÉES POUR LE FORMULAIRE
============================================================ */
$listeCours = $bdd->query("SELECT id_cours, nom_cours FROM cours ORDER BY nom_cours")->fetchAll(PDO::FETCH_ASSOC);
$listeClasses = $bdd->query("
    SELECT c.id_classe, c.niveau, d.nom_dept, d.faculte 
    FROM classes c 
    JOIN departements d ON c.id_dept = d.id_dept 
    ORDER BY d.nom_dept, c.niveau
")->fetchAll(PDO::FETCH_ASSOC);

$success_msg = "";
$error_msg = "";

/* ============================================================
   TRAITEMENT DU POST
============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['modifier_horaire'])) {
    $id_cours     = intval($_POST['id_cours']);
    $id_classe    = intval($_POST['id_classe']);
    $jour         = trim($_POST['jour']);
    $heure_debut  = trim($_POST['heure_debut']);
    $heure_fin    = trim($_POST['heure_fin']);
    $salle        = trim($_POST['salle']);
    $type_cours   = trim($_POST['type_cours']);
    $semaine      = intval($_POST['semaine']);

    if (!empty($id_cours) && !empty($id_classe) && !empty($jour) && !empty($heure_debut) && !empty($heure_fin)) {
        try {
            $reqProf = $bdd->prepare("SELECT id_prof FROM cours WHERE id_cours = ?");
            $reqProf->execute([$id_cours]);
            $id_prof = $reqProf->fetchColumn() ?: null;

            $bdd->beginTransaction();

            $req = $bdd->prepare("
                UPDATE horaires 
                SET id_cours = ?, id_classe = ?, id_prof = ?, jour = ?, heure_debut = ?, heure_fin = ?, salle = ?, type_cours = ?, semaine = ?
                WHERE id_horaire = ?
            ");
            $req->execute([$id_cours, $id_classe, $id_prof, $jour, $heure_debut, $heure_fin, $salle, $type_cours, $semaine, $id_horaire]);

            $bdd->commit();
            $success_msg = "✅ L'horaire a été modifié avec succès !";

            $reqHoraire = $bdd->prepare("
                SELECT h.*, c.nom_cours, c.id_prof, cl.id_classe, cl.niveau, d.nom_dept, d.faculte
                FROM horaires h
                LEFT JOIN cours c ON h.id_cours = c.id_cours
                LEFT JOIN classes cl ON h.id_classe = cl.id_classe
                LEFT JOIN departements d ON cl.id_dept = d.id_dept
                WHERE h.id_horaire = ?
            ");
            $reqHoraire->execute([$id_horaire]);
            $horaire = $reqHoraire->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $bdd->rollBack();
            $error_msg = "Erreur lors de la modification : " . $e->getMessage();
        }
    } else {
        $error_msg = "Veuillez remplir tous les champs obligatoires.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier Horaire — UniVerse</title>
    <style>
        :root {
            --bg-primary: #f0f4f8; --bg-card: #ffffff;
            --text-primary: #1a2332; --text-secondary: #4a5568;
            --accent-primary: #2563eb; --accent-success: #10b981; --accent-danger: #ef4444;
            --border-color: #e2e8f0; --radius: 12px;
            --shadow-md: 0 4px 12px rgba(0,0,0,0.1);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', system-ui, sans-serif; }
        body { background: var(--bg-primary); color: var(--text-primary); min-height: 100vh; display: flex; justify-content: center; align-items: center; padding: 20px; }
        .container { width: 100%; max-width: 850px; margin: 0 auto; }
        .card { background: var(--bg-card); padding: 40px; border-radius: var(--radius); box-shadow: var(--shadow-md); border: 1px solid var(--border-color); }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 2px solid var(--border-color); }
        .card-header h1 { font-size: 24px; font-weight: 700; }
        .card-header a { color: var(--accent-primary); text-decoration: none; font-weight: 600; }
        .alert { padding: 14px 18px; border-radius: var(--radius); margin-bottom: 20px; font-weight: 500; }
        .alert.success { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
        .alert.error { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-group.full-width { grid-column: 1 / -1; }
        .form-group label { font-size: 13px; font-weight: 600; color: var(--text-secondary); }
        .form-group label .required { color: var(--accent-danger); }
        .form-control { padding: 10px 14px; border: 1.5px solid var(--border-color); border-radius: var(--radius); font-size: 14px; background: var(--bg-primary); transition: all 0.2s; color: var(--text-primary); }
        .form-control:focus { outline: none; border-color: var(--accent-primary); box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); background: var(--bg-card); }
        .info-display { background: #f8fafc; padding: 12px 16px; border-radius: var(--radius); border: 1px solid var(--border-color); font-size: 14px; }
        .faculte-badge { display: inline-block; padding: 2px 10px; border-radius: 12px; background: #eff6ff; color: #1e40af; font-weight: 600; font-size: 11px; }
        .btn-group { display: flex; gap: 15px; margin-top: 25px; padding-top: 20px; border-top: 1px solid var(--border-color); }
        .btn-primary { display: inline-flex; align-items: center; gap: 8px; background: linear-gradient(135deg, var(--accent-primary), #3b82f6); color: white; padding: 12px 28px; border-radius: var(--radius); font-weight: 600; border: none; cursor: pointer; font-size: 14px; transition: all 0.2s; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3); }
        .btn-secondary { display: inline-flex; align-items: center; gap: 8px; background: #64748b; color: white; text-decoration: none; padding: 12px 28px; border-radius: var(--radius); font-weight: 600; font-size: 14px; }
        .btn-secondary:hover { background: #475569; transform: translateY(-2px); }
        @media (max-width: 700px) { .form-grid { grid-template-columns: 1fr; } .form-group.full-width { grid-column: 1; } .card { padding: 25px; } .btn-group { flex-direction: column; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h1>✏️ Modifier l'horaire</h1>
                <a href="dashboard_doyen.php">⬅️ Retour</a>
            </div>

            <?php if (!empty($success_msg)): ?>
                <div class="alert success"><?= $success_msg; ?></div>
            <?php endif; ?>
            <?php if (!empty($error_msg)): ?>
                <div class="alert error"><?= $error_msg; ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Cours actuel</label>
                        <div class="info-display"><strong><?= htmlspecialchars($horaire['nom_cours'] ?? 'N/A'); ?></strong></div>
                    </div>
                    <div class="form-group">
                        <label>Classe actuelle</label>
                        <div class="info-display">
                            <strong><?= htmlspecialchars($horaire['niveau'] ?? 'N/A'); ?></strong>
                            <span style="color: var(--text-secondary); font-size: 13px;">(<?= htmlspecialchars($horaire['nom_dept'] ?? 'N/A'); ?>)</span>
                        </div>
                    </div>
                    <div class="form-group full-width">
                        <label>Faculté actuelle</label>
                        <div class="info-display"><span class="faculte-badge"><?= htmlspecialchars($horaire['faculte'] ?? 'Non assignée'); ?></span></div>
                    </div>
                    <div class="form-group">
                        <label>Nouveau cours <span class="required">*</span></label>
                        <select name="id_cours" class="form-control" required>
                            <?php foreach ($listeCours as $c): ?>
                                <option value="<?= $c['id_cours']; ?>" <?= ($horaire['id_cours'] == $c['id_cours']) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($c['nom_cours']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Nouvelle classe <span class="required">*</span></label>
                        <select name="id_classe" class="form-control" required>
                            <?php foreach ($listeClasses as $cl): ?>
                                <option value="<?= $cl['id_classe']; ?>" <?= ($horaire['id_classe'] == $cl['id_classe']) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($cl['niveau']); ?> (<?= htmlspecialchars($cl['nom_dept']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Jour <span class="required">*</span></label>
                        <select name="jour" class="form-control" required>
                            <?php foreach (['Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'] as $j): ?>
                                <option value="<?= $j; ?>" <?= ($horaire['jour'] == $j) ? 'selected' : ''; ?>><?= $j; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Semaine</label>
                        <input type="number" name="semaine" value="<?= $horaire['semaine'] ?? 1; ?>" min="1" max="15" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Heure début <span class="required">*</span></label>
                        <input type="time" name="heure_debut" value="<?= $horaire['heure_debut'] ?? ''; ?>" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Heure fin <span class="required">*</span></label>
                        <input type="time" name="heure_fin" value="<?= $horaire['heure_fin'] ?? ''; ?>" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Type de cours</label>
                        <select name="type_cours" class="form-control">
                            <option value="CM" <?= ($horaire['type_cours'] == 'CM') ? 'selected' : ''; ?>>CM (Cours Magistral)</option>
                            <option value="TD" <?= ($horaire['type_cours'] == 'TD') ? 'selected' : ''; ?>>TD (Travaux Dirigés)</option>
                            <option value="TP" <?= ($horaire['type_cours'] == 'TP') ? 'selected' : ''; ?>>TP (Travaux Pratiques)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Salle / Amphi</label>
                        <input type="text" name="salle" value="<?= htmlspecialchars($horaire['salle'] ?? ''); ?>" placeholder="Ex: Amphi A, Salle 101..." class="form-control">
                    </div>
                </div>
                <div class="btn-group">
                    <button type="submit" name="modifier_horaire" class="btn-primary">💾 Enregistrer les modifications</button>
                    <a href="dashboard_doyen.php" class="btn-secondary">❌ Annuler</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>