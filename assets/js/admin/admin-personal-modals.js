/* Añadir personal — abrir modales desde tarjetas (sin cambiar su apariencia) */
(function () {
    'use strict';

    var MODALS = {
        ecografista: 'eco-modal-staff-ecografista',
        recepcionista: 'eco-modal-staff-recepcionista',
        paciente: 'eco-modal-staff-paciente'
    };

    var FNAC = {
        ecografista: 'staff-eco-fnac',
        recepcionista: 'staff-rx-fnac',
        paciente: 'staff-pat-fnac'
    };

    var ERR = {
        ecografista: 'staff-eco-error',
        recepcionista: 'staff-rx-error',
        paciente: 'staff-pat-error'
    };

    var FOCUS = {
        ecografista: 'staff-eco-nombre',
        recepcionista: 'staff-rx-nombre',
        paciente: 'staff-pat-nombre'
    };

    function $(id) {
        return document.getElementById(id);
    }

    function showErr(id, msg) {
        var el = $(id);
        if (!el) return;
        if (msg) {
            el.textContent = msg;
            el.hidden = false;
        } else {
            el.textContent = '';
            el.hidden = true;
        }
    }

    function destroyFp(inp) {
        if (inp && inp._flatpickr) inp._flatpickr.destroy();
    }

    function initFp(tipo) {
        var inp = $(FNAC[tipo]);
        if (!inp || typeof flatpickr === 'undefined') return;
        destroyFp(inp);
        var loc = flatpickr.l10ns && flatpickr.l10ns.es ? flatpickr.l10ns.es : undefined;
        flatpickr(inp, {
            locale: loc,
            dateFormat: 'Y-m-d',
            maxDate: 'today',
            altInput: true,
            altFormat: 'd/m/Y'
        });
    }

    function openModal(tipo) {
        var mid = MODALS[tipo];
        if (!mid || !window.EcoModal) return;

        var formId = tipo === 'paciente' ? 'staff-form-paciente' : 'staff-form-' + tipo;
        var form = $(formId);
        showErr(ERR[tipo], '');
        if (form) form.reset();
        destroyFp($(FNAC[tipo]));

        EcoModal.open(mid);
        setTimeout(function () {
            initFp(tipo);
            var f = $(FOCUS[tipo]);
            if (f) f.focus();
        }, 60);
    }

    var LISTA_CFG = {
        ecografista: {
            icon: 'fa-solid fa-user-doctor',
            iconClass: 'staff-lista-modal-head__icon--eco',
            sub: 'Profesionales con cuenta aprobada en la clínica',
            filtro: 'ver_usuarios.php?filtro=doctores'
        },
        recepcionista: {
            icon: 'fa-solid fa-user-tie',
            iconClass: 'staff-lista-modal-head__icon--rx',
            sub: 'Personal de recepción con cuenta aprobada',
            filtro: 'ver_usuarios.php?filtro=personal'
        }
    };

    var listaTipoActual = null;
    var listaTimer = null;

    function cargarListaStaff(tipo, q) {
        var body = $('staff-lista-body');
        if (!body) return;
        body.innerHTML = '<p class="staff-lista-empty"><i class="fa-solid fa-spinner fa-spin"></i> Cargando…</p>';

        var fd = new FormData();
        fd.append('tipo', tipo);
        fd.append('query', q || '');

        fetch((window.ECO_BASE || '') + 'api/listar_personal_staff.php', { method: 'POST', body: fd })
            .then(function (r) { return r.text(); })
            .then(function (html) {
                body.innerHTML = html || '<p class="staff-lista-empty">Sin resultados.</p>';
            })
            .catch(function () {
                body.innerHTML = '<p class="staff-lista-empty" style="color:#b91c1c;">No se pudo cargar el listado.</p>';
            });
    }

    var PERFIL_MODAL = 'eco-modal-staff-perfil';

    function setPerfilView(showContent) {
        var loading = $('staff-perfil-loading');
        var content = $('staff-perfil-content');
        var err = $('staff-perfil-error');
        var actions = $('staff-perfil-actions');
        if (loading) loading.hidden = !!showContent;
        if (content) content.hidden = !showContent;
        if (actions && !showContent) actions.hidden = true;
        if (err) {
            err.hidden = true;
            err.textContent = '';
        }
    }

    function estadoBadgeClass(estado) {
        if (estado === 'aprobado') return 'staff-perfil-estado--ok';
        if (estado === 'pendiente') return 'staff-perfil-estado--pending';
        if (estado === 'inhabilitado') return 'staff-perfil-estado--off';
        return '';
    }

    function renderPerfil(p) {
        var avatar = $('staff-perfil-avatar');
        if (avatar) {
            avatar.textContent = p.iniciales || '?';
            avatar.className = 'staff-perfil-aside__avatar ' + (p.avatar_class || 'staff-perfil-avatar--default');
        }
        var asideName = $('staff-perfil-aside-name');
        var asideRole = $('staff-perfil-aside-role');
        var asideCedula = $('staff-perfil-aside-cedula');
        var title = $('staff-perfil-title');
        var correo = $('staff-perfil-correo');
        var estado = $('staff-perfil-estado');
        var fnac = $('staff-perfil-fnac');
        var rowFnac = $('staff-perfil-row-fnac');
        var registro = $('staff-perfil-registro');
        var actions = $('staff-perfil-actions');

        if (asideName) asideName.textContent = p.nombre || '—';
        if (asideRole) asideRole.textContent = p.rol_label || '—';
        if (asideCedula) asideCedula.textContent = p.cedula || '—';
        if (title) title.textContent = 'Perfil · ' + (p.nombre || 'Usuario');
        if (correo) correo.textContent = p.correo || '—';
        if (estado) {
            estado.textContent = p.estado_label || '—';
            estado.className = 'staff-perfil-estado ' + estadoBadgeClass(p.estado);
        }
        if (fnac && rowFnac) {
            var fnacTxt = p.fecha_nacimiento || '';
            if (fnacTxt && p.edad != null && p.edad > 0) {
                fnacTxt += ' · ' + p.edad + ' años';
            }
            rowFnac.hidden = !fnacTxt;
            fnac.textContent = fnacTxt || '—';
        }
        if (registro) registro.textContent = p.fecha_registro || '—';

        var hint = $('staff-perfil-self-hint');
        var btnReset = $('staff-perfil-btn-reset');
        var btnEstado = $('staff-perfil-btn-estado');

        if (p.puede_acciones) {
            if (actions) actions.hidden = false;
            if (hint) hint.hidden = true;
            if (btnReset) {
                /* reset_password.php exige POST + token CSRF (así se cerró el
                   hueco de CSRF por enlace GET). Un href suelto lo rebotaba a
                   ?error=parametros_invalidos, así que se envía un formulario. */
                btnReset.setAttribute('href', '#'); // sigue siendo enlace: mantiene estilo y foco
                btnReset.onclick = function (ev) {
                    ev.preventDefault();
                    if (!confirm('¿Restablecer la contraseña de este usuario?')) return false;
                    var f = document.createElement('form');
                    f.method = 'post';
                    f.action = (window.ECO_BASE || '') + 'api/reset_password.php';
                    f.innerHTML = '<input type="hidden" name="id" value="' + parseInt(p.id, 10) + '">'
                        + '<input type="hidden" name="csrf_token" value="' + (window.ECO_CSRF || '') + '">';
                    document.body.appendChild(f);
                    f.submit();
                    return false;
                };
            }
            if (btnEstado) {
                var esInhab = p.estado === 'inhabilitado';
                btnEstado.dataset.userId = String(p.id);
                btnEstado.dataset.nuevoEstado = esInhab ? 'aprobado' : 'inhabilitado';
                btnEstado.classList.remove('staff-perfil-btn--disable', 'staff-perfil-btn--enable');
                btnEstado.classList.add(esInhab ? 'staff-perfil-btn--enable' : 'staff-perfil-btn--disable');
                btnEstado.innerHTML = esInhab
                    ? '<i class="fa-solid fa-user-check" aria-hidden="true"></i><span>Habilitar acceso</span>'
                    : '<i class="fa-solid fa-user-slash" aria-hidden="true"></i><span>Inhabilitar acceso</span>';
            }
        } else {
            if (actions) actions.hidden = true;
            if (hint) hint.hidden = false;
        }

        setPerfilView(true);
    }

    function showPerfilError(msg) {
        var loading = $('staff-perfil-loading');
        var content = $('staff-perfil-content');
        var err = $('staff-perfil-error');
        if (loading) loading.hidden = true;
        if (content) content.hidden = true;
        if (err) {
            err.textContent = msg;
            err.hidden = false;
        }
    }

    window.staffAbrirPerfilModal = function (id) {
        if (!id || !window.EcoModal) return;

        setPerfilView(false);
        EcoModal.open(PERFIL_MODAL);

        fetch((window.ECO_BASE || '') + 'api/get_perfil_personal_ajax.php?id=' + encodeURIComponent(id))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success && data.perfil) {
                    renderPerfil(data.perfil);
                } else {
                    showPerfilError(data.message || 'No se pudo cargar el perfil.');
                }
            })
            .catch(function () {
                showPerfilError('Error de red. Intente de nuevo.');
            });
    };

    window.staffAbrirListaModal = function (tipo, count, title) {
        if (!window.EcoModal) return;
        var cfg = LISTA_CFG[tipo];
        if (!cfg) return;

        listaTipoActual = tipo;

        var titleEl = $('staff-lista-title');
        var subEl = $('staff-lista-sub');
        var iconWrap = $('staff-lista-icon');
        var countEl = $('staff-lista-count');
        var verTodo = $('staff-lista-ver-todo');
        var queryInp = $('staff-lista-query');

        if (titleEl) titleEl.textContent = title || (tipo === 'recepcionista' ? 'Recepcionistas activas' : 'Ecografistas activos');
        if (subEl) subEl.textContent = cfg.sub;
        if (iconWrap) {
            iconWrap.className = 'staff-lista-modal-head__icon ' + (cfg.iconClass || '');
            iconWrap.innerHTML = '<i class="' + cfg.icon + '"></i>';
        }
        if (countEl) countEl.textContent = typeof count === 'number' || typeof count === 'string' ? String(count) : '—';
        if (verTodo) verTodo.href = cfg.filtro;
        if (queryInp) queryInp.value = '';

        EcoModal.open('eco-modal-staff-lista');
        cargarListaStaff(tipo, '');
        if (queryInp) setTimeout(function () { queryInp.focus(); }, 120);
    };

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.staff-section-header[data-staff-lista]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var tipo = btn.getAttribute('data-staff-lista');
                var count = parseInt(btn.getAttribute('data-staff-lista-count'), 10);
                var title = btn.getAttribute('data-staff-lista-title') || '';
                if (tipo) staffAbrirListaModal(tipo, isNaN(count) ? 0 : count, title);
            });
        });

        var listaQuery = $('staff-lista-query');
        if (listaQuery) {
            listaQuery.addEventListener('input', function () {
                if (!listaTipoActual) return;
                var q = this.value;
                clearTimeout(listaTimer);
                listaTimer = setTimeout(function () {
                    cargarListaStaff(listaTipoActual, q);
                }, 280);
            });
        }

        var btnEstadoPerfil = $('staff-perfil-btn-estado');
        if (btnEstadoPerfil) {
            btnEstadoPerfil.addEventListener('click', function () {
                var userId = btnEstadoPerfil.dataset.userId;
                var nuevoEstado = btnEstadoPerfil.dataset.nuevoEstado;
                if (!userId || !nuevoEstado) return;

                var msg = nuevoEstado === 'inhabilitado'
                    ? '¿Inhabilitar el acceso de este usuario? No podrá iniciar sesión.'
                    : '¿Habilitar de nuevo el acceso de este usuario?';

                if (!confirm(msg)) return;

                var fd = new FormData();
                fd.append('id', userId);
                fd.append('nuevo_estado', nuevoEstado);

                btnEstadoPerfil.disabled = true;
                fetch((window.ECO_BASE || '') + 'api/cambiar_estado_usuario.php', { method: 'POST', body: fd })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        btnEstadoPerfil.disabled = false;
                        if (data.success) {
                            if (window.EcoModal) EcoModal.close(PERFIL_MODAL);
                            window.location.reload();
                        } else {
                            alert(data.message || 'No se pudo actualizar el estado.');
                        }
                    })
                    .catch(function () {
                        btnEstadoPerfil.disabled = false;
                        alert('Error de red. Intente de nuevo.');
                    });
            });
        }

        document.addEventListener('click', function (e) {
            var trigger = e.target.closest('[data-staff-perfil-id]');
            if (!trigger) return;
            e.preventDefault();
            var pid = parseInt(trigger.getAttribute('data-staff-perfil-id'), 10);
            if (!pid) return;
            if (trigger.closest('#eco-modal-staff-lista') && window.EcoModal) {
                EcoModal.close('eco-modal-staff-lista');
            }
            staffAbrirPerfilModal(pid);
        });

        document.querySelectorAll('a.staff-register-card[data-staff-modal]').forEach(function (link) {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                var tipo = link.getAttribute('data-staff-modal');
                if (tipo) openModal(tipo);
            });
        });

        document.querySelectorAll('.staff-register-form').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                var endpoint = form.getAttribute('data-endpoint');
                var wrap = form.closest('.eco-modal');
                var modalId = wrap ? wrap.id : '';
                var tipo = 'paciente';
                if (modalId.indexOf('ecografista') !== -1) tipo = 'ecografista';
                else if (modalId.indexOf('recepcionista') !== -1) tipo = 'recepcionista';

                var errId = ERR[tipo];
                showErr(errId, '');

                var p1 = form.querySelector('[name="contrasena"]');
                var p2 = form.querySelector('[name="confirmar_contrasena"]');
                if (p1 && p2 && p1.value !== p2.value) {
                    showErr(errId, 'Las contraseñas no coinciden.');
                    return;
                }

                var btn = form.querySelector('[type="submit"]');
                var btnHtml = btn ? btn.innerHTML : '';
                if (btn) {
                    btn.disabled = true;
                    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Guardando…';
                }

                fetch(endpoint, { method: 'POST', body: new FormData(form) })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (btn) {
                            btn.disabled = false;
                            btn.innerHTML = btnHtml;
                        }
                        if (data.success) {
                            if (modalId) EcoModal.close(modalId);
                            var q = new URLSearchParams();
                            q.set('registro', 'ok');
                            if (data.nombre) q.set('nombre', data.nombre);
                            window.location.href = (window.ECO_BASE || '') + 'personal?' + q.toString();
                        } else {
                            showErr(errId, data.message || 'No se pudo registrar.');
                        }
                    })
                    .catch(function () {
                        if (btn) {
                            btn.disabled = false;
                            btn.innerHTML = btnHtml;
                        }
                        showErr(errId, 'Error de red. Intente de nuevo.');
                    });
            });
        });
    });
})();
