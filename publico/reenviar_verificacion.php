<?php
/*
 * reenviar_verificacion.php — Reenvía el correo de verificación al usuario
 * autenticado que aún no ha verificado su cuenta (Fase 1).
 */
if (session_status() === PHP_SESSION_NONE) session_start();
include __DIR__ . '/../core/conexion.php';
require_once __DIR__ . '/../lib/comunicaciones/correo_app.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . eco_url('login'));
    exit;
}

$uid = (int)$_SESSION['usuario_id'];
$volver = eco_url('dashboard');

$stmt = $conex->prepare("SELECT nombre_completo, correo, email_verificado FROM usuarios WHERE id = ?");
$stmt->bind_param('i', $uid);
$stmt->execute();
$u = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$u) {
    header('Location: ' . eco_url('login'));
    exit;
}

if ((int)$u['email_verificado'] === 1) {
    unset($_SESSION['email_sin_verificar']);
    header('Location: ' . $volver . '?verif=ya');
    exit;
}

$token  = eco_token();
// En la BD solo el sha256; el token en claro va unicamente en el enlace.
$tokenHash = hash('sha256', $token);
$expira = date('Y-m-d H:i:s', strtotime('+24 hours'));
$upd = $conex->prepare("UPDATE usuarios SET token_verificacion = ?, token_verificacion_expira = ? WHERE id = ?");
$upd->bind_param('ssi', $tokenHash, $expira, $uid);
$upd->execute();
$upd->close();

$link = eco_base_url() . '/publico/verificar_correo.php?token=' . urlencode($token);
$cuerpo = "Hola {$u['nombre_completo']},\n\n"
    . "Para verificar tu correo en EcoMadelleine, abre este enlace:\n\n"
    . $link . "\n\n"
    . "El enlace vence en 24 horas.\n\n"
    . "— EcoMadelleine · Centro de Diagnóstico";

$ok = eco_enviar_correo((string)$u['correo'], 'Verifica tu correo · EcoMadelleine', $cuerpo);

header('Location: ' . $volver . ($ok ? '?verif=enviado' : '?verif=error'));
exit;
