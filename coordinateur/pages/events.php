<?php
$pageTitle = "Supervision des événements";
require_once '../config/database.php';
include '../includes/header.php';

$db = Database::getInstance()->getConnection();

// Filtres
$filtre_statut = $_GET['statut'] ?? 'tous';
$filtre_club = $_GET['club'] ?? 'tous';
$filtre_recherche = $_GET['search'] ?? '';

// Construction de la requête avec filtres
$sql = "
    SELECT e.*, 
           c.NomClub,
           o.NomOrg, o.PrenomOrg,
           (SELECT COUNT(*) FROM inscri WHERE Idenv = e.Idenv)
           as nb_inscrits
    FROM Evenement e
    LEFT JOIN Club c ON e.idClub = c.idClub
    LEFT JOIN Organisateur o ON e.EmailOrg = o.EmailOrg
    WHERE 1=1
";

$params = [];

if ($filtre_statut !== 'tous') {
    $sql .= " AND e.Statut = ?";
    $params[] = $filtre_statut;
}

if ($filtre_club !== 'tous') {
    $sql .= " AND e.idClub = ?";
    $params[] = intval($filtre_club);
}

if (!empty($filtre_recherche)) {
    $sql .= " AND (e.NomEnv LIKE ? OR e.Lieu LIKE ?)";
    $params[] = "%$filtre_recherche%";
    $params[] = "%$filtre_recherche%";
}

$sql .= " ORDER BY e.dateDebut DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$evenements = $stmt->fetchAll();

// Récupérer la liste des clubs pour le filtre
$stmt_clubs = $db->query("SELECT idClub, NomClub FROM Club ORDER BY NomClub");
$clubs = $stmt_clubs->fetchAll();

// Récupérer les statistiques par statut
$stmt_stats = $db->query("
    SELECT Statut, COUNT(*) as count 
    FROM Evenement 
    GROUP BY Statut
");
$stats_statut = [];
while ($row = $stmt_stats->fetch()) {
    $stats_statut[$row['Statut']] = $row['count'];
}
?>

<div class="evenements-supervision">
    <!-- En-tête avec filtres -->
    <div class="page-header">
        <h2>Supervision des événements</h2>
        <div class="stats-summary">
            <span class="stat-badge">
                <strong><?php echo $stats_statut['planifié'] ?? 0; ?></strong> Planifiés
            </span>
            <span class="stat-badge">
                <strong><?php echo $stats_statut['en cours'] ?? 0; ?></strong> En cours
            </span>
            <span class="stat-badge">
                <strong><?php echo $stats_statut['terminé'] ?? 0; ?></strong> Terminés
            </span>
            <span class="stat-badge danger">
                <strong><?php echo $stats_statut['annulé'] ?? 0; ?></strong> Annulés
            </span>
        </div>
    </div>
    
    <!-- Filtres -->
    <div class="filters-section">
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
                    <option value="planifié" <?php echo $filtre_statut === 'planifié' ? 'selected' : ''; ?>>Planifié</option>
                    <option value="en cours" <?php echo $filtre_statut === 'en cours' ? 'selected' : ''; ?>>En cours</option>
                    <option value="terminé" <?php echo $filtre_statut === 'terminé' ? 'selected' : ''; ?>>Terminé</option>
                    <option value="annulé" <?php echo $filtre_statut === 'annulé' ? 'selected' : ''; ?>>Annulé</option>
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
            
            <div class="filter-actions">
                <button type="submit" class="btn btn-primary">Filtrer</button>
                <a href="events.php" class="btn btn-secondary">Réinitialiser</a>
            </div>
        </form>
    </div>
    
    <!-- Liste des événements -->
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Événement</th>
                    <th>Club</th>
                    <th>Organisateur</th>
                    <th>Date début</th>
                    <th>Date fin</th>
                    <th>Lieu</th>
                    <th>Inscrits</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($evenements)): ?>
                <tr>
                    <td colspan="9" class="text-center">Aucun événement trouvé</td>
                </tr>
                <?php else: ?>
                    <?php foreach ($evenements as $event): ?>
                    <tr>
                        <td>
                            <div class="event-info">
                                <?php if ($event['photo']): ?>
                                    <img src="../uploads/events/<?php echo htmlspecialchars($event['photo']); ?>" 
                                         alt="<?php echo htmlspecialchars($event['NomEnv']); ?>" 
                                         class="event-thumb">
                                <?php endif; ?>
                                <strong><?php echo htmlspecialchars($event['NomEnv']); ?></strong>
                            </div>
                        </td>
                        <td><?php echo htmlspecialchars($event['NomClub'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($event['PrenomOrg'] . ' ' . $event['NomOrg']); ?></td>
                        <td><?php echo date('d/m/Y', strtotime($event['dateDebut'])); ?></td>
                        <td><?php echo $event['dateFin'] ? date('d/m/Y', strtotime($event['dateFin'])) : '-'; ?></td>
                        <td><?php echo htmlspecialchars($event['Lieu'] ?? 'Non défini'); ?></td>
                        <td>
                            <span class="badge badge-info">
                                <?php echo $event['nb_inscrits']; ?><?php echo $event['Capacite'] ? '/' . $event['Capacite'] : ''; ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge badge-<?php echo $event['Statut']; ?>">
                                <?php echo ucfirst($event['Statut']); ?>
                            </span>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn-icon" 
                                        onclick="viewEvent(<?php echo $event['Idenv']; ?>)" 
                                        title="Voir détails">
                                    👁️
                                </button>
                                <button class="btn-icon" 
                                        onclick="editEvent(<?php echo $event['Idenv']; ?>)" 
                                        title="Modifier">
                                    ✏️
                                </button>
                                <?php if ($event['Statut'] !== 'terminé' && $event['Statut'] !== 'annulé'): ?>
                                <button class="btn-icon danger" 
                                        onclick="cancelEvent(<?php echo $event['Idenv']; ?>, '<?php echo htmlspecialchars(addslashes($event['NomEnv'])); ?>')" 
                                        title="Annuler">
                                    ❌
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Détails événement -->
<div id="viewEventModal" class="modal">
    <div class="modal-content modal-large">
        <span class="close" onclick="closeModal('viewEventModal')">&times;</span>
        <h2>Détails de l'événement</h2>
        <div id="eventDetails"></div>
    </div>
</div>

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
                        <option value="planifié">Planifié</option>
                        <option value="en cours">En cours</option>
                        <option value="terminé">Terminé</option>
                        <option value="annulé">Annulé</option>
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

<script src="../assets/js/events.js"></script>
<?php include '../includes/footer.php'; ?>