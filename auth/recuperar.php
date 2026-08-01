<?php
/*
 * recuperar.php — Solicitud de recuperación de contraseña (Fase 1).
 * Envía un enlace con token de un solo uso (1 h) al correo si existe.
 * No revela si el correo está o no registrado (evita enumeración de usuarios).
 */
if (session_status() === PHP_SESSION_NONE) session_start();
include __DIR__ . '/../core/conexion.php';
require_once __DIR__ . '/../lib/comunicaciones/correo_app.php';

$enviado = false;
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $correo = trim((string)($_POST['correo'] ?? ''));

    if ($correo === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $error = 'Introduce un correo electrónico válido.';
    } else {
        $stmt = $conex->prepare("SELECT id, nombre_completo FROM usuarios WHERE correo = ? AND estado = 'aprobado' LIMIT 1");
        $stmt->bind_param('s', $correo);
        $stmt->execute();
        $u = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($u) {
            $token  = eco_token();
            /* En la BD va solo el sha256, nunca el token en claro: es el mismo
               criterio que descarga_tokens. Quien lea la base (un respaldo, una
               copia de la tabla) no obtiene enlaces de restablecimiento usables;
               el token en claro existe unicamente en el correo del usuario. */
            $tokenHash = hash('sha256', $token);
            $expira = date('Y-m-d H:i:s', strtotime('+1 hour'));
            $upd = $conex->prepare("UPDATE usuarios SET token_recuperacion = ?, token_recuperacion_expira = ? WHERE id = ?");
            $upd->bind_param('ssi', $tokenHash, $expira, $u['id']);
            $upd->execute();
            $upd->close();

            $link = eco_base_url() . '/publico/restablecer_password.php?token=' . urlencode($token);
            $cuerpo = "Hola {$u['nombre_completo']},\n\n"
                . "Recibimos una solicitud para restablecer tu contraseña en EcoMadelleine. "
                . "Para crear una nueva, abre este enlace:\n\n"
                . $link . "\n\n"
                . "El enlace vence en 1 hora. Si no solicitaste esto, ignora este mensaje; tu contraseña seguirá igual.\n\n"
                . "— EcoMadelleine · Centro de Diagnóstico";
            eco_enviar_correo($correo, 'Restablece tu contraseña · EcoMadelleine', $cuerpo);
        }
        // Respuesta genérica siempre (no se revela si el correo existe).
        $enviado = true;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar contraseña · EcoMadelleine</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --azul:#02b1f4; --azul-dark:#014a82; --ink:#0c1a2e; --gris:#4a5870; --gris-mute:#94a3b8; --err:#b91c1c; --ok:#15803d; }
        * { box-sizing:border-box; margin:0; padding:0; }
        body { font-family:'Inter',system-ui,sans-serif; min-height:100vh; display:flex; align-items:center; justify-content:center;
               background:linear-gradient(180deg,#eaf3ff 0%,#f5f9ff 100%); color:var(--ink); padding:24px; }
        .card { background:rgba(255,255,255,.72); backdrop-filter:blur(28px); border:1px solid rgba(255,255,255,.6);
                border-radius:24px; padding:44px 40px; max-width:440px; width:100%;
                box-shadow:0 30px 80px rgba(12,26,46,.12); }
        .ic { width:64px; height:64px; border-radius:50%; background:rgba(2,177,244,.12); color:var(--azul-dark);
              display:flex; align-items:center; justify-content:center; margin:0 auto 20px; font-size:24px; }
        h1 { font-size:21px; font-weight:700; margin-bottom:8px; letter-spacing:-.02em; text-align:center; }
        p.sub { font-size:14px; color:var(--gris); line-height:1.55; margin-bottom:26px; text-align:center; }
        .field { position:relative; display:flex; align-items:center; margin-bottom:16px; }
        .field > i { position:absolute; left:16px; color:var(--gris-mute); font-size:14px; }
        .field input { width:100%; padding:14px 16px 14px 46px; border:1px solid rgba(2,177,244,.35); border-radius:14px;
                       font-size:14.5px; background:#fff; color:var(--ink); font-family:inherit; }
        .field input:focus { outline:none; border-color:var(--azul); box-shadow:0 0 0 4px rgba(2,177,244,.14); }
        .btn { width:100%; padding:15px; border:none; border-radius:14px; cursor:pointer;
               background:linear-gradient(135deg,var(--azul),var(--azul-dark)); color:#fff; font-weight:600; font-size:14.5px;
               font-family:inherit; box-shadow:0 14px 30px rgba(2,177,244,.35); }
        .msg { padding:12px 16px; border-radius:12px; font-size:13.5px; margin-bottom:18px; display:flex; gap:10px; align-items:flex-start; }
        .msg-err { background:rgba(239,68,68,.08); color:var(--err); border:1px solid rgba(239,68,68,.25); }
        .msg-ok  { background:rgba(34,197,94,.08); color:var(--ok); border:1px solid rgba(34,197,94,.3); }
        .foot { margin-top:22px; text-align:center; font-size:13.5px; }
        .foot a { color:var(--azul-dark); font-weight:600; text-decoration:none; }
        .foot a:hover { color:var(--azul); }
    </style>
</head>
<body>
    <div class="card">
        <div class="ic"><i class="fa-solid fa-key"></i></div>
        <h1>Recuperar contraseña</h1>

        <?php if ($enviado): ?>
            <div class="msg msg-ok" style="margin-top:6px;">
                <i class="fa-solid fa-paper-plane"></i>
                <span>Si ese correo está registrado, te enviamos un enlace para restablecer tu contraseña. Revisa tu bandeja de entrada (y spam). El enlace vence en 1 hora.</span>
            </div>
            <div class="foot"><a href="<?= eco_url('login') ?>"><i class="fa-solid fa-arrow-left"></i> Volver a iniciar sesión</a></div>
        <?php else: ?>
            <p class="sub">Introduce el correo de tu cuenta y te enviaremos un enlace para crear una nueva contraseña.</p>
            <?php if ($error): ?>
                <div class="msg msg-err"><i class="fa-solid fa-triangle-exclamation"></i><span><?= htmlspecialchars($error) ?></span></div>
            <?php endif; ?>
            <form method="POST" action="<?= eco_url('recuperar') ?>" autocomplete="off">
                <?= csrf_field() ?>
                <div class="field">
                    <i class="fa-regular fa-envelope"></i>
                    <input type="email" name="correo" placeholder="Correo electrónico" required autofocus autocomplete="email">
                </div>
                <button type="submit" class="btn"><i class="fa-solid fa-paper-plane"></i> Enviar enlace</button>
            </form>
            <div class="foot"><a href="<?= eco_url('login') ?>"><i class="fa-solid fa-arrow-left"></i> Volver a iniciar sesión</a></div>
        <?php endif; ?>
    </div>
</body>
</html>
