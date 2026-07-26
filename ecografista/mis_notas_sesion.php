<?php
session_start();
include __DIR__ . '/../core/conexion.php';
require_once __DIR__ . '/../lib/core/table_sort_helpers.php';

if (!isset($_SESSION['usuario_id'])) { header('Location: ' . eco_url('login')); exit; }
if ($_SESSION['rol'] !== 'ecografista') { header('Location: ' . eco_url('dashboard')); exit; }

$ecografista_id = (int)$_SESSION['usuario_id'];

/* Lista de pacientes con todos sus datos + conteo de notas */
$pacientes = [];
$sql = "SELECT DISTINCT u.id, u.nombre_completo, u.correo, u.cedula, u.direccion, u.telefono, u.fecha_registro,
               TIMESTAMPDIFF(YEAR, u.fecha_nacimiento, CURDATE()) AS edad,
               (SELECT COUNT(*) FROM citas c2 WHERE c2.paciente_id=u.id AND c2.ecografista_id=?) AS total_citas,
               (SELECT COUNT(*) FROM informes_estudios ie WHERE ie.paciente_id=u.id) AS total_informes,
               (SELECT COUNT(*) FROM notas_clinicas n WHERE n.paciente_id=u.id) AS total_notas,
               (SELECT MAX(n.fecha_sesion) FROM notas_clinicas n WHERE n.paciente_id=u.id) AS ultima_nota
        FROM usuarios u
        LEFT JOIN citas c ON u.id=c.paciente_id
        WHERE u.rol='paciente' AND u.estado='aprobado'
              AND (u.creado_por_id=? OR c.ecografista_id=?)
        ORDER BY ultima_nota DESC, u.nombre_completo ASC";
if ($s = $conex->prepare($sql)) {
    $s->bind_param('iii', $ecografista_id, $ecografista_id, $ecografista_id);
    $s->execute();
    $pacientes = $s->get_result()->fetch_all(MYSQLI_ASSOC);
    $s->close();
}

$page_title    = 'Notas de Sesión';
$page_subtitle = 'Cuaderno clínico privado por paciente';
$active_section = 'notas-sesion';

$page_head_extra = '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">'
    . '<link rel="stylesheet" href="assets/css/recepcion/recepcion-gestion-pacientes.css">';

ob_start();
?>

<?php if (empty($pacientes)): ?>
    <div class="card" style="text-align:center;padding:60px 20px;">
        <i class="fa-solid fa-notes-medical" style="font-size:3rem;color:var(--text-muted);opacity:.4;margin-bottom:14px;"></i>
        <h3 style="margin:0 0 6px;color:var(--text-primary);">Aún no tienes pacientes</h3>
        <p style="color:var(--text-secondary);margin:0;font-size:13.5px;">Cuando tengas pacientes asignados podrás añadirles notas clínicas privadas.</p>
    </div>
<?php else: ?>

<div class="card" style="padding:14px 18px;margin-bottom:14px;">
    <div style="position:relative;">
        <i class="fa-solid fa-magnifying-glass" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-muted);"></i>
        <input type="search" id="notas-search" placeholder="Buscar por nombre, cédula, correo, teléfono o dirección..."
               style="width:100%;padding:10px 14px 10px 40px;border:1.5px solid var(--border);border-radius:8px;font-family:inherit;font-size:13.5px;background:var(--bg-surface);color:var(--text-primary);box-sizing:border-box;">
    </div>
</div>

<div class="card" id="notas-list-card" style="padding:0;overflow:hidden;">
    <div class="rx-pac-wrap data-table table-responsive" style="border:none;">
        <table class="rx-pac-table eco-notas-table">
            <colgroup>
                <col class="col-notas-paciente"><col class="col-notas-cedula"><col class="col-notas-edad">
                <col class="col-notas-correo"><col class="col-notas-telefono"><col class="col-notas-direccion">
                <col class="col-notas-citas"><col class="col-notas-informes"><col class="col-notas-ingreso">
                <col class="col-notas-notas"><col class="col-notas-ultima"><col class="col-notas-accion">
            </colgroup>
            <thead>
                <tr>
                    <?= eco_sort_th('Paciente', 0, 'text') ?>
                    <?= eco_sort_th('Cédula', 1, 'number') ?>
                    <?= eco_sort_th('Edad', 2, 'number') ?>
                    <?= eco_sort_th('Correo', 3, 'text') ?>
                    <?= eco_sort_th('Teléfono', 4, 'text') ?>
                    <?= eco_sort_th('Dirección', 5, 'text') ?>
                    <th>Citas</th>
                    <th>Informes</th>
                    <?= eco_sort_th('Ingreso', 8, 'date') ?>
                    <th>Notas</th>
                    <?= eco_sort_th('Última nota', 10, 'date') ?>
                    <th class="rx-th-acciones">Acción</th>
                </tr>
            </thead>
            <tbody id="tbody-notas">
            <?php foreach ($pacientes as $p):
                $iniciales = '';
                foreach (explode(' ', trim($p['nombre_completo'])) as $part) if (strlen($iniciales) < 2 && $part !== '') $iniciales .= strtoupper($part[0]);
                $fecha_ing = $p['fecha_registro'] ? date('d/m/Y', strtotime($p['fecha_registro'])) : '—';
                $ultima = $p['ultima_nota'] ? date('d/m/Y', strtotime($p['ultima_nota'])) : '—';
                $busqueda = strtolower(($p['nombre_completo']??'') . ' ' . ($p['cedula']??'') . ' ' . ($p['correo']??'') . ' ' . ($p['telefono']??'') . ' ' . ($p['direccion']??''));
                $sortNombre = htmlspecialchars(mb_strtolower(trim((string)$p['nombre_completo']), 'UTF-8'), ENT_QUOTES, 'UTF-8');
                $cedulaDigits = preg_replace('/\D/', '', (string)($p['cedula'] ?? ''));
                $sortCedula = htmlspecialchars($cedulaDigits !== '' ? $cedulaDigits : '0', ENT_QUOTES, 'UTF-8');
                $sortEdad = htmlspecialchars($p['edad'] ? (string)(int)$p['edad'] : '0', ENT_QUOTES, 'UTF-8');
                $sortCorreo = htmlspecialchars(mb_strtolower(trim((string)($p['correo'] ?? '')), 'UTF-8'), ENT_QUOTES, 'UTF-8');
                $sortTelefono = htmlspecialchars(mb_strtolower(trim((string)($p['telefono'] ?? '')), 'UTF-8'), ENT_QUOTES, 'UTF-8');
                $sortDireccion = htmlspecialchars(mb_strtolower(trim((string)($p['direccion'] ?? '')), 'UTF-8'), ENT_QUOTES, 'UTF-8');
                $sortIngreso = $p['fecha_registro'] ? htmlspecialchars(date('Y-m-d', strtotime($p['fecha_registro'])), ENT_QUOTES, 'UTF-8') : '';
                $sortUltima = $p['ultima_nota'] ? htmlspecialchars(date('Y-m-d', strtotime($p['ultima_nota'])), ENT_QUOTES, 'UTF-8') : '';
            ?>
                <tr class="nota-row" data-search="<?= htmlspecialchars($busqueda) ?>">
                    <td class="rx-pac-td-nombre" data-sort-value="<?= $sortNombre ?>">
                        <div class="rx-pac-cell-nombre">
                            <span class="rx-pac-avatar" aria-hidden="true"><?= htmlspecialchars($iniciales ?: '?') ?></span>
                            <strong><?= htmlspecialchars($p['nombre_completo']) ?></strong>
                        </div>
                    </td>
                    <td class="rx-pac-td-cedula" data-sort-value="<?= $sortCedula ?>"><?= htmlspecialchars($p['cedula'] ?: '—') ?></td>
                    <td class="rx-pac-td-edad" data-sort-value="<?= $sortEdad ?>"><?= $p['edad'] ? (int)$p['edad'] . ' años' : '—' ?></td>
                    <td class="rx-pac-td-email" data-sort-value="<?= $sortCorreo ?>"><?= htmlspecialchars($p['correo'] ?: '—') ?></td>
                    <td class="rx-pac-td-telefono" data-sort-value="<?= $sortTelefono ?>"><?= htmlspecialchars($p['telefono'] ?: '—') ?></td>
                    <td class="rx-pac-td-direccion" data-sort-value="<?= $sortDireccion ?>"><?= htmlspecialchars($p['direccion'] ?: '—') ?></td>
                    <td><span class="badge badge-accent"><?= (int)$p['total_citas'] ?></span></td>
                    <td><span class="badge badge-purple"><?= (int)$p['total_informes'] ?></span></td>
                    <td class="rx-pac-td-ingreso" data-sort-value="<?= $sortIngreso ?>"><?= htmlspecialchars($fecha_ing) ?></td>
                    <td><span class="badge nota-count-badge <?= $p['total_notas'] > 0 ? 'badge-accent' : 'badge-info' ?>"><?= (int)$p['total_notas'] ?></span></td>
                    <td class="rx-pac-td-ingreso" data-sort-value="<?= $sortUltima ?>"><?= htmlspecialchars($ultima) ?></td>
                    <td class="rx-td-acciones" style="white-space:nowrap;">
                        <button type="button" onclick='abrirNotasPacienteEco(<?= (int)$p['id'] ?>, <?= json_encode($p['nombre_completo'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' class="btn-primary" style="padding:6px 12px;font-size:12px;">
                            <i class="fa-solid fa-notes-medical"></i> Ver / Añadir
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; ?>

<?php include __DIR__ . '/../layouts/partials/modal_gestionar_paciente_ecografista.php'; ?>

<script>
/* Buscador */
(function(){
    const s = document.getElementById('notas-search');
    if (!s) return;
    const rows = document.querySelectorAll('.nota-row');
    s.addEventListener('input', () => {
        const q = s.value.trim().toLowerCase();
        rows.forEach(r => { r.style.display = r.dataset.search.includes(q) ? '' : 'none'; });
    });
})();

document.addEventListener('eco:notas-changed', function (event) {
    const detail = event.detail || {};
    const fila = document.querySelector(`#tbody-notas .nota-row button[onclick*="abrirNotasPacienteEco(${detail.pacienteId},"]`);
    if (!fila) return;
    const badge = fila.closest('tr').querySelector('.nota-count-badge');
    if (!badge) return;
    if (detail.action === 'clear') badge.textContent = '0';
    if (detail.action === 'add') badge.textContent = String(parseInt(badge.textContent || '0', 10) + 1);
    badge.classList.remove('badge-info', 'badge-accent');
    badge.classList.add(parseInt(badge.textContent, 10) > 0 ? 'badge-accent' : 'badge-info');
});
</script>

<?php
$page_content = ob_get_clean();
$page_scripts_extra = <<<'HTML'
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
<script src="assets/js/panel/eco-table-sort.js"></script>
<script src="assets/js/panel/ecografista-modals.js?v=auto"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var card = document.getElementById('notas-list-card');
    if (card && window.EcoTableSort) { EcoTableSort.init(card); }
});
</script>
HTML;
include __DIR__ . '/../layouts/shell.php';
