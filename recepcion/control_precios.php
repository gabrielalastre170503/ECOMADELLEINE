<?php
/**
 * Control de precios — recepción.
 *
 * Edita en un solo sitio las tarifas que usa la facturación:
 *   · ecografías  → tipos_ecografias.precio
 *   · servicios y promociones → precios_servicios.precio
 *
 * Guarda campo a campo contra api/guardar_precio.php (sin botón global): así
 * un error en una fila no impide guardar el resto.
 */
session_start();
include __DIR__ . '/../core/conexion.php';
require_once __DIR__ . '/../lib/facturacion/facturacion.php';
require_once __DIR__ . '/../lib/informes/catalogo.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . eco_url('login'));
    exit;
}
// El ecografista también entra: la sección sustituye a "Notas de sesión" en su
// menú. Sigue fuera del alcance del paciente.
if (!in_array($_SESSION['rol'] ?? '', ['recepcionista', 'administrador', 'ecografista'], true)) {
    header('Location: ' . eco_url('dashboard'));
    exit;
}

$cp_estudios = eco_catalogo_tipos_activos($conex);
$cp_catalogo = eco_precios_catalogo(true);

$cp_servicios = [];
$cp_promos    = [];
foreach ($cp_catalogo as $clave => $d) {
    $d['clave'] = $clave;
    if ($d['tipo'] === 'promocion') {
        $cp_promos[] = $d;
    } else {
        $cp_servicios[] = $d;
    }
}

$cp_precios_eco = array_map(static fn($t) => (float)$t['precio'], $cp_estudios);
$cp_min = $cp_precios_eco ? min($cp_precios_eco) : 0.0;
$cp_max = $cp_precios_eco ? max($cp_precios_eco) : 0.0;

$page_title     = 'Control de precios';
$page_subtitle   = 'Tarifas de ecografías, servicios y promociones';
$active_section = 'control-precios';

$page_head_extra = '<link rel="stylesheet" href="assets/css/recepcion/control-precios.css?v=auto">';

ob_start();
?>

<div class="cp-resumen">
    <div class="stat-card">
        <div class="stat-card-icon" style="background:var(--accent-soft);color:var(--accent-text);"><i class="fa-solid fa-wave-square"></i></div>
        <p class="stat-card-label">Estudios activos</p>
        <p class="stat-card-value" style="color:var(--accent-text);"><?= count($cp_estudios) ?></p>
        <p class="stat-card-sub">Entre <?= htmlspecialchars(eco_money($cp_min)) ?> y <?= htmlspecialchars(eco_money($cp_max)) ?></p>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon" style="background:rgba(21,128,61,.12);color:#15803d;"><i class="fa-solid fa-hand-holding-medical"></i></div>
        <p class="stat-card-label">Servicios</p>
        <p class="stat-card-value" style="color:#15803d;"><?= count($cp_servicios) ?></p>
        <p class="stat-card-sub">Consulta, citología y otros</p>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon" style="background:rgba(245,158,11,.14);color:#b45309;"><i class="fa-solid fa-tags"></i></div>
        <p class="stat-card-label">Promociones</p>
        <p class="stat-card-value warning"><?= count($cp_promos) ?></p>
        <p class="stat-card-sub">Sustituyen el precio de sus partes</p>
    </div>
</div>

<section class="card cp-seccion">
    <div class="cp-seccion__head">
        <h3 class="cp-seccion__title"><i class="fa-solid fa-hand-holding-medical"></i> Servicios</h3>
        <span class="cp-seccion__meta"><?= count($cp_servicios) ?> servicios</span>
    </div>
    <p class="cp-seccion__note">Se marcan como casillas al registrar o programar una atención y se suman al total.</p>
    <?php if (empty($cp_servicios)): ?>
        <p class="cp-vacio">No hay servicios configurados.</p>
    <?php else: ?>
        <ul class="cp-lista">
            <?php foreach ($cp_servicios as $s): ?>
                <li class="cp-fila">
                    <span class="cp-fila__icono" aria-hidden="true"><i class="<?= htmlspecialchars($s['icono']) ?>"></i></span>
                    <span class="cp-fila__texto">
                        <span class="cp-fila__nombre"><?= htmlspecialchars($s['etiqueta']) ?></span>
                        <span class="cp-fila__cat">Servicio adicional</span>
                    </span>
                    <span class="cp-precio">
                        <span class="cp-precio__simbolo">$</span>
                        <input type="number" min="0" step="0.01" inputmode="decimal"
                               value="<?= htmlspecialchars(number_format((float)$s['precio'], 2, '.', '')) ?>"
                               data-cp-origen="servicio" data-cp-clave="<?= htmlspecialchars($s['clave']) ?>"
                               aria-label="Precio de <?= htmlspecialchars($s['etiqueta']) ?>">
                    </span>
                    <span class="cp-fila__estado" aria-hidden="true"></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<section class="card cp-seccion">
    <div class="cp-seccion__head">
        <h3 class="cp-seccion__title"><i class="fa-solid fa-tags"></i> Promociones</h3>
        <span class="cp-seccion__meta"><?= count($cp_promos) ?> promociones</span>
    </div>
    <p class="cp-seccion__note">
        El precio de la promoción <strong>sustituye</strong> al de sus componentes cuando se dan juntos.
        Por ejemplo, si una atención lleva ecografía y consulta, se cobra el precio de «Eco + Consulta»
        en lugar de la suma de ambos.
    </p>
    <?php if (empty($cp_promos)): ?>
        <p class="cp-vacio">No hay promociones configuradas.</p>
    <?php else: ?>
        <ul class="cp-lista cp-lista--anchas">
            <?php foreach ($cp_promos as $p): ?>
                <li class="cp-fila cp-fila--promo">
                    <span class="cp-fila__icono" aria-hidden="true"><i class="<?= htmlspecialchars($p['icono']) ?>"></i></span>
                    <span class="cp-fila__texto">
                        <span class="cp-fila__nombre"><?= htmlspecialchars($p['etiqueta']) ?></span>
                        <span class="cp-fila__cat">
                            <?= $p['clave'] === 'combo_cito'
                                ? 'Citología + procesamiento + eco pélvica'
                                : 'Ecografía más cara + consulta' ?>
                        </span>
                    </span>
                    <span class="cp-precio">
                        <span class="cp-precio__simbolo">$</span>
                        <input type="number" min="0" step="0.01" inputmode="decimal"
                               value="<?= htmlspecialchars(number_format((float)$p['precio'], 2, '.', '')) ?>"
                               data-cp-origen="servicio" data-cp-clave="<?= htmlspecialchars($p['clave']) ?>"
                               aria-label="Precio de <?= htmlspecialchars($p['etiqueta']) ?>">
                    </span>
                    <span class="cp-fila__estado" aria-hidden="true"></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<section class="card cp-seccion">
    <div class="cp-seccion__head">
        <h3 class="cp-seccion__title"><i class="fa-solid fa-wave-square"></i> Ecografías</h3>
        <span class="cp-seccion__meta"><span id="cp-eco-visibles"><?= count($cp_estudios) ?></span> de <?= count($cp_estudios) ?></span>
    </div>
    <p class="cp-seccion__note">Tarifa de cada tipo de estudio del catálogo.</p>
    <?php if (empty($cp_estudios)): ?>
        <p class="cp-vacio">No hay estudios activos en el catálogo.</p>
    <?php else: ?>
        <div class="cp-buscar">
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
            <input type="search" id="cp-buscar-eco" placeholder="Buscar estudio por nombre o categoría…" autocomplete="off">
        </div>
        <ul class="cp-lista" id="cp-lista-eco">
            <?php foreach ($cp_estudios as $t):
                $busca = mb_strtolower(($t['nombre'] ?? '') . ' ' . ($t['categoria'] ?? ''), 'UTF-8');
            ?>
                <li class="cp-fila" data-cp-busca="<?= htmlspecialchars($busca) ?>">
                    <span class="cp-fila__icono" aria-hidden="true"><i class="<?= htmlspecialchars($t['icono'] ?: 'fa-solid fa-wave-square') ?>"></i></span>
                    <span class="cp-fila__texto">
                        <span class="cp-fila__nombre"><?= htmlspecialchars($t['nombre']) ?></span>
                        <span class="cp-fila__cat"><?= htmlspecialchars($t['categoria'] ?: 'Sin categoría') ?></span>
                    </span>
                    <span class="cp-precio">
                        <span class="cp-precio__simbolo">$</span>
                        <input type="number" min="0" step="0.01" inputmode="decimal"
                               value="<?= htmlspecialchars(number_format((float)$t['precio'], 2, '.', '')) ?>"
                               data-cp-origen="estudio" data-cp-clave="<?= (int)$t['id'] ?>"
                               aria-label="Precio de <?= htmlspecialchars($t['nombre']) ?>">
                    </span>
                    <span class="cp-fila__estado" aria-hidden="true"></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<div class="cp-toast" id="cp-toast" role="status" aria-live="polite"></div>

<?php
$page_content = ob_get_clean();

$page_scripts_extra = <<<'HTML'
<script>
(function () {
    var toast = document.getElementById('cp-toast');
    var toastTimer = null;

    function avisar(msg, esError) {
        if (!toast) return;
        toast.textContent = msg;
        toast.classList.toggle('is-error', !!esError);
        toast.classList.add('is-visible');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () { toast.classList.remove('is-visible'); }, 3200);
    }

    function marcar(fila, clase, icono) {
        var el = fila.querySelector('.cp-fila__estado');
        if (!el) return;
        el.className = 'cp-fila__estado is-visible ' + clase;
        el.innerHTML = icono;
        if (clase === 'is-ok') {
            setTimeout(function () { el.classList.remove('is-visible'); }, 2000);
        }
    }

    /* Se guarda al salir del campo, no en cada tecla: escribir "25" pasaría
       por "2" y habría guardado un precio intermedio. */
    document.querySelectorAll('input[data-cp-origen]').forEach(function (inp) {
        var original = inp.value;

        inp.addEventListener('focus', function () { original = inp.value; });

        inp.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { inp.blur(); }
            if (e.key === 'Escape') { inp.value = original; inp.blur(); }
        });

        inp.addEventListener('blur', function () {
            var valor = inp.value.trim();
            if (valor === original) return;
            if (valor === '' || isNaN(parseFloat(valor)) || parseFloat(valor) < 0) {
                inp.value = original;
                avisar('El precio debe ser un número mayor o igual a cero.', true);
                return;
            }

            var fila = inp.closest('.cp-fila');
            marcar(fila, 'is-carga', '<i class="fa-solid fa-spinner fa-spin"></i>');

            var fd = new FormData();
            fd.append('origen', inp.getAttribute('data-cp-origen'));
            fd.append('clave', inp.getAttribute('data-cp-clave'));
            fd.append('precio', valor);

            fetch((window.ECO_BASE || '') + 'api/guardar_precio.php', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (res && res.success) {
                        inp.value = parseFloat(res.precio).toFixed(2);
                        original = inp.value;
                        marcar(fila, 'is-ok', '<i class="fa-solid fa-check"></i>');
                        avisar(res.message || 'Precio actualizado.', false);
                    } else {
                        inp.value = original;
                        marcar(fila, 'is-error', '<i class="fa-solid fa-triangle-exclamation"></i>');
                        avisar((res && res.message) || 'No se pudo guardar.', true);
                    }
                })
                .catch(function () {
                    inp.value = original;
                    marcar(fila, 'is-error', '<i class="fa-solid fa-triangle-exclamation"></i>');
                    avisar('Error de red. El precio no se guardó.', true);
                });
        });
    });

    /* Filtro del catálogo de ecografías */
    var buscador = document.getElementById('cp-buscar-eco');
    var contador = document.getElementById('cp-eco-visibles');
    if (buscador) {
        buscador.addEventListener('input', function () {
            var q = buscador.value.trim().toLowerCase();
            var visibles = 0;
            document.querySelectorAll('#cp-lista-eco .cp-fila').forEach(function (fila) {
                var coincide = (fila.getAttribute('data-cp-busca') || '').indexOf(q) !== -1;
                fila.classList.toggle('cp-fila--oculta', !coincide);
                if (coincide) visibles++;
            });
            if (contador) contador.textContent = visibles;
        });
    }
})();
</script>
HTML;

include __DIR__ . '/../layouts/shell.php';
