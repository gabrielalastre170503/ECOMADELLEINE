<?php
/**
 * reportes.php — Reportes de negocio / BI (Fase 6).
 * Actividad y facturacion de citas por rango de fechas + export CSV/PDF.
 *
 * Acceso: administrador y recepcionista (datos globales) y ecografista
 * (scopeado a SUS propias citas — no ve ingresos globales ni otros ecografistas).
 */
session_start();
include __DIR__ . '/../core/conexion.php';
require_once __DIR__ . '/../lib/reportes/reportes.php';
require_once __DIR__ . '/../lib/facturacion/facturacion.php';

if (!isset($_SESSION['usuario_id'])) { header('Location: ' . eco_url('login')); exit; }
$rol = $_SESSION['rol'] ?? '';
if (!in_array($rol, ['administrador', 'recepcionista', 'ecografista'], true)) { header('Location: ' . eco_url('dashboard')); exit; }

// Un ecografista solo ve sus propios datos; admin/recepcionista ven todo (null = sin filtro).
$ecoId = ($rol === 'ecografista') ? (int)$_SESSION['usuario_id'] : null;

[$desde, $hasta] = eco_reporte_rango($_GET['desde'] ?? null, $_GET['hasta'] ?? null);
$resumen      = eco_reporte_resumen($conex, $desde, $hasta, $ecoId);
$por_tipo     = eco_reporte_por_tipo($conex, $desde, $hasta, $ecoId);
$por_eco      = $ecoId ? [] : eco_reporte_por_ecografista($conex, $desde, $hasta);
$serie        = eco_reporte_serie_diaria($conex, $desde, $hasta, $ecoId);
$por_metodo   = eco_reporte_por_metodo_pago($conex, $desde, $hasta, $ecoId);
$top_pac      = eco_reporte_top_pacientes($conex, $desde, $hasta, 10, $ecoId);
$comparativa  = eco_reporte_comparativa_meses($conex, 6, $ecoId);
$satisf       = eco_reporte_satisfaccion($conex, $desde, $hasta, $ecoId);

// Notas de sesión registradas en el periodo (scopeado al ecografista si aplica)
$total_notas = 0;
$sqlNotas = "SELECT COUNT(*) c FROM notas_clinicas WHERE DATE(fecha_sesion) BETWEEN ? AND ?"
    . ($ecoId ? " AND ecografista_id = ?" : "");
if ($stN = $conex->prepare($sqlNotas)) {
    if ($ecoId) { $stN->bind_param('ssi', $desde, $hasta, $ecoId); }
    else        { $stN->bind_param('ss', $desde, $hasta); }
    $stN->execute();
    $total_notas = (int)($stN->get_result()->fetch_assoc()['c'] ?? 0);
    $stN->close();
}

// Informes firmados en el periodo (por fecha de firma; scopeado al ecografista si aplica)
$total_firmados = 0;
$sqlFirm = "SELECT COUNT(*) c FROM informes_estudios
            WHERE estado = 'firmado'
              AND DATE(COALESCE(fecha_firma, finalizado_en, creado_en)) BETWEEN ? AND ?"
    . ($ecoId ? " AND ecografista_id = ?" : "");
if ($stF = $conex->prepare($sqlFirm)) {
    if ($ecoId) { $stF->bind_param('ssi', $desde, $hasta, $ecoId); }
    else        { $stF->bind_param('ss', $desde, $hasta); }
    $stF->execute();
    $total_firmados = (int)($stF->get_result()->fetch_assoc()['c'] ?? 0);
    $stF->close();
}

// Análisis clínico (solo ecografista): gráficos de su actividad y pacientes.
$clin = null;
if ($ecoId) {
    // Top tipos de estudio: derivado de $por_tipo (ya eco-scopeado), sin query extra.
    $tipos_top = array_slice($por_tipo, 0, 8);
    $clin = [
        'dia'    => eco_reporte_eco_dia_semana($conex, $ecoId, $desde, $hasta),
        'hora'   => eco_reporte_eco_hora($conex, $ecoId, $desde, $hasta),
        'edad'   => eco_reporte_eco_edad($conex, $ecoId, $desde, $hasta),
        'dir'    => eco_reporte_eco_direccion($conex, $ecoId, $desde, $hasta, 10),
        'nuevos' => eco_reporte_eco_pacientes_nuevos($conex, $ecoId, 6),
        'tipos'  => [
            'labels' => array_map(fn($r) => $r['tipo'], $tipos_top),
            'data'   => array_map(fn($r) => (int)$r['citas'], $tipos_top),
        ],
    ];
}

$qs = 'desde=' . urlencode($desde) . '&hasta=' . urlencode($hasta);

$page_title     = $ecoId ? 'Estadísticas' : 'Reportes';
$page_subtitle  = $ecoId ? 'Tu actividad y facturación por periodo' : 'Actividad y facturación por periodo';
$active_section = 'reportes';
$page_head_extra = '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';

// Tarjetas KPI: [label, valor, icono, color]
$kpis = [
    ['Citas',        number_format($resumen['citas']),       'fa-calendar-check', '#0284c7'],
    ['Completadas',  number_format($resumen['completadas']), 'fa-circle-check',   '#15803d'],
    ['Canceladas',   number_format($resumen['canceladas']),  'fa-calendar-xmark', '#b91c1c'],
    ['Pacientes',    number_format($resumen['pacientes']),   'fa-users',          '#7c3aed'],
    ['Facturado',    eco_money($resumen['facturado']),       'fa-file-invoice',   '#0f766e'],
    ['Cobrado',      eco_money($resumen['cobrado']),          'fa-money-bill-wave','#15803d'],
    ['Saldo',        eco_money($resumen['saldo']),            'fa-hand-holding-dollar', '#b45309'],
    ['Tasa de cobro', $resumen['tasa_cobro'] . '%',          'fa-percent',        '#0284c7'],
    ['No-show (' . $resumen['no_show'] . ')', $resumen['tasa_no_show'] . '%', 'fa-user-clock', '#b45309'],
    ['Satisfacción (' . $satisf['respuestas'] . ')', ($satisf['respuestas'] > 0 ? $satisf['promedio'] . '/5' : '—'), 'fa-star', '#d97706'],
    ['Notas de sesión',   number_format($total_notas),    'fa-notes-medical',  '#0d9488'],
    ['Informes firmados', number_format($total_firmados),  'fa-file-signature', '#0369a1'],
];

ob_start();
?>
<!-- Filtro de fechas + exportar -->
<div class="card" style="padding:16px 18px;margin-bottom:16px;">
    <form method="get" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
        <div>
            <label style="display:block;font-size:12px;color:var(--text-secondary);margin-bottom:4px;font-weight:600;">Desde</label>
            <input type="date" name="desde" value="<?= htmlspecialchars($desde) ?>" style="padding:8px 10px;border:1px solid var(--border);border-radius:8px;background:var(--bg-surface);color:var(--text-primary);">
        </div>
        <div>
            <label style="display:block;font-size:12px;color:var(--text-secondary);margin-bottom:4px;font-weight:600;">Hasta</label>
            <input type="date" name="hasta" value="<?= htmlspecialchars($hasta) ?>" style="padding:8px 10px;border:1px solid var(--border);border-radius:8px;background:var(--bg-surface);color:var(--text-primary);">
        </div>
        <button type="submit" class="btn-secondary" style="padding:9px 16px;"><i class="fa-solid fa-filter" style="color:var(--accent-text);"></i> Aplicar</button>
        <div style="flex:1;"></div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a class="btn-secondary" style="padding:9px 14px;" href="<?= eco_url('api/exportar_reporte.php') ?>?formato=pdf&<?= $qs ?>"><i class="fa-solid fa-file-pdf" style="color:#b91c1c;"></i> PDF</a>
            <a class="btn-secondary" style="padding:9px 14px;" href="<?= eco_url('api/exportar_reporte.php') ?>?r=resumen&<?= $qs ?>"><i class="fa-solid fa-file-csv"></i> Resumen</a>
            <a class="btn-secondary" style="padding:9px 14px;" href="<?= eco_url('api/exportar_reporte.php') ?>?r=tipos&<?= $qs ?>"><i class="fa-solid fa-file-csv"></i> Por tipo</a>
            <?php if (!$ecoId): ?>
            <a class="btn-secondary" style="padding:9px 14px;" href="<?= eco_url('api/exportar_reporte.php') ?>?r=ecografistas&<?= $qs ?>"><i class="fa-solid fa-file-csv"></i> Por ecografista</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- KPIs -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:14px;margin-bottom:16px;">
    <?php foreach ($kpis as [$label, $valor, $icono, $color]): ?>
        <div class="card" style="padding:16px 18px;display:flex;align-items:center;gap:14px;">
            <div style="width:42px;height:42px;border-radius:11px;flex-shrink:0;display:flex;align-items:center;justify-content:center;background:<?= $color ?>1a;color:<?= $color ?>;font-size:18px;">
                <i class="fa-solid <?= $icono ?>"></i>
            </div>
            <div style="min-width:0;">
                <div style="font-size:20px;font-weight:800;color:var(--text-primary);line-height:1.1;"><?= htmlspecialchars($valor) ?></div>
                <div style="font-size:12px;color:var(--text-secondary);"><?= htmlspecialchars($label) ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Tendencia -->
<div class="card" style="padding:18px;margin-bottom:16px;">
    <h3 style="margin:0 0 12px;font-size:15px;color:var(--text-primary);"><i class="fa-solid fa-chart-line" style="color:var(--accent);"></i> Tendencia diaria</h3>
    <?php if (empty($serie)): ?>
        <p style="color:var(--text-secondary);font-size:13px;margin:0;">Sin actividad en el periodo seleccionado.</p>
    <?php else: ?>
        <div style="position:relative;height:280px;"><canvas id="repo-chart"></canvas></div>
    <?php endif; ?>
</div>

<!-- Comparativa mensual (independiente del rango) -->
<div class="card" style="padding:18px;margin-bottom:16px;">
    <h3 style="margin:0 0 12px;font-size:15px;color:var(--text-primary);"><i class="fa-solid fa-chart-column" style="color:var(--accent);"></i> Comparativa últimos 6 meses</h3>
    <div style="position:relative;height:260px;"><canvas id="repo-meses"></canvas></div>
</div>

<!-- Tablas -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(360px,1fr));gap:16px;">
    <div class="card" style="padding:18px;">
        <h3 style="margin:0 0 12px;font-size:15px;color:var(--text-primary);"><i class="fa-solid fa-wave-square" style="color:var(--accent);"></i> Por tipo de estudio</h3>
        <table class="eco-table" style="width:100%;border-collapse:collapse;font-size:13px;">
            <thead><tr style="text-align:left;color:var(--text-secondary);border-bottom:1px solid var(--border-soft);">
                <th style="padding:8px 6px;">Tipo</th><th style="padding:8px 6px;text-align:right;">Citas</th>
                <th style="padding:8px 6px;text-align:right;">Compl.</th><th style="padding:8px 6px;text-align:right;">Cobrado</th>
            </tr></thead>
            <tbody>
            <?php if (empty($por_tipo)): ?>
                <tr><td colspan="4" style="padding:12px 6px;color:var(--text-secondary);">Sin datos.</td></tr>
            <?php else: foreach ($por_tipo as $r): ?>
                <tr style="border-bottom:1px solid var(--border-soft);">
                    <td style="padding:8px 6px;color:var(--text-primary);"><?= htmlspecialchars($r['tipo']) ?></td>
                    <td style="padding:8px 6px;text-align:right;"><?= (int)$r['citas'] ?></td>
                    <td style="padding:8px 6px;text-align:right;"><?= (int)$r['completadas'] ?></td>
                    <td style="padding:8px 6px;text-align:right;"><?= htmlspecialchars(eco_money($r['cobrado'])) ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?php if (!$ecoId): ?>
    <div class="card" style="padding:18px;">
        <h3 style="margin:0 0 12px;font-size:15px;color:var(--text-primary);"><i class="fa-solid fa-user-doctor" style="color:var(--accent);"></i> Por ecografista</h3>
        <table class="eco-table" style="width:100%;border-collapse:collapse;font-size:13px;">
            <thead><tr style="text-align:left;color:var(--text-secondary);border-bottom:1px solid var(--border-soft);">
                <th style="padding:8px 6px;">Ecografista</th><th style="padding:8px 6px;text-align:right;">Citas</th>
                <th style="padding:8px 6px;text-align:right;">Compl.</th><th style="padding:8px 6px;text-align:right;">Pac.</th>
                <th style="padding:8px 6px;text-align:right;">Cobrado</th>
            </tr></thead>
            <tbody>
            <?php if (empty($por_eco)): ?>
                <tr><td colspan="5" style="padding:12px 6px;color:var(--text-secondary);">Sin datos.</td></tr>
            <?php else: foreach ($por_eco as $r): ?>
                <tr style="border-bottom:1px solid var(--border-soft);">
                    <td style="padding:8px 6px;color:var(--text-primary);"><?= htmlspecialchars($r['ecografista']) ?></td>
                    <td style="padding:8px 6px;text-align:right;"><?= (int)$r['citas'] ?></td>
                    <td style="padding:8px 6px;text-align:right;"><?= (int)$r['completadas'] ?></td>
                    <td style="padding:8px 6px;text-align:right;"><?= (int)$r['pacientes'] ?></td>
                    <td style="padding:8px 6px;text-align:right;"><?= htmlspecialchars(eco_money($r['cobrado'])) ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Método de pago + Top pacientes -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(360px,1fr));gap:16px;margin-top:16px;">
    <div class="card" style="padding:18px;">
        <h3 style="margin:0 0 12px;font-size:15px;color:var(--text-primary);"><i class="fa-solid fa-money-bill-transfer" style="color:var(--accent);"></i> Ingresos por método de pago</h3>
        <table class="eco-table" style="width:100%;border-collapse:collapse;font-size:13px;">
            <thead><tr style="text-align:left;color:var(--text-secondary);border-bottom:1px solid var(--border-soft);">
                <th style="padding:8px 6px;">Método</th><th style="padding:8px 6px;text-align:right;">Pagos</th><th style="padding:8px 6px;text-align:right;">Cobrado</th>
            </tr></thead>
            <tbody>
            <?php if (empty($por_metodo)): ?>
                <tr><td colspan="3" style="padding:12px 6px;color:var(--text-secondary);">Sin pagos registrados.</td></tr>
            <?php else: foreach ($por_metodo as $r): ?>
                <tr style="border-bottom:1px solid var(--border-soft);">
                    <td style="padding:8px 6px;color:var(--text-primary);"><?= htmlspecialchars($r['metodo']) ?></td>
                    <td style="padding:8px 6px;text-align:right;"><?= (int)$r['pagos'] ?></td>
                    <td style="padding:8px 6px;text-align:right;"><?= htmlspecialchars(eco_money($r['cobrado'])) ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <div class="card" style="padding:18px;">
        <h3 style="margin:0 0 12px;font-size:15px;color:var(--text-primary);"><i class="fa-solid fa-ranking-star" style="color:var(--accent);"></i> Top 10 pacientes</h3>
        <table class="eco-table" style="width:100%;border-collapse:collapse;font-size:13px;">
            <thead><tr style="text-align:left;color:var(--text-secondary);border-bottom:1px solid var(--border-soft);">
                <th style="padding:8px 6px;">Paciente</th><th style="padding:8px 6px;text-align:right;">Citas</th><th style="padding:8px 6px;text-align:right;">Cobrado</th>
            </tr></thead>
            <tbody>
            <?php if (empty($top_pac)): ?>
                <tr><td colspan="3" style="padding:12px 6px;color:var(--text-secondary);">Sin datos.</td></tr>
            <?php else: foreach ($top_pac as $r): ?>
                <tr style="border-bottom:1px solid var(--border-soft);">
                    <td style="padding:8px 6px;color:var(--text-primary);"><?= htmlspecialchars($r['paciente']) ?></td>
                    <td style="padding:8px 6px;text-align:right;"><?= (int)$r['citas'] ?></td>
                    <td style="padding:8px 6px;text-align:right;"><?= htmlspecialchars(eco_money($r['cobrado'])) ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php if ($clin): ?>
<h2 style="margin:24px 0 4px;font-size:16px;color:var(--text-primary);"><i class="fa-solid fa-stethoscope" style="color:var(--accent);"></i> Análisis clínico</h2>
<p style="margin:0 0 14px;font-size:12px;color:var(--text-secondary);">Tu actividad y tus pacientes en el periodo seleccionado.</p>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(360px,1fr));gap:16px;">
    <div class="card" style="padding:18px;">
        <h3 style="margin:0 0 12px;font-size:15px;color:var(--text-primary);"><i class="fa-solid fa-user-plus" style="color:var(--accent);"></i> Pacientes nuevos (6 meses)</h3>
        <div style="position:relative;height:260px;"><canvas id="clin-nuevos"></canvas></div>
    </div>
    <div class="card" style="padding:18px;">
        <h3 style="margin:0 0 12px;font-size:15px;color:var(--text-primary);"><i class="fa-solid fa-wave-square" style="color:var(--accent);"></i> Estudios más frecuentes</h3>
        <div style="position:relative;height:260px;"><canvas id="clin-tipos"></canvas></div>
    </div>
    <div class="card" style="padding:18px;">
        <h3 style="margin:0 0 12px;font-size:15px;color:var(--text-primary);"><i class="fa-solid fa-chart-simple" style="color:var(--accent);"></i> Actividad por día</h3>
        <div style="position:relative;height:260px;"><canvas id="clin-dia"></canvas></div>
    </div>
    <div class="card" style="padding:18px;">
        <h3 style="margin:0 0 12px;font-size:15px;color:var(--text-primary);"><i class="fa-solid fa-clock" style="color:var(--accent);"></i> Citas por hora</h3>
        <div style="position:relative;height:260px;"><canvas id="clin-hora"></canvas></div>
    </div>
    <div class="card" style="padding:18px;">
        <h3 style="margin:0 0 12px;font-size:15px;color:var(--text-primary);"><i class="fa-solid fa-cake-candles" style="color:var(--accent);"></i> Pacientes por edad</h3>
        <div style="position:relative;height:260px;"><canvas id="clin-edad"></canvas></div>
    </div>
    <div class="card" style="padding:18px;">
        <h3 style="margin:0 0 12px;font-size:15px;color:var(--text-primary);"><i class="fa-solid fa-location-dot" style="color:var(--accent);"></i> Pacientes por dirección</h3>
        <div style="position:relative;height:260px;"><canvas id="clin-dir"></canvas></div>
    </div>
</div>
<?php endif; ?>
<?php
$page_content = ob_get_clean();

$chart_labels = json_encode(array_map(fn($r) => $r['dia'], $serie));
$chart_citas  = json_encode(array_map(fn($r) => $r['citas'], $serie));
$chart_cobr   = json_encode(array_map(fn($r) => $r['cobrado'], $serie));
$mes_labels   = json_encode(array_map(fn($r) => $r['mes'], $comparativa));
$mes_citas    = json_encode(array_map(fn($r) => $r['citas'], $comparativa));
$mes_cobr     = json_encode(array_map(fn($r) => $r['cobrado'], $comparativa));

$page_scripts_extra = <<<HTML
<script>
(function () {
    if (typeof Chart === 'undefined') return;
    var el = document.getElementById('repo-chart');
    if (el) new Chart(el, {
        type: 'line',
        data: {
            labels: {$chart_labels},
            datasets: [
                { label: 'Citas', data: {$chart_citas}, borderColor: '#0284c7', backgroundColor: 'rgba(2,132,199,.12)', fill: true, tension: .3, yAxisID: 'y' },
                { label: 'Cobrado', data: {$chart_cobr}, borderColor: '#15803d', backgroundColor: 'rgba(21,128,61,.10)', fill: true, tension: .3, yAxisID: 'y1' }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            scales: {
                y:  { type: 'linear', position: 'left',  beginAtZero: true, title: { display: true, text: 'Citas' } },
                y1: { type: 'linear', position: 'right', beginAtZero: true, grid: { drawOnChartArea: false }, title: { display: true, text: 'Cobrado' } }
            }
        }
    });

    var elm = document.getElementById('repo-meses');
    if (elm) new Chart(elm, {
        type: 'bar',
        data: {
            labels: {$mes_labels},
            datasets: [
                { label: 'Citas', data: {$mes_citas}, backgroundColor: '#0284c7', yAxisID: 'y' },
                { label: 'Cobrado', data: {$mes_cobr}, backgroundColor: '#15803d', yAxisID: 'y1' }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            scales: {
                y:  { type: 'linear', position: 'left',  beginAtZero: true, title: { display: true, text: 'Citas' } },
                y1: { type: 'linear', position: 'right', beginAtZero: true, grid: { drawOnChartArea: false }, title: { display: true, text: 'Cobrado' } }
            }
        }
    });
})();
</script>
HTML;

// Gráficos del análisis clínico (solo ecografista).
if ($clin) {
    $cl = json_encode($clin, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    $page_scripts_extra .= <<<HTML
<script>
(function () {
    if (typeof Chart === 'undefined') return;
    var D = {$cl};
    var grid = 'rgba(148,163,184,.18)';
    function mk(id, cfg){ var el = document.getElementById(id); if (el) new Chart(el, cfg); }
    function hexA(h, a){ h = h.replace('#',''); return 'rgba(' + parseInt(h.substr(0,2),16) + ',' + parseInt(h.substr(2,2),16) + ',' + parseInt(h.substr(4,2),16) + ',' + a + ')'; }

    mk('clin-nuevos', { type:'line',
        data:{ labels:D.nuevos.labels, datasets:[{ label:'Pacientes', data:D.nuevos.data, borderColor:'#22c55e', backgroundColor:hexA('#22c55e',.12), fill:true, tension:.35, pointRadius:3, borderWidth:2 }] },
        options:{ responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}}, scales:{ y:{beginAtZero:true,ticks:{precision:0},grid:{color:grid}}, x:{grid:{display:false}} } } });

    mk('clin-tipos', { type:'bar',
        data:{ labels:D.tipos.labels, datasets:[{ label:'Estudios', data:D.tipos.data, backgroundColor:'#0284c7', borderRadius:6 }] },
        options:{ indexAxis:'y', responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}}, scales:{ x:{beginAtZero:true,ticks:{precision:0},grid:{color:grid}}, y:{grid:{display:false}} } } });

    mk('clin-dia', { type:'bar',
        data:{ labels:['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'], datasets:[{ label:'Citas', data:D.dia, backgroundColor:'#8b5cf6', borderRadius:6, maxBarThickness:34 }] },
        options:{ responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}}, scales:{ y:{beginAtZero:true,ticks:{precision:0},grid:{color:grid}}, x:{grid:{display:false}} } } });

    mk('clin-hora', { type:'bar',
        data:{ labels:D.hora.labels, datasets:[{ label:'Citas', data:D.hora.data, backgroundColor:'#14b8a6', borderRadius:5, maxBarThickness:26 }] },
        options:{ responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}}, scales:{ y:{beginAtZero:true,ticks:{precision:0},grid:{color:grid}}, x:{grid:{display:false},ticks:{maxTicksLimit:13}} } } });

    mk('clin-edad', { type:'bar',
        data:{ labels:D.edad.labels, datasets:[{ label:'Pacientes', data:D.edad.data, backgroundColor:'#6366f1', borderRadius:6, maxBarThickness:40 }] },
        options:{ responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}}, scales:{ y:{beginAtZero:true,ticks:{precision:0},grid:{color:grid}}, x:{grid:{display:false}} } } });

    mk('clin-dir', { type:'bar',
        data:{ labels:D.dir.labels, datasets:[{ label:'Pacientes', data:D.dir.data, backgroundColor:'#0ea5e9', borderRadius:6 }] },
        options:{ indexAxis:'y', responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}}, scales:{ x:{beginAtZero:true,ticks:{precision:0},grid:{color:grid}}, y:{grid:{display:false}} } } });
})();
</script>
HTML;
}

include __DIR__ . '/../layouts/shell.php';
