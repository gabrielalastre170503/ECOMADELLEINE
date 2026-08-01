<?php
session_start();
include __DIR__ . '/../core/conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . eco_url('login'));
    exit;
}
if ($_SESSION['rol'] !== 'paciente') {
    header('Location: ' . eco_url('dashboard'));
    exit;
}

$usuario_id = (int)$_SESSION['usuario_id'];

/* Notificaciones (se muestran una vez y se limpian) */
$notificaciones = [];
if ($stmt_notif = $conex->prepare('SELECT id, notificacion_paciente FROM citas WHERE paciente_id = ? AND notificacion_paciente IS NOT NULL')) {
    $stmt_notif->bind_param('i', $usuario_id);
    $stmt_notif->execute();
    $res = $stmt_notif->get_result();
    while ($row = $res->fetch_assoc()) {
        $notificaciones[] = $row;
        if ($stmt_clear = $conex->prepare('UPDATE citas SET notificacion_paciente = NULL WHERE id = ?')) {
            $stmt_clear->bind_param('i', $row['id']);
            $stmt_clear->execute();
            $stmt_clear->close();
        }
    }
    $stmt_notif->close();
}

/* Citas del paciente */
$citas = [];
$q = "
    SELECT c.id, c.fecha_cita, c.fecha_propuesta, c.estado, c.fecha_solicitud,
           p.nombre_completo AS ecografista_nombre,
           t.nombre AS tipo_estudio, t.icono AS tipo_icono, t.categoria AS tipo_categoria,
           e.puntuacion AS encuesta_punt
    FROM citas c
    LEFT JOIN usuarios p ON c.ecografista_id = p.id
    LEFT JOIN tipos_ecografias t ON c.tipo_ecografia_id = t.id
    LEFT JOIN encuestas e ON e.cita_id = c.id
    WHERE c.paciente_id = ?
    ORDER BY c.fecha_solicitud DESC
";
if ($stmt = $conex->prepare($q)) {
    $stmt->bind_param('i', $usuario_id);
    $stmt->execute();
    $citas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$msg_ok = '';
if (isset($_GET['status']) && $_GET['status'] === 'cita_creada') {
    $msg_ok = 'Tu solicitud de cita se registró correctamente. Te notificaremos cuando el ecografista la confirme.';
}

/* ── Metadatos de estado (etiqueta, badge, color, grupo de filtro) ── */
$meses_abbr = [1 => 'ENE', 2 => 'FEB', 3 => 'MAR', 4 => 'ABR', 5 => 'MAY', 6 => 'JUN', 7 => 'JUL', 8 => 'AGO', 9 => 'SEP', 10 => 'OCT', 11 => 'NOV', 12 => 'DIC'];

/* Todas las tarjetas del mismo azul: el acento del sistema, el que ya usan la
   barra lateral, los botones y las tarjetas de "Mis Informes". El estado se
   sigue leyendo en el distintivo y en la nota de cada tarjeta. */
define('ECO_CITA_AZUL', '#02b1f4');

$estado_meta = [
    'confirmada'         => ['Confirmada',  'badge-accent',  ECO_CITA_AZUL, 'proxima'],
    'completada'         => ['Completada',  'badge-info',    ECO_CITA_AZUL, 'historial'],
    'pendiente'          => ['Pendiente',   'badge-warning', ECO_CITA_AZUL, 'pendiente'],
    'pendiente_paciente' => ['Pospuesta',   'badge-warning', ECO_CITA_AZUL, 'pendiente'],
    'reprogramada'       => ['Reprogramada','badge-purple',  ECO_CITA_AZUL, 'proxima'],
    'cancelada'          => ['Cancelada',   'badge-danger',  ECO_CITA_AZUL, 'historial'],
    'rechazada'          => ['Rechazada',   'badge-danger',  ECO_CITA_AZUL, 'historial'],
];
$meta_default = ['Solicitada', 'badge-accent', ECO_CITA_AZUL, 'pendiente'];

/* Qué significa cada estado PARA EL PACIENTE. El badge dice cómo se llama;
   esto dice qué tiene que hacer, que es lo que preguntaría en recepción. */
$estado_nota = [
    'confirmada'         => 'Tu cita está confirmada. Preséntate 10 minutos antes.',
    'completada'         => 'Estudio realizado. El informe aparecerá en «Mis Informes».',
    'pendiente'          => 'Esperando que el ecografista confirme la fecha.',
    'pendiente_paciente' => 'El ecografista propuso otra fecha: entra en los detalles para aceptarla o rechazarla.',
    'reprogramada'       => 'La cita se movió de fecha. Revisa el día y la hora.',
    'cancelada'          => 'Esta cita fue cancelada.',
    'rechazada'          => 'La solicitud no se aprobó. Puedes solicitar otra fecha.',
];

/* ── Estadísticas + próxima cita ── */
$total        = count($citas);
$num_prox     = 0;
$num_pend     = 0;
$num_comp     = 0;
$proxima_ts   = null;
$proxima_cita = null;
$ahora        = time();

foreach ($citas as $c) {
    $grupo = ($estado_meta[$c['estado']] ?? $meta_default)[3];
    if ($grupo === 'pendiente') $num_pend++;
    if ($c['estado'] === 'completada') $num_comp++;

    $efectiva = ($c['estado'] === 'pendiente_paciente' && !empty($c['fecha_propuesta']))
        ? $c['fecha_propuesta'] : $c['fecha_cita'];
    if (in_array($c['estado'], ['confirmada', 'reprogramada'], true) && !empty($efectiva)) {
        $ts = strtotime($efectiva);
        if ($ts && $ts >= $ahora) {
            $num_prox++;
            if ($proxima_ts === null || $ts < $proxima_ts) {
                $proxima_ts   = $ts;
                $proxima_cita = $c;
            }
        }
    }
}

$proxima_label = '—';
$proxima_sub   = 'sin citas agendadas';
if ($proxima_ts !== null) {
    $proxima_label = date('d/m/Y', $proxima_ts);
    $proxima_sub   = date('h:i A', $proxima_ts);
}

/* Cuenta atrás por días de calendario, no por horas: una cita de mañana a
   primera hora está "mañana" aunque falten menos de 24 horas. */
$cuenta_texto = '';
$cuenta_clase = '';
if ($proxima_ts !== null) {
    $dias = (int)floor((strtotime(date('Y-m-d', $proxima_ts)) - strtotime(date('Y-m-d', $ahora))) / 86400);
    if ($dias <= 0) {
        $cuenta_texto = 'Es hoy';
        $cuenta_clase = 'mc-cuenta--hoy';
    } elseif ($dias === 1) {
        $cuenta_texto = 'Es mañana';
        $cuenta_clase = 'mc-cuenta--pronto';
    } elseif ($dias <= 7) {
        $cuenta_texto = "En $dias días";
        $cuenta_clase = 'mc-cuenta--pronto';
    } else {
        $cuenta_texto = "En $dias días";
    }
}

$page_title       = 'Mis Citas';
$page_subtitle    = 'Consulta el estado de tus citas y los detalles de cada solicitud';
$active_section   = 'miscitas';

$css_citas = 'assets/css/paciente/mis-citas.css';
$page_head_extra = '<link rel="stylesheet" href="' . $css_citas
    . '?v=' . (@filemtime(__DIR__ . '/../' . $css_citas) ?: '1') . '">';

ob_start();
?>

<?php foreach ($notificaciones as $n): ?>
    <div class="cita-aviso">
        <i class="fa-solid fa-bell"></i>
        <p><?= htmlspecialchars($n['notificacion_paciente']) ?></p>
    </div>
<?php endforeach; ?>

<?php if ($proxima_cita !== null): ?>
    <?php
        $px_titulo = $proxima_cita['tipo_estudio'] ?: 'Ecografía';
        $px_icono  = $proxima_cita['tipo_icono'] ?: 'fa-solid fa-wave-square';
    ?>
    <section class="card mc-hero">
        <div class="mc-hero__fecha">
            <span class="mc-hero__dia"><?= date('d', $proxima_ts) ?></span>
            <span class="mc-hero__mes"><?= $meses_abbr[(int)date('n', $proxima_ts)] ?></span>
            <span class="mc-hero__hora"><?= date('H:i', $proxima_ts) ?></span>
        </div>
        <div class="mc-hero__info">
            <p class="mc-hero__eyebrow">
                <i class="fa-solid fa-calendar-day"></i> Tu próxima cita
                <span class="mc-cuenta <?= htmlspecialchars($cuenta_clase) ?>"><?= htmlspecialchars($cuenta_texto) ?></span>
            </p>
            <h2 class="mc-hero__titulo"><i class="<?= htmlspecialchars($px_icono, ENT_QUOTES) ?>" style="color:var(--accent);margin-right:6px;"></i><?= htmlspecialchars($px_titulo) ?></h2>
            <p class="mc-hero__datos">
                <span><i class="fa-regular fa-clock"></i><?= htmlspecialchars(date('d/m/Y', $proxima_ts) . ' · ' . date('h:i A', $proxima_ts)) ?></span>
                <span><i class="fa-solid fa-user-doctor"></i><?= htmlspecialchars($proxima_cita['ecografista_nombre'] ?: 'Sin asignar') ?></span>
                <?php if (!empty($proxima_cita['tipo_categoria'])): ?>
                    <span><i class="fa-solid fa-layer-group"></i><?= htmlspecialchars($proxima_cita['tipo_categoria']) ?></span>
                <?php endif; ?>
            </p>
        </div>
        <div class="mc-hero__acciones">
            <button type="button" class="btn-primary" onclick="abrirDetalleCitaPaciente(<?= (int)$proxima_cita['id'] ?>)">
                <i class="fa-solid fa-eye"></i> Ver detalles
            </button>
            <a href="<?= eco_url('preparacion') ?>" class="btn-secondary">
                <i class="fa-solid fa-clipboard-list"></i> Cómo prepararme
            </a>
        </div>
    </section>
<?php elseif ($total > 0): ?>
    <section class="card mc-hero mc-hero--vacio">
        <div class="mc-hero__fecha"><span class="mc-hero__dia"><i class="fa-regular fa-calendar"></i></span></div>
        <div class="mc-hero__info">
            <p class="mc-hero__eyebrow"><i class="fa-solid fa-calendar-day"></i> Tu próxima cita</p>
            <h2 class="mc-hero__titulo">No tienes citas próximas</h2>
            <p class="mc-hero__datos"><span>Cuando el ecografista confirme una fecha, aparecerá aquí.</span></p>
        </div>
        <div class="mc-hero__acciones">
            <a href="<?= eco_url('solicitar-cita') ?>" class="btn-primary"><i class="fa-solid fa-file-circle-plus"></i> Solicitar cita</a>
        </div>
    </section>
<?php endif; ?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-card-icon"><i class="fa-solid fa-calendar-check"></i></div>
        <p class="stat-card-label">Total de citas</p>
        <p class="stat-card-value accent"><?= $total ?></p>
        <p class="stat-card-sub">solicitudes registradas</p>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon" style="background:rgba(3,105,161,.12);color:#0369a1;"><i class="fa-solid fa-calendar-day"></i></div>
        <p class="stat-card-label">Próxima cita</p>
        <p class="stat-card-value" style="font-size:20px;"><?= htmlspecialchars($proxima_label) ?></p>
        <p class="stat-card-sub"><?= htmlspecialchars($proxima_sub) ?></p>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon" style="background:rgba(245,158,11,.12);color:#b45309;"><i class="fa-solid fa-hourglass-half"></i></div>
        <p class="stat-card-label">Pendientes</p>
        <p class="stat-card-value warning"><?= $num_pend ?></p>
        <p class="stat-card-sub">en espera de confirmación</p>
    </div>
    <a href="<?= eco_url('solicitar-cita') ?>" class="stat-card" style="text-decoration:none;">
        <div class="stat-card-icon"><i class="fa-solid fa-file-circle-plus"></i></div>
        <p class="stat-card-label">Acción rápida</p>
        <p class="stat-card-value accent" style="font-size:18px;">Solicitar cita</p>
        <p class="stat-card-sub">agenda un nuevo estudio</p>
    </a>
</div>

<?php if ($total === 0): ?>
    <div class="card">
        <div class="cita-empty">
            <i class="fa-solid fa-calendar-xmark"></i>
            <p style="margin:0 0 4px;font-weight:600;color:var(--text-secondary);">No tienes ninguna cita solicitada o programada</p>
            <p style="margin:0 0 16px;font-size:13px;">Cuando solicites un estudio, aparecerá aquí con su estado en tiempo real.</p>
            <a href="<?= eco_url('solicitar-cita') ?>" class="btn-primary"><i class="fa-solid fa-file-circle-plus"></i> Solicitar nueva cita</a>
        </div>
    </div>
<?php else: ?>

    <div class="cita-toolbar">
        <div class="cita-search">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" id="cita-search-input" placeholder="Buscar por estudio o ecografista…" autocomplete="off">
        </div>
        <div class="cita-tabs">
            <button type="button" class="cita-tab is-active" data-filter="todas">
                Todas <span class="cita-tab-count"><?= $total ?></span>
            </button>
            <button type="button" class="cita-tab" data-filter="proxima">
                Próximas <span class="cita-tab-count"><?= $num_prox ?></span>
            </button>
            <button type="button" class="cita-tab" data-filter="pendiente">
                Pendientes <span class="cita-tab-count"><?= $num_pend ?></span>
            </button>
            <button type="button" class="cita-tab" data-filter="historial">
                Historial <span class="cita-tab-count"><?= ($total - $num_prox - $num_pend) ?></span>
            </button>
        </div>
    </div>

    <div class="cita-list">
        <?php foreach ($citas as $cita):
            $meta   = $estado_meta[$cita['estado']] ?? $meta_default;
            [$etiqueta, $badge, $color, $grupo] = $meta;

            $efectiva = ($cita['estado'] === 'pendiente_paciente' && !empty($cita['fecha_propuesta']))
                ? $cita['fecha_propuesta'] : $cita['fecha_cita'];
            $ts = $efectiva ? strtotime($efectiva) : null;

            /* Una cita próxima cuya fecha ya pasó pertenece al historial */
            if ($grupo === 'proxima' && $ts !== null && $ts < $ahora) {
                $grupo = 'historial';
            }

            $icono = $cita['tipo_icono'] ?: 'fa-solid fa-wave-square';
            $titulo = $cita['tipo_estudio'] ?: 'Ecografía';
            $nota   = $estado_nota[$cita['estado']] ?? '';
            $busca  = mb_strtolower(trim($titulo . ' ' . ($cita['ecografista_nombre'] ?? '') . ' '
                        . ($cita['tipo_categoria'] ?? '') . ' ' . $etiqueta));
        ?>
            <div class="cita-card" data-grupo="<?= htmlspecialchars($grupo) ?>"
                 data-estado="<?= htmlspecialchars($cita['estado']) ?>"
                 data-search="<?= htmlspecialchars($busca, ENT_QUOTES) ?>"
                 style="--cita-color:<?= htmlspecialchars($color) ?>;">
                <?php if ($ts): ?>
                    <div class="cita-date">
                        <span class="cita-date-day"><?= date('d', $ts) ?></span>
                        <span class="cita-date-month"><?= $meses_abbr[(int)date('n', $ts)] ?></span>
                        <span class="cita-date-time"><?= date('H:i', $ts) ?></span>
                    </div>
                <?php else: ?>
                    <div class="cita-date cita-date--tbd">
                        <i class="fa-solid fa-clock"></i>
                        <span>Por<br>confirmar</span>
                    </div>
                <?php endif; ?>

                <div class="cita-main">
                    <p class="cita-title"><i class="<?= htmlspecialchars($icono, ENT_QUOTES) ?>" style="color:<?= htmlspecialchars($color) ?>;margin-right:7px;"></i><?= htmlspecialchars($titulo) ?></p>
                    <div class="cita-meta">
                        <span><i class="fa-solid fa-user-doctor"></i><?= htmlspecialchars($cita['ecografista_nombre'] ?? 'Sin asignar') ?></span>
                        <?php if (!empty($cita['tipo_categoria'])): ?>
                            <span><i class="fa-solid fa-layer-group"></i><?= htmlspecialchars($cita['tipo_categoria']) ?></span>
                        <?php endif; ?>
                        <?php if ($cita['estado'] === 'pendiente_paciente' && $ts): ?>
                            <span style="color:#b45309;"><i class="fa-solid fa-calendar-day"></i>Nueva fecha propuesta</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($nota !== ''): ?>
                        <p class="cita-nota"><span class="cita-nota__punto" aria-hidden="true"></span><?= htmlspecialchars($nota) ?></p>
                    <?php endif; ?>
                </div>

                <div class="cita-side">
                    <span class="badge <?= $badge ?>"><?= htmlspecialchars($etiqueta) ?></span>
                    <button type="button" class="cita-btn" onclick="abrirDetalleCitaPaciente(<?= (int)$cita['id'] ?>)">
                        <i class="fa-solid fa-eye"></i> Ver detalles
                    </button>
                    <?php if ($grupo === 'proxima'): ?>
                        <button type="button" class="cita-btn cita-btn--cancel" onclick="cancelarCitaPaciente(<?= (int)$cita['id'] ?>)">
                            <i class="fa-solid fa-calendar-xmark"></i> Cancelar
                        </button>
                    <?php endif; ?>
                    <?php if ($cita['estado'] === 'completada'): ?>
                        <?php if (!empty($cita['encuesta_punt'])): ?>
                            <span class="cita-enc-rated" title="Tu valoración">
                                <?php for ($i = 1; $i <= 5; $i++): ?><i class="fa-<?= $i <= (int)$cita['encuesta_punt'] ? 'solid' : 'regular' ?> fa-star"></i><?php endfor; ?>
                            </span>
                        <?php else: ?>
                            <button type="button" class="cita-btn cita-btn--rate" onclick="abrirEncuesta(<?= (int)$cita['id'] ?>)">
                                <i class="fa-solid fa-star"></i> Calificar
                            </button>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <div id="cita-empty-filter" class="card cita-empty" style="display:none;">
            <i class="fa-solid fa-calendar-xmark"></i>
            <p style="margin:0;font-weight:600;color:var(--text-secondary);">No hay citas en esta categoría</p>
        </div>
    </div>

<?php endif; ?>

<div id="eco-modal-detalle-cita-paciente" class="eco-modal" aria-hidden="true" role="dialog">
    <div class="eco-modal__dialog" style="max-width:540px;">
        <div class="eco-modal__main" style="padding-top:24px;">
            <button type="button" class="eco-modal__close" data-eco-modal-close aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
            <div class="cd-head">
                <div class="cd-head__icon"><i class="fa-solid fa-calendar-check"></i></div>
                <div>
                    <h2 class="cd-head__title">Detalles de la cita</h2>
                    <p class="cd-head__sub" id="modal-cita-num">…</p>
                </div>
            </div>
            <div id="modal-cita-body"><p class="cd-loading"><i class="fa-solid fa-spinner fa-spin"></i> Cargando…</p></div>
        </div>
    </div>
</div>

<div id="eco-modal-cancelar-cita" class="eco-modal" aria-hidden="true" role="dialog">
    <div class="eco-modal__dialog" style="max-width:420px;">
        <div class="eco-modal__main" style="padding:32px 26px;text-align:center;">
            <button type="button" class="eco-modal__close" data-eco-modal-close aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
            <div class="cc-icon cc-icon--danger"><i class="fa-solid fa-calendar-xmark"></i></div>
            <h2 class="cc-title">¿Cancelar esta cita?</h2>
            <p class="cc-text">Esta acción no se puede deshacer. Si necesitas otra fecha, podrás solicitar una nueva cita.</p>
            <div class="cc-foot">
                <button type="button" class="btn-secondary" data-eco-modal-close><i class="fa-solid fa-arrow-left"></i> Volver</button>
                <a id="cancelar-cita-confirm" href="#" class="btn-primary" style="background:linear-gradient(135deg,#ef4444,#dc2626);box-shadow:0 4px 12px rgba(239,68,68,.3);"><i class="fa-solid fa-xmark"></i> Sí, cancelar</a>
            </div>
        </div>
    </div>
</div>

<?php if ($msg_ok): ?>
<div id="eco-modal-cita-creada" class="eco-modal" aria-hidden="true" role="dialog">
    <div class="eco-modal__dialog" style="max-width:430px;">
        <div class="eco-modal__main" style="padding:32px 26px;text-align:center;">
            <button type="button" class="eco-modal__close" data-eco-modal-close aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
            <div class="cc-icon"><i class="fa-solid fa-circle-check"></i></div>
            <h2 class="cc-title">¡Solicitud enviada!</h2>
            <p class="cc-text"><?= htmlspecialchars($msg_ok) ?></p>
            <button type="button" class="btn-primary cc-btn" data-eco-modal-close><i class="fa-solid fa-check"></i> Entendido</button>
        </div>
    </div>
</div>
<?php endif; ?>

<div id="eco-modal-encuesta" class="eco-modal" aria-hidden="true" role="dialog">
    <div class="eco-modal__dialog" style="max-width:430px;">
        <div class="eco-modal__main" style="padding:30px 26px;text-align:center;">
            <button type="button" class="eco-modal__close" data-eco-modal-close aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
            <div class="cc-icon" style="background:rgba(251,191,36,.15);color:#d97706;"><i class="fa-solid fa-star"></i></div>
            <h2 class="cc-title">¿Cómo fue tu experiencia?</h2>
            <p class="cc-text">Tu opinión nos ayuda a mejorar la atención.</p>
            <div class="enc-stars" style="font-size:30px;color:#fbbf24;margin:6px 0 14px;">
                <i class="fa-regular fa-star" data-v="1"></i>
                <i class="fa-regular fa-star" data-v="2"></i>
                <i class="fa-regular fa-star" data-v="3"></i>
                <i class="fa-regular fa-star" data-v="4"></i>
                <i class="fa-regular fa-star" data-v="5"></i>
            </div>
            <textarea id="enc-comentario" rows="3" maxlength="1000" placeholder="Comentario (opcional)" style="width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:8px;background:var(--bg-surface);color:var(--text-primary);font-family:inherit;resize:vertical;"></textarea>
            <p id="enc-error" style="color:#dc2626;font-size:12.5px;min-height:16px;margin:8px 0 0;"></p>
            <div class="cc-foot" style="margin-top:10px;">
                <button type="button" class="btn-secondary" data-eco-modal-close><i class="fa-solid fa-arrow-left"></i> Cancelar</button>
                <button type="button" id="enc-submit" class="btn-primary"><i class="fa-solid fa-paper-plane"></i> Enviar</button>
            </div>
        </div>
    </div>
</div>

<?php
$page_content = ob_get_clean();

$page_scripts_extra = <<<'HTML'
<script>
(function () {
    /* Filtro por pestañas + búsqueda. Se combinan en una sola pasada: si cada
       uno ocultara por su cuenta, el segundo volvería a mostrar lo que el
       primero había escondido. */
    var tabs   = document.querySelectorAll('.cita-tab');
    var cards  = document.querySelectorAll('.cita-card');
    var empty  = document.getElementById('cita-empty-filter');
    var search = document.getElementById('cita-search-input');
    var filtro = 'todas';

    function aplicarFiltros() {
        var q = (search && search.value || '').trim().toLowerCase();
        var visibles = 0;
        cards.forEach(function (c) {
            var okGrupo = (filtro === 'todas' || c.getAttribute('data-grupo') === filtro);
            var okBusca = (!q || (c.getAttribute('data-search') || '').indexOf(q) !== -1);
            var show = okGrupo && okBusca;
            c.style.display = show ? '' : 'none';
            if (show) visibles++;
        });
        if (empty) empty.style.display = (visibles === 0) ? '' : 'none';
    }

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            tabs.forEach(function (t) { t.classList.remove('is-active'); });
            tab.classList.add('is-active');
            filtro = tab.getAttribute('data-filter');
            aplicarFiltros();
        });
    });

    if (search) search.addEventListener('input', aplicarFiltros);

    /* Modal de detalles */
    var modalId = 'eco-modal-detalle-cita-paciente';
    var bodyEl  = document.getElementById('modal-cita-body');
    var subEl   = document.getElementById('modal-cita-num');

    /* Aceptar/rechazar la fecha propuesta. Delegado porque esos botones se
       crean al vuelo en renderDetalle() cada vez que se abre el detalle. */
    if (bodyEl) {
        bodyEl.addEventListener('click', function (ev) {
            var b = ev.target.closest('[data-prop-accion]');
            if (!b) return;
            ev.preventDefault();
            window.ecoPost((window.ECO_BASE || '') + 'api/gestionar_propuesta.php', {
                cita_id: b.getAttribute('data-prop-id'),
                accion:  b.getAttribute('data-prop-accion')
            });
        });
    }

    var mesesAbbr = ['ENE','FEB','MAR','ABR','MAY','JUN','JUL','AGO','SEP','OCT','NOV','DIC'];
    var estadoMeta = {
        /* El mismo azul que las tarjetas: si el modal usara otro color, la
           misma cita saldría de un tono en la lista y de otro al abrirla.
           Aquí va un escalón más del acento porque el distintivo del modal
           lleva texto blanco encima y sobre el acento claro no se leería. */
        confirmada:         ['Confirmada',   '#0369a1', 'fa-circle-check'],
        completada:         ['Completada',   '#0369a1', 'fa-clipboard-check'],
        pendiente:          ['Pendiente',    '#0369a1', 'fa-hourglass-half'],
        pendiente_paciente: ['Pospuesta',    '#0369a1', 'fa-clock-rotate-left'],
        reprogramada:       ['Reprogramada', '#0369a1', 'fa-calendar-day'],
        cancelada:          ['Cancelada',    '#0369a1', 'fa-ban'],
        rechazada:          ['Rechazada',    '#0369a1', 'fa-xmark']
    };

    function esc(v) {
        if (v == null) return '';
        return String(v).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    var na = '<span style="color:var(--text-muted);">No especificado</span>';
    function val(v) { return v ? esc(v) : na; }
    function multiline(v) { return v ? esc(v).replace(/\n/g, '<br>') : na; }
    function parseFecha(s) { if (!s) return null; var d = new Date(String(s).replace(' ', 'T')); return isNaN(d.getTime()) ? null : d; }
    function row(icon, label, value) {
        return '<div class="cd-row"><div class="cd-row__icon"><i class="fa-solid ' + icon + '"></i></div>'
            + '<div class="cd-row__text"><div class="cd-row__label">' + label + '</div>'
            + '<div class="cd-row__value">' + value + '</div></div></div>';
    }

    function renderDetalle(data) {
        var meta  = estadoMeta[data.estado] || ['Solicitada', '#02b1f4', 'fa-circle-info'];
        var color = meta[1];
        if (subEl) subEl.textContent = 'Solicitud #' + data.id;

        var d = parseFecha(data.fecha_cita);
        var heroDate = d
            ? '<div class="cd-hero__date"><span class="cd-hero__day">' + d.getDate() + '</span><span class="cd-hero__month">' + mesesAbbr[d.getMonth()] + '</span></div>'
            : '<div class="cd-hero__date"><span class="cd-hero__day" style="font-size:17px;"><i class="fa-solid fa-clock"></i></span></div>';

        var html = '';
        html += '<div class="cd-hero" style="--cd-color:' + color + ';">' + heroDate
            + '<div class="cd-hero__info"><div class="cd-hero__label">Fecha de la cita</div>'
            + '<div class="cd-hero__value">' + esc(data.fecha_cita_formateada || 'Por confirmar') + '</div>'
            + '<span class="cd-badge"><i class="fa-solid ' + meta[2] + '"></i> ' + meta[0] + '</span></div></div>';

        if (data.estado === 'pendiente_paciente' || data.estado === 'reprogramada') {
            html += '<div class="cd-banner">';
            html += '<div class="cd-banner__title"><i class="fa-solid fa-calendar-day"></i> El profesional propuso una nueva fecha</div>';
            html += '<p class="cd-banner__text"><strong>Nueva fecha sugerida:</strong> ' + val(data.fecha_propuesta_formateada) + '</p>';
            if (data.reprogramacion_motivo) {
                html += '<p class="cd-banner__text"><strong>Motivo:</strong> <em>' + multiline(data.reprogramacion_motivo) + '</em></p>';
            }
            html += '<div class="cd-banner__actions">';
            /* Botones, no enlaces: gestionar_propuesta.php exige POST + token
               CSRF. Como enlace GET, abrir un enlace ajeno movia la fecha de
               la cita. El envio lo hace el manejador delegado de mas abajo. */
            html += '<button type="button" class="btn-secondary" data-prop-accion="rechazar" data-prop-id="' + esc(data.id) + '"><i class="fa-solid fa-xmark"></i> Rechazar</button>';
            html += '<button type="button" class="btn-primary" data-prop-accion="aceptar" data-prop-id="' + esc(data.id) + '"><i class="fa-solid fa-check"></i> Aceptar nueva fecha</button>';
            html += '</div></div>';
        }

        html += '<div class="cd-section"><p class="cd-section__title"><i class="fa-solid fa-clipboard-list"></i> Detalle de la solicitud</p><div class="cd-rows">';
        html += row('fa-wave-square', 'Estudio', val(data.motivo_principal));
        html += row('fa-star', 'Tipo de cita', val(data.tipo_cita));
        html += row('fa-hospital', 'Modalidad', val(data.modalidad));
        html += row('fa-notes-medical', 'Antecedentes médicos y detalles', multiline(data.motivo_consulta));
        html += '</div></div>';

        html += '<div class="cd-section"><p class="cd-section__title"><i class="fa-solid fa-user-doctor"></i> Profesional asignado</p><div class="cd-rows">';
        html += row('fa-user-doctor', 'Nombre', val(data.profesional_nombre));
        html += row('fa-id-badge', 'Rol', val(data.profesional_rol));
        html += '</div></div>';

        html += '<div class="cd-section"><p class="cd-section__title"><i class="fa-solid fa-comment-dots"></i> Notas adicionales</p><div class="cd-rows">';
        html += row('fa-comment', 'Tus notas', multiline(data.notas_paciente));
        html += '</div></div>';

        bodyEl.innerHTML = html;
    }

    window.abrirDetalleCitaPaciente = function (citaId) {
        if (!bodyEl) return;
        bodyEl.innerHTML = '<p class="cd-loading"><i class="fa-solid fa-spinner fa-spin"></i> Cargando detalles…</p>';
        if (subEl) subEl.textContent = '…';
        if (typeof EcoModal !== 'undefined') EcoModal.open(modalId);
        fetch((window.ECO_BASE || '') + 'api/get_cita_details_paciente.php?id=' + encodeURIComponent(citaId))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.error) {
                    bodyEl.innerHTML = '<p style="color:#b91c1c;padding:12px;">' + esc(data.error) + '</p>';
                    return;
                }
                renderDetalle(data);
            })
            .catch(function () {
                bodyEl.innerHTML = '<p style="color:#b91c1c;padding:12px;">No se pudieron cargar los detalles.</p>';
            });
    };

    window.cerrarDetalleCitaPaciente = function () {
        if (typeof EcoModal !== 'undefined') EcoModal.close(modalId);
    };

    /* Cancelar una próxima cita (con confirmación en modal) */
    window.cancelarCitaPaciente = function (id) {
        /* El endpoint exige POST + token CSRF: se envia formulario en vez de
           navegar por href (el enlace GET se podia disparar desde otra web). */
        var a = document.getElementById('cancelar-cita-confirm');
        if (a) a.onclick = function (ev) {
            ev.preventDefault();
            window.ecoPost((window.ECO_BASE || '') + 'api/cancelar_cita_paciente.php', { cita_id: id });
            return false;
        };
        if (typeof EcoModal !== 'undefined') EcoModal.open('eco-modal-cancelar-cita');
    };

    /* Modal de confirmación al registrar la solicitud */
    var okModal = document.getElementById('eco-modal-cita-creada');
    if (okModal && typeof EcoModal !== 'undefined') {
        EcoModal.open('eco-modal-cita-creada');
        if (window.history && history.replaceState) {
            history.replaceState(null, '', window.location.pathname);
        }
    }

    /* Encuesta de satisfacción post-estudio */
    var encCitaId = 0, encPunt = 0;
    var encStars = document.querySelectorAll('#eco-modal-encuesta .enc-stars i');
    function encRender(n) {
        encStars.forEach(function (s) {
            var v = +s.getAttribute('data-v');
            s.className = (v <= n ? 'fa-solid' : 'fa-regular') + ' fa-star';
        });
    }
    encStars.forEach(function (s) {
        s.addEventListener('click', function () { encPunt = +s.getAttribute('data-v'); encRender(encPunt); });
    });
    window.abrirEncuesta = function (id) {
        encCitaId = id; encPunt = 0; encRender(0);
        var c = document.getElementById('enc-comentario'); if (c) c.value = '';
        var e = document.getElementById('enc-error'); if (e) e.textContent = '';
        if (typeof EcoModal !== 'undefined') EcoModal.open('eco-modal-encuesta');
    };
    var encBtn = document.getElementById('enc-submit');
    if (encBtn) encBtn.addEventListener('click', function () {
        var err = document.getElementById('enc-error');
        if (encPunt < 1) { if (err) err.textContent = 'Selecciona una puntuación.'; return; }
        encBtn.disabled = true;
        var fd = new FormData();
        fd.append('cita_id', encCitaId);
        fd.append('puntuacion', encPunt);
        fd.append('comentario', document.getElementById('enc-comentario').value);
        fetch((window.ECO_BASE || '') + 'api/guardar_encuesta.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                encBtn.disabled = false;
                if (d.success) { location.reload(); }
                else if (err) { err.textContent = d.message || 'No se pudo enviar.'; }
            })
            .catch(function () { encBtn.disabled = false; if (err) err.textContent = 'Error de red.'; });
    });
})();
</script>
HTML;

include __DIR__ . '/../layouts/shell.php';
