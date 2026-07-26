<?php
session_start();
include __DIR__ . '/../core/conexion.php';
require_once __DIR__ . '/../lib/comunicaciones/correo_app.php';
require_once __DIR__ . '/../lib/seguridad/seguridad.php';

$error = '';
$mensaje_exito = '';
if (isset($_GET['status'])) {
    if ($_GET['status'] == 'registro_exitoso') {
        $mensaje_exito = '¡Registro exitoso! Ahora puedes iniciar sesión.';
    } elseif ($_GET['status'] == 'verifica_correo') {
        $mensaje_exito = '¡Cuenta creada! Te enviamos un correo para verificar tu cuenta. Revisa tu bandeja de entrada.';
    } elseif ($_GET['status'] == 'password_actualizada') {
        $mensaje_exito = 'Tu contraseña se actualizó correctamente. Ya puedes iniciar sesión.';
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    require_csrf();
    if (empty($_POST['correo']) || empty($_POST['contrasena'])) {
        $error = 'Por favor, completa todos los campos.';
    } else {
        $correo = $_POST['correo'];
        $contrasena = $_POST['contrasena'];

        // ── Throttling persistente: bloquea fuerza bruta por correo + IP ──
        $throttle = eco_login_estado($conex, $correo);
        if ($throttle['bloqueado']) {
            $min = (int)ceil($throttle['espera'] / 60);
            $error = 'Demasiados intentos fallidos. Espera ' . $min . ' minuto(s) antes de volver a intentarlo.';
            eco_auditar($conex, 'login_bloqueado', ['detalle' => ['correo' => $correo]]);
        } else {
        $stmt = $conex->prepare("SELECT id, nombre_completo, correo, contrasena, rol, estado, email_verificado, two_factor_enabled, ultimo_acceso FROM usuarios WHERE correo = ?");
        $stmt->bind_param("s", $correo);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($resultado->num_rows === 1) {
            $usuario = $resultado->fetch_assoc();

            if (password_verify($contrasena, $usuario['contrasena'])) {
                // Credenciales correctas: registra exito y limpia el contador de fallos.
                eco_login_registrar($conex, $correo, true);
                eco_login_limpiar($conex, $correo);
                if ($usuario['estado'] == 'aprobado') {
                    // ── 2FA por correo (opcional, opt-in desde el perfil) — cualquier rol ──
                    if ((int)$usuario['two_factor_enabled'] === 1) {
                        $otp = eco_otp_codigo();
                        $_SESSION['2fa_pending'] = [
                            'user_id'       => (int)$usuario['id'],
                            'nombre'        => $usuario['nombre_completo'],
                            'correo'        => $usuario['correo'],
                            'rol'           => $usuario['rol'],
                            'verificado'    => (int)$usuario['email_verificado'],
                            'ultimo_acceso' => $usuario['ultimo_acceso'],
                            'otp_hash'      => password_hash($otp, PASSWORD_DEFAULT),
                            'expira'        => time() + 600,
                            'intentos'      => 0,
                        ];
                        $cuerpo = "Hola {$usuario['nombre_completo']},\n\n"
                            . "Tu código de verificación en dos pasos es:\n\n    {$otp}\n\n"
                            . "Vence en 10 minutos. Si no intentaste iniciar sesión, cambia tu contraseña.\n\n"
                            . "— EcoMadelleine · Centro de Diagnóstico";
                        eco_enviar_correo((string)$usuario['correo'], 'Tu código de acceso · EcoMadelleine', $cuerpo);
                        eco_auditar($conex, 'login_2fa_enviado', ['usuario_id' => (int)$usuario['id']]);
                        header('Location: ' . eco_url('verificar-2fa'));
                        exit();
                    }

                    // Previene session fixation: nuevo ID de sesión al autenticar.
                    session_regenerate_id(true);
                    $_SESSION['usuario_id'] = $usuario['id'];
                    $_SESSION['nombre_completo'] = $usuario['nombre_completo'];
                    $_SESSION['correo'] = $usuario['correo'];
                    $_SESSION['rol'] = $usuario['rol'];
                    if ((int)$usuario['email_verificado'] === 0) {
                        $_SESSION['email_sin_verificar'] = true;
                    }
                    // Acceso anterior (para el perfil) + registrar este acceso.
                    $_SESSION['ultimo_acceso'] = $usuario['ultimo_acceso'];
                    if ($up = $conex->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?")) {
                        $up->bind_param('i', $usuario['id']);
                        $up->execute();
                        $up->close();
                    }
                    eco_auditar($conex, 'login_exito', ['usuario_id' => (int)$usuario['id']]);
                    header('Location: ' . eco_url('dashboard'));
                    exit();
                } elseif ($usuario['estado'] == 'pendiente') {
                    $error = 'Tu cuenta aún está pendiente de aprobación.';
                } elseif ($usuario['estado'] == 'inhabilitado') {
                    $error = 'Tu cuenta ha sido inhabilitada. Contacta al administrador.';
                } else {
                    $error = 'Tu cuenta ha sido rechazada o desactivada.';
                }
            } else {
                eco_login_registrar($conex, $correo, false);
                eco_auditar($conex, 'login_fallido', ['detalle' => ['correo' => $correo]]);
                $error = 'Correo o contraseña incorrectos.';
            }
        } else {
            eco_login_registrar($conex, $correo, false);
            eco_auditar($conex, 'login_fallido', ['detalle' => ['correo' => $correo]]);
            $error = 'Correo o contraseña incorrectos.';
        }
        $stmt->close();
        }
    }
}

/* Métrica real para el brand panel */
$r = $conex->query("SELECT COUNT(*) c FROM usuarios WHERE rol='paciente' AND estado='aprobado'");
$total_pacientes = (int)($r->fetch_assoc()['c'] ?? 0);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#eaf3ff">
    <title>Iniciar sesión · EcoMadelleine</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
    /* ════════════════════════════════════════════════════════════════
       DESIGN TOKENS — sincronizados con el landing
       ════════════════════════════════════════════════════════════════ */
    :root {
        --sky-1: #eaf3ff;
        --sky-2: #f5f9ff;
        --white: #ffffff;
        --ink: #0c1a2e;
        --ink-2: #1e2a44;
        --gris: #4a5870;
        --gris-soft: #6b7689;
        --gris-mute: #94a3b8;
        --azul: #02b1f4;
        --azul-dark: #014a82;
        --azul-deep: #003a66;
        --azul-soft: #e0f5fe;
        --silver-top: rgba(255, 255, 255, .85);
        --silver-bot: rgba(12, 26, 46, .06);
        --silver-edge: rgba(255, 255, 255, .55);
        --glass: rgba(255, 255, 255, .55);
        --glass-strong: rgba(255, 255, 255, .72);
        --sh-soft: 0 1px 2px rgba(12, 26, 46, .04), 0 8px 24px rgba(12, 26, 46, .06);
        --sh-deep: 0 30px 80px rgba(12, 26, 46, .15);
        --sh-glow: 0 14px 30px rgba(2, 177, 244, .35);
        --r: 18px;
        --r-lg: 24px;
        --r-xl: 32px;
        --ease: cubic-bezier(.22, 1, .36, 1);
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html, body { overflow-x: hidden; }
    body {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        background: var(--sky-2);
        color: var(--ink);
        min-height: 100vh;
        font-size: 15px;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
        position: relative;
    }
    ::selection { background: var(--azul); color: #fff; }

    /* Fondo ambiental — idéntico al landing */
    body::before {
        content: '';
        position: fixed;
        inset: 0;
        z-index: -2;
        background:
            radial-gradient(ellipse 80% 60% at 85% 0%, rgba(2, 177, 244, .18) 0%, transparent 55%),
            radial-gradient(ellipse 60% 50% at 0% 30%, rgba(99, 179, 237, .14) 0%, transparent 55%),
            radial-gradient(ellipse 70% 50% at 50% 100%, rgba(167, 139, 250, .10) 0%, transparent 55%),
            linear-gradient(180deg, var(--sky-1) 0%, var(--sky-2) 100%);
    }
    body::after {
        content: '';
        position: fixed;
        inset: 0;
        z-index: -1;
        pointer-events: none;
        background-image: radial-gradient(circle at 1px 1px, rgba(12, 26, 46, .035) 1px, transparent 0);
        background-size: 28px 28px;
    }

    a { color: inherit; text-decoration: none; }
    button { font-family: inherit; }

    /* ════════════════════════════════════════════════════════════════
       TOP BAR (logo + back link)
       ════════════════════════════════════════════════════════════════ */
    .top-nav {
        position: fixed;
        top: 24px; left: 0; right: 0;
        z-index: 100;
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0 36px;
    }
    .brand-mark {
        display: inline-flex;
        align-items: center;
        gap: 12px;
        font-size: 17px;
        font-weight: 700;
        color: var(--ink);
        letter-spacing: -0.015em;
    }
    .brand-mark .ic {
        width: 36px; height: 36px;
        border-radius: 12px;
        background: linear-gradient(135deg, var(--azul) 0%, var(--azul-dark) 100%);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: 13px;
        box-shadow: inset 0 1px 0 rgba(255,255,255,.4), 0 6px 16px rgba(2,177,244,.35);
    }
    .brand-mark .txt { display: flex; flex-direction: column; line-height: 1; }
    .brand-mark small {
        font-size: 8.5px;
        font-weight: 500;
        color: var(--gris-mute);
        text-transform: uppercase;
        letter-spacing: 1.8px;
        margin-top: 3px;
    }
    .back-link {
        font-size: 13px;
        color: var(--gris);
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 9px;
        padding: 9px 16px 9px 14px;
        background: var(--glass-strong);
        backdrop-filter: blur(18px) saturate(1.6);
        -webkit-backdrop-filter: blur(18px) saturate(1.6);
        border: 1px solid var(--silver-edge);
        border-radius: 999px;
        box-shadow: inset 0 1px 0 var(--silver-top);
        transition: background .25s, transform .35s var(--ease), padding-left .35s var(--ease);
    }
    .back-link:hover {
        background: #fff;
        transform: translateX(-2px);
        padding-left: 18px;
    }
    .back-link i { font-size: 11px; }

    /* ════════════════════════════════════════════════════════════════
       LAYOUT
       ════════════════════════════════════════════════════════════════ */
    .auth-wrap {
        min-height: 100vh;
        display: grid;
        grid-template-columns: 1fr 1.05fr;
        /* stretch (no "center"): con center cada panel toma solo su propia
           altura de contenido, asi que al aparecer el aviso de error la
           tarjeta derecha crecia sola y quedaba descuadrada frente a la
           izquierda (fija en min-height:560px). Con stretch ambas igualan
           siempre a la mas alta de las dos. */
        align-items: stretch;
        gap: 24px;
        max-width: 1240px;
        margin: 0 auto;
        padding: 110px 36px 50px;
    }

    /* ── Brand panel (izq) ────────────────────────────────────────── */
    .brand-panel {
        background: linear-gradient(160deg, var(--ink) 0%, var(--azul-deep) 100%);
        color: #fff;
        border-radius: var(--r-xl);
        padding: 56px 48px;
        position: relative;
        overflow: hidden;
        min-height: 560px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        box-shadow: var(--sh-deep);
    }
    .brand-panel::before {
        content: '';
        position: absolute;
        top: -100px; right: -100px;
        width: 320px; height: 320px;
        background: radial-gradient(circle, rgba(2, 177, 244, .4), transparent 60%);
        filter: blur(20px);
        animation: glowFloat 10s ease-in-out infinite alternate;
    }
    .brand-panel::after {
        content: '';
        position: absolute;
        bottom: -120px; left: -120px;
        width: 280px; height: 280px;
        background: radial-gradient(circle, rgba(139, 92, 246, .25), transparent 65%);
        filter: blur(30px);
        animation: glowFloat 12s ease-in-out infinite alternate-reverse;
    }
    @keyframes glowFloat {
        from { transform: translate(0, 0) scale(1); }
        to   { transform: translate(-3%, 3%) scale(1.08); }
    }

    .brand-eyebrow {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 2px;
        color: rgba(255,255,255,.6);
        font-weight: 600;
        margin-bottom: 22px;
        position: relative;
        display: inline-flex;
        align-items: center;
        gap: 12px;
    }
    .brand-eyebrow::before {
        content: '';
        width: 26px; height: 1px;
        background: rgba(255,255,255,.5);
    }
    .brand-panel h1 {
        font-size: clamp(30px, 3.4vw, 44px);
        font-weight: 700;
        line-height: 1.08;
        letter-spacing: -0.025em;
        margin-bottom: 18px;
        position: relative;
        color: #fff;
    }
    .brand-panel h1 .grad {
        background: linear-gradient(120deg, var(--azul) 0%, #7dd3fc 100%);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
        font-style: italic;
        font-weight: 600;
    }
    .brand-panel .lead {
        font-size: 15px;
        color: rgba(255,255,255,.72);
        line-height: 1.65;
        max-width: 380px;
        position: relative;
    }

    .brand-stats {
        display: flex;
        gap: 36px;
        position: relative;
        padding-top: 28px;
        border-top: 1px solid rgba(255,255,255,.1);
    }
    .brand-stat .num {
        font-size: 30px;
        font-weight: 800;
        color: #fff;
        line-height: 1;
        letter-spacing: -0.03em;
        margin-bottom: 6px;
    }
    .brand-stat .num .grad {
        background: linear-gradient(120deg, var(--azul) 0%, #7dd3fc 100%);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
    }
    .brand-stat .num sub {
        font-size: 14px;
        font-weight: 600;
        background: linear-gradient(120deg, var(--azul) 0%, #7dd3fc 100%);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
        margin-left: 2px;
        vertical-align: 4px;
    }
    .brand-stat .lbl {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 1.4px;
        color: rgba(255,255,255,.55);
        font-weight: 600;
    }

    /* ── Form card (der) ──────────────────────────────────────────── */
    .form-card {
        background: var(--glass-strong);
        backdrop-filter: blur(28px) saturate(1.8);
        -webkit-backdrop-filter: blur(28px) saturate(1.8);
        border: 1px solid var(--silver-edge);
        border-radius: var(--r-xl);
        padding: 56px 52px;
        min-height: 560px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        box-shadow:
            inset 0 1px 0 var(--silver-top),
            inset 0 -1px 0 var(--silver-bot),
            var(--sh-soft);
    }
    .form-card .eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1.6px;
        color: var(--azul-dark);
        background: var(--glass);
        padding: 7px 14px;
        border-radius: 999px;
        border: 1px solid var(--silver-edge);
        width: max-content;
        margin-bottom: 16px;
        box-shadow: inset 0 1px 0 var(--silver-top);
    }
    .form-card .eyebrow i { font-size: 9px; color: var(--azul); }
    .form-card h2 {
        font-size: clamp(26px, 2.4vw, 32px);
        font-weight: 700;
        letter-spacing: -0.025em;
        line-height: 1.15;
        color: var(--ink);
        margin-bottom: 10px;
    }
    .form-card h2 .grad {
        background: linear-gradient(120deg, var(--azul) 0%, var(--azul-deep) 100%);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
    }
    .form-card .sub {
        font-size: 14.5px;
        color: var(--gris-soft);
        margin-bottom: 32px;
        line-height: 1.55;
    }

    /* Inputs */
    .input-group { display: flex; flex-direction: column; gap: 14px; }
    .input-field { position: relative; display: flex; align-items: center; }
    .input-field > i {
        position: absolute;
        left: 16px;
        color: var(--gris-mute);
        font-size: 14px;
        pointer-events: none;
        z-index: 1;
        transition: color .25s;
    }
    .input-field input {
        width: 100%;
        padding: 15px 16px 15px 46px;
        border: 1px solid var(--silver-edge);
        border-radius: 14px;
        font-size: 14.5px;
        background: rgba(255,255,255,.6);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        color: var(--ink);
        font-family: inherit;
        transition: border-color .25s, background .25s, box-shadow .3s var(--ease);
    }
    .input-field input::placeholder { color: var(--gris-mute); }
    .input-field input:focus {
        outline: none;
        border-color: var(--azul);
        background: #fff;
        box-shadow: 0 0 0 4px rgba(2, 177, 244, .14);
    }
    .input-field:focus-within > i { color: var(--azul-dark); }
    .input-field.has-toggle input { padding-right: 46px; }

    .pw-toggle {
        position: absolute;
        right: 8px;
        top: 50%;
        transform: translateY(-50%);
        background: transparent;
        border: none;
        width: 36px; height: 36px;
        border-radius: 50%;
        color: var(--gris-mute);
        cursor: pointer;
        font-size: 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        z-index: 2;
        transition: color .2s, background .2s;
    }
    .pw-toggle:hover { color: var(--ink); background: rgba(2, 177, 244, .08); }

    /* Aux row */
    .form-aux {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 4px;
        font-size: 13px;
    }
    .remember {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        color: var(--gris);
        cursor: pointer;
        user-select: none;
        font-weight: 500;
    }
    .remember input[type="checkbox"] {
        width: 16px; height: 16px;
        accent-color: var(--azul);
        cursor: pointer;
    }
    .forgot {
        color: var(--gris);
        font-weight: 500;
        transition: color .2s;
    }
    .forgot:hover { color: var(--azul-dark); }

    /* Submit */
    .btn-submit {
        width: 100%;
        padding: 16px;
        border: none;
        background: linear-gradient(135deg, var(--azul) 0%, var(--azul-dark) 100%);
        color: #fff;
        font-size: 14.5px;
        font-weight: 600;
        border-radius: 14px;
        cursor: pointer;
        margin-top: 16px;
        box-shadow:
            inset 0 1px 0 rgba(255,255,255,.3),
            var(--sh-glow);
        transition: transform .25s var(--ease), box-shadow .3s;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
    }
    .btn-submit:hover {
        transform: translateY(-2px);
        box-shadow:
            inset 0 1px 0 rgba(255,255,255,.3),
            0 22px 44px rgba(2, 177, 244, .45);
    }
    .btn-submit i { transition: transform .3s var(--ease); }
    .btn-submit:hover i { transform: translateX(3px); }

    /* Mensajes */
    .msg {
        padding: 14px 18px;
        border-radius: 14px;
        font-size: 13.5px;
        font-weight: 500;
        margin-bottom: 22px;
        display: flex;
        align-items: flex-start;
        gap: 12px;
        border: 1px solid;
        line-height: 1.45;
    }
    .msg i { font-size: 14px; margin-top: 1px; flex-shrink: 0; }
    .msg-err {
        background: rgba(239, 68, 68, .08);
        color: #991b1b;
        border-color: rgba(239, 68, 68, .25);
    }
    .msg-ok {
        background: rgba(34, 197, 94, .08);
        color: #15803d;
        border-color: rgba(34, 197, 94, .3);
    }

    /* Footer */
    .form-footer {
        margin-top: 32px;
        text-align: center;
        font-size: 13.5px;
        color: var(--gris-soft);
    }
    .form-footer a {
        color: var(--azul-dark);
        font-weight: 600;
        transition: color .2s;
    }
    .form-footer a:hover { color: var(--azul); }

    /* ════════════════════════════════════════════════════════════════
       RESPONSIVE
       ════════════════════════════════════════════════════════════════ */
    @media (max-width: 1080px) {
        .auth-wrap { gap: 18px; padding: 110px 28px 40px; }
        .brand-panel, .form-card { padding: 44px 36px; min-height: 520px; }
    }
    @media (max-width: 920px) {
        .auth-wrap {
            grid-template-columns: 1fr;
            padding: 100px 24px 40px;
        }
        .brand-panel { min-height: auto; padding: 40px 32px; }
        .brand-panel h1 { font-size: 28px; }
        .form-card { min-height: auto; padding: 40px 32px; }
        .top-nav { padding: 0 24px; }
    }
    @media (max-width: 560px) {
        :root { font-size: 14px; }
        .auth-wrap { padding: 90px 16px 30px; }
        .brand-panel, .form-card { padding: 32px 24px; border-radius: 24px; }
        .form-card h2 { font-size: 24px; }
        .brand-stats { gap: 22px; }
        .brand-stat .num { font-size: 24px; }
        .brand-mark .txt small { display: none; }
        .top-nav { top: 16px; padding: 0 16px; }
        .back-link span { display: none; }
        .back-link { padding: 9px 12px; }
        .form-aux { flex-wrap: wrap; gap: 10px; }
    }
    </style>
</head>
<body>

<nav class="top-nav">
    <a href="index.php" class="brand-mark">
        <span class="ic"><i class="fa-solid fa-wave-square"></i></span>
        <span class="txt">EcoMadelleine<small>Centro de Diagnóstico</small></span>
    </a>
    <a href="index.php" class="back-link">
        <i class="fa-solid fa-arrow-left"></i> <span>Volver al inicio</span>
    </a>
</nav>

<main class="auth-wrap">
    <!-- Brand panel -->
    <aside class="brand-panel">
        <div>
            <span class="brand-eyebrow">Acceso seguro</span>
            <h1>Bienvenido<br><span class="grad">de vuelta.</span></h1>
            <p class="lead">Accede a tu panel personal para gestionar citas, revisar informes ecográficos y agendar nuevos estudios con la Dra. Madelleine Toro.</p>
        </div>
        <div class="brand-stats">
            <div class="brand-stat">
                <div class="num"><span class="grad"><?php echo $total_pacientes > 0 ? number_format($total_pacientes, 0, ',', '.') . '+' : '—'; ?></span></div>
                <div class="lbl">Pacientes</div>
            </div>
            <div class="brand-stat">
                <div class="num"><span class="grad">24<sub>h</sub></span></div>
                <div class="lbl">Informe</div>
            </div>
            <div class="brand-stat">
                <div class="num"><span class="grad">100%</span></div>
                <div class="lbl">Confidencial</div>
            </div>
        </div>
    </aside>

    <!-- Form card -->
    <section class="form-card">
        <span class="eyebrow"><i class="fa-solid fa-right-to-bracket"></i> Iniciar sesión</span>
        <h2>Ingresa a tu <span class="grad">cuenta clínica</span></h2>
        <p class="sub">Introduce tus credenciales para continuar con tu historial médico.</p>

        <?php if ($error): ?>
            <div class="msg msg-err">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($mensaje_exito): ?>
            <div class="msg msg-ok">
                <i class="fa-solid fa-circle-check"></i>
                <span><?php echo htmlspecialchars($mensaje_exito); ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?= eco_url('login') ?>" class="input-group" autocomplete="off">
            <?= csrf_field() ?>
            <div class="input-field">
                <i class="fa-regular fa-envelope"></i>
                <input type="email" name="correo" placeholder="Correo electrónico" required autofocus>
            </div>
            <div class="input-field has-toggle">
                <i class="fa-solid fa-lock"></i>
                <input type="password" name="contrasena" id="contrasena" placeholder="Contraseña" required>
                <button type="button" class="pw-toggle" id="pw-toggle" aria-label="Mostrar contraseña">
                    <i class="fa-regular fa-eye"></i>
                </button>
            </div>

            <div class="form-aux">
                <label class="remember">
                    <input type="checkbox" name="recordar"> Recordarme
                </label>
                <a href="<?= eco_url('recuperar') ?>" class="forgot">¿Olvidaste tu contraseña?</a>
            </div>

            <button type="submit" class="btn-submit">
                Iniciar sesión <i class="fa-solid fa-arrow-right"></i>
            </button>
        </form>

        <div class="form-footer">
            ¿Aún no tienes una cuenta? <a href="<?= eco_url('registro') ?>">Regístrate aquí</a>
            <div style="margin-top:8px;"><a href="<?= eco_url('privacidad') ?>">Aviso de privacidad</a></div>
        </div>
    </section>
</main>

<script>
(function(){
    const t = document.getElementById('pw-toggle');
    const i = document.getElementById('contrasena');
    if (!t || !i) return;
    t.addEventListener('click', () => {
        const isPw = i.type === 'password';
        i.type = isPw ? 'text' : 'password';
        t.querySelector('i').className = isPw ? 'fa-regular fa-eye-slash' : 'fa-regular fa-eye';
        t.setAttribute('aria-label', isPw ? 'Ocultar contraseña' : 'Mostrar contraseña');
    });
})();
</script>

</body>
</html>
