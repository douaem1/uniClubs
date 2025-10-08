<?php
$pageTitle = "Gestion des clubs";
require_once '../config/database.php';
include '../includes/header.php';

$db = Database::getInstance()->getConnection();

// Récupérer tous les clubs avec leurs informations
$stmt = $db->query("
    SELECT c.*, 
           co.NomCord, co.PrenomCord,
           o.NomOrg, o.PrenomOrg,
           (SELECT COUNT(*) FROM Evenement WHERE idClub = c.idClub) as nb_evenements
    FROM Club c
    LEFT JOIN Coordinateur co ON c.EmailCord = co.EmailCord
    LEFT JOIN Organisateur o ON c.EmailOrg = o.EmailOrg
    ORDER BY c.NomClub
");
$clubs = $stmt->fetchAll();

// Récupérer la liste des organisateurs disponibles
$stmt = $db->query("SELECT * FROM Organisateur ORDER BY NomOrg, PrenomOrg");
$organisateurs = $stmt->fetchAll();
?>

<div class="clubs-management">
    <div class="page-header">
        <h2>Gestion des clubs</h2>
        <button class="btn btn-primary" onclick="openModal('addClubModal')">
            + Créer un club
        </button>
    </div>
    
    <!-- Liste des clubs -->
    <div class="clubs-grid">
        <?php if (empty($clubs)): ?>
        <div class="empty-state">
            <p>Aucun club pour le moment. Créez le premier club !</p>
        </div>
        <?php else: ?>
            <?php foreach ($clubs as $club): ?>
            <div class="club-card">
                <div class="club-image">
                    <?php if ($club['photoclub']): ?>
                        <img src="../uploads/clubs/<?php echo htmlspecialchars($club['photoclub']); ?>" alt="<?php echo htmlspecialchars($club['NomClub']); ?>">
                    <?php else: ?>
                        <div class="club-placeholder">📚</div>
                    <?php endif; ?>
                </div>
                
                <div class="club-info">
                    <h3><?php echo htmlspecialchars($club['NomClub']); ?></h3>
                    <p class="club-description">
                        <?php 
                        $desc = $club['description'] ?? 'Aucune description';
                        echo htmlspecialchars(substr($desc, 0, 100)); 
                        echo strlen($desc) > 100 ? '...' : ''; 
                        ?>
                    </p>
                    
                    <div class="club-stats">
                        <span class="stat">
                            <strong><?php echo $club['NbrAdherent']; ?></strong> adhérents
                        </span>
                        <span class="stat">
                            <strong><?php echo $club['nb_evenements']; ?></strong> événements
                        </span>
                    </div>
                    
                    <div class="club-meta">
                        <p><strong>Organisateur:</strong> 
                            <?php echo htmlspecialchars(($club['PrenomOrg'] ?? '') . ' ' . ($club['NomOrg'] ?? 'Non assigné')); ?>
                        </p>
                    </div>
                </div>
                
                <div class="club-actions">
                    <button class="btn btn-sm btn-secondary" onclick="editClub(<?php echo $club['idClub']; ?>)">
                        ✏️ Modifier
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="deleteClub(<?php echo $club['idClub']; ?>, '<?php echo htmlspecialchars(addslashes($club['NomClub'])); ?>')">
                        🗑️ Supprimer
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Créer/Modifier Club -->
<div id="addClubModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('addClubModal')">&times;</span>
        <h2 id="modalTitle">Créer un nouveau club</h2>
        
        <form id="clubForm" method="POST" action="../actions/club_actions.php" enctype="multipart/form-data">
            <input type="hidden" name="action" id="formAction" value="create">
            <input type="hidden" name="idClub" id="idClub">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            
            <div class="form-group">
                <label for="nomClub">Nom du club *</label>
                <input type="text" id="nomClub" name="nomClub" required>
            </div>
            
            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="4" placeholder="Description du club..."></textarea>
            </div>
            
            <div class="form-group">
                <label for="emailOrg">Organisateur responsable *</label>
                <select id="emailOrg" name="emailOrg" required>
                    <option value="">Sélectionner un organisateur</option>
                    <?php foreach ($organisateurs as $org): ?>
                    <option value="<?php echo htmlspecialchars($org['EmailOrg']); ?>">
                        <?php echo htmlspecialchars($org['PrenomOrg'] . ' ' . $org['NomOrg'] . ' (' . $org['EmailOrg'] . ')'); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="photoclub">Photo du club</label>
                <input type="file" id="photoclub" name="photoclub" accept="image/*">
                <small>Format: JPG, PNG (Max 2MB)</small>
                <div id="imagePreview" class="image-preview"></div>
            </div>
            
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addClubModal')">Annuler</button>
                <button type="submit" class="btn btn-primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<script src="../assets/js/clubs.js"></script>
<?php include '../includes/footer.php'; ?>