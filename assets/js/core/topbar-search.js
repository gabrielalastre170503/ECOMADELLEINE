/* =====================================================================
   ECOMADELLEINE — BUSCADOR DE LA BARRA SUPERIOR
   El campo existía pero no hacía nada. Consulta api/buscar_global.php, que
   decide qué puede ver cada rol y a dónde lleva cada resultado.
   ===================================================================== */
(function () {
    'use strict';

    var MIN = 2;          // por debajo de 2 caracteres cualquier búsqueda sobra
    var ESPERA = 220;     // ms sin teclear antes de consultar

    document.addEventListener('DOMContentLoaded', function () {
        /* Aplazado a la siguiente vuelta: algunas páginas registran su filtro
           dentro de su propio DOMContentLoaded, que corre DESPUÉS de este. */
        setTimeout(prefiltrarDesdeUrl, 0);

        var caja  = document.getElementById('eco-buscador');
        var input = document.getElementById('eco-buscador-input');
        var panel = document.getElementById('eco-buscador-panel');
        if (!caja || !input || !panel) return;

        var temporizador = null;
        var peticion = 0;
        var opciones = [];      // nodos <a> en el orden en que se ven
        var activo = -1;

        function esc(s) {
            return String(s == null ? '' : s)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function cerrar() {
            panel.hidden = true;
            panel.innerHTML = '';
            input.setAttribute('aria-expanded', 'false');
            opciones = [];
            activo = -1;
        }

        function abrir(html) {
            panel.innerHTML = html;
            panel.hidden = false;
            input.setAttribute('aria-expanded', 'true');
            opciones = Array.prototype.slice.call(panel.querySelectorAll('.tbs-item'));
            activo = -1;
        }

        function mensaje(texto, cargando) {
            abrir('<p class="tbs-estado">' +
                (cargando ? '<i class="fa-solid fa-spinner fa-spin"></i> ' : '') +
                esc(texto) + '</p>');
            opciones = [];
        }

        function marcar(i) {
            if (!opciones.length) return;
            if (activo >= 0 && opciones[activo]) {
                opciones[activo].classList.remove('is-activo');
                opciones[activo].removeAttribute('aria-selected');
            }
            activo = (i + opciones.length) % opciones.length;
            var el = opciones[activo];
            el.classList.add('is-activo');
            el.setAttribute('aria-selected', 'true');
            if (el.scrollIntoView) el.scrollIntoView({ block: 'nearest' });
        }

        function pintar(data, termino) {
            if (!data || !data.ok) { mensaje('No se pudo buscar.'); return; }
            if (!data.total) {
                // Sin esc() aquí a propósito: mensaje() escapa el texto entero.
                mensaje('Sin resultados para «' + termino + '».');
                return;
            }
            var html = '';
            data.grupos.forEach(function (g) {
                html += '<p class="tbs-grupo">' + esc(g.titulo) + '</p>';
                g.items.forEach(function (it) {
                    html += '<a class="tbs-item" role="option" href="' + esc(it.url) + '">' +
                        '<span class="tbs-item__ico"><i class="' + esc(it.icono) + '"></i></span>' +
                        '<span class="tbs-item__txt">' +
                            '<strong>' + esc(it.titulo) + '</strong>' +
                            '<small>' + esc(it.sub) + '</small>' +
                        '</span></a>';
                });
            });
            abrir(html);
        }

        function buscar() {
            var q = input.value.trim();
            if (q.length < MIN) { cerrar(); return; }

            var mio = ++peticion;
            mensaje('Buscando…', true);

            fetch((window.ECO_BASE || '') + 'api/buscar_global.php?q=' + encodeURIComponent(q), {
                credentials: 'same-origin'
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    // Una respuesta vieja no debe pisar a una más nueva.
                    if (mio !== peticion) return;
                    pintar(data, q);
                })
                .catch(function () {
                    if (mio !== peticion) return;
                    mensaje('Error de red.');
                });
        }

        input.addEventListener('input', function () {
            clearTimeout(temporizador);
            if (input.value.trim().length < MIN) { cerrar(); return; }
            temporizador = setTimeout(buscar, ESPERA);
        });

        input.addEventListener('focus', function () {
            if (input.value.trim().length >= MIN && panel.hidden) buscar();
        });

        input.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { cerrar(); input.blur(); return; }
            if (panel.hidden) {
                if (e.key === 'Enter' && input.value.trim().length >= MIN) {
                    e.preventDefault();
                    clearTimeout(temporizador);
                    buscar();
                }
                return;
            }
            if (e.key === 'ArrowDown') { e.preventDefault(); marcar(activo + 1); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); marcar(activo - 1); }
            else if (e.key === 'Enter') {
                // Sin nada resaltado, Enter abre el primer resultado.
                var destino = opciones[activo >= 0 ? activo : 0];
                if (destino) { e.preventDefault(); window.location.href = destino.href; }
            }
        });

        document.addEventListener('click', function (e) {
            if (!caja.contains(e.target)) cerrar();
        });
    });

    /* Los resultados que no tienen página propia llegan con ?q=: se vuelca en
       el buscador de la página y se dispara su filtro, que ya existía. */
    function prefiltrarDesdeUrl() {
        var q = new URLSearchParams(window.location.search).get('q');
        if (!q) return;
        var campos = document.querySelectorAll('input[type="search"]');
        for (var i = 0; i < campos.length; i++) {
            if (campos[i].id === 'eco-buscador-input') continue;   // el de la barra no
            campos[i].value = q;
            campos[i].dispatchEvent(new Event('input', { bubbles: true }));
            campos[i].dispatchEvent(new Event('keyup', { bubbles: true }));
            return;
        }
    }
})();
