<?php
require_once __DIR__ . '/db.php';
// ============================================================
// ajouteinscription.php — Inscription étudiant
// ✅ 2 modes : Existant | Créer + Inscrire
// ✅ Support iframe (dashboard_agent.php?crud=inscription_add)
// ✅ Bouton retour dashboard
// ============================================================
require_once 'auth.php';
requireRole(['Administrateur', 'Agent']);

try {
    $bdd = getDBConnection();
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Erreur : " . $e->getMessage());
}

$is_iframe = isset($_GET['iframe']) && $_GET['iframe'] === '1';
$iframe_query = $is_iframe ? 'iframe=1&' : '';

$mode = isset($_GET['mode']) ? $_GET['mode'] : 'existant';
$message = "";
$message_type = "";

/* ============================================================
   CHARGEMENT DES DONNÉES
============================================================ */
$departements = $bdd->query("SELECT id_dept, nom_dept, faculte FROM departements ORDER BY nom_dept")->fetchAll(PDO::FETCH_ASSOC);

$id_dept     = isset($_POST['dept'])     ? (int)$_POST['dept']     : (isset($_GET['dept'])     ? (int)$_GET['dept']     : 0);
$id_classe   = isset($_POST['classe'])   ? (int)$_POST['classe']   : (isset($_GET['classe'])   ? (int)$_GET['classe']   : 0);
$id_etudiant = isset($_POST['etudiant']) ? (int)$_POST['etudiant'] : 0;

$classes = [];
if ($id_dept > 0) {
    $stmt = $bdd->prepare("SELECT id_classe, niveau FROM classes WHERE id_dept = ? ORDER BY niveau");
    $stmt->execute([$id_dept]);
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$etudiants = [];
if ($id_classe > 0) {
    $stmt = $bdd->prepare("
        SELECT e.id_etudiant, e.matricule, u.nom, u.prenom
        FROM etudiants e
        INNER JOIN users u ON e.id_user = u.id_user
        WHERE e.id_classe = ?
        AND e.id_etudiant NOT IN (SELECT id_etudiant FROM inscriptions)
        ORDER BY u.nom, u.prenom
    ");
    $stmt->execute([$id_classe]);
    $etudiants = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/* ============================================================
   INSCRIPTION EXISTANT
============================================================ */
if (isset($_POST['inscrire_existant'])) {
    $id_e = (int)($_POST['etudiant'] ?? 0);
    $date = trim($_POST['date_inscription'] ?? '');

    if ($id_e <= 0 || $date === '') {
        $message = "❌ Sélectionnez un étudiant et une date.";
        $message_type = "error";
    } else {
        try {
            $check = $bdd->prepare("SELECT COUNT(*) FROM inscriptions WHERE id_etudiant = ?");
            $check->execute([$id_e]);
            if ($check->fetchColumn() > 0) {
                $message = "❌ Cet étudiant est déjà inscrit.";
                $message_type = "error";
            } else {
                $ins = $bdd->prepare("INSERT INTO inscriptions (id_etudiant, date_inscription) VALUES (?, ?)");
                $ins->execute([$id_e, $date]);
                $message = "✅ Étudiant inscrit avec succès !";
                $message_type = "success";
                $_POST = [];
            }
        } catch (Exception $e) {
            $message = "❌ Erreur : " . htmlspecialchars($e->getMessage());
            $message_type = "error";
        }
    }
}

/* ============================================================
   CRÉATION ÉTUDIANT + INSCRIPTION
============================================================ */
if (isset($_POST['creer_et_inscrire'])) {
    $nom              = trim($_POST['nom'] ?? '');
    $prenom           = trim($_POST['prenom'] ?? '');
    $sexe             = trim($_POST['sexe'] ?? '');
    $date_naissance   = trim($_POST['date_naissance'] ?? '');
    $telephone        = trim($_POST['telephone'] ?? '');
    $email            = trim($_POST['email'] ?? '');
    $titre            = trim($_POST['titre'] ?? 'Nouveau');
    $annee_acad       = trim($_POST['annee_academique'] ?? '');
    $id_classe_post   = (int)($_POST['classe'] ?? 0);
    $date_inscription = trim($_POST['date_inscription'] ?? '');

    if ($nom === '' || $prenom === '' || $sexe === '' || $email === '' || $id_classe_post <= 0 || $annee_acad === '' || $date_inscription === '') {
        $message = "❌ Remplissez tous les champs obligatoires.";
        $message_type = "error";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "❌ Email invalide.";
        $message_type = "error";
    } else {
        try {
            $bdd->beginTransaction();

            $username = strtolower(substr($prenom, 0, 1) . '.' . str_replace(' ', '', $nom));
            $check = $bdd->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $check->execute([$username]);
            if ($check->fetchColumn() > 0) {
                $username .= rand(10, 99);
            }
            $password = password_hash('123456', PASSWORD_BCRYPT);

            $insUser = $bdd->prepare("
                INSERT INTO users (nom, prenom, sexe, telephone, email, username, mot_de_passe, role, statut)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'Etudiant', 'Actif')
            ");
            $insUser->execute([$nom, $prenom, $sexe, $telephone, $email, $username, $password]);
            $id_user = (int)$bdd->lastInsertId();

            $insEtud = $bdd->prepare("
                INSERT INTO etudiants (id_user, id_classe, titre, date_naissance, annee_academique_entree)
                VALUES (?, ?, ?, ?, ?)
            ");
            $insEtud->execute([$id_user, $id_classe_post, $titre, $date_naissance ?: null, $annee_acad]);
            $id_etu = (int)$bdd->lastInsertId();

            $insInscr = $bdd->prepare("INSERT INTO inscriptions (id_etudiant, date_inscription) VALUES (?, ?)");
            $insInscr->execute([$id_etu, $date_inscription]);

            $reqInfo = $bdd->prepare("SELECT u.email_auth, e.matricule FROM users u JOIN etudiants e ON u.id_user = e.id_user WHERE u.id_user = ?");
            $reqInfo->execute([$id_user]);
            $info = $reqInfo->fetch(PDO::FETCH_ASSOC);

            $bdd->commit();

            $message = "✅ Étudiant créé et inscrit avec succès !<br>
                        📋 <strong>Matricule :</strong> " . htmlspecialchars($info['matricule'] ?? '—') . "<br>
                        📧 <strong>Email académique :</strong> " . htmlspecialchars($info['email_auth'] ?? '—') . "<br>
                        🔑 <strong>Mot de passe par défaut :</strong> 123456";
            $message_type = "success";
            $_POST = [];
        } catch (Exception $e) {
            if ($bdd->inTransaction()) $bdd->rollBack();
            $message = "❌ Erreur : " . htmlspecialchars($e->getMessage());
            $message_type = "error";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription — UniVerse</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Arial, sans-serif; }
        body { background: #eef3f8; min-height: 100vh; color: #1e293b; }
        body.iframe-mode { background: #f8fafc; }

        /* ============================================================
           WRAPPER
        ============================================================ */
        .main-container { padding: 30px 20px; }
        .main-container.in-iframe { padding: 18px; }
        .page-inner { max-width: 900px; margin: 0 auto; width: 100%; }

        /* ============================================================
           IFRAME TOP BAR
        ============================================================ */
        .iframe-topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 20px;
            background: white;
            border-bottom: 1px solid #e2e8f0;
            margin-bottom: 18px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            flex-wrap: wrap;
            gap: 12px;
        }
        .iframe-topbar-left { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 9px 16px;
            background: #f1f5f9;
            color: #475569;
            text-decoration: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            border: 1px solid #e2e8f0;
            transition: all 0.2s;
            white-space: nowrap;
        }
        .btn-back:hover { background: #e2e8f0; color: #1e293b; }

        .btn-nav {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 9px 16px;
            background: white;
            color: #475569;
            text-decoration: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            border: 1px solid #e2e8f0;
            transition: all 0.2s;
            white-space: nowrap;
        }
        .btn-nav:hover { background: #f0fdf4; color: #065f46; border-color: #10b981; }
        .btn-nav.active { background: #10b981; color: white; border-color: #10b981; }

        /* ============================================================
           PAGE HEADER (standalone)
        ============================================================ */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 22px;
            flex-wrap: wrap;
            gap: 12px;
        }
        .page-header h1 { color: #003366; font-size: 24px; }
        .page-header .actions { display: flex; gap: 8px; flex-wrap: wrap; }

        /* ============================================================
           ALERTES
        ============================================================ */
        .alert {
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 18px;
            font-size: 14px;
            line-height: 1.6;
            animation: slideDown 0.3s ease;
        }
        .alert.success { background: #d1fae5; color: #065f46; border-left: 4px solid #10b981; }
        .alert.error { background: #fee2e2; color: #991b1b; border-left: 4px solid #dc2626; }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }

        /* ============================================================
           TABS
        ============================================================ */
        .tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 18px;
            background: white;
            padding: 6px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .tab {
            flex: 1;
            padding: 12px 16px;
            border-radius: 8px;
            text-align: center;
            text-decoration: none;
            font-weight: 700;
            font-size: 13.5px;
            color: #475569;
            transition: all 0.2s;
            white-space: nowrap;
        }
        .tab:hover { background: #f1f5f9; }
        .tab.active { background: linear-gradient(135deg, #003366, #0055aa); color: white; }

        /* ============================================================
           CARTE FORMULAIRE
        ============================================================ */
        .card {
            background: white;
            padding: 28px;
            border-radius: 16px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
            width: 100%;
            overflow: hidden;
        }
        .card h2 {
            color: #003366;
            font-size: 17px;
            margin-bottom: 18px;
            padding-bottom: 12px;
            border-bottom: 2px solid #f1f5f9;
        }

        .info-banner {
            background: #eff6ff;
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #3b82f6;
            color: #1e40af;
            font-size: 13px;
            line-height: 1.6;
        }

        /* ============================================================
           FORMULAIRE
        ============================================================ */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }
        .form-group {
            display: flex;
            flex-direction: column;
            min-width: 0;
        }
        .form-group.full { grid-column: 1 / -1; }
        .form-group label {
            font-weight: 600;
            font-size: 13px;
            color: #334155;
            margin-bottom: 6px;
        }
        .form-group label .req { color: #dc2626; margin-left: 2px; }
        .form-group input,
        .form-group select {
            width: 100%;
            max-width: 100%;
            padding: 11px 14px;
            border-radius: 10px;
            border: 1.5px solid #cbd5e1;
            outline: none;
            font-size: 14px;
            background: #f8fafc;
            transition: all 0.2s;
            font-family: inherit;
        }
        .form-group input:focus,
        .form-group select:focus {
            border-color: #0055aa;
            box-shadow: 0 0 0 3px rgba(0,85,170,0.1);
            background: white;
        }
        .form-group select:disabled { background: #f1f5f9; cursor: not-allowed; opacity: 0.7; }

        /* ============================================================
           BOUTONS
        ============================================================ */
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
            white-space: nowrap;
        }
        .btn-primary { background: #003366; color: white; }
        .btn-primary:hover { background: #0055aa; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,51,102,0.25); }
        .btn-success { background: #16a34a; color: white; }
        .btn-success:hover { background: #15803d; transform: translateY(-1px); }
        .btn-cancel { background: #e5e7eb; color: #111827; }
        .btn-cancel:hover { background: #d1d5db; }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none !important; }

        .btn-row {
            display: flex;
            gap: 12px;
            margin-top: 22px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            flex-wrap: wrap;
        }
        .btn-row .btn { flex: 1; min-width: 140px; }

        /* ============================================================
           RESPONSIVE
        ============================================================ */
        @media (max-width: 700px) {
            .main-container { padding: 16px 12px; }
            .main-container.in-iframe { padding: 12px; }
            .form-grid { grid-template-columns: 1fr; }
            .form-group.full { grid-column: 1; }
            .tabs { flex-direction: column; }
            .card { padding: 20px; }
            .btn-row .btn { min-width: 0; }
            .page-header h1 { font-size: 20px; }
        }
    </style>
</head>
<body class="<?= $is_iframe ? 'iframe-mode' : '' ?>">

<?php if (!$is_iframe): ?>
    <?php include("menu.php"); ?>
<?php endif; ?>

<div class="main-container <?= $is_iframe ? 'in-iframe' : '' ?>">
    <div class="page-inner">

        <?php if ($is_iframe): ?>
            <!-- IFRAME TOP BAR -->
            <div class="iframe-topbar">
                <div class="iframe-topbar-left">
                    <a href="dashboard_agent.php" target="_top" class="btn-back">
                        ← Retour au dashboard
                    </a>
                    <a href="afficheinscription.php?iframe=1" class="btn-nav">
                        📋 Liste
                    </a>
                    <a href="ajouteinscription.php?iframe=1" class="btn-nav active">
                        ➕ Nouvelle
                    </a>
                </div>
            </div>
        <?php else: ?>
            <!-- STANDALONE HEADER -->
            <div class="page-header">
                <h1>📋 Nouvelle inscription</h1>
                <div class="actions">
                    <a href="afficheinscription.php" class="btn btn-cancel">← Retour à la liste</a>
                    <a href="dashboard_agent.php" class="btn btn-primary">🏠 Dashboard</a>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($message): ?>
            <div class="alert <?= $message_type ?>"><?= $message ?></div>
        <?php endif; ?>

        <!-- TABS -->
        <div class="tabs">
            <a href="?<?= $iframe_query ?>mode=existant" class="tab <?= $mode === 'existant' ? 'active' : '' ?>">
                🔍 Étudiant existant
            </a>
            <a href="?<?= $iframe_query ?>mode=nouveau" class="tab <?= $mode === 'nouveau' ? 'active' : '' ?>">
                ➕ Créer étudiant + inscription
            </a>
        </div>

        <?php if ($mode === 'existant'): ?>
            <!-- MODE 1 -->
            <div class="card">
                <h2>🔍 Inscrire un étudiant existant</h2>

                <div class="info-banner">
                    💡 Sélectionnez d'abord le <strong>département</strong>, puis la <strong>classe</strong>, puis l'<strong>étudiant</strong>.
                    Seuls les étudiants non encore inscrits sont affichés.
                </div>

                <form method="POST">
                    <input type="hidden" name="iframe" value="<?= $is_iframe ? '1' : '' ?>">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Département <span class="req">*</span></label>
                            <select name="dept" onchange="this.form.submit()" required>
                                <option value="0">— Sélectionner —</option>
                                <?php foreach ($departements as $d): ?>
                                    <option value="<?= $d['id_dept'] ?>" <?= ($id_dept == $d['id_dept']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($d['nom_dept']) ?> (<?= htmlspecialchars($d['faculte'] ?: 'Sans fac') ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Classe <span class="req">*</span></label>
                            <select name="classe" onchange="this.form.submit()" <?= empty($classes) ? 'disabled' : '' ?>>
                                <?php if (empty($classes)): ?>
                                    <option value="0">— Choisir un département d'abord —</option>
                                <?php else: ?>
                                    <option value="0">— Sélectionner —</option>
                                    <?php foreach ($classes as $c): ?>
                                        <option value="<?= $c['id_classe'] ?>" <?= ($id_classe == $c['id_classe']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($c['niveau']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="form-group full">
                            <label>Étudiant <span class="req">*</span></label>
                            <select name="etudiant" required <?= empty($etudiants) ? 'disabled' : '' ?>>
                                <?php if (empty($etudiants)): ?>
                                    <option value="0">— Aucun étudiant non-inscrit dans cette classe —</option>
                                <?php else: ?>
                                    <option value="0">— Sélectionner un étudiant —</option>
                                    <?php foreach ($etudiants as $e): ?>
                                        <option value="<?= $e['id_etudiant'] ?>" <?= ($id_etudiant == $e['id_etudiant']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($e['nom'] . ' ' . $e['prenom']) ?> — <?= htmlspecialchars($e['matricule']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="form-group full">
                            <label>Date d'inscription <span class="req">*</span></label>
                            <input type="date" name="date_inscription" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>

                    <div class="btn-row">
                        <button type="submit" name="inscrire_existant" class="btn btn-success" <?= empty($etudiants) ? 'disabled' : '' ?>>
                            💾 Inscrire l'étudiant
                        </button>
                        <a href="afficheinscription.php<?= $is_iframe ? '?iframe=1' : '' ?>" class="btn btn-cancel">❌ Annuler</a>
                    </div>
                </form>
            </div>

        <?php else: ?>
            <!-- MODE 2 -->
            <div class="card">
                <h2>➕ Créer un étudiant et l'inscrire</h2>

                <div class="info-banner">
                    💡 Le <strong>matricule</strong> et l'<strong>email académique</strong> seront générés automatiquement.<br>
                    🔑 Mot de passe par défaut : <strong>123456</strong> (à changer à la 1ère connexion).
                </div>

                <form method="POST">
                    <input type="hidden" name="iframe" value="<?= $is_iframe ? '1' : '' ?>">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Nom <span class="req">*</span></label>
                            <input type="text" name="nom" value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Prénom <span class="req">*</span></label>
                            <input type="text" name="prenom" value="<?= htmlspecialchars($_POST['prenom'] ?? '') ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Sexe <span class="req">*</span></label>
                            <select name="sexe" required>
                                <option value="">— Choisir —</option>
                                <option value="Masculin" <?= (($_POST['sexe'] ?? '') === 'Masculin') ? 'selected' : '' ?>>Masculin</option>
                                <option value="Feminin"  <?= (($_POST['sexe'] ?? '') === 'Feminin')  ? 'selected' : '' ?>>Féminin</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Date de naissance</label>
                            <input type="date" name="date_naissance" value="<?= htmlspecialchars($_POST['date_naissance'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label>Téléphone</label>
                            <input type="text" name="telephone" value="<?= htmlspecialchars($_POST['telephone'] ?? '') ?>" placeholder="+257...">
                        </div>
                        <div class="form-group">
                            <label>Email <span class="req">*</span></label>
                            <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required placeholder="etudiant@email.com">
                        </div>

                        <div class="form-group">
                            <label>Département <span class="req">*</span></label>
                            <select name="dept" onchange="this.form.submit()" required>
                                <option value="0">— Sélectionner —</option>
                                <?php foreach ($departements as $d): ?>
                                    <option value="<?= $d['id_dept'] ?>" <?= ($id_dept == $d['id_dept']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($d['nom_dept']) ?> (<?= htmlspecialchars($d['faculte'] ?: 'Sans fac') ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Classe <span class="req">*</span></label>
                            <select name="classe" required <?= empty($classes) ? 'disabled' : '' ?>>
                                <?php if (empty($classes)): ?>
                                    <option value="0">— Choisir un département d'abord —</option>
                                <?php else: ?>
                                    <option value="0">— Sélectionner —</option>
                                    <?php foreach ($classes as $c): ?>
                                        <option value="<?= $c['id_classe'] ?>" <?= (isset($_POST['classe']) && $_POST['classe'] == $c['id_classe']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($c['niveau']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Titre <span class="req">*</span></label>
                            <select name="titre" required>
                                <option value="Nouveau" <?= (($_POST['titre'] ?? '') === 'Nouveau') ? 'selected' : '' ?>>Nouveau</option>
                                <option value="Redoublant" <?= (($_POST['titre'] ?? '') === 'Redoublant') ? 'selected' : '' ?>>Redoublant</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Année académique <span class="req">*</span></label>
                            <input type="text" name="annee_academique" value="<?= htmlspecialchars($_POST['annee_academique'] ?? date('Y') . '-' . (date('Y') + 1)) ?>" required placeholder="2025-2026">
                        </div>

                        <div class="form-group full">
                            <label>Date d'inscription <span class="req">*</span></label>
                            <input type="date" name="date_inscription" value="<?= htmlspecialchars($_POST['date_inscription'] ?? date('Y-m-d')) ?>" required>
                        </div>
                    </div>

                    <div class="btn-row">
                        <button type="submit" name="creer_et_inscrire" class="btn btn-primary">🚀 Créer et inscrire</button>
                        <a href="afficheinscription.php<?= $is_iframe ? '?iframe=1' : '' ?>" class="btn btn-cancel">❌ Annuler</a>
                    </div>
                </form>
            </div>
        <?php endif; ?>

    </div>
</div>

</body>
</html>