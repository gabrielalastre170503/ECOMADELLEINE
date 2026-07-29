<?php
/**
 * Ficha del paciente (recepción): datos completos, citas, informes de estudio
 * y notas de sesión.
 *
 * La historia la arma lib/pacientes/historia.php, compartida con
 * api/get_historia_clinica.php: las dos vistas muestran los mismos hechos.
 */
session_start();
include __DIR__ . '/../core/conexion.php';
require_once __DIR__ . '/../lib/pacientes/historia.php';
require_once __DIR__ . '/../lib/informes/informes.php';
require_once __DIR__ . '/../lib/seguridad/seguridad.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . eco_url('login'));
    exit;
}
if (($_SESSION['rol'] ?? '') !== 'recepcionista') {
    header('Location: ' . eco_url('dashboard'));
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: ' . eco_url('gestion-pacientes'));
    exit;
}

$paciente_id = (int)$_GET['id'];
$paciente = eco_paciente_ficha($conex, $paciente_id);
if (!$paciente || ($paciente['estado'] ?? '') !== 'aprobado') {
    header('Location: ' . eco_url('gestion-pacientes'));
    exit;
}

// Misma bitácora que el endpoint: esta página muestra datos clínicos.
eco_auditar($conex, 'acceso_historia_clinica', [
    'entidad'    => 'paciente',
    'entidad_id' => $paciente_id,
    'detalle'    => ['paciente' => $paciente['nombre_completo'], 'via' => 'ficha_recepcion'],
]);

$citas    = eco_historia_citas($conex, $paciente_id);
$informes = eco_historia_informes($conex, $paciente_id);
$notas    = eco_historia_notas($conex, $paciente_id, 0); // texto completo

// Cobros: lo facturado y lo que queda por cobrar.
$facturado = 0.0;
$pendiente = 0.0;
foreach ($citas as $c) {
    if ($c['costo'] !== null) {
        $facturado += (float)$c['costo'];
    }
    if ($c['saldo'] !== null && $c['estado'] !== 'cancelada') {
        $pendiente += (float)$c['saldo'];
    }
}

$fmt = static function (?string $v, string $formato = 'd/m/Y'): string {
    if (!$v || $v === '0000-00-00' || $v === '0000-00-00 00:00:00') {
        return '—';
    }
    $ts = strtotime($v);
    return $ts ? date($formato, $ts) : '—';
};

$iniciales = '';
foreach (explode(' ', trim((string)$paciente['nombre_completo'])) as $p) {
    if ($p !== '' && strlen($iniciales) < 2) {
        $iniciales .= strtoupper($p[0]);
    }
}

$page_title    = htmlspecialchars($paciente['nombre_completo']);
$page_subtitle = 'Ficha del paciente · Cédula ' . htmlspecialchars($paciente['cedula'] ?: '—');
$active_section = 'gestion-pacientes';
$body_class     = 'fpx';

$css_ficha = 'assets/css/recepcion/ficha-paciente.css';
$page_head_extra = '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">'
    . '<link rel="stylesheet" href="' . $css_ficha . '?v=' . (@filemtime(__DIR__ . '/../' . $css_ficha) ?: '1') . '">';

$page_header_actions = '
    <a href="' . eco_url('gestion-pacientes') . '" class="btn-secondary"><i class="fa-solid fa-arrow-left"></i> Volver al listado</a>';

ob_start();
?>

<section class="fpx-hero card">
    <div class="fpx-hero__ident">
        <span class="fpx-hero__avatar" aria-hidden="true"><?= htmlspecialchars($iniciales ?: '?') ?></span>
        <div>
            <h2><?= htmlspecialchars($paciente['nombre_completo']) ?></h2>
            <p>
                <?= htmlspecialchars($paciente['cedula'] ?: 'Sin cédula') ?>
                <?php if ($paciente['edad'] !== null): ?>
                    · <?= (int)$paciente['edad'] ?> años
                <?php endif; ?>
            </p>
        </div>
    </div>
    <div class="fpx-hero__acciones">
        <button type="button" class="btn-primary"
            onclick='rxAbrirProgramarCita(<?= (int)$paciente_id ?>, <?= json_encode($paciente['nombre_completo'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
            <i class="fa-solid fa-calendar-plus"></i> Programar cita
        </button>
        <button type="button" class="btn-secondary"
            onclick='rxAbrirInformesPaciente(<?= (int)$paciente_id ?>, <?= json_encode($paciente['nombre_completo'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
            <i class="fa-solid fa-file-waveform"></i> Ver informes
        </button>
    </div>
</section>

<div class="fpx-kpis">
    <div class="stat-card">
        <div class="stat-card-icon" style="background:rgba(2,132,199,.12);color:#0284c7;"><i class="fa-solid fa-calendar-check"></i></div>
        <p class="stat-card-label">Citas</p>
        <p class="stat-card-value" style="color:#0284c7;"><?= count($citas) ?></p>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon" style="background:rgba(109,40,217,.12);color:#6d28d9;"><i class="fa-solid fa-file-waveform"></i></div>
        <p class="stat-card-label">Informes</p>
        <p class="stat-card-value" style="color:#6d28d9;"><?= count($informes) ?></p>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon" style="background:rgba(180,83,9,.12);color:#b45309;"><i class="fa-solid fa-note-sticky"></i></div>
        <p class="stat-card-label">Notas de sesión</p>
        <p class="stat-card-value" style="color:#b45309;"><?= count($notas) ?></p>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon" style="background:rgba(21,128,61,.12);color:#15803d;"><i class="fa-solid fa-money-bill-wave"></i></div>
        <p class="stat-card-label">Facturado</p>
        <p class="stat-card-value" style="color:#15803d;"><?= htmlspecialchars(eco_money($facturado)) ?></p>
        <?php if ($pendiente > 0.005): ?>
            <p class="stat-card-sub fpx-pendiente"><?= htmlspecialchars(eco_money($pendiente)) ?> por cobrar</p>
        <?php else: ?>
            <p class="stat-card-sub">Sin saldo pendiente</p>
        <?php endif; ?>
    </div>
</div>

<div class="card fpx-datos">
    <h3 class="fpx-titulo"><i class="fa-solid fa-address-card"></i> Datos del paciente</h3>
    <dl class="fpx-dl">
        <div><dt>Cédula</dt><dd><?= htmlspecialchars($paciente['cedula'] ?: '—') ?></dd></div>
        <div><dt>Fecha de nacimiento</dt><dd><?= htmlspecialchars($fmt($paciente['fecha_nacimiento'])) ?></dd></div>
        <div><dt>Edad</dt><dd><?= $paciente['edad'] !== null ? (int)$paciente['edad'] . ' años' : '—' ?></dd></div>
        <div><dt>Teléfono</dt><dd><?= htmlspecialchars($paciente['telefono'] ?: '—') ?></dd></div>
        <div class="fpx-dl__ancho"><dt>Correo</dt>
            <dd>
                <?= htmlspecialchars($paciente['correo'] ?: '—') ?>
                <?php if ((int)$paciente['email_verificado'] === 1): ?>
                    <span class="fpx-chip fpx-chip--ok"><i class="fa-solid fa-circle-check"></i> Verificado</span>
                <?php else: ?>
                    <span class="fpx-chip fpx-chip--warn"><i class="fa-solid fa-circle-exclamation"></i> Sin verificar</span>
                <?php endif; ?>
            </dd>
        </div>
        <div class="fpx-dl__ancho"><dt>Dirección</dt><dd><?= htmlspecialchars($paciente['direccion'] ?: '—') ?></dd></div>
        <div><dt>Registro en sistema</dt><dd><?= htmlspecialchars($fmt($paciente['fecha_registro'])) ?></dd></div>
        <div><dt>Último acceso</dt><dd><?= htmlspecialchars($fmt($paciente['ultimo_acceso'], 'd/m/Y H:i')) ?></dd></div>
        <div><dt>Registrado por</dt><dd><?= htmlspecialchars($paciente['creado_por'] ?: 'Registro propio') ?></dd></div>
        <div><dt>Verificación en dos pasos</dt>
            <dd><?= (int)$paciente['two_factor_enabled'] === 1 ? 'Activada' : 'Desactivada' ?></dd>
        </div>
    </dl>
</div>

<div class="card fpx-bloque">
    <h3 class="fpx-titulo">
        <i class="fa-solid fa-calendar-days"></i> Citas
        <span class="fpx-conteo"><?= count($citas) ?></span>
    </h3>
    <?php if (!$citas): ?>
        <p class="fpx-vacio"><i class="fa-regular fa-calendar"></i> Este paciente todavía no tiene citas registradas.</p>
    <?php else: ?>
        <div class="data-table fpx-tabla">
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Estudio / servicio</th>
                        <th>Ecografista</th>
                        <th>Estado</th>
                        <th class="fpx-num">Monto</th>
                        <th>Pago</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($citas as $c): ?>
                    <tr>
                        <td class="fpx-nowrap">
                            <strong><?= htmlspecialchars($fmt($c['fecha'])) ?></strong>
                            <?php if ($c['hora'] !== ''): ?>
                                <span class="fpx-sub"><?= htmlspecialchars($c['hora']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($c['titulo']) ?></strong>
                            <?php if ($c['servicios'] !== '' && $c['servicios'] !== $c['titulo']): ?>
                                <span class="fpx-sub"><?= htmlspecialchars($c['servicios']) ?></span>
                            <?php endif; ?>
                            <?php if ($c['detalle'] !== ''): ?>
                                <span class="fpx-sub fpx-motivo"><?= htmlspecialchars($c['detalle']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($c['profesional'] ?: 'Sin asignar') ?></td>
                        <td><span class="fpx-estado fpx-estado--<?= htmlspecialchars($c['estado']) ?>"><?= htmlspecialchars(eco_cita_estado_label($c['estado'])) ?></span></td>
                        <td class="fpx-num"><?= htmlspecialchars($c['costo_fmt'] ?: '—') ?></td>
                        <td>
                            <?php if ($c['pago_label'] !== ''): ?>
                                <span class="fpx-pago fpx-pago--<?= htmlspecialchars($c['pago_estado']) ?>"><?= htmlspecialchars($c['pago_label']) ?></span>
                                <?php if ($c['metodo_pago'] !== ''): ?>
                                    <span class="fpx-sub"><?= htmlspecialchars($c['metodo_pago']) ?></span>
                                <?php endif; ?>
                                <?php if ($c['saldo'] !== null && $c['saldo'] > 0.005): ?>
                                    <span class="fpx-sub fpx-pendiente">Debe <?= htmlspecialchars($c['saldo_fmt']) ?></span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="fpx-sub">Sin facturar</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="card fpx-bloque">
    <h3 class="fpx-titulo">
        <i class="fa-solid fa-file-waveform"></i> Informes de estudio
        <span class="fpx-conteo"><?= count($informes) ?></span>
    </h3>
    <?php if (!$informes): ?>
        <p class="fpx-vacio"><i class="fa-regular fa-folder-open"></i> No hay informes de estudio para este paciente.</p>
    <?php else: ?>
        <ul class="fpx-lista">
            <?php foreach ($informes as $inf): ?>
                <li class="fpx-item">
                    <span class="fpx-item__icono fpx-item__icono--informe" aria-hidden="true"><i class="fa-solid fa-wave-square"></i></span>
                    <div class="fpx-item__cuerpo">
                        <strong><?= htmlspecialchars($inf['titulo']) ?></strong>
                        <span class="fpx-sub">
                            <?= htmlspecialchars($inf['numero'] ?: 'Sin número') ?>
                            · <?= htmlspecialchars($fmt($inf['fecha'])) ?>
                            · <?= htmlspecialchars($inf['profesional'] ?: 'Sin ecografista') ?>
                            <?php if ($inf['categoria'] !== ''): ?>
                                · <?= htmlspecialchars($inf['categoria']) ?>
                            <?php endif; ?>
                        </span>
                    </div>
                    <span class="fpx-estado fpx-estado--inf-<?= htmlspecialchars($inf['estado']) ?>"><?= htmlspecialchars(eco_informe_estado_label($inf['estado'])) ?></span>
                    <a class="fpx-item__link" href="<?= eco_url('informe/' . (int)$inf['id']) ?>" target="_blank" rel="noopener">
                        <i class="fa-solid fa-arrow-up-right-from-square"></i> Ver
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<div class="card fpx-bloque">
    <h3 class="fpx-titulo">
        <i class="fa-solid fa-note-sticky"></i> Notas de sesión
        <span class="fpx-conteo"><?= count($notas) ?></span>
    </h3>
    <?php if (!$notas): ?>
        <p class="fpx-vacio"><i class="fa-regular fa-note-sticky"></i> Ningún ecografista ha dejado notas de sesión.</p>
    <?php else: ?>
        <ul class="fpx-notas">
            <?php foreach ($notas as $n): ?>
                <li class="fpx-nota">
                    <div class="fpx-nota__cabecera">
                        <strong><?= htmlspecialchars($fmt($n['fecha'])) ?></strong>
                        <span class="fpx-sub"><?= htmlspecialchars($n['profesional'] ?: 'Sin ecografista') ?></span>
                    </div>
                    <p class="fpx-nota__texto"><?= nl2br(htmlspecialchars($n['detalle'])) ?></p>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../layouts/partials/modal_rx_gestion_pacientes.php'; ?>

<?php
$page_content = ob_get_clean();

$page_scripts_extra = <<<'HTML'
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
<script src="assets/js/recepcion/recepcion_rx_pacientes.js"></script>
HTML;

include __DIR__ . '/../layouts/shell.php';
