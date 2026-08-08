/* Recepción — Gestión pacientes: modales programar cita, informes, alta extendida */
(function () {
    'use strict';

    function esc(s) {
        if (s === null || typeof s === 'undefined') return '';
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function byId(id) {
        return document.getElementById(id);
    }

    function rxSetErr(idEl, msg) {
        var el = byId(idEl);
        if (!el) return;
        el.textContent = msg || '';
        el.style.display = msg ? 'block' : 'none';
    }

    function destroyFp(inp) {
        if (inp && inp._flatpickr) inp._flatpickr.destroy();
    }

    /** --- Programar cita --- */
    var rxProgFp = null;
    function initRxProgFp() {
        var fecha = byId('rx-prog-fecha');
        if (!fecha || typeof flatpickr === 'undefined') return;
        destroyFp(fecha);
        rxProgFp = null;
        fecha.value = '';
        rxProgFp = flatpickr(fecha, {
            enableTime: true,
            dateFormat: 'Y-m-d H:i',
            altInput: true,
            altFormat: 'd/m/Y h:i K',
            locale: flatpickr.l10ns && flatpickr.l10ns.es ? flatpickr.l10ns.es : 'es',
            minuteIncrement: 15
        });
    }

    /* Filtro del catalogo de estudios. Un estudio ya marcado no se oculta
       nunca: si desapareciera del filtro se guardaria una seleccion que no
       se ve. Las cabeceras de categoria se ocultan si se quedan sin filas. */
    function rxProgFiltrar() {
        var caja = byId('rx-prog-estudios');
        var q = byId('rx-prog-buscar');
        if (!caja || !q) return;

        var texto = q.value.trim().toLowerCase();
        var visibles = 0, total = 0;
        var cat = null, catVisibles = 0;

        Array.prototype.forEach.call(caja.children, function (el) {
            if (el.hasAttribute('data-rxp-cat')) {
                if (cat) cat.classList.toggle('pcx-oculto', catVisibles === 0);
                cat = el;
                catVisibles = 0;
                return;
            }
            var inp = el.querySelector('[data-rxp-estudio]');
            var coincide = texto === ''
                || (el.getAttribute('data-rxp-busca') || '').indexOf(texto) !== -1
                || (inp && inp.checked);
            el.classList.toggle('pcx-oculto', !coincide);
            total++;
            if (coincide) { visibles++; catVisibles++; }
        });
        if (cat) cat.classList.toggle('pcx-oculto', catVisibles === 0);

        var cuenta = byId('rx-prog-visibles');
        if (cuenta) cuenta.textContent = visibles + ' de ' + total;

        var aviso = byId('rx-prog-sin-resultados');
        if (aviso) aviso.hidden = visibles !== 0;
    }

    var _rxProgBuscar = byId('rx-prog-buscar');
    if (_rxProgBuscar) {
        _rxProgBuscar.addEventListener('input', rxProgFiltrar);
        /* Enter dentro del buscador enviaria el formulario. */
        _rxProgBuscar.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); }
            if (e.key === 'Escape') { _rxProgBuscar.value = ''; rxProgFiltrar(); }
        });
    }

    window.rxAbrirProgramarCita = function (pacienteId, pacienteNombre) {
        var modal = byId('eco-modal-rx-programar-cita');
        var pidIn = byId('rx-prog-paciente-id');
        var nameEl = byId('rx-prog-paciente-nombre');
        if (!modal || !pidIn || !nameEl || !window.EcoModal) return;

        rxSetErr('rx-prog-error', '');
        var form = byId('rx-form-programar-cita');
        if (form) form.reset();
        pidIn.value = pacienteId;
        nameEl.textContent = pacienteNombre || '—';
        rxProgAtencion.reiniciar();
        rxProgFiltrar();   // form.reset() vacia el buscador; hay que repintar la lista

        EcoModal.open('eco-modal-rx-programar-cita');
        setTimeout(initRxProgFp, 0);
    };

    /* --- Estudios, servicios y total ---
       El mismo bloque de campos aparece en "Programar cita" y en el alta
       extendida, así que se monta una vez por formulario en lugar de repetir
       el código. El total lo calcula el servidor: precios y promociones viven
       en lib/facturacion y no se duplican aquí. */
    function rxBloqueAtencion(cfg) {
        var montoManual = false;
        var montoEl = byId(cfg.monto);

        function refrescar() {
            var resumenEl = byId(cfg.resumen);
            var notaEl = byId(cfg.nota);

            var tipos = [], nombres = [], servicios = [];
            document.querySelectorAll(cfg.estudio + ':checked').forEach(function (c) {
                tipos.push(parseInt(c.value, 10));
                var txt = c.parentElement.querySelector('.mcp-opcion__nombre');
                if (txt) nombres.push(txt.textContent.trim());
            });
            document.querySelectorAll(cfg.servicio + ':checked').forEach(function (c) {
                servicios.push(c.value);
            });

            if (resumenEl) {
                resumenEl.textContent = nombres.length
                    ? nombres.length + (nombres.length === 1 ? ' ecografía: ' : ' ecografías: ') + nombres.join(', ')
                    : 'Ninguna seleccionada.';
            }

            if (!tipos.length && !servicios.length) {
                if (notaEl) notaEl.textContent = 'Se calcula solo al elegir estudios y servicios. Puedes cambiarlo.';
                if (montoEl && !montoManual) montoEl.value = '';
                return;
            }

            fetch((window.ECO_BASE || '') + 'api/calcular_total_servicios.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ tipos_ecografia: tipos, servicios: servicios })
            })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res || !res.success) return;
                if (montoEl && !montoManual) montoEl.value = res.total > 0 ? res.total.toFixed(2) : '';
                if (notaEl) {
                    notaEl.textContent = 'Sugerido: ' + res.total_texto
                        + (res.promos && res.promos.length ? ' · ' + res.promos.join(' · ') : '')
                        + (montoManual ? ' (monto modificado a mano)' : '');
                }
            })
            .catch(function () {
                if (notaEl) notaEl.textContent = 'No se pudo calcular el total. Escribe el monto a mano.';
            });
        }

        document.querySelectorAll(cfg.estudio + ', ' + cfg.servicio).forEach(function (c) {
            c.addEventListener('change', refrescar);
        });
        if (montoEl) {
            montoEl.addEventListener('input', function () { montoManual = true; });
        }

        return {
            /* Al reabrir el modal el formulario ya se reseteó: hay que olvidar
               también que el monto se había tocado a mano. */
            reiniciar: function () {
                montoManual = false;
                refrescar();
            }
        };
    }

    var rxProgAtencion = rxBloqueAtencion({
        estudio: '[data-rxp-estudio]',
        servicio: '[data-rxp-servicio]',
        resumen: 'rx-prog-estudios-resumen',
        nota: 'rx-prog-monto-sugerido',
        monto: 'rx-prog-monto'
    });

    var rxExtAtencion = rxBloqueAtencion({
        estudio: '[data-rxe-estudio]',
        servicio: '[data-rxe-servicio]',
        resumen: 'rx-ext-estudios-resumen',
        nota: 'rx-ext-monto-sugerido',
        monto: 'rx-ext-monto'
    });

    /** --- Informes --- */
    window.rxAbrirInformesPaciente = function (pacienteId, pacienteNombre) {
        var modal = byId('eco-modal-rx-informes-paciente');
        var sub = byId('rx-inf-sub');
        var body = byId('rx-inf-body');
        if (!modal || !body || !window.EcoModal) return;

        // Se recuerdan para que "Volver" pueda reabrir esta misma lista.
        rxInfDetPacienteId = pacienteId;
        rxInfDetPacienteNom = pacienteNombre || '';

        if (sub) {
            sub.textContent = (pacienteNombre || '') + ' · Cargando…';
        }
        body.innerHTML = '<p class="eco-modal__body-text" style="margin:16px 0;"><i class="fa-solid fa-spinner fa-spin"></i> Cargando informes…</p>';
        EcoModal.open('eco-modal-rx-informes-paciente');

        fetch((window.ECO_BASE || '') + 'api/get_informes_paciente.php?paciente_id=' + encodeURIComponent(pacienteId))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.error) {
                    if (sub) sub.textContent = esc(pacienteNombre || '');
                    body.innerHTML = '<p class="eco-modal__body-text" style="color:var(--danger);">' + esc(data.error) + '</p>';
                    return;
                }
                var pn = esc(data.paciente_nombre || pacienteNombre || '');
                var ci = esc(data.paciente_cedula || '');
                var tot = typeof data.total === 'number' ? data.total : 0;
                if (sub) sub.textContent = pn + (ci ? ' · CI ' + ci : '') + ' · ' + tot + ' informe(s)';

                var list = data.informes || [];
                if (!list.length) {
                    body.innerHTML = '<div style="text-align:center;padding:32px;color:var(--text-muted);font-size:13px;"><i class="fa-regular fa-folder-open" style="font-size:2.2rem;display:block;margin-bottom:10px;opacity:.45;"></i>No hay estudios registrados para este paciente.</div>';
                    return;
                }

                var html = '<div style="display:flex;flex-direction:column;gap:10px;max-height:min(62vh,520px);overflow-y:auto;padding-right:4px;">';
                list.forEach(function (inf) {
                    html += '<div style="border:1px solid var(--border-soft);border-radius:var(--radius);padding:12px 14px;background:var(--bg-muted);">';
                    html += '<div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap;">';
                    html += '<div style="flex:1;min-width:0;">';
                    html += '<div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;"><i class="' + esc(inf.tipo_icono || 'fa-solid fa-wave-square') + '" style="color:var(--accent-text);"></i>';
                    html += '<strong style="font-size:13px;">' + esc(inf.tipo_nombre || '') + '</strong></div>';
                    html += '<div style="font-size:12px;color:var(--text-secondary);">';
                    html += 'Nº ' + esc(inf.numero_informe || '-') + ' · ' + esc(inf.fecha_formateada || '—');
                    html += ' · <span style="color:var(--text-muted);">' + esc(inf.ecografista || '—') + '</span>';
                    html += '</div>';
                    html += '</div>';
                    html += '<div style="display:flex;align-items:center;gap:8px;">';
                    html += '<span style="font-size:11px;font-weight:700;text-transform:uppercase;padding:3px 8px;border-radius:999px;background:var(--accent-soft);color:var(--accent-text);">' + esc(inf.estado_label || inf.estado || '') + '</span>';
                    html += '<button type="button" class="btn-primary rx-js-inf-det" data-rx-inf="' + esc(inf.id) + '" style="padding:6px 12px;font-size:12px;white-space:nowrap;">' +
                        '<i class="fa-solid fa-file-lines"></i> Ver detalle</button>';
                    html += '</div></div></div>';
                });
                html += '</div>';
                body.innerHTML = html;
            })
            .catch(function () {
                if (sub) sub.textContent = esc(pacienteNombre || '');
                body.innerHTML = '<p style="color:var(--danger);font-size:13px;">No se pudieron cargar los informes.</p>';
            });
    };

    /** --- Detalle de un informe (solo lectura) ---
        Se abre desde la lista de "Estudios e informes". La lista se cierra al
        abrirlo y el botón "Volver" la reabre: dos modales encima del otro
        dejan al fondo el que sigue capturando el teclado. */
    var rxInfDetId = null;
    var rxInfDetPacienteId = null;
    var rxInfDetPacienteNom = '';

    function rxInfDetPinta(data) {
        var body = byId('eco-informe-detalle-body');
        var icon = byId('eco-inf-det-icon');
        var titulo = byId('eco-inf-det-titulo');
        var pac = byId('eco-inf-det-paciente');
        if (!body) return;

        if (data.error) {
            body.innerHTML = '<p style="color:var(--danger);padding:20px;">' + esc(data.error) + '</p>';
            if (icon) icon.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i>';
            return;
        }

        var inf = data.informe || {}, tipo = data.tipo || {}, p = data.paciente || {};
        if (icon) icon.innerHTML = '<i class="' + esc(tipo.icono || 'fa-solid fa-wave-square') + '"></i>';
        if (titulo) titulo.textContent = tipo.nombre || 'Informe de estudio';
        if (pac) {
            var edad = p.edad ? (String(p.edad).trim() + ' años') : '';
            pac.textContent = 'Paciente: ' + (p.nombre || '—')
                + '  ·  CI: ' + (p.cedula || '—') + '  ·  ' + (edad || '—');
        }

        var colores = { finalizado: ['#166534', '#dcfce7'], firmado: ['#075985', '#e0f2fe'], anulado: ['#b91c1c', '#fee2e2'] };
        var c = colores[inf.estado] || ['#374151', '#f3f4f6'];
        var distintivo = '<span style="background:' + c[1] + ';color:' + c[0] + ';padding:2px 10px;border-radius:12px;font-weight:600;font-size:11px;">'
            + esc(inf.estado_label || inf.estado || '') + '</span>';

        var meta = '<div class="inf-det-meta">'
            + '<span><i class="fa-solid fa-hashtag"></i> <strong>' + esc(inf.numero_informe || '-') + '</strong></span>'
            + '<span><i class="fa-regular fa-calendar"></i> <strong>' + esc(inf.fecha_formateada || '-') + '</strong></span>'
            + '<span><i class="fa-solid fa-user-doctor"></i> <strong>' + esc(data.ecografista || '-') + '</strong></span>'
            + '<span>' + distintivo + '</span></div>';

        var firma = '';
        if (inf.firma) {
            firma = '<div style="margin:8px 0 4px;padding:8px 12px;border-radius:8px;background:#e0f2fe;color:#075985;font-size:12.5px;">'
                + '<i class="fa-solid fa-signature"></i> Firmado por <strong>' + esc(inf.firma.por) + '</strong>'
                + (inf.firma.fecha ? ' · ' + esc(inf.firma.fecha) : '') + '</div>';
        }
        body.innerHTML = meta + firma + (data.html || '');

        /* Sin acceso al contenido clínico no tiene sentido ofrecer "imprimir":
           la versión imprimible ES el informe completo y responde 403. Se
           oculta el botón en vez de dejar que lleve a un error. */
        var btnP = byId('rx-inf-det-print');
        if (btnP) btnP.style.display = (data.clinico_visible === false) ? 'none' : '';
    }

    window.rxAbrirInformeDetalle = function (informeId) {
        var body = byId('eco-informe-detalle-body');
        if (!body || !window.EcoModal) return;
        rxInfDetId = informeId;

        /* "Volver" solo tiene sentido si se venía de la lista. Desde la ficha
           el informe se abre directo, y un botón que no lleva a ninguna parte
           es peor que no tenerlo. */
        var lista = byId('eco-modal-rx-informes-paciente');
        var desdeLista = !!(lista && lista.classList.contains('eco-modal--open'));
        var btnV = byId('rx-inf-det-volver');
        if (btnV) btnV.style.display = desdeLista ? '' : 'none';

        EcoModal.close('eco-modal-rx-informes-paciente');
        body.innerHTML = '<div class="modal-form-eco-loader"><i class="fa-solid fa-spinner fa-spin"></i><p>Cargando informe…</p></div>';
        var t = byId('eco-inf-det-titulo');
        if (t) t.textContent = 'Cargando…';
        EcoModal.open('eco-modal-informe-detalle-eco');

        fetch((window.ECO_BASE || '') + 'api/get_informe_detalle.php?informe_id=' + encodeURIComponent(informeId))
            .then(function (r) { return r.json(); })
            .then(rxInfDetPinta)
            .catch(function () {
                body.innerHTML = '<p style="color:var(--danger);padding:20px;">No se pudo cargar el informe.</p>';
            });
    };

    /** --- Alta extendida --- */
    var rxExtFp = null;
    var rxExtFpCita = null;
    function initRxExtFp() {
        if (typeof flatpickr === 'undefined') return;
        var loc = flatpickr.l10ns && flatpickr.l10ns.es ? flatpickr.l10ns.es : undefined;

        var fn = byId('rx-ext-fnac');
        if (fn) {
            destroyFp(fn);
            rxExtFp = null;
            fn.value = '';
            rxExtFp = flatpickr(fn, { locale: loc, dateFormat: 'Y-m-d', maxDate: 'today', altInput: true, altFormat: 'd/m/Y' });
        }

        // Fecha de atención: sí admite futuro y lleva hora, al revés que la de
        // nacimiento.
        var fc = byId('rx-ext-fecha-cita');
        if (fc) {
            destroyFp(fc);
            rxExtFpCita = null;
            fc.value = '';
            rxExtFpCita = flatpickr(fc, {
                locale: loc,
                enableTime: true,
                dateFormat: 'Y-m-d H:i',
                altInput: true,
                altFormat: 'd/m/Y h:i K',
                minuteIncrement: 15
            });
        }
    }

    window.rxAbrirCrearPacienteExtendido = function () {
        if (!window.EcoModal) return;
        var form = byId('rx-form-crear-paciente-extendido');
        rxSetErr('rx-ext-error', '');
        if (form) form.reset();
        rxExtAtencion.reiniciar();
        destroyFp(byId('rx-ext-fnac'));
        destroyFp(byId('rx-ext-fecha-cita'));
        EcoModal.open('eco-modal-rx-crear-paciente-extendido');
        setTimeout(initRxExtFp, 0);
    };

    /** Compatibilidad con buscar_pacientes_secretaria (onclick antiguo) */
    window.abrirModalProgramarCita = function (id, nombre) {
        rxAbrirProgramarCita(id, nombre || '');
    };

    document.addEventListener('DOMContentLoaded', function () {
        var wrapRx = byId('rx-pac-wrap');
        if (wrapRx && window.EcoTableSort) {
            EcoTableSort.init(wrapRx);
        }
        if (wrapRx) {
            wrapRx.addEventListener('click', function (e) {
                var bFicha = e.target.closest('.rx-js-ficha');
                if (bFicha) {
                    e.preventDefault();
                    var fid = parseInt(bFicha.getAttribute('data-rx-pid'), 10);
                    if (fid && typeof window.abrirModalGestionarPaciente === 'function') {
                        window.abrirModalGestionarPaciente(fid);
                    }
                    return;
                }
                var bProg = e.target.closest('.rx-js-prog');
                if (bProg) {
                    e.preventDefault();
                    var pid = parseInt(bProg.getAttribute('data-rx-pid'), 10);
                    var nom = bProg.getAttribute('data-rx-nom');
                    if (nom === null) nom = '';
                    if (pid) window.rxAbrirProgramarCita(pid, nom);
                    return;
                }
            });
        }

        /* Los botones "Ver detalle" se inyectan dentro del modal de informes,
           así que el escuchador va delegado en el documento. */
        document.addEventListener('click', function (e) {
            var b = e.target.closest('.rx-js-inf-det');
            if (!b) return;
            e.preventDefault();
            var iid = b.getAttribute('data-rx-inf');
            if (iid) window.rxAbrirInformeDetalle(iid);
        });

        var btnVolver = byId('rx-inf-det-volver');
        if (btnVolver) {
            btnVolver.addEventListener('click', function () {
                EcoModal.close('eco-modal-informe-detalle-eco');
                if (rxInfDetPacienteId) {
                    window.rxAbrirInformesPaciente(rxInfDetPacienteId, rxInfDetPacienteNom);
                }
            });
        }

        /* Imprimir sin salir del modal: se carga la versión imprimible en un
           iframe oculto que llama solo a window.print(). */
        var btnPrint = byId('rx-inf-det-print');
        if (btnPrint) {
            btnPrint.addEventListener('click', function () {
                if (!rxInfDetId) return;
                var previo = byId('rx-inf-print-frame');
                if (previo) previo.remove();
                var marco = document.createElement('iframe');
                marco.id = 'rx-inf-print-frame';
                marco.setAttribute('aria-hidden', 'true');
                marco.style.cssText = 'position:fixed;left:-10000px;top:0;width:8.5in;height:11in;border:0;visibility:hidden;';
                marco.src = (window.ECO_BASE || '') + 'informe/' + encodeURIComponent(rxInfDetId) + '?print=1';
                document.body.appendChild(marco);
                setTimeout(function () { try { marco.remove(); } catch (err) {} }, 60000);
            });
        }

        var fProg = byId('rx-form-programar-cita');
        if (fProg) {
            fProg.addEventListener('submit', function (e) {
                e.preventDefault();
                rxSetErr('rx-prog-error', '');
                var btn = byId('rx-prog-submit');
                if (!byId('rx-prog-ecografista') || !byId('rx-prog-ecografista').value) {
                    rxSetErr('rx-prog-error', 'Seleccione un ecografista.');
                    return;
                }
                if (btn) {
                    btn.disabled = true;
                    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Guardando…';
                }
                fetch((window.ECO_BASE || '') + 'api/guardar_cita_directa.php', { method: 'POST', body: new FormData(fProg) })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (btn) {
                            btn.disabled = false;
                            btn.innerHTML = '<i class="fa-solid fa-check"></i> Guardar cita';
                        }
                        if (data.success) {
                            if (typeof EcoModal !== 'undefined') EcoModal.close('eco-modal-rx-programar-cita');
                            window.alert(data.message || 'Cita guardada.');
                            if (typeof window.buscarPacientesRecepcion === 'function') {
                                var inp = byId('buscador-pacientes-rx');
                                window.buscarPacientesRecepcion(inp ? inp.value : '');
                            } else {
                                window.location.reload();
                            }
                        } else {
                            rxSetErr('rx-prog-error', data.message || 'No se pudo guardar.');
                        }
                    })
                    .catch(function () {
                        if (btn) {
                            btn.disabled = false;
                            btn.innerHTML = '<i class="fa-solid fa-check"></i> Guardar cita';
                        }
                        rxSetErr('rx-prog-error', 'Error de red.');
                    });
            });
        }

        var fExt = byId('rx-form-crear-paciente-extendido');
        if (fExt) {
            fExt.addEventListener('submit', function (e) {
                e.preventDefault();
                rxSetErr('rx-ext-error', '');
                var p1 = byId('rx-ext-pass');
                var p2 = byId('rx-ext-pass2');
                if (p1 && p2 && p1.value !== p2.value) {
                    rxSetErr('rx-ext-error', 'Las contraseñas no coinciden.');
                    return;
                }
                var btn = byId('rx-ext-submit');
                if (btn) {
                    btn.disabled = true;
                    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Guardando…';
                }
                fetch((window.ECO_BASE || '') + 'api/guardar_paciente_extendido_ajax.php', { method: 'POST', body: new FormData(fExt) })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (btn) {
                            btn.disabled = false;
                            btn.innerHTML = '<i class="fa-solid fa-check"></i> Registrar';
                        }
                        if (data.success) {
                            EcoModal.close('eco-modal-rx-crear-paciente-extendido');
                            var aviso = data.message || 'Paciente registrado.';
                            if (data.cita) {
                                // Confirma lo que quedó cobrado y a quién se asignó:
                                // sin esto no hay forma de saber si la atención se guardó.
                                aviso += '\n\nAtención registrada: ' + (data.cita.detalle || '')
                                    + '\nEcografista: ' + (data.cita.ecografista || 'Sin asignar')
                                    + '\nCobro: ' + (data.cita.estado_pago || '')
                                    + (data.cita.metodo_pago ? ' · ' + data.cita.metodo_pago : '');
                            }
                            window.alert(aviso);
                            if (typeof window.buscarPacientesRecepcion === 'function') {
                                var inp = byId('buscador-pacientes-rx');
                                window.buscarPacientesRecepcion(inp ? inp.value : '');
                            } else {
                                window.location.reload();
                            }
                        } else {
                            rxSetErr('rx-ext-error', data.message || 'No se pudo registrar.');
                        }
                    })
                    .catch(function () {
                        if (btn) {
                            btn.disabled = false;
                            btn.innerHTML = '<i class="fa-solid fa-check"></i> Registrar';
                        }
                        rxSetErr('rx-ext-error', 'Error de red.');
                    });
            });
        }
    });
})();
