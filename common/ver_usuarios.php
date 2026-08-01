<?php
session_start();
include __DIR__ . '/../core/conexion.php';

if (!isset($_SESSION['usuario_id']) || ($_SESSION['rol'] ?? '') !== 'administrador') {
    header('Location: ' . eco_url('login'));
    exit;
}

$filtro = isset($_GET['filtro']) ? trim((string)$_GET['filtro']) : 'aprobados';
if (!in_array($filtro, ['pendientes', 'personal', 'doctores', 'aprobados', 'pacientes'], true)) {
    $filtro = 'aprobados';
}

$filtrosMeta = [
    'aprobados'   => ['Todos los usuarios aprobados', 'Cuentas activas e inhabilitadas del sistema', 'fa-solid fa-users', 'Usuarios'],
    'pendientes'  => ['Usuarios pendientes de aprobación', 'Solicitudes de registro por revisar', 'fa-solid fa-user-clock', 'Pendientes'],
    'personal'    => ['Personal activo', 'Ecografistas y recepcionistas', 'fa-solid fa-user-tie', 'Personal'],
    'doctores'    => ['Ecografistas activos', 'Profesionales con cuenta en la clínica', 'fa-solid fa-user-doctor', 'Ecografistas'],
    'pacientes'   => ['Pacientes activos', 'Expedientes de pacientes registrados', 'fa-solid fa-hospital-user', 'Pacientes'],
];

[$page_title, $page_subtitle] = $filtrosMeta[$filtro] ?? $filtrosMeta['aprobados'];

$active_section = 'ver-usuarios';
$body_class = 'ver-usuarios-page';
$page_head_extra = '<link rel="stylesheet" href="assets/css/usuarios/ver-usuarios.css">';

$page_header_actions = '
    <a href="' . eco_url('personal') . '" class="btn-secondary"><i class="fa-solid fa-user-plus"></i> Añadir personal</a>
    <a href="' . eco_url('dashboard') . '" class="btn-secondary"><i class="fa-solid fa-gauge-high"></i> Panel</a>';

ob_start();
?>

<nav class="vu-filter-nav" aria-label="Filtrar listado de usuarios">
    <?php foreach ($filtrosMeta as $key => $meta): ?>
        <a href="<?= eco_url('usuarios') ?>?filtro=<?= urlencode($key) ?>"
           class="vu-filter-chip<?= $filtro === $key ? ' is-active' : '' ?>">
            <i class="<?= htmlspecialchars($meta[2]) ?>" aria-hidden="true"></i>
            <?= htmlspecialchars($meta[3]) ?>
        </a>
    <?php endforeach; ?>
</nav>

<div class="vu-controls-grid">
    <div class="card">
        <div class="vu-search-wrap">
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
            <input type="search"
                   id="buscador-usuarios"
                   class="vu-search-input"
                   placeholder="Buscar por nombre o cédula…"
                   autocomplete="off"
                   aria-label="Buscar usuarios">
        </div>
    </div>
    <div class="card vu-total-card">
        <div class="vu-total-card__icon" aria-hidden="true">
            <i class="<?= htmlspecialchars($filtrosMeta[$filtro][2]) ?>"></i>
        </div>
        <div>
            <div class="vu-total-card__label">Resultados</div>
            <div class="vu-total-card__value"><span id="vu-users-count">—</span> usuarios</div>
        </div>
    </div>
</div>

<div class="card" id="vu-users-list-card">
    <div id="tabla-usuarios-container" class="vu-users-wrap data-table">
        <p class="vu-users-empty"><i class="fa-solid fa-spinner fa-spin"></i> Cargando usuarios…</p>
    </div>
</div>

<?php
$page_content = ob_get_clean();

ob_start();
?>
<?php
/* La contraseña recién generada llega por la sesión (antes venía en la URL, que
   queda en el historial y en los logs del servidor). Se consume una sola vez:
   al recargar la página ya no vuelve a mostrarse. */
$vu_temp_pass = (string)($_SESSION['vu_temp_pass'] ?? '');
unset($_SESSION['vu_temp_pass']);
?>
<div id="eco-modal-vu-temp-pass" class="eco-modal" aria-hidden="true" role="dialog" aria-labelledby="vu-temp-pass-title"
     data-temp-pass="<?= htmlspecialchars($vu_temp_pass, ENT_QUOTES, 'UTF-8') ?>">
    <div class="eco-modal__dialog">
        <div class="eco-modal__main" style="padding:28px 24px 24px;text-align:center;">
            <button type="button" class="eco-modal__close" data-eco-modal-close aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
            <div class="vu-temp-pass-icon" aria-hidden="true"><i class="fa-solid fa-circle-check"></i></div>
            <h4 class="eco-modal__title" id="vu-temp-pass-title" style="margin-bottom:8px;">Contraseña restablecida</h4>
            <p class="eco-modal__body-text" style="margin:0 0 4px;">La nueva contraseña temporal es:</p>
            <div class="vu-temp-pass-box">
                <code id="vu-temp-pass-display">—</code>
            </div>
            <p class="eco-modal__body-text" style="font-size:12.5px;color:var(--text-muted);margin:0 0 18px;">
                Anótala y entrégala al usuario de forma segura.
            </p>
            <button type="button" class="btn-primary" data-eco-modal-close>Entendido</button>
        </div>
    </div>
</div>
<?php
$modal_temp_pass = ob_get_clean();

$page_scripts_extra = $modal_temp_pass
    . '<script src="assets/js/panel/eco-table-sort.js"></script>'
    . '<script src="assets/js/usuarios/ver-usuarios.js"></script>'
    . '<script>window.VU_FILTRO = ' . json_encode($filtro, JSON_UNESCAPED_UNICODE) . ';</script>';

include __DIR__ . '/../layouts/shell.php';
