<?php
$pageTitle = "Tableau de bord";
include '../../ConnDB.php';
include '../includes/header.php';

// Récupérer les statistiques
$stats = [
    'total_clubs' => 0,
    'total_evenements' => 0,
    'evenements_a_venir' => 0,
    'evenements_attente' => 0,
    'evenements_valides' => 0
];

// Nombre total de clubs
$result = $conn->query("SELECT COUNT(*) as count FROM Club");
$row = $result->fetch_assoc();
$stats['total_clubs'] = $row['count'];

// Nombre total d'événements
$result = $conn->query("SELECT COUNT(*) as count FROM Evenement");
$row = $result->fetch_assoc();
$stats['total_evenements'] = $row['count'];

// Événements à venir
$result = $conn->query("SELECT COUNT(*) as count FROM Evenement WHERE dateDebut >= CURDATE() AND Statut = 'non saturé'");
$row = $result->fetch_assoc();
$stats['evenements_a_venir'] = $row['count'];

// Événements en attente de validation
$result = $conn->query("SELECT COUNT(*) as count FROM Evenement WHERE validation = 'en attente'");
$row = $result->fetch_assoc();
$stats['evenements_attente'] = $row['count'];

// Événements validés
$result = $conn->query("SELECT COUNT(*) as count FROM Evenement WHERE validation = 'validé'");
$row = $result->fetch_assoc();
$stats['evenements_valides'] = $row['count'];

// Récupérer les événements récents (limite à 5)
$query = "
    SELECT e.*, 
           c.NomClub,
           p.Nom as NomOrg, 
           p.Prenom as PrenomOrg,
           (SELECT COUNT(*) FROM inscri WHERE Idenv = e.Idenv) as nb_inscrits
    FROM Evenement e
    LEFT JOIN Club c ON e.idClub = c.idClub
    LEFT JOIN Organisateur o ON e.idOrganisateur = o.idCompte
    LEFT JOIN Compte co ON o.idCompte = co.idCompte
    LEFT JOIN Participant p ON o.idCompte = p.idCompte
    ORDER BY e.dateDebut DESC
    LIMIT 5
";
$result = $conn->query($query);
$evenements_recents = [];
while ($row = $result->fetch_assoc()) {
    $evenements_recents[] = $row;
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

<div class="dashboard">
    <div class="row g-4 mb-4">
        <div class="col-md-6 col-xl-3">
            <div class="card stat-card stat-card-gradient-purple border-0 h-100 shadow-hover">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between gap-3">
                        <div class="flex-grow-1">
                            <h2 class="mb-2 fw-bold"><?php echo $stats['total_clubs']; ?></h2>
                            <p class="mb-0 small">Clubs actifs</p>
                        </div>
                        <div class="stat-icon-modern flex-shrink-0">
                            <i class="bi bi-people-fill fs-2" style="color: #3B82F6;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6 col-xl-3">
            <div class="card stat-card stat-card-gradient-green border-0 h-100 shadow-hover">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between gap-3">
                        <div class="flex-grow-1">
                            <h2 class="mb-2 fw-bold"><?php echo $stats['total_evenements']; ?></h2>
                            <p class="mb-0 small">Événements</p>
                        </div>
                        <div class="stat-icon-modern flex-shrink-0">
                            <i class="bi bi-calendar-event fs-2" style="color: #10B981;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6 col-xl-3">
            <div class="card stat-card stat-card-gradient-orange border-0 h-100 shadow-hover">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between gap-3">
                        <div class="flex-grow-1">
                            <h2 class="mb-2 fw-bold"><?php echo $stats['evenements_a_venir']; ?></h2>
                            <p class="mb-0 small">À venir</p>
                        </div>
                        <div class="stat-icon-modern flex-shrink-0">
                            <i class="bi bi-alarm fs-2" style="color: #F59E0B;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6 col-xl-3">
            <div class="card stat-card stat-card-gradient-blue border-0 h-100 shadow-hover">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between gap-3">
                        <div class="flex-grow-1">
                            <h2 class="mb-2 fw-bold"><?php echo $stats['evenements_attente']; ?></h2>
                            <p class="mb-0 small">En attente</p>
                        </div>
                        <div class="stat-icon-modern flex-shrink-0">
                            <i class="bi bi-hourglass-split fs-2" style="color: #06B6D4;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Section événements -->
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
                        <?php if (empty($evenements_recents)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5">
                                <i class="bi bi-calendar-x fs-1 text-muted d-block mb-2"></i>
                                <span class="text-muted">Aucun événement récent</span>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($evenements_recents as $event): 
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
                                            <a href="../actions/events.php?action=validate&id=<?php echo $event['Idenv']; ?>&redirect=dashboard" 
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
        <div class="card-footer bg-gradient-light border-0 py-3">
            <a href="events.php" class="btn btn-primary-modern shadow-sm">
                <i class="bi bi-list-ul me-2"></i>Voir tous les événements
            </a>
        </div>
    </div>
</div>

<!-- Modal de refus d'événement -->
<div id="rejectEventModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Refuser l'événement</h2>
            <span class="close" onclick="closeRejectModal()">&times;</span>
        </div>
        
        <form id="rejectForm" method="POST" action="../actions/events.php?redirect=dashboard">
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

<?php include '../includes/footer.php'; ?>