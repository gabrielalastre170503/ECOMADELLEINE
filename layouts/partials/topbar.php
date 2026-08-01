<?php
/**
 * Topbar — barra superior fija.
 *
 * Variables esperadas:
 *   $page_title    (string) Título de página para breadcrumb
 *   $active_section(string) id de sección (Dashboard, Citas, etc.)
 *   $_SESSION['rol']             string
 *   $_SESSION['nombre_completo'] string
 *
 * Variables opcionales:
 *   $topbar_quick_action  (array|null) ['label'=>'', 'icon'=>'', 'href'=>'', 'onclick'=>'']
 *   $topbar_notifications (int)        Número de notificaciones (badge en la campana)
 */
$page_title           = $page_title           ?? 'Dashboard';
$browser_title        = $browser_title        ?? '';
$topbar_title         = ($page_title !== '' && $page_title !== null) ? $page_title : ($browser_title !== '' ? $browser_title : 'Dashboard');
$active_label         = ucfirst($active_section ?? 'Inicio');
$topbar_quick_action  = $topbar_quick_action  ?? null;
$topbar_notifications = (int)($topbar_notifications ?? 0);
$rol = $_SESSION['rol'] ?? 'usuario';
$nombre_usuario = $_SESSION['nombre_completo'] ?? 'Usuario';
$iniciales = '';
foreach (explode(' ', trim($nombre_usuario)) as $p) {
    if ($p !== '' && strlen($iniciales) < 2) $iniciales .= strtoupper($p[0]);
}
if ($iniciales === '') $iniciales = 'U';
?>
<header class="app-topbar">

    <!-- Toggle sidebar -->
    <button id="btn-toggle-sidebar" class="topbar-toggle" title="Colapsar/expandir menú">
        <i class="fa-solid fa-bars"></i>
    </button>

    <!-- Breadcrumb -->
    <nav class="topbar-breadcrumb">
        <i class="fa-solid fa-house"></i>
        <span><?= htmlspecialchars(ucfirst($rol)) ?></span>
        <i class="fa-solid fa-chevron-right"></i>
        <strong><?= htmlspecialchars($topbar_title) ?></strong>
    </nav>

    <!-- Buscador -->
    <div class="topbar-search" id="eco-buscador">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="search" id="eco-buscador-input" placeholder="Buscar pacientes, citas, informes..."
               aria-label="Buscar" autocomplete="off" role="combobox"
               aria-expanded="false" aria-controls="eco-buscador-panel" aria-autocomplete="list">
        <div class="topbar-search__panel" id="eco-buscador-panel" role="listbox" hidden></div>
    </div>

    <!-- Reloj -->
    <div class="topbar-clock">
        <i class="fa-regular fa-clock"></i>
        <strong id="topbar-clock-time">--:--</strong>
        <span style="opacity:.6;">|</span>
        <span id="topbar-clock-date">—</span>
    </div>

    <!-- Acciones -->
    <div class="topbar-actions">

        <?php if ($topbar_quick_action): ?>
            <button class="topbar-quick-action"
                <?= !empty($topbar_quick_action['onclick'])
                    ? 'onclick="' . htmlspecialchars($topbar_quick_action['onclick']) . '"'
                    : '' ?>>
                <i class="<?= htmlspecialchars($topbar_quick_action['icon'] ?? 'fa-solid fa-plus') ?>"></i>
                <span><?= htmlspecialchars($topbar_quick_action['label']) ?></span>
            </button>
        <?php endif; ?>

        <!-- Notificaciones -->
        <div class="topbar-notif" id="eco-notif">
            <button class="topbar-btn-icon" id="eco-notif-btn" title="Notificaciones" aria-haspopup="true" aria-expanded="false">
                <i class="fa-regular fa-bell"></i>
                <span class="badge-dot" id="eco-notif-badge" hidden>0</span>
            </button>
            <div class="topbar-notif-panel" id="eco-notif-panel" hidden>
                <div class="topbar-notif-head">
                    <strong><i class="fa-regular fa-bell"></i> Notificaciones</strong>
                    <button type="button" id="eco-notif-readall" class="topbar-notif-readall">Marcar todas</button>
                </div>
                <div class="topbar-notif-list" id="eco-notif-list">
                    <div class="topbar-notif-loading"><i class="fa-solid fa-spinner fa-spin"></i></div>
                </div>
            </div>
        </div>

        <!-- Toggle de tema -->
        <button id="btn-toggle-theme" class="topbar-btn-icon" title="Cambiar tema">
            <i class="fa-solid fa-moon"></i>
        </button>

        <!-- Usuario -->
        <div class="topbar-user">
            <div class="topbar-user-avatar"><?= htmlspecialchars($iniciales) ?></div>
            <div class="topbar-user-info">
                <strong><?= htmlspecialchars($nombre_usuario) ?></strong>
                <small><?= htmlspecialchars($rol) ?></small>
            </div>
        </div>

    </div>

</header>
