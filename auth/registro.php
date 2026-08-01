<?php
if (session_status() === PHP_SESSION_NONE) session_start(); // necesaria para el token CSRF antes de imprimir HTML
include __DIR__ . '/../core/conexion.php';
require_once __DIR__ . '/../lib/comunicaciones/correo_app.php';
$mensaje = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    require_csrf();
    $nombre_completo = trim($_POST['nombre_completo']);
    $fecha_nacimiento = $_POST['fecha_nacimiento'];
    $cedula_tipo = $_POST['cedula_tipo'];
    $cedula_numero = trim($_POST['cedula_numero']);
    $cedula = $cedula_tipo . $cedula_numero;
    $direccion = trim($_POST['direccion'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $correo = trim($_POST['correo']);
    $contrasena = $_POST['contrasena'];
    $confirmar_contrasena = $_POST['confirmar_contrasena'];
    $rol = 'paciente';
    $estado = 'aprobado';

    $fecha_nac = new DateTime($fecha_nacimiento);
    $hoy = new DateTime();
    $edad = $hoy->diff($fecha_nac)->y;

    if (strlen($cedula_numero) < 7 || strlen($cedula_numero) > 8) {
        $mensaje = "El número de documento debe tener entre 7 y 8 dígitos.";
    } elseif ($contrasena !== $confirmar_contrasena) {
        $mensaje = "Las contraseñas no coinciden.";
    } elseif (strlen($contrasena) < 8) {
        $mensaje = "La contraseña debe tener al menos 8 caracteres.";
    } elseif (!preg_match('/[A-Z]/', $contrasena)) {
        $mensaje = "La contraseña debe contener al menos una letra mayúscula.";
    } elseif (!preg_match('/[\W_]/', $contrasena)) {
        $mensaje = "La contraseña debe contener al menos un carácter especial (ej: !@#$%).";
    } else {
        $check_stmt = $conex->prepare("SELECT id FROM usuarios WHERE correo = ? OR cedula = ?");
        $check_stmt->bind_param("ss", $correo, $cedula);
        $check_stmt->execute();
        $result = $check_stmt->get_result();

        if ($result->num_rows > 0) {
            $mensaje = "El correo electrónico o la cédula ya están registrados.";
        } else {
            $contrasena_hasheada = password_hash($contrasena, PASSWORD_DEFAULT);
            $email_verificado = 0;
            $token_verif  = eco_token();
            // En la BD solo el sha256; el token en claro viaja unicamente en el
            // correo (mismo criterio que recuperacion y descarga_tokens).
            $token_verif_hash = hash('sha256', $token_verif);
            $token_expira = date('Y-m-d H:i:s', strtotime('+24 hours'));

            $insert_stmt = $conex->prepare("INSERT INTO usuarios (nombre_completo, fecha_nacimiento, cedula, direccion, telefono, correo, contrasena, rol, estado, email_verificado, token_verificacion, token_verificacion_expira) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $insert_stmt->bind_param("sssssssssiss", $nombre_completo, $fecha_nacimiento, $cedula, $direccion, $telefono, $correo, $contrasena_hasheada, $rol, $estado, $email_verificado, $token_verif_hash, $token_expira);

            if ($insert_stmt->execute()) {
                // Correo de verificación (no bloquea el registro si el envío falla)
                $link = eco_base_url() . '/publico/verificar_correo.php?token=' . urlencode($token_verif);
                $cuerpo = "Hola {$nombre_completo},\n\n"
                    . "Gracias por registrarte en EcoMadelleine. Para verificar tu correo y activar todas las funciones de tu cuenta, abre este enlace:\n\n"
                    . $link . "\n\n"
                    . "El enlace vence en 24 horas. Si no creaste esta cuenta, ignora este mensaje.\n\n"
                    . "— EcoMadelleine · Centro de Diagnóstico";
                eco_enviar_correo($correo, 'Verifica tu correo · EcoMadelleine', $cuerpo);
                header('Location: ' . eco_url('login') . '?status=verifica_correo');
                exit();
            } else {
                $mensaje = "Error al registrar el usuario.";
            }
            $insert_stmt->close();
        }
        $check_stmt->close();
    }
}

/* Métricas reales para el brand panel */
$r = $conex->query("SELECT COUNT(*) c FROM tipos_ecografias WHERE activo=1 AND (categoria IS NULL OR categoria NOT IN ('Musculoesqueletica_Sub','Obstetrica_Sub','Partes_Blandas_Sub'))");
$total_tipos = (int)($r->fetch_assoc()['c'] ?? 0);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#eaf3ff">
    <title>Crear cuenta · EcoMadelleine</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <style>
    /* ════════════════════════════════════════════════════════════════
       DESIGN TOKENS — sincronizados con landing + login
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
       TOP BAR
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
        grid-template-columns: 1fr 1.15fr;
        align-items: stretch;
        gap: 24px;
        max-width: 1280px;
        margin: 0 auto;
        padding: 110px 36px 50px;
    }

    /* ── Brand panel ──────────────────────────────────────────────── */
    .brand-panel {
        background: linear-gradient(160deg, var(--ink) 0%, var(--azul-deep) 100%);
        color: #fff;
        border-radius: var(--r-xl);
        padding: 52px 44px;
        position: relative;
        overflow: hidden;
        min-height: 640px;
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
        margin-bottom: 20px;
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
        font-size: clamp(28px, 3.2vw, 40px);
        font-weight: 700;
        line-height: 1.1;
        letter-spacing: -0.025em;
        margin-bottom: 16px;
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
        font-size: 14.5px;
        color: rgba(255,255,255,.72);
        line-height: 1.65;
        max-width: 380px;
        position: relative;
        margin-bottom: 36px;
    }

    /* Lista de beneficios */
    .benefit-list {
        list-style: none;
        position: relative;
        margin-top: 8px;
    }
    .benefit-list li {
        display: flex;
        align-items: flex-start;
        gap: 14px;
        padding: 14px 0;
        border-top: 1px solid rgba(255,255,255,.08);
        font-size: 14px;
        color: rgba(255,255,255,.85);
        line-height: 1.5;
    }
    .benefit-list li:first-child { border-top: none; padding-top: 0; }
    .benefit-list li .ico {
        width: 32px; height: 32px;
        background: rgba(255,255,255,.08);
        border: 1px solid rgba(255,255,255,.12);
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        color: var(--azul);
        flex-shrink: 0;
    }
    .benefit-list li strong { color: #fff; font-weight: 600; display: block; margin-bottom: 2px; font-size: 13.5px; }
    .benefit-list li span { font-size: 12.5px; color: rgba(255,255,255,.55); }

    .brand-stats {
        display: flex;
        gap: 28px;
        position: relative;
        padding-top: 26px;
        border-top: 1px solid rgba(255,255,255,.1);
    }
    .brand-stat .num {
        font-size: 28px;
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
    .brand-stat .lbl {
        font-size: 10.5px;
        text-transform: uppercase;
        letter-spacing: 1.4px;
        color: rgba(255,255,255,.55);
        font-weight: 600;
    }

    /* ── Form card ────────────────────────────────────────────────── */
    .form-card {
        background: var(--glass-strong);
        backdrop-filter: blur(28px) saturate(1.8);
        -webkit-backdrop-filter: blur(28px) saturate(1.8);
        border: 1px solid var(--silver-edge);
        border-radius: var(--r-xl);
        padding: 48px 44px;
        min-height: 640px;
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
        margin-bottom: 14px;
        box-shadow: inset 0 1px 0 var(--silver-top);
    }
    .form-card .eyebrow i { font-size: 9px; color: var(--azul); }
    .form-card h2 {
        font-size: clamp(24px, 2.2vw, 30px);
        font-weight: 700;
        letter-spacing: -0.025em;
        line-height: 1.15;
        color: var(--ink);
        margin-bottom: 8px;
    }
    .form-card h2 .grad {
        background: linear-gradient(120deg, var(--azul) 0%, var(--azul-deep) 100%);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
    }
    .form-card .sub {
        font-size: 14px;
        color: var(--gris-soft);
        margin-bottom: 28px;
        line-height: 1.55;
    }

    /* Inputs */
    .input-group { display: flex; flex-direction: column; gap: 12px; }
    .input-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
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
        padding: 14px 16px 14px 46px;
        border: 1px solid var(--silver-edge);
        border-radius: 14px;
        font-size: 14px;
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

    /* Compound cédula */
    .cedula-group {
        display: flex;
        align-items: stretch;
    }
    .cedula-select {
        background: rgba(255,255,255,.6);
        backdrop-filter: blur(10px);
        border: 1px solid var(--silver-edge);
        border-right: none;
        border-radius: 14px 0 0 14px;
        padding: 14px 14px;
        font-weight: 700;
        color: var(--ink);
        cursor: pointer;
        font-family: inherit;
        font-size: 14px;
        outline: none;
        text-align: center;
        appearance: none;
        -webkit-appearance: none;
        -moz-appearance: none;
        min-width: 64px;
        transition: border-color .25s, background .25s;
    }
    .cedula-input-wrap {
        position: relative;
        flex: 1;
        display: flex;
        align-items: center;
    }
    .cedula-input-wrap > i {
        position: absolute;
        left: 14px;
        color: var(--gris-mute);
        font-size: 13px;
        pointer-events: none;
    }
    .cedula-input-wrap input {
        width: 100%;
        padding: 14px 16px 14px 38px;
        border: 1px solid var(--silver-edge);
        border-radius: 0 14px 14px 0;
        font-size: 14px;
        background: rgba(255,255,255,.6);
        backdrop-filter: blur(10px);
        color: var(--ink);
        font-family: inherit;
        transition: border-color .25s, background .25s, box-shadow .3s var(--ease);
    }
    .cedula-input-wrap input::placeholder { color: var(--gris-mute); }
    .cedula-group:focus-within .cedula-select { border-color: var(--azul); background: #fff; }
    .cedula-group:focus-within .cedula-input-wrap input {
        border-color: var(--azul);
        background: #fff;
        box-shadow: 0 0 0 4px rgba(2, 177, 244, .14);
    }
    .cedula-group:focus-within .cedula-input-wrap > i { color: var(--azul-dark); }

    .pw-toggle {
        position: absolute;
        right: 8px;
        top: 50%;
        transform: translateY(-50%);
        background: transparent;
        border: none;
        width: 34px; height: 34px;
        border-radius: 50%;
        color: var(--gris-mute);
        cursor: pointer;
        font-size: 13px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        z-index: 2;
        transition: color .2s, background .2s;
    }
    .pw-toggle:hover { color: var(--ink); background: rgba(2, 177, 244, .08); }

    /* Password requirements hint */
    .pw-hint {
        font-size: 11.5px;
        color: var(--gris-mute);
        padding: 6px 4px 2px;
        line-height: 1.5;
        display: flex;
        align-items: flex-start;
        gap: 6px;
    }
    .pw-hint i { font-size: 10px; margin-top: 2px; color: var(--azul-dark); }

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
        margin-top: 14px;
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

    /* Footer */
    .form-footer {
        margin-top: 24px;
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
    .form-legal {
        font-size: 11.5px;
        color: var(--gris-mute);
        text-align: center;
        margin-top: 14px;
        line-height: 1.5;
    }

    /* Flatpickr ajustes */
    .flatpickr-day { height: 38px !important; line-height: 38px !important; }

    /* ════════════════════════════════════════════════════════════════
       RESPONSIVE
       ════════════════════════════════════════════════════════════════ */
    @media (max-width: 1080px) {
        .auth-wrap { gap: 18px; padding: 110px 28px 40px; }
        .brand-panel, .form-card { padding: 40px 32px; min-height: 600px; }
    }
    @media (max-width: 920px) {
        .auth-wrap {
            grid-template-columns: 1fr;
            padding: 100px 24px 40px;
        }
        .brand-panel { min-height: auto; padding: 36px 28px; }
        .brand-panel h1 { font-size: 26px; }
        .brand-panel .lead { margin-bottom: 28px; }
        .form-card { min-height: auto; padding: 36px 28px; }
        .top-nav { padding: 0 24px; }
        .input-row { grid-template-columns: 1fr; gap: 12px; }
    }
    @media (max-width: 560px) {
        :root { font-size: 14px; }
        .auth-wrap { padding: 90px 16px 30px; }
        .brand-panel, .form-card { padding: 30px 22px; border-radius: 24px; }
        .form-card h2 { font-size: 22px; }
        .brand-stats { gap: 18px; }
        .brand-stat .num { font-size: 22px; }
        .brand-mark .txt small { display: none; }
        .top-nav { top: 16px; padding: 0 16px; }
        .back-link span { display: none; }
        .back-link { padding: 9px 12px; }
    }
    </style>
</head>
<body>

<nav class="top-nav">
    <a href="index.php" class="brand-mark">
        <span class="ic"><i class="fa-solid fa-wave-square"></i></span>
        <span class="txt">EcoMadelleine<small>Centro de Diagnóstico</small></span>
    </a>
    <a href="<?= eco_url('login') ?>" class="back-link">
        <i class="fa-solid fa-arrow-left"></i> <span>Iniciar sesión</span>
    </a>
</nav>

<main class="auth-wrap">
    <!-- Brand panel -->
    <aside class="brand-panel">
        <div>
            <span class="brand-eyebrow">Registro de paciente</span>
            <h1>Crea tu<br><span class="grad">cuenta clínica.</span></h1>
            <p class="lead">Tu historial ecográfico en un solo lugar. Agenda estudios, descarga informes y mantén toda tu información médica segura.</p>

            <ul class="benefit-list">
                <li>
                    <span class="ico"><i class="fa-solid fa-calendar-check"></i></span>
                    <div>
                        <strong>Agendas en línea 24/7</strong>
                        <span>Reserva tu cita ecográfica cuando quieras.</span>
                    </div>
                </li>
                <li>
                    <span class="ico"><i class="fa-solid fa-file-waveform"></i></span>
                    <div>
                        <strong>Informes digitales en 24h</strong>
                        <span>Recibe el PDF profesional listo para tu médico.</span>
                    </div>
                </li>
                <li>
                    <span class="ico"><i class="fa-solid fa-shield-halved"></i></span>
                    <div>
                        <strong>Datos confidenciales</strong>
                        <span>Tu historial protegido con cifrado y acceso privado.</span>
                    </div>
                </li>
            </ul>
        </div>

        <div class="brand-stats">
            <div class="brand-stat">
                <div class="num"><span class="grad"><?php echo $total_tipos > 0 ? $total_tipos . '+' : '—'; ?></span></div>
                <div class="lbl">Estudios disponibles</div>
            </div>
            <div class="brand-stat">
                <div class="num"><span class="grad">100%</span></div>
                <div class="lbl">Sin papeles</div>
            </div>
        </div>
    </aside>

    <!-- Form card -->
    <section class="form-card">
        <span class="eyebrow"><i class="fa-solid fa-user-plus"></i> Crear cuenta</span>
        <h2>Regístrate como <span class="grad">paciente</span></h2>
        <p class="sub">Completa tus datos para acceder al sistema y agendar tu primera ecografía.</p>

        <?php if ($mensaje): ?>
            <div class="msg msg-err">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span><?php echo htmlspecialchars($mensaje); ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?= eco_url('registro') ?>" class="input-group" autocomplete="off">
            <?= csrf_field() ?>
            <div class="input-field">
                <i class="fa-solid fa-user"></i>
                <input type="text" name="nombre_completo" placeholder="Nombre y apellido" required autofocus>
            </div>

            <div class="input-row">
                <div class="input-field">
                    <i class="fa-solid fa-calendar-day"></i>
                    <input type="text" name="fecha_nacimiento" id="fecha_nacimiento" placeholder="Fecha de nacimiento" required>
                </div>
                <div class="cedula-group">
                    <select name="cedula_tipo" class="cedula-select" required>
                        <option value="V-">V</option>
                        <option value="E-">E</option>
                        <option value="P-">P</option>
                    </select>
                    <div class="cedula-input-wrap">
                        <i class="fa-solid fa-id-card"></i>
                        <input type="text" name="cedula_numero" placeholder="Documento" required minlength="7" maxlength="8" pattern="\d{7,8}" title="Entre 7 y 8 dígitos" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                    </div>
                </div>
            </div>

            <div class="input-field">
                <i class="fa-regular fa-envelope"></i>
                <input type="email" name="correo" placeholder="Correo electrónico" required autocomplete="email">
            </div>

            <div class="input-field">
                <i class="fa-solid fa-location-dot"></i>
                <input type="text" name="direccion" placeholder="Dirección física (estado, sector)" required maxlength="255" autocomplete="street-address">
            </div>

            <div class="input-field">
                <i class="fa-solid fa-phone"></i>
                <input type="tel" name="telefono" placeholder="Teléfono" required maxlength="30" autocomplete="tel">
            </div>

            <div class="input-field has-toggle">
                <i class="fa-solid fa-lock"></i>
                <input type="password" name="contrasena" id="contrasena" placeholder="Contraseña" required
                       minlength="8" pattern="(?=.*[A-Z])(?=.*[\W_]).{8,}"
                       title="Mínimo 8 caracteres, una mayúscula y un símbolo.">
                <button type="button" class="pw-toggle" data-target="contrasena" aria-label="Mostrar contraseña">
                    <i class="fa-regular fa-eye"></i>
                </button>
            </div>

            <div class="input-field has-toggle">
                <i class="fa-solid fa-lock-open"></i>
                <input type="password" name="confirmar_contrasena" id="confirmar_contrasena" placeholder="Confirmar contraseña" required>
                <button type="button" class="pw-toggle" data-target="confirmar_contrasena" aria-label="Mostrar contraseña">
                    <i class="fa-regular fa-eye"></i>
                </button>
            </div>

            <div class="pw-hint">
                <i class="fa-solid fa-circle-info"></i>
                <span>Mínimo 8 caracteres con al menos una mayúscula y un símbolo (ej: !@#$%).</span>
            </div>

            <button type="submit" class="btn-submit">
                Crear mi cuenta <i class="fa-solid fa-arrow-right"></i>
            </button>

            <p class="form-legal">Al registrarte aceptas el tratamiento confidencial de tus datos clínicos.</p>
        </form>

        <div class="form-footer">
            ¿Ya tienes una cuenta? <a href="<?= eco_url('login') ?>">Inicia sesión aquí</a>
            <div style="margin-top:8px;">Al registrarte aceptas nuestro <a href="<?= eco_url('privacidad') ?>">Aviso de privacidad</a></div>
        </div>
    </section>
</main>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://npmcdn.com/flatpickr/dist/l10n/es.js"></script>

<script>
document.addEventListener('DOMContentLoaded', () => {

    /* Show/hide password */
    document.querySelectorAll('.pw-toggle').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = btn.getAttribute('data-target');
            const input = document.getElementById(id);
            if (!input) return;
            const isPw = input.type === 'password';
            input.type = isPw ? 'text' : 'password';
            btn.querySelector('i').className = isPw ? 'fa-regular fa-eye-slash' : 'fa-regular fa-eye';
            btn.setAttribute('aria-label', isPw ? 'Ocultar contraseña' : 'Mostrar contraseña');
        });
    });

    /* Password match validation */
    const pw = document.getElementById('contrasena');
    const pw2 = document.getElementById('confirmar_contrasena');
    const checkMatch = () => {
        if (pw.value && pw2.value && pw.value !== pw2.value) {
            pw2.setCustomValidity('Las contraseñas no coinciden.');
        } else {
            pw2.setCustomValidity('');
        }
    };
    if (pw && pw2) {
        pw.addEventListener('change', checkMatch);
        pw2.addEventListener('keyup', checkMatch);
    }

    /* Flatpickr */
    if (window.flatpickr) {
        flatpickr("#fecha_nacimiento", {
            dateFormat: "Y-m-d",
            altInput: true,
            altFormat: "d / m / Y",
            locale: "es",
            maxDate: "today",
            disableMobile: true,
        });
    }
});
</script>

</body>
</html>
