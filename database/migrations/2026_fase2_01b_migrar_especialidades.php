<?php
/**
 * Fase 2 · A1b — Migra usuarios.especialidades (CSV) al modelo normalizado.
 * Idempotente: vuelve a sincronizar el puente desde el CSV de cada ecografista.
 * Ejecutar UNA vez tras 2026_fase2_01_especialidades.sql y ANTES de 2026_fase2_01c.
 *
 *   php database/migrations/2026_fase2_01b_migrar_especialidades.php
 */

/* Guarda: escribe en la base de datos, solo por linea de comandos.
   Sin esto, cualquiera podia dispararlo desde el navegador. */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script solo puede ejecutarse por linea de comandos.');
}
require __DIR__ . '/../../core/conexion.php';
require __DIR__ . '/../../lib/personal/especialidades.php';

// Comprueba que la columna aun exista (si ya se dropeo, no hay nada que migrar).
$colExiste = $conex->query("SHOW COLUMNS FROM usuarios LIKE 'especialidades'");
if (!$colExiste || $colExiste->num_rows === 0) {
    fwrite(STDOUT, "La columna usuarios.especialidades ya no existe. Nada que migrar.\n");
    exit(0);
}

$res = $conex->query(
    "SELECT id, nombre_completo, especialidades
     FROM usuarios
     WHERE rol = 'ecografista' AND especialidades IS NOT NULL AND TRIM(especialidades) <> ''"
);

$migrados = 0;
$totalVinculos = 0;
while ($row = $res->fetch_assoc()) {
    $csv = (string)$row['especialidades'];
    $lista = eco_especialidad_split($csv);
    if (eco_sync_especialidades_usuario($conex, (int)$row['id'], $csv)) {
        $migrados++;
        $totalVinculos += count($lista);
        fwrite(STDOUT, sprintf("  #%d %s -> [%s]\n", $row['id'], $row['nombre_completo'], implode(', ', $lista)));
    } else {
        fwrite(STDERR, sprintf("  FALLO al migrar #%d %s\n", $row['id'], $row['nombre_completo']));
    }
}

$catTotal = (int)($conex->query("SELECT COUNT(*) n FROM especialidades")->fetch_assoc()['n'] ?? 0);
$linkTotal = (int)($conex->query("SELECT COUNT(*) n FROM usuario_especialidades")->fetch_assoc()['n'] ?? 0);

fwrite(STDOUT, sprintf(
    "\nOK: %d ecografistas migrados, %d vinculos creados.\nCatalogo: %d especialidades. Puente: %d filas.\n",
    $migrados, $totalVinculos, $catTotal, $linkTotal
));
