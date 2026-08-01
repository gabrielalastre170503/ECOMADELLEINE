<?php
session_start();
require_once __DIR__ . '/../lib/core/api.php';
include __DIR__ . '/../core/conexion.php';
require_once __DIR__ . '/../lib/seguridad/seguridad.php';

api_json();

if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['ecografista', 'administrador', 'recepcionista'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso no autorizado']);
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'ID de paciente no valido']);
    exit();
}

$paciente_id = (int)$_GET['id'];

/* Un ecografista solo accede a SUS pacientes (ver eco_ecografista_atiende).
   Recepción y administración siguen viendo a todos: lo necesitan para agendar
   y gestionar. */
require_once __DIR__ . '/../lib/pacientes/mis_pacientes.php';
if (api_rol() === 'ecografista' && !eco_ecografista_puede_ver_paciente($conex, api_uid(), $paciente_id)) {
    eco_responder_requiere_confirmacion($paciente_id);
    exit();
}

$response = [];

$stmt = $conex->prepare("SELECT nombre_completo, cedula, direccion, correo, telefono, TIMESTAMPDIFF(YEAR, fecha_nacimiento, CURDATE()) AS edad, fecha_nacimiento, fecha_registro FROM usuarios WHERE id = ? AND rol = 'paciente'");
$stmt->bind_param("i", $paciente_id);
$stmt->execute();
$paciente = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$paciente) {
    http_response_code(404);
    echo json_encode(['error' => 'Paciente no encontrado']);
    exit();
}
$response['paciente'] = $paciente;

// Bitácora de acceso a datos clínicos (cumplimiento): quién abrió esta ficha.
eco_auditar($conex, 'acceso_ficha_paciente', [
    'entidad'    => 'paciente',
    'entidad_id' => $paciente_id,
    'detalle'    => ['paciente' => $paciente['nombre_completo'] ?? ''],
]);

$stmt = $conex->prepare("SELECT COUNT(id) AS total FROM informes_estudios WHERE paciente_id = ?");
$stmt->bind_param("i", $paciente_id);
$stmt->execute();
$response['total_estudios'] = (int)$stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$stmt = $conex->prepare("SELECT COUNT(id) AS total FROM citas WHERE paciente_id = ?");
$stmt->bind_param("i", $paciente_id);
$stmt->execute();
$response['total_citas'] = (int)$stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$stmt = $conex->prepare("
    SELECT inf.id, inf.numero_informe, inf.fecha_estudio, t.nombre AS tipo_nombre
    FROM informes_estudios inf
    LEFT JOIN tipos_ecografias t ON t.id = inf.tipo_ecografia_id
    WHERE inf.paciente_id = ?
    ORDER BY inf.creado_en DESC
    LIMIT 5
");
$stmt->bind_param("i", $paciente_id);
$stmt->execute();
$response['ultimos_estudios'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Servicios que el paciente marco al solicitar su cita abierta (misma cita que se
// reusara para facturar). Se devuelven como claves para pre-marcarlos en la modal
// de "Seleccionar tipo de expediente".
require_once __DIR__ . '/../lib/facturacion/facturacion.php';
$response['servicios_cita'] = [];
$response['estudios_cita']  = [];
$response['servicios_hoy']  = [];
if (($_SESSION['rol'] ?? '') === 'ecografista') {
    $eco_uid = (int)$_SESSION['usuario_id'];
    $stmt = $conex->prepare(
        "SELECT motivo_principal FROM citas
          WHERE paciente_id = ? AND ecografista_id = ? AND monto_pagado = 0 AND estado <> 'cancelada'
          ORDER BY (DATE(COALESCE(fecha_cita, fecha_solicitud)) = CURDATE()) DESC,
                   COALESCE(fecha_cita, fecha_solicitud) DESC, id DESC
          LIMIT 1"
    );
    $stmt->bind_param("ii", $paciente_id, $eco_uid);
    $stmt->execute();
    if ($row = $stmt->get_result()->fetch_assoc()) {
        $motivo_open = (string)($row['motivo_principal'] ?? '');
        $response['servicios_cita'] = eco_servicios_desde_texto($motivo_open);

        // Estudios ya elegidos por el paciente (nombre + precio) para que el banner
        // de facturacion del ecografista calcule el total real (no solo el estudio actual).
        $nombres_estudio = eco_estudios_desde_texto($motivo_open);
        if ($nombres_estudio) {
            $mapa_precios = [];
            if ($rp = $conex->query("SELECT nombre, precio FROM tipos_ecografias")) {
                while ($t = $rp->fetch_assoc()) {
                    $mapa_precios[mb_strtolower(trim((string)$t['nombre']))] = (float)$t['precio'];
                }
                $rp->free();
            }
            foreach ($nombres_estudio as $nom) {
                $response['estudios_cita'][] = [
                    'nombre' => $nom,
                    'precio' => $mapa_precios[mb_strtolower(trim($nom))] ?? 0.0,
                ];
            }
        }
    }
    $stmt->close();

    // Servicios ya facturados HOY (cualquier estado de pago). Se DESACTIVAN en el
    // modal para no cobrarlos dos veces el mismo dia (ej. una sola consulta).
    $st_hoy = $conex->prepare(
        "SELECT motivo_principal FROM citas
          WHERE paciente_id = ? AND ecografista_id = ? AND estado <> 'cancelada'
            AND DATE(COALESCE(fecha_cita, fecha_solicitud)) = CURDATE()"
    );
    $st_hoy->bind_param("ii", $paciente_id, $eco_uid);
    $st_hoy->execute();
    $rs_hoy  = $st_hoy->get_result();
    $set_hoy = [];
    while ($rh = $rs_hoy->fetch_assoc()) {
        foreach (eco_servicios_desde_texto((string)($rh['motivo_principal'] ?? '')) as $kk) {
            $set_hoy[$kk] = true;
        }
    }
    $response['servicios_hoy'] = array_keys($set_hoy);
    $st_hoy->close();
}

echo json_encode($response);
$conex->close();
