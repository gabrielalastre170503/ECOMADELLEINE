/* Gestión de usuarios — administrador */
(function () {
    'use strict';

    var filtroActual = window.VU_FILTRO || 'aprobados';
    var debounceTimer = null;

    function $(id) {
        return document.getElementById(id);
    }

    function updateTotal(container) {
        var countEl = $('vu-users-count');
        if (!countEl || !container) return;
        var wrap = container.querySelector('[data-vu-total]');
        var total = wrap ? wrap.getAttribute('data-vu-total') : null;
        if (total !== null) {
            countEl.textContent = total;
        }
    }

    window.buscarUsuarios = function (query) {
        var container = $('tabla-usuarios-container');
        if (!container) return;

        container.innerHTML = '<p class="vu-users-empty"><i class="fa-solid fa-spinner fa-spin"></i> Cargando…</p>';

        var fd = new FormData();
        fd.append('query', query || '');
        fd.append('filtro', filtroActual);

        fetch((window.ECO_BASE || '') + 'api/buscar_usuarios_filtro.php', { method: 'POST', body: fd })
            .then(function (r) { return r.text(); })
            .then(function (html) {
                container.innerHTML = html || '<p class="vu-users-empty" data-vu-total="0"><i class="fa-solid fa-users-slash"></i>Sin resultados.</p>';
                updateTotal(container);
                if (window.EcoTableSort) {
                    EcoTableSort.reset();
                    EcoTableSort.init(container);
                }
            })
            .catch(function () {
                container.innerHTML = '<p class="vu-users-empty" data-vu-total="0">Error al cargar el listado.</p>';
                updateTotal(container);
            });
    };

    window.toggleUserState = function (userId, newState) {
        var msg = newState === 'inhabilitado'
            ? '¿Inhabilitar el acceso de este usuario?'
            : (newState === 'aprobado'
                ? '¿Aprobar / habilitar el acceso de este usuario?'
                : '¿Confirmar el cambio de estado?');

        if (!confirm(msg)) return;

        var fd = new FormData();
        fd.append('id', userId);
        fd.append('nuevo_estado', newState);

        fetch((window.ECO_BASE || '') + 'api/cambiar_estado_usuario.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    var q = $('buscador-usuarios');
                    buscarUsuarios(q ? q.value : '');
                } else {
                    alert(data.message || 'No se pudo actualizar el estado.');
                }
            })
            .catch(function () {
                alert('Error de red. Intente de nuevo.');
            });
    };

    function showTempPassModal(pass) {
        var display = $('vu-temp-pass-display');
        if (display) display.textContent = pass;
        if (window.EcoModal) {
            EcoModal.open('eco-modal-vu-temp-pass');
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        var buscador = $('buscador-usuarios');
        var container = $('tabla-usuarios-container');

        /* La contraseña temporal la entrega el servidor en el propio modal
           (viene de la sesion). Antes se leia de la URL, donde quedaba en el
           historial y en los logs; por eso ya no hace falta limpiarla. */
        var modalTP = $('eco-modal-vu-temp-pass');
        var tempPass = modalTP ? modalTP.getAttribute('data-temp-pass') : '';
        if (tempPass) {
            modalTP.removeAttribute('data-temp-pass');
            showTempPassModal(tempPass);
        }

        buscarUsuarios('');

        if (buscador) {
            buscador.addEventListener('input', function () {
                clearTimeout(debounceTimer);
                var q = this.value;
                debounceTimer = setTimeout(function () {
                    buscarUsuarios(q);
                }, 280);
            });
        }

        if (container && window.EcoTableSort) {
            EcoTableSort.init(container);
        }
    });
})();
