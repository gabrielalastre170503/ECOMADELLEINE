<?php
if (!isset($_POST['send'])) {
    return;
}

include __DIR__ . "/../core/conexion.php";

$nombre        = trim($_POST['name'] ?? '');
$fecha_nac     = trim($_POST['fecha_nacimiento'] ?? '');
$nacionalidad  = trim($_POST['nacionalidad'] ?? 'V');
$cedula_numero = trim($_POST['cedula_numero'] ?? '');
$correo        = trim($_POST['email'] ?? '');
$password      = $_POST['password'] ?? '';
$telefono      = trim($_POST['telefono'] ?? '');
$direccion     = trim($_POST['direccion'] ?? '');
$acepto        = isset($_POST['acepto_privacidad']);

if ($nombre === '' || $fecha_nac === '' || $cedula_numero === '' || $correo === '' || $password === '' || $telefono === '' || $direccion === '' || !$acepto) {
    header('Location: index.php?status=error');
    exit();
}

if (!preg_match('/^\d{7,8}$/', $cedula_numero)) {
    header('Location: index.php?status=error');
    exit();
}

$cedula = $nacionalidad . '-' . $cedula_numero;

try {
    $fecha = DateTime::createFromFormat('d-m-Y', $fecha_nac) ?: DateTime::createFromFormat('Y-m-d', $fecha_nac);
    if (!$fecha) {
        throw new Exception('Fecha invalida');
    }
    $fecha_mysql = $fecha->format('Y-m-d');
    $edad = $fecha->diff(new DateTime('today'))->y;
} catch (Exception $e) {
    header('Location: index.php?status=error');
    exit();
}

$check = $conex->prepare("SELECT id FROM usuarios WHERE correo = ? OR cedula = ?");
$check->bind_param("ss", $correo, $cedula);
$check->execute();
if ($check->get_result()->num_rows > 0) {
    $check->close();
    header('Location: ' . eco_url('login') . '?error=user_exists');
    exit();
}
$check->close();

$hash = password_hash($password, PASSWORD_DEFAULT);
$rol = 'paciente';
$estado = 'aprobado';

$stmt = $conex->prepare("INSERT INTO usuarios (nombre_completo, fecha_nacimiento, cedula, direccion, correo, telefono, contrasena, rol, estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("sssssssss", $nombre, $fecha_mysql, $cedula, $direccion, $correo, $telefono, $hash, $rol, $estado);

if ($stmt->execute()) {
    $new_user_id = $stmt->insert_id;
    session_start();
    $_SESSION['usuario_id']     = $new_user_id;
    $_SESSION['nombre_completo']= $nombre;
    $_SESSION['correo']         = $correo;
    $_SESSION['rol']            = $rol;
    header('Location: ' . eco_url('login') . '?status=registro_ok');
    exit();
}

$stmt->close();
$conex->close();
header('Location: index.php?status=error');
exit();
