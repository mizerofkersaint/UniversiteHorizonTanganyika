<?php
require_once __DIR__ . '/db.php';
// ============================================================
// DASHBOARD DOYEN - UniVerse (Refonte Design)
// ============================================================
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'auth.php';
requireRole('Doyen');

try {
    $bdd = getDBConnection();
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

$id_user = $USER_ID;

$stmtDoyen = $bdd->prepare("SELECT id_dept FROM doyens WHERE id_user = ?");
$stmtDoyen->execute([$id_user]);
$id_dept_doyen = $stmtDoyen->fetchColumn();

if (!$id_dept_doyen) {
    die("Aucun département assigné à ce doyen. Contactez l'administration.");
}

$stmtUser = $bdd->prepare("SELECT nom, prenom, photo, role FROM users WHERE id_user = ?");
$stmtUser->execute([$id_user]);
$user = $stmtUser->fetch(PDO::FETCH_ASSOC);

define('MINERVAL_ANNUEL', 500000);

$message = "";
$message_type = "";
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

// ============================================================
// VARIABLES POUR IFRAME
// ============================================================
$vue_crud = isset($_GET['crud']) ? trim($_GET['crud']) : '';
$url_iframe = '';
$titre_iframe = '';

if ($vue_crud !== '') {
    $pages_crud_autorisees = [
        'classe'    => ['url' => 'afficheclasse.php', 'titre' => 'Gestion des classes'],
        'classe_add'=> ['url' => 'ajouteclassedepartement.php', 'titre' => 'Ajouter une classe'],
        'cours'     => ['url' => 'affichecours.php', 'titre' => 'Gestion des cours'],
        'cours_add' => ['url' => 'ajoutecours.php', 'titre' => 'Ajouter un cours'],
        'note'      => ['url' => 'affichenote.php', 'titre' => 'Gestion des notes'],
        'note_add'  => ['url' => 'ajoutenote.php', 'titre' => 'Ajouter une note'],
        'etudiant'  => ['url' => 'AffichageEtudiants.php', 'titre' => 'Gestion des étudiants'],
        'etudiant_add' => ['url' => 'ajouteetudiant.php', 'titre' => 'Ajouter un étudiant'],
        'prof'      => ['url' => 'afficheprofesseur.php', 'titre' => 'Gestion des professeurs'],
        'prof_add'  => ['url' => 'ajouteprofesseur.php', 'titre' => 'Ajouter un professeur'],
    ];
    if (isset($pages_crud_autorisees[$vue_crud])) {
        $url_iframe = $pages_crud_autorisees[$vue_crud]['url'];
        $titre_iframe = $pages_crud_autorisees[$vue_crud]['titre'];
    }
}

// ============================================================
// TRAITEMENT DES ACTIONS
// ============================================================

if (isset($_GET['valider_note']) && !empty($_GET['valider_note'])) {
    $id_note = intval($_GET['valider_note']);
    try {
        $bdd->beginTransaction();
        $check = $bdd->prepare("SELECT n.*, cl.id_dept, u.nom, u.prenom, co.nom_cours 
                                FROM notes n 
                                JOIN cours co ON n.id_cours = co.id_cours 
                                JOIN classes cl ON co.id_classe = cl.id_classe 
                                JOIN etudiants e ON n.id_etudiant = e.id_etudiant 
                                JOIN users u ON e.id_user = u.id_user 
                                WHERE n.id_note = ? AND n.statut = 'soumis' AND cl.id_dept = ?");
        $check->execute([$id_note, $id_dept_doyen]);
        $note = $check->fetch(PDO::FETCH_ASSOC);
        if ($note) {
            $upd = $bdd->prepare("UPDATE notes SET statut = 'valide', date_validation = NOW(), id_doyen = ? WHERE id_note = ?");
            $upd->execute([$id_user, $id_note]);
            $bdd->commit();
            $message = "<div class='alert success'>✅ Note de " . htmlspecialchars($note['nom'] . ' ' . $note['prenom']) . " pour " . htmlspecialchars($note['nom_cours']) . " validée.</div>";
            $message_type = "success";
        } else {
            $message = "<div class='alert error'>❌ Note introuvable ou hors de votre département.</div>";
            $message_type = "error";
        }
    } catch (Exception $e) {
        $bdd->rollBack();
        $message = "<div class='alert error'>❌ Erreur : " . $e->getMessage() . "</div>";
        $message_type = "error";
    }
}

if (isset($_POST['rejeter_note'])) {
    $id_note = intval($_POST['id_note']);
    $motif = trim($_POST['motif_rejet']);
    if (empty($motif)) {
        $message = "<div class='alert error'>❌ Le motif de rejet est obligatoire.</div>";
        $message_type = "error";
    } else {
        try {
            $bdd->beginTransaction();
            $check = $bdd->prepare("SELECT n.*, cl.id_dept, u.nom, u.prenom, co.nom_cours 
                                    FROM notes n 
                                    JOIN cours co ON n.id_cours = co.id_cours 
                                    JOIN classes cl ON co.id_classe = cl.id_classe 
                                    JOIN etudiants e ON n.id_etudiant = e.id_etudiant 
                                    JOIN users u ON e.id_user = u.id_user 
                                    WHERE n.id_note = ? AND n.statut = 'soumis' AND cl.id_dept = ?");
            $check->execute([$id_note, $id_dept_doyen]);
            $note = $check->fetch(PDO::FETCH_ASSOC);
            if ($note) {
                $upd = $bdd->prepare("UPDATE notes SET statut = 'rejete', motif_rejet = ?, date_validation = NOW(), id_doyen = ? WHERE id_note = ?");
                $upd->execute([$motif, $id_user, $id_note]);
                $bdd->commit();
                $message = "<div class='alert error'>⛔ Note rejetée. Motif : " . htmlspecialchars($motif) . "</div>";
                $message_type = "error";
            }
        } catch (Exception $e) {
            $bdd->rollBack();
            $message = "<div class='alert error'>❌ Erreur : " . $e->getMessage() . "</div>";
        }
    }
}

if (isset($_POST['publier_note'])) {
    $id_note = intval($_POST['id_note']);
    try {
        $bdd->beginTransaction();
        $check = $bdd->prepare("SELECT n.*, cl.id_dept FROM notes n 
                                JOIN cours co ON n.id_cours = co.id_cours 
                                JOIN classes cl ON co.id_classe = cl.id_classe 
                                WHERE n.id_note = ? AND n.statut = 'valide' AND cl.id_dept = ?");
        $check->execute([$id_note, $id_dept_doyen]);
        $note = $check->fetch(PDO::FETCH_ASSOC);
        if ($note) {
            $checkFin = $bdd->prepare("SELECT COALESCE(SUM(montant), 0) as total_paye FROM paiements WHERE id_etudiant = ? AND statut = 'valide'");
            $checkFin->execute([$note['id_etudiant']]);
            $totalPaye = $checkFin->fetchColumn() ?: 0;
            
            if ($totalPaye >= MINERVAL_ANNUEL) {
                $upd = $bdd->prepare("UPDATE notes SET date_publication = NOW() WHERE id_note = ?");
                $upd->execute([$id_note]);
                $bdd->commit();
                $message = "<div class='alert success'>✅ Résultat publié.</div>";
                $message_type = "success";
            } else {
                $message = "<div class='alert warning'>⚠ Publication bloquée. Minerval insuffisant.</div>";
                $message_type = "warning";
            }
        }
    } catch (Exception $e) {
        $bdd->rollBack();
        $message = "<div class='alert error'>❌ Erreur : " . $e->getMessage() . "</div>";
    }
}

if (isset($_GET['valider_examen']) && !empty($_GET['valider_examen'])) {
    $id_examen = intval($_GET['valider_examen']);
    try {
        $bdd->beginTransaction();
        $check = $bdd->prepare("SELECT e.*, co.nom_cours, cl.id_dept FROM examens e 
                                JOIN cours co ON e.id_cours = co.id_cours 
                                JOIN classes cl ON e.id_classe = cl.id_classe 
                                WHERE e.id_examen = ? AND e.statut = 'en_attente' AND cl.id_dept = ?");
        $check->execute([$id_examen, $id_dept_doyen]);
        if ($check->fetch()) {
            $upd = $bdd->prepare("UPDATE examens SET statut = 'valide', date_validation = NOW(), id_doyen = ? WHERE id_examen = ?");
            $upd->execute([$id_user, $id_examen]);
            $bdd->commit();
            $message = "<div class='alert success'>✅ Examen validé.</div>";
            $message_type = "success";
        }
    } catch (Exception $e) { $bdd->rollBack(); }
}

if (isset($_GET['desactiver_professeur']) && !empty($_GET['desactiver_professeur'])) {
    $id_prof = intval($_GET['desactiver_professeur']);
    try {
        $bdd->beginTransaction();
        $check = $bdd->prepare("SELECT p.id_prof, p.id_user FROM professeurs p 
                                JOIN cours c ON p.id_prof = c.id_prof 
                                JOIN classes cl ON c.id_classe = cl.id_classe 
                                WHERE p.id_prof = ? AND cl.id_dept = ?");
        $check->execute([$id_prof, $id_dept_doyen]);
        if ($check->fetch()) {
            $bdd->prepare("UPDATE users SET statut = 'Inactif' WHERE id_user = (SELECT id_user FROM professeurs WHERE id_prof = ?)")->execute([$id_prof]);
            $bdd->commit();
            $message = "<div class='alert success'>✅ Professeur désactivé.</div>";
            $message_type = "success";
        }
    } catch (Exception $e) { $bdd->rollBack(); }
}

if (isset($_POST['ajouter_affectation'])) {
    $id_cours = intval($_POST['id_cours']);
    $id_prof = intval($_POST['id_prof']);
    try {
        $bdd->beginTransaction();
        $check = $bdd->prepare("SELECT id_cours FROM cours c JOIN classes cl ON c.id_classe = cl.id_classe WHERE c.id_cours = ? AND cl.id_dept = ?");
        $check->execute([$id_cours, $id_dept_doyen]);
        if ($check->fetch()) {
            $bdd->prepare("UPDATE cours SET id_prof = ? WHERE id_cours = ?")->execute([$id_prof == 0 ? null : $id_prof, $id_cours]);
            $bdd->commit();
            $message = "<div class='alert success'>✅ Affectation mise à jour.</div>";
            $message_type = "success";
        }
    } catch (Exception $e) { $bdd->rollBack(); }
}

if (isset($_POST['modifier_credits'])) {
    $id_cours = intval($_POST['id_cours']);
    $nouveau_credit = intval($_POST['credit']);
    if ($nouveau_credit > 0) {
        try {
            $bdd->beginTransaction();
            $check = $bdd->prepare("SELECT c.id_cours FROM cours c JOIN classes cl ON c.id_classe = cl.id_classe WHERE c.id_cours = ? AND cl.id_dept = ?");
            $check->execute([$id_cours, $id_dept_doyen]);
            if ($check->fetch()) {
                $bdd->prepare("UPDATE cours SET credit = ? WHERE id_cours = ?")->execute([$nouveau_credit, $id_cours]);
                $bdd->commit();
                $message = "<div class='alert success'>✅ Crédits mis à jour.</div>";
                $message_type = "success";
            }
        } catch (Exception $e) { $bdd->rollBack(); }
    }
}

if (isset($_POST['ajouter_professeur'])) {
    $nom = trim($_POST['nom']); $prenom = trim($_POST['prenom']);
    $email = trim($_POST['email']); $telephone = trim($_POST['telephone']);
    $specialite = trim($_POST['specialite']); $diplome = trim($_POST['diplome']);
    
    if (!empty($nom) && !empty($prenom) && !empty($email)) {
        try {
            $bdd->beginTransaction();
            $username = strtolower(substr($prenom, 0, 1) . '.' . str_replace(' ', '', $nom));
            $password = password_hash('123456', PASSWORD_BCRYPT);
            
            $insertUser = $bdd->prepare("INSERT INTO users (nom, prenom, email, telephone, username, mot_de_passe, role, statut) VALUES (?, ?, ?, ?, ?, ?, 'Professeur', 'Actif')");
            $insertUser->execute([$nom, $prenom, $email, $telephone, $username, $password]);
            $id_user_new = $bdd->lastInsertId();
            
            $bdd->prepare("INSERT INTO professeurs (id_user, specialite, diplome) VALUES (?, ?, ?)")->execute([$id_user_new, $specialite, $diplome]);
            $bdd->commit();
            $message = "<div class='alert success'>✅ Professeur ajouté (mot de passe: 123456).</div>";
            $message_type = "success";
        } catch (Exception $e) { $bdd->rollBack(); $message = "<div class='alert error'>❌ " . $e->getMessage() . "</div>"; }
    }
}

if (isset($_POST['ajouter_classe'])) {
    $niveau = trim($_POST['niveau']);
    if (!empty($niveau)) {
        try {
            $bdd->prepare("INSERT INTO classes (niveau, id_dept) VALUES (?, ?)")->execute([$niveau, $id_dept_doyen]);
            $message = "<div class='alert success'>✅ Classe ajoutée.</div>";
            $message_type = "success";
        } catch (Exception $e) { $message = "<div class='alert error'>❌ " . $e->getMessage() . "</div>"; }
    }
}

if (isset($_POST['ajouter_cours'])) {
    $nom_cours = trim($_POST['nom_cours']);
    $credit = intval($_POST['credit']);
    $semestre = trim($_POST['semestre']);
    $id_classe = intval($_POST['id_classe']);
    if (!empty($nom_cours) && $credit > 0 && $id_classe > 0) {
        try {
            $bdd->prepare("INSERT INTO cours (nom_cours, credit, semestre, id_classe, id_prof) VALUES (?, ?, ?, ?, NULL)")->execute([$nom_cours, $credit, $semestre, $id_classe]);
            $message = "<div class='alert success'>✅ Cours ajouté.</div>";
            $message_type = "success";
        } catch (Exception $e) { $message = "<div class='alert error'>❌ " . $e->getMessage() . "</div>"; }
    }
}

if (isset($_POST['modifier_professeur'])) {
    $id_prof = intval($_POST['id_prof']);
    $nom = trim($_POST['nom']); $prenom = trim($_POST['prenom']);
    $email = trim($_POST['email']); $telephone = trim($_POST['telephone']);
    $specialite = trim($_POST['specialite']); $diplome = trim($_POST['diplome']);
    try {
        $bdd->beginTransaction();
        $check = $bdd->prepare("SELECT id_user FROM professeurs WHERE id_prof = ?");
        $check->execute([$id_prof]);
        $id_user_prof = $check->fetchColumn();
        if ($id_user_prof) {
            $bdd->prepare("UPDATE users SET nom = ?, prenom = ?, email = ?, telephone = ? WHERE id_user = ?")->execute([$nom, $prenom, $email, $telephone, $id_user_prof]);
            $bdd->prepare("UPDATE professeurs SET specialite = ?, diplome = ? WHERE id_prof = ?")->execute([$specialite, $diplome, $id_prof]);
            $bdd->commit();
            $message = "<div class='alert success'>✅ Professeur modifié.</div>";
            $message_type = "success";
        }
    } catch (Exception $e) { $bdd->rollBack(); }
}

if (isset($_POST['modifier_classe'])) {
    $id_classe = intval($_POST['id_classe']);
    $niveau = trim($_POST['niveau']);
    if (!empty($niveau)) {
        try {
            $bdd->prepare("UPDATE classes SET niveau = ? WHERE id_classe = ? AND id_dept = ?")->execute([$niveau, $id_classe, $id_dept_doyen]);
            $message = "<div class='alert success'>✅ Classe modifiée.</div>";
            $message_type = "success";
        } catch (Exception $e) { $message = "<div class='alert error'>❌ " . $e->getMessage() . "</div>"; }
    }
}

if (isset($_POST['modifier_cours'])) {
    $id_cours = intval($_POST['id_cours']);
    $nom_cours = trim($_POST['nom_cours']);
    $credit = intval($_POST['credit']);
    $semestre = trim($_POST['semestre']);
    $id_classe = intval($_POST['id_classe']);
    if (!empty($nom_cours) && $credit > 0 && $id_classe > 0) {
        try {
            $bdd->prepare("UPDATE cours SET nom_cours = ?, credit = ?, semestre = ?, id_classe = ? WHERE id_cours = ?")->execute([$nom_cours, $credit, $semestre, $id_classe, $id_cours]);
            $message = "<div class='alert success'>✅ Cours modifié.</div>";
            $message_type = "success";
        } catch (Exception $e) { $message = "<div class='alert error'>❌ " . $e->getMessage() . "</div>"; }
    }
}

// ============================================================
// STATISTIQUES
// ============================================================
$totalProfs = $bdd->prepare("SELECT COUNT(DISTINCT p.id_prof) FROM professeurs p JOIN cours c ON p.id_prof = c.id_prof JOIN classes cl ON c.id_classe = cl.id_classe WHERE cl.id_dept = ?");
$totalProfs->execute([$id_dept_doyen]); $totalProfs = $totalProfs->fetchColumn() ?: 0;

$totalEtudiants = $bdd->prepare("SELECT COUNT(DISTINCT e.id_etudiant) FROM etudiants e JOIN classes cl ON e.id_classe = cl.id_classe WHERE cl.id_dept = ?");
$totalEtudiants->execute([$id_dept_doyen]); $totalEtudiants = $totalEtudiants->fetchColumn() ?: 0;

$totalClasses = $bdd->prepare("SELECT COUNT(*) FROM classes WHERE id_dept = ?");
$totalClasses->execute([$id_dept_doyen]); $totalClasses = $totalClasses->fetchColumn() ?: 0;

$totalCours = $bdd->prepare("SELECT COUNT(DISTINCT c.id_cours) FROM cours c JOIN classes cl ON c.id_classe = cl.id_classe WHERE cl.id_dept = ?");
$totalCours->execute([$id_dept_doyen]); $totalCours = $totalCours->fetchColumn() ?: 0;

$notesAValider = $bdd->prepare("SELECT COUNT(n.id_note) FROM notes n JOIN cours c ON n.id_cours = c.id_cours JOIN classes cl ON c.id_classe = cl.id_classe WHERE cl.id_dept = ? AND n.statut = 'soumis'");
$notesAValider->execute([$id_dept_doyen]); $notesAValider = $notesAValider->fetchColumn() ?: 0;

$examensAValider = $bdd->prepare("SELECT COUNT(e.id_examen) FROM examens e JOIN cours c ON e.id_cours = c.id_cours JOIN classes cl ON c.id_classe = cl.id_classe WHERE cl.id_dept = ? AND e.statut = 'en_attente'");
$examensAValider->execute([$id_dept_doyen]); $examensAValider = $examensAValider->fetchColumn() ?: 0;

$etudiantsNonEnOrdre = $bdd->prepare("SELECT COUNT(DISTINCT e.id_etudiant) FROM etudiants e JOIN classes cl ON e.id_classe = cl.id_classe LEFT JOIN paiements p ON e.id_etudiant = p.id_etudiant AND p.statut = 'valide' WHERE cl.id_dept = ? GROUP BY e.id_etudiant HAVING COALESCE(SUM(p.montant), 0) < ?");
$etudiantsNonEnOrdre->execute([$id_dept_doyen, MINERVAL_ANNUEL]);
$etudiantsNonEnOrdre = $etudiantsNonEnOrdre->rowCount() ?: 0;

$notifsNonLues = $bdd->prepare("SELECT COUNT(*) FROM notifications WHERE statut = 'non_lu' AND (id_dept = ? OR id_dept IS NULL)");
$notifsNonLues->execute([$id_dept_doyen]); $notifsNonLues = $notifsNonLues->fetchColumn() ?: 0;

$resultatsAPublier = $bdd->prepare("SELECT COUNT(n.id_note) FROM notes n JOIN cours co ON n.id_cours = co.id_cours JOIN classes cl ON co.id_classe = cl.id_classe WHERE cl.id_dept = ? AND n.statut = 'valide' AND n.date_publication IS NULL");
$resultatsAPublier->execute([$id_dept_doyen]); $resultatsAPublier = $resultatsAPublier->fetchColumn() ?: 0;

$coursSansProf = $bdd->prepare("SELECT COUNT(c.id_cours) FROM cours c JOIN classes cl ON c.id_classe = cl.id_classe WHERE cl.id_dept = ? AND c.id_prof IS NULL");
$coursSansProf->execute([$id_dept_doyen]); $coursSansProf = $coursSansProf->fetchColumn() ?: 0;

// ============================================================
// DONNÉES POUR LES PAGES
// ============================================================
$professeurs = $bdd->prepare("SELECT p.id_prof, u.id_user, u.nom, u.prenom, u.email, u.telephone, u.photo, u.statut, p.specialite, p.diplome, (SELECT COUNT(*) FROM cours WHERE id_prof = p.id_prof) as nb_cours FROM professeurs p JOIN users u ON p.id_user = u.id_user WHERE u.statut = 'Actif' ORDER BY u.nom ASC");
$professeurs->execute();

$classes = $bdd->prepare("SELECT c.*, d.nom_dept FROM classes c JOIN departements d ON c.id_dept = d.id_dept WHERE c.id_dept = ? ORDER BY c.niveau ASC");
$classes->execute([$id_dept_doyen]);

$coursList = $bdd->prepare("SELECT c.*, cl.niveau, u.nom, u.prenom FROM cours c JOIN classes cl ON c.id_classe = cl.id_classe LEFT JOIN professeurs p ON c.id_prof = p.id_prof LEFT JOIN users u ON p.id_user = u.id_user WHERE cl.id_dept = ? ORDER BY c.nom_cours ASC");
$coursList->execute([$id_dept_doyen]);

$notesSoumises = $bdd->prepare("SELECT n.*, u.nom, u.prenom, e.matricule, co.nom_cours, cl.niveau FROM notes n JOIN etudiants e ON n.id_etudiant = e.id_etudiant JOIN users u ON e.id_user = u.id_user JOIN cours co ON n.id_cours = co.id_cours JOIN classes cl ON co.id_classe = cl.id_classe WHERE cl.id_dept = ? AND n.statut = 'soumis' ORDER BY n.date_soumission ASC");
$notesSoumises->execute([$id_dept_doyen]);

$examensSoumis = $bdd->prepare("SELECT e.*, co.nom_cours, cl.niveau, u.nom, u.prenom FROM examens e JOIN cours co ON e.id_cours = co.id_cours JOIN classes cl ON e.id_classe = cl.id_classe JOIN professeurs p ON e.id_prof = p.id_prof JOIN users u ON p.id_user = u.id_user WHERE cl.id_dept = ? AND e.statut = 'en_attente' ORDER BY e.date_envoi ASC");
$examensSoumis->execute([$id_dept_doyen]);

$resultatsAPublierList = $bdd->prepare("SELECT n.*, u.nom, u.prenom, e.matricule, co.nom_cours, cl.niveau, COALESCE((SELECT SUM(montant) FROM paiements WHERE id_etudiant = n.id_etudiant AND statut = 'valide'), 0) as total_paye FROM notes n JOIN etudiants e ON n.id_etudiant = e.id_etudiant JOIN users u ON e.id_user = u.id_user JOIN cours co ON n.id_cours = co.id_cours JOIN classes cl ON co.id_classe = cl.id_classe WHERE cl.id_dept = ? AND n.statut = 'valide' AND n.date_publication IS NULL ORDER BY u.nom ASC");
$resultatsAPublierList->execute([$id_dept_doyen]);

$resultatsPublies = $bdd->prepare("SELECT n.*, u.nom, u.prenom, e.matricule, co.nom_cours, cl.niveau FROM notes n JOIN etudiants e ON n.id_etudiant = e.id_etudiant JOIN users u ON e.id_user = u.id_user JOIN cours co ON n.id_cours = co.id_cours JOIN classes cl ON co.id_classe = cl.id_classe WHERE cl.id_dept = ? AND n.statut = 'valide' AND n.date_publication IS NOT NULL ORDER BY n.date_publication DESC");
$resultatsPublies->execute([$id_dept_doyen]);

$resultatsBloques = $bdd->prepare("SELECT n.*, u.nom, u.prenom, e.matricule, co.nom_cours, cl.niveau, COALESCE((SELECT SUM(montant) FROM paiements WHERE id_etudiant = n.id_etudiant AND statut = 'valide'), 0) as total_paye FROM notes n JOIN etudiants e ON n.id_etudiant = e.id_etudiant JOIN users u ON e.id_user = u.id_user JOIN cours co ON n.id_cours = co.id_cours JOIN classes cl ON co.id_classe = cl.id_classe WHERE cl.id_dept = ? AND n.statut IN ('rejete', 'valide') AND (n.date_publication IS NULL OR n.statut = 'rejete') ORDER BY u.nom ASC");
$resultatsBloques->execute([$id_dept_doyen]);

$etudiantsParClasse = $bdd->prepare("SELECT cl.id_classe, cl.niveau, COUNT(e.id_etudiant) as nb_etudiants FROM classes cl LEFT JOIN etudiants e ON cl.id_classe = e.id_classe WHERE cl.id_dept = ? GROUP BY cl.id_classe ORDER BY cl.niveau ASC");
$etudiantsParClasse->execute([$id_dept_doyen]);

$statsReussite = $bdd->prepare("SELECT COUNT(CASE WHEN n.note >= 10 THEN 1 END) as reussite, COUNT(CASE WHEN n.note < 10 THEN 1 END) as echec FROM notes n JOIN cours c ON n.id_cours = c.id_cours JOIN classes cl ON c.id_classe = cl.id_classe WHERE cl.id_dept = ? AND n.statut = 'valide'");
$statsReussite->execute([$id_dept_doyen]);
$stats = $statsReussite->fetch(PDO::FETCH_ASSOC);
$tauxReussite = ($stats['reussite'] + $stats['echec']) > 0 ? round(($stats['reussite'] / ($stats['reussite'] + $stats['echec'])) * 100, 1) : 0;

$distNotes = $bdd->prepare("SELECT COUNT(CASE WHEN note < 10 THEN 1 END) as 'moins_10', COUNT(CASE WHEN note >= 10 AND note < 12 THEN 1 END) as '10_12', COUNT(CASE WHEN note >= 12 AND note < 14 THEN 1 END) as '12_14', COUNT(CASE WHEN note >= 14 AND note < 16 THEN 1 END) as '14_16', COUNT(CASE WHEN note >= 16 AND note < 18 THEN 1 END) as '16_18', COUNT(CASE WHEN note >= 18 THEN 1 END) as '18_plus' FROM notes n JOIN cours c ON n.id_cours = c.id_cours JOIN classes cl ON c.id_classe = cl.id_classe WHERE cl.id_dept = ? AND n.statut = 'valide'");
$distNotes->execute([$id_dept_doyen]);
$dist = $distNotes->fetch(PDO::FETCH_ASSOC);

$listeDepartements = $bdd->query("SELECT id_dept, nom_dept, faculte FROM departements ORDER BY nom_dept")->fetchAll(PDO::FETCH_ASSOC);
$listeClasses = $bdd->query("SELECT c.id_classe, c.niveau, c.id_dept, d.nom_dept, d.faculte FROM classes c JOIN departements d ON c.id_dept = d.id_dept ORDER BY d.nom_dept, c.niveau")->fetchAll(PDO::FETCH_ASSOC);

$lesHoraires = $bdd->query("SELECT h.*, cl.niveau, d.nom_dept, d.faculte, c.nom_cours AS matiere, CONCAT(u.nom, ' ', u.prenom) AS enseignant FROM horaires h LEFT JOIN cours c ON h.id_cours = c.id_cours LEFT JOIN professeurs p ON h.id_prof = p.id_prof LEFT JOIN users u ON p.id_user = u.id_user LEFT JOIN classes cl ON h.id_classe = cl.id_classe LEFT JOIN departements d ON cl.id_dept = d.id_dept ORDER BY FIELD(h.jour, 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'), h.heure_debut ASC")->fetchAll(PDO::FETCH_ASSOC);

$historique = $bdd->prepare("SELECT a.*, u.nom, u.prenom FROM audit_financier a JOIN users u ON a.id_user = u.id_user ORDER BY a.date_action DESC LIMIT 50");
$historique->execute();

$notifications = $bdd->prepare("SELECT * FROM notifications WHERE id_dept = ? OR id_dept IS NULL ORDER BY date_creation DESC LIMIT 20");
$notifications->execute([$id_dept_doyen]);

$anneesAcademiques = $bdd->query("SELECT DISTINCT annee_academique_entree FROM etudiants WHERE annee_academique_entree IS NOT NULL ORDER BY annee_academique_entree DESC")->fetchAll(PDO::FETCH_COLUMN);
$classesForAnnee = $bdd->prepare("SELECT id_classe, niveau FROM classes WHERE id_dept = ? ORDER BY niveau");
$classesForAnnee->execute([$id_dept_doyen]);

$evenementsCalendrier = $bdd->prepare("SELECT 'cours' as type, CONCAT(jour, ' ', heure_debut, '-', heure_fin) as date_heure, c.nom_cours as titre, salle, cl.niveau as classe FROM horaires h JOIN cours c ON h.id_cours = c.id_cours JOIN classes cl ON h.id_classe = cl.id_classe WHERE cl.id_dept = ? AND h.jour IN ('Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi') UNION SELECT 'examen' as type, DATE_FORMAT(date_prevue, '%Y-%m-%d') as date_heure, CONCAT('Examen de ', co.nom_cours) as titre, '' as salle, cl.niveau as classe FROM examens e JOIN cours co ON e.id_cours = co.id_cours JOIN classes cl ON e.id_classe = cl.id_classe WHERE cl.id_dept = ? AND e.statut = 'valide' ORDER BY date_heure ASC LIMIT 20");
$evenementsCalendrier->execute([$id_dept_doyen, $id_dept_doyen]);

// GESTION VOIR/MODIFIER
$prof_voir = null;
if (isset($_GET['voir_prof'])) {
    $stmt = $bdd->prepare("SELECT p.*, u.nom, u.prenom, u.email, u.telephone, u.photo, u.statut, (SELECT COUNT(*) FROM cours WHERE id_prof = p.id_prof) as nb_cours, (SELECT COUNT(*) FROM notes n JOIN cours c ON n.id_cours = c.id_cours WHERE c.id_prof = p.id_prof) as nb_notes FROM professeurs p JOIN users u ON p.id_user = u.id_user WHERE p.id_prof = ?");
    $stmt->execute([intval($_GET['voir_prof'])]);
    $prof_voir = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($prof_voir) $page = 'voir_professeur';
}

$prof_modifier = null;
if (isset($_GET['modifier_prof'])) {
    $stmt = $bdd->prepare("SELECT p.*, u.nom, u.prenom, u.email, u.telephone, u.photo FROM professeurs p JOIN users u ON p.id_user = u.id_user WHERE p.id_prof = ?");
    $stmt->execute([intval($_GET['modifier_prof'])]);
    $prof_modifier = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($prof_modifier) $page = 'modifier_professeur';
}

$classe_voir = null;
if (isset($_GET['voir_classe'])) {
    $stmt = $bdd->prepare("SELECT c.*, d.nom_dept, d.faculte, (SELECT COUNT(*) FROM etudiants WHERE id_classe = c.id_classe) as nb_etudiants, (SELECT COUNT(*) FROM cours WHERE id_classe = c.id_classe) as nb_cours FROM classes c JOIN departements d ON c.id_dept = d.id_dept WHERE c.id_classe = ? AND c.id_dept = ?");
    $stmt->execute([intval($_GET['voir_classe']), $id_dept_doyen]);
    $classe_voir = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($classe_voir) $page = 'voir_classe';
}

$classe_modifier = null;
if (isset($_GET['modifier_classe'])) {
    $stmt = $bdd->prepare("SELECT * FROM classes WHERE id_classe = ? AND id_dept = ?");
    $stmt->execute([intval($_GET['modifier_classe']), $id_dept_doyen]);
    $classe_modifier = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($classe_modifier) $page = 'modifier_classe';
}

$cours_voir = null;
if (isset($_GET['voir_cours'])) {
    $stmt = $bdd->prepare("SELECT c.*, cl.niveau, d.nom_dept, d.faculte, u.nom, u.prenom FROM cours c JOIN classes cl ON c.id_classe = cl.id_classe JOIN departements d ON cl.id_dept = d.id_dept LEFT JOIN professeurs p ON c.id_prof = p.id_prof LEFT JOIN users u ON p.id_user = u.id_user WHERE c.id_cours = ? AND d.id_dept = ?");
    $stmt->execute([intval($_GET['voir_cours']), $id_dept_doyen]);
    $cours_voir = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($cours_voir) $page = 'voir_cours';
}

$cours_modifier = null;
if (isset($_GET['modifier_cours'])) {
    $stmt = $bdd->prepare("SELECT c.*, cl.id_classe as classe_id, cl.niveau FROM cours c JOIN classes cl ON c.id_classe = cl.id_classe WHERE c.id_cours = ? AND cl.id_dept = ?");
    $stmt->execute([intval($_GET['modifier_cours']), $id_dept_doyen]);
    $cours_modifier = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($cours_modifier) $page = 'modifier_cours';
}

// Salutation dynamique
$heure = (int)date('H');
if ($heure < 12) $salutation = 'Bonjour';
elseif ($heure < 18) $salutation = 'Bon après-midi';
else $salutation = 'Bonsoir';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Décanat — UniVerse</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="assets/theme.css">
    <script src="assets/theme.js"></script>
    <style>
        :root {
            --bg-primary: #f5f7fa;
            --bg-sidebar: #1e1b4b;
            --bg-card: #ffffff;
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --text-muted: #64748b;
            --text-white: #ffffff;
            --accent-blue: #3b82f6;
            --accent-indigo: #6366f1;
            --accent-purple: #8b5cf6;
            --accent-violet: #a78bfa;
            --accent-success: #10b981;
            --accent-warning: #f59e0b;
            --accent-danger: #ef4444;
            --accent-pink: #ec4899;
            --accent-cyan: #06b6d4;
            --border-color: #e5e7eb;
            --border-light: #f1f5f9;
            --shadow-sm: 0 1px 3px rgba(15,23,42,0.04);
            --shadow-md: 0 4px 16px rgba(15,23,42,0.06);
            --shadow-lg: 0 12px 32px rgba(15,23,42,0.08);
            --shadow-xl: 0 20px 40px rgba(15,23,42,0.12);
            --radius-sm: 8px;
            --radius: 14px;
            --radius-lg: 20px;
            --radius-xl: 24px;
            --transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', 'Segoe UI', system-ui, sans-serif; }
        body { background: var(--bg-primary); color: var(--text-primary); display: flex; min-height: 100vh; -webkit-font-smoothing: antialiased; }

        /* ===== SIDEBAR ===== */
        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, #312e81 0%, #1e1b4b 100%);
            color: var(--text-white);
            padding: 28px 18px;
            position: fixed;
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            z-index: 100;
            border-right: 1px solid rgba(255,255,255,0.06);
        }
        .sidebar::-webkit-scrollbar { width: 4px; }
        .sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.15); border-radius: 4px; }

        .sidebar .logo {
            font-size: 22px; font-weight: 800; margin-bottom: 26px;
            display: flex; align-items: center; gap: 10px;
            padding: 0 8px;
        }
        .sidebar .logo span {
            background: linear-gradient(135deg, #c4b5fd, #f0abfc);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }

        .user-profile {
            background: linear-gradient(135deg, rgba(139,92,246,0.2), rgba(167,139,250,0.1));
            padding: 20px 16px;
            border-radius: var(--radius);
            margin-bottom: 24px;
            text-align: center;
            border: 1px solid rgba(139,92,246,0.25);
            position: relative;
            overflow: hidden;
        }
        .user-profile::before {
            content: '';
            position: absolute; top: -40px; right: -40px;
            width: 100px; height: 100px;
            background: rgba(139,92,246,0.2);
            border-radius: 50%;
        }
        .profile-img {
            width: 68px; height: 68px; border-radius: 50%; object-fit: cover;
            margin-bottom: 10px; border: 3px solid rgba(167,139,250,0.6);
            position: relative; z-index: 1;
            box-shadow: 0 4px 16px rgba(139,92,246,0.4);
        }
        .user-profile .name { font-weight: 700; font-size: 15px; position: relative; z-index: 1; }
        .user-profile .title { font-size: 12px; color: #c4b5fd; margin-top: 2px; position: relative; z-index: 1; }
        .badge-role {
            display: inline-block; padding: 5px 14px; font-size: 10px; font-weight: 700;
            text-transform: uppercase; border-radius: 20px; margin-top: 8px;
            background: linear-gradient(135deg, #8b5cf6, #a78bfa);
            color: white; letter-spacing: 0.5px;
            position: relative; z-index: 1;
            box-shadow: 0 4px 12px rgba(139,92,246,0.4);
        }

        .nav-links { list-style: none; display: flex; flex-direction: column; gap: 2px; flex: 1; }
        .nav-links a {
            color: #c4b5fd; text-decoration: none; padding: 10px 14px; border-radius: 10px;
            display: flex; align-items: center; gap: 12px; font-weight: 600; font-size: 12px;
            transition: var(--transition); position: relative;
        }
        .nav-links a:hover { background: rgba(255,255,255,0.06); color: var(--text-white); transform: translateX(3px); }
        .nav-links a.active {
            background: linear-gradient(135deg, rgba(139,92,246,0.3), rgba(167,139,250,0.15));
            color: var(--text-white);
            box-shadow: inset 3px 0 0 #a78bfa;
        }
        .nav-links a i { width: 18px; text-align: center; font-size: 13px; }
        .nav-badge {
            margin-left: auto; background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white; font-size: 10px; padding: 2px 8px; border-radius: 10px; font-weight: 700;
            box-shadow: 0 2px 8px rgba(239,68,68,0.4);
        }
        .nav-links .logout { color: #fca5a5; margin-top: auto; padding-top: 16px; border-top: 1px solid rgba(255,255,255,0.08); }
        .nav-links .logout:hover { background: rgba(239,68,68,0.15); color: #fecaca; }

        /* ===== MAIN CONTENT ===== */
        .main-content {
            margin-left: 280px;
            width: calc(100% - 280px);
            padding: 28px 32px;
            min-height: 100vh;
        }

        /* ===== HERO BANNER ===== */
        .hero-banner {
            background: linear-gradient(135deg, #4c1d95 0%, #6d28d9 50%, #a78bfa 100%);
            color: white;
            padding: 32px 36px;
            border-radius: var(--radius-lg);
            margin-bottom: 28px;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-lg);
        }
        .hero-banner::before {
            content: '';
            position: absolute; top: -50%; right: -10%;
            width: 400px; height: 400px;
            background: radial-gradient(circle, rgba(255,255,255,0.12), transparent 70%);
            border-radius: 50%;
        }
        .hero-banner::after {
            content: '';
            position: absolute; bottom: -60%; left: 20%;
            width: 300px; height: 300px;
            background: radial-gradient(circle, rgba(236,72,153,0.25), transparent 70%);
            border-radius: 50%;
        }
        .hero-banner .hero-content {
            position: relative; z-index: 1;
            display: flex; justify-content: space-between; align-items: center;
            flex-wrap: wrap; gap: 20px;
        }
        .hero-banner h1 {
            font-size: 26px; font-weight: 800; letter-spacing: -0.3px;
            margin-bottom: 6px;
        }
        .hero-banner p {
            font-size: 14px; opacity: 0.9; font-weight: 500;
        }
        .hero-actions { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
        .hero-actions .theme-toggle-btn {
            background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.25);
            width: 42px; height: 42px; border-radius: 50%; cursor: pointer;
            font-size: 18px; color: white; transition: var(--transition);
            display: flex; align-items: center; justify-content: center;
        }
        .hero-actions .theme-toggle-btn:hover { background: rgba(255,255,255,0.25); transform: scale(1.05); }

        /* ===== BUTTONS ===== */
        .btn {
            padding: 10px 18px; border-radius: 10px; border: none;
            font-weight: 600; font-size: 13px; cursor: pointer;
            transition: var(--transition); display: inline-flex; align-items: center; gap: 8px;
            text-decoration: none; white-space: nowrap;
        }
        .btn-primary { background: linear-gradient(135deg, #8b5cf6, #7c3aed); color: white; box-shadow: 0 4px 12px rgba(139,92,246,0.3); }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(139,92,246,0.4); }
        .btn-success { background: linear-gradient(135deg, #10b981, #059669); color: white; box-shadow: 0 4px 12px rgba(16,185,129,0.25); }
        .btn-success:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(16,185,129,0.35); }
        .btn-warning { background: linear-gradient(135deg, #f59e0b, #d97706); color: white; box-shadow: 0 4px 12px rgba(245,158,11,0.25); }
        .btn-warning:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(245,158,11,0.35); }
        .btn-danger { background: linear-gradient(135deg, #ef4444, #dc2626); color: white; box-shadow: 0 4px 12px rgba(239,68,68,0.25); }
        .btn-danger:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(239,68,68,0.35); }
        .btn-outline { background: rgba(255,255,255,0.15); color: white; border: 1px solid rgba(255,255,255,0.3); }
        .btn-outline:hover { background: rgba(255,255,255,0.25); }
        .btn-outline-dark { background: transparent; color: var(--text-secondary); border: 1px solid var(--border-color); }
        .btn-outline-dark:hover { background: var(--bg-primary); }

        /* ===== ALERTS ===== */
        .alert {
            padding: 16px 22px; border-radius: var(--radius); margin-bottom: 22px;
            font-weight: 600; display: flex; align-items: center; gap: 12px;
            font-size: 14px; animation: slideDown 0.35s ease;
            box-shadow: var(--shadow-sm);
        }
        .alert.success { background: linear-gradient(135deg, #d1fae5, #ecfdf5); color: #065f46; border-left: 4px solid #10b981; }
        .alert.error { background: linear-gradient(135deg, #fee2e2, #fef2f2); color: #991b1b; border-left: 4px solid #ef4444; }
        .alert.warning { background: linear-gradient(135deg, #fef3c7, #fffbeb); color: #92400e; border-left: 4px solid #f59e0b; }
        .alert.info { background: linear-gradient(135deg, #dbeafe, #eff6ff); color: #1e40af; border-left: 4px solid #3b82f6; }
        @keyframes slideDown { from { opacity:0; transform:translateY(-10px); } to { opacity:1; transform:translateY(0); } }

        /* ===== STAT CARDS ===== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 18px;
            margin-bottom: 28px;
        }
        .stat-card {
            background: var(--bg-card);
            padding: 22px;
            border-radius: var(--radius);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            cursor: default;
        }
        .stat-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; }
        .stat-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-lg); border-color: transparent; }
        .stat-card .stat-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; }
        .stat-card .stat-icon-wrap {
            width: 44px; height: 44px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; color: white;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .stat-card .stat-value { font-size: 28px; font-weight: 800; color: var(--text-primary); letter-spacing: -0.5px; }
        .stat-card .stat-label { font-size: 12px; color: var(--text-muted); font-weight: 600; margin-top: 4px; text-transform: uppercase; letter-spacing: 0.4px; }
        .stat-card .stat-cta { font-size: 12px; color: var(--accent-purple); font-weight: 700; margin-top: 8px; }

        .stat-card.purple::before { background: linear-gradient(90deg, #8b5cf6, #a78bfa); }
        .stat-card.purple .stat-icon-wrap { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
        .stat-card.blue::before { background: linear-gradient(90deg, #3b82f6, #60a5fa); }
        .stat-card.blue .stat-icon-wrap { background: linear-gradient(135deg, #3b82f6, #2563eb); }
        .stat-card.green::before { background: linear-gradient(90deg, #10b981, #34d399); }
        .stat-card.green .stat-icon-wrap { background: linear-gradient(135deg, #10b981, #059669); }
        .stat-card.orange::before { background: linear-gradient(90deg, #f59e0b, #fbbf24); }
        .stat-card.orange .stat-icon-wrap { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .stat-card.red::before { background: linear-gradient(90deg, #ef4444, #f87171); }
        .stat-card.red .stat-icon-wrap { background: linear-gradient(135deg, #ef4444, #dc2626); }
        .stat-card.pink::before { background: linear-gradient(90deg, #ec4899, #f472b6); }
        .stat-card.pink .stat-icon-wrap { background: linear-gradient(135deg, #ec4899, #db2777); }
        .stat-card.indigo::before { background: linear-gradient(90deg, #6366f1, #818cf8); }
        .stat-card.indigo .stat-icon-wrap { background: linear-gradient(135deg, #6366f1, #4f46e5); }
        .stat-card.cyan::before { background: linear-gradient(90deg, #06b6d4, #22d3ee); }
        .stat-card.cyan .stat-icon-wrap { background: linear-gradient(135deg, #06b6d4, #0891b2); }

        .stat-card.alert-card { border: 2px solid var(--accent-warning); cursor: pointer; }
        .stat-card.alert-card:hover { border-color: var(--accent-warning); box-shadow: 0 12px 32px rgba(245,158,11,0.15); }

        /* ===== PANELS ===== */
        .panel {
            background: var(--bg-card);
            padding: 24px 28px;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            margin-bottom: 24px;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
        }
        .panel:hover { box-shadow: var(--shadow-md); }
        .panel-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 20px; flex-wrap: wrap; gap: 10px;
            padding-bottom: 16px; border-bottom: 2px solid var(--border-light);
        }
        .panel-header h2 {
            font-size: 18px; font-weight: 700; color: var(--text-primary);
            display: flex; align-items: center; gap: 10px;
        }
        .panel-header .panel-actions { display: flex; gap: 8px; flex-wrap: wrap; }

        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px; }
        .grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 24px; margin-bottom: 24px; }

        /* ===== TABLES ===== */
        .table-responsive { overflow-x: auto; border-radius: var(--radius); border: 1px solid var(--border-light); }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th {
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            padding: 14px 16px; color: var(--text-secondary);
            font-weight: 700; border-bottom: 2px solid var(--border-color);
            font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; text-align: left;
        }
        td { padding: 14px 16px; border-bottom: 1px solid var(--border-light); vertical-align: middle; }
        tr { transition: var(--transition); }
        tr:hover td { background: linear-gradient(90deg, #faf5ff, #f3e8ff); }

        /* ===== BADGES ===== */
        .status-badge {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 4px 12px; border-radius: 20px;
            font-size: 11px; font-weight: 700;
        }
        .status-badge.valide { background: linear-gradient(135deg, #d1fae5, #ecfdf5); color: #065f46; }
        .status-badge.soumis { background: linear-gradient(135deg, #fef3c7, #fffbeb); color: #92400e; }
        .status-badge.en_attente { background: linear-gradient(135deg, #fef3c7, #fffbeb); color: #92400e; }
        .status-badge.rejete { background: linear-gradient(135deg, #fee2e2, #fef2f2); color: #991b1b; }
        .status-badge.publie { background: linear-gradient(135deg, #d1fae5, #ecfdf5); color: #065f46; border: 1px solid #10b981; }

        .faculte-badge {
            display: inline-block; padding: 3px 12px; border-radius: 20px;
            background: linear-gradient(135deg, #f3e8ff, #e9d5ff); color: #6b21a8;
            font-weight: 600; font-size: 11px;
        }

        /* ===== ACTION BUTTONS ===== */
        .btn-action {
            padding: 6px 12px; border-radius: 8px; border: none;
            font-size: 12px; font-weight: 600; cursor: pointer;
            transition: var(--transition); display: inline-flex; align-items: center; gap: 4px;
            text-decoration: none;
        }
        .btn-action.success { background: #d1fae5; color: #065f46; }
        .btn-action.success:hover { background: linear-gradient(135deg, #10b981, #059669); color: white; transform: translateY(-1px); }
        .btn-action.danger { background: #fee2e2; color: #991b1b; }
        .btn-action.danger:hover { background: linear-gradient(135deg, #ef4444, #dc2626); color: white; transform: translateY(-1px); }
        .btn-action.primary { background: #f3e8ff; color: #6b21a8; }
        .btn-action.primary:hover { background: linear-gradient(135deg, #8b5cf6, #7c3aed); color: white; transform: translateY(-1px); }
        .btn-action.warning { background: #fef3c7; color: #92400e; }
        .btn-action.warning:hover { background: linear-gradient(135deg, #f59e0b, #d97706); color: white; transform: translateY(-1px); }

        /* ===== FILTER BAR ===== */
        .filter-bar {
            display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            padding: 18px 22px; border-radius: var(--radius); border: 1px solid var(--border-color);
        }
        .filter-bar .form-group { margin-bottom: 0; flex: 1; min-width: 140px; }
        .filter-bar .form-group label { font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); display: block; margin-bottom: 6px; }
        .filter-bar input, .filter-bar select {
            padding: 9px 14px; font-size: 13px; background: white;
            border: 1px solid var(--border-color); border-radius: 8px; width: 100%;
            outline: none; transition: var(--transition);
        }
        .filter-bar input:focus, .filter-bar select:focus { border-color: var(--accent-purple); box-shadow: 0 0 0 3px rgba(139,92,246,0.1); }
        .filter-actions { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }

        /* ===== CHARTS ===== */
        .chart-container { position: relative; height: 240px; }

        /* ===== FORMS ===== */
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-weight: 600; font-size: 13px; color: var(--text-secondary); margin-bottom: 6px; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%; padding: 11px 14px; border: 1.5px solid var(--border-color);
            border-radius: 10px; font-size: 14px; outline: none; transition: var(--transition);
            background: var(--bg-primary); font-family: inherit;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            border-color: var(--accent-purple); background: white; box-shadow: 0 0 0 3px rgba(139,92,246,0.1);
        }

        /* ===== IFRAME ===== */
        .iframe-panel { background: var(--bg-card); border-radius: var(--radius-lg); border: 1px solid var(--border-color); box-shadow: var(--shadow-sm); overflow: hidden; }
        .iframe-header { background: linear-gradient(135deg, #f8fafc, #f1f5f9); padding: 15px 22px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); }
        .iframe-header h2 { font-size: 16px; color: var(--accent-purple); font-weight: 700; }
        .iframe-content { width: 100%; height: calc(100vh - 220px); border: none; }

        /* ===== EMPTY STATE ===== */
        .empty-state { text-align: center; padding: 40px 20px; color: var(--text-muted); font-size: 14px; }
        .empty-state i { font-size: 42px; margin-bottom: 12px; display: block; opacity: 0.4; }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 1200px) { .grid-2 { grid-template-columns: 1fr; } .grid-3 { grid-template-columns: 1fr 1fr; } }
        @media (max-width: 992px) { .grid-3 { grid-template-columns: 1fr; } }
        @media (max-width: 768px) {
            body { flex-direction: column; }
            .sidebar { width: 100%; height: auto; position: relative; padding: 18px; }
            .main-content { margin-left: 0; width: 100%; padding: 16px; }
            .hero-banner { padding: 24px; }
            .hero-banner h1 { font-size: 20px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .filter-bar { flex-direction: column; align-items: stretch; }
            .filter-bar .form-group { min-width: unset; }
        }
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
            .panel { padding: 18px; }
        }
    </style>
</head>
<body>

    <!-- SIDEBAR -->
    <div class="sidebar">
        <div class="logo">🎓 <span>UniVerse</span></div>
        <div class="user-profile">
            <img src="uploads/<?= htmlspecialchars($user['photo'] ?? 'default.png'); ?>" alt="Photo" class="profile-img">
            <p class="name">Dr. <?= htmlspecialchars(($user['nom'] ?? '') . ' ' . ($user['prenom'] ?? '')); ?></p>
            <p class="title">Doyen de la Faculté</p>
            <span class="badge-role">👑 DOYEN</span>
        </div>
        <ul class="nav-links">
            <li><a href="?page=dashboard" class="<?= ($page=='dashboard' && $vue_crud=='')?'active':''; ?>"><i class="fas fa-chart-line"></i> Tableau de bord</a></li>
            <li><a href="?page=profil" class="<?= ($page=='profil')?'active':''; ?>"><i class="fas fa-user"></i> Mon profil</a></li>
            <li><a href="?page=professeurs" class="<?= ($page=='professeurs')?'active':''; ?>"><i class="fas fa-users"></i> Professeurs</a></li>
            <li><a href="?crud=prof" class="<?= ($vue_crud=='prof')?'active':''; ?>"><i class="fas fa-list"></i> Gestion profs</a></li>
            <li><a href="?crud=prof_add" class="<?= ($vue_crud=='prof_add')?'active':''; ?>"><i class="fas fa-plus"></i> Ajouter prof</a></li>
            <li><a href="?page=classes" class="<?= ($page=='classes')?'active':''; ?>"><i class="fas fa-school"></i> Classes</a></li>
            <li><a href="?crud=classe" class="<?= ($vue_crud=='classe')?'active':''; ?>"><i class="fas fa-list"></i> Gestion classes</a></li>
            <li><a href="?crud=classe_add" class="<?= ($vue_crud=='classe_add')?'active':''; ?>"><i class="fas fa-plus"></i> Ajouter classe</a></li>
            <li><a href="?page=etudiants" class="<?= ($page=='etudiants')?'active':''; ?>"><i class="fas fa-user-graduate"></i> Étudiants</a></li>
            <li><a href="?crud=etudiant" class="<?= ($vue_crud=='etudiant')?'active':''; ?>"><i class="fas fa-list"></i> Gestion étudiants</a></li>
            <li><a href="?page=cours" class="<?= ($page=='cours')?'active':''; ?>"><i class="fas fa-book"></i> Cours</a></li>
            <li><a href="?crud=cours" class="<?= ($vue_crud=='cours')?'active':''; ?>"><i class="fas fa-list"></i> Gestion cours</a></li>
            <li><a href="?crud=cours_add" class="<?= ($vue_crud=='cours_add')?'active':''; ?>"><i class="fas fa-plus"></i> Ajouter cours</a></li>
            <li><a href="?page=affectations" class="<?= ($page=='affectations')?'active':''; ?>"><i class="fas fa-link"></i> Affectations</a></li>
            <li><a href="?page=credits" class="<?= ($page=='credits')?'active':''; ?>"><i class="fas fa-coins"></i> Crédits</a></li>
            <li><a href="?page=examens" class="<?= ($page=='examens')?'active':''; ?>"><i class="fas fa-file-alt"></i> Examens <span class="nav-badge"><?= $examensAValider; ?></span></a></li>
            <li><a href="gestion_examens_doyen.php"><i class="fas fa-calendar-plus"></i> Programmer examens</a></li>
            <li><a href="?page=notes" class="<?= ($page=='notes')?'active':''; ?>"><i class="fas fa-pen"></i> Notes <span class="nav-badge"><?= $notesAValider; ?></span></a></li>
            <li><a href="?crud=note" class="<?= ($vue_crud=='note')?'active':''; ?>"><i class="fas fa-list"></i> Gestion notes</a></li>
            <li><a href="?crud=note_add" class="<?= ($vue_crud=='note_add')?'active':''; ?>"><i class="fas fa-plus"></i> Ajouter note</a></li>
            <li><a href="?page=resultats" class="<?= ($page=='resultats')?'active':''; ?>"><i class="fas fa-check-double"></i> Résultats à publier</a></li>
            <li><a href="?page=resultats_publies" class="<?= ($page=='resultats_publies')?'active':''; ?>"><i class="fas fa-flag"></i> Résultats publiés</a></li>
            <li><a href="?page=validation_annuelle" class="<?= ($page=='validation_annuelle')?'active':''; ?>"><i class="fas fa-calendar-check"></i> Validation annuelle</a></li>
            <li><a href="?page=calendrier" class="<?= ($page=='calendrier')?'active':''; ?>"><i class="fas fa-calendar-alt"></i> Calendrier</a></li>
            <li><a href="messagerie_doyen.php"><i class="fas fa-envelope"></i> Messagerie</a></li>
            <li><a href="?page=notifications" class="<?= ($page=='notifications')?'active':''; ?>"><i class="fas fa-bell"></i> Notifications <span class="nav-badge"><?= $notifsNonLues; ?></span></a></li>
            <li><a href="?page=historique" class="<?= ($page=='historique')?'active':''; ?>"><i class="fas fa-history"></i> Historique</a></li>
            <li><a href="?page=emploi_temps" class="<?= ($page=='emploi_temps')?'active':''; ?>"><i class="fas fa-calendar-alt"></i> Emploi du temps</a></li>
            <li><a href="?page=statistiques" class="<?= ($page=='statistiques')?'active':''; ?>"><i class="fas fa-chart-pie"></i> Statistiques</a></li>
            <li><a href="?page=rapports" class="<?= ($page=='rapports')?'active':''; ?>"><i class="fas fa-file-alt"></i> Rapports</a></li>
            <li><a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
        </ul>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content">

        <!-- HERO BANNER -->
        <div class="hero-banner">
            <div class="hero-content">
                <div>
                    <h1><?= $salutation ?>, Dr. <?= htmlspecialchars($user['prenom'] ?? '') ?> 👋</h1>
                    <p><?= date('l d F Y') ?> — Décanat · Gestion académique de votre département</p>
                </div>
                <div class="hero-actions">
                    <button class="theme-toggle-btn" onclick="toggleTheme()" title="Mode sombre">🌙</button>
                    <a href="?page=professeurs&action=ajouter" class="btn btn-primary"><i class="fas fa-plus"></i> Professeur</a>
                    <a href="?page=classes&action=ajouter" class="btn btn-success"><i class="fas fa-plus"></i> Classe</a>
                </div>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <?= $message ?>
        <?php endif; ?>

        <!-- IFRAME CRUD -->
        <?php if ($url_iframe !== ''): ?>
            <div class="iframe-panel">
                <div class="iframe-header">
                    <h2>📄 <?= htmlspecialchars($titre_iframe) ?></h2>
                    <a href="dashboard_doyen.php?page=dashboard" class="btn btn-outline-dark" style="font-size:12px; padding:6px 14px;">← Retour</a>
                </div>
                <iframe src="<?= htmlspecialchars($url_iframe) ?>" class="iframe-content"></iframe>
            </div>

        <?php elseif ($page == 'dashboard'): ?>
        <div class="stats-grid">
            <div class="stat-card purple">
                <div class="stat-top"><div class="stat-icon-wrap"><i class="fas fa-chalkboard-teacher"></i></div></div>
                <div class="stat-value"><?= $totalProfs; ?></div>
                <div class="stat-label">Professeurs</div>
            </div>
            <div class="stat-card blue">
                <div class="stat-top"><div class="stat-icon-wrap"><i class="fas fa-user-graduate"></i></div></div>
                <div class="stat-value"><?= $totalEtudiants; ?></div>
                <div class="stat-label">Étudiants</div>
            </div>
            <div class="stat-card green">
                <div class="stat-top"><div class="stat-icon-wrap"><i class="fas fa-school"></i></div></div>
                <div class="stat-value"><?= $totalClasses; ?></div>
                <div class="stat-label">Classes</div>
            </div>
            <div class="stat-card pink">
                <div class="stat-top"><div class="stat-icon-wrap"><i class="fas fa-book"></i></div></div>
                <div class="stat-value"><?= $totalCours; ?></div>
                <div class="stat-label">Cours</div>
            </div>
            <div class="stat-card orange alert-card" onclick="location.href='?page=notes'">
                <div class="stat-top"><div class="stat-icon-wrap"><i class="fas fa-pen"></i></div></div>
                <div class="stat-value"><?= $notesAValider; ?></div>
                <div class="stat-label">Notes à valider</div>
                <div class="stat-cta">📝 Valider →</div>
            </div>
            <div class="stat-card orange alert-card" onclick="location.href='?page=examens'">
                <div class="stat-top"><div class="stat-icon-wrap"><i class="fas fa-file-alt"></i></div></div>
                <div class="stat-value"><?= $examensAValider; ?></div>
                <div class="stat-label">Examens à valider</div>
                <div class="stat-cta">📄 Valider →</div>
            </div>
            <div class="stat-card orange alert-card" onclick="location.href='?page=resultats'">
                <div class="stat-top"><div class="stat-icon-wrap"><i class="fas fa-check-double"></i></div></div>
                <div class="stat-value"><?= $resultatsAPublier; ?></div>
                <div class="stat-label">Résultats à publier</div>
                <div class="stat-cta">📊 Publier →</div>
            </div>
            <div class="stat-card red">
                <div class="stat-top"><div class="stat-icon-wrap"><i class="fas fa-exclamation-triangle"></i></div></div>
                <div class="stat-value"><?= $etudiantsNonEnOrdre; ?></div>
                <div class="stat-label">Étudiants non en ordre</div>
            </div>
        </div>

        <div class="grid-2">
            <div class="panel">
                <div class="panel-header"><h2><i class="fas fa-chart-bar" style="color:var(--accent-blue);"></i> Distribution des notes</h2></div>
                <div class="chart-container"><canvas id="chartDistNotes"></canvas></div>
            </div>
            <div class="panel">
                <div class="panel-header"><h2><i class="fas fa-chart-pie" style="color:var(--accent-success);"></i> Taux de réussite</h2></div>
                <div class="chart-container"><canvas id="chartReussite"></canvas></div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-header"><h2><i class="fas fa-users" style="color:var(--accent-purple);"></i> Étudiants par classe</h2></div>
            <div class="table-responsive">
                <table>
                    <thead><tr><th>Classe</th><th>Nombre d'étudiants</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php while($ec = $etudiantsParClasse->fetch()): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($ec['niveau']); ?></strong></td>
                                <td><?= $ec['nb_etudiants']; ?> étudiant(s)</td>
                                <td><a href="?page=classes&id=<?= $ec['id_classe']; ?>" class="btn-action primary"><i class="fas fa-eye"></i></a></td>
                            </tr>
                        <?php endwhile; ?>
                        <?php if ($etudiantsParClasse->rowCount() == 0): ?>
                            <tr><td colspan="3" class="empty-state"><i class="fas fa-inbox"></i>Aucune classe</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="panel">
            <div class="panel-header"><h2><i class="fas fa-exclamation-triangle" style="color:var(--accent-warning);"></i> Alertes</h2></div>
            <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(250px,1fr)); gap:12px;">
                <?php if ($notesAValider > 0): ?><div class="alert warning" style="margin:0; cursor:pointer;" onclick="location.href='?page=notes'">⚠ <?= $notesAValider; ?> note(s) en attente</div><?php endif; ?>
                <?php if ($examensAValider > 0): ?><div class="alert warning" style="margin:0; cursor:pointer;" onclick="location.href='?page=examens'">⚠ <?= $examensAValider; ?> examen(s) en attente</div><?php endif; ?>
                <?php if ($resultatsAPublier > 0): ?><div class="alert info" style="margin:0; cursor:pointer;" onclick="location.href='?page=resultats'">📊 <?= $resultatsAPublier; ?> résultat(s) à publier</div><?php endif; ?>
                <?php if ($etudiantsNonEnOrdre > 0): ?><div class="alert error" style="margin:0;">⚠ <?= $etudiantsNonEnOrdre; ?> étudiant(s) non en ordre</div><?php endif; ?>
                <?php if ($notesAValider == 0 && $examensAValider == 0 && $etudiantsNonEnOrdre == 0): ?><div class="alert success" style="margin:0;">✅ Tout est à jour !</div><?php endif; ?>
            </div>
        </div>

        <?php elseif ($page == 'professeurs'): ?>
        <div class="panel">
            <div class="panel-header">
                <h2><i class="fas fa-users" style="color:var(--accent-purple);"></i> Professeurs du département</h2>
                <a href="?page=professeurs&action=ajouter" class="btn btn-primary"><i class="fas fa-plus"></i> Ajouter</a>
            </div>
            <?php if (isset($_GET['action']) && $_GET['action'] == 'ajouter'): ?>
            <form method="POST" style="margin-bottom:20px; padding:22px; background:linear-gradient(135deg, #faf5ff, #f3e8ff); border-radius:var(--radius); border:1px solid #e9d5ff;">
                <h3 style="margin-bottom:15px; color:var(--accent-purple);">➕ Ajouter un professeur</h3>
                <div class="grid-2">
                    <div class="form-group"><label>Nom</label><input type="text" name="nom" required></div>
                    <div class="form-group"><label>Prénom</label><input type="text" name="prenom" required></div>
                    <div class="form-group"><label>Email</label><input type="email" name="email" required></div>
                    <div class="form-group"><label>Téléphone</label><input type="text" name="telephone"></div>
                    <div class="form-group"><label>Spécialité</label><input type="text" name="specialite"></div>
                    <div class="form-group"><label>Diplôme</label><input type="text" name="diplome"></div>
                </div>
                <button type="submit" name="ajouter_professeur" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
                <a href="?page=professeurs" class="btn btn-outline-dark">Annuler</a>
            </form>
            <?php endif; ?>
            <div class="table-responsive">
                <table>
                    <thead><tr><th>Nom</th><th>Spécialité</th><th>Email</th><th>Téléphone</th><th>Statut</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php while($p = $professeurs->fetch()): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($p['nom'] . ' ' . $p['prenom']); ?></strong></td>
                                <td><?= htmlspecialchars($p['specialite'] ?? '—'); ?></td>
                                <td><?= htmlspecialchars($p['email']); ?></td>
                                <td><?= htmlspecialchars($p['telephone'] ?? '—'); ?></td>
                                <td><span class="status-badge <?= $p['statut'] == 'Actif' ? 'valide' : 'rejete'; ?>"><?= $p['statut']; ?></span></td>
                                <td>
                                    <a href="?voir_prof=<?= $p['id_prof']; ?>" class="btn-action primary"><i class="fas fa-eye"></i></a>
                                    <a href="?modifier_prof=<?= $p['id_prof']; ?>" class="btn-action warning"><i class="fas fa-edit"></i></a>
                                    <?php if ($p['statut'] == 'Actif'): ?>
                                        <a href="?desactiver_professeur=<?= $p['id_prof']; ?>" class="btn-action danger" onclick="return confirm('Désactiver ?')"><i class="fas fa-user-slash"></i></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php elseif ($page == 'classes'): ?>
        <div class="panel">
            <div class="panel-header">
                <h2><i class="fas fa-school" style="color:var(--accent-purple);"></i> Classes</h2>
                <a href="?page=classes&action=ajouter" class="btn btn-primary"><i class="fas fa-plus"></i> Ajouter</a>
            </div>
            <?php if (isset($_GET['action']) && $_GET['action'] == 'ajouter'): ?>
            <form method="POST" style="margin-bottom:20px; padding:22px; background:linear-gradient(135deg, #faf5ff, #f3e8ff); border-radius:var(--radius); border:1px solid #e9d5ff;">
                <h3 style="margin-bottom:15px; color:var(--accent-purple);">➕ Ajouter une classe</h3>
                <div class="form-group"><label>Niveau</label>
                    <select name="niveau" required>
                        <option value="BAC I">BAC I</option><option value="BAC II">BAC II</option><option value="BAC III">BAC III</option><option value="BAC IV">BAC IV</option><option value="BAC V">BAC V</option>
                    </select>
                </div>
                <button type="submit" name="ajouter_classe" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
                <a href="?page=classes" class="btn btn-outline-dark">Annuler</a>
            </form>
            <?php endif; ?>
            <div class="table-responsive">
                <table>
                    <thead><tr><th>Classe</th><th>Département</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php while($cl = $classes->fetch()): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($cl['niveau']); ?></strong></td>
                                <td><?= htmlspecialchars($cl['nom_dept']); ?></td>
                                <td>
                                    <a href="?voir_classe=<?= $cl['id_classe']; ?>" class="btn-action primary"><i class="fas fa-eye"></i></a>
                                    <a href="?modifier_classe=<?= $cl['id_classe']; ?>" class="btn-action warning"><i class="fas fa-edit"></i></a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php elseif ($page == 'cours'): ?>
        <div class="panel">
            <div class="panel-header">
                <h2><i class="fas fa-book" style="color:var(--accent-purple);"></i> Cours</h2>
                <a href="?page=cours&action=ajouter" class="btn btn-primary"><i class="fas fa-plus"></i> Ajouter</a>
            </div>
            <?php if (isset($_GET['action']) && $_GET['action'] == 'ajouter'): ?>
            <form method="POST" style="margin-bottom:20px; padding:22px; background:linear-gradient(135deg, #faf5ff, #f3e8ff); border-radius:var(--radius); border:1px solid #e9d5ff;">
                <h3 style="margin-bottom:15px; color:var(--accent-purple);">➕ Ajouter un cours</h3>
                <div class="grid-2">
                    <div class="form-group"><label>Nom</label><input type="text" name="nom_cours" required></div>
                    <div class="form-group"><label>Crédits</label><input type="number" name="credit" min="1" max="30" value="3" required></div>
                    <div class="form-group"><label>Semestre</label><select name="semestre"><option value="Semestre 1">Semestre 1</option><option value="Semestre 2">Semestre 2</option></select></div>
                    <div class="form-group"><label>Classe</label><select name="id_classe" required>
                        <?php $classesForCours = $bdd->prepare("SELECT id_classe, niveau FROM classes WHERE id_dept = ? ORDER BY niveau"); $classesForCours->execute([$id_dept_doyen]); while($cc = $classesForCours->fetch()): ?>
                            <option value="<?= $cc['id_classe']; ?>"><?= htmlspecialchars($cc['niveau']); ?></option>
                        <?php endwhile; ?>
                    </select></div>
                </div>
                <button type="submit" name="ajouter_cours" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
                <a href="?page=cours" class="btn btn-outline-dark">Annuler</a>
            </form>
            <?php endif; ?>
            <div class="table-responsive">
                <table>
                    <thead><tr><th>Nom</th><th>Classe</th><th>Professeur</th><th>Crédits</th><th>Semestre</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php while($c = $coursList->fetch()): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($c['nom_cours']); ?></strong></td>
                                <td><?= htmlspecialchars($c['niveau']); ?></td>
                                <td><?= htmlspecialchars(($c['nom'] ?? '') . ' ' . ($c['prenom'] ?? '')) ?: 'Non assigné'; ?></td>
                                <td><?= $c['credit']; ?></td>
                                <td><?= htmlspecialchars($c['semestre']); ?></td>
                                <td>
                                    <a href="?voir_cours=<?= $c['id_cours']; ?>" class="btn-action primary"><i class="fas fa-eye"></i></a>
                                    <a href="?modifier_cours=<?= $c['id_cours']; ?>" class="btn-action warning"><i class="fas fa-edit"></i></a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php elseif ($page == 'notes'): ?>
        <div class="panel">
            <div class="panel-header">
                <h2><i class="fas fa-pen" style="color:var(--accent-warning);"></i> Notes à valider</h2>
                <span style="color:var(--accent-warning); font-weight:700;"><?= $notesAValider; ?> en attente</span>
            </div>
            <div class="table-responsive">
                <table>
                    <thead><tr><th>Étudiant</th><th>Matricule</th><th>Cours</th><th>Classe</th><th>Note</th><th>Session</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php while($n = $notesSoumises->fetch()): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($n['nom'] . ' ' . $n['prenom']); ?></strong></td>
                                <td><?= htmlspecialchars($n['matricule']); ?></td>
                                <td><?= htmlspecialchars($n['nom_cours']); ?></td>
                                <td><?= htmlspecialchars($n['niveau']); ?></td>
                                <td><strong style="color:<?= $n['note']>=10 ? 'var(--accent-success)' : 'var(--accent-danger)' ?>;"><?= number_format($n['note'], 2); ?>/20</strong></td>
                                <td><?= htmlspecialchars($n['session']); ?></td>
                                <td><a href="?valider_note=<?= $n['id_note']; ?>" class="btn-action success" onclick="return confirm('Valider ?')"><i class="fas fa-check"></i></a></td>
                            </tr>
                        <?php endwhile; ?>
                        <?php if ($notesAValider == 0): ?><tr><td colspan="7" class="empty-state"><i class="fas fa-check-circle"></i>Aucune note en attente.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php elseif ($page == 'examens'): ?>
        <div class="panel">
            <div class="panel-header"><h2><i class="fas fa-file-alt" style="color:var(--accent-warning);"></i> Examens à valider</h2></div>
            <div class="table-responsive">
                <table>
                    <thead><tr><th>Professeur</th><th>Cours</th><th>Classe</th><th>Type</th><th>Date</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php while($e = $examensSoumis->fetch()): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($e['nom'] . ' ' . $e['prenom']); ?></strong></td>
                                <td><?= htmlspecialchars($e['nom_cours']); ?></td>
                                <td><?= htmlspecialchars($e['niveau']); ?></td>
                                <td><?= htmlspecialchars($e['type_examen']); ?></td>
                                <td><?= date('d/m/Y', strtotime($e['date_prevue'])); ?></td>
                                <td><a href="?valider_examen=<?= $e['id_examen']; ?>" class="btn-action success" onclick="return confirm('Valider ?')"><i class="fas fa-check"></i></a></td>
                            </tr>
                        <?php endwhile; ?>
                        <?php if ($examensAValider == 0): ?><tr><td colspan="6" class="empty-state"><i class="fas fa-check-circle"></i>Aucun examen en attente.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php elseif ($page == 'notifications'): ?>
        <div class="panel">
            <div class="panel-header">
                <h2><i class="fas fa-bell" style="color:var(--accent-warning);"></i> Notifications de mon département</h2>
                <span style="font-size:13px; color:var(--text-muted);"><?= $notifsNonLues; ?> non lue(s)</span>
            </div>
            <?php if ($notifications->rowCount() > 0): ?>
                <?php while($n = $notifications->fetch()): ?>
                    <div style="display:flex; justify-content:space-between; align-items:center; padding:14px 0; border-bottom:1px solid var(--border-light);">
                        <div>
                            <div style="font-weight:600; color:var(--text-primary);"><?= htmlspecialchars($n['titre']); ?></div>
                            <div style="font-size:13px; color:var(--text-secondary); margin-top:3px;"><?= htmlspecialchars($n['message']); ?></div>
                            <div style="font-size:11px; color:var(--text-muted); margin-top:4px;"><?= date('d/m/Y H:i', strtotime($n['date_creation'])); ?></div>
                        </div>
                        <span class="status-badge <?= $n['statut'] == 'non_lu' ? 'soumis' : 'valide'; ?>"><?= $n['statut'] == 'non_lu' ? '⏳ Non lu' : '✅ Lu'; ?></span>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state"><i class="fas fa-bell-slash"></i>Aucune notification pour votre département.</div>
            <?php endif; ?>
        </div>

        <?php elseif ($page == 'resultats'): ?>
        <div class="panel">
            <div class="panel-header"><h2><i class="fas fa-check-double" style="color:var(--accent-purple);"></i> Résultats à publier</h2></div>
            <div class="table-responsive">
                <table>
                    <thead><tr><th>Étudiant</th><th>Cours</th><th>Note</th><th>Minerval</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php while($r = $resultatsAPublierList->fetch()): $estEnOrdre = ($r['total_paye'] >= MINERVAL_ANNUEL); ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($r['nom'] . ' ' . $r['prenom']); ?></strong></td>
                                <td><?= htmlspecialchars($r['nom_cours']); ?></td>
                                <td><strong><?= number_format($r['note'], 2); ?>/20</strong></td>
                                <td><?= number_format($r['total_paye'], 0, ',', ' '); ?> BIF <?= $estEnOrdre ? '✅' : '⚠'; ?></td>
                                <td>
                                    <?php if ($estEnOrdre): ?>
                                        <form method="POST" style="display:inline;"><input type="hidden" name="id_note" value="<?= $r['id_note']; ?>"><button type="submit" name="publier_note" class="btn-action success" onclick="return confirm('Publier ?')"><i class="fas fa-check"></i> Publier</button></form>
                                    <?php else: ?>
                                        <span style="color:#94a3b8;">🔒 Minerval insuffisant</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php elseif ($page == 'resultats_publies'): ?>
        <div class="panel">
            <div class="panel-header"><h2><i class="fas fa-flag" style="color:var(--accent-success);"></i> Résultats publiés</h2></div>
            <div class="table-responsive">
                <table>
                    <thead><tr><th>Étudiant</th><th>Cours</th><th>Note</th><th>Classe</th><th>Date de publication</th></tr></thead>
                    <tbody>
                        <?php while($r = $resultatsPublies->fetch()): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($r['nom'] . ' ' . $r['prenom']); ?></strong></td>
                                <td><?= htmlspecialchars($r['nom_cours']); ?></td>
                                <td><strong><?= number_format($r['note'], 2); ?>/20</strong></td>
                                <td><?= htmlspecialchars($r['niveau']); ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($r['date_publication'])); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php elseif ($page == 'affectations'): ?>
        <div class="panel">
            <div class="panel-header"><h2><i class="fas fa-link" style="color:var(--accent-purple);"></i> Affectations pédagogiques</h2></div>
            <div class="table-responsive">
                <table>
                    <thead><tr><th>Cours</th><th>Classe</th><th>Professeur actuel</th><th>Nouveau professeur</th></tr></thead>
                    <tbody>
                        <?php $coursAff = $bdd->prepare("SELECT c.id_cours, c.nom_cours, cl.niveau, u.nom, u.prenom, p.id_prof FROM cours c JOIN classes cl ON c.id_classe = cl.id_classe LEFT JOIN professeurs p ON c.id_prof = p.id_prof LEFT JOIN users u ON p.id_user = u.id_user WHERE cl.id_dept = ? ORDER BY c.nom_cours"); $coursAff->execute([$id_dept_doyen]); while($ca = $coursAff->fetch()): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($ca['nom_cours']); ?></strong></td>
                                <td><?= htmlspecialchars($ca['niveau']); ?></td>
                                <td><?= $ca['id_prof'] ? htmlspecialchars($ca['nom'] . ' ' . $ca['prenom']) : '<span style="color:var(--accent-danger);">Non assigné</span>'; ?></td>
                                <td>
                                    <form method="POST" style="display:flex; gap:8px;">
                                        <input type="hidden" name="id_cours" value="<?= $ca['id_cours']; ?>">
                                        <select name="id_prof" style="padding:8px; border-radius:8px; border:1px solid var(--border-color);">
                                            <option value="0">-- Désaffecter --</option>
                                            <?php $profList = $bdd->prepare("SELECT p.id_prof, u.nom, u.prenom FROM professeurs p JOIN users u ON p.id_user = u.id_user WHERE u.statut = 'Actif'"); $profList->execute(); while($pl = $profList->fetch()): ?>
                                                <option value="<?= $pl['id_prof']; ?>" <?= ($pl['id_prof'] == $ca['id_prof']) ? 'selected' : ''; ?>><?= htmlspecialchars($pl['nom'] . ' ' . $pl['prenom']); ?></option>
                                            <?php endwhile; ?>
                                        </select>
                                        <button type="submit" name="ajouter_affectation" class="btn-action success"><i class="fas fa-save"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php elseif ($page == 'credits'): ?>
        <div class="panel">
            <div class="panel-header"><h2><i class="fas fa-coins" style="color:var(--accent-warning);"></i> Gestion des crédits</h2></div>
            <div class="table-responsive">
                <table>
                    <thead><tr><th>Cours</th><th>Classe</th><th>Crédits actuels</th><th>Nouveaux crédits</th></tr></thead>
                    <tbody>
                        <?php $coursCredits = $bdd->prepare("SELECT c.id_cours, c.nom_cours, c.credit, cl.niveau FROM cours c JOIN classes cl ON c.id_classe = cl.id_classe WHERE cl.id_dept = ? ORDER BY c.nom_cours"); $coursCredits->execute([$id_dept_doyen]); while($cc = $coursCredits->fetch()): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($cc['nom_cours']); ?></strong></td>
                                <td><?= htmlspecialchars($cc['niveau']); ?></td>
                                <td><?= $cc['credit']; ?></td>
                                <td>
                                    <form method="POST" style="display:flex; gap:8px;">
                                        <input type="hidden" name="id_cours" value="<?= $cc['id_cours']; ?>">
                                        <input type="number" name="credit" value="<?= $cc['credit']; ?>" min="1" max="30" style="width:90px; padding:8px; border-radius:8px; border:1px solid var(--border-color);">
                                        <button type="submit" name="modifier_credits" class="btn-action success"><i class="fas fa-save"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php elseif ($page == 'statistiques'): ?>
        <div class="panel">
            <div class="panel-header"><h2><i class="fas fa-chart-pie" style="color:var(--accent-purple);"></i> Statistiques du département</h2></div>
            <div class="grid-3">
                <div style="background:linear-gradient(135deg,#eff6ff,#dbeafe); padding:20px; border-radius:var(--radius); text-align:center;"><div style="font-size:32px; font-weight:800; color:var(--accent-blue);"><?= $totalEtudiants; ?></div><div style="font-weight:600; color:var(--text-secondary);">Étudiants</div></div>
                <div style="background:linear-gradient(135deg,#f0fdf4,#dcfce7); padding:20px; border-radius:var(--radius); text-align:center;"><div style="font-size:32px; font-weight:800; color:var(--accent-success);"><?= $totalProfs; ?></div><div style="font-weight:600; color:var(--text-secondary);">Professeurs</div></div>
                <div style="background:linear-gradient(135deg,#fef3c7,#fde68a); padding:20px; border-radius:var(--radius); text-align:center;"><div style="font-size:32px; font-weight:800; color:var(--accent-warning);"><?= $totalCours; ?></div><div style="font-weight:600; color:var(--text-secondary);">Cours</div></div>
                <div style="background:linear-gradient(135deg,#f3e8ff,#e9d5ff); padding:20px; border-radius:var(--radius); text-align:center;"><div style="font-size:32px; font-weight:800; color:var(--accent-purple);"><?= $tauxReussite; ?>%</div><div style="font-weight:600; color:var(--text-secondary);">Taux réussite</div></div>
                <div style="background:linear-gradient(135deg,#fee2e2,#fecaca); padding:20px; border-radius:var(--radius); text-align:center;"><div style="font-size:32px; font-weight:800; color:var(--accent-danger);"><?= $etudiantsNonEnOrdre; ?></div><div style="font-weight:600; color:var(--text-secondary);">Non en ordre</div></div>
                <div style="background:linear-gradient(135deg,#fef3c7,#fde68a); padding:20px; border-radius:var(--radius); text-align:center;"><div style="font-size:32px; font-weight:800; color:var(--accent-warning);"><?= $notesAValider; ?></div><div style="font-weight:600; color:var(--text-secondary);">Notes à valider</div></div>
            </div>
        </div>

        <?php elseif ($page == 'historique'): ?>
        <div class="panel">
            <div class="panel-header"><h2><i class="fas fa-history" style="color:var(--accent-purple);"></i> Historique</h2></div>
            <div class="table-responsive">
                <table>
                    <thead><tr><th>Utilisateur</th><th>Action</th><th>Table</th><th>Date</th></tr></thead>
                    <tbody>
                        <?php while($h = $historique->fetch()): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($h['nom'] . ' ' . $h['prenom']); ?></strong></td>
                                <td><?= htmlspecialchars($h['action']); ?></td>
                                <td><?= htmlspecialchars($h['table_concernee']); ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($h['date_action'])); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php elseif ($page == 'emploi_temps'): ?>
        <div class="panel">
            <div class="panel-header"><h2><i class="fas fa-calendar-alt" style="color:var(--accent-purple);"></i> Emploi du temps</h2></div>
            <div class="table-responsive">
                <table>
                    <thead><tr><th>Jour</th><th>Horaire</th><th>Cours</th><th>Classe</th><th>Enseignant</th><th>Salle</th></tr></thead>
                    <tbody>
                        <?php foreach($lesHoraires as $h): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($h['jour']); ?></strong></td>
                                <td><?= substr($h['heure_debut'],0,5).' - '.substr($h['heure_fin'],0,5); ?></td>
                                <td><?= htmlspecialchars($h['matiere'] ?? 'N/A'); ?></td>
                                <td><?= htmlspecialchars($h['niveau']); ?></td>
                                <td><?= htmlspecialchars($h['enseignant'] ?? 'Non assigné'); ?></td>
                                <td><?= htmlspecialchars($h['salle'] ?: '-'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($lesHoraires)): ?>
                            <tr><td colspan="6" class="empty-state"><i class="fas fa-calendar-times"></i>Aucun horaire programmé.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php elseif ($page == 'calendrier'): ?>
        <div class="panel">
            <div class="panel-header"><h2><i class="fas fa-calendar-alt" style="color:var(--accent-purple);"></i> Calendrier</h2></div>
            <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(300px,1fr)); gap:15px;">
                <?php while($ev = $evenementsCalendrier->fetch()): ?>
                    <div style="background:white; padding:18px; border-radius:var(--radius); border-left:4px solid <?= $ev['type'] == 'cours' ? 'var(--accent-blue)' : 'var(--accent-warning)'; ?>; box-shadow:var(--shadow-md);">
                        <div style="display:flex; align-items:center; gap:10px; margin-bottom:8px;"><strong><?= htmlspecialchars($ev['titre']); ?></strong></div>
                        <div style="font-size:13px; color:var(--text-secondary);">📅 <?= htmlspecialchars($ev['date_heure']); ?></div>
                        <div style="font-size:12px; color:var(--text-muted); margin-top:4px;">Classe : <?= htmlspecialchars($ev['classe']); ?></div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>

        <?php elseif ($page == 'rapports'): ?>
        <div class="panel">
            <div class="panel-header"><h2><i class="fas fa-file-alt" style="color:var(--accent-purple);"></i> Rapports</h2></div>
            <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:15px;">
                <a href="?page=rapports&export=professeurs" class="btn btn-outline-dark" style="justify-content:center; padding:16px;"><i class="fas fa-users"></i> Professeurs (CSV)</a>
                <a href="?page=rapports&export=classes" class="btn btn-outline-dark" style="justify-content:center; padding:16px;"><i class="fas fa-school"></i> Classes (CSV)</a>
                <a href="?page=rapports&export=etudiants" class="btn btn-outline-dark" style="justify-content:center; padding:16px;"><i class="fas fa-user-graduate"></i> Étudiants (CSV)</a>
                <a href="?page=rapports&export=cours" class="btn btn-outline-dark" style="justify-content:center; padding:16px;"><i class="fas fa-book"></i> Cours (CSV)</a>
                <a href="?page=rapports&export=notes" class="btn btn-outline-dark" style="justify-content:center; padding:16px;"><i class="fas fa-pen"></i> Notes (CSV)</a>
            </div>
        </div>

        <?php elseif ($page == 'profil'): ?>
        <div class="panel">
            <div class="panel-header"><h2><i class="fas fa-user" style="color:var(--accent-purple);"></i> Mon profil</h2></div>
            <div style="display:flex; gap:30px; flex-wrap:wrap; align-items:center;">
                <div style="flex:0 0 180px; text-align:center;">
                    <img src="uploads/<?= htmlspecialchars($user['photo'] ?? 'default.png'); ?>" style="width:150px; height:150px; border-radius:50%; object-fit:cover; border:4px solid var(--accent-purple); box-shadow:0 8px 24px rgba(139,92,246,0.25);">
                </div>
                <div style="flex:1; min-width:280px;">
                    <div class="grid-2">
                        <div><strong>Nom :</strong> <?= htmlspecialchars($user['nom'] ?? ''); ?></div>
                        <div><strong>Prénom :</strong> <?= htmlspecialchars($user['prenom'] ?? ''); ?></div>
                        <div><strong>Rôle :</strong> Doyen</div>
                        <div><strong>Département :</strong> <?= htmlspecialchars($id_dept_doyen); ?></div>
                    </div>
                    <a href="mon_profil.php" class="btn btn-primary" style="margin-top:20px;"><i class="fas fa-edit"></i> Modifier mon profil</a>
                </div>
            </div>
        </div>

        <?php elseif ($page == 'validation_annuelle'): ?>
        <div class="panel">
            <div class="panel-header"><h2><i class="fas fa-calendar-check" style="color:var(--accent-purple);"></i> Validation annuelle</h2></div>
            <div class="empty-state"><i class="fas fa-hard-hat"></i>Module en cours de développement</div>
        </div>

        <?php elseif ($page == 'etudiants'): ?>
        <div class="panel">
            <div class="panel-header"><h2><i class="fas fa-user-graduate" style="color:var(--accent-blue);"></i> Étudiants du département</h2></div>
            <div class="table-responsive">
                <table>
                    <thead><tr><th>Matricule</th><th>Nom</th><th>Prénom</th><th>Classe</th><th>Département</th><th>Email</th><th>Téléphone</th></tr></thead>
                    <tbody>
                        <?php $stmt = $bdd->prepare("SELECT e.matricule, u.nom, u.prenom, u.email, u.telephone, c.niveau, d.nom_dept, d.faculte FROM etudiants e JOIN users u ON e.id_user = u.id_user JOIN classes c ON e.id_classe = c.id_classe JOIN departements d ON c.id_dept = d.id_dept WHERE d.id_dept = ? ORDER BY u.nom ASC"); $stmt->execute([$id_dept_doyen]); while($etu = $stmt->fetch()): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($etu['matricule']); ?></strong></td>
                                <td><?= htmlspecialchars($etu['nom']); ?></td>
                                <td><?= htmlspecialchars($etu['prenom']); ?></td>
                                <td><?= htmlspecialchars($etu['niveau']); ?></td>
                                <td><?= htmlspecialchars($etu['nom_dept']); ?></td>
                                <td><?= htmlspecialchars($etu['email']); ?></td>
                                <td><?= htmlspecialchars($etu['telephone']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php elseif ($page == 'voir_professeur' && $prof_voir): ?>
        <div class="panel">
            <div class="panel-header">
                <h2><i class="fas fa-user" style="color:var(--accent-purple);"></i> Profil du professeur</h2>
                <a href="?page=professeurs" class="btn btn-outline-dark"><i class="fas fa-arrow-left"></i> Retour</a>
            </div>
            <div class="grid-2">
                <div><strong>Nom complet :</strong> <?= htmlspecialchars($prof_voir['nom'] . ' ' . $prof_voir['prenom']); ?></div>
                <div><strong>Email :</strong> <?= htmlspecialchars($prof_voir['email']); ?></div>
                <div><strong>Téléphone :</strong> <?= htmlspecialchars($prof_voir['telephone'] ?? 'N/A'); ?></div>
                <div><strong>Spécialité :</strong> <?= htmlspecialchars($prof_voir['specialite'] ?? 'N/A'); ?></div>
                <div><strong>Diplôme :</strong> <?= htmlspecialchars($prof_voir['diplome'] ?? 'N/A'); ?></div>
                <div><strong>Nombre de cours :</strong> <?= $prof_voir['nb_cours']; ?></div>
                <div><strong>Nombre de notes :</strong> <?= $prof_voir['nb_notes']; ?></div>
                <div><strong>Statut :</strong> <?= htmlspecialchars($prof_voir['statut']); ?></div>
            </div>
        </div>

        <?php elseif ($page == 'modifier_professeur' && $prof_modifier): ?>
        <div class="panel">
            <div class="panel-header">
                <h2><i class="fas fa-edit" style="color:var(--accent-warning);"></i> Modifier le professeur</h2>
                <a href="?page=professeurs" class="btn btn-outline-dark"><i class="fas fa-arrow-left"></i> Retour</a>
            </div>
            <form method="POST">
                <input type="hidden" name="id_prof" value="<?= $prof_modifier['id_prof']; ?>">
                <div class="grid-2">
                    <div class="form-group"><label>Nom</label><input type="text" name="nom" value="<?= htmlspecialchars($prof_modifier['nom']); ?>" required></div>
                    <div class="form-group"><label>Prénom</label><input type="text" name="prenom" value="<?= htmlspecialchars($prof_modifier['prenom']); ?>" required></div>
                    <div class="form-group"><label>Email</label><input type="email" name="email" value="<?= htmlspecialchars($prof_modifier['email']); ?>" required></div>
                    <div class="form-group"><label>Téléphone</label><input type="text" name="telephone" value="<?= htmlspecialchars($prof_modifier['telephone'] ?? ''); ?>"></div>
                    <div class="form-group"><label>Spécialité</label><input type="text" name="specialite" value="<?= htmlspecialchars($prof_modifier['specialite'] ?? ''); ?>"></div>
                    <div class="form-group"><label>Diplôme</label><input type="text" name="diplome" value="<?= htmlspecialchars($prof_modifier['diplome'] ?? ''); ?>"></div>
                </div>
                <button type="submit" name="modifier_professeur" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
                <a href="?page=professeurs" class="btn btn-outline-dark">Annuler</a>
            </form>
        </div>

        <?php elseif ($page == 'voir_classe' && $classe_voir): ?>
        <div class="panel">
            <div class="panel-header"><h2><i class="fas fa-school" style="color:var(--accent-purple);"></i> Détail de la classe</h2><a href="?page=classes" class="btn btn-outline-dark"><i class="fas fa-arrow-left"></i> Retour</a></div>
            <div class="grid-2">
                <div><strong>Niveau :</strong> <?= htmlspecialchars($classe_voir['niveau']); ?></div>
                <div><strong>Département :</strong> <?= htmlspecialchars($classe_voir['nom_dept']); ?></div>
                <div><strong>Faculté :</strong> <?= htmlspecialchars($classe_voir['faculte'] ?? 'N/A'); ?></div>
                <div><strong>Nombre d'étudiants :</strong> <?= $classe_voir['nb_etudiants']; ?></div>
                <div><strong>Nombre de cours :</strong> <?= $classe_voir['nb_cours']; ?></div>
            </div>
        </div>

        <?php elseif ($page == 'modifier_classe' && $classe_modifier): ?>
        <div class="panel">
            <div class="panel-header"><h2><i class="fas fa-edit" style="color:var(--accent-warning);"></i> Modifier la classe</h2><a href="?page=classes" class="btn btn-outline-dark"><i class="fas fa-arrow-left"></i> Retour</a></div>
            <form method="POST">
                <input type="hidden" name="id_classe" value="<?= $classe_modifier['id_classe']; ?>">
                <div class="form-group"><label>Niveau</label><select name="niveau" required>
                    <option value="BAC I" <?= ($classe_modifier['niveau'] == 'BAC I') ? 'selected' : ''; ?>>BAC I</option>
                    <option value="BAC II" <?= ($classe_modifier['niveau'] == 'BAC II') ? 'selected' : ''; ?>>BAC II</option>
                    <option value="BAC III" <?= ($classe_modifier['niveau'] == 'BAC III') ? 'selected' : ''; ?>>BAC III</option>
                    <option value="BAC IV" <?= ($classe_modifier['niveau'] == 'BAC IV') ? 'selected' : ''; ?>>BAC IV</option>
                    <option value="BAC V" <?= ($classe_modifier['niveau'] == 'BAC V') ? 'selected' : ''; ?>>BAC V</option>
                </select></div>
                <button type="submit" name="modifier_classe" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
                <a href="?page=classes" class="btn btn-outline-dark">Annuler</a>
            </form>
        </div>

        <?php elseif ($page == 'voir_cours' && $cours_voir): ?>
        <div class="panel">
            <div class="panel-header"><h2><i class="fas fa-book" style="color:var(--accent-purple);"></i> Détail du cours</h2><a href="?page=cours" class="btn btn-outline-dark"><i class="fas fa-arrow-left"></i> Retour</a></div>
            <div class="grid-2">
                <div><strong>Nom :</strong> <?= htmlspecialchars($cours_voir['nom_cours']); ?></div>
                <div><strong>Classe :</strong> <?= htmlspecialchars($cours_voir['niveau']); ?></div>
                <div><strong>Département :</strong> <?= htmlspecialchars($cours_voir['nom_dept']); ?></div>
                <div><strong>Faculté :</strong> <?= htmlspecialchars($cours_voir['faculte'] ?? 'N/A'); ?></div>
                <div><strong>Crédits :</strong> <?= $cours_voir['credit']; ?></div>
                <div><strong>Semestre :</strong> <?= htmlspecialchars($cours_voir['semestre']); ?></div>
                <div><strong>Professeur :</strong> <?= htmlspecialchars(($cours_voir['nom'] ?? '') . ' ' . ($cours_voir['prenom'] ?? '')) ?: 'Non assigné'; ?></div>
            </div>
        </div>

        <?php elseif ($page == 'modifier_cours' && $cours_modifier): ?>
        <div class="panel">
            <div class="panel-header"><h2><i class="fas fa-edit" style="color:var(--accent-warning);"></i> Modifier le cours</h2><a href="?page=cours" class="btn btn-outline-dark"><i class="fas fa-arrow-left"></i> Retour</a></div>
            <form method="POST">
                <input type="hidden" name="id_cours" value="<?= $cours_modifier['id_cours']; ?>">
                <div class="grid-2">
                    <div class="form-group"><label>Nom</label><input type="text" name="nom_cours" value="<?= htmlspecialchars($cours_modifier['nom_cours']); ?>" required></div>
                    <div class="form-group"><label>Crédits</label><input type="number" name="credit" value="<?= $cours_modifier['credit']; ?>" min="1" max="30" required></div>
                    <div class="form-group"><label>Semestre</label><select name="semestre"><option value="Semestre 1" <?= ($cours_modifier['semestre'] == 'Semestre 1') ? 'selected' : ''; ?>>Semestre 1</option><option value="Semestre 2" <?= ($cours_modifier['semestre'] == 'Semestre 2') ? 'selected' : ''; ?>>Semestre 2</option></select></div>
                    <div class="form-group"><label>Classe</label><select name="id_classe" required>
                        <?php $classesForCours = $bdd->prepare("SELECT id_classe, niveau FROM classes WHERE id_dept = ? ORDER BY niveau"); $classesForCours->execute([$id_dept_doyen]); while($cc = $classesForCours->fetch()): ?>
                            <option value="<?= $cc['id_classe']; ?>" <?= ($cc['id_classe'] == $cours_modifier['id_classe']) ? 'selected' : ''; ?>><?= htmlspecialchars($cc['niveau']); ?></option>
                        <?php endwhile; ?>
                    </select></div>
                </div>
                <button type="submit" name="modifier_cours" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
                <a href="?page=cours" class="btn btn-outline-dark">Annuler</a>
            </form>
        </div>

        <?php endif; ?>

        <div style="text-align:center; padding:20px 0 10px; color:var(--text-muted); font-size:12px; border-top:1px solid var(--border-color); margin-top:20px;">
            <p>© <?= date('Y'); ?> UniVerse — Décanat</p>
        </div>

    </div>

<script>
<?php if ($page == 'dashboard' && $url_iframe === ''): ?>
const ctxDist = document.getElementById('chartDistNotes').getContext('2d');
new Chart(ctxDist, {
    type:'bar',
    data:{
        labels:['<10','10-12','12-14','14-16','16-18','18+'],
        datasets:[{
            label:'Étudiants',
            data:[<?= $dist['moins_10'] ?? 0; ?>, <?= $dist['10_12'] ?? 0; ?>, <?= $dist['12_14'] ?? 0; ?>, <?= $dist['14_16'] ?? 0; ?>, <?= $dist['16_18'] ?? 0; ?>, <?= $dist['18_plus'] ?? 0; ?>],
            backgroundColor:['rgba(239,68,68,0.75)','rgba(245,158,11,0.75)','rgba(250,204,21,0.75)','rgba(16,185,129,0.75)','rgba(59,130,246,0.75)','rgba(139,92,246,0.75)'],
            borderColor:['#ef4444','#f59e0b','#facc15','#10b981','#3b82f6','#8b5cf6'],
            borderWidth:2, borderRadius:8
        }]
    },
    options:{ responsive:true, maintainAspectRatio:false, plugins:{ legend:{ display:false } }, scales:{ y:{ beginAtZero:true, grid:{ color:'#f1f5f9' } }, x:{ grid:{ display:false } } } }
});

const ctxReussite = document.getElementById('chartReussite').getContext('2d');
new Chart(ctxReussite, {
    type:'doughnut',
    data:{
        labels:['Réussite','Échec'],
        datasets:[{
            data:[<?= $stats['reussite'] ?? 0; ?>, <?= $stats['echec'] ?? 0; ?>],
            backgroundColor:['rgba(16,185,129,0.85)','rgba(239,68,68,0.85)'],
            borderColor:['#10b981','#ef4444'],
            borderWidth:3
        }]
    },
    options:{ responsive:true, maintainAspectRatio:false, plugins:{ legend:{ position:'bottom', labels:{ padding:20, font:{ size:12, weight:'600' } } } }, cutout:'65%' }
});
<?php endif; ?>
</script>

</body>
</html>