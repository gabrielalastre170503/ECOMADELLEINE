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
<div class="card" style="margin-bottom:18px;background:linear-gradient(135deg,var(--accent-soft),var(--bg-surface));border:1px solid rgba(2,177,244,.2);">
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
    ];
    foreach ($accesos as $a): ?>
        <a href="<?= $a[0] ?>" class="card" style="text-decoration:none;color:inherit;display:flex;align-items:center;gap:12px;padding:16px;">
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
            <span style="width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg,var(--accent),#38bdf8);color:#fff;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="<?= htmlspecialchars($inf['tipo_icono'] ?: 'fa-solid fa-wave-square', ENT_QUOTES) ?>"></i></span>
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

<div class="stats-grid">
    <a href="<?= eco_url('citas-pendientes') ?>" class="stat-card" style="text-decoration:none;color:inherit;">
        <div class="stat-card-icon" style="background:rgba(245,158,11,.12);color:#b45309;"><i class="fa-solid fa-inbox"></i></div>
        <p class="stat-card-label">Citas pendientes</p>
        <p class="stat-card-value warning"><?= number_format($total_pendientes) ?></p>
        <p class="stat-card-sub">Por asignar</p>
    </a>
    <div class="stat-card">
        <div class="stat-card-icon" style="background:rgba(34,197,94,.12);color:#15803d;"><i class="fa-solid fa-calendar-day"></i></div>
        <p class="stat-card-label">Citas hoy</p>
        <p class="stat-card-value success"><?= number_format($citas_hoy_rx) ?></p>
        <p class="stat-card-sub">Confirmadas / reprogramadas</p>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon" style="background:rgba(99,102,241,.12);color:#4338ca;"><i class="fa-solid fa-users"></i></div>
        <p class="stat-card-label">Pacientes activos</p>
        <p class="stat-card-value" style="color:#4338ca;"><?= number_format($pacientes_activos) ?></p>
        <p class="stat-card-sub">Cuentas aprobadas</p>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon" style="background:rgba(249,115,22,.12);color:#c2410c;"><i class="fa-solid fa-user-doctor"></i></div>
        <p class="stat-card-label">Ecografistas</p>
        <p class="stat-card-value warning"><?= number_format($eco_activos) ?></p>
        <p class="stat-card-sub">Equipo disponible</p>
    </div>
</div>

<p style="font-size:12.5px;color:var(--text-muted);margin:-6px 0 14px;"><i class="fa-solid fa-bolt" style="color:var(--accent);"></i> <?= (int)$nuevas_hoy ?> solicitudes nuevas en las últimas 24 h</p>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,300px),1fr));gap:16px;">
    <div class="card">
        <div class="card-header">
            <h3><i class="fa-solid fa-calendar-check" style="color:var(--accent);margin-right:6px;"></i> Agenda de hoy</h3>
            <a href="<?= eco_url('agenda') ?>" style="font-size:12.5px;font-weight:600;color:var(--accent-text);">Calendario completo →</a>
        </div>
        <?php if (empty($agenda_hoy)): ?>
            <p style="color:var(--text-muted);margin:0;font-size:13.5px;">No hay citas confirmadas para hoy.</p>
        <?php else: ?>
            <div style="display:flex;flex-direction:column;gap:10px;">
                <?php foreach ($agenda_hoy as $row):
                    $hora = !empty($row['fecha_cita']) ? date('h:i A', strtotime($row['fecha_cita'])) : '—';
                    $mot = isset($row['motivo_consulta']) ? trim((string)$row['motivo_consulta']) : '';
                    if (strlen($mot) > 90) {
                        $mot = substr($mot, 0, 87) . '…';
                    }
                    $mot = $mot !== '' ? $mot : 'Sin motivo';
                ?>
                    <div style="padding:10px 12px;border:1px solid var(--border-soft);border-radius:10px;">
                        <div style="font-size:12px;font-weight:700;color:var(--accent-text);"><?= htmlspecialchars($hora) ?></div>
                        <strong style="font-size:13.5px;color:var(--text-primary);"><?= htmlspecialchars($row['paciente_nombre'] ?? '') ?></strong>
                        <div style="font-size:12.5px;color:var(--text-secondary);margin-top:4px;"><?= htmlspecialchars($mot) ?></div>
                        <div style="font-size:11.5px;color:var(--text-muted);margin-top:4px;"><i class="fa-solid fa-user-doctor"></i> <?= htmlspecialchars($row['profesional_nombre'] ?? 'Por asignar') ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-header">
            <h3><i class="fa-solid fa-paper-plane" style="color:#6366f1;margin-right:6px;"></i> Solicitudes recientes</h3>
            <a href="<?= eco_url('citas-pendientes') ?>" style="font-size:12.5px;font-weight:600;color:var(--accent-text);">Ver todas →</a>
        </div>
        <?php if (empty($solicitudes_recientes)): ?>
            <p style="color:var(--text-muted);margin:0;font-size:13.5px;">No hay solicitudes pendientes.</p>
        <?php else: ?>
            <ul style="margin:0;padding:0;list-style:none;display:flex;flex-direction:column;gap:10px;">
                <?php foreach ($solicitudes_recientes as $sol):
                    $fs = !empty($sol['fecha_solicitud']) ? date('d/m H:i', strtotime($sol['fecha_solicitud'])) : '—';
                ?>
                    <li style="padding:10px 12px;border-radius:10px;background:var(--bg-muted);border:1px solid var(--border-soft);">
                        <div style="font-size:11px;color:var(--text-muted);"><?= htmlspecialchars($fs) ?></div>
                        <strong style="font-size:13px;color:var(--text-primary);"><?= htmlspecialchars($sol['paciente_nombre'] ?? '') ?></strong>
                        <div style="font-size:12px;color:var(--text-secondary);"><?= htmlspecialchars($sol['correo'] ?? '') ?></div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-header">
            <h3><i class="fa-solid fa-user-plus" style="color:#15803d;margin-right:6px;"></i> Pacientes recientes</h3>
            <a href="<?= eco_url('gestion-pacientes') ?>" style="font-size:12.5px;font-weight:600;color:var(--accent-text);">Gestión →</a>
        </div>
        <?php if (empty($pacientes_recientes)): ?>
            <p style="color:var(--text-muted);margin:0;font-size:13.5px;">Sin registros recientes.</p>
        <?php else: ?>
            <ul style="margin:0;padding:0;list-style:none;">
                <?php foreach ($pacientes_recientes as $px):
                    $fr = !empty($px['fecha_registro']) ? date('d/m', strtotime($px['fecha_registro'])) : '—';
                ?>
                    <li style="padding:8px 0;border-bottom:1px solid var(--border-soft);display:flex;justify-content:space-between;gap:10px;align-items:center;">
                        <span style="font-weight:600;font-size:13.5px;color:var(--text-primary);"><?= htmlspecialchars($px['nombre_completo'] ?? '') ?></span>
                        <span style="font-size:12px;color:var(--text-muted);"><?= htmlspecialchars($fr) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

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
}

include __DIR__ . '/../layouts/shell.php';
