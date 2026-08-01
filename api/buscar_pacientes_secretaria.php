<?php
session_start();
include __DIR__ . '/../core/conexion.php';
require_once __DIR__ . '/../lib/core/table_sort_helpers.php';
require_once __DIR__ . '/../lib/facturacion/facturacion.php';
require_once __DIR__ . '/../lib/pacientes/filtros.php';

if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['recepcionista', 'administrador'], true)) {
    exit('Acceso denegado');
}

/**
 * @return string Iniciales (máx. 2) para avatar
 */
function rx_paciente_iniciales(string $nombre): string
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
 * Etiquetas de los servicios adicionales asentados en el texto de la cita.
 *
 * @return string[]
 */
function rx_servicios_labels(?string $motivo): array
{
    $claves = eco_servicios_desde_texto($motivo);
    if (!$claves) {
        return [];
    }
    $mapa = [];
    foreach (eco_servicios_adicionales() as $s) {
        $mapa[$s['key']] = $s['label'];
    }
    $out = [];
    foreach ($claves as $k) {
        if (isset($mapa[$k])) {
            $out[] = $mapa[$k];
        }
    }
    return $out;
}

/** Fila del panel desplegable. */
function rx_detalle_item(string $etiqueta, string $valor): string
{
    return '<div class="rx-detalle__item">'
        . '<span class="rx-detalle__label">' . htmlspecialchars($etiqueta) . '</span>'
        . '<strong class="rx-detalle__valor">' . htmlspecialchars($valor !== '' ? $valor : '—') . '</strong>'
        . '</div>';
}

$termino_busqueda = isset($_POST['query']) ? (string)$_POST['query'] : '';
$busqueda = '%' . $termino_busqueda . '%';

$rango = isset($_POST['rango']) ? (string)$_POST['rango'] : 'todos';
$fechaFiltro = isset($_POST['fecha']) ? trim((string)$_POST['fecha']) : '';
$rangoFechas = eco_rango_atencion($rango, $fechaFiltro);

// Con filtro de fecha activo, "atendido" = tiene una cita no cancelada ese día.
$sqlExiste = $rangoFechas
    ? " AND EXISTS (SELECT 1 FROM citas cf
                     WHERE cf.paciente_id = u.id AND cf.estado <> 'cancelada'
                       AND cf.fecha_cita BETWEEN ? AND ?)"
    : '';
// Y la columna "Atención" debe mostrar la cita de ese día, no la más reciente.
$sqlCitaRango = $rangoFechas ? ' AND c3.fecha_cita BETWEEN ? AND ? ' : '';

// Mismos campos buscables que "Mis Pacientes": nombre, cédula, correo, teléfono y dirección.
$sqlCount = "SELECT COUNT(*) AS total FROM usuarios u
    WHERE u.rol = 'paciente' AND u.estado = 'aprobado'
      AND (u.nombre_completo LIKE ? OR u.cedula LIKE ? OR u.correo LIKE ? OR u.telefono LIKE ? OR u.direccion LIKE ?)"
    . $sqlExiste;
$paramsCount = [$busqueda, $busqueda, $busqueda, $busqueda, $busqueda];
if ($rangoFechas) {
    array_push($paramsCount, $rangoFechas[0], $rangoFechas[1]);
}
$stmtCount = $conex->prepare($sqlCount);
$stmtCount->bind_param(str_repeat('s', count($paramsCount)), ...$paramsCount);
$stmtCount->execute();
$totalFiltrado = (int)($stmtCount->get_result()->fetch_assoc()['total'] ?? 0);
$stmtCount->close();

// Importes del mismo conjunto que muestra la tabla: si hay filtro de fecha se
// suman solo las citas de ese rango, así "Hoy" da lo cobrado hoy.
// 'exonerado' no cuenta como pendiente: no se va a cobrar.
$sqlMontos = "SELECT COALESCE(SUM(c.monto_pagado), 0) AS cobrado,
                     COALESCE(SUM(CASE WHEN c.estado_pago IN ('pendiente','parcial')
                                       THEN GREATEST(COALESCE(c.monto_total, 0) - c.monto_pagado, 0)
                                       ELSE 0 END), 0) AS pendiente
    FROM citas c
    JOIN usuarios u ON u.id = c.paciente_id
    WHERE u.rol = 'paciente' AND u.estado = 'aprobado' AND c.estado <> 'cancelada'
      AND (u.nombre_completo LIKE ? OR u.cedula LIKE ? OR u.correo LIKE ? OR u.telefono LIKE ? OR u.direccion LIKE ?)"
    . ($rangoFechas ? " AND c.fecha_cita BETWEEN ? AND ?" : '');
$paramsMontos = [$busqueda, $busqueda, $busqueda, $busqueda, $busqueda];
if ($rangoFechas) {
    array_push($paramsMontos, $rangoFechas[0], $rangoFechas[1]);
}
$stmtMontos = $conex->prepare($sqlMontos);
$stmtMontos->bind_param(str_repeat('s', count($paramsMontos)), ...$paramsMontos);
$stmtMontos->execute();
$montos = $stmtMontos->get_result()->fetch_assoc() ?: ['cobrado' => 0, 'pendiente' => 0];
$stmtMontos->close();

$attrMontos = ' data-rx-cobrado="' . htmlspecialchars(eco_money((float)$montos['cobrado']), ENT_QUOTES, 'UTF-8') . '"'
    . ' data-rx-pendiente="' . htmlspecialchars(eco_money((float)$montos['pendiente']), ENT_QUOTES, 'UTF-8') . '"'
    . ' data-rx-pendiente-num="' . (float)$montos['pendiente'] . '"';

// Recepción ve el total de citas e informes del paciente en la clínica (no de un
// ecografista concreto, como sí hace "Mis Pacientes"), más el estudio, servicios
// y cobro de su atención más reciente.
$sql = "SELECT u.id, u.nombre_completo, u.correo, u.cedula, u.direccion, u.telefono, u.fecha_registro,
               TIMESTAMPDIFF(YEAR, u.fecha_nacimiento, CURDATE()) AS edad,
               (SELECT COUNT(*) FROM citas c WHERE c.paciente_id = u.id) AS total_citas,
               (SELECT COUNT(*) FROM informes_estudios ie WHERE ie.paciente_id = u.id) AS total_informes,
               ult.id           AS cita_id,
               ult.motivo_principal,
               ult.monto_total,
               ult.monto_pagado,
               ult.estado_pago,
               ult.metodo_pago,
               ult.fecha_cita,
               te.nombre        AS tipo_eco,
               eco.nombre_completo AS ecografista
    FROM usuarios u
    LEFT JOIN citas ult ON ult.id = (
            SELECT c3.id FROM citas c3
             WHERE c3.paciente_id = u.id AND c3.estado <> 'cancelada' $sqlCitaRango
             ORDER BY COALESCE(c3.fecha_cita, c3.fecha_solicitud) DESC, c3.id DESC
             LIMIT 1)
    LEFT JOIN tipos_ecografias te ON te.id = ult.tipo_ecografia_id
    LEFT JOIN usuarios eco        ON eco.id = ult.ecografista_id
    WHERE u.rol = 'paciente' AND u.estado = 'aprobado'
      AND (u.nombre_completo LIKE ? OR u.cedula LIKE ? OR u.correo LIKE ? OR u.telefono LIKE ? OR u.direccion LIKE ?)
      $sqlExiste
    ORDER BY u.nombre_completo ASC";

// El orden de los parámetros sigue el orden textual de los ? en la consulta:
// primero el subselect del JOIN, luego los LIKE del WHERE, luego el EXISTS.
$params = [];
if ($rangoFechas) {
    array_push($params, $rangoFechas[0], $rangoFechas[1]);
}
array_push($params, $busqueda, $busqueda, $busqueda, $busqueda, $busqueda);
if ($rangoFechas) {
    array_push($params, $rangoFechas[0], $rangoFechas[1]);
}

$stmt = $conex->prepare($sql);
$stmt->bind_param(str_repeat('s', count($params)), ...$params);
$stmt->execute();
$pacientes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if ($pacientes) {
    // Datos para "Exportar a Excel": los consume el botón de la página.
    $exportar = array_map(static function (array $p): array {
        $servicios = rx_servicios_labels($p['motivo_principal'] ?? null);
        return [
            'Nombre'         => (string)$p['nombre_completo'],
            'Cédula'         => (string)($p['cedula'] ?: ''),
            'Edad'           => $p['edad'] ? (int)$p['edad'] : '',
            'Correo'         => (string)($p['correo'] ?: ''),
            'Teléfono'       => (string)($p['telefono'] ?: ''),
            'Dirección'      => (string)($p['direccion'] ?: ''),
            'Citas'          => (int)$p['total_citas'],
            'Informes'       => (int)$p['total_informes'],
            'Tipo de eco'    => (string)($p['tipo_eco'] ?: ''),
            'Servicios'      => implode(', ', $servicios),
            'Ecografista'    => (string)($p['ecografista'] ?: ''),
            'Monto'          => $p['cita_id'] ? (float)($p['monto_total'] ?? 0) : '',
            'Estado de pago' => $p['cita_id'] ? eco_estado_pago_label((string)$p['estado_pago']) : '',
            'Método de pago' => (string)($p['metodo_pago'] ?: ''),
            'Ingreso'        => $p['fecha_registro'] ? date('d/m/Y', strtotime($p['fecha_registro'])) : '',
        ];
    }, $pacientes);

    echo '<div class="table-responsive" data-rx-total="' . $totalFiltrado . '"' . $attrMontos
        . ' data-rx-export="' . htmlspecialchars(json_encode($exportar, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') . '">';
    // Clase propia: .rx-pac-table la comparten otras 4 tablas (usuarios del
    // admin, facturación, notas, mis pacientes) con distinto número de columnas.
    echo '<table class="rx-pac-table rx-gestion-pac-table">';
    echo '<colgroup>';
    echo '<col class="col-expandir"><col class="col-paciente"><col class="col-cedula"><col class="col-edad">'
        . '<col class="col-correo"><col class="col-telefono"><col class="col-direccion">'
        . '<col class="col-citas"><col class="col-informes"><col class="col-atencion">'
        . '<col class="col-ingreso"><col class="col-acciones">';
    echo '</colgroup>';
    echo '<thead><tr>';
    echo '<th class="rx-th-expandir"><span class="sr-only">Detalle</span></th>';
    echo eco_sort_th('Paciente', 1, 'text');
    echo eco_sort_th('Cédula', 2, 'number');
    echo eco_sort_th('Edad', 3, 'number');
    echo eco_sort_th('Correo', 4, 'text');
    echo eco_sort_th('Teléfono', 5, 'text');
    echo eco_sort_th('Dirección', 6, 'text');
    echo '<th>Citas</th>';
    echo '<th>Informes</th>';
    echo eco_sort_th('Atención', 9, 'text');
    echo eco_sort_th('Ingreso', 10, 'date');
    echo '<th class="rx-th-acciones">Acciones</th>';
    echo '</tr></thead><tbody>';

    foreach ($pacientes as $paciente) {
        $id = (int)$paciente['id'];
        $nomAttr = htmlspecialchars((string)$paciente['nombre_completo'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $iniciales = htmlspecialchars(rx_paciente_iniciales((string)$paciente['nombre_completo']));
        $fechaRegistro = $paciente['fecha_registro'] ? date('d/m/Y', strtotime($paciente['fecha_registro'])) : '—';

        $sortNombre = htmlspecialchars(mb_strtolower(trim((string)$paciente['nombre_completo']), 'UTF-8'), ENT_QUOTES, 'UTF-8');
        $cedulaDigits = preg_replace('/\D/', '', (string)($paciente['cedula'] ?? ''));
        $sortCedula = htmlspecialchars($cedulaDigits !== '' ? $cedulaDigits : '0', ENT_QUOTES, 'UTF-8');
        $sortEdad = htmlspecialchars($paciente['edad'] ? (string)(int)$paciente['edad'] : '0', ENT_QUOTES, 'UTF-8');
        $sortCorreo = htmlspecialchars(mb_strtolower(trim((string)($paciente['correo'] ?? '')), 'UTF-8'), ENT_QUOTES, 'UTF-8');
        $sortTelefono = htmlspecialchars(mb_strtolower(trim((string)($paciente['telefono'] ?? '')), 'UTF-8'), ENT_QUOTES, 'UTF-8');
        $sortDireccion = htmlspecialchars(mb_strtolower(trim((string)($paciente['direccion'] ?? '')), 'UTF-8'), ENT_QUOTES, 'UTF-8');
        $sortIngreso = $paciente['fecha_registro']
            ? htmlspecialchars(date('Y-m-d', strtotime($paciente['fecha_registro'])), ENT_QUOTES, 'UTF-8')
            : '';

        // Atención más reciente: estudio + estado de cobro.
        $tieneCita  = !empty($paciente['cita_id']);
        $servicios  = rx_servicios_labels($paciente['motivo_principal'] ?? null);
        $tipoEco    = (string)($paciente['tipo_eco'] ?? '');
        $estadoPago = (string)($paciente['estado_pago'] ?? '');
        $monto      = $paciente['monto_total'] !== null ? (float)$paciente['monto_total'] : null;
        $metodoPago = (string)($paciente['metodo_pago'] ?? '');
        $fechaAt    = !empty($paciente['fecha_cita']) ? date('d/m/Y H:i', strtotime((string)$paciente['fecha_cita'])) : '';

        // Una cita puede llevar VARIOS estudios: tipo_ecografia_id solo guarda el
        // primero, la lista completa vive en el texto de motivo_principal.
        $estudios = eco_estudios_desde_texto($paciente['motivo_principal'] ?? null);
        if (!$estudios && $tipoEco !== '') {
            $estudios = [$tipoEco];
        }
        // Titular de la celda: el primer estudio (+N si hay más); si no hay
        // estudios, el primer servicio.
        $resumen = $estudios[0] ?? ($servicios[0] ?? '');
        if (count($estudios) > 1) {
            $resumen .= ' +' . (count($estudios) - 1);
        }
        $tituloCelda = $estudios ? implode(' · ', $estudios) : $resumen;
        $sortAtencion = htmlspecialchars(mb_strtolower($tieneCita ? ($estadoPago . ' ' . $resumen) : 'zzz', 'UTF-8'), ENT_QUOTES, 'UTF-8');

        echo '<tr class="rx-pac-row" data-rx-row="' . $id . '">';

        echo '<td class="rx-td-expandir">';
        if ($tieneCita) {
            echo '<button type="button" class="rx-expand" data-rx-expand="' . $id . '"'
                . ' aria-expanded="false" aria-label="Ver detalle de la atención de ' . $nomAttr . '">'
                . '<i class="fa-solid fa-chevron-right" aria-hidden="true"></i></button>';
        }
        echo '</td>';

        echo '<td class="rx-pac-td-nombre" data-sort-value="' . $sortNombre . '">';
        echo '<div class="rx-pac-cell-nombre">';
        echo '<span class="rx-pac-avatar" aria-hidden="true">' . $iniciales . '</span>';
        echo '<strong>' . htmlspecialchars($paciente['nombre_completo']) . '</strong>';
        echo '</div></td>';
        echo '<td class="rx-pac-td-cedula" data-sort-value="' . $sortCedula . '">' . htmlspecialchars($paciente['cedula'] ?: '—') . '</td>';
        echo '<td class="rx-pac-td-edad" data-sort-value="' . $sortEdad . '">' . ($paciente['edad'] ? (int)$paciente['edad'] . ' años' : '—') . '</td>';
        echo '<td class="rx-pac-td-email" data-sort-value="' . $sortCorreo . '">' . htmlspecialchars($paciente['correo'] ?: '—') . '</td>';
        echo '<td class="rx-pac-td-telefono" data-sort-value="' . $sortTelefono . '">' . htmlspecialchars($paciente['telefono'] ?: '—') . '</td>';
        echo '<td class="rx-pac-td-direccion" data-sort-value="' . $sortDireccion . '">' . htmlspecialchars($paciente['direccion'] ?: '—') . '</td>';
        echo '<td><span class="badge badge-accent">' . (int)$paciente['total_citas'] . '</span></td>';
        echo '<td><span class="badge badge-purple">' . (int)$paciente['total_informes'] . '</span></td>';

        echo '<td class="rx-td-atencion" data-sort-value="' . $sortAtencion . '">';
        if ($tieneCita) {
            [$colTxt, $colBg] = eco_estado_pago_color($estadoPago);
            echo '<span class="rx-atencion__estudio" title="' . htmlspecialchars($tituloCelda, ENT_QUOTES, 'UTF-8') . '">'
                . htmlspecialchars($resumen !== '' ? $resumen : 'Sin estudio') . '</span>';
            echo '<span class="rx-pago-badge" style="color:' . $colTxt . ';background:' . $colBg . ';">'
                . htmlspecialchars(eco_estado_pago_label($estadoPago))
                . ($monto !== null ? ' · ' . htmlspecialchars(eco_money($monto)) : '')
                . '</span>';
            // Fecha de la última visita: "Ingreso" es el alta del paciente y no
            // cambia, así que la fecha de atención se muestra aquí.
            if ($fechaAt !== '') {
                echo '<span class="rx-atencion__fecha">' . htmlspecialchars(date('d/m/Y', strtotime((string)$paciente['fecha_cita']))) . '</span>';
            }
        } else {
            echo '<span class="rx-atencion__vacio">Sin atención</span>';
        }
        echo '</td>';

        echo '<td class="rx-pac-td-ingreso" data-sort-value="' . $sortIngreso . '">' . htmlspecialchars($fechaRegistro) . '</td>';
        // Acciones en iconos: con etiqueta ocupaban 322px de ancho y obligaban a
        // desplazar la tabla. El texto sigue disponible como tooltip y para
        // lectores de pantalla.
        echo '<td class="rx-td-acciones">';
        echo '<div class="acciones-wrapper">';
        // Con dos botones ya cabe la etiqueta: un icono suelto obliga a
        // adivinar o a esperar el tooltip.
        echo '<button type="button" class="rx-btn rx-btn--accion rx-btn--prim rx-js-ficha" data-rx-pid="' . $id . '"'
            . ' aria-label="Ver ficha de ' . $nomAttr . '"><i class="fa-solid fa-id-card" aria-hidden="true"></i><span>Ficha</span></button>';
        echo '<button type="button" class="rx-btn rx-btn--accion rx-btn--sec rx-js-prog" data-rx-pid="' . $id . '" data-rx-nom="' . $nomAttr . '"'
            . ' aria-label="Programar cita para ' . $nomAttr . '"><i class="fa-solid fa-calendar-plus" aria-hidden="true"></i><span>Cita</span></button>';
        // Los informes se consultan desde la ficha, que ya los lista con su
        // número, fecha y estado: aquí el tercer botón solo robaba ancho.
        echo '</div>';
        echo '</td>';
        echo '</tr>';

        // Fila de detalle: lo que no cabe en la tabla sin volverla ilegible.
        if ($tieneCita) {
            echo '<tr class="rx-detalle" data-rx-detalle="' . $id . '" hidden>';
            echo '<td colspan="12">';
            echo '<div class="rx-detalle__panel">';
            echo rx_detalle_item(count($estudios) > 1 ? 'Ecografías' : 'Tipo de ecografía', implode(', ', $estudios));
            echo rx_detalle_item('Servicios', implode(', ', $servicios));
            echo rx_detalle_item('Ecografista', (string)($paciente['ecografista'] ?? ''));
            echo rx_detalle_item('Monto', $monto !== null ? eco_money($monto) : '');
            echo rx_detalle_item('Pagado', eco_money((float)($paciente['monto_pagado'] ?? 0)));
            echo rx_detalle_item('Estado de pago', eco_estado_pago_label($estadoPago));
            echo rx_detalle_item('Método de pago', $metodoPago);
            echo rx_detalle_item('Fecha de atención', $fechaAt);
            echo '</div>';
            if (!empty($paciente['motivo_principal'])) {
                echo '<p class="rx-detalle__motivo">' . htmlspecialchars((string)$paciente['motivo_principal']) . '</p>';
            }
            echo '</td>';
            echo '</tr>';
        }
    }
    echo '</tbody></table></div>';
} else {
    $vacio = eco_rango_atencion_vacio($rango, $rangoFechas, $termino_busqueda !== '');
    echo '<p class="rx-pac-empty" data-rx-total="0"' . $attrMontos . ' data-rx-export="[]">'
        . htmlspecialchars($vacio) . '</p>';
}

$stmt->close();
$conex->close();
