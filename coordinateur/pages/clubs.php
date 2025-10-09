<?php
$pageTitle = "Gestion des clubs";
require_once '../config/database.php';
include '../includes/header.php';

$db = Database::getInstance()->getConnection();

// Récupérer tous les clubs
$stmt = $db->query("
       SELECT c.*, co.Email AS OrgEmail, p.Nom AS NomOrg, p.Prenom AS PrenomOrg
       FROM Club c
       LEFT JOIN Organisateur o ON c.idOrganisateur = o.idCompte
       LEFT JOIN Compte co ON o.idCompte = co.idCompte
       LEFT JOIN Participant p ON o.idCompte = p.idCompte
       ORDER BY c.NomClub
");
$clubs = $stmt->fetchAll();

// Si edition demandée, charger le club et préparer le formulaire en mode update
$editingClub = null;
if (isset($_GET['edit'])) {
    $idToEdit = intval($_GET['edit']);
    $stmt = $db->prepare("SELECT * FROM Club WHERE idClub = ?");
    $stmt->execute([$idToEdit]);
    $editingClub = $stmt->fetch();
}

// Si confirmation suppression demandée
$confirmDeleteClub = null;
if (isset($_GET['confirm_delete'])) {
    $idToDelete = intval($_GET['confirm_delete']);
    $stmt = $db->prepare("SELECT idClub, NomClub FROM Club WHERE idClub = ?");
    $stmt->execute([$idToDelete]);
    $confirmDeleteClub = $stmt->fetch();
}

// Liste des organisateurs pour le menu déroulant
    $stmt = $db->query("SELECT o.idCompte, c.Email, p.Nom, p.Prenom FROM Organisateur o LEFT JOIN Compte c ON o.idCompte = c.idCompte LEFT JOIN Participant p ON o.idCompte = p.idCompte ORDER BY p.Nom, p.Prenom");
    $organisateurs = $stmt->fetchAll();
?>

<div class="clubs-management">
    <div class="page-header">
        <div class="header-left">
            <a href="../pages/dashboard.php" class="btn btn-secondary">
                ⬅️ Retour au tableau de bord
            </a>
            <h2>Gestion des clubs</h2>
        </div>
        <a href="?create=1" class="btn btn-primary">+ Créer un club</a>
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
                        <img src="../uploads/clubs/<?php echo htmlspecialchars($club['photoclub']); ?>" 
                             alt="<?php echo htmlspecialchars($club['NomClub']); ?>">
                    <?php else: ?>
                        <div class="club-placeholder">📸</div>
                    <?php endif; ?>
                </div>

                <div class="club-info">
                    <h3><?php echo htmlspecialchars($club['NomClub']); ?></h3>
                    <p class="club-description">
                        <?php 
                            $desc = $club['description'] ?? 'Aucune description';
                            echo htmlspecialchars(substr($desc, 0, 120));
                            echo strlen($desc) > 120 ? '...' : '';
                        ?>
                    </p>

                    <p><strong>Adhérents :</strong> <?php echo intval($club['NbrAdherent']); ?></p>

                    <div class="club-actions">
                        <a class="btn btn-sm btn-secondary" href="clubs.php?edit=<?php echo $club['idClub']; ?>">✏️ Modifier</a>
                        <a class="btn btn-sm btn-danger" href="clubs.php?confirm_delete=<?php echo $club['idClub']; ?>">🗑️ Supprimer</a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Créer/Modifier Club -->
<div id="addClubModal" class="modal <?php echo (isset($_GET['create']) || $editingClub) ? 'open' : ''; ?>">
    <div class="modal-content">
        <a href="clubs.php" class="close" aria-label="Fermer">&times;</a>
        <h2 id="modalTitle"><?php echo $editingClub ? 'Modifier le club' : 'Créer un nouveau club'; ?></h2>

        <form id="clubForm" method="POST" action="../actions/clubs.php" enctype="multipart/form-data">
            <input type="hidden" name="action" id="formAction" value="<?php echo $editingClub ? 'update' : 'create'; ?>">
            <input type="hidden" name="idClub" id="idClub" value="<?php echo $editingClub['idClub'] ?? ''; ?>">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

            <div class="form-group">
                <label for="nomClub">Nom du club *</label>
                <input type="text" id="nomClub" name="nomClub" required value="<?php echo htmlspecialchars($editingClub['NomClub'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="description">Description du club</label>
                <textarea id="description" name="description" rows="4" placeholder="Description du club..."><?php echo htmlspecialchars($editingClub['description'] ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="photoclub">Photo du club</label>
                <input type="file" id="photoclub" name="photoclub" accept="image/*">
                <small>Formats acceptés : JPG, PNG (max 2 Mo)</small>
                <div id="imagePreview" class="image-preview"></div>
            </div>

            <div class="form-group">
                <label for="orgEmail">Email de l'organisateur *</label>
                <input type="email" id="orgEmail" name="orgEmail" placeholder="ex: organisateur@gmail.com" required value="<?php 
                    if ($editingClub) {
                        // Pré-remplir depuis la table Compte
                        $stmtEmail = $db->prepare('SELECT Email FROM Compte WHERE idCompte = ?');
                        $stmtEmail->execute([$editingClub['idOrganisateur']]);
                        echo htmlspecialchars($stmtEmail->fetch()['Email'] ?? '');
                    }
                ?>">
            </div>

            <div class="form-group">
                <label for="orgPassword">Mot de passe de l'organisateur *</label>
                <input type="password" id="orgPassword" name="orgPassword" placeholder="Mot de passe" required>
            </div>

            <div class="form-actions">
                <a href="clubs.php" class="btn btn-secondary">Annuler</a>
                <button type="submit" class="btn btn-primary"><?php echo $editingClub ? 'Mettre à jour' : 'Enregistrer'; ?></button>
            </div>
        </form>
    </div>
</div>

<script src="../assets/js/clubs.js"></script>
<?php if ($confirmDeleteClub): ?>
<!-- Modal confirmation suppression -->
<div class="modal open" id="confirmDeleteModal">
    <div class="modal-content">
        <a href="clubs.php" class="close" aria-label="Fermer">&times;</a>
        <h2>Confirmer la suppression</h2>
        <p>Êtes-vous sûr de vouloir supprimer le club
           <strong><?php echo htmlspecialchars($confirmDeleteClub['NomClub']); ?></strong> ?<br>
           Cette action est définitive.</p>
        <div class="form-actions">
            <a href="clubs.php" class="btn btn-secondary">Annuler</a>
            <a href="../actions/clubs.php?action=delete&id=<?php echo intval($confirmDeleteClub['idClub']); ?>" class="btn btn-danger">Supprimer</a>
        </div>
    </div>
</div>
<?php endif; ?>
<?php include '../includes/footer.php'; ?>
