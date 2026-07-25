<?php
/**
 * Ejecutar UNA SOLA VEZ para actualizar el esquema de Ecografía de Rodilla
 * con la estructura LITERAL extraída del documento físico Dra. Madelleine Toro.
 * Acceder desde: http://localhost/Sistema_EcoMadelleineV1/database/seed_schema_rodilla.php
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
            'subtitulo' => 'Se practica estudio con transductor lineal multifrecuencial, observándose lo siguiente:',
            'campos'    => [
                ['nombre' => 'nombres_apellidos', 'etiqueta' => 'Nombres y Apellidos', 'tipo' => 'text',     'ancho' => 'completo', 'requerido' => true],
                ['nombre' => 'edad',              'etiqueta' => 'Edad',                'tipo' => 'number',   'ancho' => 'tercio',   'unidad' => 'años', 'min' => 0, 'readonly' => true],
                ['nombre' => 'cedula',            'etiqueta' => 'Cédula (CI)',         'tipo' => 'text',     'ancho' => 'tercio',   'readonly' => true],
                ['nombre' => 'fecha',             'etiqueta' => 'Fecha',               'tipo' => 'date',     'ancho' => 'tercio',   'requerido' => true, 'readonly' => true],
                ['nombre' => 'lado_estudiado',    'etiqueta' => 'Rodilla estudiada',   'tipo' => 'radio',
                 'opciones' => ['Derecha', 'Izquierda', 'Bilateral'], 'ancho' => 'completo'],
                ['nombre' => 'motivo_consulta',   'etiqueta' => 'Motivo de Consulta',  'tipo' => 'textarea', 'ancho' => 'completo', 'filas' => 2, 'requerido' => true],
            ],
        ],

        /* ─── 2. Piel ─── */
        [
            'id'     => 'piel',
            'titulo' => 'Piel',
            'icono'  => 'fa-solid fa-hand-dots',
            'campos' => [
                ['nombre' => 'distribucion_uniforme_delgada', 'etiqueta' => 'Con distribución uniforme y delgada',           'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'sin_evidencia_lesiones',        'etiqueta' => 'Sin evidencia de lesiones focales ni difusas','tipo' => 'radio_sinno', 'ancho' => 'medio'],
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

        /* ═══════════ COMPARTIMENTO ANTERIOR ═══════════ */

        /* ─── 4. Tendón del Cuádriceps ─── */
        [
            'id'     => 'tendon_cuadriceps',
            'titulo' => 'Compartimento Anterior — Tendón del Cuádriceps',
            'icono'  => 'fa-solid fa-arrow-up',
            'campos' => [
                ['nombre' => 'ubicacion_anatomica_normal',     'etiqueta' => 'De ubicación anatómica normal',                                       'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'tamano_normal',                  'etiqueta' => 'De tamaño normal',                                                     'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'tamano_mm',                      'etiqueta' => 'Mide',                                                                 'tipo' => 'number',      'ancho' => 'tercio', 'unidad' => 'mm'],
                ['nombre' => 'distribucion_lineal_ecogenico',  'etiqueta' => 'Con distribución lineal, ecogénico',                                   'tipo' => 'radio_sinno', 'ancho' => 'completo'],
                ['nombre' => 'aspecto_uniforme_sin_alteraciones','etiqueta' => 'De aspecto uniforme, sin evidencias de alteraciones estructurales','tipo' => 'radio_sinno', 'ancho' => 'completo'],
            ],
        ],

        /* ─── 5. Bursa Suprapatelar ─── */
        [
            'id'     => 'bursa_suprapatelar',
            'titulo' => 'Compartimento Anterior — Bursa Suprapatelar',
            'icono'  => 'fa-solid fa-circle',
            'campos' => [
                ['nombre' => 'ubicacion_anatomica_normal', 'etiqueta' => 'De ubicación anatómica normal',        'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'tamano_normal',              'etiqueta' => 'De tamaño normal (<1 mm)',             'tipo' => 'radio_sinno', 'ancho' => 'medio'],
            ],
        ],

        /* ─── 6. Tendón Rotuliano ─── */
        [
            'id'     => 'tendon_rotuliano',
            'titulo' => 'Compartimento Anterior — Tendón Rotuliano',
            'icono'  => 'fa-solid fa-dna',
            'campos' => [
                ['nombre' => 'ubicacion_anatomica_normal',     'etiqueta' => 'De ubicación anatómica normal',                                       'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'tamano_normal',                  'etiqueta' => 'De tamaño normal',                                                     'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'tamano_mm',                      'etiqueta' => 'Mide',                                                                 'tipo' => 'number',      'ancho' => 'tercio', 'unidad' => 'mm'],
                ['nombre' => 'distribucion_lineal_ecogenico',  'etiqueta' => 'Con distribución lineal, ecogénico',                                   'tipo' => 'radio_sinno', 'ancho' => 'completo'],
                ['nombre' => 'aspecto_uniforme_sin_alteraciones','etiqueta' => 'De aspecto uniforme, sin evidencias de alteraciones estructurales','tipo' => 'radio_sinno', 'ancho' => 'completo'],
            ],
        ],

        /* ─── 7. Bursa Infrapatelar ─── */
        [
            'id'     => 'bursa_infrapatelar',
            'titulo' => 'Compartimento Anterior — Bursa Infrapatelar',
            'icono'  => 'fa-solid fa-circle',
            'campos' => [
                ['nombre' => 'ubicacion_anatomica_normal', 'etiqueta' => 'De ubicación anatómica normal',        'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'tamano_normal',              'etiqueta' => 'De tamaño normal (<1 mm)',             'tipo' => 'radio_sinno', 'ancho' => 'medio'],
            ],
        ],

        /* ─── 8. Cartílago Articular Femoral ─── */
        [
            'id'     => 'cartilago_femoral',
            'titulo' => 'Compartimento Anterior — Cartílago Articular Femoral',
            'icono'  => 'fa-solid fa-circle-nodes',
            'campos' => [
                ['nombre' => 'ubicacion_anatomica_normal', 'etiqueta' => 'Ubicación anatómica normal',          'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'hipoecoico_tamano_normal',   'etiqueta' => 'Hipoecoico, de tamaño normal',        'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'tamano_mm',                  'etiqueta' => 'Mide',                                 'tipo' => 'number',      'ancho' => 'tercio', 'unidad' => 'mm'],
            ],
        ],

        /* ─── 9. Grasa de Hoffa ─── */
        [
            'id'     => 'grasa_hoffa',
            'titulo' => 'Compartimento Anterior — Grasa de Hoffa',
            'icono'  => 'fa-solid fa-droplet',
            'campos' => [
                ['nombre' => 'hipoecoica',             'etiqueta' => 'Hipoecoica',                          'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'sin_lesiones_focales',   'etiqueta' => 'Sin lesiones focales ni difusas',     'tipo' => 'radio_sinno', 'ancho' => 'medio'],
            ],
        ],

        /* ═══════════ COMPARTIMENTO MEDIAL ═══════════ */

        /* ─── 10. Ligamento Colateral Interno ─── */
        [
            'id'     => 'ligamento_colateral_interno',
            'titulo' => 'Compartimento Medial — Ligamento Colateral Interno',
            'icono'  => 'fa-solid fa-arrow-left',
            'campos' => [
                ['nombre' => 'ubicacion_anatomica_normal',     'etiqueta' => 'Ubicación anatómica normal',                                'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'tamano_normal',                  'etiqueta' => 'De tamaño normal',                                          'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'tamano_mm',                      'etiqueta' => 'Mide',                                                       'tipo' => 'number',      'ancho' => 'tercio', 'unidad' => 'mm'],
                ['nombre' => 'distribucion_lineal',            'etiqueta' => 'Con distribución lineal',                                   'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'patron_ecografico_normal',       'etiqueta' => 'Patrón ecográfico normal, sin lesiones focales ni difusas','tipo' => 'radio_sinno', 'ancho' => 'completo'],
            ],
        ],

        /* ─── 11. Cuerno Anterior del Menisco Interno ─── */
        [
            'id'     => 'menisco_interno',
            'titulo' => 'Compartimento Medial — Cuerno Anterior del Menisco Interno',
            'icono'  => 'fa-solid fa-moon',
            'campos' => [
                ['nombre' => 'ecogenico_sin_lesiones', 'etiqueta' => 'Ecogénico, sin lesiones focales ni difusas', 'tipo' => 'radio_sinno', 'ancho' => 'completo'],
            ],
        ],

        /* ═══════════ COMPARTIMENTO LATERAL ═══════════ */

        /* ─── 12. Ligamento Colateral Externo ─── */
        [
            'id'     => 'ligamento_colateral_externo',
            'titulo' => 'Compartimento Lateral — Ligamento Colateral Externo',
            'icono'  => 'fa-solid fa-arrow-right',
            'campos' => [
                ['nombre' => 'ubicacion_anatomica_normal',     'etiqueta' => 'De ubicación anatómica normal',                             'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'tamano_normal',                  'etiqueta' => 'De tamaño normal',                                          'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'tamano_mm',                      'etiqueta' => 'Mide',                                                       'tipo' => 'number',      'ancho' => 'tercio', 'unidad' => 'mm'],
                ['nombre' => 'distribucion_lineal',            'etiqueta' => 'Con distribución lineal',                                   'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'patron_ecografico_normal',       'etiqueta' => 'Patrón ecográfico normal, sin lesiones focales ni difusas','tipo' => 'radio_sinno', 'ancho' => 'completo'],
            ],
        ],

        /* ─── 13. Cuerno Anterior del Menisco Externo ─── */
        [
            'id'     => 'menisco_externo',
            'titulo' => 'Compartimento Lateral — Cuerno Anterior del Menisco Externo',
            'icono'  => 'fa-solid fa-moon',
            'campos' => [
                ['nombre' => 'ecogenico',              'etiqueta' => 'Ecogénico',                          'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'sin_lesiones_focales',   'etiqueta' => 'Sin lesiones focales ni difusas',    'tipo' => 'radio_sinno', 'ancho' => 'medio'],
            ],
        ],

        /* ─── 14. Tendón del Bíceps Femoral ─── */
        [
            'id'     => 'tendon_biceps_femoral',
            'titulo' => 'Compartimento Lateral — Tendón del Bíceps Femoral',
            'icono'  => 'fa-solid fa-dumbbell',
            'campos' => [
                ['nombre' => 'ubicacion_anatomica_normal',     'etiqueta' => 'De ubicación anatómica normal',                                       'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'tamano_normal',                  'etiqueta' => 'De tamaño normal',                                                     'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'tamano_mm',                      'etiqueta' => 'Mide',                                                                 'tipo' => 'number',      'ancho' => 'tercio', 'unidad' => 'mm'],
                ['nombre' => 'distribucion_lineal',            'etiqueta' => 'Con distribución lineal',                                              'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'ecogenico_uniforme_sin_alteraciones', 'etiqueta' => 'Ecogénico, de aspecto uniforme, sin evidencias de alteraciones estructurales', 'tipo' => 'radio_sinno', 'ancho' => 'completo'],
            ],
        ],

        /* ═══════════ COMPARTIMENTO POSTERIOR ═══════════ */

        /* ─── 15. Cuernos Posteriores de Meniscos ─── */
        [
            'id'     => 'cuernos_posteriores_meniscos',
            'titulo' => 'Compartimento Posterior — Cuernos Posteriores de Meniscos',
            'icono'  => 'fa-solid fa-moon',
            'campos' => [
                ['nombre' => 'ecogenicos',             'etiqueta' => 'Ecogénicos',                          'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'sin_lesiones_focales',   'etiqueta' => 'Sin lesiones focales ni difusas',     'tipo' => 'radio_sinno', 'ancho' => 'medio'],
            ],
        ],

        /* ─── 16. Paquete Vascular Poplíteo ─── */
        [
            'id'     => 'paquete_vascular_popliteo',
            'titulo' => 'Compartimento Posterior — Paquete Vascular Poplíteo',
            'icono'  => 'fa-solid fa-heart-pulse',
            'campos' => [
                ['nombre' => 'ubicacion_anatomica_normal',     'etiqueta' => 'De ubicación anatómica normal',                  'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'trayecto_normal',                'etiqueta' => 'Con trayecto normal',                            'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'sin_alteraciones_estructurales', 'etiqueta' => 'Sin evidencias de alteraciones estructurales',   'tipo' => 'radio_sinno', 'ancho' => 'completo'],
            ],
        ],

        /* ─── 17. Conclusión ─── */
        [
            'id'     => 'conclusion',
            'titulo' => 'Conclusión',
            'icono'  => 'fa-solid fa-file-medical',
            'campos' => [
                ['nombre' => 'conclusion', 'etiqueta' => 'Estudio Ecosonográfico de rodillas — con signos sugestivos de:', 'tipo' => 'textarea', 'filas' => 6, 'ancho' => 'completo', 'requerido' => true],
            ],
        ],
    ],
];

$json        = json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
$descripcion = 'Informe ecosonográfico de rodilla: piel, subcutáneo y compartimentos anterior, medial, lateral y posterior.';
$icono       = 'fa-solid fa-person-running';

$sql = "UPDATE tipos_ecografias
        SET esquema_campos  = ?,
            esquema_version = esquema_version + 1,
            descripcion     = ?,
            icono           = ?
        WHERE codigo = 'ECO_MUSCU_RODILLA'";

$stmt = $conex->prepare($sql);
$stmt->bind_param('sss', $json, $descripcion, $icono);

echo '<style>body{font-family:monospace;background:#f6f8fa;padding:24px;}pre{background:#f0fff4;padding:20px;border-radius:8px;font-size:13px;}</style>';

if ($stmt->execute()) {
    $filas = $stmt->affected_rows;
    $stmt->close();

    echo '<pre>';
    if ($filas > 0) {
        echo '<strong style="color:#15803d;">✔ Esquema de Rodilla actualizado (literal al documento físico).</strong>' . "\n\n";
    } else {
        echo '<strong style="color:#b45309;">⚠ No se encontró el registro (codigo=ECO_MUSCU_RODILLA).</strong>' . "\n\n";
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
    echo "\n<strong>Siguiente paso:</strong> Recarga (Ctrl+Shift+R), abre Musculoesquelética → Rodilla.\n";
    echo '</pre>';
} else {
    echo '<p style="color:red;font-size:14px;"><strong>Error:</strong> ' . htmlspecialchars($conex->error) . '</p>';
    $stmt->close();
}

$conex->close();
