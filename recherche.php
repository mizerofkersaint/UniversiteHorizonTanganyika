<?php
require_once __DIR__ . '/db.php';
require_once 'auth.php';
requireRole('Professeur');
session_start();

// Connexion BDD (reprise du code du dashboard)
try {
    $bdd = getDBConnection();
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// Récupération du professeur (ou fictif)
$id_user = $_SESSION['id_user'] ?? 0;
$prof = null;
if ($id_user > 0) {
    $reqProf = $bdd->prepare("SELECT id_prof FROM professeurs WHERE id_user = ?");
    $reqProf->execute([$id_user]);
    $prof = $reqProf->fetch(PDO::FETCH_ASSOC);
}
if (!$prof) {
    // Fictif pour développement
    $prof = ['id_prof' => 1];
}
$id_prof = $prof['id_prof'];

$results = [];
$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$type = isset($_GET['type']) ? $_GET['type'] : 'tous';

if (!empty($search)) {
    // Recherche des étudiants (uniquement ceux des classes du prof)
    if ($type == 'tous' || $type == 'etudiants') {
        $sql = "SELECT DISTINCT u.nom, u.prenom, e.matricule, c.niveau, d.nom_dept, u.email, u.telephone, 'etudiant' as type_result
                FROM etudiants e
                JOIN users u ON e.id_user = u.id_user
                JOIN classes c ON e.id_classe = c.id_classe
                JOIN departements d ON c.id_dept = d.id_dept
                JOIN cours co ON co.id_classe = c.id_classe
                WHERE co.id_prof = :id_prof
                AND (u.nom LIKE :search OR u.prenom LIKE :search OR e.matricule LIKE :search)
                LIMIT 20";
        $stmt = $bdd->prepare($sql);
        $stmt->execute(['id_prof' => $id_prof, 'search' => "%$search%"]);
        $results = array_merge($results, $stmt->fetchAll());
    }

    // Recherche des cours du prof
    if ($type == 'tous' || $type == 'cours') {
        $sql = "SELECT c.nom_cours, c.semestre, c.credit, cl.niveau, d.nom_dept, 'cours' as type_result
                FROM cours c
                JOIN classes cl ON c.id_classe = cl.id_classe
                JOIN departements d ON cl.id_dept = d.id_dept
                WHERE c.id_prof = :id_prof
                AND (c.nom_cours LIKE :search OR cl.niveau LIKE :search OR d.nom_dept LIKE :search)
                LIMIT 20";
        $stmt = $bdd->prepare($sql);
        $stmt->execute(['id_prof' => $id_prof, 'search' => "%$search%"]);
        $results = array_merge($results, $stmt->fetchAll());
    }

    // Recherche des classes du prof
    if ($type == 'tous' || $type == 'classes') {
        $sql = "SELECT DISTINCT cl.niveau, d.nom_dept, 'classe' as type_result
                FROM classes cl
                JOIN departements d ON cl.id_dept = d.id_dept
                JOIN cours co ON co.id_classe = cl.id_classe
                WHERE co.id_prof = :id_prof
                AND (cl.niveau LIKE :search OR d.nom_dept LIKE :search)
                LIMIT 20";
        $stmt = $bdd->prepare($sql);
        $stmt->execute(['id_prof' => $id_prof, 'search' => "%$search%"]);
        $results = array_merge($results, $stmt->fetchAll());
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recherche — UniVerse</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f1f5f9; padding: 30px; }
        .container { max-width: 1000px; margin: 0 auto; }
        .card { background: white; padding: 25px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); border: 1px solid #e2e8f0; }
        .card h2 { margin-bottom: 15px; }
        .search-form { display: flex; gap: 15px; align-items: center; flex-wrap: wrap; }
        .search-form input[type="text"] { flex: 1; padding: 12px; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 16px; min-width: 250px; }
        .search-form select, .search-form button { padding: 12px 20px; border-radius: 8px; border: 1px solid #e2e8f0; background: white; font-weight: 600; }
        .search-form button { background: #2563eb; color: white; border: none; cursor: pointer; transition: background 0.2s; }
        .search-form button:hover { background: #1d4ed8; }
        .result-item { border-bottom: 1px solid #e2e8f0; padding: 15px 0; }
        .result-item:last-child { border-bottom: none; }
        .badge-type { display: inline-block; padding: 2px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; margin-right: 8px; }
        .badge-etudiant { background: #dbeafe; color: #1e40af; }
        .badge-cours { background: #d1fae5; color: #065f46; }
        .badge-classe { background: #fef3c7; color: #92400e; }
        .no-results { text-align: center; color: #94a3b8; padding: 30px; }
        .no-results i { font-size: 32px; display: block; margin-bottom: 10px; }
        .header-actions { display: flex; gap: 10px; align-items: center; }
        .btn { padding: 8px 16px; border-radius: 8px; border: none; font-weight: 600; cursor: pointer; transition: 0.2s; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
        .btn-primary { background: #2563eb; color: white; }
        .btn-primary:hover { background: #1d4ed8; transform: translateY(-2px); }
        .btn-secondary { background: #e2e8f0; color: #475569; }
        .btn-secondary:hover { background: #cbd5e1; }
    </style>
</head>
<body>
<div class="container">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <h1><i class="fas fa-search"></i> Recherche intelligente</h1>
        <a href="dashboard_professeur.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Retour</a>
    </div>

    <div class="card">
        <form method="GET" class="search-form">
            <input type="text" name="q" placeholder="🔍 Nom, prénom, matricule, cours, classe..." value="<?= htmlspecialchars($search); ?>">
            <select name="type">
                <option value="tous" <?= $type == 'tous' ? 'selected' : ''; ?>>Tous</option>
                <option value="etudiants" <?= $type == 'etudiants' ? 'selected' : ''; ?>>Étudiants</option>
                <option value="cours" <?= $type == 'cours' ? 'selected' : ''; ?>>Cours</option>
                <option value="classes" <?= $type == 'classes' ? 'selected' : ''; ?>>Classes</option>
            </select>
            <button type="submit"><i class="fas fa-search"></i> Rechercher</button>
            <?php if (!empty($search)): ?>
                <a href="recherche.php" class="btn btn-secondary"><i class="fas fa-times"></i> Réinitialiser</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if (!empty($search)): ?>
        <div class="card">
            <h2>Résultats pour "<?= htmlspecialchars($search); ?>" (<?= count($results); ?>)</h2>
            <?php if (count($results) > 0): ?>
                <?php foreach ($results as $r): ?>
                    <div class="result-item">
                        <?php if ($r['type_result'] == 'etudiant'): ?>
                            <span class="badge-type badge-etudiant"><i class="fas fa-user-graduate"></i> Étudiant</span>
                            <strong><?= htmlspecialchars($r['nom'] . ' ' . $r['prenom']); ?></strong>
                            <span style="color:#64748b;">(<?= htmlspecialchars($r['matricule']); ?>)</span>
                            <div><?= htmlspecialchars($r['niveau']); ?> - <?= htmlspecialchars($r['nom_dept']); ?></div>
                            <div style="font-size:13px; color:#64748b;">📧 <?= htmlspecialchars($r['email']); ?> | 📱 <?= htmlspecialchars($r['telephone']); ?></div>
                        <?php elseif ($r['type_result'] == 'cours'): ?>
                            <span class="badge-type badge-cours"><i class="fas fa-book"></i> Cours</span>
                            <strong><?= htmlspecialchars($r['nom_cours']); ?></strong>
                            <div><?= htmlspecialchars($r['niveau']); ?> - <?= htmlspecialchars($r['nom_dept']); ?> | Semestre <?= htmlspecialchars($r['semestre']); ?> | Crédits: <?= $r['credit']; ?></div>
                        <?php elseif ($r['type_result'] == 'classe'): ?>
                            <span class="badge-type badge-classe"><i class="fas fa-school"></i> Classe</span>
                            <strong><?= htmlspecialchars($r['niveau']); ?></strong>
                            <div><?= htmlspecialchars($r['nom_dept']); ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-results">
                    <i class="fas fa-search"></i>
                    Aucun résultat trouvé pour "<strong><?= htmlspecialchars($search); ?></strong>".
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="card" style="text-align:center; color:#94a3b8;">
            <i class="fas fa-search" style="font-size:48px; display:block; margin-bottom:15px;"></i>
            <p>Saisissez un terme de recherche pour commencer.</p>
            <p style="font-size:13px;">Vous pouvez rechercher des étudiants, des cours ou des classes.</p>
        </div>
    <?php endif; ?>
</div>
</body>
</html>