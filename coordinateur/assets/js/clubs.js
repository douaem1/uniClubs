// clubs.js - Modern clubs management with animations and preview

document.addEventListener('DOMContentLoaded', function() {
    // Animate cards on load
    animateCards();
    
    // Setup image preview
    setupImagePreview();
    
    // Setup file upload label update
    setupFileUpload();
});

// Animate cards with stagger effect
function animateCards() {
    const cards = document.querySelectorAll('.club-card-modern');
    cards.forEach((card, index) => {
        setTimeout(() => {
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });
}

// Image preview function
function previewImage(input) {
    const preview = document.getElementById('imagePreview');
    const fileLabel = input.nextElementSibling;
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        const file = input.files[0];
        
        // Check file size (2MB max)
        if (file.size > 2 * 1024 * 1024) {
            alert('Le fichier est trop volumineux. Taille maximale : 2 Mo');
            input.value = '';
            return;
        }
        
        reader.onload = function(e) {
            preview.innerHTML = `
                <div class="preview-container">
                    <img src="${e.target.result}" alt="Preview">
                    <button type="button" class="remove-preview" onclick="removePreview()">
                        <i class="bi bi-x-circle-fill"></i>
                    </button>
                </div>
            `;
            preview.style.display = 'block';
            
            // Update label
            fileLabel.querySelector('span').textContent = file.name;
            fileLabel.classList.add('file-selected');
        };
        
        reader.readAsDataURL(file);
    }
}

// Remove image preview
function removePreview() {
    const input = document.getElementById('photoclub');
    const preview = document.getElementById('imagePreview');
    const fileLabel = input.nextElementSibling;
    
    input.value = '';
    preview.innerHTML = '';
    preview.style.display = 'none';
    
    // Reset label
    fileLabel.querySelector('span').textContent = 'Cliquez pour choisir une image';
    fileLabel.classList.remove('file-selected');
}

// Setup image preview
function setupImagePreview() {
    const fileInput = document.getElementById('photoclub');
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            previewImage(this);
        });
    }
}

// Setup file upload label update
function setupFileUpload() {
    const fileInput = document.getElementById('photoclub');
    const fileLabel = document.querySelector('.file-upload-label-modern');
    
    if (fileInput && fileLabel) {
        fileLabel.addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-preview')) {
                e.preventDefault();
            }
        });
    }
}

// Escape HTML for security
function escapeHtml(text) {
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
