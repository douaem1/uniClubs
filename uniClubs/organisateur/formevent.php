<?php
session_start();
include '../ConnDB.php';

if (!isset($_SESSION['idCompte']) || $_SESSION['role'] !== 'organisateur') {
    header('Location: login.php');
    exit;
}

$idOrganisateur = $_SESSION['idCompte'];

// Déterminer le mode (création ou édition)
$mode = 'create';
$eventData = [];

// MODE ÉDITION : Si un ID est passé dans l'URL
if (isset($_GET['edit'])) {
    $eventId = intval($_GET['edit']);
    
    // Récupérer les données de l'événement depuis la base de données avec jointure pour le club
    $stmt = $conn->prepare("
        SELECT e.*, c.NomClub 
        FROM Evenement e 
        LEFT JOIN Club c ON e.idClub = c.idClub 
        WHERE e.Idenv = ? AND e.idOrganisateur = ?
    ");
    $stmt->bind_param("ii", $eventId, $idOrganisateur);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $mode = 'edit';
        
        // Mapper les données de la base vers le format du formulaire
        $eventData = [
            'Idenv' => $row['Idenv'],
            'club' => $row['NomClub'] ?? '',
            'titre' => $row['NomEnv'],
            'type' => $row['Type'] ?? '',
            'type_autre' => '', // TypeAutre n'existe pas dans la base
            'description' => $row['discription'], // Attention: discription avec 's'
            'date_debut' => date('Y-m-d', strtotime($row['dateDebut'])),
            'heure_debut' => $row['heureDebut'] ? date('H:i', strtotime($row['heureDebut'])) : '',
            'date_fin' => date('Y-m-d', strtotime($row['dateFin'])),
            'heure_fin' => $row['heureFin'] ? date('H:i', strtotime($row['heureFin'])) : '',
            'lieu' => $row['Lieu'],
            'date_limite' => $row['finInscription'] ? date('Y-m-d', strtotime($row['finInscription'])) : date('Y-m-d'),
            'capacite' => $row['Capacite'],
            'prix_adherent' => $row['prixAdherent'] ?? 0,
            'prix_non_adherent' => $row['prixNonAdherent'] ?? 0,
            'photo' => $row['photo'] ?? ''
        ];
    } else {
        // L'événement n'existe pas ou n'appartient pas à cet organisateur
        header('Location: Evenements.php');
        exit;
    }
    $stmt->close();
}
// Si les données viennent d'un POST (retour depuis recap.php par exemple)
elseif (isset($_POST['mode']) && $_POST['mode'] === 'edit' && isset($_POST['Idenv'])) {
    $mode = 'edit';
    $eventData = [
        'Idenv' => $_POST['Idenv'] ?? '',
        'titre' => $_POST['titre'] ?? '',
        'description' => $_POST['description'] ?? '',
        'lieu' => $_POST['lieu'] ?? '',
        'date_debut' => $_POST['date_debut'] ?? '',
        'heure_debut' => $_POST['heure_debut'] ?? '',
        'date_fin' => $_POST['date_fin'] ?? '',
        'heure_fin' => $_POST['heure_fin'] ?? '',
        'capacite' => $_POST['capacite'] ?? '',
        'date_limite' => $_POST['date_limite'] ?? '',
        'club' => $_POST['club'] ?? '',
        'type' => $_POST['type'] ?? '',
        'type_autre' => $_POST['type_autre'] ?? '',
        'prix_adherent' => $_POST['prix_adherent'] ?? '',
        'prix_non_adherent' => $_POST['prix_non_adherent'] ?? '',
        'photo' => $_POST['photo_actuelle'] ?? '',
    ];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $mode === 'edit' ? 'Modifier' : 'Créer' ?> un Événement</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="navbar.css">

  
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: "Poppins", sans-serif;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: #333;
      padding: 40px 20px;
      min-height: 100vh;
    }

    .container {
      max-width: 900px;
      margin: 0 auto;
    }

    .page-header {
      text-align: center;
      margin-bottom: 30px;
      color: #fff;
    }

    .page-header h2 {
      font-size: 2rem;
      font-weight: 700;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 12px;
      margin-bottom: 8px;
    }

    .page-header p {
      font-size: 1rem;
      opacity: 0.95;
    }

    form {
      background-color: #fff;
      padding: 40px;
      border-radius: 20px;
      box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
    }

    .form-section {
      margin-bottom: 35px;
    }

    .section-title {
      color: #667eea;
      font-size: 1.3rem;
      font-weight: 700;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 10px;
      padding-bottom: 10px;
      border-bottom: 2px solid #e2e8f0;
    }

    .form-group {
      margin-bottom: 20px;
    }

    label {
      display: block;
      font-weight: 600;
      margin-bottom: 8px;
      color: #2d3748;
      font-size: 0.95rem;
      display: flex;
      align-items: center;
      gap: 6px;
    }

    label i {
      color: #667eea;
      font-size: 1.1em;
    }

    .required {
      color: #e53e3e;
      margin-left: 4px;
    }

    input[type="text"],
    input[type="email"],
    input[type="date"],
    input[type="time"],
    input[type="number"],
    textarea,
    select {
      width: 100%;
      padding: 12px 16px;
      border: 2px solid #e2e8f0;
      border-radius: 10px;
      font-size: 0.95rem;
      background-color: #f7fafc;
      font-family: "Poppins", sans-serif;
      transition: all 0.3s;
    }

    input:focus,
    textarea:focus,
    select:focus {
      outline: none;
      border-color: #667eea;
      background-color: #fff;
      box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    textarea {
      resize: vertical;
      min-height: 120px;
    }

    .grid-2 {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
    }

    /* Image Upload Section */
    .image-upload {
      border: 3px dashed #cbd5e0;
      border-radius: 12px;
      padding: 40px 20px;
      text-align: center;
      color: #667eea;
      cursor: pointer;
      transition: all 0.3s;
      background: #f7fafc;
    }

    .image-upload:hover {
      background-color: #edf2f7;
      border-color: #667eea;
    }

    .image-upload i {
      font-size: 3rem;
      color: #667eea;
      margin-bottom: 15px;
      display: block;
    }

    .image-upload-text {
      font-weight: 600;
      font-size: 1.05rem;
      margin-bottom: 5px;
      color: #2d3748;
    }

    .image-upload-hint {
      font-size: 0.85rem;
      color: #718096;
    }

    input[type="file"] {
      display: none;
    }

    #preview {
      display: none;
      margin-top: 20px;
      max-width: 100%;
      max-height: 300px;
      border-radius: 12px;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }

    /* Current image display */
    .current-image-container {
      margin-top: 20px;
      text-align: center;
    }

    .current-image-label {
      font-size: 0.9rem;
      color: #667eea;
      font-weight: 600;
      margin-bottom: 10px;
      display: block;
    }

    .current-image {
      max-width: 100%;
      max-height: 300px;
      border-radius: 12px;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }

    /* Prix input avec icône */
    .input-with-icon {
      position: relative;
    }

    .input-with-icon i {
      position: absolute;
      left: 16px;
      top: 50%;
      transform: translateY(-50%);
      color: #718096;
      font-size: 1.1em;
    }

    .input-with-icon input {
      padding-left: 45px;
    }

    /* Champ "Autre" caché par défaut */
    #autreTypeContainer {
      display: none;
      margin-top: 15px;
    }

    /* Buttons */
    .button-group {
      display: flex;
      gap: 15px;
      margin-top: 35px;
      padding-top: 25px;
      border-top: 2px solid #e2e8f0;
    }

    .btn {
      border: none;
      padding: 14px 30px;
      border-radius: 12px;
      cursor: pointer;
      font-size: 1rem;
      font-weight: 600;
      flex: 1;
      transition: all 0.3s;
      text-align: center;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      font-family: "Poppins", sans-serif;
    }

    .btn-primary {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: #fff;
      box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
    }

    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
    }

    .btn-secondary {
      background-color: #e2e8f0;
      color: #2d3748;
    }

    .btn-secondary:hover {
      background-color: #cbd5e0;
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    /* Info box */
    .info-box {
      background: linear-gradient(135deg, #ebf8ff 0%, #e6fffa 100%);
      border-left: 4px solid #667eea;
      padding: 16px 20px;
      border-radius: 10px;
      margin-bottom: 25px;
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .info-box i {
      font-size: 1.5rem;
      color: #667eea;
    }

    .info-box-text {
      font-size: 0.9rem;
      color: #2d3748;
    }

    /* Responsive */
    @media (max-width: 768px) {
      body {
        padding: 20px 15px;
      }

      form {
        padding: 30px 20px;
      }

      .grid-2 {
        grid-template-columns: 1fr;
      }

      .button-group {
        flex-direction: column;
      }

      .page-header h2 {
        font-size: 1.5rem;
      }
    }

    /* Animation au chargement */
    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    form {
      animation: fadeInUp 0.5s ease-out;
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
  <div class="user-menu">

    <div class="dropdown" id="userDropdown">
      <a href="#">Mon profil</a>
      <a href="../logout.php">Se déconnecter</a>
    </div>
  </div>
</div>

  <div class="container">
    <div class="page-header">
      <h2>
        <i class="bi bi-calendar-plus"></i>
        <?= $mode === 'edit' ? 'Modifier l\'événement' : 'Créer un Événement' ?>
      </h2>
      <p><?= $mode === 'edit' ? 'Modifiez les informations de votre événement' : 'Remplissez les informations pour créer votre événement' ?></p>
    </div>

    <form method="POST" action="recap.php" enctype="multipart/form-data">
      
      <!-- Section: Informations générales -->
      <div class="form-section">
        <h3 class="section-title">
          <i class="bi bi-info-circle"></i>
          Informations générales
        </h3>

        <div class="info-box">
          <i class="bi bi-lightbulb"></i>
          <div class="info-box-text">
            <strong>Conseil :</strong> Soyez précis et attractif dans votre titre et description pour attirer plus de participants.
          </div>
        </div>

        <div class="form-group">
          <label>
            <i class="bi bi-building"></i>
            Nom du club<span class="required">*</span>
          </label>
          <input type="text" name="club" placeholder="Ex: Club Robotique" required value="<?= htmlspecialchars($eventData['club'] ?? '') ?>">
        </div>

        <div class="form-group">
          <label>
            <i class="bi bi-card-heading"></i>
            Titre de l'événement<span class="required">*</span>
          </label>
          <input type="text" name="titre" placeholder="Ex: Workshop Arduino pour débutants" required value="<?= htmlspecialchars($eventData['titre'] ?? '') ?>">
        </div>

        <div class="form-group">
          <label>
            <i class="bi bi-tag"></i>
            Type d'événement<span class="required">*</span>
          </label>
          <select name="type" id="typeSelect" required>
            <option value="">Sélectionner un type</option>
            <option <?= (isset($eventData['type']) && $eventData['type'] == 'Workshop') ? 'selected' : '' ?>>Workshop</option>
            <option <?= (isset($eventData['type']) && $eventData['type'] == 'Conférence') ? 'selected' : '' ?>>Conférence</option>
            <option <?= (isset($eventData['type']) && $eventData['type'] == 'Formation') ? 'selected' : '' ?>>Formation</option>
            <option <?= (isset($eventData['type']) && $eventData['type'] == 'Bénévolat') ? 'selected' : '' ?>>Bénévolat</option>
            <option <?= (isset($eventData['type']) && $eventData['type'] == 'Sortie') ? 'selected' : '' ?>>Sortie</option>
            <option <?= (isset($eventData['type']) && $eventData['type'] == 'Compétition') ? 'selected' : '' ?>>Compétition</option>
            <option <?= (isset($eventData['type']) && $eventData['type'] == 'Séminaire') ? 'selected' : '' ?>>Séminaire</option>
            <option <?= (isset($eventData['type']) && $eventData['type'] == 'Hackathon') ? 'selected' : '' ?>>Hackathon</option>
            <option <?= (isset($eventData['type']) && $eventData['type'] == 'Autre') ? 'selected' : '' ?>>Autre</option>
          </select>
        </div>

        <!-- Champ "Autre" qui s'affiche conditionnellement -->
        <div class="form-group" id="autreTypeContainer">
          <label>
            <i class="bi bi-pencil"></i>
            Précisez le type<span class="required">*</span>
          </label>
          <input type="text" name="type_autre" id="typeAutre" placeholder="Ex: Journée portes ouvertes" value="<?= htmlspecialchars($eventData['type_autre'] ?? '') ?>">
        </div>

        <div class="form-group">
          <label>
            <i class="bi bi-text-paragraph"></i>
            Description<span class="required">*</span>
          </label>
          <textarea name="description" maxlength="500" placeholder="Décrivez votre événement, les objectifs, le programme..." required><?= htmlspecialchars($eventData['description'] ?? '') ?></textarea>
        </div>
      </div>

      <!-- Section: Image -->
      <div class="form-section">
        <h3 class="section-title">
          <i class="bi bi-image"></i>
          Image de l'événement
        </h3>

        <?php if ($mode === 'edit' && !empty($eventData['photo']) && $eventData['photo'] !== '0'): ?>
          <div class="current-image-container">
            <span class="current-image-label">
              <i class="bi bi-image-fill"></i> Image actuelle
            </span>
            <?php
            // Vérifier si le fichier existe
            $photoPath = $eventData['photo'];
            if (file_exists($photoPath)):
            ?>
              <img src="<?= htmlspecialchars($photoPath) ?>?v=<?= time() ?>" alt="Image actuelle" class="current-image" id="currentImage" onerror="this.style.display='none'; this.parentElement.innerHTML+='<p style=\'color: #e53e3e; margin-top: 10px;\'>Image introuvable</p>';">
            <?php else: ?>
              <p style="color: #e53e3e; text-align: center; margin-top: 10px;">
                <i class="bi bi-exclamation-triangle"></i> Image introuvable : <?= htmlspecialchars($photoPath) ?>
              </p>
            <?php endif; ?>
          </div>
          <input type="hidden" name="photo_actuelle" value="<?= htmlspecialchars($eventData['photo']) ?>">
          <p style="text-align: center; margin: 15px 0; color: #718096; font-size: 0.9rem;">
            Téléchargez une nouvelle image pour remplacer l'image actuelle
          </p>
        <?php endif; ?>

        <div class="image-upload" onclick="document.getElementById('image').click()">
          <i class="bi bi-cloud-upload"></i>
          <div class="image-upload-text"><?= $mode === 'edit' ? 'Changer l\'image' : 'Glissez l\'image ici ou cliquez pour parcourir' ?></div>
          <div class="image-upload-hint">JPG, PNG – Max 5MB</div>
        </div>
        <input type="file" name="image" id="image" accept="image/png, image/jpeg">
        <img id="preview" alt="Aperçu de l'image">
      </div>

      <!-- Section: Date et lieu -->
      <div class="form-section">
        <h3 class="section-title">
          <i class="bi bi-calendar-event"></i>
          Date et lieu
        </h3>

        <div class="grid-2">
          <div class="form-group">
            <label>
              <i class="bi bi-calendar-check"></i>
              Date de début<span class="required">*</span>
            </label>
            <input type="date" name="date_debut" id="dateDebut" required value="<?= htmlspecialchars($eventData['date_debut'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label>
              <i class="bi bi-clock"></i>
              Heure de début<span class="required">*</span>
            </label>
            <input type="time" name="heure_debut" required value="<?= htmlspecialchars($eventData['heure_debut'] ?? '') ?>">
          </div>
        </div>

        <div class="grid-2">
          <div class="form-group">
            <label>
              <i class="bi bi-calendar-x"></i>
              Date de fin<span class="required">*</span>
            </label>
            <input type="date" name="date_fin" id="dateFin" required value="<?= htmlspecialchars($eventData['date_fin'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label>
              <i class="bi bi-clock-fill"></i>
              Heure de fin<span class="required">*</span>
            </label>
            <input type="time" name="heure_fin" required value="<?= htmlspecialchars($eventData['heure_fin'] ?? '') ?>">
          </div>
        </div>

        <div class="form-group">
          <label>
            <i class="bi bi-geo-alt"></i>
            Lieu<span class="required">*</span>
          </label>
          <input type="text" name="lieu" placeholder="Salle, bâtiment ou adresse complète" required value="<?= htmlspecialchars($eventData['lieu'] ?? '') ?>">
        </div>

        <div class="form-group">
          <label>
            <i class="bi bi-calendar-range"></i>
            Date limite d'inscription<span class="required">*</span>
          </label>
          <input type="date" name="date_limite" id="dateLimite" required value="<?= htmlspecialchars($eventData['date_limite'] ?? '') ?>">
        </div>
      </div>

      <!-- Section: Inscription -->
      <div class="form-section">
        <h3 class="section-title">
          <i class="bi bi-ticket-perforated"></i>
          Inscription et tarification
        </h3>

        <div class="form-group">
          <label>
            <i class="bi bi-people"></i>
            Capacité maximale<span class="required">*</span>
          </label>
          <input type="number" name="capacite" placeholder="50" min="1" required value="<?= htmlspecialchars($eventData['capacite'] ?? '') ?>">
        </div>

        <div class="grid-2">
          <div class="form-group">
            <label>
              <i class="bi bi-person-check"></i>
              Prix adhérent (DH)<span class="required">*</span>
            </label>
            <div class="input-with-icon">
              <i class="bi bi-currency-dollar"></i>
              <input type="number" name="prix_adherent" placeholder="0" min="0" step="0.01" required value="<?= htmlspecialchars($eventData['prix_adherent'] ?? '0') ?>">
            </div>
          </div>
          <div class="form-group">
            <label>
              <i class="bi bi-person-x"></i>
              Prix non-adhérent (DH)<span class="required">*</span>
            </label>
            <div class="input-with-icon">
              <i class="bi bi-currency-dollar"></i>
              <input type="number" name="prix_non_adherent" placeholder="0" min="0" step="0.01" required value="<?= htmlspecialchars($eventData['prix_non_adherent'] ?? '0') ?>">
            </div>
          </div>
        </div>
      </div>

      <?php if ($mode === 'edit'): ?>
        <input type="hidden" name="Idenv" value="<?= htmlspecialchars($eventData['Idenv']) ?>">
        <input type="hidden" name="action" value="update">
      <?php endif; ?>

      <!-- Buttons -->
      <div class="button-group">
        <a href="Evenements.php" class="btn btn-secondary">
          <i class="bi bi-x-circle"></i>
          Annuler
        </a>
        <button type="submit" class="btn btn-primary">
          <i class="bi bi-<?= $mode === 'edit' ? 'check-circle' : 'plus-circle' ?>"></i>
          <?= $mode === 'edit' ? 'Mettre à jour' : 'Créer l\'événement' ?>
        </button>
      </div>
    </form>
  </div>

  <script>
    const inputFile = document.getElementById('image');
    const preview = document.getElementById('preview');
    const uploadArea = document.querySelector('.image-upload');
    const typeSelect = document.getElementById('typeSelect');
    const autreTypeContainer = document.getElementById('autreTypeContainer');
    const typeAutre = document.getElementById('typeAutre');
    const currentImage = document.getElementById('currentImage');

    // Gérer l'affichage du champ "Autre"
    typeSelect.addEventListener('change', function() {
      if (this.value === 'Autre') {
        autreTypeContainer.style.display = 'block';
        typeAutre.required = true;
      } else {
        autreTypeContainer.style.display = 'none';
        typeAutre.required = false;
        typeAutre.value = '';
      }
    });

    // Vérifier au chargement de la page si "Autre" est sélectionné
    if (typeSelect.value === 'Autre') {
      autreTypeContainer.style.display = 'block';
      typeAutre.required = true;
    }

    // Prévisualisation de l'image
    inputFile.addEventListener('change', function() {
      const file = this.files[0];
      if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
          preview.src = e.target.result;
          preview.style.display = 'block';
          // Cacher l'image actuelle si une nouvelle est sélectionnée
          if (currentImage) {
            currentImage.style.opacity = '0.5';
          }
        };
        reader.readAsDataURL(file);
      } else {
        preview.style.display = 'none';
        if (currentImage) {
          currentImage.style.opacity = '1';
        }
      }
    });

    // Drag and drop
    uploadArea.addEventListener('dragover', (e) => {
      e.preventDefault();
      uploadArea.style.borderColor = '#667eea';
      uploadArea.style.background = '#edf2f7';
    });

    uploadArea.addEventListener('dragleave', () => {
      uploadArea.style.borderColor = '#cbd5e0';
      uploadArea.style.background = '#f7fafc';
    });

    uploadArea.addEventListener('drop', (e) => {
      e.preventDefault();
      uploadArea.style.borderColor = '#cbd5e0';
      uploadArea.style.background = '#f7fafc';
      
      const file = e.dataTransfer.files[0];
      if (file && file.type.startsWith('image/')) {
        inputFile.files = e.dataTransfer.files;
        const reader = new FileReader();
        reader.onload = function(e) {
          preview.src = e.target.result;
          preview.style.display = 'block';
          if (currentImage) {
            currentImage.style.opacity = '0.5';
          }
        };
        reader.readAsDataURL(file);
      }
    });

    // Validation des dates
    const dateDebut = document.getElementById('dateDebut');
    const dateFin = document.getElementById('dateFin');
    const dateLimite = document.getElementById('dateLimite');

    dateDebut.addEventListener('change', () => {
      dateFin.min = dateDebut.value;
      dateLimite.max = dateDebut.value;
    });

    // Définir la date minimum à aujourd'hui
    const today = new Date().toISOString().split('T')[0];
    dateDebut.min = today;
    dateLimite.min = today;
  </script>
</body>
</html>