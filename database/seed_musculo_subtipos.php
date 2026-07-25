<?php
/**
 * Crea/actualiza 6 sub-tipos de Ecografía Musculoesquelética:
 * Hombro, Codo, Muñeca/Mano, Cadera, Rodilla, Tobillo.
 *
 * Ejecutar UNA SOLA VEZ:
 *   http://localhost/Sistema_EcoMadelleineV1/database/seed_musculo_subtipos.php
 */
/* Guarda: escribe en la base de datos, solo por linea de comandos.
   Sin esto, cualquiera podia dispararlo desde el navegador. */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script solo puede ejecutarse por linea de comandos.');
}
include __DIR__ . '/../core/conexion.php';

/* Schema genérico reutilizable por todas las articulaciones */
function schema_articulacion(string $articulacion): array
{
    return [
        'version'   => 1,
        'secciones' => [

            /* 1. Encabezado */
            [
                'id'        => 'encabezado',
                'titulo'    => 'Datos del Paciente',
                'icono'     => 'fa-solid fa-id-card',
                'subtitulo' => 'Se realiza estudio en tiempo real con transductor lineal multifrecuencia, observándose:',
                'campos'    => [
                    ['nombre' => 'nombres_apellidos', 'etiqueta' => 'Nombres y Apellidos', 'tipo' => 'text',     'ancho' => 'completo', 'requerido' => true],
                    ['nombre' => 'edad',              'etiqueta' => 'Edad',                'tipo' => 'number',   'ancho' => 'tercio',   'unidad' => 'años', 'min' => 0, 'readonly' => true],
                    ['nombre' => 'cedula',            'etiqueta' => 'Cédula (CI)',         'tipo' => 'text',     'ancho' => 'tercio',   'readonly' => true],
                    ['nombre' => 'fecha',             'etiqueta' => 'Fecha',               'tipo' => 'date',     'ancho' => 'tercio',   'requerido' => true, 'readonly' => true],
                    ['nombre' => 'motivo_consulta',   'etiqueta' => 'Motivo de Consulta',  'tipo' => 'textarea', 'ancho' => 'completo', 'filas' => 2, 'requerido' => true],
                ],
            ],

            /* 2. Estudio de la articulación */
            [
                'id'     => 'estudio',
                'titulo' => 'Estudio de ' . $articulacion,
                'icono'  => 'fa-solid fa-bone',
                'campos' => [
                    ['nombre' => 'lado_estudiado', 'etiqueta' => 'Lado estudiado', 'tipo' => 'radio',
                     'opciones' => ['Derecho', 'Izquierdo', 'Bilateral'], 'ancho' => 'completo'],
                    ['nombre' => 'estructuras_normales', 'etiqueta' => 'Estructuras anatómicas normales', 'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                    ['nombre' => 'derrame_articular',    'etiqueta' => 'Derrame articular',               'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                    ['nombre' => 'lesiones_partes_blandas', 'etiqueta' => 'Lesiones en partes blandas',   'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                    ['nombre' => 'imagenes_patologicas',  'etiqueta' => 'Imágenes patológicas',           'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                    ['nombre' => 'hallazgos',  'etiqueta' => 'Hallazgos descriptivos', 'tipo' => 'textarea', 'filas' => 6, 'ancho' => 'completo'],
                ],
            ],

            /* 3. Conclusión */
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
}

/* Definición de las 6 articulaciones */
$articulaciones = [
    ['codigo' => 'ECO_MUSCU_HOMBRO',   'nombre' => 'Ecografía de Hombro',        'art' => 'Hombro',           'icono' => 'fa-solid fa-person',         'pos' => 51, 'desc' => 'Estudio ecográfico de la articulación glenohumeral.'],
    ['codigo' => 'ECO_MUSCU_CODO',     'nombre' => 'Ecografía de Codo',          'art' => 'Codo',             'icono' => 'fa-solid fa-bone',           'pos' => 52, 'desc' => 'Estudio ecográfico de la articulación humero-cubital.'],
    ['codigo' => 'ECO_MUSCU_MUNECA',   'nombre' => 'Ecografía de Muñeca y Mano', 'art' => 'Muñeca y Mano',    'icono' => 'fa-solid fa-hand',           'pos' => 53, 'desc' => 'Estudio ecográfico de muñeca, túnel carpiano y mano.'],
    ['codigo' => 'ECO_MUSCU_CADERA',   'nombre' => 'Ecografía de Cadera',        'art' => 'Cadera',           'icono' => 'fa-solid fa-person-walking', 'pos' => 54, 'desc' => 'Estudio ecográfico de la articulación coxofemoral.'],
    ['codigo' => 'ECO_MUSCU_RODILLA',  'nombre' => 'Ecografía de Rodilla',       'art' => 'Rodilla',          'icono' => 'fa-solid fa-person-running', 'pos' => 55, 'desc' => 'Estudio ecográfico de la articulación femorotibial.'],
    ['codigo' => 'ECO_MUSCU_TOBILLO',  'nombre' => 'Ecografía de Tobillo',       'art' => 'Tobillo',          'icono' => 'fa-solid fa-shoe-prints',    'pos' => 56, 'desc' => 'Estudio ecográfico de la articulación tibioastragalina.'],
];

echo '<style>body{font-family:monospace;background:#f6f8fa;padding:24px;}pre{background:#f0fff4;padding:20px;border-radius:8px;font-size:13px;}.err{background:#fff5f5;color:#b91c1c;padding:12px;border-radius:8px;}</style>';
echo '<pre>';
echo "Sembrando sub-tipos de Ecografía Musculoesquelética…\n\n";

$insertadas = 0;
$actualizadas = 0;

foreach ($articulaciones as $a) {
    $json = json_encode(schema_articulacion($a['art']), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    /* Upsert por codigo (UNIQUE) */
    $sql = "INSERT INTO tipos_ecografias
                (codigo, nombre, categoria, descripcion, icono, esquema_campos, esquema_version, activo, posicion)
            VALUES (?, ?, 'Musculoesqueletica_Sub', ?, ?, ?, 1, 1, ?)
            ON DUPLICATE KEY UPDATE
                nombre          = VALUES(nombre),
                categoria       = VALUES(categoria),
                descripcion     = VALUES(descripcion),
                icono           = VALUES(icono),
                esquema_campos  = VALUES(esquema_campos),
                esquema_version = esquema_version + 1,
                posicion        = VALUES(posicion)";

    $stmt = $conex->prepare($sql);
    if (!$stmt) {
        echo "  [ERROR] prepare: " . htmlspecialchars($conex->error) . "\n";
        continue;
    }

    $stmt->bind_param('sssssi', $a['codigo'], $a['nombre'], $a['desc'], $a['icono'], $json, $a['pos']);

    if ($stmt->execute()) {
        if ($stmt->affected_rows >= 1 && $stmt->insert_id > 0) {
            $insertadas++;
            echo "  ✔ [INSERT] " . $a['nombre'] . " (id=" . $stmt->insert_id . ")\n";
        } else {
            $actualizadas++;
            echo "  ✔ [UPDATE] " . $a['nombre'] . "\n";
        }
    } else {
        echo "  [ERROR] " . htmlspecialchars($stmt->error) . " — " . $a['codigo'] . "\n";
    }
    $stmt->close();
}

echo "\n";
echo "<strong style=\"color:#15803d;\">✔ Listo.</strong>\n";
echo "Insertadas : $insertadas\n";
echo "Actualizadas: $actualizadas\n\n";
echo "<strong>Siguiente paso:</strong> Recarga el sistema. La tarjeta \"Musculoesquelética\"\n";
echo "abrirá un sub-selector con las 6 articulaciones.\n";
echo '</pre>';

$conex->close();
