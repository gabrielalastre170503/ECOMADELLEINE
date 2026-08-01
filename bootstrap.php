<?php
/*
 * bootstrap.php — Arranque global de seguridad (Fase 0).
 *
 * Se carga automáticamente en CADA petición PHP mediante:
 *     php_value auto_prepend_file  (ver .htaccess)
 * Por eso NO debe producir ninguna salida.
 *
 * Responsabilidades:
 *   1. Cargar variables de entorno desde .env.
 *   2. Endurecer la cookie de sesión (HttpOnly, SameSite, Secure en HTTPS).
 *   3. Exponer helpers CSRF: csrf_token(), csrf_field(), csrf_meta(),
 *      csrf_validate(), require_csrf().
 *
 * Las páginas siguen llamando a session_start() como hasta ahora; al haberse
 * fijado antes los parámetros de cookie, la sesión hereda la configuración segura.
 */

if (defined('ECO_BOOTSTRAP')) {
    return;
}
define('ECO_BOOTSTRAP', 1);

require_once __DIR__ . '/config/env_loader.php';
eco_load_env(__DIR__ . '/.env');

/* ── 0. Visibilidad de errores según el entorno ────────────────────────
 * php.ini de XAMPP trae display_errors=On, que es lo correcto para
 * desarrollar y lo peor posible en producción: cualquier error fatal
 * imprime al navegador la traza completa con rutas absolutas, nombres de
 * tabla y fragmentos de consulta. Fuera de 'development' los errores se
 * registran en el log del servidor y no se muestran nunca.
 * Se controla con APP_ENV en el .env (development | production). */
$eco_entorno = strtolower(trim((string)eco_env('APP_ENV', 'production')));
$eco_es_dev  = in_array($eco_entorno, ['development', 'dev', 'local'], true);
@ini_set('display_errors',         $eco_es_dev ? '1' : '0');
@ini_set('display_startup_errors', $eco_es_dev ? '1' : '0');
@ini_set('log_errors', '1');   // registrar SIEMPRE, se muestren o no
unset($eco_entorno, $eco_es_dev);

/* ── 1. Endurecimiento de la sesión ───────────────────────────────── */
if (session_status() === PHP_SESSION_NONE) {
    $secure = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? null) == 443);

    // Mitiga session fixation aceptando solo IDs generados por el servidor.
    @ini_set('session.use_strict_mode', '1');
    @ini_set('session.use_only_cookies', '1');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/* ── 1b. Cabeceras de seguridad HTTP ──────────────────────────────────
 * CSP con allowlist de los CDNs realmente usados (jsdelivr, cdnjs, Google
 * Fonts, npmcdn/unpkg para el locale de flatpickr). Se permite 'unsafe-inline'
 * porque el código tiene mucho <script>/<style>/onclick inline. Aun así limita
 * orígenes externos, bloquea objetos y el framing por terceros (anti-clickjacking).
 * No se usa upgrade-insecure-requests para no romper el desarrollo en http. */
if (!headers_sent()) {
    /* php.ini trae expose_php=On, que anuncia la version exacta de PHP en cada
       respuesta y facilita buscar exploits conocidos para ella. Se quita aqui
       porque es determinista: "Header unset" en .htaccess no alcanza de forma
       fiable las cabeceras que anade el propio PHP. */
    header_remove('X-Powered-By');

    header('Content-Security-Policy: ' . implode('; ', [
        "default-src 'self'",
        "base-uri 'self'",
        "object-src 'none'",
        "frame-ancestors 'self'",
        "form-action 'self'",
        "img-src 'self' data: blob: https:",
        "font-src 'self' data: https://fonts.gstatic.com https://cdnjs.cloudflare.com",
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net https://npmcdn.com https://unpkg.com",
        "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://npmcdn.com https://unpkg.com",
        "connect-src 'self'",
        "frame-src 'self'",
    ]));
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
}

/* ── 1c. Caducidad de sesión por inactividad ──────────────────────────
 * php.ini trae gc_maxlifetime=1440 (24 min), pero con gc_probability 1/1000
 * el recolector solo corre en el 0,1 % de las peticiones: en la práctica una
 * sesión abandonada sobrevive indefinidamente. En un puesto compartido —la
 * recepción de una clínica— eso significa que quien se siente después sigue
 * dentro de la sesión anterior. Aquí la caducidad es determinista.
 *
 * Cobertura: se REFRESCA en cada página y en los endpoints que usan la capa
 * api_*, y se APLICA al cargar cualquier página (layouts/shell.php) y en
 * api_require_login(). Los endpoints que leen $_SESSION a pelo no la refrescan
 * ni la aplican: no alargan la sesión, que es el lado seguro del fallo. */
if (!defined('ECO_SESION_INACTIVIDAD_MIN')) {
    define('ECO_SESION_INACTIVIDAD_MIN', 30);
}

if (!function_exists('eco_sesion_inactividad')) {
    /**
     * @param bool $aplicar  true = cierra la sesión si venció; false = solo marca actividad.
     * @return bool  true si la sesión se cerró por inactividad.
     */
    function eco_sesion_inactividad(bool $aplicar = true): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE || empty($_SESSION['usuario_id'])) {
            return false;
        }
        $limite = ECO_SESION_INACTIVIDAD_MIN * 60;
        $ultima = (int)($_SESSION['eco_ultima_actividad'] ?? 0);

        if ($aplicar && $ultima > 0 && (time() - $ultima) > $limite) {
            $_SESSION = [];
            if (ini_get('session.use_cookies') && !headers_sent()) {
                $p = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $p['path'], $p['domain'], $p['secure'], $p['httponly']);
            }
            @session_destroy();
            return true;
        }
        $_SESSION['eco_ultima_actividad'] = time();
        return false;
    }
}

/* ── 2. Helpers CSRF ──────────────────────────────────────────────── */
if (!function_exists('csrf_token')) {

    function eco_ensure_session()
    {
        // Solo arranca si no hay sesión y aún no se enviaron cabeceras
        // (evita el warning "headers already sent" en páginas que imprimen
        //  HTML antes de tocar la sesión).
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }
    }

    /** Devuelve (creando si hace falta) el token CSRF de la sesión. */
    function csrf_token()
    {
        eco_ensure_session();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /** Campo oculto para formularios HTML. */
    function csrf_field()
    {
        return '<input type="hidden" name="csrf_token" value="'
            . htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
    }

    /** Etiqueta <meta> para que el JavaScript pueda leer el token. */
    function csrf_meta()
    {
        return '<meta name="csrf-token" content="'
            . htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
    }

    /** Compara en tiempo constante el token recibido con el de la sesión. */
    function csrf_validate($token)
    {
        eco_ensure_session();
        return !empty($_SESSION['csrf_token'])
            && is_string($token)
            && hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Exige un token CSRF válido en peticiones que cambian estado.
     * Lee el token de $_POST['csrf_token'] o de la cabecera X-CSRF-Token.
     * Si falla, responde 419 y termina la ejecución.
     *
     * @param bool $force  Si es true, valida también métodos distintos de POST.
     */
    function require_csrf($force = false)
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (!$force && $method !== 'POST') {
            return;
        }

        $token = $_POST['csrf_token']
            ?? $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? '';

        if (csrf_validate($token)) {
            return;
        }

        // 403 Forbidden: estándar y compatible con Apache/mod_php.
        // (Se evita 419 porque Apache lo degrada a 500 al no reconocerlo.)
        http_response_code(403);

        // Responder en el formato esperado por el cliente.
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $isAjax = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest')
            || isset($_SERVER['HTTP_X_CSRF_TOKEN'])
            || strpos($accept, 'application/json') !== false;

        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'ok'      => false,
                'message' => 'Sesión expirada o token de seguridad inválido. Recarga la página e inténtalo de nuevo.',
            ]);
        } else {
            header('Content-Type: text/html; charset=utf-8');
            echo '<!doctype html><meta charset="utf-8"><title>Sesión expirada</title>'
                . '<div style="font-family:system-ui,sans-serif;max-width:520px;margin:80px auto;text-align:center;color:#1e2a44">'
                . '<h2>Sesión expirada</h2>'
                . '<p>Por seguridad, tu solicitud no pudo verificarse. Recarga la página e inténtalo de nuevo.</p>'
                . '<p><a href="javascript:history.back()" style="color:#0277bd">Volver</a></p></div>';
        }
        exit;
    }
}

/* ── 3. Helper de URL (rutas limpias del router) ──────────────────────
 * Devuelve una URL absoluta con el prefijo de la subcarpeta del proyecto,
 * para enlazar a rutas limpias sin romperse según la profundidad de la URL
 * actual. eco_url('mi-agenda') -> /Sistema_EcoMadelleineV1/mi-agenda */
if (!function_exists('eco_url')) {
    function eco_url(string $path = ''): string
    {
        static $base = null;
        if ($base === null) {
            // La base es la carpeta del proyecto relativa al DOCUMENT_ROOT, derivada
            // de la ubicacion de bootstrap.php (__DIR__ = raiz del proyecto), NO del
            // SCRIPT_NAME: asi no se rompe segun la profundidad del script en
            // ejecucion (endpoints en api/, paginas en subcarpetas accedidas directo).
            $docroot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
            $appdir  = rtrim(str_replace('\\', '/', __DIR__), '/');
            if ($docroot !== '' && stripos($appdir, $docroot) === 0) {
                $base = substr($appdir, strlen($docroot));
            } else {
                $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
            }
        }
        if ($path === '') {
            return $base !== '' ? $base . '/' : '/';
        }
        return $base . '/' . ltrim($path, '/');
    }
}
