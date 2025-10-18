<?php
    session_start();
    include 'ConnDB.php';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);
        $recaptchaResponse = $_POST['g-recaptcha-response'];
        $secretKey = "6LdHhuQrAAAAAFDFflYcxPYgQSS7w-UwqaZUUanI";

        // Vérifier le captcha
        $response = file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret=$secretKey&response=$recaptchaResponse");
        $responseData = json_decode($response);

        // Vérifier si les champs sont remplis
        if (empty($email) || empty($password)) {
            $error = "Veuillez remplir tous les champs.";
        } else {
            if(!$responseData->success) {
                $error = "Captcha invalide! Veuillez réessayer";
            } else {
                // Vérifier si l'utilisateur existe
                $sql = "SELECT idCompte, Password, role FROM Compte WHERE Email = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows === 1) {
                    $row = $result->fetch_assoc();

                    // Vérification du mot de passe (ici on suppose qu'il est hashé)
                    if (password_verify($password, $row['Password'])) {
                        // Démarrer la session et enregistrer les infos de l'utilisateur
                        $_SESSION['idCompte'] = $row['idCompte'];
                        $_SESSION['email'] = $email;
                        $_SESSION['role'] = $row['role'];

                        // Si le rôle est "participant"
                        if ($row['role'] === 'participant') {
                            $stmt2 = $conn->prepare("SELECT Nom, Prenom, n_Telephone, Filiere, EstExterne FROM participant WHERE idCompte = ?");
                            $stmt2->bind_param("i", $row['idCompte']);
                            $stmt2->execute();
                            $res2 = $stmt2->get_result();

                            if ($res2->num_rows === 1) {
                                $data = $res2->fetch_assoc();
                                $_SESSION['nom'] = $data['Nom'];
                                $_SESSION['prenom'] = $data['Prenom'];
                                $_SESSION['tele'] = $data['n_Telephone'];
                                $_SESSION['filiere'] = $data['Filiere'];
                                $_SESSION['externe'] = $data['EstExterne'];
                            }
                            $stmt2->close();
                        }

                        // Rediriger selon le rôle ou vers la page d'accueil
                        if ($row['role'] === 'participant') {
                            header('Location: participant/Evenements.php');
                        } elseif ($row['role'] === 'organisateur') {
                            header('Location: organisateur/dashboard_organisateur.php');
                        } elseif ($row['role'] === 'coordinateur') {
                            header('Location: coordinateur/pages/dashboard.php');
                        } else {
                            header('Location: login.php');
                        }
                        exit;
                    } else {
                        $error = "Mot de passe incorrect";
                    }
                } else {
                    $error = "Aucun compte trouvé avec cet email";
                }

            }
        }
    }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Connexion</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <link rel="stylesheet" href="participant/assets/style/login.css">
</head>
<body class="bg-light d-flex align-items-center" style="height: 100vh;">
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow border-0">
                <div class="card-body">
                    <h4 class="text-center mb-4">Connexion</h4>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="mb-3">
                            <label>Email</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label>Mot de passe</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>

                        <div class="g-recaptcha" data-sitekey="6LdHhuQrAAAAAN_Jx1i6sic0N2KxGmrz3J1yyRAL"></div>

                        <button type="submit" class="btn btn-primary w-100">Se connecter</button>
                        <div class="text-center mt-3">
                            <a href="forgotPassword.php">Mot de passe oublié ?</a>
                        </div>
                    </form>

                    <div class="text-center mt-2">
                        <a href="regester.php">Créer un compte</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
