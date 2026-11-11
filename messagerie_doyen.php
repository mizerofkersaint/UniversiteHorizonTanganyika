<?php
require_once __DIR__ . '/db.php';
require_once 'auth.php';
requireRole(['Doyen', 'Administrateur']);

try {
    $bdd = getDBConnection();
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Erreur : " . $e->getMessage());
}

// Récupérer le département du doyen
$stmtDept = $bdd->prepare("SELECT id_dept FROM doyens WHERE id_user = ?");
$stmtDept->execute([$USER_ID]);
$id_dept_doyen = $stmtDept->fetchColumn();

if (!$id_dept_doyen && $USER_ROLE !== 'Administrateur') {
    die("Aucun département assigné à ce doyen.");
}

$message_succes = '';
$message_erreur = '';

// ============================================================
// TRAITEMENT DE L'ENVOI (CIBLÉ PAR CLASSE / DÉPARTEMENT / USER)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['envoyer'])) {
    $type_dest = $_POST['type_destinataire'] ?? '';
    $sujet = trim($_POST['sujet'] ?? '');
    $contenu = trim($_POST['contenu'] ?? '');
    $destinataires_ids = [];

    if ($sujet === '' || $contenu === '') {
        $message_erreur = "Le sujet et le contenu sont obligatoires.";
    } else {
        try {
            // Déterminer la liste des destinataires
            if ($type_dest === 'classe') {
                // Envoyer à toute une classe (niveau + département)
                $id_classe = (int)($_POST['id_classe'] ?? 0);
                if ($id_classe <= 0) throw new Exception("Veuillez sélectionner une classe.");
                
                $stmt = $bdd->prepare("
                    SELECT DISTINCT u.id_user 
                    FROM etudiants e 
                    JOIN users u ON e.id_user = u.id_user 
                    JOIN classes c ON e.id_classe = c.id_classe
                    WHERE e.id_classe = ? AND c.id_dept = ? AND u.statut = 'Actif'
                ");
                $stmt->execute([$id_classe, $id_dept_doyen]);
                $destinataires_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

            } elseif ($type_dest === 'etudiants_dept') {
                // Envoyer à tous les étudiants du département
                $stmt = $bdd->prepare("
                    SELECT DISTINCT u.id_user 
                    FROM etudiants e 
                    JOIN users u ON e.id_user = u.id_user 
                    JOIN classes c ON e.id_classe = c.id_classe
                    WHERE c.id_dept = ? AND u.statut = 'Actif'
                ");
                $stmt->execute([$id_dept_doyen]);
                $destinataires_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

            } elseif ($type_dest === 'professeurs_dept') {
                // Envoyer à tous les professeurs du département
                $stmt = $bdd->prepare("
                    SELECT DISTINCT u.id_user 
                    FROM professeurs p 
                    JOIN users u ON p.id_user = u.id_user 
                    JOIN cours co ON co.id_prof = p.id_prof 
                    JOIN classes cl ON co.id_classe = cl.id_classe
                    WHERE cl.id_dept = ? AND u.statut = 'Actif'
                ");
                $stmt->execute([$id_dept_doyen]);
                $destinataires_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

            } elseif ($type_dest === 'tous_dept') {
                // Étudiants + professeurs du département
                $stmt = $bdd->prepare("
                    SELECT DISTINCT u.id_user 
                    FROM users u
                    LEFT JOIN etudiants e ON e.id_user = u.id_user
                    LEFT JOIN classes c1 ON e.id_classe = c1.id_classe
                    LEFT JOIN professeurs p ON p.id_user = u.id_user
                    LEFT JOIN cours co ON co.id_prof = p.id_prof
                    LEFT JOIN classes c2 ON co.id_classe = c2.id_classe
                    WHERE (c1.id_dept = ? OR c2.id_dept = ?) AND u.statut = 'Actif'
                ");
                $stmt->execute([$id_dept_doyen, $id_dept_doyen]);
                $destinataires_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

            } elseif ($type_dest === 'user') {
                // Envoyer à un utilisateur précis
                $id_user_cible = (int)($_POST['id_user_cible'] ?? 0);
                if ($id_user_cible > 0) {
                    $destinataires_ids = [$id_user_cible];
                }
            }

            if (empty($destinataires_ids)) {
                throw new Exception("Aucun destinataire trouvé pour ce critère.");
            }

            // Envoyer le message à chaque destinataire
            $bdd->beginTransaction();
            $ins = $bdd->prepare("INSERT INTO messages (id_expediteur, id_destinataire, sujet, contenu, lu) VALUES (?, ?, ?, ?, 0)");
            $nb_envoyes = 0;
            foreach ($destinataires_ids as $dest_id) {
                if ($dest_id == $USER_ID) continue; // Ne pas s'envoyer à soi-même
                $ins->execute([$USER_ID, $dest_id, $sujet, $contenu]);
                $nb_envoyes++;
            }
            $bdd->commit();

            $message_succes = "✅ Message envoyé à <strong>$nb_envoyes</strong> destinataire(s) avec succès.";
        } catch (Exception $e) {
            if ($bdd->inTransaction()) $bdd->rollBack();
            $message_erreur = "Erreur : " . $e->getMessage();
        }
    }
}

// ============================================================
// MARQUER COMME LU
// ============================================================
if (isset($_GET['lu']) && (int)$_GET['lu'] > 0) {
    $bdd->prepare("UPDATE messages SET lu = 1 WHERE id_message = ? AND id_destinataire = ?")
        ->execute([(int)$_GET['lu'], $USER_ID]);
    header("Location: messagerie_doyen.php?onglet=recus");
    exit;
}

// ============================================================
// LISTES
// ============================================================
$onglet = $_GET['onglet'] ?? 'recus';

$stmt = $bdd->prepare("SELECT m.*, u.nom, u.prenom, u.role FROM messages m JOIN users u ON m.id_expediteur = u.id_user WHERE m.id_destinataire = ? ORDER BY m.date_envoi DESC");
$stmt->execute([$USER_ID]);
$recus = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $bdd->prepare("SELECT m.*, u.nom, u.prenom, u.role FROM messages m JOIN users u ON m.id_destinataire = u.id_user WHERE m.id_expediteur = ? ORDER BY m.date_envoi DESC LIMIT 100");
$stmt->execute([$USER_ID]);
$envoyes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Liste des classes du département
$stmt_classes = $bdd->prepare("SELECT id_classe, niveau FROM classes WHERE id_dept = ? ORDER BY niveau");
$stmt_classes->execute([$id_dept_doyen]);
$liste_classes = $stmt_classes->fetchAll(PDO::FETCH_ASSOC);

// Liste de tous les utilisateurs (pour l'option "utilisateur précis")
$stmt_users = $bdd->prepare("SELECT id_user, nom, prenom, role FROM users WHERE id_user != ? AND statut = 'Actif' ORDER BY role, nom");
$stmt_users->execute([$USER_ID]);
$liste_users = $stmt_users->fetchAll(PDO::FETCH_ASSOC);

$nb_non_lus = count(array_filter($recus, fn($m) => !$m['lu']));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Messagerie - Doyen</title>
    <link rel="stylesheet" href="assets/theme.css">
    <script src="assets/theme.js"></script>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI',Arial,sans-serif; }
        body { background:#eef3f8; padding:30px 20px; }
        .container { max-width:1100px; margin:0 auto; }
        .page-header { background:linear-gradient(135deg,#003366,#0055aa); color:white; text-align:center; padding:25px; border-radius:12px; box-shadow:0 4px 10px rgba(0,0,0,0.2); margin-bottom:20px; position:relative; }
        .page-header h1 { font-size:24px; }
        .page-header p { font-size:13px; opacity:0.9; margin-top:5px; }
        .theme-toggle-btn { position:absolute; top:15px; right:15px; background:white; border:1px solid #e2e8f0; width:42px; height:42px; border-radius:50%; font-size:18px; cursor:pointer; }
        .card { background:white; border-radius:18px; box-shadow:0 10px 25px rgba(0,0,0,.07); padding:25px; margin-bottom:20px; }
        .card h2 { color:#003366; margin-bottom:15px; font-size:17px; padding-bottom:12px; border-bottom:2px solid #f1f5f9; }
        .tabs { display:flex; gap:10px; margin-bottom:20px; flex-wrap:wrap; }
        .tab { padding:12px 24px; border-radius:10px; background:white; border:1px solid #e2e8f0; text-decoration:none; color:#475569; font-weight:600; font-size:14px; }
        .tab.active { background:#003366; color:white; border-color:#003366; }
        .tab .badge { background:#dc2626; color:white; padding:2px 8px; border-radius:10px; font-size:11px; margin-left:5px; }
        .form-grid { display:grid; grid-template-columns:1fr 1fr; gap:15px; }
        .form-group { display:flex; flex-direction:column; margin-bottom:15px; }
        .form-group.full { grid-column:1 / -1; }
        .form-group label { font-weight:600; font-size:13px; color:#334155; margin-bottom:6px; }
        .form-control { padding:11px 14px; border:1px solid #d1d5db; border-radius:10px; font-size:14px; background:#f8fafc; outline:none; width:100%; }
        .form-control:focus { border-color:#0055aa; box-shadow:0 0 0 3px rgba(0,85,170,.10); background:white; }
        textarea.form-control { resize:vertical; min-height:100px; }
        .radio-group { display:flex; flex-direction:column; gap:8px; padding:15px; background:#f8fafc; border-radius:10px; border:1px solid #e2e8f0; }
        .radio-group label { display:flex; align-items:center; gap:10px; font-weight:500; cursor:pointer; padding:8px 10px; border-radius:6px; }
        .radio-group label:hover { background:white; }
        .radio-group input[type="radio"] { width:18px; height:18px; }
        .btn { padding:11px 22px; border:none; border-radius:10px; font-weight:600; font-size:14px; cursor:pointer; text-decoration:none; display:inline-block; }
        .btn-primary { background:#003366; color:white; }
        .btn-primary:hover { background:#0055aa; }
        .message-item { border-bottom:1px solid #f1f5f9; padding:15px; display:flex; gap:15px; }
        .message-item.non-lu { background:#eff6ff; border-left:4px solid #003366; }
        .message-item .avatar { width:45px; height:45px; border-radius:50%; background:#003366; color:white; display:flex; align-items:center; justify-content:center; font-weight:bold; flex-shrink:0; }
        .message-item .content { flex:1; }
        .message-item .header { display:flex; justify-content:space-between; margin-bottom:5px; }
        .message-item .sender { font-weight:700; color:#1e293b; font-size:14px; }
        .message-item .role { font-size:11px; background:#eff6ff; color:#1e40af; padding:2px 8px; border-radius:10px; margin-left:5px; }
        .message-item .date { font-size:12px; color:#64748b; }
        .message-item .sujet { font-weight:600; color:#003366; margin-bottom:5px; }
        .message-item .contenu { font-size:13px; color:#475569; line-height:1.5; }
        .alert { padding:14px 18px; border-radius:10px; margin-bottom:20px; font-weight:600; }
        .alert-success { background:#d1fae5; color:#065f46; border-left:4px solid #10b981; }
        .alert-error { background:#fee2e2; color:#991b1b; border-left:4px solid #dc2626; }
        .empty { text-align:center; padding:40px; color:#94a3b8; }
        .info-box { background:#eff6ff; padding:12px 16px; border-radius:8px; margin-bottom:15px; font-size:13px; color:#1e40af; border-left:3px solid #3b82f6; }
    </style>
</head>
<body>
<div class="container">
    <div class="page-header">
        <div style="position:absolute; top:15px; left:15px;">
            <a href="dashboard_doyen.php" style="background:rgba(255,255,255,0.2); color:white; border:1px solid rgba(255,255,255,0.3); text-decoration:none; font-size:13px; padding:8px 15px; border-radius:8px; font-weight:600;">← Retour</a>
        </div>
        <h1>💬 Ma Messagerie</h1>
        <p>Envoyez des messages ciblés (classe, département, utilisateur)</p>
        <button class="theme-toggle-btn" onclick="toggleTheme()">🌙</button>
    </div>

    <?php if ($message_succes): ?><div class="alert alert-success"><?= $message_succes ?></div><?php endif; ?>
    <?php if ($message_erreur): ?><div class="alert alert-error">❌ <?= htmlspecialchars($message_erreur) ?></div><?php endif; ?>

    <div class="tabs">
        <a href="?onglet=recus" class="tab <?= $onglet === 'recus' ? 'active' : '' ?>">📥 Reçus <?php if ($nb_non_lus > 0): ?><span class="badge"><?= $nb_non_lus ?></span><?php endif; ?></a>
        <a href="?onglet=envoyes" class="tab <?= $onglet === 'envoyes' ? 'active' : '' ?>">📤 Envoyés</a>
        <a href="?onglet=nouveau" class="tab <?= $onglet === 'nouveau' ? 'active' : '' ?>">✍️ Nouveau message</a>
    </div>

    <?php if ($onglet === 'nouveau'): ?>
        <div class="card">
            <h2>✍️ Nouveau message ciblé</h2>
            <div class="info-box">
                💡 Choisissez le type de destinataire. Le message sera envoyé à <strong>tous les utilisateurs concernés</strong>.
            </div>
            <form method="POST">
                <div class="form-group">
                    <label>Type de destinataire *</label>
                    <div class="radio-group">
                        <label><input type="radio" name="type_destinataire" value="classe" checked onclick="toggleDest('classe')"> 🎓 Étudiants d'une classe spécifique (BAC)</label>
                        <label><input type="radio" name="type_destinataire" value="etudiants_dept" onclick="toggleDest('etudiants_dept')"> 👨‍🎓 Tous les étudiants de mon département</label>
                        <label><input type="radio" name="type_destinataire" value="professeurs_dept" onclick="toggleDest('professeurs_dept')"> 👨‍🏫 Tous les professeurs de mon département</label>
                        <label><input type="radio" name="type_destinataire" value="tous_dept" onclick="toggleDest('tous_dept')"> 👥 Tous (étudiants + professeurs) du département</label>
                        <label><input type="radio" name="type_destinataire" value="user" onclick="toggleDest('user')"> 👤 Un utilisateur précis</label>
                    </div>
                </div>

                <!-- Champ conditionnel : Classe -->
                <div class="form-group" id="bloc-classe">
                    <label>Choisir la classe (BAC) *</label>
                    <select name="id_classe" class="form-control">
                        <option value="">-- Sélectionner une classe --</option>
                        <?php foreach ($liste_classes as $c): ?>
                            <option value="<?= $c['id_classe'] ?>"><?= htmlspecialchars($c['niveau']) ?> (Département)</option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($liste_classes)): ?>
                        <small style="color:#dc2626;">Aucune classe dans votre département.</small>
                    <?php endif; ?>
                </div>

                <!-- Champ conditionnel : Utilisateur précis -->
                <div class="form-group" id="bloc-user" style="display:none;">
                    <label>Choisir l'utilisateur *</label>
                    <select name="id_user_cible" class="form-control">
                        <option value="">-- Sélectionner --</option>
                        <?php foreach ($liste_users as $u): ?>
                            <option value="<?= $u['id_user'] ?>"><?= htmlspecialchars($u['prenom'] . ' ' . $u['nom'] . ' (' . $u['role'] . ')') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-grid">
                    <div class="form-group full">
                        <label>Sujet *</label>
                        <input type="text" name="sujet" class="form-control" required placeholder="Objet du message">
                    </div>
                    <div class="form-group full">
                        <label>Contenu *</label>
                        <textarea name="contenu" class="form-control" required placeholder="Votre message..."></textarea>
                    </div>
                </div>

                <button type="submit" name="envoyer" class="btn btn-primary">📤 Envoyer le message</button>
            </form>
        </div>

        <script>
        function toggleDest(type) {
            document.getElementById('bloc-classe').style.display = (type === 'classe') ? 'block' : 'none';
            document.getElementById('bloc-user').style.display = (type === 'user') ? 'block' : 'none';
        }
        </script>

    <?php elseif ($onglet === 'recus'): ?>
        <div class="card">
            <h2>📥 Messages reçus (<?= count($recus) ?>)</h2>
            <?php if (count($recus) > 0): ?>
                <?php foreach ($recus as $m): ?>
                    <div class="message-item <?= !$m['lu'] ? 'non-lu' : '' ?>">
                        <div class="avatar"><?= strtoupper(substr($m['prenom'], 0, 1) . substr($m['nom'], 0, 1)) ?></div>
                        <div class="content">
                            <div class="header">
                                <div>
                                    <span class="sender"><?= htmlspecialchars($m['prenom'] . ' ' . $m['nom']) ?></span>
                                    <span class="role"><?= htmlspecialchars($m['role']) ?></span>
                                </div>
                                <span class="date"><?= date('d/m/Y H:i', strtotime($m['date_envoi'])) ?></span>
                            </div>
                            <div class="sujet">📌 <?= htmlspecialchars($m['sujet']) ?></div>
                            <div class="contenu"><?= nl2br(htmlspecialchars($m['contenu'])) ?></div>
                            <?php if (!$m['lu']): ?>
                                <a href="?lu=<?= $m['id_message'] ?>" class="btn btn-primary" style="margin-top:10px; padding:6px 14px; font-size:12px;">✅ Marquer comme lu</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty">📭 Aucun message reçu.</div>
            <?php endif; ?>
        </div>

    <?php elseif ($onglet === 'envoyes'): ?>
        <div class="card">
            <h2>📤 Messages envoyés (<?= count($envoyes) ?>)</h2>
            <?php if (count($envoyes) > 0): ?>
                <?php foreach ($envoyes as $m): ?>
                    <div class="message-item">
                        <div class="avatar" style="background:<?= $m['lu'] ? '#16a34a' : '#f59e0b' ?>;"><?= strtoupper(substr($m['prenom'], 0, 1) . substr($m['nom'], 0, 1)) ?></div>
                        <div class="content">
                            <div class="header">
                                <div>
                                    <span class="sender">À : <?= htmlspecialchars($m['prenom'] . ' ' . $m['nom']) ?></span>
                                    <span class="role"><?= htmlspecialchars($m['role']) ?></span>
                                </div>
                                <span class="date"><?= date('d/m/Y H:i', strtotime($m['date_envoi'])) ?></span>
                            </div>
                            <div class="sujet">📌 <?= htmlspecialchars($m['sujet']) ?></div>
                            <div class="contenu"><?= nl2br(htmlspecialchars($m['contenu'])) ?></div>
                            <div style="margin-top:8px; font-size:11px; color:<?= $m['lu'] ? '#16a34a' : '#f59e0b' ?>; font-weight:600;">
                                <?= $m['lu'] ? '✅ Lu' : '⏳ En attente de lecture' ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty">📭 Aucun message envoyé.</div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
</body>
</html>