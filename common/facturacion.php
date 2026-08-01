<?php
session_start();
include __DIR__ . '/../core/conexion.php';
require_once __DIR__ . '/../lib/facturacion/facturacion.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . eco_url('login'));
    exit;
}
$rol = $_SESSION['rol'] ?? '';
$uid = (int)$_SESSION['usuario_id'];
if (!in_array($rol, ['recepcionista', 'administrador', 'ecografista'], true)) {
    header('Location: ' . eco_url('dashboard'));
    exit;
}

$es_eco = ($rol === 'ecografista');

// Recepcion/Admin ven todas las citas facturables; el ecografista solo las suyas.
$sql = "
    SELECT c.id, c.fecha_cita, c.estado, c.monto_total, c.monto_pagado, c.estado_pago, c.metodo_pago,
           c.motivo_principal AS servicios,
           u.nombre_completo AS paciente, u.cedula,
           t.nombre AS estudio, t.precio AS precio_estudio
    FROM citas c
    JOIN usuarios u ON u.id = c.paciente_id
    LEFT JOIN tipos_ecografias t ON t.id = c.tipo_ecografia_id
    WHERE (c.tipo_ecografia_id IS NOT NULL OR c.monto_total IS NOT NULL)
";
if ($es_eco) {
    $sql .= " AND c.ecografista_id = ? ";
}
$sql .= " ORDER BY (c.estado_pago = 'pagado'), c.fecha_cita DESC, c.id DESC";

$citas = [];
if ($es_eco) {
    $stmt = $conex->prepare($sql);
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $rs = $stmt->get_result();
    while ($row = $rs->fetch_assoc()) {
        $citas[] = $row;
    }
    $stmt->close();
} elseif ($q = $conex->query($sql)) {
    while ($row = $q->fetch_assoc()) {
        $citas[] = $row;
    }
    $q->free();
}

$tot_facturado = 0.0;
$tot_cobrado   = 0.0;
$tot_porcobrar = 0.0;
foreach ($citas as $c) {
    $mt = (float)($c['monto_total'] ?? 0);
    $mp = (float)$c['monto_pagado'];
    $tot_facturado += $mt;
    $tot_cobrado   += $mp;
    if ($c['estado_pago'] !== 'exonerado') {
        $tot_porcobrar += max($mt - $mp, 0);
    }
}

$page_title     = 'Facturación';
$page_subtitle  = $es_eco ? 'Cobros y estado de pago de tus citas' : 'Cobros y estado de pago de las citas';
$active_section = 'facturacion';

ob_start();
?>
<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:18px;">
    <div class="stat-card">
        <div class="stat-card-icon" style="background:var(--accent-soft);color:var(--accent-text);"><i class="fa-solid fa-file-invoice-dollar"></i></div>
        <p class="stat-card-label">Total facturado</p>
        <p class="stat-card-value accent"><?= eco_money($tot_facturado) ?></p>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon" style="background:rgba(34,197,94,.12);color:#15803d;"><i class="fa-solid fa-hand-holding-dollar"></i></div>
        <p class="stat-card-label">Cobrado</p>
        <p class="stat-card-value success"><?= eco_money($tot_cobrado) ?></p>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon" style="background:rgba(245,158,11,.12);color:#b45309;"><i class="fa-solid fa-clock"></i></div>
        <p class="stat-card-label">Por cobrar</p>
        <p class="stat-card-value warning"><?= eco_money($tot_porcobrar) ?></p>
    </div>
</div>

<div class="card" style="padding:0;overflow:hidden;">
    <div class="card-header" style="padding:16px 20px;margin:0;flex-wrap:wrap;gap:10px;">
        <h3 style="margin:0;"><i class="fa-solid fa-cash-register" style="margin-right:8px;color:var(--accent);"></i> Citas (<?= count($citas) ?>)</h3>
        <div class="fact-filtros" style="display:flex;gap:6px;flex-wrap:wrap;">
            <button type="button" class="btn-secondary fact-filtro is-active" data-filtro="todos" style="font-size:12px;">Todas</button>
            <button type="button" class="btn-secondary fact-filtro" data-filtro="pendiente" style="font-size:12px;">Pendientes</button>
            <button type="button" class="btn-secondary fact-filtro" data-filtro="parcial" style="font-size:12px;">Parciales</button>
            <button type="button" class="btn-secondary fact-filtro" data-filtro="pagado" style="font-size:12px;">Pagadas</button>
        </div>
    </div>

    <?php if (empty($citas)): ?>
        <p style="padding:30px 20px;margin:0;color:var(--text-muted);text-align:center;">Aún no hay citas con estudio asignado para facturar.</p>
    <?php else: ?>
        <div class="data-table fact-table" style="border:none;border-radius:0;">
            <table class="rx-pac-table">
                <thead>
                    <tr>
                        <th class="rx-sort-th" data-sort-col="0" data-sort-type="text" tabindex="0" role="button">Paciente</th>
                        <th class="rx-sort-th" data-sort-col="1" data-sort-type="text" tabindex="0" role="button">Estudio / Servicios</th>
                        <th class="rx-sort-th" data-sort-col="2" data-sort-type="date" tabindex="0" role="button">Fecha</th>
                        <th class="rx-sort-th" data-sort-col="3" data-sort-type="number" tabindex="0" role="button" style="text-align:right;">Total</th>
                        <th class="rx-sort-th" data-sort-col="4" data-sort-type="number" tabindex="0" role="button" style="text-align:right;">Pagado</th>
                        <th class="rx-sort-th" data-sort-col="5" data-sort-type="number" tabindex="0" role="button" style="text-align:right;">Saldo</th>
                        <th class="rx-sort-th" data-sort-col="6" data-sort-type="text" tabindex="0" role="button">Estado</th>
                        <th style="text-align:right;">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($citas as $c):
                        $mt = (float)($c['monto_total'] ?? 0);
                        $mp = (float)$c['monto_pagado'];
                        $saldo = max($mt - $mp, 0);
                        [$txt, $bg] = eco_estado_pago_color($c['estado_pago']);
                        $fecha = $c['fecha_cita'] ? date('d/m/Y', strtotime($c['fecha_cita'])) : '—';
                        $fecha_iso = $c['fecha_cita'] ? date('Y-m-d', strtotime($c['fecha_cita'])) : '';
                        $servicios = trim((string)($c['servicios'] ?? ''));
                        $estudios_list = eco_estudios_desde_texto($servicios);
                        $estudio_lead  = $estudios_list ? implode(', ', $estudios_list) : ($c['estudio'] ?: 'Sin estudio');
                        $metodo  = trim((string)($c['metodo_pago'] ?? ''));
                        $settled = in_array($c['estado_pago'], ['pagado', 'exonerado'], true);
                    ?>
                        <tr class="fact-row" data-estado="<?= htmlspecialchars($c['estado_pago']) ?>">
                            <td>
                                <strong style="display:block;font-size:13.5px;"><?= htmlspecialchars($c['paciente']) ?></strong>
                                <small style="color:var(--text-muted);"><?= htmlspecialchars($c['cedula'] ?: '—') ?></small>
                            </td>
                            <td style="max-width:320px;">
                                <span><?= htmlspecialchars($estudio_lead) ?></span>
                                <?php if ($servicios !== '' && $servicios !== $estudio_lead): ?>
                                    <small style="display:block;color:var(--text-muted);font-size:11.5px;line-height:1.4;margin-top:2px;"><?= htmlspecialchars($servicios) ?></small>
                                <?php endif; ?>
                            </td>
                            <td data-sort-value="<?= htmlspecialchars($fecha_iso) ?>"><?= htmlspecialchars($fecha) ?></td>
                            <td style="text-align:right;font-weight:600;" data-sort-value="<?= number_format($mt, 2, '.', '') ?>"><?= eco_money($mt) ?></td>
                            <td style="text-align:right;color:#15803d;" data-sort-value="<?= number_format($mp, 2, '.', '') ?>"><?= eco_money($mp) ?></td>
                            <td style="text-align:right;font-weight:700;<?= $saldo > 0 && $c['estado_pago'] !== 'exonerado' ? 'color:#b45309;' : 'color:var(--text-muted);' ?>" data-sort-value="<?= number_format($saldo, 2, '.', '') ?>"><?= eco_money($saldo) ?></td>
                            <td data-sort-value="<?= htmlspecialchars($c['estado_pago']) ?>">
                                <span class="badge" style="background:<?= $bg ?>;color:<?= $txt ?>;"><?= htmlspecialchars(eco_estado_pago_label($c['estado_pago'])) ?></span>
                                <?php if (in_array($c['estado_pago'], ['pagado', 'parcial'], true) && $metodo !== ''): ?>
                                    <small style="display:block;color:var(--text-muted);font-size:11px;margin-top:3px;"><i class="fa-solid fa-credit-card" style="margin-right:4px;opacity:.7;"></i><?= htmlspecialchars($metodo) ?></small>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:right;white-space:nowrap;">
                                <button type="button" class="btn-primary fact-cobrar" style="font-size:11.5px;padding:7px 11px;"
                                        data-id="<?= (int)$c['id'] ?>"
                                        data-paciente="<?= htmlspecialchars($c['paciente'], ENT_QUOTES) ?>"
                                        data-estudio="<?= htmlspecialchars($servicios !== '' ? $servicios : ($c['estudio'] ?: 'Sin estudio'), ENT_QUOTES) ?>"
                                        data-total="<?= number_format($mt, 2, '.', '') ?>"
                                        data-pagado="<?= number_format($mp, 2, '.', '') ?>"
                                        data-estado="<?= htmlspecialchars($c['estado_pago']) ?>"
                                        data-metodo="<?= htmlspecialchars($metodo, ENT_QUOTES) ?>">
                                    <i class="fa-solid fa-<?= $settled ? 'eye' : 'cash-register' ?>"></i> <?= $settled ? 'Ver' : 'Cobrar' ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Modal de cobro -->
<style>
/* El diálogo se montaba a mano con estilos en línea, así que se quedaba fuera
   de «.eco-glass .eco-modal__dialog { background: var(--eco-solid) }»: heredaba
   el blanco al 90% del tema y el velo oscuro se transparentaba, que es de
   donde salía el gris. Usando el componente del sistema queda opaco. */
.fx-dialog { max-width:470px; padding:0; overflow:hidden; }
.fx-hero { display:flex; align-items:flex-start; gap:15px; padding:22px 52px 18px 22px; background:linear-gradient(122deg,var(--accent-soft) 0%,var(--bg-muted) 45%,var(--eco-solid,var(--bg-surface)) 100%); border-bottom:1px solid var(--border-soft); }
.fx-hero__icon { width:46px; height:46px; flex-shrink:0; display:flex; align-items:center; justify-content:center; border-radius:var(--radius); background:var(--accent-soft); color:var(--accent-text); border:1px solid rgba(2,177,244,.2); font-size:1.15rem; }
.fx-hero__copy { min-width:0; }
.fx-hero__kicker { margin:0 0 3px; font-size:10.5px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:var(--accent-text); }
.fx-hero__title { margin:0 0 6px; font-size:1.15rem; font-weight:700; letter-spacing:-.02em; color:var(--text-primary); }
.fx-hero__paciente { display:block; font-size:13px; font-weight:700; color:var(--text-primary); }
.fx-hero__estudio { display:block; margin-top:2px; font-size:11.5px; line-height:1.45; color:var(--text-secondary); }

.fx-body { padding:18px 22px; }
.fx-error { margin-bottom:12px; padding:9px 12px; border-radius:8px; font-size:12.5px; background:rgba(239,68,68,.1); border:1px solid rgba(239,68,68,.35); color:#b91c1c; }

/* Lo que hay que saber para cobrar, antes de los campos. */
.fx-resumen { display:grid; grid-template-columns:repeat(3,1fr); gap:8px; margin-bottom:16px; }
.fx-resumen__item { padding:9px 11px; border:1px solid var(--border); border-radius:10px; text-align:center; }
.fx-resumen__item span { display:block; font-size:10px; font-weight:700; letter-spacing:.04em; text-transform:uppercase; color:var(--text-muted); }
.fx-resumen__item strong { display:block; margin-top:3px; font-size:15px; font-variant-numeric:tabular-nums; color:var(--text-primary); }
.fx-resumen__item--pend { background:var(--accent-soft); border-color:rgba(2,177,244,.28); }
.fx-resumen__item--pend strong { color:#0369a1; }
.fx-resumen__item--saldado { background:var(--bg-muted); }
.fx-resumen__item--saldado strong { color:var(--text-muted); }

.fx-fila { display:flex; gap:12px; }
.fx-fila > * { flex:1; min-width:0; }
.eco-modal .fx-body .eco-field { margin-bottom:14px; }
.eco-modal .fx-body label { display:block; margin-bottom:5px; font-size:12px; font-weight:600; color:var(--text-secondary); }
.eco-modal .fx-body input[readonly] { background:var(--bg-muted); color:var(--text-muted); cursor:default; }

.fx-abono-head { display:flex; align-items:baseline; justify-content:space-between; gap:10px; }
.fx-todo { padding:0; border:none; background:none; font-family:inherit; font-size:11.5px; font-weight:700; color:var(--accent-text); cursor:pointer; text-decoration:underline; text-underline-offset:2px; }
.fx-todo:hover { text-decoration:none; }
.fx-todo[hidden] { display:none; }

.fx-saldada { padding:18px; border-radius:12px; background:var(--accent-soft); border:1px solid rgba(2,177,244,.28); text-align:center; }
.fx-saldada i { font-size:26px; color:var(--accent-text); }
.fx-saldada p { margin:8px 0 0; font-size:13.5px; font-weight:600; color:#0369a1; }

.fx-footer { display:flex; align-items:center; justify-content:space-between; gap:8px; padding:14px 22px; border-top:1px solid var(--border); }
.fx-footer__der { display:flex; gap:8px; }
@media (max-width: 480px) {
    .fx-resumen { grid-template-columns:1fr; }
    .fx-fila { display:block; }
    .fx-footer { flex-direction:column-reverse; align-items:stretch; }
    .fx-footer__der { justify-content:flex-end; }
}
</style>
<div id="fact-modal" class="eco-modal" aria-hidden="true" role="dialog" aria-labelledby="fact-modal-title">
    <div class="eco-modal__dialog fx-dialog">
        <button type="button" class="eco-modal__close" id="fact-close" aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>

        <div class="fx-hero">
            <div class="fx-hero__icon"><i class="fa-solid fa-cash-register"></i></div>
            <div class="fx-hero__copy">
                <p class="fx-hero__kicker">Facturación</p>
                <h3 class="fx-hero__title" id="fact-modal-title">Registrar cobro</h3>
                <strong class="fx-hero__paciente" id="fact-paciente">—</strong>
                <span class="fx-hero__estudio" id="fact-estudio"></span>
            </div>
        </div>

        <div class="fx-body">
            <div id="fact-error" class="fx-error" style="display:none;" role="alert"></div>
            <input type="hidden" id="fact-cita-id">

            <div id="fact-pagada" class="fx-saldada" style="display:none;">
                <i class="fa-solid fa-circle-check"></i>
                <p class="fact-pagada-text"></p>
            </div>

            <div id="fact-form">
                <div class="fx-resumen">
                    <div class="fx-resumen__item"><span>Total</span><strong id="fact-r-total">—</strong></div>
                    <div class="fx-resumen__item"><span>Ya pagado</span><strong id="fact-r-pagado">—</strong></div>
                    <div class="fx-resumen__item fx-resumen__item--pend" id="fact-r-pend-box"><span>Pendiente</span><strong id="fact-r-pend">—</strong></div>
                </div>

                <div class="fx-fila">
                    <div class="eco-field">
                        <label for="fact-total">Monto total ($)</label>
                        <input type="number" id="fact-total" min="0" step="0.01" inputmode="decimal">
                    </div>
                    <div class="eco-field">
                        <label for="fact-pagado">Ya pagado ($)</label>
                        <input type="number" id="fact-pagado" readonly tabindex="-1">
                    </div>
                </div>

                <div class="eco-field">
                    <div class="fx-abono-head">
                        <label for="fact-abono">Abono ahora ($)</label>
                        <button type="button" class="fx-todo" id="fact-todo" hidden>Cobrar lo pendiente</button>
                    </div>
                    <input type="number" id="fact-abono" min="0" step="0.01" placeholder="0.00" inputmode="decimal">
                </div>

                <div class="eco-field" style="margin-bottom:0;">
                    <label for="fact-metodo">Método de pago</label>
                    <select id="fact-metodo">
                        <option value="">Seleccionar…</option>
                        <?php foreach (eco_metodos_pago() as $m): ?>
                            <option value="<?= htmlspecialchars($m) ?>"><?= htmlspecialchars($m) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div><!-- /#fact-form -->
        </div>

        <div class="fx-footer">
            <button type="button" id="fact-exonerar" class="btn-secondary"><i class="fa-solid fa-gift"></i> Exonerar</button>
            <div class="fx-footer__der">
                <button type="button" id="fact-cancel" class="btn-secondary">Cancelar</button>
                <button type="button" id="fact-guardar" class="btn-primary"><i class="fa-solid fa-check"></i> Registrar</button>
            </div>
        </div>
    </div>
</div>

<style>
.fact-table th.rx-sort-th { cursor:pointer; user-select:none; white-space:nowrap; }
.fact-table th.rx-sort-th:hover { color:var(--accent); }
.fact-table th.rx-sort-th::after { content:'⇅'; opacity:.3; margin-left:6px; font-size:11px; font-weight:400; }
.fact-table th.rx-sort-th--asc::after { content:'▲'; opacity:.85; font-size:9px; }
.fact-table th.rx-sort-th--desc::after { content:'▼'; opacity:.85; font-size:9px; }
</style>
<script src="assets/js/panel/eco-table-sort.js"></script>
<script>
(function () {
    var modal = document.getElementById('fact-modal');
    /* Ahora es un .eco-modal del sistema: lo abre y cierra EcoModal, que marca
       la clase de apertura. Con style.display se quedaria invisible. */
    function openModal() {
        if (window.EcoModal) { EcoModal.open('fact-modal'); }
        else { modal.style.display = 'flex'; }
    }
    function closeModal() {
        if (window.EcoModal) { EcoModal.close('fact-modal'); }
        else { modal.style.display = 'none'; }
        setError('');
    }
    function setError(m) { var e = document.getElementById('fact-error'); e.textContent = m || ''; e.style.display = m ? 'block' : 'none'; }

    function dinero(n) { return '$' + (Math.round(n * 100) / 100).toFixed(2); }

    /* Total, pagado y —lo que de verdad se cobra— lo pendiente. */
    function refrescarResumen() {
        var total  = parseFloat(document.getElementById('fact-total').value) || 0;
        var pagado = parseFloat(document.getElementById('fact-pagado').value) || 0;
        var pend   = Math.max(total - pagado, 0);

        document.getElementById('fact-r-total').textContent  = dinero(total);
        document.getElementById('fact-r-pagado').textContent = dinero(pagado);
        document.getElementById('fact-r-pend').textContent   = dinero(pend);

        var caja = document.getElementById('fact-r-pend-box');
        caja.classList.toggle('fx-resumen__item--pend', pend > 0.005);
        caja.classList.toggle('fx-resumen__item--saldado', pend <= 0.005);

        var todo = document.getElementById('fact-todo');
        todo.hidden = pend <= 0.005;
        todo.textContent = 'Cobrar lo pendiente (' + dinero(pend) + ')';
        todo.setAttribute('data-pend', pend.toFixed(2));
    }

    document.querySelectorAll('.fact-cobrar').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('fact-cita-id').value = btn.getAttribute('data-id');
            document.getElementById('fact-paciente').textContent = btn.getAttribute('data-paciente');
            document.getElementById('fact-estudio').textContent = btn.getAttribute('data-estudio');
            document.getElementById('fact-total').value = btn.getAttribute('data-total');
            document.getElementById('fact-pagado').value = btn.getAttribute('data-pagado');
            document.getElementById('fact-abono').value = '';
            document.getElementById('fact-metodo').value = '';
            setError('');
            refrescarResumen();

            // Cita ya saldada (pagada o exonerada): mostrar mensaje en vez del formulario.
            var estadoPago = btn.getAttribute('data-estado') || '';
            var metodo = btn.getAttribute('data-metodo') || '';
            var pagada = estadoPago === 'pagado';
            var settled = pagada || estadoPago === 'exonerado';
            var pagadaBox = document.getElementById('fact-pagada');
            if (settled) {
                var msg = pagada ? 'Ya se abonó el total de esta cita.' : 'Esta cita fue exonerada de pago.';
                if (pagada && metodo) msg += ' Método: ' + metodo + '.';
                pagadaBox.querySelector('.fact-pagada-text').textContent = msg;
                pagadaBox.style.display = 'block';
                document.getElementById('fact-form').style.display = 'none';
                document.getElementById('fact-exonerar').style.display = 'none';
                document.getElementById('fact-guardar').style.display = 'none';
                document.getElementById('fact-cancel').textContent = 'Cerrar';
            } else {
                pagadaBox.style.display = 'none';
                document.getElementById('fact-form').style.display = '';
                document.getElementById('fact-exonerar').style.display = '';
                document.getElementById('fact-guardar').style.display = '';
                document.getElementById('fact-cancel').textContent = 'Cancelar';
            }
            openModal();
        });
    });

    document.getElementById('fact-close').addEventListener('click', closeModal);
    document.getElementById('fact-cancel').addEventListener('click', closeModal);

    // Cambiar el total recalcula lo pendiente sin tener que hacer la resta.
    document.getElementById('fact-total').addEventListener('input', refrescarResumen);

    var btnTodo = document.getElementById('fact-todo');
    btnTodo.addEventListener('click', function () {
        document.getElementById('fact-abono').value = btnTodo.getAttribute('data-pend') || '';
        document.getElementById('fact-metodo').focus();
    });

    function enviar(fd, btn) {
        var orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
        fetch((window.ECO_BASE || '') + 'api/registrar_pago.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                btn.disabled = false; btn.innerHTML = orig;
                if (d && d.success) { location.reload(); }
                else { setError((d && d.message) || 'No se pudo registrar.'); }
            })
            .catch(function () { btn.disabled = false; btn.innerHTML = orig; setError('Error de red.'); });
    }

    document.getElementById('fact-guardar').addEventListener('click', function () {
        var fd = new FormData();
        fd.append('accion', 'cobrar');
        fd.append('cita_id', document.getElementById('fact-cita-id').value);
        fd.append('monto_total', document.getElementById('fact-total').value || '0');
        fd.append('abono', document.getElementById('fact-abono').value || '0');
        fd.append('metodo_pago', document.getElementById('fact-metodo').value);
        enviar(fd, this);
    });

    document.getElementById('fact-exonerar').addEventListener('click', function () {
        if (!confirm('¿Exonerar de pago esta cita?')) return;
        var fd = new FormData();
        fd.append('accion', 'exonerar');
        fd.append('cita_id', document.getElementById('fact-cita-id').value);
        enviar(fd, this);
    });

    document.querySelectorAll('.fact-filtro').forEach(function (b) {
        b.addEventListener('click', function () {
            document.querySelectorAll('.fact-filtro').forEach(function (x) { x.classList.remove('is-active'); });
            b.classList.add('is-active');
            var f = b.getAttribute('data-filtro');
            document.querySelectorAll('.fact-row').forEach(function (row) {
                row.style.display = (f === 'todos' || row.getAttribute('data-estado') === f) ? '' : 'none';
            });
        });
    });

    // Ordenamiento por columnas (reusa el sorter de las tablas de recepción).
    if (window.EcoTableSort) { window.EcoTableSort.init(document.querySelector('.fact-table')); }
})();
</script>

<?php
$page_content = ob_get_clean();
include __DIR__ . '/../layouts/shell.php';
