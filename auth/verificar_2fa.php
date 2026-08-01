<?php
/*
 * verificar_2fa.php — Segundo factor por código OTP enviado al correo (Fase 1).
 * Requiere $_SESSION['2fa_pending'] (creado en login.php tras validar la contraseña).
 */
if (session_status() === PHP_SESSION_NONE) session_start();
include __DIR__ . '/../core/conexion.php';
require_once __DIR__ . '/../lib/comunicaciones/correo_app.php';

if (empty($_SESSION['2fa_pending']) || !is_array($_SESSION['2fa_pending'])) {
    header('Location: ' . eco_url('login'));
    exit;
}
$pend = &$_SESSION['2fa_pending'];
$error = '';
$info  = '';

/* Reenviar código.
   Antes esto reiniciaba 'intentos' a 0 y no tenía tope: bastaba encadenar
   reenvíos para saltarse el límite de 5 intentos —y de paso llenar el buzón
   del usuario de correos—. Ahora el contador de intentos NO se toca, hay
   espera mínima entre envíos y un máximo por sesión pendiente. */
define('ECO_2FA_REENVIO_ESPERA', 60);   // segundos entre reenvíos
define('ECO_2FA_REENVIO_MAX',     3);   // reenvíos por intento de acceso

if (isset($_GET['reenviar'])) {
    $ahora     = time();
    $reenvios  = (int)($pend['reenvios'] ?? 0);
    $ultimo    = (int)($pend['ultimo_envio'] ?? 0);
    $restante  = ECO_2FA_REENVIO_ESPERA - ($ahora - $ultimo);

    if ($reenvios >= ECO_2FA_REENVIO_MAX) {
        $error = 'Has pedido demasiados códigos. Vuelve a iniciar sesión para empezar de nuevo.';
    } elseif ($ultimo > 0 && $restante > 0) {
        $error = 'Espera ' . $restante . ' segundo(s) antes de pedir otro código.';
    } else {
        $otp = eco_otp_codigo();
        $pend['otp_hash']     = password_hash($otp, PASSWORD_DEFAULT);
        $pend['expira']       = $ahora + 600;
        $pend['reenvios']     = $reenvios + 1;
        $pend['ultimo_envio'] = $ahora;
        $cuerpo = "Hola {$pend['nombre']},\n\nTu nuevo código de verificación es:\n\n    {$otp}\n\n"
            . "Vence en 10 minutos.\n\n— EcoMadelleine · Centro de Diagnóstico";
        eco_enviar_correo((string)$pend['correo'], 'Tu código de acceso · EcoMadelleine', $cuerpo);
        $info = 'Te enviamos un nuevo código a tu correo.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $codigo = preg_replace('/\D/', '', (string)($_POST['codigo'] ?? ''));

    if (time() > (int)$pend['expira']) {
        $error = 'El código venció. Solicita uno nuevo.';
    } elseif ((int)$pend['intentos'] >= 5) {
        unset($_SESSION['2fa_pending']);
        header('Location: ' . eco_url('login') . '?status=2fa_bloqueado');
        exit;
    } elseif ($codigo === '' || !password_verify($codigo, $pend['otp_hash'])) {
        $pend['intentos'] = (int)$pend['intentos'] + 1;
        $restantes = 5 - (int)$pend['intentos'];
        $error = 'Código incorrecto.' . ($restantes > 0 ? " Te quedan {$restantes} intentos." : '');
    } else {
        // Éxito: completar el inicio de sesión.
        $uid    = (int)$pend['user_id'];
        $nombre = $pend['nombre'];
        $correo = $pend['correo'];
        $rol    = $pend['rol'];
        $verif  = (int)$pend['verificado'];
        $ultacc = $pend['ultimo_acceso'] ?? null;
        unset($_SESSION['2fa_pending']);

        session_regenerate_id(true);
        $_SESSION['usuario_id']      = $uid;
        $_SESSION['nombre_completo'] = $nombre;
        $_SESSION['correo']          = $correo;
        $_SESSION['rol']             = $rol;
        if ($verif === 0) {
            $_SESSION['email_sin_verificar'] = true;
        }
        // Acceso anterior (para el perfil) + registrar este acceso.
        $_SESSION['ultimo_acceso'] = $ultacc;
        if ($up = $conex->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?")) {
            $up->bind_param('i', $uid);
            $up->execute();
            $up->close();
        }
        header('Location: ' . eco_url('dashboard'));
        exit;
    }
}

$correo_masked = preg_replace_callback('/^(.).*(.@.*)$/u', fn($m) => $m[1] . '•••' . $m[2], (string)$pend['correo']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación en dos pasos · EcoMadelleine</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --azul:#02b1f4; --azul-dark:#014a82; --ink:#0c1a2e; --gris:#4a5870; --err:#b91c1c; --ok:#15803d; }
        * { box-sizing:border-box; margin:0; padding:0; }
        body { font-family:'Inter',system-ui,sans-serif; min-height:100vh; display:flex; align-items:center; justify-content:center;
               background:linear-gradient(180deg,#eaf3ff 0%,#f5f9ff 100%); color:var(--ink); padding:24px; }
        .card { background:rgba(255,255,255,.72); backdrop-filter:blur(28px); border:1px solid rgba(255,255,255,.6);
                border-radius:24px; padding:44px 40px; max-width:430px; width:100%; text-align:center;
                box-shadow:0 30px 80px rgba(12,26,46,.12); }
        .ic { width:66px; height:66px; border-radius:50%; background:rgba(2,177,244,.12); color:var(--azul-dark);
              display:flex; align-items:center; justify-content:center; margin:0 auto 20px; font-size:26px; }
        h1 { font-size:21px; font-weight:700; margin-bottom:8px; letter-spacing:-.02em; }
        p.sub { font-size:14px; color:var(--gris); line-height:1.55; margin-bottom:24px; }
        .otp-input { width:100%; text-align:center; letter-spacing:.5em; font-size:26px; font-weight:700; padding:14px;
                     border:1.5px solid rgba(2,177,244,.4); border-radius:14px; background:#fff; color:var(--ink);
                     font-family:inherit; margin-bottom:16px; }
        .otp-input:focus { outline:none; border-color:var(--azul); box-shadow:0 0 0 4px rgba(2,177,244,.14); }
        .btn { width:100%; padding:15px; border:none; border-radius:14px; cursor:pointer;
               background:linear-gradient(135deg,var(--azul),var(--azul-dark)); color:#fff; font-weight:600; font-size:14.5px;
               font-family:inherit; box-shadow:0 14px 30px rgba(2,177,244,.35); }
        .msg { padding:12px 16px; border-radius:12px; font-size:13.5px; margin-bottom:18px; text-align:left;
               display:flex; gap:10px; align-items:flex-start; }
        .msg-err { background:rgba(239,68,68,.08); color:var(--err); border:1px solid rgba(239,68,68,.25); }
        .msg-ok  { background:rgba(34,197,94,.08); color:var(--ok); border:1px solid rgba(34,197,94,.3); }
        .links { margin-top:20px; font-size:13px; color:var(--gris); display:flex; justify-content:space-between; }
        .links a { color:var(--azul-dark); font-weight:600; text-decoration:none; }
        .links a:hover { color:var(--azul); }
    </style>
</head>
<body>
    <div class="card">
        <div class="ic"><i class="fa-solid fa-shield-halved"></i></div>
        <h1>Verificación en dos pasos</h1>
        <p class="sub">Enviamos un código de 6 dígitos a <strong><?= htmlspecialchars($correo_masked) ?></strong>. Ingrésalo para continuar.</p>

        <?php if ($error): ?>
            <div class="msg msg-err"><i class="fa-solid fa-triangle-exclamation"></i><span><?= htmlspecialchars($error) ?></span></div>
        <?php elseif ($info): ?>
            <div class="msg msg-ok"><i class="fa-solid fa-circle-check"></i><span><?= htmlspecialchars($info) ?></span></div>
        <?php endif; ?>

        <form method="POST" action="<?= eco_url('verificar-2fa') ?>" autocomplete="off">
            <?= csrf_field() ?>
            <input type="text" name="codigo" class="otp-input" inputmode="numeric" maxlength="6" pattern="\d{6}"
                   placeholder="••••••" required autofocus>
            <button type="submit" class="btn"><i class="fa-solid fa-check"></i> Verificar y entrar</button>
        </form>

        <div class="links">
            <a href="verificar_2fa.php?reenviar=1"><i class="fa-solid fa-rotate-right"></i> Reenviar código</a>
            <a href="<?= eco_url('login') ?>"><i class="fa-solid fa-arrow-left"></i> Cancelar</a>
        </div>
    </div>
</body>
</html>
