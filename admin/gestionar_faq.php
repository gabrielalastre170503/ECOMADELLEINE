<?php
session_start();
include __DIR__ . '/../core/conexion.php';

if (!isset($_SESSION['usuario_id']) || ($_SESSION['rol'] ?? '') !== 'administrador') {
    header('Location: ' . eco_url('login'));
    exit;
}

$faqRows = [];
if ($r = $conex->query('SELECT id, pregunta, respuesta, orden FROM faqs ORDER BY orden ASC, id ASC')) {
    while ($row = $r->fetch_assoc()) {
        $faqRows[] = $row;
    }
    $r->free();
}
$totalFaqs = count($faqRows);

$status = isset($_GET['status']) ? (string)$_GET['status'] : '';

$page_title    = 'Preguntas frecuentes';
$page_subtitle = 'Administra las FAQ visibles en el sitio público';
$active_section = 'admin-contenido';
$body_class    = 'cw-gestion-page';
$page_head_extra = '<link rel="stylesheet" href="assets/css/admin/admin-gestion-contenido.css">';

$page_header_actions = '
    <a href="' . eco_url('contenido') . '" class="btn-secondary"><i class="fa-solid fa-arrow-left"></i> Contenido web</a>
    <a href="index.php#faq" target="_blank" rel="noopener" class="btn-secondary"><i class="fa-solid fa-eye"></i> Ver en sitio</a>';

ob_start();
?>

<?php if ($status === 'added'): ?>
    <div class="cw-feedback cw-feedback--ok" role="status">
        <i class="fa-solid fa-circle-check"></i> Pregunta añadida correctamente.
    </div>
<?php elseif ($status === 'deleted'): ?>
    <div class="cw-feedback cw-feedback--ok" role="status">
        <i class="fa-solid fa-circle-check"></i> Pregunta eliminada.
    </div>
<?php elseif ($status === 'error'): ?>
    <div class="cw-feedback cw-feedback--err" role="alert">
        <i class="fa-solid fa-circle-exclamation"></i> No se pudo completar la operación. Intente de nuevo.
    </div>
<?php endif; ?>

<div class="cw-faq-grid">
    <div class="card cw-panel">
        <div class="cw-panel__head">
            <div class="cw-panel__head-icon cw-panel__head-icon--faq" aria-hidden="true">
                <i class="fa-solid fa-plus"></i>
            </div>
            <div class="cw-panel__head-text">
                <h3>Nueva pregunta</h3>
                <p>Añade entradas al listado público</p>
            </div>
        </div>
        <div class="cw-panel__body">
            <form action="<?= eco_url('api/acciones_contenido.php') ?>" method="POST" class="cw-form">
                <?= csrf_field() ?>
                <input type="hidden" name="tipo" value="faq">
                <div class="cw-field">
                    <label for="pregunta"><i class="fa-solid fa-circle-question"></i> Pregunta</label>
                    <textarea name="pregunta" id="pregunta" class="cw-input" rows="3" required placeholder="Ej. ¿Cómo solicito una cita?"></textarea>
                </div>
                <div class="cw-field">
                    <label for="respuesta"><i class="fa-solid fa-comment-dots"></i> Respuesta</label>
                    <textarea name="respuesta" id="respuesta" class="cw-input" rows="6" required placeholder="Redacta la respuesta completa…"></textarea>
                </div>
                <div class="cw-form__actions">
                    <button type="submit" name="accion" value="agregar" class="btn-primary">
                        <i class="fa-solid fa-plus"></i> Añadir pregunta
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card cw-panel">
        <div class="cw-panel__head">
            <div class="cw-panel__head-icon cw-panel__head-icon--faq" aria-hidden="true">
                <i class="fa-solid fa-list"></i>
            </div>
            <div class="cw-panel__head-text">
                <h3>Preguntas actuales</h3>
                <p>Ordenadas según configuración del sitio</p>
            </div>
            <span class="cw-panel__badge"><?= (int)$totalFaqs ?></span>
        </div>
        <div class="cw-panel__body">
            <?php if ($totalFaqs === 0): ?>
                <p class="cw-empty"><i class="fa-solid fa-inbox"></i>No hay preguntas registradas.</p>
            <?php else: ?>
                <ul class="cw-faq-list">
                    <?php foreach ($faqRows as $i => $faq): ?>
                        <li class="cw-faq-item">
                            <span class="cw-faq-item__num"><?= $i + 1 ?></span>
                            <div class="cw-faq-item__body">
                                <h4 class="cw-faq-item__q"><?= htmlspecialchars($faq['pregunta']) ?></h4>
                                <p class="cw-faq-item__a"><?= htmlspecialchars($faq['respuesta']) ?></p>
                            </div>
                            <div class="cw-faq-item__actions">
                                <a href="<?= eco_url('api/acciones_contenido.php') ?>?tipo=faq&amp;accion=borrar&amp;id=<?= (int)$faq['id'] ?>&amp;csrf_token=<?= urlencode(csrf_token()) ?>"
                                   class="cw-btn-delete"
                                   onclick="return confirm('¿Eliminar esta pregunta?');">
                                    <i class="fa-solid fa-trash-can"></i> Borrar
                                </a>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$page_content = ob_get_clean();
include __DIR__ . '/../layouts/shell.php';
