<?php
    session_start();
    include 'ConnDB.php';

    if (!isset($_GET['token'])) {
        die("Token invalide");
    }

    $token = $_GET['token'];

    $stmt = $conn->prepare("SELECT idCompte, expiration FROM passwordresets WHERE token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $_SESSION['error_message'] = "Token invalide ou expiré";
        header('Location: login.php');
        exit;
    }

    $row = $result->fetch_assoc();

    // Vérifier l’expiration
    if (strtotime($row['expiration']) < time()) {
        die("Le lien a expiré");
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $password = password_hash(trim($_POST['password']), PASSWORD_DEFAULT);

        $stmt2 = $conn->prepare("UPDATE compte SET Password = ? WHERE idCompte = ?");
        $stmt2->bind_param("si", $password, $row['idCompte']);
        $stmt2->execute();

        $stmt3 = $conn->prepare("DELETE FROM passwordresets WHERE idCompte = ?");
        $stmt3->bind_param("i", $row['idCompte']);
        $stmt3->execute();

        $_SESSION['success_message'] = "Mot de passe réinitialisé avec succès";
        header('Location: login.php');
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
    <h2>Réinitialiser le mot de passe</h2>
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
            <label for="pass">Nouveau mot de passe</label>
            <input type="password" name="password" id="pass" class="form-control" required>
        </div>
        <div class="btn-container mt-2">
            <button type="submit" class="btn btn-primary">Réinitialiser</button>
        </div>
    </form>
</div>
</body>
</html>
