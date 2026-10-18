/**
 * Alexa Gym — User & Dashboard JavaScript
 */

// Immediately restore theme
(function() {
    const savedTheme = localStorage.getItem('gym_theme') || 'dark';
    document.documentElement.setAttribute('data-theme', savedTheme);
})();

function updateThemeIcons(theme) {
    document.querySelectorAll('.theme-toggle i, .action-btn-circle[onclick*="toggleTheme"] i').forEach(icon => {
        if (theme === 'light') {
            icon.classList.remove('fa-moon');
            icon.classList.add('fa-sun');
        } else {
            icon.classList.remove('fa-sun');
            icon.classList.add('fa-moon');
        }
    });
}

function toggleTheme() {
    const currentTheme = document.documentElement.getAttribute('data-theme') || 'dark';
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', newTheme);
    localStorage.setItem('gym_theme', newTheme);
    updateThemeIcons(newTheme);
}

document.addEventListener('DOMContentLoaded', () => {
    const savedTheme = localStorage.getItem('gym_theme') || 'dark';
    updateThemeIcons(savedTheme);

    // Auto-close sidebar on mobile when navigating
    document.querySelectorAll('.sidebar-nav a').forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth <= 900) {
                const sidebar = document.querySelector('.app-sidebar');
                const backdrop = document.getElementById('sidebar-backdrop');
                if (sidebar) sidebar.classList.remove('mobile-open');
                if (backdrop) backdrop.classList.remove('active');
            }
        });
    });
});

// Mobile Sidebar Toggle with Backdrop Overlay
function toggleSidebar() {
    const sidebar = document.querySelector('.app-sidebar');
    if (!sidebar) return;

    let backdrop = document.getElementById('sidebar-backdrop');
    if (!backdrop) {
        backdrop = document.createElement('div');
        backdrop.id = 'sidebar-backdrop';
        backdrop.className = 'sidebar-backdrop';
        document.body.appendChild(backdrop);
        backdrop.addEventListener('click', () => {
            const sb = document.querySelector('.app-sidebar');
            if (sb) sb.classList.remove('mobile-open');
            backdrop.classList.remove('active');
        });
    }

    sidebar.classList.toggle('mobile-open');
    if (sidebar.classList.contains('mobile-open')) {
        backdrop.classList.add('active');
    } else {
        backdrop.classList.remove('active');
    }
}

// Modal System
function openModal(id) {
    const modal = document.getElementById(id);
    if (modal) modal.classList.add('show');
}

function closeModal(id) {
    const modal = document.getElementById(id);
    if (modal) modal.classList.remove('show');
}

// Tabs Switching (e.g. for Workout categories)
function switchTab(tabName) {
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.tab-pane').forEach(pane => pane.classList.remove('active'));

    const activeBtn = document.querySelector(`.tab-btn[data-tab="${tabName}"]`);
    const activePane = document.getElementById(`tab-${tabName}`);
    if (activeBtn) activeBtn.classList.add('active');
    if (activePane) activePane.classList.add('active');
}

// Star Rating Interactive Widget
document.addEventListener('DOMContentLoaded', () => {
    const ratingContainer = document.getElementById('star-rating-widget');
    const ratingInput = document.getElementById('rating-input');
    if (ratingContainer && ratingInput) {
        const stars = ratingContainer.querySelectorAll('i');
        stars.forEach(star => {
            star.addEventListener('mouseover', () => {
                const val = parseInt(star.dataset.val, 10);
                stars.forEach(s => {
                    const sVal = parseInt(s.dataset.val, 10);
                    s.classList.toggle('hover', sVal <= val);
                });
            });

            star.addEventListener('mouseout', () => {
                stars.forEach(s => s.classList.remove('hover'));
            });

            star.addEventListener('click', () => {
                const val = parseInt(star.dataset.val, 10);
                ratingInput.value = val;
                stars.forEach(s => {
                    const sVal = parseInt(s.dataset.val, 10);
                    s.classList.toggle('active', sVal <= val);
                });
            });
        });
    }

    // Close modal on backdrop click
    document.querySelectorAll('.modal-backdrop').forEach(modal => {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) modal.classList.remove('show');
        });
    });
});

// Print Receipt
function printReceipt(printableId = 'receipt-modal') {
    window.print();
}
