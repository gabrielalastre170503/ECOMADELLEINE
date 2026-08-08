<?php
/**
 * Listas de precios: crear, activar y eliminar tarifas.
 *
 * Mismos roles que Control de precios, porque es la misma sección.
 * Cambiar de tarifa mueve TODOS los precios de golpe, así que las tres
 * acciones quedan en auditoría.
 */
session_start();
require_once __DIR__ . '/../lib/core/api.php';
include __DIR__ . '/../core/conexion.php';
require_once __DIR__ . '/../lib/seguridad/seguridad.php';
require_once __DIR__ . '/../lib/facturacion/facturacion.php';
require_once __DIR__ . '/../lib/facturacion/listas_precios.php';

api_json();
api_require_roles(['recepcionista', 'administrador', 'ecografista']);
api_require_post();
api_require_csrf();

$accion = api_str('accion');

if ($accion === 'crear') {
    $res = eco_lista_precios_crear(
        $conex,
        api_str('nombre'),
        api_str('descripcion'),
        api_uid() ?: null
    );
    if (!$res['ok']) {
        api_fail($res['message']);
    }
    eco_auditar($conex, 'lista_precios_creada', [
        'entidad'    => 'listas_precios',
        'entidad_id' => $res['id'],
        'detalle'    => ['nombre' => api_str('nombre')],
    ]);
    api_ok(['message' => $res['message'], 'id' => $res['id']]);
}

if ($accion === 'aplicar') {
    $id  = api_int('lista_id');
    $res = eco_lista_precios_aplicar($conex, $id);
    if (!$res['ok']) {
        api_fail($res['message']);
    }
    eco_auditar($conex, 'lista_precios_aplicada', [
        'entidad'    => 'listas_precios',
        'entidad_id' => $id,
        'detalle'    => ['mensaje' => $res['message'], 'precios' => $res['aplicados']],
    ]);
    $detalle = $res['aplicados'] > 0
        ? ' ' . $res['aplicados'] . ' precio(s) cambiaron.'
        : ' Los precios ya eran los de esta tarifa.';
    $aviso = $res['sin_precio'] > 0
        ? ' ' . $res['sin_precio'] . ' estudio(s) no estaban en la tarifa y conservan su precio.'
        : '';
    api_ok([
        'message'    => $res['message'] . $detalle . $aviso,
        'aplicados'  => $res['aplicados'],
        'sin_precio' => $res['sin_precio'],
    ]);
}

if ($accion === 'eliminar') {
    $id  = api_int('lista_id');
    $res = eco_lista_precios_eliminar($conex, $id);
    if (!$res['ok']) {
        api_fail($res['message']);
    }
    eco_auditar($conex, 'lista_precios_eliminada', [
        'entidad'    => 'listas_precios',
        'entidad_id' => $id,
        'detalle'    => ['mensaje' => $res['message']],
    ]);
    api_ok(['message' => $res['message']]);
}

api_fail('Acción no reconocida.');
