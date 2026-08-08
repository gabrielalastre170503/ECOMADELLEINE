<?php
/**
 * Modal: crear paciente (AJAX → guardar_paciente.php).
 * Requiere: conexion cargada ($conex), sesión iniciada, rol ecografista|administrador|recepcionista.
 *
 * IDs expuestos: eco-modal-crear-paciente, eco-modal-exito-paciente
 */
if (!isset($conex) || !($conex instanceof mysqli)) {
    return;
}
$rol_modal = $_SESSION['rol'] ?? '';

/* Recepción registra al paciente y, en el mismo paso, deja asentado el servicio
   que viene a hacerse: estudio, servicios adicionales, ecografista y cobro.
   Eso crea una cita, que es lo que hace aparecer al paciente en "Mis Pacientes"
   del ecografista asignado. */
$mcp_con_servicio = ($rol_modal === 'recepcionista');
$mcp_ecografistas = [];
$mcp_tipos        = [];
$mcp_servicios    = [];
$mcp_metodos      = [];
if ($mcp_con_servicio) {
    require_once __DIR__ . '/../../lib/informes/catalogo.php';
    require_once __DIR__ . '/../../lib/facturacion/facturacion.php';
    if ($q = $conex->query("SELECT id, nombre_completo FROM usuarios WHERE rol = 'ecografista' AND estado = 'aprobado' ORDER BY nombre_completo ASC")) {
        while ($row = $q->fetch_assoc()) {
            $mcp_ecografistas[] = $row;
        }
        $q->free();
    }
    $mcp_tipos     = eco_catalogo_tipos_activos($conex);
    $mcp_servicios = eco_servicios_adicionales();
    $mcp_metodos   = eco_metodos_pago();
}
?>
<div id="eco-modal-crear-paciente" class="eco-modal" aria-hidden="true" role="dialog" aria-labelledby="eco-modal-crear-paciente-title">
    <div class="eco-modal__dialog eco-modal__dialog--wide">
        <div class="eco-modal__split">
            <div class="eco-modal__aside">
                <div class="eco-modal__aside-icon"><i class="fa-solid fa-user-plus"></i></div>
                <h3 id="eco-modal-crear-paciente-title">Nuevo paciente</h3>
                <p>Registra los datos básicos. Se generará una contraseña temporal para el primer acceso.</p>
                <?php if ($mcp_con_servicio): ?>
                <ol class="pcx-pasos">
                    <li>Datos del paciente</li>
                    <li>Servicio a realizar</li>
                    <li>Cobro</li>
                </ol>
                <?php endif; ?>
                <p class="eco-modal__hint"><i class="fa-solid fa-key" style="margin-right:4px;"></i> Entrega la contraseña al paciente de forma segura.</p>
            </div>
            <div class="eco-modal__main pcx-main">
                <button type="button" class="eco-modal__close" data-eco-modal-close aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
                <h4 class="eco-modal__title">Datos del paciente</h4>
                <div id="eco-crear-paciente-error" class="pcx-error" role="alert"></div>
                <form id="form-crear-paciente-eco" action="<?= eco_url('api/guardar_paciente.php') ?>" method="post" novalidate>
                    <?= csrf_field() ?>

                    <fieldset class="mcp-bloque pcx-bloque">
                        <legend class="mcp-bloque__titulo">
                            <?php if ($mcp_con_servicio): ?><span class="pcx-num">1</span><?php else: ?><i class="fa-solid fa-id-card"></i><?php endif; ?>
                            Datos del paciente
                        </legend>
                        <div class="eco-field">
                            <label for="nombre_completo_eco">Nombre completo</label>
                            <input type="text" name="nombre_completo" id="nombre_completo_eco" required maxlength="100" autocomplete="name" placeholder="Nombre y apellido">
                        </div>
                        <div class="eco-field">
                            <label for="fecha_nacimiento_eco">Fecha de nacimiento</label>
                            <input type="text" name="fecha_nacimiento" id="fecha_nacimiento_eco" required placeholder="Seleccionar…" autocomplete="bday">
                        </div>
                        <div class="eco-field">
                            <label for="cedula_numero_eco">Identificación</label>
                            <div class="eco-cedula-row">
                                <select name="cedula_tipo" id="cedula_tipo_eco" aria-label="Tipo de documento">
                                    <option value="V-">V</option>
                                    <option value="E-">E</option>
                                    <option value="P-">P</option>
                                </select>
                                <input type="number" name="cedula_numero" id="cedula_numero_eco" required min="1000000" max="99999999" placeholder="7–8 dígitos" inputmode="numeric">
                            </div>
                        </div>
                        <div class="eco-field">
                            <label for="correo_eco">Correo electrónico</label>
                            <input type="email" name="correo" id="correo_eco" required maxlength="100" autocomplete="email" placeholder="correo@ejemplo.com">
                        </div>
                        <div class="eco-field">
                            <label for="direccion_eco">Dirección física</label>
                            <input type="text" name="direccion" id="direccion_eco" required maxlength="255" autocomplete="street-address" placeholder="Estado, Sector">
                        </div>
                        <div class="eco-field">
                            <label for="telefono_eco">Teléfono</label>
                            <input type="tel" name="telefono" id="telefono_eco" required maxlength="30" autocomplete="tel" placeholder="Ej: 0412-1234567">
                        </div>
                    </fieldset>

                    <?php if ($mcp_con_servicio): ?>
                    <fieldset class="mcp-bloque">
                        <legend class="mcp-bloque__titulo"><span class="pcx-num">2</span> Servicio a realizar</legend>
                        <p class="mcp-bloque__nota">Opcional. Si lo completas se agenda la atención y el paciente pasa a la lista del ecografista asignado.</p>

                        <div class="eco-field">
                            <label for="mcp_ecografista">Ecografista responsable</label>
                            <?php if (empty($mcp_ecografistas)): ?>
                                <p class="mcp-bloque__vacio">No hay ecografistas aprobados.</p>
                            <?php else: ?>
                                <select name="ecografista_id" id="mcp_ecografista">
                                    <option value="">Sin asignar</option>
                                    <?php foreach ($mcp_ecografistas as $eco): ?>
                                        <option value="<?= (int)$eco['id'] ?>"><?= htmlspecialchars($eco['nombre_completo']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>

                        <div class="eco-field">
                            <div class="pcx-label-fila">
                                <span class="mcp-label">Tipos de ecografía <span class="mcp-opcional">(puedes elegir varios)</span></span>
                                <span class="pcx-contador" id="mcp-visibles"><?= count($mcp_tipos) ?> de <?= count($mcp_tipos) ?></span>
                            </div>
                            <div class="pcx-buscar">
                                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                                <input type="search" id="mcp-buscar" placeholder="Filtrar por nombre o categoría…" autocomplete="off" aria-label="Filtrar tipos de ecografía">
                            </div>
                            <div class="mcp-estudios" id="mcp-estudios" role="group" aria-label="Tipos de ecografía a realizar">
                                <?php
                                $mcp_cat_previa = null;
                                foreach ($mcp_tipos as $t):
                                    $cat = (string)($t['categoria'] ?? '');
                                    if ($cat !== $mcp_cat_previa):
                                        $mcp_cat_previa = $cat;
                                        ?>
                                        <p class="mcp-estudios__cat" data-mcp-cat><?= htmlspecialchars($cat !== '' ? $cat : 'Otros') ?></p>
                                    <?php endif; ?>
                                    <label class="mcp-opcion" data-mcp-busca="<?= htmlspecialchars(mb_strtolower($t['nombre'] . ' ' . $cat, 'UTF-8')) ?>">
                                        <input type="checkbox" name="tipos_ecografia[]" value="<?= (int)$t['id'] ?>"
                                               data-precio="<?= htmlspecialchars((string)(float)$t['precio']) ?>" data-mcp-estudio>
                                        <span class="mcp-opcion__nombre"><?= htmlspecialchars($t['nombre']) ?></span>
                                        <span class="mcp-opcion__precio"><?= htmlspecialchars(eco_money((float)$t['precio'])) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <p class="pcx-sin-resultados" id="mcp-sin-resultados" hidden>Ningún estudio coincide con el filtro.</p>
                            <p class="mcp-resumen" id="mcp-estudios-resumen">Ninguna seleccionada.</p>
                        </div>

                        <div class="eco-field">
                            <span class="mcp-label">Otros servicios</span>
                            <div class="mcp-servicios">
                                <?php foreach ($mcp_servicios as $s): ?>
                                    <label class="mcp-opcion">
                                        <input type="checkbox" name="servicios[]" value="<?= htmlspecialchars($s['key']) ?>" data-mcp-servicio>
                                        <span class="mcp-opcion__nombre"><?= htmlspecialchars($s['label']) ?></span>
                                        <span class="mcp-opcion__precio"><?= htmlspecialchars(eco_money((float)$s['price'])) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="eco-field">
                            <label for="mcp_otro_servicio">Otro servicio <span class="mcp-opcional">(opcional)</span></label>
                            <input type="text" name="otro_servicio" id="mcp_otro_servicio" maxlength="120" placeholder="Describe el servicio">
                        </div>

                        <div class="eco-field">
                            <label for="mcp_fecha_cita">Fecha y hora de atención <span class="mcp-opcional">(opcional)</span></label>
                            <input type="text" name="fecha_cita" id="mcp_fecha_cita" autocomplete="off" placeholder="Ahora mismo si lo dejas vacío">
                        </div>
                    </fieldset>

                    <fieldset class="mcp-bloque">
                        <legend class="mcp-bloque__titulo"><span class="pcx-num">3</span> Cobro</legend>

                        <div class="eco-field">
                            <label for="mcp_monto">Monto a cobrar</label>
                            <div class="mcp-monto-row">
                                <span class="mcp-monto-row__simbolo">$</span>
                                <input type="number" name="monto_total" id="mcp_monto" min="0" step="0.01" placeholder="0.00" inputmode="decimal">
                            </div>
                            <p class="mcp-sugerido" id="mcp-monto-sugerido">Se calcula solo al elegir estudio y servicios. Puedes cambiarlo.</p>
                        </div>

                        <div class="eco-field">
                            <label for="mcp_metodo_pago">Método de pago</label>
                            <select name="metodo_pago" id="mcp_metodo_pago">
                                <option value="">Sin cobrar todavía</option>
                                <?php foreach ($mcp_metodos as $m): ?>
                                    <option value="<?= htmlspecialchars($m) ?>"><?= htmlspecialchars($m) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="mcp-bloque__nota">Al elegir un método el cobro queda registrado como pagado.</p>
                        </div>
                    </fieldset>
                    <?php endif; ?>

                    <div class="eco-modal__footer pcx-footer">
                        <button type="button" class="btn-secondary" data-eco-modal-close>Cancelar</button>
                        <button type="submit" class="btn-primary" id="btn-submit-crear-paciente-eco"><i class="fa-solid fa-check"></i> Crear paciente</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div id="eco-modal-exito-paciente" class="eco-modal" aria-hidden="true" role="dialog" aria-labelledby="eco-modal-exito-titulo">
    <div class="eco-modal__dialog eco-modal__dialog--compact">
        <div class="eco-modal__main mcx-main">
            <button type="button" class="eco-modal__close" data-eco-modal-close aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>

            <div class="mcx-tick"><i class="fa-solid fa-check"></i></div>
            <h3 id="eco-modal-exito-titulo" class="mcx-titulo">Paciente creado</h3>
            <p class="mcx-sub">Cuenta de <strong id="eco-exito-paciente-nombre"></strong></p>

            <div class="mcx-clave">
                <p class="mcx-clave__rotulo"><i class="fa-solid fa-key"></i> Contraseña temporal</p>
                <div class="temp-pass-box" id="eco-exito-paciente-pass">—</div>
                <button type="button" class="mcx-copiar" id="eco-exito-copiar" data-copiado="Copiada">
                    <i class="fa-regular fa-copy"></i> Copiar
                </button>
            </div>

            <p class="mcx-envio" id="eco-exito-envio">
                <i class="fa-regular fa-paper-plane"></i>
                <span id="eco-exito-envio-txt">Se le envía por correo al paciente.</span>
            </p>
            <div class="mcp-exito-cita" id="eco-exito-cita" hidden>
                <div class="mcp-exito-cita__fila">
                    <span>Ecografista</span><strong id="eco-exito-cita-eco">—</strong>
                </div>
                <div class="mcp-exito-cita__fila">
                    <span>Monto</span><strong id="eco-exito-cita-total">—</strong>
                </div>
                <div class="mcp-exito-cita__fila">
                    <span>Pago</span><strong id="eco-exito-cita-pago">—</strong>
                </div>
                <p class="mcp-exito-cita__detalle" id="eco-exito-cita-detalle"></p>
            </div>
            <div class="eco-modal__footer" style="border-top:none;justify-content:center;margin-top:8px;">
                <button type="button" class="btn-primary" id="btn-eco-exito-cerrar"><i class="fa-solid fa-check"></i> Entendido</button>
            </div>
        </div>
    </div>
</div>
<script>
/* Copiar la clave y decir qué ha pasado con el correo. Lo rellena quien crea
   al paciente (recepción o ecografista) llamando a ecoExitoPaciente(). */
(function () {
    var btn = document.getElementById('eco-exito-copiar');
    if (btn) {
        btn.addEventListener('click', function () {
            var caja = document.getElementById('eco-exito-paciente-pass');
            var clave = caja ? caja.textContent.trim() : '';
            if (!clave || clave === '—' || !navigator.clipboard) { return; }
            navigator.clipboard.writeText(clave).then(function () {
                var original = btn.innerHTML;
                btn.innerHTML = '<i class="fa-solid fa-check"></i> ' + (btn.getAttribute('data-copiado') || 'Copiada');
                btn.classList.add('is-ok');
                setTimeout(function () { btn.innerHTML = original; btn.classList.remove('is-ok'); }, 1800);
            });
        });
    }

    /* ── Validación campo a campo ─────────────────────────────────────
       Antes solo se sabía qué faltaba tras enviar, con un aviso genérico en lo
       alto de un formulario largo. Las reglas viven aquí, junto al marcado, y
       el ayudante compartido (shell.js) se encarga de marcar y llevar el foco.

       Las mismas comprobaciones las repite el servidor: esto solo evita el
       viaje de ida y vuelta, no sustituye la validación de verdad. */
    var soloDigitos = function (s) { return (s || '').replace(/\D/g, ''); };

    window.ecoReglasPaciente = {
        nombre: function (v) {
            if (v === '') return 'Escribe el nombre del paciente.';
            if (v.length < 3) return 'El nombre parece demasiado corto.';
            return '';
        },
        fecha: function (v) {
            if (v === '') return 'Elige la fecha de nacimiento.';
            return '';
        },
        cedula: function (v) {
            if (v === '') return 'Escribe el número de documento.';
            if (!/^\d{7,8}$/.test(v)) return 'Deben ser 7 u 8 dígitos, sin puntos ni guiones.';
            return '';
        },
        correo: function (v) {
            if (v === '') return 'Escribe el correo del paciente.';
            // Deliberadamente laxa: la comprobación seria es que llegue el correo.
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v)) return 'Ese correo no parece válido.';
            return '';
        },
        direccion: function (v) {
            if (v === '') return 'Escribe la dirección.';
            return '';
        },
        telefono: function (v) {
            if (v === '') return 'Escribe el teléfono.';
            if (soloDigitos(v).length < 7) return 'El teléfono parece incompleto.';
            return '';
        }
    };

    /* En DOMContentLoaded y no aquí mismo: este parcial se imprime dentro del
       contenido de la página, y shell.js —de donde salen ecoValidador y
       ecoFiltroCatalogo— se carga al final del <body>. Montándolo al vuelo, los
       dos ayudantes todavía no existían y el formulario se enviaba sin validar. */
    document.addEventListener('DOMContentLoaded', function () {
        var R = window.ecoReglasPaciente;
        var validador = window.ecoValidador ? window.ecoValidador('form-crear-paciente-eco', {
            nombre_completo_eco:  R.nombre,
            fecha_nacimiento_eco: R.fecha,
            cedula_numero_eco:    R.cedula,
            correo_eco:           R.correo,
            direccion_eco:        R.direccion,
            telefono_eco:         R.telefono
        }) : null;
        window.ecoValidadorCrearPaciente = validador;

        var refiltrar = window.ecoFiltroCatalogo ? window.ecoFiltroCatalogo({
            caja: 'mcp-estudios', buscador: 'mcp-buscar', contador: 'mcp-visibles',
            aviso: 'mcp-sin-resultados', cat: 'data-mcp-cat', item: 'data-mcp-estudio',
            busca: 'data-mcp-busca'
        }) : function () {};

        /* Al abrir, el formulario ya se reseteó desde la página: hay que borrar
           los avisos y repintar la lista, que seguiría filtrada de la vez
           anterior. */
        var modalCrear = document.getElementById('eco-modal-crear-paciente');
        if (modalCrear) {
            modalCrear.addEventListener('eco-modal:open', function () {
                if (validador) validador.limpiar();
                refiltrar();
            });
        }
    });

    /** Deja el aviso acorde a si el correo salió o no. */
    window.ecoExitoPaciente = function (data) {
        var caja = document.getElementById('eco-exito-envio');
        var txt  = document.getElementById('eco-exito-envio-txt');
        if (!caja || !txt) { return; }
        var correo = data && data.correo ? data.correo : '';
        if (data && data.correo_enviado) {
            caja.classList.remove('is-fallo');
            txt.innerHTML = 'Enviada por correo a <strong>' + correo.replace(/[<>&]/g, '') + '</strong>. '
                + 'El paciente debe cambiarla al entrar por primera vez.';
        } else {
            caja.classList.add('is-fallo');
            txt.innerHTML = 'No se pudo enviar el correo. La cuenta está creada: '
                + '<strong>anota esta contraseña y entrégasela al paciente</strong>.';
        }
    };
})();
</script>
