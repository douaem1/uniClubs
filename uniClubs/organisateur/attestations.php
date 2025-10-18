<?php
session_start();
require_once('../ConnDB.php');
require_once('fpdf/fpdf.php');

// Importer PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    require 'phpmailer/src/Exception.php';
    require 'phpmailer/src/PHPMailer.php';
    require 'phpmailer/src/SMTP.php';
}

// Traitement de la génération et envoi des attestations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    if ($_POST['action'] === 'generate_send') {
        $idEvent = intval($_POST['event_id']);

        // Récupérer les informations de l'événement
        $stmtEvent = $conn->prepare("
            SELECT e.*, c.NomClub 
            FROM Evenement e
            LEFT JOIN Club c ON e.idClub = c.idClub
            WHERE e.Idenv = ?
        ");
        $stmtEvent->bind_param("i", $idEvent);
        $stmtEvent->execute();
        $resultEvent = $stmtEvent->get_result();
        $event = $resultEvent->fetch_assoc();

        if (!$event) {
            $_SESSION['message'] = "Événement introuvable.";
            $_SESSION['message_type'] = "error";
            header('Location: attestations.php');
            exit();
        }

        // Récupérer les participants présents
        $stmtParticipants = $conn->prepare("
            SELECT p.*, c.Email, i.present
            FROM inscri i
            INNER JOIN Participant p ON i.idParticipant = p.idCompte
            INNER JOIN Compte c ON p.idCompte = c.idCompte
            WHERE i.Idenv = ? AND i.present = TRUE
        ");
        $stmtParticipants->bind_param("i", $idEvent);
        $stmtParticipants->execute();
        $resultParticipants = $stmtParticipants->get_result();

        $participants = [];
        while ($row = $resultParticipants->fetch_assoc()) {
            $participants[] = $row;
        }

        if (empty($participants)) {
            $_SESSION['message'] = "Aucun participant marqué comme présent.";
            $_SESSION['message_type'] = "warning";
        } else {
            $attestationsDir = 'attestations';
            if (!is_dir($attestationsDir)) {
                mkdir($attestationsDir, 0777, true);
            }

            $successCount = 0;
            $errorCount = 0;

            foreach ($participants as $participant) {
                try {
                    // Générer l'attestation PDF
                    $fileName = generateAttestation($event, $participant, $attestationsDir);

                    // Enregistrer dans la base de données
                    $stmtAtt = $conn->prepare("
                        INSERT INTO Attestation (chemin, Idenv, idParticipant)
                        VALUES (?, ?, ?)
                        ON DUPLICATE KEY UPDATE chemin = VALUES(chemin)
                    ");
                    $stmtAtt->bind_param("sii", $fileName, $idEvent, $participant['idCompte']);
                    $stmtAtt->execute();

                    // Envoyer par email
                    $emailSent = sendAttestationEmail(
                        $participant['Email'],
                        $participant['Nom'] . ' ' . $participant['Prenom'],
                        $event['NomEnv'],
                        $fileName
                    );

                    if ($emailSent) {
                        $successCount++;
                        error_log("SUCCESS: Email envoyé à " . $participant['Email']);
                    } else {
                        $errorCount++;
                        error_log("ERROR: Échec envoi email à " . $participant['Email']);
                    }

                } catch (Exception $e) {
                    $errorCount++;
                    error_log("EXCEPTION: " . $e->getMessage() . " pour " . ($participant['Email'] ?? 'email inconnu'));
                    $_SESSION['error_detail'] = $e->getMessage();
                }
            }

            $_SESSION['message'] = "$successCount attestation(s) générée(s) et envoyée(s) avec succès.";
            if ($errorCount > 0) {
                $_SESSION['message'] .= " $errorCount échec(s).";
            }
            $_SESSION['message_type'] = $successCount > 0 ? "success" : "error";
        }

        header('Location: attestations.php');
        exit();
    }
}

// Fonction pour générer l'attestation PDF
function generateAttestation($event, $participant, $directory) {
    $pdf = new FPDF('P', 'mm', 'A4');
    $pdf->AddPage();
    
    // Bordure décorative
    $pdf->SetDrawColor(139, 92, 246);
    $pdf->SetLineWidth(1);
    $pdf->Rect(10, 10, 190, 277);
    $pdf->SetLineWidth(0.5);
    $pdf->Rect(12, 12, 186, 273);
    
    // Logo ou titre principal
    $pdf->SetFont('Arial', 'B', 28);
    $pdf->SetTextColor(139, 92, 246);
    $pdf->SetY(30);
    $pdf->Cell(0, 15, utf8_decode('ATTESTATION'), 0, 1, 'C');
    
    $pdf->SetFont('Arial', '', 14);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->Cell(0, 10, 'de participation', 0, 1, 'C');
    
    // Ligne décorative
    $pdf->SetDrawColor(139, 92, 246);
    $pdf->SetLineWidth(0.5);
    $pdf->Line(60, 60, 150, 60);
    
    // Corps du texte
    $pdf->SetY(80);
    $pdf->SetFont('Arial', '', 12);
    $pdf->SetTextColor(50, 50, 50);
    
    $pdf->MultiCell(0, 8, "Nous certifions que", 0, 'C');
    
    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'B', 18);
    $pdf->SetTextColor(139, 92, 246);
    $fullName = $participant['Nom'] . ' ' . $participant['Prenom'];
    $pdf->Cell(0, 12, utf8_decode($fullName), 0, 1, 'C');
    
    $pdf->Ln(5);
    $pdf->SetFont('Arial', '', 12);
    $pdf->SetTextColor(50, 50, 50);
    $pdf->MultiCell(0, 8, utf8_decode("a participé à l'événement"), 0, 'C');
    
    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->SetTextColor(70, 70, 70);
    $pdf->MultiCell(0, 10, utf8_decode($event['NomEnv']), 0, 'C');
    
    // Détails de l'événement
    $pdf->Ln(10);
    $pdf->SetFont('Arial', '', 11);
    $pdf->SetTextColor(100, 100, 100);
    
    if (!empty($event['NomClub'])) {
        $pdf->Cell(0, 8, utf8_decode("Organisé par: " . $event['NomClub']), 0, 1, 'C');
    }
    
    $pdf->Cell(0, 8, utf8_decode("Type: " . $event['Type']), 0, 1, 'C');
    $pdf->Cell(0, 8, utf8_decode("Lieu: " . $event['Lieu']), 0, 1, 'C');
    
    $dateDebut = date('d/m/Y', strtotime($event['dateDebut']));
    $dateFin = date('d/m/Y', strtotime($event['dateFin']));
    $pdf->Cell(0, 8, utf8_decode("Date: du $dateDebut au $dateFin"), 0, 1, 'C');
    
    // Signature et date
    $pdf->SetY(230);
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 8, utf8_decode("Délivré le " . date('d/m/Y')), 0, 1, 'C');
    
    $pdf->Ln(15);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(95, 8, "L'Organisateur", 0, 0, 'C');
   
    // Nom du fichier unique
    $fileName = $directory . '/attestation_' . $event['Idenv'] . '_' . $participant['idCompte'] . '_' . time() . '.pdf';
    $pdf->Output('F', $fileName);
    
    return $fileName;
}

// Fonction pour envoyer l'email avec l'attestation
function sendAttestationEmail($to, $participantName, $eventName, $filePath) {
    
    $mail = new PHPMailer(true);
    
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'douae.moeniss@gmail.com';
        $mail->Password   = 'qcdz cepv epis zggv';
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;
    
        $mail->setFrom('douae.moeniss@gmail.com', 'UniClub');
        $mail->addAddress($to, $participantName);
        $mail->addReplyTo('douae.moeniss@gmail.com', 'UniClub');
        
        // Contenu
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = "Votre attestation de participation - " . $eventName;
        
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
                    <h1> Attestation de participation</h1>
                </div>
                <div class='content'>
                    <p>Bonjour <strong>" . htmlspecialchars($participantName) . "</strong>,</p>
                    <p>Nous vous remercions pour votre participation à l'événement :</p>
                    <h2 style='color: #8b5cf6;'>" . htmlspecialchars($eventName) . "</h2>
                    <p>Vous trouverez en pièce jointe votre attestation de participation officielle.</p>
                    <p>Nous espérons vous revoir très bientôt lors de nos prochains événements !</p>
                    <p style='margin-top: 30px;'>Cordialement,<br><strong>L'équipe UniClub</strong></p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        // Vérifier que le fichier existe avant de l'attacher
        if (!file_exists($filePath)) {
            throw new Exception("Le fichier PDF n'existe pas: " . $filePath);
        }
        
        // Attacher le PDF
        $mail->addAttachment($filePath, 'attestation_participation.pdf');
        
        $mail->send();
        error_log("SUCCESS PHPMailer: Email envoyé à " . $to);
        return true;
        
    } catch (Exception $e) {
        error_log("ERROR PHPMailer: " . $mail->ErrorInfo . " | Exception: " . $e->getMessage());
        return false;
    }
}

// RÉCUPÉRATION DES ÉVÉNEMENTS - MySQLi
$stmtEvents = $conn->prepare("
    SELECT e.*, c.NomClub,
    (SELECT COUNT(*) FROM inscri WHERE Idenv = e.Idenv AND present = TRUE) as nbPresents,
    (SELECT COUNT(*) FROM inscri WHERE Idenv = e.Idenv) as nbInscrits,
    (SELECT COUNT(*) FROM Attestation WHERE Idenv = e.Idenv) as nbAttestations
    FROM Evenement e
    LEFT JOIN Club c ON e.idClub = c.idClub
    ORDER BY e.dateDebut DESC
");
$stmtEvents->execute();
$resultEvents = $stmtEvents->get_result();
$events = $resultEvents->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Attestations - UniClub</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="navbar.css">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: #f5f5f9;
            color: #333;
        }

        .navbar {
            background: #fff;
            padding: 18px 40px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 30px;
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

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 40px 40px 40px;
        }

        .page-header {
            margin-bottom: 40px;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
        }

        .page-description {
            color: #888;
            font-size: 1rem;
        }

        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #6ee7b7;
        }

        .alert-warning {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fcd34d;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }

        .events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 28px;
        }

        .event-card {
            background: #fff;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .event-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        }

        .event-header {
            padding: 28px;
            background: linear-gradient(135deg, #f9f7ff, #fff);
            border-bottom: 2px solid #f0f0f0;
        }

        .event-name {
            font-size: 1.3rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
        }

        .event-club {
            color: #8b5cf6;
            font-size: 0.95rem;
            font-weight: 500;
            margin-bottom: 8px;
        }

        .event-date {
            color: #666;
            font-size: 0.9rem;
        }

        .event-body {
            padding: 24px 28px 28px 28px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-bottom: 24px;
        }

        .stat-box {
            text-align: center;
            padding: 16px 12px;
            background: #f9f7ff;
            border-radius: 12px;
            border: 1px solid #ece6fa;
        }

        .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            color: #8b5cf6;
            display: block;
            line-height: 1;
        }

        .stat-label {
            font-size: 0.75rem;
            color: #666;
            margin-top: 6px;
            display: block;
        }

        .badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            margin-bottom: 20px;
        }

        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-info {
            background: #dbeafe;
            color: #1e40af;
        }

        .btn {
            width: 100%;
            padding: 14px 20px;
            border-radius: 12px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-primary {
            background: linear-gradient(135deg, #8b5cf6, #a78bfa);
            color: #fff;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #7c3aed, #8b5cf6);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);
        }

        .btn-primary:disabled {
            background: #d1d5db;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .btn-primary:disabled:hover {
            transform: none;
            box-shadow: none;
        }

        .empty-state {
            text-align: center;
            padding: 80px 20px;
            background: #fff;
            border-radius: 18px;
            color: #888;
        }

        .empty-icon {
            font-size: 5rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            color: #333;
            margin-bottom: 10px;
        }

        @media (max-width: 768px) {
            .container {
                padding: 0 20px 40px 20px;
            }

            .navbar {
                padding: 18px 20px;
            }

            .events-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
            }
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

    </style>
</head>
<body>
     <div class="navbar">
  <div class="logo-section">
    <a href="dashboard_organisateur.php" class="logo-container">
      <div class="logo-icon">
        <i class="bi bi-people-fill"></i>
      </div>
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
   <div class="navbar">
    <a href="dashboard_organisateur.php" class="logo">
        <div class="logo-icon"><i class="bi bi-people-fill"></i></div>
        <span>UniClub</span>
    </a>
    
    <a href="dashboard_organisateur.php" style="color: #8b5cf6; text-decoration: none; font-weight: 500;">
        <i class="bi bi-arrow-left"></i> Retour au tableau de bord
    </a>
</div>

    <div class="container">
        <div class="page-header">
            <h1 class="page-title"><i class="bi bi-file-earmark-text"></i> Gestion des Attestations</h1>
            <p class="page-description">Générez et envoyez automatiquement les attestations aux participants présents</p>
        </div>

        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-<?= $_SESSION['message_type'] ?>">
                <span>
                    <?php if ($_SESSION['message_type'] === 'success'): ?><i class="bi bi-check-circle-fill"></i><?php endif; ?>
                    <?php if ($_SESSION['message_type'] === 'warning'): ?><i class="bi bi-exclamation-triangle-fill"></i><?php endif; ?>
                    <?php if ($_SESSION['message_type'] === 'error'): ?><i class="bi bi-x-circle-fill"></i><?php endif; ?>
                </span>
                <span><?= htmlspecialchars($_SESSION['message']) ?></span>
                <?php if (isset($_SESSION['error_detail'])): ?>
                    <br><small style="margin-top: 8px; display: block;">Détail: <?= htmlspecialchars($_SESSION['error_detail']) ?></small>
                <?php endif; ?>
            </div>
            <?php 
            unset($_SESSION['message'], $_SESSION['message_type'], $_SESSION['error_detail']); 
            ?>
        <?php endif; ?>

        <?php if (!empty($events)): ?>
            <div class="events-grid">
                <?php foreach ($events as $event): 
                    $eventPassed = strtotime($event['dateFin']) < time();
                    $canGenerate = $eventPassed && $event['nbPresents'] > 0;
                ?>
                <div class="event-card">
                    <div class="event-header">
                        <div class="event-name"><?= htmlspecialchars($event['NomEnv']) ?></div>
                        <?php if ($event['NomClub']): ?>
                            <div class="event-club"><i class="bi bi-building"></i> <?= htmlspecialchars($event['NomClub']) ?></div>
                        <?php endif; ?>
                        <div class="event-date">
                            <i class="bi bi-calendar3"></i> <?= date('d/m/Y', strtotime($event['dateDebut'])) ?>
                            <?php if ($event['dateFin'] !== $event['dateDebut']): ?>
                                - <?= date('d/m/Y', strtotime($event['dateFin'])) ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="event-body">
                        <?php if ($event['nbAttestations'] > 0): ?>
                            <span class="badge badge-success">
                                <i class="bi bi-check-circle"></i> <?= $event['nbAttestations'] ?> attestation(s) envoyée(s)
                            </span>
                        <?php elseif (!$eventPassed): ?>
                            <span class="badge badge-info"><i class="bi bi-clock"></i> Événement en cours</span>
                        <?php elseif ($event['nbPresents'] == 0): ?>
                            <span class="badge badge-warning"><i class="bi bi-exclamation-triangle"></i> Aucun participant présent</span>
                        <?php else: ?>
                            <span class="badge badge-warning"><i class="bi bi-hourglass-split"></i> Prêt à générer</span>
                        <?php endif; ?>

                        <div class="stats-grid">
                            <div class="stat-box">
                                <span class="stat-number"><?= $event['nbInscrits'] ?></span>
                                <span class="stat-label">Inscrits</span>
                            </div>
                            <div class="stat-box">
                                <span class="stat-number"><?= $event['nbPresents'] ?></span>
                                <span class="stat-label">Présents</span>
                            </div>
                            <div class="stat-box">
                                <span class="stat-number"><?= $event['nbAttestations'] ?></span>
                                <span class="stat-label">Générées</span>
                            </div>
                        </div>

                        <?php if ($canGenerate): ?>
                            <form method="post" onsubmit="return confirm('Générer et envoyer <?= $event['nbPresents'] ?> attestation(s) par email ?');">
                                <input type="hidden" name="action" value="generate_send">
                                <input type="hidden" name="event_id" value="<?= $event['Idenv'] ?>">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-file-earmark-pdf"></i> Générer & Envoyer les attestations
                                </button>
                            </form>
                        <?php else: ?>
                            <button class="btn btn-primary" disabled title="<?= !$eventPassed ? 'Événement non terminé' : 'Aucun participant présent' ?>">
                                <i class="bi bi-file-earmark-pdf"></i> Générer & Envoyer les attestations
                            </button>
                            <p style="text-align: center; color: #888; font-size: 0.85rem; margin-top: 10px;">
                                <?php if (!$eventPassed): ?>
                                    <i class="bi bi-clock-history"></i> Disponible après la fin de l'événement
                                <?php else: ?>
                                    <i class="bi bi-exclamation-circle"></i> Aucun participant marqué comme présent
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-calendar-x"></i></div>
                <h3>Aucun événement trouvé</h3>
                <p style="margin: 10px 0 20px 0;">Créez d'abord des événements pour pouvoir gérer les attestations</p>
                <a href="formevent.php" class="btn btn-primary" style="max-width: 250px; margin: 10px auto;">
                    <i class="bi bi-plus-circle"></i> Créer un événement
                </a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>