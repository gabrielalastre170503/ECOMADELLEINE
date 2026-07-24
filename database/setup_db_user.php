<?php
/**
 * setup_db_user.php — Aprovisiona (o re-sincroniza) el usuario de aplicacion de
 * MySQL/MariaDB para que SIEMPRE coincida con el .env. SOLO CLI.
 *
 * Por que existe:
 *   El error "Access denied for user 'eco_app'@'localhost'" ocurre cuando el
 *   usuario de MySQL no existe o su contraseña no coincide con DB_PASS del .env.
 *   Esto pasa al reinstalar XAMPP, restaurar la BD desde un backup, o montar el
 *   proyecto en otra maquina. Este script elimina ese problema de raiz: lee el
 *   .env (unica fuente de verdad) y deja el usuario, su contraseña y sus
 *   privilegios exactamente como la aplicacion los necesita.
 *
 * Es idempotente: se puede ejecutar cuantas veces haga falta, siempre deja el
 * mismo estado final.
 *
 * Uso:
 *   php database/setup_db_user.php
 *   php database/setup_db_user.php --admin-user=root --admin-pass=TU_PASS_ROOT
 *
 * El usuario administrador (para poder crear usuarios/otorgar permisos) se toma,
 * en este orden, de:
 *   1) --admin-user / --admin-pass en la linea de comandos
 *   2) las variables de entorno DB_ADMIN_USER / DB_ADMIN_PASS
 *   3) por defecto: root sin contraseña (default de XAMPP)
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script solo se ejecuta por línea de comandos.');
}

require_once __DIR__ . '/../config/env_loader.php';
$ENV_PATH = __DIR__ . '/../.env';

if (!is_file($ENV_PATH)) {
    fwrite(STDERR, "ERROR: no se encontro el .env en $ENV_PATH.\n" .
        "Copia .env.example a .env en la raiz del proyecto y rellena las credenciales.\n");
    exit(1);
}
eco_load_env($ENV_PATH);

// --- Credenciales de la aplicacion (destino a garantizar) ---
$DB_HOST = eco_env('DB_HOST', 'localhost');
$DB_USER = eco_env('DB_USER', 'eco_app');
$DB_PASS = eco_env('DB_PASS', '');
$DB_NAME = eco_env('DB_NAME', 'db_clinica_ecografias');

if ($DB_USER === '' || $DB_NAME === '') {
    fwrite(STDERR, "ERROR: DB_USER y DB_NAME no pueden estar vacios en el .env.\n");
    exit(1);
}
if ($DB_PASS === '') {
    fwrite(STDERR, "ADVERTENCIA: DB_PASS esta vacio en el .env. Se creara el usuario sin contraseña.\n");
}

// --- Credenciales del administrador (para aprovisionar) ---
$adminUser = null;
$adminPass = null;
foreach ($argv as $arg) {
    if (strpos($arg, '--admin-user=') === 0) $adminUser = substr($arg, 13);
    if (strpos($arg, '--admin-pass=') === 0) $adminPass = substr($arg, 13);
}
if ($adminUser === null) $adminUser = getenv('DB_ADMIN_USER') !== false ? getenv('DB_ADMIN_USER') : 'root';
if ($adminPass === null) $adminPass = getenv('DB_ADMIN_PASS') !== false ? getenv('DB_ADMIN_PASS') : '';

// Reportar errores de mysqli como excepciones para capturarlas con claridad.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

echo "Sincronizando usuario de BD con el .env ...\n";
echo "  host    : $DB_HOST\n";
echo "  usuario : $DB_USER\n";
echo "  base    : $DB_NAME\n";
echo "  admin   : $adminUser\n\n";

try {
    // Conectar como admin, SIN seleccionar base (puede que aun no exista).
    $adm = mysqli_connect($DB_HOST, $adminUser, $adminPass);
} catch (\mysqli_sql_exception $e) {
    fwrite(STDERR, "ERROR: no se pudo conectar como administrador '$adminUser'@'$DB_HOST'.\n" .
        '  Detalle: ' . $e->getMessage() . "\n\n" .
        "Si tu MySQL root tiene contraseña, pasala asi:\n" .
        "  php database/setup_db_user.php --admin-user=root --admin-pass=TU_PASS_ROOT\n");
    exit(1);
}

try {
    // Escapamos todo lo que se interpola en SQL (los nombres vienen del .env, que
    // es de confianza, pero escapar evita sorpresas con caracteres especiales).
    $userEsc = mysqli_real_escape_string($adm, $DB_USER);
    $passEsc = mysqli_real_escape_string($adm, $DB_PASS);
    $hostEsc = mysqli_real_escape_string($adm, 'localhost'); // el usuario de la app entra por localhost
    // Nombre de BD: solo permitimos identificadores seguros para ir entre backticks.
    if (!preg_match('/^[A-Za-z0-9_]+$/', $DB_NAME)) {
        fwrite(STDERR, "ERROR: DB_NAME contiene caracteres no permitidos: $DB_NAME\n");
        exit(1);
    }

    // 1) Base de datos (no la sobrescribe si ya existe).
    mysqli_query($adm, "CREATE DATABASE IF NOT EXISTS `$DB_NAME` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "  [ok] base de datos `$DB_NAME` disponible\n";

    // 2) Usuario: crear si no existe y, exista o no, forzar la contraseña del .env.
    mysqli_query($adm, "CREATE USER IF NOT EXISTS '$userEsc'@'$hostEsc' IDENTIFIED BY '$passEsc'");
    mysqli_query($adm, "SET PASSWORD FOR '$userEsc'@'$hostEsc' = PASSWORD('$passEsc')");
    echo "  [ok] usuario '$DB_USER'@'localhost' creado / contraseña sincronizada\n";

    // 3) Privilegios minimos sobre la base de la app (least privilege).
    mysqli_query($adm, "GRANT SELECT, INSERT, UPDATE, DELETE ON `$DB_NAME`.* TO '$userEsc'@'$hostEsc'");
    mysqli_query($adm, "FLUSH PRIVILEGES");
    echo "  [ok] privilegios (SELECT, INSERT, UPDATE, DELETE) otorgados\n\n";
} catch (\mysqli_sql_exception $e) {
    fwrite(STDERR, 'ERROR aprovisionando el usuario: ' . $e->getMessage() . "\n");
    exit(1);
}

mysqli_close($adm);

// 4) Verificacion final: conectar YA como el usuario de la app, tal cual lo hara
//    la aplicacion. Si esto pasa, la web funcionara.
try {
    $test = mysqli_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    mysqli_close($test);
    echo "VERIFICACION OK: '$DB_USER' puede conectarse a '$DB_NAME'. La aplicacion funcionara.\n";
    exit(0);
} catch (\mysqli_sql_exception $e) {
    fwrite(STDERR, "VERIFICACION FALLIDA: el usuario de la app aun no conecta.\n" .
        '  Detalle: ' . $e->getMessage() . "\n");
    exit(1);
}
