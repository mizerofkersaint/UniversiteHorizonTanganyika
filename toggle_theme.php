<?php
// ============================================================
// toggle_theme.php — Bascule entre mode clair et mode sombre
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_POST['theme'])) {
    $_SESSION['theme'] = ($_POST['theme'] === 'dark') ? 'dark' : 'light';
}

// Redirection vers la page précédente (ou par défaut)
$redirect = $_SERVER['HTTP_REFERER'] ?? 'administrateur.php';
header('Location: ' . $redirect);
exit;
?>