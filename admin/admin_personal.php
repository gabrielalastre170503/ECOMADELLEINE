<?php
session_start();
include __DIR__ . '/../core/conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . eco_url('login'));
    exit;
}
if (($_SESSION['rol'] ?? '') !== 'administrador') {
    header('Location: ' . eco_url('dashboard'));
    exit;
}

function eco_staff_initials(string $name): string
{
    $init = '';
    foreach (preg_split('/\s+/u', trim($name)) as $part) {
        if ($part !== '' && mb_strlen($init) < 2) {
            $init .= mb_strtoupper(mb_substr($part, 0, 1));
        }
    }
    return $init !== '' ? $init : '?';
}

function eco_staff_role_meta(string $rol): array
{
    if ($rol === 'recepcionista') {
        return [
            'label'       => 'Recepcionista',
            'badge_class' => 'staff-profile-card__role--rx',
            'avatar_class'=> 'staff-profile-card__avatar--rx',
        ];
    }
    return [
        'label'       => 'Ecografista',
        'badge_class' => 'staff-profile-card__role--eco',
        'avatar_class'=> 'staff-profile-card__avatar--eco',
    ];
}

function eco_render_staff_grid(mysqli $conex, string $title, string $icon, string $query, string $rol, int $total = 0): void
{
    $meta = eco_staff_role_meta($rol);
    $r = $conex->query($query);
    ?>
    <div class="card staff-section">
        <button type="button" class="card-header staff-section-header" data-staff-lista="<?= htmlspecialchars($rol) ?>" data-staff-lista-count="<?= (int)$total ?>" data-staff-lista-title="<?= htmlspecialchars($title) ?>" aria-label="Abrir listado: <?= htmlspecialchars($title) ?>">
            <h3><i class="<?= htmlspecialchars($icon) ?>" style="margin-right:8px;color:var(--accent);"></i> <?= htmlspecialchars($title) ?></h3>
            <span class="staff-section-header__cta"><i class="fa-solid fa-expand"></i> Ver listado</span>
        </button>
        <?php if (!$r || $r->num_rows === 0): ?>
            <p class="staff-section__empty">No hay registros.</p>
        <?php else: ?>
            <div class="staff-grid">
                <?php while ($row = $r->fetch_assoc()):
                    $id = (int)$row['id'];
                    $nombre = (string)($row['nombre_completo'] ?? '');
                    $correo = (string)($row['correo'] ?? '');
                    $iniciales = eco_staff_initials($nombre);
                ?>
                    <article class="staff-profile-card">
                        <button type="button"
                                class="staff-profile-card__action"
                                data-staff-perfil-id="<?= $id ?>"
                                title="Ver perfil"
                                aria-label="Ver perfil de <?= htmlspecialchars($nombre) ?>">
                            <i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i>
                        </button>
                        <button type="button" class="staff-profile-card__main" data-staff-perfil-id="<?= $id ?>">
                            <div class="staff-profile-card__avatar <?= htmlspecialchars($meta['avatar_class']) ?>">
                                <?= htmlspecialchars($iniciales) ?>
                            </div>
                            <div class="staff-profile-card__body">
                                <h4 class="staff-profile-card__name"><?= htmlspecialchars($nombre) ?></h4>
                                <span class="staff-profile-card__role <?= htmlspecialchars($meta['badge_class']) ?>">
                                    <?= htmlspecialchars($meta['label']) ?>
                                </span>
                                <p class="staff-profile-card__email">
                                    <i class="fa-solid fa-envelope" aria-hidden="true"></i>
                                    <span><?= htmlspecialchars($correo) ?></span>
                                </p>
                            </div>
                        </button>
                    </article>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

$total_ecografistas = 0;
$total_recepcionistas = 0;
if ($r = $conex->query("SELECT COUNT(id) AS c FROM usuarios WHERE rol = 'ecografista' AND estado = 'aprobado'")) {
    $total_ecografistas = (int)($r->fetch_assoc()['c'] ?? 0);
    $r->free();
}
if ($r = $conex->query("SELECT COUNT(id) AS c FROM usuarios WHERE rol = 'recepcionista' AND estado = 'aprobado'")) {
    $total_recepcionistas = (int)($r->fetch_assoc()['c'] ?? 0);
    $r->free();
}

$page_title    = 'Añadir personal';
$page_subtitle = 'Crear cuentas y consultar el equipo de la clínica';
$active_section = 'admin-personal';
$body_class    = 'staff-personal-page';
// ?v=filemtime como en shell.php: sin esto el navegador sirve el CSS cacheado
// y los cambios de estilo no se ven hasta forzar una recarga dura.
$css_v = static fn(string $rel): string => $rel . '?v=' . (@filemtime(__DIR__ . '/../' . $rel) ?: '1');

$page_head_extra = '<link rel="stylesheet" href="' . $css_v('assets/css/admin/admin-personal.css') . '">'
    . '<link rel="stylesheet" href="' . $css_v('assets/css/admin/admin-personal-modals.css') . '">'
    . '<link rel="stylesheet" href="' . $css_v('assets/css/core/estilos.css') . '">'
    . '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">';

$page_header_actions = '<a href="' . eco_url('usuarios') . '" class="btn-secondary"><i class="fa-solid fa-users"></i> Ver todos los usuarios</a>';

ob_start();

$staff_registro_ok = isset($_GET['registro']) && $_GET['registro'] === 'ok';
$staff_registro_nombre = isset($_GET['nombre']) ? trim((string)$_GET['nombre']) : '';
?>

<?php if ($staff_registro_ok): ?>
    <div class="staff-feedback-banner" role="status">
        <i class="fa-solid fa-circle-check" style="color:var(--success);margin-right:6px;"></i>
        <?php if ($staff_registro_nombre !== ''): ?>
            <strong><?= htmlspecialchars($staff_registro_nombre) ?></strong> registrado correctamente.
        <?php else: ?>
            Registro completado correctamente.
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="staff-register-grid">
    <a href="#" class="card staff-register-card" data-staff-modal="ecografista">
        <div class="staff-register-card__icon staff-register-card__icon--eco"><i class="fa-solid fa-user-doctor"></i></div>
        <strong class="staff-register-card__title">Registrar ecografista</strong>
        <p class="staff-register-card__desc">Alta de profesional para estudios ecográficos.</p>
        <span class="staff-register-card__cta">Crear perfil <i class="fa-solid fa-arrow-right"></i></span>
    </a>

    <a href="#" class="card staff-register-card" data-staff-modal="recepcionista">
        <div class="staff-register-card__icon staff-register-card__icon--rx"><i class="fa-solid fa-clipboard-user"></i></div>
        <strong class="staff-register-card__title">Registrar recepcionista</strong>
        <p class="staff-register-card__desc">Personal de recepción y citas.</p>
        <span class="staff-register-card__cta">Crear perfil <i class="fa-solid fa-arrow-right"></i></span>
    </a>

    <a href="#" class="card staff-register-card" data-staff-modal="paciente">
        <div class="staff-register-card__icon staff-register-card__icon--pat"><i class="fa-solid fa-user-plus"></i></div>
        <strong class="staff-register-card__title">Registrar paciente</strong>
        <p class="staff-register-card__desc">Nuevo expediente de paciente.</p>
        <span class="staff-register-card__cta">Crear cuenta <i class="fa-solid fa-arrow-right"></i></span>
    </a>

    <div class="card staff-register-card staff-register-card--metric" role="status" aria-label="Ecografistas activos: <?= (int)$total_ecografistas ?>">
        <div class="staff-register-card__icon staff-register-card__icon--eco" aria-hidden="true"><i class="fa-solid fa-user-doctor"></i></div>
        <strong class="staff-register-card__title">Ecografistas activos</strong>
        <p class="staff-register-card__desc">Profesionales con cuenta aprobada en la clínica.</p>
        <span class="staff-register-card__metric-value"><?= number_format($total_ecografistas) ?></span>
    </div>
</div>

<?php
eco_render_staff_grid(
    $conex,
    'Ecografistas activos',
    'fa-solid fa-user-doctor',
    "SELECT id, nombre_completo, correo FROM usuarios WHERE rol = 'ecografista' AND estado = 'aprobado' ORDER BY nombre_completo ASC",
    'ecografista',
    $total_ecografistas
);
eco_render_staff_grid(
    $conex,
    'Recepcionistas activas',
    'fa-solid fa-user-tie',
    "SELECT id, nombre_completo, correo FROM usuarios WHERE rol = 'recepcionista' AND estado = 'aprobado' ORDER BY nombre_completo ASC",
    'recepcionista',
    $total_recepcionistas
);
?>

<?php
$page_content = ob_get_clean();

ob_start();
include __DIR__ . '/../layouts/partials/modal_admin_personal.php';
$staff_modals_html = ob_get_clean();

ob_start();
include __DIR__ . '/../layouts/partials/modal_admin_personal_lista.php';
$staff_modals_html .= ob_get_clean();

ob_start();
include __DIR__ . '/../layouts/partials/modal_admin_personal_perfil.php';
$staff_modals_html .= ob_get_clean();

$page_scripts_extra = $staff_modals_html
    . '<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>'
    . '<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>'
    . '<script src="assets/js/admin/admin-personal-modals.js"></script>';

include __DIR__ . '/../layouts/shell.php';
