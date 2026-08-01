<?php
session_start();
require_once __DIR__ . '/../lib/core/api.php';
include __DIR__ . '/../core/conexion.php';
require_once __DIR__ . '/../lib/informes/estudios_render.php';
require_once __DIR__ . '/../lib/informes/informes.php';
require_once __DIR__ . '/../lib/seguridad/seguridad.php';

api_json();

if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['ecografista', 'administrador', 'recepcionista', 'paciente'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso no autorizado']);
    exit();
}

$sesion_rol = (string)$_SESSION['rol'];
$sesion_uid = (int)$_SESSION['usuario_id'];

// Libera el bloqueo de sesión mientras hay I/O BD + renderizado (primer open más fluido si hay paralelismo)
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$informe_id = isset($_GET['informe_id']) && is_numeric($_GET['informe_id'])
    ? (int)$_GET['informe_id'] : 0;

if (!$informe_id) {
    http_response_code(400);
    echo json_encode(['error' => 'ID de informe inválido']);
    exit();
}

$stmt = $conex->prepare("
    SELECT
        ie.id, ie.numero_informe, ie.fecha_estudio, ie.estado,
        ie.datos_clinicos, ie.creado_en,
        ie.ecografista_id, ie.paciente_id,
        ie.fecha_firma, ie.fecha_anulacion, ie.motivo_anulacion,
        t.nombre      AS tipo_nombre,
        t.icono       AS tipo_icono,
        t.categoria   AS tipo_categoria,
        t.esquema_campos,
        p.nombre_completo AS paciente_nombre,
        p.cedula          AS paciente_cedula,
        TIMESTAMPDIFF(YEAR, p.fecha_nacimiento, CURDATE()) AS paciente_edad,
        u.nombre_completo AS ecografista_nombre,
        fb.nombre_completo AS firmante_nombre,
        ab.nombre_completo AS anulador_nombre
    FROM informes_estudios ie
    LEFT JOIN tipos_ecografias t ON t.id = ie.tipo_ecografia_id
    LEFT JOIN usuarios p ON p.id = ie.paciente_id
    LEFT JOIN usuarios u ON u.id = ie.ecografista_id
    LEFT JOIN usuarios fb ON fb.id = ie.firmado_por
    LEFT JOIN usuarios ab ON ab.id = ie.anulado_por
    WHERE ie.id = ?
");
$stmt->bind_param('i', $informe_id);
$stmt->execute();
$informe = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$informe) {
    http_response_code(404);
    echo json_encode(['error' => 'Informe no encontrado']);
    exit();
}

// El paciente sólo puede ver SUS propios informes y únicamente finalizados/firmados.
if ($sesion_rol === 'paciente') {
    if ((int)$informe['paciente_id'] !== $sesion_uid
        || !in_array($informe['estado'], ['finalizado', 'firmado'], true)) {
        http_response_code(403);
        echo json_encode(['error' => 'Acceso no autorizado']);
        exit();
    }
}

// Bitácora de acceso a datos clínicos (cumplimiento): quién consultó este informe.
eco_auditar($conex, 'acceso_informe', [
    'entidad'    => 'informe',
    'entidad_id' => $informe_id,
    'detalle'    => ['paciente' => $informe['paciente_nombre'] ?? '', 'numero' => $informe['numero_informe'] ?? ''],
]);

/* Contenido clinico solo para quien deba verlo. La recepcion abre esta misma
   modal para su trabajo administrativo, asi que en vez de un 403 —que le
   romperia la pantalla— recibe la ficha sin los hallazgos y un aviso claro.
   Los datos clinicos ni siquiera se decodifican si no se van a enviar. */
$ve_clinico = eco_puede_ver_clinico(
    $sesion_rol, $sesion_uid,
    (int)$informe['paciente_id'], (string)$informe['estado'],
    $conex, (int)$informe['ecografista_id']
);

if ($ve_clinico) {
    $esquema        = json_decode($informe['esquema_campos'], true) ?: ['secciones' => []];
    $datos_clinicos = json_decode($informe['datos_clinicos'], true) ?: [];
    // Renderizar en modo solo lectura
    $html = eco_render_formulario($esquema, $datos_clinicos, true);
} else {
    $html = '<div class="inf-det-sin-clinico">'
        . '<i class="fa-solid fa-lock" aria-hidden="true"></i>'
        . '<p><strong>Contenido clínico restringido</strong></p>'
        . '<p>Los hallazgos y conclusiones del estudio solo puede consultarlos el personal clínico. '
        . 'Aquí tienes los datos del informe para gestión y facturación.</p>'
        . '</div>';
}

$fecha_raw = $informe['fecha_estudio'] ?: substr($informe['creado_en'], 0, 10);
$fecha_fmt = $fecha_raw ? date('d/m/Y', strtotime($fecha_raw)) : '—';

$estados_labels = [
    'borrador'   => 'Borrador',
    'finalizado' => 'Finalizado',
    'firmado'    => 'Firmado',
    'anulado'    => 'Anulado',
];

$fmt_dt = static function ($v) {
    return $v ? date('d/m/Y H:i', strtotime($v)) : null;
};
$puede_gestionar = eco_puede_gestionar_informe($sesion_rol, $sesion_uid, (int)$informe['ecografista_id']);

echo json_encode([
    'html'            => $html,
    'clinico_visible' => $ve_clinico,   // el cliente oculta imprimir/descargar si es false
    'informe' => [
        'id'              => (int)$informe['id'],
        'numero_informe'  => $informe['numero_informe'] ?? '—',
        'fecha_formateada'=> $fecha_fmt,
        'estado'          => $informe['estado'],
        'estado_label'    => $estados_labels[$informe['estado']] ?? $informe['estado'],
        'ecografista_id'  => (int)$informe['ecografista_id'],
        'puede_gestionar' => $puede_gestionar,
        'firma'           => $informe['estado'] === 'firmado'
            ? ['por' => $informe['firmante_nombre'] ?? '—', 'fecha' => $fmt_dt($informe['fecha_firma'])]
            : null,
        'anulacion'       => $informe['estado'] === 'anulado'
            ? ['por' => $informe['anulador_nombre'] ?? '—', 'fecha' => $fmt_dt($informe['fecha_anulacion']), 'motivo' => $informe['motivo_anulacion'] ?? '']
            : null,
    ],
    'tipo' => [
        'nombre'   => $informe['tipo_nombre']    ?? 'Ecografía',
        'icono'    => $informe['tipo_icono']     ?? 'fa-solid fa-wave-square',
        'categoria'=> $informe['tipo_categoria'] ?? '',
    ],
    'paciente' => [
        'nombre' => $informe['paciente_nombre'] ?? '—',
        'cedula' => $informe['paciente_cedula'] ?? '—',
        'edad'   => $informe['paciente_edad']   ?? '—',
    ],
    'ecografista' => $informe['ecografista_nombre'] ?? '—',
], JSON_UNESCAPED_UNICODE);

$conex->close();
