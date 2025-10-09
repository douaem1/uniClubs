// clubs.js - gère l'affichage du récapitulatif avant envoi du formulaire de club

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('clubForm');
    if (!form) return;

    const previewBtn = document.getElementById('previewBtn');
    const recap = document.getElementById('recap');
    const recapContent = document.getElementById('recapContent');
    const recapCancel = document.getElementById('recapCancel');
    const recapConfirm = document.getElementById('recapConfirm');

    function buildRecap() {
        const nom = document.getElementById('nomClub').value;
        const desc = document.getElementById('description').value;
        const orgEmail = document.getElementById('orgEmail').value;
        const orgNom = document.getElementById('orgNom') ? document.getElementById('orgNom').value : '';
        const orgPrenom = document.getElementById('orgPrenom') ? document.getElementById('orgPrenom').value : '';

        let html = '<p><strong>Nom du club :</strong> ' + escapeHtml(nom) + '</p>';
        html += '<p><strong>Description :</strong> ' + (escapeHtml(desc) || '<em>Aucune</em>') + '</p>';
        html += '<p><strong>Organisateur :</strong> ' + escapeHtml((orgPrenom + ' ' + orgNom).trim() || orgEmail) + '</p>';
        html += '<p><strong>Email organisateur :</strong> ' + escapeHtml(orgEmail) + '</p>';

        recapContent.innerHTML = html;
    }

    function escapeHtml(text) {
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    previewBtn.addEventListener('click', function(e) {
        buildRecap();
        recap.style.display = 'block';
        // Scroll to recap
        recap.scrollIntoView({ behavior: 'smooth' });
    });

    recapCancel.addEventListener('click', function() {
        recap.style.display = 'none';
    });

    recapConfirm.addEventListener('click', function() {
        // Submit the form
        form.submit();
    });
});
