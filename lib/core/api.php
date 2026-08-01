<?php
/**
 * Capa API consistente (Fase 5A).
 *
 * Helpers opt-in para endpoints JSON: cabecera, sesion, auth por rol, CSRF,
 * lectura de parametros (POST o cuerpo JSON) y respuestas con shape plano
 * `{ "success": bool, ... }` — compatible con los endpoints existentes.
 *
 * Uso tipico:
 *   require_once __DIR__ . '/lib/core/api.php';
 *   include 'core/conexion.php';
 *   api_json();
 *   api_require_roles(['administrador', 'recepcionista']);
 *   api_require_post();
 *   api_require_csrf();
 *   $id = api_int('cita_id');
 *   if ($id <= 0) api_fail('Cita no valida.');
 *   api_ok(['estado' => 'confirmada']);
 */

if (!function_exists('api_json')) {
    /** Fija la cabecera JSON (una sola vez). */
    function api_json(): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
    }
}

if (!function_exists('api_session')) {
    /** Asegura que la sesion este iniciada sin duplicar el arranque. */
    function api_session(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        /* OJO: aquí NO se marca actividad. Al hacerlo, la marca se refrescaba
           antes de que api_require_login() comprobara la caducidad, así que la
           sesión no expiraba nunca por esta vía. Quien renueva la marca es
           eco_sesion_inactividad(true) desde api_require_login(), y solo
           después de comprobar que no había vencido. */
    }
}

if (!function_exists('api_uid')) {
    /** Id del usuario en sesion (0 si no hay). */
    function api_uid(): int
    {
        api_session();
        return (int)($_SESSION['usuario_id'] ?? 0);
    }
}

if (!function_exists('api_rol')) {
    /** Rol del usuario en sesion ('' si no hay). */
    function api_rol(): string
    {
        api_session();
        return (string)($_SESSION['rol'] ?? '');
    }
}

if (!function_exists('api_ok')) {
    /** Respuesta de exito: { success:true, ...$fields } y termina. */
    function api_ok(array $fields = []): never
    {
        echo json_encode(['success' => true] + $fields);
        exit();
    }
}

if (!function_exists('api_fail')) {
    /** Respuesta de error: status + { success:false, message, ...$extra } y termina. */
    function api_fail(string $message, int $code = 400, array $extra = []): never
    {
        http_response_code($code);
        echo json_encode(['success' => false, 'message' => $message] + $extra);
        exit();
    }
}

if (!function_exists('api_require_login')) {
    /** Exige sesion iniciada; si no, 401. */
    function api_require_login(): void
    {
        api_session();
        if (function_exists('eco_sesion_inactividad') && eco_sesion_inactividad(true)) {
            api_fail('Tu sesión expiró por inactividad. Vuelve a iniciar sesión.', 401);
        }
        if (api_uid() <= 0) {
            api_fail('No autenticado.', 401);
        }
    }
}

if (!function_exists('api_require_roles')) {
    /** Exige sesion + que el rol este en la lista permitida; si no, 403. */
    function api_require_roles(array $roles): void
    {
        api_require_login();
        if (!in_array(api_rol(), $roles, true)) {
            api_fail('Acceso no autorizado.', 403);
        }
    }
}

if (!function_exists('api_require_post')) {
    /** Exige metodo POST; si no, 405. */
    function api_require_post(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            api_fail('Metodo no permitido.', 405);
        }
    }
}

if (!function_exists('api_require_csrf')) {
    /** Valida el token CSRF reutilizando require_csrf() de bootstrap. */
    function api_require_csrf(): void
    {
        if (function_exists('require_csrf')) {
            require_csrf();
        }
    }
}

if (!function_exists('api_body')) {
    /** Cuerpo JSON decodificado (assoc) una sola vez; [] si no es JSON valido. */
    function api_body(): array
    {
        static $body = null;
        if ($body === null) {
            $raw = file_get_contents('php://input');
            $dec = $raw !== '' ? json_decode($raw, true) : null;
            $body = is_array($dec) ? $dec : [];
        }
        return $body;
    }
}

if (!function_exists('api_param')) {
    /** Lee un parametro de $_POST, luego del cuerpo JSON, luego default. */
    function api_param(string $key, $default = null)
    {
        if (isset($_POST[$key])) {
            return $_POST[$key];
        }
        $body = api_body();
        return $body[$key] ?? $default;
    }
}

if (!function_exists('api_int')) {
    /** Parametro como entero. */
    function api_int(string $key, int $default = 0): int
    {
        $v = api_param($key, null);
        return $v === null ? $default : (int)$v;
    }
}

if (!function_exists('api_str')) {
    /** Parametro como string recortado. */
    function api_str(string $key, string $default = ''): string
    {
        $v = api_param($key, null);
        return $v === null ? $default : trim((string)$v);
    }
}

if (!function_exists('api_get')) {
    /** Lee un parametro de la query string ($_GET). */
    function api_get(string $key, $default = null)
    {
        return $_GET[$key] ?? $default;
    }
}

if (!function_exists('api_get_int')) {
    /** Parametro de query string como entero. */
    function api_get_int(string $key, int $default = 0): int
    {
        return isset($_GET[$key]) ? (int)$_GET[$key] : $default;
    }
}

if (!function_exists('api_get_str')) {
    /** Parametro de query string como string recortado. */
    function api_get_str(string $key, string $default = ''): string
    {
        return isset($_GET[$key]) ? trim((string)$_GET[$key]) : $default;
    }
}
