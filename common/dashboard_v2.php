<?php
session_start();
include __DIR__ . '/../core/conexion.php';
require_once __DIR__ . '/../lib/facturacion/facturacion.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . eco_url('login'));
    exit;
}

$rol        = $_SESSION['rol']   ?? 'usuario';
$usuario_id = (int)$_SESSION['usuario_id'];
$nombre     = $_SESSION['nombre_completo'] ?? 'Doctor';
$primer_nombre = explode(' ', trim($nombre))[0] ?? 'Doctor';

$browser_title     = 'Dashboard';
$page_title        = '';
$page_subtitle     = '';
$active_section    = ($rol === 'paciente') ? 'paciente-dashboard' : 'dashboard';
$page_header_actions = '';

ob_start();

/* ===================================================================
   DASHBOARD DEL ECOGRAFISTA
   =================================================================== */
if ($rol === 'ecografista'):

    /* KPIs propios del ecografista */
    $mis_citas_hoy = $mis_pendientes = $mis_pacientes = 0;
    if ($s = $conex->prepare("SELECT COUNT(*) c FROM citas WHERE ecografista_id=? AND estado IN ('confirmada','reprogramada') AND DATE(fecha_cita)=CURDATE()")) {
        $s->bind_param('i', $usuario_id); $s->execute();
        $mis_citas_hoy = (int)$s->get_result()->fetch_assoc()['c']; $s->close();
    }
    if ($s = $conex->prepare("SELECT COUNT(*) c FROM citas WHERE ecografista_id=? AND estado='pendiente'")) {
        $s->bind_param('i', $usuario_id); $s->execute();
        $mis_pendientes = (int)$s->get_result()->fetch_assoc()['c']; $s->close();
    }
    if ($s = $conex->prepare("
        SELECT COUNT(DISTINCT u.id) c
        FROM usuarios u
        LEFT JOIN citas c ON c.paciente_id = u.id
        WHERE u.rol='paciente' AND u.estado='aprobado'
          AND (u.creado_por_id=? OR c.ecografista_id=?)")) {
        $s->bind_param('ii', $usuario_id, $usuario_id); $s->execute();
        $mis_pacientes = (int)$s->get_result()->fetch_assoc()['c']; $s->close();
    }

    /* Citas de esta semana */
    $mis_semana = 0;
    if ($s = $conex->prepare("SELECT COUNT(*) c FROM citas WHERE ecografista_id=? AND estado IN ('confirmada','reprogramada') AND YEARWEEK(fecha_cita,1)=YEARWEEK(CURDATE(),1)")) {
        $s->bind_param('i', $usuario_id); $s->execute();
        $mis_semana = (int)$s->get_result()->fetch_assoc()['c']; $s->close();
    }

    $meses_eco = [1=>'enero',2=>'febrero',3=>'marzo',4=>'abril',5=>'mayo',6=>'junio',7=>'julio',8=>'agosto',9=>'septiembre',10=>'octubre',11=>'noviembre',12=>'diciembre'];
    $meses_abbr_eco = [1=>'ENE',2=>'FEB',3=>'MAR',4=>'ABR',5=>'MAY',6=>'JUN',7=>'JUL',8=>'AGO',9=>'SEP',10=>'OCT',11=>'NOV',12=>'DIC'];
    $hoy_txt = (int)date('d') . ' de ' . $meses_eco[(int)date('n')];

    /* Próximas 5 citas */
    $proximas = [];
    if ($s = $conex->prepare("
        SELECT c.id, c.fecha_cita, c.motivo_consulta, c.motivo_principal, c.estado, c.estado_pago,
               u.nombre_completo paciente, u.cedula,
               t.nombre tipo_nombre
        FROM citas c
        JOIN usuarios u ON u.id=c.paciente_id
        LEFT JOIN tipos_ecografias t ON t.id=c.tipo_ecografia_id
        WHERE c.ecografista_id=? AND c.estado IN ('confirmada','reprogramada')
              AND c.fecha_cita >= NOW()
        ORDER BY c.fecha_cita ASC LIMIT 5")) {
        $s->bind_param('i', $usuario_id); $s->execute();
        $proximas = $s->get_result()->fetch_all(MYSQLI_ASSOC); $s->close();
    }
?>

<style>
.eco-appt { display:flex; align-items:center; gap:13px; padding:12px 14px; border:1px solid var(--border); border-radius:12px; transition:border-color .18s ease, box-shadow .18s ease; }
.eco-appt:hover { border-color:rgba(2,177,244,.35); box-shadow:var(--shadow); }
.eco-appt__date { width:48px; flex-shrink:0; text-align:center; padding:7px 0; border-radius:10px; background:var(--accent-soft); color:var(--accent-text); }
.eco-appt__day { display:block; font-size:18px; font-weight:800; line-height:1; }
.eco-appt__mon { display:block; font-size:10px; font-weight:700; letter-spacing:.05em; margin-top:2px; }
.eco-appt__main { flex:1; min-width:0; }
.eco-appt__name { font-size:13.5px; color:var(--text-primary); display:block; }
.eco-appt__meta { display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-top:3px; font-size:11.5px; color:var(--text-secondary); }
.eco-appt__chip { padding:2px 9px; border-radius:999px; background:var(--bg-muted); color:var(--text-secondary); font-weight:600; }
</style>

<!-- Hero de bienvenida -->
<div class="card" style="margin-bottom:18px;background:var(--bg-surface);border:1px solid rgba(2,177,244,.2);">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;">
        <div>
            <h2 style="margin:0 0 4px;font-size:20px;font-weight:700;color:var(--text-primary);">Hola, <?= htmlspecialchars($primer_nombre) ?> 👋</h2>
            <p style="margin:0;font-size:13.5px;color:var(--text-secondary);">Hoy es <?= htmlspecialchars($hoy_txt) ?>. Tienes <strong style="color:var(--accent-text);"><?= $mis_citas_hoy ?></strong> cita<?= $mis_citas_hoy === 1 ? '' : 's' ?> en tu agenda<?= $mis_pendientes > 0 ? ' y ' . $mis_pendientes . ' solicitud' . ($mis_pendientes === 1 ? '' : 'es') . ' por revisar' : '' ?>.</p>
        </div>
        <a href="<?= eco_url('mi-agenda') ?>" class="btn-primary" style="white-space:nowrap;"><i class="fa-solid fa-calendar-days"></i> Ver mi agenda</a>
    </div>
</div>

<!-- Indicadores -->
<div class="stats-grid">
    <a href="<?= eco_url('mi-agenda') ?>" class="stat-card" style="text-decoration:none;color:inherit;">
        <div class="stat-card-icon" style="background:var(--accent-soft);color:var(--accent-text);">
            <i class="fa-solid fa-calendar-day"></i>
        </div>
        <p class="stat-card-label">Citas de Hoy</p>
        <p class="stat-card-value accent"><?= $mis_citas_hoy ?></p>
        <p class="stat-card-sub">En tu agenda</p>
    </a>
    <a href="<?= eco_url('solicitudes') ?>" class="stat-card" style="text-decoration:none;color:inherit;">
        <div class="stat-card-icon" style="background:rgba(245,158,11,.12);color:#b45309;">
            <i class="fa-solid fa-inbox"></i>
        </div>
        <p class="stat-card-label">Solicitudes Pendientes</p>
        <p class="stat-card-value warning"><?= $mis_pendientes ?></p>
        <p class="stat-card-sub">Esperan tu respuesta</p>
    </a>
    <a href="<?= eco_url('mi-agenda') ?>" class="stat-card" style="text-decoration:none;color:inherit;">
        <div class="stat-card-icon" style="background:rgba(139,92,246,.12);color:#7c3aed;">
            <i class="fa-solid fa-calendar-week"></i>
        </div>
        <p class="stat-card-label">Esta Semana</p>
        <p class="stat-card-value" style="color:#7c3aed;"><?= $mis_semana ?></p>
        <p class="stat-card-sub">Citas programadas</p>
    </a>
    <a href="<?= eco_url('mis-pacientes') ?>" class="stat-card" style="text-decoration:none;color:inherit;">
        <div class="stat-card-icon" style="background:rgba(34,197,94,.12);color:#15803d;">
            <i class="fa-solid fa-user-injured"></i>
        </div>
        <p class="stat-card-label">Pacientes Activos</p>
        <p class="stat-card-value success"><?= $mis_pacientes ?></p>
        <p class="stat-card-sub">Bajo tu cuidado</p>
    </a>
</div>
<div style="display:grid;grid-template-columns:1.4fr 1fr;gap:18px;">

    <div class="card">
        <div class="card-header">
            <h3><i class="fa-solid fa-calendar-check" style="margin-right:7px;color:var(--accent);"></i> Próximas Citas</h3>
            <a href="<?= eco_url('proximas-citas') ?>" style="font-size:12.5px;color:var(--accent-text);font-weight:600;">Ver todas →</a>
        </div>
        <?php if (empty($proximas)): ?>
            <p style="color:var(--text-muted);text-align:center;padding:30px 0;font-size:13px;">
                <i class="fa-regular fa-calendar" style="font-size:2rem;opacity:.4;display:block;margin-bottom:8px;"></i>
                No tienes citas próximas.
            </p>
        <?php else: ?>
            <div style="display:flex;flex-direction:column;gap:10px;">
                <?php foreach ($proximas as $c):
                    $fecha = strtotime($c['fecha_cita']);
                ?>
                    <div class="eco-appt">
                        <div class="eco-appt__date">
                            <span class="eco-appt__day"><?= date('d', $fecha) ?></span>
                            <span class="eco-appt__mon"><?= $meses_abbr_eco[(int)date('n', $fecha)] ?></span>
                        </div>
                        <div class="eco-appt__main">
                            <strong class="eco-appt__name"><?= htmlspecialchars($c['paciente']) ?></strong>
                            <div class="eco-appt__meta">
                                <span><i class="fa-regular fa-clock"></i> <?= date('H:i', $fecha) ?></span>
                                <?php
                                $estudios_dash = eco_estudios_desde_texto($c['motivo_principal'] ?? '');
                                $estudios_dash_txt = $estudios_dash ? implode(', ', $estudios_dash) : ($c['tipo_nombre'] ?? '');
                                ?>
                                <?php if ($estudios_dash_txt !== ''): ?>
                                    <span class="eco-appt__chip"><i class="fa-solid fa-wave-square"></i> <?= htmlspecialchars($estudios_dash_txt) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($fecha < time()): ?>
                            <span class="badge badge-info">Completada</span>
                        <?php elseif ($c['estado'] === 'reprogramada'): ?>
                            <span class="badge badge-purple">Reprogramada</span>
                        <?php else: ?>
                            <span class="badge badge-success">Confirmada</span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-header">
            <h3><i class="fa-solid fa-chart-column" style="margin-right:7px;color:var(--accent);"></i> Actividad</h3>
        </div>
        <div style="position:relative;height:270px;">
            <canvas id="dashChart"></canvas>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
fetch((window.ECO_BASE || '') + 'api/get_chart_data.php').then(r=>r.json()).then(d => {
    const ctx = document.getElementById('dashChart');
    if (!ctx) return;
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: d.labels,
            datasets: [{
                label: 'Citas confirmadas',
                data: d.data,
                backgroundColor: 'rgba(2,177,244,.7)',
                borderColor: 'rgba(2,177,244,1)',
                borderWidth: 1,
                borderRadius: 6
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            scales: {
                y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: 'rgba(0,0,0,.05)' } },
                x: { grid: { display: false } }
            },
            plugins: { legend: { display: false } }
        }
    });
}).catch(()=>{});
</script>

<?php
/* ===================================================================
   DASHBOARD DEL ADMINISTRADOR  (original)
   =================================================================== */
elseif ($rol === 'administrador'):

    $stats_admin = [
        'aprobados' => 0,
        'pacientes_activos' => 0,
        'personal' => 0,
        'total_citas' => 0,
    ];
    if ($r = $conex->query("SELECT COUNT(id) c FROM usuarios WHERE estado='aprobado'")) {
        $stats_admin['aprobados'] = (int)$r->fetch_assoc()['c'];
    }
    if ($r = $conex->query("SELECT COUNT(id) c FROM usuarios WHERE rol='paciente' AND estado='aprobado'")) {
        $stats_admin['pacientes_activos'] = (int)$r->fetch_assoc()['c'];
    }
    if ($r = $conex->query("SELECT COUNT(id) c FROM usuarios WHERE rol IN ('ecografista','recepcionista') AND estado='aprobado'")) {
        $stats_admin['personal'] = (int)$r->fetch_assoc()['c'];
    }
    if ($r = $conex->query("SELECT COUNT(id) c FROM citas")) {
        $stats_admin['total_citas'] = (int)$r->fetch_assoc()['c'];
    }

    include __DIR__ . '/../layouts/partials/dashboard_admin_content.php';

/* ===================================================================
   DASHBOARD DEL PACIENTE
   =================================================================== */
elseif ($rol === 'paciente'):

    /* Próxima cita confirmada/reprogramada */
    $next = null;
    if ($s = $conex->prepare("SELECT c.fecha_cita, u.nombre_completo AS profesional_nombre, t.nombre AS tipo_nombre
            FROM citas c
            JOIN usuarios u ON c.ecografista_id = u.id
            LEFT JOIN tipos_ecografias t ON t.id = c.tipo_ecografia_id
            WHERE c.paciente_id = ? AND c.estado IN ('confirmada','reprogramada') AND c.fecha_cita >= NOW()
            ORDER BY c.fecha_cita ASC LIMIT 1")) {
        $s->bind_param('i', $usuario_id); $s->execute();
        $next = $s->get_result()->fetch_assoc() ?: null; $s->close();
    }

    /* Contadores del paciente */
    $citas_completadas = $solicitudes_pendientes = $informes_total = 0;
    if ($s = $conex->prepare("SELECT COUNT(id) c FROM citas WHERE paciente_id = ? AND estado = 'completada'")) {
        $s->bind_param('i', $usuario_id); $s->execute();
        $citas_completadas = (int)$s->get_result()->fetch_assoc()['c']; $s->close();
    }
    if ($s = $conex->prepare("SELECT COUNT(id) c FROM citas WHERE paciente_id = ? AND estado IN ('pendiente','pendiente_paciente')")) {
        $s->bind_param('i', $usuario_id); $s->execute();
        $solicitudes_pendientes = (int)$s->get_result()->fetch_assoc()['c']; $s->close();
    }
    if ($s = $conex->prepare("SELECT COUNT(id) c FROM informes_estudios WHERE paciente_id = ? AND estado IN ('finalizado','firmado')")) {
        $s->bind_param('i', $usuario_id); $s->execute();
        $informes_total = (int)$s->get_result()->fetch_assoc()['c']; $s->close();
    }

    /* Informes recientes (últimos 3 finalizados) */
    $informes_recientes = [];
    if ($s = $conex->prepare("SELECT ie.id, ie.numero_informe, ie.fecha_estudio, ie.creado_en,
            t.nombre AS tipo_nombre, t.icono AS tipo_icono, u.nombre_completo AS ecografista_nombre
            FROM informes_estudios ie
            LEFT JOIN tipos_ecografias t ON t.id = ie.tipo_ecografia_id
            LEFT JOIN usuarios u ON u.id = ie.ecografista_id
            WHERE ie.paciente_id = ? AND ie.estado IN ('finalizado','firmado')
            ORDER BY ie.creado_en DESC LIMIT 3")) {
        $s->bind_param('i', $usuario_id); $s->execute();
        $informes_recientes = $s->get_result()->fetch_all(MYSQLI_ASSOC); $s->close();
    }

    $meses_es = [1=>'enero',2=>'febrero',3=>'marzo',4=>'abril',5=>'mayo',6=>'junio',7=>'julio',8=>'agosto',9=>'septiembre',10=>'octubre',11=>'noviembre',12=>'diciembre'];
    $nextTs = ($next && !empty($next['fecha_cita'])) ? strtotime($next['fecha_cita']) : null;
?>

<!-- Hero de bienvenida -->
<div class="card" style="margin-bottom:18px;">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;">
        <div>
            <h2 style="margin:0 0 4px;font-size:20px;font-weight:700;color:var(--text-primary);">Hola, <?= htmlspecialchars($primer_nombre) ?> 👋</h2>
            <p style="margin:0;font-size:13.5px;color:var(--text-secondary);">Bienvenido a tu portal clínico. Gestiona tus citas y consulta tus resultados ecográficos.</p>
        </div>
        <a href="<?= eco_url('solicitar-cita') ?>" class="btn-primary" style="white-space:nowrap;"><i class="fa-solid fa-plus"></i> Solicitar nueva cita</a>
    </div>
</div>

<!-- Indicadores -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-card-icon"><i class="fa-solid fa-calendar-day"></i></div>
        <p class="stat-card-label">Próxima cita</p>
        <?php if ($nextTs): ?>
            <p class="stat-card-value accent" style="font-size:19px;"><?= date('d', $nextTs) . ' ' . ($meses_es[(int)date('n', $nextTs)] ?? '') ?></p>
            <p class="stat-card-sub"><?= date('h:i A', $nextTs) ?><?= !empty($next['profesional_nombre']) ? ' · ' . htmlspecialchars($next['profesional_nombre']) : '' ?></p>
        <?php else: ?>
            <p class="stat-card-value" style="font-size:19px;color:var(--text-muted);">Sin citas</p>
            <p class="stat-card-sub">agenda tu estudio</p>
        <?php endif; ?>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon" style="background:rgba(245,158,11,.14);color:#b45309;"><i class="fa-solid fa-hourglass-half"></i></div>
        <p class="stat-card-label">Solicitudes pendientes</p>
        <p class="stat-card-value warning"><?= $solicitudes_pendientes ?></p>
        <p class="stat-card-sub">en espera de confirmación</p>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon" style="background:rgba(34,197,94,.12);color:#15803d;"><i class="fa-solid fa-check-double"></i></div>
        <p class="stat-card-label">Citas completadas</p>
        <p class="stat-card-value success"><?= $citas_completadas ?></p>
        <p class="stat-card-sub">estudios realizados</p>
    </div>
    <a href="<?= eco_url('mis-informes') ?>" class="stat-card" style="text-decoration:none;">
        <div class="stat-card-icon"><i class="fa-solid fa-file-medical"></i></div>
        <p class="stat-card-label">Mis informes</p>
        <p class="stat-card-value accent"><?= $informes_total ?></p>
        <p class="stat-card-sub">resultados disponibles →</p>
    </a>
</div>

<!-- Accesos rápidos -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:12px;margin-bottom:18px;">
    <?php
    $accesos = [
        ['solicitar-cita', 'fa-solid fa-file-circle-plus', 'Solicitar cita'],
        ['mis-citas',      'fa-solid fa-calendar-check',   'Mis citas'],
        ['mis-informes',   'fa-solid fa-file-medical',     'Mis informes'],
        ['ecografistas',   'fa-solid fa-user-doctor',      'Ecografistas'],
        ['faq',            'fa-solid fa-circle-question',  'Preguntas'],
        ['ayuda',          'fa-solid fa-life-ring',        'Ayuda'],
        ['mis-pagos',      'fa-solid fa-receipt',          'Pagos'],
    ];
    // eco_url() y no la ruta suelta: en relativo el navegador la resolvería
    // contra la página actual, que hoy funciona por casualidad.
    foreach ($accesos as $a): ?>
        <a href="<?= eco_url($a[0]) ?>" class="card" style="text-decoration:none;color:inherit;display:flex;align-items:center;gap:12px;padding:16px;">
            <span style="width:38px;height:38px;border-radius:10px;background:var(--accent-soft);color:var(--accent-text);display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="<?= $a[1] ?>"></i></span>
            <strong style="font-size:13.5px;color:var(--text-primary);"><?= $a[2] ?></strong>
        </a>
    <?php endforeach; ?>
</div>

<!-- Informes recientes -->
<div class="card" style="margin-bottom:18px;">
    <div class="card-header">
        <h3><i class="fa-solid fa-file-waveform" style="margin-right:8px;color:var(--accent);"></i> Informes recientes</h3>
        <a href="<?= eco_url('mis-informes') ?>" style="font-size:12.5px;color:var(--accent-text);font-weight:600;text-decoration:none;">Ver todos →</a>
    </div>
    <?php if (empty($informes_recientes)): ?>
        <p style="color:var(--text-muted);font-size:13.5px;margin:6px 0;">Aún no tienes informes. Tus resultados aparecerán aquí al finalizar un estudio.</p>
    <?php else: foreach ($informes_recientes as $i => $inf):
        $raw = $inf['fecha_estudio'] ?: substr($inf['creado_en'], 0, 10);
        $f   = $raw ? date('d/m/Y', strtotime($raw)) : '—';
    ?>
        <a href="<?= eco_url('informe/' . (int)$inf['id']) ?>" target="_blank" rel="noopener" style="display:flex;align-items:center;gap:14px;padding:12px 6px;<?= $i > 0 ? 'border-top:1px solid var(--border-soft);' : '' ?>text-decoration:none;color:inherit;">
            <!-- Plano y en tono suave, como los accesos rápidos: el bloque de
                 color macizo pesaba más que el propio nombre del estudio. -->
            <span style="width:38px;height:38px;border-radius:10px;background:var(--accent-soft);color:var(--accent-text);font-size:14px;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="<?= htmlspecialchars($inf['tipo_icono'] ?: 'fa-solid fa-wave-square', ENT_QUOTES) ?>"></i></span>
            <span style="flex:1;min-width:0;">
                <strong style="display:block;font-size:13.5px;color:var(--text-primary);"><?= htmlspecialchars($inf['tipo_nombre'] ?: 'Ecografía') ?></strong>
                <small style="color:var(--text-secondary);"><?= htmlspecialchars($f) ?> · <?= htmlspecialchars($inf['ecografista_nombre'] ?: '—') ?></small>
            </span>
            <i class="fa-solid fa-chevron-right" style="color:var(--text-muted);font-size:12px;"></i>
        </a>
    <?php endforeach; endif; ?>
</div>

<!-- Frecuencia de citas -->
<div class="card">
    <div class="card-header">
        <h3><i class="fa-solid fa-chart-line" style="margin-right:7px;color:var(--accent);"></i> Frecuencia de citas (últimos 8 meses)</h3>
    </div>
    <div style="position:relative;height:260px;">
        <canvas id="patientDashChart"></canvas>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
fetch((window.ECO_BASE || '') + 'api/get_patient_chart_data.php').then(r => r.json()).then(d => {
    const ctx = document.getElementById('patientDashChart');
    if (!ctx) return;
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: d.labels,
            datasets: [{
                label: 'Citas confirmadas',
                data: d.data,
                fill: true,
                backgroundColor: 'rgba(2,177,244,0.08)',
                borderColor: '#02b1f4',
                tension: 0.35,
                pointRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
            plugins: { legend: { display: false } }
        }
    });
}).catch(() => {});
</script>

<?php
/* ===================================================================
   DASHBOARD RECEPCIONISTA
   =================================================================== */
elseif ($rol === 'recepcionista'):

    require_once __DIR__ . '/../lib/panel/metricas_recepcion.php';

    $rx_dias      = 14;
    $rx_serie     = eco_panel_rx_serie_diaria($conex, $rx_dias);
    $rx_tot       = eco_panel_rx_totales($conex);
    $rx_por_edad  = eco_panel_rx_por_edad($conex);

    /** Altura de barra en %, con el máximo de la serie como tope. */
    $rx_pct = static function (float $v, float $max): float {
        return $max > 0 ? round(($v / $max) * 100, 2) : 0.0;
    };

    $total_pendientes = $citas_hoy_rx = $pacientes_activos = $eco_activos = $nuevas_hoy = 0;

    if ($r = $conex->query("SELECT COUNT(*) c FROM citas WHERE estado = 'pendiente'")) {
        $total_pendientes = (int)$r->fetch_assoc()['c'];
        $r->free();
    }
    if ($r = $conex->query("SELECT COUNT(*) c FROM citas WHERE estado IN ('confirmada','reprogramada') AND DATE(fecha_cita) = CURDATE()")) {
        $citas_hoy_rx = (int)$r->fetch_assoc()['c'];
        $r->free();
    }
    if ($r = $conex->query("SELECT COUNT(*) c FROM usuarios WHERE rol = 'paciente' AND estado = 'aprobado'")) {
        $pacientes_activos = (int)$r->fetch_assoc()['c'];
        $r->free();
    }
    if ($r = $conex->query("SELECT COUNT(*) c FROM usuarios WHERE rol = 'ecografista' AND estado = 'aprobado'")) {
        $eco_activos = (int)$r->fetch_assoc()['c'];
        $r->free();
    }
    if ($r = $conex->query("SELECT COUNT(*) c FROM citas WHERE estado = 'pendiente' AND fecha_solicitud >= (NOW() - INTERVAL 1 DAY)")) {
        $nuevas_hoy = (int)$r->fetch_assoc()['c'];
        $r->free();
    }

    $agenda_hoy = [];
    if ($s = $conex->prepare("SELECT c.fecha_cita, c.motivo_consulta, u.nombre_completo AS paciente_nombre, prof.nombre_completo AS profesional_nombre
        FROM citas c
        JOIN usuarios u ON c.paciente_id = u.id
        LEFT JOIN usuarios prof ON c.ecografista_id = prof.id
        WHERE c.estado IN ('confirmada','reprogramada') AND DATE(c.fecha_cita) = CURDATE()
        ORDER BY c.fecha_cita ASC LIMIT 5")) {
        $s->execute();
        $agenda_hoy = $s->get_result()->fetch_all(MYSQLI_ASSOC);
        $s->close();
    }

    $solicitudes_recientes = [];
    if ($s = $conex->prepare("SELECT c.id, c.fecha_solicitud, u.nombre_completo AS paciente_nombre, u.correo
        FROM citas c JOIN usuarios u ON c.paciente_id = u.id
        WHERE c.estado = 'pendiente' ORDER BY c.fecha_solicitud DESC LIMIT 5")) {
        $s->execute();
        $solicitudes_recientes = $s->get_result()->fetch_all(MYSQLI_ASSOC);
        $s->close();
    }

    $pacientes_recientes = [];
    if ($r = $conex->query("SELECT nombre_completo, fecha_registro FROM usuarios WHERE rol = 'paciente' AND estado = 'aprobado' ORDER BY fecha_registro DESC LIMIT 3")) {
        $pacientes_recientes = $r->fetch_all(MYSQLI_ASSOC);
        $r->free();
    }
?>

<div class="rxp">

<?php
$rx_max_pac = (float)max(array_column($rx_serie, 'pacientes') ?: [0]);
$rx_max_cob = (float)max(array_column($rx_serie, 'cobrado') ?: [0]);
$rx_sum_pac = array_sum(array_column($rx_serie, 'pacientes'));
$rx_sum_cob = array_sum(array_column($rx_serie, 'cobrado'));
// Mismo formato de fecha que el saludo del ecografista.
$rx_meses = [1=>'enero',2=>'febrero',3=>'marzo',4=>'abril',5=>'mayo',6=>'junio',
             7=>'julio',8=>'agosto',9=>'septiembre',10=>'octubre',11=>'noviembre',12=>'diciembre'];
$rx_hoy_txt = (int)date('d') . ' de ' . $rx_meses[(int)date('n')];
?>

<div class="card rxp-hero">
    <div>
        <h2>Hola, <?= htmlspecialchars($primer_nombre) ?> 👋</h2>
        <p>Hoy es <?= htmlspecialchars($rx_hoy_txt) ?>.
           <?= $citas_hoy_rx > 0
                ? 'Hay <strong style="color:var(--accent-text);">' . (int)$citas_hoy_rx . '</strong> cita' . ($citas_hoy_rx === 1 ? '' : 's') . ' confirmada' . ($citas_hoy_rx === 1 ? '' : 's') . ' para hoy'
                : 'No hay citas confirmadas para hoy' ?><?= $total_pendientes > 0
                ? ' y <strong style="color:#b45309;">' . (int)$total_pendientes . '</strong> solicitud' . ($total_pendientes === 1 ? '' : 'es') . ' por asignar.'
                : '.' ?></p>
    </div>
    <a href="<?= eco_url('agenda') ?>" class="btn-primary" style="white-space:nowrap;"><i class="fa-solid fa-calendar-days"></i> Agenda general</a>
</div>

<div class="rxp-kpis">
    <div class="stat-card">
        <div class="stat-card-icon" style="background:rgba(2,132,199,.12);color:#0284c7;"><i class="fa-solid fa-user-check"></i></div>
        <p class="stat-card-label">Atendidos hoy</p>
        <p class="stat-card-value" style="color:#0284c7;"><?= number_format($rx_tot['hoy_pacientes']) ?></p>
        <p class="stat-card-sub"><?= number_format($rx_tot['mes_pacientes']) ?> en lo que va de mes</p>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon" style="background:rgba(21,128,61,.12);color:#15803d;"><i class="fa-solid fa-money-bill-wave"></i></div>
        <p class="stat-card-label">Cobrado hoy</p>
        <p class="stat-card-value" style="color:#15803d;"><?= htmlspecialchars(eco_money($rx_tot['hoy_cobrado'])) ?></p>
        <p class="stat-card-sub"><?= htmlspecialchars(eco_money($rx_tot['mes_cobrado'])) ?> en lo que va de mes</p>
    </div>
    <a href="<?= eco_url('citas-pendientes') ?>" class="stat-card">
        <div class="stat-card-icon" style="background:rgba(245,158,11,.14);color:#b45309;"><i class="fa-solid fa-inbox"></i></div>
        <p class="stat-card-label">Citas pendientes</p>
        <p class="stat-card-value warning"><?= number_format($total_pendientes) ?></p>
        <p class="stat-card-sub">Por asignar · <?= (int)$nuevas_hoy ?> nuevas en 24 h</p>
    </a>
    <a href="<?= eco_url('gestion-pacientes') ?>" class="stat-card">
        <div class="stat-card-icon" style="background:rgba(99,102,241,.12);color:#4338ca;"><i class="fa-solid fa-users"></i></div>
        <p class="stat-card-label">Pacientes activos</p>
        <p class="stat-card-value" style="color:#4338ca;"><?= number_format($pacientes_activos) ?></p>
        <p class="stat-card-sub"><?= (int)$citas_hoy_rx ?> citas hoy · <?= (int)$eco_activos ?> ecografistas</p>
    </a>
</div>

<div class="rxp-grid">

    <?php /* Dos gráficos separados, no un doble eje: son magnitudes distintas. */ ?>
    <section class="card">
        <div class="rxp-card__head">
            <h3 class="rxp-card__title">Atendidos por día</h3>
            <span class="rxp-card__meta"><?= $rx_dias ?> días · <?= number_format($rx_sum_pac) ?></span>
        </div>
        <p class="rxp-card__note">Pacientes distintos con cita no cancelada.</p>
        <?php if ($rx_sum_pac === 0): ?>
            <p class="viz-vacio">Sin atenciones en este periodo.</p>
        <?php else: ?>
            <div class="viz-cols" role="img"
                 aria-label="Pacientes atendidos por día en los últimos <?= $rx_dias ?> días. Total <?= $rx_sum_pac ?>. Máximo diario <?= (int)$rx_max_pac ?>.">
                <?php $rx_pico_pac = false; foreach ($rx_serie as $p):
                    $esPico = !$rx_pico_pac && $p['pacientes'] > 0 && (float)$p['pacientes'] === $rx_max_pac;
                    if ($esPico) { $rx_pico_pac = true; }
                ?>
                    <div class="viz-col" tabindex="0"
                         aria-label="<?= htmlspecialchars($p['etiqueta']) ?>: <?= (int)$p['pacientes'] ?> paciente<?= $p['pacientes'] === 1 ? '' : 's' ?>">
                        <span class="viz-col__tip"><?= htmlspecialchars($p['etiqueta']) ?> · <?= (int)$p['pacientes'] ?></span>
                        <?php if ($esPico): ?><span class="viz-col__pico"><?= (int)$p['pacientes'] ?></span><?php endif; ?>
                        <span class="viz-col__bar" style="height:<?= $rx_pct((float)$p['pacientes'], $rx_max_pac) ?>%;"></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="viz-xaxis" aria-hidden="true">
                <?php foreach ($rx_serie as $i => $p): ?>
                    <span><?= ($i % 3 === 0 || $i === count($rx_serie) - 1) ? htmlspecialchars($p['etiqueta']) : '' ?></span>
                <?php endforeach; ?>
            </div>
            <details class="viz-tabla">
                <summary>Ver datos</summary>
                <table>
                    <thead><tr><th scope="col">Día</th><th scope="col">Pacientes</th></tr></thead>
                    <tbody>
                        <?php foreach ($rx_serie as $p): ?>
                            <tr><td><?= htmlspecialchars($p['etiqueta']) ?></td><td><?= (int)$p['pacientes'] ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </details>
        <?php endif; ?>
    </section>

    <section class="card">
        <div class="rxp-card__head">
            <h3 class="rxp-card__title">Cobrado por día</h3>
            <span class="rxp-card__meta"><?= $rx_dias ?> días · <?= htmlspecialchars(eco_money($rx_sum_cob)) ?></span>
        </div>
        <p class="rxp-card__note">Dinero recibido, no facturado. Pendiente: <?= htmlspecialchars(eco_money($rx_tot['pendiente'])) ?>.</p>
        <?php if ($rx_sum_cob <= 0): ?>
            <p class="viz-vacio">Sin cobros en este periodo.</p>
        <?php else: ?>
            <div class="viz-cols" role="img"
                 aria-label="Dinero cobrado por día en los últimos <?= $rx_dias ?> días. Total <?= htmlspecialchars(eco_money($rx_sum_cob)) ?>.">
                <?php $rx_pico_cob = false; foreach ($rx_serie as $p):
                    $esPico = !$rx_pico_cob && $p['cobrado'] > 0 && (float)$p['cobrado'] === $rx_max_cob;
                    if ($esPico) { $rx_pico_cob = true; }
                ?>
                    <div class="viz-col viz-col--money" tabindex="0"
                         aria-label="<?= htmlspecialchars($p['etiqueta']) ?>: <?= htmlspecialchars(eco_money((float)$p['cobrado'])) ?>">
                        <span class="viz-col__tip"><?= htmlspecialchars($p['etiqueta']) ?> · <?= htmlspecialchars(eco_money((float)$p['cobrado'])) ?></span>
                        <?php if ($esPico): ?><span class="viz-col__pico"><?= htmlspecialchars(eco_money((float)$p['cobrado'])) ?></span><?php endif; ?>
                        <span class="viz-col__bar" style="height:<?= $rx_pct((float)$p['cobrado'], $rx_max_cob) ?>%;"></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="viz-xaxis" aria-hidden="true">
                <?php foreach ($rx_serie as $i => $p): ?>
                    <span><?= ($i % 3 === 0 || $i === count($rx_serie) - 1) ? htmlspecialchars($p['etiqueta']) : '' ?></span>
                <?php endforeach; ?>
            </div>
            <details class="viz-tabla">
                <summary>Ver datos</summary>
                <table>
                    <thead><tr><th scope="col">Día</th><th scope="col">Cobrado</th></tr></thead>
                    <tbody>
                        <?php foreach ($rx_serie as $p): ?>
                            <tr><td><?= htmlspecialchars($p['etiqueta']) ?></td><td><?= htmlspecialchars(eco_money((float)$p['cobrado'])) ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </details>
        <?php endif; ?>
    </section>

    <section class="card">
        <div class="rxp-card__head">
            <h3 class="rxp-card__title">Agenda de hoy</h3>
            <a href="<?= eco_url('agenda') ?>" class="rxp-card__link">Calendario →</a>
        </div>
        <p class="rxp-card__note">Citas confirmadas o reprogramadas.</p>
        <?php if (empty($agenda_hoy)): ?>
            <p class="viz-vacio">No hay citas confirmadas para hoy.</p>
        <?php else: ?>
            <ul class="rxp-lista">
                <?php foreach ($agenda_hoy as $row):
                    $hora = !empty($row['fecha_cita']) ? date('h:i A', strtotime($row['fecha_cita'])) : '—';
                ?>
                    <li class="rxp-item">
                        <span class="rxp-item__meta"><?= htmlspecialchars($hora) ?></span>
                        <span class="rxp-item__nombre"><?= htmlspecialchars($row['paciente_nombre'] ?? '') ?></span>
                        <span class="rxp-item__extra"><i class="fa-solid fa-user-doctor"></i> <?= htmlspecialchars($row['profesional_nombre'] ?? 'Por asignar') ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="card">
        <div class="rxp-card__head">
            <h3 class="rxp-card__title">Por edad</h3>
            <span class="rxp-card__meta"><?= number_format($rx_por_edad['total'] - $rx_por_edad['sin_fecha']) ?> pacientes</span>
        </div>
        <p class="rxp-card__note">
            <?php if ($rx_por_edad['sin_fecha'] > 0): ?>
                <?= number_format($rx_por_edad['sin_fecha']) ?> de <?= number_format($rx_por_edad['total']) ?> sin fecha de nacimiento.
            <?php else: ?>
                Todos con fecha de nacimiento registrada.
            <?php endif; ?>
        </p>
        <?php
        $rx_edad_total = array_sum(array_column($rx_por_edad['filas'], 'n'));
        if ($rx_edad_total === 0): ?>
            <p class="viz-vacio">Sin fechas de nacimiento registradas.</p>
        <?php else:
            $rx_max_edad = (float)max(array_column($rx_por_edad['filas'], 'n')); ?>
            <div class="viz-cols" role="img"
                 aria-label="Pacientes por rango de edad, sobre <?= $rx_edad_total ?> con fecha registrada.">
                <?php $rx_pico_edad = false; foreach ($rx_por_edad['filas'] as $f):
                    $esPico = !$rx_pico_edad && $f['n'] > 0 && (float)$f['n'] === $rx_max_edad;
                    if ($esPico) { $rx_pico_edad = true; }
                ?>
                    <div class="viz-col" tabindex="0"
                         aria-label="<?= htmlspecialchars($f['rango']) ?> años: <?= (int)$f['n'] ?> paciente<?= $f['n'] === 1 ? '' : 's' ?>">
                        <span class="viz-col__tip"><?= htmlspecialchars($f['rango']) ?> años · <?= (int)$f['n'] ?></span>
                        <?php if ($esPico): ?><span class="viz-col__pico"><?= (int)$f['n'] ?></span><?php endif; ?>
                        <span class="viz-col__bar" style="height:<?= $rx_pct((float)$f['n'], $rx_max_edad) ?>%;"></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="viz-xaxis" aria-hidden="true">
                <?php foreach ($rx_por_edad['filas'] as $f): ?>
                    <span><?= htmlspecialchars($f['rango']) ?></span>
                <?php endforeach; ?>
            </div>
            <details class="viz-tabla">
                <summary>Ver datos</summary>
                <table>
                    <thead><tr><th scope="col">Edad</th><th scope="col">Pacientes</th></tr></thead>
                    <tbody>
                        <?php foreach ($rx_por_edad['filas'] as $f): ?>
                            <tr><td><?= htmlspecialchars($f['rango']) ?> años</td><td><?= (int)$f['n'] ?></td></tr>
                        <?php endforeach; ?>
                        <?php if ($rx_por_edad['sin_fecha'] > 0): ?>
                            <tr><td>Sin registrar</td><td><?= (int)$rx_por_edad['sin_fecha'] ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </details>
        <?php endif; ?>
    </section>

    <?php /* Ocupa dos columnas: sin el gráfico de tipos la fila quedaba coja,
             y aquí el ancho extra sirve (los correos son largos). */ ?>
    <section class="card rxp-ancho">
        <div class="rxp-card__head">
            <h3 class="rxp-card__title">Solicitudes recientes</h3>
            <a href="<?= eco_url('citas-pendientes') ?>" class="rxp-card__link">Ver todas →</a>
        </div>
        <p class="rxp-card__note">Pendientes de asignar ecografista.</p>
        <?php if (empty($solicitudes_recientes)): ?>
            <p class="viz-vacio">No hay solicitudes pendientes.</p>
        <?php else: ?>
            <ul class="rxp-lista">
                <?php foreach ($solicitudes_recientes as $sol):
                    $fs = !empty($sol['fecha_solicitud']) ? date('d/m H:i', strtotime($sol['fecha_solicitud'])) : '—';
                ?>
                    <li class="rxp-item">
                        <span class="rxp-item__meta"><?= htmlspecialchars($fs) ?></span>
                        <span class="rxp-item__nombre"><?= htmlspecialchars($sol['paciente_nombre'] ?? '') ?></span>
                        <span class="rxp-item__extra"><?= htmlspecialchars($sol['correo'] ?? '') ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

</div>

</div><!-- /.rxp -->

<?php
/* ===================================================================
   FALLBACK (otros roles)
   =================================================================== */
else:
?>
<div class="card" style="text-align:center;padding:60px 20px;">
    <i class="fa-solid fa-rocket" style="font-size:3rem;color:var(--accent);opacity:.6;margin-bottom:14px;"></i>
    <h2 style="margin:0 0 8px;color:var(--text-primary);">Bienvenido, <?= htmlspecialchars($primer_nombre) ?></h2>
    <p style="color:var(--text-secondary);margin:0 0 20px;">Tu panel personalizado se está construyendo. Mientras tanto, usa el menú lateral para acceder a tus funciones.</p>
</div>
<?php endif;

$page_content = ob_get_clean();

if ($rol === 'administrador') {
    $page_head_extra = '<link rel="stylesheet" href="assets/css/admin/admin-dashboard.css">'
        . '<link rel="stylesheet" href="assets/css/admin/admin-dashboard-modals.css">';

    ob_start();
    include __DIR__ . '/../layouts/partials/modal_dashboard_admin_kpi.php';
    $admin_kpi_modals_html = ob_get_clean();

    $page_scripts_extra = ($admin_kpi_modals_html ?? '')
        . '<script src="assets/js/admin/admin-dashboard-modals.js"></script>';
} elseif ($rol === 'recepcionista') {
    // ?v=auto lo resuelve shell.php con filemtime.
    $page_head_extra = '<link rel="stylesheet" href="assets/css/panel/panel-recepcion.css?v=auto">';
}

include __DIR__ . '/../layouts/shell.php';
