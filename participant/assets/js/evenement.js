document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('search');
    const clubSelect = document.getElementById('club-filter'); // nouveau select
    const eventsContainer = document.getElementById('events-container');
    const eventBoxes = eventsContainer.getElementsByClassName('event-box');

    function filterEvents() {
        const query = searchInput.value.toLowerCase();
        const selectedClub = clubSelect.value.toLowerCase();

        Array.from(eventBoxes).forEach(box => {
            const title = box.querySelector('.card-title').textContent.toLowerCase();
            const club = box.querySelector('.chip-club').textContent.toLowerCase();
            const type = box.querySelector('.chip-type').textContent.toLowerCase();
            const locationElement = box.querySelector('.bi-geo-alt');
            const location = locationElement ? locationElement.parentElement.textContent.toLowerCase() : '';

            const matchesSearch = title.includes(query) || club.includes(query) || type.includes(query) || location.includes(query);
            const matchesClub = selectedClub === '' || club === selectedClub;

            if (matchesSearch && matchesClub) {
                box.style.display = '';
            } else {
                box.style.display = 'none';
            }
        });
    }

    // Événements : input et changement de select
    searchInput.addEventListener('input', filterEvents);
    clubSelect.addEventListener('change', filterEvents);
});
