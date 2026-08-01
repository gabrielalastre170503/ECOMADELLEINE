<?php
session_start();
include __DIR__ . '/../core/conexion.php';

if (!isset($_SESSION['usuario_id'])) { header('Location: ' . eco_url('login')); exit; }
if ($_SESSION['rol'] !== 'ecografista') { header('Location: ' . eco_url('dashboard')); exit; }

$ecografista_id = (int)$_SESSION['usuario_id'];

$solicitudes = [];
if ($s = $conex->prepare("
    SELECT c.id, c.fecha_solicitud, c.fecha_cita, c.motivo_consulta, c.motivo_principal,
           c.modalidad, c.notas_paciente,
           u.id AS paciente_id, u.nombre_completo paciente, u.cedula, u.correo,
           t.nombre AS tipo_nombre, t.icono AS tipo_icono
    FROM citas c
    JOIN usuarios u ON u.id=c.paciente_id
    LEFT JOIN tipos_ecografias t ON t.id=c.tipo_ecografia_id
    WHERE c.ecografista_id=? AND c.estado='pendiente'
    ORDER BY c.fecha_solicitud ASC")) {
    $s->bind_param('i', $ecografista_id);
    $s->execute();
    $solicitudes = $s->get_result()->fetch_all(MYSQLI_ASSOC);
    $s->close();
}

$page_title    = 'Solicitudes de Cita';
$page_subtitle = 'Solicitudes pendientes que esperan tu confirmación';
$active_section = 'citas';

$page_head_extra = '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">';

ob_start();
?>

<?php if (empty($solicitudes)): ?>
    <div class="card" style="text-align:center;padding:60px 20px;">
        <i class="fa-solid fa-inbox" style="font-size:3rem;color:var(--success);opacity:.5;margin-bottom:14px;"></i>
        <h3 style="margin:0 0 6px;color:var(--text-primary);">¡Bandeja vacía!</h3>
        <p style="color:var(--text-secondary);margin:0;font-size:13.5px;">No tienes solicitudes pendientes por procesar.</p>
    </div>
<?php else: ?>

<div class="card" style="margin-bottom:14px;padding:14px 18px;display:flex;align-items:center;gap:12px;background:rgba(245,158,11,.06);border-color:rgba(245,158,11,.3);">
    <i class="fa-solid fa-bell" style="color:#b45309;font-size:18px;"></i>
    <div style="flex:1;">
        <strong style="color:#b45309;font-size:14px;"><?= count($solicitudes) ?> solicitud<?= count($solicitudes) > 1 ? 'es' : '' ?> pendiente<?= count($solicitudes) > 1 ? 's' : '' ?></strong>
        <div style="color:var(--text-secondary);font-size:12.5px;">Confirma o propón una nueva fecha para cada solicitud.</div>
    </div>
</div>

<div class="card" style="padding:0;overflow:hidden;">
    <div class="data-table" style="border:none;">
        <table>
            <thead>
                <tr>
                    <th>Paciente</th>
                    <th>Tipo de Estudio</th>
                    <th>Fecha Propuesta</th>
                    <th>Motivo</th>
                    <th>Solicitada</th>
                    <th style="text-align:right;">Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($solicitudes as $s):
                $iniciales = '';
                foreach (explode(' ', trim($s['paciente'])) as $p) if (strlen($iniciales) < 2 && $p !== '') $iniciales .= strtoupper($p[0]);
                $fecha_prop  = $s['fecha_cita'] ? date('d/m/Y H:i', strtotime($s['fecha_cita'])) : 'Sin fecha propuesta';
                $fecha_solic = date('d/m/Y', strtotime($s['fecha_solicitud']));
                $motivo = $s['motivo_consulta'] ?: $s['motivo_principal'] ?: 'No especificado';
            ?>
                <tr class="sol-row" style="cursor:pointer;" title="Ver detalles del paciente y la solicitud"
                    data-cita-id="<?= (int)$s['id'] ?>"
                    data-paciente="<?= htmlspecialchars($s['paciente'], ENT_QUOTES, 'UTF-8') ?>"
                    data-fecha-propuesta="<?= htmlspecialchars($fecha_prop, ENT_QUOTES, 'UTF-8') ?>">
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div style="width:34px;height:34px;background:linear-gradient(135deg,var(--accent),#38bdf8);color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11.5px;flex-shrink:0;"><?= htmlspecialchars($iniciales ?: '?') ?></div>
                            <div style="min-width:0;">
                                <strong style="color:var(--text-primary);"><?= htmlspecialchars($s['paciente']) ?></strong>
                                <div style="font-size:11.5px;color:var(--text-muted);"><?= htmlspecialchars($s['cedula'] ?: 'Sin cédula') ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <?php if ($s['tipo_nombre']): ?>
                            <span style="display:inline-flex;align-items:center;gap:5px;">
                                <i class="<?= htmlspecialchars($s['tipo_icono'] ?: 'fa-solid fa-wave-square') ?>" style="color:var(--accent);"></i>
                                <?= htmlspecialchars($s['tipo_nombre']) ?>
                            </span>
                        <?php else: ?>
                            <span style="color:var(--text-muted);">—</span>
                        <?php endif; ?>
                    </td>
                    <td style="white-space:nowrap;"><?= htmlspecialchars($fecha_prop) ?></td>
                    <td style="max-width:240px;font-size:12.5px;color:var(--text-secondary);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($motivo) ?>"><?= htmlspecialchars(mb_strimwidth($motivo, 0, 60, '…')) ?></td>
                    <td style="font-size:12px;color:var(--text-muted);white-space:nowrap;"><?= htmlspecialchars($fecha_solic) ?></td>
                    <td style="text-align:right;white-space:nowrap;">
                        <button type="button" onclick="confirmarSolicitud(<?= (int)$s['id'] ?>)" class="btn-primary" style="padding:6px 12px;font-size:12px;background:linear-gradient(135deg,#22c55e,#16a34a);box-shadow:0 4px 12px rgba(34,197,94,.3);">
                            <i class="fa-solid fa-check"></i> Confirmar
                        </button>
                        <button type="button" onclick="abrirProponerFechaEco(<?= (int)$s['id'] ?>)" class="btn-secondary" style="padding:6px 12px;font-size:12px;">
                            <i class="fa-solid fa-clock"></i> Posponer
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; ?>

<script>
function confirmarSolicitud(id) {
    fetch((window.ECO_BASE || '') + 'api/check_conflict.php?cita_id=' + encodeURIComponent(id))
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (d.conflict) {
                window.abrirConfirmCita({
                    icon: 'fa-solid fa-triangle-exclamation', tone: 'is-warning',
                    title: 'Conflicto de horario',
                    text: 'Hay un conflicto con otra cita en ese horario. ¿Quieres proponer una nueva fecha al paciente?',
                    acceptLabel: 'Proponer fecha', acceptIcon: 'fa-solid fa-clock',
                    onAccept: function () { abrirProponerFechaEco(id); }
                });
            } else {
                window.abrirConfirmCita({
                    icon: 'fa-solid fa-calendar-check', tone: 'is-success',
                    title: '¿Confirmar esta cita?',
                    text: 'La cita quedará confirmada y el paciente recibirá la notificación.',
                    acceptLabel: 'Confirmar', acceptIcon: 'fa-solid fa-check',
                    acceptStyle: 'background:linear-gradient(135deg,#22c55e,#16a34a);box-shadow:0 4px 12px rgba(34,197,94,.3);',
                    /* POST + token CSRF: confirmar cambia datos y el enlace GET
                       anterior se podia disparar desde otra web. */
                    onAccept: function () { window.ecoPost((window.ECO_BASE || '') + 'api/confirmar_cita.php', { cita_id: id }); }
                });
            }
        })
        .catch(function (err) { alert('Error al verificar conflicto: ' + err.message); });
}
</script>

<style>
.sol-row:hover td { background:var(--bg-hover); }
.sd-head { display:flex; align-items:center; gap:14px; margin-bottom:6px; }
.sd-avatar { width:52px; height:52px; border-radius:50%; flex-shrink:0; background:linear-gradient(135deg,var(--accent),#38bdf8); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:17px; }
.sd-head h2 { margin:0; font-size:18px; font-weight:800; color:var(--text-primary); }
.sd-head .sd-sub { font-size:12.5px; color:var(--text-muted); margin-top:2px; }
.sd-section { margin-top:18px; }
.sd-section__title { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:var(--accent-text); display:flex; align-items:center; gap:7px; margin-bottom:11px; }
.sd-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:12px 18px; }
.sd-item { min-width:0; }
.sd-label { font-size:10.5px; font-weight:700; text-transform:uppercase; letter-spacing:.4px; color:var(--text-muted); }
.sd-value { font-size:13.5px; color:var(--text-primary); font-weight:600; margin-top:2px; word-break:break-word; }
.sd-value i { color:var(--accent); margin-right:4px; }
.sd-block { background:var(--bg-muted); border:1px solid var(--border); border-radius:10px; padding:11px 13px; font-size:13px; color:var(--text-secondary); line-height:1.55; white-space:pre-wrap; word-break:break-word; }
.sd-block.is-empty { color:var(--text-muted); font-style:italic; }
.sd-foot { display:flex; gap:10px; justify-content:flex-end; margin-top:24px; flex-wrap:wrap; }
@media (max-width:520px){ .sd-grid { grid-template-columns:1fr; } }

/* Modal de confirmación */
.cf-icon { width:70px; height:70px; margin:0 auto 16px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:32px; background:var(--accent-soft); color:var(--accent-text); animation:cfPop .3s cubic-bezier(.34,1.56,.64,1); }
.cf-icon.is-success { background:rgba(34,197,94,.14); color:#16a34a; }
.cf-icon.is-warning { background:rgba(245,158,11,.16); color:#b45309; }
@keyframes cfPop { 0% { transform:scale(.5); opacity:0; } 100% { transform:scale(1); opacity:1; } }
.cf-title { margin:0 0 9px; font-size:19px; font-weight:800; color:var(--text-primary); }
.cf-text { margin:0 auto 22px; max-width:330px; font-size:13.5px; color:var(--text-secondary); line-height:1.55; }
.cf-foot { display:flex; gap:10px; justify-content:center; }
</style>

<div id="eco-modal-detalle-solicitud" class="eco-modal" aria-hidden="true" role="dialog">
    <div class="eco-modal__dialog" style="max-width:600px;">
        <div class="eco-modal__main" style="padding-top:24px;">
            <button type="button" class="eco-modal__close" data-eco-modal-close aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
            <div id="sol-detalle-body"><p style="text-align:center;color:var(--text-muted);padding:30px;"><i class="fa-solid fa-spinner fa-spin"></i> Cargando…</p></div>
        </div>
    </div>
</div>

<div id="eco-modal-confirmar-cita" class="eco-modal" aria-hidden="true" role="dialog">
    <div class="eco-modal__dialog" style="max-width:420px;">
        <div class="eco-modal__main" style="padding:32px 26px;text-align:center;">
            <button type="button" class="eco-modal__close" data-eco-modal-close aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
            <div class="cf-icon" id="cf-icon"><i class="fa-solid fa-circle-question"></i></div>
            <h2 class="cf-title" id="cf-title">¿Confirmar esta cita?</h2>
            <p class="cf-text" id="cf-text"></p>
            <div class="cf-foot">
                <button type="button" class="btn-secondary" data-eco-modal-close><i class="fa-solid fa-xmark"></i> Cancelar</button>
                <button type="button" class="btn-primary" id="cf-accept"><i class="fa-solid fa-check"></i> Confirmar</button>
            </div>
        </div>
    </div>
</div>

<?php
include __DIR__ . '/../layouts/partials/modal_proponer_fecha_ecografista.php';

$page_content = ob_get_clean();

$page_scripts_extra = <<<'HTML'
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
<script>
(function () {
    var MODAL = 'eco-modal-proponer-fecha-eco';
    var fpInstance = null;

    window.abrirProponerFechaEco = function (citaId) {
        var row = document.querySelector('tr[data-cita-id="' + String(citaId) + '"]');
        var paciente = row ? row.getAttribute('data-paciente') : '—';
        var fechaTxt = row ? row.getAttribute('data-fecha-propuesta') : '—';
        document.getElementById('eco-proponer-paciente-nombre').textContent = paciente || '—';
        document.getElementById('eco-proponer-fecha-actual').textContent = fechaTxt || '—';
        document.getElementById('eco-proponer-cita-id').value = citaId;
        document.getElementById('eco-proponer-motivo').value = '';
        document.getElementById('eco-proponer-error').style.display = 'none';

        // Reset + enriquecer panel de info
        var propSet = function (id, txt) { var el = document.getElementById(id); if (el) el.textContent = txt; };
        propSet('eco-prop-sub', '—'); propSet('eco-prop-estudio', '—'); propSet('eco-prop-modalidad', '—');
        var propEstado = document.getElementById('eco-prop-estado'); if (propEstado) { propEstado.textContent = '—'; propEstado.className = 'badge'; }
        var propMotivoBox = document.getElementById('eco-prop-motivo-box'); if (propMotivoBox) propMotivoBox.style.display = 'none';
        fetch((window.ECO_BASE || '') + 'api/get_solicitud_details.php?id=' + encodeURIComponent(citaId))
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d || d.error) return;
                propSet('eco-proponer-paciente-nombre', d.paciente_nombre || paciente);
                propSet('eco-prop-sub', (d.paciente_cedula || 'Sin cédula') + (d.paciente_edad ? ' · ' + d.paciente_edad + ' años' : ''));
                propSet('eco-prop-estudio', d.tipo_nombre || 'No especificado');
                propSet('eco-prop-modalidad', d.modalidad_formateada || d.modalidad || '—');
                if (d.fecha_propuesta_formateada) propSet('eco-proponer-fecha-actual', d.fecha_propuesta_formateada);
                if (propEstado && d.estado) { propEstado.textContent = d.estado.charAt(0).toUpperCase() + d.estado.slice(1); propEstado.className = 'badge badge-warning'; }
                if (propMotivoBox && d.motivo_consulta) { propMotivoBox.style.display = ''; propSet('eco-prop-motivo-text', d.motivo_consulta); }
            })
            .catch(function () {});

        EcoModal.open(MODAL);
        var inp = document.getElementById('eco-proponer-calendario');
        if (fpInstance) {
            fpInstance.destroy();
            fpInstance = null;
        }
        inp.value = '';
        fpInstance = flatpickr(inp, {
            enableTime: true,
            dateFormat: 'Y-m-d H:i',
            altInput: true,
            altFormat: 'd/m/Y h:i K',
            locale: flatpickr.l10ns.es,
            minuteIncrement: 15,
            time_24hr: false
        });
    };

    document.getElementById('eco-form-proponer-fecha').addEventListener('submit', function (e) {
        e.preventDefault();
        var err = document.getElementById('eco-proponer-error');
        err.style.display = 'none';
        var fd = new FormData(e.target);
        var btn = document.getElementById('eco-proponer-submit');
        btn.disabled = true;
        fetch((window.ECO_BASE || '') + 'api/guardar_propuesta.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                btn.disabled = false;
                if (d.success) {
                    window.location.reload();
                } else {
                    err.textContent = d.message || 'No se pudo enviar la propuesta.';
                    err.style.display = 'block';
                }
            })
            .catch(function (x) {
                btn.disabled = false;
                err.textContent = x.message || 'Error de red';
                err.style.display = 'block';
            });
    });

    /* ── Detalle de la solicitud (click en la fila) ── */
    var SOLMODAL = 'eco-modal-detalle-solicitud';
    var solBody  = document.getElementById('sol-detalle-body');

    function escSol(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
    function inicialesSol(nombre) { var p = String(nombre || '').trim().split(/\s+/), i = ''; for (var k = 0; k < p.length && i.length < 2; k++) { if (p[k]) i += p[k][0].toUpperCase(); } return i || '?'; }
    function sdItem(label, value) { return '<div class="sd-item"><div class="sd-label">' + label + '</div><div class="sd-value">' + escSol(value || '—') + '</div></div>'; }
    function sdBlock(text) { var t = (text || '').trim(); return t ? '<div class="sd-block">' + escSol(t) + '</div>' : '<div class="sd-block is-empty">No especificado</div>'; }

    window.verDetalleSolicitud = function (citaId) {
        solBody.innerHTML = '<p style="text-align:center;color:var(--text-muted);padding:30px;"><i class="fa-solid fa-spinner fa-spin"></i> Cargando…</p>';
        EcoModal.open(SOLMODAL);
        fetch((window.ECO_BASE || '') + 'api/get_solicitud_details.php?id=' + encodeURIComponent(citaId))
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.error) { solBody.innerHTML = '<p style="color:#b91c1c;padding:16px;">' + escSol(d.error) + '</p>'; return; }
                var estudio = d.tipo_nombre
                    ? '<i class="' + escSol(d.tipo_icono || 'fa-solid fa-wave-square') + '"></i> ' + escSol(d.tipo_nombre)
                    : 'No especificado';
                solBody.innerHTML =
                    '<div class="sd-head">'
                        + '<div class="sd-avatar">' + inicialesSol(d.paciente_nombre) + '</div>'
                        + '<div><h2>' + escSol(d.paciente_nombre || 'Paciente') + '</h2>'
                        + '<div class="sd-sub">' + escSol(d.paciente_cedula || 'Sin cédula') + '</div></div>'
                    + '</div>'
                    + '<div class="sd-section"><div class="sd-section__title"><i class="fa-solid fa-user"></i> Datos del paciente</div>'
                        + '<div class="sd-grid">'
                            + sdItem('Cédula', d.paciente_cedula)
                            + sdItem('Edad', d.paciente_edad ? d.paciente_edad + ' años' : '—')
                            + sdItem('Fecha de nacimiento', d.paciente_fnac_formateada)
                            + sdItem('Correo', d.paciente_correo)
                            + sdItem('Paciente desde', d.paciente_registro_formateada)
                        + '</div>'
                    + '</div>'
                    + '<div class="sd-section"><div class="sd-section__title"><i class="fa-solid fa-clipboard-list"></i> Datos de la solicitud</div>'
                        + '<div class="sd-grid">'
                            + '<div class="sd-item"><div class="sd-label">Tipo de estudio</div><div class="sd-value">' + estudio + '</div></div>'
                            + sdItem('Tipo de cita', d.tipo_cita_formateado)
                            + sdItem('Modalidad', d.modalidad_formateada)
                            + sdItem('Fecha propuesta', d.fecha_propuesta_formateada)
                            + sdItem('Fecha de solicitud', d.fecha_solicitud_formateada)
                        + '</div>'
                    + '</div>'
                    + '<div class="sd-section"><div class="sd-section__title"><i class="fa-solid fa-notes-medical"></i> Antecedentes médicos y detalles</div>' + sdBlock(d.motivo_consulta) + '</div>'
                    + (d.motivo_principal ? '<div class="sd-section"><div class="sd-section__title"><i class="fa-solid fa-list-check"></i> Servicios solicitados</div>' + sdBlock(d.motivo_principal) + '</div>' : '')
                    + (d.notas_paciente ? '<div class="sd-section"><div class="sd-section__title"><i class="fa-solid fa-comment-dots"></i> Notas del paciente</div>' + sdBlock(d.notas_paciente) + '</div>' : '')
                    + '<div class="sd-foot">'
                        + '<button type="button" class="btn-secondary" data-eco-modal-close><i class="fa-solid fa-xmark"></i> Cerrar</button>'
                        + '<button type="button" class="btn-secondary" onclick="EcoModal.close(\'' + SOLMODAL + '\');abrirProponerFechaEco(' + citaId + ');"><i class="fa-solid fa-clock"></i> Posponer</button>'
                        + '<button type="button" class="btn-primary" style="background:linear-gradient(135deg,#22c55e,#16a34a);box-shadow:0 4px 12px rgba(34,197,94,.3);" onclick="EcoModal.close(\'' + SOLMODAL + '\');confirmarSolicitud(' + citaId + ');"><i class="fa-solid fa-check"></i> Confirmar</button>'
                    + '</div>';
            })
            .catch(function () { solBody.innerHTML = '<p style="color:#b91c1c;padding:16px;">No se pudieron cargar los detalles.</p>'; });
    };

    document.querySelectorAll('tr.sol-row').forEach(function (row) {
        row.addEventListener('click', function (e) {
            if (e.target.closest('button')) return;
            verDetalleSolicitud(row.getAttribute('data-cita-id'));
        });
    });

    /* ── Modal de confirmación reutilizable ── */
    var CFMODAL  = 'eco-modal-confirmar-cita';
    var cfAccept = document.getElementById('cf-accept');
    var cfCb     = null;

    window.abrirConfirmCita = function (opts) {
        opts = opts || {};
        document.getElementById('cf-icon').className = 'cf-icon ' + (opts.tone || '');
        document.getElementById('cf-icon').innerHTML = '<i class="' + (opts.icon || 'fa-solid fa-circle-question') + '"></i>';
        document.getElementById('cf-title').textContent = opts.title || '¿Confirmar?';
        document.getElementById('cf-text').textContent = opts.text || '';
        cfAccept.innerHTML = '<i class="' + (opts.acceptIcon || 'fa-solid fa-check') + '"></i> ' + (opts.acceptLabel || 'Confirmar');
        cfAccept.style.cssText = opts.acceptStyle || '';
        cfCb = typeof opts.onAccept === 'function' ? opts.onAccept : null;
        EcoModal.open(CFMODAL);
    };

    cfAccept.addEventListener('click', function () {
        EcoModal.close(CFMODAL);
        var cb = cfCb; cfCb = null;
        if (cb) cb();
    });
})();
</script>
HTML;

include __DIR__ . '/../layouts/shell.php';
