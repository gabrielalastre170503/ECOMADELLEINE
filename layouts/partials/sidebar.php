<?php
/**
 * Sidebar lateral — TODAS las secciones del sistema agrupadas y filtradas por rol.
 *
 * Variables esperadas:
 *   $active_section  (string)  id de la sección activa
 *   $_SESSION['rol'] (string)  administrador | ecografista | recepcionista | paciente
 *   $_SESSION['nombre_completo'] (string)
 */
$rol = $_SESSION['rol'] ?? '';
$active_section = $active_section ?? '';

/* ---------------------------------------------------------------
 * MENÚ por ROL
 * Para cada item: id (= active_section), label, icon, href, roles
 * Los items se agrupan en bloques (heading). Si una página ya está
 * migrada al nuevo shell, su href apunta al archivo nuevo;
 * si no, apunta a panel.php?vista=slug (deep-link existente).
 * --------------------------------------------------------------- */

$menu = [
    /* ─── PRINCIPAL ─── */
    'Principal' => [
        /* === ADMINISTRADOR === */
        [
            'id' => 'dashboard',
            'label' => 'Panel de Control',
            'icon' => 'fa-solid fa-gauge-high',
            'href' => 'dashboard',
            'roles' => ['administrador'],
        ],
        [
            'id' => 'admin-personal',
            'label' => 'Añadir Personal',
            'icon' => 'fa-solid fa-users-cog',
            'href' => 'personal',
            'roles' => ['administrador'],
        ],
        [
            'id' => 'ver-usuarios',
            'label' => 'Usuarios',
            'icon' => 'fa-solid fa-users',
            'href' => 'usuarios?filtro=aprobados',
            'roles' => ['administrador'],
        ],
        [
            'id' => 'admin-especialidades',
            'label' => 'Especialidades',
            'icon' => 'fa-solid fa-stethoscope',
            'href' => 'especialidades',
            'roles' => ['administrador'],
        ],
        [
            'id' => 'agenda-general',
            'label' => 'Agenda General',
            'icon' => 'fa-solid fa-calendar-week',
            'href' => 'agenda',
            'roles' => ['administrador'],
        ],
        [
            'id' => 'reportes',
            'label' => 'Reportes',
            'icon' => 'fa-solid fa-chart-line',
            'href' => 'reportes',
            // Recepción tiene su propia entrada más abajo ("Estadísticas"), para
            // poder colocarla en su orden sin alterar el menú del administrador.
            'roles' => ['administrador'],
        ],

        /* === ECOGRAFISTA === */
        [
            'id' => 'dashboard',
            'label' => 'Panel de Control',
            'icon' => 'fa-solid fa-gauge-high',
            'href' => 'dashboard',
            'roles' => ['ecografista'],
        ],
        [
            'id' => 'pacientes',
            'label' => 'Pacientes Clínicos',
            'icon' => 'fa-solid fa-users',
            'href' => 'mis-pacientes',
            'roles' => ['ecografista'],
        ],
        [
            'id' => 'proximas-citas',
            'label' => 'Próximas Citas',
            'icon' => 'fa-solid fa-hourglass-half',
            'href' => 'proximas-citas',
            'roles' => ['ecografista'],
        ],
        [
            'id' => 'citas',
            'label' => 'Solicitudes de Cita',
            'icon' => 'fa-solid fa-inbox',
            'href' => 'solicitudes',
            'roles' => ['ecografista'],
        ],
        [
            'id' => 'agenda',
            'label' => 'Mi Agenda',
            'icon' => 'fa-solid fa-calendar-days',
            'href' => 'mi-agenda',
            'roles' => ['ecografista'],
        ],
        [
            'id' => 'disponibilidad',
            'label' => 'Mi Disponibilidad',
            'icon' => 'fa-solid fa-clock',
            'href' => 'disponibilidad',
            'roles' => ['ecografista'],
        ],
        [
            'id' => 'facturacion',
            'label' => 'Facturación',
            'icon' => 'fa-solid fa-cash-register',
            'href' => 'facturacion',
            'roles' => ['ecografista'],
        ],
        [
            'id' => 'reportes',
            'label' => 'Estadísticas',
            'icon' => 'fa-solid fa-chart-pie',
            'href' => 'reportes',
            'roles' => ['ecografista'],
        ],

        /* === RECEPCIONISTA ===
           El orden del menú es el de este array. */
        [
            'id' => 'dashboard',
            'label' => 'Panel de Control',
            'icon' => 'fa-solid fa-gauge-high',
            'href' => 'dashboard',
            'roles' => ['recepcionista'],
        ],
        [
            'id' => 'gestion-pacientes',
            'label' => 'Gestión Pacientes',
            'icon' => 'fa-solid fa-address-book',
            'href' => 'gestion-pacientes',
            'roles' => ['recepcionista'],
        ],
        [
            'id' => 'agenda-general',
            'label' => 'Agenda General',
            'icon' => 'fa-solid fa-calendar-week',
            'href' => 'agenda',
            'roles' => ['recepcionista'],
        ],
        [
            'id' => 'solicitudes-generales',
            'label' => 'Citas Pendientes',
            'icon' => 'fa-solid fa-inbox',
            'href' => 'citas-pendientes',
            'roles' => ['recepcionista'],
        ],
        [
            'id' => 'facturacion',
            'label' => 'Facturación',
            'icon' => 'fa-solid fa-cash-register',
            'href' => 'facturacion',
            'roles' => ['recepcionista'],
        ],
        [
            'id' => 'control-precios',
            'label' => 'Control de Precios',
            'icon' => 'fa-solid fa-tags',
            'href' => 'control-precios',
            'roles' => ['recepcionista'],
        ],
        [
            // El id debe seguir siendo 'reportes': es el $active_section que fija
            // common/reportes.php para marcar la entrada activa.
            'id' => 'reportes',
            'label' => 'Estadísticas',
            'icon' => 'fa-solid fa-chart-pie',
            'href' => 'reportes',
            'roles' => ['recepcionista'],
        ],

        /* === PACIENTE === */
        [
            'id' => 'paciente-dashboard',
            'label' => 'Panel de Control',
            'icon' => 'fa-solid fa-house',
            'href' => 'dashboard',
            'roles' => ['paciente'],
        ],
        [
            'id' => 'miscitas',
            'label' => 'Mis Citas',
            'icon' => 'fa-solid fa-calendar-check',
            'href' => 'mis-citas',
            'roles' => ['paciente'],
        ],
        [
            'id' => 'solicitar',
            'label' => 'Solicitar Nueva Cita',
            'icon' => 'fa-solid fa-file-circle-plus',
            'href' => 'solicitar-cita',
            'roles' => ['paciente'],
        ],
    ],

    /* ─── HISTORIAL / REGISTROS ─── */
    'Registros' => [
        [
            'id' => 'historial-citas',
            'label' => 'Historial de Citas',
            'icon' => 'fa-solid fa-clipboard-list',
            'href' => 'historial-citas',
            'roles' => ['ecografista'],
        ],
        [
            'id' => 'historial-citas-general',
            'label' => 'Historial de Citas',
            'icon' => 'fa-solid fa-clipboard-list',
            'href' => 'historial-recepcion',
            'roles' => ['recepcionista'],
        ],
        [
            'id' => 'control-precios',
            'label' => 'Control de Precios',
            'icon' => 'fa-solid fa-tags',
            'href' => 'control-precios',
            'roles' => ['ecografista'],
        ],
        [
            'id' => 'directorio',
            'label' => 'Directorio Clínico',
            'icon' => 'fa-solid fa-user-doctor',
            'href' => 'directorio',
            'roles' => ['recepcionista'],
        ],
        [
            'id' => 'admin-documentos',
            'label' => 'Repositorio Documentos',
            'icon' => 'fa-solid fa-folder-open',
            'href' => 'repositorio',
            'roles' => ['administrador'],
        ],
    ],

    /* ─── INFORMACIÓN (para pacientes) ─── */
    'Información' => [
        [
            'id' => 'mis-informes',
            'label' => 'Mis Informes',
            'icon' => 'fa-solid fa-file-medical',
            'href' => 'mis-informes',
            'roles' => ['paciente'],
        ],
        [
            'id' => 'mis-pagos',
            'label' => 'Mi Historial de Pagos',
            'icon' => 'fa-solid fa-receipt',
            'href' => 'mis-pagos',
            'roles' => ['paciente'],
        ],
        [
            'id' => 'psicologos',
            'label' => 'Ecografistas Activos',
            'icon' => 'fa-solid fa-user-doctor',
            'href' => 'ecografistas',
            'roles' => ['paciente'],
        ],
        [
            'id' => 'preparacion',
            'label' => 'Preparación de Estudios',
            'icon' => 'fa-solid fa-clipboard-list',
            'href' => 'preparacion',
            'roles' => ['paciente'],
        ],
        [
            'id' => 'precios',
            'label' => 'Precios de Ecografías',
            'icon' => 'fa-solid fa-tag',
            'href' => 'precios',
            'roles' => ['paciente'],
        ],
        [
            'id' => 'faq',
            'label' => 'Preguntas Frecuentes',
            'icon' => 'fa-solid fa-circle-question',
            'href' => 'faq',
            'roles' => ['paciente'],
        ],
        [
            'id' => 'ayuda',
            'label' => 'Centro de Ayuda',
            'icon' => 'fa-solid fa-life-ring',
            'href' => 'ayuda',
            'roles' => ['paciente'],
        ],
    ],

    /* ─── CONFIGURACIÓN / SISTEMA ─── */
    'Sistema' => [
        [
            'id' => 'admin-contenido',
            'label' => 'Contenido Web',
            'icon' => 'fa-solid fa-file-pen',
            'href' => 'contenido',
            'roles' => ['administrador'],
        ],
        [
            'id' => 'auditoria',
            'label' => 'Bitácora',
            'icon' => 'fa-solid fa-clipboard-list',
            'href' => 'auditoria',
            'roles' => ['administrador'],
        ],
        [
            'id' => 'notas-rapidas',
            'label' => 'Notas Rápidas',
            'icon' => 'fa-solid fa-note-sticky',
            'href' => 'notas-rapidas',
            'roles' => ['administrador', 'recepcionista'],
        ],
        [
            'id' => 'perfil',
            'label' => 'Mi Perfil',
            'icon' => 'fa-solid fa-user-gear',
            'href' => 'perfil',
            'roles' => ['administrador', 'ecografista', 'recepcionista', 'paciente'],
        ],
    ],
];

/* Render helper */
function eco_render_sidebar_item(array $item, string $active_section, string $rol): string
{
    if (!in_array($rol, $item['roles'], true)) return '';
    $is_active = ($item['id'] === $active_section) ? ' is-active' : '';
    return sprintf(
        '<a href="%s" class="sidebar-link%s" data-tooltip="%s">'
        . '<i class="%s"></i><span>%s</span></a>',
        htmlspecialchars(eco_url($item['href'])),
        $is_active,
        htmlspecialchars($item['label']),
        htmlspecialchars($item['icon']),
        htmlspecialchars($item['label'])
    );
}

$nombre_usuario = $_SESSION['nombre_completo'] ?? 'Usuario';
$iniciales = '';
foreach (explode(' ', trim($nombre_usuario)) as $p) {
    if ($p !== '' && strlen($iniciales) < 2) $iniciales .= strtoupper($p[0]);
}
if ($iniciales === '') $iniciales = 'U';

/* El menú plegado se marca YA en el servidor. Antes lo hacía el JS al terminar
   de cargar el DOM, así que la página se pintaba una vez con el menú entero y
   acto seguido se encogía: ese es el parpadeo al cambiar de sección. */
$eco_menu_plegado = (($_COOKIE['eco_sidebar'] ?? '') === 'collapsed');
?>

<aside class="app-sidebar<?= $eco_menu_plegado ? ' is-collapsed' : '' ?>">

    <a href="<?= htmlspecialchars(eco_url('dashboard')) ?>" class="sidebar-brand">
        <div class="sidebar-brand-logo">
            <i class="fa-solid fa-wave-square"></i>
        </div>
        <div class="sidebar-brand-text">
            <strong>EcoMadelleine</strong>
            <small>Clínica de Ecografías</small>
        </div>
    </a>

    <nav class="sidebar-nav">
        <?php foreach ($menu as $grupo => $items):
            $items_visibles = array_filter($items, fn($i) => in_array($rol, $i['roles'], true));
            if (empty($items_visibles)) continue;
        ?>
            <div class="sidebar-nav-section"><?= htmlspecialchars($grupo) ?></div>
            <?php foreach ($items_visibles as $item) {
                echo eco_render_sidebar_item($item, $active_section, $rol);
            } ?>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-user-avatar"><?= htmlspecialchars($iniciales) ?></div>
        <div class="sidebar-user-info">
            <strong><?= htmlspecialchars($nombre_usuario) ?></strong>
            <small><?= htmlspecialchars($rol ?: 'usuario') ?></small>
        </div>
        <a href="<?= htmlspecialchars(eco_url('logout')) ?>" class="sidebar-user-logout" title="Cerrar sesión">
            <i class="fa-solid fa-right-from-bracket"></i>
        </a>
    </div>

</aside>
<div class="sidebar-backdrop"></div>
