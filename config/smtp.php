<?php

declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$autoloadPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

if (!is_file($autoloadPath)) {
    throw new RuntimeException(
        'PHPMailer est introuvable. Vérifiez que le dossier vendor existe et que Composer a installé PHPMailer.'
    );
}

require_once $autoloadPath;

/**
 * Crée et configure un objet PHPMailer pour Gmail SMTP.
 *
 * IMPORTANT :
 * - Remplace les valeurs d'exemple par tes véritables informations.
 * - Utilise un mot de passe d'application Google, jamais ton mot de passe Gmail habituel.
 * - Pour un projet publié, conserve les identifiants SMTP hors du dossier public et du dépôt Git.
 */
function creerMailerSMTP(): PHPMailer
{
    $emailExpediteur = getenv('SMTP_USERNAME') ?: '';
    $motDePasseApplication = getenv('SMTP_PASSWORD') ?: '';

    if ($emailExpediteur === '' || $motDePasseApplication === '') {
        throw new RuntimeException(
            'Configuration SMTP absente. Définissez SMTP_USERNAME et SMTP_PASSWORD avant l’envoi.'
        );
    }

    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = $emailExpediteur;
    $mail->Password = $motDePasseApplication;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;

    $mail->CharSet = PHPMailer::CHARSET_UTF8;
    $mail->setFrom($emailExpediteur, 'Gestionuniversitaire');

    return $mail;
}
?>
