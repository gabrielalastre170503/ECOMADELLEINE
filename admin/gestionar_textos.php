<?php
session_start();
include __DIR__ . '/../core/conexion.php';

if (!isset($_SESSION['usuario_id']) || ($_SESSION['rol'] ?? '') !== 'administrador') {
    header('Location: ' . eco_url('login'));
    exit;
}

$contenido = ['mision' => '', 'vision' => '', 'valores' => ''];
if ($r = $conex->query("SELECT clave, valor FROM contenido_web WHERE clave IN ('mision', 'vision', 'valores')")) {
    while ($fila = $r->fetch_assoc()) {
        $contenido[$fila['clave']] = (string)$fila['valor'];
    }
    $r->free();
}

$status = isset($_GET['status']) ? (string)$_GET['status'] : '';

$charsMision  = mb_strlen($contenido['mision']);
$charsVision  = mb_strlen($contenido['vision']);
$charsValores = mb_strlen($contenido['valores']);

$page_title    = 'Textos «Nosotros»';
$page_subtitle = 'Edita misión, visión y valores con vista previa en tiempo real';
$active_section = 'admin-contenido';
$body_class    = 'cw-gestion-page cw-textos-page';
$page_head_extra = '<link rel="stylesheet" href="assets/css/admin/admin-gestion-contenido.css">'
    . '<link rel="stylesheet" href="assets/css/contenido/gestionar-textos.css">'
    . '<link rel="stylesheet" href="assets/css/core/estilos.css">';

$page_header_actions = '
    <a href="' . eco_url('contenido') . '" class="btn-secondary"><i class="fa-solid fa-arrow-left"></i> Contenido web</a>
    <a href="index.php#nosotros" target="_blank" rel="noopener" class="btn-secondary"><i class="fa-solid fa-arrow-up-right-from-square"></i> Abrir sitio</a>';

ob_start();
?>

<?php if ($status === 'updated'): ?>
    <div class="cw-feedback cw-feedback--ok" role="status">
        <i class="fa-solid fa-circle-check"></i> Contenido publicado correctamente en la landing.
    </div>
<?php elseif ($status === 'error'): ?>
    <div class="cw-feedback cw-feedback--err" role="alert">
        <i class="fa-solid fa-circle-exclamation"></i> No se pudo guardar. Intente de nuevo.
    </div>
<?php endif; ?>

<form action="<?= eco_url('api/acciones_contenido.php') ?>" method="POST" id="form-textos-nosotros" class="cw-textos-form">
    <?= csrf_field() ?>
    <input type="hidden" name="tipo" value="textos_web">

    <div class="cw-textos-layout">
        <div class="cw-textos-editor">
            <div class="card cw-textos-editor-card">
                <div class="cw-textos-editor-card__head">
                    <h3><i class="fa-solid fa-pen-to-square"></i> Editor de contenido</h3>
                    <nav class="cw-textos-jump" aria-label="Ir a bloque">
                        <a href="#bloque-mision" class="cw-textos-jump__link cw-textos-jump__link--mision">Misión</a>
                        <a href="#bloque-vision" class="cw-textos-jump__link cw-textos-jump__link--vision">Visión</a>
                        <a href="#bloque-valores" class="cw-textos-jump__link cw-textos-jump__link--valores">Valores</a>
                    </nav>
                </div>

                <article class="cw-texto-card cw-texto-card--mision" id="bloque-mision">
                    <header class="cw-texto-card__header">
                        <span class="cw-texto-card__icon" aria-hidden="true"><i class="fa-solid fa-bullseye"></i></span>
                        <div class="cw-texto-card__titles">
                            <h4>Misión</h4>
                            <p>¿Qué hacemos y para quién?</p>
                        </div>
                        <span class="cw-texto-card__count" data-count-for="mision"><?= (int)$charsMision ?> / 1200</span>
                    </header>
                    <div class="cw-texto-card__field">
                        <textarea name="mision" id="mision" class="cw-texto-card__input" maxlength="1200" required
                                  placeholder="Describe el propósito central de EcoMadelleine…"
                                  data-preview="preview-mision"><?= htmlspecialchars($contenido['mision']) ?></textarea>
                    </div>
                </article>

                <article class="cw-texto-card cw-texto-card--vision" id="bloque-vision">
                    <header class="cw-texto-card__header">
                        <span class="cw-texto-card__icon" aria-hidden="true"><i class="fa-solid fa-binoculars"></i></span>
                        <div class="cw-texto-card__titles">
                            <h4>Visión</h4>
                            <p>¿Hacia dónde nos dirigimos?</p>
                        </div>
                        <span class="cw-texto-card__count" data-count-for="vision"><?= (int)$charsVision ?> / 1200</span>
                    </header>
                    <div class="cw-texto-card__field">
                        <textarea name="vision" id="vision" class="cw-texto-card__input" maxlength="1200" required
                                  placeholder="Define la meta institucional a futuro…"
                                  data-preview="preview-vision"><?= htmlspecialchars($contenido['vision']) ?></textarea>
                    </div>
                </article>

                <article class="cw-texto-card cw-texto-card--valores" id="bloque-valores">
                    <header class="cw-texto-card__header">
                        <span class="cw-texto-card__icon" aria-hidden="true"><i class="fa-solid fa-gem"></i></span>
                        <div class="cw-texto-card__titles">
                            <h4>Valores</h4>
                            <p>Principios que nos distinguen</p>
                        </div>
                        <span class="cw-texto-card__count" data-count-for="valores"><?= (int)$charsValores ?> / 600</span>
                    </header>
                    <div class="cw-texto-card__field">
                        <textarea name="valores" id="valores" class="cw-texto-card__input" maxlength="600" required
                                  placeholder="Ej. ética, calidez humana, excelencia clínica…"
                                  data-preview="preview-valores"><?= htmlspecialchars($contenido['valores']) ?></textarea>
                    </div>
                </article>
            </div>
        </div>

        <aside class="cw-textos-preview-wrap">
            <div class="card cw-textos-preview-card">
                <div class="cw-textos-preview-card__head">
                    <h3><i class="fa-solid fa-display"></i> Vista previa</h3>
                    <span class="cw-textos-preview-badge">Landing pública</span>
                </div>
                <div class="cw-textos-preview-mock">
                    <div class="cw-textos-preview-mock__img" aria-hidden="true">
                        <i class="fa-solid fa-user-doctor"></i>
                    </div>
                    <div class="cw-textos-preview-mock__body">
                        <h2>Sobre Nosotros</h2>
                        <div class="cw-textos-preview-block">
                            <span class="cw-textos-preview-block__tag cw-textos-preview-block__tag--mision">Misión</span>
                            <p id="preview-mision"><?= $contenido['mision'] !== '' ? nl2br(htmlspecialchars($contenido['mision'])) : '<em class="cw-preview-placeholder">Escribe la misión…</em>' ?></p>
                        </div>
                        <div class="cw-textos-preview-block">
                            <span class="cw-textos-preview-block__tag cw-textos-preview-block__tag--vision">Visión</span>
                            <p id="preview-vision"><?= $contenido['vision'] !== '' ? nl2br(htmlspecialchars($contenido['vision'])) : '<em class="cw-preview-placeholder">Escribe la visión…</em>' ?></p>
                        </div>
                        <div class="cw-textos-preview-block">
                            <span class="cw-textos-preview-block__tag cw-textos-preview-block__tag--valores">Valores</span>
                            <p id="preview-valores"><?= $contenido['valores'] !== '' ? nl2br(htmlspecialchars($contenido['valores'])) : '<em class="cw-preview-placeholder">Escribe los valores…</em>' ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </aside>
    </div>

    <div class="cw-textos-savebar">
        <div class="cw-textos-savebar__inner card">
            <p class="cw-textos-savebar__hint">
                <i class="fa-solid fa-cloud-arrow-up"></i>
                Los cambios se publican al guardar en la sección <strong>#nosotros</strong>.
            </p>
            <div class="cw-textos-savebar__actions">
                <a href="index.php#nosotros" target="_blank" rel="noopener" class="btn-secondary">
                    <i class="fa-solid fa-eye"></i> Previsualizar en sitio
                </a>
                <button type="submit" name="accion" value="actualizar" class="btn-primary" id="btn-guardar-textos">
                    <i class="fa-solid fa-floppy-disk"></i> Guardar cambios
                </button>
            </div>
        </div>
    </div>
</form>

<?php
$page_content = ob_get_clean();
$page_scripts_extra = '<script src="assets/js/contenido/gestionar-textos.js"></script>';
include __DIR__ . '/../layouts/shell.php';
