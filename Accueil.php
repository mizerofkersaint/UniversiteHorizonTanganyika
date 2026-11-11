<?php
require_once __DIR__ . '/db.php';
// ============================================================
// Accueil.php — Page d'accueil publique de l'Université
// Redesign professionnel — Institutionnel
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. CONNEXION À LA BASE DE DONNÉES
try {
    $bdd = getDBConnection();
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

/* =========================
   STATISTIQUES BASE DE DONNÉES
========================= */
$nbDepartements  = $bdd->query("SELECT COUNT(*) FROM departements")->fetchColumn() ?: 0;
$nbFacultes      = $bdd->query("SELECT COUNT(DISTINCT faculte) FROM departements WHERE faculte IS NOT NULL")->fetchColumn() ?: 0;
$nbEtudiants     = $bdd->query("SELECT COUNT(*) FROM etudiants")->fetchColumn() ?: 0;
$nbProfesseurs   = $bdd->query("SELECT COUNT(*) FROM professeurs")->fetchColumn() ?: 0;
$nbCours         = $bdd->query("SELECT COUNT(*) FROM cours")->fetchColumn() ?: 0;
$nbPaiements     = $bdd->query("SELECT COUNT(*) FROM paiements")->fetchColumn() ?: 0;
$totalRecettes   = $bdd->query("SELECT SUM(montant) FROM paiements")->fetchColumn() ?: 0;

// Lien vers login ou dashboard selon la session
$is_connected = isset($_SESSION['id_user']) && (int)$_SESSION['id_user'] > 0;
$dashboards = [
    'Administrateur' => 'administrateur.php',
    'Recteur'        => 'dashboard_recteur.php',
    'Doyen'          => 'dashboard_doyen.php',
    'Comptable'      => 'dashboard_comptable.php',
    'Professeur'     => 'dashboard_professeur.php',
    'Etudiant'       => 'dashboard_etudiant.php',
    'Agent'          => 'dashboard_agent.php'
];
$dashboard_url = $is_connected
    ? ($dashboards[$_SESSION['role'] ?? ''] ?? 'login.php')
    : 'login.php';
$btn_label = $is_connected ? 'Mon espace' : 'Accéder au système';
$btn_icon  = $is_connected ? 'fa-sign-in-alt' : 'fa-rocket';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Université du Lac Tanganyika — Institution d'excellence à Bujumbura, Burundi. Découvrez nos facultés, départements et programmes académiques.">
    <meta name="keywords" content="université, facultés, Bujumbura, Burundi, enseignement supérieur, formation">
    <title>Université Horizon Tanganyika (UHT) — Excellence · Innovation · Réussite</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        /* ============================================================
           VARIABLES (identiques à facultes.php)
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
            --success: #10b981;
            --primary: #6366f1;

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
           PROGRESS BAR
           ============================================================ */
        .progress-bar {
            position: fixed;
            top: 0; left: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--gold), var(--gold-light));
            z-index: 9999;
            transition: width 0.1s linear;
            box-shadow: 0 0 10px rgba(201, 165, 92, 0.5);
        }

        /* ============================================================
           TOP BAR
           ============================================================ */
        .top-bar {
            position: fixed;
            top: 0; left: 0; right: 0;
            padding: 20px 40px;
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

        .nav-links {
            display: flex;
            gap: 32px;
            list-style: none;
            align-items: center;
        }
        .nav-links a {
            color: rgba(255, 255, 255, 0.85);
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.2s var(--ease);
            position: relative;
        }
        .nav-links a::after {
            content: '';
            position: absolute;
            bottom: -6px;
            left: 0; right: 0;
            height: 2px;
            background: var(--gold);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.3s var(--ease);
        }
        .nav-links a:hover { color: var(--white); }
        .nav-links a:hover::after { transform: scaleX(1); }

        .top-actions {
            display: flex;
            gap: 12px;
            align-items: center;
        }
        .access-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 11px 22px;
            background: linear-gradient(135deg, var(--gold), var(--gold-light));
            color: var(--navy);
            text-decoration: none;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 700;
            transition: all 0.3s var(--ease);
            box-shadow: 0 4px 14px rgba(201, 165, 92, 0.35);
        }
        .access-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(201, 165, 92, 0.5);
        }

        .mobile-toggle {
            display: none;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.25);
            color: var(--white);
            width: 42px;
            height: 42px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 16px;
        }

        /* ============================================================
           HERO
           ============================================================ */
        .hero {
            position: relative;
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 120px 40px 80px;
            overflow: hidden;
            color: var(--white);
        }
        .hero-bg {
            position: absolute;
            inset: 0;
            background-image: url('univ.jpg'), url('https://images.unsplash.com/photo-1541339907198-e08756dedf3f?w=1920&q=85&auto=format&fit=crop');
            background-size: cover;
            background-position: center;
            transform: scale(1.1);
            animation: slowZoom 25s ease-in-out infinite alternate;
        }
        @keyframes slowZoom {
            0%   { transform: scale(1.1); }
            100% { transform: scale(1.18); }
        }
        .hero-overlay {
            position: absolute;
            inset: 0;
            background:
                linear-gradient(105deg, rgba(6, 17, 39, 0.92) 0%, rgba(11, 30, 61, 0.75) 45%, rgba(11, 30, 61, 0.4) 100%),
                linear-gradient(180deg, transparent 0%, rgba(6, 17, 39, 0.3) 70%, rgba(6, 17, 39, 0.9) 100%);
        }

        .hero-inner {
            position: relative;
            z-index: 2;
            max-width: 1300px;
            margin: 0 auto;
            width: 100%;
            display: grid;
            grid-template-columns: 1.3fr 1fr;
            gap: 80px;
            align-items: center;
        }

        .hero-content {
            animation: heroFade 1.2s var(--ease) both;
        }
        @keyframes heroFade {
            from { opacity: 0; transform: translateY(40px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .hero-pretitle {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 4px;
            color: var(--gold);
            margin-bottom: 24px;
        }
        .hero-pretitle::before {
            content: '';
            width: 40px;
            height: 1px;
            background: var(--gold);
        }

        .hero-title {
            font-family: var(--font-display);
            font-size: clamp(38px, 5.5vw, 72px);
            font-weight: 800;
            line-height: 1.05;
            letter-spacing: -1.5px;
            margin-bottom: 24px;
            background: linear-gradient(135deg, #ffffff 0%, #f5e9d3 45%, #c9a55c 100%);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 0 4px 30px rgba(0, 0, 0, 0.2);
        }

        .hero-subtitle {
            font-size: 17px;
            color: rgba(255, 255, 255, 0.85);
            max-width: 540px;
            margin-bottom: 40px;
            line-height: 1.7;
        }

        .hero-actions {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
        }

        .hero-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 16px 32px;
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

        /* Card flottante hero (facultés) */
        .hero-side-card {
            background: rgba(255, 255, 255, 0.06);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 20px;
            padding: 32px;
            animation: heroFade 1.4s var(--ease) 0.2s both;
        }
        .hero-side-card h3 {
            font-family: var(--font-display);
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 8px;
            color: var(--white);
        }
        .hero-side-card p {
            font-size: 13px;
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 24px;
        }
        .hero-side-card .quick-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.15);
        }
        .hero-side-card .quick-stat {
            text-align: left;
        }
        .hero-side-card .quick-stat .val {
            font-family: var(--font-display);
            font-size: 32px;
            font-weight: 800;
            color: var(--gold);
            line-height: 1;
        }
        .hero-side-card .quick-stat .lbl {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: rgba(255, 255, 255, 0.6);
            font-weight: 700;
            margin-top: 4px;
        }
        .hero-side-card .link-fac {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 24px;
            padding: 14px 20px;
            background: rgba(201, 165, 92, 0.15);
            border: 1px solid rgba(201, 165, 92, 0.4);
            border-radius: 12px;
            color: var(--gold-light);
            text-decoration: none;
            font-size: 13px;
            font-weight: 700;
            transition: all 0.3s var(--ease);
        }
        .hero-side-card .link-fac:hover {
            background: var(--gold);
            color: var(--navy);
            border-color: var(--gold);
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
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 2px;
            animation: scrollBounce 2s infinite;
        }
        .scroll-hint i { font-size: 16px; color: var(--gold); }
        @keyframes scrollBounce {
            0%, 100% { transform: translateX(-50%) translateY(0); }
            50%      { transform: translateX(-50%) translateY(8px); }
        }

        /* ============================================================
           SECTION : STATS EN BANDEAU
           ============================================================ */
        .stats-strip {
            background: var(--navy-deep);
            padding: 60px 20px;
            position: relative;
            overflow: hidden;
        }
        .stats-strip::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--gold), transparent);
        }
        .stats-strip-inner {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 40px;
            text-align: center;
        }
        .strip-item {
            position: relative;
        }
        .strip-item::after {
            content: '';
            position: absolute;
            right: -20px;
            top: 20%;
            height: 60%;
            width: 1px;
            background: rgba(201, 165, 92, 0.2);
        }
        .strip-item:last-child::after { display: none; }
        .strip-value {
            font-family: var(--font-display);
            font-size: clamp(32px, 4vw, 48px);
            font-weight: 800;
            color: var(--gold);
            line-height: 1;
            letter-spacing: -1px;
            margin-bottom: 8px;
        }
        .strip-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: rgba(255, 255, 255, 0.6);
            font-weight: 700;
        }

        /* ============================================================
           SECTION : INTRO / MISSION
           ============================================================ */
        .mission-section {
            padding: 100px 20px;
            background: var(--white);
        }
        .mission-inner {
            max-width: 1100px;
            margin: 0 auto;
            text-align: center;
        }
        .section-eyebrow {
            display: inline-block;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 4px;
            color: var(--gold);
            margin-bottom: 20px;
        }
        .section-title {
            font-family: var(--font-display);
            font-size: clamp(30px, 4vw, 46px);
            font-weight: 700;
            line-height: 1.15;
            color: var(--navy);
            letter-spacing: -0.5px;
            margin-bottom: 24px;
        }
        .section-title em {
            font-style: italic;
            color: var(--gold-dark);
        }
        .section-desc {
            font-size: 17px;
            color: var(--text-muted);
            max-width: 780px;
            margin: 0 auto;
            line-height: 1.75;
        }
        .section-desc + .section-desc {
            margin-top: 16px;
        }

        /* ============================================================
           SECTION : FACULTÉS (aperçu)
           ============================================================ */
        .facultes-section {
            padding: 100px 20px;
            background: var(--cream);
        }
        .facultes-header {
            max-width: 1200px;
            margin: 0 auto 60px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 30px;
            flex-wrap: wrap;
        }
        .facultes-header-text {
            flex: 1;
            min-width: 300px;
        }
        .facultes-header .link-all {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 14px 26px;
            background: var(--navy);
            color: var(--white);
            text-decoration: none;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 700;
            transition: all 0.3s var(--ease);
            white-space: nowrap;
        }
        .facultes-header .link-all:hover {
            background: var(--gold);
            color: var(--navy);
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(201, 165, 92, 0.4);
        }

        .facultes-grid {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 24px;
        }

        .fac-card {
            position: relative;
            background: var(--white);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
            transition: all 0.35s var(--ease);
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
        }
        .fac-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-xl);
            border-color: var(--gold);
        }
        .fac-card-top {
            padding: 28px 24px;
            background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%);
            color: var(--white);
            position: relative;
            overflow: hidden;
        }
        .fac-card-top::before {
            content: '';
            position: absolute;
            top: -40%; right: -20%;
            width: 180px; height: 180px;
            background: radial-gradient(circle, rgba(201, 165, 92, 0.25), transparent 70%);
            border-radius: 50%;
        }
        .fac-card-icon {
            position: relative;
            z-index: 1;
            width: 52px;
            height: 52px;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--gold), var(--gold-light));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: var(--navy);
            margin-bottom: 16px;
            box-shadow: 0 6px 18px rgba(201, 165, 92, 0.4);
        }
        .fac-card-title {
            position: relative;
            z-index: 1;
            font-family: var(--font-display);
            font-size: 20px;
            font-weight: 700;
            line-height: 1.25;
            margin-bottom: 4px;
        }
        .fac-card-sub {
            position: relative;
            z-index: 1;
            font-size: 12px;
            color: rgba(255, 255, 255, 0.75);
            font-weight: 500;
            letter-spacing: 0.5px;
        }

        .fac-card-body {
            padding: 20px 24px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .fac-card-stats {
            display: flex;
            flex-direction: column;
            gap: 6px;
            font-size: 12px;
            color: var(--text-muted);
        }
        .fac-card-stats span {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-weight: 600;
        }
        .fac-card-stats i {
            color: var(--gold);
            font-size: 11px;
            width: 14px;
        }
        .fac-card-arrow {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: var(--cream);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            color: var(--navy);
            transition: all 0.3s var(--ease);
        }
        .fac-card:hover .fac-card-arrow {
            background: var(--gold);
            color: var(--navy);
            transform: translateX(4px);
        }

        /* Empty state */
        .fac-empty {
            grid-column: 1 / -1;
            text-align: center;
            padding: 60px 30px;
            background: var(--white);
            border: 2px dashed var(--border);
            border-radius: 16px;
            color: var(--text-muted);
        }
        .fac-empty i {
            font-size: 48px;
            color: var(--gold);
            opacity: 0.5;
            margin-bottom: 16px;
            display: block;
        }

        /* ============================================================
           SECTION : POINTS FORTS
           ============================================================ */
        .features-section {
            padding: 100px 20px;
            background: var(--white);
        }
        .features-grid {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 32px;
            margin-top: 60px;
        }
        .feature-card {
            padding: 40px 32px;
            background: var(--cream);
            border-radius: 16px;
            border: 1px solid var(--border);
            transition: all 0.3s var(--ease);
            position: relative;
            overflow: hidden;
        }
        .feature-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--gold), var(--gold-light));
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.4s var(--ease);
        }
        .feature-card:hover {
            transform: translateY(-6px);
            box-shadow: var(--shadow-lg);
            border-color: var(--gold);
        }
        .feature-card:hover::before { transform: scaleX(1); }

        .feature-icon {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            background: linear-gradient(135deg, var(--navy), var(--navy-light));
            color: var(--gold);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 24px;
        }
        .feature-title {
            font-family: var(--font-display);
            font-size: 20px;
            font-weight: 700;
            color: var(--navy);
            margin-bottom: 12px;
        }
        .feature-desc {
            font-size: 14px;
            color: var(--text-muted);
            line-height: 1.7;
        }

        /* ============================================================
           SECTION : INFO CARDS (moyenne, année, taux, cours)
           ============================================================ */
        .info-section {
            padding: 80px 20px;
            background: var(--navy-deep);
            position: relative;
            overflow: hidden;
        }
        .info-section::before {
            content: '';
            position: absolute;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            width: 600px; height: 600px;
            background: radial-gradient(circle, rgba(201, 165, 92, 0.08), transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }
        .info-grid {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 24px;
            position: relative;
            z-index: 1;
        }
        .info-card {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(201, 165, 92, 0.2);
            border-radius: 16px;
            padding: 28px;
            display: flex;
            align-items: center;
            gap: 20px;
            transition: all 0.3s var(--ease);
        }
        .info-card:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: var(--gold);
            transform: translateY(-4px);
        }
        .info-icon {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--gold), var(--gold-light));
            color: var(--navy);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
            box-shadow: 0 6px 18px rgba(201, 165, 92, 0.35);
        }
        .info-content {
            flex: 1;
        }
        .info-title {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.65);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 4px;
        }
        .info-value {
            font-family: var(--font-display);
            font-size: 24px;
            font-weight: 800;
            color: var(--white);
            line-height: 1;
        }
        .info-desc {
            font-size: 11px;
            color: rgba(255, 255, 255, 0.5);
            margin-top: 4px;
        }

        /* ============================================================
           SECTION : CTA FINAL
           ============================================================ */
        .cta-section {
            padding: 100px 20px;
            background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%);
            text-align: center;
            color: var(--white);
            position: relative;
            overflow: hidden;
        }
        .cta-section::before {
            content: '';
            position: absolute;
            top: -50%; right: -10%;
            width: 500px; height: 500px;
            background: radial-gradient(circle, rgba(201, 165, 92, 0.18), transparent 70%);
            border-radius: 50%;
        }
        .cta-inner {
            position: relative;
            z-index: 2;
            max-width: 800px;
            margin: 0 auto;
        }
        .cta-eyebrow {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 4px;
            color: var(--gold);
            margin-bottom: 20px;
        }
        .cta-title {
            font-family: var(--font-display);
            font-size: clamp(30px, 4.5vw, 52px);
            font-weight: 700;
            line-height: 1.15;
            margin-bottom: 20px;
            letter-spacing: -1px;
        }
        .cta-title span { color: var(--gold); }
        .cta-desc {
            font-size: 17px;
            color: rgba(255, 255, 255, 0.8);
            line-height: 1.7;
            margin-bottom: 40px;
        }
        .cta-actions {
            display: flex;
            gap: 16px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .cta-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 16px 32px;
            border-radius: 60px;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            text-decoration: none;
            transition: all 0.3s var(--ease);
        }
        .cta-btn.primary {
            background: linear-gradient(135deg, var(--gold), var(--gold-light));
            color: var(--navy);
            box-shadow: 0 8px 24px rgba(201, 165, 92, 0.4);
        }
        .cta-btn.primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 32px rgba(201, 165, 92, 0.55);
        }
        .cta-btn.secondary {
            background: rgba(255, 255, 255, 0.1);
            color: var(--white);
            border: 1px solid rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(8px);
        }
        .cta-btn.secondary:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-3px);
        }

        /* ============================================================
           FOOTER
           ============================================================ */
        .footer {
            background: var(--navy-deep);
            color: rgba(255, 255, 255, 0.7);
            padding: 80px 20px 30px;
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
        .footer-grid {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 50px;
            padding-bottom: 40px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }
        .footer-brand {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .footer-brand-crest {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--gold), var(--gold-light));
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: var(--font-display);
            font-size: 20px;
            font-weight: 800;
            color: var(--navy);
            box-shadow: 0 0 0 4px rgba(201, 165, 92, 0.15);
        }
        .footer-brand h3 {
            font-family: var(--font-display);
            font-size: 20px;
            font-weight: 700;
            color: var(--white);
            line-height: 1.3;
        }
        .footer-brand p {
            font-size: 13px;
            line-height: 1.7;
            color: rgba(255, 255, 255, 0.6);
        }

        .footer-col h4 {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: var(--gold);
            margin-bottom: 20px;
        }
        .footer-col ul {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .footer-col ul li a,
        .footer-col ul li span {
            color: rgba(255, 255, 255, 0.65);
            text-decoration: none;
            font-size: 13px;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .footer-col ul li a:hover { color: var(--gold); }
        .footer-col ul li i { color: var(--gold); font-size: 11px; width: 14px; }

        .footer-stats-mini {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .footer-stats-mini .row {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
        }
        .footer-stats-mini .row span:first-child { color: rgba(255, 255, 255, 0.55); }
        .footer-stats-mini .row span:last-child {
            color: var(--gold);
            font-weight: 700;
        }

        .footer-bottom {
            max-width: 1200px;
            margin: 30px auto 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            font-size: 12px;
            color: rgba(255, 255, 255, 0.4);
        }
        .footer-bottom a {
            color: var(--gold);
            text-decoration: none;
            font-weight: 600;
        }
        .footer-bottom a:hover { text-decoration: underline; }

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
            background: linear-gradient(135deg, var(--gold), var(--gold-light));
            color: var(--navy);
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            box-shadow: 0 8px 24px rgba(201, 165, 92, 0.5);
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
            transform: translateY(-4px);
            box-shadow: 0 12px 32px rgba(201, 165, 92, 0.7);
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
            .hero-inner {
                grid-template-columns: 1fr;
                gap: 60px;
            }
            .nav-links { gap: 20px; }
            .footer-grid {
                grid-template-columns: 1fr 1fr;
            }
        }
        @media (max-width: 768px) {
            .top-bar { padding: 14px 18px; }
            .top-bar.scrolled { padding: 10px 18px; }
            .brand-name { font-size: 15px; }
            .brand-tagline { display: none; }
            .brand-crest { width: 40px; height: 40px; font-size: 15px; }
            .nav-links { display: none; }
            .mobile-toggle { display: flex; align-items: center; justify-content: center; }
            .access-btn { padding: 9px 16px; font-size: 12px; }
            .hero { padding: 100px 20px 60px; min-height: auto; }
            .hero-title { font-size: 36px; letter-spacing: -1px; }
            .hero-actions { flex-direction: column; }
            .hero-btn { width: 100%; max-width: 320px; justify-content: center; }
            .stats-strip { padding: 40px 20px; }
            .strip-item::after { display: none; }
            .mission-section,
            .facultes-section,
            .features-section,
            .cta-section { padding: 70px 20px; }
            .info-section { padding: 60px 20px; }
            .facultes-header { flex-direction: column; align-items: flex-start; }
            .footer-grid { grid-template-columns: 1fr; gap: 40px; }
            .footer-bottom { flex-direction: column; text-align: center; }
            .back-to-top { bottom: 20px; right: 20px; width: 44px; height: 44px; }
        }
        @media (max-width: 480px) {
            .brand-text { display: none; }
            .stats-strip-inner { grid-template-columns: 1fr 1fr; gap: 30px; }
            .cta-actions { flex-direction: column; }
            .cta-btn { width: 100%; justify-content: center; }
            .hero-side-card .quick-stats { grid-template-columns: 1fr 1fr; }
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

    <!-- Progress bar -->
    <div class="progress-bar" id="progressBar"></div>

    <!-- ============================================================
         TOP BAR
    ============================================================ -->
    <header class="top-bar" id="topBar">
        <a href="Accueil.php" class="brand">
            <div class="brand-crest">ULT</div>
            <div class="brand-text">
                <span class="brand-name">Université Horizon Tanganyika (UHT)</span>
                <span class="brand-tagline">Excellence • Innovation • Réussite</span>
            </div>
        </a>
        <nav class="nav-links" id="navLinks">
            <a href="#mission">Notre mission</a>
            <a href="#facultes">Nos facultés</a>
            <a href="#points-forts">Points forts</a>
            <a href="#contact">Contact</a>
        </nav>
        <div class="top-actions">
            <a href="<?= htmlspecialchars($dashboard_url); ?>" class="access-btn">
                <i class="fas <?= $btn_icon; ?>"></i>
                <span><?= $btn_label; ?></span>
            </a>
            <button class="mobile-toggle" onclick="toggleMobileMenu()" aria-label="Menu">
                <i class="fas fa-bars" id="menuIcon"></i>
            </button>
        </div>
    </header>

    <!-- ============================================================
         HERO
    ============================================================ -->
    <section class="hero">
        <div class="hero-bg"></div>
        <div class="hero-overlay"></div>

        <div class="hero-inner">
            <div class="hero-content">
                <div class="hero-pretitle">Bienvenue à l'UHT </div>
                <h1 class="hero-title">
                    L'excellence académique<br>
                    au cœur du Burundi
                </h1>
                <p class="hero-subtitle">
                    Une institution publique dédiée à la formation des leaders de demain. Découvrez nos <?= number_format($nbFacultes); ?> facultés, ses départements spécialisés et ses programmes d'excellence.
                </p>
                <div class="hero-actions">
                    <a href="facultes.php" class="hero-btn primary">
                        <i class="fas fa-university"></i> Voir les facultés
                    </a>
                    <a href="<?= htmlspecialchars($dashboard_url); ?>" class="hero-btn secondary">
                        <i class="fas <?= $btn_icon; ?>"></i> <?= $btn_label; ?>
                    </a>
                </div>
            </div>

            <div class="hero-side-card">
                <h3>L'Université en bref</h3>
                <p>Une communauté académique engagée pour la réussite de chaque étudiant.</p>
                <div class="quick-stats">
                    <div class="quick-stat">
                        <div class="val counter" data-target="<?= $nbEtudiants; ?>">0</div>
                        <div class="lbl">Étudiants</div>
                    </div>
                    <div class="quick-stat">
                        <div class="val counter" data-target="<?= $nbProfesseurs; ?>">0</div>
                        <div class="lbl">Professeurs</div>
                    </div>
                    <div class="quick-stat">
                        <div class="val counter" data-target="<?= $nbFacultes; ?>">0</div>
                        <div class="lbl">Facultés</div>
                    </div>
                    <div class="quick-stat">
                        <div class="val counter" data-target="<?= $nbCours; ?>">0</div>
                        <div class="lbl">Cours</div>
                    </div>
                </div>
                <a href="facultes.php" class="link-fac">
                    <span>Explorer nos facultés</span>
                    <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>

        <div class="scroll-hint">
            <span>Défiler</span>
            <i class="fas fa-chevron-down"></i>
        </div>
    </section>

    <!-- ============================================================
         STATS STRIP
    ============================================================ -->
    <section class="stats-strip">
        <div class="stats-strip-inner">
            <div class="strip-item">
                <div class="strip-value counter" data-target="<?= $nbEtudiants; ?>">0</div>
                <div class="strip-label">Étudiants inscrits</div>
            </div>
            <div class="strip-item">
                <div class="strip-value counter" data-target="<?= $nbProfesseurs; ?>">0</div>
                <div class="strip-label">Professeurs</div>
            </div>
            <div class="strip-item">
                <div class="strip-value counter" data-target="<?= $nbDepartements; ?>">0</div>
                <div class="strip-label">Départements</div>
            </div>
            <div class="strip-item">
                <div class="strip-value counter" data-target="<?= $nbCours; ?>">0</div>
                <div class="strip-label">Cours proposés</div>
            </div>
            <div class="strip-item">
                <div class="strip-value counter" data-target="<?= $nbFacultes; ?>">0</div>
                <div class="strip-label">Facultés</div>
            </div>
        </div>
    </section>

    <!-- ============================================================
         MISSION
    ============================================================ -->
    <section class="mission-section" id="mission">
        <div class="mission-inner reveal">
            <div class="section-eyebrow">Notre mission</div>
            <h2 class="section-title">
                Former les <em>leaders</em> de demain,<br>
                au service du Burundi et du monde
            </h2>
            <p class="section-desc">
                Depuis sa création, l'Université du Lac Tanganyika s'engage à offrir une formation d'excellence, accessible et tournée vers l'innovation. Nos programmes sont conçus pour répondre aux défis contemporains et aux besoins du marché du travail.
            </p>
            <p class="section-desc">
                Nos <?= number_format($nbFacultes); ?> facultés et <?= number_format($nbDepartements); ?> départements couvrent l'ensemble des grands domaines du savoir, de la médecine aux sciences humaines, en passant par le droit, l'ingénierie et la gestion.
            </p>
        </div>
    </section>

    <!-- ============================================================
         APERÇU DES FACULTÉS
    ============================================================ -->
    <section class="facultes-section" id="facultes">
        <div class="facultes-header">
            <div class="facultes-header-text reveal">
                <div class="section-eyebrow">Nos formations</div>
                <h2 class="section-title" style="margin-bottom:0;">
                    Explorez nos facultés
                </h2>
            </div>
            <a href="facultes.php" class="link-all reveal">
                Voir toutes les facultés <i class="fas fa-arrow-right"></i>
            </a>
        </div>

        <div class="facultes-grid">
            <?php
            // Récupération des facultés pour l'aperçu (3 premières)
            try {
                $facultesApercu = $bdd->query("
                    SELECT 
                        d.faculte,
                        COUNT(DISTINCT d.id_dept) as nb_departements,
                        COUNT(DISTINCT cl.id_classe) as nb_classes,
                        COUNT(DISTINCT e.id_etudiant) as nb_etudiants
                    FROM departements d
                    LEFT JOIN classes cl ON d.id_dept = cl.id_dept
                    LEFT JOIN etudiants e ON cl.id_classe = e.id_classe
                    WHERE d.faculte IS NOT NULL AND d.faculte != ''
                    GROUP BY d.faculte
                    ORDER BY nb_etudiants DESC
                    LIMIT 3
                ")->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                $facultesApercu = [];
            }

            // Icônes par faculté (mêmes mots-clés que facultes.php)
            function facIcon($nom) {
                $lower = mb_strtolower($nom, 'UTF-8');
                if (strpos($lower, 'droit') !== false)        return ['fa-gavel', 'Justice & Éthique'];
                if (strpos($lower, 'médecine') !== false || strpos($lower, 'medecine') !== false) return ['fa-stethoscope', 'Santé & Bien-être'];
                if (strpos($lower, 'gestion') !== false)      return ['fa-chart-line', 'Management & Économie'];
                if (strpos($lower, 'lettres') !== false)      return ['fa-book-open', 'Culture & Humanités'];
                if (strpos($lower, 'politique') !== false)    return ['fa-landmark', 'Gouvernance & Société'];
                if (strpos($lower, 'ingéni') !== false || strpos($lower, 'ingeni') !== false) return ['fa-cogs', 'Ingénierie & Technologie'];
                if (strpos($lower, 'science') !== false)      return ['fa-flask', 'Sciences & Découvertes'];
                return ['fa-university', 'Excellence académique'];
            }

            if (!empty($facultesApercu)):
                foreach ($facultesApercu as $fa):
                    list($icon, $tagline) = facIcon($fa['faculte']);
            ?>
                    <a href="facultes.php#faculty-<?= array_search($fa, $facultesApercu); ?>" class="fac-card reveal">
                        <div class="fac-card-top">
                            <div class="fac-card-icon"><i class="fas <?= $icon; ?>"></i></div>
                            <div class="fac-card-title"><?= htmlspecialchars($fa['faculte']); ?></div>
                            <div class="fac-card-sub"><?= htmlspecialchars($tagline); ?></div>
                        </div>
                        <div class="fac-card-body">
                            <div class="fac-card-stats">
                                <span><i class="fas fa-sitemap"></i> <?= (int)$fa['nb_departements']; ?> départements</span>
                                <span><i class="fas fa-users"></i> <?= (int)$fa['nb_etudiants']; ?> étudiants</span>
                            </div>
                            <div class="fac-card-arrow"><i class="fas fa-arrow-right"></i></div>
                        </div>
                    </a>
                <?php endforeach;
            else: ?>
                <div class="fac-empty">
                    <i class="fas fa-university"></i>
                    <p>Les facultés seront bientôt disponibles.</p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- ============================================================
         POINTS FORTS
    ============================================================ -->
    <section class="features-section" id="points-forts">
        <div style="max-width:1100px; margin:0 auto; text-align:center;" class="reveal">
            <div class="section-eyebrow">Pourquoi nous choisir</div>
            <h2 class="section-title">
                Une université <em>d'excellence</em><br>
                tournée vers l'avenir
            </h2>
            <p class="section-desc">
                Nous offrons un environnement académique stimulant, des infrastructures modernes et un accompagnement personnalisé pour chaque étudiant.
            </p>
        </div>

        <div class="features-grid">
            <div class="feature-card reveal" style="transition-delay: 0.1s;">
                <div class="feature-icon"><i class="fas fa-graduation-cap"></i></div>
                <h3 class="feature-title">Formation d'excellence</h3>
                <p class="feature-desc">Des programmes rigoureux, dispensés par des professeurs hautement qualifiés et reconnus dans leurs domaines respectifs.</p>
            </div>
            <div class="feature-card reveal" style="transition-delay: 0.2s;">
                <div class="feature-icon"><i class="fas fa-globe-africa"></i></div>
                <h3 class="feature-title">Ouverture internationale</h3>
                <p class="feature-desc">Des partenariats avec des universités africaines et internationales, offrant des opportunités d'échange et de recherche.</p>
            </div>
            <div class="feature-card reveal" style="transition-delay: 0.3s;">
                <div class="feature-icon"><i class="fas fa-flask"></i></div>
                <h3 class="feature-title">Recherche & Innovation</h3>
                <p class="feature-desc">Un pôle de recherche actif, des laboratoires modernes et une culture de l'innovation au service de la société.</p>
            </div>
            <div class="feature-card reveal" style="transition-delay: 0.4s;">
                <div class="feature-icon"><i class="fas fa-users"></i></div>
                <h3 class="feature-title">Communauté soudée</h3>
                <p class="feature-desc">Une vie étudiante riche : associations, événements culturels et sportifs, un réseau actif d'anciens élèves.</p>
            </div>
            <div class="feature-card reveal" style="transition-delay: 0.5s;">
                <div class="feature-icon"><i class="fas fa-briefcase"></i></div>
                <h3 class="feature-title">Insertion professionnelle</h3>
                <p class="feature-desc">Des stages, un accompagnement vers l'emploi et un réseau d'entreprises partenaires pour faciliter votre insertion.</p>
            </div>
            <div class="feature-card reveal" style="transition-delay: 0.6s;">
                <div class="feature-icon"><i class="fas fa-hand-holding-heart"></i></div>
                <h3 class="feature-title">Accompagnement social</h3>
                <p class="feature-desc">Bourses, aides sociales et services d'orientation pour garantir l'égalité des chances pour tous.</p>
            </div>
        </div>
    </section>

    <!-- ============================================================
         INFO CARDS (moyenne, année, taux, cours)
    ============================================================ -->
    <section class="info-section">
        <div class="info-grid">
            <div class="info-card reveal">
                <div class="info-icon"><i class="fas fa-chart-line"></i></div>
                <div class="info-content">
                    <div class="info-title">Moyenne générale</div>
                    <div class="info-value">14.2/20</div>
                    <div class="info-desc">Performance académique</div>
                </div>
            </div>
            <div class="info-card reveal" style="transition-delay: 0.1s;">
                <div class="info-icon"><i class="fas fa-calendar-alt"></i></div>
                <div class="info-content">
                    <div class="info-title">Année académique</div>
                    <div class="info-value">2025-2026</div>
                    <div class="info-desc">Session en cours</div>
                </div>
            </div>
            <div class="info-card reveal" style="transition-delay: 0.2s;">
                <div class="info-icon"><i class="fas fa-award"></i></div>
                <div class="info-content">
                    <div class="info-title">Taux de réussite</div>
                    <div class="info-value">78.5%</div>
                    <div class="info-desc">Moyenne globale</div>
                </div>
            </div>
            <div class="info-card reveal" style="transition-delay: 0.3s;">
                <div class="info-icon"><i class="fas fa-book-open"></i></div>
                <div class="info-content">
                    <div class="info-title">Cours actifs</div>
                    <div class="info-value"><?= number_format($nbCours); ?></div>
                    <div class="info-desc">Enseignements en cours</div>
                </div>
            </div>
        </div>
    </section>

    <!-- ============================================================
         CTA FINAL
    ============================================================ -->
    <section class="cta-section" id="contact">
        <div class="cta-inner">
            <div class="cta-eyebrow">Rejoignez-nous</div>
            <h2 class="cta-title">
                Prêt à commencer votre <span>parcours</span> ?
            </h2>
            <p class="cta-desc">
                Que vous soyez futur étudiant, enseignant ou partenaire, notre équipe est à votre écoute. Découvrez nos programmes, rencontrez nos équipes et construisez votre avenir avec nous.
            </p>
            <div class="cta-actions">
                <a href="facultes.php" class="cta-btn primary">
                    <i class="fas fa-university"></i> Explorer les facultés
                </a>
                <a href="mailto:info@ulb.edu.bi" class="cta-btn secondary">
                    <i class="fas fa-envelope"></i> Nous contacter
                </a>
            </div>
        </div>
    </section>

    <!-- ============================================================
         FOOTER
    ============================================================ -->
    <footer class="footer">
        <div class="footer-grid">
            <div class="footer-brand">
                <div class="footer-brand-crest">ULT</div>
                <h3>Université du Lac Tanganyika</h3>
                <p>Institution publique d'enseignement supérieur dédiée à l'excellence académique, à la recherche et à l'innovation au service de la société.</p>
            </div>

            <div class="footer-col">
                <h4>Navigation</h4>
                <ul>
                    <li><a href="#mission"><i class="fas fa-chevron-right"></i> Notre mission</a></li>
                    <li><a href="facultes.php"><i class="fas fa-chevron-right"></i> Nos facultés</a></li>
                    <li><a href="#points-forts"><i class="fas fa-chevron-right"></i> Points forts</a></li>
                    <li><a href="#contact"><i class="fas fa-chevron-right"></i> Contact</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h4>Contact</h4>
                <ul>
                    <li><span><i class="fas fa-map-marker-alt"></i> Bujumbura, Burundi</span></li>
                    <li><a href="mailto:info@ulb.edu.bi"><i class="fas fa-envelope"></i> info@ulb.edu.bi</a></li>
                    <li><a href="tel:+25722220000"><i class="fas fa-phone"></i> +257 22 22 00 00</a></li>
                    <li><a href="login.php"><i class="fas fa-sign-in-alt"></i> Espace personnel</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h4>Statistiques</h4>
                <div class="footer-stats-mini">
                    <div class="row"><span>Départements</span><span><?= number_format($nbDepartements); ?></span></div>
                    <div class="row"><span>Facultés</span><span><?= number_format($nbFacultes); ?></span></div>
                    <div class="row"><span>Étudiants</span><span><?= number_format($nbEtudiants); ?></span></div>
                    <div class="row"><span>Professeurs</span><span><?= number_format($nbProfesseurs); ?></span></div>
                </div>
            </div>
        </div>

        <div class="footer-bottom">
            <div>© <?= date('Y'); ?> Université du Lac Tanganyika — Tous droits réservés</div>
            <div>Fait avec <i class="fas fa-heart" style="color:var(--gold);"></i> à Bujumbura, Burundi</div>
        </div>
    </footer>

    <!-- Back to top -->
    <button class="back-to-top" id="backToTop" onclick="window.scrollTo({top:0,behavior:'smooth'})" aria-label="Retour en haut">
        <i class="fas fa-arrow-up"></i>
    </button>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        /* ============================================================
           1. PROGRESS BAR + TOP BAR SCROLL + BACK TO TOP
        ============================================================ */
        const progressBar = document.getElementById('progressBar');
        const topBar = document.getElementById('topBar');
        const backToTop = document.getElementById('backToTop');

        window.addEventListener('scroll', function() {
            const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            const docHeight = document.documentElement.scrollHeight - document.documentElement.clientHeight;
            const progress = (scrollTop / docHeight) * 100;
            if (progressBar) progressBar.style.width = progress + '%';

            if (scrollTop > 60) topBar.classList.add('scrolled');
            else topBar.classList.remove('scrolled');

            if (scrollTop > 500) backToTop.classList.add('visible');
            else backToTop.classList.remove('visible');
        });

        /* ============================================================
           2. REVEAL ON SCROLL
        ============================================================ */
        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-visible');
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.12, rootMargin: '0px 0px -60px 0px' });
            document.querySelectorAll('.reveal').forEach(el => observer.observe(el));
        } else {
            document.querySelectorAll('.reveal').forEach(el => el.classList.add('is-visible'));
        }

        /* ============================================================
           3. COUNTERS ANIMATION
        ============================================================ */
        const counterObserver = 'IntersectionObserver' in window
            ? new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        animateCounter(entry.target);
                        counterObserver.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.5 })
            : null;

        document.querySelectorAll('.counter').forEach(c => {
            if (counterObserver) counterObserver.observe(c);
            else c.textContent = parseInt(c.dataset.target).toLocaleString('fr-FR');
        });

        function animateCounter(el) {
            const target = parseInt(el.dataset.target) || 0;
            const duration = 1800;
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
           4. SMOOTH SCROLL POUR LIENS INTERNES
        ============================================================ */
        document.querySelectorAll('a[href^="#"]').forEach(link => {
            link.addEventListener('click', function(e) {
                const href = this.getAttribute('href');
                if (href === '#') return;
                const target = document.querySelector(href);
                if (target) {
                    e.preventDefault();
                    const offset = 80;
                    const top = target.getBoundingClientRect().top + window.pageYOffset - offset;
                    window.scrollTo({ top, behavior: 'smooth' });
                    closeMobileMenu();
                }
            });
        });
    });

    /* ============================================================
       MOBILE MENU
    ============================================================ */
    function toggleMobileMenu() {
        const nav = document.getElementById('navLinks');
        const icon = document.getElementById('menuIcon');
        if (!nav) return;
        const isOpen = nav.style.display === 'flex';
        if (isOpen) {
            nav.style.display = 'none';
            if (icon) icon.className = 'fas fa-bars';
        } else {
            nav.style.display = 'flex';
            nav.style.position = 'absolute';
            nav.style.top = '70px';
            nav.style.left = '20px';
            nav.style.right = '20px';
            nav.style.background = 'rgba(11, 30, 61, 0.98)';
            nav.style.padding = '20px';
            nav.style.borderRadius = '16px';
            nav.style.flexDirection = 'column';
            nav.style.gap = '16px';
            nav.style.boxShadow = '0 12px 32px rgba(0,0,0,0.3)';
            if (icon) icon.className = 'fas fa-times';
        }
    }

    function closeMobileMenu() {
        const nav = document.getElementById('navLinks');
        const icon = document.getElementById('menuIcon');
        if (nav && window.innerWidth <= 768) {
            nav.style.display = '';
            if (icon) icon.className = 'fas fa-bars';
        }
    }
    </script>

</body>
</html>