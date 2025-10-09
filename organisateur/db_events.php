<?php
$host = 'localhost';
$dbname = 'uniclubs';
$user = 'root';       // ou ton utilisateur
$pass = '';           // ton mot de passe

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}
?>
