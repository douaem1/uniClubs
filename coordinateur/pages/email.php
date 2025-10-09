<?php
$pageTitle = "Envoyer un email";
require_once '../config/database.php';
include '../includes/header.php';

$db = Database::getInstance()->getConnection();

// Récupérer la liste des organisateurs
$stmt = $db->query("
    SELECT o.*, c.NomClub 
    FROM Organisateur o
    LEFT JOIN Club c ON o.EmailOrg = c.EmailOrg
    ORDER BY o.NomOrg, o.PrenomOrg
");
$organisateurs = $stmt->fetchAll();
?>

<div class="send-email-section">
    <div class="page-header">
        <h2>Envoyer un email aux organisateurs</h2>
        <p>Communiquer avec les organisateurs concernant les actualités, rappels ou informations importantes</p>
    </div>
    
    <div class="email-form-container">
        <form id="emailForm" method="POST" action="../actions/email_actions.php" enctype="multipart/form-data">
            <input type="hidden" name="action" value="send">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            
            <!-- Destinataires -->
            <div class="form-section">
                <h3>📧 Destinataires</h3>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="selectAll" onclick="toggleAllOrganisateurs()">
                        <strong>Sélectionner tous les organisateurs</strong>
                    </label>
                </div>
                
                <div class="organisateurs-list">
                    <?php if (empty($organisateurs)): ?>
                        <p class="text-muted">Aucun organisateur disponible</p>
                    <?php else: ?>
                        <?php foreach ($organisateurs as $org): ?>
                        <div class="organisateur-item">
                            <label>
                                <input type="checkbox" 
                                       name="destinataires[]" 
                                       value="<?php echo htmlspecialchars($org['EmailOrg']); ?>"
                                       class="org-checkbox">
                                <div class="org-info">
                                    <strong><?php echo htmlspecialchars($org['PrenomOrg'] . ' ' . $org['NomOrg']); ?></strong>
                                    <span class="email"><?php echo htmlspecialchars($org['EmailOrg']); ?></span>
                                    <?php if ($org['NomClub']): ?>
                                        <span class="club-badge"><?php echo htmlspecialchars($org['NomClub']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Contenu de l'email -->
            <div class="form-section">
                <h3>✉️ Contenu de l'email</h3>
                
                <div class="form-group">
                    <label for="sujet">Sujet *</label>
                    <input type="text" 
                           id="sujet" 
                           name="sujet" 
                           placeholder="Ex: Nouvelle politique de gestion des événements"
                           required>
                </div>
                
                <div class="form-group">
                    <label for="message">Message *</label>
                    <textarea id="message" 
                              name="message" 
                              rows="10" 
                              placeholder="Rédigez votre message ici..."
                              required></textarea>
                    <small>Vous pouvez utiliser des balises HTML pour formater le texte</small>
                </div>
                
                <div class="form-group">
                    <label for="piece_jointe">Pièce jointe (optionnel)</label>
                    <input type="file" 
                           id="piece_jointe" 
                           name="piece_jointe"
                           accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                    <small>Formats acceptés: PDF, DOC, DOCX, JPG, PNG (Max 5MB)</small>
                </div>
            </div>
            
            <!-- Actions -->
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="resetForm()">Réinitialiser</button>
                <button type="submit" class="btn btn-primary" id="sendBtn">
                    📤 Envoyer l'email
                </button>
            </div>
        </form>
    </div>
    
    <!-- Historique récent -->
    <div class="email-history">
        <h3>📋 Historique récent</h3>
        <div id="historyContainer">
            <p class="text-muted">Chargement...</p>
        </div>
    </div>
</div>

<script>
// Sélectionner/désélectionner tous les organisateurs
function toggleAllOrganisateurs() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.org-checkbox');
    checkboxes.forEach(cb => cb.checked = selectAll.checked);
}

// Réinitialiser le formulaire
function resetForm() {
    if (confirm('Voulez-vous vraiment réinitialiser le formulaire ?')) {
        document.getElementById('emailForm').reset();
        document.getElementById('selectAll').checked = false;
    }
}

// Validation avant envoi
document.getElementById('emailForm').addEventListener('submit', function(e) {
    const checkboxes = document.querySelectorAll('.org-checkbox:checked');
    if (checkboxes.length === 0) {
        e.preventDefault();
        alert('Veuillez sélectionner au moins un destinataire');
        return false;
    }
    
    const btn = document.getElementById('sendBtn');
    btn.disabled = true;
    btn.innerHTML = '⏳ Envoi en cours...';
});

// Charger l'historique (simulé pour l'instant)
setTimeout(() => {
    document.getElementById('historyContainer').innerHTML = '<p class="text-muted">Aucun email envoyé récemment</p>';
}, 500);
</script>

<?php include '../includes/footer.php'; ?>