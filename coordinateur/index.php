<?php
require_once 'config/database.php';
require_once 'config/session.php';

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
            $stmt = $db->prepare("SELECT * FROM Coordinateur WHERE EmailCord = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            // Vérifier les identifiants
            if ($user && password_verify($password, $user['MotPass'])) {
                // Créer la session
                $_SESSION['user_id'] = $user['idCoord'];
                $_SESSION['email'] = $user['EmailCord'];
                $_SESSION['nom'] = $user['NomCord'];
                $_SESSION['prenom'] = $user['PrenomCord'];
                $_SESSION['role'] = 'coordinateur';
                
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
    <style>
        .login-container {
            max-width: 400px;
            margin: 100px auto;
            padding: 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-header h1 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #555;
        }
        
        .form-group input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .error-message {
            color: #dc3545;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .btn-login {
            width: 100%;
            padding: 10px;
            background: #007bff;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .btn-login:hover {
            background: #0056b3;
        }
        
        .login-links {
            text-align: center;
            margin-top: 20px;
        }
        
        .login-links a {
            color: #007bff;
            text-decoration: none;
        }
        
        .login-links a:hover {
            text-decoration: underline;
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
