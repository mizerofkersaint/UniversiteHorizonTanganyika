<?php
require_once __DIR__ . '/db.php';
// ============================================================
// DASHBOARD ÉTUDIANT - UniVerse
// Version complète : Formation, Progression, Résultats, Bulletin PDF
// ============================================================
require_once 'auth.php';
requireRole('Etudiant');

try {
    $bdd = getDBConnection();
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

$id_user = $USER_ID;

$reqEtudiant = $bdd->prepare("
    SELECT e.id_etudiant, e.matricule, e.titre, e.annee_academique_entree,
           u.nom, u.prenom, u.sexe, u.telephone, u.email, u.email_auth, u.photo,
           c.niveau, c.id_classe,
           d.nom_dept, d.faculte
    FROM etudiants e
    INNER JOIN users u ON e.id_user = u.id_user
    LEFT JOIN classes c ON e.id_classe = c.id_classe
    LEFT JOIN departements d ON c.id_dept = d.id_dept
    WHERE e.id_user = ?
");
$reqEtudiant->execute([$id_user]);
$etudiant = $reqEtudiant->fetch(PDO::FETCH_ASSOC);

if (!$etudiant) {
    die("Profil étudiant introuvable. Contactez l'administration.");
}

$id_etudiant = $etudiant['id_etudiant'];
$id_classe = $etudiant['id_classe'];
$annee_courante = $etudiant['annee_academique_entree'] ?? date('Y') . '-' . (date('Y') + 1);
$photo_profil = (!empty($etudiant['photo']) && file_exists("uploads/" . $etudiant['photo'])) ? $etudiant['photo'] : 'default.png';

// ============================================================
// 3. PAGES ET FILTRES
// ============================================================
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
$message = "";
$message_type = "";

// Filtres pour les résultats
$annee_filtre = isset($_GET['annee']) ? trim($_GET['annee']) : $annee_courante;
$semestre_filtre = isset($_GET['semestre']) ? trim($_GET['semestre']) : '';
$session_filtre = isset($_GET['session']) ? trim($_GET['session']) : '';

// ============================================================
// 4. RÉCUPÉRATION DES DONNÉES
// ============================================================

// --- NOTES OFFICIELLES (validées ET publiées) ---
$sqlNotes = "SELECT n.note, n.session, n.date_saisie, n.date_validation, n.date_publication,
                    co.nom_cours, co.credit, co.semestre, co.id_cours,
                    CONCAT(u.nom, ' ', u.prenom) AS enseignant
             FROM notes n
             JOIN cours co ON n.id_cours = co.id_cours
             LEFT JOIN professeurs p ON co.id_prof = p.id_prof
             LEFT JOIN users u ON p.id_user = u.id_user
             WHERE n.id_etudiant = ? AND n.statut = 'valide' AND n.date_publication IS NOT NULL";

$paramsNotes = [$id_etudiant];
if (!empty($semestre_filtre)) {
    $sqlNotes .= " AND co.semestre = ?";
    $paramsNotes[] = $semestre_filtre;
}
if (!empty($session_filtre)) {
    $sqlNotes .= " AND n.session = ?";
    $paramsNotes[] = $session_filtre;
}
$sqlNotes .= " ORDER BY co.semestre, co.nom_cours";

$stmtNotes = $bdd->prepare($sqlNotes);
$stmtNotes->execute($paramsNotes);
$notes = $stmtNotes->fetchAll(PDO::FETCH_ASSOC);

// --- STATISTIQUES ---
$totalNotes = count($notes);
$moyenneGlobale = 0;
$creditsObtenus = 0;
$notesValidees = 0;
$creditsTotal = 0;

foreach ($notes as $n) {
    if ($n['note'] >= 10) {
        $notesValidees++;
        $creditsObtenus += intval($n['credit'] ?? 0);
    }
    if (!empty($n['credit'])) {
        $creditsTotal += intval($n['credit']);
    }
}
if ($totalNotes > 0) {
    $sommeNotes = array_sum(array_column($notes, 'note'));
    $moyenneGlobale = round($sommeNotes / $totalNotes, 2);
}
$tauxReussite = $totalNotes > 0 ? round(($notesValidees / $totalNotes) * 100, 1) : 0;
$creditsRestants = $creditsTotal - $creditsObtenus;
$progression = $creditsTotal > 0 ? round(($creditsObtenus / $creditsTotal) * 100, 1) : 0;

// --- STATUT DE PUBLICATION (minerval + pénalités) ---
$minervalPaye = $bdd->prepare("SELECT COALESCE(SUM(montant), 0) FROM paiements WHERE id_etudiant = ? AND statut = 'valide'");
$minervalPaye->execute([$id_etudiant]);
$totalPaye = $minervalPaye->fetchColumn();
$minervalAttendu = 500000;
$minervalOk = ($totalPaye >= $minervalAttendu);

$penalitesOk = true;

$publicationOk = ($minervalOk && $penalitesOk);
$statutPublication = $publicationOk ? 'Autorisée' : 'Bloquée';
$statutPublicationClass = $publicationOk ? 'success' : 'danger';

// --- COURS DE LA CLASSE ---
$coursClasse = $bdd->prepare("
    SELECT c.id_cours, c.nom_cours, c.credit, c.semestre,
           CONCAT(u.nom, ' ', u.prenom) AS enseignant
    FROM cours c
    INNER JOIN classes cl ON c.id_classe = cl.id_classe
    LEFT JOIN professeurs p ON c.id_prof = p.id_prof
    LEFT JOIN users u ON p.id_user = u.id_user
    WHERE c.id_classe = ?
    ORDER BY c.semestre, c.nom_cours
");
$coursClasse->execute([$id_classe]);
$coursClasseList = $coursClasse->fetchAll(PDO::FETCH_ASSOC);

$coursAvecNotes = array_column($notes, 'id_cours');

// --- HORAIRES ---
$horaires = $bdd->prepare("
    SELECT h.jour, h.heure_debut, h.heure_fin, h.salle, h.type_cours,
           c.nom_cours,
           CONCAT(u.nom, ' ', u.prenom) AS enseignant
    FROM horaires h
    INNER JOIN cours c ON h.id_cours = c.id_cours
    LEFT JOIN professeurs pr ON h.id_prof = pr.id_prof
    LEFT JOIN users u ON pr.id_user = u.id_user
    WHERE h.id_classe = ?
    ORDER BY FIELD(h.jour, 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'), h.heure_debut ASC
");
$horaires->execute([$id_classe]);
$horairesList = $horaires->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les examens programmés pour la classe de l'étudiant
$examensEtudiant = $bdd->prepare("
    SELECT e.*, c.nom_cours
    FROM examens e
    JOIN cours c ON e.id_cours = c.id_cours
    WHERE e.id_classe = ? AND e.statut = 'valide'
    ORDER BY e.date_prevue DESC, e.heure_debut ASC
");
$examensEtudiant->execute([$id_classe]);
$examensEtudiantList = $examensEtudiant->fetchAll(PDO::FETCH_ASSOC);

// --- PAIEMENTS ---
$paiements = $bdd->prepare("
    SELECT montant, tranche, date_paiement, banque, bordereau, motif, statut
    FROM paiements
    WHERE id_etudiant = ?
    ORDER BY date_paiement DESC
");
$paiements->execute([$id_etudiant]);
$paiementsList = $paiements->fetchAll(PDO::FETCH_ASSOC);

// --- ÉTUDIANTS DE LA CLASSE ---
$classeEtudiants = $bdd->prepare("
    SELECT u.nom, u.prenom, e.matricule
    FROM etudiants e
    INNER JOIN users u ON e.id_user = u.id_user
    WHERE e.id_classe = ? AND e.id_etudiant != ?
    ORDER BY u.nom ASC
");
$classeEtudiants->execute([$id_classe, $id_etudiant]);
$classeEtudiantsList = $classeEtudiants->fetchAll(PDO::FETCH_ASSOC);

// --- NOTIFICATIONS ---
$notifications = $bdd->prepare("
    SELECT * FROM notifications
    WHERE source_table IN ('etudiant', 'professeur', 'examen', 'notes', '')
    ORDER BY date_creation DESC
    LIMIT 20
");
$notifications->execute();
$notificationsList = $notifications->fetchAll(PDO::FETCH_ASSOC);

$nbNotifsNonLues = $bdd->prepare("SELECT COUNT(*) FROM notifications WHERE statut = 'non_lu' AND source_table IN ('etudiant', 'professeur', 'examen', 'notes', '')");
$nbNotifsNonLues->execute();
$nbNotifsNonLues = $nbNotifsNonLues->fetchColumn();

// --- ANNONCES (pour la classe et le département) ---
$annonces = $bdd->prepare("
    SELECT a.*, c.nom_cours, cl.niveau
    FROM annonces_professeurs a
    LEFT JOIN cours c ON a.id_cours = c.id_cours
    LEFT JOIN classes cl ON a.id_classe = cl.id_classe
    WHERE a.id_classe = ? OR a.id_classe IS NULL
    ORDER BY a.date_publication DESC
");
$annonces->execute([$id_classe]);
$annoncesList = $annonces->fetchAll(PDO::FETCH_ASSOC);

// --- RÉSULTAT ANNUEL (validation par le Doyen) ---
$resultatAnnuel = $bdd->prepare("
    SELECT moyenne, credits_obtenus, credits_requis, decision, statut, date_validation, valide_par
    FROM resultats_annuels
    WHERE id_etudiant = ? AND annee_academique = ?
    ORDER BY date_validation DESC
    LIMIT 1
");
$resultatAnnuel->execute([$id_etudiant, $annee_courante]);
$resultatAnnuelData = $resultatAnnuel->fetch(PDO::FETCH_ASSOC);

// Si le résultat annuel n'existe pas encore, on simule (en attente)
if (!$resultatAnnuelData) {
    $resultatAnnuelData = [
        'moyenne' => $moyenneGlobale,
        'credits_obtenus' => $creditsObtenus,
        'credits_requis' => $creditsTotal,
        'decision' => $moyenneGlobale >= 10 ? 'ADMIS' : 'AJOURNÉ',
        'statut' => 'en_attente',
        'date_validation' => null,
        'valide_par' => null
    ];
}

// ============================================================
// 5. DEBUT DE LA PAGE HTML
// ============================================================
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portail Étudiant — UniVerse</title>
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
            border-right: 1px solid rgba(255,255,255,0.04);
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
            width: 66px; height: 66px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 10px;
            border: 3px solid var(--accent-blue);
            box-shadow: 0 4px 14px rgba(37,99,235,0.35);
        }
        .user-profile .name { font-weight: 600; font-size: 14px; color: var(--text-white); }
        .user-profile .matricule { font-size: 11px; color: var(--text-light); margin-top: 2px; }
        .badge-role {
            display: inline-block;
            padding: 4px 12px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            border-radius: 20px;
            margin-top: 8px;
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-purple));
            color: white;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 12px rgba(37,99,235,0.3);
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
            background: linear-gradient(135deg, rgba(37,99,235,0.2), rgba(139,92,246,0.12));
            color: var(--text-white);
            box-shadow: inset 3px 0 0 var(--accent-blue);
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
            border-bottom: 1px solid var(--border-color);
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
        header.page-header h1 i { color: var(--accent-blue); font-size: 22px; }
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
        .btn-outline:hover { background: var(--bg-primary); border-color: var(--accent-blue); color: var(--accent-blue); }

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
        .stat-card .stat-sub {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 4px;
            font-weight: 500;
        }

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
        .status-badge.soumis,
        .status-badge.en_attente { background: #fef3c7; color: #92400e; }
        .status-badge.rejete     { background: #fee2e2; color: #991b1b; }
        .status-badge.publie     { background: #d1fae5; color: #065f46; border: 1px solid var(--accent-success); }
        .status-badge.brouillon  { background: #e2e8f0; color: #475569; }

        .faculte-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            background: #eff6ff;
            color: #1e40af;
            font-weight: 600;
            font-size: 11px;
        }

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
           HERO BANNER
           ================================================================ */
        .hero-banner {
            background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 60%, #8b5cf6 100%);
            color: white;
            padding: 26px 30px;
            border-radius: var(--radius-lg);
            margin-bottom: 26px;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-md);
        }
        .hero-banner::before {
            content: '';
            position: absolute;
            top: -50%; right: -10%;
            width: 340px; height: 340px;
            background: radial-gradient(circle, rgba(255,255,255,0.1), transparent 70%);
            border-radius: 50%;
        }
        .hero-banner::after {
            content: '';
            position: absolute;
            bottom: -60%; left: 15%;
            width: 280px; height: 280px;
            background: radial-gradient(circle, rgba(236,72,153,0.2), transparent 70%);
            border-radius: 50%;
        }
        .hero-banner .hero-content {
            position: relative;
            z-index: 1;
        }
        .hero-banner h2 {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .hero-banner p {
            font-size: 14px;
            opacity: 0.9;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        /* ================================================================
           PROGRESS BAR
           ================================================================ */
        .progress-bar-bg {
            background: #f1f5f9;
            height: 10px;
            border-radius: 8px;
            overflow: hidden;
            margin-top: 8px;
        }
        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--accent-blue), var(--accent-purple));
            border-radius: 8px;
            transition: width 1s ease;
        }

        /* ================================================================
           LIST ITEMS (notifications / annonces)
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
           CHART
           ================================================================ */
        .chart-container { position: relative; height: 240px; }

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
        <div class="logo">🎓 <span>UniVerse</span></div>

        <div class="user-profile">
            <img src="uploads/<?= htmlspecialchars($photo_profil); ?>" alt="Photo" class="profile-img">
            <p class="name"><?= htmlspecialchars($etudiant['prenom'] . ' ' . $etudiant['nom']); ?></p>
            <p class="matricule">📋 <?= htmlspecialchars($etudiant['matricule']); ?></p>
            <span class="badge-role">🎓 Étudiant</span>
        </div>

        <ul class="nav-links">
            <li><a href="?page=dashboard" class="<?= ($page=='dashboard')?'active':''; ?>"><i class="fas fa-chart-line"></i> Tableau de bord</a></li>
            <li><a href="mes_ressources.php"><i class="fas fa-star"></i> ⭐ Mes Ressources</a></li>
            <li><a href="?page=profil" class="<?= ($page=='profil')?'active':''; ?>"><i class="fas fa-user"></i> Mon profil</a></li>
            <li><a href="?page=formation" class="<?= ($page=='formation')?'active':''; ?>"><i class="fas fa-graduation-cap"></i> Ma formation</a></li>
            <li><a href="?page=cours" class="<?= ($page=='cours')?'active':''; ?>"><i class="fas fa-book"></i> Mes cours</a></li>
            <li><a href="supports_cours.php"><i class="fas fa-folder-open"></i> Supports de cours</a></li>
            <li><a href="?page=horaires" class="<?= ($page=='horaires')?'active':''; ?>"><i class="fas fa-clock"></i> Emploi du temps</a></li>
            <li><a href="?page=examens" class="<?= ($page=='examens')?'active':''; ?>"><i class="fas fa-file-alt"></i> Mes examens</a></li>
            <li><a href="?page=resultats" class="<?= ($page=='resultats')?'active':''; ?>"><i class="fas fa-pen"></i> Mes résultats</a></li>
            <li><a href="?page=progression" class="<?= ($page=='progression')?'active':''; ?>"><i class="fas fa-chart-bar"></i> Progression</a></li>
            <li><a href="?page=resultat_annuel" class="<?= ($page=='resultat_annuel')?'active':''; ?>"><i class="fas fa-check-double"></i> Résultat annuel</a></li>
            <li><a href="?page=bulletin" class="<?= ($page=='bulletin')?'active':''; ?>"><i class="fas fa-file-alt"></i> Bulletin officiel</a></li>
            <li><a href="?page=paiements" class="<?= ($page=='paiements')?'active':''; ?>"><i class="fas fa-credit-card"></i> Mes paiements</a></li>
            <li><a href="?page=penalites" class="<?= ($page=='penalites')?'active':''; ?>"><i class="fas fa-exclamation-triangle"></i> Pénalités</a></li>
            <li><a href="?page=annonces" class="<?= ($page=='annonces')?'active':''; ?>"><i class="fas fa-bullhorn"></i> Annonces</a></li>
            <li><a href="?page=notifications" class="<?= ($page=='notifications')?'active':''; ?>"><i class="fas fa-bell"></i> Notifications <span class="nav-badge"><?= $nbNotifsNonLues; ?></span></a></li>
            <li><a href="?page=messages" class="<?= ($page=='messages')?'active':''; ?>"><i class="fas fa-envelope"></i> Messages</a></li>
            <li><a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
        </ul>
    </aside>

    <!-- ================================================================
         MAIN CONTENT
    ================================================================ -->
    <main class="main-content">

        <header class="page-header">
            <h1><i class="fas fa-user-graduate"></i> Portail Étudiant</h1>
            <div class="header-actions">
                <button id="themeToggleBtn" class="theme-toggle-btn" onclick="toggleTheme()" title="Mode sombre">🌙</button>
                <span class="btn btn-outline" style="cursor:default;">
                    <?= htmlspecialchars($etudiant['niveau'] . ' — ' . $etudiant['annee_academique_entree']); ?>
                </span>
                <a href="bulletin_pdf.php?id_etudiant=<?= $id_etudiant; ?>&annee=<?= $annee_courante; ?>" target="_blank" class="btn btn-success">
                    <i class="fas fa-file-pdf"></i> Bulletin PDF
                </a>
            </div>
        </header>

        <?php if (!empty($message)): ?>
            <div class="alert <?= $message_type; ?>"><?= $message; ?></div>
        <?php endif; ?>

        <!-- ============================================================
             PAGE : DASHBOARD (ACCUEIL)
        ============================================================ -->
        <?php if ($page == 'dashboard'): ?>

        <!-- Hero banner -->
        <div class="hero-banner">
            <div class="hero-content">
                <h2>👋 Bienvenue, <?= htmlspecialchars($etudiant['prenom']); ?> !</h2>
                <p>
                    <span>📅 <?= date('l d F Y') ?></span>
                    <span>|</span>
                    <span>🎓 Année académique <strong><?= htmlspecialchars($annee_courante); ?></strong></span>
                    <?php if ($resultatAnnuelData['statut'] == 'valide'): ?>
                        <span class="status-badge" style="background:rgba(255,255,255,0.2); color:white;">✅ Résultat validé</span>
                    <?php else: ?>
                        <span class="status-badge" style="background:rgba(255,255,255,0.2); color:white;">⏳ En attente de validation</span>
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <!-- Stat cards -->
        <div class="stats-grid">
            <div class="stat-card blue">
                <div class="stat-header">
                    <span class="stat-label">Cours</span>
                    <span class="stat-icon"><i class="fas fa-chalkboard"></i></span>
                </div>
                <div class="stat-value"><?= count($coursClasseList); ?></div>
                <div class="stat-sub">Cette année</div>
            </div>

            <div class="stat-card green">
                <div class="stat-header">
                    <span class="stat-label">Notes</span>
                    <span class="stat-icon"><i class="fas fa-check-double"></i></span>
                </div>
                <div class="stat-value"><?= $totalNotes; ?></div>
                <div class="stat-sub"><?= $notesValidees; ?> validées</div>
            </div>

            <div class="stat-card purple">
                <div class="stat-header">
                    <span class="stat-label">Moyenne</span>
                    <span class="stat-icon"><i class="fas fa-calculator"></i></span>
                </div>
                <div class="stat-value"><?= number_format($moyenneGlobale, 2); ?><span style="font-size:14px; color:var(--text-muted);">/20</span></div>
                <div class="stat-sub">Moyenne globale</div>
            </div>

            <div class="stat-card pink">
                <div class="stat-header">
                    <span class="stat-label">Crédits</span>
                    <span class="stat-icon"><i class="fas fa-certificate"></i></span>
                </div>
                <div class="stat-value"><?= $creditsObtenus; ?><span style="font-size:14px; color:var(--text-muted);">/<?= $creditsTotal; ?></span></div>
                <div class="stat-sub"><?= $progression; ?>% progression</div>
            </div>

            <div class="stat-card orange">
                <div class="stat-header">
                    <span class="stat-label">Paiements</span>
                    <span class="stat-icon"><i class="fas fa-wallet"></i></span>
                </div>
                <div class="stat-value"><?= count($paiementsList); ?></div>
                <div class="stat-sub"><?= $minervalOk ? '✅ En ordre' : '⚠ À régulariser'; ?></div>
            </div>

            <div class="stat-card blue">
                <div class="stat-header">
                    <span class="stat-label">Notifications</span>
                    <span class="stat-icon"><i class="fas fa-bell"></i></span>
                </div>
                <div class="stat-value"><?= $nbNotifsNonLues; ?></div>
                <div class="stat-sub">Non lues</div>
            </div>
        </div>

        <!-- Progression -->
        <div class="panel">
            <div class="panel-header">
                <h2><i class="fas fa-chart-bar" style="color:var(--accent-purple);"></i> Progression académique</h2>
                <a href="?page=progression" class="btn-action primary">Voir plus <i class="fas fa-arrow-right"></i></a>
            </div>
            <div style="margin-top:10px;">
                <div style="display:flex; justify-content:space-between; font-size:13px; font-weight:600; color:var(--text-secondary);">
                    <span><?= $creditsObtenus; ?> crédits obtenus</span>
                    <span><?= $creditsRestants; ?> crédits restants</span>
                    <span><?= $progression; ?>%</span>
                </div>
                <div class="progress-bar-bg">
                    <div class="progress-bar-fill" style="width:<?= min(100, $progression); ?>%;"></div>
                </div>
                <div style="margin-top:12px; display:flex; gap:15px; flex-wrap:wrap; font-size:12px; color:var(--text-muted);">
                    <span>✅ Cours validés : <?= $notesValidees; ?></span>
                    <span>📝 Total notes : <?= $totalNotes; ?></span>
                    <span>📊 Moyenne : <?= number_format($moyenneGlobale, 2); ?>/20</span>
                </div>
            </div>
        </div>

        <!-- Résultat annuel -->
        <div class="panel" style="border-left: 4px solid <?= ($resultatAnnuelData['statut'] == 'valide') ? 'var(--accent-success)' : 'var(--accent-warning)'; ?>;">
            <div class="panel-header">
                <h2>
                    <i class="fas fa-check-double" style="color:<?= ($resultatAnnuelData['statut'] == 'valide') ? 'var(--accent-success)' : 'var(--accent-warning)'; ?>;"></i>
                    Résultat annuel
                </h2>
                <a href="?page=resultat_annuel" class="btn-action primary">Voir détails <i class="fas fa-arrow-right"></i></a>
            </div>
            <?php if ($resultatAnnuelData['statut'] == 'valide'): ?>
                <div class="alert success" style="margin:0;">
                    <i class="fas fa-check-circle"></i> <strong>Année validée</strong> — Moyenne : <?= number_format($resultatAnnuelData['moyenne'], 2); ?>/20
                </div>
                <div style="display:flex; gap:20px; margin-top:12px; font-size:13px; flex-wrap:wrap;">
                    <span>📚 Crédits : <?= $resultatAnnuelData['credits_obtenus']; ?>/<?= $resultatAnnuelData['credits_requis']; ?></span>
                    <span>🎯 Décision : <strong><?= $resultatAnnuelData['decision']; ?></strong></span>
                    <?php if ($resultatAnnuelData['date_validation']): ?>
                        <span>📅 Validé le : <?= date('d/m/Y', strtotime($resultatAnnuelData['date_validation'])); ?></span>
                    <?php endif; ?>
                    <?php if ($publicationOk): ?>
                        <span><span class="status-badge publie">✅ Bulletin disponible</span></span>
                    <?php else: ?>
                        <span><span class="status-badge rejete">🔴 Publication bloquée</span></span>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="alert warning" style="margin:0;">
                    <i class="fas fa-hourglass-half"></i> <strong>En attente de validation</strong> — Le résultat annuel est en cours de vérification par la Doyenne.
                </div>
                <div style="margin-top:12px; font-size:13px; color:var(--text-muted);">
                    <i class="fas fa-info-circle"></i> Une notification vous sera envoyée dès que le résultat sera disponible.
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>


        <!-- ============================================================
             PAGE : PROFIL
        ============================================================ -->
        <?php if ($page == 'profil'): ?>
        <div class="panel">
            <div class="panel-header">
                <h2><i class="fas fa-user"></i> Mon profil</h2>
                <a href="?page=profil&edit=1" class="btn btn-warning"><i class="fas fa-edit"></i> Modifier</a>
            </div>
            <div style="display:flex; gap:24px; flex-wrap:wrap;">
                <div style="flex:0 0 200px; text-align:center;">
                    <img src="uploads/<?= htmlspecialchars($photo_profil); ?>" style="width:150px; height:150px; border-radius:50%; object-fit:cover; border:4px solid var(--accent-blue); box-shadow: 0 8px 20px rgba(37,99,235,0.2);">
                    <h3 style="margin-top:12px; font-size:17px;"><?= htmlspecialchars($etudiant['prenom'] . ' ' . $etudiant['nom']); ?></h3>
                    <span class="faculte-badge" style="margin-top:6px; display:inline-block;"><?= htmlspecialchars($etudiant['faculte'] ?? 'Non assignée'); ?></span>
                </div>
                <div style="flex:1; min-width:280px;">
                    <div class="grid-2">
                        <div><strong>Nom :</strong> <?= htmlspecialchars($etudiant['nom']); ?></div>
                        <div><strong>Prénom :</strong> <?= htmlspecialchars($etudiant['prenom']); ?></div>
                        <div><strong>Matricule :</strong> <?= htmlspecialchars($etudiant['matricule']); ?></div>
                        <div><strong>Sexe :</strong> <?= htmlspecialchars($etudiant['sexe'] ?? 'Non renseigné'); ?></div>
                        <div><strong>Email :</strong> <?= htmlspecialchars($etudiant['email']); ?></div>
                        <div><strong>Email académique :</strong> <?= htmlspecialchars($etudiant['email_auth']); ?></div>
                        <div><strong>Téléphone :</strong> <?= htmlspecialchars($etudiant['telephone'] ?? 'Non renseigné'); ?></div>
                        <div><strong>Faculté :</strong> <?= htmlspecialchars($etudiant['faculte'] ?? 'Non assignée'); ?></div>
                        <div><strong>Département :</strong> <?= htmlspecialchars($etudiant['nom_dept'] ?? 'Non assigné'); ?></div>
                        <div><strong>Classe :</strong> <?= htmlspecialchars($etudiant['niveau']); ?></div>
                        <div><strong>Année académique :</strong> <?= htmlspecialchars($annee_courante); ?></div>
                        <div><strong>Titre :</strong> <?= htmlspecialchars($etudiant['titre'] ?? 'Nouveau'); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <?php if (isset($_GET['edit']) && $_GET['edit'] == 1): ?>
        <div class="panel">
            <div class="panel-header"><h2><i class="fas fa-edit"></i> Modifier mon profil</h2></div>
            <form method="POST" action="mon_profil.php" enctype="multipart/form-data">
                <div class="grid-2">
                    <div class="form-group"><label>Téléphone</label><input type="text" name="telephone" value="<?= htmlspecialchars($etudiant['telephone'] ?? ''); ?>"></div>
                    <div class="form-group"><label>Email</label><input type="email" name="email" value="<?= htmlspecialchars($etudiant['email']); ?>" required></div>
                    <div class="form-group"><label>Photo</label><input type="file" name="photo" accept="image/*"></div>
                </div>
                <div style="display:flex; gap:12px; margin-top:12px;">
                    <button type="submit" name="modifier_profil" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
                    <a href="?page=profil" class="btn btn-outline">Annuler</a>
                </div>
            </form>
        </div>
        <?php endif; ?>
        <?php endif; ?>


        <!-- ============================================================
             PAGE : FORMATION
        ============================================================ -->
        <?php if ($page == 'formation'): ?>
        <div class="panel">
            <div class="panel-header"><h2><i class="fas fa-graduation-cap"></i> Ma formation</h2></div>
            <div class="grid-2">
                <div><strong>Faculté :</strong> <?= htmlspecialchars($etudiant['faculte'] ?? 'Non assignée'); ?></div>
                <div><strong>Département :</strong> <?= htmlspecialchars($etudiant['nom_dept'] ?? 'Non assigné'); ?></div>
                <div><strong>Classe :</strong> <?= htmlspecialchars($etudiant['niveau']); ?></div>
                <div><strong>Année académique :</strong> <?= htmlspecialchars($annee_courante); ?></div>
                <div><strong>Crédits requis :</strong> <?= $creditsTotal; ?></div>
                <div><strong>Crédits obtenus :</strong> <?= $creditsObtenus; ?></div>
                <div><strong>Crédits restants :</strong> <?= $creditsRestants; ?></div>
                <div><strong>Progression :</strong> <?= $progression; ?>%</div>
            </div>
            <div style="margin-top:16px;">
                <div class="progress-bar-bg">
                    <div class="progress-bar-fill" style="width:<?= min(100, $progression); ?>%;"></div>
                </div>
            </div>
            <div style="margin-top:16px; display:flex; gap:10px; flex-wrap:wrap;">
                <span class="status-badge valide">✅ <?= $notesValidees; ?> cours validés</span>
                <span class="status-badge rejete">❌ <?= $totalNotes - $notesValidees; ?> cours non validés</span>
                <span class="status-badge <?= $moyenneGlobale >= 10 ? 'valide' : 'rejete'; ?>">📊 Moyenne : <?= number_format($moyenneGlobale, 2); ?>/20</span>
            </div>
        </div>
        <?php endif; ?>


        <!-- ============================================================
             PAGE : COURS
        ============================================================ -->
        <?php if ($page == 'cours'): ?>
        <div class="panel">
            <div class="panel-header">
                <h2><i class="fas fa-book"></i> Mes cours</h2>
                <span style="font-size:13px; color:var(--text-muted);"><?= count($coursClasseList); ?> cours au total</span>
            </div>
            <div class="table-responsive">
                <table>
                    <thead><tr><th>Nom</th><th>Crédits</th><th>Semestre</th><th>Enseignant</th><th>Note</th><th>Statut</th></tr></thead>
                    <tbody>
                        <?php foreach($coursClasseList as $c):
                            $noteCours = array_filter($notes, function($n) use ($c) { return $n['id_cours'] == $c['id_cours']; });
                            $note = !empty($noteCours) ? reset($noteCours) : null;
                            $aNote = ($note && $note['note'] !== null);
                        ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($c['nom_cours']); ?></strong></td>
                                <td><?= $c['credit']; ?></td>
                                <td><?= htmlspecialchars($c['semestre']); ?></td>
                                <td><?= htmlspecialchars($c['enseignant'] ?? 'Non assigné'); ?></td>
                                <td><?= $aNote ? '<strong>' . number_format($note['note'], 2) . '/20</strong>' : '—'; ?></td>
                                <td>
                                    <?php if ($aNote && $note['date_publication']): ?>
                                        <span class="status-badge publie">✅ Publié</span>
                                    <?php elseif ($aNote): ?>
                                        <span class="status-badge en_attente">⏳ En attente</span>
                                    <?php else: ?>
                                        <span class="status-badge brouillon">⏳ Non évalué</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (count($coursClasseList) === 0): ?>
                            <tr><td colspan="6"><div class="empty-state"><i class="fas fa-book-open"></i><p>Aucun cours enregistré pour votre classe.</p></div></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>


        <!-- ============================================================
             PAGE : EXAMENS
        ============================================================ -->
        <?php if ($page == 'examens'): ?>
        <div class="panel">
            <div class="panel-header">
                <h2><i class="fas fa-file-alt" style="color:var(--accent-warning);"></i> Mes examens programmés</h2>
                <span style="font-size:13px; color:var(--text-muted);"><?= count($examensEtudiantList) ?> examen(s)</span>
            </div>
            <?php if (count($examensEtudiantList) > 0): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Titre</th>
                                <th>Cours</th>
                                <th>Type</th>
                                <th>Date</th>
                                <th>Horaire</th>
                                <th>Durée</th>
                                <th>Points</th>
                                <th>Instructions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $today = date('Y-m-d');
                            foreach ($examensEtudiantList as $e):
                                $is_future = strtotime($e['date_prevue']) >= strtotime($today);
                                $style_row = $is_future ? 'background:#f8fafc;' : 'opacity:0.7;';
                            ?>
                                <tr style="<?= $style_row ?>">
                                    <td><strong><?= htmlspecialchars($e['titre']) ?></strong></td>
                                    <td><?= htmlspecialchars($e['nom_cours']) ?></td>
                                    <td><span class="status-badge brouillon"><?= htmlspecialchars($e['type_examen']) ?></span></td>
                                    <td><strong style="color:<?= $is_future ? 'var(--accent-success)' : 'var(--text-muted)' ?>;"><?= date('d/m/Y', strtotime($e['date_prevue'])) ?></strong></td>
                                    <td><strong><?= substr($e['heure_debut'], 0, 5) . ' - ' . substr($e['heure_fin'], 0, 5) ?></strong></td>
                                    <td><?= $e['duree'] ?> min</td>
                                    <td><?= $e['points_total'] ?></td>
                                    <td style="font-size:12px; color:var(--text-muted);"><?= htmlspecialchars($e['instructions'] ?? '—') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-file-alt"></i>
                    <p>Aucun examen programmé pour votre classe.</p>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>


        <!-- ============================================================
             PAGE : HORAIRES
        ============================================================ -->
        <?php if ($page == 'horaires'): ?>
        <div class="panel">
            <div class="panel-header">
                <h2><i class="fas fa-clock"></i> Mon emploi du temps</h2>
            </div>
            <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:15px;">
                <button class="btn btn-outline" onclick="filtrerHoraire('all')">Tous</button>
                <button class="btn btn-outline" onclick="filtrerHoraire('Lundi')">Lundi</button>
                <button class="btn btn-outline" onclick="filtrerHoraire('Mardi')">Mardi</button>
                <button class="btn btn-outline" onclick="filtrerHoraire('Mercredi')">Mercredi</button>
                <button class="btn btn-outline" onclick="filtrerHoraire('Jeudi')">Jeudi</button>
                <button class="btn btn-outline" onclick="filtrerHoraire('Vendredi')">Vendredi</button>
                <button class="btn btn-outline" onclick="filtrerHoraire('Samedi')">Samedi</button>
            </div>
            <div class="table-responsive" id="horaireContainer">
                <table>
                    <thead><tr><th>Jour</th><th>Horaire</th><th>Cours</th><th>Enseignant</th><th>Salle</th><th>Type</th></tr></thead>
                    <tbody>
                        <?php foreach($horairesList as $h): ?>
                            <tr data-jour="<?= htmlspecialchars($h['jour']); ?>">
                                <td><strong><?= htmlspecialchars($h['jour']); ?></strong></td>
                                <td><?= substr($h['heure_debut'],0,5).' - '.substr($h['heure_fin'],0,5); ?></td>
                                <td><?= htmlspecialchars($h['nom_cours']); ?></td>
                                <td><?= htmlspecialchars($h['enseignant'] ?? 'Non assigné'); ?></td>
                                <td><?= htmlspecialchars($h['salle'] ?: '-'); ?></td>
                                <td><span class="status-badge brouillon"><?= htmlspecialchars($h['type_cours'] ?? 'CM'); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (count($horairesList) === 0): ?>
                            <tr><td colspan="6"><div class="empty-state"><i class="fas fa-calendar-times"></i><p>Aucun horaire programmé.</p></div></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <script>
            function filtrerHoraire(jour) {
                var rows = document.querySelectorAll('#horaireContainer tbody tr');
                rows.forEach(function(row) {
                    if (jour === 'all' || row.getAttribute('data-jour') === jour) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            }
        </script>
        <?php endif; ?>


        <!-- ============================================================
             PAGE : RÉSULTATS (avec filtres)
        ============================================================ -->
        <?php if ($page == 'resultats'): ?>
        <div class="panel">
            <div class="panel-header">
                <h2><i class="fas fa-pen"></i> Mes résultats</h2>
            </div>
            <form method="GET" class="filter-bar" style="margin-bottom:18px;">
                <input type="hidden" name="page" value="resultats">
                <div class="form-group">
                    <label>Année académique</label>
                    <select name="annee">
                        <option value="<?= $annee_courante; ?>" <?= ($annee_filtre == $annee_courante) ? 'selected' : ''; ?>><?= $annee_courante; ?></option>
                        <?php
                        $annees = $bdd->prepare("SELECT DISTINCT annee_academique_entree FROM etudiants WHERE id_etudiant = ?");
                        $annees->execute([$id_etudiant]);
                        while($a = $annees->fetch()):
                            if ($a['annee_academique_entree'] != $annee_courante): ?>
                                <option value="<?= $a['annee_academique_entree']; ?>" <?= ($annee_filtre == $a['annee_academique_entree']) ? 'selected' : ''; ?>><?= $a['annee_academique_entree']; ?></option>
                            <?php endif;
                        endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Semestre</label>
                    <select name="semestre">
                        <option value="">Tous</option>
                        <option value="Semestre 1" <?= ($semestre_filtre == 'Semestre 1') ? 'selected' : ''; ?>>Semestre 1</option>
                        <option value="Semestre 2" <?= ($semestre_filtre == 'Semestre 2') ? 'selected' : ''; ?>>Semestre 2</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Session</label>
                    <select name="session">
                        <option value="">Toutes</option>
                        <option value="1ère Session" <?= ($session_filtre == '1ère Session') ? 'selected' : ''; ?>>1ère Session</option>
                        <option value="2ème Session" <?= ($session_filtre == '2ème Session') ? 'selected' : ''; ?>>2ème Session</option>
                    </select>
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filtrer</button>
                    <a href="?page=resultats" class="btn btn-outline"><i class="fas fa-times"></i> Réinitialiser</a>
                </div>
            </form>

            <div class="table-responsive">
                <table>
                    <thead><tr><th>Cours</th><th>Crédits</th><th>Note</th><th>Session</th><th>Statut</th><th>Validation</th></tr></thead>
                    <tbody>
                        <?php if (count($notes) > 0): ?>
                            <?php foreach($notes as $n): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($n['nom_cours']); ?></strong></td>
                                    <td><?= $n['credit']; ?></td>
                                    <td><strong style="color:<?= $n['note'] >= 10 ? 'var(--accent-success)' : 'var(--accent-danger)'; ?>;"><?= number_format($n['note'], 2); ?>/20</strong></td>
                                    <td><?= htmlspecialchars($n['session']); ?></td>
                                    <td><span class="status-badge <?= $n['note'] >= 10 ? 'valide' : 'rejete'; ?>"><?= $n['note'] >= 10 ? '✅ Validé' : '❌ Échec'; ?></span></td>
                                    <td><?= $n['date_publication'] ? date('d/m/Y', strtotime($n['date_publication'])) : '—'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr style="font-weight:700; background:#f8fafc;">
                                <td colspan="2"><strong>Résumé</strong></td>
                                <td><strong><?= number_format($moyenneGlobale, 2); ?>/20</strong></td>
                                <td colspan="3">Crédits : <?= $creditsObtenus; ?>/<?= $creditsTotal; ?> | Validés : <?= $notesValidees; ?></td>
                            </tr>
                        <?php else: ?>
                            <tr><td colspan="6"><div class="empty-state"><i class="fas fa-inbox"></i><p>Aucun résultat publié pour cette année académique.</p></div></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>


        <!-- ============================================================
             PAGE : PROGRESSION ACADÉMIQUE
        ============================================================ -->
        <?php if ($page == 'progression'): ?>
        <div class="panel">
            <div class="panel-header"><h2><i class="fas fa-chart-bar"></i> Ma progression académique</h2></div>
            <div class="grid-2">
                <div style="background:#f8fafc; padding:20px; border-radius:var(--radius); border:1px solid var(--border-color);">
                    <div style="font-size:12px; color:var(--text-muted); text-transform:uppercase; font-weight:600; letter-spacing:0.4px;">Crédits obtenus</div>
                    <div style="font-size:30px; font-weight:700; color:var(--accent-blue); margin:6px 0;"><?= $creditsObtenus; ?> <span style="font-size:18px; color:var(--text-muted);">/ <?= $creditsTotal; ?></span></div>
                    <div class="progress-bar-bg">
                        <div class="progress-bar-fill" style="width:<?= min(100, $progression); ?>%;"></div>
                    </div>
                    <div style="font-size:13px; color:var(--text-muted); margin-top:6px;"><?= $progression; ?>% complété</div>
                </div>
                <div style="background:#f8fafc; padding:20px; border-radius:var(--radius); border:1px solid var(--border-color);">
                    <div style="font-size:12px; color:var(--text-muted); text-transform:uppercase; font-weight:600; letter-spacing:0.4px;">Cours</div>
                    <div style="font-size:30px; font-weight:700; color:var(--accent-success); margin:6px 0;"><?= $notesValidees; ?> <span style="font-size:18px; color:var(--text-muted);">/ <?= $totalNotes; ?></span></div>
                    <div style="font-size:13px; color:var(--text-muted);">validés</div>
                    <div style="font-size:13px; color:var(--text-muted); margin-top:8px;">Moyenne : <strong style="color:var(--text-primary);"><?= number_format($moyenneGlobale, 2); ?>/20</strong></div>
                </div>
            </div>
            <div style="margin-top:16px; display:flex; gap:10px; flex-wrap:wrap;">
                <span class="status-badge valide">✅ <?= $notesValidees; ?> cours validés</span>
                <span class="status-badge rejete">❌ <?= $totalNotes - $notesValidees; ?> cours non validés</span>
                <span class="status-badge <?= $creditsRestants == 0 ? 'valide' : 'en_attente'; ?>">📚 <?= $creditsRestants; ?> crédits restants</span>
            </div>
        </div>
        <?php endif; ?>


        <!-- ============================================================
             PAGE : RÉSULTAT ANNUEL
        ============================================================ -->
        <?php if ($page == 'resultat_annuel'): ?>
        <div class="panel" style="border-left: 4px solid <?= ($resultatAnnuelData['statut'] == 'valide') ? 'var(--accent-success)' : 'var(--accent-warning)'; ?>;">
            <div class="panel-header">
                <h2><i class="fas fa-check-double" style="color:<?= ($resultatAnnuelData['statut'] == 'valide') ? 'var(--accent-success)' : 'var(--accent-warning)'; ?>;"></i> Résultat annuel — <?= $annee_courante; ?></h2>
            </div>
            <div class="grid-2">
                <div><strong>Étudiant :</strong> <?= htmlspecialchars($etudiant['prenom'] . ' ' . $etudiant['nom']); ?></div>
                <div><strong>Matricule :</strong> <?= htmlspecialchars($etudiant['matricule']); ?></div>
                <div><strong>Classe :</strong> <?= htmlspecialchars($etudiant['niveau']); ?></div>
                <div><strong>Faculté :</strong> <?= htmlspecialchars($etudiant['faculte'] ?? 'Non assignée'); ?></div>
                <div><strong>Moyenne annuelle :</strong> <strong style="font-size:20px; color:<?= ($resultatAnnuelData['moyenne'] >= 10) ? 'var(--accent-success)' : 'var(--accent-danger)'; ?>;"><?= number_format($resultatAnnuelData['moyenne'], 2); ?>/20</strong></div>
                <div><strong>Crédits obtenus :</strong> <?= $resultatAnnuelData['credits_obtenus']; ?>/<?= $resultatAnnuelData['credits_requis']; ?></div>
                <div><strong>Décision :</strong> <span class="status-badge <?= $resultatAnnuelData['decision'] == 'ADMIS' ? 'valide' : 'rejete'; ?>"><?= $resultatAnnuelData['decision']; ?></span></div>
                <div>
                    <strong>Statut :</strong>
                    <?php if ($resultatAnnuelData['statut'] == 'valide'): ?>
                        <span class="status-badge publie">✅ Validé</span>
                    <?php else: ?>
                        <span class="status-badge en_attente">⏳ En attente</span>
                    <?php endif; ?>
                </div>
                <?php if ($resultatAnnuelData['date_validation']): ?>
                    <div><strong>Date de validation :</strong> <?= date('d/m/Y', strtotime($resultatAnnuelData['date_validation'])); ?></div>
                <?php endif; ?>
                <?php if ($resultatAnnuelData['valide_par']): ?>
                    <div><strong>Validé par :</strong> Doyen/Doyenne du département</div>
                <?php endif; ?>
            </div>
            <?php if ($publicationOk && $resultatAnnuelData['statut'] == 'valide'): ?>
                <div style="margin-top:16px;">
                    <a href="bulletin_pdf.php?id_etudiant=<?= $id_etudiant; ?>&annee=<?= $annee_courante; ?>" target="_blank" class="btn btn-success"><i class="fas fa-file-pdf"></i> Télécharger le bulletin PDF</a>
                </div>
            <?php else: ?>
                <div style="margin-top:16px; background:#fef3c7; padding:15px 18px; border-radius:var(--radius-sm); border-left:4px solid var(--accent-warning); color:#92400e;">
                    <i class="fas fa-info-circle"></i> Le bulletin officiel sera disponible après validation du résultat annuel et vérification de votre situation.
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>


        <!-- ============================================================
             PAGE : BULLETIN OFFICIEL
        ============================================================ -->
        <?php if ($page == 'bulletin'): ?>
        <div class="panel">
            <div class="panel-header">
                <h2><i class="fas fa-file-alt"></i> Bulletin officiel</h2>
            </div>
            <?php if ($publicationOk && $resultatAnnuelData['statut'] == 'valide'): ?>
                <div class="alert success" style="margin-bottom:16px;">
                    <i class="fas fa-check-circle"></i> <strong>Bulletin officiel disponible</strong> — Année académique <?= $annee_courante; ?>
                </div>
                <div style="display:flex; gap:12px; flex-wrap:wrap;">
                    <a href="bulletin_pdf.php?id_etudiant=<?= $id_etudiant; ?>&annee=<?= $annee_courante; ?>" target="_blank" class="btn btn-success"><i class="fas fa-file-pdf"></i> Télécharger PDF</a>
                    <a href="#" class="btn btn-primary" onclick="window.print(); return false;"><i class="fas fa-print"></i> Imprimer</a>
                    <a href="#" class="btn btn-outline"><i class="fas fa-share-alt"></i> Partager</a>
                </div>
                <div style="margin-top:20px; padding:18px; background:#f8fafc; border-radius:var(--radius-sm); border:1px solid var(--border-color);">
                    <p style="font-size:13px; color:var(--text-muted);">
                        <i class="fas fa-info-circle"></i> Le bulletin est officiel et certifié par l'Université du Lac Tanganyika.
                        <?php if ($resultatAnnuelData['date_validation']): ?>
                            Validé le <?= date('d/m/Y', strtotime($resultatAnnuelData['date_validation'])); ?>
                        <?php endif; ?>
                    </p>
                </div>
            <?php else: ?>
                <div class="alert warning" style="margin-bottom:16px;">
                    <i class="fas fa-hourglass-half"></i> <strong>Bulletin non disponible</strong>
                </div>
                <div style="padding:18px; background:#fef3c7; border-radius:var(--radius-sm); border-left:4px solid var(--accent-warning); color:#92400e;">
                    <p style="margin-bottom:8px; font-weight:600;">Le bulletin officiel n'est pas encore disponible pour les raisons suivantes :</p>
                    <ul style="list-style:disc; padding-left:20px; font-size:13px; color:#78350f;">
                        <?php if ($resultatAnnuelData['statut'] != 'valide'): ?>
                            <li>Le résultat annuel n'a pas encore été validé par la Doyenne.</li>
                        <?php endif; ?>
                        <?php if (!$minervalOk): ?>
                            <li>Votre situation financière (minerval) doit être régularisée.</li>
                        <?php endif; ?>
                        <?php if (!$penalitesOk): ?>
                            <li>Des pénalités doivent être régularisées.</li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div style="margin-top:16px;">
                    <a href="?page=resultat_annuel" class="btn btn-primary"><i class="fas fa-arrow-left"></i> Voir le résultat annuel</a>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>


        <!-- ============================================================
             PAGE : PAIEMENTS
        ============================================================ -->
        <?php if ($page == 'paiements'): ?>
        <div class="panel">
            <div class="panel-header">
                <h2><i class="fas fa-credit-card"></i> Mes paiements</h2>
                <div class="panel-actions">
                    <span class="status-badge <?= $minervalOk ? 'valide' : 'rejete'; ?>"><?= $minervalOk ? '✅ En ordre' : '⚠ À régulariser'; ?></span>
                    <span style="font-size:13px; font-weight:600; color:var(--text-secondary);">Total : <?= number_format($totalPaye, 0, ',', ' '); ?> BIF</span>
                </div>
            </div>
            <div class="table-responsive">
                <table>
                    <thead><tr><th>Tranche</th><th>Montant</th><th>Date</th><th>Banque</th><th>Bordereau</th><th>Statut</th></tr></thead>
                    <tbody>
                        <?php foreach($paiementsList as $p): ?>
                            <tr>
                                <td><?= htmlspecialchars($p['tranche'] ?? '—'); ?></td>
                                <td><strong><?= number_format($p['montant'], 0, ',', ' '); ?> BIF</strong></td>
                                <td><?= date('d/m/Y', strtotime($p['date_paiement'])); ?></td>
                                <td><?= htmlspecialchars($p['banque'] ?? '—'); ?></td>
                                <td><?= htmlspecialchars($p['bordereau'] ?? '—'); ?></td>
                                <td>
                                    <?php
                                    $statut_cls = 'en_attente';
                                    $statut_txt = '⏳ En attente';
                                    if ($p['statut'] == 'valide') { $statut_cls = 'valide'; $statut_txt = '✅ Validé'; }
                                    elseif ($p['statut'] == 'rejete') { $statut_cls = 'rejete'; $statut_txt = '❌ Rejeté'; }
                                    ?>
                                    <span class="status-badge <?= $statut_cls; ?>"><?= $statut_txt; ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($paiementsList)): ?>
                            <tr><td colspan="6"><div class="empty-state"><i class="fas fa-credit-card"></i><p>Aucun paiement enregistré.</p></div></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>


        <!-- ============================================================
             PAGE : PÉNALITÉS
        ============================================================ -->
        <?php if ($page == 'penalites'): ?>
        <div class="panel">
            <div class="panel-header"><h2><i class="fas fa-exclamation-triangle"></i> Mes pénalités</h2></div>
            <?php if ($penalitesOk): ?>
                <div class="alert success" style="margin:0;">
                    <i class="fas fa-check-circle"></i> ✅ Aucune pénalité enregistrée.
                </div>
            <?php else: ?>
                <div class="alert warning" style="margin:0;">
                    <i class="fas fa-exclamation-triangle"></i> ⚠ Des pénalités sont en attente de régularisation.
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>


        <!-- ============================================================
             PAGE : ANNONCES
        ============================================================ -->
        <?php if ($page == 'annonces'): ?>
        <div class="panel">
            <div class="panel-header"><h2><i class="fas fa-bullhorn"></i> Annonces</h2></div>
            <?php if (count($annoncesList) > 0): ?>
                <?php foreach($annoncesList as $a): ?>
                    <div class="list-item">
                        <div class="list-main">
                            <div class="list-title"><?= htmlspecialchars($a['titre']); ?></div>
                            <div class="list-text"><?= nl2br(htmlspecialchars($a['message'])); ?></div>
                            <div class="list-meta">
                                📅 <?= date('d/m/Y H:i', strtotime($a['date_publication'])); ?>
                                <?php if ($a['nom_cours']): ?>
                                    · 📚 <?= htmlspecialchars($a['nom_cours']); ?>
                                <?php endif; ?>
                                <?php if ($a['niveau']): ?>
                                    · 🏫 <?= htmlspecialchars($a['niveau']); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state"><i class="fas fa-bullhorn"></i><p>Aucune annonce pour le moment.</p></div>
            <?php endif; ?>
        </div>
        <?php endif; ?>


        <!-- ============================================================
             PAGE : NOTIFICATIONS
        ============================================================ -->
        <?php if ($page == 'notifications'): ?>
        <div class="panel">
            <div class="panel-header"><h2><i class="fas fa-bell"></i> Notifications</h2></div>
            <?php if (count($notificationsList) > 0): ?>
                <?php foreach($notificationsList as $n): ?>
                    <div class="list-item">
                        <div class="list-main">
                            <div class="list-title"><?= htmlspecialchars($n['titre']); ?></div>
                            <div class="list-text"><?= htmlspecialchars($n['message']); ?></div>
                            <div class="list-meta">📅 <?= date('d/m/Y H:i', strtotime($n['date_creation'])); ?></div>
                        </div>
                        <div>
                            <span class="status-badge <?= $n['statut'] == 'non_lu' ? 'en_attente' : 'valide'; ?>">
                                <?= $n['statut'] == 'non_lu' ? '⏳ Non lu' : '✅ Lu'; ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state"><i class="fas fa-bell-slash"></i><p>Aucune notification.</p></div>
            <?php endif; ?>
        </div>
        <?php endif; ?>


        <!-- ============================================================
             PAGE : MESSAGES
        ============================================================ -->
        <?php if ($page == 'messages'): ?>
        <div class="panel">
            <div class="panel-header"><h2><i class="fas fa-envelope"></i> Messages</h2></div>
            <div class="empty-state">
                <i class="fas fa-envelope"></i>
                <p>La messagerie sera bientôt disponible.</p>
            </div>
        </div>
        <?php endif; ?>


        <!-- ============================================================
             FOOTER
        ============================================================ -->
        <div style="text-align:center; padding:20px 0 10px; color:var(--text-light); font-size:12px; border-top:1px solid var(--border-color); margin-top:10px;">
            <p>© <?= date('Y'); ?> UniVerse — Portail Étudiant</p>
        </div>

    </main>

</body>
</html>