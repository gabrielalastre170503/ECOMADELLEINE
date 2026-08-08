<?php
/**
 * Modales shell — recepcionista · programar cita (con ecografista), informes del paciente, alta extendida.
 * Requiere $conex (mysqli).
 */
if (!isset($_SESSION['usuario_id']) || ($_SESSION['rol'] ?? '') !== 'recepcionista') {
    return;
}
if (!isset($conex) || !($conex instanceof mysqli)) {
    return;
}

$rx_modal_ecografistas = [];
if ($rq = $conex->query("SELECT id, nombre_completo FROM usuarios WHERE rol = 'ecografista' AND estado = 'aprobado' ORDER BY nombre_completo ASC")) {
    while ($row = $rq->fetch_assoc()) {
        $rx_modal_ecografistas[] = $row;
    }
    $rq->free();
}

require_once __DIR__ . '/../../lib/informes/catalogo.php';
require_once __DIR__ . '/../../lib/facturacion/facturacion.php';
$rx_modal_tipos     = eco_catalogo_tipos_activos($conex);
$rx_modal_servicios = eco_servicios_adicionales();
$rx_modal_metodos   = eco_metodos_pago();
?>

<div id="eco-modal-rx-programar-cita" class="eco-modal" aria-hidden="true" role="dialog" aria-labelledby="rx-prog-aside-title">
    <div class="eco-modal__dialog eco-modal__dialog--wide">
        <div class="eco-modal__split">
            <div class="eco-modal__aside">
                <div class="eco-modal__aside-icon"><i class="fa-solid fa-calendar-plus"></i></div>
                <h3 id="rx-prog-aside-title">Programar cita</h3>
                <p>Agendar estudio ecográfico para:</p>
                <strong id="rx-prog-paciente-nombre">—</strong>
                <ol class="pcx-pasos">
                    <li>Ecografista</li>
                    <li>Estudios y servicios</li>
                    <li>Fecha y antecedentes</li>
                    <li>Cobro</li>
                </ol>
                <p class="eco-modal__hint"><i class="fa-solid fa-circle-info" style="margin-right:4px;"></i> La cita queda confirmada y el paciente recibe notificación.</p>
            </div>
            <div class="eco-modal__main pcx-main">
                <button type="button" class="eco-modal__close" data-eco-modal-close aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
                <h4 class="eco-modal__title">Detalle de la cita</h4>
                <div id="rx-prog-error" class="pcx-error" role="alert"></div>
                <form id="rx-form-programar-cita" novalidate>
                    <input type="hidden" name="paciente_id" id="rx-prog-paciente-id" value="">

                    <fieldset class="mcp-bloque pcx-bloque">
                        <legend class="mcp-bloque__titulo"><span class="pcx-num">1</span> Ecografista</legend>
                        <div class="eco-field">
                            <label for="rx-prog-ecografista">Ecografista responsable</label>
                            <?php if (empty($rx_modal_ecografistas)): ?>
                                <p class="mcp-bloque__vacio">No hay ecografistas aprobados.</p>
                            <?php else: ?>
                                <select name="ecografista_id" id="rx-prog-ecografista" required>
                                    <option value="">Seleccionar…</option>
                                    <?php foreach ($rx_modal_ecografistas as $eco): ?>
                                        <option value="<?= (int)$eco['id'] ?>"><?= htmlspecialchars($eco['nombre_completo']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>
                    </fieldset>

                    <fieldset class="mcp-bloque">
                        <legend class="mcp-bloque__titulo"><span class="pcx-num">2</span> Estudios y servicios</legend>
                        <div class="eco-field">
                            <div class="pcx-label-fila">
                                <span class="mcp-label">Tipos de ecografía <span class="mcp-opcional">(puedes elegir varios)</span></span>
                                <span class="pcx-contador" id="rx-prog-visibles"><?= count($rx_modal_tipos) ?> de <?= count($rx_modal_tipos) ?></span>
                            </div>
                            <div class="pcx-buscar">
                                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                                <input type="search" id="rx-prog-buscar" placeholder="Filtrar por nombre o categoría…" autocomplete="off" aria-label="Filtrar tipos de ecografía">
                            </div>
                            <div class="mcp-estudios" id="rx-prog-estudios" role="group" aria-label="Tipos de ecografía a realizar">
                                <?php
                                $rx_cat_previa = null;
                                foreach ($rx_modal_tipos as $t):
                                    $cat = (string)($t['categoria'] ?? '');
                                    if ($cat !== $rx_cat_previa):
                                        $rx_cat_previa = $cat;
                                        ?>
                                        <p class="mcp-estudios__cat" data-rxp-cat><?= htmlspecialchars($cat !== '' ? $cat : 'Otros') ?></p>
                                    <?php endif; ?>
                                    <label class="mcp-opcion" data-rxp-busca="<?= htmlspecialchars(mb_strtolower($t['nombre'] . ' ' . $cat, 'UTF-8')) ?>">
                                        <input type="checkbox" name="tipos_ecografia[]" value="<?= (int)$t['id'] ?>" data-rxp-estudio>
                                        <span class="mcp-opcion__nombre"><?= htmlspecialchars($t['nombre']) ?></span>
                                            <span class="mcp-opcion__precio"><?= htmlspecialchars(eco_money((float)$t['precio'])) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <p class="pcx-sin-resultados" id="rx-prog-sin-resultados" hidden>Ningún estudio coincide con el filtro.</p>
                            <p class="mcp-resumen" id="rx-prog-estudios-resumen">Ninguna seleccionada.</p>
                        </div>

                        <div class="eco-field">
                            <span class="mcp-label">Otros servicios</span>
                            <div class="mcp-servicios">
                                <?php foreach ($rx_modal_servicios as $s): ?>
                                    <label class="mcp-opcion">
                                        <input type="checkbox" name="servicios[]" value="<?= htmlspecialchars($s['key']) ?>" data-rxp-servicio>
                                        <span class="mcp-opcion__nombre"><?= htmlspecialchars($s['label']) ?></span>
                                            <span class="mcp-opcion__precio"><?= htmlspecialchars(eco_money((float)$s['price'])) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="eco-field">
                            <label for="rx-prog-otro">Otro servicio <span class="mcp-opcional">(opcional)</span></label>
                            <input type="text" name="otro_servicio" id="rx-prog-otro" maxlength="120" placeholder="Describe el servicio">
                        </div>
                    </fieldset>

                    <fieldset class="mcp-bloque">
                        <legend class="mcp-bloque__titulo"><span class="pcx-num">3</span> Fecha y antecedentes</legend>
                        <div class="eco-field">
                            <label for="rx-prog-fecha">Fecha y hora</label>
                            <input type="text" name="fecha_cita" id="rx-prog-fecha" required autocomplete="off" placeholder="Seleccionar…">
                        </div>
                        <div class="eco-field">
                            <label for="rx-prog-motivo">Antecedentes médicos y detalles <span class="mcp-opcional">(opcional)</span></label>
                            <textarea name="motivo_consulta" id="rx-prog-motivo" rows="3" placeholder="Antecedentes médicos y detalles"></textarea>
                        </div>
                    </fieldset>

                    <fieldset class="mcp-bloque">
                        <legend class="mcp-bloque__titulo"><span class="pcx-num">4</span> Cobro</legend>
                        <div class="eco-field">
                            <label for="rx-prog-monto">Monto a cobrar</label>
                            <div class="mcp-monto-row">
                                <span class="mcp-monto-row__simbolo">$</span>
                                <input type="number" name="monto_total" id="rx-prog-monto" min="0" step="0.01" placeholder="0.00" inputmode="decimal">
                            </div>
                            <p class="mcp-sugerido" id="rx-prog-monto-sugerido">Se calcula solo al elegir estudios y servicios. Puedes cambiarlo.</p>
                        </div>
                        <div class="eco-field">
                            <label for="rx-prog-metodo">Método de pago</label>
                            <select name="metodo_pago" id="rx-prog-metodo">
                                <option value="">Sin cobrar todavía</option>
                                <?php foreach ($rx_modal_metodos as $m): ?>
                                    <option value="<?= htmlspecialchars($m) ?>"><?= htmlspecialchars($m) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="mcp-bloque__nota">Al elegir un método el cobro queda registrado como pagado.</p>
                        </div>
                    </fieldset>

                    <div class="eco-modal__footer pcx-footer">
                        <button type="button" class="btn-secondary" data-eco-modal-close>Cancelar</button>
                        <button type="submit" class="btn-primary" id="rx-prog-submit" <?= (empty($rx_modal_ecografistas) || empty($rx_modal_tipos)) ? 'disabled' : '' ?>><i class="fa-solid fa-check"></i> Guardar cita</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div id="eco-modal-rx-informes-paciente" class="eco-modal" aria-hidden="true" role="dialog" aria-labelledby="rx-inf-title">
    <div class="eco-modal__dialog eco-modal__dialog--wide">
        <div class="eco-modal__main" style="padding-top:22px;">
            <button type="button" class="eco-modal__close" data-eco-modal-close aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
            <h4 class="eco-modal__title" id="rx-inf-title">Estudios e informes</h4>
            <p class="eco-modal__body-text" id="rx-inf-sub" style="margin-top:-8px;"></p>
            <div id="rx-inf-body" style="margin-top:12px;min-height:80px;"></div>
        </div>
    </div>
</div>

<!-- Detalle de un informe, en solo lectura. Reutiliza los ids y clases del
     modal del ecografista para heredar su CSS; recepción no firma ni anula,
     así que esos botones no están. -->
<div id="eco-modal-informe-detalle-eco" class="eco-modal eco-modal-panel-ecografista" aria-hidden="true" role="dialog" aria-labelledby="eco-inf-det-titulo">
    <div class="modal-content-form-eco">
        <div class="modal-form-eco-header">
            <div class="eco-modal-tipo-icon" id="eco-inf-det-icon">
                <i class="fa-solid fa-file-waveform"></i>
            </div>
            <div class="eco-header-tipo-info">
                <h2 id="eco-inf-det-titulo">Informe de estudio</h2>
                <p id="eco-inf-det-paciente">—</p>
            </div>
            <div class="eco-modal-informe-detalle-actions">
                <button type="button" class="eco-btn-cancel" id="rx-inf-det-volver" title="Volver a la lista de informes">
                    <i class="fa-solid fa-arrow-left"></i> Volver
                </button>
                <button type="button" class="eco-btn-cancel" id="rx-inf-det-print" title="Imprimir informe">
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

<div id="eco-modal-rx-crear-paciente-extendido" class="eco-modal" aria-hidden="true" role="dialog" aria-labelledby="rx-ext-aside-title">
    <div class="eco-modal__dialog eco-modal__dialog--wide">
        <div class="eco-modal__split">
            <div class="eco-modal__aside">
                <div class="eco-modal__aside-icon"><i class="fa-solid fa-address-card"></i></div>
                <h3 id="rx-ext-aside-title">Alta extendida</h3>
                <p>Registro con contraseña definida por el paciente. Debe cumplir requisitos de seguridad.</p>
                <ol class="pcx-pasos">
                    <li>Datos del paciente</li>
                    <li>Contraseña</li>
                    <li>Servicio a realizar</li>
                    <li>Cobro</li>
                </ol>
                <p class="eco-modal__hint"><i class="fa-solid fa-lock" style="margin-right:4px;"></i> Mayúscula + símbolo, mín. 8 caracteres.</p>
            </div>
            <div class="eco-modal__main pcx-main">
                <button type="button" class="eco-modal__close" data-eco-modal-close aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
                <h4 class="eco-modal__title">Datos completos</h4>
                <div id="rx-ext-error" class="pcx-error" role="alert"></div>
                <form id="rx-form-crear-paciente-extendido" novalidate>
                    <?= csrf_field() ?>

                    <fieldset class="mcp-bloque pcx-bloque">
                        <legend class="mcp-bloque__titulo"><span class="pcx-num">1</span> Datos del paciente</legend>
                        <div class="eco-field">
                            <label for="rx-ext-nombre">Nombre completo</label>
                            <input type="text" class="eco-input" name="nombre_completo" id="rx-ext-nombre" required maxlength="100" autocomplete="name" placeholder="Nombre y apellido">
                        </div>
                        <div class="eco-field">
                            <label for="rx-ext-fnac">Fecha de nacimiento</label>
                            <input type="text" class="eco-input" name="fecha_nacimiento" id="rx-ext-fnac" required autocomplete="off" placeholder="Seleccionar…">
                        </div>
                        <div class="eco-field">
                            <label for="rx-ext-doc">Documento</label>
                            <div class="eco-cedula-row">
                                <select name="cedula_tipo" id="rx-ext-doc-tipo" aria-label="Tipo">
                                    <option value="V-">V</option>
                                    <option value="E-">E</option>
                                    <option value="P-">P</option>
                                </select>
                                <input type="number" class="eco-input" name="cedula_numero" id="rx-ext-doc-num" required min="1000000" max="99999999" placeholder="7–8 dígitos" inputmode="numeric">
                            </div>
                        </div>
                        <div class="eco-field">
                            <label for="rx-ext-correo">Correo</label>
                            <input type="email" class="eco-input" name="correo" id="rx-ext-correo" required maxlength="100" autocomplete="email" placeholder="correo@ejemplo.com">
                        </div>
                        <div class="eco-field">
                            <label for="rx-ext-direccion">Dirección física</label>
                            <input type="text" class="eco-input" name="direccion" id="rx-ext-direccion" required maxlength="255" autocomplete="street-address" placeholder="Estado, Sector">
                        </div>
                        <div class="eco-field">
                            <label for="rx-ext-telefono">Teléfono</label>
                            <input type="tel" class="eco-input" name="telefono" id="rx-ext-telefono" required maxlength="30" autocomplete="tel" placeholder="Ej: 0412-1234567">
                        </div>
                    </fieldset>

                    <fieldset class="mcp-bloque">
                        <legend class="mcp-bloque__titulo"><span class="pcx-num">2</span> Contraseña</legend>
                        <p class="mcp-bloque__nota">La elige el paciente. Con ella entra directamente, sin clave temporal.</p>
                        <div class="eco-field">
                            <label for="rx-ext-pass">Contraseña</label>
                            <input type="password" class="eco-input" name="contrasena" id="rx-ext-pass" required minlength="8" autocomplete="new-password" placeholder="Mínimo 8 caracteres">
                            <p class="eco-field-hint"><i class="fa-solid fa-circle-info"></i> Mayúscula, símbolo y mínimo 8 caracteres.</p>
                        </div>
                        <div class="eco-field">
                            <label for="rx-ext-pass2">Confirmar contraseña</label>
                            <input type="password" class="eco-input" name="confirmar_contrasena" id="rx-ext-pass2" required minlength="8" autocomplete="new-password" placeholder="Repita la contraseña">
                        </div>
                    </fieldset>

                    <fieldset class="mcp-bloque">
                        <legend class="mcp-bloque__titulo"><span class="pcx-num">3</span> Servicio a realizar</legend>
                        <p class="mcp-bloque__nota">Opcional. Si lo completas se agenda la atención y el paciente pasa a la lista del ecografista asignado.</p>

                        <div class="eco-field">
                            <label for="rx-ext-ecografista">Ecografista responsable</label>
                            <?php if (empty($rx_modal_ecografistas)): ?>
                                <p class="mcp-bloque__vacio">No hay ecografistas aprobados.</p>
                            <?php else: ?>
                                <select name="ecografista_id" id="rx-ext-ecografista">
                                    <option value="">Sin asignar</option>
                                    <?php foreach ($rx_modal_ecografistas as $eco): ?>
                                        <option value="<?= (int)$eco['id'] ?>"><?= htmlspecialchars($eco['nombre_completo']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>

                        <div class="eco-field">
                            <div class="pcx-label-fila">
                                <span class="mcp-label">Tipos de ecografía <span class="mcp-opcional">(puedes elegir varios)</span></span>
                                <span class="pcx-contador" id="rx-ext-visibles"><?= count($rx_modal_tipos) ?> de <?= count($rx_modal_tipos) ?></span>
                            </div>
                            <div class="pcx-buscar">
                                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                                <input type="search" id="rx-ext-buscar" placeholder="Filtrar por nombre o categoría…" autocomplete="off" aria-label="Filtrar tipos de ecografía">
                            </div>
                            <div class="mcp-estudios" id="rx-ext-estudios" role="group" aria-label="Tipos de ecografía a realizar">
                                <?php
                                $rx_ext_cat_previa = null;
                                foreach ($rx_modal_tipos as $t):
                                    $cat = (string)($t['categoria'] ?? '');
                                    if ($cat !== $rx_ext_cat_previa):
                                        $rx_ext_cat_previa = $cat;
                                        ?>
                                        <p class="mcp-estudios__cat" data-rxe-cat><?= htmlspecialchars($cat !== '' ? $cat : 'Otros') ?></p>
                                    <?php endif; ?>
                                    <label class="mcp-opcion" data-rxe-busca="<?= htmlspecialchars(mb_strtolower($t['nombre'] . ' ' . $cat, 'UTF-8')) ?>">
                                        <input type="checkbox" name="tipos_ecografia[]" value="<?= (int)$t['id'] ?>" data-rxe-estudio>
                                        <span class="mcp-opcion__nombre"><?= htmlspecialchars($t['nombre']) ?></span>
                                        <span class="mcp-opcion__precio"><?= htmlspecialchars(eco_money((float)$t['precio'])) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <p class="pcx-sin-resultados" id="rx-ext-sin-resultados" hidden>Ningún estudio coincide con el filtro.</p>
                            <p class="mcp-resumen" id="rx-ext-estudios-resumen">Ninguna seleccionada.</p>
                        </div>

                        <div class="eco-field">
                            <span class="mcp-label">Otros servicios</span>
                            <div class="mcp-servicios">
                                <?php foreach ($rx_modal_servicios as $s): ?>
                                    <label class="mcp-opcion">
                                        <input type="checkbox" name="servicios[]" value="<?= htmlspecialchars($s['key']) ?>" data-rxe-servicio>
                                        <span class="mcp-opcion__nombre"><?= htmlspecialchars($s['label']) ?></span>
                                        <span class="mcp-opcion__precio"><?= htmlspecialchars(eco_money((float)$s['price'])) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="eco-field">
                            <label for="rx-ext-otro">Otro servicio <span class="mcp-opcional">(opcional)</span></label>
                            <input type="text" name="otro_servicio" id="rx-ext-otro" maxlength="120" placeholder="Describe el servicio">
                        </div>

                        <div class="eco-field">
                            <label for="rx-ext-fecha-cita">Fecha y hora de atención <span class="mcp-opcional">(opcional)</span></label>
                            <input type="text" name="fecha_cita" id="rx-ext-fecha-cita" autocomplete="off" placeholder="Ahora mismo si lo dejas vacío">
                        </div>
                    </fieldset>

                    <fieldset class="mcp-bloque">
                        <legend class="mcp-bloque__titulo"><span class="pcx-num">4</span> Cobro</legend>

                        <div class="eco-field">
                            <label for="rx-ext-monto">Monto a cobrar</label>
                            <div class="mcp-monto-row">
                                <span class="mcp-monto-row__simbolo">$</span>
                                <input type="number" name="monto_total" id="rx-ext-monto" min="0" step="0.01" placeholder="0.00" inputmode="decimal">
                            </div>
                            <p class="mcp-sugerido" id="rx-ext-monto-sugerido">Se calcula solo al elegir estudios y servicios. Puedes cambiarlo.</p>
                        </div>

                        <div class="eco-field">
                            <label for="rx-ext-metodo">Método de pago</label>
                            <select name="metodo_pago" id="rx-ext-metodo">
                                <option value="">Sin cobrar todavía</option>
                                <?php foreach ($rx_modal_metodos as $m): ?>
                                    <option value="<?= htmlspecialchars($m) ?>"><?= htmlspecialchars($m) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="mcp-bloque__nota">Al elegir un método el cobro queda registrado como pagado.</p>
                        </div>
                    </fieldset>

                    <div class="eco-modal__footer pcx-footer">
                        <button type="button" class="btn-secondary" data-eco-modal-close>Cancelar</button>
                        <button type="submit" class="btn-primary" id="rx-ext-submit"><i class="fa-solid fa-check"></i> Registrar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
