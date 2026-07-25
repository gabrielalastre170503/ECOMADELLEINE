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

    <a href="<?= eco_url('gestionar-faq') ?>" class="card admin-contenido-card">
        <div class="admin-contenido-card__icon admin-contenido-card__icon--faq"><i class="fa-solid fa-circle-question"></i></div>
        <strong class="admin-contenido-card__title">Preguntas frecuentes</strong>
        <p class="admin-contenido-card__desc">Edita la sección FAQ del sitio público.</p>
        <span class="admin-contenido-card__cta">Gestionar <i class="fa-solid fa-arrow-right"></i></span>
    </a>

    <a href="<?= eco_url('gestionar-textos') ?>" class="card admin-contenido-card">
        <div class="admin-contenido-card__icon admin-contenido-card__icon--textos"><i class="fa-solid fa-file-lines"></i></div>
        <strong class="admin-contenido-card__title">Textos «Nosotros»</strong>
        <p class="admin-contenido-card__desc">Misión, visión y textos institucionales.</p>
        <span class="admin-contenido-card__cta">Gestionar <i class="fa-solid fa-arrow-right"></i></span>
    </a>

    <a href="index.php" target="_blank" rel="noopener" class="card admin-contenido-card">
        <div class="admin-contenido-card__icon admin-contenido-card__icon--preview"><i class="fa-solid fa-globe"></i></div>
        <strong class="admin-contenido-card__title">Vista previa del sitio</strong>
        <p class="admin-contenido-card__desc">Abre la página de inicio en una nueva pestaña.</p>
        <span class="admin-contenido-card__cta">Abrir <i class="fa-solid fa-arrow-up-right-from-square"></i></span>
    </a>

    <a href="<?= eco_url('gestionar-estudios') ?>" class="card admin-contenido-card">
        <div class="admin-contenido-card__icon admin-contenido-card__icon--estudios"><i class="fa-solid fa-wave-square"></i></div>
        <strong class="admin-contenido-card__title">Estudios ecográficos</strong>
        <p class="admin-contenido-card__desc">Añade y administra los tipos de estudio del catálogo.</p>
        <span class="admin-contenido-card__cta">Gestionar <i class="fa-solid fa-arrow-right"></i></span>
    </a>

</div>

<div class="card" style="margin-top:22px;">
    <div class="card-header">
        <h3><i class="fa-solid fa-link" style="margin-right:7px;color:var(--accent);"></i> Accesos rápidos</h3>
    </div>
    <div class="data-table" style="border:none;">
        <table>
            <tbody>
                <tr>
                    <td><strong>Sección Nosotros</strong> (público)</td>
                    <td style="text-align:right;white-space:nowrap;">
                        <a href="index.php#nosotros" target="_blank" class="btn-secondary" style="font-size:12px;">Ver</a>
                        <a href="<?= eco_url('gestionar-textos') ?>" class="btn-primary" style="font-size:12px;">Editar</a>
                    </td>
                </tr>
                <tr>
                    <td><strong>Estudios ecográficos</strong> (listado en inicio)</td>
                    <td style="text-align:right;white-space:nowrap;">
                        <a href="index.php#servicios" target="_blank" class="btn-secondary" style="font-size:12px;">Ver</a>
                        <a href="<?= eco_url('gestionar-estudios') ?>" class="btn-primary" style="font-size:12px;">Editar</a>
                    </td>
                </tr>
                <tr>
                    <td><strong>FAQ</strong></td>
                    <td style="text-align:right;white-space:nowrap;">
                        <a href="index.php#faq" target="_blank" class="btn-secondary" style="font-size:12px;">Ver</a>
                        <a href="<?= eco_url('gestionar-faq') ?>" class="btn-primary" style="font-size:12px;">Editar</a>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<?php
$page_content = ob_get_clean();
include __DIR__ . '/../layouts/shell.php';
