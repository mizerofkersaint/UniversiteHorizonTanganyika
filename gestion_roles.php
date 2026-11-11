<?php
require_once __DIR__ . '/db.php';
// ============================================================
// gestion_roles.php — Gestion des rôles et permissions (Approche A)
// ============================================================
// Cette page affiche :
//   - Les 6 rôles fixes avec leurs descriptions et statistiques
//   - Une interface pour attribuer un rôle à un utilisateur
//   - Un archivage automatique des changements de rôle
// ============================================================

// 1. Démarrer la session AVANT tout HTML
require_once 'auth.php';
requireRole('Administrateur');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================================
// GARDE-FOU TEMPORAIRE — ACCÈS ADMINISTRATEUR
// ============================================================
if (!isset($_SESSION['id_user']) || (int)$_SESSION['id_user'] <= 0) {
    http_response_code(403);
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <title>Accès refusé — UniVerse</title>
        <style>
            body { font-family: 'Segoe UI', Arial, sans-serif; background: #eef3f8; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
            .box { background: white; padding: 40px; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,.08); text-align: center; max-width: 500px; }
            .box h1 { color: #dc2626; margin-bottom: 15px; font-size: 22px; }
            .box p { color: #475569; font-size: 14px; line-height: 1.6; }
            .box a { display: inline-block; margin-top: 20px; padding: 10px 20px; background: #003366; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; }
        </style>
    </head>
    <body>
        <div class="box">
            <h1>🔒 Accès refusé</h1>
            <p>Cette page est réservée à l'administrateur connecté.</p>
            <a href="administrateur.php">← Retour au tableau de bord</a>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// ============================================================
// CONNEXION PDO
// ============================================================
try {
    $bdd = getDBConnection();
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    error_log("[gestion_roles] Erreur connexion : " . $e->getMessage());
    die("Erreur de connexion à la base de données.");
}

require_once 'functions_archivage.php';

// ============================================================
// CSRF token
// ============================================================
if (empty($_SESSION['csrf_gestion_roles'])) {
    $_SESSION['csrf_gestion_roles'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_gestion_roles'];

// ============================================================
// CONFIGURATION — RÔLES FIXES (synchronisés avec ENUM users.role)
// ============================================================
$roles_config = [
    'Administrateur' => [
        'description'    => 'Accès complet à toutes les fonctionnalités du système. Gère les utilisateurs, les paramètres, les sauvegardes et les archives.',
        'permissions'    => [
            'Consulter les utilisateurs', 'Ajouter des utilisateurs', 'Modifier des utilisateurs', 'Supprimer des utilisateurs',
            'Consulter les informations académiques', 'Gérer les paiements',
            'Consulter les archives', 'Créer des sauvegardes', 'Restaurer une sauvegarde',
            'Consulter les paramètres du système',
        ],
        'niveau'         => 100,
        'couleur'        => '#dc2626',
        'icone'          => '👑',
    ],
    'Doyen' => [
        'description'    => 'Gestion académique de son département. Valide les notes, gère les professeurs, les classes et les cours de sa faculté.',
        'permissions'    => [
            'Consulter les informations académiques', 'Gérer les classes', 'Gérer les cours',
            'Consulter les professeurs', 'Gérer les professeurs', 'Consulter les notes', 'Valider les notes',
            'Consulter les archives',
        ],
        'niveau'         => 80,
        'couleur'        => '#8b5cf6',
        'icone'          => '🎓',
    ],
	    'Agent' => [
        'description'    => 'Agent d\'inscription : enregistre et modifie les dossiers des étudiants, crée les inscriptions.',
        'permissions'    => [
            'Enregistrer des étudiants', 'Modifier des étudiants',
            'Créer des inscriptions', 'Consulter la liste des étudiants',
            'Rechercher des utilisateurs',
        ],
        'niveau'         => 65,
        'couleur'        => '#10b981',
        'icone'          => '📝',
    ],
    'Comptable' => [
        'description'    => 'Gestion des paiements et des finances de l\'université. Valide les bordereaux et audite les recettes.',
        'permissions'    => [
            'Consulter les étudiants', 'Consulter les paiements', 'Gérer les paiements',
            'Auditer les finances',
        ],
        'niveau'         => 70,
        'couleur'        => '#10b981',
        'icone'          => '💰',
    ],
    'Professeur' => [
        'description'    => 'Gestion de ses cours, des notes et des examens. Saisit les notes et communique avec ses étudiants.',
        'permissions'    => [
            'Consulter les classes', 'Consulter les cours', 'Consulter les étudiants',
            'Consulter les notes', 'Gérer les notes',
        ],
        'niveau'         => 60,
        'couleur'        => '#2563eb',
        'icone'          => '👨‍🏫',
    ],
    'Etudiant' => [
        'description'    => 'Consultation de ses propres notes, paiements, emploi du temps et informations personnelles.',
        'permissions'    => [
            'Consulter ses notes', 'Consulter ses paiements',
        ],
        'niveau'         => 30,
        'couleur'        => '#f59e0b',
        'icone'          => '🎓',
    ],
    'Lecteur' => [
        'description'    => 'Consultation en lecture seule des informations publiques de l\'université.',
        'permissions'    => [
            'Consulter les départements', 'Consulter les classes', 'Consulter les cours',
        ],
        'niveau'         => 10,
        'couleur'        => '#64748b',
        'icone'          => '📖',
    ],
];

$roles_valides = array_keys($roles_config);

// ============================================================
// TRAITEMENT : CHANGEMENT DE RÔLE D'UN UTILISATEUR
// ============================================================
$message_succes = '';
$message_erreur = '';

if (isset($_POST['changer_role'])) {

    // Vérification CSRF
    if (!isset($_POST['csrf_token']) || !hash_equals($csrf_token, $_POST['csrf_token'])) {
        $message_erreur = "Jeton de sécurité invalide. Rechargez la page.";
    } else {
        $id_user_cible = isset($_POST['id_user']) ? (int)$_POST['id_user'] : 0;
        $nouveau_role  = isset($_POST['nouveau_role']) ? trim($_POST['nouveau_role']) : '';

        if ($id_user_cible <= 0) {
            $message_erreur = "Utilisateur invalide.";
        } elseif (!in_array($nouveau_role, $roles_valides, true)) {
            $message_erreur = "Rôle invalide.";
        } else {
            try {
                // Vérifier que l'utilisateur existe
                $checkUser = $bdd->prepare("SELECT id_user, role FROM users WHERE id_user = ? LIMIT 1");
                $checkUser->execute([$id_user_cible]);
                $user_cible = $checkUser->fetch(PDO::FETCH_ASSOC);

                if (!$user_cible) {
                    throw new Exception("Utilisateur introuvable.");
                }

                $ancien_role = $user_cible['role'];

                if ($ancien_role === $nouveau_role) {
                    throw new Exception("L'utilisateur possède déjà ce rôle.");
                }

                // Récupérer anciennes données pour archivage
                $ancienne_valeur = getDonneesEnregistrement($bdd, 'users', $id_user_cible);
                if (is_array($ancienne_valeur)) {
                    unset($ancienne_valeur['mot_de_passe']);
                }

                // UPDATE du rôle
                $stmt = $bdd->prepare("UPDATE users SET role = ? WHERE id_user = ?");
                $stmt->execute([$nouveau_role, $id_user_cible]);

                // Récupérer nouvelles données pour archivage
                $nouvelle_valeur = getDonneesEnregistrement($bdd, 'users', $id_user_cible);
                if (is_array($nouvelle_valeur)) {
                    unset($nouvelle_valeur['mot_de_passe']);
                }

                // Archivage
                archiverAction(
                    $bdd,
                    getUserIdEnSession($bdd),
                    'UPDATE',
                    'users',
                    $id_user_cible,
                    $ancienne_valeur,
                    $nouvelle_valeur,
                    'Changement de rôle : ' . $ancien_role . ' → ' . $nouveau_role
                );

                $message_succes = "Le rôle de l'utilisateur a été modifié avec succès : " . $ancien_role . " → " . $nouveau_role . ".";

            } catch (Exception $e) {
                error_log("[gestion_roles] Erreur changement rôle : " . $e->getMessage());
                $message_erreur = $e->getMessage();
            }
        }
    }
}

// ============================================================
// STATISTIQUES PAR RÔLE
// ============================================================
$stats_roles = [];
try {
    $stmt = $bdd->query("
        SELECT role, COUNT(*) AS nb
        FROM users
        GROUP BY role
    ");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $stats_roles[$row['role']] = (int)$row['nb'];
    }
} catch (Exception $e) {
    error_log("[gestion_roles] Erreur stats rôles : " . $e->getMessage());
}

$total_users = array_sum($stats_roles);

// ============================================================
// LISTE DES UTILISATEURS POUR LE FORMULAIRE D'ATTRIBUTION
// ============================================================
$liste_users = [];
try {
    $stmt = $bdd->query("
        SELECT id_user, nom, prenom, username, role, statut
        FROM users
        ORDER BY nom, prenom
    ");
    $liste_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("[gestion_roles] Erreur liste users : " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rôles & Permissions — UniVerse</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Arial, sans-serif; }
        body { background: #eef3f8; color: #1f2937; }

        .page-top-bar { background: #fff; padding: 12px 25px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
        .page-top-bar a { color: #003366; text-decoration: none; font-weight: 600; font-size: 14px; }
        .page-top-bar a:hover { color: #0055aa; text-decoration: underline; }

        .container { width: 95%; max-width: 1300px; margin: 30px auto; }

        .page-header { background: linear-gradient(135deg, #003366, #0055aa); color: white; text-align: center; padding: 25px; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.2); margin-bottom: 20px; }
        .page-header h1 { font-size: 24px; }
        .page-header p { font-size: 13px; opacity: 0.9; margin-top: 5px; }

        .alert { padding: 14px 18px; border-radius: 10px; margin-bottom: 20px; font-weight: 600; font-size: 14px; }
        .alert-success { background: #d1fae5; color: #065f46; border-left: 4px solid #10b981; }
        .alert-error { background: #fee2e2; color: #991b1b; border-left: 4px solid #dc2626; }

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 25px; }
        .stat-card { background: white; padding: 20px; border-radius: 14px; box-shadow: 0 4px 10px rgba(0,0,0,.05); text-align: center; border-top: 4px solid #003366; }
        .stat-card .value { font-size: 28px; font-weight: 800; color: #003366; }
        .stat-card .label { font-size: 12px; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px; }

        .roles-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .role-card { background: white; border-radius: 16px; box-shadow: 0 4px 15px rgba(0,0,0,.06); padding: 24px; position: relative; border-left: 5px solid #003366; transition: transform 0.2s ease; }
        .role-card:hover { transform: translateY(-4px); box-shadow: 0 10px 25px rgba(0,0,0,.10); }
        .role-card .role-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px; }
        .role-card .role-title { display: flex; align-items: center; gap: 10px; font-size: 18px; font-weight: 700; color: #0f172a; }
        .role-card .role-icon { font-size: 24px; }
        .role-card .role-count { background: #eff6ff; color: #1e40af; padding: 6px 14px; border-radius: 20px; font-weight: 700; font-size: 13px; }
        .role-card .role-count.empty { background: #f1f5f9; color: #94a3b8; }
        .role-card .role-description { color: #475569; font-size: 13px; line-height: 1.6; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #f1f5f9; }
        .role-card .permissions-title { font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.4px; margin-bottom: 8px; }
        .role-card .permissions-list { list-style: none; padding: 0; }
        .role-card .permissions-list li { font-size: 12px; color: #475569; padding: 4px 0; padding-left: 20px; position: relative; }
        .role-card .permissions-list li::before { content: "✓"; position: absolute; left: 0; color: #10b981; font-weight: 700; }

        .card { background: white; border-radius: 18px; box-shadow: 0 10px 25px rgba(0,0,0,.07); padding: 30px; margin-bottom: 20px; }
        .card h2 { color: #003366; margin-bottom: 20px; font-size: 18px; display: flex; align-items: center; gap: 10px; }
        .card p.hint { font-size: 13px; color: #64748b; margin-bottom: 20px; line-height: 1.5; }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr auto; gap: 15px; align-items: end; }
        .form-group { display: flex; flex-direction: column; }
        .form-group label { font-weight: 600; font-size: 13px; color: #334155; margin-bottom: 6px; }
        select { padding: 12px 14px; border: 1px solid #d1d5db; border-radius: 10px; font-size: 14px; outline: none; background: #f8fafc; transition: border-color 0.3s; }
        select:focus { border-color: #0055aa; box-shadow: 0 0 0 3px rgba(0,85,170,.10); background: #fff; }

        .btn { padding: 12px 24px; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: 8px; transition: all 0.2s; }
        .btn-primary { background: #003366; color: white; }
        .btn-primary:hover { background: #0055aa; transform: translateY(-2px); }

        @media (max-width: 700px) {
            .form-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="page-top-bar">
    <a href="administrateur.php">← Retour au tableau de bord</a>
    <span style="font-size:13px; color:#64748b;">Gestion des rôles & permissions</span>
</div>

<div class="container">

    <div class="page-header">
        <h1>🎭 Rôles & Permissions</h1>
        <p>Vue d'ensemble des rôles et de leurs responsabilités</p>
    </div>

    <?php if ($message_succes !== ''): ?>
        <div class="alert alert-success">✅ <?= htmlspecialchars($message_succes) ?></div>
    <?php endif; ?>
    <?php if ($message_erreur !== ''): ?>
        <div class="alert alert-error">❌ <?= htmlspecialchars($message_erreur) ?></div>
    <?php endif; ?>

    <!-- STATISTIQUES GLOBALES -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="value"><?= $total_users ?></div>
            <div class="label">Utilisateurs au total</div>
        </div>
        <div class="stat-card">
            <div class="value"><?= count($roles_config) ?></div>
            <div class="label">Rôles définis</div>
        </div>
        <div class="stat-card">
            <div class="value"><?= isset($stats_roles['Administrateur']) ? $stats_roles['Administrateur'] : 0 ?></div>
            <div class="label">Administrateurs</div>
        </div>
        <div class="stat-card">
            <div class="value"><?= isset($stats_roles['Etudiant']) ? $stats_roles['Etudiant'] : 0 ?></div>
            <div class="label">Étudiants</div>
        </div>
    </div>

    <!-- CARTES DES RÔLES -->
    <div class="roles-grid">
        <?php foreach ($roles_config as $nom_role => $config): ?>
            <?php
            $nb_users = isset($stats_roles[$nom_role]) ? $stats_roles[$nom_role] : 0;
            ?>
            <div class="role-card" style="border-left-color: <?= htmlspecialchars($config['couleur']) ?>;">
                <div class="role-header">
                    <div class="role-title">
                        <span class="role-icon"><?= $config['icone'] ?></span>
                        <?= htmlspecialchars($nom_role) ?>
                    </div>
                    <span class="role-count <?= ($nb_users === 0) ? 'empty' : '' ?>">
                        <?= $nb_users ?> utilisateur<?= ($nb_users > 1) ? 's' : '' ?>
                    </span>
                </div>
                <div class="role-description">
                    <?= htmlspecialchars($config['description']) ?>
                </div>
                <div class="permissions-title">Permissions associées (<?= count($config['permissions']) ?>)</div>
                <ul class="permissions-list">
                    <?php foreach ($config['permissions'] as $permission): ?>
                        <li><?= htmlspecialchars($permission) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- FORMULAIRE DE CHANGEMENT DE RÔLE -->
    <div class="card">
        <h2>🔧 Modifier le rôle d'un utilisateur</h2>
        <p class="hint">Sélectionnez un utilisateur et attribuez-lui un nouveau rôle. Le changement est immédiat et archivé.</p>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

            <div class="form-grid">
                <div class="form-group">
                    <label>Utilisateur</label>
                    <select name="id_user" required>
                        <option value="">— Choisir un utilisateur —</option>
                        <?php foreach ($liste_users as $u): ?>
                            <option value="<?= (int)$u['id_user'] ?>">
                                <?= htmlspecialchars($u['nom'] . ' ' . $u['prenom'] . ' (' . $u['username'] . ')') ?>
                                — <?= htmlspecialchars($u['role']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Nouveau rôle</label>
                    <select name="nouveau_role" required>
                        <option value="">— Choisir un rôle —</option>
                        <?php foreach ($roles_config as $nom_role => $config): ?>
                            <option value="<?= htmlspecialchars($nom_role) ?>">
                                <?= $config['icone'] ?> <?= htmlspecialchars($nom_role) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" name="changer_role" class="btn btn-primary">💾 Enregistrer</button>
            </div>
        </form>
    </div>

</div>
</body>
</html>