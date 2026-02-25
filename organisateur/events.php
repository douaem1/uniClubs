<?php
session_start();
include '../ConnDB.php';
$isEditMode = false;
$eventData = null;

if (isset($_GET['edit'])) {
    $eventId = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM Evenement WHERE Idenv = ? AND idOrganisateur = ?");
    $stmt->bind_param("ii", $eventId, $_SESSION['idCompte']);
    $stmt->execute();
    $eventData = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($eventData) {
        $isEditMode = true;
    } else {
        header('Location: Evenements.php');
        exit;
    }
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    require 'phpmailer/src/Exception.php';
    require 'phpmailer/src/PHPMailer.php';
    require 'phpmailer/src/SMTP.php';
}

if (!isset($_SESSION['idCompte']) || $_SESSION['role'] !== 'organisateur') {
    header('Location: login.php');
    exit;
}

$idOrganisateur = $_SESSION['idCompte'];

// Fonction d'envoi d'email
function sendCancellationEmail($to, $participantName, $eventName) {
    $mail = new PHPMailer(true);
    
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'douae.moeniss@gmail.com';
        $mail->Password = 'qcdz cepv epis zggv';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        $mail->setFrom('douae.moeniss@gmail.com', 'UniClub');
        $mail->addAddress($to, $participantName);
        
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = "Annulation d'événement : " . $eventName;
        
        $mail->Body = "
            <html>
            <head>
                <style>
                    body { font-family: 'Poppins', Arial; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: linear-gradient(135deg, #f56565 0%, #e53e3e 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                    .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                    .alert-box { background: #fff5f5; border-left: 4px solid #e53e3e; padding: 15px; margin: 15px 0; border-radius: 5px; }
                    .footer { text-align: center; margin-top: 30px; color: #718096; font-size: 0.9em; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>Annulation d'événement</h1>
                    </div>
                    <div class='content'>
                        <p>Bonjour <strong>" . htmlspecialchars($participantName) . "</strong>,</p>
                        <div class='alert-box'>
                            <strong>⚠️ L'événement suivant a été annulé :</strong><br>
                            <strong>" . htmlspecialchars($eventName) . "</strong>
                        </div>
                        <p>Nous regrettons de vous informer que cet événement ne peut pas avoir lieu. Votre inscription a été automatiquement annulée.</p>
                        <div class='footer'>
                            <p>Cordialement,<br><strong>L'équipe UniClub</strong></p>
                        </div>
                    </div>
                </div>
            </body>
            </html>
        ";
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Erreur envoi email: " . $mail->ErrorInfo);
        return false;
    }
}

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $eventId = intval($_POST['Idenv']);
        
        switch ($_POST['action']) {
            case 'publish':
                $stmt = $conn->prepare("UPDATE Evenement SET etat = 'publié' WHERE Idenv = ? AND idOrganisateur = ?");
                $stmt->bind_param("ii", $eventId, $idOrganisateur);
                $stmt->execute();
                $stmt->close();
                header("Location: " . $_SERVER['PHP_SELF'] . "?publish_success=1");
                exit;
                
            case 'cancel':
                $stmtEvent = $conn->prepare("SELECT NomEnv, etat FROM Evenement WHERE Idenv = ? AND idOrganisateur = ?");
                $stmtEvent->bind_param("ii", $eventId, $idOrganisateur);
                $stmtEvent->execute();
                $event = $stmtEvent->get_result()->fetch_assoc();
                $stmtEvent->close();
                
                if ($event && $event['etat'] === 'publié') {
                    $query = "SELECT c.Email, p.Prenom, p.Nom FROM inscri i 
                              JOIN Compte c ON i.idParticipant = c.idCompte 
                              JOIN Participant p ON i.idParticipant = p.idCompte 
                              WHERE i.Idenv = ?";
                    $stmtPart = $conn->prepare($query);
                    
                    if ($stmtPart) {
                        $stmtPart->bind_param("i", $eventId);
                        $stmtPart->execute();
                        $participants = $stmtPart->get_result()->fetch_all(MYSQLI_ASSOC);
                        $stmtPart->close();
                        
                        foreach ($participants as $p) {
                            $fullName = trim(($p['Prenom'] ?? '') . ' ' . ($p['Nom'] ?? ''));
                            sendCancellationEmail($p['Email'], $fullName ?: 'Participant', $event['NomEnv']);
                        }
                    }
                }
                
                $stmtDelInscri = $conn->prepare("DELETE FROM inscri WHERE Idenv = ?");
                $stmtDelInscri->bind_param("i", $eventId);
                $stmtDelInscri->execute();
                $stmtDelInscri->close();
                
                $stmtDel = $conn->prepare("DELETE FROM Evenement WHERE Idenv = ? AND idOrganisateur = ?");
                $stmtDel->bind_param("ii", $eventId, $idOrganisateur);
                $stmtDel->execute();
                $stmtDel->close();
                
                header("Location: " . $_SERVER['PHP_SELF'] . "?cancel_success=1");
                exit;
        }
    }
    
    if (isset($_POST['update_presence'])) {
        $eventId = intval($_POST['event_id']);
        
        $stmtAll = $conn->prepare("SELECT idParticipant FROM inscri WHERE Idenv = ?");
        $stmtAll->bind_param("i", $eventId);
        $stmtAll->execute();
        $allParticipants = $stmtAll->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtAll->close();
        
        foreach ($allParticipants as $participant) {
            $participantId = $participant['idParticipant'];
            $presentValue = isset($_POST['presence'][$participantId]) ? 1 : 0;
            
            $stmt = $conn->prepare("UPDATE inscri SET present = ? WHERE idParticipant = ? AND Idenv = ?");
            $stmt->bind_param("iii", $presentValue, $participantId, $eventId);
            $stmt->execute();
            $stmt->close();
        }
        
        header("Location: " . $_SERVER['PHP_SELF'] . "?view_participants=" . $eventId . "&success=1");
        exit;
    }
}

// OPTIMISATION: Une seule requête pour récupérer événements + nombre d'inscrits
$query = "SELECT e.*, 
          (SELECT COUNT(*) FROM inscri WHERE Idenv = e.Idenv) as nb_inscrits
          FROM Evenement e 
          WHERE e.idOrganisateur = ? 
          ORDER BY e.dateDebut DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $idOrganisateur);
$stmt->execute();
$events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$viewParticipants = isset($_GET['view_participants']) ? intval($_GET['view_participants']) : 0;
$participants = [];
$eventDetails = null;

if ($viewParticipants > 0) {
    $stmtEvent = $conn->prepare("SELECT * FROM Evenement WHERE Idenv = ? AND idOrganisateur = ?");
    $stmtEvent->bind_param("ii", $viewParticipants, $idOrganisateur);
    $stmtEvent->execute();
    $eventDetails = $stmtEvent->get_result()->fetch_assoc();
    $stmtEvent->close();
    
    if ($eventDetails) {
        $stmtPart = $conn->prepare("
            SELECT i.idParticipant, p.Nom, p.Prenom, c.Email, COALESCE(i.present, 0) as present
            FROM inscri i
            JOIN Compte c ON i.idParticipant = c.idCompte
            JOIN Participant p ON i.idParticipant = p.idCompte
            WHERE i.Idenv = ?
        ");
        $stmtPart->bind_param("i", $viewParticipants);
        $stmtPart->execute();
        $participants = $stmtPart->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtPart->close();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des événements</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="navbar.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            font-family: 'Poppins', sans-serif; 
            min-height: 100vh; 
            padding: 20px 0; 
        }
        .container { max-width: 1100px; margin: 0 auto; padding: 0 20px; }
        
        .header {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 20px;
            padding: 24px 32px;
            margin-bottom: 28px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .header h2 { font-size: 1.75rem; color: #2d3748; font-weight: 700; display: flex; align-items: center; gap: 12px; }
        
        .btn { border: none; border-radius: 10px; padding: 10px 20px; font-weight: 600; cursor: pointer; transition: all 0.3s; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; }
        .btn-add { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4); }
        .btn-add:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5); }
        .btn-publish { background: linear-gradient(135deg, #48bb78 0%, #38a169 100%); color: #fff; padding: 8px 18px; font-size: 0.8em; }
        .btn-publish:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(72, 187, 120, 0.4); }
        .btn-cancel { background: linear-gradient(135deg, #f56565 0%, #e53e3e 100%); color: #fff; padding: 8px 18px; font-size: 0.8em; }
        .btn-cancel:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(245, 101, 101, 0.4); }
        .btn-participants { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; }
        .btn-back { background: #e2e8f0; color: #2d3748; }
        
        .search-bar { background: rgba(255, 255, 255, 0.98); border-radius: 16px; padding: 8px; margin-bottom: 24px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08); }
        .search-bar input { width: 100%; padding: 14px 20px 14px 50px; border-radius: 12px; border: 2px solid transparent; background: #f7fafc; font-family: 'Poppins', sans-serif; transition: all 0.3s; }
        .search-bar input:focus { outline: none; border-color: #667eea; background: #fff; }
        .search-container { position: relative; }
        .search-container i { position: absolute; left: 20px; top: 50%; transform: translateY(-50%); color: #a0aec0; font-size: 1.2em; }
        
        .event-list { display: flex; flex-direction: column; gap: 20px; }
        .event-card {
            display: flex;
            background: rgba(255, 255, 255, 0.98);
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            transition: all 0.3s;
            border: 2px solid transparent;
        }
        .event-card:hover { transform: translateY(-4px); box-shadow: 0 12px 35px rgba(102, 126, 234, 0.2); border-color: rgba(102, 126, 234, 0.3); }
        
        .event-img { width: 200px; height: 200px; object-fit: cover; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .event-content { flex: 1; padding: 24px; display: flex; gap: 20px; }
        .event-info { flex: 1; display: flex; flex-direction: column; gap: 10px; }
        .event-title { font-size: 1.3rem; font-weight: 700; color: #2d3748; }
        
        .event-meta { color: #718096; font-size: 0.9em; display: flex; gap: 20px; flex-wrap: wrap; }
        .event-meta span { display: flex; align-items: center; gap: 6px; }
        .event-meta i { color: #667eea; }
        
        .event-status { display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; border-radius: 20px; font-size: 0.8em; font-weight: 600; }
        .status-valide { background: #f0fff4; color: #38a169; border: 1.5px solid #9ae6b4; }
        .status-publie { background: #e6fffa; color: #319795; border: 1.5px solid #81e6d9; }
        .status-termine { background: #fff5f5; color: #e53e3e; border: 1.5px solid #feb2b2; }
        .status-actif { background: #ebf8ff; color: #3182ce; border: 1.5px solid #90cdf4; }
        
        .event-actions { display: flex; gap: 10px; flex-direction: column; justify-content: center; padding: 24px 24px 24px 0; }
        
        .progress-bar-bg { background: #e2e8f0; border-radius: 10px; height: 10px; overflow: hidden; margin: 8px 0; }
        .progress-bar { background: linear-gradient(90deg, #667eea 0%, #764ba2 100%); height: 10px; border-radius: 10px; }
        
        .participants-section {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
            padding: 32px;
            margin-bottom: 30px;
        }
        .participants-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 28px; padding-bottom: 20px; border-bottom: 2px solid #e2e8f0; }
        .participants-header h3 { color: #2d3748; font-size: 1.5rem; font-weight: 700; }
        
        .participant-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 18px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            background: #f7fafc;
            margin-bottom: 12px;
            transition: all 0.3s;
        }
        .participant-item:hover { border-color: #667eea; background: #fff; transform: translateX(4px); }
        
        .participant-info { flex: 1; }
        .participant-name { font-weight: 600; color: #2d3748; }
        .participant-email { font-size: 0.85em; color: #718096; }
        
        .message { padding: 16px 24px; border-radius: 12px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px; font-weight: 600; }
        .success { background: linear-gradient(135deg, #48bb78 0%, #38a169 100%); color: #fff; }
        
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); }
        .modal.active { display: flex; align-items: center; justify-content: center; }
        .modal-content { background: #fff; padding: 30px; border-radius: 20px; max-width: 500px; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3); }
        .modal-header { font-size: 1.3rem; font-weight: 700; color: #2d3748; margin-bottom: 16px; display: flex; gap: 12px; align-items: center; }
        .modal-header i { color: #e53e3e; font-size: 1.8em; }
        .modal-body { color: #718096; margin-bottom: 24px; line-height: 1.6; }
        .modal-footer { display: flex; gap: 12px; justify-content: flex-end; }
        
        @media (max-width: 768px) {
            .event-card { flex-direction: column; }
            .event-img { width: 100%; height: 200px; }
            .event-content { flex-direction: column; padding: 24px; }
            .event-actions { flex-direction: row; }
            .header { flex-direction: column; gap: 16px; text-align: center; }
            .participants-header { flex-direction: column; gap: 16px; align-items: flex-start; }
        }
    </style>
</head>
<body>
<div class="navbar">
    <div class="logo-section">
        <a href="dashboard_organisateur.php" class="logo-container">
            <div class="logo-icon"><i class="bi bi-people-fill"></i></div>
            <span class="logo-text">UniClub</span>
        </a>
    </div>
    <div class="user-menu">
        <div class="dropdown" id="userDropdown">
            <a href="#">Mon profil</a>
            <a href="../logout.php">Se déconnecter</a>
        </div>
    </div>
</div>

<div class="container">
    <?php if ($viewParticipants > 0 && $eventDetails): ?>
        <!-- Vue Participants -->
        <div class="participants-section">
            <div class="participants-header">
                <h3><i class="bi bi-people-fill"></i> Participants - <?= htmlspecialchars($eventDetails['NomEnv']) ?></h3>
                <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-back"><i class="bi bi-arrow-left"></i> Retour</a>
            </div>
            
            <?php if (isset($_GET['success'])): ?>
                <div class="message success"><i class="bi bi-check-circle-fill"></i> Présences mises à jour</div>
            <?php endif; ?>
            
            <?php if (empty($participants)): ?>
                <div style="text-align: center; padding: 60px 20px; color: #718096;">
                    <i class="bi bi-inbox" style="font-size: 3em; color: #cbd5e0; display: block; margin-bottom: 12px;"></i>
                    Aucun participant inscrit
                </div>
            <?php else: ?>
                <?php 
                    $estTermine = (new DateTime($eventDetails['dateDebut'])) < new DateTime();
                ?>
                <form method="POST" action="">
                    <input type="hidden" name="event_id" value="<?= $viewParticipants ?>">
                    <?php foreach ($participants as $p): ?>
                        <div class="participant-item">
                            <div class="participant-info">
                                <div class="participant-name"><i class="bi bi-person-circle"></i> <?= htmlspecialchars($p['Prenom'] . ' ' . $p['Nom']) ?></div>
                                <div class="participant-email"><i class="bi bi-envelope"></i> <?= htmlspecialchars($p['Email']) ?></div>
                            </div>
                            <?php if ($estTermine): ?>
                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                    <input type="checkbox" name="presence[<?= $p['idParticipant'] ?>]" value="1" <?= $p['present'] ? 'checked' : '' ?>>
                                    Présent
                                </label>
                            <?php else: ?>
                                <span class="event-status status-actif"><i class="bi bi-clock"></i> En attente</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if ($estTermine): ?>
                        <button type="submit" name="update_presence" class="btn btn-add" style="margin-top: 20px; padding: 14px 36px; font-size: 1em;">
                            <i class="bi bi-check-circle"></i> Enregistrer
                        </button>
                    <?php endif; ?>
                </form>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <!-- Vue Événements -->
        <div class="header">
            <h2><i class="bi bi-calendar-event"></i> Gestion des événements</h2>
            <a href="formevent.php" class="btn btn-add"><i class="bi bi-plus-circle"></i> Nouvel événement</a>
        </div>
        
        <?php if (isset($_GET['publish_success'])): ?>
            <div class="message success"><i class="bi bi-check-circle-fill"></i> Événement publié avec succès</div>
        <?php endif; ?>
        <?php if (isset($_GET['cancel_success'])): ?>
            <div class="message success"><i class="bi bi-check-circle-fill"></i> Événement annulé et emails envoyés aux participants</div>
        <?php endif; ?>
        
        <div class="search-bar">
            <div class="search-container">
                <i class="bi bi-search"></i>
                <input type="text" id="searchInput" placeholder="Rechercher un événement...">
            </div>
        </div>
        
        <div class="event-list">
            <?php if (empty($events)): ?>
                <div style="text-align: center; padding: 60px; background: rgba(255,255,255,0.98); border-radius: 20px; color: #718096;">
                    <i class="bi bi-calendar-x" style="font-size: 3em; display: block; margin-bottom: 12px; color: #cbd5e0;"></i>
                    Aucun événement trouvé
                </div>
            <?php endif; ?>
            
            <?php foreach ($events as $event): 
                // OPTIMISATION: Nombre d'inscrits déjà récupéré dans la requête principale
                $inscrits = $event['nb_inscrits'];
                
                $progress = ($event['Capacite'] > 0) ? min(100, round(($inscrits / $event['Capacite']) * 100)) : 0;
                $estTermine = (new DateTime($event['dateDebut'])) < new DateTime();
                $img = !empty($event['photo']) && $event['photo'] !== '0' ? $event['photo'] : 'default.jpg';
            ?>
            <div class="event-card">
                <img src="<?= htmlspecialchars($img) ?>" class="event-img" alt="<?= htmlspecialchars($event['NomEnv']) ?>" onerror="this.src='default.jpg'">
                <div class="event-content">
                    <div class="event-info">
                        <div class="event-title"><?= htmlspecialchars($event['NomEnv']) ?></div>
                        <div class="event-meta">
                            <span><i class="bi bi-calendar3"></i> <?= date('d/m/Y', strtotime($event['dateDebut'])) ?></span>
                            <span><i class="bi bi-geo-alt-fill"></i> <?= htmlspecialchars($event['Lieu']) ?></span>
                        </div>
                        
                        <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-top: 10px;">
                            <?php if ($event['validation'] === 'validé'): ?>
                                <span class="event-status status-valide"><i class="bi bi-check-circle-fill"></i> Validé</span>
                            <?php endif; ?>
                            
                            <?php if ($event['validation'] === 'validé' && $event['etat'] !== 'publié'): ?>
                                <form action="<?= $_SERVER['PHP_SELF'] ?>" method="post" style="display:inline;">
                                    <input type="hidden" name="action" value="publish">
                                    <input type="hidden" name="Idenv" value="<?= $event['Idenv'] ?>">
                                    <button type="submit" class="btn btn-publish"><i class="bi bi-megaphone"></i> Publier</button>
                                </form>
                            <?php elseif ($event['etat'] === 'publié'): ?>
                                <span class="event-status status-publie"><i class="bi bi-globe"></i> Publié</span>
                            <?php endif; ?>
                            
                            <?php if ($estTermine): ?>
                                <span class="event-status status-termine"><i class="bi bi-check-all"></i> Terminé</span>
                            <?php else: ?>
                                <span class="event-status status-actif"><i class="bi bi-calendar-check"></i> À venir</span>
                            <?php endif; ?>
                        </div>
                        
                        <div style="margin-top: 12px;">
                            <div style="display: flex; align-items: center; gap: 6px; color: #667eea; font-weight: 600; margin-bottom: 6px;">
                                <i class="bi bi-people"></i> <?= $inscrits ?>/<?= $event['Capacite'] ?> inscrits
                            </div>
                            <div class="progress-bar-bg"><div class="progress-bar" style="width: <?= $progress ?>%"></div></div>
                        </div>
                    </div>
                    
                    <div class="event-actions">
                        <a href="?view_participants=<?= $event['Idenv'] ?>" class="btn btn-participants"><i class="bi bi-people-fill"></i> Participants</a>
                        <a href="formevent.php?edit=<?= $event['Idenv'] ?>" class="btn btn-add"><i class="bi bi-pencil-fill"></i> Modifier</a>
                        <button class="btn btn-cancel" onclick="openModal(<?= $event['Idenv'] ?>, '<?= htmlspecialchars(addslashes($event['NomEnv'])) ?>')">
                            <i class="bi bi-trash"></i> Annuler
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Modal de confirmation -->
<div id="confirmModal" class="modal">
    <div class="modal-content">
        <div class="modal-header"><i class="bi bi-exclamation-triangle"></i> Confirmation d'annulation</div>
        <div class="modal-body">
            Êtes-vous sûr d'annuler l'événement <strong id="eventName"></strong> ?<br><br>
            <span style="color: #e53e3e; font-weight: 600;">⚠️ Un email d'annulation sera envoyé à tous les participants inscrits.</span>
        </div>
        <div class="modal-footer">
            <button class="btn btn-back" onclick="closeModal()">Non, annuler</button>
            <form method="POST" action="" style="display:inline;">
                <input type="hidden" name="action" value="cancel">
                <input type="hidden" name="Idenv" id="modalEventId" value="">
                <button type="submit" class="btn btn-cancel">Oui, annuler l'événement</button>
            </form>
        </div>
    </div>
</div>

<script>
    function openModal(id, name) {
        document.getElementById('modalEventId').value = id;
        document.getElementById('eventName').textContent = name;
        document.getElementById('confirmModal').classList.add('active');
    }
    
    function closeModal() {
        document.getElementById('confirmModal').classList.remove('active');
    }
    
    // Fermer modal en cliquant en dehors
    document.getElementById('confirmModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });
    
    // Recherche optimisée avec debounce
    let searchTimeout;
    document.getElementById('searchInput')?.addEventListener('input', function(e) {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            const term = e.target.value.toLowerCase();
            document.querySelectorAll('.event-card').forEach(card => {
                const title = card.querySelector('.event-title').textContent.toLowerCase();
                const location = card.querySelector('.event-meta span:nth-child(2)').textContent.toLowerCase();
                card.style.display = (title.includes(term) || location.includes(term)) ? 'flex' : 'none';
            });
        }, 300); // Attendre 300ms après la dernière frappe
    });
</script>
</body>
</html>