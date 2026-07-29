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

$nombre_paciente = $_SESSION['nombre_completo'] ?? 'Paciente';
$pre_ecografista_id = isset($_GET['ecografista_id']) ? (int)$_GET['ecografista_id'] : 0;

$msg_err = '';
if (isset($_GET['error'])) {
    $msg_err = match ($_GET['error']) {
        'faltan_datos'   => 'Completa todos los campos obligatorios antes de enviar.',
        'error_guardar'  => 'No se pudo registrar la solicitud. Intenta de nuevo o contacta a recepción.',
        default          => 'Ocurrió un error al procesar tu solicitud.',
    };
}

/* Precio (USD) por estudio según su nombre */
function precio_estudio(string $nombre): int
{
    $n = mb_strtolower($nombre);
    if (strpos($n, 'abdominal') !== false && strpos($n, 'renal') !== false) return 20;
    if (strpos($n, 'abdominal') !== false) return 15;
    if (strpos($n, 'renal') !== false) return 15;
    if (strpos($n, 'pelvic') !== false || strpos($n, 'pélvic') !== false) return 15;
    if (strpos($n, 'musculo') !== false) return 20;
    if (strpos($n, 'prostat') !== false) return 15;
    if (strpos($n, 'mamar') !== false) return 15;
    if (strpos($n, 'tiroid') !== false) return 15;
    if (strpos($n, 'obstetr') !== false) return 15;
    if (strpos($n, 'partes blandas') !== false) return 15;
    if (strpos($n, 'testicular') !== false) return 20;
    if (strpos($n, 'cuello') !== false) return 15;
    if (strpos($n, 'transvaginal') !== false) return 20;
    if (strpos($n, 'pulmonar') !== false) return 20;
    return 15;
}

/* Tipos de ecografía (principales) para el selector visual */
$tipos_eco = [];
$res_tipos = $conex->query("SELECT id, nombre, categoria, descripcion, icono
    FROM tipos_ecografias
    WHERE activo = 1 AND (categoria IS NULL OR categoria NOT IN ('Musculoesqueletica_Sub','Obstetrica_Sub','Partes_Blandas_Sub'))
    ORDER BY categoria, posicion, nombre");
if ($res_tipos) {
    while ($t = $res_tipos->fetch_assoc()) { $tipos_eco[] = $t; }
}

/* Servicios adicionales combinables (sin ecografía).
   Salen del catálogo de precios_servicios, igual que en los modales de
   recepción y ecografista: estaban escritos a mano aquí con sus importes, así
   que al cambiar un precio en "Control de precios" el paciente seguía viendo
   el viejo. */
require_once __DIR__ . '/../lib/facturacion/facturacion.php';
$adicionales = eco_servicios_adicionales();

$page_title    = 'Solicitar cita';
$page_subtitle = 'Elige el servicio, el ecografista, la fecha y la hora disponibles';
$active_section = 'solicitar';

$page_head_extra = '
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
';

ob_start();
?>

<style>
.sol-stepper { display:flex; align-items:flex-start; margin:0 0 22px; }
.sol-step { display:flex; flex-direction:column; align-items:center; gap:8px; flex:0 0 auto; width:96px; }
.sol-step__dot { width:40px; height:40px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:15px; font-weight:700; background:var(--bg-muted); color:var(--text-muted); border:2px solid var(--border); transition:all .25s ease; }
.sol-step__label { font-size:12px; font-weight:600; color:var(--text-muted); text-align:center; transition:color .25s ease; }
.sol-step-line { flex:1; height:2px; background:var(--border); margin-top:19px; border-radius:2px; transition:background .25s ease; }
.sol-step.is-current .sol-step__dot { background:var(--accent-soft); color:var(--accent-text); border-color:var(--accent); box-shadow:0 0 0 4px rgba(2,177,244,.14); }
.sol-step.is-current .sol-step__label { color:var(--accent-text); }
.sol-step.is-done .sol-step__dot { background:var(--accent); color:#fff; border-color:var(--accent); }
.sol-step.is-done .sol-step__label { color:var(--text-primary); }
.sol-step-line.is-done { background:var(--accent); }

.sol-layout { display:grid; grid-template-columns:1fr; gap:18px; align-items:stretch; }
@media (min-width:980px){ .sol-layout { grid-template-columns:minmax(0,1fr) 320px; } }
.sol-aside { display:flex; flex-direction:column; min-width:0; }

.sol-card { background:var(--bg-surface); border:1px solid var(--border); border-radius:var(--radius-lg); padding:20px 22px; margin-bottom:16px; }
.sol-card__head { display:flex; align-items:center; gap:13px; margin-bottom:18px; }
.sol-card__num { width:32px; height:32px; border-radius:9px; flex-shrink:0; background:var(--accent-soft); color:var(--accent-text); font-weight:700; font-size:14px; display:flex; align-items:center; justify-content:center; }
.sol-card__title { font-size:15px; font-weight:700; color:var(--text-primary); margin:0; }
.sol-card__sub { font-size:12.5px; color:var(--text-muted); margin:2px 0 0; }

.sol-field { margin-bottom:16px; }
.sol-field:last-child { margin-bottom:0; }
.sol-field > label { display:block; font-size:12.5px; font-weight:600; color:var(--text-secondary); margin-bottom:7px; }
#form-solicitar-cita select, #form-solicitar-cita textarea, #form-solicitar-cita input[type=text] { width:100%; padding:10px 12px; border:1.5px solid var(--border); border-radius:9px; font-family:inherit; font-size:13.5px; background:var(--bg-surface); color:var(--text-primary); box-sizing:border-box; transition:border-color .18s ease, box-shadow .18s ease; }
#form-solicitar-cita select:focus, #form-solicitar-cita textarea:focus, #form-solicitar-cita input[type=text]:focus { outline:none; border-color:var(--accent); box-shadow:0 0 0 3px rgba(2,177,244,.12); }

/* Tarjetas de servicio (adicionales) */
.sol-serv-grid { display:grid; gap:12px; grid-template-columns:repeat(auto-fill,minmax(172px,1fr)); }
.sol-serv { position:relative; cursor:pointer; margin:0; }
.sol-serv input { position:absolute; opacity:0; width:0; height:0; }
.sol-serv__box { position:relative; height:100%; display:flex; flex-direction:column; gap:9px; padding:15px 14px 16px; border:1.5px solid var(--border); border-radius:14px; background:var(--bg-surface); overflow:hidden; transition:transform .2s ease, border-color .2s ease, box-shadow .2s ease, background .2s ease; }
.sol-serv__box::before { content:''; position:absolute; inset:0 0 auto; height:3px; background:linear-gradient(90deg,var(--accent),#38bdf8); transform:scaleX(0); transform-origin:left; transition:transform .25s ease; }
.sol-serv:hover .sol-serv__box { border-color:var(--accent); box-shadow:0 8px 20px rgba(2,177,244,.13); transform:translateY(-3px); }
.sol-serv input:checked + .sol-serv__box { border-color:var(--accent); background:var(--accent-soft); box-shadow:0 0 0 3px rgba(2,177,244,.14); }
.sol-serv input:checked + .sol-serv__box::before { transform:scaleX(1); }
/* Mismo tratamiento plano que los estudios: al marcar, el icono se llena. */
.sol-serv__icon { width:40px; height:40px; border-radius:11px; display:flex; align-items:center; justify-content:center; font-size:16px; color:var(--accent-text); background:var(--accent-soft); transition:background .2s ease, color .2s ease; }
.sol-serv input:checked + .sol-serv__box .sol-serv__icon { background:var(--accent); color:#fff; }
.sol-serv__label { font-size:13px; font-weight:600; color:var(--text-primary); line-height:1.3; padding-right:38px; }
.sol-serv__price { position:absolute; top:12px; right:12px; font-size:12px; font-weight:800; color:#15803d; background:rgba(34,197,94,.12); padding:2px 8px; border-radius:999px; }
.sol-serv__check { position:absolute; bottom:11px; right:12px; font-size:15px; color:var(--accent); opacity:0; transform:scale(.6); transition:opacity .2s ease, transform .2s ease; }
.sol-serv input:checked + .sol-serv__box .sol-serv__check { opacity:1; transform:scale(1); }

/* Controles segmentados */
.sol-segment { display:flex; gap:8px; flex-wrap:wrap; }
.sol-seg-opt { flex:1; min-width:150px; cursor:pointer; margin:0; }
.sol-seg-opt input { position:absolute; opacity:0; width:0; height:0; }
.sol-seg-opt > span { display:flex; align-items:center; justify-content:center; gap:9px; padding:12px 14px; border:1.5px solid var(--border); border-radius:10px; font-size:13px; font-weight:600; color:var(--text-secondary); transition:all .18s ease; }
.sol-seg-opt:hover > span { border-color:rgba(2,177,244,.4); color:var(--text-primary); }
.sol-seg-opt input:checked + span { border-color:var(--accent); background:var(--accent-soft); color:var(--accent-text); box-shadow:0 0 0 3px rgba(2,177,244,.12); }

/* Grilla de estudios */
.eco-type-grid { display:grid; gap:12px; grid-template-columns:repeat(auto-fill,minmax(185px,1fr)); }
.eco-type-card { position:relative; overflow:hidden; display:flex; flex-direction:column; align-items:flex-start; gap:9px; padding:15px 14px 16px; border:1.5px solid var(--border); border-radius:14px; background:var(--bg-surface); cursor:pointer; transition:transform .2s ease, border-color .2s ease, box-shadow .2s ease, background .2s ease; }
.eco-type-card::before { content:''; position:absolute; inset:0 0 auto; height:3px; background:linear-gradient(90deg,var(--accent),#38bdf8); transform:scaleX(0); transform-origin:left; transition:transform .25s ease; }
.eco-type-card:hover { border-color:var(--accent); box-shadow:0 8px 20px rgba(2,177,244,.14); transform:translateY(-3px); }
.eco-type-card.selected { border-color:var(--accent); background:var(--accent-soft); box-shadow:0 0 0 3px rgba(2,177,244,.14); }
.eco-type-card.selected::before { transform:scaleX(1); }
/* Plano y en tono suave: el bloque de color macizo con sombra pesaba más que
   el nombre del estudio, que es lo que se lee. Al elegir la tarjeta el icono
   sí se llena, y ahí el contraste marca la selección. */
.eco-type-card__icon { width:40px; height:40px; border-radius:11px; display:flex; align-items:center; justify-content:center; font-size:16px; color:var(--accent-text); background:var(--accent-soft); transition:background .2s ease, color .2s ease; }
.eco-type-card.selected .eco-type-card__icon { background:var(--accent); color:#fff; }
.eco-type-card__cat { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.4px; color:var(--accent-text); }
.eco-type-card__name { font-size:13.5px; font-weight:600; color:var(--text-primary); line-height:1.3; padding-right:6px; }
.eco-type-card__price { position:absolute; top:12px; right:12px; font-size:12px; font-weight:800; color:#15803d; background:rgba(34,197,94,.12); padding:2px 8px; border-radius:999px; }
.eco-type-card__check { position:absolute; bottom:11px; right:12px; font-size:15px; color:var(--accent); opacity:0; transform:scale(.6); transition:opacity .2s ease, transform .2s ease; }
.eco-type-card.selected .eco-type-card__check { opacity:1; transform:scale(1); }
.eco-type-card.is-locked { opacity:.42; pointer-events:none; filter:grayscale(.35); }
.sol-serv.is-locked { opacity:.42; }
.sol-serv.is-locked .sol-serv__box { cursor:not-allowed; filter:grayscale(.35); }

.time-slots-grid { display:flex; flex-wrap:wrap; gap:8px; margin-top:8px; }
.time-slot-btn { padding:9px 15px; border-radius:9px; border:1.5px solid var(--border); background:var(--bg-surface); cursor:pointer; font-size:13px; font-family:inherit; color:var(--text-primary); transition:all .18s ease; }
.time-slot-btn:hover { border-color:rgba(2,177,244,.5); }
.time-slot-btn.selected { border-color:var(--accent); background:var(--accent-soft); color:var(--accent-text); font-weight:600; }
.sol-hint { font-size:12px; color:var(--text-muted); margin:8px 0 0; display:flex; align-items:center; gap:7px; }

/* Resumen */
.sol-summary { background:var(--bg-surface); border:1px solid var(--border); border-radius:var(--radius-lg); overflow:hidden; display:flex; flex-direction:column; }
@media (min-width:980px){ .sol-summary { position:sticky; top:18px; flex:1; min-height:0; } }
.sol-summary__head { padding:16px 18px; background:linear-gradient(135deg,var(--accent-soft),var(--bg-surface)); border-bottom:1px solid var(--border); }
.sol-summary__head h3 { margin:0; font-size:14.5px; font-weight:700; color:var(--text-primary); display:flex; align-items:center; gap:9px; }
.sol-summary__head p { margin:4px 0 0; font-size:12px; color:var(--text-secondary); }
.sol-summary__body { padding:8px 18px 14px; flex:1 1 auto; display:flex; flex-direction:column; }
.sol-info { margin-top:14px; padding:12px 13px; border:1px solid var(--border); border-radius:12px; background:var(--bg-muted); }
.sol-info__title { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:var(--text-secondary); display:flex; align-items:center; gap:7px; margin-bottom:9px; }
.sol-info__title i { color:var(--accent); }
.sol-info__list { list-style:none; margin:0; padding:0; display:flex; flex-direction:column; gap:8px; }
.sol-info__list li { position:relative; padding-left:19px; font-size:12px; color:var(--text-secondary); line-height:1.45; }
.sol-info__list li b { color:var(--text-primary); font-weight:800; }
.sol-info__list li::before { content:'\2022'; position:absolute; left:4px; top:0; color:var(--accent); font-weight:900; }
.sol-info__list--check li::before { content:'\2713'; left:3px; color:#15803d; font-weight:900; }
.sol-info--contact { margin-top:auto; background:transparent; border:none; padding:14px 2px 0; display:flex; flex-wrap:wrap; gap:6px 16px; font-size:11.5px; color:var(--text-muted); }
.sol-info--contact i { color:var(--accent); margin-right:5px; }
.sol-sum-row { display:flex; align-items:center; gap:12px; padding:12px 0; border-bottom:1px dashed var(--border); }
.sol-sum-row:last-child { border-bottom:none; }
.sol-sum-icon { width:32px; height:32px; border-radius:9px; flex-shrink:0; background:var(--bg-muted); color:var(--text-muted); display:flex; align-items:center; justify-content:center; font-size:13px; transition:all .2s ease; }
.sol-sum-row.is-filled .sol-sum-icon { background:var(--accent-soft); color:var(--accent-text); }
.sol-sum-text { min-width:0; }
.sol-sum-label { font-size:10.5px; color:var(--text-muted); text-transform:uppercase; letter-spacing:.5px; font-weight:600; }
.sol-sum-value { font-size:13px; font-weight:600; color:var(--text-muted); margin-top:1px; word-break:break-word; }
.sol-sum-row.is-filled .sol-sum-value { color:var(--text-primary); }
.sol-total { display:flex; align-items:center; justify-content:space-between; padding:14px 18px; background:rgba(34,197,94,.08); border-top:1px solid var(--border); }
.sol-total span { font-size:13px; font-weight:700; color:var(--text-secondary); text-transform:uppercase; letter-spacing:.4px; }
.sol-total strong { font-size:24px; font-weight:800; color:#15803d; }
.sol-sum-sub { font-size:11.5px; color:var(--text-muted); font-weight:500; margin-top:2px; line-height:1.45; }
.sol-sum-sub b { color:var(--text-secondary); font-weight:700; }
.sol-ahorro { padding:9px 18px; background:rgba(34,197,94,.1); color:#15803d; font-size:12px; font-weight:700; display:flex; align-items:center; gap:7px; border-top:1px solid var(--border); }
.sol-promo { padding:9px 18px; background:rgba(245,158,11,.12); color:#b45309; font-size:12px; font-weight:700; display:flex; align-items:center; gap:7px; border-top:1px solid var(--border); }
.sol-summary__foot { padding:16px 18px; border-top:1px solid var(--border); }
.sol-summary__foot .btn-primary { width:100%; justify-content:center; }
.sol-summary__note { font-size:11.5px; color:var(--text-muted); margin:12px 0 0; text-align:center; line-height:1.5; }
.sol-summary__note a { color:var(--accent-text); text-decoration:none; font-weight:600; }
</style>

<?php if ($msg_err): ?>
    <div class="card" style="border-left:4px solid var(--danger);background:rgba(239,68,68,.06);margin-bottom:18px;">
        <strong style="color:#b91c1c;"><i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($msg_err) ?></strong>
    </div>
<?php endif; ?>

<form action="<?= eco_url('api/solicitar_cita_directa.php') ?>" method="post" id="form-solicitar-cita" data-preselect-eco="<?= $pre_ecografista_id ?>">
    <?= csrf_field() ?>

    <div class="sol-stepper" id="sol-stepper">
        <div class="sol-step is-current" data-step="0">
            <div class="sol-step__dot"><i class="fa-solid fa-clipboard-list"></i></div>
            <span class="sol-step__label">Servicio</span>
        </div>
        <div class="sol-step-line" data-line="0"></div>
        <div class="sol-step" data-step="1">
            <div class="sol-step__dot"><i class="fa-solid fa-user-doctor"></i></div>
            <span class="sol-step__label">Profesional</span>
        </div>
        <div class="sol-step-line" data-line="1"></div>
        <div class="sol-step" data-step="2">
            <div class="sol-step__dot"><i class="fa-solid fa-circle-check"></i></div>
            <span class="sol-step__label">Confirmar</span>
        </div>
    </div>

    <div class="sol-layout">
        <div class="sol-main">

            <!-- Paso 1 -->
            <div class="sol-card">
                <div class="sol-card__head">
                    <div class="sol-card__num">1</div>
                    <div>
                        <p class="sol-card__title">¿Qué necesitas?</p>
                        <p class="sol-card__sub">Elige el servicio y el detalle de tu estudio</p>
                    </div>
                </div>

                <div class="sol-field">
                    <label>Ecografías <span style="font-weight:500;color:var(--text-muted);">— elige las que necesites (puedes marcar varias)</span></label>
                    <div class="eco-type-grid" id="eco-type-grid">
                        <?php foreach ($tipos_eco as $t): $pr = precio_estudio($t['nombre']); ?>
                            <div class="eco-type-card" role="button" tabindex="0" aria-pressed="false"
                                 data-id="<?= (int)$t['id'] ?>"
                                 data-nombre="<?= htmlspecialchars($t['nombre'], ENT_QUOTES) ?>"
                                 data-precio="<?= $pr ?>">
                                <div class="eco-type-card__icon"><i class="<?= htmlspecialchars($t['icono'] ?: 'fa-solid fa-wave-square', ENT_QUOTES) ?>"></i></div>
                                <span class="eco-type-card__price">$<?= $pr ?></span>
                                <?php if (!empty($t['categoria'])): ?><span class="eco-type-card__cat"><?= htmlspecialchars($t['categoria']) ?></span><?php endif; ?>
                                <span class="eco-type-card__name"><?= htmlspecialchars($t['nombre']) ?></span>
                                <i class="fa-solid fa-circle-check eco-type-card__check"></i>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($tipos_eco)): ?>
                            <p style="color:var(--text-muted);grid-column:1/-1;">No hay tipos de ecografía configurados. Contacta a recepción.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="sol-field">
                    <label>Servicios adicionales <span style="font-weight:500;color:var(--text-muted);">— opcionales, se suman al total</span></label>
                    <div class="sol-serv-grid" id="adic-grid">
                        <?php foreach ($adicionales as $a): ?>
                            <label class="sol-serv">
                                <input type="checkbox" class="adic-input" value="<?= htmlspecialchars($a['key'], ENT_QUOTES) ?>"
                                       data-price="<?= htmlspecialchars((string)(float)$a['price'], ENT_QUOTES) ?>" data-label="<?= htmlspecialchars($a['label'], ENT_QUOTES) ?>">
                                <span class="sol-serv__box">
                                    <span class="sol-serv__price"><?= htmlspecialchars(eco_money((float)$a['price'])) ?></span>
                                    <!-- El icono viene del catálogo con su estilo ya incluido. -->
                                    <span class="sol-serv__icon"><i class="<?= htmlspecialchars($a['icon'] ?: 'fa-solid fa-plus', ENT_QUOTES) ?>"></i></span>
                                    <span class="sol-serv__label"><?= htmlspecialchars($a['label']) ?></span>
                                    <i class="fa-solid fa-circle-check sol-serv__check"></i>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <p class="sol-hint"><i class="fa-solid fa-circle-info"></i> Marca al menos una ecografía o un servicio adicional.</p>
                </div>

                <input type="hidden" name="tipo_ecografia_id" id="tipo_ecografia_id" value="">
                <input type="hidden" name="motivo_principal" id="motivo_principal" value="">
                <input type="hidden" name="modalidad" value="presencial">

                <div class="sol-field" style="margin-top:16px;">
                    <label>Tipo de cita</label>
                    <div class="sol-segment">
                        <label class="sol-seg-opt">
                            <input type="radio" name="tipo_cita" value="primera_consulta" checked>
                            <span><i class="fa-solid fa-star"></i> Primera valoración</span>
                        </label>
                        <label class="sol-seg-opt">
                            <input type="radio" name="tipo_cita" value="seguimiento">
                            <span><i class="fa-solid fa-rotate"></i> Seguimiento</span>
                        </label>
                    </div>
                    <p class="sol-hint"><i class="fa-solid fa-hospital"></i> Todas las atenciones son presenciales en la clínica.</p>
                </div>

                <div class="sol-field" style="margin-top:16px;">
                    <label for="motivo_consulta">Antecedentes médicos y detalles <span style="color:var(--danger);">*</span></label>
                    <textarea name="motivo_consulta" id="motivo_consulta" rows="3" required placeholder="Describe síntomas, estudios previos o lo que indicó tu médico tratante."></textarea>
                </div>
            </div>

            <!-- Paso 2 -->
            <div class="sol-card">
                <div class="sol-card__head">
                    <div class="sol-card__num">2</div>
                    <div>
                        <p class="sol-card__title">Profesional y horario</p>
                        <p class="sol-card__sub">Selecciona al ecografista y un cupo disponible</p>
                    </div>
                </div>

                <div style="display:grid;gap:14px;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));margin-bottom:16px;">
                    <div class="sol-field" style="margin-bottom:0;">
                        <label for="especialidad_selector">Especialidad</label>
                        <select id="especialidad_selector" required>
                            <option value="">Elige una opción</option>
                            <option value="ecografista">Ecografista</option>
                        </select>
                    </div>
                    <div class="sol-field" style="margin-bottom:0;">
                        <label for="psicologo_selector">Ecografista disponible</label>
                        <select name="ecografista_id" id="psicologo_selector" required disabled>
                            <option value="">Primero elige especialidad</option>
                        </select>
                    </div>
                </div>

                <div id="date-picker-group" class="sol-field" style="display:none;margin-top:16px;">
                    <label for="calendario-paciente">Fecha disponible</label>
                    <input type="text" id="calendario-paciente" name="fecha_seleccionada" placeholder="Selecciona una fecha…" readonly style="max-width:280px;">
                </div>

                <div id="time-slots-group" class="sol-field" style="display:none;margin-top:16px;">
                    <label>Horario</label>
                    <div id="time-slots-container" class="time-slots-grid"></div>
                    <input type="hidden" name="hora_seleccionada" id="hora_seleccionada_input" value="">
                </div>

                <p class="sol-hint" id="sol-step2-hint"><i class="fa-solid fa-circle-info"></i> Elige la especialidad para ver los ecografistas y sus cupos.</p>
            </div>

            <!-- Paso 3 -->
            <div class="sol-card">
                <div class="sol-card__head">
                    <div class="sol-card__num">3</div>
                    <div>
                        <p class="sol-card__title">Notas adicionales</p>
                        <p class="sol-card__sub">Opcional — cualquier detalle que debamos saber</p>
                    </div>
                </div>
                <div class="sol-field" style="margin-bottom:0;">
                    <textarea name="notas_paciente" id="notas_paciente" rows="3" placeholder="Preferencia de turno, acompañante, dudas sobre la preparación del estudio, etc."></textarea>
                </div>
            </div>
        </div>

        <!-- Resumen -->
        <aside class="sol-aside">
            <div class="sol-summary">
                <div class="sol-summary__head">
                    <h3><i class="fa-solid fa-clipboard-check" style="color:var(--accent);"></i> Resumen de tu solicitud</h3>
                    <p>Revisa los datos antes de enviar</p>
                </div>
                <div class="sol-summary__body">
                    <div class="sol-sum-row is-filled">
                        <div class="sol-sum-icon"><i class="fa-solid fa-user"></i></div>
                        <div class="sol-sum-text"><div class="sol-sum-label">Paciente</div><div class="sol-sum-value"><?= htmlspecialchars($nombre_paciente) ?></div></div>
                    </div>
                    <div class="sol-sum-row" id="sum-row-estudio">
                        <div class="sol-sum-icon"><i class="fa-solid fa-wave-square"></i></div>
                        <div class="sol-sum-text"><div class="sol-sum-label">Ecografías</div><div class="sol-sum-value" id="sum-estudio">Sin seleccionar</div><div class="sol-sum-sub" id="sum-estudio-sub" style="display:none;"></div></div>
                    </div>
                    <div class="sol-sum-row" id="sum-row-adic">
                        <div class="sol-sum-icon"><i class="fa-solid fa-clipboard-list"></i></div>
                        <div class="sol-sum-text"><div class="sol-sum-label">Servicios adicionales</div><div class="sol-sum-value" id="sum-adic">Ninguno</div><div class="sol-sum-sub" id="sum-adic-sub" style="display:none;"></div></div>
                    </div>
                    <div class="sol-sum-row" id="sum-row-profesional">
                        <div class="sol-sum-icon"><i class="fa-solid fa-user-doctor"></i></div>
                        <div class="sol-sum-text"><div class="sol-sum-label">Ecografista</div><div class="sol-sum-value" id="sum-profesional">Sin seleccionar</div></div>
                    </div>
                    <div class="sol-sum-row" id="sum-row-fecha">
                        <div class="sol-sum-icon"><i class="fa-solid fa-calendar-day"></i></div>
                        <div class="sol-sum-text"><div class="sol-sum-label">Fecha</div><div class="sol-sum-value" id="sum-fecha">Sin seleccionar</div></div>
                    </div>
                    <div class="sol-sum-row" id="sum-row-hora">
                        <div class="sol-sum-icon"><i class="fa-solid fa-clock"></i></div>
                        <div class="sol-sum-text"><div class="sol-sum-label">Hora</div><div class="sol-sum-value" id="sum-hora">Sin seleccionar</div></div>
                    </div>
                    <div class="sol-sum-row is-filled" id="sum-row-tipo">
                        <div class="sol-sum-icon"><i class="fa-solid fa-star"></i></div>
                        <div class="sol-sum-text"><div class="sol-sum-label">Tipo de cita</div><div class="sol-sum-value" id="sum-tipo">Primera valoración</div></div>
                    </div>
                    <div class="sol-sum-row is-filled" id="sum-row-modalidad">
                        <div class="sol-sum-icon"><i class="fa-solid fa-location-dot"></i></div>
                        <div class="sol-sum-text"><div class="sol-sum-label">Modalidad</div><div class="sol-sum-value">Presencial · En la clínica</div></div>
                    </div>
                    <div class="sol-sum-row" id="sum-row-items">
                        <div class="sol-sum-icon"><i class="fa-solid fa-list-check"></i></div>
                        <div class="sol-sum-text"><div class="sol-sum-label">Servicios seleccionados</div><div class="sol-sum-value" id="sum-items">0 servicios</div></div>
                    </div>

                    <div class="sol-info">
                        <div class="sol-info__title"><i class="fa-solid fa-clipboard-list"></i> Antes del estudio</div>
                        <ul class="sol-info__list sol-info__list--check">
                            <li>Sigue la preparación de tu estudio: <b>ayuno</b> o <b>vejiga llena</b> según corresponda.</li>
                            <li>Usa ropa cómoda y evita cremas o lociones en la zona a evaluar.</li>
                        </ul>
                    </div>

                    <div class="sol-info">
                        <div class="sol-info__title"><i class="fa-solid fa-gift"></i> Promociones disponibles</div>
                        <ul class="sol-info__list">
                            <li>Eco + Consulta médica por <b>$25</b></li>
                            <li>Citología + Procesamiento + Eco pélvico por <b>$25</b></li>
                        </ul>
                    </div>

                    <div class="sol-info">
                        <div class="sol-info__title"><i class="fa-solid fa-circle-info"></i> Bueno saber</div>
                        <ul class="sol-info__list sol-info__list--check">
                            <li>Atención 100% presencial en la clínica</li>
                            <li>Llega 10 minutos antes de tu cita</li>
                            <li>Trae tu orden médica si la tienes</li>
                            <li>El pago se realiza en la clínica</li>
                        </ul>
                    </div>

                    <div class="sol-info sol-info--contact">
                        <span><i class="fa-solid fa-location-dot"></i> San Felipe, Edo. Yaracuy</span>
                        <span><i class="fa-solid fa-phone"></i> 0412 851 7770</span>
                    </div>
                </div>
                <div class="sol-total">
                    <span>Total</span>
                    <strong id="sum-total">$0</strong>
                </div>
                <div id="sum-ahorro" class="sol-ahorro" style="display:none;"><i class="fa-solid fa-piggy-bank"></i> <span id="sum-ahorro-txt"></span></div>
                <div id="sum-promo"></div>
                <div class="sol-summary__foot">
                    <button type="submit" class="btn-primary" id="btn-enviar-solicitud" disabled>
                        <i class="fa-solid fa-paper-plane"></i> Completa los pasos
                    </button>
                    <p class="sol-summary__note">Precios referenciales en USD · <a href="<?= eco_url('precios') ?>">ver tarifas</a></p>
                </div>
            </div>
        </aside>

    </div>
</form>

<?php
$page_content = ob_get_clean();

$page_scripts_extra = <<<'HTML'
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
<script>
(function () {
    var form = document.getElementById('form-solicitar-cita');
    var especialidadSelector = document.getElementById('especialidad_selector');
    var psicologoSelector = document.getElementById('psicologo_selector');
    var datePickerGroup = document.getElementById('date-picker-group');
    var calendarInput = document.getElementById('calendario-paciente');
    var timeSlotsGroup = document.getElementById('time-slots-group');
    var timeSlotsContainer = document.getElementById('time-slots-container');
    var btn = document.getElementById('btn-enviar-solicitud');
    var horaInput = document.getElementById('hora_seleccionada_input');
    var tipoIdInput = document.getElementById('tipo_ecografia_id');
    var motivoPrincipalInput = document.getElementById('motivo_principal');
    var notasInput = document.getElementById('notas_paciente');
    var step2Hint = document.getElementById('sol-step2-hint');
    var studyCards = Array.prototype.slice.call(document.querySelectorAll('#eco-type-grid .eco-type-card'));
    var adicInputs = Array.prototype.slice.call(document.querySelectorAll('#adic-grid .adic-input'));
    var pendingEco = (form && parseInt(form.getAttribute('data-preselect-eco'), 10)) || 0;
    var fp = null;

    var seleccion = [];  // estudios [{id,name,price}]
    var adic = [];       // adicionales [{key,label,price}]

    // Combo "Citología + Procesamiento + Eco pélvico" excluye sus componentes sueltos
    var comboCb   = adicInputs.filter(function (i) { return i.value === 'combo_cito'; })[0];
    var comboParts = adicInputs.filter(function (i) { return i.value === 'citologia' || i.value === 'procesamiento'; });
    var pelvCards = studyCards.filter(function (c) { return /p[eé]lvic/i.test(c.getAttribute('data-nombre') || ''); });

    function setSummary(id, value, emptyText) {
        var el = document.getElementById(id); if (!el) return;
        var row = el.closest('.sol-sum-row');
        if (value) { el.textContent = value; if (row) row.classList.add('is-filled'); }
        else { el.textContent = emptyText || 'Sin seleccionar'; if (row) row.classList.remove('is-filled'); }
    }
    function setSub(id, items) {
        var el = document.getElementById(id); if (!el) return;
        if (items && items.length) { el.innerHTML = items.join('<br>'); el.style.display = ''; }
        else { el.innerHTML = ''; el.style.display = 'none'; }
    }
    function setStep(idx, st) { var s = document.querySelector('.sol-step[data-step="' + idx + '"]'); if (!s) return; s.classList.remove('is-current','is-done'); if (st) s.classList.add(st); }
    function setLine(idx, d) { var l = document.querySelector('.sol-step-line[data-line="' + idx + '"]'); if (l) l.classList.toggle('is-done', !!d); }

    function leerEstudios() {
        return studyCards.filter(function (c) { return c.classList.contains('selected'); }).map(function (c) {
            return { id: c.getAttribute('data-id'), name: c.getAttribute('data-nombre'), price: parseInt(c.getAttribute('data-precio'), 10) || 0 };
        });
    }
    function leerAdic() {
        return adicInputs.filter(function (i) { return i.checked; }).map(function (i) {
            /* parseFloat y no parseInt: los precios salen del catálogo y pueden
               llevar decimales; con parseInt un servicio de $15.50 sumaría 15. */
            return { key: i.value, label: i.getAttribute('data-label'), price: parseFloat(i.getAttribute('data-price')) || 0 };
        });
    }
    function calcular() {
        var adicSum = 0, consulta = false, combo = false;
        adic.forEach(function (a) {
            if (a.key === 'consulta') consulta = true;
            else if (a.key === 'combo_cito') combo = true;
            else adicSum += a.price;
        });
        var ecoSum = 0; seleccion.forEach(function (s) { ecoSum += s.price; });

        var total = adicSum, ahorro = 0, promos = [];

        // Combo Citología + Procesamiento + Eco pélvico = $25 (suelto: 20+3+15 = $38)
        if (combo) {
            total += 25; ahorro += 38 - 25;
            promos.push({ label: 'Combo Citología + Procesamiento + Eco pélvico', price: 25 });
        }

        // Promo Eco + Consulta = $25 (la ecografía más cara va con la consulta)
        if (consulta && seleccion.length >= 1) {
            var precios = seleccion.map(function (s) { return s.price; }).sort(function (a, b) { return b - a; });
            var maxEco = precios[0], resto = 0;
            for (var i = 1; i < precios.length; i++) resto += precios[i];
            total += 25 + resto; ahorro += (maxEco + 15) - 25;
            promos.push({ label: 'Promoción Eco + Consulta', price: 25 });
        } else {
            total += ecoSum;
            if (consulta) total += 15;
        }

        return { total: total, ahorro: ahorro, promos: promos };
    }
    function hayComponentes() { return seleccion.length > 0 || adic.length > 0; }

    function lockAdic(cb, locked, motivo) {
        if (!cb) return;
        cb.disabled = locked;
        var box = cb.closest('.sol-serv');
        if (box) { box.classList.toggle('is-locked', locked); box.title = locked ? motivo : ''; }
    }
    function lockCard(card, locked, motivo) {
        card.classList.toggle('is-locked', locked);
        if (locked) { card.setAttribute('aria-disabled', 'true'); card.title = motivo; }
        else { card.removeAttribute('aria-disabled'); card.title = ''; }
    }
    function applyComboLocks() {
        if (!comboCb) return;
        if (comboCb.checked) {
            // Combo activo → desmarca y bloquea sus componentes sueltos
            comboParts.forEach(function (cb) { cb.checked = false; lockAdic(cb, true, 'Ya incluido en el combo seleccionado'); });
            pelvCards.forEach(function (card) {
                card.classList.remove('selected'); card.setAttribute('aria-pressed', 'false');
                lockCard(card, true, 'Ya incluido en el combo seleccionado');
            });
            lockAdic(comboCb, false);
            return;
        }
        // Combo inactivo → libera componentes
        comboParts.forEach(function (cb) { lockAdic(cb, false); });
        pelvCards.forEach(function (card) { lockCard(card, false); });
        // Si algún componente suelto está activo, bloquea el combo
        var anyComp = comboParts.some(function (cb) { return cb.checked; })
            || pelvCards.some(function (c) { return c.classList.contains('selected'); });
        lockAdic(comboCb, anyComp, 'Quita Citología / Procesamiento / Eco pélvica para usar el combo');
    }

    function refresh() {
        applyComboLocks();
        seleccion = leerEstudios();
        adic = leerAdic();

        setSummary('sum-estudio', seleccion.length ? (seleccion.length + (seleccion.length === 1 ? ' estudio' : ' estudios')) : '', 'Sin seleccionar');
        setSummary('sum-adic', adic.length ? (adic.length + (adic.length === 1 ? ' servicio' : ' servicios')) : '', 'Ninguno');

        // Desglose con precios
        setSub('sum-estudio-sub', seleccion.map(function (s) { return s.name + ' — <b>$' + s.price + '</b>'; }));
        setSub('sum-adic-sub', adic.map(function (a) { return a.label + ' — <b>$' + a.price + '</b>'; }));

        // Conteo total de servicios
        var nTotal = seleccion.length + adic.length;
        setSummary('sum-items', nTotal ? (nTotal + (nTotal === 1 ? ' servicio' : ' servicios')) : '', '0 servicios');

        var calc = calcular();
        document.getElementById('sum-total').textContent = '$' + calc.total;

        // Banner(s) de promoción
        var promoEl = document.getElementById('sum-promo');
        if (promoEl) {
            promoEl.innerHTML = calc.promos.map(function (p) {
                return '<div class="sol-promo"><i class="fa-solid fa-gift"></i> ' + p.label + ' aplicada · $' + p.price + '</div>';
            }).join('');
        }

        // Línea de ahorro
        var ahEl = document.getElementById('sum-ahorro'), ahTxt = document.getElementById('sum-ahorro-txt');
        if (ahEl) {
            if (calc.ahorro > 0) { ahEl.style.display = ''; if (ahTxt) ahTxt.textContent = 'Ahorras $' + calc.ahorro + (calc.promos.length > 1 ? ' con las promociones' : ' con la promoción'); }
            else { ahEl.style.display = 'none'; }
        }

        // motivo_principal (lo que ve la clínica)
        var parts = [];
        if (seleccion.length) parts.push('Ecografías: ' + seleccion.map(function (s) { return s.name; }).join(', '));
        if (adic.length) parts.push(adic.map(function (a) { return a.label; }).join(', '));
        var promoTxt = calc.promos.length ? (calc.promos.map(function (p) { return p.label; }).join(' · ') + ' · ') : '';
        motivoPrincipalInput.value = (parts.join(' · ') + (parts.length ? ' · ' : '') + promoTxt + 'Total $' + calc.total).substring(0, 250);
        tipoIdInput.value = seleccion.length ? seleccion[0].id : '';

        var s1 = hayComponentes();
        var s2 = !!horaInput.value;
        setStep(0, s1 ? 'is-done' : 'is-current'); setLine(0, s1);
        setStep(1, s1 ? (s2 ? 'is-done' : 'is-current') : ''); setLine(1, s2);
        setStep(2, s2 ? 'is-current' : '');

        var ok = s1 && s2;
        btn.disabled = !ok;
        btn.innerHTML = ok ? '<i class="fa-solid fa-paper-plane"></i> Enviar solicitud' : '<i class="fa-solid fa-paper-plane"></i> Completa los pasos';
    }

    // Estudios (selección múltiple)
    studyCards.forEach(function (card) {
        function pick() {
            if (card.classList.contains('is-locked')) return;
            card.classList.toggle('selected');
            card.setAttribute('aria-pressed', card.classList.contains('selected') ? 'true' : 'false');
            refresh();
        }
        card.addEventListener('click', pick);
        card.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); pick(); } });
    });

    // Servicios adicionales
    adicInputs.forEach(function (i) { i.addEventListener('change', refresh); });

    // Tipo de cita → resumen
    var tipoRadios = Array.prototype.slice.call(document.querySelectorAll('input[name="tipo_cita"]'));
    function syncTipo() {
        var checked = tipoRadios.filter(function (r) { return r.checked; })[0];
        var lbl = (checked && checked.value === 'seguimiento') ? 'Seguimiento' : 'Primera valoración';
        var el = document.getElementById('sum-tipo'); if (el) el.textContent = lbl;
    }
    tipoRadios.forEach(function (r) { r.addEventListener('change', syncTipo); });
    syncTipo();

    if (!especialidadSelector || !psicologoSelector) { refresh(); return; }

    especialidadSelector.addEventListener('change', function () {
        var rol = this.value;
        psicologoSelector.innerHTML = '<option value="">Cargando…</option>';
        psicologoSelector.disabled = true;
        datePickerGroup.style.display = 'none';
        timeSlotsGroup.style.display = 'none';
        horaInput.value = '';
        setSummary('sum-profesional', ''); setSummary('sum-fecha', ''); setSummary('sum-hora', '');
        if (fp) { fp.destroy(); fp = null; }
        refresh();
        if (!rol) { if (step2Hint) step2Hint.style.display = 'flex'; return; }
        if (step2Hint) step2Hint.style.display = 'none';

        fetch((window.ECO_BASE || '') + 'api/get_professionals_by_specialty.php?rol=' + encodeURIComponent(rol))
            .then(function (r) { return r.json(); })
            .then(function (profs) {
                psicologoSelector.innerHTML = '<option value="">Elige un profesional</option>';
                if (profs.length > 0) {
                    profs.forEach(function (p) { psicologoSelector.innerHTML += '<option value="' + p.id + '">' + p.nombre_completo + '</option>'; });
                    psicologoSelector.disabled = false;
                    if (pendingEco) { psicologoSelector.value = String(pendingEco); pendingEco = 0; if (psicologoSelector.value) psicologoSelector.dispatchEvent(new Event('change')); }
                } else {
                    psicologoSelector.innerHTML = '<option value="">No hay profesionales disponibles</option>';
                }
            });
    });

    psicologoSelector.addEventListener('change', function () {
        var id = this.value;
        var nombre = this.options[this.selectedIndex] ? this.options[this.selectedIndex].text : '';
        datePickerGroup.style.display = 'none';
        timeSlotsGroup.style.display = 'none';
        horaInput.value = '';
        setSummary('sum-profesional', id ? nombre : ''); setSummary('sum-fecha', ''); setSummary('sum-hora', '');
        if (fp) { fp.destroy(); fp = null; }
        refresh();
        if (!id) return;

        fetch((window.ECO_BASE || '') + 'api/get_available_dates.php?ecografista_id=' + encodeURIComponent(id))
            .then(function (r) { return r.json(); })
            .then(function (dates) {
                datePickerGroup.style.display = 'block';
                fp = flatpickr(calendarInput, {
                    locale: (window.flatpickr && flatpickr.l10ns && flatpickr.l10ns.es) ? flatpickr.l10ns.es : undefined,
                    dateFormat: 'Y-m-d', minDate: 'today', enable: dates,
                    onChange: function (sd, dateStr) {
                        horaInput.value = ''; setSummary('sum-hora', '');
                        if (sd[0]) setSummary('sum-fecha', sd[0].toLocaleDateString('es-VE', { weekday: 'long', day: 'numeric', month: 'long' }));
                        refresh();
                        timeSlotsContainer.innerHTML = 'Cargando…';
                        timeSlotsGroup.style.display = 'block';
                        fetch((window.ECO_BASE || '') + 'api/get_available_times.php?ecografista_id=' + encodeURIComponent(id) + '&fecha=' + encodeURIComponent(dateStr))
                            .then(function (res) { return res.json(); })
                            .then(function (times) {
                                timeSlotsContainer.innerHTML = '';
                                if (times.length > 0) {
                                    times.forEach(function (time) {
                                        var b = document.createElement('button');
                                        b.type = 'button'; b.className = 'time-slot-btn';
                                        var label = new Date('1970-01-01T' + time).toLocaleTimeString('es-VE', { hour: 'numeric', minute: '2-digit', hour12: true });
                                        b.textContent = label;
                                        b.addEventListener('click', function () {
                                            document.querySelectorAll('.time-slot-btn').forEach(function (x) { x.classList.remove('selected'); });
                                            b.classList.add('selected'); horaInput.value = time; setSummary('sum-hora', label); refresh();
                                        });
                                        timeSlotsContainer.appendChild(b);
                                    });
                                } else {
                                    timeSlotsContainer.innerHTML = '<p style="color:var(--text-muted);">No hay horarios para este día.</p>';
                                }
                            });
                    }
                });
            });
    });

    if (pendingEco) { especialidadSelector.value = 'ecografista'; especialidadSelector.dispatchEvent(new Event('change')); }
    refresh();
})();
</script>
HTML;

include __DIR__ . '/../layouts/shell.php';
