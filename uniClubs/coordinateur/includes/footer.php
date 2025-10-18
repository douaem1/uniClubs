</div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="footer bg-white border-top mt-auto py-4">
        <div class="container-fluid px-4">
            <div class="row align-items-center">
                <div class="col-md-6 text-center text-md-start mb-3 mb-md-0">
                    <div class="d-flex align-items-center justify-content-center justify-content-md-start gap-2">
                        <i class="bi bi-people-fill fs-5" style="color: #7C3AED;"></i>
                        <span class="fw-bold" style="color: #7C3AED;">UniClubs</span>
                        <span class="text-muted">- Espace Coordinateur</span>
                    </div>
                    <p class="text-muted small mb-0 mt-2">
                        Gestion centralisée des clubs et événements universitaires
                    </p>
                </div>
                <div class="col-md-6">
                    <div class="d-flex flex-column flex-md-row align-items-center justify-content-center justify-content-md-end gap-3">
                        <div class="text-center text-md-end">
                            <small class="text-muted d-block">
                                &copy; <?php echo date('Y'); ?> UniClubs. Tous droits réservés.
                            </small>
                            <small class="text-muted d-block">
                                Version 1.0.0
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap 5.3 JS Bundle (avec Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Script pour afficher le toast si message flash présent -->
    <script>
        <?php if ($flashMessage): ?>
        document.addEventListener('DOMContentLoaded', function() {
            var toastEl = document.getElementById('liveToast');
            var toast = new bootstrap.Toast(toastEl);
            toast.show();
        });
        <?php endif; ?>
        
        // Initialiser tous les tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    </script>
</body>
</html>