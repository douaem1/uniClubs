<?php
$pageTitle = "Supervision des événements";
include '../../ConnDB.php';
include '../includes/header.php';

// Filtres
$filtre_statut = $_GET['statut'] ?? 'tous';
$filtre_club = $_GET['club'] ?? 'tous';
$filtre_recherche = $_GET['search'] ?? '';
$filtre_date_debut = $_GET['date_debut'] ?? '';
$filtre_date_fin = $_GET['date_fin'] ?? '';

// Construction de la requête avec filtres
$sql = "
    SELECT e.*, 
           c.NomClub,
           p.Nom as NomOrg, p.Prenom as PrenomOrg,
           (SELECT COUNT(*) FROM inscri WHERE Idenv = e.Idenv) as nb_inscrits
    FROM Evenement e
    LEFT JOIN Club c ON e.idClub = c.idClub
    LEFT JOIN Organisateur o ON e.idOrganisateur = o.idCompte
    LEFT JOIN Compte co ON o.idCompte = co.idCompte
    LEFT JOIN Participant p ON o.idCompte = p.idCompte
    WHERE 1=1
";

$types = '';
$params = [];

if ($filtre_statut !== 'tous') {
    $sql .= " AND e.Statut = ?";
    $types .= 's';
    $params[] = $filtre_statut;
}

if ($filtre_club !== 'tous') {
    $sql .= " AND e.idClub = ?";
    $types .= 'i';
    $params[] = intval($filtre_club);
}

if (!empty($filtre_recherche)) {
    $sql .= " AND (e.NomEnv LIKE ? OR e.Lieu LIKE ?)";
    $types .= 'ss';
    $searchTerm = "%$filtre_recherche%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if (!empty($filtre_date_debut)) {
    $sql .= " AND e.dateDebut >= ?";
    $types .= 's';
    $params[] = $filtre_date_debut;
}

if (!empty($filtre_date_fin)) {
    $sql .= " AND e.dateFin <= ?";
    $types .= 's';
    $params[] = $filtre_date_fin;
}

$sql .= " ORDER BY e.dateDebut DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$evenements = [];
while ($row = $result->fetch_assoc()) {
    $evenements[] = $row;
}
$stmt->close();

// Si affichage des détails d'un événement
$eventDetails = null;
if (isset($_GET['view'])) {
    $idEvent = intval($_GET['view']);
    $stmt = $conn->prepare("
        SELECT e.Idenv, e.NomEnv, e.discription, e.dateDebut, e.heureDebut, e.heureFin, 
               e.prixAdherent, e.prixNonAdherent, e.dateFin, e.Lieu, e.Capacite, e.Type, 
               e.Statut, e.finInscription, e.photo, e.idOrganisateur, e.idClub, e.etat, e.validation,
               c.NomClub,
               p.Nom as NomOrg, p.Prenom as PrenomOrg, co.Email as EmailOrg,
               (SELECT COUNT(*) FROM inscri WHERE Idenv = e.Idenv) as nb_inscrits
        FROM Evenement e
        LEFT JOIN Club c ON e.idClub = c.idClub
        LEFT JOIN Organisateur o ON e.idOrganisateur = o.idCompte
        LEFT JOIN Compte co ON o.idCompte = co.idCompte
        LEFT JOIN Participant p ON o.idCompte = p.idCompte
        WHERE e.Idenv = ?
    ");
    $stmt->bind_param('i', $idEvent);
    $stmt->execute();
    $result = $stmt->get_result();
    $eventDetails = $result->fetch_assoc();
    $stmt->close();
}

// Récupérer la liste des clubs pour le filtre
$result_clubs = $conn->query("SELECT idClub, NomClub FROM Club ORDER BY NomClub");
$clubs = [];
while ($row = $result_clubs->fetch_assoc()) {
    $clubs[] = $row;
}

// Récupérer les statistiques par statut
$result_stats = $conn->query("
    SELECT Statut, COUNT(*) as count 
    FROM Evenement 
    GROUP BY Statut
");
$stats_statut = [];
while ($row = $result_stats->fetch_assoc()) {
    $stats_statut[$row['Statut']] = $row['count'];
}
?>

<div class="evenements-supervision">
    <!-- En-tête avec filtres -->
    <div class="card border-0 shadow-modern mb-4">
        <div class="card-header bg-gradient-light border-0 py-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <h5 class="mb-0 fw-bold text-dark">
                    <i class="bi bi-calendar3 me-2 text-primary"></i>Événements récents
                </h5>
                <div class="d-flex gap-2">
                    <span class="badge badge-modern badge-success-modern px-3 py-2">
                        <i class="bi bi-check-circle-fill me-1"></i> <?php echo $stats_statut['non saturé'] ?? 0; ?> Non saturés
                    </span>
                    <span class="badge badge-modern badge-danger-modern px-3 py-2">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i> <?php echo $stats_statut['saturé'] ?? 0; ?> Saturés
                    </span>
                </div>
            </div>
        </div>
    
        <!-- Filtres -->
        <div class="card-body bg-white py-3">
        <form method="GET" action="" class="filters-form">
            <div class="filter-group">
                <label for="search">Rechercher</label>
                <input type="text" 
                       id="search" 
                       name="search" 
                       placeholder="Nom ou lieu..." 
                       value="<?php echo htmlspecialchars($filtre_recherche); ?>">
            </div>
            
            <div class="filter-group">
                <label for="statut">Statut</label>
                <select id="statut" name="statut">
                    <option value="tous" <?php echo $filtre_statut === 'tous' ? 'selected' : ''; ?>>Tous</option>
                    <option value="non saturé" <?php echo $filtre_statut === 'non saturé' ? 'selected' : ''; ?>>Non saturé</option>
                    <option value="saturé" <?php echo $filtre_statut === 'saturé' ? 'selected' : ''; ?>>Saturé</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="club">Club</label>
                <select id="club" name="club">
                    <option value="tous" <?php echo $filtre_club === 'tous' ? 'selected' : ''; ?>>Tous les clubs</option>
                    <?php foreach ($clubs as $club): ?>
                    <option value="<?php echo $club['idClub']; ?>" <?php echo $filtre_club == $club['idClub'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($club['NomClub']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="date_debut">Date début</label>
                <input type="date" 
                       id="date_debut" 
                       name="date_debut" 
                       value="<?php echo htmlspecialchars($filtre_date_debut); ?>">
            </div>
            
            <div class="filter-group">
                <label for="date_fin">Date fin</label>
                <input type="date" 
                       id="date_fin" 
                       name="date_fin" 
                       value="<?php echo htmlspecialchars($filtre_date_fin); ?>">
            </div>
            
            <div class="filter-actions">
                <button type="submit" class="btn btn-primary-modern shadow-sm">
                    <i class="bi bi-funnel me-1"></i>Filtrer
                </button>
                <a href="events.php" class="btn btn-secondary-modern">
                    <i class="bi bi-arrow-clockwise me-1"></i>Réinitialiser
                </a>
            </div>
        </form>
        </div>
    </div>
    
    <!-- Liste des événements -->
    <div class="card border-0 shadow-modern mb-4">
        <div class="card-body p-0">
            <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 modern-table">
            <thead class="table-header-modern">
                <tr>
                    <th class="ps-4 fw-semibold text-uppercase">Événement</th>
                    <th class="fw-semibold text-uppercase">Club</th>
                    <th class="fw-semibold text-uppercase">Date début</th>
                    <th class="fw-semibold text-uppercase">Date fin</th>
                    <th class="fw-semibold text-uppercase">Lieu</th>
                    <th class="text-center pe-4 fw-semibold text-uppercase">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($evenements)): ?>
                <tr>
                    <td colspan="6" class="text-center py-5">
                        <i class="bi bi-calendar-x fs-1 text-muted d-block mb-2"></i>
                        <span class="text-muted">Aucun événement trouvé</span>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($evenements as $event): 
                        $dateDebut = new DateTime($event['dateDebut']);
                        $isToday = $dateDebut->format('Y-m-d') === date('Y-m-d');
                        $isPast = $dateDebut < new DateTime();
                    ?>
                    <tr class="table-row-modern <?php echo $isToday ? 'table-row-today' : ($isPast ? 'table-row-past' : ''); ?>">
                        <td class="ps-4 py-3">
                            <div class="d-flex align-items-center">
                                <div class="rounded-3 d-flex align-items-center justify-content-center me-3 shadow-sm" style="width: 48px; height: 48px; background: linear-gradient(135deg, #EDE9FE, #DDD6FE);">
                                    <i class="bi bi-calendar-event fs-5" style="color: #7C3AED;"></i>
                                </div>
                                <div>
                                    <div class="fw-semibold text-dark"><?php echo htmlspecialchars($event['NomEnv']); ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="py-3">
                            <span class="badge badge-club-modern">
                                <i class="bi bi-people-fill me-1"></i>
                                <?php echo htmlspecialchars($event['NomClub'] ?? 'N/A'); ?>
                            </span>
                        </td>
                        <td class="py-3">
                            <span class="fw-semibold text-dark"><?php echo $dateDebut->format('d/m/Y'); ?></span>
                        </td>
                        <td class="py-3">
                            <span class="text-dark"><?php echo $event['dateFin'] ? date('d/m/Y', strtotime($event['dateFin'])) : '-'; ?></span>
                        </td>
                        <td class="py-3">
                            <span class="text-dark">
                                <i class="bi bi-geo-alt-fill text-primary me-1"></i>
                                <?php echo htmlspecialchars($event['Lieu'] ?? 'Non défini'); ?>
                            </span>
                        </td>
                        <td class="text-center pe-4 py-3">
                            <div class="d-flex gap-2 justify-content-center action-buttons-modern">
                                <?php if (($event['validation'] ?? 'en attente') === 'validé'): ?>
                                    <span class="btn btn-sm btn-success-modern disabled" style="cursor: default;">
                                        <i class="bi bi-check-circle-fill me-1"></i> Validé
                                    </span>
                                <?php elseif (($event['validation'] ?? 'en attente') === 'refusé'): ?>
                                    <span class="btn btn-sm btn-danger-modern disabled" style="cursor: default;">
                                        <i class="bi bi-x-circle-fill me-1"></i> Refusé
                                    </span>
                                <?php else: ?>
                                    <a href="../actions/events.php?action=validate&id=<?php echo $event['Idenv']; ?>" 
                                       class="btn btn-sm btn-success-modern" 
                                       data-bs-toggle="tooltip" 
                                       title="Accepter l'événement">
                                        <i class="bi bi-check-lg"></i>
                                    </a>
                                    <button type="button" 
                                            onclick="openRejectModal(<?php echo $event['Idenv']; ?>, '<?php echo htmlspecialchars($event['NomEnv'], ENT_QUOTES); ?>')" 
                                            class="btn btn-sm btn-danger-modern" 
                                            data-bs-toggle="tooltip" 
                                            title="Refuser l'événement">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                <?php endif; ?>
                                <a href="events.php?view=<?php echo $event['Idenv']; ?>" 
                                   class="btn btn-sm btn-info-modern"
                                   data-bs-toggle="tooltip" 
                                   title="Voir les détails">
                                    <i class="bi bi-eye-fill"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Détails événement -->
<?php if ($eventDetails): ?>
<div id="viewEventModal" class="modal open">
    <div class="modal-content event-details-modal">
        <div class="event-details-header">
            <h2 class="event-details-title">Détails de l'événement</h2>
            <a href="events.php" class="close">&times;</a>
        </div>
        
        <?php if ($eventDetails['photo']): ?>
        <div class="event-image-section">
            <img src="../../organisateur/<?php echo htmlspecialchars($eventDetails['photo']); ?>" 
                 alt="<?php echo htmlspecialchars($eventDetails['NomEnv']); ?>" 
                 class="event-main-image">
        </div>
        <?php endif; ?>
        
        <div class="event-details-grid">
            <div>
                <div class="event-detail-item">
                    <div class="event-detail-label">Nom de l'événement</div>
                    <div class="event-detail-value"><?php echo htmlspecialchars($eventDetails['NomEnv']); ?></div>
                </div>
                
                <div class="event-detail-item">
                    <div class="event-detail-label">Date de début</div>
                    <div class="event-detail-value"><?php echo date('l d F Y', strtotime($eventDetails['dateDebut'])); ?></div>
                </div>
                
                <div class="event-detail-item">
                    <div class="event-detail-label">Date de fin</div>
                    <div class="event-detail-value"><?php echo $eventDetails['dateFin'] ? date('l d F Y', strtotime($eventDetails['dateFin'])) : 'Non définie'; ?></div>
                </div>
                
                <div class="event-detail-item">
                    <div class="event-detail-label">Heure de début</div>
                    <div class="event-detail-value"><?php echo $eventDetails['heureDebut'] ? htmlspecialchars($eventDetails['heureDebut']) : 'Non définie'; ?></div>
                </div>
                
                <div class="event-detail-item">
                    <div class="event-detail-label">Heure de fin</div>
                    <div class="event-detail-value"><?php echo $eventDetails['heureFin'] ? htmlspecialchars($eventDetails['heureFin']) : 'Non définie'; ?></div>
                </div>
                
                <div class="event-detail-item">
                    <div class="event-detail-label">Date de fin d'inscription</div>
                    <div class="event-detail-value"><?php echo $eventDetails['finInscription'] ? date('l d F Y', strtotime($eventDetails['finInscription'])) : 'Non définie'; ?></div>
                </div>
                
                <div class="event-detail-item">
                    <div class="event-detail-label">Capacité</div>
                    <div class="event-detail-value"><?php echo $eventDetails['Capacite'] ? $eventDetails['Capacite'] . ' places' : 'Illimitée'; ?></div>
                </div>
                
                <div class="event-detail-item">
                    <div class="event-detail-label">Nombre d'inscrits</div>
                    <div class="event-detail-value"><?php echo $eventDetails['nb_inscrits'] ?? 0; ?> participant(s)</div>
                </div>
            </div>
            
            <div>
                <div class="event-detail-item">
                    <div class="event-detail-label">Club organisateur</div>
                    <div class="event-detail-value"><?php echo htmlspecialchars($eventDetails['NomClub'] ?? 'Non défini'); ?></div>
                </div>
                
                <div class="event-detail-item">
                </div>
                
                <div class="event-detail-item">
                    <div class="event-detail-label">Email club</div>
                    <div class="event-detail-value"><?php echo htmlspecialchars($eventDetails['EmailOrg'] ?? 'Non défini'); ?></div>
                </div>
                
                <div class="event-detail-item">
                    <div class="event-detail-label">Lieu</div>
                    <div class="event-detail-value"><?php echo htmlspecialchars($eventDetails['Lieu'] ?? 'Non défini'); ?></div>
                </div>
                
                <div class="event-detail-item">
                    <div class="event-detail-label">Type d'événement</div>
                    <div class="event-detail-value"><?php echo htmlspecialchars($eventDetails['Type'] ?? 'Non défini'); ?></div>
                </div>
                
                <div class="event-detail-item">
                    <div class="event-detail-label">Prix adhérent</div>
                    <div class="event-detail-value">
                        <?php 
                            $prixAdherent = $eventDetails['prixAdherent'] ?? null; 
                            echo ($prixAdherent === null || $prixAdherent === '' || $prixAdherent == 0) 
                                ? 'Gratuit' 
                                : htmlspecialchars($prixAdherent) . ' MAD';
                        ?>
                    </div>
                </div>
                
                <div class="event-detail-item">
                    <div class="event-detail-label">Prix non-adhérent</div>
                    <div class="event-detail-value">
                        <?php 
                            $prixNonAdherent = $eventDetails['prixNonAdherent'] ?? null; 
                            echo ($prixNonAdherent === null || $prixNonAdherent === '' || $prixNonAdherent == 0) 
                                ? 'Gratuit' 
                                : htmlspecialchars($prixNonAdherent) . ' MAD';
                        ?>
                    </div>
                </div>
                
                <div class="event-detail-item">
                    <div class="event-detail-label">État de publication</div>
                    <div class="event-detail-value">
                        <span class="badge <?php echo $eventDetails['etat'] === 'publié' ? 'bg-success' : 'bg-secondary'; ?>">
                            <?php echo ucfirst(htmlspecialchars($eventDetails['etat'] ?? 'Non défini')); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="event-status-section">
            <div class="event-status-title">Statut des places</div>
            <span class="event-status-badge <?php echo $eventDetails['Statut'] === 'non saturé' ? 'non-sature' : 'sature'; ?>">
                <?php echo ucfirst($eventDetails['Statut']); ?>
            </span>
        </div>
        
        <?php if ($eventDetails['discription']): ?>
        <div class="event-description-section">
            <div class="event-description-title">Description</div>
            <div class="event-description">
                <?php echo nl2br(htmlspecialchars($eventDetails['discription'])); ?>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="event-validation-section">
            <div class="event-validation-title">Statut de validation</div>
            <?php 
                $validation = strtolower(trim($eventDetails['validation'] ?? 'en attente'));
                $badgeClass = $validation === 'validé' ? 'validated' : ($validation === 'refusé' ? 'refused' : 'pending');
                $label = $validation === 'validé' ? 'Validé' : ($validation === 'refusé' ? 'Refusé' : 'En attente de validation');
            ?>
            <span class="event-validation-badge <?php echo $badgeClass; ?>">
                <?php echo $label; ?>
            </span>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modal Modifier événement -->
<div id="editEventModal" class="modal">
    <div class="modal-content modal-large">
        <span class="close" onclick="closeModal('editEventModal')">&times;</span>
        <h2>Modifier l'événement</h2>
        <form id="editEventForm" method="POST" action="../actions/event_actions.php">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="idEvent" id="editIdEvent">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            
            <div class="form-row">
                <div class="form-group">
                    <label for="editNomEnv">Nom de l'événement *</label>
                    <input type="text" id="editNomEnv" name="nomEnv" required>
                </div>
                
                <div class="form-group">
                    <label for="editStatut">Statut *</label>
                    <select id="editStatut" name="statut" required>
                        <option value="non saturé">Non saturé</option>
                        <option value="saturé">Saturé</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label for="editDescription">Description</label>
                <textarea id="editDescription" name="description" rows="3"></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="editDateDebut">Date début *</label>
                    <input type="date" id="editDateDebut" name="dateDebut" required>
                </div>
                
                <div class="form-group">
                    <label for="editDateFin">Date fin</label>
                    <input type="date" id="editDateFin" name="dateFin">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="editLieu">Lieu</label>
                    <input type="text" id="editLieu" name="lieu">
                </div>
                
                <div class="form-group">
                    <label for="editCapacite">Capacité</label>
                    <input type="number" id="editCapacite" name="capacite" min="1">
                </div>
            </div>
            
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editEventModal')">Annuler</button>
                <button type="submit" class="btn btn-primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal de refus d'événement -->
<div id="rejectEventModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Refuser l'événement</h2>
            <span class="close" onclick="closeRejectModal()">&times;</span>
        </div>
        
        <form id="rejectForm" method="POST" action="../actions/events.php">
            <input type="hidden" name="action" value="reject">
            <input type="hidden" name="id" id="rejectEventId">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            
            <div class="form-group">
                <label for="eventName">Événement concerné</label>
                <input type="text" id="eventName" readonly class="form-control">
            </div>
            
            <div class="form-group">
                <label for="raison">Raison du refus *</label>
                <textarea id="raison" 
                          name="raison" 
                          rows="5" 
                          placeholder="Expliquez pourquoi cet événement est refusé..."
                          required></textarea>
                <small>Cette raison sera envoyée par email à l'organisateur</small>
            </div>
            
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeRejectModal()">Annuler</button>
                <button type="submit" class="btn btn-danger">Confirmer le refus</button>
            </div>
        </form>
    </div>
</div>

<script>
function openRejectModal(eventId, eventName) {
    document.getElementById('rejectEventId').value = eventId;
    document.getElementById('eventName').value = eventName;
    document.getElementById('rejectEventModal').classList.add('open');
}

function closeRejectModal() {
    document.getElementById('rejectEventModal').classList.remove('open');
    document.getElementById('rejectForm').reset();
}

// Fermer le modal en cliquant à l'extérieur
document.getElementById('rejectEventModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeRejectModal();
    }
});

// Initialiser les tooltips Bootstrap
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

<!-- Script pour la recherche dynamique -->
<script src="../assets/js/events.js"></script>

<?php include '../includes/footer.php'; ?>