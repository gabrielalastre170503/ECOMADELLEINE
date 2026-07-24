<?php
/**
 * Helpers de facturacion: estado de pago, formato monetario, etiquetas.
 */

if (!function_exists('eco_estado_pago')) {
    /**
     * Calcula el estado de pago a partir del total y lo pagado.
     * 'exonerado' es un estado manual (no se deduce aqui).
     */
    function eco_estado_pago(?float $total, float $pagado): string
    {
        $total = (float)($total ?? 0);
        if ($total <= 0) {
            return $pagado > 0 ? 'pagado' : 'pendiente';
        }
        if ($pagado <= 0) {
            return 'pendiente';
        }
        if ($pagado + 0.001 >= $total) {
            return 'pagado';
        }
        return 'parcial';
    }
}

if (!function_exists('eco_total_desde_texto')) {
    /**
     * Extrae el ultimo importe "$X" de un texto libre (los bundles de servicios
     * guardan el total acordado en motivo_principal, p. ej. "... Total $50").
     * Devuelve float o null si no hay importe.
     */
    function eco_total_desde_texto(?string $texto): ?float
    {
        if (!$texto) {
            return null;
        }
        if (preg_match_all('/\$\s*([0-9]+(?:[.,][0-9]{1,2})?)/', $texto, $m) && !empty($m[1])) {
            $ultimo = end($m[1]);
            return (float)str_replace(',', '.', $ultimo);
        }
        return null;
    }
}

if (!function_exists('eco_money')) {
    /** Formatea un importe como "$20" o "$20.50" (sin decimales si son .00). */
    function eco_money(?float $n): string
    {
        $n = (float)($n ?? 0);
        $s = number_format($n, 2, '.', ',');
        $s = preg_replace('/\.00$/', '', $s);
        return '$' . $s;
    }
}

if (!function_exists('eco_estado_pago_label')) {
    function eco_estado_pago_label(string $estado): string
    {
        return [
            'pendiente'  => 'Pendiente',
            'parcial'    => 'Pago parcial',
            'pagado'     => 'Pagado',
            'exonerado'  => 'Exonerado',
        ][$estado] ?? ucfirst($estado);
    }
}

if (!function_exists('eco_estado_pago_color')) {
    /** [textColor, bgColor] para badges. */
    function eco_estado_pago_color(string $estado): array
    {
        return [
            'pendiente' => ['#b45309', '#fef3c7'],
            'parcial'   => ['#9a3412', '#ffedd5'],
            'pagado'    => ['#15803d', '#dcfce7'],
            'exonerado' => ['#475569', '#f1f5f9'],
        ][$estado] ?? ['#374151', '#f3f4f6'];
    }
}

if (!function_exists('eco_metodos_pago')) {
    /** Metodos de pago aceptados. */
    function eco_metodos_pago(): array
    {
        return ['Efectivo', 'Pago móvil', 'Transferencia', 'Punto de venta', 'Divisas', 'Zelle'];
    }
}

if (!function_exists('eco_servicios_adicionales')) {
    /**
     * Catalogo de servicios adicionales combinables (sin ecografia). Mismas
     * claves/precios que usa el flujo del paciente (solicitar_cita_paciente.php),
     * centralizados aqui para que el flujo del ecografista calcule igual.
     *  - 'combo_cito' ya incluye Citologia + Procesamiento + Eco pelvico.
     */
    function eco_servicios_adicionales(): array
    {
        return [
            ['key' => 'consulta',      'label' => 'Consulta médica',                        'price' => 15, 'icon' => 'fa-stethoscope'],
            ['key' => 'citologia',     'label' => 'Citología médica',                       'price' => 20, 'icon' => 'fa-vial'],
            ['key' => 'procesamiento', 'label' => 'Procesamiento de muestra',               'price' => 3,  'icon' => 'fa-microscope'],
            ['key' => 'combo_cito',    'label' => 'Procesamiento, Citologia + Eco pélvico',  'price' => 25, 'icon' => 'fa-flask-vial'],
        ];
    }
}

if (!function_exists('eco_servicios_desde_texto')) {
    /**
     * Infiere que servicios adicionales (claves) estan presentes en un texto de
     * motivo_principal. El texto se construyo a partir de las mismas etiquetas del
     * catalogo, asi que se detectan por substring. Si esta el combo, se omiten sus
     * componentes sueltos (citologia/procesamiento) por estar ya incluidos.
     *
     * @return string[] claves de servicios detectadas
     */
    function eco_servicios_desde_texto(?string $motivo): array
    {
        if (!$motivo) {
            return [];
        }
        $keys = [];
        foreach (eco_servicios_adicionales() as $s) {
            if (mb_stripos($motivo, $s['label']) !== false) {
                $keys[] = $s['key'];
            }
        }
        // La promo "Eco + Consulta" implica consulta aunque no aparezca la etiqueta suelta.
        if (mb_stripos($motivo, 'Eco + Consulta') !== false && !in_array('consulta', $keys, true)) {
            $keys[] = 'consulta';
        }
        // La promo "Combo ..." implica el combo.
        if (mb_stripos($motivo, 'Combo Citolog') !== false && !in_array('combo_cito', $keys, true)) {
            $keys[] = 'combo_cito';
        }
        if (in_array('combo_cito', $keys, true)) {
            $keys = array_values(array_filter(
                $keys,
                static fn($k) => !in_array($k, ['citologia', 'procesamiento'], true)
            ));
        }
        return array_values(array_unique($keys));
    }
}

if (!function_exists('eco_estudios_desde_texto')) {
    /**
     * Extrae los nombres de estudios del segmento inicial "Ecografías:/Estudio:"
     * de un motivo_principal. Devuelve [] si no existe ese prefijo (citas viejas).
     *
     * @return string[]
     */
    function eco_estudios_desde_texto(?string $motivo): array
    {
        if (!$motivo) {
            return [];
        }
        $seg = explode(' · ', $motivo)[0];
        if (preg_match('/^\s*(?:Ecograf[ií]as?|Estudios?)\s*:\s*(.+)$/iu', $seg, $m)) {
            $items = array_map('trim', explode(',', $m[1]));
            return array_values(array_filter($items, static fn($s) => $s !== ''));
        }
        return [];
    }
}

if (!function_exists('eco_calcular_bundle_multi')) {
    /**
     * Version multi-estudio del calculo de bundle. Aplica las mismas promociones
     * que el flujo del paciente sobre VARIOS estudios:
     *   - Combo Citología + Procesamiento + Eco pélvico = $25 (excluye sus sueltos).
     *   - Promoción Eco + Consulta = $25 (la ecografia mas cara va con la consulta;
     *     el resto de estudios se cobra a precio completo).
     *
     * @param array<int,array{nombre:string,precio:float}> $estudios
     * @param string[] $serviciosKeys
     * @return array{total:float,motivo:string,promos:string[],ahorro:float}
     */
    function eco_calcular_bundle_multi(array $estudios, array $serviciosKeys): array
    {
        $cat = [];
        foreach (eco_servicios_adicionales() as $s) {
            $cat[$s['key']] = $s;
        }
        $keys = array_values(array_unique(array_filter(
            $serviciosKeys,
            static fn($k) => is_string($k) && isset($cat[$k])
        )));

        $combo    = in_array('combo_cito', $keys, true);
        $consulta = in_array('consulta', $keys, true);

        $nombres = [];
        $precios = [];
        foreach ($estudios as $e) {
            $nom = trim((string)($e['nombre'] ?? ''));
            if ($nom === '') {
                continue;
            }
            $nombres[] = $nom;
            $precios[] = (float)($e['precio'] ?? 0);
        }

        $total   = 0.0;
        $ahorro  = 0.0;
        $promos  = [];
        $sueltos = [];

        foreach ($keys as $k) {
            if ($k === 'consulta' || $k === 'combo_cito') {
                continue;
            }
            if ($combo && in_array($k, ['citologia', 'procesamiento'], true)) {
                continue;
            }
            $total    += (float)$cat[$k]['price'];
            $sueltos[] = $cat[$k]['label'];
        }

        if ($combo) {
            $total  += 25.0;
            $ahorro += (20 + 3 + 15) - 25;
            $promos[] = 'Combo Citología + Procesamiento + Eco pélvico';
        }

        if ($consulta && count($precios) >= 1) {
            rsort($precios); // descendente: la mas cara primero
            $maxEco = $precios[0];
            $resto  = array_sum(array_slice($precios, 1));
            $total  += 25.0 + $resto; // Eco mas cara + Consulta = 25; resto a precio pleno
            $ahorro += ($maxEco + 15) - 25;
            $promos[] = 'Promoción Eco + Consulta';
        } else {
            $total += array_sum($precios);
            if ($consulta) {
                $total += 15.0;
            }
        }

        if ($ahorro < 0) {
            $ahorro = 0.0;
        }

        $parts = [];
        if ($nombres) {
            $parts[] = (count($nombres) > 1 ? 'Ecografías: ' : 'Estudio: ') . implode(', ', $nombres);
        }
        $adicLabels = $sueltos;
        if ($consulta) {
            $adicLabels[] = $cat['consulta']['label'];
        }
        if ($combo) {
            $adicLabels[] = $cat['combo_cito']['label'];
        }
        if ($adicLabels) {
            $parts[] = implode(', ', $adicLabels);
        }
        if ($promos) {
            $parts[] = implode(' · ', $promos);
        }
        $parts[] = 'Total ' . eco_money($total);

        return [
            'total'  => round($total, 2),
            'motivo' => mb_substr(implode(' · ', $parts), 0, 250),
            'promos' => $promos,
            'ahorro' => round($ahorro, 2),
        ];
    }
}

if (!function_exists('eco_calcular_bundle')) {
    /**
     * Calcula el total y el texto "motivo_principal" de un estudio + servicios
     * adicionales, aplicando las mismas promociones que el flujo del paciente:
     *   - Combo Citología + Procesamiento + Eco pélvico = $25 (excluye sus sueltos).
     *   - Promoción Eco + Consulta = $25 (sustituye precio del estudio + consulta).
     *
     * @param float    $ecoPrecio  precio del estudio elegido (0 si ninguno)
     * @param string   $ecoNombre  nombre del estudio (vacio si ninguno)
     * @param string[] $serviciosKeys  claves de servicios adicionales seleccionados
     * @return array{total:float,motivo:string,promos:string[],ahorro:float}
     */
    function eco_calcular_bundle(float $ecoPrecio, string $ecoNombre, array $serviciosKeys): array
    {
        $estudios = [];
        if (trim($ecoNombre) !== '' || $ecoPrecio > 0) {
            $estudios[] = ['nombre' => $ecoNombre, 'precio' => $ecoPrecio];
        }
        return eco_calcular_bundle_multi($estudios, $serviciosKeys);
    }
}

if (!function_exists('eco_facturar_cita_reuso')) {
    /**
     * Asienta la facturacion de un estudio + servicios reusando la cita "abierta"
     * mas reciente del paciente con ese ecografista (sin pago registrado). Si no
     * existe ninguna, crea una cita presencial/completada como contenedor de cobro.
     * El precio del estudio se lee de la BD (autoritativo, no se confia en el cliente).
     *
     * @param string[] $serviciosKeys  claves de servicios adicionales
     * @return array{0:int,1:float}|null  [cita_id, total] o null si no hay nada que facturar
     */
    function eco_facturar_cita_reuso(mysqli $conex, int $pacienteId, int $ecografistaId, int $tipoEcoId, array $serviciosKeys): ?array
    {
        // Estudio que el ecografista esta realizando ahora.
        $ecoPrecio = 0.0;
        $ecoNombre = '';
        if ($tipoEcoId > 0 && ($q = $conex->prepare("SELECT nombre, precio FROM tipos_ecografias WHERE id = ?"))) {
            $q->bind_param('i', $tipoEcoId);
            $q->execute();
            if ($row = $q->get_result()->fetch_assoc()) {
                $ecoNombre = (string)$row['nombre'];
                $ecoPrecio = (float)$row['precio'];
            }
            $q->close();
        }

        // Cita abierta reusable (sin pago). Se prefiere la cita de HOY: asi todos los
        // estudios del mismo dia se agrupan en una sola cita (el walk-in crea su
        // contenedor "completada" hoy y los siguientes estudios se le unen). Si no
        // hay ninguna de hoy, se reusa la mas reciente (la cita agendada del paciente).
        // 1) Cita de HOY (cualquier estado de pago): asi todos los estudios y servicios
        //    del mismo dia se consolidan en una sola cita y un servicio (ej. consulta)
        //    no se cobra dos veces. 2) Si no hay de hoy, se reusa la mas reciente SIN
        //    pago (la cita agendada del paciente).
        $citaId       = 0;
        $motivoPrevio = '';
        $pagadoPrevio = 0.0;
        $buscarCita = static function (string $sql) use ($conex, $pacienteId, $ecografistaId, &$citaId, &$motivoPrevio, &$pagadoPrevio) {
            if ($sel = $conex->prepare($sql)) {
                $sel->bind_param('ii', $pacienteId, $ecografistaId);
                $sel->execute();
                if ($r = $sel->get_result()->fetch_assoc()) {
                    $citaId       = (int)$r['id'];
                    $motivoPrevio = (string)($r['motivo_principal'] ?? '');
                    $pagadoPrevio = (float)($r['monto_pagado'] ?? 0);
                }
                $sel->close();
            }
        };
        $buscarCita(
            "SELECT id, motivo_principal, monto_pagado FROM citas
              WHERE paciente_id = ? AND ecografista_id = ? AND estado <> 'cancelada'
                AND DATE(COALESCE(fecha_cita, fecha_solicitud)) = CURDATE()
              ORDER BY COALESCE(fecha_cita, fecha_solicitud) DESC, id DESC
              LIMIT 1"
        );
        if ($citaId === 0) {
            $buscarCita(
                "SELECT id, motivo_principal, monto_pagado FROM citas
                  WHERE paciente_id = ? AND ecografista_id = ? AND monto_pagado = 0 AND estado <> 'cancelada'
                  ORDER BY COALESCE(fecha_cita, fecha_solicitud) DESC, id DESC
                  LIMIT 1"
            );
        }

        // Estudios = los que el paciente ya tenia en la cita (no se pierden) + el actual.
        $estudios = [];
        $vistos   = [];
        $addEstudio = static function (string $nombre, float $precio) use (&$estudios, &$vistos) {
            $nombre = trim($nombre);
            if ($nombre === '') {
                return;
            }
            $clave = mb_strtolower($nombre);
            if (isset($vistos[$clave])) {
                return;
            }
            $vistos[$clave] = true;
            $estudios[] = ['nombre' => $nombre, 'precio' => $precio];
        };

        if ($citaId > 0) {
            $previos = eco_estudios_desde_texto($motivoPrevio);
            if ($previos) {
                // Resolver precios por nombre desde la BD.
                $mapa = [];
                if ($rp = $conex->query("SELECT nombre, precio FROM tipos_ecografias")) {
                    while ($t = $rp->fetch_assoc()) {
                        $mapa[mb_strtolower(trim((string)$t['nombre']))] = (float)$t['precio'];
                    }
                    $rp->free();
                }
                foreach ($previos as $nom) {
                    $addEstudio($nom, $mapa[mb_strtolower(trim($nom))] ?? 0.0);
                }
            }
        }
        // El estudio que se esta realizando ahora (se agrega si no estaba).
        if ($ecoNombre !== '' || $ecoPrecio > 0) {
            $addEstudio($ecoNombre, $ecoPrecio);
        }

        // Servicios = union de los que ya tenia la cita + los nuevos (sin duplicar).
        // Asi un servicio ya facturado hoy (ej. consulta) se mantiene una sola vez.
        $serviciosKeys = array_values(array_unique(array_merge(
            eco_servicios_desde_texto($motivoPrevio),
            $serviciosKeys
        )));

        $bundle = eco_calcular_bundle_multi($estudios, $serviciosKeys);
        if ($bundle['total'] <= 0) {
            return null;
        }
        $motivo = $bundle['motivo'];
        $total  = (float)$bundle['total'];

        if ($citaId > 0) {
            // Si la cita ya tenia pagos (consolidacion del mismo dia tras cobrar),
            // se recalcula el estado en vez de forzar 'pendiente'. monto_pagado no se toca.
            $estadoPago = 'pendiente';
            if ($pagadoPrevio > 0) {
                $estadoPago = ($pagadoPrevio + 0.009 >= $total) ? 'pagado' : 'parcial';
            }
            // tipo_ecografia_id solo se toca si este cobro trae un estudio real: 0 no es
            // un id valido (viola fk_cita_tipo) y, en un cobro de solo-servicios, tampoco
            // debe borrar el tipo de eco que la cita ya tuviera asentado hoy.
            if ($tipoEcoId > 0) {
                if ($up = $conex->prepare(
                    "UPDATE citas
                        SET motivo_principal = ?, monto_total = ?, tipo_ecografia_id = ?, estado_pago = ?
                      WHERE id = ?"
                )) {
                    $up->bind_param('sdisi', $motivo, $total, $tipoEcoId, $estadoPago, $citaId);
                    $up->execute();
                    $up->close();
                }
            } else {
                if ($up = $conex->prepare(
                    "UPDATE citas
                        SET motivo_principal = ?, monto_total = ?, estado_pago = ?
                      WHERE id = ?"
                )) {
                    $up->bind_param('sdsi', $motivo, $total, $estadoPago, $citaId);
                    $up->execute();
                    $up->close();
                }
            }
            return [$citaId, $total];
        }

        // No hay cita reusable → crear una presencial/completada como contenedor.
        // tipo_ecografia_id debe ir NULL (no 0) cuando el cobro es solo de servicios.
        $tipoEcoIdParam = $tipoEcoId > 0 ? $tipoEcoId : null;
        if ($ins = $conex->prepare(
            "INSERT INTO citas
                (paciente_id, ecografista_id, tipo_ecografia_id, fecha_cita, motivo_principal,
                 modalidad, tipo_cita, monto_total, estado_pago, estado)
             VALUES (?, ?, ?, NOW(), ?, 'presencial', 'primera_consulta', ?, 'pendiente', 'completada')"
        )) {
            $ins->bind_param('iiisd', $pacienteId, $ecografistaId, $tipoEcoIdParam, $motivo, $total);
            $ins->execute();
            $newId = (int)$ins->insert_id;
            $ins->close();
            return [$newId, $total];
        }
        return null;
    }
}
