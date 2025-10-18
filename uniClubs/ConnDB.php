<?php
    $db_host = 'localhost';
    $db_user = 'root';
    $db_pass = '';
    $db_name = 'uniclubs';

    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

    if ($conn->connect_error) {
        die("Connexion échouée : " . $conn->connect_error);
    }

    // Pour forcer l’encodage UTF-8 (important pour les accents et les autre caractères spéciaux)
    $conn->set_charset('utf8mb4');
?>