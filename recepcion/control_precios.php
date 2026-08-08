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
require_once __DIR__ . '/../lib/facturacion/listas_precios.php';
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

$cp_hay_listas = eco_listas_precios_disponibles($conex);
$cp_listas     = $cp_hay_listas ? eco_listas_precios($conex) : [];
$cp_activa     = null;
foreach ($cp_listas as $l) {
    if ($l['es_activa']) { $cp_activa = $l; break; }
}

$page_title     = 'Control de precios';
$page_subtitle   = 'Tarifas de ecografías, servicios y promociones';
$active_section = 'control-precios';

$page_head_extra = '<link rel="stylesheet" href="assets/css/recepcion/control-precios.css?v=auto">';

ob_start();
?>

<?php if (!$cp_hay_listas): ?>
    <section class="card cp-seccion">
        <p class="cp-vacio">
            <i class="fa-solid fa-database"></i>
            Para guardar tarifas alternas falta correr la migración
            <code>database/migrations/2026_listas_precios.sql</code>.
            Mientras tanto los precios se editan uno a uno, como hasta ahora.
        </p>
    </section>
<?php else: ?>
<section class="card cp-seccion cp-tarifas">
    <div class="cp-seccion__head">
        <h3 class="cp-seccion__title"><i class="fa-solid fa-layer-group"></i> Tarifas guardadas</h3>
        <button type="button" class="btn-secondary cp-tarifas__nueva" id="cp-btn-nueva">
            <i class="fa-solid fa-plus"></i> Guardar la tarifa actual
        </button>
    </div>
    <p class="cp-seccion__note">
        Cada tarifa guarda el precio de todos los estudios y servicios. Al activar una,
        los precios de abajo se sustituyen de golpe; <strong>los precios que edites abajo
        se guardan siempre en la tarifa en uso</strong>, así que puedes ir y volver sin perder nada.
        Las citas ya cobradas mantienen el importe con el que se cobraron.
    </p>

    <form class="cp-tarifas__form" id="cp-form-nueva" hidden>
        <div class="cp-tarifas__campos">
            <label>
                <span>Nombre</span>
                <input type="text" id="cp-nueva-nombre" maxlength="80" required
                       placeholder="Ej.: Promoción Yumare" autocomplete="off">
            </label>
            <label>
                <span>Descripción <em>(opcional)</em></span>
                <input type="text" id="cp-nueva-desc" maxlength="255"
                       placeholder="Ej.: Jornada fuera de sede" autocomplete="off">
            </label>
        </div>
        <p class="cp-tarifas__ayuda">
            <i class="fa-solid fa-circle-info"></i>
            Se guardan los precios que hay puestos ahora mismo. Después edítalos abajo
            estando esa tarifa activa.
        </p>
        <div class="cp-tarifas__acciones">
            <button type="button" class="btn-secondary" id="cp-btn-cancelar">Cancelar</button>
            <button type="submit" class="btn-primary"><i class="fa-solid fa-check"></i> Guardar tarifa</button>
        </div>
    </form>

    <?php if (count($cp_listas) < 2): ?>
        <?php /* Con una sola tarifa el botón no dice para qué sirve todavía. */ ?>
        <ol class="cp-tarifas__pasos">
            <li>Pulsa <strong>Guardar la tarifa actual</strong> y ponle nombre (por ejemplo «Promoción Yumare»).</li>
            <li><strong>Actívala.</strong> Todavía tiene los mismos precios: aún no cambia nada.</li>
            <li>Edita abajo los precios de la promoción. Quedan guardados en esa tarifa.</li>
            <li>Listo: desde ahora cambias de una a otra con un clic, sin volver a escribirlos.</li>
        </ol>
    <?php endif; ?>

    <ul class="cp-tarifas__lista">
        <?php foreach ($cp_listas as $l): ?>
            <li class="cp-tarifa<?= $l['es_activa'] ? ' is-activa' : '' ?>">
                <div class="cp-tarifa__head">
                    <span class="cp-tarifa__nombre"><?= htmlspecialchars($l['nombre']) ?></span>
                    <?php if ($l['es_activa']): ?>
                        <span class="cp-tarifa__badge"><i class="fa-solid fa-circle-check"></i> En uso</span>
                    <?php endif; ?>
                </div>
                <?php if ($l['descripcion'] !== ''): ?>
                    <p class="cp-tarifa__desc"><?= htmlspecialchars($l['descripcion']) ?></p>
                <?php endif; ?>
                <p class="cp-tarifa__meta">
                    <?= (int)$l['estudios'] ?> estudios · <?= (int)$l['servicios'] ?> servicios
                    <?php if ($l['aplicada_en']): ?>
                        · usada el <?= htmlspecialchars(date('d/m/Y', strtotime($l['aplicada_en']))) ?>
                    <?php endif; ?>
                </p>
                <div class="cp-tarifa__acciones">
                    <?php if ($l['es_activa']): ?>
                        <span class="cp-tarifa__nota">Es la tarifa que se está cobrando.</span>
                    <?php else: ?>
                        <button type="button" class="btn-primary cp-tarifa__aplicar"
                                data-cp-lista="<?= (int)$l['id'] ?>"
                                data-cp-nombre="<?= htmlspecialchars($l['nombre']) ?>">
                            <i class="fa-solid fa-arrows-rotate"></i> Activar
                        </button>
                        <button type="button" class="btn-secondary cp-tarifa__borrar"
                                data-cp-lista="<?= (int)$l['id'] ?>"
                                data-cp-nombre="<?= htmlspecialchars($l['nombre']) ?>"
                                title="Eliminar tarifa">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    <?php endif; ?>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>

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

<?php /* Confirmación de activar/eliminar tarifa. Un solo modal para las dos:
         cambian el icono, el texto y el botón, no la estructura. */ ?>
<div id="cp-modal-confirmar" class="eco-modal" aria-hidden="true" role="dialog" aria-labelledby="cp-confirm-titulo">
    <div class="eco-modal__dialog eco-modal__dialog--sm">
        <div class="eco-modal__main cp-confirm">
            <button type="button" class="eco-modal__close" data-eco-modal-close aria-label="Cerrar">
                <i class="fa-solid fa-xmark"></i>
            </button>
            <div class="cp-confirm__icono" id="cp-confirm-icono" aria-hidden="true">
                <i class="fa-solid fa-arrows-rotate"></i>
            </div>
            <h4 class="eco-modal__title" id="cp-confirm-titulo">Cambiar de tarifa</h4>
            <div class="cp-confirm__cuerpo" id="cp-confirm-cuerpo"></div>
            <div class="cp-confirm__pie">
                <button type="button" class="btn-secondary" data-eco-modal-close>Cancelar</button>
                <button type="button" class="btn-primary" id="cp-confirm-ok">Continuar</button>
            </div>
        </div>
    </div>
</div>

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

    /* ── Tarifas guardadas ────────────────────────────────────────────
       Las tres acciones recargan al terminar: cambian los precios de toda la
       página, y repintarlos a mano dejaría la pantalla diciendo una cosa y la
       base de datos otra. */
    function pedirTarifa(campos, alTerminar) {
        var fd = new FormData();
        Object.keys(campos).forEach(function (k) { fd.append(k, campos[k]); });
        return fetch((window.ECO_BASE || '') + 'api/listas_precios.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res && res.success) {
                    avisar(res.message || 'Listo.', false);
                    setTimeout(function () { window.location.reload(); }, 700);
                } else {
                    avisar((res && res.message) || 'No se pudo completar la acción.', true);
                    if (alTerminar) alTerminar();
                }
            })
            .catch(function () {
                avisar('Error de red. No se cambió nada.', true);
                if (alTerminar) alTerminar();
            });
    }

    /* Confirmación en modal, no window.confirm(): el aviso del navegador sale
       sin estilo, no deja resaltar el nombre de la tarifa y bloquea la página.
       Los párrafos se montan con textContent, así un nombre de tarifa con < o &
       no puede inyectar marcado. */
    var confirmAlAceptar = null;
    var confirmEls = {
        modal:  document.getElementById('cp-modal-confirmar'),
        icono:  document.getElementById('cp-confirm-icono'),
        titulo: document.getElementById('cp-confirm-titulo'),
        cuerpo: document.getElementById('cp-confirm-cuerpo'),
        ok:     document.getElementById('cp-confirm-ok')
    };

    /**
     * @param {{icono:string, titulo:string, parrafos:Array, textoOk:string,
     *          iconoOk:string, peligro:boolean}} cfg
     *   Cada párrafo es un array de trozos: 'texto' o ['texto', 'fuerte'].
     */
    function confirmar(cfg, alAceptar) {
        if (!confirmEls.modal || !window.EcoModal) {   // sin modal, el flujo sigue
            if (window.confirm(cfg.titulo + '. ¿Continuar?')) alAceptar();
            return;
        }
        confirmEls.icono.innerHTML = '<i class="' + cfg.icono + '"></i>';
        confirmEls.icono.classList.toggle('is-peligro', !!cfg.peligro);
        confirmEls.titulo.textContent = cfg.titulo;

        confirmEls.cuerpo.textContent = '';
        cfg.parrafos.forEach(function (trozos) {
            var p = document.createElement('p');
            trozos.forEach(function (t) {
                if (Array.isArray(t)) {
                    var s = document.createElement('strong');
                    s.textContent = t[0];
                    p.appendChild(s);
                } else {
                    p.appendChild(document.createTextNode(t));
                }
            });
            confirmEls.cuerpo.appendChild(p);
        });

        confirmEls.ok.innerHTML = '<i class="' + cfg.iconoOk + '"></i> ';
        confirmEls.ok.appendChild(document.createTextNode(cfg.textoOk));
        confirmEls.ok.classList.toggle('cp-confirm__ok--peligro', !!cfg.peligro);
        confirmEls.ok.disabled = false;

        confirmAlAceptar = alAceptar;
        EcoModal.open('cp-modal-confirmar');
        setTimeout(function () { confirmEls.ok.focus(); }, 60);
    }

    if (confirmEls.ok) {
        confirmEls.ok.addEventListener('click', function () {
            var fn = confirmAlAceptar;
            confirmAlAceptar = null;
            EcoModal.close('cp-modal-confirmar');
            if (typeof fn === 'function') fn();
        });
    }

    var formNueva = document.getElementById('cp-form-nueva');
    var btnNueva  = document.getElementById('cp-btn-nueva');
    if (btnNueva && formNueva) {
        btnNueva.addEventListener('click', function () {
            formNueva.hidden = !formNueva.hidden;
            if (!formNueva.hidden) document.getElementById('cp-nueva-nombre').focus();
        });
        var btnCancelar = document.getElementById('cp-btn-cancelar');
        if (btnCancelar) {
            btnCancelar.addEventListener('click', function () { formNueva.hidden = true; });
        }
        formNueva.addEventListener('submit', function (e) {
            e.preventDefault();
            var nombre = document.getElementById('cp-nueva-nombre').value.trim();
            if (nombre === '') { avisar('Ponle un nombre a la tarifa.', true); return; }
            var btn = formNueva.querySelector('button[type="submit"]');
            if (btn) btn.disabled = true;
            pedirTarifa({
                accion: 'crear',
                nombre: nombre,
                descripcion: document.getElementById('cp-nueva-desc').value.trim()
            }, function () { if (btn) btn.disabled = false; });
        });
    }

    document.querySelectorAll('.cp-tarifa__aplicar').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var nombre = btn.getAttribute('data-cp-nombre') || 'esta tarifa';
            confirmar({
                icono: 'fa-solid fa-arrows-rotate',
                titulo: 'Cambiar de tarifa',
                parrafos: [
                    ['Se van a cambiar ', ['todos los precios'], ' a los de ', ['«' + nombre + '»'], '.'],
                    ['Los precios que están puestos ahora quedan guardados en la tarifa en uso, '
                     + 'así que puedes volver cuando quieras.'],
                    ['Las citas ya cobradas mantienen el importe con el que se cobraron.']
                ],
                textoOk: 'Sí, cambiar la tarifa',
                iconoOk: 'fa-solid fa-arrows-rotate'
            }, function () {
                btn.disabled = true;
                btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Aplicando…';
                pedirTarifa({ accion: 'aplicar', lista_id: btn.getAttribute('data-cp-lista') }, function () {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-arrows-rotate"></i> Activar';
                });
            });
        });
    });

    document.querySelectorAll('.cp-tarifa__borrar').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var nombre = btn.getAttribute('data-cp-nombre') || 'esta tarifa';
            confirmar({
                icono: 'fa-solid fa-trash',
                titulo: 'Eliminar tarifa',
                peligro: true,
                parrafos: [
                    ['Se borran los precios guardados en ', ['«' + nombre + '»'], '.'],
                    ['Los precios que se están cobrando ahora no cambian.']
                ],
                textoOk: 'Eliminar',
                iconoOk: 'fa-solid fa-trash'
            }, function () {
                btn.disabled = true;
                pedirTarifa({ accion: 'eliminar', lista_id: btn.getAttribute('data-cp-lista') }, function () {
                    btn.disabled = false;
                });
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
