<?php
require_once __DIR__ . '/db.php';
require_once 'auth.php';
requireRole(['Professeur', 'Administrateur', 'Doyen']);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $bdd = getDBConnection();
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

$id_cours = intval($_GET['id_cours'] ?? 0);
$session_choisie = trim($_GET['session'] ?? '1ère Session');

if ($id_cours <= 0) {
    die("Cours invalide");
}

/* ============================================================
   VÉRIFICATION DES DROITS
============================================================ */
$coursAutorise = false;

if ($USER_ROLE === 'Professeur') {
    // Le prof doit posséder le cours
    $stmt = $bdd->prepare("SELECT id_prof FROM professeurs WHERE id_user = ?");
    $stmt->execute([$USER_ID]);
    $id_prof_user = $stmt->fetchColumn();

    if ($id_prof_user) {
        $check = $bdd->prepare("SELECT id_cours FROM cours WHERE id_cours = ? AND id_prof = ?");
        $check->execute([$id_cours, $id_prof_user]);
        $coursAutorise = (bool)$check->fetch();
    }
} elseif ($USER_ROLE === 'Administrateur') {
    // L'admin peut tout exporter
    $coursAutorise = true;
} elseif ($USER_ROLE === 'Doyen') {
    // Le doyen peut exporter les cours de son département
    $stmt = $bdd->prepare("SELECT id_dept FROM doyens WHERE id_user = ?");
    $stmt->execute([$USER_ID]);
    $id_dept_doyen = $stmt->fetchColumn();

    if ($id_dept_doyen) {
        $check = $bdd->prepare("
            SELECT c.id_cours FROM cours c
            JOIN classes cl ON c.id_classe = cl.id_classe
            WHERE c.id_cours = ? AND cl.id_dept = ?
        ");
        $check->execute([$id_cours, $id_dept_doyen]);
        $coursAutorise = (bool)$check->fetch();
    }
}

if (!$coursAutorise) {
    http_response_code(403);
    die("⛔ Vous n'êtes pas autorisé à exporter les notes de ce cours.");
}

/* ============================================================
   RÉCUPÉRATION DES DONNÉES
============================================================ */
$req = $bdd->prepare("
    SELECT e.matricule, u.nom, u.prenom, n.note
    FROM etudiants e
    INNER JOIN users u ON e.id_user = u.id_user
    INNER JOIN classes cl ON e.id_classe = cl.id_classe
    INNER JOIN cours c ON c.id_classe = cl.id_classe
    LEFT JOIN notes n ON n.id_etudiant = e.id_etudiant AND n.id_cours = c.id_cours AND n.session = ?
    WHERE c.id_cours = ?
    ORDER BY u.nom, u.prenom
");
$req->execute([$session_choisie, $id_cours]);
$notes = $req->fetchAll(PDO::FETCH_ASSOC);

$reqCours = $bdd->prepare("SELECT nom_cours FROM cours WHERE id_cours = ?");
$reqCours->execute([$id_cours]);
$cours = $reqCours->fetch(PDO::FETCH_ASSOC);

if (!$cours) {
    die("Cours introuvable");
}

header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=Notes_" . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $cours['nom_cours']) . "_" . date('Y-m-d') . ".xls");
echo "\xEF\xBB\xBF";

echo "<table border='1'>";
echo "<tr><th colspan='4' style='background-color:#0f172a; color:white; font-size:14px;'>Notes - " . htmlspecialchars($cours['nom_cours']) . " (" . htmlspecialchars($session_choisie) . ")</th></tr>";
echo "<tr><th style='font-weight:bold;'>Matricule</th><th style='font-weight:bold;'>Nom</th><th style='font-weight:bold;'>Prénom</th><th style='font-weight:bold;'>Note /20</th></tr>";

foreach ($notes as $n) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($n['matricule']) . "</td>";
    echo "<td>" . htmlspecialchars($n['nom']) . "</td>";
    echo "<td>" . htmlspecialchars($n['prenom']) . "</td>";
    echo "<td style='text-align:center;'>" . ($n['note'] !== null ? number_format($n['note'], 2) : '-') . "</td>";
    echo "</tr>";
}

echo "</table>";
exit;
?>