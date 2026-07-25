<?php
/**
 * Ejecutar UNA SOLA VEZ para actualizar el esquema de Ecografía Prostática
 * con la estructura LITERAL extraída del documento físico Dra. Madelleine Toro.
 * Acceder desde: http://localhost/Sistema_EcoMadelleineV1/database/seed_schema_prostata.php
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

        /* ─── 2. Vejiga — Diámetros Pre-Micción ─── */
        [
            'id'     => 'vejiga_pre',
            'titulo' => 'Vejiga — Diámetros Pre-Micción',
            'icono'  => 'fa-solid fa-circle-dot',
            'campos' => [
                ['nombre' => 'pre_l',       'etiqueta' => 'L',       'tipo' => 'number', 'ancho' => 'tercio', 'unidad' => 'mm'],
                ['nombre' => 'pre_ap',      'etiqueta' => 'AP',      'tipo' => 'number', 'ancho' => 'tercio', 'unidad' => 'mm'],
                ['nombre' => 'pre_t',       'etiqueta' => 'T',       'tipo' => 'number', 'ancho' => 'tercio', 'unidad' => 'mm'],
                ['nombre' => 'pre_volumen', 'etiqueta' => 'Volumen', 'tipo' => 'number', 'ancho' => 'medio',  'unidad' => 'ml'],
            ],
        ],

        /* ─── 3. Vejiga — Diámetros Post-Micción ─── */
        [
            'id'     => 'vejiga_post',
            'titulo' => 'Vejiga — Diámetros Post-Micción',
            'icono'  => 'fa-solid fa-circle-dot',
            'campos' => [
                ['nombre' => 'post_l',       'etiqueta' => 'L',       'tipo' => 'number', 'ancho' => 'tercio', 'unidad' => 'mm'],
                ['nombre' => 'post_ap',      'etiqueta' => 'AP',      'tipo' => 'number', 'ancho' => 'tercio', 'unidad' => 'mm'],
                ['nombre' => 'post_t',       'etiqueta' => 'T',       'tipo' => 'number', 'ancho' => 'tercio', 'unidad' => 'mm'],
                ['nombre' => 'post_volumen', 'etiqueta' => 'Volumen', 'tipo' => 'number', 'ancho' => 'medio',  'unidad' => 'ml'],
            ],
        ],

        /* ─── 4. Vejiga — Características ─── */
        [
            'id'     => 'vejiga_caracteristicas',
            'titulo' => 'Vejiga — Paredes y Hallazgos',
            'icono'  => 'fa-solid fa-droplet',
            'campos' => [
                ['nombre' => 'paredes_descripcion',      'etiqueta' => 'De paredes',                                                                                  'tipo' => 'text',        'ancho' => 'medio'],
                ['nombre' => 'paredes_medida',           'etiqueta' => 'Medida',                                                                                      'tipo' => 'number',      'ancho' => 'medio',  'unidad' => 'mm'],
                ['nombre' => 'bordes_sin_anormalidades', 'etiqueta' => 'Bordes regulares, sin anormalidades en su pared y sin imágenes patológicas en su interior',   'tipo' => 'radio_sinno', 'ancho' => 'completo'],
                ['nombre' => 'litiasis',                 'etiqueta' => 'Litiasis',                                                                                    'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'litiasis_mm',              'etiqueta' => 'Litiasis — Medida',                                                                           'tipo' => 'number',      'ancho' => 'medio',  'unidad' => 'mm',
                 'depende_de' => 'litiasis', 'depende_valor' => 'SI'],
                ['nombre' => 'sedimentos',               'etiqueta' => 'Sedimentos',                                                                                  'tipo' => 'radio_sinno', 'ancho' => 'medio'],
            ],
        ],

        /* ─── 5. Vesículas Seminales ─── */
        [
            'id'     => 'vesiculas_seminales',
            'titulo' => 'Vesículas Seminales',
            'icono'  => 'fa-solid fa-circle-nodes',
            'campos' => [
                ['nombre' => 'visibles_posicion_habitual',          'etiqueta' => 'Visibles en su posición habitual',                                  'tipo' => 'radio_sinno', 'ancho' => 'completo'],
                ['nombre' => 'distendidas_forma_tamano_conservada', 'etiqueta' => 'Distendidas, de forma, tamaño y ecogenicidad conservada',           'tipo' => 'radio_sinno', 'ancho' => 'completo'],
                ['nombre' => 'sin_alteraciones_ecograficas',        'etiqueta' => 'Sin alteraciones ecográficas aparentes',                            'tipo' => 'radio_sinno', 'ancho' => 'completo'],
            ],
        ],

        /* ─── 6. Próstata — Características ─── */
        [
            'id'     => 'prostata_caracteristicas',
            'titulo' => 'Próstata — Forma y Ecogenicidad',
            'icono'  => 'fa-solid fa-circle',
            'campos' => [
                ['nombre' => 'forma_tamano_ecogenicidad_normal', 'etiqueta' => 'De forma, tamaño y ecogenicidad normal',          'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'aumentado',                        'etiqueta' => 'Aumentado',                                       'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'parenquima_homogeneo',             'etiqueta' => 'Parénquima homogéneo',                            'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'parenquima_heterogeneo',           'etiqueta' => 'Heterogéneo',                                     'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'sin_imagenes_tu',                  'etiqueta' => 'Sin imágenes sugestivas de TU sólido o quístico', 'tipo' => 'radio_sinno', 'ancho' => 'completo'],
            ],
        ],

        /* ─── 7. Próstata — Diámetros ─── */
        [
            'id'     => 'prostata_diametros',
            'titulo' => 'Próstata — Diámetros',
            'icono'  => 'fa-solid fa-ruler',
            'campos' => [
                ['nombre' => 'l',       'etiqueta' => 'L',       'tipo' => 'number', 'ancho' => 'tercio', 'unidad' => 'mm'],
                ['nombre' => 'ap',      'etiqueta' => 'AP',      'tipo' => 'number', 'ancho' => 'tercio', 'unidad' => 'mm'],
                ['nombre' => 't',       'etiqueta' => 'T',       'tipo' => 'number', 'ancho' => 'tercio', 'unidad' => 'mm'],
                ['nombre' => 'volumen', 'etiqueta' => 'Volumen', 'tipo' => 'number', 'ancho' => 'medio',  'unidad' => 'ml'],
            ],
        ],

        /* ─── 8. Conclusión y Diagnóstico ─── */
        [
            'id'     => 'conclusion',
            'titulo' => 'Conclusión y Diagnóstico',
            'icono'  => 'fa-solid fa-file-medical',
            'campos' => [
                ['nombre' => 'conclusion',     'etiqueta' => 'Conclusión',     'tipo' => 'textarea', 'filas' => 5, 'ancho' => 'completo', 'requerido' => true],
                ['nombre' => 'diagnostico_1',  'etiqueta' => 'Diagnóstico 1',  'tipo' => 'textarea', 'filas' => 3, 'ancho' => 'completo'],
            ],
        ],
    ],
];

$json        = json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
$descripcion = 'Reporte ecográfico prostático: vejiga (pre/post micción), vesículas seminales y próstata.';
$icono       = 'fa-solid fa-mars';

$sql = "UPDATE tipos_ecografias
        SET esquema_campos  = ?,
            esquema_version = esquema_version + 1,
            descripcion     = ?,
            icono           = ?
        WHERE codigo = 'ECO_PROST'";

$stmt = $conex->prepare($sql);
$stmt->bind_param('sss', $json, $descripcion, $icono);

echo '<style>body{font-family:monospace;background:#f6f8fa;padding:24px;}pre{background:#f0fff4;padding:20px;border-radius:8px;font-size:13px;}</style>';

if ($stmt->execute()) {
    $filas = $stmt->affected_rows;
    $stmt->close();

    echo '<pre>';
    if ($filas > 0) {
        echo '<strong style="color:#15803d;">✔ Esquema de Ecografía Prostática actualizado (literal al documento físico).</strong>' . "\n\n";
    } else {
        echo '<strong style="color:#b45309;">⚠ No se encontró el registro (codigo=ECO_PROST).</strong>' . "\n\n";
    }
    echo 'Registros actualizados : ' . $filas . "\n";
    echo 'Secciones en esquema  : ' . count($schema['secciones']) . "\n\n";
    $total = 0;
    foreach ($schema['secciones'] as $s) {
        $c = count($s['campos'] ?? []);
        $total += $c;
        echo '  • ' . $s['titulo'] . ' — ' . $c . " campo(s)\n";
    }
    echo "\n<strong>Total de campos:</strong> " . $total . "\n";
    echo "\n<strong>Siguiente paso:</strong> Recarga (Ctrl+Shift+R), abre la modal de Ecografía Prostática.\n";
    echo '</pre>';
} else {
    echo '<p style="color:red;font-size:14px;"><strong>Error:</strong> ' . htmlspecialchars($conex->error) . '</p>';
    $stmt->close();
}

$conex->close();
