<?php
/**
 * Ejecutar UNA SOLA VEZ para actualizar el esquema de Ecografía de Hombro
 * con la estructura LITERAL extraída del documento físico Dra. Madelleine Toro.
 * Acceder desde: http://localhost/Sistema_EcoMadelleineV1/database/seed_schema_hombro.php
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
                ['nombre' => 'lado_estudiado',    'etiqueta' => 'Hombro estudiado',    'tipo' => 'radio',
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

        /* ─── 4. Músculo Deltoides ─── */
        [
            'id'     => 'deltoides',
            'titulo' => 'Músculo Deltoides',
            'icono'  => 'fa-solid fa-dumbbell',
            'campos' => [
                ['nombre' => 'ubicacion_anatomica_normal', 'etiqueta' => 'De ubicación anatómica normal',                          'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'patron_ecografico_normal',   'etiqueta' => 'Patrón ecográfico normal sin lesiones focales ni difusas','tipo' => 'radio_sinno', 'ancho' => 'medio'],
            ],
        ],

        /* ─── 5. Tendón Largo del Bíceps ─── */
        [
            'id'     => 'biceps',
            'titulo' => 'Tendón Largo del Bíceps',
            'icono'  => 'fa-solid fa-dna',
            'campos' => [
                ['nombre' => 'ubicacion_anatomica_normal', 'etiqueta' => 'De ubicación anatómica normal',                                 'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'tamano_normal',              'etiqueta' => 'De tamaño normal (5 mm)',                                       'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'visualiza_corredera',        'etiqueta' => 'Se visualiza en corredera bicipital',                           'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'corte_longitudinal_fibras',  'etiqueta' => 'En corte longitudinal se visualizan sus fibras',                'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'desgarro_total',             'etiqueta' => 'Compatible con desgarro total',                                  'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'tendinitis',                 'etiqueta' => 'Disminuido compatible con tendinitis',                          'tipo' => 'radio_sinno', 'ancho' => 'medio'],
            ],
        ],

        /* ─── 6. Tendón del Subescapular ─── */
        [
            'id'     => 'subescapular',
            'titulo' => 'Tendón del Subescapular',
            'icono'  => 'fa-solid fa-ring',
            'campos' => [
                ['nombre' => 'ubicacion_anatomica_normal', 'etiqueta' => 'De ubicación anatómica normal',                  'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'tamano_normal',              'etiqueta' => 'De tamaño normal (4 mm)',                        'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'distribucion_lineal',        'etiqueta' => 'Con distribución lineal, ecogénico',             'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'aspecto_uniforme',           'etiqueta' => 'De aspecto uniforme',                            'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'sin_alteraciones',           'etiqueta' => 'Sin evidencias de alteraciones estructurales',   'tipo' => 'radio_sinno', 'ancho' => 'completo'],
                ['nombre' => 'rango_movimiento',           'etiqueta' => 'Con rango de movimiento conservado',             'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'tendinitis',                 'etiqueta' => 'Disminuido compatible con tendinitis',           'tipo' => 'radio_sinno', 'ancho' => 'medio'],
            ],
        ],

        /* ─── 7. Tendón del Supraespinoso ─── */
        [
            'id'     => 'supraespinoso',
            'titulo' => 'Tendón del Supraespinoso',
            'icono'  => 'fa-solid fa-ring',
            'campos' => [
                ['nombre' => 'ubicacion_anatomica_normal', 'etiqueta' => 'De ubicación anatómica normal',                  'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'tamano_normal',              'etiqueta' => 'De tamaño normal (4 mm)',                        'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'distribucion_lineal',        'etiqueta' => 'Con distribución lineal, ecogénico',             'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'aspecto_uniforme',           'etiqueta' => 'De aspecto uniforme',                            'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'sin_alteraciones',           'etiqueta' => 'Sin evidencias de alteraciones estructurales',   'tipo' => 'radio_sinno', 'ancho' => 'completo'],
                ['nombre' => 'rango_movimiento',           'etiqueta' => 'Con rango de movimiento conservado',             'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'tendinitis',                 'etiqueta' => 'Disminuido compatible con tendinitis',           'tipo' => 'radio_sinno', 'ancho' => 'medio'],
            ],
        ],

        /* ─── 8. Tendón del Infraespinoso ─── */
        [
            'id'     => 'infraespinoso',
            'titulo' => 'Tendón del Infraespinoso',
            'icono'  => 'fa-solid fa-ring',
            'campos' => [
                ['nombre' => 'ubicacion_anatomica_normal', 'etiqueta' => 'De ubicación anatómica normal',                  'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'tamano_normal',              'etiqueta' => 'De tamaño normal (4 mm)',                        'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'distribucion_lineal',        'etiqueta' => 'Con distribución lineal, ecogénico',             'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'aspecto_uniforme',           'etiqueta' => 'De aspecto uniforme',                            'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'sin_alteraciones',           'etiqueta' => 'Sin evidencias de alteraciones estructurales',   'tipo' => 'radio_sinno', 'ancho' => 'completo'],
                ['nombre' => 'tendinitis',                 'etiqueta' => 'Disminuido compatible con tendinitis',           'tipo' => 'radio_sinno', 'ancho' => 'medio'],
            ],
        ],

        /* ─── 9. Bursa ─── */
        [
            'id'     => 'bursa',
            'titulo' => 'Bursa',
            'icono'  => 'fa-solid fa-circle',
            'campos' => [
                ['nombre' => 'distribucion_tamano_normal', 'etiqueta' => 'Mantienen su distribución y tamaño normal (<1 mm)', 'tipo' => 'radio_sinno', 'ancho' => 'completo'],
                ['nombre' => 'coleccion_liquida',          'etiqueta' => 'Evidencias de colección líquida',                  'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'bursitis',                   'etiqueta' => 'Compatible con bursitis',                          'tipo' => 'radio_sinno', 'ancho' => 'medio'],
            ],
        ],

        /* ─── 10. Superficie Ósea ─── */
        [
            'id'     => 'superficie_osea',
            'titulo' => 'Superficie Ósea',
            'icono'  => 'fa-solid fa-bone',
            'campos' => [
                ['nombre' => 'ecogenica_continua', 'etiqueta' => 'Ecogénica continua',  'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'sin_lesiones_focales','etiqueta' => 'Sin lesiones focales','tipo' => 'radio_sinno', 'ancho' => 'medio'],
            ],
        ],

        /* ─── 11. Acromion ─── */
        [
            'id'     => 'acromion',
            'titulo' => 'Acromion (Clasificación de Neer)',
            'icono'  => 'fa-solid fa-mountain',
            'campos' => [
                ['nombre' => 'tipo_neer', 'etiqueta' => 'Acromion tipo (según Neer)', 'tipo' => 'radio',
                 'opciones' => ['Tipo I', 'Tipo II', 'Tipo III'], 'ancho' => 'completo'],
            ],
        ],

        /* ─── 12. Conclusión ─── */
        [
            'id'     => 'conclusion',
            'titulo' => 'Conclusión',
            'icono'  => 'fa-solid fa-file-medical',
            'campos' => [
                ['nombre' => 'conclusion', 'etiqueta' => 'Estudio Ecosonográfico de Hombro — con signos sugestivos de:', 'tipo' => 'textarea', 'filas' => 6, 'ancho' => 'completo', 'requerido' => true],
            ],
        ],
    ],
];

$json        = json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
$descripcion = 'Informe ecosonográfico de hombro: piel, tejido subcutáneo, deltoides, bíceps, manguito rotador, bursa, ósea y acromion.';
$icono       = 'fa-solid fa-person';

$sql = "UPDATE tipos_ecografias
        SET esquema_campos  = ?,
            esquema_version = esquema_version + 1,
            descripcion     = ?,
            icono           = ?
        WHERE codigo = 'ECO_MUSCU_HOMBRO'";

$stmt = $conex->prepare($sql);
$stmt->bind_param('sss', $json, $descripcion, $icono);

echo '<style>body{font-family:monospace;background:#f6f8fa;padding:24px;}pre{background:#f0fff4;padding:20px;border-radius:8px;font-size:13px;}</style>';

if ($stmt->execute()) {
    $filas = $stmt->affected_rows;
    $stmt->close();

    echo '<pre>';
    if ($filas > 0) {
        echo '<strong style="color:#15803d;">✔ Esquema de Hombro actualizado (literal al documento físico).</strong>' . "\n\n";
    } else {
        echo '<strong style="color:#b45309;">⚠ No se encontró el registro (codigo=ECO_MUSCU_HOMBRO).</strong>' . "\n\n";
    }
    echo 'Registros actualizados : ' . $filas . "\n";
    echo 'Secciones en esquema  : ' . count($schema['secciones']) . "\n\n";
    $total_campos = 0;
    foreach ($schema['secciones'] as $s) {
        $c = count($s['campos'] ?? []);
        $total_campos += $c;
        echo '  • ' . $s['titulo'] . ' — ' . $c . " campo(s)\n";
    }
    echo "\n<strong>Total de campos:</strong> $total_campos\n";
    echo "\n<strong>Siguiente paso:</strong> Recarga (Ctrl+Shift+R), abre Musculoesquelética → Hombro.\n";
    echo '</pre>';
} else {
    echo '<p style="color:red;font-size:14px;"><strong>Error:</strong> ' . htmlspecialchars($conex->error) . '</p>';
    $stmt->close();
}

$conex->close();
