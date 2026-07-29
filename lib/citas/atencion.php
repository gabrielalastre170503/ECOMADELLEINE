<?php
/**
 * Composición de una "atención": estudios + servicios adicionales + cobro.
 *
 * Lo usan los formularios que asientan una atención completa:
 *   - api/guardar_paciente.php                (alta rápida con servicio)
 *   - api/guardar_paciente_extendido_ajax.php (alta extendida con servicio)
 *   - api/guardar_cita_directa.php            (cita a un paciente existente)
 *
 * Está aquí porque ambos deben calcular EXACTAMENTE igual: precios desde la BD,
 * las mismas promociones y el mismo formato de motivo_principal. Si cada
 * endpoint lo hiciera por su cuenta, un descuento aplicado al dar de alta no
 * coincidiría con el mismo caso al reprogramar.
 */

require_once __DIR__ . '/../facturacion/facturacion.php';
require_once __DIR__ . '/citas.php';
require_once __DIR__ . '/../comunicaciones/notificaciones.php';

if (!function_exists('eco_atencion_desde_form')) {

    /**
     * Normaliza los campos del formulario en una atención lista para guardar.
     *
     * Campos que lee de $post:
     *   tipos_ecografia[]  ids de estudio (o tipo_ecografia_id suelto, compatible)
     *   servicios[]        claves de eco_servicios_adicionales()
     *   otro_servicio      texto libre
     *   monto_total        importe manual que manda sobre el calculado
     *   metodo_pago        si viene y es válido, la atención queda pagada
     *
     * @return array{
     *   tipo_eco_id:?int, estudios:string[], servicios:string[], motivo:string,
     *   total:float, pagado:float, estado_pago:string, metodo_pago:?string,
     *   fecha_pago:?string
     * }|null  null si no se eligió nada que atender.
     */
    function eco_atencion_desde_form(mysqli $conex, array $post): ?array
    {
        $tiposIn = [];
        if (isset($post['tipos_ecografia']) && is_array($post['tipos_ecografia'])) {
            $tiposIn = $post['tipos_ecografia'];
        } elseif (isset($post['tipo_ecografia_id'])) {
            // Compatibilidad con los formularios que aún mandan un solo id.
            $tiposIn = [$post['tipo_ecografia_id']];
        }
        $serviciosIn = (isset($post['servicios']) && is_array($post['servicios'])) ? $post['servicios'] : [];
        $otro        = trim((string)($post['otro_servicio'] ?? ''));

        // Estudios: nombres y precios autoritativos desde la BD. La cita guarda
        // el primero en tipo_ecografia_id (la FK solo acepta uno) y la lista
        // completa viaja en motivo_principal.
        $estudios    = [];
        $nombres     = [];
        $tipoEcoId   = null;
        $idsPedidos = array_values(array_unique(array_filter(
            array_map(static fn($v) => (int)$v, $tiposIn),
            static fn(int $v) => $v > 0
        )));
        if ($idsPedidos) {
            $marcas = implode(',', array_fill(0, count($idsPedidos), '?'));
            if ($q = $conex->prepare("SELECT id, nombre, precio FROM tipos_ecografias
                                       WHERE activo = 1 AND id IN ($marcas)
                                       ORDER BY FIELD(id, $marcas)")) {
                $dobles = array_merge($idsPedidos, $idsPedidos);
                $q->bind_param(str_repeat('i', count($dobles)), ...$dobles);
                $q->execute();
                $res = $q->get_result();
                while ($row = $res->fetch_assoc()) {
                    if ($tipoEcoId === null) {
                        $tipoEcoId = (int)$row['id'];
                    }
                    $estudios[] = ['nombre' => (string)$row['nombre'], 'precio' => (float)$row['precio']];
                    $nombres[]  = (string)$row['nombre'];
                }
                $q->close();
            }
        }

        // Servicios adicionales: solo claves del catálogo.
        $validas   = array_column(eco_servicios_adicionales(), 'key');
        $servicios = array_values(array_intersect(
            array_map(static fn($s) => (string)$s, $serviciosIn),
            $validas
        ));

        if (!$estudios && !$servicios && $otro === '') {
            return null;
        }

        $bundle = eco_calcular_bundle_multi($estudios, $servicios);
        $total  = (float)$bundle['total'];

        $montoManual = trim((string)($post['monto_total'] ?? ''));
        if ($montoManual !== '' && is_numeric($montoManual) && (float)$montoManual >= 0) {
            $total = round((float)$montoManual, 2);
        }

        // El texto termina siempre en "Total $X": eco_total_desde_texto lee el
        // último importe, así que "Otro" se inserta antes y el total se re-anexa.
        $motivo = preg_replace('/\s*·\s*Total\s+\$[0-9.,]+\s*$/u', '', (string)$bundle['motivo']);
        if ($otro !== '') {
            $otroLimpio = trim(str_replace('$', '', $otro));
            if ($otroLimpio !== '') {
                $motivo = ($motivo !== '' ? $motivo . ' · ' : '') . 'Otro: ' . $otroLimpio;
            }
        }
        $motivo = trim((string)$motivo);
        $motivo = ($motivo !== '' ? $motivo . ' · ' : '') . 'Total ' . eco_money($total);
        $motivo = mb_substr($motivo, 0, 250);

        // Cobro: elegir método equivale a dejarlo pagado por completo.
        $metodoIn   = trim((string)($post['metodo_pago'] ?? ''));
        $metodoPago = in_array($metodoIn, eco_metodos_pago(), true) ? $metodoIn : null;
        $pagado     = 0.0;
        $estadoPago = 'pendiente';
        $fechaPago  = null;
        if ($metodoPago !== null) {
            $pagado     = $total;
            $estadoPago = eco_estado_pago($total, $pagado);
            $fechaPago  = date('Y-m-d H:i:s');
        }

        return [
            'tipo_eco_id' => $tipoEcoId,
            'estudios'    => $nombres,
            'servicios'   => $servicios,
            'motivo'      => $motivo,
            'total'       => $total,
            'pagado'      => $pagado,
            'estado_pago' => $estadoPago,
            'metodo_pago' => $metodoPago,
            'fecha_pago'  => $fechaPago,
        ];
    }

    /**
     * Asienta la atención que recepción registró junto con el alta del paciente:
     * estudios, servicios adicionales, ecografista y cobro. Devuelve un resumen
     * para el modal de éxito, o null si el formulario no traía servicio.
     *
     * La comparte el alta rápida y el alta extendida: son el mismo hecho clínico
     * registrado con dos formularios distintos, así que el paciente debe llegar
     * igual a "Mis Pacientes" del ecografista en ambos casos.
     *
     * Quien llama decide si el rol puede asentar servicio: el bloque solo lo
     * muestran los formularios de recepción.
     *
     * Los precios se leen de la BD, nunca del cliente. El monto sí es editable
     * por recepción (descuentos, acuerdos) y ese valor manda sobre el calculado.
     */
    function eco_crear_cita_de_alta(mysqli $conex, int $pacienteId, array $post): ?array
    {
        $ecografistaId = isset($post['ecografista_id']) ? (int)$post['ecografista_id'] : 0;
        $fechaCitaIn   = trim((string)($post['fecha_cita'] ?? ''));

        // Estudios, servicios y cobro: mismo cálculo que "Programar cita".
        $atencion = eco_atencion_desde_form($conex, $post);
        if ($atencion === null) {
            // Sin nada que atender no se agenda: el alta queda solo como paciente.
            return null;
        }

        // Ecografista: debe existir, tener el rol y estar aprobado.
        $ecografistaNombre = '';
        if ($ecografistaId > 0) {
            $q = $conex->prepare("SELECT nombre_completo FROM usuarios WHERE id = ? AND rol = 'ecografista' AND estado = 'aprobado'");
            $q->bind_param('i', $ecografistaId);
            $q->execute();
            if ($row = $q->get_result()->fetch_assoc()) {
                $ecografistaNombre = (string)$row['nombre_completo'];
            } else {
                $ecografistaId = 0;
            }
            $q->close();
        }

        $motivo     = $atencion['motivo'];
        $total      = $atencion['total'];
        $pagado     = $atencion['pagado'];
        $estadoPago = $atencion['estado_pago'];
        $metodoPago = $atencion['metodo_pago'];
        $fechaPago  = $atencion['fecha_pago'];

        $fechaCita = date('Y-m-d H:i:s');
        if ($fechaCitaIn !== '') {
            $ts = strtotime($fechaCitaIn);
            if ($ts > 0) {
                $fechaCita = date('Y-m-d H:i:s', $ts);
            }
        }

        // 0 no es un id valido para las FK: deben ir NULL.
        $ecoIdParam  = $ecografistaId > 0 ? $ecografistaId : null;
        $tipoIdParam = $atencion['tipo_eco_id'];

        $sql = "INSERT INTO citas
                    (paciente_id, ecografista_id, tipo_ecografia_id, fecha_cita, motivo_principal,
                     modalidad, tipo_cita, monto_total, monto_pagado, estado_pago, metodo_pago, fecha_pago, estado)
                VALUES (?, ?, ?, ?, ?, 'presencial', 'primera_consulta', ?, ?, ?, ?, ?, 'confirmada')";
        if (!($ins = $conex->prepare($sql))) {
            error_log('eco_crear_cita_de_alta: ' . $conex->error);
            return null;
        }
        $ins->bind_param(
            'iiissddsss',
            $pacienteId, $ecoIdParam, $tipoIdParam, $fechaCita, $motivo,
            $total, $pagado, $estadoPago, $metodoPago, $fechaPago
        );
        if (!$ins->execute()) {
            error_log('eco_crear_cita_de_alta: ' . $ins->error);
            $ins->close();
            return null;
        }
        $citaId = (int)$ins->insert_id;
        $ins->close();

        eco_cita_evento($conex, $citaId, 'confirmada', [
            'estado_nuevo' => 'confirmada',
            'detalle'      => ['origen' => 'alta_paciente_recepcion', 'total' => $total],
        ]);
        if ($metodoPago !== null) {
            eco_cita_evento($conex, $citaId, 'pago_registrado', [
                'detalle' => ['metodo' => $metodoPago, 'monto' => $total],
            ]);
        }

        // Aviso in-app al ecografista: la campana ya consulta esta bandeja.
        if ($ecografistaId > 0) {
            eco_notificar($conex, $ecografistaId, 'cita_asignada', 'Nuevo paciente asignado', [
                'mensaje' => $motivo,
                'url'     => eco_url('mis-pacientes'),
                'icono'   => 'fa-solid fa-user-plus',
            ]);
        }

        return [
            'cita_id'     => $citaId,
            'ecografista' => $ecografistaNombre,
            'total'       => eco_money($total),
            'estado_pago' => eco_estado_pago_label($estadoPago),
            'metodo_pago' => $metodoPago,
            'detalle'     => $motivo,
        ];
    }

    /**
     * Resumen legible de la atención para los modales de confirmación.
     */
    function eco_atencion_resumen(array $atencion): array
    {
        return [
            'estudios'    => implode(', ', $atencion['estudios']),
            'total'       => eco_money($atencion['total']),
            'estado_pago' => eco_estado_pago_label($atencion['estado_pago']),
            'metodo_pago' => $atencion['metodo_pago'],
            'detalle'     => $atencion['motivo'],
        ];
    }
}
