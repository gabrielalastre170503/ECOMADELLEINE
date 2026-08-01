<?php
/**
 * Buscador de la barra superior.
 *
 * Busca pacientes, citas e informes y devuelve, con cada resultado, el destino
 * al que lleva. El alcance y el destino dependen del rol:
 *   · recepcionista  → todo; el paciente abre su ficha
 *   · administrador  → todo; el paciente abre el listado de usuarios filtrado
 *   · ecografista    → solo lo suyo; el paciente abre su listado filtrado
 *   · paciente       → solo sus citas e informes
 *
 * GET q (mín. 2 caracteres). Respuesta: {ok, q, total, grupos:[{tipo,titulo,items}]}
 */
session_start();
require_once __DIR__ . '/../lib/core/api.php';
include __DIR__ . '/../core/conexion.php';
api_json();

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Sesión no iniciada']);
    exit;
}

$rol = (string)($_SESSION['rol'] ?? '');
$uid = (int)$_SESSION['usuario_id'];
$q   = trim((string)($_GET['q'] ?? ''));

if (mb_strlen($q, 'UTF-8') < 2) {
    echo json_encode(['ok' => true, 'q' => $q, 'total' => 0, 'grupos' => []]);
    exit;
}

$like  = '%' . $q . '%';
$LIMIT = 5;                       // por grupo: la lista tiene que caber sin desplazarse
$grupos = [];

/** Enlace destino de un paciente según quién busca. */
$url_paciente = static function (array $p) use ($rol): ?string {
    switch ($rol) {
        case 'recepcionista': return eco_url('ficha-paciente') . '?id=' . (int)$p['id'];
        case 'administrador': return eco_url('usuarios') . '?q=' . rawurlencode((string)$p['cedula']);
        case 'ecografista':   return eco_url('mis-pacientes') . '?q=' . rawurlencode((string)$p['cedula']);
    }
    return null;
};

/* ── Pacientes ── (el paciente no busca a otros pacientes) */
if ($rol !== 'paciente') {
    if ($rol === 'ecografista') {
        /* Antes solo salían los suyos, y por eso un paciente ajeno era
           inalcanzable: no se podía ni pedir acceso. Ahora salen todos, con
           'propio' marcando cuáles están bajo su atención; los demás se
           muestran como fuera de su ámbito y abrirlos exige justificarlo.
           Los suyos van primero. La condición de "propio" es la misma que
           eco_ecografista_atiende(): creado por él, cita o informe. */
        $sql = "SELECT u.id, u.nombre_completo, u.cedula, u.correo, u.telefono,
                       (u.creado_por_id = ?
                        OR EXISTS (SELECT 1 FROM citas c
                                    WHERE c.paciente_id = u.id AND c.ecografista_id = ?)
                        OR EXISTS (SELECT 1 FROM informes_estudios i
                                    WHERE i.paciente_id = u.id AND i.ecografista_id = ?)) AS propio
                  FROM usuarios u
                 WHERE u.rol = 'paciente'
                   AND (u.nombre_completo LIKE ? OR u.cedula LIKE ? OR u.correo LIKE ?)
                 ORDER BY propio DESC, u.nombre_completo LIMIT $LIMIT";
        $st = $conex->prepare($sql);
        $st->bind_param('iiisss', $uid, $uid, $uid, $like, $like, $like);
    } else {
        $sql = "SELECT u.id, u.nombre_completo, u.cedula, u.correo, u.telefono
                  FROM usuarios u
                 WHERE u.rol = 'paciente'
                   AND (u.nombre_completo LIKE ? OR u.cedula LIKE ? OR u.correo LIKE ?)
                 ORDER BY u.nombre_completo LIMIT $LIMIT";
        $st = $conex->prepare($sql);
        $st->bind_param('sss', $like, $like, $like);
    }
    $st->execute();
    $filas = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();

    $items = [];
    foreach ($filas as $p) {
        $destino = $url_paciente($p);
        if ($destino === null) { continue; }
        $sub = trim((string)($p['cedula'] ?? '')) ?: 'Sin cédula';
        if (!empty($p['correo'])) { $sub .= ' · ' . $p['correo']; }
        // 'propio' solo lo devuelve la consulta del ecografista; para los demás
        // roles no existe la distinción y todo se considera dentro de ámbito.
        $fuera = array_key_exists('propio', $p) && !(int)$p['propio'];
        if ($fuera) { $sub .= ' · fuera de tu ámbito'; }
        $items[] = [
            'titulo'       => (string)$p['nombre_completo'],
            'sub'          => $sub,
            'icono'        => $fuera ? 'fa-solid fa-lock' : 'fa-solid fa-user',
            'url'          => $destino,
            'fuera_ambito' => $fuera,
            'paciente_id'  => (int)$p['id'],
        ];
    }
    if ($items) {
        $grupos[] = ['tipo' => 'pacientes', 'titulo' => 'Pacientes', 'items' => $items];
    }
}

/* ── Informes ── */
$where = "(i.numero_informe LIKE ? OR pac.nombre_completo LIKE ? OR pac.cedula LIKE ? OR t.nombre LIKE ?)";
$tipos = 'ssss';
$args  = [$like, $like, $like, $like];
if ($rol === 'ecografista') {
    $where .= " AND i.ecografista_id = ?";
    $tipos .= 'i';
    $args[] = $uid;
} elseif ($rol === 'paciente') {
    // Igual que la vista del informe: solo los suyos y ya cerrados.
    $where .= " AND i.paciente_id = ? AND i.estado IN ('finalizado','firmado')";
    $tipos .= 'i';
    $args[] = $uid;
}
$sql = "SELECT i.id, i.numero_informe, i.estado, i.fecha_estudio, i.creado_en,
               pac.nombre_completo AS paciente, t.nombre AS tipo
          FROM informes_estudios i
          JOIN usuarios pac ON pac.id = i.paciente_id
          LEFT JOIN tipos_ecografias t ON t.id = i.tipo_ecografia_id
         WHERE $where
         ORDER BY COALESCE(i.fecha_estudio, i.creado_en) DESC
         LIMIT $LIMIT";
$st = $conex->prepare($sql);
$st->bind_param($tipos, ...$args);
$st->execute();
$filas = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$st->close();

$items = [];
foreach ($filas as $i) {
    $fecha = $i['fecha_estudio'] ?: $i['creado_en'];
    $sub = (string)($i['numero_informe'] ?? '');
    if ($rol !== 'paciente') { $sub .= ' · ' . $i['paciente']; }
    if ($fecha) { $sub .= ' · ' . date('d/m/Y', strtotime((string)$fecha)); }
    $items[] = [
        'titulo' => (string)($i['tipo'] ?: 'Informe'),
        'sub'    => $sub,
        'icono'  => 'fa-solid fa-file-waveform',
        'url'    => eco_url('informe/' . (int)$i['id']),
    ];
}
if ($items) {
    $grupos[] = ['tipo' => 'informes', 'titulo' => 'Informes', 'items' => $items];
}

/* ── Citas ── */
$where = "(pac.nombre_completo LIKE ? OR pac.cedula LIKE ? OR c.motivo_consulta LIKE ?)";
$tipos = 'sss';
$args  = [$like, $like, $like];
if ($rol === 'ecografista') {
    $where .= " AND c.ecografista_id = ?";
    $tipos .= 'i';
    $args[] = $uid;
} elseif ($rol === 'paciente') {
    $where .= " AND c.paciente_id = ?";
    $tipos .= 'i';
    $args[] = $uid;
}
$sql = "SELECT c.id, c.fecha_cita, c.estado, c.motivo_consulta,
               c.paciente_id, pac.nombre_completo AS paciente, pac.cedula
          FROM citas c
          JOIN usuarios pac ON pac.id = c.paciente_id
         WHERE $where
         ORDER BY c.fecha_cita DESC
         LIMIT $LIMIT";
$st = $conex->prepare($sql);
$st->bind_param($tipos, ...$args);
$st->execute();
$filas = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$st->close();

/* Ninguna cita tiene página propia: se lleva a la vista donde SÍ se listan. */
$items = [];
foreach ($filas as $c) {
    switch ($rol) {
        case 'recepcionista': $destino = eco_url('ficha-paciente') . '?id=' . (int)$c['paciente_id']; break;
        case 'administrador': $destino = eco_url('citas-admin') . '?q=' . rawurlencode((string)$c['cedula']); break;
        case 'ecografista':   $destino = eco_url('historial-citas') . '?q=' . rawurlencode((string)$c['cedula']); break;
        default:              $destino = eco_url('mis-citas'); break;
    }
    $sub = $c['fecha_cita'] ? date('d/m/Y H:i', strtotime((string)$c['fecha_cita'])) : 'Sin fecha';
    $sub .= ' · ' . ucfirst((string)$c['estado']);
    if (!empty($c['motivo_consulta'])) {
        $sub .= ' · ' . mb_strimwidth((string)$c['motivo_consulta'], 0, 40, '…', 'UTF-8');
    }
    $items[] = [
        'titulo' => $rol === 'paciente' ? 'Cita' : (string)$c['paciente'],
        'sub'    => $sub,
        'icono'  => 'fa-solid fa-calendar-check',
        'url'    => $destino,
    ];
}
if ($items) {
    $grupos[] = ['tipo' => 'citas', 'titulo' => 'Citas', 'items' => $items];
}

$total = 0;
foreach ($grupos as $g) { $total += count($g['items']); }

echo json_encode(['ok' => true, 'q' => $q, 'total' => $total, 'grupos' => $grupos]);
