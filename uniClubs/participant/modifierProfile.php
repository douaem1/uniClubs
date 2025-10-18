<?php
    include '../ConnDB.php';
    include 'header.php';

    $idCompte = $_SESSION['idCompte'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $nom = trim($_POST['nom']);
        $prenom = trim($_POST['prenom']);
        $telephone = trim($_POST['telephone']);
        $email = trim($_POST['email']);
        if (isset($_POST['filiere'])) {
            $filiere = trim($_POST['filiere']);
        } else {
            $filiere = null;
        }
        if (isset($_POST['externe'])) {
            $is_externe = 1;
        } else {
            $is_externe = 0;
        }
        $password = trim($_POST['password']);

        $stmtPwd = $conn->prepare("SELECT Password FROM Compte WHERE idCompte = ?");
        $stmtPwd->bind_param("i", $idCompte);
        $stmtPwd->execute();
        $resultPwd = $stmtPwd->get_result()->fetch_assoc();

        if (!$resultPwd || !password_verify($password, $resultPwd['Password'])) {
            // mot de passe incorrect
            $_SESSION['error_message'] = "Mot de passe incorrect! Modification refusée.";
            header('Location: modifierProfile.php');
            exit;
        }

        //Vérification filière vs externe
        if (!empty($filiere) && $is_externe == 1) {
            $_SESSION['error_message'] = "Vous ne pouvez pas remplir la filière et cocher externe en même temps!";
            header('Location: modifierProfile.php');
            exit;
        }

        // Vérifier que l'email n'est pas déjà utilisé par un autre compte
        $check = $conn->prepare("SELECT idCompte FROM compte WHERE Email = ? AND idCompte != ?");
        $check->bind_param("si", $email, $idCompte);
        $check->execute();
        $checkResult = $check->get_result();

        if ($checkResult->num_rows > 0) {
            $_SESSION['error_message'] = "Cet email est déjà utilisé";
            header('Location: modifierProfile.php');
            exit;
        }

        // Update Compte
        $stmtCompte = $conn->prepare("UPDATE compte SET Email = ? WHERE idCompte = ?");
        $stmtCompte->bind_param("si", $email, $idCompte);
        $stmtCompte->execute();

        // Update Participant
        $stmtPart = $conn->prepare("UPDATE participant SET Nom = ?, Prenom = ?, n_Telephone = ?, EmailParti = ?, Filiere = ?, EstExterne = ? WHERE idCompte = ?");
        $stmtPart->bind_param("sssssii", $nom, $prenom, $telephone, $email, $filiere, $is_externe, $idCompte);
        $stmtPart->execute();

        // Mettre à jour les sessions aussi si nécessaire
        $_SESSION['nom'] = $nom;
        header('Location: profile.php');
        exit;
    }

    // Charger les infos actuelles
    $stmt = $conn->prepare("SELECT Nom, Prenom, n_Telephone, EmailParti, Filiere, EstExterne FROM participant WHERE idCompte = ?");
    $stmt->bind_param("i", $idCompte);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
?>

<link rel="stylesheet" href="assets/style/profile.css">
<body class="bg-light">
<div class="profile-container">
    <h2>Modifier mon profil</h2>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger mt-3">
            <?php 
                echo $_SESSION['error_message']; 
                unset($_SESSION['error_message']); 
            ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <label for="nom">Nom</label>
            <input type="text" name="nom" class="form-control" id="nom" value="<?php echo htmlspecialchars($user['Nom']); ?>" required>
        </div>
        <div class="mb-3">
            <label for="prenom">Prénom</label>
            <input type="text" name="prenom" class="form-control" id="prenom" value="<?php echo htmlspecialchars($user['Prenom']); ?>" required>
        </div>
        <div class="mb-3">
            <label for="tel">Téléphone</label>
            <input type="tel" name="telephone" class="form-control" id="tel" value="<?php echo htmlspecialchars($user['n_Telephone']); ?>">
        </div>
        <div class="mb-3">
            <label for="email">Email</label>
            <input type="email" name="email" class="form-control" id="email" value="<?php echo htmlspecialchars($user['EmailParti']); ?>" required>
        </div>
        <div class="mb-3">
    <label for="filiere">Filière</label>
        <input type="text" name="filiere" class="form-control" id="filiere" value="<?php echo htmlspecialchars($user['Filiere'] ?? ''); ?>">
    </div>

    <div class="form-check mb-3">
        <input type="checkbox" name="externe" class="form-check-input" id="externe"
            <?php if (!empty($user['EstExterne']) && $user['EstExterne'] == 1) echo 'checked'; ?>>
        <label class="form-check-label" for="externe">Je suis un participant externe</label>
    </div>

        <div class="btn-container mt-3">
            <button type="button" class="btn btn-primary" onclick="openDeletePopup()">Enregistrer</button>
            <a href="profile.php" class="btn btn-secondary">Annuler</a>
        </div>

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
                        <button type="submit" name="confirmDelete" class="btn btn-danger">Enregistrer</button>
                    </div>
                    <div class="text-center mt-3">
                        <a href="../forgotPassword.php">Mot de passe oublié ?</a>
                    </div>
                </form>
            </div>
        </div>
    </form>
</div>
<script src="assets/js/profile.js"></script>
</body>
</html>
