<?php
/**
 * Ejecutar UNA SOLA VEZ para actualizar el esquema de Ecografía Testicular
 * con la estructura LITERAL extraída del documento físico Dra. Madelleine Toro.
 * Acceder desde: http://localhost/Sistema_EcoMadelleineV1/database/seed_schema_testicular.php
 */
/* Guarda: escribe en la base de datos, solo por linea de comandos.
   Sin esto, cualquiera podia dispararlo desde el navegador. */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script solo puede ejecutarse por linea de comandos.');
}
include __DIR__ . '/../core/conexion.php';

/* Campos comunes a ambos testículos (par: Derecho / Izquierdo) */
$campos_testiculo = [
    /* Medidas */
    ['nombre' => 'lon_mm', 'etiqueta' => 'LON', 'tipo' => 'number', 'ancho' => 'tercio', 'unidad' => 'mm'],
    ['nombre' => 'ap_mm',  'etiqueta' => 'AP',  'tipo' => 'number', 'ancho' => 'tercio', 'unidad' => 'mm'],
    ['nombre' => 't_mm',   'etiqueta' => 'T',   'tipo' => 'number', 'ancho' => 'tercio', 'unidad' => 'mm'],

    /* Forma y tamaño */
    ['nombre' => 'bolsa_escrotal_aumentado', 'etiqueta' => 'En su bolsa escrotal — De forma y tamaño aumentado',                                 'tipo' => 'radio_sinno', 'ancho' => 'medio'],
    ['nombre' => 'bolsa_escrotal_conservado','etiqueta' => 'Conservado',                                                                          'tipo' => 'radio_sinno', 'ancho' => 'medio'],

    /* Parénquima */
    ['nombre' => 'parenquima_homogeneo_sin_tu', 'etiqueta' => 'Parénquima homogéneo de ecogenicidad conservada, sin imágenes sugestivas de TU sólido o quístico', 'tipo' => 'radio_sinno', 'ancho' => 'completo'],

    /* Epidídimo */
    ['nombre' => 'epididimo_con_quiste', 'etiqueta' => 'Cabeza de epidídimo sin alteraciones ecográficas aparentes con presencia de quiste', 'tipo' => 'radio_sinno', 'ancho' => 'completo'],

    /* Otras alteraciones */
    ['nombre' => 'sin_alteraciones_escroto', 'etiqueta' => 'No se observó alteraciones del escroto ni imágenes sugestivas de TU sólido o quístico', 'tipo' => 'radio_sinno', 'ancho' => 'completo'],
    ['nombre' => 'varicocele',  'etiqueta' => 'Se visualiza varicocele', 'tipo' => 'radio_sinno', 'ancho' => 'tercio'],
    ['nombre' => 'hidrocele',   'etiqueta' => 'Se visualiza hidrocele',  'tipo' => 'radio_sinno', 'ancho' => 'tercio'],
    ['nombre' => 'hernia',      'etiqueta' => 'Se visualiza hernia',     'tipo' => 'radio_sinno', 'ancho' => 'tercio'],

    /* Plexo Pampiniforme */
    ['nombre' => 'plexo_calibre_normal', 'etiqueta' => 'Plexo Pampiniforme — De calibre normal', 'tipo' => 'radio_sinno', 'ancho' => 'medio'],
    ['nombre' => 'plexo_presencia_hernias', 'etiqueta' => 'Plexo Pampiniforme — Presencia de hernias', 'tipo' => 'radio_sinno', 'ancho' => 'medio'],
];

$schema = [
    'version'   => 1,
    'secciones' => [

        /* ─── 1. Encabezado ─── */
        [
            'id'        => 'encabezado',
            'titulo'    => 'Datos del Paciente',
            'icono'     => 'fa-solid fa-id-card',
            'subtitulo' => 'Con equipo de alta resolución se realiza exploración de ambos escrotos con transductor lineal de 7.5 - 10 MHz, observándose:',
            'campos'    => [
                ['nombre' => 'nombres_apellidos', 'etiqueta' => 'Nombres y Apellidos', 'tipo' => 'text',     'ancho' => 'completo', 'requerido' => true],
                ['nombre' => 'edad',              'etiqueta' => 'Edad',                'tipo' => 'number',   'ancho' => 'tercio',   'unidad' => 'años', 'min' => 0, 'readonly' => true],
                ['nombre' => 'cedula',            'etiqueta' => 'Cédula (CI)',         'tipo' => 'text',     'ancho' => 'tercio',   'readonly' => true],
                ['nombre' => 'fecha',             'etiqueta' => 'Fecha',               'tipo' => 'date',     'ancho' => 'tercio',   'requerido' => true, 'readonly' => true],
                ['nombre' => 'motivo_consulta',   'etiqueta' => 'Motivo de Consulta',  'tipo' => 'textarea', 'ancho' => 'completo', 'filas' => 2, 'requerido' => true],
            ],
        ],

        /* ─── 2. Testículos (par: Derecho / Izquierdo) ─── */
        [
            'id'           => 'testiculos',
            'titulo'       => 'Testículos',
            'icono'        => 'fa-solid fa-mars',
            'tipo_seccion' => 'par',
            'lados'        => ['Testículo Derecho', 'Testículo Izquierdo'],
            'ids_lados'    => ['testiculo_der', 'testiculo_izq'],
            'campos'       => $campos_testiculo,
        ],

        /* ─── 3. Conclusión ─── */
        [
            'id'     => 'conclusion',
            'titulo' => 'Conclusión',
            'icono'  => 'fa-solid fa-file-medical',
            'campos' => [
                ['nombre' => 'conclusion', 'etiqueta' => 'Signos ecográficos sugestivos de:', 'tipo' => 'textarea', 'filas' => 6, 'ancho' => 'completo', 'requerido' => true],
            ],
        ],
    ],
];

$json        = json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
$descripcion = 'Ecosonograma testicular: exploración bilateral con plexo pampiniforme (derecho e izquierdo).';
$icono       = 'fa-solid fa-mars';

$sql = "UPDATE tipos_ecografias
        SET esquema_campos  = ?,
            esquema_version = esquema_version + 1,
            descripcion     = ?,
            icono           = ?
        WHERE codigo = 'ECO_TEST'";

$stmt = $conex->prepare($sql);
$stmt->bind_param('sss', $json, $descripcion, $icono);

echo '<style>body{font-family:monospace;background:#f6f8fa;padding:24px;}pre{background:#f0fff4;padding:20px;border-radius:8px;font-size:13px;}</style>';

if ($stmt->execute()) {
    $filas = $stmt->affected_rows;
    $stmt->close();

    echo '<pre>';
    if ($filas > 0) {
        echo '<strong style="color:#15803d;">✔ Esquema de Ecografía Testicular actualizado (literal al documento físico).</strong>' . "\n\n";
    } else {
        echo '<strong style="color:#b45309;">⚠ No se encontró el registro (codigo=ECO_TEST).</strong>' . "\n\n";
    }
    echo 'Registros actualizados : ' . $filas . "\n";
    echo 'Secciones en esquema  : ' . count($schema['secciones']) . "\n\n";
    $total = 0;
    foreach ($schema['secciones'] as $s) {
        $c   = count($s['campos'] ?? []);
        $par = (($s['tipo_seccion'] ?? '') === 'par') ? ' [PAR: ' . implode(' / ', $s['lados']) . ']' : '';
        $total += $c;
        echo '  • ' . $s['titulo'] . ' — ' . $c . ' campo(s)' . $par . "\n";
    }
    echo "\n<strong>Total de campos:</strong> " . $total . "\n";
    echo "\n<strong>Siguiente paso:</strong> Recarga (Ctrl+Shift+R), abre la modal de Ecografía Testicular.\n";
    echo '</pre>';
} else {
    echo '<p style="color:red;font-size:14px;"><strong>Error:</strong> ' . htmlspecialchars($conex->error) . '</p>';
    $stmt->close();
}

$conex->close();
