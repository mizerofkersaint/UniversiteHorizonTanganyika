<?php
require_once __DIR__ . '/db.php';
require_once 'auth.php';
requireRole(['Administrateur', 'Recteur']);

try {
    $bdd = getDBConnection();
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Erreur : " . $e->getMessage());
}

// ============================================================
// CONFIGURATION DES RAPPORTS
// ============================================================
$rapports = [
    'etudiants' => [
        'label' => '🎓 Étudiants',
        'colonnes' => [
            'matricule' => 'Matricule',
            'nom' => 'Nom',
            'prenom' => 'Prénom',
            'niveau' => 'Classe',
            'nom_dept' => 'Département',
            'faculte' => 'Faculté',
            'annee' => 'Année académique',
        ],
        'sql' => "SELECT e.matricule, u.nom, u.prenom, c.niveau, d.nom_dept, d.faculte, e.annee_academique_entree AS annee
                  FROM etudiants e
                  JOIN users u ON e.id_user = u.id_user
                  JOIN classes c ON e.id_classe = c.id_classe
                  JOIN departements d ON c.id_dept = d.id_dept
                  WHERE 1=1",
        'has_dates' => false,
    ],
    'notes' => [
        'label' => '📝 Notes',
        'colonnes' => [
            'etudiant' => 'Étudiant',
            'nom_cours' => 'Cours',
            'note' => 'Note',
            'session' => 'Session',
            'date_saisie' => 'Date',
            'niveau' => 'Classe',
            'nom_dept' => 'Département',
        ],
        'sql' => "SELECT CONCAT(u.nom,' ',u.prenom) AS etudiant, co.nom_cours, n.note, n.session, n.date_saisie, c.niveau, d.nom_dept
                  FROM notes n
                  JOIN etudiants e ON n.id_etudiant = e.id_etudiant
                  JOIN users u ON e.id_user = u.id_user
                  JOIN cours co ON n.id_cours = co.id_cours
                  JOIN classes c ON e.id_classe = c.id_classe
                  JOIN departements d ON c.id_dept = d.id_dept
                  WHERE n.statut = 'valide'",
        'has_dates' => true,
        'date_field' => 'n.date_saisie',
    ],
    'paiements' => [
        'label' => '💰 Paiements',
        'colonnes' => [
            'etudiant' => 'Étudiant',
            'montant' => 'Montant',
            'tranche' => 'Tranche',
            'date_paiement' => 'Date',
            'banque' => 'Banque',
            'statut' => 'Statut',
            'nom_dept' => 'Département',
        ],
        'sql' => "SELECT CONCAT(u.nom,' ',u.prenom) AS etudiant, p.montant, p.tranche, p.date_paiement, p.banque, p.statut, d.nom_dept
                  FROM paiements p
                  JOIN etudiants e ON p.id_etudiant = e.id_etudiant
                  JOIN users u ON e.id_user = u.id_user
                  JOIN classes c ON e.id_classe = c.id_classe
                  JOIN departements d ON c.id_dept = d.id_dept
                  WHERE p.statut = 'valide'",
        'has_dates' => true,
        'date_field' => 'p.date_paiement',
    ],
];

// ============================================================
// PARAMÈTRES
// ============================================================
$type_rapport = $_GET['type'] ?? 'etudiants';
if (!isset($rapports[$type_rapport])) $type_rapport = 'etudiants';
$config = $rapports[$type_rapport];

$colonnes_choisies = $_GET['colonnes'] ?? array_keys($config['colonnes']);
if (!is_array($colonnes_choisies) || empty($colonnes_choisies)) $colonnes_choisies = array_keys($config['colonnes']);

$date_debut = $_GET['date_debut'] ?? '';
$date_fin = $_GET['date_fin'] ?? '';
$filtre_dept = (int)($_GET['dept'] ?? 0);
$filtre_faculte = trim($_GET['faculte'] ?? '');
$mode = $_GET['mode'] ?? 'detail';

// ============================================================
// CONSTRUCTION REQUÊTE
// ============================================================
$sql = $config['sql'];
$params = [];

if ($config['has_dates'] && $date_debut) { $sql .= " AND " . $config['date_field'] . " >= ?"; $params[] = $date_debut; }
if ($config['has_dates'] && $date_fin) { $sql .= " AND " . $config['date_field'] . " <= ?"; $params[] = $date_fin; }
if ($filtre_dept > 0) { $sql .= " AND d.id_dept = ?"; $params[] = $filtre_dept; }
if ($filtre_faculte !== '') { $sql .= " AND d.faculte = ?"; $params[] = $filtre_faculte; }

$sql .= " ORDER BY 1 LIMIT 500";

$stmt = $bdd->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ============================================================
// EXPORT CSV
// ============================================================
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="rapport_' . $type_rapport . '_' . date('Y-m-d') . '.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, array_map(fn($k) => $config['colonnes'][$k], $colonnes_choisies));
    foreach ($data as $row) {
        fputcsv($out, array_map(fn($k) => $row[$k] ?? '', $colonnes_choisies));
    }
    fclose($out);
    exit;
}

// ============================================================
// ANALYSE COMPARATIVE
// ============================================================
$comparaison = [];
$titre_comparaison = '';
$col_extra = '';

if ($mode === 'compare_dept' || $mode === 'compare_faculte') {
    $group = ($mode === 'compare_dept') ? 'd.nom_dept' : 'd.faculte';
    $titre_comparaison = ($mode === 'compare_dept') ? 'Comparaison par département' : 'Comparaison par faculté';

    if ($type_rapport === 'notes') {
        $sql_cmp = "SELECT $group AS groupe, COUNT(*) AS nb,
                    ROUND(AVG(n.note),2) AS moyenne,
                    ROUND(AVG(CASE WHEN n.note >= 10 THEN 1 ELSE 0 END)*100, 1) AS taux
                    FROM notes n
                    JOIN etudiants e ON n.id_etudiant = e.id_etudiant
                    JOIN classes c ON e.id_classe = c.id_classe
                    JOIN departements d ON c.id_dept = d.id_dept
                    WHERE n.statut = 'valide'";
        $p = [];
        if ($date_debut) { $sql_cmp .= " AND n.date_saisie >= ?"; $p[] = $date_debut; }
        if ($date_fin) { $sql_cmp .= " AND n.date_saisie <= ?"; $p[] = $date_fin; }
        $sql_cmp .= " GROUP BY $group ORDER BY moyenne DESC";
        $col_extra = 'note';
    } elseif ($type_rapport === 'paiements') {
        $sql_cmp = "SELECT $group AS groupe, COUNT(*) AS nb,
                    SUM(p.montant) AS total,
                    ROUND(AVG(p.montant),0) AS moyenne
                    FROM paiements p
                    JOIN etudiants e ON p.id_etudiant = e.id_etudiant
                    JOIN classes c ON e.id_classe = c.id_classe
                    JOIN departements d ON c.id_dept = d.id_dept
                    WHERE p.statut = 'valide'";
        $p = [];
        if ($date_debut) { $sql_cmp .= " AND p.date_paiement >= ?"; $p[] = $date_debut; }
        if ($date_fin) { $sql_cmp .= " AND p.date_paiement <= ?"; $p[] = $date_fin; }
        $sql_cmp .= " GROUP BY $group ORDER BY total DESC";
        $col_extra = 'montant';
    } else {
        $sql_cmp = "SELECT $group AS groupe, COUNT(*) AS nb
                    FROM etudiants e
                    JOIN classes c ON e.id_classe = c.id_classe
                    JOIN departements d ON c.id_dept = d.id_dept
                    GROUP BY $group ORDER BY nb DESC";
        $p = [];
        $col_extra = 'etudiants';
    }
    $stmt = $bdd->prepare($sql_cmp);
    $stmt->execute($p);
    $comparaison = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ============================================================
// RAPPORT PÉRIODIQUE (mensuel)
// ============================================================
$rapport_mensuel = [];
if ($mode === 'rapport_mensuel') {
    if ($type_rapport === 'paiements') {
        $sql_m = "SELECT DATE_FORMAT(date_paiement, '%Y-%m') AS periode, 
                  DATE_FORMAT(date_paiement, '%M %Y') AS mois_lisible,
                  COUNT(*) AS nb, SUM(montant) AS total, ROUND(AVG(montant),0) AS moyenne
                  FROM paiements WHERE statut = 'valide' 
                  GROUP BY periode ORDER BY periode DESC LIMIT 12";
    } elseif ($type_rapport === 'notes') {
        $sql_m = "SELECT DATE_FORMAT(n.date_saisie, '%Y-%m') AS periode,
                  DATE_FORMAT(n.date_saisie, '%M %Y') AS mois_lisible,
                  COUNT(*) AS nb, ROUND(AVG(n.note),2) AS total, 
                  ROUND(AVG(CASE WHEN n.note >= 10 THEN 1 ELSE 0 END)*100,1) AS moyenne
                  FROM notes n WHERE n.statut = 'valide'
                  GROUP BY periode ORDER BY periode DESC LIMIT 12";
    } else {
        $sql_m = "SELECT DATE_FORMAT(u.date_creation, '%Y-%m') AS periode,
                  DATE_FORMAT(u.date_creation, '%M %Y') AS mois_lisible,
                  COUNT(*) AS nb, 0 AS total, 0 AS moyenne
                  FROM etudiants e JOIN users u ON e.id_user = u.id_user
                  GROUP BY periode ORDER BY periode DESC LIMIT 12";
    }
    try { $rapport_mensuel = $bdd->query($sql_m)->fetchAll(); } catch (Exception $e) { $rapport_mensuel = []; }
}

// ============================================================
// PRÉVISIONS (paiements uniquement)
// ============================================================
$previsions = [];
$projection = 0;
if ($mode === 'prevision' && $type_rapport === 'paiements') {
    $sql_prev = "SELECT DATE_FORMAT(date_paiement, '%Y-%m') AS mois, SUM(montant) AS total, COUNT(*) AS nb
                 FROM paiements
                 WHERE statut = 'valide' AND date_paiement >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                 GROUP BY mois ORDER BY mois ASC";
    $previsions = $bdd->query($sql_prev)->fetchAll(PDO::FETCH_ASSOC);
    if (count($previsions) > 0) {
        $total_3 = 0; $cnt = 0;
        foreach (array_slice($previsions, -3) as $pv) { $total_3 += $pv['total']; $cnt++; }
        $projection = $cnt > 0 ? round($total_3 / $cnt) : 0;
    }
}

// ============================================================
// LISTES FILTRES
// ============================================================
$depts = $bdd->query("SELECT id_dept, nom_dept FROM departements ORDER BY nom_dept")->fetchAll();
$facultes = $bdd->query("SELECT DISTINCT faculte FROM departements WHERE faculte IS NOT NULL AND faculte != '' ORDER BY faculte")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Rapports personnalisés - UniVerse</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Arial, sans-serif; }
        body { background: #eef3f8; padding: 30px 20px; color: #1f2937; }
        .container { max-width: 1400px; margin: 0 auto; }
        .page-header { background: linear-gradient(135deg, #003366, #0055aa); color: white; text-align: center; padding: 25px; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.2); margin-bottom: 20px; }
        .page-header h1 { font-size: 24px; }
        .page-header p { font-size: 13px; opacity: 0.9; margin-top: 5px; }
        .card { background: white; border-radius: 18px; box-shadow: 0 10px 25px rgba(0,0,0,.07); padding: 25px; margin-bottom: 20px; }
        .card h2 { color: #003366; margin-bottom: 15px; font-size: 17px; padding-bottom: 12px; border-bottom: 2px solid #f1f5f9; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 15px; }
        .form-group { display: flex; flex-direction: column; }
        .form-group label { font-weight: 600; font-size: 12px; color: #334155; margin-bottom: 5px; text-transform: uppercase; }
        select, input[type="date"] { padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 13px; background: #f8fafc; outline: none; }
        select:focus, input:focus { border-color: #0055aa; box-shadow: 0 0 0 3px rgba(0,85,170,.10); }
        .colonnes-grid { display: flex; flex-wrap: wrap; gap: 10px; margin: 10px 0; padding: 15px; background: #f8fafc; border-radius: 10px; }
        .colonnes-grid label { display: flex; align-items: center; gap: 6px; font-size: 13px; cursor: pointer; padding: 6px 12px; background: white; border-radius: 6px; border: 1px solid #e2e8f0; }
        .colonnes-grid label:hover { border-color: #0055aa; }
        .colonnes-grid input:checked + span { font-weight: 700; color: #003366; }
        .tabs { display: flex; gap: 10px; margin-bottom: 15px; flex-wrap: wrap; }
        .tab { padding: 10px 20px; border-radius: 8px; background: white; border: 1px solid #e2e8f0; text-decoration: none; color: #475569; font-weight: 600; font-size: 13px; }
        .tab.active { background: #003366; color: white; border-color: #003366; }
        .tab:hover { border-color: #0055aa; }
        .btn { padding: 11px 22px; border: none; border-radius: 10px; font-weight: 600; font-size: 14px; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s; }
        .btn-primary { background: #003366; color: white; }
        .btn-primary:hover { background: #0055aa; transform: translateY(-2px); }
        .btn-success { background: #16a34a; color: white; }
        .btn-success:hover { background: #15803d; }
        .actions { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 15px; }
        .results-bar { background: #eff6ff; padding: 12px 20px; border-radius: 10px; margin-bottom: 15px; border-left: 4px solid #3b82f6; }
        .results-bar strong { color: #003366; font-size: 18px; }
        table { width: 100%; border-collapse: collapse; background: white; border-radius: 10px; overflow: hidden; font-size: 13px; }
        th { background: #003366; color: white; padding: 12px; text-align: left; font-size: 12px; text-transform: uppercase; }
        td { padding: 10px 12px; border-bottom: 1px solid #eef2f7; }
        tr:hover td { background: #f8fbff; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .stat-card { background: white; padding: 18px; border-radius: 12px; text-align: center; border-top: 4px solid #003366; box-shadow: 0 4px 10px rgba(0,0,0,.05); }
        .stat-card .value { font-size: 24px; font-weight: 800; color: #003366; }
        .stat-card .label { font-size: 11px; color: #64748b; text-transform: uppercase; font-weight: 600; margin-top: 4px; }
        .empty { padding: 40px; text-align: center; color: #94a3b8; }
    </style>
</head>
<body>
<div class="container">

    <div class="page-header">
        <h1>📊 Rapports Personnalisés</h1>
        <p>Filtres, colonnes au choix, analyse comparative et prévisions</p>
    </div>

    <!-- TABS DE MODE -->
    <div class="tabs">
        <a href="?type=<?= $type_rapport ?>&mode=detail" class="tab <?= $mode === 'detail' ? 'active' : '' ?>">📋 Rapport détaillé</a>
        <a href="?type=<?= $type_rapport ?>&mode=compare_dept" class="tab <?= $mode === 'compare_dept' ? 'active' : '' ?>">🏛️ Comparaison départements</a>
        <a href="?type=<?= $type_rapport ?>&mode=compare_faculte" class="tab <?= $mode === 'compare_faculte' ? 'active' : '' ?>">🎓 Comparaison facultés</a>
        <a href="?type=<?= $type_rapport ?>&mode=rapport_mensuel" class="tab <?= $mode === 'rapport_mensuel' ? 'active' : '' ?>">📅 Rapport mensuel</a>
        <?php if ($type_rapport === 'paiements'): ?>
            <a href="?type=paiements&mode=prevision" class="tab <?= $mode === 'prevision' ? 'active' : '' ?>">📈 Prévisions</a>
        <?php endif; ?>
    </div>

    <!-- FORMULAIRE DE CONFIGURATION -->
    <div class="card">
        <h2>⚙️ Configuration du rapport</h2>
        <form method="GET">
            <input type="hidden" name="mode" value="<?= htmlspecialchars($mode) ?>">
            
            <div class="form-grid">
                <div class="form-group">
                    <label>Type de rapport</label>
                    <select name="type" onchange="this.form.submit()">
                        <?php foreach ($rapports as $k => $r): ?>
                            <option value="<?= $k ?>" <?= $k === $type_rapport ? 'selected' : '' ?>><?= $r['label'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if ($config['has_dates']): ?>
                    <div class="form-group">
                        <label>Date début</label>
                        <input type="date" name="date_debut" value="<?= htmlspecialchars($date_debut) ?>">
                    </div>
                    <div class="form-group">
                        <label>Date fin</label>
                        <input type="date" name="date_fin" value="<?= htmlspecialchars($date_fin) ?>">
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label>Département</label>
                    <select name="dept">
                        <option value="0">Tous</option>
                        <?php foreach ($depts as $d): ?>
                            <option value="<?= $d['id_dept'] ?>" <?= $filtre_dept == $d['id_dept'] ? 'selected' : '' ?>><?= htmlspecialchars($d['nom_dept']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Faculté</label>
                    <select name="faculte">
                        <option value="">Toutes</option>
                        <?php foreach ($facultes as $f): ?>
                            <option value="<?= htmlspecialchars($f['faculte']) ?>" <?= $filtre_faculte === $f['faculte'] ? 'selected' : '' ?>><?= htmlspecialchars($f['faculte']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <?php if ($mode === 'detail'): ?>
                <label style="font-weight:600;font-size:12px;color:#334155;text-transform:uppercase;margin-top:10px;display:block;">Colonnes à afficher</label>
                <div class="colonnes-grid">
                    <?php foreach ($config['colonnes'] as $k => $label): ?>
                        <label>
                            <input type="checkbox" name="colonnes[]" value="<?= $k ?>" <?= in_array($k, $colonnes_choisies) ? 'checked' : '' ?>>
                            <span><?= htmlspecialchars($label) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="actions">
                <button type="submit" class="btn btn-primary">🔍 Générer le rapport</button>
                <?php if ($mode === 'detail'): ?>
                    <?php $export_url = '?type=' . $type_rapport . '&mode=detail&export=csv' . ($date_debut ? '&date_debut=' . urlencode($date_debut) : '') . ($date_fin ? '&date_fin=' . urlencode($date_fin) : '') . ($filtre_dept ? '&dept=' . $filtre_dept : '') . ($filtre_faculte ? '&faculte=' . urlencode($filtre_faculte) : '') . '&' . implode('&', array_map(fn($c) => 'colonnes[]=' . urlencode($c), $colonnes_choisies)); ?>
                    <a href="<?= $export_url ?>" class="btn btn-success">📥 Exporter en CSV</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <?php if ($mode === 'detail'): ?>
        <!-- ============================================================ -->
        <!-- RAPPORT DÉTAILLÉ -->
        <!-- ============================================================ -->
        <div class="results-bar">
            <strong><?= count($data) ?></strong> résultat(s) trouvé(s)
            <?= count($data) >= 500 ? ' <em>(limite 500 atteinte)</em>' : '' ?>
        </div>

        <div class="card">
            <h2>📋 Résultats</h2>
            <?php if (count($data) > 0): ?>
                <div style="overflow-x:auto;">
                    <table>
                        <thead>
                            <tr>
                                <?php foreach ($colonnes_choisies as $col): ?>
                                    <th><?= htmlspecialchars($config['colonnes'][$col] ?? $col) ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($data as $row): ?>
                                <tr>
                                    <?php foreach ($colonnes_choisies as $col): 
                                        $val = $row[$col] ?? '';
                                        if ($col === 'montant' && is_numeric($val)) $val = number_format($val, 0, ',', ' ') . ' BIF';
                                        elseif ($col === 'note' && is_numeric($val)) $val = number_format($val, 2) . '/20';
                                    ?>
                                        <td><?= htmlspecialchars((string)$val) ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty">📭 Aucune donnée ne correspond aux critères.</div>
            <?php endif; ?>
        </div>

    <?php elseif ($mode === 'compare_dept' || $mode === 'compare_faculte'): ?>
        <!-- ============================================================ -->
        <!-- ANALYSE COMPARATIVE -->
        <!-- ============================================================ -->
        <div class="card">
            <h2>⚖️ <?= $titre_comparaison ?></h2>
            <?php if (count($comparaison) > 0): ?>
                <div style="overflow-x:auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Groupe</th>
                                <th>Nombre d'enregistrements</th>
                                <?php if ($col_extra === 'note'): ?>
                                    <th>Moyenne /20</th>
                                    <th>Taux de réussite</th>
                                <?php elseif ($col_extra === 'montant'): ?>
                                    <th>Total collecté</th>
                                    <th>Moyenne</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($comparaison as $c): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($c['groupe'] ?? 'Non assigné') ?></strong></td>
                                    <td><?= (int)$c['nb'] ?></td>
                                    <?php if ($col_extra === 'note'): ?>
                                        <td><strong style="color:<?= $c['moyenne'] >= 10 ? '#16a34a' : '#dc2626' ?>;"><?= number_format($c['moyenne'], 2) ?>/20</strong></td>
                                        <td><?= number_format($c['taux'], 1) ?>%</td>
                                    <?php elseif ($col_extra === 'montant'): ?>
                                        <td><strong style="color:#16a34a"><?= number_format($c['total'], 0, ',', ' ') ?> BIF</strong></td>
                                        <td><?= number_format($c['moyenne'], 0, ',', ' ') ?> BIF</td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty">📭 Aucune donnée pour cette comparaison.</div>
            <?php endif; ?>
        </div>

    <?php elseif ($mode === 'rapport_mensuel'): ?>
        <!-- ============================================================ -->
        <!-- RAPPORT PÉRIODIQUE MENSUEL -->
        <!-- ============================================================ -->
        <div class="card">
            <h2>📅 Rapport mensuel (12 derniers mois)</h2>
            <?php if (count($rapport_mensuel) > 0): ?>
                <div style="overflow-x:auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Période</th>
                                <th>Nombre d'opérations</th>
                                <th><?= $type_rapport === 'notes' ? 'Moyenne /20' : ($type_rapport === 'paiements' ? 'Total collecté' : '—') ?></th>
                                <th><?= $type_rapport === 'notes' ? 'Taux de réussite' : 'Moyenne' ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rapport_mensuel as $rm): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars(ucfirst($rm['mois_lisible'] ?? $rm['periode'])) ?></strong></td>
                                    <td><?= (int)$rm['nb'] ?></td>
                                    <td>
                                        <?php if ($type_rapport === 'notes'): ?>
                                            <strong style="color:<?= $rm['total'] >= 10 ? '#16a34a' : '#dc2626' ?>;"><?= number_format($rm['total'], 2) ?>/20</strong>
                                        <?php elseif ($type_rapport === 'paiements'): ?>
                                            <strong style="color:#16a34a"><?= number_format($rm['total'], 0, ',', ' ') ?> BIF</strong>
                                        <?php else: ?>—<?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($type_rapport === 'notes'): ?>
                                            <?= number_format($rm['moyenne'], 1) ?>%
                                        <?php elseif ($type_rapport === 'paiements'): ?>
                                            <?= number_format($rm['moyenne'], 0, ',', ' ') ?> BIF
                                        <?php else: ?>—<?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty">📭 Aucune donnée pour établir un rapport mensuel.</div>
            <?php endif; ?>
        </div>

    <?php elseif ($mode === 'prevision'): ?>
        <!-- ============================================================ -->
        <!-- PRÉVISIONS -->
        <!-- ============================================================ -->
        <?php if (count($previsions) > 0): ?>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="value"><?= count($previsions) ?></div>
                    <div class="label">Mois analysés</div>
                </div>
                <div class="stat-card" style="border-top-color:#16a34a;">
                    <div class="value" style="color:#16a34a;"><?= number_format(array_sum(array_column($previsions, 'total')), 0, ',', ' ') ?></div>
                    <div class="label">Total BIF (6 mois)</div>
                </div>
                <div class="stat-card" style="border-top-color:#f59e0b;">
                    <div class="value" style="color:#f59e0b;"><?= number_format($projection, 0, ',', ' ') ?></div>
                    <div class="label">Projection mois prochain (BIF)</div>
                </div>
            </div>

            <div class="card">
                <h2>📈 Évolution des paiements (6 derniers mois)</h2>
                <div style="overflow-x:auto;">
                    <table>
                        <thead>
                            <tr><th>Mois</th><th>Nombre de paiements</th><th>Total collecté</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($previsions as $pv): ?>
                                <tr>
                                    <td><?= date('F Y', strtotime($pv['mois'] . '-01')) ?></td>
                                    <td><?= (int)$pv['nb'] ?></td>
                                    <td><strong style="color:#16a34a"><?= number_format($pv['total'], 0, ',', ' ') ?> BIF</strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div style="margin-top:15px; padding:15px; background:#fffbeb; border-left:4px solid #f59e0b; border-radius:8px; font-size:13px; color:#92400e;">
                    <strong>💡 Prévision :</strong> Basée sur la moyenne des 3 derniers mois. Projection estimée : <strong><?= number_format($projection, 0, ',', ' ') ?> BIF</strong> pour le mois prochain.
                </div>
            </div>
        <?php else: ?>
            <div class="empty">📭 Pas assez de données pour faire une prévision.</div>
        <?php endif; ?>
    <?php endif; ?>

</div>
</body>
</html>