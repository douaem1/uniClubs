// Recherche dynamique des événements avec AJAX
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('search');
    const statutSelect = document.getElementById('statut');
    const clubSelect = document.getElementById('club');
    const dateDebutInput = document.getElementById('date_debut');
    const dateFinInput = document.getElementById('date_fin');
    const eventsTableBody = document.querySelector('.modern-table tbody');
    
    let searchTimeout = null;
    
    // Fonction pour effectuer la recherche AJAX
    function performSearch() {
        const searchValue = searchInput ? searchInput.value : '';
        const statutValue = statutSelect ? statutSelect.value : 'tous';
        const clubValue = clubSelect ? clubSelect.value : 'tous';
        const dateDebutValue = dateDebutInput ? dateDebutInput.value : '';
        const dateFinValue = dateFinInput ? dateFinInput.value : '';
        
        // Construire l'URL avec les paramètres
        const params = new URLSearchParams({
            ajax: '1',
            search: searchValue,
            statut: statutValue,
            club: clubValue,
            date_debut: dateDebutValue,
            date_fin: dateFinValue
        });
        
        // Afficher un indicateur de chargement
        if (eventsTableBody) {
            eventsTableBody.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Chargement...</span>
                        </div>
                        <p class="mt-2 text-muted">Recherche en cours...</p>
                    </td>
                </tr>
            `;
        }
        
        // Effectuer la requête AJAX
        fetch(`../actions/events.php?action=search&${params.toString()}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Erreur réseau');
                }
                return response.json();
            })
            .then(data => {
                updateEventsTable(data);
            })
            .catch(error => {
                console.error('Erreur:', error);
                if (eventsTableBody) {
                    eventsTableBody.innerHTML = `
                        <tr>
                            <td colspan="6" class="text-center py-5">
                                <i class="bi bi-exclamation-triangle fs-1 text-danger d-block mb-2"></i>
                                <span class="text-danger">Erreur lors de la recherche</span>
                            </td>
                        </tr>
                    `;
                }
            });
    }
    
    // Fonction pour mettre à jour le tableau des événements
    function updateEventsTable(events) {
        if (!eventsTableBody) return;
        
        if (events.length === 0) {
            eventsTableBody.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center py-5">
                        <i class="bi bi-calendar-x fs-1 text-muted d-block mb-2"></i>
                        <span class="text-muted">Aucun événement trouvé</span>
                    </td>
                </tr>
            `;
            return;
        }
        
        let html = '';
        events.forEach(event => {
            const dateDebut = new Date(event.dateDebut);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const eventDate = new Date(dateDebut);
            eventDate.setHours(0, 0, 0, 0);
            
            const isToday = eventDate.getTime() === today.getTime();
            const isPast = eventDate < today;
            
            const rowClass = isToday ? 'table-row-today' : (isPast ? 'table-row-past' : '');
            
            const dateFormatted = dateDebut.toLocaleDateString('fr-FR');
            const timeFormatted = dateDebut.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
            
            const dateFin = event.dateFin ? new Date(event.dateFin).toLocaleDateString('fr-FR') : '-';
            
            let actionButtons = '';
            const validation = (event.validation || 'en attente').toLowerCase().trim();
            
            if (validation === 'validé') {
                actionButtons = `
                    <span class="btn btn-sm btn-success-modern disabled" style="cursor: default;">
                        <i class="bi bi-check-circle-fill me-1"></i> Validé
                    </span>
                `;
            } else if (validation === 'refusé') {
                actionButtons = `
                    <span class="btn btn-sm btn-danger-modern disabled" style="cursor: default;">
                        <i class="bi bi-x-circle-fill me-1"></i> Refusé
                    </span>
                `;
            } else {
                actionButtons = `
                    <a href="../actions/events.php?action=validate&id=${event.Idenv}" 
                       class="btn btn-sm btn-success-modern" 
                       data-bs-toggle="tooltip" 
                       title="Accepter l'événement">
                        <i class="bi bi-check-lg"></i>
                    </a>
                    <button type="button" 
                            onclick="openRejectModal(${event.Idenv}, '${escapeHtml(event.NomEnv)}')" 
                            class="btn btn-sm btn-danger-modern" 
                            data-bs-toggle="tooltip" 
                            title="Refuser l'événement">
                        <i class="bi bi-x-lg"></i>
                    </button>
                `;
            }
            
            html += `
                <tr class="table-row-modern ${rowClass}">
                    <td class="ps-4 py-3">
                        <div class="d-flex align-items-center">
                            <div class="rounded-3 d-flex align-items-center justify-content-center me-3 shadow-sm" style="width: 48px; height: 48px; background: linear-gradient(135deg, #EDE9FE, #DDD6FE);">
                                <i class="bi bi-calendar-event fs-5" style="color: #7C3AED;"></i>
                            </div>
                            <div>
                                <div class="fw-semibold text-dark mb-1">${escapeHtml(event.NomEnv)}</div>
                                <small class="text-muted">
                                    <i class="bi bi-clock me-1"></i>${timeFormatted}
                                </small>
                            </div>
                        </div>
                    </td>
                    <td class="py-3">
                        <span class="badge badge-club-modern">
                            <i class="bi bi-people-fill me-1"></i>
                            ${escapeHtml(event.NomClub || 'N/A')}
                        </span>
                    </td>
                    <td class="py-3">
                        <span class="fw-semibold text-dark">${dateFormatted}</span>
                    </td>
                    <td class="py-3">
                        <span class="text-dark">${dateFin}</span>
                    </td>
                    <td class="py-3">
                        <span class="text-dark">
                            <i class="bi bi-geo-alt-fill text-primary me-1"></i>
                            ${escapeHtml(event.Lieu || 'Non défini')}
                        </span>
                    </td>
                    <td class="text-center pe-4 py-3">
                        <div class="d-flex gap-2 justify-content-center action-buttons-modern">
                            ${actionButtons}
                            <a href="events.php?view=${event.Idenv}" 
                               class="btn btn-sm btn-info-modern"
                               data-bs-toggle="tooltip" 
                               title="Voir les détails">
                                <i class="bi bi-eye-fill"></i>
                            </a>
                        </div>
                    </td>
                </tr>
            `;
        });
        
        eventsTableBody.innerHTML = html;
        
        // Réinitialiser les tooltips Bootstrap
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
    
    // Fonction pour échapper le HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Écouter les changements sur le champ de recherche avec debounce
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(performSearch, 500); // Attendre 500ms après la dernière frappe
        });
    }
    
    // Écouter les changements sur les filtres
    if (statutSelect) {
        statutSelect.addEventListener('change', performSearch);
    }
    
    if (clubSelect) {
        clubSelect.addEventListener('change', performSearch);
    }
    
    if (dateDebutInput) {
        dateDebutInput.addEventListener('change', performSearch);
    }
    
    if (dateFinInput) {
        dateFinInput.addEventListener('change', performSearch);
    }
    
    // Empêcher la soumission du formulaire (on utilise AJAX maintenant)
    const filtersForm = document.querySelector('.filters-form');
    if (filtersForm) {
        filtersForm.addEventListener('submit', function(e) {
            e.preventDefault();
            performSearch();
        });
    }
});
