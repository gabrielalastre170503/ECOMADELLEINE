<?php
/**
 * Ejecutar UNA SOLA VEZ para registrar/actualizar el esquema de Ecografía Abdominal Total.
 * Acceder desde: http://localhost/Sistema_EcoMadelleineV1/database/seed_schema_abd_total.php
 */
/* Guarda: escribe en la base de datos, solo por linea de comandos.
   Sin esto, cualquiera podia dispararlo desde el navegador. */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script solo puede ejecutarse por linea de comandos.');
}
include __DIR__ . '/../core/conexion.php';

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

        /* ─── 2. Hígado ─── */
        [
            'id'     => 'higado',
            'titulo' => 'Hígado',
            'icono'  => 'fa-solid fa-droplet',
            'campos' => [
                ['nombre' => 'parenquima_homogeneo',         'etiqueta' => 'Parénquima homogéneo',                               'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'parenquima_heterogeneo',       'etiqueta' => 'Parénquima heterogéneo',                             'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'bordes_regulares',             'etiqueta' => 'De bordes regulares',                                'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'tamano_normal',                'etiqueta' => 'Tamaño normal',                                      'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'tamano_aumentado',             'etiqueta' => 'Tamaño aumentado',                                   'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'ecogenicidad_aumentada',       'etiqueta' => 'Ecogenicidad aumentada',                             'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'lobulos_definidos',            'etiqueta' => 'Lóbulos Caudado, Izquierdo y Derecho bien definidos','tipo' => 'radio_sinno', 'ancho' => 'completo'],
                ['nombre' => 'hepatometria_lobulo_der',      'etiqueta' => 'Hepatometría — Lóbulo Derecho',                     'tipo' => 'number',      'ancho' => 'medio',  'unidad' => 'mm'],
                ['nombre' => 'hepatometria_lobulo_izq',      'etiqueta' => 'Hepatometría — Lóbulo Izquierdo',                   'tipo' => 'number',      'ancho' => 'medio',  'unidad' => 'mm'],
                ['nombre' => 'patron_vascular_conservado',   'etiqueta' => 'Patrón vascular conservado',                        'tipo' => 'radio_sinno', 'ancho' => 'medio'],
            ],
        ],

        /* ─── 3. Vías Biliares ─── */
        [
            'id'     => 'vias_biliares',
            'titulo' => 'Vías Biliares',
            'icono'  => 'fa-solid fa-water',
            'campos' => [
                ['nombre' => 'intrahepaticas_dilatadas',          'etiqueta' => 'Intrahepáticas dilatadas',                    'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'coledoco_dilatado',                 'etiqueta' => 'Colédoco dilatado',                           'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'vesicula_distendida_paredes_delg',  'etiqueta' => 'Vesícula biliar distendida de paredes delgadas','tipo' => 'radio_sinno','ancho' => 'completo'],
                ['nombre' => 'vesicula_medida',                   'etiqueta' => 'Vesícula biliar mide',                        'tipo' => 'number',      'ancho' => 'tercio', 'unidad' => 'mm'],
                ['nombre' => 'litiasis_en_interior',              'etiqueta' => 'Imágenes de litiasis en su interior',         'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'litiasis_tamano',                   'etiqueta' => 'Litiasis — Tamaño',                           'tipo' => 'number',      'ancho' => 'tercio', 'unidad' => 'mm',
                 'depende_de' => 'litiasis_en_interior', 'depende_valor' => 'SI'],
                ['nombre' => 'vesicula_medida_l',                 'etiqueta' => 'Vesícula Mide L',                             'tipo' => 'number',      'ancho' => 'tercio', 'unidad' => 'mm'],
                ['nombre' => 'vesicula_medida_t',                 'etiqueta' => 'Vesícula Mide T',                             'tipo' => 'number',      'ancho' => 'tercio', 'unidad' => 'mm'],
                ['nombre' => 'hepatocoledoco_dilatado',           'etiqueta' => 'Hepatocolédoco dilatado',                     'tipo' => 'radio_sinno', 'ancho' => 'medio'],
            ],
        ],

        /* ─── 4. Páncreas ─── */
        [
            'id'     => 'pancreas',
            'titulo' => 'Páncreas',
            'icono'  => 'fa-solid fa-stethoscope',
            'campos' => [
                ['nombre' => 'parenquima',      'etiqueta' => 'Parénquima',    'tipo' => 'radio',      'opciones' => ['Homogéneo', 'Heterogéneo'], 'ancho' => 'medio'],
                ['nombre' => 'lesiones_focales','etiqueta' => 'Lesiones focales','tipo' => 'radio_sinno','ancho' => 'medio'],
                ['nombre' => 'cabeza',          'etiqueta' => 'Cabeza',        'tipo' => 'number',     'ancho' => 'tercio', 'unidad' => 'mm'],
                ['nombre' => 'cuerpo',          'etiqueta' => 'Cuerpo',        'tipo' => 'number',     'ancho' => 'tercio', 'unidad' => 'mm'],
                ['nombre' => 'cola',            'etiqueta' => 'Cola',          'tipo' => 'number',     'ancho' => 'tercio', 'unidad' => 'mm'],
            ],
        ],

        /* ─── 5. Aorta y Bazo ─── */
        [
            'id'     => 'aorta_bazo',
            'titulo' => 'Aorta y Bazo',
            'icono'  => 'fa-solid fa-heart-pulse',
            'campos' => [
                ['nombre' => 'aorta_diametro',         'etiqueta' => 'Aorta Abdominal (posición Prevertebral) — Diámetro', 'tipo' => 'number',      'ancho' => 'medio', 'unidad' => 'mm'],
                ['nombre' => 'bazo_aspecto_conservado','etiqueta' => 'Bazo — Aspecto y ecoestructura conservada',           'tipo' => 'radio_sinno', 'ancho' => 'completo'],
                ['nombre' => 'bazo_medida_l',          'etiqueta' => 'Bazo Mide L',                                        'tipo' => 'number',      'ancho' => 'medio', 'unidad' => 'mm'],
                ['nombre' => 'bazo_medida_t',          'etiqueta' => 'Bazo Mide T',                                        'tipo' => 'number',      'ancho' => 'medio', 'unidad' => 'mm'],
            ],
        ],

        /* ─── 6. Vejiga y Espacio de Morrison ─── */
        [
            'id'     => 'vejiga_morrison',
            'titulo' => 'Vejiga y Espacio de Morrison',
            'icono'  => 'fa-solid fa-circle-dot',
            'campos' => [
                ['nombre' => 'paredes_descripcion',       'etiqueta' => 'De paredes',                                                     'tipo' => 'text',        'ancho' => 'medio'],
                ['nombre' => 'paredes_medida',            'etiqueta' => 'Medida de paredes',                                              'tipo' => 'number',      'ancho' => 'medio',  'unidad' => 'mm'],
                ['nombre' => 'bordes_sin_anormalidades',  'etiqueta' => 'Bordes regulares, sin anormalidades en pared ni imágenes patológicas', 'tipo' => 'radio_sinno', 'ancho' => 'completo'],
                ['nombre' => 'litiasis',                  'etiqueta' => 'Litiasis',                                                       'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'litiasis_tamano',           'etiqueta' => 'Tamaño Litiasis',                                               'tipo' => 'number',      'ancho' => 'medio',  'unidad' => 'mm',
                 'depende_de' => 'litiasis', 'depende_valor' => 'SI'],
                ['nombre' => 'sedimentos',                'etiqueta' => 'Sedimentos',                                                     'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'morrison_libre',            'etiqueta' => 'Espacio de Morrison Libre',                                      'tipo' => 'radio_sinno', 'ancho' => 'medio'],
            ],
        ],

        /* ─── 7. Digestivo ─── */
        [
            'id'     => 'digestivo',
            'titulo' => 'Digestivo',
            'icono'  => 'fa-solid fa-wave-square',
            'campos' => [
                ['nombre' => 'gas_forma',          'etiqueta' => 'Colon y asas intestinales — Artefacto por gas en forma', 'tipo' => 'text',        'ancho' => 'completo'],
                ['nombre' => 'imagenes_patologicas','etiqueta' => 'Evidencia de imágenes patológicas',                     'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'asas_intestinales',  'etiqueta' => 'Asas intestinales',                                     'tipo' => 'radio',
                 'opciones' => ['Normal', 'Levemente distendidas', 'Moderadamente distendidas', 'Severamente distendidas'], 'ancho' => 'completo'],
                ['nombre' => 'predominio',         'etiqueta' => 'Predominio',                                            'tipo' => 'radio',
                 'opciones' => ['Ascendente', 'Transverso', 'Descendente'],                                                'ancho' => 'completo'],
            ],
        ],

        /* ─── 8. Conclusión y Diagnóstico ─── */
        [
            'id'     => 'conclusion',
            'titulo' => 'Conclusión y Diagnóstico',
            'icono'  => 'fa-solid fa-file-medical',
            'campos' => [
                ['nombre' => 'conclusion',   'etiqueta' => 'Conclusión',   'tipo' => 'textarea', 'filas' => 5, 'ancho' => 'completo', 'requerido' => true],
                ['nombre' => 'diagnostico_1','etiqueta' => 'Diagnóstico 1','tipo' => 'textarea', 'filas' => 3, 'ancho' => 'completo'],
            ],
        ],
    ],
];

$json = json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

$descripcion = 'Estudio ecográfico completo de hígado, vías biliares, páncreas, aorta, bazo, vejiga y digestivo.';
$icono       = 'fa-solid fa-wave-square';

// Actualiza el registro existente (codigo = 'eco_abdominal' del schema.sql)
// y elimina el duplicado 'ECO-ABD-TOT' si fue creado por error
$sql = "UPDATE tipos_ecografias
        SET esquema_campos  = ?,
            esquema_version = esquema_version + 1,
            descripcion     = ?,
            icono           = ?
        WHERE codigo = 'eco_abdominal'";

$stmt = $conex->prepare($sql);
$stmt->bind_param('sss', $json, $descripcion, $icono);

echo '<style>body{font-family:monospace;background:#f6f8fa;padding:24px;}pre{background:#f0fff4;padding:20px;border-radius:8px;font-size:13px;}</style>';

if ($stmt->execute()) {
    $filas = $stmt->affected_rows;
    $stmt->close();

    // Limpia el duplicado 'ECO-ABD-TOT' si existe
    $del = $conex->query("DELETE FROM tipos_ecografias WHERE codigo = 'ECO-ABD-TOT'");

    echo '<pre>';
    if ($filas > 0) {
        echo '<strong style="color:#15803d;">✔ Ecografía Abdominal Total actualizada correctamente.</strong>' . "\n\n";
    } else {
        echo '<strong style="color:#b45309;">⚠ No se encontró el registro (codigo=eco_abdominal). Verifica que el tipo existe en la tabla.</strong>' . "\n\n";
    }
    echo 'Registros actualizados : ' . $filas . "\n";
    echo 'Secciones en esquema  : ' . count($schema['secciones']) . "\n\n";
    foreach ($schema['secciones'] as $s) {
        $c = count($s['campos'] ?? []);
        echo '  • ' . $s['titulo'] . ' — ' . $c . " campo(s)\n";
    }
    echo "\n<strong>Siguiente paso:</strong> Recarga el modal en el sistema y selecciona \"Ecografía Abdominal Total\".\n";
    echo '</pre>';
} else {
    echo '<p style="color:red;font-size:14px;"><strong>Error:</strong> ' . htmlspecialchars($conex->error) . '</p>';
    $stmt->close();
}

$conex->close();
