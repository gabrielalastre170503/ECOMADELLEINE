<?php
/**
 * Ejecutar UNA SOLA VEZ para actualizar el esquema de Ecografía de Codo
 * con la estructura LITERAL extraída del documento físico Dra. Madelleine Toro.
 * Acceder desde: http://localhost/Sistema_EcoMadelleineV1/database/seed_schema_codo.php
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
            'subtitulo' => 'Se practica estudio de codo con transductor lineal multifrecuencial, observándose lo siguiente:',
            'campos'    => [
                ['nombre' => 'nombres_apellidos', 'etiqueta' => 'Nombres y Apellidos', 'tipo' => 'text',     'ancho' => 'completo', 'requerido' => true],
                ['nombre' => 'edad',              'etiqueta' => 'Edad',                'tipo' => 'number',   'ancho' => 'tercio',   'unidad' => 'años', 'min' => 0, 'readonly' => true],
                ['nombre' => 'cedula',            'etiqueta' => 'Cédula (CI)',         'tipo' => 'text',     'ancho' => 'tercio',   'readonly' => true],
                ['nombre' => 'fecha',             'etiqueta' => 'Fecha',               'tipo' => 'date',     'ancho' => 'tercio',   'requerido' => true, 'readonly' => true],
                ['nombre' => 'lado_estudiado',    'etiqueta' => 'Codo estudiado',      'tipo' => 'radio',
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
                ['nombre' => 'cantidad', 'etiqueta' => 'Hipoecoico', 'tipo' => 'radio',
                 'opciones' => ['Escaso', 'Moderado'], 'ancho' => 'completo'],
            ],
        ],

        /* ─── 4. Receso Sinovial Anterior ─── */
        [
            'id'     => 'receso_sinovial_anterior',
            'titulo' => 'Receso Sinovial Anterior',
            'icono'  => 'fa-solid fa-droplet',
            'campos' => [
                ['nombre' => 'evidencia_inflamacion', 'etiqueta' => 'Con evidencia de inflamación', 'tipo' => 'radio_sinno', 'ancho' => 'completo'],
            ],
        ],

        /* ─── 5. Cartílago Hialino ─── */
        [
            'id'     => 'cartilago_hialino',
            'titulo' => 'Cartílago Hialino',
            'icono'  => 'fa-solid fa-circle-nodes',
            'campos' => [
                ['nombre' => 'ubicacion_anatomica_normal',     'etiqueta' => 'De ubicación anatómica normal',                'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'hipoecoico_tamano_normal',       'etiqueta' => 'Hipoecoico, de tamaño normal (<2 mm)',         'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'sin_alteraciones_estructurales', 'etiqueta' => 'Sin evidencias de alteraciones estructurales','tipo' => 'radio_sinno', 'ancho' => 'completo'],
            ],
        ],

        /* ─── 6. Tendón del Bíceps ─── */
        [
            'id'     => 'tendon_biceps',
            'titulo' => 'Tendón del Bíceps',
            'icono'  => 'fa-solid fa-dumbbell',
            'campos' => [
                ['nombre' => 'ubicacion_tamano_normal',        'etiqueta' => 'De ubicación anatómica normal, de tamaño normal',   'tipo' => 'radio_sinno', 'ancho' => 'completo'],
                ['nombre' => 'distribucion_lineal_uniforme',   'etiqueta' => 'Con distribución lineal ecogénico, de aspecto uniforme', 'tipo' => 'radio_sinno', 'ancho' => 'completo'],
                ['nombre' => 'sin_alteraciones_estructurales', 'etiqueta' => 'Sin evidencias de alteraciones estructurales',     'tipo' => 'radio_sinno', 'ancho' => 'completo'],
            ],
        ],

        /* ─── 7. Epicóndilo Lateral ─── */
        [
            'id'     => 'epicondilo_lateral',
            'titulo' => 'Epicóndilo Lateral',
            'icono'  => 'fa-solid fa-arrow-right',
            'campos' => [
                ['nombre' => 'ubicacion_ecogenico_continuo', 'etiqueta' => 'De ubicación anatómica normal, ecogénico continuo', 'tipo' => 'radio_sinno', 'ancho' => 'completo'],
                ['nombre' => 'signos_inflamacion',           'etiqueta' => 'Con signos de inflamación',                        'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'signos_cronicidad',            'etiqueta' => 'Con signos de cronicidad',                         'tipo' => 'radio_sinno', 'ancho' => 'medio'],
            ],
        ],

        /* ─── 8. Tendones Extensores Radiales Proximales ─── */
        [
            'id'     => 'tendones_extensores_radiales',
            'titulo' => 'Tendones Extensores Radiales Proximales',
            'icono'  => 'fa-solid fa-dna',
            'campos' => [
                ['nombre' => 'ubicacion_anatomica_normal',     'etiqueta' => 'De ubicación anatómica normal',                          'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'tamano_normal',                  'etiqueta' => 'De tamaño normal',                                       'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'distribucion_lineal_uniforme',   'etiqueta' => 'Con distribución lineal, ecogénico, de aspecto uniforme','tipo' => 'radio_sinno', 'ancho' => 'completo'],
                ['nombre' => 'sin_alteraciones_estructurales', 'etiqueta' => 'Sin evidencias de alteraciones estructurales',          'tipo' => 'radio_sinno', 'ancho' => 'completo'],
            ],
        ],

        /* ─── 9. Epicóndilo Medial ─── */
        [
            'id'     => 'epicondilo_medial',
            'titulo' => 'Epicóndilo Medial',
            'icono'  => 'fa-solid fa-arrow-left',
            'campos' => [
                ['nombre' => 'ubicacion_evidencia_inflamacion', 'etiqueta' => 'De ubicación anatómica normal, ecogénico continuo, con evidencia de inflamación', 'tipo' => 'radio_sinno', 'ancho' => 'completo'],
                ['nombre' => 'tipo_inflamacion', 'etiqueta' => 'Tipo de inflamación', 'tipo' => 'radio',
                 'opciones' => ['Difusas', 'Moderada'], 'ancho' => 'completo',
                 'depende_de' => 'ubicacion_evidencia_inflamacion', 'depende_valor' => 'SI'],
            ],
        ],

        /* ─── 10. Tendones Flexores Cubitales Proximales ─── */
        [
            'id'     => 'tendones_flexores_cubitales',
            'titulo' => 'Tendones Flexores Cubitales Proximales',
            'icono'  => 'fa-solid fa-dna',
            'campos' => [
                ['nombre' => 'ubicacion_anatomica_normal',       'etiqueta' => 'De ubicación anatómica normal',                          'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'tamano_distribucion_lineal',       'etiqueta' => 'De tamaño normal con distribución lineal',               'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'ecogenico_aspecto_uniforme',       'etiqueta' => 'Ecogénico, de aspecto uniforme',                         'tipo' => 'radio_sinno', 'ancho' => 'completo'],
                ['nombre' => 'sin_alteraciones_estructurales',   'etiqueta' => 'Sin evidencias de alteraciones estructurales',           'tipo' => 'radio_sinno', 'ancho' => 'completo'],
            ],
        ],

        /* ─── 11. Tendón del Tríceps ─── */
        [
            'id'     => 'tendon_triceps',
            'titulo' => 'Tendón del Tríceps',
            'icono'  => 'fa-solid fa-dna',
            'campos' => [
                ['nombre' => 'ubicacion_anatomica_normal',       'etiqueta' => 'De ubicación anatómica normal',                          'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'tamano_distribucion_lineal',       'etiqueta' => 'De tamaño normal con distribución lineal',               'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'ecogenico_aspecto_uniforme',       'etiqueta' => 'Ecogénico de aspecto uniforme',                          'tipo' => 'radio_sinno', 'ancho' => 'completo'],
                ['nombre' => 'sin_alteraciones_estructurales',   'etiqueta' => 'Sin evidencias de alteraciones estructurales',           'tipo' => 'radio_sinno', 'ancho' => 'completo'],
            ],
        ],

        /* ─── 12. Fosa Olecraneana ─── */
        [
            'id'     => 'fosa_olecraneana',
            'titulo' => 'Fosa Olecraneana',
            'icono'  => 'fa-solid fa-bone',
            'campos' => [
                ['nombre' => 'ubicacion_anatomica_normal',   'etiqueta' => 'De ubicación anatómica normal',           'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'perdida_estructura_anatomica', 'etiqueta' => 'Con pérdida de estructura anatómica',     'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'signos_inflamacion',           'etiqueta' => 'Con signos de inflamación',               'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'caracter_cronico',             'etiqueta' => 'De carácter crónico',                      'tipo' => 'radio_sinno', 'ancho' => 'medio'],
            ],
        ],

        /* ─── 13. Conclusión ─── */
        [
            'id'     => 'conclusion',
            'titulo' => 'Conclusión',
            'icono'  => 'fa-solid fa-file-medical',
            'campos' => [
                ['nombre' => 'conclusion', 'etiqueta' => 'Estudio Ecosonográfico de codo con signos sugestivos de:', 'tipo' => 'textarea', 'filas' => 6, 'ancho' => 'completo', 'requerido' => true],
            ],
        ],
    ],
];

$json        = json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
$descripcion = 'Informe ecosonográfico de codo: piel, subcutáneo, receso sinovial, cartílago, bíceps, epicóndilos, extensores, flexores, tríceps y fosa olecraneana.';
$icono       = 'fa-solid fa-bone';

$sql = "UPDATE tipos_ecografias
        SET esquema_campos  = ?,
            esquema_version = esquema_version + 1,
            descripcion     = ?,
            icono           = ?
        WHERE codigo = 'ECO_MUSCU_CODO'";

$stmt = $conex->prepare($sql);
$stmt->bind_param('sss', $json, $descripcion, $icono);

echo '<style>body{font-family:monospace;background:#f6f8fa;padding:24px;}pre{background:#f0fff4;padding:20px;border-radius:8px;font-size:13px;}</style>';

if ($stmt->execute()) {
    $filas = $stmt->affected_rows;
    $stmt->close();

    echo '<pre>';
    if ($filas > 0) {
        echo '<strong style="color:#15803d;">✔ Esquema de Codo actualizado (literal al documento físico).</strong>' . "\n\n";
    } else {
        echo '<strong style="color:#b45309;">⚠ No se encontró el registro (codigo=ECO_MUSCU_CODO).</strong>' . "\n\n";
    }
    echo 'Registros actualizados : ' . $filas . "\n";
    echo 'Secciones en esquema  : ' . count($schema['secciones']) . "\n\n";
    $total_campos = 0;
    foreach ($schema['secciones'] as $s) {
        $c = count($s['campos'] ?? []);
        $total_campos += $c;
        echo '  • ' . $s['titulo'] . ' — ' . $c . " campo(s)\n";
    }
    echo "\n<strong>Total de campos:</strong> " . $total_campos . "\n";
    echo "\n<strong>Siguiente paso:</strong> Recarga (Ctrl+Shift+R), abre Musculoesquelética → Codo.\n";
    echo '</pre>';
} else {
    echo '<p style="color:red;font-size:14px;"><strong>Error:</strong> ' . htmlspecialchars($conex->error) . '</p>';
    $stmt->close();
}

$conex->close();
