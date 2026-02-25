<?php
session_start();
include '../ConnDB.php';

if (!isset($_SESSION['idCompte']) || $_SESSION['role'] !== 'organisateur') {
    header('Location: login.php');
    exit;
}

$idOrganisateur = $_SESSION['idCompte'];

$stmtOrg = $conn->prepare("SELECT idCompte, Email FROM Compte WHERE idCompte = ? AND role = 'organisateur'");
$stmtOrg->bind_param("i", $idOrganisateur);
$stmtOrg->execute();
$organisateur = $stmtOrg->get_result()->fetch_assoc();

if (!$organisateur) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$emailOrganisateur = $organisateur['Email'];
$nomOrganisateur = ucfirst(explode('@', $emailOrganisateur)[0]);

// Récupérer les événements qui approchent (dans les 7 prochains jours)
$stmtUpcomingNotifs = $conn->prepare("
    SELECT Idenv, NomEnv, dateDebut, Lieu, 
           DATEDIFF(dateDebut, CURDATE()) as joursRestants 
    FROM Evenement 
    WHERE idOrganisateur = ? 
    AND etat = 'publié' 
    AND dateDebut >= CURDATE() 
    AND dateDebut <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ORDER BY dateDebut ASC
");
$stmtUpcomingNotifs->bind_param("i", $idOrganisateur);
$stmtUpcomingNotifs->execute();
$upcomingEvents = $stmtUpcomingNotifs->get_result()->fetch_all(MYSQLI_ASSOC);

// Récupérer les événements récemment validés (dans les 3 derniers jours)
$stmtValidatedEvents = $conn->prepare("
    SELECT Idenv, NomEnv, dateDebut, validation 
    FROM Evenement 
    WHERE idOrganisateur = ? 
    AND validation = 'validé'
    AND etat = 'publié'
    AND dateDebut >= CURDATE()
    ORDER BY dateDebut DESC
    LIMIT 3
");
$stmtValidatedEvents->bind_param("i", $idOrganisateur);
$stmtValidatedEvents->execute();
$validatedEvents = $stmtValidatedEvents->get_result()->fetch_all(MYSQLI_ASSOC);

// Récupérer les événements refusés (non encore consultés)
$stmtRefusedEvents = $conn->prepare("
    SELECT Idenv, NomEnv, validation 
    FROM Evenement 
    WHERE idOrganisateur = ? 
    AND validation = 'refusé'
    ORDER BY Idenv DESC
    LIMIT 3
");
$stmtRefusedEvents->bind_param("i", $idOrganisateur);
$stmtRefusedEvents->execute();
$refusedEvents = $stmtRefusedEvents->get_result()->fetch_all(MYSQLI_ASSOC);

// Récupérer les événements en attente de validation
$stmtPendingEvents = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM Evenement 
    WHERE idOrganisateur = ? 
    AND validation = 'en attente'
    AND etat = 'non publié'
");
$stmtPendingEvents->bind_param("i", $idOrganisateur);
$stmtPendingEvents->execute();
$pendingCount = $stmtPendingEvents->get_result()->fetch_assoc()['count'];

// Récupérer les événements saturés ou presque saturés (>90% capacité)
$stmtCapacityEvents = $conn->prepare("
    SELECT e.Idenv, e.NomEnv, e.Capacite, 
           (SELECT COUNT(*) FROM inscri WHERE Idenv = e.Idenv) as nbInscrits
    FROM Evenement e
    WHERE e.idOrganisateur = ? 
    AND e.etat = 'publié'
    AND e.dateDebut >= CURDATE()
    HAVING (nbInscrits / e.Capacite) >= 0.9
    ORDER BY (nbInscrits / e.Capacite) DESC
    LIMIT 3
");
$stmtCapacityEvents->bind_param("i", $idOrganisateur);
$stmtCapacityEvents->execute();
$capacityEvents = $stmtCapacityEvents->get_result()->fetch_all(MYSQLI_ASSOC);

// Récupérer les inscriptions récentes (dernières 24h)
$stmtRecentInscriptions = $conn->prepare("
    SELECT e.NomEnv, COUNT(i.idParticipant) as newCount
    FROM inscri i
    INNER JOIN Evenement e ON i.Idenv = e.Idenv
    WHERE e.idOrganisateur = ?
    AND i.dateIcription >= DATE_SUB(CURDATE(), INTERVAL 1 DAY)
    GROUP BY e.Idenv, e.NomEnv
    HAVING newCount > 0
    ORDER BY newCount DESC
    LIMIT 3
");
$stmtRecentInscriptions->bind_param("i", $idOrganisateur);
$stmtRecentInscriptions->execute();
$recentInscriptions = $stmtRecentInscriptions->get_result()->fetch_all(MYSQLI_ASSOC);

$stats = ['total_events' => 0, 'total_participants' => 0, 'upcoming_events' => 0];

$stmtTotal = $conn->prepare("SELECT COUNT(*) as total FROM Evenement WHERE idOrganisateur = ? AND etat = 'publié'");
$stmtTotal->bind_param("i", $idOrganisateur);
$stmtTotal->execute();
$stats['total_events'] = $stmtTotal->get_result()->fetch_assoc()['total'];

$stmtParticipants = $conn->prepare("SELECT COUNT(DISTINCT i.idParticipant) as total FROM inscri i INNER JOIN Evenement e ON i.Idenv = e.Idenv WHERE e.idOrganisateur = ? AND e.etat = 'publié'");
$stmtParticipants->bind_param("i", $idOrganisateur);
$stmtParticipants->execute();
$stats['total_participants'] = $stmtParticipants->get_result()->fetch_assoc()['total'];

$stmtUpcoming = $conn->prepare("SELECT COUNT(*) as total FROM Evenement WHERE idOrganisateur = ? AND etat = 'publié' AND dateDebut >= CURDATE()");
$stmtUpcoming->bind_param("i", $idOrganisateur);
$stmtUpcoming->execute();
$stats['upcoming_events'] = $stmtUpcoming->get_result()->fetch_assoc()['total'];

$stmtEvents = $conn->prepare("SELECT e.*, (SELECT COUNT(*) FROM inscri WHERE Idenv = e.Idenv) as nbInscrits, c.NomClub FROM Evenement e LEFT JOIN Club c ON e.idClub = c.idClub WHERE e.idOrganisateur = ? AND e.etat = 'publié' ORDER BY e.dateDebut DESC LIMIT 5");
$stmtEvents->bind_param("i", $idOrganisateur);
$stmtEvents->execute();
$recentEvents = $stmtEvents->get_result()->fetch_all(MYSQLI_ASSOC);

$eventParticipants = [];
if (isset($_GET['event_id'])) {
    $eventId = intval($_GET['event_id']);
    $stmtEventParticipants = $conn->prepare("SELECT p.idCompte, p.Nom, p.Prenom, p.n_Telephone, p.EmailParti, i.dateIcription FROM inscri i INNER JOIN Participant p ON i.idParticipant = p.idCompte WHERE i.Idenv = ? ORDER BY i.dateIcription DESC");
    $stmtEventParticipants->bind_param("i", $eventId);
    $stmtEventParticipants->execute();
    $eventParticipants = $stmtEventParticipants->get_result()->fetch_all(MYSQLI_ASSOC);
}

$eventDetails = null;
if (isset($_GET['detail_id'])) {
    $detailId = intval($_GET['detail_id']);
    $stmtDetails = $conn->prepare("SELECT e.*, c.NomClub, (SELECT COUNT(*) FROM inscri WHERE Idenv = e.Idenv) as nbInscrits FROM Evenement e LEFT JOIN Club c ON e.idClub = c.idClub WHERE e.Idenv = ? AND e.idOrganisateur = ? AND e.etat = 'publié'");
    $stmtDetails->bind_param("ii", $detailId, $idOrganisateur);
    $stmtDetails->execute();
    $eventDetails = $stmtDetails->get_result()->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - UniClub</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #8A2BE2;
            --primary-hover: #7c3aed;
            --white: #ffffff;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: #f5f5f9; color: #333; }
        .navbar { background: var(--white); padding: 18px 40px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .logo-section { display: flex; align-items: center; }
        .logo-container { display: flex; align-items: center; gap: 12px; text-decoration: none; color: #333; }
        .logo-icon { width: 42px; height: 42px; background: linear-gradient(135deg, var(--primary-color), var(--primary-hover)); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: var(--white); font-size: 1.3rem; }
        .logo-text { font-size: 1.4rem; font-weight: 700; }
        .user-avatar { width: 42px; height: 42px; background: linear-gradient(135deg, var(--primary-color), var(--primary-hover)); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--white); font-weight: 600; cursor: pointer; }
        .dropdown { position: absolute; top: 55px; right: 0; background: var(--white); border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,0.12); padding: 8px; min-width: 180px; display: none; z-index: 100; }
        .dropdown.active { display: block; }
        .dropdown a { display: block; padding: 10px 16px; color: #555; text-decoration: none; border-radius: 8px; }
        .dropdown a:hover { background: #f3f0ff; }
        
        /* Notification Button */
        .notification-btn-container { position: relative; margin-right: 20px; }
        .notification-btn { 
            width: 42px; 
            height: 42px; 
            background: #f3f0ff; 
            border: none; 
            border-radius: 50%; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            cursor: pointer; 
            transition: all 0.3s;
            position: relative;
        }
        .notification-btn:hover { background: #e9d5ff; transform: scale(1.05); }
        .notification-btn i { font-size: 1.3rem; color: var(--primary-color); }
        .notification-badge-count {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #ef4444;
            color: white;
            border-radius: 50%;
            width: 22px;
            height: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: 700;
            border: 2px solid white;
        }
        
        /* Notification Panel */
        .notification-panel {
            position: fixed;
            top: 0;
            right: -450px;
            width: 450px;
            height: 100vh;
            background: white;
            box-shadow: -4px 0 24px rgba(0,0,0,0.15);
            z-index: 2000;
            transition: right 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
        }
        .notification-panel.active { right: 0; }
        .notification-panel-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1999;
            display: none;
            opacity: 0;
            transition: opacity 0.3s;
        }
        .notification-panel-overlay.active { display: block; opacity: 1; }
        
        .notification-panel-header {
            padding: 25px;
            border-bottom: 2px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .notification-panel-header h3 {
            font-size: 1.4rem;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .notification-panel-close {
            background: #f3f4f6;
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            cursor: pointer;
            color: #666;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }
        .notification-panel-close:hover { background: #e5e7eb; transform: rotate(90deg); }
        
        .notification-panel-content {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
        }
        
        .notification-item {
            background: white;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 12px;
            border-left: 4px solid;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: all 0.3s;
            cursor: pointer;
        }
        .notification-item:hover { transform: translateX(-4px); box-shadow: 0 4px 12px rgba(0,0,0,0.12); }
        .notification-item.danger { border-left-color: #ef4444; }
        .notification-item.success { border-left-color: #10b981; }
        .notification-item.warning { border-left-color: #f59e0b; }
        .notification-item.info { border-left-color: #3b82f6; }
        .notification-item.purple { border-left-color: #a855f7; }
        
        .notification-item-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 8px;
        }
        .notification-item-icon {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
        }
        .notification-item.danger .notification-item-icon { background: #fee2e2; color: #ef4444; }
        .notification-item.success .notification-item-icon { background: #d1fae5; color: #10b981; }
        .notification-item.warning .notification-item-icon { background: #fef3c7; color: #f59e0b; }
        .notification-item.info .notification-item-icon { background: #dbeafe; color: #3b82f6; }
        .notification-item.purple .notification-item-icon { background: #f3e8ff; color: #a855f7; }
        
        .notification-item-content {
            flex: 1;
            margin-left: 12px;
        }
        .notification-item-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 4px;
            font-size: 0.95rem;
        }
        .notification-item-text {
            color: #666;
            font-size: 0.85rem;
            line-height: 1.4;
        }
        .notification-item-time {
            color: #999;
            font-size: 0.75rem;
            margin-top: 6px;
        }
        .notification-item-delete {
            background: transparent;
            border: none;
            color: #999;
            cursor: pointer;
            padding: 4px;
            border-radius: 6px;
            transition: all 0.3s;
        }
        .notification-item-delete:hover { background: #f3f4f6; color: #ef4444; }
        
        .notification-empty {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        .notification-empty i { font-size: 3rem; margin-bottom: 15px; opacity: 0.3; }
        
        .clear-all-btn {
            margin: 0 20px 20px 20px;
            padding: 12px;
            background: #f3f4f6;
            border: none;
            border-radius: 10px;
            color: #666;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
        }
        .clear-all-btn:hover { background: #e5e7eb; }
        
        /* Notifications Bar */
        .notifications-container { 
            max-width: 1400px; 
            margin: 0 auto; 
            padding: 0 40px; 
            margin-top: 20px;
        }
        .notification-bar {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            border-radius: 16px;
            padding: 20px 24px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: 0 4px 16px rgba(59, 130, 246, 0.2);
            animation: slideInDown 0.5s ease-out;
            position: relative;
            overflow: hidden;
        }
        .notification-bar.danger {
            background: linear-gradient(135deg, #ff6b6b, #ee5a6f);
            box-shadow: 0 4px 16px rgba(255, 107, 107, 0.2);
        }
        .notification-bar.warning {
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            box-shadow: 0 4px 16px rgba(251, 191, 36, 0.2);
        }
        .notification-bar.success {
            background: linear-gradient(135deg, #10b981, #059669);
            box-shadow: 0 4px 16px rgba(16, 185, 129, 0.2);
        }
        .notification-bar.info {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            box-shadow: 0 4px 16px rgba(59, 130, 246, 0.2);
        }
        .notification-bar.purple {
            background: linear-gradient(135deg, #a855f7, #9333ea);
            box-shadow: 0 4px 16px rgba(168, 85, 247, 0.2);
        }
        .notification-bar::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
            animation: shimmer 2s infinite;
        }
        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        @keyframes slideOutUp {
            from {
                opacity: 1;
                transform: translateY(0);
            }
            to {
                opacity: 0;
                transform: translateY(-20px);
            }
        }
        .notification-icon {
            font-size: 2rem;
            color: white;
            flex-shrink: 0;
            animation: pulse 2s infinite;
            z-index: 1;
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        .notification-content {
            flex: 1;
            color: white;
            z-index: 1;
        }
        .notification-title {
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 4px;
        }
        .notification-text {
            font-size: 0.9rem;
            opacity: 0.95;
        }
        .notification-close {
            background: rgba(255,255,255,0.2);
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            color: white;
            cursor: pointer;
            flex-shrink: 0;
            transition: all 0.3s;
            z-index: 1;
        }
        .notification-close:hover {
            background: rgba(255,255,255,0.3);
            transform: rotate(90deg);
        }
        .notification-badge {
            background: rgba(255,255,255,0.3);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            color: white;
            margin-right: 8px;
        }
        
        .container { max-width: 1400px; margin: 0 auto; padding: 40px; }
        .welcome { font-size: 2rem; font-weight: 600; color: #333; margin-bottom: 40px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px; margin-bottom: 50px; }
        .stat-card { background: var(--white); border-radius: 16px; padding: 28px 32px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 2px 12px rgba(0,0,0,0.06); transition: all 0.3s; }
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 8px 24px rgba(0,0,0,0.1); }
        .stat-info h3 { font-size: 2.2rem; font-weight: 700; color: var(--primary-color); margin-bottom: 6px; }
        .stat-info p { font-size: 0.95rem; color: #888; }
        .stat-icon { font-size: 2.5rem; color: var(--primary-hover); }
        .section-title { font-size: 1.3rem; font-weight: 600; color: #555; margin-bottom: 24px; }
        .actions-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 24px; margin-bottom: 50px; }
        .action-card { background: linear-gradient(135deg, var(--primary-color), var(--primary-hover)); border-radius: 16px; padding: 40px 32px; text-align: center; color: var(--white); text-decoration: none; display: block; transition: transform 0.2s; }
        .action-card:hover { transform: translateY(-4px); }
        .action-icon { font-size: 2.5rem; margin-bottom: 16px; }
        .event-card { background: var(--white); border-radius: 16px; padding: 24px; margin-bottom: 18px; display: flex; align-items: center; gap: 24px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); transition: all 0.3s; }
        .event-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,0.1); }
        .event-image { width: 90px; height: 90px; border-radius: 12px; object-fit: cover; background: #f3f0ff; }
        .event-info { flex: 1; }
        .event-title { font-size: 1.15rem; font-weight: 600; color: #333; margin-bottom: 8px; }
        .event-meta { display: flex; gap: 20px; color: #888; font-size: 0.95rem; flex-wrap: wrap; }
        .event-actions { display: flex; gap: 12px; flex-wrap: wrap; }
        .btn { padding: 10px 20px; border-radius: 10px; border: none; cursor: pointer; font-weight: 500; font-size: 0.9rem; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; transition: all 0.3s; }
        .btn-primary { background: var(--primary-color); color: var(--white); }
        .btn-success { background: #10b981; color: var(--white); }
        .btn-info { background: #3b82f6; color: var(--white); }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 1000; padding: 20px; overflow-y: auto; }
        .modal.active { display: flex; align-items: center; justify-content: center; }
        .modal-content { background: var(--white); border-radius: 20px; padding: 35px; max-width: 1000px; width: 100%; max-height: 85vh; overflow-y: auto; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 2px solid #f0f0f0; }
        .modal-header h2 { font-size: 1.6rem; color: var(--primary-color); display: flex; align-items: center; gap: 12px; }
        .modal-close { background: #f3f4f6; border: none; width: 36px; height: 36px; border-radius: 50%; cursor: pointer; color: #666; }
        .participant-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .participant-table th, .participant-table td { padding: 14px 12px; text-align: left; border-bottom: 1px solid #f0f0f0; }
        .participant-table th { background: #f9fafb; font-weight: 600; }
        .detail-section { margin-bottom: 25px; padding: 20px; background: #f9fafb; border-radius: 12px; border-left: 4px solid var(--primary-color); }
        .detail-label { font-weight: 600; color: var(--primary-color); margin-bottom: 8px; }
        .detail-value { color: #333; font-size: 1rem; line-height: 1.6; }
        .detail-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; }
        .no-events { text-align: center; padding: 60px 20px; color: #888; background: var(--white); border-radius: 16px; }
        .empty-participants { text-align: center; padding: 40px 20px; color: #999; }
        @media (max-width: 768px) {
            .container { padding: 20px; }
            .notifications-container { padding: 0 20px; }
            .notification-bar { flex-direction: column; text-align: center; }
            .event-card { flex-direction: column; }
            .event-actions { flex-direction: column; width: 100%; }
            .btn { width: 100%; justify-content: center; }
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
        <div style="display: flex; align-items: center;">
           
            <div class="user-menu" style="position: relative;">
                <div class="user-avatar" onclick="toggleDropdown()"><?= strtoupper(substr($nomOrganisateur, 0, 1)) ?></div>
                <div class="dropdown" id="userDropdown">
                    <a href="#">Mon profil</a>
                    <a href="../logout.php">Se déconnecter</a>
                </div>
            </div>
        </div>
    </div>

   

    <div class="container">
        <h1 class="welcome">Bonjour, <?= htmlspecialchars($nomOrganisateur) ?></h1>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-info"><h3><?= $stats['total_events'] ?></h3><p>Événements publiés</p></div>
                <div class="stat-icon"><i class="bi bi-calendar-event"></i></div>
            </div>
            <div class="stat-card">
                <div class="stat-info"><h3><?= $stats['total_participants'] ?></h3><p>Participants inscrits</p></div>
                <div class="stat-icon"><i class="bi bi-people"></i></div>
            </div>
            <div class="stat-card">
                <div class="stat-info"><h3><?= $stats['upcoming_events'] ?></h3><p>Événements à venir</p></div>
                <div class="stat-icon"><i class="bi bi-calendar-check"></i></div>
            </div>
        </div>

        <div class="actions-section">
            <h2 class="section-title">Actions rapides</h2>
            <div class="actions-grid">
                <a href="formevent.php" class="action-card"><div class="action-icon"><i class="bi bi-plus-circle"></i></div><div class="action-title">Créer événement</div></a>
                <a href="events.php" class="action-card"><div class="action-icon"><i class="bi bi-calendar3"></i></div><div class="action-title">Tous les événements</div></a>
                <a href="send_email.php" class="action-card"><div class="action-icon"><i class="bi bi-envelope"></i></div><div class="action-title">Envoyer email</div></a>
                <a href="attestations.php" class="action-card"><div class="action-icon"><i class="bi bi-file-earmark-text"></i></div><div class="action-title">Attestations</div></a>
            </div>
        </div>

        <div class="events-section">
            <h2 class="section-title">Événements publiés récents</h2>
            <?php if (!empty($recentEvents)): ?>
                <?php foreach ($recentEvents as $event):
                    $img = (!empty($event['photo']) && file_exists($event['photo'])) ? $event['photo'] : 'default.jpg';
                ?>
                <div class="event-card">
                    <img src="<?= htmlspecialchars($img) ?>" class="event-image" alt="Event" onerror="this.src='default.jpg'">
                    <div class="event-info">
                        <div class="event-title"><?= htmlspecialchars($event['NomEnv']) ?></div>
                        <div class="event-meta">
                            <span><i class="bi bi-calendar3"></i> <?= date('d/m/Y', strtotime($event['dateDebut'])) ?></span>
                            <span><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($event['Lieu']) ?></span>
                            <?php if ($event['NomClub']): ?><span><i class="bi bi-building"></i> <?= htmlspecialchars($event['NomClub']) ?></span><?php endif; ?>
                        </div>
                        <div style="color: var(--primary-color); font-size: 0.95rem; font-weight: 500;"><i class="bi bi-people-fill"></i> <?= $event['nbInscrits'] ?>/<?= $event['Capacite'] ?> participants</div>
                    </div>
                    <div class="event-actions">
                        <button class="btn btn-success" onclick="showParticipants(<?= $event['Idenv'] ?>, '<?= htmlspecialchars(addslashes($event['NomEnv'])) ?>')"><i class="bi bi-people"></i> Participants</button>
                        <button class="btn btn-info" onclick="showDetails(<?= $event['Idenv'] ?>)"><i class="bi bi-eye"></i> Détails</button>
                        <a href="events.php" class="btn btn-primary"><i class="bi bi-pencil"></i> Gérer</a>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-events">
                    <div style="font-size: 4rem; margin-bottom: 20px; opacity: 0.5;"><i class="bi bi-calendar-x"></i></div>
                    <h3>Aucun événement publié</h3>
                    <p style="margin: 10px 0 20px 0;">Créez et publiez un événement pour le voir ici</p>
                    <a href="events.php" class="btn btn-primary">Gérer les événements</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="modal" id="participantsModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="bi bi-people-fill"></i> <span id="eventTitle">Participants</span></h2>
                <button class="modal-close" onclick="closeModal('participantsModal')"><i class="bi bi-x-lg"></i></button>
            </div>
            <div id="participantsContent"></div>
        </div>
    </div>

    <div class="modal" id="detailsModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="bi bi-info-circle"></i> Détails de l'événement</h2>
                <button class="modal-close" onclick="closeModal('detailsModal')"><i class="bi bi-x-lg"></i></button>
            </div>
            <div id="detailsContent"></div>
        </div>
    </div>

<script>
        // Système de gestion des notifications
        let notifications = [];

        function toggleDropdown() {
            document.getElementById('userDropdown').classList.toggle('active');
        }

        document.addEventListener('click', function(e) {
            const dropdown = document.getElementById('userDropdown');
            const avatar = document.querySelector('.user-avatar');
            if (!avatar.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.classList.remove('active');
            }
        });

        function toggleNotificationPanel() {
            const panel = document.getElementById('notificationPanel');
            const overlay = document.getElementById('notificationOverlay');
            panel.classList.toggle('active');
            overlay.classList.toggle('active');
            
            if (panel.classList.contains('active')) {
                renderNotifications();
            }
        }

        function addNotificationToStorage(notif) {
            let savedNotifs = JSON.parse(localStorage.getItem('allNotifications') || '[]');
            
            // Éviter les doublons
            const exists = savedNotifs.some(n => n.id === notif.id);
            if (!exists) {
                notif.timestamp = Date.now();
                savedNotifs.unshift(notif);
                
                // Garder seulement les 50 dernières notifications
                if (savedNotifs.length > 50) {
                    savedNotifs = savedNotifs.slice(0, 50);
                }
                
                localStorage.setItem('allNotifications', JSON.stringify(savedNotifs));
                updateNotificationCount();
            }
        }

        function updateNotificationCount() {
            let savedNotifs = JSON.parse(localStorage.getItem('allNotifications') || '[]');
            const count = savedNotifs.length;
            const badge = document.getElementById('notificationCount');
            
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : count;
                badge.style.display = 'flex';
            } else {
                badge.style.display = 'none';
            }
        }

        function renderNotifications() {
            let savedNotifs = JSON.parse(localStorage.getItem('allNotifications') || '[]');
            const container = document.getElementById('notificationPanelContent');
            
            if (savedNotifs.length === 0) {
                container.innerHTML = `
                    <div class="notification-empty">
                        <i class="bi bi-bell-slash"></i>
                        <h3>Aucune notification</h3>
                        <p>Vous êtes à jour !</p>
                    </div>
                `;
                return;
            }
            
            container.innerHTML = savedNotifs.map(notif => {
                const timeAgo = getTimeAgo(notif.timestamp);
                return `
                    <div class="notification-item ${notif.type}">
                        <div class="notification-item-header">
                            <div style="display: flex; align-items: center; flex: 1;">
                                <div class="notification-item-icon">
                                    <i class="bi ${notif.icon}"></i>
                                </div>
                                <div class="notification-item-content">
                                    <div class="notification-item-title">${notif.title}</div>
                                    <div class="notification-item-text">${notif.message}</div>
                                    <div class="notification-item-time">${timeAgo}</div>
                                </div>
                            </div>
                            <button class="notification-item-delete" onclick="deleteNotification('${notif.id}', event)">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                    </div>
                `;
            }).join('');
        }

        function deleteNotification(id, event) {
            event.stopPropagation();
            let savedNotifs = JSON.parse(localStorage.getItem('allNotifications') || '[]');
            savedNotifs = savedNotifs.filter(n => n.id !== id);
            localStorage.setItem('allNotifications', JSON.stringify(savedNotifs));
            renderNotifications();
            updateNotificationCount();
        }

        function clearAllNotifications() {
            if (confirm('Voulez-vous vraiment effacer toutes les notifications ?')) {
                localStorage.setItem('allNotifications', '[]');
                renderNotifications();
                updateNotificationCount();
            }
        }

        function getTimeAgo(timestamp) {
            const seconds = Math.floor((Date.now() - timestamp) / 1000);
            
            if (seconds < 60) return 'À l\'instant';
            if (seconds < 3600) return Math.floor(seconds / 60) + ' min';
            if (seconds < 86400) return Math.floor(seconds / 3600) + ' h';
            if (seconds < 604800) return Math.floor(seconds / 86400) + ' j';
            return new Date(timestamp).toLocaleDateString('fr-FR');
        }

        function closeNotification(notifId) {
            const notif = document.getElementById('notif-' + notifId);
            if (!notif) return;
            
            notif.style.animation = 'slideOutUp 0.3s ease-out';
            setTimeout(() => {
                notif.remove();
                let closedNotifs = JSON.parse(localStorage.getItem('closedNotifications') || '[]');
                if (!closedNotifs.includes(notifId)) {
                    closedNotifs.push(notifId);
                    localStorage.setItem('closedNotifications', JSON.stringify(closedNotifs));
                }
            }, 300);
        }

        // Initialiser les notifications au chargement
        document.addEventListener('DOMContentLoaded', function() {
            // Cacher les notifications déjà fermées
            let closedNotifs = JSON.parse(localStorage.getItem('closedNotifications') || '[]');
            closedNotifs.forEach(id => {
                const notif = document.getElementById('notif-' + id);
                if (notif) notif.remove();
            });
            
            // Nettoyer le localStorage après 7 jours
            const lastCleanup = localStorage.getItem('lastNotifCleanup');
            const now = Date.now();
            if (!lastCleanup || (now - parseInt(lastCleanup)) > 7 * 24 * 60 * 60 * 1000) {
                localStorage.removeItem('closedNotifications');
                localStorage.setItem('lastNotifCleanup', now.toString());
            }

            // Charger les notifications depuis les barres visibles
            const notifBars = document.querySelectorAll('.notification-bar');
            notifBars.forEach(bar => {
                const id = bar.id.replace('notif-', '');
                const type = bar.classList.contains('danger') ? 'danger' : 
                            bar.classList.contains('success') ? 'success' : 
                            bar.classList.contains('warning') ? 'warning' : 
                            bar.classList.contains('purple') ? 'purple' : 'info';
                
                const icon = bar.querySelector('.notification-icon i').className.replace('bi ', '');
                const title = bar.querySelector('.notification-badge')?.textContent || 'Notification';
                const eventName = bar.querySelector('.notification-title').textContent.replace(title, '').trim();
                const message = bar.querySelector('.notification-text').textContent;
                
                addNotificationToStorage({
                    id: id,
                    type: type,
                    icon: icon,
                    title: eventName || title,
                    message: message
                });
            });

            updateNotificationCount();
        });

        function showParticipants(eventId, eventName) {
            window.location.href = '?event_id=' + eventId + '#participantsModal';
        }

        function showDetails(eventId) {
            window.location.href = '?detail_id=' + eventId + '#detailsModal';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
            window.history.replaceState({}, document.title, window.location.pathname);
        }

        document.getElementById('participantsModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal('participantsModal');
        });

        document.getElementById('detailsModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal('detailsModal');
        });

        // Ouvrir automatiquement les modals si les paramètres sont présents
        window.addEventListener('DOMContentLoaded', function() {
            if (window.location.hash === '#participantsModal' || <?= isset($_GET['event_id']) ? 'true' : 'false' ?>) {
                document.getElementById('participantsModal').classList.add('active');
            }
            if (window.location.hash === '#detailsModal' || <?= isset($_GET['detail_id']) ? 'true' : 'false' ?>) {
                document.getElementById('detailsModal').classList.add('active');
            }
        });

        <?php if (isset($_GET['event_id']) && !empty($eventParticipants)): ?>
        document.getElementById('participantsContent').innerHTML = `
            <table class="participant-table">
                <thead><tr><th>Nom</th><th>Prénom</th><th>Téléphone</th><th>Email</th><th>Date inscription</th></tr></thead>
                <tbody>
                    <?php foreach ($eventParticipants as $p): ?>
                    <tr>
                        <td><?= htmlspecialchars($p['Nom']) ?></td>
                        <td><?= htmlspecialchars($p['Prenom']) ?></td>
                        <td><?= htmlspecialchars($p['n_Telephone'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($p['EmailParti']) ?></td>
                        <td><?= $p['dateIcription'] ? date('d/m/Y', strtotime($p['dateIcription'])) : 'N/A' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div style="margin-top: 20px; padding: 15px; background: #f0fdf4; border-radius: 10px; text-align: center;">
                <strong style="color: #059669;">Total: <?= count($eventParticipants) ?> participant(s)</strong>
            </div>
        `;
        document.getElementById('eventTitle').textContent = 'Participants - <?= isset($recentEvents) ? addslashes($recentEvents[0]['NomEnv'] ?? 'Événement') : 'Événement' ?>';
        <?php elseif (isset($_GET['event_id']) && empty($eventParticipants)): ?>
        document.getElementById('participantsContent').innerHTML = `<div class="empty-participants"><i class="bi bi-people"></i><h3>Aucun participant</h3></div>`;
        <?php endif; ?>

        <?php if (isset($_GET['detail_id']) && $eventDetails): ?>
        document.getElementById('detailsContent').innerHTML = `
            <div style="text-align: center; margin-bottom: 30px;">
                <h2 style="color: var(--primary-color); font-size: 1.8rem;"><?= htmlspecialchars($eventDetails['NomEnv']) ?></h2>
                <?php if ($eventDetails['NomClub']): ?><p style="color: #7c3aed;"><?= htmlspecialchars($eventDetails['NomClub']) ?></p><?php endif; ?>
            </div>
            
            <?php if ($eventDetails['photo'] && file_exists($eventDetails['photo'])): ?>
            <img src="<?= htmlspecialchars($eventDetails['photo']) ?>" style="width: 100%; max-width: 400px; border-radius: 12px; margin: 20px auto; display: block;" alt="Image">
            <?php endif; ?>
            
            <div class="detail-section"><div class="detail-label">Type</div><div class="detail-value"><?= htmlspecialchars($eventDetails['Type']) ?></div></div>
            <div class="detail-section"><div class="detail-label">Description</div><div class="detail-value"><?= $eventDetails['discription'] ? htmlspecialchars($eventDetails['discription']) : 'Aucune description' ?></div></div>
            <div class="detail-grid">
                <div class="detail-section"><div class="detail-label">Date début</div><div class="detail-value"><?= date('d/m/Y', strtotime($eventDetails['dateDebut'])) ?></div></div>
                <div class="detail-section"><div class="detail-label">Date fin</div><div class="detail-value"><?= date('d/m/Y', strtotime($eventDetails['dateFin'])) ?></div></div>
            </div>
            <div class="detail-section"><div class="detail-label">Lieu</div><div class="detail-value"><?= htmlspecialchars($eventDetails['Lieu']) ?></div></div>
            <div class="detail-grid">
                <div class="detail-section"><div class="detail-label">Capacité</div><div class="detail-value"><?= $eventDetails['nbInscrits'] ?>/<?= $eventDetails['Capacite'] ?> personnes</div></div>
                <div class="detail-section"><div class="detail-label">Date limite</div><div class="detail-value"><?= date('d/m/Y', strtotime($eventDetails['finInscription'])) ?></div></div>
            </div>
            
            <?php if (isset($eventDetails['prixAdherent']) || isset($eventDetails['prixNonAdherent'])): ?>
            <div class="detail-section">
                <div class="detail-label">Tarification</div>
                <div class="detail-value" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 10px;">
                    <?php if (isset($eventDetails['prixAdherent'])): ?>
                    <div style="background: white; padding: 15px; border-radius: 10px; border: 2px solid #e9d5ff;">
                        <div style="color: #7c3aed; font-size: 0.85rem; margin-bottom: 5px;">Prix adhérent</div>
                        <div style="font-size: 1.4rem; font-weight: 700; color: var(--primary-color);"><?= number_format($eventDetails['prixAdherent'], 2) ?> DH</div>
                    </div>
                    <?php endif; ?>
                    <?php if (isset($eventDetails['prixNonAdherent'])): ?>
                    <div style="background: white; padding: 15px; border-radius: 10px; border: 2px solid #e9d5ff;">
                        <div style="color: #7c3aed; font-size: 0.85rem; margin-bottom: 5px;">Prix non-adhérent</div>
                        <div style="font-size: 1.4rem; font-weight: 700; color: var(--primary-color);"><?= number_format($eventDetails['prixNonAdherent'], 2) ?> DH</div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        `;
        <?php elseif (isset($_GET['detail_id']) && !$eventDetails): ?>
        document.getElementById('detailsContent').innerHTML = `<div class="empty-participants"><i class="bi bi-exclamation-triangle"></i><h3>Événement introuvable</h3></div>`;
        <?php endif; ?>
    </script>
</body>
</html>