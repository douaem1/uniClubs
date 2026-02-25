<?php
$pageTitle = "Gestion des clubs";
include '../../ConnDB.php';
include '../includes/header.php';

// Récupérer tous les clubs
$query = "
    SELECT c.*, co.Email AS OrgEmail, p.Nom AS NomOrg, p.Prenom AS PrenomOrg
    FROM Club c
    LEFT JOIN Organisateur o ON c.idOrganisateur = o.idCompte
    LEFT JOIN Compte co ON o.idCompte = co.idCompte
    LEFT JOIN Participant p ON o.idCompte = p.idCompte
    ORDER BY c.NomClub
";
$result = $conn->query($query);
$clubs = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $clubs[] = $row;
    }
    $result->free();
}

// Si édition demandée, charger le club et préparer le formulaire en mode update
$editingClub = null;
$editingOrgEmail = '';
if (isset($_GET['edit'])) {
    $idToEdit = intval($_GET['edit']);
    // Récupérer le club avec l'email de l'organisateur
    $stmt = $conn->prepare("
        SELECT c.*, co.Email AS OrgEmail
        FROM Club c
        LEFT JOIN Compte co ON c.idOrganisateur = co.idCompte
        WHERE c.idClub = ?
    ");
    $stmt->bind_param("i", $idToEdit);
    $stmt->execute();
    $result = $stmt->get_result();
    $editingClub = $result->fetch_assoc();
    if ($editingClub) {
        $editingOrgEmail = $editingClub['OrgEmail'] ?? '';
    }
    $stmt->close();
}

// Si confirmation suppression demandée
$confirmDeleteClub = null;
if (isset($_GET['confirm_delete'])) {
    $idToDelete = intval($_GET['confirm_delete']);
    $stmt = $conn->prepare("SELECT idClub, NomClub FROM Club WHERE idClub = ?");
    $stmt->bind_param("i", $idToDelete);
    $stmt->execute();
    $result = $stmt->get_result();
    $confirmDeleteClub = $result->fetch_assoc();
    $stmt->close();
}

// Liste des organisateurs pour le menu déroulant
$query = "
    SELECT o.idCompte, c.Email, p.Nom, p.Prenom 
    FROM Organisateur o 
    LEFT JOIN Compte c ON o.idCompte = c.idCompte 
    LEFT JOIN Participant p ON o.idCompte = p.idCompte 
    ORDER BY p.Nom, p.Prenom
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

<div class="clubs-management">
    <!-- Modern Header -->
    <div class="clubs-header">
        <div class="header-content">
            <div class="header-top">
                <a href="../pages/dashboard.php" class="btn-back">
                    <i class="bi bi-arrow-left"></i>
                    <span>Retour au tableau de bord</span>
                </a>
                <a href="?create=1" class="btn-create-club">
                    <i class="bi bi-plus-circle-fill"></i>
                    <span>Créer un club</span>
                </a>
            </div>
            <div class="header-main">
                <h1 class="page-title">Gestion des clubs</h1>
                <p class="page-subtitle">Gérez vos clubs universitaires, modifiez les informations et suivez les adhérents</p>
            </div>
        </div>
    </div>

    <!-- Modern Clubs Grid -->
    <div class="clubs-container">
        <?php if (empty($clubs)): ?>
            <div class="empty-state-modern">
                <div class="empty-icon">
                    <i class="bi bi-people"></i>
                </div>
                <h3>Aucun club pour le moment</h3>
                <p>Créez votre premier club pour commencer à gérer vos adhérents</p>
                <a href="?create=1" class="btn-create-empty">
                    <i class="bi bi-plus-circle-fill"></i>
                    <span>Créer un club</span>
                </a>
            </div>
        <?php else: ?>
            <div class="clubs-grid-modern">
                <?php foreach ($clubs as $index => $club): ?>
                <div class="club-card-modern" style="animation-delay: <?php echo $index * 0.1; ?>s">
                    <!-- Card Image -->
                    <div class="club-card-image">
                        <?php if ($club['photoclub']): ?>
                            <img src="../uploads/clubs/<?php echo htmlspecialchars($club['photoclub']); ?>" 
                                 alt="<?php echo htmlspecialchars($club['NomClub']); ?>">
                            <div class="image-overlay"></div>
                        <?php else: ?>
                            <div class="club-placeholder-modern">
                                <i class="bi bi-people-fill"></i>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Card Body -->
                    <div class="club-card-body">
                        <h3 class="club-name"><?php echo htmlspecialchars($club['NomClub']); ?></h3>
                        <p class="club-description-modern">
                            <?php 
                                $desc = $club['description'] ?? 'Aucune description disponible';
                                echo htmlspecialchars(substr($desc, 0, 100));
                                echo strlen($desc) > 100 ? '...' : '';
                            ?>
                        </p>

                        <!-- Réseaux sociaux -->
                        <?php 
                        $hasSocial = !empty($club['facebook']) || !empty($club['instagram']) || 
                                     !empty($club['twitter']) || !empty($club['linkedin']) || 
                                     !empty($club['website']);
                        ?>
                        <?php if ($hasSocial): ?>
                        <div class="club-social-links">
                            <?php if (!empty($club['facebook'])): ?>
                                <a href="<?php echo htmlspecialchars($club['facebook']); ?>" 
                                   target="_blank" 
                                   rel="noopener noreferrer" 
                                   class="social-icon facebook-icon"
                                   title="Facebook">
                                    <i class="bi bi-facebook"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php if (!empty($club['instagram'])): ?>
                                <a href="<?php echo htmlspecialchars($club['instagram']); ?>" 
                                   target="_blank" 
                                   rel="noopener noreferrer" 
                                   class="social-icon instagram-icon"
                                   title="Instagram">
                                    <i class="bi bi-instagram"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php if (!empty($club['twitter'])): ?>
                                <a href="<?php echo htmlspecialchars($club['twitter']); ?>" 
                                   target="_blank" 
                                   rel="noopener noreferrer" 
                                   class="social-icon twitter-icon"
                                   title="Twitter">
                                    <i class="bi bi-twitter"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php if (!empty($club['linkedin'])): ?>
                                <a href="<?php echo htmlspecialchars($club['linkedin']); ?>" 
                                   target="_blank" 
                                   rel="noopener noreferrer" 
                                   class="social-icon linkedin-icon"
                                   title="LinkedIn">
                                    <i class="bi bi-linkedin"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php if (!empty($club['website'])): ?>
                                <a href="<?php echo htmlspecialchars($club['website']); ?>" 
                                   target="_blank" 
                                   rel="noopener noreferrer" 
                                   class="social-icon website-icon"
                                   title="Site web">
                                    <i class="bi bi-globe"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Card Footer -->
                    <div class="club-card-footer">
                        <a class="btn-edit-modern" href="clubs.php?edit=<?php echo $club['idClub']; ?>">
                            <i class="bi bi-pencil-square"></i>
                            <span>Modifier</span>
                        </a>
                        <a class="btn-delete-modern" href="clubs.php?confirm_delete=<?php echo $club['idClub']; ?>">
                            <i class="bi bi-trash"></i>
                            <span>Supprimer</span>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Créer/Modifier Club -->
<div id="addClubModal" class="modal <?php echo (isset($_GET['create']) || $editingClub) ? 'open' : ''; ?>">
    <div class="modal-content modal-modern">
        <div class="modal-header-modern">
            <h2 id="modalTitle"><?php echo $editingClub ? 'Modifier le club' : '➕ Créer un nouveau club'; ?></h2>
            <a href="clubs.php" class="close-modern" aria-label="Fermer">
                <i class="bi bi-x-lg"></i>
            </a>
        </div>

        <form id="clubForm" method="POST" action="../actions/clubs.php" enctype="multipart/form-data">
            <input type="hidden" name="action" id="formAction" value="<?php echo $editingClub ? 'update' : 'create'; ?>">
            <input type="hidden" name="idClub" id="idClub" value="<?php echo $editingClub['idClub'] ?? ''; ?>">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

            <div class="modal-body-modern">
                <div class="form-group-modern">
                    <label for="nomClub"><i class="bi bi-tag-fill"></i> Nom du club *</label>
                    <input type="text" id="nomClub" name="nomClub" required 
                           placeholder="Ex: Club de Robotique" 
                           value="<?php echo htmlspecialchars($editingClub['NomClub'] ?? ''); ?>">
                </div>

                <div class="form-group-modern">
                    <label for="description"><i class="bi bi-text-paragraph"></i> Description du club</label>
                    <textarea id="description" name="description" rows="4" 
                              placeholder="Décrivez les activités et objectifs du club..."><?php echo htmlspecialchars($editingClub['description'] ?? ''); ?></textarea>
                </div>

                <div class="form-group-modern">
                    <label for="photoclub"><i class="bi bi-image-fill"></i> Photo du club</label>
                    
                    <?php if ($editingClub && !empty($editingClub['photoclub'])): ?>
                    <!-- Afficher la photo actuelle -->
                    <div class="current-image-preview">
                        <img src="../uploads/clubs/<?php echo htmlspecialchars($editingClub['photoclub']); ?>" 
                             alt="Photo actuelle" 
                             style="max-width: 200px; max-height: 200px; border-radius: 8px; margin-bottom: 10px;">
                        <p style="color: #666; font-size: 14px;">Photo actuelle (choisissez un nouveau fichier pour la remplacer)</p>
                    </div>
                    <?php endif; ?>
                    
                    <div class="file-upload-modern">
                        <input type="file" id="photoclub" name="photoclub" accept="image/*" onchange="previewImage(this)">
                        <label for="photoclub" class="file-upload-label-modern">
                            <i class="bi bi-cloud-upload-fill"></i>
                            <span><?php echo $editingClub ? 'Choisir une nouvelle image' : 'Cliquez pour choisir une image'; ?></span>
                            <small>JPG, PNG (max 2 Mo)</small>
                        </label>
                    </div>
                    <div id="imagePreview" class="image-preview-modern"></div>
                </div>

                <div class="form-group-modern">
                    <label for="orgEmail"><i class="bi bi-envelope-fill"></i> Email de l'organisateur *</label>
                    <input type="email" id="orgEmail" name="orgEmail" 
                           placeholder="organisateur@example.com" 
                           <?php echo !$editingClub ? 'required' : ''; ?>
                           value="<?php echo htmlspecialchars($editingOrgEmail); ?>">
                </div>

                <div class="form-group-modern">
                    <label for="orgPassword"><i class="bi bi-lock-fill"></i> Mot de passe de l'organisateur <?php echo $editingClub ? '(laisser vide pour ne pas modifier)' : '*'; ?></label>
                    <input type="password" id="orgPassword" name="orgPassword" 
                           placeholder="••••••••" 
                           <?php echo !$editingClub ? 'required' : ''; ?>>
                    <?php if ($editingClub): ?>
                    <small style="color: #666; font-size: 12px; display: block; margin-top: 5px;">
                        Laissez ce champ vide si vous ne souhaitez pas changer le mot de passe
                    </small>
                    <?php endif; ?>
                </div>

                <!-- Réseaux sociaux -->
                <div class="form-section-title">
                    <i class="bi bi-share-fill"></i> Réseaux sociaux (optionnel)
                </div>

                <div class="form-group-modern">
                    <label for="facebook"><i class="bi bi-facebook"></i> Facebook</label>
                    <input type="url" id="facebook" name="facebook" 
                           placeholder="https://facebook.com/votre-club" 
                           value="<?php echo htmlspecialchars($editingClub['facebook'] ?? ''); ?>">
                </div>

                <div class="form-group-modern">
                    <label for="instagram"><i class="bi bi-instagram"></i> Instagram</label>
                    <input type="url" id="instagram" name="instagram" 
                           placeholder="https://instagram.com/votre-club" 
                           value="<?php echo htmlspecialchars($editingClub['instagram'] ?? ''); ?>">
                </div>

                <div class="form-group-modern">
                    <label for="twitter"><i class="bi bi-twitter"></i> Twitter</label>
                    <input type="url" id="twitter" name="twitter" 
                           placeholder="https://twitter.com/votre-club" 
                           value="<?php echo htmlspecialchars($editingClub['twitter'] ?? ''); ?>">
                </div>

                <div class="form-group-modern">
                    <label for="linkedin"><i class="bi bi-linkedin"></i> LinkedIn</label>
                    <input type="url" id="linkedin" name="linkedin" 
                           placeholder="https://linkedin.com/company/votre-club" 
                           value="<?php echo htmlspecialchars($editingClub['linkedin'] ?? ''); ?>">
                </div>

                <div class="form-group-modern">
                    <label for="website"><i class="bi bi-globe"></i> Site web</label>
                    <input type="url" id="website" name="website" 
                           placeholder="https://votre-club.com" 
                           value="<?php echo htmlspecialchars($editingClub['website'] ?? ''); ?>">
                </div>
            </div>

            <div class="modal-footer-modern">
                <a href="clubs.php" class="btn-cancel-modern">Annuler</a>
                <button type="submit" class="btn-submit-modern">
                    <i class="bi bi-check-circle-fill"></i>
                    <span><?php echo $editingClub ? 'Mettre à jour' : 'Créer le club'; ?></span>
                </button>
            </div>
        </form>
    </div>
</div>

<script src="../assets/js/clubs.js"></script>
<?php if ($confirmDeleteClub): ?>
<!-- Modal confirmation suppression -->
<div class="modal open" id="confirmDeleteModal">
    <div class="modal-content modal-modern modal-delete">
        <div class="modal-header-modern">
            <h2>⚠️ Confirmer la suppression</h2>
            <a href="clubs.php" class="close-modern" aria-label="Fermer">
                <i class="bi bi-x-lg"></i>
            </a>
        </div>
        <div class="modal-body-modern">
            <div class="delete-warning">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <p>Êtes-vous sûr de vouloir supprimer le club <strong><?php echo htmlspecialchars($confirmDeleteClub['NomClub']); ?></strong> ?</p>
                <p class="warning-text">Cette action est définitive et irréversible.</p>
            </div>
        </div>
        <div class="modal-footer-modern">
            <a href="clubs.php" class="btn-cancel-modern">Annuler</a>
            <a href="../actions/clubs.php?action=delete&id=<?php echo intval($confirmDeleteClub['idClub']); ?>" class="btn-delete-confirm">
                <i class="bi bi-trash-fill"></i>
                <span>Supprimer définitivement</span>
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
/* Styles pour les icônes des réseaux sociaux */
.club-social-links {
    display: flex;
    gap: 8px;
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid #eee;
}

.social-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    color: white;
    font-size: 14px;
    transition: all 0.3s ease;
    text-decoration: none;
}

.social-icon:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.facebook-icon {
    background: #1877F2;
}

.facebook-icon:hover {
    background: #166FE5;
}

.instagram-icon {
    background: linear-gradient(45deg, #F58529, #DD2A7B, #8134AF, #515BD4);
}

.instagram-icon:hover {
    filter: brightness(1.1);
}

.twitter-icon {
    background: #1DA1F2;
}

.twitter-icon:hover {
    background: #1A91DA;
}

.linkedin-icon {
    background: #0A66C2;
}

.linkedin-icon:hover {
    background: #004182;
}

.website-icon {
    background: #6c757d;
}

.website-icon:hover {
    background: #5a6268;
}

/* Style pour l'aperçu de l'image actuelle */
.current-image-preview {
    margin-bottom: 15px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
    text-align: center;
}

.current-image-preview img {
    display: block;
    margin: 0 auto;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}
</style>

<?php include '../includes/footer.php'; ?>