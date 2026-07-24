<?php
/**
 * Importación masiva de pacientes desde Excel/CSV (parseado en el navegador
 * con SheetJS y enviado como JSON). Crea pacientes nuevos y OMITE duplicados
 * por correo o cédula. Solo personal autorizado.
 *
 * Body JSON: { "filas": [ { "Nombre": "...", "Cédula": "...", ... }, ... ] }
 * Respuesta: { success, creados, omitidos, errores, message }
 */
session_start();
require_once __DIR__ . '/../lib/core/api.php';
include __DIR__ . '/../core/conexion.php';
require_once __DIR__ . '/../lib/seguridad/seguridad.php';

api_json();
api_require_roles(['ecografista', 'administrador', 'recepcionista']);
api_require_post();
api_require_csrf();

$filas = api_body()['filas'] ?? null;
if (!is_array($filas) || count($filas) === 0) {
    api_fail('No se recibieron filas para importar.');
}
if (count($filas) > 1000) {
    api_fail('Demasiadas filas (máximo 1000 por importación).');
}

$creado_por_id = api_uid();

/** Normaliza una clave de cabecera: minúsculas, sin acentos ni separadores. */
function imp_norm_key(string $k): string
{
    $k = mb_strtolower(trim($k), 'UTF-8');
    $k = strtr($k, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'ü' => 'u']);
    return preg_replace('/[^a-z0-9]/', '', $k);
}

/** Primer valor no vacío de la fila cuyo header normalizado esté en $aliases. */
function imp_val(array $rowNorm, array $aliases): string
{
    foreach ($aliases as $a) {
        if (isset($rowNorm[$a]) && trim((string)$rowNorm[$a]) !== '') {
            return trim((string)$rowNorm[$a]);
        }
    }
    return '';
}

$creados = 0;
$omitidos = 0;
$errores = 0;

$chkCorreo = $conex->prepare("SELECT id FROM usuarios WHERE correo = ? LIMIT 1");
$chkCedula = $conex->prepare("SELECT id FROM usuarios WHERE cedula = ? LIMIT 1");
$insert = $conex->prepare(
    "INSERT INTO usuarios
        (nombre_completo, fecha_nacimiento, cedula, direccion, telefono, correo, contrasena, rol, estado, email_verificado, creado_por_id)
     VALUES (?, ?, ?, ?, ?, ?, ?, 'paciente', 'aprobado', 1, ?)"
);

foreach ($filas as $fila) {
    if (!is_array($fila)) { $errores++; continue; }

    $rowNorm = [];
    foreach ($fila as $k => $v) { $rowNorm[imp_norm_key((string)$k)] = $v; }

    $nombre    = imp_val($rowNorm, ['nombre', 'nombrecompleto', 'nombreyapellido', 'paciente']);
    $cedula    = imp_val($rowNorm, ['cedula', 'cedulanumero', 'documento', 'ci']);
    $correo    = imp_val($rowNorm, ['correo', 'email', 'correoelectronico']);
    $telefono  = imp_val($rowNorm, ['telefono', 'tlf', 'celular', 'movil']);
    $direccion = imp_val($rowNorm, ['direccion', 'domicilio']);
    $fnac_raw  = imp_val($rowNorm, ['fechanacimiento', 'fechadenacimiento', 'nacimiento', 'fechanac']);

    // Requiere al menos nombre y (correo o cédula)
    if ($nombre === '' || ($correo === '' && $cedula === '')) { $errores++; continue; }

    // Fecha de nacimiento opcional → NULL si no es válida
    $fecha_nac = null;
    if ($fnac_raw !== '') {
        $ts = strtotime($fnac_raw);
        if ($ts !== false) { $fecha_nac = date('Y-m-d', $ts); }
    }

    // Omitir duplicados por correo o cédula
    $existe = false;
    if ($correo !== '') {
        $chkCorreo->bind_param('s', $correo);
        $chkCorreo->execute();
        if ($chkCorreo->get_result()->num_rows > 0) { $existe = true; }
    }
    if (!$existe && $cedula !== '') {
        $chkCedula->bind_param('s', $cedula);
        $chkCedula->execute();
        if ($chkCedula->get_result()->num_rows > 0) { $existe = true; }
    }
    if ($existe) { $omitidos++; continue; }

    $pass_hash = password_hash(bin2hex(random_bytes(4)), PASSWORD_DEFAULT);
    $insert->bind_param('sssssssi', $nombre, $fecha_nac, $cedula, $direccion, $telefono, $correo, $pass_hash, $creado_por_id);
    if ($insert->execute()) {
        $creados++;
    } elseif ($insert->errno === 1062) {
        $omitidos++; // carrera contra el índice único
    } else {
        error_log('importar_pacientes: ' . $insert->error);
        $errores++;
    }
}

$chkCorreo->close();
$chkCedula->close();
$insert->close();

if (function_exists('eco_auditar')) {
    eco_auditar($conex, 'pacientes_importados', [
        'detalle' => ['creados' => $creados, 'omitidos' => $omitidos, 'errores' => $errores],
    ]);
}
$conex->close();

api_ok([
    'creados'  => $creados,
    'omitidos' => $omitidos,
    'errores'  => $errores,
    'message'  => "Importación completada: {$creados} creados, {$omitidos} omitidos"
        . ($errores > 0 ? ", {$errores} con datos inválidos." : '.'),
]);
