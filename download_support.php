<?php
require_once __DIR__ . '/db.php';
session_start();
$is_shared_access = isset($_GET['token']);

// Authentification SEULEMENT si ce n'est pas un partage par lien
if (!$is_shared_access) {
    require_once 'auth.php';
}

try {
    $bdd = getDBConnection();
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Erreur : " . $e->getMessage());
}

// ============================================================
// RÉCUPÉRATION DU FICHIER (2 modes possibles)
// ============================================================
if ($is_shared_access) {
    // --- MODE PARTAGE (Lien public) ---
    $token = $_GET['token'];
    $stmt = $bdd->prepare("
        SELECT s.*, c.nom_cours 
        FROM supports_cours s 
        JOIN cours c ON s.id_cours = c.id_cours 
        WHERE s.share_token = ?
    ");
    $stmt->execute([$token]);
    $support = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$support) {
        die("⛔ Lien de partage invalide ou révoqué.");
    }
} else {
    // --- MODE NORMAL (Connecté) ---
    $id_support = (int)($_GET['id'] ?? 0);
    if ($id_support <= 0) die("ID invalide.");

    $stmt = $bdd->prepare("
        SELECT s.*, c.id_cours, c.nom_cours, s.id_classe
        FROM supports_cours s
        JOIN cours c ON s.id_cours = c.id_cours
        WHERE s.id_support = ?
    ");
    $stmt->execute([$id_support]);
    $support = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$support) die("Support introuvable.");

    // VÉRIFICATION DES DROITS
    $is_admin = ($USER_ROLE === 'Administrateur');
    $is_owner = false;
    $is_etudiant_classe = false;

    // Si c'est le prof propriétaire
    if ($USER_ROLE === 'Professeur') {
        $stmt_prof = $bdd->prepare("SELECT id_prof FROM professeurs WHERE id_user = ?");
        $stmt_prof->execute([$USER_ID]);
        $id_prof_user = $stmt_prof->fetchColumn();
        $is_owner = ($id_prof_user && $id_prof_user == $support['id_prof']);
    }

    // Si c'est un étudiant de la classe concernée
    if ($USER_ROLE === 'Etudiant') {
        $stmt_etu = $bdd->prepare("SELECT id_classe FROM etudiants WHERE id_user = ?");
        $stmt_etu->execute([$USER_ID]);
        $id_classe_etu = $stmt_etu->fetchColumn();
        $is_etudiant_classe = ($id_classe_etu && $id_classe_etu == $support['id_classe']);
    }

    if (!$is_admin && !$is_owner && !$is_etudiant_classe) {
        http_response_code(403);
        die("⛔ Vous n'avez pas accès à ce document.");
    }
}

// ============================================================
// ENVOI DU FICHIER
// ============================================================
$chemin = __DIR__ . '/uploads/supports/' . $support['fichier'];

if (!file_exists($chemin)) {
    die("Le fichier n'existe plus sur le serveur.");
}

// Journalisation (sauf si c'est un invité anonyme via token)
if (!$is_shared_access) {
    try {
        $bdd->prepare("INSERT INTO archivage (id_user, nom_utilisateur, prenom_utilisateur, role_utilisateur, action, table_concernee, id_concerned, description, adresse_ip, date_action) VALUES (?, ?, ?, ?, 'SELECT', 'supports_cours', ?, ?, ?, NOW())")
            ->execute([$USER_ID, $USER_NOM, $USER_PRENOM, $USER_ROLE, $support['id_support'], 'Téléchargement support : ' . $support['titre'], $_SERVER['REMOTE_ADDR'] ?? null]);
    } catch (Exception $e) { /* silencieux */ }
}

// Headers de téléchargement
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $chemin);
finfo_close($finfo);

header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . basename($support['titre']) . '_v' . ($support['version'] ?? 1) . '.' . $support['type_fichier'] . '"');
header('Content-Length: ' . filesize($chemin));
header('Cache-Control: no-store');

readfile($chemin);
exit;
?>