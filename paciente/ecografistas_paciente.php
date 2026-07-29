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

$profesionales = [];
$res = $conex->query("
    SELECT u.id, u.nombre_completo,
           (SELECT GROUP_CONCAT(e.nombre ORDER BY e.nombre SEPARATOR ', ')
              FROM usuario_especialidades ue
              JOIN especialidades e ON e.id = ue.especialidad_id
             WHERE ue.usuario_id = u.id) AS especialidades,
           (SELECT COUNT(DISTINCT c.paciente_id) FROM citas c
            WHERE c.ecografista_id = u.id AND c.estado IN ('confirmada', 'completada')) AS pacientes_atendidos
    FROM usuarios u
    WHERE u.rol = 'ecografista' AND u.estado = 'aprobado'
    ORDER BY u.nombre_completo ASC
");
if ($res) {
    $profesionales = $res->fetch_all(MYSQLI_ASSOC);
    $res->free();
}

/* Iniciales para el avatar */
function eco_iniciales(string $nombre): string
{
    $ini = '';
    foreach (preg_split('/\s+/', trim($nombre)) as $w) {
        if ($w !== '' && mb_strlen($ini) < 2) $ini .= mb_strtoupper(mb_substr($w, 0, 1));
    }
    return $ini !== '' ? $ini : 'E';
}

/* Procesar especialidades por profesional + catálogo global para filtros */
$todas_esp = []; // normalizada => etiqueta visible
foreach ($profesionales as &$p) {
    $lista = array_values(array_filter(array_map('trim', preg_split('/[,;]/', (string)$p['especialidades']))));
    $p['_chips'] = $lista;
    $norm = [];
    foreach ($lista as $e) {
        $k = mb_strtolower($e);
        $norm[] = $k;
        if (!isset($todas_esp[$k])) $todas_esp[$k] = $e;
    }
    $p['_specs_norm'] = $norm;
}
unset($p);
ksort($todas_esp);

$total_eco = count($profesionales);
$total_pac = array_sum(array_map(fn($p) => (int)$p['pacientes_atendidos'], $profesionales));

$page_title     = 'Ecografistas';
$page_subtitle  = 'Conoce al equipo de imagen diagnóstica de la clínica';
$active_section = 'psicologos';

ob_start();
?>

<style>
.eco-toolbar { display:flex; flex-wrap:wrap; gap:12px; align-items:center; justify-content:space-between; margin-bottom:18px; }
.eco-search { position:relative; flex:1; min-width:230px; max-width:360px; }
.eco-search i { position:absolute; left:13px; top:50%; transform:translateY(-50%); color:var(--text-muted); font-size:13px; pointer-events:none; }
.eco-search input { width:100%; padding:10px 12px 10px 36px; border:1.5px solid var(--border); border-radius:10px; font-family:inherit; font-size:13.5px; background:var(--bg-surface); color:var(--text-primary); box-sizing:border-box; transition:border-color .18s ease, box-shadow .18s ease; }
.eco-search input:focus { outline:none; border-color:var(--accent); box-shadow:0 0 0 3px rgba(2,177,244,.12); }
.eco-filters { display:flex; gap:6px; flex-wrap:wrap; }
.eco-filter { display:inline-flex; align-items:center; gap:6px; padding:8px 14px; border-radius:999px; font-size:12.5px; font-weight:600; color:var(--text-secondary); background:var(--bg-surface); border:1px solid var(--border); cursor:pointer; transition:all .18s ease; white-space:nowrap; }
.eco-filter:hover { color:var(--text-primary); border-color:rgba(2,177,244,.35); }
.eco-filter.is-active { background:var(--accent); color:#fff; border-color:var(--accent); box-shadow:0 4px 12px rgba(2,177,244,.28); }

.eco-doc-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(290px,1fr)); gap:16px; }
.eco-doc-card { background:var(--bg-surface); border:1px solid var(--border); border-radius:var(--radius-lg); padding:20px; display:flex; flex-direction:column; gap:14px; transition:box-shadow .2s ease, transform .2s ease, border-color .2s ease; }
.eco-doc-card:hover { box-shadow:var(--shadow); transform:translateY(-3px); border-color:rgba(2,177,244,.3); }
.eco-doc-top { display:flex; align-items:center; gap:14px; }
/* Plano: en una rejilla de siete, siete bloques de color macizo compiten
   entre sí y con el nombre, que es lo que se busca. Con fondo suave las
   iniciales siguen identificando sin gritar. */
.eco-doc-avatar { width:52px; height:52px; border-radius:14px; flex-shrink:0; display:flex; align-items:center; justify-content:center; font-size:17px; font-weight:700; color:#0369a1; letter-spacing:.5px; background:var(--accent-soft); border:1px solid rgba(2,177,244,.22); transition:background .2s ease, color .2s ease, border-color .2s ease; }
.eco-doc-card:hover .eco-doc-avatar { background:#0369a1; color:#fff; border-color:#0369a1; }
[data-theme="dark"] .eco-doc-avatar { color:#7dd3fc; }
[data-theme="dark"] .eco-doc-card:hover .eco-doc-avatar { background:#0284c7; color:#fff; border-color:#0284c7; }
.eco-doc-id { min-width:0; flex:1; }
.eco-doc-name { font-size:15px; font-weight:700; color:var(--text-primary); margin:0; line-height:1.25; }
.eco-doc-role { font-size:12px; color:var(--text-muted); margin:3px 0 0; display:flex; align-items:center; gap:6px; }
.eco-doc-chips { display:flex; flex-wrap:wrap; gap:6px; min-height:24px; }
.eco-doc-chip { font-size:11px; font-weight:600; padding:4px 11px; border-radius:999px; background:var(--accent-soft); color:var(--accent-text); border:1px solid rgba(2,177,244,.2); }
.eco-doc-chip--muted { background:var(--bg-muted); color:var(--text-muted); border-color:var(--border); }
.eco-doc-meta { display:flex; align-items:center; gap:8px; font-size:12.5px; color:var(--text-secondary); padding-top:13px; border-top:1px dashed var(--border); }
/* --accent sobre blanco da 2.4:1: para un icono de 12px se veía desvaído.
   El escalón oscuro del mismo azul llega a 4.5:1. */
.eco-doc-meta i { color:var(--accent-text); }
.eco-doc-meta strong { color:var(--text-primary); }
.eco-doc-actions { display:flex; gap:8px; margin-top:auto; }
.eco-doc-btn { flex:1; display:inline-flex; align-items:center; justify-content:center; gap:7px; padding:10px 12px; border-radius:10px; font-size:12.5px; font-weight:600; cursor:pointer; text-decoration:none; transition:all .18s ease; border:1.5px solid var(--border); white-space:nowrap; }
.eco-doc-btn--ghost { background:var(--bg-surface); color:var(--text-secondary); }
.eco-doc-btn--ghost:hover { border-color:var(--accent); color:var(--accent-text); background:var(--accent-soft); }
.eco-doc-btn--solid { background:var(--accent); color:#fff; border-color:var(--accent); }
.eco-doc-btn--solid:hover { filter:brightness(.95); }

.eco-doc-empty { text-align:center; padding:48px 24px; color:var(--text-muted); }
.eco-doc-empty > i { font-size:42px; color:var(--accent); opacity:.5; margin-bottom:14px; display:block; }

/* Modal de perfil */
.pd-head { display:flex; align-items:center; gap:14px; margin-bottom:18px; padding-right:30px; }
/* El del modal, igual que el de la tarjeta: es el mismo profesional y no
   debería cambiar de aspecto al abrirlo. */
.pd-avatar { width:52px; height:52px; border-radius:14px; flex-shrink:0; display:flex; align-items:center; justify-content:center; font-size:17px; font-weight:700; color:#0369a1; letter-spacing:.5px; background:var(--accent-soft); border:1px solid rgba(2,177,244,.22); }
[data-theme="dark"] .pd-avatar { color:#7dd3fc; }
.pd-name { margin:0; font-size:1.1rem; font-weight:700; color:var(--text-primary); }
.pd-role { margin:3px 0 0; font-size:12.5px; color:var(--text-muted); display:flex; align-items:center; gap:6px; }
.pd-rows { background:var(--bg-muted); border:1px solid var(--border); border-radius:12px; padding:2px 14px; }
.pd-row { display:flex; gap:12px; padding:11px 0; border-bottom:1px dashed var(--border); align-items:flex-start; }
.pd-row:last-child { border-bottom:none; }
.pd-row__icon { width:28px; height:28px; border-radius:8px; background:var(--accent-soft); color:var(--accent-text); display:flex; align-items:center; justify-content:center; font-size:12px; flex-shrink:0; margin-top:1px; }
.pd-row__text { min-width:0; flex:1; }
.pd-row__label { font-size:10.5px; color:var(--text-muted); text-transform:uppercase; letter-spacing:.4px; font-weight:600; }
.pd-row__value { font-size:13.5px; color:var(--text-primary); margin-top:2px; line-height:1.45; word-break:break-word; }
.pd-row__value .eco-doc-chip { display:inline-block; margin:2px 4px 2px 0; }
.pd-foot { margin-top:18px; }
.pd-foot .btn-primary { width:100%; justify-content:center; }
.pd-loading { text-align:center; color:var(--text-muted); padding:24px 12px; }
</style>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-card-icon"><i class="fa-solid fa-user-doctor"></i></div>
        <p class="stat-card-label">Ecografistas</p>
        <p class="stat-card-value accent"><?= $total_eco ?></p>
        <p class="stat-card-sub">disponibles para tu estudio</p>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon" style="background:rgba(34,197,94,.12);color:#15803d;"><i class="fa-solid fa-users"></i></div>
        <p class="stat-card-label">Pacientes atendidos</p>
        <p class="stat-card-value" style="color:#15803d;"><?= $total_pac ?></p>
        <p class="stat-card-sub">confianza del equipo</p>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon" style="background:rgba(139,92,246,.12);color:#7c3aed;"><i class="fa-solid fa-certificate"></i></div>
        <p class="stat-card-label">Especialidades</p>
        <p class="stat-card-value" style="color:#7c3aed;"><?= count($todas_esp) ?></p>
        <p class="stat-card-sub">áreas que cubre el equipo</p>
    </div>
    <a href="<?= eco_url('solicitar-cita') ?>" class="stat-card" style="text-decoration:none;">
        <div class="stat-card-icon"><i class="fa-solid fa-file-circle-plus"></i></div>
        <p class="stat-card-label">Acción rápida</p>
        <p class="stat-card-value accent" style="font-size:18px;">Solicitar cita</p>
        <p class="stat-card-sub">elige a tu ecografista</p>
    </a>
</div>

<div class="card">
    <div class="card-header">
        <h3><i class="fa-solid fa-user-doctor" style="margin-right:8px;color:var(--accent);"></i> Nuestro equipo de ecografistas</h3>
    </div>
    <p style="color:var(--text-secondary);margin:0 0 18px;font-size:13.5px;">
        Profesionales disponibles para programar tus estudios ecográficos. Consulta su perfil o solicita una cita directamente.
    </p>

    <?php if (empty($profesionales)): ?>
        <div class="eco-doc-empty">
            <i class="fa-solid fa-user-doctor"></i>
            <p style="margin:0;font-weight:600;color:var(--text-secondary);">No hay ecografistas disponibles en este momento</p>
        </div>
    <?php else: ?>

        <div class="eco-toolbar">
            <div class="eco-search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="eco-search-input" placeholder="Buscar por nombre o especialidad…" autocomplete="off">
            </div>
            <?php if (count($todas_esp) > 1): ?>
                <div class="eco-filters" id="eco-filters">
                    <button type="button" class="eco-filter is-active" data-spec="">Todas</button>
                    <?php foreach ($todas_esp as $norm => $label): ?>
                        <button type="button" class="eco-filter" data-spec="<?= htmlspecialchars($norm, ENT_QUOTES) ?>"><?= htmlspecialchars($label) ?></button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="eco-doc-grid" id="eco-doc-grid">
            <?php foreach ($profesionales as $p):
                $chips    = $p['_chips'];
                $busca    = mb_strtolower($p['nombre_completo'] . ' ' . (string)$p['especialidades']);
                $specsAttr = implode(',', $p['_specs_norm']);
            ?>
                <div class="eco-doc-card" data-search="<?= htmlspecialchars($busca, ENT_QUOTES) ?>" data-specs="<?= htmlspecialchars($specsAttr, ENT_QUOTES) ?>">
                    <div class="eco-doc-top">
                        <div class="eco-doc-avatar"><?= htmlspecialchars(eco_iniciales($p['nombre_completo'])) ?></div>
                        <div class="eco-doc-id">
                            <p class="eco-doc-name"><?= htmlspecialchars($p['nombre_completo']) ?></p>
                            <p class="eco-doc-role"><i class="fa-solid fa-stethoscope"></i> Ecografista</p>
                        </div>
                    </div>

                    <div class="eco-doc-chips">
                        <?php if (!empty($chips)): ?>
                            <?php foreach (array_slice($chips, 0, 3) as $chip): ?>
                                <span class="eco-doc-chip"><?= htmlspecialchars($chip) ?></span>
                            <?php endforeach; ?>
                            <?php if (count($chips) > 3): ?>
                                <span class="eco-doc-chip eco-doc-chip--muted">+<?= count($chips) - 3 ?> más</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="eco-doc-chip eco-doc-chip--muted">Especialidad por confirmar</span>
                        <?php endif; ?>
                    </div>

                    <div class="eco-doc-meta">
                        <i class="fa-solid fa-users"></i>
                        <span><strong><?= (int)$p['pacientes_atendidos'] ?></strong> pacientes atendidos</span>
                    </div>

                    <div class="eco-doc-actions">
                        <button type="button" class="eco-doc-btn eco-doc-btn--ghost" onclick="abrirModalProfesionalShell(<?= (int)$p['id'] ?>)">
                            <i class="fa-solid fa-id-card"></i> Ver perfil
                        </button>
                        <a class="eco-doc-btn eco-doc-btn--solid" href="<?= eco_url('solicitar-cita') ?>?ecografista_id=<?= (int)$p['id'] ?>">
                            <i class="fa-solid fa-calendar-plus"></i> Solicitar
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>

            <div id="eco-empty-filter" class="eco-doc-empty" style="display:none;">
                <i class="fa-solid fa-magnifying-glass"></i>
                <p style="margin:0;font-weight:600;color:var(--text-secondary);">No encontramos ecografistas con esos criterios</p>
                <p style="margin:6px 0 0;font-size:13px;">Prueba con otro nombre o especialidad.</p>
            </div>
        </div>

    <?php endif; ?>
</div>

<div id="eco-modal-prof-shell" class="eco-modal" aria-hidden="true" role="dialog">
    <div class="eco-modal__dialog" style="max-width:460px;">
        <div class="eco-modal__main" style="padding-top:24px;">
            <button type="button" class="eco-modal__close" onclick="cerrarModalProfesionalShell()" aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
            <div class="pd-head">
                <div class="pd-avatar" id="prof-shell-avatar">…</div>
                <div>
                    <h2 class="pd-name" id="prof-shell-nombre">…</h2>
                    <p class="pd-role" id="prof-shell-rol">…</p>
                </div>
            </div>
            <div id="prof-shell-body"><p class="pd-loading"><i class="fa-solid fa-spinner fa-spin"></i> Cargando…</p></div>
        </div>
    </div>
</div>

<?php
$page_content = ob_get_clean();

$page_scripts_extra = <<<'HTML'
<script>
(function () {
    /* ── Búsqueda y filtro por especialidad ── */
    var search  = document.getElementById('eco-search-input');
    var cards   = Array.prototype.slice.call(document.querySelectorAll('.eco-doc-card'));
    var filters = document.querySelectorAll('#eco-filters .eco-filter');
    var emptyF  = document.getElementById('eco-empty-filter');
    var activeSpec = '';

    function aplicar() {
        var q = (search && search.value || '').trim().toLowerCase();
        var visibles = 0;
        cards.forEach(function (c) {
            var okBusca = !q || (c.getAttribute('data-search') || '').indexOf(q) !== -1;
            var specs = (c.getAttribute('data-specs') || '').split(',');
            var okSpec = !activeSpec || specs.indexOf(activeSpec) !== -1;
            var show = okBusca && okSpec;
            c.style.display = show ? '' : 'none';
            if (show) visibles++;
        });
        if (emptyF) emptyF.style.display = (visibles === 0) ? '' : 'none';
    }

    if (search) search.addEventListener('input', aplicar);
    filters.forEach(function (f) {
        f.addEventListener('click', function () {
            filters.forEach(function (x) { x.classList.remove('is-active'); });
            f.classList.add('is-active');
            activeSpec = f.getAttribute('data-spec') || '';
            aplicar();
        });
    });

    /* ── Modal de perfil ── */
    var modalId = 'eco-modal-prof-shell';
    var overlay = document.getElementById(modalId);

    function esc(v) {
        if (v == null) return '';
        return String(v).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    var na = '<span style="color:var(--text-muted);">No especificado</span>';
    function val(v) { return v ? esc(v) : na; }
    function iniciales(nombre) {
        var ini = '';
        String(nombre || '').trim().split(/\s+/).forEach(function (w) {
            if (w && ini.length < 2) ini += w.charAt(0).toUpperCase();
        });
        return ini || 'E';
    }
    function chipsHtml(str) {
        if (!str) return na;
        var parts = String(str).split(/[,;]/).map(function (s) { return s.trim(); }).filter(Boolean);
        if (!parts.length) return na;
        return parts.map(function (s) { return '<span class="eco-doc-chip">' + esc(s) + '</span>'; }).join('');
    }
    function row(icon, label, value) {
        return '<div class="pd-row"><div class="pd-row__icon"><i class="fa-solid ' + icon + '"></i></div>'
            + '<div class="pd-row__text"><div class="pd-row__label">' + label + '</div>'
            + '<div class="pd-row__value">' + value + '</div></div></div>';
    }

    window.abrirModalProfesionalShell = function (id) {
        if (!overlay) return;
        document.getElementById('prof-shell-avatar').textContent = '…';
        document.getElementById('prof-shell-nombre').textContent = '…';
        document.getElementById('prof-shell-rol').textContent = '…';
        document.getElementById('prof-shell-body').innerHTML = '<p class="pd-loading"><i class="fa-solid fa-spinner fa-spin"></i> Cargando…</p>';
        if (typeof EcoModal !== 'undefined') EcoModal.open(modalId);
        fetch((window.ECO_BASE || '') + 'api/get_professional_details.php?id=' + encodeURIComponent(id))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var bodyEl = document.getElementById('prof-shell-body');
                if (data.error) {
                    bodyEl.innerHTML = '<p style="color:#b91c1c;padding:12px;">' + esc(data.error) + '</p>';
                    return;
                }
                document.getElementById('prof-shell-avatar').textContent = iniciales(data.nombre_completo);
                document.getElementById('prof-shell-nombre').textContent = data.nombre_completo || '';
                document.getElementById('prof-shell-rol').innerHTML = '<i class="fa-solid fa-stethoscope"></i> ' + esc(data.rol_formateado || 'Ecografista');

                var html = '<div class="pd-rows">';
                html += row('fa-certificate', 'Especialidades', chipsHtml(data.especialidades));
                html += row('fa-envelope', 'Correo de contacto', val(data.correo));
                html += row('fa-calendar-check', 'Miembro desde', val(data.fecha_registro_formateada));
                html += row('fa-circle-check', 'Estado', val(data.estado_formateado));
                html += '</div>';
                html += '<div class="pd-foot"><a class="btn-primary" href="<?= eco_url('solicitar-cita') ?>?ecografista_id=' + encodeURIComponent(id) + '"><i class="fa-solid fa-calendar-plus"></i> Solicitar cita con este ecografista</a></div>';
                bodyEl.innerHTML = html;
            })
            .catch(function () {
                document.getElementById('prof-shell-body').innerHTML = '<p style="color:#b91c1c;padding:12px;">No se pudo cargar el perfil.</p>';
            });
    };

    window.cerrarModalProfesionalShell = function () {
        if (typeof EcoModal !== 'undefined') EcoModal.close(modalId);
    };
})();
</script>
HTML;

include __DIR__ . '/../layouts/shell.php';
