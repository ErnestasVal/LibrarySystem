// Modal functionality
const loginModal = document.getElementById('loginModal');
const registerModal = document.getElementById('registerModal');
const editModal = document.getElementById('editModal');
const reservationModal = document.getElementById('reservationModal');

function openLoginModal() {
    loginModal.style.display = 'block';
}

function closeLoginModal() {
    loginModal.style.display = 'none';
}

function openRegisterModal() {
    registerModal.style.display = 'block';
    loginModal.style.display = 'none';
}

function closeRegisterModal() {
    registerModal.style.display = 'none';
}

function showRegisterForm() {
    loginModal.style.display = 'none';
    registerModal.style.display = 'block';
}

function showLoginForm() {
    registerModal.style.display = 'none';
    loginModal.style.display = 'block';
}

function openReservationModal(book) {
    const reservationDetails = document.getElementById('reservationDetails');
    const returnDate = new Date();
    returnDate.setDate(returnDate.getDate() + parseInt(book.isdavimo_laikas));
    
    const formattedDate = returnDate.toISOString().split('T')[0];
    
    reservationDetails.innerHTML = `
        <h3>${book.pavadinimas}</h3>
        <div class="reservation-info">
            <p><strong>Autorius:</strong> ${book.autorius}</p>
            <p><strong>Išdavimo laikas:</strong> ${book.isdavimo_laikas} dienos</p>
            <p><strong>Numatoma grąžinimo data:</strong> ${formattedDate}</p>
            <p><strong>Laisvų egzempliorių:</strong> ${book.available_copies} iš ${book.egzemplioriu_sk}</p>
        </div>
        <p>Ar norite patvirtinti šios knygos rezervaciją?</p>
    `;
    
    document.getElementById('reservation_book_id').value = book.id;
    reservationModal.style.display = 'block';
}

function closeReservationModal() {
    if (reservationModal) {
        reservationModal.style.display = 'none';
    }
}

function openEditModal(book) {
    document.getElementById('edit_book_id').value = book.id;
    document.getElementById('edit_pavadinimas').value = book.pavadinimas;
    document.getElementById('edit_autorius').value = book.autorius;
    document.getElementById('edit_puslapiu_sk').value = book.puslapiu_sk;
    document.getElementById('edit_egzemplioriu_sk').value = book.egzemplioriu_sk;
    document.getElementById('edit_isdavimo_laikas').value = book.isdavimo_laikas;
    
    editModal.style.display = 'block';
}

function closeEditModal() {
    if (editModal) {
        editModal.style.display = 'none';
    }
}

// Admin Panel functionality
function openAdminPanel() {
    document.querySelector('.admin-panel').scrollIntoView({ 
        behavior: 'smooth' 
    });
}

// Close modals when clicking X
document.querySelectorAll('.close').forEach(closeBtn => {
    closeBtn.addEventListener('click', function() {
        this.closest('.modal').style.display = 'none';
    });
});

// Close modals when clicking outside
window.addEventListener('click', function(event) {
    if (event.target === loginModal) {
        closeLoginModal();
    }
    if (event.target === registerModal) {
        closeRegisterModal();
    }
    if (editModal && event.target === editModal) {
        closeEditModal();
    }
    if (reservationModal && event.target === reservationModal) {
        closeReservationModal();
    }
});

// Close modals with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeLoginModal();
        closeRegisterModal();
        closeEditModal();
        closeReservationModal();
    }
});

// On load, react to any server-set flags (set via inline script in the page)
document.addEventListener('DOMContentLoaded', function() {
    if (window.openAdminPanelOnLoad) {
        openAdminPanel();
    }
    if (window.showLoginAfterRegister) {
        openLoginModal();
    }

    // Live search filter for books
    const searchInput = document.getElementById('bookSearch');
    const bookCards = Array.from(document.querySelectorAll('.books-grid .book-card'));
    const noResults = document.getElementById('noResults');

    if (searchInput) {
        // simple debounce to avoid excessive DOM operations
        let debounceTimer = null;
        searchInput.addEventListener('input', function(e) {
            const q = e.target.value.trim().toLowerCase();
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                let visible = 0;
                bookCards.forEach(card => {
                    const text = card.textContent.toLowerCase();
                    if (q === '' || text.indexOf(q) !== -1) {
                        card.style.display = '';
                        visible++;
                    } else {
                        card.style.display = 'none';
                    }
                });

                if (noResults) {
                    noResults.style.display = visible === 0 ? '' : 'none';
                }
            }, 120);
        });
    }
});