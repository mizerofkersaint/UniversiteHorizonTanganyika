<?php
require_once __DIR__ . '/db.php';
require_once 'auth.php';
requireRole('Comptable');

/* ============================================================
   CONNEXION À LA BASE DE DONNÉES
============================================================ */
try {
    $bdd = getDBConnection();
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

/* ============================================================
   CSRF TOKEN
============================================================ */
if (empty($_SESSION['csrf_comptable'])) {
    $_SESSION['csrf_comptable'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_comptable'];

/* ============================================================
   MESSAGE FLASH
============================================================ */
$message = "";
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

/* ============================================================
   UTILISATEUR CONNECTÉ
============================================================ */
$id_user = $USER_ID;
$stmtUser = $bdd->prepare("SELECT id_user, nom, prenom, photo, role FROM users WHERE id_user = ?");
$stmtUser->execute([$id_user]);
$user = $stmtUser->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("Utilisateur introuvable.");
}

define('MINERVAL_ANNUEL', 500000);

/* ============================================================
   PARAMÈTRES DE PAGE
============================================================ */
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

/* ------------------------------------------------------------
   CRUD via IFRAME
------------------------------------------------------------ */
$crud = isset($_GET['crud']) ? trim($_GET['crud']) : '';
$url_iframe = '';
$titre_iframe = '';

if ($crud !== '') {
    $pages_crud = [
        'paiement_liste' => ['url' => 'affichepaiement.php',  'titre' => 'Gestion des paiements'],
        'paiement_add'   => ['url' => 'ajoutepaiement.php',   'titre' => 'Ajouter un paiement'],
    ];
    if (isset($pages_crud[$crud])) {
        $url_iframe = $pages_crud[$crud]['url'];
        $titre_iframe = $pages_crud[$crud]['titre'];
    }
}

/* ============================================================
   HELPER : VÉRIFICATION CSRF
============================================================ */
function verifierCsrf($token) {
    return isset($_SESSION['csrf_comptable']) && hash_equals($_SESSION['csrf_comptable'], $token);
}

/* ============================================================
   TRAITEMENT DES ACTIONS
============================================================ */

/* --- VALIDER UN PAIEMENT --- */
if (isset($_GET['valider']) && !empty($_GET['valider'])) {
    $id_paiement = intval($_GET['valider']);
    $token_get   = isset($_GET['csrf']) ? $_GET['csrf'] : '';

    if (!verifierCsrf($token_get)) {
        $_SESSION['message'] = "<div class='alert error'>❌ Jeton de sécurité invalide.</div>";
        header("Location: dashboard_comptable.php");
        exit();
    }

    try {
        $bdd->beginTransaction();
        $check = $bdd->prepare("SELECT id_etudiant, statut, bordereau, montant, tranche, date_paiement FROM paiements WHERE id_paiement = ?");
        $check->execute([$id_paiement]);
        $paiement = $check->fetch(PDO::FETCH_ASSOC);
        if ($paiement && $paiement['statut'] == 'en_attente') {
            $checkDoublon = $bdd->prepare("SELECT COUNT(*) FROM paiements WHERE bordereau = ? AND statut = 'valide' AND id_paiement != ?");
            $checkDoublon->execute([$paiement['bordereau'], $id_paiement]);
            if ($checkDoublon->fetchColumn() == 0) {
                $upd = $bdd->prepare("UPDATE paiements SET statut = 'valide', date_validation = NOW(), valide_par = ? WHERE id_paiement = ?");
                $upd->execute([$id_user, $id_paiement]);
                $hist = $bdd->prepare("INSERT INTO historique_paiements (id_paiement, id_etudiant, action, ancien_statut, nouveau_statut, date_action) VALUES (?, ?, 'validation', 'en_attente', 'valide', NOW())");
                $hist->execute([$id_paiement, $paiement['id_etudiant']]);
                $bdd->commit();
                $_SESSION['message'] = "<div class='alert success'>✅ Bordereau N° " . htmlspecialchars($paiement['bordereau']) . " validé avec succès !</div>";

                /* Email de confirmation (silencieux en cas d'erreur) */
                try {
                    require_once 'functions_email.php';
                    $stmt_email = $bdd->prepare("SELECT u.email, u.nom, u.prenom FROM etudiants e JOIN users u ON e.id_user = u.id_user WHERE e.id_etudiant = ?");
                    $stmt_email->execute([$paiement['id_etudiant']]);
                    $etudiant_email = $stmt_email->fetch(PDO::FETCH_ASSOC);
                    if ($etudiant_email) {
                        $contenu = '<p>Bonjour <strong>' . htmlspecialchars($etudiant_email['prenom']) . '</strong>,</p>
                            <p>Votre paiement a été validé par le service comptable.</p>
                            <ul>
                                <li><strong>Montant :</strong> ' . number_format($paiement['montant'], 0, ',', ' ') . ' BIF</li>
                                <li><strong>Tranche :</strong> ' . htmlspecialchars($paiement['tranche']) . '</li>
                                <li><strong>Date :</strong> ' . date('d/m/Y', strtotime($paiement['date_paiement'])) . '</li>
                            </ul>';
                        envoyerEmail($etudiant_email['email'], 'Paiement validé - UniVerse', templateEmail('Paiement validé', $contenu, '#16a34a'));
                    }
                } catch (Exception $mailEx) {
                    error_log("[dashboard_comptable] Email : " . $mailEx->getMessage());
                }
            } else {
                $_SESSION['message'] = "<div class='alert error'>⚠ Ce numéro de bordereau existe déjà dans un paiement validé.</div>";
            }
        } else {
            $_SESSION['message'] = "<div class='alert error'>❌ Ce bordereau n'est plus en attente.</div>";
        }
    } catch (Exception $e) {
        $bdd->rollBack();
        $_SESSION['message'] = "<div class='alert error'>❌ Erreur : " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    header("Location: dashboard_comptable.php");
    exit();
}

/* --- REJETER UN PAIEMENT --- */
if (isset($_POST['rejeter_bordereau'])) {
    if (!isset($_POST['csrf_token']) || !verifierCsrf($_POST['csrf_token'])) {
        $_SESSION['message'] = "<div class='alert error'>❌ Jeton de sécurité invalide.</div>";
        header("Location: dashboard_comptable.php");
        exit();
    }
    $id_paiement = intval($_POST['id_paiement']);
    $motif_rejet = trim($_POST['motif_rejet']);
    try {
        $bdd->beginTransaction();
        $check = $bdd->prepare("SELECT id_etudiant, statut, bordereau, montant FROM paiements WHERE id_paiement = ?");
        $check->execute([$id_paiement]);
        $paiement = $check->fetch(PDO::FETCH_ASSOC);
        if ($paiement && $paiement['statut'] == 'en_attente') {
            $upd = $bdd->prepare("UPDATE paiements SET statut = 'rejete', motif_rejet = ?, date_validation = NOW(), valide_par = ? WHERE id_paiement = ?");
            $upd->execute([$motif_rejet, $id_user, $id_paiement]);
            $hist = $bdd->prepare("INSERT INTO historique_paiements (id_paiement, id_etudiant, action, ancien_statut, nouveau_statut, date_action) VALUES (?, ?, 'rejet', 'en_attente', 'rejete', NOW())");
            $hist->execute([$id_paiement, $paiement['id_etudiant']]);
            $bdd->commit();
            $_SESSION['message'] = "<div class='alert error'>⛔ Bordereau rejeté. Motif : " . htmlspecialchars($motif_rejet) . "</div>";
        } else {
            $_SESSION['message'] = "<div class='alert error'>❌ Ce bordereau n'est plus en attente.</div>";
        }
    } catch (Exception $e) {
        $bdd->rollBack();
        $_SESSION['message'] = "<div class='alert error'>❌ Erreur : " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    header("Location: dashboard_comptable.php");
    exit();
}

/* --- AJOUTER UN PAIEMENT --- */
if (isset($_POST['action_ajouter'])) {
    if (!isset($_POST['csrf_token']) || !verifierCsrf($_POST['csrf_token'])) {
        $_SESSION['message'] = "<div class='alert error'>❌ Jeton de sécurité invalide.</div>";
        header("Location: dashboard_comptable.php");
        exit();
    }
    $id_etudiant = intval($_POST['id_etudiant']);
    $tranche = trim($_POST['tranche']);
    $montant = floatval($_POST['montant']);
    $date_paiement = $_POST['date_paiement'];
    $banque = trim($_POST['banque']);
    $bordereau = trim($_POST['bordereau']);
    $motif = isset($_POST['motif']) ? trim($_POST['motif']) : null;
    $mode_paiement = isset($_POST['mode_paiement']) ? trim($_POST['mode_paiement']) : 'Especes';
    $annee_academique = isset($_POST['annee_academique']) ? trim($_POST['annee_academique']) : date('Y') . '-' . (date('Y') + 1);

    if ($id_etudiant > 0 && $montant > 0) {
        try {
            $bdd->beginTransaction();
            $ins = $bdd->prepare("INSERT INTO paiements (id_etudiant, tranche, montant, date_paiement, banque, bordereau, motif, statut, mode_paiement, annee_academique) VALUES (?, ?, ?, ?, ?, ?, ?, 'en_attente', ?, ?)");
            $ins->execute([$id_etudiant, $tranche, $montant, $date_paiement, $banque, $bordereau, $motif, $mode_paiement, $annee_academique]);
            $id_paiement = $bdd->lastInsertId();
            $hist = $bdd->prepare("INSERT INTO historique_paiements (id_paiement, id_etudiant, action, ancien_statut, nouveau_statut, date_action) VALUES (?, ?, 'creation', NULL, 'en_attente', NOW())");
            $hist->execute([$id_paiement, $id_etudiant]);
            $bdd->commit();
            $_SESSION['message'] = "<div class='alert success'>🎉 Bordereau enregistré ! En attente de validation.</div>";
        } catch (Exception $e) {
            $bdd->rollBack();
            $_SESSION['message'] = "<div class='alert error'>❌ Erreur : " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    } else {
        $_SESSION['message'] = "<div class='alert error'>❌ Veuillez remplir tous les champs requis.</div>";
    }
    header("Location: dashboard_comptable.php");
    exit();
}

/* --- MODIFIER UN PAIEMENT --- */
if (isset($_POST['action_modifier'])) {
    if (!isset($_POST['csrf_token']) || !verifierCsrf($_POST['csrf_token'])) {
        $_SESSION['message'] = "<div class='alert error'>❌ Jeton de sécurité invalide.</div>";
        header("Location: dashboard_comptable.php");
        exit();
    }
    $id_paiement = intval($_POST['id_paiement']);
    $tranche = trim($_POST['tranche']);
    $montant = floatval($_POST['montant']);
    $date_paiement = $_POST['date_paiement'];
    $banque = trim($_POST['banque']);
    $bordereau = trim($_POST['bordereau']);
    $motif = isset($_POST['motif']) ? trim($_POST['motif']) : null;
    $mode_paiement = isset($_POST['mode_paiement']) ? trim($_POST['mode_paiement']) : 'Especes';
    $annee_academique = isset($_POST['annee_academique']) ? trim($_POST['annee_academique']) : date('Y') . '-' . (date('Y') + 1);

    try {
        $bdd->beginTransaction();
        $check = $bdd->prepare("SELECT statut, id_etudiant FROM paiements WHERE id_paiement = ?");
        $check->execute([$id_paiement]);
        $old = $check->fetch(PDO::FETCH_ASSOC);
        $upd = $bdd->prepare("UPDATE paiements SET tranche = ?, montant = ?, date_paiement = ?, banque = ?, bordereau = ?, motif = ?, mode_paiement = ?, annee_academique = ? WHERE id_paiement = ?");
        $upd->execute([$tranche, $montant, $date_paiement, $banque, $bordereau, $motif, $mode_paiement, $annee_academique, $id_paiement]);
        if ($old && $old['statut'] == 'valide') {
            $updStatut = $bdd->prepare("UPDATE paiements SET statut = 'en_attente' WHERE id_paiement = ?");
            $updStatut->execute([$id_paiement]);
            $hist = $bdd->prepare("INSERT INTO historique_paiements (id_paiement, id_etudiant, action, ancien_statut, nouveau_statut, date_action) VALUES (?, ?, 'modification_retour_attente', 'valide', 'en_attente', NOW())");
            $hist->execute([$id_paiement, $old['id_etudiant']]);
        }
        $bdd->commit();
        $_SESSION['message'] = "<div class='alert success'>🔄 Paiement #" . $id_paiement . " mis à jour avec succès.</div>";
    } catch (Exception $e) {
        $bdd->rollBack();
        $_SESSION['message'] = "<div class='alert error'>❌ Erreur : " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    header("Location: dashboard_comptable.php");
    exit();
}

/* --- SUPPRIMER UN PAIEMENT (avec CSRF via GET token) --- */
if (isset($_GET['supprimer']) && !empty($_GET['supprimer'])) {
    $token_get = isset($_GET['csrf']) ? $_GET['csrf'] : '';
    if (!verifierCsrf($token_get)) {
        $_SESSION['message'] = "<div class='alert error'>❌ Jeton de sécurité invalide.</div>";
        header("Location: dashboard_comptable.php");
        exit();
    }
    $id_del = intval($_GET['supprimer']);
    try {
        $del = $bdd->prepare("DELETE FROM paiements WHERE id_paiement = ?");
        $del->execute([$id_del]);
        $_SESSION['message'] = "<div class='alert error'>🗑️ Enregistrement supprimé.</div>";
    } catch (Exception $e) {
        $_SESSION['message'] = "<div class='alert error'>❌ Erreur : " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    header("Location: dashboard_comptable.php");
    exit();
}

/* ============================================================
   STATISTIQUES (avec comparaisons réelles)
============================================================ */
$annee_courante = date('Y') . '-' . (date('Y') + 1);
$annee_filtre = isset($_GET['annee']) ? trim($_GET['annee']) : $annee_courante;

$totalEtudiants     = $bdd->query("SELECT COUNT(*) FROM etudiants")->fetchColumn() ?: 0;
$recettesJour       = $bdd->query("SELECT SUM(montant) FROM paiements WHERE DATE(date_paiement) = CURDATE() AND statut = 'valide'")->fetchColumn() ?: 0;
$recettesHier       = $bdd->query("SELECT SUM(montant) FROM paiements WHERE DATE(date_paiement) = DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND statut = 'valide'")->fetchColumn() ?: 0;
$recettesMois       = $bdd->query("SELECT SUM(montant) FROM paiements WHERE MONTH(date_paiement) = MONTH(CURDATE()) AND YEAR(date_paiement) = YEAR(CURDATE()) AND statut = 'valide'")->fetchColumn() ?: 0;
$recettesMoisPrec   = $bdd->query("SELECT SUM(montant) FROM paiements WHERE MONTH(date_paiement) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND YEAR(date_paiement) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND statut = 'valide'")->fetchColumn() ?: 0;
$recettesAnnuelles  = $bdd->query("SELECT SUM(montant) FROM paiements WHERE YEAR(date_paiement) = YEAR(CURDATE()) AND statut = 'valide'")->fetchColumn() ?: 0;
$recettesAnneePrec  = $bdd->query("SELECT SUM(montant) FROM paiements WHERE YEAR(date_paiement) = YEAR(CURDATE()) - 1 AND statut = 'valide'")->fetchColumn() ?: 0;
$soldeCaisse        = $bdd->query("SELECT SUM(montant) FROM paiements WHERE statut = 'valide'")->fetchColumn() ?: 0;
$paiementsValides   = $bdd->query("SELECT COUNT(*) FROM paiements WHERE statut = 'valide'")->fetchColumn() ?: 0;
$paiementsAnnules   = $bdd->query("SELECT COUNT(*) FROM paiements WHERE statut = 'rejete'")->fetchColumn() ?: 0;
$paiementsEnAttente = $bdd->query("SELECT COUNT(*) FROM paiements WHERE statut = 'en_attente'")->fetchColumn() ?: 0;
$nbRecus            = $bdd->query("SELECT COUNT(*) FROM paiements WHERE numero_recu IS NOT NULL")->fetchColumn() ?: 0;

/* Comparaison safe */
function pct($actuel, $precedent) {
    if ($precedent <= 0) return $actuel > 0 ? 100 : 0;
    return round((($actuel - $precedent) / $precedent) * 100, 1);
}
$pctJour  = pct($recettesJour, $recettesHier);
$pctMois  = pct($recettesMois, $recettesMoisPrec);
$pctAnnee = pct($recettesAnnuelles, $recettesAnneePrec);

/* Débiteurs */
$debiteurs = $bdd->query("
    SELECT e.id_etudiant, u.nom, u.prenom, e.matricule, COALESCE(SUM(p.montant), 0) as total_paye,
           d.nom_dept, c.niveau
    FROM etudiants e
    JOIN users u ON e.id_user = u.id_user
    JOIN classes c ON e.id_classe = c.id_classe
    JOIN departements d ON c.id_dept = d.id_dept
    LEFT JOIN paiements p ON e.id_etudiant = p.id_etudiant AND p.statut = 'valide'
    GROUP BY e.id_etudiant
    HAVING total_paye < " . MINERVAL_ANNUEL . "
")->fetchAll(PDO::FETCH_ASSOC);
$nbDebiteurs = count($debiteurs);

/* Listes */
$listeDepartements = $bdd->query("SELECT id_dept, nom_dept, faculte FROM departements ORDER BY nom_dept")->fetchAll(PDO::FETCH_ASSOC);
$listeClasses      = $bdd->query("SELECT * FROM classes ORDER BY niveau")->fetchAll(PDO::FETCH_ASSOC);
$listeEtudiants    = $bdd->query("
    SELECT e.id_etudiant, u.nom, u.prenom, e.matricule, c.niveau, d.nom_dept, c.id_classe, d.id_dept
    FROM etudiants e
    JOIN users u ON e.id_user = u.id_user
    LEFT JOIN classes c ON e.id_classe = c.id_classe
    LEFT JOIN departements d ON c.id_dept = d.id_dept
    ORDER BY u.nom ASC
")->fetchAll(PDO::FETCH_ASSOC);

/* Pagination paiements */
$limite   = 10;
$page_num = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$debut    = ($page_num - 1) * $limite;
$total    = $bdd->query("SELECT COUNT(*) FROM paiements")->fetchColumn() ?: 0;
$nbPages  = ceil($total / $limite) < 1 ? 1 : ceil($total / $limite);

$listePaiements = $bdd->query("
    SELECT p.*, u.nom, u.prenom, e.matricule, d.nom_dept, d.faculte
    FROM paiements p
    JOIN etudiants e ON p.id_etudiant = e.id_etudiant
    JOIN users u ON e.id_user = u.id_user
    JOIN classes c ON e.id_classe = c.id_classe
    JOIN departements d ON c.id_dept = d.id_dept
    ORDER BY p.date_paiement DESC
    LIMIT $debut, $limite
")->fetchAll(PDO::FETCH_ASSOC);

/* Paiement en cours de modification */
$paiement_a_modifier = null;
if (isset($_GET['modifier'])) {
    $id_mod = intval($_GET['modifier']);
    $reqMod = $bdd->prepare("SELECT * FROM paiements WHERE id_paiement = ?");
    $reqMod->execute([$id_mod]);
    $paiement_a_modifier = $reqMod->fetch(PDO::FETCH_ASSOC);
}

/* Données graphiques */
$mois_data = $bdd->query("
    SELECT DATE_FORMAT(date_paiement, '%Y-%m') AS mois, SUM(montant) AS total
    FROM paiements WHERE statut = 'valide' AND date_paiement >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY mois ORDER BY mois ASC
")->fetchAll(PDO::FETCH_ASSOC);
$mois_labels   = array_map(fn($m) => date('M', strtotime($m['mois'].'-01')), $mois_data);
$mois_montants = array_map(fn($m) => (int)$m['total'], $mois_data);

$dept_data_raw = $bdd->query("
    SELECT d.nom_dept, SUM(p.montant) AS total
    FROM paiements p
    JOIN etudiants e ON p.id_etudiant = e.id_etudiant
    JOIN classes c ON e.id_classe = c.id_classe
    JOIN departements d ON c.id_dept = d.id_dept
    WHERE p.statut = 'valide'
    GROUP BY d.id_dept ORDER BY total DESC LIMIT 7
")->fetchAll(PDO::FETCH_ASSOC);
$dept_labels = array_column($dept_data_raw, 'nom_dept');
$dept_data   = array_map(fn($d) => (int)$d['total'], $dept_data_raw);

$banque_data_raw = $bdd->query("
    SELECT banque, SUM(montant) AS total FROM paiements WHERE statut = 'valide' AND banque IS NOT NULL AND banque != '' GROUP BY banque ORDER BY total DESC LIMIT 6
")->fetchAll(PDO::FETCH_ASSOC);
$banque_labels = array_column($banque_data_raw, 'banque');
$banque_data   = array_map(fn($b) => (int)$b['total'], $banque_data_raw);

function buildQuery($extra = []) {
    $q = $_GET;
    foreach ($extra as $k => $v) $q[$k] = $v;
    return http_build_query($q);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Services Financiers — UniVerse</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="assets/theme.css">
    <script src="assets/theme.js"></script>
    <style>
        :root {
            --bg-light: #f1f5f9;
            --bg-white: #ffffff;
            --sidebar-color: #0f172a;
            --sidebar-hover: #1e293b;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --text-light: #94a3b8;
            --text-white: #ffffff;
            --accent-blue: #2563eb;
            --accent-blue-dark: #1d4ed8;
            --success: #10b981;
            --success-dark: #059669;
            --warning: #f59e0b;
            --warning-dark: #d97706;
            --error: #ef4444;
            --error-dark: #dc2626;
            --purple: #8b5cf6;
            --pink: #ec4899;
            --border-color: #e2e8f0;
            --radius: 14px;
            --radius-sm: 10px;
            --shadow-xs: 0 1px 2px rgba(0,0,0,0.04);
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
            --transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; }
        body { background-color: var(--bg-light); color: var(--text-main); display: flex; min-height: 100vh; -webkit-font-smoothing: antialiased; }

        /* =====================================================
           SIDEBAR
        ===================================================== */
        .sidebar {
            width: 260px;
            background-color: var(--sidebar-color);
            color: var(--text-white);
            padding: 24px 16px;
            position: fixed;
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            z-index: 100;
            transition: var(--transition);
        }
        .sidebar::-webkit-scrollbar { width: 4px; }
        .sidebar::-webkit-scrollbar-thumb { background: #334155; border-radius: 2px; }
        .sidebar .logo {
            font-size: 22px;
            font-weight: 800;
            margin-bottom: 22px;
            padding: 0 10px;
            letter-spacing: 0.5px;
            background: linear-gradient(135deg, #60a5fa, #a78bfa);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .user-profile {
            background: rgba(255,255,255,0.05);
            padding: 16px 14px;
            border-radius: var(--radius-sm);
            margin-bottom: 22px;
            text-align: center;
            border: 1px solid rgba(255,255,255,0.06);
        }
        .profile-img {
            width: 62px; height: 62px; border-radius: 50%;
            object-fit: cover; margin-bottom: 10px;
            border: 2px solid var(--success);
        }
        .user-profile .name { font-weight: 600; font-size: 14px; color: var(--text-white); }
        .user-profile .title { font-size: 11px; color: var(--text-light); margin-top: 2px; }
        .badge-role {
            display: inline-block; padding: 4px 10px;
            font-size: 10px; font-weight: 700; text-transform: uppercase;
            border-radius: 20px; margin-top: 8px;
            background: linear-gradient(135deg, var(--success), var(--success-dark));
            color: white; letter-spacing: 0.5px;
        }

        .nav-links { list-style: none; display: flex; flex-direction: column; gap: 3px; flex: 1; }
        .nav-links a {
            color: var(--text-light);
            text-decoration: none;
            padding: 11px 14px;
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
            font-size: 14px;
            transition: var(--transition);
        }
        .nav-links a:hover { background: rgba(255,255,255,0.06); color: var(--text-white); }
        .nav-links a.active {
            background: linear-gradient(135deg, rgba(16,185,129,0.15), rgba(16,185,129,0.05));
            color: var(--text-white);
            box-shadow: inset 3px 0 0 var(--success);
        }
        .nav-links a i { width: 20px; text-align: center; font-size: 14px; }
        .nav-badge {
            margin-left: auto;
            background: var(--warning);
            color: #0f172a;
            font-size: 10px;
            padding: 2px 8px;
            border-radius: 10px;
            font-weight: 700;
        }
        .nav-links a.logout {
            color: var(--error);
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid rgba(255,255,255,0.06);
        }
        .nav-links a.logout:hover { background: rgba(239,68,68,0.1); }

        /* =====================================================
           MAIN CONTENT
        ===================================================== */
        .main-content {
            margin-left: 260px;
            width: calc(100% - 260px);
            padding: 28px 32px;
            min-height: 100vh;
        }

        header.page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
            flex-wrap: wrap;
            gap: 12px;
        }
        header.page-header h1 {
            font-size: 26px;
            color: var(--text-main);
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        header.page-header h1 i { color: var(--success); font-size: 22px; }
        .header-actions { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }

        /* =====================================================
           BOUTONS
        ===================================================== */
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
        .btn-success { background: var(--success); color: white; }
        .btn-success:hover { background: var(--success-dark); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(16,185,129,0.3); }
        .btn-danger { background: var(--error); color: white; }
        .btn-danger:hover { background: var(--error-dark); transform: translateY(-2px); }
        .btn-secondary { background: #e2e8f0; color: #475569; }
        .btn-secondary:hover { background: #cbd5e1; }
        .btn-outline { background: transparent; color: var(--text-muted); border: 1px solid var(--border-color); }
        .btn-outline:hover { background: var(--bg-light); }

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
        .theme-toggle-btn:hover { background: var(--bg-light); transform: rotate(15deg); }

        /* =====================================================
           ALERTES
        ===================================================== */
        .alert {
            padding: 14px 20px;
            border-radius: var(--radius-sm);
            margin-bottom: 20px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            animation: slideDown 0.35s ease;
        }
        .alert.success { background: #d1fae5; color: #065f46; border-left: 4px solid var(--success); }
        .alert.error { background: #fee2e2; color: #991b1b; border-left: 4px solid var(--error); }
        .alert.warning { background: #fef3c7; color: #92400e; border-left: 4px solid var(--warning); }
        .alert.info { background: #eff6ff; color: #1e40af; border-left: 4px solid var(--accent-blue); }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

        /* =====================================================
           STAT CARDS
        ===================================================== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 18px;
            margin-bottom: 28px;
        }
        .stat-card {
            background: var(--bg-white);
            padding: 20px 22px;
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
            opacity: 0.7;
        }
        .stat-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); }
        .stat-card.green::before  { background: var(--success); }
        .stat-card.blue::before   { background: var(--accent-blue); }
        .stat-card.orange::before { background: var(--warning); }
        .stat-card.red::before    { background: var(--error); }
        .stat-card.purple::before { background: var(--purple); }

        .stat-card .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 8px;
        }
        .stat-card .stat-label {
            font-size: 11px;
            color: var(--text-muted);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .stat-card .stat-icon {
            font-size: 20px;
            color: var(--text-light);
            opacity: 0.6;
        }
        .stat-card .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-main);
            line-height: 1.2;
        }
        .stat-card .stat-change {
            font-size: 11px;
            font-weight: 600;
            margin-top: 6px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .stat-card .stat-change.up   { color: var(--success); }
        .stat-card .stat-change.down { color: var(--error); }
        .stat-card .stat-change.neutral { color: var(--text-muted); }

        /* =====================================================
           PANELS
        ===================================================== */
        .panel {
            background: var(--bg-white);
            padding: 24px 28px;
            border-radius: var(--radius);
            border: 1px solid var(--border-color);
            margin-bottom: 24px;
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
            border-bottom: 1px solid var(--bg-light);
        }
        .panel-header h2 {
            font-size: 17px;
            color: var(--text-main);
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .panel-header .panel-actions { display: flex; gap: 8px; flex-wrap: wrap; }

        /* =====================================================
           GRIDS
        ===================================================== */
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 22px; margin-bottom: 22px; }
        .grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 22px; margin-bottom: 22px; }

        /* =====================================================
           TABLES
        ===================================================== */
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
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
            font-size: 13px;
        }
        tr { transition: var(--transition); }
        tr:hover td { background: #fafcff; }
        tr:last-child td { border-bottom: none; }

        /* =====================================================
           BADGES
        ===================================================== */
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

        .faculte-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            background: #eff6ff;
            color: #1e40af;
            font-weight: 600;
            font-size: 11px;
        }

        /* =====================================================
           BTN-ACTION (petits boutons d'action)
        ===================================================== */
        .btn-action {
            padding: 6px 10px;
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
        .btn-action.success:hover { background: var(--success); color: white; }
        .btn-action.danger { background: #fee2e2; color: #991b1b; }
        .btn-action.danger:hover { background: var(--error); color: white; }
        .btn-action.primary { background: #eff6ff; color: #1e40af; }
        .btn-action.primary:hover { background: var(--accent-blue); color: white; }
        .btn-action.warning { background: #fef3c7; color: #92400e; }
        .btn-action.warning:hover { background: var(--warning); color: white; }

        /* =====================================================
           FORMULAIRES
        ===================================================== */
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
        .form-group label .required { color: var(--error); margin-left: 2px; }
        input, select, textarea {
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
        input:focus, select:focus, textarea:focus {
            border-color: var(--accent-blue);
            background: white;
            box-shadow: 0 0 0 3px rgba(37,99,235,0.08);
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        /* =====================================================
           FILTER BAR
        ===================================================== */
        .filter-bar {
            background: white;
            padding: 18px 20px;
            border-radius: var(--radius);
            border: 1px solid var(--border-color);
            margin-bottom: 22px;
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
            align-items: flex-end;
        }
        .filter-bar .form-group {
            flex: 1;
            min-width: 150px;
            margin-bottom: 0;
        }
        .filter-bar .form-group label {
            font-size: 11px;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
        }
        .filter-bar .form-group select,
        .filter-bar .form-group input {
            padding: 9px 12px;
            font-size: 13px;
            background: #f8fafc;
        }
        .filter-actions { display: flex; gap: 10px; }

        /* =====================================================
           PAGINATION
        ===================================================== */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 22px;
            flex-wrap: wrap;
        }
        .pagination a {
            background: white;
            color: var(--accent-blue);
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            font-weight: 600;
            font-size: 13px;
            transition: var(--transition);
        }
        .pagination a:hover { background: var(--accent-blue); color: white; border-color: var(--accent-blue); }
        .page-indicator {
            padding: 8px 16px;
            background: white;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            font-weight: 600;
            font-size: 13px;
            color: var(--text-muted);
        }

        /* =====================================================
           QUICK ACTIONS
        ===================================================== */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
        }
        .quick-actions .quick-btn {
            padding: 14px 18px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border-color);
            background: white;
            cursor: pointer;
            transition: var(--transition);
            font-weight: 600;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: var(--text-main);
        }
        .quick-actions .quick-btn:hover {
            background: #f8fafc;
            border-color: var(--accent-blue);
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }
        .quick-actions .quick-btn i {
            width: 28px;
            height: 28px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
        }

        /* =====================================================
           CHARTS
        ===================================================== */
        .chart-container { position: relative; height: 250px; }

        /* =====================================================
           IFRAME PANEL
        ===================================================== */
        .iframe-panel {
            background: white;
            border-radius: var(--radius);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }
        .iframe-header {
            background: #f8fafc;
            padding: 14px 22px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
        }
        .iframe-header h2 { font-size: 15px; color: var(--accent-blue); font-weight: 700; }
        .iframe-content { width: 100%; height: calc(100vh - 220px); border: none; }

        /* =====================================================
           EMPTY STATE
        ===================================================== */
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

        /* =====================================================
           MODAL
        ===================================================== */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            z-index: 9999;
            display: none;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .modal-overlay.active { display: flex; }
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: var(--radius);
            max-width: 500px;
            width: 100%;
            box-shadow: var(--shadow-lg);
            animation: modalIn 0.25s ease;
        }
        @keyframes modalIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
        .modal-content h3 {
            margin-bottom: 18px;
            color: var(--error);
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* =====================================================
           RESPONSIVE
        ===================================================== */
        @media (max-width: 1024px) {
            .grid-2, .grid-3 { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            body { flex-direction: column; }
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
                padding: 18px;
            }
            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 18px;
            }
            header.page-header { flex-direction: column; align-items: flex-start; }
            .filter-bar { flex-direction: column; align-items: stretch; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .form-row { grid-template-columns: 1fr; }
        }
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
            .panel { padding: 18px; }
            .header-actions { width: 100%; }
            .header-actions .btn { flex: 1; justify-content: center; }
        }
    </style>
</head>
<body>

    <!-- ============================================================
         SIDEBAR
    ============================================================ -->
    <aside class="sidebar">
        <div class="logo">🎓 UniVerse</div>

        <div class="user-profile">
            <?php $img_name = (!empty($user['photo']) && file_exists("uploads/" . $user['photo'])) ? $user['photo'] : 'default.png'; ?>
            <img src="uploads/<?= htmlspecialchars($img_name); ?>" alt="Photo" class="profile-img">
            <p class="name"><?= htmlspecialchars(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? '')); ?></p>
            <p class="title">Agent Comptable</p>
            <span class="badge-role"><?= htmlspecialchars($user['role'] ?? 'COMPTABLE'); ?></span>
        </div>

        <ul class="nav-links">
            <li><a href="?page=dashboard" class="<?= ($page == 'dashboard' && $crud == '') ? 'active' : ''; ?>"><i class="fas fa-chart-line"></i> Tableau de bord</a></li>
            <li><a href="?crud=paiement_liste" class="<?= ($crud == 'paiement_liste') ? 'active' : ''; ?>"><i class="fas fa-list"></i> Liste des paiements</a></li>
            <li><a href="?crud=paiement_add" class="<?= ($crud == 'paiement_add') ? 'active' : ''; ?>"><i class="fas fa-plus-circle"></i> Ajouter un paiement</a></li>
            <li><a href="?page=paiements" class="<?= ($page == 'paiements') ? 'active' : ''; ?>"><i class="fas fa-credit-card"></i> Paiements <span class="nav-badge"><?= $paiementsEnAttente; ?></span></a></li>
            <li><a href="?page=bordereaux" class="<?= ($page == 'bordereaux') ? 'active' : ''; ?>"><i class="fas fa-file-invoice"></i> Bordereaux</a></li>
            <li><a href="?page=debiteurs" class="<?= ($page == 'debiteurs') ? 'active' : ''; ?>"><i class="fas fa-exclamation-triangle"></i> Débiteurs</a></li>
            <li><a href="?page=etudiant" class="<?= ($page == 'etudiant') ? 'active' : ''; ?>"><i class="fas fa-search"></i> Recherche étudiant</a></li>
            <li><a href="?page=rapports" class="<?= ($page == 'rapports') ? 'active' : ''; ?>"><i class="fas fa-file-alt"></i> Rapports</a></li>
            <li><a href="mon_profil.php"><i class="fas fa-user"></i> Mon Profil</a></li>
            <li><a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
        </ul>
    </aside>

    <!-- ============================================================
         MAIN CONTENT
    ============================================================ -->
    <main class="main-content">

        <header class="page-header">
            <h1><i class="fas fa-wallet"></i> Centre de Gestion Financière</h1>
            <div class="header-actions">
                <button class="theme-toggle-btn" onclick="toggleTheme()" title="Mode sombre">🌙</button>
                <a href="?crud=paiement_add" class="btn btn-primary"><i class="fas fa-plus"></i> Nouveau paiement</a>
                <a href="export_excel_universel.php?table=paiements&filename=Liste_Paiements" class="btn btn-success"><i class="fas fa-file-excel"></i> Export Excel</a>
            </div>
        </header>

        <?= $message; ?>

        <!-- ============================================================
             VUE IFRAME (CRUD EXTERNES)
        ============================================================ -->
        <?php if ($url_iframe !== ''): ?>
            <div class="iframe-panel">
                <div class="iframe-header">
                    <h2>📄 <?= htmlspecialchars($titre_iframe) ?></h2>
                    <a href="dashboard_comptable.php?page=dashboard" class="btn btn-outline" style="font-size:12px; padding:6px 14px;">
                        <i class="fas fa-arrow-left"></i> Retour
                    </a>
                </div>
                <iframe src="<?= htmlspecialchars($url_iframe) ?>" class="iframe-content"></iframe>
            </div>

        <?php else: ?>

        <!-- ============================================================
             PAGE : DASHBOARD
        ============================================================ -->
        <?php if ($page == 'dashboard'): ?>

        <!-- Stat cards -->
        <div class="stats-grid">
            <div class="stat-card green">
                <div class="stat-header">
                    <span class="stat-label">Étudiants</span>
                    <span class="stat-icon"><i class="fas fa-user-graduate"></i></span>
                </div>
                <div class="stat-value"><?= number_format($totalEtudiants); ?></div>
                <div class="stat-change neutral"><i class="fas fa-users"></i> Inscrits</div>
            </div>

            <div class="stat-card blue">
                <div class="stat-header">
                    <span class="stat-label">Recettes du jour</span>
                    <span class="stat-icon"><i class="fas fa-calendar-day"></i></span>
                </div>
                <div class="stat-value"><?= number_format($recettesJour, 0, ',', ' '); ?> BIF</div>
                <div class="stat-change <?= $pctJour >= 0 ? 'up' : 'down'; ?>">
                    <i class="fas fa-arrow-<?= $pctJour >= 0 ? 'up' : 'down'; ?>"></i>
                    <?= ($pctJour >= 0 ? '+' : '') . $pctJour; ?>% vs hier
                </div>
            </div>

            <div class="stat-card orange">
                <div class="stat-header">
                    <span class="stat-label">Recettes du mois</span>
                    <span class="stat-icon"><i class="fas fa-chart-bar"></i></span>
                </div>
                <div class="stat-value"><?= number_format($recettesMois, 0, ',', ' '); ?> BIF</div>
                <div class="stat-change <?= $pctMois >= 0 ? 'up' : 'down'; ?>">
                    <i class="fas fa-arrow-<?= $pctMois >= 0 ? 'up' : 'down'; ?>"></i>
                    <?= ($pctMois >= 0 ? '+' : '') . $pctMois; ?>% vs mois dernier
                </div>
            </div>

            <div class="stat-card purple">
                <div class="stat-header">
                    <span class="stat-label">Recettes annuelles</span>
                    <span class="stat-icon"><i class="fas fa-trophy"></i></span>
                </div>
                <div class="stat-value"><?= number_format($recettesAnnuelles, 0, ',', ' '); ?> BIF</div>
                <div class="stat-change <?= $pctAnnee >= 0 ? 'up' : 'down'; ?>">
                    <i class="fas fa-arrow-<?= $pctAnnee >= 0 ? 'up' : 'down'; ?>"></i>
                    <?= ($pctAnnee >= 0 ? '+' : '') . $pctAnnee; ?>% vs année dernière
                </div>
            </div>

            <div class="stat-card green">
                <div class="stat-header">
                    <span class="stat-label">Solde caisse</span>
                    <span class="stat-icon"><i class="fas fa-money-bill-wave"></i></span>
                </div>
                <div class="stat-value"><?= number_format($soldeCaisse, 0, ',', ' '); ?> BIF</div>
                <div class="stat-change neutral"><i class="fas fa-minus"></i> Total validé</div>
            </div>

            <div class="stat-card red">
                <div class="stat-header">
                    <span class="stat-label">Débiteurs</span>
                    <span class="stat-icon"><i class="fas fa-user-slash"></i></span>
                </div>
                <div class="stat-value"><?= $nbDebiteurs; ?></div>
                <div class="stat-change down"><i class="fas fa-exclamation-circle"></i> À régulariser</div>
            </div>

            <div class="stat-card blue">
                <div class="stat-header">
                    <span class="stat-label">Reçus générés</span>
                    <span class="stat-icon"><i class="fas fa-file-invoice"></i></span>
                </div>
                <div class="stat-value"><?= number_format($nbRecus); ?></div>
                <div class="stat-change neutral"><i class="fas fa-receipt"></i> Documents</div>
            </div>

            <div class="stat-card green">
                <div class="stat-header">
                    <span class="stat-label">Paiements validés</span>
                    <span class="stat-icon"><i class="fas fa-check-circle"></i></span>
                </div>
                <div class="stat-value"><?= number_format($paiementsValides); ?></div>
                <div class="stat-change up"><i class="fas fa-thumbs-up"></i> Confirmés</div>
            </div>

            <div class="stat-card red">
                <div class="stat-header">
                    <span class="stat-label">Paiements rejetés</span>
                    <span class="stat-icon"><i class="fas fa-times-circle"></i></span>
                </div>
                <div class="stat-value"><?= number_format($paiementsAnnules); ?></div>
                <div class="stat-change down"><i class="fas fa-ban"></i> Refusés</div>
            </div>

            <div class="stat-card orange">
                <div class="stat-header">
                    <span class="stat-label">En attente</span>
                    <span class="stat-icon"><i class="fas fa-hourglass-half"></i></span>
                </div>
                <div class="stat-value"><?= $paiementsEnAttente; ?></div>
                <div class="stat-change neutral"><i class="fas fa-clock"></i> À valider</div>
            </div>
        </div>

        <!-- Charts -->
        <div class="grid-2">
            <div class="panel">
                <div class="panel-header">
                    <h2><i class="fas fa-chart-area" style="color:var(--accent-blue);"></i> Évolution mensuelle</h2>
                </div>
                <div class="chart-container"><canvas id="chartMensuel"></canvas></div>
            </div>
            <div class="panel">
                <div class="panel-header">
                    <h2><i class="fas fa-chart-pie" style="color:var(--success);"></i> Paiements par département</h2>
                </div>
                <div class="chart-container"><canvas id="chartDepartement"></canvas></div>
            </div>
        </div>

        <div class="grid-3">
            <div class="panel">
                <div class="panel-header">
                    <h2><i class="fas fa-university" style="color:var(--warning);"></i> Par banque</h2>
                </div>
                <div class="chart-container"><canvas id="chartBanque"></canvas></div>
            </div>
            <div class="panel">
                <div class="panel-header">
                    <h2><i class="fas fa-chart-bar" style="color:var(--error);"></i> Validés vs Rejetés</h2>
                </div>
                <div class="chart-container"><canvas id="chartStatus"></canvas></div>
            </div>
            <div class="panel">
                <div class="panel-header">
                    <h2><i class="fas fa-clock" style="color:var(--warning);"></i> File d'attente</h2>
                </div>
                <div style="max-height:250px; overflow-y:auto;">
                    <?php $attente = $bdd->query("SELECT p.*, u.nom, u.prenom, e.matricule FROM paiements p JOIN etudiants e ON p.id_etudiant = e.id_etudiant JOIN users u ON e.id_user = u.id_user WHERE p.statut = 'en_attente' ORDER BY p.date_paiement ASC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC); ?>
                    <?php if (!empty($attente)): ?>
                        <?php foreach ($attente as $a): ?>
                            <div style="display:flex; justify-content:space-between; align-items:center; padding:10px 4px; border-bottom:1px solid #f1f5f9;">
                                <div>
                                    <div style="font-weight:600; font-size:13px;"><?= htmlspecialchars($a['nom'] . ' ' . $a['prenom']); ?></div>
                                    <div style="font-size:11px; color:#64748b;"><?= htmlspecialchars($a['matricule']); ?> · <?= number_format($a['montant'], 0, ',', ' '); ?> BIF</div>
                                </div>
                                <div style="display:flex; gap:4px;">
                                    <a href="?valider=<?= (int)$a['id_paiement']; ?>&csrf=<?= urlencode($csrf_token); ?>" class="btn-action success" onclick="return confirm('Valider ce paiement ?')"><i class="fas fa-check"></i></a>
                                    <button class="btn-action danger" onclick="ouvrirModalRejet(<?= (int)$a['id_paiement']; ?>)"><i class="fas fa-times"></i></button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-check-circle" style="color:var(--success);"></i>
                            <p>Aucun paiement en attente</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php endif; ?>

        <!-- ============================================================
             PAGE : PAIEMENTS
        ============================================================ -->
        <?php if ($page == 'paiements'): ?>
        <div class="panel">
            <div class="panel-header">
                <h2><i class="fas fa-list" style="color:var(--accent-blue);"></i> Registre des paiements</h2>
                <span style="font-size:13px; color:var(--text-muted);"><?= $total; ?> paiement(s) au total</span>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Étudiant</th>
                            <th>Matricule</th>
                            <th>Département</th>
                            <th>Faculté</th>
                            <th>Tranche</th>
                            <th>Montant</th>
                            <th>Banque</th>
                            <th>Date</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($listePaiements)): ?>
                            <?php foreach ($listePaiements as $p): $statut_class = $p['statut'] ?? 'en_attente'; ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars(($p['nom'] ?? '') . ' ' . ($p['prenom'] ?? '')); ?></strong></td>
                                    <td><?= htmlspecialchars($p['matricule'] ?? 'N/A'); ?></td>
                                    <td><?= htmlspecialchars($p['nom_dept'] ?? 'N/A'); ?></td>
                                    <td><span class="faculte-badge"><?= htmlspecialchars($p['faculte'] ?? 'Non assignée'); ?></span></td>
                                    <td><?= htmlspecialchars($p['tranche'] ?? '—'); ?></td>
                                    <td><strong><?= number_format($p['montant'], 0, ',', ' '); ?> BIF</strong></td>
                                    <td><?= htmlspecialchars($p['banque'] ?? '—'); ?></td>
                                    <td><?= date('d/m/Y', strtotime($p['date_paiement'])); ?></td>
                                    <td>
                                        <span class="status-badge <?= $statut_class; ?>">
                                            <?= $statut_class == 'valide' ? '✅ Validé' : ($statut_class == 'en_attente' ? '⏳ En attente' : '❌ Rejeté'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="display:flex; gap:4px; flex-wrap:wrap;">
                                            <?php if ($statut_class == 'en_attente'): ?>
                                                <a href="?valider=<?= (int)$p['id_paiement']; ?>&csrf=<?= urlencode($csrf_token); ?>" class="btn-action success" onclick="return confirm('Valider ce paiement ?')" title="Valider"><i class="fas fa-check"></i></a>
                                                <button class="btn-action danger" onclick="ouvrirModalRejet(<?= (int)$p['id_paiement']; ?>)" title="Rejeter"><i class="fas fa-times"></i></button>
                                            <?php endif; ?>
                                            <a href="?modifier=<?= (int)$p['id_paiement']; ?>" class="btn-action primary" title="Modifier"><i class="fas fa-edit"></i></a>
                                            <a href="?supprimer=<?= (int)$p['id_paiement']; ?>&csrf=<?= urlencode($csrf_token); ?>" class="btn-action danger" onclick="return confirm('Supprimer ce paiement ?')" title="Supprimer"><i class="fas fa-trash"></i></a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10">
                                    <div class="empty-state">
                                        <i class="fas fa-inbox"></i>
                                        <p>Aucun paiement enregistré.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($nbPages > 1): ?>
                <div class="pagination">
                    <?php if ($page_num > 1): ?>
                        <a href="?<?= buildQuery(['p' => $page_num - 1]); ?>"><i class="fas fa-chevron-left"></i> Précédent</a>
                    <?php endif; ?>
                    <div class="page-indicator">Page <?= $page_num; ?> / <?= $nbPages; ?></div>
                    <?php if ($page_num < $nbPages): ?>
                        <a href="?<?= buildQuery(['p' => $page_num + 1]); ?>">Suivant <i class="fas fa-chevron-right"></i></a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- ============================================================
             PAGE : BORDEREAUX (avec filtre sécurisé)
        ============================================================ -->
        <?php if ($page == 'bordereaux'): ?>
        <?php
        /* Filtre sécurisé (whitelist) */
        $filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
        $filtres_valides = ['all', 'en_attente', 'valide', 'rejete'];
        if (!in_array($filter, $filtres_valides, true)) {
            $filter = 'all';
        }
        ?>
        <div class="panel">
            <div class="panel-header">
                <h2><i class="fas fa-file-invoice"></i> Gestion des bordereaux</h2>
                <div class="panel-actions">
                    <a href="?page=bordereaux&filter=all" class="btn-action <?= $filter === 'all' ? 'primary' : 'warning'; ?>">Tous</a>
                    <a href="?page=bordereaux&filter=en_attente" class="btn-action warning">En attente</a>
                    <a href="?page=bordereaux&filter=valide" class="btn-action success">Validés</a>
                    <a href="?page=bordereaux&filter=rejete" class="btn-action danger">Rejetés</a>
                </div>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Référence</th>
                            <th>Étudiant</th>
                            <th>Matricule</th>
                            <th>Montant</th>
                            <th>Date</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sqlBordereaux = "SELECT p.*, u.nom, u.prenom, e.matricule, d.nom_dept
                                          FROM paiements p
                                          JOIN etudiants e ON p.id_etudiant = e.id_etudiant
                                          JOIN users u ON e.id_user = u.id_user
                                          JOIN classes c ON e.id_classe = c.id_classe
                                          JOIN departements d ON c.id_dept = d.id_dept";
                        $paramsBord = [];
                        if ($filter !== 'all') {
                            $sqlBordereaux .= " WHERE p.statut = ?";
                            $paramsBord[] = $filter;
                        }
                        $sqlBordereaux .= " ORDER BY p.date_paiement DESC LIMIT 50";
                        $stmtBord = $bdd->prepare($sqlBordereaux);
                        $stmtBord->execute($paramsBord);
                        $bordereaux_list = $stmtBord->fetchAll(PDO::FETCH_ASSOC);

                        if (!empty($bordereaux_list)):
                            foreach ($bordereaux_list as $b): ?>
                                <tr>
                                    <td><span style="background:#f1f5f9; padding:3px 10px; border-radius:4px; font-family:monospace; font-size:12px;"><?= htmlspecialchars($b['bordereau'] ?? '—'); ?></span></td>
                                    <td><strong><?= htmlspecialchars($b['nom'] . ' ' . $b['prenom']); ?></strong></td>
                                    <td><?= htmlspecialchars($b['matricule']); ?></td>
                                    <td><strong><?= number_format($b['montant'], 0, ',', ' '); ?> BIF</strong></td>
                                    <td><?= date('d/m/Y', strtotime($b['date_paiement'])); ?></td>
                                    <td>
                                        <span class="status-badge <?= $b['statut']; ?>">
                                            <?= $b['statut'] == 'valide' ? '✅ Validé' : ($b['statut'] == 'en_attente' ? '⏳ En attente' : '❌ Rejeté'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($b['statut'] == 'en_attente'): ?>
                                            <a href="?valider=<?= (int)$b['id_paiement']; ?>&csrf=<?= urlencode($csrf_token); ?>" class="btn-action success" onclick="return confirm('Valider ce bordereau ?')"><i class="fas fa-check"></i></a>
                                            <button class="btn-action danger" onclick="ouvrirModalRejet(<?= (int)$b['id_paiement']; ?>)"><i class="fas fa-times"></i></button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach;
                        else: ?>
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state">
                                        <i class="fas fa-inbox"></i>
                                        <p>Aucun bordereau pour ce filtre.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- ============================================================
             PAGE : DÉBITEURS
        ============================================================ -->
        <?php if ($page == 'debiteurs'): ?>
        <div class="panel">
            <div class="panel-header">
                <h2><i class="fas fa-exclamation-triangle" style="color:var(--error);"></i> Étudiants débiteurs (<?= $nbDebiteurs; ?>)</h2>
                <div class="panel-actions">
                    <a href="export_excel_universel.php?table=paiements&filename=Debiteurs" class="btn-action primary"><i class="fas fa-file-export"></i> Exporter</a>
                </div>
            </div>
            <?php if (!empty($debiteurs)): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Matricule</th>
                                <th>Nom</th>
                                <th>Département</th>
                                <th>Classe</th>
                                <th>Attendu</th>
                                <th>Payé</th>
                                <th>Solde</th>
                                <th>%</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($debiteurs as $d):
                                $solde    = MINERVAL_ANNUEL - $d['total_paye'];
                                $pourcent = MINERVAL_ANNUEL > 0 ? round(($d['total_paye'] / MINERVAL_ANNUEL) * 100, 1) : 0;
                                $retard   = $solde > 100000;
                            ?>
                                <tr style="<?= $retard ? 'border-left: 3px solid var(--error);' : ''; ?>">
                                    <td><code style="font-size:12px;"><?= htmlspecialchars($d['matricule'] ?? 'N/A'); ?></code></td>
                                    <td><strong><?= htmlspecialchars($d['nom'] . ' ' . $d['prenom']); ?></strong></td>
                                    <td><?= htmlspecialchars($d['nom_dept'] ?? 'N/A'); ?></td>
                                    <td><?= htmlspecialchars($d['niveau'] ?? 'N/A'); ?></td>
                                    <td><?= number_format(MINERVAL_ANNUEL, 0, ',', ' '); ?> BIF</td>
                                    <td><?= number_format($d['total_paye'], 0, ',', ' '); ?> BIF</td>
                                    <td style="color:<?= $retard ? 'var(--error)' : 'var(--warning)'; ?>; font-weight:700;">
                                        <?= number_format($solde, 0, ',', ' '); ?> BIF
                                        <?php if ($retard): ?> <span class="status-badge rejete">⚠</span> <?php endif; ?>
                                    </td>
                                    <td><?= $pourcent; ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-check-circle" style="color:var(--success);"></i>
                    <p>Tous les étudiants sont à jour !</p>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- ============================================================
             PAGE : RECHERCHE ÉTUDIANT (avec SQL sécurisé)
        ============================================================ -->
        <?php if ($page == 'etudiant'): ?>
        <div class="panel">
            <div class="panel-header">
                <h2><i class="fas fa-search"></i> Recherche étudiant</h2>
            </div>
            <form method="GET" class="filter-bar">
                <input type="hidden" name="page" value="etudiant">
                <div class="form-group">
                    <label>Recherche</label>
                    <input type="text" name="q" placeholder="Nom, prénom ou matricule..." value="<?= htmlspecialchars($_GET['q'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Faculté</label>
                    <select name="faculte">
                        <option value="">Toutes</option>
                        <?php $facultes = $bdd->query("SELECT DISTINCT faculte FROM departements WHERE faculte IS NOT NULL AND faculte != '' ORDER BY faculte");
                        while ($f = $facultes->fetch()): ?>
                            <option value="<?= htmlspecialchars($f['faculte']); ?>" <?= (isset($_GET['faculte']) && $_GET['faculte'] == $f['faculte']) ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($f['faculte']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Département</label>
                    <select name="departement">
                        <option value="">Tous</option>
                        <?php foreach ($listeDepartements as $d): ?>
                            <option value="<?= (int)$d['id_dept']; ?>" <?= (isset($_GET['departement']) && $_GET['departement'] == $d['id_dept']) ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($d['nom_dept']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Rechercher</button>
                    <a href="?page=etudiant" class="btn btn-outline"><i class="fas fa-times"></i> Réinitialiser</a>
                </div>
            </form>

            <?php if (isset($_GET['q']) && !empty($_GET['q'])):
                /* ✅ SQL sécurisé (requête préparée) */
                $paramsEtu = [];
                $sqlEtu = "SELECT e.id_etudiant, e.matricule, u.nom, u.prenom, u.email, u.telephone,
                                  c.niveau, d.nom_dept, d.faculte,
                                  COALESCE((SELECT SUM(montant) FROM paiements WHERE id_etudiant = e.id_etudiant AND statut = 'valide'), 0) as total_paye
                           FROM etudiants e
                           JOIN users u ON e.id_user = u.id_user
                           JOIN classes c ON e.id_classe = c.id_classe
                           JOIN departements d ON c.id_dept = d.id_dept
                           WHERE (u.nom LIKE ? OR u.prenom LIKE ? OR e.matricule LIKE ?)";

                $q_like = '%' . $_GET['q'] . '%';
                $paramsEtu[] = $q_like;
                $paramsEtu[] = $q_like;
                $paramsEtu[] = $q_like;

                if (!empty($_GET['faculte'])) {
                    $sqlEtu .= " AND d.faculte = ?";
                    $paramsEtu[] = $_GET['faculte'];
                }
                if (!empty($_GET['departement'])) {
                    $sqlEtu .= " AND d.id_dept = ?";
                    $paramsEtu[] = (int)$_GET['departement'];
                }
                $sqlEtu .= " ORDER BY u.nom ASC LIMIT 50";

                $stmtEtu = $bdd->prepare($sqlEtu);
                $stmtEtu->execute($paramsEtu);
                $resultats = $stmtEtu->fetchAll(PDO::FETCH_ASSOC);

                if (!empty($resultats)): ?>
                    <div style="margin-top:18px;">
                        <p style="font-size:13px; color:var(--text-muted); margin-bottom:12px;">
                            <strong style="color:var(--text-main);"><?= count($resultats); ?></strong> résultat(s) pour "<strong><?= htmlspecialchars($_GET['q']); ?></strong>"
                        </p>
                        <?php foreach ($resultats as $etu):
                            $estEnOrdre = $etu['total_paye'] >= MINERVAL_ANNUEL; ?>
                            <div style="background:#f8fafc; border-radius:10px; padding:14px 18px; margin-bottom:10px; border:1px solid var(--border-color);">
                                <div style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:10px; align-items:center;">
                                    <div>
                                        <strong><?= htmlspecialchars($etu['nom'] . ' ' . $etu['prenom']); ?></strong>
                                        <span style="color:var(--text-muted); font-size:12px;"> · <?= htmlspecialchars($etu['matricule']); ?></span>
                                        <div style="margin-top:4px; font-size:12px; color:var(--text-muted);">
                                            <?= htmlspecialchars($etu['nom_dept']); ?> · <?= htmlspecialchars($etu['niveau']); ?>
                                        </div>
                                    </div>
                                    <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                                        <span class="faculte-badge"><?= htmlspecialchars($etu['faculte'] ?? 'Non assignée'); ?></span>
                                        <span class="status-badge <?= $estEnOrdre ? 'valide' : 'rejete'; ?>">
                                            <?= $estEnOrdre ? '✅ En ordre' : '⚠ Non en ordre'; ?>
                                        </span>
                                        <span style="font-size:12px; font-weight:600;"><?= number_format($etu['total_paye'], 0, ',', ' '); ?> BIF</span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-search"></i>
                        <p>Aucun étudiant trouvé pour ces critères.</p>
                    </div>
                <?php endif;
            endif; ?>
        </div>
        <?php endif; ?>

        <!-- ============================================================
             PAGE : RAPPORTS
        ============================================================ -->
        <?php if ($page == 'rapports'): ?>
        <div class="panel">
            <div class="panel-header">
                <h2><i class="fas fa-file-alt"></i> Rapports financiers</h2>
            </div>
            <div class="quick-actions">
                <a href="export_excel_universel.php?table=paiements&filename=Rapport_Paiements" class="quick-btn">
                    <i class="fas fa-file-excel" style="background:#dcfce7; color:#166534;"></i> Rapport Excel
                </a>
                <a href="rapports_personnalises.php?type=paiements" class="quick-btn">
                    <i class="fas fa-chart-bar" style="background:#dbeafe; color:#1e40af;"></i> Rapports personnalisés
                </a>
                <a href="export_excel_universel.php?table=notes&filename=Rapport_Notes" class="quick-btn">
                    <i class="fas fa-file-pdf" style="background:#fee2e2; color:#991b1b;"></i> Rapport Notes
                </a>
                <a href="export_excel_universel.php?table=etudiants&filename=Rapport_Etudiants" class="quick-btn">
                    <i class="fas fa-calendar-day" style="background:#fef3c7; color:#92400e;"></i> Rapport Étudiants
                </a>
                <a href="export_excel_universel.php?table=inscriptions&filename=Rapport_Inscriptions" class="quick-btn">
                    <i class="fas fa-calendar-alt" style="background:#e0e7ff; color:#4338ca;"></i> Rapport Inscriptions
                </a>
                <a href="export_excel_universel.php?table=professeurs&filename=Rapport_Professeurs" class="quick-btn">
                    <i class="fas fa-calendar-year" style="background:#fce7f3; color:#9d174d;"></i> Rapport Professeurs
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- ============================================================
             FORMULAIRE AJOUT / MODIFICATION (uniquement sur dashboard)
        ============================================================ -->
        <?php if ($page == 'dashboard' && $crud === ''): ?>
        <div class="panel" id="form-paiement">
            <div class="panel-header">
                <h2>
                    <i class="fas <?= $paiement_a_modifier ? 'fa-edit' : 'fa-plus-circle'; ?>" style="color:var(--success);"></i>
                    <?= $paiement_a_modifier ? 'Modifier le paiement #' . (int)$paiement_a_modifier['id_paiement'] : 'Nouveau paiement'; ?>
                </h2>
            </div>

            <?php if ($paiement_a_modifier): ?>
                <form method="POST" action="dashboard_comptable.php">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                    <input type="hidden" name="id_paiement" value="<?= (int)$paiement_a_modifier['id_paiement']; ?>">
                    <div class="alert warning">
                        <i class="fas fa-info-circle"></i>
                        Après modification, le paiement sera remis en attente de validation.
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Tranche <span class="required">*</span></label>
                            <input type="text" name="tranche" value="<?= htmlspecialchars($paiement_a_modifier['tranche'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Mode de paiement</label>
                            <select name="mode_paiement">
                                <option value="Especes" <?= ($paiement_a_modifier['mode_paiement'] ?? '') == 'Especes' ? 'selected' : ''; ?>>Espèces</option>
                                <option value="Banque" <?= ($paiement_a_modifier['mode_paiement'] ?? '') == 'Banque' ? 'selected' : ''; ?>>Banque</option>
                                <option value="Mobile Money" <?= ($paiement_a_modifier['mode_paiement'] ?? '') == 'Mobile Money' ? 'selected' : ''; ?>>Mobile Money</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Banque</label>
                            <input type="text" name="banque" value="<?= htmlspecialchars($paiement_a_modifier['banque'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Bordereau</label>
                            <input type="text" name="bordereau" value="<?= htmlspecialchars($paiement_a_modifier['bordereau'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Montant (BIF) <span class="required">*</span></label>
                            <input type="number" name="montant" value="<?= htmlspecialchars($paiement_a_modifier['montant']); ?>" step="0.01" required>
                        </div>
                        <div class="form-group">
                            <label>Date <span class="required">*</span></label>
                            <input type="date" name="date_paiement" value="<?= htmlspecialchars($paiement_a_modifier['date_paiement']); ?>" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Motif (pénalité)</label>
                        <input type="text" name="motif" value="<?= htmlspecialchars($paiement_a_modifier['motif'] ?? ''); ?>">
                    </div>
                    <div style="display:flex; gap:12px; margin-top:12px; flex-wrap:wrap;">
                        <button type="submit" name="action_modifier" class="btn btn-primary"><i class="fas fa-save"></i> Mettre à jour</button>
                        <a href="dashboard_comptable.php" class="btn btn-secondary"><i class="fas fa-times"></i> Annuler</a>
                    </div>
                </form>
            <?php else: ?>
                <form method="POST" action="dashboard_comptable.php">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                    <div class="alert info">
                        <i class="fas fa-info-circle"></i>
                        Le bordereau sera en attente de validation après enregistrement.
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Département</label>
                            <select id="select_dept">
                                <option value="">-- Tous --</option>
                                <?php foreach ($listeDepartements as $dept): ?>
                                    <option value="<?= (int)$dept['id_dept']; ?>"><?= htmlspecialchars($dept['nom_dept']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Classe</label>
                            <select id="select_classe">
                                <option value="">-- Toutes --</option>
                                <?php foreach ($listeClasses as $cl): ?>
                                    <option value="<?= (int)$cl['id_classe']; ?>"><?= htmlspecialchars($cl['niveau']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Étudiant <span class="required">*</span></label>
                        <select name="id_etudiant" id="select_etudiant" required>
                            <option value="">-- Choisir --</option>
                            <?php foreach ($listeEtudiants as $etud): ?>
                                <option value="<?= (int)$etud['id_etudiant']; ?>"
                                        data-dept="<?= (int)$etud['id_dept']; ?>"
                                        data-classe="<?= (int)$etud['id_classe']; ?>">
                                    <?= htmlspecialchars($etud['nom'] . ' ' . $etud['prenom'] . ' · ' . $etud['matricule']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Tranche <span class="required">*</span></label>
                            <input type="text" name="tranche" placeholder="Ex: Inscription, Tranche 1..." required>
                        </div>
                        <div class="form-group">
                            <label>Année académique</label>
                            <select name="annee_academique">
                                <option value="<?= date('Y') . '-' . (date('Y') + 1); ?>" selected><?= date('Y') . '-' . (date('Y') + 1); ?></option>
                                <option value="<?= (date('Y') - 1) . '-' . date('Y'); ?>"><?= (date('Y') - 1) . '-' . date('Y'); ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Mode de paiement</label>
                            <select name="mode_paiement">
                                <option value="Especes">Espèces</option>
                                <option value="Banque">Banque</option>
                                <option value="Mobile Money">Mobile Money</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Banque</label>
                            <input type="text" name="banque" placeholder="Ex: BCB, CRDB...">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Bordereau</label>
                            <input type="text" name="bordereau" placeholder="Ex: FT-89241">
                        </div>
                        <div class="form-group">
                            <label>Montant (BIF) <span class="required">*</span></label>
                            <input type="number" name="montant" placeholder="Ex: 450000" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Date <span class="required">*</span></label>
                            <input type="date" name="date_paiement" value="<?= date('Y-m-d'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Motif (pénalité)</label>
                            <input type="text" name="motif" placeholder="Si pénalité...">
                        </div>
                    </div>
                    <button type="submit" name="action_ajouter" class="btn btn-primary" style="margin-top:6px;">
                        <i class="fas fa-save"></i> Enregistrer le paiement
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <!-- ============================================================
             ACTIVITÉS RÉCENTES
        ============================================================ -->
        <div class="panel">
            <div class="panel-header">
                <h2><i class="fas fa-history" style="color:var(--accent-blue);"></i> Activités récentes</h2>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Utilisateur</th>
                            <th>Action</th>
                            <th>Date</th>
                            <th>Heure</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $historique = $bdd->query("
                            SELECT h.*, u.nom, u.prenom
                            FROM historique_paiements h
                            JOIN users u ON h.id_etudiant = u.id_user
                            ORDER BY h.date_action DESC
                            LIMIT 10
                        ");
                        $nbHisto = 0;
                        while ($h = $historique->fetch()):
                            $nbHisto++;
                            $action_label = '';
                            switch ($h['action']) {
                                case 'creation': $action_label = '🆕 a enregistré un paiement'; break;
                                case 'validation': $action_label = '✅ a validé un paiement'; break;
                                case 'rejet': $action_label = '⛔ a rejeté un paiement'; break;
                                case 'modification_retour_attente': $action_label = '🔄 a remis un paiement en attente'; break;
                                default: $action_label = htmlspecialchars($h['action']);
                            }
                        ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($h['nom'] . ' ' . $h['prenom']); ?></strong></td>
                                <td><?= $action_label; ?></td>
                                <td><?= date('d/m/Y', strtotime($h['date_action'])); ?></td>
                                <td><?= date('H:i', strtotime($h['date_action'])); ?></td>
                            </tr>
                        <?php endwhile; ?>
                        <?php if ($nbHisto === 0): ?>
                            <tr>
                                <td colspan="4">
                                    <div class="empty-state">
                                        <i class="fas fa-history"></i>
                                        <p>Aucune activité enregistrée.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ============================================================
             ACTIONS RAPIDES
        ============================================================ -->
        <div class="panel">
            <div class="panel-header">
                <h2><i class="fas fa-bolt" style="color:var(--warning);"></i> Actions rapides</h2>
            </div>
            <div class="quick-actions">
                <a href="?crud=paiement_add" class="quick-btn">
                    <i class="fas fa-plus-circle" style="background:#dcfce7; color:#166534;"></i> Nouveau paiement
                </a>
                <a href="?crud=paiement_liste" class="quick-btn">
                    <i class="fas fa-list" style="background:#dbeafe; color:#1e40af;"></i> Liste des paiements
                </a>
                <a href="?page=bordereaux&filter=en_attente" class="quick-btn">
                    <i class="fas fa-file-invoice" style="background:#fef3c7; color:#92400e;"></i> Bordereaux en attente
                </a>
                <a href="export_excel_universel.php?table=paiements&filename=Liste_Paiements" class="quick-btn">
                    <i class="fas fa-file-excel" style="background:#dcfce7; color:#166534;"></i> Export Excel
                </a>
                <a href="?page=etudiant" class="quick-btn">
                    <i class="fas fa-search" style="background:#dbeafe; color:#1e40af;"></i> Rechercher étudiant
                </a>
                <a href="?page=debiteurs" class="quick-btn">
                    <i class="fas fa-exclamation-triangle" style="background:#fee2e2; color:#991b1b;"></i> Débiteurs
                </a>
            </div>
        </div>
        <?php endif; /* end dashboard */ ?>

        <!-- Footer -->
        <div style="text-align:center; padding:20px 0 10px; color:var(--text-light); font-size:12px; border-top:1px solid var(--border-color);">
            <p>© <?= date('Y'); ?> UniVerse — Services Financiers</p>
        </div>

        <?php endif; /* end else ($url_iframe === '') */ ?>
    </main>

    <!-- ============================================================
         MODAL REJET
    ============================================================ -->
    <div class="modal-overlay" id="modalRejet">
        <div class="modal-content">
            <h3><i class="fas fa-times-circle"></i> Rejeter le bordereau</h3>
            <form method="POST" action="dashboard_comptable.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="id_paiement" id="rejet_id_paiement">
                <div class="form-group">
                    <label>Motif du rejet <span class="required">*</span></label>
                    <select name="motif_rejet" required>
                        <option value="">-- Choisir un motif --</option>
                        <option value="Bordereau illisible">Bordereau illisible</option>
                        <option value="Montant incorrect">Montant incorrect</option>
                        <option value="Numéro de bordereau incorrect">Numéro de bordereau incorrect</option>
                        <option value="Bordereau déjà utilisé">Bordereau déjà utilisé</option>
                        <option value="Informations incorrectes">Informations incorrectes</option>
                        <option value="Document invalide">Document invalide</option>
                        <option value="Autre">Autre</option>
                    </select>
                </div>
                <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:16px;">
                    <button type="button" class="btn btn-secondary" onclick="fermerModalRejet()">Annuler</button>
                    <button type="submit" name="rejeter_bordereau" class="btn btn-danger"><i class="fas fa-times"></i> Confirmer le rejet</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ============================================================
         SCRIPTS
    ============================================================ -->
    <script>
    /* ---------- Filtre dynamique étudiants ---------- */
    function filtrerEtudiants() {
        const selectDept    = document.getElementById('select_dept');
        const selectClasse  = document.getElementById('select_classe');
        const selectEtudiant = document.getElementById('select_etudiant');
        if (!selectDept || !selectClasse || !selectEtudiant) return;

        const deptFiltre   = selectDept.value;
        const classeFiltre = selectClasse.value;
        const options = Array.from(selectEtudiant.querySelectorAll('option'));

        selectEtudiant.innerHTML = '<option value="">-- Choisir --</option>';
        options.forEach(option => {
            if (!option.value) return;
            const matchDept   = !deptFiltre   || option.getAttribute('data-dept')   === deptFiltre;
            const matchClasse = !classeFiltre || option.getAttribute('data-classe') === classeFiltre;
            if (matchDept && matchClasse) selectEtudiant.appendChild(option);
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        filtrerEtudiants();
        document.getElementById('select_dept')?.addEventListener('change', filtrerEtudiants);
        document.getElementById('select_classe')?.addEventListener('change', filtrerEtudiants);

        /* Auto-scroll vers le formulaire si en mode modification */
        <?php if ($paiement_a_modifier): ?>
            const form = document.getElementById('form-paiement');
            if (form) form.scrollIntoView({ behavior: 'smooth', block: 'start' });
        <?php endif; ?>
    });

    /* ---------- Modal rejet ---------- */
    function ouvrirModalRejet(id) {
        const input = document.getElementById('rejet_id_paiement');
        if (input) input.value = id;
        const modal = document.getElementById('modalRejet');
        if (modal) modal.classList.add('active');
    }
    function fermerModalRejet() {
        const modal = document.getElementById('modalRejet');
        if (modal) modal.classList.remove('active');
    }
    document.getElementById('modalRejet')?.addEventListener('click', function(e) {
        if (e.target === this) fermerModalRejet();
    });

    /* ---------- Graphiques (dashboard uniquement) ---------- */
    <?php if ($page == 'dashboard' && $crud === ''): ?>
    document.addEventListener('DOMContentLoaded', function() {
        const labelsMois = <?= json_encode($mois_labels); ?>;
        const dataMois   = <?= json_encode($mois_montants); ?>;

        if (labelsMois.length > 0) {
            new Chart(document.getElementById('chartMensuel').getContext('2d'), {
                type: 'bar',
                data: {
                    labels: labelsMois,
                    datasets: [{
                        label: 'Recettes (BIF)',
                        data: dataMois,
                        backgroundColor: 'rgba(37, 99, 235, 0.7)',
                        borderColor: '#2563eb',
                        borderWidth: 2,
                        borderRadius: 6
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, ticks: { callback: v => v.toLocaleString() + ' BIF' } },
                        x: { grid: { display: false } }
                    }
                }
            });
        } else {
            document.getElementById('chartMensuel').parentElement.innerHTML = '<div class="empty-state"><i class="fas fa-chart-area"></i><p>Aucune donnée disponible</p></div>';
        }

        const deptLabels = <?= json_encode($dept_labels); ?>;
        const deptData   = <?= json_encode($dept_data); ?>;
        if (deptLabels.length > 0) {
            new Chart(document.getElementById('chartDepartement').getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: deptLabels,
                    datasets: [{ data: deptData, backgroundColor: ['#2563eb', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#14b8a6'] }]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, padding: 10, font: { size: 11 } } } } }
            });
        } else {
            document.getElementById('chartDepartement').parentElement.innerHTML = '<div class="empty-state"><i class="fas fa-chart-pie"></i><p>Aucune donnée disponible</p></div>';
        }

        const banqueLabels = <?= json_encode($banque_labels); ?>;
        const banqueData   = <?= json_encode($banque_data); ?>;
        if (banqueLabels.length > 0) {
            new Chart(document.getElementById('chartBanque').getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: banqueLabels,
                    datasets: [{ data: banqueData, backgroundColor: ['#10b981', '#2563eb', '#f59e0b', '#8b5cf6', '#ec4899', '#14b8a6'] }]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, padding: 10, font: { size: 11 } } } } }
            });
        } else {
            document.getElementById('chartBanque').parentElement.innerHTML = '<div class="empty-state"><i class="fas fa-university"></i><p>Aucune banque enregistrée</p></div>';
        }

        new Chart(document.getElementById('chartStatus').getContext('2d'), {
            type: 'bar',
            data: {
                labels: ['Validés', 'Rejetés', 'En attente'],
                datasets: [{
                    data: [<?= $paiementsValides; ?>, <?= $paiementsAnnules; ?>, <?= $paiementsEnAttente; ?>],
                    backgroundColor: ['rgba(16,185,129,0.75)', 'rgba(239,68,68,0.75)', 'rgba(245,158,11,0.75)'],
                    borderColor: ['#10b981', '#ef4444', '#f59e0b'],
                    borderWidth: 2,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } }, x: { grid: { display: false } } }
            }
        });
    });
    <?php endif; ?>
    </script>

</body>
</html>