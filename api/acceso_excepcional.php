<?php
/**
 * Acceso excepcional a un paciente que no está bajo la atención del ecografista
 * ("romper el cristal").
 *
 * No es un permiso que se pide y se olvida: queda escrito en la bitácora con el
 * motivo, el usuario, la IP y la hora, y solo vale un rato dentro de la sesión
 * (ECO_ACCESO_EXCEPCIONAL_MIN). El profesional nunca queda bloqueado ante una
 * urgencia, pero salirse de su ámbito es un acto deliberado y trazable.
 */
session_start();
require_once __DIR__ . '/../lib/core/api.php';
include __DIR__ . '/../core/conexion.php';
require_once __DIR__ . '/../lib/pacientes/mis_pacientes.php';

api_json();
api_require_roles(['ecografista']);
api_require_post();
api_require_csrf();

$paciente_id = api_int('paciente_id');
$motivo      = api_str('motivo');

if ($paciente_id <= 0) {
    api_fail('Paciente no válido.');
}

// El paciente debe existir y estar aprobado: así no sirve para sondear ids.
$st = $conex->prepare("SELECT nombre_completo FROM usuarios WHERE id = ? AND rol = 'paciente' AND estado = 'aprobado'");
$st->bind_param('i', $paciente_id);
$st->execute();
$pac = $st->get_result()->fetch_assoc();
$st->close();
if (!$pac) {
    api_fail('Paciente no encontrado.', 404);
}

$res = eco_acceso_excepcional_conceder($conex, api_uid(), $paciente_id, $motivo);
if (!$res['ok']) {
    api_fail($res['error']);
}

api_ok([
    'paciente'    => $pac['nombre_completo'],
    'vigencia_min' => ECO_ACCESO_EXCEPCIONAL_MIN,
    'message'     => 'Acceso registrado. Queda constancia en la bitácora de auditoría.',
]);
