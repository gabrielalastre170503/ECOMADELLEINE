<?php
session_start();
include __DIR__ . '/../core/conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . eco_url('login'));
    exit;
}
if (($_SESSION['rol'] ?? '') !== 'recepcionista') {
    header('Location: ' . eco_url('dashboard'));
    exit;
}

$rx_total_pacientes = 0;
if ($r = $conex->query("SELECT COUNT(*) AS c FROM usuarios WHERE rol = 'paciente' AND estado = 'aprobado'")) {
    $row = $r->fetch_assoc();
    $rx_total_pacientes = (int)($row['c'] ?? 0);
}

$page_title    = 'Gestión de pacientes';
$page_subtitle = 'Directorio rápido, citas e informes';
$active_section = 'gestion-pacientes';
$body_class    = 'rx-gestion-pacientes-page';

// ?v=auto lo resuelve shell.php con filemtime: sin esto el navegador sirve el
// CSS cacheado y los cambios de estilo no se ven.
$page_head_extra = '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">'
    . '<link rel="stylesheet" href="assets/css/recepcion/recepcion-gestion-pacientes.css?v=auto">';

$page_header_actions = '
    <div class="rx-filtro-fechas" role="group" aria-label="Filtrar por fecha de atención"
         title="Filtrar por fecha de atención">
        <span class="rx-filtro-fechas__label"><i class="fa-solid fa-user-check" aria-hidden="true"></i></span>
        <button type="button" class="rx-filtro-seg is-active" data-rx-rango="todos" aria-pressed="true">Todos</button>
        <button type="button" class="rx-filtro-seg" data-rx-rango="hoy" aria-pressed="false">Hoy</button>
        <button type="button" class="rx-filtro-seg" data-rx-rango="ayer" aria-pressed="false">Ayer</button>
        <button type="button" class="rx-filtro-seg" data-rx-rango="semana" aria-pressed="false">7 días</button>
        <span class="rx-filtro-seg rx-filtro-seg--fecha" id="rx-chip-fecha">
            <i class="fa-solid fa-calendar-day" aria-hidden="true"></i>
            <input type="text" id="rx-filtro-fecha" readonly placeholder="Fecha…"
                   aria-label="Filtrar por una fecha concreta de atención">
        </span>
    </div>
    <button type="button" class="btn-secondary" id="btn-export-pac-rx"><i class="fa-solid fa-file-export"></i> Exportar</button>
    <button type="button" class="btn-secondary" id="btn-import-pac-rx"><i class="fa-solid fa-file-import"></i> Importar</button>
    <input type="file" id="file-import-pac-rx" accept=".xlsx,.xls,.csv" hidden>
    <button type="button" class="btn-primary" id="btn-open-crear-paciente-eco">
        <i class="fa-solid fa-user-plus"></i> Registrar paciente
    </button>
    <button type="button" class="rx-btn-alta-ext" id="btn-open-crear-paciente-ext">
        <i class="fa-solid fa-file-circle-plus" aria-hidden="true"></i>
        Alta extendida
    </button>';

ob_start();
?>

<!-- Buscador + total (misma línea que Mis Pacientes ecografista) -->
<div class="rx-controls-grid">
    <div class="card">
        <div class="rx-search-wrap">
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
            <input type="search" id="buscador-pacientes-rx" class="rx-search-input"
                   placeholder="Buscar por nombre o cédula…" autocomplete="off">
        </div>
    </div>
    <div class="card rx-total-card">
        <div class="rx-total-card__fila">
            <div class="rx-total-card__icon" aria-hidden="true">
                <i class="fa-solid fa-users"></i>
            </div>
            <div class="rx-total-card__texto">
                <div class="rx-total-card__label">Total</div>
                <div class="rx-total-card__value"><span id="rx-pac-count"><?= $rx_total_pacientes ?></span> pacientes</div>
            </div>
        </div>
        <div class="rx-total-card__fila rx-total-card__fila--dinero">
            <div class="rx-total-card__icon rx-total-card__icon--dinero" aria-hidden="true">
                <i class="fa-solid fa-money-bill-wave"></i>
            </div>
            <div class="rx-total-card__texto">
                <div class="rx-total-card__label">Cobrado</div>
                <div class="rx-total-card__value" id="rx-monto-cobrado">—</div>
                <div class="rx-total-card__sub" id="rx-monto-pendiente" hidden></div>
            </div>
        </div>
    </div>
</div>

<!-- Lista -->
<div class="card" id="rx-pac-list-card" style="padding:0;overflow:hidden;">
    <div id="rx-pac-wrap" class="rx-pac-wrap data-table">
        <p style="padding:20px;color:var(--text-muted);margin:0;">Cargando…</p>
    </div>
</div>

<?php
include __DIR__ . '/../layouts/partials/modal_crear_paciente.php';
include __DIR__ . '/../layouts/partials/modal_rx_gestion_pacientes.php';
$page_content = ob_get_clean();

$page_scripts_extra = <<<'HTML'
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="assets/js/panel/eco-table-sort.js"></script>
<script src="assets/js/recepcion/recepcion_rx_pacientes.js"></script>
<script>
/* Ojo: este bloque es un nowdoc, PHP no se interpola aqui. La URL se arma
   con ECO_BASE, igual que el resto de peticiones de la pagina. */
window.abrirModalGestionarPaciente = function (id) {
    window.location.href = (window.ECO_BASE || '') + 'ficha-paciente?id=' + encodeURIComponent(id);
};
</script>
<script>
(function () {
    var inp = document.getElementById('buscador-pacientes-rx');
    var box = document.getElementById('rx-pac-wrap');
    var countEl = document.getElementById('rx-pac-count');

    /* Filas del listado visible, para "Exportar". Se refresca en cada búsqueda:
       se exporta lo que la recepcionista está viendo, no toda la base. */
    window.RX_PAC_EXPORT = [];

    function rxActualizarTotal(wrap) {
        if (!wrap) return;
        var el = wrap.querySelector('[data-rx-total]');
        if (!el) return;
        if (countEl) countEl.textContent = el.getAttribute('data-rx-total') || '0';

        /* Importes del mismo conjunto que la tabla: con "Hoy" activo son los de
           hoy, no los históricos. Los formatea el servidor. */
        var cobradoEl = document.getElementById('rx-monto-cobrado');
        var pendEl = document.getElementById('rx-monto-pendiente');
        if (cobradoEl) cobradoEl.textContent = el.getAttribute('data-rx-cobrado') || '—';
        if (pendEl) {
            var pendNum = parseFloat(el.getAttribute('data-rx-pendiente-num') || '0');
            pendEl.textContent = 'Pendiente ' + (el.getAttribute('data-rx-pendiente') || '');
            pendEl.hidden = !(pendNum > 0);
        }
    }

    function rxActualizarExport(wrap) {
        window.RX_PAC_EXPORT = [];
        if (!wrap) return;
        var el = wrap.querySelector('[data-rx-export]');
        if (!el) return;
        try {
            var data = JSON.parse(el.getAttribute('data-rx-export') || '[]');
            if (Array.isArray(data)) window.RX_PAC_EXPORT = data;
        } catch (e) { /* listado sin datos exportables */ }
    }

    /* Filtro por día de atención. Se manda junto a la búsqueda, así el texto y
       el rango de fechas se combinan en vez de pisarse. */
    var rangoActual = 'todos';
    var fechaActual = '';

    window.buscarPacientesRecepcion = function (q) {
        if (!box) return;
        box.innerHTML = '<p style="padding:20px;color:var(--text-muted);margin:0;">Cargando…</p>';
        fetch((window.ECO_BASE || '') + 'api/buscar_pacientes_secretaria.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'query=' + encodeURIComponent(q || '')
                + '&rango=' + encodeURIComponent(rangoActual)
                + '&fecha=' + encodeURIComponent(fechaActual)
        })
            .then(function (r) { return r.text(); })
            .then(function (html) {
                box.innerHTML = html;
                rxActualizarTotal(box);
                rxActualizarExport(box);
                if (typeof window.rxResetTablaOrden === 'function') {
                    window.rxResetTablaOrden();
                }
            })
            .catch(function () {
                box.innerHTML = '<p style="color:#b91c1c;padding:16px;margin:0;">No se pudo cargar el listado.</p>';
            });
    };

    var chips = document.querySelectorAll('[data-rx-rango]');
    var chipFecha = document.getElementById('rx-chip-fecha');
    var inputFecha = document.getElementById('rx-filtro-fecha');

    function marcarChip(activo) {
        chips.forEach(function (c) {
            var on = c === activo;
            c.classList.toggle('is-active', on);
            c.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
        if (chipFecha) chipFecha.classList.toggle('is-active', activo === chipFecha);
    }

    chips.forEach(function (chip) {
        chip.addEventListener('click', function () {
            rangoActual = chip.getAttribute('data-rx-rango');
            fechaActual = '';
            if (inputFecha && inputFecha._flatpickr) inputFecha._flatpickr.clear();
            marcarChip(chip);
            buscarPacientesRecepcion(inp ? inp.value : '');
        });
    });

    // Fecha suelta: el chip es el propio campo, así flatpickr se posiciona solo.
    if (inputFecha && typeof flatpickr !== 'undefined') {
        var locFiltro = (flatpickr.l10ns && flatpickr.l10ns.es) ? flatpickr.l10ns.es : undefined;
        flatpickr(inputFecha, {
            locale: locFiltro,
            dateFormat: 'Y-m-d',
            altInput: true,
            altFormat: 'd/m/Y',
            // flatpickr oculta el input original y crea otro: el visible dentro
            // del chip es este, así que es el que hay que poder estilar.
            altInputClass: 'rx-filtro-fecha-alt',
            maxDate: 'today',
            onChange: function (sel, valorISO) {
                if (!valorISO) return;
                rangoActual = 'fecha';
                fechaActual = valorISO;
                marcarChip(chipFecha);
                buscarPacientesRecepcion(inp ? inp.value : '');
            }
        });
    }

    if (inp) {
        buscarPacientesRecepcion('');
        inp.addEventListener('keyup', function () { buscarPacientesRecepcion(this.value); });
    }

    /* Detalle de la atención (tipo de eco, servicios, cobro y método de pago).
       Delegado en el contenedor: la tabla se reemplaza en cada búsqueda. */
    if (box) {
        box.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-rx-expand]');
            if (!btn) return;
            var fila = box.querySelector('[data-rx-detalle="' + btn.getAttribute('data-rx-expand') + '"]');
            if (!fila) return;
            var abierto = btn.getAttribute('aria-expanded') === 'true';
            btn.setAttribute('aria-expanded', abierto ? 'false' : 'true');
            fila.hidden = abierto;
        });
    }

    var fpEco = null;
    function initFechaNacimientoEco() {
        var el = document.getElementById('fecha_nacimiento_eco');
        if (!el || typeof flatpickr === 'undefined') return;
        if (fpEco) { fpEco.destroy(); fpEco = null; }
        var loc = (flatpickr.l10ns && flatpickr.l10ns.es) ? flatpickr.l10ns.es : undefined;
        fpEco = flatpickr(el, {
            locale: loc,
            dateFormat: 'Y-m-d',
            maxDate: 'today',
            altInput: true,
            altFormat: 'd/m/Y'
        });
    }

    var fpAtencion = null;
    function initFechaAtencion() {
        var el = document.getElementById('mcp_fecha_cita');
        if (!el || typeof flatpickr === 'undefined') return;
        if (fpAtencion) { fpAtencion.destroy(); fpAtencion = null; }
        var loc = (flatpickr.l10ns && flatpickr.l10ns.es) ? flatpickr.l10ns.es : undefined;
        fpAtencion = flatpickr(el, {
            locale: loc,
            enableTime: true,
            time_24hr: true,
            dateFormat: 'Y-m-d H:i',
            altInput: true,
            altFormat: 'd/m/Y H:i'
        });
    }

    /* El total lo calcula el servidor: precios y promociones viven en
       lib/facturacion y no se duplican aquí. */
    var montoEditadoAMano = false;
    var montoEl = document.getElementById('mcp_monto');
    if (montoEl) {
        montoEl.addEventListener('input', function () { montoEditadoAMano = true; });
    }

    function recalcularTotal() {
        var notaEl = document.getElementById('mcp-monto-sugerido');
        var resumenEl = document.getElementById('mcp-estudios-resumen');
        if (!montoEl) return;

        var servicios = [];
        document.querySelectorAll('[data-mcp-servicio]:checked').forEach(function (c) {
            servicios.push(c.value);
        });
        var tipos = [];
        var nombres = [];
        document.querySelectorAll('[data-mcp-estudio]:checked').forEach(function (c) {
            tipos.push(parseInt(c.value, 10));
            var txt = c.parentElement.querySelector('.mcp-opcion__nombre');
            if (txt) nombres.push(txt.textContent.trim());
        });

        if (resumenEl) {
            resumenEl.textContent = nombres.length
                ? nombres.length + (nombres.length === 1 ? ' ecografía: ' : ' ecografías: ') + nombres.join(', ')
                : 'Ninguna seleccionada.';
        }

        if (!tipos.length && !servicios.length) {
            if (notaEl) notaEl.textContent = 'Se calcula solo al elegir estudios y servicios. Puedes cambiarlo.';
            if (!montoEditadoAMano) montoEl.value = '';
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
            if (!montoEditadoAMano) montoEl.value = res.total > 0 ? res.total.toFixed(2) : '';
            if (notaEl) {
                notaEl.textContent = 'Sugerido: ' + res.total_texto
                    + (res.promos && res.promos.length ? ' · ' + res.promos.join(' · ') : '')
                    + (montoEditadoAMano ? ' (monto modificado a mano)' : '');
            }
        })
        .catch(function () {
            if (notaEl) notaEl.textContent = 'No se pudo calcular el total. Escribe el monto a mano.';
        });
    }

    document.querySelectorAll('[data-mcp-servicio], [data-mcp-estudio]').forEach(function (c) {
        c.addEventListener('change', recalcularTotal);
    });

    var btnOpen = document.getElementById('btn-open-crear-paciente-eco');
    if (btnOpen) {
        btnOpen.addEventListener('click', function () {
            var form = document.getElementById('form-crear-paciente-eco');
            var err = document.getElementById('eco-crear-paciente-error');
            if (form) form.reset();
            if (err) { err.style.display = 'none'; err.textContent = ''; }
            montoEditadoAMano = false;
            var notaEl = document.getElementById('mcp-monto-sugerido');
            if (notaEl) notaEl.textContent = 'Se calcula solo al elegir estudios y servicios. Puedes cambiarlo.';
            var resumenEl = document.getElementById('mcp-estudios-resumen');
            if (resumenEl) resumenEl.textContent = 'Ninguna seleccionada.';
            if (typeof EcoModal !== 'undefined') EcoModal.open('eco-modal-crear-paciente');
            setTimeout(function () {
                initFechaNacimientoEco();
                initFechaAtencion();
            }, 0);
        });
    }

    var btnExt = document.getElementById('btn-open-crear-paciente-ext');
    if (btnExt && typeof window.rxAbrirCrearPacienteExtendido === 'function') {
        btnExt.addEventListener('click', function () {
            rxAbrirCrearPacienteExtendido();
        });
    }

    var formEco = document.getElementById('form-crear-paciente-eco');
    if (formEco) {
        formEco.addEventListener('submit', function (e) {
            e.preventDefault();
            var btn = document.getElementById('btn-submit-crear-paciente-eco');
            var err = document.getElementById('eco-crear-paciente-error');
            var val = window.ecoValidadorCrearPaciente;
            if (err) { err.style.display = 'none'; err.textContent = ''; }
            // Se revisa antes de enviar: el fallo se marca en el campo, no en un
            // aviso al principio de un formulario que hay que volver a recorrer.
            if (val && !val.validar()) { return; }
            if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Guardando…'; }
            fetch((window.ECO_BASE || '') + 'api/guardar_paciente.php', { method: 'POST', body: new FormData(formEco) })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success) {
                        if (typeof EcoModal !== 'undefined') EcoModal.close('eco-modal-crear-paciente');
                        document.getElementById('eco-exito-paciente-nombre').textContent = data.nombre || '';
                        document.getElementById('eco-exito-paciente-pass').textContent = data.password || '—';
                        if (window.ecoExitoPaciente) window.ecoExitoPaciente(data);

                        var boxCita = document.getElementById('eco-exito-cita');
                        if (boxCita) {
                            if (data.cita) {
                                document.getElementById('eco-exito-cita-eco').textContent = data.cita.ecografista || 'Sin asignar';
                                document.getElementById('eco-exito-cita-total').textContent = data.cita.total || '—';
                                document.getElementById('eco-exito-cita-pago').textContent = data.cita.metodo_pago
                                    ? data.cita.estado_pago + ' · ' + data.cita.metodo_pago
                                    : data.cita.estado_pago;
                                document.getElementById('eco-exito-cita-detalle').textContent = data.cita.detalle || '';
                                boxCita.hidden = false;
                            } else {
                                boxCita.hidden = true;
                            }
                        }

                        EcoModal.open('eco-modal-exito-paciente');
                        buscarPacientesRecepcion(inp ? inp.value : '');
                    } else {
                        /* El servidor dice QUÉ campo falló (correo repetido, cédula
                           ya usada…): se marca ahí. El aviso de arriba queda para
                           lo que no corresponde a ningún campo. */
                        var puesto = data.campo && val
                            ? val.marcarPorNombre(data.campo, data.message || 'Revisa este dato.')
                            : false;
                        if (!puesto && err) {
                            err.textContent = data.message || 'No se pudo crear el paciente.';
                            err.style.display = 'block';
                            err.scrollIntoView({ block: 'center', behavior: 'smooth' });
                        }
                    }
                })
                .catch(function () {
                    if (err) {
                        err.textContent = 'Error de red. Intenta de nuevo.';
                        err.style.display = 'block';
                    }
                })
                .finally(function () {
                    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-check"></i> Crear paciente'; }
                });
        });
    }

    var btnExito = document.getElementById('btn-eco-exito-cerrar');
    if (btnExito) {
        btnExito.addEventListener('click', function () {
            if (typeof EcoModal !== 'undefined') EcoModal.close('eco-modal-exito-paciente');
        });
    }
})();
</script>
<script>
/* Exportar / Importar pacientes a Excel (.xlsx) con SheetJS. */
(function () {
    var btnExp  = document.getElementById('btn-export-pac-rx');
    var btnImp  = document.getElementById('btn-import-pac-rx');
    var fileInp = document.getElementById('file-import-pac-rx');

    if (btnExp) {
        btnExp.addEventListener('click', function () {
            var data = window.RX_PAC_EXPORT || [];
            if (!data.length) { alert('No hay pacientes para exportar.'); return; }
            if (!window.XLSX) { alert('No se pudo cargar el componente de Excel. Revisa tu conexión.'); return; }
            var ws = XLSX.utils.json_to_sheet(data);
            var wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, 'Pacientes');
            var hoy = new Date().toISOString().slice(0, 10);
            XLSX.writeFile(wb, 'Pacientes_' + hoy + '.xlsx');
        });
    }

    if (btnImp && fileInp) {
        btnImp.addEventListener('click', function () { fileInp.value = ''; fileInp.click(); });
        fileInp.addEventListener('change', function () {
            var file = fileInp.files && fileInp.files[0];
            if (!file) return;
            if (!window.XLSX) { alert('No se pudo cargar el componente de Excel. Revisa tu conexión.'); return; }

            var reader = new FileReader();
            reader.onload = function (e) {
                var filas;
                try {
                    var wb = XLSX.read(e.target.result, { type: 'array' });
                    var ws = wb.Sheets[wb.SheetNames[0]];
                    filas = XLSX.utils.sheet_to_json(ws, { defval: '' });
                } catch (err) {
                    alert('No se pudo leer el archivo. Asegúrate de que sea un Excel o CSV válido.');
                    return;
                }
                if (!filas.length) { alert('El archivo no contiene filas.'); return; }

                var original = btnImp.innerHTML;
                btnImp.disabled = true;
                btnImp.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Importando…';
                fetch((window.ECO_BASE || '') + 'api/importar_pacientes.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ filas: filas })
                })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    alert(res.message || (res.success ? 'Importación completada.' : 'No se pudo importar.'));
                    if (res.success && res.creados > 0) {
                        var inp = document.getElementById('buscador-pacientes-rx');
                        window.buscarPacientesRecepcion(inp ? inp.value : '');
                    }
                })
                .catch(function () { alert('Error de red durante la importación.'); })
                .finally(function () { btnImp.disabled = false; btnImp.innerHTML = original; });
            };
            reader.readAsArrayBuffer(file);
        });
    }
})();
</script>
HTML;

include __DIR__ . '/../layouts/shell.php';
