<?php
/**
 * Shell premium reutilizable — incluir desde cualquier vista que use este layout.
 *
 * Variables esperadas (definir ANTES de incluir):
 *   $page_title            (string)  Título mostrado en breadcrumb + <title>
 *   $page_content          (string)  HTML del contenido (usa ob_start/ob_get_clean)
 *   $active_section        (string)  id de sección activa para el sidebar
 *
 * Variables opcionales:
 *   $page_subtitle         (string)  Subtítulo bajo el H1
 *   $browser_title         (string)  Título en <title> y topbar si $page_title está vacío
 *   $page_header_actions   (string)  HTML extra para la zona derecha del header
 *   $topbar_quick_action   (array)   Ver topbar.php
 *   $topbar_notifications  (int)     Badge en la campana
 *   $body_class            (string)  Clases extra para <body>
 *   $page_head_extra       (string)  HTML opcional en <head> (CSS de terceros, etc.)
 *   $page_scripts_extra    (string)  HTML opcional antes de </body> (JS adicional)
 *
 *   Incluye shell-modals.css / shell-modals.js: modales reutilizables (EcoModal).
 */
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . eco_url('login'));
    exit;
}

// Gate de consentimiento informado (cumplimiento médico): el paciente debe
// aceptar la versión vigente antes de usar el sistema. Defensivo: solo si hay
// conexión disponible (las vistas de paciente incluyen conexion.php).
if (($_SESSION['rol'] ?? '') === 'paciente' && isset($conex) && $conex instanceof mysqli) {
    require_once __DIR__ . '/../lib/seguridad/consentimiento.php';
    if (!eco_consentimiento_vigente($conex, (int)$_SESSION['usuario_id'])) {
        header('Location: ' . eco_url('consentimiento'));
        exit;
    }
}

$page_title          = $page_title          ?? 'Panel';
$page_subtitle       = $page_subtitle       ?? '';
$browser_title       = $browser_title       ?? '';
$html_title          = ($page_title !== '' && $page_title !== null) ? $page_title : ($browser_title !== '' ? $browser_title : 'Panel');
$page_content        = $page_content        ?? '';
$page_header_actions = $page_header_actions ?? '';
$active_section      = $active_section      ?? '';
$body_class          = $body_class          ?? '';
$page_head_extra     = $page_head_extra     ?? '';
$page_scripts_extra  = $page_scripts_extra  ?? '';

/* Cache-busting automático en $page_head_extra / $page_scripts_extra.
 *
 * Esos bloques suelen escribirse con nowdoc (<<<'HTML'), que protege el JS de
 * la interpolación de PHP pero impide llamar a filemtime() dentro. Antes se
 * ponía el número a mano (?v=26) y había que acordarse de subirlo en cada
 * página al tocar el asset; si se olvidaba, el navegador seguía sirviendo la
 * versión vieja. Escribiendo `?v=auto` se resuelve aquí con la fecha real del
 * archivo. Solo afecta a rutas propias: las de CDN no llevan el marcador.
 */
$eco_resolver_version = static function (string $html): string {
    return preg_replace_callback(
        '/(src|href)="([^"]+\.(?:js|css))\?v=auto"/i',
        static function (array $m): string {
            $ruta = __DIR__ . '/../' . ltrim($m[2], '/');
            return $m[1] . '="' . $m[2] . '?v=' . (@filemtime($ruta) ?: '1') . '"';
        },
        $html
    );
};
$page_head_extra    = $eco_resolver_version($page_head_extra);
$page_scripts_extra = $eco_resolver_version($page_scripts_extra);

// Clase de rol en el <body> (por si se quieren reskins puntuales por rol) +
// la clase 'eco-glass', gancho del tema glass Apple aplicado a todo el shell.
$user_role  = preg_replace('/[^a-z]/', '', strtolower($_SESSION['rol'] ?? ''));
$role_class = $user_role !== '' ? 'role-' . $user_role : '';
$body_class = trim($body_class . ' ' . $role_class . ' eco-glass');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?= csrf_meta() ?>
    <script>window.ECO_BASE = '<?= eco_url() ?>';</script>
    <title><?= htmlspecialchars($html_title) ?> · EcoMadelleine</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/core/shell.css?v=<?= @filemtime(__DIR__ . '/../assets/css/core/shell.css') ?: '1' ?>">
    <link rel="stylesheet" href="assets/css/core/shell-modals.css?v=<?= @filemtime(__DIR__ . '/../assets/css/core/shell-modals.css') ?: '1' ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/core/glass.css?v=<?= @filemtime(__DIR__ . '/../assets/css/core/glass.css') ?: '1' ?>">
    <?= $page_head_extra ?>

    <script>
        (function () {
            var t = localStorage.getItem('eco_theme') || 'light';
            document.documentElement.setAttribute('data-theme', t);
        })();
    </script>

    <!-- CSRF: inyecta automáticamente el token en todas las peticiones fetch() same-origin -->
    <script>
        (function () {
            var meta = document.querySelector('meta[name="csrf-token"]');
            var token = meta ? meta.getAttribute('content') : '';
            window.ECO_CSRF = token;
            if (!token || !window.fetch) return;
            var _fetch = window.fetch;
            window.fetch = function (input, init) {
                init = init || {};
                var url = (typeof input === 'string') ? input : (input && input.url) || '';
                var sameOrigin = (url.indexOf('http://') !== 0 && url.indexOf('https://') !== 0)
                    || url.indexOf(window.location.origin) === 0;
                if (sameOrigin) {
                    var headers = new Headers(init.headers || (typeof input !== 'string' && input.headers) || {});
                    if (!headers.has('X-CSRF-Token')) headers.set('X-CSRF-Token', token);
                    init.headers = headers;
                }
                return _fetch(input, init);
            };
        })();
    </script>
</head>
<body class="<?= htmlspecialchars($body_class) ?>">

    <div class="app-shell">

        <?php include __DIR__ . '/partials/sidebar.php'; ?>

        <div class="app-main">

            <?php include __DIR__ . '/partials/topbar.php'; ?>

            <main class="app-page">

                <?php if (!empty($_SESSION['email_sin_verificar'])): ?>
                    <div class="eco-verif-banner" role="status">
                        <i class="fa-solid fa-envelope-circle-check"></i>
                        <span>
                            <?php if (($_GET['verif'] ?? '') === 'enviado'): ?>
                                Te reenviamos el correo de verificación. Revisa tu bandeja de entrada y spam.
                            <?php elseif (($_GET['verif'] ?? '') === 'error'): ?>
                                No pudimos reenviar el correo ahora. Inténtalo más tarde.
                            <?php else: ?>
                                Tu correo aún no está verificado. Verifícalo para asegurar tu cuenta y recibir notificaciones.
                            <?php endif; ?>
                        </span>
                        <a href="<?= eco_url('publico/reenviar_verificacion.php') ?>" class="eco-verif-banner__btn">
                            <i class="fa-solid fa-paper-plane"></i> Reenviar correo
                        </a>
                    </div>
                    <style>
                        .eco-verif-banner{display:flex;align-items:center;gap:12px;flex-wrap:wrap;
                            background:linear-gradient(180deg,rgba(245,158,11,.12),rgba(245,158,11,.06));
                            border:1px solid rgba(245,158,11,.35);color:#92400e;border-radius:12px;
                            padding:12px 16px;margin-bottom:16px;font-size:13.5px;}
                        .eco-verif-banner > i{font-size:18px;color:#d97706;flex-shrink:0;}
                        .eco-verif-banner span{flex:1;min-width:200px;line-height:1.4;}
                        .eco-verif-banner__btn{display:inline-flex;align-items:center;gap:6px;white-space:nowrap;
                            background:#d97706;color:#fff;text-decoration:none;font-weight:600;font-size:12.5px;
                            padding:8px 14px;border-radius:8px;transition:background .2s;}
                        .eco-verif-banner__btn:hover{background:#b45309;}
                    </style>
                <?php endif; ?>

                <?php if ($page_title || $page_subtitle || $page_header_actions): ?>
                    <div class="page-header">
                        <div>
                            <h1><?= htmlspecialchars($page_title) ?></h1>
                            <?php if ($page_subtitle): ?>
                                <p><?= htmlspecialchars($page_subtitle) ?></p>
                            <?php endif; ?>
                        </div>
                        <?php if ($page_header_actions): ?>
                            <div class="page-header-actions"><?= $page_header_actions ?></div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?= $page_content ?>

            </main>
        </div>

    </div>

    <script src="assets/js/core/shell.js"></script>
    <script src="assets/js/core/shell-modals.js"></script>
    <script src="assets/js/core/notificaciones.js?v=<?= @filemtime(__DIR__ . '/../assets/js/core/notificaciones.js') ?: '1' ?>"></script>
    <?= $page_scripts_extra ?>
</body>
</html>
