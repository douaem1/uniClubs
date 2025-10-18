<?php
    session_start();
    if (!isset($_SESSION['idCompte'])) {
        header('Location: ../login.php');
        exit();
    }
    
    if (isset($_SESSION['prenom'])) {
        $nomUtilisateur = $_SESSION['prenom'];
    } else {
        echo "Utilisateur";
    }
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>UniClub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/style/header.css">
</head>
<body>
  <header class="main-header">
    <div class="header-container">
      <!-- Logo Section -->
      <div class="logo-section">
        <a href="Evenements.php" class="logo-container">
          <div class="logo-icon">
            <i class="bi bi-people-fill"></i>
          </div>
          <span class="logo-text">UniClub</span>
        </a>
      </div>

      <!-- Navigation Section -->
      <nav class="nav-section">
        <a href="Evenements.php" class="nav-link ">Événements</a>
        <a href="mesInscription.php" class="nav-link ">Mes inscriptions</a>
        <!-- User Section -->
        <div class="user-section">
          <a href="profile.php" class="user-profile">
              <div class="user-avatar">
                <i class="bi bi-person-fill"></i>
              </div>
              <span class="user-name"><?= htmlspecialchars($nomUtilisateur) ?></span>
          </a>
          <a href="../logout.php" class="logout-btn">
            <i class="bi bi-box-arrow-right"></i>
          </a>
        </div>
      </nav>      
    </div>
  </header>
