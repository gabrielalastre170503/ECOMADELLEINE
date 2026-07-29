<?php
/**
 * Historia clinica consolidada de un paciente: linea de tiempo unificada de
 * informes de estudio, notas de sesion y citas. Solo lectura (JSON).
 *
 * Los datos los arma lib/pacientes/historia.php, compartido con la ficha del
 * paciente de recepcion, para que ambas vistas no puedan divergir.
 */
session_start();
require_once __DIR__ . '/../lib/core/api.php';
include __DIR__ . '/../core/conexion.php';
require_once __DIR__ . '/../lib/pacientes/historia.php';
require_once __DIR__ . '/../lib/seguridad/seguridad.php';

api_json();

if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['ecografista', 'administrador', 'recepcionista'], true)) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso no autorizado']);
    exit();
}

$paciente_id = isset($_GET['paciente_id']) && is_numeric($_GET['paciente_id']) ? (int)$_GET['paciente_id'] : 0;
if ($paciente_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Paciente no válido']);
    exit();
}

$paciente = eco_paciente_ficha($conex, $paciente_id);
if (!$paciente) {
    http_response_code(404);
    echo json_encode(['error' => 'Paciente no encontrado']);
    exit();
}

// Bitácora de acceso a datos clínicos (cumplimiento): quién consultó esta historia.
eco_auditar($conex, 'acceso_historia_clinica', [
    'entidad'    => 'paciente',
    'entidad_id' => $paciente_id,
    'detalle'    => ['paciente' => $paciente['nombre_completo'] ?? ''],
]);

$historia = eco_historia_clinica($conex, $paciente_id);

// Fecha visible; fecha_orden solo servía para ordenar.
$eventos = [];
foreach ($historia['eventos'] as $ev) {
    $ev['fecha_fmt'] = $ev['fecha'] ? date('d/m/Y', strtotime($ev['fecha'])) : '—';
    unset($ev['fecha_orden']);
    $eventos[] = $ev;
}

$fmt_fecha = static function ($v) {
    return ($v && $v !== '0000-00-00') ? date('d/m/Y', strtotime($v)) : '—';
};

echo json_encode([
    'paciente' => [
        'nombre'     => $paciente['nombre_completo'],
        'cedula'     => $paciente['cedula'] ?? '',
        'edad'       => $paciente['edad'] ?? '',
        'correo'     => $paciente['correo'] ?? '',
        'telefono'   => $paciente['telefono'] ?? '',
        'direccion'  => $paciente['direccion'] ?? '',
        'nacimiento' => $fmt_fecha($paciente['fecha_nacimiento'] ?? null),
        'registro'   => $fmt_fecha($paciente['fecha_registro'] ?? null),
    ],
    'resumen'        => $historia['resumen'],
    'total'          => count($eventos),
    'costo_total'    => $historia['costo_total'],
    'costo_total_fmt'=> $historia['costo_total'] > 0 ? eco_money($historia['costo_total']) : '',
    'eventos'        => $eventos,
], JSON_UNESCAPED_UNICODE);

$conex->close();
