<?php
require_once '../config/session.php';
include '../../ConnDB.php';

//requireCoordinateur();


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
            createClub($conn);
            break;
        case 'update':
            updateClub($conn);
            break;
        case 'delete':
            deleteClub($conn);
            break;
        case 'get':
            getClub($conn);
            break;
        default:
            throw new Exception("Action invalide");
    }
} catch (Exception $e) {
    setFlashMessage('error', $e->getMessage());
    header('Location: ../pages/clubs.php');
    exit();
}

function createClub($conn) {
    $nomClub = trim($_POST['nomClub']);
    $description = trim($_POST['description'] ?? '');
    $orgEmail = trim($_POST['orgEmail'] ?? '');
    $orgPassword = $_POST['orgPassword'] ?? '';
    
    // Réseaux sociaux
    $facebook = trim($_POST['facebook'] ?? '');
    $instagram = trim($_POST['instagram'] ?? '');
    $twitter = trim($_POST['twitter'] ?? '');
    $linkedin = trim($_POST['linkedin'] ?? '');
    $website = trim($_POST['website'] ?? '');

    // Vérifier si le club existe déjà
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM Club WHERE NomClub = ?");
    $stmt->bind_param('s', $nomClub);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
 
    if ($row['count'] > 0) {
        throw new Exception("Un club avec ce nom existe déjà");
    }

    // Gérer l'upload de photo
    $photoclub = null;
    if (isset($_FILES['photoclub']) && $_FILES['photoclub']['error'] === UPLOAD_ERR_OK) {
        $photoclub = uploadPhoto($_FILES['photoclub']);
    }

    // Email du coordinateur connecté
    $emailCord = $_SESSION['email'];

    // Créer systématiquement le compte + organisateur pour cet organisateur
    if ($orgEmail === '') {
        throw new Exception('Email de l\'organisateur requis');
    }
    // Créer Compte avec le mot de passe fourni par le coordinateur
    if ($orgPassword === '') {
        throw new Exception('Mot de passe de l\'organisateur requis');
    }
    $hash = password_hash($orgPassword, PASSWORD_DEFAULT);
    $insCompte = $conn->prepare("INSERT INTO Compte (Email, Password, role) VALUES (?, ?, 'organisateur')");
    $insCompte->bind_param('ss', $orgEmail, $hash);
    if (!$insCompte->execute()) {
        throw new Exception("Erreur lors de la création du compte: " . $conn->error);
    }
    $newIdCompte = $conn->insert_id;
    $insCompte->close();
    // Créer Organisateur (lié à Compte)
    $insOrg = $conn->prepare("INSERT INTO Organisateur (idCompte) VALUES (?)");
    $insOrg->bind_param('i', $newIdCompte);
    if (!$insOrg->execute()) {
        // En cas d'échec, supprimer le compte créé pour éviter les orphelins
        $conn->query("DELETE FROM Compte WHERE idCompte = $newIdCompte");
        throw new Exception("Erreur lors de la création de l'organisateur: " . $conn->error);
    }
    $insOrg->close();
    $idOrganisateur = $newIdCompte;

    // idCoordinateur = idCompte du coordinateur connecté (depuis la session)
    $idCoordinateur = $_SESSION['idCompte'] ?? null;
    
    // Vérifier que le coordinateur existe et qu'il a bien le rôle de coordinateur
    if ($idCoordinateur) {
        $stmtCheck = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM Coordinateur 
            WHERE idCompte = ?
        ");
        $stmtCheck->bind_param("i", $idCoordinateur);
        $stmtCheck->execute();
        $resultCheck = $stmtCheck->get_result();
        $rowCheck = $resultCheck->fetch_assoc();
        $stmtCheck->close();
        
        // Si le coordinateur n'existe pas dans la table Coordinateur, le créer
        if ($rowCheck['count'] === 0) {
            $stmtCreateCoord = $conn->prepare("INSERT INTO Coordinateur (idCompte) VALUES (?)");
            $stmtCreateCoord->bind_param("i", $idCoordinateur);
            if (!$stmtCreateCoord->execute()) {
                throw new Exception("Erreur lors de la création du coordinateur: " . $conn->error);
            }
            $stmtCreateCoord->close();
        }
    }

    // S'assurer qu'il n'y a pas de résultats en attente
    while ($conn->more_results()) {
        $conn->next_result();
        if ($result = $conn->store_result()) {
            $result->free();
        }
    }
    
    // Créer le club
    $stmt = $conn->prepare("INSERT INTO Club (NomClub, description, photoclub, NbrAdherent, idCoordinateur, idOrganisateur, facebook, instagram, twitter, linkedin, website) VALUES (?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('sssiisssss', $nomClub, $description, $photoclub, $idCoordinateur, $idOrganisateur, $facebook, $instagram, $twitter, $linkedin, $website);
    
    if (!$stmt->execute()) {
        // En cas d'échec, nettoyer les entrées précédentes
        $conn->query("DELETE FROM Organisateur WHERE idCompte = $idOrganisateur");
        $conn->query("DELETE FROM Compte WHERE idCompte = $idOrganisateur");
        throw new Exception("Erreur lors de la création du club: " . $conn->error);
    }
    $stmt->close();

    setFlashMessage('success', "Club \"$nomClub\" créé avec succès");
    header('Location: ../pages/clubs.php');
    exit();
}

function updateClub($conn) {
    $idClub = intval($_POST['idClub']);
    $nomClub = trim($_POST['nomClub']);
    $description = trim($_POST['description'] ?? '');
    $orgEmail = trim($_POST['orgEmail'] ?? '');
    
    // Réseaux sociaux
    $facebook = trim($_POST['facebook'] ?? '');
    $instagram = trim($_POST['instagram'] ?? '');
    $twitter = trim($_POST['twitter'] ?? '');
    $linkedin = trim($_POST['linkedin'] ?? '');
    $website = trim($_POST['website'] ?? '');
    
    // Vérifier si le club existe
    $stmt = $conn->prepare("SELECT * FROM Club WHERE idClub = ?");
    $stmt->bind_param("i", $idClub);
    $stmt->execute();
    $result = $stmt->get_result();
    $club = $result->fetch_assoc();
    
    if (!$club) {
        throw new Exception("Club introuvable");
    }
    
    // Gérer l'upload de photo
    $photoclub = $club['photoclub'];
    if (isset($_FILES['photoclub']) && $_FILES['photoclub']['error'] === UPLOAD_ERR_OK) {
        // Supprimer l'ancienne photo si elle existe
        if ($photoclub && file_exists("../uploads/clubs/$photoclub")) {
            unlink("../uploads/clubs/$photoclub");
        }
        $photoclub = uploadPhoto($_FILES['photoclub']);
    }
    
    // Par défaut conserver le même organisateur
    $idOrganisateur = $club['idOrganisateur'];

    // Si un email d'organisateur est fourni, mettre à jour le Compte existant
    if ($orgEmail !== '') {
        // Email actuel
        $stmtCur = $conn->prepare("SELECT Email FROM Compte WHERE idCompte = ?");
        $stmtCur->bind_param("i", $idOrganisateur);
        $stmtCur->execute();
        $resultCur = $stmtCur->get_result();
        $current = $resultCur->fetch_assoc();
        
        if (!$current) {
            throw new Exception("Compte organisateur introuvable");
        }
        
        if ($orgEmail !== $current['Email']) {
            $upd = $conn->prepare("UPDATE Compte SET Email = ? WHERE idCompte = ?");
            $upd->bind_param("si", $orgEmail, $idOrganisateur);
            $upd->execute();
        }
        
        // Mettre à jour le mot de passe si fourni
        if (!empty($_POST['orgPassword'])) {
            $hash = password_hash($_POST['orgPassword'], PASSWORD_DEFAULT);
            $updPwd = $conn->prepare("UPDATE Compte SET Password = ? WHERE idCompte = ?");
            $updPwd->bind_param("si", $hash, $idOrganisateur);
            $updPwd->execute();
        }
    }

    // Mettre à jour le club
    $stmt = $conn->prepare("
        UPDATE Club 
        SET NomClub = ?, description = ?, photoclub = ?, idOrganisateur = ?, facebook = ?, instagram = ?, twitter = ?, linkedin = ?, website = ?
        WHERE idClub = ?
    ");
    $stmt->bind_param("sssisssssi", $nomClub, $description, $photoclub, $idOrganisateur, $facebook, $instagram, $twitter, $linkedin, $website, $idClub);
    $stmt->execute();
    
    setFlashMessage('success', "Club \"$nomClub\" modifié avec succès");
    header('Location: ../pages/clubs.php');
    exit();
}

function deleteClub($conn) {
    $idClub = intval($_POST['idClub'] ?? $_GET['id'] ?? 0);
    
    if ($idClub === 0) {
        throw new Exception("ID de club invalide");
    }
    
    // Récupérer les infos du club
    $stmt = $conn->prepare("SELECT NomClub, photoclub, idOrganisateur FROM Club WHERE idClub = ?");
    $stmt->bind_param("i", $idClub);
    $stmt->execute();
    $result = $stmt->get_result();
    $club = $result->fetch_assoc();
    
    if (!$club) {
        throw new Exception("Club introuvable");
    }
    
    // Vérifier si le club a des événements
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM Evenement WHERE idClub = ?");
    $stmt->bind_param("i", $idClub);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $count = $row['count'];
    
    if ($count > 0) {
        throw new Exception("Impossible de supprimer ce club car il a $count événement(s) associé(s)");
    }
    
    // Supprimer la photo si elle existe
    if ($club['photoclub'] && file_exists("../uploads/clubs/{$club['photoclub']}")) {
        unlink("../uploads/clubs/{$club['photoclub']}");
    }
    
    // Supprimer le club
    $stmt = $conn->prepare("DELETE FROM Club WHERE idClub = ?");
    $stmt->bind_param("i", $idClub);
    $stmt->execute();

    // Supprimer également le compte de l'organisateur si non utilisé ailleurs
    if (!empty($club['idOrganisateur'])) {
        $idOrganisateur = (int)$club['idOrganisateur'];
        
        $check = $conn->prepare("SELECT COUNT(*) as cnt FROM Club WHERE idOrganisateur = ?");
        $check->bind_param("i", $idOrganisateur);
        $check->execute();
        $resultCheck = $check->get_result();
        $rowCheck = $resultCheck->fetch_assoc();
        $cnt = (int)$rowCheck['cnt'];
        
        if ($cnt === 0) {
            // Supprimer l'organisateur
            $stmt = $conn->prepare("DELETE FROM Organisateur WHERE idCompte = ?");
            $stmt->bind_param("i", $idOrganisateur);
            $stmt->execute();
            
            // Supprimer le compte
            $stmt = $conn->prepare("DELETE FROM Compte WHERE idCompte = ?");
            $stmt->bind_param("i", $idOrganisateur);
            $stmt->execute();
        }
    }
    
    setFlashMessage('success', "Club \"{$club['NomClub']}\" supprimé avec succès");
    header('Location: ../pages/clubs.php');
    exit();
}

function getClub($conn) {
    $idClub = intval($_GET['id'] ?? 0);
    
    if ($idClub === 0) {
        http_response_code(400);
        echo json_encode(['error' => 'ID invalide']);
        exit();
    }
    
    $stmt = $conn->prepare("SELECT * FROM Club WHERE idClub = ?");
    $stmt->bind_param("i", $idClub);
    $stmt->execute();
    $result = $stmt->get_result();
    $club = $result->fetch_assoc();
    
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
    $uploadDir = '../uploads/clubs/';
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