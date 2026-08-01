<?php
session_start();
include __DIR__ . '/../core/conexion.php';
require_once __DIR__ . '/../lib/seguridad/seguridad.php';

// Seguridad: Solo administradores
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'administrador') {
    die("Acceso denegado.");
}

// Exige POST + token CSRF (cierra el hueco de CSRF por enlace GET).
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id']) || !is_numeric($_POST['id'])) {
    header('Location: ' . eco_url('usuarios') . '?error=parametros_invalidos');
    exit();
}
require_csrf();

$usuario_id_a_resetear = (int)$_POST['id'];
$filtro_origen = $_POST['filtro'] ?? 'aprobados';

// Seguridad: El administrador no puede restablecer su propia contraseña desde aquí
if ($usuario_id_a_resetear == $_SESSION['usuario_id']) {
    header('Location: ' . eco_url('usuarios') . '?filtro=' . urlencode($filtro_origen) . '&error=auto_reset');
    exit();
}

// 1. Generar una nueva contraseña temporal
$contrasena_temporal = bin2hex(random_bytes(4));
$contrasena_hasheada = password_hash($contrasena_temporal, PASSWORD_DEFAULT);

// 2. Actualizar la contraseña del usuario
$stmt = $conex->prepare("UPDATE usuarios SET contrasena = ? WHERE id = ?");
$stmt->bind_param("si", $contrasena_hasheada, $usuario_id_a_resetear);

if ($stmt->execute()) {
    eco_auditar($conex, 'password_reset', ['entidad' => 'usuario', 'entidad_id' => $usuario_id_a_resetear]);
    /* 3. La contraseña se pasa por la SESION, no por la URL. En la URL quedaba
          registrada en el historial del navegador, en los logs de acceso de
          Apache y se filtraba por la cabecera Referer hacia terceros. */
    $_SESSION['vu_temp_pass'] = $contrasena_temporal;
    $redirect_url = eco_url('usuarios') . '?filtro=' . urlencode($filtro_origen) . '&status=password_reset';
    header('Location: ' . $redirect_url);
} else {
    header('Location: ' . eco_url('usuarios') . '?filtro=' . urlencode($filtro_origen) . '&error=reset_failed');
}

$stmt->close();
$conex->close();
?>