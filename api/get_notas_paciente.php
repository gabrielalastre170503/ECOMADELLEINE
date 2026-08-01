<?php
session_start();
require_once __DIR__ . '/../lib/core/api.php';
include __DIR__ . '/../core/conexion.php';
api_json();

if (!isset($_SESSION['usuario_id']) || ($_SESSION['rol'] ?? '') !== 'ecografista') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Acceso denegado']); exit;
}

$paciente_id = (int)($_GET['paciente_id'] ?? 0);
if ($paciente_id <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Paciente inválido']); exit;
}

/* Las notas de sesión son datos clínicos: solo las ve el ecografista que
   atiende a ese paciente (ver eco_ecografista_atiende). */
require_once __DIR__ . '/../lib/pacientes/mis_pacientes.php';
if (!eco_ecografista_puede_ver_paciente($conex, api_uid(), $paciente_id)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Este paciente no está bajo tu atención.',
                      'requiere_confirmacion' => true, 'paciente_id' => $paciente_id],
                     JSON_UNESCAPED_UNICODE);
    exit;
}

$paciente = null;
if ($s = $conex->prepare("SELECT id, nombre_completo, cedula, correo FROM usuarios WHERE id=? AND rol='paciente' LIMIT 1")) {
    $s->bind_param('i', $paciente_id); $s->execute();
    $paciente = $s->get_result()->fetch_assoc();
    $s->close();
}
if (!$paciente) {
    echo json_encode(['ok' => false, 'error' => 'Paciente no encontrado']); exit;
}

$notas = [];
if ($s = $conex->prepare("
    SELECT n.id, n.fecha_sesion, n.contenido, n.creado_en, u.nombre_completo AS autor
    FROM notas_clinicas n
    LEFT JOIN usuarios u ON u.id=n.ecografista_id
    WHERE n.paciente_id=?
    ORDER BY n.fecha_sesion DESC")) {
    $s->bind_param('i', $paciente_id); $s->execute();
    $notas = $s->get_result()->fetch_all(MYSQLI_ASSOC);
    $s->close();
}

echo json_encode([
    'ok' => true,
    'paciente' => $paciente,
    'notas' => $notas,
]);
