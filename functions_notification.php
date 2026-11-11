<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/vendor/autoload.php';

/**
 * Déclenche une notification (SQL + email).
 * 
 * @param PDO    $bdd
 * @param string $table  Table source
 * @param string $action Action (INSERT, UPDATE, DELETE…)
 * @param string $titre  Titre de la notification
 * @param string $message Contenu
 * @return bool
 */
function declencherNotification($bdd, $table, $action, $titre, $message) {
    try {
        $stmt = $bdd->prepare("INSERT INTO notifications (source_table, type_evenement, titre, message) VALUES (?, ?, ?, ?)");
        $stmt->execute([$table, $action, $titre, $message]);
    } catch (Exception $e) {
        error_log("[functions_notification] Erreur insert SQL : " . $e->getMessage());
        return false;
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.example.com'; // À configurer
        $mail->SMTPAuth   = true;
        $mail->Username   = 'votre_adresse@email.com';
        $mail->Password   = 'votre_mot_de_passe';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->setFrom('systeme@universite.bi', 'UniVerse Alerte');

        $mail->addAddress('admin@universite.bi', 'Admin UniVerse');

        $mail->isHTML(true);
        $mail->Subject = "[UniVerse Alerte] " . $titre;
        $mail->Body    = "<h3>Événement sur la table : " . htmlspecialchars($table) . " (" . htmlspecialchars($action) . ")</h3><p>" . htmlspecialchars($message) . "</p>";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer n'a pas pu envoyer l'alerte : " . $mail->ErrorInfo);
        return false;
    }
}