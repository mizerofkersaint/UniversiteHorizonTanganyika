<?php
require_once __DIR__ . '/db.php';
// ============================================================
// configuration.php — Configuration du système UniVerse
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
    error_log("[configuration] Erreur connexion : " . $e->getMessage());
    die("Erreur de connexion à la base de données.");
}

require_once 'functions_archivage.php';

// ============================================================
// CSRF token
// ============================================================
if (empty($_SESSION['csrf_configuration'])) {
    $_SESSION['csrf_configuration'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_configuration'];

// ============================================================
// CONFIGURATION DU STOCKAGE DU LOGO
// ============================================================
$logo_dir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'logo';
$logo_url = 'uploads/logo/';

// Créer le dossier si nécessaire
if (!is_dir($logo_dir)) {
    @mkdir($logo_dir, 0755, true);
}

// Protéger le dossier avec un .htaccess
$htaccess_file = $logo_dir . DIRECTORY_SEPARATOR . '.htaccess';
if (!file_exists($htaccess_file)) {
    @file_put_contents($htaccess_file, "Options -Indexes\n<FilesMatch \"\\.(php|php3|php4|php5|phtml|pl|py|jsp|asp|sh|cgi)$\">\nRequire all denied\n</FilesMatch>\n");
}

// ============================================================
// VALIDATION DES IMAGES
// ============================================================
$extensions_autorisees = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'];
$mime_autorises = ['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml', 'image/webp'];
$taille_max = 2 * 1024 * 1024; // 2 Mo

// ============================================================
// FONCTIONS UTILITAIRES
// ============================================================
function charger_configuration($bdd) {
    $config = [];
    try {
        $stmt = $bdd->query("SELECT cle, valeur, categorie, libelle, type FROM configuration");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $config[$row['cle']] = $row;
        }
    } catch (Exception $e) {
        error_log("[configuration] Erreur chargement : " . $e->getMessage());
    }
    return $config;
}

function valider_url($url) {
    if ($url === '') return true;
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

function valider_email($email) {
    if ($email === '') return true;
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// ============================================================
// TRAITEMENT DU FORMULAIRE
// ============================================================
$message_succes = '';
$message_erreur = '';

if (isset($_POST['enregistrer_config'])) {

    // Vérification CSRF
    if (!isset($_POST['csrf_token']) || !hash_equals($csrf_token, $_POST['csrf_token'])) {
        $message_erreur = "Jeton de sécurité invalide. Rechargez la page.";
    } else {
        // 1. Récupération des anciennes valeurs pour archivage
        $ancienne_config = charger_configuration($bdd);

        // 2. Champs à mettre à jour (liste blanche)
        $champs_textes = [
            'univ_nom_officiel', 'univ_nom_abrege', 'univ_adresse',
            'univ_ville', 'univ_pays', 'univ_telephone',
            'univ_email', 'univ_site_web',
            'app_nom', 'app_version', 'app_email_contact', 'app_fuseau',
        ];

        $erreurs = [];
        $a_mettre_a_jour = [];

        foreach ($champs_textes as $cle) {
            if (array_key_exists($cle, $_POST)) {
                $valeur = trim((string)$_POST[$cle]);

                // Validation spécifique par type
                if ($cle === 'univ_email' || $cle === 'app_email_contact') {
                    if (!valider_email($valeur)) {
                        $erreurs[] = "Format d'email invalide pour : " . $cle;
                        continue;
                    }
                }
                if ($cle === 'univ_site_web') {
                    if (!valider_url($valeur)) {
                        $erreurs[] = "Format d'URL invalide pour le site web.";
                        continue;
                    }
                }

                $a_mettre_a_jour[$cle] = $valeur;
            }
        }

        // 3. Gestion du logo (si un fichier est envoyé)
        $nouveau_logo_nom = null;
        if (isset($_FILES['univ_logo']) && $_FILES['univ_logo']['error'] === UPLOAD_ERR_OK) {
            $fichier = $_FILES['univ_logo'];

            // Vérification taille
            if ($fichier['size'] > $taille_max) {
                $erreurs[] = "Le logo est trop volumineux (max 2 Mo).";
            } else {
                // Vérification extension
                $extension = strtolower(pathinfo($fichier['name'], PATHINFO_EXTENSION));
                if (!in_array($extension, $extensions_autorisees, true)) {
                    $erreurs[] = "Format de logo non autorisé. Formats acceptés : " . implode(', ', $extensions_autorisees);
                } else {
                    // Vérification MIME réelle
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime = finfo_file($finfo, $fichier['tmp_name']);
                    finfo_close($finfo);

                    if (!in_array($mime, $mime_autorises, true)) {
                        $erreurs[] = "Le fichier n'est pas une image valide.";
                    } else {
                        // Nom sûr
                        $nouveau_logo_nom = 'logo_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
                        $destination = $logo_dir . DIRECTORY_SEPARATOR . $nouveau_logo_nom;

                        if (!move_uploaded_file($fichier['tmp_name'], $destination)) {
                            $erreurs[] = "Impossible d'enregistrer le logo sur le serveur.";
                            $nouveau_logo_nom = null;
                        }
                    }
                }
            }
        }

        // 4. Si erreurs, on affiche et on s'arrête (mais on ne supprime pas le logo uploadé — il sera supprimé plus tard si annulé)
        if (count($erreurs) > 0) {
            if ($nouveau_logo_nom !== null && file_exists($logo_dir . DIRECTORY_SEPARATOR . $nouveau_logo_nom)) {
                @unlink($logo_dir . DIRECTORY_SEPARATOR . $nouveau_logo_nom);
            }
            $message_erreur = implode(' ', $erreurs);
        } else {
            try {
                $bdd->beginTransaction();

                // Mise à jour des champs texte
                $stmt = $bdd->prepare("UPDATE configuration SET valeur = ? WHERE cle = ?");
                foreach ($a_mettre_a_jour as $cle => $valeur) {
                    $stmt->execute([$valeur, $cle]);
                }

                // Mise à jour du logo si applicable
                if ($nouveau_logo_nom !== null) {
                    // Supprimer l'ancien logo si existant
                    if (!empty($ancienne_config['univ_logo']['valeur'])) {
                        $ancien_fichier = $logo_dir . DIRECTORY_SEPARATOR . $ancienne_config['univ_logo']['valeur'];
                        if (file_exists($ancien_fichier)) {
                            @unlink($ancien_fichier);
                        }
                    }
                    // Enregistrer le nouveau nom
                    $stmt->execute([$nouveau_logo_nom, 'univ_logo']);
                }

                $bdd->commit();

                // Archivage
                $nouvelle_config = charger_configuration($bdd);
                archiverAction(
                    $bdd,
                    getUserIdEnSession($bdd),
                    'UPDATE',
                    'configuration',
                    null,
                    $ancienne_config,
                    $nouvelle_config,
                    'Modification de la configuration du système'
                );

                $message_succes = "Configuration enregistrée avec succès.";

            } catch (Exception $e) {
                $bdd->rollBack();
                error_log("[configuration] Erreur sauvegarde : " . $e->getMessage());
                $message_erreur = "Une erreur est survenue lors de l'enregistrement.";
                // Nettoyer le nouveau logo si l'insertion a échoué
                if ($nouveau_logo_nom !== null && file_exists($logo_dir . DIRECTORY_SEPARATOR . $nouveau_logo_nom)) {
                    @unlink($logo_dir . DIRECTORY_SEPARATOR . $nouveau_logo_nom);
                }
            }
        }
    }
}

// ============================================================
// CHARGEMENT DE LA CONFIGURATION POUR AFFICHAGE
// ============================================================
$config = charger_configuration($bdd);

// Fonction utilitaire pour récupérer une valeur
function cfg($config, $cle, $defaut = '') {
    return isset($config[$cle]['valeur']) ? $config[$cle]['valeur'] : $defaut;
}

// Vérifier si le logo existe
$logo_actuel = cfg($config, 'univ_logo');
$logo_existe = $logo_actuel !== '' && file_exists($logo_dir . DIRECTORY_SEPARATOR . $logo_actuel);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuration du système — UniVerse</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Arial, sans-serif; }
        body { background: #eef3f8; color: #1f2937; }

        .page-top-bar { background: #fff; padding: 12px 25px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
        .page-top-bar a { color: #003366; text-decoration: none; font-weight: 600; font-size: 14px; }
        .page-top-bar a:hover { color: #0055aa; text-decoration: underline; }

        .container { width: 95%; max-width: 1000px; margin: 30px auto; }

        .page-header { background: linear-gradient(135deg, #003366, #0055aa); color: white; text-align: center; padding: 25px; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.2); margin-bottom: 20px; }
        .page-header h1 { font-size: 24px; }
        .page-header p { font-size: 13px; opacity: 0.9; margin-top: 5px; }

        .alert { padding: 14px 18px; border-radius: 10px; margin-bottom: 20px; font-weight: 600; font-size: 14px; }
        .alert-success { background: #d1fae5; color: #065f46; border-left: 4px solid #10b981; }
        .alert-error { background: #fee2e2; color: #991b1b; border-left: 4px solid #dc2626; }

        .card { background: white; border-radius: 18px; box-shadow: 0 10px 25px rgba(0,0,0,.07); padding: 30px; margin-bottom: 20px; }
        .card h2 { color: #003366; margin-bottom: 20px; font-size: 18px; display: flex; align-items: center; gap: 10px; padding-bottom: 12px; border-bottom: 2px solid #f1f5f9; }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
        .form-group { display: flex; flex-direction: column; }
        .form-group.full { grid-column: 1 / -1; }
        .form-group label { font-weight: 600; font-size: 13px; color: #334155; margin-bottom: 6px; }
        .form-group label .required { color: #dc2626; }
        .form-group input, .form-group textarea, .form-group select {
            padding: 12px 14px; border: 1px solid #d1d5db; border-radius: 10px; font-size: 14px;
            outline: none; transition: border-color 0.3s; background: #f8fafc; font-family: inherit;
        }
        .form-group input:focus, .form-group textarea:focus {
            border-color: #0055aa; box-shadow: 0 0 0 3px rgba(0,85,170,.10); background: #fff;
        }
        .form-group textarea { resize: vertical; min-height: 70px; }
        .form-group .hint { font-size: 11px; color: #94a3b8; margin-top: 4px; }

        .logo-preview { display: flex; align-items: center; gap: 20px; padding: 18px; background: #f8fafc; border-radius: 10px; border: 2px dashed #cbd5e1; }
        .logo-preview .logo-img { width: 120px; height: 120px; object-fit: contain; border-radius: 10px; background: white; border: 1px solid #e2e8f0; padding: 8px; }
        .logo-preview .logo-empty { width: 120px; height: 120px; display: flex; align-items: center; justify-content: center; background: white; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 40px; color: #cbd5e1; }
        .logo-preview .logo-info { flex: 1; }
        .logo-preview .logo-info strong { display: block; color: #1e293b; font-size: 14px; margin-bottom: 8px; }
        .logo-preview .logo-info input[type="file"] { font-size: 13px; }

        .buttons { display: flex; justify-content: flex-end; gap: 12px; margin-top: 25px; padding-top: 20px; border-top: 1px solid #e2e8f0; }
        .btn { padding: 12px 26px; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: 8px; transition: all 0.2s; }
        .btn-primary { background: #003366; color: white; }
        .btn-primary:hover { background: #0055aa; transform: translateY(-2px); }
        .btn-secondary { background: #e5e7eb; color: #111827; }
        .btn-secondary:hover { background: #d1d5db; }

        @media (max-width: 700px) {
            .form-grid { grid-template-columns: 1fr; }
            .buttons { flex-direction: column; }
            .btn { width: 100%; }
            .logo-preview { flex-direction: column; }
        }
    </style>
</head>
<body>

<div class="page-top-bar">
    <a href="administrateur.php">← Retour au tableau de bord</a>
    <span style="font-size:13px; color:#64748b;">Configuration du système</span>
</div>

<div class="container">

    <div class="page-header">
        <h1>⚙️ Configuration du système</h1>
        <p>Informations de l'université et paramètres généraux de l'application</p>
    </div>

    <?php if ($message_succes !== ''): ?>
        <div class="alert alert-success">✅ <?= htmlspecialchars($message_succes) ?></div>
    <?php endif; ?>
    <?php if ($message_erreur !== ''): ?>
        <div class="alert alert-error">❌ <?= htmlspecialchars($message_erreur) ?></div>
    <?php endif; ?>

    <form method="POST" action="" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

        <!-- SECTION 1 : INFORMATIONS DE L'UNIVERSITÉ -->
        <div class="card">
            <h2>🏛️ Informations de l'université</h2>
            <div class="form-grid">

                <div class="form-group">
                    <label>Nom officiel <span class="required">*</span></label>
                    <input type="text" name="univ_nom_officiel" value="<?= htmlspecialchars(cfg($config, 'univ_nom_officiel')) ?>" required>
                </div>

                <div class="form-group">
                    <label>Nom abrégé</label>
                    <input type="text" name="univ_nom_abrege" value="<?= htmlspecialchars(cfg($config, 'univ_nom_abrege')) ?>" placeholder="Ex: ULT">
                </div>

                <div class="form-group full">
                    <label>Adresse physique</label>
                    <textarea name="univ_adresse" placeholder="Ex: Avenue de l'Université, Bujumbura"><?= htmlspecialchars(cfg($config, 'univ_adresse')) ?></textarea>
                </div>

                <div class="form-group">
                    <label>Ville</label>
                    <input type="text" name="univ_ville" value="<?= htmlspecialchars(cfg($config, 'univ_ville')) ?>">
                </div>

                <div class="form-group">
                    <label>Pays</label>
                    <input type="text" name="univ_pays" value="<?= htmlspecialchars(cfg($config, 'univ_pays')) ?>">
                </div>

                <div class="form-group">
                    <label>Téléphone officiel</label>
                    <input type="tel" name="univ_telephone" value="<?= htmlspecialchars(cfg($config, 'univ_telephone')) ?>" placeholder="Ex: +257 ...">
                </div>

                <div class="form-group">
                    <label>Email officiel</label>
                    <input type="email" name="univ_email" value="<?= htmlspecialchars(cfg($config, 'univ_email')) ?>" placeholder="contact@universite.bi">
                </div>

                <div class="form-group full">
                    <label>Site web officiel</label>
                    <input type="url" name="univ_site_web" value="<?= htmlspecialchars(cfg($config, 'univ_site_web')) ?>" placeholder="https://www.universite.bi">
                </div>

            </div>
        </div>

        <!-- SECTION 2 : PARAMÈTRES GÉNÉRAUX -->
        <div class="card">
            <h2>📱 Paramètres généraux de l'application</h2>
            <div class="form-grid">

                <div class="form-group">
                    <label>Nom de l'application <span class="required">*</span></label>
                    <input type="text" name="app_nom" value="<?= htmlspecialchars(cfg($config, 'app_nom', 'UniVerse')) ?>" required>
                </div>

                <div class="form-group">
                    <label>Version</label>
                    <input type="text" name="app_version" value="<?= htmlspecialchars(cfg($config, 'app_version', '1.0')) ?>">
                </div>

                <div class="form-group">
                    <label>Email de contact</label>
                    <input type="email" name="app_email_contact" value="<?= htmlspecialchars(cfg($config, 'app_email_contact')) ?>" placeholder="contact@universe.local">
                </div>

                <div class="form-group">
                    <label>Fuseau horaire</label>
                    <input type="text" name="app_fuseau" value="<?= htmlspecialchars(cfg($config, 'app_fuseau', 'Africa/Bujumbura')) ?>" placeholder="Africa/Bujumbura">
                    <span class="hint">Format : Continent/Ville (ex: Africa/Bujumbura)</span>
                </div>

            </div>
        </div>

        <!-- SECTION 3 : LOGO -->
        <div class="card">
            <h2>🖼️ Logo de l'université</h2>
            <div class="logo-preview">
                <?php if ($logo_existe): ?>
                    <img src="<?= htmlspecialchars($logo_url . $logo_actuel) ?>" alt="Logo actuel" class="logo-img">
                <?php else: ?>
                    <div class="logo-empty">🏛️</div>
                <?php endif; ?>
                <div class="logo-info">
                    <strong>Remplacer le logo</strong>
                    <input type="file" name="univ_logo" accept="image/*">
                    <div class="hint" style="margin-top:8px;">
                        Formats acceptés : JPG, PNG, GIF, SVG, WEBP — Taille max : 2 Mo.<br>
                        Le logo actuel sera conservé si aucun nouveau fichier n'est sélectionné.
                    </div>
                </div>
            </div>
        </div>

        <!-- BOUTONS -->
        <div class="buttons">
            <a href="administrateur.php" class="btn btn-secondary">❌ Annuler</a>
            <button type="submit" name="enregistrer_config" class="btn btn-primary">💾 Enregistrer les modifications</button>
        </div>

    </form>

</div>
</body>
</html>