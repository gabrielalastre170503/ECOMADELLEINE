<?php
/**
 * Modales shell: gestionar paciente + notas de sesión (ecografista).
 * Requiere sesión; cargado desde vistas ecografista con shell.
 *
 * IDs: eco-modal-gestionar-paciente-eco, eco-modal-notas-paciente-eco
 * API: get_patient_details.php, get_notas_paciente.php, guardar_nota.php, limpiar_notas.php
 */
if (!isset($_SESSION['usuario_id']) || ($_SESSION['rol'] ?? '') !== 'ecografista') {
    return;
}

$eco_tipos_modal = [];
$eco_tipos_musculo = [];
$eco_tipos_obstetrica = [];
$eco_tipos_pblandas = [];
if (isset($conex) && $conex instanceof mysqli) {
    // Catálogo memoizado (lib/informes/catalogo.php): reusa la lectura ya hecha en el request.
    require_once __DIR__ . '/../../lib/informes/catalogo.php';
    $eco_menu_tipos       = eco_catalogo_tipos_menu($conex);
    $eco_tipos_modal      = $eco_menu_tipos['principales'];
    $eco_tipos_musculo    = $eco_menu_tipos['musculo'];
    $eco_tipos_obstetrica = $eco_menu_tipos['obstetrica'];
    $eco_tipos_pblandas   = $eco_menu_tipos['partes_blandas'];
}

// Catalogo de servicios adicionales (consulta, citologia, procesamiento, combo)
// para el selector de facturacion del flujo de informe del ecografista.
require_once __DIR__ . '/../../lib/facturacion/facturacion.php';
$eco_servicios_facturacion = eco_servicios_adicionales();
// En la UI del ecografista el combo no se muestra como tarjeta (fue reemplazado
// por la tarjeta "Ecografías"); el catálogo completo sigue yendo a JS porque
// los cálculos de bundle/promos lo necesitan para citas ya existentes.
$eco_servicios_ui = array_values(array_filter(
    $eco_servicios_facturacion,
    static fn($s) => $s['key'] !== 'combo_cito'
));

// Mismo mapa que panel.php para colores por categoría
$eco_colores_shell = [
    'Abdominal'          => ['bg' => 'linear-gradient(135deg,#02b1f4,#38bdf8)', 'badge' => '#e0f5fe', 'text' => '#0284c7'],
    'Renal'              => ['bg' => 'linear-gradient(135deg,#0ea5e9,#7dd3fc)', 'badge' => '#e0f2fe', 'text' => '#0369a1'],
    'Obstetrica'         => ['bg' => 'linear-gradient(135deg,#ec4899,#f9a8d4)', 'badge' => '#fce7f3', 'text' => '#be185d'],
    'Cervical'           => ['bg' => 'linear-gradient(135deg,#14b8a6,#5eead4)', 'badge' => '#ccfbf1', 'text' => '#0f766e'],
    'Pelvica'            => ['bg' => 'linear-gradient(135deg,#8b5cf6,#c4b5fd)', 'badge' => '#ede9fe', 'text' => '#6d28d9'],
    'Musculoesqueletica' => ['bg' => 'linear-gradient(135deg,#22c55e,#86efac)', 'badge' => '#dcfce7', 'text' => '#15803d'],
    'Prostatica'         => ['bg' => 'linear-gradient(135deg,#3b82f6,#93c5fd)', 'badge' => '#dbeafe', 'text' => '#1d4ed8'],
    'Mamaria'            => ['bg' => 'linear-gradient(135deg,#f43f5e,#fda4af)', 'badge' => '#ffe4e6', 'text' => '#be123c'],
    'Partes Blandas'     => ['bg' => 'linear-gradient(135deg,#f59e0b,#fcd34d)', 'badge' => '#fef3c7', 'text' => '#b45309'],
    'Testicular'         => ['bg' => 'linear-gradient(135deg,#6366f1,#a5b4fc)', 'badge' => '#e0e7ff', 'text' => '#4338ca'],
    'Pulmonar'           => ['bg' => 'linear-gradient(135deg,#0891b2,#22d3ee)', 'badge' => '#cffafe', 'text' => '#0e7490'],
];
$eco_color_default_shell = ['bg' => 'linear-gradient(135deg,#64748b,#94a3b8)', 'badge' => '#f1f5f9', 'text' => '#475569'];

if (!function_exists('eco_estilo_tipo_shell')) {
    /**
     * Icono + color "acorde" a cada ecografía según palabras clave del nombre.
     * Devuelve ['icon','bg','badge','text'] o null si no hay match (entonces se
     * usa el color por categoría + el icono de la BD).
     */
    function eco_estilo_tipo_shell(string $nombre): ?array
    {
        $n = mb_strtolower($nombre, 'UTF-8');
        $n = strtr($n, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'ü' => 'u']);
        // [ [palabras clave...], icono, color1, color2, badgeBg, badgeText ]
        $reglas = [
            [['obstetr', 'embaraz', 'gestac', 'trimestre', 'fetal', 'feto', 'morfolog'], 'fa-solid fa-person-pregnant', '#ec4899', '#f9a8d4', '#fce7f3', '#be185d'],
            [['mama', 'mamaria', 'seno'],                                                 'fa-solid fa-ribbon',          '#f43f5e', '#fda4af', '#ffe4e6', '#be123c'],
            [['tiroid', 'cervic', 'cuello', 'paratiroid'],                                'fa-solid fa-head-side-cough', '#14b8a6', '#5eead4', '#ccfbf1', '#0f766e'],
            [['pulmon', 'torax', 'toracic', 'pleural'],                                   'fa-solid fa-lungs',           '#0891b2', '#22d3ee', '#cffafe', '#0e7490'],
            [['carotid', 'doppler', 'vascul', 'venoso', 'arterial', 'vena', 'arteria'],   'fa-solid fa-heart-pulse',     '#ef4444', '#fca5a5', '#fee2e2', '#b91c1c'],
            [['transfontanel', 'cerebral', 'craneal', 'encefal', 'neonatal cerebral'],    'fa-solid fa-brain',           '#a855f7', '#d8b4fe', '#f3e8ff', '#7e22ce'],
            [['ocular', 'orbit', ' ojo'],                                                 'fa-solid fa-eye',             '#0ea5e9', '#7dd3fc', '#e0f2fe', '#0369a1'],
            [['prostat'],                                                                 'fa-solid fa-mars',            '#3b82f6', '#93c5fd', '#dbeafe', '#1d4ed8'],
            [['testicul', 'escrotal'],                                                    'fa-solid fa-mars',            '#6366f1', '#a5b4fc', '#e0e7ff', '#4338ca'],
            [['pelvi', 'utero', 'ovario', 'ginecolog', 'anexial'],                        'fa-solid fa-venus',           '#8b5cf6', '#c4b5fd', '#ede9fe', '#6d28d9'],
            [['abdomin', 'hepat', 'higado', 'vesicula', 'biliar', 'pancrea', 'bazo'],     'fa-solid fa-bowl-food',       '#02b1f4', '#38bdf8', '#e0f5fe', '#0284c7'],
            [['renal', 'rinon', 'urolog', 'vesical', 'vejiga', 'urinari'],                'fa-solid fa-droplet',         '#0ea5e9', '#7dd3fc', '#e0f2fe', '#0369a1'],
            [['musculo', 'articul', 'tendon', 'hombro', 'rodilla', 'codo', 'tobillo', 'cadera', 'oseo', 'hueso'], 'fa-solid fa-bone', '#22c55e', '#86efac', '#dcfce7', '#15803d'],
            [['muneca', 'mano', 'dedo'],                                                  'fa-solid fa-hand',            '#22c55e', '#86efac', '#dcfce7', '#15803d'],
            [['partes blandas', 'blanda', 'ganglio', 'nodulo', 'tejido', 'subcutan', 'piel'], 'fa-solid fa-hand-dots',   '#f59e0b', '#fcd34d', '#fef3c7', '#b45309'],
        ];
        foreach ($reglas as $r) {
            foreach ($r[0] as $kw) {
                if (mb_strpos($n, $kw) !== false) {
                    return [
                        'icon'  => $r[1],
                        'bg'    => 'linear-gradient(135deg,' . $r[2] . ',' . $r[3] . ')',
                        'badge' => $r[4],
                        'text'  => $r[5],
                    ];
                }
            }
        }
        return null;
    }
}
?>
<style>
.ng-info { margin:0 0 16px; border:1px solid var(--border); border-radius:12px; overflow:hidden; background:var(--bg-muted); }
.ng-info > summary { list-style:none; cursor:pointer; padding:12px 14px; font-size:12.5px; font-weight:700; color:var(--text-primary); display:flex; align-items:center; gap:8px; }
.ng-info > summary::-webkit-details-marker { display:none; }
.ng-info > summary > i:first-child { color:var(--accent); }
.ng-caret { margin-left:auto; transition:transform .2s; color:var(--text-muted); font-size:12px; }
.ng-info[open] .ng-caret { transform:rotate(180deg); }
.ng-info__content { padding:0 14px 14px; }
.ng-head { display:flex; align-items:center; gap:11px; padding:6px 0 12px; border-bottom:1px dashed var(--border); margin-bottom:6px; }
.ng-avatar { width:40px; height:40px; border-radius:50%; flex-shrink:0; background:linear-gradient(135deg,var(--accent),#38bdf8); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:13px; }
.ng-name { font-size:14px; font-weight:700; color:var(--text-primary); }
.ng-sub { font-size:11.5px; color:var(--text-muted); margin-top:1px; }
.ng-sec { margin-top:14px; }
.ng-sec__title { font-size:10.5px; font-weight:700; text-transform:uppercase; letter-spacing:.4px; color:var(--accent-text); display:flex; align-items:center; gap:6px; margin-bottom:8px; }
.ng-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px 16px; }
.ng-item { min-width:0; }
.ng-item__label { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.4px; color:var(--text-muted); }
.ng-item__val { font-size:13px; font-weight:600; color:var(--text-primary); margin-top:2px; word-break:break-word; }
.ng-block { background:var(--bg-surface); border:1px solid var(--border); border-radius:9px; padding:10px 12px; font-size:12.5px; color:var(--text-secondary); line-height:1.5; white-space:pre-wrap; word-break:break-word; }
.ng-block.is-empty { color:var(--text-muted); font-style:italic; }

/* Modal Notas de sesión — layout 2 columnas */
.ns-main { padding:0 !important; }
.ns-header { display:flex; align-items:center; gap:14px; padding:20px 24px; background:linear-gradient(135deg,var(--accent-soft),var(--bg-surface)); border-bottom:1px solid var(--border); }
.ns-header__icon { width:46px; height:46px; border-radius:13px; flex-shrink:0; background:linear-gradient(135deg,var(--accent),#38bdf8); color:#fff; display:flex; align-items:center; justify-content:center; font-size:20px; box-shadow:0 4px 12px rgba(2,177,244,.3); }
.ns-header__title { margin:0; font-size:17px; font-weight:800; color:var(--text-primary); }
.ns-header__sub { margin:3px 0 0; font-size:12.5px; color:var(--text-secondary); line-height:1.45; }
.ns-body { display:block; padding:20px 24px 24px; }
.ns-col { min-width:0; }
.ns-col__label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:var(--text-muted); display:flex; align-items:center; gap:7px; margin:0 0 10px; }
.ns-col__label i { color:var(--accent); }
.ns-card { border:1px solid var(--border); border-radius:14px; background:var(--bg-surface); padding:16px; margin-bottom:18px; }
.ns-history { border-top:1px solid var(--border-soft); padding-top:16px; }
.ns-history__head { display:flex; align-items:center; justify-content:space-between; margin-bottom:12px; flex-wrap:wrap; gap:8px; font-size:13px; }
#eco-modal-notas-paciente-eco .eco-field label i { color:var(--accent); margin-right:5px; }
#eco-modal-notas-paciente-eco .ng-grid { grid-template-columns:1fr 1fr; }
</style>
<div id="eco-modal-gestionar-paciente-eco" class="eco-modal" aria-hidden="true" role="dialog" aria-labelledby="eco-modal-gestionar-title">
    <div class="eco-modal__dialog eco-modal__dialog--wide">
        <div class="eco-modal__split">
            <div class="eco-modal__aside">
                <div class="eco-modal__aside-icon"><i class="fa-solid fa-user-gear"></i></div>
                <h3 id="eco-modal-gestionar-title">Gestionar paciente</h3>
                <p>Acciones rápidas para:</p>
                <strong id="eco-gestion-pac-nombre">—</strong>
                <p id="eco-gestion-pac-meta" class="eco-modal__body-text" style="margin:0;font-size:12px;"></p>
                <p id="eco-gestion-pac-tel" class="eco-modal__body-text" style="margin:6px 0 0;font-size:12px;display:none;"></p>
                <p class="eco-modal__hint"><i class="fa-solid fa-circle-info" style="margin-right:4px;"></i> Los informes usan el esquema de ecografías del sistema.</p>
            </div>
            <div class="eco-modal__main">
                <button type="button" class="eco-modal__close" data-eco-modal-close aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
                <h4 class="eco-modal__title">Información y acciones</h4>
                <div id="eco-gestion-pac-body">
                    <p class="eco-modal__body-text">Cargando datos del paciente…</p>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="eco-modal-notas-paciente-eco" class="eco-modal" aria-hidden="true" role="dialog" aria-labelledby="eco-modal-notas-eco-title">
    <div class="eco-modal__dialog ns-dialog" style="max-width:660px;width:100%;">
        <div class="eco-modal__main ns-main">
            <button type="button" class="eco-modal__close" data-eco-modal-close aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>

            <div class="ns-header">
                <div class="ns-header__icon"><i class="fa-solid fa-notes-medical"></i></div>
                <div>
                    <h4 class="ns-header__title" id="eco-modal-notas-eco-title">Notas de sesión</h4>
                    <p class="ns-header__sub">Registro clínico privado del ecografista. Consulta los datos completos del paciente en su <strong>Historia clínica</strong>.</p>
                </div>
            </div>

            <div class="ns-body">
                <div class="ns-col ns-col--form">
                    <div id="eco-notas-eco-error" style="display:none;padding:10px 12px;border-radius:8px;font-size:13px;margin-bottom:12px;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.35);color:#b91c1c;" role="alert"></div>

                    <form id="eco-form-notas-paciente" class="ns-card" novalidate>
                        <p class="ns-col__label"><i class="fa-solid fa-pen-to-square"></i> Nueva nota de sesión</p>
                        <input type="hidden" name="paciente_id" id="eco-notas-paciente-id">
                        <div class="eco-field">
                            <label for="eco-notas-fecha"><i class="fa-regular fa-clock"></i> Fecha de la sesión</label>
                            <input type="datetime-local" name="fecha_sesion" id="eco-notas-fecha" required>
                        </div>
                        <div class="eco-field">
                            <label for="eco-notas-contenido"><i class="fa-solid fa-pen"></i> Nota</label>
                            <textarea name="contenido" id="eco-notas-contenido" rows="4" required maxlength="2000" placeholder="Observaciones, hallazgos, recomendaciones…"></textarea>
                        </div>
                        <div class="eco-modal__footer" style="margin-top:0;padding-top:0;border:none;justify-content:flex-end;">
                            <button type="submit" class="btn-primary"><i class="fa-solid fa-floppy-disk"></i> Guardar nota</button>
                        </div>
                    </form>

                    <div class="ns-history">
                        <div class="ns-history__head">
                            <strong><i class="fa-solid fa-clock-rotate-left" style="color:var(--accent);margin-right:6px;"></i> Historial de notas</strong>
                            <button type="button" id="eco-btn-limpiar-notas" class="btn-secondary" style="font-size:11.5px;color:var(--danger, #b91c1c);border-color:rgba(239,68,68,.35);display:none;">
                                <i class="fa-solid fa-trash"></i> Borrar todas
                            </button>
                        </div>
                        <div id="eco-notas-list"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="eco-modal-informes-paciente-eco" class="eco-modal" aria-hidden="true" role="dialog" aria-labelledby="eco-modal-informes-title">
    <div class="eco-modal__dialog eco-modal__dialog--wide eco-inform-modal__dialog">
        <button type="button" class="eco-modal__close eco-inform-modal__close" data-eco-modal-close aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>

        <div class="eco-inform-modal__hero">
            <div class="eco-inform-modal__hero-icon"><i class="fa-solid fa-file-waveform"></i></div>
            <div class="eco-inform-modal__hero-copy">
                <p class="eco-inform-modal__hero-kicker">Historial ecográfico</p>
                <h2 id="eco-modal-informes-title" class="eco-inform-modal__hero-title">Estudios registrados</h2>
                <p class="eco-inform-modal__hero-lead">Listado ordenado desde el más reciente. Pulse <strong>Ver</strong> para abrir el informe.</p>
            </div>
        </div>

        <div class="eco-inform-modal__strip">
            <div class="eco-inform-modal__pill eco-inform-modal__pill--name">
                <i class="fa-solid fa-user" aria-hidden="true"></i>
                <span id="eco-informes-strip-name">—</span>
            </div>
            <div class="eco-inform-modal__pill eco-inform-modal__pill--muted">
                <i class="fa-solid fa-id-card" aria-hidden="true"></i>
                <span id="eco-informes-strip-ci">CI —</span>
            </div>
            <div class="eco-inform-modal__pill eco-inform-modal__pill--age" id="eco-informes-strip-age-wrap" hidden>
                <i class="fa-solid fa-cake-candles" aria-hidden="true"></i>
                <span id="eco-informes-strip-age"></span>
            </div>
            <div class="eco-inform-modal__pill eco-inform-modal__pill--count" id="eco-informes-strip-count-wrap">
                <i class="fa-solid fa-folder-open" aria-hidden="true"></i>
                <span id="eco-informes-strip-count">0 informes</span>
            </div>
        </div>

        <div class="eco-inform-modal__toolbar">
            <p class="eco-inform-modal__toolbar-meta"><i class="fa-solid fa-arrow-down-short-wide"></i> Más reciente primero</p>
            <button type="button" class="btn-primary eco-inform-modal__btn-new" id="eco-informes-toolbar-new"><i class="fa-solid fa-plus"></i> Nuevo informe</button>
        </div>

        <div class="eco-inform-modal__list-wrap">
            <div id="eco-informes-list" class="eco-inform-modal__list" role="list" aria-busy="true"></div>
        </div>
    </div>
</div>

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
                <button type="button" class="eco-btn-cancel" id="eco-inf-det-firmar" title="Firmar informe" style="display:none;color:#0369a1;border-color:#7dd3fc;">
                    <i class="fa-solid fa-signature"></i> Firmar
                </button>
                <button type="button" class="eco-btn-cancel" id="eco-inf-det-anular" title="Anular informe" style="display:none;color:#b91c1c;border-color:#fca5a5;">
                    <i class="fa-solid fa-ban"></i> Anular
                </button>
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

<div id="eco-modal-historia-clinica-eco" class="eco-modal eco-modal-panel-ecografista" aria-hidden="true" role="dialog" aria-labelledby="eco-hc-titulo">
    <div class="modal-content-form-eco">
        <div class="modal-form-eco-header">
            <div class="eco-modal-tipo-icon"><i class="fa-solid fa-clock-rotate-left"></i></div>
            <div class="eco-header-tipo-info">
                <h2 id="eco-hc-titulo">Historia clínica</h2>
                <p id="eco-hc-paciente">—</p>
            </div>
            <div class="eco-modal-informe-detalle-actions">
                <button type="button" class="modal-close-btn" data-eco-modal-close aria-label="Cerrar">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
        </div>
        <div class="modal-form-eco-body" id="eco-hc-body">
            <div class="modal-form-eco-loader">
                <i class="fa-solid fa-spinner fa-spin"></i>
                <p>Cargando historia clínica…</p>
            </div>
        </div>
    </div>
</div>

<div id="eco-modal-facturacion-paciente-eco" class="eco-modal eco-modal-panel-ecografista" aria-hidden="true" role="dialog" aria-labelledby="eco-fp-titulo">
    <div class="modal-content-form-eco">
        <div class="modal-form-eco-header">
            <div class="eco-modal-tipo-icon"><i class="fa-solid fa-cash-register"></i></div>
            <div class="eco-header-tipo-info">
                <h2 id="eco-fp-titulo">Facturación</h2>
                <p id="eco-fp-paciente">—</p>
            </div>
            <div class="eco-modal-informe-detalle-actions">
                <button type="button" class="modal-close-btn" data-eco-modal-close aria-label="Cerrar">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
        </div>
        <div class="modal-form-eco-body" id="eco-fp-body">
            <div class="modal-form-eco-loader">
                <i class="fa-solid fa-spinner fa-spin"></i>
                <p>Cargando facturación…</p>
            </div>
        </div>
    </div>
</div>

<style>
/* ===================== Historia clínica — diseño consolidado ===================== */
.hc-wrap { display:flex; flex-direction:column; gap:20px; }

/* Ficha del paciente */
.hc-patient { display:flex; gap:16px; align-items:flex-start; padding:18px 20px; border-radius:16px;
    background:linear-gradient(135deg, rgba(2,177,244,.10), rgba(56,189,248,.03)); border:1px solid var(--border, #e5e7eb); }
.hc-patient__avatar { flex:0 0 56px; width:56px; height:56px; border-radius:16px; display:flex; align-items:center; justify-content:center;
    font-size:20px; font-weight:800; color:#fff; background:linear-gradient(135deg,#02b1f4,#0284c7); box-shadow:0 8px 18px rgba(2,177,244,.32); }
.hc-patient__main { min-width:0; flex:1; }
.hc-patient__name { font-size:18px; font-weight:800; color:var(--text-primary, #0f172a); letter-spacing:-.2px; }
.hc-patient__sub { display:flex; flex-wrap:wrap; gap:8px 16px; margin-top:6px; font-size:12.5px; font-weight:600; color:var(--text-secondary, #475569); }
.hc-patient__sub span { display:inline-flex; align-items:center; gap:5px; }
.hc-patient__sub i { color:#02b1f4; }
.hc-facts { display:grid; grid-template-columns:repeat(auto-fill,minmax(150px,1fr)); gap:11px 18px; margin-top:14px; padding-top:14px; border-top:1px dashed var(--border, #e2e8f0); }
.hc-fact { min-width:0; }
.hc-fact__l { display:block; font-size:9.5px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:var(--text-muted, #94a3b8); }
.hc-fact__v { display:block; font-size:13px; font-weight:600; color:var(--text-primary, #0f172a); margin-top:2px; word-break:break-word; }

/* Tarjetas de estadística */
.hc-stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(118px,1fr)); gap:12px; }
.hc-stat { display:flex; align-items:center; gap:11px; padding:13px 15px; border-radius:13px; background:var(--bg-surface, #fff); border:1px solid var(--border, #e5e7eb); }
.hc-stat__ic { flex:0 0 38px; width:38px; height:38px; border-radius:11px; display:flex; align-items:center; justify-content:center; font-size:16px; }
.hc-stat__num { font-size:18px; font-weight:800; color:var(--text-primary, #0f172a); line-height:1.1; }
.hc-stat__lbl { font-size:10.5px; font-weight:700; color:var(--text-muted, #94a3b8); text-transform:uppercase; letter-spacing:.4px; margin-top:1px; }

/* Encabezado de la línea de tiempo */
.hc-tl-head { font-size:11.5px; font-weight:800; text-transform:uppercase; letter-spacing:.6px; color:var(--text-muted, #94a3b8); display:flex; align-items:center; gap:10px; }
.hc-tl-head::after { content:''; flex:1; height:1px; background:var(--border, #e5e7eb); }

/* Línea de tiempo */
.hc-timeline { position:relative; display:flex; flex-direction:column; gap:14px; }
.hc-item { position:relative; display:flex; gap:15px; }
.hc-item:not(:last-child)::before { content:''; position:absolute; left:18px; top:40px; bottom:-14px; width:2px; background:var(--border, #e5e7eb); }
.hc-dot { flex:0 0 38px; width:38px; height:38px; border-radius:50%; display:flex; align-items:center; justify-content:center; color:#fff; font-size:15px;
    background:var(--hc-c, #94a3b8); box-shadow:0 0 0 4px var(--bg-surface, #fff); z-index:1; }
.hc-card { flex:1; min-width:0; border:1px solid var(--border, #e5e7eb); border-left:3px solid var(--hc-c, #94a3b8); border-radius:12px; padding:13px 16px;
    background:var(--bg-surface, #fff); transition:box-shadow .18s ease, transform .18s ease; }
.hc-item[role="button"] { cursor:pointer; }
.hc-item[role="button"]:hover .hc-card { box-shadow:0 10px 24px rgba(2,132,199,.13); transform:translateY(-2px); }
.hc-card__top { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
.hc-card__title { font-size:14.5px; font-weight:700; color:var(--text-primary, #0f172a); }
.hc-card__meta { display:flex; align-items:center; gap:6px; flex-wrap:wrap; font-size:12px; color:var(--text-muted, #94a3b8); margin-top:6px; }
.hc-card__meta i { opacity:.85; }
.hc-card__meta .hc-sep { opacity:.45; }
.hc-card__det { margin:9px 0 0; font-size:12.5px; color:var(--text-secondary, #475569); line-height:1.55; }
.hc-card__det strong { color:var(--text-primary, #0f172a); font-weight:700; }
.hc-tags { display:flex; gap:6px; flex-wrap:wrap; margin-top:9px; }
.hc-tag { font-size:10.5px; font-weight:700; text-transform:capitalize; padding:2px 9px; border-radius:999px; background:var(--bg-muted, #f1f5f9); color:var(--text-secondary, #475569); }
.hc-pago { display:inline-flex; align-items:center; gap:6px; margin-top:10px; font-size:11.5px; font-weight:700; padding:4px 11px; border-radius:999px; }
.hc-card__open { display:inline-flex; align-items:center; gap:6px; margin-top:11px; font-size:11.5px; font-weight:800; color:#0284c7; }
.hc-card__open i { transition:transform .18s ease; }
.hc-item[role="button"]:hover .hc-card__open i { transform:translateX(3px); }

/* Chips */
.hc-chip { font-size:10.5px; font-weight:700; padding:2px 9px; border-radius:999px; white-space:nowrap; }
.hc-chip--num { background:var(--bg-muted, #f1f5f9); color:var(--text-muted, #64748b); font-family:ui-monospace, "SFMono-Regular", monospace; }
.hc-chip--costo { background:rgba(34,197,94,.13); color:#15803d; }
.hc-chip--estado { text-transform:capitalize; background:#eef2ff; color:#3730a3; }
.hc-estado--cita { background:rgba(34,197,94,.12); color:#15803d; }
.hc-estado--informe { background:rgba(2,177,244,.12); color:#0369a1; }

/* Estado vacío */
.hc-empty { text-align:center; padding:42px 20px; color:var(--text-muted, #94a3b8); }
.hc-empty i { font-size:34px; opacity:.32; }
.hc-empty p { margin:13px 0 0; font-size:13.5px; }

/* Modo oscuro */
[data-theme="dark"] .hc-patient { background:linear-gradient(135deg, rgba(2,177,244,.10), rgba(2,132,199,.03)); border-color:var(--border); }
[data-theme="dark"] .hc-patient__name,
[data-theme="dark"] .hc-fact__v,
[data-theme="dark"] .hc-stat__num,
[data-theme="dark"] .hc-card__title,
[data-theme="dark"] .hc-card__det strong { color:var(--text-primary); }
[data-theme="dark"] .hc-stat,
[data-theme="dark"] .hc-card { background:var(--bg-surface); border-color:var(--border); }
[data-theme="dark"] .hc-tag,
[data-theme="dark"] .hc-chip--num { background:var(--bg-hover); color:var(--text-secondary); }
[data-theme="dark"] .hc-dot { box-shadow:0 0 0 4px var(--bg-app); }
[data-theme="dark"] .hc-chip--estado { background:rgba(99,102,241,.18); color:#a5b4fc; }

/* ===================== Facturación del paciente (modal) ===================== */
.fp-wrap { display:flex; flex-direction:column; gap:18px; }
.fp-totals { display:grid; grid-template-columns:repeat(3,1fr); gap:12px; }
.fp-total { padding:14px 12px; border-radius:13px; border:1px solid var(--border, #e5e7eb); background:var(--bg-surface, #fff); text-align:center; }
.fp-total__lbl { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.4px; color:var(--text-muted, #94a3b8); }
.fp-total__val { font-size:19px; font-weight:800; margin-top:4px; }
.fp-total--fact .fp-total__val { color:#0284c7; }
.fp-total--cob .fp-total__val { color:#15803d; }
.fp-total--pend .fp-total__val { color:#b45309; }
.fp-list { display:flex; flex-direction:column; gap:12px; }
.fp-cita { border:1px solid var(--border, #e5e7eb); border-radius:13px; padding:14px 16px; background:var(--bg-surface, #fff); }
.fp-cita__top { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; }
.fp-cita__title { font-size:14px; font-weight:700; color:var(--text-primary, #0f172a); }
.fp-cita__date { font-size:11.5px; color:var(--text-muted, #94a3b8); margin-top:2px; }
.fp-cita__serv { font-size:11.5px; color:var(--text-secondary, #475569); margin-top:6px; line-height:1.45; }
.fp-badge { font-size:10.5px; font-weight:700; padding:3px 10px; border-radius:999px; white-space:nowrap; flex-shrink:0; }
.fp-amounts { display:flex; gap:20px; margin-top:12px; padding-top:12px; border-top:1px dashed var(--border, #e5e7eb); flex-wrap:wrap; }
.fp-amt span { display:block; font-size:9.5px; font-weight:700; text-transform:uppercase; letter-spacing:.4px; color:var(--text-muted, #94a3b8); }
.fp-amt strong { font-size:15px; font-weight:800; color:var(--text-primary, #0f172a); }
.fp-amt--saldo strong { color:#b45309; }
.fp-method { font-size:11.5px; color:var(--text-muted, #94a3b8); margin-top:10px; display:flex; align-items:center; gap:6px; }
.fp-method i { color:#15803d; }
.fp-abonar-btn { margin-top:12px; font-size:12.5px; padding:8px 14px; }
.fp-form { margin-top:12px; padding-top:12px; border-top:1px dashed var(--border, #e5e7eb); display:none; gap:10px; flex-wrap:wrap; align-items:flex-end; }
.fp-form.is-open { display:flex; }
.fp-field { flex:1; min-width:120px; }
.fp-field label { display:block; font-size:10.5px; font-weight:700; margin-bottom:5px; color:var(--text-secondary, #475569); }
.fp-field input, .fp-field select { width:100%; padding:8px 10px; border:1.5px solid var(--border, #e5e7eb); border-radius:8px; font-size:13px; font-family:inherit; box-sizing:border-box; background:var(--bg-surface, #fff); color:var(--text-primary, #0f172a); }
.fp-field input:focus, .fp-field select:focus { outline:none; border-color:#02b1f4; box-shadow:0 0 0 3px rgba(2,177,244,.13); }
.fp-msg { margin-top:10px; font-size:12px; padding:8px 11px; border-radius:8px; display:none; }
.fp-msg--ok { display:block; background:rgba(34,197,94,.12); color:#15803d; }
.fp-msg--err { display:block; background:rgba(239,68,68,.1); color:#b91c1c; }
.fp-empty { text-align:center; padding:42px 20px; color:var(--text-muted, #94a3b8); }
.fp-empty i { font-size:32px; opacity:.32; }
.fp-empty p { margin:12px 0 0; font-size:13.5px; }
[data-theme="dark"] .fp-total,
[data-theme="dark"] .fp-cita { background:var(--bg-surface); border-color:var(--border); }
[data-theme="dark"] .fp-cita__title,
[data-theme="dark"] .fp-amt strong { color:var(--text-primary); }
[data-theme="dark"] .fp-field input,
[data-theme="dark"] .fp-field select { background:#0e1726; border-color:#2b3a52; color:var(--text-primary); }
</style>

<script>window.EcoServiciosCatalogo = <?= json_encode($eco_servicios_facturacion, JSON_UNESCAPED_UNICODE) ?>;</script>
<style>
.eco-exp-servicios { margin:4px 0 18px; padding:16px 16px 14px; border:1.5px solid var(--border, #e5e7eb); border-radius:14px; background:var(--bg-muted, #f8fafc); }
.eco-exp-serv-head { display:flex; align-items:center; gap:10px; margin-bottom:13px; flex-wrap:wrap; }
.eco-exp-serv-head__title { font-size:13.5px; font-weight:800; color:var(--text-primary, #0f172a); display:flex; align-items:center; gap:7px; }
.eco-exp-serv-head__title i { color:#02b1f4; }
.eco-exp-serv-head__hint { font-size:11.5px; font-weight:600; color:var(--text-muted, #94a3b8); }
.eco-exp-serv-grid { display:grid; gap:10px; grid-template-columns:repeat(4,1fr); }
@media (max-width:640px){ .eco-exp-serv-grid { grid-template-columns:repeat(2,1fr); } }
.eco-serv-chip { position:relative; cursor:pointer; margin:0; }
.eco-serv-chip input { position:absolute; opacity:0; width:0; height:0; }
.eco-serv-chip__box { position:relative; height:100%; display:flex; flex-direction:column; gap:7px; padding:11px 12px 12px; border:1.5px solid var(--border, #e5e7eb); border-radius:13px; background:var(--bg-surface, #fff); transition:border-color .18s ease, box-shadow .18s ease, background .18s ease; min-height:92px; box-sizing:border-box; }
.eco-serv-chip:hover .eco-serv-chip__box { border-color:#7dd3fc; }
.eco-serv-chip input:checked + .eco-serv-chip__box { border-color:#02b1f4; background:rgba(2,177,244,.07); box-shadow:0 0 0 3px rgba(2,177,244,.13); }
.eco-serv-chip__icon { width:34px; height:34px; flex-shrink:0; border-radius:9px; display:flex; align-items:center; justify-content:center; font-size:14px; color:#02b1f4; background:rgba(2,177,244,.1); }
.eco-serv-chip__label { font-size:12.5px; font-weight:600; color:var(--text-primary, #0f172a); line-height:1.3; padding-right:42px; }
.eco-serv-chip__price { position:absolute; top:11px; right:11px; font-size:11.5px; font-weight:800; color:#15803d; background:rgba(34,197,94,.12); padding:2px 8px; border-radius:999px; }
.eco-serv-chip__price--estudio { color:#0369a1; background:rgba(2,177,244,.12); }
.eco-serv-chip__check { position:absolute; bottom:11px; right:11px; transform:scale(.6); font-size:14px; color:#02b1f4; opacity:0; transition:opacity .18s ease, transform .18s ease; }
.eco-serv-chip input:checked + .eco-serv-chip__box .eco-serv-chip__check { opacity:1; transform:scale(1); }
.eco-serv-chip.is-locked { opacity:.45; }
.eco-serv-chip.is-locked .eco-serv-chip__box { cursor:not-allowed; }
.eco-exp-serv-foot { display:flex; align-items:center; justify-content:space-between; margin-top:13px; padding-top:11px; border-top:1px dashed var(--border, #e5e7eb); }
.eco-exp-serv-foot__label { font-size:12px; font-weight:700; color:var(--text-secondary, #475569); display:flex; align-items:center; gap:7px; }
.eco-exp-serv-foot__label i { color:#02b1f4; }
.eco-exp-serv-foot__total { font-size:16px; font-weight:800; color:#0f172a; }
.eco-exp-serv-note { margin:9px 0 0; font-size:11.5px; color:var(--text-muted, #94a3b8); display:flex; align-items:flex-start; gap:6px; line-height:1.4; }
.eco-exp-serv-note i { color:#02b1f4; margin-top:1px; }
.eco-exp-serv-prefill { margin:10px 0 0; font-size:11.5px; font-weight:600; color:#0369a1; background:rgba(2,177,244,.10); border:1px solid #bae6fd; border-radius:9px; padding:7px 11px; display:flex; align-items:center; gap:7px; line-height:1.35; }
.eco-exp-serv-prefill i { color:#02b1f4; }
[data-theme="dark"] .eco-exp-serv-prefill { color:#7dd3fc; background:rgba(2,132,199,.14); border-color:rgba(2,132,199,.4); }
.eco-serv-chip.is-today .eco-serv-chip__box { border-color:#fcd34d; background:rgba(245,158,11,.08); }
.eco-exp-serv-hoy { margin:10px 0 0; font-size:11.5px; font-weight:600; color:#b45309; background:rgba(245,158,11,.10); border:1px solid #fde68a; border-radius:9px; padding:7px 11px; display:flex; align-items:center; gap:7px; line-height:1.35; }
.eco-exp-serv-hoy i { color:#f59e0b; }
[data-theme="dark"] .eco-exp-serv-hoy { color:#fcd34d; background:rgba(180,83,9,.16); border-color:rgba(180,83,9,.45); }
.eco-exp-cards-label { font-size:13.5px; font-weight:800; color:var(--text-primary, #0f172a); margin:0 0 10px; }
[data-theme="dark"] .eco-exp-servicios { background:rgba(255,255,255,.03); border-color:var(--border); }
[data-theme="dark"] .eco-serv-chip__box { background:var(--bg-surface); border-color:var(--border); }
[data-theme="dark"] .eco-serv-chip__label,
[data-theme="dark"] .eco-exp-serv-foot__total,
[data-theme="dark"] .eco-exp-cards-label { color:var(--text-primary); }
[data-theme="dark"] .eco-serv-chip__price { color:#86efac; }

/* Banner de facturación dentro del formulario de estudio */
.eco-fact-banner { margin:18px 0; border:1.5px solid #bae6fd; border-radius:14px; background:linear-gradient(135deg,#f0f9ff,#fff); padding:14px 16px; }
.eco-fact-banner__head { font-size:12.5px; font-weight:800; color:#0369a1; display:flex; align-items:center; gap:8px; margin-bottom:10px; }
.eco-fact-banner__lines { list-style:none; margin:0; padding:0; }
.eco-fact-banner__lines li { display:flex; align-items:center; justify-content:space-between; gap:12px; font-size:13px; color:#334155; padding:5px 0; border-bottom:1px dashed #e2e8f0; }
.eco-fact-banner__lines li:last-child { border-bottom:0; }
.eco-fact-banner__lines li span { font-weight:600; }
.eco-fact-banner__lines li b { font-weight:800; color:#0f172a; white-space:nowrap; }
.eco-fact-banner__promo { margin-top:9px; font-size:11.5px; font-weight:700; color:#15803d; background:rgba(34,197,94,.12); border-radius:999px; padding:5px 11px; display:inline-flex; align-items:center; gap:6px; }
.eco-fact-banner__ahorro { margin-top:7px; font-size:11.5px; font-weight:700; color:#b45309; display:flex; align-items:center; gap:6px; }
.eco-fact-banner__total { display:flex; align-items:center; justify-content:space-between; margin-top:12px; padding-top:11px; border-top:1.5px solid #bae6fd; }
.eco-fact-banner__total span { font-size:12.5px; font-weight:700; color:#475569; }
.eco-fact-banner__total strong { font-size:19px; font-weight:800; color:#0369a1; }
[data-theme="dark"] .eco-fact-banner { background:rgba(2,132,199,.10); border-color:rgba(2,132,199,.45); }
[data-theme="dark"] .eco-fact-banner__head { color:#7dd3fc; }
[data-theme="dark"] .eco-fact-banner__lines li { color:var(--text-secondary); border-bottom-color:var(--border); }
[data-theme="dark"] .eco-fact-banner__lines li b { color:var(--text-primary); }
[data-theme="dark"] .eco-fact-banner__total { border-top-color:rgba(2,132,199,.45); }
[data-theme="dark"] .eco-fact-banner__total span { color:var(--text-secondary); }
[data-theme="dark"] .eco-fact-banner__total strong { color:#7dd3fc; }

/* Botones del modal "firmar antes de imprimir" */
#eco-modal-firmar-antes-eco .eco-modal__footer { margin-top:20px; padding-top:16px; border-top:1px solid var(--border,#eef1f5); gap:10px; justify-content:flex-end; }
#eco-modal-firmar-antes-eco .eco-btn-cancel,
#eco-modal-firmar-antes-eco .eco-btn-submit {
    display:inline-flex; align-items:center; justify-content:center; gap:8px; line-height:1;
    padding:12px 22px; font-size:13.5px; font-weight:600; font-family:"Poppins",sans-serif;
    border-radius:12px; cursor:pointer;
    transition:transform .18s ease, box-shadow .18s ease, background .18s ease, border-color .18s ease, color .18s ease;
}
#eco-modal-firmar-antes-eco .eco-btn-cancel {
    background:var(--bg-surface,#fff); color:var(--text-secondary,#64748b); border:1.5px solid var(--border,#dfe5ec);
}
#eco-modal-firmar-antes-eco .eco-btn-cancel:hover { background:var(--bg-muted,#f1f5f9); color:var(--text-primary,#0c1a2e); border-color:#c3cdd9; }
#eco-modal-firmar-antes-eco .eco-btn-submit {
    background:linear-gradient(135deg,#02b1f4,#014a82); color:#fff; border:none; box-shadow:0 8px 22px rgba(2,177,244,.34);
}
#eco-modal-firmar-antes-eco .eco-btn-submit:hover { transform:translateY(-1px); box-shadow:0 12px 28px rgba(2,177,244,.45); }
#eco-modal-firmar-antes-eco .eco-btn-submit:active { transform:translateY(0); }
#eco-modal-firmar-antes-eco .eco-btn-submit:disabled { opacity:.65; cursor:not-allowed; transform:none; box-shadow:none; }
</style>
<div id="eco-modal-expediente-informe-eco" class="eco-modal" aria-hidden="true" role="dialog" aria-labelledby="eco-expediente-title" data-eco-modal-static>
    <div class="eco-modal__dialog eco-modal__dialog--wide eco-exp-dialog" style="max-width:820px;">
        <div class="eco-modal__main eco-exp-main">
            <button type="button" class="eco-modal__close eco-exp-close" data-eco-modal-close aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>

            <div class="eco-exp-header">
                <div class="eco-exp-header__icon"><i class="fa-solid fa-folder-tree"></i></div>
                <h4 class="eco-exp-header__title" id="eco-expediente-title">Seleccionar tipo de expediente</h4>
                <p class="eco-exp-header__subtitle">Elige el formulario clínico apropiado según la edad del paciente</p>
            </div>

            <div class="eco-exp-patient">
                <span class="eco-exp-patient__avatar"><i class="fa-solid fa-user-circle"></i></span>
                <div class="eco-exp-patient__info">
                    <span class="eco-exp-patient__label">Paciente</span>
                    <span class="eco-exp-patient__name" id="eco-expediente-paciente-info">—</span>
                </div>
                <span class="eco-exp-patient__age" id="eco-expediente-paciente-edad" hidden></span>
            </div>

            <div class="eco-exp-servicios">
                <div class="eco-exp-serv-head">
                    <span class="eco-exp-serv-head__title"><i class="fa-solid fa-plus-circle"></i> Servicios</span>
                    <span class="eco-exp-serv-head__hint">elige uno o varios · puedes combinarlos</span>
                </div>
                <div class="eco-exp-serv-grid" id="eco-exp-serv-grid">
                    <label class="eco-serv-chip">
                        <input type="checkbox" class="eco-serv-input" id="eco-serv-input-eco"
                               value="eco"
                               data-label="Ecografías"
                               data-price="0" checked>
                        <span class="eco-serv-chip__box">
                            <span class="eco-serv-chip__icon"><i class="fa-solid fa-wave-square"></i></span>
                            <span class="eco-serv-chip__label">Ecografías</span>
                            <span class="eco-serv-chip__price eco-serv-chip__price--estudio">según tipo</span>
                            <span class="eco-serv-chip__check"><i class="fa-solid fa-check"></i></span>
                        </span>
                    </label>
                    <?php foreach ($eco_servicios_ui as $s): ?>
                        <label class="eco-serv-chip">
                            <input type="checkbox" class="eco-serv-input"
                                   value="<?= htmlspecialchars($s['key'], ENT_QUOTES, 'UTF-8') ?>"
                                   data-label="<?= htmlspecialchars($s['label'], ENT_QUOTES, 'UTF-8') ?>"
                                   data-price="<?= (int)$s['price'] ?>">
                            <span class="eco-serv-chip__box">
                                <span class="eco-serv-chip__icon"><i class="fa-solid <?= htmlspecialchars($s['icon'], ENT_QUOTES, 'UTF-8') ?>"></i></span>
                                <span class="eco-serv-chip__label"><?= htmlspecialchars($s['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="eco-serv-chip__price">$<?= (int)$s['price'] ?></span>
                                <span class="eco-serv-chip__check"><i class="fa-solid fa-check"></i></span>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <div class="eco-exp-serv-foot">
                    <span class="eco-exp-serv-foot__label"><i class="fa-solid fa-receipt"></i> Subtotal servicios</span>
                    <span class="eco-exp-serv-foot__total" id="eco-exp-serv-subtotal">$0</span>
                </div>
                <p class="eco-exp-serv-note"><i class="fa-solid fa-circle-info"></i> Con "Ecografías" pasarás a elegir el estudio; el total final (estudio + servicios + promociones) se calcula en ese paso. Sin ecografía, los servicios se registran y facturan directamente.</p>
            </div>

            <div class="eco-exp-cards-label">Tipo de expediente</div>
            <div class="eco-exp-cards">
                <button type="button" class="eco-exp-card eco-exp-card--adulto" id="eco-expediente-adulto">
                    <div class="eco-exp-card__icon"><i class="fa-solid fa-user"></i></div>
                    <h3 class="eco-exp-card__title">Expediente Adulto</h3>
                    <p class="eco-exp-card__desc">Historia clínica completa para pacientes adultos</p>
                    <div class="eco-exp-card__range"><i class="fa-solid fa-calendar-check"></i> 18 años o más</div>
                    <span class="eco-exp-card__badge eco-exp-card__badge--reco">Recomendado</span>
                    <span class="eco-exp-card__badge eco-exp-card__badge--lock"><i class="fa-solid fa-lock"></i> No disponible</span>
                </button>
                <button type="button" class="eco-exp-card eco-exp-card--infantil" id="eco-expediente-infantil">
                    <div class="eco-exp-card__icon"><i class="fa-solid fa-child"></i></div>
                    <h3 class="eco-exp-card__title">Expediente Infantil</h3>
                    <p class="eco-exp-card__desc">Formulario pediátrico para menores de edad</p>
                    <div class="eco-exp-card__range"><i class="fa-solid fa-calendar-check"></i> Menores de 18 años</div>
                    <span class="eco-exp-card__badge eco-exp-card__badge--reco">Recomendado</span>
                    <span class="eco-exp-card__badge eco-exp-card__badge--lock"><i class="fa-solid fa-lock"></i> No disponible</span>
                </button>
            </div>
        </div>
    </div>
</div>

<div id="eco-modal-seleccionar-ecografia-eco" class="eco-modal eco-modal-panel-ecografista" aria-hidden="true" role="dialog" aria-labelledby="eco-seleccionar-ecografia-title" data-eco-modal-static>
    <div class="modal-content-eco-grid">
        <div class="eco-modal-header">
            <button type="button" class="eco-btn-back" id="eco-volver-expediente">
                <i class="fa-solid fa-arrow-left"></i> Volver
            </button>
            <div class="eco-header-title">
                <h2 id="eco-seleccionar-ecografia-title"><i class="fa-solid fa-wave-square" style="color:#02b1f4;margin-right:8px;"></i>Seleccionar Tipo de Ecografía</h2>
                <p id="eco-modal-paciente-info">Paciente: —</p>
            </div>
            <button type="button" class="modal-close-btn" data-eco-modal-close aria-label="Cerrar">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="eco-modal-body">
            <div class="eco-cards-grid">
                <?php foreach ($eco_tipos_modal as $t): ?>
                    <?php
                    $cat = $t['categoria'] ?? '';
                    $estilo = eco_estilo_tipo_shell($t['nombre']);
                    $col = $estilo
                        ? ['bg' => $estilo['bg'], 'badge' => $estilo['badge'], 'text' => $estilo['text']]
                        : ($eco_colores_shell[$cat] ?? $eco_color_default_shell);
                    $iconoClase = $estilo['icon'] ?? ($t['icono'] ?: 'fa-solid fa-wave-square');
                    $icono = htmlspecialchars($iconoClase, ENT_QUOTES, 'UTF-8');
                    $desc  = htmlspecialchars($t['descripcion'] ?? '', ENT_QUOTES, 'UTF-8');
                    ?>
                    <div class="eco-card"
                         tabindex="0"
                         role="button"
                         data-eco-tipo-id="<?= (int)$t['id'] ?>"
                         data-eco-tipo-codigo="<?= htmlspecialchars($t['codigo'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                         data-eco-tipo-nombre="<?= htmlspecialchars($t['nombre'], ENT_QUOTES, 'UTF-8') ?>"
                         data-eco-tipo-precio="<?= htmlspecialchars((string)($t['precio'] ?? 0), ENT_QUOTES, 'UTF-8') ?>"
                         data-eco-tipo-icono="<?= htmlspecialchars($iconoClase, ENT_QUOTES, 'UTF-8') ?>">
                        <div class="eco-card-icon" style="background:<?= htmlspecialchars($col['badge'], ENT_QUOTES, 'UTF-8') ?>;color:<?= htmlspecialchars($col['text'], ENT_QUOTES, 'UTF-8') ?>;">
                            <i class="<?= $icono ?>"></i>
                        </div>
                        <?php if ($cat !== ''): ?>
                            <span class="eco-card-badge"
                                  style="background:<?= htmlspecialchars($col['badge'], ENT_QUOTES, 'UTF-8') ?>;color:<?= htmlspecialchars($col['text'], ENT_QUOTES, 'UTF-8') ?>;">
                                <?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        <?php endif; ?>
                        <p class="eco-card-name"><?= htmlspecialchars($t['nombre'], ENT_QUOTES, 'UTF-8') ?></p>
                        <?php if ($desc !== ''): ?>
                            <p class="eco-card-desc"><?= $desc ?></p>
                        <?php endif; ?>
                        <span class="eco-card-select-hint"><i class="fa-solid fa-circle-check"></i> Seleccionar</span>
                    </div>
                <?php endforeach; ?>

                <?php if (empty($eco_tipos_modal)): ?>
                    <p style="grid-column:1/-1;text-align:center;color:#aaa;padding:30px 0;">
                        <i class="fa-solid fa-circle-exclamation" style="font-size:2rem;margin-bottom:10px;display:block;"></i>
                        No hay tipos de ecografía activos configurados.<br>
                        <small>Solicítale al administrador que los registre en la tabla <code>tipos_ecografias</code>.</small>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ── Sub-modal: selección articulación musculoesquelética ─────────────── -->
<div id="eco-modal-seleccionar-musculo-eco" class="eco-modal eco-modal-panel-ecografista" aria-hidden="true" role="dialog" aria-labelledby="eco-seleccionar-musculo-title" data-eco-modal-static>
    <div class="modal-content-eco-grid">
        <div class="eco-modal-header">
            <button type="button" class="eco-btn-back" id="eco-volver-de-musculo">
                <i class="fa-solid fa-arrow-left"></i> Volver
            </button>
            <div class="eco-header-title">
                <h2 id="eco-seleccionar-musculo-title"><i class="fa-solid fa-bone" style="color:#22c55e;margin-right:8px;"></i>Ecografía Musculoesquelética</h2>
                <p id="eco-musculo-paciente-info">Seleccione la articulación a estudiar</p>
            </div>
            <button type="button" class="modal-close-btn" data-eco-modal-close aria-label="Cerrar">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="eco-modal-body">
            <div class="musculo-cards-grid">
                <?php foreach ($eco_tipos_musculo as $t):
                    $icono_m = htmlspecialchars(eco_estilo_tipo_shell($t['nombre'])['icon'] ?? ($t['icono'] ?: 'fa-solid fa-bone'), ENT_QUOTES, 'UTF-8');
                    $desc_m  = htmlspecialchars($t['descripcion'] ?? '', ENT_QUOTES, 'UTF-8');
                ?>
                <div class="eco-card musculo-card"
                     tabindex="0"
                     role="button"
                     data-eco-tipo-id="<?= (int)$t['id'] ?>"
                     data-eco-tipo-codigo="<?= htmlspecialchars($t['codigo'], ENT_QUOTES, 'UTF-8') ?>"
                     data-eco-tipo-nombre="<?= htmlspecialchars($t['nombre'], ENT_QUOTES, 'UTF-8') ?>"
                     data-eco-tipo-precio="<?= htmlspecialchars((string)($t['precio'] ?? 0), ENT_QUOTES, 'UTF-8') ?>"
                     data-eco-tipo-icono="<?= $icono_m ?>">
                    <div class="eco-card-icon musculo-card-icon">
                        <i class="<?= $icono_m ?>"></i>
                    </div>
                    <p class="eco-card-name"><?= htmlspecialchars($t['nombre'], ENT_QUOTES, 'UTF-8') ?></p>
                    <?php if ($desc_m !== ''): ?>
                        <p class="eco-card-desc"><?= $desc_m ?></p>
                    <?php endif; ?>
                    <span class="eco-card-select-hint"><i class="fa-solid fa-circle-check"></i> Seleccionar</span>
                </div>
                <?php endforeach; ?>

                <?php if (empty($eco_tipos_musculo)): ?>
                    <p style="grid-column:1/-1;text-align:center;color:#aaa;padding:30px 0;">
                        <i class="fa-solid fa-circle-exclamation" style="font-size:2rem;margin-bottom:10px;display:block;"></i>
                        No hay sub-tipos musculoesqueléticos configurados.<br>
                        <small>Ejecuta <code>database/seed_musculo_subtipos.php</code></small>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ── Sub-modal: selección de trimestre obstétrico ─────────────── -->
<div id="eco-modal-seleccionar-obstetrica-eco" class="eco-modal eco-modal-panel-ecografista" aria-hidden="true" role="dialog" aria-labelledby="eco-seleccionar-obstetrica-title" data-eco-modal-static>
    <div class="modal-content-eco-grid">
        <div class="eco-modal-header">
            <button type="button" class="eco-btn-back" id="eco-volver-de-obstetrica">
                <i class="fa-solid fa-arrow-left"></i> Volver
            </button>
            <div class="eco-header-title">
                <h2 id="eco-seleccionar-obstetrica-title"><i class="fa-solid fa-baby" style="color:#ec4899;margin-right:8px;"></i>Ecografía Obstétrica</h2>
                <p id="eco-obstetrica-paciente-info">Seleccione el trimestre del estudio</p>
            </div>
            <button type="button" class="modal-close-btn" data-eco-modal-close aria-label="Cerrar">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="eco-modal-body">
            <div class="obstetrica-cards-grid">
                <?php foreach ($eco_tipos_obstetrica as $t):
                    $icono_o = htmlspecialchars(eco_estilo_tipo_shell($t['nombre'])['icon'] ?? ($t['icono'] ?: 'fa-solid fa-baby'), ENT_QUOTES, 'UTF-8');
                    $desc_o  = htmlspecialchars($t['descripcion'] ?? '', ENT_QUOTES, 'UTF-8');
                ?>
                <div class="eco-card obstetrica-card"
                     tabindex="0"
                     role="button"
                     data-eco-tipo-id="<?= (int)$t['id'] ?>"
                     data-eco-tipo-codigo="<?= htmlspecialchars($t['codigo'], ENT_QUOTES, 'UTF-8') ?>"
                     data-eco-tipo-nombre="<?= htmlspecialchars($t['nombre'], ENT_QUOTES, 'UTF-8') ?>"
                     data-eco-tipo-precio="<?= htmlspecialchars((string)($t['precio'] ?? 0), ENT_QUOTES, 'UTF-8') ?>"
                     data-eco-tipo-icono="<?= $icono_o ?>">
                    <div class="eco-card-icon obstetrica-card-icon">
                        <i class="<?= $icono_o ?>"></i>
                    </div>
                    <p class="eco-card-name"><?= htmlspecialchars($t['nombre'], ENT_QUOTES, 'UTF-8') ?></p>
                    <?php if ($desc_o !== ''): ?>
                        <p class="eco-card-desc"><?= $desc_o ?></p>
                    <?php endif; ?>
                    <span class="eco-card-select-hint"><i class="fa-solid fa-circle-check"></i> Seleccionar</span>
                </div>
                <?php endforeach; ?>

                <?php if (empty($eco_tipos_obstetrica)): ?>
                    <p style="grid-column:1/-1;text-align:center;color:#aaa;padding:30px 0;">
                        <i class="fa-solid fa-circle-exclamation" style="font-size:2rem;margin-bottom:10px;display:block;"></i>
                        No hay sub-tipos obstétricos configurados.<br>
                        <small>Ejecuta <code>database/seed_obstetrica_subtipos.php</code></small>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ── Sub-modal: selección tipo de partes blandas ─────────────── -->
<div id="eco-modal-seleccionar-pblandas-eco" class="eco-modal eco-modal-panel-ecografista" aria-hidden="true" role="dialog" aria-labelledby="eco-seleccionar-pblandas-title" data-eco-modal-static>
    <div class="modal-content-eco-grid">
        <div class="eco-modal-header">
            <button type="button" class="eco-btn-back" id="eco-volver-de-pblandas">
                <i class="fa-solid fa-arrow-left"></i> Volver
            </button>
            <div class="eco-header-title">
                <h2 id="eco-seleccionar-pblandas-title"><i class="fa-solid fa-hand-holding-medical" style="color:#f59e0b;margin-right:8px;"></i>Ecografía de Partes Blandas</h2>
                <p id="eco-pblandas-paciente-info">Seleccione el tipo de estudio</p>
            </div>
            <button type="button" class="modal-close-btn" data-eco-modal-close aria-label="Cerrar">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="eco-modal-body">
            <div class="pblandas-cards-grid">
                <?php foreach ($eco_tipos_pblandas as $t):
                    $icono_p = htmlspecialchars(eco_estilo_tipo_shell($t['nombre'])['icon'] ?? ($t['icono'] ?: 'fa-solid fa-hand-holding-medical'), ENT_QUOTES, 'UTF-8');
                    $desc_p  = htmlspecialchars($t['descripcion'] ?? '', ENT_QUOTES, 'UTF-8');
                ?>
                <div class="eco-card pblandas-card"
                     tabindex="0"
                     role="button"
                     data-eco-tipo-id="<?= (int)$t['id'] ?>"
                     data-eco-tipo-codigo="<?= htmlspecialchars($t['codigo'], ENT_QUOTES, 'UTF-8') ?>"
                     data-eco-tipo-nombre="<?= htmlspecialchars($t['nombre'], ENT_QUOTES, 'UTF-8') ?>"
                     data-eco-tipo-precio="<?= htmlspecialchars((string)($t['precio'] ?? 0), ENT_QUOTES, 'UTF-8') ?>"
                     data-eco-tipo-icono="<?= $icono_p ?>">
                    <div class="eco-card-icon pblandas-card-icon">
                        <i class="<?= $icono_p ?>"></i>
                    </div>
                    <p class="eco-card-name"><?= htmlspecialchars($t['nombre'], ENT_QUOTES, 'UTF-8') ?></p>
                    <?php if ($desc_p !== ''): ?>
                        <p class="eco-card-desc"><?= $desc_p ?></p>
                    <?php endif; ?>
                    <span class="eco-card-select-hint"><i class="fa-solid fa-circle-check"></i> Seleccionar</span>
                </div>
                <?php endforeach; ?>

                <?php if (empty($eco_tipos_pblandas)): ?>
                    <p style="grid-column:1/-1;text-align:center;color:#aaa;padding:30px 0;">
                        <i class="fa-solid fa-circle-exclamation" style="font-size:2rem;margin-bottom:10px;display:block;"></i>
                        No hay sub-tipos de partes blandas configurados.<br>
                        <small>Ejecuta <code>database/seed_partes_blandas_subtipos.php</code></small>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div id="eco-modal-formulario-estudio-eco" class="eco-modal eco-modal-panel-ecografista" aria-hidden="true" role="dialog" aria-labelledby="modal-form-eco-titulo" data-eco-modal-static>
    <div class="modal-content-form-eco">

        <div class="modal-form-eco-header">
            <button type="button" class="eco-btn-back" id="eco-volver-tipos-ecografia">
                <i class="fa-solid fa-arrow-left"></i> Volver
            </button>
            <div class="eco-modal-tipo-icon" id="modal-form-eco-icon">
                <i class="fa-solid fa-wave-square"></i>
            </div>
            <div class="eco-header-tipo-info">
                <h2 id="modal-form-eco-titulo">Formulario de Estudio</h2>
                <p id="modal-form-eco-paciente">Paciente: —</p>
            </div>
            <button type="button" class="modal-close-btn" data-eco-modal-close aria-label="Cerrar">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="modal-form-eco-feedback-bar" id="modal-form-eco-feedback" style="display:none;"></div>

        <div class="modal-form-eco-body" id="modal-form-eco-body">
            <div class="modal-form-eco-loader">
                <i class="fa-solid fa-spinner fa-spin"></i>
                <p>Cargando formulario…</p>
            </div>
        </div>

    </div>
</div>

<!-- ── Modal: registro/facturación de servicios sin ecografía (consulta, citología, procesamiento) ──
     Va DESPUÉS del formulario de estudio en el DOM a propósito: en modo "registro" ambas quedan
     abiertas a la vez y esta debe pintarse por encima (mismo z-index; gana el orden en el DOM). ── -->
<div id="eco-modal-servicio-eco" class="eco-modal" aria-hidden="true" role="dialog" aria-labelledby="eco-servicio-title" data-eco-modal-static>
    <div class="eco-modal__dialog" style="max-width:560px;width:100%;">
        <div class="eco-modal__main">
            <button type="button" class="eco-modal__close" data-eco-modal-close aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
            <h4 class="eco-modal__title" id="eco-servicio-title"><i class="fa-solid fa-stethoscope" style="color:var(--accent);margin-right:7px;"></i>Registro de servicios</h4>
            <p class="eco-modal__body-text" id="eco-servicio-paciente" style="margin:0 0 14px;">Paciente: —</p>

            <div class="eco-fact-banner" style="margin:0 0 14px;">
                <div class="eco-fact-banner__head"><i class="fa-solid fa-receipt"></i> <span id="eco-servicio-fact-head">Facturación de servicios</span></div>
                <ul class="eco-fact-banner__lines" id="eco-servicio-lineas"></ul>
                <div class="eco-fact-banner__total" id="eco-servicio-total-wrap"><span>Total a facturar</span><strong id="eco-servicio-total">$0</strong></div>
                <p class="eco-exp-serv-note" id="eco-servicio-nota-registro" style="display:none;margin-top:9px;"><i class="fa-solid fa-circle-info"></i> Estos servicios se facturan junto con el informe del estudio; aquí solo se registra la atención.</p>
            </div>

            <label for="eco-servicio-obs" style="display:block;font-size:12.5px;font-weight:700;color:var(--text-secondary);margin:0 0 6px;">Motivo / observaciones <span id="eco-servicio-obs-req" style="font-weight:500;color:var(--text-muted);">(opcional)</span></label>
            <textarea id="eco-servicio-obs" rows="4" placeholder="Ej.: control post-estudio, revisión de resultados, toma de muestra…"
                      style="width:100%;box-sizing:border-box;padding:11px 13px;border:1.5px solid var(--border);border-radius:10px;background:var(--bg-surface);color:var(--text-primary);font-family:inherit;font-size:13.5px;resize:vertical;"></textarea>
            <p class="eco-modal__body-text" style="margin:7px 0 0;font-size:11.5px;color:var(--text-muted);"><i class="fa-solid fa-notes-medical" style="margin-right:4px;"></i>Si escribes observaciones, quedan guardadas como nota de sesión del paciente.</p>

            <div id="eco-servicio-feedback" style="display:none;margin-top:12px;padding:10px 13px;border-radius:10px;font-size:13px;"></div>

            <div class="eco-modal__footer" style="margin-top:18px;display:flex;gap:10px;justify-content:space-between;align-items:center;">
                <button type="button" class="btn-secondary" id="eco-servicio-volver" style="margin-left:0;"><i class="fa-solid fa-arrow-left"></i> Volver</button>
                <div style="display:flex;gap:10px;">
                    <button type="button" class="btn-secondary" data-eco-modal-close style="margin-left:0;">Cancelar</button>
                    <button type="button" class="btn-primary" id="eco-servicio-guardar"><i class="fa-solid fa-circle-check"></i> <span id="eco-servicio-guardar-txt">Guardar y facturar</span></button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/modal_programar_cita_ecografista.php'; ?>

<!-- Confirmación: firmar antes de imprimir un informe no firmado (rol ecografista) -->
<div id="eco-modal-firmar-antes-eco" class="eco-modal" aria-hidden="true" role="dialog" aria-labelledby="eco-firmar-antes-title">
    <div class="eco-modal__dialog" style="max-width:460px;width:100%;">
        <div class="eco-modal__main">
            <button type="button" class="eco-modal__close" data-eco-modal-close aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
            <h4 class="eco-modal__title" id="eco-firmar-antes-title">
                <i class="fa-solid fa-file-signature" style="margin-right:8px;color:#0369a1;"></i> Informe sin firmar
            </h4>
            <p class="eco-modal__body-text">
                Este informe aún no está firmado. Debes firmarlo antes de imprimirlo.<br><br>
                Al firmar se sella criptográficamente y <strong>ya no podrá editarse</strong>.
            </p>
            <div class="eco-modal__footer" style="justify-content:flex-end;gap:10px;">
                <button type="button" class="eco-btn-cancel" data-eco-modal-close>Cancelar</button>
                <button type="button" class="eco-btn-submit" id="eco-firmar-antes-confirm">
                    <i class="fa-solid fa-signature"></i> Firmar e imprimir
                </button>
            </div>
        </div>
    </div>
</div>

