<?php
/**
 * Mis Informes (paciente): índice documental de los estudios finalizados.
 *
 * Un informe es un documento con número correlativo, autor y firma, así que
 * se presenta como un expediente archivado por año, no como una lista de
 * tarjetas.
 */
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

/* Solo se muestran al paciente los informes finalizados o firmados (no borradores). */
$informes = [];
if ($stmt = $conex->prepare("
    SELECT ie.id, ie.numero_informe, ie.fecha_estudio, ie.estado, ie.creado_en,
           t.nombre AS tipo_nombre, t.icono AS tipo_icono, t.categoria AS tipo_categoria,
           u.nombre_completo AS ecografista_nombre
    FROM informes_estudios ie
    LEFT JOIN tipos_ecografias t ON t.id = ie.tipo_ecografia_id
    LEFT JOIN usuarios u         ON u.id = ie.ecografista_id
    WHERE ie.paciente_id = ? AND ie.estado IN ('finalizado','firmado')
    ORDER BY ie.creado_en DESC
")) {
    $stmt->bind_param('i', $usuario_id);
    $stmt->execute();
    $informes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$total       = count($informes);
$num_firmado = 0;
$num_anio    = 0;
$anio_actual = (int)date('Y');
$ultimo_label = '—';

/* Agrupación por año: los informes se numeran por ejercicio
   (INF-2026-00001), así que es como están archivados de verdad. */
$por_anio = [];
foreach ($informes as $i => $inf) {
    if ($inf['estado'] === 'firmado') { $num_firmado++; }
    $raw = $inf['fecha_estudio'] ?: substr((string)$inf['creado_en'], 0, 10);
    $ts  = $raw ? strtotime($raw) : null;
    if ($ts && (int)date('Y', $ts) === $anio_actual) { $num_anio++; }
    if ($i === 0 && $ts) { $ultimo_label = date('d/m/Y', $ts); }

    $anio = $ts ? date('Y', $ts) : 'Sin fecha';
    $por_anio[$anio][] = $inf + ['_ts' => $ts];
}

$paciente_nombre = (string)($_SESSION['nombre_completo'] ?? 'Paciente');

$page_title     = 'Mis Informes';
$page_subtitle  = 'Expediente de tus estudios ecográficos';
$active_section = 'mis-informes';
$body_class     = 'exp';

$css_inf = 'assets/css/paciente/mis-informes.css';
$page_head_extra = '<link rel="stylesheet" href="' . $css_inf
    . '?v=' . (@filemtime(__DIR__ . '/../' . $css_inf) ?: '1') . '">';

ob_start();
?>

<section class="card exp-cabecera">
    <div class="exp-cabecera__ident">
        <span class="exp-cabecera__sello" aria-hidden="true"><i class="fa-solid fa-folder-open"></i></span>
        <div>
            <p class="exp-cabecera__eyebrow">Expediente de estudios</p>
            <h2 class="exp-cabecera__nombre"><?= htmlspecialchars($paciente_nombre) ?></h2>
            <p class="exp-cabecera__nota">
                <?php if ($total === 0): ?>
                    Sin estudios registrados todavía.
                <?php else: ?>
                    <?= $total ?> <?= $total === 1 ? 'estudio archivado' : 'estudios archivados' ?>
                    · último el <?= htmlspecialchars($ultimo_label) ?>
                <?php endif; ?>
            </p>
        </div>
    </div>
    <dl class="exp-cifras">
        <div>
            <dt>Estudios</dt>
            <dd><?= $total ?></dd>
        </div>
        <div class="exp-cifras__firmados">
            <dt>Firmados</dt>
            <dd><?= $num_firmado ?></dd>
        </div>
        <div>
            <dt>En <?= $anio_actual ?></dt>
            <dd><?= $num_anio ?></dd>
        </div>
    </dl>
</section>

<div class="card">
    <div class="card-header">
        <h3><i class="fa-solid fa-folder-tree" style="margin-right:8px;color:var(--accent);"></i> Índice de estudios</h3>
    </div>

    <?php if ($total === 0): ?>
        <div class="exp-vacio">
            <i class="fa-solid fa-file-circle-xmark"></i>
            <p style="margin:0 0 4px;font-weight:600;color:var(--text-secondary);">Aún no tienes informes disponibles</p>
            <p style="margin:0;font-size:13px;">Tus resultados aparecerán aquí cuando el ecografista finalice tu estudio.</p>
        </div>
    <?php else: ?>

        <div class="exp-toolbar">
            <div class="exp-search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="exp-search-input" placeholder="Buscar por estudio, número o ecografista…" autocomplete="off">
            </div>
            <div class="exp-tabs">
                <button type="button" class="exp-tab is-active" data-filter="todos">Todos <span class="exp-tab-count"><?= $total ?></span></button>
                <button type="button" class="exp-tab" data-filter="firmado">Firmados <span class="exp-tab-count"><?= $num_firmado ?></span></button>
                <button type="button" class="exp-tab" data-filter="finalizado">Finalizados <span class="exp-tab-count"><?= ($total - $num_firmado) ?></span></button>
            </div>
        </div>

        <div class="exp-tabla-wrap">
            <table class="exp-tabla">
                <thead>
                    <tr>
                        <th>Nº de informe</th>
                        <th>Estudio</th>
                        <th>Fecha</th>
                        <th>Ecografista</th>
                        <th>Validación</th>
                        <!-- Columna de acción: sin rótulo visible, pero anunciada
                             a los lectores de pantalla. -->
                        <th><span aria-label="Acción"></span></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($por_anio as $anio => $filas): ?>
                    <tr class="exp-anio">
                        <td colspan="6">
                            <div class="exp-anio__fila">
                                <span class="exp-anio__num"><?= htmlspecialchars($anio) ?></span>
                                <span class="exp-anio__conteo"><?= count($filas) ?> <?= count($filas) === 1 ? 'estudio' : 'estudios' ?></span>
                            </div>
                        </td>
                    </tr>
                    <?php foreach ($filas as $inf):
                        $firmado = ($inf['estado'] === 'firmado');
                        $titulo  = $inf['tipo_nombre'] ?: 'Ecografía';
                        $icono   = $inf['tipo_icono'] ?: 'fa-solid fa-file-waveform';
                        $busca   = mb_strtolower(trim($titulo . ' ' . ($inf['numero_informe'] ?? '') . ' '
                                    . ($inf['ecografista_nombre'] ?? '') . ' ' . ($inf['tipo_categoria'] ?? '')));
                    ?>
                        <tr class="exp-fila"
                            data-estado="<?= htmlspecialchars($inf['estado']) ?>"
                            data-search="<?= htmlspecialchars($busca, ENT_QUOTES) ?>">
                            <td class="exp-num"><?= htmlspecialchars($inf['numero_informe'] ?: '—') ?></td>
                            <td>
                                <div class="exp-estudio">
                                    <span class="exp-estudio__icono" aria-hidden="true"><i class="<?= htmlspecialchars($icono, ENT_QUOTES) ?>"></i></span>
                                    <span>
                                        <span class="exp-estudio__nombre"><?= htmlspecialchars($titulo) ?></span>
                                        <?php if (!empty($inf['tipo_categoria'])): ?>
                                            <span class="exp-estudio__cat"><?= htmlspecialchars($inf['tipo_categoria']) ?></span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </td>
                            <td class="exp-fecha"><?= $inf['_ts'] ? date('d/m/Y', $inf['_ts']) : '—' ?></td>
                            <td class="exp-autor"><?= htmlspecialchars($inf['ecografista_nombre'] ?: '—') ?></td>
                            <td>
                                <?php if ($firmado): ?>
                                    <span class="exp-sello"><i class="fa-solid fa-certificate"></i> Firmado</span>
                                <?php else: ?>
                                    <span class="exp-sello exp-sello--sin"><i class="fa-regular fa-circle-check"></i> Finalizado</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:right;">
                                <button type="button" class="exp-btn" data-informe-id="<?= (int)$inf['id'] ?>">
                                    <i class="fa-solid fa-eye"></i> Ver informe
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div id="exp-empty-filter" class="exp-vacio" style="display:none;">
            <i class="fa-solid fa-magnifying-glass"></i>
            <p style="margin:0;font-weight:600;color:var(--text-secondary);">No se encontraron informes</p>
            <p style="margin:0;font-size:13px;">Prueba con otro término de búsqueda o filtro.</p>
        </div>

    <?php endif; ?>
</div>

<!-- Misma modal "Ver informe" del rol ecografista (lectura): reutiliza id/clases → mismo CSS de shell-modals.css -->
<div id="eco-modal-informe-detalle-eco" class="eco-modal eco-modal-panel-ecografista" aria-hidden="true" role="dialog" aria-labelledby="eco-inf-det-titulo">
    <div class="modal-content-form-eco">
        <div class="modal-form-eco-header">
            <div class="eco-modal-tipo-icon" id="eco-inf-det-icon">
                <i class="fa-solid fa-file-waveform"></i>
            </div>
            <div class="eco-header-tipo-info">
                <h2 id="eco-inf-det-titulo">Informe de Estudio</h2>
                <p id="eco-inf-det-paciente">—</p>
            </div>
            <div class="eco-modal-informe-detalle-actions">
                <button type="button" class="eco-btn-cancel" id="eco-inf-det-print" title="Imprimir informe">
                    <i class="fa-solid fa-print"></i> Imprimir
                </button>
                <button type="button" class="modal-close-btn" data-eco-modal-close aria-label="Cerrar">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
        </div>
        <div class="modal-form-eco-body" id="eco-informe-detalle-body">
            <div class="modal-form-eco-loader">
                <i class="fa-solid fa-spinner fa-spin"></i>
                <p>Cargando informe…</p>
            </div>
        </div>
    </div>
</div>

<?php
$page_content = ob_get_clean();

$page_scripts_extra = <<<'HTML'
<script>
(function () {
    /* Filtro por pestañas + búsqueda, en una sola pasada: si cada uno ocultara
       por su cuenta, el segundo volvería a mostrar lo que el primero escondió. */
    var tabs   = document.querySelectorAll('.exp-tab');
    var filas  = Array.prototype.slice.call(document.querySelectorAll('.exp-fila'));
    var anios  = Array.prototype.slice.call(document.querySelectorAll('.exp-anio'));
    var search = document.getElementById('exp-search-input');
    var empty  = document.getElementById('exp-empty-filter');
    var tabla  = document.querySelector('.exp-tabla-wrap');
    if (!filas.length) return;

    var filtro = 'todos';

    function aplicar() {
        var q = (search && search.value || '').trim().toLowerCase();
        var visibles = 0;
        filas.forEach(function (f) {
            var okEstado = (filtro === 'todos' || f.getAttribute('data-estado') === filtro);
            var okBusca  = (!q || (f.getAttribute('data-search') || '').indexOf(q) !== -1);
            var show = okEstado && okBusca;
            f.style.display = show ? '' : 'none';
            if (show) visibles++;
        });

        /* Un año sin filas visibles no debe dejar su separador suelto. */
        anios.forEach(function (a) {
            var hay = false;
            var n = a.nextElementSibling;
            while (n && !n.classList.contains('exp-anio')) {
                if (n.classList.contains('exp-fila') && n.style.display !== 'none') { hay = true; break; }
                n = n.nextElementSibling;
            }
            a.style.display = hay ? '' : 'none';
        });

        if (empty) empty.style.display = (visibles === 0) ? '' : 'none';
        if (tabla) tabla.style.display = (visibles === 0) ? 'none' : '';
    }

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            tabs.forEach(function (t) { t.classList.remove('is-active'); });
            tab.classList.add('is-active');
            filtro = tab.getAttribute('data-filter');
            aplicar();
        });
    });

    if (search) search.addEventListener('input', aplicar);
})();
</script>
<script>
(function () {
    var modal = document.getElementById('eco-modal-informe-detalle-eco');
    if (!modal || !window.EcoModal) return;
    var iconEl   = document.getElementById('eco-inf-det-icon');
    var tituloEl = document.getElementById('eco-inf-det-titulo');
    var pacEl    = document.getElementById('eco-inf-det-paciente');
    var bodyEl   = document.getElementById('eco-informe-detalle-body');
    var printBtn = document.getElementById('eco-inf-det-print');
    var currentId = null;

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function render(data) {
        if (data.error) {
            bodyEl.innerHTML = '<p style="color:#c0392b;padding:20px;">' + esc(data.error) + '</p>';
            iconEl.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i>';
            return;
        }
        var inf = data.informe || {}, tipo = data.tipo || {}, pac = data.paciente || {};
        iconEl.innerHTML = '<i class="' + esc(tipo.icono || 'fa-solid fa-wave-square') + '"></i>';
        tituloEl.textContent = tipo.nombre || 'Informe de estudio';
        var edad = pac.edad ? (String(pac.edad).trim() + ' años') : '';
        pacEl.textContent = 'Paciente: ' + (pac.nombre || '—') + '  ·  CI: ' + (pac.cedula || '—') + '  ·  ' + (edad || '—');

        var estado = inf.estado || '';
        var colors = { finalizado: ['#166534', '#dcfce7'], firmado: ['#075985', '#e0f2fe'] };
        var ec = colors[estado] || ['#374151', '#f3f4f6'];
        var badge = '<span style="background:' + ec[1] + ';color:' + ec[0] + ';padding:2px 10px;border-radius:12px;font-weight:600;font-size:11px;">' +
            esc(inf.estado_label || estado) + '</span>';

        var meta = '<div class="inf-det-meta">' +
            '<span><i class="fa-solid fa-hashtag"></i> <strong>' + esc(inf.numero_informe || '-') + '</strong></span>' +
            '<span><i class="fa-regular fa-calendar"></i> <strong>' + esc(inf.fecha_formateada || '-') + '</strong></span>' +
            '<span><i class="fa-solid fa-user-doctor"></i> <strong>' + esc(data.ecografista || '-') + '</strong></span>' +
            '<span>' + badge + '</span></div>';

        var firma = '';
        if (inf.firma) {
            firma = '<div style="margin:8px 0 4px;padding:8px 12px;border-radius:8px;background:#e0f2fe;color:#075985;font-size:12.5px;">' +
                '<i class="fa-solid fa-signature"></i> Firmado por <strong>' + esc(inf.firma.por) + '</strong>' +
                (inf.firma.fecha ? ' · ' + esc(inf.firma.fecha) : '') + '</div>';
        }
        bodyEl.innerHTML = meta + firma + (data.html || '');
    }

    // Imprimir sin salir del modal: carga la versión imprimible en un iframe oculto que auto-llama window.print().
    if (printBtn) printBtn.addEventListener('click', function () {
        if (!currentId) return;
        var prev = document.getElementById('inf-print-frame');
        if (prev) prev.remove();
        var iframe = document.createElement('iframe');
        iframe.id = 'inf-print-frame';
        iframe.setAttribute('aria-hidden', 'true');
        iframe.style.cssText = 'position:fixed;left:-10000px;top:0;width:8.5in;height:11in;border:0;visibility:hidden;';
        iframe.src = '<?= eco_url('informe') ?>/' + encodeURIComponent(currentId) + '?print=1';
        document.body.appendChild(iframe);
        setTimeout(function () { try { iframe.remove(); } catch (e) {} }, 60000);
    });

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.exp-btn[data-informe-id]');
        if (!btn) return;
        e.preventDefault();
        var id = btn.getAttribute('data-informe-id');
        currentId = id;
        iconEl.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
        tituloEl.textContent = 'Cargando…';
        pacEl.textContent = '';
        bodyEl.innerHTML = '<div class="modal-form-eco-loader"><i class="fa-solid fa-spinner fa-spin"></i><p>Cargando informe…</p></div>';
        EcoModal.open('eco-modal-informe-detalle-eco');
        fetch((window.ECO_BASE || '') + 'api/get_informe_detalle.php?informe_id=' + encodeURIComponent(id))
            .then(function (r) { return r.json(); })
            .then(render)
            .catch(function (err) {
                bodyEl.innerHTML = '<p style="color:#c0392b;padding:20px;">Error al cargar: ' +
                    esc(err && err.message ? err.message : 'Error de red.') + '</p>';
                iconEl.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i>';
            });
    });
})();
</script>
HTML;

include __DIR__ . '/../layouts/shell.php';
