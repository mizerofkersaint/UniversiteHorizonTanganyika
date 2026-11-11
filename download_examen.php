<?php
require_once __DIR__ . '/db.php';
// ============================================================
// TÉLÉCHARGEMENT SÉCURISÉ D'UN EXAMEN
// ============================================================
require_once 'auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$id_user = $USER_ID;
$id_examen = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id_examen <= 0) die("ID examen invalide.");

try {
    $bdd = getDBConnection();
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

$check = $bdd->prepare("
    SELECT e.sujet_fichier, e.id_prof, c.id_classe, cl.id_dept
    FROM examens e
    JOIN cours c ON e.id_cours = c.id_cours
    JOIN classes cl ON c.id_classe = cl.id_classe
    WHERE e.id_examen = ?
");
$check->execute([$id_examen]);
$examen = $check->fetch(PDO::FETCH_ASSOC);

if (!$examen) {
    die("Examen introuvable.");
}

/* ============================================================
   VÉRIFICATION DES DROITS (corrigée)
============================================================ */
$autorise = false;

// 1. Professeur propriétaire
$stmtProf = $bdd->prepare("SELECT id_prof FROM professeurs WHERE id_user = ?");
$stmtProf->execute([$id_user]);
$id_prof_user = $stmtProf->fetchColumn();

if ($id_prof_user && (int)$id_prof_user === (int)$examen['id_prof']) {
    $autorise = true;
}

// 2. Doyen du département
if (!$autorise && $USER_ROLE === 'Doyen') {
    $stmtDoyen = $bdd->prepare("SELECT id_doyen FROM doyens WHERE id_user = ? AND id_dept = ?");
    $stmtDoyen->execute([$id_user, $examen['id_dept']]);
    if ($stmtDoyen->fetch()) {
        $autorise = true;
    }
}

// 3. Administrateur / Recteur
if (!$autorise && in_array($USER_ROLE, ['Administrateur', 'Recteur'], true)) {
    $autorise = true;
}

// 4. Étudiant de la classe concernée
if (!$autorise && $USER_ROLE === 'Etudiant') {
    $stmtEtu = $bdd->prepare("SELECT id_classe FROM etudiants WHERE id_user = ? AND id_classe = ?");
    $stmtEtu->execute([$id_user, $examen['id_classe']]);
    if ($stmtEtu->fetch()) {
        $autorise = true;
    }
}

if (!$autorise) {
    http_response_code(403);
    die("⛔ Vous n'êtes pas autorisé à télécharger ce fichier.");
}

/* ============================================================
   ENVOI DU FICHIER
============================================================ */
$fichier = basename($examen['sujet_fichier']);
$chemin = 'uploads/examens/' . $fichier;

if (!file_exists($chemin) || !is_file($chemin)) {
    die("Le fichier n'existe plus sur le serveur.");
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $chemin);
finfo_close($finfo);

header('Content-Type: ' . $mime_type);
header('Content-Disposition: attachment; filename="' . $fichier . '"');
header('Content-Length: ' . filesize($chemin));
header('Cache-Control: no-store');
readfile($chemin);
exit;
?>