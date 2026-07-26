<?php
session_start();
include __DIR__ . '/../core/conexion.php';
require_once __DIR__ . '/../lib/core/table_sort_helpers.php';

if (!isset($_SESSION['usuario_id'])) { header('Location: ' . eco_url('login')); exit; }
if ($_SESSION['rol'] !== 'ecografista') { header('Location: ' . eco_url('dashboard')); exit; }

$ecografista_id = (int)$_SESSION['usuario_id'];

/* Carga inicial de TODOS mis pacientes (filtros JS son client-side rápido) */
$pacientes = [];
$sql = "SELECT DISTINCT u.id, u.nombre_completo, u.correo, u.cedula, u.direccion, u.telefono, u.fecha_registro,
               TIMESTAMPDIFF(YEAR, u.fecha_nacimiento, CURDATE()) AS edad,
               (SELECT COUNT(*) FROM citas c2 WHERE c2.paciente_id=u.id AND c2.ecografista_id=?) AS total_citas,
               (SELECT COUNT(*) FROM informes_estudios ie WHERE ie.paciente_id=u.id) AS total_informes
        FROM usuarios u
        LEFT JOIN citas c ON u.id = c.paciente_id
        WHERE u.rol='paciente' AND u.estado='aprobado'
              AND (u.creado_por_id = ? OR c.ecografista_id = ?)
        ORDER BY u.fecha_registro DESC";
if ($s = $conex->prepare($sql)) {
    $s->bind_param('iii', $ecografista_id, $ecografista_id, $ecografista_id);
    $s->execute();
    $pacientes = $s->get_result()->fetch_all(MYSQLI_ASSOC);
    $s->close();
}

$page_title    = 'Mis Pacientes';
$page_subtitle = 'Pacientes clínicos asignados o que has atendido';
$active_section = 'pacientes';

$page_head_extra = '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">'
    . '<link rel="stylesheet" href="assets/css/recepcion/recepcion-gestion-pacientes.css">';

$page_header_actions = '
    <button type="button" class="btn-secondary" id="btn-export-pac"><i class="fa-solid fa-file-export"></i> Exportar</button>
    <button type="button" class="btn-secondary" id="btn-import-pac"><i class="fa-solid fa-file-import"></i> Importar</button>
    <input type="file" id="file-import-pac" accept=".xlsx,.xls,.csv" hidden>
    <button type="button" class="btn-primary" data-eco-abrir-crear-paciente-mis><i class="fa-solid fa-user-plus"></i> Añadir Paciente</button>';

ob_start();
?>
<script>
window.PAC_EXPORT = <?= json_encode(array_map(function ($p) {
    return [
        'Nombre'    => $p['nombre_completo'],
        'Cédula'    => $p['cedula'] ?: '',
        'Edad'      => $p['edad'] ? (int)$p['edad'] : '',
        'Correo'    => $p['correo'] ?: '',
        'Teléfono'  => $p['telefono'] ?: '',
        'Dirección' => $p['direccion'] ?: '',
        'Citas'     => (int)$p['total_citas'],
        'Informes'  => (int)$p['total_informes'],
        'Ingreso'   => $p['fecha_registro'] ? date('d/m/Y', strtotime($p['fecha_registro'])) : '',
    ];
}, $pacientes), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
</script>

<!-- Buscador + stats -->
<div style="display:grid;grid-template-columns:1fr 240px;gap:14px;margin-bottom:18px;">
    <div class="card" style="padding:14px 18px;">
        <div style="position:relative;">
            <i class="fa-solid fa-magnifying-glass" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-muted);"></i>
            <input type="search" id="buscador-pacientes"
                   placeholder="Buscar por nombre, cédula, correo, teléfono o dirección..."
                   style="width:100%;padding:11px 14px 11px 40px;border:1.5px solid var(--border);border-radius:8px;font-family:inherit;font-size:13.5px;background:var(--bg-surface);color:var(--text-primary);box-sizing:border-box;">
        </div>
    </div>
    <div class="card" style="padding:14px 18px;display:flex;align-items:center;gap:10px;">
        <div style="width:36px;height:36px;background:var(--accent-soft);color:var(--accent-text);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <i class="fa-solid fa-users"></i>
        </div>
        <div>
            <div style="font-size:11px;color:var(--text-secondary);text-transform:uppercase;font-weight:600;">Total</div>
            <div style="font-size:18px;font-weight:700;color:var(--text-primary);"><span id="pac-count"><?= count($pacientes) ?></span> pacientes</div>
        </div>
    </div>
</div>

<!-- Lista de pacientes -->
<?php if (empty($pacientes)): ?>
    <div class="card" style="text-align:center;padding:60px 20px;">
        <i class="fa-solid fa-user-injured" style="font-size:3rem;color:var(--text-muted);opacity:.4;margin-bottom:14px;"></i>
        <h3 style="margin:0 0 6px;color:var(--text-primary);">No tienes pacientes aún</h3>
        <p style="color:var(--text-secondary);margin:0 0 18px;font-size:13.5px;">Empieza añadiendo tu primer paciente al sistema.</p>
        <button type="button" class="btn-primary" data-eco-abrir-crear-paciente-mis><i class="fa-solid fa-user-plus"></i> Añadir Paciente</button>
    </div>
<?php else: ?>

<div class="card" id="pac-list-card" style="padding:0;overflow:hidden;">
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
            <tbody id="tbody-pacientes">
                <?php foreach ($pacientes as $p):
                    $iniciales = '';
                    foreach (explode(' ', trim($p['nombre_completo'])) as $part) {
                        if ($part !== '' && strlen($iniciales) < 2) $iniciales .= strtoupper($part[0]);
                    }
                    $fecha_ing = $p['fecha_registro'] ? date('d/m/Y', strtotime($p['fecha_registro'])) : '—';
                    $busqueda = strtolower($p['nombre_completo'] . ' ' . ($p['cedula'] ?? '') . ' ' . ($p['correo'] ?? '') . ' ' . ($p['telefono'] ?? '') . ' ' . ($p['direccion'] ?? ''));
                    $sortNombre = htmlspecialchars(mb_strtolower(trim((string)$p['nombre_completo']), 'UTF-8'), ENT_QUOTES, 'UTF-8');
                    $cedulaDigits = preg_replace('/\D/', '', (string)($p['cedula'] ?? ''));
                    $sortCedula = htmlspecialchars($cedulaDigits !== '' ? $cedulaDigits : '0', ENT_QUOTES, 'UTF-8');
                    $sortEdad = htmlspecialchars($p['edad'] ? (string)(int)$p['edad'] : '0', ENT_QUOTES, 'UTF-8');
                    $sortCorreo = htmlspecialchars(mb_strtolower(trim((string)($p['correo'] ?? '')), 'UTF-8'), ENT_QUOTES, 'UTF-8');
                    $sortTelefono = htmlspecialchars(mb_strtolower(trim((string)($p['telefono'] ?? '')), 'UTF-8'), ENT_QUOTES, 'UTF-8');
                    $sortDireccion = htmlspecialchars(mb_strtolower(trim((string)($p['direccion'] ?? '')), 'UTF-8'), ENT_QUOTES, 'UTF-8');
                    $sortIngreso = $p['fecha_registro']
                        ? htmlspecialchars(date('Y-m-d', strtotime($p['fecha_registro'])), ENT_QUOTES, 'UTF-8')
                        : '';
                ?>
                    <tr class="pac-row" data-search="<?= htmlspecialchars($busqueda) ?>">
                        <td class="rx-pac-td-nombre" data-sort-value="<?= $sortNombre ?>">
                            <div class="rx-pac-cell-nombre">
                                <span class="rx-pac-avatar" aria-hidden="true"><?= htmlspecialchars($iniciales ?: '?') ?></span>
                                <strong><?= htmlspecialchars($p['nombre_completo']) ?></strong>
                            </div>
                        </td>
                        <td class="rx-pac-td-cedula" data-sort-value="<?= $sortCedula ?>"><?= htmlspecialchars($p['cedula'] ?: '—') ?></td>
                        <td class="rx-pac-td-edad" data-sort-value="<?= $sortEdad ?>"><?= $p['edad'] ? (int)$p['edad'] . ' años' : '—' ?></td>
                        <td class="rx-pac-td-email" data-sort-value="<?= $sortCorreo ?>"><?= htmlspecialchars($p['correo'] ?: '—') ?></td>
                        <td class="rx-pac-td-telefono" data-sort-value="<?= $sortTelefono ?>"><?= htmlspecialchars($p['telefono'] ?: '—') ?></td>
                        <td class="rx-pac-td-direccion" data-sort-value="<?= $sortDireccion ?>"><?= htmlspecialchars($p['direccion'] ?: '—') ?></td>
                        <td><span class="badge badge-accent"><?= (int)$p['total_citas'] ?></span></td>
                        <td><span class="badge badge-purple"><?= (int)$p['total_informes'] ?></span></td>
                        <td class="rx-pac-td-ingreso" data-sort-value="<?= $sortIngreso ?>"><?= htmlspecialchars($fecha_ing) ?></td>
                        <td class="rx-td-acciones" style="white-space:nowrap;">
                            <div style="display:inline-flex;gap:6px;align-items:center;justify-content:flex-end;">
                                <button type="button" onclick="abrirGestionPacienteEco(<?= (int)$p['id'] ?>)" class="btn-primary" style="padding:6px 12px;font-size:12px;">
                                    <i class="fa-solid fa-folder-open"></i> Gestionar
                                </button>
                                <button type="button"
                                    onclick='abrirProgramarCitaEco(<?= (int)$p['id'] ?>, <?= json_encode($p['nombre_completo'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'
                                    class="btn-secondary" style="padding:6px 12px;font-size:12px;">
                                    <i class="fa-solid fa-calendar-plus"></i> Cita
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div id="pac-empty-state" style="text-align:center;padding:40px 20px;color:var(--text-muted);display:none;">
        <i class="fa-solid fa-magnifying-glass" style="font-size:2rem;opacity:.4;display:block;margin-bottom:10px;"></i>
        <p style="margin:0;">No se encontraron pacientes con ese criterio.</p>
    </div>
</div>

<?php endif; ?>

<script>
(function() {
    const input = document.getElementById('buscador-pacientes');
    const rows  = document.querySelectorAll('.pac-row');
    const empty = document.getElementById('pac-empty-state');
    const count = document.getElementById('pac-count');
    if (!input || !rows.length) return;

    input.addEventListener('input', () => {
        const q = input.value.trim().toLowerCase();
        let visible = 0;
        rows.forEach(r => {
            const match = r.dataset.search.includes(q);
            r.style.display = match ? '' : 'none';
            if (match) visible++;
        });
        count.textContent = visible;
        empty.style.display = visible === 0 ? 'block' : 'none';
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
