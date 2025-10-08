<?php
require_once '../config/session.php';
requireCoordinateur();

$currentUser = getCurrentUser();
$flashMessage = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'Coordinateur'; ?> - UniClub</title>
    <link rel="stylesheet" href="../assets/css/coordinateur.css">
</head>
<body>
    <div class="layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>🎓 UniClub</h2>
                <p>Coordinateur</p>
            </div>
            
            <nav class="sidebar-nav">
                <a href="dashboard.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">
                    <span class="icon">📊</span>
                    Tableau de bord
                </a>
                <a href="clubs.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'clubs.php' ? 'active' : ''; ?>">
                    <span class="icon">👥</span>
                    Gestion des clubs
                </a>
                <a href="evenements.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'evenements.php' ? 'active' : ''; ?>">
                    <span class="icon">📅</span>
                    Événements
                </a>
                <a href="send_email.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'send_email.php' ? 'active' : ''; ?>">
                    <span class="icon">✉️</span>
                    Envoyer email
                </a>
            </nav>
            
            <div class="sidebar-footer">
                <div class="user-info">
                    <p><strong><?php echo htmlspecialchars($currentUser['prenom'] . ' ' . $currentUser['nom']); ?></strong></p>
                    <p class="email"><?php echo htmlspecialchars($currentUser['email']); ?></p>
                </div>
                <a href="../logout.php" class="btn-logout">Déconnexion</a>
            </div>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <header class="top-bar">
                <h1><?php echo $pageTitle ?? 'Tableau de bord'; ?></h1>
            </header>
            
            <!-- Messages flash -->
            <?php if ($flashMessage): ?>
            <div class="alert alert-<?php echo $flashMessage['type']; ?>">
                <?php echo htmlspecialchars($flashMessage['message']); ?>
            </div>
            <?php endif; ?>
            
            <div class="content">