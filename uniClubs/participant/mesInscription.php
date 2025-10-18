<?php
    include '../ConnDB.php';
    include 'header.php';

    $idParticipant = $_SESSION['idCompte'];

    // Préparer la requête
    $sql = "SELECT e.photo, e.Idenv, e.NomEnv, e.dateDebut, e.Lieu, c.NomClub, i.dateIcription
            FROM inscri i
            INNER JOIN evenement e ON i.Idenv = e.Idenv
            LEFT JOIN club c ON e.idClub = c.idClub
            WHERE i.idParticipant = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $idParticipant);
    $stmt->execute();
    $result = $stmt->get_result();
?>
<link rel="stylesheet" href="assets/style/mesInscription.css">
<body class="bg-light">
<div class="container mt-5">
    <div class="row">
        <?php if ($result->num_rows > 0): ?>
            <?php while($row = $result->fetch_assoc()): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card shadow-sm h-100 border-0">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($row['NomEnv']); ?></h5>
                            <div class="detail-cover">
                                <img src="<?php echo htmlspecialchars('../organisateur/' . ($row['photo'] ?? 'default.jpg')); ?>" alt="Image de l'événement">
                            </div>
                            <p class="card-text mb-1">
                                <i class="bi bi-people me-2"></i><?php echo htmlspecialchars($row['NomClub']); ?>
                            </p>
                            <p class="card-text mb-1">
                                <i class="bi bi-calendar-event me-2"></i><?php echo htmlspecialchars($row['dateDebut']); ?>
                            </p>
                            <p class="card-text mb-1">
                                <i class="bi bi-geo-alt me-2"></i><?php echo htmlspecialchars($row['Lieu']); ?>
                            </p>
                            <p class="card-text small text-secondary">
                                Inscrit le : <?php echo htmlspecialchars($row['dateIcription']); ?>
                            </p>
                        </div>
                        <div class="card-footer bg-white border-0">
                            <a href="detail.php?id=<?php echo $row['Idenv']; ?>" class="btn btn-primary w-100">Détails</a>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p class="text-muted">Vous n'êtes inscrit à aucun événement pour le moment</p>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
