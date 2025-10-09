<?php
require_once 'config/database.php';
require_once 'config/session.php';

// Mode développement : si true, on effectue un auto-login sur le premier compte 'coordinateur'.
// NE PAS ACTIVER EN PRODUCTION.
define('DEV_AUTO_LOGIN', true);

if (DEV_AUTO_LOGIN) {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->query("SELECT * FROM compte WHERE role = 'coordinateur' LIMIT 1");
        $devUser = $stmt->fetch();
        if ($devUser) {
            $_SESSION['user_id'] = $devUser['idCompte'];
            $_SESSION['email'] = $devUser['Email'];
            $_SESSION['nom'] = $devUser['Nom'] ?? '';
            $_SESSION['prenom'] = $devUser['Prenom'] ?? '';
            $_SESSION['role'] = $devUser['role'];
            header('Location: pages/dashboard.php');
            exit();
        }
    } catch (PDOException $e) {
        // En dev, on peut ignorer l'erreur ou logger si besoin
    }
}

// Si l'utilisateur est déjà connecté, rediriger vers le dashboard
if (isCoordinateur()) {
    header('Location: pages/dashboard.php');
    exit();
}

$error = '';

// Traitement de la connexion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Veuillez remplir tous les champs';
    } else {
        try {
            $db = Database::getInstance()->getConnection();

            // Utiliser la table `compte` avec les colonnes : idCompte, Email, Password, role
            $stmt = $db->prepare("SELECT * FROM compte WHERE Email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            // Vérifier les identifiants et que le rôle est bien 'coordinateur'
            if ($user && password_verify($password, $user['Password']) && isset($user['role']) && $user['role'] === 'coordinateur') {
                // Créer la session (adapter les noms de champs existants)
                $_SESSION['user_id'] = $user['idCompte'];
                $_SESSION['email'] = $user['Email'];
                // La table `compte` n'a pas Nom/Prenom ; laisser vides si absent
                $_SESSION['nom'] = $user['Nom'] ?? '';
                $_SESSION['prenom'] = $user['Prenom'] ?? '';
                $_SESSION['role'] = $user['role'];

                // Rediriger vers le tableau de bord
                header('Location: pages/dashboard.php');
                exit();
            } else {
                $error = 'Email ou mot de passe incorrect';
            }
        } catch (PDOException $e) {
            $error = 'Erreur de connexion à la base de données';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion Coordinateur - UniClub</title>
    <link rel="stylesheet" href="assets/css/coordinateur.css">
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>🎓 UniClub</h1>
            <p>Espace Coordinateur</p>
        </div>
        
        <?php if ($error): ?>
        <div class="error-message">
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required 
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit" class="btn-login">Se connecter</button>
        </form>
        
        <div class="login-links">
            <a href="#">Mot de passe oublié ?</a>
        </div>
    </div>
</body>
</html>
