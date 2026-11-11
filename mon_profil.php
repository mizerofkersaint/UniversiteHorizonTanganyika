<?php
require_once __DIR__ . '/db.php';
require_once 'auth.php';
requireRole(['Administrateur', 'Recteur', 'Doyen', 'Comptable', 'Professeur', 'Etudiant']);

try {
    $bdd = getDBConnection();
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

$id_user = $USER_ID;

// Définir le lien du dashboard selon le rôle
$dashboards = [
    'Administrateur' => 'administrateur.php',
    'Recteur'       => 'dashboard_recteur.php',
    'Doyen'         => 'dashboard_doyen.php',
    'Comptable'     => 'dashboard_comptable.php',
    'Professeur'    => 'dashboard_professeur.php',
    'Etudiant'      => 'dashboard_etudiant.php'
];
$dashboard_url = $dashboards[$USER_ROLE] ?? 'Accueil.php';

$message = "";

// ============================================================
// TRAITEMENT DE LA MISE À JOUR
// ============================================================
if (isset($_POST['action_modifier_profil'])) {
    $nom = trim($_POST['nom']);
    $prenom = trim($_POST['prenom']);
    $email = trim($_POST['email']);
    $telephone = trim($_POST['telephone']);

    try {
        $updUser = $bdd->prepare("UPDATE users SET nom = ?, prenom = ?, email = ?, telephone = ? WHERE id_user = ?");
        $updUser->execute([$nom, $prenom, $email, $telephone, $id_user]);

        // Gestion de la photo
        if (!empty($_FILES['photo']['name'])) {
            $extension = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            $extensions_autorisees = ['jpg', 'jpeg', 'png', 'webp'];
            if (in_array($extension, $extensions_autorisees)) {
                $nom_photo = "profile_" . $id_user . "." . $extension;
                if (move_uploaded_file($_FILES['photo']['tmp_name'], "uploads/" . $nom_photo)) {
                    $updPhoto = $bdd->prepare("UPDATE users SET photo = ? WHERE id_user = ?");
                    $updPhoto->execute([$nom_photo, $id_user]);
                    $_SESSION['photo'] = $nom_photo;
                }
            }
        }

        // Mise à jour selon le rôle
        if ($USER_ROLE === 'Etudiant' && isset($_POST['matricule'])) {
            $updEtud = $bdd->prepare("UPDATE etudiants SET matricule = ? WHERE id_user = ?");
            $updEtud->execute([trim($_POST['matricule']), $id_user]);
        } elseif (in_array($USER_ROLE, ['Professeur', 'Doyen', 'Recteur']) && isset($_POST['specialite'])) {
            $updProf = $bdd->prepare("UPDATE professeurs SET specialite = ?, diplome = ? WHERE id_user = ?");
            $updProf->execute([trim($_POST['specialite']), trim($_POST['diplome'] ?? ''), $id_user]);
        }

        $message = "<div class='alert success'>🎉 Les modifications ont été appliquées avec succès !</div>";
    } catch (Exception $e) {
        $message = "<div class='alert error'>❌ Erreur : " . $e->getMessage() . "</div>";
    }
}

// ============================================================
// CHARGEMENT DES DONNÉES
// ============================================================
$reqUser = $bdd->prepare("SELECT * FROM users WHERE id_user = ?");
$reqUser->execute([$id_user]);
$user = $reqUser->fetch(PDO::FETCH_ASSOC);

$infos_specifiques = [];

if ($USER_ROLE === 'Etudiant') {
    $reqEtud = $bdd->prepare("SELECT e.matricule, c.niveau AS nom_classe, d.nom_dept FROM etudiants e LEFT JOIN classes c ON e.id_classe = c.id_classe LEFT JOIN departements d ON c.id_dept = d.id_dept WHERE e.id_user = ?");
    $reqEtud->execute([$id_user]);
    $infos_specifiques = $reqEtud->fetch(PDO::FETCH_ASSOC) ?: [];
} elseif (in_array($USER_ROLE, ['Professeur', 'Doyen', 'Recteur'])) {
    $reqEns = $bdd->prepare("SELECT specialite, diplome FROM professeurs WHERE id_user = ?");
    $reqEns->execute([$id_user]);
    $infos_specifiques = $reqEns->fetch(PDO::FETCH_ASSOC) ?: [];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mon Profil</title>
    <link rel="stylesheet" href="assets/theme.css">
    <script src="assets/theme.js"></script>
    <style>
        :root { --bg-light: #f8fafc; --sidebar-color: #0f172a; --text-main: #334155; --accent-blue: #2563eb; --success: #10b981; --error: #ef4444; --border-color: #e2e8f0; }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
        body { background-color: var(--bg-light); color: var(--text-main); display: flex; min-height: 100vh; }
        
        .sidebar { width: 260px; background-color: var(--sidebar-color); color: white; padding: 30px 20px; position: fixed; height: 100vh; display: flex; flex-direction: column; }
        .sidebar .logo { font-size: 24px; font-weight: 800; margin-bottom: 30px; }
        .sidebar a { color: #94a3b8; text-decoration: none; padding: 12px; border-radius: 8px; display: block; font-weight: 600; margin-bottom: 10px; transition: 0.2s; }
        .sidebar a:hover, .sidebar a.active { background: rgba(255,255,255,0.1); color: white; }
        .sidebar .logout { color: var(--error); margin-top: auto; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 15px; }
        
        .main-content { margin-left: 260px; width: calc(100% - 260px); padding: 40px; }
        .profile-container { display: grid; grid-template-columns: 320px 1fr; gap: 30px; }
        
        .card { background: white; padding: 30px; border-radius: 16px; border: 1px solid var(--border-color); text-align: center; }
        .profile-xl { width: 130px; height: 130px; border-radius: 50%; object-fit: cover; margin-bottom: 15px; border: 3px solid var(--accent-blue); }
        .role-badge { display: inline-block; padding: 6px 12px; font-size: 12px; font-weight: 700; border-radius: 20px; background: #eff6ff; color: var(--accent-blue); margin-top: 5px; text-transform: uppercase; }
        
        .info-list { text-align: left; margin-top: 20px; border-top: 1px solid var(--border-color); padding-top: 15px; }
        .info-item { margin-bottom: 12px; font-size: 14px; color: #475569; }
        .info-item span { font-weight: 600; color: #1e293b; }

        .form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 15px; text-align: left; }
        label { font-size: 13px; font-weight: 600; color: #64748b; }
        input, select { padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border-color); font-size: 14px; width: 100%; background: #f8fafc; }
        input:focus { outline: none; border-color: var(--accent-blue); background: white; }
        
        .btn-submit { background: var(--accent-blue); color: white; padding: 12px; border-radius: 8px; font-weight: 600; border: none; cursor: pointer; width: 100%; margin-top: 10px; }
        .btn-submit:hover { background: #1d4ed8; }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; font-size: 14px; }
        .alert.success { background: #d1fae5; color: #065f46; }
        .alert.error { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="logo">🎓 UniVerse</div>
        
        <!-- LIEN DYNAMIQUE VERS LE BON DASHBOARD -->
        <a href="<?= $dashboard_url ?>">🏠 Tableau de Bord</a>
        <a href="#" class="active">👤 Fiche Profil</a>
        <a href="supports_cours.php">📚 Supports de cours</a>
        <a href="logout.php" class="logout">🚪 Déconnexion</a>
    </div>

    <div class="main-content">
        <h1 style="margin-bottom: 30px; color: #1e293b;">Dossier d'Information Individuel</h1>
        
        <?= $message; ?>

        <div class="profile-container">
            
            <!-- CARTE GAUCHE : INFOS -->
            <div class="card">
                <?php $img = (!empty($user['photo']) && file_exists("uploads/".$user['photo'])) ? $user['photo'] : 'default.png'; ?>
                <img src="uploads/<?= htmlspecialchars($img); ?>" class="profile-xl" alt="Photo">
                <h2><?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']); ?></h2>
                <span class="role-badge">👤 <?= htmlspecialchars($USER_ROLE); ?></span>

                <div class="info-list">
                    <p style="font-weight: bold; margin-bottom: 12px; color: var(--accent-blue);">📂 Registre de la Base de Données</p>
                    <div class="info-item">Email: <span><?= htmlspecialchars($user['email'] ?? 'Non répertorié'); ?></span></div>
                    <div class="info-item">Téléphone: <span><?= htmlspecialchars($user['telephone'] ?? 'Non répertorié'); ?></span></div>
                    
                    <?php if ($USER_ROLE === 'Etudiant'): ?>
                        <div class="info-item">Matricule: <span><?= htmlspecialchars($infos_specifiques['matricule'] ?? 'N/A'); ?></span></div>
                        <div class="info-item">Classe: <span><?= htmlspecialchars($infos_specifiques['nom_classe'] ?? 'Non assignée'); ?></span></div>
                        <div class="info-item">Département: <span><?= htmlspecialchars($infos_specifiques['nom_dept'] ?? 'Non assigné'); ?></span></div>
                    
                    <?php elseif (in_array($USER_ROLE, ['Professeur', 'Doyen', 'Recteur'])): ?>
                        <div class="info-item">Diplôme: <span><?= htmlspecialchars($infos_specifiques['diplome'] ?? 'Non renseigné'); ?></span></div>
                        <div class="info-item">Spécialité: <span><?= htmlspecialchars($infos_specifiques['specialite'] ?? 'Générale'); ?></span></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- CARTE DROITE : FORMULAIRE -->
            <div class="card" style="text-align: left;">
                <h2 style="margin-bottom: 20px; color: #1e293b;">✏️ Formulaire de Rectification</h2>
                
                <form method="POST" action="" enctype="multipart/form-data">
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label>Nom de Famille</label>
                            <input type="text" name="nom" value="<?= htmlspecialchars($user['nom']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Prénom</label>
                            <input type="text" name="prenom" value="<?= htmlspecialchars($user['prenom']); ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Adresse Électronique</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($user['email'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Numéro de Contact</label>
                        <input type="text" name="telephone" value="<?= htmlspecialchars($user['telephone'] ?? ''); ?>">
                    </div>

                    <!-- CHAMPS SPECIFIQUES ETUDIANT -->
                    <?php if ($USER_ROLE === 'Etudiant'): ?>
                        <div class="form-group">
                            <label>Numéro Matricule Étudiant</label>
                            <input type="text" name="matricule" value="<?= htmlspecialchars($infos_specifiques['matricule'] ?? ''); ?>">
                        </div>
                    <?php endif; ?>

                    <!-- CHAMPS SPECIFIQUES PROFESSEUR / DOYEN / RECTEUR -->
                    <?php if (in_array($USER_ROLE, ['Professeur', 'Doyen', 'Recteur'])): ?>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                            <div class="form-group">
                                <label>Spécialité</label>
                                <input type="text" name="specialite" value="<?= htmlspecialchars($infos_specifiques['specialite'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label>Diplôme</label>
                                <input type="text" name="diplome" value="<?= htmlspecialchars($infos_specifiques['diplome'] ?? ''); ?>">
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label>Mettre à jour la photo de profil</label>
                        <input type="file" name="photo" accept="image/*">
                    </div>

                    <button type="submit" name="action_modifier_profil" class="btn-submit">💾 Enregistrer les modifications</button>
                </form>
            </div>

        </div>
    </div>

</body>
</html>