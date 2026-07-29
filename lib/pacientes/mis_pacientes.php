<?php
/**
 * Listado "Mis Pacientes" del ecografista: consulta, filas y datos de exportación.
 *
 * Vive aquí porque lo usan dos consumidores que deben coincidir exactamente:
 *   - ecografista/mis_pacientes.php      (render inicial de la página)
 *   - api/mis_pacientes_ecografista.php  (refresco periódico de la tabla)
 * Si el marcado se duplicara, el refresco acabaría pintando filas distintas a
 * las del render inicial.
 */

if (!function_exists('eco_mis_pacientes')) {

    /**
     * Pacientes creados por el ecografista o a los que ha atendido en una cita.
     *
     * Con $rangoFechas ([desde, hasta] de eco_rango_atencion) se restringe a los
     * que ATENDIÓ en ese rango: ahí la vía "creado_por_id" no aplica, porque dar
     * de alta a un paciente no es haberlo atendido ese día.
     *
     * @param array{0:string,1:string}|null $rangoFechas
     * @return array<int,array<string,mixed>>
     */
    function eco_mis_pacientes(mysqli $conex, int $ecografistaId, ?array $rangoFechas = null): array
    {
        $campos = "u.id, u.nombre_completo, u.correo, u.cedula, u.direccion, u.telefono, u.fecha_registro,
                   TIMESTAMPDIFF(YEAR, u.fecha_nacimiento, CURDATE()) AS edad,
                   (SELECT COUNT(*) FROM citas c2 WHERE c2.paciente_id=u.id AND c2.ecografista_id=?) AS total_citas,
                   (SELECT COUNT(*) FROM informes_estudios ie WHERE ie.paciente_id=u.id) AS total_informes";

        if ($rangoFechas === null) {
            $sql = "SELECT DISTINCT $campos
                    FROM usuarios u
                    LEFT JOIN citas c ON u.id = c.paciente_id
                    WHERE u.rol='paciente' AND u.estado='aprobado'
                          AND (u.creado_por_id = ? OR c.ecografista_id = ?)
                    ORDER BY u.fecha_registro DESC";
            $params = [$ecografistaId, $ecografistaId, $ecografistaId];
        } else {
            $sql = "SELECT $campos
                    FROM usuarios u
                    WHERE u.rol='paciente' AND u.estado='aprobado'
                      AND EXISTS (SELECT 1 FROM citas cf
                                   WHERE cf.paciente_id = u.id AND cf.ecografista_id = ?
                                     AND cf.estado <> 'cancelada'
                                     AND cf.fecha_cita BETWEEN ? AND ?)
                    ORDER BY u.fecha_registro DESC";
            $params = [$ecografistaId, $ecografistaId, $rangoFechas[0], $rangoFechas[1]];
        }

        // El string de tipos se deriva de los propios parámetros: contarlo a mano
        // es la forma más fácil de descuadrar el bind cuando cambia la consulta.
        $tipos = '';
        foreach ($params as $p) {
            $tipos .= is_int($p) ? 'i' : 's';
        }

        $pacientes = [];
        if ($s = $conex->prepare($sql)) {
            $s->bind_param($tipos, ...$params);
            $s->execute();
            $pacientes = $s->get_result()->fetch_all(MYSQLI_ASSOC);
            $s->close();
        }
        return $pacientes;
    }

    /**
     * Importes de las citas DEL ECOGRAFISTA, con el mismo rango de fechas que la
     * tabla: así "Hoy" muestra lo cobrado hoy por él, no lo de toda la clínica.
     *
     * Se excluyen las citas canceladas, y 'exonerado' no cuenta como pendiente
     * porque no se va a cobrar.
     *
     * @param array{0:string,1:string}|null $rangoFechas
     * @return array{cobrado:float,pendiente:float}
     */
    function eco_mis_pacientes_montos(mysqli $conex, int $ecografistaId, ?array $rangoFechas = null): array
    {
        $sql = "SELECT COALESCE(SUM(c.monto_pagado), 0) AS cobrado,
                       COALESCE(SUM(CASE WHEN c.estado_pago IN ('pendiente','parcial')
                                         THEN GREATEST(COALESCE(c.monto_total, 0) - c.monto_pagado, 0)
                                         ELSE 0 END), 0) AS pendiente
                FROM citas c
                WHERE c.ecografista_id = ? AND c.estado <> 'cancelada'"
            . ($rangoFechas ? " AND c.fecha_cita BETWEEN ? AND ?" : '');

        $out = ['cobrado' => 0.0, 'pendiente' => 0.0];
        if ($s = $conex->prepare($sql)) {
            if ($rangoFechas) {
                $s->bind_param('iss', $ecografistaId, $rangoFechas[0], $rangoFechas[1]);
            } else {
                $s->bind_param('i', $ecografistaId);
            }
            $s->execute();
            if ($row = $s->get_result()->fetch_assoc()) {
                $out = ['cobrado' => (float)$row['cobrado'], 'pendiente' => (float)$row['pendiente']];
            }
            $s->close();
        }
        return $out;
    }

    /** Iniciales (máx. 2) para el avatar de la fila. */
    function eco_mis_pacientes_iniciales(string $nombre): string
    {
        $iniciales = '';
        foreach (explode(' ', trim($nombre)) as $part) {
            if ($part !== '' && strlen($iniciales) < 2) {
                $iniciales .= strtoupper($part[0]);
            }
        }
        return $iniciales !== '' ? $iniciales : '?';
    }

    /**
     * Filas <tr> de la tabla. Devuelve HTML listo para inyectar en el <tbody>.
     */
    function eco_mis_pacientes_filas_html(array $pacientes): string
    {
        $html = '';
        foreach ($pacientes as $p) {
            $iniciales = eco_mis_pacientes_iniciales((string)$p['nombre_completo']);
            $fechaIng  = $p['fecha_registro'] ? date('d/m/Y', strtotime($p['fecha_registro'])) : '—';
            $busqueda  = strtolower($p['nombre_completo'] . ' ' . ($p['cedula'] ?? '') . ' ' . ($p['correo'] ?? '')
                . ' ' . ($p['telefono'] ?? '') . ' ' . ($p['direccion'] ?? ''));

            $sortNombre = htmlspecialchars(mb_strtolower(trim((string)$p['nombre_completo']), 'UTF-8'), ENT_QUOTES, 'UTF-8');
            $cedulaDig  = preg_replace('/\D/', '', (string)($p['cedula'] ?? ''));
            $sortCedula = htmlspecialchars($cedulaDig !== '' ? $cedulaDig : '0', ENT_QUOTES, 'UTF-8');
            $sortEdad   = htmlspecialchars($p['edad'] ? (string)(int)$p['edad'] : '0', ENT_QUOTES, 'UTF-8');
            $sortCorreo = htmlspecialchars(mb_strtolower(trim((string)($p['correo'] ?? '')), 'UTF-8'), ENT_QUOTES, 'UTF-8');
            $sortTel    = htmlspecialchars(mb_strtolower(trim((string)($p['telefono'] ?? '')), 'UTF-8'), ENT_QUOTES, 'UTF-8');
            $sortDir    = htmlspecialchars(mb_strtolower(trim((string)($p['direccion'] ?? '')), 'UTF-8'), ENT_QUOTES, 'UTF-8');
            $sortIng    = $p['fecha_registro']
                ? htmlspecialchars(date('Y-m-d', strtotime($p['fecha_registro'])), ENT_QUOTES, 'UTF-8')
                : '';

            $id      = (int)$p['id'];
            $nomJson = htmlspecialchars(
                json_encode((string)$p['nombre_completo'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP),
                ENT_QUOTES,
                'UTF-8'
            );

            $html .= '<tr class="pac-row" data-pac-id="' . $id . '" data-search="' . htmlspecialchars($busqueda) . '">'
                . '<td class="rx-pac-td-nombre" data-sort-value="' . $sortNombre . '">'
                . '<div class="rx-pac-cell-nombre">'
                . '<span class="rx-pac-avatar" aria-hidden="true">' . htmlspecialchars($iniciales) . '</span>'
                . '<strong>' . htmlspecialchars($p['nombre_completo']) . '</strong>'
                . '</div></td>'
                . '<td class="rx-pac-td-cedula" data-sort-value="' . $sortCedula . '">' . htmlspecialchars($p['cedula'] ?: '—') . '</td>'
                . '<td class="rx-pac-td-edad" data-sort-value="' . $sortEdad . '">' . ($p['edad'] ? (int)$p['edad'] . ' años' : '—') . '</td>'
                . '<td class="rx-pac-td-email" data-sort-value="' . $sortCorreo . '">' . htmlspecialchars($p['correo'] ?: '—') . '</td>'
                . '<td class="rx-pac-td-telefono" data-sort-value="' . $sortTel . '">' . htmlspecialchars($p['telefono'] ?: '—') . '</td>'
                . '<td class="rx-pac-td-direccion" data-sort-value="' . $sortDir . '">' . htmlspecialchars($p['direccion'] ?: '—') . '</td>'
                . '<td><span class="badge badge-accent">' . (int)$p['total_citas'] . '</span></td>'
                . '<td><span class="badge badge-purple">' . (int)$p['total_informes'] . '</span></td>'
                . '<td class="rx-pac-td-ingreso" data-sort-value="' . $sortIng . '">' . htmlspecialchars($fechaIng) . '</td>'
                . '<td class="rx-td-acciones" style="white-space:nowrap;">'
                . '<div style="display:inline-flex;gap:6px;align-items:center;justify-content:flex-end;">'
                . '<button type="button" onclick="abrirGestionPacienteEco(' . $id . ')" class="btn-primary" style="padding:6px 12px;font-size:12px;">'
                . '<i class="fa-solid fa-folder-open"></i> Gestionar</button>'
                . '<button type="button" onclick="abrirProgramarCitaEco(' . $id . ', ' . $nomJson . ')" class="btn-secondary" style="padding:6px 12px;font-size:12px;">'
                . '<i class="fa-solid fa-calendar-plus"></i> Cita</button>'
                . '</div></td>'
                . '</tr>';
        }
        return $html;
    }

    /**
     * Filas para "Exportar a Excel" (mismas columnas que la tabla).
     *
     * @return array<int,array<string,mixed>>
     */
    function eco_mis_pacientes_export(array $pacientes): array
    {
        return array_map(static function (array $p): array {
            return [
                'Nombre'    => (string)$p['nombre_completo'],
                'Cédula'    => (string)($p['cedula'] ?: ''),
                'Edad'      => $p['edad'] ? (int)$p['edad'] : '',
                'Correo'    => (string)($p['correo'] ?: ''),
                'Teléfono'  => (string)($p['telefono'] ?: ''),
                'Dirección' => (string)($p['direccion'] ?: ''),
                'Citas'     => (int)$p['total_citas'],
                'Informes'  => (int)$p['total_informes'],
                'Ingreso'   => $p['fecha_registro'] ? date('d/m/Y', strtotime($p['fecha_registro'])) : '',
            ];
        }, $pacientes);
    }
}
