<?php
/**
 * Ejecutar UNA SOLA VEZ para registrar/actualizar el esquema de Ecografía Pélvica (Ginecológica).
 * Acceder desde: http://localhost/Sistema_EcoMadelleineV1/database/seed_schema_pelvica.php
 */
/* Guarda: escribe en la base de datos, solo por linea de comandos.
   Sin esto, cualquiera podia dispararlo desde el navegador. */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script solo puede ejecutarse por linea de comandos.');
}
include __DIR__ . '/../core/conexion.php';

/* Campos comunes a ambos ovarios (par: Derecho / Izquierdo) */
$campos_ovario = [
    ['nombre' => 'medida_l',  'etiqueta' => 'Medida L',  'tipo' => 'number', 'ancho' => 'tercio', 'unidad' => 'mm'],
    ['nombre' => 'medida_ap', 'etiqueta' => 'Medida AP', 'tipo' => 'number', 'ancho' => 'tercio', 'unidad' => 'mm'],
    ['nombre' => 'medida_t',  'etiqueta' => 'Medida T',  'tipo' => 'number', 'ancho' => 'tercio', 'unidad' => 'mm'],
    ['nombre' => 'ecopatron_normal',           'etiqueta' => 'De ecopatrón normal',                 'tipo' => 'radio_sinno', 'ancho' => 'completo'],
    ['nombre' => 'sin_imagenes_patologicas',   'etiqueta' => 'Sin imágenes patológicas en su interior', 'tipo' => 'radio_sinno', 'ancho' => 'completo'],
];

$schema = [
    'version'   => 1,
    'secciones' => [

        /* ─── 1. Encabezado ─── */
        [
            'id'     => 'encabezado',
            'titulo' => 'Datos del Paciente',
            'icono'  => 'fa-solid fa-id-card',
            'campos' => [
                ['nombre' => 'nombres_apellidos', 'etiqueta' => 'Nombres y Apellidos', 'tipo' => 'text',     'ancho' => 'completo', 'requerido' => true],
                ['nombre' => 'edad',              'etiqueta' => 'Edad',                'tipo' => 'number',   'ancho' => 'tercio',   'unidad' => 'años', 'min' => 0, 'readonly' => true],
                ['nombre' => 'cedula',            'etiqueta' => 'Cédula (CI)',         'tipo' => 'text',     'ancho' => 'tercio',   'readonly' => true],
                ['nombre' => 'fecha',             'etiqueta' => 'Fecha',               'tipo' => 'date',     'ancho' => 'tercio',   'requerido' => true, 'readonly' => true],
                ['nombre' => 'motivo_consulta',   'etiqueta' => 'Motivo de Consulta',  'tipo' => 'textarea', 'ancho' => 'completo', 'filas' => 2, 'requerido' => true],
            ],
        ],

        /* ─── 2. Antecedentes ginecológicos y obstétricos ─── */
        [
            'id'        => 'antecedentes_gineco',
            'titulo'    => 'Antecedentes Ginecológicos y Obstétricos',
            'icono'     => 'fa-solid fa-venus',
            'subtitulo' => 'FUR / Menarquia / Fórmula obstétrica (G P C A) y método anticonceptivo.',
            'campos'    => [
                ['nombre' => 'fur',       'etiqueta' => 'FUR (Fecha de Última Regla)', 'tipo' => 'date',   'ancho' => 'medio'],
                ['nombre' => 'menarquia', 'etiqueta' => 'Menarquia (edad)',            'tipo' => 'number', 'ancho' => 'medio',  'unidad' => 'años', 'min' => 0],
                ['nombre' => 'gestas',    'etiqueta' => 'Gestas',                      'tipo' => 'number', 'ancho' => 'sexto',  'min' => 0],
                ['nombre' => 'paras',     'etiqueta' => 'Paras',                       'tipo' => 'number', 'ancho' => 'sexto',  'min' => 0],
                ['nombre' => 'cesareas',  'etiqueta' => 'Cesáreas',                    'tipo' => 'number', 'ancho' => 'sexto',  'min' => 0],
                ['nombre' => 'abortos',   'etiqueta' => 'Abortos',                     'tipo' => 'number', 'ancho' => 'sexto',  'min' => 0],
                ['nombre' => 'aco',       'etiqueta' => 'ACO (Anticonceptivos Orales)','tipo' => 'text',   'ancho' => 'tercio'],
            ],
        ],

        /* ─── 3. Útero ─── */
        [
            'id'        => 'utero',
            'titulo'    => 'Útero',
            'icono'     => 'fa-solid fa-circle-nodes',
            'subtitulo' => 'Se realiza estudio en tiempo real con transductor multifrecuencia, observándose:',
            'campos'    => [
                ['nombre' => 'tipo_transductor', 'etiqueta' => 'Tipo de transductor', 'tipo' => 'radio',
                 'opciones' => ['Convex', 'Endocavitario'], 'ancho' => 'medio'],
                ['nombre' => 'situado_en',       'etiqueta' => 'Situado en',          'tipo' => 'radio',
                 'opciones' => ['AVF', 'Indiferente', 'Central', 'RVF'], 'ancho' => 'completo'],
                ['nombre' => 'lateralizado',     'etiqueta' => 'Lateralizado a',      'tipo' => 'radio',
                 'opciones' => ['Derecha', 'Izquierda'], 'ancho' => 'completo'],
                ['nombre' => 'bordes_regulares', 'etiqueta' => 'Bordes regulares',    'tipo' => 'radio_sinno', 'ancho' => 'completo'],
                ['nombre' => 'medida_l',         'etiqueta' => 'Diámetro L',          'tipo' => 'number', 'ancho' => 'tercio', 'unidad' => 'mm'],
                ['nombre' => 'medida_ap',        'etiqueta' => 'Diámetro AP',         'tipo' => 'number', 'ancho' => 'tercio', 'unidad' => 'mm'],
                ['nombre' => 'medida_t',         'etiqueta' => 'Diámetro T',          'tipo' => 'number', 'ancho' => 'tercio', 'unidad' => 'mm'],
            ],
        ],

        /* ─── 4. Endometrio ─── */
        [
            'id'     => 'endometrio',
            'titulo' => 'Endometrio',
            'icono'  => 'fa-solid fa-circle-dot',
            'campos' => [
                ['nombre' => 'normal',                  'etiqueta' => 'Normal',                                  'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'mide',                    'etiqueta' => 'Mide',                                    'tipo' => 'number',      'ancho' => 'medio',  'unidad' => 'mm'],
                ['nombre' => 'sin_nodulos_en_pared',    'etiqueta' => 'Sin imágenes nodulares visibles en pared','tipo' => 'radio_sinno', 'ancho' => 'completo'],
            ],
        ],

        /* ─── 5. Cuello Uterino ─── */
        [
            'id'     => 'cuello_uterino',
            'titulo' => 'Cuello Uterino',
            'icono'  => 'fa-solid fa-ring',
            'campos' => [
                ['nombre' => 'normal',               'etiqueta' => 'Normal',                                                              'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'sin_nodulos_naboth',   'etiqueta' => 'Sin imágenes nodulares en pared (quiste de Naboth)',                  'tipo' => 'radio_sinno', 'ancho' => 'completo'],
            ],
        ],

        /* ─── 6. Ovarios (par: Derecho / Izquierdo) ─── */
        [
            'id'           => 'ovarios',
            'titulo'       => 'Ovarios',
            'icono'        => 'fa-solid fa-circle-half-stroke',
            'tipo_seccion' => 'par',
            'lados'        => ['Ovario Derecho', 'Ovario Izquierdo'],
            'ids_lados'    => ['ovario_der', 'ovario_izq'],
            'campos'       => $campos_ovario,
        ],

        /* ─── 7. Fondo de Saco Posterior y Paredes Vaginales ─── */
        [
            'id'     => 'fondo_saco_paredes',
            'titulo' => 'Fondo de Saco Posterior y Paredes Vaginales',
            'icono'  => 'fa-solid fa-magnifying-glass',
            'campos' => [
                ['nombre' => 'fondo_saco_libre',                'etiqueta' => 'Fondo de saco posterior libre',                'tipo' => 'radio_sinno', 'ancho' => 'completo'],
                ['nombre' => 'paredes_vaginales_normal',        'etiqueta' => 'Paredes vaginales normales',                   'tipo' => 'radio_sinno', 'ancho' => 'completo'],
                ['nombre' => 'paredes_vaginales_sin_patologia', 'etiqueta' => 'Paredes vaginales — Sin imágenes patológicas', 'tipo' => 'radio_sinno', 'ancho' => 'completo'],
            ],
        ],

        /* ─── 8. Conclusión y Diagnóstico ─── */
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
$descripcion = 'Estudio ecográfico ginecológico: útero, endometrio, cuello, ovarios y fondo de saco.';
$icono       = 'fa-solid fa-venus';

$sql = "UPDATE tipos_ecografias
        SET esquema_campos  = ?,
            esquema_version = esquema_version + 1,
            descripcion     = ?,
            icono           = ?
        WHERE codigo = 'ECO_PELVICA'";

$stmt = $conex->prepare($sql);
$stmt->bind_param('sss', $json, $descripcion, $icono);

echo '<style>body{font-family:monospace;background:#f6f8fa;padding:24px;}pre{background:#f0fff4;padding:20px;border-radius:8px;font-size:13px;}</style>';

if ($stmt->execute()) {
    $filas = $stmt->affected_rows;
    $stmt->close();

    echo '<pre>';
    if ($filas > 0) {
        echo '<strong style="color:#15803d;">✔ Ecografía Pélvica (Ginecológica) actualizada correctamente.</strong>' . "\n\n";
    } else {
        echo '<strong style="color:#b45309;">⚠ No se encontró el registro (codigo=ECO_PELVICA).</strong>' . "\n\n";
    }
    echo 'Registros actualizados : ' . $filas . "\n";
    echo 'Secciones en esquema  : ' . count($schema['secciones']) . "\n\n";
    foreach ($schema['secciones'] as $s) {
        $c   = count($s['campos'] ?? []);
        $par = (($s['tipo_seccion'] ?? '') === 'par') ? ' [PAR: ' . implode(' / ', $s['lados']) . ']' : '';
        echo '  • ' . $s['titulo'] . ' — ' . $c . ' campo(s)' . $par . "\n";
    }
    echo "\n<strong>Siguiente paso:</strong> Recarga el modal y selecciona \"Ecografía Pélvica\".\n";
    echo '</pre>';
} else {
    echo '<p style="color:red;font-size:14px;"><strong>Error:</strong> ' . htmlspecialchars($conex->error) . '</p>';
    $stmt->close();
}

$conex->close();
