<?php
session_start();
include __DIR__ . '/../core/conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . eco_url('login'));
    exit;
}
if (($_SESSION['rol'] ?? '') !== 'administrador') {
    header('Location: ' . eco_url('dashboard'));
    exit;
}

$status = $_GET['status'] ?? '';
$tipos = [];
$r = $conex->query("SELECT id, codigo, nombre, categoria, descripcion, icono, activo, precio FROM tipos_ecografias ORDER BY activo DESC, categoria, nombre");
if ($r) {
    while ($row = $r->fetch_assoc()) {
        $tipos[] = $row;
    }
}

$page_title    = 'Estudios ecográficos';
$page_subtitle = 'Catálogo público y clínico de tipos de ecografía';
$active_section = 'admin-contenido';
$page_head_extra = '<link rel="stylesheet" href="assets/css/admin/admin-contenido.css">';

$page_header_actions = '<a href="' . eco_url('contenido') . '" class="btn-secondary"><i class="fa-solid fa-arrow-left"></i> Volver a contenido</a>';

ob_start();
?>

<?php if ($status === 'added'): ?>
    <div class="card" style="border-left:4px solid var(--success);background:rgba(34,197,94,.06);margin-bottom:14px;padding:12px 16px;">
        <strong style="color:#15803d;"><i class="fa-solid fa-circle-check"></i> Estudio agregado correctamente.</strong>
    </div>
<?php elseif ($status === 'deleted'): ?>
    <div class="card" style="border-left:4px solid var(--warning);background:rgba(245,158,11,.08);margin-bottom:14px;padding:12px 16px;">
        <strong style="color:#b45309;"><i class="fa-solid fa-eye-slash"></i> Estudio desactivado del catálogo.</strong>
    </div>
<?php elseif ($status === 'error'): ?>
    <div class="card" style="border-left:4px solid var(--danger);background:rgba(239,68,68,.06);margin-bottom:14px;padding:12px 16px;">
        <strong style="color:#b91c1c;"><i class="fa-solid fa-triangle-exclamation"></i> No se pudo completar la operación.</strong>
    </div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:minmax(280px,360px) 1fr;gap:18px;align-items:start;">
    <div class="card" style="padding:22px;">
        <h3 style="margin:0 0 16px;font-size:16px;color:var(--text-primary);"><i class="fa-solid fa-plus" style="color:var(--accent);margin-right:8px;"></i> Añadir estudio</h3>
        <form action="<?= eco_url('api/acciones_contenido.php') ?>" method="post" style="display:flex;flex-direction:column;gap:14px;">
            <?= csrf_field() ?>
            <input type="hidden" name="tipo" value="eco_tipo">
            <input type="hidden" name="accion" value="agregar">
            <div>
                <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px;color:var(--text-primary);">Nombre del estudio *</label>
                <input type="text" name="nombre" required maxlength="120" placeholder="Ej. Ecografía de tiroides"
                       style="width:100%;padding:10px 12px;border:1.5px solid var(--border);border-radius:8px;font-family:inherit;font-size:13.5px;box-sizing:border-box;">
            </div>
            <div>
                <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px;color:var(--text-primary);">Código interno</label>
                <input type="text" name="codigo" maxlength="40" placeholder="Se genera automático si se deja vacío"
                       style="width:100%;padding:10px 12px;border:1.5px solid var(--border);border-radius:8px;font-family:inherit;font-size:13.5px;box-sizing:border-box;">
            </div>
            <div>
                <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px;color:var(--text-primary);">Categoría</label>
                <input type="text" name="categoria" maxlength="60" placeholder="Ej. Abdominal, Obstétrica…"
                       style="width:100%;padding:10px 12px;border:1.5px solid var(--border);border-radius:8px;font-family:inherit;font-size:13.5px;box-sizing:border-box;">
            </div>
            <div>
                <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px;color:var(--text-primary);">Descripción breve</label>
                <textarea name="descripcion" rows="3" placeholder="Texto para el sitio público y referencia clínica."
                          style="width:100%;padding:10px 12px;border:1.5px solid var(--border);border-radius:8px;font-family:inherit;font-size:13.5px;resize:vertical;box-sizing:border-box;"></textarea>
            </div>
            <div>
                <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px;color:var(--text-primary);">Icono Font Awesome</label>
                <input type="text" name="icono" maxlength="60" value="fa-solid fa-wave-square" placeholder="fa-solid fa-wave-square"
                       style="width:100%;padding:10px 12px;border:1.5px solid var(--border);border-radius:8px;font-family:inherit;font-size:13.5px;box-sizing:border-box;">
            </div>
            <button type="submit" class="btn-primary" style="width:100%;justify-content:center;"><i class="fa-solid fa-floppy-disk"></i> Guardar estudio</button>
        </form>
    </div>

    <div class="card" style="padding:0;overflow:hidden;">
        <div class="card-header" style="padding:18px 20px;margin:0;">
            <h3 style="margin:0;"><i class="fa-solid fa-list" style="margin-right:8px;color:var(--accent);"></i> Catálogo actual (<?= count($tipos) ?>)</h3>
            <a href="index.php#servicios" target="_blank" rel="noopener" class="btn-secondary" style="font-size:12px;"><i class="fa-solid fa-globe"></i> Ver en inicio</a>
        </div>
        <?php if (empty($tipos)): ?>
            <p style="padding:24px 20px;margin:0;color:var(--text-muted);">No hay estudios registrados.</p>
        <?php else: ?>
            <div class="data-table" style="border:none;border-radius:0;">
                <table>
                    <thead>
                        <tr>
                            <th>Estudio</th>
                            <th>Categoría</th>
                            <th>Precio (USD)</th>
                            <th>Estado</th>
                            <th style="text-align:right;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tipos as $t): ?>
                            <tr>
                                <td>
                                    <div style="display:flex;align-items:center;gap:10px;">
                                        <span style="width:34px;height:34px;border-radius:8px;background:var(--accent-soft);color:var(--accent-text);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                            <i class="<?= htmlspecialchars($t['icono'] ?: 'fa-solid fa-wave-square') ?>"></i>
                                        </span>
                                        <span>
                                            <strong style="display:block;font-size:13.5px;"><?= htmlspecialchars($t['nombre']) ?></strong>
                                            <small style="color:var(--text-muted);"><?= htmlspecialchars($t['codigo']) ?></small>
                                        </span>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($t['categoria'] ?: '—') ?></td>
                                <td>
                                    <div class="precio-edit" style="display:flex;align-items:center;gap:6px;">
                                        <span style="color:var(--text-muted);font-size:13px;">$</span>
                                        <input type="number" min="0" step="0.01" value="<?= number_format((float)$t['precio'], 2, '.', '') ?>"
                                               data-tipo-id="<?= (int)$t['id'] ?>" class="precio-input"
                                               style="width:84px;padding:6px 8px;border:1.5px solid var(--border);border-radius:7px;font-size:13px;box-sizing:border-box;">
                                        <button type="button" class="btn-secondary precio-save" data-tipo-id="<?= (int)$t['id'] ?>"
                                                style="font-size:11.5px;padding:6px 9px;" title="Guardar precio">
                                            <i class="fa-solid fa-floppy-disk"></i>
                                        </button>
                                    </div>
                                </td>
                                <td>
                                    <?php if ((int)$t['activo'] === 1): ?>
                                        <span class="badge badge-success">Activo</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">Inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:right;white-space:nowrap;">
                                    <?php if ((int)$t['activo'] === 1): ?>
                                        <a href="<?= eco_url('api/acciones_contenido.php') ?>?tipo=eco_tipo&amp;accion=desactivar&amp;id=<?= (int)$t['id'] ?>&amp;csrf_token=<?= urlencode(csrf_token()) ?>"
                                           class="btn-secondary" style="font-size:12px;"
                                           onclick="return confirm('¿Desactivar este estudio del catálogo público?');">
                                            <i class="fa-solid fa-eye-slash"></i>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.querySelectorAll('.precio-save').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var id = btn.getAttribute('data-tipo-id');
        var input = document.querySelector('.precio-input[data-tipo-id="' + id + '"]');
        if (!input) return;
        var precio = parseFloat(input.value);
        if (isNaN(precio) || precio < 0) { input.style.borderColor = '#ef4444'; return; }

        var original = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';

        var fd = new FormData();
        fd.append('tipo_id', id);
        fd.append('precio', precio);

        fetch((window.ECO_BASE || '') + 'api/guardar_precio_tipo.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                btn.disabled = false;
                if (d && d.success) {
                    input.style.borderColor = '#22c55e';
                    btn.innerHTML = '<i class="fa-solid fa-check"></i>';
                    setTimeout(function () { btn.innerHTML = original; input.style.borderColor = ''; }, 1400);
                } else {
                    input.style.borderColor = '#ef4444';
                    btn.innerHTML = original;
                    alert((d && d.message) || 'No se pudo guardar.');
                }
            })
            .catch(function () { btn.disabled = false; btn.innerHTML = original; alert('Error de red.'); });
    });
});
</script>

<?php
$page_content = ob_get_clean();
include __DIR__ . '/../layouts/shell.php';
