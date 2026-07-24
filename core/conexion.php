<?php
/*
 * conexion.php — Conexión central a la base de datos (MySQLi).
 *
 * Mejoras de seguridad aplicadas:
 *  - Ya NO se expone el error interno de MySQL al navegador (fuga de información).
 *    El detalle se registra en el log del servidor con error_log() y al usuario
 *    se le muestra un mensaje genérico.
 *  - Credenciales centralizadas. EN PRODUCCIÓN deben moverse a variables de
 *    entorno (getenv) y usar un usuario MySQL con privilegios mínimos, nunca 'root'.
 */

// --- Credenciales: se leen del .env ---
require_once __DIR__ . '/../config/env_loader.php';
$ENV_PATH = __DIR__ . '/../.env';
eco_load_env($ENV_PATH);

// Si el .env no está (ruta rota tras un refactor, archivo no creado, etc.)
// fallamos aqui mismo en vez de caer en silencio al fallback 'root' de
// eco_env(): eso enmascara el problema real y produce un mysqli_sql_exception
// confuso mas abajo (como paso al mover este archivo a core/ sin actualizar
// la ruta relativa al .env).
if (!is_file($ENV_PATH)) {
    error_log("conexion.php: no se encontro el .env esperado en $ENV_PATH. Copia .env.example a .env en la raiz del proyecto y rellena las credenciales.");
    http_response_code(503);
    die('El servicio no está disponible en este momento. Inténtalo más tarde.');
}

// Zona horaria del sistema (Venezuela, UTC-04:00). Se fija en PHP y, mas abajo,
// en la sesion de MySQL, para que NOW()/CURDATE() y date()/strtotime() coincidan
// (evita desfases en recordatorios, "hace ..." y throttling de login).
date_default_timezone_set(eco_env('APP_TZ', 'America/Caracas'));

$DB_HOST = eco_env('DB_HOST', 'localhost');
$DB_USER = eco_env('DB_USER', 'root');
$DB_PASS = eco_env('DB_PASS', '');
$DB_NAME = eco_env('DB_NAME', 'db_clinica_ecografias');

// En PHP 8.1+ mysqli reporta errores lanzando excepciones (MYSQLI_REPORT_ERROR |
// MYSQLI_REPORT_STRICT es el modo por defecto), asi que un fallo de conexion NO
// devuelve false: lanza mysqli_sql_exception. El '@' no suprime excepciones y el
// bloque if(!$conex) de abajo nunca se alcanzaba, por eso el usuario veia un
// "Fatal error" que ademas filtraba credenciales, host, BD y rutas. La capturamos
// aqui para registrar el detalle solo en el log y mostrar un mensaje generico.
try {
    $conex = @mysqli_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
} catch (\mysqli_sql_exception $e) {
    // Si es "Access denied", el usuario de MySQL esta desincronizado con el .env.
    // Se arregla ejecutando: php database/setup_db_user.php
    error_log('Error de conexion a la BD: ' . $e->getMessage() .
        ' | Si es "Access denied", ejecuta: php database/setup_db_user.php para re-sincronizar el usuario con el .env');
    http_response_code(503);
    die('El servicio no está disponible en este momento. Inténtalo más tarde.');
}

if (!$conex) {
    // Fallback por si el modo de reporte de mysqli estuviera desactivado (devuelve
    // false en vez de lanzar). El detalle solo va al log; nunca al cliente.
    error_log('Error de conexion a la BD: ' . mysqli_connect_error());
    http_response_code(503);
    die('El servicio no está disponible en este momento. Inténtalo más tarde.');
}

mysqli_set_charset($conex, 'utf8mb4');

// Alinea el reloj de la sesion MySQL con PHP (UTC-04:00). Offset fijo: no
// depende de las tablas de zonas horarias de MySQL.
@mysqli_query($conex, "SET time_zone = '" . eco_env('DB_TZ_OFFSET', '-04:00') . "'");
