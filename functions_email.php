<?php
// ============================================================
// FONCTIONS D'ENVOI D'EMAILS CENTRALISÉES
// ============================================================
require_once __DIR__ . '/config/smtp.php';

/**
 * Envoie un email HTML à un destinataire.
 *
 * @param string $destinataire Adresse email du destinataire
 * @param string $sujet Sujet de l'email
 * @param string $contenu_html Corps HTML de l'email
 * @return bool True si succès, False sinon
 */
function envoyerEmail($destinataire, $sujet, $contenu_html)
{
    if (empty($destinataire) || !filter_var($destinataire, FILTER_VALIDATE_EMAIL)) {
        error_log("[email] Adresse invalide : $destinataire");
        return false;
    }
    try {
        $mail = creerMailerSMTP();
        $mail->addAddress($destinataire);
        $mail->isHTML(true);
        $mail->Subject = $sujet;
        $mail->Body = $contenu_html;
        $mail->AltBody = strip_tags(str_replace('<br>', "\n", $contenu_html));
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("[email] Erreur envoi à $destinataire : " . $e->getMessage());
        return false;
    }
}

/**
 * Template HTML standard pour les emails UniVerse.
 */
function templateEmail($titre, $contenu, $couleur = '#003366')
{
    return '
    <div style="font-family:Arial,sans-serif;max-width:600px;margin:auto;padding:20px;">
        <div style="background:'.$couleur.';color:white;padding:15px;border-radius:8px 8px 0 0;text-align:center;">
            <h2 style="margin:0;">🎓 UniVerse</h2>
        </div>
        <div style="background:#f8fafc;padding:20px;border:1px solid #e2e8f0;border-top:none;border-radius:0 0 8px 8px;">
            <h3 style="color:'.$couleur.';margin-top:0;">'.$titre.'</h3>
            '.$contenu.'
            <hr style="border:none;border-top:1px solid #e2e8f0;margin:20px 0;">
            <p style="font-size:11px;color:#94a3b8;text-align:center;">
                © '.date('Y').' UniVerse — Université du Lac Tanganyika<br>
                Ceci est un message automatique, ne pas répondre.
            </p>
        </div>
    </div>';
}
?>