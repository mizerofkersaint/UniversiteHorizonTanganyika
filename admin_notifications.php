<?php
require_once __DIR__ . '/db.php';
// ============================================================
// admin_notifications.php — Centre de notifications administrateur
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
    error_log("[admin_notifications] Erreur connexion : " . $e->getMessage());
    die("Erreur de connexion à la base de données.");
}

require_once 'functions_archivage.php';

// ============================================================
// CSRF token
// ============================================================
if (empty($_SESSION['csrf_admin_notif'])) {
    $_SESSION['csrf_admin_notif'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_admin_notif'];

// ============================================================
// CONFIGURATION
// ============================================================
$types_valides = ['INFO', 'SUCCES', 'ALERT', 'URGENT'];
$statuts_valides = ['non_lu', 'lu'];

// ============================================================
// TRAITEMENT DES ACTIONS
// ============================================================
$message_succes = '';
$message_erreur = '';

// --- ACTION 1 : CRÉER UNE NOTIFICATION ---
if (isset($_POST['creer_notif'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($csrf_token, $_POST['csrf_token'])) {
        $message_erreur = "Jeton de sécurité invalide. Rechargez la page.";
    } else {
        $titre    = isset($_POST['titre'])    ? trim($_POST['titre'])    : '';
        $message  = isset($_POST['message'])  ? trim($_POST['message'])  : '';
        $type     = isset($_POST['type_evenement']) ? trim($_POST['type_evenement']) : 'INFO';

        if (!in_array($type, $types_valides, true)) {
            $type = 'INFO';
        }

        if ($titre === '' || $message === '') {
            $message_erreur = "Le titre et le message sont obligatoires.";
        } else {
            try {
                $stmt = $bdd->prepare("
                    INSERT INTO notifications 
                        (source_table, type_evenement, titre, message, statut, date_creation)
                    VALUES 
                        ('admin', ?, ?, ?, 'non_lu', NOW())
                ");
                $stmt->execute([$type, $titre, $message]);
                $id_new = (int) $bdd->lastInsertId();

                // Archivage
                archiverAction(
                    $bdd,
                    getUserIdEnSession($bdd),
                    'INSERT',
                    'notifications',
                    $id_new,
                    null,
                    ['titre' => $titre, 'type' => $type, 'message' => $message],
                    'Création d\'une notification : ' . $titre
                );

                $message_succes = "Notification créée avec succès.";
            } catch (Exception $e) {
                error_log("[admin_notifications] Erreur création : " . $e->getMessage());
                $message_erreur = "Une erreur est survenue lors de la création.";
            }
        }
    }
}

// --- ACTION 2 : MARQUER COMME LU ---
if (isset($_POST['marquer_lu'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($csrf_token, $_POST['csrf_token'])) {
        $message_erreur = "Jeton de sécurité invalide. Rechargez la page.";
    } else {
        $id_notif = isset($_POST['id_notif']) ? (int)$_POST['id_notif'] : 0;
        if ($id_notif > 0) {
            try {
                $stmt = $bdd->prepare("UPDATE notifications SET statut = 'lu' WHERE id_notification = ?");
                $stmt->execute([$id_notif]);
                $message_succes = "Notification marquée comme lue.";
            } catch (Exception $e) {
                error_log("[admin_notifications] Erreur marquage lu : " . $e->getMessage());
                $message_erreur = "Une erreur est survenue.";
            }
        }
    }
}

// --- ACTION 3 : SUPPRIMER ---
if (isset($_POST['supprimer_notif'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($csrf_token, $_POST['csrf_token'])) {
        $message_erreur = "Jeton de sécurité invalide. Rechargez la page.";
    } else {
        $id_notif = isset($_POST['id_notif']) ? (int)$_POST['id_notif'] : 0;
        if ($id_notif > 0) {
            try {
                // Récupérer les données pour archivage AVANT suppression
                $stmt = $bdd->prepare("SELECT * FROM notifications WHERE id_notification = ? LIMIT 1");
                $stmt->execute([$id_notif]);
                $ancienne = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($ancienne) {
                    $stmt = $bdd->prepare("DELETE FROM notifications WHERE id_notification = ?");
                    $stmt->execute([$id_notif]);

                    // Archivage
                    archiverAction(
                        $bdd,
                        getUserIdEnSession($bdd),
                        'DELETE',
                        'notifications',
                        $id_notif,
                        $ancienne,
                        null,
                        'Suppression d\'une notification : ' . ($ancienne['titre'] ?? '?')
                    );

                    $message_succes = "Notification supprimée.";
                }
            } catch (Exception $e) {
                error_log("[admin_notifications] Erreur suppression : " . $e->getMessage());
                $message_erreur = "Une erreur est survenue lors de la suppression.";
            }
        }
    }
}

// ============================================================
// FILTRES ET PAGINATION
// ============================================================
$filtre_statut = isset($_GET['statut']) ? trim($_GET['statut']) : '';
$filtre_type   = isset($_GET['type'])   ? trim($_GET['type'])   : '';
$page          = isset($_GET['page'])   ? max(1, (int)$_GET['page']) : 1;
$limite        = 15;

if (!in_array($filtre_statut, $statuts_valides, true)) {
    $filtre_statut = '';
}
if (!in_array($filtre_type, $types_valides, true)) {
    $filtre_type = '';
}

// Construction WHERE
$where = [];
$params = [];

if ($filtre_statut !== '') {
    $where[] = "statut = ?";
    $params[] = $filtre_statut;
}
if ($filtre_type !== '') {
    $where[] = "type_evenement = ?";
    $params[] = $filtre_type;
}

$sql_where = $where ? ("WHERE " . implode(" AND ", $where)) : "";

// Total
try {
    $stmt = $bdd->prepare("SELECT COUNT(*) FROM notifications $sql_where");
    $stmt->execute($params);
    $total = (int) $stmt->fetchColumn();
} catch (Exception $e) {
    error_log("[admin_notifications] Erreur count : " . $e->getMessage());
    $total = 0;
}

$nb_pages = max(1, (int)ceil($total / $limite));
if ($page > $nb_pages) {
    $page = $nb_pages;
}
$debut = ($page - 1) * $limite;

// Requête principale
try {
    $sql = "SELECT * FROM notifications $sql_where ORDER BY date_creation DESC, id_notification DESC LIMIT " . (int)$limite . " OFFSET " . (int)$debut;
    $stmt = $bdd->prepare($sql);
    $stmt->execute($params);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("[admin_notifications] Erreur requête : " . $e->getMessage());
    $notifications = [];
}

// Statistiques
try {
    $stats = $bdd->query("
        SELECT 
            COUNT(*) AS total,
            SUM(CASE WHEN statut = 'non_lu' THEN 1 ELSE 0 END) AS non_lues,
            SUM(CASE WHEN statut = 'lu' THEN 1 ELSE 0 END) AS lues
        FROM notifications
    ")->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $stats = ['total' => 0, 'non_lues' => 0, 'lues' => 0];
}

// ============================================================
// FONCTIONS UTILITAIRES
// ============================================================
function badge_type($type) {
    switch ($type) {
        case 'INFO':   return ['label' => 'ℹ️ Information', 'color' => '#3b82f6', 'bg' => '#dbeafe'];
        case 'SUCCES': return ['label' => '✅ Succès',      'color' => '#10b981', 'bg' => '#d1fae5'];
        case 'ALERT':  return ['label' => '⚠️ Alerte',      'color' => '#f59e0b', 'bg' => '#fef3c7'];
        case 'URGENT': return ['label' => '🚨 Urgent',      'color' => '#dc2626', 'bg' => '#fee2e2'];
        default:       return ['label' => '📌 ' . $type,     'color' => '#64748b', 'bg' => '#f1f5f9'];
    }
}

function build_query($extra = []) {
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
    <title>Notifications — Administration UniVerse</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Arial, sans-serif; }
        body { background: #eef3f8; color: #1f2937; }

        .page-top-bar { background: #fff; padding: 12px 25px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
        .page-top-bar a { color: #003366; text-decoration: none; font-weight: 600; font-size: 14px; }
        .page-top-bar a:hover { color: #0055aa; text-decoration: underline; }

        .container { width: 95%; max-width: 1300px; margin: 30px auto; }

        .page-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 22px; }
        .page-header h1 { color: #003366; font-size: 28px; font-weight: 800; }
        .header-actions { display: flex; gap: 12px; flex-wrap: wrap; }
        .counter { background: #3b82f6; color: white; padding: 6px 14px; border-radius: 20px; font-weight: bold; font-size: 13px; }
        .counter.warn { background: #f59e0b; }

        .alert { padding: 14px 18px; border-radius: 10px; margin-bottom: 20px; font-weight: 600; font-size: 14px; }
        .alert-success { background: #d1fae5; color: #065f46; border-left: 4px solid #10b981; }
        .alert-error { background: #fee2e2; color: #991b1b; border-left: 4px solid #dc2626; }

        .card { background: white; border-radius: 18px; box-shadow: 0 10px 25px rgba(0,0,0,.07); padding: 25px 30px; margin-bottom: 20px; }
        .card h2 { color: #003366; margin-bottom: 18px; font-size: 17px; padding-bottom: 12px; border-bottom: 2px solid #f1f5f9; }

        .form-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 15px; margin-bottom: 15px; }
        .form-group { display: flex; flex-direction: column; }
        .form-group label { font-weight: 600; font-size: 13px; color: #334155; margin-bottom: 6px; }
        .form-group input, .form-group select, .form-group textarea { padding: 11px 14px; border: 1px solid #d1d5db; border-radius: 10px; font-size: 14px; outline: none; background: #f8fafc; transition: border-color 0.3s; font-family: inherit; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color: #0055aa; box-shadow: 0 0 0 3px rgba(0,85,170,.10); background: #fff; }
        .form-group textarea { resize: vertical; min-height: 70px; }

        .btn { padding: 11px 22px; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: 6px; transition: all 0.2s; }
        .btn-primary { background: #003366; color: white; }
        .btn-primary:hover { background: #0055aa; transform: translateY(-2px); }
        .btn-secondary { background: #e5e7eb; color: #111827; }
        .btn-secondary:hover { background: #d1d5db; }
        .btn-danger { background: #dc2626; color: white; }
        .btn-danger:hover { background: #b91c1c; }
        .btn-sm { padding: 6px 12px; font-size: 12px; }

        .filters-bar { background: white; padding: 16px 22px; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,.05); margin-bottom: 20px; display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end; }
        .filters-bar .form-group { min-width: 160px; }
        .filters-bar label { font-size: 11px; text-transform: uppercase; letter-spacing: 0.3px; }

        .notif-list { display: flex; flex-direction: column; gap: 12px; }
        .notif-item { background: white; border-radius: 14px; border: 1px solid #e5e7eb; padding: 18px 22px; display: flex; justify-content: space-between; align-items: flex-start; gap: 20px; transition: all 0.2s; }
        .notif-item:hover { box-shadow: 0 6px 15px rgba(0,0,0,.08); }
        .notif-item.non_lu { border-left: 4px solid #3b82f6; background: #f8fbff; }
        .notif-item.lu { border-left: 4px solid #cbd5e1; opacity: 0.85; }

        .notif-content { flex: 1; min-width: 0; }
        .notif-header { display: flex; align-items: center; gap: 10px; margin-bottom: 6px; flex-wrap: wrap; }
        .notif-titre { font-weight: 700; color: #0f172a; font-size: 15px; }
        .notif-badge { display: inline-block; padding: 3px 10px; border-radius: 10px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .notif-message { color: #475569; font-size: 13px; line-height: 1.5; margin-bottom: 8px; }
        .notif-meta { display: flex; gap: 15px; flex-wrap: wrap; font-size: 12px; color: #64748b; }
        .notif-meta span { display: inline-flex; align-items: center; gap: 4px; }

        .notif-actions { display: flex; gap: 8px; flex-shrink: 0; align-self: center; }

        .empty-state { text-align: center; padding: 60px 20px; background: white; border-radius: 18px; }
        .empty-state .icon { font-size: 48px; display: block; margin-bottom: 15px; opacity: 0.5; }
        .empty-state h3 { color: #334155; font-size: 18px; margin-bottom: 8px; }

        .pagination { display: flex; justify-content: center; align-items: center; gap: 6px; margin-top: 20px; flex-wrap: wrap; }
        .pagination a, .pagination span { padding: 8px 14px; border-radius: 8px; font-weight: 600; font-size: 13px; text-decoration: none; }
        .pagination a { background: white; color: #334155; border: 1px solid #e2e8f0; transition: all 0.2s; }
        .pagination a:hover { background: #003366; color: white; border-color: #003366; }
        .pagination .active { background: #003366; color: white; border-color: #003366; }

        @media (max-width: 800px) {
            .form-grid { grid-template-columns: 1fr; }
            .notif-item { flex-direction: column; }
            .notif-actions { width: 100%; justify-content: flex-end; }
        }
    </style>
</head>
<body>

<div class="page-top-bar">
    <a href="administrateur.php">← Retour au tableau de bord</a>
    <span style="font-size:13px; color:#64748b;">Centre de notifications</span>
</div>

<div class="container">

    <div class="page-header">
        <h1>🔔 Centre de notifications</h1>
        <div class="header-actions">
            <span class="counter"><?= (int)$stats['total'] ?> au total</span>
            <span class="counter warn">⏳ <?= (int)$stats['non_lues'] ?> non lues</span>
            <span class="counter" style="background:#10b981;">✅ <?= (int)$stats['lues'] ?> lues</span>
        </div>
    </div>

    <?php if ($message_succes !== ''): ?>
        <div class="alert alert-success">✅ <?= htmlspecialchars($message_succes) ?></div>
    <?php endif; ?>
    <?php if ($message_erreur !== ''): ?>
        <div class="alert alert-error">❌ <?= htmlspecialchars($message_erreur) ?></div>
    <?php endif; ?>

    <!-- CRÉATION -->
    <div class="card">
        <h2>📢 Créer une notification</h2>
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <div class="form-grid">
                <div class="form-group">
                    <label>Titre *</label>
                    <input type="text" name="titre" placeholder="Ex: Maintenance du serveur" required>
                </div>
                <div class="form-group">
                    <label>Type</label>
                    <select name="type_evenement">
                        <option value="INFO">ℹ️ Information</option>
                        <option value="SUCCES">✅ Succès</option>
                        <option value="ALERT">⚠️ Alerte</option>
                        <option value="URGENT">🚨 Urgent</option>
                    </select>
                </div>
            </div>
            <div class="form-group" style="margin-bottom:15px;">
                <label>Message *</label>
                <textarea name="message" placeholder="Contenu de la notification..." required></textarea>
            </div>
            <button type="submit" name="creer_notif" class="btn btn-primary">🚀 Envoyer la notification</button>
        </form>
    </div>

    <!-- FILTRES -->
    <form method="GET" class="filters-bar">
        <div class="form-group">
            <label>Statut</label>
            <select name="statut">
                <option value="">— Tous —</option>
                <option value="non_lu" <?= ($filtre_statut === 'non_lu') ? 'selected' : '' ?>>Non lues</option>
                <option value="lu"     <?= ($filtre_statut === 'lu')     ? 'selected' : '' ?>>Lues</option>
            </select>
        </div>
        <div class="form-group">
            <label>Type</label>
            <select name="type">
                <option value="">— Tous —</option>
                <option value="INFO"   <?= ($filtre_type === 'INFO')   ? 'selected' : '' ?>>Information</option>
                <option value="SUCCES" <?= ($filtre_type === 'SUCCES') ? 'selected' : '' ?>>Succès</option>
                <option value="ALERT"  <?= ($filtre_type === 'ALERT')  ? 'selected' : '' ?>>Alerte</option>
                <option value="URGENT" <?= ($filtre_type === 'URGENT') ? 'selected' : '' ?>>Urgent</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">🔍 Filtrer</button>
        <a href="admin_notifications.php" class="btn btn-secondary">✖️ Réinitialiser</a>
    </form>

    <!-- LISTE -->
    <?php if (empty($notifications)): ?>
        <div class="empty-state">
            <span class="icon">📭</span>
            <h3>Aucune notification trouvée</h3>
        </div>
    <?php else: ?>
        <div class="notif-list">
            <?php foreach ($notifications as $n): 
                $badge = badge_type($n['type_evenement']);
                $statut_class = ($n['statut'] === 'non_lu') ? 'non_lu' : 'lu';
            ?>
                <div class="notif-item <?= $statut_class ?>">
                    <div class="notif-content">
                        <div class="notif-header">
                            <span class="notif-titre"><?= htmlspecialchars($n['titre']) ?></span>
                            <span class="notif-badge" style="background:<?= $badge['bg'] ?>; color:<?= $badge['color'] ?>;">
                                <?= htmlspecialchars($badge['label']) ?>
                            </span>
                        </div>
                        <div class="notif-message"><?= nl2br(htmlspecialchars($n['message'])) ?></div>
                        <div class="notif-meta">
                            <span>📅 <?= date('d/m/Y H:i', strtotime($n['date_creation'])) ?></span>
                            <span>📋 Source : <?= htmlspecialchars($n['source_table']) ?></span>
                            <span>🔖 <?= $n['statut'] === 'non_lu' ? '⏳ Non lue' : '✅ Lue' ?></span>
                        </div>
                    </div>
                    <div class="notif-actions">
                        <?php if ($n['statut'] === 'non_lu'): ?>
                            <form method="POST" action="" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                <input type="hidden" name="id_notif" value="<?= (int)$n['id_notification'] ?>">
                                <button type="submit" name="marquer_lu" class="btn btn-sm btn-secondary">✅ Lu</button>
                            </form>
                        <?php endif; ?>
                        <form method="POST" action="" style="display:inline;" onsubmit="return confirm('Supprimer cette notification ?');">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            <input type="hidden" name="id_notif" value="<?= (int)$n['id_notification'] ?>">
                            <button type="submit" name="supprimer_notif" class="btn btn-sm btn-danger">🗑️</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- PAGINATION -->
        <?php if ($nb_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?<?= build_query(['page' => $page - 1]) ?>">← Précédent</a>
                <?php endif; ?>

                <?php
                $debut_p = max(1, $page - 3);
                $fin_p   = min($nb_pages, $page + 3);
                if ($debut_p > 1) {
                    echo '<a href="?' . build_query(['page' => 1]) . '">1</a>';
                    if ($debut_p > 2) echo '<span>…</span>';
                }
                for ($i = $debut_p; $i <= $fin_p; $i++) {
                    if ($i == $page) {
                        echo '<span class="active">' . $i . '</span>';
                    } else {
                        echo '<a href="?' . build_query(['page' => $i]) . '">' . $i . '</a>';
                    }
                }
                if ($fin_p < $nb_pages) {
                    if ($fin_p < $nb_pages - 1) echo '<span>…</span>';
                    echo '<a href="?' . build_query(['page' => $nb_pages]) . '">' . $nb_pages . '</a>';
                }
                ?>

                <?php if ($page < $nb_pages): ?>
                    <a href="?<?= build_query(['page' => $page + 1]) ?>">Suivant →</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

</div>
</body>
</html>