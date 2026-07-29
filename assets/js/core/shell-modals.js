/* =====================================================================
   ECOMADELLEINE — API mínima para modales (.eco-modal)
   Uso: EcoModal.open('id'), EcoModal.close('id'), data-eco-modal-close
   ===================================================================== */
(function () {
    'use strict';

    var stack = [];

    /* Navegación entre modales: los flujos encadenados hacen close(A)+open(B) en
       el mismo tick. Con las transiciones normales los dos fondos se cruzan
       durante 180 ms — el velo se aclara y vuelve a oscurecerse, y el diálogo
       saliente se ve translúcido sobre el entrante. Se percibe como un parpadeo
       en cada salto. Cuando detectamos ese patrón el cambio de fondo es
       instantáneo y solo anima el diálogo nuevo. */
    var ULTIMO_CIERRE_MS = 0;
    var VENTANA_SALTO_MS = 60;
    var elementoSaliente = null;

    // Debe superar la animación de entrada del diálogo (0.09s al saltar): si se
    // quitara antes, la duración cambiaría a mitad de la animación y se vería
    // un tirón. Se limpia el temporizador anterior por si hay saltos seguidos.
    var MS_MARCA_SALTO = 180;

    function marcarInstantaneo(el) {
        if (!el) return;
        el.classList.add('eco-modal--instant');
        if (el._tempSalto) clearTimeout(el._tempSalto);
        el._tempSalto = setTimeout(function () {
            el.classList.remove('eco-modal--instant');
            el._tempSalto = null;
        }, MS_MARCA_SALTO);
    }

    function getEl(id) {
        return typeof id === 'string' ? document.getElementById(id) : id;
    }

    function lockScroll(on) {
        if (on) {
            document.body.classList.add('eco-modal-open');
        } else {
            if (!document.querySelector('.eco-modal.eco-modal--open')) {
                document.body.classList.remove('eco-modal-open');
            }
        }
    }

    window.EcoModal = {
        open: function (id) {
            var el = getEl(id);
            if (!el || !el.classList.contains('eco-modal')) return;

            // ¿Venimos de cerrar otro modal en este mismo tick? Entonces es una
            // navegación, no una apertura desde cero: sin cruce de fondos.
            var esSalto = (performance.now() - ULTIMO_CIERRE_MS) < VENTANA_SALTO_MS;
            if (esSalto) {
                marcarInstantaneo(el);
                if (elementoSaliente && elementoSaliente !== el) {
                    marcarInstantaneo(elementoSaliente);
                }
            }
            elementoSaliente = null;

            el.classList.add('eco-modal--open');
            /* Refuerzo reflow: sin display:none los keyframes del hijo se aplican de forma fiable */
            void el.offsetHeight;
            el.setAttribute('aria-hidden', 'false');
            el.setAttribute('aria-modal', 'true');
            stack.push(id);
            lockScroll(true);
            var first = el.querySelector('input:not([type="hidden"]), select, textarea, button');
            if (first && typeof first.focus === 'function') {
                setTimeout(function () { try { first.focus(); } catch (e) {} }, 50);
            }
        },

        close: function (id) {
            var el = getEl(id);
            if (!el) return;
            if (el.classList.contains('eco-modal--open')) {
                // Se anota para que un open() inmediato pueda cortarle el
                // desvanecido y evitar el cruce de fondos.
                ULTIMO_CIERRE_MS = performance.now();
                elementoSaliente = el;
            }
            el.classList.remove('eco-modal--open');
            el.setAttribute('aria-hidden', 'true');
            el.removeAttribute('aria-modal');
            stack = stack.filter(function (x) { return x !== (typeof id === 'string' ? id : el.id); });
            lockScroll(false);
        },

        closeTop: function () {
            if (stack.length === 0) return;
            var top = stack[stack.length - 1];
            EcoModal.close(top);
        }
    };

    document.addEventListener('DOMContentLoaded', function () {
        document.addEventListener('click', function (e) {
            var t = e.target;
            if (!t || !t.closest) return;
            var btn = t.closest('[data-eco-modal-close]');
            if (btn) {
                var modal = btn.closest('.eco-modal');
                if (modal && modal.id) {
                    e.preventDefault();
                    EcoModal.close(modal.id);
                }
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            var open = document.querySelectorAll('.eco-modal.eco-modal--open');
            if (open.length === 0) return;
            var last = open[open.length - 1];
            // Modales con data-eco-modal-static no se cierran con ESC ni backdrop
            if (last.hasAttribute('data-eco-modal-static')) return;
            if (last.id) EcoModal.close(last.id);
        });

        // El clic fuera del modal (backdrop) NO cierra: el usuario debe pulsar la X.
    });
})();
