<?php
/**
 * Configuration Email PHPMailer
 * Modifiez ces paramètres selon votre fournisseur SMTP
 */

// Configuration SMTP (exemple avec Gmail)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587); // 587 pour TLS, 465 pour SSL
define('SMTP_USERNAME', 'raissouni.aya@etu.uae.ac.ma'); // Votre adresse email
define('SMTP_PASSWORD', 'lloo pclr qbnq yrva'); // Mot de passe d'application
define('SMTP_ENCRYPTION', 'tls'); // 'tls' ou 'ssl'

// Informations de l'expéditeur
define('MAIL_FROM_EMAIL', 'noreply@uniclub.com');
define('MAIL_FROM_NAME', 'UniClub');
define('MAIL_REPLY_TO', 'coordinateur@uniclub.com');

// Debug (0 = désactivé, 1 = erreurs, 2 = messages, 3 = connexion, 4 = tout)
define('SMTP_DEBUG', 0);

?>