<?php
require_once '../../config/database.php';
require_once '../../config/session.php';

requireCoordinateur();

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
        $sujet = trim($_POST['sujet']);
        $message = trim($_POST['message']);
        
        // Validation
        if (empty($destinataires)) {
            throw new Exception("Veuillez sélectionner au moins un destinataire");
        }
        
        if (empty($sujet) || empty($message)) {
            throw new Exception("Le sujet et le message sont obligatoires");
        }
        
        // Gérer la pièce jointe
        $pieceJointe = null;
        if (isset($_FILES['piece_jointe']) && $_FILES['piece_jointe']['error'] === UPLOAD_ERR_OK) {
            $pieceJointe = handleFileUpload($_FILES['piece_jointe']);
        }
        
        // Préparer l'email
        $expediteur = $_SESSION['email'];
        $nomExpediteur = $_SESSION['prenom'] . ' ' . $_SESSION['nom'];
        
        // En-têtes de l'email
        $headers = "From: $nomExpediteur <$expediteur>\r\n";
        $headers .= "Reply-To: $expediteur\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        
        if ($pieceJointe) {
            // Email avec pièce jointe
            $boundary = md5(time());
            $headers .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";
            
            $emailBody = "--$boundary\r\n";
            $emailBody .= "Content-Type: text/html; charset=UTF-8\r\n";
            $emailBody .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
            $emailBody .= formatEmailBody($message, $nomExpediteur);
            $emailBody .= "\r\n\r\n";
            
            // Ajouter la pièce jointe
            $emailBody .= "--$boundary\r\n";
            $emailBody .= "Content-Type: application/octet-stream; name=\"{$pieceJointe['name']}\"\r\n";
            $emailBody .= "Content-Transfer-Encoding: base64\r\n";
            $emailBody .= "Content-Disposition: attachment; filename=\"{$pieceJointe['name']}\"\r\n\r\n";
            $emailBody .= chunk_split(base64_encode(file_get_contents($pieceJointe['path'])));
            $emailBody .= "\r\n--$boundary--";
        } else {
            // Email simple HTML
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $emailBody = formatEmailBody($message, $nomExpediteur);
        }
        
        // Envoyer l'email à chaque destinataire
        $succes = 0;
        $echecs = 0;
        
        foreach ($destinataires as $dest) {
            if (mail($dest, $sujet, $emailBody, $headers)) {
                $succes++;
            } else {
                $echecs++;
            }
        }
        
        // Message de résultat
        if ($succes > 0) {
            $msg = "Email envoyé avec succès à $succes organisateur(s)";
            if ($echecs > 0) {
                $msg .= " ($echecs échec(s))";
            }
            setFlashMessage('success', $msg);
        } else {
            throw new Exception("Échec de l'envoi de l'email");
        }
        
        // Nettoyer le fichier temporaire
        if ($pieceJointe && file_exists($pieceJointe['path'])) {
            unlink($pieceJointe['path']);
        }
        
    } catch (Exception $e) {
        setFlashMessage('error', $e->getMessage());
    }
    
    header('Location: ../pages/email.php');
    exit();
}

function handleFileUpload($file) {
    $uploadDir = '../../uploads/temp/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $allowedTypes = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'image/jpeg',
        'image/png'
    ];
    $maxSize = 5 * 1024 * 1024; // 5MB
    
    // Vérifier le type MIME
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
     finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        throw new Exception("Type de fichier non autorisé");
    }
    
    if ($file['size'] > $maxSize) {
        throw new Exception("Fichier trop volumineux (max 5MB)");
    }
    
    $filename = uniqid('attachment_') . '_' . basename($file['name']);
    $destination = $uploadDir . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new Exception("Erreur lors de l'upload du fichier");
    }
    
    return [
        'path' => $destination,
        'name' => basename($file['name'])
    ];
}

function formatEmailBody($message, $expediteur) {
    return "
    <!DOCTYPE html>
    <html lang='fr'>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
                     color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
            .message { background: white; padding: 20px; border-radius: 5px; margin: 20px 0; }
            .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
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
                </div>
                <div class='footer'>
                    <p>Cordialement,<br><strong>$expediteur</strong><br>Coordinateur UniClub</p>
                    <hr style='border: none; border-top: 1px solid #ddd; margin: 20px 0;'>
                    <p>Cet email a été envoyé automatiquement depuis la plateforme UniClub.</p>
                </div>
            </div>
        </div>
    </body>
    </html>
    ";
}
?>