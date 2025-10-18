<?php
    session_start();
    include 'ConnDB.php';
    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\Exception;

    require 'vendor/autoload.php';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = trim($_POST['email']);

        // Vérifier si l’email existe
        $stmt = $conn->prepare("SELECT idCompte FROM compte WHERE Email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $token = bin2hex(random_bytes(50)); //generé token unique
            $expire = date('Y-m-d H:i:s', strtotime('+15 minutes')); // cela veut dire va expriré une 15min aprés creation

            // Insérer dans la table passwordresets
            $stmt2 = $conn->prepare("INSERT INTO passwordresets (idCompte, token, expiration) VALUES (?, ?, ?)");
            $stmt2->bind_param("iss", $row['idCompte'], $token, $expire);
            $stmt2->execute();

            // Envoyer l’email
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username   = 'douae.moeniss@gmail.com';
                $mail->Password   = 'qcdz cepv epis zggv';
                $mail->SMTPSecure = 'tls';
                $mail->SMTPDebug = 0;
                $mail->Port = 587;

                $mail->setFrom('douae.moeniss@gmail.com');
                $mail->addAddress($email);
                $mail->isHTML(true);

                $resetLink = "http://localhost:3000/uniClubs/resetPassword.php?token=$token";
                
                $mail->Subject = 'Reinitialisation de votre mot de passe';
                $mail->Body = "Cliquez sur ce lien pour réinitialiser votre mot de passe : 
                <a href='$resetLink'>Réinitialiser le mot de passe</a>
                <br>Le lien expirera dans 15 min!";

                $mail->send();
                $_SESSION['success_message'] = "Un email de réinitialisation a été envoyé! consulter votre boite email";
            } catch (Exception $e) {
                $_SESSION['error_message'] = "Erreur lors de l'envoi de l'email: {$mail->ErrorInfo}";
            }

        } else {
            $_SESSION['error_message'] = "Email non trouvé";
            echo "<div class='alert alert-warning'>Aucun compte n'est associé à cette adresse email</div>";
        }

        header('Location: forgotPassword.php');
        exit;
    }
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Connexion</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="participant/assets/style/password.css">
</head>
<body class="bg-light">
<div class="auth-container">
    <h2>Mot de passe oublié</h2>
    <?php
        if(isset($_SESSION['error_message'])) {
            echo "<p class='alert alert-danger mt-3'>{$_SESSION['error_message']}</p>";
            unset($_SESSION['error_message']);
        }
        if(isset($_SESSION['success_message'])) {
            echo "<p class='alert alert-success mt-3'>{$_SESSION['success_message']}</p>";
            unset($_SESSION['success_message']);
        }
    ?>
    <form method="POST">
        <div class="mb-3">
            <label for="email">Email</label>
            <input type="email" name="email" id="email" class="form-control" placeholder="contact@gmail.com" required>
        </div>
        <div class="btn-container mt-2">
            <button type="submit" class="btn btn-primary">Envoyer le lien</button>
            <a href="login.php" class="btn btn-secondary">Annuler</a>
        </div>
    </form>
</div>
</body>
</html>
