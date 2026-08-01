<?php
session_start();
include __DIR__ . '/../core/conexion.php';
require_once __DIR__ . '/../lib/citas/citas.php';

// Seguridad
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'paciente') {
    header('Location: ' . eco_url('login'));
    exit();
}

// Aceptar/rechazar una propuesta cambia datos: POST + token CSRF. Como enlace
// GET, abrir un enlace ajeno movia la fecha de la cita del paciente.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . eco_url('mis-citas'));
    exit();
}
require_csrf();

if (isset($_POST['cita_id']) && isset($_POST['accion'])) {
    $cita_id = $_POST['cita_id'];
    $accion = $_POST['accion'];
    $paciente_id = $_SESSION['usuario_id'];

    if ($accion == 'aceptar') {
        // Mueve la fecha propuesta a la fecha final y confirma la cita
        $stmt = $conex->prepare("UPDATE citas SET fecha_cita = fecha_propuesta, fecha_propuesta = NULL, estado = 'confirmada' WHERE id = ? AND paciente_id = ?");
    } elseif ($accion == 'rechazar') {
        // Cancela la cita
        $stmt = $conex->prepare("UPDATE citas SET estado = 'cancelada' WHERE id = ? AND paciente_id = ?");
    }

    if (isset($stmt)) {
        $stmt->bind_param("ii", $cita_id, $paciente_id);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            if ($accion == 'aceptar') {
                eco_cita_evento($conex, (int)$cita_id, 'aceptada', ['estado_nuevo' => 'confirmada']);
            } else {
                eco_cita_evento($conex, (int)$cita_id, 'rechazada', ['estado_nuevo' => 'cancelada']);
            }
        }
        $stmt->close();
    }
}

header('Location: ' . eco_url('mis-citas'));
exit();
?>