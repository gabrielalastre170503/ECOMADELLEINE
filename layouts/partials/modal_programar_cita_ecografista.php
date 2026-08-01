<?php
/**
 * Modal shell: programar cita directa (ecografista).
 * IDs: eco-modal-programar-cita-eco — JS: assets/js/panel/ecografista-modals.js
 * Backend: guardar_cita_directa.php (estudios/servicios + fecha_cita + cobro).
 */
if (!isset($_SESSION['usuario_id']) || ($_SESSION['rol'] ?? '') !== 'ecografista') {
    return;
}
if (!isset($conex) || !($conex instanceof mysqli)) {
    return;
}
require_once __DIR__ . '/../../lib/informes/catalogo.php';
require_once __DIR__ . '/../../lib/facturacion/facturacion.php';
$eco_prog_tipos_rows = eco_catalogo_tipos_activos($conex);
$eco_prog_servicios  = eco_servicios_adicionales();
$eco_prog_metodos    = eco_metodos_pago();
?>
<style>
/* ── Modal "Programar cita" ──────────────────────────────────────────
   El formulario era una única columna de campos sueltos: 23 estudios, los
   servicios, la fecha, el cobro y el motivo, todo del mismo peso visual. Se
   agrupa en tres pasos numerados y se añade un filtro para el catálogo, que
   es lo que obliga a desplazarse. Los estilos viven aquí, junto al marcado,
   porque el modal viaja con el parcial a cinco vistas distintas. */
.pcx-pasos { counter-reset:pcx; list-style:none; margin:14px 0 0; padding:0; display:grid; gap:9px; }
.pcx-pasos li { display:flex; align-items:center; gap:9px; font-size:12.5px; color:var(--text-secondary); }
.pcx-pasos li::before { counter-increment:pcx; content:counter(pcx); flex-shrink:0; width:21px; height:21px; border-radius:50%; display:flex; align-items:center; justify-content:center; background:var(--bg-surface); border:1px solid var(--border); font-size:11px; font-weight:700; color:var(--accent-text); }

.pcx-error { display:none; padding:10px 12px; margin-bottom:14px; border-radius:8px; font-size:13px; background:rgba(239,68,68,.1); border:1px solid rgba(239,68,68,.35); color:#b91c1c; }

/* El número del paso, dentro de la leyenda del bloque. */
.eco-modal .pcx-num { display:inline-flex; align-items:center; justify-content:center; width:19px; height:19px; margin-right:2px; border-radius:50%; background:var(--accent-soft); color:var(--accent-text); font-size:10.5px; font-weight:700; }
.eco-modal .pcx-bloque:first-of-type { margin-top:0; }

/* El buscador es <input type="search">, un tipo que la regla común de campos
   del modal no cubre: salía con el ancho por defecto del navegador (unos 20
   caracteres) y el texto de ayuda cortado. Se le da su propia forma. */
.pcx-buscar { position:relative; margin-bottom:10px; }
.pcx-buscar > i { position:absolute; left:14px; top:50%; transform:translateY(-50%); font-size:12.5px; color:var(--text-muted); pointer-events:none; transition:color .18s ease; }
.pcx-buscar:focus-within > i { color:var(--accent-text); }
.eco-modal .eco-field .pcx-buscar input[type="search"] {
    display:block; width:100%; min-height:40px; margin:0;
    padding:9px 14px 9px 38px; box-sizing:border-box;
    border:1px solid var(--border); border-radius:999px;
    font-family:inherit; font-size:13px; line-height:1.4;
    background:var(--bg-surface); color:var(--text-primary);
    transition:border-color .18s ease, box-shadow .18s ease;
}
.eco-modal .eco-field .pcx-buscar input[type="search"]::placeholder { color:var(--text-muted); opacity:1; }
.eco-modal .eco-field .pcx-buscar input[type="search"]:focus {
    outline:none; border-color:var(--accent);
    box-shadow:0 0 0 3px var(--accent-soft);
}

/* Cuántos quedan a la vista, en la misma línea del rótulo. */
.pcx-label-fila { display:flex; align-items:baseline; justify-content:space-between; gap:12px; }
.eco-modal .pcx-label-fila .mcp-label { margin-bottom:6px; }
.pcx-contador { flex-shrink:0; font-size:11px; font-weight:600; color:var(--text-muted); font-variant-numeric:tabular-nums; }

.pcx-oculto { display:none !important; }
.pcx-sin-resultados { margin:8px 0 0; font-size:12px; color:var(--text-muted); text-align:center; }

/* El botón de guardar no debería quedar al fondo de un formulario largo.
   Los márgenes negativos llevan el fondo hasta los bordes del panel: si no,
   el contenido se vería pasar por los laterales al desplazarse. */
.pcx-main { padding-bottom:0 !important; }
.pcx-footer { position:sticky; bottom:0; z-index:2; margin:18px -22px 0; padding:14px 22px; background:var(--bg-surface); border-top:1px solid var(--border); }
</style>
<div id="eco-modal-programar-cita-eco" class="eco-modal" aria-hidden="true" role="dialog" aria-labelledby="eco-prog-aside-title">
    <div class="eco-modal__dialog eco-modal__dialog--wide">
        <div class="eco-modal__split">
            <div class="eco-modal__aside">
                <div class="eco-modal__aside-icon"><i class="fa-solid fa-calendar-check"></i></div>
                <h3 id="eco-prog-aside-title">Nueva cita</h3>
                <p>Agendar estudio ecográfico para:</p>
                <strong id="eco-prog-paciente-nombre">—</strong>
                <ol class="pcx-pasos">
                    <li>Estudios y servicios</li>
                    <li>Fecha y motivo</li>
                    <li>Cobro</li>
                </ol>
                <p class="eco-modal__hint"><i class="fa-solid fa-circle-info" style="margin-right:4px;"></i> La cita queda confirmada y el paciente recibe notificación.</p>
            </div>
            <div class="eco-modal__main pcx-main">
                <button type="button" class="eco-modal__close" onclick="window.cerrarProgramarCitaEco && cerrarProgramarCitaEco()" aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
                <h4 class="eco-modal__title">Detalle de la cita</h4>
                <div id="eco-prog-cita-error" class="pcx-error" role="alert"></div>
                <form id="eco-form-programar-cita-eco" action="<?= eco_url('api/guardar_cita_directa.php') ?>" method="post" novalidate>
                    <input type="hidden" name="paciente_id" id="eco-prog-paciente-id" value="">

                    <fieldset class="mcp-bloque pcx-bloque">
                        <legend class="mcp-bloque__titulo"><span class="pcx-num">1</span> Estudios y servicios</legend>
                        <div class="eco-field">
                            <div class="pcx-label-fila">
                                <span class="mcp-label">Tipos de ecografía <span class="mcp-opcional">(puedes elegir varios)</span></span>
                                <span class="pcx-contador" id="eco-prog-visibles"><?= count($eco_prog_tipos_rows) ?> de <?= count($eco_prog_tipos_rows) ?></span>
                            </div>
                            <div class="pcx-buscar">
                                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                                <input type="search" id="eco-prog-buscar" placeholder="Filtrar por nombre o categoría…" autocomplete="off" aria-label="Filtrar tipos de ecografía">
                            </div>
                            <div class="mcp-estudios" id="eco-prog-estudios" role="group" aria-label="Tipos de ecografía a realizar">
                                <?php
                                $eco_cat_previa = null;
                                foreach ($eco_prog_tipos_rows as $t):
                                    $cat = (string)($t['categoria'] ?? '');
                                    if ($cat !== $eco_cat_previa):
                                        $eco_cat_previa = $cat;
                                        ?>
                                        <p class="mcp-estudios__cat" data-ecop-cat><?= htmlspecialchars($cat !== '' ? $cat : 'Otros') ?></p>
                                    <?php endif; ?>
                                    <label class="mcp-opcion" data-ecop-busca="<?= htmlspecialchars(mb_strtolower($t['nombre'] . ' ' . $cat, 'UTF-8')) ?>">
                                        <input type="checkbox" name="tipos_ecografia[]" value="<?= (int)$t['id'] ?>" data-ecop-estudio>
                                        <span class="mcp-opcion__nombre"><?= htmlspecialchars($t['nombre']) ?></span>
                                            <span class="mcp-opcion__precio"><?= htmlspecialchars(eco_money((float)$t['precio'])) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <p class="pcx-sin-resultados" id="eco-prog-sin-resultados" hidden>Ningún estudio coincide con el filtro.</p>
                            <p class="mcp-resumen" id="eco-prog-estudios-resumen">Ninguna seleccionada.</p>
                        </div>

                        <div class="eco-field">
                            <span class="mcp-label">Otros servicios</span>
                            <div class="mcp-servicios">
                                <?php foreach ($eco_prog_servicios as $s): ?>
                                    <label class="mcp-opcion">
                                        <input type="checkbox" name="servicios[]" value="<?= htmlspecialchars($s['key']) ?>" data-ecop-servicio>
                                        <span class="mcp-opcion__nombre"><?= htmlspecialchars($s['label']) ?></span>
                                            <span class="mcp-opcion__precio"><?= htmlspecialchars(eco_money((float)$s['price'])) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="eco-field">
                            <label for="eco-prog-otro">Otro servicio <span class="mcp-opcional">(opcional)</span></label>
                            <input type="text" name="otro_servicio" id="eco-prog-otro" maxlength="120" placeholder="Describe el servicio">
                        </div>
                    </fieldset>

                    <fieldset class="mcp-bloque">
                        <legend class="mcp-bloque__titulo"><span class="pcx-num">2</span> Fecha y motivo</legend>
                        <div class="eco-field">
                            <label for="eco-prog-fecha-eco">Fecha y hora</label>
                            <input type="text" name="fecha_cita" id="eco-prog-fecha-eco" required placeholder="Seleccionar…" autocomplete="off">
                        </div>
                        <div class="eco-field">
                            <label for="eco-prog-motivo-eco">Motivo / indicación <span class="mcp-opcional">(opcional)</span></label>
                            <textarea name="motivo_consulta" id="eco-prog-motivo-eco" rows="3" placeholder="Ej.: control evolutivo, dolor abdominal…"></textarea>
                        </div>
                    </fieldset>

                    <fieldset class="mcp-bloque">
                        <legend class="mcp-bloque__titulo"><span class="pcx-num">3</span> Cobro</legend>
                        <div class="eco-field">
                            <label for="eco-prog-monto">Monto a cobrar</label>
                            <div class="mcp-monto-row">
                                <span class="mcp-monto-row__simbolo">$</span>
                                <input type="number" name="monto_total" id="eco-prog-monto" min="0" step="0.01" placeholder="0.00" inputmode="decimal">
                            </div>
                            <p class="mcp-sugerido" id="eco-prog-monto-sugerido">Se calcula solo al elegir estudios y servicios. Puedes cambiarlo.</p>
                        </div>
                        <div class="eco-field">
                            <label for="eco-prog-metodo">Método de pago</label>
                            <select name="metodo_pago" id="eco-prog-metodo">
                                <option value="">Sin cobrar todavía</option>
                                <?php foreach ($eco_prog_metodos as $m): ?>
                                    <option value="<?= htmlspecialchars($m) ?>"><?= htmlspecialchars($m) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="mcp-bloque__nota">Al elegir un método el cobro queda registrado como pagado.</p>
                        </div>
                    </fieldset>

                    <div class="eco-modal__footer pcx-footer">
                        <button type="button" class="btn-secondary" onclick="window.cerrarProgramarCitaEco && cerrarProgramarCitaEco()">Cancelar</button>
                        <button type="submit" class="btn-primary" id="eco-prog-submit"><i class="fa-solid fa-check"></i> Guardar cita</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
