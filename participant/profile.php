<?php
    include '../ConnDB.php';
    include 'header.php';

    $idCompte = $_SESSION['idCompte'];

    // Récupérer les infos de l'utilisateur
    $sql = "SELECT p.Nom, p.Prenom, p.EmailParti, p.n_Telephone, p.Filiere, p.EstExterne 
            FROM participant p
            INNER JOIN compte c ON p.idCompte = c.idCompte
            WHERE c.idCompte = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $idCompte);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
    } else {
        die("Utilisateur introuvable");
    }   
?>

<link rel="stylesheet" href="assets/style/profile.css">
<body class="bg-light">
<div class="profile-container">
    
    <div class="greeting-wrap d-flex align-items-center justify-content-between mb-3">
        <div class="d-flex align-items-center gap-2">
            <h2 class="greeting-title fw-bold">Bonjour dans votre espace, <span class="greeting-name"><?php echo htmlspecialchars($_SESSION['prenom']); ?></span></h2>
        </div>
    </div>
    <div class="profile-item">
        <span class="profile-label">Nom :</span> <?php echo htmlspecialchars($user['Nom']); ?>
    </div>
    <div class="profile-item">
        <span class="profile-label">Prénom :</span> <?php echo htmlspecialchars($user['Prenom']); ?>
    </div>
    <div class="profile-item">
        <span class="profile-label">Email :</span> <?php echo htmlspecialchars($user['EmailParti']); ?>
    </div>
    <div class="profile-item">
        <span class="profile-label">Téléphone :</span> <?php echo htmlspecialchars($user['n_Telephone']); ?>
    </div>

    <?php if (!empty($user['Filiere'])): ?>
        <div class="profile-item">
            <span class="profile-label">Filière :</span> <?php echo htmlspecialchars($user['Filiere']); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($user['EstExterne'])): ?>
        <div class="profile-item">
            <span class="profile-label">Externe :</span> Oui
        </div>
    <?php endif; ?>

    <div class="btn-container mt-4">
        <a href="modifierProfile.php" class="btn btn-primary">
            <i class="bi bi-pencil-square"></i>
        </a>
        <button type="button" class="btn btn-danger" onclick="openDeletePopup()"><i class="bi bi-trash"></i></button>
        <!-- Popup de confirmation -->
        <!-- Popup de mot de passe (masquée par défaut) -->
        <div id="delete-popup" class="popup" aria-hidden="true">
            <div class="popup-content">
                <p>Entrez votre mot de passe</p>
                <!-- Formulaire envoyé au PHP pour traitement -->
                <form action="supprimerProfile.php" method="POST">
                <input type="password" name="password" placeholder="Mot de passe" required class="form-control mb-3">
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-secondary" onclick="closeDeletePopup()">Annuler</button>
                        <button type="submit" name="confirmDelete" class="btn btn-danger">Supprimer</button>
                    </div>
                    <div class="text-center mt-3">
                        <a href="../forgotPassword.php">Mot de passe oublié ?</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<script src="assets/js/profile.js"></script>
</body>
</html>