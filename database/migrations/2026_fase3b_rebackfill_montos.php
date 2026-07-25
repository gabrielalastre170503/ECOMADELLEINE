<?php
/**
 * Fase 3B (mini) — Re-backfill de monto_total.
 * El total real de una cita es el "Total $X" acordado en motivo_principal (incluye
 * todos los servicios del bundle). Solo si no hay total en el texto se usa el precio
 * del estudio. Se actualizan unicamente las citas sin pagos registrados (no se
 * sobreescribe nada que recepcion ya haya cobrado).
 *
 *   php database/migrations/2026_fase3b_rebackfill_montos.php
 */

/* Guarda: escribe en la base de datos, solo por linea de comandos.
   Sin esto, cualquiera podia dispararlo desde el navegador. */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script solo puede ejecutarse por linea de comandos.');
}
require __DIR__ . '/../../core/conexion.php';
require __DIR__ . '/../../lib/facturacion/facturacion.php';

$res = $conex->query("
    SELECT c.id, c.motivo_principal, c.monto_total, c.monto_pagado, t.precio AS precio_estudio
    FROM citas c
    LEFT JOIN tipos_ecografias t ON t.id = c.tipo_ecografia_id
    WHERE c.monto_pagado = 0
");

$upd = $conex->prepare("UPDATE citas SET monto_total = ?, estado_pago = 'pendiente' WHERE id = ?");
$cambiados = 0;

while ($r = $res->fetch_assoc()) {
    $bundle = eco_total_desde_texto($r['motivo_principal']);
    $estudio = (float)($r['precio_estudio'] ?? 0);
    $nuevo = $bundle !== null ? $bundle : ($estudio > 0 ? $estudio : null);

    if ($nuevo === null) {
        continue;
    }
    $actual = $r['monto_total'] !== null ? (float)$r['monto_total'] : null;
    if ($actual !== null && abs($actual - $nuevo) < 0.001) {
        continue; // ya esta correcto
    }

    $id = (int)$r['id'];
    $upd->bind_param('di', $nuevo, $id);
    $upd->execute();
    if ($upd->affected_rows > 0) {
        $cambiados++;
        fwrite(STDOUT, sprintf("  cita #%d: %s -> %s%s\n",
            $id,
            $actual !== null ? ('$' . $actual) : 'NULL',
            '$' . $nuevo,
            $bundle !== null ? '  (Total del bundle)' : '  (precio estudio)'
        ));
    }
}
$upd->close();

fwrite(STDOUT, "\nOK: $cambiados citas actualizadas.\n");
