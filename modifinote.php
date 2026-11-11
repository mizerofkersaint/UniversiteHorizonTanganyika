<?php
require_once __DIR__ . '/db.php';
require_once 'auth.php';
requireRole(['Administrateur', 'Doyen', 'Professeur']);
try {
    $bdd = getDBConnection();
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

include("menu.php");

$id_note = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id_note <= 0) {
    die("ID de la note invalide.");
}

/* ===========================
   RECUPERATION DE LA NOTE AVEC FACULTE
=========================== */
$affiche = $bdd->prepare("
    SELECT n.*, e.id_etudiant, u.nom, u.prenom, co.nom_cours,
           d.nom_dept, d.faculte, cl.niveau
    FROM notes n
    INNER JOIN etudiants e ON n.id_etudiant = e.id_etudiant
    INNER JOIN users u ON e.id_user = u.id_user
    INNER JOIN cours co ON n.id_cours = co.id_cours
    INNER JOIN classes cl ON co.id_classe = cl.id_classe
    INNER JOIN departements d ON cl.id_dept = d.id_dept
    WHERE n.id_note = ?
");
$affiche->execute([$id_note]);
$data = $affiche->fetch();

if (!$data) {
    die("Note introuvable.");
}

/* ===========================
   ETUDIANTS (avec faculté via département)
=========================== */
$etudiants = $bdd->query("
    SELECT e.id_etudiant, u.nom, u.prenom, d.nom_dept, d.faculte
    FROM etudiants e
    INNER JOIN users u ON e.id_user = u.id_user
    INNER JOIN classes cl ON e.id_classe = cl.id_classe
    INNER JOIN departements d ON cl.id_dept = d.id_dept
    ORDER BY u.nom ASC
")->fetchAll();

/* ===========================
   COURS
=========================== */
$cours = $bdd->query("
    SELECT id_cours, nom_cours
    FROM cours
    ORDER BY nom_cours ASC
")->fetchAll();

/* ===========================
   MODIFICATION (Transaction PDO sécurisée)
=========================== */
if (isset($_POST["modifier"])) {
    $etudiant = $_POST["etudiant"];
    $cours1   = $_POST["cours"];
    $note     = $_POST["note"];
    $session  = $_POST["session"];

    try {
        $bdd->beginTransaction();

        $modifier = $bdd->prepare("
            UPDATE notes
            SET
                id_etudiant = ?,
                id_cours = ?,
                note = ?,
                session = ?,
                date_saisie = CURDATE()
            WHERE id_note = ?
        ");
        $modifier->execute([
            $etudiant,
            $cours1,
            $note,
            $session,
            $id_note
        ]);

        $bdd->commit();

        echo "<script>
            alert('Note modifiée avec succès');
            window.location='affichenote.php';
        </script>";
        exit();
    } catch (Exception $e) {
        $bdd->rollBack();
        echo "<script>alert('Erreur lors de la modification : " . addslashes($e->getMessage()) . "');</script>";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Modifier une Note</title>
<style>
*{ margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI',Arial,sans-serif; }
body{ background:#eef3f8; }
.content{ width:100%; display:flex; justify-content:center; padding:40px; }
.form-card{ width:750px; background:#fff; border-radius:18px; padding:35px; box-shadow:0 10px 25px rgba(0,0,0,.08); }
.form-card h2{ text-align:center; color:#003366; margin-bottom:30px; font-size:28px; }
.form-group{ margin-bottom:20px; }
.form-group label{ display:block; margin-bottom:8px; font-weight:bold; color:#003366; }
input, select{ width:100%; height:50px; border:1px solid #d1d5db; border-radius:10px; padding:0 15px; font-size:15px; outline:none; transition:.3s; }
input:focus, select:focus{ border-color:#0066cc; box-shadow:0 0 0 4px rgba(0,102,204,.15); }
.buttons{ margin-top:30px; display:flex; justify-content:center; gap:20px; }
.btn1{ background:#16a34a; color:white; border:none; padding:13px 28px; border-radius:10px; font-size:15px; font-weight:bold; cursor:pointer; transition:.3s; }
.btn1:hover{ background:#