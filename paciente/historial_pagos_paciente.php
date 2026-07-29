<?php
/**
 * Mi historial de pagos (paciente): estado de cuenta y extracto de movimientos.
 *
 * Los importes salen de lib/pacientes/historia.php, la misma fuente que usan la
 * ficha de recepción y la historia clínica del ecografista: el paciente debe
 * ver exactamente la misma cifra que le dicen en el mostrador.
 */
session_start();
include __DIR__ . '/../core/conexion.php';
require_once __DIR__ . '/../lib/pacientes/historia.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . eco_url('login'));
    exit;
}
if (($_SESSION['rol'] ?? '') !== 'paciente') {
    header('Location: ' . eco_url('dashboard'));
    exit;
}

$usuario_id = (int)$_SESSION['usuario_id'];

/* Solo las atenciones con importe: una cita sin facturar no es un movimiento
   de cuenta y solo ensuciaría el extracto. */
$pagos = array_values(array_filter(
    eco_historia_citas($conex, $usuario_id),
    static fn(array $c) => $c['costo'] !== null
));

$total_facturado = 0.0;
$total_pagado    = 0.0;
$total_pendiente = 0.0;
$total_a_favor   = 0.0;
$n_pendientes    = 0;
$n_pagados       = 0;
$ultimo_pago     = '';

foreach ($pagos as $p) {
    // Una cita cancelada no se cobra: queda en el extracto como constancia,
    // pero fuera de los totales.
    if ($p['estado'] === 'cancelada') {
        continue;
    }
    $total_facturado += (float)$p['costo'];
    $total_pagado    += (float)($p['pagado'] ?? 0);
    if ($p['saldo'] !== null && $p['saldo'] > 0.005) {
        $total_pendiente += (float)$p['saldo'];
        $n_pendientes++;
    }
    // Cobrado de más. Ocurre poco, pero si se calla, el estado de cuenta no
    // cuadra con la suma del extracto y parece un error de la página.
    $total_a_favor += max((float)($p['pagado'] ?? 0) - (float)$p['costo'], 0);
    if ($p['pago_estado'] === 'pagado') {
        $n_pagados++;
        if ($ultimo_pago === '' && $p['fecha']) {
            $ultimo_pago = date('d/m/Y', strtotime($p['fecha']));
        }
    }
}

/* Porcentaje cobrado del total facturado. Se limita a 100 para que un cobro
   de más no desborde la barra: ese caso se avisa aparte. */
$pct_pagado = $total_facturado > 0
    ? min(100, (int)round(($total_pagado / $total_facturado) * 100))
    : ($pagos ? 100 : 0);

/* Agrupación por mes: un extracto se lee por periodos, no como una lista
   corrida. Cada grupo lleva su subtotal. */
$meses = [1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
          'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
$por_mes = [];
foreach ($pagos as $p) {
    $ts    = $p['fecha'] ? strtotime($p['fecha']) : null;
    $clave = $ts ? date('Y-m', $ts) : 'sin-fecha';
    if (!isset($por_mes[$clave])) {
        $por_mes[$clave] = [
            'titulo' => $ts ? $meses[(int)date('n', $ts)] . ' ' . date('Y', $ts) : 'Sin fecha',
            'filas'  => [],
            'total'  => 0.0,
        ];
    }
    $por_mes[$clave]['filas'][] = $p;
    if ($p['estado'] !== 'cancelada') {
        $por_mes[$clave]['total'] += (float)$p['costo'];
    }
}

$page_title     = 'Mi Historial de Pagos';
$page_subtitle  = 'Estado de cuenta y detalle de cada atención facturada';
$active_section = 'mis-pagos';
$body_class     = 'pg';

$css_pagos = 'assets/css/paciente/historial-pagos.css';
$page_head_extra = '<link rel="stylesheet" href="' . $css_pagos
    . '?v=' . (@filemtime(__DIR__ . '/../' . $css_pagos) ?: '1') . '">';

ob_start();
?>

<section class="card pg-cuenta">
    <div class="pg-cuenta__saldo">
        <p class="pg-cuenta__label">Saldo pendiente</p>
        <?php if ($total_pendiente > 0.005): ?>
            <p class="pg-cuenta__cifra"><?= htmlspecialchars(eco_money($total_pendiente)) ?></p>
            <p class="pg-cuenta__nota">
                en <?= $n_pendientes ?> <?= $n_pendientes === 1 ? 'atención' : 'atenciones' ?>.
                Puedes pagarlo en recepción.
            </p>
        <?php else: ?>
            <p class="pg-cuenta__cifra pg-cuenta__cifra--aldia"><?= htmlspecialchars(eco_money(0)) ?></p>
            <p class="pg-cuenta__nota">Estás al día, no debes nada.</p>
        <?php endif; ?>
    </div>
    <div class="pg-cuenta__detalle">
        <div class="pg-cuenta__lineas">
            <span>Pagado<strong><?= htmlspecialchars(eco_money($total_pagado)) ?></strong></span>
            <span>Facturado<strong><?= htmlspecialchars(eco_money($total_facturado)) ?></strong></span>
        </div>
        <div class="pg-barra" role="img"
             aria-label="<?= $pct_pagado ?>% del total facturado está pagado">
            <div class="pg-barra__relleno" style="width:<?= $pct_pagado ?>%;"></div>
        </div>
        <p class="pg-cuenta__pie">
            <?= count($pagos) ?> <?= count($pagos) === 1 ? 'atención facturada' : 'atenciones facturadas' ?>
            <?php if ($ultimo_pago !== ''): ?>
                · último pago el <?= htmlspecialchars($ultimo_pago) ?>
            <?php endif; ?>
        </p>
    </div>
</section>

<?php if ($total_a_favor > 0.005): ?>
    <div class="pg-aviso">
        <i class="fa-solid fa-circle-exclamation"></i>
        <p>Figura <strong><?= htmlspecialchars(eco_money($total_a_favor)) ?></strong> cobrado de más.
           Consúltalo en recepción para que revisen el cobro.</p>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3><i class="fa-solid fa-file-invoice-dollar" style="margin-right:8px;color:var(--accent);"></i> Extracto de movimientos</h3>
    </div>

    <?php if (!$pagos): ?>
        <div class="pg-vacio">
            <i class="fa-solid fa-receipt"></i>
            <p style="margin:0 0 4px;font-weight:600;color:var(--text-secondary);">Todavía no tienes pagos registrados</p>
            <p style="margin:0;font-size:13px;">Aquí aparecerá el detalle en cuanto se te facture una atención.</p>
        </div>
    <?php else: ?>

        <div class="pg-toolbar">
            <div class="pg-search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="pg-search-input" placeholder="Buscar por estudio, ecografista o método…" autocomplete="off">
            </div>
            <div class="pg-tabs">
                <button type="button" class="pg-tab is-active" data-filter="todos">Todos <span class="pg-tab-count"><?= count($pagos) ?></span></button>
                <button type="button" class="pg-tab" data-filter="pagado">Pagados <span class="pg-tab-count"><?= $n_pagados ?></span></button>
                <button type="button" class="pg-tab" data-filter="deuda">Con saldo <span class="pg-tab-count"><?= $n_pendientes ?></span></button>
            </div>
        </div>

        <div class="pg-tabla-wrap">
            <table class="pg-tabla">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Concepto</th>
                        <th>Método</th>
                        <th class="pg-num">Importe</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody id="pg-cuerpo">
                <?php foreach ($por_mes as $mes): ?>
                    <tr class="pg-mes">
                        <td colspan="5">
                            <div class="pg-mes__fila">
                                <span class="pg-mes__nombre"><?= htmlspecialchars($mes['titulo']) ?></span>
                                <span class="pg-mes__total"><?= count($mes['filas']) ?> · <?= htmlspecialchars(eco_money($mes['total'])) ?></span>
                            </div>
                        </td>
                    </tr>
                    <?php foreach ($mes['filas'] as $p):
                        $anulada  = ($p['estado'] === 'cancelada');
                        $conSaldo = (!$anulada && $p['saldo'] !== null && $p['saldo'] > 0.005);
                        $aFavor   = $anulada ? 0.0 : max((float)($p['pagado'] ?? 0) - (float)$p['costo'], 0);
                        $grupo    = $conSaldo ? 'deuda' : ($p['pago_estado'] === 'pagado' ? 'pagado' : 'otro');
                        $busca    = mb_strtolower(trim($p['titulo'] . ' ' . $p['profesional'] . ' '
                                     . $p['metodo_pago'] . ' ' . $p['servicios']));
                        $ts       = $p['fecha'] ? strtotime($p['fecha']) : null;
                    ?>
                        <tr class="pg-fila<?= $anulada ? ' pg-fila--anulada' : '' ?>"
                            data-grupo="<?= htmlspecialchars($grupo) ?>"
                            data-search="<?= htmlspecialchars($busca, ENT_QUOTES) ?>">
                            <td class="pg-fecha"><?= $ts ? date('d/m/Y', $ts) : '—' ?></td>
                            <td>
                                <span class="pg-concepto"><?= htmlspecialchars($p['titulo']) ?></span>
                                <?php if ($p['profesional'] !== ''): ?>
                                    <span class="pg-concepto__extra"><?= htmlspecialchars($p['profesional']) ?></span>
                                <?php endif; ?>
                                <?php if ($anulada): ?>
                                    <span class="pg-concepto__extra">Cita cancelada · no se cobró</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($p['metodo_pago'] !== ''): ?>
                                    <span class="pg-metodo"><i class="fa-solid fa-receipt"></i><?= htmlspecialchars($p['metodo_pago']) ?></span>
                                <?php else: ?>
                                    <span class="pg-concepto__extra" style="margin:0;">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="pg-num">
                                <span class="pg-importe"><?= htmlspecialchars($p['costo_fmt']) ?></span>
                                <?php if ($conSaldo): ?>
                                    <span class="pg-saldo">debes <?= htmlspecialchars($p['saldo_fmt']) ?></span>
                                <?php elseif ($aFavor > 0.005): ?>
                                    <span class="pg-saldo">pagaste <?= htmlspecialchars(eco_money((float)$p['pagado'])) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($anulada): ?>
                                    <span class="pg-estado">Anulada</span>
                                <?php elseif ($conSaldo): ?>
                                    <span class="pg-estado pg-estado--debe"><?= htmlspecialchars($p['pago_label'] ?: 'Pendiente') ?></span>
                                <?php elseif ($aFavor > 0.005): ?>
                                    <span class="pg-estado pg-estado--exceso">Cobrado de más</span>
                                <?php elseif ($p['pago_estado'] === 'pagado'): ?>
                                    <span class="pg-estado pg-estado--pagado">Pagado</span>
                                <?php else: ?>
                                    <span class="pg-estado"><?= htmlspecialchars($p['pago_label'] ?: '—') ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div id="pg-empty-filter" class="pg-vacio" style="display:none;">
            <i class="fa-solid fa-magnifying-glass"></i>
            <p style="margin:0;font-weight:600;color:var(--text-secondary);">No se encontraron movimientos</p>
            <p style="margin:0;font-size:13px;">Prueba con otro término de búsqueda o filtro.</p>
        </div>

    <?php endif; ?>
</div>

<?php
$page_content = ob_get_clean();

$page_scripts_extra = <<<'HTML'
<script>
(function () {
    var tabs   = document.querySelectorAll('.pg-tab');
    var filas  = Array.prototype.slice.call(document.querySelectorAll('.pg-fila'));
    var meses  = Array.prototype.slice.call(document.querySelectorAll('.pg-mes'));
    var search = document.getElementById('pg-search-input');
    var empty  = document.getElementById('pg-empty-filter');
    var tabla  = document.querySelector('.pg-tabla-wrap');
    if (!filas.length) return;

    var filtro = 'todos';

    function aplicar() {
        var q = (search && search.value || '').trim().toLowerCase();
        var visibles = 0;
        filas.forEach(function (f) {
            var okGrupo = (filtro === 'todos' || f.getAttribute('data-grupo') === filtro);
            var okBusca = (!q || (f.getAttribute('data-search') || '').indexOf(q) !== -1);
            var show = okGrupo && okBusca;
            f.style.display = show ? '' : 'none';
            if (show) visibles++;
        });

        /* Un mes cuyas filas quedaron todas ocultas no debe dejar su cabecera
           suelta: se recorren los hermanos hasta el siguiente mes. */
        meses.forEach(function (m) {
            var hay = false;
            var n = m.nextElementSibling;
            while (n && !n.classList.contains('pg-mes')) {
                if (n.classList.contains('pg-fila') && n.style.display !== 'none') { hay = true; break; }
                n = n.nextElementSibling;
            }
            m.style.display = hay ? '' : 'none';
        });

        if (empty) empty.style.display = (visibles === 0) ? '' : 'none';
        if (tabla) tabla.style.display = (visibles === 0) ? 'none' : '';
    }

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            tabs.forEach(function (t) { t.classList.remove('is-active'); });
            tab.classList.add('is-active');
            filtro = tab.getAttribute('data-filter');
            aplicar();
        });
    });

    if (search) search.addEventListener('input', aplicar);
})();
</script>
HTML;

include __DIR__ . '/../layouts/shell.php';
