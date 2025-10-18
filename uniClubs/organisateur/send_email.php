<?php
session_start();
require_once('../ConnDB.php');

// Importer PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    require 'phpmailer/src/Exception.php';
    require 'phpmailer/src/PHPMailer.php';
    require 'phpmailer/src/SMTP.php';
}

// Fonction pour envoyer l'email
function sendEmail($to, $participantName, $subject, $message, $eventName) {
    $mail = new PHPMailer(true);
    
    try {
        // Configuration SMTP Gmail
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'douae.moeniss@gmail.com';
        $mail->Password   = 'qcdz cepv epis zggv';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        // Options SSL
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Destinataire
        $mail->setFrom('douae.moeniss@gmail.com', 'UniClub');
        $mail->addAddress($to, $participantName);
        
        // Contenu
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = $subject;
        
        $mail->Body = "
        <html>
        <head>
            <style>
                body { font-family: 'Arial', sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #8b5cf6, #a78bfa); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>📧 " . htmlspecialchars($eventName) . "</h1>
                </div>
                <div class='content'>
                    <p>Bonjour <strong>" . htmlspecialchars($participantName) . "</strong>,</p>
                    " . nl2br(htmlspecialchars($message)) . "
                    <p style='margin-top: 30px;'>Cordialement,<br><strong>L'équipe UniClub</strong></p>
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

// Traitement de l'envoi d'email
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_email') {
    $idEvent = intval($_POST['event_id']);
    $subject = $_POST['subject'] ?? '';
    $message = $_POST['message'] ?? '';
    $sendTo = $_POST['send_to'] ?? 'all';
    $singleParticipantId = $_POST['single_participant_id'] ?? null;
    
    try {
        // Récupérer l'événement
        $stmtEvent = $conn->prepare("SELECT NomEnv FROM Evenement WHERE Idenv = ?");
        $stmtEvent->bind_param("i", $idEvent);
        $stmtEvent->execute();
        $resultEvent = $stmtEvent->get_result();
        $event = $resultEvent->fetch_assoc();

        if (!$event) {
            throw new Exception("Événement introuvable.");
        }

        // Récupérer les participants selon le type d'envoi
        if ($sendTo === 'single' && $singleParticipantId) {
            // Un seul participant
            $stmtParticipants = $conn->prepare("
                SELECT p.idCompte, p.Nom, p.Prenom, c.Email
                FROM inscri i
                INNER JOIN Participant p ON i.idParticipant = p.idCompte
                INNER JOIN Compte c ON p.idCompte = c.idCompte
                WHERE i.Idenv = ? AND p.idCompte = ?
            ");
            $stmtParticipants->bind_param("ii", $idEvent, $singleParticipantId);
        } elseif ($sendTo === 'present') {
            // Participants présents
            $stmtParticipants = $conn->prepare("
                SELECT p.idCompte, p.Nom, p.Prenom, c.Email
                FROM inscri i
                INNER JOIN Participant p ON i.idParticipant = p.idCompte
                INNER JOIN Compte c ON p.idCompte = c.idCompte
                WHERE i.Idenv = ? AND i.present = TRUE
            ");
            $stmtParticipants->bind_param("i", $idEvent);
        } else {
            // Tous les participants
            $stmtParticipants = $conn->prepare("
                SELECT p.idCompte, p.Nom, p.Prenom, c.Email
                FROM inscri i
                INNER JOIN Participant p ON i.idParticipant = p.idCompte
                INNER JOIN Compte c ON p.idCompte = c.idCompte
                WHERE i.Idenv = ?
            ");
            $stmtParticipants->bind_param("i", $idEvent);
        }
        $stmtParticipants->execute();
        $resultParticipants = $stmtParticipants->get_result();

        $participants = [];
        while ($row = $resultParticipants->fetch_assoc()) {
            $participants[] = $row;
        }

        if (empty($participants)) {
            $_SESSION['message'] = "Aucun participant à qui envoyer l'email.";
            $_SESSION['message_type'] = "warning";
        } else {
            $successCount = 0;
            $errorCount = 0;

            foreach ($participants as $participant) {
                try {
                    $emailSent = sendEmail(
                        $participant['Email'],
                        $participant['Nom'] . ' ' . $participant['Prenom'],
                        $subject,
                        $message,
                        $event['NomEnv']
                    );

                    if ($emailSent) {
                        $successCount++;
                    } else {
                        $errorCount++;
                    }
                } catch (Exception $e) {
                    $errorCount++;
                    error_log("Erreur envoi email: " . $e->getMessage());
                }
            }

            $_SESSION['message'] = "$successCount email(s) envoyé(s) avec succès.";
            if ($errorCount > 0) {
                $_SESSION['message'] .= " $errorCount échec(s).";
            }
            $_SESSION['message_type'] = $successCount > 0 ? "success" : "danger";
        }

    } catch (Exception $e) {
        $_SESSION['message'] = "Erreur: " . $e->getMessage();
        $_SESSION['message_type'] = "danger";
    }

    header('Location: send_email.php');
    exit();
}

// Récupérer tous les événements (MySQLi)
$queryEvents = "
    SELECT e.*, c.NomClub,
    (SELECT COUNT(*) FROM inscri WHERE Idenv = e.Idenv AND present = TRUE) as nbPresents,
    (SELECT COUNT(*) FROM inscri WHERE Idenv = e.Idenv) as nbInscrits
    FROM Evenement e
    LEFT JOIN Club c ON e.idClub = c.idClub
    ORDER BY e.dateDebut DESC
";
$resultEvents = $conn->query($queryEvents);
$events = [];
if ($resultEvents) {
    while ($row = $resultEvents->fetch_assoc()) {
        $events[] = $row;
    }
}

// Si un événement est sélectionné, récupérer ses participants
$selectedEvent = null;
$participants = [];
if (isset($_GET['event_id'])) {
    $eventId = intval($_GET['event_id']);
    
    $stmtEvent = $conn->prepare("SELECT * FROM Evenement WHERE Idenv = ?");
    $stmtEvent->bind_param("i", $eventId);
    $stmtEvent->execute();
    $resultEvent = $stmtEvent->get_result();
    $selectedEvent = $resultEvent->fetch_assoc();
    
    if ($selectedEvent) {
        $stmtParticipants = $conn->prepare("
            SELECT p.idCompte, p.Nom, p.Prenom, c.Email, i.present
            FROM inscri i
            INNER JOIN Participant p ON i.idParticipant = p.idCompte
            INNER JOIN Compte c ON p.idCompte = c.idCompte
            WHERE i.Idenv = ?
            ORDER BY p.Nom, p.Prenom
        ");
        $stmtParticipants->bind_param("i", $eventId);
        $stmtParticipants->execute();
        $resultParticipants = $stmtParticipants->get_result();
        
        while ($row = $resultParticipants->fetch_assoc()) {
            $participants[] = $row;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Envoyer Email - UniClub</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="navbar.css">

    
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: #f5f5f9;
        }

        .navbar-custom {
            background: #fff;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            padding: 18px 0;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 1.4rem;
            font-weight: 700;
            color: #333;
            text-decoration: none;
        }

        .logo-icon {
            width: 42px;
            height: 42px;
            background: linear-gradient(135deg, #8b5cf6, #a78bfa);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1.3rem;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
        }

        .page-description {
            color: #6c757d;
        }

        .card-custom {
            border: none;
            border-radius: 18px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            margin-bottom: 30px;
        }

        .event-item {
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 12px;
            cursor: pointer;
            transition: all 0.2s;
            border: 2px solid transparent;
            text-decoration: none;
            display: block;
            color: inherit;
        }

        .event-item:hover {
            background: #f9f7ff;
            border-color: #e9e3f9;
            color: inherit;
        }

        .event-item.active {
            background: #f3f0ff;
            border-color: #8b5cf6;
        }

        .event-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 6px;
        }

        .event-date {
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 6px;
        }

        .event-stats {
            font-size: 0.85rem;
            color: #8b5cf6;
            font-weight: 500;
        }

        .participants-list {
            max-height: 300px;
            overflow-y: auto;
            padding: 16px;
            background: #f9f7ff;
            border-radius: 12px;
        }

        .participant-item {
            padding: 12px;
            margin-bottom: 8px;
            background: #fff;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .badge-present {
            background: #d1fae5;
            color: #065f46;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .btn-primary-custom {
            background: linear-gradient(135deg, #8b5cf6, #a78bfa);
            border: none;
            padding: 12px 28px;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.2s;
        }

        .btn-primary-custom:hover {
            background: linear-gradient(135deg, #7c3aed, #8b5cf6);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }

        .empty-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        .logo-section {
      display: flex;
      align-items: center;
    }

    .logo-container {
      display: flex;
      align-items: center;
      gap: 12px;
      text-decoration: none;
      color: #333;
    }

    .logo-icon {
      width: 42px;
      height: 42px;
      background: linear-gradient(135deg, var(--primary-color), var(--primary-hover));
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--white);
      font-size: 1.3rem;
    }

    .logo-text {
      font-size: 1.4rem;
      font-weight: 700;
      color: #333;
    }


        .form-control:focus,
        .form-select:focus {
            border-color: #8b5cf6;
            box-shadow: 0 0 0 0.25rem rgba(139, 92, 246, 0.15);
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-custom">
        <div class="container">
            <a href="dashboard_organisateur.php" class="logo">
                <div class="logo-icon">
                    <i class="bi bi-people-fill"></i>
                </div>
                <span>UniClub</span>
            </a>
            <a href="dashboard_organisateur.php" class="btn btn-link text-decoration-none" style="color: #8b5cf6;">
                <i class="bi bi-arrow-left"></i> Retour au tableau de bord
            </a>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title"><i class="bi bi-envelope-fill"></i> Envoyer un Email</h1>
            <p class="page-description">Sélectionnez un événement et envoyez un email groupé aux participants</p>
        </div>

        <!-- Alert Messages -->
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-<?= $_SESSION['message_type'] ?> alert-dismissible fade show" role="alert">
                <?php if ($_SESSION['message_type'] === 'success'): ?>
                    <i class="bi bi-check-circle-fill"></i>
                <?php elseif ($_SESSION['message_type'] === 'warning'): ?>
                    <i class="bi bi-exclamation-triangle-fill"></i>
                <?php else: ?>
                    <i class="bi bi-x-circle-fill"></i>
                <?php endif; ?>
                <?= htmlspecialchars($_SESSION['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
        <?php endif; ?>

        <?php if (!empty($events)): ?>
            <div class="row">
                <!-- Liste des événements -->
                <div class="col-lg-5">
                    <div class="card card-custom">
                        <div class="card-body">
                            <h5 class="card-title mb-4"><i class="bi bi-calendar-event"></i> Événements</h5>
                            <div style="max-height: 600px; overflow-y: auto;">
                                <?php foreach ($events as $event): ?>
                                    <a href="?event_id=<?= $event['Idenv'] ?>" 
                                       class="event-item <?= (isset($_GET['event_id']) && $_GET['event_id'] == $event['Idenv']) ? 'active' : '' ?>">
                                        <div class="event-name"><?= htmlspecialchars($event['NomEnv']) ?></div>
                                        <div class="event-date">
                                            <i class="bi bi-calendar3"></i> <?= date('d/m/Y', strtotime($event['dateDebut'])) ?>
                                        </div>
                                        <div class="event-stats">
                                            <i class="bi bi-people"></i> <?= $event['nbInscrits'] ?> inscrits • 
                                            <i class="bi bi-check-circle"></i> <?= $event['nbPresents'] ?> présents
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section Email -->
                <div class="col-lg-7">
                    <div class="card card-custom">
                        <div class="card-body">
                            <?php if ($selectedEvent): ?>
                                <h5 class="card-title mb-4">
                                    <i class="bi bi-envelope-paper"></i> 
                                    <?= htmlspecialchars($selectedEvent['NomEnv']) ?>
                                </h5>

                                <?php if (!empty($participants)): ?>
                                    <!-- Liste des participants -->
                                    <div class="mb-4">
                                        <label class="form-label fw-semibold">
                                            <i class="bi bi-people"></i> Participants (<?= count($participants) ?>)
                                        </label>
                                        <div class="participants-list">
                                            <?php foreach ($participants as $p): ?>
                                                <div class="participant-item" data-participant-id="<?= $p['idCompte'] ?>">
                                                    <div>
                                                        <div class="fw-semibold"><?= htmlspecialchars($p['Nom'] . ' ' . $p['Prenom']) ?></div>
                                                        <small class="text-muted">
                                                            <i class="bi bi-envelope"></i> <?= htmlspecialchars($p['Email']) ?>
                                                        </small>
                                                    </div>
                                                    <div class="d-flex gap-2 align-items-center">
                                                        <?php if ($p['present']): ?>
                                                            <span class="badge-present">
                                                                <i class="bi bi-check-circle"></i> Présent
                                                            </span>
                                                        <?php endif; ?>
                                                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                                                onclick="selectSingleParticipant(<?= $p['idCompte'] ?>, '<?= htmlspecialchars($p['Nom'] . ' ' . $p['Prenom']) ?>')">
                                                            <i class="bi bi-send"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>

                                    <!-- Formulaire d'envoi -->
                                    <form method="post" id="emailForm">
                                        <input type="hidden" name="action" value="send_email">
                                        <input type="hidden" name="event_id" value="<?= $selectedEvent['Idenv'] ?>">
                                        <input type="hidden" name="single_participant_id" id="singleParticipantId" value="">

                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">
                                                <i class="bi bi-send"></i> Envoyer à :
                                            </label>
                                            <select name="send_to" id="sendToSelect" class="form-select" onchange="updateSendToOption()">
                                                <option value="all">Tous les inscrits (<?= count($participants) ?>)</option>
                                                <option value="present">Seulement les présents (<?= array_filter($participants, fn($p) => $p['present']) ? count(array_filter($participants, fn($p) => $p['present'])) : 0 ?>)</option>
                                                <option value="single" id="singleOption" disabled>Un participant spécifique</option>
                                            </select>
                                            <div id="selectedParticipantInfo" class="mt-2" style="display: none;">
                                                <div class="alert alert-info py-2 mb-0">
                                                    <i class="bi bi-person-check-fill"></i> 
                                                    <strong>Destinataire :</strong> <span id="selectedParticipantName"></span>
                                                    <button type="button" class="btn-close btn-close-sm float-end" onclick="clearSingleSelection()"></button>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">
                                                <i class="bi bi-card-heading"></i> Sujet de l'email :
                                            </label>
                                            <input type="text" name="subject" class="form-control" 
                                                   placeholder="Ex: Rappel - <?= htmlspecialchars($selectedEvent['NomEnv']) ?>" 
                                                   required>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">
                                                <i class="bi bi-chat-text"></i> Message :
                                            </label>
                                            <textarea name="message" class="form-control" rows="6"
                                                      placeholder="Écrivez votre message ici..." 
                                                      required></textarea>
                                        </div>

                                        <button type="submit" class="btn btn-primary-custom w-100" 
                                                onclick="return confirm('Envoyer cet email à tous les participants sélectionnés ?');">
                                            <i class="bi bi-send-fill"></i> Envoyer l'email
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <div class="empty-state">
                                        <div class="empty-icon"><i class="bi bi-people"></i></div>
                                        <h5>Aucun participant inscrit</h5>
                                        <p class="text-muted">Il n'y a aucun participant inscrit à cet événement</p>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="empty-state">
                                    <div class="empty-icon"><i class="bi bi-envelope"></i></div>
                                    <h5>Sélectionnez un événement</h5>
                                    <p class="text-muted">Cliquez sur un événement dans la liste pour composer votre email</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="card card-custom">
                <div class="card-body">
                    <div class="empty-state py-5">
                        <div class="empty-icon"><i class="bi bi-calendar-x"></i></div>
                        <h4>Aucun événement trouvé</h4>
                        <p class="text-muted mb-4">Créez d'abord des événements pour envoyer des emails</p>
                        <a href="dashboard_organisateur.php" class="btn btn-primary-custom">
                            <i class="bi bi-plus-circle"></i> Créer un événement
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Fonction pour sélectionner un participant unique
        function selectSingleParticipant(participantId, participantName) {
            document.getElementById('singleParticipantId').value = participantId;
            document.getElementById('sendToSelect').value = 'single';
            document.getElementById('singleOption').disabled = false;
            document.getElementById('singleOption').textContent = 'Un participant spécifique (sélectionné)';
            document.getElementById('selectedParticipantName').textContent = participantName;
            document.getElementById('selectedParticipantInfo').style.display = 'block';
            
            // Mettre en surbrillance le participant sélectionné
            document.querySelectorAll('.participant-item').forEach(item => {
                item.style.backgroundColor = '';
                item.style.border = '';
            });
            const selectedItem = document.querySelector(`.participant-item[data-participant-id="${participantId}"]`);
            if (selectedItem) {
                selectedItem.style.backgroundColor = '#e0f2fe';
                selectedItem.style.border = '2px solid #0ea5e9';
            }
        }
        
        // Fonction pour effacer la sélection d'un participant unique
        function clearSingleSelection() {
            document.getElementById('singleParticipantId').value = '';
            document.getElementById('sendToSelect').value = 'all';
            document.getElementById('singleOption').disabled = true;
            document.getElementById('singleOption').textContent = 'Un participant spécifique';
            document.getElementById('selectedParticipantInfo').style.display = 'none';
            
            // Retirer la surbrillance
            document.querySelectorAll('.participant-item').forEach(item => {
                item.style.backgroundColor = '';
                item.style.border = '';
            });
        }
        
        // Fonction pour mettre à jour l'option d'envoi
        function updateSendToOption() {
            const sendTo = document.getElementById('sendToSelect').value;
            if (sendTo !== 'single') {
                clearSingleSelection();
            }
        }
        
        // Validation du formulaire
        document.getElementById('emailForm').addEventListener('submit', function(e) {
            const sendTo = document.getElementById('sendToSelect').value;
            const singleParticipantId = document.getElementById('singleParticipantId').value;
            
            if (sendTo === 'single' && !singleParticipantId) {
                e.preventDefault();
                alert('Veuillez sélectionner un participant en cliquant sur le bouton d\'envoi à côté de son nom.');
                return false;
            }
            
            let confirmMessage = 'Envoyer cet email ';
            if (sendTo === 'single') {
                const participantName = document.getElementById('selectedParticipantName').textContent;
                confirmMessage += `à ${participantName} ?`;
            } else if (sendTo === 'present') {
                confirmMessage += 'à tous les participants présents ?';
            } else {
                confirmMessage += 'à tous les participants ?';
            }
            
            if (!confirm(confirmMessage)) {
                e.preventDefault();
                return false;
            }
        });
    </script>
</body>
</html>