<?php
/**
 * lib/core/tokens.php — Fase 3 (b): enlaces de resultado por token (sin login).
 *
 * Un token da acceso de SOLO LECTURA a los resultados de un informe (imagenes +
 * adjuntos) durante una ventana de tiempo y un numero limitado de aperturas.
 *
 * Seguridad:
 *   - En BD se guarda SOLO el sha256 del token (token_hash); el token en claro
 *     vive unicamente en la URL entregada. Una fuga de BD no produce enlaces
 *     usables.
 *   - El token en claro son 64 hex (32 bytes aleatorios) -> imposible de adivinar.
 *   - Caducidad (expira_en) + tope de aperturas (max_usos) + revocacion manual.
 *
 * Migracion: database/migrations/2026_fase3_03_descarga_tokens.sql
 */

if (!function_exists('eco_token_hash')) {

    function eco_token_hash(string $raw): string
    {
        return hash('sha256', $raw);
    }

    /**
     * Crea un token para los resultados de un informe.
     *
     * @param array $opts  expira_horas (int, def 72), max_usos (int|null, def 5),
     *                     creado_por (int|null)
     * @return array{raw:string, id:int, expira_en:string}
     * @throws RuntimeException
     */
    function eco_token_crear(mysqli $conex, int $informeId, array $opts = []): array
    {
        $horas = isset($opts['expira_horas']) ? max(1, (int)$opts['expira_horas']) : 72;
        $maxUsos = array_key_exists('max_usos', $opts)
            ? ($opts['max_usos'] === null ? null : max(1, (int)$opts['max_usos']))
            : 5;
        $creadoPor = (isset($opts['creado_por']) && $opts['creado_por'] !== '')
            ? (int)$opts['creado_por'] : null;

        $raw  = bin2hex(random_bytes(32));   // 64 hex
        $hash = eco_token_hash($raw);

        $sql = "INSERT INTO descarga_tokens (token_hash, informe_id, creado_por, max_usos, expira_en)
                VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? HOUR))";
        if (!($st = $conex->prepare($sql))) {
            throw new RuntimeException('Error de base de datos.');
        }
        // creado_por / max_usos pueden ser NULL: mysqli envia NULL si la variable lo es.
        $st->bind_param('siiii', $hash, $informeId, $creadoPor, $maxUsos, $horas);
        if (!$st->execute()) {
            $st->close();
            throw new RuntimeException('No se pudo generar el enlace.');
        }
        $id = (int)$st->insert_id;
        $st->close();

        $expira = '';
        if ($q = $conex->prepare("SELECT expira_en FROM descarga_tokens WHERE id = ?")) {
            $q->bind_param('i', $id);
            $q->execute();
            $expira = (string)($q->get_result()->fetch_assoc()['expira_en'] ?? '');
            $q->close();
        }
        return ['raw' => $raw, 'id' => $id, 'expira_en' => $expira];
    }

    /**
     * Verifica un token (NO consume aperturas). Usar para servir los binarios
     * incrustados en la pagina de resultados.
     *
     * @return array{ok:bool, motivo:string, informe_id:int, token_id:int,
     *               expira_en:string, usos:int, max_usos:?int}
     *         motivo: ok | invalido | revocado | expirado | agotado
     */
    function eco_token_verificar(mysqli $conex, string $rawToken): array
    {
        $out = ['ok' => false, 'motivo' => 'invalido', 'informe_id' => 0,
                'token_id' => 0, 'expira_en' => '', 'usos' => 0, 'max_usos' => null];

        $raw = trim($rawToken);
        if (!preg_match('/^[a-f0-9]{64}$/', $raw)) {
            return $out;   // formato imposible -> invalido (no toca BD)
        }
        $hash = eco_token_hash($raw);

        $sql = "SELECT id, informe_id, max_usos, usos, revocado,
                       expira_en, (expira_en <= NOW()) AS expirado
                FROM descarga_tokens WHERE token_hash = ?";
        if (!($st = @$conex->prepare($sql))) {
            return $out;
        }
        $st->bind_param('s', $hash);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();

        if (!$row) {
            return $out;   // no existe
        }

        $out['informe_id'] = (int)$row['informe_id'];
        $out['token_id']   = (int)$row['id'];
        $out['expira_en']  = (string)$row['expira_en'];
        $out['usos']       = (int)$row['usos'];
        $out['max_usos']   = $row['max_usos'] === null ? null : (int)$row['max_usos'];

        if ((int)$row['revocado'] === 1) { $out['motivo'] = 'revocado'; return $out; }
        if ((int)$row['expirado'] === 1) { $out['motivo'] = 'expirado'; return $out; }
        if ($out['max_usos'] !== null && $out['usos'] >= $out['max_usos']) {
            $out['motivo'] = 'agotado';
            return $out;
        }
        $out['ok']     = true;
        $out['motivo'] = 'ok';
        return $out;
    }

    /**
     * Abre un token: verifica y, si es valido, lo CONSUME atomicamente
     * (1 apertura). Usar en la carga de la pagina de resultados.
     */
    function eco_token_abrir(mysqli $conex, string $rawToken, string $ip): array
    {
        $est = eco_token_verificar($conex, $rawToken);
        if (!$est['ok']) {
            return $est;
        }
        // Consumo atomico: solo cuenta si sigue valido (evita condiciones de carrera).
        $hash = eco_token_hash(trim($rawToken));
        $sql = "UPDATE descarga_tokens
                SET usos = usos + 1, ultimo_uso_en = NOW(), ultimo_uso_ip = ?
                WHERE token_hash = ? AND revocado = 0 AND expira_en > NOW()
                  AND (max_usos IS NULL OR usos < max_usos)";
        if ($st = @$conex->prepare($sql)) {
            $ipCut = substr($ip, 0, 45);
            $st->bind_param('ss', $ipCut, $hash);
            $st->execute();
            $aff = $st->affected_rows;
            $st->close();
            if ($aff < 1) {                 // perdio la carrera entre verificar y consumir
                $est['ok'] = false;
                $est['motivo'] = 'agotado';
            } else {
                $est['usos']++;
            }
        }
        return $est;
    }

    /** Revoca un token (opcionalmente exigiendo que pertenezca a un informe). */
    function eco_token_revocar(mysqli $conex, int $tokenId, ?int $informeId = null): bool
    {
        if ($informeId !== null) {
            $st = $conex->prepare("UPDATE descarga_tokens SET revocado = 1 WHERE id = ? AND informe_id = ?");
            if (!$st) return false;
            $st->bind_param('ii', $tokenId, $informeId);
        } else {
            $st = $conex->prepare("UPDATE descarga_tokens SET revocado = 1 WHERE id = ?");
            if (!$st) return false;
            $st->bind_param('i', $tokenId);
        }
        $ok  = $st->execute();
        $aff = $st->affected_rows;
        $st->close();
        return $ok && $aff > 0;
    }

    /** Lista los enlaces de un informe (para la UI de gestion). */
    function eco_tokens_de_informe(mysqli $conex, int $informeId): array
    {
        $out = [];
        $sql = "SELECT id, max_usos, usos, revocado, expira_en, creado_en,
                       ultimo_uso_en, (expira_en <= NOW()) AS expirado
                FROM descarga_tokens WHERE informe_id = ?
                ORDER BY creado_en DESC";
        if (!($st = $conex->prepare($sql))) return $out;
        $st->bind_param('i', $informeId);
        $st->execute();
        $res = $st->get_result();
        while ($r = $res->fetch_assoc()) { $out[] = $r; }
        $st->close();
        return $out;
    }

    /** URL absoluta del enlace de resultados a partir del token en claro. */
    function eco_token_url(string $raw): string
    {
        /* Se delega en eco_base_url(), que ya deriva la subcarpeta con eco_url()
           y donde se valida la cabecera Host. Antes esta funcion componia la
           ruta con dirname(SCRIPT_NAME): como el unico llamador vive en api/,
           el enlace salia como .../api/publico/resultado.php y no abria. */
        if (function_exists('eco_base_url')) {
            return eco_base_url() . '/publico/resultado.php?t=' . $raw;
        }
        $https = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? null) == 443);
        $scheme = $https ? 'https' : 'http';
        $host   = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
        $dir    = function_exists('eco_url') ? rtrim(eco_url(''), '/') : '';
        return $scheme . '://' . $host . $dir . '/publico/resultado.php?t=' . $raw;
    }
}
