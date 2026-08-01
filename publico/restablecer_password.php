<?php
/*
 * restablecer_password.php — Define una nueva contraseña a partir del token (Fase 1).
 */
if (session_status() === PHP_SESSION_NONE) session_start();
include __DIR__ . '/../core/conexion.php';
require_once __DIR__ . '/../lib/seguridad/seguridad.php';

$error = '';
$token = '';
$token_valido = false;
$usuario_id = null;

/* Localiza y valida el token (de GET o de POST). */
$token = trim((string)($_POST['token'] ?? $_GET['token'] ?? ''));
if ($token !== '' && preg_match('/^[a-f0-9]{64}$/', $token)) {
    /* La columna guarda el sha256, no el token: se busca por el hash de lo que
       llega en el enlace (ver auth/recuperar.php). */
    $tokenHash = hash('sha256', $token);
    $stmt = $conex->prepare("SELECT id, token_recuperacion_expira FROM usuarios WHERE token_recuperacion = ? LIMIT 1");
    $stmt->bind_param('s', $tokenHash);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($u && !empty($u['token_recuperacion_expira']) && strtotime($u['token_recuperacion_expira']) >= time()) {
        $token_valido = true;
        $usuario_id = (int)$u['id'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    if (!$token_valido) {
        $error = 'El enlace es inválido o venció. Solicita uno nuevo.';
    } else {
        $p1 = (string)($_POST['contrasena'] ?? '');
        $p2 = (string)($_POST['confirmar_contrasena'] ?? '');
        if ($p1 !== $p2) {
            $error = 'Las contraseñas no coinciden.';
        } elseif (strlen($p1) < 8 || !preg_match('/[A-Z]/', $p1) || !preg_match('/[\W_]/', $p1)) {
            $error = 'La contraseña debe tener al menos 8 caracteres, una mayúscula y un símbolo.';
        } else {
            $hash = password_hash($p1, PASSWORD_DEFAULT);
            $upd = $conex->prepare("UPDATE usuarios SET contrasena = ?, token_recuperacion = NULL, token_recuperacion_expira = NULL WHERE id = ?");
            $upd->bind_param('si', $hash, $usuario_id);
            if ($upd->execute()) {
                $upd->close();
                eco_auditar($conex, 'password_restablecido', ['usuario_id' => $usuario_id]);
                header('Location: ' . eco_url('login') . '?status=password_actualizada');
                exit;
            }
            $upd->close();
            $error = 'No se pudo actualizar la contraseña. Inténtalo de nuevo.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva contraseña · EcoMadelleine</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --azul:#02b1f4; --azul-dark:#014a82; --ink:#0c1a2e; --gris:#4a5870; --gris-mute:#94a3b8; --err:#b91c1c; }
        * { box-sizing:border-box; margin:0; padding:0; }
        body { font-family:'Inter',system-ui,sans-serif; min-height:100vh; display:flex; align-items:center; justify-content:center;
               background:linear-gradient(180deg,#eaf3ff 0%,#f5f9ff 100%); color:var(--ink); padding:24px; }
        .card { background:rgba(255,255,255,.72); backdrop-filter:blur(28px); border:1px solid rgba(255,255,255,.6);
                border-radius:24px; padding:44px 40px; max-width:440px; width:100%;
                box-shadow:0 30px 80px rgba(12,26,46,.12); }
        .ic { width:64px; height:64px; border-radius:50%; background:rgba(2,177,244,.12); color:var(--azul-dark);
              display:flex; align-items:center; justify-content:center; margin:0 auto 20px; font-size:24px; }
        .ic--err { background:rgba(239,68,68,.1); color:var(--err); }
        h1 { font-size:21px; font-weight:700; margin-bottom:8px; letter-spacing:-.02em; text-align:center; }
        p.sub { font-size:14px; color:var(--gris); line-height:1.55; margin-bottom:24px; text-align:center; }
        .field { position:relative; display:flex; align-items:center; margin-bottom:14px; }
        .field > i { position:absolute; left:16px; color:var(--gris-mute); font-size:14px; }
        .field input { width:100%; padding:14px 16px 14px 46px; border:1px solid rgba(2,177,244,.35); border-radius:14px;
                       font-size:14.5px; background:#fff; color:var(--ink); font-family:inherit; }
        .field input:focus { outline:none; border-color:var(--azul); box-shadow:0 0 0 4px rgba(2,177,244,.14); }
        .hint { font-size:11.5px; color:var(--gris-mute); margin:2px 2px 16px; display:flex; gap:6px; align-items:flex-start; }
        .btn { width:100%; padding:15px; border:none; border-radius:14px; cursor:pointer;
               background:linear-gradient(135deg,var(--azul),var(--azul-dark)); color:#fff; font-weight:600; font-size:14.5px;
               font-family:inherit; box-shadow:0 14px 30px rgba(2,177,244,.35); }
        .msg { padding:12px 16px; border-radius:12px; font-size:13.5px; margin-bottom:18px; display:flex; gap:10px; align-items:flex-start; }
        .msg-err { background:rgba(239,68,68,.08); color:var(--err); border:1px solid rgba(239,68,68,.25); }
        .foot { margin-top:22px; text-align:center; font-size:13.5px; }
        .foot a { color:var(--azul-dark); font-weight:600; text-decoration:none; }
    </style>
</head>
<body>
    <div class="card">
        <?php if (!$token_valido): ?>
            <div class="ic ic--err"><i class="fa-solid fa-link-slash"></i></div>
            <h1>Enlace inválido</h1>
            <p class="sub"><?= htmlspecialchars($error ?: 'Este enlace de recuperación es inválido o ya venció.') ?></p>
            <div class="foot"><a href="<?= eco_url('recuperar') ?>"><i class="fa-solid fa-rotate-right"></i> Solicitar uno nuevo</a></div>
        <?php else: ?>
            <div class="ic"><i class="fa-solid fa-lock"></i></div>
            <h1>Crea tu nueva contraseña</h1>
            <p class="sub">Elige una contraseña segura para tu cuenta.</p>
            <?php if ($error): ?>
                <div class="msg msg-err"><i class="fa-solid fa-triangle-exclamation"></i><span><?= htmlspecialchars($error) ?></span></div>
            <?php endif; ?>
            <form method="POST" action="restablecer_password.php" autocomplete="off">
                <?= csrf_field() ?>
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                <div class="field">
                    <i class="fa-solid fa-lock"></i>
                    <input type="password" name="contrasena" placeholder="Nueva contraseña" required minlength="8"
                           pattern="(?=.*[A-Z])(?=.*[\W_]).{8,}" title="Mínimo 8 caracteres, una mayúscula y un símbolo." autocomplete="new-password">
                </div>
                <div class="field">
                    <i class="fa-solid fa-lock-open"></i>
                    <input type="password" name="confirmar_contrasena" placeholder="Confirmar contraseña" required autocomplete="new-password">
                </div>
                <div class="hint"><i class="fa-solid fa-circle-info"></i><span>Mínimo 8 caracteres con al menos una mayúscula y un símbolo (ej: !@#$%).</span></div>
                <button type="submit" class="btn"><i class="fa-solid fa-check"></i> Guardar contraseña</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
