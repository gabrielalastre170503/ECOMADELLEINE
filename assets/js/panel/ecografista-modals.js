(function () {
    'use strict';

    var patientState = { id: null, name: '', age: null, cedula: '', serviciosCita: [], estudiosCita: [], serviciosHoy: [], _stale: false };
    var citaState = { id: null, paciente: '', fecha: '' };
    var studyState = { expediente: '', tipoId: null, tipoNombre: '', tipoIcono: '', tipoPrecio: 0, servicios: [] };
    var _currentInformeDetalleEcoId = null;
    var _currentInformeDetalleEcoEstado = '';
    var _firmarAntesCtx = null; // { informeId } pendiente de firmar antes de imprimir

    /** Toast premium global. Crea stack si no existe y muestra notificación flotante. */
    function ecoToast(opts) {
        opts = opts || {};
        var type = opts.type || 'info';
        var title = opts.title || '';
        var msg = opts.message || '';
        var duration = opts.duration === 0 ? 0 : (opts.duration || 5000);

        var stack = document.getElementById('eco-toast-stack');
        if (!stack) {
            stack = document.createElement('div');
            stack.id = 'eco-toast-stack';
            stack.className = 'eco-toast-stack';
            document.body.appendChild(stack);
        }

        var icons = {
            error:   'fa-solid fa-triangle-exclamation',
            success: 'fa-solid fa-circle-check',
            info:    'fa-solid fa-circle-info',
            warning: 'fa-solid fa-triangle-exclamation'
        };

        var toast = document.createElement('div');
        toast.className = 'eco-toast eco-toast--' + type;
        toast.setAttribute('role', type === 'error' ? 'alert' : 'status');
        toast.innerHTML =
            '<div class="eco-toast__icon"><i class="' + (icons[type] || icons.info) + '"></i></div>' +
            '<div class="eco-toast__body">' +
                (title ? '<p class="eco-toast__title"></p>' : '') +
                '<p class="eco-toast__msg"></p>' +
            '</div>' +
            '<button type="button" class="eco-toast__close" aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>';

        if (title) toast.querySelector('.eco-toast__title').textContent = title;
        toast.querySelector('.eco-toast__msg').textContent = msg;

        function dismiss() {
            if (!toast.parentNode) return;
            toast.classList.add('is-leaving');
            setTimeout(function () { if (toast.parentNode) toast.remove(); }, 260);
        }
        toast.querySelector('.eco-toast__close').addEventListener('click', dismiss);
        stack.appendChild(toast);
        if (duration > 0) setTimeout(dismiss, duration);
        return { dismiss: dismiss };
    }
    window.ecoToast = ecoToast;

    /** Caché en memoria de get_informe_detalle.php (misma sesión/pestaña): reaperturas instantáneas sin refetch */
    var informeDetalleEcoCache = new Map();
    var INFORME_DETALLE_CACHE_MAX = 48;

    function informeEcoCacheGet(id) {
        var k = String(id);
        if (!informeDetalleEcoCache.has(k)) return null;
        var v = informeDetalleEcoCache.get(k);
        informeDetalleEcoCache.delete(k);
        informeDetalleEcoCache.set(k, v);
        return v;
    }

    function informeEcoCacheSet(id, data) {
        var k = String(id);
        if (informeDetalleEcoCache.has(k)) informeDetalleEcoCache.delete(k);
        informeDetalleEcoCache.set(k, data);
        while (informeDetalleEcoCache.size > INFORME_DETALLE_CACHE_MAX) {
            informeDetalleEcoCache.delete(informeDetalleEcoCache.keys().next().value);
        }
    }

    function informeEcoCacheInvalidate(id) {
        if (id == null || id === '') return;
        informeDetalleEcoCache.delete(String(id));
    }

    /* Caché de get_patient_details.php. Al cerrar cualquier submodal se vuelve a
       "Gestionar paciente", que antes repetía el fetch y mostraba de nuevo
       "Cargando datos del paciente...": ese parpadeo en cada paso atrás era la
       mayor parte de la lentitud percibida al navegar entre modales. */
    var pacienteEcoCache = new Map();
    var PACIENTE_CACHE_MAX = 24;
    var pacienteEcoInflight = new Map();

    function pacienteEcoCacheGet(id) {
        var k = String(id);
        if (!pacienteEcoCache.has(k)) return null;
        var v = pacienteEcoCache.get(k);
        pacienteEcoCache.delete(k);
        pacienteEcoCache.set(k, v);
        return v;
    }

    function pacienteEcoCacheSet(id, data) {
        var k = String(id);
        if (pacienteEcoCache.has(k)) pacienteEcoCache.delete(k);
        pacienteEcoCache.set(k, data);
        while (pacienteEcoCache.size > PACIENTE_CACHE_MAX) {
            pacienteEcoCache.delete(pacienteEcoCache.keys().next().value);
        }
    }

    /** Invalida el paciente indicado, o todos si no se pasa id. */
    function pacienteEcoCacheInvalidate(id) {
        if (id == null || id === '') { pacienteEcoCache.clear(); return; }
        pacienteEcoCache.delete(String(id));
    }
    window.ecoInvalidarCachePaciente = pacienteEcoCacheInvalidate;

    /** Devuelve el JSON del paciente; deduplica peticiones simultáneas. */
    function fetchPacienteEcoPayload(pacienteId) {
        var k = String(pacienteId);
        var enCurso = pacienteEcoInflight.get(k);
        if (enCurso) return enCurso;
        var p = fetch((window.ECO_BASE || '') + 'api/get_patient_details.php?id=' + encodeURIComponent(pacienteId))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data && !data.error) pacienteEcoCacheSet(k, data);
                return data;
            })
            .finally(function () { pacienteEcoInflight.delete(k); });
        pacienteEcoInflight.set(k, p);
        return p;
    }

    /* Peticiones GET en vuelo, indexadas por URL.
     *
     * No es una caché: la promesa se descarta al resolverse, así que nunca se
     * muestran datos clínicos viejos. Sirve para dos cosas:
     *   1. Si algo pide la misma URL dos veces seguidas, se reutiliza la
     *      petición en curso en vez de duplicarla.
     *   2. Permite precargar al pasar el cursor: mientras el usuario mueve el
     *      ratón hasta la tarjeta y hace clic, la respuesta ya viene en camino,
     *      así que el submodal abre con los datos puestos y sin spinner.
     */
    var peticionesEnVuelo = new Map();

    function ecoGetJson(url) {
        var enCurso = peticionesEnVuelo.get(url);
        if (enCurso) return enCurso;
        var p = fetch(url)
            .then(function (r) { return r.json(); })
            .finally(function () { peticionesEnVuelo.delete(url); });
        peticionesEnVuelo.set(url, p);
        return p;
    }

    /** Lanza la petición sin usar el resultado; los errores se ignoran a propósito. */
    function ecoPrecargar(url) {
        try { ecoGetJson(url).catch(function () {}); } catch (e) {}
    }

    function urlApi(ruta) {
        return (window.ECO_BASE || '') + ruta;
    }

    var informeEcoDetalleInflight = new Map();

    function fetchInformeDetalleEcoPayload(informeId) {
        var ik = String(informeId);
        var existing = informeEcoDetalleInflight.get(ik);
        if (existing) return existing;
        var p = fetch((window.ECO_BASE || '') + 'api/get_informe_detalle.php?informe_id=' + encodeURIComponent(informeId))
            .then(function (r) { return r.json(); })
            .finally(function () {
                informeEcoDetalleInflight.delete(ik);
            });
        informeEcoDetalleInflight.set(ik, p);
        return p;
    }

    /** Rellena la modal shell de detalle (mismo HTML/DOM siempre); data = JSON del endpoint */
    function aplicarInformeEcoDetalleDOM(data, body, iconEl, tituloEl, pacienteEl) {
        if (data.error) {
            body.innerHTML =
                '<p style="color:#c0392b;padding:20px;">' + esc(data.error) + '</p>';
            iconEl.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i>';
            return;
        }

        var inf = data.informe || {};
        var tipo = data.tipo || {};
        var paciente = data.paciente || {};

        iconEl.innerHTML =
            '<i class="' + esc(tipo.icono || 'fa-solid fa-wave-square') + '"></i>';
        tituloEl.textContent = tipo.nombre || 'Informe de estudio';
        var edadTxt = paciente.edad ? (String(paciente.edad).trim() + ' años') : '';
        pacienteEl.textContent =
            'Paciente: ' + (paciente.nombre || '—') +
            '  ·  CI: ' + (paciente.cedula || '—') +
            '  ·  ' + (edadTxt || '—');

        var estado = inf.estado || '';
        _currentInformeDetalleEcoEstado = estado;
        var estadoColors = {
            borrador:   ['#92400e', '#fef3c7'],
            finalizado: ['#166534', '#dcfce7'],
            firmado:    ['#075985', '#e0f2fe'],
            anulado:    ['#991b1b', '#fee2e2']
        };
        var ec = estadoColors[estado] || ['#374151', '#f3f4f6'];
        var estadoBadge =
            '<span style="background:' + ec[1] + ';color:' + ec[0] + ';padding:2px 10px;border-radius:12px;font-weight:600;font-size:11px;">' +
            esc(inf.estado_label || estado) + '</span>';

        var metaBar =
            '<div class="inf-det-meta">' +
            '<span><i class="fa-solid fa-hashtag"></i> <strong>' + esc(inf.numero_informe || '-') + '</strong></span>' +
            '<span><i class="fa-regular fa-calendar"></i> <strong>' + esc(inf.fecha_formateada || '-') + '</strong></span>' +
            '<span><i class="fa-solid fa-user-doctor"></i> <strong>' + esc(data.ecografista || '-') + '</strong></span>' +
            '<span>' + estadoBadge + '</span>' +
            '</div>';

        var auditLine = '';
        if (inf.firma) {
            auditLine =
                '<div style="margin:8px 0 4px;padding:8px 12px;border-radius:8px;background:#e0f2fe;color:#075985;font-size:12.5px;">' +
                '<i class="fa-solid fa-signature"></i> Firmado por <strong>' + esc(inf.firma.por) + '</strong>' +
                (inf.firma.fecha ? ' · ' + esc(inf.firma.fecha) : '') +
                ' <button type="button" class="eco-firma-verify"><i class="fa-solid fa-shield-halved"></i> Verificar integridad</button>' +
                '</div>';
        } else if (inf.anulacion) {
            auditLine =
                '<div style="margin:8px 0 4px;padding:8px 12px;border-radius:8px;background:#fee2e2;color:#991b1b;font-size:12.5px;">' +
                '<i class="fa-solid fa-ban"></i> Anulado' + (inf.anulacion.fecha ? ' el ' + esc(inf.anulacion.fecha) : '') +
                ' por <strong>' + esc(inf.anulacion.por) + '</strong>' +
                (inf.anulacion.motivo ? ' — Motivo: ' + esc(inf.anulacion.motivo) : '') + '</div>';
        }

        body.innerHTML = metaBar + auditLine + (data.html || '') +
            '<div id="eco-inf-det-imagenes" class="eco-inf-imagenes"></div>' +
            '<div id="eco-inf-det-compartir" class="eco-inf-compartir"></div>';

        // Acciones de firma/anulacion segun estado y autoria
        var puede = !!inf.puede_gestionar;
        var btnFirmar = byId('eco-inf-det-firmar');
        var btnAnular = byId('eco-inf-det-anular');
        if (btnFirmar) btnFirmar.style.display = (puede && estado === 'finalizado') ? '' : 'none';
        if (btnAnular) btnAnular.style.display = (puede && (estado === 'finalizado' || estado === 'firmado')) ? '' : 'none';

        // Imagenes ecograficas / adjuntos (Fase 3); editable solo autor/admin y si no esta anulado.
        cargarImagenesInformeEco(_currentInformeDetalleEcoId, puede && estado !== 'anulado');
        // Compartir resultados por enlace (Fase 3b); solo autor/admin e informes finalizados/firmados.
        montarCompartirInformeEco(_currentInformeDetalleEcoId, estado, puede);
        // Verificar integridad de la firma (Fase 3c); solo cuando esta firmado.
        var vbtn = body.querySelector('.eco-firma-verify');
        if (vbtn) vbtn.addEventListener('click', function () { verificarFirmaInformeEco(_currentInformeDetalleEcoId, vbtn); });
    }

    /* ── Fase 3: imagenes ecograficas y adjuntos del informe ── */
    function ecoFmtBytes(n) {
        n = +n || 0;
        if (n < 1024) return n + ' B';
        if (n < 1048576) return (n / 1024).toFixed(0) + ' KB';
        return (n / 1048576).toFixed(1) + ' MB';
    }

    function cargarImagenesInformeEco(informeId, puedeEditar) {
        var cont = byId('eco-inf-det-imagenes');
        if (!cont || !informeId) return;
        cont.innerHTML =
            '<div class="eco-inf-img-head"><i class="fa-solid fa-images"></i> Imágenes y adjuntos</div>' +
            '<div class="eco-inf-img-loading"><i class="fa-solid fa-spinner fa-spin"></i></div>';
        fetch((window.ECO_BASE || '') + 'api/get_archivos_informe.php?informe_id=' + encodeURIComponent(informeId))
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d || !d.success) { cont.innerHTML = ''; return; }
                renderImagenesInformeEco(cont, informeId, d.archivos || [], !!puedeEditar && !!d.puede_editar);
            })
            .catch(function () { cont.innerHTML = ''; });
    }

    function renderImagenesInformeEco(cont, informeId, archivos, puedeEditar) {
        var html =
            '<div class="eco-inf-img-head"><i class="fa-solid fa-images"></i> Imágenes y adjuntos' +
            (archivos.length ? ' <span class="eco-inf-img-count">' + archivos.length + '</span>' : '') + '</div>';
        if (puedeEditar) {
            html +=
                '<div class="eco-inf-img-actions">' +
                '<button type="button" class="btn-primary eco-inf-img-upbtn" data-inf-up="' + esc(informeId) + '">' +
                '<i class="fa-solid fa-cloud-arrow-up"></i> Subir imagen / PDF</button>' +
                '<input type="file" class="eco-inf-img-file" accept="image/jpeg,image/png,image/webp,application/pdf" hidden multiple>' +
                '<span class="eco-inf-img-hint">JPG, PNG, WEBP o PDF · máx 15 MB</span></div>';
        }
        if (!archivos.length) {
            html += '<div class="eco-inf-img-empty">Sin imágenes ni adjuntos.</div>';
        } else {
            html += '<div class="eco-inf-img-grid">';
            archivos.forEach(function (a) {
                var delBtn = puedeEditar
                    ? '<button type="button" class="eco-inf-img-del" data-del-arch="' + esc(a.id) + '" title="Eliminar"><i class="fa-solid fa-xmark"></i></button>'
                    : '';
                if (a.es_imagen) {
                    html +=
                        '<figure class="eco-inf-img-item" data-img-url="' + esc(a.url) + '" data-img-name="' + esc(a.nombre) + '">' +
                        '<img src="' + esc(a.url) + '" alt="' + esc(a.nombre) + '" loading="lazy">' + delBtn + '</figure>';
                } else {
                    html +=
                        '<figure class="eco-inf-img-item eco-inf-img-fileitem">' +
                        '<a href="' + esc(a.url) + '" target="_blank" rel="noopener" class="eco-inf-img-pdf">' +
                        '<i class="fa-solid fa-file-pdf"></i><span>' + esc(a.nombre) + '</span><small>' + ecoFmtBytes(a.tamano) + '</small></a>' +
                        delBtn + '</figure>';
                }
            });
            html += '</div>';
        }
        cont.innerHTML = html;

        if (puedeEditar) {
            var btn = cont.querySelector('[data-inf-up]');
            var file = cont.querySelector('.eco-inf-img-file');
            if (btn && file) {
                btn.addEventListener('click', function () { file.click(); });
                file.addEventListener('change', function () {
                    if (file.files && file.files.length) subirImagenesInformeEco(informeId, file.files, puedeEditar);
                });
            }
            cont.querySelectorAll('[data-del-arch]').forEach(function (b) {
                b.addEventListener('click', function (e) {
                    e.stopPropagation();
                    if (!window.confirm('¿Eliminar este archivo?')) return;
                    var body = new URLSearchParams(); body.set('archivo_id', b.getAttribute('data-del-arch'));
                    fetch((window.ECO_BASE || '') + 'api/borrar_archivo_informe.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString() })
                        .then(function (r) { return r.json(); })
                        .then(function (d) {
                            if (d && d.success) { ecoToast({ type: 'success', message: 'Archivo eliminado.' }); cargarImagenesInformeEco(informeId, puedeEditar); }
                            else { ecoToast({ type: 'error', message: (d && d.message) || 'No se pudo eliminar.' }); }
                        })
                        .catch(function () { ecoToast({ type: 'error', message: 'Error de red.' }); });
                });
            });
        }

        cont.querySelectorAll('.eco-inf-img-item[data-img-url]').forEach(function (fig) {
            fig.addEventListener('click', function (e) {
                if (e.target.closest('.eco-inf-img-del')) return;
                ecoAbrirLightbox(fig.getAttribute('data-img-url'), fig.getAttribute('data-img-name'));
            });
        });
    }

    function subirImagenesInformeEco(informeId, files, puedeEditar) {
        var arr = Array.prototype.slice.call(files);
        var done = 0;
        ecoToast({ type: 'info', message: 'Subiendo ' + arr.length + ' archivo(s)…' });
        var chain = Promise.resolve();
        arr.forEach(function (f) {
            chain = chain.then(function () {
                var fd = new FormData();
                fd.set('informe_id', informeId);
                fd.set('categoria', f.type === 'application/pdf' ? 'adjunto' : 'imagen');
                fd.set('archivo', f);
                return fetch((window.ECO_BASE || '') + 'api/subir_archivo_informe.php', { method: 'POST', body: fd })
                    .then(function (r) { return r.json(); })
                    .then(function (d) {
                        if (d && d.success) { done++; }
                        else { ecoToast({ type: 'error', message: (d && d.message) || 'Error al subir un archivo.' }); }
                    })
                    .catch(function () { ecoToast({ type: 'error', message: 'Error de red al subir.' }); });
            });
        });
        chain.then(function () {
            if (done) ecoToast({ type: 'success', message: done + ' archivo(s) subido(s).' });
            cargarImagenesInformeEco(informeId, puedeEditar);
        });
    }

    function ecoAbrirLightbox(url, nombre) {
        var ov = byId('eco-img-lightbox');
        if (!ov) {
            ov = document.createElement('div');
            ov.id = 'eco-img-lightbox';
            ov.className = 'eco-img-lightbox';
            ov.innerHTML =
                '<button type="button" class="eco-img-lightbox-close" aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>' +
                '<img alt="">';
            document.body.appendChild(ov);
            ov.addEventListener('click', function (e) {
                if (e.target === ov || e.target.closest('.eco-img-lightbox-close')) ov.classList.remove('is-open');
            });
            document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && ov) ov.classList.remove('is-open'); });
        }
        var img = ov.querySelector('img');
        img.src = url; img.alt = nombre || '';
        ov.classList.add('is-open');
    }

    /* ── Fase 3 (b): compartir resultados por enlace (token sin login) ── */
    function montarCompartirInformeEco(informeId, estado, puede) {
        var cont = byId('eco-inf-det-compartir');
        if (!cont || !informeId) return;
        // Solo el autor/admin y solo informes finalizados o firmados.
        if (!puede || (estado !== 'finalizado' && estado !== 'firmado')) { cont.innerHTML = ''; return; }
        cont.innerHTML =
            '<div class="eco-inf-share-head"><i class="fa-solid fa-share-nodes"></i> Compartir resultados</div>' +
            '<p class="eco-inf-share-hint">Genera un enlace seguro para que el paciente vea sus imágenes sin iniciar sesión.</p>' +
            '<button type="button" class="btn-primary eco-inf-share-btn" data-share-inf="' + esc(informeId) + '">' +
            '<i class="fa-solid fa-link"></i> Generar enlace</button>' +
            '<div class="eco-inf-share-result" hidden></div>';
        var btn = cont.querySelector('[data-share-inf]');
        if (btn) btn.addEventListener('click', function () { generarEnlaceResultadoEco(informeId, btn); });
    }

    function generarEnlaceResultadoEco(informeId, btn) {
        var cont = byId('eco-inf-det-compartir');
        if (!cont) return;
        var out = cont.querySelector('.eco-inf-share-result');
        if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Generando…'; }
        var body = new URLSearchParams();
        body.set('informe_id', informeId);
        body.set('expira_horas', '72');   // 3 dias
        fetch((window.ECO_BASE || '') + 'api/generar_enlace_resultado.php', {
            method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString()
        })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-rotate"></i> Generar otro'; }
                if (!d || !d.success) { ecoToast({ type: 'error', message: (d && d.message) || 'No se pudo generar el enlace.' }); return; }
                var exp = d.expira_en ? (' · válido hasta ' + esc(d.expira_en)) : '';
                var wa = 'https://wa.me/?text=' + encodeURIComponent('Resultados de tu estudio: ' + d.url);
                out.innerHTML =
                    '<div class="eco-inf-share-url"><input type="text" readonly value="' + esc(d.url) + '">' +
                    '<button type="button" class="eco-inf-share-copy" title="Copiar"><i class="fa-solid fa-copy"></i></button></div>' +
                    '<div class="eco-inf-share-links">' +
                    '<a href="' + esc(d.url) + '" target="_blank" rel="noopener"><i class="fa-solid fa-up-right-from-square"></i> Abrir</a>' +
                    '<a href="' + wa + '" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i> WhatsApp</a>' +
                    '<span class="eco-inf-share-exp">' + exp + '</span></div>';
                out.hidden = false;
                var inp = out.querySelector('input');
                var cp = out.querySelector('.eco-inf-share-copy');
                if (cp) cp.addEventListener('click', function () {
                    inp.select();
                    var ok = false;
                    try { ok = document.execCommand('copy'); } catch (e) {}
                    if (navigator.clipboard) { navigator.clipboard.writeText(inp.value).then(function () {}, function () {}); ok = true; }
                    ecoToast({ type: ok ? 'success' : 'info', message: ok ? 'Enlace copiado.' : 'Copia el enlace manualmente.' });
                });
                ecoToast({ type: 'success', message: 'Enlace generado.' });
            })
            .catch(function () {
                if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-link"></i> Generar enlace'; }
                ecoToast({ type: 'error', message: 'Error de red.' });
            });
    }

    /* ── Fase 3 (c): verificar integridad/autenticidad de la firma ── */
    function verificarFirmaInformeEco(informeId, btn) {
        if (!informeId) return;
        var prev = btn ? btn.innerHTML : '';
        if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Verificando…'; }
        fetch((window.ECO_BASE || '') + 'api/verificar_firma.php?format=json&informe_id=' + encodeURIComponent(informeId))
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (btn) { btn.disabled = false; btn.innerHTML = prev; }
                if (!d || !d.success) { ecoToast({ type: 'error', message: (d && d.message) || 'No se pudo verificar.' }); return; }
                if (!d.firmado) { ecoToast({ type: 'info', message: 'Este informe aún no está firmado.' }); return; }
                if (d.valido) {
                    ecoToast({ type: 'success', title: 'Documento íntegro', message: 'Contenido sin alteraciones y sello del servidor auténtico.' });
                } else if (!d.integro) {
                    ecoToast({ type: 'error', title: 'Contenido alterado', message: 'El informe fue modificado tras la firma. No confíes en él.' });
                } else {
                    ecoToast({ type: 'error', title: 'Sello inválido', message: 'El sello de firma no es auténtico.' });
                }
            })
            .catch(function () {
                if (btn) { btn.disabled = false; btn.innerHTML = prev; }
                ecoToast({ type: 'error', message: 'Error de red al verificar.' });
            });
    }

    /** Ejecuta firmar/anular sobre el informe abierto y refresca el modal. */
    function _accionInformeEco(tipo) {
        var id = _currentInformeDetalleEcoId;
        if (!id) return;
        var url, payload = new URLSearchParams();
        payload.set('informe_id', id);

        if (tipo === 'firmar') {
            if (!window.confirm('¿Firmar este informe? Una vez firmado no podrá editarse.')) return;
            url = (window.ECO_BASE || '') + 'api/firmar_informe.php';
        } else {
            var motivo = window.prompt('Motivo de la anulación (mínimo 5 caracteres).\nEl informe NO se borra: queda registrado como anulado.');
            if (motivo === null) return;
            motivo = motivo.trim();
            if (motivo.length < 5) { ecoToast('El motivo es demasiado corto.', 'error'); return; }
            payload.set('motivo', motivo);
            url = (window.ECO_BASE || '') + 'api/anular_informe.php';
        }

        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: payload.toString()
        })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d && d.success) {
                    ecoToast(d.message || 'Listo.', 'success');
                    informeEcoCacheInvalidate(id);
                    window.abrirDetalleInformeEco(id);
                    document.dispatchEvent(new CustomEvent('eco:informes-changed', { detail: { informeId: id } }));
                } else {
                    ecoToast((d && d.message) || 'No se pudo completar la acción.', 'error');
                }
            })
            .catch(function () { ecoToast('Error de red.', 'error'); });
    }

    function esc(value) {
        if (value === null || typeof value === 'undefined') return '';
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function byId(id) {
        return document.getElementById(id);
    }

    function setError(id, message) {
        var el = byId(id);
        if (!el) return;
        el.textContent = message || '';
        el.style.display = message ? 'block' : 'none';
    }

    function formatDate(value) {
        if (!value) return 'Por confirmar';
        var d = new Date(String(value).replace(' ', 'T'));
        if (Number.isNaN(d.getTime())) return value;
        return d.toLocaleString('es-ES', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    window.abrirGestionPacienteEco = function (pacienteId) {
        var body = byId('eco-gestion-pac-body');
        var nombreEl = byId('eco-gestion-pac-nombre');
        var metaEl = byId('eco-gestion-pac-meta');
        var telEl = byId('eco-gestion-pac-tel');
        if (!body || !nombreEl || !window.EcoModal) return;

        patientState.id = pacienteId;

        var cacheado = pacienteEcoCacheGet(pacienteId);
        if (!cacheado) {
            // Solo se muestra el estado de carga si de verdad hay que esperar:
            // al volver de un submodal los datos ya están y se pinta directo.
            patientState.name = '';
            body.innerHTML = '<p class="eco-modal__body-text">Cargando datos del paciente...</p>';
            nombreEl.textContent = '...';
            if (metaEl) metaEl.textContent = '';
            if (telEl) { telEl.textContent = ''; telEl.style.display = 'none'; }
        }
        EcoModal.open('eco-modal-gestionar-paciente-eco');

        if (cacheado) {
            pintarGestionPacienteEco(cacheado, pacienteId);
            return;
        }

        fetchPacienteEcoPayload(pacienteId)
            .then(function (data) {
                if (data.error) {
                    body.innerHTML = '<p style="color:#b91c1c;">' + esc(data.error) + '</p>';
                    return;
                }
                pintarGestionPacienteEco(data, pacienteId);
            })
            .catch(function () {
                body.innerHTML = '<p style="color:#b91c1c;">No se pudo cargar la informacion del paciente.</p>';
            });
    };

    /** Vuelca el JSON del paciente en el modal de gestión y engancha sus acciones. */
    function pintarGestionPacienteEco(data, pacienteId) {
        var body = byId('eco-gestion-pac-body');
        var nombreEl = byId('eco-gestion-pac-nombre');
        var metaEl = byId('eco-gestion-pac-meta');
        var telEl = byId('eco-gestion-pac-tel');
        if (!body || !nombreEl) return;

        (function () {
                var p = data.paciente || {};
                patientState.name = p.nombre_completo || '';
                patientState.age = p.edad || null;
                patientState.cedula = p.cedula || '';
                patientState.serviciosCita = data.servicios_cita || [];
                patientState.estudiosCita = data.estudios_cita || [];
                patientState.serviciosHoy = data.servicios_hoy || [];
                patientState._stale = false;
                nombreEl.textContent = p.nombre_completo || '-';

                var meta = [];
                if (p.edad) meta.push(p.edad + ' anos');
                if (p.cedula) meta.push('Cedula: ' + p.cedula);
                if (metaEl) metaEl.textContent = meta.join(' · ');

                if (telEl) {
                    if (p.telefono) {
                        telEl.innerHTML = '<i class="fa-solid fa-phone" style="margin-right:5px;color:var(--text-muted);"></i>' + esc(p.telefono);
                        telEl.style.display = '';
                    } else {
                        telEl.textContent = '';
                        telEl.style.display = 'none';
                    }
                }

                var nInf = typeof data.total_estudios === 'number' ? data.total_estudios : 0;
                var nCit = typeof data.total_citas === 'number' ? data.total_citas : 0;
                var pid = encodeURIComponent(pacienteId);

                body.innerHTML =
                    '<p class="eco-modal__body-text" style="margin-bottom:14px;">' +
                    (p.correo ? '<i class="fa-regular fa-envelope" style="width:16px;color:var(--text-muted);"></i> ' + esc(p.correo) + '<br>' : '') +
                    (p.direccion ? '<i class="fa-solid fa-location-dot" style="width:16px;color:var(--text-muted);"></i> ' + esc(p.direccion) + '<br>' : '') +
                    '<span style="color:var(--text-muted);font-size:12px;">' + nCit + ' citas · ' + nInf + ' informes de estudio</span></p>' +
                    '<div class="eco-action-grid">' +
                    '<button type="button" class="eco-action-card eco-action-card--informes" id="eco-btn-open-informes-pac">' +
                    '<span class="eco-action-card__icon eco-action-card__icon--informes"><i class="fa-solid fa-folder-open"></i></span>' +
                    '<div><h3>Ver informes (' + nInf + ')</h3><p>Historial de estudios ecograficos.</p></div></button>' +
                    '<button type="button" class="eco-action-card eco-action-card--historia" id="eco-btn-open-historia-pac">' +
                    '<span class="eco-action-card__icon eco-action-card__icon--notas"><i class="fa-solid fa-clock-rotate-left"></i></span>' +
                    '<div><h3>Historia clinica</h3><p>Linea de tiempo: estudios, notas y citas.</p></div></button>' +
                    '<button type="button" class="eco-action-card eco-action-card--nuevo" id="eco-btn-new-informe-pac">' +
                    '<span class="eco-action-card__icon eco-action-card__icon--nuevo"><i class="fa-solid fa-file-pen"></i></span>' +
                    '<div><h3>Nuevo informe</h3><p>Crear estudio con formulario dinamico.</p></div></button>' +
                    '<button type="button" class="eco-action-card eco-action-card--notas" id="eco-btn-open-notas-pac">' +
                    '<span class="eco-action-card__icon eco-action-card__icon--notas"><i class="fa-solid fa-notes-medical"></i></span>' +
                    '<div><h3>Notas de sesion</h3><p>Ver y anadir notas clinicas.</p></div></button>' +
                    '<button type="button" class="eco-action-card eco-action-card--cita" id="eco-btn-programar-cita-pac">' +
                    '<span class="eco-action-card__icon eco-action-card__icon--cita"><i class="fa-solid fa-calendar-plus"></i></span>' +
                    '<div><h3>Programar cita</h3><p>Agenda directa con el paciente.</p></div></button>' +
                    '<button type="button" class="eco-action-card eco-action-card--facturacion" id="eco-btn-open-facturacion-pac">' +
                    '<span class="eco-action-card__icon eco-action-card__icon--facturacion"><i class="fa-solid fa-cash-register"></i></span>' +
                    '<div><h3>Facturación</h3><p>Ver saldos y abonar el pago del paciente.</p></div></button>' +
                    '</div>';

                var btnNotas = byId('eco-btn-open-notas-pac');
                if (btnNotas) {
                    btnNotas.addEventListener('click', function () {
                        window.abrirNotasPacienteEco(pacienteId, patientState.name);
                    });
                }

                var btnInformes = byId('eco-btn-open-informes-pac');
                if (btnInformes) {
                    btnInformes.addEventListener('click', function () {
                        window.abrirInformesPacienteEco(pacienteId);
                    });
                }

                var btnHistoria = byId('eco-btn-open-historia-pac');
                if (btnHistoria) {
                    btnHistoria.addEventListener('click', function () {
                        window.abrirHistoriaClinicaEco(pacienteId);
                    });
                }

                var btnNuevoInforme = byId('eco-btn-new-informe-pac');
                if (btnNuevoInforme) {
                    btnNuevoInforme.addEventListener('click', function () {
                        window.abrirFlujoNuevoInformeEco(pacienteId);
                    });
                }

                var btnProg = byId('eco-btn-programar-cita-pac');
                if (btnProg) {
                    btnProg.addEventListener('click', function () {
                        window.abrirProgramarCitaEco(pacienteId, patientState.name, { fromGestion: true });
                    });
                }

                var btnFact = byId('eco-btn-open-facturacion-pac');
                if (btnFact) {
                    btnFact.addEventListener('click', function () {
                        window.abrirFacturacionPacienteEco(pacienteId);
                    });
                }

                /* Precarga al apuntar: el trayecto del ratón hasta la tarjeta y
                   el clic dan de sobra para los ~30 ms que tarda la API, así que
                   el submodal abre con los datos ya puestos. Solo se dispara una
                   vez por tarjeta y la respuesta no se retiene. */
                var pid = encodeURIComponent(pacienteId);
                [
                    [btnInformes, 'api/get_informes_paciente.php?paciente_id=' + pid],
                    [btnHistoria, 'api/get_historia_clinica.php?paciente_id=' + pid],
                    [btnNotas,    'api/get_notas_paciente.php?paciente_id=' + pid],
                    [btnFact,     'api/get_facturacion_paciente.php?paciente_id=' + pid]
                ].forEach(function (par) {
                    var boton = par[0];
                    if (!boton) return;
                    var lanzar = function () { ecoPrecargar(urlApi(par[1])); };
                    boton.addEventListener('mouseenter', lanzar, { once: true, passive: true });
                    boton.addEventListener('focus', lanzar, { once: true });
                });
        })();
    }

    function ecoInformEstadoClass(estado) {
        var e = String(estado || '').toLowerCase();
        if (e === 'borrador') return 'eco-inform-card__status eco-inform-card__status--borrador';
        if (e === 'finalizado') return 'eco-inform-card__status eco-inform-card__status--finalizado';
        if (e === 'firmado') return 'eco-inform-card__status eco-inform-card__status--firmado';
        if (e === 'anulado') return 'eco-inform-card__status eco-inform-card__status--anulado';
        return 'eco-inform-card__status';
    }

    function informesStripSetLoading() {
        var nm = byId('eco-informes-strip-name');
        var ci = byId('eco-informes-strip-ci');
        var cnt = byId('eco-informes-strip-count');
        var ageW = byId('eco-informes-strip-age-wrap');
        if (nm) nm.textContent = 'Cargando…';
        if (ci) ci.textContent = 'CI —';
        if (cnt) cnt.textContent = '—';
        if (ageW) ageW.hidden = true;
    }

    function informesStripSetFromAjax(data) {
        var nm = byId('eco-informes-strip-name');
        var ci = byId('eco-informes-strip-ci');
        var cnt = byId('eco-informes-strip-count');
        var ageW = byId('eco-informes-strip-age-wrap');
        var age = byId('eco-informes-strip-age');
        if (nm) nm.textContent = data.paciente_nombre || '—';
        if (ci) ci.textContent = 'CI ' + (data.paciente_cedula || '—');
        if (cnt) cnt.textContent = (data.total || 0) + ' informes';
        if (ageW && age) {
            var ed = data.paciente_edad;
            var edTrim = ed != null ? String(ed).trim() : '';
            if (edTrim) {
                age.textContent = /^\d+$/.test(edTrim) ? edTrim + ' años' : edTrim;
                ageW.hidden = false;
            } else {
                age.textContent = '';
                ageW.hidden = true;
            }
        }
    }

    window.abrirInformesPacienteEco = function (pacienteId) {
        var list = byId('eco-informes-list');
        if (!list || !window.EcoModal) return;

        patientState.id = pacienteId;

        list.innerHTML =
            '<div class="eco-inform-modal__loading" role="status"><i class="fa-solid fa-spinner fa-spin"></i> Cargando informes...</div>';
        list.setAttribute('aria-busy', 'true');

        informesStripSetLoading();
        EcoModal.close('eco-modal-gestionar-paciente-eco');
        EcoModal.open('eco-modal-informes-paciente-eco');

        ecoGetJson(urlApi('api/get_informes_paciente.php?paciente_id=' + encodeURIComponent(pacienteId)))
            .then(function (data) {
                if (data.error) {
                    var nmErr = byId('eco-informes-strip-name');
                    var ciErr = byId('eco-informes-strip-ci');
                    var cnErr = byId('eco-informes-strip-count');
                    var agWr = byId('eco-informes-strip-age-wrap');
                    if (nmErr) nmErr.textContent = '—';
                    if (ciErr) ciErr.textContent = 'CI —';
                    if (cnErr) cnErr.textContent = '—';
                    if (agWr) agWr.hidden = true;
                    list.innerHTML = '<p class="eco-modal__body-text" style="color:var(--danger);padding:24px;text-align:center;">' + esc(data.error) + '</p>';
                    list.setAttribute('aria-busy', 'false');
                    return;
                }

                informesStripSetFromAjax(data);

                patientState.name = data.paciente_nombre || patientState.name;
                patientState.age = data.paciente_edad || patientState.age;
                patientState.cedula = data.paciente_cedula || patientState.cedula;

                if (!data.informes || !data.informes.length) {
                    list.innerHTML =
                        '<div class="eco-inform-modal__empty">' +
                        '<div class="eco-inform-modal__empty-icon"><i class="fa-solid fa-folder-open"></i></div>' +
                        '<h3 class="eco-inform-modal__empty-title">Sin estudios registrados</h3>' +
                        '<p class="eco-inform-modal__empty-copy">Todavía no hay informes ecográficos asociados a este paciente. ' +
                        'Puede crear el primero con el botón <strong>Nuevo informe</strong> en la parte superior.</p>' +
                        '<p class="eco-inform-modal__empty-tip">Consejo: complete el informe antes de archivar para mantener el historial al dia.</p>' +
                        '</div>';
                    list.setAttribute('aria-busy', 'false');
                    return;
                }

                list.innerHTML = data.informes.map(function (inf) {
                    var lab = esc(inf.estado_label || inf.estado || '');
                    var catChip = '';
                    if (inf.tipo_categoria && String(inf.tipo_categoria).trim()) {
                        catChip =
                            '<span class="eco-inform-card__cat">' +
                            esc(String(inf.tipo_categoria).trim()) + '</span>';
                    }
                    return '<article class="eco-inform-card" role="listitem">' +
                        '<div class="eco-inform-card__avatar">' +
                        '<i class="' + esc(inf.tipo_icono || 'fa-solid fa-wave-square') + '" aria-hidden="true"></i></div>' +
                        '<div class="eco-inform-card__main">' +
                        '<div class="eco-inform-card__head">' +
                        '<h3 class="eco-inform-card__title">' + esc(inf.tipo_nombre || 'Ecografia') + '</h3>' +
                        catChip +
                        '</div>' +
                        '<ul class="eco-inform-card__meta">' +
                        '<li class="eco-inform-card__meta-item"><i class="fa-solid fa-hashtag"></i># ' +
                        esc(inf.numero_informe || '-') + '</li>' +
                        '<li class="eco-inform-card__meta-item"><i class="fa-regular fa-calendar"></i>' +
                        esc(inf.fecha_formateada || '-') + '</li>' +
                        '<li class="eco-inform-card__meta-item"><i class="fa-solid fa-user-doctor"></i>' +
                        esc(inf.ecografista || '-') + '</li>' +
                        '</ul></div>' +
                        /* El estado va aqui, no en el titulo: asi queda a la
                           altura de los botones en lugar de flotando arriba. */
                        '<div class="eco-inform-card__actions">' +
                        '<span class="' + ecoInformEstadoClass(inf.estado) + '">' + lab + '</span>' +
                        ((inf.estado === 'borrador' || inf.estado === 'finalizado')
                            ? '<button type="button" class="btn-secondary eco-inform-card__cta" data-edit-informe-id="' +
                              Number(inf.id) + '" title="' + (inf.estado === 'borrador' ? 'Continuar borrador' : 'Editar informe') + '">' +
                              '<i class="fa-solid fa-pen"></i> ' + (inf.estado === 'borrador' ? 'Continuar' : 'Editar') + '</button>'
                            : '') +
                        '<button type="button" class="btn-secondary eco-inform-card__cta" data-informe-id="' +
                        Number(inf.id) + '" title="Ver detalle">' +
                        '<i class="fa-solid fa-eye"></i> Ver</button>' +
                        '</div>' +
                        '</article>';
                }).join('');

                list.querySelectorAll('[data-informe-id]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        window.abrirDetalleInformeEco(btn.getAttribute('data-informe-id'));
                    });
                });
                list.querySelectorAll('[data-edit-informe-id]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        window.editarInformeEstudioEco(btn.getAttribute('data-edit-informe-id'));
                    });
                });
                list.setAttribute('aria-busy', 'false');
            })
            .catch(function () {
                list.innerHTML =
                    '<p class="eco-modal__body-text" style="color:var(--danger);padding:24px;text-align:center;">No se pudieron cargar los informes.</p>';
                list.setAttribute('aria-busy', 'false');
                var nm = byId('eco-informes-strip-name');
                var ci = byId('eco-informes-strip-ci');
                var cnt = byId('eco-informes-strip-count');
                var ageW = byId('eco-informes-strip-age-wrap');
                if (nm) nm.textContent = '—';
                if (ci) ci.textContent = 'CI —';
                if (cnt) cnt.textContent = '—';
                if (ageW) ageW.hidden = true;
            });
    };

    window.abrirHistoriaClinicaEco = function (pacienteId) {
        var body = byId('eco-hc-body');
        var nameEl = byId('eco-hc-paciente');
        if (!body || !window.EcoModal) return;

        patientState.id = pacienteId;
        body.innerHTML =
            '<div class="modal-form-eco-loader"><i class="fa-solid fa-spinner fa-spin"></i><p>Cargando historia clínica…</p></div>';
        if (nameEl) nameEl.textContent = patientState.name || '—';
        EcoModal.close('eco-modal-gestionar-paciente-eco');
        EcoModal.open('eco-modal-historia-clinica-eco');

        ecoGetJson(urlApi('api/get_historia_clinica.php?paciente_id=' + encodeURIComponent(pacienteId)))
            .then(function (data) {
                if (data.error) {
                    body.innerHTML = '<p style="color:#b91c1c;padding:20px;">' + esc(data.error) + '</p>';
                    return;
                }
                var p = data.paciente || {};
                if (nameEl) nameEl.textContent = (p.nombre || '—') + (p.cedula ? ' · CI ' + p.cedula : '');

                // Ficha del paciente
                function ini(n) { var a = String(n || '').trim().split(/\s+/), o = ''; for (var i = 0; i < a.length && o.length < 2; i++) { if (a[i]) o += a[i][0].toUpperCase(); } return o || '?'; }
                function fact(l, v) { return v ? '<div class="hc-fact"><span class="hc-fact__l">' + l + '</span><span class="hc-fact__v">' + esc(v) + '</span></div>' : ''; }
                var facts =
                    fact('Teléfono', p.telefono) +
                    fact('Correo', p.correo) +
                    fact('Dirección', p.direccion) +
                    fact('Nacimiento', p.nacimiento) +
                    fact('Paciente desde', p.registro);
                var patientCard =
                    '<div class="hc-patient">' +
                        '<div class="hc-patient__avatar">' + ini(p.nombre) + '</div>' +
                        '<div class="hc-patient__main">' +
                            '<div class="hc-patient__name">' + esc(p.nombre || '—') + '</div>' +
                            '<div class="hc-patient__sub">' +
                                (p.cedula ? '<span><i class="fa-solid fa-id-card"></i>' + esc(p.cedula) + '</span>' : '') +
                                (p.edad ? '<span><i class="fa-solid fa-cake-candles"></i>' + esc(p.edad) + ' años</span>' : '') +
                            '</div>' +
                            (facts ? '<div class="hc-facts">' + facts + '</div>' : '') +
                        '</div>' +
                    '</div>';

                // Tarjetas de estadística
                var res = data.resumen || {};
                function stat(icon, color, num, label) {
                    return '<div class="hc-stat"><div class="hc-stat__ic" style="background:' + color + '1f;color:' + color + ';"><i class="fa-solid ' + icon + '"></i></div>' +
                        '<div><div class="hc-stat__num">' + num + '</div><div class="hc-stat__lbl">' + label + '</div></div></div>';
                }
                var statsBar = '<div class="hc-stats">' +
                    stat('fa-file-waveform', '#0ea5e9', (res.informes || 0), 'Informes') +
                    stat('fa-notes-medical', '#8b5cf6', (res.notas || 0), 'Notas') +
                    stat('fa-calendar-check', '#22c55e', (res.citas || 0), 'Citas') +
                    (data.costo_total_fmt ? stat('fa-coins', '#d97706', esc(data.costo_total_fmt), 'Facturado') : '') +
                    '</div>';

                if (!data.eventos || !data.eventos.length) {
                    body.innerHTML = '<div class="hc-wrap">' + patientCard + statsBar +
                        '<div class="hc-empty"><i class="fa-regular fa-folder-open"></i><p>Sin estudios, notas ni citas registradas todavía.</p></div></div>';
                    return;
                }

                var meta = {
                    informe: { icon: 'fa-file-waveform', color: '#0ea5e9' },
                    nota:    { icon: 'fa-notes-medical', color: '#8b5cf6' },
                    cita:    { icon: 'fa-calendar-check', color: '#22c55e' }
                };
                var pagoColors = {
                    pendiente: ['#b45309', 'rgba(245,158,11,.13)'],
                    parcial:   ['#9a3412', 'rgba(249,115,22,.13)'],
                    pagado:    ['#15803d', 'rgba(34,197,94,.13)'],
                    exonerado: ['#475569', 'rgba(100,116,139,.13)']
                };
                var items = data.eventos.map(function (e) {
                    var m = meta[e.tipo] || { icon: 'fa-circle', color: '#94a3b8' };
                    var clickable = e.tipo === 'informe';

                    var chips = '';
                    if (e.numero) chips += '<span class="hc-chip hc-chip--num"># ' + esc(e.numero) + '</span>';
                    if (e.estado) chips += '<span class="hc-chip hc-chip--estado hc-estado--' + esc(e.tipo) + '">' + esc(e.estado) + '</span>';
                    if (e.costo_fmt) chips += '<span class="hc-chip hc-chip--costo">' + esc(e.costo_fmt) + '</span>';

                    var metaRow = '<i class="fa-regular fa-calendar"></i> ' + esc(e.fecha_fmt);
                    if (e.profesional) metaRow += ' <span class="hc-sep">·</span> <i class="fa-solid fa-user-doctor"></i> ' + esc(e.profesional);

                    var det = e.detalle ? '<p class="hc-card__det">' + esc(e.detalle) + '</p>' : '';
                    var extra = '';
                    if (e.tipo === 'cita') {
                        var tags = '';
                        if (e.modalidad) tags += '<span class="hc-tag">' + esc(e.modalidad) + '</span>';
                        if (e.tipo_cita) tags += '<span class="hc-tag">' + esc(e.tipo_cita) + '</span>';
                        if (tags) extra += '<div class="hc-tags">' + tags + '</div>';
                        if (e.servicios) extra += '<p class="hc-card__det"><strong>Servicios:</strong> ' + esc(e.servicios) + '</p>';
                        if (e.pago_label) {
                            var pc = pagoColors[e.pago_estado] || ['#475569', 'rgba(100,116,139,.13)'];
                            var pagoTxt = 'Pago: ' + esc(e.pago_label) +
                                (e.pagado_fmt ? ' · ' + esc(e.pagado_fmt) + ' pagado' : '') +
                                (e.saldo_fmt && e.saldo_fmt !== '$0' ? ' · ' + esc(e.saldo_fmt) + ' saldo' : '');
                            extra += '<div><span class="hc-pago" style="color:' + pc[0] + ';background:' + pc[1] + ';"><i class="fa-solid fa-receipt"></i> ' + pagoTxt + '</span></div>';
                        }
                    }
                    var openLink = clickable ? '<div class="hc-card__open">Ver informe <i class="fa-solid fa-arrow-right"></i></div>' : '';

                    return '<div class="hc-item hc-item--' + esc(e.tipo) + '" style="--hc-c:' + m.color + ';"' +
                        (clickable ? ' data-hc-informe="' + Number(e.id) + '" role="button" tabindex="0"' : '') + '>' +
                        '<div class="hc-dot"><i class="fa-solid ' + m.icon + '"></i></div>' +
                        '<div class="hc-card">' +
                        '<div class="hc-card__top"><span class="hc-card__title">' + esc(e.titulo) + '</span>' + chips + '</div>' +
                        '<div class="hc-card__meta">' + metaRow + '</div>' +
                        det + extra + openLink +
                        '</div></div>';
                }).join('');

                body.innerHTML = '<div class="hc-wrap">' + patientCard + statsBar +
                    '<div class="hc-tl-head"><i class="fa-solid fa-timeline"></i> Línea de tiempo</div>' +
                    '<div class="hc-timeline">' + items + '</div></div>';

                body.querySelectorAll('[data-hc-informe]').forEach(function (el) {
                    el.addEventListener('click', function () {
                        window.abrirDetalleInformeEco(el.getAttribute('data-hc-informe'));
                    });
                });
            })
            .catch(function () {
                body.innerHTML = '<p style="color:#b91c1c;padding:20px;">No se pudo cargar la historia clínica.</p>';
            });
    };

    // Facturación del paciente: ver saldos y abonar el pago directamente.
    window.abrirFacturacionPacienteEco = function (pacienteId) {
        var body = byId('eco-fp-body');
        var nameEl = byId('eco-fp-paciente');
        if (!body || !window.EcoModal) return;

        patientState.id = pacienteId;
        if (nameEl) nameEl.textContent = patientState.name || '—';
        body.innerHTML = '<div class="modal-form-eco-loader"><i class="fa-solid fa-spinner fa-spin"></i><p>Cargando facturación…</p></div>';
        EcoModal.close('eco-modal-gestionar-paciente-eco');
        EcoModal.open('eco-modal-facturacion-paciente-eco');

        function cargar() {
            ecoGetJson(urlApi('api/get_facturacion_paciente.php?paciente_id=' + encodeURIComponent(pacienteId)))
                .then(render)
                .catch(function () {
                    body.innerHTML = '<p style="color:#b91c1c;padding:20px;">No se pudo cargar la facturación.</p>';
                });
        }

        function render(data) {
            if (data.error) { body.innerHTML = '<p style="color:#b91c1c;padding:20px;">' + esc(data.error) + '</p>'; return; }
            if (nameEl) nameEl.textContent = (data.paciente && data.paciente.nombre ? data.paciente.nombre : '—') +
                (data.paciente && data.paciente.cedula ? ' · CI ' + data.paciente.cedula : '');

            var t = data.totales || {};
            var totals =
                '<div class="fp-totals">' +
                '<div class="fp-total fp-total--fact"><div class="fp-total__lbl">Facturado</div><div class="fp-total__val">' + esc(t.facturado_fmt || '$0') + '</div></div>' +
                '<div class="fp-total fp-total--cob"><div class="fp-total__lbl">Cobrado</div><div class="fp-total__val">' + esc(t.cobrado_fmt || '$0') + '</div></div>' +
                '<div class="fp-total fp-total--pend"><div class="fp-total__lbl">Por cobrar</div><div class="fp-total__val">' + esc(t.por_cobrar_fmt || '$0') + '</div></div>' +
                '</div>';

            var metodos = data.metodos || [];
            function optMetodos() {
                return '<option value="">Método…</option>' + metodos.map(function (m) { return '<option value="' + esc(m) + '">' + esc(m) + '</option>'; }).join('');
            }

            if (!data.citas || !data.citas.length) {
                body.innerHTML = '<div class="fp-wrap">' + totals +
                    '<div class="fp-empty"><i class="fa-solid fa-file-invoice-dollar"></i><p>Este paciente no tiene citas facturables.</p></div></div>';
                return;
            }

            var rows = data.citas.map(function (c) {
                var col = c.estado_color || ['#374151', '#f3f4f6'];
                var badge = '<span class="fp-badge" style="color:' + col[0] + ';background:' + col[1] + ';">' + esc(c.estado_label) + '</span>';
                var serv = c.servicios ? '<div class="fp-cita__serv">' + esc(c.servicios) + '</div>' : '';
                var method = c.metodo ? '<div class="fp-method"><i class="fa-solid fa-credit-card"></i> Pagado con ' + esc(c.metodo) + '</div>' : '';

                var abonar = '';
                if (c.puede_abonar) {
                    var ph = String(c.saldo_fmt || '').replace('$', '');
                    abonar =
                        '<button type="button" class="btn-secondary fp-abonar-btn" data-fp-toggle="' + c.id + '"><i class="fa-solid fa-hand-holding-dollar"></i> Abonar</button>' +
                        '<div class="fp-form" id="fp-form-' + c.id + '">' +
                            '<div class="fp-field"><label>Abono ($)</label><input type="number" min="0" step="0.01" id="fp-abono-' + c.id + '" placeholder="' + esc(ph) + '"></div>' +
                            '<div class="fp-field"><label>Método de pago</label><select id="fp-metodo-' + c.id + '">' + optMetodos() + '</select></div>' +
                            '<button type="button" class="btn-primary fp-registrar" data-fp-id="' + c.id + '" data-fp-total="' + esc(c.total) + '"><i class="fa-solid fa-check"></i> Registrar</button>' +
                        '</div>' +
                        '<div class="fp-msg" id="fp-msg-' + c.id + '"></div>';
                }

                return '<div class="fp-cita">' +
                    '<div class="fp-cita__top"><div style="min-width:0;"><div class="fp-cita__title">' + esc(c.estudio) + '</div>' +
                    '<div class="fp-cita__date"><i class="fa-regular fa-calendar"></i> ' + esc(c.fecha_fmt) + '</div></div>' + badge + '</div>' +
                    serv +
                    '<div class="fp-amounts">' +
                    '<div class="fp-amt"><span>Total</span><strong>' + esc(c.total_fmt) + '</strong></div>' +
                    '<div class="fp-amt"><span>Pagado</span><strong>' + esc(c.pagado_fmt) + '</strong></div>' +
                    '<div class="fp-amt fp-amt--saldo"><span>Saldo</span><strong>' + esc(c.saldo_fmt) + '</strong></div>' +
                    '</div>' + method + abonar + '</div>';
            }).join('');

            body.innerHTML = '<div class="fp-wrap">' + totals + '<div class="fp-list">' + rows + '</div></div>';

            body.querySelectorAll('[data-fp-toggle]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var f = byId('fp-form-' + btn.getAttribute('data-fp-toggle'));
                    if (f) {
                        f.classList.toggle('is-open');
                        if (f.classList.contains('is-open')) { var inp = f.querySelector('input'); if (inp) inp.focus(); }
                    }
                });
            });
            body.querySelectorAll('.fp-registrar').forEach(function (btn) {
                btn.addEventListener('click', function () { registrarAbono(btn); });
            });
        }

        function registrarAbono(btn) {
            var id = btn.getAttribute('data-fp-id');
            var total = btn.getAttribute('data-fp-total');
            var abonoEl = byId('fp-abono-' + id);
            var metodoEl = byId('fp-metodo-' + id);
            var msg = byId('fp-msg-' + id);
            function showMsg(text, ok) { if (!msg) return; msg.textContent = text; msg.className = 'fp-msg ' + (ok ? 'fp-msg--ok' : 'fp-msg--err'); }

            var ab = parseFloat(abonoEl ? abonoEl.value : '');
            if (!ab || ab <= 0) { showMsg('Ingresa un monto de abono válido.', false); return; }

            var orig = btn.innerHTML;
            btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';

            var fd = new FormData();
            fd.append('accion', 'cobrar');
            fd.append('cita_id', id);
            fd.append('monto_total', total);
            fd.append('abono', String(ab));
            fd.append('metodo_pago', metodoEl ? metodoEl.value : '');

            fetch((window.ECO_BASE || '') + 'api/registrar_pago.php', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (d && d.success) {
                        showMsg(d.message || 'Abono registrado.', true);
                        document.dispatchEvent(new CustomEvent('eco:facturacion-changed', { detail: { pacienteId: pacienteId } }));
                        setTimeout(cargar, 750);
                    } else {
                        btn.disabled = false; btn.innerHTML = orig;
                        showMsg((d && d.message) || 'No se pudo registrar el pago.', false);
                    }
                })
                .catch(function () { btn.disabled = false; btn.innerHTML = orig; showMsg('Error de red.', false); });
        }

        cargar();
    };

    window.abrirFlujoNuevoInformeEco = function (pacienteId) {
        if (!byId('eco-modal-expediente-informe-eco') || !window.EcoModal) return;

        function openExpediente() {
            var info = byId('eco-expediente-paciente-info');
            var edadEl = byId('eco-expediente-paciente-edad');
            var edad = Number(patientState.age);

            if (info) info.textContent = patientState.name || '—';
            if (edadEl) {
                if (edad > 0) {
                    edadEl.textContent = edad + ' años';
                    edadEl.hidden = false;
                } else {
                    edadEl.hidden = true;
                }
            }

            var adulto = byId('eco-expediente-adulto');
            var infantil = byId('eco-expediente-infantil');
            if (adulto && infantil) {
                // Recomendado según edad
                adulto.classList.toggle('eco-exp-card--recommended', edad >= 18);
                infantil.classList.toggle('eco-exp-card--recommended', edad > 0 && edad < 18);
                // Deshabilitado visual cuando no aplica
                adulto.classList.toggle('eco-exp-card--disabled', edad > 0 && edad < 18);
                infantil.classList.toggle('eco-exp-card--disabled', edad >= 18);
            }

            _ecoResetServicios();
            _ecoPreseleccionarServicios(patientState.serviciosCita);
            // Si la solicitud del paciente pedía SOLO servicios (sin estudios),
            // se desmarca "Ecografías" para llevarlo directo al registro de servicios.
            var chipEco = byId('eco-serv-input-eco');
            if (chipEco && (patientState.serviciosCita || []).length && !(patientState.estudiosCita || []).length) {
                chipEco.checked = false;
            }
            _ecoRefreshServicios();
            EcoModal.close('eco-modal-gestionar-paciente-eco');
            EcoModal.close('eco-modal-informes-paciente-eco');
            EcoModal.open('eco-modal-expediente-informe-eco');
        }

        if (patientState.id === pacienteId && patientState.name && !patientState._stale) {
            openExpediente();
            return;
        }

        fetch((window.ECO_BASE || '') + 'api/get_patient_details.php?id=' + encodeURIComponent(pacienteId))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.error) {
                    window.alert(data.error);
                    return;
                }
                var p = data.paciente || {};
                patientState.id = pacienteId;
                patientState.name = p.nombre_completo || '';
                patientState.age = p.edad || null;
                patientState.cedula = p.cedula || '';
                patientState.serviciosCita = data.servicios_cita || [];
                patientState.estudiosCita = data.estudios_cita || [];
                patientState.serviciosHoy = data.servicios_hoy || [];
                patientState._stale = false;
                openExpediente();
            })
            .catch(function () {
                window.alert('No se pudo cargar el paciente.');
            });
    };

    // ─────────────────────────────────────────────────────────────────────
    // Facturación del flujo de informe: servicios adicionales + cálculo del
    // bundle. Espejo en JS de lib/facturacion.php::eco_calcular_bundle() (el
    // servidor recalcula de forma autoritativa; esto es solo previsualización).
    // ─────────────────────────────────────────────────────────────────────
    function _ecoServiciosCatalogo() {
        var map = {};
        (window.EcoServiciosCatalogo || []).forEach(function (s) { map[s.key] = s; });
        return map;
    }
    function _ecoMoney(n) {
        n = Math.round((Number(n) || 0) * 100) / 100;
        return '$' + ((n % 1 === 0) ? String(n) : n.toFixed(2));
    }
    // Calculo multi-estudio (espejo de eco_calcular_bundle_multi en PHP).
    function _ecoCalcBundleMulti(estudios, keys) {
        var cat = _ecoServiciosCatalogo();
        keys = (keys || []).filter(function (k, i, a) { return cat[k] && a.indexOf(k) === i; });
        var combo = keys.indexOf('combo_cito') !== -1;
        var consulta = keys.indexOf('consulta') !== -1;

        var nombres = [], precios = [];
        (estudios || []).forEach(function (e) {
            var nom = (e && e.nombre ? String(e.nombre) : '').trim();
            if (nom === '') return;
            nombres.push(nom);
            precios.push(Number(e.precio) || 0);
        });

        var total = 0, ahorro = 0, promos = [], sueltos = [];
        keys.forEach(function (k) {
            if (k === 'consulta' || k === 'combo_cito') return;
            if (combo && (k === 'citologia' || k === 'procesamiento')) return;
            total += Number(cat[k].price) || 0;
            sueltos.push(cat[k].label);
        });
        if (combo) { total += 25; ahorro += (20 + 3 + 15) - 25; promos.push('Combo Citología + Procesamiento + Eco pélvico'); }
        if (consulta && precios.length >= 1) {
            var ord = precios.slice().sort(function (a, b) { return b - a; });
            var resto = 0; for (var i = 1; i < ord.length; i++) resto += ord[i];
            total += 25 + resto; ahorro += (ord[0] + 15) - 25;
            promos.push('Promoción Eco + Consulta');
        } else {
            precios.forEach(function (p) { total += p; });
            if (consulta) total += 15;
        }
        if (ahorro < 0) ahorro = 0;

        var parts = [];
        if (nombres.length) parts.push((nombres.length > 1 ? 'Ecografías: ' : 'Estudio: ') + nombres.join(', '));
        var adic = sueltos.slice();
        if (consulta) adic.push(cat['consulta'].label);
        if (combo) adic.push(cat['combo_cito'].label);
        if (adic.length) parts.push(adic.join(', '));
        if (promos.length) parts.push(promos.join(' · '));
        parts.push('Total ' + _ecoMoney(total));
        return {
            total: Math.round(total * 100) / 100,
            motivo: parts.join(' · ').substring(0, 250),
            promos: promos,
            ahorro: Math.round(ahorro * 100) / 100,
            nombres: nombres,
            precios: precios
        };
    }
    function _ecoCalcBundle(ecoPrecio, ecoNombre, keys) {
        var est = [];
        ecoNombre = (ecoNombre || '').trim();
        if (ecoNombre !== '' || (Number(ecoPrecio) || 0) > 0) est.push({ nombre: ecoNombre, precio: Number(ecoPrecio) || 0 });
        return _ecoCalcBundleMulti(est, keys);
    }
    // Estudios a facturar = los que el paciente ya tenia + el que el eco elige ahora.
    function _ecoEstudiosFacturables() {
        var est = [], vistos = {};
        function add(nombre, precio) {
            nombre = (nombre || '').trim();
            if (nombre === '') return;
            var k = nombre.toLowerCase();
            if (vistos[k]) return;
            vistos[k] = true;
            est.push({ nombre: nombre, precio: Number(precio) || 0 });
        }
        (patientState.estudiosCita || []).forEach(function (e) { add(e.nombre, e.precio); });
        if (studyState.tipoNombre) add(studyState.tipoNombre, studyState.tipoPrecio);
        return est;
    }
    function _ecoLeerServicios() {
        var grid = byId('eco-exp-serv-grid');
        if (!grid) return [];
        return Array.prototype.slice.call(grid.querySelectorAll('.eco-serv-input'))
            .filter(function (i) { return i.checked && i.value !== 'eco'; })
            .map(function (i) { return i.value; });
    }
    // ¿Está marcada la tarjeta "Ecografías"? (no es un servicio del catálogo:
    // decide si el flujo pasa por el selector de tipos de ecografía)
    function _ecoEcoSeleccionada() {
        var chip = byId('eco-serv-input-eco');
        return !!(chip && chip.checked);
    }
    function _ecoAplicarLockCombo() {
        var grid = byId('eco-exp-serv-grid');
        if (!grid) return;
        var inputs = Array.prototype.slice.call(grid.querySelectorAll('.eco-serv-input'));
        var combo = inputs.filter(function (i) { return i.value === 'combo_cito'; })[0];
        var partes = inputs.filter(function (i) { return i.value === 'citologia' || i.value === 'procesamiento'; });
        function setLock(input, locked, msg) {
            input.disabled = locked;
            var chip = input.closest('.eco-serv-chip');
            if (chip) { chip.classList.toggle('is-locked', locked); chip.title = locked ? (msg || '') : ''; }
        }
        if (combo && combo.checked) {
            // El combo ya incluye Citología + Procesamiento: se desactivan sueltos.
            partes.forEach(function (i) { i.checked = false; setLock(i, true, 'Ya incluido en el combo'); });
            setLock(combo, false, '');
        } else {
            partes.forEach(function (i) { setLock(i, false, ''); });
            // Si se eligió Citología o Procesamiento por separado, el combo no se puede usar.
            var hayParte = partes.some(function (i) { return i.checked; });
            if (combo) {
                if (hayParte) combo.checked = false;
                setLock(combo, hayParte, 'No disponible: ya elegiste Citología o Procesamiento por separado');
            }
        }
    }
    function _ecoRefreshServicios() {
        _ecoAplicarLockCombo();
        _ecoBloquearServiciosHoy();
        studyState.servicios = _ecoLeerServicios();
        var sub = byId('eco-exp-serv-subtotal');
        if (sub) sub.textContent = _ecoMoney(_ecoCalcBundle(0, '', studyState.servicios).total);
    }
    function _ecoResetServicios() {
        var grid = byId('eco-exp-serv-grid');
        if (grid) {
            Array.prototype.slice.call(grid.querySelectorAll('.eco-serv-input')).forEach(function (i) {
                // "Ecografías" queda marcada por defecto (el caso más común).
                i.checked = (i.value === 'eco'); i.disabled = false;
                var chip = i.closest('.eco-serv-chip');
                if (chip) { chip.classList.remove('is-locked', 'is-today'); chip.title = ''; }
            });
        }
        studyState.servicios = [];
        studyState.tipoPrecio = 0;
        var sub = byId('eco-exp-serv-subtotal');
        if (sub) sub.textContent = '$0';
        var cont = grid ? (grid.closest('.eco-exp-servicios') || document) : document;
        var hint = cont.querySelector('.eco-exp-serv-prefill');
        if (hint) hint.style.display = 'none';
        var hoy = cont.querySelector('.eco-exp-serv-hoy');
        if (hoy) hoy.style.display = 'none';
    }
    // Pre-marca los servicios que el paciente ya eligio al solicitar su cita.
    function _ecoPreseleccionarServicios(keys) {
        var grid = byId('eco-exp-serv-grid');
        if (!grid || !keys || !keys.length) return;
        Array.prototype.slice.call(grid.querySelectorAll('.eco-serv-input')).forEach(function (i) {
            if (keys.indexOf(i.value) !== -1) i.checked = true;
        });
        _ecoRefreshServicios();
        var head = grid.closest('.eco-exp-servicios');
        var hint = head ? head.querySelector('.eco-exp-serv-prefill') : null;
        if (head && !hint) {
            hint = document.createElement('p');
            hint.className = 'eco-exp-serv-prefill';
            hint.innerHTML = '<i class="fa-solid fa-wand-magic-sparkles"></i> Pre-marcados desde la solicitud del paciente. Puedes ajustarlos.';
            var grdEl = head.querySelector('.eco-exp-serv-grid');
            if (grdEl) grdEl.insertAdjacentElement('afterend', hint);
        }
        if (hint) hint.style.display = '';
    }
    // Desactiva los servicios ya facturados HOY (no se cobran dos veces el mismo dia).
    function _ecoBloquearServiciosHoy() {
        var grid = byId('eco-exp-serv-grid');
        if (!grid) return;
        var keys = patientState.serviciosHoy || [];
        var algunoBloqueado = false;
        Array.prototype.slice.call(grid.querySelectorAll('.eco-serv-input')).forEach(function (i) {
            if (keys.indexOf(i.value) === -1) return;
            algunoBloqueado = true;
            i.checked = false;
            i.disabled = true;
            var chip = i.closest('.eco-serv-chip');
            if (chip) { chip.classList.add('is-locked', 'is-today'); chip.title = 'Ya cobrado hoy a este paciente'; }
        });
        var head = grid.closest('.eco-exp-servicios');
        if (!head) return;
        var hint = head.querySelector('.eco-exp-serv-hoy');
        if (algunoBloqueado && !hint) {
            hint = document.createElement('p');
            hint.className = 'eco-exp-serv-hoy';
            hint.innerHTML = '<i class="fa-solid fa-circle-info"></i> Servicios en gris ya se cobraron hoy a este paciente; no se vuelven a cobrar.';
            grid.insertAdjacentElement('afterend', hint);
        }
        if (hint) hint.style.display = algunoBloqueado ? '' : 'none';
    }
    function _ecoFacturaBannerHTML() {
        var estudios = _ecoEstudiosFacturables();
        var calc = _ecoCalcBundleMulti(estudios, studyState.servicios);
        var cat = _ecoServiciosCatalogo();
        var lines = [];
        estudios.forEach(function (e) {
            lines.push('<li><span>' + esc(e.nombre) + '</span><b>' + _ecoMoney(e.precio) + '</b></li>');
        });
        (studyState.servicios || []).forEach(function (k) {
            if (cat[k]) lines.push('<li><span>' + esc(cat[k].label) + '</span><b>' + _ecoMoney(cat[k].price) + '</b></li>');
        });
        var promoHtml = calc.promos.map(function (p) {
            return '<div class="eco-fact-banner__promo"><i class="fa-solid fa-gift"></i> ' + esc(p) + ' aplicada</div>';
        }).join('');
        var ahorroHtml = calc.ahorro > 0
            ? '<div class="eco-fact-banner__ahorro"><i class="fa-solid fa-tags"></i> Ahorro ' + _ecoMoney(calc.ahorro) + '</div>'
            : '';
        return '<div class="eco-fact-banner">' +
            '<div class="eco-fact-banner__head"><i class="fa-solid fa-receipt"></i> Facturación del estudio</div>' +
            '<ul class="eco-fact-banner__lines">' + lines.join('') + '</ul>' +
            promoHtml + ahorroHtml +
            '<div class="eco-fact-banner__total"><span>Total a facturar</span><strong>' + _ecoMoney(calc.total) + '</strong></div>' +
            '</div>';
    }

    function seleccionarExpediente(expediente) {
        var edad = Number(patientState.age);

        // Validación: bloquear combinación incongruente
        if (edad > 0 && edad < 18 && expediente === 'adulto') {
            ecoToast({
                type: 'error',
                title: 'Expediente no disponible',
                message: 'El paciente tiene ' + edad + ' años (menor de edad). Selecciona "Expediente Infantil".',
                duration: 6000
            });
            return;
        }
        if (edad >= 18 && expediente === 'infantil') {
            ecoToast({
                type: 'error',
                title: 'Expediente no disponible',
                message: 'El paciente tiene ' + edad + ' años (mayor de edad). Selecciona "Expediente Adulto".',
                duration: 6000
            });
            return;
        }

        // Sincronizar selección y decidir el camino: con "Ecografías" se pasa al
        // selector de tipos; sin ella, directo al registro/facturación de servicios.
        _ecoRefreshServicios();
        var conEco = _ecoEcoSeleccionada();
        var soloServicios = studyState.servicios || [];
        if (!conEco && !soloServicios.length) {
            ecoToast({
                type: 'error',
                title: 'Sin servicios seleccionados',
                message: 'Selecciona al menos un servicio (o la tarjeta Ecografías) para continuar.',
                duration: 5000
            });
            return;
        }

        studyState.expediente = expediente;

        if (!conEco) {
            EcoModal.close('eco-modal-expediente-informe-eco');
            abrirModalServicioEco('facturar');
            return;
        }

        var info = byId('eco-modal-paciente-info');
        var ageText = patientState.age ? ' · ' + patientState.age + ' anos' : '';
        var expText = expediente === 'infantil' ? 'Expediente infantil' : 'Expediente adulto';
        if (info) info.textContent = 'Paciente: ' + (patientState.name || '-') + ageText + ' · ' + expText;
        EcoModal.close('eco-modal-expediente-informe-eco');
        EcoModal.open('eco-modal-seleccionar-ecografia-eco');
    }

    // ─────────────────────────────────────────────────────────────────────
    // Modal de servicios sin ecografía (consulta / citología / procesamiento).
    // Modo 'facturar': asienta el cobro (flujo sin estudio).
    // Modo 'registro': solo registra la atención (la facturación va con el
    // informe del estudio que está abierto debajo).
    // ─────────────────────────────────────────────────────────────────────
    var _servicioCtx = { mode: 'facturar' };

    var _servicioAcciones = {
        consulta:      'Realizar consulta',
        citologia:     'Realizar citología',
        procesamiento: 'Realizar procesamiento de muestra'
    };
    function _ecoServicioAccionLabel(keys) {
        keys = keys || [];
        if (keys.length === 1) {
            var cat = _ecoServiciosCatalogo();
            return _servicioAcciones[keys[0]] ||
                ('Realizar ' + ((cat[keys[0]] && cat[keys[0]].label) || 'servicio').toLowerCase());
        }
        return 'Realizar servicios (' + keys.length + ')';
    }

    function abrirModalServicioEco(mode) {
        if (!byId('eco-modal-servicio-eco') || !window.EcoModal) return;
        _servicioCtx.mode = (mode === 'registro') ? 'registro' : 'facturar';
        var keys = studyState.servicios || [];
        if (!keys.length) return;

        var cat = _ecoServiciosCatalogo();
        var titulo = byId('eco-servicio-title');
        var pacienteEl = byId('eco-servicio-paciente');
        var lineas = byId('eco-servicio-lineas');
        var totalWrap = byId('eco-servicio-total-wrap');
        var totalEl = byId('eco-servicio-total');
        var factHead = byId('eco-servicio-fact-head');
        var notaReg = byId('eco-servicio-nota-registro');
        var obs = byId('eco-servicio-obs');
        var obsReq = byId('eco-servicio-obs-req');
        var volver = byId('eco-servicio-volver');
        var btnTxt = byId('eco-servicio-guardar-txt');
        var fb = byId('eco-servicio-feedback');

        var esRegistro = _servicioCtx.mode === 'registro';
        var tituloTxt = keys.length === 1 && cat[keys[0]]
            ? cat[keys[0]].label
            : 'Registro de servicios';
        if (titulo) titulo.innerHTML = '<i class="fa-solid fa-stethoscope" style="color:var(--accent);margin-right:7px;"></i>' + esc(tituloTxt);
        if (pacienteEl) pacienteEl.textContent = 'Paciente: ' + (patientState.name || '—');

        if (lineas) {
            lineas.innerHTML = keys.map(function (k) {
                var s = cat[k] || { label: k, price: 0 };
                return '<li><span>' + esc(s.label) + '</span><b>' + _ecoMoney(s.price) + '</b></li>';
            }).join('');
        }
        var calc = _ecoCalcBundleMulti([], keys);
        if (totalEl) totalEl.textContent = _ecoMoney(calc.total);
        if (totalWrap) totalWrap.style.display = esRegistro ? 'none' : '';
        if (factHead) factHead.textContent = esRegistro ? 'Servicios de esta atención' : 'Facturación de servicios';
        if (notaReg) notaReg.style.display = esRegistro ? '' : 'none';
        if (obs) obs.value = '';
        if (obsReq) obsReq.textContent = esRegistro ? '(requerido)' : '(opcional)';
        if (volver) volver.style.display = esRegistro ? 'none' : '';
        if (btnTxt) btnTxt.textContent = esRegistro ? 'Guardar registro' : 'Guardar y facturar';
        if (fb) { fb.style.display = 'none'; fb.innerHTML = ''; }

        EcoModal.open('eco-modal-servicio-eco');
    }

    function _servicioFeedback(msg, esError) {
        var fb = byId('eco-servicio-feedback');
        if (!fb) return;
        fb.style.display = '';
        fb.style.background = esError ? 'rgba(239,68,68,.1)' : 'rgba(34,197,94,.1)';
        fb.style.color = esError ? '#b91c1c' : '#15803d';
        fb.textContent = msg;
    }

    function guardarServicioEco() {
        var keys = studyState.servicios || [];
        if (!keys.length) return;
        var esRegistro = _servicioCtx.mode === 'registro';
        var obs = byId('eco-servicio-obs');
        var obsVal = obs ? obs.value.trim() : '';
        if (esRegistro && obsVal === '') {
            _servicioFeedback('Escribe el motivo u observaciones de la atención para registrarla.', true);
            return;
        }

        var btn = byId('eco-servicio-guardar');
        var prev = btn ? btn.innerHTML : '';
        if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Guardando…'; }

        fetch((window.ECO_BASE || '') + 'api/facturar_servicios.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                paciente_id: patientState.id,
                servicios: keys,
                observaciones: obsVal,
                facturar: esRegistro ? 0 : 1
            })
        })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d || !d.success) {
                    _servicioFeedback((d && d.message) || 'No se pudo guardar. Inténtalo de nuevo.', true);
                    return;
                }
                ecoToast({ type: 'success', title: esRegistro ? 'Atención registrada' : 'Servicios facturados', message: d.message || '', duration: 6000 });
                EcoModal.close('eco-modal-servicio-eco');
                if (obsVal !== '') {
                    document.dispatchEvent(new CustomEvent('eco:notas-changed', { detail: { pacienteId: patientState.id, action: 'add' } }));
                }
                if (!esRegistro) {
                    // El cobro del día cambió: refrescar los detalles la próxima vez.
                    patientState._stale = true;
                    pacienteEcoCacheInvalidate(patientState.id);
                    document.dispatchEvent(new CustomEvent('eco:facturacion-changed', { detail: { pacienteId: patientState.id } }));
                    if (patientState.id) window.abrirGestionPacienteEco(patientState.id);
                }
                // En modo registro el formulario del estudio sigue abierto debajo.
            })
            .catch(function () { _servicioFeedback('Error de red. Inténtalo de nuevo.', true); })
            .finally(function () { if (btn) { btn.disabled = false; btn.innerHTML = prev; } });
    }

    function volverDeServicioAExpediente() {
        EcoModal.close('eco-modal-servicio-eco');
        EcoModal.open('eco-modal-expediente-informe-eco');
    }

    function volverAExpediente() {
        EcoModal.close('eco-modal-seleccionar-ecografia-eco');
        EcoModal.open('eco-modal-expediente-informe-eco');
    }

    function volverATiposEcografia() {
        EcoModal.close('eco-modal-formulario-estudio-eco');
        var fb = byId('modal-form-eco-feedback');
        if (fb) {
            fb.style.display = 'none';
            fb.innerHTML = '';
        }
        // Si veníamos del sub-modal musculo, regresamos a él
        if (studyState.fromSubMusculo) {
            EcoModal.open('eco-modal-seleccionar-musculo-eco');
            return;
        }
        // Si veníamos del sub-modal obstétrico, regresamos a él
        if (studyState.fromSubObstetrica) {
            EcoModal.open('eco-modal-seleccionar-obstetrica-eco');
            return;
        }
        // Si veníamos del sub-modal partes blandas, regresamos a él
        if (studyState.fromSubPartesBlandas) {
            EcoModal.open('eco-modal-seleccionar-pblandas-eco');
            return;
        }
        EcoModal.open('eco-modal-seleccionar-ecografia-eco');
    }

    function cerrarFormularioEstudioEco() {
        EcoModal.close('eco-modal-formulario-estudio-eco');
        var fb = byId('modal-form-eco-feedback');
        var bodyEl = byId('modal-form-eco-body');
        if (fb) {
            fb.style.display = 'none';
            fb.innerHTML = '';
        }
        if (bodyEl) {
            bodyEl.innerHTML =
                '<div class="modal-form-eco-loader"><i class="fa-solid fa-spinner fa-spin"></i><p>Cargando formulario…</p></div>';
        }
        studyState.ultimoInformeId = null;
        if (patientState.id) window.abrirGestionPacienteEco(patientState.id);
    }

    function _imprimirInformeEnIframeEco(informeId) {
        if (!informeId) return;
        var prev = document.getElementById('eco-print-frame');
        if (prev) prev.remove();
        var iframe = document.createElement('iframe');
        iframe.id = 'eco-print-frame';
        iframe.setAttribute('aria-hidden', 'true');
        iframe.style.cssText = 'position:fixed;left:-10000px;top:0;width:8.5in;height:11in;border:0;visibility:hidden;';
        iframe.src = (window.ECO_BASE || '') + 'informe/' + encodeURIComponent(informeId) + '?print=1';
        document.body.appendChild(iframe);
        setTimeout(function () { try { iframe.remove(); } catch (e) {} }, 60000);
    }

    /** Abre el modal que obliga a firmar antes de imprimir un informe no firmado. */
    function _pedirFirmaAntesImprimir(informeId) {
        if (!informeId || !window.EcoModal) return;
        _firmarAntesCtx = { informeId: informeId };
        EcoModal.open('eco-modal-firmar-antes-eco');
    }

    function imprimirInformeEco() {
        var id = studyState.ultimoInformeId;
        if (!id) return;
        // En el formulario el informe queda 'finalizado' (sin firmar) al guardar.
        if (studyState.ultimoInformeFirmado) {
            _imprimirInformeEnIframeEco(id);
        } else {
            _pedirFirmaAntesImprimir(id);
        }
    }

    function seleccionarTipoEcografia(btn) {
        var codigo = btn.getAttribute('data-eco-tipo-codigo') || '';

        // Interceptar Musculoesquelética padre → abrir sub-selector
        if (codigo === 'ECO_MUSCU') {
            EcoModal.close('eco-modal-seleccionar-ecografia-eco');
            var infoP = document.getElementById('eco-musculo-paciente-info');
            if (infoP) {
                infoP.textContent = patientState.name
                    ? 'Paciente: ' + patientState.name + ' · Seleccione la articulación a estudiar'
                    : 'Seleccione la articulación a estudiar';
            }
            EcoModal.open('eco-modal-seleccionar-musculo-eco');
            studyState.fromSubMusculo = true;
            return;
        }

        // Interceptar Obstétrica padre → abrir sub-selector de trimestre
        if (codigo === 'eco_obstetrica') {
            EcoModal.close('eco-modal-seleccionar-ecografia-eco');
            var infoO = document.getElementById('eco-obstetrica-paciente-info');
            if (infoO) {
                infoO.textContent = patientState.name
                    ? 'Paciente: ' + patientState.name + ' · Seleccione el trimestre del estudio'
                    : 'Seleccione el trimestre del estudio';
            }
            EcoModal.open('eco-modal-seleccionar-obstetrica-eco');
            studyState.fromSubObstetrica = true;
            return;
        }

        // Interceptar Partes Blandas padre → abrir sub-selector
        if (codigo === 'ECO_PBLANCAS') {
            EcoModal.close('eco-modal-seleccionar-ecografia-eco');
            var infoP = document.getElementById('eco-pblandas-paciente-info');
            if (infoP) {
                infoP.textContent = patientState.name
                    ? 'Paciente: ' + patientState.name + ' · Seleccione el tipo de estudio'
                    : 'Seleccione el tipo de estudio';
            }
            EcoModal.open('eco-modal-seleccionar-pblandas-eco');
            studyState.fromSubPartesBlandas = true;
            return;
        }

        studyState.tipoId = btn.getAttribute('data-eco-tipo-id');
        studyState.tipoNombre = btn.getAttribute('data-eco-tipo-nombre') || 'Formulario de estudio';
        studyState.tipoIcono = btn.getAttribute('data-eco-tipo-icono') || 'fa-solid fa-wave-square';
        studyState.tipoPrecio = parseFloat(btn.getAttribute('data-eco-tipo-precio')) || 0;
        abrirFormularioEstudioEco();
    }

    function seleccionarSubMusculoEco(btn) {
        studyState.tipoId = btn.getAttribute('data-eco-tipo-id');
        studyState.tipoNombre = btn.getAttribute('data-eco-tipo-nombre') || 'Formulario de estudio';
        studyState.tipoIcono = btn.getAttribute('data-eco-tipo-icono') || 'fa-solid fa-bone';
        studyState.tipoPrecio = parseFloat(btn.getAttribute('data-eco-tipo-precio')) || 0;
        EcoModal.close('eco-modal-seleccionar-musculo-eco');
        abrirFormularioEstudioEco();
    }

    function seleccionarSubObstetricaEco(btn) {
        studyState.tipoId = btn.getAttribute('data-eco-tipo-id');
        studyState.tipoNombre = btn.getAttribute('data-eco-tipo-nombre') || 'Formulario de estudio';
        studyState.tipoIcono = btn.getAttribute('data-eco-tipo-icono') || 'fa-solid fa-baby';
        studyState.tipoPrecio = parseFloat(btn.getAttribute('data-eco-tipo-precio')) || 0;
        EcoModal.close('eco-modal-seleccionar-obstetrica-eco');
        abrirFormularioEstudioEco();
    }

    function seleccionarSubPartesBlandasEco(btn) {
        studyState.tipoId = btn.getAttribute('data-eco-tipo-id');
        studyState.tipoNombre = btn.getAttribute('data-eco-tipo-nombre') || 'Formulario de estudio';
        studyState.tipoIcono = btn.getAttribute('data-eco-tipo-icono') || 'fa-solid fa-hand-holding-medical';
        studyState.tipoPrecio = parseFloat(btn.getAttribute('data-eco-tipo-precio')) || 0;
        EcoModal.close('eco-modal-seleccionar-pblandas-eco');
        abrirFormularioEstudioEco();
    }

    function abrirFormularioEstudioEco(opts) {
        opts = opts || {};
        var editInformeId = opts.informeId || 0;
        var body = byId('modal-form-eco-body');
        var title = byId('modal-form-eco-titulo');
        var patient = byId('modal-form-eco-paciente');
        var icon = byId('modal-form-eco-icon');
        var feedbackEl = byId('modal-form-eco-feedback');
        if (!body) return;
        if (!editInformeId && (!studyState.tipoId || !patientState.id)) return;

        if (feedbackEl) {
            feedbackEl.style.display = 'none';
            feedbackEl.innerHTML = '';
        }
        if (title) title.textContent = studyState.tipoNombre || 'Formulario de estudio';
        if (patient) patient.textContent = 'Paciente: ' + (patientState.name || '—');
        if (icon) icon.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
        body.innerHTML =
            '<div class="modal-form-eco-loader"><i class="fa-solid fa-spinner fa-spin"></i><p>Cargando formulario…</p></div>';
        EcoModal.close('eco-modal-seleccionar-ecografia-eco');
        EcoModal.open('eco-modal-formulario-estudio-eco');

        var formUrl = editInformeId
            ? (window.ECO_BASE || '') + 'api/get_form_ecografia.php?informe_id=' + encodeURIComponent(editInformeId)
            : (window.ECO_BASE || '') + 'api/get_form_ecografia.php?paciente_id=' + encodeURIComponent(patientState.id) + '&tipo_id=' + encodeURIComponent(studyState.tipoId);

        fetch(formUrl)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.error) {
                    body.innerHTML = '<p style="color:#c0392b;padding:20px;">' + esc(data.error) + '</p>';
                    if (icon) icon.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i>';
                    return;
                }
                var tipo = data.tipo || {};
                if (title) title.textContent = tipo.nombre || studyState.tipoNombre || 'Formulario de estudio';
                if (icon) {
                    icon.innerHTML = '<i class="' +
                        esc(tipo.icono || studyState.tipoIcono || 'fa-solid fa-wave-square') +
                        '"></i>';
                }

                body.innerHTML =
                    '<form id="eco-form-estudio-dinamico" autocomplete="off">' +
                    '<input type="hidden" name="paciente_id" value="' +
                    esc(data.paciente && data.paciente.id ? data.paciente.id : patientState.id) + '">' +
                    '<input type="hidden" name="tipo_ecografia_id" value="' + esc(tipo.id || studyState.tipoId) + '">' +
                    '<input type="hidden" name="esquema_version" value="' + esc(tipo.esquema_version || '') + '">' +
                    '<input type="hidden" name="tipo_expediente" value="' + esc(studyState.expediente || '') + '">' +
                    '<input type="hidden" name="informe_id" value="' + esc((data.informe && data.informe.id) || editInformeId || '') + '">' +
                    (!editInformeId
                        ? '<input type="hidden" name="servicios" value="' + esc((studyState.servicios || []).join(',')) + '">'
                        : '') +
                    (data.html || '') +
                    // Facturación al final, debajo de la Conclusión.
                    (!editInformeId ? _ecoFacturaBannerHTML() : '') +
                    '<div class="modal-form-eco-actions">' +
                    // Botón contextual: si el flujo trae servicios (consulta, citología,
                    // procesamiento), permite registrar esa atención sin salir del informe.
                    ((!editInformeId && (studyState.servicios || []).length)
                        ? '<button type="button" class="eco-btn-cancel" id="eco-servicio-desde-form" style="color:#0369a1;border-color:#7dd3fc;">' +
                          '<i class="fa-solid fa-stethoscope"></i> ' + esc(_ecoServicioAccionLabel(studyState.servicios)) + '</button>'
                        : '') +
                    '<button type="button" class="eco-btn-cancel" id="eco-cancelar-estudio">' +
                    '<i class="fa-solid fa-xmark"></i> Cancelar</button>' +
                    '<button type="button" class="eco-btn-cancel" id="eco-borrador-estudio">' +
                    '<i class="fa-regular fa-floppy-disk"></i> Guardar borrador</button>' +
                    '<button type="submit" class="eco-btn-submit" id="eco-submit-estudio">' +
                    '<i class="fa-solid fa-circle-check"></i> Finalizar informe</button>' +
                    '<button type="button" class="eco-btn-imprimir" id="eco-imprimir-estudio" disabled ' +
                    'title="Disponible después de finalizar el informe">' +
                    '<i class="fa-solid fa-print"></i> Imprimir</button>' +
                    '</div>' +
                    '</form>';

                var form = byId('eco-form-estudio-dinamico');
                if (form) {
                    form.addEventListener('submit', function (e) { guardarInformeEstudioEco(e, 'finalizar'); });
                    form.addEventListener('change', manejarCamposCondicionales);
                }
                var cancel = byId('eco-cancelar-estudio');
                if (cancel) cancel.addEventListener('click', cerrarFormularioEstudioEco);
                var servDesdeForm = byId('eco-servicio-desde-form');
                if (servDesdeForm) servDesdeForm.addEventListener('click', function () { abrirModalServicioEco('registro'); });
                var borrador = byId('eco-borrador-estudio');
                if (borrador) borrador.addEventListener('click', function () { guardarInformeEstudioEco(null, 'borrador'); });
                var imprimirBtn = byId('eco-imprimir-estudio');
                if (imprimirBtn) imprimirBtn.addEventListener('click', imprimirInformeEco);
            })
            .catch(function (e) {
                body.innerHTML =
                    '<p style="color:#c0392b;padding:20px;">Error de red: ' + esc(e.message) + '</p>';
                if (icon) icon.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i>';
            });
    }

    function manejarCamposCondicionales(event) {
        var input = event.target;
        var form = byId('eco-form-estudio-dinamico');
        if (!form || !input || input.type !== 'radio') return;
        form.querySelectorAll('.campo-condicional').forEach(function (el) {
            if (el.dataset.dependeDe === input.name) {
                el.style.display = input.value === el.dataset.dependeValor ? '' : 'none';
            }
        });
    }

    function guardarInformeEstudioEco(event, accion) {
        if (event) event.preventDefault();
        var form = byId('eco-form-estudio-dinamico');
        if (!form) return;
        accion = accion === 'borrador' ? 'borrador' : 'finalizar';

        var submitBtn = form.querySelector('.eco-btn-submit');
        var draftBtn  = byId('eco-borrador-estudio');
        var actingBtn = accion === 'borrador' ? draftBtn : submitBtn;
        var feedbackEl = byId('modal-form-eco-feedback');

        var draftLabel  = '<i class="fa-regular fa-floppy-disk"></i> Guardar borrador';
        var finalLabel  = '<i class="fa-solid fa-circle-check"></i> Finalizar informe';
        function restoreButtons() {
            if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = finalLabel; }
            if (draftBtn)  { draftBtn.disabled = false;  draftBtn.innerHTML = draftLabel; }
        }

        if (submitBtn) submitBtn.disabled = true;
        if (draftBtn)  draftBtn.disabled = true;
        if (actingBtn) actingBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Guardando…';
        if (feedbackEl) { feedbackEl.style.display = 'none'; feedbackEl.innerHTML = ''; }

        var fd = new FormData(form);
        fd.set('accion', accion);

        fetch((window.ECO_BASE || '') + 'api/guardar_informe_estudio.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (feedbackEl) feedbackEl.style.display = 'block';
                if (data.success) {
                    studyState.ultimoInformeId = data.informe_id;
                    studyState.ultimoInformeFirmado = false; // guardado/finalizado => aún sin firmar
                    // Persistir el id para que el siguiente guardado actualice el mismo informe.
                    var hid = form.querySelector('input[name="informe_id"]');
                    if (hid) hid.value = data.informe_id || '';

                    if (feedbackEl) {
                        var _infId = data.informe_id || '';
                        /* Imprimir solo cuando se finaliza, igual que el boton
                           del pie: un borrador no se imprime. Reusa la misma
                           funcion, con su paso previo de firma. */
                        feedbackEl.innerHTML =
                            '<div class="eco-msg-ok"><i class="fa-solid fa-circle-check"></i> ' +
                            esc(data.message || 'Informe guardado.') +
                            ' &nbsp;—&nbsp; <span class="eco-msg-ok__acciones">' +
                            '<button type="button" class="eco-msg-ok__link" data-ver-informe="' +
                            esc(String(_infId)) + '">Ver informe <i class="fa-solid fa-arrow-right"></i></button>' +
                            (accion === 'finalizar'
                                ? '<button type="button" class="eco-msg-ok__link" data-imprimir-informe="' +
                                  esc(String(_infId)) + '"><i class="fa-solid fa-print"></i> Imprimir</button>'
                                : '') +
                            '</span></div>';
                        var _verBtn = feedbackEl.querySelector('[data-ver-informe]');
                        if (_verBtn) {
                            _verBtn.addEventListener('click', function () {
                                if (window.EcoModal) EcoModal.close('eco-modal-formulario-estudio-eco');
                                if (typeof window.abrirDetalleInformeEco === 'function') {
                                    window.abrirDetalleInformeEco(_infId);
                                }
                            });
                        }
                        var _impBtn = feedbackEl.querySelector('[data-imprimir-informe]');
                        if (_impBtn) {
                            _impBtn.addEventListener('click', imprimirInformeEco);
                        }
                    }
                    document.dispatchEvent(new CustomEvent('eco:informes-changed', {
                        detail: { pacienteId: patientState.id, informeId: data.informe_id }
                    }));
                    // Forzar refetch de servicios_hoy al reabrir el expediente (ya se facturo).
                    patientState._stale = true;
                    pacienteEcoCacheInvalidate(patientState.id);

                    if (accion === 'finalizar') {
                        if (submitBtn) {
                            submitBtn.innerHTML = '<i class="fa-solid fa-circle-check"></i> Finalizado';
                            submitBtn.classList.add('eco-btn-submit--saved');
                        }
                        if (draftBtn) draftBtn.style.display = 'none';
                        var imprimirBtn = byId('eco-imprimir-estudio');
                        if (imprimirBtn) {
                            imprimirBtn.disabled = false;
                            imprimirBtn.removeAttribute('title');
                            imprimirBtn.classList.add('eco-btn-imprimir--ready');
                        }
                    } else {
                        // Borrador guardado: permitir seguir editando.
                        restoreButtons();
                    }
                } else if (feedbackEl) {
                    feedbackEl.innerHTML =
                        '<div class="eco-msg-err"><i class="fa-solid fa-triangle-exclamation"></i> ' +
                        esc(data.message || 'No se pudo guardar.') + '</div>';
                    restoreButtons();
                }
                var bodyScroll = byId('modal-form-eco-body');
                if (bodyScroll && typeof bodyScroll.scrollTo === 'function') {
                    bodyScroll.scrollTo({ top: 0, behavior: 'smooth' });
                }
            })
            .catch(function (e) {
                if (feedbackEl) {
                    feedbackEl.style.display = 'block';
                    feedbackEl.innerHTML =
                        '<div class="eco-msg-err">Error de red: ' + esc(e.message) + '</div>';
                }
                restoreButtons();
                var bodyScroll = byId('modal-form-eco-body');
                if (bodyScroll && typeof bodyScroll.scrollTo === 'function') {
                    bodyScroll.scrollTo({ top: 0, behavior: 'smooth' });
                }
            });
    }

    /** Abre el formulario en modo edicion para un informe (borrador o finalizado). */
    window.editarInformeEstudioEco = function (informeId) {
        if (!window.EcoModal) return;
        EcoModal.close('eco-modal-informe-detalle-eco');
        EcoModal.close('eco-modal-informes-paciente-eco');
        abrirFormularioEstudioEco({ informeId: informeId });
    };

    window.abrirDetalleInformeEco = function (informeId) {
        var body = byId('eco-informe-detalle-body');
        var iconEl = byId('eco-inf-det-icon');
        var tituloEl = byId('eco-inf-det-titulo');
        var pacienteEl = byId('eco-inf-det-paciente');
        if (!body || !iconEl || !tituloEl || !pacienteEl || !window.EcoModal) return;

        _currentInformeDetalleEcoId = informeId;
        EcoModal.open('eco-modal-informe-detalle-eco');

        var cached = informeEcoCacheGet(informeId);
        if (cached) {
            aplicarInformeEcoDetalleDOM(cached, body, iconEl, tituloEl, pacienteEl);
            return;
        }

        iconEl.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
        tituloEl.textContent = 'Cargando…';
        pacienteEl.textContent = '';
        body.innerHTML =
            '<div class="modal-form-eco-loader"><i class="fa-solid fa-spinner fa-spin"></i><p>Cargando informe…</p></div>';

        fetchInformeDetalleEcoPayload(informeId)
            .then(function (data) {
                if (!data.error) {
                    informeEcoCacheSet(informeId, data);
                }
                aplicarInformeEcoDetalleDOM(data, body, iconEl, tituloEl, pacienteEl);
            })
            .catch(function (e) {
                body.innerHTML =
                    '<p style="color:#c0392b;padding:20px;">Error al cargar: ' +
                    esc(e && e.message ? e.message : 'Error de red.') +
                    '</p>';
                iconEl.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i>';
            });
    };

    /* Al cerrar "Ver informe" o "Seleccionar tipo de expediente",
       volver al modal de Gestionar paciente. */
    var _volverGestionIds = ['eco-modal-informes-paciente-eco', 'eco-modal-informe-detalle-eco', 'eco-modal-expediente-informe-eco', 'eco-modal-historia-clinica-eco', 'eco-modal-facturacion-paciente-eco'];
    var _volviendoGestion = false;
    var _programarDesdeGestion = false;
    function _volverAGestionEco() {
        if (_volviendoGestion) return;
        if (!patientState || !patientState.id || !window.EcoModal || !window.abrirGestionPacienteEco) return;
        _volviendoGestion = true;
        _programarDesdeGestion = false;
        var pid = patientState.id;
        // El cierre y la reapertura se hacen en el mismo frame: antes iban en un
        // setTimeout que sumaba un salto de tarea visible al volver atrás.
        requestAnimationFrame(function () {
            EcoModal.close('eco-modal-informe-detalle-eco');
            EcoModal.close('eco-modal-expediente-informe-eco');
            EcoModal.close('eco-modal-informes-paciente-eco');
            EcoModal.close('eco-modal-programar-cita-eco');
            window.abrirGestionPacienteEco(pid);
            // Guarda antirrebote alineada con la duración real de la animación.
            setTimeout(function () { _volviendoGestion = false; }, 150);
        });
    }

    // 1) Clic en la X de esos modales
    document.addEventListener('click', function (e) {
        var closer = (e.target && e.target.closest) ? e.target.closest('[data-eco-modal-close]') : null;
        if (!closer) return;
        var modal = closer.closest('.eco-modal');
        if (!modal) return;
        if (_volverGestionIds.indexOf(modal.id) !== -1) {
            _volverAGestionEco();
        } else if (modal.id === 'eco-modal-programar-cita-eco' && _programarDesdeGestion) {
            // Solo si "Programar cita" se abrió desde Gestionar paciente
            _volverAGestionEco();
        }
    });

    // 2) Respaldo: observar el cierre del modal de detalle por cualquier vía
    function _initVolverObservers() {
        if (typeof MutationObserver === 'undefined') return;
        var el = byId('eco-modal-informe-detalle-eco');
        if (el && !el._volObs) {
            el._volObs = true;
            var wasOpen = el.classList.contains('eco-modal--open');
            new MutationObserver(function () {
                var isOpen = el.classList.contains('eco-modal--open');
                if (wasOpen && !isOpen) _volverAGestionEco();
                wasOpen = isOpen;
            }).observe(el, { attributes: true, attributeFilter: ['class', 'aria-hidden'] });
        }
        // Respaldo para "Programar cita" (cierre por ESC u otra vía)
        var prog = byId('eco-modal-programar-cita-eco');
        if (prog && !prog._volObs) {
            prog._volObs = true;
            var progOpen = prog.classList.contains('eco-modal--open');
            new MutationObserver(function () {
                var isOpen = prog.classList.contains('eco-modal--open');
                if (progOpen && !isOpen && _programarDesdeGestion) _volverAGestionEco();
                progOpen = isOpen;
            }).observe(prog, { attributes: true, attributeFilter: ['class', 'aria-hidden'] });
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', _initVolverObservers);
    } else {
        _initVolverObservers();
    }

    document.addEventListener('eco:informes-changed', function (ev) {
        var d = ev && ev.detail ? ev.detail : {};
        if (d.informeId != null && String(d.informeId).length) {
            informeEcoCacheInvalidate(d.informeId);
        }
        // El contador de informes del modal de gestión quedó desfasado.
        pacienteEcoCacheInvalidate(d.pacienteId);
    });

    /* Un cobro cambia los saldos del paciente: descarta su copia en caché para
       que la próxima apertura traiga datos frescos. */
    document.addEventListener('eco:facturacion-changed', function (ev) {
        var d = ev && ev.detail ? ev.detail : {};
        pacienteEcoCacheInvalidate(d.pacienteId);
    });

    window.abrirNotasPacienteEco = function (pacienteId, nombre) {
        if (!byId('eco-modal-notas-paciente-eco') || !window.EcoModal) return;
        patientState.id = pacienteId;
        patientState.name = nombre || '';
        byId('eco-notas-paciente-id').value = pacienteId;
        /* El titulo se queda fijo: el paciente se identifica en la tira de
           datos, igual que en el historial de informes. */
        notasSet('eco-notas-strip-name', nombre || '—');
        notasSet('eco-notas-strip-ci', 'CI —');
        notasSet('eco-notas-strip-count', '…');
        var ultWrap = byId('eco-notas-strip-last-wrap');
        if (ultWrap) ultWrap.hidden = true;

        var ahora = new Date();
        var offset = ahora.getTimezoneOffset() * 60000;
        byId('eco-notas-fecha').value = new Date(ahora - offset).toISOString().slice(0, 16);
        byId('eco-notas-contenido').value = '';
        notasContador();
        setError('eco-notas-eco-error', '');
        EcoModal.open('eco-modal-notas-paciente-eco');
        cargarNotasEco();
    };

    function notasSet(id, texto) { var el = byId(id); if (el) el.textContent = texto; }

    function notasContador() {
        var ta = byId('eco-notas-contenido');
        var el = byId('eco-notas-contador');
        if (!ta || !el) return;
        var n = ta.value.length;
        el.textContent = n + ' / 2000';
        el.classList.toggle('is-tope', n >= 1900);
    }

    function cargarNotasEco() {
        var list = byId('eco-notas-list');
        var btnLimpiar = byId('eco-btn-limpiar-notas');
        if (!list) return;

        list.innerHTML = '<p class="eco-modal__body-text">Cargando...</p>';
        ecoGetJson(urlApi('api/get_notas_paciente.php?paciente_id=' + encodeURIComponent(patientState.id)))
            .then(function (data) {
                if (!data.ok) {
                    list.innerHTML = '<p style="color:#b91c1c;">' + esc(data.error || 'Error') + '</p>';
                    if (btnLimpiar) btnLimpiar.style.display = 'none';
                    notasSet('eco-notas-strip-count', '0 notas');
                    return;
                }

                var pac = data.paciente || {};
                if (pac.nombre_completo) notasSet('eco-notas-strip-name', pac.nombre_completo);
                notasSet('eco-notas-strip-ci', pac.cedula ? ('CI ' + pac.cedula) : 'Sin cédula');

                var notas = data.notas || [];
                notasSet('eco-notas-strip-count', notas.length === 1 ? '1 nota' : (notas.length + ' notas'));

                var ultWrap = byId('eco-notas-strip-last-wrap');
                if (ultWrap) {
                    ultWrap.hidden = !notas.length;
                    if (notas.length) notasSet('eco-notas-strip-last', 'Última: ' + formatDate(notas[0].fecha_sesion));
                }

                if (!notas.length) {
                    list.className = 'ns-list';
                    list.innerHTML = '<div class="ns-vacio"><i class="fa-regular fa-note-sticky"></i>' +
                        '<p>Todavía no hay notas de este paciente.<br>La primera que guardes aparecerá aquí.</p></div>';
                    if (btnLimpiar) btnLimpiar.style.display = 'none';
                    return;
                }

                if (btnLimpiar) btnLimpiar.style.display = 'inline-flex';
                list.className = 'ns-list ns-list--llena';
                list.innerHTML = notas.map(function (nota) {
                    var autor = nota.autor
                        ? '<span class="ns-nota__autor"><i class="fa-solid fa-user-doctor"></i> ' + esc(nota.autor) + '</span>'
                        : '';
                    var texto = esc(nota.contenido || '').replace(/\n/g, '<br>');
                    return '<article class="ns-nota">' +
                        '<header class="ns-nota__head">' +
                            '<span class="ns-nota__fecha"><i class="fa-regular fa-calendar"></i> ' + esc(formatDate(nota.fecha_sesion)) + '</span>' +
                            autor +
                        '</header>' +
                        '<p class="ns-nota__body">' + texto + '</p>' +
                    '</article>';
                }).join('');
            })
            .catch(function (e) {
                list.innerHTML = '<p style="color:#b91c1c;">' + esc(e.message) + '</p>';
            });
    }

    window.abrirDetalleCitaEco = function (citaId) {
        var body = byId('eco-detalle-cita-body');
        if (!body || !window.EcoModal) return;

        body.innerHTML = '<p class="eco-modal__body-text">Cargando detalle...</p>';
        EcoModal.open('eco-modal-detalle-cita-eco');

        fetch((window.ECO_BASE || '') + 'api/get_cita_details_psicologo.php?cita_id=' + encodeURIComponent(citaId))
            .then(function (r) { return r.json(); })
            .then(function (resp) {
                if (!resp.success) {
                    body.innerHTML = '<p style="color:#b91c1c;">' + esc(resp.message || resp.error || 'No se pudo cargar la cita.') + '</p>';
                    return;
                }
                var c = resp.data || {};
                var puedeReprogramar = ['confirmada', 'reprogramada'].indexOf(c.estado) !== -1;
                body.innerHTML =
                    '<div style="display:grid;gap:10px;font-size:13px;">' +
                    '<p style="margin:0;"><strong>Paciente:</strong> ' + esc(c.paciente_nombre || '-') + '</p>' +
                    '<p style="margin:0;"><strong>Cedula:</strong> ' + esc(c.paciente_cedula || '-') + '</p>' +
                    '<p style="margin:0;"><strong>Fecha:</strong> ' + esc(formatDate(c.fecha_cita)) + '</p>' +
                    '<p style="margin:0;"><strong>Estado:</strong> ' + esc(c.estado || '-') + '</p>' +
                    (c.tipo_nombre ? '<p style="margin:0;"><strong>Estudio:</strong> ' + esc(c.tipo_nombre) + '</p>' : '') +
                    '<p style="margin:0;"><strong>Motivo:</strong><br><span style="color:var(--text-secondary);">' + esc(c.motivo_consulta || '-') + '</span></p>' +
                    '</div>' +
                    '<div class="eco-tl-wrap"><div class="eco-tl-head"><i class="fa-solid fa-timeline"></i> Línea de tiempo</div>' +
                    '<div id="eco-cita-tl" class="eco-tl-box"><div class="eco-tl-empty"><i class="fa-solid fa-spinner fa-spin"></i></div></div></div>' +
                    '<div class="eco-modal__footer">' +
                    (c.paciente_id ? '<button type="button" class="btn-secondary" id="eco-detalle-gestionar-paciente"><i class="fa-solid fa-folder-open"></i> Gestionar paciente</button>' : '') +
                    (puedeReprogramar ? '<button type="button" class="btn-primary" id="eco-detalle-reprogramar-cita"><i class="fa-solid fa-calendar-pen"></i> Reprogramar</button>' : '') +
                    '</div>';

                var btnGestion = byId('eco-detalle-gestionar-paciente');
                if (btnGestion) {
                    btnGestion.addEventListener('click', function () {
                        window.abrirGestionPacienteEco(c.paciente_id);
                    });
                }

                var btnReprogramar = byId('eco-detalle-reprogramar-cita');
                if (btnReprogramar) {
                    btnReprogramar.addEventListener('click', function () {
                        window.abrirReprogramarCitaEco(c.id, c.paciente_nombre || '-', formatDate(c.fecha_cita));
                    });
                }

                // Linea de tiempo de la cita (Fase 4C)
                var tlBox = byId('eco-cita-tl');
                if (tlBox) {
                    fetch((window.ECO_BASE || '') + 'api/get_cita_timeline.php?cita_id=' + encodeURIComponent(citaId))
                        .then(function (r) { return r.json(); })
                        .then(function (t) { tlBox.innerHTML = (t && t.success) ? t.html : ''; })
                        .catch(function () { tlBox.innerHTML = ''; });
                }
            })
            .catch(function () {
                body.innerHTML = '<p style="color:#b91c1c;">No se pudo cargar el detalle de la cita.</p>';
            });
    };

    /* --- Estudios, servicios y total de "Nueva cita" ---
       El total lo calcula el servidor: precios y promociones viven en
       lib/facturacion y no se duplican aqui. */
    var _ecoProgMontoManual = false;

    function ecoProgRecalcular() {
        var resumenEl = byId('eco-prog-estudios-resumen');
        var notaEl = byId('eco-prog-monto-sugerido');
        var montoEl = byId('eco-prog-monto');
        if (!resumenEl && !montoEl) return;

        var tipos = [], nombres = [], servicios = [];
        document.querySelectorAll('[data-ecop-estudio]:checked').forEach(function (c) {
            tipos.push(parseInt(c.value, 10));
            var txt = c.parentElement.querySelector('.mcp-opcion__nombre');
            if (txt) nombres.push(txt.textContent.trim());
        });
        document.querySelectorAll('[data-ecop-servicio]:checked').forEach(function (c) {
            servicios.push(c.value);
        });

        if (resumenEl) {
            resumenEl.textContent = nombres.length
                ? nombres.length + (nombres.length === 1 ? ' ecografía: ' : ' ecografías: ') + nombres.join(', ')
                : 'Ninguna seleccionada.';
        }

        if (!tipos.length && !servicios.length) {
            if (notaEl) notaEl.textContent = 'Se calcula solo al elegir estudios y servicios. Puedes cambiarlo.';
            if (montoEl && !_ecoProgMontoManual) montoEl.value = '';
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
            if (montoEl && !_ecoProgMontoManual) montoEl.value = res.total > 0 ? res.total.toFixed(2) : '';
            if (notaEl) {
                notaEl.textContent = 'Sugerido: ' + res.total_texto
                    + (res.promos && res.promos.length ? ' · ' + res.promos.join(' · ') : '')
                    + (_ecoProgMontoManual ? ' (monto modificado a mano)' : '');
            }
        })
        .catch(function () {
            if (notaEl) notaEl.textContent = 'No se pudo calcular el total. Escribe el monto a mano.';
        });
    }

    document.querySelectorAll('[data-ecop-estudio], [data-ecop-servicio]').forEach(function (c) {
        c.addEventListener('change', ecoProgRecalcular);
    });

    /* Filtro del catalogo de estudios. Un estudio ya marcado no se oculta
       nunca: si desapareciera del filtro se guardaria una seleccion que no
       se ve. Las cabeceras de categoria se ocultan si se quedan sin filas. */
    function ecoProgFiltrar() {
        var caja = byId('eco-prog-estudios');
        var q = byId('eco-prog-buscar');
        if (!caja || !q) return;

        var texto = q.value.trim().toLowerCase();
        var visibles = 0, total = 0;
        var cat = null, catVisibles = 0;

        Array.prototype.forEach.call(caja.children, function (el) {
            if (el.hasAttribute('data-ecop-cat')) {
                if (cat) cat.classList.toggle('pcx-oculto', catVisibles === 0);
                cat = el;
                catVisibles = 0;
                return;
            }
            var inp = el.querySelector('[data-ecop-estudio]');
            var coincide = texto === ''
                || (el.getAttribute('data-ecop-busca') || '').indexOf(texto) !== -1
                || (inp && inp.checked);
            el.classList.toggle('pcx-oculto', !coincide);
            total++;
            if (coincide) { visibles++; catVisibles++; }
        });
        if (cat) cat.classList.toggle('pcx-oculto', catVisibles === 0);

        var cuenta = byId('eco-prog-visibles');
        if (cuenta) cuenta.textContent = visibles + ' de ' + total;

        var aviso = byId('eco-prog-sin-resultados');
        if (aviso) aviso.hidden = visibles !== 0;
    }

    var _ecoProgBuscar = byId('eco-prog-buscar');
    if (_ecoProgBuscar) {
        _ecoProgBuscar.addEventListener('input', ecoProgFiltrar);
        /* Enter dentro del buscador enviaria el formulario. */
        _ecoProgBuscar.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); }
            if (e.key === 'Escape') { _ecoProgBuscar.value = ''; ecoProgFiltrar(); }
        });
    }
    var _ecoProgMontoEl = byId('eco-prog-monto');
    if (_ecoProgMontoEl) {
        _ecoProgMontoEl.addEventListener('input', function () { _ecoProgMontoManual = true; });
    }

    window.abrirProgramarCitaEco = function (pacienteId, pacienteNombre, opts) {
        opts = opts || {};
        if (!window.EcoModal) return;

        var idInp = byId('eco-prog-paciente-id');
        var nameEl = byId('eco-prog-paciente-nombre');
        var form = byId('eco-form-programar-cita-eco');
        if (!idInp || !nameEl || !form || !byId('eco-modal-programar-cita-eco')) return;

        // Recordar si se abrió desde Gestionar paciente para volver a él al cerrar
        _programarDesdeGestion = !!opts.fromGestion;

        if (opts.fromGestion) {
            EcoModal.close('eco-modal-gestionar-paciente-eco');
        }

        idInp.value = pacienteId;
        nameEl.textContent = pacienteNombre || '—';
        patientState.id = pacienteId;
        patientState.name = pacienteNombre || '';

        form.reset();
        idInp.value = pacienteId;
        nameEl.textContent = pacienteNombre || '—';
        setError('eco-prog-cita-error', '');
        _ecoProgMontoManual = false;
        ecoProgRecalcular();
        ecoProgFiltrar();   // form.reset() vacia el buscador; hay que repintar la lista

        EcoModal.open('eco-modal-programar-cita-eco');

        var fechaIn = byId('eco-prog-fecha-eco');
        if (fechaIn && window.flatpickr) {
            if (fechaIn._flatpickr) fechaIn._flatpickr.destroy();
            fechaIn.value = '';
            flatpickr(fechaIn, {
                enableTime: true,
                dateFormat: 'Y-m-d H:i',
                altInput: true,
                altFormat: 'd/m/Y h:i K',
                locale: window.flatpickr.l10ns && window.flatpickr.l10ns.es ? window.flatpickr.l10ns.es : 'es',
                minuteIncrement: 15
            });
        }
    };

    window.cerrarProgramarCitaEco = function () {
        var fechaIn = byId('eco-prog-fecha-eco');
        if (fechaIn && fechaIn._flatpickr) {
            fechaIn._flatpickr.destroy();
        }
        var form = byId('eco-form-programar-cita-eco');
        if (form) form.reset();
        setError('eco-prog-cita-error', '');
        // Si se abrió desde Gestionar paciente, cerrar y volver a ese modal
        if (_programarDesdeGestion) {
            _volverAGestionEco();
        } else if (window.EcoModal) {
            EcoModal.close('eco-modal-programar-cita-eco');
        }
    };

    function repIniciales(nombre) {
        var parts = String(nombre || '').trim().split(/\s+/), out = '';
        for (var i = 0; i < parts.length && out.length < 2; i++) { if (parts[i]) out += parts[i][0].toUpperCase(); }
        return out || '?';
    }
    function repBadge(estado) {
        switch (estado) {
            case 'confirmada': return 'badge-success';
            case 'reprogramada': return 'badge-purple';
            case 'pendiente': case 'pendiente_paciente': return 'badge-warning';
            case 'cancelada': case 'rechazada': return 'badge-danger';
            case 'completada': return 'badge-info';
            default: return 'badge-accent';
        }
    }
    function repSet(id, text) { var el = byId(id); if (el) el.textContent = text; }

    window.abrirReprogramarCitaEco = function (citaId, paciente, fechaActual) {
        if (!byId('eco-modal-reprogramar-cita-eco') || !window.EcoModal) return;
        citaState.id = citaId;
        citaState.paciente = paciente || '-';
        citaState.fecha = fechaActual || '-';

        byId('eco-reprogramar-cita-id').value = citaId;
        byId('eco-reprogramar-paciente').textContent = citaState.paciente;
        byId('eco-reprogramar-fecha-actual').textContent = citaState.fecha;
        byId('eco-reprogramar-motivo').value = '';
        setError('eco-reprogramar-error', '');

        // Reset del panel de información + datos básicos inmediatos
        repSet('eco-rep-avatar', repIniciales(citaState.paciente));
        repSet('eco-rep-sub', '—');
        repSet('eco-rep-estudio', '—');
        repSet('eco-rep-modalidad', '—');
        var repEstado = byId('eco-rep-estado'); if (repEstado) { repEstado.textContent = '—'; repEstado.className = 'badge'; }
        var repMotivoBox = byId('eco-rep-motivo-box'); if (repMotivoBox) repMotivoBox.style.display = 'none';

        // Enriquecer con los detalles completos de la cita
        fetch((window.ECO_BASE || '') + 'api/get_solicitud_details.php?id=' + encodeURIComponent(citaId))
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d || d.error) return;
                var nombre = d.paciente_nombre || citaState.paciente;
                repSet('eco-reprogramar-paciente', nombre);
                repSet('eco-rep-avatar', repIniciales(nombre));
                repSet('eco-rep-sub', (d.paciente_cedula || 'Sin cédula') + (d.paciente_edad ? ' · ' + d.paciente_edad + ' años' : ''));
                repSet('eco-rep-modalidad', d.modalidad_formateada || d.modalidad || '—');
                repSet('eco-rep-estudio', d.tipo_nombre || 'No especificado');
                if (repEstado && d.estado) { repEstado.textContent = d.estado.charAt(0).toUpperCase() + d.estado.slice(1); repEstado.className = 'badge ' + repBadge(d.estado); }
                if (repMotivoBox && d.motivo_consulta) { repMotivoBox.style.display = ''; repSet('eco-rep-motivo-text', d.motivo_consulta); }
            })
            .catch(function () {});

        EcoModal.open('eco-modal-reprogramar-cita-eco');

        var input = byId('eco-reprogramar-calendario');
        if (input) {
            input.value = '';
            if (input._flatpickr) input._flatpickr.destroy();
            if (window.flatpickr) {
                window.flatpickr(input, {
                    enableTime: true,
                    dateFormat: 'Y-m-d H:i',
                    altInput: true,
                    altFormat: 'd/m/Y h:i K',
                    locale: window.flatpickr.l10ns && window.flatpickr.l10ns.es ? window.flatpickr.l10ns.es : 'es',
                    minuteIncrement: 15
                });
            }
        }
    };

    document.addEventListener('DOMContentLoaded', function () {
        var notasTa = byId('eco-notas-contenido');
        if (notasTa) {
            notasTa.addEventListener('input', notasContador);
        }

        var notasForm = byId('eco-form-notas-paciente');
        if (notasForm) {
            notasForm.addEventListener('submit', function (e) {
                e.preventDefault();
                setError('eco-notas-eco-error', '');
                fetch((window.ECO_BASE || '') + 'api/guardar_nota.php', { method: 'POST', body: new FormData(notasForm) })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data.ok) {
                            byId('eco-notas-contenido').value = '';
                            notasContador();
                            cargarNotasEco();
                            document.dispatchEvent(new CustomEvent('eco:notas-changed', {
                                detail: { pacienteId: patientState.id, action: 'add' }
                            }));
                        } else {
                            setError('eco-notas-eco-error', data.error || 'No se pudo guardar');
                        }
                    })
                    .catch(function (e) { setError('eco-notas-eco-error', e.message || 'Error de red'); });
            });
        }

        var limpiar = byId('eco-btn-limpiar-notas');
        if (limpiar) {
            limpiar.addEventListener('click', function () {
                if (!window.confirm('Borrar todas las notas de ' + (patientState.name || 'este paciente') + '?')) return;
                var fd = new FormData();
                fd.append('paciente_id', patientState.id);
                fetch((window.ECO_BASE || '') + 'api/limpiar_notas.php', { method: 'POST', body: fd })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data.ok) {
                            cargarNotasEco();
                            document.dispatchEvent(new CustomEvent('eco:notas-changed', {
                                detail: { pacienteId: patientState.id, action: 'clear' }
                            }));
                        } else {
                            window.alert(data.error || 'Error al borrar');
                        }
                    });
            });
        }

        var progCitaForm = byId('eco-form-programar-cita-eco');
        if (progCitaForm) {
            progCitaForm.addEventListener('submit', function (e) {
                e.preventDefault();
                setError('eco-prog-cita-error', '');
                var btnProg = byId('eco-prog-submit');
                if (btnProg) btnProg.disabled = true;
                fetch((window.ECO_BASE || '') + 'api/guardar_cita_directa.php', { method: 'POST', body: new FormData(progCitaForm) })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (btnProg) btnProg.disabled = false;
                        if (data.success) {
                            _programarDesdeGestion = false;
                            window.cerrarProgramarCitaEco();
                            window.alert(data.message || 'Cita guardada.');
                            window.location.reload();
                        } else {
                            setError('eco-prog-cita-error', data.message || 'No se pudo guardar.');
                        }
                    })
                    .catch(function (err) {
                        if (btnProg) btnProg.disabled = false;
                        setError('eco-prog-cita-error', (err && err.message) ? err.message : 'Error de red.');
                    });
            });
        }

        var reprogramarForm = byId('eco-form-reprogramar-cita');
        if (reprogramarForm) {
            reprogramarForm.addEventListener('submit', function (e) {
                e.preventDefault();
                setError('eco-reprogramar-error', '');
                var btn = byId('eco-reprogramar-submit');
                if (btn) btn.disabled = true;
                fetch((window.ECO_BASE || '') + 'api/actualizar_cita.php', { method: 'POST', body: new FormData(reprogramarForm) })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (btn) btn.disabled = false;
                        if (data.success) window.location.reload();
                        else setError('eco-reprogramar-error', data.message || 'No se pudo reprogramar');
                    })
                    .catch(function (e) {
                        if (btn) btn.disabled = false;
                        setError('eco-reprogramar-error', e.message || 'Error de red');
                    });
            });
        }

        var toolbarInformesNew = byId('eco-informes-toolbar-new');
        if (toolbarInformesNew) {
            toolbarInformesNew.addEventListener('click', function () {
                if (patientState.id) window.abrirFlujoNuevoInformeEco(patientState.id);
            });
        }

        var expAdulto = byId('eco-expediente-adulto');
        if (expAdulto) {
            expAdulto.addEventListener('click', function () {
                seleccionarExpediente('adulto');
            });
        }

        var expInfantil = byId('eco-expediente-infantil');
        if (expInfantil) {
            expInfantil.addEventListener('click', function () {
                seleccionarExpediente('infantil');
            });
        }

        var volverExp = byId('eco-volver-expediente');
        if (volverExp) volverExp.addEventListener('click', volverAExpediente);

        var servGrid = byId('eco-exp-serv-grid');
        if (servGrid) {
            servGrid.addEventListener('change', function (e) {
                if (e.target && e.target.classList.contains('eco-serv-input')) _ecoRefreshServicios();
            });
        }

        // Modal de servicios sin ecografía
        var servGuardar = byId('eco-servicio-guardar');
        if (servGuardar) servGuardar.addEventListener('click', guardarServicioEco);
        var servVolver = byId('eco-servicio-volver');
        if (servVolver) servVolver.addEventListener('click', volverDeServicioAExpediente);

        var volverTipos = byId('eco-volver-tipos-ecografia');
        if (volverTipos) volverTipos.addEventListener('click', volverATiposEcografia);

        var printInf = byId('eco-inf-det-print');
        if (printInf) printInf.addEventListener('click', function () {
            // Si está finalizado pero sin firmar, exigir la firma antes de imprimir.
            if (_currentInformeDetalleEcoEstado === 'finalizado') {
                _pedirFirmaAntesImprimir(_currentInformeDetalleEcoId);
            } else {
                _imprimirInformeEnIframeEco(_currentInformeDetalleEcoId);
            }
        });

        var firmarInf = byId('eco-inf-det-firmar');
        if (firmarInf) firmarInf.addEventListener('click', function () { _accionInformeEco('firmar'); });
        var anularInf = byId('eco-inf-det-anular');
        if (anularInf) anularInf.addEventListener('click', function () { _accionInformeEco('anular'); });

        // Confirmación "firmar antes de imprimir": firma y, al terminar, imprime.
        var firmarAntesBtn = byId('eco-firmar-antes-confirm');
        if (firmarAntesBtn) firmarAntesBtn.addEventListener('click', function () {
            var ctx = _firmarAntesCtx;
            if (!ctx || !ctx.informeId) { if (window.EcoModal) EcoModal.close('eco-modal-firmar-antes-eco'); return; }
            var btn = this;
            var prev = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Firmando…';
            var payload = new URLSearchParams();
            payload.set('informe_id', ctx.informeId);
            fetch((window.ECO_BASE || '') + 'api/firmar_informe.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: payload.toString()
            })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (d && d.success) {
                        ecoToast({ type: 'success', message: d.message || 'Informe firmado.' });
                        informeEcoCacheInvalidate(ctx.informeId);
                        _currentInformeDetalleEcoEstado = 'firmado';
                        if (studyState && String(studyState.ultimoInformeId) === String(ctx.informeId)) {
                            studyState.ultimoInformeFirmado = true;
                        }
                        document.dispatchEvent(new CustomEvent('eco:informes-changed', { detail: { informeId: ctx.informeId } }));
                        if (window.EcoModal) EcoModal.close('eco-modal-firmar-antes-eco');
                        // Si el detalle está abierto, refrescarlo para reflejar el estado firmado.
                        var det = document.getElementById('eco-modal-informe-detalle-eco');
                        if (det && det.classList.contains('eco-modal--open') && typeof window.abrirDetalleInformeEco === 'function') {
                            window.abrirDetalleInformeEco(ctx.informeId);
                        }
                        _imprimirInformeEnIframeEco(ctx.informeId);
                    } else {
                        ecoToast({ type: 'error', message: (d && d.message) || 'No se pudo firmar.' });
                    }
                })
                .catch(function () { ecoToast({ type: 'error', message: 'Error de red.' }); })
                .then(function () { btn.disabled = false; btn.innerHTML = prev; _firmarAntesCtx = null; });
        });

        var modalTipoEco = document.getElementById('eco-modal-seleccionar-ecografia-eco');
        if (modalTipoEco) {
            modalTipoEco.addEventListener('click', function (e) {
                var card = e.target.closest('.eco-card[data-eco-tipo-id]');
                if (card && modalTipoEco.contains(card)) seleccionarTipoEcografia(card);
            });
            modalTipoEco.addEventListener('keydown', function (e) {
                if (e.key !== 'Enter' && e.key !== ' ') return;
                var card = e.target.closest('.eco-card[data-eco-tipo-id]');
                if (!card || !modalTipoEco.contains(card)) return;
                e.preventDefault();
                seleccionarTipoEcografia(card);
            });
        }

        // Sub-modal Musculoesquelética
        var modalMusculo = document.getElementById('eco-modal-seleccionar-musculo-eco');
        if (modalMusculo) {
            modalMusculo.addEventListener('click', function (e) {
                var card = e.target.closest('.eco-card[data-eco-tipo-id]');
                if (card && modalMusculo.contains(card)) seleccionarSubMusculoEco(card);
            });
            modalMusculo.addEventListener('keydown', function (e) {
                if (e.key !== 'Enter' && e.key !== ' ') return;
                var card = e.target.closest('.eco-card[data-eco-tipo-id]');
                if (!card || !modalMusculo.contains(card)) return;
                e.preventDefault();
                seleccionarSubMusculoEco(card);
            });
        }

        var volverDeMusculo = byId('eco-volver-de-musculo');
        if (volverDeMusculo) {
            volverDeMusculo.addEventListener('click', function () {
                studyState.fromSubMusculo = false;
                EcoModal.close('eco-modal-seleccionar-musculo-eco');
                EcoModal.open('eco-modal-seleccionar-ecografia-eco');
            });
        }

        // Sub-modal Obstétrica
        var modalObstetrica = document.getElementById('eco-modal-seleccionar-obstetrica-eco');
        if (modalObstetrica) {
            modalObstetrica.addEventListener('click', function (e) {
                var card = e.target.closest('.eco-card[data-eco-tipo-id]');
                if (card && modalObstetrica.contains(card)) seleccionarSubObstetricaEco(card);
            });
            modalObstetrica.addEventListener('keydown', function (e) {
                if (e.key !== 'Enter' && e.key !== ' ') return;
                var card = e.target.closest('.eco-card[data-eco-tipo-id]');
                if (!card || !modalObstetrica.contains(card)) return;
                e.preventDefault();
                seleccionarSubObstetricaEco(card);
            });
        }

        var volverDeObstetrica = byId('eco-volver-de-obstetrica');
        if (volverDeObstetrica) {
            volverDeObstetrica.addEventListener('click', function () {
                studyState.fromSubObstetrica = false;
                EcoModal.close('eco-modal-seleccionar-obstetrica-eco');
                EcoModal.open('eco-modal-seleccionar-ecografia-eco');
            });
        }

        // Sub-modal Partes Blandas
        var modalPblandas = document.getElementById('eco-modal-seleccionar-pblandas-eco');
        if (modalPblandas) {
            modalPblandas.addEventListener('click', function (e) {
                var card = e.target.closest('.eco-card[data-eco-tipo-id]');
                if (card && modalPblandas.contains(card)) seleccionarSubPartesBlandasEco(card);
            });
            modalPblandas.addEventListener('keydown', function (e) {
                if (e.key !== 'Enter' && e.key !== ' ') return;
                var card = e.target.closest('.eco-card[data-eco-tipo-id]');
                if (!card || !modalPblandas.contains(card)) return;
                e.preventDefault();
                seleccionarSubPartesBlandasEco(card);
            });
        }

        var volverDePblandas = byId('eco-volver-de-pblandas');
        if (volverDePblandas) {
            volverDePblandas.addEventListener('click', function () {
                studyState.fromSubPartesBlandas = false;
                EcoModal.close('eco-modal-seleccionar-pblandas-eco');
                EcoModal.open('eco-modal-seleccionar-ecografia-eco');
            });
        }
    });
})();
