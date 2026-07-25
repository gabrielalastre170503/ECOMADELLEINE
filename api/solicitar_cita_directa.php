<?php
session_start();
include __DIR__ . '/../core/conexion.php';
require_once __DIR__ . '/../lib/citas/citas.php';

// Seguridad: Solo pacientes pueden solicitar
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'paciente') {
    header('Location: ' . eco_url('login'));
    exit();
}

// Crea la cita (y su cargo): exige token CSRF del formulario.
require_csrf();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validar que todos los datos necesarios llegaron
    if (empty($_POST['ecografista_id']) || empty($_POST['fecha_seleccionada']) || empty($_POST['hora_seleccionada']) || empty($_POST['motivo_consulta'])) {
        header('Location: ' . eco_url('solicitar-cita') . '?error=faltan_datos');
        exit();
    }

    $paciente_id = $_SESSION['usuario_id'];
    $ecografista_id = $_POST['ecografista_id'];
    $motivo = $_POST['motivo_consulta'];
    
    // Nuevos campos
    $tipo_cita = $_POST['tipo_cita'];
    $modalidad = $_POST['modalidad'];
    $motivo_principal = $_POST['motivo_principal'];
    $notas_paciente = $_POST['notas_paciente'];
    // Tipo de ecografía elegido en el selector visual (puede ir nulo si no se eligió)
    $tipo_ecografia_id = !empty($_POST['tipo_ecografia_id']) ? (int)$_POST['tipo_ecografia_id'] : null;

    // Cargo inicial: el "Total $X" acordado en el resumen de servicios (incluye todo
    // el bundle); si no hay total en el texto, el precio del estudio seleccionado.
    require_once __DIR__ . '/../lib/facturacion/facturacion.php';
    $monto_total = eco_total_desde_texto($motivo_principal);
    if ($monto_total === null && $tipo_ecografia_id) {
        if ($pst = $conex->prepare("SELECT precio FROM tipos_ecografias WHERE id = ?")) {
            $pst->bind_param('i', $tipo_ecografia_id);
            $pst->execute();
            $prow = $pst->get_result()->fetch_assoc();
            $pst->close();
            if ($prow && (float)$prow['precio'] > 0) {
                $monto_total = (float)$prow['precio'];
            }
        }
    }

    // Combinar la fecha y la hora seleccionadas
    $fecha_cita_str = $_POST['fecha_seleccionada'] . ' ' . $_POST['hora_seleccionada'];
    $fecha_cita = date('Y-m-d H:i:s', strtotime($fecha_cita_str));

    // Insertar la cita con todos los nuevos detalles
    $stmt = $conex->prepare("
        INSERT INTO citas (paciente_id, ecografista_id, tipo_ecografia_id, motivo_consulta, tipo_cita, modalidad, motivo_principal, notas_paciente, fecha_cita, estado, monto_total)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendiente', ?)
    ");
    $stmt->bind_param("iiissssssd", $paciente_id, $ecografista_id, $tipo_ecografia_id, $motivo, $tipo_cita, $modalidad, $motivo_principal, $notas_paciente, $fecha_cita, $monto_total);
    
    if ($stmt->execute()) {
        eco_cita_evento($conex, (int)$stmt->insert_id, 'solicitada', ['estado_nuevo' => 'pendiente', 'detalle' => ['tipo_ecografia_id' => $tipo_ecografia_id, 'fecha' => $fecha_cita]]);
        header('Location: ' . eco_url('mis-citas') . '?status=cita_creada');
    } else {
        header('Location: ' . eco_url('solicitar-cita') . '?error=error_guardar');
    }
    $stmt->close();
}
$conex->close();
?>