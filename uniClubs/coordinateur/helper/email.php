<?php
// Import manuel de PHPMailer (sans Composer)
$basePath = dirname(dirname('C:/xampp/htdocs/TP PHP/uniClubs/uniClubs/coordinateur/helper'));
require_once $basePath . '/organisateur/PHPMailer/src/PHPMailer.php';
require_once $basePath . '/organisateur/PHPMailer/src/SMTP.php';
require_once $basePath . '/organisateur/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../config/email.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailHelper {
    
    /**
     * Envoie un email HTML
     */
    public static function sendEmail($to, $subject, $htmlContent) {
        $mail = new PHPMailer(true);
        
        try {
            // Configuration du serveur SMTP
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USERNAME;
            $mail->Password   = SMTP_PASSWORD;
            $mail->SMTPSecure = SMTP_ENCRYPTION;
            $mail->Port       = SMTP_PORT;
            $mail->CharSet    = 'UTF-8';
            
            // Debug
            $mail->SMTPDebug = SMTP_DEBUG;
            
            // Expéditeur
            $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
            $mail->addReplyTo(MAIL_REPLY_TO, MAIL_FROM_NAME);
            
            // Destinataire
            $mail->addAddress($to);
            
            // Contenu
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlContent;
            $mail->AltBody = strip_tags($htmlContent); // Version texte brut
            
            // Envoi
            $mail->send();
            return true;
            
        } catch (Exception $e) {
            error_log("Erreur d'envoi email : {$mail->ErrorInfo}");
            return false;
        }
    }
    
    /**
     * Template email validation événement
     */
    public static function sendValidationEmail($event) {
        $htmlContent = "
        <!DOCTYPE html>
        <html lang='fr'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f4; }
                .container { max-width: 600px; margin: 20px auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
                .header { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; padding: 40px 30px; text-align: center; }
                .header h1 { margin: 0; font-size: 28px; }
                .content { padding: 40px 30px; }
                .success-badge { background: #d4edda; color: #155724; padding: 20px; border-radius: 8px; text-align: center; margin-bottom: 30px; border: 2px solid #c3e6cb; }
                .success-badge h2 { margin: 0 0 10px 0; font-size: 24px; }
                .event-info { background: #f8f9fa; padding: 25px; border-radius: 8px; margin: 25px 0; border-left: 4px solid #28a745; }
                .event-info h3 { margin-top: 0; color: #28a745; }
                .event-info p { margin: 10px 0; }
                .info-label { font-weight: bold; color: #555; }
                .footer { background: #f8f9fa; padding: 30px; text-align: center; color: #666; font-size: 14px; }
                .footer hr { border: none; border-top: 1px solid #ddd; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🎓 UniClub</h1>
                    <p style='margin: 10px 0 0 0; font-size: 16px;'>Plateforme de Gestion d'Événements</p>
                </div>
                
                <div class='content'>
                    <div class='success-badge'>
                        <h2>✅ Félicitations !</h2>
                        <p style='margin: 0; font-size: 16px;'>Votre événement a été validé avec succès</p>
                    </div>
                  
      
                    <p>Nous avons le plaisir de vous informer que votre événement a été approuvé par notre équipe de coordination.</p>
                    
                    <div class='event-info'>
                        <h3>📅 Détails de votre événement</h3>
                        <p><span class='info-label'>Nom :</span> " . htmlspecialchars($event['NomEnv']) . "</p>
                        <p><span class='info-label'>Date :</span> " . date('l d F Y à H:i', strtotime($event['dateDebut'])) . "</p>
                        <p><span class='info-label'>Lieu :</span> " . htmlspecialchars($event['Lieu'] ?? 'Non défini') . "</p>
                    </div>
                    
                    <p><strong>Prochaine étape :</strong></p>
                    <ul>
                        <li>Les participants peuvent s'inscrire dès maintenant</li>
                    </ul>
                    
                    <p style='margin-top: 30px; color: #666; font-size: 14px;'>
                        <strong>Besoin d'aide ?</strong> N'hésitez pas à contacter notre équipe de coordination.
                    </p>
                </div>
                
                <div class='footer'>
                    <p><strong>Cordialement,</strong><br>L'équipe UniClub</p>
                    <hr>
                    <p>Cet email a été envoyé automatiquement depuis la plateforme UniClub.<br>
                    Merci de ne pas répondre directement à ce message.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        return self::sendEmail(
            $event['EmailOrg'], 
            "✅ Votre événement a été validé - UniClub",
            $htmlContent
        );
    }
    
    /**
     * Template email refus événement
     */
    public static function sendRejectionEmail($event, $raison) {
        $htmlContent = "
        <!DOCTYPE html>
        <html lang='fr'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f4; }
                .container { max-width: 600px; margin: 20px auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
                .header { background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; padding: 40px 30px; text-align: center; }
                .header h1 { margin: 0; font-size: 28px; }
                .content { padding: 40px 30px; }
                .alert-box { background: #f8d7da; color: #721c24; padding: 20px; border-radius: 8px; text-align: center; margin-bottom: 30px; border: 2px solid #f5c6cb; }
                .alert-box h2 { margin: 0 0 10px 0; font-size: 24px; }
                .event-info { background: #f8f9fa; padding: 25px; border-radius: 8px; margin: 25px 0; border-left: 4px solid #dc3545; }
                .event-info h3 { margin-top: 0; color: #dc3545; }
                .event-info p { margin: 10px 0; }
                .rejection-reason { background: #fff3cd; border: 2px solid #ffc107; color: #856404; padding: 20px; border-radius: 8px; margin: 25px 0; }
                .rejection-reason h3 { margin-top: 0; color: #856404; }
                .info-label { font-weight: bold; color: #555; }
                .footer { background: #f8f9fa; padding: 30px; text-align: center; color: #666; font-size: 14px; }
                .footer hr { border: none; border-top: 1px solid #ddd; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🎓 UniClub</h1>
                    <p style='margin: 10px 0 0 0; font-size: 16px;'>Plateforme de Gestion d'Événements</p>
                </div>
                
                <div class='content'>
                    <div class='alert-box'>
                        <h2>❌ Événement refusé</h2>
                        <p style='margin: 0; font-size: 16px;'>Votre demande nécessite des modifications</p>
                    </div>
                       
                    <p>Après examen de votre demande, nous ne pouvons malheureusement pas valider votre événement dans sa forme actuelle.</p>
                    
                    <div class='event-info'>
                        <h3>📅 Événement concerné</h3>
                        <p><span class='info-label'>Nom :</span> " . htmlspecialchars($event['NomEnv']) . "</p>
                        <p><span class='info-label'>Date :</span> " . date('l d F Y à H:i', strtotime($event['dateDebut'])) . "</p>
                        <p><span class='info-label'>Lieu :</span> " . htmlspecialchars($event['Lieu'] ?? 'Non défini') . "</p>
                    </div>
                    
                    <div class='rejection-reason'>
                        <h3>📝 Raison du refus</h3>
                        <p style='white-space: pre-wrap;'>" . nl2br(htmlspecialchars($raison)) . "</p>
                    </div>
                    
                    <p><strong>Que faire maintenant ?</strong></p>
                    <ul>
                        <li>Prenez en compte les remarques ci-dessus</li>
                        <li>Modifiez votre événement en conséquence</li>
                        <li>Soumettez à nouveau votre demande</li>
                    </ul>
                                    
                    <p style='margin-top: 30px; color: #666; font-size: 14px;'>
                        <strong>Besoin d'aide ?</strong> N'hésitez pas à contacter notre équipe de coordination pour plus de précisions.
                    </p>
                </div>
                
                <div class='footer'>
                    <p><strong>Cordialement,</strong><br>L'équipe UniClub</p>
                    <hr>
                    <p>Cet email a été envoyé automatiquement depuis la plateforme UniClub.<br>
                    Merci de ne pas répondre directement à ce message.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        return self::sendEmail(
            $event['EmailOrg'], 
            "❌ Votre événement nécessite des modifications - UniClub",
            $htmlContent
        );
    }
}
?>