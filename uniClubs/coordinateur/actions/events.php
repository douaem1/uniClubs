<?php
include '../../ConnDB.php';
require_once '../config/session.php';
require_once '../helper/email.php';

//requireCoordinateur();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get':
            getEvent($conn);
            break;
        case 'search':
            searchEvents($conn);
            break;
        case 'update':
            updateEvent($conn);
            break;
        case 'cancel':
            cancelEvent($conn);
            break;
        case 'delete':
            deleteEvent($conn);
            break;
        case 'validate':
            validateEvent($conn);
            break;
        case 'reject':
            rejectEvent($conn);
            break;
        default:
            throw new Exception("Action invalide");
    }
} catch (Exception $e) {
    if ($action === 'get' || $action === 'search') {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    } else {
        setFlashMessage('error', $e->getMessage());
        header('Location: ../pages/events.php');
    }
    exit();
}

function getEvent($conn) {
    $idEvent = intval($_GET['id'] ?? 0);
    
    if ($idEvent === 0) {
        throw new Exception("ID invalide");
    }
    
    // Requête principale
    $query = "
        SELECT e.*, 
               c.NomClub,
               p.Nom as NomOrg, p.Prenom as PrenomOrg, co.Email as EmailOrg,
               (SELECT COUNT(*) FROM inscri WHERE Idenv = e.Idenv) as nb_inscrits,
               (SELECT COUNT(*) FROM inscri WHERE Idenv = e.Idenv AND present = 1) as nb_presents
        FROM Evenement e
        LEFT JOIN Club c ON e.idClub = c.idClub
        LEFT JOIN Organisateur o ON e.idOrganisateur = o.idCompte
        LEFT JOIN Compte co ON o.idCompte = co.idCompte
        LEFT JOIN Participant p ON o.idCompte = p.idCompte
        WHERE e.Idenv = ?
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $idEvent);
    $stmt->execute();
    $result = $stmt->get_result();
    $event = $result->fetch_assoc();
    
    if (!$event) {
        throw new Exception("Événement introuvable");
    }
    
    // Récupérer la liste des participants
    $query2 = "
        SELECT p.*, i.dateIcription, i.present
        FROM inscri i
        JOIN Participant p ON i.idParticipant = p.idCompte
        WHERE i.Idenv = ?
        ORDER BY i.dateIcription DESC
    ";
    
    $stmt2 = $conn->prepare($query2);
    $stmt2->bind_param("i", $idEvent);
    $stmt2->execute();
    $result2 = $stmt2->get_result();
    
    $participants = [];
    while ($row = $result2->fetch_assoc()) {
        $participants[] = $row;
    }
    
    $event['participants'] = $participants;
    
    header('Content-Type: application/json');
    echo json_encode($event);
    exit();
}

function updateEvent($conn) {
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
    $stmt = $conn->prepare("SELECT NomEnv FROM Evenement WHERE Idenv = ?");
    $stmt->bind_param("i", $idEvent);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$result->fetch_assoc()) {
        throw new Exception("Événement introuvable");
    }
    
    // Valider les dates
    if ($dateFin && $dateFin < $dateDebut) {
        throw new Exception("La date de fin ne peut pas être antérieure à la date de début");
    }
    
    // Mise à jour
    $query = "
        UPDATE Evenement 
        SET NomEnv = ?, discription = ?, dateDebut = ?, dateFin = ?, 
            Lieu = ?, Capacite = ?, Statut = ?
        WHERE Idenv = ?
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sssssisi", $nomEnv, $description, $dateDebut, $dateFin, $lieu, $capacite, $statut, $idEvent);
    $stmt->execute();
    
    setFlashMessage('success', "Événement \"$nomEnv\" modifié avec succès");
    header('Location: ../pages/events.php');
    exit();
}

function cancelEvent($conn) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        throw new Exception("Token de sécurité invalide");
    }
    
    $idEvent = intval($_POST['idEvent'] ?? 0);
    
    if ($idEvent === 0) {
        throw new Exception("ID invalide");
    }
    
    // Récupérer l'événement
    $stmt = $conn->prepare("SELECT NomEnv, Statut FROM Evenement WHERE Idenv = ?");
    $stmt->bind_param("i", $idEvent);
    $stmt->execute();
    $result = $stmt->get_result();
    $event = $result->fetch_assoc();
    
    if (!$event) {
        throw new Exception("Événement introuvable");
    }
    
    if ($event['Statut'] === 'saturé') {
        throw new Exception("Cet événement est déjà saturé");
    }
    
    // Marquer comme saturé
    $stmt = $conn->prepare("UPDATE Evenement SET Statut = 'saturé' WHERE Idenv = ?");
    $stmt->bind_param("i", $idEvent);
    $stmt->execute();
    
    setFlashMessage('success', "Événement \"{$event['NomEnv']}\" annulé avec succès");
    header('Location: ../pages/events.php');
    exit();
}

function deleteEvent($conn) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        throw new Exception("Token de sécurité invalide");
    }
    
    $idEvent = intval($_POST['idEvent'] ?? 0);
    
    if ($idEvent === 0) {
        throw new Exception("ID invalide");
    }
    
    // Récupérer l'événement
    $stmt = $conn->prepare("SELECT NomEnv FROM Evenement WHERE Idenv = ?");
    $stmt->bind_param("i", $idEvent);
    $stmt->execute();
    $result = $stmt->get_result();
    $event = $result->fetch_assoc();
    
    if (!$event) {
        throw new Exception("Événement introuvable");
    }
    
    // Supprimer l'événement
    $stmt = $conn->prepare("DELETE FROM Evenement WHERE Idenv = ?");
    $stmt->bind_param("i", $idEvent);
    $stmt->execute();
    
    setFlashMessage('success', "Événement \"{$event['NomEnv']}\" supprimé avec succès");
    header('Location: ../pages/events.php');
    exit();
}

function validateEvent($conn) {
    $idEvent = intval($_GET['id'] ?? 0);
    $redirect = $_GET['redirect'] ?? '';
    
    if ($idEvent === 0) {
        throw new Exception("ID invalide");
    }
    
    // Récupérer l'événement avec les infos de l'organisateur
    $query = "
        SELECT e.NomEnv, e.Statut, e.validation, e.etat, e.dateDebut, e.Lieu,
               p.Nom as NomOrg, p.Prenom as PrenomOrg, co.Email as EmailOrg
        FROM Evenement e
        LEFT JOIN Organisateur o ON e.idOrganisateur = o.idCompte
        LEFT JOIN Compte co ON o.idCompte = co.idCompte
        LEFT JOIN Participant p ON o.idCompte = p.idCompte
        WHERE e.Idenv = ?
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $idEvent);
    $stmt->execute();
    $result = $stmt->get_result();
    $event = $result->fetch_assoc();
    
    if (!$event) {
        throw new Exception("Événement introuvable");
    }
    
    if ($event['Statut'] === 'saturé') {
        throw new Exception("Cet événement est déjà saturé");
    }
    
    // Valider l'événement
    $validationStatus = 'validé';
    $stmt = $conn->prepare("UPDATE Evenement SET validation = ? WHERE Idenv = ?");
    $stmt->bind_param("si", $validationStatus, $idEvent);
    $stmt->execute();

    // Envoyer email de validation à l'organisateur
    $emailSent = false;
    if (!empty($event['EmailOrg'])) {
        $emailSent = EmailHelper::sendValidationEmail($event);
    }

    if ($emailSent) {
        setFlashMessage('success', "Événement \"{$event['NomEnv']}\" validé avec succès. ✅ Email envoyé à l'organisateur.");
    } else {
        setFlashMessage('warning', "Événement \"{$event['NomEnv']}\" validé, mais l'email n'a pas pu être envoyé. ⚠️");
    }
    
    if ($redirect === 'dashboard') {
        header('Location: ../pages/dashboard.php');
    } else {
        header('Location: ../pages/events.php');
    }
    exit();
}

function rejectEvent($conn) {
    $idEvent = intval($_GET['id'] ?? ($_POST['id'] ?? 0));
    $raison = trim($_POST['raison'] ?? '');
    $redirect = $_GET['redirect'] ?? ($_POST['redirect'] ?? '');
    
    if ($idEvent === 0) {
        throw new Exception("ID invalide");
    }
    
    if (empty($raison)) {
        throw new Exception("Veuillez fournir une raison pour le refus");
    }
    
    // Récupérer l'événement avec les infos de l'organisateur
    $query = "
        SELECT e.NomEnv, e.Statut, e.validation, e.etat, e.dateDebut, e.Lieu,
               p.Nom as NomOrg, p.Prenom as PrenomOrg, co.Email as EmailOrg
        FROM Evenement e
        LEFT JOIN Organisateur o ON e.idOrganisateur = o.idCompte
        LEFT JOIN Compte co ON o.idCompte = co.idCompte
        LEFT JOIN Participant p ON o.idCompte = p.idCompte
        WHERE e.Idenv = ?
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $idEvent);
    $stmt->execute();
    $result = $stmt->get_result();
    $event = $result->fetch_assoc();
    
    if (!$event) {
        throw new Exception("Événement introuvable");
    }
    
    if ($event['Statut'] === 'saturé') {
        throw new Exception("Cet événement est déjà saturé");
    }
    
    // Refuser l'événement
    $validationStatus = 'refusé';
    $stmt = $conn->prepare("UPDATE Evenement SET validation = ? WHERE Idenv = ?");
    $stmt->bind_param("si", $validationStatus, $idEvent);
    $stmt->execute();

    // Envoyer email de refus à l'organisateur
    $emailSent = false;
    if (!empty($event['EmailOrg'])) {
        $emailSent = EmailHelper::sendRejectionEmail($event, $raison);
    }

    if ($emailSent) {
        setFlashMessage('success', "Événement \"{$event['NomEnv']}\" refusé avec succès. ✅ Email envoyé à l'organisateur.");
    } else {
        setFlashMessage('warning', "Événement \"{$event['NomEnv']}\" refusé, mais l'email n'a pas pu être envoyé. ⚠️");
    }
    
    if ($redirect === 'dashboard') {
        header('Location: ../pages/dashboard.php');
    } else {
        header('Location: ../pages/events.php');
    }
    exit();
}

function searchEvents($conn) {
    // Récupérer les paramètres de recherche
    $filtre_statut = $_GET['statut'] ?? 'tous';
    $filtre_club = $_GET['club'] ?? 'tous';
    $filtre_recherche = $_GET['search'] ?? '';
    $filtre_date_debut = $_GET['date_debut'] ?? '';
    $filtre_date_fin = $_GET['date_fin'] ?? '';
    
    // Construction de la requête avec filtres
    $sql = "
        SELECT e.*, 
               c.NomClub,
               p.Nom as NomOrg, p.Prenom as PrenomOrg,
               (SELECT COUNT(*) FROM inscri WHERE Idenv = e.Idenv) as nb_inscrits
        FROM Evenement e
        LEFT JOIN Club c ON e.idClub = c.idClub
        LEFT JOIN Organisateur o ON e.idOrganisateur = o.idCompte
        LEFT JOIN Compte co ON o.idCompte = co.idCompte
        LEFT JOIN Participant p ON o.idCompte = p.idCompte
        WHERE 1=1
    ";
    
    $types = '';
    $params = [];
    
    if ($filtre_statut !== 'tous') {
        $sql .= " AND e.Statut = ?";
        $types .= 's';
        $params[] = $filtre_statut;
    }
    
    if ($filtre_club !== 'tous') {
        $sql .= " AND e.idClub = ?";
        $types .= 'i';
        $params[] = intval($filtre_club);
    }
    
    if (!empty($filtre_recherche)) {
        $sql .= " AND (e.NomEnv LIKE ? OR e.Lieu LIKE ?)";
        $types .= 'ss';
        $searchTerm = "%$filtre_recherche%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    if (!empty($filtre_date_debut)) {
        $sql .= " AND e.dateDebut >= ?";
        $types .= 's';
        $params[] = $filtre_date_debut;
    }
    
    if (!empty($filtre_date_fin)) {
        $sql .= " AND e.dateFin <= ?";
        $types .= 's';
        $params[] = $filtre_date_fin;
    }
    
    $sql .= " ORDER BY e.dateDebut DESC";
    
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $evenements = [];
    while ($row = $result->fetch_assoc()) {
        $evenements[] = $row;
    }
    $stmt->close();
    
    header('Content-Type: application/json');
    echo json_encode($evenements);
    exit();
}
?>