<?php
require_once __DIR__ . '/db.php';
require_once 'auth.php';

try {
    $bdd = getDBConnection();
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Erreur : " . $e->getMessage());
}

// Récupérer la liste des utilisateurs avec qui discuter (tous sauf soi-même)
$stmt = $bdd->prepare("
    SELECT u.id_user, u.nom, u.prenom, u.role, u.photo, u.statut,
           (SELECT COUNT(*) FROM chat_messages c WHERE c.id_expediteur = u.id_user AND c.id_destinataire = ? AND c.lu = 0) AS non_lus
    FROM users u
    WHERE u.id_user != ? AND u.statut = 'Actif'
    ORDER BY u.nom
");
$stmt->execute([$USER_ID, $USER_ID]);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Définir le destinataire sélectionné
$destinataire_id = isset($_GET['to']) ? (int)$_GET['to'] : (count($users) > 0 ? $users[0]['id_user'] : 0);
$destinataire = null;
foreach ($users as $u) {
    if ($u['id_user'] == $destinataire_id) {
        $destinataire = $u;
        break;
    }
}

// Marquer les messages comme lus
if ($destinataire_id > 0) {
    $upd = $bdd->prepare("UPDATE chat_messages SET lu = 1 WHERE id_expediteur = ? AND id_destinataire = ?");
    $upd->execute([$destinataire_id, $USER_ID]);
}

// Récupérer les messages de la conversation
$messages = [];
if ($destinataire_id > 0) {
    $stmt = $bdd->prepare("
        SELECT * FROM chat_messages 
        WHERE (id_expediteur = ? AND id_destinataire = ?) 
           OR (id_expediteur = ? AND id_destinataire = ?)
        ORDER BY date_envoi ASC
        LIMIT 200
    ");
    $stmt->execute([$USER_ID, $destinataire_id, $destinataire_id, $USER_ID]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$dashboards = [
    'Administrateur' => 'administrateur.php', 'Recteur' => 'dashboard_recteur.php',
    'Doyen' => 'dashboard_doyen.php', 'Comptable' => 'dashboard_comptable.php',
    'Professeur' => 'dashboard_professeur.php', 'Etudiant' => 'dashboard_etudiant.php'
];
$dashboard_url = $dashboards[$USER_ROLE] ?? 'Accueil.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Chat - UniVerse</title>
    <link rel="stylesheet" href="assets/theme.css">
    <script src="assets/theme.js"></script>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI',Arial,sans-serif; }
        body { background:#eef3f8; padding:20px; height:100vh; overflow:hidden; }
        .chat-container { max-width:1300px; margin:0 auto; height:calc(100vh - 40px); display:grid; grid-template-columns:300px 1fr; gap:15px; }
        .sidebar-users { background:white; border-radius:12px; box-shadow:0 4px 10px rgba(0,0,0,0.1); overflow-y:auto; padding:15px; }
        .sidebar-users h2 { color:#003366; margin-bottom:15px; font-size:16px; border-bottom:2px solid #f1f5f9; padding-bottom:10px; }
        .user-item { padding:12px; border-radius:8px; display:flex; align-items:center; gap:10px; text-decoration:none; color:#334155; margin-bottom:5px; transition:0.2s; border-left:3px solid transparent; }
        .user-item:hover { background:#f8fafc; }
        .user-item.active { background:#eff6ff; border-left-color:#003366; }
        .user-avatar { width:38px; height:38px; border-radius:50%; object-fit:cover; background:#e2e8f0; }
        .user-info { flex:1; }
        .user-name { font-weight:600; font-size:13px; }
        .user-role { font-size:11px; color:#64748b; text-transform:uppercase; }
        .badge-unread { background:#dc2626; color:white; font-size:10px; padding:2px 8px; border-radius:10px; font-weight:700; }
        
        .chat-panel { background:white; border-radius:12px; box-shadow:0 4px 10px rgba(0,0,0,0.1); display:flex; flex-direction:column; overflow:hidden; }
        .chat-header { padding:15px 20px; background:#003366; color:white; display:flex; align-items:center; gap:10px; }
        .chat-header h1 { font-size:16px; }
        .chat-header .back-btn { background:rgba(255,255,255,0.2); color:white; text-decoration:none; padding:6px 12px; border-radius:6px; font-size:12px; margin-right:10px; }
        .chat-header .theme-toggle-btn { margin-left:auto; background:rgba(255,255,255,0.2); border:none; color:white; width:36px; height:36px; border-radius:50%; cursor:pointer; font-size:16px; }
        
        .messages-area { flex:1; overflow-y:auto; padding:20px; background:#f8fafc; display:flex; flex-direction:column; gap:10px; }
        .message { max-width:70%; padding:10px 15px; border-radius:15px; font-size:14px; line-height:1.4; position:relative; word-wrap:break-word; }
        .message .time { display:block; font-size:10px; margin-top:4px; opacity:0.7; }
        .message.sent { background:#003366; color:white; align-self:flex-end; border-bottom-right-radius:3px; }
        .message.received { background:white; color:#1e293b; align-self:flex-start; border-bottom-left-radius:3px; border:1px solid #e2e8f0; }
        .empty-chat { text-align:center; padding:40px; color:#94a3b8; margin:auto; }
        
        .input-area { padding:15px; background:white; border-top:1px solid #e2e8f0; display:flex; gap:10px; }
        .input-area input { flex:1; padding:12px 15px; border:1px solid #d1d5db; border-radius:25px; font-size:14px; outline:none; }
        .input-area input:focus { border-color:#003366; }
        .input-area button { background:#003366; color:white; border:none; border-radius:50%; width:45px; height:45px; cursor:pointer; font-size:18px; display:flex; align-items:center; justify-content:center; }
        .input-area button:hover { background:#0055aa; }
    </style>
</head>
<body>
<div class="chat-container">
    
    <!-- SIDEBAR USERS -->
    <div class="sidebar-users">
        <h2>💬 Conversations</h2>
        <?php if (count($users) > 0): ?>
            <?php foreach ($users as $u): ?>
                <a href="chat.php?to=<?= $u['id_user'] ?>" class="user-item <?= ($u['id_user'] == $destinataire_id) ? 'active' : '' ?>">
                    <?php $img = (!empty($u['photo']) && file_exists("uploads/".$u['photo'])) ? $u['photo'] : 'default.png'; ?>
                    <img src="uploads/<?= htmlspecialchars($img) ?>" class="user-avatar">
                    <div class="user-info">
                        <div class="user-name"><?= htmlspecialchars($u['prenom'] . ' ' . $u['nom']) ?></div>
                        <div class="user-role"><?= htmlspecialchars($u['role']) ?></div>
                    </div>
                    <?php if ($u['non_lus'] > 0): ?>
                        <span class="badge-unread"><?= $u['non_lus'] ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="color:#94a3b8; text-align:center; padding:20px;">Aucun autre utilisateur.</p>
        <?php endif; ?>
    </div>

    <!-- CHAT PANEL -->
    <div class="chat-panel">
        <?php if ($destinataire): ?>
            <div class="chat-header">
                <a href="<?= $dashboard_url ?>" class="back-btn">← Retour</a>
                <img src="uploads/<?= htmlspecialchars((!empty($destinataire['photo']) && file_exists("uploads/".$destinataire['photo'])) ? $destinataire['photo'] : 'default.png') ?>" class="user-avatar" style="width:32px;height:32px;">
                <h1><?= htmlspecialchars($destinataire['prenom'] . ' ' . $destinataire['nom']) ?> <small style="opacity:0.7;font-weight:400;">(<?= htmlspecialchars($destinataire['role']) ?>)</small></h1>
                <button class="theme-toggle-btn" onclick="toggleTheme()">🌙</button>
            </div>

            <div class="messages-area" id="messagesArea">
                <?php if (count($messages) > 0): ?>
                    <?php foreach ($messages as $msg): ?>
                        <?php $is_sent = ($msg['id_expediteur'] == $USER_ID); ?>
                        <div class="message <?= $is_sent ? 'sent' : 'received' ?>">
                            <?= nl2br(htmlspecialchars($msg['message'])) ?>
                            <span class="time"><?= date('H:i', strtotime($msg['date_envoi'])) ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-chat">💬 Commencez la conversation avec <?= htmlspecialchars($destinataire['prenom']) ?> !</div>
                <?php endif; ?>
            </div>

            <div class="input-area">
                <input type="text" id="messageInput" placeholder="Écrivez un message..." onkeypress="if(event.key==='Enter') envoyerMessage()">
                <button onclick="envoyerMessage()">➤</button>
            </div>
        <?php else: ?>
            <div class="chat-header">
                <a href="<?= $dashboard_url ?>" class="back-btn">← Retour</a>
                <h1>💬 Chat</h1>
                <button class="theme-toggle-btn" onclick="toggleTheme()">🌙</button>
            </div>
            <div class="empty-chat" style="flex:1;display:flex;align-items:center;justify-content:center;">
                <div>
                    <h2 style="color:#003366;margin-bottom:10px;">Aucune conversation</h2>
                    <p>Sélectionnez un utilisateur à gauche pour commencer.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($destinataire): ?>
<script>
const USER_ID = <?= $USER_ID ?>;
const DESTINATAIRE_ID = <?= $destinataire_id ?>;
const messagesArea = document.getElementById('messagesArea');

// Scroll automatique en bas au chargement
function scrollToBottom() {
    messagesArea.scrollTop = messagesArea.scrollHeight;
}
scrollToBottom();

// Envoyer un message
function envoyerMessage() {
    const input = document.getElementById('messageInput');
    const message = input.value.trim();
    if (message === '') return;

    fetch('chat_ajax.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=send&to=' + DESTINATAIRE_ID + '&message=' + encodeURIComponent(message)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            input.value = '';
            chargerNouveauxMessages();
        }
    });
}

// Charger les nouveaux messages (toutes les 3 secondes)
let dernierMessageId = <?= count($messages) > 0 ? max(array_column($messages, 'id_message')) : 0 ?>;

function chargerNouveauxMessages() {
    fetch('chat_ajax.php?action=fetch&to=' + DESTINATAIRE_ID + '&last=' + dernierMessageId)
    .then(res => res.json())
    .then(data => {
        if (data.success && data.messages.length > 0) {
            data.messages.forEach(msg => {
                const div = document.createElement('div');
                div.className = 'message ' + (msg.id_expediteur == USER_ID ? 'sent' : 'received');
                div.innerHTML = msg.message.replace(/\n/g, '<br>') + 
                    '<span class="time">' + msg.heure + '</span>';
                messagesArea.appendChild(div);
                dernierMessageId = Math.max(dernierMessageId, parseInt(msg.id_message));
            });
            scrollToBottom();
        }
    });
}

setInterval(chargerNouveauxMessages, 3000);
</script>
<?php endif; ?>
</body>
</html>