<?php
require_once 'auth.php';
requireRole('Professeur');
session_start();
require_once 'dashboard_professeur.php';

// Enregistrer une présence
if (isset($_POST['enregistrer_presences'])) {
    $id_cours = intval($_POST['id_cours']);
    $date_seance = $_POST['date_seance'];
    $presences = $_POST['presence'] ?? [];

    if ($id_cours > 0 && !empty($date_seance)) {
        foreach ($presences as $id_etudiant => $statut) {
            $stmt = $bdd->prepare("INSERT INTO presences (id_etudiant, id_cours, date_seance, statut) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE statut = ?");
            $stmt->execute([$id_etudiant, $id_cours, $date_seance, $statut, $statut]);
        }
        $success = "✅ Présences enregistrées avec succès !";
    } else {
        $error = "❌ Veuillez sélectionner un cours et une date.";
    }
}

// Récupération des cours du professeur
$coursList = $bdd->prepare("SELECT id_cours, nom_cours FROM cours WHERE id_prof = ?");
$coursList->execute([$id_prof]);

// Si un cours est sélectionné, récupérer les étudiants
$id_cours_selected = isset($_GET['id_cours']) ? intval($_GET['id_cours']) : 0;
$date_selected = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$etudiants = [];

if ($id_cours_selected > 0) {
    $etudiants = $bdd->prepare("
        SELECT e.id_etudiant, u.nom, u.prenom, e.matricule,
               (SELECT statut FROM presences WHERE id_etudiant = e.id_etudiant AND id_cours = ? AND date_seance = ?) as statut_presence
        FROM etudiants e
        JOIN users u ON e.id_user = u.id_user
        JOIN classes c ON e.id_classe = c.id_classe
        JOIN cours co ON co.id_classe = c.id_classe
        WHERE co.id_cours = ?
        ORDER BY u.nom
    ");
    $etudiants->execute([$id_cours_selected, $date_selected, $id_cours_selected]);
}

// Statistiques de présence
$statistiques = [];
if ($id_cours_selected > 0) {
    $stats = $bdd->prepare("
        SELECT 
            COUNT(CASE WHEN statut = 'present' THEN 1 END) as presents,
            COUNT(CASE WHEN statut = 'absent' THEN 1 END) as absents,
            COUNT(CASE WHEN statut = 'retard' THEN 1 END) as retards,
            COUNT(CASE WHEN statut = 'justifie' THEN 1 END) as justifies,
            COUNT(*) as total
        FROM presences
        WHERE id_cours = ? AND date_seance = ?
    ");
    $stats->execute([$id_cours_selected, $date_selected]);
    $statistiques = $stats->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Présences — UniVerse</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f1f5f9; padding: 30px; }
        .container { max-width: 1000px; margin: 0 auto; }
        .card { background: white; padding: 25px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); border: 1px solid #e2e8f0; }
        .card h2 { margin-bottom: 15px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { font-weight: 600; display: block; margin-bottom: 5px; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #e2e8f0; }
        .btn { padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; }
        .btn-primary { background: #2563eb; color: white; }
        .btn-success { background: #10b981; color: white; }
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 20px; }
        .stat-card { text-align: center; padding: 15px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0; }
        .stat-card .number { font-size: 24px; font-weight: 700; }
        .stat-card .label { font-size: 12px; color: #64748b; }
        .stat-card.green .number { color: #10b981; }
        .stat-card.red .number { color: #ef4444; }
        .stat-card.orange .number { color: #f59e0b; }
        .stat-card.blue .number { color: #2563eb; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f8fafc; padding: 10px; text-align: left; border-bottom: 2px solid #e2e8f0; }
        td { padding: 10px; border-bottom: 1px solid #e2e8f0; }
        select.presence-select { padding: 5px; border-radius: 6px; border: 1px solid #e2e8f0; background: white; }
        .alert { padding: 12px; border-radius: 8px; margin-bottom: 15px; }
        .alert-success { background: #d1fae5; color: #065f46; }
        .alert-danger { background: #fee2e2; color: #991b1b; }
        .filter-bar { display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap; }
    </style>
</head>
<body>
<div class="container">
    <h1><i class="fas fa-clipboard-check"></i> Gestion des présences</h1>

    <?php if (isset($success)): ?><div class="alert alert-success"><?= $success; ?></div><?php endif; ?>
    <?php if (isset($error)): ?><div class="alert alert-danger"><?= $error; ?></div><?php endif; ?>

    <!-- Filtres -->
    <div class="card">
        <h2>🔍 Sélectionner un cours</h2>
        <form method="GET" class="filter-bar">
            <div class="form-group">
                <label>Cours</label>
                <select name="id_cours" required>
                    <option value="">— Choisir —</option>
                    <?php while ($c = $coursList->fetch()): ?>
                        <option value="<?= $c['id_cours']; ?>" <?= ($id_cours_selected == $c['id_cours']) ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($c['nom_cours']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Date</label>
                <input type="date" name="date" value="<?= $date_selected; ?>" required>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Charger</button>
        </form>
    </div>

    <?php if ($id_cours_selected > 0): ?>
        <!-- Statistiques -->
        <div class="stats-grid">
            <div class="stat-card green">
                <div class="number"><?= $statistiques['presents'] ?? 0; ?></div>
                <div class="label">✅ Présents</div>
            </div>
            <div class="stat-card red">
                <div class="number"><?= $statistiques['absents'] ?? 0; ?></div>
                <div class="label">❌ Absents</div>
            </div>
            <div class="stat-card orange">
                <div class="number"><?= $statistiques['retards'] ?? 0; ?></div>
                <div class="label">⏳ Retards</div>
            </div>
            <div class="stat-card blue">
                <div class="number"><?= $statistiques['justifies'] ?? 0; ?></div>
                <div class="label">📋 Justifiés</div>
            </div>
        </div>

        <!-- Formulaire de saisie -->
        <div class="card">
            <h2>📝 Saisie des présences</h2>
            <form method="POST">
                <input type="hidden" name="id_cours" value="<?= $id_cours_selected; ?>">
                <input type="hidden" name="date_seance" value="<?= $date_selected; ?>">
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Matricule</th>
                                <th>Nom</th>
                                <th>Prénom</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($e = $etudiants->fetch()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($e['matricule']); ?></td>
                                    <td><?= htmlspecialchars($e['nom']); ?></td>
                                    <td><?= htmlspecialchars($e['prenom']); ?></td>
                                    <td>
                                        <select name="presence[<?= $e['id_etudiant']; ?>]" class="presence-select">
                                            <option value="present" <?= ($e['statut_presence'] == 'present') ? 'selected' : ''; ?>>✅ Présent</option>
                                            <option value="absent" <?= ($e['statut_presence'] == 'absent') ? 'selected' : ''; ?>>❌ Absent</option>
                                            <option value="retard" <?= ($e['statut_presence'] == 'retard') ? 'selected' : ''; ?>>⏳ Retard</option>
                                            <option value="justifie" <?= ($e['statut_presence'] == 'justifie') ? 'selected' : ''; ?>>📋 Justifié</option>
                                        </select>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <button type="submit" name="enregistrer_presences" class="btn btn-success" style="margin-top:15px;"><i class="fas fa-save"></i> Enregistrer les présences</button>
            </form>
        </div>
    <?php endif; ?>
</div>
</body>
</html>