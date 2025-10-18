// deletePopup.js
function openDeletePopup() {
    const popup = document.getElementById('delete-popup');
    popup.classList.add('active');
    popup.setAttribute('aria-hidden', 'false');
    popup.querySelector('input[name="password"]').focus();
}

function closeDeletePopup() {
    const popup = document.getElementById('delete-popup');
    popup.classList.remove('active');
    popup.setAttribute('aria-hidden', 'true');
}