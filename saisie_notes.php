<?php
require_once __DIR__ . '/db.php';
require_once 'auth.php';
requireRole('Professeur');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $bdd = getDBConnection();
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

/* ============================================================
   RÉCUPÉRATION DU PROFESSEUR
============================================================ */
$id_user = $USER_ID;

$reqProf = $bdd->prepare("
    SELECT p.id_prof, p.specialite, p.diplome, u.nom, u.prenom, u.photo
    FROM professeurs p 
    INNER JOIN users u ON p.id_user = u.id_user 
    WHERE p.id_user = ?
");
$reqProf->execute([$id_user]);
$prof = $reqProf->fetch(PDO::FETCH_ASSOC);

if (!$prof) {
    die("Profil professeur introuvable. Contactez l'administration.");
}
$id_prof = $prof['id_prof'];

$message = "";
$stats = [];

/* ============================================================
   ENREGISTREMENT DES NOTES (avec vérification propriétaire)
============================================================ */
if (isset($_POST['sauvegarder_notes'])) {
    $id_cours_selectionne = intval($_POST['id_cours']);
    $session_selectionnee = trim($_POST['session']);
    $notes_saisies = $_POST['notes'] ?? [];

    /* ✅ VÉRIFICATION : le cours appartient bien au prof */
    $checkCours = $bdd->prepare("SELECT id_cours FROM cours WHERE id_cours = ? AND id_prof = ?");
    $checkCours->execute([$id_cours_selectionne, $id_prof]);

    if (!$checkCours->fetch()) {
        $message = "<div class='alert error'>❌ Vous n'êtes pas autorisé à saisir des notes pour ce cours.</div>";
    } else {
        $nb_insertions = 0;
        $nb_modifications = 0;

        foreach ($notes_saisies as $id_etudiant => $note_valeur) {
            if ($note_valeur === "") continue;
            $note_valeur = floatval(str_replace(',', '.', $note_valeur));
            if ($note_valeur < 0 || $note_valeur > 20) continue;

            $check = $bdd->prepare("SELECT id_note FROM notes WHERE id_etudiant = ? AND id_cours = ? AND session = ?");
            $check->execute([$id_etudiant, $id_cours_selectionne, $session_selectionnee]);
            $existing = $check->fetch();

            if ($existing) {
                $update = $bdd->prepare("UPDATE notes SET note = ?, date_saisie = CURDATE() WHERE id_note = ?");
                $update->execute([$note_valeur, $existing['id_note']]);
                $nb_modifications++;
            } else {
                $insert = $bdd->prepare("INSERT INTO notes (id_etudiant, id_cours, note, session, date_saisie) VALUES (?, ?, ?, ?, CURDATE())");
                $insert->execute([$id_etudiant, $id_cours_selectionne, $note_valeur, $session_selectionnee]);
                $nb_insertions++;
            }
        }

        $message = "<div class='alert success'>🎉 $nb_insertions note(s) ajoutée(s) et $nb_modifications note(s) mise(s) à jour !</div>";
    }
}

/* ============================================================
   COURS DU PROFESSEUR
============================================================ */
$reqCours = $bdd->prepare("
    SELECT c.id_cours, c.nom_cours, cl.niveau, d.nom_dept, d.faculte, cl.id_classe, d.id_dept
    FROM cours c
    INNER JOIN classes cl ON c.id_classe = cl.id_classe
    INNER JOIN departements d ON cl.id_dept = d.id_dept
    WHERE c.id_prof = ?
    ORDER BY d.nom_dept, cl.niveau, c.nom_cours
");
$reqCours->execute([$id_prof]);
$listeCours = $reqCours->fetchAll(PDO::FETCH_ASSOC);

$id_cours_choisi = isset($_POST['id_cours']) ? intval($_POST['id_cours']) : (isset($_GET['id_cours']) ? intval($_GET['id_cours']) : 0);
$session_choisie = $_POST['session'] ?? $_GET['session'] ?? '1ère Session';
$listeEtudiants = [];

$departements = $bdd->query("SELECT id_dept, nom_dept FROM departements ORDER BY nom_dept")->fetchAll(PDO::FETCH_ASSOC);

$filtre_dept = isset($_GET['filtre_dept']) ? intval($_GET['filtre_dept']) : 0;
$filtre_faculte = isset($_GET['filtre_faculte']) ? trim($_GET['filtre_faculte']) : '';

$facultesFiltrees = [];
if ($filtre_dept > 0) {
    $faculteReq = $bdd->prepare("SELECT faculte FROM departements WHERE id_dept = ?");
    $faculteReq->execute([$filtre_dept]);
    $faculte = $faculteReq->fetchColumn();
    if ($faculte) $facultesFiltrees = [$faculte];
}

$classesFiltrees = [];
if ($filtre_dept > 0) {
    $reqClasses = $bdd->prepare("SELECT id_classe, niveau FROM classes WHERE id_dept = ? ORDER BY niveau");
    $reqClasses->execute([$filtre_dept]);
    $classesFiltrees = $reqClasses->fetchAll(PDO::FETCH_ASSOC);
}

if ($id_cours_choisi > 0) {
    /* ✅ Vérification avant chargement */
    $checkCours = $bdd->prepare("SELECT id_cours FROM cours WHERE id_cours = ? AND id_prof = ?");
    $checkCours->execute([$id_cours_choisi, $id_prof]);

    if ($checkCours->fetch()) {
        $reqEtudiants = $bdd->prepare("
            SELECT e.id_etudiant, e.matricule, u.nom, u.prenom, n.note
            FROM etudiants e
            INNER JOIN users u ON e.id_user = u.id_user
            INNER JOIN classes cl ON e.id_classe = cl.id_classe
            INNER JOIN cours c ON c.id_classe = cl.id_classe
            LEFT JOIN notes n ON n.id_etudiant = e.id_etudiant AND n.id_cours = c.id_cours AND n.session = ?
            WHERE c.id_cours = ?
            ORDER BY u.nom, u.prenom
        ");
        $reqEtudiants->execute([$session_choisie, $id_cours_choisi]);
        $listeEtudiants = $reqEtudiants->fetchAll(PDO::FETCH_ASSOC);

        $notes = array_filter(array_column($listeEtudiants, 'note'), function($n) { return !is_null($n); });
        if (count($notes) > 0) {
            $stats['moyenne'] = round(array_sum($notes) / count($notes), 2);
            $stats['min'] = min($notes);
            $stats['max'] = max($notes);
            $stats['total'] = count($notes);
            $stats['reussite'] = count(array_filter($notes, function($n) { return $n >= 10; }));
            $stats['echec'] = $stats['total'] - $stats['reussite'];
        }
    }
}

$photo_profil = !empty($prof['photo']) && file_exists("uploads/" . $prof['photo']) ? $prof['photo'] : 'default.png';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saisie des Notes — UniVerse</title>
    <style>
        :root {
            --bg-light: #f1f5f9; --sidebar-color: #0f172a; --text-main: #334155;
            --accent-blue: #2563eb; --success: #10b981; --warning: #f59e0b;
            --error: #ef4444; --border-color: #e2e8f0;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
        body { background-color: var(--bg-light); color: var(--text-main); display: flex; min-height: 100vh; }
        .sidebar { width: 260px; background-color: var(--sidebar-color); color: white; padding: 30px 20px; position: fixed; height: 100vh; display: flex; flex-direction: column; }
        .sidebar .logo { font-size: 24px; font-weight: 800; margin-bottom: 25px; }
        .user-profile { background: rgba(255,255,255,0.05); padding: 15px; border-radius: 12px; margin-bottom: 25px; text-align: center; }
        .profile-img { width: 60px; height: 60px; border-radius: 50%; object-fit: cover; border: 3px solid var(--accent-blue); margin-bottom: 8px; }
        .user-profile .name { font-weight: 600; font-size: 14px; }
        .user-profile .title { font-size: 11px; color: #94a3b8; }
        .badge { display: inline-block; padding: 4px 8px; font-size: 11px; font-weight: 700; border-radius: 6px; margin-top: 6px; background: #8b5cf6; }
        .nav-links { list-style: none; display: flex; flex-direction: column; gap: 8px; flex: 1; }
        .nav-links a { color: #94a3b8; text-decoration: none; padding: 12px; border-radius: 8px; display: block; font-weight: 600; transition: all 0.2s; }
        .nav-links a:hover, .nav-links a.active { background: rgba(255,255,255,0.1); color: white; }
        .nav-links a.logout { color: var(--error); margin-top: auto; }
        .main-content { margin-left: 260px; width: calc(100% - 260px); padding: 40px; }
        header { margin-bottom: 30px; border-bottom: 1px solid var(--border-color); padding-bottom: 15px; display: flex; justify-content: space-between; align-items: center; }
        header h1 { font-size: 26px; color: #1e293b; }
        .info-badge { background: white; border: 1px solid var(--border-color); padding: 8px 16px; border-radius: 8px; font-weight: 600; }
        .panel { background: white; padding: 30px; border-radius: 16px; border: 1px solid var(--border-color); margin-bottom: 25px; }
        .panel h2 { margin-bottom: 20px; font-size: 18px; color: #1e293b; }
        .filter-form { display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap; }
        .filter-form .form-group { display: flex; flex-direction: column; gap: 6px; }
        .filter-form .form-group label { font-size: 13px; font-weight: 600; color: #64748b; text-transform: uppercase; }
        .filter-form select { padding: 10px 16px; border-radius: 8px; border: 1px solid var(--border-color); font-size: 14px; background: white; min-width: 180px; }
        .filter-form select:focus { outline: none; border-color: var(--accent-blue); box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); }
        .filter-form select:disabled { background: #f1f5f9; cursor: not-allowed; opacity: 0.7; }
        .btn { display: inline-block; background: var(--accent-blue); color: white; padding: 10px 24px; border-radius: 8px; font-weight: 600; border: none; cursor: pointer; text-decoration: none; font-size: 14px; transition: all 0.2s; }
        .btn:hover { background: #1d4ed8; transform: translateY(-2px); }
        .btn-success { background: var(--success); }
        .btn-success:hover { background: #059669; }
        .btn-reset { background: #64748b; }
        .btn-reset:hover { background: #475569; }
        .faculte-badge { display: inline-block; padding: 2px 10px; border-radius: 12px; background: #eff6ff; color: #1e40af; font-weight: 600; font-size: 11px; }
        .stats-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .stat-card { background: #f8fafc; padding: 12px 15px; border-radius: 10px; text-align: center; border: 1px solid var(--border-color); }
        .stat-card .number { font-size: 22px; font-weight: 700; }
        .stat-card .label { font-size: 11px; color: #64748b; font-weight: 600; text-transform: uppercase; }
        .stat-card .number.success { color: var(--success); }
        .stat-card .number.warning { color: var(--warning); }
        .stat-card .number.error { color: var(--error); }
        table { width: 100%; border-collapse: collapse; text-align: left; margin-top: 15px; }
        th { background: #f8fafc; color: #1e293b; padding: 12px 14px; font-weight: 600; border-bottom: 2px solid var(--border-color); font-size: 13px; }
        td { padding: 12px 14px; border-bottom: 1px solid var(--border-color); color: #475569; font-size: 14px; }
        tr:hover { background: #f8fafc; }
        .input-note { width: 100px; padding: 8px 12px; text-align: center; font-weight: bold; color: #1e293b; border: 1px solid var(--border-color); border-radius: 6px; transition: all 0.2s; }
        .input-note:focus { outline: none; border-color: var(--accent-blue); box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); }
        .input-note.has-note { border-color: var(--success); background: #f0fdf4; }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; }
        .alert.success { background: #d1fae5; color: #065f46; }
        .alert.error { background: #fee2e2; color: #991b1b; }
        .flex-between { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
        .stats-inline { display: flex; gap: 20px; font-size: 14px; color: #475569; }
        .stats-inline span { font-weight: 700; color: #1e293b; }
        @media (max-width: 768px) { body { flex-direction: column; } .sidebar { width: 100%; height: auto; position: relative; padding: 20px; } .main-content { margin-left: 0; width: 100%; padding: 20px; } header { flex-direction: column; align-items: flex-start; gap: 10px; } .filter-form { flex-direction: column; align-items: stretch; } .filter-form select { min-width: unset; } .stats-cards { grid-template-columns: repeat(2, 1fr); } }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="logo">🎓 UniVerse</div>
        <div class="user-profile">
            <img src="uploads/<?= htmlspecialchars($photo_profil); ?>" alt="Photo" class="profile-img">
            <p class="name">Prof. <?= htmlspecialchars($prof['nom'] . ' ' . $prof['prenom']); ?></p>
            <p class="title"><?= htmlspecialchars($prof['specialite'] ?? 'Enseignant'); ?></p>
            <span class="badge">Professeur</span>
        </div>
        <ul class="nav-links">
            <li><a href="dashboard_professeur.php">📊 Vue d'ensemble</a></li>
            <li><a href="saisie_notes.php" class="active">✍️ Saisie des Notes</a></li>
            <li><a href="emploi_temps_prof.php">📚 Mes Emplois du Temps</a></li>
            <li><a href="mon_profil.php">👤 Mon Profil</a></li>
            <li><a href="logout.php" class="logout">🚪 Déconnexion</a></li>
        </ul>
    </div>

    <div class="main-content">
        <header>
            <h1>Gestion des Évaluations</h1>
            <div class="info-badge">Saisie des notes</div>
        </header>

        <?= $message; ?>

        <div class="panel">
            <h2>Filtres de recherche</h2>
            <form method="GET" action="" class="filter-form">
                <div class="form-group">
                    <label for="filtre_dept">Département</label>
                    <select name="filtre_dept" id="filtre_dept" onchange="this.form.submit()">
                        <option value="0">-- Tous --</option>
                        <?php foreach ($departements as $d): ?>
                            <option value="<?= $d['id_dept']; ?>" <?= $filtre_dept == $d['id_dept'] ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($d['nom_dept']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="filtre_faculte">Faculté</label>
                    <select name="filtre_faculte" id="filtre_faculte" <?= empty($filtre_dept) ? 'disabled' : ''; ?>>
                        <option value="">-- Toutes --</option>
                        <?php foreach ($facultesFiltrees as $f): ?>
                            <option value="<?= $f; ?>" <?= $filtre_faculte == $f ? 'selected' : ''; ?>><?= $f; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="filtre_classe">Classe</label>
                    <select name="filtre_classe" id="filtre_classe">
                        <option value="0">-- Toutes --</option>
                        <?php foreach ($classesFiltrees as $cl): ?>
                            <option value="<?= $cl['id_classe']; ?>" <?= (isset($_GET['filtre_classe']) && $_GET['filtre_classe'] == $cl['id_classe']) ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($cl['niveau']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="id_cours">Cours</label>
                    <select name="id_cours" id="id_cours">
                        <option value="0">-- Choisir un cours --</option>
                        <?php
                        $filtre_classe = isset($_GET['filtre_classe']) ? intval($_GET['filtre_classe']) : 0;
                        foreach ($listeCours as $cours):
                            if ($filtre_classe > 0 && $cours['id_classe'] != $filtre_classe) continue;
                            if ($filtre_dept > 0 && $cours['id_dept'] != $filtre_dept) continue;
                        ?>
                            <option value="<?= $cours['id_cours']; ?>" <?= $id_cours_choisi == $cours['id_cours'] ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($cours['nom_cours'] . ' (' . $cours['niveau'] . ' - ' . $cours['nom_dept'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="session">Session</label>
                    <select name="session" id="session">
                        <option value="1ère Session" <?= $session_choisie == '1ère Session' ? 'selected' : ''; ?>>1ère Session</option>
                        <option value="2ème Session" <?= $session_choisie == '2ème Session' ? 'selected' : ''; ?>>2ème Session</option>
                    </select>
                </div>
                <button type="submit" class="btn">🔍 Charger</button>
                <a href="saisie_notes.php" class="btn btn-reset">✖️ Réinitialiser</a>
            </form>
        </div>

        <?php if ($id_cours_choisi > 0 && count($listeEtudiants) > 0 && !empty($stats)): ?>
            <div class="stats-cards">
                <div class="stat-card">
                    <div class="number"><?= $stats['total']; ?></div>
                    <div class="label">Étudiants</div>
                </div>
                <div class="stat-card">
                    <div class="number <?= $stats['moyenne'] >= 10 ? 'success' : 'error'; ?>">
                        <?= number_format($stats['moyenne'], 2); ?>
                    </div>
                    <div class="label">Moyenne</div>
                </div>
                <div class="stat-card">
                    <div class="number success"><?= $stats['reussite']; ?></div>
                    <div class="label">Réussite</div>
                </div>
                <div class="stat-card">
                    <div class="number error"><?= $stats['echec']; ?></div>
                    <div class="label">Échec</div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($id_cours_choisi > 0): ?>
            <div class="panel">
                <div class="flex-between">
                    <h2>📋 Liste des étudiants</h2>
                    <?php if (count($listeEtudiants) > 0): ?>
                        <div class="stats-inline">
                            <span>👨‍🎓 Total : <?= count($listeEtudiants); ?></span>
                            <span>📊 Session : <?= htmlspecialchars($session_choisie); ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <form method="POST" action="">
                    <input type="hidden" name="id_cours" value="<?= htmlspecialchars($id_cours_choisi); ?>">
                    <input type="hidden" name="session" value="<?= htmlspecialchars($session_choisie); ?>">

                    <table>
                        <thead>
                            <tr>
                                <th>Matricule</th>
                                <th>Nom</th>
                                <th>Prénom</th>
                                <th style="text-align: center; width: 120px;">Note (/20)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($listeEtudiants) > 0): ?>
                                <?php foreach ($listeEtudiants as $etudiant):
                                    $hasNote = !is_null($etudiant['note']);
                                ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($etudiant['matricule']); ?></strong></td>
                                        <td><?= htmlspecialchars($etudiant['nom']); ?></td>
                                        <td><?= htmlspecialchars($etudiant['prenom']); ?></td>
                                        <td style="text-align: center;">
                                            <input type="number" 
                                                   name="notes[<?= $etudiant['id_etudiant']; ?>]" 
                                                   class="input-note <?= $hasNote ? 'has-note' : ''; ?>" 
                                                   step="0.25" min="0" max="20" placeholder="--" 
                                                   value="<?= $hasNote ? htmlspecialchars($etudiant['note']) : ''; ?>">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="4" style="text-align:center; padding:30px; color:#64748b;">📭 Aucun étudiant inscrit dans ce cours.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <?php if (count($listeEtudiants) > 0): ?>
                        <br>
                        <button type="submit" name="sauvegarder_notes" class="btn btn-success">
                            💾 Sauvegarder toutes les notes
                        </button>
                    <?php endif; ?>
                </form>
            </div>
        <?php else: ?>
            <div class="panel">
                <div style="text-align: center; padding: 20px; color: #94a3b8;">
                    💡 Sélectionnez un cours et une session pour commencer la saisie des notes.
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>