<?php
session_start();
include __DIR__ . '/../core/conexion.php';
require_once __DIR__ . '/../lib/facturacion/facturacion.php';

if (!isset($_SESSION['usuario_id'])) { header('Location: ' . eco_url('login')); exit; }
if ($_SESSION['rol'] !== 'ecografista') { header('Location: ' . eco_url('dashboard')); exit; }

$ecografista_id = (int)$_SESSION['usuario_id'];

$citas = [];
if ($s = $conex->prepare("
    SELECT c.id, c.fecha_cita, c.motivo_consulta, c.motivo_principal, c.estado, c.estado_pago, c.modalidad,
           u.id AS paciente_id, u.nombre_completo paciente, u.cedula, u.correo,
           TIMESTAMPDIFF(YEAR, u.fecha_nacimiento, CURDATE()) AS edad,
           t.nombre AS tipo_nombre, t.icono AS tipo_icono
    FROM citas c
    JOIN usuarios u ON u.id=c.paciente_id
    LEFT JOIN tipos_ecografias t ON t.id=c.tipo_ecografia_id
    WHERE c.ecografista_id=? AND c.estado IN ('confirmada','reprogramada')
          AND c.fecha_cita >= NOW()
    ORDER BY c.fecha_cita ASC")) {
    $s->bind_param('i', $ecografista_id);
    $s->execute();
    $citas = $s->get_result()->fetch_all(MYSQLI_ASSOC);
    $s->close();
}

$page_title    = 'Mis Próximas Citas';
$page_subtitle = 'Citas confirmadas y reprogramadas que tienes pendientes';
$active_section = 'proximas-citas';
$page_header_actions = '<a href="' . eco_url('mi-agenda') . '" class="btn-secondary"><i class="fa-solid fa-calendar-week"></i> Ver en Calendario</a>';
$page_head_extra = '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">';

ob_start();
?>

<?php if (empty($citas)): ?>
    <div class="card" style="text-align:center;padding:60px 20px;">
        <i class="fa-regular fa-calendar" style="font-size:3rem;color:var(--text-muted);opacity:.4;margin-bottom:14px;"></i>
        <h3 style="margin:0 0 6px;color:var(--text-primary);">No tienes citas próximas</h3>
        <p style="color:var(--text-secondary);margin:0;font-size:13.5px;">Todas tus citas pendientes aparecerán aquí.</p>
    </div>
<?php else: ?>

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(360px,1fr));gap:14px;">
    <?php foreach ($citas as $c):
        $ts = strtotime($c['fecha_cita']);
        $iniciales = '';
        foreach (explode(' ', trim($c['paciente'])) as $p) if (strlen($iniciales) < 2 && $p !== '') $iniciales .= strtoupper($p[0]);
        // Solo se muestra "Completada" cuando ya pasó la fecha/hora de la cita.
        $pasada = $ts && $ts < time();
        $badge_label = $pasada ? 'Completada' : ucfirst($c['estado']);
        $badge_color = $pasada ? 'info' : ($c['estado'] === 'confirmada' ? 'success' : 'warning');
    ?>
        <div class="card" style="padding:0;overflow:hidden;display:flex;flex-direction:column;transition:all .2s;"
             data-cita-id="<?= (int)$c['id'] ?>"
             data-paciente-id="<?= (int)$c['paciente_id'] ?>"
             data-paciente="<?= htmlspecialchars($c['paciente'], ENT_QUOTES, 'UTF-8') ?>"
             data-fecha="<?= htmlspecialchars(date('d/m/Y H:i', $ts), ENT_QUOTES, 'UTF-8') ?>"
             onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='var(--shadow-md)';"
             onmouseout="this.style.transform='';this.style.boxShadow='';">

            <!-- Header con fecha -->
            <div style="display:flex;background:linear-gradient(135deg,var(--accent-soft),var(--bg-muted));padding:14px 18px;align-items:center;gap:14px;border-bottom:1px solid var(--border-soft);">
                <div style="background:var(--bg-surface);border:1px solid var(--border);border-radius:10px;padding:6px 10px;text-align:center;min-width:54px;">
                    <div style="font-size:18px;font-weight:800;color:var(--text-primary);line-height:1;"><?= date('d', $ts) ?></div>
                    <div style="font-size:10px;color:var(--text-secondary);text-transform:uppercase;font-weight:700;letter-spacing:.5px;"><?= date('M', $ts) ?></div>
                </div>
                <div style="flex:1;min-width:0;">
                    <div style="font-size:18px;font-weight:700;color:var(--text-primary);"><i class="fa-regular fa-clock" style="margin-right:5px;color:var(--accent);"></i><?= date('H:i', $ts) ?></div>
                    <div style="font-size:12px;color:var(--text-secondary);"><?= date('l', $ts) ?></div>
                </div>
                <span class="badge badge-<?= $badge_color ?>"><?= htmlspecialchars($badge_label) ?></span>
            </div>

            <!-- Cuerpo paciente -->
            <div style="padding:16px 18px;flex:1;">
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
                    <div style="width:42px;height:42px;background:linear-gradient(135deg,var(--accent),#38bdf8);color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;flex-shrink:0;"><?= htmlspecialchars($iniciales ?: '?') ?></div>
                    <div style="min-width:0;">
                        <strong style="display:block;color:var(--text-primary);font-size:14px;"><?= htmlspecialchars($c['paciente']) ?></strong>
                        <small style="color:var(--text-secondary);font-size:11.5px;">
                            <?= htmlspecialchars($c['cedula'] ?: 'Sin cédula') ?>
                            <?= $c['edad'] ? ' · ' . (int)$c['edad'] . ' años' : '' ?>
                        </small>
                    </div>
                </div>

                <?php
                $estudios_cita = eco_estudios_desde_texto($c['motivo_principal'] ?? '');
                $estudios_txt  = $estudios_cita ? implode(', ', $estudios_cita) : ($c['tipo_nombre'] ?? '');
                ?>
                <?php if ($estudios_txt !== ''): ?>
                    <div style="display:flex;align-items:flex-start;gap:6px;font-size:12.5px;color:var(--text-primary);margin-bottom:8px;">
                        <i class="<?= htmlspecialchars($c['tipo_icono'] ?: 'fa-solid fa-wave-square') ?>" style="color:var(--accent);margin-top:2px;"></i>
                        <span><?= htmlspecialchars($estudios_txt) ?></span>
                    </div>
                <?php endif; ?>

                <?php if (!empty($c['motivo_consulta'])): ?>
                    <div style="background:var(--bg-muted);border-radius:8px;padding:8px 12px;font-size:12px;color:var(--text-secondary);line-height:1.5;margin-top:8px;">
                        <i class="fa-solid fa-quote-left" style="opacity:.4;margin-right:5px;"></i>
                        <?= htmlspecialchars(mb_strimwidth($c['motivo_consulta'], 0, 120, '…')) ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Acciones -->
            <div style="display:flex;gap:8px;padding:12px 18px;border-top:1px solid var(--border-soft);background:var(--bg-muted);">
                <button type="button" onclick="abrirGestionPacienteEco(<?= (int)$c['paciente_id'] ?>)" class="btn-primary" style="flex:1;justify-content:center;padding:8px;font-size:12.5px;">
                    <i class="fa-solid fa-folder-open"></i> Gestionar
                </button>
                <button type="button" onclick='abrirReprogramarCitaEco(<?= (int)$c['id'] ?>, <?= json_encode($c['paciente']) ?>, <?= json_encode(date('d/m/Y H:i', $ts)) ?>)' class="btn-secondary" style="flex:1;justify-content:center;padding:8px;font-size:12.5px;">
                    <i class="fa-solid fa-calendar-pen"></i> Reprogramar
                </button>
                <button type="button" onclick='cancelarCitaEco(<?= (int)$c['id'] ?>, <?= json_encode($c['paciente']) ?>)' class="btn-secondary btn-cancelar-eco" style="flex:1;justify-content:center;padding:8px;font-size:12.5px;">
                    <i class="fa-solid fa-calendar-xmark"></i> Cancelar
                </button>
            </div>

        </div>
    <?php endforeach; ?>
</div>

<?php endif; ?>

<style>
.btn-cancelar-eco { color:#dc2626; border-color:rgba(239,68,68,.3); }
.btn-cancelar-eco:hover { background:#ef4444; color:#fff; border-color:#ef4444; }
.cce-icon { width:70px; height:70px; margin:0 auto 16px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:31px; background:rgba(239,68,68,.14); color:#dc2626; }
.cce-title { margin:0 0 9px; font-size:19px; font-weight:800; color:var(--text-primary); }
.cce-text { margin:0 auto 22px; max-width:330px; font-size:13.5px; color:var(--text-secondary); line-height:1.55; }
.cce-foot { display:flex; gap:10px; justify-content:center; }
</style>

<div id="eco-modal-cancelar-cita-eco" class="eco-modal" aria-hidden="true" role="dialog">
    <div class="eco-modal__dialog" style="max-width:420px;">
        <div class="eco-modal__main" style="padding:32px 26px;text-align:center;">
            <button type="button" class="eco-modal__close" data-eco-modal-close aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
            <div class="cce-icon"><i class="fa-solid fa-calendar-xmark"></i></div>
            <h2 class="cce-title">¿Cancelar esta cita?</h2>
            <p class="cce-text">Vas a cancelar la cita de <strong id="cce-paciente">—</strong>. Esta acción no se puede deshacer y el paciente será notificado.</p>
            <div class="cce-foot">
                <button type="button" class="btn-secondary" data-eco-modal-close><i class="fa-solid fa-arrow-left"></i> Volver</button>
                <a id="cce-confirm" href="#" class="btn-primary" style="background:linear-gradient(135deg,#ef4444,#dc2626);box-shadow:0 4px 12px rgba(239,68,68,.3);"><i class="fa-solid fa-xmark"></i> Sí, cancelar</a>
            </div>
        </div>
    </div>
</div>

<script>
function cancelarCitaEco(id, paciente) {
    var p = document.getElementById('cce-paciente');
    if (p) p.textContent = paciente || '—';
    var a = document.getElementById('cce-confirm');
    if (a) a.href = (window.ECO_BASE || '') + 'api/cancelar_cita_ecografista.php?cita_id=' + encodeURIComponent(id);
    if (typeof EcoModal !== 'undefined') EcoModal.open('eco-modal-cancelar-cita-eco');
}
</script>

<?php
include __DIR__ . '/../layouts/partials/modal_gestionar_paciente_ecografista.php';
include __DIR__ . '/../layouts/partials/modal_cita_ecografista.php';
$page_content = ob_get_clean();
$page_scripts_extra = <<<'HTML'
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
<script src="assets/js/panel/ecografista-modals.js?v=26"></script>
HTML;
include __DIR__ . '/../layouts/shell.php';
