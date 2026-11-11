<?php
require_once __DIR__ . '/db.php';
// ============================================================
// auth.php — Garde-fou centralisé pour toutes les pages
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Vérifier la connexion
if (!isset($_SESSION['id_user']) || (int)$_SESSION['id_user'] <= 0) {
    header('Location: login.php');
    exit();
}

// 2. Timeout de session (30 min d'inactivité)
$timeout = 1800;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
    session_unset();
    session_destroy();
    header('Location: login.php?timeout=1');
    exit();
}
$_SESSION['last_activity'] = time();

// 3. Mettre à jour la dernière activité en BDD
try {
    $bdd_auth = getDBConnection();
    $bdd_auth->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $bdd_auth->prepare("UPDATE user_sessions SET derniere_activite = NOW() WHERE id_user = ? AND statut = 'Connecte'");
    $stmt->execute([$_SESSION['id_user']]);
} catch (Exception $e) {
    error_log("[auth] " . $e->getMessage());
}

// 4. Fonction de contrôle de rôle
function requireRole($roles) {
    if (!is_array($roles)) $roles = [$roles];
    if (!in_array($_SESSION['role'], $roles)) {
        http_response_code(403);
        die("⛔ Accès refusé. Rôle requis : " . implode(', ', $roles) . ". Votre rôle : " . $_SESSION['role']);
    }
}

// 5. Variables raccourcies pour toutes les pages
$USER_ID = (int)$_SESSION['id_user'];
$USER_ROLE = $_SESSION['role'];
$USER_NOM = $_SESSION['nom'] ?? '';
$USER_PRENOM = $_SESSION['prenom'] ?? '';
$USER_NAME = trim($USER_PRENOM . ' ' . $USER_NOM);
$USER_PHOTO = $_SESSION['photo'] ?? 'default.png';

// ============================================================
// 6. FORCER LE CHANGEMENT DE MOT DE PASSE À LA PREMIÈRE CONNEXION
// ============================================================
$page_courante = basename($_SERVER['PHP_SELF']);

// On exclut les pages qui ne doivent pas être bloquées
$pages_exclues = ['changer_mot_de_passe.php', 'logout.php', 'login.php', 'verifier_2fa.php'];

if (!in_array($page_courante, $pages_exclues) && isset($bdd_auth)) {
    try {
        $check_mdp = $bdd_auth->prepare("SELECT premiere_connexion FROM users WHERE id_user = ? LIMIT 1");
        $check_mdp->execute([$USER_ID]);
        $premiere = $check_mdp->fetchColumn();
        
        if ($premiere === 'Oui') {
            header('Location: changer_mot_de_passe.php?force=1');
            exit();
        }
    } catch (Exception $e) {
        error_log("[auth] Erreur verif premiere_connexion : " . $e->getMessage());
    }
}
?>