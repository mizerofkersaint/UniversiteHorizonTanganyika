<?php
require_once __DIR__ . '/db.php';
// ============================================================
// DÉCONNEXION — UniVerse
// ============================================================
// Ce fichier termine proprement la session de l'utilisateur :
//   1. Met à jour user_sessions (si une session y est enregistrée)
//   2. Vide les variables de session
//   3. Détruit la session PHP
//   4. Supprime le cookie de session
//   5. Redirige vers Accueil.php
// ============================================================

// 1. Démarrer la session SEULEMENT si nécessaire
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Récupérer l'identifiant utilisateur AVANT de vider la session
$id_user = isset($_SESSION['id_user']) ? (int) $_SESSION['id_user'] : null;

// 3. Mettre à jour user_sessions si l'utilisateur est identifié
if ($id_user !== null && $id_user > 0) {
    try {
        $bdd = getDBConnection();
        $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Mettre à jour UNIQUEMENT les sessions encore marquées 'Connecte' pour cet utilisateur.
        // Si aucune ligne ne correspond, aucune erreur n'est levée.
        $stmt = $bdd->prepare("
            UPDATE user_sessions
            SET statut = 'Deconnecte', derniere_activite = NOW()
            WHERE id_user = ? AND statut = 'Connecte'
        ");
        $stmt->execute([$id_user]);
    } catch (Exception $e) {
        // On journalise mais on ne bloque PAS la déconnexion
        error_log("[logout] Erreur mise à jour user_sessions : " . $e->getMessage());
    }
}

// 4. Vider les variables de session
$_SESSION = [];

// 5. Supprimer le cookie de session (si utilisé)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// 6. Détruire la session PHP
session_destroy();

// 7. Rediriger vers la page d'accueil
header("Location: Accueil.php");
exit();