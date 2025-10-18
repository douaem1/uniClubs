<?php
$pageTitle = "Envoyer un email";
include '../../ConnDB.php';
include '../includes/header.php';

// Récupérer la liste des organisateurs
$query = "
    SELECT 
        c.Email AS Email,
        cl.NomClub AS NomClub
    FROM Organisateur o
    JOIN Compte c ON o.idCompte = c.idCompte
    LEFT JOIN Club cl ON cl.idOrganisateur = o.idCompte
";
$result = $conn->query($query);
$organisateurs = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $organisateurs[] = $row;
    }
    $result->free();
}
?>

<style>
/* Styles pour le formulaire d'envoi d'email */
.send-email-section {
    max-width: 1000px;
    margin: 0 auto;
    padding: 2rem 1rem;
}

.email-card {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
    overflow: hidden;
    margin-bottom: 30px;
    border: 1px solid #f0f0f0;
}

.card-header {
    padding: 1.5rem 2rem;
    background-color: #f8f9fa;
    border-bottom: 1px solid #f0f0f0;
}

.card-header h2 {
    margin: 0;
    color: #111827;
    font-size: 1.5rem;
    font-weight: 600;
}

.card-body {
    padding: 2rem;
}

.form-section {
    margin-bottom: 2rem;
}

.form-section h3 {
    color: #7C3AED;
    font-size: 1.1rem;
    margin-bottom: 1.5rem;
    padding-bottom: 0.75rem;
    border-bottom: 2px solid #F5F3FF;
    font-weight: 600;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-label {
    font-weight: 500;
    color: #495057;
    margin-bottom: 0.5rem;
}

.form-control, .form-select {
    padding: 0.75rem 1rem;
    border: 1px solid #dee2e6;
    border-radius: 10px;
    font-size: 0.95rem;
    transition: all 0.2s ease;
    box-shadow: none;
}

.form-control:focus, .form-select:focus {
    border-color: #7C3AED;
    box-shadow: 0 0 0 0.2rem rgba(124, 58, 237, 0.1);
}

textarea.form-control {
    min-height: 200px;
    resize: vertical;
}

/* Style pour les cases à cocher personnalisées */
.form-check-input:checked {
    background-color: #7C3AED;
    border-color: #7C3AED;
}

.form-check-input:focus {
    box-shadow: 0 0 0 0.25rem rgba(124, 58, 237, 0.15);
}

/* Select all checkbox */
#selectAll {
    width: 20px !important;
    height: 20px !important;
    border: 2px solid #E5E7EB;
    cursor: pointer;
}

#selectAll:checked {
    background-color: #7C3AED !important;
    border-color: #7C3AED !important;
}

#selectAll:focus {
    box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.1) !important;
    border-color: #7C3AED !important;
}

/* Style pour la liste des organisateurs */
.organisateurs-list {
    max-height: 400px;
    overflow-y: auto;
    scrollbar-width: thin;
    scrollbar-color: #E5E7EB #F9FAFB;
    background: #FFFFFF !important;
    border: 1px solid #E5E7EB !important;
}

.organisateurs-list::-webkit-scrollbar {
    width: 6px;
}

.organisateurs-list::-webkit-scrollbar-track {
    background: #F9FAFB;
    border-radius: 10px;
}

.organisateurs-list::-webkit-scrollbar-thumb {
    background-color: #E5E7EB;
    border-radius: 10px;
}

/* Fix checkbox layout - CRITICAL */
.organisateur-item {
    transition: all 0.2s ease;
    border-radius: 8px;
    margin-bottom: 0.5rem;
    background: #FFFFFF !important;
    border: 1px solid #E5E7EB !important;
    padding: 0 !important;
}

.organisateur-item:hover {
    background: #F9FAFB !important;
    border-color: #7C3AED !important;
}

.organisateur-item .form-check {
    margin: 0 !important;
    padding: 0 !important;
    display: flex;
    align-items: center;
    width: 100%;
}

/* Checkbox styling - SMALL SIZE */
.organisateur-item .form-check-input {
    width: 18px !important;
    height: 18px !important;
    margin: 0 !important;
    flex-shrink: 0;
    border: 2px solid #E5E7EB;
    border-radius: 0.25rem;
    cursor: pointer;
}

.organisateur-item .form-check-input:checked {
    background-color: #7C3AED !important;
    border-color: #7C3AED !important;
}

.organisateur-item .form-check-input:focus {
    box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.1) !important;
    border-color: #7C3AED !important;
}

/* Label styling - MUST NOT TAKE FULL WIDTH */
.organisateur-item .form-check-label {
    margin: 0 !important;
    padding: 12px 16px !important;
    width: 100% !important;
    cursor: pointer;
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
}

/* Email text styling */
.organisateur-item .form-check-label .fw-medium {
    color: #111827 !important;
    font-size: 0.9rem;
    font-weight: 500 !important;
}

/* Selected state */
.organisateur-item:has(.form-check-input:checked) {
    background: #F5F3FF !important;
    border-color: #7C3AED !important;
}

.organisateur-item:has(.form-check-input:checked) .fw-medium {
    color: #7C3AED !important;
    font-weight: 600 !important;
}

/* Badge club styling */
.organisateur-item .badge {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
    border-radius: 0.375rem;
}

/* Icon styling */
.organisateur-item .bi-person-circle {
    flex-shrink: 0;
    color: #7C3AED !important;
}

/* Gap between checkbox and content */
.organisateur-item .gap-3 {
    gap: 1rem !important;
}

/* Ensure proper alignment */
.organisateur-item label {
    border-bottom: 1px solid #F3F4F6;
}

.organisateur-item:last-child label {
    border-bottom: none;
}

/* Style pour le téléchargement de fichiers */
.file-upload-label {
    transition: all 0.3s ease;
    border: 2px dashed #dee2e6;
    border-radius: 12px;
    background-color: #f8f9fa;
    padding: 1.5rem;
}

.file-upload-label:hover {
    border-color: #7C3AED;
    background-color: #F5F3FF;
}

/* Style pour les boutons d'action */
.btn {
    padding: 0.625rem 1.25rem;
    border-radius: 8px;
    font-weight: 500;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.btn i {
    margin-right: 0.5rem;
}

.btn-primary {
    background-color: #7C3AED;
    border-color: #7C3AED;
}

.btn-primary:hover {
    background-color: #9333EA;
    border-color: #9333EA;
    transform: translateY(-1px);
}

.btn-outline-primary {
    color: #7C3AED;
    border-color: #7C3AED;
}

.btn-outline-primary:hover {
    background-color: #7C3AED;
    color: white;
}

.btn-outline-secondary {
    color: #6c757d;
    border-color: #dee2e6;
}

.btn-outline-secondary:hover {
    background-color: #f8f9fa;
    color: #495057;
}

/* Animation pour les éléments du formulaire */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.form-section {
    animation: fadeIn 0.3s ease-out forwards;
    opacity: 0;
}

/* Délai d'animation pour chaque section */
.form-section:nth-child(1) { animation-delay: 0.1s; }
.form-section:nth-child(2) { animation-delay: 0.2s; }
.form-section:nth-child(3) { animation-delay: 0.3s; }

/* Style pour les badges */
.badge {
    font-weight: 500;
    padding: 0.4em 0.8em;
    border-radius: 20px;
    font-size: 0.8rem;
}

/* Style pour les icônes dans les boutons */
.btn i {
    font-size: 1.1em;
    line-height: 1;
}

/* Style pour les champs de formulaire en lecture seule */
.form-control[readonly] {
    background-color: #f8f9fa;
    opacity: 1;
}

/* Style pour les messages d'erreur */
.invalid-feedback {
    font-size: 0.85rem;
    margin-top: 0.25rem;
}

/* Styles pour les écrans plus petits */
@media (max-width: 768px) {
    .card-body {
        padding: 15px;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .btn {
        width: 100%;
        margin-bottom: 10px;
    }
}
</style>

<div class="send-email-section">
    <div class="email-card shadow-sm">
        <div class="card-header bg-white border-bottom-0">
            <div class="d-flex align-items-center">
                <i class="bi bi-envelope-paper me-2 fs-4 text-primary"></i>
                <h2 class="h4 mb-0">Nouveau message</h2>
            </div>
        </div>
        <div class="card-body">
            <form id="emailForm" method="POST" action="../actions/email.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="send">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                
                <!-- Destinataires -->
                <div class="form-section">
                    <h3 class="d-flex align-items-center text-muted mb-3">
                        <i class="bi bi-people-fill me-2"></i>Destinataires
                    </h3>
                    
                    <div class="form-group">
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" role="switch" id="selectAll" onchange="toggleAllOrganisateurs()">
                            <label class="form-check-label fw-medium" for="selectAll">
                                <i class="bi bi-check2-all me-1"></i>Sélectionner tous les organisateurs
                            </label>
                        </div>
                        <div class="organisateurs-list p-3 border rounded-3 bg-light" style="max-height: 300px; overflow-y: auto;">
                            <?php if (empty($organisateurs)): ?>
                                <div class="text-center py-4 text-muted">
                                    <i class="bi bi-people-slash fs-1 opacity-50 mb-2 d-block"></i>
                                    Aucun organisateur trouvé
                                </div>
                            <?php else: ?>
                                <?php foreach ($organisateurs as $index => $org): ?>
                                <div class="organisateur-item">
                                    <label class="form-check d-flex align-items-center justify-content-between" for="org-<?php echo $index; ?>">
                                        <div class="d-flex align-items-center gap-3 flex-grow-1">
                                            <input class="form-check-input destinataire-checkbox" type="checkbox" name="destinataires[]" 
                                                   value="<?php echo htmlspecialchars($org['Email']); ?>" 
                                                   id="org-<?php echo $index; ?>">
                                            <div class="d-flex flex-column">
                                                <span class="fw-medium"><?php echo htmlspecialchars($org['Email']); ?></span>
                                                <?php if (!empty($org['NomClub'])): ?>
                                                    <span class="badge bg-soft-primary text-primary mt-1" style="width: fit-content; background: #EDE9FE; color: #7C3AED; font-size: 0.75rem; padding: 0.25rem 0.5rem;">
                                                        <i class="bi bi-people me-1"></i><?php echo htmlspecialchars($org['NomClub']); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <i class="bi bi-person-circle text-primary" style="font-size: 1.5rem;"></i>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Sujet -->
                <div class="form-section">
                    <h3 class="d-flex align-items-center text-muted mb-3">
                        <i class="bi bi-chat-square-text-fill me-2"></i>Contenu du message
                    </h3>
                    <div class="form-group mb-4">
                        <label for="sujet" class="form-label fw-medium">Sujet</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="bi bi-tag"></i>
                            </span>
                            <input type="text" class="form-control border-start-0 ps-2" id="sujet" name="sujet" 
                                   placeholder="Objet du message" required>
                        </div>
                    </div>
                    <div class="form-group mb-4">
                        <label for="message" class="form-label fw-medium">Message</label>
                        <div class="position-relative">
                            <textarea class="form-control" id="message" name="message" rows="8" 
                                      placeholder="Écrivez votre message ici..." 
                                      style="border-radius: 8px; min-height: 200px;" required></textarea>
                            <div class="position-absolute" style="bottom: 10px; right: 10px;">
                                <button type="button" class="btn btn-sm" 
                                        style="background: #EDE9FE; color: #7C3AED; border: 1px solid #DDD6FE;"
                                        onclick="insertTemplate('cordial')" 
                                        title="Ajouter une formule de politesse">
                                    <i class="bi bi-chat-square-quote"></i> Formule
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label fw-medium">
                            <i class="bi bi-paperclip me-1"></i>Pièce jointe (optionnel)
                        </label>
                        <div class="file-upload">
                            <input type="file" name="piece_jointe" class="d-none" id="fileInput" onchange="updateFileName(this)">
                            <label for="fileInput" class="file-upload-label d-flex align-items-center p-3 border rounded-3 bg-white shadow-sm" 
                                   style="cursor: pointer;">
                                <div style="background: #EDE9FE; padding: 0.5rem; border-radius: 0.5rem;" class="me-3">
                                    <i class="bi bi-cloud-arrow-up-fill fs-4" style="color: #7C3AED;"></i>
                                </div>
                                <div>
                                    <div class="fw-medium mb-1" style="color: #111827;">Glissez-déposez votre fichier ici</div>
                                    <div class="text-muted small">ou cliquez pour parcourir</div>
                                    <div class="text-muted small mt-1">Taille maximale : 5 Mo</div>
                                </div>
                            </label>
                            <div id="fileName" class="mt-2 small"></div>
                        </div>
                    </div>
                </div>
                
                <!-- Actions -->
                <div class="form-actions mt-4 pt-3 border-top d-flex justify-content-between">
                    <button type="button" class="btn" style="background: #EDE9FE; color: #7C3AED; border: 1px solid #DDD6FE;" onclick="history.back()">
                        <i class="bi bi-arrow-left me-1"></i>Retour
                    </button>
                    <button type="submit" class="btn btn-primary px-4" id="sendBtn">
                        <i class="bi bi-send-fill me-1"></i>Envoyer le message
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Sélectionner/désélectionner tous les organisateurs
function toggleAllOrganisateurs() {
    const checkboxes = document.querySelectorAll('.destinataire-checkbox');
    const selectAllCheckbox = document.getElementById('selectAll');
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAllCheckbox.checked;
        // Trigger change event to update visual state
        checkbox.dispatchEvent(new Event('change'));
    });
}

// Mettre à jour le nom du fichier sélectionné
function updateFileName(input) {
    const fileName = document.getElementById('fileName');
    if (input.files.length > 0) {
        const file = input.files[0];
        const fileSize = (file.size / 1024 / 1024).toFixed(2); // Taille en Mo
        fileName.innerHTML = `
            <div class="d-flex align-items-center p-2 rounded" style="background: #F0FDF4; border: 1px solid #86EFAC;">
                <i class="bi bi-file-earmark-check text-success me-2 fs-5"></i>
                <div>
                    <div style="color: #166534; font-weight: 500;">${file.name}</div>
                    <small class="text-muted">${fileSize} Mo</small>
                </div>
            </div>
        `;
    } else {
        fileName.innerHTML = '';
    }
}

// Insérer un modèle de texte dans le champ de message
function insertTemplate(type) {
    const messageField = document.getElementById('message');
    let template = '';
    
    switch(type) {
        case 'cordial':
            template = '\n\nCordialement,\n[Votre nom]\n[Votre poste]';
            break;
    }
    
    const startPos = messageField.selectionStart;
    const endPos = messageField.selectionEnd;
    const currentText = messageField.value;
    
    // Insérer le modèle à la position actuelle du curseur
    messageField.value = currentText.substring(0, startPos) + template + currentText.substring(endPos);
    
    // Positionner le curseur après le modèle inséré
    messageField.focus();
    messageField.setSelectionRange(startPos + template.length, startPos + template.length);
}

// Gestion du glisser-déposer de fichiers
document.addEventListener('DOMContentLoaded', function() {
    const dropArea = document.querySelector('.file-upload-label');
    const fileInput = document.getElementById('fileInput');
    
    if (!dropArea || !fileInput) return;
    
    // Empêcher le comportement par défaut pour les événements de glisser
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropArea.addEventListener(eventName, preventDefaults, false);
        document.body.addEventListener(eventName, preventDefaults, false);
    });
    
    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }
    
    // Effet de survol
    ['dragenter', 'dragover'].forEach(eventName => {
        dropArea.addEventListener(eventName, highlight, false);
    });
    
    ['dragleave', 'drop'].forEach(eventName => {
        dropArea.addEventListener(eventName, unhighlight, false);
    });
    
    function highlight() {
        dropArea.style.borderColor = '#7C3AED';
        dropArea.style.backgroundColor = '#F5F3FF';
    }
    
    function unhighlight() {
        dropArea.style.borderColor = '#dee2e6';
        dropArea.style.backgroundColor = '#fff';
    }
    
    // Gérer le dépôt de fichiers
    dropArea.addEventListener('drop', handleDrop, false);
    
    function handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        
        if (files.length) {
            fileInput.files = files;
            updateFileName(fileInput);
        }
    }
    
    // Mettre à jour le style des cases à cocher cochées
    const checkboxes = document.querySelectorAll('.destinataire-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const item = this.closest('.organisateur-item');
            if (this.checked) {
                item.style.background = '#F5F3FF';
                item.style.borderColor = '#7C3AED';
            } else {
                item.style.background = '#FFFFFF';
                item.style.borderColor = '#E5E7EB';
            }
        });
    });
});

// Validation avant envoi - CORRIGÉ
document.getElementById('emailForm').addEventListener('submit', function(e) {
    const checkboxes = document.querySelectorAll('.destinataire-checkbox:checked');
    if (checkboxes.length === 0) {
        e.preventDefault();
        alert('Veuillez sélectionner au moins un destinataire');
        return false;
    }
    
    const btn = document.getElementById('sendBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> Envoi en cours...';
});
</script>

<?php include '../includes/footer.php'; ?>