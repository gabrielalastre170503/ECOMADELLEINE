<?php
/**
 * lib/seguridad/seguridad.php — Cierre de Fase 0.
 *
 *   - Auditoria de acciones sensibles            -> tabla `auditoria`
 *   - Throttling persistente de login (anti fuerza bruta) -> tabla `intentos_login`
 *
 * Requiere una conexion mysqli activa ($conex) pasada por parametro.
 * Todas las funciones DEGRADAN EN SILENCIO: si la tabla no existe o la query
 * falla, no lanzan ni rompen el flujo principal (login, etc.).
 *
 * Migracion: database/migrations/2026_fase0_01_auditoria_throttle.sql
 */

if (!defined('ECO_LOGIN_MAX_FALLOS'))  define('ECO_LOGIN_MAX_FALLOS', 5);   // fallos permitidos
if (!defined('ECO_LOGIN_VENTANA_MIN')) define('ECO_LOGIN_VENTANA_MIN', 15); // ventana / bloqueo (min)

if (!function_exists('eco_client_ip')) {

    /** IP del cliente. Solo REMOTE_ADDR (no se confia en cabeceras spoofeables). */
    function eco_client_ip(): string
    {
        return substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    }

    /** User-Agent recortado a la longitud de la columna. */
    function eco_user_agent(): string
    {
        return substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }

    /**
     * Registra una accion en la bitacora `auditoria`. Degrada en silencio.
     *
     * @param mysqli $conex
     * @param string $accion  verbo corto: 'login_exito', 'login_fallido', 'crear_cita'...
     * @param array  $opts    usuario_id, entidad, entidad_id, detalle (string|array)
     */
    function eco_auditar(mysqli $conex, string $accion, array $opts = []): void
    {
        $usuarioId = $opts['usuario_id'] ?? ($_SESSION['usuario_id'] ?? null);
        $usuarioId = ($usuarioId !== null && $usuarioId !== '') ? (int)$usuarioId : null;
        $entidad   = isset($opts['entidad'])    ? substr((string)$opts['entidad'], 0, 40) : null;
        $entidadId = (isset($opts['entidad_id']) && $opts['entidad_id'] !== '') ? (int)$opts['entidad_id'] : null;

        $detalle = $opts['detalle'] ?? null;
        if (is_array($detalle)) {
            $detalle = json_encode($detalle, JSON_UNESCAPED_UNICODE);
        }

        $ip = eco_client_ip();
        $ua = eco_user_agent();

        $sql = "INSERT INTO auditoria (usuario_id, accion, entidad, entidad_id, detalle, ip, user_agent)
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        // El try/catch es imprescindible: desde PHP 8 mysqli lanza excepciones y
        // '@' NO las suprime, solo silencia warnings. Sin esto, un fallo al
        // auditar (tabla ausente, restriccion de clave ajena, disco lleno) no
        // "degradaba en silencio" como promete la cabecera: abortaba la peticion
        // con un error fatal, que ademas imprime la traza y las rutas absolutas.
        try {
            if (!($st = @$conex->prepare($sql))) {
                return;
            }
            $st->bind_param('ississs', $usuarioId, $accion, $entidad, $entidadId, $detalle, $ip, $ua);
            $st->execute();
            $st->close();
        } catch (\Throwable $e) {
            error_log('eco_auditar: no se pudo registrar "' . $accion . '" — ' . $e->getMessage());
        }
    }

    /**
     * Registra un intento de login (exitoso o fallido) en `intentos_login`.
     */
    function eco_login_registrar(mysqli $conex, string $correo, bool $exito): void
    {
        $correo = substr($correo, 0, 100);
        $ip     = eco_client_ip();
        $ex     = $exito ? 1 : 0;
        try {
            if (!($st = @$conex->prepare(
                "INSERT INTO intentos_login (correo, ip, exito) VALUES (?, ?, ?)"
            ))) {
                return;
            }
            $st->bind_param('ssi', $correo, $ip, $ex);
            $st->execute();
            $st->close();
        } catch (\Throwable $e) {
            error_log('eco_login_registrar: ' . $e->getMessage());
        }
    }

    /**
     * Estado de bloqueo por fuerza bruta para (correo + IP) en la ventana actual.
     *
     * @return array{bloqueado:bool, fallos:int, espera:int}
     *         espera = segundos restantes de bloqueo (0 si no esta bloqueado).
     */
    function eco_login_estado(mysqli $conex, string $correo): array
    {
        $out     = ['bloqueado' => false, 'fallos' => 0, 'espera' => 0];
        $ip      = eco_client_ip();
        $ventana = (int)ECO_LOGIN_VENTANA_MIN;

        // El intervalo es una constante interna (no entrada del usuario): se inyecta directo.
        // `espera` se calcula con el reloj de MySQL (TIMESTAMPDIFF) para evitar
        // desfases de timezone entre PHP y la BD.
        $sql = "SELECT COUNT(*) AS fallos,
                       TIMESTAMPDIFF(SECOND, NOW(), MAX(creado_en) + INTERVAL {$ventana} MINUTE) AS espera
                FROM intentos_login
                WHERE exito = 0
                  AND creado_en > (NOW() - INTERVAL {$ventana} MINUTE)
                  AND (correo = ? OR ip = ?)";
        try {
            if (!($st = @$conex->prepare($sql))) {
                return $out;
            }
            $st->bind_param('ss', $correo, $ip);
            if (!$st->execute()) {
                $st->close();
                return $out;
            }
            $row = $st->get_result()->fetch_assoc();
            $st->close();
        } catch (\Throwable $e) {
            // Si el throttling no puede consultarse, NO se bloquea el login:
            // se devuelve "sin bloqueo" y queda constancia en el log.
            error_log('eco_login_estado: ' . $e->getMessage());
            return $out;
        }

        $fallos        = (int)($row['fallos'] ?? 0);
        $espera        = (int)($row['espera'] ?? 0);
        $out['fallos'] = $fallos;

        if ($fallos >= ECO_LOGIN_MAX_FALLOS && $espera > 0) {
            $out['bloqueado'] = true;
            $out['espera']    = $espera;
        }
        return $out;
    }

    /** Limpia los fallos de un correo tras un login correcto (resetea el contador). */
    function eco_login_limpiar(mysqli $conex, string $correo): void
    {
        try {
            if (!($st = @$conex->prepare(
                "DELETE FROM intentos_login WHERE correo = ? AND exito = 0"
            ))) {
                return;
            }
            $st->bind_param('s', $correo);
            $st->execute();
            $st->close();
        } catch (\Throwable $e) {
            error_log('eco_login_limpiar: ' . $e->getMessage());
        }
    }
}
