<?php
session_start();
include __DIR__ . '/../core/conexion.php';
require_once __DIR__ . '/../lib/core/table_sort_helpers.php';

if (!isset($_SESSION['usuario_id']) || ($_SESSION['rol'] ?? '') !== 'administrador') {
    http_response_code(403);
    exit('<p class="vu-users-empty">Acceso denegado.</p>');
}

function vu_iniciales(string $nombre): string
{
    $ini = '';
    foreach (preg_split('/\s+/u', trim($nombre)) as $part) {
        if ($part !== '' && mb_strlen($ini) < 2) {
            $ini .= mb_strtoupper(mb_substr($part, 0, 1));
        }
    }
    return $ini !== '' ? $ini : '?';
}

function vu_rol_meta(string $rol): array
{
    $map = [
        'ecografista'   => ['Ecografista', 'vu-rol-badge--eco', 'rx-pac-avatar--eco'],
        'recepcionista' => ['Recepcionista', 'vu-rol-badge--rx', 'rx-pac-avatar--rx'],
        'paciente'      => ['Paciente', '', 'rx-pac-avatar--pat'],
        'administrador' => ['Administrador', '', 'rx-pac-avatar--admin'],
    ];
    return $map[$rol] ?? [ucfirst($rol), '', 'rx-pac-avatar--admin'];
}

function vu_estado_label(string $estado): string
{
    $map = [
        'aprobado'     => 'Aprobado',
        'inhabilitado' => 'Inhabilitado',
        'pendiente'    => 'Pendiente',
    ];
    return $map[$estado] ?? ucfirst($estado);
}

$termino_busqueda = isset($_POST['query']) ? trim((string)$_POST['query']) : '';
$filtro = isset($_POST['filtro']) ? trim((string)$_POST['filtro']) : 'aprobados';
$busqueda_like = '%' . $termino_busqueda . '%';
$session_user_id = (int)$_SESSION['usuario_id'];

$sql = '';
$sqlCount = '';
$types = 'ss';
$params = [$busqueda_like, $busqueda_like];

switch ($filtro) {
    case 'pendientes':
        $base = "FROM usuarios WHERE estado = 'pendiente' AND (nombre_completo LIKE ? OR cedula LIKE ?)";
        break;
    case 'personal':
        $base = "FROM usuarios WHERE rol IN ('ecografista', 'recepcionista') AND estado IN ('aprobado', 'inhabilitado') AND (nombre_completo LIKE ? OR cedula LIKE ?)";
        break;
    case 'doctores':
        $base = "FROM usuarios WHERE rol = 'ecografista' AND estado IN ('aprobado', 'inhabilitado') AND (nombre_completo LIKE ? OR cedula LIKE ?)";
        break;
    case 'pacientes':
        $base = "FROM usuarios WHERE rol = 'paciente' AND estado IN ('aprobado', 'inhabilitado') AND (nombre_completo LIKE ? OR cedula LIKE ?)";
        break;
    case 'aprobados':
    default:
        $base = "FROM usuarios WHERE estado IN ('aprobado', 'inhabilitado') AND (nombre_completo LIKE ? OR cedula LIKE ?)";
        break;
}

$sql = "SELECT id, nombre_completo, correo, cedula, rol, estado {$base} ORDER BY rol, nombre_completo ASC";
$sqlCount = "SELECT COUNT(*) AS total {$base}";

$stmtCount = $conex->prepare($sqlCount);
$stmtCount->bind_param($types, ...$params);
$stmtCount->execute();
$totalFiltrado = (int)($stmtCount->get_result()->fetch_assoc()['total'] ?? 0);
$stmtCount->close();

$stmt = $conex->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$resultado = $stmt->get_result();

if (!$resultado || $resultado->num_rows === 0) {
    echo '<p class="vu-users-empty" data-vu-total="0"><i class="fa-solid fa-users-slash"></i>No se encontraron usuarios con estos criterios.</p>';
    $stmt->close();
    $conex->close();
    exit;
}

echo '<div class="table-responsive" data-vu-total="' . $totalFiltrado . '">';
echo '<table class="rx-pac-table">';
echo '<colgroup>';
echo '<col class="col-usuario"><col class="col-cedula"><col class="col-correo"><col class="col-rol"><col class="col-estado"><col class="col-acciones">';
echo '</colgroup>';
echo '<thead><tr>';
echo eco_sort_th('Usuario', 0, 'text');
echo eco_sort_th('Cédula', 1, 'number');
echo eco_sort_th('Correo', 2, 'text');
echo eco_sort_th('Rol', 3, 'text');
echo eco_sort_th('Estado', 4, 'text');
echo '<th class="rx-th-acciones">Acciones</th>';
echo '</tr></thead><tbody>';

while ($usuario = $resultado->fetch_assoc()) {
    $id = (int)$usuario['id'];
    $nombre = (string)($usuario['nombre_completo'] ?? '');
    $rol = (string)($usuario['rol'] ?? '');
    $estado = (string)($usuario['estado'] ?? '');
    [$rolLabel, $rolBadge, $avatarClass] = vu_rol_meta($rol);

    $sortNombre = htmlspecialchars(mb_strtolower(trim($nombre), 'UTF-8'), ENT_QUOTES, 'UTF-8');
    $cedulaDigits = preg_replace('/\D/', '', (string)($usuario['cedula'] ?? ''));
    $sortCedula = htmlspecialchars($cedulaDigits !== '' ? $cedulaDigits : '0', ENT_QUOTES, 'UTF-8');
    $sortCorreo = htmlspecialchars(mb_strtolower(trim((string)($usuario['correo'] ?? '')), 'UTF-8'), ENT_QUOTES, 'UTF-8');
    $sortRol = htmlspecialchars(mb_strtolower($rol, 'UTF-8'), ENT_QUOTES, 'UTF-8');
    $sortEstado = htmlspecialchars(mb_strtolower($estado, 'UTF-8'), ENT_QUOTES, 'UTF-8');

    echo '<tr>';
    echo '<td class="rx-pac-td-nombre" data-sort-value="' . $sortNombre . '">';
    echo '<div class="rx-pac-cell-nombre">';
    echo '<span class="rx-pac-avatar ' . htmlspecialchars($avatarClass) . '" aria-hidden="true">' . htmlspecialchars(vu_iniciales($nombre)) . '</span>';
    echo '<strong>' . htmlspecialchars($nombre) . '</strong>';
    echo '</div></td>';
    echo '<td class="rx-pac-td-cedula" data-sort-value="' . $sortCedula . '">' . htmlspecialchars($usuario['cedula'] ?: '—') . '</td>';
    echo '<td class="rx-pac-td-email" data-sort-value="' . $sortCorreo . '">' . htmlspecialchars($usuario['correo'] ?: '—') . '</td>';
    echo '<td data-sort-value="' . $sortRol . '"><span class="vu-rol-badge ' . htmlspecialchars($rolBadge) . '">' . htmlspecialchars($rolLabel) . '</span></td>';
    echo '<td data-sort-value="' . $sortEstado . '"><span class="vu-estado-badge vu-estado-badge--' . htmlspecialchars($estado) . '">' . htmlspecialchars(vu_estado_label($estado)) . '</span></td>';
    echo '<td class="rx-td-acciones"><div class="acciones-wrapper">';

    if ($session_user_id !== $id) {
        if ($estado === 'pendiente') {
            echo '<button type="button" class="vu-btn vu-btn--approve" onclick="toggleUserState(' . $id . ', \'aprobado\')"><i class="fa-solid fa-circle-check"></i> Aprobar</button>';
        } elseif ($estado === 'aprobado') {
            echo '<button type="button" class="vu-btn vu-btn--warn" onclick="toggleUserState(' . $id . ', \'inhabilitado\')"><i class="fa-solid fa-user-slash"></i> Inhabilitar</button>';
        } elseif ($estado === 'inhabilitado') {
            echo '<button type="button" class="vu-btn vu-btn--ok" onclick="toggleUserState(' . $id . ', \'aprobado\')"><i class="fa-solid fa-user-check"></i> Habilitar</button>';
        }
        // Ruta absoluta: estas filas se inyectan por AJAX en /usuarios, así que
        // una acción relativa la resolvería el navegador contra ESA página y no
        // contra api/, dando 404.
        echo '<form method="post" action="' . htmlspecialchars(eco_url('api/reset_password.php')) . '" style="display:inline" onsubmit="return confirm(\'¿Restablecer la contraseña? Se generará una temporal.\');">' . csrf_field() . '<input type="hidden" name="id" value="' . (int)$id . '"><input type="hidden" name="filtro" value="' . htmlspecialchars($filtro, ENT_QUOTES) . '"><button type="submit" class="vu-btn vu-btn--reset"><i class="fa-solid fa-key"></i> Restablecer</button></form>';
    } else {
        echo '<span class="vu-self-note" style="font-size:11px;color:var(--text-muted);">Tu cuenta</span>';
    }

    echo '</div></td></tr>';
}

echo '</tbody></table></div>';

$stmt->close();
$conex->close();
