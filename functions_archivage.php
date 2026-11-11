<?php
// ============================================================
// FONCTIONS D'ARCHIVAGE CENTRALISÉ - UniVerse
// Version complète et vérifiée (avec restaurerArchive)
// ⚠️ NE PAS METTRE DE  À LA FIN
// ============================================================

// ------------------------------------------------------------
// Constantes internes
// ------------------------------------------------------------
if (!defined('ARCHIVAGE_TABLES_AUTORISEES')) {
    define('ARCHIVAGE_TABLES_AUTORISEES', [
        'users'                => 'id_user',
        'departements'         => 'id_dept',
        'classes'              => 'id_classe',
        'cours'                => 'id_cours',
        'etudiants'            => 'id_etudiant',
        'professeurs'          => 'id_prof',
        'inscriptions'         => 'id_inscription',
        'notes'                => 'id_note',
        'paiements'            => 'id_paiement',
        'examens'              => 'id_examen',
        'horaires'             => 'id_horaire',
        'presences'            => 'id_presence',
        'notifications'        => 'id_notification',
        'annonces_professeurs' => 'id_annonce',
        'messages'             => 'id_message',
        'messages_professeurs' => 'id_message',
        'evenements_calendrier'=> 'id_evenement',
        'supports_cours'       => 'id_support',
        'historique_paiements' => 'id_historique',
        'audit_financier'      => 'id_audit',
        'archivage'            => 'id_archivage',
    ]);
}

if (!defined('ARCHIVAGE_ACTIONS_AUTORISEES')) {
    define('ARCHIVAGE_ACTIONS_AUTORISEES', ['INSERT', 'UPDATE', 'DELETE', 'SELECT']);
}

if (!defined('ARCHIVAGE_ID_USER_FALLBACK_INTERDIT')) {
    define('ARCHIVAGE_ID_USER_FALLBACK_INTERDIT', true);
}

// ============================================================
// 1. RÉCUPÉRATION DE L'UTILISATEUR EN SESSION
// ============================================================
function getUserIdEnSession($bdd = null) {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }

    if (!($bdd instanceof PDO)) {
        return null;
    }

    $candidats = [];
    if (isset($_SESSION['id_user'])) {
        $candidats[] = $_SESSION['id_user'];
    }
    if (isset($_SESSION['user_id'])) {
        $candidats[] = $_SESSION['user_id'];
    }

    foreach ($candidats as $candidat) {
        $id = (int) $candidat;
        if ($id <= 0) {
            continue;
        }

        try {
            $stmt = $bdd->prepare("SELECT id_user FROM users WHERE id_user = ? LIMIT 1");
            $stmt->execute([$id]);
            if ($stmt->fetchColumn()) {
                return $id;
            }
        } catch (Exception $e) {
            error_log("[archivage] Erreur vérification utilisateur session : " . $e->getMessage());
            return null;
        }
    }

    return null;
}

// ============================================================
// 2. RÉCUPÉRATION DES DONNÉES D'UN ENREGISTREMENT
// ============================================================
function getDonneesEnregistrement($bdd, $table, $id) {
    if (!($bdd instanceof PDO)) {
        return null;
    }
    if (!isset(ARCHIVAGE_TABLES_AUTORISEES[$table])) {
        error_log("[archivage] Table non autorisée pour getDonneesEnregistrement : " . $table);
        return null;
    }

    $clePrimaire = ARCHIVAGE_TABLES_AUTORISEES[$table];
    $id = (int) $id;
    if ($id <= 0) {
        return null;
    }

    try {
        $sql = "SELECT * FROM `{$table}` WHERE `{$clePrimaire}` = ? LIMIT 1";
        $stmt = $bdd->prepare($sql);
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (Exception $e) {
        error_log("[archivage] Erreur getDonneesEnregistrement({$table}, {$id}) : " . $e->getMessage());
        return null;
    }
}

// ============================================================
// 3. FONCTION CENTRALE D'ARCHIVAGE
// ============================================================
function archiverAction(
    $bdd,
    $id_user,
    $action,
    $table,
    $id_concerned = null,
    $ancienne_valeur = null,
    $nouvelle_valeur = null,
    $description = null
) {
    if (!($bdd instanceof PDO)) {
        error_log("[archivage] archiverAction : connexion PDO invalide.");
        return false;
    }

    $action = strtoupper(trim((string) $action));
    if (!in_array($action, ARCHIVAGE_ACTIONS_AUTORISEES, true)) {
        error_log("[archivage] Action non autorisée : " . $action);
        return false;
    }

    $id_user_resolu = null;

    if ($id_user !== null) {
        $id_user_cast = (int) $id_user;
        if ($id_user_cast > 0) {
            $id_user_resolu = $id_user_cast;
        }
    }

    if ($id_user_resolu === null) {
        $id_user_resolu = getUserIdEnSession($bdd);
    }

    $nom_utilisateur    = null;
    $prenom_utilisateur = null;
    $role_utilisateur   = null;

    if ($id_user_resolu !== null) {
        try {
            $stmt = $bdd->prepare("SELECT nom, prenom, role FROM users WHERE id_user = ? LIMIT 1");
            $stmt->execute([$id_user_resolu]);
            $u = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($u) {
                $nom_utilisateur    = $u['nom'];
                $prenom_utilisateur = $u['prenom'];
                $role_utilisateur   = $u['role'];
            } else {
                $id_user_resolu     = null;
                $nom_utilisateur    = null;
                $prenom_utilisateur = null;
                $role_utilisateur   = null;
            }
        } catch (Exception $e) {
            error_log("[archivage] Erreur vérification utilisateur : " . $e->getMessage());
            $id_user_resolu     = null;
            $nom_utilisateur    = null;
            $prenom_utilisateur = null;
            $role_utilisateur   = null;
        }
    }

    $ancienne_json = _archivage_preparer_json($ancienne_valeur);
    $nouvelle_json = _archivage_preparer_json($nouvelle_valeur);

    $adresse_ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    if (is_string($user_agent) && strlen($user_agent) > 255) {
        $user_agent = substr($user_agent, 0, 255);
    }

    try {
        $stmt = $bdd->prepare("
            INSERT INTO archivage
                (id_user, nom_utilisateur, prenom_utilisateur, role_utilisateur,
                 action, table_concernee, id_concerned,
                 ancienne_valeur, nouvelle_valeur,
                 description, adresse_ip, user_agent, date_action)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $id_user_resolu,
            $nom_utilisateur,
            $prenom_utilisateur,
            $role_utilisateur,
            $action,
            (string) $table,
            $id_concerned !== null ? (int) $id_concerned : null,
            $ancienne_json,
            $nouvelle_json,
            $description,
            $adresse_ip,
            $user_agent,
        ]);
        return (int) $bdd->lastInsertId();
    } catch (Exception $e) {
        error_log("[archivage] Échec de l'insertion : " . $e->getMessage());
        return false;
    }
}

// ============================================================
// 3bis. HELPER JSON
// ============================================================
function _archivage_preparer_json($valeur) {
    if ($valeur === null) {
        return null;
    }
    if (is_array($valeur) || is_object($valeur)) {
        $json = json_encode($valeur, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            error_log("[archivage] Impossible d'encoder la valeur en JSON : " . json_last_error_msg());
            return null;
        }
        return $json;
    }
    if (is_string($valeur)) {
        $trim = trim($valeur);
        if ($trim !== '' && ($trim[0] === '{' || $trim[0] === '[')) {
            json_decode($trim);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $valeur;
            }
        }
        return $valeur;
    }
    return (string) $valeur;
}

// ============================================================
// 4. HISTORIQUE / STATISTIQUES / NETTOYAGE
// ============================================================
function getHistoriqueArchivage($bdd, $filtres = [], $page = 1, $limite = 20) {
    $resultat = [
        'total' => 0,
        'page'  => max(1, (int) $page),
        'limite'=> max(1, (int) $limite),
        'pages' => 1,
        'data'  => [],
    ];
    if (!($bdd instanceof PDO)) {
        return $resultat;
    }

    $where  = [];
    $params = [];

    if (!empty($filtres['id_user'])) {
        $where[]  = "a.id_user = ?";
        $params[] = (int) $filtres['id_user'];
    }
    if (!empty($filtres['action'])) {
        $where[]  = "a.action = ?";
        $params[] = strtoupper(trim($filtres['action']));
    }
    if (!empty($filtres['table'])) {
        $where[]  = "a.table_concernee = ?";
        $params[] = trim($filtres['table']);
    }
    if (!empty($filtres['date_debut'])) {
        $where[]  = "a.date_action >= ?";
        $params[] = $filtres['date_debut'] . ' 00:00:00';
    }
    if (!empty($filtres['date_fin'])) {
        $where[]  = "a.date_action <= ?";
        $params[] = $filtres['date_fin'] . ' 23:59:59';
    }
    if (!empty($filtres['recherche'])) {
        $search   = '%' . $filtres['recherche'] . '%';
        $where[]  = "(a.nom_utilisateur LIKE ? OR a.prenom_utilisateur LIKE ?
                      OR a.table_concernee LIKE ? OR a.description LIKE ?)";
        $params[] = $search;
        $params[] = $search;
        $params[] = $search;
        $params[] = $search;
    }

    $sqlWhere = $where ? (" WHERE " . implode(" AND ", $where)) : "";

    try {
        $stmt = $bdd->prepare("SELECT COUNT(*) FROM archivage a{$sqlWhere}");
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        $limite = max(1, (int) $limite);
        $page   = max(1, (int) $page);
        $debut  = ($page - 1) * $limite;

        $sql = "SELECT a.*, u.nom AS user_nom, u.prenom AS user_prenom, u.role AS user_role
                FROM archivage a
                LEFT JOIN users u ON a.id_user = u.id_user
                {$sqlWhere}
                ORDER BY a.date_action DESC
                LIMIT " . (int) $limite . " OFFSET " . (int) $debut;

        $stmt = $bdd->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $resultat['total']  = $total;
        $resultat['page']   = $page;
        $resultat['limite'] = $limite;
        $resultat['pages']  = max(1, (int) ceil($total / $limite));
        $resultat['data']   = $data;
    } catch (Exception $e) {
        error_log("[archivage] Erreur getHistoriqueArchivage : " . $e->getMessage());
    }

    return $resultat;
}

function getStatsArchivage($bdd) {
    $stats = [
        'total_actions'   => 0,
        'actions'         => [],
        'tables'          => [],
        'utilisateurs'    => [],
        'activite_7_jours'=> [],
    ];
    if (!($bdd instanceof PDO)) {
        return $stats;
    }
    try {
        $stats['total_actions'] = (int) $bdd->query("SELECT COUNT(*) FROM archivage")->fetchColumn();

        $stmt = $bdd->query("SELECT action, COUNT(*) AS nb FROM archivage GROUP BY action ORDER BY nb DESC");
        $stats['actions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $bdd->query("SELECT table_concernee, COUNT(*) AS nb FROM archivage GROUP BY table_concernee ORDER BY nb DESC LIMIT 10");
        $stats['tables'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $bdd->query("
            SELECT u.nom, u.prenom, u.role, COUNT(a.id_archivage) AS nb_actions
            FROM archivage a
            JOIN users u ON a.id_user = u.id_user
            GROUP BY a.id_user
            ORDER BY nb_actions DESC
            LIMIT 10
        ");
        $stats['utilisateurs'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $bdd->query("
            SELECT DATE(date_action) AS jour, COUNT(*) AS nb
            FROM archivage
            WHERE date_action >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY DATE(date_action)
            ORDER BY jour ASC
        ");
        $stats['activite_7_jours'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("[archivage] Erreur getStatsArchivage : " . $e->getMessage());
    }
    return $stats;
}

function nettoyerArchives($bdd, $jours = 365) {
    if (!($bdd instanceof PDO)) {
        return 0;
    }
    $jours = max(1, (int) $jours);
    try {
        $stmt = $bdd->prepare("DELETE FROM archivage WHERE date_action < DATE_SUB(NOW(), INTERVAL ? DAY)");
        $stmt->execute([$jours]);
        return (int) $stmt->rowCount();
    } catch (Exception $e) {
        error_log("[archivage] Erreur nettoyerArchives : " . $e->getMessage());
        return 0;
    }
}

// ============================================================
// 5. RESTAURATION D'UNE ARCHIVE
// ============================================================
function restaurerArchive($bdd, $id_archivage, $id_user_qui_restaure = null) {
    if (!($bdd instanceof PDO)) {
        return ['success' => false, 'message' => "Connexion PDO invalide."];
    }

    $id_archivage = (int) $id_archivage;
    if ($id_archivage <= 0) {
        return ['success' => false, 'message' => "ID d'archive invalide."];
    }

    /* ---------- 1. Charger la ligne d'archive ---------- */
    try {
        $stmt = $bdd->prepare("SELECT * FROM archivage WHERE id_archivage = ? LIMIT 1");
        $stmt->execute([$id_archivage]);
        $archive = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return ['success' => false, 'message' => "Erreur chargement archive : " . $e->getMessage()];
    }

    if (!$archive) {
        return ['success' => false, 'message' => "Archive #$id_archivage introuvable."];
    }

    /* ---------- 2. Vérifier la table (whitelist) ---------- */
    $tables_restaurables = [
        'users'                => 'id_user',
        'departements'         => 'id_dept',
        'classes'              => 'id_classe',
        'cours'                => 'id_cours',
        'etudiants'            => 'id_etudiant',
        'professeurs'          => 'id_prof',
        'inscriptions'         => 'id_inscription',
        'notes'                => 'id_note',
        'paiements'            => 'id_paiement',
        'examens'              => 'id_examen',
        'horaires'             => 'id_horaire',
        'notifications'        => 'id_notification',
        'annonces_professeurs' => 'id_annonce',
        'messages'             => 'id_message',
        'evenements_calendrier'=> 'id_evenement',
        'supports_cours'       => 'id_support',
    ];

    $table = $archive['table_concernee'];
    if (!isset($tables_restaurables[$table])) {
        return ['success' => false, 'message' => "La table « " . htmlspecialchars($table) . " » n'est pas restaurable."];
    }
    $id_key = $tables_restaurables[$table];

    $action_origine = strtoupper($archive['action'] ?? '');
    $id_concerned   = $archive['id_concerned'];

    /* ---------- 3. Déterminer le type de restauration ---------- */
    $restore_type = '';
    $log_action   = '';
    $data_to_use  = null;

    if ($action_origine === 'DELETE') {
        $restore_type = 'reinsert';
        $log_action   = 'INSERT';
        $data_to_use  = json_decode($archive['ancienne_valeur'] ?? '', true);
    } elseif ($action_origine === 'UPDATE') {
        $restore_type = 'revert';
        $log_action   = 'UPDATE';
        $data_to_use  = json_decode($archive['ancienne_valeur'] ?? '', true);
    } elseif ($action_origine === 'INSERT') {
        $restore_type = 'delete';
        $log_action   = 'DELETE';
        $data_to_use  = json_decode($archive['nouvelle_valeur'] ?? '', true);
    } else {
        return ['success' => false, 'message' => "Action « $action_origine » non restaurable."];
    }

    if (empty($data_to_use) || !is_array($data_to_use)) {
        return ['success' => false, 'message' => "Données JSON invalides dans l'archive."];
    }

    /* ---------- 4. Exécuter la restauration ---------- */
    try {
        $bdd->beginTransaction();

        if ($restore_type === 'reinsert') {
            $id_value = $data_to_use[$id_key] ?? null;

            if ($id_value !== null) {
                $check = $bdd->prepare("SELECT COUNT(*) FROM `$table` WHERE `$id_key` = ?");
                $check->execute([$id_value]);
                if ((int)$check->fetchColumn() > 0) {
                    $bdd->rollBack();
                    return ['success' => false, 'message' => "L'enregistrement #$id_value existe déjà dans « $table »."];
                }
            }

            if ($table === 'users' && empty($data_to_use['mot_de_passe'])) {
                $data_to_use['mot_de_passe'] = password_hash(bin2hex(random_bytes(8)), PASSWORD_BCRYPT);
                $data_to_use['premiere_connexion'] = 'Oui';
            }

            $columns      = array_keys($data_to_use);
            $placeholders = array_fill(0, count($columns), '?');
            $sql = "INSERT INTO `$table` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $placeholders) . ")";
            $stmt = $bdd->prepare($sql);
            $stmt->execute(array_values($data_to_use));

        } elseif ($restore_type === 'revert') {
            if ($id_concerned !== null) {
                $check = $bdd->prepare("SELECT COUNT(*) FROM `$table` WHERE `$id_key` = ?");
                $check->execute([$id_concerned]);
                if ((int)$check->fetchColumn() === 0) {
                    $bdd->rollBack();
                    return ['success' => false, 'message' => "L'enregistrement #$id_concerned n'existe plus dans « $table »."];
                }
            }

            $columns = array_keys($data_to_use);
            $sets    = [];
            $params  = [];
            foreach ($columns as $col) {
                if ($col === $id_key) continue;
                $sets[]   = "`$col` = ?";
                $params[] = $data_to_use[$col];
            }

            if (empty($sets)) {
                $bdd->rollBack();
                return ['success' => false, 'message' => "Aucune colonne à restaurer."];
            }

            $params[] = $id_concerned;
            $sql = "UPDATE `$table` SET " . implode(', ', $sets) . " WHERE `$id_key` = ?";
            $stmt = $bdd->prepare($sql);
            $stmt->execute($params);

        } elseif ($restore_type === 'delete') {
            if ($id_concerned === null) {
                $id_concerned = $data_to_use[$id_key] ?? null;
            }

            if ($id_concerned === null) {
                $bdd->rollBack();
                return ['success' => false, 'message' => "ID d'enregistrement introuvable."];
            }

            $stmt = $bdd->prepare("DELETE FROM `$table` WHERE `$id_key` = ?");
            $stmt->execute([$id_concerned]);
        }

        $bdd->commit();
    } catch (Exception $e) {
        if ($bdd->inTransaction()) $bdd->rollBack();
        error_log("[restaurerArchive] Erreur : " . $e->getMessage());
        return ['success' => false, 'message' => "Erreur BDD : " . $e->getMessage()];
    }

    /* ---------- 5. Journaliser l'action de restauration ---------- */
    try {
        archiverAction(
            $bdd,
            $id_user_qui_restaure,
            $log_action,
            $table,
            $id_concerned,
            null,
            null,
            '↩️ RESTAURATION depuis archive #' . $id_archivage . ' (action d\'origine : ' . $action_origine . ')'
        );
    } catch (Exception $e) {
        error_log("[restaurerArchive] Erreur archivage : " . $e->getMessage());
    }

    /* ---------- 6. Message utilisateur ---------- */
    $messages = [
        'reinsert' => "Enregistrement supprimé ré-ajouté dans « $table » (ID #$id_concerned).",
        'revert'   => "Enregistrement #$id_concerned de « $table » restauré à sa valeur antérieure.",
        'delete'   => "Enregistrement #$id_concerned de « $table » supprimé (annulation d'une création).",
    ];

    return [
        'success' => true,
        'message' => '✅ ' . $messages[$restore_type],
        'type'    => $restore_type,
    ];
}

// ✅ FIN DU FICHIER — NE PAS METTRE DE ?> ICI