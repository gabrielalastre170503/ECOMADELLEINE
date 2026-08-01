<?php
/*
 * verificar_correo.php — Valida el token de verificación de correo (Fase 1).
 * Marca usuarios.email_verificado = 1 y limpia el token.
 */
if (session_status() === PHP_SESSION_NONE) session_start();
include __DIR__ . '/../core/conexion.php';

$ok = false;
$titulo = 'Verificación de correo';
$mensaje = '';

$token = isset($_GET['token']) ? trim((string)$_GET['token']) : '';

if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
    $mensaje = 'El enlace de verificación no es válido.';
} else {
    // La columna guarda el sha256: se busca por el hash de lo que trae el enlace.
    $tokenHash = hash('sha256', $token);
    $stmt = $conex->prepare("SELECT id, nombre_completo, email_verificado, token_verificacion_expira
                             FROM usuarios WHERE token_verificacion = ? LIMIT 1");
    $stmt->bind_param('s', $tokenHash);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$u) {
        $mensaje = 'El enlace de verificación no es válido o ya fue utilizado.';
    } elseif ((int)$u['email_verificado'] === 1) {
        $ok = true;
        $mensaje = 'Tu correo ya estaba verificado. Puedes iniciar sesión normalmente.';
    } elseif (!empty($u['token_verificacion_expira']) && strtotime($u['token_verificacion_expira']) < time()) {
        $mensaje = 'El enlace de verificación venció. Inicia sesión y solicita uno nuevo.';
    } else {
        $upd = $conex->prepare("UPDATE usuarios SET email_verificado = 1, token_verificacion = NULL, token_verificacion_expira = NULL WHERE id = ?");
        $upd->bind_param('i', $u['id']);
        if ($upd->execute()) {
            $ok = true;
            $mensaje = '¡Tu correo fue verificado correctamente! Ya puedes iniciar sesión.';
        } else {
            $mensaje = 'No se pudo completar la verificación. Inténtalo más tarde.';
        }
        $upd->close();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $ok ? 'Correo verificado' : 'Verificación' ?> · EcoMadelleine</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --azul:#02b1f4; --azul-dark:#014a82; --ink:#0c1a2e; --gris:#4a5870; --ok:#15803d; --err:#b91c1c; }
        * { box-sizing:border-box; margin:0; padding:0; }
        body { font-family:'Inter',system-ui,sans-serif; min-height:100vh; display:flex; align-items:center; justify-content:center;
               background:linear-gradient(180deg,#eaf3ff 0%,#f5f9ff 100%); color:var(--ink); padding:24px; }
        .card { background:rgba(255,255,255,.72); backdrop-filter:blur(28px); border:1px solid rgba(255,255,255,.6);
                border-radius:24px; padding:48px 40px; max-width:440px; width:100%; text-align:center;
                box-shadow:0 30px 80px rgba(12,26,46,.12); }
        .ic { width:72px; height:72px; border-radius:50%; display:flex; align-items:center; justify-content:center;
              margin:0 auto 22px; font-size:30px; }
        .ic--ok { background:rgba(34,197,94,.12); color:var(--ok); }
        .ic--err { background:rgba(239,68,68,.1); color:var(--err); }
        h1 { font-size:22px; font-weight:700; margin-bottom:10px; letter-spacing:-.02em; }
        p { font-size:14.5px; color:var(--gris); line-height:1.6; margin-bottom:28px; }
        .btn { display:inline-flex; align-items:center; gap:9px; padding:14px 26px; border-radius:14px;
               background:linear-gradient(135deg,var(--azul),var(--azul-dark)); color:#fff; font-weight:600; font-size:14.5px;
               text-decoration:none; box-shadow:0 14px 30px rgba(2,177,244,.35); transition:transform .2s; }
        .btn:hover { transform:translateY(-2px); }
    </style>
</head>
<body>
    <div class="card">
        <div class="ic <?= $ok ? 'ic--ok' : 'ic--err' ?>">
            <i class="fa-solid <?= $ok ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
        </div>
        <h1><?= $ok ? '¡Correo verificado!' : 'No se pudo verificar' ?></h1>
        <p><?= htmlspecialchars($mensaje) ?></p>
        <a class="btn" href="<?= eco_url('login') ?>"><i class="fa-solid fa-right-to-bracket"></i> Ir a iniciar sesión</a>
    </div>
</body>
</html>
