<?php
require_once __DIR__ . '/db.php';
require_once 'auth.php';
requireRole(['Administrateur', 'Doyen', 'Agent']);
include("menu.php");

try {
    $bdd = getDBConnection();
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

/* ============================================================
   SUPPRESSION AVEC REQUÊTE PRÉPARÉE
============================================================ */
if (isset($_GET["supp"]) && !empty($_GET["supp"])) {
    $id = (int)$_GET["supp"];
    try {
        $bdd->beginTransaction();
        $reqUser = $bdd->prepare("SELECT id_user FROM etudiants WHERE id_etudiant = ?");
        $reqUser->execute([$id]);
        $etd = $reqUser->fetch(PDO::FETCH_ASSOC);
        if ($etd) {
            $idUserLie = $etd['id_user'];
            $suppEtudiant = $bdd->prepare("DELETE FROM etudiants WHERE id_etudiant = ?");
            $suppEtudiant->execute([$id]);
            $suppUser = $bdd->prepare("DELETE FROM users WHERE id_user = ?");
            $suppUser->execute([$idUserLie]);
        }
        $bdd->commit();
        header("Location: AffichageEtudiants.php?success=suppression");
        exit();
    } catch (Exception $e) {
        $bdd->rollBack();
        echo "<script>alert('Erreur lors de la suppression : " . addslashes($e->getMessage()) . "');</script>";
    }
}

/* ============================================================
   RECHERCHE AVANCÉE - MULTI-CRITÈRES
============================================================ */
$search_nom = isset($_GET['search_nom']) ? trim($_GET['search_nom']) : '';
$search_prenom = isset($_GET['search_prenom']) ? trim($_GET['search_prenom']) : '';
$search_matricule = isset($_GET['search_matricule']) ? trim($_GET['search_matricule']) : '';
$search_email = isset($_GET['search_email']) ? trim($_GET['search_email']) : '';
$filtreDept = isset($_GET['dept']) ? (int)$_GET['dept'] : 0;
$filtreFaculte = isset($_GET['faculte']) ? trim($_GET['faculte']) : '';
$filtreClasse = isset($_GET['classe']) ? (int)$_GET['classe'] : 0;
$filtreSexe = isset($_GET['sexe']) ? trim($_GET['sexe']) : '';
$filtreTitre = isset($_GET['titre']) ? trim($_GET['titre']) : '';
$filtreAnnee = isset($_GET['annee']) ? trim($_GET['annee']) : '';

/* ============================================================
   RÉCUPÉRATION DES DÉPARTEMENTS POUR LES FILTRES
============================================================ */
$departements = $bdd->query("SELECT id_dept, nom_dept, faculte FROM departements ORDER BY nom_dept");

/* ============================================================
   CONSTRUCTION DE LA REQUÊTE AVEC TOUS LES CRITÈRES
============================================================ */
$where = [];
$params = [];

if (!empty($search_nom)) {
    $where[] = "u.nom LIKE ?";
    $params[] = "%" . $search_nom . "%";
}
if (!empty($search_prenom)) {
    $where[] = "u.prenom LIKE ?";
    $params[] = "%" . $search_prenom . "%";
}
if (!empty($search_matricule)) {
    $where[] = "e.matricule LIKE ?";
    $params[] = "%" . $search_matricule . "%";
}
if (!empty($search_email)) {
    $where[] = "u.email LIKE ?";
    $params[] = "%" . $search_email . "%";
}

if ($filtreDept > 0) {
    $where[] = "d.id_dept = ?";
    $params[] = $filtreDept;
}
if (!empty($filtreFaculte)) {
    $where[] = "d.faculte = ?";
    $params[] = $filtreFaculte;
}
if ($filtreClasse > 0) {
    $where[] = "c.id_classe = ?";
    $params[] = $filtreClasse;
}
if (!empty($filtreSexe)) {
    $where[] = "u.sexe = ?";
    $params[] = $filtreSexe;
}
if (!empty($filtreTitre)) {
    $where[] = "e.titre = ?";
    $params[] = $filtreTitre;
}
if (!empty($filtreAnnee)) {
    $where[] = "e.annee_academique_entree = ?";
    $params[] = $filtreAnnee;
}

$sqlWhere = $where ? "WHERE " . implode(" AND ", $where) : "";

/* ============================================================
   PAGINATION
============================================================ */
$limite = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if($page < 1) $page = 1;
$debut = ($page - 1) * $limite;

$sqlTotal = "
    SELECT COUNT(*) as total
    FROM etudiants e
    INNER JOIN users u ON u.id_user = e.id_user
    INNER JOIN classes c ON c.id_classe = e.id_classe
    INNER JOIN departements d ON d.id_dept = c.id_dept
    $sqlWhere
";
$stmtTotal = $bdd->prepare($sqlTotal);
$stmtTotal->execute($params);
$total = $stmtTotal->fetch(PDO::FETCH_ASSOC)['total'];
$nbPages = ceil($total / $limite) < 1 ? 1 : ceil($total / $limite);

/* ============================================================
   REQUÊTE PRINCIPALE
============================================================ */
$sql = "
    SELECT 
        e.id_etudiant,
        u.nom, u.prenom, u.sexe, u.telephone, 
        u.email AS email_personnel, u.email_auth AS email_academique,
        e.date_naissance, e.matricule, e.titre, e.annee_academique_entree,
        c.niveau, d.nom_dept, d.faculte
    FROM etudiants e
    INNER JOIN users u ON u.id_user = e.id_user
    INNER JOIN classes c ON c.id_classe = e.id_classe
    INNER JOIN departements d ON d.id_dept = c.id_dept
    $sqlWhere
    ORDER BY e.id_etudiant DESC
    LIMIT $debut, $limite
";
$stmt = $bdd->prepare($sql);
$stmt->execute($params);
$etudiants = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les classes filtrées par département
$classesList = [];
if($filtreDept > 0){
    $classeReq = $bdd->prepare("SELECT id_classe, niveau FROM classes WHERE id_dept = ? ORDER BY niveau");
    $classeReq->execute([$filtreDept]);
    $classesList = $classeReq->fetchAll(PDO::FETCH_ASSOC);
}

// Faculté filtrée
$faculteChoisie = '';
if($filtreDept > 0){
    $faculteReq = $bdd->prepare("SELECT faculte FROM departements WHERE id_dept = ?");
    $faculteReq->execute([$filtreDept]);
    $faculteChoisie = $faculteReq->fetchColumn();
}

function buildQuery($extra = []) {
    $query = $_GET;
    foreach($extra as $key => $value) $query[$key] = $value;
    return http_build_query($query);
}

$success = isset($_GET['success']) ? $_GET['success'] : '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion Étudiants - Recherche Avancée</title>
    <style>
        *{ margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI', Arial, sans-serif; }
        body{ background:#eef3f8; color:#1f2937; }
        .container{ width:95%; margin:30px auto; }
        header{ background:linear-gradient(135deg,#003366,#0055aa); color:white; text-align:center; padding:25px; border-radius:12px; box-shadow:0 4px 10px rgba(0,0,0,0.2); margin-bottom:20px; }
        
        .search-advanced{
            background:#fff;
            padding:25px;
            border-radius:18px;
            box-shadow:0 10px 25px rgba(0,0,0,.07);
            margin-bottom:20px;
            border:1px solid #e5e7eb;
        }
        .search-advanced .search-title{
            font-size:18px;
            font-weight:700;
            color:#003366;
            margin-bottom:15px;
            display:flex;
            align-items:center;
            gap:10px;
        }
        .search-advanced .search-title .badge{
            background:#003366;
            color:white;
            font-size:11px;
            padding:2px 12px;
            border-radius:12px;
        }
        .search-grid{
            display:grid;
            grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));
            gap:15px;
        }
        .search-group{
            display:flex;
            flex-direction:column;
        }
        .search-group label{
            font-size:12px;
            font-weight:600;
            color:#64748b;
            text-transform:uppercase;
            margin-bottom:5px;
            letter-spacing:0.3px;
        }
        .search-group input, .search-group select{
            padding:10px 14px;
            border:1px solid #d1d5db;
            border-radius:8px;
            font-size:14px;
            outline:none;
            transition:border-color 0.3s;
            background:#f8fafc;
        }
        .search-group input:focus, .search-group select:focus{
            border-color:#0055aa;
            box-shadow:0 0 0 3px rgba(0,85,170,.10);
            background:white;
        }
        .search-group select:disabled{
            background:#f1f5f9;
            cursor:not-allowed;
            opacity:0.7;
        }
        .search-actions{
            display:flex;
            gap:12px;
            margin-top:15px;
            flex-wrap:wrap;
            align-items:center;
        }
        .btn-search{
            background:#003366;
            color:white;
            border:none;
            padding:12px 30px;
            border-radius:8px;
            font-weight:700;
            cursor:pointer;
            transition:background 0.3s;
        }
        .btn-search:hover{
            background:#00509e;
        }
        .btn-reset{
            background:#e5e7eb;
            color:#111827;
            border:none;
            padding:12px 30px;
            border-radius:8px;
            font-weight:700;
            cursor:pointer;
            transition:background 0.3s;
            text-decoration:none;
            display:inline-flex;
            align-items:center;
        }
        .btn-reset:hover{
            background:#d1d5db;
        }
        .btn-excel{
            background:#10b981;
            color:white;
            border:none;
            padding:12px 30px;
            border-radius:8px;
            font-weight:700;
            cursor:pointer;
            transition:background 0.3s;
            text-decoration:none;
            display:inline-flex;
            align-items:center;
            gap:8px;
        }
        .btn-excel:hover{
            background:#059669;
        }
        
        .result-stats{
            background:white;
            padding:15px 20px;
            border-radius:12px;
            margin-bottom:15px;
            display:flex;
            justify-content:space-between;
            align-items:center;
            flex-wrap:wrap;
            gap:10px;
            border:1px solid #e5e7eb;
        }
        .result-stats .count{
            font-size:15px;
            font-weight:600;
            color:#1f2937;
        }
        .result-stats .count strong{
            color:#003366;
            font-size:20px;
        }
        .result-stats .filters-active{
            display:flex;
            gap:8px;
            flex-wrap:wrap;
        }
        .result-stats .filter-tag{
            background:#eff6ff;
            color:#1e40af;
            padding:4px 12px;
            border-radius:12px;
            font-size:12px;
            font-weight:600;
        }
        
        .add-container{
            display:flex;
            justify-content:flex-end;
            margin-bottom:15px;
        }
        .ajouter{
            background:linear-gradient(135deg,#0066cc,#004c99);
            color:white;
            padding:12px 18px;
            border-radius:10px;
            text-decoration:none;
            font-weight:700;
        }
        .table-card{
            background:#fff;
            border-radius:18px;
            box-shadow:0 10px 25px rgba(0,0,0,.07);
            overflow-x:auto;
        }
        .table-header{
            padding:18px;
            display:flex;
            justify-content:space-between;
            border-bottom:1px solid #edf0f4;
        }
        .table-header h2{
            color:#003366;
        }
        table{
            width:100%;
            border-collapse:collapse;
            min-width:1500px;
        }
        th{
            background:#003366;
            color:white;
            padding:14px;
            font-size:13px;
        }
        td{
            padding:12px;
            text-align:center;
            border-bottom:1px solid #eef2f7;
        }
        tr:hover{
            background:#f8fbff;
        }
        .actions-cell a{
            text-decoration:none;
            color:white;
            padding:8px 12px;
            border-radius:8px;
            font-weight:700;
            display:inline-block;
            margin:2px;
        }
        .modifier{
            background:#16a34a;
        }
        .supprimer{
            background:#dc2626;
        }
        .faculte-badge{
            display:inline-block;
            padding:2px 10px;
            border-radius:12px;
            background:#eff6ff;
            color:#1e40af;
            font-weight:600;
            font-size:11px;
        }
        .pagination{
            display:flex;
            justify-content:center;
            align-items:center;
            gap:15px;
            padding:20px;
            flex-wrap:wrap;
        }
        .pagination a{
            background:#003366;
            color:white;
            text-decoration:none;
            padding:10px 18px;
            border-radius:8px;
            font-weight:bold;
            transition:background 0.3s;
        }
        .pagination a:hover{
            background:#00509e;
        }
        .page-indicator{
            padding:10px 18px;
            background:white;
            border-radius:8px;
            border:1px solid #dbe3ec;
            font-weight:700;
        }
        .alert-success{
            background:#d1fae5;
            color:#065f46;
            padding:12px 18px;
            border-radius:8px;
            margin-bottom:15px;
            border-left:4px solid #10b981;
            font-weight:600;
        }
        @media(max-width:900px){
            .search-grid{
                grid-template-columns:repeat(2,1fr);
            }
        }
        @media(max-width:600px){
            .search-grid{
                grid-template-columns:1fr;
            }
            .search-actions{
                flex-direction:column;
            }
            .search-actions .btn-search,
            .search-actions .btn-reset,
            .search-actions .btn-excel{
                width:100%;
                justify-content:center;
            }
            .result-stats{
                flex-direction:column;
                align-items:flex-start;
            }
        }
    </style>
</head>
<body>
<header><h1>🎓 GESTION DES ÉTUDIANTS - RECHERCHE AVANCÉE</h1></header>
<div class="container">

    <?php if($success == 'suppression'): ?>
        <div class="alert-success">✅ Étudiant supprimé avec succès !</div>
    <?php endif; ?>

    <!-- BARRE DE RECHERCHE AVANCÉE -->
    <div class="search-advanced">
        <div class="search-title">
            🔍 Recherche Avancée
            <span class="badge">Multi-critères</span>
        </div>
        <form method="GET" action="">
            <div class="search-grid">
                <div class="search-group">
                    <label>👤 Nom</label>
                    <input type="text" name="search_nom" placeholder="Ex: KERSAINT" value="<?= htmlspecialchars($search_nom) ?>">
                </div>
                <div class="search-group">
                    <label>👤 Prénom</label>
                    <input type="text" name="search_prenom" placeholder="Ex: Francis" value="<?= htmlspecialchars($search_prenom) ?>">
                </div>
                <div class="search-group">
                    <label>🎯 Matricule</label>
                    <input type="text" name="search_matricule" placeholder="Ex: FSG-2026-1234" value="<?= htmlspecialchars($search_matricule) ?>">
                </div>
                <div class="search-group">
                    <label>📧 Email</label>
                    <input type="email" name="search_email" placeholder="Ex: etudiant@email.com" value="<?= htmlspecialchars($search_email) ?>">
                </div>
                <div class="search-group">
                    <label>🏛️ Département</label>
                    <select name="dept" onchange="this.form.submit()">
                        <option value="0">-- Tous --</option>
                        <?php 
                        $departements->execute();
                        while($d = $departements->fetch(PDO::FETCH_ASSOC)): ?>
                            <option value="<?= $d['id_dept'] ?>" <?= ($filtreDept == $d['id_dept']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($d['nom_dept']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="search-group">
                    <label>🏫 Faculté</label>
                    <select name="faculte" <?= empty($filtreDept) ? 'disabled' : '' ?>>
                        <option value="">-- Toutes --</option>
                        <?php if(!empty($filtreDept) && !empty($faculteChoisie)): ?>
                            <option value="<?= $faculteChoisie ?>" <?= ($filtreFaculte == $faculteChoisie) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($faculteChoisie) ?>
                            </option>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="search-group">
                    <label>📚 Classe</label>
                    <select name="classe">
                        <option value="0">-- Toutes --</option>
                        <?php foreach($classesList as $c): ?>
                            <option value="<?= $c['id_classe'] ?>" <?= ($filtreClasse == $c['id_classe']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['niveau']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="search-group">
                    <label>⚥ Sexe</label>
                    <select name="sexe">
                        <option value="">-- Tous --</option>
                        <option value="Masculin" <?= ($filtreSexe == 'Masculin') ? 'selected' : '' ?>>Masculin</option>
                        <option value="Feminin" <?= ($filtreSexe == 'Feminin') ? 'selected' : '' ?>>Féminin</option>
                    </select>
                </div>
                <div class="search-group">
                    <label>📋 Titre</label>
                    <select name="titre">
                        <option value="">-- Tous --</option>
                        <option value="Nouveau" <?= ($filtreTitre == 'Nouveau') ? 'selected' : '' ?>>Nouveau</option>
                        <option value="Redoublant" <?= ($filtreTitre == 'Redoublant') ? 'selected' : '' ?>>Redoublant</option>
                    </select>
                </div>
                <div class="search-group">
                    <label>📅 Année Académique</label>
                    <select name="annee">
                        <option value="">-- Toutes --</option>
                        <option value="2024-2025" <?= ($filtreAnnee == '2024-2025') ? 'selected' : '' ?>>2024-2025</option>
                        <option value="2025-2026" <?= ($filtreAnnee == '2025-2026') ? 'selected' : '' ?>>2025-2026</option>
                        <option value="2026-2027" <?= ($filtreAnnee == '2026-2027') ? 'selected' : '' ?>>2026-2027</option>
                    </select>
                </div>
            </div>
            
            <div class="search-actions">
                <button type="submit" class="btn-search">🔍 Rechercher</button>
                <a href="AffichageEtudiants.php" class="btn-reset">✖️ Réinitialiser</a>
                
                <!-- ============================================================
                   BOUTON D'EXPORT EXCEL - INCLUS
                ============================================================ -->
                <?php 
                $export_url = 'export_excel_universel.php?table=etudiants&filename=Liste_Etudiants';
                if (!empty($_GET)) {
                    $export_url .= '&' . http_build_query($_GET);
                }
                ?>
                <a href="<?= $export_url ?>" class="btn-excel" onclick="return confirm('Exporter les données en Excel ?')">
                    📊 Exporter Excel
                </a>
            </div>
        </form>
    </div>

    <!-- STATISTIQUES DES RÉSULTATS -->
    <div class="result-stats">
        <div class="count">
            <strong><?= $total ?></strong> étudiant(s) trouvé(s)
        </div>
        <div class="filters-active">
            <?php if(!empty($search_nom)): ?>
                <span class="filter-tag">Nom: <?= htmlspecialchars($search_nom) ?></span>
            <?php endif; ?>
            <?php if(!empty($search_prenom)): ?>
                <span class="filter-tag">Prénom: <?= htmlspecialchars($search_prenom) ?></span>
            <?php endif; ?>
            <?php if(!empty($search_matricule)): ?>
                <span class="filter-tag">Matricule: <?= htmlspecialchars($search_matricule) ?></span>
            <?php endif; ?>
            <?php if($filtreDept > 0): ?>
                <span class="filter-tag">Département filtré</span>
            <?php endif; ?>
            <?php if(!empty($filtreSexe)): ?>
                <span class="filter-tag">Sexe: <?= htmlspecialchars($filtreSexe) ?></span>
            <?php endif; ?>
        </div>
    </div>

    <div class="add-container">
        <a href="ajouteetudiant.php" class="ajouter">+ Ajouter Étudiant</a>
    </div>
    
    <div class="table-card">
        <div class="table-header">
            <h2>📋 Liste des étudiants</h2>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Matricule</th><th>Nom</th><th>Prénom</th><th>Sexe</th>
                    <th>Email Perso</th><th>Email Acad.</th><th>Téléphone</th>
                    <th>Classe</th><th>Département</th><th>Faculté</th>
                    <th>Année</th><th>Titre</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if(count($etudiants) > 0): ?>
                <?php foreach($etudiants as $e): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($e['matricule'] ?: '-') ?></strong></td>
                    <td><?= htmlspecialchars($e['nom']) ?></td>
                    <td><?= htmlspecialchars($e['prenom']) ?></td>
                    <td><?= htmlspecialchars($e['sexe']) ?></td>
                    <td><?= htmlspecialchars($e['email_personnel'] ?: '-') ?></td>
                    <td style="color:#0066cc; font-weight:bold;"><?= htmlspecialchars($e['email_academique']) ?></td>
                    <td><?= htmlspecialchars($e['telephone'] ?: '-') ?></td>
                    <td><?= htmlspecialchars($e['niveau']) ?></td>
                    <td><?= htmlspecialchars($e['nom_dept']) ?></td>
                    <td><span class="faculte-badge"><?= htmlspecialchars($e['faculte'] ?: 'Non assignée') ?></span></td>
                    <td style="font-weight:600; color:#2e7d32;"><?= htmlspecialchars($e['annee_academique_entree'] ?: '-') ?></td>
                    <td><?= htmlspecialchars($e['titre']) ?></td>
                    <td class="actions-cell">
                        <a class="modifier" href="modifietudiant.php?id=<?= $e['id_etudiant']; ?>">✏️</a>
                        <a href="AffichageEtudiants.php?supp=<?= $e['id_etudiant']; ?>" class="supprimer" onclick="return confirm('Voulez-vous supprimer cet étudiant ?');">🗑️</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="13" style="padding:30px; color:#64748b; text-align:center;">📭 Aucun étudiant trouvé.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        
        <?php if($nbPages > 1): ?>
        <div class="pagination">
            <?php if($page > 1): ?>
                <a href="?<?= buildQuery(['page' => $page - 1]) ?>">← Précédent</a>
            <?php endif; ?>
            <div class="page-indicator">Page <?= $page ?> / <?= $nbPages ?></div>
            <?php if($page < $nbPages): ?>
                <a href="?<?= buildQuery(['page' => $page + 1]) ?>">Suivant →</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>