<?php
session_start();
include __DIR__ . '/../core/conexion.php';

// Seguridad: solo el paciente dueño puede cancelar
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'paciente') {
    header('Location: ' . eco_url('login'));
    exit();
}

// Cancelar cambia datos: POST + token CSRF (antes era un enlace GET, que
// SameSite=Lax no frena en una navegacion de primer nivel).
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . eco_url('mis-citas'));
    exit();
}
require_csrf();

$cita_id     = isset($_POST['cita_id']) ? (int)$_POST['cita_id'] : 0;
$paciente_id = (int)$_SESSION['usuario_id'];

if ($cita_id > 0) {
    // Solo se cancelan citas propias que aún no se completaron/cancelaron
    $stmt = $conex->prepare("
        UPDATE citas
        SET estado = 'cancelada'
        WHERE id = ? AND paciente_id = ?
          AND estado IN ('confirmada','reprogramada','pendiente','pendiente_paciente')
    ");
    $stmt->bind_param('ii', $cita_id, $paciente_id);
    $stmt->execute();
    $stmt->close();
}

header('Location: ' . eco_url('mis-citas'));
exit();
