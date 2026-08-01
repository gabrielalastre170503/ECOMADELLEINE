<?php
session_start();
include __DIR__ . '/../core/conexion.php';

// Seguridad: solo el ecografista dueño de la cita puede cancelarla
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'ecografista') {
    header('Location: ' . eco_url('login'));
    exit();
}

// Cancelar cambia datos: POST + token CSRF (antes era un enlace GET, que
// SameSite=Lax no frena en una navegacion de primer nivel).
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . eco_url('proximas-citas'));
    exit();
}
require_csrf();

$cita_id        = isset($_POST['cita_id']) ? (int)$_POST['cita_id'] : 0;
$ecografista_id = (int)$_SESSION['usuario_id'];

if ($cita_id > 0) {
    // Solo se cancelan citas propias que aún no se completaron/cancelaron
    $stmt = $conex->prepare("
        UPDATE citas
        SET estado = 'cancelada', fecha_respuesta = NOW()
        WHERE id = ? AND ecografista_id = ?
          AND estado IN ('confirmada','reprogramada','pendiente','pendiente_paciente')
    ");
    $stmt->bind_param('ii', $cita_id, $ecografista_id);
    $stmt->execute();
    $stmt->close();
}

header('Location: ' . eco_url('proximas-citas'));
exit();
