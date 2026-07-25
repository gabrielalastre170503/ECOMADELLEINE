<?php
/**
 * Ejecutar UNA SOLA VEZ para actualizar el esquema de Ecografía de Cadera
 * con la estructura LITERAL extraída del documento físico Dra. Madelleine Toro.
 * Acceder desde: http://localhost/Sistema_EcoMadelleineV1/database/seed_schema_cadera.php
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
            'subtitulo' => 'Se practica estudio de cadera con transductor lineal multifrecuencial, observándose lo siguiente:',
            'campos'    => [
                ['nombre' => 'nombres_apellidos', 'etiqueta' => 'Nombres y Apellidos',  'tipo' => 'text',     'ancho' => 'completo', 'requerido' => true],
                ['nombre' => 'edad',              'etiqueta' => 'Edad',                 'tipo' => 'number',   'ancho' => 'tercio',   'unidad' => 'años', 'min' => 0, 'readonly' => true],
                ['nombre' => 'cedula',            'etiqueta' => 'Cédula (CI)',          'tipo' => 'text',     'ancho' => 'tercio',   'readonly' => true],
                ['nombre' => 'fecha',             'etiqueta' => 'Fecha',                'tipo' => 'date',     'ancho' => 'tercio',   'requerido' => true, 'readonly' => true],
                ['nombre' => 'lado_estudiado',    'etiqueta' => 'Cadera estudiada',     'tipo' => 'radio',
                 'opciones' => ['Derecha', 'Izquierda', 'Bilateral'], 'ancho' => 'completo'],
                ['nombre' => 'motivo_consulta',   'etiqueta' => 'Motivo de Consulta',   'tipo' => 'textarea', 'ancho' => 'completo', 'filas' => 2, 'requerido' => true],
            ],
        ],

        /* ─── 2. Piel ─── */
        [
            'id'     => 'piel',
            'titulo' => 'Piel',
            'icono'  => 'fa-solid fa-hand-dots',
            'campos' => [
                ['nombre' => 'distribucion_uniforme_delgada', 'etiqueta' => 'Con distribución uniforme y delgada',           'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'sin_evidencia_lesiones',        'etiqueta' => 'Sin evidencia de lesiones focales ni difusas', 'tipo' => 'radio_sinno', 'ancho' => 'medio'],
            ],
        ],

        /* ─── 3. Tejido Subcutáneo ─── */
        [
            'id'     => 'tejido_subcutaneo',
            'titulo' => 'Tejido Subcutáneo',
            'icono'  => 'fa-solid fa-layer-group',
            'campos' => [
                ['nombre' => 'cantidad', 'etiqueta' => 'Hipoecoico', 'tipo' => 'radio',
                 'opciones' => ['Escaso', 'Moderado', 'Abundante'], 'ancho' => 'completo'],
                ['nombre' => 'sin_evidencia_lesiones', 'etiqueta' => 'Sin evidencia de lesiones focales ni difusas', 'tipo' => 'radio_sinno', 'ancho' => 'completo'],
            ],
        ],

        /* ─── 4. Cápsula Sinovial ─── */
        [
            'id'     => 'capsula_sinovial',
            'titulo' => 'Cápsula Sinovial',
            'icono'  => 'fa-solid fa-droplet',
            'campos' => [
                ['nombre' => 'delgada_mm',                       'etiqueta' => 'Delgada',                                                     'tipo' => 'number',      'ancho' => 'tercio', 'unidad' => 'mm'],
                ['nombre' => 'engrosada_mm',                     'etiqueta' => 'Engrosada',                                                   'tipo' => 'number',      'ancho' => 'tercio', 'unidad' => 'mm'],
                ['nombre' => 'hipoecoica_sin_inflamacion',       'etiqueta' => 'Hipoecoica, sin signos de inflamación',                       'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'no_visualiza_derrame_articular',   'etiqueta' => 'No se visualiza derrame articular',                           'tipo' => 'radio_sinno', 'ancho' => 'medio'],
            ],
        ],

        /* ─── 5. Superficie del Fémur ─── */
        [
            'id'     => 'superficie_femur',
            'titulo' => 'Superficie del Fémur',
            'icono'  => 'fa-solid fa-bone',
            'campos' => [
                ['nombre' => 'lineal_ecogenica_sin_lesiones', 'etiqueta' => 'Lineal ecogénica sin lesiones focales ni difusas', 'tipo' => 'radio_sinno', 'ancho' => 'completo'],
            ],
        ],

        /* ─── 6. Bursa del Trocánter Mayor ─── */
        [
            'id'     => 'bursa_trocanter',
            'titulo' => 'Bursa del Trocánter Mayor',
            'icono'  => 'fa-solid fa-circle',
            'campos' => [
                ['nombre' => 'delgada_mm',          'etiqueta' => 'Delgada',                       'tipo' => 'number',      'ancho' => 'tercio', 'unidad' => 'mm'],
                ['nombre' => 'engrosada_mm',        'etiqueta' => 'Engrosada',                     'tipo' => 'number',      'ancho' => 'tercio', 'unidad' => 'mm'],
                ['nombre' => 'hipoecoica',          'etiqueta' => 'Hipoecoica',                    'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'sin_signos_inflamacion','etiqueta' => 'Sin signos de inflamación',   'tipo' => 'radio_sinno', 'ancho' => 'medio'],
            ],
        ],

        /* ─── 7. Bursa del Iliopsoas ─── */
        [
            'id'     => 'bursa_iliopsoas',
            'titulo' => 'Bursa del Iliopsoas',
            'icono'  => 'fa-solid fa-circle',
            'campos' => [
                ['nombre' => 'delgada_mm',          'etiqueta' => 'Delgada',                       'tipo' => 'number',      'ancho' => 'tercio', 'unidad' => 'mm'],
                ['nombre' => 'engrosada_mm',        'etiqueta' => 'Engrosada',                     'tipo' => 'number',      'ancho' => 'tercio', 'unidad' => 'mm'],
                ['nombre' => 'hipoecoica',          'etiqueta' => 'Hipoecoica',                    'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'sin_signos_inflamacion','etiqueta' => 'Sin signos de inflamación',   'tipo' => 'radio_sinno', 'ancho' => 'medio'],
            ],
        ],

        /* ─── 8. Origen Conjunto de Músculos Isquiotibiales ─── */
        [
            'id'     => 'isquiotibiales',
            'titulo' => 'Origen Conjunto de Músculos Isquiotibiales',
            'icono'  => 'fa-solid fa-dna',
            'campos' => [
                ['nombre' => 'aspecto_ecografico_normal', 'etiqueta' => 'Con aspecto ecográfico normal', 'tipo' => 'radio_sinno', 'ancho' => 'completo'],
            ],
        ],

        /* ─── 9. Nota — Estudio Comparativo ─── */
        [
            'id'     => 'estudio_comparativo',
            'titulo' => 'Nota — Estudio Comparativo',
            'icono'  => 'fa-solid fa-circle-info',
            'campos' => [
                ['nombre' => 'articulacion_comparada', 'etiqueta' => 'Articulación de cadera comparada', 'tipo' => 'text', 'ancho' => 'completo'],
                ['nombre' => 'caracteristicas_ecograficas', 'etiqueta' => 'Presentando características ecográficas', 'tipo' => 'radio',
                 'opciones' => ['Similares', 'No similares'], 'ancho' => 'completo'],
            ],
        ],

        /* ─── 10. Conclusión ─── */
        [
            'id'     => 'conclusion',
            'titulo' => 'Conclusión',
            'icono'  => 'fa-solid fa-file-medical',
            'campos' => [
                ['nombre' => 'conclusion', 'etiqueta' => 'Estudio Ecosonográfico de Cadera — con signos sugestivos de:', 'tipo' => 'textarea', 'filas' => 6, 'ancho' => 'completo', 'requerido' => true],
            ],
        ],
    ],
];

$json        = json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
$descripcion = 'Informe ecosonográfico de cadera: piel, subcutáneo, cápsula sinovial, fémur, bursas y músculos isquiotibiales.';
$icono       = 'fa-solid fa-person-walking';

$sql = "UPDATE tipos_ecografias
        SET esquema_campos  = ?,
            esquema_version = esquema_version + 1,
            descripcion     = ?,
            icono           = ?
        WHERE codigo = 'ECO_MUSCU_CADERA'";

$stmt = $conex->prepare($sql);
$stmt->bind_param('sss', $json, $descripcion, $icono);

echo '<style>body{font-family:monospace;background:#f6f8fa;padding:24px;}pre{background:#f0fff4;padding:20px;border-radius:8px;font-size:13px;}</style>';

if ($stmt->execute()) {
    $filas = $stmt->affected_rows;
    $stmt->close();

    echo '<pre>';
    if ($filas > 0) {
        echo '<strong style="color:#15803d;">✔ Esquema de Cadera actualizado (literal al documento físico).</strong>' . "\n\n";
    } else {
        echo '<strong style="color:#b45309;">⚠ No se encontró el registro (codigo=ECO_MUSCU_CADERA).</strong>' . "\n\n";
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
    echo "\n<strong>Siguiente paso:</strong> Recarga (Ctrl+Shift+R), abre Musculoesquelética → Cadera.\n";
    echo '</pre>';
} else {
    echo '<p style="color:red;font-size:14px;"><strong>Error:</strong> ' . htmlspecialchars($conex->error) . '</p>';
    $stmt->close();
}

$conex->close();
