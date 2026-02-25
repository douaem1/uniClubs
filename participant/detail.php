<?php
    include '../ConnDB.php';
    include 'header.php';

    if (!isset($_GET['id']) || empty($_GET['id'])) {
        die("Événement invalide.");
    }
    $Idenv = intval($_GET['id']);
    
    // Récupérer les infos de l'événement
    $stmt = $conn->prepare("
        SELECT e.*, c.NomClub, comp.Email AS EmailOrganisateur
        FROM Evenement e
        LEFT JOIN Club c ON e.idClub = c.idClub
        LEFT JOIN Organisateur o ON e.idOrganisateur = o.idCompte
        LEFT JOIN Compte comp ON o.idCompte = comp.idCompte
        WHERE e.Idenv = ?
    ");
    $stmt->bind_param("i", $Idenv);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        die("Événement introuvable.");
    }

    $event = $result->fetch_assoc();
?>

<link rel="stylesheet" href="assets/style/detail.css">
<div class="detail-container">
    <div class="detail-card">
    <button type="button" class="close-btn" onclick="window.history.back()">&times;</button> 
        <div class="detail-header">
            <h2 class="detail-title"><i class="bi bi-bookmarks"></i>
                <?php echo htmlspecialchars($event['NomEnv']);?>
            </h2>  
        </div>

        <div class="detail-cover">
            <img src="<?php echo htmlspecialchars('../organisateur/' . ($event['photo'] ?? 'default.jpg')); ?>" alt="Image de l'événement">
        </div>

        <div class=" detail-grid detail-item">
                <label class="detail-label"><i class="bi bi-card-heading"></i> Description</label>
                <output class="detail-value"><?php echo htmlspecialchars($event['discription'] ?? '—'); ?></output>
        </div>
        
        <div class="detail-grid">
            <div class="detail-item">
                <label class="detail-label"><i class="bi bi-people"></i> Club organisateur</label>
                <output class="detail-value"><?php echo htmlspecialchars($event['NomClub'] ?? '—'); ?></output>
            </div>
            <div class="detail-item">
                <label class="detail-label"><i class="bi bi-envelope"></i> Contact organisateur</label>
                <output class="detail-value"><?php echo htmlspecialchars($event['EmailOrganisateur'] ?? '—'); ?></output>
            </div>
            <div class="detail-item">
                <label class="detail-label"><i class="bi bi-tag"></i> Type d'événement</label>
                <output class="detail-value"><?php echo htmlspecialchars($event['Type'] ?? '—'); ?></output>
            </div>
            <div class="detail-item">
                <label class="detail-label"><i class="bi bi-calendar2-x"></i> Fin d'inscription</label>
                <output class="detail-value"><?php echo htmlspecialchars($event['finInscription'] ?? '—'); ?></output>
            </div>
            <div class="detail-item">
                <label class="detail-label"><i class="bi bi-calendar-event"></i> Date de début</label>
                <output class="detail-value"><?php echo htmlspecialchars($event['dateDebut'] ?? '—'); ?></output>
            </div>
            <div class="detail-item">
                <label class="detail-label"><i class="bi bi-calendar4-week"></i> Date de fin</label>
                <output class="detail-value"><?php echo htmlspecialchars($event['dateFin'] ?? '—'); ?></output>
            </div>
            <div class="detail-item">
                <label class="detail-label"><i class="bi bi-calendar-event"></i> Heure de début</label>
                <output class="detail-value"><?php echo htmlspecialchars($event['heureDebut'] ?? '—'); ?></output>
            </div>
            <div class="detail-item">
                <label class="detail-label"><i class="bi bi-calendar4-week"></i> Heure de fin</label>
                <output class="detail-value"><?php echo htmlspecialchars($event['heureFin'] ?? '—'); ?></output>
            </div>
            <div class="detail-item">
                <label class="detail-label"><i class="bi bi-geo-alt"></i> Lieu</label>
                <output class="detail-value"><?php echo htmlspecialchars($event['Lieu'] ?? '—'); ?></output>
            </div>
            <div class="detail-item">
                <label class="detail-label"><i class="bi bi-cash"></i> Prix Adherent </label>
                <output class="detail-value">
                    <?php
                    if (isset($event['prixAdherent'])) {
                        echo htmlspecialchars($event['prixAdherent'] . ' DH');
                    } else {
                        echo htmlspecialchars('—');
                    }
                    ?>
                </output>
            </div>
            <div class="detail-item">
                <label class="detail-label"><i class="bi bi-cash"></i> Prix Non Adherent</label>
                <output class="detail-value">
                    <?php
                    if (isset($event['prixAdherent'])) {
                        echo htmlspecialchars($event['prixNonAdherent'] . ' DH');
                    } else {
                        echo htmlspecialchars('—');
                    }
                    ?>
                </output>
            </div>
        </div>

        

        <div class="detail-actions">
            <?php
                if (isset($event['Statut']) && $event['Statut'] === 'saturé') {
                    echo '<button class="btn-disabled" disabled>Saturé</button>';
                } else {
                    echo '<a href="inscription.php?id=' . $event['Idenv'] . '" class="btn-primary">S\'inscrire</a>';
                }
            ?>
        </div>
    </div>
</div>
