<?php
require_once __DIR__ . '/db.php';
// ============================================================
// DASHBOARD PROFESSEUR - UniVerse (Refonte Design)
// ============================================================
require_once 'auth.php';
requireRole('Professeur');

try {
    $bdd = getDBConnection();
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

$id_user = $USER_ID;

$reqProf = $bdd->prepare("
    SELECT p.id_prof, p.specialite, p.diplome, u.nom, u.prenom, u.photo, u.email, u.telephone
    FROM professeurs p
    INNER JOIN users u ON p.id_user = u.id_user
    WHERE p.id_user = ?
");
$reqProf->execute([$id_user]);
$prof = $reqProf->fetch(PDO::FETCH_ASSOC);

if (!$prof) {
    die("Profil professeur introuvable. Contactez l'administration.");
}

$id_prof = $prof['id_prof'];
$photo_profil = (!empty($prof['photo']) && file_exists("uploads/" . $prof['photo'])) ? $prof['photo'] : 'default.png';

$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
$message = "";
$message_type = "";

function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

$csrf_token = generateCSRFToken();

// ============================================================
// TRAITEMENT DES ACTIONS (inchangé)
// ============================================================
if (isset($_POST['envoyer_examen'])) {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        $message = "<div class='alert error'>❌ Token CSRF invalide.</div>";
        $message_type = "error";
    } else {
        $id_cours = intval($_POST['id_cours']);
        $id_classe = intval($_POST['id_classe']);
        $semestre = trim($_POST['semestre']);
        $annee_academique = trim($_POST['annee_academique']);
        
        $checkCours = $bdd->prepare("SELECT id_cours, id_classe FROM cours WHERE id_cours = ? AND id_prof = ?");
        $checkCours->execute([$id_cours, $id_prof]);
        if (!$checkCours->fetch()) {
            $message = "<div class='alert error'>❌ Ce cours ne vous appartient pas.</div>";
            $message_type = "error";
        } else {
            if (isset($_FILES['fichier_examen']) && $_FILES['fichier_examen']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['fichier_examen'];
                $allowed_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
                $allowed_ext = ['pdf', 'doc', 'docx'];
                $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime_type = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);

                if (!in_array($file_ext, $allowed_ext) || !in_array($mime_type, $allowed_types)) {
                    $message = "<div class='alert error'>❌ Format non autorisé. Utilisez PDF, DOC ou DOCX.</div>";
                    $message_type = "error";
                } elseif ($file['size'] > 10 * 1024 * 1024) {
                    $message = "<div class='alert error'>❌ Le fichier ne doit pas dépasser 10 Mo.</div>";
                    $message_type = "error";
                } else {
                    $checkDoublon = $bdd->prepare("SELECT COUNT(*) FROM examens WHERE id_cours = ? AND id_prof = ? AND statut IN ('soumis','en_attente')");
                    $checkDoublon->execute([$id_cours, $id_prof]);
                    if ($checkDoublon->fetchColumn() > 0) {
                        $message = "<div class='alert warning'>⚠ Un examen est déjà en attente pour ce cours.</div>";
                        $message_type = "warning";
                    } else {
                        $reqDoyen = $bdd->prepare("
                            SELECT u.id_user FROM doyens d
                            JOIN departements dept ON d.id_dept = dept.id_dept
                            JOIN classes cl ON cl.id_dept = dept.id_dept
                            JOIN cours c ON c.id_classe = cl.id_classe
                            JOIN users u ON d.id_user = u.id_user
                            WHERE c.id_cours = ? LIMIT 1
                        ");
                        $reqDoyen->execute([$id_cours]);
                        $id_doyen = $reqDoyen->fetchColumn();

                        $nom_fichier = 'examen_' . date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $file_ext;
                        $chemin = 'uploads/examens/' . $nom_fichier;
                        if (!is_dir('uploads/examens')) mkdir('uploads/examens', 0777, true);

                        if (move_uploaded_file($file['tmp_name'], $chemin)) {
                            try {
                                $bdd->beginTransaction();
                                $stmt = $bdd->prepare("
                                    INSERT INTO examens 
                                    (id_cours, id_classe, id_prof, id_doyen, titre, type_examen, date_prevue, duree, points_total, instructions, sujet_fichier, statut, date_envoi)
                                    VALUES (?, ?, ?, ?, ?, ?, NOW(), 0, 20, ?, ?, 'soumis', NOW())
                                ");
                                $stmt->execute([
                                    $id_cours, $id_classe, $id_prof, $id_doyen,
                                    trim($_POST['titre']), trim($_POST['type_examen']),
                                    trim($_POST['instructions']), $nom_fichier
                                ]);
                                $id_examen = $bdd->lastInsertId();

                                $hist = $bdd->prepare("INSERT INTO audit_financier (id_user, action, table_concernee, id_concerned, nouvelle_valeur, adresse_ip, user_agent) VALUES (?, 'creation_examen', 'examens', ?, ?, ?, ?)");
                                $hist->execute([$id_user, $id_examen, 'Fichier: ' . $nom_fichier, $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null]);

                                $notif = $bdd->prepare("INSERT INTO notifications (titre, message, type_evenement, statut, date_creation, source_table) VALUES (?, ?, 'INFO', 'non_lu', NOW(), 'examen')");
                                $notif->execute([
                                    '📄 Nouvel examen soumis',
                                    'Le professeur ' . $prof['nom'] . ' ' . $prof['prenom'] . ' a soumis un examen pour le cours ' . trim($_POST['titre'])
                                ]);

                                $bdd->commit();
                                $message = "<div class='alert success'>✅ Examen envoyé au Doyen !</div>";
                                $message_type = "success";
                            } catch (Exception $e) {
                                $bdd->rollBack();
                                $message = "<div class='alert error'>❌ Erreur : " . $e->getMessage() . "</div>";
                                $message_type = "error";
                                if (file_exists($chemin)) unlink($chemin);
                            }
                        } else {
                            $message = "<div class='alert error'>❌ Erreur lors de l'upload.</div>";
                            $message_type = "error";
                        }
                    }
                }
            } else {
                $message = "<div class='alert error'>❌ Veuillez sélectionner un fichier.</div>";
                $message_type = "error";
            }
        }
    }
}

if (isset($_GET['supprimer_examen']) && !empty($_GET['supprimer_examen'])) {
    if (!verifyCSRFToken($_GET['csrf_token'] ?? '')) {
        $message = "<div class='alert error'>❌ Token CSRF invalide.</div>";
        $message_type = "error";
    } else {
        $id_examen = intval($_GET['supprimer_examen']);
        $check = $bdd->prepare("SELECT sujet_fichier, statut FROM examens WHERE id_examen = ? AND id_prof = ?");
        $check->execute([$id_examen, $id_prof]);
        $examen = $check->fetch(PDO::FETCH_ASSOC);
        if ($examen && in_array($examen['statut'], ['brouillon', 'rejete'])) {
            if ($examen['sujet_fichier'] && file_exists('uploads/examens/' . $examen['sujet_fichier'])) {
                unlink('uploads/examens/' . $examen['sujet_fichier']);
            }
            $del = $bdd->prepare("DELETE FROM examens WHERE id_examen = ?");
            $del->execute([$id_examen]);
            $message = "<div class='alert success'>🗑️ Examen supprimé.</div>";
            $message_type = "success";
        } else {
            $message = "<div class='alert error'>❌ Impossible de supprimer cet examen.</div>";
            $message_type = "error";
        }
    }
}

if (isset($_POST['renvoyer_examen'])) {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        $message = "<div class='alert error'>❌ Token CSRF invalide.</div>";
        $message_type = "error";
    } else {
        $id_examen = intval($_POST['id_examen']);
        $check = $bdd->prepare("SELECT id_cours, id_classe, statut FROM examens WHERE id_examen = ? AND id_prof = ? AND statut = 'rejete'");
        $check->execute([$id_examen, $id_prof]);
        if ($check->fetch()) {
            $upd = $bdd->prepare("UPDATE examens SET statut = 'soumis', date_envoi = NOW(), motif_rejet = NULL WHERE id_examen = ?");
            $upd->execute([$id_examen]);
            $message = "<div class='alert success'>✅ Examen renvoyé au Doyen.</div>";
            $message_type = "success";
        } else {
            $message = "<div class='alert error'>❌ Examen introuvable ou non rejeté.</div>";
            $message_type = "error";
        }
    }
}

if (isset($_POST['sauvegarder_notes_brouillon'])) {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        $message = "<div class='alert error'>❌ Token CSRF invalide.</div>";
        $message_type = "error";
    } else {
        $id_cours = intval($_POST['id_cours']);
        $session = trim($_POST['session']);
        $notes_saisies = $_POST['notes'] ?? [];
        
        $checkCours = $bdd->prepare("SELECT id_cours FROM cours WHERE id_cours = ? AND id_prof = ?");
        $checkCours->execute([$id_cours, $id_prof]);
        if ($checkCours->fetch()) {
            foreach ($notes_saisies as $id_etudiant => $note) {
                $note = floatval(str_replace(',', '.', $note));
                if ($note < 0 || $note > 20) continue;
                
                $checkNote = $bdd->prepare("SELECT id_note FROM notes WHERE id_etudiant = ? AND id_cours = ? AND session = ?");
                $checkNote->execute([$id_etudiant, $id_cours, $session]);
                $existing = $checkNote->fetch();
                
                if ($existing) {
                    $upd = $bdd->prepare("UPDATE notes SET note = ?, statut = 'brouillon', date_saisie = CURDATE() WHERE id_note = ?");
                    $upd->execute([$note, $existing['id_note']]);
                } else {
                    $ins = $bdd->prepare("INSERT INTO notes (id_etudiant, id_cours, note, session, statut, date_saisie) VALUES (?, ?, ?, ?, 'brouillon', CURDATE())");
                    $ins->execute([$id_etudiant, $id_cours, $note, $session]);
                }
            }
            $message = "<div class='alert success'>✅ Notes sauvegardées en brouillon.</div>";
            $message_type = "success";
        }
    }
}

if (isset($_POST['soumettre_notes'])) {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        $message = "<div class='alert error'>❌ Token CSRF invalide.</div>";
        $message_type = "error";
    } else {
        $id_cours = intval($_POST['id_cours']);
        $session = trim($_POST['session']);
        
        $checkCours = $bdd->prepare("SELECT id_cours FROM cours WHERE id_cours = ? AND id_prof = ?");
        $checkCours->execute([$id_cours, $id_prof]);
        if ($checkCours->fetch()) {
            $notes = $bdd->prepare("SELECT id_note FROM notes WHERE id_cours = ? AND session = ? AND statut = 'brouillon'");
            $notes->execute([$id_cours, $session]);
            while ($n = $notes->fetch()) {
                $upd = $bdd->prepare("UPDATE notes SET statut = 'soumis', date_soumission = NOW() WHERE id_note = ?");
                $upd->execute([$n['id_note']]);
            }
            
            $notif = $bdd->prepare("INSERT INTO notifications (titre, message, type_evenement, statut, date_creation, source_table) VALUES (?, ?, 'INFO', 'non_lu', NOW(), 'notes')");
            $notif->execute([
                '📝 Nouvelles notes soumises',
                'Le professeur ' . $prof['nom'] . ' ' . $prof['prenom'] . ' a soumis des notes pour validation.'
            ]);
            
            $message = "<div class='alert success'>✅ Notes soumises au Doyen.</div>";
            $message_type = "success";
        }
    }
}

try {
    $bdd->exec("
        CREATE TABLE IF NOT EXISTS messages (
            id_message INT(11) PRIMARY KEY AUTO_INCREMENT,
            id_expediteur INT(11) NOT NULL,
            id_destinataire INT(11) NOT NULL,
            sujet VARCHAR(255) NOT NULL,
            contenu TEXT NOT NULL,
            date_envoi DATETIME DEFAULT CURRENT_TIMESTAMP,
            lu TINYINT(1) DEFAULT 0,
            INDEX(id_expediteur),
            INDEX(id_destinataire),
            FOREIGN KEY (id_expediteur) REFERENCES users(id_user) ON DELETE CASCADE,
            FOREIGN KEY (id_destinataire) REFERENCES users(id_user) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (Exception $e) { }

if (isset($_POST['envoyer_message'])) {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        $message = "<div class='alert error'>❌ Token CSRF invalide.</div>";
        $message_type = "error";
    } else {
        $destinataire = intval($_POST['id_destinataire']);
        $sujet = trim($_POST['sujet']);
        $contenu = trim($_POST['contenu']);
        if ($destinataire > 0 && !empty($sujet) && !empty($contenu)) {
            try {
                $stmt = $bdd->prepare("INSERT INTO messages (id_expediteur, id_destinataire, sujet, contenu, date_envoi, lu) VALUES (?, ?, ?, ?, NOW(), 0)");
                $stmt->execute([$id_user, $destinataire, $sujet, $contenu]);
                $message = "<div class='alert success'>✅ Message envoyé.</div>";
                $message_type = "success";
                $notif = $bdd->prepare("INSERT INTO notifications (titre, message, type_evenement, statut, date_creation, source_table) VALUES (?, ?, 'INFO', 'non_lu', NOW(), 'message')");
                $notif->execute([
                    '📩 Nouveau message',
                    'Le professeur ' . $prof['nom'] . ' ' . $prof['prenom'] . ' vous a envoyé un message : ' . $sujet
                ]);
            } catch (Exception $e) {
                $message = "<div class='alert error'>❌ Erreur : " . $e->getMessage() . "</div>";
                $message_type = "error";
            }
        } else {
            $message = "<div class='alert error'>❌ Remplissez tous les champs.</div>";
            $message_type = "error";
        }
    }
}

if (isset($_GET['marquer_lu']) && !empty($_GET['marquer_lu'])) {
    $id_msg = intval($_GET['marquer_lu']);
    try {
        $upd = $bdd->prepare("UPDATE messages SET lu = 1 WHERE id_message = ? AND id_destinataire = ?");
        $upd->execute([$id_msg, $id_user]);
        $message = "<div class='alert success'>✅ Message marqué comme lu.</div>";
        $message_type = "success";
    } catch (Exception $e) {
        $message = "<div class='alert error'>❌ Erreur.</div>";
        $message_type = "error";
    }
}

// ============================================================
// STATISTIQUES ET DONNÉES (inchangé)
// ============================================================
$stmt = $bdd->prepare("SELECT COUNT(*) FROM cours WHERE id_prof = ?");
$stmt->execute([$id_prof]); $nbCours = $stmt->fetchColumn();

$stmt = $bdd->prepare("SELECT COUNT(DISTINCT e.id_etudiant) FROM etudiants e JOIN classes c ON e.id_classe = c.id_classe JOIN cours co ON co.id_classe = c.id_classe WHERE co.id_prof = ?");
$stmt->execute([$id_prof]); $nbEtudiants = $stmt->fetchColumn();

$stmt = $bdd->prepare("SELECT COUNT(DISTINCT c.id_classe) FROM classes c JOIN cours co ON co.id_classe = c.id_classe WHERE co.id_prof = ?");
$stmt->execute([$id_prof]); $nbClasses = $stmt->fetchColumn();

$stmt = $bdd->prepare("SELECT COUNT(*) FROM examens WHERE id_prof = ? AND statut = 'soumis'");
$stmt->execute([$id_prof]); $nbExamensEnvoyes = $stmt->fetchColumn();

$stmt = $bdd->prepare("SELECT COUNT(*) FROM examens WHERE id_prof = ? AND statut = 'en_attente'");
$stmt->execute([$id_prof]); $nbExamensAttente = $stmt->fetchColumn();

$stmt = $bdd->prepare("SELECT COUNT(*) FROM examens WHERE id_prof = ? AND statut = 'valide'");
$stmt->execute([$id_prof]); $nbExamensValides = $stmt->fetchColumn();

$stmt = $bdd->prepare("SELECT COUNT(*) FROM examens WHERE id_prof = ? AND statut = 'rejete'");
$stmt->execute([$id_prof]); $nbExamensRejetes = $stmt->fetchColumn();

$stmt = $bdd->prepare("SELECT COUNT(*) FROM notes n JOIN cours c ON n.id_cours = c.id_cours WHERE c.id_prof = ? AND n.statut = 'brouillon'");
$stmt->execute([$id_prof]); $nbNotesBrouillon = $stmt->fetchColumn();

$stmt = $bdd->prepare("SELECT COUNT(*) FROM notes n JOIN cours c ON n.id_cours = c.id_cours WHERE c.id_prof = ? AND n.statut = 'soumis'");
$stmt->execute([$id_prof]); $nbNotesSoumises = $stmt->fetchColumn();

$stmt = $bdd->prepare("SELECT COUNT(*) FROM notifications WHERE statut = 'non_lu' AND source_table IN ('professeur','examen','notes','message')");
$stmt->execute(); $nbNotifs = $stmt->fetchColumn();

$stmt = $bdd->prepare("SELECT COUNT(CASE WHEN note >= 10 THEN 1 END) as reussite, COUNT(CASE WHEN note < 10 THEN 1 END) as echec FROM notes n JOIN cours c ON n.id_cours = c.id_cours WHERE c.id_prof = ? AND n.statut = 'valide'");
$stmt->execute([$id_prof]); $stats = $stmt->fetch(PDO::FETCH_ASSOC);
$notesReussite = ($stats['reussite'] + $stats['echec']) > 0 ? round(($stats['reussite'] / ($stats['reussite'] + $stats['echec'])) * 100, 1) : 0;
$notesEchec = 100 - $notesReussite;

$stmt = $bdd->prepare("SELECT COUNT(CASE WHEN note < 10 THEN 1 END) as moins_10, COUNT(CASE WHEN note >= 10 AND note < 12 THEN 1 END) as '10_12', COUNT(CASE WHEN note >= 12 AND note < 14 THEN 1 END) as '12_14', COUNT(CASE WHEN note >= 14 AND note < 16 THEN 1 END) as '14_16', COUNT(CASE WHEN note >= 16 AND note < 18 THEN 1 END) as '16_18', COUNT(CASE WHEN note >= 18 THEN 1 END) as '18_plus' FROM notes n JOIN cours c ON n.id_cours = c.id_cours WHERE c.id_prof = ? AND n.statut = 'valide'");
$stmt->execute([$id_prof]); $dist = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $bdd->prepare("SELECT n.note, n.session, n.date_saisie, u.nom, u.prenom, co.nom_cours FROM notes n JOIN etudiants e ON n.id_etudiant = e.id_etudiant JOIN users u ON e.id_user = u.id_user JOIN cours co ON n.id_cours = co.id_cours WHERE co.id_prof = ? AND n.statut IN ('brouillon','valide') ORDER BY n.date_saisie DESC LIMIT 5");
$stmt->execute([$id_prof]); $dernieresNotes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $bdd->prepare("SELECT c.*, cl.niveau, d.nom_dept, (SELECT COUNT(*) FROM etudiants e WHERE e.id_classe = c.id_classe) as nb_etudiants FROM cours c JOIN classes cl ON c.id_classe = cl.id_classe JOIN departements d ON cl.id_dept = d.id_dept WHERE c.id_prof = ? ORDER BY c.nom_cours");
$stmt->execute([$id_prof]); $mesCours = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $bdd->prepare("SELECT h.*, c.nom_cours, cl.niveau, d.nom_dept FROM horaires h JOIN cours c ON h.id_cours = c.id_cours JOIN classes cl ON h.id_classe = cl.id_classe JOIN departements d ON cl.id_dept = d.id_dept WHERE h.id_prof = ? OR c.id_prof = ? ORDER BY FIELD(h.jour, 'Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'), h.heure_debut");
$stmt->execute([$id_prof, $id_prof]); $horaires = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $bdd->prepare("SELECT c.id_cours, c.nom_cours, cl.id_classe, cl.niveau FROM cours c JOIN classes cl ON c.id_classe = cl.id_classe WHERE c.id_prof = ? ORDER BY c.nom_cours");
$stmt->execute([$id_prof]); $coursList = $stmt->fetchAll(PDO::FETCH_ASSOC);

$doyens = $bdd->prepare("SELECT u.id_user, u.nom, u.prenom FROM doyens d JOIN users u ON d.id_user = u.id_user");
$doyens->execute(); $liste_doyens = $doyens->fetchAll(PDO::FETCH_ASSOC);

$filter_msg = isset($_GET['filter']) ? $_GET['filter'] : 'recus';
$search_msg = isset($_GET['search_msg']) ? trim($_GET['search_msg']) : '';

$sql_messages = "
    SELECT m.*, u1.nom as expediteur_nom, u1.prenom as expediteur_prenom,
           u2.nom as destinataire_nom, u2.prenom as destinataire_prenom
    FROM messages m
    JOIN users u1 ON m.id_expediteur = u1.id_user
    JOIN users u2 ON m.id_destinataire = u2.id_user
    WHERE (m.id_expediteur = ? OR m.id_destinataire = ?)
";
$params_messages = [$id_user, $id_user];
if ($filter_msg == 'recus') { $sql_messages .= " AND m.id_destinataire = ?"; $params_messages[] = $id_user; }
elseif ($filter_msg == 'envoyes') { $sql_messages .= " AND m.id_expediteur = ?"; $params_messages[] = $id_user; }
if (!empty($search_msg)) { $sql_messages .= " AND (m.sujet LIKE ? OR m.contenu LIKE ?)"; $sp = "%$search_msg%"; $params_messages[] = $sp; $params_messages[] = $sp; }
$sql_messages .= " ORDER BY m.date_envoi DESC LIMIT 50";
$stmt_messages = $bdd->prepare($sql_messages);
$stmt_messages->execute($params_messages);
$messages_list = $stmt_messages->fetchAll(PDO::FETCH_ASSOC);

$examens_page = isset($_GET['examens_page']) ? intval($_GET['examens_page']) : 1;
$examens_limit = 10;
$examens_offset = ($examens_page - 1) * $examens_limit;
$examens_filter = isset($_GET['examens_filter']) ? $_GET['examens_filter'] : 'tous';
$examens_search = isset($_GET['examens_search']) ? trim($_GET['examens_search']) : '';

$sql_examens_count = "SELECT COUNT(*) FROM examens e JOIN cours c ON e.id_cours = c.id_cours WHERE e.id_prof = ?";
$params_count = [$id_prof];
if ($examens_filter != 'tous') { $sql_examens_count .= " AND e.statut = ?"; $params_count[] = $examens_filter; }
if (!empty($examens_search)) { $sql_examens_count .= " AND (c.nom_cours LIKE ? OR e.titre LIKE ?)"; $params_count[] = "%$examens_search%"; $params_count[] = "%$examens_search%"; }
$stmt_count = $bdd->prepare($sql_examens_count);
$stmt_count->execute($params_count);
$total_examens = $stmt_count->fetchColumn();
$total_examens_pages = ceil($total_examens / $examens_limit);

$sql_examens = "SELECT e.*, c.nom_cours, cl.niveau, d.nom_dept, u.nom as doyen_nom, u.prenom as doyen_prenom
                FROM examens e
                JOIN cours c ON e.id_cours = c.id_cours
                JOIN classes cl ON e.id_classe = cl.id_classe
                JOIN departements d ON cl.id_dept = d.id_dept
                LEFT JOIN users u ON e.id_doyen = u.id_user
                WHERE e.id_prof = ?";
$params_examens = [$id_prof];
if ($examens_filter != 'tous') { $sql_examens .= " AND e.statut = ?"; $params_examens[] = $examens_filter; }
if (!empty($examens_search)) { $sql_examens .= " AND (c.nom_cours LIKE ? OR e.titre LIKE ?)"; $params_examens[] = "%$examens_search%"; $params_examens[] = "%$examens_search%"; }
$sql_examens .= " ORDER BY e.date_envoi DESC LIMIT $examens_limit OFFSET $examens_offset";
$stmt_examens = $bdd->prepare($sql_examens);
$stmt_examens->execute($params_examens);

$notes_page = isset($_GET['notes_page']) ? intval($_GET['notes_page']) : 1;
$notes_limit = 10;
$notes_offset = ($notes_page - 1) * $notes_limit;
$notes_filter = isset($_GET['notes_filter']) ? $_GET['notes_filter'] : 'brouillon';
$notes_search = isset($_GET['notes_search']) ? trim($_GET['notes_search']) : '';

$sql_notes_count = "SELECT COUNT(*) FROM notes n JOIN cours c ON n.id_cours = c.id_cours WHERE c.id_prof = ? AND n.statut = ?";
$params_notes_count = [$id_prof, $notes_filter];
if (!empty($notes_search)) { $sql_notes_count .= " AND (n.id_etudiant IN (SELECT id_etudiant FROM etudiants WHERE matricule LIKE ?))"; $params_notes_count[] = "%$notes_search%"; }
$stmt_notes_count = $bdd->prepare($sql_notes_count);
$stmt_notes_count->execute($params_notes_count);
$total_notes = $stmt_notes_count->fetchColumn();
$total_notes_pages = ceil($total_notes / $notes_limit);

$sql_notes = "SELECT n.*, u.nom, u.prenom, e.matricule, c.nom_cours
              FROM notes n
              JOIN etudiants e ON n.id_etudiant = e.id_etudiant
              JOIN users u ON e.id_user = u.id_user
              JOIN cours c ON n.id_cours = c.id_cours
              WHERE c.id_prof = ? AND n.statut = ?";
$params_notes = [$id_prof, $notes_filter];
if (!empty($notes_search)) { $sql_notes .= " AND e.matricule LIKE ?"; $params_notes[] = "%$notes_search%"; }
$sql_notes .= " ORDER BY n.date_saisie DESC LIMIT $notes_limit OFFSET $notes_offset";
$stmt_notes = $bdd->prepare($sql_notes);
$stmt_notes->execute($params_notes);

// Greeting selon l'heure
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
    <title>Espace Enseignant — UniVerse</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="assets/theme.css">
    <script src="assets/theme.js"></script>
    <style>
        :root {
            --bg-primary: #f5f7fa;
            --bg-sidebar: #111827;
            --bg-card: #ffffff;
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --text-muted: #64748b;
            --text-white: #ffffff;
            --accent-blue: #3b82f6;
            --accent-indigo: #6366f1;
            --accent-success: #10b981;
            --accent-warning: #f59e0b;
            --accent-danger: #ef4444;
            --accent-purple: #8b5cf6;
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
            background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%);
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
            background: linear-gradient(135deg, #60a5fa, #a78bfa);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }

        .user-profile {
            background: linear-gradient(135deg, rgba(99,102,241,0.15), rgba(139,92,246,0.08));
            padding: 20px 16px;
            border-radius: var(--radius);
            margin-bottom: 24px;
            text-align: center;
            border: 1px solid rgba(99,102,241,0.2);
            position: relative;
            overflow: hidden;
        }
        .user-profile::before {
            content: '';
            position: absolute; top: -40px; right: -40px;
            width: 100px; height: 100px;
            background: rgba(99,102,241,0.15);
            border-radius: 50%;
        }
        .profile-img {
            width: 68px; height: 68px; border-radius: 50%; object-fit: cover;
            margin-bottom: 10px; border: 3px solid rgba(99,102,241,0.5);
            position: relative; z-index: 1;
            box-shadow: 0 4px 16px rgba(99,102,241,0.3);
        }
        .user-profile .name { font-weight: 700; font-size: 15px; position: relative; z-index: 1; }
        .user-profile .title { font-size: 12px; color: #94a3b8; margin-top: 2px; position: relative; z-index: 1; }
        .badge-role {
            display: inline-block; padding: 5px 14px; font-size: 10px; font-weight: 700;
            text-transform: uppercase; border-radius: 20px; margin-top: 8px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white; letter-spacing: 0.5px;
            position: relative; z-index: 1;
            box-shadow: 0 4px 12px rgba(99,102,241,0.3);
        }

        .nav-links { list-style: none; display: flex; flex-direction: column; gap: 3px; flex: 1; }
        .nav-links a {
            color: #94a3b8; text-decoration: none; padding: 11px 14px; border-radius: 10px;
            display: flex; align-items: center; gap: 12px; font-weight: 600; font-size: 13px;
            transition: var(--transition); position: relative;
        }
        .nav-links a:hover { background: rgba(255,255,255,0.06); color: var(--text-white); transform: translateX(3px); }
        .nav-links a.active {
            background: linear-gradient(135deg, rgba(99,102,241,0.2), rgba(139,92,246,0.12));
            color: var(--text-white);
            box-shadow: inset 3px 0 0 #6366f1;
        }
        .nav-links a i { width: 20px; text-align: center; font-size: 14px; }
        .nav-badge {
            margin-left: auto; background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white; font-size: 10px; padding: 2px 8px; border-radius: 10px; font-weight: 700;
            box-shadow: 0 2px 8px rgba(239,68,68,0.4);
        }
        .nav-links .logout { color: #f87171; margin-top: auto; padding-top: 16px; border-top: 1px solid rgba(255,255,255,0.08); }
        .nav-links .logout:hover { background: rgba(239,68,68,0.12); color: #fecaca; }

        /* ===== MAIN CONTENT ===== */
        .main-content {
            margin-left: 280px;
            width: calc(100% - 280px);
            padding: 28px 32px;
            min-height: 100vh;
        }

        /* ===== HEADER BANNER ===== */
        .hero-banner {
            background: linear-gradient(135deg, #1e293b 0%, #334155 50%, #6366f1 100%);
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
            background: radial-gradient(circle, rgba(255,255,255,0.1), transparent 70%);
            border-radius: 50%;
        }
        .hero-banner::after {
            content: '';
            position: absolute; bottom: -60%; left: 20%;
            width: 300px; height: 300px;
            background: radial-gradient(circle, rgba(139,92,246,0.2), transparent 70%);
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
            font-size: 14px; opacity: 0.85; font-weight: 500;
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
            padding: 10px 20px; border-radius: 10px; border: none;
            font-weight: 600; font-size: 13px; cursor: pointer;
            transition: var(--transition); display: inline-flex; align-items: center; gap: 8px;
            text-decoration: none; white-space: nowrap;
        }
        .btn-primary { background: linear-gradient(135deg, #3b82f6, #6366f1); color: white; box-shadow: 0 4px 12px rgba(59,130,246,0.25); }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(59,130,246,0.35); }
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
        .stat-card::before {
            content: '';
            position: absolute; top: 0; left: 0; right: 0;
            height: 4px;
        }
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: transparent;
        }
        .stat-card .stat-top {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 14px;
        }
        .stat-card .stat-icon-wrap {
            width: 44px; height: 44px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; color: white;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .stat-card .stat-value { font-size: 28px; font-weight: 800; color: var(--text-primary); letter-spacing: -0.5px; }
        .stat-card .stat-label { font-size: 12px; color: var(--text-muted); font-weight: 600; margin-top: 4px; text-transform: uppercase; letter-spacing: 0.4px; }

        .stat-card.green::before { background: linear-gradient(90deg, #10b981, #34d399); }
        .stat-card.green .stat-icon-wrap { background: linear-gradient(135deg, #10b981, #059669); }
        .stat-card.blue::before { background: linear-gradient(90deg, #3b82f6, #60a5fa); }
        .stat-card.blue .stat-icon-wrap { background: linear-gradient(135deg, #3b82f6, #2563eb); }
        .stat-card.purple::before { background: linear-gradient(90deg, #8b5cf6, #a78bfa); }
        .stat-card.purple .stat-icon-wrap { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
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
            padding-bottom: 16px;
            border-bottom: 2px solid var(--border-light);
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
        tr:hover td { background: linear-gradient(90deg, #f8fbff, #eff6ff); }

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
        .status-badge.brouillon { background: linear-gradient(135deg, #e2e8f0, #f1f5f9); color: #475569; }

        .faculte-badge {
            display: inline-block; padding: 3px 12px; border-radius: 20px;
            background: linear-gradient(135deg, #eff6ff, #dbeafe); color: #1e40af;
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
        .btn-action.primary { background: #eff6ff; color: #1e40af; }
        .btn-action.primary:hover { background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; transform: translateY(-1px); }
        .btn-action.warning { background: #fef3c7; color: #92400e; }
        .btn-action.warning:hover { background: linear-gradient(135deg, #f59e0b, #d97706); color: white; transform: translateY(-1px); }

        /* ===== FILTER BAR ===== */
        .filter-bar {
            display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            padding: 18px 22px;
            border-radius: var(--radius);
            border: 1px solid var(--border-color);
        }
        .filter-bar .form-group { margin-bottom: 0; flex: 1; min-width: 140px; }
        .filter-bar .form-group label { font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); display: block; margin-bottom: 6px; }
        .filter-bar input, .filter-bar select {
            padding: 9px 14px; font-size: 13px; background: white;
            border: 1px solid var(--border-color); border-radius: 8px; width: 100%;
            outline: none; transition: var(--transition);
        }
        .filter-bar input:focus, .filter-bar select:focus { border-color: var(--accent-blue); box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
        .filter-actions { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }

        /* ===== PAGINATION ===== */
        .pagination { display: flex; justify-content: center; align-items: center; gap: 6px; margin-top: 22px; flex-wrap: wrap; }
        .pagination a, .pagination span {
            padding: 8px 14px; border-radius: 8px; font-weight: 600; font-size: 13px;
            transition: var(--transition); text-decoration: none;
        }
        .pagination a { background: white; color: var(--text-secondary); border: 1px solid var(--border-color); }
        .pagination a:hover { background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; border-color: transparent; transform: translateY(-2px); }
        .pagination .active { background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; border: 1px solid #2563eb; }
        .pagination .page-indicator { background: white; border: 1px solid var(--border-color); color: var(--text-secondary); }

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
            border-color: var(--accent-blue); background: white; box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
        }

        /* ===== EXAM UPLOAD ZONE ===== */
        .exam-form { margin-top: 5px; }
        .exam-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .exam-form-col { display: flex; flex-direction: column; gap: 16px; }

        .upload-zone {
            position: relative;
            border: 2px dashed #cbd5e1;
            border-radius: var(--radius);
            padding: 32px 20px;
            text-align: center;
            background: linear-gradient(135deg, #fafbfc, #f1f5f9);
            transition: var(--transition);
            margin-top: 10px;
            cursor: pointer;
        }
        .upload-zone:hover { border-color: var(--accent-blue); background: linear-gradient(135deg, #f0f4ff, #e8eeff); }
        .upload-zone.dragover { border-color: var(--accent-blue); background: linear-gradient(135deg, #e8eeff, #dbeafe); transform: scale(1.01); }
        .upload-icon { font-size: 44px; background: linear-gradient(135deg, #3b82f6, #6366f1); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin-bottom: 12px; }
        .upload-text p { margin: 0; color: var(--text-secondary); font-size: 14px; }
        .upload-text .upload-hint { font-size: 12px; color: var(--text-muted); margin-top: 4px; }
        .upload-input { position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; }
        .file-name-display {
            display: none; align-items: center; justify-content: center; gap: 8px;
            margin-top: 12px; padding: 10px 16px;
            background: linear-gradient(135deg, #d1fae5, #ecfdf5);
            border-radius: 10px; color: #065f46; font-weight: 600; font-size: 13px;
        }
        .submit-btn { width: 100%; padding: 14px; margin-top: 20px; font-size: 15px; justify-content: center; }

        /* ===== EMPTY STATE ===== */
        .empty-state {
            text-align: center; padding: 40px 20px;
            color: var(--text-muted); font-size: 14px;
        }
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
            .exam-form-grid { grid-template-columns: 1fr; gap: 12px; }
        }
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
            .panel { padding: 18px; }
        }
    </style>
</head>
<body>

    <!-- ================================================================
    SIDEBAR
    ================================================================ -->
    <div class="sidebar">
        <div class="logo">🎓 <span>UniVerse</span></div>
        <div class="user-profile">
            <img src="uploads/<?= htmlspecialchars($photo_profil); ?>" alt="Photo" class="profile-img">
            <p class="name">Prof. <?= htmlspecialchars($prof['nom'] . ' ' . $prof['prenom']); ?></p>
            <p class="title"><?= htmlspecialchars($prof['specialite'] ?? 'Enseignant'); ?></p>
            <span class="badge-role">Professeur</span>
        </div>
        <ul class="nav-links">
            <li><a href="?page=dashboard" class="<?= ($page=='dashboard')?'active':''; ?>"><i class="fas fa-chart-line"></i> Tableau de bord</a></li>
            <li><a href="?page=profil" class="<?= ($page=='profil')?'active':''; ?>"><i class="fas fa-user"></i> Mon profil</a></li>
            <li><a href="supports_cours.php"><i class="fas fa-folder-open"></i> Supports de cours</a></li>
            <li><a href="chat.php"><i class="fas fa-comments"></i> Chat</a></li>
            <li><a href="?page=mes_classes" class="<?= ($page=='mes_classes')?'active':''; ?>"><i class="fas fa-school"></i> Mes classes</a></li>
            <li><a href="?page=mes_etudiants" class="<?= ($page=='mes_etudiants')?'active':''; ?>"><i class="fas fa-users"></i> Mes étudiants</a></li>
            <li><a href="?page=examens" class="<?= ($page=='examens')?'active':''; ?>"><i class="fas fa-file-alt"></i> Mes examens <span class="nav-badge"><?= $nbExamensAttente + $nbExamensRejetes; ?></span></a></li>
            <li><a href="?page=envoyer_examen" class="<?= ($page=='envoyer_examen')?'active':''; ?>"><i class="fas fa-paper-plane"></i> Envoyer examen</a></li>
            <li><a href="?page=notes" class="<?= ($page=='notes')?'active':''; ?>"><i class="fas fa-pen"></i> Notes <span class="nav-badge"><?= $nbNotesBrouillon + $nbNotesSoumises; ?></span></a></li>
            <li><a href="?page=messages" class="<?= ($page=='messages')?'active':''; ?>"><i class="fas fa-envelope"></i> Messages <span class="nav-badge"><?= count(array_filter($messages_list, function($m) use ($id_user) { return $m['id_destinataire'] == $id_user && !$m['lu']; })); ?></span></a></li>
            <li><a href="?page=notifications" class="<?= ($page=='notifications')?'active':''; ?>"><i class="fas fa-bell"></i> Notifications <span class="nav-badge"><?= $nbNotifs; ?></span></a></li>
            <li><a href="?page=emploi_temps" class="<?= ($page=='emploi_temps')?'active':''; ?>"><i class="fas fa-calendar-alt"></i> Emploi du temps</a></li>
            <li><a href="?page=statistiques" class="<?= ($page=='statistiques')?'active':''; ?>"><i class="fas fa-chart-pie"></i> Statistiques</a></li>
            <li><a href="?page=historique" class="<?= ($page=='historique')?'active':''; ?>"><i class="fas fa-history"></i> Historique</a></li>
            <li><a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
        </ul>
    </div>

    <!-- ================================================================
    MAIN CONTENT
    ================================================================ -->
    <div class="main-content">

        <!-- HERO BANNER -->
        <div class="hero-banner">
            <div class="hero-content">
                <div>
                    <h1><?= $salutation ?>, Prof. <?= htmlspecialchars($prof['prenom']) ?> 👋</h1>
                    <p><?= date('l d F Y') ?> — Bienvenue sur votre espace enseignant UniVerse</p>
                </div>
                <div class="hero-actions">
                    <button class="theme-toggle-btn" onclick="toggleTheme()" title="Mode sombre">🌙</button>
                    <a href="?page=envoyer_examen" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Envoyer examen</a>
                    <a href="?page=notes&action=saisie" class="btn btn-success"><i class="fas fa-pen"></i> Saisir notes</a>
                </div>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert <?= $message_type; ?>"><?= $message; ?></div>
        <?php endif; ?>

        <!-- ============================================================
        PAGE : DASHBOARD
        ============================================================ -->
        <?php if ($page == 'dashboard'): ?>
        <div class="stats-grid">
            <div class="stat-card green">
                <div class="stat-top"><div class="stat-icon-wrap"><i class="fas fa-users"></i></div></div>
                <div class="stat-value"><?= $nbEtudiants; ?></div>
                <div class="stat-label">Étudiants</div>
            </div>
            <div class="stat-card blue">
                <div class="stat-top"><div class="stat-icon-wrap"><i class="fas fa-book"></i></div></div>
                <div class="stat-value"><?= $nbCours; ?></div>
                <div class="stat-label">Mes cours</div>
            </div>
            <div class="stat-card purple">
                <div class="stat-top"><div class="stat-icon-wrap"><i class="fas fa-school"></i></div></div>
                <div class="stat-value"><?= $nbClasses; ?></div>
                <div class="stat-label">Classes</div>
            </div>
            <div class="stat-card orange alert-card" onclick="location.href='?page=examens&examens_filter=en_attente'">
                <div class="stat-top"><div class="stat-icon-wrap"><i class="fas fa-file-alt"></i></div></div>
                <div class="stat-value"><?= $nbExamensAttente + $nbExamensRejetes; ?></div>
                <div class="stat-label">Examens à traiter</div>
            </div>
            <div class="stat-card orange alert-card" onclick="location.href='?page=notes&notes_filter=brouillon'">
                <div class="stat-top"><div class="stat-icon-wrap"><i class="fas fa-pen"></i></div></div>
                <div class="stat-value"><?= $nbNotesBrouillon; ?></div>
                <div class="stat-label">Notes à compléter</div>
            </div>
            <div class="stat-card indigo">
                <div class="stat-top"><div class="stat-icon-wrap"><i class="fas fa-check-double"></i></div></div>
                <div class="stat-value"><?= $nbNotesSoumises; ?></div>
                <div class="stat-label">Notes soumises</div>
            </div>
            <div class="stat-card green" onclick="location.href='?page=examens&examens_filter=valide'">
                <div class="stat-top"><div class="stat-icon-wrap"><i class="fas fa-thumbs-up"></i></div></div>
                <div class="stat-value"><?= $nbExamensValides; ?></div>
                <div class="stat-label">Examens validés</div>
            </div>
            <div class="stat-card red" onclick="location.href='?page=examens&examens_filter=rejete'">
                <div class="stat-top"><div class="stat-icon-wrap"><i class="fas fa-ban"></i></div></div>
                <div class="stat-value"><?= $nbExamensRejetes; ?></div>
                <div class="stat-label">Examens rejetés</div>
            </div>
            <div class="stat-card cyan">
                <div class="stat-top"><div class="stat-icon-wrap"><i class="fas fa-bell"></i></div></div>
                <div class="stat-value"><?= $nbNotifs; ?></div>
                <div class="stat-label">Notifications</div>
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
            <div class="panel-header"><h2><i class="fas fa-list" style="color:var(--accent-blue);"></i> Dernières notes</h2><a href="?page=notes" class="btn-action primary">Voir tout →</a></div>
            <div class="table-responsive">
                <table>
                    <thead><tr><th>Étudiant</th><th>Cours</th><th>Note</th><th>Session</th><th>Date</th></tr></thead>
                    <tbody>
                        <?php foreach($dernieresNotes as $n): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($n['nom'].' '.$n['prenom']); ?></strong></td>
                                <td><?= htmlspecialchars($n['nom_cours']); ?></td>
                                <td><span class="status-badge <?= $n['note']>=10?'valide':'rejete'; ?>"><?= number_format($n['note'],2); ?></span></td>
                                <td><?= htmlspecialchars($n['session']); ?></td>
                                <td><?= date('d/m/Y', strtotime($n['date_saisie'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($dernieresNotes)): ?>
                            <tr><td colspan="5" class="empty-state"><i class="fas fa-inbox"></i>Aucune note récente</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="panel">
            <div class="panel-header"><h2><i class="fas fa-exclamation-triangle" style="color:var(--accent-warning);"></i> Alertes</h2></div>
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px,1fr)); gap:12px;">
                <?php if ($nbExamensAttente > 0): ?><div class="alert warning" style="margin:0; cursor:pointer;" onclick="location.href='?page=examens&examens_filter=en_attente'">⚠ <?= $nbExamensAttente; ?> examen(s) en attente</div><?php endif; ?>
                <?php if ($nbExamensRejetes > 0): ?><div class="alert error" style="margin:0; cursor:pointer;" onclick="location.href='?page=examens&examens_filter=rejete'">⚠ <?= $nbExamensRejetes; ?> examen(s) rejeté(s)</div><?php endif; ?>
                <?php if ($nbNotesSoumises > 0): ?><div class="alert info" style="margin:0; cursor:pointer;" onclick="location.href='?page=notes&notes_filter=soumis'">📝 <?= $nbNotesSoumises; ?> note(s) en attente</div><?php endif; ?>
                <?php if ($nbNotesBrouillon > 0): ?><div class="alert warning" style="margin:0; cursor:pointer;" onclick="location.href='?page=notes&notes_filter=brouillon'">📝 <?= $nbNotesBrouillon; ?> note(s) en brouillon</div><?php endif; ?>
                <?php if ($nbNotifs > 0): ?><div class="alert info" style="margin:0; cursor:pointer;" onclick="location.href='?page=notifications'">🔔 <?= $nbNotifs; ?> notification(s)</div><?php endif; ?>
                <?php if ($nbExamensAttente == 0 && $nbExamensRejetes == 0 && $nbNotesSoumises == 0 && $nbNotesBrouillon == 0): ?><div class="alert success" style="margin:0;">✅ Tout est à jour !</div><?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- ============================================================
        PAGE : MES COURS
        ============================================================ -->
        <?php if ($page == 'mes_cours'): ?>
        <div class="panel">
            <div class="panel-header"><h2><i class="fas fa-book" style="color:var(--accent-blue);"></i> Mes cours</h2></div>
            <div class="table-responsive">
                <table>
                    <thead><tr><th>Nom</th><th>Classe</th><th>Département</th><th>Semestre</th><th>Crédits</th><th>Étudiants</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach($mesCours as $c): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($c['nom_cours']); ?></strong></td>
                                <td><?= htmlspecialchars($c['niveau']); ?></td>
                                <td><?= htmlspecialchars($c['nom_dept']); ?></td>
                                <td><?= htmlspecialchars($c['semestre']); ?></td>
                                <td><?= $c['credit']; ?></td>
                                <td><?= $c['nb_etudiants']; ?></td>
                                <td>
                                    <a href="?page=mes_etudiants&id_cours=<?= $c['id_cours']; ?>" class="btn-action primary"><i class="fas fa-users"></i></a>
                                    <a href="?page=notes&action=saisie&id_cours=<?= $c['id_cours']; ?>" class="btn-action success"><i class="fas fa-pen"></i></a>
                                    <a href="?page=envoyer_examen&id_cours=<?= $c['id_cours']; ?>" class="btn-action warning"><i class="fas fa-paper-plane"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- ============================================================
        PAGE : MES CLASSES
        ============================================================ -->
        <?php if ($page == 'mes_classes'): ?>
        <div class="panel">
            <div class="panel-header"><h2><i class="fas fa-school" style="color:var(--accent-purple);"></i> Mes classes</h2></div>
            <div class="table-responsive">
                <table>
                    <thead><tr><th>Classe</th><th>Département</th><th>Étudiants</th><th>Cours</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php
                        $mesClasses = $bdd->prepare("
                            SELECT DISTINCT c.id_classe, c.niveau, d.nom_dept,
                                   (SELECT COUNT(*) FROM etudiants WHERE id_classe = c.id_classe) as nb_etudiants,
                                   (SELECT COUNT(*) FROM cours WHERE id_classe = c.id_classe AND id_prof = ?) as nb_cours
                            FROM classes c
                            JOIN departements d ON c.id_dept = d.id_dept
                            JOIN cours co ON co.id_classe = c.id_classe
                            WHERE co.id_prof = ?
                            ORDER BY c.niveau
                        ");
                        $mesClasses->execute([$id_prof, $id_prof]);
                        while($cl = $mesClasses->fetch()): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($cl['niveau']); ?></strong></td>
                                <td><?= htmlspecialchars($cl['nom_dept']); ?></td>
                                <td><?= $cl['nb_etudiants']; ?></td>
                                <td><?= $cl['nb_cours']; ?></td>
                                <td>
                                    <a href="?page=mes_etudiants&id_classe=<?= $cl['id_classe']; ?>" class="btn-action primary"><i class="fas fa-users"></i></a>
                                    <a href="?page=envoyer_examen&id_classe=<?= $cl['id_classe']; ?>" class="btn-action warning"><i class="fas fa-paper-plane"></i></a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- ============================================================
        PAGE : MES ÉTUDIANTS
        ============================================================ -->
        <?php if ($page == 'mes_etudiants'): ?>
        <div class="panel">
            <div class="panel-header">
                <h2><i class="fas fa-users" style="color:var(--accent-success);"></i> Mes étudiants</h2>
                <a href="?page=mes_etudiants" class="btn btn-outline-dark"><i class="fas fa-sync-alt"></i> Réinitialiser</a>
            </div>

            <form method="GET" class="filter-bar" style="margin-bottom:18px;">
                <input type="hidden" name="page" value="mes_etudiants">
                <div class="form-group">
                    <label>Département</label>
                    <select name="filtre_dept" onchange="this.form.submit()">
                        <option value="">-- Tous --</option>
                        <?php
                        $depts = $bdd->query("SELECT id_dept, nom_dept FROM departements ORDER BY nom_dept");
                        $filtre_dept = isset($_GET['filtre_dept']) ? intval($_GET['filtre_dept']) : 0;
                        while($d = $depts->fetch()): ?>
                            <option value="<?= $d['id_dept']; ?>" <?= ($filtre_dept==$d['id_dept'])?'selected':''; ?>><?= htmlspecialchars($d['nom_dept']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Faculté</label>
                    <select name="filtre_faculte" onchange="this.form.submit()">
                        <option value="">-- Toutes --</option>
                        <?php
                        $facultes = $bdd->query("SELECT DISTINCT faculte FROM departements WHERE faculte IS NOT NULL ORDER BY faculte");
                        $filtre_faculte = isset($_GET['filtre_faculte']) ? trim($_GET['filtre_faculte']) : '';
                        while($f = $facultes->fetch()): ?>
                            <option value="<?= htmlspecialchars($f['faculte']); ?>" <?= ($filtre_faculte==$f['faculte'])?'selected':''; ?>><?= htmlspecialchars($f['faculte']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Classe</label>
                    <select name="filtre_classe" onchange="this.form.submit()">
                        <option value="">-- Toutes --</option>
                        <?php
                        $classesF = $bdd->query("SELECT id_classe, niveau FROM classes ORDER BY niveau");
                        $filtre_classe = isset($_GET['filtre_classe']) ? intval($_GET['filtre_classe']) : 0;
                        while($cl = $classesF->fetch()): ?>
                            <option value="<?= $cl['id_classe']; ?>" <?= ($filtre_classe==$cl['id_classe'])?'selected':''; ?>><?= htmlspecialchars($cl['niveau']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Cours</label>
                    <select name="id_cours" onchange="this.form.submit()">
                        <option value="">-- Tous --</option>
                        <?php
                        $coursF = $bdd->prepare("SELECT id_cours, nom_cours FROM cours WHERE id_prof = ? ORDER BY nom_cours");
                        $coursF->execute([$id_prof]);
                        $id_cours_filtre = isset($_GET['id_cours']) ? intval($_GET['id_cours']) : 0;
                        while($cf = $coursF->fetch()): ?>
                            <option value="<?= $cf['id_cours']; ?>" <?= ($id_cours_filtre==$cf['id_cours'])?'selected':''; ?>><?= htmlspecialchars($cf['nom_cours']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group" style="flex:2; min-width:200px;">
                    <label>Recherche</label>
                    <input type="text" name="search" placeholder="Nom, prénom, matricule..." value="<?= htmlspecialchars($_GET['search'] ?? ''); ?>">
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filtrer</button>
                    <a href="?page=mes_etudiants" class="btn btn-outline-dark">Réinitialiser</a>
                </div>
            </form>

            <?php
            $sql_etudiants = "
                SELECT DISTINCT e.id_etudiant, u.nom, u.prenom, e.matricule, c.niveau, d.nom_dept, d.faculte, c.id_classe
                FROM etudiants e
                JOIN users u ON e.id_user = u.id_user
                JOIN classes c ON e.id_classe = c.id_classe
                JOIN departements d ON c.id_dept = d.id_dept
                JOIN cours co ON co.id_classe = c.id_classe
                WHERE co.id_prof = ?
            ";
            $params_etudiants = [$id_prof];
            if (!empty($filtre_dept)) { $sql_etudiants .= " AND d.id_dept = ?"; $params_etudiants[] = $filtre_dept; }
            if (!empty($filtre_faculte)) { $sql_etudiants .= " AND d.faculte = ?"; $params_etudiants[] = $filtre_faculte; }
            if (!empty($filtre_classe)) { $sql_etudiants .= " AND c.id_classe = ?"; $params_etudiants[] = $filtre_classe; }
            if (!empty($id_cours_filtre)) { $sql_etudiants .= " AND co.id_cours = ?"; $params_etudiants[] = $id_cours_filtre; }
            if (!empty($_GET['search'])) {
                $search = '%' . trim($_GET['search']) . '%';
                $sql_etudiants .= " AND (u.nom LIKE ? OR u.prenom LIKE ? OR e.matricule LIKE ?)";
                $params_etudiants[] = $search; $params_etudiants[] = $search; $params_etudiants[] = $search;
            }
            $sql_etudiants .= " ORDER BY u.nom, u.prenom";
            $stmt_etudiants = $bdd->prepare($sql_etudiants);
            $stmt_etudiants->execute($params_etudiants);
            $mesEtudiants = $stmt_etudiants->fetchAll();
            ?>

            <div class="table-responsive">
                <table>
                    <thead><tr><th>Matricule</th><th>Nom</th><th>Prénom</th><th>Classe</th><th>Département</th><th>Faculté</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php if(count($mesEtudiants) > 0): ?>
                            <?php foreach($mesEtudiants as $e): ?>
                                <tr>
                                    <td><?= htmlspecialchars($e['matricule']); ?></td>
                                    <td><strong><?= htmlspecialchars($e['nom']); ?></strong></td>
                                    <td><?= htmlspecialchars($e['prenom']); ?></td>
                                    <td><?= htmlspecialchars($e['niveau']); ?></td>
                                    <td><?= htmlspecialchars($e['nom_dept']); ?></td>
                                    <td><span class="faculte-badge"><?= htmlspecialchars($e['faculte'] ?: 'Non assignée'); ?></span></td>
                                    <td>
                                        <a href="?page=notes&action=saisie&id_etudiant=<?= $e['id_etudiant']; ?>" class="btn-action success"><i class="fas fa-pen"></i></a>
                                        <a href="#" class="btn-action primary"><i class="fas fa-eye"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="7" class="empty-state"><i class="fas fa-search"></i>Aucun étudiant trouvé avec ces filtres.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- ============================================================
        PAGE : ENVOYER UN EXAMEN
        ============================================================ -->
        <?php if ($page == 'envoyer_examen'): ?>
        <div class="panel">
            <div class="panel-header">
                <h2><i class="fas fa-paper-plane" style="color:var(--accent-blue);"></i> Envoyer un examen au Doyen</h2>
                <span style="font-size:13px; color:var(--text-muted);">📄 PDF, DOC, DOCX (max 10 Mo)</span>
            </div>

            <form method="POST" enctype="multipart/form-data" class="exam-form">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token; ?>">

                <div class="exam-form-grid">
                    <div class="exam-form-col">
                        <div class="form-group">
                            <label>Cours <span style="color:var(--accent-danger);">*</span></label>
                            <select name="id_cours" id="select_cours" required onchange="chargerClasse()">
                                <option value="">-- Sélectionner --</option>
                                <?php foreach($coursList as $c):
                                    $selected = (isset($_GET['id_cours']) && $_GET['id_cours'] == $c['id_cours']) ? 'selected' : '';
                                ?>
                                    <option value="<?= $c['id_cours']; ?>" data-classe="<?= $c['id_classe']; ?>" <?= $selected; ?>>
                                        <?= htmlspecialchars($c['nom_cours'] . ' (' . $c['niveau'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Titre <span style="color:var(--accent-danger);">*</span></label>
                            <input type="text" name="titre" placeholder="Ex: Examen final" required>
                        </div>
                        <div class="form-group">
                            <label>Semestre <span style="color:var(--accent-danger);">*</span></label>
                            <select name="semestre" required>
                                <option value="Semestre 1">Semestre 1</option>
                                <option value="Semestre 2">Semestre 2</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Année académique <span style="color:var(--accent-danger);">*</span></label>
                            <input type="text" name="annee_academique" value="<?= date('Y') . '-' . (date('Y')+1); ?>" required>
                        </div>
                    </div>

                    <div class="exam-form-col">
                        <div class="form-group">
                            <label>Classe <span style="color:var(--accent-danger);">*</span></label>
                            <select name="id_classe" id="select_classe" required>
                                <option value="">-- Sélectionner un cours d'abord --</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Type d'examen</label>
                            <select name="type_examen">
                                <option value="Examen">📝 Examen</option>
                                <option value="Interrogation">📋 Interrogation</option>
                                <option value="TP">🔬 TP</option>
                                <option value="TD">📖 TD</option>
                                <option value="Rattrapage">🔄 Rattrapage</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Durée (minutes)</label>
                            <input type="number" name="duree" value="120" min="30" max="240">
                        </div>
                        <div class="form-group">
                            <label>Points totaux</label>
                            <input type="number" name="points_total" value="20" min="1" max="100">
                        </div>
                    </div>
                </div>

                <div class="form-group" style="margin-top:10px;">
                    <label>Instructions</label>
                    <textarea name="instructions" rows="3" placeholder="Instructions pour l'examen..."></textarea>
                </div>

                <div class="upload-zone" id="uploadZone">
                    <div class="upload-icon"><i class="fas fa-cloud-upload-alt"></i></div>
                    <div class="upload-text">
                        <p><strong>Déposez votre fichier ici</strong> ou cliquez pour parcourir</p>
                        <p class="upload-hint">PDF, DOC, DOCX — Taille max : 10 Mo</p>
                    </div>
                    <input type="file" name="fichier_examen" id="fichier_examen" class="upload-input" accept=".pdf,.doc,.docx" required>
                    <div id="file-name-display" class="file-name-display" style="display:none;">
                        <i class="fas fa-file-alt"></i> <span id="file-name-text"></span>
                    </div>
                </div>

                <button type="submit" name="envoyer_examen" class="btn btn-primary submit-btn">
                    <i class="fas fa-paper-plane"></i> Envoyer au Doyen
                </button>
            </form>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const fileInput = document.getElementById('fichier_examen');
                const fileNameDisplay = document.getElementById('file-name-display');
                const fileNameText = document.getElementById('file-name-text');

                if (fileInput) {
                    fileInput.addEventListener('change', function() {
                        if (this.files && this.files.length > 0) {
                            fileNameText.textContent = this.files[0].name;
                            fileNameDisplay.style.display = 'flex';
                        } else {
                            fileNameDisplay.style.display = 'none';
                        }
                    });
                }

                const selectCours = document.getElementById('select_cours');
                const selectClasse = document.getElementById('select_classe');

                if (selectCours && selectClasse) {
                    function chargerClasse() {
                        var selected = selectCours.options[selectCours.selectedIndex];
                        var classeId = selected.getAttribute('data-classe');
                        if (classeId && classeId !== '') {
                            selectClasse.innerHTML = '<option value="' + classeId + '">Classe sélectionnée</option>';
                            selectClasse.disabled = false;
                        } else {
                            selectClasse.innerHTML = '<option value="">-- Sélectionner un cours d\'abord --</option>';
                            selectClasse.disabled = true;
                        }
                    }
                    if (selectCours.value) chargerClasse();
                    else selectClasse.disabled = true;
                    selectCours.addEventListener('change', chargerClasse);
                }

                const uploadZone = document.getElementById('uploadZone');
                if (uploadZone) {
                    uploadZone.addEventListener('dragover', function(e) {
                        e.preventDefault();
                        this.classList.add('dragover');
                    });
                    uploadZone.addEventListener('dragleave', function(e) {
                        e.preventDefault();
                        this.classList.remove('dragover');
                    });
                    uploadZone.addEventListener('drop', function(e) {
                        e.preventDefault();
                        this.classList.remove('dragover');
                        const files = e.dataTransfer.files;
                        if (files.length > 0) {
                            fileInput.files = files;
                            fileNameText.textContent = files[0].name;
                            fileNameDisplay.style.display = 'flex';
                        }
                    });
                }
            });
        </script>
        <?php endif; ?>

        <!-- ============================================================
        PAGE : MES EXAMENS
        ============================================================ -->
        <?php if ($page == 'examens'): ?>
        <div class="panel">
            <div class="panel-header">
                <h2><i class="fas fa-file-alt" style="color:var(--accent-blue);"></i> Mes examens</h2>
                <div class="panel-actions">
                    <a href="?page=examens&examens_filter=tous" class="btn-action primary">Tous</a>
                    <a href="?page=examens&examens_filter=en_attente" class="btn-action warning">En attente</a>
                    <a href="?page=examens&examens_filter=valide" class="btn-action success">Validés</a>
                    <a href="?page=examens&examens_filter=rejete" class="btn-action danger">Rejetés</a>
                </div>
            </div>
            <form method="GET" class="filter-bar" style="margin-bottom:18px;">
                <input type="hidden" name="page" value="examens">
                <div class="form-group">
                    <label>Recherche</label>
                    <input type="text" name="examens_search" placeholder="Cours ou titre..." value="<?= htmlspecialchars($examens_search); ?>">
                </div>
                <div class="form-group">
                    <label>Statut</label>
                    <select name="examens_filter">
                        <option value="tous" <?= ($examens_filter=='tous')?'selected':''; ?>>Tous</option>
                        <option value="soumis" <?= ($examens_filter=='soumis')?'selected':''; ?>>Soumis</option>
                        <option value="en_attente" <?= ($examens_filter=='en_attente')?'selected':''; ?>>En attente</option>
                        <option value="valide" <?= ($examens_filter=='valide')?'selected':''; ?>>Validés</option>
                        <option value="rejete" <?= ($examens_filter=='rejete')?'selected':''; ?>>Rejetés</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filtrer</button>
                <a href="?page=examens" class="btn btn-outline-dark">Réinitialiser</a>
            </form>
            <div class="table-responsive">
                <table>
                    <thead><tr><th>Cours</th><th>Classe</th><th>Titre</th><th>Statut</th><th>Date</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php while($ex = $stmt_examens->fetch()):
                            $statutClass = $ex['statut'] == 'valide' ? 'valide' : ($ex['statut'] == 'rejete' ? 'rejete' : 'soumis');
                            $statutLabel = $ex['statut'] == 'valide' ? '✅ Validé' : ($ex['statut'] == 'rejete' ? '❌ Rejeté' : '⏳ En attente');
                            $displayMotif = ($ex['statut'] == 'rejete' && $ex['motif_rejet']) ? ' title="Motif: '.htmlspecialchars($ex['motif_rejet']).'"' : '';
                        ?>
                            <tr>
                                <td><?= htmlspecialchars($ex['nom_cours']); ?></td>
                                <td><?= htmlspecialchars($ex['niveau']); ?></td>
                                <td><strong><?= htmlspecialchars($ex['titre']); ?></strong></td>
                                <td><span class="status-badge <?= $statutClass; ?>" <?= $displayMotif; ?>><?= $statutLabel; ?></span></td>
                                <td><?= date('d/m/Y', strtotime($ex['date_envoi'])); ?></td>
                                <td>
                                    <?php if ($ex['sujet_fichier']): ?>
                                        <a href="download_examen.php?id=<?= $ex['id_examen']; ?>" class="btn-action primary"><i class="fas fa-download"></i></a>
                                    <?php endif; ?>
                                    <?php if ($ex['statut'] == 'rejete'): ?>
                                        <button class="btn-action warning" onclick="alert('Motif : <?= htmlspecialchars($ex['motif_rejet']); ?>')"><i class="fas fa-info-circle"></i></button>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="csrf_token" value="<?= $csrf_token; ?>">
                                            <input type="hidden" name="id_examen" value="<?= $ex['id_examen']; ?>">
                                            <button type="submit" name="renvoyer_examen" class="btn-action success" onclick="return confirm('Renvoyer cet examen ?')"><i class="fas fa-redo"></i></button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if (in_array($ex['statut'], ['brouillon', 'rejete'])): ?>
                                        <a href="?page=examens&supprimer_examen=<?= $ex['id_examen']; ?>&csrf_token=<?= $csrf_token; ?>" class="btn-action danger" onclick="return confirm('Supprimer cet examen ?')"><i class="fas fa-trash"></i></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        <?php if ($stmt_examens->rowCount() == 0): ?>
                            <tr><td colspan="6" class="empty-state"><i class="fas fa-inbox"></i>Aucun examen trouvé.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($total_examens_pages > 1): ?>
                <div class="pagination">
                    <?php if ($examens_page > 1): ?>
                        <a href="?page=examens&examens_page=<?= $examens_page-1; ?>&examens_filter=<?= $examens_filter; ?>&examens_search=<?= urlencode($examens_search); ?>">‹ Précédent</a>
                    <?php endif; ?>
                    <?php for($i=1; $i<=$total_examens_pages; $i++): ?>
                        <a href="?page=examens&examens_page=<?= $i; ?>&examens_filter=<?= $examens_filter; ?>&examens_search=<?= urlencode($examens_search); ?>" class="<?= ($i==$examens_page)?'active':''; ?>"><?= $i; ?></a>
                    <?php endfor; ?>
                    <?php if ($examens_page < $total_examens_pages): ?>
                        <a href="?page=examens&examens_page=<?= $examens_page+1; ?>&examens_filter=<?= $examens_filter; ?>&examens_search=<?= urlencode($examens_search); ?>">Suivant ›</a>
                    <?php endif; ?>
                    <span class="page-indicator">Page <?= $examens_page; ?> / <?= $total_examens_pages; ?></span>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- ============================================================
        PAGE : NOTES
        ============================================================ -->
        <?php if ($page == 'notes'): ?>
        <div class="panel">
            <div class="panel-header">
                <h2><i class="fas fa-pen" style="color:var(--accent-success);"></i> Gestion des notes</h2>
                <div class="panel-actions">
                    <a href="?page=notes&notes_filter=brouillon" class="btn-action warning">Brouillons</a>
                    <a href="?page=notes&notes_filter=soumis" class="btn-action primary">Soumises</a>
                    <a href="?page=notes&notes_filter=rejete" class="btn-action danger">Rejetées</a>
                    <a href="?page=notes&action=saisie" class="btn-action success">Saisir</a>
                </div>
            </div>

            <?php if (isset($_GET['action']) && $_GET['action'] == 'saisie'): ?>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token; ?>">
                <div class="grid-2">
                    <div class="form-group">
                        <label>Cours <span style="color:var(--accent-danger);">*</span></label>
                        <select name="id_cours" required>
                            <option value="">-- Sélectionner --</option>
                            <?php
                            $coursSaisie = $bdd->prepare("SELECT id_cours, nom_cours FROM cours WHERE id_prof = ?");
                            $coursSaisie->execute([$id_prof]);
                            while($cs = $coursSaisie->fetch()): ?>
                                <option value="<?= $cs['id_cours']; ?>" <?= (isset($_GET['id_cours']) && $_GET['id_cours'] == $cs['id_cours']) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($cs['nom_cours']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Session <span style="color:var(--accent-danger);">*</span></label>
                        <select name="session" required>
                            <option value="1ère Session">1ère Session</option>
                            <option value="2ème Session">2ème Session</option>
                        </select>
                    </div>
                </div>
                <?php
                if (isset($_POST['id_cours']) || isset($_GET['id_cours'])) {
                    $id_cours_sel = isset($_POST['id_cours']) ? intval($_POST['id_cours']) : (isset($_GET['id_cours']) ? intval($_GET['id_cours']) : 0);
                    $session_sel = isset($_POST['session']) ? trim($_POST['session']) : '1ère Session';
                    $etudiantsNotes = $bdd->prepare("
                        SELECT e.id_etudiant, u.nom, u.prenom, e.matricule, n.note, n.id_note
                        FROM etudiants e
                        JOIN users u ON e.id_user = u.id_user
                        JOIN classes c ON e.id_classe = c.id_classe
                        JOIN cours co ON co.id_classe = c.id_classe
                        LEFT JOIN notes n ON n.id_etudiant = e.id_etudiant AND n.id_cours = co.id_cours AND n.session = ?
                        WHERE co.id_cours = ? AND co.id_prof = ?
                        ORDER BY u.nom
                    ");
                    $etudiantsNotes->execute([$session_sel, $id_cours_sel, $id_prof]);
                ?>
                <div style="margin-top:18px;">
                    <div class="table-responsive">
                        <table>
                            <thead><tr><th>Matricule</th><th>Étudiant</th><th>Note /20</th></tr></thead>
                            <tbody>
                                <?php while($en = $etudiantsNotes->fetch()): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($en['matricule']); ?></td>
                                        <td><strong><?= htmlspecialchars($en['nom'].' '.$en['prenom']); ?></strong></td>
                                        <td>
                                            <input type="number" name="notes[<?= $en['id_etudiant']; ?>]" value="<?= $en['note'] ?? ''; ?>"
                                                   step="0.25" min="0" max="20"
                                                   style="width:110px; padding:8px; border-radius:8px; border:1.5px solid var(--border-color); text-align:center; font-weight:600;">
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <input type="hidden" name="id_cours" value="<?= $id_cours_sel; ?>">
                <input type="hidden" name="session" value="<?= $session_sel; ?>">
                <div style="display:flex; gap:12px; margin-top:18px;">
                    <button type="submit" name="sauvegarder_notes_brouillon" class="btn btn-warning"><i class="fas fa-save"></i> Sauvegarder brouillon</button>
                    <button type="submit" name="soumettre_notes" class="btn btn-primary" onclick="return confirm('Soumettre ces notes au Doyen ?')"><i class="fas fa-paper-plane"></i> Soumettre</button>
                </div>
                <?php } else { ?>
                <div class="empty-state"><i class="fas fa-info-circle"></i>Sélectionnez un cours pour commencer la saisie.</div>
                <?php } ?>
            </form>

            <?php else: ?>
            <form method="GET" class="filter-bar" style="margin-bottom:18px;">
                <input type="hidden" name="page" value="notes">
                <input type="hidden" name="notes_filter" value="<?= $notes_filter; ?>">
                <div class="form-group">
                    <label>Recherche</label>
                    <input type="text" name="notes_search" placeholder="Matricule..." value="<?= htmlspecialchars($notes_search); ?>">
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filtrer</button>
                <a href="?page=notes&notes_filter=<?= $notes_filter; ?>" class="btn btn-outline-dark">Réinitialiser</a>
            </form>
            <div class="table-responsive">
                <table>
                    <thead><tr><th>Étudiant</th><th>Matricule</th><th>Cours</th><th>Note</th><th>Session</th><th>Statut</th></tr></thead>
                    <tbody>
                        <?php while($n = $stmt_notes->fetch()): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($n['nom'].' '.$n['prenom']); ?></strong></td>
                                <td><?= htmlspecialchars($n['matricule']); ?></td>
                                <td><?= htmlspecialchars($n['nom_cours']); ?></td>
                                <td><strong style="color:<?= $n['note']>=10 ? 'var(--accent-success)' : 'var(--accent-danger)' ?>;"><?= number_format($n['note'],2); ?>/20</strong></td>
                                <td><?= htmlspecialchars($n['session']); ?></td>
                                <td><span class="status-badge <?= $n['statut']; ?>"><?= $n['statut']; ?></span></td>
                            </tr>
                        <?php endwhile; ?>
                        <?php if ($stmt_notes->rowCount() == 0): ?>
                            <tr><td colspan="6" class="empty-state"><i class="fas fa-inbox"></i>Aucune note trouvée.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($total_notes_pages > 1): ?>
                <div class="pagination">
                    <?php if ($notes_page > 1): ?>
                        <a href="?page=notes&notes_page=<?= $notes_page-1; ?>&notes_filter=<?= $notes_filter; ?>&notes_search=<?= urlencode($notes_search); ?>">‹ Précédent</a>
                    <?php endif; ?>
                    <?php for($i=1; $i<=$total_notes_pages; $i++): ?>
                        <a href="?page=notes&notes_page=<?= $i; ?>&notes_filter=<?= $notes_filter; ?>&notes_search=<?= urlencode($notes_search); ?>" class="<?= ($i==$notes_page)?'active':''; ?>"><?= $i; ?></a>
                    <?php endfor; ?>
                    <?php if ($notes_page < $total_notes_pages): ?>
                        <a href="?page=notes&notes_page=<?= $notes_page+1; ?>&notes_filter=<?= $notes_filter; ?>&notes_search=<?= urlencode($notes_search); ?>">Suivant ›</a>
                    <?php endif; ?>
                    <span class="page-indicator">Page <?= $notes_page; ?> / <?= $total_notes_pages; ?></span>
                </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- ============================================================
        PAGE : MESSAGES
        ============================================================ -->
        <?php if ($page == 'messages'): ?>
        <div class="panel">
            <div class="panel-header">
                <h2><i class="fas fa-envelope" style="color:var(--accent-blue);"></i> Messagerie</h2>
                <div class="panel-actions">
                    <a href="?page=messages&filter=recus" class="btn-action primary <?= ($filter_msg=='recus')?'active':''; ?>">📥 Reçus</a>
                    <a href="?page=messages&filter=envoyes" class="btn-action primary <?= ($filter_msg=='envoyes')?'active':''; ?>">📤 Envoyés</a>
                    <a href="?page=messages&filter=tous" class="btn-action primary <?= ($filter_msg=='tous')?'active':''; ?>">🗂️ Tous</a>
                    <button class="btn btn-primary" onclick="toggleNewMessage()"><i class="fas fa-plus"></i> Nouveau</button>
                </div>
            </div>

            <div id="nouveauMessage" style="display:none; background:linear-gradient(135deg, #f8fafc, #f1f5f9); padding:22px; border-radius:var(--radius); border:1px solid var(--border-color); margin-bottom:20px;">
                <h3 style="margin-bottom: 15px; color: var(--text-primary);"><i class="fas fa-pen"></i> Rédiger un message</h3>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token; ?>">
                    <div class="grid-2">
                        <div class="form-group">
                            <label>Destinataire <span style="color:var(--accent-danger);">*</span></label>
                            <select name="id_destinataire" required>
                                <option value="">-- Sélectionner --</option>
                                <?php foreach($liste_doyens as $doyen): ?>
                                    <option value="<?= $doyen['id_user']; ?>">Doyen <?= htmlspecialchars($doyen['nom'].' '.$doyen['prenom']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Sujet <span style="color:var(--accent-danger);">*</span></label>
                            <input type="text" name="sujet" placeholder="Objet du message" required>
                        </div>
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label>Message <span style="color:var(--accent-danger);">*</span></label>
                            <textarea name="contenu" rows="4" placeholder="Votre message..." required></textarea>
                        </div>
                    </div>
                    <button type="submit" name="envoyer_message" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Envoyer</button>
                    <button type="button" class="btn btn-outline-dark" onclick="toggleNewMessage()">Annuler</button>
                </form>
            </div>

            <form method="GET" class="filter-bar" style="margin-bottom: 18px;">
                <input type="hidden" name="page" value="messages">
                <input type="hidden" name="filter" value="<?= $filter_msg; ?>">
                <div class="form-group" style="flex: 2;">
                    <label>Rechercher</label>
                    <input type="text" name="search_msg" placeholder="Sujet ou contenu..." value="<?= htmlspecialchars($search_msg); ?>">
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Rechercher</button>
                <a href="?page=messages&filter=<?= $filter_msg; ?>" class="btn btn-outline-dark">Réinitialiser</a>
            </form>

            <div class="table-responsive">
                <table>
                    <thead><tr><th>De</th><th>À</th><th>Sujet</th><th>Date</th><th>Statut</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php if (count($messages_list) > 0): ?>
                            <?php foreach($messages_list as $msg):
                                $is_recu = ($msg['id_destinataire'] == $id_user);
                                $lu_class = $msg['lu'] ? 'valide' : 'soumis';
                                $lu_text = $msg['lu'] ? '✅ Lu' : '⏳ Non lu';
                            ?>
                                <tr>
                                    <td><?= htmlspecialchars($msg['expediteur_nom'] . ' ' . $msg['expediteur_prenom']); ?></td>
                                    <td><?= htmlspecialchars($msg['destinataire_nom'] . ' ' . $msg['destinataire_prenom']); ?></td>
                                    <td><strong><?= htmlspecialchars($msg['sujet']); ?></strong></td>
                                    <td><?= date('d/m/Y H:i', strtotime($msg['date_envoi'])); ?></td>
                                    <td><span class="status-badge <?= $lu_class; ?>"><?= $lu_text; ?></span></td>
                                    <td>
                                        <?php if ($is_recu && !$msg['lu']): ?>
                                            <a href="?page=messages&marquer_lu=<?= $msg['id_message']; ?>&filter=<?= $filter_msg; ?>&search_msg=<?= urlencode($search_msg); ?>" class="btn-action success" onclick="return confirm('Marquer ce message comme lu ?')"><i class="fas fa-check"></i></a>
                                        <?php endif; ?>
                                        <button class="btn-action primary" onclick="voirMessage('<?= addslashes($msg['contenu']); ?>')"><i class="fas fa-eye"></i></button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="empty-state"><i class="fas fa-inbox"></i>Aucun message trouvé.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <script>
            function toggleNewMessage() {
                var div = document.getElementById('nouveauMessage');
                div.style.display = div.style.display === 'none' ? 'block' : 'none';
            }
            function voirMessage(contenu) {
                alert('📩 Contenu du message :\n\n' + contenu);
            }
        </script>
        <?php endif; ?>

        <!-- ============================================================
        PAGE : EMPLOI DU TEMPS
        ============================================================ -->
        <?php if ($page == 'emploi_temps'): ?>
        <div class="panel">
            <div class="panel-header"><h2><i class="fas fa-calendar-alt" style="color:var(--accent-indigo);"></i> Mon emploi du temps</h2></div>
            <div class="table-responsive">
                <table>
                    <thead><tr><th>Jour</th><th>Heure</th><th>Cours</th><th>Classe</th><th>Département</th><th>Salle</th></tr></thead>
                    <tbody>
                        <?php foreach($horaires as $h): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($h['jour']); ?></strong></td>
                                <td><?= substr($h['heure_debut'],0,5).' - '.substr($h['heure_fin'],0,5); ?></td>
                                <td><?= htmlspecialchars($h['nom_cours']); ?></td>
                                <td><?= htmlspecialchars($h['niveau']); ?></td>
                                <td><?= htmlspecialchars($h['nom_dept']); ?></td>
                                <td><?= htmlspecialchars($h['salle'] ?: '-'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($horaires)): ?>
                            <tr><td colspan="6" class="empty-state"><i class="fas fa-calendar-times"></i>Aucun horaire programmé.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- ============================================================
        PAGE : NOTIFICATIONS
        ============================================================ -->
        <?php if ($page == 'notifications'): ?>
        <div class="panel">
            <div class="panel-header"><h2><i class="fas fa-bell" style="color:var(--accent-warning);"></i> Notifications</h2></div>
            <?php
            $notifications = $bdd->prepare("SELECT * FROM notifications WHERE source_table IN ('professeur','examen','notes','message') ORDER BY date_creation DESC LIMIT 20");
            $notifications->execute();
            if ($notifications->rowCount() > 0): ?>
                <?php while($n = $notifications->fetch()): ?>
                    <div style="display:flex; justify-content:space-between; align-items:center; padding:14px 0; border-bottom:1px solid var(--border-light);">
                        <div>
                            <div style="font-weight:600; color:var(--text-primary);"><?= htmlspecialchars($n['titre']); ?></div>
                            <div style="font-size:13px; color:var(--text-secondary); margin-top:3px;"><?= htmlspecialchars($n['message']); ?></div>
                            <div style="font-size:11px; color:var(--text-muted); margin-top:4px;"><?= date('d/m/Y H:i', strtotime($n['date_creation'])); ?></div>
                        </div>
                        <span class="status-badge <?= $n['statut'] == 'non_lu' ? 'soumis' : 'valide'; ?>">
                            <?= $n['statut'] == 'non_lu' ? '⏳ Non lu' : '✅ Lu'; ?>
                        </span>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state"><i class="fas fa-bell-slash"></i>Aucune notification.</div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- ============================================================
        PAGE : HISTORIQUE
        ============================================================ -->
        <?php if ($page == 'historique'): ?>
        <div class="panel">
            <div class="panel-header"><h2><i class="fas fa-history" style="color:var(--accent-purple);"></i> Mon historique</h2></div>
            <div class="table-responsive">
                <table>
                    <thead><tr><th>Utilisateur</th><th>Action</th><th>Table</th><th>Date</th></tr></thead>
                    <tbody>
                        <?php
                        $historique = $bdd->prepare("SELECT a.*, u.nom, u.prenom FROM audit_financier a JOIN users u ON a.id_user = u.id_user WHERE a.id_user = ? ORDER BY a.date_action DESC LIMIT 50");
                        $historique->execute([$id_user]);
                        while($h = $historique->fetch()): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($h['nom'].' '.$h['prenom']); ?></strong></td>
                                <td><?= htmlspecialchars($h['action']); ?></td>
                                <td><?= htmlspecialchars($h['table_concernee']); ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($h['date_action'])); ?></td>
                            </tr>
                        <?php endwhile; ?>
                        <?php if ($historique->rowCount() == 0): ?><tr><td colspan="4" class="empty-state"><i class="fas fa-inbox"></i>Aucun historique.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- ============================================================
        PAGE : STATISTIQUES
        ============================================================ -->
        <?php if ($page == 'statistiques'): ?>
        <div class="panel">
            <div class="panel-header"><h2><i class="fas fa-chart-pie" style="color:var(--accent-purple);"></i> Mes statistiques</h2></div>
            <div class="stats-grid">
                <div class="stat-card blue">
                    <div class="stat-top"><div class="stat-icon-wrap"><i class="fas fa-users"></i></div></div>
                    <div class="stat-value"><?= $nbEtudiants; ?></div>
                    <div class="stat-label">Étudiants</div>
                </div>
                <div class="stat-card green">
                    <div class="stat-top"><div class="stat-icon-wrap"><i class="fas fa-book"></i></div></div>
                    <div class="stat-value"><?= $nbCours; ?></div>
                    <div class="stat-label">Cours</div>
                </div>
                <div class="stat-card purple">
                    <div class="stat-top"><div class="stat-icon-wrap"><i class="fas fa-school"></i></div></div>
                    <div class="stat-value"><?= $nbClasses; ?></div>
                    <div class="stat-label">Classes</div>
                </div>
                <div class="stat-card orange">
                    <div class="stat-top"><div class="stat-icon-wrap"><i class="fas fa-paper-plane"></i></div></div>
                    <div class="stat-value"><?= $nbExamensEnvoyes; ?></div>
                    <div class="stat-label">Examens envoyés</div>
                </div>
                <div class="stat-card green">
                    <div class="stat-top"><div class="stat-icon-wrap"><i class="fas fa-thumbs-up"></i></div></div>
                    <div class="stat-value"><?= $nbExamensValides; ?></div>
                    <div class="stat-label">Examens validés</div>
                </div>
                <div class="stat-card red">
                    <div class="stat-top"><div class="stat-icon-wrap"><i class="fas fa-ban"></i></div></div>
                    <div class="stat-value"><?= $nbExamensRejetes; ?></div>
                    <div class="stat-label">Examens rejetés</div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ============================================================
        PAGE : PROFIL
        ============================================================ -->
        <?php if ($page == 'profil'): ?>
        <div class="panel">
            <div class="panel-header"><h2><i class="fas fa-user" style="color:var(--accent-indigo);"></i> Mon profil</h2></div>
            <div style="display:flex; gap:30px; flex-wrap:wrap; align-items:center;">
                <div style="flex:0 0 200px; text-align:center;">
                    <img src="uploads/<?= htmlspecialchars($photo_profil); ?>" style="width:150px; height:150px; border-radius:50%; object-fit:cover; border:4px solid var(--accent-blue); box-shadow:0 8px 24px rgba(59,130,246,0.2);">
                </div>
                <div style="flex:1; min-width:280px;">
                    <div class="grid-2">
                        <div><strong>Nom :</strong> <?= htmlspecialchars($prof['nom']); ?></div>
                        <div><strong>Prénom :</strong> <?= htmlspecialchars($prof['prenom']); ?></div>
                        <div><strong>Email :</strong> <?= htmlspecialchars($prof['email']); ?></div>
                        <div><strong>Téléphone :</strong> <?= htmlspecialchars($prof['telephone'] ?? 'Non renseigné'); ?></div>
                        <div><strong>Spécialité :</strong> <?= htmlspecialchars($prof['specialite'] ?? 'Non renseignée'); ?></div>
                        <div><strong>Diplôme :</strong> <?= htmlspecialchars($prof['diplome'] ?? 'Non renseigné'); ?></div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- FOOTER -->
        <div style="text-align:center; padding:20px 0 10px; color:var(--text-muted); font-size:12px; border-top:1px solid var(--border-color); margin-top:20px;">
            <p>© <?= date('Y'); ?> UniVerse — Espace Enseignant</p>
        </div>

    </div>

    <!-- ================================================================
    JAVASCRIPT GRAPHIQUES
    ================================================================ -->
    <script>
        <?php if ($page == 'dashboard'): ?>
        const ctxDist = document.getElementById('chartDistNotes').getContext('2d');
        new Chart(ctxDist, {
            type: 'bar',
            data: {
                labels: ['<10','10-12','12-14','14-16','16-18','18+'],
                datasets: [{
                    label: 'Étudiants',
                    data: [<?= $dist['moins_10'] ?? 0; ?>, <?= $dist['10_12'] ?? 0; ?>, <?= $dist['12_14'] ?? 0; ?>, <?= $dist['14_16'] ?? 0; ?>, <?= $dist['16_18'] ?? 0; ?>, <?= $dist['18_plus'] ?? 0; ?>],
                    backgroundColor: [
                        'rgba(239,68,68,0.75)', 'rgba(245,158,11,0.75)',
                        'rgba(250,204,21,0.75)', 'rgba(16,185,129,0.75)',
                        'rgba(59,130,246,0.75)', 'rgba(139,92,246,0.75)'
                    ],
                    borderColor: ['#ef4444','#f59e0b','#facc15','#10b981','#3b82f6','#8b5cf6'],
                    borderWidth: 2,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, grid: { color: '#f1f5f9' } }, x: { grid: { display: false } } }
            }
        });

        const ctxReussite = document.getElementById('chartReussite').getContext('2d');
        new Chart(ctxReussite, {
            type: 'doughnut',
            data: {
                labels: ['Réussite', 'Échec'],
                datasets: [{
                    data: [<?= $notesReussite; ?>, <?= $notesEchec; ?>],
                    backgroundColor: ['rgba(16,185,129,0.85)', 'rgba(239,68,68,0.85)'],
                    borderColor: ['#10b981', '#ef4444'],
                    borderWidth: 3
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { padding: 20, font: { size: 12, weight: '600' } } } },
                cutout: '65%'
            }
        });
        <?php endif; ?>
    </script>

</body>
</html>