<?php
session_start();
require_once __DIR__ . '/../lib/core/api.php';
include __DIR__ . '/../core/conexion.php';
require_once __DIR__ . '/../lib/seguridad/seguridad.php';
require_once __DIR__ . '/../lib/facturacion/facturacion.php';
require_once __DIR__ . '/../lib/citas/citas.php';
require_once __DIR__ . '/../lib/citas/atencion.php';
require_once __DIR__ . '/../lib/comunicaciones/notificaciones.php';

api_json();
$response = ['success' => false, 'message' => 'Ocurrio un error inesperado.'];

api_require_roles(['ecografista', 'administrador', 'recepcionista']);

/* eco_crear_cita_de_alta() vive en lib/citas/atencion.php: la comparten esta
   alta rápida y el alta extendida. */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode($response);
    exit();
}

api_require_csrf();

/* `campo` acompaña a cada error para que el formulario pueda marcar el campo
   concreto y llevar el foco hasta él, en vez de dejar un aviso genérico arriba
   y que el usuario busque cuál de los seis campos era. */
foreach (['nombre_completo' => 'Falta el nombre completo.',
          'fecha_nacimiento' => 'Falta la fecha de nacimiento.',
          'cedula_tipo'      => 'Falta el tipo de documento.',
          'cedula_numero'    => 'Falta el número de documento.',
          'correo'           => 'Falta el correo electrónico.'] as $campo => $aviso) {
    if (empty($_POST[$campo])) {
        $response['message'] = $aviso;
        $response['campo']   = $campo;
        echo json_encode($response);
        exit();
    }
}

$nombre           = trim($_POST['nombre_completo']);
$fecha_nacimiento = $_POST['fecha_nacimiento'];
$correo           = trim($_POST['correo']);
$cedula_tipo      = $_POST['cedula_tipo'];
$cedula_numero    = trim($_POST['cedula_numero']);
$direccion        = trim((string)($_POST['direccion'] ?? ''));
$telefono         = trim((string)($_POST['telefono'] ?? ''));
$creado_por_id    = (int)$_SESSION['usuario_id'];

if (!preg_match('/^\d{7,8}$/', $cedula_numero)) {
    $response['message'] = 'El número de documento debe tener entre 7 y 8 dígitos.';
    $response['campo']   = 'cedula_numero';
    echo json_encode($response);
    exit();
}

$cedula = $cedula_tipo . $cedula_numero;

/* Se distingue cuál de los dos está repetido: "el correo o la cédula ya están
   registrados" obliga a probar los dos a ciegas. */
$check = $conex->prepare("SELECT correo = ? AS mismo_correo, cedula = ? AS misma_cedula
                            FROM usuarios WHERE correo = ? OR cedula = ? LIMIT 1");
$check->bind_param("ssss", $correo, $cedula, $correo, $cedula);
$check->execute();
if ($repe = $check->get_result()->fetch_assoc()) {
    if ((int)$repe['mismo_correo'] === 1) {
        $response['message'] = 'Ese correo electrónico ya está registrado.';
        $response['campo']   = 'correo';
    } else {
        $response['message'] = 'Ese número de documento ya está registrado.';
        $response['campo']   = 'cedula_numero';
    }
    $check->close();
    echo json_encode($response);
    exit();
}
$check->close();

try {
    $fecha_nac = new DateTime($fecha_nacimiento);
    $edad = (new DateTime('today'))->diff($fecha_nac)->y;
} catch (Exception $e) {
    $response['message'] = 'La fecha de nacimiento no es válida.';
    $response['campo']   = 'fecha_nacimiento';
    echo json_encode($response);
    exit();
}

$contrasena_temporal = bin2hex(random_bytes(4));
$contrasena_hash     = password_hash($contrasena_temporal, PASSWORD_DEFAULT);
$rol = 'paciente';
$estado = 'aprobado';

$email_verificado = 1; // creado por un profesional → cuenta de confianza
$insert = $conex->prepare("INSERT INTO usuarios (nombre_completo, fecha_nacimiento, cedula, direccion, telefono, correo, contrasena, rol, estado, email_verificado, creado_por_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$insert->bind_param("sssssssssii", $nombre, $fecha_nacimiento, $cedula, $direccion, $telefono, $correo, $contrasena_hash, $rol, $estado, $email_verificado, $creado_por_id);

if ($insert->execute()) {
    $paciente_id = (int)$insert->insert_id;
    eco_auditar($conex, 'paciente_creado', ['entidad' => 'usuario', 'entidad_id' => $paciente_id, 'detalle' => ['correo' => $correo]]);
    $response['success']  = true;
    $response['message']  = 'Paciente creado con exito.';
    $response['nombre']   = $nombre;
    $response['password'] = $contrasena_temporal;
    $response['correo']   = $correo;

    /* La contraseña también le llega al paciente por correo. Si el envío falla
       —SMTP caído, buzón inexistente— la cuenta YA está creada: no se deshace
       nada, se avisa en la respuesta para que quien la dio de alta sepa que
       tiene que dictarla. */
    require_once __DIR__ . '/../lib/comunicaciones/correo_app.php';
    $errCorreo = null;
    $cuerpo =
        "Hola " . $nombre . ",\n\n"
        . "Se ha creado tu cuenta en EcoMadelleine.\n\n"
        . "Usuario (correo): " . $correo . "\n"
        . "Contraseña temporal: " . $contrasena_temporal . "\n\n"
        . "Entra en " . eco_base_url() . " y cámbiala en «Mi Perfil» la primera vez que inicies sesión.\n\n"
        . "Si no esperabas este mensaje, ignóralo o avísanos.\n\n"
        . "— Clínica de Ecografías EcoMadelleine";
    $enviado = eco_enviar_correo($correo, 'Tu acceso a EcoMadelleine', $cuerpo, $errCorreo);
    $response['correo_enviado'] = $enviado;
    if (!$enviado) {
        error_log('guardar_paciente: no se pudo enviar la clave a ' . $correo . ' — ' . (string)$errCorreo);
    }

    // Recepción puede dejar asentado en el mismo paso el servicio que viene a
    // hacerse el paciente. Eso crea la cita, y la cita es lo que hace aparecer
    // al paciente en "Mis Pacientes" del ecografista asignado.
    // El bloque de servicio solo lo muestra el formulario de recepción.
    $cita = (($_SESSION['rol'] ?? '') === 'recepcionista')
        ? eco_crear_cita_de_alta($conex, $paciente_id, $_POST)
        : null;
    if ($cita !== null) {
        $response['cita'] = $cita;
    }
} else {
    // FIX SEGURIDAD: log interno + mensaje genérico; detecta duplicado (correo/cédula).
    error_log('guardar_paciente: ' . $insert->error);
    $response['message'] = ($insert->errno === 1062)
        ? 'El correo o la cédula ya están registrados.'
        : 'No se pudo crear el paciente. Inténtalo de nuevo.';
}

$insert->close();
$conex->close();
echo json_encode($response);
