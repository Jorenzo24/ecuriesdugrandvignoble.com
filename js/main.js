document.addEventListener('DOMContentLoaded', () => {
    // ---- Langue courante (fr par défaut, en pour les pages /en/) ----
    const isEN = (document.documentElement.lang || 'fr').toLowerCase().startsWith('en');
    const t = {
        menuOpen:  isEN ? 'Open menu'  : 'Ouvrir le menu',
        menuClose: isEN ? 'Close menu' : 'Fermer le menu',
        formOk:    isEN
            ? 'Thank you, your message has been sent. We will get back to you shortly.'
            : 'Merci, votre message a bien été envoyé. Nous reviendrons vers vous rapidement.',
        formErr:   isEN
            ? 'Something went wrong. You can reach us on +33 6 81 07 84 53.'
            : 'Une erreur est survenue. Vous pouvez nous appeler au 06 81 07 84 53.',
    };

    // ---- Année dynamique du copyright ----
    const yearEl = document.getElementById('year');
    if (yearEl) yearEl.textContent = new Date().getFullYear();

    // ---- Header : état "scrolled" pour basculer fond blanc/transparent ----
    const header = document.getElementById('site-header');
    if (header) {
        const onScroll = () => {
            header.classList.toggle('is-scrolled', window.scrollY > 24);
        };
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });

        // ---- Menu mobile ----
        const toggle = header.querySelector('.site-header__toggle');
        const nav = header.querySelector('.site-nav');
        if (toggle && nav) {
            toggle.addEventListener('click', () => {
                const isOpen = header.classList.toggle('is-open');
                toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                toggle.setAttribute('aria-label', isOpen ? t.menuClose : t.menuOpen);
                document.body.style.overflow = isOpen ? 'hidden' : '';
            });

            nav.querySelectorAll('a').forEach((a) => {
                a.addEventListener('click', () => {
                    header.classList.remove('is-open');
                    toggle.setAttribute('aria-expanded', 'false');
                    document.body.style.overflow = '';
                });
            });
        }
    }

    // ---- Alerte formulaire contact (?status=success|error) ----
    const params = new URLSearchParams(window.location.search);
    const status = params.get('status');
    if (status === 'success' || status === 'error') {
        const form = document.querySelector('.contact-form');
        if (form) {
            const alertEl = document.createElement('div');
            alertEl.className = 'contact-form__alert contact-form__alert--' + status;
            alertEl.setAttribute('role', 'alert');
            alertEl.textContent = status === 'success' ? t.formOk : t.formErr;
            form.insertAdjacentElement('beforebegin', alertEl);
            alertEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
            history.replaceState({}, '', window.location.pathname);
        }
    }

    // ---- Reveal au scroll ----
    const revealEls = document.querySelectorAll('.reveal');
    if ('IntersectionObserver' in window && revealEls.length) {
        const io = new IntersectionObserver((entries) => {
            entries.forEach((e) => {
                if (e.isIntersecting) {
                    e.target.classList.add('is-visible');
                    io.unobserve(e.target);
                }
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -60px 0px' });
        revealEls.forEach((el) => io.observe(el));
    } else {
        revealEls.forEach((el) => el.classList.add('is-visible'));
    }
});
