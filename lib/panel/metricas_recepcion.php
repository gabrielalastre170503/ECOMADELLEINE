<?php
/**
 * Métricas del Panel de Control de recepción.
 *
 * Se agrupan aquí para que la vista no lleve SQL suelto y para poder probarlas
 * contra la BD sin renderizar la página.
 *
 * Criterio común: una cita cuenta como atención si NO está cancelada. El dinero
 * "cobrado" es monto_pagado (lo que entró en caja), no lo facturado.
 */

require_once __DIR__ . '/../facturacion/facturacion.php';

if (!function_exists('eco_panel_rx_serie_diaria')) {

    /**
     * Pacientes atendidos y dinero cobrado por día, para los últimos $dias días.
     * Devuelve la serie COMPLETA: los días sin actividad van con 0, si no el
     * gráfico mentiría sobre el ritmo de la clínica.
     *
     * @return array<int,array{fecha:string,etiqueta:string,pacientes:int,cobrado:float}>
     */
    function eco_panel_rx_serie_diaria(mysqli $conex, int $dias = 14): array
    {
        $dias = max(1, min(90, $dias));

        $datos = [];
        $sql = "SELECT DATE(fecha_cita) d,
                       COUNT(DISTINCT paciente_id) pacientes,
                       COALESCE(SUM(monto_pagado), 0) cobrado
                  FROM citas
                 WHERE estado <> 'cancelada'
                   AND fecha_cita >= (CURDATE() - INTERVAL ? DAY)
                   AND fecha_cita < (CURDATE() + INTERVAL 1 DAY)
                 GROUP BY DATE(fecha_cita)";
        $desfase = $dias - 1;
        if ($s = $conex->prepare($sql)) {
            $s->bind_param('i', $desfase);
            $s->execute();
            $res = $s->get_result();
            while ($row = $res->fetch_assoc()) {
                $datos[(string)$row['d']] = [
                    'pacientes' => (int)$row['pacientes'],
                    'cobrado'   => (float)$row['cobrado'],
                ];
            }
            $s->close();
        }

        $meses = ['', 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
        $serie = [];
        $hoy = new DateTimeImmutable('today');
        for ($i = $desfase; $i >= 0; $i--) {
            $f = $hoy->modify("-$i days");
            $clave = $f->format('Y-m-d');
            $serie[] = [
                'fecha'     => $clave,
                'etiqueta'  => $f->format('j') . ' ' . $meses[(int)$f->format('n')],
                'pacientes' => $datos[$clave]['pacientes'] ?? 0,
                'cobrado'   => $datos[$clave]['cobrado'] ?? 0.0,
            ];
        }
        return $serie;
    }

    /**
     * Totales del día de hoy y del mes en curso.
     *
     * @return array{hoy_pacientes:int,hoy_cobrado:float,mes_pacientes:int,mes_cobrado:float,pendiente:float}
     */
    function eco_panel_rx_totales(mysqli $conex): array
    {
        $out = ['hoy_pacientes' => 0, 'hoy_cobrado' => 0.0, 'mes_pacientes' => 0, 'mes_cobrado' => 0.0, 'pendiente' => 0.0];

        $sql = "SELECT COUNT(DISTINCT CASE WHEN DATE(fecha_cita) = CURDATE() THEN paciente_id END) hoy_pac,
                       COALESCE(SUM(CASE WHEN DATE(fecha_cita) = CURDATE() THEN monto_pagado END), 0) hoy_cob,
                       COUNT(DISTINCT CASE WHEN YEAR(fecha_cita) = YEAR(CURDATE())
                                            AND MONTH(fecha_cita) = MONTH(CURDATE()) THEN paciente_id END) mes_pac,
                       COALESCE(SUM(CASE WHEN YEAR(fecha_cita) = YEAR(CURDATE())
                                          AND MONTH(fecha_cita) = MONTH(CURDATE()) THEN monto_pagado END), 0) mes_cob,
                       COALESCE(SUM(CASE WHEN estado_pago IN ('pendiente','parcial')
                                         THEN GREATEST(COALESCE(monto_total, 0) - monto_pagado, 0) END), 0) pendiente
                  FROM citas
                 WHERE estado <> 'cancelada'";
        if ($r = $conex->query($sql)) {
            $row = $r->fetch_assoc() ?: [];
            $out = [
                'hoy_pacientes' => (int)($row['hoy_pac'] ?? 0),
                'hoy_cobrado'   => (float)($row['hoy_cob'] ?? 0),
                'mes_pacientes' => (int)($row['mes_pac'] ?? 0),
                'mes_cobrado'   => (float)($row['mes_cob'] ?? 0),
                'pendiente'     => (float)($row['pendiente'] ?? 0),
            ];
            $r->free();
        }
        return $out;
    }

    /**
     * Pacientes por rango de edad. "Sin registrar" se devuelve aparte y NO como
     * una barra más: es un hueco de datos, no un grupo de edad.
     *
     * @return array{filas:array<int,array{rango:string,n:int}>,sin_fecha:int,total:int}
     */
    function eco_panel_rx_por_edad(mysqli $conex): array
    {
        $rangos = ['0-17' => 0, '18-29' => 0, '30-44' => 0, '45-59' => 0, '60+' => 0];
        $sinFecha = 0;
        $total = 0;

        $sql = "SELECT TIMESTAMPDIFF(YEAR, fecha_nacimiento, CURDATE()) e
                  FROM usuarios WHERE rol = 'paciente' AND estado = 'aprobado'";
        if ($r = $conex->query($sql)) {
            while ($row = $r->fetch_assoc()) {
                $total++;
                $e = $row['e'];
                if ($e === null) { $sinFecha++; continue; }
                $e = (int)$e;
                if ($e < 18)      { $rangos['0-17']++; }
                elseif ($e < 30)  { $rangos['18-29']++; }
                elseif ($e < 45)  { $rangos['30-44']++; }
                elseif ($e < 60)  { $rangos['45-59']++; }
                else              { $rangos['60+']++; }
            }
            $r->free();
        }

        $filas = [];
        foreach ($rangos as $rango => $n) {
            $filas[] = ['rango' => $rango, 'n' => $n];
        }
        return ['filas' => $filas, 'sin_fecha' => $sinFecha, 'total' => $total];
    }
}
