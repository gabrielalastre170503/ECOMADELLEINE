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

/* Precios reales desde la BD (gestionados por el administrador), por codigo estable. */
$precios_db = [];
if ($rp = $conex->query("SELECT codigo, precio FROM tipos_ecografias")) {
    while ($row = $rp->fetch_assoc()) {
        $precios_db[$row['codigo']] = (float)$row['precio'];
    }
    $rp->free();
}
$precio_de = static function (string $codigo, float $fallback) use ($precios_db): float {
    return isset($precios_db[$codigo]) && $precios_db[$codigo] > 0 ? $precios_db[$codigo] : $fallback;
};

/* Catálogo de estudios: icono, color, nombre, codigo (precio desde BD), precio_fallback, sinopsis */
$catalogo = [
    ['fa-solid fa-droplet',          '#02b1f4', 'Ecografía Abdominal / Renal',        'ECO_ABD_REN',        20, 'Evalúa en un mismo estudio los órganos del abdomen (hígado, vesícula, páncreas y bazo) junto con los riñones y las vías urinarias.'],
    ['fa-solid fa-wave-square',      '#0284c7', 'Ecografía Abdominal',                'eco_abdominal',      15, 'Estudio de los órganos abdominales —hígado, vesícula y vías biliares, páncreas y bazo— para detectar cálculos, quistes o inflamación.'],
    ['fa-solid fa-droplet',          '#14b8a6', 'Ecografía Renal',                    'ECO_RENAL',          15, 'Valora los riñones y las vías urinarias; útil para detectar cálculos, quistes o alteraciones del flujo urinario.'],
    ['fa-solid fa-venus',            '#8b5cf6', 'Ecografía Pélvica',                  'ECO_PELVICA',        15, 'Examina los órganos de la pelvis (útero, ovarios y vejiga) para evaluar dolor pélvico, quistes o sangrados.'],
    ['fa-solid fa-bone',             '#22c55e', 'Ecografía Musculoesquelética',       'ECO_MUSCU',          20, 'Estudia músculos, tendones, ligamentos y articulaciones; ideal para lesiones deportivas, desgarros o tendinitis.'],
    ['fa-solid fa-mars',             '#3b82f6', 'Ecografía Prostática',               'ECO_PROST',          15, 'Evalúa el tamaño y la estructura de la próstata y la vejiga, apoyando el control urológico.'],
    ['fa-solid fa-venus',            '#ec4899', 'Ecografía Mamaria',                  'ECO_MAMA',           15, 'Estudio de las mamas que complementa la mamografía y ayuda a caracterizar nódulos o quistes.'],
    ['fa-solid fa-shield-halved',    '#14b8a6', 'Ecografía Tiroidea',                'eco_tiroides',       15, 'Analiza la glándula tiroides para detectar nódulos, quistes o cambios de tamaño en el cuello.'],
    ['fa-solid fa-baby',             '#f472b6', 'Ecografía Obstétrica · I Trimestre', 'ECO_OBS_I_TRIM',     15, 'Confirma el embarazo, la vitalidad del bebé y la edad gestacional durante el primer trimestre.'],
    ['fa-solid fa-person-pregnant',  '#ec4899', 'Ecografía Obstétrica · II–III Trim.', 'ECO_OBS_II_III_TRIM', 20, 'Controla el crecimiento, la anatomía y el bienestar del bebé durante el segundo y tercer trimestre.'],
    ['fa-solid fa-hand',             '#8b5cf6', 'Ecografía de Partes Blandas',        'ECO_PBLANCAS',       15, 'Evalúa bultos, quistes o lesiones por debajo de la piel en cualquier región del cuerpo.'],
    ['fa-solid fa-mars',             '#3b82f6', 'Ecografía Testicular',               'ECO_TEST',           20, 'Estudia los testículos y el escroto ante dolor, inflamación o presencia de bultos.'],
    ['fa-solid fa-head-side-virus',  '#0ea5e9', 'Ecografía de Cuello',               'ECO_CUELLO',         15, 'Examen de las estructuras del cuello (ganglios, glándulas y partes blandas) para evaluar masas o inflamación.'],
    ['fa-solid fa-heart-pulse',      '#a855f7', 'Ecografía Transvaginal',            'ECO_TRANSV',         20, 'Estudio ginecológico de alta resolución que evalúa el útero y los ovarios con gran detalle.'],
    ['fa-solid fa-lungs',            '#0ea5e9', 'Ecografía Pulmonar',                'ECO_PULMONAR',       20, 'Técnica altamente sensible y específica que facilita la evaluación de los pulmones y la pleura, útil en cuadros respiratorios.'],
];

/* Consultas y promociones: icono, color, nombre, precio, sinopsis, promo */
$servicios = [
    ['fa-solid fa-user-doctor', '#02b1f4', 'Consulta de Medicina General', 15, 'Evaluación médica integral para adultos y niños: valoración de síntomas, orientación diagnóstica e indicaciones de tratamiento.', false],
    ['fa-solid fa-vial',        '#ec4899', 'Citología + Procesamiento + Eco Pélvico', 25, 'Toma de citología (Papanicolaou) con procesamiento de la muestra, más ecografía pélvica para el control ginecológico.', false],
    ['fa-solid fa-vial',        '#a855f7', 'Solo Citología', 20, 'Toma de citología (Papanicolaou) para el tamizaje y control ginecológico.', false],
    ['fa-solid fa-microscope',  '#0ea5e9', 'Procesamiento de Muestra', 3, 'Procesamiento en laboratorio de la muestra tomada para su análisis.', false],
    ['fa-solid fa-flask-vial',  '#ec4899', 'Citología + Procesamiento de Muestra', 25, 'Toma de citología junto con el procesamiento de la muestra para su análisis.', false],
    ['fa-solid fa-gift',        '#f59e0b', 'Eco + Consulta', 25, 'Paquete promocional que combina una ecografía con la consulta médica a un precio preferencial.', true],
];

$total            = count($catalogo);
$precios_catalogo = array_map(fn($c) => $precio_de($c[3], $c[4]), $catalogo);
$precio_min       = $precios_catalogo ? (int)min($precios_catalogo) : 0;
$precio_max       = $precios_catalogo ? (int)max($precios_catalogo) : 0;

$page_title     = 'Precios de Ecografías';
$page_subtitle  = 'Tipos de estudio, descripción y tarifa referencial';
$active_section = 'precios';

ob_start();
?>

<style>
.pe-search { position:relative; max-width:380px; margin:0 0 18px; }
.pe-search i { position:absolute; left:13px; top:50%; transform:translateY(-50%); color:var(--text-muted); font-size:13px; pointer-events:none; }
.pe-search input { width:100%; padding:11px 13px 11px 38px; border:1.5px solid var(--border); border-radius:11px; font-family:inherit; font-size:14px; background:var(--bg-surface); color:var(--text-primary); box-sizing:border-box; transition:border-color .18s ease, box-shadow .18s ease; }
.pe-search input:focus { outline:none; border-color:var(--accent); box-shadow:0 0 0 3px rgba(2,177,244,.12); }

.pe-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(310px,1fr)); gap:16px; }
.pe-card { background:var(--bg-surface); border:1px solid var(--border); border-radius:var(--radius-lg); padding:18px; display:flex; flex-direction:column; gap:12px; transition:box-shadow .2s ease, transform .2s ease, border-color .2s ease; }
.pe-card:hover { box-shadow:var(--shadow); transform:translateY(-3px); border-color:rgba(2,177,244,.25); }
.pe-card__top { display:flex; align-items:center; gap:13px; }
.pe-card__icon { width:46px; height:46px; border-radius:12px; flex-shrink:0; display:flex; align-items:center; justify-content:center; font-size:19px; }
.pe-card__name { font-size:14.5px; font-weight:700; color:var(--text-primary); line-height:1.3; margin:0; flex:1; min-width:0; }
.pe-card__price { flex-shrink:0; display:inline-flex; align-items:baseline; gap:1px; padding:5px 12px; border-radius:999px; background:rgba(34,197,94,.12); color:#15803d; font-weight:800; font-size:15px; }
.pe-card__price small { font-size:11px; font-weight:700; }
.pe-card__desc { font-size:13px; color:var(--text-secondary); line-height:1.55; margin:0; }

.pe-empty { grid-column:1/-1; text-align:center; padding:40px 20px; color:var(--text-muted); }
.pe-empty > i { font-size:38px; color:var(--accent); opacity:.5; margin-bottom:12px; display:block; }

.pe-section-title { font-size:13px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:var(--text-secondary); margin:26px 0 14px; display:flex; align-items:center; gap:8px; }
.pe-section-title i { color:var(--accent); }
.pe-card--promo { border-color:rgba(245,158,11,.5); background:linear-gradient(135deg,rgba(245,158,11,.08),var(--bg-surface)); }
.pe-badge-promo { align-self:flex-start; display:inline-flex; align-items:center; gap:6px; background:#f59e0b; color:#fff; font-size:10.5px; font-weight:800; text-transform:uppercase; letter-spacing:.5px; padding:4px 11px; border-radius:999px; }
</style>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-card-icon"><i class="fa-solid fa-wave-square"></i></div>
        <p class="stat-card-label">Estudios disponibles</p>
        <p class="stat-card-value accent"><?= $total ?></p>
        <p class="stat-card-sub">tipos de ecografía</p>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon" style="background:rgba(34,197,94,.12);color:#15803d;"><i class="fa-solid fa-tag"></i></div>
        <p class="stat-card-label">Rango de precios</p>
        <p class="stat-card-value" style="color:#15803d;font-size:22px;">$<?= $precio_min ?>–$<?= $precio_max ?></p>
        <p class="stat-card-sub">según el estudio</p>
    </div>
    <a href="<?= eco_url('solicitar-cita') ?>" class="stat-card" style="text-decoration:none;">
        <div class="stat-card-icon"><i class="fa-solid fa-file-circle-plus"></i></div>
        <p class="stat-card-label">Acción rápida</p>
        <p class="stat-card-value accent" style="font-size:18px;">Solicitar cita</p>
        <p class="stat-card-sub">agenda tu estudio</p>
    </a>
</div>

<div class="card" style="margin-bottom:18px;">
    <div style="display:flex;align-items:flex-start;gap:14px;">
        <span style="width:44px;height:44px;border-radius:12px;background:var(--accent);color:#fff;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;"><i class="fa-solid fa-circle-info"></i></span>
        <div>
            <h2 style="margin:0 0 4px;font-size:17px;font-weight:700;color:var(--text-primary);">Tarifas de nuestros estudios</h2>
            <p style="margin:0;font-size:13.5px;color:var(--text-secondary);line-height:1.5;">Precios referenciales en dólares (USD). El monto final puede variar según indicaciones médicas; confírmalo con recepción al agendar.</p>
        </div>
    </div>
</div>

<div class="pe-search">
    <i class="fa-solid fa-magnifying-glass"></i>
    <input type="text" id="pe-search-input" placeholder="Buscar un estudio…" autocomplete="off">
</div>

<div class="pe-grid" id="pe-grid">
    <?php foreach ($catalogo as [$icon, $color, $nombre, $codigo, $precio_fb, $sinopsis]): $precio = $precio_de($codigo, $precio_fb); ?>
        <div class="pe-card" data-search="<?= htmlspecialchars(mb_strtolower($nombre), ENT_QUOTES) ?>">
            <div class="pe-card__top">
                <span class="pe-card__icon" style="background:<?= $color ?>1f;color:<?= $color ?>;"><i class="<?= htmlspecialchars($icon, ENT_QUOTES) ?>"></i></span>
                <h3 class="pe-card__name"><?= htmlspecialchars($nombre) ?></h3>
                <span class="pe-card__price"><small>$</small><?= (int)$precio ?></span>
            </div>
            <p class="pe-card__desc"><?= htmlspecialchars($sinopsis) ?></p>
        </div>
    <?php endforeach; ?>

    <div id="pe-empty-filter" class="pe-empty" style="display:none;">
        <i class="fa-solid fa-magnifying-glass"></i>
        <p style="margin:0;font-weight:600;color:var(--text-secondary);">No se encontró ningún estudio con ese nombre</p>
    </div>
</div>

<p class="pe-section-title"><i class="fa-solid fa-stethoscope"></i> Consultas y promociones</p>
<div class="pe-grid">
    <?php foreach ($servicios as [$icon, $color, $nombre, $precio, $sinopsis, $promo]): ?>
        <div class="pe-card<?= $promo ? ' pe-card--promo' : '' ?>">
            <?php if ($promo): ?><span class="pe-badge-promo"><i class="fa-solid fa-gift"></i> Promoción</span><?php endif; ?>
            <div class="pe-card__top">
                <span class="pe-card__icon" style="background:<?= $color ?>1f;color:<?= $color ?>;"><i class="<?= htmlspecialchars($icon, ENT_QUOTES) ?>"></i></span>
                <h3 class="pe-card__name"><?= htmlspecialchars($nombre) ?></h3>
                <span class="pe-card__price"><small>$</small><?= (int)$precio ?></span>
            </div>
            <p class="pe-card__desc"><?= htmlspecialchars($sinopsis) ?></p>
        </div>
    <?php endforeach; ?>
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
    var search = document.getElementById('pe-search-input');
    var cards  = Array.prototype.slice.call(document.querySelectorAll('.pe-card'));
    var empty  = document.getElementById('pe-empty-filter');
    if (!search) return;
    search.addEventListener('input', function () {
        var q = this.value.trim().toLowerCase();
        var vis = 0;
        cards.forEach(function (c) {
            var show = !q || (c.getAttribute('data-search') || '').indexOf(q) !== -1;
            c.style.display = show ? '' : 'none';
            if (show) vis++;
        });
        if (empty) empty.style.display = (vis === 0) ? '' : 'none';
    });
})();
</script>
HTML;

include __DIR__ . '/../layouts/shell.php';
