<?php
/**
 * Guarda un precio desde Control de precios (recepción, administración y
 * ecografista: la sección está en el menú de los tres roles).
 *
 * Dos destinos según el origen:
 *   origen=estudio   → tipos_ecografias.precio   (id numérico)
 *   origen=servicio  → precios_servicios.precio  (clave de texto)
 *
 * El precio se valida aquí y no en el cliente: el formulario solo propone.
 */
session_start();
require_once __DIR__ . '/../lib/core/api.php';
include __DIR__ . '/../core/conexion.php';
require_once __DIR__ . '/../lib/seguridad/seguridad.php';
require_once __DIR__ . '/../lib/facturacion/facturacion.php';

api_json();
$response = ['success' => false, 'message' => 'Ocurrió un error.'];

api_require_roles(['recepcionista', 'administrador', 'ecografista']);
api_require_post();
api_require_csrf();

$origen = (string)($_POST['origen'] ?? '');
$clave  = trim((string)($_POST['clave'] ?? ''));
$bruto  = trim((string)($_POST['precio'] ?? ''));

if (!in_array($origen, ['estudio', 'servicio'], true) || $clave === '') {
    $response['message'] = 'Datos incompletos.';
    echo json_encode($response);
    exit();
}

// Se acepta coma o punto como separador decimal.
$bruto = str_replace(',', '.', $bruto);
if (!is_numeric($bruto)) {
    $response['message'] = 'El precio debe ser un número.';
    echo json_encode($response);
    exit();
}
$precio = round((float)$bruto, 2);
if ($precio < 0) {
    $response['message'] = 'El precio no puede ser negativo.';
    echo json_encode($response);
    exit();
}
if ($precio > 99999.99) {
    $response['message'] = 'El precio supera el máximo permitido.';
    echo json_encode($response);
    exit();
}

if ($origen === 'estudio') {
    $id = (int)$clave;
    if ($id <= 0) {
        $response['message'] = 'Estudio no válido.';
        echo json_encode($response);
        exit();
    }
    $sel = $conex->prepare("SELECT nombre, precio FROM tipos_ecografias WHERE id = ?");
    $sel->bind_param('i', $id);
    $sel->execute();
    $previo = $sel->get_result()->fetch_assoc();
    $sel->close();
    if (!$previo) {
        $response['message'] = 'El estudio no existe.';
        echo json_encode($response);
        exit();
    }

    $up = $conex->prepare("UPDATE tipos_ecografias SET precio = ? WHERE id = ?");
    $up->bind_param('di', $precio, $id);
    $ok = $up->execute();
    $up->close();
    $etiqueta = (string)$previo['nombre'];
    $anterior = (float)$previo['precio'];
} else {
    $sel = $conex->prepare("SELECT etiqueta, precio FROM precios_servicios WHERE clave = ?");
    $sel->bind_param('s', $clave);
    $sel->execute();
    $previo = $sel->get_result()->fetch_assoc();
    $sel->close();
    if (!$previo) {
        $response['message'] = 'El servicio no existe.';
        echo json_encode($response);
        exit();
    }

    $up = $conex->prepare("UPDATE precios_servicios SET precio = ? WHERE clave = ?");
    $up->bind_param('ds', $precio, $clave);
    $ok = $up->execute();
    $up->close();
    $etiqueta = (string)$previo['etiqueta'];
    $anterior = (float)$previo['precio'];
}

if ($ok) {
    // Cambiar una tarifa afecta a lo que se cobra: queda en auditoría.
    eco_auditar($conex, 'precio_actualizado', [
        'entidad'    => $origen === 'estudio' ? 'tipos_ecografias' : 'precios_servicios',
        'entidad_id' => $origen === 'estudio' ? (int)$clave : null,
        'detalle'    => ['clave' => $clave, 'etiqueta' => $etiqueta, 'antes' => $anterior, 'ahora' => $precio],
    ]);
    $response['success']  = true;
    $response['message']  = $etiqueta . ': ' . eco_money($anterior) . ' → ' . eco_money($precio);
    $response['precio']   = $precio;
    $response['formato']  = eco_money($precio);
} else {
    error_log('guardar_precio: ' . $conex->error);
    $response['message'] = 'No se pudo guardar el precio.';
}

$conex->close();
echo json_encode($response);
