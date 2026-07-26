<?php
session_start();
include __DIR__ . '/../core/conexion.php';

if (!isset($_SESSION['usuario_id'])) { header('Location: ' . eco_url('login')); exit; }
if ($_SESSION['rol'] !== 'ecografista') { header('Location: ' . eco_url('dashboard')); exit; }

$ecografista_id = (int)$_SESSION['usuario_id'];

$citas_hoy = $citas_semana = $confirmadas = 0;
if ($s = $conex->prepare("SELECT COUNT(*) c FROM citas WHERE ecografista_id=? AND DATE(fecha_cita)=CURDATE() AND estado IN ('confirmada','reprogramada')")) {
    $s->bind_param('i', $ecografista_id); $s->execute();
    $citas_hoy = (int)$s->get_result()->fetch_assoc()['c']; $s->close();
}
if ($s = $conex->prepare("SELECT COUNT(*) c FROM citas WHERE ecografista_id=? AND fecha_cita BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY) AND estado IN ('confirmada','reprogramada')")) {
    $s->bind_param('i', $ecografista_id); $s->execute();
    $citas_semana = (int)$s->get_result()->fetch_assoc()['c']; $s->close();
}
if ($s = $conex->prepare("SELECT COUNT(*) c FROM citas WHERE ecografista_id=? AND estado='confirmada' AND fecha_cita >= NOW()")) {
    $s->bind_param('i', $ecografista_id); $s->execute();
    $confirmadas = (int)$s->get_result()->fetch_assoc()['c']; $s->close();
}

$page_title    = 'Mi Agenda';
$page_subtitle = 'Calendario personal con todas tus citas';
$active_section = 'agenda';

$page_header_actions = '<button type="button" class="btn-secondary" id="agenda-btn-recordatorios"><i class="fa-solid fa-bell"></i> Recordatorios</button> '
    . '<a href="' . eco_url('disponibilidad') . '" class="btn-secondary"><i class="fa-solid fa-clock"></i> Mi Disponibilidad</a>';
$page_head_extra = '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">';

ob_start();
?>

<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);">
    <div class="stat-card">
        <div class="stat-card-icon" style="background:var(--accent-soft);color:var(--accent-text);"><i class="fa-solid fa-calendar-day"></i></div>
        <p class="stat-card-label">Hoy</p>
        <p class="stat-card-value accent"><?= $citas_hoy ?></p>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon" style="background:rgba(34,197,94,.12);color:#15803d;"><i class="fa-solid fa-calendar-week"></i></div>
        <p class="stat-card-label">Próximos 7 días</p>
        <p class="stat-card-value success"><?= $citas_semana ?></p>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon" style="background:rgba(139,92,246,.12);color:#6d28d9;"><i class="fa-solid fa-circle-check"></i></div>
        <p class="stat-card-label">Confirmadas totales</p>
        <p class="stat-card-value" style="color:#6d28d9;"><?= $confirmadas ?></p>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3><i class="fa-solid fa-calendar-days" style="margin-right:7px;color:var(--accent);"></i> Calendario</h3>
        <div style="display:flex;gap:14px;font-size:12px;color:var(--text-secondary);">
            <span style="display:inline-flex;align-items:center;gap:5px;"><span style="width:10px;height:10px;border-radius:50%;background:var(--accent);"></span> Confirmada</span>
            <span style="display:inline-flex;align-items:center;gap:5px;"><span style="width:10px;height:10px;border-radius:50%;background:#f59e0b;"></span> Reprogramada</span>
        </div>
    </div>
    <div id="calendario" style="min-height:620px;"></div>
</div>

<link  href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const el = document.getElementById('calendario');
    if (!el || typeof FullCalendar === 'undefined') return;

    const calendar = new FullCalendar.Calendar(el, {
        initialView: 'dayGridMonth',
        locale: 'es',
        height: 'auto',
        firstDay: 1,
        headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay' },
        buttonText: { today: 'Hoy', month: 'Mes', week: 'Semana', day: 'Día' },
        allDayText: 'Todo el día',
        events: (window.ECO_BASE || '') + 'api/get_citas.php',
        eventTimeFormat: { hour: 'numeric', minute: '2-digit', meridiem: 'short', hour12: true },
        slotLabelFormat: { hour: 'numeric', minute: '2-digit', meridiem: 'short', hour12: true },
        eventClick: function(info) {
            if (typeof abrirDetalleCitaEco === 'function' && info.event.id) {
                abrirDetalleCitaEco(info.event.id);
            }
        }
    });
    calendar.render();
});
</script>

<style>
.fc { font-family: var(--font); --fc-border-color: var(--border-soft); --fc-button-bg-color: var(--bg-surface); --fc-button-border-color: var(--border); --fc-button-text-color: var(--text-primary); --fc-button-hover-bg-color: var(--bg-hover); --fc-button-hover-border-color: var(--text-muted); --fc-button-active-bg-color: var(--accent); --fc-button-active-border-color: var(--accent); --fc-today-bg-color: var(--accent-soft); --fc-event-bg-color: var(--accent); --fc-event-border-color: var(--accent); }
.fc .fc-toolbar-title { font-size: 16px !important; color: var(--text-primary); }
.fc .fc-button { font-size: 12px !important; padding: 6px 12px !important; border-radius: 8px !important; font-weight: 600; }
.fc .fc-button-active { color: #fff !important; }
.fc .fc-col-header-cell-cushion { color: var(--text-secondary); font-weight: 600; font-size: 12px; text-transform: uppercase; }
.fc .fc-daygrid-day-number { color: var(--text-primary); font-size: 12.5px; padding: 6px 8px; }
.fc .fc-event { border-radius: 4px; padding: 2px 6px; font-size: 11.5px; cursor: pointer; }
.fc .fc-event-time, .fc .fc-event-title { font-size: 10.5px; }
</style>

<?php
include __DIR__ . '/../layouts/partials/modal_gestionar_paciente_ecografista.php';
include __DIR__ . '/../layouts/partials/modal_cita_ecografista.php';
include __DIR__ . '/../layouts/partials/modal_recordatorios.php';
$page_content = ob_get_clean();
$page_scripts_extra = <<<'HTML'
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
<script src="assets/js/panel/ecografista-modals.js?v=auto"></script>
<script src="assets/js/agenda/recordatorios-ui.js"></script>
HTML;
include __DIR__ . '/../layouts/shell.php';
