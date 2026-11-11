<?php
require_once __DIR__ . '/db.php';
// ============================================================
// afficheuser.php — Gestion des utilisateurs
// ✅ Correction : ob_start() pour permettre header() après menu.php
// ============================================================
ob_start();  // ⬅️ BUFFER DE SORTIE (corrige "headers already sent")

require_once 'auth.php';
requireRole('Administrateur');
include("menu.php");

/*=========================================
CONNEXION SÉCURISÉE
=========================================*/
try {
    $bdd = getDBConnection();
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Erreur de connexion à la base de données : " . $e->getMessage());
}

/*=========================================
GESTION DU TRI
=========================================*/
$tri = isset($_GET['tri']) ? $_GET['tri'] : 'date_creation';
$ordre = isset($_GET['ordre']) && $_GET['ordre'] === 'asc' ? 'ASC' : 'DESC';
$colonnes_autorisees = ['nom', 'prenom', 'username', 'email', 'role', 'statut', 'date_creation', 'derniere_connexion'];
if (!in_array($tri, $colonnes_autorisees)) $tri = 'date_creation';

function lien_tri($colonne, $label, $tri_actuel, $ordre_actuel) {
    $nouvel_ordre = ($tri_actuel === $colonne && $ordre_actuel === 'ASC') ? 'desc' : 'asc';
    $icone = ($tri_actuel === $colonne) ? ($ordre_actuel === 'ASC' ? ' ▲' : ' ▼') : '';
    $params = $_GET;
    $params['tri'] = $colonne;
    $params['ordre'] = $nouvel_ordre;
    $params['page'] = 1;
    $query = http_build_query($params);
    return '<a href="?' . $query . '" style="color:white; text-decoration:none;">' . $label . $icone . '</a>';
}

/*=========================================
SUPPRESSION D'UN UTILISATEUR
=========================================*/
if (isset($_GET["supp"]) && !empty($_GET["supp"])) {
    $id = (int)$_GET["supp"];
    try {
        $bdd->beginTransaction();
        $photoQuery = $bdd->prepare("SELECT photo FROM users WHERE id_user = ?");
        $photoQuery->execute([$id]);
        $data = $photoQuery->fetch(PDO::FETCH_ASSOC);
        if ($data && !empty($data["photo"]) && file_exists("photos/" . $data["photo"])) {
            unlink("photos/" . $data["photo"]);
        }
        $supp = $bdd->prepare("DELETE FROM users WHERE id_user = ?");
        $supp->execute([$id]);
        $bdd->commit();

        ob_end_clean();  // ⬅️ VIDER LE BUFFER AVANT LE HEADER
        header("Location: afficheuser.php?success=suppression");
        exit();
    } catch (Exception $e) {
        $bdd->rollBack();
        echo "<script>alert('Erreur lors de la suppression');</script>";
    }
}

/*=========================================
RECHERCHE AVANCÉE
=========================================*/
$search_nom = isset($_GET['search_nom']) ? trim($_GET['search_nom']) : '';
$search_prenom = isset($_GET['search_prenom']) ? trim($_GET['search_prenom']) : '';
$search_username = isset($_GET['search_username']) ? trim($_GET['search_username']) : '';
$search_email = isset($_GET['search_email']) ? trim($_GET['search_email']) : '';
$search_tel = isset($_GET['search_tel']) ? trim($_GET['search_tel']) : '';
$filtreRole = isset($_GET['role']) ? trim($_GET['role']) : '';
$filtreStatut = isset($_GET['statut']) ? trim($_GET['statut']) : '';

/*=========================================
PAGINATION
=========================================*/
$limite = 7;
$page = isset($_GET["page"]) ? (int)$_GET["page"] : 1;
if ($page < 1) $page = 1;
$debut = ($page - 1) * $limite;

/*=========================================
CONSTRUCTION DE LA REQUÊTE
=========================================*/
$where = array();
$params = array();

if (!empty($search_nom)) { $where[] = "u.nom LIKE ?"; $params[] = "%" . $search_nom . "%"; }
if (!empty($search_prenom)) { $where[] = "u.prenom LIKE ?"; $params[] = "%" . $search_prenom . "%"; }
if (!empty($search_username)) { $where[] = "u.username LIKE ?"; $params[] = "%" . $search_username . "%"; }
if (!empty($search_email)) { $where[] = "u.email LIKE ?"; $params[] = "%" . $search_email . "%"; }
if (!empty($search_tel)) { $where[] = "u.telephone LIKE ?"; $params[] = "%" . $search_tel . "%"; }
if (!empty($filtreRole)) { $where[] = "u.role = ?"; $params[] = $filtreRole; }
if (!empty($filtreStatut)) { $where[] = "u.statut = ?"; $params[] = $filtreStatut; }

$sqlWhere = "";
if (count($where) > 0) {
    $sqlWhere = " WHERE " . implode(" AND ", $where);
}

/*=========================================
CALCUL DU TOTAL
=========================================*/
$sqlTotal = "SELECT COUNT(*) AS total FROM users u $sqlWhere";
$stmtTotal = $bdd->prepare($sqlTotal);
$stmtTotal->execute($params);
$total = $stmtTotal->fetch(PDO::FETCH_ASSOC)["total"];

$nbPages = ceil($total / $limite);
if ($nbPages < 1) $nbPages = 1;
if ($page > $nbPages) { $page = $nbPages; $debut = ($page - 1) * $limite; }

/*=========================================
EXÉCUTION DE LA REQUÊTE PRINCIPALE
=========================================*/
$sql = "
    SELECT u.*, 
           dep.nom_dept, 
           dep.faculte 
    FROM users u
    LEFT JOIN doyens d ON u.id_user = d.id_user
    LEFT JOIN departements dep ON d.id_dept = dep.id_dept
    $sqlWhere 
    ORDER BY u.$tri $ordre 
    LIMIT $debut, $limite
";
$stmt = $bdd->prepare($sql);
$stmt->execute($params);

function buildQuery($extra = array()) {
    $query = $_GET;
    foreach ($extra as $key => $value) {
        $query[$key] = $value;
    }
    return http_build_query($query);
}

$success = isset($_GET['success']) ? $_GET['success'] : '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion des Utilisateurs - Recherche Avancée</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Arial, sans-serif; }
        body { background: #eef2f7; color: #1f2937; }
        .container { width: 95%; margin: 30px auto; }
        .page-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 22px; }
        .page-header h1 { color: #003366; font-size: 30px; font-weight: 800; }
        .ajouter { background: linear-gradient(135deg, #0066cc, #004c99); color: white; text-decoration: none; padding: 12px 18px; border-radius: 10px; font-weight: bold; transition: .3s; }
        .ajouter:hover { transform: translateY(-2px); }

        .search-card { background: white; border-radius: 18px; box-shadow: 0 10px 25px rgba(0, 0, 0, .07); padding: 20px; margin-bottom: 22px; border: 1px solid #e5e7eb; }
        .search-title { color: #003366; font-size: 18px; font-weight: bold; margin-bottom: 15px; display:flex; align-items:center; gap:10px; }
        .search-title .badge { background:#003366; color:white; font-size:11px; padding:2px 12px; border-radius:12px; }
        .search-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; }
        .search-group { display: flex; flex-direction: column; }
        .search-group label { font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase; margin-bottom: 5px; }
        .search-group input, .search-group select { width: 100%; height: 46px; border: 1px solid #d1d5db; border-radius: 10px; padding: 0 15px; outline: none; transition: .3s; background: white; }
        .search-group input:focus, .search-group select:focus { border-color: #00509e; box-shadow: 0 0 0 4px rgba(0, 80, 158, .10); }
        .search-actions { grid-column: 1 / -1; display: flex; justify-content: flex-end; gap: 10px; margin-top: 10px; flex-wrap:wrap; align-items:center; }
        .btn-search { background: #003366; color: white; border: none; padding: 12px 24px; border-radius: 10px; cursor: pointer; font-weight: bold; transition: .3s; }
        .btn-search:hover { background: #00509e; }
        .btn-reset { background: #e5e7eb; color: #111827; text-decoration: none; padding: 12px 24px; border-radius: 10px; font-weight: bold; transition: .3s; display:inline-flex; align-items:center; }
        .btn-reset:hover { background: #d1d5db; }
        .btn-excel { background: #10b981; color: white; border: none; padding: 12px 24px; border-radius: 10px; font-weight: bold; transition: .3s; text-decoration: none; display:inline-flex; align-items:center; gap:8px; }
        .btn-excel:hover { background: #059669; }

        .result-stats { background:white; padding:12px 20px; border-radius:12px; margin-bottom:15px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; border:1px solid #e5e7eb; }
        .result-stats .count { font-weight:600; }
        .result-stats .count strong { color:#003366; font-size:18px; }
        .filter-tag { background:#eff6ff; color:#1e40af; padding:4px 12px; border-radius:12px; font-size:12px; font-weight:600; }

        .table-card { background: white; border-radius: 18px; overflow: hidden; box-shadow: 0 10px 25px rgba(0, 0, 0, .07); border: 1px solid #e5e7eb; }
        .table-header { display: flex; justify-content: space-between; align-items: center; padding: 18px 22px; border-bottom: 1px solid #edf0f4; }
        .table-header h2 { color: #003366; }
        .table-info { color: #64748b; font-weight: bold; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; min-width: 1600px; border-collapse: collapse; }
        thead { background: #003366; color: white; }
        th { padding: 15px; text-align: center; font-size: 14px; }
        th a:hover { text-decoration: underline !important; opacity: 0.9; }
        td { padding: 14px; text-align: center; border-bottom: 1px solid #eef2f7; }
        tbody tr:hover { background: #f8fbff; }
        .photo { width: 60px; height: 60px; border-radius: 50%; object-fit: cover; border: 2px solid #ddd; }
        .badge { display: inline-block; padding: 6px 12px; border-radius: 999px; font-size: 12px; font-weight: bold; }
        .role { background: #eff6ff; color: #1d4ed8; }
        .statut.actif { background: #dcfce7; color: #166534; }
        .statut.inactif { background: #fee2e2; color: #991b1b; }
        .email-auth { font-family: monospace; font-size: 12px; color: #0066cc; font-weight: bold; }
        .dept-badge { background: #f3e8ff; color: #6b21a8; padding: 4px 10px; border-radius: 8px; font-size: 11px; font-weight: 600; display:inline-block; }
        .btn { display: inline-block; text-decoration: none; color: white; padding: 8px 14px; border-radius: 8px; font-size: 13px; font-weight: bold; transition: .3s; }
        .modifier { background: #16a34a; }
        .modifier:hover { background: #15803d; }
        .supprimer { background: #dc2626; }
        .supprimer:hover { background: #b91c1c; }
        .pagination { display: flex; justify-content: center; align-items: center; gap: 15px; margin-top: 25px; flex-wrap: wrap; padding:15px; }
        .pagination a { background: #003366; color: white; text-decoration: none; padding: 11px 18px; border-radius: 10px; font-weight: bold; transition: .3s; }
        .pagination a:hover { background: #00509e; }
        .page-indicator { padding: 10px 18px; border: 1px solid #dbe3ec; border-radius: 10px; background: white; color: #003366; font-weight: bold; }
        .alert-success { background:#d1fae5; color:#065f46; padding:12px 18px; border-radius:8px; margin-bottom:15px; border-left:4px solid #10b981; font-weight:600; }
        footer { margin-top: 30px; background: #003366; color: white; text-align: center; padding: 15px; }
        @media(max-width: 1000px) { .search-grid { grid-template-columns: 1fr 1fr; } }
        @media(max-width: 700px) { .page-header { flex-direction: column; align-items: flex-start; } .search-grid { grid-template-columns: 1fr; } .search-actions { flex-direction:column; } .search-actions .btn-search, .search-actions .btn-reset, .search-actions .btn-excel { width:100%; justify-content:center; } }
    </style>
</head>
<body>

<div class="container">

    <div class="page-header">
        <h1>👥 Gestion des Utilisateurs</h1>
        <a href="ajouteuser.php" class="ajouter">+ Ajouter un utilisateur</a>
    </div>

    <?php if($success == 'suppression'): ?>
        <div class="alert-success">✅ Utilisateur supprimé avec succès !</div>
    <?php endif; ?>

    <!-- RECHERCHE AVANCÉE -->
    <div class="search-card">
        <div class="search-title">
            🔍 Recherche Avancée
            <span class="badge">Multi-critères</span>
        </div>
        <form method="GET" class="search-grid">
            <div class="search-group">
                <label>👤 Nom</label>
                <input type="text" name="search_nom" placeholder="Ex: KERSAINT" value="<?= htmlspecialchars($search_nom) ?>">
            </div>
            <div class="search-group">
                <label>👤 Prénom</label>
                <input type="text" name="search_prenom" placeholder="Ex: Francis" value="<?= htmlspecialchars($search_prenom) ?>">
            </div>
            <div class="search-group">
                <label>🔑 Username</label>
                <input type="text" name="search_username" placeholder="Ex: f.mizero" value="<?= htmlspecialchars($search_username) ?>">
            </div>
            <div class="search-group">
                <label>📧 Email</label>
                <input type="email" name="search_email" placeholder="Ex: user@email.com" value="<?= htmlspecialchars($search_email) ?>">
            </div>
            <div class="search-group">
                <label>📱 Téléphone</label>
                <input type="text" name="search_tel" placeholder="Ex: +257 66..." value="<?= htmlspecialchars($search_tel) ?>">
            </div>
            <div class="search-group">
                <label>🎯 Rôle</label>
                <select name="role">
                    <option value="">-- Tous --</option>
                    <option value="Administrateur" <?= ($filtreRole == "Administrateur") ? 'selected' : ''; ?>>Administrateur</option>
                    <option value="Professeur" <?= ($filtreRole == "Professeur") ? 'selected' : ''; ?>>Professeur</option>
                    <option value="Comptable" <?= ($filtreRole == "Comptable") ? 'selected' : ''; ?>>Comptable</option>
                    <option value="Etudiant" <?= ($filtreRole == "Etudiant") ? 'selected' : ''; ?>>Étudiant</option>
                    <option value="Lecteur" <?= ($filtreRole == "Lecteur") ? 'selected' : ''; ?>>Lecteur</option>
                    <option value="Doyen" <?= ($filtreRole == "Doyen") ? 'selected' : ''; ?>>Doyen</option>
                </select>
            </div>
            <div class="search-group">
                <label>📊 Statut</label>
                <select name="statut">
                    <option value="">-- Tous --</option>
                    <option value="Actif" <?= ($filtreStatut == "Actif") ? 'selected' : ''; ?>>Actif</option>
                    <option value="Inactif" <?= ($filtreStatut == "Inactif") ? 'selected' : ''; ?>>Inactif</option>
                </select>
            </div>
            <div class="search-actions">
                <button type="submit" class="btn-search">🔍 Rechercher</button>
                <a href="afficheuser.php" class="btn-reset">✖️ Réinitialiser</a>

                <?php
                $export_url = 'export_excel_universel.php?table=users&filename=Liste_Utilisateurs';
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

    <!-- STATISTIQUES -->
    <div class="result-stats">
        <div class="count"><strong><?= $total ?></strong> utilisateur(s) trouvé(s)</div>
        <div>
            <?php if(!empty($search_nom)): ?><span class="filter-tag">Nom: <?= htmlspecialchars($search_nom) ?></span><?php endif; ?>
            <?php if(!empty($filtreRole)): ?><span class="filter-tag">Rôle: <?= htmlspecialchars($filtreRole) ?></span><?php endif; ?>
            <?php if(!empty($filtreStatut)): ?><span class="filter-tag">Statut: <?= htmlspecialchars($filtreStatut) ?></span><?php endif; ?>
        </div>
    </div>

    <div class="table-card">
        <div class="table-header">
            <h2>📋 Liste des utilisateurs</h2>
            <div class="table-info"><?= $total ?> utilisateur(s)</div>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>N°</th>
                        <th>Photo</th>
                        <th><?= lien_tri('nom', 'Nom', $tri, $ordre) ?></th>
                        <th><?= lien_tri('prenom', 'Prénom', $tri, $ordre) ?></th>
                        <th><?= lien_tri('username', 'Username', $tri, $ordre) ?></th>
                        <th><?= lien_tri('email', 'Email', $tri, $ordre) ?></th>
                        <th>Email Académique</th>
                        <th>Téléphone</th>
                        <th>Sexe</th>
                        <th><?= lien_tri('role', 'Rôle', $tri, $ordre) ?></th>
                        <th>Département (Doyen)</th>
                        <th><?= lien_tri('statut', 'Statut', $tri, $ordre) ?></th>
                        <th><?= lien_tri('derniere_connexion', 'Dernière connexion', $tri, $ordre) ?></th>
                        <th><?= lien_tri('date_creation', 'Date création', $tri, $ordre) ?></th>
                        <th colspan="2">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $numero = $debut + 1;
                while ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $image = "photos/default.png";
                    if (!empty($user["photo"]) && file_exists("photos/" . $user["photo"])) {
                        $image = "photos/" . $user["photo"];
                    }
                ?>
                    <tr>
                        <td><?= $numero++ ?></td>
                        <td><img src="<?= $image; ?>" class="photo" alt="Avatar"></td>
                        <td><?= htmlspecialchars($user['nom']) ?></td>
                        <td><?= htmlspecialchars($user['prenom']) ?></td>
                        <td><?= htmlspecialchars($user['username']) ?></td>
                        <td><?= htmlspecialchars($user['email']) ?></td>
                        <td class="email-auth">
                            <?= htmlspecialchars($user['email_auth'] ?? '—') ?>
                        </td>
                        <td><?= htmlspecialchars($user['telephone']) ?></td>
                        <td><?= htmlspecialchars($user['sexe']) ?></td>
                        <td><span class="badge role"><?= htmlspecialchars($user['role']) ?></span></td>

                        <!-- AFFICHAGE DU DEPARTEMENT -->
                        <td>
                            <?php if ($user['role'] === 'Doyen' && $user['nom_dept']): ?>
                                <span class="dept-badge">
                                    <?= htmlspecialchars($user['nom_dept']) ?><br>
                                    <small style="font-weight:normal; color:#8b5cf6; font-size:10px;"><?= htmlspecialchars($user['faculte'] ?: 'Sans fac') ?></small>
                                </span>
                            <?php else: ?>
                                <span style="color:#cbd5e1;">-</span>
                            <?php endif; ?>
                        </td>

                        <td><span class="badge statut <?= strtolower($user['statut']) ?>"><?= htmlspecialchars($user["statut"]) ?></span></td>
                        <td><?= !empty($user["derniere_connexion"]) ? date("d/m/Y H:i", strtotime($user["derniere_connexion"])) : "-" ?></td>
                        <td><?= date("d/m/Y H:i", strtotime($user["date_creation"])) ?></td>
                        <td><a href="modifiuser.php?id=<?= $user['id_user']; ?>" class="btn modifier">✏️</a></td>
                        <td><a href="afficheuser.php?supp=<?= $user['id_user']; ?>" class="btn supprimer" onclick="return confirm('Voulez-vous supprimer cet utilisateur ?');">🗑️</a></td>
                    </tr>
                <?php } ?>
                <?php if ($total == 0) { ?>
                    <tr><td colspan="16" style="padding:30px; color:#64748b; text-align:center;">📭 Aucun utilisateur trouvé.</td></tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="pagination">
        <?php if ($page > 1) { ?>
            <a href="?<?= buildQuery(['page' => $page - 1]) ?>">← Précédent</a>
        <?php } ?>
        <div class="page-indicator">Page <?= $page ?> / <?= $nbPages ?></div>
        <?php if ($page < $nbPages) { ?>
            <a href="?<?= buildQuery(['page' => $page + 1]) ?>">Suivant →</a>
        <?php } ?>
    </div>

    <footer>© <?= date("Y") ?> Université - Gestion des utilisateurs</footer>

</div>
</body>
</html>