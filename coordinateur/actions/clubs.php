<?php
require_once '../../config/database.php';
require_once '../../config/session.php';

requireCoordinateur();

$db = Database::getInstance()->getConnection();

// Vérifier le token CSRF
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    setFlashMessage('error', 'Token de sécurité invalide');
    header('Location: ../pages/clubs.php');
    exit();
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'create':
            createClub($db);
            break;
        case 'update':
            updateClub($db);
            break;
        case 'delete':
            deleteClub($db);
            break;
        case 'get':
            getClub($db);
            break;
        default:
            throw new Exception("Action invalide");
    }
} catch (Exception $e) {
    setFlashMessage('error', $e->getMessage());
    header('Location: ../pages/clubs.php');
    exit();
}

function createClub($db) {
    $nomClub = trim($_POST['nomClub']);
    $description = trim($_POST['description'] ?? '');
    $emailOrg = $_POST['emailOrg'];
    
    // Vérifier si le club existe déjà
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM Club WHERE NomClub = ?");
    $stmt->execute([$nomClub]);
    if ($stmt->fetch()['count'] > 0) {
        throw new Exception("Un club avec ce nom existe déjà");
    }
    
    // Gérer l'upload de photo
    $photoclub = null;
    if (isset($_FILES['photoclub']) && $_FILES['photoclub']['error'] === UPLOAD_ERR_OK) {
        $photoclub = uploadPhoto($_FILES['photoclub']);
    }
    
    // Récupérer l'email du coordinateur connecté
    $emailCord = $_SESSION['email'];
    
    $stmt = $db->prepare("
        INSERT INTO Club (NomClub, description, photoclub, EmailCord, EmailOrg)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$nomClub, $description, $photoclub, $emailCord, $emailOrg]);
    
    setFlashMessage('success', "Club \"$nomClub\" créé avec succès");
    header('Location: ../pages/clubs.php');
    exit();
}

function updateClub($db) {
    $idClub = intval($_POST['idClub']);
    $nomClub = trim($_POST['nomClub']);
    $description = trim($_POST['description'] ?? '');
    $emailOrg = $_POST['emailOrg'];
    
    // Vérifier si le club existe
    $stmt = $db->prepare("SELECT * FROM Club WHERE idClub = ?");
    $stmt->execute([$idClub]);
    $club = $stmt->fetch();
    
    if (!$club) {
        throw new Exception("Club introuvable");
    }
    
    // Gérer l'upload de photo
    $photoclub = $club['photoclub'];
    if (isset($_FILES['photoclub']) && $_FILES['photoclub']['error'] === UPLOAD_ERR_OK) {
        // Supprimer l'ancienne photo si elle existe
        if ($photoclub && file_exists("../../uploads/clubs/$photoclub")) {
            unlink("../../uploads/clubs/$photoclub");
        }
        $photoclub = uploadPhoto($_FILES['photoclub']);
    }
    
    $stmt = $db->prepare("
        UPDATE Club 
        SET NomClub = ?, description = ?, photoclub = ?, EmailOrg = ?
        WHERE idClub = ?
    ");
    $stmt->execute([$nomClub, $description, $photoclub, $emailOrg, $idClub]);
    
    setFlashMessage('success', "Club \"$nomClub\" modifié avec succès");
    header('Location: ../pages/clubs.php');
    exit();
}

function deleteClub($db) {
    $idClub = intval($_POST['idClub'] ?? $_GET['id'] ?? 0);
    
    if ($idClub === 0) {
        throw new Exception("ID de club invalide");
    }
    
    // Récupérer les infos du club
    $stmt = $db->prepare("SELECT NomClub, photoclub FROM Club WHERE idClub = ?");
    $stmt->execute([$idClub]);
    $club = $stmt->fetch();
    
    if (!$club) {
        throw new Exception("Club introuvable");
    }
    
    // Vérifier si le club a des événements
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM Evenement WHERE idClub = ?");
    $stmt->execute([$idClub]);
    $count = $stmt->fetch()['count'];
    
    if ($count > 0) {
        throw new Exception("Impossible de supprimer ce club car il a $count événement(s) associé(s)");
    }
    
    // Supprimer la photo si elle existe
    if ($club['photoclub'] && file_exists("../../uploads/clubs/{$club['photoclub']}")) {
        unlink("../../uploads/clubs/{$club['photoclub']}");
    }
    
    $stmt = $db->prepare("DELETE FROM Club WHERE idClub = ?");
    $stmt->execute([$idClub]);
    
    setFlashMessage('success', "Club \"{$club['NomClub']}\" supprimé avec succès");
    header('Location: ../pages/clubs.php');
    exit();
}

function getClub($db) {
    $idClub = intval($_GET['id'] ?? 0);
    
    if ($idClub === 0) {
        http_response_code(400);
        echo json_encode(['error' => 'ID invalide']);
        exit();
    }
    
    $stmt = $db->prepare("SELECT * FROM Club WHERE idClub = ?");
    $stmt->execute([$idClub]);
    $club = $stmt->fetch();
    
    if (!$club) {
        http_response_code(404);
        echo json_encode(['error' => 'Club introuvable']);
        exit();
    }
    
    header('Content-Type: application/json');
    echo json_encode($club);
    exit();
}

function uploadPhoto($file) {
    $uploadDir = '../../uploads/clubs/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
    $maxSize = 2 * 1024 * 1024; // 2MB
    
    // Vérifier le type MIME
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        throw new Exception("Type de fichier non autorisé. Formats acceptés: JPG, PNG");
    }
    
    if ($file['size'] > $maxSize) {
        throw new Exception("Fichier trop volumineux (max 2MB)");
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('club_') . '.' . $extension;
    $destination = $uploadDir . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new Exception("Erreur lors de l'upload du fichier");
    }
    
    return $filename;
}
?>