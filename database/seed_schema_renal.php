<?php
/**
 * Ejecutar UNA SOLA VEZ para registrar/actualizar el esquema de Ecografía Renal.
 * Acceder desde: http://localhost/Sistema_EcoMadelleineV1/database/seed_schema_renal.php
 * También se puede ejecutar via PHP CLI.
 */
/* Guarda: escribe en la base de datos, solo por linea de comandos.
   Sin esto, cualquiera podia dispararlo desde el navegador. */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script solo puede ejecutarse por linea de comandos.');
}
include __DIR__ . '/../core/conexion.php';

/* Campos comunes a ambos riñones (se renderizan en columnas paralelas D / I) */
$campos_rinon = [
    /* Medidas */
    ['nombre' => 'medida_l',  'etiqueta' => 'Medida L',  'tipo' => 'number', 'ancho' => 'tercio', 'unidad' => 'mm'],
    ['nombre' => 'medida_ap', 'etiqueta' => 'Medida AP', 'tipo' => 'number', 'ancho' => 'tercio', 'unidad' => 'mm'],
    ['nombre' => 'medida_t',  'etiqueta' => 'Medida T',  'tipo' => 'number', 'ancho' => 'tercio', 'unidad' => 'mm'],
    ['nombre' => 'volumen',   'etiqueta' => 'Volumen',   'tipo' => 'number', 'ancho' => 'completo', 'unidad' => 'ml'],

    /* Forma y tamaño */
    ['nombre' => 'forma_situacion_normal', 'etiqueta' => 'Forma, situación y tamaño normal', 'tipo' => 'radio_sinno', 'ancho' => 'completo'],
    ['nombre' => 'aumentado',              'etiqueta' => 'Aumentado',                         'tipo' => 'radio_sinno', 'ancho' => 'medio'],
    ['nombre' => 'pequeno',                'etiqueta' => 'Pequeño',                           'tipo' => 'radio_sinno', 'ancho' => 'medio'],

    /* Litiasis */
    ['nombre' => 'litiasis',         'etiqueta' => 'Se observan Litiasis',  'tipo' => 'radio_sinno', 'ancho' => 'completo'],
    ['nombre' => 'litiasis_medida',  'etiqueta' => 'Litiasis — Medida',     'tipo' => 'number',      'ancho' => 'completo', 'unidad' => 'mm',
     'depende_de' => 'litiasis', 'depende_valor' => 'SI'],

    /* Microlitiasis */
    ['nombre' => 'microlitiasis',         'etiqueta' => 'Se observan Microlitiasis', 'tipo' => 'radio_sinno', 'ancho' => 'completo'],
    ['nombre' => 'microlitiasis_medida',  'etiqueta' => 'Microlitiasis — Medida',    'tipo' => 'number',      'ancho' => 'completo', 'unidad' => 'mm',
     'depende_de' => 'microlitiasis', 'depende_valor' => 'SI'],

    /* Quiste */
    ['nombre' => 'quiste',     'etiqueta' => 'Se observa Quiste', 'tipo' => 'radio_sinno', 'ancho' => 'completo'],
    ['nombre' => 'quiste_l',   'etiqueta' => 'Quiste — L',        'tipo' => 'number',      'ancho' => 'tercio', 'unidad' => 'mm',
     'depende_de' => 'quiste', 'depende_valor' => 'SI'],
    ['nombre' => 'quiste_ap',  'etiqueta' => 'Quiste — AP',       'tipo' => 'number',      'ancho' => 'tercio', 'unidad' => 'mm',
     'depende_de' => 'quiste', 'depende_valor' => 'SI'],
    ['nombre' => 'quiste_t',   'etiqueta' => 'Quiste — T',        'tipo' => 'number',      'ancho' => 'tercio', 'unidad' => 'mm',
     'depende_de' => 'quiste', 'depende_valor' => 'SI'],

    /* Parénquima */
    ['nombre' => 'parenquima', 'etiqueta' => 'Parénquima (con relación al parénquima hepático)', 'tipo' => 'radio',
     'opciones' => ['Conservado', 'Disminuido', 'Aumentado'], 'ancho' => 'completo'],

    /* Hallazgos */
    ['nombre' => 'imagenes_tumorales',       'etiqueta' => 'Imágenes tumorales visibles', 'tipo' => 'radio_sinno', 'ancho' => 'completo'],
    ['nombre' => 'dilatacion_pielocalicial', 'etiqueta' => 'Dilatación pielocalicial',    'tipo' => 'radio_sinno', 'ancho' => 'completo'],
    ['nombre' => 'colecciones_liquidas',     'etiqueta' => 'Colecciones líquidas',        'tipo' => 'radio_sinno', 'ancho' => 'completo'],
    ['nombre' => 'ureteres_visibles',        'etiqueta' => 'Uréteres visibles',           'tipo' => 'radio_sinno', 'ancho' => 'completo'],
];

$schema = [
    'version'   => 1,
    'secciones' => [

        /* ─── 1. Encabezado ─── */
        [
            'id'        => 'encabezado',
            'titulo'    => 'Datos del Paciente',
            'icono'     => 'fa-solid fa-id-card',
            'subtitulo' => 'Se realiza estudio en tiempo real con transductor convexo multifrecuencia, observándose:',
            'campos'    => [
                ['nombre' => 'nombres_apellidos', 'etiqueta' => 'Nombres y Apellidos', 'tipo' => 'text',     'ancho' => 'completo', 'requerido' => true],
                ['nombre' => 'edad',              'etiqueta' => 'Edad',                'tipo' => 'number',   'ancho' => 'tercio',   'unidad' => 'años', 'min' => 0, 'readonly' => true],
                ['nombre' => 'cedula',            'etiqueta' => 'Cédula (CI)',         'tipo' => 'text',     'ancho' => 'tercio',   'readonly' => true],
                ['nombre' => 'fecha',             'etiqueta' => 'Fecha',               'tipo' => 'date',     'ancho' => 'tercio',   'requerido' => true, 'readonly' => true],
                ['nombre' => 'motivo_consulta',   'etiqueta' => 'Motivo de Consulta',  'tipo' => 'textarea', 'ancho' => 'completo', 'filas' => 2, 'requerido' => true],
            ],
        ],

        /* ─── 2. Riñones (par: Derecho / Izquierdo) ─── */
        [
            'id'           => 'rinones',
            'titulo'       => 'Riñones',
            'icono'        => 'fa-solid fa-shield-halved',
            'tipo_seccion' => 'par',
            'lados'        => ['Riñón Derecho', 'Riñón Izquierdo'],
            'ids_lados'    => ['rinon_der', 'rinon_izq'],
            'campos'       => $campos_rinon,
        ],

        /* ─── 3. Vejiga y Espacio de Morrison ─── */
        [
            'id'     => 'vejiga_morrison',
            'titulo' => 'Vejiga y Espacio de Morrison',
            'icono'  => 'fa-solid fa-circle-dot',
            'campos' => [
                ['nombre' => 'paredes_descripcion',      'etiqueta' => 'De paredes',                                                            'tipo' => 'text',        'ancho' => 'medio'],
                ['nombre' => 'paredes_medida',           'etiqueta' => 'Medida de paredes',                                                     'tipo' => 'number',      'ancho' => 'medio',  'unidad' => 'mm'],
                ['nombre' => 'bordes_sin_anormalidades', 'etiqueta' => 'Bordes regulares, sin anormalidades en pared ni imágenes patológicas', 'tipo' => 'radio_sinno', 'ancho' => 'completo'],
                ['nombre' => 'litiasis',                 'etiqueta' => 'Litiasis',                                                              'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'litiasis_tamano',          'etiqueta' => 'Tamaño Litiasis',                                                       'tipo' => 'number',      'ancho' => 'medio',  'unidad' => 'mm',
                 'depende_de' => 'litiasis', 'depende_valor' => 'SI'],
                ['nombre' => 'sedimentos',               'etiqueta' => 'Sedimentos',                                                            'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'morrison_libre',           'etiqueta' => 'Espacio de Morrison Libre',                                             'tipo' => 'radio_sinno', 'ancho' => 'medio'],
            ],
        ],

        /* ─── 4. Conclusión y Diagnóstico ─── */
        [
            'id'     => 'conclusion',
            'titulo' => 'Conclusión y Diagnóstico',
            'icono'  => 'fa-solid fa-file-medical',
            'campos' => [
                ['nombre' => 'conclusion',    'etiqueta' => 'Conclusión',    'tipo' => 'textarea', 'filas' => 5, 'ancho' => 'completo', 'requerido' => true],
                ['nombre' => 'diagnostico_1', 'etiqueta' => 'Diagnóstico 1', 'tipo' => 'textarea', 'filas' => 3, 'ancho' => 'completo'],
            ],
        ],
    ],
];

$json        = json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
$descripcion = 'Estudio ecográfico de ambos riñones, vejiga y espacio de Morrison.';
$icono       = 'fa-solid fa-shield-halved';

$sql = "UPDATE tipos_ecografias
        SET esquema_campos  = ?,
            esquema_version = esquema_version + 1,
            descripcion     = ?,
            icono           = ?
        WHERE codigo = 'ECO_RENAL'";

$stmt = $conex->prepare($sql);
$stmt->bind_param('sss', $json, $descripcion, $icono);

echo '<style>body{font-family:monospace;background:#f6f8fa;padding:24px;}pre{background:#f0fff4;padding:20px;border-radius:8px;font-size:13px;}</style>';

if ($stmt->execute()) {
    $filas = $stmt->affected_rows;
    $stmt->close();

    echo '<pre>';
    if ($filas > 0) {
        echo '<strong style="color:#15803d;">✔ Ecografía Renal actualizada correctamente.</strong>' . "\n\n";
    } else {
        echo '<strong style="color:#b45309;">⚠ No se encontró el registro (codigo=ECO_RENAL).</strong>' . "\n\n";
    }
    echo 'Registros actualizados : ' . $filas . "\n";
    echo 'Secciones en esquema  : ' . count($schema['secciones']) . "\n\n";
    foreach ($schema['secciones'] as $s) {
        $c   = count($s['campos'] ?? []);
        $par = (($s['tipo_seccion'] ?? '') === 'par') ? ' [PAR: ' . implode(' / ', $s['lados']) . ']' : '';
        echo '  • ' . $s['titulo'] . ' — ' . $c . ' campo(s)' . $par . "\n";
    }
    echo "\n<strong>Siguiente paso:</strong> Recarga el modal y selecciona \"Ecografía Renal\".\n";
    echo '</pre>';
} else {
    echo '<p style="color:red;font-size:14px;"><strong>Error:</strong> ' . htmlspecialchars($conex->error) . '</p>';
    $stmt->close();
}

$conex->close();
