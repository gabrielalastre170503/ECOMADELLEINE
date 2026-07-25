<?php
/**
 * Ejecutar UNA SOLA VEZ para actualizar el esquema de Ecografía Mamaria
 * con la estructura LITERAL extraída del documento físico Dra. Madelleine Toro.
 * Acceder desde: http://localhost/Sistema_EcoMadelleineV1/database/seed_schema_mamaria.php
 */
/* Guarda: escribe en la base de datos, solo por linea de comandos.
   Sin esto, cualquiera podia dispararlo desde el navegador. */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script solo puede ejecutarse por linea de comandos.');
}
include __DIR__ . '/../core/conexion.php';

/* Campos comunes a ambas mamas (par: Derecha / Izquierda) */
$campos_mama = [
    ['nombre' => 'piel_tejido_sin_alteraciones',   'etiqueta' => 'Piel y tejido celular subcutáneo sin alteraciones',                                                  'tipo' => 'radio_sinno', 'ancho' => 'completo'],
    ['nombre' => 'parenquima_mamario',             'etiqueta' => 'Parénquima mamario',                                                                                 'tipo' => 'radio',
     'opciones' => ['Escaso', 'Denso'], 'ancho' => 'completo'],
    ['nombre' => 'tejidofibroglandular',           'etiqueta' => 'Tejidofibroglandular, trabecular de ecogenicidad conservada',                                        'tipo' => 'radio_sinno', 'ancho' => 'completo'],
    ['nombre' => 'areola_cooper_normales',         'etiqueta' => 'Aréola y Ligamentos de Cooper normales, finos, con trayecto curvilíneo en malla, sin convergencia', 'tipo' => 'radio_sinno', 'ancho' => 'completo'],
    ['nombre' => 'conductos_galactoforos_normales','etiqueta' => 'Conductos Galactóforos terminales y conductos ductolobulillares normales',                          'tipo' => 'radio_sinno', 'ancho' => 'completo'],
    ['nombre' => 'ganglios_visualizados',          'etiqueta' => 'Ganglios visualizados',                                                                              'tipo' => 'radio_sinno', 'ancho' => 'completo'],
    ['nombre' => 'ganglios_observaciones',         'etiqueta' => 'Observaciones de ganglios',                                                                          'tipo' => 'text',        'ancho' => 'completo',
     'depende_de' => 'ganglios_visualizados', 'depende_valor' => 'SI'],
    ['nombre' => 'protesis_mamaria',               'etiqueta' => 'Prótesis mamaria',                                                                                   'tipo' => 'radio_sinno', 'ancho' => 'completo'],
    ['nombre' => 'tipo_protesis',                  'etiqueta' => 'Tipo de prótesis',                                                                                   'tipo' => 'radio',
     'opciones' => ['Retro glandular', 'Retro pectoral'], 'ancho' => 'completo',
     'depende_de' => 'protesis_mamaria', 'depende_valor' => 'SI'],
];

$schema = [
    'version'   => 1,
    'secciones' => [

        /* ─── 1. Encabezado ─── */
        [
            'id'        => 'encabezado',
            'titulo'    => 'Datos del Paciente',
            'icono'     => 'fa-solid fa-id-card',
            'subtitulo' => 'Se realiza estudio en tiempo real con transductor lineal multifrecuencia, barridos sagitales, observándose:',
            'campos'    => [
                ['nombre' => 'nombres_apellidos',       'etiqueta' => 'Nombres y Apellidos',          'tipo' => 'text',     'ancho' => 'completo', 'requerido' => true],
                ['nombre' => 'edad',                    'etiqueta' => 'Edad',                          'tipo' => 'number',   'ancho' => 'tercio',   'unidad' => 'años', 'min' => 0, 'readonly' => true],
                ['nombre' => 'cedula',                  'etiqueta' => 'Cédula (CI)',                   'tipo' => 'text',     'ancho' => 'tercio',   'readonly' => true],
                ['nombre' => 'fecha',                   'etiqueta' => 'Fecha',                         'tipo' => 'date',     'ancho' => 'tercio',   'requerido' => true, 'readonly' => true],
                ['nombre' => 'fur',                     'etiqueta' => 'FUR (Fecha de Última Regla)',   'tipo' => 'date',     'ancho' => 'medio'],
                ['nombre' => 'antecedentes_personales', 'etiqueta' => 'Antecedentes Patológicos Personales', 'tipo' => 'textarea', 'ancho' => 'completo', 'filas' => 2],
                ['nombre' => 'motivo_consulta',         'etiqueta' => 'Motivo de Consulta',            'tipo' => 'textarea', 'ancho' => 'completo', 'filas' => 2, 'requerido' => true],
            ],
        ],

        /* ─── 2. Información del Estudio ─── */
        [
            'id'        => 'info_estudio',
            'titulo'    => 'Información del Estudio',
            'icono'     => 'fa-solid fa-circle-info',
            'subtitulo' => 'Al estudio ecográfico practicado en tiempo real, con equipo Toshiba, Modo, Sin Doppler color, con transductor lineal de 10 MHz. Con barridos sagitales, longitudinales. Se observaron en pantalla los siguientes hallazgos:',
            'campos'    => [],
        ],

        /* ─── 3. Mamas (par: Derecha / Izquierda) ─── */
        [
            'id'           => 'mamas',
            'titulo'       => 'Hallazgos por Mama',
            'icono'        => 'fa-solid fa-venus',
            'tipo_seccion' => 'par',
            'lados'        => ['Mama Derecha', 'Mama Izquierda'],
            'ids_lados'    => ['mama_der', 'mama_izq'],
            'campos'       => $campos_mama,
        ],

        /* ─── 4. Conclusión ─── */
        [
            'id'     => 'conclusion',
            'titulo' => 'Conclusión',
            'icono'  => 'fa-solid fa-file-medical',
            'campos' => [
                ['nombre' => 'conclusion', 'etiqueta' => 'Conclusión', 'tipo' => 'textarea', 'filas' => 6, 'ancho' => 'completo', 'requerido' => true],
            ],
        ],
    ],
];

$json        = json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
$descripcion = 'Reporte ecográfico mamario: hallazgos por mama (derecha e izquierda) + prótesis.';
$icono       = 'fa-solid fa-venus';

$sql = "UPDATE tipos_ecografias
        SET esquema_campos  = ?,
            esquema_version = esquema_version + 1,
            descripcion     = ?,
            icono           = ?
        WHERE codigo = 'ECO_MAMA'";

$stmt = $conex->prepare($sql);
$stmt->bind_param('sss', $json, $descripcion, $icono);

echo '<style>body{font-family:monospace;background:#f6f8fa;padding:24px;}pre{background:#f0fff4;padding:20px;border-radius:8px;font-size:13px;}</style>';

if ($stmt->execute()) {
    $filas = $stmt->affected_rows;
    $stmt->close();

    echo '<pre>';
    if ($filas > 0) {
        echo '<strong style="color:#15803d;">✔ Esquema de Ecografía Mamaria actualizado (literal al documento físico).</strong>' . "\n\n";
    } else {
        echo '<strong style="color:#b45309;">⚠ No se encontró el registro (codigo=ECO_MAMA).</strong>' . "\n\n";
    }
    echo 'Registros actualizados : ' . $filas . "\n";
    echo 'Secciones en esquema  : ' . count($schema['secciones']) . "\n\n";
    $total = 0;
    foreach ($schema['secciones'] as $s) {
        $c   = count($s['campos'] ?? []);
        $par = (($s['tipo_seccion'] ?? '') === 'par') ? ' [PAR: ' . implode(' / ', $s['lados']) . ']' : '';
        $total += $c;
        echo '  • ' . $s['titulo'] . ' — ' . $c . ' campo(s)' . $par . "\n";
    }
    echo "\n<strong>Total de campos:</strong> " . $total . "\n";
    echo "\n<strong>Siguiente paso:</strong> Recarga (Ctrl+Shift+R), abre la modal de Ecografía Mamaria.\n";
    echo '</pre>';
} else {
    echo '<p style="color:red;font-size:14px;"><strong>Error:</strong> ' . htmlspecialchars($conex->error) . '</p>';
    $stmt->close();
}

$conex->close();
