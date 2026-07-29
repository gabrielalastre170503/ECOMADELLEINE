<?php
/**
 * Alta paciente desde recepción con contraseña elegida por el usuario (AJAX JSON).
 */
session_start();
require_once __DIR__ . '/../lib/core/api.php';
include __DIR__ . '/../core/conexion.php';
require_once __DIR__ . '/../lib/citas/atencion.php';

api_json();
$response = ['success' => false, 'message' => ''];

api_require_roles(['recepcionista', 'administrador']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode($response);
    exit;
}

api_require_csrf();

$nombre_completo  = trim((string)($_POST['nombre_completo'] ?? ''));
$fecha_nacimiento = (string)($_POST['fecha_nacimiento'] ?? '');
$cedula_tipo      = (string)($_POST['cedula_tipo'] ?? '');
$cedula_numero    = trim((string)($_POST['cedula_numero'] ?? ''));
$direccion        = trim((string)($_POST['direccion'] ?? ''));
$telefono         = trim((string)($_POST['telefono'] ?? ''));
$correo           = trim((string)($_POST['correo'] ?? ''));
$contrasena       = (string)($_POST['contrasena'] ?? '');
$confirmar        = (string)($_POST['confirmar_contrasena'] ?? '');

if ($nombre_completo === '' || $fecha_nacimiento === '' || $cedula_tipo === '' || $cedula_numero === '' || $correo === '') {
    $response['message'] = 'Complete todos los campos obligatorios.';
    echo json_encode($response);
    exit;
}

if (!preg_match('/^\d{7,8}$/', $cedula_numero)) {
    $response['message'] = 'El número de cédula debe tener entre 7 y 8 dígitos.';
    echo json_encode($response);
    exit;
}

if ($contrasena !== $confirmar) {
    $response['message'] = 'Las contraseñas no coinciden.';
    echo json_encode($response);
    exit;
}

if (strlen($contrasena) < 8 || !preg_match('/[A-Z]/', $contrasena) || !preg_match('/[\W_]/', $contrasena)) {
    $response['message'] = 'La contraseña debe tener al menos 8 caracteres, una mayúscula y un símbolo.';
    echo json_encode($response);
    exit;
}

$cedula = $cedula_tipo . $cedula_numero;
$creado_por_id = (int)$_SESSION['usuario_id'];

try {
    $fecha_nac = new DateTime($fecha_nacimiento);
    $edad = (new DateTime('today'))->diff($fecha_nac)->y;
} catch (Exception $e) {
    $response['message'] = 'Fecha de nacimiento inválida.';
    echo json_encode($response);
    exit;
}

$check = $conex->prepare('SELECT id FROM usuarios WHERE correo = ? OR cedula = ?');
$check->bind_param('ss', $correo, $cedula);
$check->execute();
if ($check->get_result()->num_rows > 0) {
    $check->close();
    $response['message'] = 'El correo o la cédula ya están registrados.';
    echo json_encode($response);
    exit;
}
$check->close();

$contrasena_hasheada = password_hash($contrasena, PASSWORD_DEFAULT);
$rol = 'paciente';
$estado = 'aprobado';

$email_verificado = 1; // creado por recepción → cuenta de confianza
$insert = $conex->prepare('INSERT INTO usuarios (nombre_completo, fecha_nacimiento, cedula, direccion, telefono, correo, contrasena, rol, estado, email_verificado, creado_por_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
$insert->bind_param('sssssssssii', $nombre_completo, $fecha_nacimiento, $cedula, $direccion, $telefono, $correo, $contrasena_hasheada, $rol, $estado, $email_verificado, $creado_por_id);

if ($insert->execute()) {
    $paciente_id = (int)$insert->insert_id;
    $response['success'] = true;
    $response['message'] = 'Paciente registrado.';
    $response['nombre'] = $nombre_completo;

    // Igual que en el alta rápida: si recepción dejó asentado el servicio, se
    // crea la cita, y esa cita es lo que hace aparecer al paciente en
    // "Mis Pacientes" del ecografista asignado.
    $cita = (($_SESSION['rol'] ?? '') === 'recepcionista')
        ? eco_crear_cita_de_alta($conex, $paciente_id, $_POST)
        : null;
    if ($cita !== null) {
        $response['cita'] = $cita;
    }
} else {
    // FIX SEGURIDAD: log interno + mensaje genérico; detecta duplicado (correo/cédula).
    error_log('guardar_paciente_extendido: ' . $insert->error);
    $response['message'] = ($insert->errno === 1062)
        ? 'El correo o la cédula ya están registrados.'
        : 'No se pudo guardar el paciente. Inténtalo de nuevo.';
}

$insert->close();
$conex->close();
echo json_encode($response);
