<?php
/**
 * Facturación de un paciente (solo lectura, JSON) para el modal "Gestionar paciente".
 * Devuelve las citas facturables del paciente con su saldo, para abonar desde el modal.
 * El ecografista solo ve/abona sus propias citas.
 */
session_start();
require_once __DIR__ . '/../lib/core/api.php';
include __DIR__ . '/../core/conexion.php';
require_once __DIR__ . '/../lib/facturacion/facturacion.php';

api_json();

if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'] ?? '', ['ecografista', 'administrador', 'recepcionista'], true)) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso no autorizado']);
    exit();
}
$rol    = (string)$_SESSION['rol'];
$uid    = (int)$_SESSION['usuario_id'];
$es_eco = ($rol === 'ecografista');

$paciente_id = isset($_GET['paciente_id']) && is_numeric($_GET['paciente_id']) ? (int)$_GET['paciente_id'] : 0;
if ($paciente_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Paciente no válido']);
    exit();
}

/* Un ecografista solo accede a SUS pacientes (ver eco_ecografista_atiende).
   Recepción y administración no se limitan: facturan a todos. */
require_once __DIR__ . '/../lib/pacientes/mis_pacientes.php';
if ($es_eco && !eco_ecografista_puede_ver_paciente($conex, $uid, $paciente_id)) {
    eco_responder_requiere_confirmacion($paciente_id);
    exit();
}

$stmt = $conex->prepare("SELECT nombre_completo, cedula FROM usuarios WHERE id = ? AND rol = 'paciente'");
$stmt->bind_param('i', $paciente_id);
$stmt->execute();
$pac = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$pac) {
    http_response_code(404);
    echo json_encode(['error' => 'Paciente no encontrado']);
    exit();
}

$sql = "SELECT c.id, c.fecha_cita, c.monto_total, c.monto_pagado, c.estado_pago, c.metodo_pago,
               c.motivo_principal AS servicios, t.nombre AS estudio
        FROM citas c
        LEFT JOIN tipos_ecografias t ON t.id = c.tipo_ecografia_id
        WHERE c.paciente_id = ? AND (c.tipo_ecografia_id IS NOT NULL OR c.monto_total IS NOT NULL)";
if ($es_eco) {
    $sql .= " AND c.ecografista_id = ? ";
}
$sql .= " ORDER BY (c.estado_pago = 'pagado'), COALESCE(c.fecha_cita, c.fecha_solicitud) DESC, c.id DESC";

$stmt = $conex->prepare($sql);
if ($es_eco) {
    $stmt->bind_param('ii', $paciente_id, $uid);
} else {
    $stmt->bind_param('i', $paciente_id);
}
$stmt->execute();
$rs = $stmt->get_result();

$citas      = [];
$facturado  = 0.0;
$cobrado    = 0.0;
$por_cobrar = 0.0;
while ($r = $rs->fetch_assoc()) {
    $mt = (float)($r['monto_total'] ?? 0);
    $mp = (float)$r['monto_pagado'];
    $saldo = max($mt - $mp, 0);
    $facturado += $mt;
    $cobrado   += $mp;
    if ($r['estado_pago'] !== 'exonerado') {
        $por_cobrar += $saldo;
    }
    $serv = trim((string)($r['servicios'] ?? ''));
    $estudios = eco_estudios_desde_texto($serv);
    $lead = $estudios ? implode(', ', $estudios) : ($r['estudio'] ?: 'Sin estudio');

    $citas[] = [
        'id'           => (int)$r['id'],
        'fecha_fmt'    => $r['fecha_cita'] ? date('d/m/Y', strtotime($r['fecha_cita'])) : '—',
        'estudio'      => $lead,
        'servicios'    => ($serv !== '' && $serv !== $lead) ? $serv : '',
        'total'        => $mt,
        'total_fmt'    => eco_money($mt),
        'pagado_fmt'   => eco_money($mp),
        'saldo'        => $saldo,
        'saldo_fmt'    => eco_money($saldo),
        'estado_pago'  => $r['estado_pago'],
        'estado_label' => eco_estado_pago_label($r['estado_pago']),
        'estado_color' => eco_estado_pago_color($r['estado_pago']),
        'metodo'       => trim((string)($r['metodo_pago'] ?? '')),
        'puede_abonar' => ($saldo > 0 && $r['estado_pago'] !== 'exonerado'),
    ];
}
$stmt->close();

echo json_encode([
    'paciente' => ['nombre' => $pac['nombre_completo'], 'cedula' => $pac['cedula'] ?? ''],
    'totales'  => [
        'facturado_fmt'  => eco_money($facturado),
        'cobrado_fmt'    => eco_money($cobrado),
        'por_cobrar_fmt' => eco_money($por_cobrar),
    ],
    'metodos' => eco_metodos_pago(),
    'citas'   => $citas,
], JSON_UNESCAPED_UNICODE);

$conex->close();
