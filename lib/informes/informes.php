<?php
/**
 * Helpers del nucleo clinico (informes_estudios): numeracion correlativa,
 * transiciones de estado y reglas de permisos.
 */

if (!function_exists('eco_siguiente_numero_informe')) {
    /**
     * Devuelve el siguiente numero de informe correlativo del anio en curso,
     * con formato INF-AAAA-NNNNN. Atomico y libre de carreras: usa el contador
     * por-conexion LAST_INSERT_ID(). Debe llamarse dentro de la transaccion que
     * inserta/finaliza el informe para no "quemar" numeros si algo falla.
     */
    function eco_siguiente_numero_informe(mysqli $conex): string
    {
        $anio  = (int)date('Y');
        $clave = 'informe_' . $anio;

        $stmt = $conex->prepare(
            "INSERT INTO contadores (clave, valor) VALUES (?, LAST_INSERT_ID(1))
             ON DUPLICATE KEY UPDATE valor = LAST_INSERT_ID(valor + 1)"
        );
        $stmt->bind_param('s', $clave);
        $stmt->execute();
        $stmt->close();

        $seq = (int)$conex->insert_id;
        return sprintf('INF-%d-%05d', $anio, $seq);
    }
}

if (!function_exists('eco_informe_estado_label')) {
    /** Etiqueta legible de un estado de informe. */
    function eco_informe_estado_label(string $estado): string
    {
        return [
            'borrador'   => 'Borrador',
            'finalizado' => 'Finalizado',
            'firmado'    => 'Firmado',
            'anulado'    => 'Anulado',
        ][$estado] ?? ucfirst($estado);
    }
}

if (!function_exists('eco_puede_gestionar_informe')) {
    /**
     * Reglas de autoria: un administrador puede gestionar cualquier informe;
     * un ecografista solo los que el creo.
     */
    function eco_puede_gestionar_informe(string $rol, int $usuarioId, int $ecografistaIdInforme): bool
    {
        if ($rol === 'administrador') {
            return true;
        }
        return $rol === 'ecografista' && $usuarioId === $ecografistaIdInforme;
    }

    /**
     * ¿Puede este usuario ver el CONTENIDO CLINICO de un informe (hallazgos,
     * mediciones, conclusiones)? Distinto de gestionarlo y distinto de ver sus
     * datos administrativos (numero, fecha, estado, tipo, paciente).
     *
     * Criterio de "minimo necesario":
     *   - administrador : si (responsable del sistema).
     *   - ecografista   : si (personal clinico).
     *   - paciente      : solo lo suyo, y solo finalizado o firmado.
     *   - recepcionista : NO. Agenda y cobra; para eso le bastan los datos
     *                     administrativos. Los hallazgos no le hacen falta.
     *
     * Hay DOS vias al contenido clinico —la modal (api/get_informe_detalle.php)
     * y la version imprimible (informes/ver_informe_estudio.php)—: las dos deben
     * preguntar aqui, o cerrar una sola no sirve de nada.
     */
    function eco_puede_ver_clinico(
        string $rol,
        int $usuarioId,
        int $pacienteIdInforme,
        string $estadoInforme,
        ?mysqli $conex = null,
        int $ecografistaIdInforme = 0
    ): bool {
        if ($rol === 'administrador') {
            return true;
        }
        if ($rol === 'ecografista') {
            // Su propio informe, siempre. Si no, solo si el paciente está bajo
            // su atención (lo registró él, o tienen cita/informe en común).
            if ($ecografistaIdInforme > 0 && $usuarioId === $ecografistaIdInforme) {
                return true;
            }
            if ($conex === null) {
                return false;           // sin conexión no se puede comprobar: no se concede
            }
            require_once __DIR__ . '/../pacientes/mis_pacientes.php';
            // Incluye el permiso excepcional vigente ("romper el cristal"): si
            // el ecografista ya justificó el acceso a ese paciente, el informe
            // entra en lo que puede consultar.
            return eco_ecografista_puede_ver_paciente($conex, $usuarioId, $pacienteIdInforme);
        }
        if ($rol === 'paciente') {
            return $usuarioId === $pacienteIdInforme
                && in_array($estadoInforme, ['finalizado', 'firmado'], true);
        }
        return false;   // recepcionista y cualquier otro rol
    }
}
