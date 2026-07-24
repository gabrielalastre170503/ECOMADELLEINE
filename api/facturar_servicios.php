<?php
/**
 * Registra y (opcionalmente) factura servicios SIN ecografía del flujo del
 * ecografista: consulta médica, citología, procesamiento de muestra.
 *
 *  - facturar=1 (default): asienta el cobro reusando/creando la cita del día
 *    vía eco_facturar_cita_reuso() con tipoEcoId=0 (solo servicios).
 *  - facturar=0: modo "solo registro" (los servicios se facturan junto con el
 *    informe del estudio); únicamente guarda la observación como nota clínica.
 *  - Si hay observaciones, se guardan como nota de sesión del paciente con el
 *    prefijo de los servicios atendidos.
 *
 * POST (form o JSON): paciente_id, servicios (csv o array), observaciones?, facturar?
 */
session_start();
require_once __DIR__ . '/../lib/core/api.php';
include __DIR__ . '/../core/conexion.php';
require_once __DIR__ . '/../lib/facturacion/facturacion.php';
require_once __DIR__ . '/../lib/seguridad/seguridad.php';

api_json();
api_require_roles(['ecografista']);
api_require_post();
api_require_csrf();

$paciente_id = (int)api_param('paciente_id', 0);
$obs         = trim((string)api_param('observaciones', ''));
$facturar    = (int)api_param('facturar', 1) === 1;

$rawKeys = api_param('servicios', []);
if (is_string($rawKeys)) {
    $rawKeys = explode(',', $rawKeys);
}
$catalogo = [];
foreach (eco_servicios_adicionales() as $s) {
    $catalogo[$s['key']] = $s;
}
$keys = array_values(array_unique(array_filter(
    array_map('trim', (array)$rawKeys),
    static fn($k) => $k !== '' && isset($catalogo[$k])
)));

if ($paciente_id <= 0) {
    api_fail('Paciente inválido.');
}
if (empty($keys)) {
    api_fail('Selecciona al menos un servicio.');
}

// El paciente debe existir y ser rol paciente.
$chk = $conex->prepare("SELECT id FROM usuarios WHERE id = ? AND rol = 'paciente' LIMIT 1");
$chk->bind_param('i', $paciente_id);
$chk->execute();
if ($chk->get_result()->num_rows === 0) {
    $chk->close();
    api_fail('Paciente no encontrado.', 404);
}
$chk->close();

$ecografista_id = api_uid();
$labels = array_map(static fn($k) => $catalogo[$k]['label'], $keys);

$cita_id = 0;
$total   = 0.0;
if ($facturar) {
    try {
        $fact = eco_facturar_cita_reuso($conex, $paciente_id, $ecografista_id, 0, $keys);
        if ($fact !== null) {
            $cita_id = (int)$fact[0];
            $total   = (float)$fact[1];
        }
    } catch (Throwable $e) {
        error_log('facturar_servicios: ' . $e->getMessage());
        api_fail('No se pudo asentar la facturación. Inténtalo de nuevo.', 500);
    }
}

// Observaciones → nota de sesión (cuaderno clínico del paciente).
$nota_id = 0;
if ($obs !== '') {
    $contenido = '[' . implode(', ', $labels) . '] ' . $obs;
    if ($n = $conex->prepare("INSERT INTO notas_clinicas (paciente_id, ecografista_id, fecha_sesion, contenido) VALUES (?, ?, NOW(), ?)")) {
        $n->bind_param('iis', $paciente_id, $ecografista_id, $contenido);
        if ($n->execute()) {
            $nota_id = (int)$n->insert_id;
        }
        $n->close();
    }
}

if (function_exists('eco_auditar')) {
    eco_auditar($conex, 'servicios_registrados', [
        'entidad'    => 'usuario',
        'entidad_id' => $paciente_id,
        'detalle'    => ['servicios' => $keys, 'facturado' => $facturar, 'total' => $total, 'cita_id' => $cita_id],
    ]);
}

api_ok([
    'facturado' => $facturar && $cita_id > 0,
    'cita_id'   => $cita_id,
    'total'     => $total,
    'nota_id'   => $nota_id,
    'message'   => $facturar && $cita_id > 0
        ? 'Servicios facturados: ' . implode(', ', $labels) . ' (total $' . rtrim(rtrim(number_format($total, 2, '.', ''), '0'), '.') . ').'
        : 'Atención registrada: ' . implode(', ', $labels) . '.',
]);
