<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'auth.php';

if ($USER_ROLE !== 'RECTEUR') {
    header('Location: login.php');
    exit();
}

// Statistiques spécifiques au Recteur
$totalEtudiants = $bdd->query("SELECT COUNT(*) FROM etudiants")->fetchColumn();
$totalDepartements = $bdd->query("SELECT COUNT(*) FROM departements")->fetchColumn();
$totalFacultes = $bdd->query("SELECT COUNT(DISTINCT faculte) FROM departements WHERE faculte IS NOT NULL")->fetchColumn();
$totalProfesseurs = $bdd->query("SELECT COUNT(*) FROM professeurs")->fetchColumn();
$totalRecettes = $bdd->query("SELECT SUM(montant) FROM paiements")->fetchColumn() ?: 0;
$tauxReussite = $bdd->query("SELECT ROUND((COUNT(CASE WHEN note >= 10 THEN 1 END) / COUNT(*)) * 100, 1) FROM notes")->fetchColumn() ?: 0;

$photo_profil = !empty($USER_PHOTO) && file_exists("uploads/" . $USER_PHOTO) ? $USER_PHOTO : 'default.png';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Profil — Recteur</title>
    <style>
        :root {
            --bg-light: #f8fafc;
            --sidebar-color: #0f172a;
            --text-main: #334155;
            --accent-blue: #2563eb;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --border-color: #e2e8f0;
            --role-recteur-gold: #d4af37;
            --radius: 12px;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', system-ui, sans-serif; }
        body { background-color: var(--bg-light); color: var(--text-main); display: flex; min-height: 100vh; }

        .sidebar {
            width: 270px;
            background-color: var(--sidebar-color);
            color: white;
            padding: 25px 20px;
            position: fixed;
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            border-right: 2px solid var(--role-recteur-gold);
        }
        .sidebar .logo { font-size: 22px; font-weight: 800; margin-bottom: 20px; text-align: center; }
        .user-profile-sidebar {
            background: rgba(255,255,255,0.05);
            padding: 15px;
            border-radius: var(--radius);
            text-align: center;
            margin-bottom: 20px;
            border: 1px solid rgba(255,255,255,0.06);
        }
        .profile-img-sidebar {
            width: 70px; height: 70px; border-radius: 50%; object-fit: cover;
            border: 3px solid var(--role-recteur-gold); margin-bottom: 10px;
        }
        .user-profile-sidebar .name { font-weight: 600; font-size: 15px; }
        .user-profile-sidebar .role { font-size: 12px; color: #94a3b8; }
        .badge-role { display: inline-block; padding: 3px 12px; border-radius: 12px; font-size: 10px; font-weight: 700; background: var(--role-recteur-gold); color: #0f172a; margin-top: 4px; }

        .nav-links { list-style: none; display: flex; flex-direction: column; gap: 4px; flex: 1; }
        .nav-links a {
            color: #94a3b8; text-decoration: none; padding: 10px 14px; border-radius: 8px;
            display: flex; align-items: center; gap: 10px; font-weight: 500; font-size: 14px;
            transition: all 0.2s;
        }
        .nav-links a:hover, .nav-links a.active {
            background: rgba(255,255,255,0.08); color: white;
        }
        .nav-links a.logout { color: var(--danger); margin-top: auto; padding-top: 15px; border-top: 1px solid rgba(255,255,255,0.06); }

        .main-content {
            margin-left: 270px;
            width: calc(100% - 270px);
            padding: 35px 40px;
            min-height: 100vh;
        }

        .page-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 30px; flex-wrap: wrap; gap: 15px;
        }
        .page-header h1 { font-size: 26px; color: #1e293b; }
        .page-header .badge { background: var(--role-recteur-gold); color: #0f172a; padding: 6px 16px; border-radius: 20px; font-weight: 600; font-size: 13px; }

        .profile-grid {
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 30px;
        }

        .card {
            background: white;
            border-radius: var(--radius);
            border: 1px solid var(--border-color);
            padding: 25px;
            box-shadow: var(--shadow-sm);
        }

        .card-title {
            font-size: 17px; font-weight: 700; color: #1e293b;
            margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid var(--border-color);
        }

        .photo-section { text-align: center; }
        .profile-img { width: 150px; height: 150px; border-radius: 50%; object-fit: cover; border: 4px solid var(--role-recteur-gold); margin-bottom: 15px; }
        .photo-section .name { font-size: 20px; font-weight: 700; }
        .photo-section .role-badge { display: inline-block; padding: 4px 16px; border-radius: 20px; background: #fef3c7; color: #92400e; font-weight: 600; font-size: 13px; margin-top: 4px; }

        .info-item {
            display: flex; justify-content: space-between; padding: 10px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .info-item:last-child { border-bottom: none; }
        .info-item .label { color: #64748b; font-weight: 500; }
        .info-item .value { font-weight: 600; color: #1e293b; }

        .stats-grid {
            display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: white;
            padding: 15px 18px;
            border-radius: var(--radius);
            border: 1px solid var(--border-color);
            text-align: center;
        }
        .stat-card .number { font-size: 22px; font-weight: 700; color: var(--role-recteur-gold); }
        .stat-card .label { font-size: 12px; color: #64748b; font-weight: 600; }

        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-weight: 600; font-size: 13px; color: #475569; margin-bottom: 4px; }
        .form-group input { width: 100%; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 14px; outline: none; }
        .form-group input:focus { border-color: var(--role-recteur-gold); }
        .form-group input[readonly] { background: #f1f5f9; color: #64748b; cursor: not-allowed; }

        .btn-primary { background: var(--role-recteur-gold); color: #0f172a; border: none; padding: 12px 24px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: background 0.2s; }
        .btn-primary:hover { background: #c4a02a; }
        .btn-success { background: var(--success); color: white; }
        .btn-success:hover { background: #059669; }

        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; }
        .alert.success { background: #d1fae5; color: #065f46; }
        .alert.error { background: #fee2e2; color: #991b1b; }

        @media (max-width: 1024px) { .profile-grid { grid-template-columns: 1fr; } .stats-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 768px) {
            body { flex-direction: column; }
            .sidebar { width: 100%; height: auto; position: relative; padding: 15px; }
            .main-content { margin-left: 0; width: 100%; padding: 20px; }
            .stats-grid { grid-template-columns: 1fr; }
            .page-header { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="logo">👑 UniVerse</div>
        <div class="user-profile-sidebar">
            <img src="uploads/<?= htmlspecialchars($photo_profil); ?>" alt="Photo" class="profile-img-sidebar">
            <p class="name">Prof. <?= htmlspecialchars($USER_NAME); ?></p>
            <p class="role">Recteur de l'Université</p>
            <span class="badge-role">👑 RECTEUR</span>
        </div>
        <ul class="nav-links">
            <li><a href="dashboard_recteur.php">🏛️ Tableau de Bord</a></li>
            <li><a href="profile_recteur.php" class="active">👤 Mon Profil</a></li>
            <li><a href="audit_finances.php">📊 Audit Financier</a></li>
            <li><a href="logout.php" class="logout">🚪 Déconnexion</a></li>
        </ul>
    </div>

    <div class="main-content">
        <div class="page-header">
            <h1>👤 Mon Profil — Rectorat</h1>
            <span class="badge">👑 Recteur</span>
        </div>

        <div class="profile-grid">
            <div>
                <div class="card photo-section">
                    <img src="uploads/<?= htmlspecialchars($photo_profil); ?>" alt="Photo" class="profile-img">
                    <div class="name">Prof. <?= htmlspecialchars($USER_NAME); ?></div>
                    <span class="role-badge">Recteur</span>
                    <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid var(--border-color);">
                        <a href="?action=changer_photo" class="btn-primary" style="font-size:13px; padding:8px 16px; color:#0f172a;">📷 Changer la photo</a>
                    </div>
                </div>

                <div class="card" style="margin-top:20px;">
                    <div class="card-title">📊 Indicateurs Stratégiques</div>
                    <div class="stats-grid" style="grid-template-columns:1fr 1fr; gap:10px;">
                        <div class="stat-card"><div class="number"><?= $totalEtudiants; ?></div><div class="label">Étudiants</div></div>
                        <div class="stat-card"><div class="number"><?= $totalDepartements; ?></div><div class="label">Départements</div></div>
                        <div class="stat-card"><div class="number"><?= $totalFacultes; ?></div><div class="label">Facultés</div></div>
                        <div class="stat-card"><div class="number"><?= $totalProfesseurs; ?></div><div class="label">Professeurs</div></div>
                        <div class="stat-card"><div class="number" style="color:var(--success);"><?= number_format($totalRecettes, 0, ',', ' '); ?> BIF</div><div class="label">Recettes</div></div>
                        <div class="stat-card"><div class="number" style="color:var(--accent-blue);"><?= $tauxReussite; ?>%</div><div class="label">Taux Réussite</div></div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-title">✏️ Modifier mes informations</div>
                <form method="POST" action="mon_profil.php" enctype="multipart/form-data">
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                        <div class="form-group">
                            <label>Nom</label>
                            <input type="text" name="nom" value="<?= htmlspecialchars($USER_NOM ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Prénom</label>
                            <input type="text" name="prenom" value="<?= htmlspecialchars($USER_PRENOM ?? ''); ?>" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($USER_EMAIL ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Téléphone</label>
                        <input type="text" name="telephone" value="<?= htmlspecialchars($USER_TELEPHONE ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Rôle (Lecture seule)</label>
                        <input type="text" value="Recteur" readonly>
                    </div>
                    <button type="submit" class="btn-primary" style="width:100%; color:#0f172a;">💾 Enregistrer les modifications</button>
                </form>

                <div style="margin-top:20px; padding-top:20px; border-top:1px solid var(--border-color);">
                    <div class="card-title">🔑 Sécurité</div>
                    <a href="changer_mot_de_passe.php" class="btn-primary btn-success" style="display:block; text-align:center; text-decoration:none; color:white;">🔑 Changer mot de passe</a>
                </div>
            </div>
        </div>
    </div>

</body>
</html>