<?php
require_once __DIR__ . '/db.php';
require_once 'auth.php';
requireRole(['Comptable', 'Administrateur']);

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
   UTILISATEUR CONNECTÉ (via auth.php)
============================================================ */
$id_user = $USER_ID;
$nom_complet = $USER_NAME;

$photo_profil = (!empty($USER_PHOTO) && file_exists("uploads/" . $USER_PHOTO)) ? $USER_PHOTO : 'default.png';

/* ============================================================
   CSRF
============================================================ */
if (empty($_SESSION['csrf_bordereaux'])) {
    $_SESSION['csrf_bordereaux'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_bordereaux'];

$message = "";
$message_type = "";

/* ============================================================
   VALIDER UN PAIEMENT
============================================================ */
if (isset($_GET['valider']) && !empty($_GET['valider'])) {
    if (!isset($_GET['csrf']) || !hash_equals($csrf_token, $_GET['csrf'])) {
        $_SESSION['message_bordereaux'] = "<div class='alert error'>❌ Jeton de sécurité invalide.</div>";
        header("Location: validation_bordereaux.php");
        exit();
    }

    $id_paiement = intval($_GET['valider']);
    try {
        $bdd->beginTransaction();

        $check = $bdd->prepare("SELECT p.*, e.id_etudiant, u.nom, u.prenom, e.matricule 
                                FROM paiements p
                                JOIN etudiants e ON p.id_etudiant = e.id_etudiant
                                JOIN users u ON e.id_user = u.id_user
                                WHERE p.id_paiement = ?");
        $check->execute([$id_paiement]);
        $paiement = $check->fetch(PDO::FETCH_ASSOC);

        if (!$paiement) {
            throw new Exception("Paiement introuvable.");
        }
        if ($paiement['statut'] !== 'en_attente') {
            throw new Exception("Ce paiement n'est plus en attente de validation.");
        }

        $checkDoublon = $bdd->prepare("SELECT COUNT(*) FROM paiements WHERE bordereau = ? AND statut = 'valide' AND id_paiement != ?");
        $checkDoublon->execute([$paiement['bordereau'], $id_paiement]);
        if ($checkDoublon->fetchColumn() > 0) {
            throw new Exception("⚠ Ce numéro de bordereau existe déjà dans un paiement validé.");
        }

        $upd = $bdd->prepare("UPDATE paiements SET statut = 'valide', date_validation = NOW(), valide_par = ? WHERE id_paiement = ?");
        $upd->execute([$id_user, $id_paiement]);

        $num_recu = 'REC-' . date('Ymd') . '-' . str_pad($id_paiement, 6, '0', STR_PAD_LEFT);
        $updRecu = $bdd->prepare("UPDATE paiements SET numero_recu = ?, date_recu = NOW() WHERE id_paiement = ?");
        $updRecu->execute([$num_recu, $id_paiement]);

        $hist = $bdd->prepare("INSERT INTO historique_paiements (id_paiement, id_etudiant, action, ancien_statut, nouveau_statut, date_action) VALUES (?, ?, 'validation', 'en_attente', 'valide', NOW())");
        $hist->execute([$id_paiement, $paiement['id_etudiant']]);

        $notif = $bdd->prepare("INSERT INTO notifications (titre, message, type_evenement, statut, date_creation) 
                                VALUES (?, ?, 'SUCCES', 'non_lu', NOW())");
        $notif->execute([
            '✅ Paiement validé',
            'Votre paiement de ' . number_format($paiement['montant'], 0, ',', ' ') . ' BIF (bordereau ' . htmlspecialchars($paiement['bordereau']) . ') a été validé. Reçu : ' . $num_recu
        ]);

        try {
            require_once 'functions_email.php';
            $stmt_email = $bdd->prepare("SELECT u.email, u.nom, u.prenom FROM etudiants e JOIN users u ON e.id_user = u.id_user WHERE e.id_etudiant = ?");
            $stmt_email->execute([$paiement['id_etudiant']]);
            $etu_email = $stmt_email->fetch(PDO::FETCH_ASSOC);
            if ($etu_email) {
                $contenu = '<p>Bonjour <strong>' . htmlspecialchars($etu_email['prenom']) . '</strong>,</p>
                    <p>Votre paiement a été <strong style="color:#16a34a;">validé</strong> par le service comptable.</p>
                    <ul>
                        <li><strong>Montant :</strong> ' . number_format($paiement['montant'], 0, ',', ' ') . ' BIF</li>
                        <li><strong>Bordereau :</strong> ' . htmlspecialchars($paiement['bordereau']) . '</li>
                        <li><strong>Numéro de reçu :</strong> ' . htmlspecialchars($num_recu) . '</li>
                    </ul>
                    <p>Vous pouvez télécharger votre reçu depuis votre espace étudiant.</p>';
                envoyerEmail($etu_email['email'], 'Paiement validé - UniVerse', templateEmail('Paiement validé', $contenu, '#16a34a'));
            }
        } catch (Exception $mailEx) {
            error_log("[validation_bordereaux] Email : " . $mailEx->getMessage());
        }

        $bdd->commit();
        $_SESSION['message_bordereaux'] = "<div class='alert success'>✅ Bordereau validé avec succès. Reçu généré : " . $num_recu . "</div>";

    } catch (Exception $e) {
        $bdd->rollBack();
        $_SESSION['message_bordereaux'] = "<div class='alert error'>❌ Erreur : " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    header("Location: validation_bordereaux.php");
    exit();
}

/* ============================================================
   REJETER UN PAIEMENT
============================================================ */
if (isset($_POST['rejeter'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($csrf_token, $_POST['csrf_token'])) {
        $_SESSION['message_bordereaux'] = "<div class='alert error'>❌ Jeton de sécurité invalide.</div>";
        header("Location: validation_bordereaux.php");
        exit();
    }

    $id_paiement = intval($_POST['id_paiement']);
    $motif = trim($_POST['motif_rejet']);

    if (empty($motif)) {
        $_SESSION['message_bordereaux'] = "<div class='alert error'>❌ Le motif de rejet est obligatoire.</div>";
        header("Location: validation_bordereaux.php");
        exit();
    }

    try {
        $bdd->beginTransaction();
        $check = $bdd->prepare("SELECT p.*, e.id_etudiant FROM paiements p JOIN etudiants e ON p.id_etudiant = e.id_etudiant WHERE p.id_paiement = ?");
        $check->execute([$id_paiement]);
        $paiement = $check->fetch(PDO::FETCH_ASSOC);

        if (!$paiement) {
            throw new Exception("Paiement introuvable.");
        }
        if ($paiement['statut'] !== 'en_attente') {
            throw new Exception("Ce paiement n'est plus en attente.");
        }

        $upd = $bdd->prepare("UPDATE paiements SET statut = 'rejete', motif_rejet = ?, date_validation = NOW(), valide_par = ? WHERE id_paiement = ?");
        $upd->execute([$motif, $id_user, $id_paiement]);

        $hist = $bdd->prepare("INSERT INTO historique_paiements (id_paiement, id_etudiant, action, ancien_statut, nouveau_statut, date_action) VALUES (?, ?, 'rejet', 'en_attente', 'rejete', NOW())");
        $hist->execute([$id_paiement, $paiement['id_etudiant']]);

        $notif = $bdd->prepare("INSERT INTO notifications (titre, message, type_evenement, statut, date_creation) 
                                VALUES (?, ?, 'ALERT', 'non_lu', NOW())");
        $notif->execute([
            '❌ Paiement rejeté',
            'Votre paiement de ' . number_format($paiement['montant'], 0, ',', ' ') . ' BIF (bordereau ' . htmlspecialchars($paiement['bordereau']) . ') a été rejeté. Motif : ' . htmlspecialchars($motif)
        ]);

        $bdd->commit();
        $_SESSION['message_bordereaux'] = "<div class='alert error'>⛔ Bordereau rejeté. Motif : " . htmlspecialchars($motif) . "</div>";

    } catch (Exception $e) {
        $bdd->rollBack();
        $_SESSION['message_bordereaux'] = "<div class='alert error'>❌ Erreur : " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    header("Location: validation_bordereaux.php");
    exit();
}

if (isset($_SESSION['message_bordereaux'])) {
    $message = $_SESSION['message_bordereaux'];
    unset($_SESSION['message_bordereaux']);
}

/* ============================================================
   RÉCUPÉRATION DES BORDEREAUX
============================================================ */
$filtre_dept = isset($_GET['filtre_dept']) ? intval($_GET['filtre_dept']) : 0;
$filtre_classe = isset($_GET['filtre_classe']) ? intval($_GET['filtre_classe']) : 0;
$filtre_banque = isset($_GET['filtre_banque']) ? trim($_GET['filtre_banque']) : '';
$filtre_date_debut = isset($_GET['filtre_date_debut']) ? trim($_GET['filtre_date_debut']) : '';
$filtre_date_fin = isset($_GET['filtre_date_fin']) ? trim($_GET['filtre_date_fin']) : '';
$recherche = isset($_GET['recherche']) ? trim($_GET['recherche']) : '';

$where = ["p.statut = 'en_attente'"];
$params = [];

if ($filtre_dept > 0) {
    $where[] = "d.id_dept = ?";
    $params[] = $filtre_dept;
}
if ($filtre_classe > 0) {
    $where[] = "c.id_classe = ?";
    $params[] = $filtre_classe;
}
if (!empty($filtre_banque)) {
    $where[] = "p.banque LIKE ?";
    $params[] = "%" . $filtre_banque . "%";
}
if (!empty($filtre_date_debut)) {
    $where[] = "p.date_paiement >= ?";
    $params[] = $filtre_date_debut;
}
if (!empty($filtre_date_fin)) {
    $where[] = "p.date_paiement <= ?";
    $params[] = $filtre_date_fin;
}
if (!empty($recherche)) {
    $where[] = "(u.nom LIKE ? OR u.prenom LIKE ? OR e.matricule LIKE ? OR p.bordereau LIKE ?)";
    $search = "%" . $recherche . "%";
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
}

$sqlWhere = " WHERE " . implode(" AND ", $where);

$limite = 10;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
if ($page < 1) $page = 1;
$debut = ($page - 1) * $limite;

$sqlTotal = "SELECT COUNT(*) as total FROM paiements p 
              JOIN etudiants e ON p.id_etudiant = e.id_etudiant 
              JOIN users u ON e.id_user = u.id_user 
              JOIN classes c ON e.id_classe = c.id_classe
              JOIN departements d ON c.id_dept = d.id_dept
              $sqlWhere";
$stmtTotal = $bdd->prepare($sqlTotal);
$stmtTotal->execute($params);
$total = $stmtTotal->fetch(PDO::FETCH_ASSOC)['total'];
$nbPages = ceil($total / $limite) < 1 ? 1 : ceil($total / $limite);

$sql = "SELECT p.*, u.nom, u.prenom, e.matricule, c.niveau, d.nom_dept, d.faculte
        FROM paiements p 
        JOIN etudiants e ON p.id_etudiant = e.id_etudiant 
        JOIN users u ON e.id_user = u.id_user 
        JOIN classes c ON e.id_classe = c.id_classe
        JOIN departements d ON c.id_dept = d.id_dept
        $sqlWhere
        ORDER BY p.date_paiement ASC
        LIMIT $debut, $limite";
$stmt = $bdd->prepare($sql);
$stmt->execute($params);
$bordereaux = $stmt->fetchAll(PDO::FETCH_ASSOC);

$departements = $bdd->query("SELECT id_dept, nom_dept FROM departements ORDER BY nom_dept")->fetchAll();
$classes = $bdd->query("SELECT id_classe, niveau FROM classes ORDER BY niveau")->fetchAll();
$banques = $bdd->query("SELECT DISTINCT banque FROM paiements WHERE banque IS NOT NULL AND banque != '' ORDER BY banque")->fetchAll();

$totalValides = $bdd->query("SELECT COUNT(*) FROM paiements WHERE statut = 'valide'")->fetchColumn();
$totalRejetes = $bdd->query("SELECT COUNT(*) FROM paiements WHERE statut = 'rejete'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Centre de validation des bordereaux — UniVerse</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --bg-light: #f1f5f9; --sidebar-color: #0f172a; --text-main: #1e293b;
            --text-muted: #64748b; --text-white: #ffffff; --accent-blue: #2563eb;
            --success: #10b981; --warning: #f59e0b; --error: #ef4444;
            --border-color: #e2e8f0; --radius: 12px;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.06); --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', system-ui, sans-serif; }
        body { background-color: var(--bg-light); color: var(--text-main); display: flex; min-height: 100vh; }

        .sidebar { width: 260px; background-color: var(--sidebar-color); color: var(--text-white); padding: 30px 20px; position: fixed; height: 100vh; display: flex; flex-direction: column; overflow-y: auto; z-index: 100; }
        .sidebar .logo { font-size: 24px; font-weight: 800; margin-bottom: 25px; }
        .user-profile { background: rgba(255,255,255,0.05); padding: 15px; border-radius: var(--radius); margin-bottom: 25px; text-align: center; border: 1px solid rgba(255,255,255,0.06); }
        .profile-img { width: 60px; height: 60px; border-radius: 50%; object-fit: cover; margin-bottom: 8px; border: 2px solid var(--success); }
        .user-profile .name { font-weight: 600; font-size: 14px; }
        .user-profile .title { font-size: 11px; color: #94a3b8; }
        .badge { display: inline-block; padding: 4px 8px; font-size: 11px; font-weight: 700; text-transform: uppercase; border-radius: 6px; margin-top: 6px; background: var(--success); color: white; }
        .nav-links { list-style: none; display: flex; flex-direction: column; gap: 4px; flex: 1; }
        .nav-links a { color: #94a3b8; text-decoration: none; padding: 10px 14px; border-radius: 8px; display: flex; align-items: center; gap: 12px; font-weight: 600; font-size: 14px; transition: all 0.2s; }
        .nav-links a:hover, .nav-links a.active { background: rgba(255,255,255,0.08); color: var(--text-white); }
        .nav-links a.logout { color: var(--error); margin-top: auto; }
        .main-content { margin-left: 260px; width: calc(100% - 260px); padding: 30px 35px; min-height: 100vh; }
        header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; border-bottom: 1px solid var(--border-color); padding-bottom: 15px; flex-wrap: wrap; gap: 10px; }
        header h1 { font-size: 24px; color: #1e293b; }
        header h1 i { color: var(--warning); margin-right: 10px; }
        .header-actions .btn { padding: 8px 16px; border-radius: 8px; border: none; font-weight: 600; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; font-size: 13px; }
        .btn-primary { background: var(--accent-blue); color: white; }
        .btn-primary:hover { background: #1d4ed8; transform: translateY(-2px); }
        .alert { padding: 12px 18px; border-radius: 8px; margin-bottom: 15px; font-weight: 600; display: flex; align-items: center; gap: 10px; animation: slideDown 0.4s ease; font-size: 13px; }
        .alert.success { background: #d1fae5; color: #065f46; border-left: 4px solid var(--success); }
        .alert.error { background: #fee2e2; color: #991b1b; border-left: 4px solid var(--error); }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .panel { background: white; padding: 20px 25px; border-radius: var(--radius); border: 1px solid var(--border-color); margin-bottom: 20px; box-shadow: var(--shadow-sm); }
        .panel-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px; }
        .panel-header h2 { font-size: 18px; color: #1e293b; display: flex; align-items: center; gap: 8px; }
        .badge-count { background: var(--warning); color: #0f172a; padding: 2px 12px; border-radius: 12px; font-size: 13px; font-weight: 700; }
        .filter-bar { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; background: #f8fafc; padding: 12px 18px; border-radius: var(--radius); border: 1px solid var(--border-color); }
        .filter-bar input, .filter-bar select { padding: 8px 12px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 13px; background: white; outline: none; flex: 1; min-width: 140px; }
        .filter-bar input:focus, .filter-bar select:focus { border-color: var(--accent-blue); }
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { background: #f8fafc; padding: 10px 14px; color: #64748b; font-weight: 600; border-bottom: 2px solid var(--border-color); font-size: 11px; text-transform: uppercase; letter-spacing: 0.3px; text-align: left; }
        td { padding: 10px 14px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        tr:hover td { background: #f8fafc; }
        .status-badge { display: inline-block; padding: 3px 12px; border-radius: 12px; font-size: 11px; font-weight: 600; }
        .status-badge.en_attente { background: #fef3c7; color: #92400e; }
        .btn-action { padding: 4px 10px; border-radius: 6px; border: none; font-size: 12px; font-weight: 600; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; gap: 4px; text-decoration: none; }
        .btn-action.success { background: #d1fae5; color: #065f46; }
        .btn-action.success:hover { background: var(--success); color: white; }
        .btn-action.danger { background: #fee2e2; color: #991b1b; }
        .btn-action.danger:hover { background: var(--error); color: white; }
        .pagination { display: flex; justify-content: center; align-items: center; gap: 10px; margin-top: 20px; flex-wrap: wrap; }
        .pagination a { background: var(--accent-blue); color: white; text-decoration: none; padding: 6px 14px; border-radius: 6px; font-weight: 600; transition: all 0.2s; }
        .pagination a:hover { background: #1d4ed8; transform: translateY(-2px); }
        .pagination .active { background: #1e293b; }
        .page-indicator { padding: 6px 14px; background: white; border-radius: 6px; border: 1px solid var(--border-color); font-weight: 600; }
        .modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; display: none; justify-content: center; align-items: center; }
        .modal-overlay.active { display: flex; }
        .modal { background: white; padding: 30px; border-radius: var(--radius); max-width: 550px; width: 100%; max-height: 90vh; overflow-y: auto; box-shadow: var(--shadow-md); }
        .modal h3 { margin-bottom: 15px; }
        .modal .form-group { margin-bottom: 12px; }
        .modal .form-group label { font-weight: 600; display: block; margin-bottom: 4px; font-size: 13px; }
        .modal .form-group textarea, .modal .form-group select { width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-color); }
        .modal .btn-group { display: flex; gap: 10px; justify-content: flex-end; margin-top: 15px; }
        .faculte-badge { display: inline-block; padding: 2px 10px; border-radius: 12px; background: #eff6ff; color: #1e40af; font-weight: 600; font-size: 11px; }
        .stats-mini { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-bottom: 20px; }
        .stat-mini { background: white; padding: 12px 16px; border-radius: var(--radius); border: 1px solid var(--border-color); text-align: center; }
        .stat-mini .nombre { font-size: 22px; font-weight: 700; }
        .stat-mini .label { font-size: 11px; color: var(--text-muted); text-transform: uppercase; font-weight: 600; }
        @media (max-width: 768px) {
            body { flex-direction: column; }
            .sidebar { width: 100%; height: auto; position: relative; padding: 15px; }
            .main-content { margin-left: 0; width: 100%; padding: 15px; }
            header { flex-direction: column; align-items: flex-start; }
            .filter-bar { flex-direction: column; }
            .filter-bar input, .filter-bar select { width: 100%; }
            .stats-mini { grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="logo">🎓 UniVerse</div>
        <div class="user-profile">
            <img src="uploads/<?= htmlspecialchars($photo_profil); ?>" alt="Photo" class="profile-img">
            <p class="name"><?= htmlspecialchars($nom_complet); ?></p>
            <p class="title">Agent Comptable</p>
            <span class="badge"><?= htmlspecialchars($USER_ROLE); ?></span>
        </div>
        <ul class="nav-links">
            <li><a href="dashboard_comptable.php"><i class="fas fa-chart-line"></i> Tableau de Bord</a></li>
            <li><a href="validation_bordereaux.php" class="active"><i class="fas fa-file-invoice"></i> Validation bordereaux</a></li>
            <li><a href="mon_profil.php"><i class="fas fa-user"></i> Mon Profil</a></li>
            <li><a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
        </ul>
    </div>

    <div class="main-content">

        <header>
            <h1><i class="fas fa-file-invoice"></i> Centre de validation des bordereaux</h1>
            <div class="header-actions">
                <a href="dashboard_comptable.php" class="btn btn-primary"><i class="fas fa-arrow-left"></i> Retour</a>
            </div>
        </header>

        <?php if (!empty($message)): ?>
            <?= $message; ?>
        <?php endif; ?>

        <div class="stats-mini">
            <div class="stat-mini">
                <div class="nombre" style="color:var(--warning);"><?= $total; ?></div>
                <div class="label">⏳ En attente</div>
            </div>
            <div class="stat-mini">
                <div class="nombre" style="color:var(--success);"><?= $totalValides; ?></div>
                <div class="label">✅ Validés</div>
            </div>
            <div class="stat-mini">
                <div class="nombre" style="color:var(--error);"><?= $totalRejetes; ?></div>
                <div class="label">❌ Rejetés</div>
            </div>
        </div>

        <div class="panel">
            <form method="GET" class="filter-bar">
                <input type="text" name="recherche" placeholder="🔍 Rechercher..." value="<?= htmlspecialchars($recherche); ?>">
                <select name="filtre_dept">
                    <option value="0">🏛️ Département</option>
                    <?php foreach($departements as $d): ?>
                        <option value="<?= $d['id_dept']; ?>" <?= ($filtre_dept == $d['id_dept']) ? 'selected' : ''; ?>><?= htmlspecialchars($d['nom_dept']); ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="filtre_classe">
                    <option value="0">📚 Classe</option>
                    <?php foreach($classes as $c): ?>
                        <option value="<?= $c['id_classe']; ?>" <?= ($filtre_classe == $c['id_classe']) ? 'selected' : ''; ?>><?= htmlspecialchars($c['niveau']); ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="filtre_banque">
                    <option value="">🏦 Banque</option>
                    <?php foreach($banques as $b): ?>
                        <option value="<?= htmlspecialchars($b['banque']); ?>" <?= ($filtre_banque == $b['banque']) ? 'selected' : ''; ?>><?= htmlspecialchars($b['banque']); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="date" name="filtre_date_debut" value="<?= $filtre_date_debut; ?>" placeholder="Date début">
                <input type="date" name="filtre_date_fin" value="<?= $filtre_date_fin; ?>" placeholder="Date fin">
                <button type="submit" class="btn btn-primary" style="padding:8px 16px;"><i class="fas fa-search"></i> Filtrer</button>
                <a href="validation_bordereaux.php" class="btn btn-primary" style="background:#64748b; padding:8px 16px; text-decoration:none; color:white; border-radius:8px;"><i class="fas fa-times"></i> Réinitialiser</a>
            </form>
        </div>

        <div class="panel">
            <div class="panel-header">
                <h2><i class="fas fa-list"></i> Bordereaux en attente <span class="badge-count"><?= $total; ?></span></h2>
                <span style="font-size:13px; color:var(--text-muted);"><?= $total; ?> bordereaux trouvés</span>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Étudiant</th>
                            <th>Matricule</th>
                            <th>Département</th>
                            <th>Classe</th>
                            <th>Montant</th>
                            <th>Banque</th>
                            <th>Bordereau</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($bordereaux) > 0): ?>
                            <?php foreach ($bordereaux as $b): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($b['nom'] . ' ' . $b['prenom']); ?></strong></td>
                                    <td><?= htmlspecialchars($b['matricule']); ?></td>
                                    <td><?= htmlspecialchars($b['nom_dept']); ?></td>
                                    <td><?= htmlspecialchars($b['niveau']); ?></td>
                                    <td><strong><?= number_format($b['montant'], 0, ',', ' '); ?> BIF</strong></td>
                                    <td><span class="faculte-badge"><?= htmlspecialchars($b['banque'] ?? '—'); ?></span></td>
                                    <td><span style="background:#f1f5f9; padding:2px 8px; border-radius:4px; font-family:monospace;"><?= htmlspecialchars($b['bordereau'] ?? '—'); ?></span></td>
                                    <td><?= date('d/m/Y', strtotime($b['date_paiement'])); ?></td>
                                    <td>
                                        <a href="?valider=<?= $b['id_paiement']; ?>&csrf=<?= urlencode($csrf_token); ?>" class="btn-action success" onclick="return confirm('Valider ce paiement ?')"><i class="fas fa-check"></i></a>
                                        <button class="btn-action danger" onclick="ouvrirModalRejet(<?= $b['id_paiement']; ?>, '<?= htmlspecialchars($b['nom'] . ' ' . $b['prenom'], ENT_QUOTES); ?>', '<?= htmlspecialchars($b['bordereau'], ENT_QUOTES); ?>')"><i class="fas fa-times"></i></button>
                                        <a href="#" class="btn-action primary"><i class="fas fa-eye"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" style="text-align:center; color:#94a3b8; padding:30px;">
                                    <i class="fas fa-check-circle" style="font-size:32px; color:var(--success); display:block; margin-bottom:10px;"></i>
                                    Aucun bordereau en attente de validation.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($nbPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1; ?>&<?= http_build_query(array_diff_key($_GET, ['page' => ''])); ?>">‹ Précédent</a>
                    <?php endif; ?>
                    <?php for ($i = max(1, $page - 2); $i <= min($nbPages, $page + 2); $i++): ?>
                        <a href="?page=<?= $i; ?>&<?= http_build_query(array_diff_key($_GET, ['page' => ''])); ?>" class="<?= ($i == $page) ? 'active' : ''; ?>"><?= $i; ?></a>
                    <?php endfor; ?>
                    <?php if ($page < $nbPages): ?>
                        <a href="?page=<?= $page + 1; ?>&<?= http_build_query(array_diff_key($_GET, ['page' => ''])); ?>">Suivant ›</a>
                    <?php endif; ?>
                    <span class="page-indicator">Page <?= $page; ?> / <?= $nbPages; ?></span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="modal-overlay" id="modalRejet">
        <div class="modal">
            <h3><i class="fas fa-times-circle" style="color:var(--error);"></i> Rejeter le bordereau</h3>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="id_paiement" id="rejet_id">
                <div class="form-group">
                    <label>Étudiant</label>
                    <p id="rejet_etudiant" style="padding:8px 12px; background:#f8fafc; border-radius:8px; border:1px solid var(--border-color);"></p>
                </div>
                <div class="form-group">
                    <label>Bordereau</label>
                    <p id="rejet_bordereau" style="padding:8px 12px; background:#f8fafc; border-radius:8px; border:1px solid var(--border-color);"></p>
                </div>
                <div class="form-group">
                    <label>Motif du rejet <span style="color:red;">*</span></label>
                    <select name="motif_rejet" id="rejet_motif_select" onchange="document.getElementById('rejet_motif_autre').style.display = (this.value == 'Autre') ? 'block' : 'none';">
                        <option value="">-- Choisir un motif --</option>
                        <option value="Bordereau illisible">Bordereau illisible</option>
                        <option value="Montant incorrect">Montant incorrect</option>
                        <option value="Numéro de bordereau incorrect">Numéro de bordereau incorrect</option>
                        <option value="Bordereau déjà utilisé">Bordereau déjà utilisé</option>
                        <option value="Paiement introuvable">Paiement introuvable</option>
                        <option value="Informations incorrectes">Informations incorrectes</option>
                        <option value="Document invalide">Document invalide</option>
                        <option value="Autre">Autre</option>
                    </select>
                </div>
                <div class="form-group" id="rejet_motif_autre" style="display:none;">
                    <label>Précisez le motif</label>
                    <textarea name="motif_rejet_autre" rows="2" placeholder="Détaillez le motif..."></textarea>
                </div>
                <div class="btn-group">
                    <button type="button" class="btn btn-secondary" onclick="fermerModalRejet()" style="background:#e2e8f0; color:#475569; border:none; padding:8px 20px; border-radius:8px; cursor:pointer;">Annuler</button>
                    <button type="submit" name="rejeter" class="btn btn-danger" style="background:var(--error); color:white; border:none; padding:8px 20px; border-radius:8px; cursor:pointer;">Confirmer le rejet</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function ouvrirModalRejet(id, etudiant, bordereau) {
            document.getElementById('rejet_id').value = id;
            document.getElementById('rejet_etudiant').textContent = etudiant;
            document.getElementById('rejet_bordereau').textContent = bordereau;
            document.getElementById('rejet_motif_select').value = '';
            document.getElementById('rejet_motif_autre').style.display = 'none';
            document.getElementById('modalRejet').classList.add('active');
        }
        function fermerModalRejet() {
            document.getElementById('modalRejet').classList.remove('active');
        }
        document.getElementById('modalRejet').addEventListener('click', function(e) {
            if (e.target === this) fermerModalRejet();
        });
    </script>

</body>
</html>