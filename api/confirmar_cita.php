<?php
session_start();
include __DIR__ . '/../core/conexion.php';
require_once __DIR__ . '/../lib/citas/citas.php';
require_once __DIR__ . '/../lib/comunicaciones/notificaciones.php';

// Seguridad: Solo ecografistas pueden confirmar citas
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['ecografista'])) {
    header('Location: ' . eco_url('login'));
    exit();
}

// Confirmar una cita cambia datos: POST + token CSRF. Con un enlace GET
// bastaba que el ecografista abriera un enlace ajeno para confirmarla.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . eco_url('solicitudes'));
    exit();
}
require_csrf();

if (!isset($_POST['cita_id']) || !is_numeric($_POST['cita_id'])) {
    header('Location: ' . eco_url('solicitudes') . '?error=parametros_invalidos');
    exit();
}

$cita_id        = (int)$_POST['cita_id'];
$ecografista_id = (int)$_SESSION['usuario_id'];

// Confirma la cita (solo si está pendiente y asignada a este ecografista)
$stmt = $conex->prepare("UPDATE citas SET estado = 'confirmada', fecha_respuesta = NOW() WHERE id = ? AND ecografista_id = ? AND estado = 'pendiente'");
$stmt->bind_param("ii", $cita_id, $ecografista_id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        eco_cita_evento($conex, $cita_id, 'confirmada', ['estado_anterior' => 'pendiente', 'estado_nuevo' => 'confirmada']);
        if ($c = $conex->query("SELECT paciente_id, fecha_cita FROM citas WHERE id = " . (int)$cita_id)->fetch_assoc()) {
            $cuando = !empty($c['fecha_cita']) ? date('d/m/Y H:i', strtotime($c['fecha_cita'])) : '';
            eco_notificar($conex, (int)$c['paciente_id'], 'cita_confirmada', 'Cita confirmada', [
                'mensaje' => $cuando ? ('Tu cita fue confirmada para el ' . $cuando . '.') : 'Tu cita fue confirmada.',
                'url'     => 'mis-citas',
                'icono'   => 'fa-solid fa-calendar-check',
            ]);
        }
    }
    header('Location: ' . eco_url('solicitudes') . '?status=cita_confirmada');
} else {
    header('Location: ' . eco_url('solicitudes') . '?error=confirmacion_fallida');
}

$stmt->close();
$conex->close();
exit();
