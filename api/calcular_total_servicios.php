<?php
/**
 * Total sugerido de un estudio + servicios adicionales, con las promociones del
 * catálogo aplicadas. Lo consume el formulario de alta de paciente de recepción
 * para mostrar el monto antes de guardar.
 *
 * Se calcula en el servidor a propósito: los precios y las promociones viven en
 * lib/facturacion/facturacion.php y no deben duplicarse en JavaScript.
 *
 * Body JSON: { "tipos_ecografia": [int, ...], "servicios": ["consulta", ...] }
 * Respuesta: { success, total, total_texto, detalle, promos }
 */
session_start();
require_once __DIR__ . '/../lib/core/api.php';
include __DIR__ . '/../core/conexion.php';
require_once __DIR__ . '/../lib/facturacion/facturacion.php';

api_json();
api_require_roles(['recepcionista', 'administrador', 'ecografista']);
api_require_post();
api_require_csrf();

$body        = api_body();
$tiposIn     = (isset($body['tipos_ecografia']) && is_array($body['tipos_ecografia'])) ? $body['tipos_ecografia'] : [];
$serviciosIn = (isset($body['servicios']) && is_array($body['servicios'])) ? $body['servicios'] : [];

// Precios desde la BD, nunca del cliente.
$estudios = [];
$ids = array_values(array_unique(array_filter(
    array_map(static fn($v) => (int)$v, $tiposIn),
    static fn(int $v) => $v > 0
)));
if ($ids) {
    $marcas = implode(',', array_fill(0, count($ids), '?'));
    $q = $conex->prepare("SELECT nombre, precio FROM tipos_ecografias WHERE activo = 1 AND id IN ($marcas)");
    $q->bind_param(str_repeat('i', count($ids)), ...$ids);
    $q->execute();
    $res = $q->get_result();
    while ($row = $res->fetch_assoc()) {
        $estudios[] = ['nombre' => (string)$row['nombre'], 'precio' => (float)$row['precio']];
    }
    $q->close();
}

$validas   = array_column(eco_servicios_adicionales(), 'key');
$servicios = array_values(array_intersect(
    array_map(static fn($s) => (string)$s, $serviciosIn),
    $validas
));

$bundle = eco_calcular_bundle_multi($estudios, $servicios);

echo json_encode([
    'success'     => true,
    'total'       => (float)$bundle['total'],
    'total_texto' => eco_money((float)$bundle['total']),
    'detalle'     => (string)$bundle['motivo'],
    'promos'      => $bundle['promos'],
    'ahorro'      => (float)$bundle['ahorro'],
], JSON_UNESCAPED_UNICODE);
