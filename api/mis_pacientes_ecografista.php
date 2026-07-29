<?php
/**
 * Listado actualizado de "Mis Pacientes": lo usan el refresco periódico de la
 * tabla y el filtro "atendidos en". Devuelve las mismas filas que renderiza la
 * página (lib/pacientes/mis_pacientes.php), de modo que un paciente que
 * recepción acaba de asignar aparezca sin recargar.
 *
 * Parámetros (GET): rango = todos|hoy|ayer|semana|fecha, fecha = Y-m-d
 * Respuesta: { success, total, filas_html, export, vacio }
 */
session_start();
require_once __DIR__ . '/../lib/core/api.php';
include __DIR__ . '/../core/conexion.php';
require_once __DIR__ . '/../lib/pacientes/mis_pacientes.php';
require_once __DIR__ . '/../lib/pacientes/filtros.php';
require_once __DIR__ . '/../lib/facturacion/facturacion.php';

api_json();
api_require_roles(['ecografista']);

$ecografista_id = api_uid();
$rango       = isset($_GET['rango']) ? (string)$_GET['rango'] : 'todos';
$fechaFiltro = isset($_GET['fecha']) ? trim((string)$_GET['fecha']) : '';
$rangoFechas = eco_rango_atencion($rango, $fechaFiltro);

$pacientes = eco_mis_pacientes($conex, $ecografista_id, $rangoFechas);
$montos    = eco_mis_pacientes_montos($conex, $ecografista_id, $rangoFechas);

echo json_encode([
    'success'       => true,
    'total'         => count($pacientes),
    'filas_html'    => eco_mis_pacientes_filas_html($pacientes),
    'export'        => eco_mis_pacientes_export($pacientes),
    'filtrado'      => $rangoFechas !== null,
    'vacio'         => eco_rango_atencion_vacio($rango, $rangoFechas),
    'cobrado'       => eco_money($montos['cobrado']),
    'pendiente'     => eco_money($montos['pendiente']),
    'pendiente_num' => $montos['pendiente'],
], JSON_UNESCAPED_UNICODE);
