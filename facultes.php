<?php
require_once __DIR__ . '/db.php';
// ============================================================
// facultes.php — VITRINE PUBLIQUE des facultés & départements
// ✅ ACCESSIBLE À TOUS LES VISITEURS (aucune authentification)
// ============================================================

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
   AUTO-CRÉATION TABLE DEMANDES D'INFORMATION
============================================================ */
try {
    $bdd->exec("
        CREATE TABLE IF NOT EXISTS demandes_information (
            id_demande INT AUTO_INCREMENT PRIMARY KEY,
            nom VARCHAR(100) NOT NULL,
            prenom VARCHAR(100) NOT NULL,
            email VARCHAR(150) NOT NULL,
            telephone VARCHAR(30) DEFAULT NULL,
            id_dept INT DEFAULT NULL,
            nom_dept VARCHAR(150) DEFAULT NULL,
            faculte VARCHAR(150) DEFAULT NULL,
            niveau_souhaite VARCHAR(50) DEFAULT NULL,
            message TEXT DEFAULT NULL,
            statut ENUM('nouveau','traite','archive') DEFAULT 'nouveau',
            adresse_ip VARCHAR(45) DEFAULT NULL,
            date_demande DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (Exception $e) {
    // Silencieux : la table existe déjà ou droits limités
}

/* ============================================================
   TRAITEMENT DU FORMULAIRE (PUBLIC)
============================================================ */
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['envoyer_demande'])) {
    $nom             = trim($_POST['nom'] ?? '');
    $prenom          = trim($_POST['prenom'] ?? '');
    $email           = trim($_POST['email'] ?? '');
    $telephone       = trim($_POST['telephone'] ?? '');
    $id_dept         = (int)($_POST['id_dept'] ?? 0);
    $nom_dept        = trim($_POST['nom_dept'] ?? '');
    $faculte         = trim($_POST['faculte'] ?? '');
    $niveau_souhaite = trim($_POST['niveau_souhaite'] ?? '');
    $message         = trim($_POST['message'] ?? '');

    if ($nom === '' || $prenom === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Veuillez renseigner votre nom, prénom et une adresse e-mail valide.";
    } else {
        try {
            $stmt = $bdd->prepare("
                INSERT INTO demandes_information 
                    (nom, prenom, email, telephone, id_dept, nom_dept, faculte, niveau_souhaite, message, adresse_ip)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $nom, $prenom, $email,
                $telephone ?: null,
                $id_dept ?: null,
                $nom_dept ?: null,
                $faculte ?: null,
                $niveau_souhaite ?: null,
                $message ?: null,
                $_SERVER['REMOTE_ADDR'] ?? null
            ]);
            $success_message = "✅ Merci $prenom ! Votre demande pour le département « " . htmlspecialchars($nom_dept) . " » a bien été enregistrée. Nous vous contacterons très prochainement à l'adresse " . htmlspecialchars($email) . ".";
        } catch (Exception $e) {
            error_log("[facultes] Erreur insertion demande : " . $e->getMessage());
            $error_message = "Une erreur est survenue. Veuillez réessayer ou nous contacter par téléphone.";
        }
    }
}

/* ============================================================
   RÉCUPÉRATION DES FACULTÉS AVEC STATS
============================================================ */
$facultes = $bdd->query("
    SELECT 
        d.faculte,
        COUNT(DISTINCT d.id_dept) as nb_departements,
        COUNT(DISTINCT cl.id_classe) as nb_classes,
        COUNT(DISTINCT c.id_cours) as nb_cours,
        COUNT(DISTINCT e.id_etudiant) as nb_etudiants,
        COUNT(DISTINCT p.id_prof) as nb_profs
    FROM departements d
    LEFT JOIN classes cl ON d.id_dept = cl.id_dept
    LEFT JOIN cours c ON cl.id_classe = c.id_classe
    LEFT JOIN etudiants e ON cl.id_classe = e.id_classe
    LEFT JOIN professeurs p ON c.id_prof = p.id_prof
    WHERE d.faculte IS NOT NULL AND d.faculte != ''
    GROUP BY d.faculte
    ORDER BY nb_etudiants DESC, d.faculte ASC
")->fetchAll(PDO::FETCH_ASSOC);

/* ============================================================
   DÉPARTEMENTS PAR FACULTÉ
============================================================ */
$departementsParFaculte = [];
foreach ($facultes as $f) {
    $stmt = $bdd->prepare("
        SELECT 
            d.id_dept, d.nom_dept, d.description,
            COUNT(DISTINCT cl.id_classe) as nb_classes,
            COUNT(DISTINCT e.id_etudiant) as nb_etudiants,
            COUNT(DISTINCT c.id_cours) as nb_cours
        FROM departements d
        LEFT JOIN classes cl ON d.id_dept = cl.id_dept
        LEFT JOIN etudiants e ON cl.id_classe = e.id_classe
        LEFT JOIN cours c ON cl.id_classe = c.id_classe
        WHERE d.faculte = ?
        GROUP BY d.id_dept
        ORDER BY d.nom_dept
    ");
    $stmt->execute([$f['faculte']]);
    $departementsParFaculte[$f['faculte']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/* ============================================================
   STATS GLOBALES
============================================================ */
$totalFacultes     = count($facultes);
$totalDepartements = $bdd->query("SELECT COUNT(*) FROM departements WHERE faculte IS NOT NULL AND faculte != ''")->fetchColumn() ?: 0;
$totalEtudiants    = $bdd->query("SELECT COUNT(*) FROM etudiants")->fetchColumn() ?: 0;
$totalProfesseurs  = $bdd->query("SELECT COUNT(*) FROM professeurs")->fetchColumn() ?: 0;
$totalCours        = $bdd->query("SELECT COUNT(*) FROM cours")->fetchColumn() ?: 0;

/* ============================================================
   HELPER : THÈME VISUEL PAR FACULTÉ
============================================================ */
function getFaculteTheme($faculte) {
    $lower = mb_strtolower($faculte, 'UTF-8');

    $themes = [
        'technologie' => [
            'icon'    => 'fa-microchip',
            'color'   => '#06b6d4',
            'tagline' => 'Innovation & Recherche',
            'image'   => 'https://images.unsplash.com/photo-1532094349884-543bc11b234d?w=1400&q=85&auto=format&fit=crop',
        ],
        'ingénierie' => [
            'icon'    => 'fa-cogs',
            'color'   => '#6366f1',
            'tagline' => 'Ingénierie & Technologie',
            'image'   => 'https://images.unsplash.com/photo-1581092918056-0c4c3acd3789?w=1400&q=85&auto=format&fit=crop',
        ],
        'ingenierie' => [
            'icon'    => 'fa-cogs',
            'color'   => '#6366f1',
            'tagline' => 'Ingénierie & Technologie',
            'image'   => 'https://images.unsplash.com/photo-1581092918056-0c4c3acd3789?w=1400&q=85&auto=format&fit=crop',
        ],
        'droit' => [
            'icon'    => 'fa-gavel',
            'color'   => '#dc2626',
            'tagline' => 'Justice & Éthique',
            'image'   => 'https://images.unsplash.com/photo-1589829545856-d10d557cf95f?w=1400&q=85&auto=format&fit=crop',
        ],
        'médecine' => [
            'icon'    => 'fa-stethoscope',
            'color'   => '#10b981',
            'tagline' => 'Santé & Bien-être',
            'image'   => 'https://images.unsplash.com/photo-1576091160550-2173dba999ef?w=1400&q=85&auto=format&fit=crop',
        ],
        'medecine' => [
            'icon'    => 'fa-stethoscope',
            'color'   => '#10b981',
            'tagline' => 'Santé & Bien-être',
            'image'   => 'https://images.unsplash.com/photo-1576091160550-2173dba999ef?w=1400&q=85&auto=format&fit=crop',
        ],
        'gestion' => [
            'icon'    => 'fa-chart-line',
            'color'   => '#f59e0b',
            'tagline' => 'Management & Économie',
            'image'   => 'https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?w=1400&q=85&auto=format&fit=crop',
        ],
        'lettres' => [
            'icon'    => 'fa-book-open',
            'color'   => '#8b5cf6',
            'tagline' => 'Culture & Humanités',
            'image'   => 'https://images.unsplash.com/photo-1481627834876-b7833e8f5570?w=1400&q=85&auto=format&fit=crop',
        ],
        'politique' => [
            'icon'    => 'fa-landmark',
            'color'   => '#0ea5e9',
            'tagline' => 'Gouvernance & Société',
            'image'   => 'https://images.unsplash.com/photo-1529107386315-e1a2ed48a620?w=1400&q=85&auto=format&fit=crop',
        ],
        'science' => [
            'icon'    => 'fa-flask',
            'color'   => '#14b8a6',
            'tagline' => 'Sciences & Découvertes',
            'image'   => 'https://images.unsplash.com/photo-1532187863486-abf9dbad1b69?w=1400&q=85&auto=format&fit=crop',
        ],
    ];

    foreach ($themes as $keyword => $theme) {
        if (strpos($lower, $keyword) !== false) {
            return $theme;
        }
    }

    return [
        'icon'    => 'fa-university',
        'color'   => '#6366f1',
        'tagline' => 'Excellence académique',
        'image'   => 'https://images.unsplash.com/photo-1562774053-701939374585?w=1400&q=85&auto=format&fit=crop',
    ];
}

/* ============================================================
   HELPER : IMAGE PAR DÉPARTEMENT (granularité fine)
============================================================ */
function getDepartementImage($nom_dept) {
    $lower = mb_strtolower($nom_dept, 'UTF-8');

    $images = [
        'informatique'  => 'https://images.unsplash.com/photo-1518770660439-4636190af475?w=800&q=80&auto=format&fit=crop',
        'droit'         => 'https://images.unsplash.com/photo-1589829545856-d10d557cf95f?w=800&q=80&auto=format&fit=crop',
        'médecine'      => 'https://images.unsplash.com/photo-1576091160399-112ba8d25d1d?w=800&q=80&auto=format&fit=crop',
        'medecine'      => 'https://images.unsplash.com/photo-1576091160399-112ba8d25d1d?w=800&q=80&auto=format&fit=crop',
        'gestion'       => 'https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?w=800&q=80&auto=format&fit=crop',
        'electronique'  => 'https://images.unsplash.com/photo-1553406830-ef2513450d76?w=800&q=80&auto=format&fit=crop',
        'électronique'  => 'https://images.unsplash.com/photo-1553406830-ef2513450d76?w=800&q=80&auto=format&fit=crop',
        'civil'         => 'https://images.unsplash.com/photo-1541888946425-d81bb19240f5?w=800&q=80&auto=format&fit=crop',
        'génie'         => 'https://images.unsplash.com/photo-1541888946425-d81bb19240f5?w=800&q=80&auto=format&fit=crop',
        'genie'         => 'https://images.unsplash.com/photo-1541888946425-d81bb19240f5?w=800&q=80&auto=format&fit=crop',
        'batiment'      => 'https://images.unsplash.com/photo-1503387762-592deb58ef4e?w=800&q=80&auto=format&fit=crop',
        'bâtiment'      => 'https://images.unsplash.com/photo-1503387762-592deb58ef4e?w=800&q=80&auto=format&fit=crop',
        'science'       => 'https://images.unsplash.com/photo-1532094349884-543bc11b234d?w=800&q=80&auto=format&fit=crop',
        'politique'     => 'https://images.unsplash.com/photo-1529107386315-e1a2ed48a620?w=800&q=80&auto=format&fit=crop',
        'relation'      => 'https://images.unsplash.com/photo-1529107386315-e1a2ed48a620?w=800&q=80&auto=format&fit=crop',
        'economie'      => 'https://images.unsplash.com/photo-1554260570-9140fd3b7614?w=800&q=80&auto=format&fit=crop',
        'économie'      => 'https://images.unsplash.com/photo-1554260570-9140fd3b7614?w=800&q=80&auto=format&fit=crop',
        'fsgea'         => 'https://images.unsplash.com/photo-1554260570-9140fd3b7614?w=800&q=80&auto=format&fit=crop',
        'lettre'        => 'https://images.unsplash.com/photo-1481627834876-b7833e8f5570?w=800&q=80&auto=format&fit=crop',
        'langue'        => 'https://images.unsplash.com/photo-1481627834876-b7833e8f5570?w=800&q=80&auto=format&fit=crop',
        'fsi'           => 'https://images.unsplash.com/photo-1581092918056-0c4c3acd3789?w=800&q=80&auto=format&fit=crop',
        'spri'          => 'https://images.unsplash.com/photo-1529107386315-e1a2ed48a620?w=800&q=80&auto=format&fit=crop',
    ];

    foreach ($images as $keyword => $url) {
        if (strpos($lower, $keyword) !== false) {
            return $url;
        }
    }

    return 'https://images.unsplash.com/photo-1498243691581-b145c3f54a5a?w=800&q=80&auto=format&fit=crop';
}

/* ============================================================
   HELPER : COMPTEUR GLOBAL (pour les stats départements)
============================================================ */
function getDepartementLevels($bdd, $id_dept) {
    try {
        $stmt = $bdd->prepare("SELECT niveau FROM classes WHERE id_dept = ? ORDER BY niveau");
        $stmt->execute([$id_dept]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        return [];
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Découvrez les facultés et départements de l'Université du Lac Tanganyika. Choisissez votre parcours académique et préinscrivez-vous en ligne.">
    <meta name="keywords" content="université, facultés, départements, inscription, Bujumbura, Burundi">
    <title>Nos Facultés — Université Horizon Tanganyika (UHT)</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* ============================================================
           VARIABLES
           ============================================================ */
        :root {
            --navy: #0b1e3d;
            --navy-deep: #061127;
            --navy-light: #1e3a5f;
            --gold: #c9a55c;
            --gold-light: #e6c47a;
            --gold-dark: #a88547;
            --cream: #faf8f3;
            --white: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --text-light: #94a3b8;
            --border: #e2e8f0;

            --font-display: 'Playfair Display', Georgia, serif;
            --font-body: 'Inter', -apple-system, sans-serif;

            --shadow-sm: 0 1px 3px rgba(11, 30, 61, 0.06);
            --shadow-md: 0 4px 12px rgba(11, 30, 61, 0.08);
            --shadow-lg: 0 12px 32px rgba(11, 30, 61, 0.12);
            --shadow-xl: 0 24px 48px rgba(11, 30, 61, 0.18);

            --ease: cubic-bezier(0.4, 0, 0.2, 1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }

        body {
            font-family: var(--font-body);
            background: var(--cream);
            color: var(--text-main);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            overflow-x: hidden;
        }

        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: var(--navy-deep); }
        ::-webkit-scrollbar-thumb { background: var(--gold); border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--gold-light); }

        /* ============================================================
           TOP BAR PUBLIC
           ============================================================ */
        .top-bar {
            position: fixed;
            top: 0; left: 0; right: 0;
            padding: 18px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 1000;
            transition: all 0.4s var(--ease);
        }
        .top-bar.scrolled {
            background: rgba(11, 30, 61, 0.95);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            padding: 12px 40px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 14px;
            text-decoration: none;
            color: var(--white);
        }
        .brand-crest {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--gold), var(--gold-light));
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: var(--font-display);
            font-size: 18px;
            font-weight: 800;
            color: var(--navy);
            box-shadow: 0 0 0 3px rgba(201, 165, 92, 0.3), 0 6px 20px rgba(201, 165, 92, 0.4);
            border: 2px solid rgba(255, 255, 255, 0.2);
        }
        .brand-text {
            display: flex;
            flex-direction: column;
            line-height: 1.1;
        }
        .brand-name {
            font-family: var(--font-display);
            font-size: 19px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .brand-tagline {
            font-size: 10px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: rgba(255, 255, 255, 0.65);
            margin-top: 2px;
        }

        .top-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .login-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.25);
            color: var(--white);
            text-decoration: none;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s var(--ease);
            backdrop-filter: blur(8px);
        }
        .login-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }
        .login-btn.primary {
            background: linear-gradient(135deg, var(--gold), var(--gold-light));
            color: var(--navy);
            border-color: var(--gold);
        }
        .login-btn.primary:hover {
            box-shadow: 0 8px 24px rgba(201, 165, 92, 0.5);
        }

        /* ============================================================
           HERO PUBLIC
           ============================================================ */
        .hero {
            position: relative;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 120px 20px 80px;
            overflow: hidden;
            color: var(--white);
        }
        .hero-bg {
            position: absolute;
            inset: 0;
            background-image: url('https://images.unsplash.com/photo-1541339907198-e08756dedf3f?w=1920&q=85&auto=format&fit=crop');
            background-size: cover;
            background-position: center;
            transform: scale(1.1);
            animation: slowZoom 25s ease-in-out infinite alternate;
        }
        @keyframes slowZoom {
            0% { transform: scale(1.1); }
            100% { transform: scale(1.18); }
        }
        .hero-overlay {
            position: absolute;
            inset: 0;
            background:
                linear-gradient(180deg, rgba(6, 17, 39, 0.75) 0%, rgba(6, 17, 39, 0.55) 50%, rgba(6, 17, 39, 0.9) 100%),
                linear-gradient(135deg, rgba(201, 165, 92, 0.08) 0%, transparent 60%);
        }
        .hero-content {
            position: relative;
            z-index: 2;
            max-width: 960px;
            animation: heroFade 1.2s var(--ease) both;
        }
        @keyframes heroFade {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .hero-pretitle {
            display: inline-block;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 4px;
            color: var(--gold);
            margin-bottom: 24px;
            position: relative;
            padding: 0 40px;
        }
        .hero-pretitle::before,
        .hero-pretitle::after {
            content: '';
            position: absolute;
            top: 50%;
            width: 30px;
            height: 1px;
            background: var(--gold);
        }
        .hero-pretitle::before { left: 0; }
        .hero-pretitle::after { right: 0; }

        .hero-title {
            font-family: var(--font-display);
            font-size: clamp(38px, 6vw, 74px);
            font-weight: 800;
            line-height: 1.05;
            letter-spacing: -1px;
            margin-bottom: 24px;
            background: linear-gradient(135deg, #ffffff 0%, #f5e9d3 50%, #c9a55c 100%);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 0 4px 30px rgba(0, 0, 0, 0.2);
        }

        .hero-subtitle {
            font-size: clamp(16px, 2vw, 20px);
            font-weight: 400;
            color: rgba(255, 255, 255, 0.9);
            max-width: 700px;
            margin: 0 auto 40px;
            line-height: 1.6;
        }

        .hero-actions {
            display: flex;
            gap: 16px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .hero-btn {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            padding: 16px 36px;
            border-radius: 60px;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            text-decoration: none;
            transition: all 0.3s var(--ease);
        }
        .hero-btn.primary {
            background: linear-gradient(135deg, var(--gold), var(--gold-light));
            color: var(--navy);
            box-shadow: 0 8px 30px rgba(201, 165, 92, 0.4);
        }
        .hero-btn.primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 40px rgba(201, 165, 92, 0.55);
        }
        .hero-btn.secondary {
            background: rgba(255, 255, 255, 0.08);
            color: var(--white);
            border: 1px solid rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(8px);
        }
        .hero-btn.secondary:hover {
            background: rgba(255, 255, 255, 0.18);
            transform: translateY(-3px);
        }

        .scroll-hint {
            position: absolute;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 2;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            color: rgba(255, 255, 255, 0.7);
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 2px;
            animation: scrollBounce 2s infinite;
        }
        .scroll-hint i { font-size: 18px; color: var(--gold); }
        @keyframes scrollBounce {
            0%, 100% { transform: translateX(-50%) translateY(0); }
            50% { transform: translateX(-50%) translateY(8px); }
        }

        /* ============================================================
           OVERVIEW / STATS
           ============================================================ */
        .overview {
            background: var(--white);
            padding: 80px 20px;
            position: relative;
        }
        .overview::before {
            content: '';
            position: absolute;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 3px;
            background: var(--gold);
            border-radius: 2px;
        }
        .overview-header {
            text-align: center;
            max-width: 720px;
            margin: 0 auto 60px;
        }
        .overview-eyebrow {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 3px;
            color: var(--gold);
            margin-bottom: 16px;
        }
        .overview-title {
            font-family: var(--font-display);
            font-size: clamp(28px, 4vw, 42px);
            font-weight: 700;
            line-height: 1.15;
            color: var(--navy);
            margin-bottom: 16px;
            letter-spacing: -0.5px;
        }
        .overview-desc {
            font-size: 16px;
            color: var(--text-muted);
            line-height: 1.7;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 24px;
            max-width: 1100px;
            margin: 0 auto;
        }
        .stat-box {
            text-align: center;
            padding: 30px 20px;
            background: var(--cream);
            border: 1px solid var(--border);
            border-radius: 16px;
            transition: all 0.3s var(--ease);
            position: relative;
            overflow: hidden;
        }
        .stat-box::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--gold), var(--gold-light));
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.4s var(--ease);
        }
        .stat-box:hover {
            transform: translateY(-6px);
            box-shadow: var(--shadow-lg);
            border-color: var(--gold);
        }
        .stat-box:hover::before { transform: scaleX(1); }
        .stat-box-icon {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            background: var(--navy);
            color: var(--gold);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            margin: 0 auto 16px;
        }
        .stat-box-value {
            font-family: var(--font-display);
            font-size: clamp(36px, 4vw, 52px);
            font-weight: 800;
            color: var(--navy);
            line-height: 1;
            margin-bottom: 8px;
            letter-spacing: -1px;
        }
        .stat-box-label {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: var(--text-muted);
        }

        /* ============================================================
           MESSAGE SUCCESS / ERROR (FLASH)
           ============================================================ */
        .flash-message {
            max-width: 800px;
            margin: 30px auto;
            padding: 18px 24px;
            border-radius: 14px;
            font-weight: 500;
            font-size: 15px;
            display: flex;
            align-items: flex-start;
            gap: 14px;
            animation: flashIn 0.5s var(--ease);
        }
        @keyframes flashIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .flash-message.success {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
            border-left: 4px solid #10b981;
        }
        .flash-message.error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
            border-left: 4px solid #ef4444;
        }
        .flash-message i { font-size: 20px; margin-top: 2px; flex-shrink: 0; }

        /* ============================================================
           NAV STICKY
           ============================================================ */
        .faculty-nav {
            position: sticky;
            top: 0;
            z-index: 100;
            background: var(--navy);
            padding: 16px 0;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            border-bottom: 2px solid var(--gold);
        }
        .faculty-nav-inner {
            max-width: 1300px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            gap: 10px;
            overflow-x: auto;
            scrollbar-width: thin;
        }
        .faculty-nav-inner::-webkit-scrollbar { height: 4px; }
        .faculty-nav-inner::-webkit-scrollbar-thumb { background: var(--gold); }
        .faculty-nav-pill {
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 18px;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(201, 165, 92, 0.3);
            color: rgba(255, 255, 255, 0.85);
            text-decoration: none;
            border-radius: 24px;
            font-size: 12.5px;
            font-weight: 600;
            transition: all 0.3s var(--ease);
            white-space: nowrap;
        }
        .faculty-nav-pill i { color: var(--gold); font-size: 11px; }
        .faculty-nav-pill:hover,
        .faculty-nav-pill.active {
            background: var(--gold);
            border-color: var(--gold);
            color: var(--navy);
            transform: translateY(-1px);
        }
        .faculty-nav-pill:hover i,
        .faculty-nav-pill.active i { color: var(--navy); }

        /* ============================================================
           SECTIONS FACULTÉS
           ============================================================ */
        .faculty-section {
            padding: 90px 20px;
            position: relative;
            overflow: hidden;
        }
        .faculty-section:nth-child(even) { background: var(--white); }
        .faculty-section:nth-child(odd)  { background: var(--cream); }

        .faculty-inner {
            max-width: 1300px;
            margin: 0 auto;
        }

        /* En-tête de section faculté */
        .faculty-header {
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 50px;
            align-items: center;
            margin-bottom: 50px;
        }

        .faculty-image-wrap {
            position: relative;
            aspect-ratio: 1 / 1;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--shadow-lg);
            isolation: isolate;
        }
        .faculty-image-wrap::before {
            content: '';
            position: absolute;
            inset: 0;
            z-index: 2;
            background: linear-gradient(135deg, rgba(11, 30, 61, 0.5), transparent 70%);
            pointer-events: none;
        }
        .faculty-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 1.2s var(--ease);
        }
        .faculty-image-wrap:hover .faculty-image { transform: scale(1.08); }

        .faculty-image-badge {
            position: absolute;
            top: 16px;
            left: 16px;
            z-index: 3;
            width: 54px;
            height: 54px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: var(--white);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.2);
        }

        .faculty-content {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .faculty-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 3px;
            color: var(--gold);
        }
        .faculty-eyebrow::before {
            content: '';
            width: 24px;
            height: 1px;
            background: var(--gold);
        }
        .faculty-name {
            font-family: var(--font-display);
            font-size: clamp(28px, 3.5vw, 40px);
            font-weight: 800;
            line-height: 1.15;
            color: var(--navy);
            letter-spacing: -0.5px;
        }
        .faculty-tagline {
            font-size: 15px;
            color: var(--text-muted);
            font-style: italic;
            font-family: var(--font-display);
        }
        .faculty-stats {
            display: flex;
            gap: 24px;
            flex-wrap: wrap;
            margin-top: 8px;
            padding-top: 16px;
            border-top: 1px solid var(--border);
        }
        .fac-stat {
            display: flex;
            flex-direction: column;
        }
        .fac-stat-value {
            font-family: var(--font-display);
            font-size: 26px;
            font-weight: 800;
            color: var(--navy);
            line-height: 1;
        }
        .fac-stat-label {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-muted);
            margin-top: 4px;
        }

        /* ============================================================
           GRILLE DE DÉPARTEMENTS (SÉLECTIONNABLES)
           ============================================================ */
        .dept-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 12px;
        }
        .dept-title {
            font-size: 14px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: var(--navy);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .dept-title i { color: var(--gold); }
        .dept-hint {
            font-size: 12px;
            color: var(--text-muted);
            font-style: italic;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .dept-hint i { color: var(--gold); }

        .dept-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 24px;
        }

        /* Carte département */
        .dept-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 18px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: all 0.35s var(--ease);
            position: relative;
            cursor: pointer;
            text-align: left;
            padding: 0;
            font-family: inherit;
        }
        .dept-card:hover {
            transform: translateY(-6px);
            box-shadow: var(--shadow-xl);
            border-color: var(--gold);
        }

        .dept-card-image {
            position: relative;
            height: 160px;
            overflow: hidden;
        }
        .dept-card-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.8s var(--ease);
        }
        .dept-card:hover .dept-card-image img {
            transform: scale(1.1);
        }
        .dept-card-image::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, transparent 40%, rgba(11, 30, 61, 0.85) 100%);
        }
        .dept-card-image-title {
            position: absolute;
            bottom: 14px;
            left: 18px;
            right: 18px;
            z-index: 2;
            color: var(--white);
        }
        .dept-card-image-title .name {
            font-family: var(--font-display);
            font-size: 20px;
            font-weight: 700;
            line-height: 1.2;
            text-shadow: 0 2px 10px rgba(0,0,0,0.5);
        }

        .dept-card-body {
            padding: 20px 22px 22px;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .dept-card-desc {
            font-size: 13.5px;
            color: var(--text-muted);
            line-height: 1.55;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .dept-card-stats {
            display: flex;
            gap: 18px;
            padding: 12px 0;
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
        }
        .dept-card-stats span {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-main);
        }
        .dept-card-stats i {
            color: var(--gold);
            font-size: 12px;
        }

        .dept-card-cta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: auto;
        }
        .dept-card-cta .choose-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            background: linear-gradient(135deg, var(--navy), var(--navy-light));
            color: var(--white);
            border: none;
            border-radius: 30px;
            font-size: 12.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            cursor: pointer;
            transition: all 0.3s var(--ease);
            font-family: inherit;
        }
        .dept-card:hover .dept-card-cta .choose-btn {
            background: linear-gradient(135deg, var(--gold), var(--gold-light));
            color: var(--navy);
            box-shadow: 0 6px 18px rgba(201, 165, 92, 0.4);
        }
        .dept-card-cta .arrow {
            color: var(--gold);
            font-size: 16px;
            transition: transform 0.3s var(--ease);
        }
        .dept-card:hover .dept-card-cta .arrow {
            transform: translateX(4px);
            color: var(--navy);
        }

        /* Empty state */
        .dept-empty {
            grid-column: 1 / -1;
            padding: 50px 30px;
            text-align: center;
            background: var(--white);
            border: 2px dashed var(--border);
            border-radius: 18px;
            color: var(--text-muted);
        }
        .dept-empty i {
            font-size: 42px;
            color: var(--gold);
            margin-bottom: 14px;
            display: block;
            opacity: 0.5;
        }
        .dept-empty p { font-size: 14px; }

        /* ============================================================
           CTA FINAL PUBLIC
           ============================================================ */
        .final-cta {
            background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%);
            padding: 100px 20px;
            text-align: center;
            color: var(--white);
            position: relative;
            overflow: hidden;
        }
        .final-cta::before {
            content: '';
            position: absolute;
            top: -50%; right: -10%;
            width: 500px; height: 500px;
            background: radial-gradient(circle, rgba(201, 165, 92, 0.15), transparent 70%);
            border-radius: 50%;
        }
        .final-cta-inner {
            position: relative;
            z-index: 2;
            max-width: 700px;
            margin: 0 auto;
        }
        .final-cta h2 {
            font-family: var(--font-display);
            font-size: clamp(28px, 4vw, 44px);
            font-weight: 700;
            margin-bottom: 20px;
            line-height: 1.2;
        }
        .final-cta h2 span { color: var(--gold); }
        .final-cta p {
            font-size: 17px;
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 36px;
            line-height: 1.7;
        }
        .final-cta .contact-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .final-cta a {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 14px 28px;
            border-radius: 30px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s var(--ease);
        }
        .final-cta a.primary {
            background: linear-gradient(135deg, var(--gold), var(--gold-light));
            color: var(--navy);
            box-shadow: 0 8px 24px rgba(201, 165, 92, 0.4);
        }
        .final-cta a.primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 32px rgba(201, 165, 92, 0.55);
        }
        .final-cta a.outline {
            background: rgba(255, 255, 255, 0.1);
            color: var(--white);
            border: 1px solid rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(8px);
        }
        .final-cta a.outline:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-3px);
        }

        /* ============================================================
           MODAL PRÉINSCRIPTION
           ============================================================ */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(11, 30, 61, 0.7);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s var(--ease);
        }
        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .modal-box {
            background: var(--white);
            border-radius: 24px;
            max-width: 560px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 40px 80px rgba(0, 0, 0, 0.35);
            transform: translateY(30px) scale(0.95);
            transition: all 0.4s var(--ease);
            position: relative;
        }
        .modal-overlay.active .modal-box {
            transform: translateY(0) scale(1);
        }

        .modal-header {
            background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%);
            color: var(--white);
            padding: 28px 32px 24px;
            border-radius: 24px 24px 0 0;
            position: relative;
            overflow: hidden;
        }
        .modal-header::before {
            content: '';
            position: absolute;
            top: -40%; right: -10%;
            width: 180px; height: 180px;
            background: radial-gradient(circle, rgba(201, 165, 92, 0.3), transparent 70%);
            border-radius: 50%;
        }
        .modal-header h3 {
            font-family: var(--font-display);
            font-size: 24px;
            font-weight: 700;
            position: relative;
            z-index: 1;
            margin-bottom: 6px;
        }
        .modal-header p {
            font-size: 13px;
            color: rgba(255, 255, 255, 0.8);
            position: relative;
            z-index: 1;
        }
        .modal-header .modal-dept-tag {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 12px;
            padding: 6px 14px;
            background: rgba(201, 165, 92, 0.2);
            border: 1px solid rgba(201, 165, 92, 0.5);
            color: var(--gold-light);
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            position: relative;
            z-index: 1;
        }
        .modal-close {
            position: absolute;
            top: 16px;
            right: 16px;
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.25);
            color: var(--white);
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            z-index: 2;
            font-size: 16px;
        }
        .modal-close:hover {
            background: rgba(239, 68, 68, 0.3);
            border-color: rgba(239, 68, 68, 0.5);
        }

        .modal-body {
            padding: 28px 32px 32px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 16px;
        }
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .form-group.full { grid-column: 1 / -1; }
        .form-group label {
            font-size: 12px;
            font-weight: 700;
            color: var(--navy);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .form-group label .req { color: #ef4444; }
        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 12px 14px;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            font-size: 14px;
            font-family: inherit;
            outline: none;
            transition: all 0.2s var(--ease);
            background: var(--cream);
            color: var(--text-main);
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: var(--gold);
            background: var(--white);
            box-shadow: 0 0 0 3px rgba(201, 165, 92, 0.15);
        }
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .modal-footer {
            display: flex;
            gap: 12px;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
        }
        .modal-footer button,
        .modal-footer a {
            flex: 1;
            padding: 14px 20px;
            border: none;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s var(--ease);
            font-family: inherit;
            text-decoration: none;
            text-align: center;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-submit {
            background: linear-gradient(135deg, var(--gold), var(--gold-light));
            color: var(--navy);
            box-shadow: 0 6px 18px rgba(201, 165, 92, 0.35);
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 24px rgba(201, 165, 92, 0.5);
        }
        .btn-cancel {
            background: var(--cream);
            color: var(--text-muted);
            border: 1px solid var(--border) !important;
        }
        .btn-cancel:hover {
            background: #f1f5f9;
            color: var(--text-main);
        }

        /* ============================================================
           FLOATING CONTACT
           ============================================================ */
        .contact-float {
            position: fixed;
            bottom: 30px;
            left: 30px;
            z-index: 500;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .contact-float a {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            font-size: 20px;
            color: white;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.25);
            transition: all 0.3s var(--ease);
            position: relative;
        }
        .contact-float a:hover {
            transform: scale(1.1) translateY(-3px);
        }
        .contact-float a .tooltip {
            position: absolute;
            left: 62px;
            top: 50%;
            transform: translateY(-50%);
            background: var(--navy);
            color: white;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s;
        }
        .contact-float a:hover .tooltip { opacity: 1; }
        .contact-float .whatsapp { background: linear-gradient(135deg, #25d366, #128c7e); }
        .contact-float .email    { background: linear-gradient(135deg, #6366f1, #4f46e5); }
        .contact-float .phone    { background: linear-gradient(135deg, var(--gold), var(--gold-dark)); }

        /* ============================================================
           BACK TO TOP
           ============================================================ */
        .back-to-top {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--navy), var(--navy-light));
            color: var(--gold);
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            box-shadow: 0 8px 24px rgba(11, 30, 61, 0.4);
            opacity: 0;
            visibility: hidden;
            transform: translateY(20px);
            transition: all 0.3s var(--ease);
            z-index: 500;
        }
        .back-to-top.visible {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        .back-to-top:hover {
            background: linear-gradient(135deg, var(--gold), var(--gold-light));
            color: var(--navy);
            transform: translateY(-4px);
        }

        /* ============================================================
           FOOTER PUBLIC
           ============================================================ */
        .footer {
            background: var(--navy-deep);
            color: rgba(255, 255, 255, 0.7);
            padding: 60px 20px 30px;
            text-align: center;
            position: relative;
        }
        .footer::before {
            content: '';
            position: absolute;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 3px;
            background: var(--gold);
        }
        .footer-crest {
            width: 64px;
            height: 64px;
            margin: 0 auto 20px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--gold), var(--gold-light));
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: var(--font-display);
            font-size: 20px;
            font-weight: 800;
            color: var(--navy);
            box-shadow: 0 0 0 4px rgba(201, 165, 92, 0.2);
        }
        .footer h3 {
            font-family: var(--font-display);
            font-size: 22px;
            font-weight: 700;
            color: var(--white);
            margin-bottom: 8px;
        }
        .footer .footer-tagline {
            font-size: 13px;
            color: rgba(255, 255, 255, 0.55);
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 24px;
        }
        .footer .footer-divider {
            width: 60px;
            height: 1px;
            background: var(--gold);
            margin: 24px auto;
            opacity: 0.5;
        }
        .footer .footer-links {
            display: flex;
            gap: 24px;
            justify-content: center;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        .footer .footer-links a {
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: color 0.2s;
        }
        .footer .footer-links a:hover { color: var(--gold); }
        .footer .copyright {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.45);
            line-height: 1.7;
        }

        /* ============================================================
           REVEAL ON SCROLL
           ============================================================ */
        .reveal {
            opacity: 0;
            transform: translateY(40px);
            transition: opacity 0.8s var(--ease), transform 0.8s var(--ease);
        }
        .reveal.is-visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* ============================================================
           RESPONSIVE
           ============================================================ */
        @media (max-width: 1024px) {
            .faculty-header { grid-template-columns: 1fr; gap: 32px; }
            .faculty-image-wrap { aspect-ratio: 16 / 9; max-width: 500px; }
        }
        @media (max-width: 768px) {
            .top-bar { padding: 14px 18px; }
            .top-bar.scrolled { padding: 10px 18px; }
            .brand-name { font-size: 15px; }
            .brand-tagline { display: none; }
            .brand-crest { width: 40px; height: 40px; font-size: 15px; }
            .login-btn { padding: 8px 14px; font-size: 12px; }
            .hero { min-height: 85vh; padding: 100px 16px 60px; }
            .hero-actions { flex-direction: column; align-items: center; }
            .hero-btn { width: 100%; max-width: 300px; justify-content: center; }
            .overview { padding: 60px 16px; }
            .faculty-section { padding: 60px 16px; }
            .dept-grid { grid-template-columns: 1fr; gap: 16px; }
            .form-row { grid-template-columns: 1fr; }
            .modal-body { padding: 22px; }
            .modal-header { padding: 22px; }
            .modal-header h3 { font-size: 20px; }
            .contact-float { bottom: 20px; left: 20px; }
            .contact-float a { width: 46px; height: 46px; font-size: 18px; }
            .back-to-top { bottom: 20px; right: 20px; width: 44px; height: 44px; }
        }
        @media (max-width: 480px) {
            .brand-text { display: none; }
            .stats-grid { grid-template-columns: 1fr; }
            .final-cta .contact-actions { flex-direction: column; }
            .final-cta a { width: 100%; justify-content: center; }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                transition-duration: 0.01ms !important;
            }
            .reveal { opacity: 1; transform: none; }
            .hero-bg { animation: none; }
        }
    </style>
</head>
<body>

    <!-- ============================================================
         TOP BAR
    ============================================================ -->
    <header class="top-bar" id="topBar">
        <a href="Accueil.php" class="brand">
            <div class="brand-crest">UHT</div>
            <div class="brand-text">
                <span class="brand-name">Université Horizon Tanganyika (UHT)</span>
                <span class="brand-tagline">Excellence • Innovation • Réussite</span>
            </div>
        </a>
        <div class="top-actions">
            <a href="login.php" class="login-btn primary">
                <i class="fas fa-sign-in-alt"></i>
                <span>Espace personnel</span>
            </a>
        </div>
    </header>

    <!-- ============================================================
         HERO
    ============================================================ -->
    <section class="hero">
        <div class="hero-bg"></div>
        <div class="hero-overlay"></div>

        <div class="hero-content">
            <div class="hero-pretitle">Étudiez à l'UHT</div>
            <h1 class="hero-title">
                Choisissez votre<br>
                avenir académique
            </h1>
            <p class="hero-subtitle">
                Explorez nos facultés et départements, comparez les programmes et trouvez la formation qui correspond à votre projet professionnel. Votre futur commence ici.
            </p>
            <div class="hero-actions">
                <a href="#overview" class="hero-btn primary">
                    Découvrir les facultés <i class="fas fa-arrow-down"></i>
                </a>
                <a href="#contact-section" class="hero-btn secondary">
                    <i class="fas fa-phone"></i> Nous contacter
                </a>
            </div>
        </div>

        <div class="scroll-hint">
            <span>Défiler</span>
            <i class="fas fa-chevron-down"></i>
        </div>
    </section>

    <!-- ============================================================
         FLASH MESSAGES
    ============================================================ -->
    <?php if (!empty($success_message)): ?>
        <div class="flash-message success" style="background: #ecfdf5; margin: 30px auto; max-width: 800px; padding: 18px 24px; border-radius: 14px; color: #065f46; border-left: 4px solid #10b981; display: flex; align-items: flex-start; gap: 14px; font-weight: 500;">
            <i class="fas fa-check-circle" style="font-size:20px; flex-shrink:0; margin-top:2px;"></i>
            <div><?= $success_message; ?></div>
        </div>
    <?php endif; ?>
    <?php if (!empty($error_message)): ?>
        <div class="flash-message error" style="background: #fef2f2; margin: 30px auto; max-width: 800px; padding: 18px 24px; border-radius: 14px; color: #991b1b; border-left: 4px solid #ef4444; display: flex; align-items: flex-start; gap: 14px; font-weight: 500;">
            <i class="fas fa-exclamation-circle" style="font-size:20px; flex-shrink:0; margin-top:2px;"></i>
            <div><?= htmlspecialchars($error_message); ?></div>
        </div>
    <?php endif; ?>

    <!-- ============================================================
         OVERVIEW
    ============================================================ -->
    <section class="overview" id="overview">
        <div class="overview-header reveal">
            <div class="overview-eyebrow">Notre Université en Chiffres</div>
            <h2 class="overview-title">Une institution d'excellence au service du savoir</h2>
            <p class="overview-desc">
                Chaque faculté contribue à la richesse académique de notre université à travers ses départements, ses programmes et sa communauté. Choisissez votre parcours.
            </p>
        </div>

        <div class="stats-grid">
            <div class="stat-box reveal" style="transition-delay: 0.1s;">
                <div class="stat-box-icon"><i class="fas fa-university"></i></div>
                <div class="stat-box-value counter" data-target="<?= $totalFacultes ?>">0</div>
                <div class="stat-box-label">Facultés</div>
            </div>
            <div class="stat-box reveal" style="transition-delay: 0.2s;">
                <div class="stat-box-icon"><i class="fas fa-sitemap"></i></div>
                <div class="stat-box-value counter" data-target="<?= $totalDepartements ?>">0</div>
                <div class="stat-box-label">Départements</div>
            </div>
            <div class="stat-box reveal" style="transition-delay: 0.3s;">
                <div class="stat-box-icon"><i class="fas fa-user-graduate"></i></div>
                <div class="stat-box-value counter" data-target="<?= $totalEtudiants ?>">0</div>
                <div class="stat-box-label">Étudiants</div>
            </div>
            <div class="stat-box reveal" style="transition-delay: 0.4s;">
                <div class="stat-box-icon"><i class="fas fa-chalkboard-teacher"></i></div>
                <div class="stat-box-value counter" data-target="<?= $totalProfesseurs ?>">0</div>
                <div class="stat-box-label">Professeurs</div>
            </div>
        </div>
    </section>

    <?php if (empty($facultes)): ?>
        <section style="padding:100px 20px; text-align:center; background:var(--white);">
            <i class="fas fa-university" style="font-size:64px; color:var(--gold); opacity:0.5; display:block; margin-bottom:20px;"></i>
            <h2 style="font-family:var(--font-display); font-size:28px; color:var(--navy); margin-bottom:12px;">Facultés bientôt disponibles</h2>
            <p style="color:var(--text-muted); max-width:600px; margin:0 auto;">Les facultés et leurs départements apparaîtront ici dès qu'elles seront ajoutées. Revenez bientôt ou contactez-nous pour plus d'informations.</p>
        </section>
    <?php else: ?>

        <!-- ============================================================
             NAV STICKY
        ============================================================ -->
        <nav class="faculty-nav" id="facultyNav">
            <div class="faculty-nav-inner">
                <?php foreach ($facultes as $i => $f):
                    $theme = getFaculteTheme($f['faculte']);
                ?>
                    <a href="#faculty-<?= $i ?>" class="faculty-nav-pill" data-target="faculty-<?= $i ?>">
                        <i class="fas <?= $theme['icon'] ?>"></i>
                        <?= htmlspecialchars($f['faculte']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </nav>

        <!-- ============================================================
             SECTIONS FACULTÉS (avec départements sélectionnables)
        ============================================================ -->
        <?php foreach ($facultes as $i => $f):
            $theme = getFaculteTheme($f['faculte']);
            $depts = $departementsParFaculte[$f['faculte']] ?? [];
        ?>
            <section class="faculty-section" id="faculty-<?= $i ?>">
                <div class="faculty-inner">

                    <!-- En-tête faculté -->
                    <div class="faculty-header">
                        <div class="faculty-image-wrap reveal">
                            <img src="<?= htmlspecialchars($theme['image']) ?>"
                                 alt="<?= htmlspecialchars($f['faculte']) ?>"
                                 class="faculty-image"
                                 loading="lazy">
                            <div class="faculty-image-badge" style="background:<?= $theme['color'] ?>;">
                                <i class="fas <?= $theme['icon'] ?>"></i>
                            </div>
                        </div>

                        <div class="faculty-content reveal">
                            <div class="faculty-eyebrow">
                                <span>Faculté n°<?= str_pad($i + 1, 2, '0', STR_PAD_LEFT) ?></span>
                            </div>
                            <h2 class="faculty-name"><?= htmlspecialchars($f['faculte']) ?></h2>
                            <p class="faculty-tagline"><?= htmlspecialchars($theme['tagline']) ?></p>

                            <div class="faculty-stats">
                                <div class="fac-stat">
                                    <div class="fac-stat-value"><?= (int)$f['nb_departements'] ?></div>
                                    <div class="fac-stat-label">Départements</div>
                                </div>
                                <div class="fac-stat">
                                    <div class="fac-stat-value"><?= (int)$f['nb_classes'] ?></div>
                                    <div class="fac-stat-label">Classes</div>
                                </div>
                                <div class="fac-stat">
                                    <div class="fac-stat-value"><?= (int)$f['nb_etudiants'] ?></div>
                                    <div class="fac-stat-label">Étudiants</div>
                                </div>
                                <div class="fac-stat">
                                    <div class="fac-stat-value"><?= (int)$f['nb_cours'] ?></div>
                                    <div class="fac-stat-label">Cours</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Départements -->
                    <div class="dept-header">
                        <div class="dept-title">
                            <i class="fas fa-layer-group"></i>
                            Départements disponibles (<?= count($depts) ?>)
                        </div>
                        <div class="dept-hint">
                            <i class="fas fa-hand-pointer"></i>
                            Cliquez sur un département pour vous préinscrire
                        </div>
                    </div>

                    <?php if (!empty($depts)): ?>
                        <div class="dept-grid">
                            <?php foreach ($depts as $d):
                                $deptImage = getDepartementImage($d['nom_dept']);
                            ?>
                                <button type="button"
                                        class="dept-card reveal"
                                        onclick="ouvrirPreinscription(<?= (int)$d['id_dept'] ?>, '<?= htmlspecialchars(addslashes($d['nom_dept'])) ?>', '<?= htmlspecialchars(addslashes($f['faculte'])) ?>')">
                                    <div class="dept-card-image">
                                        <img src="<?= htmlspecialchars($deptImage) ?>"
                                             alt="<?= htmlspecialchars($d['nom_dept']) ?>"
                                             loading="lazy">
                                        <div class="dept-card-image-title">
                                            <div class="name"><?= htmlspecialchars($d['nom_dept']) ?></div>
                                        </div>
                                    </div>
                                    <div class="dept-card-body">
                                        <?php if (!empty($d['description'])): ?>
                                            <div class="dept-card-desc"><?= htmlspecialchars($d['description']) ?></div>
                                        <?php else: ?>
                                            <div class="dept-card-desc" style="font-style:italic; opacity:0.6;">Département de la faculté <?= htmlspecialchars($f['faculte']) ?>.</div>
                                        <?php endif; ?>

                                        <div class="dept-card-stats">
                                            <span><i class="fas fa-users"></i> <?= (int)$d['nb_etudiants'] ?> étudiants</span>
                                            <span><i class="fas fa-book"></i> <?= (int)$d['nb_classes'] ?> classes</span>
                                        </div>

                                        <div class="dept-card-cta">
                                            <span class="choose-btn">
                                                <i class="fas fa-check"></i> Choisir ce département
                                            </span>
                                            <i class="fas fa-arrow-right arrow"></i>
                                        </div>
                                    </div>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="dept-empty">
                            <i class="fas fa-info-circle"></i>
                            <p>Aucun département enregistré pour cette faculté pour le moment.</p>
                        </div>
                    <?php endif; ?>

                </div>
            </section>
        <?php endforeach; ?>

    <?php endif; ?>

    <!-- ============================================================
         FINAL CTA (public)
    ============================================================ -->
    <section class="final-cta" id="contact-section">
        <div class="final-cta-inner">
            <h2>Prêt à rejoindre <span>l'Université</span> ?</h2>
            <p>
                Notre équipe d'admission est à votre disposition pour vous accompagner dans votre projet.
                Contactez-nous dès aujourd'hui pour plus d'informations sur les inscriptions, les programmes et les frais de scolarité.
            </p>
            <div class="contact-actions">
                <a href="tel:+25722220000" class="primary">
                    <i class="fas fa-phone-alt"></i> +257 22 22 00 00
                </a>
                <a href="mailto:admissions@ult.edu.bi" class="outline">
                    <i class="fas fa-envelope"></i> admissions@ult.edu.bi
                </a>
                <a href="login.php" class="outline">
                    <i class="fas fa-sign-in-alt"></i> Espace étudiant
                </a>
            </div>
        </div>
    </section>

    <!-- ============================================================
         FOOTER
    ============================================================ -->
    <footer class="footer">
        <div class="footer-crest">ULT</div>
        <h3>Université du Lac Tanganyika</h3>
        <p class="footer-tagline">Excellence • Innovation • Réussite</p>
        <div class="footer-divider"></div>
        <div class="footer-links">
            <a href="Accueil.php">Accueil</a>
            <a href="facultes.php">Facultés</a>
            <a href="#contact-section">Contact</a>
            <a href="login.php">Espace personnel</a>
        </div>
        <p class="copyright">
            © <?= date('Y') ?> Université du Lac Tanganyika — Tous droits réservés<br>
            Avenue de l'Université, Bujumbura, Burundi
        </p>
    </footer>

    <!-- ============================================================
         FLOATING CONTACT
    ============================================================ -->
    <div class="contact-float">
        <a href="https://wa.me/25722220000" target="_blank" class="whatsapp" aria-label="WhatsApp">
            <i class="fab fa-whatsapp"></i>
            <span class="tooltip">WhatsApp</span>
        </a>
        <a href="mailto:admissions@ult.edu.bi" class="email" aria-label="Email">
            <i class="fas fa-envelope"></i>
            <span class="tooltip">Email</span>
        </a>
        <a href="tel:+25722220000" class="phone" aria-label="Téléphone">
            <i class="fas fa-phone"></i>
            <span class="tooltip">Appeler</span>
        </a>
    </div>

    <!-- Back to top -->
    <button class="back-to-top" id="backToTop" onclick="window.scrollTo({top:0,behavior:'smooth'})" aria-label="Retour en haut">
        <i class="fas fa-arrow-up"></i>
    </button>

    <!-- ============================================================
         MODAL PRÉINSCRIPTION
    ============================================================ -->
    <div class="modal-overlay" id="modalPreinscription">
        <div class="modal-box">
            <div class="modal-header">
                <button class="modal-close" onclick="fermerPreinscription()" aria-label="Fermer">
                    <i class="fas fa-times"></i>
                </button>
                <h3>Préinscription</h3>
                <p>Remplissez ce formulaire, nous vous recontactons rapidement.</p>
                <span class="modal-dept-tag" id="modalDeptTag">
                    <i class="fas fa-graduation-cap"></i>
                    <span id="modalDeptName">Département</span>
                </span>
            </div>

            <div class="modal-body">
                <form method="POST" action="facultes.php" id="preinscriptionForm">
                    <input type="hidden" name="id_dept" id="formIdDept">
                    <input type="hidden" name="nom_dept" id="formNomDept">
                    <input type="hidden" name="faculte" id="formFaculte">

                    <div class="form-row">
                        <div class="form-group">
                            <label>Prénom <span class="req">*</span></label>
                            <input type="text" name="prenom" required placeholder="Votre prénom">
                        </div>
                        <div class="form-group">
                            <label>Nom <span class="req">*</span></label>
                            <input type="text" name="nom" required placeholder="Votre nom">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Email <span class="req">*</span></label>
                            <input type="email" name="email" required placeholder="vous@exemple.com">
                        </div>
                        <div class="form-group">
                            <label>Téléphone</label>
                            <input type="tel" name="telephone" placeholder="+257 ...">
                        </div>
                    </div>

                    <div class="form-group full" style="margin-bottom: 16px;">
                        <label>Niveau souhaité</label>
                        <select name="niveau_souhaite">
                            <option value="">— Sélectionner —</option>
                            <option value="BAC I">BAC I (1ère année)</option>
                            <option value="BAC II">BAC II (2ème année)</option>
                            <option value="BAC III">BAC III (3ème année)</option>
                            <option value="BAC IV">BAC IV (4ème année)</option>
                            <option value="BAC V">BAC V (5ème année)</option>
                            <option value="Autre">Autre / À définir</option>
                        </select>
                    </div>

                    <div class="form-group full" style="margin-bottom: 16px;">
                        <label>Message (optionnel)</label>
                        <textarea name="message" placeholder="Une question ? Précisez votre demande..."></textarea>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn-cancel" onclick="fermerPreinscription()">
                            <i class="fas fa-times"></i> Annuler
                        </button>
                        <button type="submit" name="envoyer_demande" class="btn-submit">
                            <i class="fas fa-paper-plane"></i> Envoyer ma demande
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        /* ============================================================
           1. TOP BAR SCROLL + BACK TO TOP
        ============================================================ */
        const topBar = document.getElementById('topBar');
        const backToTop = document.getElementById('backToTop');

        window.addEventListener('scroll', function() {
            const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            if (scrollTop > 60) topBar.classList.add('scrolled');
            else topBar.classList.remove('scrolled');

            if (scrollTop > 500) backToTop.classList.add('visible');
            else backToTop.classList.remove('visible');
        });

        /* ============================================================
           2. REVEAL ON SCROLL + COUNTERS
        ============================================================ */
        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-visible');
                        if (entry.target.classList.contains('stat-box')) {
                            const counter = entry.target.querySelector('.counter');
                            if (counter && !counter.dataset.done) {
                                animateCounter(counter);
                                counter.dataset.done = '1';
                            }
                        }
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.15, rootMargin: '0px 0px -80px 0px' });

            document.querySelectorAll('.reveal').forEach(el => observer.observe(el));
        } else {
            document.querySelectorAll('.reveal').forEach(el => el.classList.add('is-visible'));
            document.querySelectorAll('.counter').forEach(c => {
                c.textContent = parseInt(c.dataset.target).toLocaleString('fr-FR');
            });
        }

        function animateCounter(el) {
            const target = parseInt(el.dataset.target) || 0;
            const duration = 1600;
            const start = performance.now();
            function step(now) {
                const elapsed = now - start;
                const progress = Math.min(elapsed / duration, 1);
                const eased = 1 - Math.pow(1 - progress, 3);
                el.textContent = Math.floor(eased * target).toLocaleString('fr-FR');
                if (progress < 1) requestAnimationFrame(step);
                else el.textContent = target.toLocaleString('fr-FR');
            }
            requestAnimationFrame(step);
        }

        /* ============================================================
           3. NAV PILLS ACTIVE STATE
        ============================================================ */
        const pills = document.querySelectorAll('.faculty-nav-pill');
        const sections = document.querySelectorAll('.faculty-section');

        if (sections.length > 0 && 'IntersectionObserver' in window) {
            const sectionObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const id = entry.target.id;
                        pills.forEach(p => {
                            p.classList.toggle('active', p.dataset.target === id);
                        });
                    }
                });
            }, { threshold: 0.4 });
            sections.forEach(s => sectionObserver.observe(s));
        }

        /* ============================================================
           4. MODAL OUVERTURE / FERMETURE
        ============================================================ */
        const modal = document.getElementById('modalPreinscription');

        // Fermer au clic sur l'overlay
        modal.addEventListener('click', function(e) {
            if (e.target === this) fermerPreinscription();
        });

        // Fermer avec la touche Échap
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modal.classList.contains('active')) {
                fermerPreinscription();
            }
        });

        // Bloquer le scroll du body quand modal ouverte
        const observerModal = new MutationObserver(() => {
            if (modal.classList.contains('active')) {
                document.body.style.overflow = 'hidden';
            } else {
                document.body.style.overflow = '';
            }
        });
        observerModal.observe(modal, { attributes: true, attributeFilter: ['class'] });
    });

    /* ============================================================
       FONCTIONS GLOBALES
    ============================================================ */
    function ouvrirPreinscription(idDept, nomDept, faculte) {
        document.getElementById('formIdDept').value = idDept;
        document.getElementById('formNomDept').value = nomDept;
        document.getElementById('formFaculte').value = faculte;
        document.getElementById('modalDeptName').textContent = nomDept;
        document.getElementById('modalPreinscription').classList.add('active');
        // Focus sur le premier champ
        setTimeout(() => {
            document.querySelector('#preinscriptionForm input[name="prenom"]').focus();
        }, 300);
    }

    function fermerPreinscription() {
        document.getElementById('modalPreinscription').classList.remove('active');
    }
    </script>

</body>
</html>