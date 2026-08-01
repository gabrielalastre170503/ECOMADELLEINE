<?php
session_start();
require_once __DIR__ . '/../lib/core/api.php';
include __DIR__ . '/../core/conexion.php';

api_json();

if (!isset($_SESSION['usuario_id']) || ($_SESSION['rol'] ?? '') !== 'ecografista') {
    http_response_code(403);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

$paciente_id    = isset($_GET['paciente_id']) ? (int)$_GET['paciente_id'] : 0;
$ecografista_id = (int)$_SESSION['usuario_id'];

if ($paciente_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Paciente no válido']);
    exit();
}

/* Un ecografista solo accede a SUS pacientes (ver eco_ecografista_atiende). */
require_once __DIR__ . '/../lib/pacientes/mis_pacientes.php';
if (!eco_ecografista_puede_ver_paciente($conex, $ecografista_id, $paciente_id)) {
    eco_responder_requiere_confirmacion($paciente_id);
    exit();
}

// Datos del paciente
$u = null;
if ($st = $conex->prepare("SELECT nombre_completo, cedula, TIMESTAMPDIFF(YEAR, fecha_nacimiento, CURDATE()) AS edad, correo, fecha_nacimiento, fecha_registro FROM usuarios WHERE id = ? AND rol = 'paciente'")) {
    $st->bind_param('i', $paciente_id);
    $st->execute();
    $u = $st->get_result()->fetch_assoc();
    $st->close();
}
if (!$u) {
    http_response_code(404);
    echo json_encode(['error' => 'Paciente no encontrado']);
    exit();
}

// Última cita de este paciente con este ecografista
$c = null;
if ($st = $conex->prepare("
    SELECT c.id, c.fecha_cita, c.fecha_solicitud, c.estado, c.motivo_consulta,
           c.motivo_principal, c.notas_paciente, c.modalidad, c.tipo_cita,
           t.nombre AS tipo_nombre, t.icono AS tipo_icono
    FROM citas c
    LEFT JOIN tipos_ecografias t ON t.id = c.tipo_ecografia_id
    WHERE c.paciente_id = ? AND c.ecografista_id = ?
    ORDER BY c.fecha_cita DESC, c.id DESC
    LIMIT 1")) {
    $st->bind_param('ii', $paciente_id, $ecografista_id);
    $st->execute();
    $c = $st->get_result()->fetch_assoc();
    $st->close();
}

$resp = [
    'paciente' => [
        'nombre'     => $u['nombre_completo'],
        'cedula'     => $u['cedula'],
        'edad'       => $u['edad'],
        'correo'     => $u['correo'],
        'nacimiento' => ($u['fecha_nacimiento'] && $u['fecha_nacimiento'] !== '0000-00-00') ? date('d/m/Y', strtotime($u['fecha_nacimiento'])) : '—',
        'registro'   => $u['fecha_registro'] ? date('d/m/Y', strtotime($u['fecha_registro'])) : '—',
    ],
    'cita' => null,
];

if ($c) {
    $resp['cita'] = [
        'estudio'          => $c['tipo_nombre'],
        'estudio_icono'    => $c['tipo_icono'],
        'tipo_cita'        => $c['tipo_cita'] ? ucwords(str_replace('_', ' ', $c['tipo_cita'])) : '—',
        'modalidad'        => $c['modalidad'] ? ucfirst($c['modalidad']) : '—',
        'fecha'            => $c['fecha_cita'] ? date('d/m/Y h:i A', strtotime($c['fecha_cita'])) : '—',
        'estado'           => $c['estado'],
        'motivo_consulta'  => $c['motivo_consulta'],
        'motivo_principal' => $c['motivo_principal'],
        'notas_paciente'   => $c['notas_paciente'],
    ];
}

echo json_encode($resp, JSON_UNESCAPED_UNICODE);
