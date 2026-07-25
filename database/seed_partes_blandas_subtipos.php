<?php
/**
 * Ejecutar UNA SOLA VEZ para crear/actualizar los 3 sub-tipos de Ecografía de Partes Blandas:
 *   • Partes Blandas (General)
 *   • Partes Blandas de Cuello
 *   • Partes Blandas — Región Inguinal
 * Acceder desde: http://localhost/Sistema_EcoMadelleineV1/database/seed_partes_blandas_subtipos.php
 */
/* Guarda: escribe en la base de datos, solo por linea de comandos.
   Sin esto, cualquiera podia dispararlo desde el navegador. */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script solo puede ejecutarse por linea de comandos.');
}
include __DIR__ . '/../core/conexion.php';

/* Encabezado del paciente (común) */
$encabezado_paciente = [
    'id'        => 'encabezado',
    'titulo'    => 'Datos del Paciente',
    'icono'     => 'fa-solid fa-id-card',
    'campos'    => [
        ['nombre' => 'nombres_apellidos', 'etiqueta' => 'Nombres y Apellidos', 'tipo' => 'text',     'ancho' => 'completo', 'requerido' => true],
        ['nombre' => 'edad',              'etiqueta' => 'Edad',                'tipo' => 'number',   'ancho' => 'tercio',   'unidad' => 'años', 'min' => 0, 'readonly' => true],
        ['nombre' => 'cedula',            'etiqueta' => 'Cédula (CI)',         'tipo' => 'text',     'ancho' => 'tercio',   'readonly' => true],
        ['nombre' => 'fecha',             'etiqueta' => 'Fecha',               'tipo' => 'date',     'ancho' => 'tercio',   'requerido' => true, 'readonly' => true],
        ['nombre' => 'motivo_consulta',   'etiqueta' => 'Motivo de Consulta',  'tipo' => 'textarea', 'ancho' => 'completo', 'filas' => 2, 'requerido' => true],
    ],
];

/* ═══════════════════════════════════════════════════════════════
   SCHEMA 1 — PARTES BLANDAS (GENERAL)
   ═══════════════════════════════════════════════════════════════ */
$schema_general = [
    'version'   => 1,
    'secciones' => [
        $encabezado_paciente + ['subtitulo' => 'Se practica estudio ecosonográfico en tiempo real, usando transductor lineal multifrecuencial, obteniendo los siguientes hallazgos:'],

        [
            'id'     => 'region_estudiada',
            'titulo' => 'Región Estudiada',
            'icono'  => 'fa-solid fa-location-dot',
            'campos' => [
                ['nombre' => 'region', 'etiqueta' => 'Estudio ecosonograma de partes blandas en', 'tipo' => 'text', 'ancho' => 'completo', 'requerido' => true],
            ],
        ],

        [
            'id'     => 'piel',
            'titulo' => 'Piel',
            'icono'  => 'fa-solid fa-hand-dots',
            'campos' => [
                ['nombre' => 'continua_ecogenica',     'etiqueta' => 'Continua ecogénica',       'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'evidencia_lesiones',     'etiqueta' => 'Evidencia de lesiones',    'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'observaciones',          'etiqueta' => 'Observaciones',             'tipo' => 'text',        'ancho' => 'completo'],
            ],
        ],

        [
            'id'     => 'tejido_subcutaneo',
            'titulo' => 'Tejido Celular Subcutáneo',
            'icono'  => 'fa-solid fa-layer-group',
            'campos' => [
                ['nombre' => 'cantidad', 'etiqueta' => 'Cantidad', 'tipo' => 'radio',
                 'opciones' => ['Escaso', 'Abundante', 'Moderado'], 'ancho' => 'completo'],
                ['nombre' => 'hipoecoico_observaciones', 'etiqueta' => 'Hipoecoico — Observaciones', 'tipo' => 'text', 'ancho' => 'completo'],
            ],
        ],

        [
            'id'     => 'estructuras_musculares',
            'titulo' => 'Estructuras Musculares',
            'icono'  => 'fa-solid fa-dumbbell',
            'campos' => [
                ['nombre' => 'distribucion_trayecto_normal', 'etiqueta' => 'Distribución y trayecto normal',                                              'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'patron_sin_patologicas',       'etiqueta' => 'Patrón ecográfico normal, sin imágenes patológicas focales ni difusas',     'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'observaciones',                'etiqueta' => 'Observaciones',                                                                'tipo' => 'text',        'ancho' => 'completo'],
            ],
        ],

        [
            'id'     => 'estructuras_vasculares',
            'titulo' => 'Estructuras Vasculares',
            'icono'  => 'fa-solid fa-heart-pulse',
            'campos' => [
                ['nombre' => 'distribucion_trayecto_normal', 'etiqueta' => 'Distribución y trayecto normal',                                              'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'patron_sin_patologicas',       'etiqueta' => 'Patrón ecográfico normal, sin imágenes patológicas focales ni difusas',     'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'observaciones',                'etiqueta' => 'Observaciones',                                                                'tipo' => 'text',        'ancho' => 'completo'],
            ],
        ],

        [
            'id'     => 'estructuras_oseas',
            'titulo' => 'Estructuras Óseas',
            'icono'  => 'fa-solid fa-bone',
            'campos' => [
                ['nombre' => 'lineal_ecogenica',          'etiqueta' => 'Lineal ecogénica',                            'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'sin_solucion_continuidad',  'etiqueta' => 'Sin evidenciar solución de continuidad',      'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'observaciones',             'etiqueta' => 'Observaciones',                                'tipo' => 'text',        'ancho' => 'completo'],
            ],
        ],

        [
            'id'     => 'conclusion',
            'titulo' => 'Conclusión',
            'icono'  => 'fa-solid fa-file-medical',
            'campos' => [
                ['nombre' => 'conclusion', 'etiqueta' => 'Estudio Ecosonográfico', 'tipo' => 'textarea', 'filas' => 6, 'ancho' => 'completo', 'requerido' => true],
            ],
        ],
    ],
];

/* ═══════════════════════════════════════════════════════════════
   SCHEMA 2 — PARTES BLANDAS DE CUELLO
   ═══════════════════════════════════════════════════════════════ */
$schema_cuello = [
    'version'   => 1,
    'secciones' => [
        $encabezado_paciente + ['subtitulo' => 'Se practica estudio ecosonográfico en tiempo real, usando transductor lineal multifrecuencial de 7.5 - 10 MHz, obteniendo los siguientes hallazgos:'],

        [
            'id'     => 'piel',
            'titulo' => 'Piel',
            'icono'  => 'fa-solid fa-hand-dots',
            'campos' => [
                ['nombre' => 'continua_ecogenica', 'etiqueta' => 'Continua ecogénica', 'tipo' => 'radio_sinno', 'ancho' => 'completo'],
            ],
        ],

        [
            'id'     => 'tejido_subcutaneo',
            'titulo' => 'Tejido Celular Subcutáneo',
            'icono'  => 'fa-solid fa-layer-group',
            'campos' => [
                ['nombre' => 'moderada_hipoecoico_conserva', 'etiqueta' => 'Moderada cantidad hipoecogénico, conserva su distribución y ecopatrón', 'tipo' => 'radio_sinno', 'ancho' => 'completo'],
            ],
        ],

        [
            'id'     => 'glandula_parotida_derecha',
            'titulo' => 'Glándula Parótida Derecha',
            'icono'  => 'fa-solid fa-circle-nodes',
            'campos' => [
                ['nombre' => 'forma_tamano_normal', 'etiqueta' => 'Forma y tamaño normal', 'tipo' => 'radio_sinno', 'ancho' => 'completo'],
            ],
        ],
        [
            'id'     => 'glandula_parotida_izquierda',
            'titulo' => 'Glándula Parótida Izquierda',
            'icono'  => 'fa-solid fa-circle-nodes',
            'campos' => [
                ['nombre' => 'forma_tamano_normal', 'etiqueta' => 'Forma y tamaño normal', 'tipo' => 'radio_sinno', 'ancho' => 'completo'],
            ],
        ],
        [
            'id'     => 'glandula_submaxilar',
            'titulo' => 'Glándula Submaxilar',
            'icono'  => 'fa-solid fa-circle-nodes',
            'campos' => [
                ['nombre' => 'forma_tamano_normal', 'etiqueta' => 'Forma y tamaño normal', 'tipo' => 'radio_sinno', 'ancho' => 'completo'],
            ],
        ],
        [
            'id'     => 'glandula_tiroidea',
            'titulo' => 'Glándula Tiroidea',
            'icono'  => 'fa-solid fa-shield-halved',
            'campos' => [
                ['nombre' => 'forma_tamano_normal', 'etiqueta' => 'Forma y tamaño normal', 'tipo' => 'radio_sinno', 'ancho' => 'completo'],
            ],
        ],

        [
            'id'     => 'region_lateral_derecha',
            'titulo' => 'Región Lateral Derecha del Cuello',
            'icono'  => 'fa-solid fa-arrow-right',
            'campos' => [
                ['nombre' => 'descripcion', 'etiqueta' => 'Descripción / Hallazgos', 'tipo' => 'textarea', 'filas' => 2, 'ancho' => 'completo'],
            ],
        ],
        [
            'id'     => 'region_lateral_izquierda',
            'titulo' => 'Región Lateral Izquierda del Cuello',
            'icono'  => 'fa-solid fa-arrow-left',
            'campos' => [
                ['nombre' => 'descripcion', 'etiqueta' => 'Descripción / Hallazgos', 'tipo' => 'textarea', 'filas' => 2, 'ancho' => 'completo'],
            ],
        ],

        [
            'id'     => 'estructura_muscular',
            'titulo' => 'Estructura Muscular',
            'icono'  => 'fa-solid fa-dumbbell',
            'campos' => [
                ['nombre' => 'distribucion_trayecto_ecopatron_normal', 'etiqueta' => 'Distribución, trayecto y ecopatrón normal', 'tipo' => 'radio_sinno', 'ancho' => 'completo'],
            ],
        ],

        [
            'id'     => 'estructura_vascular',
            'titulo' => 'Estructura Vascular',
            'icono'  => 'fa-solid fa-heart-pulse',
            'campos' => [
                ['nombre' => 'distribucion_trayecto_sin_lesiones', 'etiqueta' => 'Distribución y trayecto normal sin evidencia de lesiones', 'tipo' => 'radio_sinno', 'ancho' => 'completo'],
            ],
        ],

        [
            'id'     => 'carotida',
            'titulo' => 'Carótida',
            'icono'  => 'fa-solid fa-wave-square',
            'campos' => [
                ['nombre' => 'sin_alteraciones_aparentes', 'etiqueta' => 'Sin alteraciones ecográficas aparentes', 'tipo' => 'radio_sinno', 'ancho' => 'completo'],
            ],
        ],
        [
            'id'     => 'yugular',
            'titulo' => 'Yugular',
            'icono'  => 'fa-solid fa-wave-square',
            'campos' => [
                ['nombre' => 'sin_alteraciones', 'etiqueta' => 'Sin alteraciones', 'tipo' => 'radio_sinno', 'ancho' => 'completo'],
            ],
        ],
        [
            'id'     => 'relaciones_musculares',
            'titulo' => 'Relaciones Musculares',
            'icono'  => 'fa-solid fa-link',
            'campos' => [
                ['nombre' => 'sin_alteraciones', 'etiqueta' => 'Sin alteraciones', 'tipo' => 'radio_sinno', 'ancho' => 'completo'],
            ],
        ],

        [
            'id'     => 'conclusion',
            'titulo' => 'Conclusión',
            'icono'  => 'fa-solid fa-file-medical',
            'campos' => [
                ['nombre' => 'conclusion', 'etiqueta' => 'Estudio de cuello — con signos sugestivos de:', 'tipo' => 'textarea', 'filas' => 6, 'ancho' => 'completo', 'requerido' => true],
            ],
        ],
    ],
];

/* ═══════════════════════════════════════════════════════════════
   SCHEMA 3 — PARTES BLANDAS REGIÓN INGUINAL
   ═══════════════════════════════════════════════════════════════ */
$campos_region_inguinal = [
    ['nombre' => 'saco_herniario',           'etiqueta' => 'Saco herniario',                                                'tipo' => 'radio_sinno', 'ancho' => 'completo'],
    ['nombre' => 'contenido_intestinal',     'etiqueta' => 'Con contenido intestinal (peristaltismo presente)',             'tipo' => 'radio_sinno', 'ancho' => 'completo'],
    ['nombre' => 'atraviesa_anillo_inguinal','etiqueta' => 'Atraviesa anillo inguinal (al realizar maniobra de Valsalva)', 'tipo' => 'radio_sinno', 'ancho' => 'completo'],
    ['nombre' => 'diametro_mm',              'etiqueta' => 'Diámetro',                                                       'tipo' => 'number',      'ancho' => 'tercio', 'unidad' => 'mm'],
    ['nombre' => 'sugestivo_hernia',         'etiqueta' => 'Sugestivo de Hernia',                                            'tipo' => 'radio_sinno', 'ancho' => 'completo'],
];

$schema_inguinal = [
    'version'   => 1,
    'secciones' => [
        $encabezado_paciente + ['subtitulo' => 'Evaluación ecográfica de región inguinal con equipo MINDRAY, transductor lineal de 7.5 - 10 MHz, mediante barridos coronales, longitudinales y oblicuos. Estudio de región inguinal bilateral:'],

        [
            'id'     => 'piel',
            'titulo' => 'Piel',
            'icono'  => 'fa-solid fa-hand-dots',
            'campos' => [
                ['nombre' => 'ecogenica_continua_sin_lesiones', 'etiqueta' => 'Ecogénica continua, sin lesiones focales ni difusas', 'tipo' => 'radio_sinno', 'ancho' => 'completo'],
            ],
        ],

        [
            'id'     => 'tejido_subcutaneo',
            'titulo' => 'Tejido Celular Subcutáneo',
            'icono'  => 'fa-solid fa-layer-group',
            'campos' => [
                ['nombre' => 'escasa_cantidad_hipoecogenico', 'etiqueta' => 'De escasa cantidad, hipoecogénico', 'tipo' => 'radio_sinno', 'ancho' => 'completo'],
            ],
        ],

        /* Regiones inguinales (PAR: Derecha / Izquierda) */
        [
            'id'           => 'regiones_inguinales',
            'titulo'       => 'Regiones Inguinales',
            'icono'        => 'fa-solid fa-arrows-left-right',
            'tipo_seccion' => 'par',
            'lados'        => ['Región Inguinal Derecha', 'Región Inguinal Izquierda'],
            'ids_lados'    => ['inguinal_der', 'inguinal_izq'],
            'campos'       => $campos_region_inguinal,
        ],

        [
            'id'     => 'tejido_muscular',
            'titulo' => 'Tejido Muscular',
            'icono'  => 'fa-solid fa-dumbbell',
            'campos' => [
                ['nombre' => 'conserva_ecopatron_trayecto', 'etiqueta' => 'Conserva su ecopatrón y trayecto, sin lesiones aparentes', 'tipo' => 'radio_sinno', 'ancho' => 'completo'],
            ],
        ],

        [
            'id'     => 'estructuras_vasculares',
            'titulo' => 'Estructuras Vasculares',
            'icono'  => 'fa-solid fa-heart-pulse',
            'campos' => [
                ['nombre' => 'conserva_distribucion_trayecto_ecopatron', 'etiqueta' => 'Conserva su distribución, trayecto y ecopatrón', 'tipo' => 'radio_sinno', 'ancho' => 'completo'],
            ],
        ],

        [
            'id'     => 'conclusion',
            'titulo' => 'Conclusiones',
            'icono'  => 'fa-solid fa-file-medical',
            'campos' => [
                ['nombre' => 'conclusion', 'etiqueta' => 'Signos ecográficos sugestivos de:', 'tipo' => 'textarea', 'filas' => 6, 'ancho' => 'completo', 'requerido' => true],
            ],
        ],
    ],
];

/* ═══════════════════════════════════════════════════════════════
   UPSERT los 3 sub-tipos
   ═══════════════════════════════════════════════════════════════ */
$subtipos = [
    [
        'codigo'      => 'ECO_PBL_GENERAL',
        'nombre'      => 'Partes Blandas (General)',
        'descripcion' => 'Ecosonograma de partes blandas en región específica (piel, tejido, músculos, vascular, óseo).',
        'icono'       => 'fa-solid fa-hand-holding-medical',
        'posicion'    => 41,
        'schema'      => $schema_general,
    ],
    [
        'codigo'      => 'ECO_PBL_CUELLO',
        'nombre'      => 'Partes Blandas de Cuello',
        'descripcion' => 'Ecosonograma de partes blandas de cuello (glándulas parótidas, submaxilar, tiroidea, vasos).',
        'icono'       => 'fa-solid fa-circle-nodes',
        'posicion'    => 42,
        'schema'      => $schema_cuello,
    ],
    [
        'codigo'      => 'ECO_PBL_INGUINAL',
        'nombre'      => 'Partes Blandas — Región Inguinal',
        'descripcion' => 'Ecosonograma de región inguinal bilateral (descarte de hernia).',
        'icono'       => 'fa-solid fa-arrows-left-right',
        'posicion'    => 43,
        'schema'      => $schema_inguinal,
    ],
];

echo '<style>body{font-family:monospace;background:#f6f8fa;padding:24px;}pre{background:#f0fff4;padding:20px;border-radius:8px;font-size:13px;}</style>';
echo '<pre>';
echo "Sembrando sub-tipos de Ecografía de Partes Blandas…\n\n";

foreach ($subtipos as $s) {
    $json = json_encode($s['schema'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    $sql = "INSERT INTO tipos_ecografias
                (codigo, nombre, categoria, descripcion, icono, esquema_campos, esquema_version, activo, posicion)
            VALUES (?, ?, 'Partes_Blandas_Sub', ?, ?, ?, 1, 1, ?)
            ON DUPLICATE KEY UPDATE
                nombre          = VALUES(nombre),
                categoria       = VALUES(categoria),
                descripcion     = VALUES(descripcion),
                icono           = VALUES(icono),
                esquema_campos  = VALUES(esquema_campos),
                esquema_version = esquema_version + 1,
                posicion        = VALUES(posicion)";

    $stmt = $conex->prepare($sql);
    $stmt->bind_param('sssssi', $s['codigo'], $s['nombre'], $s['descripcion'], $s['icono'], $json, $s['posicion']);

    if ($stmt->execute()) {
        $accion = $stmt->insert_id > 0 ? 'INSERT' : 'UPDATE';
        $secciones = count($s['schema']['secciones']);
        echo "  ✔ [$accion] " . $s['nombre'] . " — $secciones secciones\n";
    } else {
        echo "  [ERROR] " . htmlspecialchars($stmt->error) . "\n";
    }
    $stmt->close();
}

echo "\n<strong style=\"color:#15803d;\">✔ Listo.</strong>\n";
echo "Siguiente paso: la tarjeta \"Ecografía de Partes Blandas\" abrirá un sub-selector con los 3 estudios.\n";
echo '</pre>';

$conex->close();
