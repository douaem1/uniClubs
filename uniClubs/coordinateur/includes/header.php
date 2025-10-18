<?php
require_once '../config/session.php';
// requireCoordinateur();

$currentUser = getCurrentUser();
$flashMessage = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="fr" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'Coordinateur'; ?> - UniClub</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="../assets/images/logo-icon.svg">
    
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/coordinateur.css">
    <link rel="stylesheet" href="../assets/css/events.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    
    <!-- Custom Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="d-flex flex-column min-vh-100">
    <!-- Toast Notification -->
    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1090">
        <div id="liveToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header bg-primary text-white">
                <strong class="me-auto">Notification</strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body">
                <?php if ($flashMessage): ?>
                    <div class="alert alert-<?php echo $flashMessage['type']; ?> mb-0">
                        <i class="bi <?php 
                            echo $flashMessage['type'] === 'success' ? 'bi-check-circle-fill' : 
                                 ($flashMessage['type'] === 'error' ? 'bi-x-circle-fill' : 'bi-info-circle-fill'); 
                        ?> me-2"></i>
                        <?php echo htmlspecialchars($flashMessage['message']); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="position-fixed top-0 start-0 w-100 h-100 bg-white bg-opacity-75 d-flex justify-content-center align-items-center" style="z-index: 9999; display: none !important;">
        <div class="text-center">
            <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                <span class="visually-hidden">Chargement...</span>
            </div>
            <p class="mt-3 text-muted">Veuillez patienter...</p>
        </div>
    </div>

    <!-- Top Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm" style="border-color: #E5E7EB !important;">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="dashboard.php" style="color: #7C3AED;">
                <i class="bi bi-people-fill me-2"></i>UniClubs
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active fw-bold' : ''; ?>" href="dashboard.php">
                            <i class="bi bi-speedometer2 me-1"></i>Tableau de bord
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'clubs.php' ? 'active fw-bold' : ''; ?>" href="clubs.php">
                            <i class="bi bi-people me-1"></i>Clubs
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'events.php' ? 'active fw-bold' : ''; ?>" href="events.php">
                            <i class="bi bi-calendar-event me-1"></i>Événements
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'email.php' ? 'active fw-bold' : ''; ?>" href="email.php">
                            <i class="bi bi-envelope me-1"></i>Email
                        </a>
                    </li>
                </ul>
                <div class="d-flex align-items-center gap-3">
                    <!-- Profil utilisateur avec email -->
                    <div class="d-flex align-items-center gap-2 px-3 py-2 rounded" style="background-color: #F3F4F6;">
                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 35px; height: 35px;">
                            <i class="bi bi-person-fill"></i>
                        </div>
                        <div class="d-none d-lg-block">
                            <div class="text-muted small">Coordinateur des clubs</div>
                            <div class="fw-semibold" style="font-size: 0.9rem;">
                                <?php echo htmlspecialchars($currentUser['email'] ?? 'Non défini'); ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Bouton de déconnexion -->
                    <a href="../../logout.php" class="btn btn-outline-danger btn-sm" title="Déconnexion">
                        <i class="bi bi-box-arrow-right me-1"></i>Déconnexion
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="flex-grow-1 py-4">
        <div class="container-fluid px-4">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0 fw-bold text-dark"><?php echo $pageTitle ?? 'Tableau de bord'; ?></h2>
            </div>
            
            <!-- Alerts Container -->
            <div class="alerts-container"></div>
            
            <!-- Main Content Area -->
            <div class="content-area">

    <!-- Bootstrap 5.3 JS Bundle (avec Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Script pour afficher le toast si message flash présent -->
    <script>
        <?php if ($flashMessage): ?>
        document.addEventListener('DOMContentLoaded', function() {
            var toastEl = document.getElementById('liveToast');
            var toast = new bootstrap.Toast(toastEl);
            toast.show();
        });
        <?php endif; ?>
    </script>
</body>
</html>