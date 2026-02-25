<?php
session_start();
include 'ConnDB.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $telephone = trim($_POST['telephone'] ?? '');
    $filiere = trim($_POST['filiere'] ?? '');
    if (isset($_POST['externe'])) {
        $is_externe = 1;
    } else {
        $is_externe = 0;
    }
    $recaptchaResponse = $_POST['g-recaptcha-response'];
    $secretKey = "6LdHhuQrAAAAAFDFflYcxPYgQSS7w-UwqaZUUanI";

    // Vérifier le captcha
    $response = file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret=$secretKey&response=$recaptchaResponse");
    $responseData = json_decode($response);

    //Vérification filière vs externe
    if (!empty($filiere) && $is_externe == 1) {
        $error = "Vous ne pouvez pas remplir la filière et cocher externe en même temps!";
    } elseif(empty($email) || empty($password)) {
        $error = "Veuillez remplir tous les champs";
    } else {
        if ($nom === '' || $prenom === '' || $email === '' || $password === '') {
            $error = "Veuillez remplir tous les champs obligatoires";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Format d'email invalide";
        } elseif (strlen($password) < 4) {
            $error = "Le mot de passe doit contenir au moins 4 caractères";
        } else {
            // Vérifier que l'email n'existe pas déjà dans Compte
            $check = $conn->prepare("SELECT idCompte FROM compte WHERE Email = ?");
            $check->bind_param("s", $email);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {
                $error = "Un compte existe déjà avec cet email";
                $check->free_result();
                $check->close();
            } else {
                $check->free_result();
                $check->close();

                // Insérer dans Compte
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $role = 'participant'; // par défaut : participant

                $stmtComp = $conn->prepare("INSERT INTO compte (Email, Password, role) VALUES (?, ?, ?)");
                if (!$stmtComp) {
                    $error = "Erreur préparation (Compte): " . $conn->error;
                } else {
                    $stmtComp->bind_param("sss", $email, $hashedPassword, $role);
                    if (!$stmtComp->execute()) {
                        $error = "Erreur insertion Compte : " . $stmtComp->error;
                        $stmtComp->close();
                    } else {
                        $newIdCompte = $conn->insert_id;
                        $stmtComp->close();

                        // Insérer dans Participant
                        $stmtPart = $conn->prepare("INSERT INTO participant (idCompte, Nom, Prenom, n_Telephone, EmailParti, Filiere, EstExterne) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        if (!$stmtPart) {
                            $error = "Erreur préparation (Participant): " . $conn->error;
                        } else {
                            $stmtPart->bind_param("isssssi", $newIdCompte, $nom, $prenom, $telephone, $email, $filiere, $is_externe);
                            if (!$stmtPart->execute()) {
                                // Si insertion participant échoue, supprimer le compte créé pour garder l'intégrité
                                $stmtPart->close();
                                $conn->query("DELETE FROM Compte WHERE idCompte = " . intval($newIdCompte));
                                $error = "Erreur insertion Participant : " . $conn->error;
                            } else {
                                $newIdParticipant = $conn->insert_id;
                                $stmtPart->close();
                                // Inscription réussie : on peut connecter l'utilisateur automatiquement
                                $_SESSION['idCompte'] = $newIdCompte;
                                $_SESSION['idParticipant'] = $newIdParticipant;
                                $_SESSION['email'] = $email;
                                $_SESSION['role'] = $role;
                                $_SESSION['nom'] = $nom;
                                $_SESSION['prenom'] = $prenom;
                                $_SESSION['tele'] = $telephone;
                                $_SESSION['filiere'] = $filiere;
                                $_SESSION['externe'] = $is_externe;

                                $success = "Inscription réussie. Vous êtes connecté";
                                // Redirection vers mes inscriptions ou autre page
                                header("Location: participant/Evenements.php");
                                exit;
                            }
                        }
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Inscription - UniClubs</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <link rel="stylesheet" href="participant/assets/style/login.css">
</head>
<body class="d-flex align-items-center justify-content-center" style="min-height:100vh;">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card shadow-sm p-4">
                    <h4 class="mb-3 text-center">Créer un compte</h4>

                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="nom" class="form-label">Nom <span class="required">*</span></label>
                            <input id="nom" name="nom" type="text" class="form-control" required value="<?php echo htmlspecialchars($_POST['nom'] ?? ''); ?>">
                        </div>

                        <div class="mb-3">
                            <label for="prenom" class="form-label">Prénom <span class="required">*</span></label>
                            <input id="prenom" name="prenom" type="text" class="form-control" required value="<?php echo htmlspecialchars($_POST['prenom'] ?? ''); ?>">
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email <span class="required">*</span></label>
                            <input id="email" name="email" type="email" class="form-control" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label for="Telephone" class="form-label">Numéro de téléphone <span class="required">*</span></label>
                            <input type="tel" name="telephone" id="Telephone" class="form-control" placeholder="Votre numéro de téléphone" required value="<?php echo htmlspecialchars($_POST['telephone'] ?? ''); ?>">
                        </div>

                        <div class="mb-3">
                            <label for="filiere" class="form-label">Filière</label>
                            <input id="filiere" name="filiere" type="text" class="form-control" placeholder="filère" value="<?php echo htmlspecialchars($_POST['filiere'] ?? ''); ?>">
                        </div>

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="externe" name="externe" <?php echo isset($_POST['externe']) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="externe">Je suis un participant externe</label>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Mot de passe <span class="required">*</span></label>
                            <input id="password" name="password" type="password" class="form-control" required>
                            <div class="form-text">Au moins 4 caractères recommandés</div>
                        </div>

                        <div class="g-recaptcha" data-sitekey="6LdHhuQrAAAAAN_Jx1i6sic0N2KxGmrz3J1yyRAL"></div>

                        <button type="submit" class="btn btn-primary w-100">S'enregistrer</button>
                    </form>

                    <div class="mt-3 text-center">
                        <small>Vous avez déjà un compte ? <a href="login.php">Se connecter</a></small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
