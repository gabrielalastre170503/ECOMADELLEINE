<?php
/**
 * Ejecutar UNA SOLA VEZ para actualizar el esquema de Ecografía de Muñeca y Mano
 * con la estructura LITERAL extraída del documento físico Dra. Madelleine Toro.
 * Acceder desde: http://localhost/Sistema_EcoMadelleineV1/database/seed_schema_muneca.php
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
                ['nombre' => 'nombres_apellidos', 'etiqueta' => 'Nombres y Apellidos',  'tipo' => 'text',     'ancho' => 'completo', 'requerido' => true],
                ['nombre' => 'edad',              'etiqueta' => 'Edad',                 'tipo' => 'number',   'ancho' => 'tercio',   'unidad' => 'años', 'min' => 0, 'readonly' => true],
                ['nombre' => 'cedula',            'etiqueta' => 'Cédula (CI)',          'tipo' => 'text',     'ancho' => 'tercio',   'readonly' => true],
                ['nombre' => 'fecha',             'etiqueta' => 'Fecha',                'tipo' => 'date',     'ancho' => 'tercio',   'requerido' => true, 'readonly' => true],
                ['nombre' => 'lado_estudiado',    'etiqueta' => 'Muñeca / Mano estudiada', 'tipo' => 'radio',
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
                ['nombre' => 'continua_ecogenica',          'etiqueta' => 'Continua ecogénica',                          'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'sin_evidencia_lesiones',      'etiqueta' => 'Sin evidencia de lesiones focales ni difusas','tipo' => 'radio_sinno', 'ancho' => 'medio'],
            ],
        ],

        /* ─── 3. Tejido Celular Subcutáneo ─── */
        [
            'id'     => 'tejido_subcutaneo',
            'titulo' => 'Tejido Celular Subcutáneo',
            'icono'  => 'fa-solid fa-layer-group',
            'campos' => [
                ['nombre' => 'cantidad', 'etiqueta' => 'Cantidad', 'tipo' => 'radio',
                 'opciones' => ['Abundante', 'Escaso', 'Hipoecoico'], 'ancho' => 'completo'],
                ['nombre' => 'sin_evidencia_lesiones', 'etiqueta' => 'Sin evidencia de lesiones focales ni difusas', 'tipo' => 'radio_sinno', 'ancho' => 'completo'],
            ],
        ],

        /* ─── 4. Cara Dorsal — Tendones Extensores (Compartimentos 1-6) ─── */
        [
            'id'     => 'cara_dorsal',
            'titulo' => 'Cara Dorsal — Tendones Extensores (Compartimentos 1; 2; 3; 4; 5 y 6)',
            'icono'  => 'fa-solid fa-hand-back-fist',
            'campos' => [
                ['nombre' => 'ubicacion_tamano_esperado',      'etiqueta' => 'Con ubicación anatómica y tamaño dentro de lo esperado', 'tipo' => 'radio_sinno', 'ancho' => 'completo'],
                ['nombre' => 'distribucion_lineal_ecogenico',  'etiqueta' => 'Con distribución lineal, patrón ecogénico',              'tipo' => 'radio_sinno', 'ancho' => 'completo'],
                ['nombre' => 'aspecto_uniforme',               'etiqueta' => 'De aspecto uniforme',                                    'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'sin_alteraciones_estructurales', 'etiqueta' => 'Sin evidencias de alteraciones estructurales',           'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'tendones_dedos_sin_alteracion',  'etiqueta' => 'Tendones de dedos sin alteración',                       'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'observaciones_dedos',            'etiqueta' => 'Observaciones (tendones de dedos)',                      'tipo' => 'text',        'ancho' => 'completo'],
            ],
        ],

        /* ─── 5. Cara Ventral — Tendón Flexor Radial del Carpo ─── */
        [
            'id'     => 'flexor_radial_carpo',
            'titulo' => 'Cara Ventral — Tendón Flexor Radial del Carpo',
            'icono'  => 'fa-solid fa-hand',
            'campos' => [
                ['nombre' => 'ubicacion_tamano_normal',        'etiqueta' => 'Con ubicación anatómica y tamaño normal',         'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'distribucion_lineal_ecogenicos', 'etiqueta' => 'Con distribución lineal, ecogénicos',             'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'aspecto_uniforme',               'etiqueta' => 'De aspecto uniforme',                             'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'sin_alteraciones_estructurales', 'etiqueta' => 'Sin evidencias de alteraciones estructurales',    'tipo' => 'radio_sinno', 'ancho' => 'medio'],
            ],
        ],

        /* ─── 6. Cara Ventral — Túnel del Carpo; Nervio Mediano ─── */
        [
            'id'     => 'tunel_carpo_mediano',
            'titulo' => 'Cara Ventral — Túnel del Carpo; Nervio Mediano',
            'icono'  => 'fa-solid fa-circle-nodes',
            'campos' => [
                ['nombre' => 'ubicacion_tamano_normal',        'etiqueta' => 'Con ubicación anatómica y tamaño normal',         'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'distribucion_lineal_ecogenicos', 'etiqueta' => 'Con distribución lineal, ecogénicos',             'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'aspecto_uniforme',               'etiqueta' => 'De aspecto uniforme',                             'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'sin_alteraciones_estructurales', 'etiqueta' => 'Sin evidencias de alteraciones estructurales',    'tipo' => 'radio_sinno', 'ancho' => 'medio'],
            ],
        ],

        /* ─── 7. Cara Ventral — Tendones Flexores Superficiales y Profundos ─── */
        [
            'id'     => 'flexores_sup_prof',
            'titulo' => 'Cara Ventral — Tendones Flexores Superficiales y Profundos',
            'icono'  => 'fa-solid fa-dna',
            'campos' => [
                ['nombre' => 'ubicacion_tamano_esperado',      'etiqueta' => 'Con ubicación anatómica y tamaño dentro de lo esperado',       'tipo' => 'radio_sinno', 'ancho' => 'completo'],
                ['nombre' => 'distribucion_lineal_ecogenicos', 'etiqueta' => 'Con distribución lineal, ecogénicos',                          'tipo' => 'radio_sinno', 'ancho' => 'completo'],
                ['nombre' => 'aspecto_uniforme_sin_alteraciones', 'etiqueta' => 'De aspecto uniforme, sin evidencias de alteraciones estructurales', 'tipo' => 'radio_sinno', 'ancho' => 'completo'],
            ],
        ],

        /* ─── 8. Articulaciones Metacarpo e Interfalángicas ─── */
        [
            'id'     => 'articulaciones_mcf_if',
            'titulo' => 'Articulaciones Metacarpo e Interfalángicas',
            'icono'  => 'fa-solid fa-hand-point-up',
            'campos' => [
                ['nombre' => 'aspecto_normal',           'etiqueta' => 'De aspecto normal',                  'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'sin_colecciones_liquidas', 'etiqueta' => 'Sin evidencia de colecciones líquidas','tipo' => 'radio_sinno', 'ancho' => 'medio'],
            ],
        ],

        /* ─── 9. Conclusión ─── */
        [
            'id'     => 'conclusion',
            'titulo' => 'Conclusión',
            'icono'  => 'fa-solid fa-file-medical',
            'campos' => [
                ['nombre' => 'conclusion', 'etiqueta' => 'Estudio Ecosonográfico de muñeca de mano — con signos sugestivos de:', 'tipo' => 'textarea', 'filas' => 6, 'ancho' => 'completo', 'requerido' => true],
            ],
        ],
    ],
];

$json        = json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
$descripcion = 'Informe ecosonográfico musculoesquelético de muñeca de mano: piel, subcutáneo, cara dorsal, cara ventral y articulaciones MCF/IFP.';
$icono       = 'fa-solid fa-hand';

$sql = "UPDATE tipos_ecografias
        SET esquema_campos  = ?,
            esquema_version = esquema_version + 1,
            descripcion     = ?,
            icono           = ?
        WHERE codigo = 'ECO_MUSCU_MUNECA'";

$stmt = $conex->prepare($sql);
$stmt->bind_param('sss', $json, $descripcion, $icono);

echo '<style>body{font-family:monospace;background:#f6f8fa;padding:24px;}pre{background:#f0fff4;padding:20px;border-radius:8px;font-size:13px;}</style>';

if ($stmt->execute()) {
    $filas = $stmt->affected_rows;
    $stmt->close();

    echo '<pre>';
    if ($filas > 0) {
        echo '<strong style="color:#15803d;">✔ Esquema de Muñeca y Mano actualizado (literal al documento físico).</strong>' . "\n\n";
    } else {
        echo '<strong style="color:#b45309;">⚠ No se encontró el registro (codigo=ECO_MUSCU_MUNECA).</strong>' . "\n\n";
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
    echo "\n<strong>Siguiente paso:</strong> Recarga (Ctrl+Shift+R), abre Musculoesquelética → Muñeca y Mano.\n";
    echo '</pre>';
} else {
    echo '<p style="color:red;font-size:14px;"><strong>Error:</strong> ' . htmlspecialchars($conex->error) . '</p>';
    $stmt->close();
}

$conex->close();
