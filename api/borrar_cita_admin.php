<?php
session_start();
include __DIR__ . '/../core/conexion.php';
require_once __DIR__ . '/../lib/seguridad/seguridad.php';

// 1. Seguridad: Solo los administradores pueden borrar citas.
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'administrador') {
    // Redirigir o mostrar un error si no es admin
    header('Location: ' . eco_url('login'));
    exit();
}

// 2. Validar que se recibió un ID de cita por POST + token CSRF (anti CSRF por enlace GET).
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id']) || !is_numeric($_POST['id'])) {
    // Redirigir si no hay un ID válido. La ruta va absoluta: relativa se
    // resolvería contra api/, que no sirve esa página.
    header('Location: ' . eco_url('citas-admin') . '?error=invalid_id');
    exit();
}
require_csrf();

$cita_id = (int)$_POST['id'];

// 3. Preparar y ejecutar la consulta de eliminación de forma segura.
$stmt = $conex->prepare("DELETE FROM citas WHERE id = ?");
$stmt->bind_param("i", $cita_id);

if ($stmt->execute()) {
    // Si se borró correctamente, redirigir con un mensaje de éxito.
    eco_auditar($conex, 'cita_borrada', ['entidad' => 'cita', 'entidad_id' => $cita_id]);
    header('Location: ' . eco_url('citas-admin') . '?status=deleted');
} else {
    // Si hubo un error, redirigir con un mensaje de error.
    header('Location: ' . eco_url('citas-admin') . '?error=delete_failed');
}

$stmt->close();
$conex->close();
exit();
?>