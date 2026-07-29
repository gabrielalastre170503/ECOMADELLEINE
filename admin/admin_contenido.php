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

$page_title    = 'Contenido web';
$page_subtitle = 'Editar textos públicos y preguntas frecuentes';
$active_section = 'admin-contenido';
// ?v=filemtime como en shell.php: sin esto el navegador sirve el CSS cacheado.
$page_head_extra = '<link rel="stylesheet" href="assets/css/admin/admin-contenido.css?v='
    . (@filemtime(__DIR__ . '/../assets/css/admin/admin-contenido.css') ?: '1') . '">';

ob_start();
?>

<div class="admin-contenido-grid">

    <a href="<?= eco_url('gestionar-faq') ?>" class="card admin-contenido-card cw-tone--faq">
        <div class="admin-contenido-card__icon"><i class="fa-solid fa-circle-question"></i></div>
        <strong class="admin-contenido-card__title">Preguntas frecuentes</strong>
        <p class="admin-contenido-card__desc">Edita la sección FAQ del sitio público.</p>
        <span class="admin-contenido-card__cta">Gestionar <i class="fa-solid fa-arrow-right"></i></span>
    </a>

    <a href="<?= eco_url('gestionar-textos') ?>" class="card admin-contenido-card cw-tone--textos">
        <div class="admin-contenido-card__icon"><i class="fa-solid fa-file-lines"></i></div>
        <strong class="admin-contenido-card__title">Textos «Nosotros»</strong>
        <p class="admin-contenido-card__desc">Misión, visión y textos institucionales.</p>
        <span class="admin-contenido-card__cta">Gestionar <i class="fa-solid fa-arrow-right"></i></span>
    </a>

    <a href="index.php" target="_blank" rel="noopener" class="card admin-contenido-card cw-tone--preview">
        <div class="admin-contenido-card__icon"><i class="fa-solid fa-globe"></i></div>
        <strong class="admin-contenido-card__title">Vista previa del sitio</strong>
        <p class="admin-contenido-card__desc">Abre la página de inicio en una nueva pestaña.</p>
        <span class="admin-contenido-card__cta">Abrir <i class="fa-solid fa-arrow-up-right-from-square"></i></span>
    </a>

    <a href="<?= eco_url('gestionar-estudios') ?>" class="card admin-contenido-card cw-tone--estudios">
        <div class="admin-contenido-card__icon"><i class="fa-solid fa-wave-square"></i></div>
        <strong class="admin-contenido-card__title">Estudios ecográficos</strong>
        <p class="admin-contenido-card__desc">Añade y administra los tipos de estudio del catálogo.</p>
        <span class="admin-contenido-card__cta">Gestionar <i class="fa-solid fa-arrow-right"></i></span>
    </a>

</div>

<div class="card admin-contenido-quick">
    <div class="card-header">
        <h3><i class="fa-solid fa-link admin-contenido-quick__glyph"></i> Accesos rápidos</h3>
    </div>
    <ul class="quick-link-list">
        <li class="quick-link cw-tone--textos">
            <span class="quick-link__mark" aria-hidden="true"></span>
            <span class="quick-link__name">Sección Nosotros <span class="quick-link__meta">(público)</span></span>
            <span class="quick-link__actions">
                <a href="index.php#nosotros" target="_blank" rel="noopener" class="quick-link__btn" aria-label="Ver Sección Nosotros en el sitio público">Ver <i class="fa-solid fa-arrow-up-right-from-square"></i></a>
                <a href="<?= eco_url('gestionar-textos') ?>" class="quick-link__btn quick-link__btn--edit" aria-label="Editar Sección Nosotros">Editar</a>
            </span>
        </li>
        <li class="quick-link cw-tone--estudios">
            <span class="quick-link__mark" aria-hidden="true"></span>
            <span class="quick-link__name">Estudios ecográficos <span class="quick-link__meta">(listado en inicio)</span></span>
            <span class="quick-link__actions">
                <a href="index.php#servicios" target="_blank" rel="noopener" class="quick-link__btn" aria-label="Ver Estudios ecográficos en el sitio público">Ver <i class="fa-solid fa-arrow-up-right-from-square"></i></a>
                <a href="<?= eco_url('gestionar-estudios') ?>" class="quick-link__btn quick-link__btn--edit" aria-label="Editar Estudios ecográficos">Editar</a>
            </span>
        </li>
        <li class="quick-link cw-tone--faq">
            <span class="quick-link__mark" aria-hidden="true"></span>
            <span class="quick-link__name">FAQ</span>
            <span class="quick-link__actions">
                <a href="index.php#faq" target="_blank" rel="noopener" class="quick-link__btn" aria-label="Ver FAQ en el sitio público">Ver <i class="fa-solid fa-arrow-up-right-from-square"></i></a>
                <a href="<?= eco_url('gestionar-faq') ?>" class="quick-link__btn quick-link__btn--edit" aria-label="Editar FAQ">Editar</a>
            </span>
        </li>
    </ul>
</div>

<?php
$page_content = ob_get_clean();
include __DIR__ . '/../layouts/shell.php';
