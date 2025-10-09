<?php
include 'db_events.php';

$message_image = '';
$imagePath = null;

// Traitement du formulaire d'ajout
if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST['action'])) {

    // Sécurisation des données texte
    $club = htmlspecialchars($_POST['club'] ?? '');
    $titre = htmlspecialchars($_POST['titre'] ?? '');
    $type = htmlspecialchars($_POST['type'] ?? '');
    $description = htmlspecialchars($_POST['description'] ?? '');
    $date_debut = htmlspecialchars($_POST['date_debut'] ?? '');
    $date_fin = htmlspecialchars($_POST['date_fin'] ?? '');
    $lieu = htmlspecialchars($_POST['lieu'] ?? '');
    $capacite = htmlspecialchars($_POST['capacite'] ?? '');
    $date_limite = htmlspecialchars($_POST['date_limite'] ?? '');

   
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
            $message_image = "❌ Format d'image non autorisé (jpg/png/gif uniquement).";
        } elseif ($file_size > 5 * 1024 * 1024) {
            $message_image = "❌ L'image dépasse 5 Mo.";
        } elseif (move_uploaded_file($tmp_name, $target_file)) {
            $imagePath = $target_file;
            $message_image = "✅ Image uploadée avec succès.";
        } else {
            $message_image = "❌ Erreur lors du transfert du fichier.";
        }
    } else {
        $message_image = "ℹ️ Aucune image sélectionnée.";
    }


    try {
        // Récupérer les infos du club
        $stmtClub = $conn->prepare("SELECT idClub, idOrganisateur FROM Club WHERE NomClub = :nomClub");
        $stmtClub->execute([':nomClub' => $club]);
        $clubData = $stmtClub->fetch(PDO::FETCH_ASSOC);

        if (!$clubData) {
            die("Club introuvable !");
        }

        $idClub = $clubData['idClub'];
        $idOrganisateur = $clubData['idOrganisateur'];

        // --- Insertion sécurisée dans Evenement ---
        $stmt = $conn->prepare("
            INSERT INTO Evenement
            (NomEnv, discription, dateDebut, dateFin, Lieu, Prix, Capacite, Type, finInscription, photo, idOrganisateur, idClub)
            VALUES
            (:NomEnv, :discription, :dateDebut, :dateFin, :Lieu, :Prix, :Capacite, :Type, :finInscription, :photo, :idOrganisateur, :idClub)
        ");

        $stmt->execute([
            ':NomEnv' => $titre,
            ':discription' => $description,
            ':dateDebut' => $date_debut,
            ':dateFin' => $date_fin,
            ':Lieu' => $lieu,
            ':Prix' => 0,
            ':Capacite' => $capacite,
            ':Type' => $type,
            ':finInscription' => $date_limite,
            ':photo' => $imagePath,
            ':idOrganisateur' => $idOrganisateur,
            ':idClub' => $idClub
        ]);

        // Récupérer l'ID de l'événement qui vient d'être inséré
        $Idenv = $conn->lastInsertId();

        $success_message = "✅ Événement inséré avec succès !";

    } catch (PDOException $e) {
        $error_message = "❌ Erreur lors de l'insertion : " . $e->getMessage();
        echo "<script>alert('Erreur: " . addslashes($e->getMessage()) . "');</script>";
    }
}

// Traitement de la suppression
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $Idenv = intval($_POST['Idenv']);
    if ($Idenv) {
        try {
            $stmt = $conn->prepare("DELETE FROM Evenement WHERE Idenv = :id");
            $stmt->execute(['id' => $Idenv]);
            echo "<script>
                    alert('Événement supprimé avec succès !');
                    window.location.href = 'events.php';
                  </script>";
            exit();
        } catch (PDOException $e) {
            $error_message = "❌ Erreur lors de la suppression : " . $e->getMessage();
            echo "<script>alert('Erreur: " . addslashes($e->getMessage()) . "');</script>";
        }
    }
}

// Traitement de la modification
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'update') {
    $titre = htmlspecialchars($_POST['titre']);
    $type = htmlspecialchars($_POST['type']);
    $description = htmlspecialchars($_POST['description']);
    $lieu = htmlspecialchars($_POST['lieu']);
    $date_debut = htmlspecialchars($_POST['date_debut']);
    $date_fin = htmlspecialchars($_POST['date_fin']);
    $capacite = htmlspecialchars($_POST['capacite']);
    $date_limite = htmlspecialchars($_POST['date_limite']);
    $Idenv = intval($_POST['Idenv']);

    // Gestion de l'image si une nouvelle est uploadée
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
        // Construire la requête UPDATE
        if ($imagePath) {
            $stmt = $conn->prepare("
                UPDATE Evenement
                SET NomEnv = :titre, Type = :type, discription = :description, Lieu = :lieu, 
                    dateDebut = :dateDebut, dateFin = :dateFin, Capacite = :capacite, 
                    finInscription = :finInscription, photo = :photo
                WHERE Idenv = :Idenv
            ");
            $params = [
                ':titre' => $titre,
                ':type' => $type,
                ':description' => $description,
                ':lieu' => $lieu,
                ':dateDebut' => $date_debut,
                ':dateFin' => $date_fin,
                ':capacite' => $capacite,
                ':finInscription' => $date_limite,
                ':photo' => $imagePath,
                ':Idenv' => $Idenv
            ];
        } else {
            $stmt = $conn->prepare("
                UPDATE Evenement
                SET NomEnv = :titre, Type = :type, discription = :description, Lieu = :lieu, 
                    dateDebut = :dateDebut, dateFin = :dateFin, Capacite = :capacite, 
                    finInscription = :finInscription
                WHERE Idenv = :Idenv
            ");
            $params = [
                ':titre' => $titre,
                ':type' => $type,
                ':description' => $description,
                ':lieu' => $lieu,
                ':dateDebut' => $date_debut,
                ':dateFin' => $date_fin,
                ':capacite' => $capacite,
                ':finInscription' => $date_limite,
                ':Idenv' => $Idenv
            ];
        }

        $stmt->execute($params);

        echo "<script>
                alert('Événement mis à jour avec succès !');
                window.location.href = 'events.php';
              </script>";
        exit();
    } catch (PDOException $e) {
        $error_message = "❌ Erreur lors de la mise à jour : " . $e->getMessage();
        echo "<script>alert('Erreur: " . addslashes($e->getMessage()) . "');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Récapitulatif de l'Événement</title>
  <style>
    body {
      min-height: 100vh;
      background: linear-gradient(135deg, #c3b1e1 0%, #b6a4d6 100%);
      font-family: 'Poppins', 'Segoe UI', Arial, sans-serif;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 40px 0;
    }
    .event-card {
      background: #fff;
      border-radius: 18px;
      box-shadow: 0 8px 32px rgba(106,54,255,0.10);
      max-width: 500px;
      width: 100%;
      margin: 0 auto;
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
    .event-footer {
      margin-top: 32px;
      padding: 0 36px;
      display: flex;
      justify-content: flex-end;
      gap: 10px;
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
      background: #dc3545;
    }
    .btn-delete:hover {
      background: #c82333;
    }
    .btn-publish {
      background: #28a745;
    }
    .btn-publish:hover {
      background: #218838;
    }
    .error-message {
      background: #f8d7da;
      color: #721c24;
      padding: 12px;
      border-radius: 8px;
      margin: 20px 36px;
      border: 1px solid #f5c6cb;
    }
    @media (max-width: 600px) {
      .event-card { padding: 0; }
      .event-header, .event-section, .event-footer { padding-left: 6vw; padding-right: 6vw; }
      .event-section { margin: 18px 0 0 0; }
    }
  </style>
</head>
<body>
  <?php if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($club) && !isset($_POST['action'])): ?>
  <div class="event-card">
    <?php if (isset($error_message)): ?>
      <div class="error-message"><?= $error_message ?></div>
    <?php endif; ?>
    
    <?php if ($imagePath && file_exists($imagePath)): ?>
    <div class="event-image-block">
      <img src="<?= $imagePath ?>" alt="Image de l'événement">
    </div>
    <?php endif; ?>
    
    <div class="event-header">
      <div class="event-title"><?= htmlspecialchars($titre) ?></div>
      <div class="event-club"><?= htmlspecialchars($club) ?></div>
    </div>
    
    <div class="event-section">
      <div class="event-label">Type d'événement</div>
      <div class="event-value"><?= htmlspecialchars($type) ?></div>
    </div>
    
    <div class="event-section">
      <div class="event-label">Description</div>
      <div class="event-value"><?= $description ? htmlspecialchars($description) : 'Aucune description fournie' ?></div>
    </div>
    
    <div class="event-section">
      <div class="event-label">Dates</div>
      <div class="event-value">
        Du <?= date('d/m/Y', strtotime($date_debut)) ?> au <?= date('d/m/Y', strtotime($date_fin)) ?>
      </div>
    </div>
    
    <div class="event-section">
      <div class="event-label">Lieu</div>
      <div class="event-value"><?= htmlspecialchars($lieu) ?></div>
    </div>
    
    <div class="event-section">
      <div class="event-label">Capacité</div>
      <div class="event-value"><?= htmlspecialchars($capacite) ?> personnes maximum</div>
    </div>
    
    <div class="event-section">
      <div class="event-label">Date limite d'inscription</div>
      <div class="event-value"><?= date('d/m/Y', strtotime($date_limite)) ?></div>
    </div>
    
    <div class="event-footer">
      <!-- Bouton Modifier -->
      <form action="formevent.php" method="post" style="display:inline; margin:0;">
        <input type="hidden" name="mode" value="edit">
        <input type="hidden" name="Idenv" value="<?= isset($Idenv) ? $Idenv : '' ?>">
        <input type="hidden" name="club" value="<?= htmlspecialchars($club) ?>">
        <input type="hidden" name="titre" value="<?= htmlspecialchars($titre) ?>">
        <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">
        <input type="hidden" name="description" value="<?= htmlspecialchars($description) ?>">
        <input type="hidden" name="lieu" value="<?= htmlspecialchars($lieu) ?>">
        <input type="hidden" name="date_debut" value="<?= $date_debut ?>">
        <input type="hidden" name="date_fin" value="<?= $date_fin ?>">
        <input type="hidden" name="capacite" value="<?= htmlspecialchars($capacite) ?>">
        <input type="hidden" name="date_limite" value="<?= $date_limite ?>">
        <button type="submit" class="btn-back">Modifier</button>
      </form>
      
      <!-- Bouton Supprimer -->
      <?php if (isset($Idenv)): ?>
      <form action="recap.php" method="post" style="display:inline; margin:0;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cet événement ?');">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="Idenv" value="<?= $Idenv ?>">
        <button type="submit" class="btn-delete">Supprimer</button>
      </form>
      <?php endif; ?>
   
      <!-- Bouton Publier -->
      <a href="events.php" class="btn-publish">Publier</a>
    </div>
  </div>
  <?php else: ?>
    <div class="event-card" style="text-align:center; padding: 40px;">
      <p>Aucun événement à afficher.</p>
      <a href="formevent.php" class="btn-back" style="margin-top:18px;">Retour au formulaire</a>
    </div>
  <?php endif; ?>
</body>
</html>