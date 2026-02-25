<?php
    include '../ConnDB.php';
    include 'header.php';

    $aujourdhui = date('Y-m-d');

    $sql = "SELECT e.*, c.NomClub
        FROM evenement e
        LEFT JOIN club c ON e.idClub = c.idClub
        WHERE e.etat = 'publié' AND e.dateDebut >= ?
        ORDER BY e.dateDebut ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $aujourdhui);
    $stmt->execute();
    $result = $stmt->get_result();

    if (isset($_SESSION['prenom'])) {
        $nom = htmlspecialchars($_SESSION['prenom']);
    } else {
        $nom = "utilisateur";
    }
?>

<link rel="stylesheet" href="assets/style/evenement.css">
<div class="container-fluid events-page mt-3 pt-2">
    
    <div class="greeting-wrap d-flex align-items-center justify-content-between mb-3">
        <div class="d-flex align-items-center gap-2">
            <h2 class="greeting-title fw-bold">Bonjour, <span class="greeting-name"><?php echo htmlspecialchars($nom); ?></span></h2>
        </div>
        <p class="greeting-sub d-none d-md-block">Découvrez les derniers événements et inscrivez-vous en un clic</p>
    </div>

    <!-- Barre de recherche et filtre -->
    <div class="row mb-4 justify-content-center">
        <div class="col-xl-7">
            <div class="search-filter d-flex align-items-center gap-1 p-2 p-md-3 bg-white rounded-4 shadow-sm">
                <div class="flex-grow-1 position-relative">
                    <i class="bi bi-search search-icon"></i>
                    <input type="text" id="search" class="form-control form-control-lg ps-5 rounded-3" placeholder="Rechercher un événement...">
                </div>
                <div class="dropdown-section">
                    <select id="club-filter" class="form-select club-select">
                        <option value="">Tous les clubs</option>
                        <?php
                        // Récupérer tous les clubs pour le filtre
                        $clubSql = "SELECT idClub, NomClub FROM club ORDER BY NomClub ASC";
                        $clubResult = $conn->query($clubSql);
                        if ($clubResult->num_rows > 0) {
                            while ($club = $clubResult->fetch_assoc()) {
                                echo '<option value="' . htmlspecialchars($club['NomClub']) . '">' . htmlspecialchars($club['NomClub']) . '</option>';
                            }
                        }
                        ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="row" id="events-container">
        <?php
            if ($result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    $statutClass = 'statut-' . strtolower(str_replace(' ', '-', $row['Statut'] ?? ''));

                    if ($row['Statut'] === 'saturé') {
                        $btnClass = 'btn btn-secondary btn-sm w-50 disabled';
                        $btnHref = '#';
                    } else {
                        $btnClass = 'btn btn-primary btn-sm w-50';
                        $btnHref = 'inscription.php?id=' . $row['Idenv'];
                    }

                    echo '<div class="col-lg-4 col-md-6 mb-4 event-box">';
                    echo '  <div class="card event-card h-100 border-0 shadow-sm">';
                    echo '    <div class="event-image-wrapper">';
                    echo '      <img src="' . htmlspecialchars('../organisateur/' . $row['photo'] ?? 'uploads/default.jpg') . '" class="card-img-top event-img" alt="Image événement">';
                    echo '      <span class="badge statut-pill ' . $statutClass . '">' . htmlspecialchars($row['Statut']) . '</span>';
                    echo '    </div>';
                    echo '    <div class="card-body">';
                    echo '      <h5 class="card-title mb-2">' . htmlspecialchars($row['NomEnv']) . '</h5>';
                    echo '      <p class="card-text desc text-muted mb-3">' . htmlspecialchars($row['discription']) . '</p>';
                    echo '      <div class="small text-muted d-flex flex-column gap-1 mb-3">';
                    echo '        <div><i class="bi bi-calendar-event me-2"></i>' . htmlspecialchars($row['dateDebut']) . '</div>';
                    echo '        <div><i class="bi bi-clock me-2"></i>' . htmlspecialchars($row['heureDebut']) . '</div>';
                    echo '        <div><i class="bi bi-geo-alt me-2"></i>' . htmlspecialchars($row['Lieu']) . '</div>';
                    echo '      </div>';
                    echo '      <div class="chips d-flex flex-wrap gap-2">';
                    echo '        <span class="chip chip-club"><i class="bi bi-people me-1"></i>' . htmlspecialchars($row['NomClub']) . '</span>';
                    echo '        <span class="chip chip-type">' . htmlspecialchars($row['Type']) . '</span>';
                    echo '      </div>';
                    echo '    </div>';
                    echo '    <div class="card-footer bg-white border-0 pt-0 pb-4 px-4">';
                    echo '      <div class="d-flex justify-content-between gap-2">';
                    echo '        <a href="detail.php?id=' . $row['Idenv'] . '" class="btn btn-light btn-sm w-50 border">Voir détails</a>';
                    echo '        <a href="' . $btnHref . '" class="' . $btnClass . '">S\'inscrire</a>';
                    echo '      </div>';
                    echo '    </div>';
                    echo '  </div>';
                    echo '</div>';
                }
            } else {
                echo "<p class='text-center'>Aucun événement disponible pour le moment.</p>";
            }
            $conn->close();
        ?>
    </div>
</div>
<script src="assets/js/evenement.js"></script>
</body>
</html>
