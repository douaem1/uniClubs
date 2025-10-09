<?php

$mode = $_POST['mode'] ?? 'create';
$eventData = [];

if ($mode === 'edit' && isset($_POST['Idenv'])) {
    $eventData = [
        'Idenv' => $_POST['Idenv'] ?? '',
        'titre' => $_POST['titre'] ?? '',
        'description' => $_POST['description'] ?? '',
        'lieu' => $_POST['lieu'] ?? '',
        'date_debut' => $_POST['date_debut'] ?? '',
        'date_fin' => $_POST['date_fin'] ?? '',
        'capacite' => $_POST['capacite'] ?? '',
        'date_limite' => $_POST['date_limite'] ?? '',
        'club' => $_POST['club'] ?? '',
        'type' => $_POST['type'] ?? '',
    ];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title><?= $mode === 'edit' ? 'Modifier' : 'Créer' ?> un Événement</title>
  <style>
    body {
      font-family: "Poppins", sans-serif;
      background-color: #f8f8fb;
      color: #333;
      padding: 40px;
    }

    h2 {
      color: #5a2be7cc;
      font-size: 22px;
      margin-bottom: 20px;
      text-align: center;
    }

    form {
      background-color: #fff;
      padding: 30px 40px;
      border-radius: 15px;
      max-width: 700px;
      margin: auto;
      box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }

    label {
      display: block;
      font-weight: 600;
      margin-bottom: 5px;
      margin-top: 15px;
    }

    input[type="text"],
    input[type="email"],
    input[type="date"],
    input[type="number"],
    textarea,
    select {
      width: 100%;
      padding: 10px 12px;
      border: 1px solid #ddd;
      border-radius: 8px;
      font-size: 14px;
      background-color: #fafafa;
      box-sizing: border-box;
    }

    textarea {
      resize: vertical;
      min-height: 80px;
    }

    .image-upload {
      border: 2px dashed #b9a8f9;
      border-radius: 10px;
      padding: 40px;
      text-align: center;
      color: #8a7be0;
      margin-top: 20px;
      cursor: pointer;
      transition: 0.3s;
    }

    .image-upload:hover {
      background-color: #f3f0ff;
    }

    .grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
    }

    input[type="submit"] {
      background-color: #6a36ff;
      color: #fff;
      border: none;
      padding: 12px 25px;
      border-radius: 10px;
      cursor: pointer;
      margin-top: 25px;
      font-size: 16px;
      font-weight: 600;
      width: 100%;
      transition: 0.3s;
    }

    input[type="submit"]:hover {
      background-color: #5328d8;
    }

    /* Cache l'input file mais rend le label cliquable */
    input[type="file"] {
      display: none;
    }

    /*  Style pour l'aperçu */
    #preview {
      display: none;
      margin-top: 15px;
      max-width: 100%;
      border-radius: 10px;
      box-shadow: 0 3px 8px rgba(0,0,0,0.2);
    }

    h3 {
      color: #5a2be7cc;
      font-size: 18px;
      margin-top: 25px;
      margin-bottom: 10px;
    }
  </style>
</head>

<body>
  <h2><?= $mode === 'edit' ? 'Modifier l\'événement' : 'Créer un Événement' ?></h2>

  <form method="POST" action="recap.php" enctype="multipart/form-data">

    <label>Nom du club*</label>
    <input type="text" name="club" placeholder="Ex: Club Robotique" required value="<?= htmlspecialchars($eventData['club'] ?? '') ?>">

    <label>Titre de l'événement*</label>
    <input type="text" name="titre" placeholder="Ex: Workshop Arduino" required value="<?= htmlspecialchars($eventData['titre'] ?? '') ?>">

    <label>Type d'événement*</label>
    <select name="type" required>
      <option value="">Sélectionner un type</option>
      <option <?= (isset($eventData['type']) && $eventData['type'] == 'Workshop') ? 'selected' : '' ?>>Workshop</option>
      <option <?= (isset($eventData['type']) && $eventData['type'] == 'Conférence') ? 'selected' : '' ?>>Conférence</option>
      <option <?= (isset($eventData['type']) && $eventData['type'] == 'Formation') ? 'selected' : '' ?>>Formation</option>
      <option <?= (isset($eventData['type']) && $eventData['type'] == 'Autre') ? 'selected' : '' ?>>Autre</option>
    </select>

    <label>Description*</label>
    <textarea name="description" maxlength="500" placeholder="Décrivez votre événement..." required><?= htmlspecialchars($eventData['description'] ?? '') ?></textarea>

    <div class="image-upload">
      <label for="image">
         Glisser l'image ici ou cliquer pour parcourir<br>
        <small>JPG, PNG – Max 5MB</small>
      </label>
      <input type="file" name="image" id="image" accept="image/png, image/jpeg">
      <img id="preview" alt="Aperçu de l'image">
    </div>

    <h3>Date et lieu</h3>
    <div class="grid">
      <div>
        <label>Date de début*</label>
        <input type="date" name="date_debut" required value="<?= htmlspecialchars($eventData['date_debut'] ?? '') ?>">
      </div>
      <div>
        <label>Date de fin*</label>
        <input type="date" name="date_fin" required value="<?= htmlspecialchars($eventData['date_fin'] ?? '') ?>">
      </div>
    </div>

    <div class="grid">
      <div>
        <label>Lieu*</label>
        <input type="text" name="lieu" placeholder="Salle, bâtiment ou adresse" required value="<?= htmlspecialchars($eventData['lieu'] ?? '') ?>">
      </div>
      <div>
        <label>Capacité maximale*</label>
        <input type="number" name="capacite" placeholder="50" required value="<?= htmlspecialchars($eventData['capacite'] ?? '') ?>">
      </div>
    </div>

    <label>Date limite d'inscription*</label>
    <input type="date" name="date_limite" required value="<?= htmlspecialchars($eventData['date_limite'] ?? '') ?>">

    <?php if ($mode === 'edit'): ?>
      <input type="hidden" name="Idenv" value="<?= htmlspecialchars($eventData['Idenv']) ?>">
      <input type="hidden" name="action" value="update">
    <?php endif; ?>

    <input type="submit" value="<?= $mode === 'edit' ? 'Mettre à jour l\'événement' : 'Créer l\'événement' ?>">
  </form>

  <script>
    const inputFile = document.getElementById('image');
    const preview = document.getElementById('preview');

    inputFile.addEventListener('change', function() {
      const file = this.files[0];
      if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
          preview.src = e.target.result;
          preview.style.display = 'block';
        };
        reader.readAsDataURL(file);
      } else {
        preview.style.display = 'none';
      }
    });
  </script>
</body>
</html>