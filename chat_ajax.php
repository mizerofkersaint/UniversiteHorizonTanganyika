<?php
require_once __DIR__ . '/db.php';
require_once 'auth.php';
header('Content-Type: application/json');

try {
    $bdd = getDBConnection();
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ============================================================
// ENVOYER UN MESSAGE
// ============================================================
if ($action === 'send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $to = (int)($_POST['to'] ?? 0);
    $message = trim($_POST['message'] ?? '');
    
    if ($to <= 0 || $message === '') {
        echo json_encode(['success' => false, 'error' => 'Données invalides']);
        exit;
    }
    
    try {
        $ins = $bdd->prepare("INSERT INTO chat_messages (id_expediteur, id_destinataire, message) VALUES (?, ?, ?)");
        $ins->execute([$USER_ID, $to, $message]);
        echo json_encode(['success' => true, 'id' => $bdd->lastInsertId()]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ============================================================
// RÉCUPÉRER LES NOUVEAUX MESSAGES
// ============================================================
if ($action === 'fetch') {
    $to = (int)($_GET['to'] ?? 0);
    $last = (int)($_GET['last'] ?? 0);
    
    if ($to <= 0) {
        echo json_encode(['success' => false]);
        exit;
    }
    
    $stmt = $bdd->prepare("
        SELECT * FROM chat_messages 
        WHERE ((id_expediteur = ? AND id_destinataire = ?) 
            OR (id_expediteur = ? AND id_destinataire = ?))
            AND id_message > ?
        ORDER BY date_envoi ASC
    ");
    $stmt->execute([$USER_ID, $to, $to, $USER_ID, $last]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Marquer les messages reçus comme lus
    $bdd->prepare("UPDATE chat_messages SET lu = 1 WHERE id_expediteur = ? AND id_destinataire = ?")
        ->execute([$to, $USER_ID]);
    
    // Formater la réponse
    $formatted = array_map(function($m) {
        return [
            'id_message' => $m['id_message'],
            'id_expediteur' => $m['id_expediteur'],
            'message' => htmlspecialchars($m['message']),
            'heure' => date('H:i', strtotime($m['date_envoi']))
        ];
    }, $messages);
    
    echo json_encode(['success' => true, 'messages' => $formatted]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Action inconnue']);
?>