<?php
    session_start();
    include '../ConnDB.php';

    if (!isset($_SESSION['idCompte'])) {
        header('Location: ../login.php');
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmDelete'])) {
        $idCompte = $_SESSION['idCompte'];
        $password = $_POST['password'];

        // Récupérer le mot de passe haché du compte
        $stmt = $conn->prepare("SELECT Password FROM compte WHERE idCompte = ?");
        $stmt->bind_param("i", $idCompte);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if ($user && password_verify($password, $user['Password'])) {
            // Supprimer les inscriptions liées à ce compte
            $conn->query("DELETE FROM inscri WHERE idParticipant = (SELECT idParticipant FROM participant WHERE idCompte = $idCompte)");

            // Supprimer participant
            $conn->query("DELETE FROM participant WHERE idCompte = $idCompte");

            // Supprimer compte
            $conn->query("DELETE FROM compte WHERE idCompte = $idCompte");

            // Détruire la session
            session_destroy();

            header('Location: ../login.php');
            exit;
        } else {   
            $_SESSION['error_message'] = "Mot de passe incorrect";
            header('Location: profile.php');
            exit;
        }
    }
?>

<?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger mt-3">
        <?php 
            echo $_SESSION['error_message']; 
            unset($_SESSION['error_message']); 
        ?>
    </div>
<?php endif; ?>
