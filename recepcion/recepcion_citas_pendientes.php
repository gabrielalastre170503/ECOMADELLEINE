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

$ecografistas = [];
if ($r = $conex->query("SELECT id, nombre_completo FROM usuarios WHERE rol = 'ecografista' AND estado = 'aprobado' ORDER BY nombre_completo ASC")) {
    while ($row = $r->fetch_assoc()) {
        $ecografistas[] = $row;
    }
    $r->free();
}

$solicitudes = [];
if ($q = $conex->query("
    SELECT c.id, c.motivo_consulta, c.motivo_principal, c.fecha_solicitud, c.fecha_cita,
           u.nombre_completo AS paciente_nombre, u.cedula AS paciente_cedula,
           u.correo AS paciente_correo, TIMESTAMPDIFF(YEAR, u.fecha_nacimiento, CURDATE()) AS paciente_edad,
           t.nombre AS tipo_nombre, t.icono AS tipo_icono
    FROM citas c
    JOIN usuarios u ON c.paciente_id = u.id
    LEFT JOIN tipos_ecografias t ON t.id = c.tipo_ecografia_id
    WHERE c.estado = 'pendiente'
    ORDER BY c.fecha_solicitud DESC
")) {
    while ($row = $q->fetch_assoc()) {
        $solicitudes[] = $row;
    }
    $q->free();
}

$msg_ok = $msg_err = '';
if (isset($_GET['status']) && $_GET['status'] === 'cita_programada') {
    $msg_ok = 'La cita se programó y quedó confirmada.';
}
if (isset($_GET['error'])) {
    $msg_err = match ($_GET['error']) {
        'no_psicologo'         => 'Debes seleccionar un ecografista antes de confirmar.',
        'programacion_fallida' => 'No se pudo guardar la programación. Intenta de nuevo.',
        default                => 'Ocurrió un error al procesar la solicitud.',
    };
}

$page_title    = 'Citas pendientes';
$page_subtitle = 'Asigna ecografista y fecha a cada solicitud';
$active_section = 'solicitudes-generales';

$page_head_extra = '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">';

ob_start();
?>

<?php if ($msg_ok): ?>
    <div class="card" style="border-left:4px solid var(--success);background:rgba(34,197,94,.06);margin-bottom:18px;">
        <strong style="color:#15803d;"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($msg_ok) ?></strong>
    </div>
<?php endif; ?>
<?php if ($msg_err): ?>
    <div class="card" style="border-left:4px solid var(--danger);background:rgba(239,68,68,.06);margin-bottom:18px;">
        <strong style="color:#b91c1c;"><i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($msg_err) ?></strong>
    </div>
<?php endif; ?>

<?php if (empty($solicitudes)): ?>
    <div class="card" style="text-align:center;padding:60px 20px;">
        <i class="fa-solid fa-inbox" style="font-size:3rem;color:var(--success);opacity:.5;margin-bottom:14px;"></i>
        <h3 style="margin:0 0 6px;color:var(--text-primary);">¡Bandeja vacía!</h3>
        <p style="color:var(--text-secondary);margin:0;font-size:13.5px;">No hay solicitudes de cita pendientes por asignar.</p>
    </div>
<?php else: ?>

<div class="card" style="margin-bottom:14px;padding:14px 18px;display:flex;align-items:center;gap:12px;background:rgba(245,158,11,.06);border-color:rgba(245,158,11,.3);">
    <i class="fa-solid fa-bell" style="color:#b45309;font-size:18px;"></i>
    <div style="flex:1;">
        <strong style="color:#b45309;font-size:14px;"><?= count($solicitudes) ?> solicitud<?= count($solicitudes) > 1 ? 'es' : '' ?> pendiente<?= count($solicitudes) > 1 ? 's' : '' ?></strong>
        <div style="color:var(--text-secondary);font-size:12.5px;">Asigna ecografista y fecha a cada solicitud.</div>
    </div>
</div>

<div class="card" style="padding:0;overflow:hidden;">
    <div class="data-table" style="border:none;">
        <table>
            <thead>
                <tr>
                    <th>Paciente</th>
                    <th>Edad</th>
                    <th>Tipo de estudio</th>
                    <th>Fecha propuesta</th>
                    <th>Motivo</th>
                    <th>Recibida</th>
                    <th style="text-align:right;">Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($solicitudes as $s):
                $iniciales = '';
                foreach (explode(' ', trim((string)$s['paciente_nombre'])) as $p) {
                    if ($p !== '' && strlen($iniciales) < 2) { $iniciales .= strtoupper($p[0]); }
                }
                $fechaProp  = !empty($s['fecha_cita']) ? date('d/m/Y H:i', strtotime($s['fecha_cita'])) : 'Sin fecha propuesta';
                $fechaRecib = !empty($s['fecha_solicitud']) ? date('d/m/Y', strtotime($s['fecha_solicitud'])) : '—';
                $motivo = trim((string)($s['motivo_consulta'] ?? '')) ?: trim((string)($s['motivo_principal'] ?? '')) ?: 'No especificado';
                $correo = !empty($s['paciente_correo']) ? $s['paciente_correo'] : 'Sin correo';
            ?>
                <tr class="sol-row">
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div style="width:34px;height:34px;background:linear-gradient(135deg,var(--accent),#38bdf8);color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11.5px;flex-shrink:0;"><?= htmlspecialchars($iniciales ?: '?') ?></div>
                            <div style="min-width:0;">
                                <strong style="color:var(--text-primary);"><?= htmlspecialchars($s['paciente_nombre']) ?></strong>
                                <div style="font-size:11.5px;color:var(--text-muted);" title="<?= htmlspecialchars($correo) ?>">
                                    <?= htmlspecialchars($s['paciente_cedula'] ?: 'Sin cédula') ?> · <?= htmlspecialchars($correo) ?>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td style="white-space:nowrap;"><?= !empty($s['paciente_edad']) ? (int)$s['paciente_edad'] . ' años' : '—' ?></td>
                    <td>
                        <?php if (!empty($s['tipo_nombre'])): ?>
                            <span style="display:inline-flex;align-items:center;gap:5px;">
                                <i class="<?= htmlspecialchars($s['tipo_icono'] ?: 'fa-solid fa-wave-square') ?>" style="color:var(--accent);"></i>
                                <?= htmlspecialchars($s['tipo_nombre']) ?>
                            </span>
                        <?php else: ?>
                            <span style="color:var(--text-muted);">—</span>
                        <?php endif; ?>
                    </td>
                    <td style="white-space:nowrap;"><?= htmlspecialchars($fechaProp) ?></td>
                    <td style="max-width:220px;font-size:12.5px;color:var(--text-secondary);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($motivo) ?>"><?= htmlspecialchars(mb_strimwidth($motivo, 0, 60, '…')) ?></td>
                    <td style="font-size:12px;color:var(--text-muted);white-space:nowrap;"><?= htmlspecialchars($fechaRecib) ?></td>
                    <td style="text-align:right;white-space:nowrap;">
                        <button type="button" class="btn-primary" style="padding:6px 12px;font-size:12px;" onclick="abrirModalAsignarCitaRx(<?= (int)$s['id'] ?>)">
                            <i class="fa-solid fa-calendar-check"></i> Asignar y programar
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
.sol-row:hover td { background: var(--bg-hover); }
</style>

<?php endif; ?>

<div id="eco-modal-asignar-cita-rx" class="eco-modal" aria-hidden="true" role="dialog">
    <div class="eco-modal__dialog" style="max-width:520px;max-height:90vh;overflow-y:auto;">
        <div class="eco-modal__main" style="padding-top:22px;">
            <button type="button" class="eco-modal__close" onclick="cerrarModalAsignarRx()" aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
            <h2 style="margin:0 0 6px;font-size:1.1rem;">Asignar cita</h2>
            <p style="margin:0 0 16px;font-size:13px;color:var(--text-secondary);">Paciente: <strong id="asignar-rx-paciente">…</strong></p>
            <p style="margin:0 0 8px;font-size:12.5px;color:var(--text-muted);">Motivo</p>
            <p id="asignar-rx-motivo" style="margin:0 0 14px;padding:10px 12px;background:var(--bg-muted);border-radius:8px;font-size:13px;">…</p>
            <p style="margin:0 0 6px;font-size:12.5px;color:var(--text-muted);">Profesional indicado en la solicitud</p>
            <p id="asignar-rx-solicitado" style="margin:0 0 16px;font-size:13.5px;font-weight:600;">—</p>

            <form id="form-asignar-cita-rx" method="post">
                <input type="hidden" name="ajax" value="1">
                <input type="hidden" name="cita_id" id="asignar-rx-cita-id" value="">
                <label style="display:block;font-size:12.5px;font-weight:600;margin-bottom:6px;">Ecografista</label>
                <select name="ecografista_id" id="asignar-rx-eco-id" required
                        style="width:100%;padding:10px 12px;border:1.5px solid var(--border);border-radius:8px;margin-bottom:14px;font-family:inherit;background:var(--bg-surface);color:var(--text-primary);">
                    <option value="">— Seleccione —</option>
                    <?php foreach ($ecografistas as $e): ?>
                        <option value="<?= (int)$e['id'] ?>"><?= htmlspecialchars($e['nombre_completo']) ?></option>
                    <?php endforeach; ?>
                </select>
                <label style="display:block;font-size:12.5px;font-weight:600;margin-bottom:6px;">Fecha y hora</label>
                <input type="text" name="fecha_cita" id="calendario-asignar-rx" placeholder="Seleccionar…" required
                       style="width:100%;padding:10px 12px;border:1.5px solid var(--border);border-radius:8px;margin-bottom:16px;font-family:inherit;background:var(--bg-surface);color:var(--text-primary);">
                <div style="display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap;">
                    <button type="button" class="btn-secondary" onclick="cerrarModalAsignarRx();">Cancelar</button>
                    <button type="submit" class="btn-primary">Confirmar cita</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$page_content = ob_get_clean();

$page_scripts_extra = <<<'HTML'
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
<script>
(function () {
    var modalId = 'eco-modal-asignar-cita-rx';
    var modal = document.getElementById(modalId);
    var form = document.getElementById('form-asignar-cita-rx');
    var fp = null;

    window.abrirModalAsignarCitaRx = function (citaId) {
        if (!modal) return;
        document.getElementById('asignar-rx-paciente').textContent = 'Cargando…';
        document.getElementById('asignar-rx-motivo').textContent = '…';
        document.getElementById('asignar-rx-solicitado').textContent = '—';
        document.getElementById('asignar-rx-cita-id').value = citaId;
        if (typeof EcoModal !== 'undefined') EcoModal.open(modalId);

        fetch((window.ECO_BASE || '') + 'api/get_cita_details_secretaria.php?cita_id=' + encodeURIComponent(citaId))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.error) {
                    document.getElementById('asignar-rx-paciente').textContent = 'Error';
                    document.getElementById('asignar-rx-motivo').textContent = data.error;
                    return;
                }
                document.getElementById('asignar-rx-paciente').textContent = data.paciente_nombre || '';
                document.getElementById('asignar-rx-motivo').textContent = data.motivo_consulta || '—';
                document.getElementById('asignar-rx-solicitado').textContent = data.profesional_solicitado_nombre || 'No especificado';
                var sel = document.getElementById('asignar-rx-eco-id');
                if (sel && data.profesional_solicitado_id) {
                    sel.value = String(data.profesional_solicitado_id);
                }
            });

        if (fp) { fp.destroy(); fp = null; }
        var cal = document.getElementById('calendario-asignar-rx');
        if (cal && window.flatpickr) {
            var loc = (flatpickr.l10ns && flatpickr.l10ns.es) ? flatpickr.l10ns.es : undefined;
            fp = flatpickr(cal, {
                enableTime: true,
                time_24hr: false,
                dateFormat: 'Y-m-d H:i',
                locale: loc,
                minuteIncrement: 15,
                minDate: 'today'
            });
        }
    };

    window.cerrarModalAsignarRx = function () {
        if (typeof EcoModal !== 'undefined') EcoModal.close(modalId);
        if (form) form.reset();
        if (fp) { fp.destroy(); fp = null; }
    };

    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var btn = form.querySelector('button[type="submit"]');
            var fd = new FormData(form);
            if (btn) { btn.disabled = true; btn.textContent = 'Guardando…'; }
            fetch((window.ECO_BASE || '') + 'api/guardar_cita.php', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success) {
                        // Este bloque es un nowdoc: PHP no interpola aquí, así que
                        // la URL se arma con ECO_BASE como el resto de la página.
                        window.location.href = (window.ECO_BASE || '') + 'citas-pendientes?status=cita_programada';
                    } else {
                        alert(data.message || 'No se pudo programar.');
                    }
                })
                .catch(function () { alert('Error de red.'); })
                .finally(function () {
                    if (btn) { btn.disabled = false; btn.textContent = 'Confirmar cita'; }
                });
        });
    }
})();
</script>
HTML;

include __DIR__ . '/../layouts/shell.php';
