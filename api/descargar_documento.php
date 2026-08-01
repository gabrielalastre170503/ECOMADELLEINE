<?php
/**
 * Sirve un documento del repositorio interno con control de acceso.
 *
 * La carpeta documentos/ se enlazaba directa desde la pagina del repositorio y
 * no tenia .htaccess: cualquiera que conociera el nombre del archivo lo
 * descargaba sin sesion. Ahora la carpeta esta denegada y este handler es la
 * unica via, con el mismo rol que exige admin_documentos.php (administrador).
 */
session_start();
require_once __DIR__ . '/../lib/core/api.php';

if (api_uid() <= 0) {
    http_response_code(403);
    exit('Acceso no autorizado.');
}
if (api_rol() !== 'administrador') {
    http_response_code(403);
    exit('No tienes acceso a este documento.');
}

$base = realpath(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'documentos');
if ($base === false) {
    http_response_code(404);
    exit('Repositorio no disponible.');
}

// basename() descarta cualquier componente de ruta ("../", subcarpetas) antes
// de tocar el disco; el realpath posterior es la comprobacion definitiva de que
// lo resuelto sigue dentro de documentos/.
$nombre = basename((string)($_GET['f'] ?? ''));
if ($nombre === '' || $nombre === '.' || $nombre === '..') {
    http_response_code(400);
    exit('Solicitud invalida.');
}

$abs = realpath($base . DIRECTORY_SEPARATOR . $nombre);
if ($abs === false || strpos($abs, $base . DIRECTORY_SEPARATOR) !== 0 || !is_file($abs)) {
    http_response_code(404);
    exit('Documento no encontrado.');
}

// Misma lista blanca que la subida: un archivo con otra extension no se sirve
// aunque de algun modo haya acabado en la carpeta.
$ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
$permitidas = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'zip', 'rar'];
if (!in_array($ext, $permitidas, true)) {
    http_response_code(403);
    exit('Tipo de documento no permitido.');
}

$tipos = [
    'pdf'  => 'application/pdf',
    'doc'  => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls'  => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'ppt'  => 'application/vnd.ms-powerpoint',
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'txt'  => 'text/plain; charset=utf-8',
    'csv'  => 'text/csv; charset=utf-8',
    'zip'  => 'application/zip',
    'rar'  => 'application/vnd.rar',
];
$mime = $tipos[$ext] ?? 'application/octet-stream';

// El PDF se abre en el visor (la pagina enlaza con target="_blank"); el resto
// se descarga. Los saltos de linea y comillas se quitan del nombre porque van
// dentro de una cabecera.
$inline    = ($ext === 'pdf');
$nombreCab = preg_replace('/[\r\n"]/', '', $nombre);

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)filesize($abs));
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $nombreCab . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, max-age=0');
readfile($abs);
exit;
