<?php
/**
 * Historia clínica de un paciente: citas, informes de estudio y notas de sesión.
 *
 * Lo usan:
 *   - api/get_historia_clinica.php        (línea de tiempo unificada, JSON)
 *   - recepcion/recepcion_ficha_paciente.php (ficha completa, por secciones)
 *
 * Está aquí porque ambas vistas muestran los MISMOS hechos clínicos: si cada
 * una hiciera su consulta, una podría contar una cita que la otra no, y no hay
 * forma peor de perder la confianza en una historia clínica.
 *
 * Cada función devuelve eventos con la misma forma, para que la línea de
 * tiempo sea solo la mezcla de las tres listas.
 */

require_once __DIR__ . '/../facturacion/facturacion.php';

if (!function_exists('eco_paciente_ficha')) {

    /**
     * Datos del paciente para su ficha. Devuelve null si no existe o no es
     * un paciente.
     */
    function eco_paciente_ficha(mysqli $conex, int $pacienteId): ?array
    {
        $sql = "SELECT u.id, u.nombre_completo, u.cedula, u.correo, u.telefono, u.direccion,
                       u.fecha_nacimiento, u.fecha_registro, u.ultimo_acceso, u.estado,
                       u.email_verificado, u.two_factor_enabled,
                       TIMESTAMPDIFF(YEAR, u.fecha_nacimiento, CURDATE()) AS edad,
                       creador.nombre_completo AS creado_por
                  FROM usuarios u
                  LEFT JOIN usuarios creador ON creador.id = u.creado_por_id
                 WHERE u.id = ? AND u.rol = 'paciente'";
        if (!($st = $conex->prepare($sql))) {
            return null;
        }
        $st->bind_param('i', $pacienteId);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();

        return $row ?: null;
    }

    /**
     * Importe suelto dentro de un texto libre: los costos históricos viven en
     * motivo_principal (p. ej. "… Total $40"). Se toma el último.
     */
    function eco_historia_costo_texto(?string $texto): ?float
    {
        if (!$texto) {
            return null;
        }
        if (preg_match_all('/\$\s*([0-9]+(?:[.,][0-9]{1,2})?)/', $texto, $m) && !empty($m[1])) {
            return (float)str_replace(',', '.', end($m[1]));
        }
        return null;
    }

    /** Informes de estudio del paciente, del más reciente al más antiguo. */
    function eco_historia_informes(mysqli $conex, int $pacienteId): array
    {
        $sql = "SELECT ie.id, ie.numero_informe, ie.estado,
                       COALESCE(ie.fecha_estudio, DATE(ie.creado_en)) AS fecha,
                       ie.creado_en, t.nombre AS tipo_nombre, t.categoria AS tipo_categoria,
                       u.nombre_completo AS ecografista
                  FROM informes_estudios ie
                  LEFT JOIN tipos_ecografias t ON t.id = ie.tipo_ecografia_id
                  LEFT JOIN usuarios u ON u.id = ie.ecografista_id
                 WHERE ie.paciente_id = ?";
        $filas = [];
        if ($st = $conex->prepare($sql)) {
            $st->bind_param('i', $pacienteId);
            $st->execute();
            $rs = $st->get_result();
            while ($r = $rs->fetch_assoc()) {
                $filas[] = [
                    'tipo'        => 'informe',
                    'id'          => (int)$r['id'],
                    'fecha'       => $r['fecha'] ?: substr((string)$r['creado_en'], 0, 10),
                    'fecha_orden' => $r['fecha'] ? $r['fecha'] . ' 00:00:00' : $r['creado_en'],
                    'titulo'      => $r['tipo_nombre'] ?: 'Informe de estudio',
                    'categoria'   => $r['tipo_categoria'] ?: '',
                    'estado'      => $r['estado'],
                    'numero'      => $r['numero_informe'] ?: '',
                    'profesional' => $r['ecografista'] ?: '',
                    'detalle'     => '',
                ];
            }
            $st->close();
        }
        return eco_historia_ordenar($filas);
    }

    /** Notas de sesión del paciente, de la más reciente a la más antigua. */
    function eco_historia_notas(mysqli $conex, int $pacienteId, int $recorte = 240): array
    {
        $sql = "SELECT nc.id, nc.fecha_sesion, nc.contenido, u.nombre_completo AS ecografista
                  FROM notas_clinicas nc
                  LEFT JOIN usuarios u ON u.id = nc.ecografista_id
                 WHERE nc.paciente_id = ?";
        $filas = [];
        if ($st = $conex->prepare($sql)) {
            $st->bind_param('i', $pacienteId);
            $st->execute();
            $rs = $st->get_result();
            while ($r = $rs->fetch_assoc()) {
                $texto = (string)$r['contenido'];
                $filas[] = [
                    'tipo'        => 'nota',
                    'id'          => (int)$r['id'],
                    'fecha'       => substr((string)$r['fecha_sesion'], 0, 10),
                    'fecha_orden' => $r['fecha_sesion'],
                    'titulo'      => 'Nota de sesión',
                    'categoria'   => '',
                    'estado'      => '',
                    'numero'      => '',
                    'profesional' => $r['ecografista'] ?: '',
                    // El recorte es para la línea de tiempo; 0 = texto completo.
                    'detalle'     => $recorte > 0 ? mb_substr($texto, 0, $recorte) : $texto,
                    'recortada'   => $recorte > 0 && mb_strlen($texto) > $recorte,
                ];
            }
            $st->close();
        }
        return eco_historia_ordenar($filas);
    }

    /** Citas del paciente, de la más reciente a la más antigua, con su cobro. */
    function eco_historia_citas(mysqli $conex, int $pacienteId): array
    {
        $sql = "SELECT c.id, c.fecha_cita, c.fecha_solicitud, c.estado, c.motivo_consulta,
                       c.motivo_principal, c.modalidad, c.tipo_cita,
                       c.monto_total, c.monto_pagado, c.estado_pago, c.metodo_pago,
                       t.nombre AS tipo_nombre, u.nombre_completo AS ecografista
                  FROM citas c
                  LEFT JOIN tipos_ecografias t ON t.id = c.tipo_ecografia_id
                  LEFT JOIN usuarios u ON u.id = c.ecografista_id
                 WHERE c.paciente_id = ?";
        $filas = [];
        if ($st = $conex->prepare($sql)) {
            $st->bind_param('i', $pacienteId);
            $st->execute();
            $rs = $st->get_result();
            while ($r = $rs->fetch_assoc()) {
                $fechaRef = $r['fecha_cita'] ?: $r['fecha_solicitud'];

                // Facturación real si existe; si no, el importe histórico del texto.
                $mt    = $r['monto_total'] !== null ? (float)$r['monto_total'] : null;
                $mp    = (float)$r['monto_pagado'];
                $costo = $mt !== null ? $mt : eco_historia_costo_texto($r['motivo_principal']);

                $estudios = eco_estudios_desde_texto($r['motivo_principal'] ?? '');

                $filas[] = [
                    'tipo'        => 'cita',
                    'id'          => (int)$r['id'],
                    'fecha'       => substr((string)$fechaRef, 0, 10),
                    'fecha_orden' => $fechaRef,
                    'hora'        => $r['fecha_cita'] ? substr((string)$r['fecha_cita'], 11, 5) : '',
                    'titulo'      => $estudios ? implode(', ', $estudios) : ($r['tipo_nombre'] ?: 'Cita'),
                    'categoria'   => '',
                    'estado'      => $r['estado'],
                    'numero'      => '',
                    'profesional' => $r['ecografista'] ?: '',
                    'detalle'     => mb_substr((string)($r['motivo_consulta'] ?? ''), 0, 240),
                    'servicios'   => trim((string)($r['motivo_principal'] ?? '')),
                    'modalidad'   => $r['modalidad'] ? ucfirst($r['modalidad']) : '',
                    'tipo_cita'   => $r['tipo_cita'] ? ucwords(str_replace('_', ' ', $r['tipo_cita'])) : '',
                    'costo'       => $costo,
                    'costo_fmt'   => $costo !== null ? eco_money($costo) : '',
                    'pagado'      => $mt !== null ? $mp : null,
                    'pagado_fmt'  => $mt !== null ? eco_money($mp) : '',
                    'saldo'       => $mt !== null ? max($mt - $mp, 0) : null,
                    'saldo_fmt'   => $mt !== null ? eco_money(max($mt - $mp, 0)) : '',
                    'pago_estado' => $mt !== null ? $r['estado_pago'] : '',
                    'pago_label'  => $mt !== null ? eco_estado_pago_label($r['estado_pago']) : '',
                    'metodo_pago' => (string)($r['metodo_pago'] ?? ''),
                ];
            }
            $st->close();
        }
        return eco_historia_ordenar($filas);
    }

    /** Orden cronológico descendente (lo más reciente arriba). */
    function eco_historia_ordenar(array $eventos): array
    {
        usort($eventos, static function ($a, $b) {
            return strcmp((string)$b['fecha_orden'], (string)$a['fecha_orden']);
        });
        return $eventos;
    }

    /** Etiqueta legible del estado de una cita. */
    function eco_cita_estado_label(string $estado): string
    {
        return [
            'pendiente'          => 'Pendiente',
            'pendiente_paciente' => 'Espera al paciente',
            'confirmada'         => 'Confirmada',
            'reprogramada'       => 'Reprogramada',
            'completada'         => 'Completada',
            'cancelada'          => 'Cancelada',
            'rechazada'          => 'Rechazada',
            'no_asistio'         => 'No asistió',
        ][$estado] ?? ucfirst(str_replace('_', ' ', $estado));
    }

    /**
     * Línea de tiempo unificada de los tres orígenes, con el resumen y el
     * total facturado.
     *
     * @return array{eventos:array,resumen:array,costo_total:float}
     */
    function eco_historia_clinica(mysqli $conex, int $pacienteId): array
    {
        $citas    = eco_historia_citas($conex, $pacienteId);
        $informes = eco_historia_informes($conex, $pacienteId);
        $notas    = eco_historia_notas($conex, $pacienteId);

        $costoTotal = 0.0;
        foreach ($citas as $c) {
            if ($c['costo'] !== null) {
                $costoTotal += (float)$c['costo'];
            }
        }

        return [
            'eventos'     => eco_historia_ordenar(array_merge($citas, $informes, $notas)),
            'resumen'     => [
                'informes' => count($informes),
                'notas'    => count($notas),
                'citas'    => count($citas),
            ],
            'costo_total' => $costoTotal,
        ];
    }
}
