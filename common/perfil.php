<?php
session_start();
include __DIR__ . '/../core/conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . eco_url('login'));
    exit;
}

$usuario_id = (int)$_SESSION['usuario_id'];
$nombre_usuario = $_SESSION['nombre_completo'] ?? 'Usuario';
$rol_usuario    = $_SESSION['rol'] ?? 'usuario';
$correoUsuario  = $_SESSION['correo'] ?? '';
$telefonoUsuario = $_SESSION['telefono'] ?? null;

$fechaRegistro = $_SESSION['fecha_registro'] ?? null;
if (empty($fechaRegistro)) {
    if ($stmt = $conex->prepare('SELECT fecha_registro FROM usuarios WHERE id = ? LIMIT 1')) {
        $stmt->bind_param('i', $usuario_id);
        $stmt->execute();
        $stmt->bind_result($fechaDb);
        if ($stmt->fetch()) {
            $fechaRegistro = $fechaDb;
            $_SESSION['fecha_registro'] = $fechaDb;
        }
        $stmt->close();
    }
}

if ($telefonoUsuario === null || $telefonoUsuario === '') {
    try {
        $st = $conex->prepare('SELECT telefono FROM usuarios WHERE id = ? LIMIT 1');
        $st->bind_param('i', $usuario_id);
        $st->execute();
        $st->bind_result($telDb);
        if ($st->fetch() && $telDb !== null && $telDb !== '') {
            $telefonoUsuario = $telDb;
            $_SESSION['telefono'] = $telDb;
        }
        $st->close();
    } catch (mysqli_sql_exception $e) {
        /* columna telefono opcional */
    }
}

/* Estado de identidad: 2FA, verificación de correo y estado de la cuenta */
$dosFactorActivo = false;
$correoVerificado = true;
$estadoCuenta = '';
if ($st2 = $conex->prepare('SELECT two_factor_enabled, email_verificado, estado FROM usuarios WHERE id = ? LIMIT 1')) {
    $st2->bind_param('i', $usuario_id);
    $st2->execute();
    $st2->bind_result($tfDb, $evDb, $estDb);
    if ($st2->fetch()) {
        $dosFactorActivo  = ((int)$tfDb === 1);
        $correoVerificado = ((int)$evDb === 1);
        $estadoCuenta     = (string)$estDb;
    }
    $st2->close();
}

$fechaRegistroTexto = '—';
$antiguedadTexto = '';
if (!empty($fechaRegistro)) {
    $ts = strtotime($fechaRegistro);
    if ($ts > 0) {
        $fechaRegistroTexto = date('d/m/Y', $ts);
        $dias = (int) floor((time() - $ts) / 86400);
        if ($dias <= 0) {
            $antiguedadTexto = 'desde hoy';
        } elseif ($dias < 30) {
            $antiguedadTexto = 'hace ' . $dias . ' día' . ($dias === 1 ? '' : 's');
        } elseif ($dias < 365) {
            $meses = (int) floor($dias / 30);
            $antiguedadTexto = 'hace ' . $meses . ' mes' . ($meses === 1 ? '' : 'es');
        } else {
            $anios = (int) floor($dias / 365);
            $antiguedadTexto = 'hace ' . $anios . ' año' . ($anios === 1 ? '' : 's');
        }
    } else {
        $fechaRegistroTexto = (string)$fechaRegistro;
    }
}

/* Etiqueta legible del estado de la cuenta */
$estadoCuentaTexto = [
    'aprobado'     => 'Activa y aprobada',
    'pendiente'    => 'Pendiente de aprobación',
    'inhabilitado' => 'Inhabilitada',
][$estadoCuenta] ?? 'Activa';

$ultimaActividad = $_SESSION['ultimo_acceso'] ?? null;
$ultimaActividadTexto = 'Esta es tu primera sesión';
if (!empty($ultimaActividad)) {
    $tsAct = strtotime($ultimaActividad);
    $ultimaActividadTexto = $tsAct > 0 ? date('d/m/Y H:i', $tsAct) : (string)$ultimaActividad;
}

$nombreUsuarioPlano = (string)$nombre_usuario;
$avatarInicial = $nombreUsuarioPlano !== '' ? strtoupper(substr($nombreUsuarioPlano, 0, 1)) : '?';

$browser_title  = 'Mi Perfil';
$page_title     = '';
$page_subtitle  = '';
$active_section = 'perfil';
$body_class     = 'perfil-usuario-page';
// ?v=filemtime como en shell.php: sin esto el navegador sirve el CSS cacheado.
$page_head_extra = '<link rel="stylesheet" href="assets/css/perfil/perfil-usuario.css?v='
    . (@filemtime(__DIR__ . '/../assets/css/perfil/perfil-usuario.css') ?: '1') . '">';

ob_start();
include __DIR__ . '/../layouts/partials/perfil_usuario_vista.php';
$page_content = ob_get_clean();

include __DIR__ . '/../layouts/shell.php';
