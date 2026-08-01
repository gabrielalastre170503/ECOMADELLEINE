<?php
date_default_timezone_set('America/Caracas');
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

if (!function_exists('formatearBytes')) {
    function formatearBytes($bytes)
    {
        $bytes = (int)$bytes;
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        $units = ['KB', 'MB', 'GB', 'TB'];
        $power = (int)floor(log($bytes, 1024));
        $power = min($power, count($units));
        $value = $bytes / pow(1024, $power);
        $unit = $units[$power - 1] ?? 'KB';
        return number_format($value, $value >= 10 ? 1 : 2) . ' ' . $unit;
    }
}

$documentos_data = [
    'items' => [],
    'stats' => [
        'total_archivos' => 0,
        'tamano_total' => 0,
        'tamano_total_legible' => '0 B',
        'por_categoria' => [],
    ],
    'carpeta_disponible' => true,
    // Los documentos ya NO se enlazan a la carpeta (ahora denegada por su
    // .htaccess): se piden al handler, que comprueba sesion y rol.
    'base_url' => eco_url('api/descargar_documento.php') . '?f=',
    'feedback' => null,
];

if (isset($_SESSION['documentos_feedback'])) {
    $documentos_data['feedback'] = $_SESSION['documentos_feedback'];
    unset($_SESSION['documentos_feedback']);
}

$documentos_base_path = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'documentos';
if (!is_dir($documentos_base_path)) {
    // 0775, no 0777: el repositorio no tiene por que ser escribible por todo el
    // sistema. Mismo permiso que usa eco_uploads_base() para los adjuntos.
    @mkdir($documentos_base_path, 0775, true);
}
$documentos_data['carpeta_disponible'] = is_dir($documentos_base_path) && is_writable($documentos_base_path);

$extensiones_permitidas = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'zip', 'rar'];
$mapa_categorias = [
    'pdf' => 'PDF y Manuales',
    'doc' => 'Documentos Word',
    'docx' => 'Documentos Word',
    'xls' => 'Hojas de Cálculo',
    'xlsx' => 'Hojas de Cálculo',
    'ppt' => 'Presentaciones',
    'pptx' => 'Presentaciones',
    'txt' => 'Notas y Texto Plano',
    'csv' => 'Registros CSV',
    'zip' => 'Archivos Comprimidos',
    'rar' => 'Archivos Comprimidos',
];
$peso_maximo_bytes = 10 * 1024 * 1024;
$redirectUrl = eco_url('repositorio');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['documento_action'])) {
    // Subir y eliminar escriben en disco: exigen token, como el resto de
    // acciones de escritura del sistema.
    require_csrf();
    $accionDocumento = $_POST['documento_action'];

    if ($accionDocumento === 'upload') {
        if (!$documentos_data['carpeta_disponible']) {
            $_SESSION['documentos_feedback'] = ['type' => 'error', 'message' => 'No se puede escribir en la carpeta de documentos. Verifica permisos.'];
            header('Location: ' . $redirectUrl);
            exit;
        }
        if (!isset($_FILES['documento_archivo']) || $_FILES['documento_archivo']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['documentos_feedback'] = ['type' => 'error', 'message' => 'No se recibió el archivo o se produjo un error durante la subida.'];
            header('Location: ' . $redirectUrl);
            exit;
        }
        $archivoSubido = $_FILES['documento_archivo'];
        if ($archivoSubido['size'] > $peso_maximo_bytes) {
            $_SESSION['documentos_feedback'] = ['type' => 'error', 'message' => 'El archivo supera el tamaño máximo permitido (10 MB).'];
            header('Location: ' . $redirectUrl);
            exit;
        }
        $nombreOriginal = $archivoSubido['name'];
        $extension = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));
        if (!in_array($extension, $extensiones_permitidas, true)) {
            $_SESSION['documentos_feedback'] = ['type' => 'error', 'message' => 'Tipo de archivo no permitido.'];
            header('Location: ' . $redirectUrl);
            exit;
        }
        $nombreBase = pathinfo($nombreOriginal, PATHINFO_FILENAME);
        $nombreSanitizado = preg_replace('/[^a-zA-Z0-9-_]+/', '-', $nombreBase);
        $nombreSanitizado = trim($nombreSanitizado, '-_');
        if ($nombreSanitizado === '') {
            $nombreSanitizado = 'documento';
        }
        $nombreDestino = $nombreSanitizado . '-' . date('Ymd-His') . '.' . $extension;
        $rutaDestino = $documentos_base_path . DIRECTORY_SEPARATOR . $nombreDestino;

        if (!move_uploaded_file($archivoSubido['tmp_name'], $rutaDestino)) {
            $_SESSION['documentos_feedback'] = ['type' => 'error', 'message' => 'No se pudo guardar el archivo en el servidor.'];
            header('Location: ' . $redirectUrl);
            exit;
        }
        $_SESSION['documentos_feedback'] = ['type' => 'success', 'message' => 'Documento cargado correctamente.'];
        header('Location: ' . $redirectUrl);
        exit;
    }

    if ($accionDocumento === 'delete') {
        $archivoEliminar = isset($_POST['documento_nombre']) ? basename($_POST['documento_nombre']) : '';
        $rutaEliminar = $archivoEliminar ? realpath($documentos_base_path . DIRECTORY_SEPARATOR . $archivoEliminar) : false;
        $carpetaReal = realpath($documentos_base_path);

        if (!$archivoEliminar || !$rutaEliminar || strpos($rutaEliminar, $carpetaReal) !== 0 || !is_file($rutaEliminar)) {
            $_SESSION['documentos_feedback'] = ['type' => 'error', 'message' => 'No se encontró el archivo solicitado.'];
            header('Location: ' . $redirectUrl);
            exit;
        }
        if (!@unlink($rutaEliminar)) {
            $_SESSION['documentos_feedback'] = ['type' => 'error', 'message' => 'No se pudo eliminar el archivo.'];
            header('Location: ' . $redirectUrl);
            exit;
        }
        $_SESSION['documentos_feedback'] = ['type' => 'success', 'message' => 'Documento eliminado correctamente.'];
        header('Location: ' . $redirectUrl);
        exit;
    }
}

if (is_dir($documentos_base_path)) {
    $archivos = scandir($documentos_base_path);
    $carpetaReal = realpath($documentos_base_path);
    foreach ($archivos as $archivo) {
        // Se saltan TODOS los archivos ocultos, no solo . y ..: en la carpeta
        // viven .gitkeep y el .htaccess que la protege, y no son documentos
        // del repositorio (aparecian como filas en la tabla).
        if ($archivo === '' || $archivo[0] === '.') {
            continue;
        }
        $rutaArchivo = $documentos_base_path . DIRECTORY_SEPARATOR . $archivo;
        if (!is_file($rutaArchivo)) {
            continue;
        }
        $extension = strtolower(pathinfo($archivo, PATHINFO_EXTENSION));
        $categoria = $mapa_categorias[$extension] ?? 'Otros Documentos';
        $tamano = filesize($rutaArchivo);
        $documentos_data['stats']['total_archivos']++;
        $documentos_data['stats']['tamano_total'] += $tamano;

        if (!isset($documentos_data['stats']['por_categoria'][$categoria])) {
            $documentos_data['stats']['por_categoria'][$categoria] = [
                'nombre' => $categoria,
                'total' => 0,
                'tamano' => 0,
            ];
        }
        $documentos_data['stats']['por_categoria'][$categoria]['total']++;
        $documentos_data['stats']['por_categoria'][$categoria]['tamano'] += $tamano;

        $documentos_data['items'][] = [
            'nombre' => $archivo,
            'extension' => $extension,
            'categoria' => $categoria,
            'tamano' => $tamano,
            'tamano_legible' => formatearBytes($tamano),
            'modificado' => filemtime($rutaArchivo),
            'modificado_legible' => date('d/m/Y H:i', filemtime($rutaArchivo)),
            'search_text' => strtolower($archivo . ' ' . $categoria . ' ' . $extension),
        ];
    }
    usort($documentos_data['items'], function ($a, $b) {
        return $b['modificado'] <=> $a['modificado'];
    });
    $documentos_data['stats']['tamano_total_legible'] = formatearBytes($documentos_data['stats']['tamano_total']);
    $documentos_data['stats']['por_categoria'] = array_values(array_map(function ($categoria) {
        $categoria['tamano_legible'] = formatearBytes($categoria['tamano']);
        return $categoria;
    }, $documentos_data['stats']['por_categoria']));
    usort($documentos_data['stats']['por_categoria'], function ($a, $b) {
        return $b['total'] <=> $a['total'];
    });
}

$documentFeedback = $documentos_data['feedback'];
$documentStats = $documentos_data['stats'];
$documentCategories = $documentStats['por_categoria'] ?? [];
$documentItems = $documentos_data['items'];
$documentBaseUrl = $documentos_data['base_url'];
$carpetaDisponibleDocs = (bool)$documentos_data['carpeta_disponible'];
$totalCategoriasDoc = count($documentCategories);

$page_title    = 'Repositorio de Documentos';
$page_subtitle = 'Contratos, manuales y archivos internos';
$active_section = 'admin-documentos';
$page_head_extra = '<link rel="stylesheet" href="assets/css/admin/admin-documentos.css">';

ob_start();
?>

<?php if ($documentFeedback): ?>
    <?php $cls = ($documentFeedback['type'] ?? '') === 'success' ? 'badge-success' : 'badge-danger'; ?>
    <div class="card" style="margin-bottom:14px;padding:12px 16px;border-left:4px solid <?= ($documentFeedback['type'] ?? '') === 'success' ? 'var(--success)' : 'var(--danger)' ?>;">
        <strong class="<?= $cls ?>"><?= ($documentFeedback['type'] ?? '') === 'success' ? 'Listo:' : 'Error:' ?></strong>
        <?= htmlspecialchars($documentFeedback['message'] ?? '') ?>
    </div>
<?php endif; ?>

<?php if (!$carpetaDisponibleDocs): ?>
    <div class="card" style="margin-bottom:14px;border-color:var(--danger);background:rgba(239,68,68,.06);">
        <strong>Permisos insuficientes:</strong> No se puede escribir en <code>documentos/</code>.
    </div>
<?php endif; ?>

<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);">
    <div class="stat-card">
        <div class="stat-card-icon" style="background:var(--accent-soft);color:var(--accent-text);"><i class="fa-solid fa-file-lines"></i></div>
        <p class="stat-card-label">Documentos</p>
        <p class="stat-card-value accent"><?= (int)($documentStats['total_archivos'] ?? 0) ?></p>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon" style="background:rgba(139,92,246,.12);color:#6d28d9;"><i class="fa-solid fa-hard-drive"></i></div>
        <p class="stat-card-label">Espacio ocupado</p>
        <p class="stat-card-value" style="color:#6d28d9;"><?= htmlspecialchars($documentStats['tamano_total_legible'] ?? '0 B') ?></p>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon" style="background:rgba(34,197,94,.12);color:#15803d;"><i class="fa-solid fa-folder-tree"></i></div>
        <p class="stat-card-label">Categorías</p>
        <p class="stat-card-value success"><?= $totalCategoriasDoc ?></p>
    </div>
</div>

<?php if ($totalCategoriasDoc > 0): ?>
    <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:18px;">
        <?php foreach ($documentCategories as $cat): ?>
            <span class="badge badge-info"><?= htmlspecialchars($cat['nombre'] ?? '') ?> · <?= (int)($cat['total'] ?? 0) ?> docs</span>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="card" style="margin-bottom:18px;">
    <div class="card-header">
        <h3><i class="fa-solid fa-cloud-arrow-up" style="margin-right:7px;color:var(--accent);"></i> Subir documento</h3>
    </div>
    <p style="color:var(--text-secondary);font-size:13px;margin:0 0 14px;">PDF, Word, Excel, PowerPoint, TXT, CSV, ZIP y RAR — máximo 10 MB.</p>
    <form method="POST" enctype="multipart/form-data" class="doc-upload-form" id="doc-upload-form">
        <?= csrf_field() ?>
        <input type="hidden" name="documento_action" value="upload">
        <div class="doc-upload-zone<?= $carpetaDisponibleDocs ? '' : ' is-disabled' ?>" id="doc-upload-zone">
            <div class="doc-file-picker-wrap">
                <input type="file"
                       class="doc-file-input"
                       id="documento_archivo"
                       name="documento_archivo"
                       required
                       accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip,.rar"
                       <?= $carpetaDisponibleDocs ? '' : 'disabled' ?>>
                <label for="documento_archivo" class="doc-file-picker-btn">
                    <i class="fa-solid fa-folder-open" aria-hidden="true"></i>
                    <span>Elegir archivo</span>
                </label>
            </div>
            <div class="doc-file-info">
                <span class="doc-file-name is-placeholder" id="doc-file-name">Ningún archivo seleccionado</span>
                <span class="doc-file-hint" id="doc-file-hint"><i class="fa-solid fa-hand-pointer"></i> También puede arrastrar el archivo aquí</span>
            </div>
        </div>
        <button type="submit" class="btn-primary doc-upload-submit" id="doc-upload-submit" <?= $carpetaDisponibleDocs ? '' : 'disabled' ?>>
            <i class="fa-solid fa-cloud-arrow-up"></i> Subir documento
        </button>
    </form>
</div>

<div class="card">
    <div class="card-header">
        <h3><i class="fa-solid fa-list" style="margin-right:7px;color:var(--accent);"></i> Archivos</h3>
    </div>
    <div style="margin-bottom:12px;">
        <input type="search" id="document-search-input" placeholder="Buscar por nombre o categoría..."
               style="width:100%;max-width:400px;padding:9px 12px;border:1.5px solid var(--border);border-radius:8px;">
    </div>

    <?php if (empty($documentItems)): ?>
        <p style="text-align:center;color:var(--text-muted);padding:30px;">Aún no hay documentos subidos.</p>
    <?php else: ?>
        <div class="data-table" style="border:none;">
            <table>
                <thead>
                    <tr><th>Nombre</th><th>Categoría</th><th>Tamaño</th><th>Actualizado</th><th>Acciones</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($documentItems as $documento):
                        $docNombre = $documento['nombre'] ?? '';
                        $docUrl = $documentBaseUrl . rawurlencode($docNombre);
                    ?>
                        <tr class="document-row" data-search="<?= htmlspecialchars($documento['search_text'] ?? '') ?>">
                            <td><strong><?= htmlspecialchars($docNombre) ?></strong><br><small>.<?= htmlspecialchars($documento['extension'] ?? '') ?></small></td>
                            <td><?= htmlspecialchars($documento['categoria'] ?? '') ?></td>
                            <td><?= htmlspecialchars($documento['tamano_legible'] ?? '') ?></td>
                            <td style="font-size:12.5px;"><?= htmlspecialchars($documento['modificado_legible'] ?? '') ?></td>
                            <td style="white-space:nowrap;">
                                <a href="<?= htmlspecialchars($docUrl) ?>" target="_blank" rel="noopener" class="btn-secondary" style="padding:4px 10px;font-size:11.5px;"><i class="fa-solid fa-arrow-up-right-from-square"></i></a>
                                <button type="button" class="btn-secondary document-copy-link" style="padding:4px 10px;font-size:11.5px;" data-url="<?= htmlspecialchars($docUrl) ?>"><i class="fa-solid fa-link"></i></button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar este documento?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="documento_action" value="delete">
                                    <input type="hidden" name="documento_nombre" value="<?= htmlspecialchars($docNombre) ?>">
                                    <button type="submit" class="btn-secondary" style="padding:4px 10px;font-size:11.5px;color:var(--danger);border-color:rgba(239,68,68,.3);"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
(function () {
    var zone = document.getElementById('doc-upload-zone');
    var input = document.getElementById('documento_archivo');
    var nameEl = document.getElementById('doc-file-name');
    var hintEl = document.getElementById('doc-file-hint');
    if (!zone || !input || !nameEl) return;

    function formatSize(bytes) {
        if (!bytes || bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / 1048576).toFixed(2) + ' MB';
    }

    function setFile(file) {
        if (!file) {
            nameEl.textContent = 'Ningún archivo seleccionado';
            nameEl.classList.add('is-placeholder');
            zone.classList.remove('has-file');
            if (hintEl) hintEl.style.display = '';
            return;
        }
        nameEl.textContent = file.name + ' · ' + formatSize(file.size);
        nameEl.classList.remove('is-placeholder');
        zone.classList.add('has-file');
        if (hintEl) hintEl.style.display = 'none';
    }

    input.addEventListener('change', function () {
        setFile(input.files && input.files[0] ? input.files[0] : null);
    });

    ['dragenter', 'dragover'].forEach(function (ev) {
        zone.addEventListener(ev, function (e) {
            if (input.disabled) return;
            e.preventDefault();
            zone.classList.add('is-dragover');
        });
    });
    ['dragleave', 'drop'].forEach(function (ev) {
        zone.addEventListener(ev, function (e) {
            e.preventDefault();
            zone.classList.remove('is-dragover');
        });
    });
    zone.addEventListener('drop', function (e) {
        if (input.disabled || !e.dataTransfer || !e.dataTransfer.files.length) return;
        var file = e.dataTransfer.files[0];
        if (typeof DataTransfer !== 'undefined') {
            var dt = new DataTransfer();
            dt.items.add(file);
            input.files = dt.files;
        } else {
            return;
        }
        input.dispatchEvent(new Event('change', { bubbles: true }));
    });
})();

document.getElementById('document-search-input')?.addEventListener('input', function () {
    const q = this.value.trim().toLowerCase();
    document.querySelectorAll('.document-row').forEach(function (row) {
        const s = row.getAttribute('data-search') || '';
        row.style.display = !q || s.includes(q) ? '' : 'none';
    });
});
document.querySelectorAll('.document-copy-link').forEach(function (btn) {
    btn.addEventListener('click', function () {
        const rel = btn.getAttribute('data-url') || '';
        const abs = new URL(rel, window.location.href).href;
        navigator.clipboard.writeText(abs).then(function () { alert('Enlace copiado al portapapeles.'); }).catch(function () { alert(abs); });
    });
});
</script>

<?php
$page_content = ob_get_clean();
include __DIR__ . '/../layouts/shell.php';
