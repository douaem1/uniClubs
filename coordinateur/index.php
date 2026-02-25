<?php
include '../ConnDB.php';
require_once '/config/session.php';

// Mode développement : si true, on effectue un auto-login sur le premier compte 'coordinateur'.
// NE PAS ACTIVER EN PRODUCTION.
define('DEV_AUTO_LOGIN', false);

if (DEV_AUTO_LOGIN) {
    try {
        $result = $conn->query("SELECT * FROM compte WHERE role = 'coordinateur' LIMIT 1");
        
        if ($result && $result->num_rows > 0) {
            $devUser = $result->fetch_assoc();
            $_SESSION['user_id'] = $devUser['idCompte'];
            $_SESSION['email'] = $devUser['Email'];
            $_SESSION['nom'] = $devUser['Nom'] ?? '';
            $_SESSION['prenom'] = $devUser['Prenom'] ?? '';
            $_SESSION['role'] = $devUser['role'];
            
            header('Location: pages/dashboard.php');
            exit();
        }
        
        if ($result) {
            $result->free();
        }
    } catch (Exception $e) {
        // En dev, on peut ignorer l'erreur ou logger si besoin
        error_log("Erreur auto-login : " . $e->getMessage());
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
            // Requête préparée pour sécuriser contre les injections SQL
            $stmt = $conn->prepare("SELECT * FROM compte WHERE Email = ?");
            
            if (!$stmt) {
                throw new Exception("Erreur de préparation de la requête");
            }
            
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                
                // Vérifier le mot de passe et le rôle
                if (password_verify($password, $user['Password']) && 
                    isset($user['role']) && 
                    $user['role'] === 'coordinateur') {
                    
                    // Créer la session
                    $_SESSION['user_id'] = $user['idCompte'];
                    $_SESSION['email'] = $user['Email'];
                    $_SESSION['nom'] = $user['Nom'] ?? '';
                    $_SESSION['prenom'] = $user['Prenom'] ?? '';
                    $_SESSION['role'] = $user['role'];

                    // Rediriger vers le tableau de bord
                    $stmt->close();
                    header('Location: pages/dashboard.php');
                    exit();
                } else {
                    $error = 'Email ou mot de passe incorrect';
                }
            } else {
                $error = 'Email ou mot de passe incorrect';
            }
            
            $stmt->close();
            
        } catch (Exception $e) {
            $error = 'Erreur de connexion à la base de données';
            error_log("Erreur de connexion : " . $e->getMessage());
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
    <style>
        body {
            background: linear-gradient(135deg, #F5F3FF 0%, #EDE9FE 50%, #DDD6FE 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
    </style>
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