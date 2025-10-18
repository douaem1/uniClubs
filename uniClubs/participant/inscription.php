<?php
    include '../ConnDB.php';
    include 'header.php';

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (!isset($_GET['id']) || empty($_GET['id'])) {
            die("Événement invalide");
        }
        $Idenv = intval($_GET['id']);
    } else {
        $Idenv = intval($_POST['Idenv']);
    }

    $idParticipant = $_SESSION['idCompte'];

    $stmt = $conn->prepare("SELECT NomEnv FROM evenement WHERE Idenv = ?");
    $stmt->bind_param("i", $Idenv);
    $stmt->execute();
    $stmt->bind_result($nomEvenement);
    $stmt->fetch();
    $stmt->close();

    $message = '';
    $alertClass = 'alert-danger';
    
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $Idenv = intval($_POST['Idenv']);
        $nom = trim($_POST['Nom']);
        $prenom = trim($_POST['Prenom']);
        $email = trim($_POST['Email']);
        $tel = trim($_POST['Telephone']);
        $filiere = trim($_POST['Filiere'] ?? '');
        if (isset($_POST['externe'])) {
            $is_externe = 1;
        } else {
            $is_externe = 0;
        }
        
        // Vérifier s'il est déjà inscrit à cet événement
        $verif = $conn->prepare("SELECT * FROM inscri WHERE idParticipant = ? AND Idenv = ?");
        $verif->bind_param("ii", $idParticipant, $Idenv);
        $verif->execute();
        $verif->store_result();

        if ($verif->num_rows == 0) {
            $verif->free_result();
            $verif->close();
            $inscri = $conn->prepare("INSERT INTO inscri (idParticipant, Idenv, dateIcription) VALUES (?, ?, CURDATE())");
            $inscri->bind_param("ii", $idParticipant, $Idenv);
            $inscri->execute();
            $inscri->close();
            $message = "Inscription réussie";
            $alertClass = 'alert-success';
        } else {
            $message = "Vous êtes déjà inscrit à cet événement";
        }
    }
?>

<link rel="stylesheet" href="assets/style/inscription.css">
<div class="container">

    <div class="form-header">
        <h2 class="form-title">Inscription - <?php echo htmlspecialchars($nomEvenement); ?></h2>
        <button type="button" class="close-btn" onclick="window.history.back()">&times;</button>
        <br>
        <?php if (!empty($message)): ?>
            <div class="alert <?php echo $alertClass; ?>" role="alert" style="margin-bottom:16px;">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
    </div>
    
    <form action="inscription.php" method="POST">
        <input type="hidden" name="Idenv" value="<?php echo $Idenv; ?>">

        <div class="form-row">
            <div class="form-group">
                <label for="Prenom" class="form-label">Prénom <span class="required">*</span></label>
                <input type="text" name="Prenom" id="Prenom" class="form-control" value="<?php echo htmlspecialchars($_SESSION['prenom'] ?? ''); ?>" readonly>
            </div>

            <div class="form-group">
                <label for="Nom" class="form-label">Nom <span class="required">*</span></label>
                <input type="text" name="Nom" id="Nom" class="form-control" value="<?php echo htmlspecialchars($_SESSION['nom'] ?? ''); ?>" readonly>
            </div>
        </div>

        <div class="form-group">
            <label for="Email" class="form-label">Email <span class="required">*</span></label>
            <input type="email" name="Email" id="Email" class="form-control" value="<?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?>" readonly>
        </div>

        <div class="form-group">
            <label for="Telephone" class="form-label">Numéro de téléphone <span class="required">*</span></label>
            <input type="tel" name="Telephone" id="Telephone" class="form-control" value="<?php echo htmlspecialchars($_SESSION['tele'] ?? ''); ?>" readonly>
        </div>
        
        <?php if (!empty($_SESSION['filiere'])): ?>
            <div class="form-group">
                <label for="Filiere" class="form-label">Filière</label>
                <input type="text" id="Filiere" class="form-control" value="<?php echo htmlspecialchars($_SESSION['filiere']); ?>" readonly>
            </div>
        <?php elseif (!empty($_SESSION['externe']) && $_SESSION['externe'] == 1): ?>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="Externe" checked readonly>
                <label class="form-check-label" for="Externe">Participant externe</label>
            </div>
        <?php endif; ?>

        <button type="submit" class="btn-submit">Valider mon inscription</button>
    </form>
</div>
</body>
</html>