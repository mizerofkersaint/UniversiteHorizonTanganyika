<?php
require_once __DIR__ . '/db.php';
// ============================================================
// DASHBOARD RECTEUR - UniVerse
// Version corrigée et redesignée — toutes fonctionnalités préservées
// ============================================================
require_once 'auth.php';
requireRole('Recteur');

try {
    $bdd = getDBConnection();
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

/* ============================================================
   CSRF TOKEN
============================================================ */
if (empty($_SESSION['csrf_recteur'])) {
    $_SESSION['csrf_recteur'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_recteur'];

/* ============================================================
   UTILISATEUR CONNECTÉ
============================================================ */
$id_user = $USER_ID;
$stmtUser = $bdd->prepare("SELECT nom, prenom, photo, role, email, telephone FROM users WHERE id_user = ?");
$stmtUser->execute([$id_user]);
$user = $stmtUser->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("Utilisateur introuvable.");
}

$message = "";
$message_type = "info";
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

/* ============================================================
   HELPER CSRF
============================================================ */
function verifierCsrfRecteur($token) {
    return isset($_SESSION['csrf_recteur']) && hash_equals($_SESSION['csrf_recteur'], (string)$token);
}

/* ============================================================
   TRAITEMENT DES ACTIONS
============================================================ */

// --- PUBLIER UNE NOTE DE SERVICE ---
if (isset($_POST['action_communique'])) {
    if (!isset($_POST['csrf_token']) || !verifierCsrfRecteur($_POST['csrf_token'])) {
        $message = "❌ Jeton de sécurité invalide. Rechargez la page.";
        $message_type = "error";
    } else {
        $contenu = trim($_POST['communique'] ?? '');
        if (!empty($contenu)) {
            try {
                $stmt = $bdd->prepare("INSERT INTO notifications (titre, message, type_evenement, statut, source_table, date_creation) VALUES (?, ?, 'INFO', 'non_lu', 'recteur', NOW())");
                $stmt->execute(['📢 Communiqué officiel', $contenu]);
                $message = "📢 Note officielle publiée avec succès.";
                $message_type = "success";
            } catch (Exception $e) {
                $message = "❌ Erreur : " . htmlspecialchars($e->getMessage());
                $message_type = "error";
            }
        } else {
            $message = "❌ Veuillez saisir un message.";
            $message_type = "error";
        }
    }
}

// --- MARQUER UNE NOTIFICATION COMME LUE ---
if (isset($_GET['notif_lu']) && !empty($_GET['notif_lu'])) {
    if (!verifierCsrfRecteur($_GET['csrf'] ?? '')) {
        $message = "❌ Jeton de sécurité invalide.";
        $message_type = "error";
    } else {
        $id_notif = intval($_GET['notif_lu']);
        $updNotif = $bdd->prepare("UPDATE notifications SET statut = 'lu' WHERE id_notification = ?");
        $updNotif->execute([$id_notif]);
        header("Location: dashboard_recteur.php?page=notifications");
        exit();
    }
}

// --- SUPPRIMER UNE NOTIFICATION ---
if (isset($_GET['notif_supprimer']) && !empty($_GET['notif_supprimer'])) {
    if (!verifierCsrfRecteur($_GET['csrf'] ?? '')) {
        $message = "❌ Jeton de sécurité invalide.";
        $message_type = "error";
    } else {
        $id_notif = intval($_GET['notif_supprimer']);
        $delNotif = $bdd->prepare("DELETE FROM notifications WHERE id_notification = ?");
        $delNotif->execute([$id_notif]);
        header("Location: dashboard_recteur.php?page=notifications");
        exit();
    }
}

/* ============================================================
   FILTRES GLOBAUX
============================================================ */
$annee_actuelle = date('Y') . '-' . (date('Y') + 1);
$annee_filtre = isset($_GET['annee']) ? trim($_GET['annee']) : $annee_actuelle;
$faculte_filtre = isset($_GET['faculte']) ? trim($_GET['faculte']) : '';
$departement_filtre = isset($_GET['departement']) ? (int)$_GET['departement'] : 0;

/* ============================================================
   STATISTIQUES GLOBALES
============================================================ */
$totalEtudiants    = $bdd->query("SELECT COUNT(*) FROM etudiants")->fetchColumn() ?: 0;
$totalProfesseurs  = $bdd->query("SELECT COUNT(*) FROM professeurs")->fetchColumn() ?: 0;
$totalDepartements = $bdd->query("SELECT COUNT(*) FROM departements")->fetchColumn() ?: 0;
$totalFacultes     = $bdd->query("SELECT COUNT(DISTINCT faculte) FROM departements WHERE faculte IS NOT NULL")->fetchColumn() ?: 0;
$totalClasses      = $bdd->query("SELECT COUNT(*) FROM classes")->fetchColumn() ?: 0;
$totalCours        = $bdd->query("SELECT COUNT(*) FROM cours")->fetchColumn() ?: 0;

$totalRecettes = $bdd->query("SELECT SUM(montant) FROM paiements WHERE statut = 'valide'")->fetchColumn() ?: 0;
$recettesAnnuelles = $bdd->query("SELECT SUM(montant) FROM paiements WHERE statut = 'valide' AND YEAR(date_paiement) = YEAR(CURDATE())")->fetchColumn() ?: 0;

$statsReussite = $bdd->query("SELECT COUNT(CASE WHEN note >= 10 THEN 1 END) as reussite, COUNT(CASE WHEN note < 10 THEN 1 END) as echec FROM notes WHERE statut = 'valide'")->fetch(PDO::FETCH_ASSOC);
$tauxReussiteGlobal = ($statsReussite['reussite'] + $statsReussite['echec']) > 0
    ? round(($statsReussite['reussite'] / ($statsReussite['reussite'] + $statsReussite['echec'])) * 100, 1)
    : 0;

$nbNotifs = $bdd->query("SELECT COUNT(*) FROM notifications WHERE statut = 'non_lu'")->fetchColumn() ?: 0;
$examensAttente = $bdd->query("SELECT COUNT(*) FROM examens WHERE statut = 'en_attente'")->fetchColumn() ?: 0;
$bordereauxAttente = $bdd->query("SELECT COUNT(*) FROM paiements WHERE statut = 'en_attente'")->fetchColumn() ?: 0;

$deliberationsAttente = 0;
$hasDeliberations = $bdd->query("SHOW TABLES LIKE 'deliberations'")->fetch();
if ($hasDeliberations) {
    $delibAttente = $bdd->prepare("SELECT COUNT(*) FROM deliberations WHERE statut = 'en_attente'");
    $delibAttente->execute();
    $deliberationsAttente = $delibAttente->fetchColumn() ?: 0;
}

$resultatsAPublier = $bdd->query("SELECT COUNT(*) FROM notes WHERE statut = 'valide' AND date_publication IS NULL")->fetchColumn() ?: 0;

// ✅ CORRECTION : rowCount() sur SELECT avec GROUP BY (MySQL renvoie souvent 0)
// On utilise une sous-requête COUNT
$etudiantsNonEnOrdre = $bdd->query("
    SELECT COUNT(*) FROM (
        SELECT e.id_etudiant
        FROM etudiants e
        LEFT JOIN paiements p ON e.id_etudiant = p.id_etudiant AND p.statut = 'valide'
        GROUP BY e.id_etudiant
        HAVING COALESCE(SUM(p.montant), 0) < 500000
    ) AS non_ordre
")->fetchColumn() ?: 0;

/* ============================================================
   DONNÉES POUR LES GRAPHIQUES ET TABLEAUX
============================================================ */
$statsParFaculte = $bdd->query("
    SELECT d.faculte, COUNT(DISTINCT e.id_etudiant) as nb_etudiants,
           COUNT(DISTINCT p.id_prof) as nb_profs,
           COUNT(DISTINCT c.id_cours) as nb_cours,
           COALESCE(SUM(pai.montant), 0) as recettes
    FROM departements d
    LEFT JOIN classes cl ON d.id_dept = cl.id_dept
    LEFT JOIN etudiants e ON cl.id_classe = e.id_classe
    LEFT JOIN cours c ON cl.id_classe = c.id_classe
    LEFT JOIN professeurs p ON c.id_prof = p.id_prof
    LEFT JOIN paiements pai ON e.id_etudiant = pai.id_etudiant AND pai.statut = 'valide'
    WHERE d.faculte IS NOT NULL AND d.faculte != ''
    GROUP BY d.faculte
    ORDER BY nb_etudiants DESC
")->fetchAll(PDO::FETCH_ASSOC);

$reussiteParFaculte = $bdd->query("
    SELECT d.faculte,
           COUNT(CASE WHEN n.note >= 10 THEN 1 END) as reussite,
           COUNT(CASE WHEN n.note < 10 THEN 1 END) as echec
    FROM departements d
    JOIN classes cl ON d.id_dept = cl.id_dept
    JOIN cours c ON cl.id_classe = c.id_classe
    JOIN notes n ON c.id_cours = n.id_cours
    WHERE d.faculte IS NOT NULL AND d.faculte != '' AND n.statut = 'valide'
    GROUP BY d.faculte
")->fetchAll(PDO::FETCH_ASSOC);

$dernieresInscriptions = $bdd->query("
    SELECT u.nom, u.prenom, e.matricule, i.date_inscription, d.nom_dept, d.faculte
    FROM inscriptions i
    INNER JOIN etudiants e ON i.id_etudiant = e.id_etudiant
    INNER JOIN users u ON e.id_user = u.id_user
    LEFT JOIN classes cl ON e.id_classe = cl.id_classe
    LEFT JOIN departements d ON cl.id_dept = d.id_dept
    ORDER BY i.date_inscription DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

$derniersPaiements = $bdd->query("
    SELECT u.nom, u.prenom, p.montant, p.date_paiement, p.tranche, d.nom_dept, d.faculte, p.statut
    FROM paiements p
    INNER JOIN etudiants e ON p.id_etudiant = e.id_etudiant
    INNER JOIN users u ON e.id_user = u.id_user
    LEFT JOIN classes cl ON e.id_classe = cl.id_classe
    LEFT JOIN departements d ON cl.id_dept = d.id_dept
    ORDER BY p.date_paiement DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

$departementsList = $bdd->query("SELECT id_dept, nom_dept, faculte FROM departements ORDER BY faculte, nom_dept")->fetchAll(PDO::FETCH_ASSOC);

// Liste des années disponibles pour le filtre
$anneesListe = $bdd->query("SELECT DISTINCT annee_academique_entree FROM etudiants WHERE annee_academique_entree IS NOT NULL ORDER BY annee_academique_entree DESC")->fetchAll(PDO::FETCH_COLUMN);

// Liste des facultés
$facultesListe = $bdd->query("SELECT DISTINCT faculte FROM departements WHERE faculte IS NOT NULL AND faculte != '' ORDER BY faculte")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chancellerie — UniVerse</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="assets/theme.css">
    <script src="assets/theme.js"></script>
    <style>
        /* ================================================================
           VARIABLES
           ================================================================ */
        :root {
            --bg-primary: #f1f5f9;
            --bg-sidebar: #0f172a;
            --bg-card: #ffffff;
            --text-primary: #1e293b;
            --text-secondary: #475569;
            --text-muted: #64748b;
            --text-light: #94a3b8;
            --text-white: #ffffff;
            --accent-blue: #2563eb;
            --accent-blue-dark: #1d4ed8;
            --accent-success: #10b981;
            --accent-success-dark: #059669;
            --accent-warning: #f59e0b;
            --accent-warning-dark: #d97706;
            --accent-danger: #ef4444;
            --accent-danger-dark: #dc2626;
            --accent-purple: #8b5cf6;
            --accent-pink: #ec4899;
            --gold: #d4af37;
            --gold-dark: #aa7c11;
            --border-color: #e2e8f0;
            --border-light: #f1f5f9;
            --shadow-xs: 0 1px 2px rgba(0,0,0,0.04);
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
            --radius-sm: 10px;
            --radius: 14px;
            --radius-lg: 18px;
            --transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; }
        body {
            background: var(--bg-primary);
            color: var(--text-primary);
            display: flex;
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
        }

        /* ================================================================
           SIDEBAR
           ================================================================ */
        .sidebar {
            width: 270px;
            background: linear-gradient(180deg, #0f172a 0%, #1e293b 100%);
            color: var(--text-white);
            padding: 24px 16px;
            position: fixed;
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            z-index: 100;
            border-right: 2px solid var(--gold);
        }
        .sidebar::-webkit-scrollbar { width: 4px; }
        .sidebar::-webkit-scrollbar-thumb { background: #334155; border-radius: 2px; }

        .sidebar .logo {
            font-size: 22px;
            font-weight: 800;
            margin-bottom: 22px;
            padding: 0 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .sidebar .logo span {
            background: linear-gradient(135deg, #fbbf24, #d4af37);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .user-profile {
            background: rgba(212,175,55,0.06);
            padding: 16px 14px;
            border-radius: var(--radius-sm);
            margin-bottom: 22px;
            text-align: center;
            border: 1px solid rgba(212,175,55,0.15);
        }
        .profile-img {
            width: 66px; height: 66px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 10px;
            border: 3px solid var(--gold);
            box-shadow: 0 4px 14px rgba(212,175,55,0.35);
        }
        .user-profile .name { font-weight: 600; font-size: 14px; color: var(--text-white); }
        .user-profile .title { font-size: 11px; color: var(--text-light); margin-top: 2px; }
        .badge-role {
            display: inline-block;
            padding: 4px 12px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            border-radius: 20px;
            margin-top: 8px;
            background: linear-gradient(135deg, var(--gold), var(--gold-dark));
            color: #0f172a;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 12px rgba(212,175,55,0.3);
        }

        .nav-links { list-style: none; display: flex; flex-direction: column; gap: 2px; flex: 1; }
        .nav-links a {
            color: var(--text-light);
            text-decoration: none;
            padding: 10px 14px;
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
            font-size: 13px;
            transition: var(--transition);
        }
        .nav-links a:hover { background: rgba(255,255,255,0.06); color: var(--text-white); }
        .nav-links a.active {
            background: linear-gradient(135deg, rgba(212,175,55,0.2), rgba(212,175,55,0.08));
            color: var(--text-white);
            box-shadow: inset 3px 0 0 var(--gold);
        }
        .nav-links a i { width: 20px; text-align: center; font-size: 13px; }
        .nav-badge {
            margin-left: auto;
            background: var(--accent-danger);
            color: white;
            font-size: 10px;
            padding: 2px 8px;
            border-radius: 10px;
            font-weight: 700;
            box-shadow: 0 2px 6px rgba(239,68,68,0.4);
        }
        .nav-links .logout {
            color: var(--accent-danger);
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid rgba(255,255,255,0.06);
        }
        .nav-links .logout:hover { background: rgba(239,68,68,0.12); }

        /* ================================================================
           MAIN CONTENT
           ================================================================ */
        .main-content {
            margin-left: 270px;
            width: calc(100% - 270px);
            padding: 28px 32px;
            min-height: 100vh;
        }

        header.page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 26px;
            padding-bottom: 18px;
            border-bottom: 2px solid var(--gold);
            flex-wrap: wrap;
            gap: 12px;
        }
        header.page-header h1 {
            font-size: 26px;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        header.page-header h1 i { color: var(--gold); font-size: 22px; }
        .header-actions { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }

        /* ================================================================
           BOUTONS
           ================================================================ */
        .btn {
            padding: 9px 18px;
            border-radius: var(--radius-sm);
            border: none;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            white-space: nowrap;
        }
        .btn-primary { background: var(--accent-blue); color: white; }
        .btn-primary:hover { background: var(--accent-blue-dark); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(37,99,235,0.3); }
        .btn-success { background: var(--accent-success); color: white; }
        .btn-success:hover { background: var(--accent-success-dark); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(16,185,129,0.3); }
        .btn-warning { background: var(--accent-warning); color: white; }
        .btn-warning:hover { background: var(--accent-warning-dark); transform: translateY(-2px); }
        .btn-danger { background: var(--accent-danger); color: white; }
        .btn-danger:hover { background: var(--accent-danger-dark); transform: translateY(-2px); }
        .btn-outline { background: transparent; color: var(--text-secondary); border: 1px solid var(--border-color); }
        .btn-outline:hover { background: var(--bg-primary); border-color: var(--gold); color: var(--gold-dark); }

        .theme-toggle-btn {
            background: white;
            border: 1px solid var(--border-color);
            width: 40px; height: 40px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 16px;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .theme-toggle-btn:hover { background: var(--bg-primary); transform: rotate(15deg); }

        /* ================================================================
           ALERTES
           ================================================================ */
        .alert {
            padding: 14px 20px;
            border-radius: var(--radius-sm);
            margin-bottom: 20px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            animation: slideDown 0.35s ease;
        }
        .alert.success { background: #d1fae5; color: #065f46; border-left: 4px solid var(--accent-success); }
        .alert.error { background: #fee2e2; color: #991b1b; border-left: 4px solid var(--accent-danger); }
        .alert.warning { background: #fef3c7; color: #92400e; border-left: 4px solid var(--accent-warning); }
        .alert.info { background: #eff6ff; color: #1e40af; border-left: 4px solid var(--accent-blue); }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

        /* ================================================================
           STAT CARDS
           ================================================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: 16px;
            margin-bottom: 26px;
        }
        .stat-card {
            background: var(--bg-card);
            padding: 18px 20px;
            border-radius: var(--radius);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            opacity: 0.85;
        }
        .stat-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); border-color: transparent; }
        .stat-card.alert-card { cursor: pointer; border: 1.5px solid var(--accent-warning); }
        .stat-card.alert-card:hover { border-color: var(--accent-warning); box-shadow: 0 6px 18px rgba(245,158,11,0.15); }

        .stat-card .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }
        .stat-card .stat-label {
            font-size: 11px;
            color: var(--text-muted);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .stat-card .stat-icon {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            color: white;
            box-shadow: 0 4px 10px rgba(0,0,0,0.12);
        }
        .stat-card .stat-value {
            font-size: 26px;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.15;
        }
        .stat-card .stat-change {
            font-size: 11px;
            font-weight: 600;
            margin-top: 6px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 10px;
            border-radius: 12px;
        }
        .stat-card .stat-change.up      { color: var(--accent-success); background: #d1fae5; }
        .stat-card .stat-change.down    { color: var(--accent-danger); background: #fee2e2; }
        .stat-card .stat-change.neutral { color: var(--text-muted); background: #f1f5f9; }
        .stat-card .cta {
            margin-top: 8px;
            font-size: 12px;
            color: var(--accent-blue);
            font-weight: 600;
        }

        .stat-card.gold::before   { background: var(--gold); }
        .stat-card.gold   .stat-icon { background: linear-gradient(135deg, #fbbf24, #d4af37); }
        .stat-card.blue::before   { background: var(--accent-blue); }
        .stat-card.blue   .stat-icon { background: linear-gradient(135deg, #3b82f6, #2563eb); }
        .stat-card.green::before  { background: var(--accent-success); }
        .stat-card.green  .stat-icon { background: linear-gradient(135deg, #34d399, #10b981); }
        .stat-card.orange::before { background: var(--accent-warning); }
        .stat-card.orange .stat-icon { background: linear-gradient(135deg, #fbbf24, #f59e0b); }
        .stat-card.red::before    { background: var(--accent-danger); }
        .stat-card.red    .stat-icon { background: linear-gradient(135deg, #f87171, #ef4444); }
        .stat-card.purple::before { background: var(--accent-purple); }
        .stat-card.purple .stat-icon { background: linear-gradient(135deg, #a78bfa, #8b5cf6); }
        .stat-card.pink::before   { background: var(--accent-pink); }
        .stat-card.pink   .stat-icon { background: linear-gradient(135deg, #f472b6, #ec4899); }

        /* ================================================================
           PANELS
           ================================================================ */
        .panel {
            background: var(--bg-card);
            padding: 22px 26px;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            margin-bottom: 22px;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
        }
        .panel:hover { box-shadow: var(--shadow-md); }
        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
            flex-wrap: wrap;
            gap: 10px;
            padding-bottom: 14px;
            border-bottom: 1px solid var(--border-light);
        }
        .panel-header h2 {
            font-size: 17px;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .panel-header .panel-actions { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }

        /* ================================================================
           GRIDS
           ================================================================ */
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 22px; margin-bottom: 22px; }
        .grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 22px; margin-bottom: 22px; }

        /* ================================================================
           TABLES
           ================================================================ */
        .table-responsive { overflow-x: auto; border-radius: var(--radius-sm); }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th {
            background: #f8fafc;
            padding: 12px 14px;
            color: var(--text-muted);
            font-weight: 600;
            border-bottom: 2px solid var(--border-color);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            text-align: left;
            white-space: nowrap;
        }
        td {
            padding: 12px 14px;
            border-bottom: 1px solid var(--border-light);
            vertical-align: middle;
            font-size: 13px;
        }
        tr { transition: var(--transition); }
        tr:hover td { background: #fafcff; }
        tr:last-child td { border-bottom: none; }

        /* ================================================================
           BADGES
           ================================================================ */
        .faculte-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            background: #eff6ff;
            color: #1e40af;
            font-weight: 600;
            font-size: 11px;
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        .status-badge.valide     { background: #d1fae5; color: #065f46; }
        .status-badge.en_attente { background: #fef3c7; color: #92400e; }
        .status-badge.rejete     { background: #fee2e2; color: #991b1b; }
        .status-badge.publie     { background: #d1fae5; color: #065f46; border: 1px solid var(--accent-success); }

        /* ================================================================
           BOUTONS ACTION
           ================================================================ */
        .btn-action {
            padding: 5px 11px;
            border-radius: 6px;
            border: none;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 4px;
            text-decoration: none;
        }
        .btn-action.success { background: #d1fae5; color: #065f46; }
        .btn-action.success:hover { background: var(--accent-success); color: white; }
        .btn-action.danger { background: #fee2e2; color: #991b1b; }
        .btn-action.danger:hover { background: var(--accent-danger); color: white; }
        .btn-action.primary { background: #eff6ff; color: #1e40af; }
        .btn-action.primary:hover { background: var(--accent-blue); color: white; }
        .btn-action.warning { background: #fef3c7; color: #92400e; }
        .btn-action.warning:hover { background: var(--accent-warning); color: white; }

        /* ================================================================
           FILTER BAR
           ================================================================ */
        .filter-bar {
            display: flex;
            gap: 12px;
            align-items: flex-end;
            flex-wrap: wrap;
            background: #f8fafc;
            padding: 16px 20px;
            border-radius: var(--radius);
            border: 1px solid var(--border-color);
        }
        .filter-bar .form-group { margin-bottom: 0; flex: 1; min-width: 140px; }
        .filter-bar .form-group label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--text-muted);
            display: block;
            margin-bottom: 5px;
        }
        .filter-bar input, .filter-bar select {
            padding: 9px 12px;
            font-size: 13px;
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            width: 100%;
            outline: none;
            transition: var(--transition);
        }
        .filter-bar input:focus, .filter-bar select:focus {
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 3px rgba(37,99,235,0.08);
        }
        .filter-actions { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }

        /* ================================================================
           FORMULAIRES
           ================================================================ */
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-bottom: 14px;
        }
        .form-group label {
            font-size: 12px;
            font-weight: 600;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .form-group label .required { color: var(--accent-danger); margin-left: 2px; }
        textarea, input, select {
            padding: 10px 14px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            background: #f8fafc;
            font-size: 14px;
            width: 100%;
            transition: var(--transition);
            outline: none;
            font-family: inherit;
        }
        textarea:focus, input:focus, select:focus {
            border-color: var(--accent-blue);
            background: white;
            box-shadow: 0 0 0 3px rgba(37,99,235,0.08);
        }

        /* ================================================================
           CHARTS
           ================================================================ */
        .chart-container { position: relative; height: 240px; }

        /* ================================================================
           BUDGET PROGRESS
           ================================================================ */
        .budget-progress {
            background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
            padding: 20px 24px;
            border-radius: var(--radius);
            border: 1px solid #fde68a;
            margin-bottom: 24px;
            box-shadow: var(--shadow-sm);
        }
        .budget-progress .budget-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-weight: 600;
            font-size: 14px;
            flex-wrap: wrap;
            gap: 10px;
            color: #78350f;
        }
        .budget-progress .budget-header .amount {
            color: var(--accent-success-dark);
            font-weight: 700;
        }
        .budget-progress .progress-bar-bg {
            background: rgba(255,255,255,0.7);
            height: 12px;
            border-radius: 6px;
            overflow: hidden;
            box-shadow: inset 0 1px 2px rgba(0,0,0,0.06);
        }
        .budget-progress .progress-bar-fill {
            background: linear-gradient(90deg, var(--accent-success), var(--accent-blue));
            height: 100%;
            border-radius: 6px;
            transition: width 1.5s ease;
            box-shadow: 0 2px 8px rgba(16,185,129,0.3);
        }

        /* ================================================================
           LIST ITEMS
           ================================================================ */
        .list-item {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            padding: 14px 0;
            border-bottom: 1px solid var(--border-light);
        }
        .list-item:last-child { border-bottom: none; }
        .list-item .list-main { flex: 1; min-width: 0; }
        .list-item .list-title { font-weight: 600; color: var(--text-primary); font-size: 14px; }
        .list-item .list-text { font-size: 13px; color: var(--text-secondary); margin-top: 4px; line-height: 1.5; }
        .list-item .list-meta { font-size: 11px; color: var(--text-light); margin-top: 6px; }

        /* ================================================================
           EMPTY STATE
           ================================================================ */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-muted);
            font-size: 14px;
        }
        .empty-state i {
            font-size: 42px;
            margin-bottom: 12px;
            display: block;
            opacity: 0.3;
        }
        .empty-state p { margin-top: 6px; font-size: 13px; }

        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1200px) {
            .grid-2 { grid-template-columns: 1fr; }
            .grid-3 { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 992px) { .grid-3 { grid-template-columns: 1fr; } }
        @media (max-width: 768px) {
            body { flex-direction: column; }
            .sidebar { width: 100%; height: auto; position: relative; padding: 18px; }
            .main-content { margin-left: 0; width: 100%; padding: 16px; }
            header.page-header { flex-direction: column; align-items: flex-start; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .filter-bar { flex-direction: column; align-items: stretch; }
            .filter-bar .form-group { min-width: unset; }
        }
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
            .panel { padding: 18px; }
        }
    </style>
</head>
<body>

    <!-- ================================================================
         SIDEBAR
    ================================================================ -->
    <aside class="sidebar">
        <div class="logo">👑 <span>UniVerse</span></div>

        <div class="user-profile">
            <?php
            $photo = (!empty($user['photo']) && file_exists("uploads/" . $user['photo']))
                ? $user['photo'] : 'default.png';
            ?>
            <img src="uploads/<?= htmlspecialchars($photo); ?>" alt="Photo" class="profile-img">
            <p class="name">Prof. <?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']); ?></p>
            <p class="title">Chancellerie de l'Université</p>
            <span class="badge-role">👑 RECTEUR</span>
        </div>

        <ul class="nav-links">
            <li><a href="?page=dashboard" class="<?= ($page=='dashboard')?'active':''; ?>"><i class="fas fa-chart-line"></i> Tableau de bord</a></li>
            <li><a href="?page=profil" class="<?= ($page=='profil')?'active':''; ?>"><i class="fas fa-user"></i> Mon profil</a></li>
            <li><a href="?page=academique" class="<?= ($page=='academique')?'active':''; ?>"><i class="fas fa-university"></i> Gestion académique</a></li>
            <li><a href="?page=etudiants" class="<?= ($page=='etudiants')?'active':''; ?>"><i class="fas fa-user-graduate"></i> Étudiants</a></li>
            <li><a href="?page=professeurs" class="<?= ($page=='professeurs')?'active':''; ?>"><i class="fas fa-chalkboard-teacher"></i> Professeurs</a></li>
            <li><a href="?page=examens" class="<?= ($page=='examens')?'active':''; ?>"><i class="fas fa-file-alt"></i> Examens <span class="nav-badge"><?= $examensAttente; ?></span></a></li>
            <li><a href="?page=deliberations" class="<?= ($page=='deliberations')?'active':''; ?>"><i class="fas fa-gavel"></i> Délibérations <span class="nav-badge"><?= $deliberationsAttente; ?></span></a></li>
            <li><a href="?page=finance" class="<?= ($page=='finance')?'active':''; ?>"><i class="fas fa-coins"></i> Finance</a></li>
            <li><a href="?page=statistiques" class="<?= ($page=='statistiques')?'active':''; ?>"><i class="fas fa-chart-pie"></i> Statistiques</a></li>
            <li><a href="?page=communications" class="<?= ($page=='communications')?'active':''; ?>"><i class="fas fa-bullhorn"></i> Communications</a></li>
            <li><a href="?page=notifications" class="<?= ($page=='notifications')?'active':''; ?>"><i class="fas fa-bell"></i> Notifications <span class="nav-badge"><?= $nbNotifs; ?></span></a></li>
            <li><a href="?page=audit" class="<?= ($page=='audit')?'active':''; ?>"><i class="fas fa-history"></i> Audit</a></li>
            <li><a href="?page=rapports" class="<?= ($page=='rapports')?'active':''; ?>"><i class="fas fa-file-alt"></i> Rapports</a></li>
            <li><a href="rapports_personnalises.php"><i class="fas fa-chart-bar"></i> Rapports personnalisés</a></li>
            <li><a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
        </ul>
    </aside>

    <!-- ================================================================
         MAIN CONTENT
    ================================================================ -->
    <main class="main-content">

        <header class="page-header">
            <h1><i class="fas fa-university"></i> Chancellerie — Supervision Générale</h1>
            <div class="header-actions">
                <button id="themeToggleBtn" class="theme-toggle-btn" onclick="toggleTheme()" title="Mode sombre">🌙</button>
                <span class="btn btn-outline" style="cursor:default;">
                    📅 <?= htmlspecialchars($annee_filtre); ?>
                </span>
                <a href="export_excel_universel.php?table=paiements&filename=Rapport_Recteur" class="btn btn-success">
                    <i class="fas fa-file-excel"></i> Rapport Excel
                </a>
            </div>
        </header>

        <?php if (!empty($message)): ?>
            <div class="alert <?= htmlspecialchars($message_type); ?>"><?= $message; ?></div>
        <?php endif; ?>

        <!-- ============================================================
             FILTRES GLOBAUX
        ============================================================ -->
        <div class="panel">
            <form method="GET" class="filter-bar" style="margin-bottom:0;">
                <input type="hidden" name="page" value="<?= htmlspecialchars($page); ?>">
                <div class="form-group">
                    <label>Année académique</label>
                    <select name="annee" onchange="this.form.submit()">
                        <option value="<?= htmlspecialchars($annee_actuelle); ?>" <?= ($annee_filtre == $annee_actuelle) ? 'selected' : ''; ?>><?= htmlspecialchars($annee_actuelle); ?></option>
                        <?php foreach ($anneesListe as $a):
                            if ($a !== $annee_actuelle): ?>
                                <option value="<?= htmlspecialchars($a); ?>" <?= ($annee_filtre == $a) ? 'selected' : ''; ?>><?= htmlspecialchars($a); ?></option>
                            <?php endif;
                        endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Faculté</label>
                    <select name="faculte" onchange="this.form.submit()">
                        <option value="">Toutes</option>
                        <?php foreach ($facultesListe as $f): ?>
                            <option value="<?= htmlspecialchars($f); ?>" <?= ($faculte_filtre == $f) ? 'selected' : ''; ?>><?= htmlspecialchars($f); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Département</label>
                    <select name="departement" onchange="this.form.submit()">
                        <option value="">Tous</option>
                        <?php foreach ($departementsList as $d): ?>
                            <option value="<?= (int)$d['id_dept']; ?>" <?= ($departement_filtre == $d['id_dept']) ? 'selected' : ''; ?>><?= htmlspecialchars($d['nom_dept']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filtrer</button>
                    <a href="?page=<?= htmlspecialchars($page); ?>" class="btn btn-outline"><i class="fas fa-times"></i> Réinitialiser</a>
                </div>
            </form>
        </div>

        <!-- ============================================================
             PAGE : DASHBOARD (ACCUEIL)
        ============================================================ -->
        <?php if ($page == 'dashboard'): ?>

        <!-- Barre de progression budget -->
        <?php
        $objectifBudget = 150000000;
        $tauxRecouvrement = $objectifBudget > 0 ? min(100, round(($totalRecettes / $objectifBudget) * 100, 1)) : 0;
        ?>
        <div class="budget-progress">
            <div class="budget-header">
                <span>📈 Taux de Recouvrement des Minervals</span>
                <span class="amount">
                    <?= number_format($totalRecettes, 0, ',', ' '); ?> / <?= number_format($objectifBudget, 0, ',', ' '); ?> BIF
                    (<?= $tauxRecouvrement; ?>%)
                </span>
            </div>
            <div class="progress-bar-bg">
                <div class="progress-bar-fill" style="width: <?= $tauxRecouvrement; ?>%;"></div>
            </div>
        </div>

        <!-- Statistiques -->
        <div class="stats-grid">
            <div class="stat-card gold">
                <div class="stat-header">
                    <span class="stat-label">Étudiants</span>
                    <span class="stat-icon"><i class="fas fa-user-graduate"></i></span>
                </div>
                <div class="stat-value"><?= number_format($totalEtudiants); ?></div>
                <div class="stat-change up"><i class="fas fa-arrow-up"></i> Inscrits</div>
            </div>

            <div class="stat-card blue">
                <div class="stat-header">
                    <span class="stat-label">Professeurs</span>
                    <span class="stat-icon"><i class="fas fa-chalkboard-teacher"></i></span>
                </div>
                <div class="stat-value"><?= number_format($totalProfesseurs); ?></div>
                <div class="stat-change neutral">Actifs</div>
            </div>

            <div class="stat-card purple">
                <div class="stat-header">
                    <span class="stat-label">Facultés</span>
                    <span class="stat-icon"><i class="fas fa-building-columns"></i></span>
                </div>
                <div class="stat-value"><?= $totalFacultes; ?></div>
                <div class="stat-change neutral">En activité</div>
            </div>

            <div class="stat-card pink">
                <div class="stat-header">
                    <span class="stat-label">Départements</span>
                    <span class="stat-icon"><i class="fas fa-sitemap"></i></span>
                </div>
                <div class="stat-value"><?= $totalDepartements; ?></div>
                <div class="stat-change neutral">Actifs</div>
            </div>

            <div class="stat-card green">
                <div class="stat-header">
                    <span class="stat-label">Cours</span>
                    <span class="stat-icon"><i class="fas fa-book"></i></span>
                </div>
                <div class="stat-value"><?= $totalCours; ?></div>
                <div class="stat-change up"><i class="fas fa-arrow-up"></i> Programmes</div>
            </div>

            <div class="stat-card orange">
                <div class="stat-header">
                    <span class="stat-label">Classes</span>
                    <span class="stat-icon"><i class="fas fa-school"></i></span>
                </div>
                <div class="stat-value"><?= $totalClasses; ?></div>
                <div class="stat-change neutral">En cours</div>
            </div>

            <div class="stat-card orange alert-card" onclick="location.href='?page=examens'">
                <div class="stat-header">
                    <span class="stat-label">Examens en attente</span>
                    <span class="stat-icon"><i class="fas fa-file-alt"></i></span>
                </div>
                <div class="stat-value"><?= $examensAttente; ?></div>
                <div class="stat-change neutral">À valider</div>
                <div class="cta">📄 Voir les examens →</div>
            </div>

            <div class="stat-card orange alert-card" onclick="location.href='?page=deliberations'">
                <div class="stat-header">
                    <span class="stat-label">Délibérations</span>
                    <span class="stat-icon"><i class="fas fa-gavel"></i></span>
                </div>
                <div class="stat-value"><?= $deliberationsAttente; ?></div>
                <div class="stat-change neutral">En attente</div>
                <div class="cta">📋 Voir les délibérations →</div>
            </div>

            <div class="stat-card green">
                <div class="stat-header">
                    <span class="stat-label">Taux de réussite</span>
                    <span class="stat-icon"><i class="fas fa-check-circle"></i></span>
                </div>
                <div class="stat-value"><?= $tauxReussiteGlobal; ?>%</div>
                <div class="stat-change <?= $tauxReussiteGlobal >= 70 ? 'up' : 'down'; ?>">Global</div>
            </div>

            <div class="stat-card red">
                <div class="stat-header">
                    <span class="stat-label">Résultats à publier</span>
                    <span class="stat-icon"><i class="fas fa-flag"></i></span>
                </div>
                <div class="stat-value"><?= $resultatsAPublier; ?></div>
                <div class="stat-change neutral">En attente</div>
            </div>
        </div>

        <!-- Graphiques -->
        <div class="grid-2">
            <div class="panel">
                <div class="panel-header">
                    <h2><i class="fas fa-chart-bar" style="color:var(--accent-blue);"></i> Étudiants par faculté</h2>
                </div>
                <div class="chart-container"><canvas id="chartFacultes"></canvas></div>
            </div>
            <div class="panel">
                <div class="panel-header">
                    <h2><i class="fas fa-chart-pie" style="color:var(--accent-success);"></i> Taux de réussite par faculté</h2>
                </div>
                <div class="chart-container"><canvas id="chartReussiteFacultes"></canvas></div>
            </div>
        </div>

        <!-- Palmarès des facultés -->
        <div class="panel">
            <div class="panel-header">
                <h2><i class="fas fa-trophy" style="color:var(--gold);"></i> Palmarès des facultés</h2>
            </div>
            <?php if (!empty($statsParFaculte)): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Faculté</th>
                                <th>Étudiants</th>
                                <th>Professeurs</th>
                                <th>Cours</th>
                                <th>Recettes</th>
                                <th>Taux réussite</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($statsParFaculte as $sf):
                                $taux = 0;
                                foreach ($reussiteParFaculte as $rf) {
                                    if ($rf['faculte'] == $sf['faculte']) {
                                        $total_reu = $rf['reussite'] + $rf['echec'];
                                        $taux = $total_reu > 0 ? round(($rf['reussite'] / $total_reu) * 100, 1) : 0;
                                        break;
                                    }
                                }
                            ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($sf['faculte']); ?></strong></td>
                                    <td><?= number_format($sf['nb_etudiants']); ?></td>
                                    <td><?= number_format($sf['nb_profs']); ?></td>
                                    <td><?= number_format($sf['nb_cours']); ?></td>
                                    <td><?= number_format($sf['recettes'], 0, ',', ' '); ?> BIF</td>
                                    <td><span class="status-badge <?= $taux >= 70 ? 'valide' : ($taux >= 50 ? 'en_attente' : 'rejete'); ?>"><?= $taux; ?>%</span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state"><i class="fas fa-trophy"></i><p>Aucune faculté renseignée.</p></div>
            <?php endif; ?>
        </div>

        <!-- Dernières inscriptions et paiements -->
        <div class="grid-2">
            <div class="panel">
                <div class="panel-header">
                    <h2><i class="fas fa-user-plus"></i> Dernières inscriptions</h2>
                </div>
                <?php if (!empty($dernieresInscriptions)): ?>
                    <div class="table-responsive">
                        <table>
                            <thead><tr><th>Étudiant</th><th>Département</th><th>Faculté</th><th>Date</th></tr></thead>
                            <tbody>
                                <?php foreach ($dernieresInscriptions as $ins): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($ins['nom'] . ' ' . $ins['prenom']); ?></strong>
                                            <br><small style="color:var(--text-muted);"><?= htmlspecialchars($ins['matricule']); ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($ins['nom_dept'] ?? 'N/A'); ?></td>
                                        <td><span class="faculte-badge"><?= htmlspecialchars($ins['faculte'] ?? 'Non assignée'); ?></span></td>
                                        <td><?= date('d/m/Y', strtotime($ins['date_inscription'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state"><i class="fas fa-inbox"></i><p>Aucune inscription enregistrée.</p></div>
                <?php endif; ?>
            </div>

            <div class="panel">
                <div class="panel-header">
                    <h2><i class="fas fa-credit-card"></i> Derniers paiements</h2>
                </div>
                <?php if (!empty($derniersPaiements)): ?>
                    <div class="table-responsive">
                        <table>
                            <thead><tr><th>Étudiant</th><th>Montant</th><th>Tranche</th><th>Date</th><th>Statut</th></tr></thead>
                            <tbody>
                                <?php foreach ($derniersPaiements as $p): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($p['nom'] . ' ' . $p['prenom']); ?></strong></td>
                                        <td><strong><?= number_format($p['montant'], 0, ',', ' '); ?> BIF</strong></td>
                                        <td><?= htmlspecialchars($p['tranche'] ?? '—'); ?></td>
                                        <td><?= date('d/m/Y', strtotime($p['date_paiement'])); ?></td>
                                        <td><span class="status-badge <?= $p['statut'] == 'valide' ? 'valide' : 'en_attente'; ?>"><?= $p['statut'] == 'valide' ? '✅ Validé' : '⏳ En attente'; ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state"><i class="fas fa-inbox"></i><p>Aucun paiement enregistré.</p></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Communications (formulaire rapide) -->
        <div class="panel" style="border-left: 4px solid var(--gold);">
            <div class="panel-header">
                <h2><i class="fas fa-bullhorn" style="color:var(--gold);"></i> Communiqué officiel</h2>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                <div class="form-group">
                    <label>Message <span class="required">*</span></label>
                    <textarea name="communique" rows="3" placeholder="Rédiger une note ou un communiqué officiel..." required></textarea>
                </div>
                <button type="submit" name="action_communique" class="btn btn-primary">
                    <i class="fas fa-paper-plane"></i> Diffuser le communiqué
                </button>
            </form>
        </div>
        <?php endif; ?>


        <!-- ============================================================
             PAGE : GESTION ACADÉMIQUE
        ============================================================ -->
        <?php if ($page == 'academique'): ?>
        <div class="panel">
            <div class="panel-header"><h2><i class="fas fa-university"></i> Structure académique</h2></div>
            <div class="table-responsive">
                <table>
                    <thead><tr><th>Faculté</th><th>Département</th><th>Classes</th><th>Cours</th><th>Étudiants</th><th>Professeurs</th></tr></thead>
                    <tbody>
                        <?php
                        $structure = $bdd->query("
                            SELECT d.faculte, d.nom_dept, d.id_dept,
                                   COUNT(DISTINCT cl.id_classe) as nb_classes,
                                   COUNT(DISTINCT c.id_cours) as nb_cours,
                                   COUNT(DISTINCT e.id_etudiant) as nb_etudiants,
                                   COUNT(DISTINCT p.id_prof) as nb_profs
                            FROM departements d
                            LEFT JOIN classes cl ON d.id_dept = cl.id_dept
                            LEFT JOIN cours c ON cl.id_classe = c.id_classe
                            LEFT JOIN etudiants e ON cl.id_classe = e.id_classe
                            LEFT JOIN professeurs p ON c.id_prof = p.id_prof
                            GROUP BY d.id_dept
                            ORDER BY d.faculte, d.nom_dept
                        ");
                        $nb_struct = 0;
                        while ($s = $structure->fetch()):
                            $nb_struct++;
                        ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($s['faculte'] ?? 'Non assignée'); ?></strong></td>
                                <td><?= htmlspecialchars($s['nom_dept']); ?></td>
                                <td><?= $s['nb_classes']; ?></td>
                                <td><?= $s['nb_cours']; ?></td>
                                <td><?= $s['nb_etudiants']; ?></td>
                                <td><?= $s['nb_profs']; ?></td>
                            </tr>
                        <?php endwhile; ?>
                        <?php if ($nb_struct === 0): ?>
                            <tr><td colspan="6"><div class="empty-state"><i class="fas fa-inbox"></i><p>Aucune structure académique.</p></div></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>


        <!-- ============================================================
             PAGE : ÉTUDIANTS (vue globale) — ✅ SQL injectable corrigé
        ============================================================ -->
        <?php if ($page == 'etudiants'): ?>
        <div class="panel">
            <div class="panel-header">
                <h2><i class="fas fa-user-graduate"></i> Étudiants de l'université</h2>
                <span style="font-size:13px; color:var(--text-muted);"><?= number_format($totalEtudiants); ?> étudiants</span>
            </div>
            <div class="table-responsive">
                <table>
                    <thead><tr><th>Matricule</th><th>Nom</th><th>Prénom</th><th>Classe</th><th>Département</th><th>Faculté</th><th>Année</th><th>Statut</th></tr></thead>
                    <tbody>
                        <?php
                        /* ✅ CORRECTION : requête préparée au lieu d'injection directe */
                        $sqlEtudiants = "SELECT e.matricule, u.nom, u.prenom, c.niveau, d.nom_dept, d.faculte, e.annee_academique_entree, e.titre
                                         FROM etudiants e
                                         JOIN users u ON e.id_user = u.id_user
                                         JOIN classes c ON e.id_classe = c.id_classe
                                         JOIN departements d ON c.id_dept = d.id_dept
                                         WHERE 1=1";
                        $params_etu = [];
                        if (!empty($faculte_filtre)) {
                            $sqlEtudiants .= " AND d.faculte = ?";
                            $params_etu[] = $faculte_filtre;
                        }
                        if ($departement_filtre > 0) {
                            $sqlEtudiants .= " AND d.id_dept = ?";
                            $params_etu[] = $departement_filtre;
                        }
                        $sqlEtudiants .= " ORDER BY u.nom ASC LIMIT 50";

                        $stmtEtudiants = $bdd->prepare($sqlEtudiants);
                        $stmtEtudiants->execute($params_etu);
                        $etudiants_list = $stmtEtudiants->fetchAll(PDO::FETCH_ASSOC);

                        if (!empty($etudiants_list)):
                            foreach ($etudiants_list as $e): ?>
                                <tr>
                                    <td><code style="font-size:12px;"><?= htmlspecialchars($e['matricule']); ?></code></td>
                                    <td><strong><?= htmlspecialchars($e['nom']); ?></strong></td>
                                    <td><?= htmlspecialchars($e['prenom']); ?></td>
                                    <td><?= htmlspecialchars($e['niveau']); ?></td>
                                    <td><?= htmlspecialchars($e['nom_dept']); ?></td>
                                    <td><span class="faculte-badge"><?= htmlspecialchars($e['faculte'] ?? 'Non assignée'); ?></span></td>
                                    <td><?= htmlspecialchars($e['annee_academique_entree']); ?></td>
                                    <td><span class="status-badge <?= $e['titre'] == 'Nouveau' ? 'valide' : 'en_attente'; ?>"><?= htmlspecialchars($e['titre']); ?></span></td>
                                </tr>
                            <?php endforeach;
                        else: ?>
                            <tr><td colspan="8"><div class="empty-state"><i class="fas fa-user-graduate"></i><p>Aucun étudiant trouvé.</p></div></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>


        <!-- ============================================================
             PAGE : PROFESSEURS (vue globale)
        ============================================================ -->
        <?php if ($page == 'professeurs'): ?>
        <div class="panel">
            <div class="panel-header">
                <h2><i class="fas fa-chalkboard-teacher"></i> Professeurs de l'université</h2>
                <span style="font-size:13px; color:var(--text-muted);"><?= number_format($totalProfesseurs); ?> professeurs</span>
            </div>
            <div class="table-responsive">
                <table>
                    <thead><tr><th>Nom</th><th>Prénom</th><th>Spécialité</th><th>Diplôme</th><th>Département</th><th>Faculté</th><th>Statut</th></tr></thead>
                    <tbody>
                        <?php
                        $sqlProfs = "SELECT u.nom, u.prenom, p.specialite, p.diplome, d.nom_dept, d.faculte, u.statut
                                     FROM professeurs p
                                     JOIN users u ON p.id_user = u.id_user
                                     LEFT JOIN cours c ON p.id_prof = c.id_prof
                                     LEFT JOIN classes cl ON c.id_classe = cl.id_classe
                                     LEFT JOIN departements d ON cl.id_dept = d.id_dept
                                     GROUP BY p.id_prof
                                     ORDER BY u.nom ASC
                                     LIMIT 50";
                        $stmtProfs = $bdd->query($sqlProfs);
                        $nb_profs_list = 0;
                        while ($p = $stmtProfs->fetch()):
                            $nb_profs_list++;
                        ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($p['nom']); ?></strong></td>
                                <td><?= htmlspecialchars($p['prenom']); ?></td>
                                <td><?= htmlspecialchars($p['specialite'] ?? '—'); ?></td>
                                <td><?= htmlspecialchars($p['diplome'] ?? '—'); ?></td>
                                <td><?= htmlspecialchars($p['nom_dept'] ?? 'N/A'); ?></td>
                                <td><span class="faculte-badge"><?= htmlspecialchars($p['faculte'] ?? 'Non assignée'); ?></span></td>
                                <td><span class="status-badge <?= $p['statut'] == 'Actif' ? 'valide' : 'rejete'; ?>"><?= $p['statut']; ?></span></td>
                            </tr>
                        <?php endwhile; ?>
                        <?php if ($nb_profs_list === 0): ?>
                            <tr><td colspan="7"><div class="empty-state"><i class="fas fa-chalkboard-teacher"></i><p>Aucun professeur enregistré.</p></div></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>


        <!-- ============================================================
             PAGE : EXAMENS (vue globale)
        ============================================================ -->
        <?php if ($page == 'examens'): ?>
        <div class="panel">
            <div class="panel-header">
                <h2><i class="fas fa-file-alt"></i> Examens — Vue globale</h2>
                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                    <span class="status-badge en_attente">⏳ En attente : <?= $examensAttente; ?></span>
                    <span class="status-badge valide">✅ Validés : <?= $bdd->query("SELECT COUNT(*) FROM examens WHERE statut = 'valide'")->fetchColumn() ?: 0; ?></span>
                    <span class="status-badge rejete">❌ Rejetés : <?= $bdd->query("SELECT COUNT(*) FROM examens WHERE statut = 'rejete'")->fetchColumn() ?: 0; ?></span>
                </div>
            </div>
            <div class="table-responsive">
                <table>
                    <thead><tr><th>Professeur</th><th>Cours</th><th>Classe</th><th>Département</th><th>Type</th><th>Date</th><th>Statut</th></tr></thead>
                    <tbody>
                        <?php
                        $sqlExamens = "SELECT e.*, co.nom_cours, cl.niveau, d.nom_dept, d.faculte, u.nom, u.prenom
                                       FROM examens e
                                       JOIN cours co ON e.id_cours = co.id_cours
                                       JOIN classes cl ON e.id_classe = cl.id_classe
                                       JOIN departements d ON cl.id_dept = d.id_dept
                                       JOIN professeurs p ON e.id_prof = p.id_prof
                                       JOIN users u ON p.id_user = u.id_user
                                       ORDER BY e.date_envoi DESC
                                       LIMIT 30";
                        $stmtExamens = $bdd->query($sqlExamens);
                        $nb_ex = 0;
                        while ($ex = $stmtExamens->fetch()):
                            $nb_ex++;
                        ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($ex['nom'] . ' ' . $ex['prenom']); ?></strong></td>
                                <td><?= htmlspecialchars($ex['nom_cours']); ?></td>
                                <td><?= htmlspecialchars($ex['niveau']); ?></td>
                                <td><?= htmlspecialchars($ex['nom_dept']); ?></td>
                                <td><?= htmlspecialchars($ex['type_examen']); ?></td>
                                <td><?= date('d/m/Y', strtotime($ex['date_envoi'])); ?></td>
                                <td><span class="status-badge <?= $ex['statut']; ?>"><?= $ex['statut']; ?></span></td>
                            </tr>
                        <?php endwhile; ?>
                        <?php if ($nb_ex === 0): ?>
                            <tr><td colspan="7"><div class="empty-state"><i class="fas fa-file-alt"></i><p>Aucun examen enregistré.</p></div></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>


        <!-- ============================================================
             PAGE : DÉLIBÉRATIONS
        ============================================================ -->
        <?php if ($page == 'deliberations'): ?>
        <div class="panel">
            <div class="panel-header"><h2><i class="fas fa-gavel"></i> Délibérations</h2></div>
            <?php if ($hasDeliberations): ?>
                <div class="table-responsive">
                    <table>
                        <thead><tr><th>Faculté</th><th>Département</th><th>Classe</th><th>Année</th><th>Étudiants</th><th>Admis</th><th>Ajournés</th><th>Statut</th></tr></thead>
                        <tbody>
                            <?php
                            $sqlDelib = "SELECT d.*, dept.faculte, dept.nom_dept, cl.niveau,
                                        (SELECT COUNT(*) FROM etudiants WHERE id_classe = d.id_classe) as nb_etudiants
                                        FROM deliberations d
                                        JOIN departements dept ON d.id_dept = dept.id_dept
                                        JOIN classes cl ON d.id_classe = cl.id_classe
                                        ORDER BY d.date_deliberation DESC";
                            $stmtDelib = $bdd->query($sqlDelib);
                            $nb_delib = 0;
                            while ($del = $stmtDelib->fetch()):
                                $nb_delib++;
                            ?>
                                <tr>
                                    <td><?= htmlspecialchars($del['faculte']); ?></td>
                                    <td><?= htmlspecialchars($del['nom_dept']); ?></td>
                                    <td><?= htmlspecialchars($del['niveau']); ?></td>
                                    <td><?= htmlspecialchars($del['annee_academique']); ?></td>
                                    <td><?= $del['nb_etudiants']; ?></td>
                                    <td><?= $del['admis'] ?? 0; ?></td>
                                    <td><?= $del['ajournes'] ?? 0; ?></td>
                                    <td><span class="status-badge <?= $del['statut']; ?>"><?= $del['statut']; ?></span></td>
                                </tr>
                            <?php endwhile; ?>
                            <?php if ($nb_delib === 0): ?>
                                <tr><td colspan="8"><div class="empty-state"><i class="fas fa-gavel"></i><p>Aucune délibération enregistrée.</p></div></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-info-circle"></i>
                    <p>Le module des délibérations sera disponible prochainement.</p>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>


        <!-- ============================================================
             PAGE : FINANCE
        ============================================================ -->
        <?php if ($page == 'finance'): ?>
        <div class="panel">
            <div class="panel-header">
                <h2><i class="fas fa-coins"></i> Suivi financier global</h2>
            </div>
            <div class="grid-2">
                <div style="background:#f8fafc; padding:20px; border-radius:var(--radius); border:1px solid var(--border-color);">
                    <div style="font-size:12px; color:var(--text-muted); text-transform:uppercase; font-weight:600; letter-spacing:0.4px;">Recettes totales</div>
                    <div style="font-size:28px; font-weight:700; color:var(--accent-success); margin-top:6px;"><?= number_format($totalRecettes, 0, ',', ' '); ?> BIF</div>
                </div>
                <div style="background:#f8fafc; padding:20px; border-radius:var(--radius); border:1px solid var(--border-color);">
                    <div style="font-size:12px; color:var(--text-muted); text-transform:uppercase; font-weight:600; letter-spacing:0.4px;">Recettes annuelles</div>
                    <div style="font-size:28px; font-weight:700; color:var(--accent-blue); margin-top:6px;"><?= number_format($recettesAnnuelles, 0, ',', ' '); ?> BIF</div>
                </div>
                <div style="background:#f8fafc; padding:20px; border-radius:var(--radius); border:1px solid var(--border-color);">
                    <div style="font-size:12px; color:var(--text-muted); text-transform:uppercase; font-weight:600; letter-spacing:0.4px;">Bordereaux en attente</div>
                    <div style="font-size:28px; font-weight:700; color:var(--accent-warning); margin-top:6px;"><?= $bordereauxAttente; ?></div>
                </div>
                <div style="background:#f8fafc; padding:20px; border-radius:var(--radius); border:1px solid var(--border-color);">
                    <div style="font-size:12px; color:var(--text-muted); text-transform:uppercase; font-weight:600; letter-spacing:0.4px;">Étudiants non en ordre</div>
                    <div style="font-size:28px; font-weight:700; color:var(--accent-danger); margin-top:6px;"><?= $etudiantsNonEnOrdre; ?></div>
                </div>
            </div>
            <div class="table-responsive" style="margin-top:20px;">
                <table>
                    <thead><tr><th>Faculté</th><th>Recettes</th><th>% total</th></tr></thead>
                    <tbody>
                        <?php
                        $recettesFac = $bdd->query("
                            SELECT d.faculte, SUM(p.montant) as total
                            FROM paiements p
                            JOIN etudiants e ON p.id_etudiant = e.id_etudiant
                            JOIN classes cl ON e.id_classe = cl.id_classe
                            JOIN departements d ON cl.id_dept = d.id_dept
                            WHERE p.statut = 'valide'
                            GROUP BY d.faculte
                            ORDER BY total DESC
                        ");
                        $nb_rec = 0;
                        while ($rf = $recettesFac->fetch()):
                            $nb_rec++;
                            $pourcent = $totalRecettes > 0 ? round(($rf['total'] / $totalRecettes) * 100, 1) : 0;
                        ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($rf['faculte'] ?? 'Non assignée'); ?></strong></td>
                                <td><?= number_format($rf['total'], 0, ',', ' '); ?> BIF</td>
                                <td><span class="status-badge valide"><?= $pourcent; ?>%</span></td>
                            </tr>
                        <?php endwhile; ?>
                        <?php if ($nb_rec === 0): ?>
                            <tr><td colspan="3"><div class="empty-state"><i class="fas fa-coins"></i><p>Aucune recette enregistrée.</p></div></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>


        <!-- ============================================================
             PAGE : STATISTIQUES
        ============================================================ -->
        <?php if ($page == 'statistiques'): ?>
        <div class="panel">
            <div class="panel-header"><h2><i class="fas fa-chart-pie"></i> Statistiques de l'université</h2></div>
            <div class="grid-3">
                <div style="background:#f8fafc; padding:22px 18px; border-radius:var(--radius); text-align:center; border:1px solid var(--border-color);">
                    <div style="font-size:30px; font-weight:700; color:var(--accent-blue);"><?= number_format($totalEtudiants); ?></div>
                    <div style="font-size:12px; color:var(--text-muted); text-transform:uppercase; font-weight:600; margin-top:4px;">Étudiants</div>
                </div>
                <div style="background:#f8fafc; padding:22px 18px; border-radius:var(--radius); text-align:center; border:1px solid var(--border-color);">
                    <div style="font-size:30px; font-weight:700; color:var(--accent-success);"><?= number_format($totalProfesseurs); ?></div>
                    <div style="font-size:12px; color:var(--text-muted); text-transform:uppercase; font-weight:600; margin-top:4px;">Professeurs</div>
                </div>
                <div style="background:#f8fafc; padding:22px 18px; border-radius:var(--radius); text-align:center; border:1px solid var(--border-color);">
                    <div style="font-size:30px; font-weight:700; color:var(--accent-purple);"><?= $totalFacultes; ?></div>
                    <div style="font-size:12px; color:var(--text-muted); text-transform:uppercase; font-weight:600; margin-top:4px;">Facultés</div>
                </div>
                <div style="background:#f8fafc; padding:22px 18px; border-radius:var(--radius); text-align:center; border:1px solid var(--border-color);">
                    <div style="font-size:30px; font-weight:700; color:var(--accent-warning);"><?= $totalDepartements; ?></div>
                    <div style="font-size:12px; color:var(--text-muted); text-transform:uppercase; font-weight:600; margin-top:4px;">Départements</div>
                </div>
                <div style="background:#f8fafc; padding:22px 18px; border-radius:var(--radius); text-align:center; border:1px solid var(--border-color);">
                    <div style="font-size:30px; font-weight:700; color:var(--accent-pink);"><?= $totalCours; ?></div>
                    <div style="font-size:12px; color:var(--text-muted); text-transform:uppercase; font-weight:600; margin-top:4px;">Cours</div>
                </div>
                <div style="background:#f8fafc; padding:22px 18px; border-radius:var(--radius); text-align:center; border:1px solid var(--border-color);">
                    <div style="font-size:30px; font-weight:700; color:var(--accent-success);"><?= $tauxReussiteGlobal; ?>%</div>
                    <div style="font-size:12px; color:var(--text-muted); text-transform:uppercase; font-weight:600; margin-top:4px;">Taux de réussite</div>
                </div>
            </div>
        </div>
        <?php endif; ?>


        <!-- ============================================================
             PAGE : NOTIFICATIONS
        ============================================================ -->
        <?php if ($page == 'notifications'): ?>
        <div class="panel">
            <div class="panel-header">
                <h2><i class="fas fa-bell"></i> Notifications</h2>
                <span style="font-size:13px; color:var(--text-muted);"><?= $nbNotifs; ?> non lue(s)</span>
            </div>
            <?php
            $notifs = $bdd->query("SELECT * FROM notifications ORDER BY date_creation DESC LIMIT 30");
            $nb_notifs_list = 0;
            if ($notifs->rowCount() > 0):
                while ($n = $notifs->fetch()):
                    $nb_notifs_list++;
            ?>
                    <div class="list-item">
                        <div class="list-main">
                            <div class="list-title"><?= htmlspecialchars($n['titre']); ?></div>
                            <div class="list-text"><?= htmlspecialchars($n['message']); ?></div>
                            <div class="list-meta">📅 <?= date('d/m/Y H:i', strtotime($n['date_creation'])); ?></div>
                        </div>
                        <div style="display:flex; gap:6px; align-items:center; flex-shrink:0;">
                            <span class="status-badge <?= $n['statut'] == 'non_lu' ? 'en_attente' : 'valide'; ?>">
                                <?= $n['statut'] == 'non_lu' ? '⏳ Non lu' : '✅ Lu'; ?>
                            </span>
                            <?php if ($n['statut'] == 'non_lu'): ?>
                                <a href="?notif_lu=<?= (int)$n['id_notification']; ?>&csrf=<?= urlencode($csrf_token); ?>" class="btn-action success" title="Marquer comme lu"><i class="fas fa-check"></i></a>
                            <?php endif; ?>
                            <a href="?notif_supprimer=<?= (int)$n['id_notification']; ?>&csrf=<?= urlencode($csrf_token); ?>" class="btn-action danger" onclick="return confirm('Supprimer cette notification ?')" title="Supprimer"><i class="fas fa-trash"></i></a>
                        </div>
                    </div>
                <?php endwhile;
            endif;
            if ($nb_notifs_list === 0): ?>
                <div class="empty-state"><i class="fas fa-bell-slash"></i><p>Aucune notification.</p></div>
            <?php endif; ?>
        </div>
        <?php endif; ?>


        <!-- ============================================================
             PAGE : COMMUNICATIONS
        ============================================================ -->
        <?php if ($page == 'communications'): ?>
        <div class="panel">
            <div class="panel-header"><h2><i class="fas fa-bullhorn"></i> Communications institutionnelles</h2></div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                <div class="form-group">
                    <label>Destinataires</label>
                    <select name="destinataire">
                        <option value="tous">Toute l'université</option>
                        <option value="etudiants">Étudiants</option>
                        <option value="professeurs">Professeurs</option>
                        <option value="doyens">Doyens/Doyennes</option>
                        <option value="personnel">Personnel administratif</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Objet</label>
                    <input type="text" name="sujet" placeholder="Objet de la communication" required>
                </div>
                <div class="form-group">
                    <label>Message</label>
                    <textarea name="message" rows="4" required></textarea>
                </div>
                <button type="submit" name="envoyer_communication" class="btn btn-primary">
                    <i class="fas fa-paper-plane"></i> Envoyer
                </button>
            </form>
        </div>
        <?php endif; ?>


        <!-- ============================================================
             PAGE : AUDIT
        ============================================================ -->
        <?php if ($page == 'audit'): ?>
        <div class="panel">
            <div class="panel-header"><h2><i class="fas fa-history"></i> Journal d'audit</h2></div>
            <div class="table-responsive">
                <table>
                    <thead><tr><th>Utilisateur</th><th>Action</th><th>Table</th><th>Date</th></tr></thead>
                    <tbody>
                        <?php
                        $audit = $bdd->query("SELECT a.*, u.nom, u.prenom FROM audit_financier a JOIN users u ON a.id_user = u.id_user ORDER BY a.date_action DESC LIMIT 50");
                        $nb_audit = 0;
                        while ($a = $audit->fetch()):
                            $nb_audit++;
                        ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($a['nom'] . ' ' . $a['prenom']); ?></strong></td>
                                <td><?= htmlspecialchars($a['action']); ?></td>
                                <td><?= htmlspecialchars($a['table_concernee']); ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($a['date_action'])); ?></td>
                            </tr>
                        <?php endwhile; ?>
                        <?php if ($nb_audit === 0): ?>
                            <tr><td colspan="4"><div class="empty-state"><i class="fas fa-history"></i><p>Aucune action enregistrée.</p></div></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>


        <!-- ============================================================
             PAGE : PROFIL
        ============================================================ -->
        <?php if ($page == 'profil'): ?>
        <div class="panel">
            <div class="panel-header"><h2><i class="fas fa-user"></i> Mon profil</h2></div>
            <div style="display:flex; gap:24px; flex-wrap:wrap;">
                <div style="flex:0 0 200px; text-align:center;">
                    <img src="uploads/<?= htmlspecialchars($photo); ?>" style="width:150px; height:150px; border-radius:50%; object-fit:cover; border:4px solid var(--gold); box-shadow: 0 8px 20px rgba(212,175,55,0.3);">
                    <h3 style="margin-top:12px; font-size:17px;">Prof. <?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']); ?></h3>
                    <span class="badge-role" style="margin-top:8px;">👑 RECTEUR</span>
                </div>
                <div style="flex:1; min-width:280px;">
                    <div class="grid-2">
                        <div><strong>Nom :</strong> <?= htmlspecialchars($user['nom']); ?></div>
                        <div><strong>Prénom :</strong> <?= htmlspecialchars($user['prenom']); ?></div>
                        <div><strong>Rôle :</strong> Recteur</div>
                        <div><strong>Email :</strong> <?= htmlspecialchars($user['email'] ?? 'recteur@univ.bi'); ?></div>
                        <div><strong>Téléphone :</strong> <?= htmlspecialchars($user['telephone'] ?? '+257 00 00 00 00'); ?></div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>


        <!-- ============================================================
             PAGE : RAPPORTS
        ============================================================ -->
        <?php if ($page == 'rapports'): ?>
        <div class="panel">
            <div class="panel-header"><h2><i class="fas fa-file-alt"></i> Rapports institutionnels</h2></div>
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px,1fr)); gap:15px;">
                <a href="export_excel_universel.php?table=paiements&filename=Rapport_Paiements" class="btn btn-success" style="justify-content:center;"><i class="fas fa-file-excel"></i> Rapport Excel</a>
                <a href="rapports_personnalises.php?type=paiements" class="btn btn-primary" style="justify-content:center;"><i class="fas fa-chart-bar"></i> Rapports personnalisés</a>
                <a href="export_excel_universel.php?table=notes&filename=Rapport_Notes" class="btn btn-outline" style="justify-content:center;"><i class="fas fa-graduation-cap"></i> Académique</a>
                <a href="export_excel_universel.php?table=paiements&filename=Rapport_Financier" class="btn btn-outline" style="justify-content:center;"><i class="fas fa-coins"></i> Financier</a>
                <a href="export_excel_universel.php?table=etudiants&filename=Rapport_Etudiants" class="btn btn-outline" style="justify-content:center;"><i class="fas fa-user-graduate"></i> Étudiants</a>
                <a href="export_excel_universel.php?table=professeurs&filename=Rapport_Professeurs" class="btn btn-outline" style="justify-content:center;"><i class="fas fa-chalkboard-teacher"></i> Enseignants</a>
            </div>
        </div>
        <?php endif; ?>


        <!-- ============================================================
             FOOTER
        ============================================================ -->
        <div style="text-align:center; padding:20px 0 10px; color:var(--text-light); font-size:12px; border-top:1px solid var(--border-color); margin-top:10px;">
            <p>© <?= date('Y'); ?> UniVerse — Chancellerie</p>
        </div>

    </main>

    <!-- ================================================================
         JAVASCRIPT GRAPHIQUES
    ================================================================ -->
    <script>
    <?php if ($page == 'dashboard'): ?>
    document.addEventListener('DOMContentLoaded', function() {
        const facultesLabels = <?= json_encode(array_column($statsParFaculte, 'faculte')); ?>;
        const facultesData   = <?= json_encode(array_column($statsParFaculte, 'nb_etudiants')); ?>;
        const colors         = ['#2563eb', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#14b8a6'];

        if (facultesLabels.length > 0) {
            new Chart(document.getElementById('chartFacultes').getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: facultesLabels,
                    datasets: [{
                        data: facultesData,
                        backgroundColor: colors.slice(0, facultesLabels.length),
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom', labels: { boxWidth: 12, padding: 12, font: { size: 11, weight: '600' } } }
                    },
                    cutout: '62%'
                }
            });
        } else {
            document.getElementById('chartFacultes').parentElement.innerHTML = '<div class="empty-state"><i class="fas fa-chart-pie"></i><p>Aucune donnée disponible</p></div>';
        }

        const facReussiteLabels = <?= json_encode(array_column($reussiteParFaculte, 'faculte')); ?>;
        const facReussiteData   = facReussiteLabels.map(label => {
            const found = <?= json_encode($reussiteParFaculte); ?>.find(item => item.faculte === label);
            if (found) {
                const total = found.reussite + found.echec;
                return total > 0 ? Math.round((found.reussite / total) * 100) : 0;
            }
            return 0;
        });

        if (facReussiteLabels.length > 0) {
            new Chart(document.getElementById('chartReussiteFacultes').getContext('2d'), {
                type: 'bar',
                data: {
                    labels: facReussiteLabels,
                    datasets: [{
                        label: 'Taux de réussite (%)',
                        data: facReussiteData,
                        backgroundColor: ['#10b981', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899'],
                        borderRadius: 6,
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: { beginAtZero: true, max: 100, ticks: { callback: function(value) { return value + '%'; } } },
                        x: { grid: { display: false } }
                    },
                    plugins: { legend: { display: false } }
                }
            });
        } else {
            document.getElementById('chartReussiteFacultes').parentElement.innerHTML = '<div class="empty-state"><i class="fas fa-chart-bar"></i><p>Aucune donnée disponible</p></div>';
        }
    });
    <?php endif; ?>
    </script>

</body>
</html>