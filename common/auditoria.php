<?php
/**
 * auditoria.php — Bitácora de auditoría (cumplimiento). Solo administrador.
 * Por defecto muestra los accesos a datos clínicos (acceso_*); permite ver todo.
 */
session_start();
include __DIR__ . '/../core/conexion.php';
require_once __DIR__ . '/../lib/core/paginacion.php';

if (!isset($_SESSION['usuario_id'])) { header('Location: ' . eco_url('login')); exit; }
if (($_SESSION['rol'] ?? '') !== 'administrador') { header('Location: ' . eco_url('dashboard')); exit; }

$grupo = in_array($_GET['grupo'] ?? '', ['clinico', 'todos'], true) ? $_GET['grupo'] : 'clinico';
$qs_q  = trim((string)($_GET['q'] ?? ''));
$validFecha = static fn($d) => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) === 1;
$desde = $validFecha($_GET['desde'] ?? '') ? $_GET['desde'] : '';
$hasta = $validFecha($_GET['hasta'] ?? '') ? $_GET['hasta'] : '';

// WHERE dinámico
$cond = []; $types = ''; $args = [];
if ($grupo === 'clinico') {
    $cond[] = "a.accion LIKE 'acceso_%'";
}
if ($desde !== '') { $cond[] = 'a.creado_en >= ?'; $types .= 's'; $args[] = $desde . ' 00:00:00'; }
if ($hasta !== '') { $cond[] = 'a.creado_en <= ?'; $types .= 's'; $args[] = $hasta . ' 23:59:59'; }
if ($qs_q !== '')  { $cond[] = 'u.nombre_completo LIKE ?'; $types .= 's'; $args[] = '%' . $qs_q . '%'; }
$where = $cond ? ('WHERE ' . implode(' AND ', $cond)) : '';

// Total + página
[$page, $perPage, $offset] = eco_paginacion_args(30, 100);

$total = 0;
$cstmt = $conex->prepare("SELECT COUNT(*) AS n FROM auditoria a LEFT JOIN usuarios u ON u.id = a.usuario_id $where");
if ($types !== '') { $cstmt->bind_param($types, ...$args); }
$cstmt->execute();
$total = (int)($cstmt->get_result()->fetch_assoc()['n'] ?? 0);
$cstmt->close();

$sql = "SELECT a.creado_en, a.accion, a.entidad, a.entidad_id, a.detalle, a.ip,
               u.nombre_completo AS usuario, u.rol AS usuario_rol
        FROM auditoria a
        LEFT JOIN usuarios u ON u.id = a.usuario_id
        $where
        ORDER BY a.creado_en DESC
        LIMIT ? OFFSET ?";
$dtypes = $types . 'ii'; $dargs = array_merge($args, [$perPage, $offset]);
$stmt = $conex->prepare($sql);
$stmt->bind_param($dtypes, ...$dargs);
$stmt->execute();
$filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conex->close();

$accion_label = [
    // En rojo y con nombre explícito: es el acceso de un ecografista a un
    // paciente fuera de su ámbito, justificado por él. El motivo que escribió
    // viaja en la columna "detalle". Es la línea que hay que mirar primero.
    'acceso_excepcional_concedido' => ['ACCESO EXCEPCIONAL', '#b91c1c'],
    'acceso_informe_denegado'      => ['Acceso denegado',    '#b91c1c'],
    // Cambiar de tarifa mueve todos los precios de una vez: se distingue del
    // cambio de un precio suelto.
    'lista_precios_aplicada'  => ['CAMBIÓ DE TARIFA',     '#b45309'],
    'lista_precios_creada'    => ['Guardó una tarifa',    '#0f766e'],
    'lista_precios_eliminada' => ['Borró una tarifa',     '#64748b'],
    'precio_actualizado'      => ['Cambió un precio',     '#b45309'],
    'acceso_historia_clinica' => ['Vio historia clínica', '#7c3aed'],
    'acceso_informe'          => ['Vio informe',          '#0284c7'],
    'acceso_ficha_paciente'   => ['Vio ficha de paciente','#0f766e'],
    'consentimiento_aceptado' => ['Aceptó consentimiento','#15803d'],
    '2fa_modificado'          => ['Cambió 2FA',           '#b45309'],
    'password_cambiado'       => ['Cambió contraseña',    '#b45309'],
    'login_2fa_enviado'       => ['2FA enviado',          '#64748b'],
];

$pages = (int)max(1, (int)ceil($total / max(1, $perPage)));
if ($page > $pages) { $page = $pages; }
$qbase = static function (array $over) use ($grupo, $desde, $hasta, $qs_q) {
    $p = array_merge(['grupo' => $grupo, 'desde' => $desde, 'hasta' => $hasta, 'q' => $qs_q], $over);
    return 'auditoria.php?' . http_build_query(array_filter($p, static fn($v) => $v !== '' && $v !== null));
};

$page_title     = 'Bitácora de auditoría';
$page_subtitle  = 'Registro de accesos a datos clínicos y acciones sensibles';
$active_section = 'auditoria';

ob_start();
?>
<div class="card" style="padding:16px 18px;margin-bottom:16px;">
    <form method="get" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
        <div>
            <label style="display:block;font-size:12px;color:var(--text-secondary);margin-bottom:4px;font-weight:600;">Mostrar</label>
            <select name="grupo" style="padding:8px 10px;border:1px solid var(--border);border-radius:8px;background:var(--bg-surface);color:var(--text-primary);">
                <option value="clinico" <?= $grupo === 'clinico' ? 'selected' : '' ?>>Accesos clínicos</option>
                <option value="todos" <?= $grupo === 'todos' ? 'selected' : '' ?>>Todas las acciones</option>
            </select>
        </div>
        <div>
            <label style="display:block;font-size:12px;color:var(--text-secondary);margin-bottom:4px;font-weight:600;">Desde</label>
            <input type="date" name="desde" value="<?= htmlspecialchars($desde) ?>" style="padding:8px 10px;border:1px solid var(--border);border-radius:8px;background:var(--bg-surface);color:var(--text-primary);">
        </div>
        <div>
            <label style="display:block;font-size:12px;color:var(--text-secondary);margin-bottom:4px;font-weight:600;">Hasta</label>
            <input type="date" name="hasta" value="<?= htmlspecialchars($hasta) ?>" style="padding:8px 10px;border:1px solid var(--border);border-radius:8px;background:var(--bg-surface);color:var(--text-primary);">
        </div>
        <div style="flex:1;min-width:180px;">
            <label style="display:block;font-size:12px;color:var(--text-secondary);margin-bottom:4px;font-weight:600;">Usuario</label>
            <input type="text" name="q" value="<?= htmlspecialchars($qs_q) ?>" placeholder="Nombre del usuario…" style="width:100%;padding:8px 10px;border:1px solid var(--border);border-radius:8px;background:var(--bg-surface);color:var(--text-primary);box-sizing:border-box;">
        </div>
        <button type="submit" class="btn-primary" style="padding:9px 16px;"><i class="fa-solid fa-filter"></i> Filtrar</button>
        <a class="btn-secondary" style="padding:9px 14px;" href="<?= eco_url('auditoria') ?>"><i class="fa-solid fa-rotate-left"></i> Limpiar</a>
    </form>
</div>

<div class="card" style="padding:18px;">
    <h3 style="margin:0 0 12px;font-size:15px;color:var(--text-primary);"><i class="fa-solid fa-clipboard-list" style="color:var(--accent);"></i> <?= number_format($total) ?> registro(s)</h3>
    <div style="overflow-x:auto;">
    <table class="eco-table" style="width:100%;border-collapse:collapse;font-size:13px;">
        <thead><tr style="text-align:left;color:var(--text-secondary);border-bottom:1px solid var(--border-soft);">
            <th style="padding:8px 6px;white-space:nowrap;">Fecha</th>
            <th style="padding:8px 6px;">Usuario</th>
            <th style="padding:8px 6px;">Acción</th>
            <th style="padding:8px 6px;">Paciente / entidad</th>
            <th style="padding:8px 6px;">IP</th>
        </tr></thead>
        <tbody>
        <?php if (empty($filas)): ?>
            <tr><td colspan="5" style="padding:14px 6px;color:var(--text-secondary);">Sin registros para el filtro seleccionado.</td></tr>
        <?php else: foreach ($filas as $f):
            $det = $f['detalle'] ? json_decode($f['detalle'], true) : null;
            $pac = is_array($det) ? ($det['paciente'] ?? '') : '';
            [$lbl, $col] = $accion_label[$f['accion']] ?? [ucfirst(str_replace('_', ' ', (string)$f['accion'])), '#64748b'];
            $ent = $f['entidad'] ? ($f['entidad'] . ($f['entidad_id'] ? ' #' . (int)$f['entidad_id'] : '')) : '';
        ?>
            <tr style="border-bottom:1px solid var(--border-soft);">
                <td style="padding:8px 6px;white-space:nowrap;color:var(--text-secondary);"><?= htmlspecialchars(date('d/m/Y H:i', strtotime((string)$f['creado_en']))) ?></td>
                <td style="padding:8px 6px;color:var(--text-primary);">
                    <?= htmlspecialchars($f['usuario'] ?? '—') ?>
                    <?php if (!empty($f['usuario_rol'])): ?><span style="color:var(--text-secondary);font-size:11.5px;"> · <?= htmlspecialchars(ucfirst($f['usuario_rol'])) ?></span><?php endif; ?>
                </td>
                <td style="padding:8px 6px;"><span style="display:inline-block;padding:2px 9px;border-radius:999px;font-size:11.5px;font-weight:600;background:<?= $col ?>1a;color:<?= $col ?>;"><?= htmlspecialchars($lbl) ?></span></td>
                <td style="padding:8px 6px;color:var(--text-primary);"><?= htmlspecialchars($pac !== '' ? $pac : $ent) ?><?php if ($pac !== '' && $ent !== ''): ?><span style="color:var(--text-secondary);font-size:11.5px;"> · <?= htmlspecialchars($ent) ?></span><?php endif; ?></td>
                <td style="padding:8px 6px;color:var(--text-secondary);font-size:12px;"><?= htmlspecialchars($f['ip'] ?? '') ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
    </div>

    <?php if ($pages > 1): ?>
    <nav style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:16px;flex-wrap:wrap;">
        <a class="btn-secondary" style="padding:8px 14px;<?= $page <= 1 ? 'pointer-events:none;opacity:.5;' : '' ?>" href="<?= htmlspecialchars($qbase(['page' => $page - 1])) ?>"><i class="fa-solid fa-chevron-left"></i> Anterior</a>
        <span style="font-size:13px;color:var(--text-secondary);">Página <?= $page ?> de <?= $pages ?></span>
        <a class="btn-secondary" style="padding:8px 14px;<?= $page >= $pages ? 'pointer-events:none;opacity:.5;' : '' ?>" href="<?= htmlspecialchars($qbase(['page' => $page + 1])) ?>">Siguiente <i class="fa-solid fa-chevron-right"></i></a>
    </nav>
    <?php endif; ?>
</div>
<?php
$page_content = ob_get_clean();
include __DIR__ . '/../layouts/shell.php';
