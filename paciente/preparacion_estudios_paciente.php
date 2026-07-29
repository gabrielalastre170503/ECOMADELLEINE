<?php
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

/* Metadatos por tipo de requisito de preparación */
$req_meta = [
    'ayuno'   => ['Ayuno 6–8 h',   '#b45309', 'rgba(245,158,11,.14)', 'fa-solid fa-utensils'],
    'vejiga'  => ['Vejiga llena',  '#0369a1', 'rgba(14,165,233,.14)', 'fa-solid fa-glass-water'],
    'ninguna' => ['Sin preparación','#15803d','rgba(34,197,94,.14)',   'fa-solid fa-circle-check'],
];

/* Guía de preparación según la categoría del estudio */
$prep_map = [
    'Abdominal' => [
        'req'  => 'ayuno',
        'prep' => 'Ayuno de 6 a 8 horas: no comas ni tomes bebidas con gas antes del estudio. Puedes beber agua y tomar tu medicación habitual.',
        'tips' => ['Evita fumar antes del estudio', 'Trae estudios o análisis previos si los tienes'],
    ],
    'Renal' => [
        'req'  => 'vejiga',
        'prep' => 'Llega con la vejiga llena: bebe 4 a 6 vasos de agua una hora antes y no orines hasta finalizar el estudio.',
        'tips' => ['Llega con tiempo para mantener la vejiga llena', 'No es necesario ayuno'],
    ],
    'Cervical' => [
        'req'  => 'ninguna',
        'prep' => 'No requiere preparación. Acude sin cremas en el cuello y, de ser posible, evita collares o cadenas.',
        'tips' => ['Comenta si tienes nódulos o molestias en la zona'],
    ],
    'Mamaria' => [
        'req'  => 'ninguna',
        'prep' => 'No requiere preparación. El día del estudio evita cremas, talcos o desodorante en la zona a evaluar.',
        'tips' => ['Usa ropa cómoda de dos piezas', 'Trae mamografías o ecografías previas'],
    ],
    'Musculoesqueletica' => [
        'req'  => 'ninguna',
        'prep' => 'No requiere preparación. Usa ropa cómoda que permita exponer fácilmente la zona a evaluar.',
        'tips' => ['Indica al ecografista la zona exacta de molestia'],
    ],
    'Obstetrica' => [
        'req'  => 'vejiga',
        'prep' => 'Primer trimestre: acude con la vejiga llena (bebe agua antes). Segundo y tercer trimestre: no requiere preparación especial.',
        'tips' => ['Trae tus controles y estudios previos', 'Usa ropa cómoda de dos piezas'],
    ],
    'Partes Blandas' => [
        'req'  => 'ninguna',
        'prep' => 'No requiere preparación. Informa al ecografista la ubicación exacta del bulto o la molestia.',
        'tips' => ['Evita cremas en la zona el día del estudio'],
    ],
    'Pelvica' => [
        'req'  => 'vejiga',
        'prep' => 'Llega con la vejiga llena: bebe 4 a 6 vasos de agua una hora antes y no orines hasta el estudio.',
        'tips' => ['Llega con tiempo para mantener la vejiga llena'],
    ],
    'Prostatica' => [
        'req'  => 'vejiga',
        'prep' => 'Llega con la vejiga llena: bebe 4 a 6 vasos de agua una hora antes del estudio.',
        'tips' => ['Sigue las indicaciones específicas de tu médico tratante'],
    ],
    'Pulmonar' => [
        'req'  => 'ninguna',
        'prep' => 'No requiere preparación. Acude con ropa cómoda que facilite el acceso al tórax.',
        'tips' => ['Comenta tus síntomas respiratorios al ecografista'],
    ],
    'Testicular' => [
        'req'  => 'ninguna',
        'prep' => 'No requiere preparación especial. Sigue las indicaciones de tu médico tratante.',
        'tips' => ['Informa cualquier dolor o inflamación en la zona'],
    ],
];

$prep_default = [
    'req'  => 'ninguna',
    'prep' => 'Consulta con recepción la preparación específica para este estudio.',
    'tips' => [],
];

/* Tipos de ecografía activos (los que el paciente puede agendar) */
$tipos = [];
$res = $conex->query("SELECT id, nombre, categoria, icono
    FROM tipos_ecografias
    WHERE activo = 1 AND (categoria IS NULL OR categoria NOT IN ('Musculoesqueletica_Sub','Obstetrica_Sub','Partes_Blandas_Sub'))
    ORDER BY categoria, posicion, nombre");
if ($res) {
    while ($t = $res->fetch_assoc()) { $tipos[] = $t; }
    $res->free();
}

/* Conteos por requisito para los chips de filtro */
$conteo = ['ayuno' => 0, 'vejiga' => 0, 'ninguna' => 0];
foreach ($tipos as $t) {
    $req = ($prep_map[$t['categoria']] ?? $prep_default)['req'];
    if (isset($conteo[$req])) $conteo[$req]++;
}
$total_tipos = count($tipos);

$page_title     = 'Preparación de Estudios';
$page_subtitle  = 'Cómo prepararte para cada tipo de ecografía';
$active_section = 'preparacion';

ob_start();
?>

<style>
.prep-tabs { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:18px; }
.prep-tab {
    display:inline-flex; align-items:center; gap:7px;
    padding:9px 15px; border-radius:999px; font-size:12.5px; font-weight:600;
    color:var(--text-secondary); background:var(--bg-surface);
    border:1px solid var(--border); cursor:pointer; transition:all .18s ease; white-space:nowrap;
}
.prep-tab:hover { color:var(--text-primary); border-color:rgba(2,177,244,.35); }
.prep-tab.is-active { background:var(--accent); color:#fff; border-color:var(--accent); box-shadow:0 4px 12px rgba(2,177,244,.28); }
.prep-tab-count { font-size:11px; font-weight:700; padding:1px 7px; border-radius:999px; background:var(--bg-muted); color:var(--text-secondary); }
.prep-tab.is-active .prep-tab-count { background:rgba(255,255,255,.22); color:#fff; }

.prep-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(300px,1fr)); gap:16px; }
.prep-card {
    background:var(--bg-surface); border:1px solid var(--border); border-radius:var(--radius-lg);
    padding:18px; display:flex; flex-direction:column; gap:12px;
    transition:box-shadow .2s ease, transform .2s ease, border-color .2s ease;
}
.prep-card:hover { box-shadow:var(--shadow); transform:translateY(-3px); border-color:rgba(2,177,244,.25); }
.prep-card__top { display:flex; align-items:center; gap:12px; }
.prep-card__icon {
    width:44px; height:44px; border-radius:12px; flex-shrink:0;
    display:flex; align-items:center; justify-content:center; font-size:18px;
}
.prep-card__name { font-size:14.5px; font-weight:700; color:var(--text-primary); line-height:1.3; margin:0; }
.prep-badge {
    display:inline-flex; align-items:center; gap:6px; align-self:flex-start;
    padding:4px 11px; border-radius:999px; font-size:11px; font-weight:700;
}
.prep-card__prep { font-size:13px; color:var(--text-secondary); line-height:1.55; margin:0; }
.prep-card__tips { margin:0; padding-left:18px; font-size:12px; color:var(--text-muted); line-height:1.7; }
.prep-card__tips li::marker { color:var(--accent); }

.prep-empty { grid-column:1/-1; text-align:center; padding:36px 20px; color:var(--text-muted); }
.prep-empty > i { font-size:38px; color:var(--accent); opacity:.5; margin-bottom:12px; display:block; }
</style>

<div class="card" style="margin-bottom:18px;">
    <div style="display:flex;align-items:flex-start;gap:14px;">
        <span style="width:44px;height:44px;border-radius:12px;background:var(--accent);color:#fff;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;"><i class="fa-solid fa-circle-info"></i></span>
        <div>
            <h2 style="margin:0 0 4px;font-size:17px;font-weight:700;color:var(--text-primary);">Antes de tu estudio</h2>
            <p style="margin:0;font-size:13.5px;color:var(--text-secondary);line-height:1.5;">Aquí encuentras la preparación de los <strong><?= $total_tipos ?></strong> tipos de ecografía disponibles. Sigue estas recomendaciones para que tu estudio sea preciso. Ante cualquier duda, consulta con tu ecografista o con recepción.</p>
        </div>
    </div>
</div>

<div class="prep-tabs">
    <button type="button" class="prep-tab is-active" data-filter="todas">Todas <span class="prep-tab-count"><?= $total_tipos ?></span></button>
    <button type="button" class="prep-tab" data-filter="ayuno"><i class="fa-solid fa-utensils"></i> Requiere ayuno <span class="prep-tab-count"><?= $conteo['ayuno'] ?></span></button>
    <button type="button" class="prep-tab" data-filter="vejiga"><i class="fa-solid fa-glass-water"></i> Vejiga llena <span class="prep-tab-count"><?= $conteo['vejiga'] ?></span></button>
    <button type="button" class="prep-tab" data-filter="ninguna"><i class="fa-solid fa-circle-check"></i> Sin preparación <span class="prep-tab-count"><?= $conteo['ninguna'] ?></span></button>
</div>

<div class="prep-grid" id="prep-grid">
    <?php foreach ($tipos as $t):
        $g    = $prep_map[$t['categoria']] ?? $prep_default;
        $req  = $g['req'];
        $rm   = $req_meta[$req] ?? $req_meta['ninguna'];
        [$req_label, $req_color, $req_bg, $req_icon] = $rm;
        $icono = $t['icono'] ?: 'fa-solid fa-wave-square';
    ?>
        <div class="prep-card" data-req="<?= htmlspecialchars($req) ?>">
            <div class="prep-card__top">
                <span class="prep-card__icon" style="background:<?= $req_bg ?>;color:<?= $req_color ?>;"><i class="<?= htmlspecialchars($icono, ENT_QUOTES) ?>"></i></span>
                <h3 class="prep-card__name"><?= htmlspecialchars($t['nombre']) ?></h3>
            </div>
            <span class="prep-badge" style="background:<?= $req_bg ?>;color:<?= $req_color ?>;"><i class="<?= $req_icon ?>"></i> <?= htmlspecialchars($req_label) ?></span>
            <p class="prep-card__prep"><?= htmlspecialchars($g['prep']) ?></p>
            <?php if (!empty($g['tips'])): ?>
                <ul class="prep-card__tips">
                    <?php foreach ($g['tips'] as $tip): ?>
                        <li><?= htmlspecialchars($tip) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <?php if (empty($tipos)): ?>
        <div class="prep-empty">
            <i class="fa-solid fa-clipboard-list"></i>
            <p style="margin:0;font-weight:600;color:var(--text-secondary);">No hay tipos de estudio configurados</p>
        </div>
    <?php endif; ?>

    <div id="prep-empty-filter" class="prep-empty" style="display:none;">
        <i class="fa-solid fa-clipboard-list"></i>
        <p style="margin:0;font-weight:600;color:var(--text-secondary);">No hay estudios en esta categoría</p>
    </div>
</div>

<div class="card" style="margin-top:18px;text-align:center;">
    <p style="margin:0 0 12px;font-size:13.5px;color:var(--text-secondary);">¿Listo para agendar tu estudio?</p>
    <a href="<?= eco_url('solicitar-cita') ?>" class="btn-primary"><i class="fa-solid fa-file-circle-plus"></i> Solicitar nueva cita</a>
</div>

<?php
$page_content = ob_get_clean();

$page_scripts_extra = <<<'HTML'
<script>
(function () {
    var tabs  = document.querySelectorAll('.prep-tab');
    var cards = Array.prototype.slice.call(document.querySelectorAll('.prep-card'));
    var empty = document.getElementById('prep-empty-filter');
    if (!cards.length) return;

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            tabs.forEach(function (t) { t.classList.remove('is-active'); });
            tab.classList.add('is-active');
            var f = tab.getAttribute('data-filter');
            var visibles = 0;
            cards.forEach(function (c) {
                var show = (f === 'todas' || c.getAttribute('data-req') === f);
                c.style.display = show ? '' : 'none';
                if (show) visibles++;
            });
            if (empty) empty.style.display = (visibles === 0) ? '' : 'none';
        });
    });
})();
</script>
HTML;

include __DIR__ . '/../layouts/shell.php';
