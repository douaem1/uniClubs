<?php
session_start();

// Simulation session coordinateur pour les tests
$_SESSION['user_id'] = 1;
$_SESSION['email'] = 'coordinateur@uniclub.ma';
$_SESSION['nom'] = 'Alami';
$_SESSION['prenom'] = 'Ahmed';
$_SESSION['role'] = 'coordinateur';

header('Location: pages/dashboard.php');
exit();
?>