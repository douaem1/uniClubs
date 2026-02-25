<?php
include '../../ConnDB.php';
require_once '../config/session.php';
require_once '../helper/email.php';

// Import manuel de PHPMailer (sans Composer)
$basePath = dirname(dirname('C:/xampp/htdocs/TP PHP/uniClubs/uniClubs/coordinateur/helper'));
require_once $basePath . '/organisateur/PHPMailer/src/PHPMailer.php';
require_once $basePath . '/organisateur/PHPMailer/src/SMTP.php';
require_once $basePath . '/organisateur/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../config/email.php';
// Import manuel de PHPMailer (sans Composer)

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

//requireCoordinateur();

// Vérifier le token CSRF
if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    setFlashMessage('error', 'Token de sécurité invalide');
    header('Location: ../pages/email.php');
    exit();
}

$action = $_POST['action'] ?? '';

if ($action === 'send') {
    sendEmail();
} else {
    setFlashMessage('error', 'Action invalide');
    header('Location: ../pages/email.php');
    exit();
}

function sendEmail() {
    try {
        // Récupérer les données du formulaire
        $destinataires = $_POST['destinataires'] ?? [];
        $sujet = trim($_POST['sujet'] ?? '');
        $message = trim($_POST['message'] ?? '');
        
        // Validation
        if (empty($destinataires)) {
            throw new Exception("Veuillez sélectionner au moins un destinataire valide");
        }
        
        // Nettoyer et valider les entrées
        $sujet = filter_var($sujet, FILTER_SANITIZE_STRING);
        $message = filter_var($message, FILTER_SANITIZE_STRING);
        
        if (empty($sujet) || empty($message)) {
            throw new Exception("Le sujet et le message sont obligatoires");
        }
        
        // Valider les adresses email
        $validatedEmails = [];
        foreach ($destinataires as $email) {
            $email = filter_var($email, FILTER_SANITIZE_EMAIL);
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $validatedEmails[] = $email;
            }
        }
        
        if (empty($validatedEmails)) {
            throw new Exception("Aucune adresse email valide sélectionnée");
        }
        
        // Gérer la pièce jointe
        $pieceJointe = null;
        if (isset($_FILES['piece_jointe']) && $_FILES['piece_jointe']['error'] === UPLOAD_ERR_OK) {
            $pieceJointe = handleFileUpload($_FILES['piece_jointe']);
        }
        
        // Préparer le contenu HTML de l'email
        $nomExpediteur = $_SESSION['prenom'] . ' ' . $_SESSION['nom'];
        $expediteurEmail = $_SESSION['email'];
        $htmlContent = formatEmailBody($message, $nomExpediteur);
        
        // Envoyer l'email à chaque destinataire
        $succes = 0;
        $echecs = 0;
        $errors = [];
        
        foreach ($destinataires as $dest) {
            try {
                // Créer une nouvelle instance de PHPMailer
                $mail = new PHPMailer(true);
                
                // Configuration SMTP
                $mail->isSMTP();
                $mail->Host       = SMTP_HOST;
                $mail->SMTPAuth   = true;
                $mail->Username   = SMTP_USERNAME;
                $mail->Password   = SMTP_PASSWORD;
                $mail->SMTPSecure = SMTP_ENCRYPTION;
                $mail->Port       = SMTP_PORT;
                $mail->CharSet    = 'UTF-8';
                
                // Expéditeur et destinataire
                $mail->setFrom(MAIL_FROM_EMAIL, $nomExpediteur);
                $mail->addAddress($dest);
                $mail->addReplyTo($expediteurEmail, $nomExpediteur);
                
                // Contenu de l'email
                $mail->isHTML(true);
                $mail->Subject = $sujet;
                $mail->Body    = $htmlContent;
                $mail->AltBody = strip_tags($message);
                
                // Ajouter la pièce jointe si elle existe
                if ($pieceJointe && file_exists($pieceJointe['path'])) {
                    $mail->addAttachment($pieceJointe['path'], $pieceJointe['name']);
                }
                
                // Envoyer l'email
                $emailSent = $mail->send();
                
                if ($emailSent) {
                    $succes++;
                } else {
                    $echecs++;
                    $errors[] = "Échec d'envoi à $dest: " . $mail->ErrorInfo;
                }
            } catch (Exception $e) {
                $echecs++;
                $errors[] = "Erreur avec $dest: " . $e->getMessage();
                error_log("Erreur d'envoi d'email à $dest: " . $e->getMessage());
            }
        }
        
        // Message de résultat
        if ($succes > 0) {
            $msg = "Email envoyé avec succès à $succes organisateur(s)";
            if ($echecs > 0) {
                $msg .= " ($echecs échec(s))";
                // Enregistrer les erreurs dans les logs
                error_log("Erreurs d'envoi d'emails: " . implode(" | ", $errors));
            }
            setFlashMessage('success', $msg);
        } else {
            throw new Exception("Échec de l'envoi de tous les emails. " . implode(" ", $errors));
        }
        
        // Nettoyer le(s) fichier(s) temporaire(s)
        if ($pieceJointe && file_exists($pieceJointe['path'])) {
            @unlink($pieceJointe['path']);
        }
        
    } catch (Exception $e) {
        setFlashMessage('error', $e->getMessage());
    }
    
    header('Location: ../pages/email.php');
    exit();
}

function handleFileUpload($file) {
    // Vérifier les erreurs de téléchargement
    if (!isset($file['error']) || is_array($file['error'])) {
        throw new Exception('Paramètres de fichier invalides');
    }

    // Vérifier les erreurs de téléchargement
    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            throw new Exception('Le fichier est trop volumineux');
        case UPLOAD_ERR_PARTIAL:
            throw new Exception('Le téléchargement du fichier a été interrompu');
        case UPLOAD_ERR_NO_FILE:
            throw new Exception('Aucun fichier n\'a été téléchargé');
        default:
            throw new Exception('Erreur inconnue lors du téléchargement du fichier');
    }

    // Vérifier si le fichier est bien un fichier téléchargé
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new Exception('Fichier non reçu correctement');
    }

    $uploadDir = '../../uploads/temp/';
    
    // Créer le répertoire s'il n'existe pas avec les bonnes permissions
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        throw new RuntimeException(sprintf('Le répertoire "%s" n\'a pas pu être créé', $uploadDir));
    }
    
    // Vérifier que le répertoire est accessible en écriture
    if (!is_writable($uploadDir)) {
        throw new Exception('Le répertoire de destination n\'est pas accessible en écriture');
    }
    
    // Types MIME autorisés avec leurs extensions correspondantes
    $allowedTypes = [
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'image/jpeg' => 'jpg',
        'image/png' => 'png'
    ];
    
    $maxSize = 5 * 1024 * 1024; // 5MB
    
    // Vérifier le type MIME réel du fichier
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    // Vérifier que le type MIME est autorisé
    if (!array_key_exists($mimeType, $allowedTypes)) {
        throw new Exception("Type de fichier non autorisé. Types acceptés : " . 
            implode(', ', array_map(function($ext) { 
                return strtoupper($ext); 
            }, array_unique($allowedTypes))));
    }
    
    // Vérifier la taille du fichier
    if ($file['size'] > $maxSize) {
        $sizeInMB = number_format($maxSize / (1024 * 1024), 2);
        throw new Exception("La taille du fichier ne doit pas dépasser {$sizeInMB} Mo");
    }
    
    // Nettoyer le nom du fichier
    $originalName = basename($file['name']);
    $safeName = preg_replace('/[^a-zA-Z0-9\.\-_]/', '_', $originalName);
    $safeName = substr($safeName, 0, 100); // Limiter la longueur du nom
    
    // Générer un nom de fichier unique avec un préfixe aléatoire
    $extension = $allowedTypes[$mimeType];
    $newFilename = uniqid('attach_' . bin2hex(random_bytes(4)) . '_', true) . '.' . $extension;
    $destination = $uploadDir . $newFilename;
    
    // S'assurer que le nom du fichier est unique
    $counter = 1;
    $pathInfo = pathinfo($destination);
    while (file_exists($destination)) {
        $newFilename = $pathInfo['filename'] . '_' . $counter++ . '.' . $pathInfo['extension'];
        $destination = $pathInfo['dirname'] . DIRECTORY_SEPARATOR . $newFilename;
    }
    
    // Déplacer le fichier téléchargé
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new Exception('Erreur lors du déplacement du fichier téléchargé');
    }
    
    // S'assurer que les permissions sont correctes
    chmod($destination, 0644);
    
    return [
        'name' => $safeName,
        'path' => $destination,
        'type' => $mimeType,
        'size' => $file['size']
    ];
}

function formatEmailBody($message, $expediteur) {
    // Nettoyer et échapper les entrées
    $safeMessage = nl2br(htmlspecialchars($message, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $safeExpediteur = htmlspecialchars($expediteur, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $currentYear = date('Y');
    
    // Inline CSS pour une meilleure compatibilité avec les clients mail
    return "
    <!DOCTYPE html>
    <html lang='fr' xmlns='http://www.w3.org/1999/xhtml' xmlns:v='urn:schemas-microsoft-com:vml' xmlns:o='urn:schemas-microsoft-com:office:office'>
    <head>
        <meta http-equiv='Content-Type' content='text/html; charset=utf-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <meta name='x-apple-disable-message-reformatting'>
        <meta http-equiv='X-UA-Compatible' content='IE=edge'>
        <title>Message de l'Université - UniClubs</title>
        
        <!--[if mso]>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
        <![endif]-->
        
        <style type='text/css'>
            /* Base styles */
            body, html {
                margin: 0 !important;
                padding: 0 !important;
                height: 100% !important;
                width: 100% !important;
                font-family: 'Segoe UI', Helvetica, Arial, sans-serif;
                -webkit-text-size-adjust: 100%;
                -ms-text-size-adjust: 100%;
                -webkit-font-smoothing: antialiased;
            }
            
            /* What it does: Stops email clients resizing small text. */
            * {
                -ms-text-size-adjust: 100%;
                -webkit-text-size-adjust: 100%;
            }
            
            /* What it does: Forces Outlook.com to display emails full width. */
            .ExternalClass {
                width: 100%;
            }
            
            /* What it does: Forces Outlook.com to display line heights normally. */
            .ExternalClass, .ExternalClass p, .ExternalClass span, .ExternalClass font, .ExternalClass td, .ExternalClass div {
                line-height: 100%;
            }
            
            /* Container styles */
            .email-container {
                width: 100%;
                max-width: 600px;
                margin: 0 auto;
                background-color: #ffffff;
                border-radius: 8px;
                overflow: hidden;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            }
            
            /* Header styles */
            .email-header {
                padding: 30px 20px;
                text-align: center;
                background: linear-gradient(135deg, #4a6ee0 0%, #8a2be2 100%);
                color: #ffffff;
            }
            
            .email-header h1 {
                margin: 0 0 10px 0;
                font-size: 24px;
                font-weight: 600;
                line-height: 1.3;
            }
            
            .email-header p {
                margin: 0;
                font-size: 14px;
                opacity: 0.9;
            }
            }
            .content { 
                padding: 30px; 
            }
            .message { 
                background: #ffffff; 
                padding: 25px; 
                border-radius: 8px; 
                margin: 20px 0; 
                line-height: 1.7;
                color: #2d3748;
                font-size: 15px;
            }
            .footer { 
                text-align: center; 
                margin-top: 30px; 
                padding-top: 20px;
                border-top: 1px solid #e2e8f0;
                color: #718096; 
                font-size: 13px; 
            }
            .footer p {
                margin: 5px 0;
            }
            .signature {
                margin: 25px 0 15px;
                color: #4a5568;
            }
            @media only screen and (max-width: 600px) {
                .container {
                    margin: 0;
                    border-radius: 0;
                }
                .header, .content {
                    padding: 20px;
                }
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🎓 UniClub</h1>
                <p>Plateforme de gestion des clubs et événements</p>
            </div>
            <div class='content'>
                <div class='message'>
                    " . nl2br(htmlspecialchars($message)) . "
                    <div class='signature'>
                        <p>Cordialement,<br><strong>" . htmlspecialchars($expediteur) . "</strong><br>Coordinateur UniClub</p>
                    </div>
                </div>
                <div class='footer'>
                    <p>Cet email a été envoyé automatiquement depuis la plateforme UniClub.</p>
                    <p>© " . date('Y') . " UniClub. Tous droits réservés.</p>
                </div>
            </div>
        </div>
    </body>
    </html>
    ";
}
?>