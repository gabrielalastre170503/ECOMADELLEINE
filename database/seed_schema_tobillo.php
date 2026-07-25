<?php
/**
 * Ejecutar UNA SOLA VEZ para actualizar el esquema de Ecografía de Tobillo
 * con la estructura LITERAL extraída del documento físico Dra. Madelleine Toro.
 * Acceder desde: http://localhost/Sistema_EcoMadelleineV1/database/seed_schema_tobillo.php
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
            'subtitulo' => 'Se practica estudio ecosonográfico con transductor lineal multifrecuencial, observándose lo siguiente:',
            'campos'    => [
                ['nombre' => 'nombres_apellidos', 'etiqueta' => 'Nombres y Apellidos', 'tipo' => 'text',     'ancho' => 'completo', 'requerido' => true],
                ['nombre' => 'edad',              'etiqueta' => 'Edad',                'tipo' => 'number',   'ancho' => 'tercio',   'unidad' => 'años', 'min' => 0, 'readonly' => true],
                ['nombre' => 'cedula',            'etiqueta' => 'Cédula (CI)',         'tipo' => 'text',     'ancho' => 'tercio',   'readonly' => true],
                ['nombre' => 'fecha',             'etiqueta' => 'Fecha',               'tipo' => 'date',     'ancho' => 'tercio',   'requerido' => true, 'readonly' => true],
                ['nombre' => 'lado_estudiado',    'etiqueta' => 'Tobillo estudiado',   'tipo' => 'radio',
                 'opciones' => ['Derecho', 'Izquierdo', 'Bilateral'], 'ancho' => 'completo'],
                ['nombre' => 'motivo_consulta',   'etiqueta' => 'Motivo de Consulta',  'tipo' => 'textarea', 'ancho' => 'completo', 'filas' => 2, 'requerido' => true],
            ],
        ],

        /* ─── 2. Piel ─── */
        [
            'id'     => 'piel',
            'titulo' => 'Piel',
            'icono'  => 'fa-solid fa-hand-dots',
            'campos' => [
                ['nombre' => 'piel_lineal_ecogenica', 'etiqueta' => 'Lineal ecogénica. Evidencia de lesiones', 'tipo' => 'radio_sinno', 'ancho' => 'completo'],
            ],
        ],

        /* ─── 3. Tejido Subcutáneo ─── */
        [
            'id'     => 'tejido_subcutaneo',
            'titulo' => 'Tejido Subcutáneo',
            'icono'  => 'fa-solid fa-layer-group',
            'campos' => [
                ['nombre' => 'cantidad', 'etiqueta' => 'Cantidad', 'tipo' => 'radio',
                 'opciones' => ['Escaso', 'Moderado'], 'ancho' => 'completo'],
                ['nombre' => 'distribucion_uniforme_hipoecoico', 'etiqueta' => 'Distribución uniforme hipoecoico',                              'tipo' => 'radio_sinno', 'ancho' => 'completo'],
                ['nombre' => 'sin_evidencias_anormales',         'etiqueta' => 'Sin evidencias de imágenes anormales locales o difusas',       'tipo' => 'radio_sinno', 'ancho' => 'completo'],
                ['nombre' => 'observaciones',                    'etiqueta' => 'Observaciones',                                                'tipo' => 'text',        'ancho' => 'completo'],
            ],
        ],

        /* ─── 4. Cápsula Articular Anterior ─── */
        [
            'id'     => 'capsula_articular_anterior',
            'titulo' => 'Cápsula Articular Anterior',
            'icono'  => 'fa-solid fa-droplet',
            'campos' => [
                ['nombre' => 'estado', 'etiqueta' => 'Estado', 'tipo' => 'radio',
                 'opciones' => ['Delgada', 'Engrosada'], 'ancho' => 'completo'],
                ['nombre' => 'sin_alteraciones_estructurales', 'etiqueta' => 'Sin evidencias de alteraciones estructurales', 'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'observaciones',                  'etiqueta' => 'Observaciones',                                'tipo' => 'text',        'ancho' => 'completo'],
            ],
        ],

        /* ─── 5. Tendón Tibial Anterior ─── */
        [
            'id'     => 'tendon_tibial_anterior',
            'titulo' => 'Tendón Tibial Anterior',
            'icono'  => 'fa-solid fa-dna',
            'campos' => [
                ['nombre' => 'ubicacion_anatomica_normal',     'etiqueta' => 'Ubicación anatómica normal',                  'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'tamano_normal',                  'etiqueta' => 'De tamaño normal (2.7 mm)',                   'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'distribucion_lineal',            'etiqueta' => 'Con distribución lineal',                     'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'ecogenico_aspecto_uniforme',     'etiqueta' => 'Ecogénico, de aspecto uniforme',              'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'sin_alteraciones_estructurales', 'etiqueta' => 'Sin evidencias de alteraciones estructurales','tipo' => 'radio_sinno', 'ancho' => 'completo'],
            ],
        ],

        /* ─── 6. Tendón Tibial Posterior ─── */
        [
            'id'     => 'tendon_tibial_posterior',
            'titulo' => 'Tendón Tibial Posterior',
            'icono'  => 'fa-solid fa-dna',
            'campos' => [
                ['nombre' => 'ubicacion_anatomica_normal',     'etiqueta' => 'Ubicación anatómica normal',                  'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'tamano_normal',                  'etiqueta' => 'De tamaño normal (3 mm)',                     'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'distribucion_lineal',            'etiqueta' => 'Con distribución lineal',                     'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'ecogenico_aspecto_uniforme',     'etiqueta' => 'Ecogénico, de aspecto uniforme',              'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'sin_alteraciones_estructurales', 'etiqueta' => 'Sin evidencias de alteraciones estructurales','tipo' => 'radio_sinno', 'ancho' => 'completo'],
            ],
        ],

        /* ─── 7. Tendón Extensor de los Dedos ─── */
        [
            'id'     => 'tendon_extensor_dedos',
            'titulo' => 'Tendón Extensor de los Dedos',
            'icono'  => 'fa-solid fa-dna',
            'campos' => [
                ['nombre' => 'ubicacion_anatomica_normal',     'etiqueta' => 'Ubicación anatómica normal',                  'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'tamano_normal',                  'etiqueta' => 'De tamaño normal (2.9 mm)',                   'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'distribucion_lineal',            'etiqueta' => 'Con distribución lineal',                     'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'ecogenico_aspecto_uniforme',     'etiqueta' => 'Ecogénico, de aspecto uniforme',              'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'sin_alteraciones_estructurales', 'etiqueta' => 'Sin evidencias de alteraciones estructurales','tipo' => 'radio_sinno', 'ancho' => 'completo'],
            ],
        ],

        /* ─── 8. Tendones Peroneos Largo y Corto ─── */
        [
            'id'     => 'tendones_peroneos',
            'titulo' => 'Tendones Peroneos Largo y Corto',
            'icono'  => 'fa-solid fa-dna',
            'campos' => [
                ['nombre' => 'ubicacion_anatomica_normal',     'etiqueta' => 'Ubicación anatómica normal',                  'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'tamano_normal',                  'etiqueta' => 'De tamaño normal (2.6 y 4 mm)',               'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'distribucion_lineal',            'etiqueta' => 'Con distribución lineal',                     'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'ecogenico_aspecto_uniforme',     'etiqueta' => 'Ecogénico, de aspecto uniforme',              'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'sin_alteraciones_estructurales', 'etiqueta' => 'Sin evidencias de alteraciones estructurales','tipo' => 'radio_sinno', 'ancho' => 'completo'],
            ],
        ],

        /* ─── 9. Tendón de Aquiles ─── */
        [
            'id'     => 'tendon_aquiles',
            'titulo' => 'Tendón de Aquiles',
            'icono'  => 'fa-solid fa-shoe-prints',
            'campos' => [
                ['nombre' => 'ubicacion_anatomica_normal',     'etiqueta' => 'Ubicación anatómica normal',                                                                'tipo' => 'radio_sinno', 'ancho' => 'completo'],
                ['nombre' => 'tamano_distribucion_ecogenico',  'etiqueta' => 'De tamaño normal (5.9 mm), con distribución lineal, ecogénico, de aspecto uniforme',        'tipo' => 'radio_sinno', 'ancho' => 'completo'],
                ['nombre' => 'sin_alteraciones_estructurales', 'etiqueta' => 'Sin evidencias de alteraciones estructurales',                                              'tipo' => 'radio_sinno', 'ancho' => 'completo'],
            ],
        ],

        /* ─── 10. Bolsa Retrocalcánea ─── */
        [
            'id'     => 'bolsa_retrocalcanea',
            'titulo' => 'Bolsa Retrocalcánea',
            'icono'  => 'fa-solid fa-circle',
            'campos' => [
                ['nombre' => 'ubicacion_anatomica_normal', 'etiqueta' => 'Ubicación anatómica normal', 'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'tamano_normal',              'etiqueta' => 'De tamaño normal (< 2 mm)',  'tipo' => 'radio_sinno', 'ancho' => 'medio'],
            ],
        ],

        /* ─── 11. Tendón del Flexor Largo del Hallux ─── */
        [
            'id'     => 'flexor_largo_hallux',
            'titulo' => 'Tendón del Flexor Largo del Hallux',
            'icono'  => 'fa-solid fa-dna',
            'campos' => [
                ['nombre' => 'ubicacion_anatomica_normal',     'etiqueta' => 'Ubicación anatómica normal',                  'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'tamano_normal',                  'etiqueta' => 'De tamaño normal (2.8 mm)',                   'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'distribucion_lineal',            'etiqueta' => 'Con distribución lineal',                     'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'ecogenico_aspecto_uniforme',     'etiqueta' => 'Ecogénico, de aspecto uniforme',              'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'sin_alteraciones_estructurales', 'etiqueta' => 'Sin evidencias de alteraciones estructurales','tipo' => 'radio_sinno', 'ancho' => 'completo'],
            ],
        ],

        /* ─── 12. Fascia Plantar ─── */
        [
            'id'     => 'fascia_plantar',
            'titulo' => 'Fascia Plantar',
            'icono'  => 'fa-solid fa-shoe-prints',
            'campos' => [
                ['nombre' => 'ubicacion_anatomica_normal', 'etiqueta' => 'Ubicación anatómica normal',                'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'ecogenica_aspecto_uniforme', 'etiqueta' => 'Ecogénica de aspecto uniforme',             'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'tamano_normal',              'etiqueta' => 'De tamaño normal (2 mm)',                   'tipo' => 'radio_sinno', 'ancho' => 'medio'],
            ],
        ],

        /* ─── 13. Conclusión ─── */
        [
            'id'     => 'conclusion',
            'titulo' => 'Conclusión',
            'icono'  => 'fa-solid fa-file-medical',
            'campos' => [
                ['nombre' => 'conclusion', 'etiqueta' => 'Estudio Ecosonográfico de Tobillo — con signos sugestivos de:', 'tipo' => 'textarea', 'filas' => 6, 'ancho' => 'completo', 'requerido' => true],
            ],
        ],
    ],
];

$json        = json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
$descripcion = 'Informe ecosonográfico de tobillo: piel, subcutáneo, cápsula, tendones tibiales, peroneos, Aquiles, bolsa retrocalcánea, flexor del hallux y fascia plantar.';
$icono       = 'fa-solid fa-shoe-prints';

$sql = "UPDATE tipos_ecografias
        SET esquema_campos  = ?,
            esquema_version = esquema_version + 1,
            descripcion     = ?,
            icono           = ?
        WHERE codigo = 'ECO_MUSCU_TOBILLO'";

$stmt = $conex->prepare($sql);
$stmt->bind_param('sss', $json, $descripcion, $icono);

echo '<style>body{font-family:monospace;background:#f6f8fa;padding:24px;}pre{background:#f0fff4;padding:20px;border-radius:8px;font-size:13px;}</style>';

if ($stmt->execute()) {
    $filas = $stmt->affected_rows;
    $stmt->close();

    echo '<pre>';
    if ($filas > 0) {
        echo '<strong style="color:#15803d;">✔ Esquema de Tobillo actualizado (literal al documento físico).</strong>' . "\n\n";
    } else {
        echo '<strong style="color:#b45309;">⚠ No se encontró el registro (codigo=ECO_MUSCU_TOBILLO).</strong>' . "\n\n";
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
    echo "\n<strong>Siguiente paso:</strong> Recarga (Ctrl+Shift+R), abre Musculoesquelética → Tobillo.\n";
    echo '</pre>';
} else {
    echo '<p style="color:red;font-size:14px;"><strong>Error:</strong> ' . htmlspecialchars($conex->error) . '</p>';
    $stmt->close();
}

$conex->close();
