<?php
session_start();

// Fonction pour vérifier si l'utilisateur est connecté
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

// Fonction pour vérifier si l'utilisateur est coordinateur
function isCoordinateur() {
    return isLoggedIn() && $_SESSION['role'] === 'coordinateur';
}

// Fonction pour rediriger si non autorisé
function requireCoordinateur() {
    if (!isCoordinateur()) {
        header('Location: ../login.php');
        exit();
    }
}

// Fonction pour obtenir les infos de l'utilisateur connecté
function getCurrentUser() {
    if (isLoggedIn()) {
        return [
            'id' => $_SESSION['user_id'],
            'email' => $_SESSION['email'],
            'nom' => $_SESSION['nom'] ?? '',
            'prenom' => $_SESSION['prenom'] ?? '',
            'role' => $_SESSION['role']
        ];
    }
    return null;
}

// Protection CSRF
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Gestion des messages flash
function setFlashMessage($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type, // 'success', 'error', 'warning', 'info'
        'message' => $message
    ];
}

function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    return null;
}
?>