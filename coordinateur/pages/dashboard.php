<?php
$pageTitle = "Tableau de bord";
require_once '../config/database.php';
include '../includes/header.php';

$db = Database::getInstance()->getConnection();

// Récupérer les statistiques
$stats = [
    'total_clubs' => 0,
    'total_evenements' => 0,
    'total_participants' => 0,
    'evenements_a_venir' => 0
];

// Nombre total de clubs
$stmt = $db->query("SELECT COUNT(*) as count FROM Club");
$stats['total_clubs'] = $stmt->fetch()['count'];

// Nombre total d'événements
$stmt = $db->query("SELECT COUNT(*) as count FROM Evenement");
$stats['total_evenements'] = $stmt->fetch()['count'];

// Nombre total de participants
$stmt = $db->query("SELECT COUNT(DISTINCT EmailParti) as count FROM Participant");
$stats['total_participants'] = $stmt->fetch()['count'];

// Événements à venir
$stmt = $db->query("SELECT COUNT(*) as count FROM Evenement WHERE dateDebut >= CURDATE() AND Statut = 'planifié'");
$stats['evenements_a_venir'] = $stmt->fetch()['count'];

// Événements récents
$stmt = $db->query("
    SELECT e.*, c.NomClub, o.NomOrg, o.PrenomOrg,
           (SELECT COUNT(*) FROM inscri WHERE Idenv = e.Idenv) as nb_inscrits
    FROM Evenement e
    LEFT JOIN Club c ON e.idClub = c.idClub
    LEFT JOIN Organisateur o ON e.EmailOrg = o.EmailOrg
    ORDER BY e.dateDebut DESC
    LIMIT 5
");
$evenements_recents = $stmt->fetchAll();
?>

<div class="dashboard">
    <!-- Cartes de statistiques -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon clubs">👥</div>
            <div class="stat-info">
                <h3><?php echo $stats['total_clubs']; ?></h3>
                <p>Clubs actifs</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon events">📅</div>
            <div class="stat-info">
                <h3><?php echo $stats['total_evenements']; ?></h3>
                <p>Événements total</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon participants">🎓</div>
            <div class="stat-info">
                <h3><?php echo $stats['total_participants']; ?></h3>
                <p>Participants</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon upcoming">⏰</div>
            <div class="stat-info">
                <h3><?php echo $stats['evenements_a_venir']; ?></h3>
                <p>Événements à venir</p>
            </div>
        </div>
    </div>
    
    <!-- Événements récents -->
    <div class="recent-events">
        <h2>Événements récents</h2>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Événement</th>
                        <th>Club</th>
                        <th>Organisateur</th>
                        <th>Date</th>
                        <th>Inscrits</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($evenements_recents)): ?>
                    <tr>
                        <td colspan="7" class="text-center">Aucun événement pour le moment</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($evenements_recents as $event): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($event['NomEnv']); ?></strong>
                            </td>
                            <td><?php echo htmlspecialchars($event['NomClub'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($event['PrenomOrg'] . ' ' . $event['NomOrg']); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($event['dateDebut'])); ?></td>
                            <td>
                                <span class="badge badge-info">
                                    <?php echo $event['nb_inscrits'] . '/' . ($event['Capacite'] ?? '∞'); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $event['Statut']; ?>">
                                    <?php echo ucfirst($event['Statut']); ?>
                                </span>
                            </td>
                            <td>
                                <a href="evenements.php?view=<?php echo $event['Idenv']; ?>" class="btn-icon" title="Voir détails">👁️</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>