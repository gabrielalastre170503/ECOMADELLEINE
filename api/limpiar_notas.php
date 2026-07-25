<?php
session_start();
require_once __DIR__ . '/../lib/core/api.php';
include __DIR__ . '/../core/conexion.php';
api_json();

if (!isset($_SESSION['usuario_id']) || ($_SESSION['rol'] ?? '') !== 'ecografista') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Acceso denegado']); exit;
}

// Borra notas clinicas de forma irreversible: exige token CSRF. El wrapper de
// fetch() de shell.php ya envia la cabecera X-CSRF-Token en cada peticion.
require_csrf();

$paciente_id = (int)($_POST['paciente_id'] ?? 0);
if ($paciente_id <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Paciente inválido']); exit;
}

if ($s = $conex->prepare("DELETE FROM notas_clinicas WHERE paciente_id=? AND ecografista_id=?")) {
    $s->bind_param('ii', $paciente_id, $_SESSION['usuario_id']);
    $s->execute();
    echo json_encode(['ok' => true, 'eliminadas' => $s->affected_rows]);
    $s->close();
} else {
    echo json_encode(['ok' => false, 'error' => 'Error de base de datos']);
}
