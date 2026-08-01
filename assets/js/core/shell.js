/* =====================================================================
   ECOMADELLEINE — SHELL JS
   Toggle de tema (claro/oscuro), colapso de sidebar y reloj en tiempo real
   ===================================================================== */

(function () {
    'use strict';

    /* ── 0. Navegacion que CAMBIA datos: POST con token CSRF ──────────
       Varias acciones (confirmar/cancelar una cita, aceptar una fecha
       propuesta) se hacian con un enlace GET. Un GET no lleva token, y
       SameSite=Lax SI manda la cookie en una navegacion de primer nivel:
       bastaba un enlace en otra web para cancelar la cita de quien lo
       abriera. Ademas cualquier prefetch del navegador podia dispararlas.
       Esto envia un formulario POST con el token, igual que ya hacia el
       boton de restablecer contrasena, y deja que el navegador siga la
       redireccion del endpoint como hasta ahora. */
    window.ecoPost = function (url, campos) {
        const f = document.createElement('form');
        f.method = 'post';
        f.action = url;
        const datos = Object.assign({ csrf_token: window.ECO_CSRF || '' }, campos || {});
        Object.keys(datos).forEach(function (k) {
            const i = document.createElement('input');
            i.type = 'hidden';
            i.name = k;
            i.value = datos[k];          // .value no interpreta HTML: nada que escapar
            f.appendChild(i);
        });
        document.body.appendChild(f);
        f.submit();
    };

    /* ── 0b. Acceso excepcional a un paciente fuera del ámbito ────────
       El servidor responde 403 con {requiere_confirmacion:true} en vez de un
       error sin salida. Aquí se pide el motivo, se registra en la bitácora y
       se reintenta lo que el usuario estaba haciendo.

       Uso:  ecoAccesoExcepcional(pacienteId, nombre, function () { ...reintento... });
       Y para envolver una respuesta ya recibida:
             if (ecoSiRequiereConfirmacion(datos, nombre, reintento)) return; */
    window.ecoAccesoExcepcional = function (pacienteId, nombre, alConceder) {
        const modal  = document.getElementById('eco-modal-acceso-excepcional');
        const motivo = document.getElementById('eco-acx-motivo');
        const error  = document.getElementById('eco-acx-error');
        const btn    = document.getElementById('eco-acx-confirmar');
        const quien  = document.getElementById('eco-acx-paciente');
        if (!modal || !motivo || !btn || !window.EcoModal) {
            alert('Este paciente no está bajo tu atención.');
            return;
        }

        motivo.value = '';
        if (error) { error.hidden = true; error.textContent = ''; }
        if (quien) quien.textContent = nombre || 'Este paciente';
        btn.disabled = false;

        btn.onclick = function () {
            const texto = motivo.value.trim();
            if (texto.length < 10) {
                if (error) { error.textContent = 'Explica el motivo (mínimo 10 caracteres).'; error.hidden = false; }
                motivo.focus();
                return;
            }
            btn.disabled = true;
            const datos = new FormData();
            datos.append('paciente_id', pacienteId);
            datos.append('motivo', texto);
            // El envoltorio de fetch de shell.php ya adjunta el token CSRF.
            fetch((window.ECO_BASE || '') + 'api/acceso_excepcional.php', { method: 'POST', body: datos })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    btn.disabled = false;
                    if (!d || !d.success) {
                        if (error) { error.textContent = (d && d.message) || 'No se pudo registrar el acceso.'; error.hidden = false; }
                        return;
                    }
                    EcoModal.close('eco-modal-acceso-excepcional');
                    if (typeof alConceder === 'function') alConceder();
                })
                .catch(function () {
                    btn.disabled = false;
                    if (error) { error.textContent = 'No se pudo contactar con el servidor.'; error.hidden = false; }
                });
        };

        EcoModal.open('eco-modal-acceso-excepcional');
        setTimeout(function () { motivo.focus(); }, 60);
    };

    /** true si la respuesta pedía justificación (y ya se abrió el diálogo). */
    window.ecoSiRequiereConfirmacion = function (datos, nombre, reintento) {
        if (!datos || !datos.requiere_confirmacion) return false;
        window.ecoAccesoExcepcional(datos.paciente_id, nombre, reintento);
        return true;
    };

    /* ── 1. Aplicar tema guardado lo antes posible (evita "flash") ── */
    const THEME_KEY = 'eco_theme';
    const SIDEBAR_KEY = 'eco_sidebar';

    function applyTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        const btn = document.getElementById('btn-toggle-theme');
        if (btn) {
            const icon = btn.querySelector('i');
            if (icon) icon.className = theme === 'dark' ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
            btn.setAttribute('title', theme === 'dark' ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro');
        }
    }

    const savedTheme = localStorage.getItem(THEME_KEY) || 'light';
    applyTheme(savedTheme);

    /* ── 2. Inicialización tras DOM listo ── */
    document.addEventListener('DOMContentLoaded', function () {
        const sidebar  = document.querySelector('.app-sidebar');
        const backdrop = document.querySelector('.sidebar-backdrop');

        /* — Estado del sidebar —
           Se guarda en cookie para que el servidor pueda pintar el menú ya
           plegado; localStorage se mantiene solo para migrar a quien venga con
           el estado antiguo. */
        function guardarEstadoSidebar(valor) {
            localStorage.setItem(SIDEBAR_KEY, valor);
            document.cookie = SIDEBAR_KEY + '=' + valor
                + '; path=' + (window.ECO_BASE || '/')
                + '; max-age=31536000; SameSite=Lax';
        }

        function cookieSidebar() {
            const m = document.cookie.match(/(?:^|;\s*)eco_sidebar=([^;]+)/);
            return m ? m[1] : null;
        }

        if (window.innerWidth > 900 && sidebar) {
            const enCookie = cookieSidebar();
            if (enCookie === null) {
                // Primera vez con cookie: se hereda lo que hubiera guardado.
                const previo = localStorage.getItem(SIDEBAR_KEY) === 'collapsed' ? 'collapsed' : 'expanded';
                if (previo === 'collapsed') sidebar.classList.add('is-collapsed');
                guardarEstadoSidebar(previo);
            }
            // Si ya hay cookie, el servidor pintó la clase: no hay nada que tocar.
        }

        /* — Toggle del sidebar — */
        const btnToggleSidebar = document.getElementById('btn-toggle-sidebar');
        let temporizadorAnim = null;

        /* El ancho cambia de golpe (una sola maquetación); lo que se anima es
           el contenido del menú, con opacidad y transform. */
        function asentarSidebar() {
            clearTimeout(temporizadorAnim);
            sidebar.classList.remove('is-animando');
            void sidebar.offsetWidth;            // reinicia la animación si se pulsa seguido
            sidebar.classList.add('is-animando');
            temporizadorAnim = setTimeout(function () {
                sidebar.classList.remove('is-animando');
            }, 220);
        }

        if (btnToggleSidebar && sidebar) {
            btnToggleSidebar.addEventListener('click', function () {
                if (window.innerWidth <= 900) {
                    sidebar.classList.toggle('is-open');
                    if (backdrop) backdrop.classList.toggle('is-open');
                } else {
                    sidebar.classList.toggle('is-collapsed');
                    asentarSidebar();
                    guardarEstadoSidebar(
                        sidebar.classList.contains('is-collapsed') ? 'collapsed' : 'expanded'
                    );
                }
            });
        }
        if (backdrop && sidebar) {
            backdrop.addEventListener('click', function () {
                sidebar.classList.remove('is-open');
                backdrop.classList.remove('is-open');
            });
        }

        /* — Toggle del tema — */
        const btnToggleTheme = document.getElementById('btn-toggle-theme');
        applyTheme(localStorage.getItem(THEME_KEY) || 'light');
        if (btnToggleTheme) {
            btnToggleTheme.addEventListener('click', function () {
                const current = document.documentElement.getAttribute('data-theme') || 'light';
                const next    = current === 'light' ? 'dark' : 'light';
                applyTheme(next);
                localStorage.setItem(THEME_KEY, next);
            });
        }

        /* — Reloj en tiempo real — */
        const clockEl = document.getElementById('topbar-clock-time');
        const dateEl  = document.getElementById('topbar-clock-date');
        if (clockEl || dateEl) {
            const dias = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
            const meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun',
                           'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
            function tick() {
                const now = new Date();
                const hh = String(now.getHours()).padStart(2, '0');
                const mm = String(now.getMinutes()).padStart(2, '0');
                if (clockEl) clockEl.textContent = `${hh}:${mm}`;
                if (dateEl)  dateEl.textContent  =
                    `${dias[now.getDay()]} ${String(now.getDate()).padStart(2,'0')} ${meses[now.getMonth()]}`;
            }
            tick();
            setInterval(tick, 30000);
        }
    });
})();
