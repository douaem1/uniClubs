<?php
require_once '../config/database.php';
require_once '../config/session.php';

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
    $orgEmail = trim($_POST['orgEmail'] ?? '');
    $orgPassword = $_POST['orgPassword'] ?? '';

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
    $insCompte = $db->prepare("INSERT INTO Compte (Email, Password, role) VALUES (?, ?, 'organisateur')");
    $insCompte->execute([$orgEmail, $hash]);
    $newIdCompte = $db->lastInsertId();
    // Créer Organisateur (lié à Compte)
    $insOrg = $db->prepare("INSERT INTO Organisateur (idCompte) VALUES (?)");
    $insOrg->execute([$newIdCompte]);
    $idOrganisateur = $newIdCompte;

    // idCoordinateur = idCompte du coordinateur connecté (depuis la session)
    $idCoordinateur = $_SESSION['user_id'] ?? null;

    // ✅ Ajout du champ NbrAdherent = 0 par défaut
    $stmt = $db->prepare("INSERT INTO Club (NomClub, description, photoclub, NbrAdherent, idCoordinateur, idOrganisateur) VALUES (?, ?, ?, 0, ?, ?)");
    $stmt->execute([$nomClub, $description, $photoclub, $idCoordinateur, $idOrganisateur]);

    setFlashMessage('success', "Club \"$nomClub\" créé avec succès");
    header('Location: ../pages/clubs.php');
    exit();
}

function updateClub($db) {
    $idClub = intval($_POST['idClub']);
    $nomClub = trim($_POST['nomClub']);
    $description = trim($_POST['description'] ?? '');
    $orgEmail = trim($_POST['orgEmail'] ?? '');
    
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
            $stmtCur = $db->prepare("SELECT Email FROM Compte WHERE idCompte = ?");
            $stmtCur->execute([$idOrganisateur]);
            $current = $stmtCur->fetch();
            if (!$current) {
                throw new Exception("Compte organisateur introuvable");
            }
            if ($orgEmail !== $current['Email']) {
                $upd = $db->prepare("UPDATE Compte SET Email = ? WHERE idCompte = ?");
                $upd->execute([$orgEmail, $idOrganisateur]);
            }
            // Mettre à jour le mot de passe si fourni
            if (!empty($_POST['orgPassword'])) {
                $hash = password_hash($_POST['orgPassword'], PASSWORD_DEFAULT);
                $updPwd = $db->prepare("UPDATE Compte SET Password = ? WHERE idCompte = ?");
                $updPwd->execute([$hash, $idOrganisateur]);
            }
        }

        $stmt = $db->prepare("\n            UPDATE Club \n            SET NomClub = ?, description = ?, photoclub = ?, idOrganisateur = ?\n            WHERE idClub = ?\n        ");
        $stmt->execute([$nomClub, $description, $photoclub, $idOrganisateur, $idClub]);
    
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
    $stmt = $db->prepare("SELECT NomClub, photoclub, idOrganisateur FROM Club WHERE idClub = ?");
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
    if ($club['photoclub'] && file_exists("../uploads/clubs/{$club['photoclub']}")) {
        unlink("../uploads/clubs/{$club['photoclub']}");
    }
    
    $stmt = $db->prepare("DELETE FROM Club WHERE idClub = ?");
    $stmt->execute([$idClub]);

    // Supprimer également le compte de l'organisateur si non utilisé ailleurs
    if (!empty($club['idOrganisateur'])) {
        $idOrganisateur = (int)$club['idOrganisateur'];
        $check = $db->prepare("SELECT COUNT(*) as cnt FROM Club WHERE idOrganisateur = ?");
        $check->execute([$idOrganisateur]);
        $cnt = (int)$check->fetch()['cnt'];
        if ($cnt === 0) {
            $db->prepare("DELETE FROM Organisateur WHERE idCompte = ?")->execute([$idOrganisateur]);
            $db->prepare("DELETE FROM Compte WHERE idCompte = ?")->execute([$idOrganisateur]);
        }
    }
    
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