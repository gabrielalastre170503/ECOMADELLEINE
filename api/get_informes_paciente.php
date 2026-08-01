<?php
session_start();
require_once __DIR__ . '/../lib/core/api.php';
include __DIR__ . '/../core/conexion.php';

api_json();

if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['ecografista', 'administrador', 'recepcionista'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso no autorizado']);
    exit();
}

$paciente_id = isset($_GET['paciente_id']) && is_numeric($_GET['paciente_id']) ? (int)$_GET['paciente_id'] : 0;
if (!$paciente_id) {
    http_response_code(400);
    echo json_encode(['error' => 'ID de paciente inválido']);
    exit();
}

/* Un ecografista solo accede a SUS pacientes (ver eco_ecografista_atiende). */
require_once __DIR__ . '/../lib/pacientes/mis_pacientes.php';
if (api_rol() === 'ecografista' && !eco_ecografista_puede_ver_paciente($conex, api_uid(), $paciente_id)) {
    eco_responder_requiere_confirmacion($paciente_id);
    exit();
}

// Datos del paciente
$stmt = $conex->prepare("SELECT id, nombre_completo, cedula, TIMESTAMPDIFF(YEAR, fecha_nacimiento, CURDATE()) AS edad FROM usuarios WHERE id = ? AND rol = 'paciente'");
$stmt->bind_param('i', $paciente_id);
$stmt->execute();
$paciente = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$paciente) {
    http_response_code(404);
    echo json_encode(['error' => 'Paciente no encontrado']);
    exit();
}

// Todos los informes del paciente con tipo y ecografista
$stmt = $conex->prepare("
    SELECT
        ie.id,
        ie.numero_informe,
        ie.fecha_estudio,
        ie.estado,
        ie.creado_en,
        t.nombre      AS tipo_nombre,
        t.icono       AS tipo_icono,
        t.categoria   AS tipo_categoria,
        u.nombre_completo AS ecografista_nombre
    FROM informes_estudios ie
    LEFT JOIN tipos_ecografias t ON t.id = ie.tipo_ecografia_id
    LEFT JOIN usuarios u         ON u.id = ie.ecografista_id
    WHERE ie.paciente_id = ?
    ORDER BY ie.creado_en DESC
");
$stmt->bind_param('i', $paciente_id);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$estados_labels = [
    'borrador'   => 'Borrador',
    'finalizado' => 'Finalizado',
    'firmado'    => 'Firmado',
    'anulado'    => 'Anulado',
];

$informes = array_map(function ($row) use ($estados_labels) {
    $fecha_raw       = $row['fecha_estudio'] ?: substr($row['creado_en'], 0, 10);
    $fecha_formateada = $fecha_raw ? date('d/m/Y', strtotime($fecha_raw)) : '—';
    return [
        'id'               => (int)$row['id'],
        'numero_informe'   => $row['numero_informe'] ?? '—',
        'fecha_formateada' => $fecha_formateada,
        'estado'           => $row['estado'],
        'estado_label'     => $estados_labels[$row['estado']] ?? $row['estado'],
        'tipo_nombre'      => $row['tipo_nombre']    ?? 'Ecografía',
        'tipo_icono'       => $row['tipo_icono']     ?? 'fa-solid fa-wave-square',
        'tipo_categoria'   => $row['tipo_categoria'] ?? '',
        'ecografista'      => $row['ecografista_nombre'] ?? '—',
    ];
}, $rows);

echo json_encode([
    'paciente_nombre' => $paciente['nombre_completo'],
    'paciente_cedula' => $paciente['cedula'] ?? '—',
    'paciente_edad'   => $paciente['edad']   ?? '—',
    'total'           => count($informes),
    'informes'        => $informes,
], JSON_UNESCAPED_UNICODE);

$conex->close();
