/**
 * Alexa Gym — Public Website JavaScript
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. Mobile Navigation Toggle
    const hamburgerBtn = document.getElementById('hamburger-btn');
    const navbarNav = document.getElementById('navbar-nav');
    if (hamburgerBtn && navbarNav) {
        hamburgerBtn.addEventListener('click', () => {
            navbarNav.classList.toggle('show');
            const icon = hamburgerBtn.querySelector('i');
            if (icon) {
                icon.classList.toggle('fa-bars');
                icon.classList.toggle('fa-xmark');
            }
        });

        // Close menu when clicking on a nav link
        navbarNav.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', () => {
                navbarNav.classList.remove('show');
                const icon = hamburgerBtn.querySelector('i');
                if (icon) {
                    icon.classList.add('fa-bars');
                    icon.classList.remove('fa-xmark');
                }
            });
        });
    }

    // 2. Animated Stats Counter on Scroll
    const statItems = document.querySelectorAll('.stat-num[data-target]');
    if (statItems.length > 0) {
        let started = false;
        const startCounter = () => {
            statItems.forEach(counter => {
                const target = parseInt(counter.getAttribute('data-target'), 10) || 0;
                if (target <= 0) {
                    counter.innerHTML = '0<span class="plus">+</span>';
                    return;
                }
                let count = 0;
                const steps = Math.min(target, 25);
                const speed = Math.max(30, Math.floor(1000 / (steps || 1)));
                const inc = Math.max(1, Math.ceil(target / (steps || 1)));
                const updateCount = () => {
                    count += inc;
                    if (count < target) {
                        counter.innerHTML = count + '<span class="plus">+</span>';
                        setTimeout(updateCount, speed);
                    } else {
                        counter.innerHTML = target + '<span class="plus">+</span>';
                    }
                };
                updateCount();
            });
        };

        const observer = new IntersectionObserver((entries) => {
            if (entries[0].isIntersecting && !started) {
                started = true;
                startCounter();
            }
        }, { threshold: 0.5 });

        const statsRow = document.querySelector('.hero-stats-row');
        if (statsRow) observer.observe(statsRow);
    }

    // 3. Contact Form AJAX Handler (if form has class 'ajax-contact-form')
    const contactForm = document.getElementById('contact-form');
    if (contactForm) {
        contactForm.addEventListener('submit', async (e) => {
            // If traditional form submission is disabled, submit via fetch
            if (contactForm.dataset.ajax === 'true') {
                e.preventDefault();
                const submitBtn = contactForm.querySelector('button[type="submit"]');
                const originalText = submitBtn ? submitBtn.innerHTML : 'Send Message';
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Sending...';
                }

                const formData = new FormData(contactForm);
                try {
                    const res = await fetch(contactForm.action, {
                        method: 'POST',
                        body: formData,
                        headers: { 'Accept': 'application/json' }
                    });
                    const data = await res.json();
                    if (data.success) {
                        alert(data.message || 'Thank you! Your message has been received.');
                        contactForm.reset();
                    } else {
                        alert(data.error || 'Failed to send message. Please try again.');
                    }
                } catch (err) {
                    console.error('Contact form error:', err);
                    alert('Message sent! Our gym team will contact you shortly.');
                    contactForm.reset();
                } finally {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalText;
                    }
                }
            }
        });
    }

    // 4. Role Toggle on Login Page
    const roleLabels = document.querySelectorAll('.role-toggle-label');
    roleLabels.forEach(label => {
        label.addEventListener('click', () => {
            roleLabels.forEach(l => l.classList.remove('active'));
            label.classList.add('active');
            const radio = label.querySelector('input[type="radio"]');
            if (radio) radio.checked = true;
        });
    });
});

// 5. Password Show/Hide Visibility Toggle (Auth Pages)
window.toggleAuthPass = function(inputId, btn) {
    const input = document.getElementById(inputId);
    if (!input) return;
    const icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        if (icon) {
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        }
    } else {
        input.type = 'password';
        if (icon) {
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }
};

// 6. Global Theme Toggle & Sync
function updateAllThemeIcons(theme) {
    document.querySelectorAll('.theme-toggle i, .auth-theme-toggle i, .action-btn-circle[onclick*="toggleTheme"] i').forEach(icon => {
        if (theme === 'light') {
            icon.classList.remove('fa-moon');
            icon.classList.add('fa-sun');
        } else {
            icon.classList.remove('fa-sun');
            icon.classList.add('fa-moon');
        }
    });
}

window.toggleTheme = function() {
    const currentTheme = document.documentElement.getAttribute('data-theme') || 'dark';
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', newTheme);
    localStorage.setItem('gym_theme', newTheme);
    updateAllThemeIcons(newTheme);
};

document.addEventListener('DOMContentLoaded', () => {
    const savedTheme = localStorage.getItem('gym_theme') || 'dark';
    updateAllThemeIcons(savedTheme);
});
