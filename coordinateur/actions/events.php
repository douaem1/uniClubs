<?php
require_once '../../config/database.php';
require_once '../../config/session.php';

requireCoordinateur();

$db = Database::getInstance()->getConnection();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get':
            getEvent($db);
            break;
        case 'update':
            updateEvent($db);
            break;
        case 'cancel':
            cancelEvent($db);
            break;
        case 'delete':
            deleteEvent($db);
            break;
        default:
            throw new Exception("Action invalide");
    }
} catch (Exception $e) {
    if ($action === 'get') {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    } else {
        setFlashMessage('error', $e->getMessage());
        header('Location: ../pages/evenements.php');
    }
    exit();
}

function getEvent($db) {
    $idEvent = intval($_GET['id'] ?? 0);
    
    if ($idEvent === 0) {
        throw new Exception("ID invalide");
    }
    
    $stmt = $db->prepare("
        SELECT e.*, 
               c.NomClub,
               o.NomOrg, o.PrenomOrg, o.EmailOrg,
               (SELECT COUNT(*) FROM inscri WHERE Idenv = e.Idenv) as nb_inscrits,
               (SELECT COUNT(*) FROM inscri WHERE Idenv = e.Idenv AND present = 1) as nb_presents
        FROM Evenement e
        LEFT JOIN Club c ON e.idClub = c.idClub
        LEFT JOIN Organisateur o ON e.EmailOrg = o.EmailOrg
        WHERE e.Idenv = ?
    ");
    $stmt->execute([$idEvent]);
    $event = $stmt->fetch();
    
    if (!$event) {
        throw new Exception("Événement introuvable");
    }
    
    // Récupérer la liste des participants
    $stmt = $db->prepare("
        SELECT p.*, i.dateIcription, i.present
        FROM inscri i
        JOIN Participant p ON i.EmailParti = p.EmailParti
        WHERE i.Idenv = ?
        ORDER BY i.dateIcription DESC
    ");
    $stmt->execute([$idEvent]);
    $participants = $stmt->fetchAll();
    
    $event['participants'] = $participants;
    
    header('Content-Type: application/json');
    echo json_encode($event);
    exit();
}

function updateEvent($db) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        throw new Exception("Token de sécurité invalide");
    }
    
    $idEvent = intval($_POST['idEvent']);
    $nomEnv = trim($_POST['nomEnv']);
    $description = trim($_POST['description'] ?? '');
    $dateDebut = $_POST['dateDebut'];
    $dateFin = $_POST['dateFin'] ?? null;
    $lieu = trim($_POST['lieu'] ?? '');
    $capacite = $_POST['capacite'] ? intval($_POST['capacite']) : null;
    $statut = $_POST['statut'];
    
    // Vérifier si l'événement existe
    $stmt = $db->prepare("SELECT NomEnv FROM Evenement WHERE Idenv = ?");
    $stmt->execute([$idEvent]);
    if (!$stmt->fetch()) {
        throw new Exception("Événement introuvable");
    }
    
    // Valider les dates
    if ($dateFin && $dateFin < $dateDebut) {
        throw new Exception("La date de fin ne peut pas être antérieure à la date de début");
    }
    
    $stmt = $db->prepare("
        UPDATE Evenement 
        SET NomEnv = ?, discription = ?, dateDebut = ?, dateFin = ?, 
            Lieu = ?, Capacite = ?, Statut = ?
        WHERE Idenv = ?
    ");
    $stmt->execute([$nomEnv, $description, $dateDebut, $dateFin, $lieu, $capacite, $statut, $idEvent]);
    
    setFlashMessage('success', "Événement \"$nomEnv\" modifié avec succès");
    header('Location: ../pages/evenements.php');
    exit();
}

function cancelEvent($db) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        throw new Exception("Token de sécurité invalide");
    }
    
    $idEvent = intval($_POST['idEvent'] ?? 0);
    
    if ($idEvent === 0) {
        throw new Exception("ID invalide");
    }
    
    // Récupérer l'événement
    $stmt = $db->prepare("SELECT NomEnv, Statut FROM Evenement WHERE Idenv = ?");
    $stmt->execute([$idEvent]);
    $event = $stmt->fetch();
    
    if (!$event) {
        throw new Exception("Événement introuvable");
    }
    
    if ($event['Statut'] === 'annulé') {
        throw new Exception("Cet événement est déjà annulé");
    }
    
    if ($event['Statut'] === 'terminé') {
        throw new Exception("Impossible d'annuler un événement terminé");
    }
    
    // Annuler l'événement
    $stmt = $db->prepare("UPDATE Evenement SET Statut = 'annulé' WHERE Idenv = ?");
    $stmt->execute([$idEvent]);
    
    setFlashMessage('success', "Événement \"{$event['NomEnv']}\" annulé avec succès");
    header('Location: ../pages/evenements.php');
    exit();
}

function deleteEvent($db) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        throw new Exception("Token de sécurité invalide");
    }
    
    $idEvent = intval($_POST['idEvent'] ?? 0);
    
    if ($idEvent === 0) {
        throw new Exception("ID invalide");
    }
    
    // Récupérer l'événement
    $stmt = $db->prepare("SELECT NomEnv FROM Evenement WHERE Idenv = ?");
    $stmt->execute([$idEvent]);
    $event = $stmt->fetch();
    
    if (!$event) {
        throw new Exception("Événement introuvable");
    }
    
    // Supprimer l'événement (les inscriptions seront supprimées en cascade)
    $stmt = $db->prepare("DELETE FROM Evenement WHERE Idenv = ?");
    $stmt->execute([$idEvent]);
    
    setFlashMessage('success', "Événement \"{$event['NomEnv']}\" supprimé avec succès");
    header('Location: ../pages/evenements.php');
    exit();
}
?>