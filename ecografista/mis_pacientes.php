<?php
session_start();
include __DIR__ . '/../core/conexion.php';
require_once __DIR__ . '/../lib/core/table_sort_helpers.php';
require_once __DIR__ . '/../lib/pacientes/mis_pacientes.php';
require_once __DIR__ . '/../lib/facturacion/facturacion.php';

if (!isset($_SESSION['usuario_id'])) { header('Location: ' . eco_url('login')); exit; }
if ($_SESSION['rol'] !== 'ecografista') { header('Location: ' . eco_url('dashboard')); exit; }

$ecografista_id = (int)$_SESSION['usuario_id'];

/* Carga inicial de TODOS mis pacientes (filtros JS son client-side rápido).
   La consulta y el marcado de las filas se comparten con
   api/mis_pacientes_ecografista.php, que refresca la tabla sin recargar. */
$pacientes = eco_mis_pacientes($conex, $ecografista_id);
$montos_iniciales = eco_mis_pacientes_montos($conex, $ecografista_id);

$page_title    = 'Mis Pacientes';
$page_subtitle = 'Pacientes clínicos asignados o que has atendido';
$active_section = 'pacientes';
$body_class    = 'eco-mis-pacientes-page';

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
    <button type="button" class="btn-secondary" id="btn-export-pac"><i class="fa-solid fa-file-export"></i> Exportar</button>
    <button type="button" class="btn-secondary" id="btn-import-pac"><i class="fa-solid fa-file-import"></i> Importar</button>
    <input type="file" id="file-import-pac" accept=".xlsx,.xls,.csv" hidden>
    <button type="button" class="btn-primary" data-eco-abrir-crear-paciente-mis><i class="fa-solid fa-user-plus"></i> Añadir Paciente</button>';

ob_start();
?>
<script>
window.PAC_EXPORT = <?= json_encode(eco_mis_pacientes_export($pacientes), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
</script>

<!-- Buscador + stats (mismas clases que Gestión de pacientes de recepción) -->
<div class="rx-controls-grid">
    <div class="card">
        <div class="rx-search-wrap">
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
            <input type="search" id="buscador-pacientes" class="rx-search-input"
                   placeholder="Buscar por nombre, cédula, correo, teléfono o dirección…" autocomplete="off">
        </div>
    </div>
    <div class="card rx-total-card">
        <div class="rx-total-card__fila">
            <div class="rx-total-card__icon" aria-hidden="true">
                <i class="fa-solid fa-users"></i>
            </div>
            <div class="rx-total-card__texto">
                <div class="rx-total-card__label">Total</div>
                <div class="rx-total-card__value"><span id="pac-count"><?= count($pacientes) ?></span> pacientes</div>
            </div>
        </div>
        <div class="rx-total-card__fila rx-total-card__fila--dinero">
            <div class="rx-total-card__icon rx-total-card__icon--dinero" aria-hidden="true">
                <i class="fa-solid fa-money-bill-wave"></i>
            </div>
            <div class="rx-total-card__texto">
                <div class="rx-total-card__label">Cobrado</div>
                <div class="rx-total-card__value" id="eco-monto-cobrado"><?= htmlspecialchars(eco_money($montos_iniciales['cobrado'])) ?></div>
                <div class="rx-total-card__sub" id="eco-monto-pendiente" <?= $montos_iniciales['pendiente'] > 0 ? '' : 'hidden' ?>>
                    Pendiente <?= htmlspecialchars(eco_money($montos_iniciales['pendiente'])) ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Lista de pacientes.
     Las dos tarjetas se renderizan siempre y se alternan por JS: el refresco
     periódico puede así pintar el primer paciente sin recargar la página. -->
<div class="card" id="pac-sin-pacientes" style="text-align:center;padding:60px 20px;<?= empty($pacientes) ? '' : 'display:none;' ?>">
    <i class="fa-solid fa-user-injured" style="font-size:3rem;color:var(--text-muted);opacity:.4;margin-bottom:14px;"></i>
    <h3 style="margin:0 0 6px;color:var(--text-primary);">No tienes pacientes aún</h3>
    <p style="color:var(--text-secondary);margin:0 0 18px;font-size:13.5px;">Empieza añadiendo tu primer paciente al sistema.</p>
    <button type="button" class="btn-primary" data-eco-abrir-crear-paciente-mis><i class="fa-solid fa-user-plus"></i> Añadir Paciente</button>
</div>

<div class="card" id="pac-list-card" style="padding:0;overflow:hidden;<?= empty($pacientes) ? 'display:none;' : '' ?>">
    <div class="rx-pac-wrap data-table table-responsive" style="border:none;">
        <table class="rx-pac-table eco-mis-pac-table">
            <colgroup>
                <col class="col-eco-paciente"><col class="col-eco-cedula"><col class="col-eco-edad">
                <col class="col-eco-correo"><col class="col-eco-telefono"><col class="col-eco-direccion"><col class="col-eco-citas"><col class="col-eco-informes">
                <col class="col-eco-ingreso"><col class="col-eco-acciones">
            </colgroup>
            <thead>
                <tr>
                    <?= eco_sort_th('Paciente', 0, 'text') ?>
                    <?= eco_sort_th('Cédula', 1, 'number') ?>
                    <?= eco_sort_th('Edad', 2, 'number') ?>
                    <?= eco_sort_th('Correo', 3, 'text') ?>
                    <?= eco_sort_th('Teléfono', 4, 'text') ?>
                    <?= eco_sort_th('Dirección', 5, 'text') ?>
                    <th>Citas</th>
                    <th>Informes</th>
                    <?= eco_sort_th('Ingreso', 8, 'date') ?>
                    <th class="rx-th-acciones">Acciones</th>
                </tr>
            </thead>
            <tbody id="tbody-pacientes"><?= eco_mis_pacientes_filas_html($pacientes) ?></tbody>
        </table>
    </div>

    <div id="pac-empty-state" style="text-align:center;padding:40px 20px;color:var(--text-muted);display:none;">
        <i class="fa-solid fa-magnifying-glass" style="font-size:2rem;opacity:.4;display:block;margin-bottom:10px;"></i>
        <p style="margin:0;">No se encontraron pacientes con ese criterio.</p>
    </div>
</div>

<!-- Sin resultados por el filtro de fechas (distinto de "no tienes pacientes") -->
<div class="card" id="pac-vacio-filtro" style="text-align:center;padding:48px 20px;color:var(--text-muted);display:none;">
    <i class="fa-solid fa-calendar-xmark" style="font-size:2.4rem;opacity:.35;display:block;margin-bottom:12px;"></i>
    <p style="margin:0;font-size:14px;"></p>
</div>

<script>
(function() {
    const input = document.getElementById('buscador-pacientes');
    const empty = document.getElementById('pac-empty-state');
    const count = document.getElementById('pac-count');
    const tbody = document.getElementById('tbody-pacientes');
    const listCard = document.getElementById('pac-list-card');
    const sinPacientes = document.getElementById('pac-sin-pacientes');
    const vacioFiltroCard = document.getElementById('pac-vacio-filtro');
    const vacioFiltro = vacioFiltroCard ? vacioFiltroCard.querySelector('p') : null;
    if (!input || !tbody) return;

    /* Las filas se releen en cada filtrado: el refresco periódico reemplaza el
       tbody, así que una lista capturada al cargar quedaría obsoleta. */
    function aplicarFiltro() {
        const q = input.value.trim().toLowerCase();
        const rows = tbody.querySelectorAll('.pac-row');
        let visible = 0;
        rows.forEach(r => {
            const match = (r.dataset.search || '').includes(q);
            r.style.display = match ? '' : 'none';
            if (match) visible++;
        });
        if (count) count.textContent = visible;
        if (empty) empty.style.display = (visible === 0 && rows.length > 0) ? 'block' : 'none';
    }
    window.ecoAplicarFiltroPacientes = aplicarFiltro;
    input.addEventListener('input', aplicarFiltro);

    /* Filtro "atendidos en". Se resuelve en el servidor y reutiliza la misma
       máquina de refresco: al cambiar de rango se pide el listado y se sustituye
       el tbody, igual que hace el sondeo periódico. */
    let rangoActual = 'todos';
    let fechaActual = '';

    /* Importes de MIS citas, con el mismo rango que la tabla. Los formatea el
       servidor para que la moneda se escriba en un solo sitio. */
    const cobradoEl = document.getElementById('eco-monto-cobrado');
    const pendienteEl = document.getElementById('eco-monto-pendiente');
    function actualizarMontos(res) {
        if (cobradoEl) cobradoEl.textContent = res.cobrado || '—';
        if (pendienteEl) {
            pendienteEl.textContent = 'Pendiente ' + (res.pendiente || '');
            pendienteEl.hidden = !((res.pendiente_num || 0) > 0);
        }
    }

    /* Refresco del listado: recepción puede asignar un paciente a este
       ecografista en cualquier momento y debe aparecer sin recargar. */
    let refrescando = false;
    function refrescarPacientes(forzar) {
        if (refrescando || (document.hidden && !forzar)) return;
        refrescando = true;
        const url = (window.ECO_BASE || '') + 'api/mis_pacientes_ecografista.php'
            + '?rango=' + encodeURIComponent(rangoActual)
            + '&fecha=' + encodeURIComponent(fechaActual);
        fetch(url, { headers: { 'Accept': 'application/json' } })
        .then(r => r.json())
        .then(res => {
            if (!res || !res.success) return;

            // Antes del corte de abajo: registrar un cobro cambia los importes
            // sin cambiar las filas, y el card debe reflejarlo igual.
            actualizarMontos(res);

            // Si las filas no cambiaron no se toca el DOM: evita parpadeos y,
            // sobre todo, no descoloca el orden que el usuario haya aplicado.
            // (EcoTableSort delega en el contenedor, así que sigue funcionando
            // sobre las filas nuevas sin volver a inicializarlo.)
            if (res.filas_html === tbody.innerHTML) return;

            tbody.innerHTML = res.filas_html;
            window.PAC_EXPORT = res.export || [];

            const hay = res.total > 0;
            if (listCard) listCard.style.display = hay ? '' : 'none';
            // Con un filtro activo y cero resultados, "No tienes pacientes aún"
            // sería falso: lo que no hay es atenciones en ese rango.
            if (sinPacientes) {
                sinPacientes.style.display = (hay || res.filtrado) ? 'none' : '';
            }
            if (vacioFiltroCard) {
                vacioFiltroCard.style.display = (!hay && res.filtrado) ? '' : 'none';
                if (vacioFiltro) vacioFiltro.textContent = res.vacio || '';
            }

            aplicarFiltro();
        })
        .catch(() => { /* corte de red: se reintenta en la siguiente vuelta */ })
        .finally(() => { refrescando = false; });
    }

    const chips = document.querySelectorAll('[data-rx-rango]');
    const chipFecha = document.getElementById('rx-chip-fecha');
    const inputFecha = document.getElementById('rx-filtro-fecha');

    function marcarChip(activo) {
        chips.forEach(c => {
            const on = c === activo;
            c.classList.toggle('is-active', on);
            c.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
        if (chipFecha) chipFecha.classList.toggle('is-active', activo === chipFecha);
    }

    chips.forEach(chip => {
        chip.addEventListener('click', function () {
            rangoActual = chip.getAttribute('data-rx-rango');
            fechaActual = '';
            if (inputFecha && inputFecha._flatpickr) inputFecha._flatpickr.clear();
            marcarChip(chip);
            refrescarPacientes(true);
        });
    });

    /* Este bloque va en el contenido de la página, pero flatpickr se carga más
       abajo en el documento: al ejecutarse aquí todavía no existe. Sin esperar,
       el guard fallaba en silencio y el chip "Fecha…" no abría el calendario. */
    function initFiltroFecha() {
        if (!inputFecha || typeof flatpickr === 'undefined') return;
        const locFiltro = (flatpickr.l10ns && flatpickr.l10ns.es) ? flatpickr.l10ns.es : undefined;
        flatpickr(inputFecha, {
            locale: locFiltro,
            dateFormat: 'Y-m-d',
            altInput: true,
            altFormat: 'd/m/Y',
            altInputClass: 'rx-filtro-fecha-alt',
            maxDate: 'today',
            onChange: function (sel, valorISO) {
                if (!valorISO) return;
                rangoActual = 'fecha';
                fechaActual = valorISO;
                marcarChip(chipFecha);
                refrescarPacientes(true);
            }
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initFiltroFecha);
    } else {
        initFiltroFecha();
    }

    setInterval(refrescarPacientes, 20000);
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) refrescarPacientes();
    });
})();
</script>

<?php
include __DIR__ . '/../layouts/partials/modal_gestionar_paciente_ecografista.php';
include __DIR__ . '/../layouts/partials/modal_crear_paciente.php';

$page_content = ob_get_clean();

$page_scripts_extra = <<<'HTML'
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="assets/js/panel/eco-table-sort.js"></script>
<script src="assets/js/panel/ecografista-modals.js?v=auto"></script>
<script>
/* Exportar / Importar pacientes a Excel (.xlsx) con SheetJS */
(function () {
    var btnExp  = document.getElementById('btn-export-pac');
    var btnImp  = document.getElementById('btn-import-pac');
    var fileInp = document.getElementById('file-import-pac');

    if (btnExp) {
        btnExp.addEventListener('click', function () {
            var data = window.PAC_EXPORT || [];
            if (!data.length) { alert('No hay pacientes para exportar.'); return; }
            if (!window.XLSX) { alert('No se pudo cargar el componente de Excel. Revisa tu conexión.'); return; }
            var ws = XLSX.utils.json_to_sheet(data);
            var wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, 'Pacientes');
            var hoy = new Date().toISOString().slice(0, 10);
            XLSX.writeFile(wb, 'Mis_Pacientes_' + hoy + '.xlsx');
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
                    if (res.success && res.creados > 0) { location.reload(); }
                })
                .catch(function () { alert('Error de red durante la importación.'); })
                .finally(function () { btnImp.disabled = false; btnImp.innerHTML = original; });
            };
            reader.readAsArrayBuffer(file);
        });
    }
})();
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var card = document.getElementById('pac-list-card');
    if (card && window.EcoTableSort) {
        EcoTableSort.init(card);
    }
});
</script>
<script>
(function () {
    var fpEcoNac = null;
    function initFechaNacimientoEcoPac() {
        var el = document.getElementById('fecha_nacimiento_eco');
        if (!el || typeof flatpickr === 'undefined') return;
        if (fpEcoNac) { fpEcoNac.destroy(); fpEcoNac = null; }
        var loc = (flatpickr.l10ns && flatpickr.l10ns.es) ? flatpickr.l10ns.es : undefined;
        fpEcoNac = flatpickr(el, {
            locale: loc,
            dateFormat: 'Y-m-d',
            maxDate: 'today',
            altInput: true,
            altFormat: 'd/m/Y'
        });
    }
    function abrirModalCrearPacienteMis() {
        var form = document.getElementById('form-crear-paciente-eco');
        var err = document.getElementById('eco-crear-paciente-error');
        if (form) form.reset();
        if (err) { err.style.display = 'none'; err.textContent = ''; }
        var fechaEl = document.getElementById('fecha_nacimiento_eco');
        if (fechaEl && fechaEl._flatpickr) fechaEl._flatpickr.destroy();
        if (typeof EcoModal !== 'undefined') EcoModal.open('eco-modal-crear-paciente');
        setTimeout(initFechaNacimientoEcoPac, 0);
    }
    document.querySelectorAll('[data-eco-abrir-crear-paciente-mis]').forEach(function (btn) {
        btn.addEventListener('click', abrirModalCrearPacienteMis);
    });
    var formEco = document.getElementById('form-crear-paciente-eco');
    if (formEco) {
        formEco.addEventListener('submit', function (e) {
            e.preventDefault();
            var btn = document.getElementById('btn-submit-crear-paciente-eco');
            var err = document.getElementById('eco-crear-paciente-error');
            if (err) { err.style.display = 'none'; err.textContent = ''; }
            if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Guardando…'; }
            fetch((window.ECO_BASE || '') + 'api/guardar_paciente.php', { method: 'POST', body: new FormData(formEco) })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success) {
                        if (typeof EcoModal !== 'undefined') EcoModal.close('eco-modal-crear-paciente');
                        var nm = document.getElementById('eco-exito-paciente-nombre');
                        var pw = document.getElementById('eco-exito-paciente-pass');
                        if (nm) nm.textContent = data.nombre || '';
                        if (pw) pw.textContent = data.password || '—';
                        if (typeof EcoModal !== 'undefined') EcoModal.open('eco-modal-exito-paciente');
                    } else if (err) {
                        err.textContent = data.message || 'No se pudo crear el paciente.';
                        err.style.display = 'block';
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
            window.location.reload();
        });
    }
})();
</script>
HTML;

include __DIR__ . '/../layouts/shell.php';
