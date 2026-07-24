<?php
session_start();
include __DIR__ . '/../core/conexion.php';

if (!isset($_SESSION['usuario_id'])) { header('Location: ' . eco_url('login')); exit; }
if ($_SESSION['rol'] !== 'ecografista') { header('Location: ' . eco_url('dashboard')); exit; }

$ecografista_id = (int)$_SESSION['usuario_id'];

/* Cargar historial completo */
$citas = [];
if ($s = $conex->prepare("
    SELECT c.id, c.fecha_cita, c.estado, c.motivo_consulta,
           u.id paciente_id, u.nombre_completo paciente, u.cedula,
           t.nombre tipo_nombre, t.icono tipo_icono
    FROM citas c
    JOIN usuarios u ON u.id=c.paciente_id
    LEFT JOIN tipos_ecografias t ON t.id=c.tipo_ecografia_id
    WHERE c.ecografista_id=?
    ORDER BY c.fecha_cita DESC")) {
    $s->bind_param('i', $ecografista_id);
    $s->execute();
    $citas = $s->get_result()->fetch_all(MYSQLI_ASSOC);
    $s->close();
}

/* Stats */
$total = count($citas);
$completadas = count(array_filter($citas, fn($c) => $c['estado'] === 'completada'));
$canceladas  = count(array_filter($citas, fn($c) => in_array($c['estado'], ['cancelada','rechazada'])));

$page_title    = 'Historial de Citas';
$page_subtitle = 'Todas tus citas registradas en el sistema';
$active_section = 'historial-citas';
$page_head_extra = '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">';

ob_start();
?>

<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);">
    <div class="stat-card">
        <div class="stat-card-icon" style="background:var(--accent-soft);color:var(--accent-text);"><i class="fa-solid fa-clipboard-list"></i></div>
        <p class="stat-card-label">Total de Citas</p>
        <p class="stat-card-value accent"><?= $total ?></p>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon" style="background:rgba(34,197,94,.12);color:#15803d;"><i class="fa-solid fa-check-double"></i></div>
        <p class="stat-card-label">Completadas</p>
        <p class="stat-card-value success"><?= $completadas ?></p>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon" style="background:rgba(239,68,68,.12);color:#b91c1c;"><i class="fa-solid fa-ban"></i></div>
        <p class="stat-card-label">Canceladas / Rechazadas</p>
        <p class="stat-card-value danger"><?= $canceladas ?></p>
    </div>
</div>

<div class="card" style="padding:14px 18px;margin-bottom:14px;">
    <div style="display:grid;grid-template-columns:1fr 200px;gap:12px;">
        <div style="position:relative;">
            <i class="fa-solid fa-magnifying-glass" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-muted);"></i>
            <input type="search" id="hist-search" placeholder="Buscar por paciente, cédula o motivo..."
                   style="width:100%;padding:10px 14px 10px 40px;border:1.5px solid var(--border);border-radius:8px;font-family:inherit;font-size:13.5px;background:var(--bg-surface);color:var(--text-primary);box-sizing:border-box;">
        </div>
        <select id="hist-filter" style="padding:10px 14px;border:1.5px solid var(--border);border-radius:8px;font-family:inherit;font-size:13.5px;background:var(--bg-surface);color:var(--text-primary);cursor:pointer;">
            <option value="">Todos los estados</option>
            <option value="completada">Completada</option>
            <option value="confirmada">Confirmada</option>
            <option value="reprogramada">Reprogramada</option>
            <option value="pendiente">Pendiente</option>
            <option value="cancelada">Cancelada</option>
            <option value="rechazada">Rechazada</option>
        </select>
    </div>
</div>

<?php if (empty($citas)): ?>
    <div class="card" style="text-align:center;padding:50px 20px;">
        <i class="fa-solid fa-clipboard" style="font-size:2.8rem;color:var(--text-muted);opacity:.4;display:block;margin-bottom:10px;"></i>
        <p style="color:var(--text-secondary);margin:0;">No tienes citas registradas todavía.</p>
    </div>
<?php else: ?>
<div class="card" style="padding:0;overflow:hidden;">
    <div class="data-table" style="border:none;">
        <table>
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Paciente</th>
                    <th>Tipo de Estudio</th>
                    <th>Motivo</th>
                    <th>Estado</th>
                    <th style="text-align:right;">Acciones</th>
                </tr>
            </thead>
            <tbody id="tbody-hist">
            <?php foreach ($citas as $c):
                $ts = $c['fecha_cita'] ? strtotime($c['fecha_cita']) : null;
                $busqueda = strtolower(($c['paciente']??'') . ' ' . ($c['cedula']??'') . ' ' . ($c['motivo_consulta']??''));
                $badge_map = [
                    'completada'   => 'success',
                    'confirmada'   => 'info',
                    'reprogramada' => 'info',
                    'pendiente'    => 'warning',
                    'cancelada'    => 'danger',
                    'rechazada'    => 'danger',
                ];
                $badge_cls = 'badge-' . ($badge_map[$c['estado']] ?? 'info');
                $es_pasada = $ts && $ts < time();
                $puede_completar = $es_pasada && in_array($c['estado'], ['confirmada','reprogramada']);
            ?>
                <tr class="hist-row" data-search="<?= htmlspecialchars($busqueda) ?>" data-estado="<?= htmlspecialchars($c['estado']) ?>">
                    <td style="white-space:nowrap;">
                        <?php if ($ts): ?>
                            <div style="font-weight:600;color:var(--text-primary);font-size:13px;"><?= date('d/m/Y', $ts) ?></div>
                            <div style="font-size:11.5px;color:var(--text-muted);"><i class="fa-regular fa-clock"></i> <?= date('H:i', $ts) ?></div>
                        <?php else: ?>
                            <span style="color:var(--text-muted);">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong style="color:var(--text-primary);"><?= htmlspecialchars($c['paciente']) ?></strong>
                        <div style="font-size:11.5px;color:var(--text-muted);"><?= htmlspecialchars($c['cedula'] ?: '—') ?></div>
                    </td>
                    <td>
                        <?php if ($c['tipo_nombre']): ?>
                            <span style="display:inline-flex;align-items:center;gap:5px;font-size:12.5px;">
                                <i class="<?= htmlspecialchars($c['tipo_icono'] ?: 'fa-solid fa-wave-square') ?>" style="color:var(--accent);"></i>
                                <?= htmlspecialchars($c['tipo_nombre']) ?>
                            </span>
                        <?php else: ?>
                            <span style="color:var(--text-muted);">—</span>
                        <?php endif; ?>
                    </td>
                    <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12.5px;color:var(--text-secondary);" title="<?= htmlspecialchars($c['motivo_consulta'] ?: '—') ?>"><?= htmlspecialchars(mb_strimwidth($c['motivo_consulta'] ?: '—', 0, 50, '…')) ?></td>
                    <td><span class="badge <?= $badge_cls ?>"><?= ucfirst($c['estado']) ?></span></td>
                    <td style="text-align:right;white-space:nowrap;">
                        <button type="button" onclick="abrirDetalleCitaEco(<?= (int)$c['id'] ?>)" class="btn-secondary" style="padding:5px 11px;font-size:11.5px;">
                            <i class="fa-solid fa-eye"></i> Ver
                        </button>
                        <?php if ($puede_completar): ?>
                            <button type="button" onclick="ecoMarcarCompletada(<?= (int)$c['id'] ?>)" class="btn-primary" style="padding:5px 11px;font-size:11.5px;background:linear-gradient(135deg,#22c55e,#16a34a);border:none;cursor:pointer;">
                                <i class="fa-solid fa-check"></i>
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div id="hist-empty" style="text-align:center;padding:40px 20px;color:var(--text-muted);display:none;">
        <i class="fa-solid fa-magnifying-glass" style="font-size:2rem;opacity:.4;display:block;margin-bottom:10px;"></i>
        <p style="margin:0;">No hay resultados para los filtros seleccionados.</p>
    </div>
</div>
<?php endif; ?>

<script>
/* Marca una cita como completada vía POST. El wrapper fetch de shell.php
   añade automáticamente la cabecera X-CSRF-Token. */
function ecoMarcarCompletada(id){
    if(!confirm('¿Marcar esta cita como completada?')) return;
    fetch((window.ECO_BASE || '') + 'api/marcar_completada.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'cita_id=' + encodeURIComponent(id)
    })
    .then(r => r.json())
    .then(d => { if (d && d.success) { location.reload(); } else { alert('No se pudo completar la cita.'); } })
    .catch(() => alert('Error de red. Inténtalo de nuevo.'));
}
(function(){
    const search = document.getElementById('hist-search');
    const filter = document.getElementById('hist-filter');
    const rows   = document.querySelectorAll('.hist-row');
    const empty  = document.getElementById('hist-empty');
    if (!search || !rows.length) return;

    function apply() {
        const q = search.value.trim().toLowerCase();
        const f = filter.value;
        let visible = 0;
        rows.forEach(r => {
            const okSearch = !q || r.dataset.search.includes(q);
            const okFilter = !f || r.dataset.estado === f;
            const show = okSearch && okFilter;
            r.style.display = show ? '' : 'none';
            if (show) visible++;
        });
        empty.style.display = visible === 0 ? 'block' : 'none';
    }
    search.addEventListener('input', apply);
    filter.addEventListener('change', apply);
})();
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
