<?php
require_once __DIR__ . '/db.php';
// ============================================================
// export_universel.php — Export unifié (XLS, CSV, PDF)
// ============================================================
require_once 'auth.php';
requireRole(['Administrateur', 'Doyen', 'Comptable', 'Recteur', 'Agent']);

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
   PARAMÈTRES
============================================================ */
$table    = isset($_GET['table']) ? trim($_GET['table']) : '';
$format   = isset($_GET['format']) ? strtolower(trim($_GET['format'])) : 'xls';
$filename = isset($_GET['filename']) ? preg_replace('/[^a-zA-Z0-9_\-]/', '_', trim($_GET['filename'])) : 'Export_' . date('Y-m-d');

if (!in_array($format, ['xls', 'csv', 'pdf'], true)) {
    $format = 'xls';
}

/* ============================================================
   CONFIGURATION DES TABLES
============================================================ */
$config_tables = [
    'etudiants' => [
        'colonnes' => [
            'matricule' => 'Matricule', 'nom' => 'Nom', 'prenom' => 'Prénom',
            'sexe' => 'Sexe', 'telephone' => 'Téléphone', 'email' => 'Email Personnel',
            'email_auth' => 'Email Académique', 'niveau' => 'Classe',
            'nom_dept' => 'Département', 'faculte' => 'Faculté',
            'titre' => 'Titre', 'annee_academique_entree' => 'Année Académique',
            'date_naissance' => 'Date Naissance'
        ],
        'sql' => "SELECT e.matricule, u.nom, u.prenom, u.sexe, u.telephone, u.email, u.email_auth,
                         c.niveau, d.nom_dept, d.faculte, e.titre, e.annee_academique_entree, e.date_naissance
                  FROM etudiants e
                  INNER JOIN users u ON e.id_user = u.id_user
                  INNER JOIN classes c ON e.id_classe = c.id_classe
                  INNER JOIN departements d ON c.id_dept = d.id_dept
                  WHERE 1=1",
        'order' => 'ORDER BY e.id_etudiant DESC'
    ],
    'professeurs' => [
        'colonnes' => [
            'nom' => 'Nom', 'prenom' => 'Prénom', 'sexe' => 'Sexe',
            'telephone' => 'Téléphone', 'email' => 'Email Personnel',
            'email_auth' => 'Email Académique', 'specialite' => 'Spécialité',
            'diplome' => 'Diplôme', 'departements' => 'Départements'
        ],
        'sql' => "SELECT u.nom, u.prenom, u.sexe, u.telephone, u.email, u.email_auth,
                         p.specialite, p.diplome,
                         GROUP_CONCAT(DISTINCT d.nom_dept ORDER BY d.nom_dept SEPARATOR ', ') AS departements
                  FROM professeurs p
                  INNER JOIN users u ON p.id_user = u.id_user
                  LEFT JOIN cours c ON p.id_prof = c.id_prof
                  LEFT JOIN classes cl ON c.id_classe = cl.id_classe
                  LEFT JOIN departements d ON cl.id_dept = d.id_dept
                  WHERE 1=1",
        'group' => 'GROUP BY p.id_prof',
        'order' => 'ORDER BY u.nom ASC'
    ],
    'cours' => [
        'colonnes' => [
            'nom_cours' => 'Nom du Cours', 'credit' => 'Crédits',
            'categorie' => 'Catégorie', 'semestre' => 'Semestre',
            'professeur' => 'Professeur', 'niveau' => 'Classe',
            'nom_dept' => 'Département', 'faculte' => 'Faculté'
        ],
        'sql' => "SELECT c.nom_cours, c.credit, c.categorie, c.semestre,
                         CONCAT(u.nom, ' ', u.prenom) AS professeur, cl.niveau, d.nom_dept, d.faculte
                  FROM cours c
                  INNER JOIN professeurs p ON c.id_prof = p.id_prof
                  INNER JOIN users u ON p.id_user = u.id_user
                  INNER JOIN classes cl ON c.id_classe = cl.id_classe
                  INNER JOIN departements d ON cl.id_dept = d.id_dept
                  WHERE 1=1",
        'order' => 'ORDER BY c.id_cours DESC'
    ],
    'paiements' => [
        'colonnes' => [
            'etudiant' => 'Étudiant', 'matricule' => 'Matricule',
            'montant' => 'Montant (BIF)', 'date_paiement' => 'Date Paiement',
            'tranche' => 'Tranche', 'banque' => 'Banque', 'bordereau' => 'Bordereau',
            'motif' => 'Motif', 'niveau' => 'Classe', 'nom_dept' => 'Département',
            'faculte' => 'Faculté'
        ],
        'sql' => "SELECT CONCAT(u.nom, ' ', u.prenom) AS etudiant, e.matricule,
                         p.montant, p.date_paiement, p.tranche, p.banque, p.bordereau, p.motif,
                         cl.niveau, d.nom_dept, d.faculte
                  FROM paiements p
                  INNER JOIN etudiants e ON p.id_etudiant = e.id_etudiant
                  INNER JOIN users u ON e.id_user = u.id_user
                  INNER JOIN classes cl ON e.id_classe = cl.id_classe
                  INNER JOIN departements d ON cl.id_dept = d.id_dept
                  WHERE 1=1",
        'order' => 'ORDER BY p.id_paiement DESC',
        'total' => 'montant'
    ],
    'notes' => [
        'colonnes' => [
            'etudiant' => 'Étudiant', 'matricule' => 'Matricule',
            'nom_cours' => 'Cours', 'note' => 'Note /20', 'session' => 'Session',
            'date_saisie' => 'Date Saisie', 'niveau' => 'Classe',
            'nom_dept' => 'Département', 'faculte' => 'Faculté'
        ],
        'sql' => "SELECT CONCAT(u.nom, ' ', u.prenom) AS etudiant, e.matricule,
                         co.nom_cours, n.note, n.session, n.date_saisie,
                         cl.niveau, d.nom_dept, d.faculte
                  FROM notes n
                  INNER JOIN etudiants e ON n.id_etudiant = e.id_etudiant
                  INNER JOIN users u ON e.id_user = u.id_user
                  INNER JOIN cours co ON n.id_cours = co.id_cours
                  INNER JOIN classes cl ON co.id_classe = cl.id_classe
                  INNER JOIN departements d ON cl.id_dept = d.id_dept
                  WHERE 1=1",
        'order' => 'ORDER BY n.id_note DESC'
    ],
    'inscriptions' => [
        'colonnes' => [
            'etudiant' => 'Étudiant', 'matricule' => 'Matricule',
            'date_inscription' => 'Date Inscription', 'niveau' => 'Classe',
            'nom_dept' => 'Département', 'faculte' => 'Faculté'
        ],
        'sql' => "SELECT CONCAT(u.nom, ' ', u.prenom) AS etudiant, e.matricule,
                         i.date_inscription, cl.niveau, d.nom_dept, d.faculte
                  FROM inscriptions i
                  INNER JOIN etudiants e ON i.id_etudiant = e.id_etudiant
                  INNER JOIN users u ON e.id_user = u.id_user
                  INNER JOIN classes cl ON e.id_classe = cl.id_classe
                  INNER JOIN departements d ON cl.id_dept = d.id_dept
                  WHERE 1=1",
        'order' => 'ORDER BY i.id_inscription DESC'
    ],
    'users' => [
        'colonnes' => [
            'nom' => 'Nom', 'prenom' => 'Prénom', 'username' => 'Username',
            'email' => 'Email', 'telephone' => 'Téléphone', 'sexe' => 'Sexe',
            'role' => 'Rôle', 'statut' => 'Statut',
            'date_creation' => 'Date Création', 'derniere_connexion' => 'Dernière Connexion'
        ],
        'sql' => "SELECT nom, prenom, username, email, telephone, sexe, role, statut, date_creation, derniere_connexion
                  FROM users WHERE 1=1",
        'order' => 'ORDER BY id_user DESC'
    ],
    'departements' => [
        'colonnes' => ['nom_dept' => 'Nom Département', 'faculte' => 'Faculté', 'description' => 'Description'],
        'sql' => "SELECT nom_dept, faculte, description FROM departements WHERE 1=1",
        'order' => 'ORDER BY id_dept DESC'
    ],
    'classes' => [
        'colonnes' => ['niveau' => 'Classe', 'nom_dept' => 'Département', 'faculte' => 'Faculté'],
        'sql' => "SELECT c.niveau, d.nom_dept, d.faculte
                  FROM classes c
                  INNER JOIN departements d ON c.id_dept = d.id_dept
                  WHERE 1=1",
        'order' => 'ORDER BY c.id_classe DESC'
    ]
];

if (!isset($config_tables[$table])) {
    die("Table non supportée pour l'export.");
}

$config = $config_tables[$table];
$sql = $config['sql'];
$params = [];

/* ============================================================
   FILTRES (préparés, sécurisés)
============================================================ */
if ($table === 'etudiants') {
    if (!empty($_GET['search_nom']))       { $sql .= " AND u.nom LIKE ?";       $params[] = '%' . $_GET['search_nom'] . '%'; }
    if (!empty($_GET['search_prenom']))    { $sql .= " AND u.prenom LIKE ?";    $params[] = '%' . $_GET['search_prenom'] . '%'; }
    if (!empty($_GET['search_matricule'])) { $sql .= " AND e.matricule LIKE ?"; $params[] = '%' . $_GET['search_matricule'] . '%'; }
    if (!empty($_GET['dept']) && $_GET['dept'] > 0)     { $sql .= " AND d.id_dept = ?";   $params[] = (int)$_GET['dept']; }
    if (!empty($_GET['classe']) && $_GET['classe'] > 0) { $sql .= " AND c.id_classe = ?"; $params[] = (int)$_GET['classe']; }
    if (!empty($_GET['sexe']))             { $sql .= " AND u.sexe = ?";         $params[] = $_GET['sexe']; }
}
if ($table === 'paiements') {
    if (!empty($_GET['departement']) && $_GET['departement'] > 0) { $sql .= " AND d.id_dept = ?"; $params[] = (int)$_GET['departement']; }
    if (!empty($_GET['tranche'])) { $sql .= " AND p.tranche = ?"; $params[] = $_GET['tranche']; }
}
if ($table === 'notes') {
    if (!empty($_GET['departement']) && $_GET['departement'] > 0) { $sql .= " AND d.id_dept = ?"; $params[] = (int)$_GET['departement']; }
    if (!empty($_GET['classe']) && $_GET['classe'] > 0)           { $sql .= " AND cl.id_classe = ?"; $params[] = (int)$_GET['classe']; }
}

if (isset($config['group'])) { $sql .= " " . $config['group']; }
$sql .= " " . $config['order'];

$stmt = $bdd->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
$colonnes = $config['colonnes'];

/* ============================================================
   DISPATCH VERS LE FORMAT CHOISI
============================================================ */
switch ($format) {
    case 'csv':
        exportToCSV($data, $colonnes, $table, $filename);
        break;
    case 'pdf':
        exportToPDF($data, $colonnes, $table, $filename);
        break;
    case 'xls':
    default:
        exportToXLS($data, $colonnes, $table, $filename);
        break;
}

/* ============================================================
   FONCTION : Export CSV (séparateur ";" pour Excel FR)
============================================================ */
function exportToCSV($data, $colonnes, $table, $filename) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    header('Pragma: no-cache');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Expires: 0');

    echo "\xEF\xBB\xBF"; // BOM UTF-8

    $out = fopen('php://output', 'w');

    // En-tête
    fputcsv($out, array_values($colonnes), ';');

    // Données
    foreach ($data as $row) {
        $line = [];
        foreach (array_keys($colonnes) as $key) {
            $value = $row[$key] ?? '';

            if ($key === 'montant' && is_numeric($value)) {
                $value = number_format($value, 0, ',', ' ') . ' BIF';
            } elseif ($key === 'note' && is_numeric($value)) {
                $value = number_format($value, 2, ',', ' ') . '/20';
            } elseif (in_array($key, ['date_naissance','date_inscription','date_paiement','date_saisie','date_creation','derniere_connexion'], true)) {
                if (!empty($value) && $value !== '0000-00-00') {
                    $value = date('d/m/Y', strtotime($value));
                } else {
                    $value = '-';
                }
            }

            $line[] = $value;
        }
        fputcsv($out, $line, ';');
    }

    // Ligne de total pour paiements
    if ($table === 'paiements' && !empty($data)) {
        $total = array_sum(array_column($data, 'montant'));
        $totalLine = array_fill(0, count($colonnes), '');
        $totalLine[count($colonnes) - 2] = 'TOTAL GÉNÉRAL';
        $totalLine[count($colonnes) - 1] = number_format($total, 0, ',', ' ') . ' BIF';
        fputcsv($out, $totalLine, ';');
    }

    fclose($out);
    exit;
}

/* ============================================================
   FONCTION : Export XLS (HTML compatible Excel)
============================================================ */
function exportToXLS($data, $colonnes, $table, $filename) {
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"" . $filename . "_" . date('Y-m-d') . ".xls\"");
    header("Pragma: no-cache");
    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
    header("Expires: 0");
    echo "\xEF\xBB\xBF";

    echo "<html xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:x='urn:schemas-microsoft-com:office:excel' xmlns='http://www.w3.org/TR/REC-html40'>";
    echo "<head><meta charset='utf-8'>";
    echo "<style>";
    echo "th { background-color: #0f172a; color: #ffffff; font-weight: bold; padding: 8px; border: 1px solid #000000; }";
    echo "td { padding: 6px; border: 1px solid #cccccc; }";
    echo ".header-title { font-size: 16pt; font-weight: bold; color: #003366; text-align: center; }";
    echo ".sub-title { font-size: 10pt; color: #666666; text-align: center; }";
    echo ".success { background-color: #d1fae5; color: #065f46; }";
    echo ".danger { background-color: #fee2e2; color: #991b1b; }";
    echo "</style></head><body>";

    echo "<table border='1' cellpadding='0' cellspacing='0' style='border-collapse:collapse;'>";
    echo "<tr><td colspan='" . count($colonnes) . "' style='background-color:#f8fafc; padding:15px; text-align:center;'>";
    echo "<div class='header-title'>📊 " . strtoupper($table) . " - EXPORT</div>";
    echo "<div class='sub-title'>Généré le " . date('d/m/Y à H:i') . " | UniVerse</div>";
    echo "</td></tr>";

    echo "<tr>";
    foreach ($colonnes as $label) {
        echo "<th>" . htmlspecialchars($label) . "</th>";
    }
    echo "</tr>";

    if (!empty($data)) {
        foreach ($data as $row) {
            echo "<tr>";
            foreach (array_keys($colonnes) as $key) {
                $value = $row[$key] ?? '';

                if ($key === 'montant' && is_numeric($value)) {
                    $value = number_format($value, 0, ',', ' ') . ' BIF';
                }
                if ($key === 'note' && is_numeric($value)) {
                    $value = number_format($value, 2, ',', ' ') . ' /20';
                    $style = $value >= 10 ? 'success' : 'danger';
                    echo "<td class='" . $style . "'>" . htmlspecialchars($value) . "</td>";
                    continue;
                }
                if (in_array($key, ['date_naissance','date_inscription','date_paiement','date_saisie','date_creation','derniere_connexion'], true)) {
                    if (!empty($value) && $value !== '0000-00-00') {
                        $value = date('d/m/Y', strtotime($value));
                    } else {
                        $value = '-';
                    }
                }
                echo "<td>" . htmlspecialchars($value) . "</td>";
            }
            echo "</tr>";
        }

        if ($table === 'paiements' && !empty($data)) {
            $total = array_sum(array_column($data, 'montant'));
            echo "<tr>";
            echo "<td colspan='" . (count($colonnes) - 1) . "' style='text-align:right; font-weight:bold; background-color:#f1f5f9;'>TOTAL GENERAL</td>";
            echo "<td style='font-weight:bold; color:#10b981; background-color:#f1f5f9;'>" . number_format($total, 0, ',', ' ') . " BIF</td>";
            echo "</tr>";
        }
    } else {
        echo "<tr><td colspan='" . count($colonnes) . "' style='text-align:center; padding:20px; color:#94a3b8;'>Aucune donnée disponible</td></tr>";
    }

    echo "</table></body></html>";
    exit;
}

/* ============================================================
   FONCTION : Export PDF (Dompdf)
============================================================ */
function exportToPDF($data, $colonnes, $table, $filename) {
    $autoload = __DIR__ . '/vendor/autoload.php';
    if (!file_exists($autoload)) {
        die("⚠ Dompdf n'est pas installé. Lancez dans le terminal : <code>composer require dompdf/dompdf</code>");
    }
    require_once $autoload;

    if (!class_exists('\\Dompdf\\Dompdf')) {
        die("⚠ Dompdf introuvable. Lancez : <code>composer require dompdf/dompdf</code>");
    }

    $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>'
          . 'body { font-family: "DejaVu Sans", sans-serif; font-size: 8.5px; color: #1e293b; margin: 10px; }'
          . '.header { text-align: center; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 3px solid #003366; }'
          . '.header h1 { color: #003366; font-size: 16px; margin: 0; text-transform: uppercase; letter-spacing: 1px; }'
          . '.header .subtitle { font-size: 10px; color: #64748b; margin-top: 3px; }'
          . '.meta { background: #f8fafc; padding: 8px 12px; border-radius: 5px; margin-bottom: 12px; font-size: 9px; border: 1px solid #e2e8f0; }'
          . 'table { width: 100%; border-collapse: collapse; }'
          . 'th { background: #003366; color: white; padding: 6px 8px; text-align: left; font-size: 8.5px; text-transform: uppercase; letter-spacing: 0.3px; }'
          . 'td { padding: 5px 8px; border-bottom: 1px solid #e2e8f0; font-size: 8.5px; }'
          . 'tbody tr:nth-child(even) td { background: #f8fafc; }'
          . '.note-success { color: #065f46; background: #d1fae5 !important; text-align: center; font-weight: bold; }'
          . '.note-danger { color: #991b1b; background: #fee2e2 !important; text-align: center; font-weight: bold; }'
          . '.money { text-align: right; font-weight: 600; color: #065f46; }'
          . '.total-row td { background: #f1f5f9 !important; font-weight: bold; font-size: 9.5px; }'
          . '.footer { text-align: center; font-size: 8px; color: #94a3b8; margin-top: 15px; padding-top: 8px; border-top: 1px solid #e2e8f0; }'
          . '</style></head><body>';

    $html .= '<div class="header">';
    $html .= '<h1>📊 ' . strtoupper($table) . ' — Export</h1>';
    $html .= '<div class="subtitle">Université du Lac Tanganyika — UniVerse</div>';
    $html .= '</div>';

    $html .= '<div class="meta">';
    $html .= '<strong>Généré le :</strong> ' . date('d/m/Y à H:i') . ' &nbsp;|&nbsp; ';
    $html .= '<strong>Nombre d\'enregistrements :</strong> ' . count($data);
    $html .= '</div>';

    $html .= '<table><thead><tr>';
    foreach ($colonnes as $label) {
        $html .= '<th>' . htmlspecialchars($label) . '</th>';
    }
    $html .= '</tr></thead><tbody>';

    if (!empty($data)) {
        foreach ($data as $row) {
            $html .= '<tr>';
            foreach (array_keys($colonnes) as $key) {
                $value = $row[$key] ?? '';
                $class = '';

                if ($key === 'montant' && is_numeric($value)) {
                    $value = number_format($value, 0, ',', ' ') . ' BIF';
                    $class = 'money';
                } elseif ($key === 'note' && is_numeric($value)) {
                    $note_brute = (float)$value;
                    $value = number_format($note_brute, 2, ',', ' ') . '/20';
                    $class = $note_brute >= 10 ? 'note-success' : 'note-danger';
                } elseif (in_array($key, ['date_naissance','date_inscription','date_paiement','date_saisie','date_creation','derniere_connexion'], true)) {
                    if (!empty($value) && $value !== '0000-00-00') {
                        $value = date('d/m/Y', strtotime($value));
                    } else {
                        $value = '-';
                    }
                }

                $html .= '<td class="' . $class . '">' . htmlspecialchars($value) . '</td>';
            }
            $html .= '</tr>';
        }

        if ($table === 'paiements' && !empty($data)) {
            $total = array_sum(array_column($data, 'montant'));
            $html .= '<tr class="total-row">';
            $html .= '<td colspan="' . (count($colonnes) - 1) . '" style="text-align:right;">TOTAL GÉNÉRAL</td>';
            $html .= '<td class="money">' . number_format($total, 0, ',', ' ') . ' BIF</td>';
            $html .= '</tr>';
        }
    } else {
        $html .= '<tr><td colspan="' . count($colonnes) . '" style="text-align:center; padding:25px; color:#94a3b8;">Aucune donnée disponible pour les critères sélectionnés.</td></tr>';
    }

    $html .= '</tbody></table>';
    $html .= '<div class="footer">';
    $html .= '© ' . date('Y') . ' UniVerse — Document généré automatiquement<br>';
    $html .= 'Université du Lac Tanganyika';
    $html .= '</div>';
    $html .= '</body></html>';

    $options = new \Dompdf\Options();
    $options->set('defaultFont', 'DejaVu Sans');
    $options->set('isRemoteEnabled', true);

    $dompdf = new \Dompdf\Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();

    $dompdf->stream($filename . '_' . date('Y-m-d') . '.pdf', ['Attachment' => 1]);
    exit;
}