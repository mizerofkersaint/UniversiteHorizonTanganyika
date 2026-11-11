<?php
require_once __DIR__ . '/db.php';
require_once 'auth.php';
requireRole('Professeur');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

try {
    $bdd = getDBConnection();
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur de connexion']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

/* ============================================================
   RÉCUPÉRATION DU PROFESSEUR
============================================================ */
$stmtProf = $bdd->prepare("SELECT id_prof FROM professeurs WHERE id_user = ?");
$stmtProf->execute([$USER_ID]);
$id_prof = $stmtProf->fetchColumn();

if (!$id_prof) {
    echo json_encode(['success' => false, 'message' => 'Profil professeur introuvable']);
    exit;
}

/* ============================================================
   PARAMÈTRES
============================================================ */
$id_etudiant = intval($_POST['id_etudiant'] ?? 0);
$id_cours    = intval($_POST['id_cours'] ?? 0);
$session     = trim($_POST['session'] ?? '');
$note        = floatval($_POST['note'] ?? -1);

if ($id_etudiant <= 0 || $id_cours <= 0 || $note < 0 || $note > 20 || empty($session)) {
    echo json_encode(['success' => false, 'message' => 'Données invalides']);
    exit;
}

/* ============================================================
   ✅ VÉRIFICATION : le cours appartient au prof
============================================================ */
$checkCours = $bdd->prepare("SELECT id_cours FROM cours WHERE id_cours = ? AND id_prof = ?");
$checkCours->execute([$id_cours, $id_prof]);

if (!$checkCours->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Accès refusé à ce cours']);
    exit;
}

/* ============================================================
   ✅ VÉRIFICATION : l'étudiant est bien inscrit dans la classe du cours
============================================================ */
$checkEtudiant = $bdd->prepare("
    SELECT e.id_etudiant 
    FROM etudiants e
    JOIN cours c ON e.id_classe = c.id_classe
    WHERE e.id_etudiant = ? AND c.id_cours = ?
");
$checkEtudiant->execute([$id_etudiant, $id_cours]);

if (!$checkEtudiant->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Étudiant non inscrit dans ce cours']);
    exit;
}

/* ============================================================
   INSERTION / MISE À JOUR
============================================================ */
try {
    $check = $bdd->prepare("SELECT id_note FROM notes WHERE id_etudiant = ? AND id_cours = ? AND session = ?");
    $check->execute([$id_etudiant, $id_cours, $session]);
    $existing = $check->fetch();

    if ($existing) {
        $update = $bdd->prepare("UPDATE notes SET note = ?, date_saisie = CURDATE() WHERE id_note = ?");
        $update->execute([$note, $existing['id_note']]);
    } else {
        $insert = $bdd->prepare("INSERT INTO notes (id_etudiant, id_cours, note, session, date_saisie) VALUES (?, ?, ?, ?, CURDATE())");
        $insert->execute([$id_etudiant, $id_cours, $note, $session]);
    }

    echo json_encode(['success' => true, 'message' => 'Note sauvegardée']);
} catch (Exception $e) {
    error_log("[sauvegarder_note_ajax] " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la sauvegarde']);
}
?>