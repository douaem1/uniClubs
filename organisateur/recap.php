<?php
session_start();
include '../ConnDB.php';

// Vérifier si l'utilisateur est connecté et est un organisateur
if (!isset($_SESSION['idCompte']) || $_SESSION['role'] !== 'organisateur') {
    header('Location: login.php');
    exit;
}

$idOrganisateur = $_SESSION['idCompte'];
$message_image = '';
$imagePath = null;

// Traitement du formulaire d'ajout
if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST['action'])) {

    // Sécurisation des données texte
    $club = htmlspecialchars($_POST['club'] ?? '');
    $titre = htmlspecialchars($_POST['titre'] ?? '');
    $type = htmlspecialchars($_POST['type'] ?? '');
    $type_autre = htmlspecialchars($_POST['type_autre'] ?? '');
    $description = htmlspecialchars($_POST['description'] ?? '');
    $date_debut = htmlspecialchars($_POST['date_debut'] ?? '');
    $heure_debut = !empty($_POST['heure_debut']) ? htmlspecialchars($_POST['heure_debut']) : null;
    $date_fin = htmlspecialchars($_POST['date_fin'] ?? '');
    $heure_fin = !empty($_POST['heure_fin']) ? htmlspecialchars($_POST['heure_fin']) : null;
    $lieu = htmlspecialchars($_POST['lieu'] ?? '');
    $capacite = intval($_POST['capacite'] ?? 0);
    $date_limite = htmlspecialchars($_POST['date_limite'] ?? '');
    $prix_adherent = floatval($_POST['prix_adherent'] ?? 0);
    $prix_non_adherent = floatval($_POST['prix_non_adherent'] ?? 0);
    
    // Si le type est "Autre", utiliser type_autre
    if ($type === 'Autre' && !empty($type_autre)) {
        $type = $type_autre;
    }

    // Gestion de l'image
    if (!empty($_FILES['image']['name'])) {
        $uploads_dir = 'uploads';

        // Crée le dossier si inexistant
        if (!is_dir($uploads_dir)) {
            mkdir($uploads_dir, 0777, true);
        }

        $tmp_name = $_FILES["image"]["tmp_name"];
        $original_name = basename($_FILES["image"]["name"]);
        $file_type = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        $file_size = $_FILES["image"]["size"];
        
        $unique_name = uniqid() . '_' . time() . '.' . $file_type;
        $target_file = $uploads_dir . '/' . $unique_name;

        $extensions_autorisees = ["jpg", "jpeg", "png", "gif"];
        
        if (!in_array($file_type, $extensions_autorisees)) {
            $message_image = "Format d'image non autorisé (jpg/png/gif uniquement).";
        } elseif ($file_size > 5 * 1024 * 1024) {
            $message_image = " L'image dépasse 5 Mo.";
        } elseif (move_uploaded_file($tmp_name, $target_file)) {
            $imagePath = $target_file;
            $message_image = " Image uploadée avec succès.";
        } else {
            $message_image = " Erreur lors du transfert du fichier.";
        }
    } else {
        $message_image = "Aucune image sélectionnée.";
    }

    try {
        // Récupérer idClub si fourni
        $idClub = null;
        if (!empty($club)) {
            $stmtClub = $conn->prepare("SELECT idClub FROM Club WHERE NomClub = ?");
            $stmtClub->bind_param("s", $club);
            $stmtClub->execute();
            $resultClub = $stmtClub->get_result();
            $clubData = $resultClub->fetch_assoc();
            if ($clubData) {
                $idClub = $clubData['idClub'];
            }
        }

        // Insertion sécurisée dans Evenement avec les nouveaux champs
        $stmt = $conn->prepare("
            INSERT INTO Evenement
            (NomEnv, discription, dateDebut, heureDebut, dateFin, heureFin, Lieu, prixAdherent, prixNonAdherent, Capacite, Type, finInscription, photo, idOrganisateur, idClub)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        // Convertir les prix en décimaux explicitement
        $prix_adherent_decimal = floatval($prix_adherent);
        $prix_non_adherent_decimal = floatval($prix_non_adherent);
        $capacite_int = intval($capacite);
        $idOrganisateur_int = intval($idOrganisateur);
        $idClub_int = $idClub ? intval($idClub) : null;

        $stmt->bind_param(
            "sssssssddisssii",
            $titre,
            $description,
            $date_debut,
            $heure_debut,
            $date_fin,
            $heure_fin,
            $lieu,
            $prix_adherent_decimal,
            $prix_non_adherent_decimal,
            $capacite_int,
            $type,
            $date_limite,
            $imagePath,
            $idOrganisateur_int,
            $idClub_int
        );

        if ($stmt->execute()) {
            $Idenv = $conn->insert_id;
            $success_message = "Événement inséré avec succès !";
        } else {
            $error_message = "Erreur lors de l'exécution : " . $stmt->error;
        }

    } catch (Exception $e) {
        $error_message = "Erreur lors de l'insertion : " . $e->getMessage();
    }
}

// Traitement de la suppression
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $Idenv = intval($_POST['Idenv']);
    if ($Idenv) {
        try {
            // Récupérer le chemin de l'image avant suppression
            $stmtGetPhoto = $conn->prepare("SELECT photo FROM Evenement WHERE Idenv = ?");
            $stmtGetPhoto->bind_param("i", $Idenv);
            $stmtGetPhoto->execute();
            $resultPhoto = $stmtGetPhoto->get_result();
            $photoData = $resultPhoto->fetch_assoc();
            
            // Supprimer l'événement
            $stmt = $conn->prepare("DELETE FROM Evenement WHERE Idenv = ?");
            $stmt->bind_param("i", $Idenv);
            $stmt->execute();
            
            // Supprimer l'image du serveur si elle existe
            if ($photoData && $photoData['photo'] && file_exists($photoData['photo'])) {
                unlink($photoData['photo']);
            }
            
            echo "<script>alert('Événement supprimé avec succès !'); window.location.href = 'events.php';</script>";
            exit();
        } catch (Exception $e) {
            $error_message = "Erreur lors de la suppression : " . $e->getMessage();
        }
    }
}

// Traitement de la modification
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'update') {
    $titre = htmlspecialchars($_POST['titre']);
    $type = htmlspecialchars($_POST['type']);
    $type_autre = htmlspecialchars($_POST['type_autre'] ?? '');
    $description = htmlspecialchars($_POST['description']);
    $lieu = htmlspecialchars($_POST['lieu']);
    $date_debut = htmlspecialchars($_POST['date_debut']);
    $heure_debut = !empty($_POST['heure_debut']) ? htmlspecialchars($_POST['heure_debut']) : null;
    $date_fin = htmlspecialchars($_POST['date_fin']);
    $heure_fin = !empty($_POST['heure_fin']) ? htmlspecialchars($_POST['heure_fin']) : null;
    $capacite = intval($_POST['capacite']);
    $date_limite = htmlspecialchars($_POST['date_limite']);
    $prix_adherent = floatval($_POST['prix_adherent'] ?? 0);
    $prix_non_adherent = floatval($_POST['prix_non_adherent'] ?? 0);
    $Idenv = intval($_POST['Idenv']);
    
    // Si le type est "Autre", utiliser type_autre
    if ($type === 'Autre' && !empty($type_autre)) {
        $type = $type_autre;
    }

    $imagePath = null;
    if (!empty($_FILES['image']['name'])) {
        $uploads_dir = 'uploads';
        if (!is_dir($uploads_dir)) {
            mkdir($uploads_dir, 0777, true);
        }

        $tmp_name = $_FILES["image"]["tmp_name"];
        $original_name = basename($_FILES["image"]["name"]);
        $file_type = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        $file_size = $_FILES["image"]["size"];
        
        $unique_name = uniqid() . '_' . time() . '.' . $file_type;
        $target_file = $uploads_dir . '/' . $unique_name;

        $extensions_autorisees = ["jpg", "jpeg", "png", "gif"];
        
        if (in_array($file_type, $extensions_autorisees) && $file_size <= 5 * 1024 * 1024) {
            if (move_uploaded_file($tmp_name, $target_file)) {
                $imagePath = $target_file;
            }
        }
    }

    try {
        if ($imagePath) {
            $stmt = $conn->prepare("
                UPDATE Evenement
                SET NomEnv = ?, Type = ?, discription = ?, Lieu = ?, 
                    dateDebut = ?, heureDebut = ?, dateFin = ?, heureFin = ?,
                    Capacite = ?, finInscription = ?, 
                    prixAdherent = ?, prixNonAdherent = ?, photo = ?
                WHERE Idenv = ?
            ");
            
            $prix_adherent_decimal = floatval($prix_adherent);
            $prix_non_adherent_decimal = floatval($prix_non_adherent);
            
            $stmt->bind_param(
                "ssssssssisddsi",
                $titre,
                $type,
                $description,
                $lieu,
                $date_debut,
                $heure_debut,
                $date_fin,
                $heure_fin,
                $capacite,
                $date_limite,
                $prix_adherent_decimal,
                $prix_non_adherent_decimal,
                $imagePath,
                $Idenv
            );
        } else {
            $stmt = $conn->prepare("
                UPDATE Evenement
                SET NomEnv = ?, Type = ?, discription = ?, Lieu = ?, 
                    dateDebut = ?, heureDebut = ?, dateFin = ?, heureFin = ?,
                    Capacite = ?, finInscription = ?,
                    prixAdherent = ?, prixNonAdherent = ?
                WHERE Idenv = ?
            ");
            
            $prix_adherent_decimal = floatval($prix_adherent);
            $prix_non_adherent_decimal = floatval($prix_non_adherent);
            
            $stmt->bind_param(
                "ssssssssisddi",
                $titre,
                $type,
                $description,
                $lieu,
                $date_debut,
                $heure_debut,
                $date_fin,
                $heure_fin,
                $capacite,
                $date_limite,
                $prix_adherent_decimal,
                $prix_non_adherent_decimal,
                $Idenv
            );
        }

        $stmt->execute();
        echo "<script>alert('Événement mis à jour avec succès !'); window.location.href = 'events.php';</script>";
        exit();
    } catch (Exception $e) {
        $error_message = "Erreur lors de la mise à jour : " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Récapitulatif de l'Événement</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --primary-color: #8A2BE2;
      --primary-hover: #7c3aed;
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      min-height: 100vh;
      background: linear-gradient(135deg, #c3b1e1 0%, #b6a4d6 100%);
      font-family: 'Poppins', sans-serif;
      padding: 0;
    }

    .navbar {
      background: #fff;
      padding: 18px 40px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      box-shadow: 0 2px 8px rgba(0,0,0,0.05);
      margin-bottom: 40px;
    }

    .logo-section {
      display: flex;
      align-items: center;
    }

    .logo-container {
      display: flex;
      align-items: center;
      gap: 12px;
      text-decoration: none;
      color: #333;
    }

    .logo-icon {
      width: 42px;
      height: 42px;
      background: linear-gradient(135deg, var(--primary-color), var(--primary-hover));
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #fff;
      font-size: 1.3rem;
    }

    .logo-text {
      font-size: 1.4rem;
      font-weight: 700;
      color: #333;
    }

    .container {
      max-width: 600px;
      margin: 0 auto;
      padding: 0 20px 40px 20px;
    }

    .event-card {
      background: #fff;
      border-radius: 18px;
      box-shadow: 0 8px 32px rgba(106,54,255,0.10);
      width: 100%;
      padding: 0 0 32px 0;
      display: flex;
      flex-direction: column;
      gap: 0;
      border: 1.5px solid #ece6fa;
      overflow: hidden;
    }

    .event-image-block {
      width: 100%;
      background: #f3f0ff;
      display: flex;
      justify-content: center;
      align-items: center;
      padding: 32px 0 18px 0;
      border-bottom: 1.5px solid #ece6fa;
    }

    .event-image-block img {
      max-width: 180px;
      width: 100%;
      border-radius: 12px;
      border: 1.5px solid #c3b1e1;
      box-shadow: 0 2px 10px rgba(106,54,255,0.10);
      background: #fff;
      object-fit: cover;
    }

    .event-header {
      padding: 28px 36px 0 36px;
      display: flex;
      flex-direction: column;
      gap: 2px;
    }

    .event-title {
      font-size: 1.7rem;
      font-weight: 700;
      color: #3d2a7a;
      margin-bottom: 2px;
      letter-spacing: 0.5px;
    }

    .event-club {
      color: #7c6bb1;
      font-size: 1.08em;
      font-weight: 500;
      margin-bottom: 0;
    }

    .event-section {
      background: #faf7ff;
      border-radius: 12px;
      padding: 18px 24px 12px 24px;
      margin: 18px 36px 0 36px;
      display: flex;
      flex-direction: column;
      gap: 2px;
      border: 1px solid #ece6fa;
      box-shadow: 0 2px 8px rgba(106,54,255,0.04);
    }

    .event-label {
      color: #6a36ff;
      font-weight: 600;
      font-size: 1.07em;
      margin-bottom: 2px;
    }

    .event-value {
      color: #2d2350;
      font-size: 1.05em;
      font-weight: 400;
      margin-bottom: 0;
      white-space: pre-line;
    }

    .price-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px;
      margin-top: 8px;
    }

    .price-item {
      background: #fff;
      padding: 12px;
      border-radius: 8px;
      border: 1px solid #e9d5ff;
    }

    .price-label {
      font-size: 0.85em;
      color: #7c6bb1;
      font-weight: 500;
      margin-bottom: 4px;
    }

    .price-value {
      font-size: 1.3em;
      font-weight: 700;
      color: #6a36ff;
    }

    .event-footer {
      margin-top: 32px;
      padding: 0 36px;
      display: flex;
      justify-content: flex-end;
      gap: 10px;
      flex-wrap: wrap;
    }

    .btn-back, .btn-delete, .btn-publish {
      background: #6a36ff;
      color: #fff;
      border: none;
      border-radius: 8px;
      padding: 10px 28px;
      font-size: 1em;
      font-weight: 600;
      cursor: pointer;
      transition: background 0.18s;
      text-decoration: none;
      display: inline-block;
    }

    .btn-back:hover, .btn-delete:hover, .btn-publish:hover {
      background: #5328d8;
    }

    .btn-delete {
      background: #e53e3e;
    }

    .btn-delete:hover {
      background: #c53030;
    }

    .btn-publish {
      background: #48bb78;
    }

    .btn-publish:hover {
      background: #38a169;
    }

    .error-message, .success-message {
      padding: 16px 20px;
      border-radius: 12px;
      margin: 0 36px 20px 36px;
      display: flex;
      align-items: center;
      gap: 10px;
      font-weight: 500;
    }

    .error-message {
      background: #fee2e2;
      color: #991b1b;
      border: 1px solid #fca5a5;
    }

    .success-message {
      background: #d1fae5;
      color: #065f46;
      border: 1px solid #6ee7b7;
    }

    .empty-state {
      text-align: center;
      padding: 60px 20px;
    }

    .empty-state i {
      font-size: 3rem;
      color: #cbd5e0;
      margin-bottom: 16px;
    }

    @media (max-width: 600px) {
      .navbar {
        padding: 18px 20px;
      }
      
      .event-card {
        padding: 0;
      }
      
      .event-header, .event-section, .event-footer, .error-message, .success-message {
        padding-left: 6vw;
        padding-right: 6vw;
      }
      
      .event-section, .error-message, .success-message {
        margin-left: 0;
        margin-right: 0;
      }

      .event-footer {
        flex-direction: column;
      }

      .btn-back, .btn-delete, .btn-publish {
        width: 100%;
      }

      .price-grid {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body>
  <div class="navbar">
    <div class="logo-section">
      <a href="dashboard_organisateur.php" class="logo-container">
        <div class="logo-icon">
          <i class="bi bi-people-fill"></i>
        </div>
        <span class="logo-text">UniClub</span>
      </a>
    </div>
  </div>

  <div class="container">
    <?php if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($titre) && !isset($_POST['action'])): ?>
      <div class="event-card">
        <?php if (isset($error_message)): ?>
          <div class="error-message">
            <i class="bi bi-x-circle-fill"></i>
            <?= $error_message ?>
          </div>
        <?php endif; ?>

        <?php if (isset($success_message)): ?>
          <div class="success-message">
            <i class="bi bi-check-circle-fill"></i>
            <?= $success_message ?>
          </div>
        <?php endif; ?>
        
        <?php if ($imagePath && file_exists($imagePath)): ?>
          <div class="event-image-block">
            <img src="<?= $imagePath ?>" alt="Image de l'événement">
          </div>
        <?php endif; ?>
        
        <div class="event-header">
          <div class="event-title"><?= htmlspecialchars($titre) ?></div>
          <?php if (!empty($club)): ?>
            <div class="event-club"><i class="bi bi-building"></i> <?= htmlspecialchars($club) ?></div>
          <?php endif; ?>
        </div>
        
        <div class="event-section">
          <div class="event-label"><i class="bi bi-tag"></i> Type d'événement</div>
          <div class="event-value"><?= htmlspecialchars($type) ?></div>
        </div>
        
        <div class="event-section">
          <div class="event-label"><i class="bi bi-text-paragraph"></i> Description</div>
          <div class="event-value"><?= $description ? htmlspecialchars($description) : 'Aucune description fournie' ?></div>
        </div>
        
        <div class="event-section">
          <div class="event-label"><i class="bi bi-calendar-range"></i> Dates et horaires</div>
          <div class="event-value">
            Du <?= date('d/m/Y', strtotime($date_debut)) ?> 
            <?php if ($heure_debut): ?>à <?= substr($heure_debut, 0, 5) ?><?php endif; ?>
            <br>
            au <?= date('d/m/Y', strtotime($date_fin)) ?>
            <?php if ($heure_fin): ?>à <?= substr($heure_fin, 0, 5) ?><?php endif; ?>
          </div>
        </div>
        
        <div class="event-section">
          <div class="event-label"><i class="bi bi-geo-alt"></i> Lieu</div>
          <div class="event-value"><?= htmlspecialchars($lieu) ?></div>
        </div>
        
        <div class="event-section">
          <div class="event-label"><i class="bi bi-people"></i> Capacité</div>
          <div class="event-value"><?= htmlspecialchars($capacite) ?> personnes maximum</div>
        </div>
        
        <div class="event-section">
          <div class="event-label"><i class="bi bi-calendar-x"></i> Date limite d'inscription</div>
          <div class="event-value"><?= date('d/m/Y', strtotime($date_limite)) ?></div>
        </div>
        
        <div class="event-section">
          <div class="event-label"><i class="bi bi-cash-coin"></i> Tarification</div>
          <div class="price-grid">
            <div class="price-item">
              <div class="price-label"><i class="bi bi-person-check"></i> Prix adhérent</div>
              <div class="price-value"><?= number_format($prix_adherent, 2) ?> DH</div>
            </div>
            <div class="price-item">
              <div class="price-label"><i class="bi bi-person-x"></i> Prix non-adhérent</div>
              <div class="price-value"><?= number_format($prix_non_adherent, 2) ?> DH</div>
            </div>
          </div>
        </div>
        
        <div class="event-footer">
          <form action="formevent.php" method="post" style="display:inline; margin:0;">
            <input type="hidden" name="mode" value="edit">
            <input type="hidden" name="Idenv" value="<?= isset($Idenv) ? $Idenv : '' ?>">
            <input type="hidden" name="club" value="<?= htmlspecialchars($club) ?>">
            <input type="hidden" name="titre" value="<?= htmlspecialchars($titre) ?>">
            <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">
            <input type="hidden" name="description" value="<?= htmlspecialchars($description) ?>">
            <input type="hidden" name="lieu" value="<?= htmlspecialchars($lieu) ?>">
            <input type="hidden" name="date_debut" value="<?= $date_debut ?>">
            <input type="hidden" name="heure_debut" value="<?= $heure_debut ?>">
            <input type="hidden" name="date_fin" value="<?= $date_fin ?>">
            <input type="hidden" name="heure_fin" value="<?= $heure_fin ?>">
            <input type="hidden" name="capacite" value="<?= htmlspecialchars($capacite) ?>">
            <input type="hidden" name="date_limite" value="<?= $date_limite ?>">
            <input type="hidden" name="prix_adherent" value="<?= $prix_adherent ?>">
            <input type="hidden" name="prix_non_adherent" value="<?= $prix_non_adherent ?>">
            <button type="submit" class="btn-back">
              <i class="bi bi-pencil"></i> Modifier
            </button>
          </form>
          
          <?php if (isset($Idenv)): ?>
            <form action="recap.php" method="post" style="display:inline; margin:0;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cet événement ?');">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="Idenv" value="<?= $Idenv ?>">
              <button type="submit" class="btn-delete">
                <i class="bi bi-trash"></i> Supprimer
              </button>
            </form>
          <?php endif; ?>
       
          <a href="events.php" class="btn-publish">
            <i class="bi bi-check-circle"></i> Terminer
          </a>
        </div>
      </div>
    <?php else: ?>
      <div class="event-card">
        <div class="empty-state">
          <i class="bi bi-calendar-x"></i>
          <h3>Aucun événement à afficher</h3>
          <p style="margin: 10px 0 20px 0; color: #718096;">Créez un nouvel événement pour commencer</p>
          <a href="formevent.php" class="btn-back">
            <i class="bi bi-plus-circle"></i> Créer un événement
          </a>
        </div>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>