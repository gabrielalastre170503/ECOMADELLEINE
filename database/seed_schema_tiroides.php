<?php
/**
 * Ejecutar UNA SOLA VEZ para actualizar el esquema de Ecografía de Tiroides
 * con la estructura LITERAL extraída del documento físico Dra. Madelleine Toro.
 * Acceder desde: http://localhost/Sistema_EcoMadelleineV1/database/seed_schema_tiroides.php
 */
/* Guarda: escribe en la base de datos, solo por linea de comandos.
   Sin esto, cualquiera podia dispararlo desde el navegador. */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script solo puede ejecutarse por linea de comandos.');
}
include __DIR__ . '/../core/conexion.php';

/* Campos comunes a ambos lóbulos (par: Derecho / Izquierdo) */
$campos_lobulo = [
    ['nombre' => 'descripcion', 'etiqueta' => 'Descripción / Hallazgos del lóbulo', 'tipo' => 'textarea', 'filas' => 3, 'ancho' => 'completo'],
    ['nombre' => 'medida_l',    'etiqueta' => 'Medida L',                            'tipo' => 'number',   'ancho' => 'tercio', 'unidad' => 'mm'],
    ['nombre' => 'medida_ap',   'etiqueta' => 'Medida AP',                           'tipo' => 'number',   'ancho' => 'tercio', 'unidad' => 'mm'],
    ['nombre' => 'medida_t',    'etiqueta' => 'Medida T',                            'tipo' => 'number',   'ancho' => 'tercio', 'unidad' => 'mm'],
];

$schema = [
    'version'   => 1,
    'secciones' => [

        /* ─── 1. Encabezado ─── */
        [
            'id'        => 'encabezado',
            'titulo'    => 'Datos del Paciente',
            'icono'     => 'fa-solid fa-id-card',
            'subtitulo' => 'Se realiza estudio en tiempo real con transductor lineal multifrecuencial, observándose:',
            'campos'    => [
                ['nombre' => 'nombres_apellidos', 'etiqueta' => 'Nombres y Apellidos', 'tipo' => 'text',     'ancho' => 'completo', 'requerido' => true],
                ['nombre' => 'edad',              'etiqueta' => 'Edad',                'tipo' => 'number',   'ancho' => 'tercio',   'unidad' => 'años', 'min' => 0, 'readonly' => true],
                ['nombre' => 'cedula',            'etiqueta' => 'Cédula (CI)',         'tipo' => 'text',     'ancho' => 'tercio',   'readonly' => true],
                ['nombre' => 'fecha',             'etiqueta' => 'Fecha',               'tipo' => 'date',     'ancho' => 'tercio',   'requerido' => true, 'readonly' => true],
                ['nombre' => 'motivo_consulta',   'etiqueta' => 'Motivo de Consulta',  'tipo' => 'textarea', 'ancho' => 'completo', 'filas' => 2, 'requerido' => true],
            ],
        ],

        /* ─── 2. Estructuras Superficiales ─── */
        [
            'id'     => 'estructuras_superficiales',
            'titulo' => 'Estructuras Superficiales',
            'icono'  => 'fa-solid fa-layer-group',
            'campos' => [
                ['nombre' => 'piel',                              'etiqueta' => 'Piel',                                  'tipo' => 'textarea', 'ancho' => 'completo', 'filas' => 2],
                ['nombre' => 'tejido_celular_subcutaneo',         'etiqueta' => 'Tejido celular subcutáneo',             'tipo' => 'textarea', 'ancho' => 'completo', 'filas' => 2],
                ['nombre' => 'estructuras_musculares_vasculares', 'etiqueta' => 'Estructuras musculares y vasculares',   'tipo' => 'textarea', 'ancho' => 'completo', 'filas' => 2],
            ],
        ],

        /* ─── 3. Tiroides — Istmo ─── */
        [
            'id'     => 'tiroides_istmo',
            'titulo' => 'Tiroides — Istmo',
            'icono'  => 'fa-solid fa-circle-nodes',
            'campos' => [
                ['nombre' => 'descripcion',   'etiqueta' => 'Descripción / Hallazgos del istmo', 'tipo' => 'textarea', 'ancho' => 'completo', 'filas' => 2],
                ['nombre' => 'medida_ap',     'etiqueta' => 'Medida en AP',                       'tipo' => 'number',   'ancho' => 'tercio',   'unidad' => 'mm'],
            ],
        ],

        /* ─── 4. Tiroides — Lóbulos (par: Derecho / Izquierdo) ─── */
        [
            'id'           => 'tiroides_lobulos',
            'titulo'       => 'Tiroides — Lóbulos',
            'icono'        => 'fa-solid fa-shield-halved',
            'tipo_seccion' => 'par',
            'lados'        => ['Lóbulo Derecho', 'Lóbulo Izquierdo'],
            'ids_lados'    => ['lobulo_der', 'lobulo_izq'],
            'campos'       => $campos_lobulo,
        ],

        /* ─── 5. Conclusión ─── */
        [
            'id'     => 'conclusion',
            'titulo' => 'Conclusión',
            'icono'  => 'fa-solid fa-file-medical',
            'campos' => [
                ['nombre' => 'conclusion', 'etiqueta' => 'Estudio Tiroideo — con signos sugestivos de:', 'tipo' => 'textarea', 'filas' => 6, 'ancho' => 'completo', 'requerido' => true],
            ],
        ],
    ],
];

$json        = json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
$descripcion = 'Informe de ecosonograma tiroideo: estructuras superficiales, istmo y lóbulos tiroideos (derecho e izquierdo).';
$icono       = 'fa-solid fa-shield-halved';

$sql = "UPDATE tipos_ecografias
        SET esquema_campos  = ?,
            esquema_version = esquema_version + 1,
            descripcion     = ?,
            icono           = ?
        WHERE codigo = 'eco_tiroides'";

$stmt = $conex->prepare($sql);
$stmt->bind_param('sss', $json, $descripcion, $icono);

echo '<style>body{font-family:monospace;background:#f6f8fa;padding:24px;}pre{background:#f0fff4;padding:20px;border-radius:8px;font-size:13px;}</style>';

if ($stmt->execute()) {
    $filas = $stmt->affected_rows;
    $stmt->close();

    echo '<pre>';
    if ($filas > 0) {
        echo '<strong style="color:#15803d;">✔ Esquema de Ecografía de Tiroides actualizado (literal al documento físico).</strong>' . "\n\n";
    } else {
        echo '<strong style="color:#b45309;">⚠ No se encontró el registro (codigo=eco_tiroides).</strong>' . "\n\n";
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
    echo "\n<strong>Siguiente paso:</strong> Recarga (Ctrl+Shift+R), abre la modal de Ecografía de Tiroides.\n";
    echo '</pre>';
} else {
    echo '<p style="color:red;font-size:14px;"><strong>Error:</strong> ' . htmlspecialchars($conex->error) . '</p>';
    $stmt->close();
}

$conex->close();
