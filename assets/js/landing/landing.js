document.addEventListener('DOMContentLoaded', () => {

    /* Flatpickr */
    if (window.flatpickr) {
        flatpickr("#fecha_nacimiento_flatpickr", {
            locale: "es",
            dateFormat: "d-m-Y",
            maxDate: "today",
            altInput: true,
            altFormat: "j F, Y",
        });
    }

    /* Header scroll state + hide-on-scroll-down */
    const header = document.querySelector('.header');
    let lastY = window.scrollY;
    const onScroll = () => {
        const y = window.scrollY;
        header.classList.toggle('scrolled', y > 24);
        if (y > 140 && y - lastY > 6)      header.classList.add('hidden');
        else if (lastY - y > 4 || y < 80)  header.classList.remove('hidden');
        lastY = y;

        const h = document.documentElement;
        const total = h.scrollHeight - h.clientHeight;
        const pct = total > 0 ? (y / total) * 100 : 0;
        const bar = document.getElementById('scroll-progress');
        if (bar) bar.style.width = pct + '%';
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();

    /* Hamburger */
    const ham = document.getElementById('hamburger');
    const navList = document.getElementById('nav-list');
    if (ham && navList) {
        ham.addEventListener('click', () => navList.classList.toggle('open'));
        navList.querySelectorAll('a').forEach(a => a.addEventListener('click', () => navList.classList.remove('open')));
    }

    /* Reveal on scroll */
    const reveals = document.querySelectorAll('.reveal');
    const io = new IntersectionObserver((entries) => {
        entries.forEach(e => {
            if (e.isIntersecting) {
                e.target.classList.add('in');
                io.unobserve(e.target);
            }
        });
    }, { threshold: 0.12, rootMargin: '0px 0px -50px 0px' });
    reveals.forEach(el => io.observe(el));

    /* Stat counters — solo si hay valor numérico real */
    const counters = document.querySelectorAll('[data-counter]');
    const ioCount = new IntersectionObserver((entries) => {
        entries.forEach(e => {
            if (!e.isIntersecting) return;
            const el = e.target;
            const target = parseInt(el.getAttribute('data-counter'), 10);
            const suffix = el.getAttribute('data-suffix') || '';
            if (isNaN(target) || target === 0) { ioCount.unobserve(el); return; }
            const duration = 1600;
            const start = performance.now();
            const step = (now) => {
                const t = Math.min((now - start) / duration, 1);
                const eased = 1 - Math.pow(1 - t, 3);
                const val = Math.round(target * eased);
                el.textContent = val.toLocaleString('es-VE') + (t === 1 ? suffix : '');
                if (t < 1) requestAnimationFrame(step);
            };
            requestAnimationFrame(step);
            ioCount.unobserve(el);
        });
    }, { threshold: 0.4 });
    counters.forEach(el => ioCount.observe(el));

    /* Magnetic CTA */
    document.querySelectorAll('.btn-primary, .btn-submit').forEach(btn => {
        btn.addEventListener('mousemove', (e) => {
            const r = btn.getBoundingClientRect();
            const x = e.clientX - r.left - r.width / 2;
            const y = e.clientY - r.top - r.height / 2;
            btn.style.transform = `translate(${x * 0.12}px, ${y * 0.18}px)`;
        });
        btn.addEventListener('mouseleave', () => { btn.style.transform = ''; });
    });

    /* Auto-hide status message */
    const msg = document.getElementById('msg-estado');
    if (msg) {
        setTimeout(() => {
            msg.style.transition = 'opacity .4s, transform .4s';
            msg.style.opacity = '0';
            msg.style.transform = 'translate(-50%, -20px)';
            setTimeout(() => msg.remove(), 500);
        }, 5500);
    }
});

/* ── Spotlight en tarjetas + CTA magnético ── */
(function () {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    if (window.matchMedia('(hover: none)').matches) return; // sin efectos en táctil

    document.querySelectorAll('.stat-card, .beneficio').forEach(function (card) {
        card.addEventListener('pointermove', function (e) {
            var r = card.getBoundingClientRect();
            card.style.setProperty('--mx', ((e.clientX - r.left) / r.width * 100).toFixed(1) + '%');
            card.style.setProperty('--my', ((e.clientY - r.top) / r.height * 100).toFixed(1) + '%');
        });
    });

    var mag = document.querySelector('.hero-ctas .btn-primary');
    if (mag) {
        mag.addEventListener('pointermove', function (e) {
            var r = mag.getBoundingClientRect();
            var x = (e.clientX - r.left - r.width / 2) * 0.16;
            var y = (e.clientY - r.top - r.height / 2) * 0.26;
            mag.style.transform = 'translate(' + x.toFixed(2) + 'px,' + (y - 2).toFixed(2) + 'px)';
        });
        mag.addEventListener('pointerleave', function () { mag.style.transform = ''; });
    }
})();
