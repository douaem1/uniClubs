<?php
include 'db_events.php';

// Récupérer le nom du club depuis le formulaire ou une variable
$nomClub = isset($_POST['club']) && !empty($_POST['club']) ? trim($_POST['club']) : 'Club Informatique';

// 1. Récupérer l'idClub à partir du nom du club
$stmtClub = $conn->prepare("SELECT idClub FROM Club WHERE LOWER(NomClub) = LOWER(:nomclub)");
$stmtClub->execute(['nomclub' => $nomClub]);
$club = $stmtClub->fetch(PDO::FETCH_ASSOC);
$idclub = $club ? $club['idClub'] : null;

// 2. Si le club existe, récupérer les événements pour ce club
$events = [];
if ($idclub) {
    $stmt = $conn->prepare("SELECT * FROM Evenement WHERE idClub = :idclub ORDER BY dateDebut DESC");
    $stmt->execute(['idclub' => $idclub]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Gestion des événements</title>
  <style>
    body { background: #f7f7fb; font-family: 'Poppins', Arial, sans-serif; margin: 0; }
    .container { max-width: 900px; margin: 40px auto; }
    .header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; }
    .header h2 { font-size: 1.5rem; color: #2d2350; }
    .btn-add { background: #8f5cff; color: #fff; border: none; border-radius: 22px; padding: 10px 28px; font-weight: 600; font-size: 1em; cursor: pointer; box-shadow: 0 2px 8px #e6e0fa; transition: background 0.18s; }
    .btn-add:hover { background: #6a36ff; }
    .search-bar { width: 100%; margin-bottom: 24px; }
    .search-bar input { width: 100%; padding: 12px 18px; border-radius: 12px; border: 1px solid #ece6fa; font-size: 1em; background: #fff; }
    .event-list { display: flex; flex-direction: column; gap: 22px; }
    .event-card {
      display: flex; align-items: flex-start; background: #fff; border-radius: 16px;
      box-shadow: 0 2px 12px #ece6fa; padding: 18px 22px; gap: 18px; border: 1px solid #ece6fa;
      transition: box-shadow 0.18s;
    }
    .event-card:hover { box-shadow: 0 8px 32px #e6e0fa; }
    .event-img { width: 90px; height: 90px; border-radius: 10px; object-fit: cover; margin-right: 18px; background: #f3f0ff; border: 1.5px solid #ece6fa; }
    .event-info { flex: 1; display: flex; flex-direction: column; gap: 6px; }
    .event-title { font-size: 1.13rem; font-weight: 600; color: #2d2350; margin-bottom: 2px; }
    .event-meta { color: #7c6bb1; font-size: 0.98em; display: flex; align-items: center; gap: 14px; }
    .event-meta span { display: flex; align-items: center; gap: 4px; }
    .event-lieu { color: #7c6bb1; font-size: 0.98em; }
    .event-progress-bar-bg { background: #ece6fa; border-radius: 8px; height: 8px; width: 100%; margin-top: 8px; }
    .event-progress-bar { background: #8f5cff; height: 8px; border-radius: 8px; }
    .event-inscrits { color: #6a36ff; font-size: 0.97em; margin-top: 6px; }
    .edit-link { color: #8f5cff; margin-left: 10px; font-size: 1.2em; text-decoration: none; transition: color 0.18s; }
    .edit-link:hover { color: #6a36ff; }
    @media (max-width: 700px) {
      .container { padding: 0 2vw; }
      .event-card { flex-direction: column; align-items: stretch; }
      .event-img { margin: 0 auto 12px auto; }
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <a href="formevent.php" style="color:#6a36ff;text-decoration:none;font-weight:500;">&lt; Retour</a>
      <h2>Gestion des événements</h2>
      <a href="formevent.php" class="btn-add">+ Nouvel événement</a>
    </div>
    <div class="search-bar">
      <input type="text" placeholder="Rechercher...">
    </div>
    <div class="event-list">
      <?php foreach ($events as $event): 
        // Nombre d'inscrits pour cet événement (table inscri)
        $stmtInscrits = $conn->prepare("SELECT COUNT(*) FROM inscri WHERE Idenv = :id");
        $stmtInscrits->execute(['id' => $event['Idenv']]);
        $inscrits = $stmtInscrits->fetchColumn();
        $progress = ($event['Capacite'] > 0) ? min(100, round(($inscrits / $event['Capacite']) * 100)) : 0;
        $img = (!empty($event['photo'])) ? $event['photo'] : 'default.jpg';
      ?>
      <div class="event-card">
        <img src="<?= htmlspecialchars($img) ?>" class="event-img" alt="Image">
        <div class="event-info">
          <div class="event-title"><?= htmlspecialchars($event['NomEnv']) ?></div>
          <div class="event-meta">
            <span>📅 <?= date('d/m/Y', strtotime($event['dateDebut'])) ?></span>
            <span>📍 <?= htmlspecialchars($event['Lieu']) ?></span>
          </div>
          <div class="event-inscrits"><?= $inscrits ?>/<?= $event['Capacite'] ?> inscrits</div>
          <div class="event-progress-bar-bg">
            <div class="event-progress-bar" style="width:<?= $progress ?>%"></div>
          </div>
        </div>
        <a href="formevent.php?edit=<?= urlencode($event['Idenv']) ?>" class="edit-link" title="Modifier">&#9998;</a>
      </div>
      <?php endforeach; ?>
    </div>
    <?php if (!$idclub): ?>
      <div style='color:red'>Aucun club trouvé pour le nom : <b><?= htmlspecialchars($nomClub) ?></b></div>
    <?php endif; ?>
  </div>
</body>
</html>