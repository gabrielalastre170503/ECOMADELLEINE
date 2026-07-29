<?php
session_start();
require_once __DIR__ . '/../lib/core/api.php';
include __DIR__ . '/../core/conexion.php';
require_once __DIR__ . '/../lib/seguridad/seguridad.php';
require_once __DIR__ . '/../lib/citas/citas.php';
require_once __DIR__ . '/../lib/citas/atencion.php';

api_json();
$response = ['success' => false, 'message' => 'Datos invalidos.'];

api_require_roles(['ecografista', 'administrador', 'recepcionista']);

api_require_csrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['paciente_id'], $_POST['fecha_cita'])) {
    echo json_encode($response);
    exit();
}

$paciente_id = (int)$_POST['paciente_id'];
$fecha_cita  = $_POST['fecha_cita'];
$motivo      = trim($_POST['motivo_consulta'] ?? '');

$rol_sesion = $_SESSION['rol'] ?? '';
if ($rol_sesion === 'recepcionista' || $rol_sesion === 'administrador') {
    $ecografista_id = (int)($_POST['ecografista_id'] ?? 0);
    if ($ecografista_id <= 0) {
        $response['message'] = 'Seleccione un ecografista responsable.';
        echo json_encode($response);
        exit();
    }
    $chkEco = $conex->prepare("SELECT id FROM usuarios WHERE id = ? AND rol = 'ecografista' AND estado = 'aprobado'");
    $chkEco->bind_param('i', $ecografista_id);
    $chkEco->execute();
    if (!$chkEco->get_result()->fetch_assoc()) {
        $chkEco->close();
        $response['message'] = 'Ecografista no válido.';
        echo json_encode($response);
        exit();
    }
    $chkEco->close();
} else {
    $ecografista_id = (int)$_SESSION['usuario_id'];
}

// Estudios, servicios y cobro: mismo cálculo que el alta de paciente, para que
// un descuento o una promoción no dependan de por dónde se creó la atención.
$atencion = eco_atencion_desde_form($conex, $_POST);
if ($atencion === null) {
    $response['message'] = 'Elija al menos un tipo de ecografía o un servicio.';
    echo json_encode($response);
    exit();
}

$etiqueta = $atencion['estudios']
    ? implode(', ', $atencion['estudios'])
    : ($atencion['servicios'] ? 'los servicios solicitados' : 'su atención');
$fecha_formateada = date('d/m/Y \a \l\a\s h:i A', strtotime($fecha_cita));
$notificacion = "Tu cita para <strong>" . htmlspecialchars($etiqueta) . "</strong> ha sido programada para el <strong>{$fecha_formateada}</strong>.";

// bind_param toma los argumentos por referencia: se usan variables sueltas.
$tipo_eco_id = $atencion['tipo_eco_id'];
$motivo_prin = $atencion['motivo'];
$monto_total = $atencion['total'];
$monto_pagado = $atencion['pagado'];
$estado_pago = $atencion['estado_pago'];
$metodo_pago = $atencion['metodo_pago'];
$fecha_pago  = $atencion['fecha_pago'];

$stmt = $conex->prepare("INSERT INTO citas
    (paciente_id, ecografista_id, tipo_ecografia_id, fecha_cita, motivo_consulta, motivo_principal,
     estado, notificacion_paciente, monto_total, monto_pagado, estado_pago, metodo_pago, fecha_pago)
    VALUES (?, ?, ?, ?, ?, ?, 'confirmada', ?, ?, ?, ?, ?, ?)");
$stmt->bind_param(
    "iiissssddsss",
    $paciente_id, $ecografista_id, $tipo_eco_id, $fecha_cita, $motivo, $motivo_prin,
    $notificacion, $monto_total, $monto_pagado, $estado_pago, $metodo_pago, $fecha_pago
);

if ($stmt->execute()) {
    $nueva_cita_id = (int)$stmt->insert_id;
    eco_auditar($conex, 'cita_creada', ['entidad' => 'cita', 'entidad_id' => $nueva_cita_id, 'detalle' => ['paciente_id' => $paciente_id, 'ecografista_id' => $ecografista_id, 'estudios' => $atencion['estudios']]]);
    eco_cita_evento($conex, $nueva_cita_id, 'creada', ['estado_nuevo' => 'confirmada', 'detalle' => ['estudios' => $atencion['estudios'], 'fecha' => $fecha_cita, 'total' => $atencion['total']]]);
    if ($atencion['metodo_pago'] !== null) {
        eco_cita_evento($conex, $nueva_cita_id, 'pago_registrado', [
            'detalle' => ['metodo' => $atencion['metodo_pago'], 'monto' => $atencion['total']],
        ]);
    }
    $response['success'] = true;
    $response['message'] = 'Cita creada y notificada al paciente.';
    $response['cita_id'] = $nueva_cita_id;
    $response['atencion'] = eco_atencion_resumen($atencion);
} else {
    // FIX SEGURIDAD: no exponer el detalle interno de MySQL al cliente.
    error_log('guardar_cita_directa: ' . $stmt->error);
    $response['message'] = 'No se pudo guardar la cita. Inténtalo de nuevo.';
}

$stmt->close();
$conex->close();
echo json_encode($response);
