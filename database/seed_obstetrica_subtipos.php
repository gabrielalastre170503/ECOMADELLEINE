<?php
/**
 * Ejecutar UNA SOLA VEZ para crear/actualizar los 2 sub-tipos de Ecografía Obstétrica:
 *   • Ecografía Obstétrica I Trimestre
 *   • Ecografía Obstétrica II y III Trimestre
 * Acceder desde: http://localhost/Sistema_EcoMadelleineV1/database/seed_obstetrica_subtipos.php
 */
/* Guarda: escribe en la base de datos, solo por linea de comandos.
   Sin esto, cualquiera podia dispararlo desde el navegador. */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script solo puede ejecutarse por linea de comandos.');
}
include __DIR__ . '/../core/conexion.php';

/* Encabezado + Antecedentes comunes a ambos sub-tipos */
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

$antecedentes_gineco = [
    'id'     => 'antecedentes_gineco',
    'titulo' => 'Antecedentes Gineco-Obstétricos',
    'icono'  => 'fa-solid fa-venus',
    'campos' => [
        ['nombre' => 'fur',       'etiqueta' => 'FUR (Fecha de Última Regla)', 'tipo' => 'date',   'ancho' => 'medio'],
        ['nombre' => 'menarquia', 'etiqueta' => 'Menarquia',                   'tipo' => 'text',   'ancho' => 'medio'],
        ['nombre' => 'gestas',    'etiqueta' => 'Gestas',                      'tipo' => 'number', 'ancho' => 'sexto',  'min' => 0],
        ['nombre' => 'paras',     'etiqueta' => 'Paras',                       'tipo' => 'number', 'ancho' => 'sexto',  'min' => 0],
        ['nombre' => 'cesareas',  'etiqueta' => 'Cesáreas',                    'tipo' => 'number', 'ancho' => 'sexto',  'min' => 0],
        ['nombre' => 'abortos',   'etiqueta' => 'Abortos',                     'tipo' => 'number', 'ancho' => 'sexto',  'min' => 0],
        ['nombre' => 'aco',       'etiqueta' => 'ACO',                          'tipo' => 'text',   'ancho' => 'tercio'],
    ],
];

/* ═══════════════════════════════════════════════════════════════
   SCHEMA I TRIMESTRE
   ═══════════════════════════════════════════════════════════════ */
$schema_i = [
    'version'   => 1,
    'secciones' => [

        $encabezado_paciente + ['subtitulo' => 'Se realiza estudio en tiempo real con transductor multifrecuencia, observándose:'],
        $antecedentes_gineco,

        /* Tipo de transductor */
        [
            'id'     => 'tipo_estudio',
            'titulo' => 'Tipo de Estudio',
            'icono'  => 'fa-solid fa-wave-square',
            'campos' => [
                ['nombre' => 'tipo_transductor', 'etiqueta' => 'Transductor utilizado', 'tipo' => 'radio',
                 'opciones' => ['Convex', 'Endocavitario'], 'ancho' => 'completo'],
            ],
        ],

        /* Vejiga */
        [
            'id'     => 'vejiga',
            'titulo' => 'Vejiga',
            'icono'  => 'fa-solid fa-droplet',
            'campos' => [
                ['nombre' => 'paredes_descripcion',      'etiqueta' => 'De paredes',                                                                                'tipo' => 'text',        'ancho' => 'medio'],
                ['nombre' => 'paredes_medida',           'etiqueta' => 'Medida de paredes',                                                                          'tipo' => 'number',      'ancho' => 'medio',  'unidad' => 'mm'],
                ['nombre' => 'bordes_sin_anormalidades', 'etiqueta' => 'Bordes regulares, sin anormalidades en su pared y sin imágenes patológicas en su interior', 'tipo' => 'radio_sinno', 'ancho' => 'completo'],
                ['nombre' => 'litiasis',                 'etiqueta' => 'Litiasis',                                                                                   'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'litiasis_mm',              'etiqueta' => 'Litiasis — Medida',                                                                          'tipo' => 'number',      'ancho' => 'medio',  'unidad' => 'mm',
                 'depende_de' => 'litiasis', 'depende_valor' => 'SI'],
            ],
        ],

        /* Útero */
        [
            'id'     => 'utero',
            'titulo' => 'Útero',
            'icono'  => 'fa-solid fa-circle-nodes',
            'campos' => [
                ['nombre' => 'situado_en',         'etiqueta' => 'Situado en',           'tipo' => 'radio',
                 'opciones' => ['AVF', 'Indiferente', 'Central', 'RVF'], 'ancho' => 'completo'],
                ['nombre' => 'aumentado_tamano',   'etiqueta' => 'Aumentado de tamaño',  'tipo' => 'radio_sinno', 'ancho' => 'medio'],
            ],
        ],

        /* Miometrio */
        [
            'id'     => 'miometrio',
            'titulo' => 'Miometrio',
            'icono'  => 'fa-solid fa-layer-group',
            'campos' => [
                ['nombre' => 'homogeneo',             'etiqueta' => 'Homogéneo',                                                            'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'contornos_sin_lesiones','etiqueta' => 'De contornos regulares, sin evidencias de lesiones focales ni difusas','tipo' => 'radio_sinno', 'ancho' => 'completo'],
            ],
        ],

        /* Cavidad Uterina */
        [
            'id'     => 'cavidad_uterina',
            'titulo' => 'Cavidad Uterina',
            'icono'  => 'fa-solid fa-baby',
            'campos' => [
                ['nombre' => 'saco_gestacional_medidas',     'etiqueta' => 'Ocupada por Saco Gestacional — Medidas',                                'tipo' => 'text',     'ancho' => 'completo'],
                ['nombre' => 'compatible_embarazo_saco',     'etiqueta' => 'Compatible con embarazo de',                                            'tipo' => 'text',     'ancho' => 'completo'],
                ['nombre' => 'lcr_medida',                   'etiqueta' => 'Embrión con actividad cardíaca y motora presente — LCR de medida',     'tipo' => 'text',     'ancho' => 'completo'],
                ['nombre' => 'compatible_embarazo_lcr',      'etiqueta' => 'Compatible con embarazo de',                                            'tipo' => 'text',     'ancho' => 'completo'],
            ],
        ],

        /* Vesícula Vitelina */
        [
            'id'     => 'vesicula_vitelina',
            'titulo' => 'Vesícula Vitelina',
            'icono'  => 'fa-solid fa-circle',
            'campos' => [
                ['nombre' => 'indemne', 'etiqueta' => 'Indemne', 'tipo' => 'radio_sinno', 'ancho' => 'completo'],
            ],
        ],

        /* Líquido Amniótico */
        [
            'id'     => 'liquido_amniotico',
            'titulo' => 'Líquido Amniótico',
            'icono'  => 'fa-solid fa-water',
            'campos' => [
                ['nombre' => 'acorde_edad_gestacional', 'etiqueta' => 'Acorde para edad gestacional', 'tipo' => 'radio_sinno', 'ancho' => 'completo'],
            ],
        ],

        /* Fondo de Saco Posterior */
        [
            'id'     => 'fondo_saco_posterior',
            'titulo' => 'Fondo de Saco Posterior',
            'icono'  => 'fa-solid fa-magnifying-glass',
            'campos' => [
                ['nombre' => 'libre', 'etiqueta' => 'Libre', 'tipo' => 'radio_sinno', 'ancho' => 'completo'],
            ],
        ],

        /* Conclusión y Diagnóstico */
        [
            'id'     => 'conclusion',
            'titulo' => 'Conclusión y Diagnóstico',
            'icono'  => 'fa-solid fa-file-medical',
            'campos' => [
                ['nombre' => 'conclusion',    'etiqueta' => 'Estudio Obstétrico — con signos sugestivos de:', 'tipo' => 'textarea', 'filas' => 5, 'ancho' => 'completo', 'requerido' => true],
                ['nombre' => 'diagnostico_1', 'etiqueta' => 'Diagnóstico 1',                                  'tipo' => 'textarea', 'filas' => 3, 'ancho' => 'completo'],
            ],
        ],
    ],
];

/* ═══════════════════════════════════════════════════════════════
   SCHEMA II Y III TRIMESTRE
   ═══════════════════════════════════════════════════════════════ */
$schema_ii_iii = [
    'version'   => 1,
    'secciones' => [

        $encabezado_paciente + ['subtitulo' => 'Se realiza estudio en tiempo real con transductor convex multifrecuencia, observándose cortes coronales, transversales, longitudinales y oblicuos. Modo B, No Doppler. Hallazgos:'],
        $antecedentes_gineco,

        /* Datos Obstétricos */
        [
            'id'     => 'datos_obstetricos',
            'titulo' => 'Datos Obstétricos',
            'icono'  => 'fa-solid fa-baby',
            'campos' => [
                ['nombre' => 'embarazo_intrauterino', 'etiqueta' => 'Embarazo: Intrauterino',  'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'feto_unico',            'etiqueta' => 'Feto: Único',             'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'situacion',             'etiqueta' => 'Situación',                'tipo' => 'text',        'ancho' => 'medio'],
                ['nombre' => 'presentacion',          'etiqueta' => 'Presentación',             'tipo' => 'radio',
                 'opciones' => ['Cefálica', 'Podálico'], 'ancho' => 'medio'],
                ['nombre' => 'posicion_dorso',        'etiqueta' => 'Posición Dorso',           'tipo' => 'radio',
                 'opciones' => ['Izquierdo', 'Derecho'], 'ancho' => 'completo'],
            ],
        ],

        /* Datos Anatómicos */
        [
            'id'     => 'datos_anatomicos',
            'titulo' => 'Datos Anatómicos',
            'icono'  => 'fa-solid fa-person',
            'campos' => [
                ['nombre' => 'polo_cefalico_normal',          'etiqueta' => 'Polo cefálico Normal',         'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'ventriculos_cerebrales_normal', 'etiqueta' => 'Ventrículos cerebrales Normal','tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'columna_normal',                'etiqueta' => 'Columna Normal',                'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'sexo',                          'etiqueta' => 'Sexo',                          'tipo' => 'text',        'ancho' => 'tercio'],
                ['nombre' => 'no_visible',                    'etiqueta' => 'No visible',                    'tipo' => 'text',        'ancho' => 'tercio'],
            ],
        ],

        /* Datos Funcionales */
        [
            'id'     => 'datos_funcionales',
            'titulo' => 'Datos Funcionales',
            'icono'  => 'fa-solid fa-heart-pulse',
            'campos' => [
                ['nombre' => 'actitud_flexion',            'etiqueta' => 'Actitud: Flexión',                'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'tono_normal',                'etiqueta' => 'Tono: Normal',                    'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'movimientos_fetales',        'etiqueta' => 'Movimientos Fetales Presentes',   'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'actividad_cardiaca',         'etiqueta' => 'Actividad cardíaca: Presentes',   'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'fcf',                         'etiqueta' => 'FCF',                              'tipo' => 'number',      'ancho' => 'tercio', 'unidad' => 'lpm'],
                ['nombre' => 'estomago_lleno',             'etiqueta' => 'Estómago: Lleno',                  'tipo' => 'radio_sinno', 'ancho' => 'tercio'],
            ],
        ],

        /* Datos Biométricos */
        [
            'id'     => 'datos_biometricos',
            'titulo' => 'Datos Biométricos',
            'icono'  => 'fa-solid fa-ruler',
            'campos' => [
                /* Diámetro Biparietal */
                ['nombre' => 'dbp_mm',     'etiqueta' => 'Diámetro Biparietal',                  'tipo' => 'number', 'ancho' => 'tercio', 'unidad' => 'mm'],
                ['nombre' => 'dbp_sem',    'etiqueta' => 'DBP — Semanas',                        'tipo' => 'number', 'ancho' => 'sexto',  'unidad' => 'sem', 'min' => 0],
                ['nombre' => 'dbp_dias',   'etiqueta' => 'DBP — Días',                           'tipo' => 'number', 'ancho' => 'sexto',  'unidad' => 'días','min' => 0],

                /* Perímetro Cefálico */
                ['nombre' => 'pc_mm',      'etiqueta' => 'Perímetro Cefálico',                   'tipo' => 'number', 'ancho' => 'tercio', 'unidad' => 'mm'],
                ['nombre' => 'pc_sem',     'etiqueta' => 'PC — Semanas',                          'tipo' => 'number', 'ancho' => 'sexto',  'unidad' => 'sem', 'min' => 0],
                ['nombre' => 'pc_dias',    'etiqueta' => 'PC — Días',                             'tipo' => 'number', 'ancho' => 'sexto',  'unidad' => 'días','min' => 0],

                /* Perímetro Abdominal */
                ['nombre' => 'pa_mm',      'etiqueta' => 'Perímetro Abdominal',                  'tipo' => 'number', 'ancho' => 'tercio', 'unidad' => 'mm'],
                ['nombre' => 'pa_sem',     'etiqueta' => 'PA — Semanas',                          'tipo' => 'number', 'ancho' => 'sexto',  'unidad' => 'sem', 'min' => 0],
                ['nombre' => 'pa_dias',    'etiqueta' => 'PA — Días',                             'tipo' => 'number', 'ancho' => 'sexto',  'unidad' => 'días','min' => 0],

                /* Longitud de Fémur */
                ['nombre' => 'lf_mm',      'etiqueta' => 'Longitud de Fémur',                    'tipo' => 'number', 'ancho' => 'tercio', 'unidad' => 'mm'],
                ['nombre' => 'lf_sem',     'etiqueta' => 'LF — Semanas',                          'tipo' => 'number', 'ancho' => 'sexto',  'unidad' => 'sem', 'min' => 0],
                ['nombre' => 'lf_dias',    'etiqueta' => 'LF — Días',                             'tipo' => 'number', 'ancho' => 'sexto',  'unidad' => 'días','min' => 0],

                ['nombre' => 'liquido_amniotico_normal', 'etiqueta' => 'Líquido amniótico: Normal', 'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'peso_fetal_estimado',      'etiqueta' => 'Peso Fetal estimado de',    'tipo' => 'text',        'ancho' => 'medio'],
            ],
        ],

        /* Datos Placentarios */
        [
            'id'     => 'datos_placentarios',
            'titulo' => 'Datos Placentarios',
            'icono'  => 'fa-solid fa-circle-half-stroke',
            'campos' => [
                ['nombre' => 'localizacion',         'etiqueta' => 'Localización',                                   'tipo' => 'text',        'ancho' => 'medio'],
                ['nombre' => 'grosor_mm',            'etiqueta' => 'Grosor',                                          'tipo' => 'number',      'ancho' => 'tercio', 'unidad' => 'mm'],
                ['nombre' => 'madurez_grado',        'etiqueta' => 'Madurez grado',                                   'tipo' => 'text',        'ancho' => 'tercio'],
                ['nombre' => 'cordon_tres_elementos','etiqueta' => 'Cordón umbilical: con sus tres elementos',        'tipo' => 'radio_sinno', 'ancho' => 'completo'],
                ['nombre' => 'otros',                'etiqueta' => 'Otros',                                            'tipo' => 'textarea',    'ancho' => 'completo', 'filas' => 2],
            ],
        ],

        /* Impresión Diagnóstica */
        [
            'id'     => 'impresion_diagnostica',
            'titulo' => 'Impresión Diagnóstica',
            'icono'  => 'fa-solid fa-file-medical',
            'campos' => [
                ['nombre' => 'impresion_diagnostica', 'etiqueta' => 'Impresión Diagnóstica', 'tipo' => 'textarea', 'filas' => 6, 'ancho' => 'completo', 'requerido' => true],
            ],
        ],
    ],
];

/* ═══════════════════════════════════════════════════════════════
   UPSERT ambos sub-tipos
   ═══════════════════════════════════════════════════════════════ */
$subtipos = [
    [
        'codigo'      => 'ECO_OBS_I_TRIM',
        'nombre'      => 'Ecografía Obstétrica I Trimestre',
        'descripcion' => 'Reporte ecográfico obstétrico del primer trimestre (saco gestacional, embrión, LCR).',
        'icono'       => 'fa-solid fa-baby',
        'posicion'    => 21,
        'schema'      => $schema_i,
    ],
    [
        'codigo'      => 'ECO_OBS_II_III_TRIM',
        'nombre'      => 'Ecografía Obstétrica II y III Trimestre',
        'descripcion' => 'Reporte ecográfico obstétrico de segundo y tercer trimestre (biometría fetal y placenta).',
        'icono'       => 'fa-solid fa-person-pregnant',
        'posicion'    => 22,
        'schema'      => $schema_ii_iii,
    ],
];

echo '<style>body{font-family:monospace;background:#f6f8fa;padding:24px;}pre{background:#f0fff4;padding:20px;border-radius:8px;font-size:13px;}</style>';
echo '<pre>';
echo "Sembrando sub-tipos de Ecografía Obstétrica…\n\n";

foreach ($subtipos as $s) {
    $json = json_encode($s['schema'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    $sql = "INSERT INTO tipos_ecografias
                (codigo, nombre, categoria, descripcion, icono, esquema_campos, esquema_version, activo, posicion)
            VALUES (?, ?, 'Obstetrica_Sub', ?, ?, ?, 1, 1, ?)
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
echo "Siguiente paso: la tarjeta principal \"Ecografía Obstétrica\" abrirá un sub-selector con los 2 trimestres.\n";
echo '</pre>';

$conex->close();
